<?php
/**
 * Battle Plan SEO — Yoast importer  (admin only)
 * ---------------------------------------------------------------------------
 * One-click, per-site copy of Yoast's per-post + per-term SEO data into the
 * clean-break _bp_seo_* namespace. Copy-only — it NEVER deletes Yoast meta, so a
 * site stays fully reversible (reactivate Yoast and its data is untouched).
 *
 * Run it WHILE Yoast is still installed (its data lives in the DB either way, but
 * this is the natural order): import → verify parity → deactivate Yoast. Because
 * this module mirrors Yoast's %%var%% token syntax, fields copy across raw — no
 * template rewriting needed; the live resolver understands the same tokens.
 *
 * Tools → Import Yoast SEO. Dry-run first (counts + flags unknown tokens), then run.
 */

if ( ! defined('ABSPATH') ) exit;

/** Yoast post meta_key → our meta key (plain string fields). */
function bp_seo_import_field_map(): array {
	return [
		'_yoast_wpseo_title'                 => BP_SEO_TITLE,
		'_yoast_wpseo_metadesc'              => BP_SEO_DESC,
		'_yoast_wpseo_canonical'             => BP_SEO_CANONICAL,
		'_yoast_wpseo_opengraph-title'       => BP_SEO_OG_TITLE,
		'_yoast_wpseo_opengraph-description' => BP_SEO_OG_DESC,
		'_yoast_wpseo_opengraph-image'       => BP_SEO_OG_IMAGE,
		'_yoast_wpseo_primary_category'      => BP_SEO_PRIMARYCAT,
	];
}

/** Robots fields need value translation, not a raw copy. */
function bp_seo_import_robots_map(): array {
	return [
		'_yoast_wpseo_meta-robots-noindex'  => BP_SEO_NOINDEX,   // Yoast '1' = noindex (2 = force index)
		'_yoast_wpseo_meta-robots-nofollow' => BP_SEO_NOFOLLOW,  // Yoast '1' = nofollow
	];
}

/** Tokens the live resolver understands (used only to flag anything unexpected). */
function bp_seo_known_tokens(): array {
	return [
		'%%title%%','%%sitename%%','%%sitedesc%%','%%sep%%','%%page%%','%%pagenumber%%',
		'%%pagetotal%%','%%name%%','%%date%%','%%searchphrase%%','%%primary_category%%',
		'%%category%%','%%pt_plural%%','%%pt_single%%','%%currentyear%%','%%currentdate%%',
	];
}

/**
 * Core import routine.
 * @param bool $dry  When true, count only — write nothing.
 * @return array report
 */
