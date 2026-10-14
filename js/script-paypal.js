document.addEventListener("DOMContentLoaded", function () {	"use strict";

// Raw Script: PayPal (Pay in 4 / Pay Later)
//
// Drives every [paypal] block on the page. The PHP side owns the money — this
// only collects the customer's details, hands PayPal a reference to an order the
// server created, and reports back what happened.
//
// Three things worth knowing before editing:
//
//   1. THE AMOUNT SENT FROM HERE IS A REQUEST, NOT A PRICE. On a fixed-amount
//      button the server ignores it entirely; on an open-amount one it is
//      clamped to a min/max that travelled inside the signed config. Nothing
//      here can make something cost less.
//
//   2. THE BUTTONS ARE PAYPAL'S IFRAMES. We cannot style their insides, read
//      them, or submit them programmatically. Validation therefore happens in
//      onClick, which is the one hook PayPal gives us before its window opens.
//
//   3. onApprove CAN FIRE TWICE. A slow phone, a retried fetch. The server
//      de-dupes on the PayPal order id, so a second capture is a no-op — but
//      the UI is locked here anyway so the customer never sees two attempts.

	if (!window.paypal) return;

	document.querySelectorAll('.bp-paypal').forEach(initPayPal);

	function initPayPal(box) {
		const buttons  = box.querySelector('.bp-pp-buttons');
		const status   = box.querySelector('.bp-pp-status');
		const msgEl    = box.querySelector('.bp-pp-message');
		const amountEl = box.querySelector('.bp-pp-amount');
		const inputs   = Array.prototype.slice.call(box.querySelectorAll('.bp-pp-input'));

		if (!buttons || !window.paypal.Buttons) return;

		const rest  = box.dataset.rest;
		const cfg   = box.dataset.cfg;
		const sig   = box.dataset.sig;
		const fixed = box.dataset.amount || '';

		let working  = false;
		let explained = false; // a specific error is already on screen — see onError

		// ---- Pay Later banner ------------------------------------------------
		// PayPal writes the "4 interest-free payments of $x" copy itself. On a
		// FIXED amount there is nothing to do here: the PHP already emitted a
		// div carrying data-pp-message and the SDK auto-bootstraps it on load.
		//
		// An open amount has no number until somebody types one, so the banner is
		// re-rendered as they do — debounced, because each render is a round trip
		// to PayPal.
		//
		// The 30/1500 bounds are PayPal's own published Pay in 4 range for the
		// US. Outside it there is no offer to make and the SDK renders nothing,
		// so the previous banner is cleared rather than left sitting there
		// advertising instalments on a price that can't have them.
		const PAY_IN_4_MIN = 30;
		const PAY_IN_4_MAX = 1500;

		let msgTimer = null;

		function paintMessage() {
			if (!msgEl || fixed || !window.paypal.Messages) return;

			const amt = parseFloat(readAmount());
			if (!amt || amt < PAY_IN_4_MIN || amt > PAY_IN_4_MAX) { msgEl.innerHTML = ''; return; }

			msgEl.innerHTML = '<div data-pp-message data-pp-pageType="checkout"' +
				' data-pp-style-layout="text" data-pp-style-logo-type="inline"' +
				' data-pp-amount="' + amt + '"></div>';

			// pageType, not placement — see the matching note in the PHP.
			window.paypal.Messages({
				amount: amt,
				pageType: 'checkout',
				style: { layout: 'text', logo: { type: 'inline' } }
			}).render(msgEl.firstElementChild);
		}

		// ---- Amount ----------------------------------------------------------
		function readAmount() {
			if (fixed) return fixed;
			return amountEl ? amountEl.value.replace(/[^0-9.]/g, '') : '';
		}

		if (amountEl) {
			// Keep the field to something that can be a price while they type:
			// digits, one dot, two decimals. Cheaper than validating after the
			// fact and it stops "12.3.4" ever reaching the server.
			amountEl.addEventListener('input', function () {
				let v = amountEl.value.replace(/[^0-9.]/g, '');
				const firstDot = v.indexOf('.');
				if (firstDot !== -1) {
					v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
					v = v.slice(0, firstDot + 3);
				}
				if (v !== amountEl.value) amountEl.value = v;

				clearTimeout(msgTimer);
				msgTimer = setTimeout(paintMessage, 600);
			});

			amountEl.addEventListener('blur', function () {
				const n = parseFloat(amountEl.value);
				if (n > 0) amountEl.value = n.toFixed(2);
				paintMessage();
			});
		}

		paintMessage();

		// ---- Validation ------------------------------------------------------
		// Native constraint validation on the fields we own, plus the amount.
		// Nothing here is trusted server-side; it exists so the customer is told
		// what is missing BEFORE a PayPal window opens over the top of the form.
		function validate() {
			let ok = true;
			let firstBad = null;

			if (!fixed) {
				const n = parseFloat(readAmount());
				const bad = !n || n <= 0;
				box.classList.toggle('bp-pp-amount-invalid', bad);
				if (bad) { ok = false; firstBad = amountEl; }
			}

			inputs.forEach(function (el) {
				const valid = el.checkValidity();
				el.closest('.bp-pp-field').classList.toggle('bp-pp-invalid', !valid);
				if (!valid) firstBad = firstBad || el;
				if (!valid) ok = false;
			});

			if (!ok) {
				say('Please finish the highlighted fields first.', 'error');
				if (firstBad && firstBad.focus) firstBad.focus();
			} else {
				say('');
			}
			return ok;
		}

		inputs.forEach(function (el) {
			el.addEventListener('input', function () {
				el.closest('.bp-pp-field').classList.remove('bp-pp-invalid');
			});
		});

		// ---- Status line -----------------------------------------------------
		function say(text, kind) {
			if (!status) return;
			status.textContent = text || '';
			status.className = 'bp-pp-status' + (text && kind ? ' bp-pp-' + kind : '');
		}

		function lock(on) {
			working = on;
			box.classList.toggle('bp-pp-working', on);
		}

		// ---- Payload ---------------------------------------------------------
		function payload(extra) {
			const body = { cfg: cfg, sig: sig, amount: readAmount() };
			inputs.forEach(function (el) { body[el.dataset.key] = el.value; });
			// Asks the server to attach PayPal's own error detail to any failure.
			// Set by the shortcode, never by the browser on its own.
			if (box.dataset.debug === '1') body.debug = '1';
			return Object.assign(body, extra || {});
		}

		function post(path, body) {
			return fetch(rest + path, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(body)
			}).then(function (r) {
				return r.text().then(function (text) {
					let json = {};
					try { json = JSON.parse(text); } catch (e) {}

					// A failure arrives as HTTP 200 carrying `error` — the server
					// never sends a 5xx, because WP Engine's edge replaces a 5xx
					// body with its own error page and the real reason is lost.
					// A genuine non-2xx here therefore means something upstream
					// broke (PHP fatal, WAF block) and `text` is the only clue.
					if (json.error || !r.ok) {
						// Always log the raw response. A PHP fatal or a WAF block comes
						// back as HTML, which JSON.parse throws on — and then the only
						// clue anywhere is what got printed here.
						if (window.console) console.error('[bp-paypal] ' + path + ' → ' + r.status, json.debug || text.slice(0, 500));

						// json.debug is only populated in debug mode: PayPal's issue code
						// and the debug_id their support asks for. It can come back
						// empty — an OAuth token rejection carries neither — so the
						// parentheses are built from what's actually there rather than
						// printed around nothing.
						const bits = json.debug
							? [json.debug.issue, json.debug.field, json.debug.debug_id].filter(Boolean)
							: [];
						const detail = bits.length ? ' (' + bits.join(' · ') + ')' : '';

						throw new Error((json.error || 'We could not reach PayPal (HTTP ' + r.status + ').') + detail);
					}
					return json;
				});
			});
		}

		// ---- The buttons -----------------------------------------------------
		window.paypal.Buttons({
			style: {
				layout: 'vertical',
				shape:  'rect',
				color:  'gold',
				label:  'pay',
				height: 48
			},

			// The only pre-flight hook PayPal offers. Reject and its window never
			// opens, which is why validation lives here rather than on submit.
			onClick: function (data, actions) {
				if (working) return actions.reject();
				return validate() ? actions.resolve() : actions.reject();
			},

			createOrder: function () {
				lock(true);
				say('Setting up your payment…');
				return post('/order', payload()).then(function (json) {
					if (!json.id) throw new Error('PayPal did not return an order.');
					say('');
					return json.id;
				}).catch(function (err) {
					lock(false);
					say(err.message, 'error');
					// Rethrowing is what tells PayPal to abandon the flow — but it
					// also lands in onError, which used to paint a generic message
					// straight over the specific one we just showed. This flag is
					// how onError knows the customer has already been told.
					explained = true;
					throw err;
				});
			},

			onApprove: function (data) {
				lock(true);
				say('Completing your payment — please don’t close this page…');

				// data.paymentSource is the only place Pay Later is distinguishable
				// from an ordinary PayPal payment; the capture response calls both
				// of them "paypal". Passed as a label for the record only.
				return post('/capture', payload({
					order_id: data.orderID,
					source:   data.paymentSource || ''
				})).then(function (json) {
					box.classList.add('bp-pp-done');
					say('Payment received. Taking you to your receipt…', 'ok');
					if (json.redirect) window.location.href = json.redirect;
				}).catch(function (err) {
					lock(false);
					say(err.message, 'error');
				});
			},

			onCancel: function () {
				lock(false);
				say('Payment cancelled — nothing has been charged.');
			},

			onError: function (err) {
				lock(false);
				if (window.console) console.error('[bp-paypal] onError', err);

				// PayPal's catch-all. It fires for our own rethrown createOrder
				// failure too, so anything already explained is left on screen —
				// "something went wrong" over the top of a real reason is worse
				// than useless, it actively hides the answer.
				if (explained) { explained = false; return; }

				say('Something went wrong at PayPal. Please try again, or call us and we’ll take the payment over the phone.', 'error');
			}
		}).render(buttons).catch(function (err) {
			// A failed render leaves an empty div, which reads as a broken page.
			// Say so rather than leaving a hole where the button should be.
			say('The payment buttons could not load. Please refresh the page.', 'error');
			if (window.console) console.error('[bp-paypal] render', err);
		});
	}
});
