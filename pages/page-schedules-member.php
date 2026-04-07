<?php
/* Battle Plan Web Design - CCSO Schedule Scheduling Page */

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

$_ot_name_format = get_user_meta( $user_id, 'schedules_name_format', true ) ?: 'first_last';

$printPage  = '';
$printPage .= '<div id="schedules-ot-app">';

// ---- App Header ----
$printPage .= '<header class="schedules-app-header schedules-supervisor-header">';
$printPage .=   '<div class="schedules-app-logo">';
$printPage .=     '<img src="/wp-content/uploads/logo.webp">';
$printPage .=   '</div>';

$printPage .=   '<div class="schedules-app-brand">';
$printPage .=     '<span class="schedules-app-title">CCSO Schedule</span>';
if ( current_user_can('manage_schedules') ) {
    $printPage .= '<a class="schedules-app-subtitle schedules-view-toggle" href="' . esc_url( home_url('/schedules-supervisor/') ) . '" title="Switch to Supervisor Dashboard">Member Dashboard <span class="view-toggle-hint">&#8592; Supervisor View</span></a>';
} else {
    $printPage .= '<span class="schedules-app-subtitle">Member Dashboard</span>';
}
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
$printPage .=   '<button id="schedules-logout-btn" class="schedules-btn schedules-btn-secondary" type="button">Sign Out</button>';
$printPage .= '</header>';

// ---- Notifications Panel ----
$printPage .= '<div id="notif-panel" class="notif-panel" hidden>';
$printPage .=   '<div class="notif-panel-header">';
$printPage .=     '<h3>Notifications</h3>';
$printPage .=     '<button type="button" class="basic-btn notif-panel-close" aria-label="Close">&times;</button>';
$printPage .=   '</div>';
$printPage .=   '<div class="notif-panel-toolbar">';
$printPage .=     '<button type="button" id="notif-archive-all" class="notif-toolbar-btn">Archive All</button>';
$printPage .=     '<button type="button" id="notif-show-archived" class="notif-toolbar-btn">Show Archived</button>';
$printPage .=   '</div>';
$printPage .=   '<div class="notif-panel-body" id="notif-panel-body">';
$printPage .=     '<p class="notif-loading">Loading&hellip;</p>';
$printPage .=   '</div>';
$printPage .= '</div>';

// ---- Member Nav ----
$printPage .= '<nav class="schedules-supervisor-nav" role="tablist" aria-label="Member views">';
$printPage .=   '<button class="sup-tab sup-group-tab active" data-group="schedule" aria-expanded="true">Schedule</button>';
$printPage .=   '<button class="sup-tab" data-view="duty"     role="tab" aria-selected="false" aria-controls="sup-view-duty">Duty Assignments</button>';
$printPage .=   '<button class="sup-tab" data-view="settings" role="tab" aria-selected="false" aria-controls="sup-view-settings">Settings</button>';
$printPage .= '</nav>';
$printPage .= '<div class="schedules-supervisor-nav-sub" id="sup-sub-schedule">';
$printPage .=   '<button class="sup-sub-tab" data-view="schedule">My Schedule</button>';
$printPage .=   '<button class="sup-sub-tab" data-view="pdo">PDO Calendar</button>';
$printPage .=   '<button class="sup-sub-tab active" data-view="calendar">Claim OT</button>';
$printPage .=   '<button class="sup-sub-tab" data-view="coverage">Coverage</button>';
$printPage .= '</div>';

