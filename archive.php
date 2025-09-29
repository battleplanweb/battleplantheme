<?php /* The template for displaying archive pages */

wp_enqueue_style( 'battleplan-style-posts', get_template_directory_uri()."/style-posts.css", array('parent-style'), _BP_VERSION ); 
get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<?php if ( have_posts() ) : 

			// Default Archives
			$archiveHeadline = wp_kses_post(get_the_archive_title());
			$archiveIntro = wp_kses_post(get_the_archive_description());
			$grid = "1-1-1";
			$valign = "stretch";
			$tags = "false"; // drop / list / button / false
			$tagTax = "tags"; // change to slug of custom taxonomy if not using tags
			$showThumb = "true";
			$size = "thumbnail";
			$picSize = "100";
			$textSize = "100";
			$showBtn = "true";
			$btnText = "Read More";
			$btnPos = "outside";
			$titlePos = "outside";
			$showExcerpt = "true";
			$showContent = "false";				
			$showDate = "true";
			$showAuthor = "false"; // true (no link), profile (link to profile)
			$accordion = "false";
			$countView = "false";
			$link = "post"; //post, cf-custom-field, specific link, false
			$addInfo = "";				
			$addClass = "";
		
		// Galleries
			if ( get_post_type() === "galleries" ) :
				$archiveHeadline = "Photo Galleries";
				$archiveIntro = "<p>Click a photo below to open up the full album.</p>";
				$valign = "start";
				$showBtn = "false";
				$btnText = "";
				$titlePos = "inside";
				$showExcerpt = "false";				
				$showDate = "false";				

		// Testimonials
			elseif ( get_post_type() === "testimonials" ) :
				$archiveHeadline = "Testimonials";
				$facebookLink = do_shortcode('[get-biz info="facebook"]')."reviews/";
				$facebookIcon = "Facebook-Like-Us-1";
				$grid = "1";
				$valign = "start";
				$picSize = "1/4";
				$textSize = "3/4";
				$showBtn = "false";
				$link = "false";
		
		// Products
			elseif ( get_post_type() === "products" ) :
				wp_enqueue_style( 'battleplan-style-products-hvac', get_template_directory_uri()."/style-products-hvac.css", array('battleplan-style-forms'), _BP_VERSION );

				$archiveHeadline = "Products";
				$grid = "1";
				$valign = "start";
				$picSize = "1/3";
				$textSize = "2/3";
				$btnText = "Learn More";
				$btnPos = "inside";
				$showDate = "false";				
				
			endif;
		
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }
		
			if ( get_post_type() == "testimonials" ) :		
				$buildIntro = "";		
				if ( $facebookLink != "reviews/" ) $buildIntro .= '<a class="noFX align-right size-quarter-s" style="margin-top:0;" href="/review/"><img alt="Like Us on Facebook" src="/wp-content/themes/battleplantheme/common/logos/'.$facebookIcon.'.webp" class="noFX" width="190" height="190" /></a>';
				$buildIntro .= '[txt]<p>Our customers really like us! But don’t take our word for it. Here are some actual reviews posted by our customers on the web.</p>';				
				if ( $facebookLink != "reviews/" ) $buildIntro .= '<p>If YOU are a satisfied customer, we invite you to click the "thumbs up" icon to review your experience with our business.  Thank you!</p>';		
				$buildIntro .= '[/txt]';				
				$archiveIntro = do_shortcode($buildIntro); 
			endif;		
		
			if ( ( is_tax() || is_tag() || is_author() || is_category() ) && !is_category('blog') && get_post_type() != "testimonials" ) : 	
				if ( is_tax() ) : $term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) ); $subHeadline = $term->name; 
				else : $subHeadline = wp_kses_post(get_the_archive_title()); endif;	
				$archiveHeadline .= "</h1><h3 class='page-subheadline archive-subheadline ".get_post_type()."-subheadline'>".$subHeadline."</h3>"; 
			endif;

		// Build Archive
			while ( have_posts() ) : the_post(); 
				if ( $addClass != '' ) $addClass = " ".$addClass;		
				$addTags = "";
				$taxonomies = get_object_taxonomies(get_post_type()); 
 				foreach( $taxonomies as $tax ) :
   					$getTerms = get_the_terms( $post->ID, $tax );
					foreach($getTerms as $getTerm) : $addTags .= " ".$tax."-".$getTerm->slug; endforeach;
 				endforeach;	

				$classes = 'col-archive col-'.get_post_type().' col-'.get_the_ID().$addTags.$addClass;
		
				$buildArchive .= do_shortcode('[col class="'.$classes.'"][build-archive type="'.get_post_type().'" show_thumb="'.$showThumb.'" size="'.$size.'" show_btn="'.$showBtn.'" btn_text="'.$btnText.'" btn_pos="'.$btnPos.'" title_pos="'.$titlePos.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" accordion="'.$accordion.'" count_view="'.$countView.'" add_info="'.$addInfo.'" link="'.$link.'"][/col]');
			endwhile; 
		
		// Build Tag List
		if ( $tags != "false" ) :
			$taxonomy = (isset($tagTax) && $tagTax && $tagTax !== "tag" && $tagTax !== "tags") ? $tagTax : 'post_tag';
			$getTerms = get_terms(array(
				'taxonomy' => $taxonomy,
				'orderby' => 'count',
				'order' => 'DESC',
			));

			if ( $tags == "drop" ) :
				$tagClass = "hide-1 hide-2 hide-3 hide-4 hide-5";
				$dropClass = "";
			elseif ( $tags == "list" ) :
				$tagClass = "tag-list hide-1 hide-2";
				$dropClass = "hide-3 hide-4 hide-5";
			else:
				$tagClass = "tag-buttons hide-1 hide-2";
				$dropClass = "hide-3 hide-4 hide-5";
			endif;

			$buildTagList = '<span class="'.$tagClass.'">';
			$buildTagDrop = '<select name="tag-dropdown" id="tag-dropdown" class="'.$dropClass.'">';
			$buildTagDrop .= '<option value="">Topics</option>';

			if ( ! is_wp_error($getTerms) && $getTerms ) :
				foreach ( $getTerms as $term ) :
					$link = get_term_link($term->term_id, $taxonomy);
					$btnClass = ($tags == "button") ? " button button-".$term->slug : "";
					$buildTagList .= '<a href="'.$link.'" rel="tag" class="tag-'.$term->slug.$btnClass.'">'.$term->name.' (' . $term->count . ')</a>';
					$buildTagDrop .= '<option value="'.$link.'">'.$term->name . ' (' . $term->count . ')</option>';
				endforeach;
			endif;

			$buildTagList .= '</span>';
			$buildTagDrop .= '</select>';

			$buildTagMenu = '<div class="archive-tags '.get_post_type().'-tags">';
			$buildTagMenu .= $buildTagList . $buildTagDrop;
			$buildTagMenu .= '</div>';
		endif;
		
		// Display Archive
			$displayArchive = '<header class="archive-header">';
				$displayArchive .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$archiveHeadline.'</h1>';
				$displayArchive .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>'; 
			$displayArchive .= '</header><!-- .archive-header-->';
		
			$displayArchive .= $buildTagMenu;
		
			$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-'.get_post_type().'"][layout grid="'.$grid.'" valign="'.$valign.'"]'.$buildArchive.'[/layout][/section]');
		
			$displayArchive .= '<footer class="archive-footer">';
				$displayArchive .= get_the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => _x( '<span class="icon chevron-left" aria-hidden="true">[get-icon type="chevron-left"]</span><span class="sr-only">Previous set of posts</span>', 'Previous set of posts' ), 'next_text' => _x( '<span class="icon chevron-right" aria-hidden="true">[get-icon type="chevron-right"]</span><span class="sr-only">Next set of posts</span>', 'Next set of posts' ), ));
			$displayArchive .= '</footer><!-- .archive-footer-->';
		
			echo do_shortcode($displayArchive);	

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif;
		?>

		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</div><!-- #primary .site-main -->

<?php get_footer();