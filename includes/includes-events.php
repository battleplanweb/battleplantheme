<?php
/* Battle Plan Web Design Event Calendar

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Setup
# Setup Custom Events + Fields
# Shortcodes
--------------------------------------------------------------*/

// Custom ACF field types: non-consecutive date ranges + the recurrence builder.
require_once get_template_directory() . '/includes/includes-acf-field-date-blocks.php';
require_once get_template_directory() . '/includes/includes-acf-field-event-recurrence.php';

// Load the recurrence editor JS on the events edit screen (specific-vs-recurring
// toggle + the recurrence field's show/hide + layout). Uses admin_enqueue_scripts
// rather than the field type's input_admin_enqueue_scripts, which wasn't firing here.
add_action( 'admin_enqueue_scripts', 'bp_event_recurrence_admin_assets' );
function bp_event_recurrence_admin_assets( $hook ) {
	if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) return;
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'events' ) return;
	wp_enqueue_script(
		'bp-event-recurrence',
		get_template_directory_uri() . '/js/script-acf-event-recurrence.min.js',
		array(),
		defined('_BP_VERSION') ? _BP_VERSION : false,
		true
	);
}

// Return an event's date blocks: [ ['s_date','e_date','s_time','e_time'], ... ].
// Falls back to the legacy single start/end range for events created before the
// date_blocks field existed, so old and new events both render with no migration.
function bp_event_date_blocks( $post_id ) {
	$blocks = get_field( 'event_dates', $post_id );
	if ( ! empty($blocks) && is_array($blocks) ) return $blocks;

	$s = get_post_meta( $post_id, 'start_date', true ); // legacy ACF date_picker (Ymd)
	if ( ! $s ) return array();
	$e = get_post_meta( $post_id, 'end_date', true );
	return array( array(
		's_date' => $s,
		'e_date' => $e ?: $s,
		's_time' => get_post_meta( $post_id, 'start_time', true ),
		'e_time' => get_post_meta( $post_id, 'end_time', true ),
	) );
}

// Seed the date_blocks field from the legacy single start/end fields when an event
// created before this feature is opened or read. Makes old events show their existing
// date in the new field (and persist to the new format on next save) with no migration
// step. Lives here (not in the generic field type) so the field type stays uncoupled.
add_filter( 'acf/load_value/name=event_dates', 'bp_event_dates_legacy_seed', 10, 3 );
function bp_event_dates_legacy_seed( $value, $post_id, $field ) {
	if ( ! empty($value) ) return $value;

	$s = get_post_meta( $post_id, 'start_date', true );
	if ( ! $s ) return $value;
	$st = strtotime( $s );
	if ( ! $st ) return $value;

	$e  = get_post_meta( $post_id, 'end_date', true );
	$et = strtotime( (string) $e );
	$sTime = get_post_meta( $post_id, 'start_time', true );
	$eTime = get_post_meta( $post_id, 'end_time', true );

	return wp_json_encode( array( array(
		's_date' => date( 'Y-m-d', $st ),
		'e_date' => $et ? date( 'Y-m-d', $et ) : date( 'Y-m-d', $st ),
		's_time' => $sTime ? date( 'H:i', strtotime($sTime) ) : '',
		'e_time' => $eTime ? date( 'H:i', strtotime($eTime) ) : '',
	) ) );
}

