document.addEventListener("DOMContentLoaded",function(){"use strict";function a(a){["sessions","new","engagement","search","pageviews","duration"].forEach(b=>{let c=[];const d=Array.from(document.querySelectorAll(`#battleplan_${a}_stats .trends-${a} tr.${b}`)).reverse();for(let a of d)if("0"!==a.getAttribute("data-count"))break;else a.remove();getObjects(`#battleplan_${a}_stats .trends-${a} tr.${b}`).forEach(a=>{c.push(parseInt(a.getAttribute("data-count"),10))}),c.sort((c,a)=>a-c);const e=c.length,f=Math.floor(e/3);let g=0;for(let d,e=0;e<f;e++)d=100-100/f*e,d*=2,getObjects(`#battleplan_${a}_stats .trends-${a} tr.${b}[data-count="${c[e]}"] td`).forEach(a=>{a.style.color="#009809",a.style.filter=`saturate(${d}%)`});for(let d,h=e-1;h>2*f;h--)d=100-100/f*g,d*=2,getObjects(`#battleplan_${a}_stats .trends-${a} tr.${b}[data-count="${c[h]}"] td`).forEach(a=>{a.style.color="#f00",a.style.filter=`saturate(${d}%)`}),g++})}function b(a,b){const c="https://"+window.location.hostname+"/wp-admin/admin-ajax.php",d=new URLSearchParams;d.append("action","update_meta"),d.append("type","site"),d.append("key","bp_admin_"+a),d.append("value",b),fetch(c,{method:"POST",body:d,headers:{"Content-Type":"application/x-www-form-urlencoded"}}).then(a=>a.json()).then(a=>console.log(a)).catch(a=>console.error("Error:",a))}function c(){const a=getObject("table.trends tr.trends.active");if(a){const b=getObjects("table.trends td.page");a.classList.contains("sessions")?b.forEach(a=>a.textContent="Sessions \u2022 Users"):a.classList.contains("new")?b.forEach(a=>a.textContent="New Users \u2022 Pct %"):a.classList.contains("engagement")?b.forEach(a=>a.textContent="Engaged \u2022 Pct %"):a.classList.contains("search")?b.forEach(a=>a.textContent="Search"):a.classList.contains("pageviews")?b.forEach(a=>a.textContent="Pageviews \u2022 Per User"):a.classList.contains("duration")&&b.forEach(a=>a.textContent="Engaged \u2022 Total")}}function d(){const a=s.value;t&&(t.textContent="View: "+a)}getObjects(".disabled").forEach(a=>{a.classList.remove("disabled","-disabled")}),getObjects("select, input, button").forEach(a=>{a.removeAttribute("disabled")}),getObject(".logged-in #wpadminbar button").addEventListener("click",()=>{window.location.href="/wp-admin/"}),console.log("1"),getObjects("#battleplan_site_stats tr").forEach(a=>{let b=100-parseInt(a.getAttribute("data-age"),10);b*=2,0>b&&(b=0),getObjects("td",a).forEach(a=>{a.style.filter=`saturate(${b}%)`})}),getObjects("#dashboard-widgets .postbox").forEach(a=>{a.addEventListener("click",()=>{a.classList.toggle("active")})}),a("weekly"),a("monthly"),a("quarterly");const e=getObject("#page-top_text"),f=getObject("#page-bottom_text"),g=getObject("#comment_status"),h=getObject("#ping_status"),i=getObject("#commentstatusdiv"),j=getObject("#commentsdiv");e&&!e.innerHTML.trim()&&getObject("#page-top").classList.add("closed"),f&&!f.innerHTML.trim()&&getObject("#page-bottom").classList.add("closed"),g&&(g.checked||h.checked)?i&&i.classList.remove("closed"):(i&&i.classList.add("closed"),j&&(j.style.display="none")),setTimeout(()=>{const a=getObject("#wds_title"),b=getObject("#wds_metadesc"),c=getObject("#wds-wds-meta-box");a&&!a.value.trim()&&b&&!b.value.trim()?c&&c.classList.add("closed"):c&&c.classList.remove("closed"),getObjects(".sui-border-frame").forEach(a=>a.style.display="block"),getObjects("#poststuff .sui-box-body, #poststuff .wds-focus-keyword, #poststuff .wds-preview-description, #poststuff p.wds-preview-description, #poststuff .wds-edit-meta .sui-button").forEach(a=>a.style.display="none"),getObjects(".wds-seo-analysis-label").forEach(a=>{a.addEventListener("click",()=>{getObjects(".sui-box-body, .wds-focus-keyword").forEach(a=>a.style.display="block")})})},1e3);const k=getObject(".trend-buttons"),l=getObject("#postbox-container-3");k&&l&&l.prepend(k),c();const m=getObjects(".trend-buttons .sessions, .trend-buttons .new, .trend-buttons .engagement, .trend-buttons .search, .trend-buttons .pageviews, .trend-buttons .duration");m.forEach(a=>{a.addEventListener("click",d=>{d.preventDefault();const e=a.className.split(" ").find(a=>["sessions","new","engagement","search","pageviews","duration"].includes(a));e&&(getObjects("table.trends tr.trends, .trend-buttons div a").forEach(a=>a.classList.remove("active")),getObjects(`table.trends tr.trends.${e}`).forEach(a=>a.classList.add("active")),getObject("a",a).classList.add("active"),c(),b("btn2",e))})});const n=getObject("#postbox-container-2"),o=getObject(".local-visitors-buttons"),p=getObject(".last-visitors-buttons");n&&o&&n.prepend(o),n&&p&&n.prepend(p);const q=(a,c,d)=>{const e=getObject(a);e&&e.addEventListener("click",a=>{a.preventDefault(),getObjects(".handle-label, .last-visitors-buttons div, .last-visitors-buttons div a").forEach(a=>a.classList.remove("active")),getObjects(c).forEach(a=>a.classList.add("active")),a.currentTarget.querySelector("a").classList.add("active"),b("btn1",d)})};q(".last-visitors-buttons .week",".handle-label-7","week"),q(".last-visitors-buttons .month",".handle-label-30","month"),q(".last-visitors-buttons .quarter",".handle-label-90","quarter"),q(".last-visitors-buttons .semester",".handle-label-180","semester"),q(".last-visitors-buttons .year",".handle-label-365","year");const r=getObject(".local-visitors-buttons .local");r&&r.addEventListener("click",a=>{a.preventDefault();const c=a.currentTarget.querySelector("a");c.classList.contains("active")?(c.classList.remove("active"),c.classList.add("not-active"),b("btn3","not-active")):(c.classList.remove("not-active"),c.classList.add("active"),b("btn3","active")),setTimeout(()=>location.reload(),1e3)});const s=getObject("#title"),t=getObject("#wp-admin-bar-view a.ab-item");s&&(s.addEventListener("input",d),d());const u=getObject("#wp-admin-bar-view a");u&&u.setAttribute("target","_blank");const v=getObjects(".col.when"),w=getObjects(".col.notes");v.forEach(a=>{a.addEventListener("click",()=>{w.forEach(a=>a.style.display="block")})}),w.forEach(a=>{a.addEventListener("click",()=>{w.forEach(a=>a.style.display="none")})});const x=getObjects("span.edit a"),y=getObjects("span.copy a");x.forEach(a=>{a.innerHTML="<i class=\"dashicons-edit\"></i>"}),y.forEach(a=>{a.innerHTML="<i class=\"dashicons-clone\"></i>"});var z=getObject("#view_jobsite_geo_pages");z&&z.addEventListener("change",function(){var a=this.value;a&&window.open(a,"_blank")}),"undefined"!=typeof QTags&&(QTags.addButton("bp_paragraph","p","<p>","</p>","p","Paragraph Tag",1),QTags.addButton("bp_li","li"," <li>","</li>","li","List Item",100),QTags.addButton("bp_h1","h1","<h1>","</h1>","h1","H1 Tag",1),QTags.addButton("bp_h2","h2","<h2>","</h2>","h2","H2 Tag",1),QTags.addButton("bp_h3","h3","<h3>","</h3>","h3","H3 Tag",1),QTags.addButton("bp_widget","widget","[widget type=\"basic\" title=\"Brand Logo (omit to hide)\" lock=\"none, top, bottom\" priority=\"2, 1, 3, 4, 5\" set=\"none, param\" class=\"\" show=\"slug\" hide=\"slud\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]\n","[/widget]\n\n","widget","Widget",1e3),QTags.addButton("bp_section","section","[section name=\"becomes id attribute\" hash=\"compensation for scroll on one-page sites\" style=\"corresponds to css\" width=\"default, stretch, full, edge, inline\" background=\"url\" left=\"50\" top=\"50\" class=\"\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]\n","[/section]\n\n","section","Section",1e3),QTags.addButton("bp_layout","layout"," [layout grid=\"1-auto, 1-1-1-1, 5e, content, 80px 100px 1fr\" break=\"none, 3, 4\" valign=\"start, stretch, center, end\" class=\"\"]\n\n"," [/layout]\n","layout","Layout",1e3),QTags.addButton("bp_column","column","  [col name=\"becomes id attribute\" hash=\"compensation for scroll on one-page sites\" align=\"center, left, right\" valign=\"start, stretch, center, end\" background=\"url\" left=\"50\" top=\"50\" class=\"\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]\n","  [/col]\n\n","column","Column",1e3),QTags.addButton("bp_image","image","   [img size=\"100 1/2 1/3 1/4 1/6 1/12\" order=\"1, 2, 3\" link=\"url to link to\" new-tab=\"false, true\" ada-hidden=\"false, true\" class=\"\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]","[/img]\n","image","Image",1e3),QTags.addButton("bp_video","video","   [vid size=\"100 1/2 1/3 1/4 1/6 1/12\" order=\"1, 2, 3\" link=\"url of video\" thumb=\"url of thumb, if not using auto\" preload=\"false, true\" class=\"\" related=\"false, true\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]","[/vid]\n","video","Video",1e3),QTags.addButton("bp_caption","caption","[caption align=\"aligncenter, alignleft, alignright | size-full-s\" width=\"800\"]<img src=\"/filename.jpg\" alt=\"\" class=\"size-full-s\" />Type caption here.[/caption]\n","","caption","Caption",1e3),QTags.addButton("bp_group","group","   [group size = \"100 1/2 1/3 1/4 1/6 1/12\" order=\"1, 2, 3\" class=\"\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]\n","   [/group]\n\n","group","Group",1e3),QTags.addButton("bp_text","text","   [txt size=\"100 1/2 1/3 1/4 1/6 1/12\" order=\"2, 1, 3\" class=\"\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]\n","   [/txt]\n","text","Text",1e3),QTags.addButton("bp_button","button","   [btn size=\"100 1/2 1/3 1/4 1/6 1/12\" order=\"3, 1, 2\" align=\"center, left, right\" link=\"url to link to\" get-biz=\"link in functions.php\" new-tab=\"false, true\" class=\"\" icon=\"chevron-right\" fancy=\"(blank), 2\" ada=\"text for ada button\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]","[/btn]\n","button","Button",1e3),QTags.addButton("bp_social","social","   [social-btn type=\"email, facebook, twitter\" img=\"none, link\"]","","social","Social",1e3),QTags.addButton("bp_accordion","accordion","   [accordion title=\"clickable title\" class=\"\" excerpt=\"false, whatever text you want the excerpt to be\" active=\"false, true\" icon=\"true, false, /wp-content/uploads/image.jpg\" btn=\"false/true (prints title) / Open Button Text\" btn_collapse=\"blank (hides btn) / Close Button Text\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\", scroll=\"true\", track=\"\"]","[/accordion]\n\n","accordion","Accordion",1e3),QTags.addButton("bp_expire-content","expire","[expire start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\"]","[/expire]\n\n","expire","Expire",1e3),QTags.addButton("bp_restrict-content","restrict","[restrict max=\"administrator, any role\" min=\"none, any role\"]","[/restrict]\n\n","restrict","Restrict",1e3),QTags.addButton("bp_lock-section","lock","[lock name=\"becomes id attribute\" style=\"default:lock, 1, 2, 3, etc\" width=\"edge, default, stretch, full, inline\" position=\"bottom, top, modal, header\" delay=\"3000\" show=\"session, never, always, # days\" background=\"url\" left=\"50\" top=\"50\" class=\"\" start=\"YYYY-MM-DD\" end=\"YYYY-MM-DD\" btn-activated=\"no, yes\" track=\"adds to data-track\" content=\"text, image\"]\n [layout]\n\n"," [/layout]\n[/lock]\n\n","lock","Lock",1e3),QTags.addButton("bp_clear","clear","[clear height=\"px, em\" class=\"\"]\n\n","","clear","Clear",1e3),QTags.addButton("bp_images_side-by-side","side by side images","[side-by-side img=\"ids\" size=\"half-s, third-s, full\" align=\"center, left, right\" full=\"id\" pos=\"bottom, top\" break=\"none, 3, 2, 1\"]\n\n","","side by side images","Side By Side Images",1e3),QTags.addButton("bp_get-countup","get countup","[get-countup name=\"becomes the id\" start=\"0\" end=\"1000\" decimals=\"0\" duration=\"5\" delay=\"0\" waypoint=\"85%\" easing=\"false, easeInSine, EaseOutSine, EaseInOutSine, Quad, Cubic, Expo, Circ\" grouping=\"true, false\" separator=\",\" decimal=\".\" prefix=\"...\" suffix=\"...\"]\n\n","","get countup","Get Count Up",1e3),QTags.addButton("bp_get-wp-page","get wp page","[get-wp-page type=\"page, post, cpt\" id=\"\" slug=\"\" title=\"\" display=\"content, excerpt, title, thumbnail, link\"]\n\n","","get wp page","Get WP Page",1e3),QTags.addButton("bp_copy-content","copy content","[copy-content \"slug\"=>\"home\", \"section\"=>\"page top, page bottom\" ]\n\n","","copy content","Copy Content",1e3),QTags.addButton("bp_random-image","random image","   [get-random-image id=\"\" tag=\"random\" size=\"thumbnail, third-s\" link=\"no, yes\" number=\"1\" offset=\"\" align=\"left, right, center\" order_by=\"recent, rand, menu_order, title, id, post_date, modified, views\" order=\"asc, desc\" shuffle=\"no, yes, peak, valley, alternate\" lazy=\"true, false\"]\n\n","","random image","Random Image",1e3),QTags.addButton("bp_random-post","random post","   [get-random-posts num=\"1\" offset=\"0\" leeway=\"0\" type=\"post\" tax=\"\" terms=\"\" orderby=\"recent, rand, views-today, views-7day, views-30day, views-90day, views-365day, views-all\" sort=\"asc, desc\" count_view=\"true, false\" thumb_only=\"false, true\" thumb_col=\"1, 2, 3, 4\" show_title=\"true, false\" title_pos=\"outside, inside\" show_date=\"false, true\" show_author=\"false, true\" show_excerpt=\"true, false\" show_social=\"false, true\" show_btn=\"true, false\" button=\"Read More\" btn_pos=\"inside, outside\" thumbnail=\"force, false\" link=\"post, false, cf-field_name, /link-destination/\" start=\"\" end=\"\" exclude=\"\" x_current=\"true, false\" size=\"thumbnail, size-third-s\" lazy=\"true, false\" pic_size=\"1/3\" text_size=\"\"]\n\n","","random post","Random Post",1e3),QTags.addButton("bp_random-text","random text","   [get-random-text cookie=\"true, false\" text1=\"\" text2=\"\" text3=\"\" text4=\"\" text5=\"\" text6=\"\" text7=\"\"]\n\n","","random text","Random Text",1e3),QTags.addButton("bp_row-of-pics","row of pics","   [get-row-of-pics id=\"\" tag=\"row-of-pics\" col=\"4\" row=\"1\" offset=\"0\" size=\"half-s, thumbnail\" valign=\"center, start, stretch, end\" link=\"no, yes\" order_by=\"recent, rand, menu_order, title, id, post_date, modified, views\" order=\"asc, desc\" shuffle=\"no, yes, peak, valley, alternate\" lazy=\"true, false\" class=\"\"]\n\n","","row of pics","Row Of Pics",1e3),QTags.addButton("bp_post-slider","post slider","   [get-post-slider type=\"\" id=\"(for images)\" auto=\"yes, no\" interval=\"6000\" loop=\"true, false\" num=\"4\" offset=\"0\" pics=\"yes, no\" controls=\"yes, no\" controls_pos=\"below, above\" indicators=\"no, yes\" justify=\"space-around, space-evenly, space-between, center\" pause=\"true, false\" tax=\"\" terms=\"\" orderby=\"recent, rand, id, author, title, name, type, date, modified, parent, comment_count, relevance, menu_order, (images) views, (posts) views-today, views-7day, views-30day, views-90day, views-365day, views-all\" order=\"asc, desc\" post_btn=\"\" all_btn=\"View All\" link=\"\" start=\"\" end=\"\" exclude=\"\" x_current=\"true, false\" show_excerpt=\"true, false\" show_content=\"false, true\" size=\"thumbnail, half-s\" pic_size=\"1/3\" text_size=\"\" class=\"\" (images) slide_type=\"box, screen, fade\" slide_effect=\"fade, dissolve, cycle, boomerang, zoom, fade-cycle, cycle-fade, fade-zoom, zoom-fade\" tag=\"\" caption=\"no, yes\" id=\"\" mult=\"1\" truncate=\"true, false, # of characters\" lazy=\"true, false\" blur=\"false, true\", rand_start=>\"\", content_type=\"image, text\"]\n\n","","post slider","Post Slider",1e3),QTags.addButton("bp_images-slider","Images Slider","<div class=\"alignright size-half-s\">[get-post-slider type=\"images\" num=\"6\" size=\"half-s\" controls=\"no\" indicators=\"yes\" tag=\"featured\" all_btn=\"\" link=\"none, alt, description, blank\" slide_type=\"box, screen, fade\" slide_effect=\"fade, dissolve, cycle, boomerang, zoom, fade-cycle, cycle-fade, fade-zoom, zoom-fade\" orderby=\"recent\" blur=\"false, true\" lazy=\"true, false\"]</div>\n\n","","images-slider","Images Slider",1e3),QTags.addButton("bp_testimonial-slider","Testimonial Slider","  [col]\n   <h2>What Our Customers Say...</h2>\n   [get-post-slider type=\"testimonials\" num=\"6\" pic_size=\"1/3\"]\n  [/col]\n\n","","testimonial-slider","Testimonial Slider",1e3),QTags.addButton("bp_logo-slider","Logo Slider","[section name=\"Logo Slider\" style=\"1\" width=\"edge\"]\n [layout]\n  [col]\n   [get-logo-slider num=\"-1\" space=\"10\" size=\"full, thumbnail, quarter-s\" max_w=\"33\" tag=\"featured\" package=\"null, hvac\" orderby=\"rand, id, title, date, modified, menu_order, recent, views\" order=\"asc, desc\" shuffle=\"false, true\" speed=\"slow, fast, # (10=slow, 25=fast)\" direction=\"normal, reverse\" pause=\"no, yes\" link=\"false, true\"]\n  [/col]\n [/layout]\n[/section]\n\n","","logo-slider","Logo Slider",1e3),QTags.addButton("bp_random-product","Random Product","  [col]\n   <h2>Featured Product</h2>\n   [get-random-posts type=\"products\" leeway=\"1\" button=\"Learn More\" orderby=\"views-30day\" sort=\"desc\"]\n  [/col]\n\n","","random-product","Random Product",1e3))});