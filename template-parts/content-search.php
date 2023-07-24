<?php /*** Template part for displaying results in search pages */ ?>

<?php
	$postType = get_post_type();
	
	if ( $postType == "optimized" || $postType == "landing" || $postType == "universal" ) $postType = "page";
	if ( $postType == "testimonials" ) $postType = "testimonial";
	if ( $postType == "galleries" ) $postType = "gallery";	
	
	$buildListing .= '<li id="post-'.get_the_ID().'" class="clearfix"><p>';

	if ( has_post_thumbnail() ) : 	
		$size = 'icon';
		$meta = wp_get_attachment_metadata( get_post_thumbnail_id( get_the_ID() ) );
		$thumbW = $meta['sizes'][$size]['width'];
		$thumbH = $meta['sizes'][$size]['height'];
	
		$buildListing .= do_shortcode('[img size="100" class="search-thumbnail" link="'.esc_url( get_permalink()).'" '.$picADA.']'.get_the_post_thumbnail( get_the_ID(), $size, array( 'class'=>'align-left img-archive img-search', 'style'=>'aspect-ratio:'.$thumbW.'/'.$thumbH )).'[/img]'); 
	endif;
	
	$buildListing .= '<a href = "'.esc_url( get_permalink()).'">'.get_the_title().'<span class="post-type"> - '.$postType.'</span></a><br>';
	$buildListing .= get_the_excerpt();
	
	$buildListing .= '</p></li>';
	echo do_shortcode($buildListing);
?>