document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Tracking code
--------------------------------------------------------------*/
	var ajaxURL = 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php'; 		
	
	$(window).on( 'load', function() {
	
	// Calculate load time for page		
		var endTime = Date.now(), loadTime = (endTime - startTime) / 1000, deviceTime = "desktop";
		if ( getDeviceW() <= getMobileCutoff() ) { deviceTime = "mobile"; }	

	// Wait 1 seconds before calling the following functions 
		setTimeout(function() {			
		// Check chron jobs	
			$.post({
				url : ajaxURL,
				data : { action: "run_chron_jobs", admin: "false" },
				success: function( response ) { console.log(response);  }
			});

			// Log page load speed
			if ( deviceTime == "desktop" ) {
				loadTime = loadTime + 0.3;
			} else {
				loadTime = loadTime + 0.6;
			}
			$.post({
				url : ajaxURL,
				data : { action: "log_page_load_speed", id: $('body').attr('id'), loadTime: loadTime, deviceTime: deviceTime },
				success: function( response ) { console.log(response); } 
			});	

		// Count random post widget, testimonial & images - teases & views
			$('.carousel img.img-slider, #primary .testimonials-name, .widget:not(.hide-widget) .testimonials-name, #wrapper-bottom .testimonials-name, #primary img.random-img, .widget:not(.hide-widget) img.random-img, #wrapper-bottom img.random-img, #primary h3, .widget:not(.hide-widget) h3, #wrapper-bottom h3').waypoint(function() {		
				var theID = $(this.element).attr('data-id');
				var countTease = $(this.element).attr('data-count-tease');				
				var countView = $(this.element).attr('data-count-view');
				$.post({
					url : ajaxURL,
					data : { action: "count_teaser_views", id: theID },
					success: function( response ) { console.log(response); } 
				});		
				this.destroy();
			}, { offset: 'bottom-in-view' });	 	

		}, 1000);
	});
	
})(jQuery); });