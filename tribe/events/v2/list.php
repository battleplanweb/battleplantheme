<?php /*** View: List View *** [your-theme]/tribe/events/v2/list.php*/ ?>

<main id="primary" class="site-main events-detailed-view" role="main" aria-label="main content">
	<div class="site-main-inner">
		<?php $this->template( 'components/loader', [ 'text' => __( 'Loading...', 'the-events-calendar' ) ] ); ?>
		<?php $this->template( 'components/json-ld-data' ); ?>
		<?php $this->template( 'components/data' ); ?>
		<?php $this->template( 'components/before' ); ?>

		<header class="archive-header events-header">
			<?php 
				$eventType = do_shortcode('[get-url-var var="eventDisplay"]');
				$numEvents = count($events);
				$eventLabel = "";

				if ( $numEvents < 1 ) $eventLabel .= "No ";			
				if ( strtolower($eventType) == "past" ) :
					$eventLabel .= "Past ";
				else:
					$eventLabel .= "Upcoming ";
				endif;
				$eventLabel .= "Event";
				if ( $numEvents != 1 ) $eventLabel .= "s";

				echo '<h1 class="page-headline archive-headline events-headline">'.$eventLabel.'</h1>';
			?>
		</header>

		<?php //$this->template( 'components/filter-bar' ); ?>

		<section class="section section-inline archive-content archive-events">
			<div class="flex grid-1 valign-start">
				<?php
					foreach ( $events as $event ) : 
						$this->setup_postdata( $event ); 
						$this->template( 'list/month-separator', [ 'event' => $event ] );
						$this->template( 'list/event', [ 'event' => $event ] ); 
					endforeach; 
				?>
			</div>
		</section>
		
		<footer class="archive-footer events-footer">
			<?php 
				$buildBtn = '';
				if ( strtolower($eventType) != "past" ) $buildBtn .= '[btn size="1/2" link="/events/list?eventDisplay=past"]« Past Events[/btn]';
				$buildBtn .= '[btn size="1/2" link="/events/month"]Calendar View[/btn]';
				if ( strtolower($eventType) == "past" ) $buildBtn .= '[btn size="1/2" link="/events/list"]Upcoming Events »[/btn]';
				echo do_shortcode('[col]'.$buildBtn.'[/col]');
			?>
		</footer>		
	</div>
</main>