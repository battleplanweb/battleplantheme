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

window.addCSS = function (rule) {
	if (!rule) return;

	if (!window.__BP_STYLE_SHEET__) {
		window.__BP_STYLE_SHEET__ = document.createElement('style');
		document.head.appendChild(window.__BP_STYLE_SHEET__);
	}

	const sheet = window.__BP_STYLE_SHEET__.sheet;
	const taggedRule = RULE_PREFIX + rule;

	const normalized = taggedRule.replace(/\s+/g, ' ').trim();

	for (const r of sheet.cssRules) {
		if (r.cssText.replace(/\s+/g, ' ').trim() === normalized) return;
	}

	try {
		sheet.insertRule(taggedRule, sheet.cssRules.length);
	} catch (_) { }
};


// Set, read & delete cookies
window.setCookie = function (name, value, days = 365) {
	const expires = new Date(Date.now() + days * 864e5).toUTCString();
	const secure = location.protocol === 'https:' ? '; Secure' : '';

	document.cookie =
		name + '=' + encodeURIComponent(value) +
		'; expires=' + expires +
		'; path=/' +
		'; SameSite=Strict' +
		secure;
};

window.getCookie = function (cname) {
	const name = cname + "=";
	const decodedCookie = decodeURIComponent(document.cookie);
	const ca = decodedCookie.split(';');
	for (let i = 0; i < ca.length; i++) {
		const c = ca[i].trim();
		if (c.indexOf(name) == 0) return c.substring(name.length);
	}
	return "";
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
	img.fetchPriority = 'low';
	img.decoding = 'async';
	img.src = imgName;
};


if (!document.body.classList.contains('wp-admin') && typeof site_bg === 'string' && site_bg) {
	preloadImg('site-background.' + site_bg, 'desktop');
	preloadImg('site-background-phone.' + site_bg, 'mobile');
}