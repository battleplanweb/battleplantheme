<?php
/* Battle Plan Web Design - HVAC Replacement Estimator
 *
 * A homeowner answers a few questions and sees an installed price range + monthly payment with no
 * contact details. They can then ask for a free in-home estimate or email themselves the result.
 * Full write-up: docs/hvac-estimator.md
 *
 * Opt-in per site. The module loads when the site hooks `bp_hvac_estimator_config` in
 * functions-site.php (that filter is where the client's prices, tiers, phone and copy live), or when
 * the `hvac_estimator` option has install => true.
 *
 * ONE implementation of the rules: the question flow (which question is asked, which answer ends the
 * quiz), sizing, tier mapping, pricing and the monthly figure are all computed HERE. The page's JS only
 * walks the flow data this file hands it and asks the server for the result; the homeowner email and both
 * office emails are built from the same bp_est_compute() result.
 *
 * Endpoints (anonymous, rate-limited, page-cache safe — no WP nonce):
 *   POST /wp-json/bp/v1/estimator/quote    { sid, answers }                    -> result (+ completion email)
 *   POST /wp-json/bp/v1/estimator/contact  { sid, answers, action, name, email, phone, t, sig, hp }
 *
 * Shortcode: [hvac-estimator]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const BP_EST_DB_VERSION = '1.0';


/*--------------------------------------------------------------
# Configuration
--------------------------------------------------------------*/

/**
 * Framework defaults. A site overrides any of it with the `bp_hvac_estimator_config` filter — the prices,
 * tier labels/series, financing disclosure, phone and office email are expected to come from the site.
 *
 * Prices are WHOLE DOLLARS, [low, high], keyed  prices[system_type][size_used_for_pricing][tier].
 * A size/tier pair missing from the table is never guessed: the homeowner gets the free in-home estimate
 * outcome and the office email says why.
 */
function bp_est_defaults(): array {
	$ci    = function_exists( 'customer_info' ) ? customer_info() : [];
	$phone = trim( ( $ci['area'] ?? '' ) . '-' . ( $ci['phone'] ?? '' ), '-' );

	return [
		'company'      => function_exists( 'bp_business_name' ) ? bp_business_name() : get_bloginfo( 'name' ),
		'phone'        => $phone,
		'office_email' => $ci['email'] ?? get_option( 'admin_email' ),
		'ref_prefix'   => 'EST',

		// "3 or more systems in the home": 'call' ends the quiz with a call-only result. 'ask' asks which
		// to replace (Just one / More than one) and prices "Just one" like a single system.
		'three_plus'   => 'call',

		// Monthly payment = installed price × factor. Basis points, so 125 = 1.25%. Integer math, rounded
		// to the nearest dollar with exact halves to the even dollar (see bp_est_monthly()).
		'payment_factor_bp' => 125,

		'financing' => [
			'provider'   => '',
			'disclosure' => '',
		],

		'system_types' => [
			'gas'      => [ 'label' => 'Gas furnace with AC', 'desc' => 'A furnace inside, an AC unit outside', 'priced' => true, 'product_type' => 'air-conditioners' ],
			'heatpump' => [ 'label' => 'Heat pump',           'desc' => 'Heats and cools with one outdoor unit', 'priced' => true, 'product_type' => 'heat-pumps' ],
			'notsure'  => [ 'label' => 'Not sure',            'desc' => 'We\'ll figure it out when we visit',    'priced' => false ],
		],

		'locations' => [
			'attic'  => [ 'label' => 'Attic' ],
			'closet' => [ 'label' => 'Closet' ],
			'garage' => [ 'label' => 'Garage' ],
		],

		'tiers' => [
			'good'   => [ 'label' => 'Good',   'desc' => 'Single-stage',   'series' => '' ],
			'better' => [ 'label' => 'Better', 'desc' => 'Two-stage',      'series' => '' ],
			'best'   => [ 'label' => 'Best',   'desc' => 'Variable-speed', 'series' => '' ],
		],

		// Area served by the system being replaced. `tons` = the sizing estimate. `load_calc` adds the
		// "most homes this size need two systems" message to a single-system result.
		'sizes' => [
			'u800'  => [ 'label' => 'Under 800 sq ft',   'tons' => '2',   'load_calc' => false ],
			'800'   => [ 'label' => '800–999 sq ft',     'tons' => '2.5', 'load_calc' => false ],
			'1000'  => [ 'label' => '1,000–1,199 sq ft', 'tons' => '3',   'load_calc' => false ],
			'1200'  => [ 'label' => '1,200–1,399 sq ft', 'tons' => '3.5', 'load_calc' => false ],
			'1400'  => [ 'label' => '1,400–1,599 sq ft', 'tons' => '4',   'load_calc' => false ],
			'1600'  => [ 'label' => '1,600–1,999 sq ft', 'tons' => '5',   'load_calc' => false ],
			'2000'  => [ 'label' => '2,000 sq ft and up','tons' => '5',   'load_calc' => true  ],
		],

		// Series that don't come in a size: price them at another size. [tier][estimated tons] => tons used.
		'size_substitutions' => [
			'better' => [ '2.5' => '3', '3.5' => '4' ],
			'best'   => [ '2.5' => '3', '3.5' => '4' ],
		],

		'prices' => [],

		'copy' => [
			'intro'           => 'Answer a few quick questions to see an estimated installed price. No contact info needed.',
			'sqft_hint'       => 'Not sure? Upstairs systems usually cool about 40% of the home.',
			'load_calc'       => 'Most homes this size need two systems. Exact price requires a load calculation.',
			'rough_note'      => 'This is a rough estimate for equipment, standard installation, and our standard material kit. We\'ll confirm your exact installed price after a free in-home evaluation.',
			'call_title'      => 'Call us for a quote',
			'call_text'       => 'Homes with more than one system need a quick conversation so we can quote it properly.',
			'inhome_title'    => 'Let\'s take a look in person',
			'inhome_text'     => 'We\'ll identify your system and give you an exact installed price during a free in-home estimate.',
			'estimate_cta'    => 'Book a free in-home estimate',
			'email_cta'       => 'Email me my estimate',
			'estimate_done'   => 'Thanks! We received your request. Our office will call you to set up your free in-home estimate.',
			'email_done'      => 'Sent! Check your inbox for your estimate.',
		],
	];
}

/** Merged, validated config (cached per request). */
function bp_est_config(): array {
	static $cfg = null;
	if ( $cfg !== null ) return $cfg;

	// Site config = the hvac_estimator option, saved by battleplan_updateSiteOptions() in functions-site.php
	// (same install pattern as site_pulse / jobsite_geo). Each key replaces the default, except copy and
	// financing, which merge so a site can override a single sentence.
	$cfg = bp_est_defaults();
	$opt = get_option( 'hvac_estimator' );
	if ( is_array( $opt ) ) {
		unset( $opt['install'] );
		foreach ( $opt as $k => $v ) {
			$cfg[ $k ] = ( in_array( $k, [ 'copy', 'financing' ], true ) && is_array( $v ) ) ? array_merge( $cfg[ $k ], $v ) : $v;
		}
	}
	// Code-level override, if a site ever needs one; most never will.
	$cfg = apply_filters( 'bp_hvac_estimator_config', $cfg );

	// Lists the site replaces wholesale, not merges (a site with two tiers shouldn't inherit a third).
	$cfg['payment_factor_bp'] = max( 0, (int) $cfg['payment_factor_bp'] );
	$cfg['three_plus']        = $cfg['three_plus'] === 'ask' ? 'ask' : 'call';
	return $cfg;
}

