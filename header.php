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
		$preload_images = get_option('bp_preload_images', []);
		$preload_key    = is_mobile() ? 'mobile' : 'desktop';
		$base_url       = get_site_url() . '/wp-content/uploads/';

		if ( !empty($preload_images[$preload_key]) ) :
			$has_parallax = false;
			foreach ( $preload_images[$preload_key] as $key => $entry ) :
				if ( $key !== 'site-bg' ) $has_parallax = true;
			endforeach;

			$first          = true;
			$pruned         = false;
			$upload_basedir = wp_upload_dir()['basedir'];
			foreach ( $preload_images[$preload_key] as $key => $entry ) :
				$file          = is_array($entry) ? $entry['file'] : $entry;
				$attachment_id = is_array($entry) ? ( $entry['attachment_id'] ?? 0 ) : 0;
				$url           = $base_url . ltrim($file, '/');

				// bp_preload_images is an accumulate-only cache: an image later deleted or renamed
				// leaves a ghost entry that would preload a 404. Skip AND drop any entry whose file
				// no longer exists on disk (self-heals the option on the next uncached render).
				if ( ! is_file( $upload_basedir . '/' . ltrim($file, '/') ) ) :
					unset( $preload_images[$preload_key][$key] );
					$pruned = true;
					continue;
				endif;

				$density       = ( is_mobile() && is_array($entry) && !empty($entry['srcset']) );
					$srcset        = $density ? $entry['srcset'] : ( ( !is_mobile() && $attachment_id ) ? wp_get_attachment_image_srcset($attachment_id, 'full') : '' );
				// Drop candidates whose files are missing (e.g. a removed image crop still
				// listed in attachment metadata) so we never preload a 404'd LCP image.
				if ( $srcset ) $srcset = bp_prune_missing_srcset( $srcset );

				if ( $key === 'site-bg' ) :
					$priority = $has_parallax ? 'low' : 'high';
				else :
					$priority = $first ? 'high' : 'low';
					$first    = false;
				endif;
				?>
				<link
					rel="preload"
					as="image"
					href="<?php echo esc_url($url); ?>"
					<?php echo $srcset ? 'imagesrcset="' . esc_attr($srcset) . '"' : ''; ?>
					<?php echo ( $srcset && ! $density ) ? 'imagesizes="100vw"' : ''; ?>
					fetchpriority="<?php echo $priority; ?>">
				<?php
			endforeach;

			// Persist the ghost-entry cleanup once (only when something was actually dropped).
			if ( $pruned ) update_option( 'bp_preload_images', $preload_images, true );
		endif;

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