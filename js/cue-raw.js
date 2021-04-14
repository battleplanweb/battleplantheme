document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {				
		$('.cue-playlist-container').each(function() {			
			var thisPlaylist = $(this);	
			
			// move time with time handle
			setInterval(function() {
				var handlePos = thisPlaylist.find('.mejs-time-handle').position(); 
				var relX = handlePos.left - 10;
				thisPlaylist.find('.mejs-currenttime').css({"transform":"translateX("+relX+"px)"});
			}, 333);

			// make tracklist same height as player
			var cueH = thisPlaylist.find('.mejs-container').outerHeight();
			thisPlaylist.find('.cue-playlist .cue-tracks').css({"max-height":cueH+"px"});	

			// swap track details in player		
			moveDivs (thisPlaylist, '.mejs-track-details .mejs-track-title', '.mejs-track-details .mejs-track-artist', 'before');			
		});	
	});
	
})(jQuery); });