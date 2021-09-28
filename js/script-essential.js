document.addEventListener("DOMContentLoaded",function(){"use strict";(function(c){var d,e,f=site_dir.theme_dir_uri,h=site_dir.upload_dir_uri,i=0,j=.5;c("#mobile-menu-bar").is(":visible")&&(i=c("#mobile-menu-bar").outerHeight());var k="noID";c.each(c("body").attr("class").split(" "),function(a,b){0===b.indexOf("postid-")?k=b.substr(7):0===b.indexOf("page-id-")&&(k=b.substr(8))}),c("body").attr("id",k);var l=window.location.pathname;l=l.substr(1).slice(0,-1).replace("/","-"),l=l?"slug-"+l:"slug-home",c("body").addClass(l),window.isApple=function(){var a=!!navigator.platform&&/iPad|iPhone|iPod/.test(navigator.platform);return a},window.getDomain=function(){return window.location.hostname},window.getSlug=function(){var a=window.location.pathname.split("/");return a[1]},window.getUrlVar=function(a){a=a||"page";var b=new RegExp("[?&]"+a+"=([^&#]*)").exec(window.location.href);return null==b?null:decodeURI(b[1])||0},c(".logo").keyup(function(a){(13===a.keyCode||32===a.keyCode)&&c(this).click()}),c(".logo").attr("tabindex","0").attr("role","button").attr("aria-label","Return to Home Page").css("cursor","pointer").click(function(){window.location="/"}),c(".logo img").attr("aria-hidden","true"),window.linkHome=function(a){c(a).keyup(function(a){(13===a.keyCode||32===a.keyCode)&&c(this).click()}),c(a).attr("tabindex","0").attr("role","button").attr("aria-label","Return to Home Page").css("cursor","pointer").click(function(){window.location="/"}),c(a).find("img").attr("aria-hidden","true")},c("img[src*='hvac-american-standard/american-standard']").each(function(){c(this).wrap("<a href=\"https://www.americanstandardair.com/\" target=\"_blank\" rel=\"noreferrer\"></a>")}),window.trackClicks=function(a,b,d,e){document.location=e,c.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_link_clicks",type:d},success:function(a){console.log(a)}})},window.setCookie=function(a,b,c){var e=document.domain.match(/[^\.]*\.[^.]*$/)[0],f=new Date,d="";f.setTime(f.getTime()+1e3*(60*(60*(24*c)))),null!=c&&""!=c&&(d="expires="+f.toGMTString()+"; "),document.cookie=a+"="+b+"; "+d+"path=/; domain="+e+"; secure"},window.getCookie=function(a){for(var b,d=a+"=",e=document.cookie.split(";"),f=0;f<e.length;f++)if(b=e[f].trim(),0==b.indexOf(d))return b.substring(d.length,b.length);return""},window.deleteCookie=function(a){setCookie(a,"",-1)},getCookie("first-page")?c("body").addClass("not-first-page"):(setCookie("first-page","no"),c("body").addClass("first-page")),getCookie("pages-viewed")?(d=+getCookie("pages-viewed"),getCookie("prev-page")!=getSlug()&&(d++,setCookie("pages-viewed",d),setCookie("prev-page",getSlug())),c("body").attr("data-pageviews",d),c("body").attr("data-unique-id",getCookie("unique-id"))):(e=+(Date.now()+Math.floor(100*Math.random())),setCookie("unique-id",e),c("body").attr("data-unique-id",e),c("body").attr("data-pageviews",1),setCookie("pages-viewed",1)),c.fn.hasPartialClass=function(a){return new RegExp(a).test(this.prop("class"))},window.getDeviceW=function(){var a=c(window).width();return isApple()&&(90==window.orientation||-90==window.orientation)&&(a=window.screen.height),a},window.getDeviceH=function(){var a=c(window).height();return isApple()&&(90==window.orientation||-90==window.orientation)&&(a=window.screen.width),a},window.getMobileCutoff=function(){return 1024},window.getTabletCutoff=function(){return 576},window.copyClasses=function(a,b,d){b=b||"img, iframe",c(a).each(function(){c(this).addClass(c(this).find(b).map(function(){return this.className}).get().join(" ")).addClass(d)})},window.shuffleArray=function(a){for(let b,c=a.length-1;0<c;c--)b=Math.floor(Math.random()*(c+1)),[a[c],a[b]]=[a[b],a[c]]},window.replaceText=function(a,b,d,e,f){e=e||"text",f=f||"false","true"==f&&(b=new RegExp(b,"gi")),"text"==e?c(a).text(function(){if(""!=b){var a=c(this).text();return a.replace(b,d)}return d}):c(a).html(function(){if(""!=b){var a=c(this).html();return a.replace(b,d)}return d})},window.trimText=function(a,b){trimText.done||(a=a||250,b=b||".slider-testimonials .testimonials-quote, #secondary .testimonials-quote, .random-post .testimonials-quote",c(b).each(function(){var b=c(this).html(),d=a,e=b.substr(0,d);e.length==d&&(e=e.substr(0,Math.min(e.length,e.lastIndexOf(" "))),c(this).html(e+"\u2026"))}),trimText.done=!0)},window.removeSidebar=function(a){var b=a.replace(".",""),d="slug-"+a.replace(/\//g,"");(c("body").hasClass(b)||c("body").hasClass(d))&&(c("body").removeClass("sidebar-right").removeClass("sidebar-left").addClass("sidebar-none"),removeDiv("#secondary"))},window.addStuck=function(a,b){b=b||"true";var d=a.split(" ").pop();c(a).is(":visible")&&(c(a).addClass("stuck"),"true"==b&&addFaux(d))},window.removeStuck=function(a,b){b=b||"true";var d=a.split(" ").pop();c(a).removeClass("stuck"),"true"==b&&removeFaux(d)},window.addFaux=function(a,b){b=b||"false";var d=c(a),e=a.substr(1);if(d.is(":visible")){c("<div class='"+e+"-faux'></div>").insertBefore(d);var f=c("."+e+"-faux");f.css({height:d.outerHeight()+"px"}),"true"==b&&d.css({position:"fixed"})}},window.removeFaux=function(a){var b=a.substr(1),d=c("."+b+"-faux");d.remove()},window.lockDiv=function(a,b,d,e,f,g){b=b||"",d=d||"",e=e||"",f=f||"true",g=g||"both";var h,j;h=""===b?c(a).next().length?c(a).next():c(a).parent().next():c(b),j=""===d?""===e?c(a).outerHeight():c(a).outerHeight()+ +e:+d,c(a).css("top","unset"),h.waypoint(function(b){var d=0;""===e?c(".stuck").each(function(){d+=c(this).outerHeight(!0)}):d=+e,d+=i,"down"===b&&("both"===g||"down"===g)?(addStuck(a,f),c(a).css("top",d+"px")):"up"==b&&("both"===g||"up"===g)&&(removeStuck(a,f),c(a).css("top","unset"))},{offset:j+"px"})},window.lockMenu=function(){lockDiv("#desktop-navigation")},window.addStroke=function(a,b,c,d,e,f){var g,h,j,k,l="";e=e||c,d=d||c,f=f||d;for(var m=0;16>m;m++)h=2*m*Math.PI/16,j=Math.round(1e4*Math.cos(h))/1e4,k=Math.round(1e4*Math.sin(h))/1e4,0>=j&&0>=k&&(g=c),0<=j&&0<=k&&(g=d),0>=j&&0<=k&&(g=e),0<=j&&0>=k&&(g=f),l+="calc("+b+"px * "+j+") calc("+b+"px * "+k+") 0 "+g,15>m&&(l+=", ");var n=document.createElement("style");n.innerHTML=a+" { text-shadow: "+l+"; letter-spacing: "+(b-1)+"px; }",document.head.appendChild(n)},window.animateScroll=function(a,b,d){var e=0,f=0;d=d||0,b=b||0,b+=i,c(".stuck").each(function(){e+=c(this).outerHeight()}),f="object"==typeof a||"string"==typeof a?c(a).offset().top-e-b:a-e-b,"#tab-description"!==a&&window.scroll({top:f,left:0,behavior:"smooth"})};var m=c("#wrapper-content").waypoint(function(a){"up"===a?c("a.scroll-top").animate({opacity:0},150,function(){c("a.scroll-top").css({display:"none"})}):c("a.scroll-top").css({display:"block"}).animate({opacity:1},150)},{offset:"10%"}),n=c("#wrapper-content").waypoint(function(a){"down"===a?c(".scroll-down").fadeOut("fast"):c(".scroll-down").fadeIn("fast")},{offset:"99%"});window.getPosition=function(a,b,d){d=d||"window";var e,f,g,h,i,j,k,l,m=c(a);return"window"==d||"screen"==d?(e=m.offset().left,f=m.offset().top):(e=m.position().left,f=m.position().top),g=m.outerWidth(!0),h=m.outerHeight(!0),i=f+h,j=e+g,k=e+g/2,l=f+h/2,"left"==b||"l"==b?e:"top"==b||"t"==b?f:"bottom"==b||"b"==b?i:"right"==b||"r"==b?j:"centerX"==b||"centerx"==b||"center-x"==b?k:"centerY"==b||"centery"==b||"center-y"==b?l:void 0},window.getTranslate=function(a,b){b=b||"Y";var c=document.querySelector(a),d=window.getComputedStyle(c),e=d.transform||d.webkitTransform||d.mozTransform,f=e.match(/matrix.*\((.+)\)/)[1].split(", ");return"x"==b||"X"==b?f[4]:"y"==b||"Y"==b?f[5]:void 0},window.buildAccordion=function(a,b,d,f,g,h){if(!buildAccordion.done){d=d||500,f=f||0,g=g||0,b=b||f+g,a=a||.1,h=h||"close";var j=[];1>a&&(a=getDeviceH()*a),1024>getDeviceW()&&(a+=i),c(".block-accordion").attr("aria-expanded",!1),c(".block-accordion").first().addClass("accordion-first"),c(".block-accordion").last().addClass("accordion-last"),c(".block-accordion").parents(".col-archive").length?(c(".block-accordion").parents(".col-archive").addClass("archive-accordion"),c(".archive-accordion").each(function(){j.push(c(this).offset().top)})):c(".block-accordion").each(function(){j.push(c(this).offset().top)}),c(".block-accordion.start-active").attr("aria-expanded",!0).addClass("active"),c(".block-accordion.start-active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},0),c(".block-accordion.start-active .accordion-content").animate({height:"toggle",opacity:"toggle"},0),c(".block-accordion").keyup(function(a){(13===a.keyCode||32===a.keyCode)&&c(this).click()}),c(".block-accordion .accordion-title").click(function(i){i.preventDefault();var e=c(this),k=e.closest(".block-accordion"),l=k.index(".block-accordion"),m=j[l],n=j[0],o=0,p=e.attr("data-text"),q=e.attr("data-collapse");k.hasClass("active")?"close"==h&&(setTimeout(function(){c(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},d),c(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},d),"false"!=q&&e.text(p)},f),setTimeout(function(){c(".block-accordion.active").removeClass("active").attr("aria-expanded",!1)},b)):(setTimeout(function(){c(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},d),c(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},d),"hide"==q?e.fadeOut():"false"!=q&&e.text(q)},f),setTimeout(function(){k.find(".accordion-excerpt").animate({height:"toggle",opacity:"toggle"},d),k.find(".accordion-content").animate({height:"toggle",opacity:"toggle"},d),null==q&&(m-n>.25*getDeviceH()?(o=m,animateScroll(o,a,d)):(o=(m-n)/2+n,animateScroll(o,a,d)))},g),setTimeout(function(){c(".block-accordion.active").removeClass("active").attr("aria-expanded",!1),k.addClass("active").attr("aria-expanded",!0)},b))}),buildAccordion.done=!0}},c(".main-navigation ul.main-menu li > a").each(function(){var a=c(this).html();c(this).parent().attr("data-content",a)}),c(".logo-slider").each(function(){var a,b,d=c(this),e=d.find(".logo-row"),f=d.attr("data-speed"),g=1e3*parseInt(d.attr("data-delay")),h=0,i=getDeviceW()*(parseInt(d.attr("data-maxw"))/100),j=d.attr("data-pause"),k=getDeviceW()*(parseInt(d.attr("data-spacing"))/100),l="swing",m=!0,n=0,o=0,p=0,q=0,r=0,s=0,t=0;e.css({opacity:0}),"0"==g&&(l="linear"),("yes"==j||"true"==j)&&(d.mouseover(function(){m=!1}),d.mouseout(function(){m=!0})),1024>getDeviceW()&&(k*=1.5),setTimeout(function(){d.find("span").find("img").each(function(){p=parseInt(c(this).attr("width")),p>i&&(p=i,c(this).width(p)),p>n&&(n=p),t=t+k+p}),0==g&&(t=0,d.find("span").find("img").each(function(){c(this).parent().width(n),t=t+k+n})),setTimeout(function(){function c(){!1!=m&&(a=e.find("span:nth-of-type(1)"),q=a.position().left+a.width(),b=e.find("span:nth-of-type(2)"),r=b.position().left,s=a.width()+r-q,console.log(h),e.animate({"margin-left":-s+"px"},f,l,function(){a.remove(),e.find("span:last").after(a),e.css({"margin-left":"0px"})}))}o=getDeviceW()+n+k,t<o&&(t=o),e.css("width",t),f="slow"==f?3*t:"fast"==f?1.5*t:t*parseInt(f),h=f+g+15,e.animate({opacity:1},300);setInterval(c,h)},10)},1500)}),window.filterArchives=function(a,b,d,e){a=a||null,b=b||".section.archive-content",d=d||".col-archive",e=e||300,c(b).fadeTo(e,0,function(){""==a||null==a?c(d).show():(c(d).hide(),c(d+"."+a).show()),c(d).css({clear:"none"}),c(b).fadeTo(e,1)})},c(".filter-btn").click(function(){var a=c(this),b="?"+a.attr("data-url")+"=",d=!1;c("input:checkbox[name=choice]:checked").each(function(){d?b=b+","+c(this).val():(b+=c(this).val(),d=!0)}),window.location=b}),c("ul.tabs li").keyup(function(a){var b=c(this);(13===a.keyCode||32===a.keyCode)&&b.click()}),c("ul.tabs li").click(function(){var a=c(this).attr("data-tab"),b=150;c("ul.tabs li").removeClass("current"),c(this).addClass("current"),c(".tab-content").fadeOut(b).next().removeClass("current"),c("#"+a).delay(b).addClass("current").fadeIn(b)}),window.prepareJSON=function(a){return a=a.replace(/%7B/g,"{").replace(/%7D/g,"}").replace(/%22/g,"\"").replace(/%3A/g,":").replace(/%2C/g,","),c.parseJSON(a)},window.revealDiv=function(a,b,d,e){b=b||0,d=d||1e3,e=e||"100%";var f=c(a),g=f.next(),h=f.outerHeight();g.css({transform:"translateY(-"+h+"px)","transition-duration":0}),setTimeout(function(){f.waypoint(function(){g.css({transform:"translateY(0)","transition-duration":d+"ms"})},{offset:e})},b)},window.btnRevealDiv=function(a,b,d,e){e=e||0,d=d||0;var f=c(b).css("display");1024>getDeviceW()&&(d+=c("#mobile-menu-bar").outerHeight()),c(b).css("display","none"),c(a).click(function(){c(b).css("display",f);animateScroll(b,d,e)})},c(".review-form:first").addClass("active"),c(".review-form #gmail-yes").click(function(){window.location.href="/google"}),c(".review-form #facebook-yes").click(function(){window.location.href="/facebook"}),c(".review-form #gmail-no, .review-form #facebook-no").click(function(){c(this).closest(".review-form").removeClass("active"),c(this).closest(".review-form").next().addClass("active")}),c.fn.replaceClass=function(a,b){return this.removeClass(a).addClass(b)},c.fn.random=function(){return this.eq(Math.floor(Math.random()*this.length))},window.cloneDiv=function(a,b,d){d=d||"after";var e=c(a),f=c(b);"after"==d?e.clone().insertAfter(f):"before"==d?e.clone().insertBefore(f):"top"==d||"start"?f.prepend(e.clone()):f.append(e.clone())},window.moveDiv=function(a,b,d){d=d||"after";var e=c(a),f=c(b);"after"==d?e.insertAfter(f):"before"==d?e.insertBefore(f):"top"==d||"start"?f.prepend(e):f.append(e)},window.moveDivs=function(a,b,d,e){e=e||"after",c(a).each(function(){var a=c(this);"after"==e?a.find(c(b)).insertAfter(a.find(d)):"before"==e?a.find(c(b)).insertBefore(a.find(d)):"top"==e||"start"?a.find(d).prepend(a.find(c(b))):a.find(d).append(a.find(c(b)))})},window.addDiv=function(a,b,d){b=b||"<div></div>",d=d||"after";var e=c(b),f=c(a);"after"==d?f.append(e):"before"==d?f.prepend(e):e.insertBefore(f)},window.wrapDiv=function(a,b,d){b=b||"<div></div>",d=d||"outside","outside"==d?c(a).each(function(){c(this).wrap(b)}):c(a).each(function(){c(this).wrapInner(b)})},window.wrapDivs=function(a,b){b=b||"<div />",c(a).wrapAll(b)},window.removeParent=function(a){c(a).unwrap()},window.removeDiv=function(a){c(a).remove()},window.svgBG=function(a,b,d){var e=c(a),f=c(b);d=d||"top","bottom"==d?e.clone().css({position:"absolute"}).appendTo(f):"top"==d?e.clone().css({position:"absolute"}).prependTo(f):"before"==d||"start"==d?e.clone().css({position:"absolute"}).insertBefore(f):e.clone().css({position:"absolute"}).insertAfter(f)},"function"!=typeof parallaxBG&&(window.parallaxBG=window.parallaxDiv=window.magicMenu=window.splitMenu=window.addMenuLogo=window.desktopSidebar=function(){}),window.setupSidebar=function(a,b,d){b=b||"true",d=d||"true",a=a||0,window.labelWidgets=function(){c(".widget:not(.hide-widget)").first().addClass("widget-first"),c(".widget:not(.hide-widget)").last().addClass("widget-last"),c(".widget:not(.hide-widget):odd").addClass("widget-even"),c(".widget:not(.hide-widget):even").addClass("widget-odd")};var e,f,g,h,j=c(".widget:not(.lock-to-top):not(.lock-to-bottom)"),k=j.length,l=j.parent(),m=[],n=c(".widget.lock-to-bottom").detach();for(e=0;e<k;e++)m.push(e);if("true"==d)for(e=0;e<k;e++)f=0|Math.random()*k,g=0|Math.random()*k,h=m[f],m[f]=m[g],m[g]=h;for(j.detach(),e=0;e<k;e++)l.append(j.eq(m[e]));l.append(n),c(".widget-set.set-a:not(:first-child), .widget-set.set-b:not(:first-child), .widget-set.set-c:not(:first-child)").addClass("hide-set").addClass("hide-widget"),c("body").hasClass("screen-mobile")?labelWidgets():desktopSidebar(a,b,d)};var o=1,p=document.getElementById("loader"),q=getComputedStyle(p).getPropertyValue("background-color"),[s,r,g,b]=q.match(/\d+/g).map(Number),a=setInterval(function(){o-=.01,document.getElementById("loader").style.backgroundColor="rgb("+s+","+r+","+g+","+o+")",.3>o&&clearInterval(a)},10);window.animateDiv=function(a,b,e,f,g){e=e||0,f=f||"100%",g=g||1e3,g/=1e3;var h=parseFloat(c(a).css("transition-duration")),i=parseFloat(c(a).css("transition-delay"));3<d&&(e*=j,g*=j,h*=j,i*=j),c(a).addClass("animated"),c(a).css({"animation-duration":g+"s","transition-duration":h+"s","transition-delay":i+"s"}),c(a+".animated").waypoint(function(){var a=c(this.element);setTimeout(function(){a.addClass(b)},e),this.destroy()},{offset:f})},window.animateDivs=function(a,b,e,f,g,h,i){f=f||0,g=g||100,h=h||"100%",i=i||1e3,i/=1e3,3<d&&(f*=j,g*=j,i*=j);var k=0,l=b,m=c(a).parent(),n=a.split(" "),o=n.pop();c(a).addClass("animated"),setTimeout(function(){m.find(o+".animated").waypoint(function(){var a=c(this.element),d=a.prevAll(o).length;a.css({"animation-duration":i+"s"}),k=6<d?g:d*g,l===e?(setTimeout(function(){a.addClass(b)},k),l=b):(setTimeout(function(){a.addClass(e)},k),l=e),this.destroy()},{offset:h})},f)},window.animateGrid=function(a,b,e,f,g,h,i,k,l){g=g||0,h=h||100,i=i||"100%",k=k||"false",l=l||1e3,l/=1e3,3<d&&(g*=j,h*=j,l*=j);var m=c(a).parent(),n=a.split(" "),o=n.pop();c(a).addClass("animated"),m.each(function(){var a=c(this),d=a.find(o+".animated").length;1==d&&a.find(o+".animated").data("animation",{effect:b,delay:0}),2==d&&(a.find(o+".animated:nth-last-child(2)").data("animation",{effect:e,delay:0}),a.find(o+".animated:nth-last-child(1)").data("animation",{effect:f,delay:1})),3==d&&(a.find(o+".animated:nth-last-child(3)").data("animation",{effect:e,delay:0}),a.find(o+".animated:nth-last-child(2)").data("animation",{effect:b,delay:1}),a.find(o+".animated:nth-last-child(1)").data("animation",{effect:f,delay:2})),4==d&&(a.find(o+".animated:nth-last-child(4)").data("animation",{effect:e,delay:0}),a.find(o+".animated:nth-last-child(3)").data("animation",{effect:b,delay:1}),a.find(o+".animated:nth-last-child(2)").data("animation",{effect:b,delay:2}),a.find(o+".animated:nth-last-child(1)").data("animation",{effect:f,delay:3})),(5==d||6==d)&&(a.find(o+".animated:nth-last-child(6)").data("animation",{effect:b,delay:0}),a.find(o+".animated:nth-last-child(5)").data("animation",{effect:b,delay:1}),a.find(o+".animated:nth-last-child(4)").data("animation",{effect:b,delay:2}),a.find(o+".animated:nth-last-child(3)").data("animation",{effect:b,delay:3}),a.find(o+".animated:nth-last-child(2)").data("animation",{effect:b,delay:4}),a.find(o+".animated:nth-last-child(1)").data("animation",{effect:b,delay:5})),(7==d||8==d)&&(a.find(o+".animated:nth-last-child(8)").data("animation",{effect:b,delay:0}),a.find(o+".animated:nth-last-child(7)").data("animation",{effect:b,delay:1}),a.find(o+".animated:nth-last-child(6)").data("animation",{effect:b,delay:2}),a.find(o+".animated:nth-last-child(5)").data("animation",{effect:b,delay:3}),a.find(o+".animated:nth-last-child(4)").data("animation",{effect:b,delay:4}),a.find(o+".animated:nth-last-child(3)").data("animation",{effect:b,delay:5}),a.find(o+".animated:nth-last-child(2)").data("animation",{effect:b,delay:6}),a.find(o+".animated:nth-last-child(1)").data("animation",{effect:b,delay:7}))}),m.find(o+".animated").waypoint(function(){var a=c(this.element),b=h*a.data("animation").delay+g,d=a.data("animation").effect;a.css({"animation-duration":l+"s"}),1024<getDeviceW()||"true"==k?setTimeout(function(){a.addClass(d)},b):a.addClass("fadeInUpSmall"),this.destroy()},{offset:i})},window.animateCSS=function(a,b,d,e){b=b||0,d=d||"100%",e=e||1e3,e/=1e3,c(a).addClass("animate"),c(a+".animate").waypoint(function(){var a=c(this.element);a.css({"transition-duration":e+"s"}),setTimeout(function(){a.removeClass("animate")},b),this.destroy()},{offset:d})},window.animateBtn=function(a,b,d,e){a=a||".menu",b=b||"li:not(.active)",e=e||"both";var f=c(a).find(b);d=d||"go-animated",f.bind("webkitAnimationEnd mozAnimationEnd msAnimationEnd oAnimationEnd animationEnd",function(){c(this).removeClass(d)}),"in"==e?f.mouseenter(function(){c(this).addClass(d)}):"out"==e?f.mouseleave(function(){c(this).addClass(d)}):f.hover(function(){c(this).addClass(d)})},window.animateWords=function(a,b,e,f,g,h){f=f||0,g=g||100,h=h||"100%",3<d&&(f*=j,g*=j);var k=c(a);k.each(function(){var a=c(this).html();a=a.split(" ");for(var d="",j=0,l=a.length;j<l;j++)d+=j==l-1?"<div class=\"wordSplit animated\">"+a[j]:"<div class=\"wordSplit animated\">"+a[j]+"&nbsp;</div>";c(this).html(d);var m=f,n=b;k.find(".wordSplit.animated").waypoint(function(){var a=c(this.element);m+=g,n===e?(setTimeout(function(){a.addClass(b)},m),n=b):(setTimeout(function(){a.addClass(e)},m),n=e),this.destroy()},{offset:h})})},window.animateCharacters=function(a,b,e,f,g,h){f=f||0,g=g||100,h=h||"100%",3<d&&(f*=j,g*=j);var k=c(a);k.each(function(){var a=c(this).html();a=a.split("");for(var d="",j=0,l=a.length;j<l;j++)" "===a[j]&&(a[j]="&nbsp;"),d+="<div class=\"charSplit animated\">"+a[j]+"</div>";c(this).html(d);var m=f,n=b;k.find(".charSplit.animated").waypoint(function(){var a=c(this.element);m+=g,n===e?(setTimeout(function(){a.addClass(b)},m),n=b):(setTimeout(function(){a.addClass(e)},m),n=e),this.destroy()},{offset:h})})},removeDiv("p:empty, .archive-intro:empty"),wrapDiv(".site-main","<div class=\"site-main-inner\"></div>","inside"),c("#wrapper-top").length?c("#wrapper-top").addClass("page-begins"):c("#wrapper-content").addClass("page-begins"),c("div.noFX").find("img").addClass("noFX"),c("div.noFX").find("a").addClass("noFX"),c(".far, .fas, .fab").addClass("fa"),c("img").addClass("unloaded"),c("img").one("load",function(){c(this).removeClass("unloaded")}).each(function(){this.complete&&c(this).trigger("load")}),c(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr("role","menubar"),c(".main-navigation li, .widget-navigation li").attr("role","none"),c(".main-navigation a, .widget-navigation a").attr("role","menuitem"),c(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr("role","menu");var t=c(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor");t.addClass("active"),t.find(">a").attr("aria-current","page"),c(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li").hover(function(){t.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),t.replaceClass("dormant","active")});var u=c(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");if(u.addClass("active"),u.find(">a").attr("aria-current","page"),c(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li").hover(function(){u.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),u.replaceClass("dormant","active")}),c("<div class=\"wp-google-badge-faux\"></div>").insertAfter(c("#colophon")),c("a[href^=\"#\"]:not(.carousel-control-next):not(.carousel-control-prev)").on("click",function(a){a.preventDefault();var b=this.hash,d=+c(b).attr("data-hash");isNaN(d)&&(d=0),""!=b&&(c("*"+b).length?(animateScroll(b,d),setTimeout(function(){animateScroll(b,d)},100)):window.location.href="/"+b)}),c(".menu-item:not(.no-highlight) a[href^=\"#\"]").is(":visible")){var v=c("nav:visible").find("ul"),w=.35*c(window).outerHeight(),x=v.outerHeight()+w,y=v.find("a[href^=\"#\"]"),z=y.map(function(){var a=c(c(this).attr("href"));if("none"!=c(this).parent().css("display"))return a});c(window).scroll(function(){var a,b,d=c(this).scrollTop()+x,e=z.map(function(){void 0!==c(this).offset()&&c(this).offset().top<d&&(a="#"+c(this)[0].id,clearTimeout(b),b=setTimeout(function(){v.find("li").removeClass("current-menu-item").removeClass("current_page_item").removeClass("active"),v.find("a[href^=\""+a+"\"]").closest("li").addClass("current-menu-item").addClass("current_page_item").addClass("active")},10),closeMenu())})})}window.closeMenu=function(){c("#mobile-menu-bar .activate-btn").removeClass("active"),c("body").removeClass("mm-active"),c(".top-push.screen-mobile #page").css({top:"0"}),c(".top-push.screen-mobile .top-strip.stuck").css({top:i+"px"})},window.openMenu=function(){c("#mobile-menu-bar .activate-btn").addClass("active"),c("body").addClass("mm-active");var a=c("#mobile-navigation").outerHeight(),b=a+i;c(".top-push.screen-mobile.mm-active #page").css({top:a+"px"}),c(".top-push.screen-mobile.mm-active .top-strip.stuck").css({top:b+"px"})},c("#mobile-menu-bar .activate-btn").click(function(){c(this).hasClass("active")?closeMenu():openMenu()}),window.closeSubMenu=function(a){c(a).removeClass("active"),c(a).height(0)},window.openSubMenu=function(a,b){c("#mobile-navigation ul.sub-menu").removeClass("active"),c("#mobile-navigation ul.sub-menu").height(0),c(a).addClass("active"),c(a).height(b+"px")},c("#mobile-navigation").addClass("get-sub-heights"),c("#mobile-navigation ul.sub-menu").each(function(){var a=c(this),b=a.outerHeight(!0);a.data("getH",b),a.parent().click(function(){a.hasClass("active")?closeSubMenu(a):openSubMenu(a,a.data("getH"))}),closeSubMenu(a)}),c("#mobile-navigation").removeClass("get-sub-heights"),c("#mobile-navigation li:not(.menu-item-has-children)").each(function(){var a=c(this);a.click(function(){closeMenu()})}),c(".carousel").each(function(){var a=c(this),b=0,d=parseInt(a.find(".carousel-inner").css("padding-bottom"));a.data("maxH",0),a.on("slid.bs.carousel",function(){var c=a.find(".carousel-item.active").outerHeight()+d;c>b&&(a.find(".carousel-inner").css("height",c+"px"),b=c)})}),c(".testimonials-rating").each(function(){var a=c(this).html(),b=a;5==a&&(b="<span class=\"rating rating-5-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated 5 Stars</span><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i></span>"),4==a&&(b="<span class=\"rating rating-4-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated 4 Stars</span><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa far fa-star\"></i></span>"),3==a&&(b="<span class=\"rating rating-3-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated 3 Stars</span><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa far fa-star\"></i><i class=\"fa far fa-star\"></i></span>"),2==a&&(b="<span class=\"rating rating-2-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated 2 Stars</span><i class=\"fa fas fa-star\"></i><i class=\"fa fas fa-star\"></i><i class=\"fa far fa-star\"></i><i class=\"fa far fa-star\"></i><i class=\"fa far fa-star\"></i></span>"),1==a&&(b="<span class=\"rating rating-1-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated 1 Star</span><i class=\"fa fas fa-star\"></i><i class=\"fa far fa-star\"></i><i class=\"fa far fa-star\"></i><i class=\"fa far fa-star\"></i><i class=\"fa far fa-star\"></i></span>"),c(this).html(b)});var A=new Date().getDay();0==A&&c(".office-hours .row-sun").addClass("today"),1==A&&c(".office-hours .row-mon").addClass("today"),2==A&&c(".office-hours .row-tue").addClass("today"),3==A&&c(".office-hours .row-wed").addClass("today"),4==A&&c(".office-hours .row-thu").addClass("today"),5==A&&c(".office-hours .row-fri").addClass("today"),6==A&&c(".office-hours .row-sat").addClass("today"),c("abbr.required, em.required, span.required").text(""),setTimeout(function(){c("abbr.required, em.required, span.required").text("")},2e3),c(window).on("load",function(){screenResize()}),c(window).resize(function(){screenResize()}),window.screenResize=function(){c("body").removeClass("screen-5 screen-4 screen-3 screen-2 screen-1"),1280<getDeviceW()&&c("body").addClass("screen-5"),1280>=getDeviceW()&&1024<getDeviceW()&&c("body").addClass("screen-4"),1024>=getDeviceW()&&860<getDeviceW()&&c("body").addClass("screen-3"),860>=getDeviceW()&&576<getDeviceW()&&c("body").addClass("screen-2"),576>=getDeviceW()&&c("body").addClass("screen-1"),c(".screen-mobile li.menu-item-has-children").children("a").attr("href","javascript:void(0)"),closeMenu(),c("div[class*=\"-faux\"]").each(function(){var a=c(this),b="."+a.attr("class"),d=b.replace("-faux","");if(!c(d).length){var e="#"+a.attr("class");d=e.replace("-faux","")}c(d).is(":visible")?(c(b).height(c(d).outerHeight()),c(".wp-google-badge-faux").height(c(".wp-google-badge").outerHeight())):c(b).height(0)}),moveDiv(".sidebar-shift.screen-mobile #secondary","#colophon","before")},c("#primary").attr("role","main").attr("aria-label","main content"),c("#secondary").attr("role","complementary").attr("aria-label","sidebar"),c("h3 a[aria-hidden=\"true\"]").each(function(){c(this).parent().attr("aria-label",c(this).text())}),c("form.hide-labels input, form.hide-labels textarea").each(function(){c(this).attr("title",c(this).closest("p").find("label").text())}),c("span.required").attr("aria-hidden",!0).after("<span class=\"sr-only\">Required Field</span>"),setTimeout(function(){c("img:not([alt])").attr("alt","")},50),setTimeout(function(){c("img:not([alt])").attr("alt","")},1e3),c("[role=\"menubar\"]").on("focus.aria mouseenter.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).attr("aria-expanded",!0)}),c("[role=\"menubar\"]").on("blur.aria mouseleave.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).attr("aria-expanded",!1)}),c("a[role=\"menuitem\"]").attr("tabindex","0"),c("li[aria-haspopup=\"true\"]").attr("tabindex","-1"),c("form.hide-labels label:not(.show-label)").addClass("sr-only"),c(window).on("load",function(){clearInterval(a),c("#loader").fadeOut("fast"),c("section.section-lock").each(function(){var a=c(this),b=a.attr("data-delay"),d=a.attr("data-pos"),e=a.attr("data-show"),f=a.outerHeight()+100;"always"==e&&(e=1e-6),"never"==e&&(e=1e5),"session"==e&&(e=null),addDiv(a.find(".flex"),"<div class=\"closeBtn\" aria-label=\"close\" aria-hidden=\"false\" tabindex=\"0\"><i class=\"fa fa-times\"></i></div>","before"),a.find(".closeBtn").keyup(function(a){(13===a.keyCode||32===a.keyCode)&&c(this).click()}),"top"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({top:i+"px"}),setTimeout(function(){a.addClass("on-screen"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),setCookie("display-message","no",e)})):"bottom"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({bottom:"0"}),setTimeout(function(){a.addClass("on-screen"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),setCookie("display-message","no",e)})):(a.css({opacity:0}).fadeOut(),"no"!==getCookie("display-message")&&(setTimeout(function(){a.css({opacity:1}).fadeIn()},b),a.focus(),a.find(".closeBtn").click(function(){a.fadeOut(),setCookie("display-message","no",e)})),!1),a.click(function(){setCookie("display-message","no",e)})}),setTimeout(function(){trimText(),buildAccordion()},1e3)})})(jQuery)});