/* Battle Plan Web Design — admin JS for the "date_blocks" ACF field type.
   Adds / removes fixed-shape date-range rows. Vanilla JS, no jQuery. */

(function () {
	"use strict";

	function init(wrap) {
		if (wrap.dataset.bpInit) return;        // guard against double-binding
		wrap.dataset.bpInit = "1";

		var rows = wrap.querySelector(".bp-db-rows");
		var template = wrap.querySelector(".bp-db-template .bp-db-row");
		var addBtn = wrap.querySelector(".bp-db-add");
		if (!rows || !template || !addBtn) return;

		addBtn.addEventListener("click", function () {
			var i = parseInt(wrap.getAttribute("data-next"), 10) || 0;
			var clone = template.cloneNode(true);

			clone.querySelectorAll("input").forEach(function (input) {
				input.disabled = false;
				if (input.name) input.name = input.name.replace("__i__", i);
				input.value = "";
			});

			rows.appendChild(clone);
			wrap.setAttribute("data-next", i + 1);
		});

		// Remove a row (event delegation so it covers cloned rows too).
		wrap.addEventListener("click", function (e) {
			if (!e.target.classList.contains("bp-db-remove")) return;
			var row = e.target.closest(".bp-db-row");
			if (row && !row.closest(".bp-db-template")) row.remove();
		});
	}

	function initAll(root) {
		(root || document).querySelectorAll(".bp-date-blocks").forEach(init);
	}

	document.addEventListener("DOMContentLoaded", function () { initAll(document); });

	// ACF can inject fields after load (e.g. newly added flexible-content layouts).
	if (window.acf && typeof acf.addAction === "function") {
		acf.addAction("append", function ($el) {
			var el = $el && $el[0] ? $el[0] : $el;
			if (el && el.querySelectorAll) initAll(el);
		});
	}
})();
