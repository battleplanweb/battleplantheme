document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Basic site functionality
# DOM level functions
# Set up animation
# Set up pages
# Set up sidebar
# Screen resize
# ADA compliance
# Delay parsing of JavaScript

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Basic site functionality
--------------------------------------------------------------*/

	var getThemeURI = theme_dir.theme_dir_urti, getUploadURI = theme_dir.upload_dir_uri, mobileCutoff = 1024, tabletCutoff = 576, mobileMenuBarH = 0, timezone, userLoc, userRefer;
	
	if ( $("#mobile-menu-bar").is(":visible") ) { mobileMenuBarH = $("#mobile-menu-bar").outerHeight();	}

// Add Post ID as an ID attribute on body tag	
	var postID = "noID";    
	$.each($('body').attr('class').split(' '), function (index, className) { 
		if (className.indexOf('postid-') === 0) { postID = className.substr(7); }
		else if (className.indexOf('page-id-') === 0) { postID = className.substr(8); }
	});	
	$('body').attr('id',postID);

// Add page slug as class to body
	var slug = window.location.pathname;
	slug = slug.substr(1).slice(0, -1).replace("/", "-");		
	if ( slug ) { slug = "slug-"+slug; } else { slug = "slug-home"; }
	$('body').addClass(slug);
	
// Is user on an Apple device?
	window.isApple = function () {
		var iOS = !!navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform);
		return iOS;		
	};	
	
// Get domain of current webpage
	window.getDomain = function () {
		return window.location.hostname;
	};		
	
// Get "slug" of current webpage
	window.getSlug = function () {
		var findSlug = window.location.pathname.split('/');
		return findSlug[1];		
	};	

// Get variable from page's URL
	window.getUrlVar = function (key) {
		key = key || "page";
		var results = new RegExp('[\?&]' + key + '=([^&#]*)').exec(window.location.href);
		if (results == null) { return null; }
		return decodeURI(results[1]) || 0;
	};

// Set up Logo to link to home page	
	$('.logo').keyup(function(event) {
		if (event.keyCode === 13 || event.keyCode === 32) {
			$(this).click();
		}
	});
	$('.logo').attr('tabindex','0').attr('role','button').attr('aria-label','Return to Home Page').css('cursor', 'pointer').click(function() { window.location = "/"; });
	$('.logo img').attr('aria-hidden', 'true');
	
	window.linkHome = function (container) {
		$(container).keyup(function(event) {
			if (event.keyCode === 13 || event.keyCode === 32) {
				$(this).click();
			}
		});
		$(container).attr('tabindex','0').attr('role','button').attr('aria-label','Return to Home Page').css('cursor', 'pointer').click(function() { window.location = "/"; });
		$(container).find('img').attr('aria-hidden', 'true');
	};		

// Set up American Standard logo to link to American Standard website	
	$("img[src*='hvac-american-standard/american-standard']").each(function() { 
		$(this).wrap('<a href="https://www.americanstandardair.com/" target="_blank" rel="noreferrer"></a>'); 
	});
			
// Track phone number clicks
	window.trackClicks = function(type, category, action, url) {
		document.location = url;
		$.post({
			url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
			data : { action: "count_link_clicks", type: action },
			success: function( response ) { console.log(response);  } 
		});		
	};

// Set up Cookies
	window.setCookie = function(cname,cvalue,exdays) {
		var domain = document.domain.match(/[^\.]*\.[^.]*$/)[0];
		var d = new Date(), expires='';
		d.setTime(d.getTime()+(exdays*24*60*60*1000));
		if ( exdays != null && exdays != "" ) {	expires = "expires="+d.toGMTString()+"; "; }
		document.cookie = cname + "=" + cvalue + "; " + expires + "path=/; domain=" + domain +"; secure";
		console.log("cookie set [ "+cname + "=" + cvalue + "; " + expires + "path=/; domain=" + domain +"; secure ]");
	};
	window.getCookie = function(cname) {
		var name = cname + "=", ca = document.cookie.split(';');
		for(var i=0; i<ca.length; i++) {
			var c = ca[i].trim();
			if (c.indexOf(name)==0) return c.substring(name.length,c.length);
		}
		return "";
	};		
	window.deleteCookie = function(cname) {
		setCookie(cname,"",-1);
	};	

// Check if this is a visitor's first page to view & add .first-page class to trigger special CSS
	if ( !getCookie('first-page') ) { 
		setCookie('first-page', 'no'); 
		$("body").addClass("first-page"); 		
	} else { 
		$("body").addClass("not-first-page"); 
	}
	
// Calculate how many pages user has viewed (exclude page refresh)
	if ( !getCookie('pages-viewed') ) { 
		var uniqueID = Date.now() + "A" + Math.floor(Math.random() * 100);		
		setCookie('unique-id', uniqueID); 
		$("body").attr("data-unique-id", uniqueID); 
		$("body").attr("data-pageviews", 1); 
		setCookie('pages-viewed', 1); 
	} else { 
		var pages_viewed = Number(getCookie('pages-viewed'));
		if ( getCookie('prev-page') != getSlug() ) {
			pages_viewed++;
			setCookie('pages-viewed', pages_viewed); 		
			setCookie('prev-page', getSlug()); 
		}
		$("body").attr("data-pageviews", pages_viewed); 
		$("body").attr("data-unique-id", getCookie('unique-id')); 
	}

// Extend .hasClass to .hasPartialClass
	$.fn.hasPartialClass = function(partial){
		return new RegExp(partial).test(this.prop('class')); 
	};

// Find width of screen
	window.getDeviceW = function () {
		var deviceWidth = $(window).width();			
		if ( isApple() && (window.orientation == 90 || window.orientation == -90 )) {
			deviceWidth = window.screen.height;
		}
		return deviceWidth;
	};		

// Find height of screen
	window.getDeviceH = function () {
		var deviceHeight = $(window).height();			
		if ( isApple() && (window.orientation == 90 || window.orientation == -90 )) {
			deviceHeight = window.screen.width;
		}
		return deviceHeight;
	};	

// Make mobile and tablet cut off variables available in script-site.js
	window.getMobileCutoff = function () {
		return mobileCutoff;
	};	

	window.getTabletCutoff = function () {
		return tabletCutoff;
	};	

// Copy one element's classes to an outer div
	window.copyClasses = function (copyTo, copyFrom, additional) {	
		copyFrom = copyFrom || "img, iframe";
		$(copyTo).each(function() {
			$(this).addClass($(this).find(copyFrom).map(function() {
				return this.className;
			}).get().join(' ')).addClass(additional);
		});
	};
	
// Shuffle an array
	window.shuffleArray = function (array) {
  		for (let i = array.length - 1; i > 0; i--) {
			let j = Math.floor(Math.random() * (i + 1));
    		[array[i], array[j]] = [array[j], array[i]];
  		}
	}	

// Find & Replace text or html in a Div	
	window.replaceText = function (container, find, replace, type, all) {
		type = type || "text";	
		all = all || "false";	
		
		if ( all == "true" ) {
			find = new RegExp(find, "gi");			
		}
		
		if ( type == "text" ) { 
			$(container).text(function () {
				if ( find != "" ) {
					var thisText = $(this).text();
					return thisText.replace(find,replace); 
				} else {
					return replace; 
				}
			});
		} else {
			$(container).html(function () {
				if ( find != "" ) {
					var thisHtml = $(this).html();
					return thisHtml.replace(find,replace); 
				} else {
					return replace; 
				}
			});
		}
	};	

// Truncate long testimonial text
	window.trimText = function (length, container) {
		if (trimText.done) return;
		length = length || 250;
		container = container || ".slider-testimonials .testimonials-quote, #secondary .testimonials-quote, .random-post .testimonials-quote";
		
		$(container).each(function() { 
			var theText = $(this).html(), maxLength = length, trimText = theText.substr(0, maxLength);
			if ( trimText.length == maxLength ) {
				trimText = trimText.substr(0, Math.min(trimText.length, trimText.lastIndexOf(" ")));
				$(this).html(trimText  + "…" );
			}
		});
		trimText.done = true; 		
	};

