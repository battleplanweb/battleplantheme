document.addEventListener("DOMContentLoaded", function () {	"use strict"; 
														   
// Raw Script: Cue	
														   
	window.addEventListener("load", function() {
		const playlists = getObjects('.cue-playlist-container');

		for (const thisPlaylist of playlists) {
			const container = getObject('.mejs-container', thisPlaylist);
			if (container) {
				const cueH = container.offsetHeight * 2;
				const tracks = getObject('.cue-playlist .cue-tracks', thisPlaylist);
				if (tracks) {
					tracks.style.maxHeight = cueH + "px";
				}
			}

			moveDivs(thisPlaylist, '.mejs-track-details .mejs-track-title', '.mejs-track-details .mejs-track-artist', 'before');
		}
	});	
});