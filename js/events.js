document.addEventListener("DOMContentLoaded",function(){"use strict";(function(a){a(window).on("load",function(){a("#tribe-events-pg-template").attr("id","primary").addClass("site-main"),wrapDiv(".post-type-archive-tribe_events.slug-events .tribe-events-loop","<section class=\"section section-inline archive-content archive-events\"></section>","outside"),a(".post-type-archive-tribe_events.slug-events .tribe-events-loop").addClass("flex").addClass("grid-1"),a(".post-type-archive-tribe_events.slug-events .type-tribe_events").addClass("col").addClass("col-archive").addClass("col-events"),wrapDiv(".post-type-archive-tribe_events.slug-events .col-events","<div class=\"col-inner\"></div>","inside"),a(".post-type-archive-tribe_events.slug-events .tribe-events-list-separator-month").addClass("span-all"),a(".post-type-archive-tribe_events.slug-events .tribe-events-event-image").addClass("block").addClass("block-image").addClass("span-3"),a(".post-type-archive-tribe_events.slug-events .tribe-events-content").addClass("block").addClass("block-group").addClass("span-9"),wrapDiv(".post-type-archive-tribe_events.slug-events .tribe-events-content","<div class=\"block block-text span-12\"></div>","inside"),wrapDiv(".post-type-archive-tribe_events.slug-events .tribe-events-read-more","<div class=\"block block-button span-12 button-events\"></div>","outside"),a(".post-type-archive-tribe_events.slug-events .tribe-events-read-more").addClass("button").addClass("button-events"),a(".single-tribe_events .tribe-events-nav-pagination").addClass("navigation").addClass("single"),a(".single-tribe_events .tribe-events-sub-nav").addClass("nav-links"),a(".single-tribe_events li.tribe-events-nav-previous a").addClass("nav-previous prev"),a(".single-tribe_events li.tribe-events-nav-next a").addClass("nav-next next"),moveDiv(".post-type-archive-tribe_events.slug-events section.archive-events","#tribe-events-content","before"),moveDiv(".post-type-archive-tribe_events.slug-events #tribe-events-footer","section.archive-events","after"),a(".post-type-archive-tribe_events.slug-events #tribe-events-content").remove(),moveDivs(".post-type-archive-tribe_events.slug-events .type-tribe_events",".tribe-events-list-event-title",".block-text > *:first-child","before"),moveDivs(".post-type-archive-tribe_events.slug-events .type-tribe_events",".tribe-events-event-meta",".block-text .tribe-events-list-event-title","after"),moveDivs(".post-type-archive-tribe_events.slug-events .type-tribe_events",".tribe-events-venue-details",".block-text p:last-of-type","after"),moveDivs(".post-type-archive-tribe_events.slug-events .type-tribe_events",".tribe-events-event-cost",".block-text p:last-of-type","after"),a(".ticket-cost").prepend("<span class='cost-label'>Cost: </span>"),a(".tribe-events-venue-details").each(function(){a(this).find("a").length&&a(this).prepend("<span class='venue-label'>Location: </span><br/>")}),moveDivs(".post-type-archive-tribe_events.slug-events .type-tribe_events",".block-button",".tribe-events-content","after"),moveDiv(".tribe-events-notices",".tribe_events","before"),a(".post-type-archive-tribe_events.slug-events .type-tribe_events").each(function(){var b=a(this),c=b.find(".tribe-events-list-event-title").text();replaceText(b.find(".tribe-events-read-more"),"Find out more \xBB","Learn More<span class=\"screen-reader-text\"> about "+c+"</span>","html")}),a(".post-type-archive-tribe_events.slug-events #tribe-events-footer").prepend("<div class=\"block block-button span-12 button-events\"><a class=\"button button-events\" href=\"/events/month/\">View Calendar</a></div>"),a(".post-type-archive-tribe_events.slug-events-month #tribe-events-footer").prepend("<div class=\"block block-button span-12 button-events\"><a class=\"button button-events\" href=\"/events/\">View Event List</a></div>");var b=a("li.tribe-events-nav-previous").text();b=b.slice(0,b.length/2).replace("\xAB",""),a(".single-tribe_events li.tribe-events-nav-previous a").html("<div class=\"post-arrow\"><i class=\"fa fas fa-chevron-left\" aria-hidden=\"true\"></i></div><div class=\"post-links\"><div class=\"meta-nav\" aria-hidden=\"true\">Previous</div><div class=\"post-title\">"+b+"</div></div>");var c=a("li.tribe-events-nav-next").text();c=c.slice(0,c.length/2).replace("\xBB",""),a(".single-tribe_events li.tribe-events-nav-next a").html("<div class=\"post-links\"><div class=\"meta-nav\" aria-hidden=\"true\">Next</div><div class=\"post-title\">"+c+"</div></div><div class=\"post-arrow\"><i class=\"fa fas fa-chevron-right\" aria-hidden=\"true\"></i></div>"),a(".tribe-events-list-event-title a").attr("aria-hidden","true").attr("tabindex","-1")})})(jQuery)});