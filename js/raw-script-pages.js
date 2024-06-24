document.addEventListener("DOMContentLoaded", function () {	"use strict";
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Set up sidebar
# Set up animation
# Set up pages
# Screen resize
# ADA compliance
# Delay parsing of JavaScript
 

Re-factor complete 5/9/2024

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Set up sidebar
--------------------------------------------------------------*/
	window.setupSidebar = function (compensate=0, sidebarScroll=true) {
		
// Add classes for first, last, even and odd widgets
		window.labelWidgets = function () {
			const visibleWidgets = getObjects(".widget:not(.hide-widget)");
			if (visibleWidgets.length) {
				visibleWidgets[0].classList.add("widget-first");
				visibleWidgets[visibleWidgets.length - 1].classList.add("widget-last");
				visibleWidgets.forEach((widget, index) => {
					widget.classList.remove("widget-even", "widget-odd"); // Clear previous classes
					widget.classList.add(index % 2 === 0 ? "widget-odd" : "widget-even");
				});
			}
		};
		
    // Shuffle array elements
		window.shuffleElements = function (nodeList) {
			let elements = Array.from(nodeList); 
			for (let i = elements.length - 1; i > 0; i--) {
				const j = Math.floor(Math.random() * (i + 1));
				[elements[i], elements[j]] = [elements[j], elements[i]]; // Swap elements
			}
			return elements;
		};

	// Get elements and shuffle non-locked widgets
		const widgets = getObjects('.widget:not(.lock-to-top):not(.lock-to-bottom):not(.widget-financing):not(.widget-event)');
		const parent = widgets[0] ? widgets[0].parentNode : null;
		const bottomWidgets = getObjects(".widget.lock-to-bottom");
		const financingWidgets = getObjects('.widget.widget-financing');
		const eventWidgets = getObjects('.widget.widget-event');

		// Remove elements from DOM to reinsert in new order
		widgets.forEach(widget => widget.remove());
		bottomWidgets.forEach(widget => widget.remove());
		financingWidgets.forEach(widget => widget.remove());
		eventWidgets.forEach(widget => widget.remove());

		// Append shuffled elements
		shuffleElements(eventWidgets).forEach(widget => parent.appendChild(widget));
		shuffleElements(financingWidgets).forEach(widget => parent.appendChild(widget));
		shuffleElements(widgets).forEach(widget => parent.appendChild(widget));
		bottomWidgets.forEach(widget => parent.appendChild(widget));

		// Check screen type and apply labels
		if (document.body.classList.contains('screen-mobile')) {
			labelWidgets();
		} else {
			desktopSidebar(compensate, sidebarScroll);
		}
	};

/*--------------------------------------------------------------
# Set up animation
--------------------------------------------------------------*/	
// Set up easing
	window.convertBezier = function(easing) {
		const easingMap = {
			"easeInCubic": 		'cubic-bezier(0.550, 0.055, 0.675, 0.190)',
			"easeInQuart": 		'cubic-bezier(0.895, 0.030, 0.685, 0.220)',
			"easeInQuint": 		'cubic-bezier(0.755, 0.050, 0.855, 0.060)',
			"easeInExpo": 		'cubic-bezier(0.950, 0.050, 0.795, 0.035)',
			"easeInBack": 		'cubic-bezier(0.600, -0.280, 0.735, 0.045)',
			"easeOutCubic": 	'cubic-bezier(0.215, 0.610, 0.355, 1.000)',
			"easeOutQuart": 	'cubic-bezier(0.165, 0.840, 0.440, 1.000)',
			"easeOutQuint": 	'cubic-bezier(0.230, 1.000, 0.320, 1.000)',
			"easeOutExpo": 		'cubic-bezier(0.190, 1.000, 0.220, 1.000)',
			"easeOutBack":	 	'cubic-bezier(0.175, 0.885, 0.320, 1.275)',
			"easeInOutCubic": 	'cubic-bezier(0.645, 0.045, 0.355, 1.000)',
			"easeInOutQuart": 	'cubic-bezier(0.770, 0.000, 0.175, 1.000)',
			"easeInOutQuint": 	'cubic-bezier(0.860, 0.000, 0.070, 1.000)',
			"easeInOutExpo":	'cubic-bezier(1.000, 0.000, 0.000, 1.000)',
			"easeInOutBack": 	'cubic-bezier(0.680, -0.550, 0.265, 1.550)'
		};
		return easingMap[easing] || easing;
	}
		
	
// Overflow: assist in hiding overflow during animation and then making it visible again
	window.animateOverflow = function(container, delay=2000) {
		const containerObj = getObject(container);
		if (!containerObj) return;

		containerObj.style.overflow = "hidden";
		setTimeout(() => {
			containerObj.style.overflow = "visible";
		}, delay);
	};
	
														   
// Convert waypoint offset to intersection observer offset														   
	window.convertOffset = function(offset) {
		return offset.includes('%') ? getDeviceH() * (1 - (parseInt(offset.replace('%', ''), 10) / 100)) : offset;
	};							   
														   
	
// Function to create keyframes for animation
	function animationKeyframes(effect) {	
		effect = effect.toLowerCase();	
		
		let offsetTransformX = '0';				
		let offsetTransformY = '0';				
		let offsetScale = '1';
		let offsetRotate = '0';
		let offsetOpacity = '1';
		let offsetSkewX = '0';
		let offsetFilter = 'none', mainFilter = 'none';
		let midEffect = '';
				
		if (effect.includes('left')) {
			offsetTransformX = '-100';
		}
		
		if (effect.includes('right')) {
			offsetTransformX = '100';
		}
		
		if (effect.includes('up')) {
			offsetTransformY = '100';
		}
		
		if (effect.includes('down')) {
			offsetTransformY = '-100';
		}
		
		if (effect.includes('fade')) {
			offsetOpacity = '0';
		}
		
		if (effect.includes('zoom')) {
			offsetScale = '0';
		}
		
		if (effect.includes('drop')) {
			offsetScale = '1.4';
			offsetOpacity = '0';
		}
		
		if (effect.includes('blur')) {
			offsetFilter = 'blur(10px)';
			mainFilter = 'blur(0)';
		}
		
		if (effect.includes('roll') && effect.includes('left')) {
			offsetRotate = '-120';
		}
		
		if (effect.includes('roll') && effect.includes('right')) {
			offsetRotate = '120';
		}
		
		if (effect.includes('spin') && effect.includes('left')) {
			offsetRotate = '-1080';
		}
		
		if (effect.includes('spin') && effect.includes('right')) {
			offsetRotate = '1080';
		}
		
		if (effect.includes('back')) {
			offsetScale = '0.7';
			midEffect = '80% { transform: scale(0.7) translate(0, 0); }'
		}
		
		if (effect.includes('lightspeed')) {
			offsetSkewX = '30';
		}
		
		if (effect.includes('jackinthebox')) {
			offsetScale = '0';
			offsetRotate = '40';
			midEffect = `50% { transform: rotate(-${offsetRotate / 2}deg); } 70% { transform: rotate(${offsetRotate / 4}deg); }`;
		}	
		
		if (effect.includes('small')) {
			offsetTransformX = offsetTransformX * 0.07;
			offsetTransformY = offsetTransformY * 0.07;
		}		
		
		if (effect.includes('slight')) {
			offsetTransformX = offsetTransformX * 0.02;
			offsetTransformY = offsetTransformY * 0.02;
		}	
		
		const offsetTransform = `scale(${offsetScale}) translate(${offsetTransformX}vw, ${offsetTransformY}vh) rotate(${offsetRotate}deg) skewX(${offsetSkewX}deg)`;
		const mainTransform = 'scale(1) translate(0, 0) rotate(0deg) skewX(0)';		
				
		let startTransform = offsetTransform;
		let endTransform = mainTransform;
		let startFilter = offsetFilter;
		let endFilter = mainFilter;
		let startOpacity = offsetOpacity;
		let endOpacity = '1';	
		
		if (effect.includes('out')) {
			startTransform = mainTransform;
			endTransform = offsetTransform;
			startFilter = mainFilter;
			endFilter = offsetFilter;
			startOpacity = '1';
			endOpacity = offsetOpacity;
		}
	
		const style = document.createElement('style');
		document.head.appendChild(style);
		
		const animationName = effect;
		
		const keyframes = `
			@keyframes ${animationName} {
				0% {
					transform: ${startTransform};
					opacity: ${startOpacity};
					filter: ${startFilter};
				}
				${midEffect}
				100% {
					transform: ${endTransform};
					opacity: ${endOpacity};
					filter: ${endFilter};
				}
			}
		`;
		style.sheet.insertRule(keyframes, 0);
		return animationName;
	}
	
	
// Applying styles with animation
	function applyAnimation(elementObj, speed, delay, easing, fillMode, type='keyframes') {
		if ( type === 'keyframes' ) {
			setStyles(elementObj, {
				'animationDuration':		`${speed}s`,
				'animationDelay':			`${delay}s`,
				'animationTimingFunction':	convertBezier(easing),
				'animationFillMode':		`${fillMode}`
			});	
		} else {
			setStyles(elementObj, {
				'transitionDuration':		`${speed}s`,
				'transitionDelay':			`${delay}s`,
				'transitionTimingFunction':	convertBezier(easing),
			});	
		}
	}
	
	
// Handles changing classes of object during animation
	function beginAnimation(elementObj, animationName) {
		elementObj.classList.remove('animation-queued');	
		elementObj.classList.add('animation-delayed');	

		const animating = () => {
			elementObj.classList.remove('animation-delayed');	
			elementObj.classList.add('animation-in-progress');	
		};

		const animated = () => {
			elementObj.classList.remove('animation-in-progress');	
			elementObj.classList.add('animation-complete');	
			elementObj.removeEventListener('animationstart', animating);
			elementObj.removeEventListener('animationend', animated);
		};

		// Add listeners
		elementObj.addEventListener('animationstart', animating);
		elementObj.addEventListener('animationend', animated);

		setStyles(elementObj, {
			'animationName':			animationName,
		});
	}
 
	
// Setting up an observer for animated objects
	function observeVisibility(elementObj, offset, animationName) {
		const observer = new IntersectionObserver((entries) => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {	
					if ( animationName !== false ) {	
						beginAnimation(entry.target, animationName);
					} else {		
						entry.target.classList.remove('animation-queued');	
						entry.target.classList.add('animate');
						entry.target.classList.add('animation-complete');	
					}
					observer.unobserve(entry.target);
				}
			});
		}, {
			rootMargin: `0px 0px -${convertOffset(offset)}px 0px` 
		});

		observer.observe(elementObj);
	}


