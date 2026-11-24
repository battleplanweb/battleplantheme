<?php
/* Battle Plan Web Design - Meta Data for Events */
	$venue = esc_attr(get_field( "venue" ));
	$location = esc_attr(get_field( "location" ));
	$venueLink = esc_url(get_field( "venue_link" ));
	$currentEvent = has_term('expired', 'event-tags', get_the_ID()) ? false : true;
	$archivePage = is_archive() ? true : false;

	$blocks = function_exists('bp_event_date_blocks') ? bp_event_date_blocks(get_the_ID()) : array();
	$currentYear = date('Y');

	// Format one date range, abbreviating within the current year (e.g. "June 10th - 12th"
	// when same month, "June 10th" when single-day, "June 10th, 2027" when a future year).
	$fmtRange = function( $sRaw, $eRaw ) use ( $currentYear ) {
		$sT = strtotime( (string) $sRaw );
		if ( ! $sT ) return '';
		$startMonth = date('F', $sT);
		$startYear  = date('Y', $sT);
		$start = ( $startYear == $currentYear ) ? date('F jS', $sT) : date('F jS, Y', $sT);

		$eT = $eRaw ? strtotime( (string) $eRaw ) : false;
		if ( ! $eT || date('Y-m-d', $eT) === date('Y-m-d', $sT) ) return $start;

		$endMonth = date('F', $eT);
		$endYear  = date('Y', $eT);
		if ( $endYear == $currentYear ) {
			$end = ( $endMonth == $startMonth && $endYear == $startYear ) ? date('jS', $eT) : date('F jS', $eT);
		} else {
			$end = date('F jS, Y', $eT);
		}
		return $start . ' - ' . $end;
	};

	$fmtTime = function( $t ) {
		$ts = $t ? strtotime( (string) $t ) : false;
		return $ts ? date('g:ia', $ts) : '';
	};

	// A weekly run in words: "Tuesdays, from January 12th to February 9th, 2027", or
	// "Tuesdays, starting January 12th" with no end. The year shows once, only when it isn't this year.
	$fmtWeekly = function( $weekdays, $interval, $sT, $eT ) use ( $currentYear ) {
		$days = array_map( function( $d ) use ( $interval ) { return ucfirst( $d ) . ( $interval > 1 ? '' : 's' ); }, $weekdays );
		$last = array_pop( $days );
		$days = $days ? implode( ', ', $days ) . ' & ' . $last : $last;
		if ( $interval === 2 ) $days = 'Every other ' . $days;
		elseif ( $interval > 2 ) $days = 'Every ' . $interval . ' weeks on ' . $days;

		$sYear = date('Y', $sT);
		if ( ! $eT ) return $days . ', starting ' . date( $sYear == $currentYear ? 'F jS' : 'F jS, Y', $sT );
		$eYear = date('Y', $eT);
		$start = date( ( $sYear != $eYear && $sYear != $currentYear ) ? 'F jS, Y' : 'F jS', $sT );
		$end   = date( $eYear == $currentYear ? 'F jS' : 'F jS, Y', $eT );
		return $days . ', from ' . $start . ' to ' . $end;
	};

	if ( $blocks || $venue || $location ) :
		$buildMeta = '<div class="event-meta">';

		$recRule = function_exists('bp_event_recurrence_rule') ? bp_event_recurrence_rule(get_the_ID()) : null;

		if ( $recRule ) :
			// Recurring: show only the next upcoming occurrence date next to the calendar.
			$next = '';
			foreach ( $blocks as $b ) :
				if ( ! empty($b['s_date']) && strtotime($b['s_date']) >= strtotime('today') ) :
					$next = $fmtRange( $b['s_date'], $b['e_date'] ?? '' );
					break;
				endif;
			endforeach;

			$weekOrder = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
			$ruleDays  = array_values( array_intersect( $weekOrder, $recRule['weekdays'] ) );
			$ruleStart = strtotime( (string) $recRule['start'] );
			if ( $recRule['freq'] === 'weekly' && $ruleDays && $ruleStart ) :
				$ruleEnd = $recRule['until'] ? strtotime( (string) $recRule['until'] ) : false;
				if ( ! $ruleEnd && $next && $ruleStart < strtotime('today') ) $ruleStart = strtotime( $b['s_date'] );
				$next = $fmtWeekly( $ruleDays, $recRule['interval'], $ruleStart, $ruleEnd );
			endif;

			if ( $next ) :
				$buildMeta .= '<div class="event-date"><span class="date-icon">[get-icon type="calendar"]</span><span class="event-dates-list">'.$next.'</span></div><!-- .event-date -->';
			endif;

			if ( $currentEvent ) :
				$st = $fmtTime( $recRule['s_time'] );
				$et = $fmtTime( $recRule['e_time'] );
				if ( $st ) $buildMeta .= '<div class="event-time"><span class="time-icon">[get-icon type="clock"]</span>'.$st.( $et ? ' - '.$et : '' ).'</div><!-- .event-time -->';
			endif;

			if ( $currentEvent == false ) :
				$buildMeta .= '<div class="event-passed">(this event has passed)</div>';
			endif;

		elseif ( $blocks ) :
			// Format each block's date range + (current events only) its time string.
			$rows = array();
			foreach ( $blocks as $b ) :
				$range = $fmtRange( $b['s_date'] ?? '', $b['e_date'] ?? '' );
				if ( ! $range ) continue;
				$st = $currentEvent ? $fmtTime( $b['s_time'] ?? '' ) : '';
				$et = $currentEvent ? $fmtTime( $b['e_time'] ?? '' ) : '';
				$rows[] = array(
					'range' => $range,
					'time'  => $st ? ( $st . ( $et ? ' - ' . $et : '' ) ) : '',
				);
			endforeach;

			if ( $rows ) :
				// If every block shares one identical time, list the date ranges together
				// and show that time just once; otherwise put each block's time on its line.
				$times      = array_unique( wp_list_pluck( $rows, 'time' ) );
				$sharedTime = ( count($times) === 1 ) ? reset($times) : null;

				// Three or more single days, one week apart, read as a weekly run.
				$weekly = false;
				$days   = array();
				foreach ( $blocks as $b ) :
					$sT = strtotime( (string) ( $b['s_date'] ?? '' ) );
					$eT = ! empty($b['e_date']) ? strtotime( (string) $b['e_date'] ) : $sT;
					if ( ! $sT || date('Y-m-d', $eT) !== date('Y-m-d', $sT) ) { $days = array(); break; }
					$days[] = strtotime( date('Y-m-d', $sT) );
				endforeach;
				sort( $days );
				if ( count($days) >= 3 ) :
					$weekly = true;
					for ( $i = 1; $i < count($days); $i++ ) :
						if ( round( ( $days[$i] - $days[$i-1] ) / 86400 ) != 7 ) { $weekly = false; break; }
					endfor;
				endif;

				if ( $sharedTime !== null && $weekly ) :
					$buildMeta .= '<div class="event-date"><span class="date-icon">[get-icon type="calendar"]</span><span class="event-dates-list">';
					$buildMeta .= $fmtWeekly( array( strtolower( date('l', $days[0]) ) ), 1, $days[0], end($days) );
					$buildMeta .= '</span></div><!-- .event-date -->';
					if ( $sharedTime !== '' ) :
						$buildMeta .= '<div class="event-time"><span class="time-icon">[get-icon type="clock"]</span>'.$sharedTime.'</div><!-- .event-time -->';
					endif;
				elseif ( $sharedTime !== null ) :
					$buildMeta .= '<div class="event-date"><span class="date-icon">[get-icon type="calendar"]</span><span class="event-dates-list">';
					$buildMeta .= implode( '<br>', wp_list_pluck( $rows, 'range' ) );
					$buildMeta .= '</span></div><!-- .event-date -->';
					if ( $sharedTime !== '' ) :
						$buildMeta .= '<div class="event-time"><span class="time-icon">[get-icon type="clock"]</span>'.$sharedTime.'</div><!-- .event-time -->';
					endif;
				else :
					foreach ( $rows as $r ) :
						$line = $r['range'];
						if ( $r['time'] ) $line .= '<span class="event-time-inline">'.$r['time'].'</span>';
						$buildMeta .= '<div class="event-date"><span class="date-icon">[get-icon type="calendar"]</span><span class="event-dates-list">'.$line.'</span></div><!-- .event-date -->';
					endforeach;
				endif;

				if ( $currentEvent == false ) $buildMeta .= '<div class="event-passed">(this event has passed)</div>';
			endif;
		endif;

		if ( $venue || $location ) :
			$buildMeta .= '<div class="event-location"><span class="location-icon">[get-icon type="location-pin"]</span>';
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

		return do_shortcode($buildMeta);
	endif;
?>
