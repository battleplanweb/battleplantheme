<?php /*** Single Event Template *** [your-theme]/tribe-events/single-event.php */

if ( ! defined( 'ABSPATH' ) ) die( '-1' );

$events_label_singular = tribe_get_event_label_singular();
$events_label_plural   = tribe_get_event_label_plural();
$event_id = get_the_ID();
$title_classes = apply_filters( 'tribe_events_single_event_title_classes', [ 'tribe-events-single-event-title' ], $event_id );
$title_classes = implode( ' ', tribe_get_classes( $title_classes ) );

$before = apply_filters( 'tribe_events_single_event_title_html_before', '<h1 class="' . $title_classes . '">', $event_id );
$after = apply_filters( 'tribe_events_single_event_title_html_after', '</h1>', $event_id );
$title = apply_filters( 'tribe_events_single_event_title_html', the_title( $before, $after, false ), $event_id );
?>

<!-- Event Header -->
<div class="site-main-inner">
	<article id="<?php echo $event_id; ?>">
	
		<header class="entry-header event-header">
			<?php echo $title; ?>

			<div class="tribe-events-schedule tribe-clearfix">
				<?php echo tribe_events_event_schedule_details( $event_id, '<h2>', '</h2>' ); ?>
				<?php if ( tribe_get_cost() ) : ?>
					<h2><span class="tribe-event-cost"><?php echo tribe_get_cost( null, true ) ?></span></h2>
				<?php endif; ?>
			</div>	

			<!-- Notices -->
			<?php tribe_the_notices() ?>
		</header>
		<!-- .entry-header -->
				
		<!-- do not remove -->		
		<ul style="display:none"><li class="tribe-events-nav-previous"><?php tribe_the_prev_event_link( '<span>&laquo;</span> %title%' ) ?></li><li class="tribe-events-nav-next"><?php tribe_the_next_event_link( '%title% <span>&raquo;</span>' ) ?></li></ul>

		<div class="entry-content event-content">
			<?php while ( have_posts() ) :  the_post(); ?>
				<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<!-- Event featured image, but exclude link -->
					<?php //echo tribe_event_featured_image( $event_id, 'full', false ); ?>

					<!-- Event content -->
					<?php do_action( 'tribe_events_single_event_before_the_content' ) ?>
					<div class="tribe-events-single-event-description tribe-events-content">
						<?php the_content(); ?>
					</div>
					<!-- .tribe-events-single-event-description -->
					<?php do_action( 'tribe_events_single_event_after_the_content' ) ?>

					<!-- Event meta -->
					<?php do_action( 'tribe_events_single_event_before_the_meta' ) ?>
					<?php //tribe_get_template_part( 'modules/meta' ); ?>
					<?php do_action( 'tribe_events_single_event_after_the_meta' ) ?>
				</div> <!-- #post-x -->
				<?php if ( get_post_type() == Tribe__Events__Main::POSTTYPE && tribe_get_option( 'showComments', false ) ) comments_template() ?>
			<?php endwhile; ?>	
		</div>
		<!-- .entry-content -->

		<footer class="entry-footer event-footer">
			<!-- Navigation -->
			<nav class="tribe-events-nav-pagination" aria-label="<?php printf( esc_html__( '%s Navigation', 'the-events-calendar' ), $events_label_singular ); ?>" >
				<ul class="tribe-events-sub-nav">
					<li class="tribe-events-nav-previous"><?php tribe_the_prev_event_link( '<span>&laquo;</span> %title%' ) ?></li>
					<li class="tribe-events-nav-next"><?php tribe_the_next_event_link( '%title% <span>&raquo;</span>' ) ?></li>
				</ul>
				<!-- .tribe-events-sub-nav -->
			</nav>
			
			<?php 
				$buildBtn .= '[btn size="1/2" link="/events/list"]Detailed View[/btn]';
				$buildBtn .= '[btn size="1/2" link="/events/month"]Calendar View[/btn]';
				echo do_shortcode('[col]'.$buildBtn.'[/col]');
			?>
		</footer>
		<!-- .entry-footer -->
		
	</article>
</div>
<!-- .site-main-inner -->