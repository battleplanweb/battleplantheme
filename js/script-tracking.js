document.addEventListener("DOMContentLoaded",function(){"use strict";(function(a){var b=a("body").attr("id");a(window).on("load",function(){a(".widget.widget-financing").addClass("tracking").attr("data-track","financing"),a("div.coupon-inner").addClass("tracking").attr("data-track","coupon"),a(".carousel.slider-testimonials").addClass("tracking").attr("data-track","testimonials");var c=Date.now(),d=(c-startTime)/1e3,e="desktop";getDeviceW()<=getTabletCutoff()&&(e="tablet"),getDeviceW()<=getMobileCutoff()&&(e="mobile"),setTimeout(function(){d+="desktop"==e?.3:.6,d=d.toFixed(1),"function"==typeof gtag&&gtag("event","join_group",{group_id:b+"\xBB"+e+"\xAB"+d})},4e3),setTimeout(function(){var c=0,d=!1,e=!1,f=!1,g=!1,h=!1,i=!1,j=getPosition(a("#wrapper-content"),"top"),k=a("#wrapper-content").height(),l=a(".wp-google-badge").height()||0;a("#primary h1, #primary h2, #primary h3, #primary p, #primary li, #primary img, #primary div, #wrapper-content").waypoint(function(){"wrapper-content"==this.element.id?c=1:(c=(Math.round(getPosition(this.element,"bottom"))-Math.round(l)-Math.round(j))/Math.round(k),1<c&&(c=1)),.2>c&&!1==i&&"function"==typeof gtag&&(gtag("event","unlock_achievement",{achievement_id:b+"-init"}),i=!0,!getCookie("track")&&"function"==typeof gtag&&(gtag("event","unlock_achievement",{achievement_id:"track-init"}),setCookie("track",!0))),.2<=c&&!1==h&&"function"==typeof gtag&&(gtag("event","unlock_achievement",{achievement_id:b+"-20"}),h=!0),.4<=c&&!1==g&&"function"==typeof gtag&&(gtag("event","unlock_achievement",{achievement_id:b+"-40"}),g=!0),.6<=c&&!1==f&&"function"==typeof gtag&&(gtag("event","unlock_achievement",{achievement_id:b+"-60"}),f=!0),.8<=c&&!1==e&&"function"==typeof gtag&&(gtag("event","unlock_achievement",{achievement_id:b+"-80"}),e=!0),1==c&&!1==d&&"function"==typeof gtag&&(gtag("event","unlock_achievement",{achievement_id:b+"-100"}),d=!0),this.destroy()},{offset:"bottom-in-view"}),a("#wrapper-bottom > section > .flex > .col").waypoint(function(){var c=a(this.element),d=a(this.element).parent().parent(),e=d.find(".flex > .col").index(c)+1,f=a("#wrapper-bottom > section").index(d)+1;"function"==typeof gtag&&gtag("event","unlock_achievement",{achievement_id:b+"-"+(f+"."+e)}),this.destroy()},{offset:"bottom-in-view"}),a(".tracking").waypoint(function(){var b=a(this.element).attr("data-track");getCookie(b)||"function"!=typeof gtag||(gtag("event","unlock_achievement",{achievement_id:"track-"+b}),setCookie(b,!0)),this.destroy()},{offset:"bottom-in-view"})},300),getCookie("user-city")||setTimeout(function(){a.getJSON("https://ipapi.co/json/",function(a){setCookie("user-city",a.city,""),setCookie("user-region",a.region_code,""),setCookie("user-country",a.country_name,"")})},4e3)})})(jQuery)});