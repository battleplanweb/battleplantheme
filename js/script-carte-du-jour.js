document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Carte Du Jour															   
		
// Handle mutliple locations		
	getObjects('.logo').forEach(logo => {
		logo.addEventListener('click', () => {
			setCookie('cdj-loc', '', 30);
		});
	});

	const locations = Object.entries(locArray), 
		  cdj_location = getCookie('cdj-loc');

	locations.forEach(location => {
		const slug = location[1]['slug'];
		getObjects('.show-' + slug).forEach(el => el.classList.add('loc'));
		getObjects('a.button.' + slug).forEach(el => el.classList.add('loc-btn'));
	});	

// Function to display the location
	window.displayLocation = function (loc) {
		getObjects('a.button').forEach(button => button.classList.remove('active'));
		getObjects(`a.button.${loc}`).forEach(button => button.classList.add('active'));

		const bodyClassList = document.body.className.split(' ');
		bodyClassList.forEach(className => {
			if (className.startsWith('location-')) {
				document.body.classList.remove(className);
			}
			if (className.startsWith('slug-menu-')) {
				document.body.classList.add('menu-page');
			}
		});

		document.body.classList.add(`location-${loc}`);
		getObjects('.location-unknown, .loc').forEach(el => {	
			const displayValue = el.getAttribute('data-display'); 
			if (!displayValue) {
				const computedDisplay = window.getComputedStyle(el).display;
				el.setAttribute('data-display', computedDisplay || 'block'); 
			}
			el.style.display = 'none';
		});
		getObjects(`.show-${loc}`).forEach(el => {
			const displayValue = el.getAttribute('data-display') || 'block'; 
			el.style.display = displayValue;
			el.style.opacity = 0;
			setTimeout(() => el.style.opacity = 1, 0);
		});

		setCookie('cdj-loc', loc, 30);
	};

	if (cdj_location) {
		displayLocation(cdj_location);
	}

	getObjects('a.button').forEach(button => {
		button.addEventListener('click', () => {
			for (const [key, value] of Object.entries(locArray)) {
				if (button.classList.contains(value.slug)) {
					displayLocation(value.slug);
					break;
				}
			}
		});
	});

/*
	// Create galleries to display pics associated with (food) menu items
	let menuExists = false;
	const elements = getObjects('.menu-page #main-content h4, .menu-page .list-item');

	elements.forEach(element => {
		const override = element.getAttribute('data-title');
		let title = element.innerHTML;
		const index = title.indexOf(" <");
		let gallery1 = element.previousElementSibling && element.previousElementSibling.matches('h2[data-category]')
			? element.previousElementSibling.getAttribute('data-category')
			: null;
		let gallery2 = element.closest('.small-list') 
			? element.closest('.small-list').previousElementSibling 
			&& element.closest('.small-list').previousElementSibling.matches('h2[data-category]')
				? element.closest('.small-list').previousElementSibling.getAttribute('data-category')
				: null
			: null;
		let gallery = gallery1 || gallery2;

		if (override) {
			title = override;
		} else if (index !== -1) {
			title = title.slice(0, index);
		}

		const link = imgLinks[title];
		const desc = imgDesc[title];

		if (link) {
			menuExists = true;
			const anchor = document.createElement('a');
			anchor.href = link;
			anchor.className = 'glightbox';
			anchor.setAttribute('data-gallery', gallery);
			anchor.setAttribute('data-glightbox', `title: ${title}; description: ${desc}; descPosition: left; type: image; effect: fade; zoomable: true; draggable: true;`);

			const span = document.createElement('span');
			span.className = 'icon search-plus menu-img-btn';
			span.setAttribute('aria-hidden', 'true');

			anchor.appendChild(span);
			element.prepend(anchor);
		}
	});

	if (menuExists) {
		const lightbox = GLightbox({
			touchNavigation: true,
			loop: true,
			autoplayVideos: true,
		});
	}	
	*/
});