// Keep the legacy start_date/end_date/start_time/end_time meta in sync from the
// date blocks (earliest start, latest end) so admin columns, sorting, the archive
// ordering, and [event_teasers] all keep working unchanged. Stored in ACF's legacy
// formats (Ymd / H:i:s) for byte-compatibility with existing events.
add_action( 'acf/save_post', 'bp_event_sync_span', 20 );
function bp_event_sync_span( $post_id ) {
	if ( get_post_type( $post_id ) !== 'events' ) return;

	$raw    = get_post_meta( $post_id, 'event_dates', true );
	$blocks = $raw ? json_decode( $raw, true ) : array();
	if ( empty($blocks) || ! is_array($blocks) ) return;

	$minStart = null; $maxEnd = null; $startTime = ''; $endTime = '';
	foreach ( $blocks as $b ) {
		$s = strtotime( $b['s_date'] ?? '' );
		if ( ! $s ) continue;
		$e = ! empty($b['e_date']) ? strtotime( $b['e_date'] ) : $s;
		if ( $minStart === null || $s < $minStart ) {
			$minStart  = $s;
			$startTime = ! empty($b['s_time']) ? date('H:i:s', strtotime($b['s_time'])) : '';
			$endTime   = ! empty($b['e_time']) ? date('H:i:s', strtotime($b['e_time'])) : '';
		}
		if ( $maxEnd === null || $e > $maxEnd ) $maxEnd = $e;
	}
	if ( $minStart === null ) return;

	update_post_meta( $post_id, 'start_date', date('Ymd', $minStart) );
	update_post_meta( $post_id, 'end_date',   date('Ymd', $maxEnd) );
	update_post_meta( $post_id, 'start_time', $startTime );
	update_post_meta( $post_id, 'end_time',   $endTime );
}


/*--------------------------------------------------------------
# Recurrence (a rule that GENERATES date blocks; rendering is unchanged)
--------------------------------------------------------------*/

// Assemble the recurrence rule from the event's ACF fields, or null if the event
// isn't set to recurring (then its manual date_blocks are used as-is).
function bp_event_recurrence_rule( $post_id ) {
	if ( get_field('date_type', $post_id) !== 'recurring' ) return null;

	$r = get_field('event_recurrence', $post_id);
	if ( ! is_array($r) || empty($r['start']) ) return null;

	return array(
		'freq'      => $r['freq'] ?? 'weekly',
		'interval'  => max( 1, (int) ( $r['interval'] ?? 1 ) ),
		'weekdays'  => array_map( 'strtolower', (array) ( $r['weekdays'] ?? array() ) ),
		'monthmode' => $r['monthmode'] ?? 'weekday',
		'nth'       => $r['nth'] ?? 'first',
		'weekday'   => $r['weekday'] ?? 'sunday',
		'monthday'  => (int) ( $r['monthday'] ?? 1 ),
		'month'     => (int) ( $r['month'] ?? 1 ),
		'start'     => $r['start'],
		'until'     => $r['until'] ?? '',
		's_time'    => $r['s_time'] ?? '',
		'e_time'    => $r['e_time'] ?? '',
	);
}

// Compute the single occurrence date (timestamp) in a given year+month for a
// monthly/yearly rule — Nth/last weekday, or a fixed day-of-month. 0 if none.
function bp_event_month_occurrence( $rule, $year, $month ) {
	if ( $rule['monthmode'] === 'monthday' ) {
		$day = min( max( 1, (int) $rule['monthday'] ), (int) date('t', mktime(0,0,0,$month,1,$year)) );
		return mktime( 0,0,0, $month, $day, $year );
	}
	// weekday mode — strtotime understands "third saturday of May 2026" / "last ...".
	$monthName = date( 'F', mktime(0,0,0,$month,1,$year) );
	$ts = strtotime( $rule['nth'].' '.$rule['weekday'].' of '.$monthName.' '.$year );
	if ( ! $ts ) return 0;
	if ( (int) date('n', $ts) !== (int) $month ) return 0; // e.g. a 5th weekday that overflowed
	return $ts;
}

