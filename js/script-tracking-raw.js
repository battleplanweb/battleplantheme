document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS: 
----------------------------------------------------------------
# Tracking code
--------------------------------------------------------------*/
	var pageID = $('body').attr('id'); 			
	
	$(window).on( 'load', function() {
	
		$('div.coupon-inner').addClass('tracking').attr('data-track',"coupon");
		$('.carousel.slider-testimonials').addClass('tracking').attr('data-track',"testimonials");
		
	// Calculate load time for page		
		var endTime = Date.now(), loadTime = (endTime - startTime) / 1000, deviceTime = "desktop";
		if ( getDeviceW() <= getTabletCutoff() ) { deviceTime = "tablet"; }	
		if ( getDeviceW() <= getMobileCutoff() ) { deviceTime = "mobile"; }	

	// Delay 1 second before calling the following functions 
		setTimeout(function() {	
		// Log page load speed
			if ( deviceTime == "desktop" ) {
				loadTime = loadTime + 0.3;
			} else {
				loadTime = loadTime + 0.6;
			}
			
			loadTime = loadTime.toFixed(1);
			
			if ( typeof gtag === 'function' ) {
				gtag("event", "join_group", { group_id: pageID + "»" + deviceTime + "«" + loadTime });
			}
		}, 4000);		

	// Delay 0.3s to allow accurate contentH  
		setTimeout(function() {		
		// Track percentage of content viewed
			var scrollPct = 0, view100 = false, view80 = false, view60 = false, view40 = false, view20 = false, view0 = false, topH = getPosition($('#wrapper-content'), 'top'), contentH = $('#wrapper-content').height(), googleH = $('.wp-google-badge').height() || 0;

			$('#primary h1, #primary h2, #primary h3, #primary p, #primary li, #primary img, #primary div, #wrapper-content').waypoint(function() {
				if ( this.element.id == "wrapper-content" ) {
					scrollPct = 1;
				} else {
					scrollPct = ( (Math.round(getPosition(this.element, 'bottom'))) - Math.round(googleH) - Math.round(topH) ) / Math.round(contentH);
					if ( scrollPct > 1 ) { scrollPct = 1; }
				}
				
				if ( scrollPct < 0.2 && view0 == false && typeof gtag === 'function' ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-init' });
					view0 = true;					
					
					if ( !getCookie('track') && typeof gtag === 'function' ) {				
						gtag("event", "unlock_achievement", { achievement_id: 'track-init' });
						setCookie('track', true);
					}				
				}								
				if ( scrollPct >= 0.2 && view20 == false && typeof gtag === 'function' ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-20' });
					view20 = true;
				}				
				if ( scrollPct >= 0.4 && view40 == false && typeof gtag === 'function' ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-40' });
					view40 = true;
				}
				if ( scrollPct >= 0.6 && view60 == false && typeof gtag === 'function' ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-60' });
					view60 = true;
				}
				if ( scrollPct >= 0.8 && view80 == false && typeof gtag === 'function' ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-80' });
					view80 = true;
				}
				if ( scrollPct == 1 && view100 == false && typeof gtag === 'function' ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-100' });
					view100 = true;
				}					
				this.destroy();
			}, { offset: 'bottom-in-view' });	

		// Track how many people see sections/columns on site
			$('#wrapper-bottom > section > .flex > .col').waypoint(function() {
				var theCol = $(this.element), theSec = $(this.element).parent().parent(), colIndex = theSec.find('.flex > .col').index( theCol ) + 1, secIndex = $('#wrapper-bottom > section').index(theSec) + 1, completeView = secIndex+'.'+colIndex;
						
				if ( typeof gtag === 'function' ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-'+completeView });
				}
				
				this.destroy();
			}, { offset: 'bottom-in-view' });	
		
		// Log what percentage of users see various trackable elements
			$('.tracking').waypoint(function() {		
				var track = $(this.element).attr('data-track');
				
				if ( !getCookie(track) && typeof gtag === 'function' ) {				
					gtag("event", "unlock_achievement", { achievement_id: 'track-'+track });
					setCookie(track, true);
				}
				this.destroy();
			}, { offset: 'bottom-in-view' });	
		}, 300);	
		
		//Test for real user or bot		
		if ( !getCookie('user-city') ) {				
			setTimeout(function() {
				$.getJSON('https://ipapi.co/json/', function(data) {
					setCookie("user-city", data["city"], '');				
					setCookie("user-region", data["region_code"], '');				
					setCookie("user-country", data["country_name"], '');
				});
			}, 4000);	
		}
	});	
})(jQuery); });