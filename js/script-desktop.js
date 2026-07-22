document.addEventListener("DOMContentLoaded", function () {	"use strict";

// Raw Script: Desktop

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Parallax
# Menus
# Sidebar widgets
# Enhancements
# ADA compliance
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Parallax
--------------------------------------------------------------*/
	window.parallaxConfigs = window.parallaxConfigs || [];

	window.updateParallaxBackgrounds = () => {
		const scrollPos = window.pageYOffset;
		const deviceH = getDeviceH();

		window.parallaxConfigs.forEach(config => {
			const { containerObj, imageH, topY, bottomY, fullScreen, svgObj, bgLayer, secLayer } = config;

			// Composited full-screen backdrop: move a fixed GPU layer via transform (no repaint).
			// Drift is derived from the image-to-viewport height ratio and anchored to the
			// container's scroll progress, so the backdrop starts at the image top and ends at the
			// image bottom — identical to the old background-position math, but on the GPU.
			if (bgLayer) {
				const coverH = Math.max(deviceH, Math.round(window.innerWidth * bgLayer._bpImgH / bgLayer._bpImgW));
				if (bgLayer._bpH !== coverH) { bgLayer.style.height = `${coverH}px`; bgLayer._bpH = coverH; }
				const rect = containerObj.getBoundingClientRect();
				const range = rect.height - deviceH;
				const frac = range > 0 ? Math.max(0, Math.min(-rect.top / range, 1)) : 0;
				bgLayer.style.transform = `translate3d(0, ${(-(coverH - deviceH) * frac).toFixed(1)}px, 0)`;
				return;
			}

			const obj = containerObj.getBoundingClientRect();
			const objTop = obj.top;
			const objHeight = obj.height;
			let startScroll, endScroll, adjTop = 0, adjBot = 0;

			if (fullScreen) {
				startScroll = objTop;
				endScroll = objTop + objHeight - deviceH;
			} else {
				startScroll = objTop - deviceH;
				endScroll = objTop + objHeight;
				adjTop = -parseInt(topY, 10);
				adjBot = -parseInt(bottomY, 10);
			}

			let scrollRange = endScroll - startScroll;
			let objScroll = Math.max(0, Math.min((endScroll / scrollRange), 1));
			objScroll = fullScreen ? objScroll : 1 - objScroll;
			let finalPosY = (imageH + adjTop) - ((imageH + adjTop + adjBot) * objScroll);
			finalPosY = (finalPosY / imageH) * 100;

			// Apply as a composited transform — SVG, or a section's clipped inner layer. The section
			// layer reuses the same finalPosY math, converted from a background-position percentage to
			// a pixel translate: offset = (sectionHeight - imageHeight) * (finalPosY / 100).
			if (svgObj) {
				svgObj.style.transform = `translateY(${finalPosY}%)`;
			} else if (secLayer) {
				const shift = (objHeight - imageH) * (finalPosY / 100);
				secLayer.style.transform = `translate3d(0, ${shift.toFixed(1)}px, 0)`;
			} else {
				containerObj.style.backgroundPositionY = `${finalPosY}%`;
			}
		});
	};


// Add parallax background to site or div
	window.parallaxBG = function (containerSel='#page', filename, imageW, imageH, posX='50%', topY=0, bottomY=0, fullScreen=true) {
		const containerObj = getObject(containerSel);
		if (!containerObj) return;

		// Resolve the uploads base defensively. If site_dir.upload_dir_uri is missing/empty at
		// runtime (e.g. Rocket Loader deferred the inline localize), `${site_dir.upload_dir_uri}/x`
		// collapses to a ROOT '/x' request and 404s. Fall back to the standard uploads path so the
		// URL is never rooted; bail entirely if we somehow still have no base or filename.
		const uploadBase = ((window.site_dir && site_dir.upload_dir_uri) || '/wp-content/uploads').replace(/\/+$/, '');
		if (!uploadBase || !filename) return;

		const isSVG = filename.startsWith('svg#');
		const svgObj = isSVG ? document.querySelector(filename.replace('svg', '')) : null;

		if (isSVG && svgObj) {
			// Store SVG-specific config
			window.parallaxConfigs.push({ containerObj, imageH, topY, bottomY, fullScreen, svgObj });
		} else if (fullScreen) {
			// Full-screen backdrop on its own composited layer. Replaces background-attachment:fixed
			// + per-frame backgroundPositionY (a whole-viewport repaint every scroll frame in Chrome).
			// Preferred path: a CSS scroll-driven animation drives the drift on the compositor with
			// ZERO main-thread work — no scroll handler, perfectly in sync. JS is only a fallback for
			// browsers without scroll-timeline support (e.g. Firefox), which are smooth anyway.
			let bgLayer = document.querySelector('.bp-parallax-bg');
			if (!bgLayer) {
				bgLayer = document.createElement('div');
				bgLayer.className = 'bp-parallax-bg';
				document.body.insertBefore(bgLayer, document.body.firstChild);
			}
			bgLayer._bpImgW = imageW;
			bgLayer._bpImgH = imageH;
			bgLayer._bpH = 0;
			setStyles(bgLayer, {
				'position': 'fixed',
				'left': '0',
				'top': '0',
				'width': '100%',
				'zIndex': '-1',
				'pointerEvents': 'none',
				'backgroundImage': `url('${uploadBase}/${filename}')`,
				'backgroundSize': 'cover',
				'backgroundPosition': `${posX} center`,
				'backgroundRepeat': 'no-repeat',
				'willChange': 'transform'
			});

			// Layer height + pan distance change only on resize, never on scroll.
			const sizeBg = () => {
				const coverH = Math.max(getDeviceH(), Math.round(window.innerWidth * bgLayer._bpImgH / bgLayer._bpImgW));
				bgLayer.style.height = `${coverH}px`;
				bgLayer.style.setProperty('--bp-pan', `${coverH - getDeviceH()}px`);
			};
			sizeBg();

			if (window.CSS && CSS.supports && CSS.supports('animation-timeline: scroll()')) {
				// Compositor path: tie the drift straight to scroll position, no per-frame JS.
				if (!document.getElementById('bp-parallax-style')) {
					const st = document.createElement('style');
					st.id = 'bp-parallax-style';
					st.textContent =
						'@keyframes bp-parallax-drift{from{transform:translate3d(0,0,0)}to{transform:translate3d(0,calc(-1*var(--bp-pan,0px)),0)}}' +
						'.bp-parallax-bg{animation:bp-parallax-drift linear both;animation-timeline:scroll(root block)}';
					document.head.appendChild(st);
				}
				if (!bgLayer._bpResize) { window.addEventListener('resize', sizeBg, { passive: true }); bgLayer._bpResize = true; }
				// Intentionally NOT pushed to parallaxConfigs — the backdrop does no per-scroll JS.
			} else {
				// Fallback (no scroll-timeline support): JS drives the transform each frame.
				window.parallaxConfigs.push({ containerObj, imageH, topY, bottomY, fullScreen, bgLayer });
			}
		} else {
			// Section backdrop on a composited inner layer. Replaces the per-frame backgroundPositionY
			// write (a section repaint) with a transform on a clipped child — same drift, on the GPU.
			// topY/bottomY still control the travel via the finalPosY math in updateParallaxBackgrounds.
			if (getComputedStyle(containerObj).position === 'static') containerObj.style.position = 'relative';
			containerObj.style.overflow = 'hidden';
			containerObj.style.isolation = 'isolate';
			let secLayer = containerObj.querySelector(':scope > .bp-parallax-sec');
			if (!secLayer) {
				secLayer = document.createElement('div');
				secLayer.className = 'bp-parallax-sec';
				containerObj.insertBefore(secLayer, containerObj.firstChild);
			}
			setStyles(secLayer, {
				'position': 'absolute',
				'left': '0',
				'top': '0',
				'width': '100%',
				'height': `${imageH}px`,
				'zIndex': '-1',
				'pointerEvents': 'none',
				'backgroundImage': `url('${uploadBase}/${filename}')`,
				'backgroundSize': `${imageW}px ${imageH}px`,
				'backgroundPosition': `${posX} 0`,
				'backgroundRepeat': 'no-repeat',
				'willChange': 'transform'
			});

			// Store image-specific config
			window.parallaxConfigs.push({ containerObj, imageH, topY, bottomY, fullScreen, secLayer });
		}

		updateParallaxBackgrounds();
	};


// Automatically add parallax to any div noted as a scroll element
	getObjects('[data-parallax="scroll"]').forEach(section => {
		let imgSrc = section.getAttribute('data-image-src');
		imgSrc = imgSrc.replace('/wp-content/uploads/', '');
		const imgW = parseInt(section.getAttribute('data-img-width'), 10);
		const imgH = parseInt(section.getAttribute('data-img-height'), 10);
		const posX = section.getAttribute('data-pos-x');
		const topY = section.getAttribute('data-top-y');
		const bottomY = section.getAttribute('data-bottom-y');

		parallaxBG(section, imgSrc, imgW, imgH, posX, topY, bottomY, false);
	});


//Control parallax movement of divs within a container
	window.parallaxDivs = window.parallaxDivs || [];

	window.updateParallaxElements = function() {
		const scrollPos = window.pageYOffset;

		window.parallaxDivs.forEach(config => {
			const { containerSel, elementSel, adjustment } = config;

			getObjects(containerSel).forEach(container => {
				const elementObj = getObject(elementSel, container);
				if (elementObj) {
					const containerHeight = container.offsetHeight;
					const containerTop = container.getBoundingClientRect().top + scrollPos;
					const containerBottom = containerTop + containerHeight;
					const adjustedWindowBottom = scrollPos + getDeviceH();
					let scrollPct = (adjustedWindowBottom - containerTop) / (containerHeight + getDeviceH());

					scrollPct = Math.max(0, Math.min(scrollPct, 1));
					const moveElem = (containerHeight - elementObj.offsetHeight + adjustment) * scrollPct;

					if (containerTop < adjustedWindowBottom && containerBottom > scrollPos) {
						// Use transform (not margin-top) so the parallax drift is a COMPOSITED move,
						// not a layout change — transforms are excluded from CLS, so the hero-copy's
						// on-load positioning no longer registers as a layout shift. Same visual result.
						elementObj.style.transform = `translateY(${moveElem}px)`;
					}
				}
			});
		});
	};


// Add parallax scrolling to element within a div
	window.parallaxDiv = function (containerSel, elementSel=".parallax", adjustment=0) {
		window.parallaxDivs.push({
			containerSel: 		containerSel,
			elementSel: 		elementSel,
			adjustment: 		adjustment
		});

    	updateParallaxElements();
	};


/*--------------------------------------------------------------
# Menus
--------------------------------------------------------------*/

// Set up Split Menu
// Splits the menu into a left/right half so a centered logo can sit in the gap.
// Rebuilds from a cached pristine copy on every call (idempotent), re-runs on
// resize, and skips splitting while the desktop nav is hidden/unmeasurable
// (≤1024px) — otherwise a 0-width measurement dumps every item onto one side.
window.splitMenu = (menuSel = "#desktop-navigation", logoSel = ".logo img", compensate = 0, override = false) => {
	const menuObj = getObject(menuSel);
	if (!menuObj) return;
	const menuFlex = getObject('.flex', menuObj);
	if (!menuFlex) return;

	// Cache the untouched menu once so every (re)run rebuilds from a clean copy,
	// and wire up a single debounced resize listener.
	if (!menuObj._smPristine) {
		const orig = getObject('ul.main-menu', menuObj);
		if (!orig) return;
		menuObj._smPristine = orig.cloneNode(true);
		let t;
		window.addEventListener('resize', () => {
			clearTimeout(t);
			t = setTimeout(() => splitMenu(menuSel, logoSel, compensate, override), 150);
		});
	}

	// Teardown: remove any prior split wrappers + leftover menu, restore a fresh full menu.
	getObjects('.split-menu-l, .split-menu-r', menuFlex).forEach(el => el.remove());
	Array.from(menuFlex.children).forEach(el => { if (el.matches('ul.main-menu')) el.remove(); });
	menuFlex.style.gridColumnGap = '';
	const menuUL = menuObj._smPristine.cloneNode(true);
	menuFlex.appendChild(menuUL);

	// Guard: never split while hidden (offsetParent null) or unmeasurable (0 width).
	if (menuObj.offsetParent === null || menuUL.offsetWidth === 0) return;

	const logoObj = getObject(logoSel);
	const logoWidth = (logoObj ? logoObj.offsetWidth : 0) + compensate;
	const menuItems = getObjects('ul.main-menu > li', menuObj);
	const menuWidth = menuUL.offsetWidth / 2;
	let currOpt = 0;
	let maxOpt = Math.round(menuItems.length / 2);

	const createSplitMenu = (side) => {
		const div = document.createElement('div');
		div.className = `split-menu-${side}`;
		menuFlex.insertBefore(div, menuFlex.firstChild);
		return div;
	};

	const splitMenuR = createSplitMenu('r');
	const splitMenuL = createSplitMenu('l');

	if (!override) {
		const itemWidths = menuItems.map(item => item.offsetWidth); // all reads first
		menuItems.forEach((item, index) => {
			currOpt += itemWidths[index];
			if (currOpt < menuWidth) {
				item.classList.add('left-menu');
			} else {
				item.classList.add('right-menu');
			}
		});
	} else {
		if (override !== true) {
			maxOpt = override;
		}
		menuItems.forEach((item, index) => {
			if (index < maxOpt) {
				item.classList.add('left-menu');
			} else {
				item.classList.add('right-menu');
			}
		});
	}

	const updateIDs = (element) => {
		const ul = getObject('ul.menu:not(.sub-menu)', element);
		if (ul) ul.id = `${ul.id}-${element.className.includes('split-menu-l') ? 'l' : 'r'}`;
	};

	const cloneMenu = menuUL.cloneNode(true);
	splitMenuR.appendChild(cloneMenu);
	splitMenuL.appendChild(menuUL);

	updateIDs(splitMenuL);
	updateIDs(splitMenuR);

	getObjects('.right-menu', splitMenuL).forEach(item => item.remove());
	getObjects('.left-menu', splitMenuR).forEach(item => item.remove());

	menuFlex.style.gridColumnGap = `${logoWidth}px`;
};


// Add a logo into an <li> on the menu strip
	window.addMenuLogo = function(filename, menuSel='#desktop-navigation') {
		const menuObj = getObject(menuSel);
		if (!menuObj) return;

		menuObj.classList.add('menu-with-logo');
		const logoDiv = document.createElement('div');
		logoDiv.className = 'menu-logo';
		logoDiv.innerHTML = `<img src="${filename}" alt="">`;

		if (menuObj.firstChild) {
			menuObj.insertBefore(logoDiv, menuObj.firstChild);
		} else {
			menuObj.appendChild(logoDiv);
		}

		const menuHeight = menuObj.offsetHeight;
		const logoImg = getObject('img', logoDiv);
		logoImg.style.height = `${menuHeight}px`;

		linkHome('.menu-logo');
	};


// Add an icon to each menu item
	window.addMenuIcon = function(filename, iconW=0, iconH=0, position='before', menuSel='#desktop-navigation') {
		const menuObj = getObject(menuSel);
		if (!menuObj) return;

		getObjects('ul.main-menu > li', menuObj).forEach(li => {
			const anchor = getObject('a', li);
			if (!anchor) return;

			addDiv(anchor,`<div class="menu-icon"><img src="${filename}" width="${iconW}" height="${iconH}" style="aspect-ratio:${iconW}/${iconH}"></div>`, position);

		});
	}


// Calculate & center sub navigation under <li>
	window.centerSubNav = function () {
		const subMenus = getObjects('.main-navigation ul.sub-menu');
		subMenus.forEach(subMenu => {
			const subW = subMenu.offsetWidth;
			const parentW = subMenu.parentElement.offsetWidth;
			const moveL = -Math.round((subW - parentW) / 2);

			subMenu.style.left = `${moveL}px`;
		});
	};


/*--------------------------------------------------------------
# Sidebar widgets
--------------------------------------------------------------*/
	window.labelWidgets = function () {
		const visibleWidgets = getObjects(".widget:not(.hide-widget)");
		if (visibleWidgets.length) {
			visibleWidgets.forEach((widget, index) => {
				widget.classList.remove("widget-even", "widget-odd", "widget-first", "widget-last"); // Clear previous classes
				widget.classList.add(index % 2 === 0 ? "widget-odd" : "widget-even");
			});
			visibleWidgets[0].classList.add("widget-first");
			visibleWidgets[visibleWidgets.length - 1].classList.add("widget-last");
		}
	};

	window.desktopSidebar = function (compensate, sidebarScroll) {
		window.secondaryObj = getObject('#secondary');
		if ( !secondaryObj ) return;

		window.primaryObj = getObject('#primary');
		window.sidebarObj = getObject('.sidebar-inner', secondaryObj);
		window.sidebarPad = parseInt(window.getComputedStyle(secondaryObj).paddingTop) + parseInt(window.getComputedStyle(secondaryObj).paddingBottom);

		window.checkHeights = function() {
			labelWidgets();
			window.primaryH = primaryObj.offsetHeight;
			window.sidebarH = sidebarObj.offsetHeight;
			return primaryH - sidebarH - sidebarPad + compensate;
		}

		window.widgetInit = function () {
			if (compensate !== 0) {
				secondaryObj.style.height = `calc(100% + ${compensate}px)`;
			}

			getObjects('.widget').forEach(widget => {
				widget.setAttribute('data-priority', 2);
				widget.setAttribute('data-height', widget.clientHeight);
				widget.classList.add('hide-widget');
			});

			const priorities = [
				{ priority: 5, selectors: ['.widget.widget-priority-5', '.widget.widget-essential', '.widget.widget_nav_menu'] },
				{ priority: 4, selectors: ['.widget.widget-priority-4', '.widget.widget-important', '.widget-contact-form'] },
				{ priority: 3, selectors: ['.widget.widget-priority-3', '.widget.widget-event', '.widget.widget-financing'] },
				{ priority: 1, selectors: ['.widget.widget-priority-1', '.widget.remove-first'] }
			];

			priorities.forEach(group => {
				group.selectors.forEach(selector => {
					getObjects(selector).forEach(widget => {
						widget.setAttribute('data-priority', group.priority);
						if (group.priority === 5) {
							widget.classList.remove('hide-widget');
						}
					});
				});
			});

			addWidgets();
		};

		window.addWidgets = function () {
			for (let i = 4; i >= 0; i--) {
				getObjects('.hide-widget').forEach(widget => {
					if (widget.getAttribute('data-priority') == i && widget.getAttribute('data-height') <= checkHeights()) {
						widget.classList.remove('hide-widget');
					}
				});
			}

			if (getObjects('.widget:not(.hide-widget)').length === 0) {
				const firstWidget = getObject('.widget');
				if (firstWidget) {
					firstWidget.classList.remove('hide-widget');
				}
			}
		};

	 // Move sidebar in conjunction with mouse scroll to keep it even with content
		window.moveWidgets = function () {
			if (sidebarScroll === true) {
				const remain = checkHeights(),
					  scrollPos = window.pageYOffset,
					  primaryRect = primaryObj.getBoundingClientRect(),
					  primaryOffset = primaryRect.top + scrollPos,
					  adjScrollPos = scrollPos - primaryOffset;
				let viewportH = getDeviceH(),
					scrollPct = 0,
					findPos = 0;

				const stuckH = getObjects('.stuck').reduce((sum, el) => sum + el.offsetHeight, 0);
				viewportH -= stuckH;

				const googleBadge = getObject('.wp-google-badge');
				if (googleBadge) {
					viewportH -= googleBadge.offsetHeight;
				}

				if (scrollPos > primaryOffset) {
					scrollPct = adjScrollPos / (primaryH - viewportH);
					findPos = remain * scrollPct;
				} else {
					findPos = 0;
				}

				if (sidebarH < viewportH) {
					findPos = adjScrollPos + parseInt(getComputedStyle(secondaryObj).paddingTop);
				}

				findPos = Math.min(Math.max(findPos, 0), remain);

				if (findPos > 0 && findPos < remain) {
					// transform (composited) instead of marginTop (layout) — no repaint, no forced reflow
					sidebarObj.style.transform = `translate3d(0, ${findPos}px, 0)`;
				}

			}
		};
	};


/*--------------------------------------------------------------
# Enhancements
--------------------------------------------------------------*/

// Reveal "Are We Open" banner
	function areWeOpenBanner(delay) {
		const phoneNumObj = getObject('#masthead .phone-number');
		const bannerObj = getObject('.currently-open-banner');

		if (phoneNumObj && bannerObj) {
			setTimeout(() => {
				moveDiv(bannerObj, phoneNumObj, 'bottom');
				const phoneLink = getObject(".phone-link", phoneNumObj),
					  phoneLinkL = phoneLink.getBoundingClientRect().left,
					  phoneLinkR = phoneLink.getBoundingClientRect().right,
					  phoneHolderL = phoneNumObj.getBoundingClientRect().left,
					  bannerW = bannerObj.offsetWidth,
					  bannerT = 0.45 * phoneNumObj.clientHeight,
					  smallScreen = (phoneLinkR + bannerW) > getDeviceW() ? true : false;


/*
				const phoneHolderW = phoneNumObj.offsetWidth,
					  phoneLinkW = phoneLinkR - icon.getBoundingClientRect().left,
					  phoneHolderA = phoneNumObj.closest(".col").classList,
					  phoneLinkA = window.getComputedStyle(phoneNumObj).textAlign,

				let icon = getObject(".icon", phoneNumObj);
				if ( !icon ) icon = phoneLink;

				if ( smallScreen ) {
					bannerObj.classList.add("small-screen");

					if (phoneLinkA === "right" || phoneHolderA.contains("text-right")) {
						bannerL = phoneHolderW - phoneLinkW - bannerW;
					} else if (phoneLinkA === "left" || phoneHolderA.contains("text-left")) {
						bannerL = -bannerW;
					} else {
						bannerL = (phoneHolderW - phoneLinkW) / 2 - bannerW;
					}
				} else {
					if (phoneLinkA === "right" || phoneHolderA.contains("text-right")) {
						bannerL = phoneHolderW;
					} else if (phoneLinkA === "left" || phoneHolderA.contains("text-left")) {
						bannerL = phoneLinkW;
					} else {
						bannerL = (phoneHolderW - phoneLinkW) / 2 + phoneLinkW;
					}
				}
*/

				let bannerL = phoneLinkR - phoneHolderL;

				if ( smallScreen ) {
					bannerObj.classList.add("small-screen");
					bannerL = (phoneLinkL - phoneHolderL) - bannerW;
				}

				setStyles(bannerObj, {
					top:		`${bannerT}px`,
					left:		`${bannerL}px`
				});

				bannerObj.classList.add('reveal-open');
			}, delay);
		}
	}


	// Execute the banner positioning if phone link exists
	window.addEventListener('load', () => {
		if (getObject("#masthead .phone-link")) {
			areWeOpenBanner((Math.random() * 2000)+2000);
		}
	});

	// Block Apple Magic Mouse gestures from affecting the the scroll-to-top button
	var el = getObject('body.screen-desktop a.icon-btn.scroll-top');
  	if (!el) return;

    ['wheel', 'gesturestart', 'gesturechange', 'gestureend'].forEach(function(type) {
    	el.addEventListener(type, function(e) {
      		e.preventDefault();
      		e.stopPropagation();
		}, { passive: false });
    });

  	el.addEventListener('mousemove', function(e) {
    	e.stopPropagation();
  	}, { passive: true });


/*--------------------------------------------------------------
# ADA compliance
--------------------------------------------------------------*/
	// Add special focus outline when someone is using tab to navigate site
	document.addEventListener('mousemove', () => {
		document.body.classList.add('using-mouse');
		document.body.classList.remove('using-keyboard');
	});

	document.addEventListener('keydown', e => {
		if (e.keyCode === 9) { // Tab key
			document.body.classList.add('using-keyboard');
			document.body.classList.remove('using-mouse');
		}
	});


	// Remove iframe from tab order
	getObjects('iframe').forEach(iframe => {
		iframe.setAttribute('aria-hidden', 'true');
		iframe.setAttribute('tabindex', '-1');
	});


	// Add .tab-focus class to links and buttons & auto scroll to center
	document.addEventListener('keydown', e => {
		if (e.keyCode === 9) { // Tab key
			getObjects('.tab-focus').forEach(el => el.classList.remove('tab-focus'));

			setTimeout(() => {
				getObjects('[aria-expanded="true"').forEach(el => el.classList.add('tab-focus'));

				const activeElement = document.activeElement;
				const menuItem = activeElement.closest('.menu-item');
				activeElement.classList.add('tab-focus');

				if (menuItem) {
					menuItem.classList.add('tab-focus');
					menuItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
				} else {
					activeElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			}, 10);
		}
	});


	document.addEventListener('mousedown', () => {
		getObjects('.tab-focus').forEach(el => el.classList.remove('tab-focus'));
	});

})