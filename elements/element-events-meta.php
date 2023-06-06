<?php
/* Battle Plan Web Design - Meta Data for Events */
	$venue = esc_attr(get_field( "venue" ));
	$location = esc_attr(get_field( "location" ));
	$venueLink = esc_url(get_field( "venue_link" ));
	$startTime = esc_attr(get_field( "start_time" ));
	$endTime = esc_attr(get_field( "end_time" ));
	$startDate = esc_attr(get_field( "start_date" ));
	$endDate = esc_attr(get_field( "end_date" ));
	$startMonth = date('F', strtotime($startDate)); 
	$endMonth = date('F', strtotime($endDate)); 
	$startYear = date('Y', strtotime($startDate)); 
	$endYear = date('Y', strtotime($endDate)); 
	$currentYear = date('Y');
	$currentEvent = has_term('expired', 'event-tags', get_the_ID()) ? false : true;
	$archivePage = is_archive() ? true : false;

	if ( $startYear == $currentYear ) :
	    $startDate = date('F jS', strtotime($startDate));
	endif;

	if ( $endYear == $currentYear ) :
		if ( $endMonth == $startMonth ) :
			$endDate = date('jS', strtotime($endDate));
		else:
			$endDate = date('F jS', strtotime($endDate));
		endif;
	endif;

	if ( $startDate || $startTime || $venue || $location ) :
		$buildMeta = '<div class="event-meta">';

		if ( $startDate ) :
			$buildMeta .= '<div class="event-date"><span class="date-icon"></span>'.$startDate;
			if ( $endDate ) $buildMeta .= ' - '.$endDate;
			if ( $currentEvent == false ) $buildMeta .= ' <div class="event-passed">(this event has passed)</div>';
			$buildMeta .= '</div><!-- .event-date -->';
		endif;

		if ( $startTime && $currentEvent == true ) :
			$buildMeta .= '<div class="event-time"><span class="time-icon"></span>'.$startTime;
			if ( $endTime ) $buildMeta .= ' - '.$endTime;
			$buildMeta .= '</div><!-- .event-time -->';
		endif;

		if ( $venue || $location ) :
			$buildMeta .= '<div class="event-location"><span class="location-icon"></span>';
			if ( $venue ) :
				if ( $venueLink && $currentEvent == true && $archivePage == false ) $buildMeta .= '<a href="'.$venueLink.'" target="_blank">';
				$buildMeta .= $venue;
				if ( $venueLink && $currentEvent == true && $archivePage == false ) $buildMeta .= '</a>';
			endif;

			if ( $venue && $location ) $buildMeta .= '<br><span class="empty-icon"></span>';

			if ( $location ) :
				if ( !$venue && $venueLink && $currentEvent == true && $archivePage == false ) $buildMeta .= '<a href="'.$venueLink.'" target="_blank">';
				$buildMeta .= $location;
				if ( !$venue && $venueLink && $currentEvent == true && $archivePage == false ) $buildMeta .= '</a>';
			endif;
			$buildMeta .= '</div><!-- .event-location -->';
		endif;

		$buildMeta .= '</div><!-- .event-meta -->';

		return $buildMeta;
	endif;
?>