document.addEventListener("DOMContentLoaded", function () {	"use strict";
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Lightbox
 

Re-factor complete 5/10/2024

--------------------------------------------------------------*/
	
/*--------------------------------------------------------------
# Lightbox
--------------------------------------------------------------*/
	const overlay = getObject('.lightbox-overlay');
    const lightboxImage = getObject('.lightbox-overlay img');
    const counterDisplay = getObject('.lightbox-overlay .lightbox-counter');
    const prevButton = getObject('.lightbox-overlay .button-prev');
    const nextButton = getObject('.lightbox-overlay .button-next');    
	const closeButton = getObject('.lightbox-overlay .closeBtn');
														   
	moveDiv(overlay, '#loader', 'after');													

    const images = getObjects('.lightbox a');
    let currentImageIndex = -1, direction = 'next';
														   
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
})