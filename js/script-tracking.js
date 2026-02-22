document.addEventListener("DOMContentLoaded", function () {
	"use strict";

	// Raw Script: Tracking

	const pageID = document.body.getAttribute('id');

	const originalGtag = window.gtag;
	window.gtag = function (...args) {
		console.log("📊 gtag fired:", JSON.stringify(args));
		if (typeof originalGtag === 'function') originalGtag.apply(this, args);
	};

	window.addEventListener("load", () => {

		// Track phone & email clicks
		const trackClicks = getObjects('.track-clicks, .wpcf7-submit');
		trackClicks.forEach(click => {
			click.addEventListener('click', function () {
				const clickType = click.getAttribute('data-action') || 'email',
					pageUrl = click.getAttribute('data-url');

				if (typeof gtag === 'function') gtag("event", "unlock_achievement", { achievement_id: 'conversion-' + clickType });

				if (pageUrl) document.location = pageUrl;
			});
		});

		// Add tracking to coupons & testimonials
		getObjects('div.coupon-inner').forEach(coupon => {
			coupon.classList.add('tracking');
			coupon.setAttribute('data-track', 'coupon');
		});

		getObjects('.carousel.slider-testimonials').forEach(carousel => {
			carousel.classList.add('tracking');
			carousel.setAttribute('data-track', 'testimonials');
		});


		// Calculate LCP
		let lcpEntry = '',
			lcpTime = 0;

		const observer = new PerformanceObserver((list) => {
			const entries = list.getEntries();
			entries.forEach((entry) => {
				if (entry.startTime > lcpTime) {
					lcpTime = entry.startTime;
					lcpEntry = entry;
				}
			});
		});

		observer.observe({ type: 'largest-contentful-paint', buffered: true });
		window.addEventListener('beforeunload', () => observer.disconnect());

		// Calculate load time for page
		const endTime = Date.now();
		let loadTime = endTime - startTime,
			deviceType = "desktop";

		if (getDeviceW() <= mobileCutoff) deviceType = "mobile";
		if (getDeviceW() <= tabletCutoff) deviceType = "tablet";

		loadTime += (deviceType === "desktop") ? 300 : 600;

		// Wait 4 seconds to ensure visitor is engaged before counting event
		setTimeout(() => {
			let displayTime = loadTime > lcpTime ? loadTime : lcpTime;
			displayTime = (displayTime / 1000).toFixed(1);

			console.log("⏱ Load event:", { pageID, deviceType, displayTime, loadTime, lcpTime });

			if (typeof gtag === 'function') gtag("event", "join_group", { group_id: `${pageID}»${deviceType}«${displayTime}` });

		}, 4000);


		// Delay 0.3s to allow accurate contentH
		setTimeout(function () {
			// Track percentage of content viewed
			const thresholds = [
				{ value: 20, flag: 'view20' },
				{ value: 30, flag: 'view30' },
				{ value: 40, flag: 'view40' },
				{ value: 50, flag: 'view50' },
				{ value: 60, flag: 'view60' },
				{ value: 70, flag: 'view70' },
				{ value: 80, flag: 'view80' },
				{ value: 90, flag: 'view90' },
				{ value: 100, flag: 'view100' }
			];

			thresholds.forEach(threshold => {
				window[threshold.flag] = false;
			});

			let maxViewed = 0;

			if (typeof gtag === 'function') gtag("event", "unlock_achievement", { achievement_id: `${pageID}-init` });

			const colophon = getObject('#colophon'),
				colophonH = colophon ? colophon.getBoundingClientRect().height : 0,
				contentT = 0,
				contentH = document.documentElement.scrollHeight - colophonH,
				googleBadge = getObject('.wp-google-badge'),
				googleH = googleBadge ? googleBadge.getBoundingClientRect().height : 0,
				viewportH = window.innerHeight - googleH;

			window.scrollTracking = function () {
				const scrollPos = window.pageYOffset,
					viewedPos = scrollPos + viewportH - contentT,
					pctViewed = Math.round((viewedPos / contentH) * 100);

				maxViewed = pctViewed > maxViewed ? Math.max(0, Math.min(100, pctViewed)) : maxViewed;

				thresholds.forEach(threshold => {
					if (maxViewed >= threshold.value && !window[threshold.flag]) {
						if (typeof gtag === 'function') gtag("event", "unlock_achievement", { achievement_id: `${pageID}-${threshold.value}` });
						console.log("🎯 Threshold hit:", threshold.value + "%", "| gtag event would fire:", `${pageID}-${threshold.value}`);

						window[threshold.flag] = true;
					}
				});

			}

			window.addEventListener('scroll', window.scrollTracking, { passive: true });


			// Log what percentage of users see various trackable elements
			const trackedObj = [...getObjects('.tracking'), ...getObjects('[data-track]')];

			const observerOptions = {
				root: null,
				rootMargin: '0px',
				threshold: 0.25
			};

			const observerCallback = (entries, observer) => {
				entries.forEach(entry => {

					if (entry.isIntersecting) {
						const track = entry.target.getAttribute('data-track');

						if (!getCookie('track-' + track)) {
							if (typeof gtag === 'function') gtag("event", "unlock_achievement", { achievement_id: 'track-' + track });
							setCookie('track-' + track, true);
						}

						observer.unobserve(entry.target);
					}
				});
			};

			const observer = new IntersectionObserver(observerCallback, observerOptions);
			trackedObj.forEach(element => {
				observer.observe(element);
			});

		}, 300);


		//Test for real user or bot
		if (!getCookie('user-city')) {
			setTimeout(() => {
				fetch('https://ipapi.co/json/')
					.then(response => response.json())
					.then(data => {
						setCookie("user-city", data.city, '');
						setCookie("user-region", data.region_code, '');
						setCookie("user-country", data.country_name, '');
					})
					.catch(error => console.error('Error fetching location data:', error));
			}, 4000);
		}

	});
});