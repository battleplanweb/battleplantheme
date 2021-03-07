<?php
/* The template for displaying 404 pages (not found) */

$getURL = str_replace("/", "", $_SERVER['REQUEST_URI']);
$filename = "wp-content/themes/battleplantheme/includes/includes-".$getURL.".php";
if ( !file_exists($filename) ) $filename = "wp-content/themes/battleplantheme/includes/includes-".get_option('site_type')."-".$getURL.".php";

if ( file_exists($filename) ) :
	$class = 'include-page '.$getURL;
	add_filter( 'document_title_parts', 'battleplan_change_title', 11 );

	function battleplan_change_title( $title_parts ) {
		$getURL = str_replace("/", "", $_SERVER['REQUEST_URI']);
		$getURL = str_replace("-", " ", $getURL);
		$title_parts['title'] = ucwords($getURL);
		return $title_parts;
	} 

	$content = do_shortcode(include $filename);
else:

	$class = 'error-404 not-found';

	$content = '<h1>Sorry!  We can\'t find that page.</h1>';
	$content .= '<p>The page you are looking for does not exist, or has been removed.</p>';
	$content .= '<p>Please try using the menu options to navigate the site, or use the form below to contact us.</p>';
	$content .= do_shortcode('[contact-form-7 title="Contact Us Form"]');
endif; 

get_header();
?>
	<main id="primary" class="site-main">
		<article class="<?php echo $class;?>">
			
			<?php echo $content;?>

		</article><!-- <?php echo $class;?> -->
	</main><!-- #primary -->
<?php 
get_footer();
?>