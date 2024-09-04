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
			if (typeof widgetInit === 'function') { console.log('widgetInit()'); widgetInit(); }		
			if (typeof centerSubNav === 'function') { console.log('centerSubNav()'); centerSubNav(); }	
			if (typeof formLabelWidth === 'function') { console.log('formLabelWidth()'); formLabelWidth(); }	
			if (typeof screenResize === 'function') { console.log('screenResize()'); screenResize(); }	
			if (typeof buildAccordion === 'function') { console.log('buildAccordion()'); buildAccordion(); }	
			if (typeof areWeOpenBanner === 'function') { console.log('areWeOpenBanner()'); areWeOpenBanner(0); }				
			if (typeof setMagicMenu === 'function') { console.log('setMagicMenu()'); setMagicMenu(); }				

			console.log('scrollFunc()');
			scrollFunc();	
		};
		
		const loadFunc = () => {
			if (typeof fadeOutLoader === 'function') { fadeOutLoader(0); }	
			
			resizeFunc();	
		};

		window.addEventListener('scroll', () => { scrollFunc(); });	

		window.addEventListener('resize', () => { resizeFunc(); });	

		window.addEventListener('pageshow', () => { loadFunc();	});			
		
		document.addEventListener('DOMContentLoaded', () => { fadeOutLoader(0.5); });		

		window.fireOffInit = true;
	}
})