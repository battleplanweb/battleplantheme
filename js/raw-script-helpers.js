document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Helpers														   

	window.mobileCutoff = 1024;
	window.tabletCutoff = 576;								  		
	
	
// Is user on an Apple device?
	window.isApple = function () {
		return /iPad|iPhone|iPod/.test(navigator.platform);
	};	
														   

// Find width & height of user's screen
	window.getDeviceW = function() {
		return isApple() && (window.orientation === 90 || window.orientation === -90) ? window.screen.height : window.innerWidth;
	};

	window.getDeviceH = function() {
		return isApple() && (window.orientation === 90 || window.orientation === -90) ? window.screen.width : window.innerHeight;
	};

	
// Make mobile and tablet cut off variables available in script-site.js
	window.getMobileCutoff = function () {
		return mobileCutoff;
	};	

	window.getTabletCutoff = function () {
		return tabletCutoff;
	};	
	

// Shortcut to select an object from a selector or jQuery element
	window.getObject = function (selectorOrElement, context=document, all=false) {
		all = (all === true || all === 'true');
		if (typeof selectorOrElement === 'string' && selectorOrElement !== "#") { 
			return all ? context.querySelectorAll(selectorOrElement) : context.querySelector(selectorOrElement);
		} else if (selectorOrElement instanceof HTMLElement) { 
			return all ? [selectorOrElement] : selectorOrElement;
		} else if (window.jQuery && selectorOrElement instanceof jQuery) { 
			return all ? selectorOrElement.get() : selectorOrElement[0]; 
		} else {
			return null;
		}
	}
	
	window.getObjects = function (selectorOrElement, context=document) {
		return getObject(selectorOrElement, context, true);
	}
	
	
/*	
// Compare relative position of two objects in the DOM
	window.comparePos = function (elem1, elem2) {
		const position = elem1.compareDocumentPosition(elem2);

        if (position & Node.DOCUMENT_POSITION_FOLLOWING) {
            return 'after';
        } else if (position & Node.DOCUMENT_POSITION_PRECEDING) {
            return 'before';
        } else if (position & Node.DOCUMENT_POSITION_CONTAINS) {
            return 'inside';
        } else if (position & Node.DOCUMENT_POSITION_CONTAINED_BY) {
            return 'outside';
        } else {
            return null;
        } 
	}
*/
	
	
// Determine if object exists, but set to display: none
	window.isVisible = function (elementObj) {
		if (elementObj) {
			const style = window.getComputedStyle(elementObj);
			return style.display !== 'none';
		}
	}
	
	
// Determine if user is on a mobile device
	window.isMobile = function () {
		return document.body.classList.contains('screen-mobile') ? true : false;
	}
	
		
// Set styles & attributes 
	window.setAttributes = function(elementObj, attributes) {
		for (const key in attributes) {
			elementObj.setAttribute(key, attributes[key]);
		}
	}	
	
	window.setStyles = function(elementObj, styles) {
    	Object.assign(elementObj.style, styles);
	}

	window.addCSS = function(elementSel, rule) {
		const styleSheet = [...document.styleSheets].find(sheet => sheet.href && sheet.href.includes('style-site'));
		if (styleSheet) {
			const ruleExists = [...styleSheet.cssRules].some(cssRule => cssRule.cssText.includes(`${elementSel} { ${rule}`));
			if (!ruleExists) {
				styleSheet.insertRule ? styleSheet.insertRule(`${elementSel} { ${rule} }`, styleSheet.cssRules.length) : styleSheet.addRule(elementSel, rule);
			}
		}
	};
	
	
// Set, read & delete cookies
	window.setCookie = function(cname, cvalue, exdays) {
		let d = new Date();
		d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
		const expires = exdays ? "expires=" + d.toUTCString() + "; " : "";
		//const domain = "domain=" + document.domain.split('.').slice(-2).join('.') + "; ";
		//document.cookie = cname + "=" + encodeURIComponent(cvalue) + "; " + expires + "path=/; domain=" + domain + "; SameSite=Strict; Secure";
		document.cookie = cname + "=" + encodeURIComponent(cvalue) + "; " + expires + "path=/; SameSite=Strict; Secure";
	};
	
	window.getCookie = function(cname) {
		const name = cname + "=";
		const decodedCookie = decodeURIComponent(document.cookie);
		const ca = decodedCookie.split(';');
		for (let i = 0; i < ca.length; i++) {
			const c = ca[i].trim();
			if (c.indexOf(name) == 0) return c.substring(name.length);
		}
		return "";
	};
	
	window.deleteCookie = function(cname) {
		setCookie(cname, "", -1);
	};

	
// Create a debounce function to improve performance on scrolling, etc.	
	window.debounce = function(func, wait) {
		let timeout;
		return function() {
			const context = this, args = arguments;
			clearTimeout(timeout);
			timeout = setTimeout(() => func.apply(context, args), wait);
		};
	};

	
// Preload images on-demand ... also preload site-background if necessary
	window.preloadImg = function(imgName, device='both') {
		if ( device === 'mobile' && getDeviceW() > mobileCutoff ) return;
		if ( device === 'desktop' && getDeviceW() < mobileCutoff ) return;
				
		const preloadImage = new Image();
		preloadImage.src = site_dir.upload_dir_uri + "/" + imgName;		
	};
	
	if ( site_bg !== null ) {
		preloadImg('site-background.'+site_bg, 'desktop');
		preloadImg('site-background-phone.'+site_bg, 'mobile');		
	}
														   
	

/*
// Fade Out / In an element
	window.fadeOut = function(elementSel, speed=300, toggle_class="visible") {
		const elementObj = getObject(elementSel);
        elementObj.style.transition = `opacity ${speed}ms ease-in-out`;
        elementObj.style.opacity = 0;
        setTimeout(() => {
        	elementObj.classList.remove(toggle_class);
        	elementObj.style.display = 'none';
		}, speed);
	};

	window.fadeIn = function(elementSel, speed=300, toggle_class="visible") {
		const elementObj = getObject(elementSel);
        setTimeout(() => {
            if (elementObj) {
				elementObj.style.transition = `opacity ${fadeSpeed}ms ease-in-out`;
                elementObj.classList.add(toggle_class);
                elementObj.style.display = 'block';
                setTimeout(() => {
                    elementObj.style.opacity = 1;
                }, 10);
            }
        }, speed);
	};
*/		
})