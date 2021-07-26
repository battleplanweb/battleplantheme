<!doctype html>
<html <?php language_attributes(); ?>>
<head>	
	<script type="text/javascript">var startTime = Date.now();</script>	
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/open-sans-v17-latin-regular.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/open-sans-v17-latin-700.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/fa-regular-400.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/fa-solid-900.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/fa-brands-400.woff2" crossorigin="anonymous">
	<?php bp_font_loader(); ?>	

	<?php wp_head(); ?>
	<?php bp_google_analytics(); ?>
</head>

<body <?php body_class(getUserRole()); ?>>
	
<?php bp_loader(); ?>
<?php wp_body_open(); ?>
	
<div id="mobile-menu-bar-faux"></div>
	
<div id="mobile-menu-bar">
	<a class="scroll-top" href="#page"><div class="mm-bar-btn scroll-to-top-btn" aria-hidden="true"></div><span class="sr-only">Scroll To Top</span></a>
	<?php bp_mobile_menu_bar_items(); ?>
	<?php echo do_shortcode('[get-biz info="mm-bar-link"]') ?>
	<a href="/contact-us/"><div class="mm-bar-btn email-btn" aria-hidden="true"></div><span class="sr-only">Contact Us</span></a>
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
	
<a class="skip-link sr-only" href="#primary"><?php esc_html_e( 'Skip to content', 'battleplan' ); ?></a>

<?php echo do_shortcode('[get-element slug="site-message"]'); ?>
	
<div id="page" class="site" aria-label="page">
	<header id="masthead" role="banner" aria-label="header">
		
		<?php if ( has_nav_menu( 'top-menu', 'battleplan' ) ) : ?>
			<nav id="desktop-navigation" class="main-navigation menu-strip" aria-label="Main Menu">
				<?php wp_nav_menu(
					array(
						'container'       => 'div',
						'container_class' => 'flex',
						'menu_id'         => 'top-menu',
						'menu_class'	  => 'menu main-menu',
						'theme_location'  => 'top-menu',
						'walker'          => new Aria_Walker_Nav_Menu(),
					)
				); ?>
			</nav><!-- #site-navigation -->
		<?php endif; ?>

		<?php echo do_shortcode('[get-element slug="site-header"]'); ?>
		
		<?php if ( has_nav_menu( 'header-menu', 'battleplan' ) ) : ?>
			<nav id="desktop-navigation" class="main-navigation menu-strip" aria-label="Main Menu">
				<?php wp_nav_menu(
					array(
						'container'       => 'div',
						'container_class' => 'flex',
						'menu_id'         => 'header-menu',
						'menu_class'	  => 'menu main-menu',
						'theme_location'  => 'header-menu',
						'walker'          => new Aria_Walker_Nav_Menu(),
					)
				); ?>
			</nav><!-- #site-navigation -->
		<?php endif; ?>
		
	</header><!-- #masthead -->
	
	<?php bp_after_masthead(); ?>
	
	<?php	
	$current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	$textarea = get_post_meta( $current_page->ID, 'page-top_text', true );
 	if ( $textarea != "" ) : echo "<section id='wrapper-top'>".apply_filters('the_content', $textarea)."</section><!-- #wrapper-top -->"; endif;
	?>
	
	<section id="wrapper-content">
		<div id="main-content">