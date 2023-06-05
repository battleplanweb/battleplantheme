<?php /* The template for displaying archive pages for "events" post type */

get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<?php if ( have_posts() ) : 		
			$archiveHeadline = 'Upcoming Events';
			$archiveIntro = '<div class="show-expired-checkbox"><input type="checkbox" id="show-expired"> Show Past Events</div>';
			$grid = "3e";		
			$valign = "start";
			$break = "2";
			$showThumb = "true";
			$picSize = "100";
			$textSize = "100";
			$showBtn = "true"; 
			$btnText = "Learn More";
			$titlePos = "inside";
			$showExcerpt = "false";
			$showContent = "false";				
			$showDate = "false";
			$showAuthor = "false";
			$accordion = "false";
			$addClass = "";
			 
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }
		
			$currentMonth = date('F Y', strtotime('-1 month')); 

		// Build Archive
			while ( have_posts() ) : the_post(); 
				$addClass = has_term('expired', 'event-tags', get_the_ID()) ? 'expired' : 'upcoming';
				$venue = esc_attr(get_field( "venue" ));
				$location = esc_attr(get_field( "location" ));
				$venueLink = esc_url(get_field( "venue_link" ));
				$startTime = esc_attr(get_field( "start_time" ));
				$endTime = esc_attr(get_field( "end_time" ));
				$startDate = esc_attr(get_field( "start_date" ));
				$endDate = esc_attr(get_field( "end_date" ));
		
				if ( date('F Y', strtotime($startDate)) != $currentMonth ) : 
					$currentMonth = date('F Y', strtotime($startDate));
					$buildArchive .= '<h2 class="month-divider span-all">'.$currentMonth.'</h2>'; 
				endif;
		
				$buildMeta = include('wp-content/themes/battleplantheme/elements/element-events-meta.php');	
	
				$buildArchive .= do_shortcode('[col class="'.$classes.' '.$addClass.'"][build-archive type="'.get_post_type().'" show_thumb="'.$showThumb.'" show_btn="'.$showBtn.'" btn_text="'.$btnText.'" btn_pos="'.$btnPos.'" title_pos="'.$titlePos.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" accordion="'.$accordion.'" add_info="'.$buildMeta.'" no_pic="'.$noPic.'"][group]'.$buildMeta.'[/group][/col]');		
			endwhile; 

		// Display Archive
			$displayArchive = '<header class="archive-header">';
				$displayArchive .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$archiveHeadline.'</h1>';
				$displayArchive .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>'; 
			$displayArchive .= '</header><!-- .archive-header-->';
		
			$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-'.get_post_type().'"][layout grid="'.$grid.'" valign="'.$valign.'" break="'.$break.'"]'.$buildArchive.'[/layout][/section]');
		
			$displayArchive .= '<footer class="archive-footer">';
				$displayArchive .= get_the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => _x( '<i class="fa fa-chevron-left"></i>', 'Previous set of posts' ), 'next_text' => _x( '<i class="fa fa-chevron-right"></i>', 'Next set of posts' ), ));
			$displayArchive .= '</footer><!-- .archive-footer-->';
		
			echo $displayArchive;	

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif;
		?>

		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</main><!-- #primary .site-main -->

<?php
get_footer();
