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

$today_date = date('Y-m-d');

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
$printPage .=   '<div class="schedules-user-info">';
$printPage .=     '<span class="user-name">' . esc_html($full_name) . '</span>';
$printPage .=     '<span class="user-badge">Badge #' . esc_html($badge) . '</span>';
$printPage .=   '</div>';
$printPage .=   '<button id="schedules-logout-btn" class="schedules-btn schedules-btn-ghost" type="button">Sign Out</button>';
$printPage .= '</header>';

// ---- Supervisor Nav ----
$printPage .= '<nav class="schedules-supervisor-nav" role="tablist" aria-label="Supervisor views">';
$printPage .=   '<button class="sup-tab active" data-view="calendar" role="tab" aria-selected="true" aria-controls="sup-view-calendar">Calendar</button>';
$printPage .=   '<button class="sup-tab" data-view="claims"   role="tab" aria-selected="false" aria-controls="sup-view-claims">Claims Log</button>';
$printPage .=   '<button class="sup-tab" data-view="members"  role="tab" aria-selected="false" aria-controls="sup-view-members">Members</button>';
$printPage .=   '<button class="sup-tab" data-view="settings" role="tab" aria-selected="false" aria-controls="sup-view-settings">Shift Settings</button>';
$printPage .= '</nav>';

// ---- VIEW: Calendar ----
$printPage .= '<div class="sup-view active" id="sup-view-calendar" data-view="calendar" role="tabpanel">';

// Week tabs
$printPage .= '<div class="schedules-week-tabs" role="tablist" aria-label="Schedule weeks">';
for ( $w = 1; $w <= $max_week; $w++ ) {
	$active   = $w === 1 ? ' active' : '';
	$selected = $w === 1 ? 'true' : 'false';
	$s_day    = ( ($w - 1) * 7 ) + 1;
	$e_day    = $w * 7;
	$printPage .= '<button class="week-tab' . $active . '" data-week="' . $w . '" role="tab" aria-selected="' . $selected . '" aria-controls="sup-week-panel-' . $w . '">';
	$printPage .=   'Week ' . $w . ' <span class="week-range unique">Days ' . $s_day . '–' . $e_day . '</span>';
	$printPage .= '</button>';
}
$printPage .= '</div>';

// Calendar
$printPage .= '<div class="schedules-calendar schedules-supervisor-calendar">';

