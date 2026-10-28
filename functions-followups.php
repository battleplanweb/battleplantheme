<?php
/* Battle Plan Web Design Functions: Follow-up Reminders
 *
 * "Email me about this later" notes, set from the Analytics page (battleplanweb only). On its due date
 * each note is emailed to Glendon (emailMe) as "{Business} follow-up", with a before/after snapshot of
 * the site's numbers — so a change (a new landing page, a rewrite…) gets checked instead of forgotten.
 *
 * Store: bp_followups = [id => ['note','url','created'=>ts,'due'=>ts]]. A sent note is removed.
 * Sending rides bot hits + admin page loads (never a real visitor's request, same rule as the chrons),
 * gated by the autoloaded bp_followups_next timestamp so the usual request is one in-memory compare.
 */

function bp_followups_get(): array {
	$f = get_option( 'bp_followups' );
	return is_array( $f ) ? $f : [];
}

function bp_followups_save( array $list ): void {
	update_option( 'bp_followups', $list, false );
	update_option( 'bp_followups_next', $list ? (int) min( array_column( $list, 'due' ) ) : 0, true );
}

// Due-date timestamp: 7am site time, N days out or on a picked Y-m-d date.
function bp_followup_due( int $days, string $date = '' ): int {
	$tz = wp_timezone();
	if ( $date !== '' ) {
		$d = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' 07:00', $tz );
		if ( $d ) return $d->getTimestamp();
	}
	return ( new DateTime( 'today 07:00', $tz ) )->modify( '+' . max( 1, $days ) . ' days' )->getTimestamp();
}

add_action( 'init', 'bp_followups_maybe_send', 99 );
function bp_followups_maybe_send(): void {
	$next = (int) get_option( 'bp_followups_next', 0 );
	if ( ! $next || time() < $next ) return;
	if ( ! is_admin() && ! ( defined( '_IS_BOT' ) && _IS_BOT ) ) return;
	if ( get_transient( 'bp_followups_lock' ) ) return;
	set_transient( 'bp_followups_lock', 1, 60 );

	$list = bp_followups_get();
	$now  = time();
	foreach ( $list as $id => $f ) {
		if ( (int) ( $f['due'] ?? 0 ) > $now ) continue;
		if ( bp_followup_send( $f ) ) unset( $list[ $id ] );
		else $list[ $id ]['due'] = $now + DAY_IN_SECONDS;   // mail failed — try again tomorrow
	}
	bp_followups_save( $list );
	delete_transient( 'bp_followups_lock' );
}

/**
 * Before/after numbers around the note's creation date. "After" = every day of data since the note;
 * "before" = the same number of days just before it, so the windows are equal even with GSC's ~3-day lag.
 * Returns rows [label, before, after, fmt] (fmt: int|pos) plus the window length in days.
 */
function bp_followup_stats( int $created ): array {
	$c    = wp_date( 'Ymd', $created );
	$rows = []; $days = 0;

	$split = function ( $hist ) use ( $c ) {
		if ( ! is_array( $hist ) || ! $hist ) return null;
		ksort( $hist );
		$before = []; $after = [];
		foreach ( $hist as $ymd => $rec ) {
			if ( strlen( (string) $ymd ) !== 8 || ! is_array( $rec ) ) continue;
			if ( (string) $ymd >= $c ) $after[] = $rec; else $before[] = $rec;
		}
		$n = count( $after );
		if ( ! $n || count( $before ) < $n ) return null;   // not enough history on one side to compare fairly
		return [ array_slice( $before, -$n ), $after, $n ];
	};

	// Website sessions (GA4, daily by channel).
	if ( $w = $split( get_option( 'bp_ga4_channel_history_daily' ) ) ) {
		$work = function ( $recs ) { $t = 0; foreach ( $recs as $r ) $t += (int) ( $r['Organic Search']['sessions'] ?? 0 ) + (int) ( $r['GBP']['sessions'] ?? 0 ); return $t; };
		$all  = function ( $recs ) { $t = 0; foreach ( $recs as $r ) foreach ( $r as $ch ) $t += (int) ( $ch['sessions'] ?? 0 ); return $t; };
		$rows[] = [ 'Your Work sessions (Organic + GBP)', $work( $w[0] ), $work( $w[1] ), 'int' ];
		$rows[] = [ 'All website sessions',               $all( $w[0] ),  $all( $w[1] ),  'int' ];
		$days   = max( $days, $w[2] );
	}

	// Google Search (Search Console, daily totals).
	if ( $w = $split( get_option( 'bp_gsc_totals_history' ) ) ) {
		$sum = function ( $recs, $k ) { $t = 0; foreach ( $recs as $r ) $t += (float) ( $r[ $k ] ?? 0 ); return $t; };
		$pos = function ( $recs ) { $pi = 0; $im = 0; foreach ( $recs as $r ) { $i = (int) ( $r['impressions'] ?? 0 ); $pi += (float) ( $r['position'] ?? 0 ) * $i; $im += $i; } return $im ? round( $pi / $im, 1 ) : 0; };
		$rows[] = [ 'Google clicks',        $sum( $w[0], 'clicks' ),      $sum( $w[1], 'clicks' ),      'int' ];
		$rows[] = [ 'Google impressions',   $sum( $w[0], 'impressions' ), $sum( $w[1], 'impressions' ), 'int' ];
		$rows[] = [ 'Avg Google position',  $pos( $w[0] ),                $pos( $w[1] ),                'pos' ];
		$days   = max( $days, $w[2] );
	}

	// Pages indexed — last weekly snapshot on/before the note vs the latest one.
	$cov = get_option( 'bp_gsc_coverage_history' );
	if ( is_array( $cov ) && $cov ) {
		ksort( $cov );
		$then = null;
		foreach ( $cov as $ymd => $rec ) if ( (string) $ymd <= $c && is_array( $rec ) ) $then = $rec;
		$now = end( $cov );
		if ( $then && is_array( $now ) ) $rows[] = [ 'Pages indexed', (int) ( $then['indexed'] ?? 0 ), (int) ( $now['indexed'] ?? 0 ), 'int' ];
	}

	return [ 'rows' => $rows, 'days' => $days ];
}