// Animate single element
	window.animateDiv = function(elementSel, effect, delay=0, offset="100%", speed=1000, easing='ease') {
		const elementObj = getObjects(elementSel);
		if (!elementObj.length) return;
		
		elementObj.forEach(element => {
			element.classList.add('animation-queued');	

			const animationName = animationKeyframes(effect);
    		applyAnimation(element, speed / 1000, delay / 1000, easing, 'both');
    		observeVisibility(element, offset, animationName);
		});
	}		

	
// Animate multiple elements in a container
	window.animateDivs = function(elementSel, effect1, effect2, initDelay=0, eachDelay=100, offset="100%", speed=1000, easing='ease') {
		const elementObj = getObjects(elementSel);
		if (!elementObj.length) return;		
		
		const parents = new Map();
		elementObj.forEach(element => {
			const containerObj = element.parentElement;
			if (!containerObj || parents.has(containerObj)) return;
			parents.set(containerObj, element);
		});
		
		parents.forEach((elem, parent) => {
			let children = Array.from(getObjects(elementSel, parent));
			if (!children.length) return;

			let elementY, delay=0;

			children.forEach((child, index) => {
				child.classList.add('animation-queued');	

				setTimeout(() => {
					const effect = index % 2 === 0 ? effect1 : effect2;
					const animationName = animationKeyframes(effect);	
					const childT = getPosition(child, 'top', parent);
					delay = (childT - elementY) > 50 ? delay : delay += eachDelay;
					elementY = childT;			

					applyAnimation(child, speed / 1000, delay / 1000, easing, 'both');
					observeVisibility(child, offset, animationName);	
				}, initDelay);
			});
		});
	};
	

