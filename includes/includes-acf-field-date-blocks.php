<?php
/* Battle Plan Web Design — Custom ACF field type: Date Blocks
 *
 * A purpose-built, fixed-shape "repeater" for non-consecutive event date ranges,
 * built on ACF's free field-type API (acf_register_field_type) — no ACF Pro
 * Repeater required. Each block = start date, end date, start time, end time.
 *
 * Stored as a single JSON value in the field's own meta key. get_field() returns:
 *   [ ['s_date'=>'Y-m-d','e_date'=>'Y-m-d','s_time'=>'H:i','e_time'=>'H:i'], ... ]
 *
 * This is intentionally NOT a general nested repeater (that would be reimplementing
 * ACF Pro). It is one fixed-shape field, the same way functions-forms.php is a
 * purpose-built form handler rather than a form-builder framework.
 */

if ( ! defined('ABSPATH') ) exit;

add_action('acf/include_field_types', 'bp_register_date_blocks_field_type');
function bp_register_date_blocks_field_type() {

	if ( ! function_exists('acf_register_field_type') || ! class_exists('acf_field') ) return;

	if ( ! class_exists('bp_acf_field_date_blocks') ) :

	class bp_acf_field_date_blocks extends acf_field {

		function initialize() {
			$this->name     = 'date_blocks';
			$this->label    = __('Date Blocks', 'battleplan');
			$this->category = 'content';
			$this->defaults = array();
		}

		// Decode the stored JSON string into an array of block rows.
		private function rows( $value ) {
			if ( is_array($value) ) return $value;
			if ( ! is_string($value) || $value === '' ) return array();
			$decoded = json_decode($value, true);
			return is_array($decoded) ? $decoded : array();
		}

		function render_field( $field ) {
			$rows = $this->rows( $field['value'] );
			if ( empty($rows) ) $rows = array( array() ); // one empty starter row
			$name = esc_attr( $field['name'] );

			echo '<div class="bp-date-blocks" data-next="' . count($rows) . '">';

				echo '<div class="bp-db-rows">';
				foreach ( $rows as $i => $row ) {
					echo $this->render_row( $name, $i, $row, false );
				}
				echo '</div>';

				// Hidden template row — inputs are disabled so they never submit.
				echo '<div class="bp-db-template" hidden>';
				echo $this->render_row( $name, '__i__', array(), true );
				echo '</div>';

				echo '<button type="button" class="button bp-db-add">+ Add Date Block</button>';

			echo '</div>';
		}

		private function render_row( $name, $i, $row, $template ) {
			$s_date = isset($row['s_date']) ? esc_attr($row['s_date']) : '';
			$e_date = isset($row['e_date']) ? esc_attr($row['e_date']) : '';
			$s_time = isset($row['s_time']) ? esc_attr($row['s_time']) : '';
			$e_time = isset($row['e_time']) ? esc_attr($row['e_time']) : '';
			$dis    = $template ? ' disabled' : '';
			$base   = $name . '[' . $i . ']';

			$h  = '<div class="bp-db-row">';
			$h .= '<span class="bp-db-field"><label>Start date</label><input type="date" name="' . $base . '[s_date]" value="' . $s_date . '"' . $dis . '></span>';
			$h .= '<span class="bp-db-field"><label>End date</label><input type="date" name="' . $base . '[e_date]" value="' . $e_date . '"' . $dis . '></span>';
			$h .= '<span class="bp-db-field"><label>Start time</label><input type="time" name="' . $base . '[s_time]" value="' . $s_time . '"' . $dis . '></span>';
			$h .= '<span class="bp-db-field"><label>End time</label><input type="time" name="' . $base . '[e_time]" value="' . $e_time . '"' . $dis . '></span>';
			$h .= '<button type="button" class="button bp-db-remove" aria-label="Remove date block">&times;</button>';
			$h .= '</div>';
			return $h;
		}

		// Sanitize posted rows and store as a single JSON string.
		function update_value( $value, $post_id, $field ) {
			if ( ! is_array($value) ) return '';
			$clean = array();
			foreach ( $value as $row ) {
				if ( ! is_array($row) ) continue;
				$s_date = $this->clean_date( $row['s_date'] ?? '' );
				if ( ! $s_date ) continue; // a block with no start date is dropped
				$e_date = $this->clean_date( $row['e_date'] ?? '' );
				$clean[] = array(
					's_date' => $s_date,
					'e_date' => ( $e_date && $e_date >= $s_date ) ? $e_date : $s_date,
					's_time' => $this->clean_time( $row['s_time'] ?? '' ),
					'e_time' => $this->clean_time( $row['e_time'] ?? '' ),
				);
			}
			// Keep blocks in chronological order regardless of entry order.
			usort( $clean, function( $a, $b ) { return strcmp( $a['s_date'], $b['s_date'] ); } );
			return $clean ? wp_json_encode( $clean ) : '';
		}

		private function clean_date( $v ) {
			$t = strtotime( (string) $v );
			return $t ? date('Y-m-d', $t) : '';
		}
		private function clean_time( $v ) {
			$v = trim( (string) $v );
			if ( $v === '' ) return '';
			$t = strtotime( $v );
			return $t ? date('H:i', $t) : '';
		}

		// Decode JSON to an array for get_field() / the_field().
		function format_value( $value, $post_id, $field ) {
			return $this->rows( $value );
		}

		function input_admin_enqueue_scripts() {
			$ver = defined('_BP_VERSION') ? _BP_VERSION : false;
			wp_enqueue_script(
				'bp-date-blocks',
				get_template_directory_uri() . '/js/script-acf-date-blocks.min.js',
				array(),
				$ver,
				true
			);
			wp_add_inline_style( 'acf-input',
				'.bp-db-row{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin-bottom:8px}'
				. '.bp-db-field{display:flex;flex-direction:column}'
				. '.bp-db-field label{font-size:11px;color:#666;margin-bottom:2px}'
				. '.bp-db-add{margin-top:4px}'
				. '.bp-db-remove{color:#a00;font-size:16px;line-height:1}'
			);
		}
	}

	endif;

	acf_register_field_type( 'bp_acf_field_date_blocks' );
}