/** Whole-dollar integer from a config price (accepts 10700, '10700', '$10,700'). */
function bp_est_dollars( $v ): int {
	return (int) preg_replace( '/[^0-9]/', '', (string) $v );
}

function bp_est_money( int $n ): string {
	return '$' . number_format( $n );
}

/**
 * Monthly payment in whole dollars = price × factor_bp / 10000, rounded to the nearest dollar, exact halves
 * to the EVEN dollar (11,400 × 1.25% = 142.50 → 142; 14,200 × 1.25% = 177.50 → 178). Pure integer math.
 */
function bp_est_monthly( int $price, int $factor_bp ): int {
	$num = $price * $factor_bp;          // in 1/10000ths of a dollar
	$q   = intdiv( $num, 10000 );
	$r   = $num % 10000;
	if ( $r > 5000 || ( $r === 5000 && $q % 2 === 1 ) ) $q++;
	return $q;
}


/*--------------------------------------------------------------
# Question flow — the single definition both PHP and JS walk
--------------------------------------------------------------*/

/**
 * Questions in order. Each:
 *   ask_if     [ question => [answers] ]  asked only when every listed question has one of those answers
 *   end_if     [ answers ]                choosing one of these finishes the quiz (no further questions)
 *   depends_on [ questions ]              changing any of these clears this answer
 *   choices_by [ question => [ answer => choice keys ] ]  narrows the choices by an earlier answer
 */
function bp_est_questions( ?array $cfg = null ): array {
	$cfg = $cfg ?? bp_est_config();

	$opts = fn( array $list ) => array_map( fn( $k, $v ) => [ 'key' => (string) $k, 'label' => $v['label'], 'desc' => $v['desc'] ?? '' ], array_keys( $list ), $list );

	$systems_ending = $cfg['three_plus'] === 'call' ? [ '3' ] : [];
	$replace_asked  = $cfg['three_plus'] === 'call' ? [ '2' ] : [ '2', '3' ];

	$type_ending = [];
	foreach ( $cfg['system_types'] as $k => $t ) if ( empty( $t['priced'] ) ) $type_ending[] = (string) $k;

	return [
		[
			'id'      => 'systems',
			'title'   => 'How many AC systems does your home have?',
			'help'    => 'Count the outdoor AC or heat pump units.',
			'choices' => [
				[ 'key' => '1', 'label' => '1', 'desc' => 'One system for the whole home' ],
				[ 'key' => '2', 'label' => '2', 'desc' => 'Usually one upstairs and one downstairs' ],
				[ 'key' => '3', 'label' => '3 or more', 'desc' => '' ],
			],
			'end_if'  => $systems_ending,
		],
		[
			'id'         => 'replace',
			'title'      => 'Which one are you replacing?',
			'help'       => '',
			'choices'    => [
				[ 'key' => 'upstairs',   'label' => 'Upstairs',          'desc' => '' ],
				[ 'key' => 'downstairs', 'label' => 'Downstairs',        'desc' => '' ],
				[ 'key' => 'both',       'label' => 'Both',              'desc' => '' ],
				[ 'key' => 'one',        'label' => 'Just one of them',  'desc' => '' ],
				[ 'key' => 'multiple',   'label' => 'More than one',     'desc' => '' ],
			],
			'choices_by' => [ 'systems' => [ '2' => [ 'upstairs', 'downstairs', 'both' ], '3' => [ 'one', 'multiple' ] ] ],
			'ask_if'     => [ 'systems' => $replace_asked ],
			'end_if'     => [ 'both', 'multiple' ],
			'depends_on' => [ 'systems' ],
		],
		[
			'id'         => 'sqft',
			'title'      => 'About how many square feet does that system cool?',
			'help'       => $cfg['copy']['sqft_hint'],
			'choices'    => $opts( $cfg['sizes'] ),
			'depends_on' => [ 'systems', 'replace' ],
		],
		[
			'id'      => 'type',
			'title'   => 'What type of system do you have now?',
			'help'    => '',
			'choices' => $opts( $cfg['system_types'] ),
			'end_if'  => $type_ending,
		],
		[
			'id'      => 'location',
			'title'   => 'Where is your indoor unit?',
			'help'    => 'The furnace or air handler.',
			'choices' => $opts( $cfg['locations'] ),
		],
		[
			'id'      => 'tier',
			'title'   => 'Which comfort level fits you best?',
			'help'    => '',
			'choices' => array_map( fn( $k, $t ) => [ 'key' => (string) $k, 'label' => $t['label'] . ' — ' . strtolower( $t['desc'] ), 'desc' => $t['series'] ?? '' ], array_keys( $cfg['tiers'] ), $cfg['tiers'] ),
		],
	];
}

/** Choice keys a question offers, given the answers so far. */
function bp_est_choice_keys( array $q, array $answers ): array {
	$keys = array_column( $q['choices'], 'key' );
	foreach ( $q['choices_by'] ?? [] as $dep => $map ) {
		$a = $answers[ $dep ] ?? null;
		if ( $a !== null && isset( $map[ $a ] ) ) $keys = array_values( array_intersect( $keys, $map[ $a ] ) );
	}
	return $keys;
}

function bp_est_is_asked( array $q, array $answers ): bool {
	foreach ( $q['ask_if'] ?? [] as $dep => $allowed ) {
		if ( ! in_array( (string) ( $answers[ $dep ] ?? '' ), $allowed, true ) ) return false;
	}
	return true;
}

/**
 * Walk the flow. Returns:
 *   ok        true when $answers is exactly a valid, finished path
 *   asked     question ids on the path, in order
 *   error     why it isn't (unknown answer, missing answer, answer to a question not on the path)
 */
function bp_est_walk( array $answers, ?array $cfg = null ): array {
	$questions = bp_est_questions( $cfg );
	$asked     = [];
	$ended     = false;

	foreach ( $questions as $q ) {
		$id = $q['id'];
		if ( $ended || ! bp_est_is_asked( $q, $answers ) ) {
			if ( array_key_exists( $id, $answers ) ) return [ 'ok' => false, 'asked' => $asked, 'error' => "'$id' is not on this path" ];
			continue;
		}
		if ( ! isset( $answers[ $id ] ) || $answers[ $id ] === '' ) return [ 'ok' => false, 'asked' => $asked, 'error' => "'$id' is unanswered" ];
		if ( ! in_array( $answers[ $id ], bp_est_choice_keys( $q, $answers ), true ) ) return [ 'ok' => false, 'asked' => $asked, 'error' => "'$id' has an invalid answer" ];
		$asked[] = $id;
		if ( in_array( $answers[ $id ], $q['end_if'] ?? [], true ) ) $ended = true;
	}

	foreach ( array_keys( $answers ) as $k ) {
		if ( ! in_array( $k, array_column( $questions, 'id' ), true ) ) return [ 'ok' => false, 'asked' => $asked, 'error' => "unknown question '$k'" ];
	}
	return [ 'ok' => true, 'asked' => $asked, 'error' => '' ];
}

/** Keep only string answers to known questions (what a request is allowed to carry). */
function bp_est_clean_answers( $raw ): array {
	$out = [];
	if ( ! is_array( $raw ) ) return $out;
	foreach ( $raw as $k => $v ) {
		if ( ! is_scalar( $v ) ) continue;
		$k = preg_replace( '/[^a-z_]/', '', strtolower( (string) $k ) );
		$v = preg_replace( '/[^a-z0-9_.]/', '', strtolower( (string) $v ) );
		if ( $k !== '' && $v !== '' ) $out[ $k ] = substr( $v, 0, 20 );
	}
	return $out;
}


