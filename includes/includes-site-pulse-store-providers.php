<?php
/* Battle Plan Web Design — Site Pulse: Store Payment Providers

/*--------------------------------------------------------------
Which company moves the money, and how the checkout hands off to them.

The store computes its own subtotal, shipping, tax and total (see
includes-site-pulse-store-totals.php). A provider's job begins after that: take
an amount that has already been decided and collect it. Everything that is NOT
provider-specific — orders, stock, webhook idempotency, the abandoned-checkout
sweep — stays out of here.

PROVIDERS COME IN TWO SHAPES, and the interface models both because every real
processor offers both and a one-shape abstraction would have fitted exactly one
of them:

  redirect   send the shopper to a page the provider hosts  (Stripe Checkout)
  embedded   mount the provider's own iframe in our page    (Clover)

Card fields live in the PROVIDER's document in both shapes. Nothing here, and
nothing in the checkout page, may read, wrap or proxy a card number — that is
what keeps the site in PCI SAQ-A rather than a much longer conversation.

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# The Registry
# Capabilities
# Beginning a Payment
# Webhooks
--------------------------------------------------------------*/

if ( ! defined( 'ABSPATH' ) ) exit;


/*--------------------------------------------------------------
# The Registry
--------------------------------------------------------------*/

/**
 * Every payment provider the store knows how to drive.
 *
 *   shape     'redirect' or 'embedded' — how the checkout hands off
 *   ready     callable: is this provider configured on this site?
 *   begin     callable( int $order_id ): array|WP_Error — the handoff
 *   webhook   callable(): void — verifies and handles a posted webhook request
 *   refund    callable( array $order, int $cents, string $note ): array|WP_Error —
 *             sends money back and returns [ id, amount, status ]. Optional: a
 *             provider without one is refunded in its own dashboard.
 *   does_tax  can the provider calculate sales tax itself?
 *
 * Filterable so a site or a future module can register another one without
 * touching this file.
 */
function sp_store_providers(): array {
	$providers = [
		'stripe' => [
			'label'    => 'Stripe',
			'shape'    => 'redirect',
			'ready'    => 'sp_store_stripe_ready',
			'begin'    => 'sp_store_stripe_begin',
			'webhook'  => 'sp_store_handle_webhook',
			'refund'   => 'sp_store_stripe_refund',
			'does_tax' => true,
			'note'     => 'Recommended. Handles tax calculation itself if you want it to, and the shopper pays on a page Stripe hosts.',
		],
	];

	return (array) apply_filters( 'sp_store_providers', $providers );
}

/** The provider this site uses. Stripe unless a site has deliberately changed it. */
function sp_store_provider(): string {
	$id = (string) sp_store_get_setting( 'payment_provider', 'stripe' );
	return isset( sp_store_providers()[ $id ] ) ? $id : 'stripe';
}

/** One provider's definition, defaulting to the active one. */
function sp_store_provider_def( string $id = '' ): array {
	$providers = sp_store_providers();
	$id        = $id ?: sp_store_provider();
	return $providers[ $id ] ?? ( $providers['stripe'] ?? [] );
}

/** Is the provider configured enough to take money? */
function sp_store_provider_ready( string $id = '' ): bool {
	$def = sp_store_provider_def( $id );
	$fn  = $def['ready'] ?? '';
	return is_callable( $fn ) ? (bool) call_user_func( $fn ) : false;
}

/** 'redirect' or 'embedded'. */
function sp_store_provider_shape( string $id = '' ): string {
	return ( sp_store_provider_def( $id )['shape'] ?? 'redirect' ) === 'embedded' ? 'embedded' : 'redirect';
}


/*--------------------------------------------------------------
# Capabilities
--------------------------------------------------------------*/

/**
 * Can the active provider work out sales tax?
 *
 * This gates the "let the payment processor do it" tax option. Clover cannot,
 * and a store set to provider-calculated tax with a provider that does not
 * calculate it would quietly charge none at all — the kind of fault nobody
 * notices until an accountant does.
 */
function sp_store_provider_does_tax( string $id = '' ): bool {
	return ! empty( sp_store_provider_def( $id )['does_tax'] );
}

/**
 * A configuration that cannot work, as a sentence, or '' when all is well.
 *
 * Returned to the settings screen so an impossible combination is visible there
 * rather than discovered on a customer's receipt.
 */
function sp_store_provider_warning(): string {
	if ( sp_store_tax_mode() === 'provider' && ! sp_store_provider_does_tax() ) {
		$label = sp_store_provider_def()['label'] ?? 'This processor';
		return sprintf(
			'Sales tax is set to &ldquo;let the payment processor work it out&rdquo;, but %s cannot calculate tax. '
			. 'No tax is being charged on any order. Switch to a flat rate, or change processor.',
			esc_html( $label )
		);
	}
	return '';
}


/*--------------------------------------------------------------
# Beginning a Payment
--------------------------------------------------------------*/

/**
 * Hand an order to the provider and get back how to collect for it.
 *
 * Returns one of:
 *   [ 'shape' => 'redirect', 'url'    => 'https://…' ]
 *   [ 'shape' => 'embedded', 'config' => [ … ] ]
 *
 * The caller renders whichever it gets. It never needs to know which provider
 * produced it — that is the whole point of this function existing.
 */
function sp_store_begin_payment( int $order_id ) {
	$id  = sp_store_provider();
	$def = sp_store_provider_def( $id );

	if ( ! sp_store_provider_ready( $id ) ) {
		return new WP_Error( 'sp_store_provider', 'This store cannot take payments yet.' );
	}

	$fn = $def['begin'] ?? '';
	if ( ! is_callable( $fn ) ) {
		return new WP_Error( 'sp_store_provider', 'That payment processor is not wired up.' );
	}

	$result = call_user_func( $fn, $order_id );
	if ( is_wp_error( $result ) ) return $result;

	$result['shape']    = sp_store_provider_shape( $id );
	$result['provider'] = $id;
	return $result;
}


/*--------------------------------------------------------------
# Webhooks
--------------------------------------------------------------*/

/**
 * The URL a provider should send events to.
 *
 * The provider id travels in the query value. The old URL used `=1`, which is
 * read as Stripe below, so an endpoint already registered in a Stripe dashboard
 * keeps working and nobody has to re-register anything.
 */
function sp_store_provider_webhook_url( string $id = '' ): string {
	return add_query_arg( 'sp_store_webhook', $id ?: sp_store_provider(), home_url( '/' ) );
}

/** Which provider a webhook request is for. */
function sp_store_webhook_provider(): string {
	$raw = (string) ( $_GET['sp_store_webhook'] ?? '' );

	// '1' is the historic value, from when Stripe was the only option.
	if ( $raw === '' || $raw === '1' ) return 'stripe';

	$raw = sanitize_key( $raw );
	return isset( sp_store_providers()[ $raw ] ) ? $raw : '';
}

/**
 * Hand a posted webhook to whichever provider sent it.
 *
 * Verification belongs to the provider, because each signs differently: Stripe
 * uses a timestamped HMAC header, Clover does something of its own. Nothing is
 * trusted before the provider's own check has passed.
 */
function sp_store_dispatch_webhook(): void {
	$id  = sp_store_webhook_provider();
	$fn  = sp_store_provider_def( $id )['webhook'] ?? '';

	if ( $id === '' || ! is_callable( $fn ) ) {
		status_header( 404 );
		exit;
	}

	call_user_func( $fn );
}
