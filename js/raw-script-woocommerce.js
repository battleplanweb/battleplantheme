document.addEventListener("DOMContentLoaded", function () {	"use strict";
	window.addEventListener("load", () => {
		
	// Set up woocommerce pages to match rest of site
		getObject('.woocommerce-js main#main').outerHTML = getObject('.woocommerce-js main#main').innerHTML;
		getObject('.woocommerce-js main#main').id = 'primary';
		
		
	// Set up products in CSS grid		
		Array.from(getObjects('.theme-battleplantheme.woocommerce-js ul.products')).forEach(el => {
			const columns = el.className.match(/columns-(\d)/);
			if (columns) {
				el.classList.add(`grid-${columns[1]}e`);
			}
		});
		
		
	// Reset breadcrumbs to common look
		const breadcrumbNav = getObject('nav.woocommerce-breadcrumb');
		breadcrumbNav.classList.add('breadcrumbs');
		breadcrumbNav.classList.remove('woocommerce-breadcrumb');
		
				
	// Reset pagination buttons to common look	
		replaceText('.theme-battleplantheme.woocommerce-js nav.woocommerce-pagination ul li a.prev', '', '<span class="icon chevron-left"></span>', 'html');
		replaceText('.theme-battleplantheme.woocommerce-js nav.woocommerce-pagination ul li a.next', '', '<span class="icon chevron-right"></span>>', 'html');
		
		const updatePaginationIcons = (selector, iconHtml) => {
			getObjects(selector).forEach(link => {
				link.innerHTML = iconHtml;
			});
		};
		updatePaginationIcons('.theme-battleplantheme.woocommerce-js nav.woocommerce-pagination ul li a.prev', '<span class="icon chevron-left"></span>');
		updatePaginationIcons('.theme-battleplantheme.woocommerce-js nav.woocommerce-pagination ul li a.next', '<span class="icon chevron-right"></span>');

		
	// On non-variation products, wrap Quantity input and Add To Cart button 
		const nonVariationProducts = getObject('.woocommerce-variation-add-to-cart');
		if (!nonVariationProducts) {
			const quantityDiv = getObject('form.cart div.quantity');
			const newDiv = document.createElement('div');
			newDiv.className = 'bp-add-to-cart';
			quantityDiv.parentNode.insertBefore(newDiv, quantityDiv);
			newDiv.appendChild(quantityDiv);
			const cartButton = getObject('button.single_add_to_cart_button');
			newDiv.appendChild(cartButton);
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
		
		getObjects('.bp-add-to-cart div.quantity + button').forEach(button => {
			const input = button.previousElementSibling.querySelector('input');
			const btnHeight = button.offsetHeight;
			input.style.height = `${btnHeight}px`;
			input.style.padding = "0 1em";
			button.style.height = `${btnHeight}px`;
			button.style.padding = "0 1em";
		});
				
				
	// Format the shipping address on cart page				
		const shippingElement = getObject('.woocommerce-shipping-destination');
		const formattedShipping = shippingElement.textContent.replace(/.([^.]*)$/, ' $1');
		shippingElement.textContent = formattedShipping.replace('Shipping to ', '').replace(',', '<br/>');

		
	// Removes button styling from Pay, View and Cancel buttons (on Account page)
		getObjects('.woocommerce-orders-table__cell a').forEach(a => {
			a.classList.remove('woocommerce-button', 'button');
		});
				
		
	// Enhance ADA compliance
		$('#billing_state, #shipping_state, button#place_order').attr('tabindex', '0').attr('aria-hidden', 'false');	
		['#billing_state', '#shipping_state', 'button#place_order'].forEach(selector => {
			const element = getObject(selector);
			element.setAttribute('tabindex', '0');
			element.setAttribute('aria-hidden', 'false');
		});
				
		
	// Make Account Page menu a flex box menu	
		getObject('.woocommerce-MyAccount-navigation ul').classList.add('row-of-buttons');

	});
	
	// Remove "no-js" from body classes	
	let c = document.body.className;
	c = c.replace(/woocommerce-no-js/, 'woocommerce-js');
	document.body.className = c;
	
});