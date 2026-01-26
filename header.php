<!doctype html>
<?php
	add_action('send_headers', function () {
		$nonce = _BP_NONCE;

		header(
			"Content-Security-Policy: " . "default-src 'self'; " .

			// Scripts: nonce + strict-dynamic + fallback for WP/CF
			"script-src " .
			"'self' " .
			"'nonce-$nonce' " .
			"'strict-dynamic' " .
			"https://cdn.jsdelivr.net " .
			"https://cdnjs.cloudflare.com " .
			"https://*.cloudflare.com " .
			"https://*.google.com " .
			"https://*.gstatic.com " .
			"'unsafe-eval'; " .

			// Styles (WP still needs inline styles)
			"style-src " .
			"'self' " .
			"'unsafe-inline' " .
			"https://fonts.googleapis.com " .
			"https://*.cloudflare.com; " .

			// Fonts
			"font-src " .
			"'self' " .
			"https://fonts.gstatic.com " .
			"https://*.cloudflare.com; " .

			// Everything else
			"img-src " . "'self' data: blob: https:; " . "connect-src " . "'self' https:; " . "frame-src " . "'self' https:; " . "worker-src blob:; " . "media-src 'self' https:; " . "form-action 'self' https:; " . "base-uri 'self'; " . "frame-ancestors 'self';"
		);
	});

?>

<html lang="en">
<head>
	<link rel="preconnect" href="https://googletagmanager.com/">
	<?php bp_google_tag_manager(); ?>

	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<script nonce="<?php echo _BP_NONCE; ?>">
		const startTime = Date.now();
		const site_bg = '<?php echo battleplan_fetch_background_image() ?>';
		<?php if ( defined('_USER_DISPLAY_LOC') ) :
			?>const google_ad_location = '<?php echo _USER_DISPLAY_LOC; ?>';
		<?php else:
			?>const google_ad_location = null;<?php
		endif; ?>
		const site_name = '<?php echo get_bloginfo("name") ?>';

		// prevents the TypeError: wp is undefined from showing in console.log
		window.wp = window.wp || {};
		wp.i18n = wp.i18n || {};
		wp.i18n.setLocaleData = () => {};
	</script>

	<style>
		#mobile-menu-bar .mm-bar-btn {
          	opacity:                                       0;
    	 	}
	</style>

	<?php bp_meta_tags(); ?>

	<?php
		$lcp_key = is_mobile() ? 'm-lcp' : 'lcp';

		if ( isset( customer_info()[ $lcp_key ] ) ) :
			$file = customer_info()[ $lcp_key ];

			if ( is_string( $file ) ) :
				$base_url = get_site_url() . '/wp-content/uploads/';
				$url = $base_url . ltrim( $file, '/' );

				$attachment_id = attachment_url_to_postid( $url );

				if ( $attachment_id ) :
					$srcset = wp_get_attachment_image_srcset( $attachment_id, 'full' );
					$sizes  = wp_get_attachment_image_sizes( $attachment_id, 'full' );
					?>
					<link
						rel="preload"
						as="image"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $srcset ? 'imagesrcset="' . esc_attr( $srcset ) . '"' : ''; ?>
						<?php echo $sizes  ? 'imagesizes="'  . esc_attr( $sizes )  . '"' : ''; ?>
						fetchpriority="high">
					<?php
				endif;
			endif;
		endif;
	?>

	<?php wp_head(); ?>

	<?php bp_loader(); ?>

</head>

<body id="<?php echo get_the_ID(); ?>" <?php body_class( battleplan_getUserRole() ); ?>>

<?php wp_body_open(); ?>

<!--div id="mobile-menu-bar-faux"></div-->
<div id="mobile-menu-bar" class="<?php echo is_biz_open() ? 'currently-open' : 'not-currently-open'; ?>">
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

<div id="page" class="site">

	<?php bp_before_masthead(); ?>

	<?php bp_masthead(); ?>

	<?php bp_after_masthead(); ?>

	<?php bp_wrapper_top(); ?>

	<?php bp_before_wrapper_content(); ?>

	<main id="wrapper-content">

		<?php bp_before_main_content(); ?>

		<div id="main-content">