document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Basic site functionality
# DOM level functions
# Setup Sidebar
# Set up animation
# Set up pages
# Screen resize
# ADA compliance
# Delay parsing of JavaScript

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Basic site functionality
--------------------------------------------------------------*/

	var mobileCutoff = 1024, tabletCutoff = 576, mobileMenuBarH = 0, ajaxURL = 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php';	
	
// Is user on an Apple device?
	window.isApple = function () {
		return !!navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform);
	};	
	
// Get "slug" of current webpage
	window.getSlug = function () {
		var findSlug = window.location.pathname.split('/');
		return findSlug[1];		
	};	

// Get variable from page's URL
	window.getUrlVar = function(key) {
		key = key || "page";
		var results = new RegExp('[\?&]' + key + '=([^&#]*)').exec(window.location.href);
		if (results == null) { return null; }
		return decodeURI(results[1]) || 0;
	};
	
// Map spacebar or enter key press to mouse button click
	window.keyPress = function(trigger) {
		$(trigger).keyup(function(event) { 
			if (event.keyCode === 13 || event.keyCode === 32) { 
				$(this).click(); 
			} 
		});
	};		

// Set up Logo to link to home page	
	keyPress('.logo');
	$('.logo').attr('tabindex','0').attr('role','button').attr('aria-label','Return to Home Page').css('cursor', 'pointer').click(function() { 
		window.location = "/"; 
	});
	$('.logo img').attr('aria-hidden', 'true');
	
	window.linkHome = function (container) {
		keyPress(container);
		$(container).attr('tabindex','0').attr('role','button').attr('aria-label','Return to Home Page').css('cursor', 'pointer').click(function() { 
			window.location = "/"; 
		});
		$(container).find('img').attr('aria-hidden', 'true');
	};		

// Set up American Standard logo to link to American Standard website	
	$("img[src*='hvac-american-standard/american-standard']").each(function() { 
		$(this).wrap('<a href="https://www.americanstandardair.com/" target="_blank" rel="noreferrer"></a>'); 
	});
			
// Track phone & email clicks
	$('.track-clicks, .wpcf7-submit').click(function() {
		var thisClick = $(this), thisAction = thisClick.attr('data-action') ? thisClick.attr('data-action') : 'email', thisUrl = thisClick.attr('data-url');
		if ( thisUrl ) { 
			//if (typeof gtag_report_conversion === "function") { 
				//gtag_report_conversion(thisUrl);
			//}			
			document.location = thisUrl; 
		}
		$.post({
			url : ajaxURL,
			data : { action: "count_link_clicks", type: thisAction }, 
			success: function( response ) { console.log(response);  } 
		});		
	});
	
// Redirect to 'thank you' page after form submission, to avoid double submissions
	window.document.addEventListener( 'wpcf7mailsent', function( event ) { 
		location = '/email-received/';
	}, false ); 

