jQuery(function($) { try {
	
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
# If page load fails

--------------------------------------------------------------*/
	
	
/*--------------------------------------------------------------
# Basic site functionality
--------------------------------------------------------------*/
	
	var getThemeURI = theme_dir.theme_dir_uri, getUploadURI = theme_dir.upload_dir_uri, mobileCutoff = 1024, tabletCutoff = 576, mobileMenuBarH = 42, failCheck;
	
failCheck="Basic site functionality";
	
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
	
// Set up Logo to link to home page	
	$('.logo').css("cursor","pointer");
	$('.logo').click(function() {
  		window.location = "/";
	});
	window.linkHome = function (container) {
		$(container).css( "cursor", "pointer" );
		$(container).click(function() { window.location = "/"; });
	};		
	
// Track phone number clicks in Google Analytics
	window.trackClicks = function(type, category, action, url) {
		gtag('event', type, { 'event_category': category, 'event_action': action, 'event_label': url, 'transport_type': 'beacon', 'event_callback': function(){document.location = url;} });
	};
	
// Track contact form submissions in Google Analytics
	document.addEventListener( 'wpcf7mailsent', function( event ) {
		gtag('event', 'contact', { 'event_category': 'Contact', 'event_action': 'Email' });
	}, false );
	
// Set up Cookies
	window.setCookie = function(cname,cvalue,exdays) {
		var d = new Date();
		d.setTime(d.getTime()+(exdays*24*60*60*1000));
		var expires = "expires="+d.toGMTString();
		document.cookie = cname + "=" + cvalue + "; " + expires + "; path=/; secure";
	};
	window.getCookie = function(cname) {
		var name = cname + "=", ca = document.cookie.split(';');
		for(var i=0; i<ca.length; i++) {
			var c = ca[i].trim();
			if (c.indexOf(name)==0) return c.substring(name.length,c.length);
		}
		return "";
	};	
	
// Is user on an Apple device?
	window.isApple = function () {
		var iOS = !!navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform);
		return iOS;		
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
	
// Extend .hasClass to .hasPartialClass
	$.fn.hasPartialClass = function(partial){
		return new RegExp(partial).test(this.prop('class')); 
	};
	
// Find width of screen
	window.getDeviceW = function () {
		var deviceWidth = window.screen.width;			
		if ( isApple() && (window.orientation == 90 || window.orientation == -90 )) {
			deviceWidth = window.screen.height;
		}
		return deviceWidth;
	};		
	
// Find height of screen
	window.getDeviceH = function () {
		var deviceHeight = window.screen.height;			
		if ( isApple() && (window.orientation == 90 || window.orientation == -90 )) {
			deviceHeight = window.screen.width;
		}
		return deviceHeight;
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
		
// Find & Replace text or html in a Div	
	window.replaceText = function (container, find, replace, type) {
		type = type || "text";	
		if ( type == "text" ) { 
			$(container).text(function () {
				var thisText = $(this).text();
				return thisText.replace(find,replace); 
			});
		} else {
			$(container).html(function () {
				var thisHtml = $(this).html();
				return thisHtml.replace(find,replace); 
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
		page = "slug-"+page.replace(/\//g, "");
		if ( $('body').hasClass(page) ) { $('body').removeClass('sidebar-right').removeClass('sidebar-left').addClass('sidebar-none'); }
	};
	
// Create faux div for sticky elements pulled out of document flow	
	window.addStuck = function (element, faux) {
		faux = faux || "true";	
		if ( $(element).is(":visible") ) {						
			$(element).addClass("stuck");	
			if ( faux == "true" ) { addFaux(element); }
		}
	};
	
	window.removeStuck = function (element, faux) {
		faux = faux || "true";
		$(element).removeClass("stuck");
		if ( faux == "true" ) { removeFaux(element); }
	};
	
	window.addFaux = function (element) {
		var theEl = $(element);
		var elementName = element.substr(1);		
		if ( theEl.is(":visible") ) {		
			$( "<div class='"+elementName+"-faux'></div>" ).insertBefore( theEl );
			var theFaux = $("."+elementName+"-faux");
			theFaux.animate({ "height":theEl.outerHeight()+"px" }, 0);
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

			if ( $("#mobile-menu-bar").is(":visible") ) {	
				newTop = newTop + mobileMenuBarH;
			}
			
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
	
// Animate the automated scrolling to section of content
	window.animateScroll = function (target, topSpacer, initSpeed) {
		var newTop=0, newLoc=0;
		initSpeed = initSpeed || 0;
		topSpacer = topSpacer || 0;
		
		if ( $("#mobile-menu-bar").is(":visible") ) {	
			topSpacer = topSpacer + mobileMenuBarH;	
		}
				
		$('.stuck').each(function() {
			newTop = newTop + $(this).outerHeight();				
		});		
		
		if ( typeof target === 'object' || typeof target === 'string' ) {		
			newLoc = $(target).offset().top - newTop - topSpacer;	
		} else {
			newLoc = target - newTop - topSpacer;	
		}
		
		if ( initSpeed == 0 ) { 
			transSpeed = Math.abs(( $(window).scrollTop() - newLoc )) / 3;
			if ( transSpeed < 500 ) { transSpeed = 500; }
			if ( transSpeed > 1500 ) { transSpeed = 1500; }
		} else {
			transSpeed = initSpeed;
		}
		$('html, body').stop().animate({ 'scrollTop': newLoc }, transSpeed, 'swing', function() { screenResize(); });
	};

// Set up "Back To Top" button
	var waypoints = $('#wrapper-content').waypoint(function(direction) {
		if (direction === 'up') {			
			$('a.scroll-top').animate( { opacity: 0 }, 150, function() { $('a.scroll-top').css({ "display": "none" }); });
		} else {
			$('a.scroll-top').css({ "display": "block" }).animate( { opacity: 1 }, 150);
		}	
	}, { offset: '10%' });	
		
// Set up "Scroll Down" button
	var waypoints = $('#wrapper-content').waypoint(function(direction) {
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
	
// Accordion section - control opening & closing of expandable text boxes
	window.buildAccordion = function (topSpacer, cssDelay, transSpeed, closeDelay, openDelay, clickActive) {
		if (buildAccordion.done) return;
		transSpeed = transSpeed || 500;
		closeDelay = closeDelay || 0;
		openDelay = openDelay || 0;
		cssDelay = cssDelay || closeDelay + openDelay;
		topSpacer = topSpacer || 0.1;
		clickActive = clickActive || 'close';
		var fullDelay = cssDelay+closeDelay+openDelay+transSpeed, useThis, accPos = [];
		
		if ( topSpacer < 1 ) { topSpacer = getDeviceH() * topSpacer; }		
		if ( getDeviceW() < mobileCutoff ) { topSpacer = topSpacer + mobileMenuBarH; }		
				
		$('.block-accordion').attr( 'aria-expanded', false );
		$('.block-accordion').first().addClass('accordion-first');
		$('.block-accordion').last().addClass('accordion-last');
		if ( $('.block-accordion').parents('.col-archive').length) {
			$('.block-accordion').parents('.col-archive').addClass('archive-accordion');
			$('.archive-accordion').each(function() { accPos.push($(this).position().top); });			
		} else {
			$('.block-accordion').each(function() { accPos.push($(this).position().top); });			
		}
	
		$(".block-accordion").keyup(function(event) {
			if (event.keyCode === 13 || event.keyCode === 32) {
				$(this).click();
			}
		});
			
		$(".block-accordion").click(function(e) {		
			e.preventDefault();  
			var locAcc = $(this), locIndex = locAcc.index('.block-accordion'), locPos = accPos[locIndex], topPos = accPos[0];			
			if ( !locAcc.hasClass("active") ) {
				setTimeout( function () {
					$( '.block-accordion.active .accordion-excerpt, .block-accordion.active .accordion-content' ).animate({ height: "toggle", opacity: "toggle" }, transSpeed);
					if ( locAcc.find('.accordion-excerpt').length ) {
						locAcc.find('.accordion-excerpt').animate({ height: "toggle", opacity: "toggle" }, transSpeed);
					}
				}, closeDelay);
				setTimeout( function () {
					locAcc.find('.accordion-content').animate({ height: "toggle", opacity: "toggle" }, transSpeed);	
					if ( (locPos - topPos) > (getDeviceH() * 0.25) ) {
						animateScroll(locPos, topSpacer, transSpeed); 
					} else {
						animateScroll(topPos, topSpacer, transSpeed); 
					}		
				}, openDelay);
				setTimeout( function() {						
					$(".block-accordion.active").removeClass('active').attr( 'aria-expanded', false ); 
					locAcc.addClass('active').attr( 'aria-expanded', true );
				}, cssDelay);
			} else if ( clickActive == 'close' ) {
				setTimeout( function () {
					$( '.block-accordion.active .accordion-excerpt, .block-accordion.active .accordion-content' ).animate({ height: "toggle", opacity: "toggle" }, transSpeed);
					if ( locAcc.find('.accordion-excerpt').length ) {
						locAcc.find('.accordion-excerpt').animate({ height: "toggle", opacity: "toggle" }, transSpeed);
					}
				}, closeDelay);	
				setTimeout( function() {						
					$(".block-accordion.active").removeClass('active').attr( 'aria-expanded', false );
				}, cssDelay);	
			}
			if ( getDeviceW() > mobileCutoff ) { setTimeout( function() { adjustSidebarH(); }, fullDelay); }		
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
	
// Set up "Magic Menu"	
	window.magicMenu = function (menu, linkOn, linkOff, speed) {
		menu = menu || "#desktop-navigation .menu";
		linkOn = linkOn || "active";
		linkOff = linkOff || "non-active";
		speed = speed || 200;
		
		var $el, $currentPage, leftPos, topPos, newWidth, newHeight, magicTop, magicLeft, magicWidth, magicHeight, startOpacity, $mainNav = $(menu);
		$mainNav.parent().parent().prepend("<div id='magic-line'></div>");
		var $magicLine = $("#magic-line");		
		if ( !$mainNav.find("li.current-menu-parent").length ) {		
			$currentPage = $mainNav.find("li.current-menu-item");
		} else { 
			$currentPage = $mainNav.find("li.current-menu-parent");
		}				
		
		if ( $currentPage.hasClass("mobile-only") ) { $currentPage.remove(); }	
		$currentPage.find(">a").addClass(linkOn);		
		
		function checkTop() {
			setTimeout( function (){ 
				if ( !$currentPage.length ) {  
					startOpacity = 0;
					$el = $mainNav.find(">li"); 
					magicTop = $el.position().top; 
					if ( magicTop < 5 ) { magicTop = $mainNav.parent().parent().position().top; }
					magicHeight = $el.height();
					$magicLine.stop().animate({ opacity: startOpacity, top: magicTop, left: magicLeft, height: magicHeight }, 0).data("origTop", magicTop).data("origLeft", magicLeft).data("origWidth", magicWidth).data("origHeight", magicHeight);	
				} else {  
					startOpacity = 1;
					$currentPage.each(function() {					
						$el = $(this); 
						magicTop = $el.position().top; 
						if ( magicTop < 5 ) { magicTop = $mainNav.parent().parent().position().top; }
						magicLeft = $el.position().left + (($el.outerWidth() - $el.width()) / 2); 
						magicWidth = $el.width(); 
						magicHeight = $el.height();
						$magicLine.stop().animate({ opacity: startOpacity, top: magicTop, left: magicLeft, width: magicWidth, height: magicHeight }, 0).data("origTop", magicTop).data("origLeft", magicLeft).data("origWidth", magicWidth).data("origHeight", magicHeight);					
					});	
				}
			}, 10);
		}

		setTimeout(function(){checkTop();},10);
		window.addEventListener('scroll', checkTop );
		
		$mainNav.find(" > li").hover(function() {			
			$el = $(this); 
			topPos = $el.position().top; 
			if ( topPos < 5 ) { topPos = $mainNav.parent().parent().position().top; }
			leftPos = $el.position().left + (($el.outerWidth() - $el.width()) / 2); 
			newWidth = $el.width(); 
			newHeight = $el.height();
			$magicLine.stop().animate({ opacity: 1, top: topPos, left: leftPos, width: newWidth, height: newHeight }, speed);
			$currentPage.find(">a").removeClass(linkOn).addClass(linkOff);
			$el.find(">a").addClass(linkOn).removeClass(linkOff);
		}, function() {
			$magicLine.stop().animate({ opacity: startOpacity, top:$magicLine.data("origTop"), left: $magicLine.data("origLeft"), width: $magicLine.data("origWidth"), height: $magicLine.data("origHeight") }, speed);    
			$el.find(">a").removeClass(linkOn).addClass(linkOff);
			$currentPage.find(">a").addClass(linkOn).removeClass(linkOff);
		});
	};		
	
// Duplicate menu button text onto the button BG
	$( ".main-navigation ul.main-menu li > a").each(function() { 
		var btnText = $(this).html();			
		$(this).parent().attr('data-content', btnText);
	});
			
		
/*--------------------------------------------------------------
# DOM level functions
--------------------------------------------------------------*/
	
failCheck="DOM level functions";
	
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
		} else {
			thisDiv.clone().insertBefore( thisAnchor );
		}
	};	
	
// Move a single div to another location
	window.moveDiv = function (moveThis, anchor, where) {
		where = where || "after";
		var thisDiv = $(moveThis), thisAnchor = $(anchor);
		if ( where == "after" ) {
			thisDiv.insertAfter( thisAnchor );
		} else {
			thisDiv.insertBefore( thisAnchor );
		}
	};
	
// Move multiple divs to another location
	window.moveDivs = function (wrapper, moveThis, anchor, where) {
		where = where || "after";
		$(wrapper).each(function() {	
			var thisDiv = $(this);			
			if ( where == "after" ) {
				thisDiv.find( $( moveThis )).insertAfter( thisDiv.find( anchor ) );
			} else {
				thisDiv.find( $( moveThis )).insertBefore( thisDiv.find( anchor ) );
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
		} else {
			currDiv.prepend( addDiv );
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

// Delete a div
	window.removeDiv = function (target) {
		$(target).remove();
	};	
	
		
/*--------------------------------------------------------------
# Set up animation
--------------------------------------------------------------*/
	
failCheck="Set up animation";
	
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
				var divIndex = thisDiv.prevAll(theDiv).size();
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
	
// Animate single element (using CSS transitions in go-style.css)
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
	window.animateBtn = function(menu, notClass, animateClass) {	
		menu = menu || ".menu";		
		notClass = notClass || "li:not(.active)";	
		var theEl = $(menu).find(notClass);
		animateClass = animateClass || "go-animated";
		theEl.bind("webkitAnimationEnd mozAnimationEnd animationend", function() { $(this).removeClass(animateClass); });
		theEl.hover(function() { $(this).addClass(animateClass); });	
	};
	
	
/*--------------------------------------------------------------
# Set up pages
--------------------------------------------------------------*/
	
failCheck="Set up pages: change col- to span-";
	
// Needed temporarily to force columns to new framework naming structure */
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
	$('.col-100').addClass('span-12').removeClass('col-100');
	
failCheck="Set up pages: Remove empty elements";
	
// Remove empty elements
	removeDiv('p:empty, .archive-intro:empty');
	
failCheck="Set up pages: Wrap content within .site-main";
	
// Wrap content within .site-main so that widgets can be distributed properly
	wrapDiv('.site-main','<div class="site-main-inner"></div>', 'inside');	
	
failCheck="Set up pages: Add 'noFX' class to img";
		
// Add "noFX" class to img if it appears in any of the parent divs
	$( "div.noFX" ).find("img").addClass("noFX");
	$( "div.noFX" ).find("a").addClass("noFX");
	
// Fade in lazy loaded images
	//animateDiv( 'img:not(.loader-img):not(.site-icon)', 'fadeIn', 150, '110%', 200 );
	
failCheck="Set up pages: Preload BG image and fade in";
	
// Preload BG image and fade in
	if ( $( 'body' ).hasClass( "background-image" ) && getDeviceW() > mobileCutoff ) {
		var preloadBG = new Image();
		preloadBG.onload = function() { animateDiv( '.parallax-mirror', 'fadeIn', 0, '', 200 ); };		
		preloadBG.onerror = function() { console.log("site-background.jpg not found"); };
		preloadBG.src = getUploadURI + "/" + "site-background.jpg";  
	}
	
failCheck="Set up pages: Add 'active' & 'hover' classes to menu items";
	
// Add "active" & "hover" classes to menu items, assign roles for ADA compliance		
	$(".main-navigation ul.main-menu").attr('role','menubar');
	$(".main-navigation li").attr('role','none');
	$(".main-navigation a").attr('role','menuitem');
	$(".main-navigation ul.sub-menu").attr('role','menu');
	
	var	$currents = $(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor"); 
	$currents.addClass( "active" );
	$currents.find(">a").attr('aria-current','page');
	$(".main-navigation ul.main-menu > li").hover ( function() { 		
		$currents.replaceClass( "active", "dormant" ); 
		$(this).addClass( "hover" );  
	}, function() {  
		$(this).removeClass("hover");
		$currents.replaceClass( "dormant", "active" ); 
	});		
	
	var	$subCurrents = $(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor"); 
	$subCurrents.addClass( "active" );
	$subCurrents.find(">a").attr('aria-current','page');
	$(".main-navigation ul.sub-menu > li").hover ( function() { 		
		$subCurrents.replaceClass( "active", "dormant" ); 
		$(this).addClass( "hover" );  
	}, function() {  
		$(this).removeClass("hover");
		$subCurrents.replaceClass( "dormant", "active" ); 
	});
	
failCheck="Set up pages: Animate scrolling when moving up or down a page";
	
// Animate scrolling when moving up or down a page
	$('a[href^="#"]:not(.carousel-control-next):not(.carousel-control-prev)').on('click', function (e) {
		e.preventDefault();    
		var target = this.hash;
		animateScroll(target);
	});
	
failCheck="Set up pages: Automatically adjust for Google review bar";

// Automatically adjust for Google review bar 
	$( '<div class="wp-google-badge-faux"></div>' ).insertAfter( $('#colophon'));
	
	
failCheck="Set up pages: Set up mobile menu animation";
	
// Set up mobile menu animation
	//$('#header *').each(function() { 
		//if ($(this).css("position") === "fixed") {
			//$(this).addClass("fixed-pos");
		//}
	//});
	//if ( $(".wp-google-badge").length > 0 ) {
		//var googleH = $(".wp-google-badge").outerHeight(true) + 20;
		//$("#mobile-navigation > ul").css({"padding-bottom":googleH+"px"});		
	//}
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
	
failCheck="Set up pages: Ensure all slides in a Bootstrap carousel are even height";
	
// Ensure all slides in a Bootstrap carousel are even height
	$(".carousel").each(function() {
		var thisCarousel = $(this), maxH = 0, getPadding = parseInt(thisCarousel.find(".carousel-inner").css('padding-bottom'));
		thisCarousel.data("maxH", 0);
		
		thisCarousel.on('slid.bs.carousel', function() {
			var thisSlide = thisCarousel.find(".carousel-item.active").outerHeight() + getPadding;
			if ( thisSlide > maxH ) { 
				thisCarousel.find(".carousel-inner").css("height",thisSlide+"px");	
				maxH = thisSlide;
			}
		});		
	});			
	
failCheck="Set up pages: Add star icons to reviews and ratings";

// Add star icons to reviews and ratings
	$('.testimonials-rating').each(function() {
		var getRating = $(this).html(), replaceRating = getRating;
		if ( getRating == 5) replaceRating = '<span class="rating rating-5-star" aria-hidden="true"><span class="sr-only">Rated 5 Stars</span></span>';
		if ( getRating == 4) replaceRating = '<span class="rating rating-4-star" aria-hidden="true"><span class="sr-only">Rated 4 Stars</span></span>';;
		if ( getRating == 3) replaceRating = '<span class="rating rating-3-star" aria-hidden="true"><span class="sr-only">Rated 3 Stars</span></span>';;
		if ( getRating == 2) replaceRating = '<span class="rating rating-2-star" aria-hidden="true"><span class="sr-only">Rated 2 Stars</span></span>';;
		if ( getRating == 1) replaceRating = '<span class="rating rating-1-star" aria-hidden="true"><span class="sr-only">Rated 1 Star</span></span>';;
		$(this).html( replaceRating );
	});

	
/*--------------------------------------------------------------
# Set up sidebar
--------------------------------------------------------------*/	
	
failCheck="Set up sidebar";
	
	window.setupSidebar = function (compensate, menuLock, shuffle) {
		compensate = compensate || 0;		
		menuLock = menuLock || "true";
		shuffle = shuffle || "true";
		var isPaused = false;		
						
// Shuffle an array of widgets
	window.shuffleWidgets = function ($elements) {
		var i, index1, index2, temp_val, count = $elements.length, $parent = $elements.parent(), shuffled_array = [];

		for (i = 0; i < count; i++) { shuffled_array.push(i); }

		for (i = 0; i < count; i++) {
			index1 = (Math.random() * count) | 0;
			index2 = (Math.random() * count) | 0;
			temp_val = shuffled_array[index1];
			shuffled_array[index1] = shuffled_array[index2];
			shuffled_array[index2] = temp_val;
		}

		$elements.detach();
		for (i = 0; i < count; i++) { $parent.append( $elements.eq(shuffled_array[i]) ); }			
				
		var el = $(".widget.lock-to-bottom").detach();
		$parent.append( el );		
	};		

// Set up "locked" widgets, and shuffle the rest
		$('.widget.lock-to-top, .widget.lock-to-bottom').addClass("locked");		
		$('.widget:not(.locked)').addClass("shuffle");
		
		if ( getDeviceW() > mobileCutoff && shuffle == "true" ) { 
			shuffleWidgets( $('.shuffle') );
		}
				
// Initiate widget removal
		window.widgetInit = function () {
			if ( getDeviceW() > mobileCutoff && isPaused==false ) { 
				$('.widget').removeClass('hide-widget');
				removeWidgets('.widget.remove-first');
				isPaused = true; 
			} 
		};
				
// Remove widgets that do not fit
		window.removeWidgets = function (removeWidget) {
			var contentH = $("#primary .site-main-inner").outerHeight() + compensate, widgetH = $("#secondary .sidebar-inner").outerHeight(true), remainH = widgetH - contentH, removeThis = $(removeWidget);

			if ( remainH > 0 && $('.widget:not(.hide-widget)').length ) {		
				removeThis.random().addClass("hide-widget");
						
				if ( $('.widget.remove-first:not(.hide-widget)').length ) { removeWidgets( '.widget.remove-first:not(.hide-widget)' ); }
				else if ( $('.widget.shuffle:not(.hide-widget)').length ) { removeWidgets( '.widget.shuffle:not(.hide-widget):not(.widget-important)' ); }
				else if ( $('.widget.lock-to-bottom:not(.hide-widget)').length ) { removeWidgets( '.widget.lock-to-bottom:not(.hide-widget):not(.widget-important)' ); }
				else { removeWidgets( '.widget.lock-to-top:not(.hide-widget):not(.widget-important)' ); }				
				
			} else { 
				checkWidgets();
			}
		};						
		
// Determine the widget with height closest to amount of space remaining to fill
		window.findClosest = function (compare, loop, test) {
			var curr, diff = 999999;
			loop++;
			for (var val = 1; val < loop; val++) {
				var newdiff = compare - test[val];
				if ( newdiff > 0 && newdiff < diff ) { diff = newdiff; curr = test[val]; }
			}
			return curr;
		};
		
// Check hidden widgets for any smaller ones that might still fit
		window.checkWidgets = function () {			
				var contentH = $("#primary .site-main-inner").outerHeight() + compensate, widgetH = $("#secondary .sidebar-inner").outerHeight(), i = 0, widgets = [];
				var remainH = contentH - widgetH + 140; // 140 is arbitrary, can be adjusted as needed to make sure widgets fit but don't go over
			
				$('.widget.hide-widget').each(function() {
					var theWidget = $(this);
					i++;
					widgets[i] = theWidget.outerHeight(true) + Math.floor((Math.random() * 10) - 5);
					theWidget.addClass("widget-height-"+widgets[i]);	
				});
			
				var replaceWidget = findClosest(remainH, i, widgets);
				widgets.splice(widgets.indexOf(replaceWidget),1);
				if ( replaceWidget < remainH ) { $(".widget-height-"+replaceWidget).removeClass("hide-widget"); }
			
				adjustSidebarH();
				setTimeout(function() { isPaused = false; }, 3000);
		};	
						
// Adjust height of #secondary to match #primary + add extra spacing between .widget if necessary
		window.adjustSidebarH = function () {
			var contentH = $("#primary").outerHeight(true) + compensate;
			$("#secondary").animate( { height: contentH+"px" }, 300);
		};
				
// Mark first, last, even and odd widgets
		window.labelWidgets = function () {
			$(".widget").removeClass("widget-first").removeClass("widget-last").removeClass("widget-even").removeClass("widget-odd");
			$(".widget:not(.hide-widget)").first().addClass("widget-first");  
			$(".widget:not(.hide-widget)").last().addClass("widget-last"); 
			$(".widget:not(.hide-widget):odd").addClass("widget-even"); 
			$(".widget:not(.hide-widget):even").addClass("widget-odd"); 	
		};
		
 // Move sidebar in conjunction with mouse scroll to keep it even with content
		window.moveWidgets = function () {
			var contentH = $('#primary').outerHeight(), elem = $(".sidebar-inner"), elemH = elem.outerHeight() + parseInt($("#secondary").css('padding-top')) + parseInt($("#secondary").css('padding-bottom')), contentV = contentH - getDeviceH() + 200, sidebarV = elemH - getDeviceH() + 400;				
			var conH = $("#secondary").outerHeight(), conT = $("#secondary").offset().top, winH = $(window).height(), winT = $(window).scrollTop();				
			var adjT = winT - conT, fullH = conH - winH, scrollPct = adjT / fullH, dist = winT - conT, maxH = contentH - elemH;	
			if ( scrollPct > 1 ) { scrollPct = 1; }
			if ( scrollPct < 0 || scrollPct == null ) { scrollPct = 0; }
			var moveElem = maxH * scrollPct;	
			if ( moveElem > maxH ) { moveElem = maxH; }
			if ( moveElem < 0 ) { moveElem = 0; }
			if ( contentV > 0 && sidebarV > 0 && dist > 0 && getDeviceW() > mobileCutoff ) { elem.css("margin-top",moveElem+"px"); } else { elem.css("margin-top","0px"); }
			
			//console.log("contentV="+contentV+", sidebarV="+sidebarV);
		};
	};	

	
/*--------------------------------------------------------------
# Screen resize
--------------------------------------------------------------*/
	
failCheck="Screen resize";
	
	$(window).load(function() { screenResize(true); });
	$(window).resize(function() { screenResize(true); }); 
	
	window.screenResize = function (widgets) {		
		widgets = widgets || false;

		setTimeout( function () {
		// Ensure Parallax elements and widgets are re-assessed on desktop
			if ( getDeviceW() > mobileCutoff ) { 
				$(window).trigger('resize.px.parallax');
				if ( widgets == true ) { widgetInit(); } 
			} 
			
			labelWidgets();

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
			
			if ( $( mainClass ).is(":visible") ) {		
				$( fauxClass ).height($( mainClass ).outerHeight());  
				$( '.wp-google-badge-faux' ).height($( '.wp-google-badge' ).outerHeight());  
			} else {			
				$( fauxClass ).height(0); 
			}						
		});		

		
		/* Remove horizontal styling from office hours box on cell phones */	
		//if ( $('body').hasClass('screen-1') ) { 
			//if ( $('.office-hours').hasClass('horz') ) { 
				//$('.office-hours').removeClass('horz').addClass('force-vert'); 
			//}
		//}
		//if ( $('.office-hours').hasClass('force-vert') ) { 
			//if ( !$('body').hasClass('screen-1') ) { 
				//$('.office-hours').removeClass('force-vert').addClass('horz'); 
			//}
		//}
		
		
		/* Set up "fixed" footer, based on class added in header.php */		
		//var footerH = $(".footer-fixed #footer").outerHeight(true);
		//$(".footer-fixed #page").css({"marginBottom":footerH+"px"});		
		
		/* Shift #secondary below #wrapper-bottom on mobile */		
		//moveDiv('.sidebar-shift.screen-mobile #secondary','#wrapper-bottom',"after");	
		
		// Reposition Woocommerce Update Cart button above coupon section on mobile checkout
		//moveDiv('.woocommerce-page.screen-mobile table.cart td.actions .button[name~="update_cart"]','.woocommerce-page #primary table.cart td.actions .coupon','before');	
		
		//if ( getDeviceW() > 860 ) { 
			/* Ensure parallax columns are matching heights */
			//$('.parallax').each(function() { 
				//$(this).find('.col.col-parallax').height( $(this).find('.col:not(.col-parallax)').outerHeight() );
			//});
			
			/* Replace .footer-icon on larger devices */
			//if ( $('.footer-bottom').hasClass('remove-icon') ) {
				//$('.footer-bottom').removeClass('remove-icon').addClass('adjust-icon');
			//}		
		//}
		
		//if ( getDeviceW() < 861 ) { 
			/* Remove .footer-icon on smaller devices */
			//if ( $('.footer-bottom').hasClass('adjust-icon') ) {
				//$('.footer-bottom').removeClass('adjust-icon').addClass('remove-icon');
			//}
		//}
		
		/* Adjustments to The Events Calendar PRO plug-in */
		//function setupEvents() {
			//moveDivs('.type-tribe_events', '.tribe-events-event-image', '.tribe-event-schedule-details', 'after');		
			//$('.tribe-events-meta-group-details').addClass('col').addClass('col-50').addClass('break-100-1');
			//$('.tribe-events-meta-group-venue').addClass('col').addClass('col-50').addClass('break-100-1');
			//$('.tribe-events-meta-group-gmap').addClass('col').addClass('col-100');
			//$('.events-archive.events-list .type-tribe_events').addClass('col').addClass('col-33').addClass('break-50-2').addClass('break-100-1');
			//$('.type-tribe_events .tribe-events-event-image').addClass('col').addClass('col-100').addClass('noPad');
			//$('li#tribe-bar-views-option-month').text('Calendar');
			//if ( $('.tribe-country-name:contains("United States")').length > 0 ) { $('.tribe-country-name').css('display','none'); }		
			//if ( $('.tribe-events-event-cost .ticket-cost').text().indexOf("Cost:") < 0) { $('.tribe-events-event-cost .ticket-cost').prepend("<strong>Cost:</strong> "); }
			//if ( $('.recurringinfo')[0] ) {} else { $('.tribe-js .tribe-events-user-recurrence-toggle').css("display","none"); }
		//}
		//if ( $('.events-single')[0] ) { setupEvents(); }		
		//if ( $('.events-archive')[0] ) { removeSidebar('.events-archive'); setupEvents(); setInterval( function () { setupEvents(); }, 2000); } 
	};
	
			
/*--------------------------------------------------------------
# ADA compliance
--------------------------------------------------------------*/
	
failCheck="ADA compliance";

	// Add alt="" to all images with no alt tag
	setTimeout(function() { $('img:not([alt])').attr('alt', ''); }, 50);
	setTimeout(function() { $('img:not([alt])').attr('alt', ''); }, 500);
	
	// Add special focus outline when someone is using tab to navigate site
	$(document).mousemove(function(event) { $('body').addClass('using-mouse').removeClass('using-keyboard'); });
	$(document).keydown(function(e) { if( e.keyCode == 9 && !$('body').hasClass("using-mouse") ) { $('body').addClass('using-keyboard'); } });
	
	// Menu support
	$('[role="menubar"]' ).on( 'focus.aria mouseenter.aria', '[aria-haspopup="true"]', function ( ev ) { $( ev.currentTarget ).attr( 'aria-expanded', true ); } );
	$('[role="menubar"]' ).on( 'blur.aria mouseleave.aria', '[aria-haspopup="true"]', function ( ev ) { $( ev.currentTarget ).attr( 'aria-expanded', false ); } );
	
	// Add .tab-focus class to links and buttons & auto scroll to better position on screen
	var allowTabFocus = false;
	$(window).on('keydown', function(e) {
		$('*').removeClass('tab-focus');
	  	if ( e.keyCode === 9 ) { allowTabFocus = true; }
	});
	$('*').on('focus', function() {
		if ( allowTabFocus ) { 
			$(this).addClass('tab-focus');			
			$(this).closest('li').addClass('tab-focus');
			var scrollPos = $(window).scrollTop(), moveTo = $(this).offset().top - ($(window).height() / 2), diff = Math.abs(moveTo - scrollPos);		
			if ( diff > 200 ) { animateScroll(moveTo); }
		}
	});
	$(window).on('mousedown', function() {
		$('*').removeClass('tab-focus');
	  	allowTabFocus = false;
	})
	
		
/*--------------------------------------------------------------
# Delay parsing of JavaScript
--------------------------------------------------------------*/
	
failCheck="Delay parsing of JavaScript";

	$(window).load(function() { 
		
	// Fade out loader screen when site is fully loaded
	$("#loader").fadeOut("fast");  
		
	// Get video link from data-src and feed to src 
		var vidDefer = document.getElementsByTagName('iframe');
		for (var i=0; i<vidDefer.length; i++) {
			if(vidDefer[i].getAttribute('data-src')) {
				vidDefer[i].setAttribute('src',vidDefer[i].getAttribute('data-src'));
			}
		}
		
		// HACK - keep the 100x400 box with parallax image from showing up on left side of page	
		//window.clearParallaxGlitch = function () {
			//$('.parallax-mirror').each(function() {
				//var thisDiv = $(this), thisW = thisDiv.width();
				//if ( thisW < 101 ) { thisDiv.css({ "opacity":0 }); }
				//else { thisDiv.css({ "opacity":1 }); }
			//}); 
		//}
		//setTimeout(function() {	clearParallaxGlitch(); }, 50);
		//$('#container').resize(function() { clearParallaxGlitch(); });
		
		
		setTimeout(function() {	// Wait 1 second before calling the following functions 
			
		// Generic page setup functions (if not overriden in site script.js)
			trimText();
			buildAccordion();
			
		}, 1000);

		setTimeout(function() {	// Wait 2.5 seconds before calling the following functions 	

		// Clear Hummingbird cache	
			//$.post({
				//url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
				//data : { action: "clear_cache" },
				//success: function( response ) { console.log(response); } 
			//});	
		
		// Count page view 
			var postID = $('body').attr('id');
			$.post({
				url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
				data : { action: "count_post_views", id : postID },
				success: function( response ) { console.log(response); } 
			});				
					
		}, 2500);

	}); 
	

/*--------------------------------------------------------------
# If page load fails
--------------------------------------------------------------*/
	
	} catch(err) {	
	
// Remove loading screen if site crashes (better to see something than nothing) 	
	$( "#page" ).prepend( "<div class='technical-difficulties'><b>ATTENTION: </b>Our site is experiencing technical difficulties, but we are working to fix the issue.  Thank you for your patience.</div>" );
	$("#loader").fadeOut("fast");

	var theSite = window.location.hostname;
	$.post({
		url : 'https://'+theSite+'/wp-admin/admin-ajax.php',
		data : { action: "sendServerEmail", theSite: theSite, failCheck: failCheck },
	});	
	
// Clear Hummingbird cache 	
	//$.post({
		//url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
		//data : { action: "force_clear_cache" },
		//success: function( response ) { console.log(response); } 
	//});		
}});