function bp_followup_send( array $f ): bool {
	$site    = bp_business_name();
	$note    = (string) ( $f['note'] ?? '' );
	$created = (int) ( $f['created'] ?? time() );
	$stats   = bp_followup_stats( $created );
	$subject = $site . ' follow-up — ' . wp_html_excerpt( $note, 60, '…' );

	$cell = 'padding:6px 12px;border-bottom:1px solid #e5e5e5;';
	$html  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;max-width:640px;">';
	$html .= '<p style="font-size:16px;margin:0 0 6px;"><b>' . esc_html( $note ) . '</b></p>';
	$html .= '<p style="color:#666;margin:0 0 16px;">Note set ' . esc_html( wp_date( 'M j, Y', $created ) ) . ' on <a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $site ) . '</a>';
	if ( ! empty( $f['url'] ) ) $html .= ' · <a href="' . esc_url( $f['url'] ) . '">' . esc_html( $f['url'] ) . '</a>';
	$html .= '</p>';

	if ( $stats['rows'] ) {
		$html .= '<p style="margin:0 0 6px;"><b>Since the note</b> — the ' . (int) $stats['days'] . ' days after it vs the ' . (int) $stats['days'] . ' days before:</p>';
		$html .= '<table style="border-collapse:collapse;"><tr><th style="' . $cell . 'text-align:left;"></th><th style="' . $cell . '">Before</th><th style="' . $cell . '">After</th><th style="' . $cell . '">Change</th></tr>';
		foreach ( $stats['rows'] as [ $label, $b, $a, $fmt ] ) {
			$fv = function ( $v ) use ( $fmt ) { return $fmt === 'pos' ? number_format( (float) $v, 1 ) : number_format( (float) $v ); };
			// Position: lower is better, so a drop is good (green).
			$good = $fmt === 'pos' ? $a < $b : $a > $b;
			$chg  = $b > 0 ? round( ( $a - $b ) / $b * 100 ) . '%' : ( $a > 0 ? 'new' : '—' );
			if ( $b > 0 && $chg[0] !== '-' && $chg !== '0%' ) $chg = '+' . $chg;
			$col  = $a == $b ? '#666' : ( $good ? '#1a7f37' : '#c62828' );
			$html .= '<tr><td style="' . $cell . '">' . esc_html( $label ) . '</td><td style="' . $cell . 'text-align:right;">' . $fv( $b ) . '</td><td style="' . $cell . 'text-align:right;">' . $fv( $a ) . '</td><td style="' . $cell . 'text-align:right;color:' . $col . ';">' . esc_html( $chg ) . '</td></tr>';
		}
		$html .= '</table>';
		$html .= '<p style="color:#888;font-size:12px;">Search Console runs ~3 days behind, and the numbers are only as fresh as the last nightly stats run.</p>';
	} else {
		$html .= '<p style="color:#666;">Not enough stats history around the note date to compare — check the dashboard directly.</p>';
	}

	$html .= '<p><a href="' . esc_url( admin_url( 'index.php?page=bp-analytics' ) ) . '" style="display:inline-block;background:#2271b1;color:#fff;padding:8px 16px;border-radius:4px;text-decoration:none;">Open Analytics</a></p>';
	$html .= '</div>';

	return (bool) emailMe( $subject, $html );
}

