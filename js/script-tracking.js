document.addEventListener("DOMContentLoaded",function(){"use strict";(function(a){var b="https://"+window.location.hostname+"/wp-admin/admin-ajax.php",c=getCookie("unique-id"),d=c+getCookie("pages-viewed"),e=a("body").attr("id");a(window).on("load",function(){a(".widget.widget-financing").addClass("tracking").attr("data-track","financing"),a("div.coupon-inner").addClass("tracking").attr("data-track","coupon"),a(".carousel.slider-testimonials").addClass("tracking").attr("data-track","testimonials");var f=Date.now(),g=(f-startTime)/1e3,h="desktop";getDeviceW()<=getMobileCutoff()&&(h="mobile"),setTimeout(function(){a.post({url:b,data:{action:"run_chron_jobs",admin:"false"},success:function(a){ajax_response(a.dashboard)}}),g+="desktop"==h?.3:.6,a.post({url:b,data:{action:"log_page_load_speed",id:e,loadTime:g,deviceTime:h},success:function(a){ajax_response(a.dashboard)}}),a.post({url:b,data:{action:"track_interaction",key:"content-tracking",track:"visitor",uniqueID:c},success:function(a){ajax_response(a.dashboard)}})},1e3),setTimeout(function(){var f=0,g=0,h=getPosition(a("#wrapper-content"),"top"),i=a("#wrapper-content").height(),j=a(".wp-google-badge").height()||0;a("#primary p, #primary img, #primary div, #wrapper-content").waypoint(function(){f=(Math.round(getPosition(this.element,"bottom"))-Math.round(j)-Math.round(h))/Math.round(i),1<f&&(f=1),setTimeout(function(){f>g&&(a.post({url:b,data:{action:"track_interaction",key:"content-scroll-pct",scroll:f,uniqueID:d},success:function(a){ajax_response(a.dashboard)}}),g=f)},100*f),this.destroy()},{offset:"bottom-in-view"}),a("#wrapper-bottom > section > .flex > .col").waypoint(function(){var c=a(this.element),f=a(this.element).parent().parent(),g=f.find(".flex > .col").index(c)+1,h=a("#wrapper-bottom > section").index(f)+1;a.post({url:b,data:{action:"track_interaction",key:"content-column-views",viewed:h+"."+g,page:e,uniqueID:d},success:function(a){ajax_response(a.dashboard)}}),this.destroy()},{offset:"bottom-in-view"}),a("#primary img.random-img, .widget:not(.hide-widget) img.random-img, #wrapper-bottom img.random-img").waypoint(function(){var c=a(this.element).attr("data-id");a.post({url:b,data:{action:"count_view",id:c},success:function(a){ajax_response(a.dashboard)}}),this.destroy()},{offset:"bottom-in-view"}),a(".carousel.slider-testimonials").waypoint(function(){a(this.element).find(".testimonials-credential.testimonials-name").each(function(){var c=a(this).attr("data-id");a.post({url:b,data:{action:"count_view",id:c},success:function(a){ajax_response(a.dashboard)}})}),this.destroy()},{offset:"bottom-in-view"}),a(".tracking").waypoint(function(){var d=a(this.element).attr("data-track");a.post({url:b,data:{action:"track_interaction",key:"content-tracking",track:d,uniqueID:c},success:function(a){ajax_response(a.dashboard)}}),this.destroy()},{offset:"bottom-in-view"})},300)})})(jQuery)});