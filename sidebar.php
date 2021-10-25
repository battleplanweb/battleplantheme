<?php /* The sidebar containing the main widget area */

if ( !is_active_sidebar('sidebar-1') || in_array('sidebar-none', get_body_class()) ) { return; } ?>

<aside id="secondary" class="sidebar widget-area" role="complementary" aria-label="sidebar">
	<div class="sidebar-inner">
		<?php dynamic_sidebar( 'sidebar-1' ); ?>
	</div><!-- .sidebar-inner -->
</aside><!-- #secondary -->