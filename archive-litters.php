<?php /* The template for displaying archive pages for "litters" post type */

wp_enqueue_style( 'battleplan-style-posts', get_template_directory_uri()."/style-posts.css", [], _BP_VERSION, 'print' );
get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>

	<div class="site-main-inner">

		<?php bp_before_the_content(); ?>

		<?php if ( have_posts() ) :
			$archiveHeadline = "Our Litters";
			$grid = "1";
			$valign = "stretch";
			$showThumb = "false";
			$picSize = "100";
			$textSize = "100";
			$showBtn = "true";
			$titlePos = "inside";
			$showExcerpt = "false";
			$showContent = "false";
			$showDate = "false";
			$showAuthor = "false";
			$accordion = "false";
			$addClass = "";
			ob_start(); ?>
				<div class="row-of-buttons">
					<div class="block block-button"><button class="available-btn" tabindex="0">Available</button></div>
					<div class="block block-button"><button class="expecting-btn" tabindex="0">Expecting</button></div>
					<div class="block block-button"><button class="all-btn" tabindex="0">All</button></div>
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
				$classes .= " litter-".strtolower(get_field( "litter_status" ));

				$name = esc_html(get_the_title());
				$sireNoLink = esc_attr(get_field( "sire_no_link" ));
				$damNoLink = esc_attr(get_field( "dam_no_link" ));
				$sire = get_field( "sire" );
				$sireCall = esc_html(get_post_meta( $sire->ID, 'call_name', true ));
				if ( $sireCall ) : $sireCall = '"'.$sireCall.'"'; else: $sireCall = "&nbsp;"; endif;
				$dam = get_field( "dam" );
				$damCall = esc_html(get_post_meta( $dam->ID, 'call_name', true ));
				if ( $damCall ) : $damCall = '"'.$damCall.'"'; else: $damCall = "&nbsp;"; endif;
				$sirePic = get_the_post_thumbnail( $sire->ID, "thumbnail", array( "class"=>"img-litters" ) );
				$damPic = get_the_post_thumbnail( $dam->ID, "thumbnail", array( "class"=>"img-litters" ) );
				if ( !$sirePic ) $sirePic = wp_get_attachment_image( $noPic, "thumbnail", array( 'class'=>'img-litters' ));
				if ( !$damPic ) $damPic = wp_get_attachment_image( $noPic, "thumbnail", array( 'class'=>'img-litters' ));

				if ( !$sireNoLink ) :
					$setupSire = '<div class="text-dogs litter-sire span-5">'.$sirePic.'<h2>'.$sireCall.'</h2><h3>'.esc_html(get_the_title($sire)).'</h3></div>';
				else:
					$setupSire = '<div class="text-dogs litter-sire span-5">'.$sirePic.'<h2>'.$sireNoLink.'</h2></div>';
				endif;

				if ( !$damNoLink ) :
					$setupDam = '<div class="text-dogs litter-dam span-5">'.$damPic.'<h2>'.$damCall.'</h2><h3>'.esc_html(get_the_title($dam)).'</h3></div>';
				else:
					$setupDam = '<div class="text-dogs litter-dam span-5">'.$damPic.'<h2>'.$damNoLink.'</h2></div>';
				endif;

				$setupCenter = '<div class="litter-x span-2"><h2>x</h2>'.do_shortcode('[btn link="'.esc_url(get_permalink($id)).'" ada="'.$name.'"]View[/btn]</div>');
				$buildArchive .= do_shortcode('[col name="'.sanitize_title(get_the_title()).'" class="'.$classes.'"]'.$setupSire.$setupCenter.$setupDam.'[/col]');

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

			echo $displayArchive;

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif;
		?>

		<?php bp_after_the_content(); ?>

	</div><!-- .site-main-inner -->

	<?php bp_after_site_main_inner(); ?>

</div><!-- #primary .site-main -->

<?php get_footer();