<?php /* The template for displaying archive pages for "jobsite_geo" post type */

wp_enqueue_style( 'battleplan-style-posts', get_template_directory_uri()."/style-posts.css", array('parent-style'), _BP_VERSION ); 
wp_enqueue_style( 'battleplan-style-posts', get_template_directory_uri()."/style-posts.css", array('parent-style'), _BP_VERSION );
wp_enqueue_style( 'battleplan-jobsite_geo', get_template_directory_uri()."/style-jobsite_geo.css", array('parent-style'), _BP_VERSION ); 	
wp_enqueue_script( 'battleplan-script-jobsite_geo', get_template_directory_uri().'/js/script-jobsite_geo.js', array(), _BP_VERSION,  array( 'strategy' => 'defer', 'in_footer' => 'true' ) );   

get_header(); ?>

<div id="primary" class="site-main" role="main" aria-label="main content">

	<?php bp_before_site_main_inner(); ?>	
		
	<div class="site-main-inner">
	
		<?php bp_before_the_content(); ?>	
	
		<?php if ( have_posts() ) : 			
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
				$name = trim(get_the_title(), ' ');		
				$address = trim(esc_attr(get_field( "address" )), ' ');
				$city = trim(esc_attr(get_field( "city" )), ' ');
				$state = trim(strtoupper(esc_attr(get_field( "state" ))), ' ');
				$location = format_location($city.'-'.$state);
				$oldBrand = ucwords(trim(esc_attr(get_field( "old_brand" )), ' '));
				$oldEquip = ucwords(trim(esc_attr(get_field( "old_equipment" )), ' '));
				$oldModel = trim(esc_attr(get_field( "old_model_no" )), ' ');
				$newBrand = ucwords(trim(esc_attr(get_field( "new_brand" )), ' '));
				$newEquip = ucwords(trim(esc_attr(get_field( "new_equipment" )), ' '));
				$newModel = trim(esc_attr(get_field( "new_model_no" )), ' ');
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
					$query = bp_WP_Query('testimonials', [
						'posts_per_page' => -1,
						'post_status'    => 'publish'
					]);

					if ($query->have_posts()) : while ($query->have_posts()) : $query->the_post();
						if (get_the_title() === $name) :
							$review = get_the_ID();
							wp_reset_postdata();
						endif;
					endwhile; endif;

					wp_reset_postdata();
				endif;
		
				$terms = wp_get_post_terms( get_the_ID(), 'jobsite_geo-techs', array("fields" => "all"));

				if (!is_wp_error($terms) && !empty($terms)) :
					foreach ($terms as $term) $tech = $term->name;     
				else:
					$rand = array('we', 'our tech', 'our technician', 'the tech', 'the technician');
					$tech = $rand[rand(0, count($rand) - 1)];			
        		endif;
		
				$rand = array('which '.$tech.' restored to full functionality', 'which '.$tech.' restored to operational status', 'which '.$tech.' returned to good condition', 'which '.$tech.' made fully operational again', 'which '.$tech.' restored to working condition', 'which '.$tech.' repaired to work as intended', 'which '.$tech.' restored to peak performance', 'which '.$tech.' successfully repaired', 'on which '.$tech.' completed the repair', 'which '.$tech.' repaired successfully', 'and '.$tech.' delivered complete customer satisfaction', 'on which '.$tech.' delivered a full repair', 'and '.$tech.' ensured absolute customer satisfaction', 'and '.$tech.' provided full restoration', 'and '.$tech.' provided complete repair', 'on which '.$tech.' fulfilled the repair request', 'and '.$tech.' provided unparalleled customer satisfaction');
				$repair = $rand[rand(0, count($rand) - 1)];			
		
    			$word = strtolower($oldBrand);
    			$vowelSounds = ['a', 'e', 'i', 'o', 'u', 'hour', 'honest'];
    			$an = "a";

    			if (in_array(substr($word, 0, 5), $vowelSounds) || in_array(substr($word, 0, 6), $vowelSounds) || in_array(substr($word, 0, 1), $vowelSounds)) $an = "an";
    			if (preg_match('/^u(ni|se|sa|e)/', $word)) $an = "a";
		
				if ( $newBrand ) :
					if ( $oldBrand ) : $jobDesc .= '</p><p>The customer had '.$an.' <b>'.$oldBrand.($oldEquip ? ' ' . $oldEquip : ''); endif;
					if ( $oldModel ) : $jobDesc .= '<span class="jobsite-model"> [Model #'.$oldModel.']</span>'; endif;
					if ( $oldBrand ) : $jobDesc .= '</b>, which '.$tech.' replaced with a new <b>'.$newBrand.($newEquip ? ' ' . $newEquip : ''); endif;
					if ( $newModel ) : $jobDesc .= '<span class="jobsite-model"> [Model #'.$newModel.']</span>'; endif;
					if ( $oldBrand ) : $jobDesc .= '</b>.'; endif;
				else:
					if ( $oldBrand ) : $jobDesc .= '</p><p>The customer has '.$an.' <b>'.$oldBrand.($oldEquip ? ' ' . $oldEquip : ''); endif;
					if ( $oldModel ) : $jobDesc .= '<span class="jobsite-model"> [Model #'.$oldModel.']</span>'; endif;
					if ( $oldBrand ) : $jobDesc .= '</b>, '.$repair.'.'; endif;
				endif;

				$buildUpdate .= '[section width="'.$sectionWidth.'" name="jobsite-'.get_the_ID().'" class="archive-content archive-'.get_post_type().$addTags.$addClass.'"][layout grid="'.$grid.'" valign="'.$valign.'"]';
				
				$buildUpdate .= '[col]';
		
				$buildUpdate .= '<div class="jobsite-description"><p>';
				$buildUpdate .= '<div class="jobsite_geo-job_meta">'.$location.'</div>';
				$buildUpdate .= $jobDesc.'</div>';
				$cleanedJobDesc = htmlspecialchars(strip_tags($jobDesc), ENT_QUOTES, 'UTF-8');
				$customer_info = customer_info();
		
		?>		
				<script nonce="<?php echo _BP_NONCE; ?>" type="application/ld+json">				
					{
						"@context": "https://schema.org",
						"@type": "UserCheckins",
						"name": "<?php echo $customer_info['name']; ?>",
						"startDate": "<?php echo $jobIsoDate; ?>",
						"description": "<?php echo $cleanedJobDesc; ?>",
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
		
				$buildUpdate .= '<ul class="jobsite-photos side-by-side align-center break-none">';
				for ($i = 0; $i < count($imgs); $i++) :
					$img = wp_get_attachment_image_src( $imgs[$i], $imgSize );

					list ($src, $width, $height ) = $img;
					if ($height > 0) $ratio = $width / $height;	
					$buildUpdate .= '<li style="flex: '.$ratio.'" class="full-top">'.wp_get_attachment_image( $imgs[$i], $imgSize, "", ["alt" => $alt[$i]] ).'</li>';	
				endfor;
				$buildUpdate .= '</ul>';

				if ( $review ) :
					wp_enqueue_style( 'battleplan-testimonials', get_template_directory_uri()."/style-testimonials.css", array('parent-style'), _BP_VERSION ); 

					$meta = wp_get_attachment_metadata( get_post_thumbnail_id( $review ) );
					$thumbW = $meta['sizes'][$size]['width'];
					$thumbH = $meta['sizes'][$size]['height'];
		
					$testimonialName = get_the_title($review);
					$testimonialPlatform = strtolower(esc_attr(get_post_field( 'testimonial_platform', $review )));	
					$testimonialContent = wp_kses_post( get_post_field('post_content', $review));
					$testimonialRate = esc_attr(get_post_field( 'testimonial_rating', $review ));			
					$testimonialStars = '';
					for ($i = 1; $i <= 5; $i++) {
						$testimonialStars .= $testimonialRate >= $i ? '[get-icon type="star"]' : ($testimonialRate >= $i - 0.5 ? '[get-icon type="star-half"]' : '[get-icon type="star-empty"]');
					}		
		
					$buildCredentials = "<div class='testimonials-credential testimonials-name'>".$testimonialName."</div>";
					if ( $testimonialRate ) $buildCredentials .= "<div class='testimonials-credential testimonials-rating'>".$testimonialStars."</div>";
		
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
							"name": "<?php echo $customer_info['name']; ?>"
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
				var pinX = <?php echo get_option('jobsite_geo')['pin_anchor_x']; ?>;
				var pinY = <?php echo get_option('jobsite_geo')['pin_anchor_y']; ?>;		
				var pinSize = 60;
				var sizeFactor = 1;

				if ( totalPins > 10 ) {
					sizeFactor = 0.6;
				} else if ( totalPins > 5) {
					sizeFactor = 0.8;
				}
				
				pinSize = pinSize * sizeFactor;
				pinX = pinX * sizeFactor;
				pinY = pinY * sizeFactor;
			
				function initMap() { 
					const bounds = new google.maps.LatLngBounds();
					geocoder = new google.maps.Geocoder();
					
					map = new google.maps.Map(document.getElementById('map'), {
						center: { lat: 0, lng: 0 },
						zoom: 8,
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
								scaledSize: new google.maps.Size(pinSize, pinSize),
								origin: new google.maps.Point(0, 0), 
								anchor: new google.maps.Point(pinX, pinY)
							};

							if (!isNaN(lat) && !isNaN(lng)) {
								var marker = new google.maps.Marker({
									map: map,
									position: new google.maps.LatLng(lat, lng),
        							icon: customMarkerIcon
								});
								
								bounds.extend(marker.getPosition());
							}
						}
					}
					map.fitBounds(bounds);
					
						const listener = google.maps.event.addListenerOnce(map, 'bounds_changed', function () {
							const maxZoom = 14;
							if (map.getZoom() > maxZoom) map.setZoom(maxZoom);
						});
				}
			</script>

			<script async defer nonce="<?php echo _BP_NONCE; ?>" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC3gx2Pk4A6N_3Uxiik83Y_DTFRGRrYdSM&&callback=initMap"></script>
		<?php }


		// Display Archive
			$displayHeader = '<header class="archive-header header-'.get_post_type().'">';
				$displayHeader .= '<h1 class="page-headline archive-headline '.get_post_type().'-headline">'.$GLOBALS['jobsite_geo-headline'].'</h1>';
				$displayHeader .= '<div class="archive-description archive-intro '.get_post_type().'-intro">'.$archiveIntro.'</div>'; 
			$displayHeader .= '</header><!-- .archive-header-->';
		
			$buildIntro = '[col class="jobsite_geo_content"][txt]'.bp_wpautop($GLOBALS['jobsite_geo-content']).'[/txt][/col]';	
		
			$buildIntro .= '[col class="jobsite_geo_map_holder"][txt class="jobsite_geo_map"]<div id="map" class="map-'.get_post_type().'"></div><div class="map-jobsite_geo-caption">'.$GLOBALS['jobsite_geo-map-caption'].'</div>[/txt][/col]';		

			$displayHeader .= do_shortcode('[section width="inline" class="'.get_post_type().'-content '.get_post_type().'-intro"][layout grid="'.$GLOBALS['mapGrid'].'"]'.$buildIntro.'[/layout][/section]');			
		
			$displayFooter = '<footer class="archive-footer">';
				$displayFooter .= get_the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => _x( '<span class="icon chevron-left" aria-hidden="true"></span>', 'Previous set of posts' ), 'next_text' => _x( '<span class="icon chevron-right" aria-hidden="true"></span>', 'Next set of posts' ), ));
			$displayFooter .= '</footer><!-- .archive-footer-->';
		
			echo $displayHeader;	

		else :

			get_template_part( 'template-parts/content', 'none' );

		endif;
		?>

		<?php bp_after_the_content(); ?>	

	</div><!-- .site-main-inner -->
	
	<?php bp_after_site_main_inner(); ?>	

</div><!-- #primary .site-main -->

</div><!-- #main-content -->
</main><!-- #wrapper-content -->

<main id="wrapper-bottom">
	<div>
		<h2 style="margin:1em 0"><?php echo $GLOBALS['jobsite_geo-bottom-headline']; ?></h2>

		<?php echo do_shortcode($buildUpdate); ?>

		<?php echo $displayFooter; ?>

		<?php get_footer();