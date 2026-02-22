<?php
/* Battle Plan Web Design Functions: Admin Columns */


/*--------------------------------------------------------------
# Disable ACP Storage (optional if still installed)
--------------------------------------------------------------*/
add_filter('acp/storage_model/use_database', '__return_false');
add_filter('acp/storage/file/directory', function(){
	return get_template_directory() . '/acp-settings';
});


/*--------------------------------------------------------------
# Remove Yoast Columns
--------------------------------------------------------------*/
add_filter('manage_page_posts_columns', function($columns){

	foreach($columns as $key => $value)
		if(strpos($key, 'wpseo') === 0)
			unset($columns[$key]);

	return $columns;

}, 999);


/*--------------------------------------------------------------
# Handle Sorting By URL Var
--------------------------------------------------------------*/
add_filter('request', function($vars){
	if (!is_admin()) return $vars;

	if (!empty($vars['orderby'])) {

		switch ($vars['orderby']) {

			case 'bp-title':
				$vars['orderby'] = 'title';
				break;

			case 'bp-modified':
				$vars['orderby'] = 'modified';
				break;

			case 'bp-post-id':
				$vars['orderby'] = 'ID';
				break;

			case 'bp-menu_order':
				$vars['orderby'] = 'menu_order';
				break;
		}
	}

	return $vars;
});



/*--------------------------------------------------------------
# Admin Columns Engine
--------------------------------------------------------------*/
function bp_match($value, $map, $default = '') {

	if (array_key_exists($value, $map)) {

		$result = $map[$value];

		return is_callable($result)
			? $result()
			: $result;
	}

	return is_callable($default)
		? $default()
		: $default;
}

function bp_render_meta($id, $cols, $col, $pt) {

	$meta_key = $cols[$col]['meta_key'];
	$value    = get_post_meta($id, $meta_key, true);

	$display_value = $value;

	if ($value && is_numeric($value) && get_post($value)) {
		$display_value = get_the_title($value);
	}

	$type = $cols[$col]['type'] ?? 'value';

	if ($type === 'date' && $value) {

		$date = null;

		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			$date = DateTime::createFromFormat('Y-m-d', $value);
		}
		elseif (preg_match('/^\d{8}$/', $value)) {
			$date = DateTime::createFromFormat('Ymd', $value);
		}

		elseif (preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) {
		    try {
			   $date = new DateTime($value);
		    } catch (Exception $e) {
			   $date = null;
		    }
		}

		if ($date) {
			$display_value = $date->format('F j, Y');
		}
	}

	if ($value && is_numeric($value) && (
		strpos($meta_key,'price')!==false ||
		strpos($meta_key,'deposit')!==false ||
		strpos($meta_key,'cost')!==false ||
		strpos($meta_key,'amount')!==false
	)) {
		$display_value = '$'.number_format($value,0);
	}

	if($pt === 'events'){

		$start_date = get_post_meta($id,'start_date',true);
		$start_time = get_post_meta($id,'start_time',true);
		$end_date   = get_post_meta($id,'end_date',true);
		$end_time   = get_post_meta($id,'end_time',true);

		if($meta_key === 'start_date'){
			$dt = new DateTime(trim($start_date.' '.$start_time));
			$display_value =
				$dt->format('F j, Y').
				($start_time ? '<br>'.$dt->format('g:ia') : '');
		}

		if($meta_key === 'end_date'){
			$display_date = $end_date ?: $start_date;
			$dt = new DateTime(trim($display_date.' '.$end_time));
			$display_value =
				$dt->format('F j, Y').
				($end_time ? '<br>'.$dt->format('g:ia') : '');
		}
	}

	if($meta_key === 'testimonial_platform'){

		$logos = [
			'Google'   => get_template_directory_uri().'/common/logos/google.webp',
			'Facebook' => get_template_directory_uri().'/common/logos/facebook.webp',
		];

		if(isset($logos[$value])){
			$display_value =
				'<img src="'.$logos[$value].'" style="height:20px;width:auto;" alt="'.$value.'">';
		}
	}

	if (!empty($cols[$col]['inline_edit'])) {

		return '<span class="bp-inline-edit"
			data-post-id="'.esc_attr($id).'"
			data-meta-key="'.esc_attr($meta_key).'"
			data-raw-value="'.esc_attr($value).'"
			data-field-type="'.esc_attr($type).'"
			style="cursor:pointer; border-bottom:var(--inline-edit);"
			title="Click to edit">'.wp_kses_post($display_value ?: '—').'</span>';
	}

	return wp_kses_post($display_value);
}


