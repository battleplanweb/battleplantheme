<?php
/* Battle Plan Web Design Functions: Admin */


/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Shortcodes
# Admin Columns Set Up
# Admin Interface Set Up
# Admin Page Set Up
# Site Audit Set Up
# Contact Form 7 Set Up
--------------------------------------------------------------*/



//See all data points collected via Google Analytics
//error_log('Rollups: ' . print_r(get_option('bp_ga4_rollups_clean'), true));


/*--------------------------------------------------------------
# Shortcodes
--------------------------------------------------------------*/

// Remove buttons from WordPress text editor
add_filter( 'quicktags_settings', 'battleplan_delete_quicktags', 10, 2 );
function battleplan_delete_quicktags( $qtInit, $editor_id = 'content' ) {
	$qtInit['buttons'] = 'strong,em,link,ul,ol,more,close';
	return $qtInit;
}

/*--------------------------------------------------------------
# Admin Columns Set Up
--------------------------------------------------------------*/
require_once get_template_directory() . '/functions-admin-columns.php';



/*--------------------------------------------------------------
# Admin Interface Set Up
--------------------------------------------------------------*/
// Disable Gutenburg
add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
add_filter( 'wp_use_widgets_block_editor', '__return_false' );

// Disable Visual Editor
add_filter( 'user_can_richedit' , '__return_false', 50 );

// Allow separate editing of thumbnails in image editor
add_filter( 'image_edit_thumbnails_separately', '__return_true' );

// Load site-icon for admin bar
add_action('admin_head', 'battleplan__admin_bar_icon');
function battleplan__admin_bar_icon() {
	$iconData = get_option('bp_site_icon');
	$iconName = isset($iconData['name']) ? $iconData['name'] : 'site-icon.webp';
	$iconUrl = esc_url(get_site_url() . '/wp-content/uploads/' . $iconName);
	?>
	<style>
		.wp-admin #wpadminbar #wp-admin-bar-site-name > .ab-item::before,
		.logged-in #wpadminbar #wp-admin-bar-site-name > .ab-item::before {
			background-image: url('<?php echo $iconUrl; ?>') !important;
		}
	</style>
	<?php
}

// Add, Remove and Reorder Items in Admin Bar
add_action( 'wp_before_admin_bar_render', 'battleplan_reorderAdminBar');
function battleplan_reorderAdminBar() {
    global $wp_admin_bar;

	$loc = get_bloginfo( 'description' );
	$locMap = 'https://www.google.com/maps/place/'.str_replace(", ", "+", $loc).'/';

	if (get_bloginfo( 'description' )) $wp_admin_bar->add_node( array( 'id' => 'tagline', 'title' => '-&nbsp;&nbsp;'.$loc.'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', 'href'  => $locMap, ) );

    $IDs_sequence = array('site-name', 'tagline', 'suspend' );
    $nodes = $wp_admin_bar->get_nodes();
    foreach ( $IDs_sequence as $id ) {
        	if ( ! isset($nodes[$id]) ) continue;
	    	$wp_admin_bar->remove_menu($id);
	    	$wp_admin_bar->add_node($nodes[$id]);
        	unset($nodes[$id]);
    }
    foreach ( $nodes as $id => &$obj ) {
        	if ( ! empty($obj->parent) ) continue;
        	$wp_admin_bar->remove_menu($id);
        	$wp_admin_bar->add_node($obj);
    }
	$wp_admin_bar->remove_node('wp-logo');
	$wp_admin_bar->remove_node('wphb');
	$wp_admin_bar->remove_node('updates');
	$wp_admin_bar->remove_node('comments');
    $wp_admin_bar->remove_node('new-content');
    $wp_admin_bar->remove_node('wpengine_adminbar');
	$wp_admin_bar->remove_node('view-site');
	$wp_admin_bar->remove_node('wpseo-menu');
	$wp_admin_bar->remove_node('tribe-events');
	$wp_admin_bar->remove_node('wp-mail-smtp-menu');
}

// Create additional admin pages
add_action( 'admin_menu', 'battleplan_admin_menu' );
function battleplan_admin_menu() {
	$auditTime          = get_option('bp_audit_time')        ? timeElapsed(get_option('bp_audit_time'),        1, 'all', 'full') . ' ago' : 'Never';
	$chronGbpTime       = get_option('bp_chron_a_time')      ? timeElapsed(get_option('bp_chron_a_time'),      1, 'all', 'full') . ' ago' : 'Never';
	$chronGbpApiTime    = get_option('bp_chron_a_api_time')  ? timeElapsed(get_option('bp_chron_a_api_time'),  1, 'all', 'full') . ' ago' : 'Never';
	$chronHouseTime     = get_option('bp_chron_b_time')      ? timeElapsed(get_option('bp_chron_b_time'),      1, 'all', 'full') . ' ago' : 'Never';
	$chronAnalyticsTime = get_option('bp_chron_c_time')      ? timeElapsed(get_option('bp_chron_c_time'),      1, 'all', 'full') . ' ago' : 'Never';

	$siteUpdated = str_replace('-', '', get_option( "site_updated" ));
	//add_menu_page( __( 'Run Chron', 'battleplan' ), __( 'Run Chron', 'battleplan' ), 'manage_options', 'run-chron', 'battleplan_force_run_chron', 'dashicons-performance', 3 );

// Menu registration — no separate Run buttons needed
	if ( _USER_LOGIN === "battleplanweb" ) :
		add_submenu_page( 'index.php', 	'Framework '._BP_VERSION, 	'Framework '._BP_VERSION, 											'manage_options', 	 'themes.php' );
		add_submenu_page( 'index.php',	'⚙️ Run Audit',       		'⚙️ Run Audit',        												'manage_options', 	'run-audit',         		'battleplan_force_run_audit' );
		add_submenu_page( 'index.php',	'Site Audit',          		'Audit <div class="admin-note">'.$auditTime.'</div>',        	'manage_options', 	 'site-audit',      		 'battleplan_site_audit' );
		add_submenu_page( 'index.php',	'Housekeeping', 			'Settings <div class="admin-note">'.$chronHouseTime.'</div>',	'manage_options', 	 'chron-house',     		 'battleplan_chron_housekeeping_status' );
		add_submenu_page( 'index.php',	'GBP Sync',          		'GBP Sync <div class="admin-note">'.$chronGbpApiTime.'</div>',    	'manage_options', 	 'chron-gbp',       		 'battleplan_chron_gbp_status' );
		add_submenu_page( 'index.php',	'Analytics',   				'Stats <div class="admin-note">'.$chronAnalyticsTime.'</div>',	'manage_options', 	 'chron-analytics', 		 'battleplan_chron_analytics_status' );
		add_submenu_page( 'index.php', 	'⚙️ Clear ALL',        		'⚙️ Clear ALL',        												'manage_options', 	'clear-all',         		'battleplan_clear_all' );
		add_submenu_page( 'index.php',	'⚙️ Clear HVAC',       		'⚙️ Clear HVAC',       												'manage_options', 	'clear-hvac',        		'battleplan_clear_hvac' );
		add_submenu_page( 'index.php',	'⚙️ Launch Site',      		'⚙️ Launch Site',       												'manage_options', 	'launch-site',       		'battleplan_launch_site' );
	endif;
}

// Menu registration
function battleplan_addSitePage() {
	echo '<h1>Admin Page</h1>';
}

