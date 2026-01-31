document.addEventListener("DOMContentLoaded", function () {
	"use strict";

	// Raw Script: Carousel


	getObjects('.carousel').forEach(carousel => {

		const slides = getObjects('.carousel-item', carousel),
			slideInner = getObject('.carousel-inner', carousel),
			indicators = getObjects('.carousel-indicators li', carousel),
			controls = getObject('.controls', carousel),
			interval = parseInt(carousel.getAttribute('data-interval')) || 6000,
			autoPlay = carousel.getAttribute('data-auto') !== "false",
			hoverPause = carousel.getAttribute('data-pause') !== "false",
			start = carousel.getAttribute('data-random') === 'true'
				? Math.floor(Math.random() * slides.length)
				: 0;

		let timer = null,
			currentSlide = 0,
			direction = "right",
			maxH = 0,
			controlsHeight = 0;

		if (controls) {
			const styles = window.getComputedStyle(controls);
			const marginTop = parseFloat(styles.marginTop) || 0;
			const marginBottom = parseFloat(styles.marginBottom) || 0;

			controlsHeight = controls.offsetHeight + marginTop + marginBottom;
		}

		/* --------------------------------------------------
		   Height management via ResizeObserver
		-------------------------------------------------- */

		if (slideInner && window.ResizeObserver) {
			const resizeObserver = new ResizeObserver(entries => {
				let tallest = maxH;
				let h = 0;

				entries.forEach(entry => {
					h = entry.contentRect.height;
					if (h > tallest) tallest = h;
				});

				let active = getObject('.active', carousel);
				let activeH = active.offsetHeight;
				slideInner.style.height = `${activeH}px`;

				if (tallest !== maxH && tallest > 0) {
					maxH = tallest;
					carousel.style.height = `${maxH + controlsHeight}px`;
				}

			});

			slides.forEach(slide => resizeObserver.observe(slide));
		}

		/* --------------------------------------------------
		   Indicators
		-------------------------------------------------- */

		const updateIndicators = () => {
			indicators.forEach((indicator, index) => {
				indicator.classList.toggle('active', index === currentSlide);
			});
		};

		/* --------------------------------------------------
		   Slide transition logic
		-------------------------------------------------- */

		const goToSlide = (n, dir) => {
			direction = dir;

			const formerSlide = slides[currentSlide];
			if (!formerSlide) return;

			formerSlide.classList.add(
				'transitioning',
				'transitioning-' + direction,
				'transitioning-out'
			);

			setTimeout(() => {
				formerSlide.classList.remove(
					'active',
					'transitioning',
					'transitioning-' + direction,
					'transitioning-out'
				);
			}, 500);

			currentSlide = (n + slides.length) % slides.length;

			const next = slides[currentSlide];
			next.classList.add(
				'next-slide',
				'transitioning',
				'transitioning-' + direction,
				'transitioning-in'
			);

			setTimeout(() => {
				next.classList.remove(
					'next-slide',
					'transitioning',
					'transitioning-' + direction,
					'transitioning-in'
				);
				next.classList.add('active');
			}, 500);

			updateIndicators();
		};

		const nextSlide = () => goToSlide(currentSlide + 1, 'right');
		const prevSlide = () => goToSlide(currentSlide - 1, 'left');

		/* --------------------------------------------------
		   Controls
		-------------------------------------------------- */

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

		/* --------------------------------------------------
		   Autoplay
		-------------------------------------------------- */

		const startAutoPlay = () => {
			if (!timer && autoPlay) {
				timer = setInterval(
					direction === "right" ? nextSlide : prevSlide,
					interval
				);
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

		if (hoverPause) {
			carousel.addEventListener('mouseenter', stopAutoPlay);
			carousel.addEventListener('mouseleave', startAutoPlay);
		}

		/* --------------------------------------------------
		   Keyboard navigation
		-------------------------------------------------- */

		document.addEventListener('keydown', e => {
			if (e.keyCode === 37) {
				prevSlide();
				e.preventDefault();
			}
			if (e.keyCode === 39) {
				nextSlide();
				e.preventDefault();
			}
		});

		/* --------------------------------------------------
		   Touch swipe
		-------------------------------------------------- */

		let touchStartX = 0,
			touchEndX = 0,
			touchStartY = 0,
			touchEndY = 0;

		const threshold = 30;

		const handleSwipe = () => {
			if (
				Math.abs(touchEndX - touchStartX) >
				Math.abs(touchEndY - touchStartY) &&
				Math.abs(touchEndX - touchStartX) > threshold
			) {
				touchEndX > touchStartX ? prevSlide() : nextSlide();
				resetTimer();
			}
		};

		carousel.addEventListener('touchstart', e => {
			touchStartX = e.changedTouches[0].screenX;
			touchStartY = e.changedTouches[0].screenY;
		}, { passive: true });

		carousel.addEventListener('touchmove', e => {
			touchEndX = e.changedTouches[0].screenX;
			touchEndY = e.changedTouches[0].screenY;

			if (
				Math.abs(touchEndX - touchStartX) >
				Math.abs(touchEndY - touchStartY) &&
				Math.abs(touchEndX - touchStartX) > threshold &&
				e.cancelable
			) {
				e.preventDefault();
			}
		}, { passive: false });

		carousel.addEventListener('touchend', handleSwipe, { passive: true });

		/* --------------------------------------------------
		   Indicator click binding
		-------------------------------------------------- */

		indicators.forEach((indicator, index) => {
			indicator.addEventListener('click', () => {
				goToSlide(index, 'right');
				resetTimer();
			});
		});

		/* --------------------------------------------------
		   Init
		-------------------------------------------------- */

		if (!slides.length) return;

		slides.forEach(slide => slide.classList.remove('active'));

		currentSlide = start >= 0 && start < slides.length ? start : 0;

		slides[currentSlide].classList.add('active');
		updateIndicators();
		startAutoPlay();

	});
});