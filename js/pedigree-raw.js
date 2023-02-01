document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {
		replaceText('.single-litters h1, .single-litters h2, .single-litters h3', ' X ', ' x ', 'html');		
		replaceText('.single-litters h1, .single-litters h2, .single-litters h3', ' x ', '<span class="X"> x </span>', 'html');
		
		moveDiv('#wrapper-bracket','#colophon','before');
		
		// add "sex-box" to each dog profile on archive page
		addDiv(".col-dogs.dogs-female .image-dogs a", "<div class='sex-box'><i class='fa fas fa-venus'></i></div>", "inside"); 
		addDiv(".col-dogs.dogs-male .image-dogs a", "<div class='sex-box'><i class='fa fas fa-mars'></i></div>", "inside"); 
		addDiv(".col-dogs.dogs-legacy .image-dogs a", "<div class='sex-box'><i class='fa fas fa-medal'></i></div>", "inside"); 
		
		// setup filtering of dogs & litters archive pages with buttons		
		$("button.female-btn, button.male-btn, button.legacy-btn, button.all-btn, button.available-btn, button.expecting-btn").keyup(function(event) {
			var thisBtn = $(this);
			if (event.keyCode === 13 || event.keyCode === 32) { thisBtn.click(); }
		});	
		
		function chooseCategory(cat) {
			if ( cat == 'all' ) { 
				filterArchives();
			} else {
				filterArchives("dogs-"+cat);
			}
			$('button').removeClass("active");
			$('.p-intro').removeClass("active");
			$('button.'+cat+'-btn').addClass("active");			
			$('.intro-'+cat).addClass("active");
		}
				
		$('button.female-btn').click( function() { chooseCategory('female'); });
		$('button.male-btn').click( function() { chooseCategory('male'); });
		$('button.legacy-btn').click( function() { chooseCategory('legacy'); });
		$('button.all-btn').click( function() { chooseCategory('all'); });
		
		if ( getUrlVar('page') == "males" ) { chooseCategory('male'); }
		else if ( getUrlVar('page') == "legacy" ) { chooseCategory('legacy'); }
		else if ( getUrlVar('page') == "females" ) { chooseCategory('female'); }
		else { chooseCategory('all'); }		

		$('button.available-btn').click( function() { filterArchives("litter-available"); $('button').removeClass("active"); $('button.available-btn').addClass("active"); });
		$('button.expecting-btn').click( function() { filterArchives("litter-expecting"); $('button').removeClass("active"); $('button.expecting-btn').addClass("active"); });		
		if ( getUrlVar('page') == "available" ) { filterArchives("litter-available"); $('button').removeClass("active"); $('button.available-btn').addClass("active"); }
		if ( getUrlVar('page') == "expecting" ) { filterArchives("litter-expecting"); $('button').removeClass("active"); $('button.expecting-btn').addClass("active"); }		
		
		
		/*
		// AJAX - insert Call Name before the "headline" (full name) of each dog pic			
		var addName = function(callname, thisDiv) {	
			var thisLoc = thisDiv.find(".block.text-dogs");		
			addDiv(thisLoc, callname, "before");
		};				
		
		$("#home-page-featured .col, .widget-random-post .col, .archive-dogs .col, .archive-litters .col").each(function() {
			var thisDiv = $(this), filename;			
			filename = thisDiv.find('a').attr('href');	
			$.post ({
				url : 'https://'+window.location.hostname+'/wp-admin/admin-ajax.php',
				data : { action: "get_callname", filename : filename },
				success: function( response ) { addName(response.callname, thisDiv); }
			});		
		});	
		*/
		
		
	});
	
})(jQuery); });