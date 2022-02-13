document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

// Add star icons to reviews and ratings
	$('.testimonials-rating').each(function() {
		var getRating = $(this).html(), star = ['far', 'far', 'far', 'far', 'far'], replaceRating, i;		
		for (i=0; i < getRating; i++) { 
			star[i] = 'fas'; 
		}		
		replaceRating = '<span class="rating rating-'+getRating+'-star" aria-hidden="true"><span class="sr-only">Rated '+getRating+' Stars</span>';
		for (i=0; i < 5; i++) { 
			replaceRating += '<i class="fa '+star[i]+' fa-star"></i>';
		}
		replaceRating += '</span>';		
		$(this).html( replaceRating );
	});

// Ensure all slides in a testimonial slider are even height
	$(".carousel.slider-testimonials").each(function() {
		var thisCarousel = $(this), maxH = 0;		
		setTimeout(function(){
			thisCarousel.find('.carousel-item').each( function() {
				var thisItem = $(this), itemH = 0;
				thisItem.addClass('calculating');
				itemH = thisItem.outerHeight(true);
				thisItem.removeClass('calculating');
				if ( itemH > maxH ) { 
					maxH = Math.ceil(itemH+20); 
				}		
			});
			thisCarousel.find(".carousel-inner").css("height", maxH+"px");	
		}, 500);
	});	
			
})(jQuery); });