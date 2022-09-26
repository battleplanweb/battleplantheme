<?php /*** View: Month View - Day Date *** [your-theme]/tribe/events/v2/month/calendar-body/day/date.php */ 

	$eventID = $day['events'][0]->ID;
	$buildDay = '';

	$buildDay .= '<div class="tribe-events-calendar-month__day-cell" >';
		$buildDay .= '<div class="tribe-events-calendar-month__day-date tribe-common-h6 tribe-common-h--alt">';
			$buildDay .= '<time class="tribe-events-calendar-month__day-date-daynum" datetime="'.esc_attr( $day['date'] ).'" >'.esc_html( $day['day_number'] ).'</time>';
			if ( $day['found_events'] > 0 ) $buildDay .= '<div class="hide-1 hide-2"><a href="'.get_the_permalink($eventID).'">'.get_the_title($eventID).'</a></div>'; 
			if ( $day['found_events'] > 0 ) $buildDay .= '<a href="'.get_the_permalink($eventID).'" style="text-decoration:none"><div class="hide-3 hide-4 hide-5 center">•</div></a>';
		$buildDay .= '</div>';
	$buildDay .= '</div>';

	echo $buildDay;
?>