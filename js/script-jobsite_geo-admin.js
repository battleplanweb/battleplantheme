document.addEventListener("DOMContentLoaded", function () {
    "use strict"; function e(c) {
        Object.keys(l).forEach(function (h) {
            const a = c.querySelectorAll(".acf-field[data-name=\"" + h + "\"]"); a.forEach(a => {
                if (!a.querySelector(".bp-rotate-image")) {
                    const b = a.querySelector("input[type=text], textarea"); if (b) {
                        const a = l[h], d = c.querySelector(".acf-field[data-name=\"" + a + "\"]"); if (d) {
                            const c = d.querySelector("input[type=hidden]"); if (c && c.value) {
                                const c = document.createElement("button"); c.type = "button", c.className = "bp-rotate-image", c.dataset.captionName = h, c.style.display = "inline-flex", c.innerHTML = `
    <svg viewBox="0 0 512 512">
        <path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="M311 464v21A278 278 0 0 1 33 209l-28-1 41-59 41 59-30 1c-6 102 82 249 254 255zm165-163 28 1-40 57-42-58h30c7-104-82-251-254-256V24a277 277 0 0 1 278 277z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
    </svg>
`, b.insertAdjacentElement("beforebegin", c)
                            }
                        }
                    }
                }
            })
        })
    } async function a(d) { const a = new FormData; a.append("action", "bp_rotate_image"), a.append("nonce", bpRotate.nonce), a.append("attachment_id", d); const b = await fetch(bpRotate.ajaxurl, { method: "POST", credentials: "same-origin", body: a }); return b.json() } console.log("Jobsite GEO admin js v5"); const l = { jobsite_photo_1_alt: "jobsite_photo_1", jobsite_photo_2_alt: "jobsite_photo_2", jobsite_photo_3_alt: "jobsite_photo_3", jobsite_photo_4_alt: "jobsite_photo_4" }; document.addEventListener("click", async function (b) {
        const c = b.target.closest(".bp-rotate-image"); if (c) {
            const b = c.dataset.captionName, d = l[b], e = document.querySelector(".acf-field[data-name=\"" + d + "\"]"), f = e.querySelector("input[type=hidden]"), g = f.value; if (!g) return void alert("No image selected."); c.disabled = !0, c.classList.add("spinning"), c.innerHTML = `
<svg viewBox="0 0 512 512">
<path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
</svg>
`; const h = await a(g); if (c.disabled = !1, c.classList.remove("spinning"), c.innerHTML = `
<svg viewBox="0 0 512 512">
<path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="M311 464v21A278 278 0 0 1 33 209l-28-1 41-59 41 59-30 1c-6 102 82 249 254 255zm165-163 28 1-40 57-42-58h30c7-104-82-251-254-256V24a277 277 0 0 1 278 277z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
</svg>
`, !h.success) return void alert("Error: " + h.data); const i = e.querySelector("img[data-name=\"image\"]"); if (i) { const b = i.src.split("?")[0].replace(/-\d+x\d+(?=\.\w+)/, ""); i.src = b + "?v=" + Date.now() }
        }
    }); const b = new MutationObserver(() => { e(document) }); b.observe(document.body, { subtree: !0, childList: !0 }), e(document), function (b) { b.querySelectorAll(".acf-image-uploader img[data-name=\"image\"]").forEach(d => { const a = d.src.split("?")[0], b = a.replace(/-\d+x\d+(?=\.\w+)/, ""); d.src = b + "?v=" + Date.now() }) }(document)
});