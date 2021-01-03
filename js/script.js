document.addEventListener("DOMContentLoaded",function(){"use strict";!function(e){theme_dir.theme_dir_uri;var t,i=theme_dir.upload_dir_uri,a=0;e("#mobile-menu-bar").is(":visible")&&(a=e("#mobile-menu-bar").outerHeight());var n="noID";e.each(e("body").attr("class").split(" "),function(e,t){0===t.indexOf("postid-")?n=t.substr(7):0===t.indexOf("page-id-")&&(n=t.substr(8))}),e("body").attr("id",n);var o=window.location.pathname;o=(o=o.substr(1).slice(0,-1).replace("/","-"))?"slug-"+o:"slug-home",e("body").addClass(o),e(".logo").css("cursor","pointer").click(function(){window.location="/"}),window.linkHome=function(t){e(t).css("cursor","pointer"),e(t).click(function(){window.location="/"})},e(".as-logo, .am-stand-logo, .widget-as-logo").each(function(){e(this).wrapInner('<a href="https://www.americanstandardair.com/"></a>')}),window.trackClicks=function(e,t,i,a){gtag("event",e,{event_category:t,event_action:i,event_label:a,transport_type:"beacon",event_callback:function(){document.location=a}})},document.addEventListener("wpcf7mailsent",function(e){gtag("event","contact",{event_category:"Contact",event_action:"Email"})},!1),window.setCookie=function(e,t,i){var a,n=new Date;n.setTime(n.getTime()+24*i*60*60*1e3),null!=i&&""!=i&&(a="expires="+n.toGMTString()+"; "),document.cookie=e+"="+t+"; "+a+"path=/; secure"},window.getCookie=function(e){for(var t=e+"=",i=document.cookie.split(";"),a=0;a<i.length;a++){var n=i[a].trim();if(0==n.indexOf(t))return n.substring(t.length,n.length)}return""},getCookie("first-page")?e("body").addClass("not-first-page"):(e("body").addClass("first-page"),setCookie("first-page","no")),window.isApple=function(){return!!navigator.platform&&/iPad|iPhone|iPod/.test(navigator.platform)},window.getSlug=function(){return window.location.pathname.split("/")[1]},window.getUrlVar=function(e){e=e||"page";var t=new RegExp("[?&]"+e+"=([^&#]*)").exec(window.location.href);return null==t?null:decodeURI(t[1])||0},e.fn.hasPartialClass=function(e){return new RegExp(e).test(this.prop("class"))},window.getDeviceW=function(){var t=e(window).width();return!isApple()||90!=window.orientation&&-90!=window.orientation||(t=window.screen.height),t},window.getDeviceH=function(){var t=e(window).height();return!isApple()||90!=window.orientation&&-90!=window.orientation||(t=window.screen.width),t},window.getMobileCutoff=function(){return 1024},window.getTabletCutoff=function(){return 576},window.copyClasses=function(t,i,a){i=i||"img, iframe",e(t).each(function(){e(this).addClass(e(this).find(i).map(function(){return this.className}).get().join(" ")).addClass(a)})},window.replaceText=function(t,i,a,n,o){n=n||"text","true"==(o=o||"false")&&(i=new RegExp(i,"gi")),"text"==n?e(t).text(function(){return""!=i?e(this).text().replace(i,a):a}):e(t).html(function(){return""!=i?e(this).html().replace(i,a):a})},window.trimText=function(t,i){trimText.done||(t=t||250,e(i=i||".slider-testimonials .testimonials-quote, #secondary .testimonials-quote, .random-post .testimonials-quote").each(function(){var i=e(this).html(),a=t,n=i.substr(0,a);n.length==a&&(n=n.substr(0,Math.min(n.length,n.lastIndexOf(" "))),e(this).html(n+"…"))}),trimText.done=!0)},window.removeSidebar=function(t){var i=t.replace(".",""),a="slug-"+t.replace(/\//g,"");(e("body").hasClass(i)||e("body").hasClass(a))&&(e("body").removeClass("sidebar-right").removeClass("sidebar-left").addClass("sidebar-none"),removeDiv("#secondary"))},window.addStuck=function(t,i){i=i||"true",e(t).is(":visible")&&(e(t).addClass("stuck"),"true"==i&&addFaux(t))},window.removeStuck=function(t,i){i=i||"true",e(t).removeClass("stuck"),"true"==i&&removeFaux(t)},window.addFaux=function(t){var i=e(t),a=t.substr(1);i.is(":visible")&&(e("<div class='"+a+"-faux'></div>").insertBefore(i),e("."+a+"-faux").animate({height:i.outerHeight()+"px"},0))},window.removeFaux=function(t){var i=t.substr(1);e("."+i+"-faux").remove()},window.lockDiv=function(t,i,n,o,s,d){var r,c;n=n||"",o=o||"",s=s||"true",d=d||"both",r=""===(i=i||"")?e(t).next().length?e(t).next():e(t).parent().next():e(i),c=""===n?""===o?e(t).outerHeight():e(t).outerHeight()+Number(o):Number(n),e(t).css("top","unset"),r.waypoint(function(i){var n=0;""===o?e(".stuck").each(function(){n+=e(this).outerHeight(!0)}):n=Number(o),n+=a,"down"!==i||"both"!==d&&"down"!==d?"up"!==i||"both"!==d&&"up"!==d||(removeStuck(t,s),e(t).css("top","unset")):(addStuck(t,s),e(t).css("top",n+"px"))},{offset:c+"px"})},window.lockMenu=function(){lockDiv("#desktop-navigation")},window.animateScroll=function(t,i,n){var o=0,s=0;n=n||0,i=i||0,i+=a,e(".stuck").each(function(){o+=e(this).outerHeight()}),s="object"==typeof t||"string"==typeof t?e(t).offset().top-o-i:t-o-i,window.scroll({top:s,left:0,behavior:"smooth"})};e("#wrapper-content").waypoint(function(t){"up"===t?e("a.scroll-top").animate({opacity:0},150,function(){e("a.scroll-top").css({display:"none"})}):e("a.scroll-top").css({display:"block"}).animate({opacity:1},150)},{offset:"10%"}),e("#wrapper-content").waypoint(function(t){"down"===t?e(".scroll-down").fadeOut("fast"):e(".scroll-down").fadeIn("fast")},{offset:"99%"});if(window.getPosition=function(t,i,a){a=a||"window";var n,o,s,d,r=e(t);return"window"==a||"screen"==a?(n=r.offset().left,o=r.offset().top):(n=r.position().left,o=r.position().top),s=r.outerWidth(!0),d=r.outerHeight(!0),"left"==i||"l"==i?n:"top"==i||"t"==i?o:"bottom"==i||"b"==i?o+d:"right"==i||"r"==i?n+s:"centerX"==i||"centerx"==i||"center-x"==i?n+s/2:"centerY"==i||"centery"==i||"center-y"==i?o+d/2:void 0},window.buildAccordion=function(t,i,n,o,s,d){if(!buildAccordion.done){o=o||0,s=s||0,d=d||"close";var r=(i=i||o+s)+o+s+(n=n||500),c=[];(t=t||.1)<1&&(t=getDeviceH()*t),getDeviceW()<1024&&(t+=a),e(".block-accordion").attr("aria-expanded",!1),e(".block-accordion").first().addClass("accordion-first"),e(".block-accordion").last().addClass("accordion-last"),e(".block-accordion").parents(".col-archive").length?(e(".block-accordion").parents(".col-archive").addClass("archive-accordion"),e(".archive-accordion").each(function(){c.push(e(this).offset().top)})):e(".block-accordion").each(function(){c.push(e(this).offset().top)}),e(".block-accordion").keyup(function(t){13!==t.keyCode&&32!==t.keyCode||e(this).click()}),e(".block-accordion").click(function(a){a.preventDefault();var l=e(this),u=l.index(".block-accordion"),m=c[u],f=c[0],h=0;l.hasClass("active")?"close"==d&&(setTimeout(function(){e(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},n),e(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},n)},o),setTimeout(function(){e(".block-accordion.active").removeClass("active").attr("aria-expanded",!1)},i)):(setTimeout(function(){e(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},n),e(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},n)},o),setTimeout(function(){l.find(".accordion-excerpt").animate({height:"toggle",opacity:"toggle"},n),l.find(".accordion-content").animate({height:"toggle",opacity:"toggle"},n),m-f>.25*getDeviceH()?(h=m,animateScroll(h,t,n)):(h=(m-f)/2+f,animateScroll(h,t,n))},s),setTimeout(function(){e(".block-accordion.active").removeClass("active").attr("aria-expanded",!1),l.addClass("active").attr("aria-expanded",!0)},i)),getDeviceW()>1024&&setTimeout(function(){adjustSidebarH()},r)}),buildAccordion.done=!0}},window.parallaxBG=function(t,a,n,o,s,d,r,c){if(s=s||"center",d=d||"center",r=r||20,c=c||3,getDeviceW()>1024){var l=e(t),u=o/l.outerHeight()/c;l.parallax({imageSrc:i+"/"+a,speed:u,bleed:r,naturalWidth:n,naturalHeight:o,positionX:s,positionY:d})}},window.parallaxDiv=function(t,i){function a(){e(t).each(function(){var t=e(this).find(i),a=t.outerHeight(),n=e(this).outerHeight(),o=e(this).offset().top,s=o+n,d=e(window).height(),r=e(window).scrollTop(),c=r+d,l=(c-o)/(n+d);l>1&&(l=1),(l<0||null==l)&&(l=0);var u=(n-a)*l;o<c&&s>r&&t.css("margin-top",u+"px")})}i=i||".parallax",getDeviceW()>1024&&window.addEventListener("scroll",function(){a()}),a()},window.magicMenu=function(t,i,a,n){i=i||"active",a=a||"non-active",n=n||"false";var o,s,d,r,c,l,u=e(t=t||"#desktop-navigation .menu"),m=u.parent().parent(),f="horizontal";m.hasClass("widget")&&(f="vertical"),m.prepend("<div id='magic-line'></div>"),m.prepend("<div id='off-screen' class='"+f+"'></div>");var h=e("#magic-line"),g=e("#off-screen");if((s=u.find("li.current-menu-parent").length?u.find("li.current-menu-parent"):u.find("li.current-menu-item")).length&&!s.hasClass("mobile-only")||(s=g),s.find(">a").addClass(i),window.setMagicMenu=function(){"horizontal"==f?(r=s.position().left+(s.outerWidth()-s.width())/2,d=s.position().top,c=s.width(),l=h.data("origH")):(r=parseInt(s.parent().css("padding-left")),d=s.position().top,c=h.data("origW"),l=s.height()),h.css({transform:"translate("+r+"px, "+d+"px)",width:c,height:l}).data("origT",d).data("origL",r).data("origW",c).data("origH",l),h.delay(250).animate({opacity:1},0);var t=[],n=[],m=[],g=[];e("#desktop-navigation ul.main-menu > .menu-item, .widget-navigation .menu-item").each(function(){var i=e(this);"horizontal"==f?(t.push(i.position().top),n.push(i.position().left+(i.outerWidth()-i.width())/2),m.push(i.width()),g.push(h.data("origH"))):(t.push(i.position().top),n.push(parseInt(i.parent().css("padding-left"))),m.push(h.data("origW")),g.push(i.height()))}),u.find(" > li").hover(function(){var d=(o=e(this)).index();h.css({transform:"translate("+n[d]+"px, "+t[d]+"px)",width:m[d],height:g[d]}),s.find(">a").removeClass(i).addClass(a),o.find(">a").addClass(i).removeClass(a)},function(){h.css({transform:"translate("+h.data("origL")+"px, "+h.data("origT")+"px)",width:h.data("origW"),height:h.data("origH")}),o.find(">a").removeClass(i).addClass(a),s.find(">a").addClass(i).removeClass(a)})},"true"==n){var p,v,w=m.find(".flex").position().left,b=m.find(".flex").width(),C=s.position().left-w;m.find("li").mouseover(function(){C=e(this).position().left-w,magicColor(C)}),m.find(".flex").mouseout(function(){C=s.position().left-w,magicColor(C)}),window.magicColor=function(t){p=t+e("#magic-line").width()/2,(v=p/b)<.33&&e("body").removeClass("menu-alt-2").removeClass("menu-alt-3").addClass("menu-alt-1"),v>=.33&&v<.66&&e("body").removeClass("menu-alt-1").removeClass("menu-alt-3").addClass("menu-alt-2"),v>=.66&&e("body").removeClass("menu-alt-1").removeClass("menu-alt-2").addClass("menu-alt-3")},magicColor(C)}setTimeout(function(){setMagicMenu()},500),e(window).resize(function(){setMagicMenu()})},window.splitMenu=function(t,i,a){if(getDeviceW()>1024){i=i||".logo img",a=a||0;var n=e(t=t||"#desktop-navigation").find(".flex"),o=e(t).find("ul.menu"),s=e(i).outerWidth()+a,d=o.width()/2,r=0;o.children().each(function(){(r+=e(this).outerWidth())<d&&e(this).addClass("left-menu"),r>d&&e(this).addClass("right-menu")}),addDiv(n,'<div class="split-menu-r"></div>',"before"),addDiv(n,'<div class="split-menu-l"></div>',"before"),moveDiv(o,".split-menu-l","inside"),cloneDiv(o,".split-menu-r","inside"),e(".split-menu-l ul.menu").attr("id",e(".split-menu-l ul.menu").attr("id")+"-l"),e(".split-menu-r ul.menu").attr("id",e(".split-menu-r ul.menu").attr("id")+"-r"),e(".split-menu-l").find("li.right-menu").remove(),e(".split-menu-r").find("li.left-menu").remove(),n.css({"grid-column-gap":s+"px"})}},e(".main-navigation ul.main-menu li > a").each(function(){var t=e(this).html();e(this).parent().attr("data-content",t)}),e(".logo-slider").each(function(){var t=e(this),i=t.find(".logo-row"),a=i.children().length,n=1e3*a,o=parseInt(t.attr("data-speed")),s=parseInt(t.attr("data-delay")),d=t.attr("data-pause"),r=parseInt(t.attr("data-padding")),c="swing",l=!0;i.css("width",n),o*=1e3,"0"==s?c="linear":s*=1e3,"yes"!=d&&"true"!=d||(t.mouseover(function(){l=!1}),t.mouseout(function(){l=!0}));setInterval(function(){if(0!=l){var e=i.find("> span:first"),t=e.outerWidth();e.animate({"margin-left":-t+"px"},o,c,function(){e.remove().css({"margin-left":"0px"}),i.find("> span:last").after(e)})}},s);window.checkLogoSlider=function(){var t=0,n=0,o=0,s=0;i.find("span").each(function(){var i=e(this);o+=i.width(),n=n+i.width()+2*r,i.height()>t&&(t=i.height())}),i.find("span").each(function(){var i=e(this),a=(t-i.height())/2;e(this).find("img").css({"margin-top":a+"px","margin-bottom":a+"px"})}),n<getDeviceW()?(l=!1,s=(getDeviceW()-o)/a/2,i.find("span").css({"padding-left":s+"px","padding-right":s+"px"})):(l=!0,i.find("span").css({"padding-left":r+"px","padding-right":r+"px"}))},e(window).on("load",function(){checkLogoSlider()}),e(window).resize(function(){checkLogoSlider()})}),window.filterArchives=function(t,i,a,n){t=t||null,a=a||".col-archive",n=n||300,e(i=i||".section.archive-content").fadeTo(n,0,function(){""==t||null==t?e(a).show():(e(a).hide(),e(a+"."+t).show()),e(a).css({clear:"none"}),e(i).fadeTo(n,1)})},setTimeout(function(){e(".msg-disappear").fadeOut()},3e3),e.fn.replaceClass=function(e,t){return this.removeClass(e).addClass(t)},e.fn.random=function(){return this.eq(Math.floor(Math.random()*this.length))},window.cloneDiv=function(t,i,a){a=a||"after";var n=e(t),o=e(i);"after"==a?n.clone().insertAfter(o):"before"==a?n.clone().insertBefore(o):o.append(n.clone())},window.moveDiv=function(t,i,a){a=a||"after";var n=e(t),o=e(i);"after"==a?n.insertAfter(o):"before"==a?n.insertBefore(o):o.append(n)},window.moveDivs=function(t,i,a,n){n=n||"after",e(t).each(function(){var t=e(this);"after"==n?t.find(e(i)).insertAfter(t.find(a)):"before"==n?t.find(e(i)).insertBefore(t.find(a)):t.find(a).append(t.find(e(i)))})},window.addDiv=function(t,i,a){a=a||"after";var n=e(i=i||"<div></div>"),o=e(t);"after"==a?o.append(n):o.prepend(n)},window.wrapDiv=function(t,i,a){i=i||"<div></div>","outside"==(a=a||"outside")?e(t).each(function(){e(this).wrap(i)}):e(t).each(function(){e(this).wrapInner(i)})},window.wrapDivs=function(t,i){i=i||"<div />",e(t).wrapAll(i)},window.removeParent=function(t){e(t).unwrap()},window.removeDiv=function(t){e(t).remove()},window.animateDiv=function(t,i,a,n,o){a=a||0,n=n||"100%",o=o||1e3,o/=1e3,e(t).addClass("animated"),e(t).css({"animation-duration":o+"s"}),e(t+".animated").waypoint(function(){var t=e(this.element);setTimeout(function(){t.addClass(i)},a),this.destroy()},{offset:n})},window.animateDivs=function(t,i,a,n,o,s,d){n=n||0,o=o||100,s=s||"100%",d=d||1e3,d/=1e3;var r=0,c=i,l=e(t).parent(),u=t.split(" ").pop();e(t).addClass("animated"),setTimeout(function(){l.find(u+".animated").waypoint(function(){var t=e(this.element),n=t.prevAll(u).length;t.css({"animation-duration":d+"s"}),r=n>6?o:n*o,c===a?(setTimeout(function(){t.addClass(i)},r),c=i):(setTimeout(function(){t.addClass(a)},r),c=a),this.destroy()},{offset:s})},n)},window.animateGrid=function(t,i,a,n,o,s,d,r,c){o=o||0,s=s||100,d=d||"100%",r=r||"false",c=c||1e3,c/=1e3;var l=e(t).parent(),u=t.split(" ").pop();e(t).addClass("animated"),l.each(function(){var t=e(this),o=t.find(u+".animated").length;1==o&&t.find(u+".animated").data("animation",{effect:i,delay:0}),2==o&&(t.find(u+".animated:nth-last-child(2)").data("animation",{effect:a,delay:0}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:n,delay:1})),3==o&&(t.find(u+".animated:nth-last-child(3)").data("animation",{effect:a,delay:0}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:i,delay:1}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:n,delay:2})),4==o&&(t.find(u+".animated:nth-last-child(4)").data("animation",{effect:a,delay:0}),t.find(u+".animated:nth-last-child(3)").data("animation",{effect:i,delay:1}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:i,delay:2}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:n,delay:3})),5!=o&&6!=o||(t.find(u+".animated:nth-last-child(6)").data("animation",{effect:i,delay:0}),t.find(u+".animated:nth-last-child(5)").data("animation",{effect:i,delay:1}),t.find(u+".animated:nth-last-child(4)").data("animation",{effect:i,delay:2}),t.find(u+".animated:nth-last-child(3)").data("animation",{effect:i,delay:3}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:i,delay:4}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:i,delay:5})),7!=o&&8!=o||(t.find(u+".animated:nth-last-child(8)").data("animation",{effect:i,delay:0}),t.find(u+".animated:nth-last-child(7)").data("animation",{effect:i,delay:1}),t.find(u+".animated:nth-last-child(6)").data("animation",{effect:i,delay:2}),t.find(u+".animated:nth-last-child(5)").data("animation",{effect:i,delay:3}),t.find(u+".animated:nth-last-child(4)").data("animation",{effect:i,delay:4}),t.find(u+".animated:nth-last-child(3)").data("animation",{effect:i,delay:5}),t.find(u+".animated:nth-last-child(2)").data("animation",{effect:i,delay:6}),t.find(u+".animated:nth-last-child(1)").data("animation",{effect:i,delay:7}))}),l.find(u+".animated").waypoint(function(){var t=e(this.element),i=s*t.data("animation").delay+o,a=t.data("animation").effect;t.css({"animation-duration":c+"s"}),getDeviceW()>1024||"true"==r?setTimeout(function(){t.addClass(a)},i):t.addClass("fadeInUpSmall"),this.destroy()},{offset:d})},window.animateCSS=function(t,i,a,n){i=i||0,a=a||"100%",n=n||1e3,n/=1e3,e(t).addClass("animate"),e(t+".animate").waypoint(function(){var t=e(this.element);t.css({"transition-duration":n+"s"}),setTimeout(function(){t.removeClass("animate")},i),this.destroy()},{offset:a})},window.animateBtn=function(t,i,a){i=i||"li:not(.active)";var n=e(t=t||".menu").find(i);a=a||"go-animated",n.bind("webkitAnimationEnd mozAnimationEnd animationend",function(){e(this).removeClass(a)}),n.hover(function(){e(this).addClass(a)})},window.animateWords=function(t,i,a,n,o,s){n=n||0,o=o||100,s=s||"100%";var d=e(t);d.each(function(){for(var t=e(this).html(),r="",c=0,l=(t=t.split(" ")).length;c<l;c++)r+=c==l-1?'<div class="wordSplit animated">'+t[c]:'<div class="wordSplit animated">'+t[c]+"&nbsp;</div>";e(this).html(r);var u=n,m=i;d.find(".wordSplit.animated").waypoint(function(){var t=e(this.element);u+=o,m===a?(setTimeout(function(){t.addClass(i)},u),m=i):(setTimeout(function(){t.addClass(a)},u),m=a),this.destroy()},{offset:s})})},window.animateCharacters=function(t,i,a,n,o,s){n=n||0,o=o||100,s=s||"100%";var d=e(t);d.each(function(){for(var t=e(this).html(),r="",c=0,l=(t=t.split("")).length;c<l;c++)" "===t[c]&&(t[c]="&nbsp;"),r+='<div class="charSplit animated">'+t[c]+"</div>";e(this).html(r);var u=n,m=i;d.find(".charSplit.animated").waypoint(function(){var t=e(this.element);u+=o,m===a?(setTimeout(function(){t.addClass(i)},u),m=i):(setTimeout(function(){t.addClass(a)},u),m=a),this.destroy()},{offset:s})})},e(".col-8").addClass("span-1").removeClass("col-8"),e(".col-17").addClass("span-2").removeClass("col-17"),e(".col-20").addClass("span-2").removeClass("col-20"),e(".col-25").addClass("span-3").removeClass("col-25"),e(".col-33").addClass("span-4").removeClass("col-33"),e(".col-40").addClass("span-5").removeClass("col-40"),e(".col-42").addClass("span-5").removeClass("col-42"),e(".col-50").addClass("span-6").removeClass("col-50"),e(".col-58").addClass("span-7").removeClass("col-58"),e(".col-60").addClass("span-7").removeClass("col-60"),e(".col-67").addClass("span-8").removeClass("col-67"),e(".col-75").addClass("span-9").removeClass("col-75"),e(".col-80").addClass("span-10").removeClass("col-80"),e(".col-83").addClass("span-10").removeClass("col-83"),e(".col-92").addClass("span-11").removeClass("col-92"),e(".col-100").addClass("span-12").removeClass("col-100"),removeDiv("p:empty, .archive-intro:empty"),wrapDiv(".site-main",'<div class="site-main-inner"></div>',"inside"),e("#wrapper-top").length?e("#wrapper-top").addClass("page-begins"):e("#wrapper-content").addClass("page-begins"),e("div.noFX").find("img").addClass("noFX"),e("div.noFX").find("a").addClass("noFX"),e(".far, .fas, .fab").addClass("fa"),e("img").addClass("unloaded"),e("img").one("load",function(){e(this).removeClass("unloaded")}).each(function(){this.complete&&e(this).trigger("load")}),e("body").hasClass("remove-sidebar")&&(e("body").removeClass("sidebar-line").removeClass("sidebar-box").removeClass("widget-box").removeClass("sidebar-right").removeClass("sidebar-left").addClass("sidebar-none"),removeDiv("#secondary")),e("body").hasClass("background-image")&&getDeviceW()>1024){var s=new Image;s.onload=function(){animateDiv(".parallax-mirror","fadeIn",0,"",200)},s.onerror=function(){console.log("site-background.jpg not found")},s.src=i+"/site-background.jpg"}e(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr("role","menubar"),e(".main-navigation li, .widget-navigation li").attr("role","none"),e(".main-navigation a, .widget-navigation a").attr("role","menuitem"),e(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr("role","menu");var d=e(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor");d.addClass("active"),d.find(">a").attr("aria-current","page"),e(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li").hover(function(){d.replaceClass("active","dormant"),e(this).addClass("hover")},function(){e(this).removeClass("hover"),d.replaceClass("dormant","active")});var r=e(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");r.addClass("active"),r.find(">a").attr("aria-current","page"),e(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li").hover(function(){r.replaceClass("active","dormant"),e(this).addClass("hover")},function(){e(this).removeClass("hover"),r.replaceClass("dormant","active")}),e('a[href^="#"]:not(.carousel-control-next):not(.carousel-control-prev)').on("click",function(e){e.preventDefault();var t=this.hash;animateScroll(t)}),e('<div class="wp-google-badge-faux"></div>').insertAfter(e("#colophon")),window.closeMenu=function(){e("#mobile-menu-bar .activate-btn").removeClass("active"),e("body").removeClass("mm-active"),e(".top-push.screen-mobile #page").css({top:"0"}),e(".top-push.screen-mobile .top-strip.stuck").css({top:a+"px"})},window.openMenu=function(){e("#mobile-menu-bar .activate-btn").addClass("active"),e("body").addClass("mm-active");var t=e("#mobile-navigation").outerHeight(),i=t+a;e(".top-push.screen-mobile.mm-active #page").css({top:t+"px"}),e(".top-push.screen-mobile.mm-active .top-strip.stuck").css({top:i+"px"})},e("#mobile-menu-bar .activate-btn").click(function(){e(this).hasClass("active")?closeMenu():openMenu()}),window.closeSubMenu=function(t){e(t).removeClass("active"),e(t).height(0)},window.openSubMenu=function(t,i){e("#mobile-navigation ul.sub-menu").removeClass("active"),e("#mobile-navigation ul.sub-menu").height(0),e(t).addClass("active"),e(t).height(i+"px")},e("#mobile-navigation").addClass("get-sub-heights"),e("#mobile-navigation ul.sub-menu").each(function(){var t=e(this),i=t.outerHeight(!0);t.data("getH",i),t.parent().click(function(){t.hasClass("active")?closeSubMenu(t):openSubMenu(t,t.data("getH"))}),closeSubMenu(t)}),e("#mobile-navigation").removeClass("get-sub-heights"),e("#mobile-navigation li:not(.menu-item-has-children)").each(function(){e(this).click(function(){closeMenu()})}),e(".carousel").each(function(){var t=e(this),i=0,a=parseInt(t.find(".carousel-inner").css("padding-bottom"));t.data("maxH",0),t.on("slid.bs.carousel",function(){var e=t.find(".carousel-item.active").outerHeight()+a;e>i&&(t.find(".carousel-inner").css("height",e+"px"),i=e)})}),e(".testimonials-rating").each(function(){var t=e(this).html(),i=t;5==t&&(i='<span class="rating rating-5-star" aria-hidden="true"><span class="sr-only">Rated 5 Stars</span></span>'),4==t&&(i='<span class="rating rating-4-star" aria-hidden="true"><span class="sr-only">Rated 4 Stars</span></span>'),3==t&&(i='<span class="rating rating-3-star" aria-hidden="true"><span class="sr-only">Rated 3 Stars</span></span>'),2==t&&(i='<span class="rating rating-2-star" aria-hidden="true"><span class="sr-only">Rated 2 Stars</span></span>'),1==t&&(i='<span class="rating rating-1-star" aria-hidden="true"><span class="sr-only">Rated 1 Star</span></span>'),e(this).html(i)});var c=(new Date).getDay();0==c&&e(".office-hours .row-sun").addClass("today"),1==c&&e(".office-hours .row-mon").addClass("today"),2==c&&e(".office-hours .row-tue").addClass("today"),3==c&&e(".office-hours .row-wed").addClass("today"),4==c&&e(".office-hours .row-thu").addClass("today"),5==c&&e(".office-hours .row-fri").addClass("today"),6==c&&e(".office-hours .row-sat").addClass("today"),window.setupSidebar=function(t,i,a){t=t||0,i=i||"true",a=a||"true";var n=!1;window.shuffleWidgets=function(t){var i,a,n,o,s=t.length,d=t.parent(),r=[];for(i=0;i<s;i++)r.push(i);for(i=0;i<s;i++)a=Math.random()*s|0,n=Math.random()*s|0,o=r[a],r[a]=r[n],r[n]=o;for(t.detach(),i=0;i<s;i++)d.append(t.eq(r[i]));var c=e(".widget.lock-to-bottom").detach();d.append(c)},e(".widget.lock-to-top, .widget.lock-to-bottom").addClass("locked"),e(".widget:not(.locked)").addClass("shuffle"),getDeviceW()>1024&&"true"==a&&shuffleWidgets(e(".shuffle")),window.widgetInit=function(){getDeviceW()>1024&&0==n&&(e(".widget").removeClass("hide-widget"),removeWidgets(".widget.remove-first"),n=!0)},window.removeWidgets=function(i){var a=e("#primary .site-main-inner").outerHeight()+t,n=e("#secondary .sidebar-inner").outerHeight(!0)-a,o=e(i);n>0&&e(".widget:not(.hide-widget)").length?(o.random().addClass("hide-widget"),e(".widget.remove-first:not(.hide-widget)").length?removeWidgets(".widget.remove-first:not(.hide-widget)"):e(".widget.shuffle:not(.hide-widget)").length?removeWidgets(".widget.shuffle:not(.hide-widget):not(.widget-important)"):e(".widget.lock-to-bottom:not(.hide-widget)").length?removeWidgets(".widget.lock-to-bottom:not(.hide-widget):not(.widget-important)"):removeWidgets(".widget.lock-to-top:not(.hide-widget):not(.widget-important)")):checkWidgets()},window.findClosest=function(e,t,i){var a,n=999999;t++;for(var o=1;o<t;o++){var s=e-i[o];s>0&&s<n&&(n=s,a=i[o])}return a},window.checkWidgets=function(){var i=e("#primary .site-main-inner").outerHeight()+t,a=e("#secondary .sidebar-inner").outerHeight(),o=0,s=[],d=i-a+140;e(".widget.hide-widget").each(function(){var t=e(this);s[++o]=t.outerHeight(!0)+Math.floor(10*Math.random()-5),t.addClass("widget-height-"+s[o])});var r=findClosest(d,o,s);s.splice(s.indexOf(r),1),r<d&&e(".widget-height-"+r).removeClass("hide-widget"),adjustSidebarH(),setTimeout(function(){n=!1},3e3)},window.adjustSidebarH=function(){var i=e("#primary").outerHeight(!0)+t;e("#secondary").animate({height:i+"px"},300)},window.labelWidgets=function(){e(".widget").removeClass("widget-first").removeClass("widget-last").removeClass("widget-even").removeClass("widget-odd"),e(".widget:not(.hide-widget)").first().addClass("widget-first"),e(".widget:not(.hide-widget)").last().addClass("widget-last"),e(".widget:not(.hide-widget):odd").addClass("widget-even"),e(".widget:not(.hide-widget):even").addClass("widget-odd")},window.moveWidgets=function(){if("true"==i){var t=e("#primary").outerHeight(),a=e(".sidebar-inner"),n=a.outerHeight()+parseInt(e("#secondary").css("padding-top"))+parseInt(e("#secondary").css("padding-bottom")),o=t-getDeviceH()+200,s=n-getDeviceH()+400,d=e("#secondary").outerHeight(),r=e("#secondary").offset().top,c=e(window).height()-0,l=e(window).scrollTop()+0-r,u=l/(d-c),m=t-n;u>1&&(u=1),(u<0||null==u)&&(u=0);var f=Math.round(m*u);f>m&&(f=m),f<0&&(f=0),o>0&&s>0&&l>0&&getDeviceW()>1024?a.css("margin-top",f+"px"):a.css("margin-top","0px")}}},e(window).on("load",function(){screenResize(!0)}),e(window).resize(function(){screenResize(!0)}),window.screenResize=function(t){t=t||!1,setTimeout(function(){getDeviceW()>1024&&(e(window).trigger("resize.px.parallax"),1==t&&widgetInit()),labelWidgets(),e("#secondary").length&&getDeviceW()>1024?window.addEventListener("scroll",moveWidgets):window.removeEventListener("scroll",moveWidgets)},300),e("body").removeClass("screen-5 screen-4 screen-3 screen-2 screen-1 screen-mobile screen-desktop"),getDeviceW()>1280&&e("body").addClass("screen-5").addClass("screen-desktop"),getDeviceW()<=1280&&getDeviceW()>1024&&e("body").addClass("screen-4").addClass("screen-desktop"),getDeviceW()<=1024&&getDeviceW()>860&&e("body").addClass("screen-3").addClass("screen-mobile"),getDeviceW()<=860&&getDeviceW()>576&&e("body").addClass("screen-2").addClass("screen-mobile"),getDeviceW()<=576&&e("body").addClass("screen-1").addClass("screen-mobile"),e(".screen-mobile li.menu-item-has-children").children("a").attr("href","javascript:void(0)"),e(".main-navigation ul.sub-menu").each(function(){var t=e(this).outerWidth(!0),i=e(this).parent().width(),a=-Math.round((t-i)/2);e(this).css({left:a+"px"})}),closeMenu(),e('div[class*="-faux"]').each(function(){var t=e(this),i="."+t.attr("class"),a=i.replace("-faux","");e(a).length||(a=("#"+t.attr("class")).replace("-faux",""));e(a).is(":visible")?(e(i).height(e(a).outerHeight()),e(".wp-google-badge-faux").height(e(".wp-google-badge").outerHeight())):e(i).height(0)}),moveDiv(".sidebar-shift.screen-mobile #secondary","#colophon","before")},setTimeout(function(){e("img:not([alt])").attr("alt","")},50),setTimeout(function(){e("img:not([alt])").attr("alt","")},1e3),e(document).mousemove(function(t){e("body").addClass("using-mouse").removeClass("using-keyboard")}),e(document).keydown(function(t){9!=t.keyCode||e("body").hasClass("using-mouse")||e("body").addClass("using-keyboard")}),e('[role="menubar"]').on("focus.aria mouseenter.aria",'[aria-haspopup="true"]',function(t){e(t.currentTarget).attr("aria-expanded",!0)}),e('[role="menubar"]').on("blur.aria mouseleave.aria",'[aria-haspopup="true"]',function(t){e(t.currentTarget).attr("aria-expanded",!1)}),e("iframe").each(function(){e(this).attr("aria-hidden",!0).attr("tabindex","-1")}),document.addEventListener("keydown",function(e){if(9===e.keyCode){var t,i=document.getElementsByClassName("tab-focus");if(i[0])for(t of i)t.classList.remove("tab-focus");setTimeout(function(){document.activeElement.scrollIntoView({behavior:"smooth",block:"center"}),document.activeElement.classList.add("tab-focus")},10)}}),document.addEventListener("mousedown",function(e){var t,i=document.getElementsByClassName("tab-focus");if(i[0])for(t of i)t.classList.remove("tab-focus")}),e(window).on("load",function(){var i=((Date.now()-startTime)/1e3).toFixed(1),n="desktop";getDeviceW()<=1024&&(n="mobile"),e("#loader").fadeOut("fast");for(var o=document.getElementsByTagName("iframe"),s=0;s<o.length;s++)o[s].getAttribute("data-src")&&o[s].setAttribute("src",o[s].getAttribute("data-src"));e("section.section-lock").each(function(){var t=e(this),i=t.attr("data-delay"),n=t.attr("data-pos"),o=t.attr("data-show"),s=t.outerHeight()+100;"always"==o&&(o=1e-6),"never"==o&&(o=1e5),"session"==o&&(o=null),addDiv(t.find(".flex"),'<div class="closeBtn" aria-label="close" aria-hidden="false" tabindex="0"><i class="fa fa-times"></i></div>',"before"),t.find(".closeBtn").keyup(function(t){13!==t.keyCode&&32!==t.keyCode||e(this).click()}),"top"==n?(t.css("top",-s+"px"),"no"!==getCookie("display-message")&&(t.delay(i).animate({top:a+"px"},600),t.find(".closeBtn").click(function(){t.animate({top:-s+"px"},600),setCookie("display-message","no",o)}))):"bottom"==n?(t.css("bottom",-s+"px"),"no"!==getCookie("display-message")&&(t.delay(i).animate({bottom:"0"},600),t.find(".closeBtn").click(function(){t.animate({bottom:-s+"px"},600),setCookie("display-message","no",o)}))):(t.css({opacity:0}).fadeOut(),"no"!==getCookie("display-message")&&(setTimeout(function(){t.css({opacity:1}).fadeIn()},i),t.find(".closeBtn").click(function(){t.fadeOut(),setCookie("display-message","no",o)})))}),setTimeout(function(){trimText(),buildAccordion(),e.getJSON("https://ipapi.co/json/",function(e){t=e.timezone})},1e3),setTimeout(function(){var a=e("body").attr("id");e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_post_views",id:a,timezone:t},success:function(e){console.log(e)}}),i>.1&&e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"log_page_load_speed",id:a,timezone:t,loadTime:i,deviceTime:n},success:function(e){console.log(e)}}),e(".testimonials-name, #primary img.random-img, .widget-image:not(.hide-widget) img.random-img, .row-of-pics img.random-img, .carousel img.img-slider, #wrapper-bottom img.random-img, .widget-random-post:not(.hide-widget) h3, #primary h3, #wrapper-bottom h3").waypoint(function(){var i=e(this.element).attr("data-id"),a=e(this.element).attr("data-count-tease"),n=e(this.element).attr("data-count-view");"true"==a&&e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_teaser_views",id:i,timezone:t},success:function(e){console.log(e)}}),"true"==n&&e.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_post_views",id:i,timezone:t},success:function(e){console.log(e)}}),this.destroy()},{offset:"bottom-in-view"})},2500)})}(jQuery)});