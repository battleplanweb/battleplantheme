<?php
/* Battle Plan Web Design — PayPal (Pay in 4 / Pay Later)

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# What this is
# Credentials & environment
# PayPal API
# The signed config (why the amount is safe)
# Shortcode: [paypal]
# REST: create order
# REST: capture order
# The payment record
# Receipt & notification email
# Admin list
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# What this is
#
# Drop this on a page and a customer can pay — with PayPal, a card, or
# PayPal's "Pay in 4" (Pay Later) four-interest-free-payments plan:
#
#   [paypal amount="1250" item="Install deposit"]         fixed price
#   [paypal item="Invoice payment" min="25" max="10000"]  customer types the amount
#
# Same shape as the Site Pulse Store's Stripe checkout, and the same three
# rules, because they are the ones that matter:
#
#   1. NO SDK, NO COMPOSER. Everything server-side is wp_remote_request()
#      against PayPal's REST API. A payment library is the single most
#      consequential thing that could rot on a site nobody is watching, so
#      there isn't one.
#
#   2. WE ARE NOT THE MERCHANT. The client's own PayPal account takes the
#      money. Card details never touch this server — the buttons are PayPal's
#      own iframes, so the site stays SAQ-A.
#
#   3. THE SERVER OWNS THE AMOUNT. See "# The signed config". The browser
#      never gets to say what something costs.
#
# Unlike the store there is no cart, no stock and no order to reserve: this is
# a one-shot payment button. The record is written AFTER capture succeeds,
# because until PayPal says COMPLETED there is nothing worth keeping.
#
# Dormant until wp-config defines PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET,
# so a site that isn't using it pays nothing but the require.
--------------------------------------------------------------*/


/*--------------------------------------------------------------
# Credentials & environment
--------------------------------------------------------------*/

/**
 * The client id. PUBLIC by design — it ships in the SDK URL in the page source,
 * exactly like a Stripe publishable key. Only the secret is a secret.
 */
function bp_paypal_client_id(): string {
	return defined( 'PAYPAL_CLIENT_ID' ) ? bp_paypal_clean_key( (string) PAYPAL_CLIENT_ID ) : '';
}

/**
 * Strip ALL whitespace, not just the ends.
 *
 * trim() is not enough. PayPal's dashboard wraps a long credential across lines,
 * and a value selected by dragging picks up the newline — in the MIDDLE of the
 * string, where trim() can't see it. It then goes into base64_encode("$id:$secret")
 * and the Basic auth header is silently wrong, which PayPal reports as the same
 * "Client Authentication failed" you get for a genuinely bad key. Hours of
 * re-copying a credential that was correct all along.
 *
 * Nothing legitimate is lost: PayPal credentials are base64url-ish (A–Z a–z 0–9
 * - _ .) and never contain whitespace.
 */
function bp_paypal_clean_key( string $raw ): string {
	return (string) preg_replace( '/\s+/', '', $raw );
}

function bp_paypal_client_secret(): string {
	return defined( 'PAYPAL_CLIENT_SECRET' ) ? bp_paypal_clean_key( (string) PAYPAL_CLIENT_SECRET ) : '';
}

/**
 * Sandbox or live. Sandbox needs its OWN client id/secret pair from the sandbox
 * app — the live pair will not authenticate against the sandbox host, so this
 * switch gets flipped at the same time the constants are swapped:
 *
 *   define( 'PAYPAL_SANDBOX', true );
 */
function bp_paypal_sandbox(): bool {
	return defined( 'PAYPAL_SANDBOX' ) && PAYPAL_SANDBOX;
}

function bp_paypal_api_base(): string {
	return bp_paypal_sandbox() ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
}

/** Is checkout actually usable? Everything customer-facing gates on this. */
function bp_paypal_ready(): bool {
	return bp_paypal_client_id() !== '' && bp_paypal_client_secret() !== '';
}

/** Currency. Filterable per site; Pay in 4 is US/USD, which is why that's the default. */
function bp_paypal_currency(): string {
	return strtoupper( (string) apply_filters( 'bp_paypal_currency', 'USD' ) );
}

/** Where a completed payment lands. A real page keeps the receipt bookmarkable. */
function bp_paypal_default_redirect(): string {
	return (string) apply_filters( 'bp_paypal_redirect', '/payment-received/' );
}

/** Transient key for the access token — see bp_paypal_token(). */
function bp_paypal_token_key(): string {
	return 'bp_paypal_token_' . ( bp_paypal_sandbox() ? 'sb_' : 'live_' ) . substr( md5( bp_paypal_client_id() ), 0, 12 );
}


/*--------------------------------------------------------------
# PayPal API
--------------------------------------------------------------*/

/**
 * An OAuth2 access token, cached.
 *
 * PayPal issues these for ~9 hours and rate-limits the token endpoint, so
 * minting one per request would be both slow and rude. Cached just under the
 * lifetime PayPal reports, keyed on the environment AND a hash of the client id
 * so swapping credentials (or flipping to sandbox) can never serve a token
 * minted for the other account.
 */
function bp_paypal_token() {
	$id     = bp_paypal_client_id();
	$secret = bp_paypal_client_secret();
	if ( $id === '' || $secret === '' ) {
		return new WP_Error( 'bp_paypal_no_keys', 'PayPal is not configured for this site yet.' );
	}

	$cached = get_transient( bp_paypal_token_key() );
	if ( is_string( $cached ) && $cached !== '' ) return $cached;

	$res = wp_remote_post( bp_paypal_api_base() . '/v1/oauth2/token', [
		'timeout' => 20,
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $id . ':' . $secret ),
			'Content-Type'  => 'application/x-www-form-urlencoded',
		],
		'body'    => [ 'grant_type' => 'client_credentials' ],
	] );

	if ( is_wp_error( $res ) ) return $res;

	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( $code !== 200 || empty( $body['access_token'] ) ) {
		$msg = (string) ( $body['error_description'] ?? 'PayPal would not issue an access token.' );
		error_log( 'bp_paypal token ' . $code . ': ' . $msg );

		// Leave a breadcrumb for wp-admin. Without this the only sign of a
		// credential problem is a customer-facing sentence on the front end that
		// nobody logged in ever sees — and the admin has no reason to suspect the
		// button is dead. Costs nothing: it is set by a failure that already
		// happened, so the notice never makes an API call of its own.
		set_transient( 'bp_paypal_auth_failing', $msg, HOUR_IN_SECONDS );

		return new WP_Error( 'bp_paypal_token', $msg, [ 'status' => $code ] );
	}

	delete_transient( 'bp_paypal_auth_failing' );

	// Expire ours five minutes before PayPal expires theirs, so a token can never
	// go stale mid-request and turn a real payment into a 401.
	$ttl = max( 60, (int) ( $body['expires_in'] ?? 32400 ) - 300 );
	set_transient( bp_paypal_token_key(), (string) $body['access_token'], $ttl );

	return (string) $body['access_token'];
}

/**
 * One call to PayPal. JSON in, JSON out.
 *
 * $request_id becomes PayPal-Request-Id, which is their idempotency key: a
 * retried create returns the ORIGINAL order instead of making a second one,
 * which is what stops a customer who double-clicks paying twice.
 *
 * A 401 is retried exactly once with a freshly minted token. PayPal can revoke
 * a token before its stated expiry (a credential rotation on their side, most
 * often), and without this the site would keep failing until the transient
 * aged out on its own.
 */