/*--------------------------------------------------------------
# Admin Columns Engine
--------------------------------------------------------------*/
function bp_admin_columns($config){

	$pt   = $config['post_type'];
	$cols = $config['columns'];

	/* Columns Layout */
	if($pt === 'attachment') {

		add_filter('manage_upload_columns', function($existing) use ($cols){

			$new = [
				'cb'     => $existing['cb'],
			];

			// Add your custom columns in the order you define
			foreach($cols as $key => $c){

				// If this is the native parent column,
				// use WP's original label instead
				if($key === 'bp-media_parent' && isset($existing['parent'])){
					$new['parent'] = $existing['parent'];
					continue;
				}

				$new[$key] = $c['label'] ?? ucfirst($key);
			}

			return $new;

		}, 999);



	} else {

		add_filter("manage_{$pt}_posts_columns", function($existing) use ($cols){

			$new = ['cb' => $existing['cb'] ?? ''];

			foreach($cols as $key => $c)
				$new[$key] = $c['label'] ?? ucfirst($key);

			return $new;

		}, 999);
	}

	/* Column Output */
	if($pt === 'attachment') {

		add_action('manage_media_custom_column', function($col, $id) use ($cols){

			if(!isset($cols[$col])) return;

			$type = $cols[$col]['type'];

			echo bp_match($type, [

				'bp-post-id' => $id,

				'bp-featured-image' => fn() =>
					wp_get_attachment_image($id, [130,130]),

				'bp-title' => (function() use ($id) {
					return '<a href="'.get_edit_post_link($id).'" target="_blank"><b style="color: var(--main-blue); font-size:115%">'.get_the_title($id).'</b></a><br>/'.basename(get_attached_file($id));
				}),

				'bp-modified' => get_the_modified_date('', $id),

				'value' => fn() => bp_render_meta($id, $cols, $col, 'attachment'),
				'date'  => fn() => bp_render_meta($id, $cols, $col, 'attachment'),
				'number'=> fn() => bp_render_meta($id, $cols, $col, 'attachment'),

				'bp-media_dimensions' => (function() use ($id){
					$meta = wp_get_attachment_metadata($id);
					return isset($meta['width'])
						? $meta['width'].'×'.$meta['height']
						: '';
				}),

				'bp-taxonomy' => (function() use ($id, $cols, $col){
					$terms = get_the_terms($id, $cols[$col]['taxonomy']);
					$taxonomy = $cols[$col]['taxonomy'];

					$output = '';

					// Display current terms as links
					if ($terms && !is_wp_error($terms)) {
						$links = [];
						foreach ($terms as $term) {
							$filter_url = add_query_arg([
								$col => $term->term_id,
							], admin_url('upload.php'));
							$links[] = '<a href="'.esc_url($filter_url).'">'.wp_kses_post($term->name).'</a>';
						}
						$output = implode(', ', $links);
					} else {
						$output = '<span style="color:#999;">None</span>';
					}

					// Add edit button if inline_edit is enabled
					if (!empty($cols[$col]['inline_edit'])) {
						$output .= ' <button type="button" class="button-link bp-taxonomy-edit"
							data-post-id="'.esc_attr($id).'"
							data-taxonomy="'.esc_attr($taxonomy).'"
							data-column="'.esc_attr($col).'"
							style="color:#2271b1; cursor:pointer; text-decoration:none; border:none; background:none; padding:0; margin-left:5px;"
							title="Edit terms">✎</button>';
					}

					return $output;
				}),

			], '');

		}, 10, 2);

	} else {

		add_action("manage_{$pt}_posts_custom_column", function($col, $id) use ($cols, $pt){

			if(!isset($cols[$col])) return;

			$type = $cols[$col]['type'];

			echo bp_match($type, [

				'bp-post-id' => $id,

				'bp-title' => (function() use ($id) {
					$status = get_post_status($id);
					$status_label = '';
					$title_color = 'var(--main-blue)';

					// Add status badge for non-published posts
					if ($status === 'draft') {
						$background_color = '#fcf3cf';
						$title_color = '#856404';
					    	$status_label = '<div style="background:'.$background_color.'; color:'.$title_color.'; padding:2px 8px; border-radius:3px; font-size:15px; font-weight:600; margin: 0 0 5px -8px;">DRAFT</div>';
					} elseif ($status === 'pending') {
						$background_color = '#f0f0f1';
						$title_color = '#646970';
					    	$status_label = '<div style="background:'.$background_color.'; color:'.$title_color.'; padding:2px 8px; border-radius:3px; font-size:15px; font-weight:600; margin: 0 0 5px -8px;">PENDING</div>';
					} elseif ($status === 'future') {
						$background_color = '#cce5ff';
						$title_color = '#004085';
					    	$status_label = '<div style="background:'.$background_color.'; color:'.$title_color.'; padding:2px 8px; border-radius:3px; font-size:15px; font-weight:600; margin: 0 0 5px -8px;">SCHEDULED</div>';
					}

					return $status_label.'<a href="'.get_edit_post_link($id).'" target="_blank"><b style="color: '.$title_color.'; font-size:115%">'.get_the_title($id).'</b></a><br>/'.get_post_field('post_name', $id);
				 }),

				'meta_exists' => fn() =>
					get_post_meta($id, $cols[$col]['meta_key'], true)
						? '<span class="dashicons dashicons-yes-alt" style="color:#2ecc71;"></span>'
						: '<span class="dashicons dashicons-dismiss" style="color:#e74c3c;"></span>',

				'bp-modified' => get_the_modified_date('', $id),

				'bp-attachments' => fn() =>
					implode('', array_map(fn($a)=>
						'<a href="'.esc_url(get_edit_post_link($a->ID)).'" target="_blank">'.
							wp_get_attachment_image($a->ID, [80,80]).
						'</a>',
						get_children([
							'post_parent'=>$id,
							'post_type'=>'attachment'
						])
					)),

				'bp-featured-image' => fn() => get_the_post_thumbnail($id, [130,130]),

				'value'  => fn() => bp_render_meta($id,$cols,$col,$pt),
				'date'   => fn() => bp_render_meta($id,$cols,$col,$pt),
				'number' => fn() => bp_render_meta($id,$cols,$col,$pt),

				'bp-categories' => (function() use ($id, $pt){
					$terms = get_the_terms($id, 'category');
					if (!$terms || is_wp_error($terms)) return '';

					$links = [];
					foreach ($terms as $term) {
						$filter_url = add_query_arg([
							'bp-categories' => $term->term_id,
						], admin_url('edit.php?post_type='.$pt));
						$links[] = '<a href="'.esc_url($filter_url).'">'.wp_kses_post($term->name).'</a>';
					}
					return implode(', ', $links);
				}),

				'bp-tags' => (function() use ($id, $pt){
					$terms = get_the_terms($id, 'post_tag');
					if (!$terms || is_wp_error($terms)) return '';

					$links = [];
					foreach ($terms as $term) {
						$filter_url = add_query_arg([
							'bp-tags' => $term->term_id,
						], admin_url('edit.php?post_type='.$pt));
						$links[] = '<a href="'.esc_url($filter_url).'">'.wp_kses_post($term->name).'</a>';
					}
					return implode(', ', $links);
				}),

				'bp-author' => fn() =>
					get_the_author_meta('display_name', get_post_field('post_author', $id)),

				'bp-menu_order' => fn() =>
					(int) get_post_field('menu_order', $id),

				'bp-meta_number' => ($v = (int) get_post_meta($id, $cols[$col]['meta_key'], true))
					? str_repeat('⭐', $v)
					: '',

				'bp-taxonomy' => (function() use ($id, $cols, $col, $pt){
					$terms = get_the_terms($id, $cols[$col]['taxonomy']);
					$taxonomy = $cols[$col]['taxonomy'];

					$output = '';

					// Display current terms as links
					if ($terms && !is_wp_error($terms)) {
						$links = [];
						foreach ($terms as $term) {
							$filter_url = add_query_arg([
								$col => $term->term_id,
							], admin_url('edit.php?post_type='.$pt));
							$links[] = '<a href="'.esc_url($filter_url).'">'.wp_kses_post($term->name).'</a>';
						}
						$output = implode(', ', $links);
					} else {
						$output = '<span style="color:#999;">None</span>';
					}

					// Add edit button if inline_edit is enabled
					if (!empty($cols[$col]['inline_edit'])) {
						$output .= ' <button type="button" class="button-link bp-taxonomy-edit"
							data-post-id="'.esc_attr($id).'"
							data-taxonomy="'.esc_attr($taxonomy).'"
							data-column="'.esc_attr($col).'"
							style="color:#2271b1; cursor:pointer; text-decoration:none; border:none; background:none; padding:0; margin-left:5px;"
							title="Edit terms">✎</button>';
					}
					return $output;
				}),
			], '');

		}, 10, 2);
	}

	/* Sortable */
	// Add this to your bp_admin_columns function, in the sortable section
	// This handles sorting meta values that contain post IDs by the post title instead of ID

	/* Sortable - Enhanced for Post ID Meta Values */
	if($pt === 'attachment'){

	    add_filter('manage_upload_sortable_columns', function($sortable) use ($cols){

		   foreach($cols as $k=>$c)
			  if(!empty($c['sortable']))
				 $sortable[$k] = $k;

		   return $sortable;

	    });

	} else {

	    add_filter("manage_edit-{$pt}_sortable_columns", function($sortable) use ($cols){

		   foreach($cols as $k=>$c)
			  if(!empty($c['sortable']))
				 $sortable[$k] = $k;

		   return $sortable;

	    });

	    // Handle orderby for meta values that are post IDs
	    add_filter('posts_clauses', function($clauses, $query) use ($pt, $cols) {
			global $wpdb;

			if (!is_admin() || !$query->is_main_query()) {
				return $clauses;
			}

			if ($query->get('post_type') !== $pt) {
				return $clauses;
			}

			$orderby = $query->get('orderby');

			if (!$orderby || !isset($cols[$orderby])) {
				return $clauses;
			}

			$col_type = $cols[$orderby]['type'] ?? '';

			// Only handle bp-meta_value columns
			if ($col_type === 'exists') {
				return $clauses;
			}

			$meta_key = $cols[$orderby]['meta_key'] ?? '';

			if (!$meta_key) {
				return $clauses;
			}

			$order = $query->get('order') ?: 'ASC';

			// Join the posts table for the related post
			$clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS mt1 ON {$wpdb->posts}.ID = mt1.post_id AND mt1.meta_key = '{$meta_key}'";
			$clauses['join'] .= " LEFT JOIN {$wpdb->posts} AS p2 ON mt1.meta_value = p2.ID";

			// Order by the related post's title (or the meta value if it's not a valid post ID)
			$clauses['orderby'] = "COALESCE(p2.post_title, mt1.meta_value) {$order}";

			// Make sure we're grouping correctly to avoid duplicates
			$clauses['groupby'] = "{$wpdb->posts}.ID";

			return $clauses;

		}, 10, 2);
	}

	add_filter('posts_clauses', function($clauses, $query) use ($pt) {
		global $wpdb;

		if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== $pt) return $clauses;

		$status_order = "
			CASE {$wpdb->posts}.post_status
				WHEN 'publish' THEN 0
				WHEN 'draft' THEN 1
				WHEN 'pending' THEN 2
				WHEN 'future' THEN 3
				ELSE 4
			END
		";


		// If user did NOT manually click a column
		if (!isset($_GET['orderby'])) {

			$clauses['orderby'] = "
				{$status_order},
				{$wpdb->posts}.post_modified DESC
			";

			return $clauses;
		}


		// Prepend to existing ORDER BY
		$clauses['orderby'] = "{$status_order}, " . $clauses['orderby'];

		return $clauses;

	}, 20, 2);

	/* Filterable Dropdowns */
	add_action('restrict_manage_posts', function() use ($pt, $cols){

		$screen = get_current_screen();
		if (!$screen || $screen->post_type !== $pt) return;

		if (!is_admin()) return;

		foreach ($cols as $key => $c) {

			if (empty($c['filterable'])) continue;

			$type  = $c['type'] ?? '';
			$value = $_GET[$key] ?? '';

			// META EXISTS
			if ($type === 'meta_exists') {
				echo '<select name="'.esc_attr($key).'">';
				echo '<option value="">All '.wp_kses_post($c['label']).'</option>';
				echo '<option value="yes" '.selected($value,'yes',false).'>Has '.wp_kses_post($c['label']).'</option>';
				echo '<option value="no"  '.selected($value,'no',false).'>No '.wp_kses_post($c['label']).'</option>';
				echo '</select>';
			}

			// TAXONOMY
			if ($type === 'bp-taxonomy') {

				$terms = get_terms([
					'taxonomy'   => $c['taxonomy'],
					'hide_empty' => false,
				]);

				echo '<select name="'.esc_attr($key).'">';
				echo '<option value="">All '.wp_kses_post($c['label']).'</option>';
				echo '<option value="__has__" '.selected($value,'__has__',false).'>Has '.wp_kses_post($c['label']).'</option>';
				echo '<option value="__none__" '.selected($value,'__none__',false).'>No '.wp_kses_post($c['label']).'</option>';
				echo '<option disabled>──────────</option>';

				foreach ($terms as $term) {
					echo '<option value="'.esc_attr($term->term_id).'" '.selected($value,$term->term_id,false).'>'.wp_kses_post($term->name).'</option>';
				}

				echo '</select>';
			}
		}
	});

	/* Query Filters */
	add_action('pre_get_posts', function($q) use ($pt, $cols){

		if (!is_admin()) return;

		global $pagenow;
		if ($pagenow !== 'edit.php') return;

		$post_type = $q->get('post_type');

		if (
			(is_array($post_type) && !in_array($pt, $post_type, true)) ||
			(!is_array($post_type) && $post_type !== $pt)
		) return;

		$meta_query = (array) ($q->get('meta_query') ?: []);
		$tax_query = [];

		$existing_tax = $q->get('tax_query');
		if (!empty($existing_tax) && is_array($existing_tax))
			$tax_query = $existing_tax;

		foreach ($cols as $key => $c) {

			if (empty($c['filterable'])) continue;

			$type  = $c['type'] ?? '';
			$value = $_GET[$key] ?? '';

			if ($value === '' || $value === null) continue;

			// META EXISTS
			if ($type === 'meta_exists') {

				if ($value === 'yes') {
					$meta_query[] = [
						'key'     => $c['meta_key'],
						'value'   => '',
						'compare' => '!=',
					];
				}

				if ($value === 'no') {
					$meta_query[] = [
						'relation' => 'OR',
						[
							'key'     => $c['meta_key'],
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => $c['meta_key'],
							'value'   => '',
							'compare' => '=',
						],
					];
				}
			}

			// TAXONOMY
			if ($type === 'bp-taxonomy') {

				unset($q->query_vars[$key]);


				// Has any term
				if ($value === '__has__') {

					$tax_query[] = [
						'taxonomy' => $c['taxonomy'],
						'operator' => 'EXISTS',
					];

					continue;
				}

				// Has no terms
				if ($value === '__none__') {

					$tax_query[] = [
						'taxonomy' => $c['taxonomy'],
						'operator' => 'NOT EXISTS',
					];

					continue;
				}

				// Specific term
				$term_id = (int) $value;
				if ($term_id <= 0) continue;

				$tax_query[] = [
					'taxonomy'         => $c['taxonomy'],
					'field'            => 'term_id',
					'terms'            => [$term_id],
					'include_children' => false,
				];
			}
		}

		if (!empty($meta_query)) {
			$q->set('meta_query', array_merge(['relation' => 'AND'], $meta_query));
		}

		if (!empty($tax_query)) {
			$q->set('tax_query', [
				'relation' => 'AND',
				...$tax_query
			]);
		}

	}, 999);
}

