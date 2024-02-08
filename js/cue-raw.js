document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {				
		$('.cue-playlist-container').each(function() {			
			var thisPlaylist = $(this);	

			// make tracklist same height as player
			var cueH = thisPlaylist.find('.mejs-container').outerHeight() * 2;
			thisPlaylist.find('.cue-playlist .cue-tracks').css({"max-height":cueH+"px"});	

			// swap track details in player		
			moveDivs (thisPlaylist, '.mejs-track-details .mejs-track-title', '.mejs-track-details .mejs-track-artist', 'before');			
		});	
	});
	
})(jQuery); });