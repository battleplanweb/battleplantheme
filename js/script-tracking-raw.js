document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS: 
----------------------------------------------------------------
# Tracking code
--------------------------------------------------------------*/
	var pageID = $('body').attr('id'); 			
	
	$(window).on( 'load', function() {
	
		$('.widget.widget-financing').addClass('tracking').attr('data-track',"financing");
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
			
			gtag("event", "join_group", { group_id: pageID + "»" + deviceTime + "«" + loadTime });
		}, 1000);		

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
				
				if ( scrollPct < 0.2 && view0 == false ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-init' });
					view0 = true;					
					
					if ( !getCookie('track') ) {				
						gtag("event", "unlock_achievement", { achievement_id: 'track-init' });
						setCookie('track', true);
					}				
				}								
				if ( scrollPct >= 0.2 && view20 == false ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-20' });
					view20 = true;
				}				
				if ( scrollPct >= 0.4 && view40 == false ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-40' });
					view40 = true;
				}
				if ( scrollPct >= 0.6 && view60 == false ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-60' });
					view60 = true;
				}
				if ( scrollPct >= 0.8 && view80 == false ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-80' });
					view80 = true;
				}
				if ( scrollPct == 1 && view100 == false ) {
					gtag("event", "unlock_achievement", { achievement_id: pageID+'-100' });
					view100 = true;
				}					
				this.destroy();
			}, { offset: 'bottom-in-view' });	

		// Track how many people see sections/columns on site
			$('#wrapper-bottom > section > .flex > .col').waypoint(function() {
				var theCol = $(this.element), theSec = $(this.element).parent().parent(), colIndex = theSec.find('.flex > .col').index( theCol ) + 1, secIndex = $('#wrapper-bottom > section').index(theSec) + 1, completeView = secIndex+'.'+colIndex;
								
				gtag("event", "unlock_achievement", { achievement_id: pageID+'-'+completeView });
				
				this.destroy();
			}, { offset: 'bottom-in-view' });	
		
		// Log what percentage of users see various trackable elements
			$('.tracking').waypoint(function() {		
				var track = $(this.element).attr('data-track');
				
				if ( !getCookie(track) ) {				
					gtag("event", "unlock_achievement", { achievement_id: 'track-'+track });
					setCookie(track, true);
				}
				this.destroy();
			}, { offset: 'bottom-in-view' });	
		}, 300);	
		
		//Test for real user or bot		
		setTimeout(function() {
			// Get IP data
			$.getJSON('https://ipapi.co/json/', function(data) {
			
				function deg2rad(deg) {
					return deg * (Math.PI/180) 
				}					
				
				var siteLat = site_options.lat, siteLong = site_options.long, userLat = data["latitude"], userLong = data["longitude"], R = 3958.8, dLat = deg2rad(siteLat-userLat), dLon = deg2rad(siteLong-userLong), a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(deg2rad(userLat)) * Math.cos(deg2rad(siteLat)) * Math.sin(dLon/2) * Math.sin(dLon/2), c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)), distance = R * c, location; 				
			
				if ( data["country_name"] == "United States" ) {
					location = data["city"] + ", " + data["region_code"];
				} else {
					location = data["city"] + ", " + data["country_name"];
				}
				
				setCookie("user-loc", location, '');
								
				if ( distance < 250 ) { 			
					$.post({
						url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
						data : { action: "check_user", distance: distance, location: location },
						success: function( response ) { console.log(response); } 
					});					
				}
			});
		}, 4000);	

	});	
})(jQuery); });