add_filter('posts_request', function($sql, $query){

	if (!is_admin()) return $sql;
	if (!$query->is_main_query()) return $sql;

	error_log("MAIN QUERY:");
	error_log($sql);

	return $sql;

}, 10, 2);

/*--------------------------------------------------------------
# Inline Editing System
--------------------------------------------------------------*/

// Enqueue inline editing JavaScript
add_action('admin_enqueue_scripts', function($hook){

	// Only load on post list pages and users page
	if (!in_array($hook, ['edit.php', 'upload.php', 'users.php'])) return;

	// Register a dummy script handle so we can attach inline script
	wp_register_script('bp-inline-edit', false, [], false, true);
	wp_enqueue_script('bp-inline-edit');

	// Add inline script
	$script = "
	document.addEventListener('DOMContentLoaded', function(){

		// ===== HELPER FUNCTIONS =====

		function ajaxPost(action, data) {
			var params = new URLSearchParams();
			params.append('action', action);
			for (var key in data) {
				if (Array.isArray(data[key])) {
					data[key].forEach(function(val) {
						params.append(key + '[]', val);
					});
				} else {
					params.append(key, data[key]);
				}
			}
			return fetch(ajaxurl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: params.toString()
			}).then(function(r){ return r.json(); });
		}

		function createEl(tag, attrs, html) {
			var el = document.createElement(tag);
			if (attrs) {
				for (var key in attrs) {
					if (key === 'style' && typeof attrs[key] === 'object') {
						Object.assign(el.style, attrs[key]);
					} else if (key.indexOf('data-') === 0) {
						el.setAttribute(key, attrs[key]);
					} else if (key === 'className') {
						el.className = attrs[key];
					} else {
						el[key] = attrs[key];
					}
				}
			}
			if (html !== undefined) el.innerHTML = html;
			return el;
		}

		function makeEditableSpan(className, dataset, text) {
			var span = createEl('span', {
				className: className,
				title: 'Click to edit'
			});
			span.style.cursor = 'pointer';
			span.style.borderBottom = 'var(--inline-edit)';
			for (var key in dataset) {
				span.setAttribute('data-' + key, dataset[key]);
			}
			span.textContent = text;
			return span;
		}


		// ===== POST META INLINE EDITING (ENHANCED WITH POST SEARCH) =====

		document.addEventListener('click', function(e){

			var span = e.target.closest('.bp-inline-edit');
			if (!span) return;

			var postId = span.getAttribute('data-post-id');
			var metaKey = span.getAttribute('data-meta-key');
			var rawValue = span.getAttribute('data-raw-value'); // Store the actual ID/value
			var currentValue = span.textContent.trim();

			// Prevent double-click creating multiple inputs
			if (span.classList.contains('editing')) return;
			span.classList.add('editing');

			// Replace with input field
			var fieldType = span.getAttribute('data-field-type') || 'value';

			var input = createEl('input', {
				type: fieldType === 'date' ? 'date' : 'text',
				className: 'bp-inline-input'
			});
			input.style.width = '100%';
			input.style.padding = '3px';
			var rawValue = span.getAttribute('data-raw-value') || '';

			if (fieldType === 'date') {
				input.value = rawValue || '';
			} else {
				input.value = (currentValue === '—') ? '' : currentValue;
			}

			input.setAttribute('data-raw-value', rawValue);
			span.replaceWith(input);
			input.focus();
			input.select();

			// Save handler
			function saveField(e) {
				if (e.type === 'keypress' && e.which !== 13) return;
				if (e.type === 'keypress') e.preventDefault();

				// Prevent double-fire
				input.removeEventListener('blur', saveField);
				input.removeEventListener('keypress', saveField);

				var newValue = input.value;

				var loading = makeEditableSpan('bp-inline-edit', { 'post-id': postId, 'meta-key': metaKey }, 'Saving...');
				input.replaceWith(loading);

				// Check if we need to resolve a post name to ID
				ajaxPost('bp_resolve_meta_value', {
					meta_key: metaKey,
					value: newValue,
					nonce: '".wp_create_nonce("bp_inline_edit")."'
				}).then(function(resolveResponse){
					if (resolveResponse.success) {

						var valueToSave = resolveResponse.data.resolved_value;
						var displayValue = resolveResponse.data.display_value;

						// Now save the resolved value
						return ajaxPost('bp_inline_edit_save', {
							post_id: postId,
							meta_key: metaKey,
							meta_value: valueToSave,
							nonce: '".wp_create_nonce("bp_inline_edit")."'
						}).then(function(response){
							if (response.success) {
								var newSpan = makeEditableSpan('bp-inline-edit', {
									'post-id': postId,
									'meta-key': metaKey,
									'raw-value': valueToSave
								}, displayValue || '—');
								loading.replaceWith(newSpan);
							} else {
								alert('Error: ' + (response.data || 'Failed to save'));
								loading.replaceWith(span);
							}
						});
					} else {
						alert('Error: ' + (resolveResponse.data || 'Failed to resolve value'));
						loading.replaceWith(span);
					}
				}).catch(function(){
					alert('Error saving field');
					loading.replaceWith(span);
				});
			}

			input.addEventListener('blur', saveField);
			input.addEventListener('keypress', saveField);

			// Cancel on Escape
			input.addEventListener('keydown', function(e){
				if (e.which === 27) {
					var cancelSpan = makeEditableSpan('bp-inline-edit', {
						'post-id': postId,
						'meta-key': metaKey,
						'raw-value': rawValue
					}, currentValue);
					input.replaceWith(cancelSpan);
				}
			});
		});


		// ===== TAXONOMY INLINE EDITING =====

		document.addEventListener('click', function(e){

			var btn = e.target.closest('.bp-taxonomy-edit');
			if (!btn) return;

			var postId = btn.getAttribute('data-post-id');
			var taxonomy = btn.getAttribute('data-taxonomy');
			var column = btn.getAttribute('data-column');
			var cell = btn.closest('td');

			// Prevent double-click
			if (btn.classList.contains('editing')) return;
			btn.classList.add('editing');

			cell.innerHTML = '<span>Loading...</span>';

			ajaxPost('bp_get_taxonomy_terms', {
				post_id: postId,
				taxonomy: taxonomy,
				nonce: '".wp_create_nonce("bp_taxonomy_edit")."'
			}).then(function(response){
				if (response.success) {

					var allTerms = response.data.all_terms;
					var currentTerms = response.data.current_terms;

					var html = '<div class=\"bp-taxonomy-editor\" data-post-id=\"'+postId+'\" data-taxonomy=\"'+taxonomy+'\" style=\"background:#fff; border:1px solid #ddd; padding:10px; max-height:200px; overflow-y:auto;\">';

					allTerms.forEach(function(term){
						var checked = currentTerms.indexOf(term.term_id) !== -1 ? 'checked' : '';
						html += '<label style=\"display:block; margin:3px 0;\"><input type=\"checkbox\" value=\"'+term.term_id+'\" '+checked+'> '+term.name+'</label>';
					});

					html += '<div style=\"margin-top:10px;\"><button type=\"button\" class=\"button button-primary bp-taxonomy-save\">Save</button> <button type=\"button\" class=\"button bp-taxonomy-cancel\">Cancel</button></div>';
					html += '</div>';

					cell.innerHTML = html;

				} else {
					alert('Error loading terms');
					location.reload();
				}
			}).catch(function(){
				alert('Error loading terms');
				location.reload();
			});
		});

		// Taxonomy save
		document.addEventListener('click', function(e){

			var btn = e.target.closest('.bp-taxonomy-save');
			if (!btn) return;

			var editor = btn.closest('.bp-taxonomy-editor');
			var cell = btn.closest('td');
			var postId = editor.getAttribute('data-post-id');
			var taxonomy = editor.getAttribute('data-taxonomy');

			var termIds = [];
			editor.querySelectorAll('input[type=\"checkbox\"]:checked').forEach(function(cb){
				termIds.push(parseInt(cb.value));
			});

			cell.innerHTML = '<span>Saving...</span>';

			ajaxPost('bp_save_taxonomy_terms', {
				post_id: postId,
				taxonomy: taxonomy,
				term_ids: termIds,
				nonce: '".wp_create_nonce("bp_taxonomy_edit")."'
			}).then(function(response){
				if (response.success) {
					location.reload();
				} else {
					alert('Error: ' + (response.data || 'Failed to save'));
					location.reload();
				}
			}).catch(function(){
				alert('Error saving terms');
				location.reload();
			});
		});

		// Taxonomy cancel
		document.addEventListener('click', function(e){
			if (e.target.closest('.bp-taxonomy-cancel')) location.reload();
		});


		// ===== USER META INLINE EDITING =====

		document.addEventListener('click', function(e){

			var span = e.target.closest('.bp-inline-edit-user');
			if (!span) return;

			var userId = span.getAttribute('data-user-id');
			var field = span.getAttribute('data-field');
			var currentValue = span.textContent.trim();

			if (span.classList.contains('editing')) return;
			span.classList.add('editing');

			var fieldType = span.getAttribute('data-field-type') || 'value';

			var input = createEl('input', {
				type: fieldType === 'date' ? 'date' : 'text',
				className: 'bp-inline-input'
			});
			input.style.width = '100%';
			input.style.padding = '3px';
			var rawValue = span.getAttribute('data-raw-value') || '';

			if (fieldType === 'date') {
				input.value = rawValue || '';
			} else {
				input.value = (currentValue === '—') ? '' : currentValue;
			}

			span.replaceWith(input);
			input.focus();
			input.select();

			function saveUserField(e) {
				if (e.type === 'keypress' && e.which !== 13) return;
				if (e.type === 'keypress') e.preventDefault();

				input.removeEventListener('blur', saveUserField);
				input.removeEventListener('keypress', saveUserField);

				var newValue = input.value;

				var loading = makeEditableSpan('bp-inline-edit-user', { 'user-id': userId, 'field': field }, 'Saving...');
				input.replaceWith(loading);

				ajaxPost('bp_inline_edit_user_save', {
					user_id: userId,
					field: field,
					value: newValue,
					nonce: '".wp_create_nonce("bp_inline_edit_user")."'
				}).then(function(response){
					if (response.success) {
						var newSpan = makeEditableSpan('bp-inline-edit-user', { 'user-id': userId, 'field': field }, newValue || '—');
						loading.replaceWith(newSpan);
					} else {
						alert('Error: ' + (response.data || 'Failed to save'));
						loading.replaceWith(span);
					}
				}).catch(function(){
					alert('Error saving field');
					loading.replaceWith(span);
				});
			}

			input.addEventListener('blur', saveUserField);
			input.addEventListener('keypress', saveUserField);

			input.addEventListener('keydown', function(e){
				if (e.which === 27) {
					var cancelSpan = makeEditableSpan('bp-inline-edit-user', { 'user-id': userId, 'field': field }, currentValue);
					input.replaceWith(cancelSpan);
				}
			});
		});


		// ===== ROLE INLINE EDITING =====

		document.addEventListener('click', function(e){

			var btn = e.target.closest('.bp-inline-edit-role');
			if (!btn) return;

			var userId = btn.getAttribute('data-user-id');
			var currentRoles = btn.getAttribute('data-current-roles') ? btn.getAttribute('data-current-roles').toString().split(',') : [];
			var cell = btn.closest('td');

			if (btn.classList.contains('editing')) return;
			btn.classList.add('editing');

			cell.innerHTML = '<span>Loading...</span>';

			ajaxPost('bp_get_user_roles', {
				nonce: '".wp_create_nonce("bp_user_role_edit")."'
			}).then(function(response){
				if (response.success) {

					var roles = response.data.roles;

					var html = '<div class=\"bp-role-editor\" data-user-id=\"'+userId+'\" style=\"background:#fff; border:1px solid #ddd; padding:10px; max-height:200px; overflow-y:auto;\">';

					for (var roleKey in roles) {
						var checked = currentRoles.indexOf(roleKey) !== -1 ? 'checked' : '';
						html += '<label style=\"display:block; margin:3px 0;\"><input type=\"checkbox\" value=\"'+roleKey+'\" '+checked+'> '+roles[roleKey]+'</label>';
					}

					html += '<div style=\"margin-top:10px;\"><button type=\"button\" class=\"button button-primary bp-role-save\">Save</button> <button type=\"button\" class=\"button bp-role-cancel\">Cancel</button></div>';
					html += '</div>';

					cell.innerHTML = html;

				} else {
					alert('Error loading roles');
					location.reload();
				}
			}).catch(function(){
				alert('Error loading roles');
				location.reload();
			});
		});

		// Role save
		document.addEventListener('click', function(e){

			var btn = e.target.closest('.bp-role-save');
			if (!btn) return;

			var editor = btn.closest('.bp-role-editor');
			var cell = btn.closest('td');
			var userId = editor.getAttribute('data-user-id');

			var roleKeys = [];
			editor.querySelectorAll('input[type=\"checkbox\"]:checked').forEach(function(cb){
				roleKeys.push(cb.value);
			});

			cell.innerHTML = '<span>Saving...</span>';

			ajaxPost('bp_save_user_roles', {
				user_id: userId,
				roles: roleKeys,
				nonce: '".wp_create_nonce("bp_user_role_edit")."'
			}).then(function(response){
				if (response.success) {
					location.reload();
				} else {
					alert('Error: ' + (response.data || 'Failed to save'));
					location.reload();
				}
			}).catch(function(){
				alert('Error saving roles');
				location.reload();
			});
		});

		// Role cancel
		document.addEventListener('click', function(e){
			if (e.target.closest('.bp-role-cancel')) location.reload();
		});
	});
	";

	wp_add_inline_script('bp-inline-edit', $script);
});

