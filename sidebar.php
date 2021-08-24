<?php /* The sidebar containing the main widget area */

if ( ! is_active_sidebar( 'sidebar-1' ) ) { return; }
?>

<aside id="secondary" class="sidebar widget-area">
	<div class="sidebar-inner">
		<?php dynamic_sidebar( 'sidebar-1' ); ?>
	</div><!-- .sidebar-inner -->
</aside><!-- #secondary -->