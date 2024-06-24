document.addEventListener("DOMContentLoaded", function () {	"use strict";
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Parallax
# Menus
# Sidebar widgets
# Enhancements
# ADA compliance
 

Re-factor complete 4/26/2024

--------------------------------------------------------------*/
	
/*--------------------------------------------------------------
# Parallax
--------------------------------------------------------------*/
	window.parallaxConfigs = window.parallaxConfigs || [];
	
    window.updateParallaxBackgrounds = function () {
        const scrollPos = window.pageYOffset;

        window.parallaxConfigs.forEach(config => {
            const { containerObj, imageH, topY, bottomY, fullScreen } = config;					
            const obj = containerObj.getBoundingClientRect();
			const objTop = obj.top;
			const objHeight = obj.height;
			let startScroll, endScroll, adjTop=0, adjBot=0;			
			
			if ( fullScreen === true ) {
				startScroll = objTop;  								// object TOP hits TOP of the viewport
				endScroll = objTop + objHeight - getDeviceH(); 		// object BOTTOM hits BOTTOM of the viewport
			} else {
				startScroll = objTop - getDeviceH();  				// object TOP hits BOTTOM of the viewport
				endScroll = objTop + objHeight; 					// object BOTTOM hits TOP of the viewport
				adjTop = -parseInt(topY, 10);
				adjBot = -parseInt(bottomY, 10);
			}
			
			let scrollRange = endScroll - startScroll;
			let objScroll = Math.max(0, Math.min((endScroll / scrollRange), 1)); 	
			objScroll = fullScreen ? objScroll : 1 - objScroll;
			let finalPosY = (imageH + adjTop) - ((imageH + adjTop + adjBot) * objScroll); 
            finalPosY = (finalPosY / imageH) * 100;
			
            containerObj.style.backgroundPositionY = `${finalPosY}%`;
        });
    }
				

// Add parallax background to site or div	
	window.parallaxBG = function (containerSel='#page', filename, imageW, imageH, posX='50%', topY=0, bottomY=0, fullScreen=true, fixed) {
		const containerObj = getObject(containerSel);
		if (!containerObj) return;
		
		setStyles(containerObj, {
			'backgroundImage': 			`url('${site_dir.upload_dir_uri}/${filename}')`,
			'backgroundSize': 			`${imageW}px ${imageH}px`,
			'backgroundPosition': 		`${posX} 50%`,
			'backgroundAttachment': 	fullScreen ? 'fixed' : 'scroll'
		});
		
		if ( containerSel === '#page' && containerObj.offsetHeight < imageH ) {
			setStyles(containerObj, {
				'backgroundAttachment': 	'unset'
			});
		} else if ( fixed === true || fixed === 'true' ) {
			setStyles(containerObj, {
				'backgroundAttachment': 	'fixed'
			});		
		} else {		
			window.parallaxConfigs.push({
				containerObj: 	containerObj,
				imageH: 		imageH,
				topY:			topY,
				bottomY:		bottomY,
				fullScreen:		fullScreen
			});		

			updateParallaxBackgrounds();
		}
	};
	
	
// Automatically add parallax to any div noted as a scroll element
	getObjects('section[data-parallax="scroll"], div[data-parallax="scroll"]').forEach(section => {
		let imgSrc = section.getAttribute('data-image-src');
		imgSrc = imgSrc.replace('/wp-content/uploads/', '');
    	const imgW = parseInt(section.getAttribute('data-img-width'), 10);
    	const imgH = parseInt(section.getAttribute('data-img-height'), 10);
    	const posX = section.getAttribute('data-pos-x');
    	const topY = section.getAttribute('data-top-y');
    	const bottomY = section.getAttribute('data-bottom-y');
    	const fixed = section.getAttribute('data-fixed');
		
		parallaxBG(section, imgSrc, imgW, imgH, posX, topY, bottomY, false, fixed);        
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
						elementObj.style.marginTop = `${moveElem}px`;
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
	window.splitMenu = function (menuSel="#desktop-navigation", logoSel=".logo img", compensate=0, override=false) {
		const menuObj = getObject(menuSel);
		const logoWidth = getObject(logoSel).offsetWidth + compensate;
		const menuFlex = getObject('.flex', menuObj);
		const menuUL = getObject('ul.menu', menuObj);
		const menuItems = getObjects('li', menuUL);
		const menuWidth = menuUL.offsetWidth / 2;
		let currOpt = 0;
		let maxOpt = Math.round(menuItems.length / 2);

		if (!override) {
			menuItems.forEach(item => {
				currOpt += item.offsetWidth;
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

		const createSplitMenu = (side) => {
			const div = document.createElement('div');
			div.className = `split-menu-${side}`;
			menuFlex.insertBefore(div, menuFlex.firstChild);
			return div;
		};

		const splitMenuR = createSplitMenu('r');
		const splitMenuL = createSplitMenu('l');

		const cloneMenu = menuUL.cloneNode(true);
		splitMenuR.appendChild(cloneMenu);
		splitMenuL.appendChild(menuUL);

		const updateIDs = (element) => {
			const ul = getObject('ul.menu', element);
			ul.id = `${ul.id}-${element.className.includes('split-menu-l') ? 'l' : 'r'}`;
		};

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
	
	
// Add an icon into each <li> in main menu
	window.addMenuIcon = function(filename, iconW=0, iconH=0, position='before', menuSel='#desktop-navigation') {
		const menuObj = getObject(menuSel);
		if (!menuObj) return;
		
		const items = getObjects('ul.main-menu > li', menuObj);
		if ( !items) return;
		
		items.forEach(item => {
			const link = getObject('a', item);
			if (!link) return;
			
			addDiv(link, `<div class="menu-icon"><img src="${filename}" width="${iconW}" height="${iconH}" style="aspect-ratio:${iconW}/${iconH}"/></div>`, position);
		});
	};
	
			
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
			return primaryH - sidebarH - sidebarPad - compensate;
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

			['set-a', 'set-b', 'set-c'].forEach(setClass => {
				let handleSets = 0;
				getObjects(`.widget.widget-set.${setClass}`).forEach(widget => {
					if (handleSets > 0) {
						widget.setAttribute('data-priority', 0);
					}
					handleSets++;
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
				
				getObjects('.stuck').forEach(stuck => {
					viewportH -= stuck.offsetHeight;
				});

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
					sidebarObj.style.marginTop = `${findPos}px`; 
					checkHeights();
				}

			}
		};
	};				   
														   
		
/*--------------------------------------------------------------
# Enhancements
--------------------------------------------------------------*/
	
// Reveal "Are We Open" banner
	function areWeOpenBanner(delay) {
		const phoneHolder = getObject('#masthead .phone-number'),
			  bannerObj = getObject('.currently-open-banner');
		
		if (phoneHolder && bannerObj) {
			setTimeout(() => {
				moveDiv(bannerObj, phoneHolder, 'bottom');	
				
				const phoneLink = getObject(".phone-link", phoneHolder),
					  phoneLinkR = phoneLink.getBoundingClientRect().right,
					  bannerW = bannerObj.offsetWidth,
					  bannerR = phoneLinkR + bannerW,
					  offscreen = bannerR > getDeviceW() ? true : false,
					  phoneHolderW = phoneHolder.offsetWidth,
					  phoneLinkW = phoneLink.offsetWidth,
					  phoneClass = phoneHolder.closest('.col').classList,
					  phoneAlign = window.getComputedStyle(phoneHolder).textAlign,
					  bannerT = phoneHolder.clientHeight * 0.45;
				
				let	bannerL;				
				
				if (!offscreen) {	
					bannerL = phoneAlign === 'right' || phoneClass.contains('text-right') ? phoneHolderW : phoneAlign === 'left' || phoneClass.contains('text-left') ? phoneLinkW : (( phoneHolderW - phoneLinkW ) / 2) + phoneLinkW;					
				} else {
					bannerObj.classList.add('alt');
					bannerL = phoneAlign === 'right' || phoneClass.contains('text-right') ? phoneHolderW - phoneLinkW - bannerW : phoneAlign === 'left' || phoneClass.contains('text-left') ? -bannerW : (( phoneHolderW - phoneLinkW ) / 2) - bannerW;
				}			
				
				setStyles(bannerObj, {
					'top':				`${bannerT}px`,
					'left':				`${bannerL}px`				
				});
			
				bannerObj.classList.add('reveal-open');
			}, delay);
		}
	}

	// Execute the banner positioning if phone link exists  
	window.addEventListener('load', () => {
		if (getObject("#masthead .phone-link")) {
			areWeOpenBanner((Math.random() * 2000)+500);
		}
	});
	
	
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