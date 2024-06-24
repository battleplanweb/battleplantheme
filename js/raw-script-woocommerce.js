document.addEventListener("DOMContentLoaded", function () {	"use strict";
	window.addEventListener("load", () => {
		
	// Set up woocommerce pages to match rest of site
		const wooMain = getObject('.woocommerce-js main#main');		
		if (wooMain) {		
			wooMain.outerHTML = wooMain.innerHTML;
			wooMain.id = 'primary';	
		}
		
	// Set up products in CSS grid		
		Array.from(getObjects('.theme-battleplantheme.woocommerce-js ul.products')).forEach(el => {
			const columns = el.className.match(/columns-(\d)/);
			if (columns) {
				el.classList.add(`grid-${columns[1]}e`);
			}
		});
		
		
	// Reset breadcrumbs to common look
		const breadcrumbNav = getObject('nav.woocommerce-breadcrumb');
		if (breadcrumbNav) {
			breadcrumbNav.classList.add('breadcrumbs');
			breadcrumbNav.classList.remove('woocommerce-breadcrumb');
		}
				
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
			if (quantityDiv) {
				const newDiv = document.createElement('div');
				newDiv.className = 'bp-add-to-cart';
				quantityDiv.parentNode.insertBefore(newDiv, quantityDiv);
				newDiv.appendChild(quantityDiv);
				const cartButton = getObject('button.single_add_to_cart_button');
				if (cartButton) {
					newDiv.appendChild(cartButton);
				}
			}
		}
		
		
	// Match height of quantity inputs and corresponding buttons
		getObjects('.bp-add-to-cart div.quantity + button').forEach(button => {
			const input = button.previousElementSibling.querySelector('input');
			const btnHeight = button.offsetHeight;
			input.style.height = `${btnHeight}px`;
			input.style.padding = "0 1em";
			button.style.height = `${btnHeight}px`;
			button.style.padding = "0 1em";
		});
		
		getObjects('#coupon_code + button').forEach(btn => {
			const input = btn.previousElementSibling;
			const btnHeight = btn.offsetHeight;
			input.style.height = `${btnHeight}px`;
			input.style.padding = '0 1em';
			btn.style.height = `${btnHeight}px`;
			btn.style.padding = '0 1em';
		});
				
				
	// Format the shipping address on cart page				
		const shippingElement = getObject('.woocommerce-shipping-destination');
		if (shippingElement) {
			const formattedShipping = shippingElement.textContent.replace(/.([^.]*)$/, ' $1');
			shippingElement.textContent = formattedShipping.replace('Shipping to ', '').replace(',', '<br>');
		}
		
	// Removes button styling from Pay, View and Cancel buttons (on Account page)
		getObjects('.woocommerce-orders-table__cell a').forEach(a => {
			a.classList.remove('woocommerce-button', 'button');
		});
				
		
	// Enhance ADA compliance
		['#billing_state', '#shipping_state', 'button#place_order'].forEach(selector => {
			const selectorObj = getObject(selector);
			if (selectorObj) {
				selectorObj.setAttribute('tabindex', '0');
				selectorObj.setAttribute('aria-hidden', 'false');
			}
		});
				
		
	// Make Account Page menu a flex box menu	
		const myAcct = getObject('.woocommerce-MyAccount-navigation ul');
		if (myAcct) {
			myAcct.classList.add('row-of-buttons');
		}
	});
	
	// Remove "no-js" from body classes	
	let c = document.body.className;
	c = c.replace(/woocommerce-no-js/, 'woocommerce-js');
	document.body.className = c;
	
});