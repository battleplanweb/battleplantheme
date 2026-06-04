<?php
/* Battle Plan Web Design Functions: Custom Post Types

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Register Custom Post Types
# Import Advanced Custom Fields

/*--------------------------------------------------------------
# Register Custom Post Types
--------------------------------------------------------------*/

add_action( 'init', 'battleplan_registerPostTypes', 0 );
function battleplan_registerPostTypes() {
	register_post_type( 'testimonials', array (
		'label'=>__( 'testimonials', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Testimonials', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Testimonial', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>true,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-format-quote',
		'has_archive'=>true,
		'capability_type'=>'page',
	));
	register_post_type( 'galleries', array (
		'label'=>__( 'galleries', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Galleries', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Gallery', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields', 'comments' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-images-alt',
		'has_archive'=>true,
		'capability_type'=>'page',
	));
	register_taxonomy( 'gallery-type', array( 'galleries' ), array(
		'labels'=>array(
			'name'=>_x( 'Gallery Type', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Gallery Type', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>true,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	wp_insert_term( 'Auto Generated', 'gallery-type' );
	wp_insert_term( 'Shortcode', 'gallery-type' );
	register_taxonomy( 'gallery-tags', array( 'galleries' ), array(
		'labels'=>array(
			'name'=>_x( 'Gallery Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Gallery Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'hierarchical'=>false,
		'show_ui'=>true,
        'show_admin_column'=>true,
	));
	register_taxonomy( 'image-categories', array( 'attachment' ), array(
		'labels'=>array(
			'name'=>_x( 'Image Categories', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Image Category', 'Taxonomy Singular Name', 'text_domain' ),
		),
        	'rewrite'				=>true,
        	'show_admin_column'		=>true,
		'exclude_from_search'	=>true,
		'show_in_nav_menus'		=>false,
		'public'       		=> true,
		'show_ui'      		=> true,
		'show_in_rest' 		=> true,
		'hierarchical' 		=> true,
		'query_var'    		=> true,
	));
	register_taxonomy( 'image-tags', array( 'attachment' ), array(
		'labels'=>array(
			'name'=>_x( 'Image Tags', 'Taxonomy General Name', 'text_domain' ),
			'singular_name'=>_x( 'Image Tag', 'Taxonomy Singular Name', 'text_domain' ),
		),
		'rewrite'				=>true,
		'show_admin_column'		=>true,
		'exclude_from_search'	=>true,
		'show_in_nav_menus'		=>false,
		'public'       		=> true,
		'show_ui'      		=> true,
		'show_in_rest' 		=> true,
		'hierarchical' 		=> false,
		'query_var'    		=> true,
	));
	register_post_type( 'landing', array (
		'label'=>__( 'landing', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Landing', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Landing', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail', 'page-attributes', 'custom-fields' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-edit-page',
		'has_archive'=>false,
		'capability_type' => 'page',

		//'rewrite' => array('slug' => 'service-areas'), // This line changes the slug
	));
	register_post_type( 'elements', array (
		'label'=>__( 'elements', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Elements', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Element', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>false,
		'exclude_from_search'=>true,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-block-default',
		'has_archive'=>false,
		'capability_type'=>'page',
	));
	register_post_type( 'universal', array (
		'label'=>__( 'universal', 'battleplan' ),
		'labels'=>array(
			'name'=>_x( 'Universal', 'Post Type General Name', 'battleplan' ),
			'singular_name'=>_x( 'Universal', 'Post Type Singular Name', 'battleplan' ),
		),
		'public'=>true,
		'publicly_queryable'=>true,
		'exclude_from_search'=>false,
		'show_in_nav_menus'=>false,
		'supports'=>array( 'title', 'editor', 'thumbnail' ),
		'hierarchical'=>false,
		'menu_position'=>20,
		'menu_icon'=>'dashicons-admin-site-alt3',
		'has_archive'=>false,
		'capability_type' => 'page',
		'capabilities' => array(
			'create_posts' => false,
		),
		'map_meta_cap' => true,
	));
}

// Unregister 'landing' CPT if jobsite_geo is installed (it replaces the same function)
add_action('init', function() {
	$jobsite_geo = get_option('jobsite_geo');
	if (!empty($jobsite_geo['install']) || post_type_exists('jobsite_geo')) {
		unregister_post_type('landing');
	}
}, 20);

// Remove 'landing', 'universal', and 'elements' from the url so that pages look like regular pages
add_filter( 'post_type_link', 'battleplan_remove_cpt_slug', 10, 2 );
function battleplan_remove_cpt_slug( $post_link, $post ) {
	if ( 'universal' === $post->post_type || 'landing' === $post->post_type || 'elements' === $post->post_type ) $post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );
 	return $post_link;
}

// Add certain post types to the main query, along with pages and posts
add_action( 'pre_get_posts', 'battleplan_add_cpt_to_main_query' );
function battleplan_add_cpt_to_main_query( $query ) {
	if ( !$query->is_main_query() ) return;
	if ( !isset( $query->query['page'] ) || 2 !== count( $query->query ) ) return;
	if ( empty( $query->query['name'] ) ) return;
	$query->set( 'post_type', array( 'post', 'page', 'landing', 'universal', 'jobsite_geo' ) );
}

/*--------------------------------------------------------------
# Import Advanced Custom Fields
--------------------------------------------------------------*/
add_action('acf/init', 'battleplan_add_acf_fields');
function battleplan_add_acf_fields() {
	acf_add_local_field_group(array(
		'key' => 'group_7df6f4843vdfg',
		'title' => 'Reference',
		'fields' => array(
			array(
				'key' => 'field_reference',
				'label' => 'Reference #',
				'name' => 'reference',
				'type' => 'text',
				'required' => 0,
				'conditional_logic' => 0,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'post',
				),
			),
		),
		'menu_order' => 1,
		'position' => 'acf_after_title',
		'style' => 'seamless',
		'label_placement' => 'top',
		'active' => true,
		'description' => '',
	));

	acf_add_local_field_group(array(
		'key' => 'group_5bd6f6743bbfe',
		'title' => 'Testimonials',
		'fields' => array(
			array(
				'key' => 'field_testimonial-rating',
				'label' => 'Rating',
				'name' => 'testimonial_rating',
				'type' => 'radio',
				'instructions' => 'Enter the testimonial\'s rating on a scale of 1 to 5.',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					0 => 'Unrated',
					1 => '1',
					2 => '2',
					3 => '3',
					4 => '4',
					5 => '5',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_testimonial-quality',
				'label' => '',
				'name' => 'testimonial_quality',
				'type' => 'checkbox',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					1 => 'Quality Review',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_platform',
				'label' => 'Platform',
				'name' => 'testimonial_platform',
				'type' => 'radio',
				'instructions' => 'What platform did this testimonial come from?',
				'required' => 0,
				'conditional_logic' => 0,
				'choices' => array(
					'None' => 'None',
					'Google' => 'Google',
					'Facebook' => 'Facebook',
					'Yelp' => 'Yelp',
					'YP' => 'YP',
					'Jobber' => 'Jobber',
					'Nextdoor' => 'Nextdoor',
					'BBB' => 'BBB',
					'Angi' => 'Angi',
					'Home_advisor' => 'Home Advisor',
					'Housecall_pro' => 'House Call Pro',
					'Houzz' => 'Houzz',
					'Fiverr' => 'Fiverr',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => 0,
				'layout' => 'horizontal',
				'allow_null' => 0,
				'return_format' => 'value',
			),
			array(
				'key' => 'field_location',
				'label' => 'Location',
				'name' => 'testimonial_location',
				'type' => 'text',
				'instructions' => 'Enter the location of the person giving the testimonial.',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_business-name',
				'label' => 'Business Name',
				'name' => 'testimonial_biz',
				'type' => 'text',
				'instructions' => 'Enter the business name of the person giving the testimonial.',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
			array(
				'key' => 'field_business-website',
				'label' => 'Business Website',
				'name' => 'testimonial_website',
				'type' => 'text',
				'instructions' => 'Enter the website of the person giving the testimonial (include http:// or https://).',
				'required' => 0,
				'conditional_logic' => 0,
				'formatting' => 'html',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'testimonials',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'seamless',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => array(
			0 => 'custom_fields',
			1 => 'discussion',
			2 => 'comments',
			3 => 'revisions',
			4 => 'slug',
			5 => 'author',
			6 => 'format',
			7 => 'categories',
			8 => 'tags',
			9 => 'send-trackbacks',
		),
		'active' => true,
		'description' => '',
	));
}


// ---------------------------------------------------------
// # Daily "new reviews" digest to the client
// ---------------------------------------------------------
// Companion to the jobsite digest (includes-jobsite-geo.php): each morning (~9am)
// email the client any testimonials added in the last 24h, so collecting reviews
// has a visible payoff. Lives here (always-loaded) because testimonials exist on
// every site, not just jobsite_geo ones.
//
// Opt-OUT per site, controlled by the bp_reviews_digest option:
//   'false'            -> off (don't send)
//   'email@domain.com' -> send to that specific address
//   'true' or absent   -> send to customer_info['email'] (default)

add_action( 'init', 'bp_reviews_schedule_daily_digest' );
function bp_reviews_schedule_daily_digest() {
	$setting = get_option('bp_reviews_digest');

	if ( $setting === 'false' ) {
		$ts = wp_next_scheduled('bp_reviews_daily_digest');
		if ( $ts ) wp_unschedule_event($ts, 'bp_reviews_daily_digest');
		return;
	}

	if ( ! wp_next_scheduled('bp_reviews_daily_digest') ) {
		$tz   = wp_timezone();
		$next = new DateTime('today 09:00', $tz);   // ~9am site-local (jobsite digest is 7am)
		if ( $next <= new DateTime('now', $tz) ) $next->modify('+1 day');
		wp_schedule_event( $next->getTimestamp(), 'daily', 'bp_reviews_daily_digest' );
	}
}

add_action( 'bp_reviews_daily_digest', 'bp_reviews_send_daily_digest' );
function bp_reviews_send_daily_digest() {
	$setting = get_option('bp_reviews_digest');
	if ( $setting === 'false' ) return;

	// Recipient: a specific address if set, else the client's own email.
	$customer = get_option('customer_info');
	$to = ( is_string($setting) && $setting !== '' && $setting !== 'true' && is_email($setting) )
		? $setting
		: ( ! empty($customer['email']) && is_email($customer['email']) ? $customer['email'] : '' );
	if ( ! $to ) return;

	// Testimonials published in the last 24h.
	$since   = date( 'Y-m-d H:i:s', current_time('timestamp') - DAY_IN_SECONDS );
	$reviews = get_posts([
		'post_type'      => 'testimonials',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'date_query'     => [[ 'column' => 'post_date', 'after' => $since ]],
	]);
	if ( empty($reviews) ) return; // nothing new — no empty digests

	$site_name = get_bloginfo('name');
	$domain    = parse_url( home_url(), PHP_URL_HOST );
	$logos     = get_template_directory_uri() . '/common/logos/';
	$count     = count($reviews);

	$cards = '';
	foreach ($reviews as $r) {
		$name = get_the_title($r);
		$body = trim( wp_strip_all_tags( $r->post_content ) );
		if ( mb_strlen($body) > 500 ) $body = mb_substr($body, 0, 500) . '…';

		$thumb = get_the_post_thumbnail_url($r->ID, 'thumbnail');

		// Rating -> gold unicode stars (supports halves).
		$rate  = (float) get_post_meta($r->ID, 'testimonial_rating', true);
		$stars = '';
		if ( $rate > 0 ) {
			$full  = (int) floor($rate);
			$half  = ( $rate - $full ) >= 0.5;
			$stars = str_repeat('★', $full) . ( $half ? '½' : '' ) . str_repeat('☆', max(0, 5 - $full - ( $half ? 1 : 0 )));
		}

		// Platform -> hosted logo file (lowercased value; housecall_pro is the one exception).
		$platform = strtolower( trim( (string) get_post_meta($r->ID, 'testimonial_platform', true) ) );
		$pfile    = ( $platform === '' || $platform === 'none' )
			? ''
			: ( $platform === 'housecall_pro' ? 'housecallpro' : $platform );

		$cards .= '<div style="border:1px solid #e2e2e2;border-radius:8px;padding:16px 18px;margin:0 0 16px;">';
		$cards .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 10px;"><tr>';
		if ( $thumb ) {
			$cards .= '<td style="width:64px;vertical-align:middle;"><img src="' . esc_url($thumb) . '" width="52" height="52" alt="" style="border-radius:50%;display:block;object-fit:cover;"></td>';
		}
		$cards .= '<td style="vertical-align:middle;">';
		$cards .= '<p style="margin:0;font-size:15px;font-weight:bold;">' . esc_html($name) . '</p>';
		$meta = '';
		if ( $stars ) $meta .= '<span style="color:#e7a400;font-size:16px;letter-spacing:1px;">' . $stars . '</span>';
		if ( $pfile ) $meta .= ' <img src="' . esc_url($logos . $pfile . '.webp') . '" alt="' . esc_attr($platform) . '" height="16" style="height:16px;width:auto;vertical-align:middle;margin-left:4px;">';
		if ( $meta ) $cards .= '<p style="margin:4px 0 0;">' . $meta . '</p>';
		$cards .= '</td></tr></table>';
		$cards .= '<p style="margin:0;line-height:1.5;color:#333;">' . esc_html($body) . '</p>';
		$cards .= '</div>';
	}

	// Closing encouragement — randomized so every email reads a little differently.
	$notes = [
		'Happy customers are your best marketing — keep asking for reviews!',
		'Every review builds trust with the next customer searching for you. Keep them coming!',
		'Fresh reviews help you stand out in local search — keep requesting them!',
		'Nothing sells like a happy customer\'s words. Keep gathering those reviews!',
		'More reviews mean more confidence for future customers. Keep it up!',
	];
	$note = $notes[ array_rand($notes) ];

	$subject = sprintf( 'You got %d new %s on your website!', $count, _n('review','reviews',$count) );

	$message  = '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#222;">';
	$message .= '<p style="font-size:16px;">We\'ve added the following ' . _n('review','reviews',$count) . ' to your website!</p>';
	$message .= $cards;
	$message .= '<p style="font-size:15px;font-weight:bold;color:#1a7f37;margin-top:22px;">' . $note . '</p>';
	$message .= '</div>';

	$headers   = [];
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	$headers[] = 'From: ' . $site_name . ' <noreply@' . $domain . '>';
	$headers[] = 'Reply-To: noreply@' . $domain;
	// Copy battleplanweb so we see exactly what the client sees (matches the jobsite digest).
	$headers[] = 'Bcc: email@bp-webdev.com';

	wp_mail($to, $subject, $message, $headers);
}