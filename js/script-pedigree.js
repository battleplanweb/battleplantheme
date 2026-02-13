document.addEventListener("DOMContentLoaded", function () {	"use strict";

// Raw Script: Pedigree

	window.addEventListener("load", () => {

		replaceText('.single-litters h1, .single-litters h2, .single-litters h3', ' X ', ' x ', 'html');
		replaceText('.single-litters h1, .single-litters h2, .single-litters h3', ' x ', '<span class="X"> x </span>', 'html');

		moveDiv('#wrapper-bracket', '#colophon', 'before');
		moveDivs('.col-archive.col-dogs', '.sex-box', '.block.block-image', 'top');

		// setup filtering of dogs & litters archive pages with buttons
		keyPress('button.female-btn, button.male-btn, button.legacy-btn, button.all-btn, button.available-btn, button.expecting-btn');

		function chooseCategory(cat) {
			if ( cat === 'all' ) {
				filterArchives();
			} else {
				filterArchives("dogs-"+cat);
			}

			getObjects('button, .p-intro').forEach(obj => {
				obj.classList.remove('active');
			});

			getObjects(`button.${cat}-btn, .intro-${cat}`).forEach(obj => {
				obj.classList.add('active');
			});
		}

		const button_female = getObject('button.female-btn');

		if (button_female) {
			button_female.addEventListener('click', () => chooseCategory('female'));
			getObject('button.male-btn').addEventListener('click', () => chooseCategory('male'));
			getObject('button.legacy-btn').addEventListener('click', () => chooseCategory('legacy'));
			getObject('button.all-btn').addEventListener('click', () => chooseCategory('all'));
		}

		if (getUrlVar('page') === "males") { chooseCategory('male');
			} else if (getUrlVar('page') === "legacy") { chooseCategory('legacy');
			} else if (getUrlVar('page') === "females") { chooseCategory('female');
			} else { chooseCategory('all');
			}

		const buttons = getObjects('button');
		const button_available = getObject('button.available-btn');
		const button_expecting = getObject('button.expecting-btn');

		if (button_available) {
			button_available.addEventListener('click', () => {
				filterArchives("litter-available");
				buttons.forEach(btn => btn.classList.remove("active"));
				button_available.classList.add("active");
			});
		}

		if (button_available) {
			button_expecting.addEventListener('click', () => {
				filterArchives("litter-expecting");
				buttons.forEach(btn => btn.classList.remove("active"));
				button_expecting.classList.add("active");
			});
		}

		if (getUrlVar('page') === "available") {
			filterArchives("litter-available");
			buttons.forEach(btn => btn.classList.remove("active"));
			button_available.classList.add("active");
		}
		if (getUrlVar('page') === "expecting") {
			filterArchives("litter-expecting");
			buttons.forEach(btn => btn.classList.remove("active"));
			button_expecting.classList.add("active");
		}

	});
});