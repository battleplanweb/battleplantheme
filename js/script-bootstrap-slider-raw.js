document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

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
	
// Avoid long delay on first slide transition
	$(".carousel.slider").each(function() {
		if ( $(this).attr('data-auto') == "true" ) {
			$(this).carousel('cycle');
		}
	});	
	
// Set up "blurred" background
	wrapDiv ( '.slider-images.slider-blur .img-slider', '<div class="img-holder"></div>', 'outside');
	addDiv ('.slider-images.slider-blur .img-holder', '<div class="img-bg"></div>', 'after'); 

	$('.slider-images.slider-blur .img-slider').each(function() {
		$(this).parent().find('.img-bg').css({ "background":"url('"+$(this).attr('src')+"')" });			
	});
			
})(jQuery); });