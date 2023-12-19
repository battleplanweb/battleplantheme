<!doctype html>
<?php 
	$nonce = _BP_NONCE;
	if ( get_option('disable-content-security-policy') != 'true' ) :
		////header( "Content-Security-Policy: script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval'; object-src 'none'; base-uri 'none'; block-all-mixed-content" ); 
		//header( "Strict-Transport-Security: max-age=63072000; includeSubDomains; preload" );
		//header( "X-Frame-Options: SAMEORIGIN" );
		//header( "X-Content-Type-Options: nosniff" );
		//header( "Referrer-Policy: strict-origin-when-cross-origin" );
	endif;
?>
 
<html <?php language_attributes(); ?>>
<head>	
	<script nonce="<?php echo _BP_NONCE; ?>" type="text/javascript">
		var startTime = Date.now();		
		<?php if ( _USER_LOCATION != "none" ) :
			?>var site_loc = '<?php echo strtolower(str_replace(array(', ', ' '), array('-', '-'), _USER_LOCATION)); ?>';
		<?php else:
			?>var site_loc = null;<?php 
		endif; ?>	
	</script>	
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	
	<link rel="preload" as="font" type="font/woff2" href="<?php echo get_site_url() ?>/wp-content/themes/battleplantheme/fonts/open-sans-v17-latin-regular.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="<?php echo get_site_url() ?>/wp-content/themes/battleplantheme/fonts/fa-solid-900.woff2" crossorigin="anonymous">
	<link rel="preconnect" href="https://googletagmanager.com/">

	<?php bp_font_loader(); ?>	

	<?php wp_head(); ?>
	
	<?php bp_google_tag_manager(); ?>
</head>

<body id="<?php echo get_the_ID(); ?>" <?php body_class( battleplan_getUserRole() ); ?>>
	 
<?php bp_loader(); ?>

<?php wp_body_open(); ?>
	
<div id="mobile-menu-bar-faux"></div>	
<div id="mobile-menu-bar" class="<?php echo do_shortcode('[get-hours-open open="currently-open" closed="not-currently-open"]'); ?>"</div>
	<?php //bp_mobile_menu_bar_items(); ?>
	<?php bp_mobile_menu_bar_contact(); ?>	
	<?php bp_mobile_menu_bar_phone(); ?>
	<?php if ( !is_biz_open() ) echo '<div class="hide-2 hide-3 hide-4 hide-5"></div>'; ?>
	<?php bp_mobile_menu_bar_scroll(); ?>
	<?php bp_mobile_menu_bar_activate(); ?>
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

<a class="skip-link sr-only" href="#primary"><?php esc_html_e( 'Skip to content', 'battleplan' ); ?></a>
	
<?php bp_before_page(); ?>
	
<div id="page" class="site" aria-label="page">

	<?php bp_before_masthead(); ?>

	<?php bp_masthead(); ?>
	
	<?php bp_after_masthead(); ?>

	<?php bp_wrapper_top(); ?>
	
	<?php bp_before_wrapper_content(); ?>
	
	<section id="wrapper-content">
	
		<?php bp_before_main_content(); ?>
		
		<div id="main-content">