// ---- VIEW: PDO Calendar ----
$_pdo_current = date('Y-m');
$_pdo_shifts  = function_exists('schedules_table') ? $wpdb->get_results(
    "SELECT shift_letter FROM " . schedules_table('shifts') . " ORDER BY shift_letter ASC", ARRAY_A
) : [];
$printPage .= '<div class="sup-view" id="sup-view-pdo" data-view="pdo" role="tabpanel" hidden>';
$printPage .=   '<div class="schedule-toolbar toolbar">';
$printPage .=     '<div class="schedule-month-nav">';
$printPage .=       '<select id="pdo-month-picker">';
for ( $_pm = 0; $_pm < 12; $_pm++ ) {
    $_pm_ts  = strtotime( "+{$_pm} months", strtotime( date('Y-m-01') ) );
    $_pm_val = date( 'Y-m', $_pm_ts );
    $_pm_sel = ( $_pm_val === $_pdo_current ) ? ' selected' : '';
    $printPage .= '<option value="' . $_pm_val . '"' . $_pm_sel . '>' . date( 'F Y', $_pm_ts ) . '</option>';
}
$printPage .=       '</select>';
$printPage .=     '</div>';
$printPage .=     '<div>';
$printPage .=       '<select id="pdo-shift-picker" aria-label="Select shift">';
foreach ( $_pdo_shifts as $_ps ) {
    $_selected = ( $_ps['shift_letter'] === $user_shift ) ? ' selected' : '';
    $printPage .= '<option value="' . esc_attr($_ps['shift_letter']) . '"' . $_selected . '>Shift ' . esc_html($_ps['shift_letter']) . '</option>';
}
$printPage .=       '</select>';
$printPage .=     '</div>';
$printPage .=   '</div>';
$printPage .=   '<div id="pdo-calendar-wrap"></div>';
$printPage .= '</div>';

// ---- VIEW: Schedule (personal calendar) ----
$_sch_current = date('Y-m');
$printPage .= '<div class="sup-view" id="sup-view-schedule" data-view="schedule" role="tabpanel" hidden>';
$printPage .=   '<div class="schedule-toolbar toolbar">';
$printPage .=     '<div class="schedule-month-nav">';
$printPage .=       '<select id="schedule-month-picker">';
for ( $_sm = 0; $_sm < 12; $_sm++ ) {
    $_sm_ts  = strtotime( "+{$_sm} months", strtotime( date('Y-m-01') ) );
    $_sm_val = date( 'Y-m', $_sm_ts );
    $_sm_sel = ( $_sm_val === $_sch_current ) ? ' selected' : '';
    $printPage .= '<option value="' . $_sm_val . '"' . $_sm_sel . '>' . date( 'F Y', $_sm_ts ) . '</option>';
}
$printPage .=       '</select>';
$printPage .=     '</div>';
$printPage .=   '</div>';
$printPage .=   '<div id="schedule-calendar-wrap"></div>';
$printPage .= '</div>';

// ---- VIEW: Claim OT ----
$printPage .= '<div class="sup-view active" id="sup-view-calendar" data-view="calendar" role="tabpanel">';

// Week tabs
if ( ! empty($weeks) ) {
	$printPage .= '<div class="schedules-week-tabs toolbar" role="tablist" aria-label="Select week">';
	foreach ( $weeks as $week_num => $week_days ) {
		$_tab_dates = array_keys($week_days);
		sort($_tab_dates);
		$_tab_first  = date( 'M j', strtotime( reset($_tab_dates) ) );
		$_tab_last   = date( 'M j', strtotime( end($_tab_dates) ) );
		$_tab_active = $week_num === array_key_first($weeks);
		$printPage .= '<button class="week-tab' . ( $_tab_active ? ' active' : '' ) . '" role="tab" aria-selected="' . ( $_tab_active ? 'true' : 'false' ) . '" data-week="' . $week_num . '">';
		$printPage .= 'Week ' . $week_num . '<br><span>' . $_tab_first . ' &ndash; ' . $_tab_last . '</span>';
		$printPage .= '</button>';
	}
	$printPage .= '<button id="ot-view-toggle" class="schedules-btn schedules-btn-sm" type="button">Column View</button>';
	$printPage .= '</div>';
}


// OT Calendar weeks
$printPage .= '<div class="schedules-calendar">';