// Replace WordPress copyright message at bottom of admin page
add_action('in_admin_footer', 'battleplan_admin_footer_text');
function battleplan_admin_footer_text() {
	wp_cache_delete('customer_info', 'options');

	$customer_info = customer_info();

	$printFooter  = '<section><div class="flex" style="grid-template-columns:80px 300px 1fr; gap:20px">';
	$printFooter .= '<div style="grid-row:span 2; align-self:center;">';
	$printFooter .= '<img src="' . esc_url('https://battleplanwebdesign.com/wp-content/uploads/site-icon-80x80.webp') . '" />';
	$printFooter .= '</div>';

	$printFooter .= '<div style="grid-row:span 2; align-self:center;">';
	$printFooter .= 'Powered by <a href="' . esc_url('https://battleplanwebdesign.com') . '" target="_blank" rel="noopener">Battle Plan Web Design</a><br>';
	$printFooter .= 'Launched ' . esc_html( date('F Y', strtotime(get_option('bp_launch_date'))) ) . '<br>';
	$printFooter .= 'Framework ' . esc_html(_BP_VERSION) . '<br>';
	$printFooter .= 'WP ' . esc_html( get_bloginfo('version') ) . '<br>';
	$printFooter .= 'Local Time: ' . esc_html( wp_date('g:i a', null, new DateTimeZone( wp_timezone_string() )) ) . '<br>';
	$printFooter .= '</div>';

	$printFooter .= '<div style="justify-self:end; margin-right:50px;">';

	$email = $customer_info['email'] ?? '';
	if ($email) {
		$printFooter .= '<a class="button" href="mailto:' . esc_attr($email) . '">Contact Email</a>';
	}

	$owner_email = $customer_info['owner-email'] ?? '';
	if ($owner_email) {
		$printFooter .= '<a class="button" href="mailto:' . esc_attr($owner_email) . '">Owner Email</a>';
	}

	$socials = ['facebook','twitter','instagram','pinterest','yelp','tiktok','youtube'];
	foreach ($socials as $key) {
		if (!empty($customer_info[$key])) {
			$printFooter .= '<a class="button" href="' . esc_url($customer_info[$key]) . '" target="_blank" rel="noopener">' . esc_html(ucfirst($key)) . '</a>';
		}
	}

	if (!empty($customer_info['google-tags']['prop-id'])) {
		$prop_id = (int)$customer_info['google-tags']['prop-id'];
		$printFooter .= '<a class="button" href="' . esc_url('https://analytics.google.com/analytics/web/#/p'.$prop_id) . '" target="_blank" rel="noopener">Analytics</a>';
	}

	if (!empty($customer_info['serpfox'])) {
		$printFooter .= '<a class="button" href="' . esc_url('//app.serpfox.com/shared/'.$customer_info['serpfox']) . '" target="_blank" rel="noopener">Keywords</a>';
	}

	$printFooter .= '</div><div style="justify-self:end; margin-bottom:15px;">';

	$placeIDs   = $customer_info['pid'] ?? null;
	$googleInfo = get_option('bp_gbp_update');

	if ($placeIDs) {
		if (!is_array($placeIDs)) $placeIDs = [$placeIDs];

		foreach ($placeIDs as $placeID) {
			$placeID = esc_attr($placeID);
			$info = $googleInfo[$placeID] ?? [];

			$printFooter .= '<div style="float:left; margin-right:50px;">';

			if (strlen($placeID) > 10 && !empty($info['city'])) {
				$printFooter .= '<a class="button" style="margin:0 0 10px -5px" href="' .
					esc_url('https://search.google.com/local/writereview?placeid='.$placeID) .
					'" target="_blank" rel="noopener">GBP: ' .
					esc_html($info['city'] . ', ' . ($info['state-abbr'] ?? '')) .
					'</a><br>';
			}

			$printFooter .= esc_html(($customer_info['area-before'] ?? '') . ($customer_info['area'] ?? '') . ($customer_info['area-after'] ?? '') . ($customer_info['phone'] ?? '')) . '<br>';
			$printFooter .= esc_html($customer_info['street'] ?? '') . '<br>';
			$printFooter .= esc_html(($customer_info['city'] ?? '') . ', ' . ($customer_info['state-abbr'] ?? '') . ' ' . ($customer_info['zip'] ?? '')) . '<br>';

			if (!empty($customer_info['lat']) && !empty($customer_info['long'])) {
				$printFooter .= esc_html($customer_info['lat'] . ', ' . $customer_info['long']) . '<br>';
			}

			$printFooter .= '</div>';
		}
	}

	$printFooter .= "<div style='margin-top: 140px'>";
	if ( ! is_array($customer_info) ) {
		$customer_info = [];
		$printFooter .= "No customer info detected.";
	} else {
		$printFooter .= showMe($customer_info, false, false);
	}
	$printFooter .= "</div>";

	$printFooter .= '</div></div></section>';

	echo do_shortcode($printFooter);
}

// Change Howdy text
add_filter( 'admin_bar_menu', 'battleplan_replace_howdy', 9992 );
function battleplan_replace_howdy( $wp_admin_bar ) {
	$my_account = $wp_admin_bar->get_node('my-account');
	$newtitle = str_replace('Howdy,', '', $my_account->title);
	$wp_admin_bar->add_node( array(
		'id'    => 'my-account',
		'title' => $newtitle,
	));
}

// Re-build <img> tag in WordPress editor
add_filter( 'image_send_to_editor', 'battleplan_remove_junk_from_image', 10, 8 );
function battleplan_remove_junk_from_image( $html, $id, $caption, $title, $align, $url, $size, $alt ) {

	$size_full      = wp_get_attachment_image_src( $id, 'full' );
	$size_requested = wp_get_attachment_image_src( $id, $size );

	if ( ! $size_requested ) {
		return $html;
	}

	$size_slug = ( $size === 'full' ) ? 'orig' : $size;

	$data_orig = '';
	if ( $size_slug !== 'orig' && $size_full ) {
		$data_orig = ' data-orig="' . esc_attr( $size_full[1] . 'x' . $size_full[2] ) . '"';
	}

	$src = str_replace( get_site_url(), '', $size_requested[0] );
	$src = esc_url( $src );

	$alt = ( $alt === get_the_title( $id ) ) ? '' : $alt;

	$width  = (int) $size_requested[1];
	$height = (int) $size_requested[2];

	$class = sprintf(
		'align%s size-%s wp-image-%d',
		sanitize_html_class( $align ),
		sanitize_html_class( $size_slug ),
		(int) $id
	);

	$style = sprintf(
		'aspect-ratio:%d/%d',
		$width,
		$height
	);

	return sprintf(
		'<img src="%s"%s width="%d" height="%d" style="%s" class="%s" alt="%s">',
		$src,
		$data_orig,
		$width,
		$height,
		esc_attr( $style ),
		esc_attr( $class ),
		esc_attr( $alt )
	);
}


// Set the quality of compression on various WordPress generated image sizes
function av_return_100(){ return 67; }
add_filter('jpeg_quality', 'av_return_100', 9999);
add_filter('wp_editor_set_quality', 'av_return_100', 9999);

// Display custom fields in WordPress admin edit screen
//add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Add & Remove WP Admin Menu items
add_action('admin_menu', 'battleplan_customize_admin_menus', 999);
function battleplan_customize_admin_menus() {
	remove_menu_page( 'link-manager.php' );       							// Links
	remove_menu_page( 'edit-comments.php' );       							// Comments
	remove_menu_page( 'wpcf7' );       									// Contact Forms
	remove_menu_page( 'edit.php?post_type=acf-field-group' );       				// Custom Fields
	remove_menu_page( 'themes.php' );       								// Appearance
	remove_menu_page( 'wpengine-common' );   								// WP Engine
	remove_menu_page( 'wp-mail-smtp' );   									// WP Mail SMTP
	remove_menu_page( 'wpseo_dashboard' );   								// Yoast SEO
	remove_menu_page( 'wpseo_workouts' );   								// Yoast SEO
	remove_menu_page( 'post_to_google_my_business');							// Post to GMB

	remove_submenu_page( 'plugins.php', 'plugin-editor.php' );        			// Plugins => Plugin Editor
	remove_submenu_page( 'options-general.php', 'options-writing.php' );   		// Settings => Writing
	remove_submenu_page( 'options-general.php', 'options-reading.php' );   		// Settings => Reading
	remove_submenu_page( 'options-general.php', 'options-media.php' );   			// Settings => Media
	remove_submenu_page( 'options-general.php', 'options-privacy.php' );   		// Settings => Privacy
	remove_submenu_page( 'options-general.php', 'akismet-key-config' );   		// Settings => Akismet
	remove_submenu_page( 'options-general.php', 'git-updater' );   				// Settings => Git Updater
	remove_submenu_page( 'options-general.php', 'git-updater-account' );   		// Settings => Git Updater Account
	remove_submenu_page( 'options-general.php', 'codepress-admin-columns' );   	// Settings => Admin Columns
	remove_submenu_page( 'tools.php', 'export-personal-data.php' );   			// Tools => Export Personal Data
	remove_submenu_page( 'tools.php', 'erase-personal-data.php' );   			// Tools => Erase Personal Data

	remove_submenu_page( 'wpseo_dashboard', 'wpseo_workouts' );   				// Yoast SEO => Workouts
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_licenses' );   				// Yoast SEO => Premium
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_page_academy' );   			// Yoast SEO => Academy
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_tools' );   					// Yoast SEO => Tools
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_integrations' );   			// Yoast SEO => Integrations
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_dashboard' );   				// Yoast SEO => General
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-logs' );   				// WP Mail SMTP => Email Log
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-reports' );   			// WP Mail SMTP => Email Reports
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-about' );   				// WP Mail SMTP => About Us

	add_submenu_page( 'upload.php', 'Favicon', 'Favicon', 'manage_options', 'customize.php' );


	if ( _USER_LOGIN !== "battleplanweb" && !in_array('administrator', _USER_ROLES) ) remove_menu_page( 'edit.php?post_type=elements');
	if ( _USER_LOGIN !== "battleplanweb" && !in_array('administrator', _USER_ROLES) ) remove_menu_page( 'edit.php?post_type=landing');

	if ( _USER_LOGIN !== "battleplanweb" ) remove_menu_page( 'edit.php?post_type=universal');
	if ( _USER_LOGIN !== "battleplanweb" ) remove_menu_page( 'tools.php');
	if ( _USER_LOGIN !== "battleplanweb" ) remove_menu_page( 'edit.php?post_type=stripe_order');


	$query = bp_WP_Query('elements', [
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'asc'
	]);

    if ( $query->have_posts() ) :
        while ( $query->have_posts() ) :
            $query->the_post();
            add_submenu_page( 'edit.php?post_type=elements', get_the_title(), get_the_title(), 'manage_options', '/post.php?post='.get_the_ID().'&action=edit' );
        endwhile;
        wp_reset_postdata();
    endif;

	if ( is_null(get_page_by_path('widgets', OBJECT, 'elements')) ) add_submenu_page( 'edit.php?post_type=elements', 'Widgets', 'Widgets', 'manage_options', 'widgets.php' );

	add_submenu_page( 'edit.php?post_type=elements', 'Menus', 'Menus', 'manage_options', 'nav-menus.php' );
	add_submenu_page( 'edit.php?post_type=elements', 'Contact Forms', 'Contact Forms', 'manage_options', 'admin.php?page=wpcf7' );

	if ( _USER_LOGIN === "battleplanweb" ) add_submenu_page( 'edit.php?post_type=elements', 'Contact Forms Integration', '&nbsp;└&nbsp;Integration', 'manage_options', 'admin.php?page=wpcf7-integration' );

	add_submenu_page( 'edit.php?post_type=elements', 'Comments', 'Comments', 'manage_options', 'edit-comments.php' );
	if ( _USER_LOGIN === "battleplanweb" ) add_submenu_page( 'edit.php?post_type=elements', 'Custom Fields', 'Custom Fields', 'manage_options', 'edit.php?post_type=acf-field-group' );
	if ( _USER_LOGIN === "battleplanweb" ) add_submenu_page( 'options-general.php', 'Options', 'Options', 'manage_options', 'options.php' );
	add_submenu_page( 'tools.php', 'WP Engine', 'WP Engine', 'manage_options', 'options-general.php?page=wpengine-common' );

	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'git-updater/git-updater.php' ) ) add_submenu_page( 'tools.php', 'Git Updater', 'Git Updater', 'manage_options', 'options-general.php?page=git-updater' );
	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'admin-columns-pro/admin-columns-pro.php' ) ) add_submenu_page( 'tools.php', 'Admin Columns', 'Admin Columns', 'manage_options', 'options-general.php?page=codepress-admin-columns' );
	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ) ) add_submenu_page( 'tools.php', 'WP Mail SMTP', 'WP Mail SMTP', 'manage_options', 'options-general.php?page=wp-mail-smtp' );

	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) add_submenu_page( 'tools.php', 'Yoast Settings', 'Yoast Settings', 'manage_options', 'admin.php?page=wpseo_page_settings' );
	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wpseo-local/local-seo.php' ) ) add_submenu_page( 'tools.php', 'Yoast Local', '&nbsp;└&nbsp;Local', 'manage_options', 'admin.php?page=wpseo_local' );
	if ( _USER_LOGIN === "battleplanweb" && is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) add_submenu_page( 'tools.php', 'Yoast Redirects', '&nbsp;└&nbsp;Redirects', 'manage_options', 'admin.php?page=wpseo_redirects' );

	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Settings', 'GBP Settings', 'manage_options', 'admin.php?page=pgmb_settings' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Templates', '&nbsp;└&nbsp;Templates', 'manage_options', 'edit.php?post_type=pgmb_templates' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Calendar', '&nbsp;└&nbsp;Calendar', 'manage_options', 'admin.php?page=post_to_google_my_business' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Account', '&nbsp;└&nbsp;Account', 'manage_options', 'admin.php?page=post_to_google_my_business-account' );

	if (defined('_USER_LOGIN') && _USER_LOGIN === 'battleplanweb') {
		add_submenu_page(
			'edit.php?post_type=jobsite_geo',
			'Refresh Jobsite Tags',
			'⚙️ Refresh Tags',
			'manage_options',
			'refresh-jobsite-tags',
			'bp_refresh_jobsite_tags_page'
		);
	}
}

