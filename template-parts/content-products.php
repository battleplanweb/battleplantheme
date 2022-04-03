<?php /* Template part for displaying products */

$comfort = esc_attr(get_field( "comfort" ));
$efficiency = esc_attr(get_field( "efficiency" ));
$brochure = esc_url(get_field( "brochure" ));


$buildProduct = '<div class="entry-content">';

	if ( has_post_thumbnail() ) : $buildProduct .= get_the_post_thumbnail( $post->ID, 'thumbnail', array('class' => 'alignright '.get_post_type().'-img size-third-s')); endif;	

	$buildProduct .= get_the_content( sprintf ( wp_kses( __( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'battleplan' ), array( 'span' => array( 'class' => array(), ), ) ), wp_kses_post( get_the_title() ) ) ); 

	$buildProduct .= '<div class="product-meta">';

		if ( $comfort != "na" && $comfort != "" ) : 
			$buildProduct .= '<div class="product-rating">';
				$buildProduct .= '<span class="rating-label">Comfort: </span>';
				if ( $comfort == 1 ) : $buildProduct .= '<span class="comfort-full"></span><span class="comfort-empty"></span><span class="comfort-empty"></span><span class="comfort-empty"></span><span class="comfort-empty"></span>';
				elseif ( $comfort == 2 ) : $buildProduct .= '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-empty"></span><span class="comfort-empty"></span><span class="comfort-empty"></span>';
				elseif ( $comfort == 3 ) : $buildProduct .= '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-empty"></span><span class="comfort-empty"></span>';
				elseif ( $comfort == 4 ) : $buildProduct .= '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-empty"></span>';
				elseif ( $comfort == 5 ) : $buildProduct .= '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span>';
				endif; 
			$buildProduct .= '</div>';
		endif; 

		if ( $efficiency != "na" && $efficiency != "" ) : 
			$buildProduct .= '<div class="product-rating">';
				$buildProduct .= '<span class="rating-label">Efficiency: </span>';
				if ( $efficiency == 1 ) : $buildProduct .= '<span class="efficiency-full"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span>';
				elseif ( $efficiency == 2 ) : $buildProduct .= '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span>';
				elseif ( $efficiency == 3 ) : $buildProduct .= '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span>';
				elseif ( $efficiency == 4 ) : $buildProduct .= '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-empty"></span>';
				elseif ( $efficiency == 5 ) : $buildProduct .= '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span>';
				endif; 
			$buildProduct .= '</div>';
		endif; 		
		
		if ( $brochure != "" ) :
			$is_link_valid = get_headers($brochure, 1);
			if ( strpos( $is_link_valid[0], "404" ) === false ) : $buildProduct .= '» <a href="'.$brochure.'" target="_blank">View Brochure</a>'; endif;
		endif;
							
$buildProduct .= '</div>';			

$buildProduct .= '<div class="product-links">';

	$buildProduct .= '<p><b>Find products by category:</b></p>';	

	$terms = get_the_terms( $post->ID , 'product-brand' ); 
	if ( $terms ) :
		$buildProduct .= '<div class="product-link-cats"><div class="product-link-label">Brand:</div>';
			foreach( $terms as $term ) : 
				$buildProduct .= '<a href="'.get_term_link( $term->slug, 'product-brand').'" rel="tag" class="button">'.$term->name.'</a>';
			endforeach; 	
		$buildProduct .= '</div>';
	endif;
	$terms = get_the_terms( $post->ID , 'product-type' ); 
	if ( $terms ) :
		$buildProduct .= '<div class="product-link-cats"><div class="product-link-label">Type:</div>';
			foreach( $terms as $term ) :
				$buildProduct .= '<a href="'.get_term_link( $term->slug, 'product-type').'" rel="tag" class="button">'.$term->name.'</a>';
			endforeach; 	
		$buildProduct .= '</div>';
	endif;
	$terms = get_the_terms( $post->ID , 'product-class' ); 
	if ( $terms ) :
		$buildProduct .= '<div class="product-link-cats"><div class="product-link-label">Series:</div>';
			foreach( $terms as $term ) : 
				$buildProduct .= '<a href="'.get_term_link( $term->slug, 'product-class').'" rel="tag" class="button">'.$term->name.'</a>';
			endforeach; 	
		$buildProduct .= '</div>';
	endif;
$buildProduct .= '</div></div><!-- .entry-content -->';		
		
echo $buildProduct;
?>