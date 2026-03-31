<?php
/* Battle Plan Web Design - CCSO Overtime Scheduling Page */

// Auth guard fallback (template_redirect handles this but guard here too)
if ( ! is_user_logged_in() ) {
	$printPage = '<script>window.location.href="' . esc_js( home_url('/schedules-login/') ) . '";</script>';
	return $printPage;
}

$user_id    = defined('_USER_ID') ? _USER_ID : get_current_user_id();
$user       = get_userdata( $user_id );
$priority   = function_exists('schedules_get_user_priority')   ? schedules_get_user_priority($user_id)   : '';
$user_shift = function_exists('schedules_get_user_shift')      ? schedules_get_user_shift($user_id)      : '';
$badge      = function_exists('schedules_get_user_badge')      ? schedules_get_user_badge($user_id)      : ($user ? $user->user_login : '');
$max_days   = function_exists('schedules_get_max_days')        ? schedules_get_max_days($priority)       : 0;
$max_week   = (int) ceil( $max_days / 7 );
$first_name = $user ? esc_html($user->first_name) : '';
$last_name  = $user ? esc_html($user->last_name)  : '';
$full_name  = trim("{$first_name} {$last_name}");
if ( ! $full_name ) $full_name = esc_html($user ? $user->display_name : 'Member');

$shift_type = '';
if ( function_exists('schedules_shift_type_label') ) {
	$shift_type = schedules_shift_type_label( $user_shift );
}

// Get calendar data
$calendar_data = [];
if ( function_exists('schedules_get_calendar_data') ) {
	$calendar_data = schedules_get_calendar_data( $user_id );
}
$weeks    = isset($calendar_data['weeks'])    ? $calendar_data['weeks']    : [];
$max_week = isset($calendar_data['max_week']) ? $calendar_data['max_week'] : $max_week;

// Get user's upcoming claims
global $wpdb;
$my_claims = [];
if ( function_exists('schedules_table') ) {
	$today      = date('Y-m-d');
	$end_date   = date('Y-m-d', strtotime("+{$max_days} days"));
	$my_claims  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.claimed_at, b.start_hour, b.end_hour, b.block_index,
			        d.schedule_date, d.shift_letter
			 FROM " . schedules_table('claims') . " c
			 JOIN " . schedules_table('blocks') . " b ON b.id = c.block_id
			 JOIN " . schedules_table('days')   . " d ON d.id = b.day_id
			 WHERE c.user_id = %d
			   AND d.schedule_date BETWEEN %s AND %s
			   AND d.is_archived = 0
			 ORDER BY d.schedule_date ASC, b.start_hour ASC",
			$user_id,
			$today,
			$end_date
		),
		ARRAY_A
	);
}

// Day-of-week labels
$dow_labels = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

$printPage  = '';
$printPage .= '<div id="schedules-ot-app">';

// ---- App Header ----
$printPage .= '<header class="schedules-app-header">';
$printPage .=   '<div class="schedules-app-logo">';
$printPage .=     '<img src="/wp-content/uploads/logo.webp">';
$printPage .=   '</div>';

$printPage .=   '<div class="schedules-app-brand">';
$printPage .=     '<span class="schedules-app-title">CCSO Overtime</span>';
$printPage .=     '<span class="schedules-app-subtitle">Member Dashboard</span>';
$printPage .=   '</div>';

$_notif_count = function_exists('schedules_unread_count') ? schedules_unread_count( $user_id ) : 0;
$printPage .=   '<div class="schedules-user-info">';
$printPage .=     '<button class="notif-bell" id="notif-bell" type="button" aria-label="Notifications">';
$printPage .=       '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
if ( $_notif_count > 0 ) {
	$printPage .=   '<span class="notif-count">' . $_notif_count . '</span>';
}
$printPage .=     '</button>';
$printPage .=     '<span class="user-name">' . esc_html($full_name) . '</span>';
$printPage .=     '<span class="user-badge">Badge #' . esc_html($badge) . '</span>';
if ( $user_shift ) {
	$printPage .= '<span class="user-shift-badge shift-' . esc_attr(strtolower($user_shift)) . '">Shift ' . esc_html($user_shift) . '</span>';
}
if ( $priority ) {
	$printPage .= '<span class="user-priority priority-' . esc_attr($priority) . '">OT</span>';
}
$printPage .=   '</div>';