if ( empty($weeks) ) {
	$printPage .= '<div class="schedules-empty">';
	$printPage .=   '<p>No available overtime slots found for your priority window.</p>';
	$printPage .= '</div>';
} else {
	foreach ( $weeks as $week_num => $week_days ) {
		$hidden = $week_num === array_key_first($weeks) ? '' : ' hidden';
		$printPage .= '<div class="schedules-week" id="week-panel-' . $week_num . '" data-week="' . $week_num . '" role="tabpanel"' . $hidden . '>';

		ksort($week_days);

		foreach ( $week_days as $date => $shifts_on_day ) {
			$ts       = strtotime($date);
			$dow      = (int) date('w', $ts);
			$dow_name = $dow_labels[$dow];
			$date_fmt = date('M j', $ts);

			$month = date( 'Y-m', $ts );
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
					$block_id     = (int) $block['id'];
					$available    = (int) $block['available'];
					$user_claimed = (bool) $block['user_claimed'];
					$time_str     = function_exists('schedules_format_block_time')
						? schedules_format_block_time($block['start_hour'], $block['end_hour'])
						: $block['start_hour'] . '–' . $block['end_hour'];

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
						$printPage .= '<button class="basic-btn claim-undo-btn" type="button" data-block-id="' . $block_id . '" data-remaining="' . $_undo_remaining . '" title="Undo claim">&times;</button>';
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

// My Claims Summary
$printPage .= '<section class="schedules-my-claims" id="schedules-my-claims">';
$printPage .=   '<h2>My Upcoming OT</h2>';

if ( empty($my_claims) ) {
	$printPage .= '<p class="claims-empty">You have no upcoming OT shifts claimed.</p>';
} else {
	$printPage .= '<ul class="my-claims-list">';
	foreach ( $my_claims as $claim ) {
		$claim_date   = date( 'l, M j', strtotime($claim['schedule_date']) );
		$time_str     = function_exists('schedules_format_block_time')
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

$printPage .= '</div>'; // sup-view-calendar

// ---- VIEW: Duty Assignments (read-only) ----
$printPage .= '<div class="sup-view" id="sup-view-duty" data-view="duty" role="tabpanel" hidden>';
$printPage .=   '<div class="duty-section-header duty-toolbar toolbar">';
$printPage .=     '<div class="duty-filter-group">';
$printPage .=       '<label for="member-duty-date">Date</label>';
$printPage .=       '<input type="date" id="member-duty-date" value="' . esc_attr(date('Y-m-d')) . '">';
$printPage .=     '</div>';
$printPage .=     '<div class="duty-filter-group">';
$printPage .=       '<label for="member-duty-shift">Shift</label>';
$printPage .=       '<select id="member-duty-shift" aria-label="Select shift">';
foreach ( $_pdo_shifts as $_ds ) {
    $_ds_sel = ( $_ds['shift_letter'] === $user_shift ) ? ' selected' : '';
    $printPage .= '<option value="' . esc_attr($_ds['shift_letter']) . '"' . $_ds_sel . '>Shift ' . esc_html($_ds['shift_letter']) . '</option>';
}
$printPage .=       '</select>';
$printPage .=     '</div>';
$printPage .=   '</div>';
$printPage .=   '<div id="member-duty-content"><p class="duty-loading">Loading&hellip;</p></div>';
$printPage .= '</div>';

// ---- VIEW: Settings ----
$printPage .= '<div class="sup-view" id="sup-view-settings" data-view="settings" role="tabpanel" hidden>';

// Display Preferences
$printPage .= '<div class="config-section" id="settings-display">';
$printPage .=   '<div class="config-section-header"><h2>Display Preferences</h2></div>';
$printPage .=   '<form id="user-settings-form" novalidate>';
$printPage .=     '<div class="form-grid-2">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="user-name-format">Member Name Format</label>';
$printPage .=         '<select id="user-name-format" name="name_format">';
$printPage .=           '<option value="first_last"' . selected( $_ot_name_format, 'first_last', false ) . '>First Last</option>';
$printPage .=           '<option value="last_first"' . selected( $_ot_name_format, 'last_first', false ) . '>Last, First</option>';
$printPage .=         '</select>';
$printPage .=       '</div>';
$printPage .=     '</div>';
$printPage .=     '<div class="form-error" id="user-settings-error" role="alert" aria-live="polite"></div>';
$printPage .=     '<div class="form-success" id="user-settings-success" role="status" aria-live="polite"></div>';
$printPage .=     '<div class="settings-save-row">';
$printPage .=       '<button type="submit" class="schedules-btn schedules-btn-primary" id="user-settings-submit">Save Preferences</button>';
$printPage .=     '</div>';
$printPage .=   '</form>';
$printPage .= '</div>';

// Change Password
$printPage .= '<div class="config-section" id="settings-change-pw">';
$printPage .=   '<div class="config-section-header"><h2>Change Password</h2></div>';
$printPage .=   '<form id="schedules-change-pw-form" novalidate>';
$printPage .=     '<div class="form-grid-2">';
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
$printPage .=     '</div>';
$printPage .=     '<div class="form-error" id="change-pw-error" role="alert" aria-live="polite"></div>';
$printPage .=     '<div class="form-success" id="change-pw-success" role="status" aria-live="polite"></div>';
$printPage .=     '<div class="settings-save-row">';
$printPage .=       '<button type="submit" class="schedules-btn schedules-btn-primary">Update Password</button>';
$printPage .=     '</div>';
$printPage .=   '</form>';
$printPage .= '</div>';

$printPage .= '</div>'; // sup-view-settings

// ---- VIEW: Coverage Requests ----
$printPage .= '<div class="sup-view" id="sup-view-coverage" data-view="coverage" role="tabpanel" hidden>';
$printPage .=   '<div class="cover-section">';
$printPage .=     '<h3 class="cover-section-heading">Incoming Requests</h3>';
$printPage .=     '<div id="cover-incoming-list"></div>';
$printPage .=   '</div>';
$printPage .=   '<div class="cover-section">';
$printPage .=     '<h3 class="cover-section-heading">Outgoing Requests</h3>';
$printPage .=     '<div id="cover-outgoing-list"></div>';
$printPage .=   '</div>';
$printPage .= '</div>'; // sup-view-coverage

// ---- Time Off Request Popup (outside views) ----
$printPage .= '<div id="timeoff-popup" class="timeoff-popup" hidden>';
$printPage .=   '<div class="timeoff-popup-inner">';
$printPage .=     '<div class="timeoff-popup-header">';
$printPage .=       '<h3 id="timeoff-popup-title">Request Time Off</h3>';
$printPage .=       '<button type="button" class="basic-btn timeoff-popup-close" aria-label="Close">&times;</button>';
$printPage .=     '</div>';
$printPage .=     '<div class="timeoff-popup-body">';
$printPage .=       '<input type="hidden" id="timeoff-user-id" value="">';
// Type radio — hidden by default; shown on future dates with a shift
$printPage .=       '<div class="form-group timeoff-type-row" id="timeoff-type-row" hidden>';
$printPage .=         '<div class="timeoff-type-radios">';
$printPage .=           '<label class="radio-label"><input type="radio" name="timeoff_type_radio" value="pdo" checked> PDO</label>';
$printPage .=           '<label class="radio-label"><input type="radio" name="timeoff_type_radio" value="cover"> Request Coverage</label>';
$printPage .=         '</div>';
$printPage .=       '</div>';
// Date + time range — applies to both PDO and coverage
$printPage .=       '<div class="timeoff-range-row">';
$printPage .=         '<div class="timeoff-range-field">';
$printPage .=           '<label for="timeoff-start-date">Start</label>';
$printPage .=           '<input type="date" id="timeoff-start-date" class="timeoff-date-input">';
$printPage .=           '<select id="timeoff-start"></select>';
$printPage .=         '</div>';
$printPage .=         '<div class="timeoff-range-field">';
$printPage .=           '<label for="timeoff-end-date">End</label>';
$printPage .=           '<input type="date" id="timeoff-end-date" class="timeoff-date-input">';
$printPage .=           '<select id="timeoff-end"></select>';
$printPage .=         '</div>';
$printPage .=       '</div>';
// Coverage section — shown when coverage selected
$printPage .=       '<div id="timeoff-cover-section" hidden>';
$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="cover-recipient-select">Ask member to cover:</label>';
$printPage .=           '<select id="cover-recipient-select"><option value="">&mdash; Select a member &mdash;</option></select>';
$printPage .=         '</div>';
$printPage .=       '</div>';
// Notes — shared
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="timeoff-notes">Notes <small>(optional)</small></label>';
$printPage .=         '<textarea id="timeoff-notes" rows="2"></textarea>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-error" id="timeoff-error" role="alert" aria-live="polite"></div>';
$printPage .=     '</div>';
$printPage .=     '<div class="timeoff-popup-footer">';
$printPage .=       '<button type="button" class="schedules-btn" id="timeoff-cancel-btn">Cancel</button>';
$printPage .=       '<button type="button" class="schedules-btn schedules-btn-primary" id="timeoff-submit-btn">Submit Request</button>';
$printPage .=     '</div>';
$printPage .=   '</div>';
$printPage .= '</div>';

// ---- Block confirmation popup (outside views) ----
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
