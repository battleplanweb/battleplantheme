document.addEventListener("DOMContentLoaded",function(){"use strict";(function(c){function d(a){var b=document.createElement("iframe");b.setAttribute("src",a.dataset.link),b.setAttribute("frameborder","0"),b.setAttribute("allowfullscreen","1"),b.setAttribute("allow","accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"),a.parentNode.replaceChild(b,a)}var e=getMobileCutoff(),f=getTabletCutoff();window.setupSidebar=function(a,b){b=b||"true",a=a||0,window.labelWidgets=function(){c(".widget:not(.hide-widget)").first().addClass("widget-first"),c(".widget:not(.hide-widget)").last().addClass("widget-last")};var d=c(".widget:not(.lock-to-top):not(.lock-to-bottom):not(.widget-financing):not(.widget-event)"),e=d.parent(),f=c(".widget.lock-to-bottom").detach(),g=c(".widget.widget-financing").detach(),h=c(".widget.widget-event").detach();d.detach(),e.append(shuffleElements(h)),e.append(shuffleElements(g)),e.append(shuffleElements(d)),e.append(f),c("body").hasClass("screen-mobile")?labelWidgets():desktopSidebar(a,b)};var h=getCookie("pages-viewed"),k=.5,l=1,m=document.getElementById("loader"),o=getComputedStyle(m).getPropertyValue("background-color"),[p,i,g,b]=o.match(/\d+/g).map(Number),a=setInterval(function(){l-=.01,m.style.backgroundColor="rgb("+p+","+i+","+g+","+l+")",.5>l&&clearInterval(a)},10);if(window.resetLoader=function(){m.style.backgroundColor="rgb("+p+","+i+","+g+",0.5)"},window.convertBezier=function(a){return"easeInCubic"==a?a="cubic-bezier(0.550, 0.055, 0.675, 0.190)":"easeInQuart"==a?a="cubic-bezier(0.895, 0.030, 0.685, 0.220)":"easeInQuint"==a?a="cubic-bezier(0.755, 0.050, 0.855, 0.060)":"easeInExpo"==a?a="cubic-bezier(0.950, 0.050, 0.795, 0.035)":"easeInBack"==a?a="cubic-bezier(0.600, -0.280, 0.735, 0.045)":"easeOutCubic"==a?a="cubic-bezier(0.215, 0.610, 0.355, 1.000)":"easeOutQuart"==a?a="cubic-bezier(0.165, 0.840, 0.440, 1.000)":"easeOutQuint"==a?a="cubic-bezier(0.230, 1.000, 0.320, 1.000)":"easeOutExpo"==a?a="cubic-bezier(0.190, 1.000, 0.220, 1.000)":"easeOutBack"==a?a="cubic-bezier(0.175, 0.885, 0.320, 1.275)":"easeInOutCubic"==a?a="cubic-bezier(0.645, 0.045, 0.355, 1.000)":"easeInOutQuart"==a?a="cubic-bezier(0.770, 0.000, 0.175, 1.000)":"easeInOutQuint"==a?a="cubic-bezier(0.860, 0.000, 0.070, 1.000)":"easeInOutExpo"==a?a="cubic-bezier(1.000, 0.000, 0.000, 1.000)":"easeInOutBack"==a&&(a="cubic-bezier(0.680, -0.550, 0.265, 1.550)"),a},window.animateOverflow=function(a,b){b=b||2e3,c(a).css({overflow:"hidden"}),setTimeout(function(){c(a).css({overflow:"visible"})},b)},window.animateDiv=function(a,b,d,e,f,g){d=d||0,e=e||"100%",f=f||1e3,f/=1e3,g=g||"ease";var i=parseFloat(c(a).css("transition-duration")),j=parseFloat(c(a).css("transition-delay"));300<h&&(d*=k,f*=k,i*=k,j*=k),c(a).addClass("animated").css({"animation-duration":f+"s","transition-duration":i+"s","transition-delay":j+"s","animation-timing-function":convertBezier(g)}),c(a+".animated").waypoint(function(){var a=c(this.element);setTimeout(function(){a.addClass(b)},d),this.destroy()},{offset:e})},window.animateDivs=function(a,b,d,e,f,g,i,j){e=e||0,f=f||100,g=g||"100%",i=i||1e3,i/=1e3,j=j||"ease";var l=0,m=b,n=a.split(" "),o=n.pop();300<h&&(e*=k,f*=k,i*=k),c(a).addClass("animated"),setTimeout(function(){c(a).parent().find(o+".animated").waypoint(function(){var a=c(this.element),e=a.prevAll(o).length;a.css({"animation-duration":i+"s","animation-timing-function":convertBezier(j)}),l=6<e?f:e*f,m===d?(setTimeout(function(){a.addClass(b)},l),m=b):(setTimeout(function(){a.addClass(d)},l),m=d),this.destroy()},{offset:g})},e)},window.animateGrid=function(a,b,d,f,g,l,m,n,o,p){g=g||0,l=l||100,m=m||"100%",n=n||"false",o=o||1e3,o/=1e3,p=p||"ease";var q,r,s=a.split(" "),t=s.pop();300<h&&(g*=k,l*=k,o*=k),c(a).addClass("animated"),c(a).parent().each(function(){var a=c(this),e=a.find(t+".animated").length;if(1==e)a.find(t+".animated").data("animation",{effect:b,delay:0});else if(2==e)a.find(t+".animated:nth-last-child(2)").data("animation",{effect:d,delay:0}),a.find(t+".animated:nth-last-child(1)").data("animation",{effect:f,delay:1});else if(3==e)a.find(t+".animated:nth-last-child(3)").data("animation",{effect:d,delay:0}),a.find(t+".animated:nth-last-child(2)").data("animation",{effect:b,delay:1}),a.find(t+".animated:nth-last-child(1)").data("animation",{effect:f,delay:2});else if(4==e)a.find(t+".animated:nth-last-child(4)").data("animation",{effect:d,delay:0}),a.find(t+".animated:nth-last-child(3)").data("animation",{effect:b,delay:1}),a.find(t+".animated:nth-last-child(2)").data("animation",{effect:b,delay:2}),a.find(t+".animated:nth-last-child(1)").data("animation",{effect:f,delay:3});else for(q=0;q<e;q++)r=q+1,a.find(t+".animated:nth-child("+r+")").data("animation",{effect:b,delay:q})}),c(a).parent().find(t+".animated").waypoint(function(){var a=c(this.element),b=l*a.data("animation").delay+g,d=a.data("animation").effect;a.css({"animation-duration":o+"s","animation-timing-function":convertBezier(p)}),getDeviceW()>e||"true"==n?setTimeout(function(){a.addClass(d)},b):(d=d.replace("Down","Up"),a.addClass(d)),this.destroy()},{offset:m})},window.animateCSS=function(a,b,d,e){b=b||0,d=d||"100%",e=e||1e3,e/=1e3,c(a).addClass("animate"),c(a+".animate").waypoint(function(){var a=c(this.element);a.css({"transition-duration":e+"s"}),setTimeout(function(){a.removeClass("animate")},b),this.destroy()},{offset:d})},window.animateBtn=function(a,b,d,e){a=a||".menu",b=b||"li:not(.active)",e=e||"both";var f=c(a).find(b);d=d||"go-animated",f.bind("webkitAnimationEnd mozAnimationEnd msAnimationEnd oAnimationEnd animationEnd",function(){c(this).removeClass(d)}),"in"==e?f.mouseenter(function(){c(this).addClass(d)}):"out"==e?f.mouseleave(function(){c(this).addClass(d)}):f.hover(function(){c(this).addClass(d)})},window.animateCharacters=function(a,b,d,e,f,g,j){c(a).each(function(){var a=c(this);e=e||0,f=f||100,g=g||"100%",j=j||"false",300<h&&(e*=k,f*=k);var l=e,m=a.html();if(!1==m.includes("<")){if("false"!=j){var n,o=a.html().split(" "),p=o.length,q="",r=b;for(n=0;n<p;n++)q+=n==p-1?"<div class=\"wordSplit animated\">"+o[n]:"<div class=\"wordSplit animated\">"+o[n]+"&nbsp;</div>"}else{var n,o=a.html().split(""),p=o.length,q="",r=b;for(n=0;n<p;n++)" "===o[n]&&(o[n]="&nbsp;"),q+="<div class=\"charSplit animated\">"+o[n]+"</div>"}a.html(q),a.find(".charSplit.animated, .wordSplit.animated").waypoint(function(){var a=c(this.element);r===d?(setTimeout(function(){a.addClass(b)},l),r=b):(setTimeout(function(){a.addClass(d)},l),r=d),this.destroy(),l+=f},{offset:g})}})},c("p:empty, .archive-intro:empty, div.restricted, div.restricted + ul, li.menu-item + ul.sub-menu").each(function(){var a=c(this);a.attr("role")&&"status"==a.attr("role")||a.remove()}),c("ul#footer-menu").find("a[href=\""+window.location+"\"").parent("li").css({display:"none"}),c("#masthead + section").addClass("page-begins"),c("div.noFX").find("img, a").addClass("noFX"),getCookie("first-page")?c("body").addClass("not-first-page"):(c("body").addClass("first-page"),setCookie("first-page","no")),!getCookie("unique-id")){var j=Math.floor(Date.now())+""+Math.floor(99*Math.random())+""+Math.floor(99*Math.random());j=j.slice(3),setCookie("unique-id",j),setCookie("pages-viewed",1)}else{var q=getCookie("pages-viewed");q++,setCookie("pages-viewed",q)}if(c("body").hasClass("alt-home")){var r=location.pathname+location.search,s=r.replace(/\//g,"");setCookie("home-url",s)}if(getCookie("home-url")&&c("a[href=\""+window.location.origin+"\"], a[href=\""+window.location.origin+"/\"]").each(function(){c(this).attr("href",window.location.origin+"/"+getCookie("home-url"))}),"undefined"!=typeof site_loc&&null!=site_loc&&setCookie("site-loc",site_loc),getCookie("site-location")||null!=getUrlVar("l")){var t=getCookie("site-location");null!=getUrlVar("l")&&(t=getUrlVar("l")),c("a").each(function(){var a=c(this).attr("href")+"",b="?";c(this).hasClass("loc-ignore")||a.includes("#")||a.includes("tel:")||"undefined"==a||(a.includes("?")&&(b="&"),c(this).attr("href",c(this).attr("href")+b+"l="+t))})}c("div.form-input").each(function(){var a=c(this).find("label"),b=a.attr("for"),d=c(this).find("input"),e=c(this).find("textarea"),f=c(this).find("select"),g=d.attr("id");d.attr("id",b),e.attr("id",b),f.attr("id",b)}),c("#request-quote-modal div.form-input").each(function(){var a=c(this).find("label"),b=c(this).find("input"),d=c(this).find("textarea"),e=c(this).find("select"),f=b.attr("id");"undefined"==f&&(f=d.attr("id"),"undefined"==f&&(f=e.attr("id"))),a.attr("for","modal-"+f),b.attr("id","modal-"+f),d.attr("id","modal-"+f),e.attr("id","modal-"+f)}),c(".screen-mobile .section.section-lock.position-modal .flex").each(function(){c(this).height()>getDeviceH()-100&&c(this).addClass("scrollable")}),c("img").addClass("unloaded"),c("#loader img").removeClass("unloaded"),c("img").one("load",function(){c(this).removeClass("unloaded")}).each(function(){this.complete&&c(this).trigger("load")}),c(".testimonials-rating").each(function(){var a,b,d=c(this).html(),e=["star-o","star-o","star-o","star-o","star-o"];for(b=0;b<d;b++)e[b]="star";for(a="<span class=\"rating rating-"+d+"-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated "+d+" Stars</span>",b=0;5>b;b++)a+="<span class=\"icon "+e[b]+"\"></span>";a+="</span>",c(this).html(a)});var u=c("svg.anonymous-icon").height();c(".testimonials-generic-letter").css({height:u+"px"}),window.formLabelWidth=function(){c(".wpcf7 form, .wpcf7 form .flex").each(function(){var a=c(this),b=0;a.find("> .form-input.width-default label").each(function(){var a=c(this),d=a.width();d>b&&(b=d)}),0<b&&a.find("> .form-input.width-default").css({"grid-template-columns":b+"px 1fr"})})},formLabelWidth(),setTimeout(function(){formLabelWidth()},500),c("abbr.required, em.required, span.required").text(""),setTimeout(function(){c("abbr.required, em.required, span.required").text("")},2e3),setTimeout(function(){c("abbr.required, em.required, span.required").text("")},6e3),moveDiv("#user_switching_switch_on","#page","before"),c(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr("role","menu").attr("aria-label","Main Menu"),c(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr("role","menu"),c(".main-navigation li, .widget-navigation li").attr("role","none"),c(".main-navigation a[href], .widget-navigation a[href]").attr("role","menuitem");var v=c(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor");v.addClass("active"),v.find(">a").attr("aria-current","page"),c(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li").hover(function(){v.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),v.replaceClass("dormant","active")});var w=c(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");if(w.addClass("active"),w.find(">a").attr("aria-current","page"),c(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li").hover(function(){w.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),w.replaceClass("dormant","active")}),c("a[href^=\"#\"]:not(.carousel-control-next):not(.carousel-control-prev)").on("click",function(a){a.preventDefault();var b=this.hash,d=+c(b).attr("data-hash");isNaN(d)&&(d=0),""!=b&&(c("*"+b).length?(animateScroll(b,d),setTimeout(function(){animateScroll(b,d)},100)):window.location.href="/"+b)}),c(".menu-item:not(.no-highlight) a[href^=\"#\"]").is(":visible")){var x=c("nav:visible").find("ul"),y=.35*c(window).outerHeight(),z=x.outerHeight()+y,A=x.find("a[href^=\"#\"]"),B=A.map(function(){var a=c(c(this).attr("href"));if("none"!=c(this).parent().css("display"))return a});c(window).scroll(function(){var a,b,d=c(this).scrollTop()+z,e=B.map(function(){void 0!==c(this).offset()&&c(this).offset().top<d&&(a="#"+c(this)[0].id,clearTimeout(b),b=setTimeout(function(){x.find("li").removeClass("current-menu-item").removeClass("current_page_item").removeClass("active"),x.find("a[href^=\""+a+"\"]").closest("li").addClass("current-menu-item").addClass("current_page_item").addClass("active")},10),closeMenu())})})}setTimeout(function(){location.hash&&window.scrollBy({top:-(.13*getDeviceH()),left:0,behavior:"smooth"})},100),c("#mobile-navigation li.menu-item-has-children > a").each(function(){c(this).attr("data-href",c(this).attr("href")).attr("href","javascript:void(0)")}),window.closeMenu=function(){c("#mobile-menu-bar .activate-btn").removeClass("active"),c("body").removeClass("mm-active"),c(".top-push.screen-mobile #page").css({top:"0"}),c(".top-push.screen-mobile .top-strip.stuck").css({top:mobileMenuBarH()+"px"})},window.openMenu=function(){c("#mobile-menu-bar .activate-btn").addClass("active"),c("body").addClass("mm-active");var a=c("#mobile-navigation").outerHeight(),b=a+mobileMenuBarH();c(".top-push.screen-mobile.mm-active #page").css({top:a+"px"}),c(".top-push.screen-mobile.mm-active .top-strip.stuck").css({top:b+"px"})},c("#mobile-menu-bar .activate-btn").click(function(){c(this).hasClass("active")?closeMenu():openMenu()}),window.closeSubMenu=function(a){c(a).removeClass("active"),c(a).height(0),c(a).prev().attr("href","javascript:void(0)")},window.openSubMenu=function(a,b){c("#mobile-navigation ul.sub-menu").removeClass("active"),c("#mobile-navigation ul.sub-menu").height(0),c("#mobile-navigation li.menu-item-has-children > a").attr("href","javascript:void(0)"),c(a).addClass("active"),c(a).height(b+"px"),setTimeout(function(){c(a).prev().attr("href",c(a).prev().attr("data-href"))},500)},c("#mobile-navigation").addClass("get-sub-heights"),c("#mobile-navigation ul.sub-menu").each(function(){var a=c(this);a.data("getH",a.outerHeight(!0)),closeSubMenu(a),a.parent().click(function(){a.hasClass("active")?closeSubMenu(a):openSubMenu(a,a.data("getH"))})}),c("#mobile-navigation").removeClass("get-sub-heights"),c("#mobile-navigation li:not(.menu-item-has-children)").each(function(){c(this).click(function(){closeMenu()})});var C=new Date().getDay();c(".office-hours .row-"+["sun","mon","tue","wed","thu","fri","sat"][C]).addClass("today");for(var D=document.getElementsByClassName("video-player"),E=0;E<D.length;E++){var F=D[E].dataset.id,G=D[E].dataset.link,H=document.createElement("div"),I=document.createElement("img"),J=document.createElement("div");H.setAttribute("data-id",F),H.setAttribute("data-link",G),I.src=D[E].dataset.thumb,H.appendChild(I),J.setAttribute("class","play"),H.appendChild(J),H.onclick=function(){d(this)},D[E].appendChild(H)}if(window.addAltStyle=function(a,b){b=b||"style-alt",c(a).addClass(b)},c("#desktop-navigation ul.main-menu > li:not(.menu-item-has-children), #desktop-navigation ul.sub-menu > li").click(function(){var a=c(this),b=a.find("a").attr("href");window.location.href=b}),c(".place-ad").length){var K=c(".single-post .entry-content *").length,L=Math.ceil(K/2);30<K&&(L=10),0<c("div.insert-promo").length?(moveDiv(".place-ad",".single-post .entry-content div.insert-promo","before"),c("div.insert-promo").remove()):1<c(".single-post .entry-content h2").length?moveDiv(".place-ad",".single-post .entry-content h2:nth-of-type(2)","before"):1==c(".single-post .entry-content h2").length?moveDiv(".place-ad",".single-post .entry-content h2","before"):moveDiv(".place-ad",".single-post .entry-content *:nth-child("+L+")","after")}c("#tag-dropdown").change(function(){var a=c(this).val();a&&(window.location.href=a)});var M=c(".img-testimonials").css("border-radius"),N=c(".img-testimonials").css("border");c(".anonymous-icon").css({"border-radius":M,border:N}),setTimeout(function(){c(".menu-clip .menu-strip").css({overflow:"visible","clip-path":"none"})},2500),c(window).on("load",function(){screenResize()}),c(window).resize(function(){screenResize()}),window.screenResize=function(){var a=getDeviceW(),b=getDeviceH();if(c("body").removeClass("screen-5").removeClass("screen-4").removeClass("screen-3").removeClass("screen-2").removeClass("screen-1").removeClass("screen-mobile").removeClass("screen-desktop"),1280<a?c("body").addClass("screen-5").addClass("screen-desktop"):1280>=a&&a>e?c("body").addClass("screen-4").addClass("screen-desktop"):a<=e&&860<a?c("body").addClass("screen-3").addClass("screen-mobile"):860>=a&&576<a?c("body").addClass("screen-2").addClass("screen-mobile"):c("body").addClass("screen-1").addClass("screen-mobile"),860>=a?c(".block-video video[data-mobile-w]").each(function(){var a=c(this).attr("data-mobile-w");c(this).css({width:a+"%",left:-((a-100)/2)+"%"})}):c(".block-video video[data-mobile-w]").each(function(){c(this).css({width:"100%",left:"0%"})}),c("#mobile-navigation > #mobile-menu .menu-search-box input[type=\"search\"]").is(":focus")||closeMenu(),c("body").hasClass("screen-mobile")&&c("body").hasClass("not-first-page")&&!c("body").hasClass("move-sidebar")&&(moveDiv(".screen-mobile.not-first-page #secondary","#colophon","before"),c("body").addClass("move-sidebar")),c("body").hasClass("screen-desktop")&&c("body").hasClass("move-sidebar")&&(moveDiv(".screen-mobile.not-first-page #secondary","#colophon","before"),c("body").removeClass("move-sidebar")),c("div[class*=\"-faux\"]").each(function(){var a=c(this),b="."+a.attr("class"),d=b.replace("-faux","");if(!c(d).length){var e="#"+a.attr("class");d=e.replace("-faux","")}c(d).is(":visible")?(c(b).height(c(d).outerHeight()),c(".wp-google-badge-faux").height(c(".wp-google-badge").outerHeight())):c(b).height(0)}),b>c("#page").outerHeight()?c("#wrapper-content").addClass("extended"):c("#wrapper-content").removeClass("extended"),getDeviceW()>e)c(".wp-google-badge").find(".wp-google-badge-btn").css({display:"block"});else if(1<c(".wp-google-badge").find(".wp-google-badge-btn").length){c(".wp-google-badge").find(".wp-google-badge-btn").css({display:"none"});var d=Math.floor(Math.random()*c(".wp-google-badge").find(".wp-google-badge-btn").length)+1;c(".wp-google-badge").find(".wp-google-badge-btn:nth-of-type("+d+")").css({display:"block"})}c("ul.side-by-side").each(function(){var a,b,d=c(this).find("img"),e=d.css("filter"),f=e.split("drop-shadow(").slice(1),g=0,h=f.map(function(c){a=parseFloat(c.split(" ")[3].replace(/[)-]|px/g,"")),b=parseFloat(c.split(" ")[4].replace(/[)-]|px/g,"")),g+=1>a&&0<a?b:a});g=g+parseInt(d.css("borderLeftWidth"))+parseInt(d.css("borderRightWidth"))+parseInt(d.css("outlineWidth")),c(this).css({padding:g+"px","margin-left":-(g/2)+"px"})}),formLabelWidth()},c("h3 a[aria-hidden=\"true\"]").each(function(){c(this).parent().attr("aria-label",c(this).text())}),setTimeout(function(){c("img:not([alt])").attr("alt","")},50),setTimeout(function(){c("img:not([alt])").attr("alt","")},1e3),c("[role=\"menu\"]").on("focus.aria mouseenter.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).addClass("menu-item-expanded").attr("aria-expanded",!0)}),c("[role=\"menu\"]").on("blur.aria mouseleave.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).removeClass("menu-item-expanded").attr("aria-expanded",!1)}),c("[role=\"menu\"] a").attr("tabindex","0"),c("li[aria-haspopup=\"true\"]").attr("tabindex","-1"),window.addEventListener("pageshow",b=>{(b.persisted||window.performance&&2===window.performance.navigation.type)&&(c("#loader").fadeOut(300,function(){}),clearInterval(a))}),c(window).on("load",function(){clearInterval(a),c("#loader").fadeOut(300,function(){}),c("section.section-lock").each(function(){var a=c(this),b=a.attr("data-delay"),d=a.attr("data-pos"),e=a.attr("data-show"),f=a.attr("data-btn");"always"==e&&(e=1e-6),"never"==e&&(e=1e5),"session"==e&&(e=null),keyPress(a.find(".closeBtn")),"top"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({top:mobileMenuBarH()+"px"}),setTimeout(function(){a.addClass("on-screen"),c("body").addClass("locked"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked"),setCookie("display-message","no",e)})):"bottom"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({bottom:"0"}),setTimeout(function(){a.addClass("on-screen"),c("body").addClass("locked"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked"),setCookie("display-message","no",e)})):"header"==d?"no"!==getCookie("display-message")&&(moveDiv(a.find(".closeBtn"),".section-lock.position-header .col-inner","top"),a.css({display:"grid"}),a.find(".closeBtn").click(function(){a.css({"max-height":0,"padding-top":0,"padding-bottom":0,"margin-top":0,"margin-bottom":0}),setCookie("display-message","no",e)})):("no"==f&&"no"!==getCookie("display-message")&&(moveDiv(a.find(".closeBtn"),".section-lock > .flex","top"),setTimeout(function(){a.addClass("on-screen"),c("body").addClass("locked"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked"),setCookie("display-message","no",e)})),"yes"==f&&(c(".modal-btn").click(function(){moveDiv(a.find(".closeBtn"),".section-lock > .flex","top"),a.addClass("on-screen"),c("body").addClass("locked"),a.focus()}),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked")}))),a.click(function(){setCookie("display-message","no",e),c("video").each(function(){this.pause(),this.currentTime=0})})})})})(jQuery)});