$printPage .=   '<div class="schedules-app-actions">';
$printPage .=     '<button id="schedules-settings-btn" class="schedules-btn schedules-btn-secondary" type="button">Settings</button>';
$printPage .=     '<button id="schedules-change-pw-btn" class="schedules-btn schedules-btn-secondary" type="button">Change Password</button>';
$printPage .=     '<button id="schedules-logout-btn" class="schedules-btn schedules-btn-secondary" type="button">Sign Out</button>';
$printPage .=   '</div>';
$printPage .= '</header>';

// ---- Notifications Panel ----
$printPage .= '<div id="notif-panel" class="notif-panel" hidden>';
$printPage .=   '<div class="notif-panel-header">';
$printPage .=     '<h3>Notifications</h3>';
$printPage .=     '<button type="button" class="notif-panel-close" aria-label="Close">&times;</button>';
$printPage .=   '</div>';
$printPage .=   '<div class="notif-panel-body" id="notif-panel-body">';
$printPage .=     '<p class="notif-loading">Loading&hellip;</p>';
$printPage .=   '</div>';
$printPage .= '</div>';

// ---- Settings Panel ----
$_ot_name_format = get_user_meta( $user_id, 'schedules_name_format', true ) ?: 'first_last';
$printPage .= '<div id="schedules-settings-panel" class="schedules-panel" hidden>';
$printPage .=   '<div class="schedules-panel-inner">';
$printPage .=     '<h2>Display Preferences</h2>';
$printPage .=     '<form id="user-settings-form" novalidate>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="user-name-format">Member Name Format</label>';
$printPage .=         '<select id="user-name-format" name="name_format">';
$printPage .=           '<option value="first_last"' . selected( $_ot_name_format, 'first_last', false ) . '>First Last</option>';
$printPage .=           '<option value="last_first"' . selected( $_ot_name_format, 'last_first', false ) . '>Last, First</option>';
$printPage .=         '</select>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-error" id="user-settings-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="form-success" id="user-settings-success" role="status" aria-live="polite"></div>';
$printPage .=       '<div class="panel-actions">';
$printPage .=         '<button type="submit" class="schedules-btn schedules-btn-primary" id="user-settings-submit">Save Preferences</button>';
$printPage .=         '<button type="button" id="schedules-settings-cancel" class="schedules-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>';

// ---- Change Password Panel ----
$printPage .= '<div id="schedules-change-pw-panel" class="schedules-panel" hidden>';
$printPage .=   '<div class="schedules-panel-inner">';
$printPage .=     '<h2>Change Password</h2>';
$printPage .=     '<form id="schedules-change-pw-form" novalidate>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="current-password">Current Password</label>';
$printPage .=         '<input type="password" id="current-password" name="current_password" autocomplete="current-password" required>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="new-password">New Password</label>';
$printPage .=         '<input type="password" id="new-password" name="new_password" autocomplete="new-password" required minlength="8">';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="confirm-password">Confirm New Password</label>';
$printPage .=         '<input type="password" id="confirm-password" name="confirm_password" autocomplete="new-password" required minlength="8">';
$printPage .=       '</div>';
$printPage .=       '<div class="form-error" id="change-pw-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="form-success" id="change-pw-success" role="status" aria-live="polite"></div>';
$printPage .=       '<div class="panel-actions">';
$printPage .=         '<button type="submit" class="schedules-btn schedules-btn-primary">Update Password</button>';
$printPage .=         '<button type="button" id="schedules-change-pw-cancel" class="schedules-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>';

