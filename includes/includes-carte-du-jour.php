<?php
/* Battle Plan Web Design Carte Du Jour Includes

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------

# Basic Theme Set Up

/*--------------------------------------------------------------
# Basic Theme Set Up
--------------------------------------------------------------*/
// Load and enqueue styles & scripts
add_action( 'wp_enqueue_scripts', 'battleplan_carte_du_jour_scripts' );
function battleplan_carte_du_jour_scripts() {
	wp_enqueue_style( 'battleplan-css-carte-du-jour', get_template_directory_uri().'/style-carte-du-jour.css', array(), _BP_VERSION );	 
	wp_enqueue_script( 'battleplan-script-carte-du-jour', get_template_directory_uri().'/js/carte-du-jour.js', array(), _BP_VERSION, false );
}

//Load Titles & Descriptions (Alt Text) of images and save for javascript
add_action('wp_head', 'battleplan_loadPicTitles');
function battleplan_loadPicTitles() { 
	wp_enqueue_script( 'battleplan-script-glightbox', get_template_directory_uri().'/js/glightbox.js', array('jquery'), _BP_VERSION, false ); 
	wp_enqueue_style( 'battleplan-glightbox', get_template_directory_uri()."/style-glightbox.css", array('parent-style'), _BP_VERSION );  
	
	$image_query = new WP_Query( array ( 'post_type'=>'attachment', 'post_status'=>'any', 'post_mime_type'=>'image/jpeg, image/gif, image/jpg, image/png, image/webp', 'posts_per_page'=> -1 ) );
	
	$links = $descs = array();
	
	if( $image_query->have_posts() ) :
		while ($image_query->have_posts() ) : 
		$image_query->the_post();
		
		$title = get_the_title(get_the_ID());
		$desc = get_post_meta( get_the_ID(), '_wp_attachment_image_alt', true );
		$link = wp_get_attachment_image_src( get_the_ID(), 'full' )[0];
		$descs[$title] = $desc;
		$links[$title] = $link;
		
		endwhile;
	endif;
	
	wp_reset_postdata();
	echo '<script nonce="'._BP_NONCE.'" >var imgDesc = '.json_encode($descs).', imgLinks = '.json_encode($links).';</script>';
}

// Save locations from functions-sites.php to a javascript var
add_action('wp_head', 'battleplan_saveLocations');
function battleplan_saveLocations() { 
	$locations = get_option('cdj_locations');
	echo '<script nonce="'._BP_NONCE.'" >var locArray = '.json_encode($locations).';</script>';
}

// Remove / display correct phone number on mobile menu bar
add_action( 'bp_mobile_menu_bar_phone', 'battleplan_carte_du_jour_phone' );
function battleplan_carte_du_jour_phone() {
	remove_action('bp_mobile_menu_bar_phone', 'battleplan_mobile_menu_bar_phone', 20);
	
	$buildPhone = '<div class="hide-unknown hide-2 hide-3 hide-4 hide-5">';	
	foreach ( get_option('cdj_locations') as $loc ) :	
		$buildPhone .= '<a href="#" class="show-'.$loc['slug'].' phone-link track-clicks" data-action="phone call" data-url="tel:1-'.$loc['phone'].'"><div class="mm-bar-btn mm-bar-phone call-btn" aria-hidden="true"></div><span class="sr-only">Call Us</span></a>';
	endforeach;
	$buildPhone .= '</div>';
		
	echo $buildPhone;
}

?>