// Reorder WP Admin Menu Items
add_filter( 'custom_menu_order', 'battleplan_custom_menu_order', 10, 1 );
add_filter( 'menu_order', 'battleplan_custom_menu_order', 10, 1 );
function battleplan_custom_menu_order( $menu_ord ) {
    if ( !$menu_ord ) return true;
	$displayTypes = array('index.php', 'separator1', 'upload.php', 'edit.php?post_type=elements', 'edit.php?post_type=page');
	$getCPT = getCPT();
	foreach ($getCPT as $postType) array_push($displayTypes, 'edit.php?post_type='.$postType);
	array_push($displayTypes, 'edit.php', 'separator2', 'plugins.php', 'options-general.php', 'tools.php', 'users.php', 'separator-last', 'wpengine-common', 'wpseo_dashboard', 'edit.php?post_type=asp-products');
	return $displayTypes;
}

// Reorder WP Admin Sub-Menu Items
add_filter( 'custom_menu_order', 'battleplan_submenu_order' );
function battleplan_submenu_order($menu_ord) {
	global $submenu;

	if (empty($submenu['options-general.php']) || !is_array($submenu['options-general.php'])) {
		return $menu_ord;
	}

	$wanted = [10,15,20,25,30,40,45,49,46,48,47];
	$arr = [];

	foreach ($wanted as $idx) {
		if (isset($submenu['options-general.php'][$idx])) {
			$arr[] = $submenu['options-general.php'][$idx];
		}
	}

	// append anything not captured so you don't lose entries
	foreach ($submenu['options-general.php'] as $idx => $item) {
		if (!in_array($item, $arr, true)) {
			$arr[] = $item;
		}
	}

	$submenu['options-general.php'] = $arr;
	return $menu_ord;
}

// Count number of each post type and add an admin note to the menu button
add_action('admin_menu', 'battleplan_custom_post_type_counts');
function battleplan_custom_post_type_counts() {
	$getCPT = array_diff( get_post_types(), array('elements', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'acf-field-group', 'acf-field', 'wpcf7_contact_form', 'user_request' ) );

	foreach ($getCPT as $postType) :
		$count_posts = wp_count_posts($postType);
		$num_posts = $count_posts->publish > 0 ? $count_posts->publish : 0;
		global $menu;

		foreach ($menu as $key => $value) :
			if ( $menu[$key][2] === 'edit.php?post_type=' . $postType || ( $menu[$key][2] === 'edit.php' && $postType == 'post') ) :
		 		$menu[$key][0] = $menu[$key][0].' <span class="admin-badge-holder count-'.$num_posts.'"><span class="admin-badge">'.$num_posts.'</span></span>';
			 	break;
		  	endif;
		endforeach;
	endforeach;
}

// Remove unwanted dashboard widgets
add_action('wp_dashboard_setup', 'battleplan_remove_dashboard_widgets');
function battleplan_remove_dashboard_widgets () {
	/*
	remove_action('welcome_panel','wp_welcome_panel'); 								//Welcome to WordPress!
	remove_meta_box('wpe_dify_news_feed','dashboard','normal'); 					//WP Engine
	remove_meta_box('wpe_dify_news_feed','dashboard','side'); 						//WP Engine
	*/
	remove_meta_box('dashboard_activity','dashboard','normal');						// Activity
	remove_meta_box('dashboard_activity','dashboard','side');						// Activity
	remove_meta_box('dashboard_right_now','dashboard','normal');					// At A Glance
	remove_meta_box('dashboard_right_now','dashboard','side');						// At A Glance
	remove_meta_box('dashboard_quick_press','dashboard','normal'); 					// Quick Draft
	remove_meta_box('dashboard_quick_press','dashboard','side'); 					// Quick Draft
	remove_meta_box('dashboard_site_health','dashboard','normal');					// Site Health
	remove_meta_box('dashboard_site_health','dashboard','side');					// Site Health
	remove_meta_box('woocommerce_dashboard_status','dashboard','normal');			// Woocommerce
	remove_meta_box('woocommerce_dashboard_status','dashboard','side');				// Woocommerce
	remove_meta_box('dashboard_primary','dashboard','normal'); 						// WordPress Events and News
	remove_meta_box('dashboard_primary','dashboard','side'); 						// WordPress Events and News
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','normal');		// WP Mail SMTP
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','side');			// WP Mail SMTP
	remove_meta_box('wp_mail_smtp_reports_widget_pro','dashboard','normal');		// WP Mail SMTP Pro
	remove_meta_box('wp_mail_smtp_reports_widget_pro','dashboard','side');			// WP Mail SMTP Pro
	remove_meta_box('wpseo-dashboard-overview','dashboard','normal');				// Yoast SEO Posts Overview
	remove_meta_box('wpseo-dashboard-overview','dashboard','side');					// Yoast SEO Posts Overview
	remove_meta_box('wpseo-wincher-dashboard-overview','dashboard','normal');		// Yoast SEO / Wincher Top Keyphrases
	remove_meta_box('wpseo-wincher-dashboard-overview','dashboard','side');			// Yoast SEO / Wincher Top Keyphrases
}

// Load site stats if hooked to Google Analytics
$customer_info = get_option('customer_info');
$prop_id = (is_array($customer_info) && isset($customer_info['google-tags']['prop-id'])) ? (int)$customer_info['google-tags']['prop-id'] : 0;

if ( $prop_id > 1 && is_admin() && (_USER_LOGIN === "battleplanweb" || in_array('bp_view_stats', _USER_ROLES)) ) {
	require_once get_template_directory() . '/functions-admin-stats.php';
}

// Adjust the number of of posts listed on admin pages
add_filter( 'edit_posts_per_page', 'custom_posts_per_page_based_on_type_in_admin', 10, 2 );
function custom_posts_per_page_based_on_type_in_admin( $per_page, $post_type ) {
	/*
		if ( _USER_LOGIN == 'battleplanweb' ) :
			$last_logins = is_array(get_option('bp_last_login')) ? get_option('bp_last_login') : array();
			define( '_LAST_LOGIN', $last_logins);
			$last_logins[$post_type] = time();
			update_option( 'bp_last_login', $last_logins, false);
		endif;

		if ( defined('_LAST_LOGIN') && _LAST_LOGIN[$post_type] < (time() - 30000) ) :
			if( $post_type == 'post' || $post_type == 'page' || $post_type == 'landing' || $post_type == 'galleries' || $post_type == 'attachment' ) : return 30;
			elseif( $post_type == 'testimonials' || $post_type == 'products' || $post_type == 'product' ) : return 30;
			else : return 50;
			endif;
		endif;
	*/

	if ( $post_type == 'testimonials' || $post_type == 'attachment' )  return 30;
    return $per_page;
}