/*--------------------------------------------------------------
# The result — the ONE pricing/eligibility implementation
--------------------------------------------------------------*/

/**
 * Compute the outcome for a finished, valid answer set (call bp_est_walk first).
 *
 * outcome  'priced'  installed range + monthly
 *          'call'    more than one system to replace → "Call us for a quote", no price
 *          'inhome'  can't price it from the answers (system type not sure, or a price missing from the
 *                    table) → free in-home estimate, no price
 */
function bp_est_compute( array $answers, ?array $cfg = null ): array {
	$cfg = $cfg ?? bp_est_config();

	$systems = $answers['systems'] ?? '';
	$replace = $answers['replace'] ?? '';
	$size    = $cfg['sizes'][ $answers['sqft'] ?? '' ] ?? null;
	$type    = $answers['type'] ?? '';
	$tier    = $answers['tier'] ?? '';

	$r = [
		'outcome'       => 'priced',
		'reason'        => '',
		'replace_count' => ( $replace === 'both' || $replace === 'multiple' || ( $systems === '3' && $replace === '' ) ) ? 'multiple' : 'one',
		'size_tons'     => $size['tons'] ?? null,
		'priced_tons'   => null,
		'load_calc'     => false,
		'series'        => $cfg['tiers'][ $tier ]['series'] ?? '',
		'tier_label'    => $cfg['tiers'][ $tier ]['label'] ?? '',
		'type_label'    => $cfg['system_types'][ $type ]['label'] ?? '',
		'type_key'      => $type,
		'tier_key'      => $tier,
		'price_low'     => null,
		'price_high'    => null,
		'monthly_low'   => null,
		'monthly_high'  => null,
	];

	if ( $systems === '3' && $replace === '' ) {
		return array_merge( $r, [ 'outcome' => 'call', 'reason' => 'Home has 3 or more systems — call-only under the current rule.' ] );
	}
	if ( $r['replace_count'] === 'multiple' ) {
		return array_merge( $r, [ 'outcome' => 'call', 'reason' => 'Replacing more than one system — call-only, ranges are never added together.' ] );
	}
	if ( isset( $cfg['system_types'][ $type ] ) && empty( $cfg['system_types'][ $type ]['priced'] ) ) {
		return array_merge( $r, [ 'outcome' => 'inhome', 'reason' => 'Existing system type is "' . $r['type_label'] . '" — no gas or heat pump price assigned.' ] );
	}

	$tons        = (string) $r['size_tons'];
	$priced_tons = (string) ( $cfg['size_substitutions'][ $tier ][ $tons ] ?? $tons );
	$range       = $cfg['prices'][ $type ][ $priced_tons ][ $tier ] ?? null;
	$low         = is_array( $range ) ? bp_est_dollars( $range[0] ?? 0 ) : 0;
	$high        = is_array( $range ) ? bp_est_dollars( $range[1] ?? 0 ) : 0;

	if ( $low <= 0 || $high < $low ) {
		error_log( "bp-estimator: no price configured for {$type} / {$priced_tons} ton / {$tier}" );
		return array_merge( $r, [ 'outcome' => 'inhome', 'priced_tons' => $priced_tons, 'reason' => "No price configured for {$r['type_label']} / {$priced_tons} ton / {$r['tier_label']}." ] );
	}

	return array_merge( $r, [
		'priced_tons'  => $priced_tons,
		'load_calc'    => ! empty( $size['load_calc'] ),
		'price_low'    => $low,
		'price_high'   => $high,
		'monthly_low'  => bp_est_monthly( $low, $cfg['payment_factor_bp'] ),
		'monthly_high' => bp_est_monthly( $high, $cfg['payment_factor_bp'] ),
	] );
}

/**
 * Every question with what the homeowner chose, or null when it wasn't asked/answered — the shape the
 * office email and the log both read.
 */
function bp_est_answer_rows( array $answers, ?array $cfg = null ): array {
	$rows = [];
	foreach ( bp_est_questions( $cfg ) as $q ) {
		$a     = $answers[ $q['id'] ] ?? null;
		$label = null;
		if ( $a !== null ) foreach ( $q['choices'] as $c ) if ( $c['key'] === $a ) $label = $c['label'];
		$rows[ $q['id'] ] = [ 'question' => $q['title'], 'answer' => $label ];
	}
	return $rows;
}

/** Short tons label: '2.5' → '2.5 ton'. */
function bp_est_tons( $t ): string {
	return $t === null || $t === '' ? '' : $t . ' ton';
}

/** Completion reference: stable for the same browser session + same answers, so refreshes don't re-notify. */
function bp_est_ref( string $sid, array $answers ): string {
	ksort( $answers );
	$h      = hash_hmac( 'sha256', $sid . '|' . wp_json_encode( $answers ), wp_salt( 'auth' ) . 'bp-estimator' );
	$alpha  = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I
	$code   = '';
	for ( $i = 0; $i < 8; $i++ ) $code .= $alpha[ hexdec( substr( $h, $i * 2, 2 ) ) % 32 ];
	$prefix = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) bp_est_config()['ref_prefix'] ) ) ?: 'EST';
	return $prefix . '-' . $code;
}

/** What the browser gets back: display strings only, all derived from the computed result. */
function bp_est_public_result( array $r, string $ref, ?array $cfg = null ): array {
	$cfg = $cfg ?? bp_est_config();
	$out = [
		'outcome' => $r['outcome'],
		'ref'     => $ref,
	];
	if ( $r['outcome'] === 'priced' ) {
		$out += [
			'type'       => $r['type_label'],
			'tier'       => $r['tier_label'],
			'series'     => $r['series'],
			'monthly'    => bp_est_money( $r['monthly_low'] ),
			'range'      => bp_est_money( $r['price_low'] ) . '–' . bp_est_money( $r['price_high'] ),
			'financing'  => $cfg['financing']['disclosure'],
			'provider'   => $cfg['financing']['provider'],
			'note'       => $cfg['copy']['rough_note'],
			'load_calc'  => $r['load_calc'] ? $cfg['copy']['load_calc'] : '',
			'product'    => bp_est_product( $r['type_key'], $r['tier_key'], $cfg ),
		];
	} elseif ( $r['outcome'] === 'call' ) {
		$out += [ 'title' => $cfg['copy']['call_title'], 'text' => $cfg['copy']['call_text'] ];
	} else {
		$out += [ 'title' => $cfg['copy']['inhome_title'], 'text' => $cfg['copy']['inhome_text'] ];
	}
	return $out;
}

/**
 * The site's own product (the products CPT from includes-hvac.php) for this system type + tier, so the result
 * can show its photo. Matched by the tier's series as a whole phrase in the product title ("Gold 17" matches
 * "Gold 17 Multi-Speed Air Conditioner", not "Gold 17X"), within the system type's product-type term
 * (gas → air-conditioners, heat pump → heat-pumps). Only a product with a featured image counts.
 * Pin one by hand with tiers[tier]['product'][type] = post ID; 'product' => false turns the lookup off for a tier.
 * Returns null when nothing matches — the result simply shows no picture. Cached 12h (cleared on product save).
 */
