document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	var sectionP = parseInt($('.screen-desktop #masthead > *:first-child').css('padding-top')) + 20;
	
	$('.screen-desktop #masthead > *:first-child').css({'padding-top':sectionP+'px'});
	
})(jQuery); });