// AJAX handler to save inline edits
add_action('wp_ajax_bp_inline_edit_save', function(){

	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bp_inline_edit')) {
		wp_send_json_error('Invalid nonce');
	}

	// Get data
	$post_id = (int) $_POST['post_id'];
	$meta_key = sanitize_text_field($_POST['meta_key']);
	$meta_value = sanitize_text_field($_POST['meta_value']);

	// Verify user can edit this post
	if (!current_user_can('edit_post', $post_id)) {
		wp_send_json_error('Permission denied');
	}

	// Update or add the meta (WP handles both)
	update_post_meta($post_id, $meta_key, $meta_value);

	// Always return success unless permission failed earlier
	wp_send_json_success([
		'post_id'   => $post_id,
		'meta_key'  => $meta_key,
		'meta_value'=> $meta_value
	]);
});

// AJAX handler to resolve meta values (convert post names to IDs, format dates/currency, etc.)
add_action('wp_ajax_bp_resolve_meta_value', function(){

	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bp_inline_edit')) {
		wp_send_json_error('Invalid nonce');
	}

	$meta_key = sanitize_text_field($_POST['meta_key']);
	$value = sanitize_text_field($_POST['value']);

	// Empty value - return as-is
	if (empty($value)) {
		wp_send_json_success([
			'resolved_value' => '',
			'display_value' => '—'
		]);
	}

	// Try to find a post by this name (for sire, dam, etc.)
	$post_types = get_post_types(['public' => true], 'names');
	$post = get_page_by_title($value, OBJECT, $post_types);

	if ($post) {
		// Found a post - return its ID and title
		wp_send_json_success([
			'resolved_value' => $post->ID,
			'display_value' => $post->post_title
		]);
	}

	// Normalize date formats to YYYY-MM-DD
	$date = null;

	// Format: YYYY-MM-DD
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
		$date = DateTime::createFromFormat('Y-m-d', $value);
	}

	// Format: YYYYMMDD
	elseif (preg_match('/^\d{8}$/', $value)) {
		$date = DateTime::createFromFormat('Ymd', $value);
	}

	if ($date) {
		$normalized = $date->format('Y-m-d');

		wp_send_json_success([
			'resolved_value' => $normalized,          // Always save dashed
			'display_value'  => $date->format('F j, Y')
		]);
	}


	// Check if it's currency (starts with $ or is numeric for price/deposit fields)
	if (strpos($meta_key, 'price') !== false ||
	    strpos($meta_key, 'deposit') !== false ||
	    strpos($meta_key, 'cost') !== false ||
	    strpos($meta_key, 'amount') !== false) {
		// Remove $ and commas, keep just numbers
		$numeric = preg_replace('/[^0-9.]/', '', $value);
		if (is_numeric($numeric)) {
			wp_send_json_success([
				'resolved_value' => $numeric,
				'display_value' => '$' . number_format($numeric, 0)
			]);
		}
	}

	// Default - return as-is
	wp_send_json_success([
		'resolved_value' => $value,
		'display_value' => $value
	]);
});

