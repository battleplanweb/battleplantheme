<?php
/* Battle Plan Web Design - CCSO Supervisor Dashboard Page */

// Auth guard fallback
if ( ! is_user_logged_in() || ! current_user_can('manage_schedules') ) {
	$redirect = is_user_logged_in() ? home_url('/schedules-overtime/') : home_url('/schedules-login/');
	$printPage = '<script>window.location.href="' . esc_js($redirect) . '";</script>';
	return $printPage;
}

$user_id    = defined('_USER_ID') ? _USER_ID : get_current_user_id();
$user       = get_userdata($user_id);
$badge      = $user ? esc_html($user->user_login) : '';
$first_name = $user ? esc_html($user->first_name) : '';
$last_name  = $user ? esc_html($user->last_name)  : '';
$full_name  = trim("{$first_name} {$last_name}");
if ( ! $full_name ) $full_name = esc_html($user ? $user->display_name : 'Supervisor');

// Calendar data (all shifts)
$calendar_data = [];
if ( function_exists('schedules_get_supervisor_calendar_data') ) {
	$calendar_data = schedules_get_supervisor_calendar_data();
}
$weeks    = isset($calendar_data['weeks'])    ? $calendar_data['weeks']    : [];
$max_week = isset($calendar_data['max_week']) ? $calendar_data['max_week'] : 4;

// Members data
$members = [];
if ( function_exists('schedules_get_all_members') ) {
	$members = schedules_get_all_members();
}

$deactivated_members = [];
if ( function_exists('schedules_get_deactivated_members') ) {
	$deactivated_members = schedules_get_deactivated_members();
}

$_titles_all = function_exists('schedules_get_titles') ? schedules_get_titles( false ) : [];
$_titles_map = [];
foreach ( $_titles_all as $_t ) {
	$_titles_map[ (int) $_t['id'] ] = esc_html( $_t['name'] );
}

// Shifts data
global $wpdb;
$shifts = [];
if ( function_exists('schedules_table') ) {
	$shifts = $wpdb->get_results(
		"SELECT * FROM " . schedules_table('shifts') . " ORDER BY shift_letter ASC",
		ARRAY_A
	);
}

$dow_labels = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];

$shift_day_labels = [
	'A' => 'Sun – Wed',
	'B' => 'Wed – Sat',
	'C' => 'Sun – Wed',
	'D' => 'Wed – Sat',
];

$today_date       = date('Y-m-d');
$sup_can_claim_ot = function_exists('schedules_get_config') && schedules_get_config( 'supervisors_can_claim_ot', '0' ) === '1';

$printPage  = '';
$printPage .= '<div id="schedules-supervisor-app">';

// ---- App Header ----
$printPage .= '<header class="schedules-app-header schedules-supervisor-header">';
$printPage .=   '<div class="schedules-app-logo">';
$printPage .=     '<img src="/wp-content/uploads/logo.webp">';
$printPage .=   '</div>';
$printPage .=   '<div class="schedules-app-brand">';
$printPage .=     '<span class="schedules-app-title">CCSO Overtime</span>';
$printPage .=     '<span class="schedules-app-subtitle">Supervisor Dashboard</span>';
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
$printPage .=   '</div>';
$printPage .=   '<button id="schedules-logout-btn" class="schedules-btn schedules-btn-secondary" type="button">Sign Out</button>';
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

// ---- Supervisor Nav ----
$printPage .= '<nav class="schedules-supervisor-nav" role="tablist" aria-label="Supervisor views">';
$printPage .=   '<button class="sup-tab" data-view="schedule" role="tab" aria-selected="false" aria-controls="sup-view-schedule">Schedule</button>';
$printPage .=   '<button class="sup-tab active" data-view="calendar" role="tab" aria-selected="true" aria-controls="sup-view-calendar">Claim OT</button>';
$printPage .=   '<button class="sup-tab" data-view="claims"   role="tab" aria-selected="false" aria-controls="sup-view-claims">Claims Log</button>';
$printPage .=   '<button class="sup-tab" data-view="members"  role="tab" aria-selected="false" aria-controls="sup-view-members">Members</button>';
$printPage .=   '<button class="sup-tab" data-view="duty"     role="tab" aria-selected="false" aria-controls="sup-view-duty">Duty Assignments</button>';
$printPage .=   '<button class="sup-tab" data-view="settings" role="tab" aria-selected="false" aria-controls="sup-view-settings">Settings</button>';
$printPage .= '</nav>';

// ---- VIEW: Calendar ----
$printPage .= '<div class="sup-view active" id="sup-view-calendar" data-view="calendar" role="tabpanel">';

// Week tabs
if ( ! empty($weeks) ) {
	$printPage .= '<div class="schedules-week-tabs toolbar" role="tablist" aria-label="Select week">';
	foreach ( $weeks as $week_num => $week_days ) {
		$_tab_dates = array_keys($week_days);
		sort($_tab_dates);
		$_tab_first = date( 'M j', strtotime( reset($_tab_dates) ) );
		$_tab_last  = date( 'M j', strtotime( end($_tab_dates) ) );
		$_tab_active = $week_num === array_key_first($weeks);
		$printPage .= '<button class="week-tab' . ( $_tab_active ? ' active' : '' ) . '" role="tab" aria-selected="' . ( $_tab_active ? 'true' : 'false' ) . '" data-week="' . $week_num . '">';
		$printPage .= 'Week ' . $week_num . '<br><span>' . $_tab_first . ' – ' . $_tab_last . '</span>';
		$printPage .= '</button>';
	}
	$printPage .= '</div>';
}

// Calendar
$printPage .= '<div class="schedules-calendar schedules-supervisor-calendar' . ( $sup_can_claim_ot ? ' sup-can-claim' : '' ) . '">';

