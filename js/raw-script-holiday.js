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

	if (content_sidebar_box) {							   
		content_sidebar_box.classList.add('xmas-corner');
	} else if (content_box && sidebar_box) {							   
		content_box.classList.add('xmas-corner');
		sidebar_box.classList.add('xmas-corner');
	} else { 
		const content_area = getObject('#wrapper-content #main-content');
		content_area.classList.add('xmas-wide-alt');
	}
														   
	const sections = getObjects('.section[class*="style-"]');
	if (sections) {
		let alternate = 1;

		sections.forEach(section => {							   
			const sectionBG = window.getComputedStyle(section).backgroundColor;			
			const sectionBGImage = window.getComputedStyle(section).backgroundImage;
			
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
		});
	}
														   
	const colophon = getObject('#colophon');													   
	const colophonBG = window.getComputedStyle(colophon).backgroundColor;			
	const colophonBGImage = window.getComputedStyle(colophon).backgroundImage;
			
	if ( (colophonBG !== 'rgba(0, 0, 0, 0)' && colophonBG !== 'transparent') || (colophonBGImage && colophonBGImage !== 'none') ) {
		colophon.classList.add('xmas-colophon');
	}
														   
});