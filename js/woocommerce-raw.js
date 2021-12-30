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
				
	// Reset pagination buttons to common look	
		replaceText('.theme-battleplantheme.woocommerce-page nav.woocommerce-pagination ul li a.prev', '', '<i class="fa fa-chevron-left"></i>', 'html');
		replaceText('.theme-battleplantheme.woocommerce-page nav.woocommerce-pagination ul li a.next', '', '<i class="fa fa-chevron-right"></i>', 'html');
		 
	// Change product page to CSS grid
		/*$('.theme-battleplantheme.woocommerce-page div.product').addClass('flex').addClass('grid-1-1');*/
		
	// On non-variation products, wrap Quantity input and Add To Cart button 
		$('.woocommerce-variation-add-to-cart').addClass("bp-add-to-cart");
		if ( !$('.bp-add-to-cart').length ) {
			wrapDiv('form.cart div.quantity', '<div class="bp-add-to-cart"></div>', 'outside');
			moveDiv('button.single_add_to_cart_button', '.bp-add-to-cart div.quantity', 'after');
		}		
		
	// Match height of quantity inputs and corresponding buttons
		$('.bp-add-to-cart div.quantity + button').each(function() {
			var theBtn = $(this), theInput = theBtn.prev().find('input'), getBtnH = theBtn.outerHeight();			
			theInput.css({"height":getBtnH+"px", "padding":"0 1em"});
			theBtn.css({"height":getBtnH+"px", "padding":"0 1em"});
		});
		
		$('#coupon_code + button').each(function() {
			var theBtn = $(this), theInput = theBtn.prev(), getBtnH = theBtn.outerHeight();			
			theInput.css({"height":getBtnH+"px", "padding":"0 1em"});
			theBtn.css({"height":getBtnH+"px", "padding":"0 1em"});
		});
				
	// Format the shipping address on cart page				
		var getShipping = $('.woocommerce-shipping-destination').text().replace(/.([^.]*)$/,'\ $1');		
		$('.woocommerce-shipping-destination').text(getShipping);		
		replaceText('.woocommerce-shipping-destination', 'Shipping to ', '', 'text');
		replaceText('.woocommerce-shipping-destination', ',', '<br/>', 'html');
		
	// Removes button styling from Pay, View and Cancel buttons (on Account page)
		$('.woocommerce-orders-table__cell a').removeClass('woocommerce-button').removeClass('button');	
		
	// Enhance ADA compliance
		$('#billing_state, #shipping_state, button#place_order').attr('tabindex', '0').attr('aria-hidden', 'false');		
		
	// Make Account Page menu a flex box menu	
		$('.woocommerce-MyAccount-navigation ul').addClass('row-of-buttons');		
	});
	
	// Remove "no-js" from body classes	
	var c = document.body.className;
	c = c.replace(/woocommerce-no-js/, 'woocommerce-js');
	document.body.className = c;
	
})(jQuery); });