// AJAX handler to get taxonomy terms for editing
add_action('wp_ajax_bp_get_taxonomy_terms', function(){

	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bp_taxonomy_edit')) {
		wp_send_json_error('Invalid nonce');
	}

	$post_id = (int) $_POST['post_id'];
	$taxonomy = sanitize_text_field($_POST['taxonomy']);

	// Verify user can edit this post
	if (!current_user_can('edit_post', $post_id)) {
		wp_send_json_error('Permission denied');
	}

	// Get all terms for this taxonomy
	$all_terms = get_terms([
		'taxonomy' => $taxonomy,
		'hide_empty' => false,
	]);

	if (is_wp_error($all_terms)) {
		wp_send_json_error('Failed to load terms');
	}

	// Get current terms assigned to this post
	$current_terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);

	if (is_wp_error($current_terms)) {
		$current_terms = [];
	}

	// Format for JSON
	$terms_data = array_map(function($term) {
		return [
			'term_id' => $term->term_id,
			'name' => $term->name,
		];
	}, $all_terms);

	wp_send_json_success([
		'all_terms' => $terms_data,
		'current_terms' => array_map('intval', $current_terms),
	]);
});

// AJAX handler to save taxonomy terms
add_action('wp_ajax_bp_save_taxonomy_terms', function(){

	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bp_taxonomy_edit')) {
		wp_send_json_error('Invalid nonce');
	}

	$post_id = (int) $_POST['post_id'];
	$taxonomy = sanitize_text_field($_POST['taxonomy']);
	$term_ids = isset($_POST['term_ids']) ? array_map('intval', $_POST['term_ids']) : [];

	// Verify user can edit this post
	if (!current_user_can('edit_post', $post_id)) {
		wp_send_json_error('Permission denied');
	}

	// Update the terms
	$result = wp_set_object_terms($post_id, $term_ids, $taxonomy);

	if (is_wp_error($result)) {
		wp_send_json_error('Failed to update terms');
	}

	wp_send_json_success([
		'post_id' => $post_id,
		'taxonomy' => $taxonomy,
		'term_ids' => $term_ids,
	]);
});

