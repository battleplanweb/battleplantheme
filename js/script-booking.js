/* Battle Plan Web Design — Booking on the public site
 *
 * Anything with data-book-session="ID" is bookable: the Book buttons on the calendar
 * (script-events.js draws them from eventLog) and on event pages
 * (includes-site-pulse-booking-public.php).
 *
 *   1. Seats are LIVE. Pages are edge-cached, so after load — and after every calendar
 *      month change (bp:calendar-rendered) — this asks the server for the real seats
 *      and flips a sold-out date's button to its "full" text.
 *   2. Clicking opens a dialog: the date, the price, how many people. A paid date goes
 *      into the Store cart (store:add-booking, heard by script-store.js); a free one is
 *      reserved right here with a name and an email.
 */
( function () {
	'use strict';

	var cfg = window.BPBook || {};
	var ajaxUrl = cfg.ajax || '/wp-admin/admin-ajax.php';

	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		Object.keys( data || {} ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( ajaxUrl, {
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

	/* ------------------------------------------------------------ live seats */

	function refreshSeats() {
		var els = document.querySelectorAll( '[data-book-session]' );
		if ( ! els.length ) return;
		var ids = [];
		els.forEach( function ( el ) {
			var id = el.getAttribute( 'data-book-session' );
			if ( ids.indexOf( id ) < 0 ) ids.push( id );
		} );
		post( 'bp_book_seats', { ids: ids.join( ',' ) } ).then( function ( res ) {
			if ( ! res || ! res.success ) return;
			var seats = res.data || {};
			document.querySelectorAll( '.event-book[data-book-session]' ).forEach( function ( btn ) {
				var s = seats[ btn.getAttribute( 'data-book-session' ) ];
				if ( ! btn.hasAttribute( 'data-book-label' ) && ! btn.classList.contains( 'is-full' ) ) {
					btn.setAttribute( 'data-book-label', btn.textContent );
				}
				var full = ! s || ! s.open || s.left <= 0;
				btn.classList.toggle( 'is-full', full );
				btn.disabled = full;
				btn.textContent = full ? ( btn.getAttribute( 'data-book-full' ) || 'Full' ) : ( btn.getAttribute( 'data-book-label' ) || btn.textContent );
				var slot = btn.closest( '.event-booking-slot' );
				if ( slot ) slot.classList.toggle( 'is-full', full );
			} );
			document.querySelectorAll( '[data-book-left]' ).forEach( function ( el ) {
				var s = seats[ el.getAttribute( 'data-book-left' ) ];
				el.textContent = ( s && s.open && s.left > 0 && s.left <= 5 ) ? ( s.left === 1 ? '1 spot left' : s.left + ' spots left' ) : '';
			} );
		} ).catch( function () {} );
	}

	/* ---------------------------------------------------------------- dialog */

	var dialog = null;

	function closeDialog() {
		if ( ! dialog ) return;
		dialog.remove();
		dialog = null;
		document.body.classList.remove( 'bp-book-open' );
	}

	function openDialog( sessionId ) {
		closeDialog();
		dialog = document.createElement( 'div' );
		dialog.className = 'bp-book-dialog';
		dialog.innerHTML = '<div class="bp-book-scrim" data-book-close></div>'
			+ '<div class="bp-book-panel" role="dialog" aria-modal="true" aria-label="Book">'
			+ '<button type="button" class="unique bp-book-x" data-book-close aria-label="Close">&times;</button>'
			+ '<div class="bp-book-body"><p class="bp-book-loading">Loading…</p></div></div>';
		document.body.appendChild( dialog );
		document.body.classList.add( 'bp-book-open' );

		post( 'bp_book_session', { id: sessionId } ).then( function ( res ) {
			if ( ! dialog ) return;
			if ( ! res || ! res.success ) { showMessage( ( res && res.data && res.data.message ) || 'That date is no longer available.' ); return; }
			render( res.data );
		} ).catch( function () { showMessage( 'Something went wrong. Please try again.' ); } );
	}

	function body() { return dialog && dialog.querySelector( '.bp-book-body' ); }

	function showMessage( text, ok ) {
		var b = body();
		if ( b ) b.innerHTML = '<p class="bp-book-msg' + ( ok ? ' is-ok' : '' ) + '">' + esc( text ) + '</p>';
	}

	function render( s ) {
		var b = body();
		if ( ! b ) return;
		var html = '<h2 class="bp-book-title">' + esc( s.title ) + '</h2>'
			+ '<p class="bp-book-when">' + esc( s.when ) + '</p>';
		if ( s.description ) html += '<p class="bp-book-desc">' + esc( s.description ) + '</p>';
		// A series (one price, every date): list the dates the seat covers.
		if ( s.series > 1 && s.dates && s.dates.length ) {
			html += '<ul class="bp-book-dates">' + s.dates.map( function ( d ) { return '<li>' + esc( d ) + '</li>'; } ).join( '' ) + '</ul>';
		}
		if ( s.price ) html += '<p class="bp-book-price">' + esc( s.price ) + ( s.deposit ? ' deposit' : '' ) + ' per person' + ( s.series > 1 ? ' for all ' + s.series + ' classes' : '' ) + '</p>';

		if ( ! s.open || s.left <= 0 ) {
			var why = s.closed || ( s.left <= 0 ? ( s.full_label || 'This date is full.' ) : 'This date can no longer be booked online.' );
			b.innerHTML = html + '<p class="bp-book-msg">' + esc( why ) + '</p>';
			return;
		}

		var opts = '';
		for ( var i = 1; i <= s.max; i++ ) opts += '<option value="' + i + '">' + i + '</option>';
		html += '<label class="bp-book-field bp-book-seats"><span>How many people?</span><select data-book-seats>' + opts + '</select></label>';
		if ( s.left <= 5 ) html += '<p class="bp-book-left">' + ( s.left === 1 ? 'Only 1 spot left.' : 'Only ' + s.left + ' spots left.' ) + '</p>';

		if ( s.checkout ) {
			var canCart = s.store_ready && document.querySelector( '[data-store-drawer]' );
			html += canCart
				? '<button type="button" class="bp-book-go" data-book-cart>Add to Cart</button>'
				: '<p class="bp-book-msg">Online payment isn\'t set up yet — please contact us to book.</p>';
			b.innerHTML = html;
			var cartBtn = b.querySelector( '[data-book-cart]' );
			if ( cartBtn ) cartBtn.addEventListener( 'click', function () {
				var qty = parseInt( b.querySelector( '[data-book-seats]' ).value, 10 ) || 1;
				document.dispatchEvent( new CustomEvent( 'store:add-booking', { detail: { sessionId: s.id, qty: qty } } ) );
				closeDialog();
			} );
			return;
		}

		html += '<form class="bp-book-form" novalidate>'
			+ '<label class="bp-book-field"><span>Name</span><input type="text" name="name" autocomplete="name" required></label>'
			+ '<label class="bp-book-field"><span>Email</span><input type="email" name="email" autocomplete="email" required></label>'
			+ '<label class="bp-book-field"><span>Phone <em>(optional)</em></span><input type="tel" name="phone" autocomplete="tel"></label>'
			+ '<label class="bp-book-hp" aria-hidden="true">Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>'
			+ '<p class="bp-book-err" hidden></p>'
			+ '<button type="submit" class="bp-book-go">' + esc( s.book_label || 'Book Now' ) + '</button>'
			+ '</form>';
		b.innerHTML = html;

		var form = b.querySelector( 'form' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var err = form.querySelector( '.bp-book-err' );
			var go  = form.querySelector( '.bp-book-go' );
			var field = function ( n ) { return form.querySelector( '[name="' + n + '"]' ).value; };
			var data = {
				id: s.id,
				seats: parseInt( b.querySelector( '[data-book-seats]' ).value, 10 ) || 1,
				name: field( 'name' ).trim(),
				email: field( 'email' ).trim(),
				phone: field( 'phone' ).trim(),
				website: field( 'website' )
			};
			if ( ! data.name || ! /.+@.+\..+/.test( data.email ) ) {
				err.textContent = 'Please enter your name and email.';
				err.hidden = false;
				return;
			}
			go.disabled = true;
			go.textContent = 'Booking…';
			post( 'bp_book_reserve', data ).then( function ( res ) {
				if ( res && res.success ) { showMessage( res.data.message, true ); refreshSeats(); return; }
				err.textContent = ( res && res.data && res.data.message ) || 'Something went wrong. Please try again.';
				err.hidden = false;
				go.disabled = false;
				go.textContent = s.book_label || 'Book Now';
			} ).catch( function () {
				err.textContent = 'Something went wrong. Please try again.';
				err.hidden = false;
				go.disabled = false;
				go.textContent = s.book_label || 'Book Now';
			} );
		} );
	}

	/* A phone's calendar day is too small to tap one of several classes, so tapping a day that has
	   more than one opens a short list of them first; picking one opens its booking dialog. */
	function openChooser( entry ) {
		closeDialog();
		var slots = entry.querySelectorAll( '.event-booking-slot[data-book-session]' );
		var html = '<h2 class="bp-book-title">Choose a class</h2><div class="bp-book-choices">';
		slots.forEach( function ( slot ) {
			var full  = slot.classList.contains( 'is-full' );
			var label = ( slot.querySelector( '.event-booking-label' ) || slot ).textContent.replace( /\s+/g, ' ' ).trim();
			var btn   = slot.querySelector( '.event-book' );
			html += '<button type="button" class="unique bp-book-choice' + ( full ? ' is-full' : '' ) + '" data-book-pick="' + slot.getAttribute( 'data-book-session' ) + '"' + ( full ? ' disabled' : '' ) + '>'
				+ '<span>' + esc( label ) + '</span><span class="bp-book-choice-state">' + esc( full ? ( btn && btn.getAttribute( 'data-book-full' ) || 'Full' ) : ( btn && ( btn.getAttribute( 'data-book-label' ) || btn.textContent ) || 'Book' ) ) + '</span></button>';
		} );
		html += '</div>';
		dialog = document.createElement( 'div' );
		dialog.className = 'bp-book-dialog';
		dialog.innerHTML = '<div class="bp-book-scrim" data-book-close></div>'
			+ '<div class="bp-book-panel" role="dialog" aria-modal="true" aria-label="Choose a class">'
			+ '<button type="button" class="unique bp-book-x" data-book-close aria-label="Close">&times;</button>'
			+ '<div class="bp-book-body">' + html + '</div></div>';
		document.body.appendChild( dialog );
		document.body.classList.add( 'bp-book-open' );
	}

	/* ----------------------------------------------------------------- wiring */

	document.addEventListener( 'click', function ( e ) {
		if ( dialog && e.target.closest( '[data-book-close]' ) ) { closeDialog(); return; }
		var pick = e.target.closest( '[data-book-pick]' );
		if ( pick && ! pick.disabled ) { openDialog( pick.getAttribute( 'data-book-pick' ) ); return; }
		var multi = e.target.closest( '#calendar .event-booking-multi' );
		if ( multi && window.matchMedia( '(max-width: 860px)' ).matches ) { e.preventDefault(); openChooser( multi ); return; }
		var el = e.target.closest( '[data-book-session]' );
		if ( ! el || el.disabled || el.classList.contains( 'is-full' ) ) return;
		// The calendar entry links to the event page; only its button (or a plain session entry) books.
		if ( e.target.closest( 'a' ) && ! e.target.closest( '.event-book' ) ) return;
		e.preventDefault();
		openDialog( el.getAttribute( 'data-book-session' ) );
	} );

	document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) closeDialog(); } );
	document.addEventListener( 'bp:calendar-rendered', refreshSeats );
	if ( document.readyState === 'loading' ) document.addEventListener( 'DOMContentLoaded', refreshSeats );
	else refreshSeats();
} )();
