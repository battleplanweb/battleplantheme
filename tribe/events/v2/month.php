<?php /*** View: Month View *** [your-theme]/tribe/events/v2/month.php */ ?>

<div <?php tribe_classes( $container_classes ); ?>>

	<main id="primary" class="site-main events-calendar-view" role="main" aria-label="main content">
		<div class="site-main-inner">
			<?php $this->template( 'components/loader', [ 'text' => __( 'Loading...', 'the-events-calendar' ) ] ); ?>
			<?php $this->template( 'components/json-ld-data' ); ?>
			<?php $this->template( 'components/data' ); ?>
			<?php $this->template( 'components/before' ); ?>

			<header class="archive-header events-header">
				<h1 class="page-headline calendar-headline events-headline"><?php echo $formatted_grid_date; ?></h1>
			</header>
			
			<div class="tribe-events-calendar-month" role="grid" aria-labelledby="tribe-events-calendar-header" aria-readonly="true" data-js="tribe-events-month-grid">
				<?php $this->template( 'month/calendar-header' ); ?>
				<?php $this->template( 'month/calendar-body' ); ?>
			</div>
			
			<footer class="archive-footer events-footer">	
				<?php 
					$buildBtn = '';
					if ( $prev_url ) :
						$buildBtn .= '[btn size="1/3" link="'.esc_url( $prev_url ).'" class="calendar-view-prev"]« '.date('M Y',  strtotime(rtrim(substr($prev_url, -8), '/'))).'[/btn]';
					else:
						$buildBtn .= '[group size="1/3" class="calendar-view-prev"][/group]';
					endif;
					$buildBtn .= '[btn size="1/3" link="/events/list" class="calendar-view-detail"]Detailed View[/btn]';
					if ( $next_url ) :
						$buildBtn .= '[btn size="1/3" link="'.esc_url( $next_url ).'" class="calendar-view-next"]'.date('M Y',  strtotime(rtrim(substr($next_url, -8), '/'))).' »[/btn]';
					else:
						$buildBtn .= '[group size="1/3" class="calendar-view-next"][/group]';
					endif;
					echo do_shortcode('[col]'.$buildBtn.'[/col]');
				?>
			</footer>
		</div>
	</main>
</div>