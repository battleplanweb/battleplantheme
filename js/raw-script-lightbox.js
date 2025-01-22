document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Lightbox
														   
	const overlay = getObject('.lightbox-overlay'),
		  lightboxImage = getObject('.lightbox-overlay img'),
		  counterDisplay = getObject('.lightbox-overlay .lightbox-counter'),
		  prevButton = getObject('.lightbox-overlay .button-prev'),
		  nextButton = getObject('.lightbox-overlay .button-next'),
		  closeButton = getObject('.lightbox-overlay .closeBtn'),
		  images = getObjects('.lightbox a'),
		  threshold = 30;
														   
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

		function closeLightbox() {
			overlay.style.opacity = '0';    
			setTimeout(() => {
				overlay.style.visibility = 'hidden';  
				document.body.style.overflow = 'visible';
			}, 500);
		}

		function changePhoto(currentImageIndex) {
			lightboxImage.classList.add('transition-out', 'direction-'+direction);
			const newImage = new Image();
			newImage.src = images[currentImageIndex].href;		

			let imageLoaded = false; 
			let timeoutCompleted = false;

			const tryTransitionIn = () => {
				if (imageLoaded && timeoutCompleted) {		
					lightboxImage.src = newImage.src;
					lightboxImage.alt = getObject('img', images[currentImageIndex]).alt;     
					lightboxImage.classList.remove('transition-out');
					lightboxImage.classList.add('transition-in');
					 setTimeout(() => {
						lightboxImage.classList.remove('transition-in', 'direction-'+direction);
					}, 300);
					updateCounterDisplay(currentImageIndex, images.length);
				}
			};

			newImage.onload = () => {
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

		images.forEach((imageLink, index) => {
			imageLink.addEventListener('click', (event) => {
				event.preventDefault(); 
				lightboxImage.src = imageLink.href; 
				lightboxImage.alt = getObject('img', imageLink).alt;
				overlay.style.opacity = '1';      
				overlay.style.visibility = 'visible';
				document.body.style.overflow = 'hidden';
				overlay.style.opacity = 1; 
				currentImageIndex = index; 
				updateCounterDisplay(currentImageIndex, images.length);
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