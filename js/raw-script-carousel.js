document.addEventListener("DOMContentLoaded", function () {
	"use strict";

	// Raw Script: Carousel

	getObjects('.carousel').forEach(carousel => {
		const slides = getObjects('.carousel-item', carousel),
			slideInner = getObject('.carousel-inner', carousel),
			indicators = getObjects('.carousel-indicators li', carousel),
			interval = parseInt(carousel.getAttribute('data-interval')) || 6000,
			autoPlay = carousel.getAttribute('data-auto') || true,
			hoverPause = carousel.getAttribute('data-pause') || true,
			contentType = carousel.classList.contains('content-image') ? 'image' : 'text',
			start = carousel.getAttribute('data-random') === 'true' ? Math.floor(Math.random() * slides.length) : 0;
		let timer = null,
			currentSlide = 0,
			direction = "right",
			maxH = slideInner ? slideInner.scrollHeight : 0;

		// Keep the indicators lit according to active slide
		const updateIndicators = () => {
			indicators.forEach((indicator, index) => {
				indicator.classList.toggle('active', index === currentSlide);
			});
		};

		// Ensure text slides are all equal height

		const setCarouselHeight = () => {
			const activeSlide = getObject('.carousel-item.active', carousel);
			if (!activeSlide) return;

			const activeSlideH = activeSlide.scrollHeight;
			if (activeSlideH > maxH) {
				maxH = activeSlideH;
				slideInner.style.height = `${maxH}px`;
				//console.log('adjusting height: ' + maxH);
			}
		};

		// Reset on resize — but debounce to avoid thrash
		let resizeTimer;
		window.addEventListener('resize', () => {
			clearTimeout(resizeTimer);
			resizeTimer = setTimeout(() => {
				maxH = 0;
				setCarouselHeight();
			}, 200);
		});

		// Watch for lazy-loaded images and adjust dynamically
		const watchLazyImages = () => {
			slides.forEach(slide => {
				const imgs = getObjects('img', slide);
				imgs.forEach(img => {
					// Recalculate only if this image changes layout height
					img.addEventListener('load', () => {
						const parentH = slide.scrollHeight;
						if (parentH > maxH) {
							maxH = parentH;
							slideInner.style.height = `${maxH}px`;
						}
					});
				});
			});
		};
		watchLazyImages();

		// Set aspect ratio based on size of images inside the carousel
		const calculateAspectRatio = () => {
			let maxImageWidth = 0,
				maxImageHeight = 0;

			slides.forEach(slide => {
				const images = getObjects('img', slide);
				images.forEach(image => {
					const imgWidth = image.getAttribute('width');
					const imgHeight = image.getAttribute('height');
					if (imgWidth && imgHeight) {
						const imgW = parseInt(imgWidth);
						const imgH = parseInt(imgHeight);
						if (imgW > maxImageWidth) {
							maxImageWidth = imgW;
							maxImageHeight = imgH;
						}
					}
				});
			});

			if (maxImageWidth > 0 && maxImageHeight > 0) {
				if (getDeviceW() < mobileCutoff) {
					slideInner.style.aspectRatio = `${maxImageWidth}/${maxImageHeight}`;
				} else {
					setCarouselHeight();
				}
			}
		};

		// Go to the next slide
		const goToSlide = (n, dir) => {
			direction = dir;
			let formerSlide = slides[currentSlide];
			if (formerSlide) {
				formerSlide.classList.add('transitioning', 'transitioning-' + direction, 'transitioning-out');
				setTimeout(() => { formerSlide.classList.remove('active', 'transitioning', 'transitioning-' + direction, 'transitioning-out'); }, 500);

				currentSlide = (n + slides.length) % slides.length;
				slides[currentSlide].classList.add('next-slide', 'transitioning', 'transitioning-' + direction, 'transitioning-in');
				setTimeout(() => {
					slides[currentSlide].classList.remove('next-slide', 'transitioning', 'transitioning-' + direction, 'transitioning-in');
					slides[currentSlide].classList.add('active');
				}, 500);

				if (contentType === 'text') setCarouselHeight();
				updateIndicators();
			}
		};

		if (contentType === 'image') calculateAspectRatio();
		if (contentType === 'text') setCarouselHeight();

		const nextSlide = () => goToSlide(currentSlide + 1, 'right');
		const prevSlide = () => goToSlide(currentSlide - 1, 'left');

		const nextBtn = getObject('.carousel-control-next', carousel);
		const prevBtn = getObject('.carousel-control-prev', carousel);

		if (nextBtn) {
			nextBtn.addEventListener('click', e => {
				e.preventDefault();
				nextSlide();
				resetTimer();
			});
		}

		if (prevBtn) {
			prevBtn.addEventListener('click', e => {
				e.preventDefault();
				prevSlide();
				resetTimer();
			});
		}

		// Auto-play functionality
		const startAutoPlay = () => {
			if (!timer) {
				timer = direction === "right" ? setInterval(nextSlide, interval) : setInterval(prevSlide, interval);
			}
		};

		const stopAutoPlay = () => {
			if (timer) {
				clearInterval(timer);
				timer = null;
			}
		};

		const resetTimer = () => {
			stopAutoPlay();
			startAutoPlay();
		};

		// Hover events to pause and resume autoplay
		if (hoverPause) {
			carousel.addEventListener('mouseenter', stopAutoPlay);
			carousel.addEventListener('mouseleave', startAutoPlay);
		}

		// Respond to keyboard controls
		document.addEventListener('keydown', e => {
			if (e.keyCode === 37) { // Left arrow key
				getObjects(".carousel.slider").forEach(carousel => {
					prevSlide();
				});
				e.preventDefault();
			} else if (e.keyCode === 39) { // Right arrow key
				getObjects(".carousel.slider").forEach(carousel => {
					nextSlide();
				});
				e.preventDefault();
			}
		});

		// Touch Swipe functionality
		let touchStartX = 0,
			touchEndX = 0,
			touchStartY = 0,
			touchEndY = 0;
		const threshold = 30;

		const handleSwipeGesture = () => {
			(Math.abs(touchEndX - touchStartX) > Math.abs(touchEndY - touchStartY) && Math.abs(touchEndX - touchStartX) > threshold)
				? touchEndX > touchStartX
					? prevSlide()
					: nextSlide()
				: null;
			resetTimer();
		};

		carousel.addEventListener('touchstart', e => {
			({ screenX: touchStartX, screenY: touchStartY } = e.changedTouches[0]);
		}, { passive: true });

		carousel.addEventListener('touchmove', e => {
			({ screenX: touchEndX, screenY: touchEndY } = e.changedTouches[0]);
			(Math.abs(touchEndX - touchStartX) > Math.abs(touchEndY - touchStartY) && Math.abs(touchEndX - touchStartX) > threshold && e.cancelable)
				? e.preventDefault()
				: null;
		}, { passive: false });

		carousel.addEventListener('touchend', e => {
			({ screenX: touchEndX, screenY: touchEndY } = e.changedTouches[0]);
			handleSwipeGesture();
		}, { passive: true });

		// Link indicators to slides
		indicators.forEach((indicator, index) => {
			indicator.addEventListener('click', () => {
				goToSlide(index, 'next');
				resetTimer();
			});
		});

		// Blur background of unevenly sized images	(untested)
		getObjects('.slider-images.slider-blur .img-slider').forEach(imgSlider => {
			const imgHolder = document.createElement('div');
			imgHolder.className = 'img-holder';
			imgSlider.parentNode.insertBefore(imgHolder, imgSlider.nextSibling);
			const imgBg = document.createElement('div');
			imgBg.className = 'img-bg';
			imgBg.style.background = `url('${imgSlider.src}')`;
			imgHolder.appendChild(imgBg);
		});

		// Initialize carousel
		if (autoPlay || autoPlay === "true") { startAutoPlay(); }
		goToSlide(start, "next");
	});
})