// Animate multiple elements in a grid
	window.animateGrid = function(elementSel, effect1, effect2, effect3, initDelay=0, eachDelay=100, offset="100%", mobile="false", speed=1000, easing='ease') {
		const elementObj = getObjects(`${elementSel}:first-child`);
		if (!elementObj.length) return;
		
		elementObj.forEach(element => {
			element.classList.add('animation-queued');	

			const containerObj = element.parentElement;
			if (!containerObj) return;

			const children = Array.from(containerObj.children);
			const spanAllChildren = children.filter(child => child.classList.contains('span-all'));
	        const otherChildren = children.filter(child => !child.classList.contains('span-all'));
            const assignFX = {
                1: [effect1],
                2: [effect2, effect3],
                3: [effect2, effect1, effect3],
                4: [effect2, effect1, effect1, effect3]
            };
			let elementY, delay=0;

			setTimeout(() => {
				spanAllChildren.forEach(child => {
					const animationName = animationKeyframes(effect1);
					applyAnimation(child, speed / 1000, eachDelay / 1000, easing, 'both');
					observeVisibility(child, offset, animationName);
				});
				
				const num = otherChildren.length;				
				
				otherChildren.forEach((child, index) => {
					let effect = num > 4 ? effect1 : assignFX[num][index];
					const animationName = animationKeyframes(effect);	
					const childT = getPosition(child, 'top', containerObj);
					delay = (childT - elementY) > 50 ? delay : delay += eachDelay;
					elementY = childT;

					applyAnimation(child, speed / 1000, delay / 1000, easing, 'both');
					observeVisibility(child, offset, animationName);
				});
			}, initDelay);
		});
	};
	
	
// Animate single element (using CSS in site-style.css)	
	window.animateCSS = function(elementSel, delay=0, offset="100%", speed=1000, easing='ease') {
		const elementObj = getObjects(elementSel);
		if (!elementObj.length) return;
		
		elementObj.forEach(element => {
			element.classList.add('animation-queued');	
			applyAnimation(element, speed / 1000, delay / 1000, easing, 'both', 'transition');
			observeVisibility(element, offset, false);
		});
	};

	
// Animate the hover effect of a button, and allow to finish (even if mouse out)	
	window.animateBtn = function(menuSel=".menu", notClass="li:not(.active)", animateClass="go-animated", inOut='both') {
		if (!animateClass) return;
		
		const menuObj = getObjects(`${menuSel} ${notClass}`);

		const addAnimationClass = (element) => {
			element.classList.add(animateClass);
			element.addEventListener('animationend', function() {
				element.classList.remove(animateClass);
			}, { once: true }); 
		};

		menuObj.forEach(item => {
			if (inOut === "in") {
				item.addEventListener('mouseenter', () => addAnimationClass(item));
			} else if (inOut === "out") {
				item.addEventListener('mouseleave', () => addAnimationClass(item));
			} else {
				item.addEventListener('mouseenter', () => addAnimationClass(item));
				item.addEventListener('mouseleave', () => addAnimationClass(item));
			}
		});
	};

	
// Split string into characters for animation
	window.animateCharacters = function(elementSel, effect1, effect2, initDelay=0, eachDelay=100, offset="100%", words=false) {
		let speed = 1000, easing = "easeOutQuart";
		const elementObj = getObjects(elementSel);

		elementObj.forEach(element => {
			if (element.innerHTML.includes("<")) return;

			let contentArray = words ? element.textContent.split(" ") : element.textContent.split("");
			let newContent = contentArray.map((item, index) => {
				if (words) {
					return `<span class="wordSplit animate">${item}</span>`;
				} else {
					return `<span class="charSplit animate">${item === " " ? "&nbsp;" : item}</span>`;
				}
			}).join(words ? "&nbsp;" : "");

			element.innerHTML = newContent; 

			const animatedElements = getObjects(".animate", element);
			animatedElements.forEach((element, index) => {
				const effect = index % 2 === 0 ? effect1 : effect2;
				const animationName = animationKeyframes(effect);	
				applyAnimation(element, speed / 1000, eachDelay / 1000, easing, 'both');
				observeVisibility(element, offset, animationName);				
			});
		});
	};
	
	
