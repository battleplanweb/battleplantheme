/* Battle Plan Web Design — Customer Connect app controller (public customer PWA)
   Client-side SPA over the customer-connect/v1 REST API. Auth = a device-stored bearer
   token (localStorage 'cc_token'); OTP is once per device, then silent renewal.
   Vanilla JS, no dependencies. Later phases fill the equipment/schedule/help
   views + push subscription; phase (a) ships boot, sign-in/OTP, and Home. */
(function () {
	'use strict';

	var D = window.customerConnectData || {};
	var root = document.getElementById('customer-connect-app');
	if (!root || !D.restBase) return;

	var TOKEN_KEY = 'cc_token';
	var state = { token: getToken(), customer: null, view: 'home' };

	/* ---------- token + fetch helpers ---------- */

	// Persist the token in localStorage AND a cookie (scoped to the app path).
	// Installed PWAs can occasionally evict localStorage between cold starts; the
	// cookie backup keeps the customer signed in so they don't re-verify each open.
	function cookieGet(name) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
		return m ? decodeURIComponent(m[1]) : '';
	}
	function cookieSet(name, val) {
		var base = '; path=/; max-age=' + (val ? 60 * 60 * 24 * 90 : 0) + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
		document.cookie = name + '=' + encodeURIComponent(val || '') + base;
	}
	function getToken() {
		try { var t = localStorage.getItem(TOKEN_KEY); if (t) return t; } catch (e) {}
		return cookieGet(TOKEN_KEY);
	}
	function setToken(t) {
		state.token = t || '';
		try { t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY); } catch (e) {}
		cookieSet(TOKEN_KEY, t || '');
	}

	function api(path, opts) {
		opts = opts || {};
		var headers = { 'Content-Type': 'application/json' };
		if (state.token) headers['X-CC-Token'] = state.token;
		return fetch(D.restBase + path, {
			method: opts.method || 'GET',
			headers: headers,
			body: opts.body ? JSON.stringify(opts.body) : undefined,
			credentials: 'omit',
			cache: 'no-store'
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (data) {
				if (!r.ok) throw Object.assign(new Error(data.message || 'Request failed'), { status: r.status, data: data });
				return data;
			});
		});
	}

	/* ---------- screen switching ---------- */

	function show(screen) {
		root.querySelectorAll('.cc-screen').forEach(function (el) {
			el.hidden = el.getAttribute('data-screen') !== screen;
		});
	}
	function q(sel, ctx) { return (ctx || root).querySelector(sel); }
	function setErr(form, msg) {
		var el = q('.cc-error', form);
		if (!el) return;
		el.textContent = msg || '';
		el.hidden = !msg;
	}

	/* ---------- boot ---------- */

	function boot() {
		if (!state.token) { show('signin'); return; }
		api('/me').then(function (res) {
			state.customer = res.customer;
			if (res.token) setToken(res.token); // silent renewal
			enterApp();
		}).catch(function (err) {
			// The verify screen must ONLY appear when there's no valid session. A confirmed 401
			// (bad/expired token) logs them out to verify; ANY other error (flaky network, a brief
			// server blip) keeps the saved token and drops them straight into the app — a verified
			// customer is never re-prompted to verify on a hiccup.
			if (err && err.status === 401) { setToken(''); show('signin'); }
			else enterApp();
		});
	}

	/* ---------- sign in / OTP ---------- */

	var signin = q('[data-screen="signin"]');
	var formId = q('[data-step="identify"]', signin);
	var formCode = q('[data-step="code"]', signin);
	var pendingIdentifier = '';

	/* ---------- OTP expiry countdown (10 min — matches the server TTL) ---------- */
	var codeTimer = null;
	function stopCountdown() { if (codeTimer) { clearInterval(codeTimer); codeTimer = null; } }
	function startCountdown() {
		stopCountdown();
		var wrap = q('[data-slot="timer"]', formCode);
		var out = q('[data-slot="countdown"]', formCode);
		if (!wrap || !out) return;
		wrap.hidden = false;
		var ends = Date.now() + 10 * 60 * 1000;
		function tick() {
			var left = Math.max(0, Math.round((ends - Date.now()) / 1000));
			var m = Math.floor(left / 60), s = left % 60;
			out.textContent = m + ':' + (s < 10 ? '0' + s : s);
			if (left <= 0) { stopCountdown(); wrap.innerHTML = 'Code expired — tap <strong>Resend code</strong>.'; }
		}
		tick();
		codeTimer = setInterval(tick, 1000);
	}

	/* ---------- 6-box code input (auto-advance, backspace, paste) ---------- */
	var codeBoxes = Array.prototype.slice.call(formCode.querySelectorAll('.cc-code-box'));
	function codeValue() { return codeBoxes.map(function (b) { return b.value.replace(/\D/g, ''); }).join(''); }
	function clearCode() { codeBoxes.forEach(function (b) { b.value = ''; }); if (codeBoxes[0]) codeBoxes[0].focus(); }
	function maybeAutoSubmit() { if (codeValue().length === 6) { if (formCode.requestSubmit) formCode.requestSubmit(); else q('button[type="submit"]', formCode).click(); } }
	codeBoxes.forEach(function (box, i) {
		box.addEventListener('input', function () {
			box.value = box.value.replace(/\D/g, '').slice(0, 1);
			if (box.value && i < codeBoxes.length - 1) codeBoxes[i + 1].focus();
			maybeAutoSubmit();
		});
		box.addEventListener('keydown', function (e) {
			if (e.key === 'Backspace' && !box.value && i > 0) { e.preventDefault(); codeBoxes[i - 1].value = ''; codeBoxes[i - 1].focus(); }
		});
		box.addEventListener('paste', function (e) {
			e.preventDefault();
			var digits = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '').slice(0, codeBoxes.length - i);
			for (var j = 0; j < digits.length; j++) codeBoxes[i + j].value = digits[j];
			codeBoxes[Math.min(i + digits.length, codeBoxes.length - 1)].focus();
			maybeAutoSubmit();
		});
	});

	formId.addEventListener('submit', function (e) {
		e.preventDefault();
		setErr(formId, '');
		var email = q('input[name="email"]', formId).value.trim();
		if (!email) { setErr(formId, 'Enter your email.'); return; }

		var btn = q('button[type="submit"]', formId);
		btn.disabled = true; btn.textContent = 'Sending…';
		api('/request-otp', { method: 'POST', body: { email: email } })
			.then(function (res) {
				pendingIdentifier = res.identifier || email;
				q('[data-slot="sent-to"]', formCode).textContent = pendingIdentifier;
				formId.hidden = true; formCode.hidden = false;
				clearCode();
				startCountdown();
			})
			.catch(function (err) { setErr(formId, err.message || 'Could not send a code.'); })
			.finally(function () { btn.disabled = false; btn.textContent = 'Send code'; });
	});

	formCode.addEventListener('submit', function (e) {
		e.preventDefault();
		setErr(formCode, '');
		var code = codeValue();
		if (code.length !== 6) { setErr(formCode, 'Enter the 6-digit code.'); return; }

		var btn = q('button[type="submit"]', formCode);
		btn.disabled = true; btn.textContent = 'Verifying…';
		api('/verify-otp', { method: 'POST', body: { identifier: pendingIdentifier, code: code } })
			.then(function (res) {
				stopCountdown();
				setToken(res.token);
				state.customer = res.customer;
				btn.textContent = 'Verified ✓';
				showWelcome();           // clear visual confirmation before the app loads
				setTimeout(enterApp, 750);
			})
			.catch(function (err) {
				setErr(formCode, err.message || 'Verification failed.');
				btn.disabled = false; btn.textContent = 'Verify code';
				clearCode();
			});
	});

	formCode.addEventListener('click', function (e) {
		var action = e.target.getAttribute('data-action');
		if (action === 'back') { stopCountdown(); formCode.hidden = true; formId.hidden = false; setErr(formCode, ''); }
		if (action === 'resend') {
			setErr(formCode, '');
			api('/request-otp', { method: 'POST', body: { email: pendingIdentifier } })
				.then(function () { clearCode(); startCountdown(); })
				.catch(function (err) { setErr(formCode, err.message || 'Could not resend.'); });
		}
	});

	/* ---------- app ---------- */

	var appActive = false;

	function enterApp() {
		appActive = true;
		show('app');
		wireTabs();
		// Seed history so the phone's Back button walks views instead of leaving.
		try { history.replaceState({ hbView: state.view || 'home' }, ''); } catch (e) {}
		renderView(state.view || 'home');
		refreshUnread();
		ensurePush();
	}

	// In-app navigation pushes a history entry; Back then returns to the prior view.
	function navigate(view) {
		if (view === state.view) return;
		try { history.pushState({ hbView: view }, ''); } catch (e) {}
		renderView(view);
	}

	window.addEventListener('popstate', function (e) {
		if (!appActive) return;
		renderView((e.state && e.state.hbView) || 'home');
	});

	// Brief success confirmation between "code verified" and the app rendering.
	function showWelcome() {
		var boot = q('[data-screen="boot"]');
		if (!boot) return;
		var inner = q('.cc-boot-inner', boot);
		if (inner) inner.innerHTML = '<div class="cc-check">✓</div><p class="cc-welcome">You\'re all set!</p>';
		show('boot');
	}

	/* ---------- push notifications (customer opt-in) ---------- */

	function pushSupported() {
		return D.pushReady && D.vapidPublic && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
	}

	function urlB64ToUint8(base64) {
		var pad = '='.repeat((4 - base64.length % 4) % 4);
		var b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
		var raw = atob(b64);
		var out = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
		return out;
	}

	// If already granted, make sure this device is subscribed (silent). Never
	// auto-prompts — the permission request only fires from the Home opt-in tap.
	function ensurePush() {
		if (!pushSupported() || Notification.permission !== 'granted') return;
		subscribeToPush().catch(function () {});
	}

	function subscribeToPush() {
		return navigator.serviceWorker.ready.then(function (reg) {
			return reg.pushManager.getSubscription().then(function (existing) {
				if (existing) return existing;
				return reg.pushManager.subscribe({
					userVisibleOnly: true,
					applicationServerKey: urlB64ToUint8(D.vapidPublic)
				});
			});
		}).then(function (sub) {
			return api('/push/subscribe', { method: 'POST', body: { subscription: sub.toJSON() } }).then(function () { return sub; });
		});
	}

	function enablePush(btn) {
		if (!pushSupported()) return;
		if (btn) { btn.disabled = true; btn.textContent = 'Enabling…'; }
		Notification.requestPermission().then(function (perm) {
			if (perm !== 'granted') { if (btn) { btn.disabled = false; btn.textContent = 'Enable notifications'; } return; }
			subscribeToPush().then(function () { renderView('home'); }).catch(function () {
				if (btn) { btn.disabled = false; btn.textContent = 'Enable notifications'; }
			});
		});
	}

	function refreshUnread() {
		api('/notifications').then(function (res) {
			var badge = q('[data-slot="unread"]', root);
			if (!badge) return;
			badge.textContent = res.unread > 9 ? '9+' : String(res.unread);
			badge.hidden = !res.unread;
		}).catch(function () {});
	}

	function closeMenu() { root.classList.remove('cc-menu-open'); }

	function wireTabs() {
		root.querySelectorAll('.cc-tab').forEach(function (tab) {
			if (tab._wired) return; tab._wired = true;
			tab.addEventListener('click', function () { navigate(tab.getAttribute('data-view')); closeMenu(); });
		});
		var bell = q('[data-action="notifications"]', root);
		if (bell && !bell._wired) {
			bell._wired = true;
			bell.addEventListener('click', function () { navigate('notifications'); });
		}
		var menu = q('[data-action="menu"]', root);
		if (menu && !menu._wired) {
			menu._wired = true;
			menu.addEventListener('click', function () { root.classList.toggle('cc-menu-open'); });
		}
		var scrim = q('[data-action="close-menu"]', root);
		if (scrim && !scrim._wired) {
			scrim._wired = true;
			scrim.addEventListener('click', closeMenu);
		}
	}

	function syncTab(view) {
		root.querySelectorAll('.cc-tab').forEach(function (t) {
			t.classList.toggle('is-active', t.getAttribute('data-view') === view);
		});
	}

	var TITLES = { home: 'Home', equipment: 'My Home', schedule: 'Schedule', help: 'Help', account: 'Account', notifications: 'Notifications' };

	function renderView(view) {
		state.view = view;
		syncTab(view);
		var host = q('[data-slot="view"]', root);
		q('[data-slot="view-title"]', root).textContent = TITLES[view] || 'Home';
		host.innerHTML = viewHtml(view);
		if (view === 'account') wireAccount(host);
		if (view === 'equipment') loadEquipment(host);
		if (view === 'notifications') loadNotifications(host);
		if (view === 'home') wireHome(host);
	}

	function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

	function greeting() {
		var h = new Date().getHours();
		return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
	}

	function viewHtml(view) {
		var c = state.customer || {};
		var name = c.first_name ? (', ' + esc(c.first_name)) : '';
		if (view === 'home') {
			var optin = '';
			if (pushSupported() && Notification.permission !== 'granted') {
				optin = '<section class="cc-card cc-optin">' +
					'<span class="cc-quick-title">Turn on reminders</span>' +
					'<span class="cc-quick-sub">Get filter &amp; maintenance reminders and service updates from ' + esc(D.company) + '.</span>' +
					'<button type="button" class="cc-btn cc-btn-primary cc-enable-push">Enable notifications</button>' +
					'</section>';
			}
			return '' +
				'<section class="cc-card cc-hero">' +
				'<h2>' + greeting() + name + '</h2>' +
				'<p>Welcome to ' + esc(D.appName) + ', your direct line to ' + esc(D.company) + '.</p>' +
				'</section>' +
				optin +
				'<div class="cc-quick">' +
				quick('schedule', 'Request service', 'Book a visit or priority scheduling.') +
				quick('help', 'AC troubleshooting', 'Not cooling? Get quick help.') +
				quick('equipment', 'My equipment', 'Track your system &amp; filter reminders.') +
				'</div>';
		}
		if (view === 'notifications') return '<div data-slot="notifications"><div class="cc-card cc-loading"><div class="cc-spinner"></div></div></div>';
		if (view === 'equipment') return '<div data-slot="equipment"><div class="cc-card cc-loading"><div class="cc-spinner"></div></div></div>';
		if (view === 'schedule') return placeholder('Schedule service', 'Request an appointment or priority scheduling. Coming in the next update.');
		if (view === 'help') return placeholder('AC troubleshooting', 'Describe what your system is doing and get guided help. Coming in the next update.');
		if (view === 'account') return accountHtml();
		return '';
	}

	function quick(view, title, sub) {
		return '<button type="button" class="cc-card cc-quick-item" data-goto="' + view + '">' +
			'<span class="cc-quick-title">' + title + '</span>' +
			'<span class="cc-quick-sub">' + sub + '</span></button>';
	}

	function placeholder(title, body) {
		return '<section class="cc-card"><h2>' + esc(title) + '</h2><p>' + body + '</p></section>';
	}

	function accountHtml() {
		var c = state.customer || {};
		return '' +
			'<section class="cc-card">' +
			'<h2>Your details</h2>' +
			'<form class="cc-form cc-account-form">' +
			field('first_name', 'First name', c.first_name) +
			field('last_name', 'Last name', c.last_name) +
			field('email', 'Email', c.email, 'email') +
			field('address', 'Address', c.address) +
			'<div class="cc-row">' + field('city', 'City', c.city) + field('state', 'State', c.state) + field('zip', 'ZIP', c.zip) + '</div>' +
			'<button type="submit" class="cc-btn cc-btn-primary">Save</button>' +
			'<p class="cc-error" role="alert" hidden></p>' +
			'<p class="cc-ok" role="status" hidden>Saved.</p>' +
			'</form>' +
			'<button type="button" class="cc-btn cc-link cc-signout">Sign out</button>' +
			'</section>';
	}

	function field(name, label, val, type) {
		return '<label class="cc-field"><span>' + esc(label) + '</span>' +
			'<input type="' + (type || 'text') + '" name="' + name + '" value="' + esc(val) + '"></label>';
	}

	function wireAccount(host) {
		var form = q('.cc-account-form', host);
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			setErr(form, ''); q('.cc-ok', form).hidden = true;
			var body = {};
			form.querySelectorAll('input').forEach(function (i) { body[i.name] = i.value.trim(); });
			var btn = q('button[type="submit"]', form);
			btn.disabled = true; btn.textContent = 'Saving…';
			api('/profile', { method: 'POST', body: body })
				.then(function (res) { state.customer = res.customer; q('.cc-ok', form).hidden = false; })
				.catch(function (err) { setErr(form, err.message || 'Could not save.'); })
				.finally(function () { btn.disabled = false; btn.textContent = 'Save'; });
		});
		q('.cc-signout', host).addEventListener('click', function () {
			setToken(''); state.customer = null; state.view = 'home'; appActive = false;
			syncTab('home');
			formCode.hidden = true; formId.hidden = false; setErr(formId, '');
			show('signin');
		});
	}

	function wireHome(host) {
		var btn = q('.cc-enable-push', host);
		if (btn) btn.addEventListener('click', function () { enablePush(btn); });
	}

	/* ---------- notifications ---------- */

	function loadNotifications(host) {
		var slot = q('[data-slot="notifications"]', host);
		api('/notifications').then(function (res) {
			renderNotifications(slot, res.items || []);
			if (res.unread) api('/notifications/read', { method: 'POST' }).then(refreshUnread).catch(function () {});
			else refreshUnread();
		}).catch(function (err) {
			slot.innerHTML = '<section class="cc-card"><p class="cc-error">' + esc(err.message || 'Could not load notifications.') + '</p></section>';
		});
	}

	function renderNotifications(slot, items) {
		if (!items.length) { slot.innerHTML = '<div class="cc-card cc-empty"><p>No notifications yet.</p></div>'; return; }
		slot.innerHTML = '<div class="cc-notif-list">' + items.map(function (n) {
			var when = n.created_at ? esc(String(n.created_at).slice(0, 10)) : '';
			return '<article class="cc-card cc-notif' + (n.read_at ? '' : ' is-unread') + '">' +
				'<div class="cc-notif-head"><span class="cc-notif-title">' + esc(n.title) + '</span><span class="cc-notif-when">' + when + '</span></div>' +
				(n.body ? '<p class="cc-notif-body">' + esc(n.body) + '</p>' : '') +
				(n.url ? '<a class="cc-notif-link" href="' + esc(n.url) + '">Open</a>' : '') +
				'</article>';
		}).join('') + '</div>';
	}

	/* ---------- equipment (My Home) ---------- */

	var equipmentCache = null;

	function loadEquipment(host) {
		var slot = q('[data-slot="equipment"]', host);
		api('/equipment').then(function (res) {
			state.equipmentTypes = res.types || [];
			equipmentCache = res.items || [];
			renderEquipment(slot);
		}).catch(function (err) {
			slot.innerHTML = '<section class="cc-card"><p class="cc-error">' + esc(err.message || 'Could not load your equipment.') + '</p></section>';
		});
	}

	function renderEquipment(slot) {
		var items = equipmentCache || [];
		var html = '<section class="cc-card cc-eq-intro"><h2>My Home</h2>' +
			'<p>Track your heating &amp; cooling systems so we can remind you about filters and seasonal maintenance.</p></section>';
		html += items.length
			? '<div class="cc-eq-list">' + items.map(eqCard).join('') + '</div>'
			: '<div class="cc-card cc-empty"><p>No equipment added yet. Add your first system below.</p></div>';
		html += '<button type="button" class="cc-btn cc-btn-primary cc-eq-add">+ Add equipment</button>';
		slot.innerHTML = html;
		wireEquipmentList(slot);
	}

	function filterChip(f) {
		f = f || {};
		if (f.status === 'due')  return '<span class="cc-chip cc-chip-due">Filter overdue</span>';
		if (f.status === 'soon') return '<span class="cc-chip cc-chip-soon">Filter due in ' + f.days_left + 'd</span>';
		if (f.status === 'ok')   return '<span class="cc-chip cc-chip-ok">Filter OK · ' + f.days_left + 'd left</span>';
		return '<span class="cc-chip cc-chip-none">Add filter date</span>';
	}

	function eqCard(it) {
		var title = esc(it.type) + (it.brand ? ' · ' + esc(it.brand) : '');
		var meta = [];
		if (it.install_year) meta.push('Installed ' + it.install_year + (it.age_years != null ? ' (' + it.age_years + ' yr)' : ''));
		if (it.filter_size) meta.push('Filter ' + esc(it.filter_size));
		if (it.location_label) meta.push(esc(it.location_label));
		return '<article class="cc-card cc-eq" data-id="' + it.id + '">' +
			'<div class="cc-eq-head"><span class="cc-eq-title">' + title + '</span>' + filterChip(it.filter) + '</div>' +
			(meta.length ? '<p class="cc-eq-meta">' + meta.join(' · ') + '</p>' : '') +
			'<div class="cc-eq-actions">' +
			'<button type="button" class="cc-btn cc-btn-sm cc-eq-log" data-id="' + it.id + '">Log filter change</button>' +
			'<button type="button" class="cc-btn cc-btn-sm cc-eq-edit" data-id="' + it.id + '">Edit</button>' +
			'</div></article>';
	}

	function wireEquipmentList(slot) {
		q('.cc-eq-add', slot).addEventListener('click', function () { openEquipmentForm(slot, null); });
		slot.querySelectorAll('.cc-eq-edit').forEach(function (b) {
			b.addEventListener('click', function () {
				var it = (equipmentCache || []).filter(function (x) { return x.id === +b.getAttribute('data-id'); })[0];
				openEquipmentForm(slot, it || null);
			});
		});
		slot.querySelectorAll('.cc-eq-log').forEach(function (b) {
			b.addEventListener('click', function () {
				b.disabled = true; b.textContent = 'Saving…';
				var today = new Date().toISOString().slice(0, 10);
				api('/equipment/' + b.getAttribute('data-id'), { method: 'POST', body: { filter_changed_at: today } })
					.then(function () { loadEquipment(slot.closest('[data-slot="view"]') || slot.parentNode); })
					.catch(function () { b.disabled = false; b.textContent = 'Log filter change'; });
			});
		});
	}

	var INTERVALS = [[30, 'Every month'], [60, 'Every 2 months'], [90, 'Every 3 months'], [180, 'Every 6 months'], [365, 'Once a year']];

	function openEquipmentForm(slot, it) {
		it = it || {};
		var types = state.equipmentTypes || [];
		var typeOpts = ['<option value="">— Select type —</option>'].concat(types.map(function (t) {
			return '<option' + (it.type === t ? ' selected' : '') + '>' + esc(t) + '</option>';
		})).join('');
		var intervalOpts = INTERVALS.map(function (p) {
			var sel = (it.filter_interval_days || 90) === p[0] ? ' selected' : '';
			return '<option value="' + p[0] + '"' + sel + '>' + p[1] + '</option>';
		}).join('');

		slot.innerHTML = '<section class="cc-card"><h2>' + (it.id ? 'Edit equipment' : 'Add equipment') + '</h2>' +
			'<form class="cc-form cc-eq-form">' +
			'<label class="cc-field"><span>Type</span><select name="type">' + typeOpts + '</select></label>' +
			field('brand', 'Brand', it.brand) +
			field('model', 'Model #', it.model) +
			'<div class="cc-row">' + field('install_year', 'Install year', it.install_year, 'number') + field('filter_size', 'Filter size', it.filter_size) + '</div>' +
			field('filter_changed_at', 'Filter last changed', it.filter_changed_at, 'date') +
			'<label class="cc-field"><span>Change filter</span><select name="filter_interval_days">' + intervalOpts + '</select></label>' +
			field('location_label', 'Location (e.g. Upstairs)', it.location_label) +
			'<label class="cc-field"><span>Notes</span><textarea name="notes" rows="2">' + esc(it.notes) + '</textarea></label>' +
			'<button type="submit" class="cc-btn cc-btn-primary">' + (it.id ? 'Save changes' : 'Add equipment') + '</button>' +
			(it.id ? '<button type="button" class="cc-btn cc-link cc-eq-delete">Delete this equipment</button>' : '') +
			'<button type="button" class="cc-btn cc-link cc-eq-cancel">Cancel</button>' +
			'<p class="cc-error" role="alert" hidden></p>' +
			'</form></section>';

		var form = q('.cc-eq-form', slot);
		q('.cc-eq-cancel', form).addEventListener('click', function () { renderEquipment(slot); });

		var del = q('.cc-eq-delete', form);
		if (del) del.addEventListener('click', function () {
			if (!window.confirm('Remove this equipment?')) return;
			api('/equipment/' + it.id, { method: 'DELETE' })
				.then(function () { loadEquipment(slot.closest('[data-slot="view"]') || slot.parentNode); })
				.catch(function (err) { setErr(form, err.message || 'Could not delete.'); });
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			setErr(form, '');
			var body = {};
			form.querySelectorAll('input, select, textarea').forEach(function (i) { body[i.name] = i.value.trim(); });
			if (!body.type) { setErr(form, 'Pick an equipment type.'); return; }
			var btn = q('button[type="submit"]', form);
			btn.disabled = true; btn.textContent = 'Saving…';
			var path = it.id ? '/equipment/' + it.id : '/equipment';
			api(path, { method: 'POST', body: body })
				.then(function () { loadEquipment(slot.closest('[data-slot="view"]') || slot.parentNode); })
				.catch(function (err) { setErr(form, err.message || 'Could not save.'); btn.disabled = false; btn.textContent = it.id ? 'Save changes' : 'Add equipment'; });
		});
	}

	// Quick-tile navigation (event delegation on the app view).
	root.addEventListener('click', function (e) {
		var tile = e.target.closest ? e.target.closest('[data-goto]') : null;
		if (!tile) return;
		navigate(tile.getAttribute('data-goto'));
	});

	boot();
})();