if ( empty($weeks) ) {
	$printPage .= '<div class="schedules-empty"><p>No schedule days found in the next 28 days.</p></div>';
} else {
	foreach ( $weeks as $week_num => $week_days ) {
		$hidden    = $week_num === array_key_first($weeks) ? '' : ' hidden';
		$printPage .= '<div class="schedules-week" id="sup-week-panel-' . $week_num . '" data-week="' . $week_num . '" role="tabpanel"' . $hidden . '>';

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
				$printPage .=   '<div class="shift-group-header">';
				$printPage .=     '<div class="shift-label"><span class="shift-pill shift-' . strtolower($shift_letter) . '">Shift ' . esc_html($shift_data['shift_letter']) . '</span><span class="shift-type-label">' . esc_html($shift_type_l) . '</span></div>';
				$printPage .=     '<button class="schedules-btn schedules-btn-small add-slot-btn" type="button" data-day-id="' . $day_id . '" data-shift="' . $shift_letter . '" data-date="' . esc_attr($date) . '">';
				$printPage .=       '+ Add Slot';
				$printPage .=     '</button>';
				$printPage .=   '</div>';

				// Inline add-slot form (hidden by default, toggled per day by JS)
				$printPage .= '<div class="add-slot-form" id="add-slot-form-' . $day_id . '" hidden>';
				$printPage .=   '<textarea class="add-slot-reason" placeholder="Reason for opening slot (e.g. callout, extra coverage)" rows="2"></textarea>';
				$printPage .=   '<div class="add-slot-actions">';
				$printPage .=     '<button class="schedules-btn schedules-btn-primary add-slot-confirm" type="button" data-day-id="' . $day_id . '">Open Slot</button>';
				$printPage .=     '<button class="schedules-btn add-slot-cancel" type="button">Cancel</button>';
				$printPage .=   '</div>';
				$printPage .=   '<div class="add-slot-msg" role="status" aria-live="polite"></div>';
				$printPage .= '</div>';

				$printPage .=   '<div class="shift-blocks">';

				$_grace_minutes = (int) apply_filters( 'schedules_unclaim_grace_minutes', 5 );

				foreach ( $shift_data['blocks'] as $block ) {
					$block_id      = (int) $block['id'];
					$available     = (int) $block['available'];
					$claims_count  = isset($block['claims_count']) ? (int) $block['claims_count'] : 0;
					$user_claimed  = (bool) $block['user_claimed'];
					$time_str      = function_exists('schedules_format_block_time')
						? schedules_format_block_time($block['start_hour'], $block['end_hour'])
						: $block['start_hour'] . '–' . $block['end_hour'];

					$_undo_remaining = 0;
					if ( $user_claimed && ! empty($block['user_claimed_at']) ) {
						$_claimed_ts     = strtotime( $block['user_claimed_at'] );
						$_deadline       = $_claimed_ts + ( $_grace_minutes * 60 );
						$_undo_remaining = max( 0, $_deadline - current_time('timestamp') );
					}

					if ( $user_claimed ) {
						$state_class = 'claimed';
						if ( $_undo_remaining > 0 ) $state_class .= ' claim-undoable';
					} elseif ( $available <= 0 ) {
						$state_class = 'full';
					} else {
						$state_class = 'available';
						if ( $available <= 2 ) $state_class .= ' limited';
					}

					$claimable  = ! $user_claimed && $sup_can_claim_ot && $available > 0;
					$block_cls  = $state_class . ( $claimable ? '' : ' supervisor-block' );
					$block_data = ' data-block-id="' . $block_id . '" data-available="' . $available . '"';
					if ( $claimable ) {
						$block_data .= ' data-time="' . esc_attr($time_str) . '" data-shift="' . $shift_letter . '" data-date="' . esc_attr($date) . '"';
					}
					$printPage .= '<div class="time-block ' . $block_cls . '"' . $block_data . '>';
					$printPage .=   '<span class="time-range">' . esc_html($time_str) . '</span>';
					$printPage .=   '<span class="block-status">' . $available . ' open</span>';
					$printPage .=   '<span class="block-claims-count">' . $claims_count . ' claimed</span>';
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
$printPage .= '</div>'; // sup-view calendar

// ---- VIEW: Claims Log ----
$printPage .= '<div class="sup-view" id="sup-view-claims" data-view="claims" role="tabpanel" hidden>';
$printPage .=   '<div class="claims-filters toolbar">';
$printPage .=     '<div class="claims-filter-group">';
$printPage .=       '<label for="claims-date-filter">Date</label>';
$printPage .=       '<input type="date" id="claims-date-filter" value="' . esc_attr($today_date) . '">';
$printPage .=     '</div>';
$printPage .=     '<div class="claims-filter-group">';
$printPage .=       '<label for="claims-shift-filter">Shift</label>';
$printPage .=       '<select id="claims-shift-filter">';
$printPage .=         '<option value="">All Shifts</option>';
$printPage .=         '<option value="A">Shift A (Day · Sun–Wed)</option>';
$printPage .=         '<option value="B">Shift B (Day · Wed–Sat)</option>';
$printPage .=         '<option value="C">Shift C (Night · Sun–Wed)</option>';
$printPage .=         '<option value="D">Shift D (Night · Wed–Sat)</option>';
$printPage .=       '</select>';
$printPage .=     '</div>';
$printPage .=   '</div>';
$printPage .=   '<div id="claims-results" class="claims-table-wrap">';
$printPage .=     '<p class="claims-hint">Select a date and click Load Claims.</p>';
$printPage .=   '</div>';
$printPage .= '</div>'; // sup-view claims

// ---- VIEW: Members ----
$printPage .= '<div class="sup-view" id="sup-view-members" data-view="members" role="tabpanel" hidden>';
$_user_name_format   = get_user_meta( $user_id, 'schedules_name_format', true ) ?: 'first_last';
$_user_members_view  = get_user_meta( $user_id, 'schedules_members_view', true ) ?: 'row';
$_view_toggle_label  = $_user_members_view === 'card' ? 'Row View' : 'Card View';

$printPage .=   '<div class="members-toolbar toolbar">';
$printPage .=     '<input type="text" id="members-search" placeholder="Search by name or badge..." autocomplete="off">';
$printPage .=     '<select id="members-filter-shift"><option value="">All Shifts</option>';
foreach ( $shifts as $s ) {
	$printPage .= '<option value="' . esc_attr( strtolower($s['shift_letter']) ) . '">Shift ' . esc_html($s['shift_letter']) . '</option>';
}
$printPage .=       '<option value="none">No Shift</option>';
$printPage .=     '</select>';
$_priority_labels = apply_filters( 'schedules_priority_labels', [
	''  => 'All OT',
	'1' => 'Priority 1',
	'2' => 'Priority 2',
	'3' => 'Priority 3',
	'4' => 'Priority 4',
	'5' => 'Priority 5',
] );
$printPage .=     '<select id="members-filter-priority">';
foreach ( $_priority_labels as $_pval => $_plabel ) {
	$printPage .= '<option value="' . esc_attr($_pval) . '">' . esc_html($_plabel) . '</option>';
}
$printPage .=     '</select>';
$printPage .=     '<select id="members-filter-title"><option value="">All Titles</option>';
foreach ( $_titles_all as $_t ) {
	if ( (int) $_t['is_active'] ) {
		$printPage .= '<option value="' . (int) $_t['id'] . '">' . esc_html($_t['name']) . '</option>';
	}
}
$printPage .=       '<option value="0">No Title</option>';
$printPage .=     '</select>';
$printPage .=     '<button id="members-view-toggle" class="schedules-btn schedules-btn-primary" type="button">' . $_view_toggle_label . '</button>';
$printPage .=     '<button id="add-member-btn" class="schedules-btn schedules-btn-primary" type="button">+ Add Member</button>';
$printPage .=   '</div>';

$printPage .=   '<div id="members-table-wrap" class="members-wrap-' . esc_attr($_user_members_view) . '">';

if ( empty($members) ) {
	$printPage .= '<p class="members-empty">No members found. Add your first member above.</p>';
} else {
	$printPage .= '<div class="members-grid">';

	foreach ( $members as $member ) {
		$uid        = (int) $member['user_id'];
		$m_badge    = esc_html($member['badge_number']);
		$m_first    = esc_html($member['first_name']);
		$m_last     = esc_html($member['last_name']);
		$m_name     = $m_first && $m_last
			? ( $_user_name_format === 'last_first' ? $m_last . ', ' . $m_first : $m_first . ' ' . $m_last )
			: esc_html($member['display_name']);
		$m_shift    = esc_html($member['shift']);
		$m_disc     = $member['discipline'];
		$m_prio     = $member['priority'];
		$m_role     = $member['role'];
		$m_email    = esc_html($member['email']);
		$m_title_id = (int) $member['title_id'];
		$m_title    = $m_title_id && isset($_titles_map[$m_title_id]) ? $_titles_map[$m_title_id] : '';
		$m_pay_rate = (float) $member['pay_rate'];
		$disc_parts = $m_disc ? array_filter( array_map( 'trim', explode( ',', $m_disc ) ) ) : [];

		$printPage .= '<div class="member-card" data-user-id="' . $uid . '" data-name="' . esc_attr( strtolower( $member['first_name'] . ' ' . $member['last_name'] . ' ' . $member['badge_number'] ) ) . '" data-shift="' . esc_attr( strtolower($member['shift']) ) . '" data-priority="' . esc_attr( $m_prio ?: 'none' ) . '" data-title-id="' . $m_title_id . '">';

		$printPage .=   '<div class="mc-head">';
		$printPage .=     '<span class="mc-name">' . $m_name . '</span>';
		$printPage .=     '<span class="mc-badge">#' . $m_badge . '</span>';
		$printPage .=   '</div>';

		$printPage .=   '<div class="mc-pills">';
		$printPage .=     ( $m_shift
			? '<span class="shift-pill shift-' . strtolower(esc_attr($m_shift)) . '">Shift ' . esc_html($m_shift) . '</span>'
			: '<span class="shift-pill shift-none">No Shift</span>' );
		if ( $m_title ) {
			$printPage .= '<span class="mc-title-pill">' . esc_html($m_title) . '</span>';
		}
		if ( $m_prio ) {
			$printPage .= '<span class="priority-pill priority-' . esc_attr($m_prio) . '">OT</span>';
		}
		$printPage .=   '</div>';

		$printPage .=   '<div class="mc-details">';
		if ( $m_pay_rate > 0 && current_user_can('admin_schedules') ) {
			$printPage .= '<div class="mc-detail-row"><span class="mc-pay">$' . number_format($m_pay_rate, 2) . '/hr</span></div>';
		}
		if ( $disc_parts ) {
			$printPage .= '<div class="mc-disc">' . esc_html( implode( ', ', array_map( 'ucfirst', $disc_parts ) ) ) . '</div>';
		}
		$printPage .=     '<div class="mc-email">' . $m_email . '</div>';
		$printPage .=   '</div>';

		$printPage .=   '<div class="mc-actions">';
		$printPage .=     '<button class="schedules-btn schedules-btn-small edit-member-btn" type="button"';
		$printPage .=       ' data-user-id="' . $uid . '"';
		$printPage .=       ' data-first-name="' . esc_attr($member['first_name']) . '"';
		$printPage .=       ' data-last-name="' . esc_attr($member['last_name']) . '"';
		$printPage .=       ' data-badge="' . esc_attr($member['badge_number']) . '"';
		$printPage .=       ' data-email="' . esc_attr($member['email']) . '"';
		$printPage .=       ' data-shift="' . esc_attr($member['shift']) . '"';
		$printPage .=       ' data-discipline="' . esc_attr($member['discipline']) . '"';
		$printPage .=       ' data-priority="' . esc_attr($m_prio) . '"';
		$printPage .=       ' data-role="' . esc_attr($m_role) . '"';
		$printPage .=       ' data-title-id="' . $m_title_id . '"';
		$printPage .=       ' data-pay-rate="' . esc_attr($m_pay_rate) . '"';
		$printPage .=       ' data-schedule-type="' . esc_attr($member['schedule_type']) . '"';
		$printPage .=       ' data-custom-schedule="' . esc_attr( json_encode($member['custom_schedule']) ) . '"';
		$printPage .=     '>Edit</button>';
		$printPage .=     '<button class="schedules-btn schedules-btn-small schedules-btn-danger deactivate-member-btn" type="button" data-user-id="' . $uid . '" data-name="' . esc_attr($member['display_name']) . '">Deactivate</button>';
		$printPage .=   '</div>';

		$printPage .= '</div>'; // .member-card
	}

	$printPage .= '</div>'; // .members-grid

	$printPage .= '<div class="members-row-view">';
	$printPage .= '<table class="schedules-table members-table-rows">';
	$printPage .=   '<thead><tr>';
	$printPage .=     '<th class="head-badge">Badge</th>';
	$printPage .=     '<th class="head-name">Name</th>';
	$printPage .=     '<th class="head-title">Title</th>';
	$printPage .=     '<th class="head-shift">Shift</th>';
	$printPage .=     '<th class="head-priority">OT Priority</th>';
	$printPage .=     '<th class="head-discipline">Discipline</th>';
	$printPage .=     '<th class="head-actions">Actions</th>';
	$printPage .=   '</tr></thead>';
	$printPage .=   '<tbody>';
	foreach ( $members as $member ) {
		$uid        = (int) $member['user_id'];
		$m_badge    = esc_html($member['badge_number']);
		$m_first    = esc_html($member['first_name']);
		$m_last     = esc_html($member['last_name']);
		$m_name     = $m_first && $m_last
			? ( $_user_name_format === 'last_first' ? $m_last . ', ' . $m_first : $m_first . ' ' . $m_last )
			: esc_html($member['display_name']);
		$m_shift    = esc_html($member['shift']);
		$m_disc     = $member['discipline'];
		$m_prio     = $member['priority'];
		$m_role     = $member['role'];
		$m_title_id = (int) $member['title_id'];
		$m_title    = $m_title_id && isset($_titles_map[$m_title_id]) ? esc_html($_titles_map[$m_title_id]) : '';
		$m_pay_rate = (float) $member['pay_rate'];
		$disc_parts = $m_disc ? array_filter( array_map( 'trim', explode( ',', $m_disc ) ) ) : [];
		$disc_str   = $disc_parts ? esc_html( implode( ', ', array_map( 'ucfirst', $disc_parts ) ) ) : '&mdash;';
		$printPage .= '<tr data-user-id="' . $uid . '" data-name="' . esc_attr( strtolower( $member['first_name'] . ' ' . $member['last_name'] . ' ' . $member['badge_number'] ) ) . '" data-shift="' . esc_attr( strtolower($member['shift']) ) . '" data-priority="' . esc_attr( $m_prio ?: 'none' ) . '" data-title-id="' . $m_title_id . '">';
		$printPage .=   '<td class="col-badge">#' . $m_badge . '</td>';
		$printPage .=   '<td class="col-name">' . $m_name . '</td>';
		$printPage .=   '<td class="col-title">' . ( $m_title ? $m_title : '&mdash;' ) . '</td>';
		$printPage .=   '<td class="col-shift">';
		$printPage .=     $m_shift
			? '<span class="shift-pill shift-' . strtolower(esc_attr($m_shift)) . '">' . esc_html($m_shift) . '</span>'
			: '&mdash;';
		$printPage .=   '</td>';
		$printPage .=   '<td class="col-priority">';
		if ( $m_prio && $m_prio !== '1' ) {
			$printPage .= '<span class="priority-pill priority-' . esc_attr($m_prio) . '">OT</span>';
		} elseif ( $m_prio === '1' ) {
			$printPage .= '&mdash;';
		}
		$printPage .=   '</td>';
		$printPage .=   '<td class="col-discipline">' . $disc_str . '</td>';
		$printPage .=   '<td class="col-actions">';
		$printPage .=     '<button class="schedules-btn schedules-btn-small edit-member-btn" type="button"';
		$printPage .=       ' data-user-id="' . $uid . '"';
		$printPage .=       ' data-first-name="' . esc_attr($member['first_name']) . '"';
		$printPage .=       ' data-last-name="' . esc_attr($member['last_name']) . '"';
		$printPage .=       ' data-badge="' . esc_attr($member['badge_number']) . '"';
		$printPage .=       ' data-email="' . esc_attr($member['email']) . '"';
		$printPage .=       ' data-shift="' . esc_attr($member['shift']) . '"';
		$printPage .=       ' data-discipline="' . esc_attr($member['discipline']) . '"';
		$printPage .=       ' data-priority="' . esc_attr($m_prio) . '"';
		$printPage .=       ' data-role="' . esc_attr($m_role) . '"';
		$printPage .=       ' data-title-id="' . $m_title_id . '"';
		$printPage .=       ' data-pay-rate="' . esc_attr($m_pay_rate) . '"';
		$printPage .=       ' data-schedule-type="' . esc_attr($member['schedule_type']) . '"';
		$printPage .=       ' data-custom-schedule="' . esc_attr( json_encode($member['custom_schedule']) ) . '"';
		$printPage .=     '>Edit</button>';
		$printPage .=     '<button class="schedules-btn schedules-btn-small schedules-btn-danger deactivate-member-btn" type="button" data-user-id="' . $uid . '" data-name="' . esc_attr($member['display_name']) . '">Deactivate</button>';
		$printPage .=   '</td>';
		$printPage .= '</tr>';
	}
	$printPage .=   '</tbody>';
	$printPage .= '</table>';
	$printPage .= '</div>'; // .members-row-view
}

$printPage .= '</div>'; // #members-table-wrap

// ---- Deactivated Members ----
$printPage .= '<div class="members-deactivated-section">';
$printPage .=   '<h3 class="deactivated-heading">Deactivated Members</h3>';
if ( empty($deactivated_members) ) {
	$printPage .= '<p class="members-empty">No deactivated members.</p>';
} else {
	$printPage .= '<div class="table-responsive">';
	$printPage .= '<table class="schedules-table members-table members-table-deactivated">';
	$printPage .=   '<thead><tr>';
	$printPage .=     '<th>Badge #</th>';
	$printPage .=     '<th>Name</th>';
	$printPage .=     '<th>Former Role</th>';
	$printPage .=     '<th>Shift</th>';
	$printPage .=     '<th>Deactivated</th>';
	if ( current_user_can('admin_schedules') ) {
		$printPage .= '<th class="actions-col">Actions</th>';
	}
	$printPage .=   '</tr></thead>';
	$printPage .=   '<tbody>';
	foreach ( $deactivated_members as $dm ) {
		$dm_uid   = (int) $dm['user_id'];
		$dm_first = esc_html($dm['first_name'] ?? '');
		$dm_last  = esc_html($dm['last_name'] ?? '');
		$dm_name  = $dm_first && $dm_last
			? ( $_user_name_format === 'last_first' ? $dm_last . ', ' . $dm_first : $dm_first . ' ' . $dm_last )
			: esc_html($dm['display_name']);
		$dm_date = $dm['deactivated_at'] ? date( 'M j, Y', strtotime($dm['deactivated_at']) ) : '—';
		$role_label = $dm['former_role'] === 'schedules_admin' ? 'Tier 3' : ( $dm['former_role'] === 'schedules_supervisor' ? 'Tier 2' : 'Tier 1' );
		$printPage .= '<tr data-user-id="' . $dm_uid . '">';
		$printPage .=   '<td class="badge-col"><strong>' . esc_html($dm['badge_number']) . '</strong></td>';
		$printPage .=   '<td>' . $dm_name . '</td>';
		$printPage .=   '<td>' . esc_html($role_label) . '</td>';
		$printPage .=   '<td>' . ( $dm['shift'] ? '<span class="shift-pill shift-' . strtolower(esc_attr($dm['shift'])) . '">Shift ' . esc_html($dm['shift']) . '</span>' : '—' ) . '</td>';
		$printPage .=   '<td>' . esc_html($dm_date) . '</td>';
		if ( current_user_can('admin_schedules') ) {
			$printPage .= '<td class="actions-col"><button class="schedules-btn schedules-btn-small schedules-btn-danger purge-member-btn" type="button" data-user-id="' . $dm_uid . '" data-name="' . esc_attr($dm['display_name']) . '">Purge Account</button></td>';
		}
		$printPage .= '</tr>';
	}
	$printPage .=   '</tbody>';
	$printPage .= '</table>';
	$printPage .= '</div>'; // .table-responsive
}
$printPage .= '</div>'; // .members-deactivated-section

$printPage .= '</div>'; // #sup-view-members (closed here, was closed after modal before)

// ---- Add/Edit Member Modal ----
$printPage .= '<div id="member-modal" class="schedules-modal" hidden role="dialog" aria-modal="true" aria-labelledby="member-modal-title">';
$printPage .=   '<div class="schedules-modal-backdrop"></div>';
$printPage .=   '<div class="schedules-modal-box">';
$printPage .=     '<button class="modal-close" aria-label="Close modal" type="button">&times;</button>';
$printPage .=     '<h2 id="member-modal-title">Add Member</h2>';
$printPage .=     '<form id="member-form" novalidate>';
$printPage .=       '<input type="hidden" name="user_id" id="member-user-id" value="0">';

$printPage .=       '<div class="form-grid-2">';

$printPage .=         '<div class="form-group member-fg-first-name">';
$printPage .=           '<label for="member-first-name">First Name <span class="req">*</span></label>';
$printPage .=           '<input type="text" id="member-first-name" name="first_name" required autocomplete="off">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-last-name">';
$printPage .=           '<label for="member-last-name">Last Name <span class="req">*</span></label>';
$printPage .=           '<input type="text" id="member-last-name" name="last_name" required autocomplete="off">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-badge">';
$printPage .=           '<label for="member-badge">Badge Number <span class="req">*</span></label>';
$printPage .=           '<input type="text" id="member-badge" name="badge_number" required autocomplete="off" inputmode="numeric">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-email">';
$printPage .=           '<label for="member-email">Email <span class="req">*</span></label>';
$printPage .=           '<input type="email" id="member-email" name="email" required autocomplete="off">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-password-row member-fg-password">';
$printPage .=           '<label for="member-password">Password <span class="req new-only">*</span></label>';
$printPage .=           '<input type="password" id="member-password" name="password" autocomplete="new-password" minlength="8">';
$printPage .=           '<span class="field-hint new-only">Required for new members. Leave blank to keep existing.</span>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-shift">';
$printPage .=           '<label for="member-shift">Shift</label>';
$printPage .=           '<select id="member-shift" name="shift">';
$printPage .=             '<option value="">No Shift</option>';
$printPage .=             '<option value="A">Shift A (Day · Sun–Wed)</option>';
$printPage .=             '<option value="B">Shift B (Day · Wed–Sat)</option>';
$printPage .=             '<option value="C">Shift C (Night · Sun–Wed)</option>';
$printPage .=             '<option value="D">Shift D (Night · Wed–Sat)</option>';
$printPage .=           '</select>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-priority">';
$printPage .=           '<label for="member-priority">OT Priority</label>';
$printPage .=           '<select id="member-priority" name="priority">';
$printPage .=             '<option value="">— Auto (by pay rate) —</option>';
$printPage .=             '<option value="1">Priority 1 (Lowest)</option>';
$printPage .=             '<option value="5">Priority 5 (Highest)</option>';
$printPage .=           '</select>';
$printPage .=           '<span class="field-hint">Tiers 2/3/4 are auto-assigned based on pay rate. Tiers 1 and 5 are manual.</span>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-pay-rate">';
$printPage .=           '<label for="member-pay-rate">Pay Rate ($/hr)</label>';
$printPage .=           '<input type="number" id="member-pay-rate" name="pay_rate" min="0" step="0.01" value="0" autocomplete="off">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-role">';
$printPage .=           '<label for="member-role">Site Access <span class="req">*</span></label>';
$printPage .=           '<select id="member-role" name="member_role">';
$printPage .=             '<option value="member">Tier 1 (lowest)</option>';
$printPage .=             '<option value="supervisor">Tier 2</option>';
if ( current_user_can('admin_schedules') ) :
$printPage .=             '<option value="admin">Tier 3 (highest)</option>';
endif;
$printPage .=           '</select>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-fg-title">';
$printPage .=           '<label for="member-title">Title</label>';
$printPage .=           '<select id="member-title" name="title_id">';
$printPage .=             '<option value="0">— None —</option>';
$_modal_titles = function_exists('schedules_get_titles') ? schedules_get_titles() : [];
foreach ( $_modal_titles as $_mt ) {
	$printPage .= '<option value="' . (int)$_mt['id'] . '">' . esc_html($_mt['name']) . '</option>';
}
$printPage .=           '</select>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group form-group-full member-fg-discipline">';
$printPage .=           '<label>Discipline</label>';
$printPage .=           '<div class="discipline-checkboxes" id="member-discipline-group">';
$_all_disciplines = function_exists('schedules_get_disciplines') ? schedules_get_disciplines() : [];
foreach ( $_all_disciplines as $_disc ) {
	$val   = esc_attr( strtolower( str_replace( ' ', '-', $_disc['name'] ) ) );
	$label = esc_html( $_disc['name'] );
	$printPage .= '<label class="check-label"><input type="checkbox" name="discipline[]" value="' . $val . '"> ' . $label . '</label>';
}
$printPage .=           '</div>';
$printPage .=         '</div>';

$printPage .=       '</div>'; // .form-grid-2

$printPage .= '<div class="form-group form-group-full member-fg-schedule-type">';
$printPage .=   '<label>Schedule Type</label>';
$printPage .=   '<div class="schedule-type-radios">';
$printPage .=     '<label class="radio-label"><input type="radio" name="schedule_type" value="shift" checked> Standard Shift</label>';
$printPage .=     '<label class="radio-label"><input type="radio" name="schedule_type" value="custom"> Custom Schedule</label>';
$printPage .=   '</div>';
$printPage .= '</div>';

$_dow_names_long = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
$printPage .= '<div id="custom-schedule-group" class="custom-schedule-section" hidden>';
$printPage .=   '<label class="form-label-block">Custom Work Schedule</label>';
$printPage .=   '<div class="custom-schedule-grid">';
for ( $_cd = 0; $_cd < 7; $_cd++ ) {
	$printPage .= '<div class="custom-day-row" data-dow="' . $_cd . '">';
	$printPage .=   '<label class="check-label custom-day-label">';
	$printPage .=     '<input type="checkbox" class="custom-day-check" name="custom_day[' . $_cd . ']" value="1"> ';
	$printPage .=     esc_html( $_dow_names_long[ $_cd ] );
	$printPage .=   '</label>';
	$printPage .=   '<div class="custom-day-times">';
	$printPage .=     '<select name="custom_start[' . $_cd . ']" class="custom-time-select custom-start-hour" disabled>';
	for ( $_ch = 0; $_ch < 24; $_ch++ ) {
		if ( $_ch === 0 )      $_hl = '12:00 AM';
		elseif ( $_ch < 12 )   $_hl = $_ch . ':00 AM';
		elseif ( $_ch === 12 ) $_hl = '12:00 PM';
		else                   $_hl = ($_ch - 12) . ':00 PM';
		$printPage .= '<option value="' . $_ch . '"' . ( $_ch === 6 ? ' selected' : '' ) . '>' . $_hl . '</option>';
	}
	$printPage .=     '</select>';
	$printPage .=     '<span class="time-to-label">to</span>';
	$printPage .=     '<select name="custom_end[' . $_cd . ']" class="custom-time-select custom-end-hour" disabled>';
	for ( $_ch = 0; $_ch < 24; $_ch++ ) {
		if ( $_ch === 0 )      $_hl = '12:00 AM';
		elseif ( $_ch < 12 )   $_hl = $_ch . ':00 AM';
		elseif ( $_ch === 12 ) $_hl = '12:00 PM';
		else                   $_hl = ($_ch - 12) . ':00 PM';
		$printPage .= '<option value="' . $_ch . '"' . ( $_ch === 18 ? ' selected' : '' ) . '>' . $_hl . '</option>';
	}
	$printPage .=     '</select>';
	$printPage .=   '</div>';
	$printPage .= '</div>';
}
$printPage .=   '</div>';
$printPage .= '</div>';

$printPage .=       '<div class="form-error" id="member-form-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="modal-footer">';
$printPage .=         '<button type="submit" class="schedules-btn schedules-btn-primary" id="member-form-submit">Save Member</button>';
$printPage .=         '<button type="button" class="schedules-btn modal-close-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>'; // .schedules-modal-box
$printPage .= '</div>'; // #member-modal

// ---- VIEW: Duty Assignments ----
$printPage .= '<div class="sup-view" id="sup-view-duty" data-view="duty" role="tabpanel" hidden>';

$printPage .=   '<div class="duty-toolbar toolbar">';
$printPage .=     '<div class="duty-filter-group">';
$printPage .=       '<label for="duty-date">Date</label>';
$printPage .=       '<input type="date" id="duty-date" value="' . esc_attr($today_date) . '">';
$printPage .=     '</div>';
$printPage .=     '<div class="duty-filter-group">';
$printPage .=       '<label for="duty-shift">Shift</label>';
$printPage .=       '<select id="duty-shift">';
$printPage .=         '<option value="A">Shift A (Day · Sun–Wed)</option>';
$printPage .=         '<option value="B">Shift B (Day · Wed–Sat)</option>';
$printPage .=         '<option value="C">Shift C (Night · Sun–Wed)</option>';
$printPage .=         '<option value="D">Shift D (Night · Wed–Sat)</option>';
$printPage .=       '</select>';
$printPage .=     '</div>';
$printPage .=   '</div>';

$printPage .=   '<div id="duty-msg" class="duty-msg" role="status" aria-live="polite"></div>';
$printPage .=   '<div id="duty-grid" class="duty-timeline-wrap"></div>';

$printPage .= '</div>'; // sup-view duty

// ---- VIEW: Schedule ----
$printPage .= '<div class="sup-view" id="sup-view-schedule" data-view="schedule" role="tabpanel" hidden>';
$printPage .=   '<div class="schedule-toolbar toolbar">';
$printPage .=     '<div class="schedule-member-autocomplete">';
$printPage .=       '<input type="text" id="schedule-member-search" placeholder="Search members..." autocomplete="off">';
$printPage .=       '<ul id="schedule-member-suggestions" class="schedule-suggestions" hidden></ul>';
$printPage .=     '</div>';
$printPage .=     '<select id="schedule-member-select" hidden>';
$printPage .=       '<option value="">Select member&hellip;</option>';
foreach ( $members as $m ) {
	$m_first = esc_html($m['first_name']);
	$m_last  = esc_html($m['last_name']);
	$m_disp  = $m_first && $m_last
		? ( $_user_name_format === 'last_first' ? $m_last . ', ' . $m_first : $m_first . ' ' . $m_last )
		: esc_html($m['display_name']);
	$printPage .= '<option value="' . (int) $m['user_id'] . '">' . $m_disp . ' (#' . esc_html($m['badge_number']) . ')</option>';
}
$printPage .=     '</select>';
$printPage .=     '<select id="schedule-month-picker">';
for ( $_sm = 0; $_sm < 12; $_sm++ ) {
	$_sm_ts  = strtotime( "+{$_sm} months", strtotime( date('Y-m-01') ) );
	$_sm_val = date( 'Y-m', $_sm_ts );
	$_sm_sel = $_sm === 0 ? ' selected' : '';
	$printPage .= '<option value="' . $_sm_val . '"' . $_sm_sel . '>' . date( 'F Y', $_sm_ts ) . '</option>';
}
$printPage .=     '</select>';
$printPage .=   '</div>';
$printPage .=   '<div id="schedule-calendar-wrap"></div>';

$printPage .= '</div>'; // sup-view schedule

// -- Time Off Request Popup (outside sup-view so it's never display:none) --
$printPage .= '<div id="timeoff-popup" class="timeoff-popup" hidden>';
$printPage .=   '<div class="timeoff-popup-inner">';
$printPage .=     '<div class="timeoff-popup-header">';
$printPage .=       '<h3>Request Time Off</h3>';
$printPage .=       '<button type="button" class="timeoff-popup-close" aria-label="Close">&times;</button>';
$printPage .=     '</div>';
$printPage .=     '<div class="timeoff-popup-body">';
$printPage .=       '<div class="timeoff-popup-date" id="timeoff-popup-date"></div>';
$printPage .=       '<input type="hidden" id="timeoff-date" value="">';
$printPage .=       '<input type="hidden" id="timeoff-user-id" value="">';
$printPage .=       '<input type="hidden" id="timeoff-type" value="pdo">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="timeoff-hours">Hours</label>';
$printPage .=         '<input type="number" id="timeoff-hours" min="0" max="24" step="0.5" value="12">';
$printPage .=       '</div>';
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

// ---- VIEW: Settings ----

$printPage .= '<div class="sup-view" id="sup-view-settings" data-view="settings" role="tabpanel" hidden>';

// -- Display Preferences (all roles) --
$printPage .= '<div class="config-section" id="settings-display">';
$printPage .=   '<div class="config-section-header">';
$printPage .=     '<h2>Display Preferences</h2>';
$printPage .=   '</div>';
$printPage .=   '<form id="user-settings-form" novalidate>';
$printPage .=     '<div class="form-grid-2">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="user-name-format">Member Name Format</label>';
$printPage .=         '<select id="user-name-format" name="name_format">';
$printPage .=           '<option value="first_last"' . selected( $_user_name_format, 'first_last', false ) . '>First Last</option>';
$printPage .=           '<option value="last_first"' . selected( $_user_name_format, 'last_first', false ) . '>Last, First</option>';
$printPage .=         '</select>';
$printPage .=       '</div>';
$printPage .=     '</div>';
$printPage .=     '<div class="form-error" id="user-settings-error" role="alert" aria-live="polite"></div>';
$printPage .=     '<div class="form-success" id="user-settings-success" role="status" aria-live="polite"></div>';
$printPage .=     '<div class="settings-save-row">';
$printPage .=       '<button type="submit" class="schedules-btn schedules-btn-primary" id="user-settings-submit">Save Preferences</button>';
$printPage .=     '</div>';
$printPage .=   '</form>';
$printPage .= '</div>'; // #settings-display

if ( current_user_can('admin_schedules') ) :

// -- Agency Settings (admin only) --
$_cfg_increment       = function_exists('schedules_get_config') ? schedules_get_config( 'duty_time_increment',      '60'  ) : '60';
$_cfg_sup_can_claim   = function_exists('schedules_get_config') ? schedules_get_config( 'supervisors_can_claim_ot', '0'   ) : '0';
$_cfg_min_ot_hours    = function_exists('schedules_get_config') ? schedules_get_config( 'ot_min_claim_hours',       '0'   ) : '0';
$_cfg_tier2_max       = function_exists('schedules_get_config') ? schedules_get_config( 'ot_priority_2_max', '0' ) : '0';
$_cfg_tier3_max       = function_exists('schedules_get_config') ? schedules_get_config( 'ot_priority_3_max', '0' ) : '0';

$printPage .= '<div class="config-section" id="config-app-settings">';
$printPage .=   '<div class="config-section-header">';
$printPage .=     '<h2>Agency Settings</h2>';
$printPage .=   '</div>';
$printPage .=   '<form id="app-settings-form" novalidate>';
$printPage .=     '<div class="form-grid-2">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="cfg-time-increment">Time Slot Increment</label>';
$printPage .=         '<select id="cfg-time-increment" name="duty_time_increment">';
$printPage .=           '<option value="60"' . selected( $_cfg_increment, '60', false ) . '>60 min (hourly)</option>';
$printPage .=           '<option value="30"' . selected( $_cfg_increment, '30', false ) . '>30 min (half-hour)</option>';
$printPage .=           '<option value="15"' . selected( $_cfg_increment, '15', false ) . '>15 min (quarter-hour)</option>';
$printPage .=         '</select>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="cfg-sup-can-claim">Supervisors &amp; Admins Can Claim OT</label>';
$printPage .=         '<select id="cfg-sup-can-claim" name="supervisors_can_claim_ot">';
$printPage .=           '<option value="0"' . selected( $_cfg_sup_can_claim, '0', false ) . '>No (default)</option>';
$printPage .=           '<option value="1"' . selected( $_cfg_sup_can_claim, '1', false ) . '>Yes — allow</option>';
$printPage .=         '</select>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="cfg-min-ot-hours">Minimum OT Claim (hours)</label>';
$printPage .=         '<input type="number" id="cfg-min-ot-hours" name="ot_min_claim_hours" min="0" max="12" step="1" value="' . esc_attr($_cfg_min_ot_hours) . '">';
$printPage .=         '<span class="field-hint">0 = no minimum. Members must claim at least this many consecutive hours.</span>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="cfg-tier2-max">Priority 2 — max pay rate ($/hr)</label>';
$printPage .=         '<input type="number" id="cfg-tier2-max" name="ot_priority_2_max" min="0" step="0.01" value="' . esc_attr($_cfg_tier2_max) . '">';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="cfg-tier3-max">Priority 3 — max pay rate ($/hr)</label>';
$printPage .=         '<input type="number" id="cfg-tier3-max" name="ot_priority_3_max" min="0" step="0.01" value="' . esc_attr($_cfg_tier3_max) . '">';
$printPage .=       '</div>';
$printPage .=     '</div>'; // .form-grid-2
$printPage .=     '<p class="config-note">OT priority is auto-assigned by pay rate: Tier 2 = &#36;0 up to the Tier 2 max &middot; Tier 3 = above Tier 2 up to Tier 3 max &middot; Tier 4 = above Tier 3. Tiers 1 and 5 are always manually assigned.</p>';

$printPage .=     '<div class="form-error" id="app-settings-error" role="alert" aria-live="polite"></div>';
$printPage .=     '<div class="form-success" id="app-settings-success" role="status" aria-live="polite"></div>';
$printPage .=     '<div class="settings-save-row">';
$printPage .=       '<button type="submit" class="schedules-btn schedules-btn-primary" id="app-settings-submit">Save Agency Settings</button>';
$printPage .=     '</div>';
$printPage .=   '</form>';
$printPage .= '</div>'; // #config-app-settings

// -- Shift Settings (admin only) --
$_cycle_anchor = get_option( 'schedules_cycle_anchor', '' );
$printPage .= '<div class="config-section" id="settings-shifts">';
$printPage .=   '<div class="config-section-header">';
$printPage .=     '<h2>Shift Settings</h2>';
$printPage .=   '</div>';
$printPage .=   '<p class="config-note">Members is the number assigned to each shift. Available OT slots = Max Capacity &minus; Members + Adjustments &minus; Claimed.</p>';
$printPage .=   '<div class="form-group shift-anchor-group">';
$printPage .=     '<label for="cycle-anchor-date">Rotation Cycle Anchor</label>';
$printPage .=     '<input type="date" id="cycle-anchor-date" value="' . esc_attr( $_cycle_anchor ) . '">';
$printPage .=     '<span class="field-hint">A Sunday that marks the start of "Week 1." The system alternates Week 1 / Week 2 from this date.</span>';
$printPage .=   '</div>';
$printPage .=   '<div class="shift-settings-grid">';

$shift_colors = [ 'A' => 'blue', 'B' => 'green', 'C' => 'purple', 'D' => 'orange' ];
$day_abbr     = [ 'Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa' ];

foreach ( $shifts as $shift_row ) {
	$sl         = esc_attr( $shift_row['shift_letter'] );
	$color      = isset( $shift_colors[ $sl ] ) ? $shift_colors[ $sl ] : 'navy';
	$floor_val  = (int) $shift_row['floor_count'];
	$max_val    = (int) $shift_row['max_capacity'];
	$start_val  = (int) $shift_row['start_hour'];
	$end_val    = isset( $shift_row['end_hour'] ) ? (int) $shift_row['end_hour'] : ( ( $start_val + 12 ) % 24 );
	$w1_days    = array_map( 'intval', explode( ',', $shift_row['work_days'] ) );
	$w2_raw     = $shift_row['work_days_week2'] ?? '';
	$w2_days    = ( $w2_raw !== '' && $w2_raw !== null ) ? array_map( 'intval', explode( ',', $w2_raw ) ) : [];

	$printPage .= '<div class="shift-settings-card shift-card-' . $color . '" data-shift="' . $sl . '">';
	$printPage .=   '<div class="shift-card-letter">' . $sl . '</div>';
	$printPage .=   '<div class="shift-card-controls">';

	// Start / End hours
	$printPage .=     '<div class="form-row-inline">';
	$printPage .=       '<div class="form-group">';
	$printPage .=         '<label>Start Hour</label>';
	$printPage .=         '<select name="shifts[' . $sl . '][start_hour]" class="shift-hour-select">';
	for ( $h = 0; $h < 24; $h++ ) {
		$label = sprintf( '%02d:00', $h );
		$sel   = $h === $start_val ? ' selected' : '';
		$printPage .= '<option value="' . $h . '"' . $sel . '>' . $label . '</option>';
	}
	$printPage .=         '</select>';
	$printPage .=       '</div>';
	$printPage .=       '<div class="form-group">';
	$printPage .=         '<label>End Hour</label>';
	$printPage .=         '<select name="shifts[' . $sl . '][end_hour]" class="shift-hour-select">';
	for ( $h = 0; $h < 24; $h++ ) {
		$label = sprintf( '%02d:00', $h );
		$sel   = $h === $end_val ? ' selected' : '';
		$printPage .= '<option value="' . $h . '"' . $sel . '>' . $label . '</option>';
	}
	$printPage .=         '</select>';
	$printPage .=       '</div>';
	$printPage .=     '</div>';

	// Member Count / Max Capacity
	$printPage .=     '<div class="form-row-inline">';
	$printPage .=       '<div class="form-group">';
	$printPage .=         '<label for="member-count-' . $sl . '">Members</label>';
	$printPage .=         '<input type="number" id="member-count-' . $sl . '" class="member-count-input" min="0" value="' . $floor_val . '" data-shift="' . $sl . '">';
	$printPage .=       '</div>';
	$printPage .=       '<div class="form-group">';
	$printPage .=         '<label for="max-cap-' . $sl . '">Max Capacity</label>';
	$printPage .=         '<input type="number" id="max-cap-' . $sl . '" class="max-cap-input" min="1" value="' . $max_val . '" data-shift="' . $sl . '">';
	$printPage .=       '</div>';
	$printPage .=     '</div>';

	// Week 1 days
	$printPage .=     '<div class="form-group">';
	$printPage .=       '<label>Week 1 Days</label>';
	$printPage .=       '<div class="week-days-row">';
	for ( $d = 0; $d < 7; $d++ ) {
		$checked = in_array( $d, $w1_days, true ) ? ' checked' : '';
		$printPage .= '<label class="day-check-label">';
		$printPage .=   '<input type="checkbox" name="shifts[' . $sl . '][days_week1][]" value="' . $d . '"' . $checked . '>';
		$printPage .=   '<span>' . $day_abbr[ $d ] . '</span>';
		$printPage .= '</label>';
	}
	$printPage .=       '</div>';
	$printPage .=     '</div>';

	// Week 2 days
	$printPage .=     '<div class="form-group">';
	$printPage .=       '<label>Week 2 Days</label>';
	$printPage .=       '<div class="week-days-row">';
	for ( $d = 0; $d < 7; $d++ ) {
		$checked = in_array( $d, $w2_days, true ) ? ' checked' : '';
		$printPage .= '<label class="day-check-label">';
		$printPage .=   '<input type="checkbox" name="shifts[' . $sl . '][days_week2][]" value="' . $d . '"' . $checked . '>';
		$printPage .=   '<span>' . $day_abbr[ $d ] . '</span>';
		$printPage .= '</label>';
	}
	$printPage .=       '</div>';
	$printPage .=     '</div>';

	$printPage .=   '</div>';
	$printPage .= '</div>';
}

$printPage .= '</div>'; // .shift-settings-grid

$printPage .= '<div class="settings-save-row">';
$printPage .=   '<button class="schedules-btn schedules-btn-primary" id="save-shift-settings-btn" type="button">Save Shift Settings</button>';
$printPage .= '</div>';

$printPage .= '<div class="form-error" id="settings-error" role="alert" aria-live="polite"></div>';
$printPage .= '<div class="form-success" id="settings-success" role="status" aria-live="polite"></div>';
$printPage .= '</div>'; // #settings-shifts

$_config_disciplines = function_exists('schedules_get_disciplines') ? schedules_get_disciplines( false ) : [];
$_config_positions   = function_exists('schedules_get_positions')   ? schedules_get_positions( false )   : [];
$_config_titles      = function_exists('schedules_get_titles')      ? schedules_get_titles( false )       : [];

// -- Disciplines --
$printPage .= '<div class="config-section" id="config-disciplines">';
$printPage .=   '<div class="config-section-header">';
$printPage .=     '<h2>Disciplines</h2>';
$printPage .=     '<button class="schedules-btn schedules-btn-primary" id="add-discipline-btn" type="button">+ Add Discipline</button>';
$printPage .=   '</div>';
$printPage .=   '<p class="config-note">Disciplines are skills or certifications assigned to members. Positions require a specific discipline.</p>';

$printPage .=   '<div class="table-responsive">';
$printPage .=   '<table class="schedules-table config-table" id="disciplines-table">';
$printPage .=     '<thead><tr><th class="drag-col"></th><th>Name</th><th>Status</th><th class="actions-col">Actions</th></tr></thead>';
$printPage .=     '<tbody>';
foreach ( $_config_disciplines as $_d ) {
	$did    = (int) $_d['id'];
	$dname  = esc_html( $_d['name'] );
	$dactive = (int) $_d['is_active'];
	$printPage .= '<tr data-discipline-id="' . $did . '" class="' . ( $dactive ? '' : 'row-inactive' ) . '">';
	$printPage .=   '<td class="drag-col"><span class="drag-handle">&#8942;&#8942;</span></td>';
	$printPage .=   '<td>' . $dname . '</td>';
	$printPage .=   '<td><span class="status-pill ' . ( $dactive ? 'status-active' : 'status-inactive' ) . '">' . ( $dactive ? 'Active' : 'Inactive' ) . '</span></td>';
	$printPage .=   '<td class="actions-col">';
	$printPage .=     '<button class="schedules-btn schedules-btn-small edit-discipline-btn" type="button"'
	                .   ' data-id="' . $did . '" data-name="' . esc_attr($_d['name']) . '"'
	                .   ' data-order="' . $dorder . '" data-active="' . $dactive . '"'
	                . '>Edit</button>';
	if ( $dactive ) {
		$printPage .= ' <button class="schedules-btn schedules-btn-small schedules-btn-danger delete-discipline-btn" type="button" data-id="' . $did . '" data-name="' . esc_attr($_d['name']) . '">Remove</button>';
	}
	$printPage .=   '</td>';
	$printPage .= '</tr>';
}
$printPage .=     '</tbody>';
$printPage .=   '</table>';
$printPage .=   '</div>';
$printPage .= '</div>'; // #config-disciplines

// -- Positions --
$printPage .= '<div class="config-section" id="config-positions">';
$printPage .=   '<div class="config-section-header">';
$printPage .=     '<h2>Positions</h2>';
$printPage .=     '<button class="schedules-btn schedules-btn-primary" id="add-position-btn" type="button">+ Add Position</button>';
$printPage .=   '</div>';
$printPage .=   '<p class="config-note">Positions are the seats filled each shift. Assign a required discipline to restrict who can be placed in each position.</p>';

$printPage .=   '<div class="table-responsive">';
$printPage .=   '<table class="schedules-table config-table" id="positions-table">';
$printPage .=     '<thead><tr><th class="drag-col"></th><th>Name</th><th>Required Discipline</th><th>Status</th><th class="actions-col">Actions</th></tr></thead>';
$printPage .=     '<tbody>';
foreach ( $_config_positions as $_p ) {
	$pid    = (int) $_p['id'];
	$pname  = esc_html( $_p['name'] );
	$pdisc  = $_p['discipline_name'] ? esc_html( $_p['discipline_name'] ) : '<span class="text-muted">Any</span>';
	$pactive = (int) $_p['is_active'];
	$printPage .= '<tr data-position-id="' . $pid . '" class="' . ( $pactive ? '' : 'row-inactive' ) . '">';
	$printPage .=   '<td class="drag-col"><span class="drag-handle">&#8942;&#8942;</span></td>';
	$printPage .=   '<td>' . $pname . '</td>';
	$printPage .=   '<td>' . $pdisc . '</td>';
	$printPage .=   '<td><span class="status-pill ' . ( $pactive ? 'status-active' : 'status-inactive' ) . '">' . ( $pactive ? 'Active' : 'Inactive' ) . '</span></td>';
	$printPage .=   '<td class="actions-col">';
	$printPage .=     '<button class="schedules-btn schedules-btn-small edit-position-btn" type="button"'
	                .   ' data-id="' . $pid . '" data-name="' . esc_attr($_p['name']) . '"'
	                .   ' data-disc-id="' . (int)$_p['required_discipline_id'] . '"'
	                .   ' data-order="' . $porder . '" data-active="' . $pactive . '"'
	                . '>Edit</button>';
	if ( $pactive ) {
		$printPage .= ' <button class="schedules-btn schedules-btn-small schedules-btn-danger delete-position-btn" type="button" data-id="' . $pid . '" data-name="' . esc_attr($_p['name']) . '">Remove</button>';
	}
	$printPage .=   '</td>';
	$printPage .= '</tr>';
}
$printPage .=     '</tbody>';
$printPage .=   '</table>';
$printPage .=   '</div>';
$printPage .= '</div>'; // #config-positions

// -- Titles --
$printPage .= '<div class="config-section" id="config-titles">';
$printPage .=   '<div class="config-section-header">';
$printPage .=     '<h2>Titles</h2>';
$printPage .=     '<button class="schedules-btn schedules-btn-primary" id="add-title-btn" type="button">+ Add Title</button>';
$printPage .=   '</div>';
$printPage .=   '<p class="config-note">Titles represent job classifications (e.g. Dispatcher II). Assign a title to a member as an alternative to listing individual disciplines.</p>';

$printPage .=   '<div class="table-responsive">';
$printPage .=   '<table class="schedules-table config-table" id="titles-table">';
$printPage .=     '<thead><tr><th class="drag-col"></th><th>Name</th><th>Status</th><th class="actions-col">Actions</th></tr></thead>';
$printPage .=     '<tbody>';
foreach ( $_config_titles as $_t ) {
	$tid    = (int) $_t['id'];
	$tname  = esc_html( $_t['name'] );
	$tactive = (int) $_t['is_active'];
	$printPage .= '<tr data-title-id="' . $tid . '" class="' . ( $tactive ? '' : 'row-inactive' ) . '">';
	$printPage .=   '<td class="drag-col"><span class="drag-handle">&#8942;&#8942;</span></td>';
	$printPage .=   '<td>' . $tname . '</td>';
	$printPage .=   '<td><span class="status-pill ' . ( $tactive ? 'status-active' : 'status-inactive' ) . '">' . ( $tactive ? 'Active' : 'Inactive' ) . '</span></td>';
	$printPage .=   '<td class="actions-col">';
	$printPage .=     '<button class="schedules-btn schedules-btn-small edit-title-btn" type="button"'
	                .   ' data-id="' . $tid . '" data-name="' . esc_attr($_t['name']) . '"'
	                .   ' data-order="' . $torder . '" data-active="' . $tactive . '"'
	                . '>Edit</button>';
	if ( $tactive ) {
		$printPage .= ' <button class="schedules-btn schedules-btn-small schedules-btn-danger delete-title-btn" type="button" data-id="' . $tid . '" data-name="' . esc_attr($_t['name']) . '">Remove</button>';
	}
	$printPage .=   '</td>';
	$printPage .= '</tr>';
}
$printPage .=     '</tbody>';
$printPage .=   '</table>';
$printPage .=   '</div>';
$printPage .= '</div>'; // #config-titles

endif; // admin_schedules

$printPage .= '</div>'; // sup-view settings

// -- Add Duty Assignment Modal (outside all tab views so grid.innerHTML can never destroy it) --
$printPage .= '<div id="duty-modal" class="schedules-modal" hidden role="dialog" aria-modal="true" aria-labelledby="duty-modal-title">';
$printPage .=   '<div class="schedules-modal-backdrop"></div>';
$printPage .=   '<div class="schedules-modal-box schedules-modal-box-sm">';
$printPage .=     '<button class="modal-close" aria-label="Close" type="button">&times;</button>';
$printPage .=     '<h2 id="duty-modal-title">Add Assignment</h2>';
$printPage .=     '<form id="duty-form" novalidate>';
$printPage .=       '<input type="hidden" id="duty-day-id" name="day_id" value="0">';
$printPage .=       '<input type="hidden" id="duty-date-hidden" name="date" value="">';
$printPage .=       '<input type="hidden" id="duty-shift-hidden" name="shift" value="">';
$printPage .=       '<input type="hidden" id="duty-position-id" name="position_id" value="0">';
$printPage .=       '<input type="hidden" name="assignment_id" value="">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label>Position</label>';
$printPage .=         '<div id="duty-position-display" class="duty-position-label"></div>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="duty-member">Member <span class="req">*</span></label>';
$printPage .=         '<select id="duty-member" name="user_id" required></select>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-grid-2">';
$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="duty-start">Start <span class="req">*</span></label>';
$printPage .=           '<select id="duty-start" name="start_time" required></select>';
$printPage .=         '</div>';
$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="duty-end">End <span class="req">*</span></label>';
$printPage .=           '<select id="duty-end" name="end_time" required></select>';
$printPage .=         '</div>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-error" id="duty-form-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="modal-footer">';
$printPage .=         '<button type="submit" class="schedules-btn schedules-btn-primary" id="duty-form-submit">Add Assignment</button>';
$printPage .=         '<button type="button" class="schedules-btn modal-close-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>'; // #duty-modal

// -- Config Modals (outside all sup-views to avoid CSS transform stacking context) --
if ( current_user_can('admin_schedules') ) :

$printPage .= '<div id="discipline-modal" class="schedules-modal" hidden role="dialog" aria-modal="true" aria-labelledby="discipline-modal-title">';
$printPage .=   '<div class="schedules-modal-backdrop"></div>';
$printPage .=   '<div class="schedules-modal-box schedules-modal-box-sm">';
$printPage .=     '<button class="modal-close" aria-label="Close" type="button">&times;</button>';
$printPage .=     '<h2 id="discipline-modal-title">Add Discipline</h2>';
$printPage .=     '<form id="discipline-form" novalidate>';
$printPage .=       '<input type="hidden" name="id" id="discipline-id" value="0">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="discipline-name">Name <span class="req">*</span></label>';
$printPage .=         '<input type="text" id="discipline-name" name="name" required autocomplete="off">';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group discipline-active-row" style="display:none">';
$printPage .=         '<label class="check-label"><input type="checkbox" id="discipline-active" name="is_active" value="1" checked> Active</label>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-error" id="discipline-form-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="modal-footer">';
$printPage .=         '<button type="button" class="schedules-btn schedules-btn-primary" id="discipline-form-submit">Save</button>';
$printPage .=         '<button type="button" class="schedules-btn modal-close-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>'; // #discipline-modal

$_disc_options = '<option value="0">Any (no restriction)</option>';
foreach ( $_config_disciplines as $_d ) {
	if ( ! (int)$_d['is_active'] ) continue;
	$_disc_options .= '<option value="' . (int)$_d['id'] . '">' . esc_html($_d['name']) . '</option>';
}

$printPage .= '<div id="position-modal" class="schedules-modal" hidden role="dialog" aria-modal="true" aria-labelledby="position-modal-title">';
$printPage .=   '<div class="schedules-modal-backdrop"></div>';
$printPage .=   '<div class="schedules-modal-box schedules-modal-box-sm">';
$printPage .=     '<button class="modal-close" aria-label="Close" type="button">&times;</button>';
$printPage .=     '<h2 id="position-modal-title">Add Position</h2>';
$printPage .=     '<form id="position-form" novalidate>';
$printPage .=       '<input type="hidden" name="id" id="position-id" value="0">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="position-name">Name <span class="req">*</span></label>';
$printPage .=         '<input type="text" id="position-name" name="name" required autocomplete="off">';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="position-discipline">Required Discipline</label>';
$printPage .=         '<select id="position-discipline" name="required_discipline_id">' . $_disc_options . '</select>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group position-active-row" style="display:none">';
$printPage .=         '<label class="check-label"><input type="checkbox" id="position-active" name="is_active" value="1" checked> Active</label>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-error" id="position-form-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="modal-footer">';
$printPage .=         '<button type="button" class="schedules-btn schedules-btn-primary" id="position-form-submit">Save</button>';
$printPage .=         '<button type="button" class="schedules-btn modal-close-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>'; // #position-modal

$printPage .= '<div id="title-modal" class="schedules-modal" hidden role="dialog" aria-modal="true" aria-labelledby="title-modal-title">';
$printPage .=   '<div class="schedules-modal-backdrop"></div>';
$printPage .=   '<div class="schedules-modal-box schedules-modal-box-sm">';
$printPage .=     '<button class="modal-close" aria-label="Close" type="button">&times;</button>';
$printPage .=     '<h2 id="title-modal-title">Add Title</h2>';
$printPage .=     '<form id="title-form" novalidate>';
$printPage .=       '<input type="hidden" name="id" id="title-id" value="0">';
$printPage .=       '<div class="form-group">';
$printPage .=         '<label for="title-name">Name <span class="req">*</span></label>';
$printPage .=         '<input type="text" id="title-name" name="name" required autocomplete="off">';
$printPage .=       '</div>';
$printPage .=       '<div class="form-group title-active-row" style="display:none">';
$printPage .=         '<label class="check-label"><input type="checkbox" id="title-active" name="is_active" value="1" checked> Active</label>';
$printPage .=       '</div>';
$printPage .=       '<div class="form-error" id="title-form-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="modal-footer">';
$printPage .=         '<button type="button" class="schedules-btn schedules-btn-primary" id="title-form-submit">Save</button>';
$printPage .=         '<button type="button" class="schedules-btn modal-close-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>';
$printPage .= '</div>'; // #title-modal

endif; // admin_schedules (config modals)

// ---- Block confirmation popup (for supervisor OT claiming when allowed) ----
if ( $sup_can_claim_ot ) {
	$printPage .= '<div id="block-confirm-popup" class="block-confirm-popup" hidden role="dialog" aria-modal="true" aria-label="Confirm claim">';
	$printPage .=   '<div class="popup-message"></div>';
	$printPage .=   '<div class="popup-note"></div>';
	$printPage .=   '<div class="popup-actions">';
	$printPage .=     '<button class="schedules-btn schedules-btn-primary popup-confirm" type="button">Confirm</button>';
	$printPage .=     '<button class="schedules-btn popup-cancel" type="button">Cancel</button>';
	$printPage .=   '</div>';
	$printPage .= '</div>';
}

$printPage .= '</div>'; // #schedules-supervisor-app

return do_shortcode( $printPage );
