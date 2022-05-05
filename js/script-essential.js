document.addEventListener("DOMContentLoaded",function(){"use strict";(function(c){function d(a){var b=document.createElement("iframe");b.setAttribute("src",a.dataset.link),b.setAttribute("frameborder","0"),b.setAttribute("allowfullscreen","1"),b.setAttribute("allow","accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"),a.parentNode.replaceChild(b,a)}var e=site_dir.theme_dir_uri,f=site_dir.upload_dir_uri,h=0;window.isApple=function(){return!!navigator.platform&&/iPad|iPhone|iPod/.test(navigator.platform)},window.getSlug=function(){var a=window.location.pathname.split("/");return a[1]},window.getUrlVar=function(a){a=a||"page";var b=new RegExp("[?&]"+a+"=([^&#]*)").exec(window.location.href);return null==b?null:decodeURI(b[1])||0},window.keyPress=function(a){c(a).keyup(function(a){(13===a.keyCode||32===a.keyCode)&&c(this).click()})},keyPress(".logo"),c(".logo").attr("tabindex","0").attr("role","button").attr("aria-label","Return to Home Page").css("cursor","pointer").click(function(){window.location="/"}),c(".logo img").attr("aria-hidden","true"),window.linkHome=function(a){keyPress(a),c(a).attr("tabindex","0").attr("role","button").attr("aria-label","Return to Home Page").css("cursor","pointer").click(function(){window.location="/"}),c(a).find("img").attr("aria-hidden","true")},c("img[src*='hvac-american-standard/american-standard']").each(function(){c(this).wrap("<a href=\"https://www.americanstandardair.com/\" target=\"_blank\" rel=\"noreferrer\"></a>")}),c(".track-clicks, .wpcf7-submit").click(function(){var a=c(this),b=a.attr("data-action")?a.attr("data-action"):"email",d=a.attr("data-url");d&&("function"==typeof gtag_report_conversion&&gtag_report_conversion(d),document.location=d),c.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_link_clicks",type:b},success:function(a){console.log(a)}})}),document.addEventListener("wpcf7mailsent",function(){location="/email-received/"},!1),window.setCookie=function(a,b,c){var e=document.domain.match(/[^\.]*\.[^.]*$/)[0],f=new Date,d="";f.setTime(f.getTime()+1e3*(60*(60*(24*c)))),null!=c&&""!=c&&(d="expires="+f.toGMTString()+"; "),document.cookie=a+"="+b+"; "+d+"; path=/; secure"},window.getCookie=function(a){for(var b,d=a+"=",e=document.cookie.split(";"),f=0;f<e.length;f++)if(b=e[f].trim(),0==b.indexOf(d))return b.substring(d.length,b.length);return""},window.deleteCookie=function(a){setCookie(a,"",-1)},c.fn.hasPartialClass=function(a){return new RegExp(a).test(this.prop("class"))},window.getDeviceW=function(){var a=c(window).width();return isApple()&&(90==window.orientation||-90==window.orientation)&&(a=window.screen.height),a},window.getDeviceH=function(){var a=c(window).height();return isApple()&&(90==window.orientation||-90==window.orientation)&&(a=window.screen.width),a},window.getMobileCutoff=function(){return 1024},window.getTabletCutoff=function(){return 576},window.copyClasses=function(a,b,d){b=b||"img, iframe",c(a).each(function(){c(this).addClass(c(this).find(b).map(function(){return this.className}).get().join(" ")).addClass(d)})},window.shuffleArray=function(a){for(let b,c=a.length-1;0<c;c--)b=Math.floor(Math.random()*(c+1)),[a[c],a[b]]=[a[b],a[c]]},window.replaceText=function(a,b,d,e,f){e=e||"text",f=f||"false","true"==f&&(b=new RegExp(b,"gi")),"text"==e?c(a).text(function(){if(""!=b){var a=c(this).text();return a.replace(b,d)}return d}):c(a).html(function(){if(""!=b){var a=c(this).html();return a.replace(b,d)}return d})},window.addStuck=function(a,b){b=b||"true",c(a).is(":visible")&&(c(a).addClass("stuck"),"true"==b&&addFaux(a.split(" ").pop()))},window.removeStuck=function(a,b){b=b||"true",c(a).removeClass("stuck"),"true"==b&&removeFaux(a.split(" ").pop())},window.addFaux=function(a,b){b=b||"false";var d=c(a);d.is(":visible")&&(c("<div class='"+a.substr(1)+"-faux'></div>").insertBefore(d),c("."+a.substr(1)+"-faux").css({height:d.outerHeight()+"px"}),"true"==b&&d.css({position:"fixed"}))},window.removeFaux=function(a){c("."+a.substr(1)+"-faux").remove()},window.lockDiv=function(a,b,d,e,f,g){b=b||"",d=d||"",e=e||"",f=f||"true",g=g||"both";var i,j;i=""===b?c(a).next().length?c(a).next():c(a).parent().next().hasClass("section-lock")?c(a).parent().next().next():c(a).parent().next():c(b),j=""===d?""===e?c(a).outerHeight():c(a).outerHeight()+ +e:+d,c(a).css("top","unset"),i.waypoint(function(b){var d=h;""===e?c(".stuck").each(function(){d+=c(this).outerHeight(!0)}):d+=+e,"down"===b&&("both"===g||"down"===g)?(addStuck(a,f),c(a).css("top",d+"px")):"up"==b&&("both"===g||"up"===g)&&(removeStuck(a,f),c(a).css("top","unset"))},{offset:j+"px"})},window.lockMenu=function(){lockDiv("#desktop-navigation")},window.addStroke=function(a,b,c,d,e,f){var g,h,j,k,l="";e=e||c,d=d||c,f=f||d;for(var m=0;16>m;m++)h=2*m*Math.PI/16,j=Math.round(1e4*Math.cos(h))/1e4,k=Math.round(1e4*Math.sin(h))/1e4,0>=j&&0>=k&&(g=c),0<=j&&0<=k&&(g=d),0>=j&&0<=k&&(g=e),0<=j&&0>=k&&(g=f),l+="calc("+b+"px * "+j+") calc("+b+"px * "+k+") 0 "+g,15>m&&(l+=", ");var n=document.createElement("style");n.innerHTML=a+" { text-shadow: "+l+"; letter-spacing: "+(b-1)+"px; }",document.head.appendChild(n)},window.animateScroll=function(a,b,d){var e=0,f=0;d=d||0,b=b||0,b+=h,c(".stuck").each(function(){e+=c(this).outerHeight()}),f="object"==typeof a||"string"==typeof a?c(a).offset().top-e-b:a-e-b,"#tab-description"!==a&&window.scroll({top:f,left:0,behavior:"smooth"})};var k=c("#wrapper-content").waypoint(function(a){"up"===a?c("a.scroll-top").animate({opacity:0},150,function(){c("a.scroll-top").css({display:"none"}).removeClass("scroll-btn-visible")}):c("a.scroll-top").css({display:"block"}).animate({opacity:1},150).addClass("scroll-btn-visible")},{offset:"10%"}),l=c("#wrapper-content").waypoint(function(a){"down"===a?c(".scroll-down").fadeOut("fast"):c(".scroll-down").fadeIn("fast")},{offset:"99%"});window.getPosition=function(a,b,d){d=d||"window";var e,f,g,h,i,j,k,l,m=c(a);return"window"==d||"screen"==d?(e=m.offset().left,f=m.offset().top):(e=m.position().left,f=m.position().top),g=m.outerWidth(!0),h=m.outerHeight(!0),i=f+h,j=e+g,k=e+g/2,l=f+h/2,"left"==b||"l"==b?e:"top"==b||"t"==b?f:"bottom"==b||"b"==b?i:"right"==b||"r"==b?j:"centerX"==b||"centerx"==b||"center-x"==b?k:"centerY"==b||"centery"==b||"center-y"==b?l:void 0},window.getTranslate=function(a,b){b=b||"Y";var c=document.querySelector(a),d=window.getComputedStyle(c),e=d.transform||d.webkitTransform||d.mozTransform,f=e.match(/matrix.*\((.+)\)/)[1].split(", ");return"x"==b||"X"==b?f[4]:"y"==b||"Y"==b?f[5]:void 0},c(".main-navigation ul.main-menu li > a").each(function(){c(this).parent().attr("data-content",c(this).html())}),window.filterArchives=function(a,b,d,e){a=a||null,b=b||".section.archive-content",d=d||".col-archive",e=e||300,c(b).fadeTo(e,0,function(){""==a||null==a?c(d).show():(c(d).hide(),c(d+"."+a).show()),c(d).css({clear:"none"}),c(b).fadeTo(e,1)})},c(".filter-btn").click(function(){var a=c(this),b="?"+a.attr("data-url")+"=",d=!1;c("input:checkbox[name=choice]:checked").each(function(){d?b=b+","+c(this).val():(b+=c(this).val(),d=!0)}),window.location=b}),keyPress("ul.tabs li"),c("ul.tabs li").click(function(){var a=c(this).attr("data-tab"),b=150;c("ul.tabs li").removeClass("current"),c(this).addClass("current"),c(".tab-content").fadeOut(b).next().removeClass("current"),c("#"+a).delay(b).addClass("current").fadeIn(b)}),window.prepareJSON=function(a){return a=a.replace(/%7B/g,"{").replace(/%7D/g,"}").replace(/%22/g,"\"").replace(/%3A/g,":").replace(/%2C/g,","),c.parseJSON(a)},window.revealDiv=function(a,b,d,e){b=b||0,d=d||1e3,e=e||"100%";var f=c(a),g=f.next(),h=f.outerHeight();g.css({transform:"translateY(-"+h+"px)","transition-duration":0}),setTimeout(function(){f.waypoint(function(){g.css({transform:"translateY(0)","transition-duration":d+"ms"})},{offset:e})},b)},window.btnRevealDiv=function(a,b,d,e){e=e||0,d=d||0,d+=h;var f=c(b).css("display");c(b).css("display","none"),c(a).click(function(){c(b).css("display",f),animateScroll(b,d,e)})},c(".review-form:first").addClass("active"),c(".review-form #gmail-yes").click(function(){window.location.href="/google"}),c(".review-form #facebook-yes").click(function(){window.location.href="/facebook"}),c(".review-form #yelp-yes").click(function(){window.location.href="/yelp"}),c(".review-form #gmail-no, .review-form #facebook-no, .review-form #yelp-no").click(function(){c(this).closest(".review-form").removeClass("active"),c(this).closest(".review-form").next().addClass("active")});var m,o=document.getElementById("ak_js"),p=[];o?o.parentNode.removeChild(o):(o=document.createElement("input"),o.type="hidden",o.name=o.id="ak_js"),o.value=new Date().getTime(),(m=document.getElementById("commentform"))&&p.push(m),(m=document.getElementById("replyrow"))&&(m=m.getElementsByTagName("td"))&&p.push(m.item(0));for(var q=0,s=p.length;q<s;q++)p[q].appendChild(o);setTimeout(function(){c("div.menu-search-box a.menu-search-bar").each(function(){var a=c(this),b=a.find("input[type=\"search\"]"),d=a.outerWidth(),e=a.find("i.fa").outerWidth();a.css({width:e+"px"}),a.click(function(){a.animate({width:d+"px"},150,function(){"function"==typeof centerSubNav&&setTimeout(function(){centerSubNav()},300)})}),isApple()||(b.focus(function(){var b=-(getPosition(a,"top")-h-25);c("#mobile-navigation > #mobile-menu").css({position:"relative"}).animate({"margin-top":b+"px"},300)}),b.blur(function(){c("#mobile-navigation > #mobile-menu").css({position:"relative"}).animate({"margin-top":"0px)"},300)}))})},300),c.fn.replaceClass=function(a,b){return this.removeClass(a).addClass(b)},c.fn.random=function(){return this.eq(Math.floor(Math.random()*this.length))},window.cloneDiv=function(a,b,d){d=d||"after","after"==d?c(a).clone().insertAfter(c(b)):"before"==d?c(a).clone().insertBefore(c(b)):"top"==d||"start"?c(b).prepend(c(a).clone()):c(b).append(c(a).clone())},window.moveDiv=function(a,b,d){d=d||"after","after"==d?c(a).insertAfter(c(b)):"before"==d?c(a).insertBefore(c(b)):"top"==d||"start"?c(b).prepend(c(a)):c(b).append(c(a))},window.moveDivs=function(a,b,d,e){e=e||"after",c(a).each(function(){var a=c(this);"after"==e?a.find(c(b)).insertAfter(a.find(d)):"before"==e?a.find(c(b)).insertBefore(a.find(d)):"top"==e||"start"?a.find(d).prepend(a.find(c(b))):a.find(d).append(a.find(c(b)))})},window.addDiv=function(a,b,d){b=b||"<div></div>",d=d||"after","after"==d?c(a).append(c(b)):"before"==d?c(a).prepend(c(b)):c(b).insertBefore(c(a))},window.wrapDiv=function(a,b,d){b=b||"<div></div>",d=d||"outside","outside"==d?c(a).each(function(){c(this).wrap(b)}):c(a).each(function(){c(this).wrapInner(b)})},window.wrapDivs=function(a,b){b=b||"<div />",c(a).wrapAll(b)},window.sizeFrame=function(a,b,d){b=b||".frame",d=d||"0.9",c(a).find("img").css({transform:"scale("+d+")"}),c(a).each(function(){var d=c(this).find(b),e=c(this).find("img");if(a.includes("video")){e=c(this).find("iframe");var f=e.width(),g=e.height();d.width(f+"px").height(g+"px").css({marginTop:-g+"px"})}else a.includes("carousel")&&(e=c(this).find(".carousel-item.active img")),e.one("load",function(){var a=e.width(),b=e.height();d.width(a+"px").height(b+"px").css({marginBottom:-b+"px"})}).each(function(){this.complete&&c(this).trigger("load")})})},window.svgBG=function(a,b,d){d=d||"top","bottom"==d?c(a).clone().css({position:"absolute"}).appendTo(c(b)):"top"==d?c(a).clone().css({position:"absolute"}).prependTo(c(b)):"before"==d||"start"==d?c(a).clone().css({position:"absolute"}).insertBefore(c(b)):c(a).clone().css({position:"absolute"}).insertAfter(c(b))},"function"!=typeof parallaxBG&&(window.parallaxBG=window.parallaxDiv=window.magicMenu=window.splitMenu=window.addMenuLogo=window.desktopSidebar=function(){}),window.setupSidebar=function(a,b,d){b=b||"true",d=d||"true",a=a||0,window.labelWidgets=function(){c(".widget:not(.hide-widget)").first().addClass("widget-first"),c(".widget:not(.hide-widget)").last().addClass("widget-last"),c(".widget:not(.hide-widget):odd").addClass("widget-even"),c(".widget:not(.hide-widget):even").addClass("widget-odd")};var e,f,g,h,j=c(".widget:not(.lock-to-top):not(.lock-to-bottom)"),k=j.length,l=j.parent(),m=[],n=c(".widget.lock-to-bottom").detach();for(e=0;e<k;e++)m.push(e);if("true"==d)for(e=0;e<k;e++)f=0|Math.random()*k,g=0|Math.random()*k,h=m[f],m[f]=m[g],m[g]=h;for(j.detach(),e=0;e<k;e++)l.append(j.eq(m[e]));l.append(n),c(".screen-desktop .widget-set.set-a:not(:first-child), .screen-desktop .widget-set.set-b:not(:first-child), .screen-desktop .widget-set.set-c:not(:first-child)").addClass("hide-set").addClass("hide-widget"),c("body").hasClass("screen-mobile")?labelWidgets():desktopSidebar(a,b,d)};var t=getCookie("pages-viewed"),u=.5,v=1,w=document.getElementById("loader"),x=getComputedStyle(w).getPropertyValue("background-color"),[y,j,g,b]=x.match(/\d+/g).map(Number),a=setInterval(function(){v-=.01,document.getElementById("loader").style.backgroundColor="rgb("+y+","+j+","+g+","+v+")",.5>v&&clearInterval(a)},10);if(window.resetLoader=function(){document.getElementById("loader").style.backgroundColor="rgb("+y+","+j+","+g+",0.5)"},window.convertBezier=function(a){return"easeInCubic"==a?a="cubic-bezier(0.550, 0.055, 0.675, 0.190)":"easeInQuart"==a?a="cubic-bezier(0.895, 0.030, 0.685, 0.220)":"easeInQuint"==a?a="cubic-bezier(0.755, 0.050, 0.855, 0.060)":"easeInExpo"==a?a="cubic-bezier(0.950, 0.050, 0.795, 0.035)":"easeInBack"==a?a="cubic-bezier(0.600, -0.280, 0.735, 0.045)":"easeOutCubic"==a?a="cubic-bezier(0.215, 0.610, 0.355, 1.000)":"easeOutQuart"==a?a="cubic-bezier(0.165, 0.840, 0.440, 1.000)":"easeOutQuint"==a?a="cubic-bezier(0.230, 1.000, 0.320, 1.000)":"easeOutExpo"==a?a="cubic-bezier(0.190, 1.000, 0.220, 1.000)":"easeOutBack"==a?a="cubic-bezier(0.175, 0.885, 0.320, 1.275)":"easeInOutCubic"==a?a="cubic-bezier(0.645, 0.045, 0.355, 1.000)":"easeInOutQuart"==a?a="cubic-bezier(0.770, 0.000, 0.175, 1.000)":"easeInOutQuint"==a?a="cubic-bezier(0.860, 0.000, 0.070, 1.000)":"easeInOutExpo"==a?a="cubic-bezier(1.000, 0.000, 0.000, 1.000)":"easeInOutBack"==a&&(a="cubic-bezier(0.680, -0.550, 0.265, 1.550)"),a},window.animateDiv=function(a,b,d,e,f,g){d=d||0,e=e||"100%",f=f||1e3,f/=1e3,g=g||"ease";var h=parseFloat(c(a).css("transition-duration")),i=parseFloat(c(a).css("transition-delay"));300<t&&(d*=u,f*=u,h*=u,i*=u),c(a).addClass("animated").css({"animation-duration":f+"s","transition-duration":h+"s","transition-delay":i+"s","animation-timing-function":convertBezier(g)}),c(a+".animated").waypoint(function(){var a=c(this.element);setTimeout(function(){a.addClass(b)},d),this.destroy()},{offset:e})},window.animateDivs=function(a,b,d,e,f,g,h,i){e=e||0,f=f||100,g=g||"100%",h=h||1e3,h/=1e3,i=i||"ease";var j=0,k=b,l=a.split(" "),m=l.pop();300<t&&(e*=u,f*=u,h*=u),c(a).addClass("animated"),setTimeout(function(){c(a).parent().find(m+".animated").waypoint(function(){var a=c(this.element),e=a.prevAll(m).length;a.css({"animation-duration":h+"s","animation-timing-function":convertBezier(i)}),j=6<e?f:e*f,k===d?(setTimeout(function(){a.addClass(b)},j),k=b):(setTimeout(function(){a.addClass(d)},j),k=d),this.destroy()},{offset:g})},e)},window.animateGrid=function(a,b,d,e,f,g,h,k,l,m){f=f||0,g=g||100,h=h||"100%",k=k||"false",l=l||1e3,l/=1e3,m=m||"ease";var n,o,p=a.split(" "),q=p.pop();300<t&&(f*=u,g*=u,l*=u),c(a).addClass("animated"),c(a).parent().each(function(){var a=c(this),f=a.find(q+".animated").length;if(1==f)a.find(q+".animated").data("animation",{effect:b,delay:0});else if(2==f)a.find(q+".animated:nth-last-child(2)").data("animation",{effect:d,delay:0}),a.find(q+".animated:nth-last-child(1)").data("animation",{effect:e,delay:1});else if(3==f)a.find(q+".animated:nth-last-child(3)").data("animation",{effect:d,delay:0}),a.find(q+".animated:nth-last-child(2)").data("animation",{effect:b,delay:1}),a.find(q+".animated:nth-last-child(1)").data("animation",{effect:e,delay:2});else if(4==f)a.find(q+".animated:nth-last-child(4)").data("animation",{effect:d,delay:0}),a.find(q+".animated:nth-last-child(3)").data("animation",{effect:b,delay:1}),a.find(q+".animated:nth-last-child(2)").data("animation",{effect:b,delay:2}),a.find(q+".animated:nth-last-child(1)").data("animation",{effect:e,delay:3});else for(n=0;n<f;n++)o=n+1,a.find(q+".animated:nth-child("+o+")").data("animation",{effect:b,delay:n})}),c(a).parent().find(q+".animated").waypoint(function(){var a=c(this.element),b=g*a.data("animation").delay+f,d=a.data("animation").effect;a.css({"animation-duration":l+"s","animation-timing-function":convertBezier(m)}),1024<getDeviceW()||"true"==k?setTimeout(function(){a.addClass(d)},b):(d=d.replace("Down","Up"),a.addClass(d)),this.destroy()},{offset:h})},window.animateCSS=function(a,b,d,e){b=b||0,d=d||"100%",e=e||1e3,e/=1e3,c(a).addClass("animate"),c(a+".animate").waypoint(function(){var a=c(this.element);a.css({"transition-duration":e+"s"}),setTimeout(function(){a.removeClass("animate")},b),this.destroy()},{offset:d})},window.animateBtn=function(a,b,d,e){a=a||".menu",b=b||"li:not(.active)",e=e||"both";var f=c(a).find(b);d=d||"go-animated",f.bind("webkitAnimationEnd mozAnimationEnd msAnimationEnd oAnimationEnd animationEnd",function(){c(this).removeClass(d)}),"in"==e?f.mouseenter(function(){c(this).addClass(d)}):"out"==e?f.mouseleave(function(){c(this).addClass(d)}):f.hover(function(){c(this).addClass(d)})},window.animateCharacters=function(a,b,d,e,f,g,h){c(a).each(function(){var a=c(this);e=e||0,f=f||100,g=g||"100%",h=h||"false",300<t&&(e*=u,f*=u);var j=e,k=a.html();if(!1==k.includes("<")){if("false"!=h){var l,m=a.html().split(" "),n=m.length,o="",p=b;for(l=0;l<n;l++)o+=l==n-1?"<div class=\"wordSplit animated\">"+m[l]:"<div class=\"wordSplit animated\">"+m[l]+"&nbsp;</div>"}else{var l,m=a.html().split(""),n=m.length,o="",p=b;for(l=0;l<n;l++)" "===m[l]&&(m[l]="&nbsp;"),o+="<div class=\"charSplit animated\">"+m[l]+"</div>"}a.html(o),a.find(".charSplit.animated, .wordSplit.animated").waypoint(function(){var a=c(this.element);p===d?(setTimeout(function(){a.addClass(b)},j),p=b):(setTimeout(function(){a.addClass(d)},j),p=d),this.destroy(),j+=f},{offset:g})}})},c("p:empty, .archive-intro:empty, div.restricted, div.restricted + ul, li.menu-item + ul.sub-menu").remove(),c("#masthead + section").addClass("page-begins"),c("div.noFX").find("img, a").addClass("noFX"),c(".far, .fas, .fab").addClass("fa"),getCookie("first-page")?c("body").addClass("not-first-page"):(c("body").addClass("first-page"),setCookie("first-page","no")),!getCookie("unique-id")){var r=Math.floor(Date.now())+""+Math.floor(900*Math.random()+99);setCookie("unique-id",r),setCookie("pages-viewed",1)}else{var z=getCookie("pages-viewed");z++,setCookie("pages-viewed",z)}if(c("body").hasClass("single-optimized")){var A=location.pathname+location.search,B=A.replace(/\//g,"");setCookie("home-url",B)}if(getCookie("home-url")&&c("a[href=\""+window.location.origin+"\"], a[href=\""+window.location.origin+"/\"]").each(function(){c(this).attr("href",window.location.origin+"/"+getCookie("home-url"))}),getCookie("site-location")||null!=getUrlVar("l")){var C=getCookie("site-location");null!=getUrlVar("l")&&(C=getUrlVar("l")),c("a").each(function(){var a=c(this).attr("href")+"",b="?";c(this).hasClass("loc-ignore")||a.includes("#")||"undefined"==a||(a.includes("?")&&(b="&"),c(this).attr("href",c(this).attr("href")+b+"l="+C))})}c("#request-quote-modal div.form-input").each(function(){var a=c(this).find("label"),b=c(this).find("input"),d=b.attr("id");a.attr("for","modal-"+d),b.attr("id","modal-"+d)}),c(".screen-mobile .section.section-lock.position-modal .flex").each(function(){c(this).height()>getDeviceH()-100&&c(this).addClass("shrink"),.85*c(this).height()>getDeviceH()-100&&c(this).removeClass("shrink").addClass("shrink-more")}),c("img").addClass("unloaded"),c("img").one("load",function(){c(this).removeClass("unloaded")}).each(function(){this.complete&&c(this).trigger("load")}),c(".testimonials-rating").each(function(){var a,b,d=c(this).html(),e=["far","far","far","far","far"];for(b=0;b<d;b++)e[b]="fas";for(a="<span class=\"rating rating-"+d+"-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated "+d+" Stars</span>",b=0;5>b;b++)a+="<i class=\"fa "+e[b]+" fa-star\"></i>";a+="</span>",c(this).html(a)}),c(".wpcf7 form, .wpcf7 form .flex").each(function(){var a=c(this),b=0;a.find("> .form-input.width-default label").each(function(){var a=c(this),d=a.width();d>b&&(b=d)}),a.find("> .form-input.width-default").css({"grid-template-columns":b+"px 1fr"})}),c("abbr.required, em.required, span.required").text(""),setTimeout(function(){c("abbr.required, em.required, span.required").text("")},2e3),moveDiv("#user_switching_switch_on","#page","before"),c(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr("role","menu").attr("aria-label","Main Menu"),c(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr("role","menu"),c(".main-navigation li, .widget-navigation li").attr("role","none"),c(".main-navigation a[href], .widget-navigation a[href]").attr("role","menuitem");var D=c(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor");D.addClass("active"),D.find(">a").attr("aria-current","page"),c(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li").hover(function(){D.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),D.replaceClass("dormant","active")});var E=c(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");if(E.addClass("active"),E.find(">a").attr("aria-current","page"),c(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li").hover(function(){E.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),E.replaceClass("dormant","active")}),c("a[href^=\"#\"]:not(.carousel-control-next):not(.carousel-control-prev)").on("click",function(a){a.preventDefault();var b=this.hash,d=+c(b).attr("data-hash");isNaN(d)&&(d=0),""!=b&&(c("*"+b).length?(animateScroll(b,d),setTimeout(function(){animateScroll(b,d)},100)):window.location.href="/"+b)}),c(".menu-item:not(.no-highlight) a[href^=\"#\"]").is(":visible")){var F=c("nav:visible").find("ul"),G=.35*c(window).outerHeight(),H=F.outerHeight()+G,I=F.find("a[href^=\"#\"]"),J=I.map(function(){var a=c(c(this).attr("href"));if("none"!=c(this).parent().css("display"))return a});c(window).scroll(function(){var a,b,d=c(this).scrollTop()+H,e=J.map(function(){void 0!==c(this).offset()&&c(this).offset().top<d&&(a="#"+c(this)[0].id,clearTimeout(b),b=setTimeout(function(){F.find("li").removeClass("current-menu-item").removeClass("current_page_item").removeClass("active"),F.find("a[href^=\""+a+"\"]").closest("li").addClass("current-menu-item").addClass("current_page_item").addClass("active")},10),closeMenu())})})}setTimeout(function(){location.hash&&window.scrollBy({top:-(.13*getDeviceH()),left:0,behavior:"smooth"})},100),c("#mobile-navigation li.menu-item-has-children > a").each(function(){c(this).attr("data-href",c(this).attr("href")).attr("href","javascript:void(0)")}),window.closeMenu=function(){c("#mobile-menu-bar .activate-btn").removeClass("active"),c("body").removeClass("mm-active"),c(".top-push.screen-mobile #page").css({top:"0"}),c(".top-push.screen-mobile .top-strip.stuck").css({top:h+"px"})},window.openMenu=function(){c("#mobile-menu-bar .activate-btn").addClass("active"),c("body").addClass("mm-active");var a=c("#mobile-navigation").outerHeight(),b=a+h;c(".top-push.screen-mobile.mm-active #page").css({top:a+"px"}),c(".top-push.screen-mobile.mm-active .top-strip.stuck").css({top:b+"px"})},c("#mobile-menu-bar .activate-btn").click(function(){c(this).hasClass("active")?closeMenu():openMenu()}),window.closeSubMenu=function(a){c(a).removeClass("active"),c(a).height(0),c(a).prev().attr("href","javascript:void(0)")},window.openSubMenu=function(a,b){c("#mobile-navigation ul.sub-menu").removeClass("active"),c("#mobile-navigation ul.sub-menu").height(0),c("#mobile-navigation li.menu-item-has-children > a").attr("href","javascript:void(0)"),c(a).addClass("active"),c(a).height(b+"px"),setTimeout(function(){c(a).prev().attr("href",c(a).prev().attr("data-href"))},500)},c("#mobile-navigation").addClass("get-sub-heights"),c("#mobile-navigation ul.sub-menu").each(function(){var a=c(this);a.data("getH",a.outerHeight(!0)),closeSubMenu(a),a.parent().click(function(){a.hasClass("active")?closeSubMenu(a):openSubMenu(a,a.data("getH"))})}),c("#mobile-navigation").removeClass("get-sub-heights"),c("#mobile-navigation li:not(.menu-item-has-children)").each(function(){c(this).click(function(){closeMenu()})});var K=new Date().getDay();c(".office-hours .row-"+["sun","mon","tue","wed","thu","fri","sat"][K]).addClass("today");for(var L=document.getElementsByClassName("video-player"),M=0;M<L.length;M++){var N=L[M].dataset.id,O=L[M].dataset.link,P=document.createElement("div"),Q=document.createElement("img"),R=document.createElement("div");P.setAttribute("data-id",N),P.setAttribute("data-link",O),Q.src=L[M].dataset.thumb,P.appendChild(Q),R.setAttribute("class","play"),P.appendChild(R),P.onclick=function(){d(this)},L[M].appendChild(P)}c(window).on("load",function(){screenResize()}),c(window).resize(function(){screenResize()}),window.screenResize=function(){var a=getDeviceW(),b=getDeviceH();c("body").removeClass("screen-5").removeClass("screen-4").removeClass("screen-3").removeClass("screen-2").removeClass("screen-1").removeClass("screen-mobile").removeClass("screen-desktop"),1280<a?c("body").addClass("screen-5").addClass("screen-desktop"):1280>=a&&1024<a?c("body").addClass("screen-4").addClass("screen-desktop"):1024>=a&&860<a?c("body").addClass("screen-3").addClass("screen-mobile"):860>=a&&576<a?c("body").addClass("screen-2").addClass("screen-mobile"):c("body").addClass("screen-1").addClass("screen-mobile"),h=1024<a?0:c("#mobile-menu-bar").outerHeight(),c("#mobile-navigation > #mobile-menu .menu-search-box input[type=\"search\"]").is(":focus")||closeMenu(),moveDiv(".screen-mobile.not-first-page #secondary","#colophon","before"),moveDiv(".screen-desktop #secondary","#primary","after"),c("div[class*=\"-faux\"]").each(function(){var a=c(this),b="."+a.attr("class"),d=b.replace("-faux","");if(!c(d).length){var e="#"+a.attr("class");d=e.replace("-faux","")}c(d).is(":visible")?(c(b).height(c(d).outerHeight()),c(".wp-google-badge-faux").height(c(".wp-google-badge").outerHeight())):c(b).height(0)}),b>c("#page").outerHeight()?c("#wrapper-content").addClass("extended"):c("#wrapper-content").removeClass("extended")},c("h3 a[aria-hidden=\"true\"]").each(function(){c(this).parent().attr("aria-label",c(this).text())}),setTimeout(function(){c("img:not([alt])").attr("alt","")},50),setTimeout(function(){c("img:not([alt])").attr("alt","")},1e3),c("[role=\"menu\"]").on("focus.aria mouseenter.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).addClass("menu-item-expanded").attr("aria-expanded",!0)}),c("[role=\"menu\"]").on("blur.aria mouseleave.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).removeClass("menu-item-expanded").attr("aria-expanded",!1)}),c("[role=\"menu\"] a").attr("tabindex","0"),c("li[aria-haspopup=\"true\"]").attr("tabindex","-1"),c(window).on("load",function(){clearInterval(a),c("#loader").fadeOut(300,function(){resetLoader()}),window.addEventListener("beforeunload",function(){c("#loader").fadeIn(300)}),c("section.section-lock").each(function(){var a=c(this),b=a.attr("data-delay"),d=a.attr("data-pos"),e=a.attr("data-show"),f=a.attr("data-btn");"always"==e&&(e=1e-6),"never"==e&&(e=1e5),"session"==e&&(e=null),keyPress(a.find(".closeBtn")),"top"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({top:h+"px"}),setTimeout(function(){a.addClass("on-screen"),c("body").addClass("locked"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked"),setCookie("display-message","no",e)})):"bottom"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({bottom:"0"}),setTimeout(function(){a.addClass("on-screen"),c("body").addClass("locked"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked"),setCookie("display-message","no",e)})):"header"==d?"no"!==getCookie("display-message")&&(moveDiv(a.find(".closeBtn"),".section-lock.position-header .col-inner","top"),a.css({display:"grid"}),a.find(".closeBtn").click(function(){a.css({"max-height":0,"padding-top":0,"padding-bottom":0,"margin-top":0,"margin-bottom":0}),setCookie("display-message","no",e)})):("no"==f&&"no"!==getCookie("display-message")&&(moveDiv(a.find(".closeBtn"),".section-lock > .flex","top"),setTimeout(function(){a.addClass("on-screen"),c("body").addClass("locked"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked"),setCookie("display-message","no",e)})),"yes"==f&&(c(".modal-btn").click(function(){moveDiv(a.find(".closeBtn"),".section-lock > .flex","top"),a.addClass("on-screen"),c("body").addClass("locked"),a.focus()}),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),c("body").removeClass("locked")}))),a.click(function(){setCookie("display-message","no",e)})})})})(jQuery)});