// AJAX handler to save user inline edits
add_action('wp_ajax_bp_inline_edit_user_save', function(){

	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bp_inline_edit_user')) {
		wp_send_json_error('Invalid nonce');
	}

	// Get data
	$user_id = (int) $_POST['user_id'];
	$field = sanitize_text_field($_POST['field']);
	$value = sanitize_text_field($_POST['value']);

	// Verify user can edit users
	if (!current_user_can('edit_users')) {
		wp_send_json_error('Permission denied');
	}

	// Update based on field type
	if ($field === 'display_name') {
		// Update display_name
		$result = wp_update_user([
			'ID' => $user_id,
			'display_name' => $value
		]);

		if (is_wp_error($result)) {
			wp_send_json_error('Failed to update display name');
		}

	} else {
		// Assume it's user meta (first_name, last_name, etc.)
		$result = update_user_meta($user_id, $field, $value);

		if ($result === false) {
			wp_send_json_error('Failed to update');
		}
	}

	wp_send_json_success([
		'user_id' => $user_id,
		'field' => $field,
		'value' => $value
	]);
});

// AJAX handler to get available user roles
add_action('wp_ajax_bp_get_user_roles', function(){

	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bp_user_role_edit')) {
		wp_send_json_error('Invalid nonce');
	}

	// Verify user can edit users
	if (!current_user_can('edit_users')) {
		wp_send_json_error('Permission denied');
	}

	// Get all available roles
	$roles = wp_roles()->get_names();

	// Format role names (capitalize first letter)
	$formatted_roles = [];
	foreach ($roles as $role_key => $role_name) {
		$formatted_roles[$role_key] = translate_user_role($role_name);
	}

	wp_send_json_success([
		'roles' => $formatted_roles
	]);
});

// AJAX handler to save user roles (multiple)
add_action('wp_ajax_bp_save_user_roles', function(){

	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bp_user_role_edit')) {
		wp_send_json_error('Invalid nonce');
	}

	// Get data
	$user_id = (int) $_POST['user_id'];
	$new_roles = isset($_POST['roles']) ? array_map('sanitize_text_field', $_POST['roles']) : [];

	// Verify user can edit users
	if (!current_user_can('edit_users')) {
		wp_send_json_error('Permission denied');
	}

	// Verify all roles exist
	$available_roles = array_keys(wp_roles()->get_names());
	foreach ($new_roles as $role) {
		if (!in_array($role, $available_roles)) {
			wp_send_json_error('Invalid role: ' . $role);
		}
	}

	// Get user object
	$user = get_userdata($user_id);

	if (!$user) {
		wp_send_json_error('User not found');
	}

	// Remove all existing roles
	foreach ($user->roles as $role) {
		$user->remove_role($role);
	}

	// Add new roles
	foreach ($new_roles as $role) {
		$user->add_role($role);
	}

	wp_send_json_success([
		'user_id' => $user_id,
		'roles' => $new_roles
	]);
});


/*--------------------------------------------------------------
# Media Library Taxonomy Filters
--------------------------------------------------------------*/

// Special handler for media library filter dropdowns
add_action('restrict_manage_media', function(){

	$cols = [
		'image-categories' => [
			'type'       => 'bp-taxonomy',
			'taxonomy'   => 'image-categories',
			'label'      => 'bp-categories',
			'filterable' => true
		],
		'image-tags' => [
			'type'       => 'bp-taxonomy',
			'taxonomy'   => 'image-tags',
			'label'      => 'bp-tags',
			'filterable' => true
		],
	];

	foreach ($cols as $key => $c) {

		$value = $_GET[$key] ?? '';

		$terms = get_terms([
			'taxonomy'   => $c['taxonomy'],
			'hide_empty' => false,
		]);

		echo '<select name="'.esc_attr($key).'">';
		echo '<option value="">All '.wp_kses_post($c['label']).'</option>';

		foreach ($terms as $term) {
			echo '<option value="'.esc_attr($term->term_id).'" '.selected($value,$term->term_id,false).'>'.wp_kses_post($term->name).'</option>';
		}

		echo '</select>';
	}
});

// LIST VIEW FIX (Traditional Media Library)
add_action('parse_query', 'bp_media_taxonomy_filter_listview', 999);

function bp_media_taxonomy_filter_listview($q) {

	if (!is_admin()) return;

	global $pagenow;
	if ($pagenow !== 'upload.php') return;

	$tax_query = [];

	// Handle image-categories filter
	if (!empty($_GET['image-categories']) && (int)$_GET['image-categories'] > 0) {
		$tax_query[] = [
			'taxonomy' => 'image-categories',
			'field'    => 'term_id',
			'terms'    => (int)$_GET['image-categories'],
		];
	}

	// Handle image-tags filter
	if (!empty($_GET['image-tags']) && (int)$_GET['image-tags'] > 0) {
		$tax_query[] = [
			'taxonomy' => 'image-tags',
			'field'    => 'term_id',
			'terms'    => (int)$_GET['image-tags'],
		];
	}

	// If we have taxonomy filters, apply them
	if (!empty($tax_query)) {

		// Set relation if multiple taxonomies
		if (count($tax_query) > 1) {
			$tax_query['relation'] = 'AND';
		}

		// Apply the tax query
		$q->set('tax_query', $tax_query);

		// **CRITICAL FIX**: Remove the taxonomy params from query_vars
		// This prevents WordPress from trying to process them as regular query vars
		unset($q->query_vars['image-categories']);
		unset($q->query_vars['image-tags']);

		// Ensure proper attachment post statuses
		if ($q->get('post_type') === 'attachment') {
			$q->set('post_status', 'inherit,private');
		}
	}
}

// GRID VIEW FIX (AJAX-based Media Library)
add_filter('ajax_query_attachments_args', 'bp_media_taxonomy_filter_gridview', 10, 1);

function bp_media_taxonomy_filter_gridview($query) {

	$tax_query = isset($query['tax_query']) ? $query['tax_query'] : [];

	// Handle image-categories filter from AJAX request
	if (!empty($_REQUEST['query']['image-categories'])) {
		$tax_query[] = [
			'taxonomy' => 'image-categories',
			'field'    => 'term_id',
			'terms'    => (int)$_REQUEST['query']['image-categories'],
		];
	}

	// Handle image-tags filter from AJAX request
	if (!empty($_REQUEST['query']['image-tags'])) {
		$tax_query[] = [
			'taxonomy' => 'image-tags',
			'field'    => 'term_id',
			'terms'    => (int)$_REQUEST['query']['image-tags'],
		];
	}

	// If we have taxonomy filters, apply them
	if (!empty($tax_query)) {

		// Set relation if multiple taxonomies
		if (count($tax_query) > 1) {
			$tax_query['relation'] = 'AND';
		}

		$query['tax_query'] = $tax_query;

		// Ensure proper post status for attachments
		$query['post_status'] = 'inherit,private';
	}

	return $query;
}


