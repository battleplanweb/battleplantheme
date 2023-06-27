<?php /* The template for displaying all single posts */

get_header(); ?>
 
<main id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	

		<?php while ( have_posts() ) : the_post();

			// Galleries
				if ( get_post_type() == "galleries" ) :
					$singleHeadline = esc_html(get_the_title());
					$singleIntro = "<p>Click an image to view a larger version.";		
					if ( wp_count_posts( 'galleries' )->publish > 1 ) $singleIntro .= " Click <a href='/galleries/'>HERE</a> to return to albums.";		
					$singleIntro .= "</p>";	
					$orderby = "rand";
					$order = "asc";
					$columns = "5";
					global $singleContent;
					ob_start();
					the_content();
					$singleContent = ob_get_clean();
					$breadcrumbs = "false";
					$date = "false";						
					$author = "false";						
					$comments = "false";
					$social = "false";
					$tags = "false";
					$navigation = "false";
					$relatedPosts = "0";	
		
			// Products
				elseif ( get_post_type() == "products" ) :	
					$singleHeadline = esc_html(get_the_title());
					$singleIntro = "";
					$breadcrumbs = "true";
					$date = "false";
					$author = "false";
					$comments = "false";
					$social = "false";
					$tags = "false"; // list / button
					$navigation = "false";	
					$relatedPosts = "0";
		
			// Events
				elseif ( get_post_type() == "events" ) :	
					$singleHeadline = esc_html(get_the_title());
					$singleIntro = "";
					$breadcrumbs = "false";
					$author = "false";
					$comments = "false";
					$social = "false";
					$tags = "false"; // list / button
					$navigation = "true";
					$relatedPosts = "0";	

			// Default Single
				else:		
					$singleHeadline = esc_html(get_the_title());
					$singleIntro = "";
					$headerImage = "false"; // display feature image as header?
					$headerImgPos = "below"; // header image displayed above/below title & meta
					$breadcrumbs = "true";
					$date = "true";
					$author = "true";
					$comments = "true";
					$social = "false";
					$tags = "list"; // list / button
					$navigation = "true";
					$relatedPosts = "0"; // 0 or number you want to display
					$facebookBtn = "false"; // display Facebook like/share button
					$facebookBtnPos = "both"; // above article, below article, both
					$facebookBtnCode = '<div class="follow_us_on_fb"><div class="iframe"><iframe src="https://www.facebook.com/plugins/like.php?href='.$GLOBALS['customer_info']['facebook'].'&width=85&layout=box_count&action=like&size=large&share=false&height=60&appId=630963613764335" width="85" height="60" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe></div><div class="text">Follow us on Facebook for more!</div></div>';
				endif;
		
			if ( function_exists( 'overrideSingle' ) ) { overrideSingle( get_post_type() ); }
		
			if ( get_post_type() == "galleries" && !has_term('shortcode', 'gallery-type') ) :
				$singleContent = do_shortcode('[get-gallery order_by="'.$orderby.'" order="'.$order.'" columns="'.$columns.'"]');
			endif;

			// Setup & Display Post		
			$displayHeader = '<article id="post-'.get_the_ID().'">';		
				$displayHeader .= '<header class="entry-header">';
				
					if ( $headerImage == "true" && $headerImgPos == "above" && has_post_thumbnail() ) $displayHeader .= get_the_post_thumbnail();
		
					if ( get_post_type() == "events" ) $displayHeader .= '<div class="calender-btn-row">[btn link="/calendar/"]Calender View[/btn][btn link="/events/"]List View[/btn]</div>';
		
					if ( $breadcrumbs == "true" ) $displayHeader .= battleplan_breadcrumbs();
		
					$displayHeader .= '<h1 class="page-headline single-headline '.get_post_type().'-headline">'.$singleHeadline.'</h1>';
				
					if ( $date == "true" || $author == "true" || $comments == "true" ) : 
						$displayHeader .= '<div class="single-meta '.get_post_type().'-meta">';		
							if ( $date == "true" ) $displayHeader .= battleplan_meta_date();
							if ( $author == "true" ) $displayHeader .= battleplan_meta_author();
							if ( $comments == "true" ) $displayHeader .= battleplan_meta_comments();
							if ( $social == "true" ) $displayHeader .= '<span class="meta-social">'.do_shortcode('[add-share-buttons facebook="true" twitter="true"]').'</span>';
						$displayHeader .= '</div>';
					endif;
					
					if ( $facebookBtn == "true" && ( $facebookBtnPos == "above" || $facebookBtnPos == "both") ) $displayHeader .= $facebookBtnCode;				
					
					if ( $headerImage == "true" && $headerImgPos == "below" && has_post_thumbnail() ) $displayHeader .= get_the_post_thumbnail();
		
					$displayHeader .= '<div class="single-intro '.get_post_type().'-intro">'.$singleIntro.'</div>'; 
				$displayHeader .= '</header><!-- .entry-header-->';	
		
				$displayFooter = '<footer class="entry-footer">';
									
					if ( $facebookBtn == "true" && ( $facebookBtnPos == "below" || $facebookBtnPos == "both") ) $displayFooter .= $facebookBtnCode;
		
					if ( $tags != "false" ) :	
						$getTerms = '';
						$displayFooter .= '<div class="single-tags '.get_post_type().'-tags">';
		
							$taxonomies = get_post_taxonomies();					
							if ( is_array($taxonomies) ) :
								foreach ( $taxonomies as $tax) :		
									$terms = get_the_terms( get_the_ID(), $tax ); 		 
									//if ( $terms && $tags == "list" ) :
										//if ( $getTerms ) $getTerms; 
									//endif;	
									if ( is_array($terms) ) :
										foreach($terms as $term):
											if ( $term !== "blog" && $term !== "Blog" ) :												
												$btnClass = ($tags == "list") ? "" : " button button-".$term->slug;
												
												$getTerms .= '<a href="'.get_term_link( $term->slug, $tax).'" rel="tag" class="tax-'.$tax.$btnClass.'">'.$term->name.'</a>';
											endif;
										endforeach; 
									endif;
								endforeach;
							endif;
							
							$termClass = ($tags == "list") ? "tag-list" : "tag-buttons";
							$displayTerms = '<span class="'.$termClass.'">'.$getTerms.'</span>';

							$displayFooter .= '<div class="entry-tags"><span class="tag-label">Tags: </span>'.$displayTerms.'</div>';
						$displayFooter .= '</div>';
					endif;
		
					if ( $navigation == "true" ) :
						if ( get_post_type() != "events" ) :
							$prev_post = get_previous_post();
							$next_post = get_next_post(); 
						else:		
							$displayed_post = get_the_ID(); 
							$query = new WP_Query(array( 'post_type' => 'events', 'posts_per_page' => -1, 'meta_key' => 'start_date', 'orderby' => 'meta_value', 'order' => 'ASC', 'tax_query' => array( array( 'taxonomy' => 'event-tags', 'field' => 'slug', 'terms' => 'upcoming', ), ), ));
							$current_post_index = -1;

							while ($query->have_posts()) :
							    $query->the_post();
							    if (get_the_ID() === $displayed_post) :
								   $current_post_index = $query->current_post;
								   break;
							    endif;
							endwhile;

							$query->rewind_posts();
							$prev_post = $next_post = null;

							if ($current_post_index !== -1) :
							    if ($current_post_index > 0) $prev_post = get_post($query->posts[$current_post_index - 1]);
							    if ($current_post_index < $query->post_count - 1) $next_post = get_post($query->posts[$current_post_index + 1]);
							endif;

							wp_reset_postdata();		
						endif;
		
						$displayFooter .= '<nav class="navigation single" role="navigation" aria-label="Posts">';
							$displayFooter .= '<h2 class="screen-reader-text">Posts navigation</h2>';
							$displayFooter .= '<div class="nav-links">';

								if ( $prev_post ) : 
									$prev_title = strip_tags(str_replace('"', '', esc_html($prev_post->post_title))); 			
									$displayFooter .= '<a class="nav-previous prev" href="'.get_permalink( $prev_post->ID ).'" rel="prev"><div class="post-arrow"><i class="fa fas fa-chevron-left" aria-hidden="true"></i></div><div class="post-links"><div class="meta-nav" aria-hidden="true">Previous</div><div class="post-title">'.$prev_title.'</div></div></a>';
								else:
									$displayFooter .= '<div class="nav-previous prev"></div>';
								endif;

								if ( $next_post ) : 
									$next_title = strip_tags(str_replace('"', '', esc_html($next_post->post_title))); 			
									$displayFooter .= '<a class="nav-next next" href="'.get_permalink( $next_post->ID ).'" rel="next"><div class="post-links"><div class="meta-nav" aria-hidden="true">Next</div><div class="post-title">'.$next_title.'</div></div><div class="post-arrow"><i class="fa fas fa-chevron-right" aria-hidden="true"></i></div></a>';
								else:
									$displayFooter .= '<div class="nav-next next"></div>';
								endif;

							$displayFooter .= '</div>';
						$displayFooter .= '</nav>';		
					endif;
		
					if ( intval($relatedPosts) > 0  ) :		

						$currentID = get_the_ID();
						$current_tag_ids = array_map(function ($tag) { return $tag->term_id; }, wp_get_post_tags($currentID));		
						$related_posts = array();
						$post_num = 1;

						$all_posts = new WP_Query(array('post_type' => get_post_type(), 'posts_per_page' => -1));
						if ($all_posts->have_posts()) :
							while ($all_posts->have_posts()) :
								$all_posts->the_post();

							   	if (get_the_ID() == $currentID) continue;  
 
							   	$post_tags =  wp_get_post_tags(get_the_ID());		
		
							   	if ($post_tags) :
				        				$post_tag_ids = array_map(function ($tag) { return $tag->term_id; }, $post_tags);
								  	$tag_similarity = count(array_intersect($current_tag_ids, $post_tag_ids));
								  	$related_posts[get_the_ID()] = $tag_similarity;
							   	endif;
						    	endwhile;
						endif;

						if (!empty($related_posts)) :		
							arsort($related_posts);	
		
							if ( intval($relatedPosts) == 1 ) : $relatedGrid = "1";
							elseif ( intval($relatedPosts) == 3 ) : $relatedGrid = "1-1-1";
							else : $relatedGrid = "1-1";
							endif;
	
							$displayFooter .= '<nav class="related single" role="navigation" aria-label="Posts">';
							$displayFooter .= '<h2 class="screen-reader-text">Related posts</h2>';
							$displayFooter .= '<div class="related-links">';	
    							$displayFooter .= '<h2 class="related">Related Posts:</h2>';
    							$displayRelated = '[section width="inline"][layout grid="'.$relatedGrid.'"]';
							
    							foreach ($related_posts as $post_id => $tag_similarity) :
								if ( $post_num > intval($relatedPosts) ) break;
								$post_num++;		
								$displayRelated .= '[col][build-archive id="'.$post_id.'" type="'.get_post_type().'" size="thumbnail" show_thumb="true" show_btn="true" btn_text="Read More" btn_pos="below" title_pos="inside" show_date="false" show_author="false" show_excerpt="false" pic_size="100"][/col]';		
    							endforeach;
							
        						$displayRelated .= '[/layout][/section]';	
							$displayFooter .= do_shortcode($displayRelated);
							$displayFooter .= '</div>';
							$displayFooter .= '</nav>';						
						endif;

						wp_reset_postdata();
	
					endif;

				$displayFooter .= '</footer><!-- .entry-footer-->';		
			$displayFooter .= '</article><!-- #post-'.get_the_ID().' -->';
		
			if ( $singleHeadline != '' ) echo do_shortcode($displayHeader);
			get_template_part( 'template-parts/content', get_post_type() );		
			echo do_shortcode($displayFooter);	
		
			if ( ( comments_open() || get_comments_number() ) && $comments == "true" ) comments_template();
		
		endwhile; ?>
		
		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</main><!-- #primary .site-main -->

<?php
get_footer();