function bp_paypal_api( string $method, string $path, array $body = [], string $request_id = '', bool $retry = true ) {
	$token = bp_paypal_token();
	if ( is_wp_error( $token ) ) return $token;

	$args = [
		'method'  => strtoupper( $method ),
		'timeout' => 30,
		'headers' => [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		],
		// PayPal wants a JSON body on POST /capture even though there is nothing
		// to say — an empty object, not an empty string.
		'body'    => wp_json_encode( $body ? $body : (object) [] ),
	];
	if ( $request_id !== '' ) $args['headers']['PayPal-Request-Id'] = $request_id;

	$res = wp_remote_request( bp_paypal_api_base() . '/' . ltrim( $path, '/' ), $args );
	if ( is_wp_error( $res ) ) return $res;

	$code = (int) wp_remote_retrieve_response_code( $res );
	$out  = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( $code === 401 && $retry ) {
		delete_transient( bp_paypal_token_key() );
		return bp_paypal_api( $method, $path, $body, $request_id, false );
	}

	if ( $code >= 200 && $code < 300 ) return is_array( $out ) ? $out : [];

	// PayPal's useful text is buried in details[0].description, not in the
	// top-level message, which is usually just "The requested action could not
	// be performed". debug_id is the one thing PayPal support will ask for, so
	// it goes in the log and, for an admin, on screen.
	$msg   = (string) ( $out['details'][0]['description'] ?? $out['message'] ?? 'PayPal returned an unexpected error.' );
	$issue = (string) ( $out['details'][0]['issue'] ?? $out['name'] ?? '' );
	$field = (string) ( $out['details'][0]['field'] ?? '' );
	$debug = (string) ( $out['debug_id'] ?? '' );

	error_log( 'bp_paypal ' . $path . ' ' . $code . ' ' . $issue . ': ' . $msg
		. ( $field !== '' ? ' [field: ' . $field . ']' : '' )
		. ( $debug !== '' ? ' [debug_id: ' . $debug . ']' : '' )
		. ' ' . wp_json_encode( $out ) );

	return new WP_Error( 'bp_paypal', $msg, [
		'status' => $code,
		'issue'  => $issue,
		'field'  => $field,
		'debug'  => $debug,
		'body'   => $out,
	] );
}


/*--------------------------------------------------------------
# The signed config (why the amount is safe)
#
# The browser has to be told what it is paying for, and the browser can be
# edited. So the shortcode emits its settings as base64 JSON with an HMAC
# alongside, and the REST endpoints refuse anything whose hash does not match.
# Change the amount and the signature stops matching; forge a signature and you
# would need wp_salt('auth') off the server first.
#
# A nonce would ALSO work and would be wrong here: nonces are per-user and
# time-boxed, which makes the markup uncacheable and breaks behind EverCache.
# This is the same HMAC trick [bp-form] already uses for its spam check, for
# exactly the same reason.
--------------------------------------------------------------*/

function bp_paypal_sign( array $cfg ): array {
	$b64 = base64_encode( (string) wp_json_encode( $cfg ) );
	return [ $b64, hash_hmac( 'sha256', $b64, wp_salt( 'auth' ) ) ];
}

/** Verify, then decode. Returns the config array or a WP_Error — never a guess. */
function bp_paypal_unsign( string $b64, string $sig ) {
	if ( $b64 === '' || $sig === '' ) {
		return new WP_Error( 'bp_paypal_unsigned', 'This payment form is missing its configuration. Please reload the page.' );
	}
	if ( ! hash_equals( hash_hmac( 'sha256', $b64, wp_salt( 'auth' ) ), $sig ) ) {
		return new WP_Error( 'bp_paypal_bad_sig', 'This payment form has been altered. Please reload the page.' );
	}
	$cfg = json_decode( (string) base64_decode( $b64, true ), true );
	if ( ! is_array( $cfg ) ) {
		return new WP_Error( 'bp_paypal_bad_cfg', 'This payment form is not readable. Please reload the page.' );
	}
	return $cfg;
}

/**
 * Settle on the amount to charge, in dollars, as a string with 2 decimals.
 *
 * Fixed-amount buttons ignore whatever the browser sent outright. Open-amount
 * buttons accept a number but clamp it to the min/max the SHORTCODE set — not
 * the ones the browser claims, because min and max travel inside the signed
 * config too.
 */
function bp_paypal_resolve_amount( array $cfg, $submitted ) {
	if ( ! empty( $cfg['amount'] ) ) return number_format( (float) $cfg['amount'], 2, '.', '' );

	$amount = (float) preg_replace( '/[^0-9.]/', '', (string) $submitted );
	$min    = isset( $cfg['min'] ) ? (float) $cfg['min'] : 1.0;
	$max    = isset( $cfg['max'] ) ? (float) $cfg['max'] : 0.0;

	if ( $amount < $min ) {
		return new WP_Error( 'bp_paypal_low', sprintf( 'The smallest payment we can take is $%s.', number_format( $min, 2 ) ) );
	}
	if ( $max > 0 && $amount > $max ) {
		return new WP_Error( 'bp_paypal_high', sprintf( 'The largest payment we can take online is $%s. Please call us for anything above that.', number_format( $max, 2 ) ) );
	}
	return number_format( $amount, 2, '.', '' );
}


/*--------------------------------------------------------------
# Shortcode: [paypal]
--------------------------------------------------------------*/

add_shortcode( 'paypal', 'bp_paypal_shortcode' );

/**
 * [paypal]
 *
 *   amount     fixed price, e.g. "1250" or "1250.00". Omit for a customer-entered amount.
 *   item       what they are paying for. Shows on the PayPal screen and both receipts.
 *   min / max  bounds for a customer-entered amount (default 1 / no cap).
 *   label      wording above an open amount field. Default "Amount to pay".
 *   fields     comma list of details to collect first: name, email, phone, note.
 *              Everything listed is required except note. Default "name,email".
 *   paylater   "false" drops the Pay Later / Pay in 4 button. Default on — it's the point.
 *   card       "false" drops the debit/credit card button (PayPal + Pay Later only).
 *   message    "false" hides the "4 interest-free payments of $x" banner above the buttons.
 *   redirect   thank-you page. Default /payment-received/.
 *   class      extra classes on the wrapper.
 */