/*--------------------------------------------------------------
# Users Admin Columns Engine
--------------------------------------------------------------*/
function bp_user_columns($config){

	$cols = $config['columns'];

	/* Columns Layout */
	add_filter('manage_users_columns', function($existing) use ($cols){

		// Start with just the checkbox
		$new = ['cb' => $existing['cb'] ?? ''];

		// Add our custom columns
		foreach($cols as $key => $c)
			$new[$key] = $c['label'] ?? ucfirst($key);

		// Remove any default WordPress columns that might conflict
		unset($new['username'], $new['name'], $new['email'], $new['role'], $new['posts']);

		// Now add them back in our order
		foreach($cols as $key => $c)
			$new[$key] = $c['label'] ?? ucfirst($key);

		return $new;

	}, 999);

	/* Column Output */
	add_filter('manage_users_custom_column', function($output, $col, $user_id) use ($cols){

		if(!isset($cols[$col])) return $output;

		$type = $cols[$col]['type'];

		return bp_match($type, [

			'user_login' => (function() use ($user_id){
				$user = get_userdata($user_id);
				return '<a href="'.esc_url(get_edit_user_link($user_id)).'" style="font-size: 115%">'.wp_kses_post($user->user_login).'</a>';
			}),

			'user_email' => fn() => get_userdata($user_id)->user_email,

			'display_name' => (function() use ($user_id, $cols, $col){
				$value = get_userdata($user_id)->display_name;

				// Check if this field is inline editable
				if (!empty($cols[$col]['inline_edit'])) {
					return '<span class="bp-inline-edit-user"
						data-user-id="'.esc_attr($user_id).'"
						data-field="display_name"
						style="cursor:pointer; border-bottom:var(--inline-edit);"
						title="Click to edit">'.wp_kses_post($value ?: '—').'</span>';
				}

				return wp_kses_post($value);
			}),

			'user_meta' => (function() use ($user_id, $cols, $col){
				$value = get_user_meta($user_id, $cols[$col]['meta_key'], true);

				// Check if this field is inline editable
				if (!empty($cols[$col]['inline_edit'])) {
					return '<span class="bp-inline-edit-user"
						data-user-id="'.esc_attr($user_id).'"
						data-field="'.esc_attr($cols[$col]['meta_key']).'"
						style="cursor:pointer; border-bottom:var(--inline-edit);"
						title="Click to edit">'.wp_kses_post($value ?: '—').'</span>';
				}

				return wp_kses_post($value);
			}),

			'user_registered' => (function() use ($user_id) {
				$user = get_userdata($user_id);

				return date_i18n('F j, Y', strtotime($user->user_registered)).'<br>'.date_i18n('g:i a', strtotime($user->user_registered));
			}),

			'user_role' => (function() use ($user_id, $cols, $col){
				$user = get_userdata($user_id);
				$roles = $user->roles;
				$current_roles = !empty($roles) ? $roles : [];

				// Display all roles
				$display_roles = !empty($current_roles) ? array_map('ucfirst', $current_roles) : ['—'];
				$display_role = implode(', ', $display_roles);

				// Check if this field is inline editable
				if (!empty($cols[$col]['inline_edit'])) {
					// Pass all current roles as comma-separated string
					$roles_string = implode(',', $current_roles);
					return '<span class="bp-inline-edit-role"
						data-user-id="'.esc_attr($user_id).'"
						data-current-roles="'.esc_attr($roles_string).'"
						style="cursor:pointer; border-bottom:var(--inline-edit);"
						title="Click to edit">'.$display_role.'</span>';
				}

				return $display_role;
			}),

			'post_count' => fn() =>count_user_posts($user_id),

		], '');

	}, 10, 3);

	/* Sortable */
	add_filter('manage_users_sortable_columns', function($sortable) use ($cols){

		foreach($cols as $k=>$c)
			if(!empty($c['sortable']))
				$sortable[$k] = $k;

		return $sortable;

	});

	/* Make columns actually sortable */
	add_action('pre_get_users', function($query) use ($cols){

		if (!is_admin()) return;

		$orderby = $query->get('orderby');

		if (!$orderby || !isset($cols[$orderby])) return;

		$type = $cols[$orderby]['type'];

		// Handle different sortable types
		if ($type === 'user_login') {
			$query->set('orderby', 'login');
		} elseif ($type === 'user_email') {
			$query->set('orderby', 'email');
		} elseif ($type === 'display_name') {
			$query->set('orderby', 'display_name');
		} elseif ($type === 'user_registered') {
			$query->set('orderby', 'registered');
		} elseif ($type === 'user_role') {
			$query->set('orderby', 'role');
		} elseif ($type === 'post_count') {
			$query->set('orderby', 'post_count');
		} elseif ($type === 'user_meta') {
			$query->set('orderby', 'meta_value');
			$query->set('meta_key', $cols[$orderby]['meta_key']);
		}

	}, 999);
}


/*--------------------------------------------------------------
# Users Config
--------------------------------------------------------------*/
bp_user_columns([
	'columns' => [

		'bp-user_login' => [
			'type' => 'user_login',
			'label' => 'Username',
			'sortable' => true,
		],

		'bp-first_name' => [
			'type' => 'user_meta',
			'meta_key' => 'first_name',
			'label' => 'First Name',
			'sortable' => true,
			'inline_edit' => true,
		],

		'bp-last_name' => [
			'type' => 'user_meta',
			'meta_key' => 'last_name',
			'label' => 'Last Name',
			'sortable' => true,
			'inline_edit' => true,
		],

		'bp-display_name' => [
			'type' => 'display_name',
			'label' => 'Display Name',
			'sortable' => true,
			'inline_edit' => true,
		],

		'bp-user_email' => [
			'type' => 'user_email',
			'label' => 'Email',
			'sortable' => true,
		],

		'bp-user_role' => [
			'type' => 'user_role',
			'label' => 'Role',
			'sortable' => true,
			'inline_edit' => true,
		],

		'bp-user-registered' => [
			'type' => 'user_registered',
			'label' => 'Registered',
			'sortable' => true,
		],

	]
]);


/*--------------------------------------------------------------
# Pages Config
--------------------------------------------------------------*/

$bp_featured_image = [
	'bp-featured-image' => [
		'type' => 'bp-featured-image',
		'label' => '',
	],
];

$bp_id = [
	'bp-post-id' => [
		'type' => 'bp-post-id',
		'label' => 'ID',
		'sortable' => true,
	],
];

$bp_title = [
	'bp-title' => [
		'type' => 'bp-title',
		'label' => 'Title',
		'sortable' => true,
	],
];

$bp_modified = [
	'bp-modified' => [
		'type' => 'bp-modified',
		'label' => 'Updated',
		'sortable' => true,
	],
];

$bp_top_bottom = [
	'bp-page-top' => [
		'type'       => 'meta_exists',
		'meta_key'   => 'page-top_text',
		'label'      => 'Top',
		'sortable'   => true,
		'filterable' => true
	],

	'bp-page-bottom' => [
		'type'       => 'meta_exists',
		'meta_key'   => 'page-bottom_text',
		'label'      => 'Bottom',
		'sortable'   => true,
		'filterable' => true
	],
];

$bp_attachments = [
	'bp-attachments' => [
		'type' => 'bp-attachments',
		'label' => 'Attachments',
	],
];

