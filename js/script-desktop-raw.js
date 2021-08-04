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
	window.parallaxBG = function (container, filename, backgroundW, backgroundH, posX, posY, bleed, speed) {
		posX = posX || 'center';
		posY = posY || 'center';
		bleed = bleed || 20;
		speed = speed || 3;

		var theContainer = $(container);
		var checkPageH = theContainer.outerHeight();
		var parallaxS = (backgroundH / checkPageH) / speed;
		theContainer.parallax({ imageSrc:getUploadURI+'/'+filename, speed:parallaxS, bleed:bleed, naturalWidth:backgroundW, naturalHeight:backgroundH, positionX:posX, positionY:posY });	
	};

//Control parallax movement of divs within a container
	window.parallaxDiv = function (container, element) {
		element = element || ".parallax";
		function posParaDiv() {
			$(container).each(function() {					
				if ( $(this).find(element).length > 0) {
					var elem = $(this).find(element), elemH = elem.outerHeight();
					var conH = $(this).outerHeight(), conT = $(this).offset().top, conB = conT + conH;
					var winH = $(window).height(), winT = $(window).scrollTop(), winB = winT + winH;		
					var adjT = winB - conT, fullH = conH + winH, scrollPct = adjT / fullH;	
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
				$magicLine.css({ "transform":"translate("+arrayL[getIndex]+"px, "+arrayT[getIndex]+"px)", "width": arrayW[getIndex], "height": arrayH[getIndex] });
				$currentPage.find(">a").removeClass(linkOn).addClass(linkOff);
				$el.find(">a").addClass(linkOn).removeClass(linkOff);
			}, function() {
				$magicLine.css({ "transform":"translate("+$magicLine.data('origL')+"px, "+$magicLine.data('origT')+"px)", "width": $magicLine.data('origW'), "height": $magicLine.data('origH') });
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
				if ( getMagicPct >= 0.33 && getMagicPct < 0.66 ) { $('body').removeClass('menu-alt-1').removeClass('menu-alt-3').addClass('menu-alt-2'); }
				if ( getMagicPct >= 0.66 ) { $('body').removeClass('menu-alt-1').removeClass('menu-alt-2').addClass('menu-alt-3'); }		
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
			if ( optW < menuW ) $(this).addClass('left-menu');
			if ( optW > menuW ) $(this).addClass('right-menu');
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
	window.setupSidebar = function (compensate, sidebarScroll, shuffle) {
		sidebarScroll = sidebarScroll || "true";
		shuffle = shuffle || "true";		
		compensate = compensate || 0;		

// Shuffle an array of widgets
		window.arrangeWidgets = function ($elements) {
			var i, index1, index2, temp_val, count = $elements.length, $parent = $elements.parent(), shuffled_array = [];
			for (i = 0; i < count; i++) { shuffled_array.push(i); }
			
			if ( shuffle == "true" ) {
				for (i = 0; i < count; i++) {
					index1 = (Math.random() * count) | 0;
					index2 = (Math.random() * count) | 0;
					temp_val = shuffled_array[index1];
					shuffled_array[index1] = shuffled_array[index2];
					shuffled_array[index2] = temp_val;
				}
			}

			$elements.detach();
			for (i = 0; i < count; i++) { $parent.append( $elements.eq(shuffled_array[i]) ); }			

			var el = $(".widget.lock-to-bottom").detach();
			$parent.append( el );		
		};		
		
// Check & log heights of main elements
		window.checkHeights = function () {
			var primary = $('#primary').outerHeight();
			var viewport = $(window).outerHeight();
			var widgets = $("#secondary .sidebar-inner").outerHeight() + parseInt($("#secondary").css('padding-top')) + parseInt($("#secondary").css('padding-bottom')) + compensate;			
			var remain = primary - widgets;

			$('#wrapper-content').attr( 'data-primary', Math.round(primary) ).attr( 'data-viewport', Math.round(viewport) ).attr( 'data-widgets', Math.round(widgets) ).attr( 'data-remain', Math.round(remain) );
			
			return remain;
		}

// Set up "locked" widgets, and shuffle the rest
		$('.widget.lock-to-top, .widget.lock-to-bottom').addClass("locked");		
		$('.widget:not(.locked)').addClass("shuffle");
		arrangeWidgets( $('.shuffle') );

// Initiate widget removal
		window.widgetInit = function () {		
			$('#secondary').css({ "height":"calc(100% + "+compensate+"px)" });
			$('.widget:not(.widget-important)').addClass('hide-widget');
			addWidgets();				
		};
		
// Add widget one by one as long as they fit
		window.addWidgets = function () {
			$('.hide-widget:not(.remove-first)').each(function(){
				if ( $(this).height() <= checkHeights() ) { $(this).removeClass('hide-widget'); }				
			});
	
			$('.hide-widget').each(function(){
				if ( $(this).height() <= checkHeights() ) { $(this).removeClass('hide-widget'); }				
			});
			
			if ( !$('.widget:not(.hide-widget)').length ) { $('widget:first-of-type').removeClass('hide-widget'); }
			
			checkHeights();
			labelWidgets();
		};	

// Add classes for first, last, even and odd widgets
		window.labelWidgets = function () {
			$(".widget").removeClass("widget-first").removeClass("widget-last").removeClass("widget-even").removeClass("widget-odd");
			$(".widget:not(.hide-widget)").first().addClass("widget-first");  
			$(".widget:not(.hide-widget)").last().addClass("widget-last"); 
			$(".widget:not(.hide-widget):odd").addClass("widget-even"); 
			$(".widget:not(.hide-widget):even").addClass("widget-odd"); 	
		};

 // Move sidebar in conjunction with mouse scroll to keep it even with content
		window.moveWidgets = function () {
			if ( sidebarScroll == "true" ) {
				var sidebar = $(".sidebar-inner"), scrollPct, findPos;	
				var primary = Number($('#wrapper-content').attr('data-primary'));
				var widgets = Number($('#wrapper-content').attr('data-widgets'));
				var viewport = Number($('#wrapper-content').attr('data-viewport'));
				var remain = Number($('#wrapper-content').attr('data-remain'));				
				var primaryOffset = $("#primary").offset().top;
				var scrollPos = Number($(window).scrollTop());
				var adjScrollPos = scrollPos - primaryOffset;			
								
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
				sidebar.css({ "margin-top": findPos+"px" }); 
			}
		};
	};	
	
// Preload BG image and fade in
	if ( $( 'body' ).hasClass( "background-image" ) ) {
		var preloadBG = new Image();
		preloadBG.onload = function() { animateDiv( '.parallax-mirror', 'fadeIn', 0, '', 200 ); };		
		preloadBG.onerror = function() { console.log("site-background.jpg not found"); };
		preloadBG.src = getUploadURI + "/" + "site-background.jpg";  
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
	$('iframe').each(function(){
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