function bp_seo_import_run( bool $dry = true ): array {
	global $wpdb;

	$fieldMap  = bp_seo_import_field_map();
	$robotsMap = bp_seo_import_robots_map();
	$allKeys   = array_merge( array_keys($fieldMap), array_keys($robotsMap) );

	$report = [
		'posts_touched'  => 0,
		'fields'         => array_fill_keys( array_merge( array_values($fieldMap), array_values($robotsMap) ), 0 ),
		'unknown_tokens' => [],   // token => sample post title
		'terms_touched'  => 0,
		'term_fields'    => [ BP_SEO_TITLE => 0, BP_SEO_DESC => 0, BP_SEO_NOINDEX => 0 ],
		'dry'            => $dry,
	];

	// ---- Posts: pull all relevant Yoast meta in one query, grouped by post ----
	$placeholders = implode( ',', array_fill( 0, count($allKeys), '%s' ) );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders)",
			$allKeys
		)
	);

	$byPost = [];
	foreach ( (array) $rows as $row ) {
		$byPost[ (int) $row->post_id ][ $row->meta_key ] = $row->meta_value;
	}

	$known = bp_seo_known_tokens();
	foreach ( $byPost as $post_id => $metas ) {
		$touched = false;

		foreach ( $fieldMap as $ykey => $ourKey ) {
			if ( ! isset($metas[$ykey]) ) continue;
			$val = (string) $metas[$ykey];
			if ( trim($val) === '' ) continue;

			// Flag any %%token%% we don't resolve (informational only).
			if ( preg_match_all('/%%[^%]+%%/', $val, $m) ) {
				foreach ( $m[0] as $tok ) {
					if ( ! in_array($tok, $known, true) && ! isset($report['unknown_tokens'][$tok]) ) {
						$report['unknown_tokens'][$tok] = get_the_title($post_id) ?: ('#' . $post_id);
					}
				}
			}

			$report['fields'][$ourKey]++;
			$touched = true;
			if ( ! $dry ) update_post_meta( $post_id, $ourKey, $val );
		}

		foreach ( $robotsMap as $ykey => $ourKey ) {
			if ( (string) ( $metas[$ykey] ?? '' ) !== '1' ) continue; // only copy an actual noindex/nofollow
			$report['fields'][$ourKey]++;
			$touched = true;
			if ( ! $dry ) update_post_meta( $post_id, $ourKey, '1' );
		}

		if ( $touched ) $report['posts_touched']++;
	}

	// ---- Terms: Yoast stores taxonomy SEO in the wpseo_taxonomy_meta option ----
	$taxMeta = get_option('wpseo_taxonomy_meta');
	if ( is_array($taxMeta) ) {
		foreach ( $taxMeta as $taxonomy => $terms ) {
			if ( ! is_array($terms) ) continue;
			foreach ( $terms as $term_id => $meta ) {
				$term_id = (int) $term_id;
				if ( ! $term_id || ! is_array($meta) ) continue;
				$touched = false;

				if ( ! empty($meta['wpseo_title']) ) {
					$report['term_fields'][BP_SEO_TITLE]++; $touched = true;
					if ( ! $dry ) update_term_meta( $term_id, BP_SEO_TITLE, (string) $meta['wpseo_title'] );
				}
				if ( ! empty($meta['wpseo_desc']) ) {
					$report['term_fields'][BP_SEO_DESC]++; $touched = true;
					if ( ! $dry ) update_term_meta( $term_id, BP_SEO_DESC, (string) $meta['wpseo_desc'] );
				}
				if ( ( $meta['wpseo_noindex'] ?? '' ) === 'noindex' ) {
					$report['term_fields'][BP_SEO_NOINDEX]++; $touched = true;
					if ( ! $dry ) update_term_meta( $term_id, BP_SEO_NOINDEX, '1' );
				}

				if ( $touched ) $report['terms_touched']++;
			}
		}
	}

	if ( ! $dry ) update_option( 'bp_seo_imported', gmdate('c'), false );
	return $report;
}

/**
 * Email a migration summary (used by the run-once housekeeping cutover). Recipient
 * is filterable; defaults to the Battle Plan dev inbox so the whole fleet reports
 * to one place. Subject carries the site + headline counts so 130 emails sort cleanly.
 *
 * @param array  $report      return of bp_seo_import_run(false)
 * @param int    $redirects   count imported by bp_seo_import_yoast_redirects()
 * @param array  $deactivated Yoast plugin files that were deactivated
 * @param bool   $robots_phys whether a physical /robots.txt is shadowing the module's rules
 */
function bp_seo_email_migration_report( array $report, int $redirects, array $deactivated, bool $robots_phys ): void {
	$site  = get_bloginfo('name');
	$url   = get_bloginfo('url');
	$to    = apply_filters( 'bp_seo_migration_email', 'email@bp-webdev.com' );

	$tokens = $report['unknown_tokens'] ?? [];
	$flag   = ! empty($tokens) || $robots_phys ? ' ⚠' : '';

	$subject = sprintf( 'SEO migration%s: %s — %d pages, %d redirects',
		$flag, $site, (int) ( $report['posts_touched'] ?? 0 ), $redirects );

	$L   = [];
	$L[] = "Battle Plan SEO migration complete — Yoast deactivated.";
	$L[] = "";
	$L[] = "Site:      {$site}";
	$L[] = "URL:       {$url}";
	$L[] = "Posts:     " . (int) ( $report['posts_touched'] ?? 0 ) . " page(s) with SEO data imported";
	$L[] = "Terms:     " . (int) ( $report['terms_touched'] ?? 0 ) . " term(s) imported";
	$L[] = "Redirects: {$redirects} imported from Yoast";
	$L[] = "Deactivated: " . implode( ', ', $deactivated );
	$L[] = "";
	foreach ( (array) ( $report['fields'] ?? [] ) as $k => $c ) $L[] = sprintf('  %-28s %d', $k, $c);

	if ( ! empty($tokens) ) {
		$L[] = "";
		$L[] = "⚠ Unrecognized %%tokens%% (these pages will render the token literally — review):";
		foreach ( $tokens as $tok => $sample ) $L[] = "  {$tok}  — e.g. \"{$sample}\"";
	}
	if ( $robots_phys ) {
		$L[] = "";
		$L[] = "⚠ A physical /robots.txt exists on this site, so the module's robots rules";
		$L[] = "  (Disallow wp-json/search + Sitemap line) are NOT applied. Delete the file";
		$L[] = "  to let WordPress serve the virtual robots.txt.";
	}
	if ( empty($tokens) && ! $robots_phys ) {
		$L[] = "";
		$L[] = "✓ No token or robots.txt issues flagged.";
	}

	wp_mail( $to, $subject, implode( "\n", $L ) );
}