function bp_paypal_shortcode( $atts ) {
	$a = shortcode_atts( [
		'amount'   => '',
		'item'     => '',
		'min'      => '1',
		'max'      => '',
		'label'    => 'Amount to pay',
		'fields'   => 'name,email',
		'paylater' => 'true',
		'card'     => 'true',
		'message'  => 'true',
		'redirect' => '',
		'class'    => '',
	], $atts, 'paypal' );

	if ( ! bp_paypal_ready() ) {
		// Say nothing to the public — an error box on a live page is worse than a
		// missing button. Only the person who can fix it gets told.
		return current_user_can( 'manage_options' )
			? '<div class="bp-pp-notice">PayPal is not configured: add PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET to wp-config.php.</div>'
			: '';
	}

	$ci   = function_exists( 'customer_info' ) ? (array) customer_info() : [];
	$item = trim( (string) $a['item'] );
	if ( $item === '' ) $item = ( $ci['name'] ?? get_bloginfo( 'name' ) ) . ' payment';

	$amount = $a['amount'] !== ''
		? number_format( (float) preg_replace( '/[^0-9.]/', '', $a['amount'] ), 2, '.', '' )
		: '';

	$fields = array_map( 'trim', explode( ',', strtolower( (string) $a['fields'] ) ) );
	$fields = array_values( array_intersect( $fields, [ 'name', 'email', 'phone', 'note' ] ) );

	// Everything the server will need at capture time, signed. Note what is NOT in
	// here: the customer's own details. Those are collected in the browser and sent
	// alongside — they only ever get written down, never priced off.
	$cfg = [
		'amount'   => $amount,
		'item'     => $item,
		'min'      => (float) $a['min'],
		'max'      => $a['max'] !== '' ? (float) $a['max'] : 0,
		'fields'   => $fields,
		'redirect' => $a['redirect'] !== '' ? $a['redirect'] : bp_paypal_default_redirect(),
		'page'     => (int) get_the_ID(),
	];
	list( $cfg_b64, $cfg_sig ) = bp_paypal_sign( $cfg );

	bp_inline_minified_css( get_template_directory() . '/style-paypal.css' );
	bp_paypal_enqueue_sdk( $a['paylater'] !== 'false', $a['card'] !== 'false' );
	bp_enqueue_script( 'battleplan-script-paypal', 'script-paypal', [ 'paypal-sdk' ] );

	$uid = 'pp-' . substr( md5( $cfg_b64 ), 0, 8 );

	$out  = '<div class="bp-paypal ' . esc_attr( trim( (string) $a['class'] ) ) . '" id="' . esc_attr( $uid ) . '"'
		. ' data-cfg="' . esc_attr( $cfg_b64 ) . '"'
		. ' data-sig="' . esc_attr( $cfg_sig ) . '"'
		. ' data-amount="' . esc_attr( $amount ) . '"'
		. ' data-currency="' . esc_attr( bp_paypal_currency() ) . '"'
		. ' data-message="' . ( $a['message'] !== 'false' ? '1' : '0' ) . '"'
		. ' data-debug="' . ( bp_paypal_debug_on() ? '1' : '0' ) . '"'
		. ' data-rest="' . esc_url( rest_url( 'bp/v1/paypal' ) ) . '">';

	$out .= '<div class="bp-pp-fields">';

	// Open amount: the one field that changes what gets charged, so it goes first
	// and it is the thing the Pay Later banner re-renders against.
	if ( $amount === '' ) {
		$out .= '<label class="bp-pp-field bp-pp-amount-field">'
			. '<span>' . esc_html( $a['label'] ) . '</span>'
			. '<span class="bp-pp-currency">$</span>'
			. '<input type="text" inputmode="decimal" class="bp-pp-amount" autocomplete="off" placeholder="0.00" required>'
			. '</label>';
	} else {
		$out .= '<div class="bp-pp-total"><span>' . esc_html( $item ) . '</span>'
			. '<strong>$' . esc_html( number_format( (float) $amount, 2 ) ) . '</strong></div>';
	}

	$labels = [ 'name' => 'Your name', 'email' => 'Email address', 'phone' => 'Phone number', 'note' => 'Note (optional)' ];
	$types  = [ 'name' => 'text', 'email' => 'email', 'phone' => 'tel' ];
	$auto   = [ 'name' => 'name', 'email' => 'email', 'phone' => 'tel' ];

	foreach ( $fields as $f ) {
		if ( $f === 'note' ) {
			$out .= '<label class="bp-pp-field"><span>' . esc_html( $labels[ $f ] ) . '</span>'
				. '<textarea class="bp-pp-input" data-key="note" rows="2"></textarea></label>';
			continue;
		}
		$out .= '<label class="bp-pp-field"><span>' . esc_html( $labels[ $f ] ) . '</span>'
			. '<input type="' . esc_attr( $types[ $f ] ) . '" class="bp-pp-input" data-key="' . esc_attr( $f ) . '"'
			. ' autocomplete="' . esc_attr( $auto[ $f ] ) . '" required></label>';
	}

	$out .= '</div>';

	// The Pay Later banner. PayPal's own data-pp-* attributes rather than a
	// JS call, because the SDK auto-bootstraps anything carrying data-pp-message
	// on load — so a FIXED amount needs no JavaScript from us at all. An open
	// amount has no number until somebody types one, so script-paypal.js rewrites
	// data-pp-amount and asks the SDK to render again.
	// data-pp-pageType, NOT data-pp-placement. Older PayPal docs used `placement`
	// with values like "payment"; the v5 reference replaced it, and an
	// unrecognised attribute is ignored silently rather than warning — so the
	// banner renders, just without the context that tunes the copy.
	if ( $a['message'] !== 'false' ) {
		$out .= '<div class="bp-pp-message"><div data-pp-message data-pp-pageType="checkout"'
			. ( $amount !== '' ? ' data-pp-amount="' . esc_attr( $amount ) . '"' : '' )
			. ' data-pp-style-layout="text" data-pp-style-logo-type="inline"></div></div>';
	}

	$out .= '<div class="bp-pp-buttons"></div>';
	$out .= '<div class="bp-pp-status" role="status" aria-live="polite"></div>';
	$out .= bp_paypal_admin_hint( $amount );
	$out .= '</div>';

	return $out;
}

/**
 * A note only logged-in admins see, for the two reasons Pay in 4 silently
 * doesn't appear that are nothing to do with this code.
 *
 * Worth printing because the failure mode is a button that renders perfectly —
 * just without the Pay Later option on it — so there is nothing to debug and
 * no error anywhere to go looking for.
 */
function bp_paypal_admin_hint( string $amount ): string {
	if ( ! current_user_can( 'manage_options' ) ) return '';

	$notes = [];

	// PayPal's published Pay in 4 range for the US. Outside it there is no offer
	// to make, so neither the banner nor the button appears.
	$n = (float) $amount;
	if ( $amount !== '' && ( $n < 30 || $n > 1500 ) ) {
		$notes[] = sprintf(
			'$%s is outside PayPal\'s Pay in 4 range ($30&ndash;$1,500), so no Pay Later button or banner will show at this price.',
			number_format( $n, 2 )
		);
	}
	if ( $amount === '' ) {
		$notes[] = 'Pay in 4 only appears once the amount entered is between $30 and $1,500.';
	}
	if ( bp_paypal_sandbox() ) {
		$notes[] = 'Sandbox mode: buyer-country=US is being sent so Pay Later renders for test buyers. It is dropped automatically on live.';
	}

	$out = '';

	if ( $notes ) {
		$out .= '<div class="bp-pp-notice bp-pp-notice-info"><strong>Admin only:</strong> ' . implode( ' ', $notes ) . '</div>';
	}

	return $out;
}

/**
 * Payments &rarr; PayPal Status.
 *
 * The credential check lives HERE, in wp-admin, and not on the front end behind
 * a debug flag. Two reasons, both learned the hard way:
 *
 *   1. The front-end version was gated on current_user_can(), and the obvious
 *      way to test a payment button is logged out, in a private window — so the
 *      report was invisible exactly when it was wanted.
 *   2. Ungating it would have printed a fingerprint of the client SECRET to
 *      anyone who appended a query string. Four characters is not an exploit,
 *      but it is still bytes of a live credential handed to strangers.
 *
 * An admin screen has neither problem: WordPress does the capability check, the
 * page is never cached, and there is no query string to remember.
 */
