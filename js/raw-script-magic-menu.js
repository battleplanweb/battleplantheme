document.addEventListener("DOMContentLoaded", function () {	"use strict"; 
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Magic Menu
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Magic Menu
--------------------------------------------------------------*/
	window.magicMenu = function(menu='#desktop-navigation .menu', linkOn='active', linkOff='non-active', stateChange='false') {
		const mainNav = getObject(menu);
		const baseNav = mainNav.parentElement.parentElement;
		let el, currentPage, magicT, magicL, magicW, magicH;
		let orient = baseNav.classList.contains('widget') ? "vertical" : "horizontal";

		baseNav.insertAdjacentHTML('afterbegin', `<div id='magic-line'></div><div id='off-screen' class='${orient}'></div>`);
		const magicLine = document.getElementById('magic-line');
		const offScreen = document.getElementById('off-screen');

		currentPage = getObject('li.current-menu-parent', mainNav) || getObject('li.current-menu-item', mainNav);
		if (!currentPage || currentPage.classList.contains('mobile-only')) {
			currentPage = offScreen;
		}
		
		const currLink = getObject('li>a', currentPage);
		if ( currLink) { currLink.classList.add(linkOn); }

		window.setMagicMenu = function() {
			let magicLineData = { origT: 0, origL: 0, origW: 0, origH: 0 };

			if (orient === "horizontal") {
				magicL = Math.round(currentPage.offsetLeft + ((currentPage.offsetWidth - currentPage.clientWidth) / 2));
				magicT = Math.round(currentPage.offsetTop);
				magicW = Math.round(currentPage.clientWidth);
				magicH = parseInt(getComputedStyle(magicLine).height);
			} else {
				magicL = parseInt(getComputedStyle(currentPage.parentElement).paddingLeft);
				magicT = Math.round(currentPage.offsetTop);
				magicW = parseInt(getComputedStyle(magicLine).width);
				magicH = Math.round(currentPage.offsetHeight);
			}

			magicLine.style.transform = `translate(${magicL}px, ${magicT}px)`;
			magicLine.style.width = `${magicW}px`;
			magicLine.style.height = `${magicH}px`;
			Object.assign(magicLineData, { origT: magicT, origL: magicL, origW: magicW, origH: magicH });

			setTimeout(() => {
				magicLine.style.opacity = 1;
			}, 250);

			let positions = [];

			getObjects('#desktop-navigation ul.main-menu > .menu-item, .widget-navigation .menu-item').forEach(item => {
				let itemT, itemL, itemW, itemH;
				if (orient === "horizontal") {
					itemT = Math.round(item.offsetTop);
					itemL = Math.round(item.offsetLeft + ((item.offsetWidth - item.clientWidth) / 2));
					itemW = Math.round(item.clientWidth);
					itemH = parseInt(getComputedStyle(magicLine).height);
				} else {
					itemT = Math.round(item.offsetTop);
					itemL = parseInt(getComputedStyle(item.parentElement).paddingLeft);
					itemW = parseInt(getComputedStyle(magicLine).width);
					itemH = Math.round(item.offsetHeight);
				}
				positions.push({ top: itemT, left: itemL, width: itemW, height: itemH });
			});

			// Attach hover events to all menu items
			getObjects('#desktop-navigation ul.main-menu > .menu-item, .widget-navigation .menu-item').forEach((item, index) => {
				item.onmouseenter = () => {
					setTimeout(() => {
						magicLine.style.transform = `translate(${positions[index].left}px, ${positions[index].top}px)`;
						magicLine.style.width = `${positions[index].width}px`;
						magicLine.style.height = `${positions[index].height}px`;
					}, 25);
					if ( currLink) { currLink.classList.replace(linkOn, linkOff); }
					getObject('li>a', item).classList.replace(linkOff, linkOn);
				};

				item.onmouseleave = () => {
					setTimeout(() => {
						magicLine.style.transform = `translate(${magicLineData.origL}px, ${magicLineData.origT}px)`;
						magicLine.style.width = `${magicLineData.origW}px`;
						magicLine.style.height = `${magicLineData.origH}px`;
					}, 25);
					getObject('li>a', item).classList.replace(linkOn, linkOff);
					if ( currLink) { currLink.classList.add(linkOn); }
				};
			});
		};
		
		if (stateChange === "true") {
			const flexElement = getObject('.flex', baseNav);
			let getMagicSide = flexElement.getBoundingClientRect().left;
			let getMagicW = flexElement.clientWidth;
			let getMagicPos = currentPage.getBoundingClientRect().left - getMagicSide;
			let getMagicAdj, getMagicPct, getMagicNow;

			window.magicColor = function(getMagicPos) {
				getMagicAdj = getMagicPos + (magicLine.clientWidth / 2); 
				getMagicPct = getMagicAdj / getMagicW;
				getMagicNow = magicLine.getBoundingClientRect().left - getMagicSide;

				document.body.classList.remove('menu-alt-1', 'menu-alt-2', 'menu-alt-3', 'menu-dir-l', 'menu-dir-r');
				if (getMagicPct < 0.33) {
					document.body.classList.add('menu-alt-1');
				} else if (getMagicPct >= 0.33 && getMagicPct < 0.66) {
					document.body.classList.add('menu-alt-2');
				} else {
					document.body.classList.add('menu-alt-3');
				}

				if (getMagicAdj <= getMagicNow) {
					document.body.classList.add('menu-dir-l');
				} else {
					document.body.classList.add('menu-dir-r');
				}
			};

			getObjects('li', baseNav).forEach(li => {
				li.addEventListener('mouseover', () => {
					getMagicPos = li.getBoundingClientRect().left - getMagicSide;
					magicColor(getMagicPos);
				});
			});

			flexElement.addEventListener('mouseout', () => {
				getMagicPos = currentPage.getBoundingClientRect().left - getMagicSide;
				magicColor(getMagicPos);
			});

			// Initialize color on load
			magicColor(getMagicPos);
		}
		
		setTimeout(() => { setMagicMenu(); }, 500);
	};
	
});