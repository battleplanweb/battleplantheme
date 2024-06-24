document.addEventListener("DOMContentLoaded", function () {	"use strict";
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Basic site functions
# Automated processes
# DOM level functions


Re-factored 4/22/2024
--------------------------------------------------------------*/
			
/*--------------------------------------------------------------
# Basic site functionality
--------------------------------------------------------------*/
														   
	window.bp_page = getObject('#page');


// Determine whether or not to leave space for mobile menu header
	window.mobileMenuBarH = function () {
		return getDeviceW() > mobileCutoff ? 0 : document.getElementById('mobile-menu-bar').offsetHeight;
	};	
	
	
// Get "slug" of current webpage
	window.getSlug = function () {
		const pathSegments = window.location.pathname.split('/');
        return pathSegments.length > 1 ? pathSegments[1] : null;	
	};	

	
// Get variable from page's URL
	window.getUrlVar = function(key) {
    	const urlParams = new URLSearchParams(window.location.search);
    	return urlParams.get(key);
	};	
		
	
// Map "enter key" to mouse button click (for logo, or any div that is not inherently clickable)
	window.keyPress = function(trigger) {
		const elementObj = getObject(trigger);
		if (elementObj) {
			elementObj.addEventListener('keyup', function(event) {
				if (event.key === 'Enter') {
					this.click();
				}
			});
		}
	};		

	
// Create a "home link" for an element, including the header's .logo element
	window.linkHome = function(elementSel) {
		keyPress(elementSel);
		
		getObjects(elementSel).forEach(elementObj => {
			setAttributes(elementObj, {
				'tabindex':			'0',
				'role':				'button',
				'aria-label':		'Return to Home Page'
			});
			setStyles(elementObj, {
				'cursor':			'pointer'
			});
			elementObj.addEventListener('click', () => window.location = "/");
			getObjects('img', elementObj).forEach(img => setAttributes(img, {'aria-hidden': 'true'}));
		});
	};	
	
	linkHome('.logo');
	

// Copy one element's classes to an outer div
	window.copyClasses = function(copyTo, copyFrom = "img, iframe", additional = "") {
		getObjects(copyTo).forEach(elementObj => {
			getObjects(copyFrom, elementObj).forEach(child => {
				const classes = child.className.split(' ').filter(cls => cls.trim() !== '');
				elementObj.classList.add(...classes);
			});

			if (additional.trim()) { 
				elementObj.classList.add(additional); 
			}
		});
	};	
		
// Find & Replace text or html in a Div	
	window.replaceText = function(elementSel, find, replace, type="text", all=false) {
		if (all === true || all === 'true') { 
			find = new RegExp(find, "gi"); 
		}

		getObjects(elementSel).forEach(elementObj => {
			if (type === "text") {
				if (find !== "") {
					elementObj.textContent = elementObj.textContent.replace(find, replace);
				} else {
					elementObj.textContent = replace;
				}
			} else {
				if (find !== "") {
					elementObj.innerHTML = elementObj.innerHTML.replace(find, replace);
				} else {
					elementObj.innerHTML = replace;
				}
			}
		});
	};	
	 
		
// Add stroke to text & transparent objects
	window.addStroke = function(elementSel, width, topColor, bottomColor, leftColor, rightColor, text='true') {	
  		let shadow = "",
			steps = 16,
			spacing = width - 1;
		leftColor = leftColor || topColor;
		bottomColor = bottomColor || topColor;
		rightColor = rightColor || bottomColor;

		for (let i = 0; i < steps; i++) {
			const angle = (i * 2 * Math.PI) / steps;
			const cos = Math.round(10000 * Math.cos(angle)) / 10000;
			const sin = Math.round(10000 * Math.sin(angle)) / 10000;
			let color = cos <= 0 && sin <= 0 ? topColor :
						cos >= 0 && sin >= 0 ? bottomColor :
						cos <= 0 && sin >= 0 ? leftColor : rightColor;

			const position = `calc(${width}px * ${cos}) calc(${width}px * ${sin})`;
			if (text === 'true') {

				shadow += `${position} 0 ${color}`;
				if (i < (steps - 1)) shadow += ", ";
			} else {
				shadow += `drop-shadow(${position} 0 ${color}) `;
			}
		}
		
		const styleSheet = document.styleSheets[0];
		document.documentElement.style.setProperty('--js-text-fx-width', width);
		

		// Select the element and add the appropriate class
		getObjects(elementSel).forEach(element => {
			if (text === 'true') {
				const rule = `.js-text-fx-shadow { text-shadow: ${shadow}; }`;
				styleSheet.insertRule(rule, styleSheet.cssRules.length);
				element.classList.add('js-text-fx-shadow');
			} else {
				const rule = `.js-text-fx-filter { filter: ${shadow}; }`;
				styleSheet.insertRule(rule, styleSheet.cssRules.length);
				element.classList.add('js-text-fx-filter');
			}
		});
	};
	
	
// Handle locking divs to top of screen on scroll	
	let lockedDivs = [];
		
	window.lockDiv = function(elementSel, triggerSel=null, triggerAdj=0, docFlow=true, positionAdj=0) {
		const elementObj = getObject(elementSel);		
		let triggerObj = triggerSel ? getObject(triggerSel) : elementObj.nextElementSibling;
		triggerObj = isVisible(triggerObj) ? triggerObj : elementObj.parentNode.nextElementSibling;
		
		if ( docFlow !== true ) {
			elementObj.classList.add("no-doc-flow");	
		}
		
		const divData = {
			elementSel,
			elementObj,
			triggerPos: triggerObj.offsetTop - elementObj.offsetHeight - triggerAdj,
			lockPos: positionAdj
		};

		lockedDivs.push(divData);
	}

	window.lockAlign = function() {				
		const stuckEls = getObjects('.stuck, .fixed-at-load');
		if ( !stuckEls.length ) {
			bp_page.style.paddingTop = "0px";			
			return;
		}
		
		let currentPosition = 0,
			pagePadding = 0;

		stuckEls.forEach((stuck, index) => {
			if (lockedDivs[index] && typeof lockedDivs[index].lockPos !== 'undefined') {
				currentPosition += lockedDivs[index].lockPos;
			}			
			
			stuck.style.top = `${currentPosition}px`;
			const rect = stuck.getBoundingClientRect();
			currentPosition = rect.bottom;	
			
			if (!stuck.classList.contains('no-doc-flow')) {
				pagePadding += stuck.offsetHeight;
				bp_page.style.paddingTop = pagePadding + "px";
			}
		});
	};


	window.fixedAtLoad = function(elementSel) {
		const elementObj = getObject(elementSel);	
		elementObj.style.position = 'fixed';
		elementObj.classList.add("fixed-at-load");	
		
		lockAlign();
	};	
	
	window.addFaux = function(elementSel, fixed=true) {
		fixedAtLoad(elementSel);
	};
	
	window.lockMenu = function() {	
		lockDiv('#desktop-navigation');	
	};	
	
	window.controlLockedDivs = function() {
		let lockedH = 0;
		
		lockedDivs.forEach(divData => {		
			lockedH += divData.lockPos;
			divData.pageY = window.pageYOffset + lockedH;
			
			
			if ( divData.pageY >= divData.triggerPos) {
				if ( !divData.elementObj.classList.contains('stuck') ) {
               		divData.elementObj.classList.add("stuck");	
                    divData.elementObj.style.top = lockedH+"px";
          			lockAlign();		
				}
			} else {
				if ( divData.elementObj.classList.contains('stuck') ) {
					divData.elementObj.classList.remove("stuck");

					if ( !divData.elementObj.classList.contains('fixed-at-load') ) {
						divData.elementObj.style.top = "unset";
					}
          			lockAlign();		
				}
			}			
			
        	lockedH += divData.elementObj.offsetHeight;
		});
	}	
	
	
// Set up scroll listener to handle various events
	const debouncedScrollFunc = debounce(() => {
		if (typeof lockAlign === 'function') { lockAlign(); }
		if (typeof toggleScrollTop === 'function') { toggleScrollTop(); }
	}, 300);
	
    window.addEventListener('scroll', () => {			
		if (typeof controlLockedDivs === 'function') { controlLockedDivs(); }		
		if (typeof debouncedScrollFunc === 'function') { debouncedScrollFunc(); }
	});	
	
		
// Lock #colophon under rest of content
	window.lockColophon = function() {	
		const colophonObj = getObject('#colophon');	
		let colophonH = colophonObj.offsetHeight;

		if ( colophonH < getDeviceH() && !getObject('body').classList.contains('background-image') ) {	
			const pageObj = colophonObj.previousElementSibling;	
			pageObj.style.marginBottom = colophonH+"px";
			
			setStyles(colophonObj, {
				'position':			'fixed',
				'bottom':			getObject('.wp-gr.wp-google-badge').offsetHeight+'px',
				'width': 			'100%',
				'zIndex':			1
			});
		}
	};
	

// Animate the automated scrolling to section of content
	window.animateScroll = function(targetSel, topSpacer=0) {
        let targetObj = targetSel;
		let padding = 0.25;  // was 0.15, but reversed for faq page -- not sure how this will impact Trailside
		
		if (typeof targetSel === 'string') {
        	targetObj = getObject(targetSel);
			padding = targetSel.startsWith('#') ? 0.15 : padding;
		} 		
		
        if (targetObj && targetSel !== "#tab-description") {
			setTimeout(() => {
				const offset = window.innerHeight * padding;
				const targetRect = targetObj.getBoundingClientRect();
				const targetPos = window.scrollY + targetRect.top - offset - topSpacer;			

				window.scroll({
					top: 			targetPos,
					behavior: 		'smooth'
				});
			}, 100);
		}		
	};
														   
			
// When linking to a #hash, scroll down to ensure it isn't obscurred by locked elements	
	if (window.location.hash) {
		setTimeout(() => { animateScroll(window.location.hash);	}, 1000);
	}
		

// Toggle visibility of scroll-up and scroll-down buttons
	window.toggleScrollTop = function() {		
		getObjects('.scroll-top').forEach(button => {
			if ( window.pageYOffset >= window.innerHeight ) {
				button.classList.add('visible');
			} else {	
				button.classList.remove('visible');
			}		
		});
	}
	
	window.toggleScrollDown = function() {		
		getObjects('.scroll-down').forEach(button => {
			if ( window.pageYOffset >= window.innerHeight ) {
				button.classList.remove('visible');
			} else {	
				button.classList.add('visible');
			}		
		});
	}
		 

//Find screen position of element
	window.getPosition = function (elementSel, neededPos='top', scope='window') {
		const elementObj = getObject(elementSel);
		if ( !elementObj ) return;
		const rect = elementObj.getBoundingClientRect();
		const conW = rect.width;
		const conH = rect.height;
		let getLeft = rect.left + window.scrollX;
		let getTop = rect.top + window.scrollY;
		let getBottom = getTop + conH;
		let getRight = getLeft + conW;
		let getCenterX = getLeft + conW / 2;
		let getCenterY = getTop + conH / 2;
		
		if (scope !== 'window' && scope !== 'screen') {
			const scopeObj = getObject(scope);
			if ( !scopeObj ) return;
            const scopeRect = scopeObj.getBoundingClientRect();
            const scopeLeft = scopeRect.left + window.scrollX;
            const scopeTop = scopeRect.top + window.scrollY;
            getLeft -= scopeLeft;
            getTop -= scopeTop;
            getBottom -= scopeTop;
            getRight -= scopeLeft;
            getCenterX -= scopeLeft;
            getCenterY -= scopeTop;
		}
		
		switch (neededPos.toLowerCase()) {
			case 'left': case 'l': return getLeft;
			case 'top': case 't': return getTop;
			case 'bottom': case 'b': return getBottom;
			case 'right': case 'r': return getRight;
			case 'centerx': case 'center-x': return getCenterX;
			case 'centery': case 'center-y': return getCenterY;
			default: return null;
		}	
	};

	
//Find translateY or translateX of element
	window.getTranslate = function(elementSel, XorY = 'Y') {
		const style = window.getComputedStyle(getObject(elementSel));
		const transform = style.transform || style.webkitTransform || style.mozTransform;

		if (!transform || transform === 'none') {
			return 0; 
		}

		let matrixValues = transform.match(/matrix3d\((.+)\)/) || transform.match(/matrix\((.+)\)/);
		if (matrixValues) {
			matrixValues = matrixValues[1].split(', ');
			if (XorY.toUpperCase() === 'X') {
				return parseFloat(matrixValues.length === 16 ? matrixValues[12] : matrixValues[4]);
			} else if (XorY.toUpperCase() === 'Y') {
				return parseFloat(matrixValues.length === 16 ? matrixValues[13] : matrixValues[5]);
			}
		}

		return 0;
	};

	
// Filter archive page based on parameters, tags, URL variable, etc.  // Mill Pond Retrievers	
	window.filterArchives = function (field="", elementSel = ".section.archive-content", column = ".col-archive", speed = 300) {
		getObjects(elementSel).forEach(element => {
			element.style.opacity = '0';
			element.style.transition = `opacity ${speed}ms ease-in-out`;

			setTimeout(() => {
				getObjects(column).forEach(col => {
					if (field === "" || field === null) {
						col.style.display = '';
					} else {
						if (col.classList.contains(field)) {
							col.style.display = ''; 
						} else {
							col.style.display = 'none'; 
						}
					}
					col.style.clear = 'none'; 
				});

				element.style.opacity = '1';
			}, speed);
		});
	};
		

/* Deprecated 4/22/2024 - not in use
	// Handle the post filter button [get-filter-btn]
	$(".filter-btn").click(function() {
		var thisBtn = $(this), url = "?"+thisBtn.attr('data-url')+"=", flag=false;
		$("input:checkbox[name=choice]:checked").each(function() {
			if ( !flag ) {
				url = url + $(this).val();
				flag = true;
			} else {
				url = url  + "," + $(this).val();
			}         
		});
		window.location = url;
	});
*/
		
/* Deprecated 4/22/2024 - removed, as it is not in use	
// Tabbed Content - control changing and animation of tabbed content
	keyPress('ul.tabs li');
	const ul_tabs = getObjects('ul.tabs li');
	
	ul_tabs.forEach(tab => {
		tab.addEventListener('click', () => {
			const tab_id = tab.getAttribute('data-tab');
			const fadeSpeed = 150;
		
			ul_tabs.forEach(tab => tab.classList.remove('current'));
			tab.classList.add('current');

			getObjects('.tab-content').forEach(content => {
				fadeOut(content, fadeSpeed, 'current');
			});
			
			fadeIn(getObject(tab_id), fadeSpeed, 'current');
	});
*/	
		
		
/* Deprecated 4/22/2024 - removed, as it is not in use	
	// Prepare data for javascript that was encodded in PHP
	window.prepareJSON = function (data) {
		data = data.replace(/%7B/g, '{').replace(/%7D/g, '}').replace(/%22/g, '"').replace(/%3A/g, ':').replace(/%2C/g, ',');				
		return $.parseJSON(data);
	};	
*/
	
	
// Cover container with direct sibling, then slide sibling out of the way to reveal container
	window.revealDiv = function (elementSel, delay=0, speed=1000, offset="0%") {
		offset = parseInt(offset) / 100;	
		const elementObj = getObject(elementSel);
 		const nextElemObj = elementObj.nextElementSibling;
		nextElemObj.style.transform = `translateY(-${elementObj.offsetHeight}px)`;
    	nextElemObj.style.transitionDuration = '0ms';
		
		setTimeout(() => {
			const observer = new IntersectionObserver((entries) => {
				entries.forEach(entry => {
					if (entry.isIntersecting) {
						nextElemObj.style.transitionDuration = `${speed}ms`;
						nextElemObj.style.transform = 'translateY(0)';
					}
				});
			}, {
				root: null,
				rootMargin: '0px',
				threshold: offset
			});

			observer.observe(elementObj);
		}, delay);
	};	
	
		
// Button to reveal a hidden div
	window.btnRevealDiv = function(buttonSel, elementSel, top=0, speed=300) {
		top += mobileMenuBarH();
		const elementObj = getObject(elementSel);
		const origDisplay = getComputedStyle(elementObj).display;
		elementObj.style.display = 'none';

		const button = getObject(buttonSel);

		button.addEventListener('click', () => {
			elementObj.style.display = origDisplay; 
			animateScroll(elementSel, top, speed);
		});
	};
	
/*--------------------------------------------------------------
# Automated processes
--------------------------------------------------------------*/	

// Set up American Standard logo to link to American Standard website	
	getObjects('img').forEach(img => {
		if (img.src.includes('hvac-american-standard/american-standard')) {
			const anchor = document.createElement('a');
			setAttributes(anchor, {
				'href': 		'https://www.americanstandardair.com/',
				'target': 		'_blank',
				'rel': 			'noreferrer'
			});

			img.parentNode.insertBefore(anchor, img);
			anchor.appendChild(img);
		}
	});
	
	
// Redirect to 'thank you' page after form submission, to avoid double submissions
	window.document.addEventListener( 'wpcf7mailsent', function( event ) { 
		location = '/email-received/';
	}, false ); 
	
	
// Duplicate menu button text onto the button BG
	getObjects('.main-navigation ul.main-menu li > a').forEach(link => {
		link.parentNode.setAttribute('data-content', link.innerHTML);
	});

	
// This Contact Form 7 script blocked by Content Security Policy	
	var ak_js = document.getElementById( 'ak_js' ), el, destinations = [];

	if( !ak_js ) {
		ak_js = document.createElement( 'input' );
		ak_js.type = 'hidden';
		ak_js.name = ak_js.id = 'ak_js';
	} else {
		ak_js.parentNode.removeChild( ak_js );
	}

	ak_js.value = ( new Date() ).getTime();

	if ( el = document.getElementById( 'commentform' ) ) { destinations.push( el ); }
	if ( ( el = document.getElementById( 'replyrow' ) ) && ( el = el.getElementsByTagName('td') ) ) { destinations.push( el.item(0) ); }
	for ( var i = 0, j = destinations.length; i < j; i++ ) { destinations[i].appendChild( ak_js ); }
	

// Keep functions from causing errors if not defined in other parts of the code
	const functions = ["parallaxBG", "parallaxDiv", "magicMenu", "splitMenu", "addMenuLogo", "addMenuIcon", "desktopSidebar"];
	functions.forEach(func => {
		if (typeof window[func] !== 'function') {
			window[func] = function() {};
		}
	});
		
	
/* Deprecated 4/22/2024 - moves search bar up to top of mobile menu, and makes regular menu disappear --- not sure what the reason is, so removing it for now	
// Control animation for menu search box
	setTimeout(function() {
		$('div.menu-search-box a.menu-search-bar').each(function() {
			var searchBar = $(this), inputBox = searchBar.find('input[type="search"]'), inputW = searchBar.outerWidth(), magW = (searchBar.find('i.fa').outerWidth()) * 1.3;
			if ( $(this).hasClass('reveal-click')) { 
				searchBar.css({ "width": magW+"px" }); 
				searchBar.click(function() {
					searchBar.animate( { "width":inputW+'px' }, 150, function() { if ( typeof centerSubNav === 'function' ) { setTimeout(function() {centerSubNav();}, 300); } });	
				});
			}
			if ( !isApple() ) {
				inputBox.focus(function() {
					var inputPos = -(getPosition(searchBar, 'top') - mobileMenuBarH() - 25);
					$('#mobile-navigation > #mobile-menu').css({"position":"relative"}).animate({ "margin-top": inputPos + "px" }, 300);
				});
				inputBox.blur(function() {
					$('#mobile-navigation > #mobile-menu').css({"position":"relative"}).animate({ "margin-top": "0px)" }, 300);
				});	
			}
		}); 
	}, 300);
*/
		

/*--------------------------------------------------------------
# DOM level functions
--------------------------------------------------------------*/

// Clone div and move the copy to new location
	window.cloneDiv = function (elementSel, anchorSel, position="after") {
		const elementObj = getObject(elementSel);
		const anchorObj = getObject(anchorSel);
		if ( !elementObj || !anchorObj ) return;

		const cloneObj = elementObj.cloneNode(true);

		switch (position) {
			case "before":
				anchorObj.parentNode.insertBefore(cloneObj, anchorObj);
				break;
			case "top": case "start": case "inside":
				anchorObj.insertBefore(cloneObj, anchorObj.firstChild);
				break;
			case "bottom": case "end": 
				anchorObj.appendChild(cloneObj);
				break;
			case "after": default: 
				anchorObj.parentNode.insertBefore(cloneObj, anchorObj.nextSibling);
				break;
		}
	};	

	
// Move a single div to another location
	window.moveDiv = function (elementSel, anchorSel, position="after") {
		const elementObj = getObject(elementSel);
		const anchorObj = getObject(anchorSel);
		if ( !elementObj || !anchorObj ) return;

		switch (position) {
			case "before":
				anchorObj.parentNode.insertBefore(elementObj, anchorObj);
				break;
			case "top": case "start": case "inside":
				anchorObj.insertBefore(elementObj, anchorObj.firstChild);
				break;
			case "bottom": case "end": 
				anchorObj.appendChild(elementObj);
				break;
			case "after": default: 
				anchorObj.parentNode.insertBefore(elementObj, anchorObj.nextSibling);
				break;
		}
	};
	

// Move multiple divs to another location
	window.moveDivs = function (wrapperSel, elementSel, anchorSel, position="after") {
		getObjects(wrapperSel).forEach(wrapper => {
			const elementObj = getObjects(elementSel, wrapperSel);
			const anchorObj = getObjects(anchorSel, wrapperSel);
			if ( !elementObj || !anchorObj ) return;

			anchorObj.forEach(anchor => {
				elementObj.forEach(element => {
					switch (position) {
						case "before":
							anchorObj.parentNode.insertBefore(element, anchorObj);
							break;
						case "top": case "start": case "inside":
							anchorObj.insertBefore(element, anchorObj.firstChild);
							break;
						case "bottom": case "end": 						
							anchorObj.appendChild(element);
						case "after": default:
							anchorObj.parentNode.insertBefore(element, anchorObj.nextSibling);
							break;
					}
				});
			});
		});
	};
	
	
// Add a div within an existing div
	window.addDiv = function (targetSel, newHTML="<div></div>", position="after") {
		const targetObj = getObjects(targetSel);
		if ( !targetObj.length ) return;
		
		targetObj.forEach(target => {
			const tempElem = document.createElement('div');
			tempElem.innerHTML = newHTML;
			const newDiv = tempElem.firstChild;

			switch (position) {
				case "before":
					target.parentNode.insertBefore(newDiv.cloneNode(true), target);
					break;
				case "top": case "start":
					target.insertBefore(newDiv.cloneNode(true), target.firstChild);
					break;
				case "bottom": case "end":
					target.appendChild(newDiv.cloneNode(true));
					break;
				case "after": default:
					if (target.nextSibling) {
						target.parentNode.insertBefore(newDiv.cloneNode(true), target.nextSibling);
					} else {
						target.parentNode.appendChild(newDiv.cloneNode(true));
					}
					break;
			}
		});
	};
	

// Wrap a div inside a newly formed div
	window.wrapDiv = function(targetSel, newHTML="<div></div>", position="outside") {
		const targetObj = getObjects(targetSel);
		if (!targetObj.length) return;

		const createWrapper = () => {
			const tempElem = document.createElement('div');
			tempElem.innerHTML = newHTML.trim();
			return tempElem.firstElementChild;
		};

		targetObj.forEach(target => {
			const wrapper = createWrapper();
			let innerWrapper = getObject('*', wrapper);			
			if ( !innerWrapper ) { innerWrapper = wrapper; }

			if (position === "outside") {
				target.parentNode.insertBefore(wrapper, target);
				innerWrapper.appendChild(target);
			} else {
				while (target.firstChild) {
					innerWrapper.appendChild(target.firstChild);
				}
				target.appendChild(wrapper);
			}
		});
	};
		

// Wrap multiple divs inside a newly formed div
	window.wrapDivs = function(targetSel, newHTML="<div></div>") {
		const targetObj = getObjects(targetSel);
		if (!targetObj.length) return;
		
		const tempElem = document.createElement('template');
		tempElem.innerHTML = newHTML.trim();
		const newDiv = tempElem.content.firstChild;

		targetObj[0].parentNode.insertBefore(newDiv, targetObj[0]);

		Array.from(targetObj).forEach(target => {
			newDiv.appendChild(target);
		});
	};	
	
	
// Size a frame according to the image or video inside it
	window.sizeFrame = function(targetSel, frameSel=".frame", scale="0.9") {
		const targetObj = getObjects(targetSel);
		if (!targetObj.length) return;

		targetObj.forEach(target => {			
			if (targetSel.includes('video')) {			
				const findSize = target.parentElement,
					  frame = getObject(frameSel, target),
					  frameW = findSize.offsetWidth,
					  frameH = findSize.offsetHeight;
					if (frame) {
						setStyles(frame, {
							'width':			`${frameW}px`,
							'height':			`${frameH}px`,
							'marginTop':		`-${frameH}px`
						});
					}
			} else {				
				const images = getObjects('img', target),
					  frame = getObject(frameSel, target);				
				let dimensionsSet = false;
				
				for (const img of images) {
					if (dimensionsSet) break;
					img.addEventListener('load', () => {						
						if (dimensionsSet) return;
						
						const frameW = img.offsetWidth,
							  frameH = img.offsetHeight;
						if (frame) {
							setStyles(frame, {
								'width':			`${frameW}px`,
								'height':			`${frameH}px`,
								'marginBottom':		`-${frameH}px`
							});
						}
						dimensionsSet = true;
						addCSS(`${targetSel} img`, `transform: scale(${scale})`);
					});
					if (img.complete) img.dispatchEvent(new Event('load'));
					if (dimensionsSet) break;
				}										
			}
		});
	};
	
	
// Turn SVG into an element's background image
	window.svgBG = function(svgSel, elementSel, position="top") {
		const svgObj = getObject(svgSel);
		const elementObj = getObject(elementSel);
		if (!svgObj || !elementObj) return;

		const svgClone = svgObj.cloneNode(true);
		svgClone.style.position = 'absolute';

		switch (position) {
			case "bottom":
				elementObj.appendChild(svgClone);
				break;
			case "before": case "start":
				elementObj.parentNode.insertBefore(svgClone, elementObj);
				break;
			case "after": case "end":
				elementObj.parentNode.insertBefore(svgClone, elementObj.nextSibling);
				break;
			case "top": default:
				elementObj.insertBefore(svgClone, elementObj.firstChild);
				break;
		}
	};		
	
})