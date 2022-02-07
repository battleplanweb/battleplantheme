document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

// Accordion section - control opening & closing of expandable text boxes
	window.buildAccordion = function (topSpacer, cssDelay, transSpeed, closeDelay, openDelay, clickActive) {
		if (buildAccordion.done) { return; }
		transSpeed = transSpeed || 500;
		closeDelay = closeDelay || (transSpeed / 3);
		openDelay = openDelay || 0;
		cssDelay = cssDelay || closeDelay + openDelay;
		topSpacer = topSpacer || 0.1;
		clickActive = clickActive || 'close';
		var mobileMenuBarH = $("#mobile-menu-bar").outerHeight(), accPos = [];

		if ( topSpacer < 1 ) { 
			topSpacer = getDeviceH() * topSpacer; 
		}		
		if ( getDeviceW() < getMobileCutoff() ) { 
			topSpacer = topSpacer + mobileMenuBarH;
		}		

		$('.block-accordion').attr( 'aria-expanded', false );
		$('.block-accordion').first().addClass('accordion-first');
		$('.block-accordion').last().addClass('accordion-last');
		if ( $('.block-accordion').parents('.col-archive').length) {
			$('.block-accordion').parents('.col-archive').addClass('archive-accordion');
			$('.archive-accordion').each(function() { accPos.push($(this).offset().top); });			
		} else {
			$('.block-accordion').each(function() { accPos.push($(this).offset().top); });			
		}
		
		$( '.block-accordion.start-active' ).attr( 'aria-expanded', true ).addClass('active');
		$( '.block-accordion.start-active .accordion-excerpt' ).animate({ height: "toggle", opacity: "toggle" }, 0);					
		$( '.block-accordion.start-active .accordion-content' ).animate({ height: "toggle", opacity: "toggle" }, 0);

		keyPress('.block-accordion h2.accordion-button');
 
		$(".block-accordion .accordion-button").click(function(e) {		
			e.preventDefault();
			var thisAcc = $(this).closest('.block-accordion'), thisBtn = thisAcc.find('.accordion-button'), thisPos = accPos[thisAcc.index('.block-accordion')], topPos = accPos[0], moveTo = 0, thisClose=thisBtn.attr('data-collapse'), activeAcc = $('.block-accordion.active'), activeBtn = activeAcc.find('.accordion-button'), activeOpen = activeBtn.attr('data-text');	
			
			if ( !thisAcc.hasClass("active") ) {
			
				setTimeout( function () {
					activeBtn.text(activeOpen).fadeIn();
					activeAcc.find('.accordion-excerpt').animate({ height: "toggle", opacity: "toggle" }, transSpeed);					
					activeAcc.find('.accordion-content').animate({ height: "toggle", opacity: "toggle" }, transSpeed);
				}, closeDelay);
				
				setTimeout( function () {
					thisAcc.find('.accordion-excerpt').animate({ height: "toggle", opacity: "toggle" }, transSpeed);						
					thisAcc.find('.accordion-content').animate({ height: "toggle", opacity: "toggle" }, transSpeed);	
					
					//if ( thisClose == undefined ) {  removed for Greater Fort Myers Dog Club - 2022 Dog Show accordion
						if ( (thisPos - topPos) > (getDeviceH() * 0.25) ) {
							animateScroll(thisPos, topSpacer, transSpeed); 
						} else {
							moveTo = ((thisPos - topPos) / 2) + topPos;						
							animateScroll(moveTo, topSpacer, transSpeed); 							
						}	
					//}
					
					if ( thisClose == "hide" ) { 
						thisBtn.fadeOut(); 
					} else if ( thisClose != "false" ) {
						thisBtn.text(thisClose); 
					} 
					
				}, openDelay);
				
				setTimeout( function() {						
					activeAcc.removeClass('active').attr( 'aria-expanded', false ); 
					thisAcc.addClass('active').attr( 'aria-expanded', true );
				}, cssDelay);
				
			} else if ( clickActive == 'close' ) {
				setTimeout( function () {
					activeBtn.text(activeOpen).fadeIn();
					activeAcc.find('.accordion-excerpt').animate({ height: "toggle", opacity: "toggle" }, transSpeed);					
					activeAcc.find('.accordion-content').animate({ height: "toggle", opacity: "toggle" }, transSpeed);
				}, closeDelay);	
				setTimeout( function() {						
					activeAcc.removeClass('active').attr( 'aria-expanded', false );
				}, cssDelay);	
			}  
		});
		buildAccordion.done = true;
	};
	
	setTimeout(function() { buildAccordion(); }, 1000);
})(jQuery); });