function bp_est_product( string $type, string $tier, array $cfg ): ?array {
	$t_cfg  = $cfg['tiers'][ $tier ] ?? [];
	$series = trim( (string) ( $t_cfg['series'] ?? '' ) );
	$term   = (string) ( $cfg['system_types'][ $type ]['product_type'] ?? '' );
	if ( ( $t_cfg['product'] ?? null ) === false || ! post_type_exists( 'products' ) ) return null;

	$pinned = (int) ( $t_cfg['product'][ $type ] ?? 0 );
	if ( ! $pinned && ( $series === '' || $term === '' ) ) return null;

	$key    = 'bp_est_product_' . md5( $type . '|' . $tier . '|' . $series . '|' . $pinned . '|' . get_option( 'bp_est_product_gen', 0 ) );
	$cached = get_transient( $key );
	if ( is_array( $cached ) ) return $cached ?: null;

	$id = 0;
	if ( $pinned && get_post_status( $pinned ) === 'publish' && has_post_thumbnail( $pinned ) ) {
		$id = $pinned;
	} elseif ( ! $pinned ) {
		$ids = get_posts( [
			'post_type'      => 'products',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'fields'         => 'ids',
			's'              => $series,
			'tax_query'      => [ [ 'taxonomy' => 'product-type', 'field' => 'slug', 'terms' => $term ] ],
			'no_found_rows'  => true,
		] );
		$re = '/(?<![\w])' . preg_quote( $series, '/' ) . '(?![\w])/i';
		foreach ( $ids as $pid ) {
			if ( preg_match( $re, get_the_title( $pid ) ) && has_post_thumbnail( $pid ) ) { $id = $pid; break; }
		}
	}

	$out = [];
	if ( $id ) {
		$thumb = get_post_thumbnail_id( $id );
		$src   = wp_get_attachment_image_src( $thumb, 'medium' );
		if ( $src ) {
			$alt = trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) );
			$out = [
				'title'  => wp_strip_all_tags( get_the_title( $id ) ),
				'url'    => get_permalink( $id ),
				'img'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => $alt !== '' ? $alt : wp_strip_all_tags( get_the_title( $id ) ),
			];
		}
	}
	set_transient( $key, $out, 12 * HOUR_IN_SECONDS );
	return $out ?: null;
}

// A product edit / new photo shows on the next quote instead of after the 12h cache.
add_action( 'save_post_products', function () { update_option( 'bp_est_product_gen', time(), false ); } );


/*--------------------------------------------------------------
# Completion log (one row per completed quiz)
--------------------------------------------------------------*/

function bp_est_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'bp_estimates';
}

add_action( 'init', 'bp_est_install_table' );
function bp_est_install_table(): void {
	if ( get_option( 'bp_est_db_version' ) === BP_EST_DB_VERSION ) return;
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = bp_est_table();
	dbDelta( "CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ref varchar(20) NOT NULL,
		created datetime NOT NULL,
		answers text NOT NULL,
		result text NOT NULL,
		outcome varchar(10) NOT NULL DEFAULT '',
		office_status varchar(12) NOT NULL DEFAULT 'pending',
		office_error varchar(255) NOT NULL DEFAULT '',
		office_attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
		lead_status varchar(12) NOT NULL DEFAULT '',
		lead_at datetime NULL DEFAULT NULL,
		lead_name varchar(100) NOT NULL DEFAULT '',
		lead_email varchar(190) NOT NULL DEFAULT '',
		lead_phone varchar(30) NOT NULL DEFAULT '',
		emailme_status varchar(12) NOT NULL DEFAULT '',
		emailme_at datetime NULL DEFAULT NULL,
		ip_hash varchar(16) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		UNIQUE KEY ref (ref),
		KEY created (created)
	) {$wpdb->get_charset_collate()};" );
	update_option( 'bp_est_db_version', BP_EST_DB_VERSION, false );
}

function bp_est_row( string $ref ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . bp_est_table() . ' WHERE ref = %s', $ref ), ARRAY_A );
	return $row ?: null;
}

function bp_est_update( string $ref, array $data ): void {
	global $wpdb;
	$wpdb->update( bp_est_table(), $data, [ 'ref' => $ref ] );
}


/*--------------------------------------------------------------
# Abuse limits + request gate
--------------------------------------------------------------*/

function bp_est_ip(): string {
	return (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
}

/** Transient counter. True while under $max hits in $window seconds. */
function bp_est_rate_ok( string $bucket, int $max, int $window ): bool {
	$key = 'bp_est_rl_' . md5( $bucket );
	$n   = (int) get_transient( $key );
	if ( $n >= $max ) return false;
	set_transient( $key, $n + 1, $window );
	return true;
}

/** Signs a completion so the contact endpoint only accepts follow-ups to a result the server issued. */
function bp_est_sign( string $ref, int $t ): string {
	return substr( hash_hmac( 'sha256', $ref . '|' . $t, wp_salt( 'nonce' ) . 'bp-estimator' ), 0, 24 );
}


/*--------------------------------------------------------------
# REST endpoints
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'bp/v1', '/estimator/quote', [
		'methods'             => 'POST',
		'callback'            => 'bp_est_rest_quote',
		'permission_callback' => '__return_true', // anonymous on purpose; gated + rate-limited inside
	] );
	register_rest_route( 'bp/v1', '/estimator/contact', [
		'methods'             => 'POST',
		'callback'            => 'bp_est_rest_contact',
		'permission_callback' => '__return_true',
	] );
} );

function bp_est_error( string $msg, int $status = 400 ): WP_REST_Response {
	return new WP_REST_Response( [ 'ok' => false, 'message' => $msg ], $status );
}

/** Shared request parsing for both endpoints: session id, answers, valid finished path. */
function bp_est_parse( WP_REST_Request $req ) {
	$sid = (string) $req->get_param( 'sid' );
	if ( ! preg_match( '/^[a-f0-9]{32}$/', $sid ) ) return bp_est_error( 'Bad session.' );

	$answers = bp_est_clean_answers( $req->get_param( 'answers' ) );
	$walk    = bp_est_walk( $answers );
	if ( ! $walk['ok'] ) return bp_est_error( 'Those answers don\'t make a complete estimate (' . $walk['error'] . ').' );

	return [ $sid, $answers ];
}

function bp_est_rest_quote( WP_REST_Request $req ) {
	$parsed = bp_est_parse( $req );
	if ( $parsed instanceof WP_REST_Response ) return $parsed;
	[ $sid, $answers ] = $parsed;

	$ip = bp_est_ip();
	if ( ! bp_est_rate_ok( 'quote|' . $ip, 60, HOUR_IN_SECONDS ) ) return bp_est_error( 'Too many requests. Please wait a bit, or give us a call.', 429 );

	$cfg    = bp_est_config();
	$result = bp_est_compute( $answers, $cfg );
	$ref    = bp_est_ref( $sid, $answers );
	$t      = time();

	bp_est_record_completion( $ref, $answers, $result, $ip );

	return new WP_REST_Response( [
		'ok'     => true,
		'result' => bp_est_public_result( $result, $ref, $cfg ),
		't'      => $t,
		'sig'    => bp_est_sign( $ref, $t ),
	], 200 );
}

/**
 * Log the completion once per reference and queue the office email for after the reply has gone out.
 * A refresh / retry / reopen of the same quiz hits the UNIQUE ref and sends nothing — unless the earlier
 * send failed, in which case it tries again (up to 5 times).
 */
function bp_est_record_completion( string $ref, array $answers, array $result, string $ip ): void {
	global $wpdb;
	$send = false;

	$inserted = $wpdb->query( $wpdb->prepare(
		'INSERT IGNORE INTO ' . bp_est_table() . ' (ref, created, answers, result, outcome, office_status, ip_hash) VALUES (%s, %s, %s, %s, %s, %s, %s)',
		$ref, current_time( 'mysql' ), wp_json_encode( $answers ), wp_json_encode( $result ), $result['outcome'], 'pending', substr( md5( $ip . wp_salt( 'auth' ) ), 0, 16 )
	) );

	if ( $inserted === false ) {
		error_log( 'bp-estimator: could not log completion ' . $ref . ' — ' . $wpdb->last_error );
		$send = true; // the log failed, the notification shouldn't
	} elseif ( $inserted === 1 ) {
		$send = true;
	} else {
		// Already logged. Retry only a failed send; claim it atomically so two tabs can't both resend.
		$send = (bool) $wpdb->query( $wpdb->prepare(
			'UPDATE ' . bp_est_table() . " SET office_status = 'pending' WHERE ref = %s AND office_status = 'failed' AND office_attempts < 5", $ref
		) );
	}
	if ( ! $send ) return;

	// Flood guard: past these caps the result still shows and is logged, but the office isn't emailed.
	if ( ! bp_est_rate_ok( 'notify|' . $ip, 20, HOUR_IN_SECONDS ) || ! bp_est_rate_ok( 'notify|site|' . gmdate( 'Ymd' ), 300, DAY_IN_SECONDS ) ) {
		bp_est_update( $ref, [ 'office_status' => 'limited', 'office_error' => 'Rate limit reached — not emailed.' ] );
		return;
	}

	$GLOBALS['bp_est_after_reply'][] = function () use ( $ref, $answers, $result ) {
		bp_est_send_office_completion( $ref, $answers, $result );
	};
	if ( count( $GLOBALS['bp_est_after_reply'] ) === 1 ) add_action( 'shutdown', 'bp_est_drain_after_reply', 1 );
}

/** Close the connection first (PHP-FPM), then send — the homeowner never waits on the mail server. */
function bp_est_drain_after_reply(): void {
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		ignore_user_abort( true );
		while ( ob_get_level() > 0 ) ob_end_flush();
		fastcgi_finish_request();
	}
	foreach ( $GLOBALS['bp_est_after_reply'] ?? [] as $job ) {
		try { $job(); } catch ( \Throwable $e ) { error_log( 'bp-estimator: ' . $e->getMessage() ); }
	}
	$GLOBALS['bp_est_after_reply'] = [];
}

