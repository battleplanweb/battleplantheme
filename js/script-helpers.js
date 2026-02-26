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