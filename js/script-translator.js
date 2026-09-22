// Raw Script: Language Switch (Google Translate)

/*--------------------------------------------------------------
# Language Switch (Google Translate)
--------------------------------------------------------------*/

(function () {	"use strict";

	const TARGET_ID  = 'hablamos-espanol';	// off-screen mount for Google's element
	const PAGE_LANG  = 'en';				// language the site is actually written in
	const LANGUAGES  = 'es,en';
	const COOKIE     = 'googtrans';
	const MAX_AGE    = 31536000;			// a year - Google's own cookie dies with the tab
	const POLL_MS    = 100;					// how often to look for Google's library
	const POLL_LIMIT = 100;					// give up after ~10s instead of polling forever

	let started = false;

	// Google's element.js calls this by name (?cb=googleTranslateElementInit), so it has
	// to live on window - a scoped function leaves the callback throwing and no widget.
	window.googleTranslateElementInit = buildWidget;

	// Returns true once the widget is built, so the poller below knows when to stop. The
	// widget is never seen: it reads the googtrans cookie and translates the page, while
	// the visible control is ours.
	function buildWidget() {
		if (started) return true;

		const ready = window.google && google.translate && google.translate.TranslateElement;

		if (!ready || !getObject('#' + TARGET_ID)) return false;

		started = true;

		new google.translate.TranslateElement({
			pageLanguage: PAGE_LANG,
			layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
			autoDisplay: false,				// no banner, no auto-popup
			includedLanguages: LANGUAGES
		}, TARGET_ID);

		return true;
	}

	// Cookie value is "/en/es" - the third slice is what the page is translated INTO.
	function currentLang() {
		const match = document.cookie.match(/(?:^|;\s*)googtrans=([^;]*)/);
		const value = match ? decodeURIComponent(match[1]) : '';

		return value.split('/')[2] || PAGE_LANG;
	}

	// Google writes this cookie on both the host and the registrable domain, so clear
	// and set every scope - leave one behind and the stale copy wins the next load.
	function writeCookie(lang) {
		const host  = location.hostname;
		const bare  = host.replace(/^www\./, '');
		const value = (lang === PAGE_LANG) ? '' : '/' + PAGE_LANG + '/' + lang;
		const kill  = COOKIE + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';

		['', '; domain=' + host, '; domain=.' + bare].forEach(function (scope) {
			document.cookie = kill + scope;

			if (value) document.cookie = COOKIE + '=' + value + '; max-age=' + MAX_AGE + '; path=/' + scope;
		});
	}

	function switchTo(lang) {
		writeCookie(lang);

		// A #googtrans(...) hash outranks the cookie, so drop it on the way out. replace()
		// rather than reload() so Back doesn't land on the pre-switch cookie state.
		location.replace(location.pathname + location.search);
	}

	function markActive(root, lang) {
		getObjects('.bp-lang', root).forEach(function (btn) {
			const on = btn.getAttribute('data-lang') === lang;

			btn.classList.toggle('is-active', on);
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
	}

	function initSwitch() {
		const root = getObject('.bp-translator');

		if (!root) return;

		markActive(root, currentLang());

		root.addEventListener('click', function (e) {
			// Google wraps text nodes in <font>, so match on the closest button
			const btn = e.target.closest ? e.target.closest('.bp-lang') : null;

			if (!btn) return;

			const lang = btn.getAttribute('data-lang');

			if (lang && lang !== currentLang()) switchTo(lang);
		});
	}

	initSwitch();

	// If element.js already ran (cached, or it beat this file) the callback above missed
	// its moment - so watch for the library too. Polling beats the fixed timeout this
	// used to rely on, which lost the race on slow connections.
	(function poll(tries) {
		if (buildWidget() || tries > POLL_LIMIT) return;
		setTimeout(function () { poll(tries + 1); }, POLL_MS);
	})(0);
})();
