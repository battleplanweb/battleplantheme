document.addEventListener("DOMContentLoaded", function () {
	"use strict"; function a(a) {
		Object.keys(c).forEach(function (b) {
			const d = a.querySelectorAll(".acf-field[data-name=\"" + b + "\"]"); d.forEach(d => {
				if (d.querySelector(".bp-rotate-image")) return; const e = d.querySelector("input[type=text], textarea"); if (!e) return; const f = c[b], g = a.querySelector(".acf-field[data-name=\"" + f + "\"]"); if (g) {
					const a = g.querySelector("input[type=hidden]"); if (a && a.value) {
						const a = document.createElement("button"); a.type = "button", a.className = "bp-rotate-image", a.dataset.captionName = b, a.style.display = "inline-flex", a.innerHTML = `
	<svg viewBox="0 0 512 512">
	    <path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="M311 464v21A278 278 0 0 1 33 209l-28-1 41-59 41 59-30 1c-6 102 82 249 254 255zm165-163 28 1-40 57-42-58h30c7-104-82-251-254-256V24a277 277 0 0 1 278 277z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
	</svg>
 `, e.insertAdjacentElement("beforebegin", a)
					}
				}
			})
		})
	} async function b(a) { const b = new FormData; b.append("action", "bp_rotate_image"), b.append("nonce", bpRotate.nonce), b.append("attachment_id", a); const c = await fetch(bpRotate.ajaxurl, { method: "POST", credentials: "same-origin", body: b }); return c.json() } console.log("Jobsite GEO admin js v5"); const c = { jobsite_photo_1_alt: "jobsite_photo_1", jobsite_photo_2_alt: "jobsite_photo_2", jobsite_photo_3_alt: "jobsite_photo_3", jobsite_photo_4_alt: "jobsite_photo_4" }; document.addEventListener("click", async function (a) {
		const d = a.target.closest(".bp-rotate-image"); if (!d) return; const e = d.dataset.captionName, f = c[e], g = document.querySelector(".acf-field[data-name=\"" + f + "\"]"), h = g.querySelector("input[type=hidden]"), i = h.value; if (!i) return void alert("No image selected."); d.disabled = !0, d.classList.add("spinning"), d.innerHTML = `
 <svg viewBox="0 0 512 512">
 <path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
 </svg>
 `; const j = await b(i); if (d.disabled = !1, d.classList.remove("spinning"), d.innerHTML = `
 <svg viewBox="0 0 512 512">
 <path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="M311 464v21A278 278 0 0 1 33 209l-28-1 41-59 41 59-30 1c-6 102 82 249 254 255zm165-163 28 1-40 57-42-58h30c7-104-82-251-254-256V24a277 277 0 0 1 278 277z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
 </svg>
 `, !j.success) return void alert("Error: " + j.data); const k = g.querySelector("img[data-name=\"image\"]"); if (k) { const a = k.src.split("?")[0].replace(/-\d+x\d+(?=\.\w+)/, ""); k.src = a + "?v=" + Date.now() }
	}); const d = new MutationObserver(() => { a(document) }); d.observe(document.body, { subtree: !0, childList: !0 }), a(document), function (a) { a.querySelectorAll(".acf-image-uploader img[data-name=\"image\"]").forEach(a => { const b = a.src.split("?")[0], c = b.replace(/-\d+x\d+(?=\.\w+)/, ""); a.src = c + "?v=" + Date.now() }) }(document)
});