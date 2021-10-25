document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Tracking code
--------------------------------------------------------------*/

	var siteLat = site_options.lat, siteLong = site_options.long, siteRadius = site_options.radius, timezone, userValid = "false", userLoc, userRefer, pageViews = $("body").attr("data-pageviews"), uniqueID=$("body").attr("data-unique-id"), ajaxURL = 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php'; 		
		
	if ( siteRadius == "default" ) { siteRadius = 100; }	
	if ( siteLong > 0 ) { siteLong = -siteLong; }
	
	$(window).on( 'load', function() {
	
	// Calculate load time for page		
		var endTime = Date.now(), loadTime = ((endTime - startTime) / 1000).toFixed(1), deviceTime = "desktop";
		if ( getDeviceW() <= getMobileCutoff() ) { deviceTime = "mobile"; }	
				
	// Wait 1 second before calling the following functions 
		setTimeout(function() {	
		// Get IP data
			$.getJSON('https://ipapi.co/json/', function(data) {
				userLoc = data["city"] + ", " + data["region_code"];
				timezone = data["timezone"];

				if ( !siteLat ) { userValid = "undetermined"; }
				
				function deg2rad(deg) { return deg * (Math.PI/180) }				
				var userLat = data["latitude"], userLong = data["longitude"], R = 3958.8, dLat = deg2rad(siteLat-userLat), dLon = deg2rad(siteLong-userLong), a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(deg2rad(userLat)) * Math.cos(deg2rad(siteLat)) * Math.sin(dLon/2) * Math.sin(dLon/2), c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)), distance = R * c; 
				
				if ( distance < siteRadius ) { userValid = "true"; }
			});
			userRefer = encodeURI(document.referrer); 
		}, 1000);

	// Wait 2 seconds before calling the following functions 
		setTimeout(function() {	
		// Count page view 
			var postID = $('body').attr('id');
			$.post({
				url : ajaxURL,
				data : { action: "count_post_views", id: postID, timezone: timezone, userValid: userValid, userLoc: userLoc, pagesViewed: pageViews, uniqueID: uniqueID },
				success: function( response ) { console.log(response); } 
			});	
			
		// Count site view 
			$.post({
				url : ajaxURL,
				data : { action: "count_site_views", timezone: timezone, userValid: userValid, userLoc: userLoc, userRefer: userRefer },
				success: function( response ) { console.log(response); } 
			});	

			// Log page load speed
			if ( loadTime > 0.1 && loadTime < 10.0 ) { 				
				$.post({
					url : ajaxURL,
					data : { action: "log_page_load_speed", id: postID, timezone: timezone, userValid: userValid, loadTime: loadTime, deviceTime: deviceTime, userLoc: userLoc },
					success: function( response ) { console.log(response); } 
				});	
			}

		// Count random post widget, testimonial & images - teases & views
			$('.carousel img.img-slider, #primary .testimonials-name, .widget:not(.hide-widget) .testimonials-name, #wrapper-bottom .testimonials-name, #primary img.random-img, .widget:not(.hide-widget) img.random-img, #wrapper-bottom img.random-img, #primary h3, .widget:not(.hide-widget) h3, #wrapper-bottom h3').waypoint(function() {		
				var theID = $(this.element).attr('data-id');
				var countTease = $(this.element).attr('data-count-tease');				
				var countView = $(this.element).attr('data-count-view');
				if ( countTease == "true" ) {
					$.post({
						url : ajaxURL,
						data : { action: "count_teaser_views", id: theID, timezone: timezone, userValid: userValid, userLoc: userLoc },
						success: function( response ) { console.log(response); } 
					});		
				}
				if ( countView == "true" ) {
					$.post({
						url : ajaxURL,
						data : { action: "count_post_views", id: theID, timezone: timezone, userValid: userValid, userLoc: userLoc },
						success: function( response ) { console.log(response); } 
					});		
				}
				this.destroy();
			}, { offset: 'bottom-in-view' });	 	

		}, 2000);
	});
	
})(jQuery); });