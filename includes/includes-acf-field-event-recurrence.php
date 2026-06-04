<?php
/* Battle Plan Web Design — Custom ACF field type: Event Recurrence
 *
 * Renders the recurrence builder as ONE field with an explicit two-column layout:
 *   LEFT  = Repeats, Period, On the
 *   RIGHT = In month / On these days / Which / Day of Week / Day of month
 * then Starting / Until / Start Time / End Time below (50/50). Only the controls
 * relevant to the chosen frequency + day-mode are shown. Stores the whole rule as a
 * single JSON value; get_field() returns the rule array.
 *
 * Show/hide + layout CSS: js/script-acf-event-recurrence.js (enqueued on the events
 * editor by bp_event_recurrence_admin_assets() in includes-events.php).
 */

if ( ! defined('ABSPATH') ) exit;

add_action('acf/include_field_types', 'bp_register_event_recurrence_field_type');
function bp_register_event_recurrence_field_type() {

	if ( ! function_exists('acf_register_field_type') || ! class_exists('acf_field') ) return;

	if ( ! class_exists('bp_acf_field_event_recurrence') ) :

	class bp_acf_field_event_recurrence extends acf_field {

		function initialize() {
			$this->name     = 'event_recurrence';
			$this->label    = __('Event Recurrence', 'battleplan');
			$this->category = 'content';
			$this->defaults = array();
		}

		private function rule( $value ) {
			if ( is_array($value) ) return $value;
			if ( ! is_string($value) || $value === '' ) return array();
			$d = json_decode($value, true);
			return is_array($d) ? $d : array();
		}

		function render_field( $field ) {
			$r    = $this->rule( $field['value'] );
			$name = esc_attr( $field['name'] );

			$g   = function( $k, $def = '' ) use ( $r ) { return isset($r[$k]) ? $r[$k] : $def; };
			$sel = function( $a, $b ) { return (string) $a === (string) $b ? ' selected' : ''; };

			$freq     = $g('freq', 'weekly');
			$mode     = $g('monthmode', 'weekday');
			$weekdays = (array) $g('weekdays', array());

			// One control = one .bp-rec-f cell. data-when (weekly|month|yearly) +
			// data-mode (weekday|monthday) drive show/hide.
			$cell = function( $attrs, $label, $control ) {
				return '<div class="bp-rec-f"'.$attrs.'><label class="bp-rec-sublabel">'.$label.'</label>'.$control.'</div>';
			};

			// --- build the individual controls ---
			$interval = '<select class="bp-rec-ctl bp-rec-interval" name="'.$name.'[interval]"><option value="1"'.$sel($g('interval','1'),'1').'>Every</option><option value="2"'.$sel($g('interval','1'),'2').'>Every other</option></select>';

			$period = '<select class="bp-rec-ctl bp-rec-freq" name="'.$name.'[freq]">';
			foreach ( array('weekly'=>'Week','monthly'=>'Month','yearly'=>'Year') as $v=>$l ) $period .= '<option value="'.$v.'"'.$sel($freq,$v).'>'.$l.'</option>';
			$period .= '</select>';

			$onthe = '<select class="bp-rec-ctl bp-rec-monthmode" name="'.$name.'[monthmode]"><option value="weekday"'.$sel($mode,'weekday').'>Nth day of week (e.g. 3rd Saturday)</option><option value="monthday"'.$sel($mode,'monthday').'>Day of the month (e.g. the 15th)</option></select>';

			$month = '<select class="bp-rec-ctl bp-rec-month" name="'.$name.'[month]">';
			for ( $m=1; $m<=12; $m++ ) $month .= '<option value="'.$m.'"'.$sel($g('month','1'),$m).'>'.date('F', mktime(0,0,0,$m,1)).'</option>';
			$month .= '</select>';

			$nth = '<select class="bp-rec-ctl bp-rec-nth" name="'.$name.'[nth]">';
			foreach ( array('first'=>'First','second'=>'Second','third'=>'Third','fourth'=>'Fourth','fifth'=>'Fifth','last'=>'Last') as $v=>$l ) $nth .= '<option value="'.$v.'"'.$sel($g('nth','first'),$v).'>'.$l.'</option>';
			$nth .= '</select>';

			$weekday = '<select class="bp-rec-ctl bp-rec-weekday" name="'.$name.'[weekday]">';
			foreach ( array('sunday'=>'Sunday','monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday') as $v=>$l ) $weekday .= '<option value="'.$v.'"'.$sel($g('weekday','sunday'),$v).'>'.$l.'</option>';
			$weekday .= '</select>';

			$monthday = '<input type="number" class="bp-rec-ctl" name="'.$name.'[monthday]" min="1" max="31" value="'.esc_attr($g('monthday','1')).'">';

			$days = '<div class="bp-rec-days">';
			foreach ( array('sunday'=>'Sun','monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri','saturday'=>'Sat') as $v=>$l ) {
				$ck = in_array($v,$weekdays,true) ? ' checked' : '';
				$days .= '<label class="bp-rec-day"><input type="checkbox" name="'.$name.'[weekdays][]" value="'.$v.'"'.$ck.'> '.$l.'</label>';
			}
			$days .= '</div>';

			// --- layout ---
			echo '<div class="bp-rec" data-freq="'.esc_attr($freq).'" data-mode="'.esc_attr($mode).'">';

			echo '<div class="bp-rec-cols">';

				echo '<div class="bp-rec-col bp-rec-left">';
					echo $cell( '', 'Repeats', $interval );
					echo $cell( '', 'Period', $period );
					echo $cell( ' data-when="month"', 'On the', $onthe );
				echo '</div>';

				echo '<div class="bp-rec-col bp-rec-right">';
					echo $cell( ' data-when="yearly"', 'In month', $month );
					echo $cell( ' data-when="weekly"', 'On these days', $days );
					echo $cell( ' data-when="month" data-mode="weekday"', 'Which', $nth );
					echo $cell( ' data-when="month" data-mode="weekday"', 'Day of Week', $weekday );
					echo $cell( ' data-when="month" data-mode="monthday"', 'Day of month', $monthday );
				echo '</div>';

			echo '</div>'; // .bp-rec-cols

			echo '<div class="bp-rec-bottom">';
				echo $cell( '', 'Starting', '<input type="date" name="'.$name.'[start]" value="'.esc_attr($g('start','')).'">' );
				echo $cell( '', 'Until <em>(blank = ongoing)</em>', '<input type="date" name="'.$name.'[until]" value="'.esc_attr($g('until','')).'">' );
				echo $cell( '', 'Start Time', '<input type="time" name="'.$name.'[s_time]" value="'.esc_attr($g('s_time','')).'">' );
				echo $cell( '', 'End Time', '<input type="time" name="'.$name.'[e_time]" value="'.esc_attr($g('e_time','')).'">' );
			echo '</div>';

			echo '</div>'; // .bp-rec
		}

		// Sanitize the posted sub-fields into a single JSON rule.
		function update_value( $value, $post_id, $field ) {
			if ( ! is_array($value) ) return '';

			$days  = array('sunday','monday','tuesday','wednesday','thursday','friday','saturday');
			$clean = array(
				'freq'      => in_array(($value['freq'] ?? ''), array('weekly','monthly','yearly'), true) ? $value['freq'] : 'weekly',
				'interval'  => ((int)($value['interval'] ?? 1)) === 2 ? 2 : 1,
				'weekdays'  => array(),
				'monthmode' => (($value['monthmode'] ?? '') === 'monthday') ? 'monthday' : 'weekday',
				'nth'       => in_array(($value['nth'] ?? ''), array('first','second','third','fourth','fifth','last'), true) ? $value['nth'] : 'first',
				'weekday'   => in_array(($value['weekday'] ?? ''), $days, true) ? $value['weekday'] : 'sunday',
				'monthday'  => min(31, max(1, (int)($value['monthday'] ?? 1))),
				'month'     => min(12, max(1, (int)($value['month'] ?? 1))),
				'start'     => $this->clean_date($value['start'] ?? ''),
				'until'     => $this->clean_date($value['until'] ?? ''),
				's_time'    => $this->clean_time($value['s_time'] ?? ''),
				'e_time'    => $this->clean_time($value['e_time'] ?? ''),
			);
			if ( ! empty($value['weekdays']) && is_array($value['weekdays']) ) {
				foreach ( $value['weekdays'] as $d ) if ( in_array($d, $days, true) ) $clean['weekdays'][] = $d;
			}
			return wp_json_encode( $clean );
		}

		private function clean_date( $v ) { $t = strtotime((string)$v); return $t ? date('Y-m-d', $t) : ''; }
		private function clean_time( $v ) { $v = trim((string)$v); if ($v === '') return ''; $t = strtotime($v); return $t ? date('H:i', $t) : ''; }

		function format_value( $value, $post_id, $field ) {
			return $this->rule( $value );
		}
	}

	endif;

	acf_register_field_type( 'bp_acf_field_event_recurrence' );
}