// Remove sidebar from specific pages
	window.removeSidebar = function(page) {	
		var test1 = page.replace(".", ""), test2 = "slug-"+page.replace(/\//g, "");
		if ( $('body').hasClass(test1) || $('body').hasClass(test2) ) { 
			$('body').removeClass('sidebar-right').removeClass('sidebar-left').addClass('sidebar-none'); 
			removeDiv('#secondary');
		} 
	};
 
// Create faux div for sticky elements pulled out of document flow	
	window.addStuck = function (element, faux) {
		faux = faux || "true";	
		var fauxElement = element.split(" ").pop();
		if ( $(element).is(":visible") ) {						
			$(element).addClass("stuck");	
			if ( faux == "true" ) { addFaux(fauxElement); }
		}
	};

	window.removeStuck = function (element, faux) {
		faux = faux || "true";
		var fauxElement = element.split(" ").pop();
		$(element).removeClass("stuck");
		if ( faux == "true" ) { removeFaux(fauxElement); }
	};

	window.addFaux = function (element) {
		var theEl = $(element);
		var elementName = element.substr(1);		
		if ( theEl.is(":visible") ) {		
			$( "<div class='"+elementName+"-faux'></div>" ).insertBefore( theEl );
			var theFaux = $("."+elementName+"-faux");
			theFaux.css({ "height":theEl.outerHeight()+"px" });
		}
	};

	window.removeFaux = function (element) {
		var elementName = element.substr(1);
		var theFaux = $("."+elementName+"-faux");
		theFaux.remove();
	};

// Stick an element to top of screen
	window.lockDiv = function(container, strictTrigger, strictOffset, strictTop, faux, whichWay) {	
		strictTrigger = strictTrigger || "";		
		strictOffset = strictOffset || "";		
		strictTop = strictTop || "";	
		faux = faux || "true";			
		whichWay = whichWay || "both";	
		var trigger, offset;

		if ( strictTrigger === "" ) {
			if ( $(container).next().length ) {
				trigger = $(container).next();			
			} else {
				trigger = $(container).parent().next();	
			}
		} else {
			trigger = $(strictTrigger);
		}
		
		if ( strictOffset === "" ) {
			if ( strictTop === "" ) {
				offset = $(container).outerHeight();
			} else {				
				offset = $(container).outerHeight() + Number(strictTop);
			}				
		} else {
			offset = Number(strictOffset);	
		}

		$(container).css("top","unset");

		trigger.waypoint(function(direction) {		
			var newTop = 0;
			if ( strictTop === "" ) {
				$('.stuck').each(function() {
					newTop = newTop + $(this).outerHeight(true);
				});		
			} else {
				newTop = Number(strictTop);
			}

			newTop = newTop + mobileMenuBarH;

			if (direction === 'down' && ( whichWay === 'both' || whichWay === 'down' )) {			
				addStuck(container, faux);
				$(container).css("top",newTop+"px");
			} else if (direction === 'up' && ( whichWay === 'both' || whichWay === 'up' )) {
				removeStuck(container, faux);				
				$(container).css("top","unset");
			}	
		}, { offset: offset+"px" });		
	};

// Shortcut to stick menu to top
	window.lockMenu = function() {	
		lockDiv('#desktop-navigation');	
	};
	
// Add stroke to text & transparent objects
	window.addStroke = function(element, width, topColor, bottomColor, leftColor, rightColor) {	
  		var shadow = "", steps = 16, spacing = width - 1, color, angle, cos, sin;
		leftColor = leftColor || topColor;
		bottomColor = bottomColor || topColor;
		rightColor = rightColor || bottomColor;
		
  		for (var i = 0; i < steps; i++) {
    		angle = (i * 2 * Math.PI) / steps;
    		cos = Math.round(10000 * Math.cos(angle)) / 10000;
    		sin = Math.round(10000 * Math.sin(angle)) / 10000;	 
			if ( cos <= 0 && sin <= 0 ) { color = topColor; }  	  
			if ( cos >= 0 && sin >= 0 ) { color = bottomColor; }
			if ( cos <= 0 && sin >= 0 ) { color = leftColor; }
			if ( cos >= 0 && sin <= 0 ) { color = rightColor; }
			shadow += "calc("+ width + "px * " + cos + ") calc("+ width + "px * " + sin + ") 0 "+ color;
	 		if ( i < (steps-1) ) { shadow += ", "; }	  
  		}			
		
		var style = document.createElement('style');
		style.innerHTML = element + " { text-shadow: "+shadow+"; letter-spacing: "+spacing+"px; }";
		document.head.appendChild(style);
	};

// Animate the automated scrolling to section of content
	window.animateScroll = function (target, topSpacer, initSpeed) {
		var newTop=0, newLoc=0;
		initSpeed = initSpeed || 0;
		topSpacer = topSpacer || 0;
		topSpacer = topSpacer + mobileMenuBarH;	

		$('.stuck').each(function() {
			newTop = newTop + $(this).outerHeight();	
		});		

		if ( typeof target === 'object' || typeof target === 'string' ) {		
			newLoc = $(target).offset().top - newTop - topSpacer;
		} else {
			newLoc = target - newTop - topSpacer;
		}
		
		if ( target !== "#tab-description" ) { // 03/23/2021 --- Kin-Tec Industries
			window.scroll({ top: newLoc, left: 0, behavior: 'smooth' }); 
		}
	};

// Set up "Back To Top" button
	var backToTop = $('#wrapper-content').waypoint(function(direction) {
		if (direction === 'up') {			
			$('a.scroll-top').animate( { opacity: 0 }, 150, function() { $('a.scroll-top').css({ "display": "none" }); });
		} else {
			$('a.scroll-top').css({ "display": "block" }).animate( { opacity: 1 }, 150);
		}	
	}, { offset: '10%' });	

// Set up "Scroll Down" button
	var scrollDown = $('#wrapper-content').waypoint(function(direction) {
		if (direction === 'down') {			
			$('.scroll-down').fadeOut("fast"); 	
		} else {
			$('.scroll-down').fadeIn("fast"); 	
		}	
	}, { offset: '99%' });	

//Find screen position of element
	window.getPosition = function (container, neededPos, scope) {
		scope = scope || 'window';	
		var theContainer = $(container), getLeft, getTop, conW, conH, getBottom, getRight, getCenterX, getCenterY;		
		if ( scope == 'window' || scope == "screen" ) {
			getLeft = theContainer.offset().left; 
			getTop = theContainer.offset().top; 
		} else {
			getLeft = theContainer.position().left; 
			getTop = theContainer.position().top; 
		}
		conW = theContainer.outerWidth(true);
		conH = theContainer.outerHeight(true);		
		getBottom = getTop + conH;
		getRight = getLeft + conW;
		getCenterX = getLeft + (conW/2);
		getCenterY = getTop + (conH/2);	

		if ( neededPos == "left" || neededPos == "l") { return getLeft; }		
		if ( neededPos == "top" || neededPos == "t") { return getTop; }
		if ( neededPos == "bottom" || neededPos == "b") { return getBottom; }
		if ( neededPos == "right" || neededPos == "r") { return getRight; }		
		if ( neededPos == "centerX" || neededPos == "centerx" || neededPos == "center-x" ) { return getCenterX; }		
		if ( neededPos == "centerY" || neededPos == "centery" || neededPos == "center-y" ) { return getCenterY; }		
	};

//Find translateY or translateX of element
	window.getTranslate = function (container, XorY) {
		XorY = XorY || 'Y';
		var theContainer = document.querySelector(container), style = window.getComputedStyle(theContainer), matrix = style.transform || style.webkitTransform || style.mozTransform, matrixValues = matrix.match(/matrix.*\((.+)\)/)[1].split(', ');
		
		if ( XorY == "x" || XorY == "X" ) { return matrixValues[4]; }
		if ( XorY == "y" || XorY == "Y" ) { return matrixValues[5]; }
	};
	
// Accordion section - control opening & closing of expandable text boxes
	window.buildAccordion = function (topSpacer, cssDelay, transSpeed, closeDelay, openDelay, clickActive) {
		if (buildAccordion.done) { return; }
		transSpeed = transSpeed || 500;
		closeDelay = closeDelay || 0;
		openDelay = openDelay || 0;
		cssDelay = cssDelay || closeDelay + openDelay;
		topSpacer = topSpacer || 0.1;
		clickActive = clickActive || 'close';
		var accPos = [];

		if ( topSpacer < 1 ) { topSpacer = getDeviceH() * topSpacer; }		
		if ( getDeviceW() < mobileCutoff ) { topSpacer = topSpacer + mobileMenuBarH; }		

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

		$(".block-accordion").keyup(function(event) {
			if (event.keyCode === 13 || event.keyCode === 32) {
				$(this).click();
			}
		});
 
		$(".block-accordion .accordion-title").click(function(e) {		
			e.preventDefault();
			var thisBtn = $(this), locAcc = thisBtn.closest('.block-accordion'), locIndex = locAcc.index('.block-accordion'), locPos = accPos[locIndex], topPos = accPos[0], moveTo = 0, btnText=thisBtn.attr('data-text'), btnCollapse=thisBtn.attr('data-collapse');	
			
			if ( !locAcc.hasClass("active") ) {
				setTimeout( function () {
					$( '.block-accordion.active .accordion-excerpt' ).animate({ height: "toggle", opacity: "toggle" }, transSpeed);					
					$( '.block-accordion.active .accordion-content' ).animate({ height: "toggle", opacity: "toggle" }, transSpeed);
					if ( btnCollapse == "hide" ) { thisBtn.fadeOut(); } 
					else if ( btnCollapse != "false" ) { thisBtn.text(btnCollapse); } 
				}, closeDelay);
				setTimeout( function () {
					locAcc.find('.accordion-excerpt').animate({ height: "toggle", opacity: "toggle" }, transSpeed);						
					locAcc.find('.accordion-content').animate({ height: "toggle", opacity: "toggle" }, transSpeed);						
					if ( btnCollapse == undefined ) {
						if ( (locPos - topPos) > (getDeviceH() * 0.25) ) {
							moveTo = locPos;
							animateScroll(moveTo, topSpacer, transSpeed); 
						} else {
							moveTo = ((locPos - topPos) / 2) + topPos;						
							animateScroll(moveTo, topSpacer, transSpeed); 
						}	
					}
				}, openDelay);
				setTimeout( function() {						
					$(".block-accordion.active").removeClass('active').attr( 'aria-expanded', false ); 
					locAcc.addClass('active').attr( 'aria-expanded', true );
				}, cssDelay);
			} else if ( clickActive == 'close' ) {
				setTimeout( function () {
					$( '.block-accordion.active .accordion-excerpt' ).animate({ height: "toggle", opacity: "toggle" }, transSpeed);					
					$( '.block-accordion.active .accordion-content' ).animate({ height: "toggle", opacity: "toggle" }, transSpeed);
					if ( btnCollapse != "false" ) { thisBtn.text(btnText); } 
				}, closeDelay);	
				setTimeout( function() {						
					$(".block-accordion.active").removeClass('active').attr( 'aria-expanded', false );
				}, cssDelay);	
			}
		});
		buildAccordion.done = true;
	};

//Set full screen parallax background for desktops
	window.parallaxBG = function (container, filename, backgroundW, backgroundH, posX, posY, bleed, speed) {
		posX = posX || 'center';
		posY = posY || 'center';
		bleed = bleed || 20;
		speed = speed || 3;

		if ( getDeviceW() > mobileCutoff ) {  
			var theContainer = $(container);
			var checkPageH = theContainer.outerHeight();
			var parallaxS = (backgroundH / checkPageH) / speed;
			theContainer.parallax({ imageSrc:getUploadURI+'/'+filename, speed:parallaxS, bleed:bleed, naturalWidth:backgroundW, naturalHeight:backgroundH, positionX:posX, positionY:posY });	
		}  
	};

//Control parallax movement of divs within a container
	window.parallaxDiv = function (container, element) {
		element = element || ".parallax";

		function moveDiv() {
			$(container).each(function() {	
				var elem = $(this).find(element), elemH = elem.outerHeight();
				var conH = $(this).outerHeight(), conT = $(this).offset().top, conB = conT + conH;
				var winH = $(window).height(), winT = $(window).scrollTop(), winB = winT + winH;		
				var adjT = winB - conT, fullH = conH + winH, scrollPct = adjT / fullH;	
				if ( scrollPct > 1 ) { scrollPct = 1; }
				if ( scrollPct < 0 || scrollPct == null ) { scrollPct = 0; }
				var moveElem = (conH - elemH) * scrollPct;					
				if ( conT < winB && conB > winT ) { elem.css("margin-top",moveElem+"px"); }
			});
		}
		if ( getDeviceW() > mobileCutoff ) {  			
			window.addEventListener('scroll', function() { moveDiv(); });			
		}
		moveDiv();


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
				magicL = $currentPage.position().left + (($currentPage.outerWidth() - $currentPage.width()) / 2);
				magicT = $currentPage.position().top; 
				magicW = $currentPage.width(); 
				magicH = $magicLine.data('origH');
			} else {
				magicL = parseInt($currentPage.parent().css('padding-left')); 
				magicT = $currentPage.position().top;
				magicW = $magicLine.data('origW'); 
				magicH = $currentPage.height();  
			}
			$magicLine.css({ "transform":"translate("+magicL+"px, "+magicT+"px)", "width": magicW, "height": magicH }).data("origT", magicT).data("origL", magicL).data("origW", magicW).data("origH", magicH);	

			$magicLine.delay(250).animate({ "opacity":1 }, 0);

			var arrayT = [], arrayL = [], arrayW = [], arrayH = [];

			$('#desktop-navigation ul.main-menu > .menu-item, .widget-navigation .menu-item').each(function() {
				var thisItem = $(this);	
				if ( orient == "horizontal" ) {
					arrayT.push(thisItem.position().top);				
					arrayL.push(thisItem.position().left + ((thisItem.outerWidth() - thisItem.width()) / 2));
					arrayW.push(thisItem.width());
					arrayH.push($magicLine.data('origH'));
				} else {
					arrayT.push(thisItem.position().top);			
					arrayL.push(parseInt(thisItem.parent().css('padding-left')));
					arrayW.push($magicLine.data('origW')); 
					arrayH.push(thisItem.height()); 
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
		if ( getDeviceW() > mobileCutoff ) { 
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
		}		
	};	
	
// Add a logo into an <li> on the menu strip
	window.addMenuLogo = function (imageFile, menu) {
		menu = menu || '#desktop-navigation';
		$(menu).addClass('menu-with-logo');
		addDiv('.menu-with-logo','<div class="menu-logo"><img src = "'+imageFile+'" alt=""></div>','before');
		$('.menu-with-logo .menu-logo').height($('.menu-with-logo').height());
		linkHome('.menu-logo');
	};
	
// Duplicate menu button text onto the button BG
	$( ".main-navigation ul.main-menu li > a").each(function() { 
		var btnText = $(this).html();			
		$(this).parent().attr('data-content', btnText);
	});

// Set up Logo Slider
	$('.logo-slider').each(function() {
		var logoSlider = $(this), logoRow = logoSlider.find('.logo-row'), speed = logoSlider.attr('data-speed'), delay = (parseInt(logoSlider.attr('data-delay'))) * 1000, maxW = getDeviceW() * (parseInt(logoSlider.attr('data-maxw')) / 100), pause = logoSlider.attr('data-pause'), spacing = getDeviceW() * (parseInt(logoSlider.attr('data-spacing')) / 100), easing = "swing", moving = true, firstLogo, secondLogo, largestW = 0, checkW = 0, thisW = 0, firstPos = 0, secondPos = 0, space = 0, containerW = 0, logoW = 0;
		
		logoRow.css({'opacity': 0});
		
		if ( delay == "0" ) { easing = "linear"; } 
		if ( pause == "yes" || pause == "true" ) {
			logoSlider.mouseover(function() { moving = false; });
			logoSlider.mouseout(function() { moving = true; });
		}		
		if ( getDeviceW() < mobileCutoff ) { spacing = spacing * 1.5; }

		setTimeout(function() { 
			logoSlider.find('span').find('img').each(function() { 
				thisW = parseInt($(this).attr('width'));
				if ( thisW > maxW ) { thisW = maxW; $(this).width(thisW); }
				if ( thisW > largestW ) { largestW = thisW; }
				logoW = logoW + spacing + thisW; 
			});

			setTimeout(function() { 
				checkW = getDeviceW() + largestW + spacing;
				if ( logoW < checkW ) { logoW = checkW; }
				logoRow.css('width', logoW); 
				
				if ( speed == "slow" ) { speed = logoW * 3;
				} else if ( speed == "fast" ) { speed = logoW * 1.5;
				} else { speed = logoW * (parseInt(speed)); }
				
				logoRow.animate({ 'opacity': 1}, 300);

				function moveLogos() {
					if ( moving != false ) {								
						firstLogo = logoRow.find('span:nth-of-type(1)');
						firstPos = firstLogo.position().left + firstLogo.width();
						secondLogo = logoRow.find('span:nth-of-type(2)');
						secondPos = secondLogo.position().left;	
						containerW = firstLogo.width() + secondPos - firstPos; 	
						
						logoRow.animate({ 'margin-left': -containerW+'px'}, speed, easing, function() {
							firstLogo.remove();
							logoRow.find('span:last').after(firstLogo);
							logoRow.css({ 'margin-left': '0px' });
						});						
					} 	
				}
				if ( delay > 0 ) { delay = delay + speed; moveLogos(); }
				var advanceLogos = setInterval( moveLogos, delay );
			}, 10);
		}, 1500);
	});

	// Filter Post Archive entries according to class (hide all, arrange, show all)  ** Mill Pond Retrievers dog / litter archive
	window.filterArchives = function (field, container, column, speed) {
		field = field || null;		
		container = container || ".section.archive-content";		
		column = column || ".col-archive";
		speed = speed || 300;

		$(container).fadeTo(speed, 0, function () {
			if ( field == "" || field == null ) { $(column).show(); }
			else { $(column).hide(); $(column+'.'+field).show(); }	
			$(column).css({ "clear":"none" });
			$(container).fadeTo(speed, 1);
		});
	};		
		


	// Handle the post filter button [get-filter-btn]
	$(".filter-btn").click(function() {
		var thisBtn = $(this), url = "?"+thisBtn.attr('data-url')+"=", flag=false;
		$("input:checkbox[name=choice]:checked").each(function() {
			if ( !flag ) {
				url = url + $(this).val();
				flag = true;
			} else {
				url = url  + "," + $(this).val();
			}         
		});
		window.location = url;
	});		
		
	// Tabbed Content - control changing and animation of tabbed content
	$("ul.tabs li").keyup(function(event) {
		var thisBtn = $(this);
		if (event.keyCode === 13 || event.keyCode === 32) { thisBtn.click(); }
	});		
	$('ul.tabs li').click(function() {
		var tab_id = $(this).attr('data-tab');
		var fadeSpeed = 150;

		$('ul.tabs li').removeClass('current');
		$(this).addClass('current');

		$('.tab-content').fadeOut(fadeSpeed).next().removeClass('current');
		$("#"+tab_id).delay(fadeSpeed).addClass('current').fadeIn(fadeSpeed);
	});

	// Prepare data for javascript that was encodded in PHP
	window.prepareJSON = function (data) {
		data = data.replace(/%7B/g, '{').replace(/%7D/g, '}').replace(/%22/g, '"').replace(/%3A/g, ':').replace(/%2C/g, ',');				
		return $.parseJSON(data);
	};		
	
	// Set a div's height to 0 and then slide to full height
	window.revealDiv = function (container, delay, speed, offset) {
		delay = delay || 0;		
		speed = speed || 1000;		
		offset = offset || "100%";
		var total = delay + speed, theEl = $(container), fixedH = theEl.outerHeight(), padT = theEl.css('padding-top'), padB = theEl.css('padding-bottom');
		theEl.css({"height":0, "padding-top":0, "padding-bottom":0});		
		setTimeout( function () { 
			theEl.waypoint(function() {
				theEl.animate({"height": fixedH, "padding-top":padT, "padding-bottom":padB}, speed);
				setTimeout( function () { screenResize(); }, total);	
			}, { offset: offset });
		}, delay);	
	};	
		
// Button to reveal a hidden div
	window.btnRevealDiv = function(button, container, topSpacer, initSpeed) {	
		initSpeed = initSpeed || 0;
		topSpacer = topSpacer || 0;
		var origDisplay = $( container ).css( "display" );

		if ( getDeviceW() < mobileCutoff ) { 
			topSpacer = topSpacer + $("#mobile-menu-bar").outerHeight();	
		}			

		$( container ).css( "display","none");	
		$( button ).click(function(){
			$( container ).css( "display",origDisplay);
			var target = container;
			animateScroll(target, topSpacer, initSpeed);
		});
	};
	
	// Set up Review Questions & Redirect
	$('.review-form:first').addClass('active');
	$('.review-form #gmail-yes').click(function() { window.location.href = "/google"; });	
	$('.review-form #facebook-yes').click(function() { window.location.href = "/facebook"; });
	
	$('.review-form #gmail-no, .review-form #facebook-no').click(function() {
		$(this).closest('.review-form').removeClass('active');
		$(this).closest('.review-form').next().addClass('active');			
	});

/*--------------------------------------------------------------
# DOM level functions
--------------------------------------------------------------*/

// Replace one class with another
	$.fn.replaceClass = function (pFromClass, pToClass) {
		return this.removeClass(pFromClass).addClass(pToClass);
	};

// Randomly select from a group of elements
	$.fn.random = function() { return this.eq(Math.floor(Math.random() * this.length)); };

// Clone div and move the copy to new location
	window.cloneDiv = function (moveThis, anchor, where) {
		where = where || "after";
		var thisDiv = $(moveThis), thisAnchor = $(anchor);
		if ( where == "after" ) {
			thisDiv.clone().insertAfter( thisAnchor );
		} else if ( where == "before" ) {
			thisDiv.clone().insertBefore( thisAnchor );
		} else if ( where == "top" || "start" ) {
			thisAnchor.prepend(thisDiv.clone());
		} else {
			thisAnchor.append(thisDiv.clone());
		}
	};	

// Move a single div to another location
	window.moveDiv = function (moveThis, anchor, where) {
		where = where || "after";
		var thisDiv = $(moveThis), thisAnchor = $(anchor);
		if ( where == "after" ) {
			thisDiv.insertAfter( thisAnchor );
		} else if ( where == "before" ) {
			thisDiv.insertBefore( thisAnchor );
		} else if ( where == "top" || "start" ) {
			thisAnchor.prepend(thisDiv);
		} else {
			thisAnchor.append(thisDiv);
		}
	};

// Move multiple divs to another location
	window.moveDivs = function (wrapper, moveThis, anchor, where) {
		where = where || "after";
		$(wrapper).each(function() {	
			var thisDiv = $(this);			
			if ( where == "after" ) {
				thisDiv.find( $( moveThis )).insertAfter( thisDiv.find( anchor ) );
			} else if ( where == "before" ) {
				thisDiv.find( $( moveThis )).insertBefore( thisDiv.find( anchor ) );
			} else if ( where == "top" || "start" ) {
				thisDiv.find( anchor ).prepend(thisDiv.find( $( moveThis )));
			} else {
				thisDiv.find( anchor ).append(thisDiv.find( $( moveThis )));
			}
		});
	};

// Add a div within an existing div
	window.addDiv = function (target, newDiv, where) {
		newDiv = newDiv || "<div></div>";
		where = where || "after";
		var addDiv = $(newDiv), currDiv = $(target);
		if ( where == "after" ) {
			currDiv.append( addDiv );
		} else if ( where == "before" ) {
			currDiv.prepend( addDiv );
		} else {
			addDiv.insertBefore( currDiv );
		}
	};

// Wrap a div inside a newly formed div
	window.wrapDiv = function (target, newDiv, where) {
		newDiv = newDiv || "<div></div>";
		where = where || "outside";
		if ( where == "outside" ) {
			$(target).each(function() { $(this).wrap(newDiv); });
		} else {
			$(target).each(function() { $(this).wrapInner(newDiv); });
		}
	};	

// Wrap multiple divs inside a newly formed div
	window.wrapDivs = function (target, newDiv) {
		newDiv = newDiv || "<div />";
		$(target).wrapAll( newDiv );
	};	

// Remove parent of target div
	window.removeParent = function (target) {
		$(target).unwrap();
	};	

// Delete a div
	window.removeDiv = function (target) {
		$(target).remove();
	};		
	
// Turn SVG into an element's background image
	window.svgBG = function (svg, element, where) {
		var thisSVG = $(svg), thisEl = $(element);
		where = where || "top";
		if ( where == "bottom" ) {
			thisSVG.clone().css({"position":"absolute"}).appendTo(thisEl);
		} else if ( where == "top" ) {
			thisSVG.clone().css({"position":"absolute"}).prependTo(thisEl);
		} else if ( where == "before" || where == "start" ) {
			thisSVG.clone().css({"position":"absolute"}).insertBefore(thisEl);
		} else {
			thisSVG.clone().css({"position":"absolute"}).insertAfter(thisEl); 
		} 
	};
	
/*--------------------------------------------------------------
# Set up animation
--------------------------------------------------------------*/

// Animate single element (using transitions from animate.css)
	window.animateDiv = function(container, effect, initDelay, offset, speed) {
		initDelay = initDelay || 0;		
		offset = offset || "100%";
		speed = speed || 1000;
		speed = speed / 1000;
		$(container).addClass('animated');
		$(container).css({ "animation-duration": speed+"s"});
		$(container+".animated").waypoint(function() {
			var thisDiv = $(this.element);	
			setTimeout( function () { thisDiv.addClass(effect); }, initDelay);			
			this.destroy();
		}, { offset: offset });
	};

// Animate multiple elements
	window.animateDivs = function(container, effect1, effect2, initDelay, mainDelay, offset, speed) {	
		initDelay = initDelay || 0;		
		mainDelay = mainDelay || 100;		
		offset = offset || "100%";
		speed = speed || 1000;
		speed = speed / 1000;
		var theDelay = 0;
		var currEffect = effect1;
		var theParent = $(container).parent();
		var getDiv = container.split(' ');
		var theDiv = getDiv.pop();
		$(container).addClass('animated');
		setTimeout( function() {
			theParent.find(theDiv+".animated").waypoint(function() {
				var thisDiv = $(this.element);	
				var divIndex = thisDiv.prevAll(theDiv).length;
				thisDiv.css({ "animation-duration": speed+"s"});
				if ( divIndex > 6 ) {
					theDelay = mainDelay;	
				} else {
					theDelay = divIndex * mainDelay;	
				}
				if ( currEffect === effect2 ) { 			
					setTimeout( function () { thisDiv.addClass(effect1); }, theDelay);
					currEffect = effect1;
				} else {
					setTimeout( function () { thisDiv.addClass(effect2); }, theDelay);
					currEffect = effect2;
				}						
				this.destroy();
			}, { offset: offset });
		}, initDelay);

	};

// Animate grid elements
	window.animateGrid = function(container, effect1, effect2, effect3, initDelay, mainDelay, offset, mobile, speed) {
		initDelay = initDelay || 0;		
		mainDelay = mainDelay || 100;		
		offset = offset || "100%";
		mobile = mobile || "false";
		speed = speed || 1000;
		speed = speed / 1000;
		var theParent = $(container).parent();
		var getDiv = container.split(' ');
		var theDiv = getDiv.pop();
		$(container).addClass('animated');
		theParent.each(function() {
			var theRow = $(this);
			var findCol = theRow.find(theDiv+".animated").length;
			if (findCol == 1) { 
				theRow.find(theDiv+".animated").data("animation", { effect:effect1, delay:0});
			}
			if (findCol == 2) { 
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", { effect:effect2, delay:0});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", { effect:effect3, delay:1});
			}
			if (findCol == 3) { 
				theRow.find(theDiv+".animated:nth-last-child(3)").data("animation", { effect:effect2, delay:0});
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", { effect:effect1, delay:1});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", { effect:effect3, delay:2});
			}
			if (findCol == 4) { 
				theRow.find(theDiv+".animated:nth-last-child(4)").data("animation", { effect:effect2, delay:0});
				theRow.find(theDiv+".animated:nth-last-child(3)").data("animation", { effect:effect1, delay:1});
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", { effect:effect1, delay:2});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", { effect:effect3, delay:3});
			}		
			if (findCol == 5 || findCol == 6) { 
				theRow.find(theDiv+".animated:nth-last-child(6)").data("animation", { effect:effect1, delay:0});	
				theRow.find(theDiv+".animated:nth-last-child(5)").data("animation", { effect:effect1, delay:1});			
				theRow.find(theDiv+".animated:nth-last-child(4)").data("animation", { effect:effect1, delay:2});
				theRow.find(theDiv+".animated:nth-last-child(3)").data("animation", { effect:effect1, delay:3});
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", { effect:effect1, delay:4});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", { effect:effect1, delay:5});
			}	
			if (findCol == 7 || findCol == 8) { 
				theRow.find(theDiv+".animated:nth-last-child(8)").data("animation", { effect:effect1, delay:0});	
				theRow.find(theDiv+".animated:nth-last-child(7)").data("animation", { effect:effect1, delay:1});	
				theRow.find(theDiv+".animated:nth-last-child(6)").data("animation", { effect:effect1, delay:2});	
				theRow.find(theDiv+".animated:nth-last-child(5)").data("animation", { effect:effect1, delay:3});			
				theRow.find(theDiv+".animated:nth-last-child(4)").data("animation", { effect:effect1, delay:4});
				theRow.find(theDiv+".animated:nth-last-child(3)").data("animation", { effect:effect1, delay:5});
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", { effect:effect1, delay:6});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", { effect:effect1, delay:7});
			}
		});
		theParent.find(theDiv+".animated").waypoint(function() {
			var thisDiv = $(this.element);
			var delay = (mainDelay * thisDiv.data("animation").delay) + initDelay;
			var effect = thisDiv.data("animation").effect;
			thisDiv.css({ "animation-duration": speed+"s"});
			if ( getDeviceW() > mobileCutoff || mobile == "true" ) { 
				setTimeout( function () { thisDiv.addClass(effect); }, delay);
			} else {
				thisDiv.addClass("fadeInUpSmall");				
			}			
			this.destroy();
		}, { offset: offset });	
	};	

