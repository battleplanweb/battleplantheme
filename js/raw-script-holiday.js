document.addEventListener("DOMContentLoaded", function () {	"use strict";
	
	document.body.classList.add('holiday-theme');

	const masthead = getObject('.screen-desktop #masthead > *:first-child');

	if (masthead) {
		const padding = parseInt(window.getComputedStyle(masthead).paddingTop, 10) + 20;
		masthead.style.paddingTop = `${padding}px`;
	}	
														   
});