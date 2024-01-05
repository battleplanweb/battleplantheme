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
					$city_state = str_replace('-', ', ', $term->name);
					$archiveHeadline = "Recent Jobs in ".$city_state;
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
			$sectionWidth = "default";
			
			$buildUpdate = "";
			$lat_lng = array();
					
			if ( function_exists( 'overrideArchive' ) ) { overrideArchive( get_post_type() ); }
		
			$testImgOrder = $testimonialImg == "left" ? 2 : 0;

		// Build Archive
			while ( have_posts() ) : the_post(); 		
				$jobDate = esc_attr(get_field( "job_date" ));		
				$jobDateTime = new DateTime($jobDate);
				$jobIsoDate = $jobDateTime->format("Y-m-d\TH:i:s.uP");
				$jobDateTimeImm = new DateTimeImmutable($jobDate);	
				$now = new DateTime();
    			$interval = $now->diff($jobDateTimeImm);

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
				$address = esc_attr(get_field( "address" ));
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
				$jobDesc = wp_kses_post( get_the_content() );
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

				$buildUpdate .= '[section width="'.$sectionWidth.'" name="jobsite-'.get_the_ID().'" class="archive-content archive-'.get_post_type().$addTags.$addClass.'"][layout grid="'.$grid.'" valign="'.$valign.'"]';
				
				$buildUpdate .= '[col]';
		
				$buildUpdate .= '<div class="jobsite-description"><p>';
				$buildUpdate .= '<span class="jobsite_geo-job_meta">'.$city.', '.$state.' ▪ '.$when.'</span><br>';
				$buildUpdate .= $jobDesc.'</p></div>';
		
		?>		
				<script nonce="<?php echo _BP_NONCE; ?>" type="application/ld+json">				
					{
						"@context": "https://schema.org",
						"@type": "UserCheckins",
						"name": "<?php echo $GLOBALS['customer_info']['name']; ?>",
						"startDate": "<?php echo $jobIsoDate; ?>",
						"description": "<?php echo $jobDesc; ?>",
						"location": {
							"@type": "Place",
							"address": {
								"@type": "PostalAddress",
								"name": "<?php echo $address.', '.$city.', '.$state.' '.$zip ?>"
							},
							"geo": {
								"@type": "GeoCoordinates",
								"latitude": <?php echo $geocode[0]['lat']; ?>,
								"longitude": <?php echo $geocode[0]['lng']; ?>
							}
						},
						"image": "<?php echo $img[0]; ?>"
					}
				</script>
		<?php
		
				if ( $imgs[4] ) $imgSize = "quarter-f";
				elseif ( $imgs[3] ) $imgSize = "third-f";
				else $imgSize = "half-f";		
		
				$buildUpdate .= '<ul class="jobsite-photos side-by-side aligncenter break-none">';
				for ($i = 0; $i < count($imgs); $i++) :
					$img = wp_get_attachment_image_src( $imgs[$i], $imgSize );

					list ($src, $width, $height ) = $img;
					if ($height > 0) $ratio = $width / $height;	
					$buildUpdate .= '<li style="flex: '.$ratio.'" class="full-top">'.wp_get_attachment_image( $imgs[$i], $imgSize, "", ["alt" => $alt[$i]] ).'</li>';	
				endfor;
				$buildUpdate .= '</ul>';
		
				if ( $newBrand ) :
					if ( $oldBrand ) : $buildUpdate .= '<div class="jobsite-equipment">Replaced customer\'s <b>'.$oldBrand.' '.$oldEquip; endif;
					if ( $oldModel ) : $buildUpdate .= '<span class="jobsite-model"> [Model #'.$oldModel.']</span>'; endif;
					if ( $oldBrand ) : $buildUpdate .= '</b> with a new <b>'.$newBrand.' '.$newEquip; endif;
					if ( $newModel ) : $buildUpdate .= '<span class="jobsite-model"> [Model #'.$newModel.']</span>'; endif;
					if ( $oldBrand ) : $buildUpdate .= '</b></div>'; endif;
				else:
					if ( $oldBrand ) : $buildUpdate .= '<div class="jobsite-equipment">Repaired customer\'s <b>'.$oldBrand.' '.$oldEquip; endif;
					if ( $oldModel ) : $buildUpdate .= '<span class="jobsite-model"> [Model #'.$oldModel.']</span>'; endif;
					if ( $oldBrand ) : $buildUpdate .= '</b></div>'; endif;
				endif;

				if ( $review ) :
					$meta = wp_get_attachment_metadata( get_post_thumbnail_id( $review ) );
					$thumbW = $meta['sizes'][$size]['width'];
					$thumbH = $meta['sizes'][$size]['height'];
		
					$testimonialName = get_the_title($review);
					$testimonialPlatform = strtolower(esc_attr(get_post_field( 'testimonial_platform', $review )));	
					$testimonialRate = esc_attr(get_post_field( 'testimonial_rating', $review ));	
					$testimonialContent = wp_kses_post( get_post_field('post_content', $review));
				
					$buildCredentials = "<div class='testimonials-credential testimonials-name'>".$testimonialName."</div>";
					if ( $testimonialRate ) $buildCredentials .= "<div class='testimonials-credential testimonials-rating'>".$testimonialRate."</div>";
		
					$buildUpdate .= '<div class="jobsite-review">';
		
					if ( $meta ) :
						$buildUpdate .= '[img order="1" size="'.$picSize.'" class="image-testimonials"]'.get_the_post_thumbnail( $review, 'thumbnail', array( 'loading' => 'lazy', 'class'=>'img-archive img-testimonials', 'style'=>'aspect-ratio:'.$thumbW.'/'.$thumbH )).'[/img]'; 
					else:						
						$buildUpdate .= "[img size='".$picSize."' class='image-testimonials testimonials-generic-icon']<svg version='1.1' class='anonymous-icon' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' x='0px' y='0px' viewBox='0 0 400 400' xml:space='preserve'><g><path class='user-icon' d='M332,319c-34.9,30-80.2,48.2-129.8,48.4h-1.7c-49.7-0.2-95.2-18.5-130.1-48.7c12.6-69,51.6-123.1,100.6-139c-27.6-11.8-46.9-39.1-46.9-71c0-42.6,34.5-77.1,77-77.1s77.1,34.5,77.1,77.1c0,31.9-19.3,59.2-46.9,71C276.7,195,315.7,249,332,319z'/></g></svg>[/img]"; 	
					endif;		
		
					$buildUpdate .= '<div style="order: '.$testImgOrder.'" class="block block-group span-'.convertSize(getTextSize( $picSize )).'">[txt class="testimonials-quote"][p]'.$testimonialContent.'[/p][/txt][txt size="11/12" class="testimonials-credentials"]'.$buildCredentials.'[/txt][txt size="1/12" class="testimonials-platform testimonials-platform-'.$testimonialPlatform.'"][/txt]</div>';		
		
		?>		
				<script nonce="<?php echo _BP_NONCE; ?>" type="application/ld+json">				
					{
						"@context":"https://schema.org",
						"@type": "UserReview",
						"author": {
							"@type": "Person",
							"name": "<?php echo $testimonialName; ?>",
							"address": "<?php echo $address.', '.$city.', '.$state.' '.$zip ?>"
						},
						"itemReviewed":{
							"@type": "HomeAndConstructionBusiness",
							"name": "<?php echo $GLOBALS['customer_info']['name']; ?>"
						},
						"reviewRating":{
							"@type": "Rating",
							"ratingValue": <?php echo $testimonialRate; ?>,
							"worstRating": 1,
							"bestRating": 5
						},
						"reviewBody": "<?php echo $testimonialContent; ?>",
						"contentLocation":{
							"@type": "Place",
							"address": {
								"@type": "PostalAddress",
								"name": "<?php echo $address.', '.$city.', '.$state.' '.$zip ?>"
							},
							"geo":{
								"@type": "GeoCoordinates",
								"latitude": <?php echo $geocode[0]['lat']; ?>,
								"longitude": <?php echo $geocode[0]['lng']; ?>
							}
						}
					}
				</script>
		<?php
							
					$buildUpdate .= '</div>';
				endif;

				$buildUpdate .= '[/col][/layout][/section]';				
		
			endwhile; 		
		
		$GLOBALS['mapPins'] = json_encode($lat_lng);		
		
		// Set up javascript to build map	
		add_action('wp_footer', 'battleplan_googleMapsAPI');
		function battleplan_googleMapsAPI() { ?>
			<script defer nonce="<?php echo _BP_NONCE; ?>">
				var addresses = <?php echo $GLOBALS['mapPins']; ?>;
				var geocoder, map, totalDis = 0, midLat = 0, midLng = 0, maxLat = 0, minLat = 0, maxLng = 0, minLng = 0, totalPins = addresses.length;

				function initMap() { 
					geocoder = new google.maps.Geocoder();
					
					for (var i = 0; i < totalPins; i++) {
						var coords = addresses[i].split(', ');
						if (coords.length === 2) {
							var thisLat = parseFloat(coords[0]), thisLng = parseFloat(coords[1]);
							midLat += thisLat;
							midLng += thisLng;		
							    
							if (thisLat > maxLat) maxLat = thisLat;
							if (thisLat < minLat) minLat = thisLat;
    						if (thisLng > maxLng) maxLng = thisLng;
    						if (thisLng < minLng) minLng = thisLng;
						}
					}
					
					midLat = midLat / totalPins;
					midLng = midLng / totalPins;
					totalDis = maxLat - minLat;
					if ( (maxLng - minLng) > totalDis ) totalDis = maxLng - minLng;
							
					if ( window.innerWidth <= 768) {
  						totalDis = Math.floor(totalDis / 8.5);
					} else {
						totalDis = Math.floor(totalDis / 8);
					}
					
					map = new google.maps.Map(document.getElementById('map'), {
						center: { lat: midLat, lng: midLng },
						zoom: totalDis,
						styles: [
						  {
							featureType: 'poi',
							elementType: 'labels',
							stylers: [{ visibility: 'off' }] // Hide points of interest labels
						  },
						]
					});

					for (var i = 0; i < totalPins; i++) {
						var coords = addresses[i].split(', ');
						if (coords.length === 2) {
							var lat = parseFloat(coords[0]);
							var lng = parseFloat(coords[1]);
							
							var customMarkerIcon = {
								url: '<?php echo site_url() ?>/wp-content/uploads/jobsite_geo-pin.webp', 
								scaledSize: new google.maps.Size(60, 60),
								origin: new google.maps.Point(0, 0), 
								anchor: new google.maps.Point(30, 55)
							};

							if (!isNaN(lat) && !isNaN(lng)) {
								var marker = new google.maps.Marker({
									map: map,
									position: new google.maps.LatLng(lat, lng),
        							icon: customMarkerIcon
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
		
			$buildIntro = "";
			$mapGrid = "1";
		
			if ( function_exists( 'jobsiteGEOpages' ) && jobsiteGEOpages( $city_state, $city ) != null ) :
				$buildIntro .= '[col][txt]'.jobsiteGEOpages( $city_state, $city ).'[/txt][/col]';	
				$mapGrid = "1-1";
			endif;
		
			$buildIntro .= '[col name="map" class="map-'.get_post_type().'"][/col]';		

			$displayArchive .= do_shortcode('[section width="inline" class="'.get_post_type().'-content '.get_post_type().'-intro"][layout grid="'.$mapGrid.'"]'.$buildIntro.'[/layout][/section]');			
		
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

</div><!-- #main-content -->
</section><!-- #wrapper-content -->

<section id="wrapper-bottom">
	<?php echo do_shortcode($buildUpdate); ?>

	<?php get_footer();