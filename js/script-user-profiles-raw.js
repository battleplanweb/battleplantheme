document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {
	
	$(window).on("load", function() {	

		// Real time profile search
		$("#sort-box").on("change", function() {				
			$.post({
				url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
				data : { action: "update_meta", type: 'user', key: 'profile-sort', value: $(this).val() },
				success: function( ) { window.location.reload(); } 
			});				
		});

		// Real time profile search
		$("#search-box").on("change keyup paste", function() {		
			$('a.link-profiles').each(function() {
				var name = $(this).attr('data-user').toLowerCase(), search = $('#search-box').val().toLowerCase();
				if ( !name.includes(search) ) {
					$(this).addClass('hide-profile');
				} else {
					$(this).removeClass('hide-profile');
				}			
			});	
		});


	});	
})(jQuery); }); 