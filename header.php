<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/fa-regular-400.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/fa-solid-900.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/fa-brands-400.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/open-sans-v17-latin-regular.woff2" crossorigin="anonymous">
	<link rel="preload" as="font" type="font/woff2" href="../../../wp-content/themes/battleplantheme/fonts/open-sans-v17-latin-700.woff2" crossorigin="anonymous">
	<?php bp_font_loader(); ?>	

	<?php wp_head(); ?>
	<?php bp_google_analytics(); ?>
</head>

<body <?php body_class(); ?>>
	
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
	
<?php wp_nav_menu( array(
	'container'       => 'nav',
	'container_id' 	  => 'mobile-navigation',
	'container_class' => 'main-navigation',
	'menu_id'         => 'mobile-menu',
	'menu_class'	  => 'menu main-menu',
	'walker'          => new Aria_Walker_Nav_Menu(),
) ); ?>			
	
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'battleplan' ); ?></a>

	<header id="masthead">
		
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

		<?php if ( is_front_page() && is_home() ) :
			$page_slug = "site-header-home"; 
			$page_data = get_page_by_path( $page_slug, OBJECT, 'page' );
			if ( !$page_data ) : $page_slug = "site-header"; endif;				
		else: $page_slug = "site-header"; endif;
		$page_data = get_page_by_path( $page_slug, OBJECT, 'page' );
		if ( $page_data ) : echo apply_filters('the_content', $page_data->post_content); endif; ?>

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
	
	<?php $current_page = sanitize_post( $GLOBALS['wp_the_query']->get_queried_object() );
	$page_slug = $current_page->post_name;
	$page_data = get_page_by_path($page_slug."-top", OBJECT, 'page' );
	if ( $page_data && $page_data->post_status == 'publish' ) : echo "<section id='wrapper-top'>".apply_filters('the_content', $page_data->post_content)."</section><!-- #wrapper-top -->"; endif; ?>
	
	<section id="wrapper-content">
		<div id="main-content">
