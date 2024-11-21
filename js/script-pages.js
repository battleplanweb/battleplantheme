document.addEventListener("DOMContentLoaded",function(){"use strict";function e(e){e=e.toLowerCase();let t="0",n="0",i="1",a="0",s="1",o="0",r="none",d="none",l="";e.includes("left")&&(t="-100"),e.includes("right")&&(t="100"),e.includes("up")&&(n="100"),e.includes("down")&&(n="-100"),e.includes("fade")&&(s="0"),e.includes("zoom")&&(i="0"),e.includes("drop")&&(i="1.4",s="0"),e.includes("blur")&&(r="blur(10px)",d="blur(0)"),(e.includes("roll")||e.includes("rotate"))&&(a="-120"),(e.includes("roll")||e.includes("rotate"))&&(e.includes("right")||e.includes("reverse"))&&(a="120"),e.includes("spin")&&(a="-1080"),e.includes("spin")&&(e.includes("right")||e.includes("reverse"))&&(a="1080"),e.includes("back")&&(i="0.7",l="80% { transform: scale(0.7) translate(0, 0); }"),e.includes("lightspeed")&&(o="30"),e.includes("jackinthebox")&&(i="0",a=40,l=`50% { transform: scale(0.75) rotate(${-a/2}deg); } 70% { transform: scale(0.4) rotate(${a/4}deg); }`),e.includes("jackinthebox")&&e.includes("reverse")&&(a=-40,l=`50% { transform: scale(0.75) rotate(${-a/2}deg); } 70% { transform: scale(0.4) rotate(${a/4}deg); }`),e.includes("small")&&(t*=.07,n*=.07),e.includes("slight")&&(t*=.02,n*=.02);const c=`scale(${i}) translate(${t}vw, ${n}vh) rotate(${a}deg) skewX(${o}deg)`,u="scale(1) translate(0, 0) rotate(0deg) skewX(0)";let m=c,f=u,p=r,h=d,g=s,b="1";e.includes("out")&&(m=u,f=c,p=d,h=r,g="1",b=s);const v=document.createElement("style");document.head.appendChild(v);const E=e,L=`
			@keyframes ${E} {
				0% {
					transform: ${m};
					opacity: ${g};
					filter: ${p};
				}

				${l}
				100% {
					transform: ${f};
					opacity: ${b};
					filter: ${h};
				}
			}
		`;return v.sheet.insertRule(L,0),E}function t(e,t,n,i,a,s="keyframes"){"keyframes"===s?setStyles(e,{animationDuration:`${t}s`,animationDelay:`${n}s`,animationTimingFunction:convertBezier(i),animationFillMode:`${a}`}):setStyles(e,{transitionDuration:`${t}s`,transitionDelay:`${n}s`,transitionTimingFunction:convertBezier(i)})}function n(e,t){e.classList.remove("animation-queued"),e.classList.add("animation-delayed");const n=()=>{e.classList.remove("animation-delayed"),e.classList.add("animation-in-progress"),e.removeEventListener("animationstart",n)},i=()=>{e.classList.remove("animation-in-progress"),e.classList.add("animation-complete"),e.removeEventListener("animationend",i)};e.addEventListener("animationstart",n),e.addEventListener("animationend",i),setStyles(e,{animationName:t})}function a(e,t,i){const a=new IntersectionObserver(e=>{e.forEach(e=>{e.isIntersecting&&(!1===i?(e.target.classList.remove("animation-queued"),e.target.classList.add("animate"),e.target.classList.add("animation-complete")):n(e.target,i),a.unobserve(e.target))})},{rootMargin:`0px 0px -${convertOffset(t)}px 0px`});a.observe(e)}function s(e,t,n){e.classList.remove("on-screen"),document.body.classList.remove("locked"),setCookie("display-"+t,"no",n),"header"===e.dataset.pos&&(e.style.cssText="max-height:0; padding-top:0; padding-bottom:0; margin-top:0; margin-bottom:0;")}function o(e){var t=document.createElement("iframe");setAttributes(t,{src:e.dataset.link,modestbranding:"0",controls:"0",frameborder:"0",allowfullscreen:"1",allow:"accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture",class:"video-player"}),e.parentNode.replaceChild(t,e)}window.bp_page=getObject("#page"),window.mobileMenuBarH=function(){return getDeviceW()>mobileCutoff?0:document.getElementById("mobile-menu-bar").offsetHeight},window.getSlug=function(){const e=window.location.pathname.split("/");return 1<e.length?e[1]:null},window.getUrlVar=function(e){const t=new URLSearchParams(window.location.search);return t.get(e)},window.keyPress=function(e){const t=getObject(e);t&&t.addEventListener("keyup",function(e){"Enter"===e.key&&this.click()})},window.linkHome=function(e){keyPress(e),getObjects(e).forEach(e=>{setAttributes(e,{tabindex:"0",role:"button","aria-label":"Return to Home Page"}),setStyles(e,{cursor:"pointer"}),e.addEventListener("click",()=>window.location="/"),getObjects("img",e).forEach(e=>setAttributes(e,{"aria-hidden":"true"}))})},linkHome(".logo"),window.copyClasses=function(e,t="img, iframe",n=""){getObjects(e).forEach(e=>{getObjects(t,e).forEach(t=>{const n=t.className.split(" ").filter(e=>""!==e.trim());e.classList.add(...n)}),n.trim()&&e.classList.add(n)})},window.replaceText=function(e,t,n,i="text",a=!1){(!0===a||"true"===a)&&(t=new RegExp(t,"gi")),getObjects(e).forEach(e=>{"text"===i?""===t?e.textContent=n:e.textContent=e.textContent.replace(t,n):""===t?e.innerHTML=n:e.innerHTML=e.innerHTML.replace(t,n)})},window.addStroke=function(e,t,n,a,s,o,r="true"){let d="",l=16;s=s||n,a=a||n,o=o||a;for(let c=0;c<l;c++){const e=2*c*Math.PI/l,i=Math.round(1e4*Math.cos(e))/1e4,u=Math.round(1e4*Math.sin(e))/1e4;let m=0>=i&&0>=u?n:0<=i&&0<=u?a:0>=i&&0<=u?s:o;const f=`calc(${t}px * ${i}) calc(${t}px * ${u})`;"true"===r?(d+=`${f} 0 ${m}`,15>c&&(d+=", ")):d+=`drop-shadow(${f} 0 ${m}) `}const c=document.styleSheets[0];document.documentElement.style.setProperty("--js-text-fx-width",t),getObjects(e).forEach(e=>{if("true"===r){const t=`.js-text-fx-shadow { text-shadow: ${d}; }`;c.insertRule(t,c.cssRules.length),e.classList.add("js-text-fx-shadow")}else{const t=`.js-text-fx-filter { filter: ${d}; }`;c.insertRule(t,c.cssRules.length),e.classList.add("js-text-fx-filter")}})};let r=[];const d=["#page > #desktop-navigation","#masthead > #desktop-navigation",".top-strip","#masthead"].map(e=>getObject(e)).find(e=>e&&"fixed"===getComputedStyle(e).position);if(d){const e=d.matches("#page > #desktop-navigation")?getObject("#masthead"):d.nextElementSibling;e&&(e.style.marginTop=`${d.offsetHeight}px`)}window.lockDiv=function(e,t=null,n=0,i=!0,a=0){const s=getObject(e);let o=t?getObject(t):s.nextElementSibling;o=isVisible(o)?o:s.parentNode.nextElementSibling,!0!==i&&s.classList.add("no-doc-flow");const d={elementSel:e,elementObj:s,triggerPos:o.offsetTop-s.offsetHeight-n,lockPos:a};r.push(d)},window.lockAlign=function(){const e=getObjects(".stuck");if(!e.length)return void(bp_page.style.paddingTop="0px");let t=mobileMenuBarH(),n=0;e.forEach((e,i)=>{r[i]&&"undefined"!=typeof r[i].lockPos&&(t+=r[i].lockPos),e.style.top=`${t}px`;const a=e.getBoundingClientRect();t=a.bottom,e.classList.contains("no-doc-flow")||(n+=e.offsetHeight)}),bp_page.style.paddingTop=n+"px"},window.lockMenu=function(){lockDiv("#desktop-navigation")},window.controlLockedDivs=function(){let e=0;r.forEach(t=>{e+=t.lockPos,t.pageY=window.pageYOffset+e,t.pageY>=t.triggerPos?!t.elementObj.classList.contains("stuck")&&(t.elementObj.classList.add("stuck"),t.elementObj.style.top=e+"px",lockAlign()):t.elementObj.classList.contains("stuck")&&(t.elementObj.classList.remove("stuck"),!t.elementObj.classList.contains("fixed-at-load")&&(t.elementObj.style.top="unset"),lockAlign()),e+=t.elementObj.offsetHeight})};const l=debounce(()=>{"function"==typeof lockAlign&&lockAlign(),"function"==typeof toggleScrollTop&&toggleScrollTop()},300);window.addEventListener("scroll",()=>{"function"==typeof controlLockedDivs&&controlLockedDivs(),"function"==typeof l&&l()}),window.lockColophon=function(){const e=getObject("#colophon");let t=e.offsetHeight;if(t<getDeviceH()&&!getObject("body").classList.contains("background-image")){const n=e.previousElementSibling;n.style.marginBottom=t+"px",setStyles(e,{position:"fixed",bottom:getObject(".wp-gr.wp-google-badge").offsetHeight+"px",width:"100%",zIndex:1})}},window.animateScroll=function(e,t=0){let n=e,i=.25;"string"==typeof e&&(n=getObject(e),i=e.startsWith("#")?.15:i),n&&"#tab-description"!==e&&setTimeout(()=>{const e=window.innerHeight*i,a=n.getBoundingClientRect(),s=window.scrollY+a.top-e-t;window.scroll({top:s,behavior:"smooth"})},100)},window.location.hash&&setTimeout(()=>{animateScroll(window.location.hash)},1e3),window.toggleScrollTop=function(){getObjects(".scroll-top").forEach(e=>{window.pageYOffset>=window.innerHeight?e.classList.add("visible"):e.classList.remove("visible")})},window.toggleScrollDown=function(){getObjects(".scroll-down").forEach(e=>{window.pageYOffset>=window.innerHeight?e.classList.remove("visible"):e.classList.add("visible")})},window.getPosition=function(e,t="top",n="window"){const i=getObject(e);if(!i)return;const a=i.getBoundingClientRect(),s=a.width,o=a.height;let r=a.left+window.scrollX,d=a.top+window.scrollY,l=d+o,c=r+s,u=r+s/2,m=d+o/2;if("window"!==n&&"screen"!==n){const e=getObject(n);if(!e)return;const t=e.getBoundingClientRect(),i=t.left+window.scrollX,a=t.top+window.scrollY;r-=i,d-=a,l-=a,c-=i,u-=i,m-=a}switch(t.toLowerCase()){case"left":case"l":return r;case"top":case"t":return d;case"bottom":case"b":return l;case"right":case"r":return c;case"centerx":case"center-x":return u;case"centery":case"center-y":return m;default:return null;}},window.getTranslate=function(e,t="Y"){const n=window.getComputedStyle(getObject(e)),i=n.transform||n.webkitTransform||n.mozTransform;if(!i||"none"===i)return 0;let a=i.match(/matrix3d\((.+)\)/)||i.match(/matrix\((.+)\)/);if(a){if(a=a[1].split(", "),"X"===t.toUpperCase())return parseFloat(16===a.length?a[12]:a[4]);if("Y"===t.toUpperCase())return parseFloat(16===a.length?a[13]:a[5])}return 0},window.filterArchives=function(e="",t=".section.archive-content",n=".col-archive",i=300){getObjects(t).forEach(t=>{t.style.opacity="0",t.style.transition=`opacity ${i}ms ease-in-out`,setTimeout(()=>{getObjects(n).forEach(t=>{t.style.display=""===e||null===e?"":t.classList.contains(e)?"":"none",t.style.clear="none"}),t.style.opacity="1"},i)})},window.revealDiv=function(e,t=0,n=1e3,i="0%"){i=parseInt(i)/100;const a=getObject(e);if(!a)return;const s=a.nextElementSibling;s.style.transform=`translateY(-${a.offsetHeight}px)`,s.style.transitionDuration="0ms",setTimeout(()=>{const e=new IntersectionObserver(e=>{e.forEach(e=>{e.isIntersecting&&(s.style.transitionDuration=`${n}ms`,s.style.transform="translateY(0)")})},{root:null,rootMargin:"0px",threshold:i});e.observe(a)},t)},window.btnRevealDiv=function(e,t,n=0,i=300){const a=getObject(t),s=getComputedStyle(a).display;a.style.display="none";const o=getObject(e);o.addEventListener("click",()=>{a.style.display=s,animateScroll(t,n,i)})},getObjects("img").forEach(e=>{if(e.src.includes("hvac-american-standard/american-standard")){const t=document.createElement("a");setAttributes(t,{href:"https://www.americanstandardair.com/",target:"_blank",rel:"noreferrer"}),e.parentNode.insertBefore(t,e),t.appendChild(e)}}),window.document.addEventListener("wpcf7mailsent",function(){location="/email-received/"},!1),getObjects(".main-navigation ul.main-menu li > a").forEach(e=>{e.parentNode.setAttribute("data-content",e.innerHTML)});var c,u=document.getElementById("ak_js"),m=[];u?u.parentNode.removeChild(u):(u=document.createElement("input"),u.type="hidden",u.name=u.id="ak_js"),u.value=new Date().getTime(),(c=document.getElementById("commentform"))&&m.push(c),(c=document.getElementById("replyrow"))&&(c=c.getElementsByTagName("td"))&&m.push(c.item(0));for(var f=0,p=m.length;f<p;f++)m[f].appendChild(u);["parallaxBG","parallaxDiv","magicMenu","splitMenu","addMenuLogo","addMenuIcon","desktopSidebar"].forEach(e=>{"function"!=typeof window[e]&&(window[e]=function(){})}),window.cloneDiv=function(e,t,n="after"){const i=getObject(e),a=getObject(t);if(i&&a){const e=i.cloneNode(!0);switch(n){case"before":a.parentNode.insertBefore(e,a);break;case"top":case"start":case"inside":a.insertBefore(e,a.firstChild);break;case"bottom":case"end":a.appendChild(e);break;case"after":default:a.parentNode.insertBefore(e,a.nextSibling);}}},window.cloneDivs=function(e,t,n,i="after"){getObjects(e).forEach(e=>{const a=getObject(t,e),s=getObjects(n,e);a&&s.length&&s.forEach(e=>{const t=a.cloneNode(!0);switch(i){case"before":e.parentNode.insertBefore(t,e);break;case"top":case"start":case"inside":e.insertBefore(t,e.firstChild);break;case"bottom":case"end":e.appendChild(t);break;case"after":default:e.parentNode.insertBefore(t,e.nextSibling);}})})},window.moveDiv=function(e,t,n="after"){const i=getObject(e),a=getObject(t);if(i&&a)switch(n){case"before":a.parentNode.insertBefore(i,a);break;case"top":case"start":case"inside":a.insertBefore(i,a.firstChild);break;case"bottom":case"end":a.appendChild(i);break;case"after":default:a.parentNode.insertBefore(i,a.nextSibling);}},window.moveDivs=function(e,t,n,i="after"){getObjects(e).forEach(()=>{const a=getObjects(t,e),s=getObjects(n,e);a&&s&&s.forEach(()=>{a.forEach(e=>{switch(i){case"before":s.parentNode.insertBefore(e,s);break;case"top":case"start":case"inside":s.insertBefore(e,s.firstChild);break;case"bottom":case"end":s.appendChild(e);case"after":default:s.parentNode.insertBefore(e,s.nextSibling);}})})})},window.addDiv=function(e,t="<div></div>",n="after"){const i=getObjects(e);i.length&&i.forEach(e=>{const i=document.createElement("div");i.innerHTML=t;const a=i.firstChild;switch(n){case"before":e.parentNode.insertBefore(a,e);break;case"top":case"start":e.insertBefore(a,e.firstChild);break;case"bottom":case"end":e.appendChild(a);break;case"after":default:e.nextSibling?e.parentNode.insertBefore(a,e.nextSibling):e.parentNode.appendChild(a);}})},window.wrapDiv=function(e,t="<div></div>",n="outside"){const i=getObjects(e);if(!i.length)return;const a=()=>{const e=document.createElement("div");return e.innerHTML=t.trim(),e.firstElementChild};i.forEach(e=>{const t=a();let i=getObject("*",t);if(i||(i=t),"outside"===n)e.parentNode.insertBefore(t,e),i.appendChild(e);else{for(;e.firstChild;)i.appendChild(e.firstChild);e.appendChild(t)}})},window.wrapDivs=function(e,t="<div></div>"){const n=getObjects(e);if(!n.length)return;const i=document.createElement("template");i.innerHTML=t.trim();const a=i.content.firstChild;n[0].parentNode.insertBefore(a,n[0]),Array.from(n).forEach(e=>{a.appendChild(e)})},window.sizeFrame=function(e,t=".frame",n="0.9"){const i=getObjects(e);i.length&&i.forEach(i=>{if(e.includes("video")){const e=i.parentElement,n=getObject(t,i),a=e.offsetWidth,s=e.offsetHeight;n&&setStyles(n,{width:`${a}px`,height:`${s}px`,marginTop:`-${s}px`})}else{const a=getObjects("img",i),s=getObject(t,i);let o=!1;for(const t of a){if(o)break;if(t.addEventListener("load",()=>{if(!o){const i=t.offsetWidth,a=t.offsetHeight;0<i&&0<a&&(s&&setStyles(s,{width:`${i}px`,height:`${a}px`,marginBottom:`-${a}px`}),o=!0,addCSS(`${e} img`,`transform: scale(${n})`))}}),t.complete&&t.dispatchEvent(new Event("load")),o)break}}})},window.svgBG=function(e,t,n="top"){const i=getObject(e),a=getObject(t);if(i&&a){const e=i.cloneNode(!0);switch(e.style.position="absolute",n){case"bottom":a.appendChild(e);break;case"before":case"start":a.parentNode.insertBefore(e,a);break;case"after":case"end":a.parentNode.insertBefore(e,a.nextSibling);break;case"top":default:a.insertBefore(e,a.firstChild);}}},window.setupSidebar=function(e=0,t=!0){window.mobileWidgets=function(){},window.shuffleElements=function(e){let t=Array.from(e);for(let n=t.length-1;0<n;n--){const e=Math.floor(Math.random()*(n+1));[t[n],t[e]]=[t[e],t[n]]}return t};const n=getObjects(".widget:not(.lock-to-top):not(.lock-to-bottom):not(.widget-financing):not(.widget-event)"),i=n[0]?n[0].parentNode:null,a=getObjects(".widget.lock-to-bottom"),s=getObjects(".widget.widget-financing"),o=getObjects(".widget.widget-event");n.forEach(e=>e.remove()),a.forEach(e=>e.remove()),s.forEach(e=>e.remove()),o.forEach(e=>e.remove()),shuffleElements(o).forEach(e=>i.appendChild(e)),shuffleElements(s).forEach(e=>i.appendChild(e)),shuffleElements(n).forEach(e=>i.appendChild(e)),a.forEach(e=>i.appendChild(e)),["set-a","set-b","set-c"].forEach(e=>{let t=0;const n=getObjects(`.widget.widget-set.${e}`)||[];n.forEach(e=>{0<t&&e.remove(),t++})}),document.body.classList.contains("screen-mobile")?mobileWidgets():desktopSidebar(e,t)},window.convertBezier=function(e){return{easeInCubic:"cubic-bezier(0.550, 0.055, 0.675, 0.190)",easeInQuart:"cubic-bezier(0.895, 0.030, 0.685, 0.220)",easeInQuint:"cubic-bezier(0.755, 0.050, 0.855, 0.060)",easeInExpo:"cubic-bezier(0.950, 0.050, 0.795, 0.035)",easeInBack:"cubic-bezier(0.600, -0.280, 0.735, 0.045)",easeOutCubic:"cubic-bezier(0.215, 0.610, 0.355, 1.000)",easeOutQuart:"cubic-bezier(0.165, 0.840, 0.440, 1.000)",easeOutQuint:"cubic-bezier(0.230, 1.000, 0.320, 1.000)",easeOutExpo:"cubic-bezier(0.190, 1.000, 0.220, 1.000)",easeOutBack:"cubic-bezier(0.175, 0.885, 0.320, 1.275)",easeInOutCubic:"cubic-bezier(0.645, 0.045, 0.355, 1.000)",easeInOutQuart:"cubic-bezier(0.770, 0.000, 0.175, 1.000)",easeInOutQuint:"cubic-bezier(0.860, 0.000, 0.070, 1.000)",easeInOutExpo:"cubic-bezier(1.000, 0.000, 0.000, 1.000)",easeInOutBack:"cubic-bezier(0.680, -0.550, 0.265, 1.550)"}[e]||e},window.animateOverflow=function(e,t=2e3){const n=getObject(e);n&&(n.style.overflow="hidden",setTimeout(()=>{n.style.overflow="visible"},t))},window.convertOffset=function(e){return e.includes("%")?getDeviceH()*(1-parseInt(e.replace("%",""),10)/100):e},window.animateDiv=function(n,i,s=0,o="100%",r=1e3,d="ease"){const l=getObjects(n);l.length&&l.forEach(n=>{n.classList.add("animation-queued");const l=e(i);t(n,r/1e3,s/1e3,d,"both"),a(n,o,l)})},window.animateDivs=function(n,i,s,o=0,r=100,d="100%",l=1e3,c="ease"){const u=getObjects(n);if(!u.length)return;const m=new Map;u.forEach(e=>{const t=e.parentElement;!t||m.has(t)||m.set(t,e)}),m.forEach((u,m)=>{let f=Array.from(getObjects(n,m));if(!f.length)return;let p,h=0;f.forEach((n,u)=>{n.classList.add("animation-queued"),setTimeout(()=>{const o=0==u%2?i:s,f=e(o),g=getPosition(n,"top",m);h=50<g-p?h:h+=r,p=g,t(n,l/1e3,h/1e3,c,"both"),a(n,d,f)},o)})})},window.animateGrid=function(n,i,s,o,r=0,d=100,l="100%",c="false",u=1e3,m="ease"){const f=getObjects(`${n}:first-child`);f.length&&f.forEach(n=>{n.classList.add("animation-queued");const c=n.parentElement;if(!c)return;const f=Array.from(c.children),p=f.filter(e=>e.classList.contains("span-all")),h=f.filter(e=>!e.classList.contains("span-all")),g={1:[i],2:[s,o],3:[s,i,o],4:[s,i,i,o]};let b,v=0;setTimeout(()=>{p.forEach(n=>{const s=e(i);t(n,u/1e3,d/1e3,m,"both"),a(n,l,s)});const n=h.length;h.forEach((s,o)=>{let r=4<n?i:g[n][o];const f=e(r),p=getPosition(s,"top",c);v=50<p-b?v:v+=d,b=p,t(s,u/1e3,v/1e3,m,"both"),a(s,l,f)})},r)})},window.animateCSS=function(e,n=0,i="100%",s=1e3,o="ease"){const r=getObjects(e);r.length&&r.forEach(e=>{e.classList.add("animation-queued"),t(e,s/1e3,n/1e3,o,"both","transition"),a(e,i,!1)})},window.animateBtn=function(e=".menu",t="li:not(.active)",n="go-animated",i="both"){if(!n)return;const a=getObjects(`${e} ${t}`),s=e=>{e.classList.add(n),e.addEventListener("animationend",function(){e.classList.remove(n)},{once:!0})};a.forEach(e=>{"in"===i?e.addEventListener("mouseenter",()=>s(e)):"out"===i?e.addEventListener("mouseleave",()=>s(e)):(e.addEventListener("mouseenter",()=>s(e)),e.addEventListener("mouseleave",()=>s(e)))})},window.animateCharacters=function(n,i,s,o=0,r=100,d="100%",l="false"){let c=0;const u=getObjects(n);u.forEach(n=>{if(n.innerHTML.includes("<"))return;let u="true"===l?n.textContent.split(" "):n.textContent.split(""),m=u.map(e=>"true"===l?`<span class="wordSplit animate">${e}</span>`:`<span class="charSplit animate">${" "===e?"&nbsp;":e}</span>`).join("true"===l?"&nbsp;":"");n.innerHTML=m;const f=getObjects(".animate",n);setTimeout(()=>{f.forEach((n,o)=>{c+=r;const l=0==o%2?i:s,u=e(l);t(n,1,c/1e3,"easeOutQuart","both"),a(n,d,u)})},o)})},getObjects("p:empty, .archive-intro:empty, div.restricted, div.restricted + ul, li.menu-item + ul.sub-menu").forEach(e=>{e.getAttribute("role")&&"status"===e.getAttribute("role")||e.remove()}),getObjects("ul#footer-menu a").forEach(e=>{e.getAttribute("href")===window.location.href&&(e.parentElement.style.display="none")});const h=getObject("#masthead + section");h&&h.classList.add("page-begins"),getObjects("div.noFX").forEach(e=>{getObjects("img, a",e).forEach(e=>{e.classList.add("noFX")})}),getObjects(".widget img").forEach(e=>{e.classList.add("img-widget")}),getCookie("first-page")?document.body.classList.add("not-first-page"):(document.body.classList.add("first-page"),setCookie("first-page","set"));let g=getCookie("pages-viewed");if(g?(g++,setCookie("pages-viewed",g)):setCookie("pages-viewed",1),document.body.classList.contains("alt-home")){const e=location.pathname+location.search,t=e.replace(/\//g,"");setCookie("home-url",t)}const b=getCookie("home-url");if(b){const e=getObjects(`a[href="${window.location.origin}"], a[href="${window.location.origin}/"]`);e.forEach(e=>{e.setAttribute("href",`${window.location.origin}/${b}`)})}getObjects(".testimonials-rating").forEach(function(e){const t=parseInt(e.textContent.trim(),10),n=["star-o","star-o","star-o","star-o","star-o"];for(let a=0;a<t;a++)n[a]="star";let i=`<span class="rating rating-${t}-star" aria-hidden="true"><span class="sr-only">Rated ${t} Stars</span>`;n.forEach(e=>{i+=`<span class="icon ${e}"></span>`}),i+="</span>",e.innerHTML=i}),window.formLabelWidth=()=>{getObjects("form").forEach(e=>{const t=getObjects(".flex:not(.form-stacked)",e).length?".flex:not(.form-stacked)":"",n=t?`${t} > .form-input.width-default label`:".form-input.width-default label";let i=0;getObjects(n,e).forEach(e=>{i=Math.max(i,e.offsetWidth)}),0<i&&getObjects(".form-input.width-default",e).forEach(e=>{e.style.gridTemplateColumns=`${i}px 1fr`})}),getObjects("abbr.required, em.required, span.required").forEach(e=>e.textContent="")},moveDiv("#user_switching_switch_on","#page","before"),getObjects(".main-navigation ul.main-menu, .widget-navigation ul.menu").forEach(e=>{setAttributes(e,{role:"menubar","aria-label":"Main Menu"})}),getObjects(".main-navigation ul.sub-menu, .widget-navigation ul.sub-menu").forEach(e=>{e.setAttribute("role","menu")}),getObjects(".main-navigation li, .widget-navigation li").forEach(e=>{e.setAttribute("role","menuitem")}),getObjects(".main-navigation a[href], .widget-navigation a[href]").forEach(e=>{e.setAttribute("role","none")});const v=(e,t)=>{t.forEach(e=>{e.classList.add("active"),getObject("a",e).setAttribute("aria-current","page")}),getObjects(e).forEach(e=>{e.addEventListener("mouseenter",()=>{t.forEach(e=>{e.classList.remove("active"),e.classList.add("dormant")}),e.classList.add("hover")}),e.addEventListener("mouseleave",()=>{e.classList.remove("hover"),t.forEach(e=>{e.classList.remove("dormant"),e.classList.add("active")})}),e.addEventListener("focusin",()=>{t.forEach(e=>{e.classList.remove("active"),e.classList.add("dormant")}),e.classList.add("hover")}),e.addEventListener("focusout",()=>{e.classList.remove("hover"),t.forEach(e=>{e.classList.remove("dormant"),e.classList.add("active")})})})};getObjects("h3 a[aria-hidden=\"true\"]").forEach(e=>{e.parentNode.setAttribute("aria-label",e.textContent)}),setTimeout(()=>{getObjects("img:not([alt])").forEach(e=>e.setAttribute("alt",""))},50),setTimeout(()=>{getObjects("img:not([alt])").forEach(e=>e.setAttribute("alt",""))},1e3),getObjects("[role=\"menubar\"]").forEach(e=>{e.addEventListener("focus",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.add("menu-item-expanded"),t.setAttribute("aria-expanded",!0))},!0),e.addEventListener("mouseenter",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.add("menu-item-expanded"),t.setAttribute("aria-expanded",!0))},!0),e.addEventListener("blur",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.remove("menu-item-expanded"),t.setAttribute("aria-expanded",!1))},!0),e.addEventListener("mouseleave",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.remove("menu-item-expanded"),t.setAttribute("aria-expanded",!1))},!0)}),getObjects("[role=\"menubar\"] a").forEach(e=>e.setAttribute("tabindex","0")),getObjects("li[aria-haspopup=\"true\"]").forEach(e=>e.setAttribute("tabindex","-1"));const E=getObjects(".main-navigation ul.main-menu > li.current-menu-item, .main-navigation ul.main-menu > li.current_page_item, .main-navigation ul.main-menu > li.current-menu-parent, .main-navigation ul.main-menu > li.current_page_parent, .main-navigation ul.main-menu > li.current-menu-ancestor, .widget-navigation ul.menu > li.current-menu-item, .widget-navigation ul.menu > li.current_page_item, .widget-navigation ul.menu > li.current-menu-parent, .widget-navigation ul.menu > li.current_page_parent, .widget-navigation ul.menu > li.current-menu-ancestor"),L=getObjects(".main-navigation ul.sub-menu > li.current-menu-item, .main-navigation ul.sub-menu > li.current_page_item, .main-navigation ul.sub-menu > li.current-menu-parent, .main-navigation ul.sub-menu > li.current_page_parent, .main-navigation ul.sub-menu > li.current-menu-ancestor, .widget-navigation ul.sub-menu > li.current-menu-item, .widget-navigation ul.sub-menu > li.current_page_item, .widget-navigation ul.sub-menu > li.current-menu-parent, .widget-navigation ul.sub-menu > li.current_page_parent, .widget-navigation ul.sub-menu > li.current-menu-ancestor");v(".main-navigation ul.main-menu > li, .widget-navigation ul.menu > li",E),v(".main-navigation ul.sub-menu > li, .widget-navigation ul.sub-menu > li",L),getObjects("a[href^=\"#\"]:not(.carousel-control-next):not(.carousel-control-prev)").forEach(e=>{e.setAttribute("data-target",e.getAttribute("href")),e.removeAttribute("href"),e.addEventListener("click",()=>{const t=e.getAttribute("data-target"),n=getObject(t),i=+n?.getAttribute("data-hash")||0;n?setTimeout(()=>animateScroll(t,i),25):window.location.href="/"+t})});const y=Array.from(getObjects("#desktop-navigation ul a[href^=\"#\"], #desktop-navigation ul a[data-target^=\"#\"]"));if(0<y.length){const e=getObject("#desktop-navigation ul a");e&&(e.setAttribute("data-target","#masthead"),y.unshift(e))}const x=y.map(e=>{const t=e.getAttribute("data-target")||e.getAttribute("href"),n=getObject(t);return n&&"none"!==window.getComputedStyle(n.parentElement).display?n:null}).filter(e=>null!==e),w=()=>{getObjects("#desktop-navigation ul li").forEach(e=>{e.classList.remove("current-menu-item","current_page_item","active")})},k=new IntersectionObserver(e=>{e.forEach(e=>{if(e.isIntersecting){w();const t=getObject(`#desktop-navigation ul a[href="#${e.target.id}"], #desktop-navigation ul a[data-target="#${e.target.id}"]`);t?.parentElement.classList.add("current-menu-item","current_page_item","active"),closeMenu()}})},{rootMargin:`0px 0px -${convertOffset("25%")}px 0px`,threshold:.01});x.forEach(e=>e&&k.observe(e)),getObjects(".screen-mobile .section.section-lock.position-modal .flex").forEach(e=>{const t=getDeviceH()-100;e.offsetHeight>t&&e.classList.add("scrollable")}),window.addEventListener("load",()=>{const e=getObjects("section.section-lock");e.forEach(e=>{const t=e.id,n=e.dataset.delay,i=e.dataset.pos,a=e.dataset.btn;let o=e.dataset.show;o="always"===o?1e-6:"never"===o?1e5:"session"===o?null:o;const r=getObject(".closeBtn",e);if(moveDiv(r,r.nextElementSibling,"top"),keyPress(r),r.addEventListener("click",()=>s(e,t,o)),"top"===i?e.style.top=`${mobileMenuBarH()}px`:"bottom"===i?e.style.bottom="0":"header"===i&&(e.style.display="grid"),"yes"===a){const t=getObject(".modal-btn");t&&t.addEventListener("click",()=>{e.classList.add("on-screen"),document.body.classList.add("locked"),e.focus()})}"no"===a&&"no"!==getCookie("display-"+t)&&setTimeout(()=>{e.classList.add("on-screen"),document.body.classList.add("locked"),e.focus()},n),e.addEventListener("click",()=>{setCookie("display-"+t,"no",o),getObjects("video").forEach(e=>{e.pause(),e.currentTime=0}),stopAllVideos()})})}),window.fadeOutLoader=function(e){const t=getObject("#loader");if(!t)return;let n=parseFloat(getComputedStyle(t).opacity);const i=0===e?.05:.01,a=getComputedStyle(t).backgroundColor,[s,o,r]=a.match(/\d+/g).map(Number),d=setInterval(()=>{n-=i,t.style.backgroundColor=`rgba(${s}, ${o}, ${r}, ${n})`,n<=e&&(clearInterval(d),0===e&&(t.style.display="none"))},10)},getObjects("#mobile-navigation li.menu-item-has-children > a").forEach(e=>{e.setAttribute("data-href",e.getAttribute("href")),e.href="javascript:void(0)"});const C=getObject("#mobile-menu-bar .activate-btn"),A=document.body.classList.contains("top-push"),B=getObject("#mobile-navigation"),S=getObjects("ul.sub-menu",B);window.closeMenu=function(){if(document.body.classList.remove("mm-active"),null!==B.offsetParent&&C&&C.classList.remove("active"),null!==B.offsetParent&&A){getObject(".top-push.screen-mobile #page").style.top="0";let e=getObject(".top-push.screen-mobile .top-strip.stuck");null!==e&&(e.style.top=`${mobileMenuBarH()}px`)}},window.openMenu=function(){if(document.body.classList.add("mm-active"),null!==B.offsetParent&&C&&C.classList.add("active"),null!==B.offsetParent&&A){const e=getObject("#mobile-navigation").offsetHeight,t=e+mobileMenuBarH();getObject(".top-push.screen-mobile.mm-active #page").style.top=`${e}px`;let n=getObject(".top-push.screen-mobile.mm-active .top-strip.stuck");null!==n&&(n.style.top=`${t}px`)}},window.closeSubMenu=function(e){e.classList.remove("active"),e.style.height="0",e.previousElementSibling.href="javascript:void(0)"},window.openSubMenu=function(e,t){NodeList.prototype.isPrototypeOf(e)||Array.isArray(e)?e.forEach(e=>{e.classList.remove("active"),e.style.height="0"}):(e.classList.remove("active"),e.style.height="0");const n=getObjects("#mobile-navigation li.menu-item-has-children > a");n&&n.forEach(e=>{e.href="javascript:void(0)"}),e.classList.add("active"),e.style.height=`${t}px`,setTimeout(()=>{e.previousElementSibling.href=e.previousElementSibling.getAttribute("data-href")},500)},C&&C.addEventListener("click",function(){this.classList.contains("active")?closeMenu():openMenu()}),S&&S.forEach(e=>{const t=e.offsetHeight;closeSubMenu(e),e.parentElement.addEventListener("click",()=>{e.classList.contains("active")?closeSubMenu(e):openSubMenu(e,t)})}),getObjects("#mobile-navigation li:not(.menu-item-has-children)").forEach(e=>{e.addEventListener("click",()=>{closeMenu()})});const H=new Date().getDay(),N=`row-${["sun","mon","tue","wed","thu","fri","sat"][H]}`;getObjects(".office-hours").forEach(e=>{const t=getObject(`.${N}`,e);t&&t.classList.add("today")}),getObjects(".video-player").forEach(function(e){const t=document.createElement("img"),n=document.createElement("div"),i=document.createElement("div");i.dataset.id=e.dataset.id,i.dataset.link=e.dataset.link,t.src=e.dataset.thumb,n.className="play",i.appendChild(t),i.appendChild(n),i.onclick=function(){o(this)},e.appendChild(i)}),window.stopAllVideos=function(){Array.prototype.forEach.call(getObjects("iframe.video-player"),function(e){e.contentWindow.postMessage("{\"event\":\"command\",\"func\":\"pauseVideo\",\"args\":\"\"}","*")})},getObjects(".block-video.video-player").forEach(function(e){e.addEventListener("click",stopAllVideos)}),window.addAltStyle=function(e,t="style-alt"){getObjects(e).forEach(e=>{e.classList.add(t)})},getObjects("#desktop-navigation ul.main-menu > li:not(.menu-item-has-children), #desktop-navigation ul.sub-menu > li").forEach(e=>{e.addEventListener("click",function(){const e=getObject("a",this).getAttribute("href");e&&(window.location.href=e)})});const M=getObject(".place-ad");if(M){const e=getObjects(".single-post .entry-content *");let t=Math.ceil(e.length/2);30<e.length&&(t=10);const n=getObject("div.insert-promo");if(n)moveDiv(M,n,"before"),n.remove();else{const e=getObjects(".single-post .entry-content h2");if(1<e.length)moveDiv(M,e[1],"before");else if(1===e.length)moveDiv(M,e[0],"before");else{const e=getObject(".single-post .entry-content *:nth-child("+t+")");e&&moveDiv(M,e,"after")}}}var O=getObject("#tag-dropdown");O&&O.addEventListener("change",function(){var e=this.value;e&&(window.location.href=e)});var T=getObject(".img-testimonials");if(T){var _=window.getComputedStyle(T),D=_.getPropertyValue("border-radius"),j=_.getPropertyValue("border");getObjects(".anonymous-icon").forEach(e=>{e.style.borderRadius=D,e.style.border=j})}const z=getObject(".menu-clip .menu-strip");z&&setTimeout(()=>z.style.overflow="visible",2500),window.screenResize=function(){const e=getDeviceW();document.body.classList.remove("screen-5","screen-4","screen-3","screen-2","screen-1","screen-mobile","screen-desktop"),1280<e?document.body.classList.add("screen-5","screen-desktop"):e>mobileCutoff?document.body.classList.add("screen-4","screen-desktop"):860<e?document.body.classList.add("screen-3","screen-mobile"):576<e?document.body.classList.add("screen-2","screen-mobile"):document.body.classList.add("screen-1","screen-mobile"),860>=e?getObjects(".block-video video[data-mobile-w]").forEach(e=>{e.style.width=`${e.getAttribute("data-mobile-w")}%`,e.style.left=`${-(mobileW-100)/2}%`}):getObjects(".block-video video[data-mobile-w]").forEach(e=>{e.style.width="100%",e.style.left="0%"});const t=getObject("#mobile-navigation > #mobile-menu .menu-search-box input[type=\"search\"]");t&&t.matches(":focus")||closeMenu();const n=getObject("#secondary");if(n&&(document.body.classList.contains("screen-mobile")?moveDiv(n,"#colophon","before"):moveDiv(n,"#primary","after")),getObjects("div[class*=\"-faux\"]").forEach(e=>{let t=`.${e.className.replace(/\s+/g,".")}`,n=t.replace("-faux","");const i=getObject(n);if(!i){let t=`#${e.className.replace(/\s+/g,"#")}`;n=t.replace("-faux","")}i&&"none"!==window.getComputedStyle(i).display?(getObjects(t).forEach(e=>e.style.height=`${i.offsetHeight}px`),getObjects(".wp-google-badge-faux").forEach(e=>{const t=getObject(".wp-google-badge");t&&(e.style.height=`${t.offsetHeight}px`)})):getObjects(t).forEach(e=>e.style.height="0px")}),getDeviceH()>getObject("#page").offsetHeight?getObject("#wrapper-content").classList.add("extended"):getObject("#wrapper-content").classList.remove("extended"),getDeviceW()>mobileCutoff)getObjects(".wp-google-badge .wp-google-badge-btn").forEach(e=>e.style.display="block");else{const e=getObjects(".wp-google-badge .wp-google-badge-btn");if(1<e.length){e.forEach(e=>e.style.display="none");const t=Math.floor(Math.random()*e.length);e[t].style.display="block"}}getObjects("ul.side-by-side").forEach(e=>{let t=getObjects("img",e),n=0;t.forEach(e=>{const t=getComputedStyle(e).filter,i=t.split("drop-shadow(").slice(1);i.forEach(e=>{const t=e.split(" ");let i=parseFloat(t[3].replace(/[)-]|px/g,"")),a=parseFloat(t[4].replace(/[)-]|px/g,""));n+=1>i&&0<i?a:i}),n+=parseInt(getComputedStyle(e).borderLeftWidth)+parseInt(getComputedStyle(e).borderRightWidth)+parseInt(getComputedStyle(e).outlineWidth)}),e.style.padding=`${n}px`,e.style.marginLeft=`-${n/2}px`})},getObjects("h3 a[aria-hidden=\"true\"]").forEach(e=>{e.parentNode.setAttribute("aria-label",e.textContent)}),setTimeout(()=>{getObjects("img:not([alt])").forEach(e=>e.setAttribute("alt",""))},50),setTimeout(()=>{getObjects("img:not([alt])").forEach(e=>e.setAttribute("alt",""))},1e3),getObjects("[role=\"menubar\"]").forEach(e=>{e.addEventListener("focus",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.add("menu-item-expanded"),t.setAttribute("aria-expanded",!0))},!0),e.addEventListener("mouseenter",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.add("menu-item-expanded"),t.setAttribute("aria-expanded",!0))},!0),e.addEventListener("blur",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.remove("menu-item-expanded"),t.setAttribute("aria-expanded",!1))},!0),e.addEventListener("mouseleave",e=>{const t=e.target.closest("[aria-haspopup=\"true\"]");t&&(t.classList.remove("menu-item-expanded"),t.setAttribute("aria-expanded",!1))},!0)}),getObjects("[role=\"menubar\"] a").forEach(e=>e.setAttribute("tabindex","0")),getObjects("li[aria-haspopup=\"true\"]").forEach(e=>e.setAttribute("tabindex","-1")),getObjects("#request-quote-modal div.form-input").forEach(e=>{const t=getObject("label",e),n=getObject("input",e),i=getObject("textarea",e),a=getObject("select",e),s=(e,t)=>{e&&(e.id="modal-"+t)};n?(s(n,n.id),t&&t.setAttribute("for",n.id)):i?(s(i,i.id),t&&t.setAttribute("for",i.id)):a&&(s(a,a.id),t&&t.setAttribute("for",a.id))})});