// Animate single element (using CSS transitions in site-style.css)
	window.animateCSS = function(container, initDelay, offset, speed) {	
		initDelay = initDelay || 0;		
		offset = offset || "100%";
		speed = speed || 1000;
		speed = speed / 1000;
		$(container).addClass('animate');
		$(container+".animate").waypoint(function() {
			var thisDiv = $(this.element);	
			thisDiv.css({ "transition-duration": speed+"s"});
			setTimeout( function () { thisDiv.removeClass('animate'); }, initDelay);		
			this.destroy();
		}, { offset: offset });
	};

// Animate the hover effect of a button, and allow to finish (even if mouse out)
	window.animateBtn = function(menu, notClass, animateClass, inOut) {	
		menu = menu || ".menu";		
		notClass = notClass || "li:not(.active)";
		inOut = inOut || 'both';

		var theEl = $(menu).find(notClass);
		animateClass = animateClass || "go-animated";
		theEl.bind("webkitAnimationEnd mozAnimationEnd msAnimationEnd oAnimationEnd animationEnd", function() { $(this).removeClass(animateClass); });
		
		if ( inOut == "in" ) {
			theEl.mouseenter(function() { $(this).addClass(animateClass); });	
		} else if ( inOut == "out" ) {
			theEl.mouseleave(function() { $(this).addClass(animateClass); });	
		} else {
			theEl.hover(function() { $(this).addClass(animateClass); });
		}
	};

