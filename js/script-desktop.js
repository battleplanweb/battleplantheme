document.addEventListener("DOMContentLoaded",function(){"use strict";!function(t){var e=site_dir.upload_dir_uri;if(window.parallaxBG=function(i,n,o,a,s,d,r,u){s=s||"center",d=d||"center",r=r||20,u=u||3;var l=t(i),c=a/l.outerHeight()/u;l.parallax({imageSrc:e+"/"+n,speed:c,bleed:r,naturalWidth:o,naturalHeight:a,positionX:s,positionY:d})},window.parallaxDiv=function(e,i){function n(){t(e).each(function(){if(t(this).find(i).length>0){var e=t(this).find(i),n=e.outerHeight(),o=t(this).outerHeight(),a=t(this).offset().top,s=a+o,d=t(window).height(),r=t(window).scrollTop(),u=r+d,l=(u-a)/(o+d);l>1&&(l=1),(l<0||null==l)&&(l=0);var c=(o-n)*l;a<u&&s>r&&e.css("margin-top",c+"px")}})}i=i||".parallax",window.addEventListener("scroll",function(){n()}),n()},window.magicMenu=function(e,i,n,o){i=i||"active",n=n||"non-active",o=o||"false";var a,s,d,r,u,l,c=t(e=e||"#desktop-navigation .menu"),h=c.parent().parent(),m="horizontal";h.hasClass("widget")&&(m="vertical"),h.prepend("<div id='magic-line'></div>"),h.prepend("<div id='off-screen' class='"+m+"'></div>");var g=t("#magic-line"),f=t("#off-screen");if((s=c.find("li.current-menu-parent").length?c.find("li.current-menu-parent"):c.find("li.current-menu-item")).length&&!s.hasClass("mobile-only")||(s=f),s.find(">a").addClass(i),window.setMagicMenu=function(){"horizontal"==m?(r=Math.round(s.position().left+(s.outerWidth()-s.width())/2),d=Math.round(s.position().top),u=Math.round(s.width()),l=Math.round(g.data("origH"))):(r=Math.round(parseInt(s.parent().css("padding-left"))),d=Math.round(s.position().top),u=Math.round(g.data("origW")),l=Math.round(s.height())),g.css({transform:"translate("+r+"px, "+d+"px)",width:u,height:l}).data("origT",d).data("origL",r).data("origW",u).data("origH",l),g.delay(250).animate({opacity:1},0);var e=[],o=[],h=[],f=[];t("#desktop-navigation ul.main-menu > .menu-item, .widget-navigation .menu-item").each(function(){var i=t(this);"horizontal"==m?(e.push(Math.round(i.position().top)),o.push(Math.round(i.position().left+(i.outerWidth()-i.width())/2)),h.push(Math.round(i.width())),f.push(Math.round(g.data("origH")))):(e.push(Math.round(i.position().top)),o.push(Math.round(parseInt(i.parent().css("padding-left")))),h.push(Math.round(g.data("origW"))),f.push(Math.round(i.height())))}),c.find(" > li").hover(function(){var d=(a=t(this)).index();g.css({transform:"translate("+o[d]+"px, "+e[d]+"px)",width:h[d],height:f[d]}),s.find(">a").removeClass(i).addClass(n),a.find(">a").addClass(i).removeClass(n)},function(){g.css({transform:"translate("+g.data("origL")+"px, "+g.data("origT")+"px)",width:g.data("origW"),height:g.data("origH")}),a.find(">a").removeClass(i).addClass(n),s.find(">a").addClass(i).removeClass(n)})},"true"==o){var p,w,v,C=h.find(".flex").position().left,b=h.find(".flex").width(),y=s.position().left-C;h.find("li").mouseover(function(){y=t(this).position().left-C,magicColor(y)}),h.find(".flex").mouseout(function(){y=s.position().left-C,magicColor(y)}),window.magicColor=function(e){p=e+g.width()/2,w=p/b,v=g.position().left-C,w<.33&&t("body").removeClass("menu-alt-2").removeClass("menu-alt-3").addClass("menu-alt-1"),w>=.33&&w<.66&&t("body").removeClass("menu-alt-1").removeClass("menu-alt-3").addClass("menu-alt-2"),w>=.66&&t("body").removeClass("menu-alt-1").removeClass("menu-alt-2").addClass("menu-alt-3"),p<=v?t("body").addClass("menu-dir-l").removeClass("menu-dir-r"):t("body").addClass("menu-dir-r").removeClass("menu-dir-l")},magicColor(y)}setTimeout(function(){setMagicMenu()},500),t(window).resize(function(){setMagicMenu()})},window.splitMenu=function(e,i,n){i=i||".logo img",n=n||0;var o=t(e=e||"#desktop-navigation").find(".flex"),a=t(e).find("ul.menu"),s=t(i).outerWidth()+n,d=a.width()/2,r=0;a.children().each(function(){(r+=t(this).outerWidth())<d&&t(this).addClass("left-menu"),r>d&&t(this).addClass("right-menu")}),addDiv(o,'<div class="split-menu-r"></div>',"before"),addDiv(o,'<div class="split-menu-l"></div>',"before"),moveDiv(a,".split-menu-l","inside"),cloneDiv(a,".split-menu-r","inside"),t(".split-menu-l ul.menu").attr("id",t(".split-menu-l ul.menu").attr("id")+"-l"),t(".split-menu-r ul.menu").attr("id",t(".split-menu-r ul.menu").attr("id")+"-r"),t(".split-menu-l").find("li.right-menu").remove(),t(".split-menu-r").find("li.left-menu").remove(),o.css({"grid-column-gap":s+"px"})},window.addMenuLogo=function(e,i){t(i=i||"#desktop-navigation").addClass("menu-with-logo"),addDiv(".menu-with-logo",'<div class="menu-logo"><img src = "'+e+'" alt=""></div>',"before"),t(".menu-with-logo .menu-logo").height(t(".menu-with-logo").height()),linkHome(".menu-logo")},window.desktopSidebar=function(e,i,n){window.checkHeights=function(){var i=t("#primary").outerHeight(),n=t(window).outerHeight(),o=t("#secondary .sidebar-inner").outerHeight()+parseInt(t("#secondary").css("padding-top"))+parseInt(t("#secondary").css("padding-bottom"))+e,a=i-o;return t("#wrapper-content").attr("data-primary",Math.round(i)).attr("data-viewport",Math.round(n)).attr("data-widgets",Math.round(o)).attr("data-remain",Math.round(a)),a},window.widgetInit=function(){t("#secondary").css({height:"calc(100% + "+e+"px)"}),t(".widget:not(.widget-important)").addClass("hide-widget"),addWidgets()},window.addWidgets=function(){t(".hide-widget:not(.remove-first):not(.hide-set)").each(function(){t(this).height()<=checkHeights()&&t(this).removeClass("hide-widget")}),t(".hide-widget:not(.remove-first)").each(function(){t(this).height()<=checkHeights()&&t(this).removeClass("hide-widget")}),t(".hide-widget").each(function(){t(this).height()<=checkHeights()&&t(this).removeClass("hide-widget")}),t(".widget:not(.hide-widget)").length||t("widget:first-of-type").removeClass("hide-widget"),checkHeights(),labelWidgets()},window.moveWidgets=function(){if("true"==i){var e,n=t(".sidebar-inner"),o=Number(t("#wrapper-content").attr("data-primary")),a=Number(t("#wrapper-content").attr("data-widgets")),s=Number(t("#wrapper-content").attr("data-viewport")),d=Number(t("#wrapper-content").attr("data-remain")),r=t("#primary").offset().top,u=Number(t(window).scrollTop()),l=u-r;e=u>r?d*(l/(o-s)):0,t(".stuck").each(function(){s-=t(this).outerHeight()}),a<(s-=t(".wp-google-badge").outerHeight())&&(e=l+parseInt(t("#secondary").css("padding-top"))),e>d&&(e=d),e<0&&(e=0),e>0&&e<d&&checkHeights(),n.css({"margin-top":e+"px"})}}},t("body").hasClass("background-image")){var i=new Image;i.onload=function(){animateDiv(".parallax-mirror","fadeIn",0,"",200)},i.onerror=function(){console.log("site-background.jpg not found")},i.src=e+"/site-background.jpg"}t(window).on("load",function(){browserResize(!0)}),t(window).resize(function(){browserResize(!0)}),window.browserResize=function(e){e=e||!1,setTimeout(function(){t(window).trigger("resize.px.parallax"),1==e&&widgetInit(),t("#secondary").length?window.addEventListener("scroll",moveWidgets):window.removeEventListener("scroll",moveWidgets)},300),t(".main-navigation ul.sub-menu").each(function(){var e=t(this).outerWidth(!0),i=t(this).parent().width(),n=-Math.round((e-i)/2);t(this).css({left:n+"px"})})},t(document).mousemove(function(e){t("body").addClass("using-mouse").removeClass("using-keyboard")}),t(document).keydown(function(e){9!=e.keyCode||t("body").hasClass("using-mouse")||t("body").addClass("using-keyboard")}),t("iframe").each(function(){t(this).attr("aria-hidden",!0).attr("tabindex","-1")}),document.addEventListener("keydown",function(t){if(9===t.keyCode){for(var e=document.getElementsByClassName("tab-focus");e[0];)e[0].classList.remove("tab-focus");setTimeout(function(){var t=document.activeElement,e=t.closest(".menu-item");t.classList.add("tab-focus"),null==e?t.scrollIntoView({behavior:"smooth",block:"center"}):(e.classList.add("tab-focus"),t.scrollIntoView({behavior:"smooth",block:"nearest"}))},10)}}),document.addEventListener("mousedown",function(t){var e,i=document.getElementsByClassName("tab-focus");if(i[0])for(e of i)e.classList.remove("tab-focus")})}(jQuery)});