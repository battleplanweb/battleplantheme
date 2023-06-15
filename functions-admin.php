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
	$printFooter .= '<div style="grid-row: span 2; align-self: center;"><img src="https://battleplanwebdesign.com/wp-content/uploads/site-icon-80x80.png" /></div>';
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
add_filter( 'admin_bar_menu', 'battleplan_replace_howdy', 25 );
function battleplan_replace_howdy( $wp_admin_bar ) {
	 $my_account=$wp_admin_bar->get_node('my-account');
	 $newtitle = str_replace( 'Howdy,', 'Welcome,', $my_account->title );
	 $wp_admin_bar->add_node( array( 'id'=>'my-account', 'title'=>$newtitle, ) );
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
	remove_menu_page( 'link-manager.php' );       									// Links
	remove_menu_page( 'edit-comments.php' );       									// Comments	
	remove_menu_page( 'wpcf7' );       											// Contact Forms	
	remove_menu_page( 'edit.php?post_type=acf-field-group' );       						// Custom Fields
	remove_menu_page( 'themes.php' );       										// Appearance
	remove_menu_page( 'wpengine-common' );   										// WP Engine
	remove_menu_page( 'wp-mail-smtp' );   											// WP Mail SMTP
	remove_menu_page( 'wpseo_dashboard' );   										// Yoast SEO
	
	remove_submenu_page( 'plugins.php', 'plugin-editor.php' );        					// Plugins => Plugin Editor
	remove_submenu_page( 'options-general.php', 'options-writing.php' );   				// Settings => Writing 		
	remove_submenu_page( 'options-general.php', 'options-reading.php' );   				// Settings => Reading 	
	remove_submenu_page( 'options-general.php', 'options-media.php' );   					// Settings => Media 	
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
	
	$the_query = new WP_Query( array('post_type' => 'elements', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'asc') );
	if ( $the_query->have_posts() ) : 
		while ( $the_query->have_posts() ) : 
			$the_query->the_post();
			add_submenu_page( 'edit.php?post_type=elements', get_the_title(), get_the_title(), 'manage_options', '/post.php?post='.get_the_ID().'&action=edit' );
		endwhile;
		wp_reset_postdata();	
	endif;			
	
	if ( is_null(get_page_by_path('widgets', OBJECT, 'elements')) ) add_submenu_page( 'edit.php?post_type=elements', 'Widgets', 'Widgets', 'manage_options', 'widgets.php' );

	add_submenu_page( 'edit.php?post_type=elements', 'Menus', 'Menus', 'manage_options', 'nav-menus.php' );		
	add_submenu_page( 'edit.php?post_type=elements', 'Contact Forms', 'Contact Forms', 'manage_options', 'admin.php?page=wpcf7' );		
	add_submenu_page( 'edit.php?post_type=elements', 'Comments', 'Comments', 'manage_options', 'edit-comments.php' );
	add_submenu_page( 'edit.php?post_type=elements', 'Custom Fields', 'Custom Fields', 'manage_options', 'edit.php?post_type=acf-field-group' );		
	add_submenu_page( 'edit.php?post_type=elements', 'Themes', 'Themes', 'manage_options', 'themes.php' );		
	add_submenu_page( 'options-general.php', 'Options', 'Options', 'manage_options', 'options.php' );
	add_submenu_page( 'tools.php', 'WP Engine', 'WP Engine', 'manage_options', 'options-general.php?page=wpengine-common' );
	
	if ( _USER_LOGIN == "battleplanweb" ) :
		add_submenu_page( 'tools.php', 'Git Updater', 'Git Updater', 'manage_options', 'options-general.php?page=git-updater' );
		add_submenu_page( 'tools.php', 'Admin Columns', 'Admin Columns', 'manage_options', 'options-general.php?page=codepress-admin-columns' );
		add_submenu_page( 'tools.php', 'WP Mail SMTP', 'WP Mail SMTP', 'manage_options', 'options-general.php?page=wp-mail-smtp' );

		add_submenu_page( 'tools.php', 'Yoast Settings', 'Yoast Settings', 'manage_options', 'admin.php?page=wpseo_page_settings' );
		add_submenu_page( 'tools.php', 'Yoast Local', 'Yoast Local', 'manage_options', 'admin.php?page=wpseo_local' );
		add_submenu_page( 'tools.php', 'Yoast Redirects', 'Yoast Redirects', 'manage_options', 'admin.php?page=wpseo_redirects' );
	endif;		
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
		$num_posts = $count_posts > 0 ? $count_posts->publish : 0;
		global $menu;

		foreach ($menu as $key => $value) :
			if ($menu[$key][2] === 'edit.php?post_type=' . $postType) :				  		  
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
	remove_action('welcome_panel','wp_welcome_panel'); 							//Welcome to WordPress!
	remove_meta_box('tribe_dashboard_widget', 'dashboard', 'normal'); 				//News From Modern Tribe	
	remove_meta_box('tribe_dashboard_widget', 'dashboard', 'side'); 					//News From Modern Tribe
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
	remove_meta_box('woocommerce_dashboard_status','dashboard','normal');				// Woocommerce
	remove_meta_box('woocommerce_dashboard_status','dashboard','side');				// Woocommerce
	remove_meta_box('dashboard_primary','dashboard','normal'); 						// WordPress Events and News
	remove_meta_box('dashboard_primary','dashboard','side'); 						// WordPress Events and News
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','normal');			// WP Mail SMTP
	remove_meta_box('wp_mail_smtp_reports_widget_lite','dashboard','side');			// WP Mail SMTP
	remove_meta_box('wp_mail_smtp_reports_widget_pro','dashboard','normal');			// WP Mail SMTP Pro
	remove_meta_box('wp_mail_smtp_reports_widget_pro','dashboard','side');			// WP Mail SMTP Pro	
	remove_meta_box('wpseo-dashboard-overview','dashboard','normal');				// Yoast SEO Posts Overview
	remove_meta_box('wpseo-dashboard-overview','dashboard','side');					// Yoast SEO Posts Overview
	remove_meta_box('wpseo-wincher-dashboard-overview','dashboard','normal');			// Yoast SEO / Wincher Top Keyphrases
	remove_meta_box('wpseo-wincher-dashboard-overview','dashboard','side');			// Yoast SEO / Wincher Top Keyphrases	
}