/*--------------------------------------------------------------
# Admin Page Set Up
--------------------------------------------------------------*/
// Add important info as body classes
// Define a function to add the option value to body class
add_filter('admin_body_class', 'battleplan_add_body_classes');
function battleplan_add_body_classes($classes) {
	$customer_info = customer_info();
	$siteType = $customer_info['site-type'] ?? null;
	$bizTypeRaw = $customer_info['business-type'] ?? null;

    if ( $siteType ) $classes .= ' site-type-'.strtolower($siteType);

	if (is_array($bizTypeRaw)) {
		foreach ($bizTypeRaw as $bizType) {
			$bizType = preg_replace('/[^a-zA-Z0-9\s]/', '', $bizType);
			$bizType = preg_replace('/\s+/', '-', trim($bizType));
			if ($bizType) {
				$classes .= ' business-type-' . strtolower($bizType);
			}
		}
	} elseif ($bizTypeRaw) {
		$bizType = preg_replace('/[^a-zA-Z0-9\s]/', '', $bizTypeRaw);
		$bizType = preg_replace('/\s+/', '-', trim($bizType));
		$classes .= ' business-type-' . strtolower($bizType);
	}

	$user = wp_get_current_user();
	if ( $user->exists() ) $classes .= ' user-'.$user->user_login;

    return $classes;
}

// Add "Remove Sidebar" checkbox to Page Attributes meta box
add_action( 'page_attributes_misc_attributes', 'battleplan_remove_sidebar_checkbox', 10, 1 );
function battleplan_remove_sidebar_checkbox($post) {
	echo '<p class="post-attributes-label-wrapper">';
	$getRemoveSidebar = get_post_meta($post->ID, "_bp_remove_sidebar", true);

	if ( $getRemoveSidebar == "" ) :
		echo '<input name="remove_sidebar" type="checkbox" value="true">';
	else:
		echo '<input name="remove_sidebar" type="checkbox" value="true" checked>';
	endif;

	echo '<label class="post-attributes-label" for="remove_sidebar">Remove Sidebar</label>';
}

add_action('save_post', 'battleplan_save_remove_sidebar', 10, 3);
function battleplan_save_remove_sidebar($post_id, $post, $update) {
	if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || ( defined('DOING_AJAX') && DOING_AJAX ) || !current_user_can('edit_post', $post_id) ) return;

	$lastViewed = readMeta( $post_id, 'log-last-viewed' );
	if ( !$lastViewed ) updateMeta( $post_id, 'log-last-viewed', strtotime("-2 days"));

    $updateRemoveSidebar = "";
    if ( isset($_POST["remove_sidebar"]) ) $updateRemoveSidebar = $_POST["remove_sidebar"];
    update_post_meta($post_id, "_bp_remove_sidebar", $updateRemoveSidebar);

	// check for duplicate before posting a new testimonial
	if ( $post->post_type == 'testimonials') :
		$new_post_title = $post->post_title;
		$query = bp_WP_Query('testimonials', [
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => [$post_id]
		]);

		$found_duplicate = false;

		if ($query->have_posts()) :	while ($query->have_posts()) : $query->the_post();
				if ( strtolower(get_the_title()) == strtolower($new_post_title)) :
					$found_duplicate = true;
					$existing_post_id = get_the_ID();
					break;
				endif;
			endwhile;
		endif;

		wp_reset_postdata();

		if ($found_duplicate) :
			wp_delete_post($post_id, true);
			$edit_post_url = get_edit_post_link($existing_post_id, 'raw');
			wp_redirect($edit_post_url);
			exit;
		endif;
	endif;
}

// Add "duplicate post/page" function to WP core
add_action( 'admin_action_battleplan_duplicate_post_as_draft', 'battleplan_duplicate_post_as_draft' );
function battleplan_duplicate_post_as_draft(){
	global $wpdb;

	if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'battleplan_duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) wp_die('No post to duplicate has been supplied!');
	if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )	return;

	$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
	$post = get_post( $post_id );
	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;
	if (isset( $post ) && $post != null) :
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);
		$new_post_id = wp_insert_post( $args );
		$taxonomies = get_object_taxonomies($post->post_type);
		foreach ($taxonomies as $taxonomy) :
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		endforeach;

		$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
		if (count($post_meta_infos)!=0) :
			$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			foreach ($post_meta_infos as $meta_info) :
				$meta_key = $meta_info->meta_key;
				if( $meta_key == '_wp_old_slug' ) continue;
				$meta_value = addslashes($meta_info->meta_value);
				$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
			endforeach;
			$sql_query.= implode(" UNION ALL ", $sql_query_sel);
			$wpdb->query($sql_query);
		endif;

		updateMeta( $new_post_id, 'log-last-viewed', strtotime("-2 days"));
		updateMeta( $new_post_id, 'log-views-today', '0' );
		updateMeta( $new_post_id, 'log-views-total-7day', '0' );
		updateMeta( $new_post_id, 'log-views-total-30day', '0' );
		updateMeta( $new_post_id, 'log-views-total-90day', '0' );
		updateMeta( $new_post_id, 'log-views-total-365day', '0' );
		updateMeta( $new_post_id, 'log-views', array( 'date'=> strtotime(date("F j, Y")), 'views' => 0 ));

		wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	else :
		wp_die('Post creation failed, could not find original post: '.$post_id);
	endif;
}

// Replace Page & Post links with icons
add_filter( 'post_row_actions', 'battleplan_post_row_actions', 90, 2 );
add_filter( 'page_row_actions', 'battleplan_post_row_actions', 90, 2 );
function battleplan_post_row_actions( $actions, $post ) {
	$out = [];

	if (isset($actions['edit'])) {
		$edit = str_replace("Edit", "<i class='dashicons-edit'></i>", $actions['edit']);
		$out['edit'] = str_replace("<a href", "<a title='Edit' target='_blank' rel='noopener' href", $edit);
	}

	$out['inline hide-if-no-js'] = '<button type="button" class="button-link editinline" aria-label="Quick edit" aria-expanded="false"><i class="dashicons-quick-edit"></i></button>';

	$out['duplicate'] = '<a target="_blank" rel="noopener" href="' .
		wp_nonce_url('admin.php?action=battleplan_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') .
		'" title="Clone" rel="permalink"><i class="dashicons-clone"></i></a>';

	if (isset($actions['view'])) {
		$view = str_replace(["View","Preview"], "<i class='dashicons-view'></i>", $actions['view']);
		$out['view'] = str_replace("<a href", "<a title='View' target='_blank' rel='noopener' href", $view);
	}

	if (isset($actions['trash'])) {
		$delete = str_replace("Trash", "<i class='dashicons-trash'></i>", $actions['trash']);
		$out['delete'] = str_replace("<a href", "<a title='Delete' href", $delete);
	}

	return $out;
}


// Replace Media Library image links with icons
add_filter('media_row_actions', 'battleplan_media_row_actions', 90, 2);
function battleplan_media_row_actions( $actions, $post ) {
	$out = [];

	if (isset($actions['edit'])) {
		$edit = str_replace("Edit", "<i class='dashicons-edit'></i>", $actions['edit']);
		$out['edit'] = str_replace("<a href", "<a title='Edit Media' target='_blank' rel='noopener' href", $edit);
	}

	if (isset($actions['view'])) {
		$view = str_replace("View", "<i class='dashicons-view'></i>", $actions['view']);
		$out['view'] = str_replace("<a href", "<a title='View Media' target='_blank' rel='noopener' href", $view);
	}

	if (isset($actions['media_replace'])) {
		$rep = str_replace("Replace media", "<i class='dashicons-replace'></i>", $actions['media_replace']);
		$out['media_replace'] = str_replace("<a href", "<a title='Replace Media' target='_blank' rel='noopener' href", $rep);
	}

	if (isset($actions['delete'])) {
		$del = str_replace("Delete Permanently", "<i class='dashicons-trash'></i>", $actions['delete']);
		$out['delete'] = str_replace("<a href", "<a title='Delete Media' href", $del);
	}

	return $out;
}


// Replace Users links with icons
add_filter( 'user_row_actions', 'battleplan_user_row_actions', 90, 2 );
function battleplan_user_row_actions($actions, $user_object) {
	$out = [];

	if (isset($actions['edit'])) {
		$out['edit'] = str_replace("Edit", "<i class='dashicons-edit'></i>", $actions['edit']);
	}
	if (isset($actions['delete'])) {
		$out['delete'] = str_replace("Delete", "<i class='dashicons-trash'></i>", $actions['delete']);
	}
	if (isset($actions['switch_to_user'])) {
		$out['switch_to_user'] = str_replace("Switch&nbsp;To", "<i class='dashicons-randomize'></i>", $actions['switch_to_user']);
	}

	return $out;
}

// Automatically set the image Title, Alt-Text, Caption & Description upon upload
add_action( 'add_attachment', 'battleplan_setImageMetaUponUpload' );
function battleplan_setImageMetaUponUpload( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) :
		$imageTitle = get_post( $post_ID )->post_title;
		$imageTitle = ucwords( preg_replace( '%\s*[-_\s]+\s*%', ' ', $imageTitle )); // remove hyphens, underscores & extra spaces and capitalize
		$imageMeta = array ( 'ID' => $post_ID, 'post_title' => $imageTitle ) /* post title */;
		update_post_meta( $post_ID, '_wp_attachment_image_alt', $imageTitle ) /* alt text */;
		wp_update_post( $imageMeta );
	endif;
}

// Add 'log-views' fields to an image when it is uploaded
add_action( 'add_attachment', 'battleplan_addWidgetPicViewsToImg' );
function battleplan_addWidgetPicViewsToImg( $post_ID ) {
	if ( wp_attachment_is_image( $post_ID ) ) :
		updateMeta( $post_ID, 'log-last-viewed', strtotime("-2 days"));
		updateMeta( $post_ID, 'log-views-today', '0' );
		updateMeta( $post_ID, 'log-views-total-7day', '0' );
		updateMeta( $post_ID, 'log-views-total-30day', '0' );
		updateMeta( $post_ID, 'log-views-total-90day', '0' );
		updateMeta( $post_ID, 'log-views-total-365day', '0' );
		updateMeta( $post_ID, 'log-views', array( 'date'=> strtotime(date("F j, Y")), 'views' => 0 ));
	endif;
}