function bp_est_rest_contact( WP_REST_Request $req ) {
	$parsed = bp_est_parse( $req );
	if ( $parsed instanceof WP_REST_Response ) return $parsed;
	[ $sid, $answers ] = $parsed;

	$cfg    = bp_est_config();
	$action = $req->get_param( 'action' ) === 'email' ? 'email' : 'estimate';
	$result = bp_est_compute( $answers, $cfg );
	$ref    = bp_est_ref( $sid, $answers );

	// Must follow a result this server issued, at human speed.
	$t   = (int) $req->get_param( 't' );
	$sig = (string) $req->get_param( 'sig' );
	if ( ! $t || ! hash_equals( bp_est_sign( $ref, $t ), $sig ) ) return bp_est_error( 'Please reload the page and try again.' );
	if ( time() - $t < 3 ) return bp_est_error( 'Please take a second to check your details, then send again.' );

	if ( $action === 'email' && $result['outcome'] !== 'priced' ) return bp_est_error( 'There is no estimate to email for these answers.' );

	// Fields
	$name  = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $req->get_param( 'name' ) ) ) );
	$email = sanitize_email( (string) $req->get_param( 'email' ) );
	$phone = preg_replace( '/[^0-9]/', '', (string) $req->get_param( 'phone' ) );
	if ( strlen( $phone ) === 11 && $phone[0] === '1' ) $phone = substr( $phone, 1 );

	$invalid = [];
	if ( mb_strlen( $name ) < 2 || mb_strlen( $name ) > 80 ) $invalid['name'] = 'Please enter your name.';
	if ( ! is_email( $email ) ) $invalid['email'] = 'Please enter a valid email address.';
	if ( $action === 'estimate' && strlen( $phone ) !== 10 ) $invalid['phone'] = 'Please enter a 10-digit phone number.';
	if ( $invalid ) return new WP_REST_Response( [ 'ok' => false, 'message' => 'Please check the highlighted fields.', 'invalid' => $invalid ], 200 );

	$phone_fmt = strlen( $phone ) === 10 ? substr( $phone, 0, 3 ) . '-' . substr( $phone, 3, 3 ) . '-' . substr( $phone, 6 ) : '';
	$contact   = [ 'name' => $name, 'email' => $email, 'phone' => $phone_fmt ];

	// Honeypot: look successful, send nothing.
	if ( trim( (string) $req->get_param( 'hp' ) ) !== '' ) return new WP_REST_Response( [ 'ok' => true, 'message' => bp_est_done_msg( $action, $cfg ) ], 200 );

	// Double-click / timeout retry of a send that already went out.
	$done_key = 'bp_est_done_' . md5( $ref . '|' . $action . '|' . strtolower( $email ) . '|' . $phone . '|' . $name );
	if ( get_transient( $done_key ) ) return new WP_REST_Response( [ 'ok' => true, 'message' => bp_est_done_msg( $action, $cfg ) ], 200 );

	if ( ! bp_est_rate_ok( 'contact|' . $action . '|' . bp_est_ip(), 6, HOUR_IN_SECONDS ) ) return bp_est_error( 'Too many requests. Please call us at ' . $cfg['phone'] . '.', 429 );

	// Spam names (same kill list the site's forms use) go to the developer mailbox, as the forms do.
	$spam = '';
	if ( function_exists( 'bp_blocklist_kill' ) ) foreach ( bp_blocklist_kill() as $w ) if ( $w !== '' && stripos( $name, $w ) !== false ) { $spam = $w; break; }

	if ( $action === 'estimate' ) {
		$sent = bp_est_send_office_lead( $ref, $answers, $result, $contact, 'estimate', $spam );
		bp_est_update( $ref, [ 'lead_status' => $sent === true ? 'sent' : 'failed', 'lead_at' => current_time( 'mysql' ), 'lead_name' => $name, 'lead_email' => $email, 'lead_phone' => $phone_fmt ] );
		if ( $sent !== true ) return new WP_REST_Response( [ 'ok' => false, 'message' => 'Sorry, we couldn\'t send your request just now. Please try again, or call us at ' . $cfg['phone'] . '.' ], 200 );
	} else {
		$sent = $spam === '' ? bp_est_send_homeowner( $ref, $result, $contact, $cfg ) : true;
		bp_est_update( $ref, [ 'emailme_status' => $sent === true ? 'sent' : 'failed', 'emailme_at' => current_time( 'mysql' ), 'lead_name' => $name, 'lead_email' => $email ] );
		if ( $sent !== true ) return new WP_REST_Response( [ 'ok' => false, 'message' => 'Sorry, we couldn\'t send the email just now. Please try again, or call us at ' . $cfg['phone'] . '.' ], 200 );
		// The office hears about it too, after the reply (its failure is logged, not shown to the homeowner).
		$GLOBALS['bp_est_after_reply'][] = function () use ( $ref, $answers, $result, $contact, $spam ) {
			bp_est_send_office_lead( $ref, $answers, $result, $contact, 'email', $spam );
		};
		if ( count( $GLOBALS['bp_est_after_reply'] ) === 1 ) add_action( 'shutdown', 'bp_est_drain_after_reply', 1 );
	}

	set_transient( $done_key, 1, 15 * MINUTE_IN_SECONDS );
	return new WP_REST_Response( [ 'ok' => true, 'message' => bp_est_done_msg( $action, $cfg ) ], 200 );
}