/**
 * Tell the admin the button is dead.
 *
 * A credential failure is invisible from inside wp-admin: the only symptom is a
 * sentence shown to a customer who is already walking away. This surfaces the
 * last real failure on every admin screen, with the one link that explains it.
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$msg = get_transient( 'bp_paypal_auth_failing' );
	if ( ! $msg ) return;

	echo '<div class="notice notice-error"><p><strong>PayPal is rejecting this site\'s credentials</strong> &mdash; '
		. esc_html( (string) $msg ) . '. No payment can complete until this is fixed. '
		. '<a href="' . esc_url( admin_url( 'edit.php?post_type=bp_payment&page=bp-paypal-status' ) ) . '">'
		. 'Open PayPal Status</a> to see which part is wrong.</p></div>';
} );

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=bp_payment',
		'PayPal Status',
		'PayPal Status',
		'manage_options',
		'bp-paypal-status',
		function () {
			echo '<div class="wrap"><h1>PayPal Status</h1>';

			if ( ! bp_paypal_ready() ) {
				echo '<div class="notice notice-error"><p><strong>Not configured.</strong> Add '
					. '<code>PAYPAL_CLIENT_ID</code> and <code>PAYPAL_CLIENT_SECRET</code> to wp-config.php.</p></div>';
			}

			// The check caches for a minute so a reload doesn't hammer PayPal's
			// rate-limited token endpoint — but the whole point of this screen is
			// fixing wp-config and looking again, so there has to be a way to skip it.
			if ( isset( $_GET['recheck'] ) && check_admin_referer( 'bp_paypal_recheck' ) ) {
				delete_transient( 'bp_paypal_credcheck_' . substr( md5( bp_paypal_client_id() . bp_paypal_client_secret() ), 0, 12 ) );
				delete_transient( bp_paypal_token_key() );
			}

			echo bp_paypal_credential_report();

			echo '<p><a class="button" href="' . esc_url( wp_nonce_url(
				admin_url( 'edit.php?post_type=bp_payment&page=bp-paypal-status&recheck=1' ),
				'bp_paypal_recheck'
			) ) . '">Re-check now</a> <span class="description">Clears the cached result and the stored access token.</span></p>';

			$redirect = bp_paypal_default_redirect();
			$page     = get_page_by_path( trim( (string) wp_parse_url( $redirect, PHP_URL_PATH ), '/' ) );
			echo '<h2>Thank-you page</h2><p><code>' . esc_html( $redirect ) . '</code> &mdash; '
				. ( $page ? '<span style="color:green">exists</span>' : '<strong style="color:#b32d2e">does not exist yet.</strong> '
					. 'Create it, or point the shortcode somewhere else with <code>redirect=""</code>.' ) . '</p>';

			echo '<h2>Pay in 4</h2><p>Shown by PayPal for amounts between <strong>$30 and $1,500</strong>. '
				. 'Between $199 and $1,500 <strong>Pay Monthly</strong> (6/12/24 months) also qualifies and PayPal '
				. 'decides which one the banner promotes &mdash; there is no setting for this. To see Pay in 4 in the '
				. 'banner, test an amount between $30 and $198.</p>';

			echo '</div>';
		}
	);
} );

/**
 * Say exactly what is wrong with the credentials.
 *
 * "Client Authentication failed" is PayPal's entire answer when a client
 * id/secret pair is rejected, and it is the same sentence whether the keys are
 * wrong, truncated, still placeholders, or simply the other environment's — so
 * on its own it tells you nothing about which.
 *
 * The trick is to try the OTHER host. Credentials that fail against live and
 * succeed against sandbox are not broken, they are sandbox keys on a site that
 * never set PAYPAL_SANDBOX — which is the overwhelmingly common case and,
 * unhelpfully, the one that looks most like a typo.
 *
 * Results are cached for a minute so reloading the page while reading this
 * doesn't hammer PayPal's token endpoint, which is rate-limited.
 */
function bp_paypal_credential_report(): string {
	$id     = bp_paypal_client_id();
	$secret = bp_paypal_client_secret();

	$rows = [];
	$rows[] = 'Mode: <strong>' . ( bp_paypal_sandbox() ? 'SANDBOX' : 'LIVE' ) . '</strong> (' . esc_html( bp_paypal_api_base() ) . ')';

	// Fingerprint, never the value. Enough to spot a truncated paste or a secret
	// that picked up a stray character, without printing the key itself.
	// PayPal's live credentials are consistently around these lengths. A value a
	// long way off is the single most useful number on this screen: it means the
	// paste was truncated, which looks identical to a wrong key from the error
	// message alone.
	$expect = [ 'PAYPAL_CLIENT_ID' => 80, 'PAYPAL_CLIENT_SECRET' => 64 ];
	$raw    = [
		'PAYPAL_CLIENT_ID'     => defined( 'PAYPAL_CLIENT_ID' ) ? (string) PAYPAL_CLIENT_ID : '',
		'PAYPAL_CLIENT_SECRET' => defined( 'PAYPAL_CLIENT_SECRET' ) ? (string) PAYPAL_CLIENT_SECRET : '',
	];

	foreach ( [ 'PAYPAL_CLIENT_ID' => $id, 'PAYPAL_CLIENT_SECRET' => $secret ] as $label => $val ) {
		if ( $val === '' ) {
			$rows[] = $label . ': <strong style="color:#b32d2e">NOT SET</strong>'
				. ( $raw[ $label ] !== '' ? ' (defined but empty after cleaning)' : '' );
			continue;
		}
		if ( stripos( $val, 'YOUR_' ) === 0 ) {
			$rows[] = $label . ': <strong style="color:#b32d2e">still the placeholder</strong> (' . esc_html( $val ) . ')';
			continue;
		}

		$len   = strlen( $val );
		$flags = [];

		// Whitespace INSIDE the value — the one trim() never caught. See
		// bp_paypal_clean_key(): this is now repaired automatically, but it is
		// still worth reporting, because it means the value in wp-config is not
		// what it looks like.
		if ( preg_replace( '/\s+/', '', $raw[ $label ] ) !== trim( $raw[ $label ] ) ) {
			$flags[] = '<strong style="color:#b32d2e">contained whitespace &mdash; stripped automatically, but fix the paste in wp-config</strong>';
		}
		if ( preg_match( '/[^A-Za-z0-9\-_.]/', $val ) ) {
			$flags[] = '<strong style="color:#b32d2e">contains characters PayPal keys never use</strong> &mdash; check for a smart quote or a stray character';
		}
		if ( $len < $expect[ $label ] - 20 ) {
			$flags[] = '<strong style="color:#b32d2e">looks truncated</strong> (expected roughly ' . $expect[ $label ] . ')';
		}

		$rows[] = $label . ': ' . $len . ' chars (expected ~' . $expect[ $label ] . '), '
			. esc_html( substr( $val, 0, 4 ) ) . '&hellip;' . esc_html( substr( $val, -4 ) )
			. ( $flags ? '<br>&nbsp;&nbsp;&nbsp;&rarr; ' . implode( '<br>&nbsp;&nbsp;&nbsp;&rarr; ', $flags ) : '' );
	}

	if ( $id === '' || $secret === '' ) {
		return bp_paypal_report_box( $rows );
	}

	$cache_key = 'bp_paypal_credcheck_' . substr( md5( $id . $secret ), 0, 12 );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) return bp_paypal_report_box( array_merge( $rows, $cached ) );

	$verdict = [];
	$here    = bp_paypal_try_token( bp_paypal_api_base(), $id, $secret );

	if ( $here === true ) {
		$verdict[] = '<strong style="color:green">These credentials authenticate fine against '
			. ( bp_paypal_sandbox() ? 'sandbox' : 'live' ) . '.</strong> The problem is elsewhere.';
	} else {
		$verdict[] = '<strong style="color:red">Rejected by ' . ( bp_paypal_sandbox() ? 'sandbox' : 'live' )
			. ':</strong> ' . esc_html( (string) $here );

		// The decisive test.
		$other_base = bp_paypal_sandbox() ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
		$other_name = bp_paypal_sandbox() ? 'LIVE' : 'SANDBOX';

		if ( bp_paypal_try_token( $other_base, $id, $secret ) === true ) {
			$verdict[] = '<strong>&rarr; They ARE valid ' . $other_name . ' credentials.</strong> '
				. ( bp_paypal_sandbox()
					? 'Remove <code>define( \'PAYPAL_SANDBOX\', true );</code> from wp-config.php.'
					: 'Add <code>define( \'PAYPAL_SANDBOX\', true );</code> to wp-config.php &mdash; or swap in the Live keys from the PayPal dashboard (toggle Sandbox&rarr;Live in Apps &amp; Credentials).' );
		} else {
			$verdict[] = '&rarr; <strong>Rejected by both hosts, so this is not an environment mismatch.</strong> '
				. 'The pair itself is not valid. In order of likelihood:';
			$verdict[] = '&nbsp;&nbsp;1. <strong>The secret was regenerated.</strong> Generating a new secret in the '
				. 'PayPal dashboard kills the old one immediately, and the dashboard keeps showing the app as if '
				. 'nothing changed. Generate a fresh secret and copy both values again.';
			$verdict[] = '&nbsp;&nbsp;2. <strong>The id and secret are from different apps.</strong> Easy to do with '
				. 'two apps open. They must come from the same one.';
			$verdict[] = '&nbsp;&nbsp;3. <strong>The paste is short.</strong> Compare the character counts above '
				. 'against what the dashboard shows &mdash; use its copy button rather than selecting the text.';
			$verdict[] = '&nbsp;&nbsp;4. <strong>The Live app isn\'t usable yet.</strong> A REST app on a business '
				. 'account that hasn\'t finished setup will not issue tokens at all.';
		}
	}

	set_transient( $cache_key, $verdict, MINUTE_IN_SECONDS );

	return bp_paypal_report_box( array_merge( $rows, $verdict ) );
}

