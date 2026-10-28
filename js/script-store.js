/* Battle Plan Web Design — Storefront
--------------------------------------------------------------
The variant picker. Reads the JSON block the server printed next to each
product, and keeps the price, the struck-through regular price, the stock line
and the buy button in step with whatever combination of options is selected.

No framework, no build step, no dependency. The whole thing is one pass over
an array that is never longer than the number of variants a client has typed
in by hand.

The add-to-cart handler is DEFERRED to phase 3: the button only exists when
the server rendered it, and it dispatches a DOM event rather than calling an
endpoint, so the cart layer can subscribe without this file changing.
-------------------------------------------------------------- */

( function () {
	'use strict';

	function parseVariants( productId ) {
		var node = document.getElementById( 'store-variants-' + productId );
		if ( ! node ) return [];
		try {
			return JSON.parse( node.textContent ) || [];
		} catch ( e ) {
			return [];
		}
	}

	/**
	 * Find the variant matching the currently selected option values.
	 * Compares only the positions that actually have a select, so a product with
	 * one named option is not tripped up by the two empty slots on every row.
	 */
	function matchVariant( variants, selected ) {
		for ( var i = 0; i < variants.length; i++ ) {
			var ok = true;
			for ( var index in selected ) {
				if ( ! Object.prototype.hasOwnProperty.call( selected, index ) ) continue;
				if ( variants[ i ].options[ index ] !== selected[ index ] ) { ok = false; break; }
			}
			if ( ok ) return variants[ i ];
		}
		return null;
	}

	function initProduct( root ) {
		var productId = root.getAttribute( 'data-product' );
		var variants  = parseVariants( productId );
		if ( ! variants.length ) return;

		var priceEl   = root.querySelector( '[data-store-price]' );
		var wasEl     = root.querySelector( '[data-store-was]' );
		var stockEl   = root.querySelector( '[data-store-stock]' );
		var addBtn    = root.querySelector( '[data-store-add]' );
		var qtyEl     = root.querySelector( '.store-qty' );
		var selects   = Array.prototype.slice.call( root.querySelectorAll( '.store-option-select' ) );
		var tiles     = Array.prototype.slice.call( root.querySelectorAll( '.store-tile' ) );
		var groups    = Array.prototype.slice.call( root.querySelectorAll( '.store-option-group' ) );
		var usesTiles = tiles.length > 0;
		var track     = root.querySelector( '[data-store-track]' );
		var slides    = Array.prototype.slice.call( root.querySelectorAll( '.store-slide' ) );
		var thumbs    = Array.prototype.slice.call( root.querySelectorAll( '.store-gallery-thumb' ) );
		var dotsWrap  = root.querySelector( '[data-store-dots]' );

		// The variant currently selected. Declared here, at function scope, because both
		// render() and the Add to Cart handler read it — the handler needs to know WHAT
		// is being added. Its declaration previously sat in the block the slide-based
		// gallery replaced, and losing it was a silent ReferenceError under strict mode
		// that killed initProduct partway through render().
		var current = null;

		// A real pointer that can hover, rather than a width guess: a 1200px touch
		// screen should still get tap-to-zoom, and a narrow desktop window should not
		// lose hover zoom just because it was resized.
		var canHover  = window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;

		function visibleSlides() {
			return slides.filter( function ( s ) { return ! s.hidden; } );
		}

		/** Desktop: make one slide the visible one. On phones the track scrolls instead. */
		function activate( id ) {
			slides.forEach( function ( s ) {
				s.classList.toggle( 'is-active', s.getAttribute( 'data-id' ) === String( id ) );
			} );
			thumbs.forEach( function ( t ) {
				t.classList.toggle( 'is-active', t.getAttribute( 'data-id' ) === String( id ) );
			} );
			syncDots();
		}

		function syncDots() {
			if ( ! dotsWrap ) return;
			var vis = visibleSlides();
			if ( vis.length < 2 ) { dotsWrap.innerHTML = ''; return; }

			var activeIdx = 0;
			if ( canHover ) {
				vis.forEach( function ( s, i ) { if ( s.classList.contains( 'is-active' ) ) activeIdx = i; } );
			} else if ( track ) {
				// Which slide is nearest the middle of the viewport after a swipe.
				var mid = track.scrollLeft + track.clientWidth / 2;
				vis.forEach( function ( s, i ) {
					if ( s.offsetLeft <= mid && s.offsetLeft + s.offsetWidth > mid ) activeIdx = i;
				} );
			}

			var html = '';
			for ( var i = 0; i < vis.length; i++ ) {
				html += '<span class="store-dot' + ( i === activeIdx ? ' is-active' : '' ) + '"></span>';
			}
			dotsWrap.innerHTML = html;
		}


		/**
		 * Show only the images belonging to the selected variant — anything tagged to one
		 * of its option values, plus everything untagged. No match at all falls back to
		 * the whole gallery rather than leaving an empty frame.
		 */
		function filterGallery( variant ) {
			if ( ! slides.length ) return;

			var tags = ( variant && variant.tags ) || [];
			function belongs( el ) {
				var tag = el.getAttribute( 'data-tag' );
				return ! tag || tags.indexOf( tag ) !== -1;
			}

			var visible = slides.filter( belongs );
			if ( ! visible.length ) visible = slides;

			slides.forEach( function ( s ) { s.hidden = visible.indexOf( s ) === -1; } );
			thumbs.forEach( function ( t ) {
				t.hidden = ! visible.some( function ( s ) {
					return s.getAttribute( 'data-id' ) === t.getAttribute( 'data-id' );
				} );
			} );

			// If the image on screen is no longer one of this variant's, switch to the
			// first that is. Otherwise picking Red would leave the grey photo showing.
			var shown = visible.filter( function ( s ) { return s.classList.contains( 'is-active' ); } );
			if ( ! shown.length ) activate( visible[ 0 ].getAttribute( 'data-id' ) );
			else syncDots();

			if ( track && ! canHover ) track.scrollTo( { left: 0 } );
		}

		/* ---- zoom ------------------------------------------------------------- */

		var panel = null;
		var lens  = null;

		/**
		 * Desktop hover zoom. A panel beside the image showing the FULL-SIZE file at
		 * 1:1, with a lens marking the region — the Amazon behaviour. Driven by
		 * background-position rather than a scaled <img>, so there is one element to
		 * move and no layout work per mouse event.
		 */
		// How much more detail the original actually holds than the slide is already
		// showing. Below this there is nothing to reveal, so zooming would just upscale
		// the same pixels and hand back a blurrier copy of what is on screen.
		var MIN_GAIN = 1.2;

		// The zoom panel is square, so its width also sets its height. Left uncapped, a
		// wide detail column would make it taller than the window and the bottom of the
		// zoomed image would sit off screen.
		var MAX_PANEL_VH = 0.75;

		/**
		 * The real magnification available for a slide, or 0 when it is not worth
		 * offering. Recomputed per use rather than cached, because the displayed size
		 * changes with the viewport — a photo with nothing to give on a wide desktop
		 * can have plenty to give on a phone, where it is rendered much smaller.
		 */
		function zoomGain( slide ) {
			var img  = slide.querySelector( 'img' );
			var full = slide._zoomImg;
			if ( ! img || ! full || ! full.naturalWidth ) return 0;

			var r = img.getBoundingClientRect();
			if ( ! r.width || ! r.height ) return 0;

			// object-fit:cover, so the rendered scale is the LARGER ratio.
			var cover = Math.max( r.width / full.naturalWidth, r.height / full.naturalHeight );
			var gain  = Math.min( 1 / cover, 4 );

			return gain >= MIN_GAIN ? gain : 0;
		}

		/** Preload the full file and mark the slide once we know whether it can zoom. */
		function prepareZoom( slide ) {
			var img = slide.querySelector( 'img' );
			if ( ! img || ! img.getAttribute( 'data-zoom' ) || slide._zoomImg ) return;

			slide._zoomImg = new Image();
			slide._zoomImg.onload = function () {
				// Only cosmetic — it drives the zoom-in cursor. The real decision is made
				// again at hover time, against the size the image is then.
				slide.classList.toggle( 'is-zoomable', zoomGain( slide ) > 0 );
			};
			slide._zoomImg.src = img.getAttribute( 'data-zoom' );
		}

		function startHoverZoom( slide ) {
			var img = slide.querySelector( 'img' );
			if ( ! img ) return;

			// Nothing worth showing: no panel, and the cursor stays an arrow so the
			// picture does not advertise a zoom that would not magnify anything.
			var zoom = zoomGain( slide );
			if ( ! zoom ) return;

			var full = slide._zoomImg;
			var rect = img.getBoundingClientRect();

			panel = document.createElement( 'div' );
			panel.className = 'store-zoom-panel';
			panel.style.backgroundImage = 'url(' + img.getAttribute( 'data-zoom' ) + ')';

			// Fill the product's detail column — a bigger window shows more of the
			// picture at once, and that column is dead space while hovering. But cap it
			// at what the file can actually fill at this magnification: a panel wider
			// than rect.width * zoom would have to upscale to fill itself, which is the
			// blurry result this whole gate exists to avoid.
			var detail = root.querySelector( '.store-single-detail' );
			var dRect  = detail ? detail.getBoundingClientRect() : null;
			var want   = ( dRect && dRect.width > 80 ) ? dRect.width : rect.width;

			// SQUARE, matching the slide. Width fills the detail column and the height
			// follows it, rather than being pinned to the image's height — which is what
			// made the panel a rectangle showing a letterboxed slice of a square photo.
			//
			// Three caps, whichever bites first:
			//   the column        — as wide as the space allows
			//   the resolution    — never larger than the file can fill at this zoom,
			//                       in either axis, or it would upscale
			//   MAX_PANEL_VH      — because the panel is square, a wide column would
			//                       otherwise make it tall enough to run off the screen
			var side = Math.min(
				want,
				rect.width  * zoom,
				rect.height * zoom,
				window.innerHeight * MAX_PANEL_VH
			);

			if ( dRect && dRect.width > 80 ) {
				panel.style.left = dRect.left + 'px';
			} else {
				var room = window.innerWidth - rect.right - 24;
				var left = room >= side ? rect.right + 16 : rect.left - side - 16;
				panel.style.left = Math.max( 8, left ) + 'px';
			}

			// Aligned with the top of the image, but nudged up when that would push the
			// bottom off screen — capping the height is only half the job if the panel
			// still starts near the fold.
			var top = Math.min( rect.top, window.innerHeight - side - 8 );

			panel.style.width  = side + 'px';
			panel.style.height = side + 'px';
			panel.style.top    = Math.max( 8, top ) + 'px';

			lens = document.createElement( 'div' );
			lens.className = 'store-zoom-lens';
			slide.appendChild( lens );
			document.body.appendChild( panel );

			function move( e ) {
				var r = img.getBoundingClientRect();
				var x = Math.min( Math.max( e.clientX - r.left, 0 ), r.width );
				var y = Math.min( Math.max( e.clientY - r.top, 0 ), r.height );

				var nw = full.naturalWidth;
				var nh = full.naturalHeight;

				// Same cover geometry as the slide, or the panel would show a differently
				// cropped picture from the one the lens is sitting on.
				var cover = Math.max( r.width / nw, r.height / nh );

				// `zoom` is fixed for this hover by zoomGain(), and the panel was sized
				// so the lens fits inside the image at exactly that magnification. No
				// floor is needed here any more: the panel can no longer out-size the
				// resolution and force an upscale.
				var p  = panel.getBoundingClientRect();
				var pw = p.width;
				var ph = p.height;

				var lensW = pw / zoom;
				var lensH = ph / zoom;
				lens.style.width  = lensW + 'px';
				lens.style.height = lensH + 'px';
				lens.style.left   = Math.min( Math.max( x - lensW / 2, 0 ), r.width  - lensW ) + 'px';
				lens.style.top    = Math.min( Math.max( y - lensH / 2, 0 ), r.height - lensH ) + 'px';

				// background-size is the cover-fitted image multiplied by the zoom, so the
				// panel window shows exactly the lens region. (The previous version
				// DIVIDED instead, which cancelled out to 1x — the same picture at the
				// same size, which is what made this look like it was doing nothing.)
				var bgW = nw * cover * zoom;
				var bgH = nh * cover * zoom;
				panel.style.backgroundSize = bgW + 'px ' + bgH + 'px';

				// Positioned in PIXELS, not percentages. A percentage ignores the crop
				// that object-fit:cover applies to the slide, so on any non-square photo
				// the panel would show a vertically offset slice from the one under the
				// lens. These offsets are how much cover hid off each edge.
				var offX = ( nw * cover - r.width )  / 2;
				var offY = ( nh * cover - r.height ) / 2;

				// Centre the cursor's point in the PANEL, so these halve the panel's
				// size rather than the image's.
				var posX = -( ( x + offX ) * zoom - pw / 2 );
				var posY = -( ( y + offY ) * zoom - ph / 2 );

				// Never scroll past the edges of the image.
				posX = Math.min( 0, Math.max( pw - bgW, posX ) );
				posY = Math.min( 0, Math.max( ph - bgH, posY ) );

				panel.style.backgroundPosition = posX + 'px ' + posY + 'px';
			}

			slide._zoomMove = move;
			slide.addEventListener( 'mousemove', move );

			// The panel is fixed and positioned once from the image's rect, so scrolling
			// would strand it beside where the image used to be. Close rather than
			// recompute: the pointer has left the picture anyway.
			slide._zoomScroll = function () { stopHoverZoom( slide ); };
			window.addEventListener( 'scroll', slide._zoomScroll, { passive: true } );
		}

		function stopHoverZoom( slide ) {
			if ( slide._zoomMove ) {
				slide.removeEventListener( 'mousemove', slide._zoomMove );
				slide._zoomMove = null;
			}
			if ( slide._zoomScroll ) {
				window.removeEventListener( 'scroll', slide._zoomScroll );
				slide._zoomScroll = null;
			}
			if ( lens && lens.parentNode )   lens.parentNode.removeChild( lens );
			if ( panel && panel.parentNode ) panel.parentNode.removeChild( panel );
			lens = panel = null;
		}

		/**
		 * Touch zoom: a full-screen overlay holding the image at twice the viewport
		 * width, panned by NATIVE SCROLLING rather than touch handlers. The browser
		 * already does momentum, bounds and pinch properly; hand-rolled drag maths
		 * fights it and loses.
		 */
		function openFullZoom( slide ) {
			var img = slide.querySelector( 'img' );
			if ( ! img ) return;

			var src  = img.getAttribute( 'data-zoom' ) || img.currentSrc || img.src;
			var full = slide._zoomImg;

			// NEVER wider than the file really is. The old 200%-of-viewport rule was a
			// guess that upscaled any image narrower than two screens — a bigger, softer
			// copy of what was already on the page. 1:1 with the original is the most
			// detail that exists; the 4-screen cap is only so a very large file does not
			// turn into an unmanageable amount of panning.
			var vw    = window.innerWidth || 390;
			var width = ( full && full.naturalWidth )
				? Math.min( full.naturalWidth, vw * 4 )
				: vw;

			var overlay = document.createElement( 'div' );
			overlay.className = 'store-zoom-full';
			overlay.innerHTML =
				'<button type="button" class="unique store-zoom-close" aria-label="Close">&times;</button>' +
				'<div class="store-zoom-scroll"><img src="' + src + '" alt="" style="width:' + width + 'px"></div>';

			document.body.appendChild( overlay );
			document.body.classList.add( 'store-drawer-open' );

			function close() {
				if ( overlay.parentNode ) overlay.parentNode.removeChild( overlay );
				document.body.classList.remove( 'store-drawer-open' );
				document.removeEventListener( 'keydown', onKey );
			}
			function onKey( e ) { if ( e.key === 'Escape' ) close(); }

			overlay.querySelector( '.store-zoom-close' ).addEventListener( 'click', close );
			document.addEventListener( 'keydown', onKey );

			// Start centred, so a square product does not open showing its top-left corner.
			var scroller = overlay.querySelector( '.store-zoom-scroll' );
			requestAnimationFrame( function () {
				scroller.scrollLeft = ( scroller.scrollWidth  - scroller.clientWidth )  / 2;
				scroller.scrollTop  = ( scroller.scrollHeight - scroller.clientHeight ) / 2;
			} );
		}

		slides.forEach( function ( slide ) {
			prepareZoom( slide );

			if ( canHover ) {
				slide.addEventListener( 'mouseenter', function () { startHoverZoom( slide ); } );
				slide.addEventListener( 'mouseleave', function () { stopHoverZoom( slide ); } );
			} else {
				slide.addEventListener( 'click', function () {
					// Same rule on touch: a full-screen view of an image with no extra
					// detail is just a bigger blur.
					if ( ! zoomGain( slide ) ) return;
					openFullZoom( slide );
				} );
			}
		} );

		thumbs.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				var id = thumb.getAttribute( 'data-id' );
				activate( id );
				// On a phone the thumbnails are hidden, but if they are ever shown the
				// click should move the carousel rather than do nothing.
				var slide = slides.filter( function ( s ) { return s.getAttribute( 'data-id' ) === id; } )[ 0 ];
				if ( slide && track && ! canHover ) {
					track.scrollTo( { left: slide.offsetLeft, behavior: 'smooth' } );
				}
			} );
		} );

		if ( track && ! canHover ) {
			var dotTimer = null;
			track.addEventListener( 'scroll', function () {
				clearTimeout( dotTimer );
				dotTimer = setTimeout( syncDots, 80 );
			}, { passive: true } );
		}

		/**
		 * The current choice, read the same way from either picker style. Both put the
		 * option position on the group and the chosen value on the control, so nothing
		 * below here knows or cares which style is on.
		 */
		function selection() {
			var selected = {};
			if ( usesTiles ) {
				groups.forEach( function ( group ) {
					var active = group.querySelector( '.store-tile.is-active' );
					if ( active ) selected[ group.getAttribute( 'data-index' ) ] = active.getAttribute( 'data-value' );
				} );
			} else {
				selects.forEach( function ( select ) {
					selected[ select.getAttribute( 'data-index' ) ] = select.value;
				} );
			}
			return selected;
		}

		/**
		 * Grey out choices that cannot be reached from the rest of the current selection.
		 * With Large picked, a colour that has no Large variant is shown unavailable
		 * rather than left looking pickable and then reporting "not available" after the
		 * click. Only possible with tiles — a <select> has nowhere to say it.
		 */
		function markUnavailable( selected ) {
			if ( ! usesTiles ) return;

			tiles.forEach( function ( tile ) {
				var index = tile.getAttribute( 'data-index' );
				var probe = {};
				Object.keys( selected ).forEach( function ( k ) {
					if ( k !== index ) probe[ k ] = selected[ k ];
				} );
				probe[ index ] = tile.getAttribute( 'data-value' );

				var match = matchVariant( variants, probe );
				tile.classList.toggle( 'is-unavailable', ! match );
				tile.classList.toggle( 'is-soldout', !! match && ! match.inStock );
			} );
		}

		function render() {
			var selected = selection();

			current = ( selects.length || tiles.length ) ? matchVariant( variants, selected ) : variants[ 0 ];
			markUnavailable( selected );

			// A combination the client never created — e.g. Large exists and Red exists,
			// but there is no Large Red. Say so plainly rather than showing a stale price.
			if ( ! current ) {
				if ( priceEl )   priceEl.textContent = '';
				if ( wasEl ) wasEl.hidden = true;
				if ( stockEl )   { stockEl.textContent = 'That combination is not available.'; stockEl.className = 'store-stock is-out'; }
				if ( addBtn )    addBtn.disabled = true;
				return;
			}

			if ( priceEl ) {
				priceEl.innerHTML = current.price;
				// Sale pricing is per variant, so the colour has to follow the selection —
				// large might be discounted while small is not.
				priceEl.classList.toggle( 'is-sale', !! current.was );
			}

			if ( wasEl ) {
				wasEl.innerHTML = current.was || '';
				wasEl.hidden    = ! current.was;
			}

			filterGallery( current );

			if ( stockEl ) {
				if ( ! current.inStock ) {
					stockEl.textContent = 'Sold out';
					stockEl.className   = 'store-stock is-out';
				} else if ( current.left !== null && current.left > 0 ) {
					stockEl.textContent = 'Only ' + current.left + ' left';
					stockEl.className   = 'store-stock is-low';
				} else {
					stockEl.textContent = 'In stock';
					stockEl.className   = 'store-stock';
				}
			}

			if ( addBtn ) addBtn.disabled = ! current.inStock;
		}

		selects.forEach( function ( select ) {
			select.addEventListener( 'change', render );
		} );

		tiles.forEach( function ( tile ) {
			tile.addEventListener( 'click', function () {
				var index = tile.getAttribute( 'data-index' );
				// Only one tile per group is active. aria-checked is kept in step so the
				// radiogroup actually announces correctly rather than just looking right.
				tiles.forEach( function ( other ) {
					if ( other.getAttribute( 'data-index' ) !== index ) return;
					var on = other === tile;
					other.classList.toggle( 'is-active', on );
					other.setAttribute( 'aria-checked', on ? 'true' : 'false' );
				} );
				render();
			} );
		} );

		if ( addBtn ) {
			addBtn.addEventListener( 'click', function () {
				if ( ! current || ! current.inStock ) return;

				var qty = qtyEl ? parseInt( qtyEl.value, 10 ) : 1;
				if ( ! qty || qty < 1 ) qty = 1;

				// Phase 3's cart layer listens for this. Dispatching an event instead of
				// calling a function means this file never has to know the cart exists.
				root.dispatchEvent( new CustomEvent( 'store:add', {
					bubbles: true,
					detail: { productId: productId, variantId: current.id, qty: qty }
				} ) );
			} );
		}

		render();
	}

	/* ---------------------------------------------------------------- cart */

	var cartState = null;

	function ajax( action, data ) {
		var url = ( window.BPStore && window.BPStore.ajax ) || '/wp-admin/admin-ajax.php';
		var body = new URLSearchParams();
		body.append( 'action', action );
		Object.keys( data || {} ).forEach( function ( k ) { body.append( k, data[ k ] ); } );

		// credentials:'same-origin' matters — the cart cookie IS the credential, and
		// without it every request would look like a brand-new visitor.
		return fetch( url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) { return r.json(); } );
	}

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( ch ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ ch ];
		} );
	}

	function renderCart( cart ) {
		cartState = cart;

		// Every cart button on the page (e.g. the header's and the mobile bar's), not just the first.
		document.querySelectorAll( '[data-store-count]' ).forEach( function ( countEl ) {
			countEl.textContent = cart.count;
			countEl.hidden = ! cart.count;
		} );

		var body = document.querySelector( '[data-store-cart-body]' );
		var foot = document.querySelector( '[data-store-cart-foot]' );
		if ( ! body ) return;

		if ( ! cart.items.length ) {
			body.innerHTML = '<p class="store-drawer-empty">Your cart is empty.</p>';
			if ( foot ) foot.hidden = true;
			return;
		}

		var html = '';
		cart.items.forEach( function ( item ) {
			html += '<div class="store-line" data-variant="' + item.variant_id + '">';
			html += item.image
				? '<a class="store-line-img" href="' + esc( item.url ) + '"><img src="' + esc( item.image ) + '" alt=""></a>'
				: '<span class="store-line-img store-line-noimg"></span>';
			html += '<div class="store-line-detail">';
			html += '<a class="store-line-title" href="' + esc( item.url ) + '">' + esc( item.title ) + '</a>';
			if ( item.variant && item.variant !== 'Default' ) {
				html += '<span class="store-line-variant">' + esc( item.variant ) + '</span>';
			}
			html += '<span class="store-line-unit">' + item.unit + ' each</span>';
			html += '<div class="store-line-controls">';
			html += '<button type="button" class="unique store-qty-btn" data-store-dec aria-label="Reduce quantity">&minus;</button>';
			html += '<input class="store-line-qty" type="number" min="0" step="1" value="' + item.qty + '"'
				+ ( item.max !== null ? ' max="' + item.max + '"' : '' ) + '>';
			html += '<button type="button" class="unique store-qty-btn" data-store-inc aria-label="Increase quantity"'
				+ ( item.max !== null && item.qty >= item.max ? ' disabled' : '' ) + '>+</button>';
			html += '<button type="button" class="unique store-line-remove" data-store-remove>Remove</button>';
			html += '</div>';
			// Say why + is disabled, rather than leaving a dead button.
			if ( item.max !== null && item.qty >= item.max ) {
				html += '<span class="store-line-limit">That is all we have in stock.</span>';
			}
			html += '</div>';
			html += '<span class="store-line-total">' + item.line + '</span>';
			html += '</div>';
		} );

		body.innerHTML = html;

		if ( foot ) {
			foot.hidden = false;
			var sub = foot.querySelector( '[data-store-cart-subtotal]' );
			if ( sub ) sub.innerHTML = cart.subtotal;

			// No URL means no payment processor is configured. The button disables
			// itself rather than linking to a page that cannot finish.
			var go = foot.querySelector( '[data-store-cart-checkout]' );
			if ( go ) {
				if ( cart.checkout ) {
					go.href = cart.checkout;
					go.removeAttribute( 'aria-disabled' );
					go.classList.remove( 'is-disabled' );
					go.textContent = 'Checkout';
				} else {
					go.removeAttribute( 'href' );
					go.setAttribute( 'aria-disabled', 'true' );
					go.classList.add( 'is-disabled' );
					go.textContent = 'Checkout unavailable';
				}
			}

			// Staff see WHY. The server only sends this to someone who can act on it.
			var note = foot.querySelector( '[data-store-cart-note]' );
			if ( note ) {
				note.textContent = cart.checkout_note || '';
				note.hidden = ! cart.checkout_note;
			}
		}
	}

	function flash( message ) {
		var body = document.querySelector( '[data-store-cart-body]' );
		if ( ! body || ! message ) return;
		var note = document.createElement( 'p' );
		note.className = 'store-drawer-error';
		note.textContent = message;
		body.insertBefore( note, body.firstChild );
	}

	function cartCall( action, data, openAfter ) {
		return ajax( action, data ).then( function ( res ) {
			var cart = ( res && res.data && res.data.cart ) || null;
			if ( cart ) renderCart( cart );
			if ( res && ! res.success ) flash( res.data && res.data.message );
			if ( openAfter ) openDrawer();
		} ).catch( function () {
			flash( 'Something went wrong. Please try again.' );
		} );
	}

	function openDrawer() {
		var drawer = document.querySelector( '[data-store-drawer]' );
		if ( ! drawer ) return;
		drawer.hidden = false;
		drawer.setAttribute( 'aria-hidden', 'false' );
		document.body.classList.add( 'store-drawer-open' );
	}

	function closeDrawer() {
		var drawer = document.querySelector( '[data-store-drawer]' );
		if ( ! drawer ) return;
		drawer.hidden = true;
		drawer.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'store-drawer-open' );
	}

	function initCart() {
		var drawer = document.querySelector( '[data-store-drawer]' );
		if ( ! drawer ) return;

		// The product picker fires this; the cart hears it. Neither knows about the other.
		document.addEventListener( 'store:add', function ( e ) {
			cartCall( 'sp_store_cart_add', {
				variant_id: e.detail.variantId,
				qty: e.detail.qty
			}, true );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '[data-store-cart-open]' ) )  { e.preventDefault(); openDrawer(); }
			if ( e.target.closest( '[data-store-cart-close]' ) ) { e.preventDefault(); closeDrawer(); }

			var line = e.target.closest( '.store-line' );
			if ( ! line ) return;
			var variantId = line.getAttribute( 'data-variant' );
			var qtyInput  = line.querySelector( '.store-line-qty' );

			if ( e.target.closest( '[data-store-remove]' ) ) {
				cartCall( 'sp_store_cart_remove', { variant_id: variantId } );
			} else if ( e.target.closest( '[data-store-inc]' ) ) {
				cartCall( 'sp_store_cart_qty', { variant_id: variantId, qty: parseInt( qtyInput.value, 10 ) + 1 } );
			} else if ( e.target.closest( '[data-store-dec]' ) ) {
				cartCall( 'sp_store_cart_qty', { variant_id: variantId, qty: parseInt( qtyInput.value, 10 ) - 1 } );
			}
		} );

		document.addEventListener( 'change', function ( e ) {
			if ( ! e.target.classList.contains( 'store-line-qty' ) ) return;
			var line = e.target.closest( '.store-line' );
			if ( line ) {
				cartCall( 'sp_store_cart_qty', {
					variant_id: line.getAttribute( 'data-variant' ),
					qty: parseInt( e.target.value, 10 ) || 0
				} );
			}
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) closeDrawer();
		} );

		// Fetch on load. The count cannot be server-rendered — these pages are cached and
		// shared, so a baked-in number would show one shopper's cart to everyone.
		cartCall( 'sp_store_cart_get', {} );
	}

	/* ---- On-site checkout ------------------------------------------------- */

	/**
	 * The checkout page: re-price on change, then hand the order to the processor.
	 *
	 * This script NEVER sends an amount. It sends an address and the id of a
	 * chosen shipping rate; the server re-prices from the database and returns
	 * rendered totals. A figure that travelled out to a browser and back is a
	 * figure somebody can edit, so none of them make that trip.
	 */
	function initCheckout() {
		var root = document.querySelector( '[data-store-checkout]' );
		if ( ! root ) return;

		var summary  = root.querySelector( '[data-store-summary]' );
		var shipWrap = root.querySelector( '[data-store-ship-options]' );
		var payMount = root.querySelector( '[data-store-pay]' );
		var errorEl  = root.querySelector( '[data-store-co-error]' );
		var placeBtn = root.querySelector( '[data-store-place]' );

		function field( id ) {
			var el = document.getElementById( id );
			return el ? el.value.trim() : '';
		}

		function chosenRate() {
			var picked = root.querySelector( 'input[name="sp_ship_rate"]:checked' );
			return picked ? picked.value : '';
		}

		function showError( message ) {
			if ( ! errorEl ) return;
			errorEl.textContent = message || '';
			errorEl.hidden = ! message;
			if ( message ) errorEl.scrollIntoView( { block: 'center', behavior: 'smooth' } );
		}

		/* ---- re-pricing ---- */

		var quoting = false;
		var again   = false;   // a change arrived mid-quote: price once more when this one lands

		function requote() {
			if ( quoting ) { again = true; return; }
			quoting = true;
			again   = false;
			if ( summary ) summary.classList.add( 'is-busy' );

			// The address travels too: with shipping by distance it is part of the price.
			ajax( 'sp_store_quote', {
				shipping_rate_id: chosenRate(),
				'address[address1]': field( 'sp-co-address1' ),
				'address[city]': field( 'sp-co-city' ),
				'address[state]': field( 'sp-co-state' ),
				'address[zip]': field( 'sp-co-zip' )
			} ).then( function ( res ) {
				if ( res && res.success ) {
					var totals = summary && summary.querySelector( '.store-summary-totals' );
					if ( totals ) totals.innerHTML = res.data.totals;

					// The rate list comes back too, because a rate can stop applying:
					// drop below a free-shipping threshold in another tab and the option
					// must disappear here rather than be chosen and then refused.
					if ( shipWrap && res.data.shipping ) {
						var keep = chosenRate();
						shipWrap.innerHTML = res.data.shipping;
						var same = keep && shipWrap.querySelector( 'input[value="' + keep + '"]' );
						if ( same ) same.checked = true;
						markChosen();
					}
				}
			} ).catch( function () {
				// Leave the figures as they were. A stale total is better than a blank
				// one, and the server re-prices again before anything is charged.
			} ).then( function () {
				quoting = false;
				if ( summary ) summary.classList.remove( 'is-busy' );
				if ( again ) requote();
			} );
		}

		function markChosen() {
			var options = root.querySelectorAll( '.store-ship-option' );
			Array.prototype.forEach.call( options, function ( opt ) {
				var input = opt.querySelector( 'input' );
				opt.classList.toggle( 'is-active', !! ( input && input.checked ) );
			} );
		}

		// Re-price when the address settles — on leaving a field, not on every keystroke,
		// because a distance price is a call to Google and the ZIP is typed a digit at a time.
		var addrTimer = null;
		[ 'sp-co-address1', 'sp-co-city', 'sp-co-state', 'sp-co-zip' ].forEach( function ( id ) {
			var el = document.getElementById( id );
			if ( ! el ) return;
			el.addEventListener( 'change', function () {
				clearTimeout( addrTimer );
				addrTimer = setTimeout( requote, 250 );
			} );
		} );

		// Delegated: the rate list is replaced wholesale on every re-quote, so a
		// listener bound to the inputs themselves would die on the first change.
		if ( shipWrap ) {
			shipWrap.addEventListener( 'change', function ( e ) {
				if ( e.target && e.target.name === 'sp_ship_rate' ) {
					markChosen();
					requote();
				}
			} );
		}

		/* ---- placing the order ---- */

		// Checked here only to save a round trip and point at the offending box.
		// The server validates again and its answer is the one that counts.
		function firstEmpty() {
			var required = [
				[ 'sp-co-email', 'Please enter your email address.' ],
				[ 'sp-co-name', 'Please enter your name.' ],
				[ 'sp-co-address1', 'Please enter your street address.' ],
				[ 'sp-co-city', 'Please enter your city.' ],
				[ 'sp-co-state', 'Please choose your state.' ],
				[ 'sp-co-zip', 'Please enter your ZIP code.' ]
			];

			for ( var i = 0; i < required.length; i++ ) {
				var el = document.getElementById( required[ i ][ 0 ] );
				if ( el && ! el.value.trim() ) {
					el.focus();
					return required[ i ][ 1 ];
				}
			}
			return '';
		}

		function mountPayment( data ) {
			if ( data.shape === 'redirect' && data.url ) {
				window.location = data.url;
				return;
			}

			// An embedded processor mounts its own iframe here. Nothing in this file
			// may build card inputs itself — the fields belong to the processor's
			// document, and that is what keeps the site out of PCI scope.
			if ( data.shape === 'embedded' && payMount && window.BPStorePay && window.BPStorePay[ data.config.provider ] ) {
				window.BPStorePay[ data.config.provider ]( payMount, data.config );
				return;
			}

			showError( 'We could not open the payment form. Please try again.' );
			if ( placeBtn ) placeBtn.disabled = false;
		}

		if ( placeBtn ) {
			placeBtn.addEventListener( 'click', function () {
				showError( '' );

				var missing = firstEmpty();
				if ( missing ) { showError( missing ); return; }

				// Agreements (when the basket needs any). Checked here only to point at the
				// box; the server decides which are required and checks again.
				var agreeBoxes = root.querySelectorAll( 'input[name="sp_agree"]' );
				var agreed = [];
				for ( var a = 0; a < agreeBoxes.length; a++ ) {
					if ( ! agreeBoxes[ a ].checked ) {
						agreeBoxes[ a ].focus();
						showError( 'Please read and accept each agreement to continue.' );
						return;
					}
					agreed.push( agreeBoxes[ a ].value );
				}
				var signEl = document.getElementById( 'sp-co-sign' );
				if ( signEl && signEl.value.trim().length < 2 ) {
					signEl.focus();
					showError( 'Please type your full name as your signature.' );
					return;
				}

				placeBtn.disabled = true;
				placeBtn.classList.add( 'is-busy' );

				var payload = {
					email: field( 'sp-co-email' ),
					shipping_rate_id: chosenRate(),
					sign_name: field( 'sp-co-sign' ),
					note: field( 'sp-co-note' ),
					'address[name]': field( 'sp-co-name' ),
					'address[address1]': field( 'sp-co-address1' ),
					'address[address2]': field( 'sp-co-address2' ),
					'address[city]': field( 'sp-co-city' ),
					'address[state]': field( 'sp-co-state' ),
					'address[zip]': field( 'sp-co-zip' )
				};
				// One agree[n] key per accepted agreement, which PHP reads back as an array.
				agreed.forEach( function ( id, i ) { payload[ 'agree[' + i + ']' ] = id; } );

				ajax( 'sp_store_place_order', payload ).then( function ( res ) {
					if ( res && res.success ) {
						mountPayment( res.data );
						return;
					}
					// Stock is already released server-side when an order fails, so the
					// button goes back to being usable rather than stranding the shopper.
					showError( ( res && res.data && res.data.message ) || 'We could not place your order.' );
					placeBtn.disabled = false;
					placeBtn.classList.remove( 'is-busy' );
				} ).catch( function () {
					showError( 'Something went wrong. Please check your connection and try again.' );
					placeBtn.disabled = false;
					placeBtn.classList.remove( 'is-busy' );
				} );
			} );
		}

		markChosen();
	}

	function init() {
		// THE CART GOES FIRST, and each product is isolated.
		//
		// These used to run the other way round, unguarded: one exception anywhere in a
		// product's gallery or picker setup propagated out of init() and initCart() was
		// never reached — so the symptom of a broken thumbnail was a dead Add to Cart
		// button, with nothing on screen connecting the two. Buying must not depend on
		// the gallery working.
		try {
			initCart();
		} catch ( e ) {
			if ( window.console ) console.error( 'store: cart failed to initialise', e );
		}

		try {
			initCheckout();
		} catch ( e ) {
			if ( window.console ) console.error( 'store: checkout failed to initialise', e );
		}

		var toolbar = document.querySelector( '.store-toolbar' );
		if ( toolbar ) {
			toolbar.addEventListener( 'change', function ( e ) {
				if ( e.target && e.target.tagName === 'SELECT' ) toolbar.submit();
			} );
			var go = toolbar.querySelector( '.store-toolbar-go' );
			if ( go ) go.hidden = true;
		}

		var products = document.querySelectorAll( '.store-single[data-product]' );
		Array.prototype.forEach.call( products, function ( root ) {
			try {
				initProduct( root );
			} catch ( e ) {
				if ( window.console ) console.error( 'store: product failed to initialise', e );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
