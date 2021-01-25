document.addEventListener("DOMContentLoaded",function(){"use strict";!function(e){theme_dir.theme_dir_uri;var t,a=theme_dir.upload_dir_uri,i=0;e("#mobile-menu-bar").is(":visible")&&(i=e("#mobile-menu-bar").outerHeight());var n="noID";e.each(e("body").attr("class").split(" "),function(e,t){0===t.indexOf("postid-")?n=t.substr(7):0===t.indexOf("page-id-")&&(n=t.substr(8))}),e("body").attr("id",n);var o=window.location.pathname;o=(o=o.substr(1).slice(0,-1).replace("/","-"))?"slug-"+o:"slug-home",e("body").addClass(o),e(".logo").css("cursor","pointer").click(function(){window.location="/"}),window.linkHome=function(t){e(t).css("cursor","pointer"),e(t).click(function(){window.location="/"})},e(".as-logo, .am-stand-logo, .widget-as-logo").each(function(){e(this).wrapInner('<a href="https://www.americanstandardair.com/"></a>')}),window.trackClicks=function(e,t,a,i){gtag("event",e,{event_category:t,event_action:a,event_label:i,transport_type:"beacon",event_callback:function(){document.location=i}})},document.addEventListener("wpcf7mailsent",function(e){gtag("event","contact",{event_category:"Contact",event_action:"Email"})},!1),window.setCookie=function(e,t,a){var i,n=new Date;n.setTime(n.getTime()+24*a*60*60*1e3),null!=a&&""!=a&&(i="expires="+n.toGMTString()+"; "),document.cookie=e+"="+t+"; "+i+"path=/; secure"},window.getCookie=function(e){for(var t=e+"=",a=document.cookie.split(";"),i=0;i<a.length;i++){var n=a[i].trim();if(0==n.indexOf(t))return n.substring(t.length,n.length)}return""},getCookie("first-page")?e("body").addClass("not-first-page"):(e("body").addClass("first-page"),setCookie("first-page","no")),window.isApple=function(){return!!navigator.platform&&/iPad|iPhone|iPod/.test(navigator.platform)},window.getSlug=function(){return window.location.pathname.split("/")[1]},window.getUrlVar=function(e){e=e||"page";var t=new RegExp("[?&]"+e+"=([^&#]*)").exec(window.location.href);return null==t?null:decodeURI(t[1])||0},e.fn.hasPartialClass=function(e){return new RegExp(e).test(this.prop("class"))},window.getDeviceW=function(){var t=e(window).width();return!isApple()||90!=window.orientation&&-90!=window.orientation||(t=window.screen.height),t},window.getDeviceH=function(){var t=e(window).height();return!isApple()||90!=window.orientation&&-90!=window.orientation||(t=window.screen.width),t},window.getMobileCutoff=function(){return 1024},window.getTabletCutoff=function(){return 576},window.copyClasses=function(t,a,i){a=a||"img, iframe",e(t).each(function(){e(this).addClass(e(this).find(a).map(function(){return this.className}).get().join(" ")).addClass(i)})},window.replaceText=function(t,a,i,n,o){n=n||"text","true"==(o=o||"false")&&(a=new RegExp(a,"gi")),"text"==n?e(t).text(function(){return""!=a?e(this).text().replace(a,i):i}):e(t).html(function(){return""!=a?e(this).html().replace(a,i):i})},window.trimText=function(t,a){trimText.done||(t=t||250,e(a=a||".slider-testimonials .testimonials-quote, #secondary .testimonials-quote, .random-post .testimonials-quote").each(function(){var a=e(this).html(),i=t,n=a.substr(0,i);n.length==i&&(n=n.substr(0,Math.min(n.length,n.lastIndexOf(" "))),e(this).html(n+"…"))}),trimText.done=!0)},window.removeSidebar=function(t){var a=t.replace(".",""),i="slug-"+t.replace(/\//g,"");(e("body").hasClass(a)||e("body").hasClass(i))&&(e("body").removeClass("sidebar-right").removeClass("sidebar-left").addClass("sidebar-none"),removeDiv("#secondary"))},window.addStuck=function(t,a){a=a||"true",e(t).is(":visible")&&(e(t).addClass("stuck"),"true"==a&&addFaux(t))},window.removeStuck=function(t,a){a=a||"true",e(t).removeClass("stuck"),"true"==a&&removeFaux(t)},window.addFaux=function(t){var a=e(t),i=t.substr(1);a.is(":visible")&&(e("<div class='"+i+"-faux'></div>").insertBefore(a),e("."+i+"-faux").css({height:a.outerHeight()+"px"}))},window.removeFaux=function(t){var a=t.substr(1);e("."+a+"-faux").remove()},window.lockDiv=function(t,a,n,o,s,d){var r,l;n=n||"",o=o||"",s=s||"true",d=d||"both",r=""===(a=a||"")?e(t).next().length?e(t).next():e(t).parent().next():e(a),l=""===n?""===o?e(t).outerHeight():e(t).outerHeight()+Number(o):Number(n),e(t).css("top","unset"),r.waypoint(function(a){var n=0;""===o?e(".stuck").each(function(){n+=e(this).outerHeight(!0)}):n=Number(o),n+=i,"down"!==a||"both"!==d&&"down"!==d?"up"!==a||"both"!==d&&"up"!==d||(removeStuck(t,s),e(t).css("top","unset")):(addStuck(t,s),e(t).css("top",n+"px"))},{offset:l+"px"})},window.lockMenu=function(){lockDiv("#desktop-navigation")},window.animateScroll=function(t,a,n){var o=0,s=0;n=n||0,a=a||0,a+=i,e(".stuck").each(function(){o+=e(this).outerHeight()}),s="object"==typeof t||"string"==typeof t?e(t).offset().top-o-a:t-o-a,window.scroll({top:s,left:0,behavior:"smooth"})};e("#wrapper-content").waypoint(function(t){"up"===t?e("a.scroll-top").animate({opacity:0},150,function(){e("a.scroll-top").css({display:"none"})}):e("a.scroll-top").css({display:"block"}).animate({opacity:1},150)},{offset:"10%"}),e("#wrapper-content").waypoint(function(t){"down"===t?e(".scroll-down").fadeOut("fast"):e(".scroll-down").fadeIn("fast")},{offset:"99%"});if(window.getPosition=function(t,a,i){i=i||"window";var n,o,s,d,r=e(t);return"window"==i||"screen"==i?(n=r.offset().left,o=r.offset().top):(n=r.position().left,o=r.position().top),s=r.outerWidth(!0),d=r.outerHeight(!0),"left"==a||"l"==a?n:"top"==a||"t"==a?o:"bottom"==a||"b"==a?o+d:"right"==a||"r"==a?n+s:"centerX"==a||"centerx"==a||"center-x"==a?n+s/2:"centerY"==a||"centery"==a||"center-y"==a?o+d/2:void 0},window.getTranslate=function(e,t){t=t||"Y";var a=document.querySelector(e),i=window.getComputedStyle(a),n=(i.transform||i.webkitTransform||i.mozTransform).match(/matrix.*\((.+)\)/)[1].split(", ");return"x"==t||"X"==t?n[4]:"y"==t||"Y"==t?n[5]:void 0},window.buildAccordion=function(t,a,n,o,s,d){if(!buildAccordion.done){o=o||0,s=s||0,d=d||"close";var r=(a=a||o+s)+o+s+(n=n||500),l=[];(t=t||.1)<1&&(t=getDeviceH()*t),getDeviceW()<1024&&(t+=i),e(".block-accordion").attr("aria-expanded",!1),e(".block-accordion").first().addClass("accordion-first"),e(".block-accordion").last().addClass("accordion-last"),e(".block-accordion").parents(".col-archive").length?(e(".block-accordion").parents(".col-archive").addClass("archive-accordion"),e(".archive-accordion").each(function(){l.push(e(this).offset().top)})):e(".block-accordion").each(function(){l.push(e(this).offset().top)}),e(".block-accordion").keyup(function(t){13!==t.keyCode&&32!==t.keyCode||e(this).click()}),e(".block-accordion").click(function(i){i.preventDefault();var c=e(this),u=c.index(".block-accordion"),m=l[u],f=l[0],g=0;c.hasClass("active")?"close"==d&&(setTimeout(function(){e(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},n),e(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},n)},o),setTimeout(function(){e(".block-accordion.active").removeClass("active").attr("aria-expanded",!1)},a)):(setTimeout(function(){e(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},n),e(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},n)},o),setTimeout(function(){c.find(".accordion-excerpt").animate({height:"toggle",opacity:"toggle"},n),c.find(".accordion-content").animate({height:"toggle",opacity:"toggle"},n),m-f>.25*getDeviceH()?(g=m,animateScroll(g,t,n)):(g=(m-f)/2+f,animateScroll(g,t,n))},s),setTimeout(function(){e(".block-accordion.active").removeClass("active").attr("aria-expanded",!1),c.addClass("active").attr("aria-expanded",!0)},a)),getDeviceW()>1024&&setTimeout(function(){adjustSidebarH()},r)}),buildAccordion.done=!0}},window.parallaxBG=function(t,i,n,o,s,d,r,l){if(s=s||"center",d=d||"center",r=r||20,l=l||3,getDeviceW()>1024){var c=e(t),u=o/c.outerHeight()/l;c.parallax({imageSrc:a+"/"+i,speed:u,bleed:r,naturalWidth:n,naturalHeight:o,positionX:s,positionY:d})}},window.parallaxDiv=function(t,a){function i(){e(t).each(function(){var t=e(this).find(a),i=t.outerHeight(),n=e(this).outerHeight(),o=e(this).offset().top,s=o+n,d=e(window).height(),r=e(window).scrollTop(),l=r+d,c=(l-o)/(n+d);c>1&&(c=1),(c<0||null==c)&&(c=0);var u=(n-i)*c;o<l&&s>r&&t.css("margin-top",u+"px")})}a=a||".parallax",getDeviceW()>1024&&window.addEventListener("scroll",function(){i()}),i()},window.magicMenu=function(t,a,i,n){a=a||"active",i=i||"non-active",n=n||"false";var o,s,d,r,l,c,u=e(t=t||"#desktop-navigation .menu"),m=u.parent().parent(),f="horizontal";m.hasClass("widget")&&(f="vertical"),m.prepend("<div id='magic-line'></div>"),m.prepend("<div id='off-screen' class='"+f+"'></div>");var g=e("#magic-line"),h=e("#off-screen");if((s=u.find("li.current-menu-parent").length?u.find("li.current-menu-parent"):u.find("li.current-menu-item")).length&&!s.hasClass("mobile-only")||(s=h),s.find(">a").addClass(a),window.setMagicMenu=function(){"horizontal"==f?(r=s.position().left+(s.outerWidth()-s.width())/2,d=s.position().top,l=s.width(),c=g.data("origH")):(r=parseInt(s.parent().css("padding-left")),d=s.position().top,l=g.data("origW"),c=s.height()),g.css({transform:"translate("+r+"px, "+d+"px)",width:l,height:c}).data("origT",d).data("origL",r).data("origW",l).data("origH",c),g.delay(250).animate({opacity:1},0);var t=[],n=[],m=[],h=[];e("#desktop-navigation ul.main-menu > .menu-item, .widget-navigation .menu-item").each(function(){var a=e(this);"horizontal"==f?(t.push(a.position().top),n.push(a.position().left+(a.outerWidth()-a.width())/2),m.push(a.width()),h.push(g.data("origH"))):(t.push(a.position().top),n.push(parseInt(a.parent().css("padding-left"))),m.push(g.data("origW")),h.push(a.height()))}),u.find(" > li").hover(function(){var d=(o=e(this)).index();g.css({transform:"translate("+n[d]+"px, "+t[d]+"px)",width:m[d],height:h[d]}),s.find(">a").removeClass(a).addClass(i),o.find(">a").addClass(a).removeClass(i)},function(){g.css({transform:"translate("+g.data("origL")+"px, "+g.data("origT")+"px)",width:g.data("origW"),height:g.data("origH")}),o.find(">a").removeClass(a).addClass(i),s.find(">a").addClass(a).removeClass(i)})},"true"==n){var p,v,w=m.find(".flex").position().left,b=m.find(".flex").width(),C=s.position().left-w;m.find("li").mouseover(function(){C=e(this).position().left-w,magicColor(C)}),m.find(".flex").mouseout(function(){C=s.position().left-w,magicColor(C)}),window.magicColor=function(t){p=t+e("#magic-line").width()/2,(v=p/b)<.33&&e("body").removeClass("menu-alt-2").removeClass("menu-alt-3").addClass("menu-alt-1"),v>=.33&&v<.66&&e("body").removeClass("menu-alt-1").removeClass("menu-alt-3").addClass("menu-alt-2"),v>=.66&&e("body").removeClass("menu-alt-1").removeClass("menu-alt-2").addClass("menu-alt-3")},magicColor(C)}setTimeout(function(){setMagicMenu()},500),e(window).resize(function(){setMagicMenu()})},window.splitMenu=function(t,a,i){if(getDeviceW()>1024){a=a||".logo img",i=i||0;var n=e(t=t||"#desktop-navigation").find(".flex"),o=e(t).find("ul.menu"),s=e(a).outerWidth()+i,d=o.width()/2,r=0;o.children().each(function(){(r+=e(this).outerWidth())<d&&e(this).addClass("left-menu"),r>d&&e(this).addClass("right-menu")}),addDiv(n,'<div class="split-menu-r"></div>',"before"),addDiv(n,'<div class="split-menu-l"></div>',"before"),moveDiv(o,".split-menu-l","inside"),cloneDiv(o,".split-menu-r","inside"),e(".split-menu-l ul.menu").attr("id",e(".split-menu-l ul.menu").attr("id")+"-l"),e(".split-menu-r ul.menu").attr("id",e(".split-menu-r ul.menu").attr("id")+"-r"),e(".split-menu-l").find("li.right-menu").remove(),e(".split-menu-r").find("li.left-menu").remove(),n.css({"grid-column-gap":s+"px"})}},window.addMenuLogo=function(t,a){e(a=a||"#desktop-navigation").addClass("menu-with-logo"),addDiv(".menu-with-logo",'<div class="menu-logo"><img src = "'+t+'" alt=""></div>',"before"),e(".menu-with-logo .menu-logo").height(e(".menu-with-logo").height()),linkHome(".menu-logo")},e(".main-navigation ul.main-menu li > a").each(function(){var t=e(this).html();e(this).parent().attr("data-content",t)}),e(".logo-slider").each(function(){var t=e(this),a=t.find(".logo-row"),i=a.children().length,n=1e3*i,o=parseInt(t.attr("data-speed")),s=parseInt(t.attr("data-delay")),d=t.attr("data-pause"),r=parseInt(t.attr("data-padding")),l="swing",c=!0;a.css("width",n),o*=1e3,"0"==s?l="linear":s*=1e3,"yes"!=d&&"true"!=d||(t.mouseover(function(){c=!1}),t.mouseout(function(){c=!0}));setInterval(function(){if(0!=c){var e=a.find("> span:first"),t=e.outerWidth();e.animate({"margin-left":-t+"px"},o,l,function(){e.remove().css({"margin-left":"0px"}),a.find("> span:last").after(e)})}},s);window.checkLogoSlider=function(){var t=0,n=0,o=0,s=0;a.find("span").each(function(){var a=e(this);o+=a.width(),n=n+a.width()+2*r,a.height()>t&&(t=a.height())}),a.find("span").each(function(){var a=e(this),i=(t-a.height())/2;e(this).find("img").css({"margin-top":i+"px","margin-bottom":i+"px"})}),n<getDeviceW()?(c=!1,s=(getDeviceW()-o)/i/2,a.find("span").css({"padding-left":s+"px","padding-right":s+"px"})):(c=!0,a.find("span").css({"padding-left":r+"px","padding-right":r+"px"}))},e(window).on("load",function(){checkLogoSlider()}),e(window).resize(function(){checkLogoSlider()})}),window.filterArchives=function(t,a,i,n){t=t||null,i=i||".col-archive",n=n||300,e(a=a||".section.archive-content").fadeTo(n,0,function(){""==t||null==t?e(i).show():(e(i).hide(),e(i+"."+t).show()),e(i).css({clear:"none"}),e(a).fadeTo(n,1)})},e("ul.tabs li").keyup(function(t){var a=e(this);13!==t.keyCode&&32!==t.keyCode||a.click()}),e("ul.tabs li").click(function(){var t=e(this).attr("data-tab");e("ul.tabs li").removeClass("current"),e(this).addClass("current"),e(".tab-content").fadeOut(150).next().removeClass("current"),e("#"+t).delay(150).addClass("current").fadeIn(150)}),window.prepareJSON=function(t){return t=t.replace(/%7B/g,"{").replace(/%7D/g,"}").replace(/%22/g,'"').replace(/%3A/g,":").replace(/%2C/g,","),e.parseJSON(t)},e.fn.replaceClass=function(e,t){return this.removeClass(e).addClass(t)},e.fn.random=function(){return this.eq(Math.floor(Math.random()*this.length))},window.cloneDiv=function(t,a,i){i=i||"after";var n=e(t),o=e(a);"after"==i?n.clone().insertAfter(o):"before"==i?n.clone().insertBefore(o):o.append(n.clone())},window.moveDiv=function(t,a,i){i=i||"after";var n=e(t),o=e(a);"after"==i?n.insertAfter(o):"before"==i?n.insertBefore(o):o.append(n)},window.moveDivs=function(t,a,i,n){n=n||"after",e(t).each(function(){var t=e(this);"after"==n?t.find(e(a)).insertAfter(t.find(i)):"before"==n?t.find(e(a)).insertBefore(t.find(i)):t.find(i).append(t.find(e(a)))})},window.addDiv=function(t,a,i){i=i||"after";var n=e(a=a||"<div></div>"),o=e(t);"after"==i?o.append(n):o.prepend(n)},window.wrapDiv=function(t,a,i){a=a||"<div></div>","outside"==(i=i||"outside")?e(t).each(function(){e(this).wrap(a)}):e(t).each(function(){e(this).wrapInner(a)})},window.wrapDivs=function(t,a){a=a||"<div />",e(t).wrapAll(a)},window.removeParent=function(t){e(t).unwrap()},window.removeDiv=function(t){e(t).remove()},window.animateDiv=function(t,a,i,n,o){i=i||0,n=n||"100%",o=o||1e3,o/=1e3,e(t).addClass("animated"),e(t).css({"animation-duration":o+"s"}),e(t+".animated").waypoint(function(){var t=e(this.element);setTimeout(function(){t.addClass(a)},i),this.destroy()},{offset:n})},window.animateDivs=function(t,a,i,n,o,s,d){n=n||0,o=o||100,s=s||"100%",d=d||1e3,d/=1e3;var r=0,l=a,c=e(t).parent(),u=t.split(" ").pop();e(t).addClass("animated"),setTimeout(function(){c.find(u+".animated").waypoint(function(){var t=e(this.element),n=t.prevAll(u).length;t.css({"animation-duration":d+"s"}),r=n>6?o:n*o,l===i?(setTimeout(function(){t.addClass(a)},r),l=a):(setTimeout(function(){t.addClass(i)},r),l=i),this.destroy()},{offset:s})},n)},window.animateGrid=function(t,a,i,n,o,s,d,r,l){o=o||0,s=s||100,d=d||"100%",r=r||"false",l=l||1e3,l/=1e3;var c=e(t).parent(),u=t.split(" ").pop();e(t).addClass("animated"),c.each(function(){var t=e(this),o=t.find(u+".animated").length;1==o&&t.find(u+".animated").data("animation",{effect:a,delay:0}),2==o&&(t.find(u+".animated:nth-last-child(2)").data("animation",{effect:i,delay:0}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:n,delay:1})),3==o&&(t.find(u+".animated:nth-last-child(3)").data("animation",{effect:i,delay:0}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:a,delay:1}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:n,delay:2})),4==o&&(t.find(u+".animated:nth-last-child(4)").data("animation",{effect:i,delay:0}),t.find(u+".animated:nth-last-child(3)").data("animation",{effect:a,delay:1}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:a,delay:2}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:n,delay:3})),5!=o&&6!=o||(t.find(u+".animated:nth-last-child(6)").data("animation",{effect:a,delay:0}),t.find(u+".animated:nth-last-child(5)").data("animation",{effect:a,delay:1}),t.find(u+".animated:nth-last-child(4)").data("animation",{effect:a,delay:2}),t.find(u+".animated:nth-last-child(3)").data("animation",{effect:a,delay:3}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:a,delay:4}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:a,delay:5})),7!=o&&8!=o||(t.find(u+".animated:nth-last-child(8)").data("animation",{effect:a,delay:0}),t.find(u+".animated:nth-last-child(7)").data("animation",{effect:a,delay:1}),t.find(u+".animated:nth-last-child(6)").data("animation",{effect:a,delay:2}),t.find(u+".animated:nth-last-child(5)").data("animation",{effect:a,delay:3}),t.find(u+".animated:nth-last-child(4)").data("animation",{effect:a,delay:4}),t.find(u+".animated:nth-last-child(3)").data("animation",{effect:a,delay:5}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:a,delay:6}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:a,delay:7}))}),c.find(u+".animated").waypoint(function(){var t=e(this.element),a=s*t.data("animation").delay+o,i=t.data("animation").effect;t.css({"animation-duration":l+"s"}),getDeviceW()>1024||"true"==r?setTimeout(function(){t.addClass(i)},a):t.addClass("fadeInUpSmall"),this.destroy()},{offset:d})},window.animateCSS=function(t,a,i,n){a=a||0,i=i||"100%",n=n||1e3,n/=1e3,e(t).addClass("animate"),e(t+".animate").waypoint(function(){var t=e(this.element);t.css({"transition-duration":n+"s"}),setTimeout(function(){t.removeClass("animate")},a),this.destroy()},{offset:i})},window.animateBtn=function(t,a,i){a=a||"li:not(.active)";var n=e(t=t||".menu").find(a);i=i||"go-animated",n.bind("webkitAnimationEnd mozAnimationEnd animationend",function(){e(this).removeClass(i)}),n.hover(function(){e(this).addClass(i)})},window.animateWords=function(t,a,i,n,o,s){n=n||0,o=o||100,s=s||"100%";var d=e(t);d.each(function(){for(var t=e(this).html(),r="",l=0,c=(t=t.split(" ")).length;l<c;l++)r+=l==c-1?'<div class="wordSplit animated">'+t[l]:'<div class="wordSplit animated">'+t[l]+"&nbsp;</div>";e(this).html(r);var u=n,m=a;d.find(".wordSplit.animated").waypoint(function(){var t=e(this.element);u+=o,m===i?(setTimeout(function(){t.addClass(a)},u),m=a):(setTimeout(function(){t.addClass(i)},u),m=i),this.destroy()},{offset:s})})},window.animateCharacters=function(t,a,i,n,o,s){n=n||0,o=o||100,s=s||"100%";var d=e(t);d.each(function(){for(var t=e(this).html(),r="",l=0,c=(t=t.split("")).length;l<c;l++)" "===t[l]&&(t[l]="&nbsp;"),r+='<div class="charSplit animated">'+t[l]+"</div>";e(this).html(r);var u=n,m=a;d.find(".charSplit.animated").waypoint(function(){var t=e(this.element);u+=o,m===i?(setTimeout(function(){t.addClass(a)},u),m=a):(setTimeout(function(){t.addClass(i)},u),m=i),this.destroy()},{offset:s})})},e(".col-8").addClass("span-1").removeClass("col-8"),e(".col-17").addClass("span-2").removeClass("col-17"),e(".col-20").addClass("span-2").removeClass("col-20"),e(".col-25").addClass("span-3").removeClass("col-25"),e(".col-33").addClass("span-4").removeClass("col-33"),e(".col-40").addClass("span-5").removeClass("col-40"),e(".col-42").addClass("span-5").removeClass("col-42"),e(".col-50").addClass("span-6").removeClass("col-50"),e(".col-58").addClass("span-7").removeClass("col-58"),e(".col-60").addClass("span-7").removeClass("col-60"),e(".col-67").addClass("span-8").removeClass("col-67"),e(".col-75").addClass("span-9").removeClass("col-75"),e(".col-80").addClass("span-10").removeClass("col-80"),e(".col-83").addClass("span-10").removeClass("col-83"),e(".col-92").addClass("span-11").removeClass("col-92"),e(".col-100").addClass("span-12").removeClass("col-100"),removeDiv("p:empty, .archive-intro:empty"),wrapDiv(".site-main",'<div class="site-main-inner"></div>',"inside"),e("#wrapper-top").length?e("#wrapper-top").addClass("page-begins"):e("#wrapper-content").addClass("page-begins"),e("div.noFX").find("img").addClass("noFX"),e("div.noFX").find("a").addClass("noFX"),e(".far, .fas, .fab").addClass("fa"),e("img").addClass("unloaded"),e("img").one("load",function(){e(this).removeClass("unloaded")}).each(function(){this.complete&&e(this).trigger("load")}),e("body").hasClass("remove-sidebar")&&(e("body").removeClass("sidebar-line").removeClass("sidebar-box").removeClass("widget-box").removeClass("sidebar-right").removeClass("sidebar-left").addClass("sidebar-none"),removeDiv("#secondary")),e("body").hasClass("background-image")&&getDeviceW()>1024){var s=new Image;s.onload=function(){animateDiv(".parallax-mirror","fadeIn",0,"",200)},s.onerror=function(){console.log("site-background.jpg not found")},s.src=a+"/site-background.jpg"}e(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr("role","menubar"),e(".main-navigation li, .widget-navigation li").attr("role","none"),e(".main-navigation a, .widget-navigation a").attr("role","menuitem"),e(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr("role","menu");var d=e(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor");d.addClass("active"),d.find(">a").attr("aria-current","page"),e(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li").hover(function(){d.replaceClass("active","dormant"),e(this).addClass("hover")},function(){e(this).removeClass("hover"),d.replaceClass("dormant","active")});var r=e(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");r.addClass("active"),r.find(">a").attr("aria-current","page"),e(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li").hover(function(){r.replaceClass("active","dormant"),e(this).addClass("hover")},function(){e(this).removeClass("hover"),r.replaceClass("dormant","active")}),e('a[href^="#"]:not(.carousel-control-next):not(.carousel-control-prev)').on("click",function(e){e.preventDefault();var t=this.hash;""!=t&&animateScroll(t)}),e('<div class="wp-google-badge-faux"></div>').insertAfter(e("#colophon")),window.closeMenu=function(){e("#mobile-menu-bar .activate-btn").removeClass("active"),e("body").removeClass("mm-active"),e(".top-push.screen-mobile #page").css({top:"0"}),e(".top-push.screen-mobile .top-strip.stuck").css({top:i+"px"})},window.openMenu=function(){e("#mobile-menu-bar .activate-btn").addClass("active"),e("body").addClass("mm-active");var t=e("#mobile-navigation").outerHeight(),a=t+i;e(".top-push.screen-mobile.mm-active #page").css({top:t+"px"}),e(".top-push.screen-mobile.mm-active .top-strip.stuck").css({top:a+"px"})},e("#mobile-menu-bar .activate-btn").click(function(){e(this).hasClass("active")?closeMenu():openMenu()}),window.closeSubMenu=function(t){e(t).removeClass("active"),e(t).height(0)},window.openSubMenu=function(t,a){e("#mobile-navigation ul.sub-menu").removeClass("active"),e("#mobile-navigation ul.sub-menu").height(0),e(t).addClass("active"),e(t).height(a+"px")},e("#mobile-navigation").addClass("get-sub-heights"),e("#mobile-navigation ul.sub-menu").each(function(){var t=e(this),a=t.outerHeight(!0);t.data("getH",a),t.parent().click(function(){t.hasClass("active")?closeSubMenu(t):openSubMenu(t,t.data("getH"))}),closeSubMenu(t)}),e("#mobile-navigation").removeClass("get-sub-heights"),e("#mobile-navigation li:not(.menu-item-has-children)").each(function(){e(this).click(function(){closeMenu()})}),e(".carousel").each(function(){var t=e(this),a=0,i=parseInt(t.find(".carousel-inner").css("padding-bottom"));t.data("maxH",0),t.on("slid.bs.carousel",function(){var e=t.find(".carousel-item.active").outerHeight()+i;e>a&&(t.find(".carousel-inner").css("height",e+"px"),a=e)})}),e(".testimonials-rating").each(function(){var t=e(this).html(),a=t;5==t&&(a='<span class="rating rating-5-star" aria-hidden="true"><span class="sr-only">Rated 5 Stars</span></span>'),4==t&&(a='<span class="rating rating-4-star" aria-hidden="true"><span class="sr-only">Rated 4 Stars</span></span>'),3==t&&(a='<span class="rating rating-3-star" aria-hidden="true"><span class="sr-only">Rated 3 Stars</span></span>'),2==t&&(a='<span class="rating rating-2-star" aria-hidden="true"><span class="sr-only">Rated 2 Stars</span></span>'),1==t&&(a='<span class="rating rating-1-star" aria-hidden="true"><span class="sr-only">Rated 1 Star</span></span>'),e(this).html(a)});var l=(new Date).getDay();0==l&&e(".office-hours .row-sun").addClass("today"),1==l&&e(".office-hours .row-mon").addClass("today"),2==l&&e(".office-hours .row-tue").addClass("today"),3==l&&e(".office-hours .row-wed").addClass("today"),4==l&&e(".office-hours .row-thu").addClass("today"),5==l&&e(".office-hours .row-fri").addClass("today"),6==l&&e(".office-hours .row-sat").addClass("today"),window.setupSidebar=function(t,a,i){t=t||0,a=a||"true",i=i||"true";var n=!1;window.shuffleWidgets=function(t){var a,i,n,o,s=t.length,d=t.parent(),r=[];for(a=0;a<s;a++)r.push(a);for(a=0;a<s;a++)i=Math.random()*s|0,n=Math.random()*s|0,o=r[i],r[i]=r[n],r[n]=o;for(t.detach(),a=0;a<s;a++)d.append(t.eq(r[a]));var l=e(".widget.lock-to-bottom").detach();d.append(l)},e(".widget.lock-to-top, .widget.lock-to-bottom").addClass("locked"),e(".widget:not(.locked)").addClass("shuffle"),getDeviceW()>1024&&"true"==i&&shuffleWidgets(e(".shuffle")),window.widgetInit=function(){getDeviceW()>1024&&0==n&&(e(".widget").removeClass("hide-widget"),removeWidgets(".widget.remove-first"),n=!0)},window.removeWidgets=function(a){var i=e("#primary .site-main-inner").outerHeight()+t,n=e("#secondary .sidebar-inner").outerHeight(!0)-i,o=e(a);n>0&&e(".widget:not(.hide-widget)").length?(o.random().addClass("hide-widget"),e(".widget.remove-first:not(.hide-widget)").length?removeWidgets(".widget.remove-first:not(.hide-widget)"):e(".widget.shuffle:not(.hide-widget)").length?removeWidgets(".widget.shuffle:not(.hide-widget):not(.widget-important)"):e(".widget.lock-to-bottom:not(.hide-widget)").length?removeWidgets(".widget.lock-to-bottom:not(.hide-widget):not(.widget-important)"):removeWidgets(".widget.lock-to-top:not(.hide-widget):not(.widget-important)")):checkWidgets()},window.findClosest=function(e,t,a){var i,n=999999;t++;for(var o=1;o<t;o++){var s=e-a[o];s>0&&s<n&&(n=s,i=a[o])}return i},window.checkWidgets=function(){var a=e("#primary .site-main-inner").outerHeight()+t,i=e("#secondary .sidebar-inner").outerHeight(),o=0,s=[],d=a-i+140;e(".widget.hide-widget").each(function(){var t=e(this);s[++o]=t.outerHeight(!0)+Math.floor(10*Math.random()-5),t.addClass("widget-height-"+s[o])});var r=findClosest(d,o,s);s.splice(s.indexOf(r),1),r<d&&e(".widget-height-"+r).removeClass("hide-widget"),adjustSidebarH(),setTimeout(function(){n=!1},3e3)},window.adjustSidebarH=function(){var a=e("#primary").outerHeight(!0)+t;e("#secondary").animate({height:a+"px"},300)},window.labelWidgets=function(){e(".widget").removeClass("widget-first").removeClass("widget-last").removeClass("widget-even").removeClass("widget-odd"),e(".widget:not(.hide-widget)").first().addClass("widget-first"),e(".widget:not(.hide-widget)").last().addClass("widget-last"),e(".widget:not(.hide-widget):odd").addClass("widget-even"),e(".widget:not(.hide-widget):even").addClass("widget-odd")},window.moveWidgets=function(){if("true"==a){var t=e("#primary").outerHeight(),i=e(".sidebar-inner"),n=i.outerHeight()+parseInt(e("#secondary").css("padding-top"))+parseInt(e("#secondary").css("padding-bottom")),o=t-getDeviceH()+200,s=n-getDeviceH()+400,d=e("#secondary").outerHeight(),r=e("#secondary").offset().top,l=e(window).height()-0,c=e(window).scrollTop()+0-r,u=c/(d-l),m=t-n;u>1&&(u=1),(u<0||null==u)&&(u=0);var f=Math.round(m*u);f>m&&(f=m),f<0&&(f=0),o>0&&s>0&&c>0&&getDeviceW()>1024?i.css("margin-top",f+"px"):i.css("margin-top","0px")}}},e(window).on("load",function(){screenResize(!0)}),e(window).resize(function(){screenResize(!0)}),window.screenResize=function(t){t=t||!1,setTimeout(function(){getDeviceW()>1024&&(e(window).trigger("resize.px.parallax"),1==t&&widgetInit()),labelWidgets(),e("#secondary").length&&getDeviceW()>1024?window.addEventListener("scroll",moveWidgets):window.removeEventListener("scroll",moveWidgets)},300),e("body").removeClass("screen-5 screen-4 screen-3 screen-2 screen-1 screen-mobile screen-desktop"),getDeviceW()>1280&&e("body").addClass("screen-5").addClass("screen-desktop"),getDeviceW()<=1280&&getDeviceW()>1024&&e("body").addClass("screen-4").addClass("screen-desktop"),getDeviceW()<=1024&&getDeviceW()>860&&e("body").addClass("screen-3").addClass("screen-mobile"),getDeviceW()<=860&&getDeviceW()>576&&e("body").addClass("screen-2").addClass("screen-mobile"),getDeviceW()<=576&&e("body").addClass("screen-1").addClass("screen-mobile"),e(".screen-mobile li.menu-item-has-children").children("a").attr("href","javascript:void(0)"),e(".main-navigation ul.sub-menu").each(function(){var t=e(this).outerWidth(!0),a=e(this).parent().width(),i=-Math.round((t-a)/2);e(this).css({left:i+"px"})}),closeMenu(),e('div[class*="-faux"]').each(function(){var t=e(this),a="."+t.attr("class"),i=a.replace("-faux","");e(i).length||(i=("#"+t.attr("class")).replace("-faux",""));e(i).is(":visible")?(e(a).height(e(i).outerHeight()),e(".wp-google-badge-faux").height(e(".wp-google-badge").outerHeight())):e(a).height(0)}),moveDiv(".sidebar-shift.screen-mobile #secondary","#colophon","before")},setTimeout(function(){e("img:not([alt])").attr("alt","")},50),setTimeout(function(){e("img:not([alt])").attr("alt","")},1e3),e(document).mousemove(function(t){e("body").addClass("using-mouse").removeClass("using-keyboard")}),e(document).keydown(function(t){9!=t.keyCode||e("body").hasClass("using-mouse")||e("body").addClass("using-keyboard")}),e('[role="menubar"]').on("focus.aria mouseenter.aria",'[aria-haspopup="true"]',function(t){e(t.currentTarget).attr("aria-expanded",!0)}),e('[role="menubar"]').on("blur.aria mouseleave.aria",'[aria-haspopup="true"]',function(t){e(t.currentTarget).attr("aria-expanded",!1)}),e("iframe").each(function(){e(this).attr("aria-hidden",!0).attr("tabindex","-1")}),e("form.hide-labels label:not(.show-label)").addClass("sr-only"),document.addEventListener("keydown",function(e){if(9===e.keyCode){var t,a=document.getElementsByClassName("tab-focus");if(a[0])for(t of a)t.classList.remove("tab-focus");setTimeout(function(){document.activeElement.scrollIntoView({behavior:"smooth",block:"center"}),document.activeElement.classList.add("tab-focus")},10)}}),document.addEventListener("mousedown",function(e){var t,a=document.getElementsByClassName("tab-focus");if(a[0])for(t of a)t.classList.remove("tab-focus")}),e(window).on("load",function(){var a=((Date.now()-startTime)/1e3).toFixed(1),n="desktop";getDeviceW()<=1024&&(n="mobile"),e("#loader").fadeOut("fast");for(var o=document.getElementsByTagName("iframe"),s=0;s<o.length;s++)o[s].getAttribute("data-src")&&o[s].setAttribute("src",o[s].getAttribute("data-src"));e("section.section-lock").each(function(){var t=e(this),a=t.attr("data-delay"),n=t.attr("data-pos"),o=t.attr("data-show"),s=t.outerHeight()+100;"always"==o&&(o=1e-6),"never"==o&&(o=1e5),"session"==o&&(o=null),addDiv(t.find(".flex"),'<div class="closeBtn" aria-label="close" aria-hidden="false" tabindex="0"><i class="fa fa-times"></i></div>',"before"),t.find(".closeBtn").keyup(function(t){13!==t.keyCode&&32!==t.keyCode||e(this).click()}),"top"==n?(t.css("top",-s+"px"),"no"!==getCookie("display-message")&&(t.delay(a).animate({top:i+"px"},600),t.find(".closeBtn").click(function(){t.animate({top:-s+"px"},600),setCookie("display-message","no",o)}))):"bottom"==n?(t.css("bottom",-s+"px"),"no"!==getCookie("display-message")&&(t.delay(a).animate({bottom:"0"},600),t.find(".closeBtn").click(function(){t.animate({bottom:-s+"px"},600),setCookie("display-message","no",o)}))):(t.css({opacity:0}).fadeOut(),"no"!==getCookie("display-message")&&(setTimeout(function(){t.css({opacity:1}).fadeIn()},a),t.find(".closeBtn").click(function(){t.fadeOut(),setCookie("display-message","no",o)})))}),setTimeout(function(){trimText(),buildAccordion(),e.getJSON("https://ipapi.co/json/",function(e){t=e.timezone})},1e3),setTimeout(function(){var i=e("body").attr("id");e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_post_views",id:i,timezone:t},success:function(e){console.log(e)}}),a>.1&&e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"log_page_load_speed",id:i,timezone:t,loadTime:a,deviceTime:n},success:function(e){console.log(e)}}),e(".testimonials-name, #primary img.random-img, .widget-image:not(.hide-widget) img.random-img, .row-of-pics img.random-img, .carousel img.img-slider, #wrapper-bottom img.random-img, .widget-random-post:not(.hide-widget) h3, #primary h3, #wrapper-bottom h3").waypoint(function(){var a=e(this.element).attr("data-id"),i=e(this.element).attr("data-count-tease"),n=e(this.element).attr("data-count-view");"true"==i&&e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_teaser_views",id:a,timezone:t},success:function(e){console.log(e)}}),"true"==n&&e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_post_views",id:a,timezone:t},success:function(e){console.log(e)}}),this.destroy()},{offset:"bottom-in-view"})},2500)})}(jQuery)});