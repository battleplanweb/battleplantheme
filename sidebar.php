<?php /* The sidebar containing the main widget area */

if ( !is_active_sidebar('sidebar-1') || in_array('sidebar-none', get_body_class()) ) { return; } ?>

<aside id="secondary" class="sidebar widget-area" role="complementary" aria-label="sidebar">

	<?php bp_before_sidebar_inner(); ?>	

	<div class="sidebar-inner">
	
		<?php bp_before_sidebar_widgets(); ?>	
	
		<?php dynamic_sidebar( 'sidebar-1' ); ?>
	
		<?php bp_after_sidebar_widgets(); ?>	
		
	</div><!-- .sidebar-inner -->
	
	<?php bp_after_sidebar_inner(); ?>	

</aside><!-- #secondary -->