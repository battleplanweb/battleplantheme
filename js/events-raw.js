document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {
		
	// Convert elements to standard class names		
		// Single Event
		$('.tribe-events-pg-template').attr('id','primary').attr('role','main').attr('aria-label','main content').addClass('site-main').removeClass('tribe-events-pg-template');
		$('.tribe-common').removeClass('tribe-common');		
		
		// Event Navigation
		$('.single-tribe_events .tribe-events-nav-pagination').addClass('navigation').addClass('single');	
		$('.single-tribe_events .tribe-events-sub-nav').addClass('nav-links');				
		$('.single-tribe_events li.tribe-events-nav-previous a').addClass('nav-previous prev');
		$('.single-tribe_events li.tribe-events-nav-next a').addClass('nav-next next');
		
		var getPrevTitle = $('li.tribe-events-nav-previous').text();
		getPrevTitle = getPrevTitle.slice(0, getPrevTitle.length / 2).replace("«", "");
		$('.single-tribe_events li.tribe-events-nav-previous a').html(		
			'<div class="post-arrow"><i class="fa fas fa-chevron-left" aria-hidden="true"></i></div><div class="post-links"><div class="meta-nav" aria-hidden="true">Previous</div><div class="post-title">' + getPrevTitle + '</div></div>'		
		);
		
		var getNextTitle = $('li.tribe-events-nav-next').text();	
		getNextTitle = getNextTitle.slice(0, getNextTitle.length / 2).replace("»", "");
		$('.single-tribe_events li.tribe-events-nav-next a').html(		
			'<div class="post-links"><div class="meta-nav" aria-hidden="true">Next</div><div class="post-title">' + getNextTitle + '</div></div><div class="post-arrow"><i class="fa fas fa-chevron-right" aria-hidden="true"></i></div>'		
		);
	});
	
})(jQuery); });