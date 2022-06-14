document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Magic Menu
--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Magic Menu
--------------------------------------------------------------*/
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
	
})(jQuery); });