// Expand a rule into single-day date blocks within [windowStart, end]. windowStart
// trims past clutter; end is the rule's "until" date or the rolling horizon.
function bp_event_generate_recurrence( $rule, $windowStartTs, $horizonTs ) {
	$startTs = strtotime( $rule['start'] );
	if ( ! $startTs ) return array();

	$endTs = $rule['until'] ? strtotime( $rule['until'] ) : $horizonTs;
	if ( ! $endTs ) $endTs = $horizonTs;
	$endTs = min( $endTs, strtotime('+10 years', $startTs) ); // safety ceiling

	$startMid = strtotime( date('Y-m-d', $startTs) );
	$from     = max( $startMid, strtotime( date('Y-m-d', $windowStartTs) ) );
	$interval = max( 1, (int) $rule['interval'] );
	$cap      = 800;
	$dates    = array();

	if ( $rule['freq'] === 'weekly' ) {
		if ( ! $rule['weekdays'] ) return array();
		// Week phase measured from the Sunday of the anchor week, so "every N weeks" holds.
		$anchorWeek = $startMid - ( (int) date('w', $startMid) ) * 86400;
		for ( $d = $from; $d <= $endTs && count($dates) < $cap; $d = strtotime('+1 day', $d) ) {
			if ( $d < $startMid ) continue;
			if ( ! in_array( strtolower( date('l', $d) ), $rule['weekdays'], true ) ) continue;
			$weekIndex = (int) floor( ( $d - $anchorWeek ) / ( 7 * 86400 ) );
			if ( $weekIndex % $interval !== 0 ) continue;
			$dates[] = $d;
		}
	} else { // monthly | yearly
		$step   = ( $rule['freq'] === 'yearly' ) ? '+'.$interval.' years' : '+'.$interval.' months';
		$cursor = strtotime( date('Y-m-01', $startMid) );
		while ( $cursor <= $endTs && count($dates) < $cap ) {
			$year  = (int) date('Y', $cursor);
			$month = ( $rule['freq'] === 'yearly' ) ? (int) $rule['month'] : (int) date('n', $cursor);
			$occ   = bp_event_month_occurrence( $rule, $year, $month );
			if ( $occ && $occ >= $from && $occ >= $startMid && $occ <= $endTs ) $dates[] = $occ;
			$cursor = strtotime( $step, $cursor );
		}
	}

	sort( $dates );
	$dates = array_unique( $dates );

	$blocks = array();
	foreach ( $dates as $ts ) {
		$blocks[] = array(
			's_date' => date('Y-m-d', $ts),
			'e_date' => date('Y-m-d', $ts),
			's_time' => $rule['s_time'],
			'e_time' => $rule['e_time'],
		);
	}
	return $blocks;
}

// Regenerate a recurring event's date blocks (current month → rolling horizon, or
// the rule's "until"). No-op for non-recurring events, so their manual blocks survive.
function bp_event_regenerate( $post_id ) {
	$rule = bp_event_recurrence_rule( $post_id );
	if ( ! $rule ) return false;

	$blocks = bp_event_generate_recurrence( $rule, strtotime( date('Y-m-01') ), strtotime('+24 months') );
	update_post_meta( $post_id, 'event_dates', wp_json_encode( $blocks ) );
	return true;
}

// On save: generate blocks from the rule BEFORE bp_event_sync_span (pri 20) derives
// the span. Runs after ACF has stored the recurrence fields (pri 10).
add_action( 'acf/save_post', 'bp_event_apply_recurrence', 15 );
function bp_event_apply_recurrence( $post_id ) {
	if ( get_post_type( $post_id ) !== 'events' ) return;
	bp_event_regenerate( $post_id );
}

// Nightly top-up: roll the horizon forward and prune past occurrences for every
// published recurring event. Called from the housekeeping cron.
function bp_event_topup_recurrences() {
	$q = bp_WP_Query('events', [
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'meta_query'     => [ [ 'key' => 'date_type', 'value' => 'recurring' ] ],
	]);
	foreach ( (array) $q->posts as $p ) {
		$pid = is_object( $p ) ? $p->ID : (int) $p;
		if ( bp_event_regenerate( $pid ) ) bp_event_sync_span( $pid );
	}
	wp_reset_postdata();
}

