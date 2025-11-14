document.addEventListener("DOMContentLoaded", function() {
	"use strict";

	console.log("Jobsite GEO admin js v5");

	// Map captions -> image fields
	const FIELD_MAP = {
		jobsite_photo_1_alt: "jobsite_photo_1",
		jobsite_photo_2_alt: "jobsite_photo_2",
		jobsite_photo_3_alt: "jobsite_photo_3",
		jobsite_photo_4_alt: "jobsite_photo_4"
	};

	// Force full-size image previews
	function forceFullSizePreviews(root) {
		root.querySelectorAll('.acf-image-uploader img[data-name="image"]').forEach(img => {
			const url = img.src.split('?')[0];
			const clean = url.replace(/-\d+x\d+(?=\.\w+)/, ''); // remove -150x150 etc
			img.src = clean + "?v=" + Date.now();
		});
	}

	// Insert rotate buttons next to captions
	function insertButtons(root) {
		Object.keys(FIELD_MAP).forEach(function(captionName) {

			const captionFields = root.querySelectorAll('.acf-field[data-name="' + captionName + '"]');

			captionFields.forEach(fieldEl => {

				// Avoid duplicates
				if (fieldEl.querySelector(".bp-rotate-image")) return;

				const input = fieldEl.querySelector("input[type=text], textarea");
				if (!input) return;

				const imgFieldName = FIELD_MAP[captionName];

				// Find the related image field
				const imgField = root.querySelector('.acf-field[data-name="' + imgFieldName + '"]');
				if (!imgField) return;

				const hidden = imgField.querySelector('input[type=hidden]');
				if (!hidden || !hidden.value) return;

				// Build the button
				const btn = document.createElement("button");
				btn.type = "button";
				btn.className = "bp-rotate-image";
				btn.dataset.captionName = captionName;
				btn.style.display = "inline-flex";

                btn.innerHTML = `
                    <svg viewBox="0 0 512 512">
                        <path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="M311 464v21A278 278 0 0 1 33 209l-28-1 41-59 41 59-30 1c-6 102 82 249 254 255zm165-163 28 1-40 57-42-58h30c7-104-82-251-254-256V24a277 277 0 0 1 278 277z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
                    </svg>
                `;

				input.insertAdjacentElement("beforebegin", btn);
			});
		});
	}

	// Rotate image handler
	async function rotateImage(attachmentId) {
		const fd = new FormData();
		fd.append("action", "bp_rotate_image");
		fd.append("nonce", bpRotate.nonce);
		fd.append("attachment_id", attachmentId);

		const res = await fetch(bpRotate.ajaxurl, {
			method: "POST",
			credentials: "same-origin",
			body: fd
		});

		return res.json();
	}

	// Refresh ACF preview
	function refreshImage(fieldEl, attachmentId) {
		fieldEl.querySelectorAll('img[data-name="image"]').forEach(img => {
			const clean = img.src.split("?")[0].replace(/-\d+x\d+(?=\.\w+)/, "");
			img.src = clean + "?t=" + Date.now();
		});
	}

	// Global click listener
	document.addEventListener("click", async function(e) {

		const btn = e.target.closest(".bp-rotate-image");
		if (!btn) return;

		const captionName = btn.dataset.captionName;
		const imgFieldName = FIELD_MAP[captionName];

		const imgField = document.querySelector('.acf-field[data-name="' + imgFieldName + '"]');
		const hidden = imgField.querySelector('input[type=hidden]');
		const attachmentId = hidden.value;

		if (!attachmentId) {
			alert("No image selected."); 
			return;
		}

		// Spinner
		btn.disabled = true;
        btn.classList.add("spinning");
        btn.innerHTML = `
            <svg viewBox="0 0 512 512">
                <path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
            </svg>
        `;

		const res = await rotateImage(attachmentId);

		// Restore button icon
		btn.disabled = false;
        btn.classList.remove("spinning");
        btn.innerHTML = `
            <svg viewBox="0 0 512 512">
                <path d="m359 227 16 67c5 24-4 41-28 47l-138 33c-23 6-40-5-46-28l-33-137c-5-24 5-40 28-46l138-34c24-5 40 5 46 29l17 69zm-143 45-27 64c-3 10 5 10 12 8l140-34c3-1 9-3 9-5-1-4-3-8-6-10l-64-55c-8-6-12-5-16 4l-20 49-28-21zm-7-30c14-3 23-18 20-32s-17-23-32-20c-14 3-23 18-20 32 3 13 18 23 32 20z"/><path d="M311 464v21A278 278 0 0 1 33 209l-28-1 41-59 41 59-30 1c-6 102 82 249 254 255zm165-163 28 1-40 57-42-58h30c7-104-82-251-254-256V24a277 277 0 0 1 278 277z"/><path d="m216 272 28 21 20-49c4-9 8-10 15-4l65 55c3 2 5 6 6 10 0 2-6 4-9 5l-140 34c-7 2-15 2-12-8l27-64zm-7-30c-14 3-29-7-32-20-3-14 6-29 20-32 15-3 29 6 32 20s-6 29-20 32z" style="fill:none;stroke:#000;"/>
            </svg>
        `;

		if (!res.success) {
			alert("Error: " + res.data);
			return;
		}

		refreshImage(imgField, attachmentId);
	});

	// Observe ACF DOM changes and reapply buttons + image forcing
	const observer = new MutationObserver(mutations => {
		insertButtons(document);
		forceFullSizePreviews(document);
	});

	observer.observe(document.body, {
		subtree: true,
		childList: true
	});

	// Run once on load
	insertButtons(document);
	forceFullSizePreviews(document);
});