if ( empty($weeks) ) {
	$printPage .= '<div class="schedules-empty"><p>No schedule days found in the next 28 days.</p></div>';
} else {
	foreach ( $weeks as $week_num => $week_days ) {
		$hidden    = $week_num === 1 ? '' : ' hidden';
		$printPage .= '<div class="schedules-week" id="sup-week-panel-' . $week_num . '" data-week="' . $week_num . '" role="tabpanel"' . $hidden . '>';

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
				$printPage .=   '<div class="shift-group-header">';
				$printPage .=     '<div class="shift-label">Shift ' . esc_html($shift_data['shift_letter']) . ' &middot; ' . esc_html($shift_type_l) . '</div>';
				$printPage .=     '<button class="schedules-btn schedules-btn-small add-slot-btn" type="button" data-day-id="' . $day_id . '" data-shift="' . $shift_letter . '" data-date="' . esc_attr($date) . '">';
				$printPage .=       '+ Add Slot';
				$printPage .=     '</button>';
				$printPage .=   '</div>';

				// Inline add-slot form (hidden by default, toggled per day by JS)
				$printPage .= '<div class="add-slot-form" id="add-slot-form-' . $day_id . '" hidden>';
				$printPage .=   '<textarea class="add-slot-reason" placeholder="Reason for opening slot (e.g. callout, extra coverage)" rows="2"></textarea>';
				$printPage .=   '<div class="add-slot-actions">';
				$printPage .=     '<button class="schedules-btn schedules-btn-primary add-slot-confirm" type="button" data-day-id="' . $day_id . '">Open Slot</button>';
				$printPage .=     '<button class="schedules-btn schedules-btn-ghost add-slot-cancel" type="button">Cancel</button>';
				$printPage .=   '</div>';
				$printPage .=   '<div class="add-slot-msg" role="status" aria-live="polite"></div>';
				$printPage .= '</div>';

				$printPage .=   '<div class="shift-blocks">';

				foreach ( $shift_data['blocks'] as $block ) {
					$block_id     = (int) $block['id'];
					$available    = (int) $block['available'];
					$claims_count = isset($block['claims_count']) ? (int) $block['claims_count'] : 0;
					$time_str     = function_exists('schedules_format_block_time')
						? schedules_format_block_time($block['start_hour'], $block['end_hour'])
						: $block['start_hour'] . '–' . $block['end_hour'];

					if ( $available <= 0 ) {
						$state_class = 'full';
					} else {
						$state_class = 'available';
						if ( $available <= 2 ) $state_class .= ' limited';
					}

					$printPage .= '<div class="time-block ' . $state_class . ' supervisor-block" data-block-id="' . $block_id . '" data-available="' . $available . '">';
					$printPage .=   '<span class="time-range">' . esc_html($time_str) . '</span>';
					$printPage .=   '<span class="block-status">' . $available . ' open</span>';
					$printPage .=   '<span class="block-claims-count">' . $claims_count . ' claimed</span>';
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
$printPage .=   '<div class="claims-filters">';
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
$printPage .=     '<button id="claims-load-btn" class="schedules-btn schedules-btn-primary" type="button">Load Claims</button>';
$printPage .=   '</div>';
$printPage .=   '<div id="claims-results" class="claims-table-wrap">';
$printPage .=     '<p class="claims-hint">Select a date and click Load Claims.</p>';
$printPage .=   '</div>';
$printPage .= '</div>'; // sup-view claims

// ---- VIEW: Members ----
$printPage .= '<div class="sup-view" id="sup-view-members" data-view="members" role="tabpanel" hidden>';
$printPage .=   '<div class="members-toolbar">';
$printPage .=     '<h2>Scheduling Members</h2>';
$printPage .=     '<button id="add-member-btn" class="schedules-btn schedules-btn-primary" type="button">+ Add Member</button>';
$printPage .=   '</div>';
$printPage .=   '<div id="members-table-wrap">';

if ( empty($members) ) {
	$printPage .= '<p class="members-empty">No members found. Add your first member above.</p>';
} else {
	$printPage .= '<div class="table-responsive">';
	$printPage .= '<table class="schedules-table members-table">';
	$printPage .=   '<thead>';
	$printPage .=     '<tr>';
	$printPage .=       '<th>Badge #</th>';
	$printPage .=       '<th>Name</th>';
	$printPage .=       '<th>Shift</th>';
	$printPage .=       '<th>Discipline</th>';
	$printPage .=       '<th>Priority</th>';
	$printPage .=       '<th>Role</th>';
	$printPage .=       '<th>Email</th>';
	$printPage .=       '<th class="actions-col">Actions</th>';
	$printPage .=     '</tr>';
	$printPage .=   '</thead>';
	$printPage .=   '<tbody>';

	foreach ( $members as $member ) {
		$uid     = (int) $member['user_id'];
		$m_badge = esc_html($member['badge_number']);
		$m_name  = esc_html($member['display_name']);
		$m_shift = esc_html($member['shift']);
		$m_disc  = esc_html($member['discipline']);
		$m_prio  = esc_html($member['priority']);
		$m_role  = esc_html($member['role']);
		$m_email = esc_html($member['email']);

		$printPage .= '<tr data-user-id="' . $uid . '">';
		$printPage .=   '<td class="badge-col"><strong>' . $m_badge . '</strong></td>';
		$printPage .=   '<td>' . $m_name . '</td>';
		$printPage .=   '<td>' . ( $m_shift ? '<span class="shift-pill shift-' . strtolower($m_shift) . '">Shift ' . $m_shift . '</span>' : '<span class="shift-pill shift-none">Non-Floor</span>' ) . '</td>';
		$disc_parts = $m_disc ? array_filter( array_map( 'trim', explode( ',', $m_disc ) ) ) : [];
		$printPage .=   '<td>' . ( $disc_parts ? esc_html( implode( ', ', array_map( 'ucfirst', $disc_parts ) ) ) : '—' ) . '</td>';
		$printPage .=   '<td>' . ( $m_prio ? '<span class="priority-pill priority-' . $m_prio . '">' . ucfirst($m_prio) . '</span>' : '—' ) . '</td>';
		$printPage .=   '<td>' . ucfirst($m_role) . '</td>';
		$printPage .=   '<td class="email-col">' . $m_email . '</td>';
		$printPage .=   '<td class="actions-col">';
		$printPage .=     '<button class="schedules-btn schedules-btn-small edit-member-btn" type="button"';
		$printPage .=       ' data-user-id="' . $uid . '"';
		$printPage .=       ' data-first-name="' . esc_attr($member['first_name']) . '"';
		$printPage .=       ' data-last-name="' . esc_attr($member['last_name']) . '"';
		$printPage .=       ' data-badge="' . esc_attr($member['badge_number']) . '"';
		$printPage .=       ' data-email="' . esc_attr($member['email']) . '"';
		$printPage .=       ' data-shift="' . esc_attr($member['shift']) . '"';
		$printPage .=       ' data-discipline="' . esc_attr($member['discipline']) . '"';
		$printPage .=       ' data-priority="' . esc_attr($member['priority']) . '"';
		$printPage .=       ' data-role="' . esc_attr($member['role']) . '"';
		$printPage .=     '>Edit</button>';
		$printPage .=     ' <button class="schedules-btn schedules-btn-small schedules-btn-danger delete-member-btn" type="button" data-user-id="' . $uid . '" data-name="' . esc_attr($member['display_name']) . '">Remove</button>';
		$printPage .=   '</td>';
		$printPage .= '</tr>';
	}

	$printPage .=   '</tbody>';
	$printPage .= '</table>';
	$printPage .= '</div>'; // .table-responsive
}

$printPage .= '</div>'; // #members-table-wrap

// ---- Add/Edit Member Modal ----
$printPage .= '<div id="member-modal" class="schedules-modal" hidden role="dialog" aria-modal="true" aria-labelledby="member-modal-title">';
$printPage .=   '<div class="schedules-modal-backdrop"></div>';
$printPage .=   '<div class="schedules-modal-box">';
$printPage .=     '<button class="modal-close" aria-label="Close modal" type="button">&times;</button>';
$printPage .=     '<h2 id="member-modal-title">Add Member</h2>';
$printPage .=     '<form id="member-form" novalidate>';
$printPage .=       '<input type="hidden" name="user_id" id="member-user-id" value="0">';

$printPage .=       '<div class="form-grid-2">';

$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="member-first-name">First Name <span class="req">*</span></label>';
$printPage .=           '<input type="text" id="member-first-name" name="first_name" required autocomplete="off">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="member-last-name">Last Name <span class="req">*</span></label>';
$printPage .=           '<input type="text" id="member-last-name" name="last_name" required autocomplete="off">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="member-badge">Badge Number <span class="req">*</span></label>';
$printPage .=           '<input type="text" id="member-badge" name="badge_number" required autocomplete="off" inputmode="numeric">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="member-email">Email <span class="req">*</span></label>';
$printPage .=           '<input type="email" id="member-email" name="email" required autocomplete="off">';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group member-password-row">';
$printPage .=           '<label for="member-password">Password <span class="req new-only">*</span></label>';
$printPage .=           '<input type="password" id="member-password" name="password" autocomplete="new-password" minlength="8">';
$printPage .=           '<span class="field-hint new-only">Required for new members. Leave blank to keep existing.</span>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="member-shift">Shift</label>';
$printPage .=           '<select id="member-shift" name="shift">';
$printPage .=             '<option value="">Non-Floor (No Shift)</option>';
$printPage .=             '<option value="A">Shift A (Day · Sun–Wed)</option>';
$printPage .=             '<option value="B">Shift B (Day · Wed–Sat)</option>';
$printPage .=             '<option value="C">Shift C (Night · Sun–Wed)</option>';
$printPage .=             '<option value="D">Shift D (Night · Wed–Sat)</option>';
$printPage .=           '</select>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="member-priority">Priority <span class="req">*</span></label>';
$printPage .=           '<select id="member-priority" name="priority" required>';
$printPage .=             '<option value="">Select Priority</option>';
$printPage .=             '<option value="floor">Floor (28 days)</option>';
$printPage .=             '<option value="green">Green (21 days)</option>';
$printPage .=             '<option value="yellow">Yellow (14 days)</option>';
$printPage .=             '<option value="red">Red (7 days)</option>';
$printPage .=           '</select>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group">';
$printPage .=           '<label for="member-role">Role <span class="req">*</span></label>';
$printPage .=           '<select id="member-role" name="is_supervisor">';
$printPage .=             '<option value="0">Member</option>';
$printPage .=             '<option value="1">Supervisor</option>';
$printPage .=           '</select>';
$printPage .=         '</div>';

$printPage .=         '<div class="form-group form-group-full">';
$printPage .=           '<label>Discipline</label>';
$printPage .=           '<div class="discipline-checkboxes" id="member-discipline-group">';
$printPage .=             '<label class="check-label"><input type="checkbox" name="discipline[]" value="phones"> Phones</label>';
$printPage .=             '<label class="check-label"><input type="checkbox" name="discipline[]" value="radio"> Radio</label>';
$printPage .=             '<label class="check-label"><input type="checkbox" name="discipline[]" value="control"> Control</label>';
$printPage .=           '</div>';
$printPage .=         '</div>';

$printPage .=       '</div>'; // .form-grid-2

$printPage .=       '<div class="form-error" id="member-form-error" role="alert" aria-live="polite"></div>';
$printPage .=       '<div class="modal-footer">';
$printPage .=         '<button type="submit" class="schedules-btn schedules-btn-primary" id="member-form-submit">Save Member</button>';
$printPage .=         '<button type="button" class="schedules-btn schedules-btn-ghost modal-close-btn">Cancel</button>';
$printPage .=       '</div>';
$printPage .=     '</form>';
$printPage .=   '</div>'; // .schedules-modal-box
$printPage .= '</div>'; // #member-modal

$printPage .= '</div>'; // sup-view members

// ---- VIEW: Settings ----
$printPage .= '<div class="sup-view" id="sup-view-settings" data-view="settings" role="tabpanel" hidden>';
$printPage .=   '<div class="settings-header">';
$printPage .=     '<h2>Shift Floor Counts</h2>';
$printPage .=     '<p class="settings-note">The floor count is the number of members permanently assigned to each shift. Available OT slots = Max Capacity − Floor Count + Adjustments − Claimed.</p>';
$printPage .=   '</div>';

$printPage .=   '<div class="shift-settings-grid">';

$shift_colors = [ 'A' => 'blue', 'B' => 'green', 'C' => 'purple', 'D' => 'orange' ];
$shift_hours  = [
	'A' => '06:00 – 18:00',
	'B' => '06:00 – 18:00',
	'C' => '18:00 – 06:00',
	'D' => '18:00 – 06:00',
];

foreach ( $shifts as $shift_row ) {
	$sl         = esc_attr($shift_row['shift_letter']);
	$color      = isset($shift_colors[$sl]) ? $shift_colors[$sl] : 'navy';
	$hours_label = isset($shift_hours[$sl]) ? $shift_hours[$sl] : '';
	$days_label  = isset($shift_day_labels[$sl]) ? $shift_day_labels[$sl] : '';
	$floor_val   = (int) $shift_row['floor_count'];
	$max_val     = (int) $shift_row['max_capacity'];
	$type_label  = function_exists('schedules_shift_type_label') ? schedules_shift_type_label($sl) : '';

	$printPage .= '<div class="shift-settings-card shift-card-' . $color . '">';
	$printPage .=   '<div class="shift-card-letter">' . $sl . '</div>';
	$printPage .=   '<div class="shift-card-info">';
	$printPage .=     '<span class="shift-card-type">' . esc_html($type_label) . '</span>';
	$printPage .=     '<span class="shift-card-hours">' . esc_html($hours_label) . '</span>';
	$printPage .=     '<span class="shift-card-days">' . esc_html($days_label) . '</span>';
	$printPage .=   '</div>';
	$printPage .=   '<div class="shift-card-controls">';
	$printPage .=     '<div class="form-group">';
	$printPage .=       '<label for="floor-count-' . $sl . '">Floor Count</label>';
	$printPage .=       '<input type="number" id="floor-count-' . $sl . '" class="floor-count-input" min="0" max="' . $max_val . '" value="' . $floor_val . '" data-shift="' . $sl . '">';
	$printPage .=     '</div>';
	$printPage .=     '<div class="form-group">';
	$printPage .=       '<label>Max Capacity</label>';
	$printPage .=       '<span class="max-cap-display">' . $max_val . '</span>';
	$printPage .=     '</div>';
	$printPage .=   '</div>';
	$printPage .= '</div>';
}

$printPage .= '</div>'; // .shift-settings-grid

$printPage .= '<div class="settings-save-row">';
$printPage .=   '<button class="schedules-btn schedules-btn-primary" id="save-all-floor-counts-btn" type="button">Save All Floor Counts</button>';
$printPage .= '</div>';

$printPage .= '<div class="form-error" id="settings-error" role="alert" aria-live="polite"></div>';
$printPage .= '<div class="form-success" id="settings-success" role="status" aria-live="polite"></div>';
$printPage .= '</div>'; // sup-view settings

$printPage .= '</div>'; // #schedules-supervisor-app

return do_shortcode( $printPage );
