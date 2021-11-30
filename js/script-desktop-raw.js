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
	
// Preload BG image and fade in
	if ( $( 'body' ).hasClass( "background-image" ) ) {
		var preloadBG = new Image();
		preloadBG.onload = function() { animateDiv( '.parallax-mirror', 'fadeIn', 0, '', 200 ); };		
		preloadBG.src = getUploadURI + "/" + "site-background.jpg";  
	}
	
//Set full screen parallax background for desktops
	window.parallaxBG = function (container, filename, backgroundW, backgroundH, posX, posY, bleed, speed) {
		posX = posX || 'center';
		posY = posY || 'center';
		bleed = bleed || 20;
		speed = speed || 3;

		var checkPageH = $(container).outerHeight(), parallaxS = (backgroundH / checkPageH) / speed;
		$(container).parallax({ imageSrc:getUploadURI+'/'+filename, speed:parallaxS, bleed:bleed, naturalWidth:backgroundW, naturalHeight:backgroundH, positionX:posX, positionY:posY });	
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
	
// Set up "Magic Menu"	
	window.magicMenu = function (menu, linkOn, linkOff, stateChange) {
		menu = menu || "#desktop-navigation .menu";
		linkOn = linkOn || "active";
		linkOff = linkOff || "non-active";
		stateChange = stateChange || "false";
		var $mainNav = $(menu), $baseNav = $mainNav.parent().parent(), $el, $currentPage, magicT, magicL, magicW, magicH, orient = "horizontal";		
		if ( $baseNav.hasClass("widget") ) { orient = "vertical"; }		

		$baseNav.prepend("<div id='magic-line'></div>");
		$baseNav.prepend("<div id='off-screen' class='" + orient + "'></div>");			
		var $magicLine = $("#magic-line"), $offScreen = $("#off-screen");

		if ( !$mainNav.find("li.current-menu-parent").length ) {		
			$currentPage = $mainNav.find("li.current-menu-item");
		} else { 
			$currentPage = $mainNav.find("li.current-menu-parent");
		}				
		if ( !$currentPage.length || $currentPage.hasClass("mobile-only") ) { $currentPage = $offScreen; }	
		$currentPage.find(">a").addClass(linkOn);

		window.setMagicMenu = function () {
			if ( orient == "horizontal" ) {
				magicL = Math.round($currentPage.position().left + (($currentPage.outerWidth() - $currentPage.width()) / 2));
				magicT = Math.round($currentPage.position().top); 
				magicW = Math.round($currentPage.width()); 
				magicH = Math.round($magicLine.data('origH'));
			} else {
				magicL = Math.round(parseInt($currentPage.parent().css('padding-left'))); 
				magicT = Math.round($currentPage.position().top);
				magicW = Math.round($magicLine.data('origW')); 
				magicH = Math.round($currentPage.height());  
			}
			$magicLine.css({ "transform":"translate("+magicL+"px, "+magicT+"px)", "width": magicW, "height": magicH }).data("origT", magicT).data("origL", magicL).data("origW", magicW).data("origH", magicH);	

			$magicLine.delay(250).animate({ "opacity":1 }, 0);

			var arrayT = [], arrayL = [], arrayW = [], arrayH = [];

			$('#desktop-navigation ul.main-menu > .menu-item, .widget-navigation .menu-item').each(function() {
				var thisItem = $(this);	
				if ( orient == "horizontal" ) {
					arrayT.push(Math.round(thisItem.position().top));				
					arrayL.push(Math.round(thisItem.position().left + ((thisItem.outerWidth() - thisItem.width()) / 2)))
					arrayW.push(Math.round(thisItem.width()));
					arrayH.push(Math.round($magicLine.data('origH')));
				} else {
					arrayT.push(Math.round(thisItem.position().top));			
					arrayL.push(Math.round(parseInt(thisItem.parent().css('padding-left'))));
					arrayW.push(Math.round($magicLine.data('origW'))); 
					arrayH.push(Math.round(thisItem.height())); 
				}
			});

			$mainNav.find(" > li").hover(function() {	
				$el = $(this); 
				var getIndex = $el.index();
				setTimeout(function() { 
					$magicLine.css({ "transform":"translate("+arrayL[getIndex]+"px, "+arrayT[getIndex]+"px)", "width": arrayW[getIndex], "height": arrayH[getIndex] });
				}, 25);
				$currentPage.find(">a").removeClass(linkOn).addClass(linkOff);
				$el.find(">a").addClass(linkOn).removeClass(linkOff);
			}, function() {
				setTimeout(function() { 
					$magicLine.css({ "transform":"translate("+$magicLine.data('origL')+"px, "+$magicLine.data('origT')+"px)", "width": $magicLine.data('origW'), "height": $magicLine.data('origH') }); 
				}, 25);
				$el.find(">a").removeClass(linkOn).addClass(linkOff);
				$currentPage.find(">a").addClass(linkOn).removeClass(linkOff);
			});
		};

		// Add alternate classes to <body> to control state changes based on position of $magicLine
		if ( stateChange == "true" ) {
			var getMagicSide = $baseNav.find('.flex').position().left, getMagicW = $baseNav.find('.flex').width(), getMagicPos = $currentPage.position().left - getMagicSide, getMagicAdj, getMagicPct, getMagicNow;			

			$baseNav.find('li').mouseover(function() {
				getMagicPos = $(this).position().left - getMagicSide;
				magicColor(getMagicPos);
			});

			$baseNav.find('.flex').mouseout(function() {
				getMagicPos = $currentPage.position().left - getMagicSide;
				magicColor(getMagicPos);
			});

			window.magicColor = function (getMagicPos) {						
				getMagicAdj = getMagicPos + ($magicLine.width() / 2);
				getMagicPct = getMagicAdj / getMagicW;
				getMagicNow = $magicLine.position().left - getMagicSide;
				if ( getMagicPct < 0.33 ) { $('body').removeClass('menu-alt-2').removeClass('menu-alt-3').addClass('menu-alt-1'); }
				else if ( getMagicPct >= 0.33 && getMagicPct < 0.66 ) { $('body').removeClass('menu-alt-1').removeClass('menu-alt-3').addClass('menu-alt-2'); }
				else { $('body').removeClass('menu-alt-1').removeClass('menu-alt-2').addClass('menu-alt-3'); }		
				if ( getMagicAdj <= getMagicNow ) { $('body').addClass('menu-dir-l').removeClass('menu-dir-r'); }	
				else { $('body').addClass('menu-dir-r').removeClass('menu-dir-l'); }
			};	

			magicColor(getMagicPos);
		}

		setTimeout( function () { setMagicMenu(); }, 500);
		$(window).resize(function() { setMagicMenu(); }); 	
	};		
	
// Set up Split Menu
	window.splitMenu = function (menu, logo, compensate) {
		menu = menu || "#desktop-navigation";
		logo = logo || ".logo img";			
		compensate = compensate || 0;			
		var menuFlex = $(menu).find('.flex'), menuUL = $(menu).find('ul.menu'), logoW = $(logo).outerWidth() + compensate, menuW = menuUL.width() / 2, optW = 0;

		menuUL.children().each(function() {
			optW += $(this).outerWidth();
			if ( optW < menuW ) { $(this).addClass('left-menu'); } else { $(this).addClass('right-menu'); }
		});

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
	window.desktopSidebar = function (compensate, sidebarScroll, shuffle) {
		
// Check & log heights of main elements
		window.checkHeights = function () {
			var primary = $('#primary').outerHeight(), viewport = $(window).outerHeight(), widgets = $("#secondary .sidebar-inner").outerHeight() + parseInt($("#secondary").css('padding-top')) + parseInt($("#secondary").css('padding-bottom')) + compensate, remain = primary - widgets;

			$('#wrapper-content').attr( 'data-primary', Math.round(primary) ).attr( 'data-viewport', Math.round(viewport) ).attr( 'data-widgets', Math.round(widgets) ).attr( 'data-remain', Math.round(remain) );
			
			return remain;
		}

// Initiate widget removal
		window.widgetInit = function () {
			if ( compensate != 0 ) { $('#secondary').css({ "height":"calc(100% + "+compensate+"px)" }); }
			$('.widget:not(.widget-important)').addClass('hide-widget');
			addWidgets();				
		};
		
// Add widget one by one as long as they fit
		window.addWidgets = function () {
			$('.hide-widget:not(.remove-first):not(.hide-set)').each(function() {
				if ( $(this).height() <= checkHeights() ) { $(this).removeClass('hide-widget'); }	 			
			});
			
			$('.hide-widget:not(.remove-first)').each(function() {
				if ( $(this).height() <= checkHeights() ) { $(this).removeClass('hide-widget'); }	 			
			});
	
			$('.hide-widget').each(function(){
				if ( $(this).height() <= checkHeights() ) { $(this).removeClass('hide-widget'); }				
			});
			
			if ( !$('.widget:not(.hide-widget)').length ) { $('widget:first-of-type').removeClass('hide-widget'); } // guarantees at least 1 visible widget
			
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
		}, 300); // 200 is shortest time for widgets to work

	// Center the sub-menu ul under the parent li
		$('.main-navigation ul.sub-menu').each(function() {	
			var subW = $(this).outerWidth(true), parentW = $(this).parent().width(), moveL = - Math.round((subW - parentW) / 2);
			$(this).css({ "left":moveL+"px" });			
		});
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