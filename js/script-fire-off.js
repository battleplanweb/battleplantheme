document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Fire Off	
														   
	if (!window.fireOffInit) {
		const debouncedScrollFunc = debounce(() => {
			if (typeof lockAlign === 'function') { lockAlign(); }
			if (typeof toggleScrollTop === 'function') { toggleScrollTop(); }
		}, 300);
		
		const scrollFunc = () => {
			if (typeof controlLockedDivs === 'function') { controlLockedDivs(); }		
			if (typeof updateParallaxBackgrounds === 'function') { updateParallaxBackgrounds(); }	
			if (typeof updateParallaxElements === 'function') { updateParallaxElements(); }		
			if (typeof moveWidgets === 'function') { moveWidgets(); }		
			if (typeof scrollTracking === 'function') { scrollTracking(); }	
			
			if (typeof debouncedScrollFunc === 'function') { debouncedScrollFunc(); }
		};
		
		const resizeFunc = () => {
			if (typeof widgetInit === 'function') { widgetInit(); }		
			if (typeof centerSubNav === 'function') { centerSubNav(); }	
			if (typeof formLabelWidth === 'function') { formLabelWidth(); }	
			if (typeof screenResize === 'function') { screenResize(); }
			if (typeof buildAccordion === 'function') { buildAccordion(); }	
			if (typeof areWeOpenBanner === 'function') { areWeOpenBanner(0); }				
			if (typeof setMagicMenu === 'function') { setMagicMenu(); }				

			scrollFunc();	
		};
		
		const loadFunc = () => {
			resizeFunc();
		};

		let scrollTicking = false;
		window.addEventListener('scroll', () => {
			if (!scrollTicking) {
				scrollTicking = true;
				requestAnimationFrame(() => { scrollFunc(); scrollTicking = false; });
			}
		}, { passive: true });

		window.addEventListener('resize', () => { resizeFunc(); });	

		window.addEventListener('pageshow', () => { loadFunc();	});


		window.fireOffInit = true;
	}
})