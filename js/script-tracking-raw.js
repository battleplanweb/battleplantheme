document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Tracking code
--------------------------------------------------------------*/
	var ajaxURL = 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php', uniqueID = getCookie('unique-id'), uniquePage = uniqueID + getCookie('pages-viewed'), pageID = $('body').attr('id'); 		
	
	$(window).on( 'load', function() {
	
		$('.widget.widget-financing').addClass('tracking').attr('data-track',"financing");
		$('div.coupon-inner').addClass('tracking').attr('data-track',"coupon");
		$('.carousel.slider-testimonials').addClass('tracking').attr('data-track',"testimonials");
		
	// Calculate load time for page		
		var endTime = Date.now(), loadTime = (endTime - startTime) / 1000, deviceTime = "desktop";
		if ( getDeviceW() <= getMobileCutoff() ) { deviceTime = "mobile"; }	

	// Delay 1 second before calling the following functions 
		setTimeout(function() {	
		// Check chron jobs	
			if ( !$('body').hasClass('wp-admin') ) {
				$.post({
					url : ajaxURL,
					data : { action: "run_chron_jobs", admin: "false" },
					success: function( response ) { ajax_response(response.dashboard);	}
				});
			}

		// Log page load speed
			if ( deviceTime == "desktop" ) {
				loadTime = loadTime + 0.3;
			} else {
				loadTime = loadTime + 0.6;
			}
			$.post({
				url : ajaxURL,
				data : { action: "log_page_load_speed", id: pageID, loadTime: loadTime, deviceTime: deviceTime },
				success: function( response ) { ajax_response(response.dashboard);	}
			});	
			
		// Initialize new user for tracking elements			
			$.post({
				url : ajaxURL,
				data : { action: "track_interaction", key: 'content-tracking', track: 'visitor', uniqueID: uniqueID },
				success: function( response ) { ajax_response(response.dashboard);	}
			});	
		}, 1000);		

	// Delay 0.3s to allow accurate contentH  
		setTimeout(function() {		
		// Track percentage of content viewed
			var scrollPct = 0, lastPct = 0, topH = getPosition($('#wrapper-content'), 'top'), contentH = $('#wrapper-content').height(), googleH = $('.wp-google-badge').height() || 0;

			$('#primary p, #primary img, #primary div, #wrapper-content').waypoint(function() {
				if ( this.element.id == "wrapper-content" ) {
					scrollPct = 1;
				} else {
					scrollPct = ( (Math.round(getPosition(this.element, 'bottom'))) - Math.round(googleH) - Math.round(topH) ) / Math.round(contentH);
					if ( scrollPct > 1 ) { scrollPct = 1; }
				}
				if ( scrollPct > lastPct ) {
					lastPct = scrollPct;
					$.post({
						url : ajaxURL,
						data : { action: 'track_interaction', key: 'content-scroll-pct', scroll: scrollPct, uniqueID: uniquePage },
						success: function( response ) { ajax_response(response.dashboard);	}
					});	
				}
				this.destroy();
			}, { offset: 'bottom-in-view' });	

		// Track how many people see sections/columns on site
			$('#wrapper-bottom > section > .flex > .col').waypoint(function() {
				var theCol = $(this.element), theSec = $(this.element).parent().parent(), colIndex = theSec.find('.flex > .col').index( theCol ) + 1, secIndex = $('#wrapper-bottom > section').index(theSec) + 1, completeView = secIndex+'.'+colIndex;
				$.post({
					url : ajaxURL,
					data : { action: 'track_interaction', key: 'content-column-views', viewed: completeView, page: pageID,  uniqueID: uniquePage },
					success: function( response ) { ajax_response(response.dashboard);	}
				});	
				this.destroy();
			}, { offset: 'bottom-in-view' });	
		
	// Log view time of testimonials, random posts & random images
			$('#primary img.random-img, .widget:not(.hide-widget) img.random-img, #wrapper-bottom img.random-img').waypoint(function() {	
				var theID = $(this.element).attr('data-id');
				$.post({
					url : ajaxURL,
					data : { action: "count_view", id: theID },
					success: function( response ) { ajax_response(response.dashboard);	}
				});				
				this.destroy();
			}, { offset: 'bottom-in-view' });
			
			$('.carousel.slider-testimonials').waypoint(function() {		
				$(this.element).find('.testimonials-credential.testimonials-name').each(function() {
					var theID = $(this).attr('data-id');
					$.post({
						url : ajaxURL,
						data : { action: "count_view", id: theID },
						success: function( response ) { ajax_response(response.dashboard);	}
					});				
				});				
				this.destroy();
			}, { offset: 'bottom-in-view' });

		// Log what percentage of users see various trackable elements
			$('.tracking').waypoint(function() {		
				var track = $(this.element).attr('data-track');
				$.post({
					url : ajaxURL,
					data : { action: "track_interaction", key: 'content-tracking', track: track, uniqueID: uniqueID },
					success: function( response ) { ajax_response(response.dashboard);	}
				});				
				this.destroy();
			}, { offset: 'bottom-in-view' });	
		}, 300);	
	});
	
})(jQuery); });