// Split string into words for animation
	window.animateWords = function(container, effect1, effect2, initDelay, mainDelay, offset) {
		initDelay = initDelay || 0;		
		mainDelay = mainDelay || 100;		
		offset = offset || "100%";
		var theContainer = $(container);		
		theContainer.each(function() {				
			var myStr = $(this).html();
			myStr = myStr.split(" ");
			var myContents = "";			
			for (var i = 0, len = myStr.length; i < len; i++) {
				if ( i == len-1 ) {
					myContents += '<div class="wordSplit animated">' + myStr[i];
				} else {
					myContents += '<div class="wordSplit animated">' + myStr[i] + '&nbsp;</div>';
				}
			}
			$(this).html(myContents);	

			var charDelay = initDelay;
			var currEffect = effect1;
			theContainer.find(".wordSplit.animated" ).waypoint(function() {
				var thisDiv = $(this.element);
				charDelay = charDelay + mainDelay;
				if ( currEffect === effect2 ) { 
					setTimeout( function () { thisDiv.addClass(effect1); }, charDelay);
					currEffect = effect1;
				} else {
					setTimeout( function () { thisDiv.addClass(effect2); }, charDelay);
					currEffect = effect2;
				}
				this.destroy();
			}, { offset: offset });
		});
	};

// Split string into characters for animation
	window.animateCharacters = function(container, effect1, effect2, initDelay, mainDelay, offset) {	
		initDelay = initDelay || 0;		
		mainDelay = mainDelay || 100;		
		offset = offset || "100%";
		var theContainer = $(container);		
		theContainer.each(function() {				
			var myStr = $(this).html();
			myStr = myStr.split("");
			var myContents = "";			
			for (var i = 0, len = myStr.length; i < len; i++) {
				if ( myStr[i] === " " ) { myStr[i] = "&nbsp;"; }
				myContents += '<div class="charSplit animated">' + myStr[i] + '</div>';
			}
			$(this).html(myContents);	

			var charDelay = initDelay;
			var currEffect = effect1;
			theContainer.find(".charSplit.animated" ).waypoint(function() {
				var thisDiv = $(this.element);
				charDelay = charDelay + mainDelay;
				if ( currEffect === effect2 ) { 
					setTimeout( function () { thisDiv.addClass(effect1); }, charDelay);
					currEffect = effect1;
				} else {
					setTimeout( function () { thisDiv.addClass(effect2); }, charDelay);
					currEffect = effect2;
				}				
				this.destroy();
			}, { offset: offset });
		});
	};

