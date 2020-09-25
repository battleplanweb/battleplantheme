<?php
/* The template for displaying all single posts */

get_header();
?>
 
	<main id="primary" class="site-main">

		<?php while ( have_posts() ) : the_post();

			// Galleries
				if ( get_post_type() == "galleries" ) :
					$singleHeadline = esc_html(get_the_title());
					$singleIntro = "<p>Click an image to view a larger version.";		
					if ( wp_count_posts( 'galleries' )->publish > 1 ) $singleIntro .= " Click <a href='/galleries/'>HERE</a> to return to albums.";		
					$singleIntro .= "</p>";	
					$orderby = "rand";
					global $singleContent;
					ob_start();
					the_content();
					$singleContent = ob_get_clean();
					if (strpos($singleContent, 'section-gallery') === false) $singleContent = do_shortcode('[get-gallery order_by="'.$orderby.'"]'); 
					$breadcrumbs = "false";
					$date = "false";						
					$author = "false";						
					$comments = "false";
					$tags = "false";
					$navigation = "false";	
		
			// Products
				elseif ( get_post_type() == "products" ) :	
					$singleHeadline = esc_html(get_the_title());
					$singleIntro = "";
					$breadcrumbs = "true";
					$date = "false";
					$author = "false";
					$comments = "false";
					$tags = "false"; // list / button
					$navigation = "false";	

			// Default Single
				else:		
					$singleHeadline = esc_html(get_the_title());
					$singleIntro = "";
					$breadcrumbs = "true";
					$date = "true";
					$author = "true";
					$comments = "true";
					$tags = "list"; // list / button
					$navigation = "true";	
				endif;
		
			if ( function_exists( 'overrideSingle' ) ) { overrideSingle( get_post_type() ); }

			// Setup & Display Post		
			$displayHeader = '<article id="post-'.get_the_ID().'">';		
				$displayHeader .= '<header class="entry-header">';
		
					if ( $breadcrumbs == "true" ) $displayHeader .= battleplan_breadcrumbs();
		
					$displayHeader .= '<h1 class="page-headline single-headline '.get_post_type().'-headline">'.$singleHeadline.'</h1>';
				
					if ( $date == "true" || $author == "true" || $comments == "true" ) : 
						$displayHeader .= '<div class="single-meta '.get_post_type().'-meta">';		
							if ( $date == "true" ) $displayHeader .= battleplan_meta_date();
							if ( $author == "true" ) $displayHeader .= battleplan_meta_author();
							if ( $comments == "true" ) $displayHeader .= battleplan_meta_comments();
						$displayHeader .= '</div>';
					endif;
		
					$displayHeader .= '<div class="single-intro '.get_post_type().'-intro">'.$singleIntro.'</div>'; 
				$displayHeader .= '</header><!-- .entry-header-->';	
		
				$displayFooter = '<footer class="entry-footer">';
		
					if ( $tags != "false" ) :				
						$displayFooter .= '<div class="single-tags '.get_post_type().'-tags">';
		
							$taxonomies = get_post_taxonomies();

							if ( $tags == "list" ) : $btnClass = ""; $termClass = "tag-list"; else: $btnClass = "button"; $termClass = "tag-buttons"; endif;
		
							foreach ( $taxonomies as $tax) :		
								$terms = get_the_terms( get_the_ID(), $tax ); 		 
								if ( $terms && $tags == "list" ) : if ( $getTerms ) : $getTerms .= ", "; endif; endif;		
								foreach($terms as $term):
									$getTerms .= '<a href="'.get_term_link( $term->slug, $tax).'" rel="tag" class="'.$btnClass.'">'.$term->name.'</a>';
								endforeach; 
							endforeach;
							$displayTerms = '<span class="'.$termClass.'">'.$getTerms.'</span>';

							$displayFooter .= '<div class="entry-tags"><span class="tag-label">Tags: </span>'.$displayTerms.'</div>';
						$displayFooter .= '</div>';
					endif;
		
					if ( $navigation == "true" ) :
						$displayFooter .= '<nav class="navigation single" role="navigation" aria-label="Posts">';
							$displayFooter .= '<h2 class="screen-reader-text">Posts navigation</h2>';
							$displayFooter .= '<div class="nav-links">';

								$prev_post = get_previous_post(); 		
								if ( $prev_post ) : 
									$prev_title = strip_tags(str_replace('"', '', esc_html($prev_post->post_title))); 			
									$displayFooter .= '<a class="nav-previous prev" href="'.get_permalink( $prev_post->ID ).'" rel="prev"><div class="post-arrow"><i class="fa fa-angle-left" aria-hidden="true"></i></div><div class="post-links"><div class="meta-nav" aria-hidden="true">Previous</div><div class="post-title">'.$prev_title.'</div></div></a>';
								else:
									$displayFooter .= '<div class="nav-previous prev"></div>';
								endif;

								$next_post = get_next_post(); 		
								if ( $next_post ) : 
									$next_title = strip_tags(str_replace('"', '', esc_html($next_post->post_title))); 			
									$displayFooter .= '<a class="nav-next next" href="'.get_permalink( $next_post->ID ).'" rel="next"><div class="post-links"><div class="meta-nav" aria-hidden="true">Next</div><div class="post-title">'.$next_title.'</div></div><div class="post-arrow"><i class="fa fa-angle-right" aria-hidden="true"></i></div></a>';
								else:
									$displayFooter .= '<div class="nav-next next"></div>';
								endif;

							$displayFooter .= '</div>';
						$displayFooter .= '</nav>';
					endif;

				$displayFooter .= '</footer><!-- .entry-footer-->';		
			$displayFooter .= '</article><!-- #post-'.get_the_ID().' -->';
		
			echo $displayHeader;
			get_template_part( 'template-parts/content', get_post_type() );		
			echo $displayFooter;	
		
			if ( comments_open() || get_comments_number() ) comments_template();
		
		endwhile; ?>

	</main><!-- #main -->

<?php
get_sidebar();
get_footer();
