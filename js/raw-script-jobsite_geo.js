document.addEventListener("DOMContentLoaded", function () {	"use strict"; 
	window.addEventListener("load", () => {
		
		getObjects('#map img').forEach(img => {
			img.classList.add('noFX');
		});
		
		const map_placement = () => {
			if ( document.body.classList.contains('jobsite_geo') && getDeviceW() < getMobileCutoff() ) {
				const move_this = getObject('.jobsite_geo_map');
				const move_here = getObject('.jobsite_geo_content p:nth-of-type(2)');
				moveDiv (move_this, move_here, "after");
			} else {
				const move_this = getObject('.jobsite_geo_map');
				const move_here = getObject('.jobsite_geo_map_holder');
				moveDiv (move_this, move_here, "inside");
			}
		}
		
		map_placement();
		window.addEventListener('resize', () => { map_placement(); });	
	});	
});