document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Accordion
														   
	window.buildAccordion = function () {
		window.accordions = getObjects('.block-accordion');
		
		let multiple_open = true;
		
	// Calculate and store heights														   
		accordions.forEach(accordion => {			
			const contentObj = getObject('.accordion-content', accordion),
				  excerptObj = getObject('.accordion-excerpt', accordion),
				  images = getObjects('img', contentObj),
				  rect = accordion.getBoundingClientRect(),
				  button = getObject('.accordion-button', accordion),
				  btn_collapse = button.getAttribute('data-collapse'),
				  multiple = accordion.getAttribute('data-multiple');			
			
			if ( multiple == 'false' ) { multiple_open = false; }
			
			const setHeight = () => {
				setTimeout(() => {
					contentObj.setAttribute('data-height', contentObj.scrollHeight);
					contentObj.setAttribute('data-top', rect.top);
					if ( !accordion.classList.contains('active') ) { 
						closeAccordion(accordion, contentObj, null); 
					}
				}, 10);
			}

			if (images.length > 0) {
				let imagesLoaded = 0;
				images.forEach(img => {
					if (img.complete) {
						imagesLoaded++;
					} else {
						img.addEventListener('load', () => {
							imagesLoaded++;
							if (imagesLoaded === images.length) {
								setHeight();
							}
						});
					}
				});
				if (imagesLoaded === images.length) {
					setHeight();
				}
			} else {
				setHeight();
			}
								console.log('new 7');
	
			if ( accordion.classList.contains('start-active') ) {
				openAccordion(accordion, contentObj, excerptObj, button);
			} else {
				button.addEventListener('click', function(e) {
					e.preventDefault();		
					if (!multiple_open) {
						getObjects('.block-accordion[aria-expanded="true"]').forEach(expanded => {
							closeAccordion(expanded, getObject('.accordion-content', expanded), button);
						});
					}			

					openAccordion(accordion, contentObj, excerptObj, button);
				});
			}

			button.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					button.click();
				}
			});
			
			
		});		
	}

	const openAccordion = (accordion, content, excerpt, button) => {	
		button && button.getAttribute('data-collapse') 
			? button.getAttribute('data-collapse') !== "hide" 
				? button.innerHTML = button.getAttribute('data-collapse') 
				: button.style.display = "none"
			: null;

		setStyles(content, {
			'height': content.getAttribute('data-height') + 'px',
			'opacity': 1
		});	
		
		if ( excerpt ) {
			setStyles(excerpt, {
				'height': '0px',
				'opacity': 0
			});	
		}

		accordion.setAttribute('aria-expanded', 'true');
		accordion.classList.add('active'); 

		!content.classList.contains('no-scroll') && animateScroll(content);
	};

	const closeAccordion = (accordion, content, button) => {
		setStyles(content, { 'height': '0px', 'opacity': 0 });
		accordion.setAttribute('aria-expanded', 'false');
		accordion.classList.remove('active');
		
		button && button.getAttribute('data-collapse') 
			? button.getAttribute('data-collapse') !== "hide" 
				? (button.innerHTML = button.getAttribute('data-text'),
					!accordion.classList.contains('no-scroll') && 
						setTimeout(() => animateScroll(accordion.previousElementSibling), 50)) 
				: button.style.display = "none" 
			: null;

	};
								
	buildAccordion();													   
});