// Human-readable recurrence summary for the event meta display.
function bp_event_recurrence_summary( $rule ) {
	if ( ! $rule ) return '';

	$ord = array( 'first'=>'1st', 'second'=>'2nd', 'third'=>'3rd', 'fourth'=>'4th', 'fifth'=>'5th', 'last'=>'last' );
	$wd  = ucfirst( $rule['weekday'] );
	$mon = date( 'F', mktime(0,0,0, max(1,(int)$rule['month']), 1) );
	$n   = (int) $rule['interval'];

	if ( $rule['freq'] === 'weekly' ) {
		$days = array_map( 'ucfirst', $rule['weekdays'] );
		$list = $days ? implode( ' & ', $days ) : 'week';
		$text = ( $n >= 2 ) ? 'Every other '.$list : 'Every '.$list;
	} elseif ( $rule['freq'] === 'monthly' ) {
		$cycle = ( $n >= 2 ) ? 'every other month' : 'every month';
		$text  = ( $rule['monthmode'] === 'monthday' )
			? 'Day '.(int)$rule['monthday'].' of '.$cycle
			: 'The '.$ord[ $rule['nth'] ].' '.$wd.' of '.$cycle;
	} else { // yearly
		$cycle = ( $n >= 2 ) ? ', every other year' : ', every year';
		$text  = ( $rule['monthmode'] === 'monthday' )
			? $mon.' '.(int)$rule['monthday'].$cycle
			: 'The '.$ord[ $rule['nth'] ].' '.$wd.' of '.$mon.$cycle;
	}

	if ( $rule['until'] ) $text .= ' through '.date( 'M j, Y', strtotime( $rule['until'] ) );
	return $text;
}



/*--------------------------------------------------------------
# Setup
--------------------------------------------------------------*/
// Set up javascript param with all events + expire old events
add_action( 'wp_head', 'battleplan_createEventLog', 0 );
function battleplan_createEventLog() {
	$eventData = array();
	$query = bp_WP_Query('events', [
		'posts_per_page' => -1
	]);

	if ($query->have_posts()) :
		while ($query->have_posts()) :
			$query->the_post();

			$tag = '';
			$eventTags = wp_get_post_terms(get_the_ID(), 'event-tags', array('field' => 'slug'));
			$eventSlugs = wp_list_pluck($eventTags, 'slug');
			foreach ($eventSlugs as $eventSlug ) :
				$tag .= ' event-'.$eventSlug;
			endforeach;

			$eventHtml = '<div class="event'.$tag.'"><a href="'. get_permalink().'">'.get_the_post_thumbnail(get_the_ID(), "thumbnail", array( 'class' => 'calendar-event-icon' )).'<span class="hide-3 hide-2 hide-1">'.get_the_title().'</span></a></div>';

			// Expand each (possibly non-consecutive) date block into its own run of days.
			$overallEnd = 0;
			foreach ( bp_event_date_blocks(get_the_ID()) as $block ) :
				$eventStart = strtotime($block['s_date'] ?? '');
				if ( ! $eventStart ) continue;
				$eventEnd = ! empty($block['e_date']) ? strtotime($block['e_date']) : $eventStart;
				$overallEnd = max($overallEnd, $eventEnd);
				$days = round(($eventEnd - $eventStart) / 86400) + 1;

				for ($i=0; $i<$days; $i++) :
					$eventDate = date('j, n, Y', strtotime("+$i days", $eventStart));
					$eventData[] = array('date' => $eventDate, 'event' => $eventHtml);
				endfor;
			endforeach;

			// Once an event's last day has fully passed, tag it expired and set it to
			// draft — gone from the live calendar/archive, still in the admin. Compared
			// against the start of today so an event still shows through its final day.
			if ( $overallEnd && $overallEnd < strtotime('today') ) {
				wp_set_object_terms(get_the_ID(), 'expired', 'event-tags', false);
				wp_update_post(array( 'ID' => get_the_ID(), 'post_status' => 'draft' ));
			}

	  	endwhile;
	endif;

	wp_reset_postdata();

	$abbr_days = get_option('event_calendar')['abbr_days'] == true ? true : false;

	?><script nonce="<?php echo _BP_NONCE; ?>">var eventLog = <?php echo json_encode($eventData); ?>; var abbr_days = <?php echo $abbr_days; ?>;</script><?php
}