/*--------------------------------------------------------------
# Set up pages
--------------------------------------------------------------*/

/* Needed temporarily to force columns to new framework naming structure 
	$('.col-8').addClass('span-1').removeClass('col-8');
	$('.col-17').addClass('span-2').removeClass('col-17');
	$('.col-20').addClass('span-2').removeClass('col-20');
	$('.col-25').addClass('span-3').removeClass('col-25');		
	$('.col-33').addClass('span-4').removeClass('col-33');
	$('.col-40').addClass('span-5').removeClass('col-40');
	$('.col-42').addClass('span-5').removeClass('col-42');
	$('.col-50').addClass('span-6').removeClass('col-50');
	$('.col-58').addClass('span-7').removeClass('col-58'); 
	$('.col-60').addClass('span-7').removeClass('col-60');
	$('.col-67').addClass('span-8').removeClass('col-67');
	$('.col-75').addClass('span-9').removeClass('col-75');
	$('.col-80').addClass('span-10').removeClass('col-80');
	$('.col-83').addClass('span-10').removeClass('col-83');
	$('.col-92').addClass('span-11').removeClass('col-92');
	$('.col-100').addClass('span-12').removeClass('col-100');*/


// Remove empty elements
	removeDiv('p:empty, .archive-intro:empty');

// Wrap content within .site-main so that widgets can be distributed properly
	wrapDiv('.site-main','<div class="site-main-inner"></div>', 'inside');	