/*--------------------------------------------------------------
# Set up pages
--------------------------------------------------------------*/
// Remove empty & restricted elements
	getObjects('p:empty, .archive-intro:empty, div.restricted, div.restricted + ul, li.menu-item + ul.sub-menu').forEach(el => {
		if (!el.getAttribute('role') || el.getAttribute('role') !== "status") {
			el.remove();
		}
	});
	
// Remove current page from footer-menu
	getObjects('ul#footer-menu a').forEach(a => {
		if (a.getAttribute('href') === window.location.href) {
			a.parentElement.style.display = 'none';
		}
	});
	
// Add .page-begins to the next section under masthead for purposes of locking .top-strip
	const pageBegins = getObject('#masthead + section');
	if (pageBegins) {
		pageBegins.classList.add('page-begins');
	}

// Add "noFX" class to img if it appears in any of the parent divs
	getObjects('div.noFX').forEach(div => {
		getObjects('img, a', div).forEach(elem => {
			elem.classList.add('noFX');
		});
	});

// Set "first page" cookie		
	if (!getCookie('first-page')) {
		document.body.classList.add('first-page');
		setCookie('first-page', 'set');
	} else {
		document.body.classList.add('not-first-page');
	}

// Set "pages viewed" cookie
	let pageviews = getCookie('pages-viewed');
	if ( !pageviews ) { 	
		setCookie('pages-viewed', 1);
	} else {
		pageviews++;
		setCookie('pages-viewed', pageviews);
	}