/** One token attempt against a named host. true, or PayPal's own error text. */
function bp_paypal_try_token( string $base, string $id, string $secret ) {
	$res = wp_remote_post( $base . '/v1/oauth2/token', [
		'timeout' => 20,
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $id . ':' . $secret ),
			'Content-Type'  => 'application/x-www-form-urlencoded',
		],
		'body'    => [ 'grant_type' => 'client_credentials' ],
	] );

	if ( is_wp_error( $res ) ) return $res->get_error_message();

	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( $code === 200 && ! empty( $body['access_token'] ) ) return true;

	// Both halves matter. error_description is the sentence ("Client Authentication
	// failed"); error is the machine code, and it is the part that discriminates —
	// invalid_client means the pair was rejected, anything else means something
	// other than the credentials is wrong.
	$desc = (string) ( $body['error_description'] ?? '' );
	$name = (string) ( $body['error'] ?? '' );

	return trim( ( $desc !== '' ? $desc : 'HTTP ' . $code ) . ( $name !== '' ? ' [' . $name . ']' : '' ) );
}

/**
 * Self-styled on purpose: this renders in wp-admin, where style-paypal.css is
 * never loaded (it is inlined by the shortcode, on the front end only).
 */
function bp_paypal_report_box( array $rows ): string {
	return '<h2>Credentials</h2><div style="max-width:820px;padding:14px 18px;line-height:1.7;'
		. 'background:#fff;border:1px solid #c3c4c7;border-left:4px solid #72aee6">'
		. implode( '<br>', $rows ) . '</div>';
}

/**
 * The PayPal JS SDK.
 *
 * Registered rather than written straight into the markup so two [paypal] blocks
 * on one page share a single copy, and so script-paypal.js can declare it as a
 * dependency and be guaranteed `window.paypal` exists by the time it runs.
 *
 * enable-funding=paylater is the whole feature: without it PayPal shows only the
 * gold button and "Pay in 4" never appears.
 *
 * First shortcode on the page wins the funding options. Two [paypal] blocks with
 * different card/paylater settings would need two SDK copies with different
 * query strings, and PayPal does not support that on one page.
 */
function bp_paypal_enqueue_sdk( bool $paylater = true, bool $card = true ): void {
	if ( wp_script_is( 'paypal-sdk', 'registered' ) ) return;

	$args = [
		'client-id'  => bp_paypal_client_id(),
		'currency'   => bp_paypal_currency(),
		'intent'     => 'capture',
		// messages = the "4 interest-free payments of $x" banner. It is a separate
		// component from the buttons and silently does nothing if left out.
		'components' => 'buttons,messages',
	];
	if ( $paylater ) $args['enable-funding'] = 'paylater';

	// Everything PayPal would otherwise offer that this button has no business
	// showing: PayPal Credit (the revolving line — a DIFFERENT product from Pay in
	// 4, and disabling it does not disable Pay Later) and Venmo, which needs its
	// own onboarding before it will even render.
	$disable = [ 'venmo', 'credit' ];
	if ( ! $card ) $disable[] = 'card';
	$args['disable-funding'] = implode( ',', $disable );

	// SANDBOX ONLY, and this is the usual reason Pay Later "doesn't work" in
	// testing: the SDK picks the buyer's country from their IP, a sandbox buyer
	// has no real one, and a buyer of unknown country is not eligible for Pay
	// Later — so the button silently never renders. buyer-country forces it.
	//
	// PayPal REJECTS this parameter in production, so it is gated on the sandbox
	// switch rather than just always sent.
	if ( bp_paypal_sandbox() ) {
		$args['buyer-country'] = (string) apply_filters( 'bp_paypal_buyer_country', 'US' );
	}

	// Last resort for a site that needs a parameter this module doesn't expose.
	$args = (array) apply_filters( 'bp_paypal_sdk_args', $args );

	$src = 'https://www.paypal.com/sdk/js?' . http_build_query( $args, '', '&' );

	// No version string: PayPal serves this URL themselves and a ?ver= would only
	// fight their cache.
	wp_enqueue_script( 'paypal-sdk', $src, [], null, true );
}


/*--------------------------------------------------------------
# REST: create order
--------------------------------------------------------------*/

