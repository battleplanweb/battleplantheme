document.addEventListener("DOMContentLoaded",function(){"use strict";function a(a,b,c){if(c){const a=c.getAttribute("data-collapse");a&&"hide"!==a?c.innerHTML=a:c.style.display="none"}setStyles(b,{height:b.getAttribute("data-height")+"px",opacity:1}),a.setAttribute("aria-expanded","true"),a.classList.add("active"),animateScroll(b)}function b(a,b,c){if(setStyles(b,{height:"0px",opacity:0}),a.setAttribute("aria-expanded","false"),a.classList.remove("active"),c){const b=c.getAttribute("data-text");b&&(c.innerHTML=b),setTimeout(()=>{animateScroll(a.previousElementSibling)},50)}}window.buildAccordion=function(){window.accordions=getObjects(".block-accordion"),accordions.forEach(a=>{const c=getObject(".accordion-content",a),d=getObjects("img",c),e=a.getBoundingClientRect(),f=()=>{setTimeout(()=>{c.setAttribute("data-height",c.scrollHeight),c.setAttribute("data-top",e.top),a.classList.contains("active")||b(a,c,null)},10)};if(0<d.length){let a=0;d.forEach(b=>{b.complete?a++:b.addEventListener("load",()=>{a++,a===d.length&&f()})}),a===d.length&&f()}else f()})},getObjects(".block-accordion .accordion-button").forEach(c=>{c.addEventListener("click",function(d){d.preventDefault();const e=c.closest(".block-accordion"),f=getObject(".accordion-content",e),g="true"===e.getAttribute("aria-expanded"),h=c.getAttribute("data-collapse");h&&"hide"!==h&&(g?b(e,f,c):getObjects(".block-accordion[aria-expanded=\"true\"]").forEach(a=>{b(a,getObject(".accordion-content",a),c)})),a(e,f,c)}),c.addEventListener("keypress",function(a){"Enter"===a.key&&(a.preventDefault(),c.click())})}),buildAccordion()});