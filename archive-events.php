<?php /* The template for displaying archive pages for "events" post type */

wp_enqueue_style( 'battleplan-style-posts', get_template_directory_uri()."/style-posts.css", [], _BP_VERSION, 'print' );
get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>

	<div class="site-main-inner">

		<?php bp_before_the_content(); ?>

		<?php if ( have_posts() ) :
			$archiveHeadline = 'Upcoming Events';
			$archiveIntro = '<div class="calender-btn-row">[btn class="show-expired-btn"]Show Past Events[/btn][btn link="/calendar/"]Calender View[/btn]</div>';
			$grid = "3e";
			$valign = "stretch";
			$break = "2";
			$showThumb = "true";
			$picSize = "100";
			$textSize = "100";
			$showBtn = "true";
			$btnText = "Learn More";
			$titlePos = "inside";
			$showExcerpt = "false";
			$showContent = "false";
			$showDate = "true";
			$showAuthor = "false";
			$accordion = "false";
			$addClass = "";

			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }

			$currentMonth = date('F Y', strtotime('-1 month'));

		// Build Archive
			while ( have_posts() ) : the_post();
				$addClass = has_term('expired', 'event-tags', get_the_ID()) ? 'event-expired' : 'event-upcoming';
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

				$classes = 'col-archive col-'.get_post_type().' col-'.get_the_ID().' '.$addClass;

				$buildArchive .= do_shortcode('[col class="'.$classes.'"][build-archive type="'.get_post_type().'" show_thumb="'.$showThumb.'" show_btn="'.$showBtn.'" btn_text="'.$btnText.'" btn_pos="'.$btnPos.'" title_pos="'.$titlePos.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" accordion="'.$accordion.'" add_info="'.$buildMeta.'" no_pic="'.$noPic.'"][group]'.$buildMeta.'[/group][/col]');
			endwhile;

		// Display Archive
			$displayArchive = '<header class="archive-header">';
				$displayArchive .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$archiveHeadline.'</h1>';
				$displayArchive .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>';
			$displayArchive .= '</header><!-- .archive-header-->';

			$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-'.get_post_type().'"][layout grid="'.$grid.'" valign="'.$valign.'" break="'.$break.'"]'.$buildArchive.'[/layout][/section]');

			$displayArchive .= '<footer class="archive-footer">';
				$displayArchive .= get_the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => _x( '<span class="icon chevron-left" aria-hidden="true"></span>', 'Previous set of posts' ), 'next_text' => _x( '<span class="icon chevron-right" aria-hidden="true"></span>', 'Next set of posts' ), ));
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