add_action( 'rest_api_init', function () {
	register_rest_route( 'bp/v1', '/paypal/order', [
		'methods'             => 'POST',
		'callback'            => 'bp_paypal_rest_create',
		'permission_callback' => '__return_true', // the signed config IS the authorization
	] );
	register_rest_route( 'bp/v1', '/paypal/capture', [
		'methods'             => 'POST',
		'callback'            => 'bp_paypal_rest_capture',
		'permission_callback' => '__return_true',
	] );
} );

function bp_paypal_rest_create( WP_REST_Request $req ) {
	if ( ! bp_paypal_ready() ) return bp_paypal_rest_error( 'PayPal is not set up on this site.', 503 );

	$cfg = bp_paypal_unsign( (string) $req->get_param( 'cfg' ), (string) $req->get_param( 'sig' ) );
	if ( is_wp_error( $cfg ) ) return bp_paypal_rest_error( $cfg->get_error_message(), 400 );

	$amount = bp_paypal_resolve_amount( $cfg, $req->get_param( 'amount' ) );
	if ( is_wp_error( $amount ) ) return bp_paypal_rest_error( $amount->get_error_message(), 400 );

	$ci    = function_exists( 'customer_info' ) ? (array) customer_info() : [];
	$brand = (string) ( $ci['name'] ?? get_bloginfo( 'name' ) );

	$order = bp_paypal_api( 'POST', '/v2/checkout/orders', [
		'intent'         => 'CAPTURE',
		'purchase_units' => [ [
			'description' => mb_substr( (string) $cfg['item'], 0, 127 ),
			// custom_id, NOT invoice_id. PayPal treats a repeated invoice_id as a
			// duplicate and refuses the capture — right for invoices, wrong for a
			// pay button, where the same person paying the same amount twice is a
			// perfectly legitimate thing to want to do.
			'custom_id'   => mb_substr( 'bp:' . (int) ( $cfg['page'] ?? 0 ) . ':' . gmdate( 'Ymd' ), 0, 127 ),
			'amount'      => [
				'currency_code' => bp_paypal_currency(),
				'value'         => $amount,
			],
		] ],
		'payment_source' => [ 'paypal' => [ 'experience_context' => [
			'brand_name'          => mb_substr( $brand, 0, 127 ),
			// Nothing here ships, so asking for an address would only add a step
			// people abandon at.
			'shipping_preference' => 'NO_SHIPPING',
			'user_action'         => 'PAY_NOW',
			// No return_url / cancel_url. Those belong to the REDIRECT flow, where
			// PayPal sends the browser back to the site itself. The JS SDK never
			// redirects — it opens its own window and hands control back through
			// onApprove — so they are at best dead weight here.
		] ] ],
	], 'bp-' . wp_generate_uuid4() );

	if ( is_wp_error( $order ) ) return bp_paypal_rest_error( $order->get_error_message(), 502, $order, $req );

	return new WP_REST_Response( [ 'id' => (string) ( $order['id'] ?? '' ) ], 200 );
}


/*--------------------------------------------------------------
# REST: capture order
#
# The money moves here. Everything before this point is reversible by closing
# the tab; from COMPLETED onwards there is a real charge, so the record and the
# emails are written on the way back out and never before.
--------------------------------------------------------------*/

function bp_paypal_rest_capture( WP_REST_Request $req ) {
	if ( ! bp_paypal_ready() ) return bp_paypal_rest_error( 'PayPal is not set up on this site.', 503 );

	$cfg = bp_paypal_unsign( (string) $req->get_param( 'cfg' ), (string) $req->get_param( 'sig' ) );
	if ( is_wp_error( $cfg ) ) return bp_paypal_rest_error( $cfg->get_error_message(), 400 );

	$order_id = preg_replace( '/[^A-Za-z0-9]/', '', (string) $req->get_param( 'order_id' ) );
	if ( $order_id === '' ) return bp_paypal_rest_error( 'There is no PayPal order to capture.', 400 );

	// Already captured? Say so and stop. onApprove can fire twice — a retried
	// fetch, a double-tap on a slow phone — and without this the second one would
	// write a second payment record for one charge.
	$existing = bp_paypal_find_payment( $order_id );
	if ( $existing ) {
		return new WP_REST_Response( [
			'status'   => (string) get_post_meta( $existing, '_bp_pp_status', true ),
			'amount'   => (string) get_post_meta( $existing, '_bp_pp_amount', true ),
			'redirect' => bp_paypal_redirect_url( $cfg, (string) get_post_meta( $existing, '_bp_pp_capture', true ) ),
		], 200 );
	}

	// A PayPal-Request-Id derived from the order means a retried capture returns
	// the original capture instead of trying to take the money a second time.
	$res = bp_paypal_api( 'POST', '/v2/checkout/orders/' . $order_id . '/capture', [], 'bp-cap-' . $order_id );
	if ( is_wp_error( $res ) ) return bp_paypal_rest_error( $res->get_error_message(), 502, $res, $req );

	$unit    = $res['purchase_units'][0] ?? [];
	$capture = $unit['payments']['captures'][0] ?? [];
	$status  = strtoupper( (string) ( $capture['status'] ?? $res['status'] ?? '' ) );

	// COMPLETED is paid. PENDING is real money too — PayPal holds some payments
	// for review — so it gets recorded, but the customer is told it isn't settled.
	if ( ! in_array( $status, [ 'COMPLETED', 'PENDING' ], true ) ) {
		return bp_paypal_rest_error(
			'PayPal did not complete this payment (' . ( $status !== '' ? $status : 'unknown' ) . '). You have not been charged.',
			402
		);
	}

	// Trust PayPal's number over ours: if they captured a different amount for any
	// reason, the amount that actually moved is the one worth writing down.
	$paid     = (string) ( $capture['amount']['value'] ?? '0.00' );
	$currency = (string) ( $capture['amount']['currency_code'] ?? bp_paypal_currency() );
	$payer    = $res['payer'] ?? [];

	$details = [
		'order_id'    => $order_id,
		'capture_id'  => (string) ( $capture['id'] ?? '' ),
		'status'      => $status,
		'amount'      => $paid,
		'currency'    => $currency,
		'item'        => (string) ( $cfg['item'] ?? '' ),
		'fee'         => (string) ( $capture['seller_receivable_breakdown']['paypal_fee']['value'] ?? '' ),
		'net'         => (string) ( $capture['seller_receivable_breakdown']['net_amount']['value'] ?? '' ),
		// How they actually paid. This is where "Pay in 4" shows up, and it is the
		// first thing the client asks about when the money arrives in instalments.
		'method'      => bp_paypal_describe_source( $res, (string) $req->get_param( 'source' ) ),
		'payer_name'  => trim( (string) ( $payer['name']['given_name'] ?? '' ) . ' ' . (string) ( $payer['name']['surname'] ?? '' ) ),
		'payer_email' => sanitize_email( (string) ( $payer['email_address'] ?? '' ) ),
		'customer'    => bp_paypal_collect_customer( $req, (array) ( $cfg['fields'] ?? [] ) ),
		'page'        => (int) ( $cfg['page'] ?? 0 ),
	];

	$post_id = bp_paypal_record_payment( $details );
	bp_paypal_send_emails( $details, $post_id );

	// For per-site follow-ups: push to a CRM, tag a lead, mark an invoice paid.
	do_action( 'bp_paypal_paid', $details, $post_id );

	return new WP_REST_Response( [
		'status'   => $status,
		'amount'   => $paid,
		'redirect' => bp_paypal_redirect_url( $cfg, $details['capture_id'] ),
	], 200 );
}

