document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {
		
		// Handle mutliple locations		
		$('.logo').click(function() {			
			setCookie('cdj-location', '', 30);			
		})
		
		var locations = Object.entries(locArray), cdj_location = getCookie('cdj-location');
		
		for (var i = 0; i < locations.length; i++) { 
			$( '.show-'+locations[i][1]['slug'] ).addClass('loc');
			$( 'a.button.'+locations[i][1]['slug'] ).addClass('loc-btn');
		}	
		
		window.displayLocation = function (loc) {
			$('a.button').removeClass('active');
			$('a.button.'+loc).addClass('active');
			
			var classList = $('body').attr('class').split(' ');
			$.each(classList, function(index, className) {
				if (className.indexOf('location-') === 0) {
			    		$('body').removeClass(className);
			 	}
			 	if(className.indexOf('slug-menu-') === 0) {
					$('body').addClass('menu-page');
			  	}
			});			
			
			$('body').addClass('location-'+loc);			 
			$('.location-unknown, .loc').fadeOut(100);
			$('.show-'+loc).fadeIn(500);			
			
			setCookie('cdj-location', loc, 30);		
		}
		
		if ( cdj_location ) { displayLocation(cdj_location); }		
		
		$('a.button').click(function() { 	
			for (var i = 0; i < locations.length; i++) {
				if ( $(this).hasClass(locations[i][1]['slug']) ) {					
					displayLocation(locations[i][1]['slug']);
					break;
				}
			}	
		});	
			
		
		// Create galleries to display pics associated with (food) menu items
		var menuExists = false;
		$('.menu-page #main-content h4, .menu-page .list-item').each(function() {
			var override = $(this).attr('data-title'), title = $(this).html(), index = title.indexOf(" <"), gallery1 = $(this).prevAll('h2[data-category]').first().attr('data-category'), gallery2 = $(this).closest('.small-list').prevAll('h2[data-category]').first().attr('data-category'), gallery = gallery1;
			
			if ( gallery == undefined ) { gallery = gallery2 }
			if ( override ) {
				title = override;
			} else if (index !== -1) {
			  	title = title.slice(0, index);
			}		
			var link = imgLinks[title], desc = imgDesc[title];
			
			if ( link ) {
				menuExists = true;				
				link = '<a href="'+link+'" class="glightbox" data-gallery="' + gallery + '" data-glightbox="title: ' + title + '; description: ' + desc + '; descPosition: left; type: image; effect: fade; zoomable: true; draggable: true;"><span class="icon search-plus menu-img-btn" aria-hidden="true"></span></a>';				
				$(this).prepend(link);
			}
		});
		
		if ( menuExists == true ) { const lightbox = GLightbox({ touchNavigation: true, loop: true, autoplayVideos: true, }); }			
	});
	
})(jQuery); });