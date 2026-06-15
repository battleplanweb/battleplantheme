document.addEventListener("DOMContentLoaded", function () {	"use strict";

// Raw Script: Lightbox

	const overlay = getObject('.lightbox-overlay'),
		  lightboxImage = getObject('.lightbox-overlay img'),
		  counterDisplay = getObject('.lightbox-overlay .lightbox-counter'),
		  prevButton = getObject('.lightbox-overlay .button-prev'),
		  nextButton = getObject('.lightbox-overlay .button-next'),
		  closeButton = getObject('.lightbox-overlay .closeBtn'),
		  threshold = 30;

	// The active image set — re-pointed to the clicked .lightbox group on open,
	// so prev/next navigate within one gallery/job rather than every link on the page.
	let images = [];

	if ( overlay ) {

		moveDiv(overlay, '#loader', 'after');

		let currentImageIndex = -1,
			direction = 'next',
			touchStartX = 0,
			touchEndX = 0,
			touchStartY = 0,
			touchEndY = 0;

		const resetTouch = () => { touchStartX = touchEndX = touchStartY = touchEndY = 0; };

		const handleSwipeGesture = () => {
			(Math.abs(touchEndX - touchStartX) > Math.abs(touchEndY - touchStartY) && Math.abs(touchEndX - touchStartX) > threshold)
				? touchEndX > touchStartX
					? prevButton.click()
					: nextButton.click()
				: null;
			resetTouch();
		};

		overlay.addEventListener('touchstart', e => {
			({screenX: touchStartX, screenY: touchStartY} = e.changedTouches[0]);
		}, {passive: true});

		overlay.addEventListener('touchmove', e => {
			({screenX: touchEndX, screenY: touchEndY} = e.changedTouches[0]);
			(Math.abs(touchEndX - touchStartX) > Math.abs(touchEndY - touchStartY) && Math.abs(touchEndX - touchStartX) > threshold && e.cancelable)
				? e.preventDefault()
				: null;
		}, {passive: false});

		overlay.addEventListener('touchend', () => handleSwipeGesture(), {passive: true});

		// Hide prev/next/counter when a gallery has a single image (e.g. a one-photo job).
		function toggleNav(count) {
			const hide = count < 2 ? 'none' : '';
			if (prevButton) prevButton.style.display = hide;
			if (nextButton) nextButton.style.display = hide;
			if (counterDisplay) counterDisplay.style.display = hide;
		}

		function closeLightbox() {
			overlay.style.opacity = '0';
			setTimeout(() => {
				overlay.style.visibility = 'hidden';
				document.body.style.overflow = 'visible';
			}, 500);
		}

		function changePhoto(currentImageIndex) {
			// Fade the current photo out…
			lightboxImage.classList.add('transition-out', 'direction-'+direction);
			const newImage = new Image();
			newImage.src = images[currentImageIndex].href;

			let imageLoaded = false;
			let timeoutCompleted = false;

			const tryTransitionIn = () => {
				if (!imageLoaded || !timeoutCompleted) return;

				// Swap the source while the image is still faded out (opacity 0),
				// then wait for the new frame to be decoded / paint-ready before
				// fading back in. Otherwise the OLD photo fades in first and the
				// new one snaps in afterward.
				lightboxImage.src = newImage.src;
				lightboxImage.alt = getObject('img', images[currentImageIndex]).alt;

				const fadeIn = () => {
					lightboxImage.classList.remove('transition-out');
					lightboxImage.classList.add('transition-in');
					setTimeout(() => {
						lightboxImage.classList.remove('transition-in', 'direction-'+direction);
					}, 300);
					updateCounterDisplay(currentImageIndex, images.length);
				};

				if (typeof lightboxImage.decode === 'function') {
					lightboxImage.decode().then(fadeIn).catch(fadeIn);
				} else {
					requestAnimationFrame(() => requestAnimationFrame(fadeIn));
				}
			};

			newImage.onload = () => {
				imageLoaded = true;
				tryTransitionIn();
			};

			newImage.onerror = () => {
				imageLoaded = true;
				tryTransitionIn();
			};

			setTimeout(() => {
				timeoutCompleted = true;
				tryTransitionIn();
			}, 300);
		}

		function updateCounterDisplay(index, total) {
			counterDisplay.textContent = `${index + 1} of ${total}`;
		}

		// Bind each .lightbox container as its own gallery. Clicking a thumbnail
		// activates that container's links, so the overlay only walks those images.
		getObjects('.lightbox').forEach((group) => {
			const groupLinks = Array.from(group.querySelectorAll('a'));
			groupLinks.forEach((imageLink, index) => {
				imageLink.addEventListener('click', (event) => {
					event.preventDefault();
					images = groupLinks;
					currentImageIndex = index;

					overlay.style.opacity = '1';
					overlay.style.visibility = 'visible';
					document.body.style.overflow = 'hidden';
					toggleNav(groupLinks.length);
					updateCounterDisplay(currentImageIndex, images.length);

					// Hide the previous photo INSTANTLY (no fade) so a stale image from
					// an earlier set can't flash, then load + decode the new one and
					// fade it in once it's paint-ready.
					lightboxImage.style.transition = 'none';
					lightboxImage.classList.remove('transition-in');
					lightboxImage.classList.add('transition-out');
					void lightboxImage.offsetWidth;       // apply opacity:0 immediately
					lightboxImage.style.transition = '';   // restore the CSS fade

					lightboxImage.src = imageLink.href;
					lightboxImage.alt = getObject('img', imageLink).alt;

					const fadeIn = () => {
						lightboxImage.classList.remove('transition-out');
						lightboxImage.classList.add('transition-in');
					};
					if (typeof lightboxImage.decode === 'function') {
						lightboxImage.decode().then(fadeIn).catch(fadeIn);
					} else {
						requestAnimationFrame(() => requestAnimationFrame(fadeIn));
					}
				});
			});
		});

		prevButton.addEventListener('click', () => {
			if (currentImageIndex > 0) {
				currentImageIndex--;
			} else {
				currentImageIndex = images.length - 1;
			}
			direction = 'prev';
			changePhoto(currentImageIndex);
		});

		nextButton.addEventListener('click', () => {
			if (currentImageIndex < images.length - 1) {
				currentImageIndex++;
			} else {
				currentImageIndex = 0;
			}
			direction = 'next';
			changePhoto(currentImageIndex);
		});

		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) { closeLightbox(); }
		});

		closeButton.addEventListener('click', closeLightbox);
	}
})