function bp_est_done_msg( string $action, array $cfg ): string {
	return $action === 'email' ? $cfg['copy']['email_done'] : $cfg['copy']['estimate_done'];
}


/*--------------------------------------------------------------
# Mail
--------------------------------------------------------------*/

/**
 * wp_mail() through the site's normal mail stack (WP Mail SMTP / Brevo), returning true or the error text.
 * The error is captured from wp_mail_failed so it can be logged on the estimate row.
 */
function bp_est_mail( string $to, string $subject, string $html, array $extra_headers = [] ) {
	$err  = '';
	$grab = function ( $wp_error ) use ( &$err ) { $err = is_wp_error( $wp_error ) ? $wp_error->get_error_message() : 'wp_mail failed'; };
	add_action( 'wp_mail_failed', $grab );

	$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$from    = 'Website · ' . str_replace( ',', '', bp_est_config()['company'] ) . ' <email@admin.' . preg_replace( '/^www\./', '', $host ) . '>';
	$headers = array_merge( [ 'Content-Type: text/html; charset=UTF-8', 'From: ' . $from ], $extra_headers );

	$ok = wp_mail( $to, $subject, $html, $headers );
	remove_action( 'wp_mail_failed', $grab );

	if ( $ok ) return true;
	$err = $err ?: 'wp_mail returned false';
	error_log( 'bp-estimator mail to ' . $to . ' failed: ' . $err );
	return $err;
}

/** A label/value table, the same visual language as the framework form emails. */
function bp_est_email_table( array $rows ): string {
	$h = '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse; font-family:Arial,sans-serif; font-size:15px;">';
	foreach ( $rows as $label => $value ) {
		$h .= '<tr><td style="font-weight:bold; vertical-align:top; padding-right:18px; white-space:nowrap;">' . esc_html( $label ) . '</td><td>' . $value . '</td></tr>';
	}
	return $h . '</table>';
}

function bp_est_unanswered(): string {
	return '<em style="color:#8a8a8a;">Not asked / not answered</em>';
}

/** Rows describing the quiz + result, shared by the completion and follow-up office emails. */
function bp_est_office_rows( string $ref, array $answers, array $r ): array {
	$cfg  = bp_est_config();
	$qa   = bp_est_answer_rows( $answers, $cfg );
	$show = fn( $id ) => $qa[ $id ]['answer'] !== null ? esc_html( $qa[ $id ]['answer'] ) : bp_est_unanswered();

	$rows = [ 'Reference' => '<strong>' . esc_html( $ref ) . '</strong>' ];
	$rows['Systems in home']   = $show( 'systems' );
	if ( $qa['replace']['answer'] !== null )         $rows['Replacing'] = esc_html( $qa['replace']['answer'] );
	elseif ( ( $answers['systems'] ?? '' ) === '1' ) $rows['Replacing'] = 'Their one system <em>(not asked — one-system home)</em>';
	else                                              $rows['Replacing'] = bp_est_unanswered();
	$rows['Square footage']    = $show( 'sqft' );
	$rows['Estimated size']    = $r['size_tons'] !== null && isset( $answers['sqft'] ) ? esc_html( bp_est_tons( $r['size_tons'] ) ) : bp_est_unanswered();
	if ( $r['priced_tons'] !== null && (string) $r['priced_tons'] !== (string) $r['size_tons'] ) {
		$rows['Size used for pricing'] = esc_html( bp_est_tons( $r['priced_tons'] ) ) . ' <em>(series not made in ' . esc_html( bp_est_tons( $r['size_tons'] ) ) . ')</em>';
	}
	$rows['Current system']    = $show( 'type' );
	$rows['Indoor unit']       = $show( 'location' );
	$rows['Comfort level']     = isset( $answers['tier'] ) ? esc_html( $r['tier_label'] . ( $r['series'] ? ' · ' . $r['series'] : '' ) ) : bp_est_unanswered();

	if ( $r['outcome'] === 'priced' ) {
		$rows['Installed range'] = '<strong>' . esc_html( bp_est_money( $r['price_low'] ) . '–' . bp_est_money( $r['price_high'] ) ) . '</strong>';
		$rows['Monthly shown']   = esc_html( 'As low as ' . bp_est_money( $r['monthly_low'] ) . '/month' ) . ' <em>(' . esc_html( $cfg['financing']['disclosure'] ) . ')</em>';
		if ( $r['load_calc'] ) $rows['Note shown'] = esc_html( $cfg['copy']['load_calc'] );
	} else {
		$rows['Result shown'] = esc_html( $r['outcome'] === 'call' ? $cfg['copy']['call_title'] : $cfg['copy']['inhome_title'] ) . ' — no price';
		$rows['Why no price'] = esc_html( $r['reason'] );
	}
	return $rows;
}

function bp_est_office_subject( string $ref, array $r, string $lead = '' ): string {
	$cfg  = bp_est_config();
	$what = $r['outcome'] === 'priced'
		? bp_est_money( $r['price_low'] ) . '–' . bp_est_money( $r['price_high'] ) . ' · ' . $r['tier_label'] . ( $r['series'] ? ' ' . $r['series'] : '' )
		: ( $r['outcome'] === 'call' ? 'Call for quote' : 'In-home estimate needed' );
	$head = $lead !== '' ? $lead : 'Price Estimator Completed';
	return $head . ' · ' . $what . ' · ' . $ref;
}

function bp_est_send_office_completion( string $ref, array $answers, array $r ): void {
	$cfg  = bp_est_config();
	$body = '<p style="font-family:Arial,sans-serif;">A homeowner finished the online price estimator. No contact details were given at this point — if they request an estimate or email themselves the result, a follow-up email with the same reference will arrive.</p>'
		. bp_est_email_table( bp_est_office_rows( $ref, $answers, $r ) )
		. bp_est_meta_footer();

	$sent = bp_est_mail( $cfg['office_email'], bp_est_office_subject( $ref, $r ), $body );

	global $wpdb;
	$wpdb->query( $wpdb->prepare(
		'UPDATE ' . bp_est_table() . ' SET office_status = %s, office_error = %s, office_attempts = office_attempts + 1 WHERE ref = %s',
		$sent === true ? 'sent' : 'failed', $sent === true ? '' : substr( (string) $sent, 0, 255 ), $ref
	) );
}