/** A readable error in the shape the button expects. */
/**
 * A readable failure, ALWAYS as HTTP 200.
 *
 * ⚠️ DO NOT "FIX" THIS BACK TO A REAL 4xx/5xx. An origin 5xx on WP Engine gets
 * its body thrown away and replaced by the edge's own error page, so the
 * carefully worded JSON never reaches the browser — the customer gets a bare
 * "HTTP 502" and the actual reason is lost. Cost an afternoon once already.
 *
 * This is also what bp_handle_form_submission() does: every failure there is a
 * 200 carrying a `status` field. Same convention, same reason.
 *
 * $status is kept for the log and for debug mode, so the intent is still on
 * record — it just isn't the transport's problem any more.
 */
function bp_paypal_rest_error( string $message, int $status = 400, $wp_error = null, $req = null ): WP_REST_Response {
	$out = [ 'error' => $message, 'code' => $status ];

	// In debug mode the response also carries PayPal's own diagnosis: the issue
	// code, the field it objected to, and the debug_id their support asks for.
	// Off by default, because a customer has no use for any of it.
	if ( is_wp_error( $wp_error ) && bp_paypal_debug_on( $req ) ) {
		$data = (array) $wp_error->get_error_data();
		$out['debug'] = array_filter( [
			'issue'    => (string) ( $data['issue'] ?? '' ),
			'field'    => (string) ( $data['field'] ?? '' ),
			'debug_id' => (string) ( $data['debug'] ?? '' ),
			'status'   => (string) ( $data['status'] ?? '' ),
		] );
	}

	return new WP_REST_Response( $out, 200 );
}

/**
 * Is this request allowed to see PayPal's error detail?
 *
 * current_user_can() ALONE IS NOT ENOUGH HERE, and the reason is easy to get
 * wrong: a logged-in admin calling a REST route from the front end is anonymous
 * to WordPress unless the request carries an X-WP-Nonce, and this route
 * deliberately doesn't use one (a nonce in the markup would make the page
 * uncacheable — see "# The signed config"). So an admin check on its own would
 * silently never be true, which is exactly the sort of debug switch that looks
 * like it works and doesn't.
 *
 * Hence the flag, mirroring bp_form_debug(): define BP_PAYPAL_DEBUG in
 * wp-config, or append ?bp_paypal_debug=1 to the page URL. The capability check
 * is kept as well for the case where a nonce IS present.
 */
function bp_paypal_debug_on( $req = null ): bool {
	if ( defined( 'BP_PAYPAL_DEBUG' ) && BP_PAYPAL_DEBUG ) return true;
	if ( $req instanceof WP_REST_Request && (string) $req->get_param( 'debug' ) === '1' ) return true;
	// On the page itself (no $req): the query param the shortcode reads to decide
	// whether to hand the flag to the browser at all.
	if ( isset( $_GET['bp_paypal_debug'] ) && $_GET['bp_paypal_debug'] === '1' ) return true;
	return current_user_can( 'manage_options' );
}

/**
 * Name the funding source for the record.
 *
 * Card and Venmo are unambiguous in the capture response. PayPal proper is not:
 * a Pay in 4 comes back as an ordinary `paypal` source, so the server cannot
 * tell it from a balance/bank payment. The JS SDK CAN — onApprove hands back
 * data.paymentSource — so the browser's answer is used to split those two.
 *
 * That hint is the browser's word, which is fine for a label on a record and is
 * why it is never allowed to touch the amount. Anything the browser claims that
 * contradicts the capture response loses.
 */
function bp_paypal_describe_source( array $res, string $hint = '' ): string {
	$src = $res['payment_source'] ?? [];

	if ( isset( $src['card'] ) ) {
		$brand = (string) ( $src['card']['brand'] ?? 'Card' );
		$last  = (string) ( $src['card']['last_digits'] ?? '' );
		return trim( ucfirst( strtolower( $brand ) ) . ( $last !== '' ? ' &bull;&bull;&bull;&bull;' . $last : '' ) );
	}
	if ( isset( $src['venmo'] ) ) return 'Venmo';

	if ( strtolower( $hint ) === 'paylater' ) return 'PayPal Pay Later (Pay in 4)';

	return 'PayPal';
}

/** Whatever the form collected, sanitized. Never priced off — see the signed config. */
function bp_paypal_collect_customer( WP_REST_Request $req, array $fields ): array {
	$out = [];
	foreach ( [ 'name', 'email', 'phone', 'note' ] as $key ) {
		if ( ! in_array( $key, $fields, true ) ) continue;
		$raw = trim( (string) $req->get_param( $key ) );
		if ( $raw === '' ) continue;
		$out[ $key ] = $key === 'email' ? sanitize_email( $raw ) : sanitize_text_field( $raw );
	}
	return $out;
}

function bp_paypal_redirect_url( array $cfg, string $capture_id ): string {
	$path = (string) ( $cfg['redirect'] ?? '' );
	if ( $path === '' ) $path = bp_paypal_default_redirect();
	$url  = ( strpos( $path, 'http' ) === 0 ) ? $path : home_url( $path );
	return add_query_arg( 'payment', $capture_id !== '' ? $capture_id : 'ok', $url );
}


/*--------------------------------------------------------------
# The payment record
#
# A CPT rather than a table: there are only ever a handful of these, WordPress
# already gives them a searchable, sortable, date-filtered admin screen for
# free, and a client asking "did that payment come through?" can be handed a
# link instead of a database query.
#
# create_posts is denied because these are records, not content — the Add New
# button on a list of real money taken would be nothing but a way to make a
# payment that never happened.
--------------------------------------------------------------*/

add_action( 'init', function () {
	register_post_type( 'bp_payment', [
		'labels' => [
			'name'          => __( 'Payments', 'battleplan' ),
			'singular_name' => __( 'Payment', 'battleplan' ),
			'menu_name'     => __( 'Payments', 'battleplan' ),
		],
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_icon'           => 'dashicons-money-alt',
		'menu_position'       => 26,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'supports'            => [ 'title' ],
		'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
		'map_meta_cap'        => true,
	] );
}, 0 );

/** Has this PayPal order already been written down? Guards the double capture. */
function bp_paypal_find_payment( string $order_id ): int {
	$found = get_posts( [
		'post_type'      => 'bp_payment',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bp_pp_order',
		'meta_value'     => $order_id,
	] );
	return $found ? (int) $found[0] : 0;
}

