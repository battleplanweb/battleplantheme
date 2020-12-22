document.addEventListener("DOMContentLoaded", function () {	"use strict"; (function($) {

	$(window).on("load", function() {
		
	// Set up woocommerce pages to match rest of site
		removeParent('.woocommerce-page main#main');
		$('.woocommerce-page main#main').attr('id', 'primary');		
		
	// Set up products in CSS grid		
		$('.theme-battleplantheme.woocommerce-page ul.products.columns-2').addClass('grid-2e');
		$('.theme-battleplantheme.woocommerce-page ul.products.columns-3').addClass('grid-3e');
		$('.theme-battleplantheme.woocommerce-page ul.products.columns-4').addClass('grid-4e');
		$('.theme-battleplantheme.woocommerce-page ul.products.columns-5').addClass('grid-5e');
		$('.theme-battleplantheme.woocommerce-page ul.products.columns-6').addClass('grid-6e');
		$('.theme-battleplantheme.woocommerce-page ul.products.columns-7').addClass('grid-7e');
		$('.theme-battleplantheme.woocommerce-page ul.products.columns-8').addClass('grid-8e');
		
	// Reset breadcrumbs to common look
		$('nav.woocommerce-breadcrumb').addClass('breadcrumbs').removeClass('woocommerce-breadcrumb');
				
	// Format the shipping address on cart page				
		var getShipping = $('.woocommerce-shipping-destination').text().replace(/.([^.]*)$/,'\ $1');		
		$('.woocommerce-shipping-destination').text(getShipping);		
		replaceText('.woocommerce-shipping-destination', 'Shipping to ', '', 'text');
		replaceText('.woocommerce-shipping-destination', ',', '<br/>', 'html');
				
	// Removes double asterisk in woocommerce required forms
		$('abbr.required, em.required, span.required').text("");
		
	// Removes button styling from Pay, View and Cancel buttons (on Account page)
		$('.woocommerce-orders-table__cell a').removeClass('woocommerce-button').removeClass('button');	
		
	// Enhance ADA compliance
		$('#billing_state, #shipping_state, button#place_order').attr('tabindex', '0').attr('aria-hidden', 'false');
	});
	
})(jQuery); });