// Add .page-begins to the next section under masthead for purposes of locking .top-strip
	if ( $('#wrapper-top').length ) { $('#wrapper-top').addClass('page-begins'); } else { $('#wrapper-content').addClass('page-begins'); }

// Add "noFX" class to img if it appears in any of the parent divs
	$( "div.noFX" ).find("img").addClass("noFX");
	$( "div.noFX" ).find("a").addClass("noFX");

// Add .fa class to all icons using .far, .fas and .fab
	$( ".far, .fas, .fab" ).addClass("fa");

// Fade in lazy loaded images
	$('img').addClass('unloaded');	
	$('img').one('load', function() { 
		$(this).removeClass('unloaded'); 
	}).each(function() { 
		if (this.complete) { 
			$(this).trigger('load'); 
		}
	});	
	
// Check if "Remove Sidebar" option is checked in admin panel, and remove sidebar if applicable	
if ( $('body').hasClass('remove-sidebar') ) { 
	$('body').removeClass('sidebar-line').removeClass('sidebar-box').removeClass('widget-box').removeClass('sidebar-right').removeClass('sidebar-left').addClass('sidebar-none'); 
	removeDiv('#secondary');
}	

// Preload BG image and fade in
	if ( $( 'body' ).hasClass( "background-image" ) && getDeviceW() > mobileCutoff ) {
		var preloadBG = new Image();
		preloadBG.onload = function() { animateDiv( '.parallax-mirror', 'fadeIn', 0, '', 200 ); };		
		preloadBG.onerror = function() { console.log("site-background.jpg not found"); };
		preloadBG.src = getUploadURI + "/" + "site-background.jpg";  
	}

