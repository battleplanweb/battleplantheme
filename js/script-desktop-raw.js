document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Desktop functionality
# Set up sidebar
# Browser resize
# ADA compliance
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Desktop functionality
--------------------------------------------------------------*/
	var getUploadURI = site_dir.upload_dir_uri;
	
//Set full screen parallax background for desktops
	window.parallaxBG = function (container, filename, backgroundW, backgroundH, posX, posY, bleed, speed, id) {
		posX = posX || 'center';
		posY = posY || 'center';
		bleed = bleed || 20;
		speed = speed || 3;
		id = id || container.replace('#','');

		var checkPageH = $(container).outerHeight(), parallaxS = (backgroundH / checkPageH) / speed;
		$(container).parallax({ imageSrc:getUploadURI+'/'+filename, speed:parallaxS, bleed:bleed, naturalWidth:backgroundW, naturalHeight:backgroundH, positionX:posX, positionY:posY, id: id });	
	};

//Control parallax movement of divs within a container
	window.parallaxDiv = function (container, element) {
		element = element || ".parallax";
		function posParaDiv() {
			$(container).each(function() {					
				if ( $(this).find(element).length > 0) {
					var elem = $(this).find(element), elemH = elem.outerHeight(), conH = $(this).outerHeight(), conT = $(this).offset().top, conB = conT + conH, winH = $(window).height(), winT = $(window).scrollTop(), winB = winT + winH, adjT = winB - conT, fullH = conH + winH, scrollPct = adjT / fullH;	
					if ( scrollPct > 1 ) { scrollPct = 1; }
					if ( scrollPct < 0 || scrollPct == null ) { scrollPct = 0; }
					var moveElem = (conH - elemH) * scrollPct;					
					if ( conT < winB && conB > winT ) { elem.css("margin-top",moveElem+"px"); }
				}
			});
		}
		window.addEventListener('scroll', function() { posParaDiv(); });			
		posParaDiv();
	};
		
// Set up Split Menu
	window.splitMenu = function (menu, logo, compensate, override) {
		menu = menu || "#desktop-navigation";
		logo = logo || ".logo img";			
		compensate = compensate || 0;	
		override = override || false;
		var menuFlex = $(menu).find('.flex'), menuUL = $(menu).find('ul.menu'), menuLI = $(menu).find('ul.menu li'), logoW = $(logo).outerWidth() + compensate, menuW = menuUL.width() / 2, totalOpt = menuLI.length, maxOpt = Math.round(totalOpt / 2), currOpt = 0;

		if ( override == false ) {
			menuUL.children().each(function() {
				currOpt += $(this).outerWidth();
				if ( currOpt < menuW ) { $(this).addClass('left-menu'); } else { $(this).addClass('right-menu'); }
			});
		} else {
			if ( override != true ) { maxOpt = override; }
			menuUL.children().each(function() {
				currOpt++;
				if ( currOpt <= maxOpt ) { $(this).addClass('left-menu'); } else { $(this).addClass('right-menu'); }
			});		
		}

		addDiv(menuFlex, '<div class="split-menu-r"></div>', 'before'); 
		addDiv(menuFlex, '<div class="split-menu-l"></div>', 'before'); 			
		moveDiv(menuUL, '.split-menu-l', 'inside'); 			
		cloneDiv(menuUL, '.split-menu-r', 'inside'); 			
		$('.split-menu-l ul.menu').attr('id', $('.split-menu-l ul.menu').attr('id') + "-l");
		$('.split-menu-r ul.menu').attr('id', $('.split-menu-r ul.menu').attr('id') + "-r");			
		$('.split-menu-l').find('li.right-menu').remove();
		$('.split-menu-r').find('li.left-menu').remove();
		menuFlex.css({"grid-column-gap":logoW+"px"});
	};	
			
