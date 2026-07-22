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
			"img-src " . "'self' data: blob: https:; " . "connect-src " . "'self' https:; " . "frame-src " . "'self' https:; " . "worker-src 'self' blob:; " . "media-src 'self' https:; " . "form-action 'self' https:; " . "base-uri 'self'; " . "frame-ancestors 'self';"
		);
	});

?>

<html lang="en">
<head>
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
		// Head-time preload is emitted ONLY for the site-wide CSS body background (`site-bg`),
		// which genuinely appears on every page and can be the LCP on hero-less pages. The
		// per-page hero/parallax LCP is handled by the cache-safe injector in functions-grid.php
		// (bp_register_hero_preload / bp_inject_hero_preload) — it records the FIRST hero that
		// actually renders in THIS page's body and injects one fetchpriority="high" preload right
		// before </head>. header.php runs before the body, so it can't know the page's real hero;
		// emitting the accumulated bp_preload_images heroes here preloaded the home hero on every
		// page (incl. pages that never show it). So we skip everything but site-bg.
		// Preload the site background RESPONSIVELY: emit both variants with mutually-exclusive
		// media queries so the browser fetches only the one matching the viewport. A device-
		// specific choice here (is_mobile) gets baked into EverCache and served to the wrong
		// device — that's what produced the "preloaded but not used" warnings (a mobile-baked
		// phone preload served to desktop). Media-gated preloads are cache-safe: the non-matching
		// link never fetches, so there's no warning on either device. Breakpoint 576 matches the
		// phone html::before rule in style-site.css.
		$preload_images = get_option('bp_preload_images', []);
		$base_url       = get_site_url() . '/wp-content/uploads/';
		$upload_basedir = wp_upload_dir()['basedir'];

		$bg_variants = array(
			'desktop' => '(min-width: 577px)',
			'mobile'  => '(max-width: 576px)',
		);

		foreach ( $bg_variants as $bg_key => $bg_media ) :
			$site_bg = $preload_images[$bg_key]['site-bg'] ?? null;
			if ( ! $site_bg ) continue;
			$file = is_array($site_bg) ? $site_bg['file'] : $site_bg;

			// Only emit if the file is actually on disk (never preload a 404'd background).
			if ( ! is_file( $upload_basedir . '/' . ltrim($file, '/') ) ) continue;
			?>
			<link
				rel="preload"
				as="image"
				href="<?php echo esc_url( $base_url . ltrim($file, '/') ); ?>"
				media="<?php echo esc_attr( $bg_media ); ?>"
				fetchpriority="high">
			<?php
		endforeach;

	?>

	<link rel="preload" href="<?php echo get_template_directory_uri(); ?>/fonts/opensans-regular.woff2" as="font" type="font/woff2" crossorigin>
	<link rel="preload" href="<?php echo get_template_directory_uri(); ?>/fonts/opensans-bold.woff2" as="font" type="font/woff2" crossorigin>

	<?php wp_head(); ?>

</head>

<body id="<?php echo get_the_ID(); ?>" <?php body_class( battleplan_getUserRole() ); ?>>

<?php bp_loader(); ?>

<?php // Loader dismissal: hold the loader until the WHOLE page has painted (hero + grunge
      // textures + fonts + entrance reveals), so it masks any pop-in / reflow while the page
      // settles. The loader sits ON TOP of the server-rendered hero, which paints beneath it
      // on its own, so how long the loader stays up does NOT affect the measured LCP — zero
      // LCP cost to holding it through the jank. Pure-CSS fade; a hard cap guarantees it can
      // never hang. The mobile-bar buttons hidden above are revealed here too, so this runs
      // even when a site has no #loader (the loader fade is simply skipped in that case). ?>
<style>#loader{transition:opacity .45s ease}#loader.bp-done{opacity:0;pointer-events:none}</style>
<script nonce="<?php echo esc_attr(_BP_NONCE); ?>">(function(){
	var loader=document.getElementById('loader');
	var done=false;
	function hide(){ if(done)return; done=true;
		// reveal the mobile-bar buttons (hidden at opacity:0 above to avoid a pre-JS flash)
		document.querySelectorAll('#mobile-menu-bar .mm-bar-btn').forEach(function(el){ el.style.opacity='1'; });
		if(loader){ loader.classList.add('bp-done'); setTimeout(function(){ loader.style.display='none'; },600); }
	}
	setTimeout(hide,4000); // safety cap: never hang the loader / never leave the buttons hidden
	if(document.readyState==='complete'){ hide(); }
	else{ window.addEventListener('load',hide); }
})();</script>

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