// Add "active" & "hover" classes to menu items, assign roles for ADA compliance		
	$(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr('role','menubar');
	$(".main-navigation li, .widget-navigation li").attr('role','none');
	$(".main-navigation a, .widget-navigation a").attr('role','menuitem');
	$(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr('role','menu');

	var	$currents = $(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor"); 
	$currents.addClass( "active" );
	$currents.find(">a").attr('aria-current','page');
	$(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li").hover ( function() { 		
		$currents.replaceClass( "active", "dormant" ); 
		$(this).addClass( "hover" );  
	}, function() {  
		$(this).removeClass("hover");
		$currents.replaceClass( "dormant", "active" ); 
	});		

	var	$subCurrents = $(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor"); 
	$subCurrents.addClass( "active" );
	$subCurrents.find(">a").attr('aria-current','page');
	$(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li").hover ( function() { 		
		$subCurrents.replaceClass( "active", "dormant" ); 
		$(this).addClass( "hover" );  
	}, function() {  
		$(this).removeClass("hover");
		$subCurrents.replaceClass( "dormant", "active" ); 
	});

// Automatically adjust for Google review bar 
	$( '<div class="wp-google-badge-faux"></div>' ).insertAfter( $('#colophon'));

// Animate scrolling when moving up or down a page
	$('a[href^="#"]:not(.carousel-control-next):not(.carousel-control-prev)').on('click', function (e) {
		e.preventDefault();    
		var target = this.hash, compensate = Number($(target).attr('data-hash'));
		if( isNaN(compensate) ) { compensate = 0; } 
		if ( target != "" ) { 
			 if ( $('*'+target).length ) { 
				animateScroll(target, compensate); 
				setTimeout(function(){ animateScroll(target, compensate); }, 100); /* helps re-calculate in case there is a .stuck to account for (Executive Mobile Detailing) */
			 } else {
				 window.location.href = "/"+target;
			}
		}
	});
	
// Control Menu Buttons on "one page" site		
	if ( $('.menu-item:not(.no-highlight) a[href^="#"]').is(':visible') ) { 
		var menu = $('nav:visible').find('ul'), whenToChange = $(window).outerHeight() * 0.35, menuHeight = menu.outerHeight()+whenToChange, menuItems = menu.find('a[href^="#"]'), scrollItems = menuItems.map(function(){ var item = $($(this).attr("href")); if ( $(this).parent().css('display') != "none" ) { return item; } });
		
		$(window).scroll(function() { 
			var fromTop = $(this).scrollTop()+menuHeight, thisHash, changeMenu; 
			var cur = scrollItems.map(function() {  
				if ( $(this).offset() !== undefined ) {
					if ( $(this).offset().top < fromTop ) { 
						thisHash = "#"+$(this)[0].id;
						clearTimeout(changeMenu);
						changeMenu = setTimeout(function(){ 
							menu.find('li').removeClass('current-menu-item').removeClass('current_page_item').removeClass('active');
							menu.find('a[href^="'+thisHash+'"]').closest('li').addClass('current-menu-item').addClass('current_page_item').addClass('active'); 
						}, 10);	
						closeMenu();// auto close mobile menu
					}
				}
			});
		});
	};
	
// Set up mobile menu animation
	window.closeMenu = function () {
		$("#mobile-menu-bar .activate-btn").removeClass("active"); 
		$("body").removeClass("mm-active"); 
		$(".top-push.screen-mobile #page").css({ "top": "0" });
		$(".top-push.screen-mobile .top-strip.stuck").css({ "top": mobileMenuBarH+"px" });
	};

	window.openMenu = function () {
		$("#mobile-menu-bar .activate-btn").addClass("active"); 
		$("body").addClass("mm-active"); 
		var getMenuH = $("#mobile-navigation").outerHeight();
		var getTotalH = getMenuH + mobileMenuBarH;
		$(".top-push.screen-mobile.mm-active #page").css({ "top": getMenuH+"px" });	
		$(".top-push.screen-mobile.mm-active .top-strip.stuck").css({ "top": getTotalH+"px" });
	};

	$("#mobile-menu-bar .activate-btn").click(function() {
		if ( $(this).hasClass("active")) { closeMenu();	} else { openMenu(); }
	}); 	

	window.closeSubMenu = function (el) {
		$(el).removeClass("active"); 
		$(el).height(0);
	};

	window.openSubMenu = function (el, h) {
		$('#mobile-navigation ul.sub-menu').removeClass("active"); 

		$('#mobile-navigation ul.sub-menu').height(0);
		$(el).addClass("active"); 
		$(el).height(h+"px");
	};

	$('#mobile-navigation').addClass("get-sub-heights");

	$('#mobile-navigation ul.sub-menu').each(function() { 
		var theSub = $(this), getSubH = theSub.outerHeight(true);
		theSub.data('getH', getSubH );	
		theSub.parent().click(function() {
			if ( theSub.hasClass("active")) { closeSubMenu(theSub); } else { openSubMenu(theSub, theSub.data('getH')); }
		}); 
		closeSubMenu(theSub); 		
	});	

	$('#mobile-navigation').removeClass("get-sub-heights");

	$('#mobile-navigation li:not(.menu-item-has-children)').each(function() { 
		var theButton = $(this);
		theButton.click(function() { closeMenu(); }); 
	});	

// Ensure all slides in a Bootstrap carousel are even height
	$(".carousel").each(function() {
		var thisCarousel = $(this), maxH = 0, getPadding = parseInt(thisCarousel.find(".carousel-inner").css('padding-bottom'));
		thisCarousel.data("maxH", 0);

		thisCarousel.on('slid.bs.carousel', function() {
			var thisSlideH = thisCarousel.find(".carousel-item.active").outerHeight() + getPadding;
			if ( thisSlideH > maxH ) { 
				thisCarousel.find(".carousel-inner").css("height",thisSlideH+"px");	
				maxH = thisSlideH;
			}
		});		
	});			

// Add star icons to reviews and ratings
	$('.testimonials-rating').each(function() {
		var getRating = $(this).html(), replaceRating = getRating;
		if ( getRating == 5) replaceRating = '<span class="rating rating-5-star" aria-hidden="true"><span class="sr-only">Rated 5 Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i></span>';
		if ( getRating == 4) replaceRating = '<span class="rating rating-4-star" aria-hidden="true"><span class="sr-only">Rated 4 Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa far fa-star"></i></span>';
		if ( getRating == 3) replaceRating = '<span class="rating rating-3-star" aria-hidden="true"><span class="sr-only">Rated 3 Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa far fa-star"></i><i class="fa far fa-star"></i></span>';
		if ( getRating == 2) replaceRating = '<span class="rating rating-2-star" aria-hidden="true"><span class="sr-only">Rated 2 Stars</span><i class="fa fas fa-star"></i><i class="fa fas fa-star"></i><i class="fa far fa-star"></i><i class="fa far fa-star"></i><i class="fa far fa-star"></i></span>';
		if ( getRating == 1) replaceRating = '<span class="rating rating-1-star" aria-hidden="true"><span class="sr-only">Rated 1 Star</span><i class="fa fas fa-star"></i><i class="fa far fa-star"></i><i class="fa far fa-star"></i><i class="fa far fa-star"></i><i class="fa far fa-star"></i></span>';
		$(this).html( replaceRating );
	});

// Determine which day of week and add active class on office-hours widget	
	var todayIs = new Date().getDay();
	if ( todayIs == 0 ) $(".office-hours .row-sun").addClass("today");
	if ( todayIs == 1 ) $(".office-hours .row-mon").addClass("today");
	if ( todayIs == 2 ) $(".office-hours .row-tue").addClass("today");
	if ( todayIs == 3 ) $(".office-hours .row-wed").addClass("today");
	if ( todayIs == 4 ) $(".office-hours .row-thu").addClass("today");
	if ( todayIs == 5 ) $(".office-hours .row-fri").addClass("today");
	if ( todayIs == 6 ) $(".office-hours .row-sat").addClass("today");	
					
// Removes double asterisk in required forms
	$('abbr.required, em.required, span.required').text("");
	setTimeout( function () { $('abbr.required, em.required, span.required').text(""); }, 2000);

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
			if ( getDeviceW() > mobileCutoff ) { 
				$('#secondary').css({ "height":"calc(100% + "+compensate+"px)" });
				$('.widget:not(.widget-important)').addClass('hide-widget');
				addWidgets();				
			} 
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
			if ( sidebarScroll == "true" && getDeviceW() > mobileCutoff ) {
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

/*--------------------------------------------------------------
# Screen resize
--------------------------------------------------------------*/

	$(window).on( 'load', function() { screenResize(true); });
	$(window).resize(function() { screenResize(true); }); 

	window.screenResize = function (widgets) {		
		widgets = widgets || false;

		setTimeout( function () {
		// Ensure Parallax elements and widgets are re-assessed on desktop
			if ( getDeviceW() > mobileCutoff ) { 
				$(window).trigger('resize.px.parallax');
				if ( widgets == true ) { widgetInit(); } 
			} 

		// If not a mobile device and sidebar exists, move widgets with scroll	
			if ( $("#secondary").length && getDeviceW() > mobileCutoff ) {  	
				window.addEventListener('scroll', moveWidgets );
			} else {
				window.removeEventListener('scroll', moveWidgets );
			}							
		}, 300); // 200 is shortest time for widgets to work

	// Add class to body to determine which size screen is being viewed
		$('body').removeClass("screen-5 screen-4 screen-3 screen-2 screen-1 screen-mobile screen-desktop");
		if ( getDeviceW() > 1280 ) { $('body').addClass("screen-5").addClass("screen-desktop"); }	
		if ( getDeviceW() <= 1280 && getDeviceW() > mobileCutoff ) { $('body').addClass("screen-4").addClass("screen-desktop"); }
		if ( getDeviceW() <= mobileCutoff && getDeviceW() > 860 ) { $('body').addClass("screen-3").addClass("screen-mobile"); }
		if ( getDeviceW() <= 860 && getDeviceW() > 576 ) { $('body').addClass("screen-2").addClass("screen-mobile"); }
		if ( getDeviceW() <= 576 ) { $('body').addClass("screen-1").addClass("screen-mobile"); }

	// Disable href on mobile menu items with children
		$(".screen-mobile li.menu-item-has-children").children("a").attr("href", "javascript:void(0)");

	// Center the sub-menu ul under the parent li on non-mobile
		$('.main-navigation ul.sub-menu').each(function() {	
			var subW = $(this).outerWidth(true), parentW = $(this).parent().width(), moveL = - Math.round((subW - parentW) / 2);
			$(this).css({ "left":moveL+"px" });			
		});

	// Close any open menus on mobile (when device ratio changes)
		closeMenu();

	// Ensure "-faux" elements remain correct size
		$('div[class*="-faux"]').each(function() {	
			var fauxDiv = $(this);
			var fauxClass = "."+fauxDiv.attr('class');
			var mainClass = fauxClass.replace("-faux", "");
			
			if ( !$(mainClass).length ) {
				var mainID = "#"+fauxDiv.attr('class');
				mainClass = mainID.replace("-faux", "");
			} 

			if ( $( mainClass ).is(":visible") ) {		
				$( fauxClass ).height($( mainClass ).outerHeight());  
				$( '.wp-google-badge-faux' ).height($( '.wp-google-badge' ).outerHeight());  
			} else {			
				$( fauxClass ).height(0); 
			}						
		});		

		// Shift #secondary below #wrapper-bottom on mobile		
		moveDiv('.sidebar-shift.screen-mobile #secondary','#colophon',"before");	
	};

/*--------------------------------------------------------------
# ADA compliance
--------------------------------------------------------------*/
	
	// Add aria-labels to landmarks, sections and titles
	$('#primary').attr( 'role', 'main' ).attr( 'aria-label', 'main content' );
	$('#secondary').attr( 'role', 'complementary' ).attr( 'aria-label', 'sidebar' );	
	$('h3 a[aria-hidden="true"]').each(function() { $(this).parent().attr( 'aria-label', $(this).text()); });
	
	$('form.hide-labels input, form.hide-labels textarea').each(function() { $(this).attr('title', $(this).closest('p').find('label').text()) });
	
	$('span.required').attr("aria-hidden", true).after('<span class="sr-only">Required Field</span>');

	// Add alt="" to all images with no alt tag
	setTimeout(function() { $('img:not([alt])').attr('alt', ''); }, 50);
	setTimeout(function() { $('img:not([alt])').attr('alt', ''); }, 1000);

	// Add special focus outline when someone is using tab to navigate site
	$(document).mousemove(function(event) { $('body').addClass('using-mouse').removeClass('using-keyboard'); });
	$(document).keydown(function(e) { if( e.keyCode == 9 && !$('body').hasClass("using-mouse") ) { $('body').addClass('using-keyboard'); } });

	// Menu support
	$('[role="menubar"]' ).on( 'focus.aria mouseenter.aria', '[aria-haspopup="true"]', function ( ev ) { $( ev.currentTarget ).attr( 'aria-expanded', true ); } );
	$('[role="menubar"]' ).on( 'blur.aria mouseleave.aria', '[aria-haspopup="true"]', function ( ev ) { $( ev.currentTarget ).attr( 'aria-expanded', false ); } );	
	$('a[role="menuitem"]' ).attr( 'tabindex', '0' );
	$('li[aria-haspopup="true"]').attr( 'tabindex', '-1' );

	// Remove iframe from tab order
	$('iframe').each(function(){
		$(this).attr("aria-hidden", true).attr("tabindex","-1");		
	})

	// Make hidden labels accessible to screen reader
	$('form.hide-labels label:not(.show-label)').addClass('sr-only');	

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
	
/*--------------------------------------------------------------
# Delay parsing of JavaScript
--------------------------------------------------------------*/

	$(window).on( 'load', function() {

	// Calculate load time for page		
		var endTime = Date.now(); 	
		var loadTime = ((endTime - startTime) / 1000).toFixed(1);	
		var deviceTime = "desktop";
		if ( getDeviceW() <= mobileCutoff ) { deviceTime = "mobile"; }	

	// Fade out loader screen when site is fully loaded
		$("#loader").fadeOut("fast");  		 
 
	// Get video link from data-src and feed to src 
		var vidDefer = document.getElementsByTagName('iframe');
		for (var i=0; i<vidDefer.length; i++) {
			if(vidDefer[i].getAttribute('data-src')) {
				vidDefer[i].setAttribute('src',vidDefer[i].getAttribute('data-src'));
			}
		}
				
	// Handle parallax sections (with content) on mobile devices
		var countParallax = 1;
		$('.section-parallax .flex').each(function() {
			var thisSection = $(this).closest('.section-parallax'), thisSectionBG = thisSection.attr('data-image-src'), parallaxClass = 'parallax-'+countParallax;			
			thisSection.addClass('mobile-full');
			
			setTimeout(function() { 				
				$('img.parallax-slider').each(function() {
					if ( $(this).attr('src') == thisSectionBG ) {
						$(this).parent().addClass('mobile-hide');						
					}
				});		
			}, 100); 
						
			$('<style>html.'+parallaxClass+'::before{background-image:url('+thisSectionBG+')}</style>').appendTo('head');		
			
			thisSection.waypoint(function(direction) {				
				if (direction === 'down') {			
					$('html').addClass(parallaxClass);
				} else {
					$('html').removeClass(parallaxClass);
				}					

			}, { offset: '100%' });	 		
			
			thisSection.waypoint(function(direction) {
				if (direction === 'down') {			
					$('html').removeClass(parallaxClass);
				} else {
					$('html').addClass(parallaxClass);
				}					
			}, { offset: function() { return -$(this.element).outerHeight() } });	
			
			countParallax++;
		}); 
				
	// Set up Locked Message position, delay, & cookie	
		$('section.section-lock').each(function() {
			var thisLock = $(this), initDelay = thisLock.attr('data-delay'), lockPos = thisLock.attr('data-pos'), cookieExpire = thisLock.attr('data-show'), rowH = thisLock.outerHeight() + 100, buttonActivated = "no";

			if ( cookieExpire == "always" ) { cookieExpire = 0.000001; }
			if ( cookieExpire == "never" ) { cookieExpire = 100000; }			
			if ( cookieExpire == "session" ) { cookieExpire = null; }			

			addDiv(thisLock.find(".flex"), '<div class="closeBtn" aria-label="close" aria-hidden="false" tabindex="0"><i class="fa fa-times"></i></div>','before');
			
			thisLock.find('.closeBtn').keyup(function(event) {
				if (event.keyCode === 13 || event.keyCode === 32) {
					$(this).click();
				}
			});
			
			if ( lockPos == "top" ) {		
				thisLock.css( "top",-rowH+"px");	
				if ( getCookie("display-message") !== "no" ) {
					thisLock.delay(initDelay).animate({ "top":mobileMenuBarH+"px" }, 600);
					thisLock.find('.closeBtn').click(function() {
						thisLock.animate({ "top":-rowH+"px" }, 600);
						setCookie("display-message","no",cookieExpire);
					});
				}
			} else if ( lockPos == "bottom" ) { 	
				thisLock.css( "bottom",-rowH+"px");	
				if ( getCookie("display-message") !== "no" ) {
					thisLock.delay(initDelay).animate({ "bottom":"0" }, 600);
					thisLock.find('.closeBtn').click(function() {
						thisLock.animate({ "bottom":-rowH+"px" }, 600);
						setCookie("display-message","no",cookieExpire);
					});
				}
			} else { 				
				thisLock.css({"opacity":0}).fadeOut();				
				if ( buttonActivated == "no" && getCookie("display-message") !== "no" ) {
					setTimeout(function() { thisLock.css({"opacity":1}).fadeIn(); }, initDelay);
					thisLock.find('.closeBtn').click(function() {
						thisLock.fadeOut();
						setCookie("display-message","no",cookieExpire);
					});	
				}
				if ( buttonActivated == "yes" ) {				
					$('.modal-btn').click(function() {
						thisLock.css({"opacity":1}).fadeIn();
					});
					thisLock.find('.closeBtn').click(function() {
						thisLock.fadeOut();
					});						
				}
			}			
			thisLock.click(function() {
				setCookie("display-message","no",cookieExpire);
			});			
		});

		setTimeout(function() {	// Wait 1 second before calling the following functions 

		// Generic page setup functions (if not overriden in script-site.js)
			trimText();
			buildAccordion();

		// Get IP data
			$.getJSON('https://ipapi.co/json/', function(data) {
				timezone = data["timezone"];				
				userLoc = data["city"] + ", " + data["region_code"];
			});
			userRefer = encodeURI(document.referrer); 
		}, 1000);

		setTimeout(function() {	// Wait 2 seconds before calling the following functions 	
		// Count page view 
			var postID = $('body').attr('id'), pageViews = $('body').attr('data-pageviews'), uniqueID = $('body').attr('data-unique-id');
			$.post({
				url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
				data : { action: "count_post_views", id: postID, timezone: timezone, userLoc: userLoc, pagesViewed: pageViews, uniqueID: uniqueID },
				success: function( response ) { console.log(response); } 
			});	
		// Count site view 
			$.post({
				url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
				data : { action: "count_site_views", timezone: timezone, userLoc: userLoc, userRefer: userRefer },
				success: function( response ) { console.log(response); } 
			});	

			// Log page load speed
			if ( loadTime > 0.1 && loadTime < 10.0 ) { 				
				$.post({
					url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',

					data : { action: "log_page_load_speed", id: postID, timezone: timezone, loadTime: loadTime, deviceTime: deviceTime, userLoc: userLoc },
					success: function( response ) { console.log(response); } 
				});	
			}

		// Count random post widget, testimonial & images - teases & views
			$('.carousel img.img-slider, #primary .testimonials-name, .widget:not(.hide-widget) .testimonials-name, #wrapper-bottom .testimonials-name, #primary img.random-img, .widget:not(.hide-widget) img.random-img, #wrapper-bottom img.random-img, #primary h3, .widget:not(.hide-widget) h3, #wrapper-bottom h3').waypoint(function() {		
				var theID = $(this.element).attr('data-id');
				var countTease = $(this.element).attr('data-count-tease');				
				var countView = $(this.element).attr('data-count-view');
				if ( countTease == "true" ) {
					$.post({
						url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
						data : { action: "count_teaser_views", id: theID, timezone: timezone, userLoc: userLoc },
						success: function( response ) { console.log(response); } 
					});		
				}
				if ( countView == "true" ) {
					$.post({
						url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
						data : { action: "count_post_views", id: theID, timezone: timezone, userLoc: userLoc },
						success: function( response ) { console.log(response); } 
					});		
				}
				this.destroy();
			}, { offset: 'bottom-in-view' });	 	

		}, 2000);
	});
	
})(jQuery); });