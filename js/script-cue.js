document.addEventListener("DOMContentLoaded",function(){"use strict";window.addEventListener("load",function(){const a=getObjects(".cue-playlist-container");for(const b of a){const a=getObject(".mejs-container",b);if(a){const c=2*a.offsetHeight,d=getObject(".cue-playlist .cue-tracks",b);d&&(d.style.maxHeight=c+"px")}moveDivs(b,".mejs-track-details .mejs-track-title",".mejs-track-details .mejs-track-artist","before")}})});