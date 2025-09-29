<?php /* The template for displaying archive pages for "dogs" post type */

wp_enqueue_style( 'battleplan-style-posts', get_template_directory_uri()."/style-posts.css", array('parent-style'), _BP_VERSION ); 
get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<?php if ( have_posts() ) : 		
			$archiveHeadline = "Our Dogs";		
			$grid = "3e";		
			$valign = "start";
			$showThumb = "true";
			$picSize = "100";
			$textSize = "100";
			$showBtn = "false"; 
			$titlePos = "inside";
			$showExcerpt = "false";
			$showContent = "false";				
			$showDate = "false";
			$showAuthor = "false";
			$accordion = "false";
			$addClass = "";
			ob_start(); ?>
				<div class="row-of-buttons">
					<div class="block block-button"><button class="all-btn" tabindex="0">[get-icon type="sex-both"] All</button></div>
					<div class="block block-button"><button class="female-btn" tabindex="0">[get-icon type="sex-female"] Females</button></div>
					<div class="block block-button"><button class="male-btn" tabindex="0">[get-icon type="sex-male"] Males</button></div>
					<div class="block block-button"><button class="legacy-btn" tabindex="0">[get-icon type="award"] Legacy Sires</button></div>
				</div>
				
				<div class="dog-description"> 
					<p class="p-intro intro-all">The following list includes our own dogs, sires we use and Legacy sires.</p>
					<p class="p-intro intro-female">The following list includes our own dogs.</p>
					<p class="p-intro intro-male">The following list includes our own dogs and sires we use.</p>
					<p class="p-intro intro-legacy">The following list includes Legacy sires.<br>We have semen from these dogs in our own collection.<br><a href="/legacy-info/">Looking to purchase a breeding?</a><br><a href="/legacy-info/">Want to list your breeding on our site?</a></p>
				</div>
			<?php 
			$archiveIntro = ob_get_clean();
			$noPic = "774";
			
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }

		// Build Archive
			while ( have_posts() ) : the_post(); 
				if ( $addClass != '' ) $addClass = " ".$addClass;
		
		 		if ( strtolower(esc_attr(get_field("list_in_archive"))) === "no" ) {
						continue;
				}					
		
				$addTags = "";
				$taxonomies = get_object_taxonomies(get_post_type()); 
 				foreach( $taxonomies as $tax ) :
   					$getTerms = get_the_terms( $post->ID, $tax );
					foreach($getTerms as $getTerm) : $addTags .= " ".$tax."-".$getTerm->slug; endforeach;
 				endforeach;	

				$classes = 'col-archive col-'.get_post_type().' col-'.get_the_ID().$addTags.$addClass;
				$classes .= " dogs-".strtolower(esc_attr(get_field( "sex" )));
				$sexBox = esc_attr(get_field( "sex" )) !== 'legacy' ? 'sex-'.strtolower(esc_attr(get_field( "sex" ))) : 'award';
				$sexBox = '<div class="sex-box">[get-icon type="'.$sexBox.'"]</div>';
		
				$buildArchive .= do_shortcode('[col class="'.$classes.'"]'.$sexBox.'[build-archive type="'.get_post_type().'" show_thumb="'.$showThumb.'" show_btn="'.$showBtn.'" btn_text="'.$btnText.'" btn_pos="'.$btnPos.'" title_pos="'.$titlePos.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" accordion="'.$accordion.'" add_info="" no_pic="'.$noPic.'"][/col]');
		
			endwhile; 

		// Display Archive
			$displayArchive = '<header class="archive-header">';
				$displayArchive .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$archiveHeadline.'</h1>';
				$displayArchive .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>'; 
			$displayArchive .= '</header><!-- .archive-header-->';
		
			$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-'.get_post_type().'"][layout grid="'.$grid.'" valign="'.$valign.'"]'.$buildArchive.'[/layout][/section]');
		
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