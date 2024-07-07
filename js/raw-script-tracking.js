document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Tracking
														   
	const pageID = document.body.getAttribute('id');		
	
	window.addEventListener("load", () => {
		
// Track phone & email clicks
		const trackClicks = getObjects('.track-clicks, .wpcf7-submit');    
		trackClicks.forEach(click => {
			click.addEventListener('click', function() {
				const clickType = click.getAttribute('data-action') || 'email',
					  pageUrl = click.getAttribute('data-url');            

				if (typeof gtag === 'function') gtag("event", "unlock_achievement", { achievement_id: 'conversion-'+clickType });

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


	// Calculate load time for page		
		const endTime = Date.now();
		let loadTime = (endTime - startTime) / 1000,
			deviceType = "desktop";

		if ( getDeviceW() <= tabletCutoff ) deviceType = "tablet";
		if ( getDeviceW() <= mobileCutoff ) deviceType = "mobile";
		

	// Wait 4 seconds to ensure visitor is engaged before counting event
		setTimeout(() => {
			loadTime += (deviceType === "desktop") ? 0.3 : 0.6;
			loadTime = loadTime.toFixed(1);

			if (typeof gtag === 'function') gtag("event", "join_group", { group_id: `${pageID}»${deviceType}«${loadTime}` });

		}, 4000);
		

	// Delay 0.3s to allow accurate contentH  
		setTimeout(function() {		
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

			const wrapperContent = getObject('#wrapper-content').getBoundingClientRect(),
				  contentT = wrapperContent.top,
				  contentH = wrapperContent.height,
				  contentB = contentT + contentH,
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
						
						window[threshold.flag] = true;
					}
				});

			}
			

		// Log what percentage of users see various trackable elements
			const trackedObj = [...getObjects('.tracking'), ...getObjects('[data-track]')];

			const observerOptions = {
				root: null, 
				rootMargin: '0px',
				threshold: 1
			};

			const observerCallback = (entries, observer) => {
				entries.forEach(entry => {
					
					if (entry.isIntersecting) {
						const track = entry.target.getAttribute('data-track');

						if ( !getCookie('track-'+track) ) {
							if (typeof gtag === 'function') gtag("event", "unlock_achievement", { achievement_id: 'track-'+track });
							setCookie('track-'+track, true);
						}

						observer.unobserve(entry.target);
					}
				});
			};

			const observer = new IntersectionObserver(observerCallback, observerOptions);
			trackedObj.forEach(element => observer.observe(element));
			
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