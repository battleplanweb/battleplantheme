document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

// Ensure all slides in a testimonial slider are even height
	$(".carousel.slider-testimonials").each(function() {
		var thisCarousel = $(this), maxH = 260;		
		setTimeout(function(){
			thisCarousel.find('.carousel-item').each( function() {
				var thisItem = $(this), itemH = 0;
				thisItem.addClass('calculating');
				itemH = thisItem.outerHeight(true);
				thisItem.removeClass('calculating');
				if ( itemH > maxH ) { 
					maxH = Math.ceil(itemH * 1.0); 
				}		
			});
			thisCarousel.find(".carousel-inner").css("height", maxH+"px");	 
		}, 1000);
	});		
	
// Ensure buttons are all the same height
	$(".carousel.slider .block.button-all").each(function() {
		var theBtn = $(this).find('a.button'), btnH = theBtn.outerHeight() - (theBtn.outerHeight() - theBtn.innerHeight());
		
		// Check if the previous element is .button-prev and set its height
		if ($(this).prev().hasClass("button-prev")) {
			$(this).prev().find('span.carousel-control-prev-icon').innerHeight(btnH);
		}

		// Check if the next element is .button-next and set its height
		if ($(this).next().hasClass("button-next")) {
			$(this).next().find('span.carousel-control-next-icon').innerHeight(btnH);
		}
		
	});
	
// Avoid long delay on first slide transition
	$(".carousel.slider").each(function() {
		if ( $(this).attr('data-auto') == "true" ) {
			$(this).carousel('cycle');
		}
	});	
	
// Make slides react to arrow keypresses	
	$(document).keydown(function(e) {
    		if (e.keyCode === 37) {
			$(".carousel.slider").carousel('prev');
       		return false;
		}
    		if (e.keyCode === 39) {
       	$(".carousel.slider").carousel('next');
       	return false;
    		}
	});
	
// Set up "blurred" background
	wrapDiv ( '.slider-images.slider-blur .img-slider', '<div class="img-holder"></div>', 'outside');
	addDiv ('.slider-images.slider-blur .img-holder', '<div class="img-bg"></div>', 'after'); 

	$('.slider-images.slider-blur .img-slider').each(function() {
		$(this).parent().find('.img-bg').css({ "background":"url('"+$(this).attr('src')+"')" });			
	});
			
})(jQuery); });