function bp_paypal_record_payment( array $d ): int {
	$who = $d['customer']['name'] ?? ( $d['payer_name'] !== '' ? $d['payer_name'] : 'PayPal customer' );

	$post_id = wp_insert_post( [
		'post_type'   => 'bp_payment',
		'post_status' => 'publish',
		'post_title'  => sprintf( '$%s — %s', number_format( (float) $d['amount'], 2 ), $who ),
		'post_date'   => current_time( 'mysql' ),
	], true );

	if ( is_wp_error( $post_id ) ) {
		// The money has already moved. A failed record must never look to the
		// customer like a failed payment, so this is logged and swallowed rather
		// than thrown — the PayPal dashboard is still the source of truth.
		error_log( 'bp_paypal could not record payment ' . $d['order_id'] . ': ' . $post_id->get_error_message() );
		return 0;
	}

	update_post_meta( $post_id, '_bp_pp_order',    $d['order_id'] );
	update_post_meta( $post_id, '_bp_pp_capture',  $d['capture_id'] );
	update_post_meta( $post_id, '_bp_pp_status',   $d['status'] );
	update_post_meta( $post_id, '_bp_pp_amount',   $d['amount'] );
	update_post_meta( $post_id, '_bp_pp_currency', $d['currency'] );
	update_post_meta( $post_id, '_bp_pp_fee',      $d['fee'] );
	update_post_meta( $post_id, '_bp_pp_net',      $d['net'] );
	update_post_meta( $post_id, '_bp_pp_method',   $d['method'] );
	update_post_meta( $post_id, '_bp_pp_item',     $d['item'] );
	update_post_meta( $post_id, '_bp_pp_payer',    trim( $d['payer_name'] . ' ' . ( $d['payer_email'] !== '' ? '<' . $d['payer_email'] . '>' : '' ) ) );
	update_post_meta( $post_id, '_bp_pp_customer', $d['customer'] );
	update_post_meta( $post_id, '_bp_pp_page',     $d['page'] );

	return (int) $post_id;
}


/*--------------------------------------------------------------
# Receipt & notification email
#
# PayPal emails its own receipt, so ours exists for the thing PayPal's cannot
# carry: what the customer typed into the form. The client's copy is the one
# that matters — it is the only place the payment and the note arrive together.
--------------------------------------------------------------*/

function bp_paypal_send_emails( array $d, int $post_id ): void {
	$ci  = function_exists( 'customer_info' ) ? (array) customer_info() : [];
	$biz = (string) ( $ci['name'] ?? get_bloginfo( 'name' ) );
	$to  = sanitize_email( (string) ( $ci['email'] ?? get_option( 'admin_email' ) ) );

	$headers = [
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $biz . ' <' . ( $to !== '' ? $to : get_option( 'admin_email' ) ) . '>',
	];

	$money = '$' . number_format( (float) $d['amount'], 2 ) . ' ' . $d['currency'];

	$rows = [
		'Amount'         => $money,
		'For'            => $d['item'],
		'Method'         => $d['method'],
		'Status'         => $d['status'] === 'PENDING' ? 'Pending review at PayPal' : 'Paid',
		'PayPal account' => trim( $d['payer_name'] . ' ' . ( $d['payer_email'] !== '' ? '<' . $d['payer_email'] . '>' : '' ) ),
		'Transaction'    => $d['capture_id'],
	];
	foreach ( $d['customer'] as $k => $v ) $rows[ ucfirst( $k ) ] = $v;
	if ( $d['net'] !== '' ) $rows['Net after PayPal fee'] = '$' . number_format( (float) $d['net'], 2 );

	$body = '<p><strong>' . esc_html( $biz ) . '</strong> received an online payment.</p><table cellpadding="6">';
	foreach ( $rows as $label => $value ) {
		if ( (string) $value === '' ) continue;
		$body .= '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( (string) $value ) . '</td></tr>';
	}
	$body .= '</table>';
	if ( $post_id ) {
		$body .= '<p><a href="' . esc_url( admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) . '">View this payment</a></p>';
	}

	if ( $to !== '' ) {
		wp_mail( $to, sprintf( '[%s] Payment received — %s', $biz, $money ), $body, $headers );
	}

	// The customer's copy, only if we actually have somewhere to send it.
	$cust_email = $d['customer']['email'] ?? $d['payer_email'];
	if ( $cust_email !== '' ) {
		$thanks = '<p>Thank you — your payment of <strong>' . esc_html( $money ) . '</strong> to '
			. esc_html( $biz ) . ' went through.</p>';
		if ( $d['status'] === 'PENDING' ) {
			$thanks .= '<p>PayPal is reviewing this payment and will confirm it shortly. Nothing further is needed from you.</p>';
		}
		$thanks .= '<p>Reference: ' . esc_html( $d['capture_id'] ) . '<br>Paid with: ' . esc_html( $d['method'] ) . '</p>';
		if ( ! empty( $ci['phone'] ) ) {
			$thanks .= '<p>Questions? Call us at ' . esc_html( trim( ( $ci['area'] ?? '' ) . '-' . $ci['phone'] ) ) . '.</p>';
		}
		wp_mail( $cust_email, sprintf( 'Your payment to %s', $biz ), $thanks, $headers );
	}
}


/*--------------------------------------------------------------
# Admin list
--------------------------------------------------------------*/

add_filter( 'manage_bp_payment_posts_columns', function ( $cols ) {
	return [
		'cb'        => $cols['cb'] ?? '',
		'title'     => 'Payment',
		'pp_for'    => 'For',
		'pp_method' => 'Method',
		'pp_status' => 'Status',
		'pp_net'    => 'Net',
		'date'      => 'Date',
	];
} );

add_action( 'manage_bp_payment_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'pp_for':    echo esc_html( (string) get_post_meta( $post_id, '_bp_pp_item', true ) ); break;
		case 'pp_method': echo esc_html( (string) get_post_meta( $post_id, '_bp_pp_method', true ) ); break;
		case 'pp_status': echo esc_html( (string) get_post_meta( $post_id, '_bp_pp_status', true ) ); break;
		case 'pp_net':
			$net = (string) get_post_meta( $post_id, '_bp_pp_net', true );
			echo $net !== '' ? '$' . esc_html( number_format( (float) $net, 2 ) ) : '&mdash;';
			break;
	}
}, 10, 2 );

/** The detail the list cannot show: the form fields and the PayPal ids. */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'bp_pp_detail', 'Payment detail', function ( $post ) {
		$rows = [
			'Amount'       => '$' . number_format( (float) get_post_meta( $post->ID, '_bp_pp_amount', true ), 2 ),
			'PayPal fee'   => (string) get_post_meta( $post->ID, '_bp_pp_fee', true ),
			'Net'          => (string) get_post_meta( $post->ID, '_bp_pp_net', true ),
			'Method'       => (string) get_post_meta( $post->ID, '_bp_pp_method', true ),
			'Status'       => (string) get_post_meta( $post->ID, '_bp_pp_status', true ),
			'For'          => (string) get_post_meta( $post->ID, '_bp_pp_item', true ),
			'PayPal payer' => (string) get_post_meta( $post->ID, '_bp_pp_payer', true ),
			'Order ID'     => (string) get_post_meta( $post->ID, '_bp_pp_order', true ),
			'Capture ID'   => (string) get_post_meta( $post->ID, '_bp_pp_capture', true ),
		];
		foreach ( (array) get_post_meta( $post->ID, '_bp_pp_customer', true ) as $k => $v ) {
			$rows[ 'Form: ' . ucfirst( (string) $k ) ] = (string) $v;
		}

		echo '<table class="widefat striped">';
		foreach ( $rows as $label => $value ) {
			if ( $value === '' ) continue;
			echo '<tr><td style="width:180px"><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $value ) . '</td></tr>';
		}
		echo '</table>';
	}, 'bp_payment', 'normal', 'high' );
} );
