document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {
		
	// Convert elements to standard class names
		
		$('#tribe-events-pg-template').attr('id','primary').addClass('site-main');
		
		wrapDiv ('.post-type-archive-tribe_events.slug-events .tribe-events-loop', '<section class="section section-inline archive-content archive-events"></section>', 'outside');
		
		$('.post-type-archive-tribe_events.slug-events .tribe-events-loop').addClass('flex').addClass('grid-1');
		//$('.post-type-archive-tribe_events.slug-events .tribe-events-loop').addClass('flex').addClass('grid-1-1').addClass('valign-stretch');
		
		$('.post-type-archive-tribe_events.slug-events .type-tribe_events').addClass('col').addClass('col-archive').addClass('col-events');
		
		wrapDiv ('.post-type-archive-tribe_events.slug-events .col-events', '<div class="col-inner"></div>', 'inside');
		
		$('.post-type-archive-tribe_events.slug-events .tribe-events-list-separator-month').addClass('span-all');

		$('.post-type-archive-tribe_events.slug-events .tribe-events-event-image').addClass('block').addClass('block-image').addClass('span-3');
		//$('.post-type-archive-tribe_events.slug-events .tribe-events-event-image').addClass('block').addClass('block-image').addClass('span-12');
		
		$('.post-type-archive-tribe_events.slug-events .tribe-events-content').addClass('block').addClass('block-group').addClass('span-9');		
		//$('.post-type-archive-tribe_events.slug-events .tribe-events-content').addClass('block').addClass('block-group').addClass('span-12');

		wrapDiv ('.post-type-archive-tribe_events.slug-events .tribe-events-content', '<div class="block block-text span-12"></div>', 'inside');	

		wrapDiv ('.post-type-archive-tribe_events.slug-events .tribe-events-read-more', '<div class="block block-button span-12 button-events"></div>', 'outside');	
		
		$('.post-type-archive-tribe_events.slug-events .tribe-events-read-more').addClass('button').addClass('button-events');
		
		$('.single-tribe_events .tribe-events-nav-pagination').addClass('navigation').addClass('single');
		
		$('.single-tribe_events .tribe-events-sub-nav').addClass('nav-links');		
		
		$('.single-tribe_events li.tribe-events-nav-previous a').addClass('nav-previous prev');

		$('.single-tribe_events li.tribe-events-nav-next a').addClass('nav-next next');

	// Move & position elements in the correct places
		
		moveDiv ('.post-type-archive-tribe_events.slug-events section.archive-events', '#tribe-events-content', 'before');
		
		moveDiv ('.post-type-archive-tribe_events.slug-events #tribe-events-footer', 'section.archive-events', 'after');
		
		removeDiv ('.post-type-archive-tribe_events.slug-events #tribe-events-content');		

		moveDivs ('.post-type-archive-tribe_events.slug-events .type-tribe_events', '.tribe-events-list-event-title', '.block-text > *:first-child', 'before');
		
		moveDivs ('.post-type-archive-tribe_events.slug-events .type-tribe_events', '.tribe-events-event-meta', '.block-text .tribe-events-list-event-title', 'after');
		
		moveDivs ('.post-type-archive-tribe_events.slug-events .type-tribe_events', '.tribe-events-venue-details', '.block-text p:last-of-type', 'after');		

		moveDivs ('.post-type-archive-tribe_events.slug-events .type-tribe_events', '.tribe-events-event-cost', '.block-text p:last-of-type', 'after');		
		
		$( ".ticket-cost" ).prepend( "<span class='cost-label'>Cost: </span>" );		
		
		$(".tribe-events-venue-details").each(function() {
			if ( $(this).find('a').length ) {		
				$(this).prepend( "<span class='venue-label'>Location: </span><br/>" );
			}				
		});
					
		moveDivs ('.post-type-archive-tribe_events.slug-events .type-tribe_events', '.block-button', '.tribe-events-content', 'after');
		
		moveDiv ('.tribe-events-notices', '.tribe_events', 'before');	
		
	// Change button text & add buttons

		$('.post-type-archive-tribe_events.slug-events .type-tribe_events').each(function() {			
			var thisEvent = $(this), eventTitle = thisEvent.find('.tribe-events-list-event-title').text(), buttonText = 'Learn More<span class="screen-reader-text"> about ' + eventTitle + '</span>';
			replaceText(thisEvent.find('.tribe-events-read-more'), 'Find out more »', buttonText, 'html');
		});

		$( '.post-type-archive-tribe_events.slug-events #tribe-events-footer' ).prepend( '<div class="block block-button span-12 button-events"><a class="button button-events" href="/events/month/">View Calendar</a></div>' );
		
		$( '.post-type-archive-tribe_events.slug-events-month #tribe-events-footer' ).prepend( '<div class="block block-button span-12 button-events"><a class="button button-events" href="/events/">View Event List</a></div>' );
		
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
		
		$('.tribe-events-list-event-title a').attr('aria-hidden','true').attr('tabindex', '-1');
				
	});
	
})(jQuery); });