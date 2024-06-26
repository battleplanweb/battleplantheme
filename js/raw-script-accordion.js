document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Accordion
														   
	window.buildAccordion = function () {
		window.accordions = getObjects('.block-accordion');
		
	// Calculate and store heights														   
		accordions.forEach(accordion => {			
			const contentObj = getObject('.accordion-content', accordion);		
			const images = getObjects('img', contentObj);
			const rect = accordion.getBoundingClientRect();
			
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
		});		
	}

	function openAccordion(accordion, content, button) {	
		if (button) {			
			const btn_collapse = button.getAttribute('data-collapse');	
			if (btn_collapse && btn_collapse !== "hide") {
				button.innerHTML = btn_collapse;
			} else {
				button.style.display = "none";
			}
		}
		
		setStyles(content, {
			'height':			content.getAttribute('data-height') + 'px',
			'opacity': 			1
		});	
		accordion.setAttribute('aria-expanded', 'true');
		accordion.classList.add('active');  

		animateScroll(content);
	}

	function closeAccordion(accordion, content, button) {
		setStyles(content, {
			'height':			'0px',
			'opacity': 			0
		});	
		accordion.setAttribute('aria-expanded', 'false');
		accordion.classList.remove('active'); 
		
		if (button) {
			const btn_text = button.getAttribute('data-text');
			if (btn_text) button.innerHTML = btn_text;
			
			setTimeout(() => { animateScroll(accordion.previousElementSibling); }, 50);
		}
	}

	getObjects('.block-accordion .accordion-button').forEach(button => {
		button.addEventListener('click', function(e) {
			e.preventDefault();
			const thisAcc = button.closest('.block-accordion'),
				  contentObj = getObject('.accordion-content', thisAcc),
				  isExpanded = thisAcc.getAttribute('aria-expanded') === 'true',
				  btn_collapse = button.getAttribute('data-collapse');
				  //passBtn = button.classList.contains('accordion-title') ? null : button;   removed 6/11/24 - Deer Hollow Cabin page btns
			
			if (btn_collapse && btn_collapse !== "hide") {				
				if (isExpanded) {
					closeAccordion(thisAcc, contentObj, button);
				} else {
					getObjects('.block-accordion[aria-expanded="true"]').forEach(expanded => {
						closeAccordion(expanded, getObject('.accordion-content', expanded), button);
					});
				}
			}
			openAccordion(thisAcc, contentObj, button);
		});

		button.addEventListener('keypress', function(e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				button.click();
			}
		});
	});
								
	buildAccordion();													   
});