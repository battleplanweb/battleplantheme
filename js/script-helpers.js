// Raw Script: Helpers

window.mobileCutoff = 1024;
window.tabletCutoff = 576;

// Find width & height of user's screen
window.getDeviceW = () => window.innerWidth;
window.getDeviceH = () => window.innerHeight;
window.getMobileCutoff = () => window.mobileCutoff;
window.getTabletCutoff = () => window.tabletCutoff;

// Shortcut to select an object from a selector or jQuery element
window.getObject = function (selectorOrElement, context = document) {
	if (!selectorOrElement) return null;

	if (selectorOrElement?.nodeType === 1) {
		return selectorOrElement;
	}

	return typeof selectorOrElement === 'string'
		? context.querySelector(selectorOrElement)
		: null;
};

window.getObjects = function (selectorOrElement, context = document) {
	if (!selectorOrElement) return [];

	if (selectorOrElement?.nodeType === 1) {
		return [selectorOrElement];
	}

	return typeof selectorOrElement === 'string'
		? Array.from(context.querySelectorAll(selectorOrElement))
		: [];
};


// Determine if object exists, but set to display: none
window.isDisplayed = function (el) {
	if (!el) return false;
	const style = getComputedStyle(el);
	return (
		style.display !== 'none' &&
		style.visibility !== 'hidden'
	);
};

// Determine if user is on a mobile device
window.isMobile = function () {
	return !!document.body?.classList.contains('screen-mobile');
}


// Set styles & attributes
window.setAttributes = function (el, attrs) {
	if (!el || !attrs) return;
	for (const key in attrs) {
		el.setAttribute(key, attrs[key]);
	}
};

window.setStyles = function (el, styles) {
	if (!el || !styles) return;
	Object.assign(el.style, styles);
};

// Safety net for window.bpIsMobile. battleplan_stampDeviceClass() sets this inline at the top of
// <body>, which is the copy that matters (it runs before paint, and before this deferred file).
// This fallback only covers a template that never calls wp_body_open(). Same UA pattern as PHP's
// is_mobile() — if you change one, change the other.
if ( typeof window.bpIsMobile === 'undefined' ) {
	window.bpIsMobile = /(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i.test(navigator.userAgent);
	document.addEventListener('DOMContentLoaded', function () {
		if ( !document.body.classList.contains('screen-mobile') && !document.body.classList.contains('screen-desktop') ) {
			document.body.classList.add( window.bpIsMobile ? 'screen-mobile' : 'screen-desktop' );
		}
	});
}

// Mobile no-op: the real parallaxBG/parallaxDiv live in script-desktop.js (desktop only).
// On mobile the background is handled by CSS (.screen-mobile::before) — these stubs only
// keep a site's parallax call from throwing and aborting script-site. Overridden on desktop.
window.parallaxBG = function () {};
window.parallaxDiv = function () {};

// Same guard for the desktop-only menu enhancements (real versions in script-desktop.js /
// script-magic-menu.js, neither of which loads on mobile). Without these, a script-site.js that
// calls splitMenu()/magicMenu()/addMenuLogo() throws on mobile and aborts the rest of the file.
window.splitMenu     = function () {};
window.magicMenu     = function () {};
window.setMagicMenu  = function () {};
window.addMenuLogo   = function () {};
window.addMenuIcon   = function () {};

window.__BP_STYLE_SHEET__ = null;
const RULE_PREFIX = '/*bp*/';
window.__BP_STYLE_RULES__ = new Set();

window.addCSS = function (rule) {
	if (!rule || window.__BP_STYLE_RULES__.has(rule)) return;

	if (!window.__BP_STYLE_SHEET__) {
		window.__BP_STYLE_SHEET__ = document.createElement('style');
		document.head.appendChild(window.__BP_STYLE_SHEET__);
	}

	const sheet = window.__BP_STYLE_SHEET__.sheet;
	const taggedRule = RULE_PREFIX + rule;

	try {
		sheet.insertRule(taggedRule, sheet.cssRules.length);
		window.__BP_STYLE_RULES__.add(rule);
	} catch (_) { }
};



// Set, read & delete cookies
window.setCookie = function (name, value, days = 365) {
	let cookie = name + '=' + encodeURIComponent(value) + '; path=/; SameSite=Strict';

	if (days !== null && days !== undefined && days !== '') {
		const expires = new Date(Date.now() + days * 864e5).toUTCString();
		cookie += '; expires=' + expires;
	}

	if (location.protocol === 'https:') {
		cookie += '; Secure';
	}

	document.cookie = cookie;
};


window.getCookie = function (cname) {
	const match = document.cookie
		.split(';')
		.map(c => c.trim())
		.find(c => c.startsWith(cname + '='));
	return match ? decodeURIComponent(match.substring(cname.length + 1)) : "";
};

window.deleteCookie = function (name) {
	document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
};


// Create a debounce function to improve performance on scrolling, etc.
window.debounce = function (func, wait) {
	let timeout;

	function debounced(...args) {
		clearTimeout(timeout);
		timeout = setTimeout(() => func.apply(this, args), wait);
	}

	debounced.cancel = () => clearTimeout(timeout);

	return debounced;
};


// Preload images on-demand ... also preload site-background if necessary
window.preloadImg = function (imgName, device = 'both') {
	if (!imgName) return;

	if (device === 'mobile' && getDeviceW() > window.mobileCutoff) return;
	if (device === 'desktop' && getDeviceW() < window.mobileCutoff) return;

	const img = new Image();
	img.fetchpriority = 'low';
	img.decoding = 'async';
	img.src = imgName;
};


const _initPreload = () => {
	if (!document.body?.classList.contains('wp-admin') && typeof site_bg === 'string' && site_bg) {
		preloadImg('site-background.' + site_bg, 'desktop');
		preloadImg('site-background-phone.' + site_bg, 'mobile');
	}
};
document.body ? _initPreload() : document.addEventListener('DOMContentLoaded', _initPreload);