// Set "home-url" cookie
	if (document.body.classList.contains('alt-home')) {
		const getAltLoc = location.pathname + location.search;
		const newHome = getAltLoc.replace(/\//g, "");
		setCookie('home-url', newHome);
	}
	
// Set city-specific landing page as new home page
	const homeUrl = getCookie('home-url');
	if (homeUrl) { 
		const anchors = getObjects(`a[href="${window.location.origin}"], a[href="${window.location.origin}/"]`);
		anchors.forEach(anchor => {
			anchor.setAttribute("href", `${window.location.origin}/${homeUrl}`);
		});
	}

	/* 2024-05-27
// Set Google Ads landing page as user-display-loc cookie
	if ( typeof google_ad_location !== 'undefined' && google_ad_location != null ) {
		setCookie('user-display-loc', google_ad_location);
	}	
*/
	
// Fade in lazy loaded images removed 6/24/24 because of Kin-Tec product pics not loading
														   /*
	getObjects('img').forEach(img => img.classList.add('unloaded'));
	getObjects('#loader img').forEach(img => img.classList.remove('unloaded'));
	getObjects('img').forEach(img => {
		img.addEventListener('load', function() {
			this.classList.remove('unloaded');
		});
		if (img.complete) {
			img.dispatchEvent(new Event('load'));
		}
	});
*/
	
// Add star icons to reviews and ratings
	getObjects('.testimonials-rating').forEach(function(element) {
		const getRating = parseInt(element.textContent.trim(), 10);
		const stars = ['star-o', 'star-o', 'star-o', 'star-o', 'star-o'];

		for (let i = 0; i < getRating; i++) {
			stars[i] = 'star';
		}

		let replaceRating = `<span class="rating rating-${getRating}-star" aria-hidden="true"><span class="sr-only">Rated ${getRating} Stars</span>`;
		stars.forEach(star => {
			replaceRating += `<span class="icon ${star}"></span>`;
		});
		replaceRating += '</span>';

		element.innerHTML = replaceRating;
	});
	
	
// Ensure customer's initial (letter) for anonymous icon is positioned appropriately
	const svgObj = getObject('svg.anonymous-icon');
	const initialObj = getObject('.testimonials-generic-letter');
	
	if (svgObj && initialObj) {
		const svgH = svgObj.clientHeight;  // Get the height of the SVG element
		const initialH = initialObj.clientHeight;  // Get the height of the SVG element
		
		const paddingT = (svgH - initialH) * 0.82;

		getObjects('.testimonials-generic-letter').forEach(letter => {
			letter.style.height = `${svgH}px`;          // Set the height to match the SVG
			letter.style.paddingTop = `${paddingT}px`;  // Apply calculated padding to vertically center
			letter.style.boxSizing = 'border-box';      // Include padding in the height calculation
		});
	}

	
// Ensure that Form labels have enough width & remove double asterisks
	window.formLabelWidth = function () {
		getObjects('.wpcf7 form, .wpcf7 form .flex').forEach(form => {
			let labelMaxWidth = 0;

			getObjects('.form-input.width-default label', form).forEach(label => {
				const labelWidth = label.offsetWidth;
				labelMaxWidth = labelWidth > labelMaxWidth ? labelWidth : labelMaxWidth;
			});

			if (labelMaxWidth > 0) {
				getObjects('.form-input.width-default', form).forEach(inputContainer => {
					inputContainer.style.gridTemplateColumns = `${labelMaxWidth}px 1fr`;
				});
			}
		});
	
		getObjects('abbr.required, em.required, span.required').forEach(element => element.textContent = "");
	};
	
	
// Move User Switching bar to top
	moveDiv('#user_switching_switch_on','#page','before');
	

// Add "active" & "hover" classes to menu items, assign roles for ADA compliance
	getObjects(".main-navigation ul.main-menu, .widget-navigation ul.menu").forEach(menu => {
        setAttributes(menu, {
            'role':				'menubar',
            'aria-label':		'Main Menu'
        });
	});
	
	getObjects(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").forEach(subMenu => {
		subMenu.setAttribute('role', 'menu');
	});
	
	getObjects(".main-navigation li, .widget-navigation li").forEach(li => {
		li.setAttribute('role', 'menuitem');
	});
	
	getObjects(".main-navigation a[href], .widget-navigation a[href]").forEach(link => {
		link.setAttribute('role', 'none');
	});

	// Handle active states and hover effects	
	const manageHover = (menuSel, currents) => {
		currents.forEach(current => {
			current.classList.add("active");
			getObject('a', current).setAttribute('aria-current', 'page');
		});
		getObjects(menuSel).forEach(menuObj => {
			menuObj.addEventListener('mouseenter', () => {
				currents.forEach(c => {
					c.classList.remove("active");
					c.classList.add("dormant");
				});
				menuObj.classList.add("hover");
			});
			menuObj.addEventListener('mouseleave', () => {
				menuObj.classList.remove("hover");
				currents.forEach(c => {
					c.classList.remove("dormant");
					c.classList.add("active");
				});
			});
		});
	};
	
	const currents = getObjects(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor");	

	const subCurrents = getObjects(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");
	
	manageHover(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li", currents)
	manageHover(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li", subCurrents)
	

// Animate scrolling when pressing a button with #hash as link
	getObjects('a[href^="#"]:not(.carousel-control-next):not(.carousel-control-prev)').forEach(link => {
        link.setAttribute('data-target', link.getAttribute('href'));
        link.removeAttribute('href');		
		link.addEventListener('click', () => {
			const target = link.getAttribute('data-target');
			const targetObj = getObject(target);
			const compensate = Number(targetObj?.getAttribute('data-hash')) || 0; 
					
		// if target #hash is on this page, scroll to it, otherwise link to the correct page
            if (targetObj) {
                setTimeout(() => animateScroll(target, compensate), 25);
            } else {
                window.location.href = "/" + target;
            }
		});
	}); 
									  
									  
// Control Menu Buttons on "one page" site		
    const menuItems = Array.from(getObjects('#desktop-navigation ul a[href^="#"], #desktop-navigation ul a[data-target^="#"]'));
									  
	if ( menuItems.length > 0 ) {
		const firstLink = getObject('#desktop-navigation ul a');
		if (firstLink) {
			firstLink.setAttribute('data-target', '#masthead');
			menuItems.unshift(firstLink);
		}	 									  
	}
									  
	const scrollItems = menuItems.map(link => {
		const target = link.getAttribute('data-target') || link.getAttribute('href');
		const section = getObject(target);
		return section && window.getComputedStyle(section.parentElement).display !== 'none' ? section : null;
	}).filter(item => item !== null);
			
	const removeMarker = () => {
		getObjects('#desktop-navigation ul li').forEach(item => {
			item.classList.remove('current-menu-item', 'current_page_item', 'active');
		});
	}

	const observer = new IntersectionObserver(entries => {
		entries.forEach(entry => {
			if (entry.isIntersecting) {
				removeMarker();
				const activeItem = getObject(`#desktop-navigation ul a[href="#${entry.target.id}"], #desktop-navigation ul a[data-target="#${entry.target.id}"]`);
				activeItem?.parentElement.classList.add('current-menu-item', 'current_page_item', 'active');
				closeMenu();
			}
		});				
	}, {
		rootMargin: `0px 0px -${convertOffset('25%')}px 0px`,
		threshold: 0.01
	});

	scrollItems.forEach(item => item && observer.observe(item));
			 

// If modal popup is taller than device height, make it scrollable
	getObjects('.screen-mobile .section.section-lock.position-modal .flex').forEach(element => {
		const viewportH = getDeviceH() - 100;
		if (element.offsetHeight > viewportH) {
			element.classList.add('scrollable');
		}
	});
	

// Handle locked sections and close button		
	window.addEventListener('load', () => {
		const sections = getObjects('section.section-lock');
		sections.forEach(section => {
			const sectionID = section.id;
			const initDelay = section.dataset.delay;
			const lockPos = section.dataset.pos;
			const buttonActivated = section.dataset.btn;
			let cookieExpire = section.dataset.show;
			cookieExpire = cookieExpire === "always" ? 0.000001 : cookieExpire === "never" ? 100000 : cookieExpire === "session" ? null : cookieExpire;

			const closeButton = getObject('.closeBtn', section);
			moveDiv(closeButton, closeButton.nextElementSibling, 'top');			
			keyPress(closeButton);
			closeButton.addEventListener('click', () => handleButtonClose(section, sectionID, cookieExpire));
			
		  	if (lockPos === "top") {
				section.style.top = `${mobileMenuBarH()}px`;
			} else if (lockPos === "bottom") {
				section.style.bottom = "0";
		  	} else if (lockPos === "header") {
				section.style.display = "grid";
		  	}

			if (buttonActivated === "yes") {
				getObject('.modal-btn').addEventListener('click', () => {
					section.classList.add("on-screen");
					document.body.classList.add('locked');
					section.focus();
				});
			}			
			
            if (buttonActivated === "no" && getCookie("display-"+sectionID) !== "no") {
                setTimeout(() => {
                    section.classList.add("on-screen");
                    document.body.classList.add('locked');
                    section.focus();
                }, initDelay);
            }

			section.addEventListener('click', () => {
				setCookie("display-"+sectionID, "no", cookieExpire);
				getObjects('video').forEach(video => {
					video.pause();
					video.currentTime = 0;
				});
				stopAllVideos();
			});
		});
	});

	function handleButtonClose(section, sectionID, cookieExpire) {
		section.classList.remove("on-screen");
		document.body.classList.remove('locked');
		setCookie("display-"+sectionID, "no", cookieExpire);
		if (section.dataset.pos === "header") {
			section.style.cssText = "max-height:0; padding-top:0; padding-bottom:0; margin-top:0; margin-bottom:0;";
		}
	}		
		
		
// Gracefully start to fade out the pre-loader
	window.fadeOutLoader = function (targetOpacity) {
		const loader = getObject("#loader");
		if (!loader) return;

		let opacity = parseFloat(getComputedStyle(loader).opacity);
		const stepReduce = targetOpacity === 0 ? 0.05 : 0.01;
		const color = getComputedStyle(loader).backgroundColor;
		const [r, g, b] = color.match(/\d+/g).map(Number); 

		const fadeInterval = setInterval(() => {
			opacity -= stepReduce;
			loader.style.backgroundColor = `rgba(${r}, ${g}, ${b}, ${opacity})`;

			if (opacity <= targetOpacity) {
				clearInterval(fadeInterval);
				if (targetOpacity === 0) {
					loader.style.display = 'none';
				}
			}
		}, 10);
	};
	
	
// Set up mobile menu animation
	getObjects('#mobile-navigation li.menu-item-has-children > a').forEach(link => {
		link.setAttribute('data-href', link.getAttribute('href'));
		link.href = 'javascript:void(0)';
	});
			
	const activateBtn = getObject("#mobile-menu-bar .activate-btn");
	const topPush = document.body.classList.contains("top-push");
	const mobileMenu = getObject('#mobile-navigation');
	const subMenu = getObjects('ul.sub-menu', mobileMenu);
			
	window.closeMenu = function() {
		document.body.classList.remove("mm-active");

		if (mobileMenu.offsetParent !== null && activateBtn) {  // mobileMenu.offsetParent !== null should determine whether the menu is visible or not (i.e. mobile device)
			activateBtn.classList.remove("active");
		}
		
		if (mobileMenu.offsetParent !== null && topPush) {
			getObject(".top-push.screen-mobile #page").style.top = "0";
			getObject(".top-push.screen-mobile .top-strip.stuck").style.top = `${mobileMenuBarH()}px`;
		}
	};
			
	window.openMenu = function() {
		document.body.classList.add("mm-active");
		
		if (mobileMenu.offsetParent !== null && activateBtn) {
			activateBtn.classList.add("active");
		}
		
		if (mobileMenu.offsetParent !== null && topPush) {
			const getMenuH = getObject("#mobile-navigation").offsetHeight;
			const getTotalH = getMenuH + mobileMenuBarH();
			getObject(".top-push.screen-mobile.mm-active #page").style.top = `${getMenuH}px`;
			getObject(".top-push.screen-mobile.mm-active .top-strip.stuck").style.top = `${getTotalH}px`;
		}
	};
			
	window.closeSubMenu = function(subMenu) {
		subMenu.classList.remove("active");
		subMenu.style.height = "0";
		subMenu.previousElementSibling.href = 'javascript:void(0)';
	};			
			
	window.openSubMenu = function(subMenu, subH) {
		if (NodeList.prototype.isPrototypeOf(subMenu) || Array.isArray(subMenu)) {
			subMenu.forEach(sub => {
				sub.classList.remove("active");
				sub.style.height = "0";
			});
		} else {
			subMenu.classList.remove("active");
			subMenu.style.height = "0";
		}
		
		const children = getObjects('#mobile-navigation li.menu-item-has-children > a');
		if (children) {
			children.forEach(a => {
				a.href = 'javascript:void(0)';
			});
		}
		subMenu.classList.add("active");
		subMenu.style.height = `${subH}px`;
		setTimeout(() => {
			subMenu.previousElementSibling.href = subMenu.previousElementSibling.getAttribute('data-href');
		}, 500);
	};
					
	if (activateBtn) {
		activateBtn.addEventListener('click', function() {
			if (this.classList.contains("active")) {
				closeMenu();
			} else {
				openMenu();
			}
		});
	}
			
	if (subMenu) {
		subMenu.forEach(sub => {
			const subH = sub.offsetHeight;
			closeSubMenu(sub);
			sub.parentElement.addEventListener('click', () => {
				if (!sub.classList.contains("active")) {
					openSubMenu(sub, subH);
				} else {
					closeSubMenu(sub);
				}
			});
		});	
	}
			
	getObjects('#mobile-navigation li:not(.menu-item-has-children)').forEach(item => {
		item.addEventListener('click', () => {
			closeMenu();
		});
	});


// Determine which day of week and add active class on office-hours widget	
	const todayIs = new Date().getDay();
	const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
	const dayClass = `row-${days[todayIs]}`;
	const dayElement = getObject(`.office-hours .${dayClass}`);
	if (dayElement) {
		dayElement.classList.add("today");
	}
	
		
// Handle displaying YouTube or Vimeo thumbnail, and then loading video once clicked	
	function activateYouTubeVimeo(div) {
		var iframe = document.createElement('iframe');
		setAttributes(iframe, {
			'src':				div.dataset.link,
			'modestbranding':	'0',
			'controls':			'0',
			'frameborder':		'0',
			'allowfullscreen':	'1',
			'allow':			'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture',
			'class':			'video-player'
		});
		div.parentNode.replaceChild(iframe, div);		
	}			

	getObjects('.video-player').forEach(function(playerObj) {
		const thumbNode = document.createElement('img');
		const playButton = document.createElement('div');
		const div = document.createElement('div');

		div.dataset.id = playerObj.dataset.id;
		div.dataset.link = playerObj.dataset.link;
		thumbNode.src = playerObj.dataset.thumb;
		playButton.className = 'play';

		div.appendChild(thumbNode);
		div.appendChild(playButton);
		div.onclick = function() { activateYouTubeVimeo(this); };

		playerObj.appendChild(div);
	});			
			
	window.stopAllVideos = function() {
		Array.prototype.forEach.call(getObjects('iframe.video-player'), function(videoElem) {
			videoElem.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*');
		});
	};

	getObjects('.block-video.video-player').forEach(function(videoBlock) {
		videoBlock.addEventListener('click', stopAllVideos);
	});
			
				
	// Add 'alt' class to sections to trigger the alternate input & button styles
	window.addAltStyle = function (sections, style='style-alt') {
		getObjects(sections).forEach(section => {
			section.classList.add(style);
		});
	};
		
			
	// Get link from a href and attach to the li (for use with Menu BG)	
	getObjects('#desktop-navigation ul.main-menu > li:not(.menu-item-has-children), #desktop-navigation ul.sub-menu > li').forEach(item => {
		item.addEventListener('click', function() {
			const link = getObject('a', this).getAttribute('href');
			if (link) { 
				window.location.href = link;
			}
		});
	});
			
	
// Move ad promo on blog pages				
	const adObj = getObject('.place-ad');
    if (adObj) {
        const postObj = getObjects('.single-post .entry-content *');
        let posP = Math.ceil(postObj.length / 2);
        if (postObj.length > 30) { posP = 10; }

        const insertObj = getObject('div.insert-promo');
        if (insertObj) {
            moveDiv(adObj, insertObj, 'before');
            insertObj.remove();
        } else {
            const h2Obj = getObjects('.single-post .entry-content h2');
            if (h2Obj.length > 1) {
                moveDiv(adObj, h2Obj[1], 'before');
            } else if (h2Obj.length === 1) {
                moveDiv(adObj, h2Obj[0], 'before');
            } else {
                const targetObj = getObject('.single-post .entry-content *:nth-child(' + posP + ')');
                if (targetObj) {
                    moveDiv(adObj, targetObj, 'after');
                }
            }
        }
    }
			
	
// Blog Archive page - tag list drop-down functionality
	var tagDropdown = getObject('#tag-dropdown');
    if (tagDropdown) {
        tagDropdown.addEventListener('change', function() {
            var tagLink = this.value;
            if (tagLink) {
                window.location.href = tagLink;
            }
        });
    }
	
			
// Apply border-radius from img.testimonial to anonymous svg
	var imgTestimonial = getObject('.img-testimonials');
    if (imgTestimonial) {
        var style = window.getComputedStyle(imgTestimonial);
        var iconRadius = style.getPropertyValue('border-radius');
        var iconBorder = style.getPropertyValue('border');

        getObjects('.anonymous-icon').forEach(icon => {
            icon.style.borderRadius = iconRadius;
            icon.style.border = iconBorder;
        });
    }
			
	
// Allow sub-menu to appear, even if initially set to overflow:hidden	
	const menuClip = getObject('.menu-clip .menu-strip');
	if (menuClip) {
		setTimeout(() => menuClip.style.overflow = 'visible', 2500);
	}
	
/*--------------------------------------------------------------
# Screen resize
--------------------------------------------------------------*/
	window.screenResize = function () {		 
		const thisDeviceW = getDeviceW();
		document.body.classList.remove('screen-5', 'screen-4', 'screen-3', 'screen-2', 'screen-1', 'screen-mobile', 'screen-desktop');

		thisDeviceW > 1280 ? document.body.classList.add("screen-5", "screen-desktop") :
		thisDeviceW > mobileCutoff ? document.body.classList.add("screen-4", "screen-desktop") :
		thisDeviceW > 860 ? document.body.classList.add("screen-3", "screen-mobile") :
		thisDeviceW > 576 ? document.body.classList.add("screen-2", "screen-mobile") :
		document.body.classList.add("screen-1", "screen-mobile");
		
		
// Resize video on mobile, if necessary
		if (thisDeviceW <= 860) {
			getObjects('.block-video video[data-mobile-w]').forEach(video => {
				video.style.width = `${video.getAttribute('data-mobile-w')}%`;
				video.style.left = `${-(mobileW - 100) / 2}%`;
			});
		} else {
			getObjects('.block-video video[data-mobile-w]').forEach(video => {
				video.style.width = '100%';
				video.style.left = '0%';
			});
		}
		
				
// Close any open menus on mobile (when device ratio changes)
		const menuSearch = getObject('#mobile-navigation > #mobile-menu .menu-search-box input[type="search"]');		
		if ( !menuSearch || !menuSearch.matches(':focus')) {
			closeMenu();
		}
		
		
// Shift #secondary to bottom of mobile site		
		const sidebar = getObject('#secondary');
		if (sidebar) {
			if ( document.body.classList.contains("screen-mobile") ) {
				moveDiv(sidebar, '#colophon', 'before');
			} else {
				moveDiv(sidebar, '#primary', 'after');
			}
		}
		

// Ensure "-faux" elements remain correct size
		getObjects('div[class*="-faux"]').forEach(fauxDiv => {
			let fauxClass = `.${fauxDiv.className.replace(/\s+/g, '.')}`;
			let mainClass = fauxClass.replace("-faux", "");			
			const mainElement = getObject(mainClass);

			if (!mainElement) {
				let mainID = `#${fauxDiv.className.replace(/\s+/g, '#')}`;
				mainClass = mainID.replace("-faux", "");
			}

			if (mainElement && window.getComputedStyle(mainElement).display !== 'none') {
				getObjects(fauxClass).forEach(el => el.style.height = `${mainElement.offsetHeight}px`);
				getObjects('.wp-google-badge-faux').forEach(el => {
					const badge = getObject('.wp-google-badge');
					if (badge) el.style.height = `${badge.offsetHeight}px`;
				});
			} else {
				getObjects(fauxClass).forEach(el => el.style.height = '0px');
			}
		});
		
		
// If total height of page is less than screen height, add min-height to #wrapper-content
		if (getDeviceH() > getObject('#page').offsetHeight) {
			getObject('#wrapper-content').classList.add("extended");
		} else {
			getObject('#wrapper-content').classList.remove("extended");
		}	
	
		
// Handle multiple Google review locations on mobile
		if (getDeviceW() > mobileCutoff) {
			getObjects('.wp-google-badge .wp-google-badge-btn').forEach(btn => btn.style.display = 'block');
		} else {
			const buttons = getObjects('.wp-google-badge .wp-google-badge-btn');
			if (buttons.length > 1) {
				buttons.forEach(btn => btn.style.display = 'none');
				const rand = Math.floor(Math.random() * buttons.length);
				buttons[rand].style.display = 'block';
			}
		}
		
// Position side-by-side images that have borders, outlines, etc.
		getObjects('ul.side-by-side').forEach(ul => {
			let images = getObjects('img', ul);
			let totalWidth = 0;

			images.forEach(img => {
				const cssFilter = getComputedStyle(img).filter;
				const dropShadowComponents = cssFilter.split("drop-shadow(").slice(1);
				dropShadowComponents.forEach(component => {
					const parts = component.split(" ");
					let chk_first = parseFloat(parts[3].replace(/[)-]|px/g, ''));
					let chk_second = parseFloat(parts[4].replace(/[)-]|px/g, ''));

					totalWidth += (chk_first < 1 && chk_first > 0) ? chk_second : chk_first;
				});

				totalWidth += parseInt(getComputedStyle(img).borderLeftWidth) + parseInt(getComputedStyle(img).borderRightWidth) + parseInt(getComputedStyle(img).outlineWidth);
			});

			ul.style.padding = `${totalWidth}px`;
			ul.style.marginLeft = `-${totalWidth / 2}px`;
		});

	};

/*--------------------------------------------------------------
# ADA compliance
--------------------------------------------------------------*/	
// Add aria-labels to landmarks, sections and titles
	getObjects('h3 a[aria-hidden="true"]').forEach(a => {
		a.parentNode.setAttribute('aria-label', a.textContent);
	});

	setTimeout(() => { getObjects('img:not([alt])').forEach(img => img.setAttribute('alt', '')); }, 50);
	setTimeout(() => { getObjects('img:not([alt])').forEach(img => img.setAttribute('alt', '')); }, 1000);

	getObjects('[role="menubar"]').forEach(menu => {
		menu.addEventListener('focus.aria', ev => {
			if (ev.target.getAttribute('aria-haspopup') === "true") {
				ev.target.classList.add('menu-item-expanded');
				ev.target.setAttribute('aria-expanded', true);
			}
		}, true);
		menu.addEventListener('mouseenter.aria', ev => {
			if (ev.target.getAttribute('aria-haspopup') === "true") {
				ev.target.classList.add('menu-item-expanded');
				ev.target.setAttribute('aria-expanded', true);
			}
		}, true);
		menu.addEventListener('blur.aria', ev => {
			if (ev.target.getAttribute('aria-haspopup') === "true") {
				ev.target.classList.remove('menu-item-expanded');
				ev.target.setAttribute('aria-expanded', false);
			}
		}, true);
		menu.addEventListener('mouseleave.aria', ev => {
			if (ev.target.getAttribute('aria-haspopup') === "true") {
				ev.target.classList.remove('menu-item-expanded');
				ev.target.setAttribute('aria-expanded', false);
			}
		}, true);
	});

	getObjects('[role="menubar"] a').forEach(a => a.setAttribute('tabindex', '0'));
	getObjects('li[aria-haspopup="true"]').forEach(li => li.setAttribute('tabindex', '-1'));
	
	
// Update ids for labels & inputs in #request-quote-modal for ADA compliance
	getObjects('#request-quote-modal div.form-input').forEach(formInput => {
		const label = getObject('label', formInput);
		const input = getObject('input', formInput);
		const textarea = getObject('textarea', formInput);
		const select = getObject('select', formInput);

		const updateId = (element, baseId) => {
			if (element) {
				element.id = 'modal-' + baseId;
			}
		};

		if (input) {
			updateId(input, input.id);
			label.setAttribute('for', 'modal-' + input.id);
		} else if (textarea) {
			updateId(textarea, textarea.id);
			label.setAttribute('for', 'modal-' + textarea.id);
		} else if (select) {
			updateId(select, select.id);
			label.setAttribute('for', 'modal-' + select.id);
		}
	});
			
});