document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Basic site functionality
# DOM level functions

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Basic site functionality
--------------------------------------------------------------*/

	var mobileCutoff = 1024, tabletCutoff = 576;	

// Determine whether or not to leave space for mobile menu header
	window.mobileMenuBarH = function () {
	if ( getDeviceW() > mobileCutoff ) { 
			return 0; 
		} else {  
			return $("#mobile-menu-bar").outerHeight(); 
		}
	};			
	
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
		/*
		$.post({
			url : ajaxURL,
			data : { action: "count_link_clicks", type: thisAction }, 
			success: function( response ) { console.log(response); }
		});	
		*/
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
		document.cookie = cname + "=" + cvalue + "; " + expires + "; path=/; SameSite=Strict; secure"; 
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
	
// Shuffle set of jQuery elements
	window.shuffleElements = function (array) {		
		var count = array.length, shuffled_array = [], newArray = [], i, index1, index2, temp_val;

		for (i = 0; i < count; i++) { shuffled_array.push(i); }
		
		for (i = 0; i < count; i++) {
			index1 = (Math.random() * count) | 0;
			index2 = (Math.random() * count) | 0;
			temp_val = shuffled_array[index1];
			shuffled_array[index1] = shuffled_array[index2];
			shuffled_array[index2] = temp_val;
		}		
		
		for (i = 0; i < count; i++) { newArray[i] = array.eq(shuffled_array[i]); }
		
		return newArray;
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
			if ( faux == "true" ) { 
				addFaux(element.split(" ").pop()); 
			}
			$(element).addClass("stuck");
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
			var newTop = mobileMenuBarH();
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
		topSpacer = topSpacer + mobileMenuBarH();	

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
		topSpacer = topSpacer + mobileMenuBarH();	
		var origDisplay = $(container).css( "display" );		

		$(container).css( "display","none");	
		$(button).click(function(){
			$(container).css( "display",origDisplay);
			animateScroll(container, topSpacer, initSpeed);
		});
	};
	
	// Set up Review Questions & Redirect
	/*
	$('.review-form:first').addClass('active');
	$('.review-form #gmail-yes').click(function() { window.location.href = "/google"; });	
	$('.review-form #facebook-yes').click(function() { window.location.href = "/facebook"; });	
	$('.review-form #yelp-yes').click(function() { window.location.href = "/yelp"; });
	
	$('.review-form #gmail-no, .review-form #facebook-no, .review-form #yelp-no').click(function() {
		$(this).closest('.review-form').removeClass('active');
		$(this).closest('.review-form').next().addClass('active');			
	});
	*/
	
	
	
	
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
			if ( $(this).hasClass('reveal-click')) { 
				searchBar.css({ "width": magW+"px" }); 
				searchBar.click(function() {
					searchBar.animate( { "width":inputW+'px' }, 150, function() { if ( typeof centerSubNav === 'function' ) { setTimeout(function() {centerSubNav();}, 300); } });	
				});
			}
			if ( !isApple() ) {
				inputBox.focus(function() {
					var inputPos = -(getPosition(searchBar, 'top') - mobileMenuBarH() - 25);
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
	
})(jQuery); });