$bp_order = [
	'bp-menu_order' => [
		'type' => 'bp-menu_order',
		'label' => 'Order',
		'sortable' => true,
	],
];

$bp_author = [
	'bp-author' => [
		'type' => 'bp-author',
		'label' => 'Author',
		'sortable' => true,
	],
];

function bp_taxonomy($key, $taxonomy, $label, $args = []) {
	return [
		$key => array_merge([
			'type'       => 'bp-taxonomy',
			'taxonomy'   => $taxonomy,
			'label'      => $label,
			'sortable'   => true,
			'filterable' => true,
			'inline_edit' => true,
		], $args)
	];
}

function bp_meta($key, $meta_key, $label, $args = []) {
	return [
		$key => array_merge([
			'type'       => 'value', // value | meta_exists | date | number | currency etc
			'meta_key'   => $meta_key,
			'label'      => $label,
			'sortable'   => true,
			'filterable' => true,
			'inline_edit'=> true,
		], $args)
	];
}

bp_admin_columns([
	'post_type' => 'page',
	'columns'   => array_merge(
		$bp_id,
		$bp_title,
		$bp_modified,
		$bp_top_bottom,
		$bp_attachments
	)
]);

bp_admin_columns([
	'post_type' => 'post',
	'columns' => array_merge(
		$bp_featured_image,
		$bp_id,
		$bp_title,
		bp_meta('bp-reference', 'reference', 'Ref #', []),
		$bp_modified,
		bp_taxonomy('bp-categories', 'category', 'Categories', []),
		bp_taxonomy('bp-tags', 'post_tag', 'Tags', []),
		$bp_author,
	)
]);

bp_admin_columns([
	'post_type' => 'universal',
	'columns'   => array_merge(
		$bp_id,
		$bp_title,
		$bp_modified,
	)
]);

bp_admin_columns([
	'post_type' => 'landing',
	'columns'   => array_merge(
		$bp_id,
		$bp_title,
		$bp_modified,
		$bp_top_bottom,
		$bp_attachments
	)
]);

bp_admin_columns([
	'post_type' => 'products',
	'columns' => array_merge(
		$bp_featured_image,
		$bp_id,
		$bp_title,
		bp_taxonomy('bp-product-brand', 'product-brand', 'Product Brand', []),
		bp_taxonomy('bp-product-type', 'product-type', 'Product Type', []),
		bp_taxonomy('bp-product-class', 'product-class', 'Product Class', []),
		$bp_modified,
		$bp_order,
	)
]);

bp_admin_columns([
	'post_type' => 'news',
	'columns'   => array_merge(
		$bp_id,
		$bp_title,
		$bp_modified,
		$bp_order,
		$bp_attachments
	)
]);

bp_admin_columns([
	'post_type' => 'testimonials',
	'columns'   => array_merge(
		$bp_featured_image,
		$bp_id,
		$bp_title,
		$bp_modified,
		bp_meta('bp-platform', 'testimonial_platform', 'Platform', []),
		bp_meta('bp-testimonial_rating', 'testimonial_rating', 'Rating', ['type'=>'bp-meta_number']),
		bp_meta('bp-testimonial_location', 'testimonial_location', 'Location', ['type' => 'meta_exists']),
		bp_meta('bp-testimonial_biz', 'testimonial_biz', 'Business', ['type' => 'meta_exists']),
		bp_meta('bp-testimonial_website', 'testimonial_website', 'Website', ['type' => 'meta_exists']),
	)
]);

bp_admin_columns([
	'post_type' => 'galleries',
	'columns' =>  array_merge(
		$bp_featured_image,
		$bp_id,
		$bp_title,
		$bp_modified,
		bp_taxonomy('bp-gallery-type', 'gallery-type', 'Type', []),
		bp_taxonomy('bp-gallery-tags', 'gallery-tags', 'Tags', []),
		$bp_order,
	)
]);

bp_admin_columns([
	'post_type' => 'jobsite_geo',
	'columns' =>  array_merge(
		$bp_id,
		$bp_title,
		bp_meta('job_date', 'job_date', 'Job Date', ['type' => 'date']),
		bp_meta('address', 'address', 'Address', []),
		bp_taxonomy('jobsite_geo-service-areas', 'jobsite_geo-service-areas', 'City', []),
		bp_meta('review', 'review', 'Review', ['type' => 'meta_exists']),
		bp_meta('is_priority_job', 'is_priority_job', 'Priority', ['type' => 'meta_exists']),
		$bp_modified,
		bp_taxonomy('jobsite_geo-services', 'jobsite_geo-services', 'Service', []),
		$bp_attachments
	)
]);

bp_admin_columns([
	'post_type' => 'events',
	'columns' =>  array_merge(
		$bp_featured_image,
		$bp_id,
		$bp_title,
		$bp_modified,
		bp_meta('bp-start_date', 'start_date', 'Starts', ['type' => 'date']),
		bp_meta('bp-end_date', 'end_date', 'Ends', ['type' => 'date']),
		bp_taxonomy('bp-event-cats', 'event-cats', 'Categories', ['inline_edit' => true]),
		bp_taxonomy('bp-event-tags', 'event-tags', 'Tags', ['inline_edit' => true]),
	)
]);

bp_admin_columns([
	'post_type' => 'dogs',
	'columns' =>  array_merge(
		$bp_featured_image,
		$bp_id,
		$bp_title,
		bp_meta('call_name', 'call_name', 'Call Name', []),
		bp_meta('sex', 'sex', 'Sex', []),
		bp_meta('color', 'color', 'Color', []),
		bp_meta('birth_date', 'birth_date', 'Birth Date', ['type' => 'date']),
		bp_taxonomy('dog-tags', 'dog-tags', 'Dog Tags', []),
		$bp_modified,
	)
]);

bp_admin_columns([
	'post_type' => 'litters',
	'columns' =>  array_merge(
		$bp_title,
		bp_meta('sire', 'sire', 'Sire', []),
		bp_meta('dam', 'dam', 'Dam', []),
		bp_meta('litter_status', 'litter_status', 'Status', []),
		bp_meta('birth_date', 'birth_date', 'Due / Birth Date', ['type' => 'date']),
		bp_meta('price', 'price', 'Price', []),
		bp_meta('deposit_hold', 'deposit_hold', 'Hold', []),
		bp_meta('deposit_born', 'deposit_born', 'Upon Birth', []),
		$bp_modified,
	)
]);

bp_admin_columns([
	'post_type' => 'pro-tips',
	'columns' => array_merge(
		$bp_featured_image,
		$bp_id,
		$bp_title,
		$bp_modified,
		bp_taxonomy('pro-tips-category', 'pro-tips-category', 'Topics', []),
		$bp_author,
	)
]);

bp_admin_columns([
	'post_type' => 'elements',
	'columns'   => array_merge(
		$bp_id,
		$bp_title,
		$bp_modified,
		$bp_order,
		$bp_attachments,
	)
]);

bp_admin_columns([
	'post_type' => 'attachment',
	'columns' =>  array_merge (

			$bp_featured_image,
			$bp_title,
			$bp_id,
			bp_meta('bp-alt-text', '_wp_attachment_image_alt', 'Alt Text', []),
			$bp_modified,
			bp_taxonomy('bp-image-categories', 'image-categories', 'Categories', []),
			bp_taxonomy('bp-image-tags', 'image-tags', 'Tags', []),

		[
			'bp-media_parent' => [
				'type' => 'bp-media_parent',
				'label' => 'Attached',
			],

			'bp-size' => [
				'type' => 'bp-media_dimensions',
				'label' => 'Size',
				'sortable' => true,
			],
		],
	)
]);

