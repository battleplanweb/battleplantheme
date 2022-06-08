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
		}, 1000);		

	// Log tease time of random post widget, testimonial & images
		$('.carousel img.img-slider, #primary .testimonials-name, .widget:not(.hide-widget) .testimonials-name, #wrapper-bottom .testimonials-name, #primary img.random-img, .widget:not(.hide-widget) img.random-img, #wrapper-bottom img.random-img').waypoint(function() {		
			var theID = $(this.element).attr('data-id');
			var countTease = $(this.element).attr('data-count-tease');				
			$.post({
				url : ajaxURL,
				data : { action: "count_teaser", id: theID },
				success: function( response ) { console.log(response); } 
			});		
			this.destroy();
		}, { offset: 'bottom-in-view' });		
		
	// Track user scrolling and important elements viewed
		var scrollPct = 0, lastPct = 0, screenH = screen.height, pageH = $('body').height(), uniqueID = getCookie('unique-id')+getCookie('pages-viewed');
		setInterval(function() {
			scrollPct = ($(window).scrollTop() + screenH) / pageH;
			if ( scrollPct > lastPct ) {
				if ( scrollPct > 1 ) { scrollPct = 1; }
				$.post({
					url : ajaxURL,
					data : { action: 'track_interaction', key: 'page-scroll-pct', value: scrollPct, uniqueID: uniqueID },
					success: function( response ) { console.log(response); } 
				});	
				lastPct = scrollPct;			
			}	
		}, 1000);	
		
		
		/*
		$('p, img, #wrapper-bottom section').waypoint(function() {
			logScroll();
			this.destroy();
		}, { offset: '40%' });	
		$('#wrapper-bottom section, #colophon').waypoint(function() {
			logScroll();
			this.destroy();
		}, { offset: 'bottom-in-view' });	
		function logScroll() {
			setTimeout(function() { pause = "false"; }, 500);			
			if ( pause == "true" ) {
				scrollPct = ($(window).scrollTop() + screenH) / pageH;
				if ( scrollPct > 1 ) { scrollPct = 1; }	
			} else {			
				$.post({
					url : ajaxURL,
					data : { action: 'track_interaction', key: 'page-scroll-pct', value: scrollPct, uniqueID: uniqueID },
					success: function( response ) { console.log(response); } 
				});		
				pause = "true";
			}	
		
		};*/
	});
	
})(jQuery); });