/** Follow-up office email when the homeowner gives contact details. Returns true or the error text. */
function bp_est_send_office_lead( string $ref, array $answers, array $r, array $c, string $action, string $spam = '' ) {
	$cfg  = bp_est_config();
	$lead = $action === 'estimate' ? 'Free In-Home Estimate Request' : 'Estimate Emailed to Homeowner';

	$contact = [ 'Name' => esc_html( $c['name'] ), 'Email' => '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>' ];
	if ( $c['phone'] !== '' ) $contact['Phone'] = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9]/', '', $c['phone'] ) ) . '">' . esc_html( $c['phone'] ) . '</a>';

	$intro = $action === 'estimate'
		? 'This homeowner asked for a <strong>free in-home estimate</strong>. Please call them to schedule — nothing has been booked yet.'
		: 'This homeowner emailed themselves their estimate. They haven\'t asked for a visit yet.';

	$body = '<p style="font-family:Arial,sans-serif;">' . $intro . '</p>'
		. bp_est_email_table( $contact )
		. '<h3 style="font-family:Arial,sans-serif; margin:24px 0 6px;">Their estimator answers</h3>'
		. bp_est_email_table( bp_est_office_rows( $ref, $answers, $r ) )
		. bp_est_meta_footer();

	$to      = $cfg['office_email'];
	$subject = bp_est_office_subject( $ref, $r, $lead . ' · ' . $c['name'] );
	if ( $spam !== '' ) { $to = 'email@bp-webdev.com'; $subject = '<- SPAM: Blocked Kill "' . $spam . '" -> ' . $subject; }

	$reply = trim( preg_replace( '/[\r\n<>"]+/', ' ', $c['name'] ) ) . ' <' . $c['email'] . '>';
	return bp_est_mail( $to, $subject, $body, [ 'Reply-To: ' . $reply, 'Bcc: Website Administrator <email@bp-webdev.com>' ] );
}

/** The homeowner's copy of their estimate. Returns true or the error text. */
function bp_est_send_homeowner( string $ref, array $r, array $c, array $cfg ) {
	$tel   = preg_replace( '/[^0-9]/', '', $cfg['phone'] );
	$first = strtok( $c['name'], ' ' );
	$p     = 'style="font-family:Arial,sans-serif; font-size:16px; line-height:1.5; margin:0 0 14px;"';

	$body  = '<div style="max-width:560px;">';
	$body .= '<p ' . $p . '>Hi ' . esc_html( $first ) . ',</p>';
	$body .= '<p ' . $p . '>Here is the estimate you put together on our website.</p>';
	$body .= '<p ' . $p . '><strong>' . esc_html( $r['type_label'] ) . ' · ' . esc_html( $r['tier_label'] . ( $r['series'] ? ' (' . $r['series'] . ')' : '' ) ) . '</strong></p>';
	$body .= '<p style="font-family:Arial,sans-serif; font-size:26px; font-weight:bold; margin:0 0 4px;">As low as ' . esc_html( bp_est_money( $r['monthly_low'] ) ) . '/month</p>';
	$body .= '<p style="font-family:Arial,sans-serif; font-size:14px; margin:0 0 14px;">' . esc_html( $cfg['financing']['disclosure'] ) . ( $cfg['financing']['provider'] ? ' Financing through ' . esc_html( $cfg['financing']['provider'] ) . '.' : '' ) . '</p>';
	$body .= '<p ' . $p . '>Estimated installed price: <strong>' . esc_html( bp_est_money( $r['price_low'] ) . '–' . bp_est_money( $r['price_high'] ) ) . '</strong></p>';
	if ( $r['load_calc'] ) $body .= '<p ' . $p . '><strong>' . esc_html( $cfg['copy']['load_calc'] ) . '</strong></p>';
	$body .= '<p ' . $p . '>' . esc_html( $cfg['copy']['rough_note'] ) . '</p>';
	$body .= '<p ' . $p . '>For your exact price, call us at <a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $cfg['phone'] ) . '</a> or reply to this email to set up a free in-home estimate.</p>';
	$body .= '<p ' . $p . '>' . esc_html( $cfg['company'] ) . '</p>';
	$body .= '<p style="font-family:Arial,sans-serif; font-size:12px; color:#8a8a8a;">Estimate reference ' . esc_html( $ref ) . '</p>';
	$body .= '</div>';

	return bp_est_mail( $c['email'], 'Your estimate from ' . $cfg['company'], $body, [ 'Reply-To: ' . str_replace( ',', '', $cfg['company'] ) . ' <' . $cfg['office_email'] . '>' ] );
}

function bp_est_meta_footer(): string {
	$ref = (string) ( $_SERVER['HTTP_REFERER'] ?? '' );
	return '<p style="font-family:Arial,sans-serif; color:#8a8a8a; border-top:1px solid #8a8a8a; margin-top:40px; padding-top:8px; font-size:13px;">'
		. 'Sent by the price estimator' . ( $ref ? ' on ' . esc_html( $ref ) : '' ) . ' · ' . esc_html( wp_date( 'M j, Y g:i a' ) )
		. '<br>Sender IP: ' . esc_html( bp_est_ip() ) . '</p>';
}


/*--------------------------------------------------------------
# Shortcode — [hvac-estimator]
--------------------------------------------------------------*/

/**
 * Print the estimator CSS in <head> at wp_head 10, just before the inlined style-site.css (11), so a site's
 * own rules override it at equal specificity (same approach as sp_store_inline_css). bp_inline_minified_css()
 * flushes in the footer, AFTER the site CSS, which would make every style-site.css override lose.
 * The check covers the page content plus its postmeta (Page Top / Page Bottom live there).
 */
add_action( 'wp_head', 'bp_est_head_css', 10 );
function bp_est_head_css(): void {
	if ( is_admin() || ! is_singular() ) return;
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) return;

	$found = str_contains( (string) $post->post_content, '[hvac-estimator' );
	if ( ! $found ) {
		foreach ( (array) get_post_meta( $post->ID ) as $vals ) {
			foreach ( (array) $vals as $v ) {
				if ( is_string( $v ) && str_contains( $v, '[hvac-estimator' ) ) { $found = true; break 2; }
			}
		}
	}
	if ( ! $found ) return;

	$path = get_template_directory() . '/style-hvac-estimator.css';
	if ( ! file_exists( $path ) ) return;
	$raw = (string) file_get_contents( $path );
	echo '<style id="bp-hvac-estimator-css">' . ( function_exists( 'bp_minify_css' ) ? bp_minify_css( $raw ) : $raw ) . '</style>' . "\n";
	$GLOBALS['bp_est_css_done'] = true;
}

