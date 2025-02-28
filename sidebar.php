<?php /* The sidebar containing the main widget area */

if ( in_array('sidebar-none', get_body_class()) ) { return; } ?>

<aside id="secondary" class="sidebar widget-area" aria-label="sidebar">

	<?php bp_before_sidebar_inner(); ?>	

	<div class="sidebar-inner"><?php 
	
		bp_before_sidebar_widgets();	
		
		if ( is_null(get_page_by_path('widgets', OBJECT, 'elements')) ) :
			dynamic_sidebar( 'sidebar-1' ); 
		else:		
			echo do_shortcode('[get-element slug="widgets"]');
		endif;
	
		bp_after_sidebar_widgets(); 	
		
	?></div><!-- .sidebar-inner -->
	
	<?php bp_after_sidebar_inner(); ?>	

</aside><!-- #secondary -->