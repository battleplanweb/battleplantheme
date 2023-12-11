document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {
		
		
		
		
		
		function codeAddress(address) {
  			geocoder.geocode({ 'address': address }, function (results, status) {
    			if (status == 'OK') {
      				var marker = new google.maps.Marker({
					map: map,
        			position: results[0].geometry.location
      			});
				} else {
      				alert('Geocode was not successful for the following reason: ' + status);
    			}
  			});
		}	
		
		
		
		
		
	});
	
})(jQuery); });