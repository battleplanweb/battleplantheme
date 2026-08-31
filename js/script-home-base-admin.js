/* Battle Plan Web Design — Home Base staff dashboard controller
   Renders into the SHARED Battle Plan admin shell (.sp-* design system, same as
   Site Pulse). Staff are WP users → WP cookie auth + wp_rest nonce.
   Views: Send (compose push to a customer or a segment) + Customers (roster). */
(function () {
	'use strict';

	var A = window.homeBaseAdmin || {};
	var root = document.getElementById('home-base-admin');
	if (!root || !A.restBase) return;

	function q(sel, ctx) { return (ctx || root).querySelector(sel); }
	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function setErr(el, msg) { if (!el) return; el.textContent = msg || ''; el.hidden = !msg; }

	function api(path, opts) {
		opts = opts || {};
		return fetch(A.restBase + path, {
			method: opts.method || 'GET',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': A.nonce },
			body: opts.body ? JSON.stringify(opts.body) : undefined,
			credentials: 'same-origin',
			cache: 'no-store'
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (data) {
				if (!r.ok) throw Object.assign(new Error(data.message || 'Request failed'), { status: r.status, data: data });
				return data;
			});
		});
	}

	/* ---------- login (logged-out) ---------- */

	if (!A.loggedIn) {
		var lf = q('.hb-admin-login-form');
		if (lf) lf.addEventListener('submit', function (e) {
			e.preventDefault();
			var errEl = q('.hb-error', lf);
			setErr(errEl, '');
			var log = q('input[name="log"]', lf).value.trim();
			var pwd = q('input[name="pwd"]', lf).value;
			if (!log || !pwd) { setErr(errEl, 'Enter your username and password.'); return; }
			var btn = q('button[type="submit"]', lf);
			btn.disabled = true; btn.textContent = 'Signing in…';
			api('/login', { method: 'POST', body: { log: log, pwd: pwd } })
				.then(function () { window.location.reload(); })
				.catch(function (err) { setErr(errEl, err.message || 'Sign-in failed.'); btn.disabled = false; btn.textContent = 'Sign in'; });
		});
		return;
	}

	/* ---------- dashboard shell ---------- */

	var state = { view: 'send', segments: [], target: { type: 'segment', key: 'all' }, chosen: null };
	var TITLES = { send: 'Send a notification', customers: 'Customers' };

	var sidebar = document.getElementById('hb-sidebar');
	var overlay = document.getElementById('hb-overlay');
	function setMenu(open) {
		if (sidebar) sidebar.classList.toggle('open', open);
		if (overlay) overlay.classList.toggle('active', open);
	}
	var burger = document.getElementById('hb-burger');
	if (burger) burger.addEventListener('click', function () { setMenu(!(sidebar && sidebar.classList.contains('open'))); });
	if (overlay) overlay.addEventListener('click', function () { setMenu(false); });

	root.querySelectorAll('.sp-nav-item').forEach(function (item) {
		item.addEventListener('click', function () { gotoView(item.getAttribute('data-view')); });
	});

	function gotoView(view) {
		setMenu(false);
		render(view);
	}

	function render(view) {
		state.view = view;
		root.querySelectorAll('.sp-nav-item').forEach(function (n) { n.classList.toggle('active', n.getAttribute('data-view') === view); });
		var title = TITLES[view] || '';
		q('[data-slot="view-title"]', root).textContent = title;
		var mt = q('[data-slot="view-title-m"]', root); if (mt) mt.textContent = title;
		var host = q('[data-slot="view"]', root);
		if (view === 'send') renderSend(host);
		if (view === 'customers') renderCustomers(host);
	}

	function loading() { return '<div class="sp-card"><div class="hb-loading"><div class="hb-spinner"></div></div></div>'; }
	function errorCard(msg) { return '<div class="sp-card"><p class="sp-form-error">' + esc(msg) + '</p></div>'; }

	/* ---------- Send ---------- */

	function renderSend(host) {
		host.innerHTML = loading();
		api('/segments').then(function (res) {
			state.segments = res.segments || [];
			drawSend(host, res.pushReady);
		}).catch(function (err) { host.innerHTML = errorCard(err.message || 'Could not load.'); });
	}

	function drawSend(host, pushReady) {
		var audOpts = state.segments.map(function (s) {
			return '<option value="seg:' + esc(s.key) + '"' + (state.target.type === 'segment' && state.target.key === s.key ? ' selected' : '') + '>' +
				esc(s.label) + ' (' + s.count + ')</option>';
		}).join('');
		var chosenLabel = state.chosen ? (esc(state.chosen.name) + ' · ' + esc(state.chosen.contact)) : '';

		host.innerHTML =
			(!pushReady ? '<div class="sp-card"><p class="sp-form-error">Push isn\'t configured on this site yet (needs HTTPS + VAPID). Reload once ready.</p></div>' : '') +
			'<div class="sp-card">' +
			'<div class="sp-card-header"><h3>Compose</h3></div>' +
			'<form class="hb-send-form">' +
			'<div class="sp-form-group"><label>Send to</label>' +
			'<select class="sp-select" name="audience">' + audOpts +
			'<option value="customer"' + (state.target.type === 'customer' ? ' selected' : '') + '>A specific customer…</option>' +
			'</select></div>' +
			'<div class="hb-cust-pick sp-form-group" ' + (state.target.type === 'customer' ? '' : 'hidden') + '>' +
			(state.chosen
				? '<div class="hb-chosen"><span>' + chosenLabel + '</span><button type="button" class="sp-btn sp-btn-sm hb-clear-cust">Change</button></div>'
				: '<input type="search" class="sp-input hb-cust-search" placeholder="Search name, phone, or email"><div class="hb-cust-results"></div>') +
			'</div>' +
			'<div class="sp-form-group"><label>Title</label><input class="sp-input" type="text" name="title" maxlength="90" placeholder="Time to change your air filter"></div>' +
			'<div class="sp-form-group"><label>Message</label><textarea class="sp-textarea" name="body" rows="3" placeholder="A quick reminder from ' + esc(A.company) + '…"></textarea></div>' +
			'<div class="sp-form-group"><label>Link (optional)</label><input class="sp-input" type="url" name="url" placeholder="https://…"></div>' +
			'<button type="submit" class="sp-btn sp-btn-secondary">Send notification</button>' +
			'<p class="sp-form-error" role="alert" hidden></p>' +
			'<p class="sp-form-ok" role="status" hidden></p>' +
			'</form></div>';

		wireSend(host);
	}

	function wireSend(host) {
		var form = q('.hb-send-form', host);
		var sel = q('select[name="audience"]', form);
		var pick = q('.hb-cust-pick', form);

		sel.addEventListener('change', function () {
			var v = sel.value;
			if (v === 'customer') { state.target = { type: 'customer' }; pick.hidden = false; }
			else { state.target = { type: 'segment', key: v.replace(/^seg:/, '') }; pick.hidden = true; }
		});

		var search = q('.hb-cust-search', form);
		if (search) {
			var t;
			search.addEventListener('input', function () {
				clearTimeout(t);
				t = setTimeout(function () { custSearch(search.value, q('.hb-cust-results', form)); }, 250);
			});
		}
		var clear = q('.hb-clear-cust', form);
		if (clear) clear.addEventListener('click', function () { state.chosen = null; drawSend(host, true); });

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var errEl = q('.sp-form-error', form), okEl = q('.sp-form-ok', form);
			setErr(errEl, ''); okEl.hidden = true;

			var body = {
				title: q('input[name="title"]', form).value.trim(),
				body: q('textarea[name="body"]', form).value.trim(),
				url: q('input[name="url"]', form).value.trim()
			};
			if (!body.title) { setErr(errEl, 'Add a title.'); return; }
			if (state.target.type === 'customer') {
				if (!state.chosen) { setErr(errEl, 'Pick a customer to notify.'); return; }
				body.target = 'customer'; body.customer_id = state.chosen.id;
			} else {
				body.target = 'segment'; body.segment = state.target.key;
			}

			var btn = q('button[type="submit"]', form);
			btn.disabled = true; btn.textContent = 'Sending…';
			api('/send', { method: 'POST', body: body })
				.then(function (res) {
					okEl.textContent = 'Sent to ' + res.recipients + ' customer' + (res.recipients === 1 ? '' : 's') +
						' · delivered to ' + res.delivered + ' device' + (res.delivered === 1 ? '' : 's') + '.';
					okEl.hidden = false; form.reset();
				})
				.catch(function (err) { setErr(errEl, err.message || 'Send failed.'); })
				.finally(function () { btn.disabled = false; btn.textContent = 'Send notification'; });
		});
	}

	function custSearch(term, resultsEl) {
		if (!term || term.length < 2) { resultsEl.innerHTML = ''; return; }
		api('/customers?search=' + encodeURIComponent(term)).then(function (res) {
			var items = res.items || [];
			if (!items.length) { resultsEl.innerHTML = '<div class="hb-cust-none">No matches.</div>'; return; }
			resultsEl.innerHTML = items.slice(0, 8).map(function (c) {
				return '<button type="button" class="hb-cust-opt" data-id="' + c.id + '">' +
					'<span class="hb-cust-name">' + esc(c.name) + '</span>' +
					'<span class="hb-cust-meta">' + esc(c.contact) + (c.subscribed ? '' : ' · no device') + '</span></button>';
			}).join('');
			resultsEl.querySelectorAll('.hb-cust-opt').forEach(function (b) {
				b.addEventListener('click', function () {
					var c = items.filter(function (x) { return x.id === +b.getAttribute('data-id'); })[0];
					state.chosen = c; state.target = { type: 'customer' };
					drawSend(q('[data-slot="view"]', root), true);
				});
			});
		}).catch(function () {});
	}

	/* ---------- Customers ---------- */

	function renderCustomers(host) {
		host.innerHTML =
			'<div class="sp-card"><input type="search" class="sp-input hb-cust-search-all" placeholder="Search customers"></div>' +
			'<div class="hb-cust-list" data-slot="cust-list">' + loading() + '</div>';
		var listEl = q('[data-slot="cust-list"]', host);
		loadCustomers('', listEl);
		var search = q('.hb-cust-search-all', host);
		var t;
		search.addEventListener('input', function () {
			clearTimeout(t);
			t = setTimeout(function () { loadCustomers(search.value, listEl); }, 250);
		});
	}

	function loadCustomers(term, listEl) {
		api('/customers' + (term ? '?search=' + encodeURIComponent(term) : '')).then(function (res) {
			var items = res.items || [];
			if (!items.length) { listEl.innerHTML = '<div class="sp-card"><p>No customers found.</p></div>'; return; }
			listEl.innerHTML = items.map(function (c) {
				return '<div class="sp-card hb-cust-row">' +
					'<div class="hb-cust-row-main">' +
					'<span class="hb-cust-name">' + esc(c.name) + (c.subscribed ? ' <span class="hb-dot" title="Has the app"></span>' : '') + '</span>' +
					'<span class="hb-cust-meta">' + esc(c.contact) + (c.location ? ' · ' + esc(c.location) : '') + ' · ' + c.equipment + ' unit' + (c.equipment === 1 ? '' : 's') + '</span>' +
					'</div>' +
					'<button type="button" class="sp-btn sp-btn-sm sp-btn-secondary hb-cust-notify" data-id="' + c.id + '">Notify</button>' +
					'</div>';
			}).join('');
			listEl.querySelectorAll('.hb-cust-notify').forEach(function (b) {
				b.addEventListener('click', function () {
					var c = items.filter(function (x) { return x.id === +b.getAttribute('data-id'); })[0];
					state.chosen = c; state.target = { type: 'customer' };
					gotoView('send');
				});
			});
		}).catch(function (err) { listEl.innerHTML = errorCard(err.message || 'Could not load.'); });
	}

	render('send');
})();
