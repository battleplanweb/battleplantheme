document.addEventListener("DOMContentLoaded",function(){"use strict";(function(a){a(".carousel.slider-testimonials").each(function(){var b=a(this),c=0;setTimeout(function(){b.find(".carousel-item").each(function(){var b=a(this),d=0;b.addClass("calculating"),d=b.outerHeight(!0),b.removeClass("calculating"),d>c&&(c=Math.ceil(d+20))}),b.find(".carousel-inner").css("height",c+"px")},500)})})(jQuery)});