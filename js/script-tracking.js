document.addEventListener("DOMContentLoaded",function(){"use strict";(function(a){var b="https://"+window.location.hostname+"/wp-admin/admin-ajax.php";a(window).on("load",function(){function c(){h=(a(window).scrollTop()+j-k)/l,1<h&&(h=1),h>i&&(console.log("ajaxing "+h),a.post({url:b,data:{action:"track_interaction",key:"content-scroll-pct",scroll:h,uniqueID:m},success:function(a){console.log(a)}}),i=h)}function d(c){o=a("#wrapper-bottom > section > .flex > .col").index(c)+1,o>p&&(console.log("ajaxing "+o),a.post({url:b,data:{action:"track_interaction",key:"content-column-views",viewed:o,total:n,uniqueID:m},success:function(a){console.log(a)}}));p=o}var e=Date.now(),f=(e-startTime)/1e3,g="desktop";getDeviceW()<=getMobileCutoff()&&(g="mobile"),setTimeout(function(){a.post({url:b,data:{action:"run_chron_jobs",admin:"false"},success:function(a){console.log(a)}}),f+="desktop"==g?.3:.6,a.post({url:b,data:{action:"log_page_load_speed",id:a("body").attr("id"),loadTime:f,deviceTime:g},success:function(a){console.log(a)}})},1e3),a(".carousel img.img-slider, #primary .testimonials-name, .widget:not(.hide-widget) .testimonials-name, #wrapper-bottom .testimonials-name, #primary img.random-img, .widget:not(.hide-widget) img.random-img, #wrapper-bottom img.random-img").waypoint(function(){var c=a(this.element).attr("data-id"),d=a(this.element).attr("data-count-tease");a.post({url:b,data:{action:"count_teaser",id:c},success:function(a){console.log(a)}}),this.destroy()},{offset:"bottom-in-view"});var h=0,i=0,j=screen.height,k=a("#masthead").height()+a("#wrapper-top").height(),l=a("#wrapper-content").height(),m=getCookie("unique-id")+getCookie("pages-viewed");a("#primary p, #primary img").waypoint(function(){c(),this.destroy()},{offset:"bottom-in-view"});console.log("alert 17");var n=a("#wrapper-bottom > section > .flex > .col").length,o=0,p=0;a("#masthead, #wrapper-bottom > section > .flex > .col").waypoint(function(){d(this.element),this.destroy()},{offset:"bottom-in-view"})})})(jQuery)});