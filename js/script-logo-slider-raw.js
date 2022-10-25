document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

// Set up Logo Slider
	$('.logo-slider').each(function() {
		var logoSlider = $(this), logoRow = logoSlider.find('.logo-row'), direction = logoSlider.attr('data-direction'), speed = logoSlider.attr('data-speed'), delay = (parseInt(logoSlider.attr('data-delay'))) * 1000, time = 0, maxW = getDeviceW() * (parseInt(logoSlider.attr('data-maxw')) / 100), pause = logoSlider.attr('data-pause'), spacing = getDeviceW() * (parseInt(logoSlider.attr('data-spacing')) / 100), easing = "swing", moving = true, firstLogo, secondLogo, largestW = 0, checkW = 0, thisW = 0, firstPos = 0, secondPos = 0, space = 0, containerW = 0, logoW = 0;
		
		logoRow.css({'opacity': 0});
		
		if ( delay == "0" ) { easing = "linear"; } 
		if ( pause == "yes" || pause == "true" ) {
			logoSlider.mouseover(function() { moving = false; });
			logoSlider.mouseout(function() { moving = true; });
		}		
		if ( getDeviceW() < getMobileCutoff() ) { spacing = spacing * 1.5; }

		setTimeout(function() { 
			logoSlider.find('span').find('img').each(function() { 
				$(this).removeClass('unloaded');
				thisW = parseInt($(this).attr('width'));
				if ( thisW > maxW ) {
					thisW = maxW; $(this).width(thisW);
				}
				if ( thisW > largestW ) { 
					largestW = thisW;
				}
				logoW = logoW + spacing + thisW; 
			});
			
			if ( delay == 0 ) {
				logoW = 0;
				logoSlider.find('span').find('img').each(function() { 
					$(this).parent().width(largestW); 
					logoW = logoW + spacing + largestW; 
				});			
			}

			setTimeout(function() { 
				checkW = getDeviceW() + largestW + spacing;
				if ( logoW < checkW ) { logoW = checkW; }
				logoRow.css('width', logoW); 
				
				if ( speed == "slow" ) { speed = logoW * 3; } 
				else if ( speed == "fast" ) { speed = logoW * 1.5; } 
				else { speed = logoW * (parseInt(speed)); }
				
				time = speed + delay + 15;
				
				logoRow.animate({ 'opacity': 1}, 300);

				function moveLogos() {
					if ( moving != false ) {	
						if ( direction == "normal" ) {
							firstLogo = logoRow.find('span:nth-of-type(1)');
							firstPos = firstLogo.position().left + firstLogo.width();
							secondLogo = logoRow.find('span:nth-of-type(2)');
							secondPos = secondLogo.position().left;	
							containerW = firstLogo.width() + secondPos - firstPos; 	

							logoRow.animate({ 'margin-left': -containerW+'px'}, speed, easing, function() {
								firstLogo.remove();
								logoRow.find('span:last').after(firstLogo);
								logoRow.css({ 'margin-left': '0px' });
							});						

						} else {
							firstLogo = logoRow.find('span:nth-last-of-type(1)');
							firstPos = firstLogo.position().left + firstLogo.width();
							secondLogo = logoRow.find('span:nth-last-of-type(2)');
							secondPos = secondLogo.position().left;	
							containerW = firstLogo.width() + secondPos - firstPos; 	
							logoRow.css({ 'margin-left': containerW+'px' });

							logoRow.animate({ 'margin-left': '0px'}, speed, easing, function() {
								firstLogo.remove();
								logoRow.find('span:first').before(firstLogo);
								logoRow.css({ 'margin-left': containerW+'px' });
							});
						}
					} 	 
				}
				moveLogos();
				setInterval( moveLogos, time );
			}, 10);
		}, 500);
	});
	
})(jQuery); });