<?php /*** View: List Event *** [your-theme]/tribe/events/v2/list/event.php */

$event_id = get_the_ID(); ?>

<div class="col col-archive col-events col-<?php echo $event_id; ?>">
	<div class="col-inner">
	
		<div class="block block-image span-4 image-events">	
			<?php if ( has_post_thumbnail() ) echo get_the_post_thumbnail( $event_id, 'thumbnail'); ?>
		</div>
		
		<div class="block block-group span-8 group-events">
			<div class="block block-text span-12 text-events">
			
				<h3 aria-label="<?php echo get_the_title(); ?>">
					<a class="link-archive link-events" href="<?php echo get_the_permalink(); ?>" aria-hidden="true" tabindex="-1"><?php echo get_the_title(); ?></a>
				</h3>
				
				<div class="event-meta">
					<?php $this->template( 'list/event/date', [ 'event' => $event ] ); ?>
					<?php $this->template( 'list/event/cost', [ 'event' => $event ] ); ?>
					<?php //$this->template( 'list/event/venue', [ 'event' => $event ] ); ?>
				</div>

				<div class="event-description">
					<?php $this->template( 'list/event/description', [ 'event' => $event ] ); ?>
				</div>
			</div>
			
			<div class="block block-button span-12 button-events">
				<?php echo do_shortcode('[btn link="'.get_the_permalink().'"]Learn More[/btn]'); ?>			
			</div>
		</div>
	</div>
</div>