// Add a logo into an <li> on the menu strip
	window.addMenuLogo = function (imageFile, menu) {
		menu = menu || '#desktop-navigation';
		$(menu).addClass('menu-with-logo');
		addDiv('.menu-with-logo','<div class="menu-logo"><img src = "'+imageFile+'" alt=""></div>','before');
		$('.menu-with-logo .menu-logo').height($('.menu-with-logo').height());
		linkHome('.menu-logo');
	};	
	
/*--------------------------------------------------------------
# Set up sidebar
--------------------------------------------------------------*/
	window.desktopSidebar = function (compensate, sidebarScroll) {
		
// Check & log heights of main elements
		window.checkHeights = function () {
			var primary = $('#primary').outerHeight(), viewport = $(window).outerHeight(), widgets = $("#secondary .sidebar-inner").outerHeight() + parseInt($("#secondary").css('padding-top')) + parseInt($("#secondary").css('padding-bottom')) + compensate, remain = primary - widgets;

			$('#wrapper-content').attr( 'data-primary', Math.round(primary) ).attr( 'data-viewport', Math.round(viewport) ).attr( 'data-widgets', Math.round(widgets) ).attr( 'data-remain', Math.round(remain) );
			
			return remain;
		}

// Initiate widget removal
		window.widgetInit = function () {
			if ( compensate != 0 ) { $('#secondary').css({ "height":"calc(100% + "+compensate+"px)" }); }
			
			$('.widget').attr('data-priority', 2).addClass('hide-widget');			
			$('.widget.widget-priority-5, .widget.widget-essential, .widget.widget_nav_menu').attr('data-priority', 5).removeClass('hide-widget');
			$('.widget.widget-priority-4, .widget.widget-important, .widget-contact-form').attr('data-priority', 4);
			$('.widget.widget-priority-3, .widget.widget-event, .widget.widget-financing').attr('data-priority', 3);
			$('.widget.widget-priority-1, .widget.remove-first').attr('data-priority', 1);
			var handleSets = 0;
			$('.widget.widget-set.set-a').each(function() {
				if ( handleSets > 0 ) { $(this).attr('data-priority', 0); }		
				handleSets++;
			});
			handleSets = 0;
			$('.widget.widget-set.set-b').each(function() {
				if ( handleSets > 0 ) { $(this).attr('data-priority', 0); }		
				handleSets++;
			});
			handleSets = 0;
			$('.widget.widget-set.set-c').each(function() {
				if ( handleSets > 0 ) { $(this).attr('data-priority', 0); }		
				handleSets++;
			});

			addWidgets();				
		};

// Add widget one by one as long as they fit
		window.addWidgets = function () {
			for (var i = 4; i >= 0; i--) {
				$('.hide-widget').each(function() {
					if ( $(this).attr('data-priority') == i && $(this).height() <= checkHeights() ) { $(this).removeClass('hide-widget'); }						
				});
			}
				
			if ( !$('.widget:not(.hide-widget)').length ) { $('.widget:first-of-type').removeClass('hide-widget'); } // guarantees at least 1 visible widget
			
			checkHeights();
			labelWidgets();
		};	

 // Move sidebar in conjunction with mouse scroll to keep it even with content
		window.moveWidgets = function () {
			if ( sidebarScroll == "true" ) {
				var scrollPct, findPos, primary = Number($('#wrapper-content').attr('data-primary')), widgets = Number($('#wrapper-content').attr('data-widgets')), viewport = Number($('#wrapper-content').attr('data-viewport')), remain = Number($('#wrapper-content').attr('data-remain')), primaryOffset = $("#primary").offset().top, scrollPos = Number($(window).scrollTop()), adjScrollPos = scrollPos - primaryOffset;			
								
				if ( scrollPos > primaryOffset ) {				
					scrollPct = adjScrollPos / ( primary - viewport );
					findPos = remain * scrollPct;
				} else {
					findPos = 0;
				}

				$('.stuck').each(function() {
					viewport = viewport - $(this).outerHeight();
				})
				viewport = viewport - $('.wp-google-badge').outerHeight();	

				if ( widgets < viewport ) { findPos = adjScrollPos + parseInt($("#secondary").css('padding-top')); }
				if ( findPos > remain ) { findPos = remain; }
				if ( findPos < 0 ) { findPos = 0; }
				if ( findPos > 0 && findPos < remain ) { checkHeights(); }
				$(".sidebar-inner").css({ "margin-top": findPos+"px" }); 
			}
		};
	};
	
