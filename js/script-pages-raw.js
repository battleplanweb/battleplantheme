document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Set up sidebar
# Set up animation
# Set up pages
# Screen resize
# ADA compliance
# Delay parsing of JavaScript

--------------------------------------------------------------*/

	var mobileCutoff = getMobileCutoff(), tabletCutoff = getTabletCutoff();		
		
/*--------------------------------------------------------------
# Set up sidebar
--------------------------------------------------------------*/
	window.setupSidebar = function (compensate, sidebarScroll) {
		sidebarScroll = sidebarScroll || "true";
		compensate = compensate || 0;
		
// Add classes for first, last, even and odd widgets
		window.labelWidgets = function () {
			$(".widget:not(.hide-widget)").first().addClass("widget-first");  
			$(".widget:not(.hide-widget)").last().addClass("widget-last"); 
		};		

// Shuffle non-locked widgets
		var $shuffledWidgets = $('.widget:not(.lock-to-top):not(.lock-to-bottom):not(.widget-financing):not(.widget-event)'), $parent = $shuffledWidgets.parent(), $bottomWidgets = $(".widget.lock-to-bottom").detach(), $financingWidgets = $('.widget.widget-financing').detach(), $eventWidgets = $('.widget.widget-event').detach();

		$shuffledWidgets.detach();
		$parent.append( shuffleElements($eventWidgets) );
		$parent.append( shuffleElements($financingWidgets) );
		$parent.append( shuffleElements($shuffledWidgets) );
		$parent.append( $bottomWidgets );
		
		if ( $('body').hasClass('screen-mobile') ) {
			labelWidgets();
		} else {
			desktopSidebar(compensate, sidebarScroll);
		}
	};	
	
/*--------------------------------------------------------------
# Set up animation
--------------------------------------------------------------*/
	
var pageViews=getCookie('pages-viewed'), pageLimit = 300, speedFactor = 0.5;

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
	
// Overflow: assist in hiding overflow during animation and then making it visible again
	window.animateOverflow = function(container, delay) {
		delay = delay || 2000;		
		$(container).css({"overflow":"hidden"});
		setTimeout(function(){ $(container).css({"overflow":"visible"}); }, delay);
	};

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
	
// Remove current page from footer-menu
	$('ul#footer-menu').find('a[href="'+window.location+'"').parent('li').css({"display":"none"});
	
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
		var unique_id = Math.floor(Date.now()) + '' + Math.floor((Math.random() * 99)) + '' + Math.floor((Math.random() * 99));
		unique_id = unique_id.slice(3);
		setCookie('unique-id', unique_id);
		setCookie('pages-viewed', 1);
	} else {
		var page_views = getCookie('pages-viewed');
		page_views++;
		setCookie('pages-viewed', page_views);
	}

