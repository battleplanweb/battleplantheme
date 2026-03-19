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

$printPage .=   '<div class="schedules-user-info">';
$printPage .=     '<span class="user-name">' . esc_html($full_name) . '</span>';
$printPage .=     '<span class="user-badge">Badge #' . esc_html($badge) . '</span>';
if ( $user_shift ) {
	$printPage .= '<span class="user-shift-badge shift-' . esc_attr(strtolower($user_shift)) . '">Shift ' . esc_html($user_shift) . '</span>';
}
if ( $priority ) {
	$printPage .= '<span class="user-priority priority-' . esc_attr($priority) . '">' . esc_html(ucfirst($priority)) . '</span>';
}
$printPage .=   '</div>';

$printPage .=   '<div class="schedules-app-actions">';
$printPage .=     '<button id="schedules-change-pw-btn" class="schedules-btn schedules-btn-ghost" type="button">Change Password</button>';
$printPage .=     '<button id="schedules-logout-btn" class="schedules-btn schedules-btn-ghost" type="button">Sign Out</button>';
$printPage .=   '</div>';
$printPage .= '</header>';

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
$printPage .=         '<button type="button" id="schedules-change-pw-cancel" class="schedules-btn schedules-btn-ghost">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>';

// ---- Week Tabs ----
if ( $max_week > 0 ) {
	$printPage .= '<div class="schedules-week-tabs" role="tablist" aria-label="Schedule weeks">';
	for ( $w = 1; $w <= $max_week; $w++ ) {
		$active    = $w === 1 ? ' active' : '';
		$selected  = $w === 1 ? 'true' : 'false';
		$start_day = ( ($w - 1) * 7 ) + 1;
		$end_day   = min( $w * 7, $max_days );
		$printPage .= '<button class="week-tab' . $active . '" data-week="' . $w . '" role="tab" aria-selected="' . $selected . '" aria-controls="week-panel-' . $w . '">';
		$printPage .=   'Week ' . $w . ' <span class="week-range unique">Days ' . $start_day . '–' . $end_day . '</span>';
		$printPage .= '</button>';
	}
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
		$hidden = $week_num === 1 ? '' : ' hidden';
		$printPage .= '<div class="schedules-week" id="week-panel-' . $week_num . '" data-week="' . $week_num . '" role="tabpanel"' . $hidden . '>';

		ksort($week_days);

		foreach ( $week_days as $date => $shifts_on_day ) {
			$ts       = strtotime($date);
			$dow      = (int) date('w', $ts);
			$dow_name = $dow_labels[$dow];
			$date_fmt = date('M j', $ts);

			$printPage .= '<div class="schedule-day" data-date="' . esc_attr($date) . '">';
			$printPage .=   '<div class="day-header">';
			$printPage .=     '<span class="day-name">' . esc_html($dow_name) . '</span>';
			$printPage .=     '<span class="day-date">' . esc_html($date_fmt) . '</span>';
			$printPage .=   '</div>';

			foreach ( $shifts_on_day as $shift_data ) {
				$shift_letter = esc_attr($shift_data['shift_letter']);
				$shift_type_l = function_exists('schedules_shift_type_label') ? schedules_shift_type_label($shift_data['shift_letter']) : '';
				$day_id       = (int) $shift_data['id'];

				$printPage .= '<div class="shift-group shift-' . strtolower($shift_letter) . '" data-shift="' . $shift_letter . '" data-day-id="' . $day_id . '">';
				$printPage .=   '<div class="shift-label">Shift ' . esc_html($shift_data['shift_letter']) . ' &middot; ' . esc_html($shift_type_l) . '</div>';
				$printPage .=   '<div class="shift-blocks">';

				foreach ( $shift_data['blocks'] as $block ) {
					$block_id    = (int) $block['id'];
					$available   = (int) $block['available'];
					$user_claimed = (bool) $block['user_claimed'];
					$time_str    = function_exists('schedules_format_block_time')
						? schedules_format_block_time($block['start_hour'], $block['end_hour'])
						: $block['start_hour'] . '–' . $block['end_hour'];

					if ( $user_claimed ) {
						$state_class  = 'claimed';
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

// ---- Block confirmation popup (hidden, cloned by JS) ----
$printPage .= '<div id="block-confirm-popup" class="block-confirm-popup" hidden role="dialog" aria-modal="true" aria-label="Confirm claim">';
$printPage .=   '<p class="popup-message"></p>';
$printPage .=   '<div class="popup-actions">';
$printPage .=     '<button class="schedules-btn schedules-btn-primary popup-confirm" type="button">Confirm</button>';
$printPage .=     '<button class="schedules-btn schedules-btn-ghost popup-cancel" type="button">Cancel</button>';
$printPage .=   '</div>';
$printPage .= '</div>';

$printPage .= '</div>'; // #schedules-ot-app

return do_shortcode( $printPage );