/*--------------------------------------------------------------
# Setup Custom Events + Fields
--------------------------------------------------------------*/
add_action( 'init', 'battleplan_registerEventPostType', 0 );
function battleplan_registerEventPostType() {
	register_post_type( 'events', array (
		'label'=>				__( 'events', 'battleplan' ),
		'labels'=>array(
			'name'=>			_x( 'Events', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>	_x( 'Event', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>			true,
		'publicly_queryable'=>	true,
		'exclude_from_search'=>	false,
		'show_in_nav_menus'=>	true,
		'supports'=>			array( 'title', 'editor', 'comments', 'author', 'excerpt', 'page-attributes', 'thumbnail', 'custom-fields', 'post-formats' ),
		'hierarchical'=>		false,
		'menu_position'=>		20,
		'menu_icon'=>			'dashicons-calendar-alt',
		'has_archive'=>			true,
		'capability_type'=>		'post',
	));

	register_taxonomy( 'event-cats', array( 'events' ), array(
		'labels'=>array(
			'name'=>			_x( 'Event Categories', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Event Category', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		true,
		'show_ui'=>			true,
        	'show_admin_column'=>	true,
	));

	register_taxonomy( 'event-tags', array( 'events' ), array(
		'labels'=>array(
			'name'=>			_x( 'Event Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>	_x( 'Event Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>		false,
		'show_ui'=>			true,
        	'show_admin_column'=>	true,
	));

	wp_insert_term( 'upcoming', 'event-tags' );
	wp_insert_term( 'expired', 'event-tags' );
	wp_insert_term( 'featured', 'event-tags' );
}

add_action('acf/init', 'battleplan_add_event_acf_fields');
function battleplan_add_event_acf_fields() {
	acf_add_local_field_group( array(
		'key' => 'group_6478d57ca3a2e',
		'title' => 'Events',
		'fields' => array(
			array(
				'key' => 'field_bp_date_type',
				'label' => 'Date Type',
				'name' => 'date_type',
				'type' => 'radio',
				'instructions' => 'Specific dates = list exact date ranges. Recurring = a repeating rule (e.g. every 3rd Saturday).',
				'choices' => array( 'specific' => 'Specific dates', 'recurring' => 'Recurring' ),
				'default_value' => 'specific',
				'layout' => 'horizontal',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array( 'width' => '100%', 'class' => '', 'id' => '' ),
			),
			array(
				'key' => 'field_bp_event_dates',
				'label' => 'Event Dates',
				'name' => 'event_dates',
				'aria-label' => '',
				'type' => 'date_blocks',
				'instructions' => 'Add one block per date range. Use multiple blocks for non-consecutive dates (e.g. June 10–12, June 15–18, June 23–26). Each block has its own start/end time.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '100%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_bp_event_recurrence',
				'label' => 'Recurrence',
				'name' => 'event_recurrence',
				'type' => 'event_recurrence',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '100%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_6478d5ds03gwk',
				'label' => 'Venue',
				'name' => 'venue',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_6478d5eg35acx',
				'label' => 'Location',
				'name' => 'location',
				'aria-label' => '',
				'type' => 'text',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
			array(
				'key' => 'field_6478d5hr36erd',
				'label' => 'Venue Link',
				'name' => 'venue_link',
				'aria-label' => '',
				'type' => 'url',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '33%',
					'class' => '',
					'id' => '',
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'events',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));
}


/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Build the event calendar
add_shortcode( 'get-event-calendar', 'battleplan_getEventCalendar' );
function battleplan_getEventCalendar($atts, $content = null ) {
	//$a = shortcode_atts( array( 'info' => 'name', ), $atts );
	//$info = esc_attr($a['info']);


	$buildCalendar = '<h1 class="page-headline calendar-headline events-headline">Upcoming Events</h1>';

	$buildCalendar .= '<div class="calendar-intro events-intro"><div class="calender-btn-row">[btn link="/events/"]List View[/btn]</div></div>';

	$buildCalendar .= '<div id="calendar"></div>';
	$buildCalendar .= '<div class="calendar-buttons">';
	$buildCalendar .= '<button id="prevButton" aria-label="Previous Month"><span class="sr-only">Previous Month</span></button>';
	$buildCalendar .= '<button id="currentButton">Return To Today</button>';
	$buildCalendar .= '<button id="nextButton" aria-label="Next Month"><span class="sr-only">Next Month</span></button>';
	$buildCalendar .= '</div>';

	echo do_shortcode($buildCalendar);
}

// Display teasers of upcoming events
add_shortcode( 'event_teasers', 'battleplan_event_teasers' );
function battleplan_event_teasers( $atts, $content = null ) {
	$a = shortcode_atts( array( 'name'=>'', 'style'=>'1', 'width'=>'default', 'grid'=>'1-1-1', 'tag'=>'featured', 'max'=>'3', 'offset'=>'0', 'start'=>'today', 'end'=>'1 year', 'valign'=>'stretch', 'show_btn'=>'true', 'btn_text'=>'Read More', 'link'=>'false', 'excerpt'=>'true', 'include_section'=>'true' ), $atts );
	$start = date('Y-m-d', strtotime(esc_attr($a['start'])));
	$end = date('Y-m-d', strtotime(esc_attr($a['end'])));
	$link = esc_attr($a['link']);
	$includeSection = esc_attr($a['include_section']);
	$buildEvents = "";

	$query = bp_WP_Query('events', [
		'posts_per_page' => esc_attr($a['max']),
		'offset'         => esc_attr($a['offset']),
		'taxonomy'       => 'event-tags',
		'terms'          => esc_attr($a['tag']),
		'meta_key'       => 'start_date',
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'     => 'start_date',
				'value'   => $start,
				'compare' => '>=',
				'type'    => 'DATE'
			],
			[
				'key'     => 'start_date',
				'value'   => $end,
				'compare' => '<=',
				'type'    => 'DATE'
			]
		]
	]);

	if ($query->have_posts()) :
		while ($query->have_posts()) :
			$query->the_post();

			$buildEvents .= '[col]';
			if ( $link == "true" ) $buildEvents .= '<a href = "'.get_the_permalink($post->ID).'">';
			$buildEvents .= get_the_post_thumbnail( get_the_ID(), 'thumbnail', array( 'class' => '' ) );
			if ( $link == "true" ) $buildEvents .= '</a>';
			$buildEvents .= '[txt]<h3>'.get_the_title().'</h3>';
			$buildEvents .= include('wp-content/themes/battleplantheme/elements/element-events-meta.php');
			if ( esc_attr($a['excerpt']) == "true" ) $buildEvents.= '<p>'.get_the_excerpt().'</p>';
			$buildEvents .= '[/txt]';
			if ( esc_attr($a['show_btn']) == "true" ) $buildEvents .= '[btn link="'.esc_url(get_the_permalink($post->ID)).'"]'.esc_attr($a['btn_text']).'[/btn]';
			$buildEvents .= '[/col]';
			$num++;
	  	endwhile;
	endif;

	wp_reset_postdata();

	$buildList = "";

	if ( $buildEvents ) :
		if ($includeSection === 'true') $buildList .= '[section name="'.esc_attr($a['name']).'" style="'.esc_attr($a['style']).'" width="'.esc_attr($a['width']).'" class="event-teasers"]';
		if ($includeSection === 'true') $buildList .= '[layout grid="'.esc_attr($a['grid']).'" valign="'.esc_attr($a['valign']).'"]';
		$buildList .= $buildEvents;
		if ($includeSection === 'true') $buildList .= '[/layout][/section]';
	endif;
	return do_shortcode($buildList);
}
?>