// ---- Month Filter ----
if ( $max_week > 0 ) {
	$printPage .= '<div class="schedules-month-nav">';
	$printPage .= '<select id="schedules-month-filter" class="schedules-month-select" aria-label="Select month">';
	for ( $m = 0; $m < 12; $m++ ) {
		$ts    = strtotime( "+{$m} months", strtotime( date('Y-m-01') ) );
		$val   = date( 'Y-m', $ts );
		$label = date( 'F Y', $ts );
		$sel   = $m === 0 ? ' selected' : '';
		$printPage .= '<option value="' . $val . '"' . $sel . '>' . $label . '</option>';
	}
	$printPage .= '</select>';
	$printPage .= '</div>';
}

// ---- Calendar Weeks ----
$printPage .= '<div class="schedules-calendar">';

if ( empty($weeks) ) {
	$printPage .= '<div class="schedules-empty">';
	$printPage .=   '<p>No available overtime slots found for your priority window.</p>';
	$printPage .= '</div>';
} else {
	foreach ( $weeks as $week_num => $week_days ) {
		$hidden = '';
		$printPage .= '<div class="schedules-week" id="week-panel-' . $week_num . '" data-week="' . $week_num . '" role="tabpanel"' . $hidden . '>';

		ksort($week_days);

		foreach ( $week_days as $date => $shifts_on_day ) {
			$ts       = strtotime($date);
			$dow      = (int) date('w', $ts);
			$dow_name = $dow_labels[$dow];
			$date_fmt = date('M j', $ts);

			$month     = date( 'Y-m', $ts );
		$printPage .= '<div class="schedule-day" data-date="' . esc_attr($date) . '" data-month="' . esc_attr($month) . '">';
			$printPage .=   '<div class="day-header">';
			$printPage .=     '<span class="day-name">' . esc_html($dow_name) . '</span>';
			$printPage .=     '<span class="day-date">' . esc_html($date_fmt) . '</span>';
			$printPage .=   '</div>';

			foreach ( $shifts_on_day as $shift_data ) {
				$shift_letter = esc_attr($shift_data['shift_letter']);
				$shift_type_l = function_exists('schedules_shift_type_label') ? schedules_shift_type_label($shift_data['shift_letter']) : '';
				$day_id       = (int) $shift_data['id'];

				$printPage .= '<div class="shift-group shift-' . strtolower($shift_letter) . '" data-shift="' . $shift_letter . '" data-day-id="' . $day_id . '">';
				$printPage .=   '<div class="shift-label"><span class="shift-pill shift-' . strtolower($shift_letter) . '">Shift ' . esc_html($shift_data['shift_letter']) . '</span><span class="shift-type-label">' . esc_html($shift_type_l) . '</span></div>';
				$printPage .=   '<div class="shift-blocks">';

				$_grace_minutes = (int) apply_filters( 'schedules_unclaim_grace_minutes', 5 );

				foreach ( $shift_data['blocks'] as $block ) {
					$block_id    = (int) $block['id'];
					$available   = (int) $block['available'];
					$user_claimed = (bool) $block['user_claimed'];
					$time_str    = function_exists('schedules_format_block_time')
						? schedules_format_block_time($block['start_hour'], $block['end_hour'])
						: $block['start_hour'] . '–' . $block['end_hour'];

					// Check if within undo grace period
					$_undo_remaining = 0;
					if ( $user_claimed && ! empty($block['user_claimed_at']) ) {
						$_claimed_ts     = strtotime( $block['user_claimed_at'] );
						$_deadline       = $_claimed_ts + ( $_grace_minutes * 60 );
						$_undo_remaining = max( 0, $_deadline - current_time('timestamp') );
					}

					if ( $user_claimed ) {
						$state_class  = 'claimed';
						if ( $_undo_remaining > 0 ) $state_class .= ' claim-undoable';
						$status_label = 'Claimed';
					} elseif ( $available <= 0 ) {
						$state_class  = 'full';
						$status_label = 'Full';
					} else {
						$state_class  = 'available';
						if ( $available <= 2 ) $state_class .= ' limited';
						$status_label = $available . ' open';
					}

					$printPage .= '<div class="time-block ' . $state_class . '" data-block-id="' . $block_id . '" data-available="' . $available . '" data-time="' . esc_attr($time_str) . '" data-shift="' . $shift_letter . '" data-date="' . esc_attr($date) . '">';
					$printPage .=   '<span class="time-range">' . esc_html($time_str) . '</span>';
					$printPage .=   '<span class="block-status">' . esc_html($status_label) . '</span>';
					if ( $_undo_remaining > 0 ) {
						$printPage .= '<button class="claim-undo-btn" type="button" data-block-id="' . $block_id . '" data-remaining="' . $_undo_remaining . '" title="Undo claim">&times;</button>';
					}
					$printPage .= '</div>';
				}

				$printPage .=   '</div>'; // .shift-blocks
				$printPage .= '</div>'; // .shift-group
			}

			$printPage .= '</div>'; // .schedule-day
		}

		$printPage .= '</div>'; // .schedules-week
	}
}