/*--------------------------------------------------------------
# Admin actions (Analytics page form) — battleplanweb only
--------------------------------------------------------------*/

function bp_followups_can(): bool {
	return defined( '_USER_LOGIN' ) && _USER_LOGIN === 'battleplanweb';
}

add_action( 'admin_post_bp_followup_add', 'bp_followup_add' );
function bp_followup_add(): void {
	if ( ! bp_followups_can() ) wp_die( 'Not allowed.' );
	check_admin_referer( 'bp_followup' );
	$note = sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) );
	if ( $note !== '' ) {
		$date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$list = bp_followups_get();
		$list[ wp_generate_password( 8, false ) ] = [
			'note'    => $note,
			'created' => time(),
			'due'     => bp_followup_due( (int) ( $_POST['days'] ?? 7 ), preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '' ),
		];
		bp_followups_save( $list );
	}
	wp_safe_redirect( admin_url( 'index.php?page=bp-analytics' ) . '#bp-followups' );
	exit;
}

// Delete a pending note, or "send now" (emails it immediately and removes it).
add_action( 'admin_post_bp_followup_act', 'bp_followup_act' );
function bp_followup_act(): void {
	if ( ! bp_followups_can() ) wp_die( 'Not allowed.' );
	check_admin_referer( 'bp_followup' );
	// Not sanitize_key(): it lowercases, and the ids are mixed-case (wp_generate_password), so the lookup missed
	$id   = preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $_GET['id'] ?? '' ) );
	$list = bp_followups_get();
	if ( isset( $list[ $id ] ) ) {
		if ( ( $_GET['do'] ?? '' ) === 'send' ) bp_followup_send( $list[ $id ] );
		unset( $list[ $id ] );
		bp_followups_save( $list );
	}
	wp_safe_redirect( admin_url( 'index.php?page=bp-analytics' ) . '#bp-followups' );
	exit;
}

// The card on the Analytics page: quick-add form + pending list.
function bp_followups_card(): void {
	if ( ! bp_followups_can() ) return;
	$list = bp_followups_get();
	uasort( $list, function ( $a, $b ) { return (int) $a['due'] <=> (int) $b['due']; } );

	echo '<div class="bp-an-card" id="bp-followups">';
	echo   '<h2 class="bp-an-h2">Follow-up reminders</h2>';
	echo   '<div class="bp-an-cardnote">Made a change you want to check on later? Leave a note — it emails glendon@bp-webdev.com as "' . esc_html( bp_business_name() ) . ' follow-up" on the date you pick, with before/after traffic, Google search &amp; indexing numbers.</div>';
	echo   '<form class="bp-fu-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'bp_followup' );
	echo     '<input type="hidden" name="action" value="bp_followup_add">';
	echo     '<input type="text" name="note" class="bp-fu-note" maxlength="300" required placeholder="Added landing page — check back to see progress">';
	echo     '<select name="days">';
	foreach ( [ 7 => 'In 1 week', 14 => 'In 2 weeks', 30 => 'In 1 month', 60 => 'In 2 months', 90 => 'In 3 months', 180 => 'In 6 months' ] as $d => $lbl ) {
		echo '<option value="' . $d . '"' . ( $d === 30 ? ' selected' : '' ) . '>' . esc_html( $lbl ) . '</option>';
	}
	echo     '</select>';
	echo     '<span class="bp-fu-or">or</span><input type="date" name="date" aria-label="Specific date" min="' . esc_attr( wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) ) . '">';
	echo     '<button type="submit" class="button button-primary">Remind me</button>';
	echo   '</form>';

	if ( $list ) {
		$base = wp_nonce_url( admin_url( 'admin-post.php?action=bp_followup_act' ), 'bp_followup' );
		echo '<table class="bp-fu-list">';
		foreach ( $list as $id => $f ) {
			echo '<tr>'
			   .   '<td class="bp-fu-due">' . esc_html( wp_date( 'M j, Y', (int) $f['due'] ) ) . '</td>'
			   .   '<td>' . esc_html( $f['note'] ) . ( ! empty( $f['url'] ) ? ' <a href="' . esc_url( $f['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $f['url'] ) . '</a>' : '' ) . '</td>'
			   .   '<td class="bp-fu-acts"><a href="' . esc_url( add_query_arg( [ 'id' => $id, 'do' => 'send' ], $base ) ) . '">Send now</a> · '
			   .     '<a href="' . esc_url( add_query_arg( [ 'id' => $id, 'do' => 'delete' ], $base ) ) . '" onclick="return confirm(\'Delete this reminder?\')">Delete</a></td>'
			   . '</tr>';
		}
		echo '</table>';
	}
	echo '</div>';
}
