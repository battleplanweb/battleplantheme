document.addEventListener("DOMContentLoaded",function(){"use strict";(function(c){var d,e=site_dir.theme_dir_uri,f=site_dir.upload_dir_uri,h=0,k=.5;window.isApple=function(){return!!navigator.platform&&/iPad|iPhone|iPod/.test(navigator.platform)},window.getSlug=function(){var a=window.location.pathname.split("/");return a[1]},window.getUrlVar=function(a){a=a||"page";var b=new RegExp("[?&]"+a+"=([^&#]*)").exec(window.location.href);return null==b?null:decodeURI(b[1])||0},window.keyPress=function(a){c(a).keyup(function(b){(13===b.keyCode||32===b.keyCode)&&c(a).click()})},keyPress(".logo"),c(".logo").attr("tabindex","0").attr("role","button").attr("aria-label","Return to Home Page").css("cursor","pointer").click(function(){window.location="/"}),c(".logo img").attr("aria-hidden","true"),window.linkHome=function(a){keyPress(a),c(a).attr("tabindex","0").attr("role","button").attr("aria-label","Return to Home Page").css("cursor","pointer").click(function(){window.location="/"}),c(a).find("img").attr("aria-hidden","true")},c("img[src*='hvac-american-standard/american-standard']").each(function(){c(this).wrap("<a href=\"https://www.americanstandardair.com/\" target=\"_blank\" rel=\"noreferrer\"></a>")}),window.trackClicks=function(a,b,d,e){document.location=e,c.post({url:"https://"+window.location.hostname+"/wp-admin/admin-ajax.php",data:{action:"count_link_clicks",type:d},success:function(a){console.log(a)}})},window.setCookie=function(a,b,c){var e=document.domain.match(/[^\.]*\.[^.]*$/)[0],f=new Date,d="";f.setTime(f.getTime()+1e3*(60*(60*(24*c)))),null!=c&&""!=c&&(d="expires="+f.toGMTString()+"; "),document.cookie=a+"="+b+"; "+d+"path=/; domain="+e+"; secure"},window.getCookie=function(a){for(var b,d=a+"=",e=document.cookie.split(";"),f=0;f<e.length;f++)if(b=e[f].trim(),0==b.indexOf(d))return b.substring(d.length,b.length);return""},window.deleteCookie=function(a){setCookie(a,"",-1)},c.fn.hasPartialClass=function(a){return new RegExp(a).test(this.prop("class"))},window.getDeviceW=function(){var a=c(window).width();return isApple()&&(90==window.orientation||-90==window.orientation)&&(a=window.screen.height),a},window.getDeviceH=function(){var a=c(window).height();return isApple()&&(90==window.orientation||-90==window.orientation)&&(a=window.screen.width),a},window.getMobileCutoff=function(){return 1024},window.getTabletCutoff=function(){return 576},window.copyClasses=function(a,b,d){b=b||"img, iframe",c(a).each(function(){c(this).addClass(c(this).find(b).map(function(){return this.className}).get().join(" ")).addClass(d)})},window.shuffleArray=function(a){for(let b,c=a.length-1;0<c;c--)b=Math.floor(Math.random()*(c+1)),[a[c],a[b]]=[a[b],a[c]]},window.replaceText=function(a,b,d,e,f){e=e||"text",f=f||"false","true"==f&&(b=new RegExp(b,"gi")),"text"==e?c(a).text(function(){if(""!=b){var a=c(this).text();return a.replace(b,d)}return d}):c(a).html(function(){if(""!=b){var a=c(this).html();return a.replace(b,d)}return d})},window.addStuck=function(a,b){b=b||"true",c(a).is(":visible")&&(c(a).addClass("stuck"),"true"==b&&addFaux(a.split(" ").pop()))},window.removeStuck=function(a,b){b=b||"true",c(a).removeClass("stuck"),"true"==b&&removeFaux(a.split(" ").pop())},window.addFaux=function(a,b){b=b||"false";var d=c(a);d.is(":visible")&&(c("<div class='"+a.substr(1)+"-faux'></div>").insertBefore(d),c("."+a.substr(1)+"-faux").css({height:d.outerHeight()+"px"}),"true"==b&&d.css({position:"fixed"}))},window.removeFaux=function(a){c("."+a.substr(1)+"-faux").remove()},window.lockDiv=function(a,b,d,e,f,g){b=b||"",d=d||"",e=e||"",f=f||"true",g=g||"both";var i,j;i=""===b?c(a).next().length?c(a).next():c(a).parent().next():c(b),j=""===d?""===e?c(a).outerHeight():c(a).outerHeight()+ +e:+d,c(a).css("top","unset"),i.waypoint(function(b){var d=h;""===e?c(".stuck").each(function(){d+=c(this).outerHeight(!0)}):d+=+e,"down"===b&&("both"===g||"down"===g)?(addStuck(a,f),c(a).css("top",d+"px")):"up"==b&&("both"===g||"up"===g)&&(removeStuck(a,f),c(a).css("top","unset"))},{offset:j+"px"})},window.lockMenu=function(){lockDiv("#desktop-navigation")},window.addStroke=function(a,b,c,d,e,f){var g,h,j,k,l="";e=e||c,d=d||c,f=f||d;for(var m=0;16>m;m++)h=2*m*Math.PI/16,j=Math.round(1e4*Math.cos(h))/1e4,k=Math.round(1e4*Math.sin(h))/1e4,0>=j&&0>=k&&(g=c),0<=j&&0<=k&&(g=d),0>=j&&0<=k&&(g=e),0<=j&&0>=k&&(g=f),l+="calc("+b+"px * "+j+") calc("+b+"px * "+k+") 0 "+g,15>m&&(l+=", ");var n=document.createElement("style");n.innerHTML=a+" { text-shadow: "+l+"; letter-spacing: "+(b-1)+"px; }",document.head.appendChild(n)},window.animateScroll=function(a,b,d){var e=0,f=0;d=d||0,b=b||0,b+=h,c(".stuck").each(function(){e+=c(this).outerHeight()}),f="object"==typeof a||"string"==typeof a?c(a).offset().top-e-b:a-e-b,"#tab-description"!==a&&window.scroll({top:f,left:0,behavior:"smooth"})};var l=c("#wrapper-content").waypoint(function(a){"up"===a?c("a.scroll-top").animate({opacity:0},150,function(){c("a.scroll-top").css({display:"none"})}):c("a.scroll-top").css({display:"block"}).animate({opacity:1},150)},{offset:"10%"}),m=c("#wrapper-content").waypoint(function(a){"down"===a?c(".scroll-down").fadeOut("fast"):c(".scroll-down").fadeIn("fast")},{offset:"99%"});window.getPosition=function(a,b,d){d=d||"window";var e,f,g,h,i,j,k,l,m=c(a);return"window"==d||"screen"==d?(e=m.offset().left,f=m.offset().top):(e=m.position().left,f=m.position().top),g=m.outerWidth(!0),h=m.outerHeight(!0),i=f+h,j=e+g,k=e+g/2,l=f+h/2,"left"==b||"l"==b?e:"top"==b||"t"==b?f:"bottom"==b||"b"==b?i:"right"==b||"r"==b?j:"centerX"==b||"centerx"==b||"center-x"==b?k:"centerY"==b||"centery"==b||"center-y"==b?l:void 0},window.getTranslate=function(a,b){b=b||"Y";var c=document.querySelector(a),d=window.getComputedStyle(c),e=d.transform||d.webkitTransform||d.mozTransform,f=e.match(/matrix.*\((.+)\)/)[1].split(", ");return"x"==b||"X"==b?f[4]:"y"==b||"Y"==b?f[5]:void 0},window.buildAccordion=function(a,b,d,f,g,i){if(!buildAccordion.done){d=d||500,f=f||0,g=g||0,b=b||f+g,a=a||.1,i=i||"close";var j=[];1>a&&(a=getDeviceH()*a),1024>getDeviceW()&&(a+=h),c(".block-accordion").attr("aria-expanded",!1),c(".block-accordion").first().addClass("accordion-first"),c(".block-accordion").last().addClass("accordion-last"),c(".block-accordion").parents(".col-archive").length?(c(".block-accordion").parents(".col-archive").addClass("archive-accordion"),c(".archive-accordion").each(function(){j.push(c(this).offset().top)})):c(".block-accordion").each(function(){j.push(c(this).offset().top)}),c(".block-accordion.start-active").attr("aria-expanded",!0).addClass("active"),c(".block-accordion.start-active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},0),c(".block-accordion.start-active .accordion-content").animate({height:"toggle",opacity:"toggle"},0),keyPress(".block-accordion"),c(".block-accordion .accordion-title").click(function(h){h.preventDefault();var e=c(this),k=e.closest(".block-accordion"),l=j[k.index(".block-accordion")],m=j[0],n=0,o=e.attr("data-collapse");k.hasClass("active")?"close"==i&&(setTimeout(function(){c(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},d),c(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},d),"false"!=o&&e.text(e.attr("data-text"))},f),setTimeout(function(){c(".block-accordion.active").removeClass("active").attr("aria-expanded",!1)},b)):(setTimeout(function(){c(".block-accordion.active .accordion-excerpt").animate({height:"toggle",opacity:"toggle"},d),c(".block-accordion.active .accordion-content").animate({height:"toggle",opacity:"toggle"},d),"hide"==o?e.fadeOut():"false"!=o&&e.text(o)},f),setTimeout(function(){k.find(".accordion-excerpt").animate({height:"toggle",opacity:"toggle"},d),k.find(".accordion-content").animate({height:"toggle",opacity:"toggle"},d),null==o&&(l-m>.25*getDeviceH()?(n=l,animateScroll(n,a,d)):(n=(l-m)/2+m,animateScroll(n,a,d)))},g),setTimeout(function(){c(".block-accordion.active").removeClass("active").attr("aria-expanded",!1),k.addClass("active").attr("aria-expanded",!0)},b))}),buildAccordion.done=!0}},c(".main-navigation ul.main-menu li > a").each(function(){c(this).parent().attr("data-content",c(this).html())}),c(".logo-slider").each(function(){var a,b,d=c(this),e=d.find(".logo-row"),f=d.attr("data-speed"),g=1e3*parseInt(d.attr("data-delay")),h=0,i=getDeviceW()*(parseInt(d.attr("data-maxw"))/100),j=d.attr("data-pause"),k=getDeviceW()*(parseInt(d.attr("data-spacing"))/100),l="swing",m=!0,n=0,o=0,p=0,q=0,r=0,s=0,t=0;e.css({opacity:0}),"0"==g&&(l="linear"),("yes"==j||"true"==j)&&(d.mouseover(function(){m=!1}),d.mouseout(function(){m=!0})),1024>getDeviceW()&&(k*=1.5),setTimeout(function(){d.find("span").find("img").each(function(){p=parseInt(c(this).attr("width")),p>i&&(p=i,c(this).width(p)),p>n&&(n=p),t=t+k+p}),0==g&&(t=0,d.find("span").find("img").each(function(){c(this).parent().width(n),t=t+k+n})),setTimeout(function(){function c(){!1!=m&&(a=e.find("span:nth-of-type(1)"),q=a.position().left+a.width(),b=e.find("span:nth-of-type(2)"),r=b.position().left,s=a.width()+r-q,e.animate({"margin-left":-s+"px"},f,l,function(){a.remove(),e.find("span:last").after(a),e.css({"margin-left":"0px"})}))}o=getDeviceW()+n+k,t<o&&(t=o),e.css("width",t),f="slow"==f?3*t:"fast"==f?1.5*t:t*parseInt(f),h=f+g+15,e.animate({opacity:1},300);setInterval(c,h)},10)},1500)}),window.filterArchives=function(a,b,d,e){a=a||null,b=b||".section.archive-content",d=d||".col-archive",e=e||300,c(b).fadeTo(e,0,function(){""==a||null==a?c(d).show():(c(d).hide(),c(d+"."+a).show()),c(d).css({clear:"none"}),c(b).fadeTo(e,1)})},c(".filter-btn").click(function(){var a=c(this),b="?"+a.attr("data-url")+"=",d=!1;c("input:checkbox[name=choice]:checked").each(function(){d?b=b+","+c(this).val():(b+=c(this).val(),d=!0)}),window.location=b}),keyPress("ul.tabs li"),c("ul.tabs li").click(function(){var a=c(this).attr("data-tab"),b=150;c("ul.tabs li").removeClass("current"),c(this).addClass("current"),c(".tab-content").fadeOut(b).next().removeClass("current"),c("#"+a).delay(b).addClass("current").fadeIn(b)}),window.prepareJSON=function(a){return a=a.replace(/%7B/g,"{").replace(/%7D/g,"}").replace(/%22/g,"\"").replace(/%3A/g,":").replace(/%2C/g,","),c.parseJSON(a)},window.revealDiv=function(a,b,d,e){b=b||0,d=d||1e3,e=e||"100%";var f=c(a),g=f.next(),h=f.outerHeight();g.css({transform:"translateY(-"+h+"px)","transition-duration":0}),setTimeout(function(){f.waypoint(function(){g.css({transform:"translateY(0)","transition-duration":d+"ms"})},{offset:e})},b)},window.btnRevealDiv=function(a,b,d,e){e=e||0,d=d||0,d+=h;var f=c(b).css("display");c(b).css("display","none"),c(a).click(function(){c(b).css("display",f),animateScroll(b,d,e)})},c(".review-form:first").addClass("active"),c(".review-form #gmail-yes").click(function(){window.location.href="/google"}),c(".review-form #facebook-yes").click(function(){window.location.href="/facebook"}),c(".review-form #gmail-no, .review-form #facebook-no").click(function(){c(this).closest(".review-form").removeClass("active"),c(this).closest(".review-form").next().addClass("active")}),c.fn.replaceClass=function(a,b){return this.removeClass(a).addClass(b)},c.fn.random=function(){return this.eq(Math.floor(Math.random()*this.length))},window.cloneDiv=function(a,b,d){d=d||"after","after"==d?c(a).clone().insertAfter(c(b)):"before"==d?c(a).clone().insertBefore(c(b)):"top"==d||"start"?c(b).prepend(c(a).clone()):c(b).append(c(a).clone())},window.moveDiv=function(a,b,d){d=d||"after","after"==d?c(a).insertAfter(c(b)):"before"==d?c(a).insertBefore(c(b)):"top"==d||"start"?c(b).prepend(c(a)):c(b).append(c(a))},window.moveDivs=function(a,b,d,e){e=e||"after",c(a).each(function(){var a=c(this);"after"==e?a.find(c(b)).insertAfter(a.find(d)):"before"==e?a.find(c(b)).insertBefore(a.find(d)):"top"==e||"start"?a.find(d).prepend(a.find(c(b))):a.find(d).append(a.find(c(b)))})},window.addDiv=function(a,b,d){b=b||"<div></div>",d=d||"after","after"==d?c(a).append(c(b)):"before"==d?c(a).prepend(c(b)):c(b).insertBefore(c(a))},window.wrapDiv=function(a,b,d){b=b||"<div></div>",d=d||"outside","outside"==d?c(a).each(function(){c(this).wrap(b)}):c(a).each(function(){c(this).wrapInner(b)})},window.wrapDivs=function(a,b){b=b||"<div />",c(a).wrapAll(b)},window.removeParent=function(a){c(a).unwrap()},window.removeDiv=function(a){c(a).remove()},window.svgBG=function(a,b,d){d=d||"top","bottom"==d?c(a).clone().css({position:"absolute"}).appendTo(c(b)):"top"==d?c(a).clone().css({position:"absolute"}).prependTo(c(b)):"before"==d||"start"==d?c(a).clone().css({position:"absolute"}).insertBefore(c(b)):c(a).clone().css({position:"absolute"}).insertAfter(c(b))},"function"!=typeof parallaxBG&&(window.parallaxBG=window.parallaxDiv=window.magicMenu=window.splitMenu=window.addMenuLogo=window.desktopSidebar=function(){}),window.setupSidebar=function(a,b,d){b=b||"true",d=d||"true",a=a||0,window.labelWidgets=function(){c(".widget:not(.hide-widget)").first().addClass("widget-first"),c(".widget:not(.hide-widget)").last().addClass("widget-last"),c(".widget:not(.hide-widget):odd").addClass("widget-even"),c(".widget:not(.hide-widget):even").addClass("widget-odd")};var e,f,g,h,j=c(".widget:not(.lock-to-top):not(.lock-to-bottom)"),k=j.length,l=j.parent(),m=[],n=c(".widget.lock-to-bottom").detach();for(e=0;e<k;e++)m.push(e);if("true"==d)for(e=0;e<k;e++)f=0|Math.random()*k,g=0|Math.random()*k,h=m[f],m[f]=m[g],m[g]=h;for(j.detach(),e=0;e<k;e++)l.append(j.eq(m[e]));l.append(n),c(".widget-set.set-a:not(:first-child), .widget-set.set-b:not(:first-child), .widget-set.set-c:not(:first-child)").addClass("hide-set").addClass("hide-widget"),c("body").hasClass("screen-mobile")?labelWidgets():desktopSidebar(a,b,d)};var n=1,o=document.getElementById("loader"),p=getComputedStyle(o).getPropertyValue("background-color"),[q,i,g,b]=p.match(/\d+/g).map(Number),a=setInterval(function(){n-=.01,document.getElementById("loader").style.backgroundColor="rgb("+q+","+i+","+g+","+n+")",.3>n&&clearInterval(a)},10);window.animateDiv=function(a,b,e,f,g){e=e||0,f=f||"100%",g=g||1e3,g/=1e3;var h=parseFloat(c(a).css("transition-duration")),i=parseFloat(c(a).css("transition-delay"));3<d&&(e*=k,g*=k,h*=k,i*=k),c(a).addClass("animated").css({"animation-duration":g+"s","transition-duration":h+"s","transition-delay":i+"s"}),c(a+".animated").waypoint(function(){var a=c(this.element);setTimeout(function(){a.addClass(b)},e),this.destroy()},{offset:f})},window.animateDivs=function(a,b,e,f,g,h,i){f=f||0,g=g||100,h=h||"100%",i=i||1e3,i/=1e3;var j=0,l=b,m=a.split(" "),n=m.pop();3<d&&(f*=k,g*=k,i*=k),c(a).addClass("animated"),setTimeout(function(){c(a).parent().find(n+".animated").waypoint(function(){var a=c(this.element),d=a.prevAll(n).length;a.css({"animation-duration":i+"s"}),j=6<d?g:d*g,l===e?(setTimeout(function(){a.addClass(b)},j),l=b):(setTimeout(function(){a.addClass(e)},j),l=e),this.destroy()},{offset:h})},f)},window.animateGrid=function(a,b,e,f,g,h,l,m,n){g=g||0,h=h||100,l=l||"100%",m=m||"false",n=n||1e3,n/=1e3;var o,p,q=a.split(" "),r=q.pop();3<d&&(g*=k,h*=k,n*=k),c(a).addClass("animated"),c(a).parent().each(function(){var a=c(this),d=a.find(r+".animated").length;if(1==d)a.find(r+".animated").data("animation",{effect:b,delay:0});else if(2==d)a.find(r+".animated:nth-last-child(2)").data("animation",{effect:e,delay:0}),a.find(r+".animated:nth-last-child(1)").data("animation",{effect:f,delay:1});else if(3==d)a.find(r+".animated:nth-last-child(3)").data("animation",{effect:e,delay:0}),a.find(r+".animated:nth-last-child(2)").data("animation",{effect:b,delay:1}),a.find(r+".animated:nth-last-child(1)").data("animation",{effect:f,delay:2});else if(4==d)a.find(r+".animated:nth-last-child(4)").data("animation",{effect:e,delay:0}),a.find(r+".animated:nth-last-child(3)").data("animation",{effect:b,delay:1}),a.find(r+".animated:nth-last-child(2)").data("animation",{effect:b,delay:2}),a.find(r+".animated:nth-last-child(1)").data("animation",{effect:f,delay:3});else for(o=0;o<d;o++)p=o+1,a.find(r+".animated:nth-child("+p+")").data("animation",{effect:b,delay:o})}),c(a).parent().find(r+".animated").waypoint(function(){var a=c(this.element),b=h*a.data("animation").delay+g,d=a.data("animation").effect;a.css({"animation-duration":n+"s"}),1024<getDeviceW()||"true"==m?setTimeout(function(){a.addClass(d)},b):a.addClass("fadeInUpSmall"),this.destroy()},{offset:l})},window.animateCSS=function(a,b,d,e){b=b||0,d=d||"100%",e=e||1e3,e/=1e3,c(a).addClass("animate"),c(a+".animate").waypoint(function(){var a=c(this.element);a.css({"transition-duration":e+"s"}),setTimeout(function(){a.removeClass("animate")},b),this.destroy()},{offset:d})},window.animateBtn=function(a,b,d,e){a=a||".menu",b=b||"li:not(.active)",e=e||"both";var f=c(a).find(b);d=d||"go-animated",f.bind("webkitAnimationEnd mozAnimationEnd msAnimationEnd oAnimationEnd animationEnd",function(){c(this).removeClass(d)}),"in"==e?f.mouseenter(function(){c(this).addClass(d)}):"out"==e?f.mouseleave(function(){c(this).addClass(d)}):f.hover(function(){c(this).addClass(d)})},window.animateCharacters=function(a,b,e,f,g,h,j){f=f||0,g=g||100,h=h||"100%",j=j||"false",3<d&&(f*=k,g*=k),c(a).each(function(){if("false"!=j){var d,k=c(this).html().split(" "),l=k.length,m="",n=f,o=b;for(d=0;d<l;d++)m+=d==l-1?"<div class=\"wordSplit animated\">"+k[d]:"<div class=\"wordSplit animated\">"+k[d]+"&nbsp;</div>"}else{var d,k=c(this).html().split(""),l=k.length,m="",n=f,o=b;for(d=0;d<l;d++)" "===k[d]&&(k[d]="&nbsp;"),m+="<div class=\"charSplit animated\">"+k[d]+"</div>"}c(this).html(m),c(a).find(".charSplit.animated, .wordSplit.animated").waypoint(function(){var a=c(this.element);n+=g,o===e?(setTimeout(function(){a.addClass(b)},n),o=b):(setTimeout(function(){a.addClass(e)},n),o=e),this.destroy()},{offset:h})})},removeDiv("p:empty, .archive-intro:empty, div.restricted, div.restricted + ul"),c("#masthead + section").addClass("page-begins"),c("div.noFX").find("img, a").addClass("noFX"),c(".far, .fas, .fab").addClass("fa"),c("img").addClass("unloaded"),c("img").one("load",function(){c(this).removeClass("unloaded")}).each(function(){this.complete&&c(this).trigger("load")}),moveDiv("#user_switching_switch_on","#page","before"),c(".main-navigation ul.main-menu, .widget-navigation ul.menu").attr("role","menu").attr("aria-label","Main Menu"),c(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").attr("role","menu"),c(".main-navigation li, .widget-navigation li").attr("role","none"),c(".main-navigation a[href], .widget-navigation a[href]").attr("role","menuitem");var j=c(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor");j.addClass("active"),j.find(">a").attr("aria-current","page"),c(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li").hover(function(){j.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),j.replaceClass("dormant","active")});var r=c(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");if(r.addClass("active"),r.find(">a").attr("aria-current","page"),c(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li").hover(function(){r.replaceClass("active","dormant"),c(this).addClass("hover")},function(){c(this).removeClass("hover"),r.replaceClass("dormant","active")}),c("<div class=\"wp-google-badge-faux\"></div>").insertAfter(c("#colophon")),c("a[href^=\"#\"]:not(.carousel-control-next):not(.carousel-control-prev)").on("click",function(a){a.preventDefault();var b=this.hash,d=+c(b).attr("data-hash");isNaN(d)&&(d=0),""!=b&&(c("*"+b).length?(animateScroll(b,d),setTimeout(function(){animateScroll(b,d)},100)):window.location.href="/"+b)}),c(".menu-item:not(.no-highlight) a[href^=\"#\"]").is(":visible")){var s=c("nav:visible").find("ul"),t=.35*c(window).outerHeight(),u=s.outerHeight()+t,v=s.find("a[href^=\"#\"]"),w=v.map(function(){var a=c(c(this).attr("href"));if("none"!=c(this).parent().css("display"))return a});c(window).scroll(function(){var a,b,d=c(this).scrollTop()+u,e=w.map(function(){void 0!==c(this).offset()&&c(this).offset().top<d&&(a="#"+c(this)[0].id,clearTimeout(b),b=setTimeout(function(){s.find("li").removeClass("current-menu-item").removeClass("current_page_item").removeClass("active"),s.find("a[href^=\""+a+"\"]").closest("li").addClass("current-menu-item").addClass("current_page_item").addClass("active")},10),closeMenu())})})}c("#mobile-navigation li.menu-item-has-children > a").each(function(){c(this).attr("data-href",c(this).attr("href")).attr("href","javascript:void(0)")}),window.closeMenu=function(){c("#mobile-menu-bar .activate-btn").removeClass("active"),c("body").removeClass("mm-active"),c(".top-push.screen-mobile #page").css({top:"0"}),c(".top-push.screen-mobile .top-strip.stuck").css({top:h+"px"})},window.openMenu=function(){c("#mobile-menu-bar .activate-btn").addClass("active"),c("body").addClass("mm-active");var a=c("#mobile-navigation").outerHeight(),b=a+h;c(".top-push.screen-mobile.mm-active #page").css({top:a+"px"}),c(".top-push.screen-mobile.mm-active .top-strip.stuck").css({top:b+"px"})},c("#mobile-menu-bar .activate-btn").click(function(){c(this).hasClass("active")?closeMenu():openMenu()}),window.closeSubMenu=function(a){c(a).removeClass("active"),c(a).height(0),c(a).prev().attr("href","javascript:void(0)")},window.openSubMenu=function(a,b){c("#mobile-navigation ul.sub-menu").removeClass("active"),c("#mobile-navigation ul.sub-menu").height(0),c("#mobile-navigation li.menu-item-has-children > a").attr("href","javascript:void(0)"),c(a).addClass("active"),c(a).height(b+"px"),setTimeout(function(){c(a).prev().attr("href",c(a).prev().attr("data-href"))},500)},c("#mobile-navigation").addClass("get-sub-heights"),c("#mobile-navigation ul.sub-menu").each(function(){var a=c(this);a.data("getH",a.outerHeight(!0)),closeSubMenu(a),a.parent().click(function(){a.hasClass("active")||openSubMenu(a,a.data("getH"))})}),c("#mobile-navigation").removeClass("get-sub-heights"),c("#mobile-navigation li:not(.menu-item-has-children)").each(function(){c(this).click(function(){closeMenu()})}),c(".carousel.slider-testimonials").each(function(){for(var a=c(this),b=0,d=0,e=parseInt(a.find(".carousel-inner").css("padding-bottom")),f=0;f<a.find(".carousel-item").length;f++)d=a.find(".carousel-item.active").outerHeight()+e,d>b&&(b=Math.ceil(d)),a.click();a.find(".carousel-inner").css("height",b+"px")}),c(".testimonials-rating").each(function(){var a,b,d=c(this).html(),e=["far","far","far","far","far"];for(b=0;b<d;b++)e[b]="fas";for(a="<span class=\"rating rating-"+d+"-star\" aria-hidden=\"true\"><span class=\"sr-only\">Rated "+d+" Stars</span>",b=0;5>b;b++)a+="<i class=\"fa "+e[b]+" fa-star\"></i>";a+="</span>",c(this).html(a)});var x=new Date().getDay();c(".office-hours .row-"+["sun","mon","tue","wed","thu","fri","sat"][x]).addClass("today"),c("abbr.required, em.required, span.required").text(""),setTimeout(function(){c("abbr.required, em.required, span.required").text("")},2e3),c(window).on("load",function(){screenResize()}),c(window).resize(function(){screenResize()}),window.screenResize=function(){var a=getDeviceW();c("body").removeClass("screen-5").removeClass("screen-4").removeClass("screen-3").removeClass("screen-2").removeClass("screen-1").removeClass("screen-mobile").removeClass("screen-desktop"),1280<a?c("body").addClass("screen-5").addClass("screen-desktop"):1280>=a&&1024<a?c("body").addClass("screen-4").addClass("screen-desktop"):1024>=a&&860<a?c("body").addClass("screen-3").addClass("screen-mobile"):860>=a&&576<a?c("body").addClass("screen-2").addClass("screen-mobile"):c("body").addClass("screen-1").addClass("screen-mobile"),h=1024<a?0:c("#mobile-menu-bar").outerHeight(),closeMenu(),moveDiv(".sidebar-shift.screen-mobile #secondary","#colophon","before"),moveDiv(".sidebar-shift.screen-desktop #secondary","#primary","after"),c("div[class*=\"-faux\"]").each(function(){var a=c(this),b="."+a.attr("class"),d=b.replace("-faux","");if(!c(d).length){var e="#"+a.attr("class");d=e.replace("-faux","")}c(d).is(":visible")?(c(b).height(c(d).outerHeight()),c(".wp-google-badge-faux").height(c(".wp-google-badge").outerHeight())):c(b).height(0)})},c("h3 a[aria-hidden=\"true\"]").each(function(){c(this).parent().attr("aria-label",c(this).text())}),c("form.hide-labels input, form.hide-labels textarea").each(function(){c(this).attr("title",c(this).closest("p").find("label").text())}),c("span.required").attr("aria-hidden",!0).after("<span class=\"sr-only\">Required Field</span>"),setTimeout(function(){c("img:not([alt])").attr("alt","")},50),setTimeout(function(){c("img:not([alt])").attr("alt","")},1e3),c("[role=\"menu\"]").on("focus.aria mouseenter.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).addClass("menu-item-expanded").attr("aria-expanded",!0)}),c("[role=\"menu\"]").on("blur.aria mouseleave.aria","[aria-haspopup=\"true\"]",function(a){c(a.currentTarget).removeClass("menu-item-expanded").attr("aria-expanded",!1)}),c("[role=\"menu\"] a").attr("tabindex","0"),c("li[aria-haspopup=\"true\"]").attr("tabindex","-1"),c("form.hide-labels label:not(.show-label)").addClass("sr-only"),c(window).on("load",function(){clearInterval(a),c("#loader").fadeOut("fast"),c("section.section-lock").each(function(){var a=c(this),b=a.attr("data-delay"),d=a.attr("data-pos"),e=a.attr("data-show");"always"==e&&(e=1e-6),"never"==e&&(e=1e5),"session"==e&&(e=null),addDiv(a.find(".flex"),"<div class=\"closeBtn\" aria-label=\"close\" aria-hidden=\"false\" tabindex=\"0\"><i class=\"fa fa-times\"></i></div>","before"),keyPress(a.find(".closeBtn")),"top"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({top:h+"px"}),setTimeout(function(){a.addClass("on-screen"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),setCookie("display-message","no",e)})):"bottom"==d?"no"!==getCookie("display-message")&&(a.delay(b).css({bottom:"0"}),setTimeout(function(){a.addClass("on-screen"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),setCookie("display-message","no",e)})):"header"==d?"no"!==getCookie("display-message")&&(moveDiv(a,"#masthead","after"),moveDiv(a.find(".closeBtn"),".section-lock.position-header .col-inner","top"),a.css({display:"grid"}),a.find(".closeBtn").click(function(){a.css({"max-height":0,"padding-top":0,"padding-bottom":0,"margin-top":0,"margin-bottom":0}),setCookie("display-message","no",e)})):("no"!==getCookie("display-message")&&(setTimeout(function(){a.addClass("on-screen"),a.focus()},b),a.find(".closeBtn").click(function(){a.removeClass("on-screen"),setCookie("display-message","no",e)})),!1),a.click(function(){setCookie("display-message","no",e)})}),setTimeout(function(){buildAccordion()},1e3)})})(jQuery)});