<?php
/* Battle Plan Web Design Cue Includes
 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# AJAX Functions
--------------------------------------------------------------*/


// Load Huzzaz
add_shortcode( 'get-huzzaz', 'battleplan_getHuzzaz' );
function battleplan_getHuzzaz( $atts, $content = null ) {
	$a = shortcode_atts( array( 'id'=>'', 'vpp'=>'', 'height'=>'', 'bg'=>'', 'color'=>'', 'button'=>'', 'highlight'=>'' ), $atts );
	$id = esc_attr($a['id']);
	$vpp = esc_attr($a['vpp']);
	$height = esc_attr($a['height']);
	$bg = esc_attr($a['bg']);
	$color = esc_attr($a['color']);
	$button = esc_attr($a['button']);
	$highlight = esc_attr($a['highlight']);

	$buildHuzzaz = '<script>';
	$buildHuzzaz .= 'setTimeout(function() { ';
	$buildHuzzaz .= 'jQuery.post({ url : "/wp-admin/admin-ajax.php", data : { action: "load_cue_mp3", id: "'.$id.'", vpp: "'.$vpp.'", height: "'.$height.'", bg: "'.$bg.'", color: "'.$color.'", button: "'.$button.'", highlight: "'.$highlight.'" }, success: function( response ) { console.log(response); } });';
	$buildHuzzaz .= '}, 10000);';
	$buildHuzzaz .= '</script>';
	
	return $buildHuzzaz;
}



/*--------------------------------------------------------------
# AJAX Functions
--------------------------------------------------------------*/

add_action( 'wp_ajax_load_cue_mp3', 'battleplan_load_cue_mp3_ajax' );
add_action( 'wp_ajax_nopriv_load_cue_mp3', 'battleplan_load_cue_mp3_ajax' );
function battleplan_load_cue_mp3_ajax() {
	$id = $_POST['id'];	
	$vpp = $_POST['vpp'];	
	$height = $_POST['height'];	
	$bg = $_POST['bg'];
	$color = $_POST['color'];
	$button = $_POST['button'];
	$highlight = $_POST['highlight'];
	
	echo do_shortcode('[huzzaz id="'.$id.'" vpp="'.$vpp.'" height="'.$height.'" bg="'.$bg.'" color="'.$color.'" button="'.$button.'" highlight="'.$highlight.'"]');
}

?>