document.addEventListener("DOMContentLoaded", function () {
	"use strict";

	// Raw Script: Swipe Scrollers
	//
	// Drives any row the shortcodes marked .swipe — [layout scroll="true"] columns and
	// [side-by-side] photo strips. Below its break point style-scroller.css turns the row
	// into a horizontal scroll-snap carousel; the swiping itself is native, so this only
	// builds the position dots, keeps them in sync, and makes the row keyboard reachable
	// once it actually overflows.
	//
	// The dots are .carousel-indicators (style-indicators.css) — the same markup and
	// styling [get-post-slider] uses, so a page with both keeps one look.

	getObjects('.swipe').forEach(row => {
		const slides = Array.from(row.children);
		if (slides.length < 2) return;

		const scrollToSlide = slide => {
			const slideBox = slide.getBoundingClientRect(),
				rowBox = row.getBoundingClientRect();
			row.scrollBy({
				left: (slideBox.left + (slideBox.width / 2)) - (rowBox.left + (rowBox.width / 2)),
				behavior: 'smooth'
			});
		};

		// Build the dots
		const dots = document.createElement('ol');
		dots.className = 'carousel-indicators swipe-dots';

		slides.forEach((slide, i) => {
			const dot = document.createElement('li');
			dot.setAttribute('role', 'button');
			dot.setAttribute('tabindex', '0');
			dot.setAttribute('data-slide-to', i);
			dot.setAttribute('aria-label', 'Show item ' + (i + 1) + ' of ' + slides.length);
			if (i === 0) dot.classList.add('active');

			dot.addEventListener('click', () => scrollToSlide(slide));
			dot.addEventListener('keydown', e => {
				if (e.key !== 'Enter' && e.key !== ' ') return;
				e.preventDefault();
				scrollToSlide(slide);
			});
			dot.addEventListener('focus', () => dot.classList.add('tab-focus'));
			dot.addEventListener('blur', () => dot.classList.remove('tab-focus'));

			dots.appendChild(dot);
		});

		row.after(dots);
		const markers = Array.from(dots.children);

		// Measure how far the content sits in from each side of the screen and hand that to
		// the CSS as --bp-bleed-*: the row is pulled out to the edges so a half-visible
		// slide runs off the screen instead of stopping short at the content inset, and the
		// first and last slides take the inset back so the row still lines up with content.
		//
		// Measured off the dots, not the row: they sit in the row's own grid column but are
		// never bled, so the reading is immune both to the bleed already applied and to any
		// inline margin the shortcode put on the row itself.
		const setBleed = () => {
			const box = dots.getBoundingClientRect(),
				screenW = document.documentElement.clientWidth;

			if (!box.width) return;

			row.style.setProperty('--bp-bleed-left', Math.max(0, Math.round(box.left)) + 'px');
			row.style.setProperty('--bp-bleed-right', Math.max(0, Math.round(screenW - box.right)) + 'px');
		};

		// A row inside a grid (a .flex in a section, a strip in a .flex.grid-*) leaves its
		// dots in the next grid row — sit them in the row's own columns and pull them back
		// up through the grid's row gap, so they read as part of the same block.
		const placeDots = () => {
			const parentStyles = window.getComputedStyle(row.parentElement);
			if (parentStyles.display.indexOf('grid') === -1) return;

			const column = window.getComputedStyle(row).gridColumn;
			if (column && column !== 'auto' && column !== 'auto / auto') dots.style.gridColumn = column;

			const rowGap = parseFloat(parentStyles.rowGap);
			if (rowGap > 0) dots.style.marginTop = 'calc(0.5em - ' + rowGap + 'px)';
		};

		// Mark whichever slide is sitting closest to the middle of the row
		const syncDots = () => {
			const rowBox = row.getBoundingClientRect(),
				middle = rowBox.left + (rowBox.width / 2);

			let active = 0,
				closest = Infinity;

			slides.forEach((slide, i) => {
				const slideBox = slide.getBoundingClientRect(),
					distance = Math.abs((slideBox.left + (slideBox.width / 2)) - middle);
				if (distance < closest) {
					closest = distance;
					active = i;
				}
			});

			markers.forEach((dot, i) => dot.classList.toggle('active', i === active));
		};

		let waiting = false;
		row.addEventListener('scroll', () => {
			if (waiting) return;
			waiting = true;
			requestAnimationFrame(() => {
				waiting = false;
				syncDots();
			});
		}, { passive: true });

		// Dots + keyboard scrolling only when the items really do run off the screen
		const checkSwipable = () => {
			const swipable = row.scrollWidth > (row.clientWidth + 1);
			row.classList.toggle('is-swipable', swipable);

			if (swipable) {
				row.setAttribute('tabindex', '0');
				placeDots();
				setBleed();
				syncDots();
			} else {
				row.removeAttribute('tabindex');
				row.style.removeProperty('--bp-bleed-left');
				row.style.removeProperty('--bp-bleed-right');
			}
		};

		checkSwipable();
		window.addEventListener('load', checkSwipable);
		window.addEventListener('resize', debounce(checkSwipable, 250));
	});
});