add_shortcode( 'hvac-estimator', 'bp_est_shortcode' );
function bp_est_shortcode( $atts = [] ): string {
	// Always live wherever the shortcode is placed. Keep it on an unlinked test page until launch.
	$cfg = bp_est_config();
	if ( empty( $cfg['prices'] ) ) {
		return current_user_can( 'edit_pages' ) ? '<p><strong>Price estimator:</strong> no prices configured — add them with the <code>bp_hvac_estimator_config</code> filter.</p>' : '';
	}

	// Normally already printed in <head> by bp_est_head_css(). This footer fallback only covers a
	// shortcode placed somewhere the head check can't see (a widget, a template).
	if ( empty( $GLOBALS['bp_est_css_done'] ) ) bp_inline_minified_css( get_template_directory() . '/style-hvac-estimator.css' );
	bp_enqueue_script( 'battleplan-hvac-estimator', 'script-hvac-estimator', [] );

	$data = [
		'rest'      => esc_url_raw( rest_url( 'bp/v1/estimator/' ) ),
		'phone'     => $cfg['phone'],
		'tel'       => preg_replace( '/[^0-9]/', '', $cfg['phone'] ),
		'questions' => bp_est_questions( $cfg ),
		'copy'      => $cfg['copy'],
		'storage'   => 'bp-est-' . substr( md5( home_url() . get_the_ID() ), 0, 8 ),
	];

	$tel   = esc_attr( $data['tel'] );
	$phone = esc_html( $cfg['phone'] );
	$copy  = $cfg['copy'];

	$field = function ( string $form, string $name, string $type, string $label, string $auto ) {
		$id = 'bpe-' . $form . '-' . $name;
		return '<div class="form-input input-' . esc_attr( $name ) . '">'
			. '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '<span class="required"></span><span class="sr-only">Required Field</span></label>'
			. bp_field_wrap( 'user-' . $name, $type, '<input type="' . esc_attr( $type ) . '" class="bp-control bp-' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="user-' . esc_attr( $name ) . '" placeholder=" " autocomplete="' . esc_attr( $auto ) . '" required aria-describedby="' . esc_attr( $id ) . '-err">' )
			. '<span class="estimator-field-error" id="' . esc_attr( $id ) . '-err"></span>'
			. '</div>';
	};
	$hp = '<label class="estimator-hp" aria-hidden="true">Leave this empty<input type="text" name="hp" tabindex="-1" autocomplete="off"></label>';

	ob_start(); ?>
	<div class="estimator" data-estimator="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
		<div class="estimator-progress">
			<p class="estimator-progress-label" aria-live="polite"></p>
			<div class="estimator-progress-track" role="progressbar" aria-label="Estimator progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span class="estimator-progress-fill"></span></div>
		</div>

		<div class="estimator-stage">
			<noscript><p>Please call us at <a href="tel:<?php echo $tel; ?>"><?php echo $phone; ?></a> for a price.</p></noscript>
		</div>

		<div class="estimator-actions" hidden>
			<div class="estimator-action-buttons">
				<button type="button" class="estimator-open" data-open="estimate" aria-expanded="false" aria-controls="bpe-form-estimate"><?php echo esc_html( $copy['estimate_cta'] ); ?></button>
				<button type="button" class="estimator-open estimator-secondary" data-open="email" aria-expanded="false" aria-controls="bpe-form-email"><?php echo esc_html( $copy['email_cta'] ); ?></button>
			</div>

			<form class="estimator-form" id="bpe-form-estimate" data-action="estimate" hidden novalidate>
				<h3 class="estimator-form-title" tabindex="-1"><?php echo esc_html( $copy['estimate_cta'] ); ?></h3>
				<p class="estimator-form-help">We'll call to set up a time. Your answers and estimate come along with your request.</p>
				<?php echo $field( 'estimate', 'name', 'text', 'Name', 'name' ) . $field( 'estimate', 'phone', 'tel', 'Phone', 'tel' ) . $field( 'estimate', 'email', 'email', 'Email', 'email' ) . $hp; ?>
				<button type="submit">Request My Free Estimate</button>
				<p class="estimator-status" role="status"></p>
			</form>

			<form class="estimator-form" id="bpe-form-email" data-action="email" hidden novalidate>
				<h3 class="estimator-form-title" tabindex="-1"><?php echo esc_html( $copy['email_cta'] ); ?></h3>
				<?php echo $field( 'email', 'name', 'text', 'Name', 'name' ) . $field( 'email', 'email', 'email', 'Email', 'email' ) . $hp; ?>
				<button type="submit">Email My Estimate</button>
				<p class="estimator-status" role="status"></p>
			</form>
		</div>

		<p class="estimator-call">Questions? Call <a href="tel:<?php echo $tel; ?>"><?php echo $phone; ?></a></p>
	</div>
	<?php
	return ob_get_clean();
}


/*--------------------------------------------------------------
# wp-admin: Tools → Price Estimator (log, mail failures)
--------------------------------------------------------------*/

add_action( 'admin_menu', function () {
	add_management_page( 'Price Estimator', 'Price Estimator', 'edit_pages', 'bp-estimator', 'bp_est_admin_page' );
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'edit_pages' ) || ( $_GET['page'] ?? '' ) === 'bp-estimator' ) return;
	global $wpdb;
	$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . bp_est_table() . " WHERE office_status = 'failed' AND created > %s", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) );
	if ( $n ) echo '<div class="notice notice-error"><p><strong>Price Estimator:</strong> ' . $n . ' office notification' . ( $n === 1 ? '' : 's' ) . ' failed to send this week. <a href="' . esc_url( admin_url( 'tools.php?page=bp-estimator' ) ) . '">Review and resend</a>.</p></div>';
} );

function bp_est_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) return;
	global $wpdb;

	if ( isset( $_POST['bp_est_resend'] ) && check_admin_referer( 'bp_est_resend' ) ) {
		$row = bp_est_row( sanitize_text_field( wp_unslash( $_POST['bp_est_resend'] ) ) );
		if ( $row ) {
			bp_est_send_office_completion( $row['ref'], json_decode( $row['answers'], true ) ?: [], json_decode( $row['result'], true ) ?: [] );
			$row = bp_est_row( $row['ref'] );
			echo '<div class="notice notice-' . ( $row['office_status'] === 'sent' ? 'success' : 'error' ) . '"><p>' . esc_html( $row['ref'] ) . ': ' . esc_html( $row['office_status'] === 'sent' ? 'sent.' : 'failed again — ' . $row['office_error'] ) . '</p></div>';
		}
	}

	$cfg  = bp_est_config();
	$rows = $wpdb->get_results( 'SELECT * FROM ' . bp_est_table() . ' ORDER BY id DESC LIMIT 200', ARRAY_A );

	echo '<div class="wrap"><h1>Price Estimator</h1>';
	echo '<p>Office notifications go to <strong>' . esc_html( $cfg['office_email'] ) . '</strong>. A failed send is retried when the homeowner reopens the result, or with the Resend button.</p>';

	echo '<table class="widefat striped"><thead><tr><th>When</th><th>Reference</th><th>Result</th><th>Office email</th><th>Estimate request</th><th>Emailed estimate</th></tr></thead><tbody>';
	if ( ! $rows ) echo '<tr><td colspan="6">No completed estimates yet.</td></tr>';
	foreach ( (array) $rows as $row ) {
		$r    = json_decode( $row['result'], true ) ?: [];
		$what = $row['outcome'] === 'priced'
			? bp_est_money( (int) $r['price_low'] ) . '–' . bp_est_money( (int) $r['price_high'] ) . ' · ' . esc_html( $r['type_label'] . ' · ' . $r['tier_label'] . ' · ' . bp_est_tons( $r['size_tons'] ) )
			: esc_html( ( $row['outcome'] === 'call' ? 'Call for quote' : 'In-home estimate' ) . ' — ' . ( $r['reason'] ?? '' ) );
		$office = esc_html( $row['office_status'] ) . ( $row['office_error'] ? '<br><small>' . esc_html( $row['office_error'] ) . '</small>' : '' );
		if ( in_array( $row['office_status'], [ 'failed', 'limited' ], true ) ) {
			$office .= '<form method="post" style="margin-top:4px;">' . wp_nonce_field( 'bp_est_resend', '_wpnonce', true, false ) . '<button class="button button-small" name="bp_est_resend" value="' . esc_attr( $row['ref'] ) . '">Resend</button></form>';
		}
		$lead = $row['lead_status'] ? esc_html( $row['lead_status'] . ' · ' . $row['lead_name'] . ' · ' . $row['lead_phone'] . ' · ' . $row['lead_email'] ) : '—';
		$em   = $row['emailme_status'] ? esc_html( $row['emailme_status'] . ( $row['lead_status'] ? '' : ' · ' . $row['lead_name'] . ' · ' . $row['lead_email'] ) ) : '—';
		echo '<tr><td>' . esc_html( $row['created'] ) . '</td><td><code>' . esc_html( $row['ref'] ) . '</code></td><td>' . $what . '</td><td>' . $office . '</td><td>' . $lead . '</td><td>' . $em . '</td></tr>';
	}
	echo '</tbody></table></div>';
}
