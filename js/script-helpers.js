document.addEventListener("DOMContentLoaded",function(){"use strict";window.mobileCutoff=1024,window.tabletCutoff=576,window.isApple=function(){return /iPad|iPhone|iPod/.test(navigator.platform)},window.getDeviceW=function(){return isApple()&&(90===window.orientation||-90===window.orientation)?window.screen.height:window.innerWidth},window.getDeviceH=function(){return isApple()&&(90===window.orientation||-90===window.orientation)?window.screen.width:window.innerHeight},window.getMobileCutoff=function(){return mobileCutoff},window.getTabletCutoff=function(){return tabletCutoff},window.getObject=function(a,b=document,c=!1){return c=!0===c||"true"===c,"string"==typeof a&&"#"!==a?c?b.querySelectorAll(a):b.querySelector(a):a instanceof HTMLElement?c?[a]:a:window.jQuery&&a instanceof jQuery?c?a.get():a[0]:null},window.getObjects=function(a,b=document){return getObject(a,b,!0)},window.isVisible=function(a){if(a){const b=window.getComputedStyle(a);return"none"!==b.display}},window.isMobile=function(){return!!document.body.classList.contains("screen-mobile")},window.setAttributes=function(a,b){for(const c in b)a.setAttribute(c,b[c])},window.setStyles=function(a,b){Object.assign(a.style,b)},window.addCSS=function(a,b){const c=[...document.styleSheets].find(a=>a.href&&a.href.includes("style-site"));if(c){const d=[...c.cssRules].some(c=>c.cssText.includes(`${a} { ${b}`));d||(c.insertRule?c.insertRule(`${a} { ${b} }`,c.cssRules.length):c.addRule(a,b))}},window.setCookie=function(a,b,c){let e=new Date;e.setTime(e.getTime()+1e3*(60*(60*(24*c))));const d=c?"expires="+e.toUTCString()+"; ":"";document.cookie=a+"="+encodeURIComponent(b)+"; "+d+"path=/; SameSite=Strict; Secure"},window.getCookie=function(a){const b=a+"=",c=decodeURIComponent(document.cookie),d=c.split(";");for(let e=0;e<d.length;e++){const a=d[e].trim();if(0==a.indexOf(b))return a.substring(b.length)}return""},window.deleteCookie=function(a){setCookie(a,"",-1)},window.debounce=function(a,b){let c;return function(){const d=this,e=arguments;clearTimeout(c),c=setTimeout(()=>a.apply(d,e),b)}},window.preloadImg=function(a,b="both"){if(!("mobile"===b&&getDeviceW()>mobileCutoff)&&!("desktop"===b&&getDeviceW()<mobileCutoff)){const b=new Image;b.src=site_dir.upload_dir_uri+"/"+a}},document.body.classList.contains("wp-admin")||null===site_bg||(preloadImg("site-background."+site_bg,"desktop"),preloadImg("site-background-phone."+site_bg,"mobile"))});