// Calculate & center sub navigation under <li>	
	window.centerSubNav = function () {
		$('.main-navigation ul.sub-menu').each(function() {	
			var subW = $(this).outerWidth(true), parentW = $(this).parent().width(), moveL = - Math.round((subW - parentW) / 2);
			$(this).css({ "left":moveL+"px" });		
		});
	};	
	
// Reveal "Are We Open" banner
if ( $( "#masthead .phone-link" ).length ) {
	setTimeout(function() {
		var posY = getPosition ($('#masthead .phone-link'), 'center-y', 'window'), posX = getPosition ($('#masthead .phone-link'), 'right', 'window'), breakpoint = getDeviceW() - posX;
		if ( breakpoint < 133 ) { $('.currently-open-banner').addClass('horz'); }
		$('.currently-open-banner').css({"top":posY+"px", "left":posX+"px"});
		$('.currently-open-banner').addClass('reveal-open');
	}, 2000);
}
	
/*--------------------------------------------------------------
# Browser resize
--------------------------------------------------------------*/
	$(window).on( 'load', function() { browserResize(true); });
	$(window).resize(function() { browserResize(true); }); 

	window.browserResize = function (widgets) {		
		widgets = widgets || false;

		setTimeout( function () {
		// Ensure Parallax elements and widgets are re-assessed
			$(window).trigger('resize.px.parallax');
			if ( widgets == true ) { widgetInit(); } 

		// If sidebar exists, move widgets with scroll	
			if ( $("#secondary").length ) {  	
				window.addEventListener('scroll', moveWidgets );
			} else {
				window.removeEventListener('scroll', moveWidgets );
			}
			
		// Calculate & center sub navigation under <li>	
			centerSubNav();
		}, 300); // 200 is shortest time for widgets to work
	};
	
/*--------------------------------------------------------------
# ADA compliance
--------------------------------------------------------------*/	
	// Add special focus outline when someone is using tab to navigate site
	$(document).mousemove(function(event) { $('body').addClass('using-mouse').removeClass('using-keyboard'); });
	$(document).keydown(function(e) { if( e.keyCode == 9 && !$('body').hasClass("using-mouse") ) { $('body').addClass('using-keyboard'); } });

	// Remove iframe from tab order
	$('iframe').each(function() {
		$(this).attr("aria-hidden", true).attr("tabindex","-1");		
	})

	// Add .tab-focus class to links and buttons & auto scroll to center	
	document.addEventListener("keydown", function(e) {
		if ( e.keyCode === 9 ) { // 9 is tab key				
			var els = document.getElementsByClassName('tab-focus');		
			while ( els[0] ) {
				els[0].classList.remove('tab-focus')
			};
			setTimeout(function() {
				var getEl = document.activeElement, menuItem = getEl.closest('.menu-item');
				getEl.classList.add("tab-focus"); 		
				if ( menuItem == null ) {
					getEl.scrollIntoView({ behavior: 'smooth', block: 'center' });	
				} else {
					menuItem.classList.add("tab-focus");
					getEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });	
				}
			}, 10);
		}		
	});
	document.addEventListener("mousedown", function(e) {
		var el, els = document.getElementsByClassName('tab-focus');				
		if ( els[0] ) { 
			for (el of els ) { 
				el.classList.remove('tab-focus'); 
			} 
		};			
	});
	
})(jQuery); });