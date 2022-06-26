<?php /* Template part for displaying products */

/*
$comfort = esc_attr(get_field( "comfort" ));
$efficiency = esc_attr(get_field( "efficiency" ));
*/
$brochure = esc_url(get_field( "brochure" ));
$allBrands = get_terms( array ( 'taxonomy' => 'product-brand', 'hide_empty' => true,) );
$allTypes = get_terms( array ( 'taxonomy' => 'product-type', 'hide_empty' => true,) );
$allClasses = get_terms( array ( 'taxonomy' => 'product-class', 'hide_empty' => true, 'order' => 'DESC',) );
$thisBrand = get_the_terms( $post->ID , 'product-brand' ); 
$thisType = get_the_terms( $post->ID , 'product-type' ); 
$thisClass = get_the_terms( $post->ID , 'product-class' ); 

$buildProduct = '<div class="entry-content">';

	if ( has_post_thumbnail() ) : $buildProduct .= get_the_post_thumbnail( $post->ID, 'thumbnail', array('class' => 'alignright '.get_post_type().'-img size-third-s')); endif;	
	
	$buildProduct .= '<div class="breadcrumbs">';	
	$buildProduct .= '» <span typeof="v:Breadcrumb"><a rel="v:url" property="v:title" href="/product-overview/">Products</a></span> » ';	
	$buildProduct .= '<span typeof="v:Breadcrumb"><a rel="v:url" property="v:title" href="/product-brand/'.$thisBrand[0]->slug.'/">'.$thisBrand[0]->name.'</a></span> » ';
	$buildProduct .= '<span typeof="v:Breadcrumb"><a rel="v:url" property="v:title" href="/product-type/'.$thisType[0]->slug.'/">'.$thisType[0]->name.'</a></span> » ';
	$buildProduct .= '<span typeof="v:Breadcrumb"><a rel="v:url" property="v:title" href="/product-class/'.$thisClass[0]->slug.'/">'.$thisClass[0]->name.' Quality</a></span>';
	
	if ( $brochure != "" ) :
		$is_link_valid = get_headers($brochure, 1);
		if ( strpos( $is_link_valid[0], "404" ) === false ) : $buildProduct .= '<br>» <a  href="'.$brochure.'" target="_blank">View Product Brochure</a>'; endif;
	endif;
		
	$buildProduct .= '</div>';

	$buildProduct .= get_the_content( sprintf ( wp_kses( __( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'battleplan' ), array( 'span' => array( 'class' => array(), ), ) ), wp_kses_post( get_the_title() ) ) ); 



		/*
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
		*/
		


$buildProduct .= '<div class="product-links">';

	if ( $thisBrand ) :
		$buildProduct .= '<div class="product-link-cats"><div class="product-link-label">Find Products by Brand:</div><div class="product-link-buttons">';
			foreach( $thisBrand as $brand ) $buildProduct .= '<a href="'.get_term_link( $brand->slug, 'product-brand').'" rel="tag" class="button">'.$brand->name.'</a>';
			foreach( $allBrands as $remaining ) if ( !in_array($remaining, $thisBrand) ) $buildProduct .= '<a href="'.get_term_link( $remaining->slug, 'product-brand').'" rel="tag" class="button">'.$remaining->name.'</a>';
		$buildProduct .= '</div></div>';
	endif;
	
	if ( $thisType ) :
		$buildProduct .= '<div class="product-link-cats"><div class="product-link-label">Find Products by Type:</div><div class="product-link-buttons">';
			foreach( $thisType as $type ) $buildProduct .= '<a href="'.get_term_link( $type->slug, 'product-type').'" rel="tag" class="button">'.$type->name.'</a>';
			foreach( $allTypes as $remaining ) if ( !in_array($remaining, $thisType) ) $buildProduct .= '<a href="'.get_term_link( $remaining->slug, 'product-type').'" rel="tag" class="button">'.$remaining->name.'</a>';
		$buildProduct .= '</div></div>';
	endif;
	
	if ( $thisClass ) :
		$buildProduct .= '<div class="product-link-cats"><div class="product-link-label">Find Products by Quality:</div><div class="product-link-buttons">';
			foreach( $allClasses as $class ) $buildProduct .= '<a href="'.get_term_link( $class->slug, 'product-class').'" rel="tag" class="button">'.$class->name.'</a>';
		$buildProduct .= '</div></div>';
	endif;
	
	$buildProduct .= '</div></div><!-- .entry-content -->';		
		
echo $buildProduct;
?>