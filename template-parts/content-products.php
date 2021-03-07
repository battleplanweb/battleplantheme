<?php
/* Template part for displaying products */

$comfort = esc_attr(get_field( "comfort" ));
$efficiency = esc_attr(get_field( "efficiency" ));
?>

	<div class="entry-content">
						
		<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'thumbnail', array('class' => 'alignright '.get_post_type().'-img size-third-s'));  endif;	
		
		 the_content( sprintf ( wp_kses( __( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'battleplan' ), array( 'span' => array( 'class' => array(), ), ) ), wp_kses_post( get_the_title() ) ) ); 
		
		echo '<div class="product-meta">';

			if ( $comfort != "na" ) : 
				echo '<div class="product-rating">';
					echo '<span class="rating-label">Comfort: </span>';
					if ( $comfort == 1 ) : echo '<span class="comfort-full"></span><span class="comfort-empty"></span><span class="comfort-empty"></span><span class="comfort-empty"></span><span class="comfort-empty"></span>';
					elseif ( $comfort == 2 ) : echo '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-empty"></span><span class="comfort-empty"></span><span class="comfort-empty"></span>';
					elseif ( $comfort == 3 ) : echo '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-empty"></span><span class="comfort-empty"></span>';
					elseif ( $comfort == 4 ) : echo '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-empty"></span>';
					elseif ( $comfort == 5 ) : echo '<span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span><span class="comfort-full"></span>';
					endif; 
				echo '</div>';
			endif; 

			if ( $efficiency != "na" ) : 
				echo '<div class="product-rating">';
					echo '<span class="rating-label">Efficiency: </span>';
					if ( $efficiency == 1 ) : echo '<span class="efficiency-full"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span>';
					elseif ( $efficiency == 2 ) : echo '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span>';
					elseif ( $efficiency == 3 ) : echo '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-empty"></span><span class="efficiency-empty"></span>';
					elseif ( $efficiency == 4 ) : echo '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-empty"></span>';
					elseif ( $efficiency == 5 ) : echo '<span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span><span class="efficiency-full"></span>';
					endif; 
				echo '</div>';
			endif; 

		echo '</div>';

		echo '<div class="product-links">';
			$terms = get_the_terms( $post->ID , 'product-brand' ); 
			if ( $terms ) :
				echo '<div class="descriptionText">View more products from:&nbsp;&nbsp;';
					foreach( $terms as $term ) : 
						echo '<a href="'.get_term_link( $term->slug, 'product-brand').'" rel="tag" class="button">'.$term->name.'</a>';
					endforeach; 	
				echo '</div>';
			endif;
			$terms = get_the_terms( $post->ID , 'product-type' ); 
			if ( $terms ) :
				echo '<div class="descriptionText">View complete list of:&nbsp;&nbsp;';
					foreach( $terms as $term ) :
						echo '<a href="'.get_term_link( $term->slug, 'product-type').'" rel="tag" class="button">'.$term->name.'</a>';
					endforeach; 	
				echo '</div>';
			endif;
			$terms = get_the_terms( $post->ID , 'product-class' ); 
			if ( $terms ) :
				echo '<div class="descriptionText">View all products in:&nbsp;&nbsp;';
					foreach( $terms as $term ) : 
						echo '<a href="'.get_term_link( $term->slug, 'product-class').'" rel="tag" class="button">'.$term->name.'</a>';
					endforeach; 	
				echo '</div>';
			endif;
		echo '</div>';?>
		
	</div><!-- .entry-content -->