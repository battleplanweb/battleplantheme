<?php
/* The template for displaying archive pages */

get_header(); 
?>

	<main id="primary" class="site-main">

		<?php if ( have_posts() ) : 
			
			$archiveHeadline = esc_html(get_the_archive_title());
			$archiveIntro = esc_html(get_the_archive_description());				

		// Galleries
			if ( get_post_type() == "galleries" ) :
				$archiveHeadline = "Photo Galleries";
				$archiveIntro = "<p>Click a photo below to open up the full album.</p>";
				$grid = "1-1-1";
				$valign = "start";
				$showThumb = "true";
				$picSize = "100";
				$textSize = "100";
				$showBtn = "false";
				$btnText = "";
				$btnPos = "outside";
				$titlePos = "inside";
				$showExcerpt = "false";				
				$showContent = "false";				
				$showDate = "false";				
				$showAuthor = "false";
				$addClass = "";

		// Testimonials
			elseif ( get_post_type() == "testimonials" ) :
				$archiveHeadline = "Testimonials";
				$facebookLink = do_shortcode('[get-biz info="facebook"]')."reviews/";
				$facebookIcon = "Facebook-Like-Us-1";
				$grid = "1";
				$valign = "start";
				$showThumb = "true";
				$picSize = "1/4";
				$textSize = "3/4";
				$showBtn = "false";
				$addClass = "";
		
		// Products
			elseif ( get_post_type() == "products" ) :
				$archiveHeadline = "Products";
				$grid = "1";
				$valign = "start";
				$showThumb = "true";
				$picSize = "1/3";
				$textSize = "2/3";
				$showBtn = "true";
				$btnText = "Learn More";
				$btnPos = "inside";
				$titlePos = "outside";
				$showExcerpt = "true";
				$showContent = "false";				
				$showDate = "false";				
				$showAuthor = "false";
				$addClass = "";
		
		// Default Archives
			else: 
				$grid = "1-1-1";
				$valign = "stretch";
				$showThumb = "true";
				$picSize = "100";
				$textSize = "100";
				$showBtn = "true";
				$btnText = "Read More";
				$btnPos = "outside";
				$titlePos = "outside";
				$showExcerpt = "true";
				$showContent = "false";				
				$showDate = "true";
				$showAuthor = "false";
				$addClass = "";
			endif;
		
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }
		
			if ( get_post_type() == "testimonials" ) :
				$archiveIntro = do_shortcode('<a href="#" onclick="trackClicks(\'contact\', \'Offsite Link\', \'Facebook\', \''.$facebookLink.'\'); return false;"><img alt="Like Us on Facebook" src="/wp-content/uploads/'.$facebookIcon.'-240x234.png" class="noFX alignright" style="margin-top:0; max-height:150px"/></a>[txt]<p>Our customers really like us! But don’t take our word for it. Here are some actual reviews posted by our customers on the web.</p><p>If YOU are a satisfied customer, we invite you to click the "thumbs up" icon to review your experience with our business.  Thank you!</p>[/txt]');	
			endif;		

			$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			if ( $term->name ) $archiveHeadline .= ": ".$term->name;

		// Build Archive
			while ( have_posts() ) : the_post(); 
				if ( $addClass != '' ) $addClass = " ".$addClass;
				$classes = 'col-archive col-'.get_post_type().' col-'.get_the_ID().$addClass;
		
				$buildArchive .= do_shortcode('[col class="'.$classes.'"][build-archive type="'.get_post_type().'" show_thumb="'.$showThumb.'" show_btn="'.$showBtn.'" btn_text="'.$btnText.'" btn_pos="'.$btnPos.'" title_pos="'.$titlePos.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" pic_size="'.$picSize.'" text_size="'.$textSize.'"][/col]');

			endwhile; 

		// Display Archive
			$displayArchive = '<header class="archive-header">';
				$displayArchive .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$archiveHeadline.'</h1>';
				$displayArchive .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>'; 
			$displayArchive .= '</header><!-- .archive-header-->';
		
			$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-'.get_post_type().'"][layout grid="'.$grid.'" valign="'.$valign.'"]'.$buildArchive.'[/layout][/section]');
		
			$displayArchive .= '<footer class="archive-footer">';
				$displayArchive .= get_the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => _x( '<i class="fa fa-chevron-left"></i>', 'Previous set of posts' ), 'next_text' => _x( '<i class="fa fa-chevron-right"></i>', 'Next set of posts' ), ));
			$displayArchive .= '</footer><!-- .archive-footer-->';
		
			echo $displayArchive;	

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif;
		?>

	</main><!-- #primary -->

<?php
get_sidebar();
get_footer();
