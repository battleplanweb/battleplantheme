<!doctype html>
<?php 
	$GLOBALS['nonce'] = base64_encode(random_bytes(20));
	$nonce = $GLOBALS['nonce'];
	header( "Content-Security-Policy: script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-inline' 'unsafe-eval' https: http:; object-src 'none'; base-uri 'none'; block-all-mixed-content" ); 
	header( "Strict-Transport-Security: max-age=63072000; includeSubDomains; preload" );
	header( "X-Frame-Options: SAMEORIGIN" );
	header( "X-Content-Type-Options: nosniff" );
	header( "Referrer-Policy: strict-origin-when-cross-origin" );
?>
 
<html <?php language_attributes(); ?>>
<head>	
	<script nonce="<?php echo $nonce; ?>" type="text/javascript">var startTime = Date.now();</script>	
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	
	<link rel="preload" as="font" type="font/woff2" href="<?php echo get_site_url() ?>/wp-content/themes/battleplantheme/fonts/open-sans-v17-latin-regular.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="<?php echo get_site_url() ?>/wp-content/themes/battleplantheme/fonts/open-sans-v17-latin-700.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="<?php echo get_site_url() ?>/wp-content/themes/battleplantheme/fonts/fa-regular-400.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="<?php echo get_site_url() ?>/wp-content/themes/battleplantheme/fonts/fa-solid-900.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="<?php echo get_site_url() ?>/wp-content/themes/battleplantheme/fonts/fa-brands-400.woff2" crossorigin="anonymous">
	<?php bp_font_loader(); ?>	

	<?php wp_head(); ?>
	
	<?php bp_google_tag_manager(); ?>
</head>

<body id="<?php echo get_the_ID(); ?>" data-unique-id="<?php echo $_COOKIE['unique-id']; ?>" data-pageviews="<?php echo $_COOKIE['pages-viewed']; ?>" <?php body_class(getUserRole()); ?>>
	
<?php bp_loader(); ?>

<?php wp_body_open(); ?>
	
<div id="mobile-menu-bar-faux"></div>
	
<div id="mobile-menu-bar">
	<a class="scroll-top" href="#page"><div class="mm-bar-btn scroll-to-top-btn" aria-hidden="true"></div><span class="sr-only">Scroll To Top</span></a>
	<?php bp_mobile_menu_bar_items(); ?>
	
	<?php echo do_shortcode('[get-biz info="mm-bar-link"]');	
	
	if ( get_page_by_title( 'Quote Request Form', OBJECT, 'wpcf7_contact_form' ) ) : $form = "Quote Request Form"; $title = "Request A Quote";
	elseif ( get_page_by_title( 'Contact Us Form', OBJECT, 'wpcf7_contact_form' ) ) : $form = "Contact Us Form"; $title = "Send A Message"; endif;	
	if ( $form && $title ) echo '<div class="mm-bar-btn modal-btn"><div class="email-btn" aria-hidden="true"></div><div class="email2-btn" aria-hidden="true"></div><span class="sr-only">Contact Us</span></div>';	?>
	
	<div class="mm-bar-btn activate-btn"><div></div><div></div><div></div></div> 
</div>
	
<?php $mainMenuLoc = ''; 
if ( has_nav_menu( 'header-menu', 'battleplan' ) ) $mainMenuLoc = 'header-menu';
if ( has_nav_menu( 'top-menu', 'battleplan' ) ) $mainMenuLoc = 'top-menu';
if ( has_nav_menu( 'widget-menu', 'battleplan' ) ) $mainMenuLoc = 'widget-menu';
	
wp_nav_menu( array(
	'container'       => 'nav',
	'container_id' 	  => 'mobile-navigation',
	'container_class' => 'main-navigation',
	'menu_id'         => 'mobile-menu',
	'menu_class'	  => 'menu main-menu',
	'theme_location'  => $mainMenuLoc,
	'walker'          => new Aria_Walker_Nav_Menu(),
) ); ?>	
	
<?php if ( $form && $title ) echo do_shortcode('[lock name="request-quote-modal" style="lock" position="modal" show="always" btn-activated="yes"][layout]<h3>'.$title.'</h3>[contact-form-7 title="'.$form.'"][/layout][/lock]'); ?>

<a class="skip-link sr-only" href="#primary"><?php esc_html_e( 'Skip to content', 'battleplan' ); ?></a>
	
<div id="page" class="site" aria-label="page">

	<?php bp_before_masthead(); ?>

	<?php bp_masthead(); ?>
	
	<?php bp_after_masthead(); ?>

	<?php bp_wrapper_top(); ?>
	
	<?php bp_before_wrapper_content(); ?>
	
	<section id="wrapper-content">
	
		<?php bp_before_main_content(); ?>
		
		<div id="main-content">