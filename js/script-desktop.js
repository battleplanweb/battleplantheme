document.addEventListener("DOMContentLoaded",function(){"use strict";(function(a){var b=site_dir.upload_dir_uri;if(a("body").hasClass("background-image")){var c=new Image;c.onload=function(){animateDiv(".parallax-mirror","fadeIn",0,"",200)},c.src=b+"/site-background.jpg"}window.parallaxBG=function(c,d,e,f,g,h,i,j,k){g=g||"center",h=h||"center",i=i||20,j=j||3,k=k||c.replace("#","");var l=a(c).outerHeight(),m=f/l/j;a(c).parallax({imageSrc:b+"/"+d,speed:m,bleed:i,naturalWidth:e,naturalHeight:f,positionX:g,positionY:h,id:k})},window.parallaxDiv=function(b,c){function d(){a(b).each(function(){if(0<a(this).find(c).length){var b=a(this).find(c),d=b.outerHeight(),e=a(this).outerHeight(),f=a(this).offset().top,g=a(window).height(),h=a(window).scrollTop(),i=h+g,j=(i-f)/(e+g);1<j&&(j=1),(0>j||null==j)&&(j=0);var k=(e-d)*j;f<i&&f+e>h&&b.css("margin-top",k+"px")}})}c=c||".parallax",window.addEventListener("scroll",function(){d()}),d()},window.splitMenu=function(b,c,d,e){b=b||"#desktop-navigation",c=c||".logo img",d=d||0,e=e||!1;var f=a(b).find(".flex"),g=a(b).find("ul.menu"),h=a(b).find("ul.menu li"),i=a(c).outerWidth()+d,j=g.width()/2,k=h.length,l=Math.round(k/2),m=0;!1==e?g.children().each(function(){m+=a(this).outerWidth(),m<j?a(this).addClass("left-menu"):a(this).addClass("right-menu")}):(!0!=e&&(l=e),g.children().each(function(){m++,m<=l?a(this).addClass("left-menu"):a(this).addClass("right-menu")})),addDiv(f,"<div class=\"split-menu-r\"></div>","before"),addDiv(f,"<div class=\"split-menu-l\"></div>","before"),moveDiv(g,".split-menu-l","inside"),cloneDiv(g,".split-menu-r","inside"),a(".split-menu-l ul.menu").attr("id",a(".split-menu-l ul.menu").attr("id")+"-l"),a(".split-menu-r ul.menu").attr("id",a(".split-menu-r ul.menu").attr("id")+"-r"),a(".split-menu-l").find("li.right-menu").remove(),a(".split-menu-r").find("li.left-menu").remove(),f.css({"grid-column-gap":i+"px"})},window.addMenuLogo=function(b,c){c=c||"#desktop-navigation",a(c).addClass("menu-with-logo"),addDiv(".menu-with-logo","<div class=\"menu-logo\"><img src = \""+b+"\" alt=\"\"></div>","before"),a(".menu-with-logo .menu-logo").height(a(".menu-with-logo").height()),linkHome(".menu-logo")},window.desktopSidebar=function(b,c){window.checkHeights=function(){var c=a("#primary").outerHeight(),d=a(window).outerHeight(),e=a("#secondary .sidebar-inner").outerHeight()+parseInt(a("#secondary").css("padding-top"))+parseInt(a("#secondary").css("padding-bottom"))+b,f=c-e;return a("#wrapper-content").attr("data-primary",Math.round(c)).attr("data-viewport",Math.round(d)).attr("data-widgets",Math.round(e)).attr("data-remain",Math.round(f)),f},window.widgetInit=function(){0!=b&&a("#secondary").css({height:"calc(100% + "+b+"px)"}),a(".widget").attr("data-priority",2).addClass("hide-widget"),a(".widget.widget-priority-5, .widget.widget-essential, .widget.widget_nav_menu").attr("data-priority",5).removeClass("hide-widget"),a(".widget.widget-priority-4, .widget.widget-important, .widget-contact-form").attr("data-priority",4),a(".widget.widget-priority-3, .widget.widget-event, .widget.widget-financing").attr("data-priority",3),a(".widget.widget-priority-1, .widget.remove-first").attr("data-priority",1);var c=0;a(".widget.widget-set.set-a").each(function(){0<c&&a(this).attr("data-priority",0),c++}),c=0,a(".widget.widget-set.set-b").each(function(){0<c&&a(this).attr("data-priority",0),c++}),c=0,a(".widget.widget-set.set-c").each(function(){0<c&&a(this).attr("data-priority",0),c++}),addWidgets()},window.addWidgets=function(){for(var b=4;0<=b;b--)a(".hide-widget").each(function(){a(this).attr("data-priority")==b&&a(this).height()<=checkHeights()&&a(this).removeClass("hide-widget")});a(".widget:not(.hide-widget)").length||a(".widget:first-of-type").removeClass("hide-widget"),checkHeights(),labelWidgets()},window.moveWidgets=function(){if("true"==c){var b,d,e=+a("#wrapper-content").attr("data-primary"),f=+a("#wrapper-content").attr("data-widgets"),g=+a("#wrapper-content").attr("data-viewport"),h=+a("#wrapper-content").attr("data-remain"),i=a("#primary").offset().top,j=+a(window).scrollTop(),k=j-i;j>i?(b=k/(e-g),d=h*b):d=0,a(".stuck").each(function(){g-=a(this).outerHeight()}),g-=a(".wp-google-badge").outerHeight(),f<g&&(d=k+parseInt(a("#secondary").css("padding-top"))),d>h&&(d=h),0>d&&(d=0),0<d&&d<h&&checkHeights(),a(".sidebar-inner").css({"margin-top":d+"px"})}}},window.centerSubNav=function(){a(".main-navigation ul.sub-menu").each(function(){var b=a(this).outerWidth(!0),c=a(this).parent().width(),d=-Math.round((b-c)/2);a(this).css({left:d+"px"})})},a(window).on("load",function(){browserResize(!0)}),a(window).resize(function(){browserResize(!0)}),window.browserResize=function(b){b=b||!1,setTimeout(function(){a(window).trigger("resize.px.parallax"),!0==b&&widgetInit(),a("#secondary").length?window.addEventListener("scroll",moveWidgets):window.removeEventListener("scroll",moveWidgets),centerSubNav()},300)},a(document).mousemove(function(){a("body").addClass("using-mouse").removeClass("using-keyboard")}),a(document).keydown(function(b){9!=b.keyCode||a("body").hasClass("using-mouse")||a("body").addClass("using-keyboard")}),a("iframe").each(function(){a(this).attr("aria-hidden",!0).attr("tabindex","-1")}),document.addEventListener("keydown",function(a){if(9===a.keyCode){for(var b=document.getElementsByClassName("tab-focus");b[0];)b[0].classList.remove("tab-focus");setTimeout(function(){var a=document.activeElement,b=a.closest(".menu-item");a.classList.add("tab-focus"),null==b?a.scrollIntoView({behavior:"smooth",block:"center"}):(b.classList.add("tab-focus"),a.scrollIntoView({behavior:"smooth",block:"nearest"}))},10)}}),document.addEventListener("mousedown",function(){var a,b=document.getElementsByClassName("tab-focus");if(b[0])for(a of b)a.classList.remove("tab-focus")})})(jQuery)});