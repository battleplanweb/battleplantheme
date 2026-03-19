/* Battle Plan Time Tracker
 *
 * - All open tabs share one session via localStorage (multi-tab safe)
 * - Only one tab pings at a time (lease-based coordination)
 * - Timer pauses after 20 minutes of no browser interaction
 * - Returning after inactivity starts a fresh session (gap not billed)
 * - Session starts 1 minute in the past (first minute always billed)
 * - Admin footer shows live session duration
 */
(function () {
	'use strict';

	var INACTIVE_MS       = 20 * 60 * 1000; // pause after 20 min idle
	var ACTIVITY_THROTTLE =      10 * 1000; // write last_active at most every 10s
	var PING_LEASE_MS     =      55 * 1000; // only ping if no tab did so in last 55s
	var CHECK_MS          =      30 * 1000; // coordination check every 30s
	var DISPLAY_MS        =      10 * 1000; // refresh display every 10s

	var KEY_SESSION     = 'bp_time_session';
	var KEY_LAST_PING   = 'bp_time_last_ping';
	var KEY_LAST_ACTIVE = 'bp_time_last_active';

	var ajaxUrl = bpTimeTracker.ajaxUrl;
	var nonce   = bpTimeTracker.nonce;

	/* ---- utils ---- */

	function pad(n) { return String(n).padStart(2, '0'); }

	function fmtDate(d) {
		return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
			+ ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
	}

	function nowStr() { return fmtDate(new Date()); }

	function uid() {
		return Date.now().toString(36) + Math.random().toString(36).slice(2, 9);
	}

	function lsGet(k)    { try { return localStorage.getItem(k); }    catch (e) { return null; } }
	function lsSet(k, v) { try { localStorage.setItem(k, v); }        catch (e) {} }
	function lsDel(k)    { try { localStorage.removeItem(k); }        catch (e) {} }

	/* ---- activity ---- */

	function lastActiveMs() { return parseInt(lsGet(KEY_LAST_ACTIVE) || '0', 10); }
	function isActive()     { return (Date.now() - lastActiveMs()) < INACTIVE_MS; }

	var _throttleTs = 0;
	function recordActivity() {
		var now = Date.now();
		if (now - _throttleTs < ACTIVITY_THROTTLE) return;

		// Coming back from inactivity — clear session so next interval starts fresh
		if (!isActive()) {
			lsDel(KEY_SESSION);
		}

		lsSet(KEY_LAST_ACTIVE, String(now));
		_throttleTs = now;
	}

	['mousemove', 'click', 'keydown', 'touchstart', 'scroll'].forEach(function (evt) {
		document.addEventListener(evt, recordActivity, { passive: true });
	});

	/* ---- session ---- */

	function newSession() {
		// Start 1 minute in the past so the first ping shows 1m, not 0m
		var started = fmtDate(new Date(Date.now() - 60000));
		var s = { id: uid(), started: started };
		lsSet(KEY_SESSION, JSON.stringify(s));
		return s;
	}

	function getSession() {
		try { return JSON.parse(lsGet(KEY_SESSION) || 'null'); } catch (e) { return null; }
	}

	/* ---- ping ---- */

	function sendPing(session) {
		var params = new URLSearchParams({
			action:     'bp_time_ping',
			nonce:      nonce,
			session_id: session.id,
			started:    session.started,
			ended:      nowStr(),
		});
		fetch(ajaxUrl, { method: 'POST', body: params, keepalive: true });
		lsSet(KEY_LAST_PING, String(Date.now()));
	}

	function maybePing(session) {
		var lastPing = parseInt(lsGet(KEY_LAST_PING) || '0', 10);
		if (Date.now() - lastPing >= PING_LEASE_MS) {
			sendPing(session);
		}
	}

	/* ---- display ---- */

	function updateDisplay(session) {
		var el = document.getElementById('bp-time-display');
		if (!el) return;

		if (!session || !isActive()) {
			el.textContent = 'paused';
			el.style.color = '#999';
			return;
		}

		var start = new Date(session.started.replace(' ', 'T'));
		var mins  = Math.max(1, Math.round((Date.now() - start.getTime()) / 60000));
		var h = Math.floor(mins / 60);
		var m = mins % 60;

		el.textContent = (h > 0 ? h + 'h ' : '') + m + 'm';
		el.style.color = '#2563eb';
	}

	/* ---- init ---- */

	// Check activity state BEFORE recording this page load as activity
	var activeOnLoad = isActive();

	var session = getSession();
	if (!activeOnLoad || !session) {
		session = newSession();
	}

	// Record page load as activity
	lsSet(KEY_LAST_ACTIVE, String(Date.now()));
	_throttleTs = Date.now();

	updateDisplay(session);
	sendPing(session); // always ping on load to register/update the session

	/* ---- coordination loop (every 30s) ---- */

	setInterval(function () {
		// Re-read from localStorage — another tab may have updated the session
		var current = getSession();
		if (!current) {
			if (isActive()) {
				current = newSession();
			} else {
				updateDisplay(null);
				return; // inactive, no session — wait for user to return
			}
		}
		session = current;

		if (!isActive()) {
			updateDisplay(session);
			return; // paused — show display but don't ping
		}

		maybePing(session);
		updateDisplay(session);

	}, CHECK_MS);

	/* ---- display refresh (every 10s for smooth UI) ---- */

	setInterval(function () {
		updateDisplay(getSession() || session);
	}, DISPLAY_MS);

})();
