document.addEventListener("DOMContentLoaded",function(){"use strict";function a(){d.style.opacity="0",setTimeout(()=>{d.style.visibility="hidden",document.body.style.overflow="visible"},500)}function b(a){e.classList.add("transition-out","direction-"+l);const b=new Image;b.src=j[a].href;let d=!1,f=!1;const g=()=>{d&&f&&(e.src=b.src,e.alt=getObject("img",j[a]).alt,e.classList.remove("transition-out"),e.classList.add("transition-in"),setTimeout(()=>{e.classList.remove("transition-in","direction-"+l)},300),c(a,j.length))};b.onload=()=>{d=!0,g()},setTimeout(()=>{f=!0,g()},300)}function c(a,b){f.textContent=`${a+1} of ${b}`}const d=getObject(".lightbox-overlay"),e=getObject(".lightbox-overlay img"),f=getObject(".lightbox-overlay .lightbox-counter"),g=getObject(".lightbox-overlay .button-prev"),h=getObject(".lightbox-overlay .button-next"),i=getObject(".lightbox-overlay .closeBtn");moveDiv(d,"#loader","after");const j=getObjects(".lightbox a");let k=-1,l="next";j.forEach((a,b)=>{a.addEventListener("click",f=>{f.preventDefault(),e.src=a.href,e.alt=getObject("img",a).alt,d.style.opacity="1",d.style.visibility="visible",document.body.style.overflow="hidden",d.style.opacity=1,k=b,c(k,j.length)})}),g.addEventListener("click",()=>{0<k?k--:k=j.length-1,l="prev",b(k)}),h.addEventListener("click",()=>{k<j.length-1?k++:k=0,l="next",b(k)}),d.addEventListener("click",b=>{b.target===d&&a()}),i.addEventListener("click",a)});