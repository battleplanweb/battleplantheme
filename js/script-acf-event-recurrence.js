/* Battle Plan Web Design — admin JS for the event editor's date fields:
   (1) toggles between the "Specific dates" field and the "Recurrence" field via the
       Date Type radio;
   (2) inside the recurrence field, shows/hides each control (.bp-rec-f) by frequency
       (data-when) and day-mode (data-mode);
   (3) injects the recurrence field's 50/50 layout CSS.
   Vanilla JS, no jQuery. */

(function () {
	"use strict";

	function injectCSS() {
		if (document.getElementById('bp-rec-css')) return;
		var s = document.createElement('style');
		s.id = 'bp-rec-css';
		s.textContent = [
			'.acf-field-event-recurrence{min-height:0!important}',
			'.bp-rec-cols{display:flex;flex-wrap:wrap;gap:0 28px}',
			'.bp-rec-col{flex:1 1 calc(50% - 14px);min-width:220px}',
			'.bp-rec-f{margin-bottom:12px}',
			'.bp-rec-sublabel{display:block;font-size:12px;color:#646970;margin:0 0 2px}',
			'.bp-rec-f select,.bp-rec-f input[type=number],.bp-rec-f input[type=date],.bp-rec-f input[type=time]{width:100%}',
			'.bp-rec-days{display:flex;flex-wrap:wrap;gap:6px 16px}',
			'.bp-rec-day{white-space:nowrap}',
			'.bp-rec-bottom{display:flex;flex-wrap:wrap;gap:0 20px;margin-top:8px}',
			'.bp-rec-bottom .bp-rec-f{flex:1 1 calc(50% - 10px)}'
		].join('');
		document.head.appendChild(s);
	}

	function acfField(key) { return document.querySelector('.acf-field[data-key="' + key + '"]'); }

	function dateType() {
		var f = acfField('field_bp_date_type');
		if (!f) return 'specific';
		var c = f.querySelector('input[type="radio"]:checked');
		return c ? c.value : 'specific';
	}

	function syncTop() {
		var recurring = dateType() === 'recurring';
		var ed = acfField('field_bp_event_dates');
		var er = acfField('field_bp_event_recurrence');
		if (ed) ed.style.display = recurring ? 'none' : '';
		if (er) er.style.display = recurring ? '' : 'none';
	}

	function syncInner() {
		var box = document.querySelector('.bp-rec');
		if (!box) return;
		var freqEl = box.querySelector('.bp-rec-freq');
		var modeEl = box.querySelector('.bp-rec-monthmode');
		var freq = freqEl ? freqEl.value : 'weekly';
		var mode = modeEl ? modeEl.value : 'weekday';

		box.querySelectorAll('.bp-rec-f').forEach(function (el) {
			var w = el.getAttribute('data-when');
			var m = el.getAttribute('data-mode');
			var okW = !w
				|| (w === 'weekly' && freq === 'weekly')
				|| (w === 'yearly' && freq === 'yearly')
				|| (w === 'month'  && freq !== 'weekly');
			var okM = !m || m === mode;
			el.style.display = (okW && okM) ? '' : 'none';
		});
	}

	function init() { injectCSS(); syncTop(); syncInner(); }

	document.addEventListener('DOMContentLoaded', init);

	document.addEventListener('change', function (e) {
		if (e.target.closest('.acf-field[data-key="field_bp_date_type"]')) syncTop();
		if (e.target.closest('.bp-rec')) syncInner();
	});

	// ACF can (re)render fields after load; re-run when it signals ready/append.
	if (window.acf && typeof acf.addAction === 'function') {
		acf.addAction('ready', init);
		acf.addAction('append', init);
	}
})();
