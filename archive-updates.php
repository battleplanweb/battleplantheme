<?php /* The template for displaying archive pages for "updates" post type (site type: profile) */

get_header(); ?>

	<main id="primary" class="site-main">

		<?php if ( have_posts() ) : 		
			$archiveHeadline = "Recent Updates";		
			$grid = "1";
			$valign = "stretch";
			$showThumb = "true"; 
			$size = "thumbnail";
			$picSize = "25";
			$textSize = "75";
			$showBtn = "false";
			$showExcerpt = "false";
			$showContent = "true";				
			$showDate = "true";
			$showAuthor = "true";
			$accordion = "false";
			$countTease = "true";
			$countView = "true";
			$link = "post"; //post, cf-custom-field, specific link, false
			$addInfo = "";				
			$addClass = "";
			/*
			ob_start(); ?>
				<div class="row-of-buttons">
					<div class="block block-button"><button class="males-btn" tabindex="0"><i class='fa fas fa-mars'></i> Males</button></div>
					<div class="block block-button"><button class="females-btn" tabindex="0"><i class='fa fas fa-venus'></i> Females</button></div>
					<div class="block block-button"><button class="all-btn" tabindex="0"><i class='fa fas fa-venus-mars'></i> All</button></div>
				</div>
			<?php 
			$archiveIntro = ob_get_clean();
			$noPic = "774";
			*/
			
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }

		// Build Archive
			while ( have_posts() ) : the_post(); 
				if ( $addClass != '' ) $addClass = " ".$addClass;
				
				$profileID = get_the_author_meta('ID');
		
		/*
				$addTags = "";
				$taxonomies = get_object_taxonomies(get_post_type()); 
 				foreach( $taxonomies as $tax ) :
   					$getTerms = get_the_terms( $post->ID, $tax );
					foreach($getTerms as $getTerm) : $addTags .= " ".$tax."-".$getTerm->slug; endforeach;
 				endforeach;	
				*/

				$classes = 'col-archive col-'.get_post_type().' col-'.get_the_ID().$addTags.$addClass;
				
				$buildUpdate .= '[col class="'.$classes.'"][txt]';
				$buildUpdate .= '<p><a href="/profile?user='.$profileID.'" class="link-archive link-'.get_post_type().'" ada-hidden="true"  tabindex="-1">[get-user user="'.$profileID.'" info="avatar" size="thumbnail-small"]</a>';					
				if ( current_user_can('delete_post', get_the_ID()) ) $buildUpdate .= '<a class="delete-post" href="'.get_delete_post_link( get_the_ID() ).'"><span><i class="fas fa-times"></i>Delete Post</span></a><br/>';
				$buildUpdate .= '<span class="archive-author '.get_post_type().'-author author"><a href="/profile?user='.$profileID.'" class="link-archive link-'.get_post_type().'"><i class="fas fa-user"></i>[get-user user="'.$profileID.'" info="first"] [get-user user="'.$profileID.'" info="last"]</a></span><br/>';
				$buildUpdate .= '<span class="archive-date '.get_post_type().'-date date"><i class="fas fa-calendar-alt"></i>'.get_the_date().'</span></p>';
				$buildUpdate .= "<h3 data-count-tease=".$countTease." data-count-view=".$countView." data-id=".get_the_ID().">";
				$buildUpdate .= esc_html(get_the_title());  
				$buildUpdate .= "</h3>";
				$buildUpdate .= do_shortcode('[p]'.wp_kses_post( get_the_content() ).'[/p]');					
				$buildUpdate .= '[/txt][/col]';		
			endwhile; 

		// Display Archive
			$displayArchive = '<header class="archive-header">';
				$displayArchive .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$archiveHeadline.'</h1>';
				$displayArchive .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>'; 
			$displayArchive .= '</header><!-- .archive-header-->';
		
			$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-'.get_post_type().'"][layout grid="'.$grid.'" valign="'.$valign.'"]'.$buildUpdate.'[/layout][/section]');
		
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
