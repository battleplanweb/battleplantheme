document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Holiday	
	document.body.classList.add('holiday-theme');

	const masthead = getObject('.screen-desktop #masthead > *:first-child');

	if (masthead) {
		const padding = parseInt(window.getComputedStyle(masthead).paddingTop, 10) + 20;
		masthead.style.paddingTop = `${padding}px`;
	}									   	   
														   
	const content_sidebar_box = getObject('.content-sidebar-box #wrapper-content #main-content');
	const content_box = getObject('.content-box #wrapper-content #main-content');
	const sidebar_box = getObject('.sidebar-box #wrapper-content #main-content');	
    const widget_box = getObject('.widget-box #wrapper-content #main-content');

	if (content_sidebar_box) {							   
		content_sidebar_box.classList.add('xmas-corner');
	} else if (content_box && sidebar_box) {							   
		content_box.classList.add('xmas-corner');
		sidebar_box.classList.add('xmas-corner');
	} else if (content_box && widget_box) {							   
		content_box.classList.add('xmas-corner');
		widget_box.classList.add('xmas-corner');
	} else if (content_box && !sidebar_box && !widget_box) {
		const content_box_inner = getObject('.sidebar-box #wrapper-content #main-content #primary');
		if ( content_box_inner) {
			content_box_inner.classList.add('xmas-corner');
		}
	} else if (!content_box && sidebar_box) {							   
		const sidebar_box_inner = getObject('.sidebar-box #wrapper-content #main-content #secondary');
		if ( sidebar_box_inner) {
			sidebar_box_inner.classList.add('xmas-corner');
		}
	} else if (!content_box && widget_box) {							   
		const widget_box_inner = getObject('.widget-box #wrapper-content #main-content #secondary');
		if ( widget_box_inner) {
			widget_box_inner.classList.add('xmas-corner');
		}
	} else { 
		const content_area = getObject('#wrapper-content #main-content');
		if (content_area) {
			content_area.classList.add('xmas-wide-alt');		
			const primary = getObject('#wrapper-content #main-content #primary');
			if (primary) {
				const padding = parseInt(window.getComputedStyle(primary).paddingTop, 10) + 35;
				primary.style.paddingTop = `${padding}px`;
			}
		}
	}
														   
	const sections = getObjects('.section[class*="style-"]');
	if (sections) {
		let alternate = 1;

		sections.forEach(section => {							   
			const sectionBG = window.getComputedStyle(section).backgroundColor;			
			const sectionBGImage = window.getComputedStyle(section).backgroundImage;
			const beforeSection = getComputedStyle(section, '::before').content;
			const afterSection = getComputedStyle(section, '::after').content;

			if (beforeSection === '' && afterSection === '') {
							
				if ( (sectionBG !== 'rgba(0, 0, 0, 0)' && sectionBG !== 'transparent') || (sectionBGImage && sectionBGImage !== 'none') ) {
					if ( alternate === 0 ) {
						const classes = ['xmas-wide-5', 'xmas-wide-10', 'xmas-wide-15'];
						const randomize = classes[Math.floor(Math.random() * classes.length)];
						section.classList.add('xmas-wide', randomize);
						alternate = 1;
					} else {
						section.classList.add('xmas-wide-alt');
						alternate = 0;
					}
				} else {
					const grid = getObject('.flex', section);
					let numDivs = 0;

					if (grid) {
						if ( grid.classList.contains('grid-1') ) {
							numDivs = 1;
						} else if ( grid.classList.contains('grid-1-1') || grid.classList.contains('grid-3-2') || grid.classList.contains('grid-2-3') ) {
							numDivs = 2;					
						} else if ( grid.classList.contains('grid-1-1-1') ) {
							numDivs = 3;
						}
					} 				

					const elements = getObjects('.col-inner', section);

					if (elements) {
						elements.forEach(element => {	  
							const elementBG = window.getComputedStyle(element).backgroundColor;

							if (elementBG !== 'rgba(0, 0, 0, 0)' && sectionBG !== 'transparent') {
								element.classList.add(`xmas-narrow-${numDivs}`);
							} 	
						});
					}
				}
			}
		});
	}
														   
	const colophon = getObject('#colophon');													   
	const colophonBG = window.getComputedStyle(colophon).backgroundColor;			
	const colophonBGImage = window.getComputedStyle(colophon).backgroundImage;
			
	if ( (colophonBG !== 'rgba(0, 0, 0, 0)' && colophonBG !== 'transparent') || (colophonBGImage && colophonBGImage !== 'none') ) {
		colophon.classList.add('xmas-colophon');
	}
														   
});