// Add 'image-category' to testimonials and jobsite geo posts
add_action('add_attachment', 'battleplan_auto_add_image_category');
function battleplan_auto_add_image_category($attachment_id) {
    $parent_post_id = get_post($attachment_id)->post_parent;
    $parent_post = get_post($parent_post_id);

    if ($parent_post && $parent_post->post_type === 'jobsite_geo') {
        $term = 'Jobsite GEO';
        if (!term_exists($term, 'image-categories')) wp_insert_term($term, 'image-categories');
        wp_set_object_terms($attachment_id, $term, 'image-categories', true);
    }

    if ($parent_post && $parent_post->post_type === 'testimonials') {
        $term = 'Testimonials';
        if (!term_exists($term, 'image-categories')) wp_insert_term($term, 'image-categories');
        wp_set_object_terms($attachment_id, $term, 'image-categories', true);
    }
}

function battleplan_force_run_audit() {
    $customerInfo  = customer_info();
    $auditInterval = isset($customerInfo['audit_delay'])
        ? (int) $customerInfo['audit_delay']
        : (86400 * 90);

    update_option('bp_audit_time', time());
    update_option('bp_audit_next', time() + $auditInterval + rand(0, 3600));
    require_once get_template_directory() . '/functions-site-audit.php';
    bp_run_site_audit();
    wp_safe_redirect(admin_url('index.php?page=site-audit'));
    exit;
}