// Load site stats if hooked to Google Analytics
if ( isset(get_option('customer_info')['google-tags']['prop-id']) && get_option('customer_info')['google-tags']['prop-id'] > 1 && is_admin() && _USER_LOGIN == "battleplanweb" ) require_once get_template_directory().'/functions-admin-stats.php';


/*--------------------------------------------------------------
# Admin Page Set Up
--------------------------------------------------------------*/
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
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return $post_id;
	if ( defined('DOING_AJAX') && DOING_AJAX ) return $post_id;
    if ( !current_user_can("edit_post", $post_id) ) return $post_id;
			
	$lastViewed = readMeta( $post_id, 'log-last-viewed' );
	if ( !$lastViewed ) updateMeta( $post_id, 'log-last-viewed', strtotime("-2 days"));	

    $updateRemoveSidebar = "";
    if ( isset($_POST["remove_sidebar"]) ) $updateRemoveSidebar = $_POST["remove_sidebar"];   
    update_post_meta($post_id, "_bp_remove_sidebar", $updateRemoveSidebar);
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
	
	$criteria = array ('lighthouse-mobile-score', 'lighthouse-mobile-fcp', 'lighthouse-mobile-si', 'lighthouse-mobile-lcp', 'lighthouse-mobile-tti', 'lighthouse-mobile-tbt', 'lighthouse-mobile-cls', 'lighthouse-desktop-score', 'lighthouse-desktop-fcp', 'lighthouse-desktop-si', 'lighthouse-desktop-lcp', 'lighthouse-desktop-tti', 'lighthouse-desktop-tbt', 'lighthouse-desktop-cls', 'keyword-page-1', 'keyword-needs-attn', 'cite-citations', 'cite-total-links', 'cite-local-links', 'cite-domains', 'gmb-overview', 'gmb-calls', 'gmb-msg', 'gmb-clicks', 'google-rating', 'google-reviews');
	
	if ( $submitCheck == "true" ) :
		$siteAudit = get_option('bp_site_audit_details');
		if ( !is_array($siteAudit) ) $siteAudit = array();	
		foreach ( $criteria as $log ) :
			if ( $_POST[$log] || $_POST[$log] == '0' ) :
				$decimals = 0;
				if ( $log == "lighthouse-mobile-si" || $log == "lighthouse-desktop-si" || $log == "lighthouse-mobile-fcp" || $log == "lighthouse-desktop-fcp" || $log == "lighthouse-mobile-lcp" || $log == "lighthouse-desktop-lcp" || $log == "lighthouse-mobile-tti" || $log == "lighthouse-desktop-tti" ) : $decimals = 1; endif;
				if ( $log == "lighthouse-mobile-cls" || $log == "lighthouse-desktop-cls" ) : $decimals = 3; endif;
				$updateNum = number_format((string)$_POST[$log], $decimals);
				//$siteAudit[$today][$log] = str_replace('.0', '', $updateNum);
				$siteAudit[$today][$log] = $updateNum;
			elseif ( !$siteAudit[$today][$log] && $siteAudit[$today][$log] != '0' ) : 
				$siteAudit[$today][$log] = "n/a";
			endif;		
		endforeach;	
	endif;
		
	array_push( $criteria, 'load_time_mobile', 'load_time_desktop', 'optimized', 'testimonials', 'menu-testimonials', 'testimonials-pct', 'coupon', 'coupon-pct', 'blog', 'galleries', 'home-call-to-action', 'homepage-teasers', 'logo-slider', 'service-map', 'bbb-link', 'financing-link', 'menu-finance', 'finance-pct', 'why-choose', 'emergency-service', 'symptom-checker', 'faq', 'tip-of-the-month', 'maintenance-tips');	
	
	if ( $submitCheck == "true" ) :
		if ( $_POST['notes'] ) :
			if ( isset( $_POST['erase-note'] ) ) :
				$siteAudit[$today]['notes'] = $_POST['notes'];
			else:
				$siteAudit[$today]['notes'] .= "  ".$_POST['notes'];
			endif;
		endif;	
		
		$googleInfo = get_option('bp_gbp_update');
		$siteAudit[$today]['google-rating'] = number_format($googleInfo['google-rating'], 1, '.', ',');
		$siteAudit[$today]['google-reviews'] = $googleInfo['google-reviews'];
		$siteAudit[$today]['load_time_mobile'] = get_option('load_time_mobile');	
		$siteAudit[$today]['load_time_desktop'] = get_option('load_time_desktop');		
		$siteAudit[$today]['testimonials-pct'] = get_option('pct-viewed-testimonials').'%';
		$siteAudit[$today]['coupon-pct'] = get_option('pct-viewed-coupon').'%';
		$siteAudit[$today]['finance-pct'] = get_option('pct-viewed-financing').'%';
		
		if ( wp_count_posts( 'post' )->publish > 0 ) : $siteAudit[$today]['blog'] = wp_count_posts( 'post' )->publish; else: $siteAudit[$today]['blog'] = "false"; endif;
		
		if ( wp_count_posts( 'optimized' )->publish > 0 ) : $siteAudit[$today]['optimized'] = wp_count_posts( 'optimized' )->publish; else: $siteAudit[$today]['optimized'] = "false"; endif;
		
		if ( wp_count_posts( 'testimonials' )->publish > 0 ) : $siteAudit[$today]['testimonials'] = wp_count_posts( 'testimonials' )->publish; else: $siteAudit[$today]['testimonials'] = "false"; endif;
		
		if ( wp_count_posts( 'galleries' )->publish > 0 ) : $siteAudit[$today]['galleries'] = wp_count_posts( 'galleries' )->publish; else: $siteAudit[$today]['galleries'] = "false"; endif;
		
		$coupon = $whyChoose = $logoSlider = $tip = $hvacMaint = $homeTeasers = $emergency = $bbb = $financing = $symptomChecker = $faq = $menuTestimonials = $menuFinance = $homeCallToAction = $serviceMap = "false";

		$args = array ('posts_per_page'=>-1, 'post_type'=>array('page', 'post', 'optimized'));
		$check_posts = new WP_Query($args);		
		if( $check_posts->have_posts() ) : while ($check_posts->have_posts() ) : $check_posts->the_post();	

			$checkContent = get_the_content();	
			if ( rtrim(get_the_permalink(),'/') == get_site_url() && strpos(substr(trim($checkContent), -7), "</h") !== false ) $homeCallToAction = "true";
			if ( strpos(get_the_permalink(), "contact") !== false && ( strpos($checkContent, "map") !== false || strpos($checkContent, "Map") !== false )) $serviceMap = "true";

			$checkContent = $checkContent.get_post_meta( get_the_ID(), 'page-bottom_text' )[0];			
			if ( strpos($checkContent, "coupon") !== false ) $coupon = "true";	
			if ( strpos($checkContent, "[why-choose") !== false ) $whyChoose = "true";	
			if ( strpos($checkContent, "[get-logo-slider") !== false ) $logoSlider = "true";			
			if ( strpos($checkContent, "[hvac-tip-of-the-month") !== false ) $tip = "true";	
			if ( strpos($checkContent, "[hvac-maintenance-tips") !== false || strpos($checkContent, "/maintenance-tips/") !== false ) $hvacMaint = "true";	
			if ( strpos($checkContent, "Home Page Teasers") !== false ) $homeTeasers = "true";					
			if ( strpos($checkContent, "[get-emergency-service") !== false ) $emergency = "true";	
			if ( strpos($checkContent, "[get-financing") !== false || strpos($checkContent, "[get-wells-fargo") !== false ) $financing = "true";	
		endwhile; endif; wp_reset_postdata();

		$check_menus = wp_get_nav_menu_items('main-menu');
		foreach ($check_menus as $menu_item) :
			if (empty($menu_item->menu_item_parent)) :
				if ( strpos($menu_item->title, "FAQ") !== false ) $faq = "true";
				if ( strpos($menu_item->title, "Testimonials") !== false ) $menuTestimonials = "true";
				if ( strpos($menu_item->title, "Financing") !== false ) $menuFinance = "true";					
			endif;
		endforeach;
		
		$args = array ('posts_per_page'=>-1, 'post_type'=>array('elements'));
		$check_posts = new WP_Query($args);	
		if( $check_posts->have_posts() ) : while ($check_posts->have_posts() ) : $check_posts->the_post();	
			$checkContent = strtolower(get_the_content());	
			if ( strpos( $checkContent, "emergency service") !== false || strpos( $checkContent, "emergency-service") !== false ) $emergency = "true";	
			if ( strpos( $checkContent, "bbb") !== false ) $bbb = "true";
			if ( strpos( $checkContent, "[get-financing") !== false || strpos($checkContent, "[get-wells-fargo") !== false ) $financing = "true";	
			if ( strpos( $checkContent, "symptom checker") !== false || strpos( $checkContent, "symptom-checker") !== false ) $symptomChecker = "true";
		endwhile; endif; wp_reset_postdata();

		$siteAudit[$today]['coupon'] = $coupon;	
		$siteAudit[$today]['why-choose'] = $whyChoose;	
		$siteAudit[$today]['logo-slider'] = $logoSlider;	
		$siteAudit[$today]['tip-of-the-month'] = $tip;	 
		$siteAudit[$today]['maintenance-tips'] = $hvacMaint;	
		$siteAudit[$today]['homepage-teasers'] = $homeTeasers;	
		$siteAudit[$today]['emergency-service'] = $emergency;	
		$siteAudit[$today]['bbb-link'] = $bbb;	
		$siteAudit[$today]['financing-link'] = $financing;	 
		$siteAudit[$today]['symptom-checker'] = $symptomChecker;	
		$siteAudit[$today]['faq'] = $faq;	
		$siteAudit[$today]['menu-testimonials'] = $menuTestimonials;	
		$siteAudit[$today]['menu-finance'] = $menuFinance;	
		$siteAudit[$today]['home-call-to-action'] = $homeCallToAction;
		$siteAudit[$today]['service-map'] = $serviceMap;						

		updateOption('bp_site_audit_details', $siteAudit, false);
	endif;
	
	$siteAuditPage = '<div class="wrap">';
	$siteAuditPage .= '<h1>Site Audit</h1>';
	
	$siteAuditPage .= '<form method="post">';
	
	$siteAuditPage .= '[section][layout class="inputs"][col]';
	
		$siteAuditPage .= '<h1>Lighthouse</h1>';
		
		$siteAuditPage .= '<h3>Mobile</h3>';		
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-score">Performance Score:</label><input id="lighthouse-mobile-score" type="text" name="lighthouse-mobile-score" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-fcp">First Contentful Paint:</label><input id="lighthouse-mobile-fcp" type="text" name="lighthouse-mobile-fcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-tbt">Total Blocking Time:</label><input id="lighthouse-mobile-tbt" type="text" name="lighthouse-mobile-tbt" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-si">Speed Index:</label><input id="lighthouse-mobile-si" type="text" name="lighthouse-mobile-si" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-lcp">Largest Contentful Paint:</label><input id="lighthouse-mobile-lcp" type="text" name="lighthouse-mobile-lcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-cls">Cumulative Layout Shift:</label><input id="lighthouse-mobile-cls" type="text" name="lighthouse-mobile-cls" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-mobile-tti">Time To Interactive:</label><input id="lighthouse-mobile-tti" type="text" name="lighthouse-mobile-tti" value=""></div>';
		
		$siteAuditPage .= '<h3>Desktop</h3>';			
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-score">Performance Score:</label><input id="lighthouse-desktop-score" type="text" name="lighthouse-desktop-score" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-fcp">First Contentful Paint:</label><input id="lighthouse-desktop-fcp" type="text" name="lighthouse-desktop-fcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-tbt">Total Blocking Time:</label><input id="lighthouse-desktop-tbt" type="text" name="lighthouse-desktop-tbt" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-si">Speed Index:</label><input id="lighthouse-desktop-si" type="text" name="lighthouse-desktop-si" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-lcp">Largest Contentful Paint:</label><input id="lighthouse-desktop-lcp" type="text" name="lighthouse-desktop-lcp" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-cls">Cumulative Layout Shift:</label><input id="lighthouse-desktop-cls" type="text" name="lighthouse-desktop-cls" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="lighthouse-desktop-tti">Time To Interactive:</label><input id="lighthouse-desktop-tti" type="text" name="lighthouse-desktop-tti" value=""></div>';
		
	$siteAuditPage .= '[/col][col]';
	
		$siteAuditPage .= '<h1>Keyword Rank</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="keyword-page-1">Page One:</label><input id="keyword-page-1" type="text" name="keyword-page-1" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="keyword-needs-attn">Needs Attention:</label><input id="keyword-needs-attn" type="text" name="keyword-needs-attn" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Backlinks</h1>';	
		$siteAuditPage .= '<div class="form-input"><label for="cite-total-links">Total Links:</label><input id="cite-total-links" type="text" name="cite-total-links" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-domains">Linking Domains:</label><input id="cite-domains" type="text" name="cite-domains" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-local-links">Local Links:</label><input id="cite-local-links" type="text" name="cite-local-links" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="cite-citations">Citations:</label><input id="cite-citations" type="text" name="cite-citations" value=""></div>';
		
		$siteAuditPage .= '<br>';

		$siteAuditPage .= '<h1>Google My Business</h1>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-overview">Overview:</label><input id="gmb-overview" type="text" name="gmb-overview" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-calls">Calls:</label><input id="gmb-calls" type="text" name="gmb-calls" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-msg">Messages:</label><input id="gmb-msg" type="text" name="gmb-msg" value=""></div>';
		$siteAuditPage .= '<div class="form-input"><label for="gmb-clicks">Clicks:</label><input id="gmb-clicks" type="text" name="gmb-clicks" value=""></div>';

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
	$siteAuditPage .= '[col class="empty"][/col]';
	$siteAuditPage .= '[col class="headline lighthouse-mobile-score"]Performance Score[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-fcp"]First Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-si"]Speed Index[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-lcp"]Largest Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-tti"]Time To Interactive[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-tbt"]Total Blocking Time[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-mobile-cls"]Cumulative Layout Shift[/col]';
	$siteAuditPage .= '[col class="headline lighthouse-desktop-score"]Performance Score[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-fcp"]First Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-si"]Speed Index[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-lcp"]Largest Contentful Paint[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-tti"]Time To Interactive[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-tbt"]Total Blocking Time[/col]';	
	$siteAuditPage .= '[col class="headline lighthouse-desktop-cls"]Cumulative Layout Shift[/col]';	
	$siteAuditPage .= '[col class="headline keyword-page-1"]Page One[/col]';	
	$siteAuditPage .= '[col class="headline keyword-needs-attn"]Needs Attention[/col]';	
	$siteAuditPage .= '[col class="headline cite-citations"]Citations[/col]';	
	$siteAuditPage .= '[col class="headline cite-total-links"]Total Links[/col]';	
	$siteAuditPage .= '[col class="headline cite-local-links"]Local Links[/col]';	
	$siteAuditPage .= '[col class="headline cite-domains"]Linking Domains[/col]';
	$siteAuditPage .= '[col class="headline gmb-overview"]Overview[/col]';	
	$siteAuditPage .= '[col class="headline gmb-calls"]Calls[/col]';	
	$siteAuditPage .= '[col class="headline gmb-msg"]Messages[/col]';	
	$siteAuditPage .= '[col class="headline gmb-clicks"]Clicks[/col]';		
	$siteAuditPage .= '[col class="headline google-rating"]Rating[/col]';		
	$siteAuditPage .= '[col class="headline google-reviews"]Number[/col]';	
	$siteAuditPage .= '[col class="headline load_time_mobile"]Mobile[/col]';		
	$siteAuditPage .= '[col class="headline load_time_desktop"]Desktop[/col]';			
	$siteAuditPage .= '[col class="headline optimized"]Optimized[/col]';
	$siteAuditPage .= '[col class="headline testimonials"]Testimonials[/col]';		
	$siteAuditPage .= '[col class="headline menu-testimonials"]Testimonials Button[/col]';		
	$siteAuditPage .= '[col class="headline testimonials-pct"]Testimonials View %[/col]';	
	$siteAuditPage .= '[col class="headline coupon"]Coupon[/col]';				
	$siteAuditPage .= '[col class="headline coupon-pct"]Coupon View %[/col]';
	$siteAuditPage .= '[col class="headline blog"]Blog[/col]';	
	$siteAuditPage .= '[col class="headline galleries"]Galleries[/col]';	
	$siteAuditPage .= '[col class="headline home-call-to-action"]Home Call To Action[/col]';			
	$siteAuditPage .= '[col class="headline homepage-teasers"]Home Page Teasers[/col]';	
	$siteAuditPage .= '[col class="headline logo-slider"]Logo Slider[/col]';			
	$siteAuditPage .= '[col class="headline service-map"]Service Map[/col]';		
	$siteAuditPage .= '[col class="headline bbb-link"]BBB[/col]';	
	$siteAuditPage .= '[col class="headline financing-link"]Financing Ad[/col]';
	$siteAuditPage .= '[col class="headline menu-finance"]Financing Button[/col]';		
	$siteAuditPage .= '[col class="headline finance-pct"]Financing View %[/col]';
	$siteAuditPage .= '[col class="headline why-choose"]Why Choose Us[/col]';		
	$siteAuditPage .= '[col class="headline emergency-service"]24/7 Service[/col]';	
	$siteAuditPage .= '[col class="headline symptom-checker"]Symptom Checker[/col]';			
	$siteAuditPage .= '[col class="headline faq"]FAQ[/col]';				
	$siteAuditPage .= '[col class="headline tip-of-the-month"]Tip Of The Month[/col]';			
	$siteAuditPage .= '[col class="headline maintenance-tips"]Maintenance Tips[/col]';
	
	$siteAuditPage .= '[col class="subhead date"]Date[/col]';	
	$siteAuditPage .= '[col class="subhead lighthouse mobile"]Mobile[/col]';	
	$siteAuditPage .= '[col class="subhead lighthouse desktop"]Desktop[/col]';	
	$siteAuditPage .= '[col class="subhead keywords"]Keywords[/col]';	
	$siteAuditPage .= '[col class="subhead citations"]Citations[/col]';	
	$siteAuditPage .= '[col class="subhead gmb"]Google My Business[/col]';		
	$siteAuditPage .= '[col class="subhead revs"]Reviews[/col]';	
	$siteAuditPage .= '[col class="subhead site-speed"]Site Speed[/col]';	
	$siteAuditPage .= '[col class="subhead site-elements"]Site Elements[/col]';
	$siteAuditPage .= '[col class="subhead site-hvac"]HVAC Elements[/col]';	
	
	$siteAudit = get_option('bp_site_audit_details');
	
	if (is_array($siteAudit) )$siteAudit = array_reverse($siteAudit);
	foreach ( $siteAudit as $date=>$auditDetails ) :
		$siteAuditPage .= '[col class="when"]'.date("M j, Y", strtotime($date)).'[/col]';
		foreach ( $criteria as $auditDetail ) :			
			$siteAuditPage .= '[col class="stat '.$auditDetail.'"]';
			
			if ( $auditDetails[$auditDetail] == "true" ) :
				$siteAuditPage .= '●';
			elseif ( $auditDetails[$auditDetail] == "false" ) :
				$siteAuditPage .= '◦';
			elseif ( !is_numeric($auditDetails[$auditDetail]) ) :
				$siteAuditPage .= $auditDetails[$auditDetail];
			else:
				$siteAuditPage .= $auditDetails[$auditDetail];
			endif;
			$siteAuditPage .= '[/col]';
		endforeach;	
		$siteAuditPage .= '[col class="notes"]'.$auditDetails['notes'].'[/col]';
	endforeach;
	
	$siteAuditPage .= '[/layout][/section]</div></div><!--site-audit-wrap-->';
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

	$optimized = get_posts( array('post_type'=>'optimized', 'numberposts'=>-1) );
	$testimonials = get_posts( array('post_type'=>'testimonials', 'numberposts'=>-1) );
	$galleries = get_posts( array('post_type'=>'galleries', 'numberposts'=>-1) );
	$posts = get_posts( array('post_type'=>'post', 'numberposts'=>-1) );
	$elements = get_posts( array('post_type'=>'elements', 'numberposts'=>-1) );
	$pages = get_posts( array('post_type'=>'page', 'numberposts'=>-1) );
	
	if ( $all == true ) :
		$products = get_posts( array('post_type'=>'products', 'numberposts'=>-1) );
		foreach ($products as $post) wp_delete_post( $post->ID, true );
		array_push($deleteImgs, 'products');
		if (in_array('product-overview', $keepPages)) unset($keepPages[array_search('product-overview', $keepPages)]);
	endif;

	foreach ($optimized as $post) wp_delete_post( $post->ID, true );
	foreach ($testimonials as $post) wp_delete_post( $post->ID, true );
	foreach ($galleries as $post) wp_delete_post( $post->ID, true );
	foreach ($posts as $post) wp_delete_post( $post->ID, true );
	foreach ($elements as $post) if ( !in_array( $post->post_name, $keepElements) ) wp_delete_post( $post->ID, true );
	foreach ($pages as $post) if ( !in_array( $post->post_name, $keepPages) ) wp_delete_post( $post->ID, true );

	$args = array( 'post_status' => 'inherit', 'posts_per_page' => -1, 'post_type' => 'attachment', 'post_mime_type' => 'image', );
	$args['tax_query'] = array( array( 'taxonomy' => 'image-categories', 'terms' => $deleteImgs, 'field' => 'slug', ),);
	$getImg = new WP_Query( $args );

	if ( $getImg->have_posts() ) : 
		while ( $getImg->have_posts() ) :
			$getImg->the_post();	
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
	delete_option('bp_site_hits_ga4');	
	delete_option('bp_site_hits_ua_1');	
	delete_option('bp_site_hits_ua_2');		
	delete_option('bp_site_hits_ua_3');		
	delete_option('bp_site_hits_ua_4');		
	delete_option('bp_site_hits_ua_5');
	delete_option('bp_site_hits_ua_1_backup');	
	delete_option('bp_site_hits_ua_2_backup');		
	delete_option('bp_site_hits_ua_3_backup');		
	delete_option('bp_site_hits_ua_4_backup');		
	delete_option('bp_site_hits_ua_5_backup');	
	delete_option('bp_site_audit_details');

	updateOption('bp_chron_time', 0);
	updateOption('bp_launch_date', date('Y-m-d'));
	
	header("Location: /wp-admin/");
	exit();
}  

?>