<?php
/* Battle Plan Web Design Functions: Admin

 
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
add_filter( 'acp/storage/file/directory', function() {
    return get_template_directory() . '/acp-settings';
} );

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
	$chronTime = timeElapsed( get_option('bp_chron_time'), 2, 'all', 'short');
	$siteUpdated = str_replace('-', '', get_option( "site_updated" ));
	//add_menu_page( __( 'Run Chron', 'battleplan' ), __( 'Run Chron', 'battleplan' ), 'manage_options', 'run-chron', 'battleplan_force_run_chron', 'dashicons-performance', 3 );
	
	if ( _USER_LOGIN == "battleplanweb" ) :
		add_submenu_page( 'index.php', 'Clear ALL', 'Clear ALL', 'manage_options', 'clear-all', 'battleplan_clear_all' );	
		add_submenu_page( 'index.php', 'Clear HVAC', 'Clear HVAC', 'manage_options', 'clear-hvac', 'battleplan_clear_hvac' );			
		add_submenu_page( 'index.php', 'Launch Site', 'Launch Site', 'manage_options', 'launch-site', 'battleplan_launch_site' );	
		add_submenu_page( 'index.php', 'Run Chron', 'Run Chron <div class="admin-note">'.$chronTime.'</div>', 'manage_options', 'run-chron', 'battleplan_force_run_chron' );	
		add_submenu_page( 'index.php', 'Site Audit', 'Site Audit <div class="admin-note">'.date("F j, Y", (int)$siteUpdated).'</div>', 'manage_options', 'site-audit', 'battleplan_site_audit' );	
	endif;
}

function battleplan_addSitePage() { 
	echo '<h1>Admin Page</h1>';
}

// Replace WordPress copyright message at bottom of admin page
add_filter('admin_footer_text', 'battleplan_admin_footer_text');
function battleplan_admin_footer_text() { 
	$printFooter = '<section><div class="flex" style="grid-template-columns: 80px 300px 1fr; gap: 20px">';
	$printFooter .= '<div style="grid-row: span 2; align-self: center;"><img src="https://battleplanwebdesign.com/wp-content/uploads/site-icon-80x80.webp" /></div>';
	$printFooter .= '<div style="grid-row: span 2; align-self: center;">Powered by <a href="https://battleplanwebdesign.com" target="_blank">Battle Plan Web Design</a><br>';
	$printFooter .= 'Launched '.date('F Y', strtotime(get_option('bp_launch_date'))).'<br>';
	$printFooter .= 'Framework '._BP_VERSION.'<br>';
	$printFooter .= 'WP '.get_bloginfo('version').'<br>';
	$printFooter .=  'Local Time: '.wp_date("g:i a", null, new DateTimeZone( wp_timezone_string() ) ).'<br></div>';
	
	$printFooter .= '<div style="justify-self: end; margin-right: 50px;"><a class="button" href = "mailto:'.get_option('customer_info')['email'].'">Contact Email</a>';
		
	if ( isset(get_option('customer_info')['owner-email']) ) $printFooter .= '<a class="button" href = "mailto:'.get_option('customer_info')['owner-email'].'">Owner Email</a>';	
	if ( isset(get_option('customer_info')['facebook']) ) $printFooter .= '<a class="button" href = "'.get_option('customer_info')['facebook'].'" target="_blank">Facebook</a>';
	if ( isset(get_option('customer_info')['twitter']) ) $printFooter .= '<a class="button" href = "'.get_option('customer_info')['twitter'].'" target="_blank">Twitter</a>';
	if ( isset(get_option('customer_info')['instagram']) ) $printFooter .= '<a class="button" href = "'.get_option('customer_info')['instagram'].'" target="_blank">Instagram</a>';
	if ( isset(get_option('customer_info')['pinterest']) ) $printFooter .= '<a class="button" href = "'.get_option('customer_info')['pinterest'].'" target="_blank">Pinterest</a>';
	if ( isset(get_option('customer_info')['yelp']) ) $printFooter .= '<a class="button" href = "'.get_option('customer_info')['yelp'].'" target="_blank">Yelp</a>';
	if ( isset(get_option('customer_info')['tiktok']) ) $printFooter .= '<a class="button" href = "'.get_option('customer_info')['tiktok'].'" target="_blank">TikTok</a>';
	if ( isset(get_option('customer_info')['youtube']) ) $printFooter .= '<a class="button" href = "'.get_option('customer_info')['youtube'].'" target="_blank">You Tube</a>';
	
	if ( isset(get_option('customer_info')['google-tags']['prop-id']) ) $printFooter .= '<a class="button" href = "https://analytics.google.com/analytics/web/#/p'.get_option('customer_info')['google-tags']['prop-id'].'/reports/explorer?params=_u..nav%3Dmaui%26_u..pageSize%3D25%26_r.explorerCard..selmet%3D%5B%22sessions%22%5D%26_r.explorerCard..seldim%3D%5B%22sessionDefaultChannelGrouping%22%5D&r=lifecycle-traffic-acquisition-v2&collectionId=life-cycle" target="_blank">Analytics</a>';
	
	if ( isset(get_option('customer_info')['serpfox']) ) $printFooter .= '<a class="button" href = "//app.serpfox.com/shared/'.get_option('customer_info')['serpfox'].'" target="_blank">Keywords</a>';
		
	$printFooter .= '</div><div style="justify-self: end; margin-bottom:15px;">';	
	 
	$placeIDs = get_option('customer_info')['pid'];
	$googleInfo = get_option('bp_gbp_update');					
	if ( isset($placeIDs) ) :
		if ( !is_array($placeIDs) ) $placeIDs = array($placeIDs);
		$primePID = true;
		foreach ( $placeIDs as $placeID ) :	
			if ( $primePID == true ) :
				$customer_info = $GLOBALS['customer_info'];
				$primePID = false;
			else:
				$customer_info = $googleInfo[$placeID];
			endif;
			
			$printFooter .= '<div style="float:left; margin-right: 50px;">';	

			if ( strlen($placeID) > 10 && $googleInfo[$placeID]['city'] ) $printFooter .= '<a class="button" style="margin: 0 0 10px -5px" href = "https://search.google.com/local/writereview?placeid='.$placeID.'" target="_blank">GBP: '.$googleInfo[$placeID]['city'].', '.$googleInfo[$placeID]['state-abbr'].'</a><br>';
			
			$printFooter .= $GLOBALS['customer_info']['area-before'].$customer_info['area'].$GLOBALS['customer_info']['area-after'].$customer_info['phone'].'<br>';
			$printFooter .= $customer_info['street'].'<br>';
			$printFooter .= $customer_info['city'].', '.$customer_info['state-abbr'].' '.$customer_info['zip'].'<br>';
			if ( isset($customer_info['lat']) ) $printFooter .= $customer_info['lat'].', '.$customer_info['long'].'<br>';	
			$printFooter .= '</div>';

		endforeach;
	endif;
			
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
	$size_full = wp_get_attachment_image_src($id, 'full');
	$size_requested = wp_get_attachment_image_src($id, $size);	
	$size = $size == 'full' ? 'orig' : $size;
	$data_orig = $size == 'orig' ? '' : ' data-orig="'.$size_full[1].'x'.$size_full[2].'"';
	$url = str_replace( get_site_url(), "", $size_requested[0] );
	$alt = $alt == get_the_title($id) ? '' : $alt;
		
	return '<img src="'.$url.'"'.$data_orig.' width="'.$size_requested[1].'" height="'.$size_requested[2].'" style="aspect-ratio:'.$size_requested[1].'/'.$size_requested[2].'" class="align'.$align.' size-'.$size.' wp-image-'.$id.'" alt="'.$alt.'" />';
}

// Set the quality of compression on various WordPress generated image sizes
function av_return_100(){ return 67; }
add_filter('jpeg_quality', 'av_return_100', 9999);
add_filter('wp_editor_set_quality', 'av_return_100', 9999);

// Display custom fields in WordPress admin edit screen
//add_filter('acf/settings/remove_wp_meta_box', '__return_false');

// Add & Remove WP Admin Menu items
add_action( 'admin_init', 'battleplan_remove_menus', 999 );
function battleplan_remove_menus() {   
	remove_menu_page( 'link-manager.php' );       										// Links
	remove_menu_page( 'edit-comments.php' );       										// Comments	
	remove_menu_page( 'wpcf7' );       													// Contact Forms	
	remove_menu_page( 'edit.php?post_type=acf-field-group' );       					// Custom Fields
	remove_menu_page( 'themes.php' );       											// Appearance
	remove_menu_page( 'wpengine-common' );   											// WP Engine
	remove_menu_page( 'wp-mail-smtp' );   												// WP Mail SMTP
	remove_menu_page( 'wpseo_dashboard' );   											// Yoast SEO	
	remove_menu_page( 'wpseo_workouts' );   											// Yoast SEO
	remove_menu_page( 'post_to_google_my_business');									// Post to GMB
	
	remove_submenu_page( 'plugins.php', 'plugin-editor.php' );        					// Plugins => Plugin Editor
	remove_submenu_page( 'options-general.php', 'options-writing.php' );   				// Settings => Writing 		
	remove_submenu_page( 'options-general.php', 'options-reading.php' );   				// Settings => Reading 	
	remove_submenu_page( 'options-general.php', 'options-media.php' );   				// Settings => Media 	
	remove_submenu_page( 'options-general.php', 'options-privacy.php' );   				// Settings => Privacy 	
	remove_submenu_page( 'options-general.php', 'akismet-key-config' );   				// Settings => Akismet	
	remove_submenu_page( 'options-general.php', 'git-updater' );   						// Settings => Git Updater 
	remove_submenu_page( 'options-general.php', 'git-updater-account' );   				// Settings => Git Updater Account		
	remove_submenu_page( 'options-general.php', 'codepress-admin-columns' );   			// Settings => Admin Columns
	remove_submenu_page( 'tools.php', 'export-personal-data.php' );   					// Tools => Export Personal Data  
	remove_submenu_page( 'tools.php', 'erase-personal-data.php' );   					// Tools => Erase Personal Data
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_workouts' );   						// Yoast SEO => Workouts
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_licenses' );   						// Yoast SEO => Premium
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_page_academy' );   					// Yoast SEO => Academy
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_tools' );   							// Yoast SEO => Tools
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_integrations' );   					// Yoast SEO => Integrations
	remove_submenu_page( 'wpseo_dashboard', 'wpseo_dashboard' );   						// Yoast SEO => General
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-logs' );   						// WP Mail SMTP => Email Log
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-reports' );   					// WP Mail SMTP => Email Reports
	remove_submenu_page( 'wp-mail-smtp', 'wp-mail-smtp-about' );   						// WP Mail SMTP => About Us			
	
	add_submenu_page( 'upload.php', 'Favicon', 'Favicon', 'manage_options', 'customize.php' );	
	
	
	if ( _USER_LOGIN != "battleplanweb" && !in_array('administrator', _USER_ROLES) ) remove_menu_page( 'edit.php?post_type=elements');	
	if ( _USER_LOGIN != "battleplanweb" && !in_array('administrator', _USER_ROLES) ) remove_menu_page( 'edit.php?post_type=landing');	
	
	if ( _USER_LOGIN != "battleplanweb" ) remove_menu_page( 'edit.php?post_type=universal');		 
	if ( _USER_LOGIN != "battleplanweb" ) remove_menu_page( 'tools.php');	
	if ( _USER_LOGIN != "battleplanweb" ) remove_menu_page( 'edit.php?post_type=stripe_order');	
		

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
	
	if ( _USER_LOGIN == "battleplanweb" ) add_submenu_page( 'edit.php?post_type=elements', 'Contact Forms Integration', '&nbsp;└&nbsp;Integration', 'manage_options', 'admin.php?page=wpcf7-integration' );		
	
	add_submenu_page( 'edit.php?post_type=elements', 'Comments', 'Comments', 'manage_options', 'edit-comments.php' );
	if ( _USER_LOGIN == "battleplanweb" ) add_submenu_page( 'edit.php?post_type=elements', 'Custom Fields', 'Custom Fields', 'manage_options', 'edit.php?post_type=acf-field-group' );		
	if ( _USER_LOGIN == "battleplanweb" ) add_submenu_page( 'edit.php?post_type=elements', 'Framework '._BP_VERSION, 'Framework '._BP_VERSION, 'manage_options', 'themes.php' );		
	if ( _USER_LOGIN == "battleplanweb" ) add_submenu_page( 'options-general.php', 'Options', 'Options', 'manage_options', 'options.php' );
	add_submenu_page( 'tools.php', 'WP Engine', 'WP Engine', 'manage_options', 'options-general.php?page=wpengine-common' );
	
	if ( _USER_LOGIN == "battleplanweb" && is_plugin_active( 'git-updater/git-updater.php' ) ) add_submenu_page( 'tools.php', 'Git Updater', 'Git Updater', 'manage_options', 'options-general.php?page=git-updater' );
	if ( _USER_LOGIN == "battleplanweb" && is_plugin_active( 'admin-columns-pro/admin-columns-pro.php' ) ) add_submenu_page( 'tools.php', 'Admin Columns', 'Admin Columns', 'manage_options', 'options-general.php?page=codepress-admin-columns' );
	if ( _USER_LOGIN == "battleplanweb" && is_plugin_active( 'wp-mail-smtp/wp_mail_smtp.php' ) ) add_submenu_page( 'tools.php', 'WP Mail SMTP', 'WP Mail SMTP', 'manage_options', 'options-general.php?page=wp-mail-smtp' );

	if ( _USER_LOGIN == "battleplanweb" && is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) add_submenu_page( 'tools.php', 'Yoast Settings', 'Yoast Settings', 'manage_options', 'admin.php?page=wpseo_page_settings' );
	if ( _USER_LOGIN == "battleplanweb" && is_plugin_active( 'wpseo-local/local-seo.php' ) ) add_submenu_page( 'tools.php', 'Yoast Local', '&nbsp;└&nbsp;Local', 'manage_options', 'admin.php?page=wpseo_local' );
	if ( _USER_LOGIN == "battleplanweb" && is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' ) ) add_submenu_page( 'tools.php', 'Yoast Redirects', '&nbsp;└&nbsp;Redirects', 'manage_options', 'admin.php?page=wpseo_redirects' );

	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Settings', 'GBP Settings', 'manage_options', 'admin.php?page=pgmb_settings' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Templates', '&nbsp;└&nbsp;Templates', 'manage_options', 'edit.php?post_type=pgmb_templates' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Calendar', '&nbsp;└&nbsp;Calendar', 'manage_options', 'admin.php?page=post_to_google_my_business' );
	if ( in_array('administrator', _USER_ROLES) && is_plugin_active( 'post-to-google-my-business-premium/post-to-google-my-business.php' ) ) add_submenu_page( 'tools.php', 'GBP Account', '&nbsp;└&nbsp;Account', 'manage_options', 'admin.php?page=post_to_google_my_business-account' );	
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
function battleplan_submenu_order( $menu_ord ) {
    global $submenu;	
    $arr = array();
    $arr[] = $submenu['options-general.php'][10];     
    $arr[] = $submenu['options-general.php'][15];
    $arr[] = $submenu['options-general.php'][20];
    $arr[] = $submenu['options-general.php'][25];
    $arr[] = $submenu['options-general.php'][30];
    $arr[] = $submenu['options-general.php'][40];
    $arr[] = $submenu['options-general.php'][45];
    $arr[] = $submenu['options-general.php'][49];
    $arr[] = $submenu['options-general.php'][46];
    $arr[] = $submenu['options-general.php'][48]; 
    $arr[] = $submenu['options-general.php'][47];
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
if ( isset(get_option('customer_info')['google-tags']['prop-id']) && get_option('customer_info')['google-tags']['prop-id'] > 1 && is_admin() && (_USER_LOGIN == "battleplanweb" || in_array('bp_view_stats', _USER_ROLES) ) ) require_once get_template_directory().'/functions-admin-stats.php';


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
	$siteType = $GLOBALS['customer_info']['site-type'] ?? null;
	$bizType = $GLOBALS['customer_info']['business-type'] ?? null;

    if ( $siteType ) $classes .= ' site-type-'.strtolower($siteType);
    if ( $bizType ) $classes .= ' business-type-'.strtolower($bizType);
	
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
	$edit = str_replace( "Edit", "<i class='dashicons-edit'></i>", $actions['edit'] );
	$view = str_replace( "View", "<i class='dashicons-view'></i>", $actions['view'] );
	$view = str_replace( "Preview", "<i class='dashicons-view'></i>", $view );
	$delete = str_replace( "Trash", "<i class='dashicons-trash'></i>", $actions['trash'] );

	$edit = str_replace( "<a href", "<a title='Edit' target='_blank' href", $edit );
	$clone = '<a target="_blank" href="' . wp_nonce_url('admin.php?action=battleplan_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Clone" rel="permalink"><i class="dashicons-clone"></i></a>';
	$view = str_replace( "<a href", "<a title='View' target='_blank' href", $view );	
	$delete = str_replace( "<a href", "<a title='Delete' href", $delete );
	$quickEdit = '<button type="button" class="button-link editinline" aria-label="Quick edit" aria-expanded="false"><i class="dashicons-quick-edit"></i></button>';

	return array( 'edit' => $edit, 'inline hide-if-no-js' => $quickEdit, 'duplicate' => $clone, 'view' => $view, 'delete' => $delete );
}

// Replace Media Library image links with icons
add_filter('media_row_actions', 'battleplan_media_row_actions', 90, 2);
function battleplan_media_row_actions( $actions, $post ) {
	$edit = str_replace( "Edit", "<i class='dashicons-edit'></i>", $actions['edit'] );
	$view = str_replace( "View", "<i class='dashicons-view'></i>", $actions['view'] );
	$media_replace = str_replace( "Replace media", "<i class='dashicons-replace'></i>", $actions['media_replace'] );
	$delete = str_replace( "Delete Permanently", "<i class='dashicons-trash'></i>", $actions['delete'] );
	
	$edit = str_replace( "<a href", "<a title='Edit Media' target='_blank' href", $edit );
	$view = str_replace( "<a href", "<a title='View Media' target='_blank' href", $view );
	$media_replace = str_replace( "<a href", "<a title='Replace Media' target='_blank' href", $media_replace );
	$delete = str_replace( "<a href", "<a title='Delete Media' href", $delete );
	
	return array( 'edit' => $edit, 'view' => $view, 'media_replace' => $media_replace, 'delete' => $delete );
} 

// Replace Users links with icons
add_filter( 'user_row_actions', 'battleplan_user_row_actions', 90, 2 );
function battleplan_user_row_actions( $actions, $post ) {
	if ( isset($actions['edit']) ) $edit = str_replace( "Edit", "<i class='dashicons-edit'></i>", $actions['edit'] );
	if ( isset($actions['delete']) ) $delete = str_replace( "Delete", "<i class='dashicons-trash'></i>", $actions['delete'] );
	if ( isset($actions['switch_to_user']) ) $switch = str_replace( "Switch&nbsp;To", "<i class='dashicons-randomize'></i>", $actions['switch_to_user'] );

	return array( 'edit' => $edit, 'delete' => $delete, 'switch_to_user' => $switch );
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

// Force clear all views for posts/pages
function battleplan_force_run_chron() {
	updateOption('bp_force_chron', true);		
	header("Location: /wp-admin/");
	exit();
}  

/*--------------------------------------------------------------
# Site Audit Set Up
--------------------------------------------------------------*/