// Set city-specific landing page as home-url cookig
	if ( $('body').hasClass('alt-home') ) {
		var get_alt_loc = location.pathname + location.search, new_home = get_alt_loc.replace(/\//g, "");		
		setCookie('home-url', new_home);
	}
	
// Set city-specific landing page as new home page
	if ( getCookie('home-url') ) { 	
		$('a[href="'+window.location.origin+'"], a[href="'+window.location.origin+'/"]').each(function() {
			$(this).attr("href", window.location.origin + "/" + getCookie('home-url') );	
		});
	}
	
// Set Google Ads landing page as site-loc cookie
	if ( site_loc != null ) {
		setCookie('site-loc', site_loc);
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
		var theLabel = $(this).find('label'), theInput = $(this).find('input'), theTextarea = $(this).find('textarea'), theSelect = $(this).find('select'), theAttr = theInput.attr('id');
		
		if ( theAttr == 'undefined' ) {
			theAttr = theTextarea.attr('id');
			if ( theAttr == 'undefined' ) {
				theAttr = theSelect.attr('id'); 
			}
		}		
		 
		theLabel.attr('for', 'modal-'+theAttr);			
		theInput.attr('id', 'modal-'+theAttr);			
		theTextarea.attr('id', 'modal-'+theAttr);			
		theSelect.attr('id', 'modal-'+theAttr);			
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
	setTimeout( function () { $('abbr.required, em.required, span.required').text(""); }, 6000);
	
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
		$(".top-push.screen-mobile .top-strip.stuck").css({ "top": mobileMenuBarH()+"px" });
	};

	window.openMenu = function () {
		$("#mobile-menu-bar .activate-btn").addClass("active"); 
		$("body").addClass("mm-active"); 
		var getMenuH = $("#mobile-navigation").outerHeight();
		var getTotalH = getMenuH + mobileMenuBarH();
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
	
	// Add 'alt' class to sections to trigger the alternate input & button styles
	window.addAltStyle = function (sections, style) {
		style = style || 'style-alt';
		$(sections).addClass(style); 
	};
	
	// Get link from a href and attach to the li (for use with Menu BG)	
	$('#desktop-navigation ul.main-menu > li:not(.menu-item-has-children), #desktop-navigation ul.sub-menu > li').click(function() {
		var btn = $(this), link = btn.find('a').attr('href');	
		window.location.href = link;
	});
	
	// Move ad promo on blog pages	
	if ( $('.ad-promo').length ) {
		var numP = $('.single-post .entry-content *').length, posP = Math.ceil(numP / 2);	
		if ( numP > 30 ) { posP = 10; }
		
		if ( $('div.insert-promo').length > 0 ) {
			moveDiv ('.ad-promo', '.single-post .entry-content div.insert-promo', 'before'); 
			$('div.insert-promo').remove();
		} else if ( $('.single-post .entry-content h2').length > 1 ) {
			moveDiv ('.ad-promo', '.single-post .entry-content h2:nth-of-type(2)', 'before'); 
		} else if ( $('.single-post .entry-content h2').length == 1 ) {
			moveDiv ('.ad-promo', '.single-post .entry-content h2', 'before'); 		
		} else {
			moveDiv ('.ad-promo', '.single-post .entry-content *:nth-child('+posP+')', 'after'); 
		}
	}
	
	// Blog Archive page - tag list drop-down functionality
	$('#tag-dropdown').change(function() {
    		var tagLink = $(this).val();
    		if (tagLink) { window.location.href = tagLink; }
  	});	
	
	// Apply border-radius from img.testimonial to anonymous svg
	var iconRadius = $('.img-testimonials').css('border-radius'), iconBorder = $('.img-testimonials').css('border');
	$('.anonymous-icon').css({'border-radius':iconRadius, 'border':iconBorder});	
	
	// Allow sub-menu to appear, even if initially set to overflow:hidden	
	setTimeout(function() {
		$('.menu-clip .menu-strip').css({'overflow':'visible'});
	}, 2500);
	
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
		
	// Resize video on mobile, if necessary
		if ( thisDeviceW <= 860 ) { 
			$('.block-video video[data-mobile-w]').each(function() {
				var mobileW = $(this).attr('data-mobile-w'), mobileL = -((mobileW-100)/2);
				$(this).css({'width':mobileW+'%', 'left':mobileL+'%'});
			})
		} else {
			$('.block-video video[data-mobile-w]').each(function() {
				$(this).css({ 'width':'100%', 'left':'0%' });
			})
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
	
	// Handle multiple Google review locations on mobile
		if ( getDeviceW() > mobileCutoff ) {
			$('.wp-google-badge').find('.wp-google-badge-btn').css({ 'display':'block' });
		} else {		
			if ( $('.wp-google-badge').find('.wp-google-badge-btn').length > 1 ) {
				$('.wp-google-badge').find('.wp-google-badge-btn').css({ 'display':'none' });
				var rand = Math.floor(Math.random() * $('.wp-google-badge').find('.wp-google-badge-btn').length) + 1;
				$('.wp-google-badge').find('.wp-google-badge-btn:nth-of-type('+rand+')').css({ 'display':'block' });
			}	
		}
		
	// Position side-by-side images that have borders, outlines, etc.
		$('ul.side-by-side').each(function() {
			var chk_image = $(this).find('img'), cssFilter = chk_image.css('filter'), dropShadowComponents = cssFilter.split("drop-shadow(").slice(1), chk_first, chk_second, totalWidth = 0;   
			var chk_filter = dropShadowComponents.map(function(component) {
				chk_first = parseFloat(component.split(" ")[3].replace(/[)-]|px/g, '')), chk_second = parseFloat(component.split(" ")[4].replace(/[)-]|px/g, ''));

				if ( chk_first < 1 && chk_first > 0 ) {
					totalWidth = totalWidth + chk_second;
				} else {
					totalWidth = totalWidth + chk_first;
				}					
			});

			totalWidth = totalWidth + parseInt(chk_image.css('borderLeftWidth')) + parseInt(chk_image.css('borderRightWidth')) + parseInt(chk_image.css('outlineWidth'));
			
			$(this).css({"padding":totalWidth+'px', "margin-left":-(totalWidth/2)+"px"});
		})
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
	$(window).on('pageshow', function(event) {
  		// Check if the page is loaded from the cache or not
  		if (event.originalEvent.persisted || window.performance && window.performance.navigation.type === 2) {
    		// Page is loaded from the cache or navigated using the back button
    			clearInterval(bgTimer);
    			$("#loader").fadeOut(300, function() { resetLoader(); });
		}
	});
	
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
					thisLock.delay(initDelay).css({ "top":mobileMenuBarH()+"px" });
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
				$('video').each(function(index) {
            		this.pause();
					this.currentTime = 0;
        		});
			});			
		});		
	});
	
})(jQuery); });