function battleplan_chron_gbp_status() {
    update_option('bp_chron_a_time', time());
    update_option('bp_chron_a_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-gbp.php';
    bp_run_chron_gbp(true);
    wp_safe_redirect(admin_url());
    exit;
}

function battleplan_chron_housekeeping_status() {
    update_option('bp_chron_b_time', time());
    update_option('bp_chron_b_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-housekeeping.php';
    bp_run_chron_housekeeping(true);
    wp_safe_redirect(admin_url());
    exit;
}

function battleplan_chron_analytics_status() {
    update_option('bp_chron_c_time', time());
    update_option('bp_chron_c_next', bp_next_nightly_window());
    require_once get_template_directory() . '/functions-chron-analytics.php';
    bp_run_chron_analytics(true);
    wp_safe_redirect(admin_url());
    exit;
}


// Add dialog boxes to shortcode helpers in text editor
add_action('admin_enqueue_scripts', 'battleplan_setupTextEditorDialogBoxes');
function battleplan_setupTextEditorDialogBoxes($hook) {
	$screen_ok = ($hook === 'post.php' || $hook === 'post-new.php');
	if(!$screen_ok) return;

	// ensure your admin JS is already enqueued; adjust handle/path if needed ---- maybe can remove.
	bp_enqueue_script( 'battleplan-admin-script', 'script-admin', ['quicktags'] );


	$bp_qtags_cfg = [
		'section' => [
			'label' => 'Section',
			'wrap' => true,
			'defaults' => [ 'name'=>'', 'class'=>'', 'style'=>'', 'width'=>'default', 'break'=>'', 'valign'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'/wp-content/uploads/', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'grid'=>'', 'data'=>'' ],
			'fields' => [
				[ 'name'=>'name', 'type'=>'text', 'label'=>'Name (id)' ],
				[ 'name'=>'style', 'type'=>'select-custom', 'label'=>'Style',
					'choices' => [ '' => 'none', '_1' => '1', '_2' => '2', '_3' => '3', '_4' => '4', 'lock' => 'lock', 'custom' => 'custom' ] ],
				[ 'name'=>'width', 'type'=>'select', 'label'=>'Width',
					'choices'=>[ ''=>'default', 'stretch'=>'stretch', 'full'=>'full', 'edge'=>'edge', 'inline'=>'inline' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'break', 'type'=>'select', 'label'=>'Break',
					'choices' => [ '' => 'none', '_4' => '4', '_3' => '3', '_2' => '2', '_1' => '1' ] ],
				[ 'name'=>'valign', 'type'=>'select', 'label'=>'V-Align',
					'choices'=>[ ''=>'none', 'center'=>'center', 'stretch'=>'stretch', 'start'=>'start', 'end'=>'end' ] ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'id' => 'name (id)', 'custom' => 'custom' ] ],
				[ 'name'=>'background', 'type'=>'text', 'label'=>'Background' ],
				[ 'name'=>'left', 'type'=>'text', 'label'=>'Left %' ],
				[ 'name'=>'top', 'type'=>'text', 'label'=>'Top %' ],
				[ 'name'=>'css', 'type'=>'text', 'label'=>'CSS (i.e. width="100px"; height="100px")' ],
				[ 'name'=>'hash', 'type'=>'text', 'label'=>'Compensation for scroll on one-page site' ],
				[ 'name'=>'grid', 'type'=>'text', 'label'=>'Grid (eliminates layout)' ],
				[ 'name'=>'data', 'type'=>'text', 'label'=>'data-field' ],
			],
			'content_placeholder' => "\n\n"
		],
		'layout' => [
			'label' => 'Layout',
			'wrap' => true,
			'defaults' => [ 'name'=>'', 'grid'=>'1', 'gap'=>'', 'break'=>'', 'valign'=>'', 'class'=>'', 'track'=>'', 'data'=>'' ],
			'fields' => [
				[ 'name'=>'name', 'type'=>'text', 'label'=>'Name (id)' ],
				[ 'name'=>'grid', 'type'=>'text', 'label'=>'Grid' ],
				[ 'name'=>'gap', 'type'=>'text', 'label'=>'Gap' ],
				[ 'name'=>'break', 'type'=>'select', 'label'=>'Break',
					'choices' => [ '' => 'none', '_4' => '4', '_3' => '3', '_2' => '2', '_1' => '1' ] ],
				[ 'name'=>'valign', 'type'=>'select', 'label'=>'V-Align',
					'choices'=>[ ''=>'none', 'center'=>'center', 'stretch'=>'stretch', 'start'=>'start', 'end'=>'end' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking' ],
				[ 'name'=>'data', 'type'=>'text', 'label'=>'data-field' ],
			],
			'content_placeholder' => "\n\n"
		],
		'column' => [
			'label' => 'Column',
			'wrap' => true,
			'defaults' => [ 'name'=>'', 'class'=>'', 'order'=>'', 'break'=>'', 'align'=>'', 'valign'=>'', 'h-span'=>'', 'v-span'=>'', 'start'=>'', 'end'=>'', 'track'=>'', 'background'=>'/wp-content/uploads/', 'left'=>'50', 'top'=>'50', 'css'=>'', 'hash'=>'', 'gap'=>'', 'data'=>'' ],
			'fields' => [
				[ 'name'=>'name', 'type'=>'text', 'label'=>'Name (id)' ],
				[ 'name'=>'align', 'type'=>'select', 'label'=>'Align',
					'choices'=>[ ''=>'none', 'left'=>'left', 'right'=>'right', 'center'=>'center' ] ],
				[ 'name'=>'valign', 'type'=>'select', 'label'=>'V-Align',
					'choices'=>[ ''=>'none', 'center'=>'center', 'stretch'=>'stretch', 'start'=>'start', 'end'=>'end' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'h-span', 'type'=>'text', 'label'=>'H-Span' ],
				[ 'name'=>'v-span', 'type'=>'text', 'label'=>'V-Span' ],
				[ 'name'=>'break', 'type'=>'select', 'label'=>'Break',
					'choices' => [ '' => 'none', '_4' => '4', '_3' => '3', '_2' => '2', '_1' => '1' ] ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'id' => 'name (id)', 'custom' => 'custom' ] ],
				[ 'name'=>'gap', 'type'=>'text', 'label'=>'Gap' ],
				[ 'name'=>'background', 'type'=>'text', 'label'=>'Background' ],
				[ 'name'=>'left', 'type'=>'text', 'label'=>'Left %' ],
				[ 'name'=>'top', 'type'=>'text', 'label'=>'Top %' ],
				[ 'name'=>'css', 'type'=>'text', 'label'=>'CSS (i.e. width="100px"; height="100px")' ],
				[ 'name'=>'hash', 'type'=>'text', 'label'=>'Compensation for scroll on one-page site' ],
				[ 'name'=>'data', 'type'=>'text', 'label'=>'data-field' ],
			],
			'content_placeholder' => "\n\n"
		],
		'group' => [
			'label' => 'Group',
			'wrap' => true,
			'defaults' => [ 'size'=>'100', 'class'=>'', 'order'=>'', 'start'=>'', 'end'=>'','track'=>'' ],
			'fields' => [
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => "\n\n"
		],
		'text' => [
			'label' => 'Text',
			'wrap' => true,
			'defaults' => [ 'size'=>'100', 'class'=>'', 'order'=>'', 'start'=>'', 'end'=>'','track'=>'' ],
			'fields' => [
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => "\n\n"
		],
		'image' => [
			'label' => 'Image',
			'wrap' => true,
			'defaults' => [ 'size'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'get-biz'=>'', 'new-tab'=>'', 'ada-hidden'=>'false', 'start'=>'', 'end'=>'', 'track'=>'' ],
			'fields' => [
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'link', 'type'=>'text', 'label'=>'URL image links to' ],
				[ 'name'=>'get-biz', 'type'=>'text', 'label'=>'[get-biz info="..."]' ],
				[ 'name'=>'new-tab', 'type'=>'select', 'label'=>'New Tab',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'ada-hidden', 'type'=>'select', 'label'=>'ADA Hidden',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => ""
		],
		'video' => [
			'label' => 'Video',
			'wrap' => false,
			'defaults' => [ 'size'=>'100', 'mobile'=>'100', 'class'=>'', 'order'=>'', 'link'=>'', 'thumb'=>'/wp-content/uploads/', 'start'=>'', 'end'=>'', 'preload'=>'false', 'related'=>'false', 'fullscreen'=>'false', 'controls'=>'true', 'autoplay'=>'false', 'loop'=>'false', 'muted'=>'false', 'begin'=>'', 'track'=>'' ],
			'fields' => [
				[ 'name'=>'link', 'type'=>'text', 'label'=>'URL of video' ],
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Desktop Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'mobile', 'type'=>'select', 'label'=>'Mobile Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'thumb', 'type'=>'text', 'label'=>'Thumbnail' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end',   'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'preload', 'type'=>'select', 'label'=>'Preload',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'related', 'type'=>'select', 'label'=>'Show Related',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'fullscreen', 'type'=>'select', 'label'=>'Fullscreen',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'controls', 'type'=>'select', 'label'=>'Show Controls',
					'choices'=>[ 'true'=>'yes', 'false'=>'no'   ] ],
				[ 'name'=>'autoplay', 'type'=>'select', 'label'=>'Autoplay',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'loop', 'type'=>'select', 'label'=>'Loop',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'muted', 'type'=>'select', 'label'=>'Muted',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'begin', 'type'=>'text', 'label'=>'Begin at' ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => "\n"
		],
		'button' => [
			'label' => 'Button',
			'wrap' => true,
			'defaults' => [ 'link'=>'', 'size'=>'100', 'align'=>'center', 'class'=>'', 'order'=>'', 'get-biz'=>'', 'new-tab'=>'false', 'fancy'=>'false', 'icon'=>'false', 'top'=>0, 'left'=>0, 'graphic'=>'false', 'graphic-w'=>'40', 'start'=>'', 'end'=>'', 'ada'=>'', 'track'=>'', 'onclick'=>'' ],
			'fields' => [
				[ 'name'=>'link', 'type'=>'text', 'label'=>'URL button links to' ],
				[ 'name'=>'size', 'type'=>'select', 'label'=>'Desktop Size',
					'choices'=>[ '100'=>'100%', '1/2'=>'1/2', '1/3'=>'1/3', '1/4'=>'1/4', '1/6'=>'1/6', '1/12'=>'1/12'   ] ],
				[ 'name'=>'align', 'type'=>'select', 'label'=>'Align',
					'choices'=>[ ''=>'none', 'left'=>'left', 'right'=>'right', 'center'=>'center' ] ],
				[ 'name'=>'class', 'type'=>'text', 'label'=>'Class' ],
				[ 'name'=>'order', 'type'=>'text', 'label'=>'Order' ],
				[ 'name'=>'get-biz', 'type'=>'text', 'label'=>'[get-biz info="..."]' ],
				[ 'name'=>'new-tab', 'type'=>'select', 'label'=>'New Tab',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'fancy', 'type'=>'select', 'label'=>'Fancy Button',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'icon', 'type'=>'select', 'label'=>'Icon',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'left', 'type'=>'text', 'label'=>'Left px' ],
				[ 'name'=>'top', 'type'=>'text', 'label'=>'Top px' ],
				[ 'name'=>'graphic', 'type'=>'select', 'label'=>'Graphic',
					'choices'=>[ 'false'=>'no', 'true'=>'yes'   ] ],
				[ 'name'=>'graphic-w', 'type'=>'text', 'label'=>'Graphic width' ],
				[ 'name'=>'start', 'type'=>'date', 'label'=>'Start' ],
				[ 'name'=>'end', 'type'=>'date', 'label'=>'End' ],
				[ 'name'=>'ada', 'type'=>'text', 'label'=>'ADA Text', ],
				[ 'name'=>'track', 'type'=>'select-custom', 'label'=>'Tracking',
					'choices' => [ '' => 'none', 'custom' => 'custom' ] ],
			],
			'content_placeholder' => ""
		],
	];

	/*

	$a = shortcode_atts( array(  ), $atts );

		QTags.addButton( 'bp_video', 'video', '   [vid size="100 1/2 1/3 1/4 1/6 1/12" order="1, 2, 3" link="url of video" thumb="url of thumb, if not using auto" preload="false, true" class="" related="false, true" start="YYYY-MM-DD" end="YYYY-MM-DD"]', '[/vid]\n', 'video', 'Video', 1000 );


	*/

	wp_localize_script('battleplan-admin-script', 'BP_QTAGS_CFG', $bp_qtags_cfg);
}



// Set up brand new site
function battleplan_clear_all() {
	battleplan_clear_hvac(true);
}

function battleplan_clear_hvac($all=false) {
	$deleteImgs = array ('testimonials', 'photos', 'graphics', 'logos', 'jobsite-geo');
	$keepPages = array ('home', 'contact-us', 'product-overview');
	$keepElements = array ('site-header', 'widgets');

	$elements = get_posts( array('post_type'=>'elements', 'numberposts'=>-1) );
	$pages = get_posts( array('post_type'=>'page', 'numberposts'=>-1) );
	$landing = get_posts( array('post_type'=>'landing', 'numberposts'=>-1) );
	$testimonials = get_posts( array('post_type'=>'testimonials', 'numberposts'=>-1) );
	$galleries = get_posts( array('post_type'=>'galleries', 'numberposts'=>-1) );
	$jobsites = get_posts( array('post_type'=>'jobsite_geo', 'numberposts'=>-1) );
	$posts = get_posts( array('post_type'=>'post', 'numberposts'=>-1) );
	$woo_products = get_posts( array('post_type'=>'product', 'numberposts'=>-1) );
	$woo_orders = get_posts( array('post_type'=>'shop_order', 'numberposts'=>-1) );
	$users = get_users( array('fields' => array('ID', 'user_login'),));

	if ( $all == true ) :
		$products = get_posts( array('post_type'=>'products', 'numberposts'=>-1) );
		foreach ($products as $post) wp_delete_post( $post->ID, true );
		array_push($deleteImgs, 'products');
		if (in_array('product-overview', $keepPages)) unset($keepPages[array_search('product-overview', $keepPages)]);
	endif;

	foreach ($elements as $post) if ( !in_array( $post->post_name, $keepElements) ) wp_delete_post( $post->ID, true );
	foreach ($pages as $post) if ( !in_array( $post->post_name, $keepPages) ) wp_delete_post( $post->ID, true );
	foreach ($landing as $post) wp_delete_post( $post->ID, true );
	foreach ($testimonials as $post) wp_delete_post( $post->ID, true );
	foreach ($galleries as $post) wp_delete_post( $post->ID, true );
	foreach ($jobsites as $post) wp_delete_post( $post->ID, true );
	foreach ($posts as $post) wp_delete_post( $post->ID, true );
	foreach ($woo_products as $post) wp_delete_post( $post->ID, true );
	foreach ($woo_orders as $post) wp_delete_post( $post->ID, true );

	foreach ($users as $user) :
	 	if ($user->user_login !== 'battleplanweb') :
			require_once(ABSPATH.'wp-admin/includes/user.php' );
        		wp_delete_user($user->ID);
		endif;
	endforeach;

	$query = bp_WP_Query('attachment', [
		'post_status'     => 'inherit',
		'posts_per_page'  => -1,
		'mime_type'       => 'image',
		'tax_query'       => [
			'relation' => 'OR',
			[
				'taxonomy' => 'image-categories',
				'terms'    => $deleteImgs,
				'field'    => 'slug'
			],
			[
				'taxonomy' => 'image-categories',
				'operator' => 'NOT EXISTS'
			]
		]
	]);

	if ( $query->have_posts() ) :
		while ( $query->have_posts() ) :
			$query->the_post();
			$keepImg = array( 'logo.png', 'logo.webp', 'site-icon.png', 'site-icon.webp', 'favicon.png', 'favicon.webp');
			if ( !in_array( basename( get_attached_file( get_the_ID() )), $keepImg) ) wp_delete_attachment( get_the_ID(), true );
		endwhile;
		wp_reset_postdata();
	endif;

	header("Location: /wp-admin/");
	exit();
}

function battleplan_launch_site() {
	delete_option('bp_gbp_update');
	delete_option('bp_site_audit_details');

	updateOption('bp_chron_time', 0);
	updateOption('bp_launch_date', date('Y-m-d'));

	header("Location: /wp-admin/");
	exit();
}




function bp_refresh_jobsite_tags_page() {
	if (!current_user_can('manage_options')) {
		wp_die(__('You do not have permission to access this page.'));
	}

	echo '<div class="wrap"><h1>refresh Jobsite Tags</h1>';

	// 🔍 Debug output so we can tell if form submission is detected
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if (isset($_POST['bp_run_refresh'])) {

			$jobsites = get_posts([
				'post_type'      => 'jobsite_geo',
				'post_status'    => ['publish', 'draft', 'pending'],
				'posts_per_page' => -1,
			]);

			$total   = count($jobsites);
			$success = 0;

			foreach ($jobsites as $j) {
				// If your original processing function is called battleplan_saveJobsite(),
				// you can call it directly:
				battleplan_saveJobsite($j->ID, get_post($j->ID), true);
				$success++;
			}

			bp_cleanup_empty_service_tags();

			echo '<div class="notice notice-success"><p><strong>✅ Refresh Complete:</strong> '
				. esc_html($success) . ' of ' . esc_html($total) . ' jobsites processed.</p></div>';
		} else {
			echo '<p><strong>No form variable detected.</strong></p>';
		}
	}

	// Render form
	?>
	<form method="post" onsubmit="return confirm('Are you sure you want to refresh all Jobsite tags?');">
		<?php submit_button('Run Refresh', 'primary', 'bp_run_refresh'); ?>
	</form>
	</div>
	<?php
}

add_action('admin_head', function(){
	$logo = get_option('bp_site_logo');
	echo '<style>
		div[data-slug="battleplantheme"] div.theme-screenshot {
			background: url("/wp-content/themes/battleplantheme/screenshot.png") no-repeat 50% 50% !important;
			background-size: contain !important;
		}
		.theme-browser .theme .theme-screenshot.blank,
		div[data-slug="battleplantheme-site"] div.theme-screenshot {
			background: url("/wp-content/uploads/'.$logo.'") no-repeat 50% 50% !important;
			background-size: contain !important;
		}
	</style>';
});



/*--------------------------------------------------------------
# Site Audit — Read-Only Auto Table
# Data populated automatically from bp_site_audit on each chron run
# Historical data stored in bp_site_audit_details
--------------------------------------------------------------*/

function battleplan_site_audit() {

    $customer_info   = get_option('customer_info');
    $siteType        = is_array($customer_info) ? ($customer_info['site-type'] ?? '') : '';
    $siteAudit       = get_option('bp_site_audit_details') ?: [];
    $launchDate      = get_option('bp_launch_date');
    $launchTs        = $launchDate ? strtotime($launchDate) : null;
    $daysSinceLaunch = $launchTs ? (int)((time() - $launchTs) / 86400) : 9999;

    // One-time legacy migration
    $migrationDone = get_option('bp_audit_migration_done');

    if (!$migrationDone) {


	$legacyMap = [
		'lighthouse-mobile-score'  => 'lighthouse-mobile-score',
		'lighthouse-mobile-fcp'    => 'lighthouse-mobile-fcp',
		'lighthouse-mobile-lcp'    => 'lighthouse-mobile-lcp',
		'lighthouse-mobile-tbt'    => 'lighthouse-mobile-tbt',
		'lighthouse-mobile-si'     => 'lighthouse-mobile-si',
		'lighthouse-mobile-cls'    => 'lighthouse-mobile-cls',
		'lighthouse-desktop-score' => 'lighthouse-desktop-score',
		'lighthouse-desktop-fcp'   => 'lighthouse-desktop-fcp',
		'lighthouse-desktop-lcp'   => 'lighthouse-desktop-lcp',
		'lighthouse-desktop-tbt'   => 'lighthouse-desktop-tbt',
		'lighthouse-desktop-si'    => 'lighthouse-desktop-si',
		'lighthouse-desktop-cls'   => 'lighthouse-desktop-cls',
		'back-total-links'         => 'back-total-links',
		'back-domains'             => 'back-domains',
		'back-local-links'         => 'cite-key-citations',
		'cite-citations'           => 'cite-citations',
		'cite-key-citations'       => 'cite-key-citations',
		'cite-citation-score'      => 'cite-citation-score',
		'console-clicks'      => 'console-clicks-28',
		'console-position'    => 'console-position-28',
		'console-impressions' => 'console-impressions-28',
		'console-ctr'         => 'console-ctr-28',
		'gmb-overview'             => 'gmb-impressions',
		'gmb-calls'                => 'gmb-calls',
		'gmb-clicks'               => 'gmb-website-clicks',
		'google-reviews'           => 'google-reviews',
		'google-rating'            => 'google-rating',
		'load_time_mobile'         => 'load_time_mobile',
		'load_time_desktop'        => 'load_time_desktop',
	];

	   foreach ($siteAudit as $date => $entry) {

		 // if (isset($entry['ga4-sessions-30'])) continue;

		  $migrated = [];
		  foreach ($legacyMap as $oldKey => $newKey) {
			 if (isset($entry[$oldKey]) && $entry[$oldKey] !== '—') {
				$migrated[$newKey] = $entry[$oldKey];
			 }
		  }
		  foreach ($entry as $k => $v) {
			 if (!isset($migrated[$k])) $migrated[$k] = $v;
		  }

		  $siteAudit[$date] = $migrated;
	   }

		updateOption('bp_site_audit_details', $siteAudit, false);
		update_option('bp_audit_migration_done', true);
	}

    // -------------------------------------------------------
    // Define row structure for the table
    // -------------------------------------------------------
    $sections = [

        'PageSpeed — Mobile' => [
            'lighthouse-mobile-score' => 'Performance Score',
            'lighthouse-mobile-fcp'   => 'First Contentful Paint',
            'lighthouse-mobile-lcp'   => 'Largest Contentful Paint',
            'lighthouse-mobile-tbt'   => 'Total Blocking Time',
            'lighthouse-mobile-si'    => 'Speed Index',
            'lighthouse-mobile-cls'   => 'Cumulative Layout Shift',
            'lighthouse-mobile-acc'   => 'Accessibility',
            'lighthouse-mobile-seo'   => 'SEO Score',
        ],

        'PageSpeed — Desktop' => [
            'lighthouse-desktop-score' => 'Performance Score',
            'lighthouse-desktop-fcp'   => 'First Contentful Paint',
            'lighthouse-desktop-lcp'   => 'Largest Contentful Paint',
            'lighthouse-desktop-tbt'   => 'Total Blocking Time',
            'lighthouse-desktop-si'    => 'Speed Index',
            'lighthouse-desktop-cls'   => 'Cumulative Layout Shift',
            'lighthouse-desktop-acc'   => 'Accessibility',
            'lighthouse-desktop-seo'   => 'SEO Score',
        ],

        'Load Speed (Real Users)' => [
            'load_time_mobile'     => 'Mobile Avg Load Time',
            'load_time_desktop'    => 'Desktop Avg Load Time',
            'speed-mobile-target'  => 'Mobile On Target',
            'speed-desktop-target' => 'Desktop On Target',
        ],

		'GA4 Traffic' => [
			'ga4-sessions-7'    => 'Sessions (7d)',
			'ga4-pageviews-7'   => 'Pageviews (7d)',
			'ga4-engagement-7'  => 'Engagement Rate (7d)',
			'ga4-sessions-30'   => 'Sessions (1m)',
			'ga4-pageviews-30'  => 'Pageviews (1m)',
			'ga4-engagement-30' => 'Engagement Rate (1m)',
			'ga4-sessions-90'   => 'Sessions (3m)',
			'ga4-pageviews-90'  => 'Pageviews (3m)',
			'ga4-engagement-90' => 'Engagement Rate (3m)',
			'ga4-sessions-180'    => 'Sessions (6m)',
			'ga4-pageviews-180'   => 'Pageviews (6m)',
			'ga4-engagement-180'  => 'Engagement Rate (6m)',
			'ga4-sessions-365'    => 'Sessions (1yr)',
			'ga4-pageviews-365'   => 'Pageviews (1yr)',
			'ga4-engagement-365'  => 'Engagement Rate (1yr)',
			'ga4-phone-30'      => 'Phone Clicks (1m)',
			'ga4-email-30'      => 'Email Clicks (1m)',
		],

        'Search Console' => [
			'console-impressions-30'  => 'Impressions (1m)',
			'console-clicks-30'       => 'Clicks (1m)',
			'console-ctr-30'          => 'CTR (1m)',
			'console-position-30'     => 'Avg Position (1m)',
			'console-impressions-90'  => 'Impressions (3m)',
			'console-clicks-90'       => 'Clicks (3m)',
			'console-ctr-90'          => 'CTR (3m)',
			'console-position-90'     => 'Avg Position (3m)',
			'console-impressions-180' => 'Impressions (6m)',
			'console-clicks-180'      => 'Clicks (6m)',
			'console-ctr-180'         => 'CTR (6m)',
			'console-position-180'    => 'Avg Position (6m)',
			'console-impressions-365' => 'Impressions (12m)',
			'console-clicks-365'      => 'Clicks (12m)',
			'console-ctr-365'         => 'CTR (12m)',
			'console-position-365'    => 'Avg Position (12m)',
		],

        'Backlinks' => [
            'back-total-links' 		=> 'Total Links',
            'back-domains'     		=> 'Linking Domains',
		  'cite-citations'         	=> 'Total Citations',
		  'cite-biz-directories'    	=> 'Business Directories',
		  'cite-service-directories'	=> 'Service Directories',
		  'cite-lead-gen'           	=> 'Lead Gen Platforms',
		  'cite-social'             	=> 'Social Media',
		  'cite-industry'           	=> 'Industry Sites',
	   ],

	   'Page Indexing' => [
		'index-pages-indexed'    => 'Pages Indexed',
		'index-404-errors'       => '404 Errors',
		'index-redirect-errors'  => 'Redirect Errors',
		'index-crawled-not'      => 'Crawled (not indexed)',
		'index-videos-indexed'   => 'Videos Indexed',
		'index-videos-not'   	=> 'Videos (not indexed)',
		],

        'Google Business Profile' 	=> [
            'google-reviews'       	=> 'Reviews',
            'google-rating'        	=> 'Rating',
            'gmb-impressions-90'      	=> 'Impressions (3m)',
            'gmb-calls-90'            	=> 'Call Clicks (3m)',
            'gmb-website-clicks-90'   	=> 'Website Clicks (3m)',
            'gmb-impressions-180'      	=> 'Impressions (6m)',
            'gmb-calls-180'            	=> 'Call Clicks (6m)',
            'gmb-website-clicks-180'   	=> 'Website Clicks (6m)',
            'gbp-profile-strength' => 'Profile Strength',
        ],

        'Google Ads' => [
            'ads-spend-30'       => 'Ad Spend (1m)',
            'ads-clicks-30'      => 'Ad Clicks (1m)',
            'ads-conversions-30' => 'Conversions (1m)',
            'ads-cpa-30'         => 'Cost Per Conversion',
        ],

        'Content' => [
            'content-freshness' => 'Days Since Last Update',
            'blog'              => 'Blog Posts',
            'jobsites'          => 'Job Sites',
            'landing'           => 'Landing Pages',
            'galleries'         => 'Galleries',
            'testimonials'      => 'Testimonials',
            'testimonials-pct-30'  => 'Testimonials Seen (1m)',
            'coupon-pct-30'        => 'Coupon Seen (1m)',
            'finance-pct-30'       => 'Financing Seen (1m)',
            'testimonials-pct-90'  => 'Testimonials Seen (3m)',
            'coupon-pct-90'        => 'Coupon Seen (3m)',
            'finance-pct-90'       => 'Financing Seen (3m)',
        ],

        'Miscellaneous' => [
            'wave'     		=> 'Wave Accessibility',
            'html'			=> 'HTML Verified',
            'schema' 		=> 'Schema Verified',
            'browserstack'	=> 'Browser Stack',
        ],


    ];

    // -------------------------------------------------------
    // Render
    // -------------------------------------------------------
    $manualFields = [
		'back-total-links',
		'back-domains',
		'cite-citations',
		'cite-key-citations',
		'cite-citation-score',
		'cite-biz-directories',
		'cite-service-directories',
		'cite-lead-gen',
		'cite-social',
		'cite-industry',
		'index-pages-indexed',
		'index-404-errors',
		'index-redirect-errors',
		'index-crawled-not',
		'index-videos-indexed',
		'index-videos-not',
		'console-indexed',
		'gmb-impressions-90',
		'gmb-calls-90',
		'gmb-website-clicks-90',
		'gmb-impressions-180',
		'gmb-calls-180',
		'gmb-website-clicks-180',
		'gbp-profile-strength',
        'ads-spend-30',
		'ads-clicks-30',
		'ads-conversions-30',
		'ads-cpa-30',
		'wave',
		'html',
		'schema',
		'browserstack',
	];

	$lastDate       = !empty($siteAudit) ? max(array_keys($siteAudit)) : null;
	$nextAudit      = get_option('bp_audit_next');

	$auditGenerated = $nextAudit
		? 'Next audit due: ' . date('M j, Y', $nextAudit)
		: '';

	$page  = '<div class="wrap">';   // <-- = not .=
	$page .= '<h1 style="font-size: 28px; font-weight: bold;">Site Audit</h1>';
    $page .= '<p style="font-size: 18px; margin-top:-5px; color:#888">' . esc_html($auditGenerated) . '</p>';

    $page .= '[clear height="20px"]';
    $page .= '<div class="scroll-stats">';
    $page .= '[section][layout class="stats ' . $siteType . '"][col]';

    if (!empty($siteAudit)) {

        $siteAudit = array_reverse($siteAudit, true);
        $dates     = array_keys($siteAudit);
        $colCount  = count($dates) + 1;
		$latestDate = $dates[0]; // most recent date

        $page .= '<table class="bp-audit-table">';

		// Header row — dates
		$page .= '<thead><tr><th class="row-label">Metric</th>';
		foreach ($dates as $date) {
			$page .= '<th><span class="month">' . date('M j', strtotime($date)) . '</span><br>'
				. date('Y', strtotime($date))
				. '<br><a class="bp-delete-audit-date" data-date="' . esc_attr($date) . '" style="color:#cc0000;cursor:pointer;font-size:11px;">✕ delete</a></th>';
		}
		$page .= '</tr></thead><tbody>';

		$alt        = 0;
		$latestDate = $dates[0];

		foreach ($sections as $sectionTitle => $fields) {

			$alt = $alt === 0 ? 1 : 0;

			$page .= '<tr><td colspan="' . $colCount . '" class="headline color-' . $alt . '">'
				. $sectionTitle . '</td></tr>';

			foreach ($fields as $key => $label) {

				$page .= '<tr><td class="subheadline color-' . $alt . '">' . $label . '</td>';

				foreach ($dates as $date) {
					$val = $siteAudit[$date][$key] ?? '—';
					if (in_array($key, $manualFields) && $date === $dates[0]) {
						// Only make the most recent column editable
						$page .= '<td class="stat color-' . $alt . ' ' . esc_attr($key) . ' editable" '
								. 'data-key="' . esc_attr($key) . '" '
								. 'data-date="' . esc_attr($date) . '">'
								. esc_html($val) . '</td>';
					} else {
						$page .= '<td class="stat color-' . $alt . ' ' . esc_attr($key) . '">'
								. esc_html($val) . '</td>';
					}
				}

				$page .= '</tr>';
			}
		}

		// Notes row
		$notes = $siteAudit[$latestDate]['notes'] ?? '';
		$page .= '<tr><td class="subheadline color-0">Notes</td>';
		$page .= '<td colspan="' . (count($dates)) . '" class="stat color-0">';
		$page .= '<textarea id="bp-audit-notes" data-key="notes" data-date="' . esc_attr($latestDate) . '" '
			. 'style="width:100%;min-height:80px;font-size:inherit;padding:4px;">'
			. esc_html($notes) . '</textarea>';
		$page .= '</td></tr>';
        $page .= '</tbody></table>';

    } else {
        $page .= '<p>No audit data yet. The table will populate automatically after the first chron run.</p>';
    }

    $page .= '[/col][/layout][/section]</div></div>';

	$page = str_ireplace(['>false</td>', '>N/A</td>', '>n/a</td>', '>N/A%</td>', '> </td>', '></td>'], '>—</td>', $page);

    echo do_shortcode($page);

	echo '<script>
		const bpAudit = {
			ajaxUrl: "' . admin_url('admin-ajax.php') . '",
			nonce:   "' . wp_create_nonce('bp_audit_nonce') . '"
		};
		document.addEventListener("DOMContentLoaded", function() {
			document.querySelectorAll(".bp-audit-table td.editable").forEach(td => {
				td.style.cursor = "pointer";
				td.title = "Click to edit";
				td.addEventListener("click", function() {
					if (this.querySelector("input")) return;
					const current = this.textContent.trim() === "—" ? "" : this.textContent.trim();
					const key     = this.dataset.key;
					const date    = this.dataset.date;
					this.innerHTML = "<input type=\'text\' value=\'" + current + "\' style=\'width:80px;font-size:inherit;padding:2px 4px;\' data-key=\'" + key + "\' data-date=\'" + date + "\'>";
					const input = this.querySelector("input");
					input.focus();
					input.select();
					input.addEventListener("blur", function() {
						saveAuditField(this.dataset.key, this.dataset.date, this.value, td);
					});
					input.addEventListener("keydown", function(e) {
						if (e.key === "Enter") this.blur();
						if (e.key === "Escape") td.textContent = current || "—";
					});
				});
			});
		});

		document.querySelectorAll(".bp-delete-audit-date").forEach(link => {
			link.addEventListener("click", function() {
				if (!confirm("Delete audit data for " + this.dataset.date + "?")) return;
				const data = new FormData();
				data.append("action", "bp_delete_audit_date");
				data.append("nonce",  bpAudit.nonce);
				data.append("date",   this.dataset.date);
				fetch(bpAudit.ajaxUrl, { method: "POST", body: data })
					.then(r => r.json())
					.then(r => { if (r.success) location.reload(); });
			});
		});

		function saveAuditField(key, date, value, td) {
			const data = new FormData();
			data.append("action", "bp_save_audit_field");
			data.append("nonce",  bpAudit.nonce);
			data.append("key",    key);
			data.append("date",   date);
			data.append("value",  value);
			fetch(bpAudit.ajaxUrl, { method: "POST", body: data })
				.then(r => r.json())
				.then(r => { td.textContent = r.success ? (value || "—") : "⚠ Error"; })
				.catch(() => { td.textContent = "⚠ Error"; });
		}

		// Notes autosave on blur
		const notesArea = document.getElementById("bp-audit-notes");
		if (notesArea) {
		notesArea.addEventListener("blur", function() {
			const data = new FormData();
			data.append("action", "bp_save_audit_field");
			data.append("nonce",  bpAudit.nonce);
			data.append("key",    this.dataset.key);
			data.append("date",   this.dataset.date);
			data.append("value",  this.value);
			fetch(bpAudit.ajaxUrl, { method: "POST", body: data })
				.then(r => r.json())
				.then(r => {
					notesArea.style.borderColor = r.success ? "green" : "red";
					setTimeout(() => notesArea.style.borderColor = "", 2000);
				});
		});
		}
	</script>';
	exit();



}

add_action('wp_ajax_bp_save_audit_field', 'bp_ajax_save_audit_field');
function bp_ajax_save_audit_field() {

    check_ajax_referer('bp_audit_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $key   = sanitize_text_field($_POST['key']   ?? '');
    $date  = sanitize_text_field($_POST['date']  ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');

    if (!$key || !$date) {
        wp_send_json_error('Missing fields');
    }

    $history = get_option('bp_site_audit_details') ?: [];
    $history[$date][$key] = $value;
    update_option('bp_site_audit_details', $history, false);

    wp_send_json_success();
}

add_action('wp_ajax_bp_delete_audit_date', 'bp_ajax_delete_audit_date');
function bp_ajax_delete_audit_date() {
    check_ajax_referer('bp_audit_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized');
    $date = sanitize_text_field($_POST['date'] ?? '');
    if (!$date) wp_send_json_error('Missing date');
    $history = get_option('bp_site_audit_details') ?: [];
    unset($history[$date]);
    update_option('bp_site_audit_details', $history, false);
    wp_send_json_success();
}