// Keep logs of site audits
function battleplan_site_audit() {
	$today = date( "Y-m-d" );
	$submitCheck = $_POST['submit_check'];
	$siteType = get_option('customer_info')['site-type'];
	
	$criteriaOrder = array ('lighthouse-mobile-score', 'lighthouse-mobile-ttfb', 'lighthouse-mobile-fcp', 'lighthouse-mobile-lcp', 'lighthouse-mobile-tti', 'lighthouse-mobile-tbt', 'lighthouse-mobile-si', 'lighthouse-mobile-cls', 'lighthouse-desktop-score', 'lighthouse-desktop-ttfb', 'lighthouse-desktop-fcp', 'lighthouse-desktop-lcp', 'lighthouse-desktop-tti', 'lighthouse-desktop-tbt', 'lighthouse-desktop-si', 'lighthouse-desktop-cls', 'keyword-page-1', 'keyword-needs-attn', 'database-page-gen-time', 'database-peak-mem', 'database-db-queries', 'database-db-queries-time', 'back-total-links', 'back-domains', 'back-local-links', 'back-c-flow', 'back-domain-authority', 'cite-citations', 'cite-key-citations', 'cite-citation-score', 'console-indexed', 'console-clicks', 'console-position', 'gmb-overview', 'gmb-calls', 'gmb-clicks');	
	
	if ( $submitCheck == "true" ) :
		$siteAudit = get_option('bp_site_audit_details');
		if ( !is_array($siteAudit) ) $siteAudit = array();	
		foreach ( $criteriaOrder as $log ) :
			$log_value = $_POST[$log];
			if ( $log_value || $log_value == '0' ) :
				if ( $log == "database-page-gen-time" || $log == "database-db-queries-time" ) :
					$get_numbers = explode(",", str_replace(' ','', $log_value));
					$total = 0;
					foreach ($get_numbers as $number) $total += $number;
					$log_value = $total / count($get_numbers);
					$decimals = 2;
				elseif ( $log == "lighthouse-mobile-cls" || $log == "lighthouse-desktop-cls" || $log == "lighthouse-mobile-ttfb" || $log == "lighthouse-desktop-ttfb" || $log == "lighthouse-mobile-si" || $log == "lighthouse-desktop-si" || $log == "lighthouse-mobile-fcp" || $log == "lighthouse-desktop-fcp" || $log == "lighthouse-mobile-lcp" || $log == "lighthouse-desktop-lcp" || $log == "lighthouse-mobile-tti" || $log == "lighthouse-desktop-tti" || $log == "database-peak-mem" ) : 
					$decimals = 2; 
				else:
					$decimals = 0;
				endif;
				$updateNum = number_format((string)$log_value, $decimals);
				$siteAudit[$today][$log] = $updateNum;
			endif;		
		endforeach;	
	endif;
		
	array_push( $criteriaOrder, 'google-reviews', 'google-rating', 'load_time_mobile', 'load_time_desktop', 'testimonials', 'testimonials-pct', 'coupon', 'coupon-pct', 'financing-link', 'finance-pct', 'blog', 'galleries', 'landing', 'jobsites', 'audit-ada', 'audit-schema', 'audit-html', 'audit-browserstack', 'notes');	
				
	if ( $submitCheck == "true" ) :
		$note_value = $_POST['notes'];
		if ( isset($note_value) ) :
			if ( isset( $_POST['erase-note'] ) ) :
				$siteAudit[$today]['notes'] = $note_value;
			else:
				$siteAudit[$today]['notes'] .= "  ".$note_value;
			endif;
		endif;	
	
		$googleInfo = get_option('bp_gbp_update');
		$siteAudit[$today]['google-rating'] = number_format($googleInfo['google-rating'], 1, '.', ',');
		$siteAudit[$today]['google-reviews'] = $googleInfo['google-reviews'];
	
		$siteAudit[$today]['load_time_mobile'] = $GLOBALS['speedSessions']['sessions-30']['mobile'] > 0 ? number_format($GLOBALS['speedTotal']['sessions-30']['mobile'] / $GLOBALS['speedSessions']['sessions-30']['mobile'], 1) : 0; 	
	
		$siteAudit[$today]['load_time_desktop'] = $GLOBALS['speedSessions']['sessions-30']['desktop'] > 0 ? number_format($GLOBALS['speedTotal']['sessions-30']['desktop'] / $GLOBALS['speedSessions']['sessions-30']['desktop'], 1) : 0; 	
	
		$siteAudit[$today]['testimonials-pct'] = $GLOBALS['ga4_contentVis']['track-init']['sessions-30'] > 0 ? number_format(($GLOBALS['ga4_contentVis']['track-testimonials']['sessions-30'] / $GLOBALS['ga4_contentVis']['track-init']['sessions-30']*100), 1).'%' : ''; 		
	
		$siteAudit[$today]['coupon-pct'] = $GLOBALS['ga4_contentVis']['track-init']['sessions-30'] > 0 ? number_format(($GLOBALS['ga4_contentVis']['track-coupon']['sessions-30'] / $GLOBALS['ga4_contentVis']['track-init']['sessions-30'])*100, 1).'%' : ''; 		
	
		$siteAudit[$today]['finance-pct'] = $GLOBALS['ga4_contentVis']['track-init']['sessions-30'] > 0 ? number_format(($GLOBALS['ga4_contentVis']['track-finance']['sessions-30'] / $GLOBALS['ga4_contentVis']['track-init']['sessions-30'])*100, 1).'%' : ''; 		
		
		if ( wp_count_posts( 'post' )->publish > 0 ) : $siteAudit[$today]['blog'] = wp_count_posts( 'post' )->publish; else: $siteAudit[$today]['blog'] = "false"; endif;
		
		if ( wp_count_posts( 'landing' )->publish > 0 ) : $siteAudit[$today]['landing'] = wp_count_posts( 'landing' )->publish; else: $siteAudit[$today]['landing'] = "false"; endif;
		
		if ( wp_count_posts( 'testimonials' )->publish > 0 ) : $siteAudit[$today]['testimonials'] = wp_count_posts( 'testimonials' )->publish; else: $siteAudit[$today]['testimonials'] = "false"; endif;
		
		if ( wp_count_posts( 'galleries' )->publish > 0 ) : $siteAudit[$today]['galleries'] = wp_count_posts( 'galleries' )->publish; else: $siteAudit[$today]['galleries'] = "false"; endif;
	
		if ( wp_count_posts( 'jobsite_geo' )->publish > 0 ) : $siteAudit[$today]['jobsites'] = wp_count_posts( 'jobsite_geo' )->publish; else: $siteAudit[$today]['jobsites'] = "false"; endif;
		
		$siteAudit[$today]['coupon'] = $siteAudit[$today]['financing-link'] = "false";

		$check_posts = bp_WP_Query(['page', 'landing'], [
			'posts_per_page' => -1
		]);

		if( $check_posts->have_posts() ) : while ($check_posts->have_posts() ) : $check_posts->the_post();	
			$header = get_posts([ 'name' => 'site-header','post_type' => 'elements' ]);
			$footer = get_posts([ 'name' => 'site-footer','post_type' => 'elements' ]);
			$widgets = get_posts([ 'name' => 'widgets','post_type' => 'elements' ]);
			$checkContent = '';
	
			if ( $header ) $checkContent .= $header[0]->post_content;
			$checkContent .= get_the_content();
			if ( $widgets ) $checkContent .= $widgets[0]->post_content;
			$checkContent .= get_post_meta( get_the_ID(), 'page-bottom_text', false );
			if ( $footer ) $checkContent .= $footer[0]->post_content;	
	
			if ( strpos($checkContent, "coupon") !== false ) $siteAudit[$today]['coupon'] = "true";	
			if ( strpos($checkContent, "[get-financing") !== false || strpos($checkContent, "[get-wells-fargo") !== false ) $siteAudit[$today]['financing-link'] = "true";	
		endwhile; endif; wp_reset_postdata();
	
	
		if ( $submitCheck == "true" ) :
			foreach ( $criteriaOrder as $log ) :
				$log_value = $_POST[$log];
				if ( $log == "audit-ada" || $log =="audit-schema" || $log =="audit-html" || $log =="audit-browserstack" ) :
					$siteAudit[$today][$log] = $log_value;
				endif;		
			endforeach;	
		endif;

		updateOption('bp_site_audit_details', $siteAudit, false);
	endif;
	
	$siteAuditPage = '<div class="wrap">';
	$siteAuditPage .= '<h1>Site Audit</h1>';
	
	$siteAuditPage .= '<form method="post">';
	
	$siteAuditPage .= '[section][layout class="inputs"][col]';
	
		$siteAuditPage .= '<h1>Lighthouse</h1>';
		
		$siteAuditPage .= '<h3>Mobile</h3>';		
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-score">Performance Score:</label><input id="lighthouse-mobile-score" type="text" name="lighthouse-mobile-score" value=""></div>';		
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-ttfb">Time To First Byte:</label><input id="lighthouse-mobile-ttfb" type="text" name="lighthouse-mobile-ttfb" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-fcp">First Contentful Paint:</label><input id="lighthouse-mobile-fcp" type="text" name="lighthouse-mobile-fcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-lcp">Largest Contentful Paint:</label><input id="lighthouse-mobile-lcp" type="text" name="lighthouse-mobile-lcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-tti">Time To Interactive:</label><input id="lighthouse-mobile-tti" type="text" name="lighthouse-mobile-tti" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-tbt">Total Blocking Time:</label><input id="lighthouse-mobile-tbt" type="text" name="lighthouse-mobile-tbt" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-si">Speed Index:</label><input id="lighthouse-mobile-si" type="text" name="lighthouse-mobile-si" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-cls">Cumulative Layout Shift:</label><input id="lighthouse-mobile-cls" type="text" name="lighthouse-mobile-cls" value=""></div>';
		
		$siteAuditPage .= '<br>';
		
		$siteAuditPage .= '<h3>Desktop</h3>';			
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-score">Performance Score:</label><input id="lighthouse-desktop-score" type="text" name="lighthouse-desktop-score" value=""></div>';	
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-ttfb">Time To First Byte:</label><input id="lighthouse-desktop-ttfb" type="text" name="lighthouse-desktop-ttfb" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-fcp">First Contentful Paint:</label><input id="lighthouse-desktop-fcp" type="text" name="lighthouse-desktop-fcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-lcp">Largest Contentful Paint:</label><input id="lighthouse-desktop-lcp" type="text" name="lighthouse-desktop-lcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-tti">Time To Interactive:</label><input id="lighthouse-desktop-tti" type="text" name="lighthouse-desktop-tti" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-tbt">Total Blocking Time:</label><input id="lighthouse-desktop-tbt" type="text" name="lighthouse-desktop-tbt" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-si">Speed Index:</label><input id="lighthouse-desktop-si" type="text" name="lighthouse-desktop-si" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-cls">Cumulative Layout Shift:</label><input id="lighthouse-desktop-cls" type="text" name="lighthouse-desktop-cls" value=""></div>';
		
		$siteAuditPage .= '<br>';
	
		$siteAuditPage .= '<h1>Keyword Rank</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="keyword-page-1">Page One:</label><input id="keyword-page-1" type="text" name="keyword-page-1" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="keyword-needs-attn">Needs Attention:</label><input id="keyword-needs-attn" type="text" name="keyword-needs-attn" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Query Monitor</h1>';
		$siteAuditPage .= '<div class="form-input"><label for="database-page-gen-time">Page Gen Time:</label><input id="database-page-gen-time" type="text" name="database-page-gen-time" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="database-peak-mem">Peak Memory:</label><input id="database-peak-mem" type="text" name="database-peak-mem" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="database-db-queries">DB Queries:</label><input id="database-db-queries" type="text" name="database-db-queries" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="database-db-queries-time">DB Queries Time:</label><input id="database-db-queries-time" type="text" name="database-db-queries-time" value=""></div>';
		
		$siteAuditPage .= '[/col][col]';

		$siteAuditPage .= '<h1>Backlinks</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="back-total-links">Total Links:</label><input id="back-total-links" type="text" name="back-total-links" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="back-domains">Linking Domains:</label><input id="back-domains" type="text" name="back-domains" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="back-local-links">Local Links:</label><input id="back-local-links" type="text" name="back-local-links" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="back-c-flow">C-Flow:</label><input id="back-c-flow" type="text" name="back-c-flow" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="back-domain-authority">Domain Authority:</label><input id="back-domain-authority" type="text" name="back-domain-authority" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Citations</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="cite-citations">Citations:</label><input id="cite-citations" type="text" name="cite-citations" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-key-citations">Key Citations:</label><input id="cite-key-citations" type="text" name="cite-key-citations" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-citation-score">Key Score:</label><input id="cite-citation-score" type="text" name="cite-citation-score" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Search Console</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="console-indexed">Index Pages:</label><input id="console-indexed" type="text" name="console-indexed" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="console-clicks">Clicks:</label><input id="console-clicks" type="text" name="console-clicks" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="console-position">Avg. Position:</label><input id="console-position" type="text" name="console-position" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Google Business Profile</h1>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-overview">Overview:</label><input id="gmb-overview" type="text" name="gmb-overview" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-calls">Calls:</label><input id="gmb-calls" type="text" name="gmb-calls" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-clicks">Clicks:</label><input id="gmb-clicks" type="text" name="gmb-clicks" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Passed Audits</h1>';
		$siteAuditPage .= '<div class="form-input"><label for="audit-ada">ADA (Wave):</label><input id="audit-ada" type="checkbox" name="audit-ada" value="true"></div>';
		$siteAuditPage .= '<div class="form-input"><label for="audit-schema">Schema:</label><input id="audit-schema" type="checkbox" name="audit-schema" value="true"></div>';
		$siteAuditPage .= '<div class="form-input"><label for="audit-html">HTML:</label><input id="audit-html" type="checkbox" name="audit-html" value="true"></div>';
		$siteAuditPage .= '<div class="form-input"><label for="audit-browserstack">Browser Stack:</label><input id="audit-browserstack" type="checkbox" name="audit-browserstack" value="true"></div>';
	
		$siteAuditPage .= '[/col][col]';
	
		$siteAuditPage .= '<h1>Notes</h1>';	
		$siteAuditPage .= '<div class="form-input"><textarea id="notes" name="notes" cols="40" rows="10"></textarea></div>';
		$siteAuditPage .= '<input type="hidden" id="submit_check" name="submit_check" value="true">';
		$siteAuditPage .= '<br><input type="checkbox" id="erase-note" name="erase-note" value="Erase"><label for="clear-note"> Erase</label><br>';			
		$siteAuditPage .= '<br><input type="submit" value="Submit">';	
		
	$siteAuditPage .= '[/col][/layout][/section]';	

	$siteAuditPage .= '</form>';	
	
	$siteAuditPage .= '[clear height="50px"]';	
	$siteAuditPage .= '<div class="scroll-stats"><h1>Historical<br>Performance</h1>';
	$siteAuditPage .= '[clear height="60px"]';
		
	$siteAuditPage .= '[section][layout class="stats '.$siteType.'"]';	
	$siteAuditPage .= '[col]';
	
	$siteAudit = get_option('bp_site_audit_details');
	
	if ( is_array($siteAudit) ) {
	
		array_reverse($siteAudit);
	 
		// rearrange order to match the $criteriaOrder array
		foreach ($siteAudit as $date => $auditDetails) :
			$sortedDetails = [];
			foreach ($criteriaOrder as $criteria) :
				if (isset($auditDetails[$criteria])) :
					$sortedDetails[$criteria] = $auditDetails[$criteria];
				else:
					$sortedDetails[$criteria] = "—";
				endif;
			endforeach;
			$siteAudit[$date] = $sortedDetails;
		endforeach;

		$siteAuditPage .="<table>";

		$siteAuditPage .= "<tr><th>Criteria</th>";
		foreach ($siteAudit as $date => $auditDetails) :
			$siteAuditPage .= '<th><span class="month">'.date("M j", strtotime($date)).'</span><br>'.date("Y", strtotime($date)).'</th>';
		endforeach;
		$siteAuditPage .= "</tr>";

		// Collect all criteria keys
		$criteriaKeys = array_keys(current($siteAudit));
		$alt = 0;

		// Add rows for each criterion
		foreach ($criteriaKeys as $criteria) :

			$count = count($siteAudit) + 1;

			$headlines = [
				'lighthouse-mobile-score' => 'Mobile Lighthouse',
				'lighthouse-desktop-score' => 'Desktop Lighthouse',
				'keyword-page-1' => 'Keyword Rank',
				'database-page-gen-time' => 'Query Monitor',
				'back-total-links' => 'Backlinks',
				'cite-citations' => 'Citations',
				'console-indexed' => 'Search Console',
				'gmb-overview' => 'Google Business Profile',
				'load_time_mobile' => 'Website Details',
				'audit-ada' => 'Passed Audits',
			];

			if (array_key_exists($criteria, $headlines)) {
				$alt = ($alt == 0) ? 1 : 0;
				$siteAuditPage .= '<tr><td colspan="'.$count.'" class="headline color-'.$alt.'">'.$headlines[$criteria].'</td></tr>';
			}

			$siteAuditPage .= '<tr><td class="subheadline color-'.$alt.'">'.ucwords(str_replace(['-', '_'], ' ', $criteria)).'</td>';
			foreach ($siteAudit as $auditDetails) :
				$auditDetail = $auditDetails[$criteria] ?? '';
				$siteAuditPage .= '<td class="stat color-'.$alt.' '.$criteria.'">';
				$siteAuditPage .= $auditDetail === "true" ? '●' : ($auditDetail === "false" ? '—' : $auditDetail);
				$siteAuditPage .= '</td>';
			endforeach;
			$siteAuditPage .= "</tr>";
		endforeach;
	}
	
	$siteAuditPage .= '</table>[/col][/layout][/section]</div></div><!--site-audit-wrap-->';
	echo do_shortcode($siteAuditPage);
	
	if ( is_array($siteAudit)) updateOption( 'site_updated', strtotime(array_key_first($siteAudit)) ); 
	exit();
}  

// Set up brand new site
function battleplan_clear_all() {
	battleplan_clear_hvac(true);
}

function battleplan_clear_hvac($all=false) {
	$deleteImgs = array ('testimonials', 'photos', 'graphics', 'logos');
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