/*--------------------------------------------------------------
# Admin page  (Tools → Import Yoast SEO)
--------------------------------------------------------------*/
add_action('admin_menu', function() {
	add_management_page( 'Import Yoast SEO', 'Import Yoast SEO', 'manage_options', 'bp-seo-import', 'bp_seo_import_admin_page' );
});

function bp_seo_import_admin_page() {
	if ( ! current_user_can('manage_options') ) return;

	$report = null;
	$ranLive = false;
	if ( isset($_POST['bp_seo_import_go']) && check_admin_referer('bp_seo_import') ) {
		$dry     = empty($_POST['bp_seo_import_live']);
		$report  = bp_seo_import_run( $dry );
		$ranLive = ! $dry;
	}

	$last = get_option('bp_seo_imported');
	?>
	<div class="wrap">
		<h1>Import Yoast SEO</h1>
		<p>Copies per-post and per-term Yoast fields (<code>_yoast_wpseo_*</code> + <code>wpseo_taxonomy_meta</code>) into Battle Plan SEO's <code>_bp_seo_*</code> storage. <strong>Copy-only</strong> — nothing in Yoast is deleted, so you can re-run it safely and reactivate Yoast at any time.</p>
		<?php if ( $last ) : ?><p><em>Last live import: <?php echo esc_html($last); ?></em></p><?php endif; ?>

		<form method="post">
			<?php wp_nonce_field('bp_seo_import'); ?>
			<p>
				<label><input type="checkbox" name="bp_seo_import_live" value="1"> Run the live import (leave unchecked for a dry-run preview)</label>
			</p>
			<p><button type="submit" name="bp_seo_import_go" class="button button-primary">Analyze / Import</button></p>
		</form>

		<?php if ( $report ) : ?>
			<hr>
			<h2><?php echo $ranLive ? 'Import complete' : 'Dry run — nothing was written'; ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<tbody>
					<tr><td><strong>Posts with SEO data</strong></td><td><?php echo (int) $report['posts_touched']; ?></td></tr>
					<?php foreach ( $report['fields'] as $key => $count ) : ?>
						<tr><td><?php echo esc_html($key); ?></td><td><?php echo (int) $count; ?></td></tr>
					<?php endforeach; ?>
					<tr><td><strong>Terms with SEO data</strong></td><td><?php echo (int) $report['terms_touched']; ?></td></tr>
					<?php foreach ( $report['term_fields'] as $key => $count ) : ?>
						<tr><td>term <?php echo esc_html($key); ?></td><td><?php echo (int) $count; ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( ! empty($report['unknown_tokens']) ) : ?>
				<h3>⚠ Unrecognized template tokens</h3>
				<p>These <code>%%tokens%%</code> appeared in Yoast fields but aren't resolved by Battle Plan SEO. Review these pages after import (they'll render the token literally):</p>
				<ul style="list-style:disc;margin-left:20px">
					<?php foreach ( $report['unknown_tokens'] as $tok => $sample ) : ?>
						<li><code><?php echo esc_html($tok); ?></code> — e.g. on “<?php echo esc_html($sample); ?>”</li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( ! $ranLive ) : ?>
				<p>✓ No unrecognized tokens — every Yoast field uses variables this module already resolves.</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
