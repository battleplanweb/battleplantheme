document.addEventListener("DOMContentLoaded", function () {	"use strict";	
	window.addEventListener("load", () => { 

// Raw Script: Espanol (Spanish Translation)

/*--------------------------------------------------------------
# Espanol (Spanish Translation)
--------------------------------------------------------------*/

	function googleTranslateElementInit() {
		new google.translate.TranslateElement({
			pageLanguage: 'en',  // Set the page language
			layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
			autoDisplay: false, // Prevent automatic display of the translate banner
			includedLanguages: 'es,en' // Limit languages to English and Spanish
		}, 'hablamos-espanol'); // The ID of the element to attach the widget
	}		

	setTimeout(function() {
		googleTranslateElementInit();
	}, 1000);


	function centerEspanol() {
		getObject('iframe.skiptranslate').style.marginLeft = ((getObject('#hablamos-espanol').clientWidth-130)/2)+"px";
	}

	setTimeout(function() { centerEspanol() }, 2000);
	window.addEventListener('resize', () => { centerEspanol(); });	
		
	});	
}); 