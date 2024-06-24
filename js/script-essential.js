document.addEventListener("DOMContentLoaded",function(){"use strict";window.bp_page=getObject("#page"),window.mobileMenuBarH=function(){return getDeviceW()>mobileCutoff?0:document.getElementById("mobile-menu-bar").offsetHeight},window.getSlug=function(){const a=window.location.pathname.split("/");return 1<a.length?a[1]:null},window.getUrlVar=function(a){const b=new URLSearchParams(window.location.search);return b.get(a)},window.keyPress=function(a){const b=getObject(a);b&&b.addEventListener("keyup",function(a){"Enter"===a.key&&this.click()})},window.linkHome=function(a){keyPress(a),getObjects(a).forEach(a=>{setAttributes(a,{tabindex:"0",role:"button","aria-label":"Return to Home Page"}),setStyles(a,{cursor:"pointer"}),a.addEventListener("click",()=>window.location="/"),getObjects("img",a).forEach(a=>setAttributes(a,{"aria-hidden":"true"}))})},linkHome(".logo"),window.copyClasses=function(a,b="img, iframe",c=""){getObjects(a).forEach(a=>{getObjects(b,a).forEach(b=>{const c=b.className.split(" ").filter(a=>""!==a.trim());a.classList.add(...c)}),c.trim()&&a.classList.add(c)})},window.replaceText=function(a,b,c,d="text",e=!1){(!0===e||"true"===e)&&(b=new RegExp(b,"gi")),getObjects(a).forEach(a=>{"text"===d?""===b?a.textContent=c:a.textContent=a.textContent.replace(b,c):""===b?a.innerHTML=c:a.innerHTML=a.innerHTML.replace(b,c)})},window.addStroke=function(a,b,c,d,e,f,g="true"){let h="",j=16;e=e||c,d=d||c,f=f||d;for(let k=0;k<j;k++){const a=2*k*Math.PI/j,i=Math.round(1e4*Math.cos(a))/1e4,l=Math.round(1e4*Math.sin(a))/1e4;let m=0>=i&&0>=l?c:0<=i&&0<=l?d:0>=i&&0<=l?e:f;const n=`calc(${b}px * ${i}) calc(${b}px * ${l})`;"true"===g?(h+=`${n} 0 ${m}`,k<15&&(h+=", ")):h+=`drop-shadow(${n} 0 ${m}) `}const k=document.styleSheets[0];document.documentElement.style.setProperty("--js-text-fx-width",b),getObjects(a).forEach(a=>{if("true"===g){const b=`.js-text-fx-shadow { text-shadow: ${h}; }`;k.insertRule(b,k.cssRules.length),a.classList.add("js-text-fx-shadow")}else{const b=`.js-text-fx-filter { filter: ${h}; }`;k.insertRule(b,k.cssRules.length),a.classList.add("js-text-fx-filter")}})};let a=[];window.lockDiv=function(b,c=null,d=0,e=!0,f=0){const g=getObject(b);let h=c?getObject(c):g.nextElementSibling;h=isVisible(h)?h:g.parentNode.nextElementSibling,!0!==e&&g.classList.add("no-doc-flow");const i={elementSel:b,elementObj:g,triggerPos:h.offsetTop-g.offsetHeight-d,lockPos:f};a.push(i)},window.lockAlign=function(){const b=getObjects(".stuck, .fixed-at-load");if(!b.length)return void(bp_page.style.paddingTop="0px");let c=0,d=0;b.forEach((b,e)=>{a[e]&&"undefined"!=typeof a[e].lockPos&&(c+=a[e].lockPos),b.style.top=`${c}px`;const f=b.getBoundingClientRect();c=f.bottom,b.classList.contains("no-doc-flow")||(d+=b.offsetHeight,bp_page.style.paddingTop=d+"px")})},window.fixedAtLoad=function(a){const b=getObject(a);b.style.position="fixed",b.classList.add("fixed-at-load"),lockAlign()},window.addFaux=function(a,b=!0){fixedAtLoad(a)},window.lockMenu=function(){lockDiv("#desktop-navigation")},window.controlLockedDivs=function(){let b=0;a.forEach(a=>{b+=a.lockPos,a.pageY=window.pageYOffset+b,a.pageY>=a.triggerPos?!a.elementObj.classList.contains("stuck")&&(a.elementObj.classList.add("stuck"),a.elementObj.style.top=b+"px",lockAlign()):a.elementObj.classList.contains("stuck")&&(a.elementObj.classList.remove("stuck"),!a.elementObj.classList.contains("fixed-at-load")&&(a.elementObj.style.top="unset"),lockAlign()),b+=a.elementObj.offsetHeight})};const b=debounce(()=>{"function"==typeof lockAlign&&lockAlign(),"function"==typeof toggleScrollTop&&toggleScrollTop()},300);window.addEventListener("scroll",()=>{"function"==typeof controlLockedDivs&&controlLockedDivs(),"function"==typeof b&&b()}),window.lockColophon=function(){const a=getObject("#colophon");let b=a.offsetHeight;if(b<getDeviceH()&&!getObject("body").classList.contains("background-image")){const c=a.previousElementSibling;c.style.marginBottom=b+"px",setStyles(a,{position:"fixed",bottom:getObject(".wp-gr.wp-google-badge").offsetHeight+"px",width:"100%",zIndex:1})}},window.animateScroll=function(a,b=0){let c=a,d=.25;"string"==typeof a&&(c=getObject(a),d=a.startsWith("#")?.15:d),c&&"#tab-description"!==a&&setTimeout(()=>{const a=window.innerHeight*d,e=c.getBoundingClientRect(),f=window.scrollY+e.top-a-b;window.scroll({top:f,behavior:"smooth"})},100)},window.location.hash&&setTimeout(()=>{animateScroll(window.location.hash)},1e3),window.toggleScrollTop=function(){getObjects(".scroll-top").forEach(a=>{window.pageYOffset>=window.innerHeight?a.classList.add("visible"):a.classList.remove("visible")})},window.toggleScrollDown=function(){getObjects(".scroll-down").forEach(a=>{window.pageYOffset>=window.innerHeight?a.classList.remove("visible"):a.classList.add("visible")})},window.getPosition=function(a,b="top",c="window"){const d=getObject(a);if(!d)return;const e=d.getBoundingClientRect(),f=e.width,g=e.height;let h=e.left+window.scrollX,i=e.top+window.scrollY,j=i+g,k=h+f,l=h+f/2,m=i+g/2;if("window"!==c&&"screen"!==c){const a=getObject(c);if(!a)return;const b=a.getBoundingClientRect(),d=b.left+window.scrollX,e=b.top+window.scrollY;h-=d,i-=e,j-=e,k-=d,l-=d,m-=e}switch(b.toLowerCase()){case"left":case"l":return h;case"top":case"t":return i;case"bottom":case"b":return j;case"right":case"r":return k;case"centerx":case"center-x":return l;case"centery":case"center-y":return m;default:return null;}},window.getTranslate=function(a,b="Y"){const c=window.getComputedStyle(getObject(a)),d=c.transform||c.webkitTransform||c.mozTransform;if(!d||"none"===d)return 0;let e=d.match(/matrix3d\((.+)\)/)||d.match(/matrix\((.+)\)/);if(e){if(e=e[1].split(", "),"X"===b.toUpperCase())return parseFloat(16===e.length?e[12]:e[4]);if("Y"===b.toUpperCase())return parseFloat(16===e.length?e[13]:e[5])}return 0},window.filterArchives=function(a="",b=".section.archive-content",c=".col-archive",d=300){getObjects(b).forEach(b=>{b.style.opacity="0",b.style.transition=`opacity ${d}ms ease-in-out`,setTimeout(()=>{getObjects(c).forEach(b=>{b.style.display=""===a||null===a?"":b.classList.contains(a)?"":"none",b.style.clear="none"}),b.style.opacity="1"},d)})},window.revealDiv=function(a,b=0,c=1e3,d="0%"){d=parseInt(d)/100;const e=getObject(a),f=e.nextElementSibling;f.style.transform=`translateY(-${e.offsetHeight}px)`,f.style.transitionDuration="0ms",setTimeout(()=>{const a=new IntersectionObserver(a=>{a.forEach(a=>{a.isIntersecting&&(f.style.transitionDuration=`${c}ms`,f.style.transform="translateY(0)")})},{root:null,rootMargin:"0px",threshold:d});a.observe(e)},b)},window.btnRevealDiv=function(a,b,c=0,d=300){c+=mobileMenuBarH();const e=getObject(b),f=getComputedStyle(e).display;e.style.display="none";const g=getObject(a);g.addEventListener("click",()=>{e.style.display=f,animateScroll(b,c,d)})},getObjects("img").forEach(a=>{if(a.src.includes("hvac-american-standard/american-standard")){const b=document.createElement("a");setAttributes(b,{href:"https://www.americanstandardair.com/",target:"_blank",rel:"noreferrer"}),a.parentNode.insertBefore(b,a),b.appendChild(a)}}),window.document.addEventListener("wpcf7mailsent",function(){location="/email-received/"},!1),getObjects(".main-navigation ul.main-menu li > a").forEach(a=>{a.parentNode.setAttribute("data-content",a.innerHTML)});var c,d=document.getElementById("ak_js"),e=[];d?d.parentNode.removeChild(d):(d=document.createElement("input"),d.type="hidden",d.name=d.id="ak_js"),d.value=new Date().getTime(),(c=document.getElementById("commentform"))&&e.push(c),(c=document.getElementById("replyrow"))&&(c=c.getElementsByTagName("td"))&&e.push(c.item(0));for(var f=0,g=e.length;f<g;f++)e[f].appendChild(d);["parallaxBG","parallaxDiv","magicMenu","splitMenu","addMenuLogo","addMenuIcon","desktopSidebar"].forEach(a=>{"function"!=typeof window[a]&&(window[a]=function(){})}),window.cloneDiv=function(a,b,c="after"){const d=getObject(a),e=getObject(b);if(d&&e){const a=d.cloneNode(!0);switch(c){case"before":e.parentNode.insertBefore(a,e);break;case"top":case"start":case"inside":e.insertBefore(a,e.firstChild);break;case"bottom":case"end":e.appendChild(a);break;case"after":default:e.parentNode.insertBefore(a,e.nextSibling);}}},window.moveDiv=function(a,b,c="after"){const d=getObject(a),e=getObject(b);if(d&&e)switch(c){case"before":e.parentNode.insertBefore(d,e);break;case"top":case"start":case"inside":e.insertBefore(d,e.firstChild);break;case"bottom":case"end":e.appendChild(d);break;case"after":default:e.parentNode.insertBefore(d,e.nextSibling);}},window.moveDivs=function(a,b,c,d="after"){getObjects(a).forEach(()=>{const e=getObjects(b,a),f=getObjects(c,a);e&&f&&f.forEach(()=>{e.forEach(a=>{switch(d){case"before":f.parentNode.insertBefore(a,f);break;case"top":case"start":case"inside":f.insertBefore(a,f.firstChild);break;case"bottom":case"end":f.appendChild(a);case"after":default:f.parentNode.insertBefore(a,f.nextSibling);}})})})},window.addDiv=function(a,b="<div></div>",c="after"){const d=getObjects(a);d.length&&d.forEach(a=>{const d=document.createElement("div");d.innerHTML=b;const e=d.firstChild;switch(c){case"before":a.parentNode.insertBefore(e.cloneNode(!0),a);break;case"top":case"start":a.insertBefore(e.cloneNode(!0),a.firstChild);break;case"bottom":case"end":a.appendChild(e.cloneNode(!0));break;case"after":default:a.nextSibling?a.parentNode.insertBefore(e.cloneNode(!0),a.nextSibling):a.parentNode.appendChild(e.cloneNode(!0));}})},window.wrapDiv=function(a,b="<div></div>",c="outside"){const d=getObjects(a);if(!d.length)return;const e=()=>{const a=document.createElement("div");return a.innerHTML=b.trim(),a.firstElementChild};d.forEach(a=>{const b=e();let d=getObject("*",b);if(d||(d=b),"outside"===c)a.parentNode.insertBefore(b,a),d.appendChild(a);else{for(;a.firstChild;)d.appendChild(a.firstChild);a.appendChild(b)}})},window.wrapDivs=function(a,b="<div></div>"){const c=getObjects(a);if(!c.length)return;const d=document.createElement("template");d.innerHTML=b.trim();const e=d.content.firstChild;c[0].parentNode.insertBefore(e,c[0]),Array.from(c).forEach(a=>{e.appendChild(a)})},window.sizeFrame=function(a,b=".frame",c="0.9"){const d=getObjects(a);d.length&&d.forEach(d=>{if(a.includes("video")){const a=d.parentElement,c=getObject(b,d),e=a.offsetWidth,f=a.offsetHeight;c&&setStyles(c,{width:`${e}px`,height:`${f}px`,marginTop:`-${f}px`})}else{const e=getObjects("img",d),f=getObject(b,d);let g=!1;for(const b of e){if(g)break;if(b.addEventListener("load",()=>{if(!g){const d=b.offsetWidth,e=b.offsetHeight;f&&setStyles(f,{width:`${d}px`,height:`${e}px`,marginBottom:`-${e}px`}),g=!0,addCSS(`${a} img`,`transform: scale(${c})`)}}),b.complete&&b.dispatchEvent(new Event("load")),g)break}}})},window.svgBG=function(a,b,c="top"){const d=getObject(a),e=getObject(b);if(d&&e){const a=d.cloneNode(!0);switch(a.style.position="absolute",c){case"bottom":e.appendChild(a);break;case"before":case"start":e.parentNode.insertBefore(a,e);break;case"after":case"end":e.parentNode.insertBefore(a,e.nextSibling);break;case"top":default:e.insertBefore(a,e.firstChild);}}}});