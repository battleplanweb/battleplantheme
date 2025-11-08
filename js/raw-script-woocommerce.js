document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Woocommerce
														   
	window.addEventListener("load", () => {
		
	// Move cart button into row with other menu bar icons on mobile
		cloneDiv('a.cart-btn', '#mobile-menu-bar', 'top');	
		
		
	// fix the social icons when WooCommerce is active (unexplained glitch)
		const hasSocialBtns = getObject('.icon-btn.social-btn');

		if ( hasSocialBtns ) {
			const social_box = hasSocialBtns.parentNode.parentNode;

			getObjects('span.icon').forEach(icon => {
				const social = icon.classList[1]; // Assumes 'icon' is always the first class
				const target = `a.icon-btn.social-btn.${social}-btn .icon-bg`;
				moveDiv(`span.icon.${social}`, target, 'before');
			});

			getObjects('p', social_box).forEach(p => p.remove());
		}
		
		
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
				
		
		// Ensure script for variation drop-down is loaded when AJAX completes
		const initVariationsForm = () => {
			getObjects('.variations_form.cart').forEach(form => {
				typeof jQuery !== 'undefined' && jQuery(form).wc_variation_form ? jQuery(form).wc_variation_form() : null;
			});
		};

		initVariationsForm();
		document.addEventListener('ajaxComplete', initVariationsForm);
		
			
		// Change "Quantity" to "Qty" on phones
		getObjects('.woocommerce #page table.shop_table thead th.product-quantity').forEach(th => th.textContent = 'Qty');
			
	});
	
	// Remove "no-js" from body classes	
	let c = document.body.className;
	c = c.replace(/woocommerce-no-js/, 'woocommerce-js');
	document.body.className = c;					   
	
	// Add woocommerce class to all woocommerce pages
	document.body.classList.contains('woocommerce-page') && !document.body.classList.contains('woocommerce') ? document.body.classList.add('woocommerce') : null;

														   
  // Fix spinner on Stripe checkout															   
	(function(){
		const start = () => {
			const body = document.body;

			/* 1) Inject styles (once) */
			const cssId = 'bp-fixed-spinner-style';
			if (!document.getElementById(cssId)) {
				const style = document.createElement('style');
				style.id = cssId;
				style.textContent = [
					'#bp-fixed-spinner{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);',
					'width:28px;height:28px;display:none;border:2px solid rgba(0,0,0,.25);',
					'border-left-color:rgba(0,0,0,.7);border-radius:50%;animation:bpwoospin .8s linear infinite;z-index:100001}',
					'.bp-loading #bp-fixed-spinner{display:block}',
					'@keyframes bpwoospin{to{transform:translate(-50%,-50%) rotate(360deg)}}'
				].join('');
				(document.head || document.documentElement).appendChild(style);
			}

			/* 2) Inject spinner node (once) */
			let spinner = document.getElementById('bp-fixed-spinner');
			if (!spinner) {
				spinner = document.createElement('div');
				spinner.id = 'bp-fixed-spinner';
				spinner.setAttribute('aria-hidden','true');
				body.appendChild(spinner);
			}

			/* Helpers */
			const visible = el => {
				const cs = getComputedStyle(el);
				return cs.display !== 'none' && cs.visibility !== 'hidden' && cs.opacity !== '0' && el.offsetParent !== null;
			};
			const hasVisibleOverlay = () => {
				const overlays = document.querySelectorAll('.blockUI.blockOverlay, .blockUI.blockMsg');
				for (const el of overlays) if (visible(el)) return true;
				return false;
			};
			const formProcessing = () => !!document.querySelector('form.woocommerce-checkout.processing, .woocommerce-checkout form.checkout.processing');

			const show = () => body.classList.add('bp-loading');
			const hide = () => body.classList.remove('bp-loading');

			let rafId = 0;
			const update = () => {
				rafId && cancelAnimationFrame(rafId);
				rafId = requestAnimationFrame(() => (hasVisibleOverlay() || formProcessing()) ? show() : hide());
			};

			/* Initial state */
			update();

			/* Observe DOM changes that could affect visibility/state */
			const obs = new MutationObserver(ms => {
				for (const m of ms) {
					if (m.type === 'childList') { update(); break; }
					if (m.type === 'attributes') { update(); break; }
				}
			});
			obs.observe(document.documentElement, {
				subtree: true,
				childList: true,
				attributes: true,
				attributeFilter: ['class','style']
			});

			/* Bonus: if jQuery is present, listen to AJAX lifecycles for extra reliability */
			if (window.jQuery) {
				const $ = window.jQuery;
				$(document).on('ajaxStart wc_blockui_adding_to_cart checkout_place_order updated_checkout', update);
				$(document).on('ajaxStop', update);
			}

			/* Cleanup */
			window.addEventListener('beforeunload', () => obs.disconnect(), { once: true });
		};

		document.readyState === 'loading'
			? document.addEventListener('DOMContentLoaded', start, { once: true })
			: start();
	})();

});