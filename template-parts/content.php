<?php /* Template part for displaying posts */ ?>

	<div class="entry-content">
		
		<?php the_content( sprintf ( wp_kses( __( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'battleplan' ), array( 'span' => array( 'class' => array(), ), ) ), wp_kses_post( get_the_title() ) ) ); ?>
		
	</div><!-- .entry-content -->