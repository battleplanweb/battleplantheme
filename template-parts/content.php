<?php /* Template part for displaying posts */ ?>

	<div class="entry-content">
		
	<?php // added wpautop here instead of globally 2/28/2025
		echo bp_wpautop(get_the_content());  // added wpautop here instead of globally 2/28/2025
	?>
		
	</div><!-- .entry-content -->