$printPage .= '</div>'; // .schedules-calendar

// ---- My Claims Summary ----
$printPage .= '<section class="schedules-my-claims" id="schedules-my-claims">';
$printPage .=   '<h2>My Upcoming OT</h2>';

if ( empty($my_claims) ) {
	$printPage .= '<p class="claims-empty">You have no upcoming OT shifts claimed.</p>';
} else {
	$printPage .= '<ul class="my-claims-list">';
	foreach ( $my_claims as $claim ) {
		$claim_date  = date( 'l, M j', strtotime($claim['schedule_date']) );
		$time_str    = function_exists('schedules_format_block_time')
			? schedules_format_block_time( (int)$claim['start_hour'], (int)$claim['end_hour'] )
			: $claim['start_hour'] . '–' . $claim['end_hour'];
		$shift_type_c = function_exists('schedules_shift_type_label') ? schedules_shift_type_label($claim['shift_letter']) : '';

		$printPage .= '<li class="my-claim-item">';
		$printPage .=   '<span class="claim-shift shift-' . strtolower(esc_attr($claim['shift_letter'])) . '">Shift ' . esc_html($claim['shift_letter']) . '</span>';
		$printPage .=   '<span class="claim-date">' . esc_html($claim_date) . '</span>';
		$printPage .=   '<span class="claim-time">' . esc_html($time_str) . '</span>';
		$printPage .=   '<span class="claim-type">' . esc_html($shift_type_c) . '</span>';
		$printPage .= '</li>';
	}
	$printPage .= '</ul>';
}

$printPage .= '</section>';

// ---- Seating View (read-only) ----
if ( $user_shift ) :
$printPage .= '<section class="schedules-duty-section" id="schedules-duty-section">';
$printPage .=   '<div class="duty-section-header">';
$printPage .=     '<h2>Duty Assignments</h2>';
$printPage .=     '<div class="duty-date-nav">';
$printPage .=       '<input type="date" id="member-duty-date" value="' . esc_attr(date('Y-m-d')) . '">';
$printPage .=     '</div>';
$printPage .=   '</div>';
$printPage .=   '<div id="member-duty-content"><p class="duty-loading">Loading\u2026</p></div>';
$printPage .= '</section>';
endif;

// ---- Block confirmation popup (hidden, cloned by JS) ----
$printPage .= '<div id="block-confirm-popup" class="block-confirm-popup" hidden role="dialog" aria-modal="true" aria-label="Confirm claim">';
$printPage .=   '<div class="popup-message"></div>';
$printPage .=   '<div class="popup-note"></div>';
$printPage .=   '<div class="popup-actions">';
$printPage .=     '<button class="schedules-btn schedules-btn-primary popup-confirm" type="button">Confirm</button>';
$printPage .=     '<button class="schedules-btn popup-cancel" type="button">Cancel</button>';
$printPage .=   '</div>';
$printPage .= '</div>';

$printPage .= '</div>'; // #schedules-ot-app

return do_shortcode( $printPage );