// Set up Cookies
	window.setCookie = function(cname,cvalue,exdays) {
		var domain = document.domain.match(/[^\.]*\.[^.]*$/)[0];
		var d = new Date(), expires='';
		d.setTime(d.getTime()+(exdays*24*60*60*1000));
		if ( exdays != null && exdays != "" ) {	expires = "expires="+d.toGMTString()+"; "; }
		//document.cookie = cname + "=" + cvalue + "; " + expires + "path=/; domain=" + domain + "; secure";		
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
	window.deleteCookie = function(cname) {
		setCookie(cname,"",-1);
	};
	
// Extend .hasClass to .hasPartialClass
	$.fn.hasPartialClass = function(partial) {
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

// Create faux div for sticky elements pulled out of document flow	
	window.addStuck = function (element, faux) {
		faux = faux || "true";	
		if ( $(element).is(":visible") ) {						
			$(element).addClass("stuck");	
			if ( faux == "true" ) { 
				addFaux(element.split(" ").pop()); 
			}
		}
	};

	window.removeStuck = function (element, faux) {
		faux = faux || "true";
		$(element).removeClass("stuck");
		if ( faux == "true" ) { 
			removeFaux(element.split(" ").pop()); 
		}
	};

	window.addFaux = function (element, fixedAtLoad) {
		fixedAtLoad = fixedAtLoad || "false";
		var theEl = $(element);
		if ( theEl.is(":visible") ) {		
			$( "<div class='"+element.substr(1)+"-faux'></div>" ).insertBefore( theEl );
			$("."+element.substr(1)+"-faux").css({ "height":theEl.outerHeight()+"px" });
			if ( fixedAtLoad == "true" ) { 
				theEl.css({ "position":"fixed" }); 
			}
		}
	};

	window.removeFaux = function (element) {
		$("."+element.substr(1)+"-faux").remove();
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
				trigger = $(container).parent().next().hasClass('section-lock') ? $(container).parent().next().next() : $(container).parent().next();
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
			var newTop = mobileMenuBarH;
			if ( strictTop === "" ) {
				$('.stuck').each(function() {
					newTop = newTop + $(this).outerHeight(true);
				});		
			} else {
				newTop = newTop + Number(strictTop);
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
			$('a.scroll-top').animate( { opacity: 0 }, 150, function() { 
				$('a.scroll-top').css({ "display": "none" }).removeClass('scroll-btn-visible');
			});
		} else {
			$('a.scroll-top').css({ "display": "block" }).animate( { opacity: 1 }, 150).addClass('scroll-btn-visible');
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
	
// Duplicate menu button text onto the button BG
	$( ".main-navigation ul.main-menu li > a").each(function() { 
		$(this).parent().attr('data-content', $(this).html());
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
	keyPress('ul.tabs li');
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
	
	// Cover container with direct sibling, then slide sibling out of the way to reveal container
	window.revealDiv = function (container, delay, speed, offset) {
		delay = delay || 0;		
		speed = speed || 1000;		
		offset = offset || "100%";
		var theEl = $(container), theNextEl = theEl.next(), fixedH = theEl.outerHeight();
		
		theNextEl.css({ "transform":"translateY(-"+fixedH+"px)", "transition-duration":0 });
		setTimeout( function () { 
			theEl.waypoint(function() {
				theNextEl.css({ "transform":"translateY(0)", "transition-duration":speed+"ms" });	
			}, { offset: offset });
		}, delay);	
	};	
		
// Button to reveal a hidden div
	window.btnRevealDiv = function(button, container, topSpacer, initSpeed) {	
		initSpeed = initSpeed || 0;
		topSpacer = topSpacer || 0;
		topSpacer = topSpacer + mobileMenuBarH;	
		var origDisplay = $(container).css( "display" );		

		$(container).css( "display","none");	
		$(button).click(function(){
			$(container).css( "display",origDisplay);
			animateScroll(container, topSpacer, initSpeed);
		});
	};
	
	// Set up Review Questions & Redirect
	$('.review-form:first').addClass('active');
	$('.review-form #gmail-yes').click(function() { window.location.href = "/google"; });	
	$('.review-form #facebook-yes').click(function() { window.location.href = "/facebook"; });	
	$('.review-form #yelp-yes').click(function() { window.location.href = "/yelp"; });
	
	$('.review-form #gmail-no, .review-form #facebook-no, .review-form #yelp-no').click(function() {
		$(this).closest('.review-form').removeClass('active');
		$(this).closest('.review-form').next().addClass('active');			
	});
	
// This script blocked by Content Security Policy	
	var ak_js = document.getElementById( 'ak_js' ), el, destinations = [];

	if( !ak_js ) {
		ak_js = document.createElement( 'input' );
		ak_js.type = 'hidden';
		ak_js.name = ak_js.id = 'ak_js';
	} else {
		ak_js.parentNode.removeChild( ak_js );
	}

	ak_js.value = ( new Date() ).getTime();

	if ( el = document.getElementById( 'commentform' ) ) { destinations.push( el ); }
	if ( ( el = document.getElementById( 'replyrow' ) ) && ( el = el.getElementsByTagName('td') ) ) { destinations.push( el.item(0) ); }
	for ( var i = 0, j = destinations.length; i < j; i++ ) { destinations[i].appendChild( ak_js ); }
	
// Control animation for menu search box
	setTimeout(function() {
		$('div.menu-search-box a.menu-search-bar').each(function() {
			var searchBar = $(this), inputBox = searchBar.find('input[type="search"]'), inputW = searchBar.outerWidth(), magW = (searchBar.find('i.fa').outerWidth()) * 1.3;
			searchBar.css({ "width": magW+"px" });
			searchBar.click(function() {
				searchBar.animate( { "width":inputW+'px' }, 150, function() { if ( typeof centerSubNav === 'function' ) { setTimeout(function() {centerSubNav();}, 300); } });	
			});
			if ( !isApple() ) {
				inputBox.focus(function() {
					var inputPos = -(getPosition(searchBar, 'top') - mobileMenuBarH - 25);
					$('#mobile-navigation > #mobile-menu').css({"position":"relative"}).animate({ "margin-top": inputPos + "px" }, 300);
				});
				inputBox.blur(function() {
					$('#mobile-navigation > #mobile-menu').css({"position":"relative"}).animate({ "margin-top": "0px)" }, 300);
				});	
			}
		}); 
	}, 300);

/*--------------------------------------------------------------
# DOM level functions
--------------------------------------------------------------*/

// Replace one class with another
	$.fn.replaceClass = function (remove, add) {
		return this.removeClass(remove).addClass(add);
	};

// Randomly select from a group of elements
	$.fn.random = function() { return this.eq(Math.floor(Math.random() * this.length)); };

// Clone div and move the copy to new location
	window.cloneDiv = function (moveThis, anchor, where) {
		where = where || "after";
		if ( where == "after" ) {
			$(moveThis).clone().insertAfter($(anchor));
		} else if ( where == "before" ) {
			$(moveThis).clone().insertBefore($(anchor));
		} else if ( where == "top" || "start" ) {
			$(anchor).prepend($(moveThis).clone());
		} else {
			$(anchor).append($(moveThis).clone());
		}
	};	

// Move a single div to another location
	window.moveDiv = function (moveThis, anchor, where) {
		where = where || "after";
		if ( where == "after" ) {
			$(moveThis).insertAfter($(anchor));
		} else if ( where == "before" ) {
			$(moveThis).insertBefore($(anchor));
		} else if ( where == "top" || "start" ) {
			$(anchor).prepend($(moveThis));
		} else {
			$(anchor).append($(moveThis));
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
		if ( where == "after" ) {
			$(target).append($(newDiv));
		} else if ( where == "before" ) {
			$(target).prepend($(newDiv));
		} else {
			$(newDiv).insertBefore($(target));
		}
	};

// Wrap a div inside a newly formed div
	window.wrapDiv = function (target, newDiv, where) {
		newDiv = newDiv || "<div></div>";
		where = where || "outside";
		if ( where == "outside" ) {
			$(target).each(function() { 
				$(this).wrap(newDiv); 
			});
		} else {
			$(target).each(function() { 
				$(this).wrapInner(newDiv); 
			});
		}
	};	

// Wrap multiple divs inside a newly formed div
	window.wrapDivs = function (target, newDiv) {
		newDiv = newDiv || "<div />";
		$(target).wrapAll( newDiv );
	};	
	
// Size a frame according to the image or video inside it
	window.sizeFrame = function (target, frame, scale) {
		frame = frame || ".frame";
		scale = scale || "0.9";
		
		$(target).find('img').css({ 'transform':'scale('+scale+')' });
		
		$(target).each(function() {
			var thisFrame = $(this).find(frame), thisImg = $(this).find('img');
			
			if (target.includes('video')) { 
				thisImg = $(this).find('iframe');
				var frameW = thisImg.width(), frameH = thisImg.height();
				thisFrame.width(frameW+"px").height(frameH+"px").css({'marginTop':-frameH+"px"});
			} else {
				if (target.includes('carousel')) { thisImg = $(this).find('.carousel-item.active img'); }

				thisImg.one("load", function() {					
					var frameW = thisImg.width(), frameH = thisImg.height();
					thisFrame.width(frameW+"px").height(frameH+"px").css({'marginBottom':-frameH+"px"});					
				}).each(function() {
					if(this.complete) {
						$(this).trigger('load');
					}
				});	
			}
		});
	};
	
// Turn SVG into an element's background image
	window.svgBG = function (svg, element, where) {
		where = where || "top";
		if ( where == "bottom" ) {
			$(svg).clone().css({"position":"absolute"}).appendTo($(element));
		} else if ( where == "top" ) {
			$(svg).clone().css({"position":"absolute"}).prependTo($(element));
		} else if ( where == "before" || where == "start" ) {
			$(svg).clone().css({"position":"absolute"}).insertBefore($(element));
		} else {
			$(svg).clone().css({"position":"absolute"}).insertAfter($(element)); 
		} 
	};
		
if ( typeof parallaxBG !== 'function' ) { 
	window.parallaxBG = window.parallaxDiv = window.magicMenu = window.splitMenu = window.addMenuLogo = window.desktopSidebar = function() {}
}	
		
/*--------------------------------------------------------------
# Set up sidebar
--------------------------------------------------------------*/
	window.setupSidebar = function (compensate, sidebarScroll, shuffle) {
		sidebarScroll = sidebarScroll || "true";
		shuffle = shuffle || "true";		
		compensate = compensate || 0;
		
// Add classes for first, last, even and odd widgets
		window.labelWidgets = function () {
			$(".widget:not(.hide-widget)").first().addClass("widget-first");  
			$(".widget:not(.hide-widget)").last().addClass("widget-last"); 
			$(".widget:not(.hide-widget):odd").addClass("widget-even"); 
			$(".widget:not(.hide-widget):even").addClass("widget-odd"); 	
		};		

// Shuffle non-locked widgets
		var $shuffledWidgets = $('.widget:not(.lock-to-top):not(.lock-to-bottom)'), count = $shuffledWidgets.length, $parent = $shuffledWidgets.parent(), i, index1, index2, temp_val, shuffled_array = [], $lockedWidgets = $(".widget.lock-to-bottom").detach();

		for (i = 0; i < count; i++) { 
			shuffled_array.push(i); 
		}

		if ( shuffle == "true" ) {
			for (i = 0; i < count; i++) {
				index1 = (Math.random() * count) | 0;
				index2 = (Math.random() * count) | 0;
				temp_val = shuffled_array[index1];
				shuffled_array[index1] = shuffled_array[index2];
				shuffled_array[index2] = temp_val;
			}
		}

		$shuffledWidgets.detach();
		for (i = 0; i < count; i++) { 
			$parent.append( $shuffledWidgets.eq(shuffled_array[i]) );
		}			

		$parent.append( $lockedWidgets );
		
		$('.screen-desktop .widget-set.set-a:not(:first-child), .screen-desktop .widget-set.set-b:not(:first-child), .screen-desktop .widget-set.set-c:not(:first-child)').addClass('hide-set').addClass('hide-widget');
		
		if ( $('body').hasClass('screen-mobile') ) {
			labelWidgets();
		} else {
			desktopSidebar(compensate, sidebarScroll, shuffle);
		}
	};	
	
/*--------------------------------------------------------------
# Set up animation
--------------------------------------------------------------*/
	
var pageViews=getCookie('pages-viewed'), uniqueID, pageLimit = 300, speedFactor = 0.5;

// Gracefully start to fade out the pre-loader
	var opacity = 1, loader = document.getElementById("loader"), color = getComputedStyle(loader).getPropertyValue("background-color"), [r,g,b,a] = color.match(/\d+/g).map(Number), bgTimer = setInterval(function() {
		opacity = opacity - 0.01;
		loader.style.backgroundColor = 'rgb('+r+','+g+','+b+','+opacity+')';
		if ( opacity < 0.5 ) { clearInterval(bgTimer); }
	}, 10);
	window.resetLoader = function () {
		loader.style.backgroundColor = 'rgb('+r+','+g+','+b+',0.5)';
	}
	
// Set up easing
	window.convertBezier = function(easing) {
		if ( easing == "easeInCubic" ) { easing = 'cubic-bezier(0.550, 0.055, 0.675, 0.190)'; }
		else if ( easing == "easeInQuart" ) { easing = 'cubic-bezier(0.895, 0.030, 0.685, 0.220)'; }
		else if ( easing == "easeInQuint" ) { easing = 'cubic-bezier(0.755, 0.050, 0.855, 0.060)'; }		
		else if ( easing == "easeInExpo" ) { easing = 'cubic-bezier(0.950, 0.050, 0.795, 0.035)'; }
		else if ( easing == "easeInBack" ) { easing = 'cubic-bezier(0.600, -0.280, 0.735, 0.045)'; }
		
		else if ( easing == "easeOutCubic" ) { easing = 'cubic-bezier(0.215, 0.610, 0.355, 1.000)'; }
		else if ( easing == "easeOutQuart" ) { easing = 'cubic-bezier(0.165, 0.840, 0.440, 1.000)'; }
		else if ( easing == "easeOutQuint" ) { easing = 'cubic-bezier(0.230, 1.000, 0.320, 1.000)'; }		
		else if ( easing == "easeOutExpo" ) { easing = 'cubic-bezier(0.190, 1.000, 0.220, 1.000)'; }
		else if ( easing == "easeOutBack" ) { easing = 'cubic-bezier(0.175, 0.885, 0.320, 1.275)'; }
	
		else if ( easing == "easeInOutCubic" ) { easing = 'cubic-bezier(0.645, 0.045, 0.355, 1.000)'; }
		else if ( easing == "easeInOutQuart" ) { easing = 'cubic-bezier(0.770, 0.000, 0.175, 1.000)'; }
		else if ( easing == "easeInOutQuint" ) { easing = 'cubic-bezier(0.860, 0.000, 0.070, 1.000)'; }		
		else if ( easing == "easeInOutExpo" ) { easing = 'cubic-bezier(1.000, 0.000, 0.000, 1.000)'; }
		else if ( easing == "easeInOutBack" ) { easing = 'cubic-bezier(0.680, -0.550, 0.265, 1.550)'; }

		return easing;
	}

// Animate single element (using transitions from animate.css)
	window.animateDiv = function(container, effect, initDelay, offset, speed, easing) {
		initDelay = initDelay || 0;		
		offset = offset || "100%";
		speed = speed || 1000;
		speed = speed / 1000;
		easing = easing || 'ease';

		var transDuration = parseFloat($(container).css( "transition-duration")), transDelay = parseFloat($(container).css( "transition-delay"));
		if ( pageViews > pageLimit ) { 
			initDelay = initDelay * speedFactor; 
			speed = speed * speedFactor; 
			transDuration = transDuration * speedFactor; 
			transDelay = transDelay * speedFactor;  
		}		

		$(container).addClass('animated').css({ "animation-duration": speed+"s", "transition-duration": transDuration+"s", "transition-delay": transDelay+"s", "animation-timing-function": convertBezier(easing) });		
		$(container+".animated").waypoint(function() {
			var thisDiv = $(this.element);	
			setTimeout( function () { 
				thisDiv.addClass(effect); 
			}, initDelay);			
			this.destroy();
		}, { offset: offset });
	};

// Animate multiple elements
	window.animateDivs = function(container, effect1, effect2, initDelay, mainDelay, offset, speed, easing) {	
		initDelay = initDelay || 0;		
		mainDelay = mainDelay || 100;		
		offset = offset || "100%";
		speed = speed || 1000;
		speed = speed / 1000;	
		easing = easing || 'ease';

		var theDelay = 0, currEffect = effect1, getDiv = container.split(' '), theDiv = getDiv.pop();
		if ( pageViews > pageLimit ) { 
			initDelay = initDelay * speedFactor;
			mainDelay = mainDelay * speedFactor; 
			speed = speed * speedFactor; 
		}

		$(container).addClass('animated');
		setTimeout( function() {
			$(container).parent().find(theDiv+".animated").waypoint(function() {
				var thisDiv = $(this.element), divIndex = thisDiv.prevAll(theDiv).length;
				thisDiv.css({ "animation-duration": speed+"s", "animation-timing-function": convertBezier(easing) });
				if ( divIndex > 6 ) {
					theDelay = mainDelay;	
				} else {
					theDelay = divIndex * mainDelay;	
				}
				if ( currEffect === effect2 ) { 			
					setTimeout( function () { 
						thisDiv.addClass(effect1); 
					}, theDelay);
					currEffect = effect1;
				} else {
					setTimeout( function () { 
						thisDiv.addClass(effect2); 
					}, theDelay);
					currEffect = effect2;
				}						
				this.destroy();
			}, { offset: offset });
		}, initDelay);

	};

// Animate grid elements
	window.animateGrid = function(container, effect1, effect2, effect3, initDelay, mainDelay, offset, mobile, speed, easing) {
		initDelay = initDelay || 0;		
		mainDelay = mainDelay || 100;		
		offset = offset || "100%";
		mobile = mobile || "false";
		speed = speed || 1000;
		speed = speed / 1000;
		easing = easing || 'ease';

		var getDiv = container.split(' '), theDiv = getDiv.pop(), i, j;
		if ( pageViews > pageLimit ) { 
			initDelay = initDelay * speedFactor; 
			mainDelay = mainDelay * speedFactor; 
			speed = speed * speedFactor; 
		}

		$(container).addClass('animated');
		$(container).parent().each(function() {
			var theRow = $(this), findCol = theRow.find(theDiv+".animated").length;
			if (findCol == 1) { 
				theRow.find(theDiv+".animated").data("animation", { effect:effect1, delay:0});
			} else if (findCol == 2) { 
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", {effect:effect2, delay:0});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", {effect:effect3, delay:1});
			} else if (findCol == 3) { 
				theRow.find(theDiv+".animated:nth-last-child(3)").data("animation", {effect:effect2, delay:0});
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", {effect:effect1, delay:1});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", {effect:effect3, delay:2});
			} else if (findCol == 4) { 
				theRow.find(theDiv+".animated:nth-last-child(4)").data("animation", {effect:effect2, delay:0});
				theRow.find(theDiv+".animated:nth-last-child(3)").data("animation", {effect:effect1, delay:1});
				theRow.find(theDiv+".animated:nth-last-child(2)").data("animation", {effect:effect1, delay:2});
				theRow.find(theDiv+".animated:nth-last-child(1)").data("animation", {effect:effect3, delay:3});
			} else {			
				for (i=0; i < findCol; i++) {
					j = i + 1;					
					theRow.find(theDiv+".animated:nth-child("+j+")").data("animation", {effect:effect1, delay:i});	
				} 
			}
		});
		
		$(container).parent().find(theDiv+".animated").waypoint(function() {
			var thisDiv = $(this.element), theDelay = (mainDelay * thisDiv.data("animation").delay) + initDelay, effect = thisDiv.data("animation").effect;
			thisDiv.css({ "animation-duration": speed+"s", "animation-timing-function": convertBezier(easing) });
			if ( getDeviceW() > mobileCutoff || mobile == "true" ) { 
				setTimeout( function () { 
					thisDiv.addClass(effect);
				}, theDelay);
			} else {			
				effect = effect.replace("Down", "Up");			
				thisDiv.addClass(effect);				
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
			var theEl = $(this.element);
			theEl.css({ "transition-duration": speed+"s"});
			setTimeout( function () { 
				theEl.removeClass('animate'); 
			}, initDelay);		
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
		theEl.bind("webkitAnimationEnd mozAnimationEnd msAnimationEnd oAnimationEnd animationEnd", function() { 
			$(this).removeClass(animateClass);
		});
		
		if ( inOut == "in" ) {
			theEl.mouseenter(function() { 
				$(this).addClass(animateClass); 
			});	
		} else if ( inOut == "out" ) {
			theEl.mouseleave(function() { 
				$(this).addClass(animateClass); 
			});	
		} else {
			theEl.hover(function() { 
				$(this).addClass(animateClass); 
			});
		}
	};

// Split string into characters for animation
	window.animateCharacters = function(container, effect1, effect2, initDelay, charDelay, offset, words) {	
		$(container).each(function() {	
			var theContainer = $(this);			
			initDelay = initDelay || 0;		
			charDelay = charDelay || 100;		
			offset = offset || "100%";
			words = words || "false";
			if ( pageViews > pageLimit ) { 
				initDelay = initDelay * speedFactor; 
				charDelay = charDelay * speedFactor; 
			}
			var theDelay = initDelay, testWords = theContainer.html();	
			
			if ( testWords.includes("<") == false ) {
				if ( words != "false" ) {		
					var strWords = theContainer.html().split(" "), strLen = strWords.length, strContents = "", i, currEffect = effect1;			
					for (i=0; i < strLen; i++) {
						if ( i == strLen-1 ) {
							strContents += '<div class="wordSplit animated">' + strWords[i];
						} else {
							strContents += '<div class="wordSplit animated">' + strWords[i] + '&nbsp;</div>';
						}
					}
				} else {
					var strWords = theContainer.html().split(""), strLen = strWords.length, strContents = "", i, currEffect = effect1;			
					for (i = 0; i < strLen; i++) {
						if ( strWords[i] === " " ) { 
							strWords[i] = "&nbsp;";
						}
						strContents += '<div class="charSplit animated">' + strWords[i] + '</div>';
					}
				}			

				theContainer.html(strContents);	

				theContainer.find(".charSplit.animated, .wordSplit.animated" ).waypoint(function() {
					var thisDiv = $(this.element);
					if ( currEffect === effect2 ) { 
						setTimeout( function () { thisDiv.addClass(effect1); }, theDelay);
						currEffect = effect1;
					} else {
						setTimeout( function () { thisDiv.addClass(effect2); }, theDelay);
						currEffect = effect2;
					}				
					this.destroy();
					theDelay = theDelay + charDelay;				
				}, { offset: offset });
			}
		});
	};

/*--------------------------------------------------------------
# Set up pages
--------------------------------------------------------------*/

// Remove empty & restricted elements
	$('p:empty, .archive-intro:empty, div.restricted, div.restricted + ul, li.menu-item + ul.sub-menu').each(function() {
		var el = $(this);
		if ( !el.attr('role') || el.attr('role') != "status" ) {
			el.remove();
		}
	});
	
// Add .page-begins to the next section under masthead for purposes of locking .top-strip
	$('#masthead + section').addClass('page-begins');

// Add "noFX" class to img if it appears in any of the parent divs
	$( "div.noFX" ).find("img, a").addClass("noFX");

// Add .fa class to all icons using .far, .fas and .fab
	$( ".far, .fas, .fab" ).addClass("fa");
	
// Set first page cookie		
	if ( !getCookie('first-page') ) { 
		$('body').addClass('first-page');
		setCookie('first-page', 'no');
	} else {
		$('body').addClass('not-first-page');
	}

// Set unique ID & pages viewed cookies
	if ( !getCookie('unique-id') ) { 
		var unique_id = Math.floor(Date.now()) + '' + Math.floor((Math.random() * 900) + 99);
		setCookie('unique-id', unique_id);
		setCookie('pages-viewed', 1);
	} else {
		var page_views = getCookie('pages-viewed');
		page_views++;
		setCookie('pages-viewed', page_views);
	}

// Set optimized page as new home page
	if ( $('body').hasClass('single-optimized') ) {
		var get_optimized_loc = location.pathname + location.search, new_home = get_optimized_loc.replace(/\//g, "");		
		setCookie('home-url', new_home);
	}
	
	if ( getCookie('home-url') ) { 	
		$('a[href="'+window.location.origin+'"], a[href="'+window.location.origin+'/"]').each(function() {
			$(this).attr("href", window.location.origin + "/" + getCookie('home-url') );	
		});
	}	

// Augment URLs with location data, if multi-location site
	if ( getCookie('site-location') || getUrlVar('l') != null ) { 
		var siteLoc = getCookie('site-location');
		if ( getUrlVar('l') != null ) { siteLoc = getUrlVar('l'); }

		$('a').each(function() {
			var origLink = String($(this).attr("href")), append = '?';
			if ( !$(this).hasClass('loc-ignore') && !origLink.includes('#') && !origLink.includes('tel:') && origLink != 'undefined' ) {
				if ( origLink.includes('?') ) {	append = '&'; }
				$(this).attr("href", $(this).attr("href") + append + 'l=' + siteLoc );	
			}
		});
	}	
	
// Add unique id to labels & inputs in #request-quote-modal	for ADA compliance		
	$('#request-quote-modal div.form-input').each(function() {
		var theLabel = $(this).find('label'), theInput = $(this).find('input'), theAttr = theInput.attr('id');
		theLabel.attr('for', 'modal-'+theAttr);			
		theInput.attr('id', 'modal-'+theAttr);			
	});	
	
// If modal popup is taller than device height, make it scrollable
	$('.screen-mobile .section.section-lock.position-modal .flex').each(function() {
		if ( $(this).height() > (getDeviceH() - 100) ) {
			$(this).addClass('scrollable');
		}
	});

// Fade in lazy loaded images
	$('img').addClass('unloaded');	
	$('#loader img').removeClass('unloaded');	
	$('img').one('load', function() { 
		$(this).removeClass('unloaded'); 
	}).each(function() { 
		if (this.complete) { 
			$(this).trigger('load'); 
		}
	});	
	
// Add star icons to reviews and ratings
	$('.testimonials-rating').each(function() {
		var getRating = $(this).html(), star = ['far', 'far', 'far', 'far', 'far'], replaceRating, i;		
		for (i=0; i < getRating; i++) { 
			star[i] = 'fas'; 
		}		
		replaceRating = '<span class="rating rating-'+getRating+'-star" aria-hidden="true"><span class="sr-only">Rated '+getRating+' Stars</span>';
		for (i=0; i < 5; i++) { 
			replaceRating += '<i class="fa '+star[i]+' fa-star"></i>';
		}
		replaceRating += '</span>';		
		$(this).html( replaceRating );
	});
	
// Ensure that Form labels have enough width
	$('.wpcf7 form, .wpcf7 form .flex').each(function() {
		var thisForm = $(this), labelMaxW = 0;
		thisForm.find('> .form-input.width-default label').each(function() {
			var thisInput = $(this), labelW = thisInput.width();
			if ( labelW > labelMaxW ) { labelMaxW = labelW }
		});
		thisForm.find('> .form-input.width-default').css({ "grid-template-columns":labelMaxW+"px 1fr" });
	});
	
// Removes double asterisk in required forms
	$('abbr.required, em.required, span.required').text("");
	setTimeout( function () { $('abbr.required, em.required, span.required').text(""); }, 2000);
	
// Move User Switching bar to top
	moveDiv('#user_switching_switch_on','#page','before');

// Add "active" & "hover" classes to menu items, assign roles for ADA compliance	
	$(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr('role','menu').attr('aria-label','Main Menu');
	$(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr('role','menu');
	$(".main-navigation li, .widget-navigation li").attr('role','none');
	$(".main-navigation a[href], .widget-navigation a[href]").attr('role','menuitem');

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

// Animate scrolling when moving up or down a page
	$('a[href^="#"]:not(.carousel-control-next):not(.carousel-control-prev)').on('click', function (e) {
		e.preventDefault();    
		var target = this.hash, compensate = Number($(target).attr('data-hash'));
		if( isNaN(compensate) ) { compensate = 0; } 
		if ( target != "" ) { 
			 if ( $('*'+target).length ) { 
				animateScroll(target, compensate); 
				setTimeout(function(){ 
					animateScroll(target, compensate); 
				}, 100); /* helps re-calculate in case there is a .stuck to account for (Executive Mobile Detailing) */
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
	
// When linking to a #hash, scroll down to ensure it isn't obscurred by locked elements	
	setTimeout(function() { 
		if ( location.hash ) { window.scrollBy ({ top: -(getDeviceH() * 0.13), left: 0, behavior: 'smooth' }); }
	}, 100);		
	
// Set up mobile menu animation
	$('#mobile-navigation li.menu-item-has-children > a').each(function() { $(this).attr('data-href', $(this).attr('href')).attr('href', 'javascript:void(0)'); });
	
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
		if ( $(this).hasClass("active")) { 
			closeMenu();	
		} else { 
			openMenu();
		}
	}); 	

	window.closeSubMenu = function (el) {
		$(el).removeClass("active"); 
		$(el).height(0);
		$(el).prev().attr('href', 'javascript:void(0)');
	};

	window.openSubMenu = function (el, h) {
		$('#mobile-navigation ul.sub-menu').removeClass("active"); 
		$('#mobile-navigation ul.sub-menu').height(0);
		$('#mobile-navigation li.menu-item-has-children > a').attr('href', 'javascript:void(0)');
		$(el).addClass("active"); 
		$(el).height(h+"px");
		setTimeout( function() { 
			$(el).prev().attr('href', $(el).prev().attr('data-href')); 
		}, 500);
	};

	$('#mobile-navigation').addClass("get-sub-heights");

	$('#mobile-navigation ul.sub-menu').each(function() { 
		var theSub = $(this);
		theSub.data('getH', theSub.outerHeight(true) );			
		closeSubMenu(theSub); 
		theSub.parent().click(function() {		
			if ( !theSub.hasClass("active")) { 
				openSubMenu(theSub, theSub.data('getH'));
			} else {
				closeSubMenu(theSub);
			}
		}); 
	});	

	$('#mobile-navigation').removeClass("get-sub-heights");

	$('#mobile-navigation li:not(.menu-item-has-children)').each(function() { 
		$(this).click(function() { 
			closeMenu(); 
		}); 
	});	

// Determine which day of week and add active class on office-hours widget	
	var todayIs = new Date().getDay(), days = ['sun','mon','tue','wed','thu','fri','sat'];
	$('.office-hours .row-'+days[todayIs]).addClass("today");
	
		
// Handle displaying YouTube or Vimeo thumbnail, and then loading video once clicked	
	function activateYouTubeVimeo(div) {
		var iframe = document.createElement('iframe');
		iframe.setAttribute('src', div.dataset.link);
		iframe.setAttribute('frameborder', '0');
		iframe.setAttribute('allowfullscreen', '1');
		iframe.setAttribute('allow', 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture');
		div.parentNode.replaceChild(iframe, div);
	}

	var playerElements = document.getElementsByClassName('video-player');
	for (var n = 0; n < playerElements.length; n++) {
		var videoId = playerElements[n].dataset.id, videoLink = playerElements[n].dataset.link, div = document.createElement('div'), thumbNode = document.createElement('img'), playButton = document.createElement('div');
		div.setAttribute('data-id', videoId);
		div.setAttribute('data-link', videoLink);
		thumbNode.src = playerElements[n].dataset.thumb;
		div.appendChild(thumbNode);
		playButton.setAttribute('class', 'play');
		div.appendChild(playButton);
		div.onclick = function () { activateYouTubeVimeo(this); };
		playerElements[n].appendChild(div);
	}
	
/*--------------------------------------------------------------
# Screen resize
--------------------------------------------------------------*/
	$(window).on( 'load', function() { screenResize(); });
	$(window).resize(function() { screenResize(); }); 

	window.screenResize = function () {		
	// Add class to body to determine which size screen is being viewed
		var thisDeviceW = getDeviceW(), thisDeviceH = getDeviceH();
		$('body').removeClass('screen-5').removeClass('screen-4').removeClass('screen-3').removeClass('screen-2').removeClass('screen-1').removeClass('screen-mobile').removeClass('screen-desktop');
		
		if ( thisDeviceW > 1280 ) { 
			$('body').addClass("screen-5").addClass("screen-desktop"); 
		} else if ( thisDeviceW <= 1280 && thisDeviceW > mobileCutoff ) { 
			$('body').addClass("screen-4").addClass("screen-desktop");
		} else if ( thisDeviceW <= mobileCutoff && thisDeviceW > 860 ) { 
			$('body').addClass("screen-3").addClass("screen-mobile");
		} else if ( thisDeviceW <= 860 && thisDeviceW > 576 ) { 
			$('body').addClass("screen-2").addClass("screen-mobile"); 
		} else {
			$('body').addClass("screen-1").addClass("screen-mobile"); 
		}
		
	// Determine whether or not to leave space at top for mobile menu bar
		if ( thisDeviceW > mobileCutoff ) { 
			mobileMenuBarH = 0; 
		} else { 
			mobileMenuBarH = $("#mobile-menu-bar").outerHeight(); 
		}
		
	// Close any open menus on mobile (when device ratio changes)
		if ( ! $('#mobile-navigation > #mobile-menu .menu-search-box input[type="search"]').is(":focus") ) { closeMenu(); }
		
	// Shift #secondary below #wrapper-bottom on mobile		
		if ( $('body').hasClass("screen-mobile") && $('body').hasClass("not-first-page") && !$('body').hasClass("move-sidebar") ) {
			moveDiv('.screen-mobile.not-first-page #secondary','#colophon','before');
			$('body').addClass('move-sidebar');
		}
		if ( $('body').hasClass("screen-desktop") && $('body').hasClass("move-sidebar") ) {
			moveDiv('.screen-mobile.not-first-page #secondary','#colophon','before');
			$('body').removeClass('move-sidebar');
		}

	// Ensure "-faux" elements remain correct size
		$('div[class*="-faux"]').each(function() {	
			var fauxDiv = $(this), fauxClass = "."+fauxDiv.attr('class'), mainClass = fauxClass.replace("-faux", "");
			
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
		
	// If total height of page is less than screen height, add min-height to #wrapper-content
		if ( thisDeviceH > $('#page').outerHeight() ) { 
			$('#wrapper-content').addClass("extended"); 
		} else { 
			$('#wrapper-content').removeClass("extended"); 
		}
	};

/*--------------------------------------------------------------
# ADA compliance
--------------------------------------------------------------*/	
	// Add aria-labels to landmarks, sections and titles
	$('h3 a[aria-hidden="true"]').each(function() { $(this).parent().attr( 'aria-label', $(this).text()); });

	// Add alt="" to all images with no alt tag
	setTimeout(function() { $('img:not([alt])').attr('alt', ''); }, 50);
	setTimeout(function() { $('img:not([alt])').attr('alt', ''); }, 1000);

	// Menu support
	$('[role="menu"]' ).on( 'focus.aria mouseenter.aria', '[aria-haspopup="true"]', function ( ev ) { $( ev.currentTarget ).addClass('menu-item-expanded').attr( 'aria-expanded', true ); } );
	$('[role="menu"]' ).on( 'blur.aria mouseleave.aria', '[aria-haspopup="true"]', function ( ev ) { $( ev.currentTarget ).removeClass('menu-item-expanded').attr( 'aria-expanded', false ); } );	
	$('[role="menu"] a' ).attr( 'tabindex', '0' );
	$('li[aria-haspopup="true"]').attr( 'tabindex', '-1' );

/*--------------------------------------------------------------
# Delay parsing of JavaScript
--------------------------------------------------------------*/
	$(window).on( 'load', function() {
	// Fade out pre-loader screen when site is fully loaded
		clearInterval(bgTimer);		
		$("#loader").fadeOut(300, function() { resetLoader(); });
		
	// Fade in pre-loader when changing pages
		window.addEventListener('beforeunload', function (e) { $('#loader').fadeIn(300); });
				
	// Set up Locked Message position, delay, & cookie	
		$('section.section-lock').each(function() {
			var thisLock = $(this), initDelay = thisLock.attr('data-delay'), lockPos = thisLock.attr('data-pos'), cookieExpire = thisLock.attr('data-show'), buttonActivated = thisLock.attr('data-btn');

			if ( cookieExpire == "always" ) { cookieExpire = 0.000001; }
			if ( cookieExpire == "never" ) { cookieExpire = 100000; }			
			if ( cookieExpire == "session" ) { cookieExpire = null; }			

			keyPress(thisLock.find('.closeBtn'));
			
			if ( lockPos == "top" ) {		
				if ( getCookie("display-message") !== "no" ) {
					thisLock.delay(initDelay).css({ "top":mobileMenuBarH+"px" });
					setTimeout( function() { 
						thisLock.addClass("on-screen"); 
						$('body').addClass('locked'); 
						thisLock.focus();
					}, initDelay);
					thisLock.find('.closeBtn').click(function() {
						thisLock.removeClass("on-screen"); 
						$('body').removeClass('locked');
						setCookie("display-message","no",cookieExpire);
					});
				}
			} else if ( lockPos == "bottom" ) { 	
				if ( getCookie("display-message") !== "no" ) {
					thisLock.delay(initDelay).css({ "bottom":"0" });
					setTimeout( function() { 
						thisLock.addClass("on-screen"); 
						$('body').addClass('locked'); 
						thisLock.focus(); 
					}, initDelay);
					thisLock.find('.closeBtn').click(function() {
						thisLock.removeClass("on-screen");
						$('body').removeClass('locked');
						setCookie("display-message","no",cookieExpire);
					});
				}
			} else if ( lockPos == "header" ) { 	
				if ( getCookie("display-message") !== "no" ) {				
					moveDiv(thisLock.find('.closeBtn'), '.section-lock.position-header .col-inner', 'top');
					thisLock.css({ "display":"grid" });
					thisLock.find('.closeBtn').click(function() {
						thisLock.css({ "max-height":0, "padding-top":0, "padding-bottom":0, "margin-top":0, "margin-bottom":0 });
						setCookie("display-message","no",cookieExpire);
					});
				}
			} else { 				
				if ( buttonActivated == "no" && getCookie("display-message") !== "no" ) {
					moveDiv(thisLock.find('.closeBtn'), '.section-lock > .flex', 'top');
					setTimeout( function() { 
						thisLock.addClass("on-screen"); 
						$('body').addClass('locked'); 
						thisLock.focus(); 
					}, initDelay);
					thisLock.find('.closeBtn').click(function() {
						thisLock.removeClass("on-screen"); 
						$('body').removeClass('locked');
						setCookie("display-message","no",cookieExpire);
					});	
				}

				if ( buttonActivated == "yes" ) {				
					$('.modal-btn').click(function() {
						moveDiv(thisLock.find('.closeBtn'), '.section-lock > .flex', 'top');
						thisLock.addClass("on-screen"); 
						$('body').addClass('locked'); 
						thisLock.focus();
					});
					thisLock.find('.closeBtn').click(function() {
						thisLock.removeClass("on-screen"); 
						$('body').removeClass('locked');
					});						
				}
			}			
			thisLock.click(function() {
				setCookie("display-message","no",cookieExpire);
			});			
		});		
	});
	
})(jQuery); });