document.addEventListener("DOMContentLoaded",function(){"use strict";const a=document.body.getAttribute("id");window.addEventListener("load",()=>{const b=getObjects(".track-clicks, .wpcf7-submit");b.forEach(a=>{a.addEventListener("click",function(){const b=a.getAttribute("data-action")||"email",c=a.getAttribute("data-url");"function"==typeof gtag&&gtag("event","unlock_achievement",{achievement_id:"conversion-"+b}),c&&(document.location=c)})}),getObjects("div.coupon-inner").forEach(a=>{a.classList.add("tracking"),a.setAttribute("data-track","coupon")}),getObjects(".carousel.slider-testimonials").forEach(a=>{a.classList.add("tracking"),a.setAttribute("data-track","testimonials")});let c="",d=0;const e=new PerformanceObserver(a=>{const b=a.getEntries();b.forEach(a=>{a.startTime>d&&(d=a.startTime,c=a)})});e.observe({type:"largest-contentful-paint",buffered:!0}),window.addEventListener("beforeunload",()=>e.disconnect());const f=Date.now();let g=f-startTime,h="desktop";getDeviceW()<=tabletCutoff&&(h="tablet"),getDeviceW()<=mobileCutoff&&(h="mobile"),g+="desktop"==h?300:600,setTimeout(()=>{let b=g>d?g:d;b=(b/1e3).toFixed(1),console.log("LCP: "+c.url+" ~ "+(c.loadTime/1e3).toFixed(1)+"s"),"function"==typeof gtag&&gtag("event","join_group",{group_id:`${a}»${h}«${b}`})},1),setTimeout(function(){const b=[{value:20,flag:"view20"},{value:30,flag:"view30"},{value:40,flag:"view40"},{value:50,flag:"view50"},{value:60,flag:"view60"},{value:70,flag:"view70"},{value:80,flag:"view80"},{value:90,flag:"view90"},{value:100,flag:"view100"}];b.forEach(a=>{window[a.flag]=!1});let c=0;"function"==typeof gtag&&gtag("event","unlock_achievement",{achievement_id:`${a}-init`});const d=getObject("#wrapper-content").getBoundingClientRect(),e=d.top,f=d.height,g=getObject(".wp-google-badge"),h=g?g.getBoundingClientRect().height:0,i=window.innerHeight-h;window.scrollTracking=function(){const d=window.pageYOffset,g=Math.round(100*((d+i-e)/f));c=g>c?Math.max(0,Math.min(100,g)):c,b.forEach(b=>{c>=b.value&&!window[b.flag]&&("function"==typeof gtag&&gtag("event","unlock_achievement",{achievement_id:`${a}-${b.value}`}),window[b.flag]=!0)})};const j=[...getObjects(".tracking"),...getObjects("[data-track]")],k=new IntersectionObserver((a,b)=>{a.forEach(a=>{if(a.isIntersecting){const c=a.target.getAttribute("data-track");getCookie("track-"+c)||("function"==typeof gtag&&gtag("event","unlock_achievement",{achievement_id:"track-"+c}),setCookie("track-"+c,!0)),b.unobserve(a.target)}})},{root:null,rootMargin:"0px",threshold:1});j.forEach(a=>k.observe(a))},300),getCookie("user-city")||setTimeout(()=>{fetch("https://ipapi.co/json/").then(a=>a.json()).then(a=>{setCookie("user-city",a.city,""),setCookie("user-region",a.region_code,""),setCookie("user-country",a.country_name,"")}).catch(a=>console.error("Error fetching location data:",a))},4e3)})});