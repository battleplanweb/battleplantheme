<?php
/* The template for displaying archive pages for "dogs" post type */

get_header(); 
?>

	<main id="primary" class="site-main">

		<?php if ( have_posts() ) : 		
			$archiveHeadline = "Our Dogs";		
			$grid = "4e";		
			$valign = "stretch";
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
					<div class="block block-button"><button class="males-btn" tabindex="0"><i class='fa fas fa-mars'></i> Males</button></div>
					<div class="block block-button"><button class="females-btn" tabindex="0"><i class='fa fas fa-venus'></i> Females</button></div>
					<div class="block block-button"><button class="all-btn" tabindex="0"><i class='fa fas fa-venus-mars'></i> All</button></div>
				</div>
			<?php 
			$archiveIntro = ob_get_clean();
			$noPic = "774";
			
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }

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
				$classes .= " dogs-".strtolower(esc_attr(get_field( "sex" )));
		
				$buildArchive .= do_shortcode('[col class="'.$classes.'"][build-archive type="'.get_post_type().'" show_thumb="'.$showThumb.'" show_btn="'.$showBtn.'" btn_text="'.$btnText.'" btn_pos="'.$btnPos.'" title_pos="'.$titlePos.'" show_excerpt="'.$showExcerpt.'" show_content="'.$showContent.'" show_date="'.$showDate.'" show_author="'.$showAuthor.'" pic_size="'.$picSize.'" text_size="'.$textSize.'" accordion="'.$accordion.'" no_pic="'.$noPic.'"][/col]');
		
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
get_footer();
