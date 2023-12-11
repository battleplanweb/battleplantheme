<?php /* The template for displaying archive pages for "dogs" post type */

get_header(); ?>

<main id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<?php if ( have_posts() ) : 		
			$term_name = get_query_var('term'); 
		
			if ($term_name) :
				$term = get_term_by('slug', $term_name, 'jobsite_geo-service-areas');
				if ( $term && !is_wp_error($term) ) :
					$archiveHeadline = "Recent Jobs in ".str_replace('-', ', ', $term->name);
				else:
					$term = get_term_by('slug', $term_name, 'jobsite_geo-services');
					if ( $term && !is_wp_error($term) ) :
						$archiveHeadline = "Recent ".$term->name." Jobs";
					else:
						$term = get_term_by('slug', $term_name, 'jobsite_geo-techs');
						if ( $term && !is_wp_error($term) ) :
							$archiveHeadline = "Recent Jobs by ".$term->name;
						else:
							$archiveHeadline = "Recent Jobs";
						endif;
					endif;
				endif;
			endif;		
		
			$grid = "1";		
			$valign = "start";
			$showThumb = "false";
			$picSize = "1/4";
			$testimonialImg = "left";
			$buildUpdate = "";
			$lat_lng = array();
					
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }
		
			$testImgOrder = $testimonialImg == "left" ? 2 : 0;

		// Build Archive
			while ( have_posts() ) : the_post(); 
		
				$now = new DateTime();
    			$date = new DateTimeImmutable(esc_attr(get_field( "job_date" )));
    			$interval = $now->diff($date);

    			if ($interval->y > 0) {
        			$when = $interval->y == 1 ? 'a year ago' : $interval->y . ' years ago';
				} elseif ($interval->m > 0) {
        			$when =  $interval->m == 1 ? 'a month ago' : $interval->m . ' months ago';
    			} elseif ($interval->d >= 7) {
        			$weeks = floor($interval->d / 7);
       				$when =  $weeks == 1 ? 'a week ago' : $weeks . ' weeks ago';
    			} elseif ($interval->d > 0) {
        			$when =  $interval->d == 1 ? 'a day ago' : $interval->d . ' days ago';
    			} 		
				
				$name = get_the_title();
				$city = esc_attr(get_field( "city" ));
				$state = esc_attr(get_field( "state" ));
				$oldBrand = esc_attr(get_field( "old_brand" ));
				$oldEquip = esc_attr(get_field( "old_equipment" ));
				$oldModel = esc_attr(get_field( "old_model_no" ));
				$newBrand = esc_attr(get_field( "new_brand" ));
				$newEquip = esc_attr(get_field( "new_equipment" ));
				$newModel = esc_attr(get_field( "new_model_no" ));
				$imgs[0] = esc_attr(get_field( "jobsite_photo_1"));
				$imgs[1] = esc_attr(get_field( "jobsite_photo_2"));
				$imgs[2] = esc_attr(get_field( "jobsite_photo_3"));
				$imgs[3]= esc_attr(get_field( "jobsite_photo_4"));
				$alt[0] = esc_attr(get_field( "jobsite_photo_1_alt"));
				$alt[1] = esc_attr(get_field( "jobsite_photo_2_alt"));
				$alt[2] = esc_attr(get_field( "jobsite_photo_3_alt"));
				$alt[3] = esc_attr(get_field( "jobsite_photo_4_alt"));		
				$imgs = array_filter($imgs);
				$alt = array_filter($alt);
				$review = esc_attr(get_field( "review"));	
				$geocode = get_post_meta(get_the_ID(), 'geocode');
				if ( $geocode ) $lat_lng[] = $geocode[0]['lat'].', '.$geocode[0]['lng'];


				if ( $review == '' ) :
					$query = new WP_Query( array( 'post_type' => 'testimonials', 'posts_per_page' => -1, 'post_status' => 'publish' ) );

					if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
						if (get_the_title() === $name) :
							$review = get_the_ID();
							wp_reset_postdata();
						endif;
					endwhile; endif;

					wp_reset_postdata();
				endif;

				$classes = 'col-archive col-'.get_post_type().' col-'.get_the_ID().$addTags.$addClass;		
				
				$buildUpdate .= '[col id="jobsite-'.get_the_ID().'" class="'.$classes.'"]';
		
				$buildUpdate .= '[group size="100" class=""]';
		
				$buildUpdate .= '<div class="jobsite-meta span-12">Date: '.$when.'<br>City: '.$city.', '.$state.'</div>';
		
				$buildUpdate .= '<div class="jobsite-description span-12">'.wp_kses_post( get_the_content() ).'</div>';
		
				if ( $imgs[4] ) $imgSize = "quarter-f";
				elseif ( $imgs[3] ) $imgSize = "third-f";
				else $imgSize = "half-f";		
		
				$buildUpdate .= '<ul class="jobsite-photos side-by-side aligncenter span-12 break-none">';
				for ($i = 0; $i < count($imgs); $i++) :
					$img = wp_get_attachment_image_src( $imgs[$i], $imgSize );

					list ($src, $width, $height ) = $img;
					if ($height > 0) $ratio = $width / $height;	
					$buildUpdate .= '<li style="flex: '.$ratio.'" class="full-top">'.wp_get_attachment_image( $imgs[$i], $imgSize, "", ["alt" => $alt[$i]] ).'</li>';	
				endfor;
				$buildUpdate .= '</ul>';
	
				if ( $oldBrand ) $buildUpdate .= '<div class="jobsite-equip-former span-12">Equipment: '.$oldBrand.' '.$oldEquip;
				if ( $oldModel ) $buildUpdate .= '<br>Model #'.$oldModel;
				if ( $oldBrand ) $buildUpdate .= '</div>';
		
				if ( $newBrand ) $buildUpdate .= '<div class="jobsite-equip-new span-12">New Equipment: '.$newBrand.' '.$newEquip;
				if ( $newModel ) $buildUpdate .= '<br>Model #'.$newModel;
				if ( $newBrand ) $buildUpdate .= '</div>';


				if ( $review ) :
					$meta = wp_get_attachment_metadata( get_post_thumbnail_id( $review ) );
					$thumbW = $meta['sizes'][$size]['width'];
					$thumbH = $meta['sizes'][$size]['height'];
		
					$testimonialName = get_the_title($review);
					$testimonialPlatform = strtolower(esc_attr(get_post_field( 'testimonial_platform', $review )));	
					$testimonialRate = esc_attr(get_post_field( 'testimonial_rating', $review ));	
				
					$buildCredentials = "<div class='testimonials-credential testimonials-name'>".$testimonialName."</div>";
					if ( $testimonialRate ) $buildCredentials .= "<div class='testimonials-credential testimonials-rating'>".$testimonialRate."</div>";
		
					$buildUpdate .= '<div class="jobsite-review span-12">';
		
					$buildUpdate .= '[img order="1" size="'.$picSize.'" class="image-testimonials"]'.get_the_post_thumbnail( $review, 'thumbnail', array( 'loading' => 'lazy', 'class'=>'img-archive img-testimonials', 'style'=>'aspect-ratio:'.$thumbW.'/'.$thumbH )).'[/img]'; 
		
					$buildUpdate .= '<div style="order: '.$testImgOrder.'" class="block block-group span-'.convertSize(getTextSize( $picSize )).'">[txt class="testimonials-quote"][p]'.wp_kses_post( get_post_field('post_content', $review) ).'[/p][/txt][txt size="11/12" class="testimonials-credentials"]'.$buildCredentials.'[/txt][txt size="1/12" class="testimonials-platform testimonials-platform-'.$testimonialPlatform.'"][/txt]</div>';
							
					$buildUpdate .= '</div>';
				endif;
		
				$buildUpdate .= '[/group]';
				$buildUpdate .= '[/col]';				
		
			endwhile; 		
		
		$GLOBALS['mapPins'] = json_encode($lat_lng);
		
		// Set up javascript to build map	
		add_action('wp_footer', 'battleplan_googleMapsAPI');
		function battleplan_googleMapsAPI() { ?>
			<script defer nonce="<?php echo _BP_NONCE; ?>">
				var geocoder;
				var map;
				var addresses = <?php echo $GLOBALS['mapPins']; ?>;

				function initMap() { 
					geocoder = new google.maps.Geocoder();

					map = new google.maps.Map(document.getElementById('map'), {
						center: { lat: 33.1507, lng: -96.8236 },
						zoom: 11
					});

					for (var i = 0; i < addresses.length; i++) {
						var coords = addresses[i].split(', ');
						if (coords.length === 2) {
							var lat = parseFloat(coords[0]);
							var lng = parseFloat(coords[1]);

							if (!isNaN(lat) && !isNaN(lng)) {
								var marker = new google.maps.Marker({
									map: map,
									position: new google.maps.LatLng(lat, lng)
								});
							}
						}
					}
				}
			</script>

			<script async defer nonce="<?php echo _BP_NONCE; ?>" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBqf0idxwuOxaG-j3eCpef1Bunv-YVdVP8&&callback=initMap"></script>
		<?php }


		// Display Archive
			$displayArchive = '<header class="archive-header header-'.get_post_type().'">';
				$displayArchive .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$archiveHeadline.'</h1>';
				$displayArchive .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>'; 
			$displayArchive .= '</header><!-- .archive-header-->';
		
		
			$displayArchive .= '<div id="map" class="map-'.get_post_type().'" style="width: 100%; height: 400px;"></div>';		
			
			$displayArchive .= do_shortcode('[section width="inline" class="archive-content archive-'.get_post_type().'"][layout grid="'.$grid.'" valign="'.$valign.'"]'.$buildUpdate.'[/layout][/section]');

		
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
