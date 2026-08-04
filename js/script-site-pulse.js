/* Battle Plan Web Design - Site Pulse JavaScript
--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Initialization
# Sidebar Navigation
# Mobile Sidebar
# Login
# Logout
# Reports — List
# Reports — Form
# Reports — Detail
# Review Panel
# Helpers
--------------------------------------------------------------*/

(function() {
'use strict';

const D = (typeof sitePulseData !== 'undefined') ? sitePulseData : {};
const $ = (sel, ctx) => (ctx || document).querySelector(sel);
const $$ = (sel, ctx) => [...(ctx || document).querySelectorAll(sel)];

// Shared edit/delete icons + a builder for the outlined icon-buttons used app-wide.
const ICON_EDIT = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
const ICON_DELETE = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';
const ICON_CAMERA = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-3px;margin-right:4px;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>';

const ICON_RECEIPT = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';
const ICON_CHECK = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
const ICON_ATTACH = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>';
const ICON_TASK = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>';
const ICON_MIC = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>';
const ICON_REPLY = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>';
const ICON_SEARCH = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>';
const ICON_CHART_BAR = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>';
const ICON_CHART_PIE = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>';

// Per-user chart style ('bar' | 'pie') for the distribution graphs, remembered server-side.
let _spChartStyle = (D.chartStyle === 'pie') ? 'pie' : 'bar';
let _spReviewStatsData = null;     // last data passed to renderReviewStats (for re-render on toggle)
let _spSurveySummaryData = null;   // last data passed to renderSurveysSummary

function spSetChartStyle(style) {
	if (style !== 'bar' && style !== 'pie') return;
	if (style === _spChartStyle) return;
	_spChartStyle = style;
	spAjax('site_pulse_set_chart_style', { style }).catch(() => {});
	if (_spReviewStatsData) renderReviewStats(_spReviewStatsData);
	if (_spSurveySummaryData) renderSurveysSummary(_spSurveySummaryData);
}

// Bar/Pie switch above a chart group — a joined segmented toggle. Selected segment = primary button
// colors, the other = secondary button colors. Delegated click handler (below) flips + saves.
function spChartToggle() {
	return '<div class="sp-chart-toggle-row"><div class="sp-chart-toggle">'
		+ `<button type="button" class="sp-chart-toggle-btn${_spChartStyle === 'bar' ? ' active' : ''}" data-chart="bar" title="Bar chart" aria-label="Bar chart">${ICON_CHART_BAR}</button>`
		+ `<button type="button" class="sp-chart-toggle-btn${_spChartStyle === 'pie' ? ' active' : ''}" data-chart="pie" title="Pie chart" aria-label="Pie chart">${ICON_CHART_PIE}</button>`
		+ '</div></div>';
}
document.addEventListener('click', (e) => {
	const b = e.target.closest('.sp-chart-toggle-btn');
	if (b) spSetChartStyle(b.dataset.chart);
});

// Render a distribution as bars (default) or a donut (per the user's choice). segs: [{label,pct,cls}].
function spDistChart(segs, opts) {
	if (!segs || !segs.length) return '';
	opts = opts || {};
	if (_spChartStyle === 'pie') return spPieChart(segs);
	const labelCls = opts.labelClass ? (' ' + opts.labelClass) : '';
	const rows = segs.map(s =>
		'<div class="sp-survey-bar-row">' +
		`<div class="sp-survey-bar-star${labelCls}">${esc(s.label)}</div>` +
		`<div class="sp-survey-bar-track"><div class="sp-survey-bar-fill ${s.cls}" style="width:${s.pct}%"></div></div>` +
		`<div class="sp-survey-bar-pct">${s.pct}%</div>` +
		'</div>'
	).join('');
	return `<div class="sp-survey-bars">${rows}</div>`;
}

// Donut chart (stroke-dasharray on stacked circles, pathLength 100 so values are percentages).
function spPieChart(segs) {
	const shown = segs.filter(s => s.pct > 0);
	if (!shown.length) return '';
	let offset = 0, slices = '';
	shown.forEach(s => {
		slices += `<circle class="sp-pie-slice ${s.cls}" cx="20" cy="20" r="16" fill="none" stroke="currentColor" stroke-width="8" pathLength="100" stroke-dasharray="${s.pct} ${100 - s.pct}" stroke-dashoffset="${-offset}"></circle>`;
		offset += s.pct;
	});
	const legend = shown.map(s =>
		'<div class="sp-pie-legend-row">' +
		`<span class="sp-pie-dot ${s.cls} unique"></span>` +
		`<span class="sp-pie-legend-label unique">${esc(s.label)}</span>` +
		`<span class="sp-pie-legend-pct unique">${s.pct}%</span></div>`
	).join('');
	return `<div class="sp-pie-wrap"><svg class="sp-pie-svg" viewBox="0 0 40 40" aria-hidden="true"><g transform="rotate(-90 20 20)">${slices}</g></svg><div class="sp-pie-legend">${legend}</div></div>`;
}

// 5→1 star distribution as percentage segments (shared by reviews + surveys).
function spStarSegments(dist) {
	if (!dist) return null;
	const total = [1, 2, 3, 4, 5].reduce((t, n) => t + (parseInt(dist[n], 10) || 0), 0);
	if (!total) return null;
	const segs = [];
	for (let star = 5; star >= 1; star--) {
		segs.push({ label: String(star), pct: Math.round((parseInt(dist[star], 10) || 0) / total * 100), cls: spRateClass(star) });
	}
	return segs;
}

// kind: 'edit' | 'delete'. cls = extra functional class(es); attrs = raw attribute string (data-*, disabled…).
function iconBtn(kind, cls = '', attrs = '') {
	const del = kind === 'delete';
	return `<button type="button" class="unique sp-btn sp-icon-btn ${del ? 'sp-icon-delete' : 'sp-icon-edit'} ${cls}" title="${del ? 'Delete' : 'Edit'}" aria-label="${del ? 'Delete' : 'Edit'}" ${attrs}>${del ? ICON_DELETE : ICON_EDIT}</button>`;
}

// Relative "last active" label from a unix timestamp (seconds, server UTC — comparable to Date.now).
function spLastActive(ts) {
	ts = parseInt(ts, 10);
	if (!ts) return '<span class="sp-text-secondary">Never</span>';
	const diff = Math.floor(Date.now() / 1000) - ts;
	let out;
	if (diff < 60) out = 'Just now';
	else if (diff < 3600) out = Math.floor(diff / 60) + 'm ago';
	else if (diff < 86400) out = Math.floor(diff / 3600) + 'h ago';
	else if (diff < 86400 * 7) out = Math.floor(diff / 86400) + 'd ago';
	else out = new Date(ts * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
	return esc(out);
}

// Report status pill — only a DRAFT (not yet submitted) warrants a pill. Submitted/reviewed are
// the normal end state and show no badge. Returns '' for anything that isn't a draft.
function reportStatusPill(status) {
	return status === 'draft' ? '<span class="unique sp-status-badge sp-status-draft">Draft</span>' : '';
}

// "View receipt" icon button for an expense row that has a receipt photo. cls = the section's
// functional class; the row's id is in data-id so the click can look up its receipt_url.
function receiptIconBtn(cls, id) {
	return `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-receipt ${cls}" title="View receipt" aria-label="View receipt" data-id="${id}">${ICON_RECEIPT}</button>`;
}

/* Shared stat-card builder (the at-a-glance metric tiles). Reused by any module that shows
   summary numbers — pass a label, a pre-formatted value, an icon key, and an optional tint
   ('success'|'warning'|'danger'). Styling lives in .sp-stat-tile / .sp-stat-icon (variable-driven). */
const SP_STAT_ICONS = {
	gauge:   '<path d="M12 13.5 15.5 10"/><path d="M4 18a8 8 0 1 1 16 0"/><circle cx="12" cy="13.8" r="1.1" fill="currentColor" stroke="none"/>',
	car:     '<path d="M5 17h14M3 13l2-6h14l2 6M5 17a2 2 0 0 0 4 0M15 17a2 2 0 0 0 4 0M3 13v4h18v-4"/>',
	trailer: '<rect x="1.5" y="7" width="13" height="9" rx="1"/><path d="M14.5 10H19l3 3v3h-7.5z"/><circle cx="6" cy="18" r="1.5"/><circle cx="18" cy="18" r="1.5"/>',
	ticket:  '<path d="M3 8a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4z"/><path d="M12 6.5v11"/>',
	wallet:  '<path d="M3 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v0H5a2 2 0 0 0-2 2z"/><path d="M3 8h16a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="16.5" cy="13.5" r="1.2" fill="currentColor" stroke="none"/>',
	clock:   '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
	items:   '<rect x="3" y="4.5" width="3.2" height="3.2" rx="0.6"/><rect x="3" y="10.4" width="3.2" height="3.2" rx="0.6"/><rect x="3" y="16.3" width="3.2" height="3.2" rx="0.6"/><path d="M9.8 6.1h11.2"/><path d="M9.8 12h11.2"/><path d="M9.8 17.9h11.2"/>',
	plane:   '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
};

function spStatIcon(key, tint) {
	const cls = `sp-tile-icon sp-stat-icon${tint ? ' is-' + tint : ''}`;
	// Prefer a framework icon registered via battleplan_icon_map (512-viewBox, fill-based,
	// inherits color). Fall back to the built-in 24-viewBox stroke set.
	const fw = D.icons && D.icons[key];
	if (fw) return `<div class="${cls}"><svg viewBox="0 0 512 512" fill="currentColor">${fw}</svg></div>`;
	return `<div class="${cls}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${SP_STAT_ICONS[key] || ''}</svg></div>`;
}

function spStatCard(label, value, iconKey, tint) {
	return `<div class="sp-tile sp-stat-tile"><div class="sp-stat-text"><div class="sp-tile-label sp-stat-label">${esc(label)}</div><div class="sp-tile-value sp-stat-value">${value}</div></div>${spStatIcon(iconKey, tint)}</div>`;
}

// Shared popup shell for the admin add/edit forms, so every "Add" opens a centered modal
// instead of an inline panel that can land below the fold. Returns the backdrop element to
// render into; the form's own card (.sp-report-form-wrap / .sp-tier-form) becomes the box.
function spFormModal() {
	closeFormModal();
	const backdrop = document.createElement('div');
	backdrop.id = 'sp-form-modal';
	backdrop.className = 'sp-modal-backdrop sp-form-modal-backdrop';
	document.body.appendChild(backdrop);
	backdrop.addEventListener('click', (e) => { if (e.target === backdrop) closeFormModal(); });
	return backdrop;
}
function closeFormModal() { document.getElementById('sp-form-modal')?.remove(); }

// A small multi-option confirm dialog (more than the two choices window.confirm allows). Renders its own
// overlay ABOVE any open form modal and resolves to the chosen option's value (null on cancel/backdrop).
// options: [{ label, value, class }]. Give the cancel option value null.
function spChoiceDialog({ title, message, options }) {
	return new Promise(resolve => {
		const back = document.createElement('div');
		back.className = 'sp-modal-backdrop sp-form-modal-backdrop';
		back.style.zIndex = '10000';
		let html = '<div class="sp-card sp-report-form-wrap sp-choice-dialog">';
		if (title) html += `<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${esc(title)}</h3></div>`;
		if (message) html += `<p class="sp-choice-msg">${esc(message)}</p>`;
		html += '<div class="sp-choice-actions">';
		(options || []).forEach((o, i) => {
			html += `<button type="button" class="unique sp-btn ${o.class || 'sp-btn-secondary'}" data-i="${i}">${esc(o.label)}</button>`;
		});
		html += '</div></div>';
		back.innerHTML = html;
		document.body.appendChild(back);
		markUniqueSpans(back);
		const done = (val) => { back.remove(); resolve(val); };
		back.addEventListener('click', (e) => { if (e.target === back) done(null); });
		$$('.sp-choice-actions .sp-btn', back).forEach(b => b.addEventListener('click', () => done((options[parseInt(b.dataset.i, 10)] || {}).value ?? null)));
	});
}


/*--------------------------------------------------------------
# Initialization
--------------------------------------------------------------*/

document.addEventListener('DOMContentLoaded', init);

function init() {
	markUniqueSpans();
	initGodMode();
	initSidebar();
	initMobileSidebar();
	initLogin();
	initLogout();
	initDashboardWidgets();
	initDashboardMessages();
	initMessages();
	spPushInit();
	initInstallCard();
	initReports();
	initReview();
	initAdmin();
	initMileage();
	initAdminMileage();
	initReviews();
	initAgencyReviews();
	initSurveys();
	initNewsSliders();
	restoreViewState();
	spNavInit();
	initClickableTagging();
}

/* ---- Global clickable tagging ----
   Add the marker class .sp-button to every clickable element so a single hover rule (.sp-button:hover)
   can style them all. "Clickable" = matches an interactive selector (button / link / [data-nav] /
   .sp-btn / [role=button]) OR carries its OWN cursor:pointer (not merely inherited from a clickable
   ancestor — that keeps the tag on the outer clickable, not every child span). Runs once on load and
   then on dynamically-rendered content via a MutationObserver, so new markup is covered automatically
   with no per-element edits. Skips the WordPress admin bar. */
const SP_CLICK_SELECTOR = 'button, a[href], [role="button"], [data-nav]';
// Never tag these: real buttons (.sp-btn keep their own hover), and classes that carry a clickable
// style but aren't actually clickable in every context (e.g. .sp-msg-members is a button in one
// place and a plain container in another).
const SP_NO_BUTTON_SELECTOR = '.sp-btn, .sp-msg-members, .sp-mileage-trailer-toggle, .sp-mileage-stop-trailer, .sp-mileage-end-trailer, .sp-radio-inline, .sp-recurring-check, .sp-appr-card, .sp-arch-row, .sp-appr-auto';
function spTagClickable(el) {
	if (!el || el.nodeType !== 1 || el.classList.contains('sp-button')) return;
	if (el.closest('#wpadminbar')) return;
	if (el.matches(SP_NO_BUTTON_SELECTOR)) return;

	let hit = el.matches(SP_CLICK_SELECTOR);
	if (!hit) {
		let cur;
		try { cur = getComputedStyle(el).cursor; } catch (e) { return; }
		if (cur === 'pointer') {
			const p = el.parentElement;
			let pc = '';
			try { pc = p ? getComputedStyle(p).cursor : ''; } catch (e) {}
			hit = pc !== 'pointer'; // own pointer, not inherited from a clickable ancestor
		}
	}
	if (hit) el.classList.add('sp-button');
}
function spTagClickables(root) {
	if (!root || root.nodeType !== 1) return;
	spTagClickable(root);
	const els = root.querySelectorAll('*');
	for (let i = 0; i < els.length; i++) spTagClickable(els[i]);
}
/* Tag every direct <div> child of a .sp-card-body as .sp-card-item (skips the transient loading
   spinner). Handles both a card-body inside the scanned subtree and a div added directly into one. */
function spTagCardItems(root) {
	if (!root || root.nodeType !== 1) return;
	const tagChildren = (body) => {
		for (let i = 0; i < body.children.length; i++) {
			const c = body.children[i];
			if (c.tagName === 'DIV' && !c.classList.contains('sp-loading') && !c.classList.contains('sp-tile')) c.classList.add('sp-card-item');
		}
	};
	if (root.matches && root.matches('.sp-card-body')) tagChildren(root);
	if (root.querySelectorAll) root.querySelectorAll('.sp-card-body').forEach(tagChildren);
	if (root.tagName === 'DIV' && !root.classList.contains('sp-loading') && !root.classList.contains('sp-tile') &&
		root.parentElement && root.parentElement.matches && root.parentElement.matches('.sp-card-body')) {
		root.classList.add('sp-card-item');
	}
}
/* The report/action-item/message forms reuse .sp-report-form-wrap both as a modal box (inside a
   .sp-form-modal-backdrop) and inline (the Reports panel). Tag ONLY the modal ones with .sp-modal so
   they get the standard modal-box styling; the inline form (not in a backdrop) is left alone. */
function spTagModalBoxes(root) {
	if (!root || root.nodeType !== 1) return;
	const tag = (el) => { if (el.closest('.sp-form-modal-backdrop')) el.classList.add('sp-modal'); };
	if (root.matches && root.matches('.sp-report-form-wrap')) tag(root);
	if (root.querySelectorAll) root.querySelectorAll('.sp-report-form-wrap').forEach(tag);
}
/* Add .checkbox-label / .radio-label to any <label> that directly contains a checkbox/radio, so one
   shared style can lay out the control + text. Covers .sp-reminder-toggle, .sp-msg-contact-pick, etc. */
function spTagCheckboxLabels(root) {
	if (!root || root.nodeType !== 1) return;
	const tag = (input, cls) => { const p = input.parentElement; if (p && p.tagName === 'LABEL') p.classList.add(cls); };
	const run = (sel, cls) => {
		if (root.matches && root.matches(sel)) tag(root, cls);
		if (root.querySelectorAll) root.querySelectorAll(sel).forEach(el => tag(el, cls));
	};
	run('input[type="checkbox"]', 'checkbox-label');
	run('input[type="radio"]', 'radio-label');
}
let _spTagQueue = [], _spTagScheduled = false;
function spScheduleTag(node) {
	_spTagQueue.push(node);
	if (_spTagScheduled) return;
	_spTagScheduled = true;
	requestAnimationFrame(() => {
		const q = _spTagQueue; _spTagQueue = []; _spTagScheduled = false;
		q.forEach(n => { spTagClickables(n); spTagCardItems(n); spTagModalBoxes(n); spTagCheckboxLabels(n); });
	});
}
function initClickableTagging() {
	spTagClickables(document.body);
	spTagCardItems(document.body);
	spTagModalBoxes(document.body); spTagCheckboxLabels(document.body);
	const obs = new MutationObserver(muts => {
		for (const m of muts) {
			for (let i = 0; i < m.addedNodes.length; i++) {
				if (m.addedNodes[i].nodeType === 1) spScheduleTag(m.addedNodes[i]);
			}
		}
	});
	obs.observe(document.body, { childList: true, subtree: true });
}

// Mouse drag-to-scroll for the horizontal "In the News" slider(s). Touch/trackpad scroll
// natively; this adds click-and-drag for a mouse and swallows the click if it was a drag so the
// article link doesn't open mid-drag.
function initNewsSliders(root) {
	(root || document).querySelectorAll('.ga-feed').forEach(feed => {
		if (feed._dragWired) return;
		feed._dragWired = true;
		let down = false, startX = 0, startScroll = 0, moved = false;

		feed.addEventListener('pointerdown', (e) => {
			if (e.pointerType && e.pointerType !== 'mouse') return;   // let touch/pen scroll natively
			if (e.button) return;                                     // primary button only
			down = true; moved = false;
			startX = e.clientX; startScroll = feed.scrollLeft;
			feed.classList.add('ga-grabbing');
			try { feed.setPointerCapture(e.pointerId); } catch (_) {}
		});
		feed.addEventListener('pointermove', (e) => {
			if (!down) return;
			const dx = e.clientX - startX;
			if (!moved && Math.abs(dx) > 4) moved = true;
			if (moved) { feed.scrollLeft = startScroll - dx; e.preventDefault(); }
		});
		const end = () => { if (down) { down = false; feed.classList.remove('ga-grabbing'); } };
		feed.addEventListener('pointerup', end);
		feed.addEventListener('pointercancel', end);
		feed.addEventListener('click', (e) => {
			if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
		}, true);
	});
}

/*--------------------------------------------------------------
# God Mode — Impersonation
--------------------------------------------------------------*/

function initGodMode() {
	const select = $('#sp-god-user-select');

	if (select) {
		select.addEventListener('change', async () => {
			const userId = select.value;
			try {
				const res = await spAjax('site_pulse_impersonate', { user_id: userId });
				if (res.success) window.location.reload();
			} catch (err) {
				alert('Error switching user.');
			}
		});
	}
}

function markUniqueSpans(root) {
	(root || document.getElementById('sp-app') || document.getElementById('sp-login-wrap') || document).querySelectorAll('span').forEach(function(el) {
		el.classList.add('unique');
	});
}

// Brief, unobtrusive "Saved" confirmation for auto-save fields (replaces explicit Save buttons).
let spFlashTimer = null;
function spFlash(msg = 'Saved') {
	let el = document.getElementById('sp-flash');
	if (!el) {
		el = document.createElement('div');
		el.id = 'sp-flash';
		el.className = 'sp-flash';
		document.body.appendChild(el);
	}
	el.textContent = msg;
	// Force reflow so re-triggering the animation works on rapid successive saves.
	void el.offsetWidth;
	el.classList.add('show');
	clearTimeout(spFlashTimer);
	spFlashTimer = setTimeout(() => el.classList.remove('show'), 1600);
}


/*--------------------------------------------------------------
# Sidebar Navigation
--------------------------------------------------------------*/

function initSidebar() {
	$$('.sp-nav-item').forEach(btn => {
		btn.addEventListener('click', () => {
			const nav = btn.dataset.nav;
			if (!nav) return;

			const group = btn.closest('.sp-nav-group');
			const hasChildren = group && group.querySelector('.sp-nav-children');

			if (hasChildren) {
				// Accordion: opening one submenu collapses the rest (only one open at a time).
				const willExpand = !group.classList.contains('expanded');
				$$('.sp-nav-group').forEach(g => { if (g !== group) g.classList.remove('expanded'); });
				group.classList.toggle('expanded', willExpand);
			} else {
				activatePanel(nav);
				closeMobileSidebar();
			}
		});
	});

	$$('.sp-nav-child').forEach(btn => {
		btn.addEventListener('click', () => {
			const nav = btn.dataset.nav;
			if (!nav) return;
			// Forms: a sub-folder item sets the category panel's sub-folder filter before it opens;
			// the category item itself (no data-sub) resets the filter to "all".
			if (nav.indexOf('forms-') === 0) {
				const cat = nav.slice(6);
				if (!_spForms[cat]) _spForms[cat] = { items: [], sort: { col: 'date', dir: 'desc' }, search: '', filter: '', canUpload: false, selected: new Set() };
				_spForms[cat].filter = btn.dataset.sub || '';
			}
			// Set the current report BEFORE activatePanel so the Grant/Restrict bar (updated inside
			// activatePanel) resolves this tab's per-report view cap.
			const action = btn.dataset.action;
			if (action === 'review-template') { reviewTemplateId = btn.dataset.templateId || ''; reviewTemplateName = (btn.textContent || '').trim(); }
			activatePanel(nav);
			// Highlight the child that was actually clicked (several children can share a panel
			// slug, e.g. GM Reports / Supervisor Reports both use reports-review).
			$$('.sp-nav-child').forEach(b => b.classList.remove('active'));
			btn.classList.add('active');
			closeMobileSidebar();
			// Action children open a form / switch the review type (after their panel is active).
			if (action === 'new-report') showReportForm();
			else if (action === 'mileage-add') showMileageForm();
			else if (action === 'review-template') { applyReviewLocationDefault(); closeReportDetail('review'); loadReviewReports(); }
		});
	});
}

// On mobile, relocate ONLY the notification bell from the topbar into the mobile header's top row
// (white icon) — on its own it gets the whole tap target. Sign Out stays in the sidebar menu on
// phones (off the cramped top bar). On desktop both live in the sidebar footer (bell, then logout).
// Moving the actual elements keeps their wiring + the unread badge intact.
function spPlaceMobileHeaderIcons() {
	const bell = document.getElementById('sp-notification-btn');
	const logout = document.getElementById('sp-logout-btn-top');
	const mobileSlot = document.getElementById('sp-mobile-header-actions');
	const sidebarSlot = document.getElementById('sp-sidebar-actions');
	if (!bell || !logout || !mobileSlot || !sidebarSlot) return;
	const isMobile = window.matchMedia('(max-width: 860px)').matches;
	// Sign Out always sits in the sidebar footer; the bell joins it there on desktop, or moves to the
	// mobile header on phones. (Re-appending logout keeps the bell·logout order right after a resize.)
	(isMobile ? mobileSlot : sidebarSlot).appendChild(bell);
	sidebarSlot.appendChild(logout);
}

// Collapsing mobile header: show the logo at the top, swap to the page title once scrolled down.
function spInitMobileHeaderScroll() {
	const header = document.getElementById('sp-mobile-header');
	if (!header) return;

	// Tapping the page title jumps back to the top of the page.
	const title = document.getElementById('sp-mobile-page-title');
	if (title && !title._spWired) {
		title._spWired = true;
		title.setAttribute('title', 'Back to top');
		title.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
	}

	let ticking = false;
	const update = () => {
		ticking = false;
		const y = window.scrollY || document.documentElement.scrollTop || 0;
		header.classList.toggle('sp-scrolled', y > 40);
	};
	window.addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
	update();
}

// Keep the mobile header's page title in sync with the active panel (read from its header crumb/h2).
function spUpdateMobileTitle() {
	const el = document.getElementById('sp-mobile-page-title');
	if (!el) return;
	const panel = document.querySelector('.sp-panel.active');
	const header = panel && panel.querySelector('.sp-panel-header');
	if (!header) return;
	const t = (header.querySelector('.sp-crumb-current') || header.querySelector('h2'));
	if (t && t.textContent.trim()) el.textContent = t.textContent.trim();
}

function activatePanel(panelId) {
	$$('.sp-panel').forEach(p => p.classList.remove('active'));
	const panel = $(`#sp-panel-${panelId}`);
	if (panel) panel.classList.add('active');
	spUpdateMobileTitle();

	$$('.sp-nav-item').forEach(b => b.classList.remove('active'));
	$$('.sp-nav-child').forEach(b => b.classList.remove('active'));

	const navBtn = $(`[data-nav="${panelId}"]`);
	if (navBtn) {
		navBtn.classList.add('active');

		const group = navBtn.closest('.sp-nav-group');
		if (group) {
			// Accordion: navigating into a submenu group collapses any other open submenu.
			if (group.querySelector('.sp-nav-children')) {
				$$('.sp-nav-group').forEach(g => { if (g !== group) g.classList.remove('expanded'); });
			}
			group.classList.add('expanded');
			const parentBtn = group.querySelector('.sp-nav-item');
			if (parentBtn) parentBtn.classList.add('active');
		}
	}

	if (panelId === 'analytics') {
		populateAnalyticsFilters();
		loadAnalytics(true);
		initReportsDigestBtn();
	}
	if (panelId === 'mileage-map') {
		loadMileageMap();
	}
	if (panelId === 'mileage-tolls') {
		initTollReconcile();
	}
	if (panelId === 'reviews') {
		loadReviews();
		loadReviewStats();
	}
	if (panelId === 'review-settings') {
		loadReviewSettings();
	}
	if (panelId === 'review-trends') {
		loadReviewTrends();
	}
	if (panelId === 'ai-assistant') {
		initAiAssistant();
	}
	if (panelId === 'agency-reviews') {
		loadAgencyReviews();
	}
	if (panelId === 'surveys') {
		initSurveys();
		loadSurveys();
	}
	if (panelId === 'directory') {
		initDirectory();
		loadDirectory();
	}
	if (panelId === 'customer-connect-customers') { initCcCustomers(); loadCcCustomers(); }
	if (panelId === 'customer-connect-send')      { initCcSend(); loadCcSegments(); }
	if (panelId === 'customer-connect-schedule')  { initCcSchedule(); loadCcSchedule(); }
	if (panelId === 'emails') {
		initEmails();
		loadEmails();
	}
	if (panelId === 'import-reports') {
		initImportReports();
	}
	if (panelId === 'import-cards') {
		initImportCards();
	}
	if (panelId === 'messages') { initMessagesPanel(); } else { stopMsgPolling(); }
	if (panelId === 'customer-messages') { initCustomerMessages(); } else { stopCmPolling(); }
	if (panelId === 'vehicle-expenses') {
		initVehicleExpenses();
	}
	if (panelId === 'business-meals') {
		initBusinessMeals();
	}
	if (panelId === 'competitive-shopping') {
		initCompetitiveShopping();
	}
	if (panelId === 'other-expenses') {
		initOtherExpenses();
	}
	if (panelId === 'travel-expenses') {
		initTravelExpenses();
	}
	if (panelId === 'expense-report') {
		initExpenseReport();
	}
	if (panelId === 'approve-expense') {
		initApproveExpense();
	}
	if (panelId === 'approved-expense') {
		initApprovedExpense();
	}
	if (panelId.indexOf('forms-') === 0) {
		initFormsPanel(panelId);
	}
	// Returning to a list view closes any open form/detail in that section, so the nav
	// item always lands on the main list (e.g. My Mileage while on Add-a-Day).
	if (panelId === 'mileage' && typeof hideMileageForm === 'function') hideMileageForm();
	if (panelId === 'reports-my' && typeof hideReportForm === 'function') hideReportForm();

	// God + System Admin only: refresh the top-of-page Grant/Restrict bar for this page.
	spUpdatePageAccessBar(panelId);

	// A new panel is effectively a new page — jump back to the top so the user starts at its header
	// instead of wherever they'd scrolled to in the previous panel (most noticeable on mobile).
	window.scrollTo(0, 0);
	const spMain = document.getElementById('sp-main');
	if (spMain) spMain.scrollTop = 0;

	saveViewState({ panel: panelId });
	spNavPush(panelId);
}

/* ---- Per-page Grant/Restrict bar (God + System Admin only) — edits the same access-role caps + overrides ---- */

let _spPaOpen = false; // remembers whether the "Grant Permissions" bar is expanded, across panel nav

function spUpdatePageAccessBar(panelId) {
	if (!D.isSystemAdmin) return;
	const main = document.getElementById('sp-main');
	if (!main) return;
	let bar = document.getElementById('sp-page-access-bar');
	if (!bar) {
		bar = document.createElement('div');
		bar.id = 'sp-page-access-bar';
		bar.className = 'sp-page-access-bar';
		bar.hidden = true;
		main.insertBefore(bar, main.firstChild);
		// Click anywhere outside a picker closes any open picker panel (bound once, document-level so a
		// background click reaches it; the toggle button stops propagation so its own click doesn't close it).
		document.addEventListener('click', (e) => { if (!e.target.closest('.sp-pa-picker')) $$('.sp-pa-panel').forEach(p => p.hidden = true); });
	}
	bar.dataset.page = panelId;
	spLoadPageAccessBar(panelId, bar);
}

async function spLoadPageAccessBar(panelId, bar) {
	try {
		// Report tabs share one panel slug; pass which report so the server resolves its per-report cap.
		const params = { page: panelId };
		if (panelId === 'reports-review') {
			let tid = reviewTemplateId;
			if (!tid) { const b = $('.sp-nav-child[data-action="review-template"].active') || $('.sp-nav-child[data-action="review-template"]'); if (b) tid = b.dataset.templateId || ''; }
			params.template_id = tid;
		}
		const res = await spAjax('site_pulse_admin_page_access_get', params);
		if (bar.dataset.page !== panelId) return; // navigated away mid-request
		if (!res.success || !res.data.has_cap) { bar.hidden = true; bar.innerHTML = ''; return; }
		spRenderPageAccessBar(bar, panelId, res.data);
		bar.hidden = false;
	} catch (e) { bar.hidden = true; }
}

function spRenderPageAccessBar(bar, panelId, d) {
	// One row per PERMISSION (a page may gate on several, e.g. View + Manage). Each row: "Permission: X"
	// + a Roles picker + a People picker. Checkbox lists like the New Message / action-item people picker.
	const pickerHtml = (kind, label, items, valKey, cap) => {
		const nOn = items.filter(i => i.has).length;
		// Granted first (checked, alphabetical), then the rest (unchecked, alphabetical). Sorted at
		// render time only, so rows don't jump around as you check/uncheck; regroups on next open/load.
		const sorted = [...items].sort((a, b) =>
			(!!b.has - !!a.has) || (a.label || a.name || '').localeCompare(b.label || b.name || '')
		);
		const rows = sorted.map(i =>
			`<label class="sp-pa-row"><input type="checkbox" class="sp-pa-cb" data-kind="${kind}" data-cap="${esc(cap)}" value="${i[valKey]}"${i.has ? ' checked' : ''}><span class="sp-pa-name">${esc(i.label || i.name)}</span></label>`
		).join('');
		return '<div class="sp-pa-picker">'
			+ `<button type="button" class="sp-select sp-pa-toggle">${esc(label)}${nOn ? ` · ${nOn}` : ''}</button>`
			+ '<div class="sp-pa-panel" hidden>'
			+ `<input type="text" class="sp-input sp-pa-search" placeholder="Search…">`
			+ `<div class="sp-pa-list">${rows}</div>`
			+ '</div></div>';
	};

	let rowsHtml = '';
	(d.permissions || []).forEach(p => {
		rowsHtml += '<div class="sp-pa-inner">';
		rowsHtml += `<span class="sp-pa-label">${esc(p.cap_label)}</span>`;
		rowsHtml += pickerHtml('role', 'Roles', p.roles, 'id', p.cap);
		rowsHtml += pickerHtml('user', 'People', p.users, 'user_id', p.cap);
		rowsHtml += '</div>';
	});

	// Collapse the whole bar behind one "Grant Permissions" button — it's God/System-Admin only and was
	// obnoxious always-open, especially on mobile. _spPaOpen (module-level) remembers the open/closed
	// choice across panel navigation so it doesn't re-collapse mid-task.
	const caret = '<svg class="sp-pa-caret" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
	const nPerm = (d.permissions || []).length;
	bar.innerHTML =
		`<button type="button" class="sp-pa-master-toggle${_spPaOpen ? ' open' : ''}" aria-expanded="${_spPaOpen ? 'true' : 'false'}">`
		+ `<span class="sp-pa-master-label">Grant Permissions${nPerm ? ` · ${nPerm}` : ''}</span>`
		+ caret
		+ '</button>'
		+ `<div class="sp-pa-body"${_spPaOpen ? '' : ' hidden'}>${rowsHtml}</div>`;

	const master = $('.sp-pa-master-toggle', bar);
	const body = $('.sp-pa-body', bar);
	master?.addEventListener('click', (e) => {
		e.stopPropagation();
		_spPaOpen = !_spPaOpen;
		if (body) body.hidden = !_spPaOpen;
		master.classList.toggle('open', _spPaOpen);
		master.setAttribute('aria-expanded', _spPaOpen ? 'true' : 'false');
		if (!_spPaOpen) $$('.sp-pa-panel', bar).forEach(p => p.hidden = true); // collapsing also closes any open picker
	});

	const setAccess = async (kind, cap, id, grant) => {
		try {
			const r = await spAjax('site_pulse_admin_page_access_set', { page: panelId, cap, target_type: kind, target_id: id, grant: grant ? 1 : 0 });
			if (!r.success) { alert(r.data?.message || 'Error saving.'); return false; }
		} catch (e) { alert('Error saving.'); return false; }
		return true;
	};

	// Each picker: toggle its panel (opening one closes the others), filter by search, grant/restrict live.
	$$('.sp-pa-picker', bar).forEach(picker => {
		const toggle = $('.sp-pa-toggle', picker);
		const panel = $('.sp-pa-panel', picker);
		const search = $('.sp-pa-search', picker);
		const baseLabel = (toggle.textContent || '').split(' · ')[0];
		toggle?.addEventListener('click', (e) => {
			e.stopPropagation();
			const wasHidden = panel.hidden;
			$$('.sp-pa-panel', bar).forEach(p => p.hidden = true);
			panel.hidden = !wasHidden;
			if (!panel.hidden) search?.focus();
		});
		search?.addEventListener('input', () => {
			const q = search.value.toLowerCase();
			$$('.sp-pa-row', picker).forEach(l => { l.style.display = (l.textContent || '').toLowerCase().includes(q) ? '' : 'none'; });
		});
		$$('.sp-pa-cb', picker).forEach(cb => cb.addEventListener('change', async () => {
			cb.disabled = true;
			const ok = await setAccess(cb.dataset.kind, cb.dataset.cap, cb.value, cb.checked);
			cb.disabled = false;
			if (!ok) { cb.checked = !cb.checked; return; }
			const n = $$('.sp-pa-cb:checked', picker).length;
			toggle.textContent = baseLabel + (n ? ` · ${n}` : '');
		}));
	});
}

function saveViewState(state) {
	const current = JSON.parse(localStorage.getItem('sp_view_state') || '{}');
	Object.assign(current, state);
	current.userId = D.userId;
	localStorage.setItem('sp_view_state', JSON.stringify(current));
}

function restoreViewState() {
	try {
		// A deep link (e.g. the mileage reminder email: ?sp_panel=mileage) wins over saved state.
		const urlPanel = new URLSearchParams(location.search).get('sp_panel');
		if (urlPanel && $(`#sp-panel-${urlPanel}`)) { activatePanel(urlPanel); return; }

		const state = JSON.parse(localStorage.getItem('sp_view_state') || '{}');
		if (state.userId != D.userId) return;
		if (state.panel) {
			activatePanel(state.panel);
		}
	} catch (e) {}
}


/*--------------------------------------------------------------
# Back-Button History (Android PWA)
--------------------------------------------------------------
Installed as a standalone PWA, the app launches with a single browser-history
entry, so Android's Back button exits instead of returning to the previous
panel. We keep our own history stack: activatePanel() pushes an entry per
panel change, and Back (popstate) first dismisses any open layer (modal /
mobile sidebar / report detail), otherwise steps back to the previous panel.
Back on the root panel still exits, matching native-app behavior. */

let _spNavReady = false;   // history seeded; safe to push
let _spPopping = false;    // inside a popstate restore — suppress pushes

function spCurrentPanel() {
	const p = document.querySelector('.sp-panel.active');
	return p ? p.id.replace(/^sp-panel-/, '') : '';
}

function spNavInit() {
	// Seed the current panel as the root entry so the first Back has a target.
	history.replaceState({ sp: true, panel: spCurrentPanel(), root: true }, '');
	_spNavReady = true;
	window.addEventListener('popstate', spNavOnPop);
}

function spNavPush(panelId) {
	if (!_spNavReady || _spPopping) return;
	const st = history.state;
	if (st && st.sp && st.panel === panelId) return; // don't stack the same panel twice
	history.pushState({ sp: true, panel: panelId }, '');
}

// Dismiss the top-most open layer. Returns true if something was closed.
function spCloseTopLayer() {
	// Mobile sidebar.
	const sidebar = document.getElementById('sp-sidebar');
	if (sidebar && sidebar.classList.contains('open')) { closeMobileSidebar(); return true; }

	// Any modal backdrop (form / directory / mileage / receipt / matrix …) — these are appended
	// to <body>, so the last one is always the top-most. Removing the node matches how each closes.
	const backs = document.querySelectorAll('.sp-modal-backdrop');
	if (backs.length) { backs[backs.length - 1].remove(); document.body.style.overflow = ''; return true; }

	// Report / review detail: an inline view that hides the list while open. Scope to the active
	// panel so a detail left open in a since-switched-away panel isn't mistaken for the top layer.
	const active = document.querySelector('.sp-panel.active');
	if (active) {
		if (active.querySelector('#sp-report-detail-wrap:not(.sp-hidden)')) { closeReportDetail(''); return true; }
		if (active.querySelector('#sp-review-detail-wrap:not(.sp-hidden)')) { closeReportDetail('review'); return true; }
	}

	return false;
}

function spNavOnPop(e) {
	// Back first closes an open layer; re-push the current panel so the stack depth (and the
	// next Back) is preserved — the gesture is "spent" dismissing the layer, not changing panels.
	if (spCloseTopLayer()) {
		history.pushState({ sp: true, panel: spCurrentPanel() }, '');
		return;
	}
	const st = e.state;
	if (st && st.sp && st.panel) {
		_spPopping = true;
		activatePanel(st.panel);
		_spPopping = false;
	}
	// No sp state → popped past our root; let the browser / PWA exit.
}


/*--------------------------------------------------------------
# Mobile Sidebar
--------------------------------------------------------------*/

function initMobileSidebar() {
	const hamburger = $('#sp-hamburger');
	const overlay = $('#sp-overlay');
	const closeBtn = $('#sp-sidebar-close');

	if (hamburger) {
		hamburger.addEventListener('click', openMobileSidebar);
	}
	if (overlay) {
		overlay.addEventListener('click', closeMobileSidebar);
	}
	if (closeBtn) {
		closeBtn.addEventListener('click', closeMobileSidebar);
	}
}

function openMobileSidebar() {
	const sidebar = $('#sp-sidebar');
	const overlay = $('#sp-overlay');
	if (sidebar) sidebar.classList.add('open');
	if (overlay) overlay.classList.add('active');
	document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
	const sidebar = $('#sp-sidebar');
	const overlay = $('#sp-overlay');
	if (sidebar) sidebar.classList.remove('open');
	if (overlay) overlay.classList.remove('active');
	document.body.style.overflow = '';
}


/*--------------------------------------------------------------
# Login
--------------------------------------------------------------*/

function initLogin() {
	const form = $('#sp-login-form');
	if (!form) return;

	// Resolve the inputs by id, falling back to name and scoped to the form — resilient to
	// older login markup that renders the inputs without ids (which made #sp-username/#sp-password
	// null and threw before the request ever fired).
	const userInput = $('#sp-username', form) || $('[name="username"]', form);
	const passInput = $('#sp-password', form) || $('[name="password"]', form);

	const toggleBtn = $('.sp-toggle-password', form);
	if (toggleBtn && passInput) {
		const eyeOpen = $('.sp-eye-open', toggleBtn);
		const eyeClosed = $('.sp-eye-closed', toggleBtn);
		// Drive visibility with inline display (overrides any framework svg rule that defeats
		// the [hidden] attribute, which was leaving both eyes showing). Open eye = "click to
		// reveal" (password hidden); slashed eye = "click to hide" (password visible).
		const syncEyes = () => {
			const visible = passInput.type === 'text';
			if (eyeOpen)   eyeOpen.style.display   = visible ? 'none' : '';
			if (eyeClosed) eyeClosed.style.display = visible ? '' : 'none';
		};
		syncEyes();
		toggleBtn.addEventListener('click', () => {
			passInput.type = passInput.type === 'password' ? 'text' : 'password';
			toggleBtn.setAttribute('aria-label', passInput.type === 'text' ? 'Hide password' : 'Show password');
			syncEyes();
		});
	}

	form.addEventListener('submit', async (e) => {
		e.preventDefault();
		const errEl = $('#sp-login-error');
		const submitBtn = $('#sp-login-submit');
		const btnText = $('.btn-text', submitBtn);
		const btnLoad = $('.btn-loading', submitBtn);

		if (!userInput || !passInput) {
			if (errEl) errEl.textContent = 'Login form failed to load. Please refresh and try again.';
			return;
		}

		if (errEl) errEl.textContent = '';
		if (submitBtn) submitBtn.disabled = true;
		if (btnText) btnText.hidden = true;
		if (btnLoad) btnLoad.hidden = false;

		try {
			const res = await spAjax('site_pulse_login', {
				username: userInput.value.trim(),
				// NOT "password": WP Engine's WAF strips/blocks a POST field named `password`
				// on non-login endpoints (admin-ajax.php), which kills every login attempt.
				sp_pass: passInput.value,
			});

			if (res.success && res.data.redirect) {
				window.location.href = res.data.redirect;
			} else {
				errEl.textContent = res.data?.message || 'Login failed.';
				submitBtn.disabled = false;
				if (btnText) btnText.hidden = false;
				if (btnLoad) btnLoad.hidden = true;
			}
		} catch (err) {
			console.error('Site Pulse login error:', err);
			errEl.textContent = 'An error occurred. Please try again.';
			submitBtn.disabled = false;
			if (btnText) btnText.hidden = false;
			if (btnLoad) btnLoad.hidden = true;
		}
	});
}


/*--------------------------------------------------------------
# Logout
--------------------------------------------------------------*/

function initLogout() {
	$$('#sp-logout-btn, #sp-logout-btn-top').forEach(btn => {
		btn.addEventListener('click', async () => {
			try {
				const res = await spAjax('site_pulse_logout', {});
				if (res.success && res.data.redirect) {
					window.location.href = res.data.redirect;
				}
			} catch (err) {
				window.location.href = '/site-pulse-login/';
			}
		});
	});

	initNotifications();
	spPlaceMobileHeaderIcons();   // bell + Sign Out into the mobile header (top-right) on small screens
	window.matchMedia('(max-width: 860px)').addEventListener('change', spPlaceMobileHeaderIcons);
	spUpdateMobileTitle();        // sync the mobile header title with whatever panel ended up active
	spInitMobileHeaderScroll();   // logo at top → page title once scrolled
}


/*--------------------------------------------------------------
# Notifications
--------------------------------------------------------------*/

function initNotifications() {
	const btn = $('#sp-notification-btn');
	const panel = $('#sp-notification-panel');
	if (!btn || !panel) return;

	btn.addEventListener('click', (e) => {
		e.stopPropagation();
		const isOpen = !panel.hidden;
		panel.hidden = isOpen;
		if (!isOpen) loadNotifications();
	});

	document.addEventListener('click', (e) => {
		if (!panel.hidden && !panel.contains(e.target) && e.target !== btn) {
			panel.hidden = true;
		}
	});

	$('#sp-mark-all-read')?.addEventListener('click', async () => {
		try {
			await spAjax('site_pulse_mark_notifications_read', {});
			loadNotifications();
			updateNotificationBadge(0);
		} catch (err) {}
	});

	const tick = () => {
		loadNotificationCount();
		loadPendingMileageCount();
	};
	tick();
	setInterval(tick, 60000);

	// When a push lands while the app is open, the service worker pings us — refresh the counts
	// immediately so the bell + app badge reflect the new activity without waiting for the poll.
	if (navigator.serviceWorker) {
		navigator.serviceWorker.addEventListener('message', (e) => {
			if (e.data && e.data.type === 'sp-activity') {
				loadNotificationCount();
				if (typeof loadMsgUnread === 'function') loadMsgUnread();
			}
		});
	}

	// Opening/returning to the app dismisses any delivered push notifications still sitting in the OS
	// notification shade. Android stamps the app icon with its OWN badge for undismissed notifications
	// (separate from the Web Badging count) — clearing them here removes that lingering "1".
	spClearDeliveredNotifications();
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'visible') spClearDeliveredNotifications();
	});
}

function spClearDeliveredNotifications() {
	if (!navigator.serviceWorker) return;
	// Authoritative path: ask the SW to dismiss its notifications + clear the badge (it owns them, and
	// on Android the launcher badge is the OS notification count — only closing them clears it).
	try {
		if (navigator.serviceWorker.controller) navigator.serviceWorker.controller.postMessage({ type: 'sp-clear' });
	} catch (e) {}
	// Fallback: also close from the page (covers the brief window before a controller is claimed).
	if (navigator.serviceWorker.ready) {
		navigator.serviceWorker.ready
			.then((reg) => reg.getNotifications ? reg.getNotifications() : [])
			.then((ns) => ns.forEach((n) => n.close()))
			.catch(() => {});
	}
}

async function loadNotificationCount() {
	try {
		const res = await spAjax('site_pulse_get_unread_count', {});
		if (res.success) {
			updateNotificationBadge(res.data.count || 0);
		}
	} catch (err) {}
}

// `count` = unread SYSTEM notifications (messages aren't notifications — they have their own count).
// Drives both the bell badge and the notification half of the app-icon badge (icon = this + messages).
function updateNotificationBadge(count) {
	_spBellCount = count || 0;
	_spNotifBadgeCount = count || 0;
	spUpdateAppBadge();
	const badge = $('#sp-notification-badge');
	if (!badge) return;
	if (count > 0) {
		badge.textContent = count > 99 ? '99+' : count;
		badge.hidden = false;
	} else {
		badge.hidden = true;
	}
}

// PWA home-screen icon badge (Web Badging API) — reflects unread messages + non-message
// notifications (messages counted once, not twice) whenever the app is running. Updated on every
// unread poll. Silently a no-op where unsupported / not installed.
let _spBellCount = 0, _spMsgCount = 0, _spNotifBadgeCount = 0;
function spUpdateAppBadge() {
	const total = (_spNotifBadgeCount || 0) + (_spMsgCount || 0);
	// Prefer the service worker: on Android the launcher badge only updates reliably from the SW
	// context (the page's own setAppBadge/clearAppBadge silently does nothing on many installs).
	try {
		if (navigator.serviceWorker && navigator.serviceWorker.controller) {
			navigator.serviceWorker.controller.postMessage({ type: 'sp-badge', count: total });
			return;
		}
	} catch (e) {}
	// Fallback (desktop / no controller yet): set it straight from the page.
	if (!('setAppBadge' in navigator)) return;
	try {
		if (total > 0) navigator.setAppBadge(total);
		else navigator.clearAppBadge();
	} catch (e) {}
}

async function loadNotifications() {
	const list = $('#sp-notification-list');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_get_notifications', {});
		if (!res.success) { list.innerHTML = '<div class="sp-notification-empty">Error loading.</div>'; return; }

		const notifications = res.data.notifications || [];
		if (notifications.length === 0) {
			list.innerHTML = '<div class="sp-notification-empty">No notifications.</div>';
			return;
		}

		list.innerHTML = notifications.map(n => {
			const unread = n.is_read == 0 ? ' unread' : '';
			return `<div class="sp-notification-item${unread}" data-id="${n.id}" data-related-id="${n.related_id || ''}" data-related-type="${n.related_type || ''}" data-ntype="${n.type || ''}">
				<div class="sp-notification-message">${esc(n.message)}</div>
				<div class="sp-notification-time">${timeAgo(n.created_at)}</div>
			</div>`;
		}).join('');

		markUniqueSpans(list);

		$$('.sp-notification-item', list).forEach(item => {
			item.addEventListener('click', async () => {
				const id = item.dataset.id;
				const relatedId = item.dataset.relatedId;
				const relatedType = item.dataset.relatedType;

				// Clicking dismisses the notification — archive it (server) and drop it from the list.
				try { await spAjax('site_pulse_mark_notification_read', { id: id }); } catch (err) {}
				item.remove();
				if (!list.querySelector('.sp-notification-item')) {
					list.innerHTML = '<div class="sp-notification-empty">No notifications.</div>';
				}
				loadNotificationCount();

				// Navigate to the related content
				$('#sp-notification-panel').hidden = true;
				const ntype = item.dataset.ntype || '';

				if (ntype === 'action_items' || ntype === 'action_resolved' || relatedType === 'action_item') {
					activatePanel('action-items');
				} else if (relatedType === 'report' && relatedId) {
					activatePanel('reports-review');
					showReportDetail(relatedId, 'review');
				} else if (relatedType === 'survey') {
					activatePanel('surveys');
				} else if (relatedType === 'expense_period') {
					activatePanel('expense-report');
				} else if (relatedType === 'conversation' && relatedId) {
					activatePanel('messages');
					openMsgConversation(parseInt(relatedId, 10));
				}
			});
		});
	} catch (err) {
		list.innerHTML = '<div class="sp-notification-empty">Error loading.</div>';
	}
}

function timeAgo(dateStr) {
	if (!dateStr) return '';
	const now = new Date();
	const date = new Date(dateStr.replace(' ', 'T'));
	const diff = Math.floor((now - date) / 1000);

	if (diff < 60) return 'just now';
	if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
	if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
	if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
	return formatDate(dateStr.split(' ')[0]);
}


/*--------------------------------------------------------------
# Dashboard Widgets
--------------------------------------------------------------*/

function initDashboardWidgets() {
	const grid = $('#sp-dashboard-widgets');

	// Load only the widgets that are currently visible (hidden ones load when added).
	if (grid) $$('.sp-widget:not([hidden])', grid).forEach(w => loadWidget(w.dataset.widget));
	else { // fallback for older markup
		if ($('#sp-widget-reports-body')) loadWidgetReports();
		if ($('#sp-widget-actions-body')) loadWidgetActions();
		if ($('#sp-widget-mileage-body')) loadWidgetMileage();
	}

	$$('.sp-widget-link').forEach(btn => {
		btn.addEventListener('click', () => activatePanel(btn.dataset.nav));
	});

	if (!grid) return;

	spRefreshAddWidget();
	spInitWidgetDrag(grid);

	// Remove a widget → hide it, refresh the picker, save.
	grid.addEventListener('click', (e) => {
		const rm = e.target.closest('.sp-widget-remove');
		if (!rm) return;
		const w = rm.closest('.sp-widget');
		if (!w) return;
		w.hidden = true;
		spRefreshAddWidget();
		spSaveDashboardLayout();
	});

	// Add a widget from the picker → unhide, move to the end, load it, save.
	const addSel = $('#sp-dash-add');
	if (addSel) addSel.addEventListener('change', () => {
		const id = addSel.value;
		if (!id) return;
		const w = grid.querySelector(`.sp-widget[data-widget="${id}"]`);
		if (w) { w.hidden = false; grid.appendChild(w); loadWidget(id); }
		addSel.value = '';
		spRefreshAddWidget();
		spSaveDashboardLayout();
	});
}

// Fill (or refill) the body of one widget by id. Alerts is server-rendered (shortcode) — no loader.
function loadWidget(id) {
	if (id === 'reports' && $('#sp-widget-reports-body')) loadWidgetReports();
	else if (id === 'actions' && $('#sp-widget-actions-body')) loadWidgetActions();
	else if (id === 'mileage' && $('#sp-widget-mileage-body')) loadWidgetMileage();
	else if (id === 'chat' && $('#sp-widget-chat-body')) loadWidgetChat();
	else if (id === 'feedback' && $('#sp-widget-feedback-body')) loadWidgetFeedback();
	else if (id === 'directory' && $('#sp-widget-directory')) loadWidgetDirectory();
	else if (id === 'pastdue' && $('#sp-widget-pastdue-body')) loadWidgetPastDue();
}

// Past-Due Reports widget — GMs/Supervisors overdue on their reports.
async function loadWidgetPastDue() {
	const body = $('#sp-widget-pastdue-body');
	if (!body) return;
	try {
		const res = await spAjax('site_pulse_pastdue_reports', {});
		const list = (res.success && res.data.list) ? res.data.list : [];
		if (!list.length) { body.innerHTML = '<div class="sp-widget-empty">Everyone’s caught up. \u{1F389}</div>'; return; }
		body.innerHTML = list.map(p => `
			<div class="sp-widget-pastdue-item">
				<div class="sp-widget-item-title">${esc(p.name)}</div>
				<div class="sp-widget-item-meta">${esc(p.role)}${p.location ? ' · ' + esc(p.location) : ''} · ${esc(p.last_label)}</div>
			</div>`).join('');
	} catch (e) { body.innerHTML = '<div class="sp-widget-empty">—</div>'; }
}

// Customer Feedback widget — a tile per channel with a count, each opening that panel.
async function loadWidgetFeedback() {
	const body = $('#sp-widget-feedback-body');
	if (!body) return;
	try {
		const res = await spAjax('site_pulse_feedback_summary', {});
		if (!res.success) { body.innerHTML = '<div class="sp-widget-empty">—</div>'; return; }
		const d = res.data || {};
		const tile = (nav, value, label, alert) =>
			`<button type="button" class="unique sp-tile sp-feed-tile" data-nav="${nav}"><div class="sp-tile-value sp-feed-value${alert ? ' sp-feed-alert' : ''}">${value}</div><div class="sp-tile-label sp-feed-label">${esc(label)}</div></button>`;
		const tiles = [];
		if (d.reviews)  tiles.push(tile('reviews', d.reviews.count ? (d.reviews.avg != null ? Number(d.reviews.avg).toFixed(1) + '★' : spComma(d.reviews.count)) : '—', `Reviews · ${spComma(d.reviews.count || 0)} in 30d`, false));
		if (d.surveys)  tiles.push(tile('surveys', spComma(d.surveys.count || 0), 'Comment Cards · 30d', false));
		if (d.messages) tiles.push(tile('customer-messages', spComma(d.messages.unread || 0), 'Unread messages', (d.messages.unread || 0) > 0));
		if (d.emails)   tiles.push(tile('emails', spComma(d.emails.new || 0), 'New emails', (d.emails.new || 0) > 0));
		if (!tiles.length) { body.innerHTML = '<div class="sp-widget-empty">No feedback channels.</div>'; return; }
		body.innerHTML = `<div class="sp-card-grid sp-feed-grid">${tiles.join('')}</div>`;
		$$('.sp-feed-tile', body).forEach(t => t.addEventListener('click', () => activatePanel(t.dataset.nav)));
	} catch (e) { body.innerHTML = '<div class="sp-widget-empty">—</div>'; }
}

// Chat widget — 5 most recent threads with an unread badge; click opens the Chat panel.
async function loadWidgetChat() {
	const body = $('#sp-widget-chat-body');
	if (!body) return;
	try {
		const res = await spAjax('site_pulse_messages_conversations', {});
		const convos = (res.success && res.data.conversations) ? res.data.conversations.slice(0, 5) : [];
		if (!convos.length) { body.innerHTML = '<div class="sp-widget-empty">No conversations yet.</div>'; return; }
		body.innerHTML = convos.map(c => `
			<div class="sp-widget-chat-item" data-cid="${c.id}">
				${c.is_group ? spMsgGroupAvatar(c.members) : spMsgAvatar(c.name, c.avatar || '')}
				<div style="min-width:0;flex:1">
					<div class="sp-widget-item-title">${esc(c.name || 'Conversation')}</div>
					<div class="sp-widget-item-meta">${c.last ? (c.last_mine ? 'You: ' : '') + esc(c.last) : ''}</div>
				</div>
				${c.unread > 0 ? `<span class="unique sp-widget-badge">${c.unread}</span>` : ''}
			</div>`).join('');
		markUniqueSpans(body);
		$$('.sp-widget-chat-item', body).forEach(it => it.addEventListener('click', () => {
			activatePanel('messages');
			openMsgConversation(parseInt(it.dataset.cid, 10)); // deep-link straight into that thread
		}));
	} catch (e) { body.innerHTML = '<div class="sp-widget-empty">—</div>'; }
}

// Directory widget — clone the panel's filter options, then push the widget's selections into the
// directory panel and open it filtered.
function loadWidgetDirectory() {
	[['sp-dir-filter-brand', 'sp-dashw-dir-brand'], ['sp-dir-filter-location', 'sp-dashw-dir-location'], ['sp-dir-filter-position', 'sp-dashw-dir-position']].forEach(([src, dst]) => {
		const s = $('#' + src), d = $('#' + dst);
		if (s && d && d.options.length <= 1) d.innerHTML = s.innerHTML; // copy the option list once
	});
	const w = $('#sp-widget-directory');
	if (!w || w._dirWired) return;
	w._dirWired = true;
	['sp-dashw-dir-brand', 'sp-dashw-dir-location', 'sp-dashw-dir-position', 'sp-dashw-dir-status'].forEach(id => $('#' + id)?.addEventListener('change', spDirWidgetApply));
	$('#sp-dashw-dir-search')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') spDirWidgetApply(); });
	$('#sp-dashw-dir-clear')?.addEventListener('click', () => {
		['sp-dashw-dir-brand', 'sp-dashw-dir-location', 'sp-dashw-dir-position'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
		const st = $('#sp-dashw-dir-status'); if (st) st.value = 'active';
		const s = $('#sp-dashw-dir-search'); if (s) s.value = '';
	});
}

// Copy the widget's filter values into the directory panel's controls, open it, and (re)load.
function spDirWidgetApply() {
	const move = (panelId, widgetId) => { const p = $('#' + panelId), v = $('#' + widgetId); if (p && v) p.value = v.value; };
	move('sp-dir-filter-brand', 'sp-dashw-dir-brand');
	move('sp-dir-filter-location', 'sp-dashw-dir-location');
	move('sp-dir-filter-position', 'sp-dashw-dir-position');
	move('sp-dir-filter-status', 'sp-dashw-dir-status');
	move('sp-dir-search', 'sp-dashw-dir-search');
	activatePanel('directory');
	if (typeof loadDirectory === 'function') loadDirectory();
}

// Rebuild the "+ Add widget" dropdown from whatever is currently hidden; hide the toolbar when empty.
function spRefreshAddWidget() {
	const grid = $('#sp-dashboard-widgets'), sel = $('#sp-dash-add'), bar = $('#sp-dashboard-toolbar');
	if (!grid || !sel) return;
	let titles = {};
	try { titles = JSON.parse(grid.dataset.widgets || '{}'); } catch (e) {}
	const hidden = $$('.sp-widget[hidden]', grid).map(w => w.dataset.widget);
	sel.innerHTML = '<option value="">+ Add widget…</option>' + hidden.map(id => `<option value="${esc(id)}">${esc(titles[id] || id)}</option>`).join('');
	if (bar) bar.style.display = hidden.length ? '' : 'none';
}

function spSaveDashboardLayout() {
	const grid = $('#sp-dashboard-widgets');
	if (!grid) return;
	const all = $$('.sp-widget', grid);
	const order = all.map(w => w.dataset.widget);
	const hidden = all.filter(w => w.hidden).map(w => w.dataset.widget);
	spAjax('site_pulse_save_dashboard_layout', { order, hidden }).catch(() => {});
}

// Drag-reorder the dashboard widgets (grab from the ≡ handle), saving the new order on drop.
function spInitWidgetDrag(grid) {
	let dragItem = null;
	$$('.sp-widget', grid).forEach(item => {
		const handle = item.querySelector('.sp-widget-drag');
		if (handle) {
			const arm = () => { item.draggable = true; };
			const disarm = () => { item.draggable = false; };
			handle.addEventListener('mousedown', arm);
			handle.addEventListener('touchstart', arm, { passive: true });
			handle.addEventListener('mouseup', disarm);
			handle.addEventListener('touchend', disarm);
		}
		item.addEventListener('dragstart', (e) => { dragItem = item; item.classList.add('sp-dragging'); e.dataTransfer.effectAllowed = 'move'; });
		item.addEventListener('dragend', () => { item.classList.remove('sp-dragging'); item.draggable = false; dragItem = null; $$('.sp-widget', grid).forEach(el => el.classList.remove('sp-drag-over')); });
		item.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; if (dragItem && item !== dragItem) item.classList.add('sp-drag-over'); });
		item.addEventListener('dragleave', () => item.classList.remove('sp-drag-over'));
		item.addEventListener('drop', (e) => {
			e.preventDefault();
			item.classList.remove('sp-drag-over');
			if (!dragItem || item === dragItem) return;
			const items = $$('.sp-widget', grid);
			const from = items.indexOf(dragItem), to = items.indexOf(item);
			grid.insertBefore(dragItem, from < to ? item.nextSibling : item);
			spSaveDashboardLayout();
		});
	});
}

// Dashboard "install the app" reminder card. Shows only when NOT running as the installed app and
// the user hasn't installed it yet (server flag). Records standalone opens so adoption is tracked.
function initInstallCard() {
	const card = $('#sp-install-card');
	if (!card) return;

	const standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
	if (standalone) { spAjax('site_pulse_record_app_open', {}).catch(() => {}); card.hidden = true; return; }
	if (D.appInstalled) { card.hidden = true; return; }
	let dismissed = false;
	try { dismissed = localStorage.getItem('sp_install_card_dismissed') === '1'; } catch (e) {}
	if (dismissed) { card.hidden = true; return; }

	const name = D.appName || 'the app';
	const ua = navigator.userAgent || '';
	// iPadOS 13+ reports as "Macintosh" — catch it via touch points.
	const isIOS = (/iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) && !window.MSStream;
	const isMobile = isIOS || /Android|Mobi/i.test(ua);
	const dismissBtn = '<button type="button" class="unique sp-btn sp-btn-ghost sp-install-dismiss">Dismiss</button>';

	let title, body, actions;
	if (!isMobile) {
		// Desktop — the app is for phones, so point them there with how-to instead of an install button.
		title = 'Get ' + esc(name) + ' on your phone';
		body = `<strong>${esc(name)}</strong> installs on your <strong>phone</strong>. On your phone’s browser, go to <strong>${esc(location.host)}</strong>, sign in, then add it to your home screen:`
			+ '<ul class="sp-install-steps">'
			+ '<li><strong>iPhone</strong> (Safari): tap the Share icon, then <em>Add to Home Screen</em>.</li>'
			+ '<li><strong>Android</strong> (Chrome): tap the menu (⋮), then <em>Install app</em> / <em>Add to Home screen</em>.</li>'
			+ '</ul>';
		actions = dismissBtn;
	} else if (isIOS) {
		title = 'Install ' + esc(name);
		body = `Tap the Share button, then <strong>Add to Home Screen</strong> — for full-screen access and push notifications.`;
		actions = dismissBtn;
	} else {
		title = 'Install ' + esc(name);
		body = `Install <strong>${esc(name)}</strong> for one-tap, full-screen access and push notifications.`;
		actions = '<button type="button" class="unique sp-btn sp-btn-primary sp-install-go">Install</button>' + dismissBtn;
	}

	card.innerHTML =
		'<div class="sp-install-card-text"><strong class="sp-install-card-title">📲 ' + title + '</strong>'
		+ '<span class="sp-install-card-body">' + body + '</span></div>'
		+ '<div class="sp-install-card-actions">' + actions + '</div>';
	card.hidden = false;
	markUniqueSpans(card);

	$('.sp-install-dismiss', card)?.addEventListener('click', () => {
		try { localStorage.setItem('sp_install_card_dismissed', '1'); } catch (e) {}
		card.hidden = true;
	});
	$('.sp-install-go', card)?.addEventListener('click', async () => {
		const ev = window.__spInstallEvent;
		if (ev && ev.prompt) {
			ev.prompt();
			try { await ev.userChoice; } catch (e) {}
			window.__spInstallEvent = null;
			card.hidden = true;
		} else {
			alert('To install: open your browser menu and choose “Install app” / “Add to Home screen.”');
		}
	});
	window.addEventListener('appinstalled', () => {
		spAjax('site_pulse_record_app_open', {}).catch(() => {});
		try { localStorage.setItem('sp_install_card_dismissed', '1'); } catch (e) {}
		card.hidden = true;
	});
}

/*--------------------------------------------------------------
# Web Push (closed-app notifications) — per-device opt-in
--------------------------------------------------------------*/

let _spPush = { vapid: '', supported: false };

function spUrlB64ToUint8(b64) {
	const pad = '='.repeat((4 - (b64.length % 4)) % 4);
	const base64 = (b64 + pad).replace(/-/g, '+').replace(/_/g, '/');
	const raw = atob(base64);
	const arr = new Uint8Array(raw.length);
	for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
	return arr;
}

// Decide whether to show the bell's "turn on notifications for this device" control.
async function spPushInit() {
	_spPush.supported = ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
	const slot = $('#sp-push-prompt');
	if (!slot) return;
	let meta;
	try { const r = await spAjax('site_pulse_push_meta', {}); if (r && r.success) meta = r.data; } catch (e) {}
	if (!meta || !meta.enabled) { slot.hidden = true; return; } // site hasn't turned push on — hide entirely
	// Push is on site-wide, so from here the row ALWAYS shows a status (never silently hidden).
	if (!_spPush.supported) {
		slot.hidden = false;
		slot.innerHTML = '<span class="sp-push-state">This device or browser doesn’t support push notifications.</span>';
		return;
	}
	_spPush.vapid = meta.vapid_public || '';
	spPushRender();
}

async function spPushRender() {
	const slot = $('#sp-push-prompt');
	if (!slot) return;
	// Per-device opt-out: if the user X'd the turn-on nudge, stay hidden until the 30-day timer lapses.
	// (Only the nudge is dismissed — if they later turn it on, the timestamp is stale and status shows.)
	const _pushUntil = parseInt(localStorage.getItem('sp_push_dismissed_until') || '0', 10);
	if (_pushUntil && Date.now() < _pushUntil) { slot.hidden = true; return; }
	slot.hidden = false;
	slot.innerHTML = '<span class="sp-push-state">Checking notifications…</span>';

	// Don't let a service worker that never reaches "ready" hang this forever (the prior silent failure).
	let reg = null;
	try {
		reg = await Promise.race([
			navigator.serviceWorker.ready,
			new Promise((_, rej) => setTimeout(() => rej(new Error('sw-timeout')), 4000))
		]);
	} catch (e) {}

	if (!reg) {
		slot.innerHTML = '<span class="sp-push-state">Couldn’t start notifications — the app’s background service isn’t active yet. Fully close the app and reopen it from the home screen, then check here again.</span>';
		return;
	}

	let sub = null;
	try { sub = await reg.pushManager.getSubscription(); } catch (e) {}

	if (sub) {
		slot.innerHTML = '<span class="sp-push-state">🔔 Notifications on for this device.</span> <button type="button" class="unique sp-btn sp-btn-ghost sp-push-off">Turn off</button>';
		$('.sp-push-off', slot)?.addEventListener('click', spPushUnsubscribe);
	} else if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
		slot.innerHTML = '<span class="sp-push-state">Notifications are blocked for this app in your device settings — turn them back on there first.</span>';
	} else {
		slot.innerHTML = '<button type="button" class="unique sp-btn sp-btn-primary sp-push-on">Turn on notifications for this device</button>'
			+ '<button type="button" class="unique sp-push-dismiss" aria-label="Not now" title="Not now — ask again in 30 days">&times;</button>';
		$('.sp-push-on', slot)?.addEventListener('click', spPushSubscribe);
		$('.sp-push-dismiss', slot)?.addEventListener('click', () => {
			localStorage.setItem('sp_push_dismissed_until', String(Date.now() + 30 * 24 * 60 * 60 * 1000));
			slot.hidden = true;
		});
	}
}

async function spPushSubscribe() {
	if (!_spPush.vapid) { alert('Push isn’t set up on the server yet (no VAPID key). Check Settings → Push.'); return; }
	try {
		const perm = await Notification.requestPermission();
		if (perm !== 'granted') { spPushRender(); return; }
		const reg = await navigator.serviceWorker.ready;
		const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: spUrlB64ToUint8(_spPush.vapid) });
		const res = await spAjax('site_pulse_push_subscribe', { sub: JSON.stringify(sub) });
		if (!res || !res.success) { alert('This device got a subscription but the server rejected it: ' + (res?.data?.message || 'unknown error')); }
	} catch (e) { alert('Could not enable notifications on this device: ' + (e && e.message ? e.message : e)); }
	spPushRender();
}

async function spPushUnsubscribe() {
	try {
		const reg = await navigator.serviceWorker.ready;
		const sub = await reg.pushManager.getSubscription();
		if (sub) { const ep = sub.endpoint; await sub.unsubscribe(); await spAjax('site_pulse_push_unsubscribe', { endpoint: ep }); }
	} catch (e) {}
	spPushRender();
}

// Dashboard important-message banners (server-rendered) — X acknowledges (clears the dashboard flag
// + marks read) and removes the banner. The whole strip is removed once the last one is dismissed.
function initDashboardMessages() {
	$$('.sp-dash-message-close').forEach(btn => {
		if (btn._wired) return;
		btn._wired = true;
		btn.addEventListener('click', async () => {
			const id = btn.dataset.id;
			const card = btn.closest('.sp-dash-message');
			btn.disabled = true;
			try { await spAjax('site_pulse_ack_dashboard_message', { id }); } catch (e) {}
			if (card) card.remove();
			const strip = $('#sp-dash-messages');
			if (strip && !strip.querySelector('.sp-dash-message')) strip.remove();
		});
	});
}


/*--------------------------------------------------------------
# Messages (direct messages between users)
--------------------------------------------------------------*/

let _spMsg = { cid: 0, timer: null, editing: false, meta: null, seenTimer: null, maxId: 0, threadSig: '', replyTo: null };

// Reply (FB Messenger style): clicking Reply on a bubble shows a bar above the composer with a quote
// of that message; the next send carries reply_to_id and renders the quote above the new bubble.
function spMsgStartReply(bubbleEl) {
	if (!bubbleEl) return;
	const mine = bubbleEl.classList.contains('mine');
	const senderEl = bubbleEl.querySelector('.sp-msg-bubble-sender');
	const sender = senderEl ? senderEl.textContent : (mine ? 'yourself' : ((_spMsg.meta && _spMsg.meta.title) || 'them'));
	const bodyEl = bubbleEl.querySelector('.sp-msg-bubble-body');
	let snippet = bodyEl ? bodyEl.textContent.trim() : '';
	if (!snippet) {
		if (bubbleEl.querySelector('.sp-msg-attach-img')) snippet = 'Photo';
		else { const f = bubbleEl.querySelector('.sp-msg-attach-file'); if (f) snippet = (f.textContent || 'Attachment').trim(); }
	}
	if (snippet.length > 90) snippet = snippet.slice(0, 90) + '…';
	_spMsg.replyTo = { id: bubbleEl.dataset.id, sender, snippet };
	spMsgRenderReplyBar();
	$('#sp-msg-input')?.focus();
}

function spMsgRenderReplyBar() {
	const composer = $('#sp-msg-composer');
	if (!composer) return;
	let bar = $('#sp-msg-reply-bar');
	if (!_spMsg.replyTo) { if (bar) bar.remove(); return; }
	if (!bar) {
		bar = document.createElement('div');
		bar.id = 'sp-msg-reply-bar';
		bar.className = 'sp-msg-reply-bar';
		composer.parentNode.insertBefore(bar, composer);
	}
	bar.innerHTML = '<div class="sp-msg-reply-bar-text"><span class="sp-msg-reply-bar-label unique">Replying to ' + esc(_spMsg.replyTo.sender) + '</span><span class="sp-msg-reply-bar-snippet unique">' + esc(_spMsg.replyTo.snippet) + '</span></div>'
		+ '<button type="button" class="unique sp-msg-reply-cancel" aria-label="Cancel reply">✕</button>';
	markUniqueSpans(bar);
	$('.sp-msg-reply-cancel', bar)?.addEventListener('click', () => spMsgCancelReply());
}

function spMsgCancelReply() {
	_spMsg.replyTo = null;
	$('#sp-msg-reply-bar')?.remove();
}

// A cheap fingerprint of the rendered thread (messages + the seen receipt). The poll uses it to skip
// re-rendering when nothing changed — a full innerHTML swap every 10s is what caused the flicker.
function spMsgThreadSig(data) {
	const msgs = data.messages || [];
	return msgs.map(m => `${m.id}:${m.edited || 0}:${(m.body || '').length}:${m.attach_url || 0}`).join(',') + '|' + spMsgReceiptHTML(data);
}

// Speech-to-text dictation into the composer (Web Speech API). The mic button only appears when the
// browser supports it (Android Chrome, desktop Chrome/Edge, iOS Safari 14.5+).
let _spDictation = { rec: null, active: false };
function spMsgSpeechApi() { return window.SpeechRecognition || window.webkitSpeechRecognition || null; }

function spMsgInitMic(thread) {
	const btn = $('#sp-msg-mic', thread);
	if (!btn || !spMsgSpeechApi()) return; // unsupported → button stays hidden
	btn.hidden = false;
	btn.addEventListener('click', () => spMsgToggleDictation(btn));
}

function spMsgToggleDictation(btn) {
	// Already listening → stop.
	if (_spDictation.active && _spDictation.rec) { try { _spDictation.rec.stop(); } catch (e) {} return; }

	const SR = spMsgSpeechApi();
	const input = $('#sp-msg-input');
	if (!SR || !input) return;

	const rec = new SR();
	rec.lang = navigator.language || 'en-US';
	rec.interimResults = true;
	rec.continuous = true;
	_spDictation.rec = rec;

	const base = input.value || ''; // keep anything already typed

	rec.onstart = () => { _spDictation.active = true; btn.classList.add('listening'); btn.title = 'Stop dictating'; };
	const stop = () => { _spDictation.active = false; btn.classList.remove('listening'); btn.title = 'Dictate a message'; };
	rec.onend = stop;
	rec.onerror = stop;
	rec.onresult = (e) => {
		let text = '';
		for (let i = 0; i < e.results.length; i++) text += e.results[i][0].transcript;
		const sep = base && !/\s$/.test(base) ? ' ' : '';
		input.value = base + sep + text;
		input.dispatchEvent(new Event('input'));
	};

	try { rec.start(); } catch (e) {}
}

// "Seen" read-receipt under the viewer's last sent message: shown once another participant's seen
// pointer reaches it (1:1 → "Seen <time>", group → "Seen by …"). data.seen = other participants'
// {user_id,name,seen_message_id,seen_at}.
function spMsgReceiptHTML(data) {
	const msgs = data.messages || [];
	let lastMine = null;
	for (let i = msgs.length - 1; i >= 0; i--) { if (msgs[i].mine) { lastMine = msgs[i]; break; } }
	if (!lastMine) return '';
	const seers = (data.seen || []).filter(s => parseInt(s.seen_message_id, 10) >= parseInt(lastMine.id, 10));
	if (!seers.length) return '';
	const isGroup = !!(data.conversation && data.conversation.is_group);
	const text = isGroup
		? 'Seen by ' + seers.map(s => esc(s.name)).join(', ')
		: 'Seen' + (seers[0].seen_at ? ' ' + esc(msgTime(seers[0].seen_at)) : '');
	return `<div class="sp-msg-seen">${text}</div>`;
}

// Mark the conversation "seen" only after 3s of visible dwell on it (genuine read receipt).
function spMsgStartSeenTimer(cid) {
	clearTimeout(_spMsg.seenTimer);
	_spMsg.seenTimer = setTimeout(() => {
		if (_spMsg.cid === cid && document.visibilityState === 'visible') {
			spAjax('site_pulse_messages_seen', { conversation_id: cid }).catch(() => {});
		}
	}, 3000);
}

function msgTime(at) {
	if (!at) return '';
	const d = new Date(String(at).replace(' ', 'T'));
	if (isNaN(d)) return at;
	return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function msgBubbleHTML(m, isGroup) {
	// Every message gets a "create action item" control; own messages also get Edit/Delete. In a
	// group, others' bubbles show the sender name.
	let actions = '<div class="sp-msg-bubble-actions">';
	actions += `<button type="button" class="unique sp-btn sp-msg-reply" title="Reply" aria-label="Reply to message">${ICON_REPLY}</button>`;
	actions += m.has_task
		? `<button type="button" class="unique sp-btn sp-msg-task is-done" title="Action item already created" aria-label="Action item already created from this message" disabled>${ICON_TASK}</button>`
		: `<button type="button" class="unique sp-btn sp-msg-task" title="Create action item" aria-label="Create action item from this message">${ICON_TASK}</button>`;
	if (m.mine) {
		actions += `<button type="button" class="unique sp-btn sp-msg-edit" title="Edit" aria-label="Edit message">${ICON_EDIT}</button>`;
		actions += `<button type="button" class="unique sp-btn sp-msg-del" title="Delete" aria-label="Delete message">${ICON_DELETE}</button>`;
	}
	actions += '</div>';
	// In a group, others' bubbles show the sender name (a 1:1 doesn't need it).
	const sender = ( isGroup && ! m.mine ) ? `<div class="sp-msg-bubble-sender">${esc(m.sender || '')}</div>` : '';
	// Quoted preview of the message this one is replying to (FB Messenger style).
	const reply = (m.reply && (m.reply.snippet || m.reply.sender))
		? `<div class="sp-msg-reply-quote"><span class="sp-msg-reply-sender unique">${esc(m.reply.sender || '')}</span><span class="sp-msg-reply-snippet unique">${esc(m.reply.snippet || '')}</span></div>`
		: '';
	let attach = '';
	if (m.attach_url) {
		const isImg = (m.attach_mime || '').indexOf('image/') === 0;
		const dataAttrs = `data-attach-url="${esc(m.attach_url)}" data-attach-mime="${esc(m.attach_mime || '')}" data-attach-name="${esc(m.attach_name || '')}"`;
		attach = isImg
			? `<button type="button" class="unique sp-msg-attach-img" ${dataAttrs}><img src="${esc(m.attach_url)}" alt="${esc(m.attach_name || '')}"></button>`
			: `<button type="button" class="unique sp-msg-attach-file" ${dataAttrs}>${ICON_ATTACH} ${esc(m.attach_name || 'Attachment')}</button>`;
	}
	const bodyHtml = m.body ? `<div class="sp-msg-bubble-body">${esc(m.body)}</div>` : '';
	return `<div class="sp-msg-bubble ${m.mine ? 'mine' : 'theirs'}" data-id="${m.id}">${actions}${sender}${reply}${attach}${bodyHtml}<div class="sp-msg-bubble-time">${esc(msgTime(m.at))}${m.edited ? ' · edited' : ''}</div></div>`;
}

// Open a message attachment in a popup (like the "new message" dialog) instead of a new tab — far
// easier to dismiss on mobile. Images and PDFs preview inline; everything else offers Download.
function spMsgAttachModal(url, mime, name, msgId) {
	if (!url) return;
	const backdrop = spFormModal();
	const isImg = (mime || '').indexOf('image/') === 0;
	const isPdf = (mime || '') === 'application/pdf' || /\.pdf(\?|$)/i.test(url);

	let html = '<div class="sp-attach-modal">';
	html += '<div class="sp-card-header sp-header sp-attach-modal-head"><span class="sp-attach-modal-name unique">' + esc(name || 'Attachment') + '</span>'
		+ '<button type="button" class="unique sp-btn sp-btn-secondary sp-attach-close" aria-label="Close">✕</button></div>';
	if (isImg) {
		html += '<div class="sp-attach-modal-body"><img src="' + esc(url) + '" alt="' + esc(name || '') + '" class="sp-attach-modal-img"></div>';
	} else if (isPdf) {
		html += '<div class="sp-attach-modal-body"><iframe src="' + esc(url) + '" class="sp-attach-modal-frame" title="' + esc(name || 'PDF') + '"></iframe></div>';
	} else {
		html += '<div class="sp-attach-modal-body sp-attach-modal-fileonly"><p>No preview available for this file type.</p></div>';
	}
	html += '<div class="sp-attach-modal-actions">';
	// Rotate controls (images only) — physically rotate the stored file so it sticks for everyone in the chat.
	if (isImg && msgId) {
		html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-attach-rotate" data-dir="ccw" title="Rotate left" aria-label="Rotate left">&#8634;</button>';
		html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-attach-rotate" data-dir="cw" title="Rotate right" aria-label="Rotate right">&#8635;</button>';
	}
	html += '<a class="unique sp-btn sp-btn-primary" href="' + esc(url) + '" download>Download</a>'
		+ '<button type="button" class="unique sp-btn sp-btn-secondary sp-attach-close">Close</button></div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);
	$$('.sp-attach-close', backdrop).forEach(b => b.addEventListener('click', () => closeFormModal()));

	// Rotate 90° either way — persists to the file server-side, then swaps the fresh (cache-busted) URL
	// into the viewer, the Download link, and the thumbnail in the thread so this client updates too.
	$$('.sp-attach-rotate', backdrop).forEach(btn => btn.addEventListener('click', async () => {
		const rotBtns = $$('.sp-attach-rotate', backdrop);
		rotBtns.forEach(b => b.disabled = true);
		try {
			const res = await spAjax('site_pulse_messages_rotate_attachment', { message_id: msgId, direction: btn.dataset.dir });
			if (res.success && res.data && res.data.url) {
				const nu = res.data.url;
				const img = backdrop.querySelector('.sp-attach-modal-img');
				if (img) img.src = nu;
				const dl = backdrop.querySelector('.sp-attach-modal-actions a[download]');
				if (dl) dl.href = nu;
				const thumb = document.querySelector(`.sp-msg-bubble[data-id="${msgId}"] .sp-msg-attach-img`);
				if (thumb) {
					thumb.dataset.attachUrl = nu;
					const ti = thumb.querySelector('img');
					if (ti) ti.src = nu;
				}
			} else {
				alert((res.data && res.data.message) || 'Could not rotate the image.');
			}
		} catch (e) { alert('Could not rotate the image.'); }
		rotBtns.forEach(b => b.disabled = false);
	}));
}

// Append a message bubble unless one with the same id is already in the thread. Makes the optimistic
// insert idempotent, so a stray double-send or a race with the 10s poll can't show the same message
// twice (previously it posted twice until a page refresh rebuilt the thread from the server).
function spMsgAppendBubbleOnce(bubbles, msg) {
	if (!bubbles || !msg) return;
	if (msg.id != null && bubbles.querySelector(`.sp-msg-bubble[data-id="${msg.id}"]`)) return;
	bubbles.insertAdjacentHTML('beforeend', msgBubbleHTML(msg, _spMsg.meta && _spMsg.meta.is_group));
	spMsgScrollBottom(bubbles);
}

// Upload a file and post it as a message (with any current composer text as a caption).
async function uploadMsgFile(file) {
	const cid = _spMsg.cid;
	if (!file || !cid) return;
	const input = $('#sp-msg-input');
	const fd = new FormData();
	fd.append('action', 'site_pulse_messages_upload_send');
	fd.append('nonce', spGetNonce());
	fd.append('conversation_id', cid);
	fd.append('body', input ? input.value.trim() : '');
	fd.append('reply_to_id', (_spMsg.replyTo && _spMsg.replyTo.id) || 0);
	fd.append('file', file);
	spMsgCancelReply();
	try {
		const r = await fetch(D.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: fd });
		const res = await r.json();
		if (res && res.success) {
			if (input) input.value = '';
			const bubbles = $('#sp-msg-bubbles');
			if (bubbles) spMsgAppendBubbleOnce(bubbles, res.data.message);
			loadMsgConversations();
		} else { alert((res && res.data && res.data.message) || 'Upload failed.'); }
	} catch (e) { alert('Upload failed.'); }
}

// Startup: wire the New Message button + poll the unread badge (whole-app, like the bell).
function initMessages() {
	const newBtn = $('#sp-msg-new-btn');
	if (newBtn && !newBtn._wired) { newBtn._wired = true; newBtn.addEventListener('click', openMsgNew); }

	// Delegated Edit/Delete on own bubbles (thread re-renders on poll, so bind once on the container).
	const threadEl = $('#sp-msg-thread');
	if (threadEl && !threadEl._wiredActions) {
		threadEl._wiredActions = true;
		threadEl.addEventListener('click', (e) => {
			const attachBtn = e.target.closest('.sp-msg-attach-img, .sp-msg-attach-file');
			if (attachBtn) { const _b = attachBtn.closest('.sp-msg-bubble'); spMsgAttachModal(attachBtn.dataset.attachUrl, attachBtn.dataset.attachMime, attachBtn.dataset.attachName, _b ? _b.dataset.id : 0); return; }
			const replyBtn = e.target.closest('.sp-msg-reply');
			if (replyBtn) { const b = replyBtn.closest('.sp-msg-bubble'); if (b) spMsgStartReply(b); return; }
			const taskBtn = e.target.closest('.sp-msg-task');
			if (taskBtn) { if (taskBtn.disabled) return; const b = taskBtn.closest('.sp-msg-bubble'); if (b) spMsgCreateAction(parseInt(b.dataset.id, 10)); return; }
			const editBtn = e.target.closest('.sp-msg-edit');
			if (editBtn) { const b = editBtn.closest('.sp-msg-bubble'); if (b) editMsg(parseInt(b.dataset.id, 10), b); return; }
			const delBtn = e.target.closest('.sp-msg-del');
			if (delBtn) { const b = delBtn.closest('.sp-msg-bubble'); if (b) deleteMsg(parseInt(b.dataset.id, 10), b); }
		});
	}

	loadMsgUnread();
	setInterval(loadMsgUnread, 60000);

	// If the user tabs away then back while a conversation is open on the Messages panel, re-arm the
	// 3s "seen" dwell (we don't count seen-time while the tab is hidden).
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState !== 'visible' || !_spMsg.cid) return;
		const panel = $('#sp-panel-messages');
		if (panel && panel.classList.contains('active')) spMsgStartSeenTimer(_spMsg.cid);
	});
}

// Inline-edit one of my bubbles. Pauses the thread poll so the refresh doesn't wipe the editor.
function editMsg(id, bubble) {
	if (_spMsg.editing) return;
	const bodyEl = bubble.querySelector('.sp-msg-bubble-body');
	if (!bodyEl) return;
	_spMsg.editing = true;
	const orig = bodyEl.textContent;
	bodyEl.innerHTML = `<textarea class="sp-msg-edit-input">${esc(orig)}</textarea><div class="sp-msg-edit-actions"><button type="button" class="unique sp-btn sp-btn-primary sp-msg-edit-save">Save</button><button type="button" class="unique sp-btn sp-btn-ghost sp-msg-edit-cancel">Cancel</button></div>`;
	markUniqueSpans(bubble);
	const ta = bubble.querySelector('.sp-msg-edit-input');
	if (ta) { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); }
	const finish = () => { _spMsg.editing = false; if (_spMsg.cid) refreshMsgThread(_spMsg.cid); };
	bubble.querySelector('.sp-msg-edit-cancel')?.addEventListener('click', finish);
	bubble.querySelector('.sp-msg-edit-save')?.addEventListener('click', async () => {
		const body = (ta ? ta.value : '').trim();
		if (!body) { finish(); return; }
		try {
			const res = await spAjax('site_pulse_messages_edit', { id, body });
			if (!res.success) alert(res.data?.message || 'Could not edit.');
		} catch (e) { alert('Could not edit.'); }
		finish();
		loadMsgConversations();
	});
}

async function deleteMsg(id, bubble) {
	if (!confirm('Delete this message?')) return;
	try {
		const res = await spAjax('site_pulse_messages_delete', { id });
		if (res.success) { bubble.remove(); loadMsgConversations(); }
		else alert(res.data?.message || 'Could not delete.');
	} catch (e) { alert('Could not delete.'); }
}

// Create an action item from a message: AI reads the message (+ a couple around it), proposes
// item(s), then the user edits/picks them and chooses whose to-do list(s) to add them to.
async function spMsgCreateAction(mid) {
	const backdrop = spFormModal();
	backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><div class="sp-loading"></div><p class="sp-help-text" style="text-align:center;margin-top:10px;">Reading the conversation…</p></div>';

	let data, res;
	try { res = await spAjax('site_pulse_messages_action_suggest', { message_id: mid }); } catch (e) {}
	if (!res || !res.success) {
		const why = (res && res.data && res.data.message) ? res.data.message : 'Could not reach the action-item generator. Make sure the latest update is deployed.';
		backdrop.innerHTML = `<div class="sp-card sp-report-form-wrap"><p>${esc(why)}</p><div class="sp-report-form-actions"><button type="button" class="unique sp-btn sp-btn-ghost sp-act-cancel">Close</button></div></div>`;
		$('.sp-act-cancel', backdrop)?.addEventListener('click', () => closeFormModal());
		return;
	}
	data = res.data;

	const items = (data.items && data.items.length) ? data.items : [{ description: '', priority: 'medium', category: 'Message' }];
	const others = data.others || [];

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>Create Action Item</h3><button type="button" class="unique sp-btn sp-btn-ghost sp-act-cancel">Cancel</button></div>';
	html += data.empty
		? '<p class="sp-help-text" style="margin:0 0 10px;">The AI didn’t find a clear to-do in these messages — write one below, then choose whose list to add it to.</p>'
		: '<p class="sp-help-text" style="margin:0 0 10px;">Review what the AI pulled from this message. Edit or uncheck any, then choose whose list to add them to.</p>';
	html += '<div class="sp-act-items">';
	items.forEach((it, i) => {
		html += '<div class="sp-act-item">'
			+ `<input type="checkbox" class="sp-act-cb" data-i="${i}" checked>`
			+ `<textarea class="sp-act-desc" data-i="${i}" rows="2" placeholder="Describe the to-do…">${esc(it.description)}</textarea>`
			+ `<select class="sp-act-pri" data-i="${i}">`
			+ ['high', 'medium', 'low'].map(p => `<option value="${p}"${it.priority === p ? ' selected' : ''}>${p.charAt(0).toUpperCase() + p.slice(1)}</option>`).join('')
			+ '</select></div>';
	});
	html += '</div>';

	html += '<div class="sp-act-assign">';
	html += '<label class="sp-reminder-toggle"><input type="radio" name="sp-act-assign" value="me" checked> Add to My Action Items</label>';
	if (others.length === 1) {
		const firstName = esc((others[0].name || '').split(' ')[0] || others[0].name);
		html += `<label class="sp-reminder-toggle"><input type="radio" name="sp-act-assign" value="other"> Add to ${firstName}’s Action Items</label>`;
		html += '<label class="sp-reminder-toggle"><input type="radio" name="sp-act-assign" value="both"> Add to Both Action Items</label>';
	} else if (others.length > 1) {
		html += '<label class="sp-reminder-toggle"><input type="radio" name="sp-act-assign" value="other"> Add to Everyone Else’s Action Items</label>';
		html += '<label class="sp-reminder-toggle"><input type="radio" name="sp-act-assign" value="both"> Add to Everyone’s Action Items</label>';
	}
	html += '</div>';

	html += '<div class="sp-report-form-actions"><button type="button" class="unique sp-btn sp-btn-primary sp-act-create">Create</button><button type="button" class="unique sp-btn sp-btn-ghost sp-act-cancel">Cancel</button></div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);

	$$('.sp-act-cancel', backdrop).forEach(b => b.addEventListener('click', () => closeFormModal()));
	$('.sp-act-create', backdrop)?.addEventListener('click', async () => {
		const chosen = [];
		$$('.sp-act-cb', backdrop).forEach(cb => {
			if (!cb.checked) return;
			const i = cb.dataset.i;
			const desc = ($(`.sp-act-desc[data-i="${i}"]`, backdrop)?.value || '').trim();
			const pri = $(`.sp-act-pri[data-i="${i}"]`, backdrop)?.value || 'medium';
			if (desc) chosen.push({ description: desc, priority: pri, category: items[i].category || 'Message' });
		});
		if (!chosen.length) { alert('Pick or write at least one action item.'); return; }
		const assign = backdrop.querySelector('input[name="sp-act-assign"]:checked')?.value || 'me';
		const btn = $('.sp-act-create', backdrop);
		if (btn) btn.disabled = true;
		try {
			const res = await spAjax('site_pulse_messages_action_create', { conversation_id: data.conversation_id, assign, items: JSON.stringify(chosen), message_id: mid });
			if (res.success) {
				// Lock this message's task button immediately for the clicker (others get it via the poll).
				const tb = document.querySelector(`#sp-msg-bubbles .sp-msg-bubble[data-id="${mid}"] .sp-msg-task`);
				if (tb) { tb.disabled = true; tb.classList.add('is-done'); tb.title = 'Action item already created'; }
				const n = res.data.created || 0;
				backdrop.innerHTML = `<div class="sp-card sp-report-form-wrap"><p style="text-align:center;font-weight:600;margin:8px 0 16px;">✓ Added ${n} action item${n === 1 ? '' : 's'}.</p><div class="sp-report-form-actions" style="justify-content:center;"><button type="button" class="unique sp-btn sp-btn-primary sp-act-cancel">Done</button></div></div>`;
				$('.sp-act-cancel', backdrop)?.addEventListener('click', () => closeFormModal());
				loadMsgUnread();
			} else { alert(res.data?.message || 'Could not create the action item.'); if (btn) btn.disabled = false; }
		} catch (e) { alert('Could not create the action item.'); if (btn) btn.disabled = false; }
	});
}

async function loadMsgUnread() {
	try {
		const res = await spAjax('site_pulse_messages_unread', {});
		if (res.success) updateMsgBadge(res.data.count || 0);
	} catch (e) {}
}

function updateMsgBadge(count) {
	_spMsgCount = count || 0;
	spUpdateAppBadge();
	const navBtn = $('[data-nav="messages"]');
	if (!navBtn) return;
	let badge = navBtn.querySelector('.sp-nav-badge');
	if (count > 0) {
		if (!badge) { badge = document.createElement('span'); badge.className = 'sp-nav-badge'; navBtn.appendChild(badge); }
		badge.textContent = count > 99 ? '99+' : count;
	} else if (badge) {
		badge.remove();
	}
}

// Entering the Messages panel: load the conversation list, restore/clear the open thread, poll.
function initMessagesPanel() {
	loadMsgConversations();
	if (_spMsg.cid) openMsgConversation(_spMsg.cid);
	else renderMsgThreadEmpty();
	startMsgPolling();
}

function startMsgPolling() {
	stopMsgPolling();
	_spMsg.timer = setInterval(() => {
		loadMsgConversations();
		if (_spMsg.cid) refreshMsgThread(_spMsg.cid);
	}, 10000);
}
function stopMsgPolling() {
	if (_spMsg.timer) { clearInterval(_spMsg.timer); _spMsg.timer = null; }
	clearTimeout(_spMsg.seenTimer);
}

async function loadMsgConversations() {
	const list = $('#sp-msg-list');
	if (!list) return;
	let res;
	try { res = await spAjax('site_pulse_messages_conversations', {}); } catch (e) {}
	if (!res || !res.success) {
		list.innerHTML = '<div class="sp-msg-empty">Could not load messages. Make sure the latest update is deployed.</div>';
		return;
	}
	const convos = res.data.conversations || [];
	if (!convos.length) {
		list.innerHTML = '<div class="sp-msg-empty">No conversations yet. Start one with “+ New Message”.</div>';
		return;
	}
	list.innerHTML = convos.map(c => `
		<button type="button" class="unique sp-msg-convo${c.id === _spMsg.cid ? ' active' : ''}${c.unread ? ' unread' : ''}" data-cid="${c.id}">
			${c.is_group ? spMsgGroupAvatar(c.members) : spMsgAvatar(c.name, c.avatar || '')}
			<div class="sp-msg-convo-main">
				${c.is_group ? '<div class="sp-msg-grouprow"><span class="sp-msg-group-tag">Group</span></div>' : ''}
				<div class="sp-msg-convo-top"><span class="sp-msg-convo-name">${esc(c.name)}</span>${c.unread ? `<span class="sp-msg-convo-badge">${c.unread}</span>` : ''}</div>
				<div class="sp-msg-convo-preview">${c.last_mine ? 'You: ' : ''}${esc((c.last || '').slice(0, 60))}</div>
			</div>
		</button>`).join('');
	markUniqueSpans(list);
	$$('.sp-msg-convo', list).forEach(b => b.addEventListener('click', () => openMsgConversation(parseInt(b.dataset.cid, 10))));
}

// Return from an open thread to the conversation list (the mobile "Back" action; on desktop it just
// clears the active thread pane). Drops the full-screen overlay and refreshes the list + badges.
function spMsgBackToList() {
	$('#sp-messenger')?.classList.remove('thread-open');
	_spMsg.cid = 0;
	renderMsgThreadEmpty();
	loadMsgConversations();
	loadMsgUnread();
}

// If the conversation name is wider than its header slot, slowly pan it left to reveal the end, then
// back — a gentle marquee. Static (no animation) when the name already fits.
function spMsgMarqueeTitle(thread) {
	const title = $('.sp-msg-thread-title', thread);
	if (!title) return;
	const wrap = title.parentElement;
	title.classList.remove('sp-marquee');
	title.style.removeProperty('--sp-marquee-shift');
	title.style.removeProperty('animation-duration');
	const shift = title.scrollWidth - wrap.clientWidth;
	if (shift > 6) {
		title.style.setProperty('--sp-marquee-shift', `-${shift}px`);
		title.style.animationDuration = Math.max(9, (shift / 22) + 6) + 's'; // pace by overshoot, never frantic
		title.classList.add('sp-marquee');
	}
}

async function openMsgConversation(cid) {
	_spMsg.cid = cid;
	_spMsg.replyTo = null; // a pending reply doesn't carry across conversations
	const thread = $('#sp-msg-thread');
	if (!thread) return;
	thread.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_messages_thread', { conversation_id: cid });
		if (!res.success) { thread.innerHTML = '<div class="sp-msg-empty">Could not load this conversation.</div>'; return; }
		_spMsg.meta = res.data.conversation;
		renderMsgThread(res.data);
		$('#sp-messenger')?.classList.add('thread-open'); // mobile: show the thread as a full-screen overlay
		loadMsgConversations();
		loadMsgUnread();
		// Reading this thread clears its push notification from the shade (and drops the icon badge by one).
		try {
			if (navigator.serviceWorker && navigator.serviceWorker.controller) {
				navigator.serviceWorker.controller.postMessage({ type: 'sp-close-tag', tag: 'sp-conv-' + cid });
			}
		} catch (e) {}
	} catch (e) {}
}

// Recipient avatar: the directory photo for a DM's other person (monogram fallback). Groups get a
// monogram of the group title.
function spMsgAvatar(name, photo) {
	if (photo) return `<span class="sp-msg-avatar"><img src="${esc(photo)}" alt="" loading="lazy"></span>`;
	const s = String(name || '?').trim();
	const parts = s.split(/\s+/);
	const initials = ((parts[0] && parts[0][0]) || '?') + (parts.length > 1 ? parts[parts.length - 1][0] : '');
	let h = 0;
	for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0;
	return `<span class="sp-msg-avatar sp-msg-avatar-mono" style="background:hsl(${h % 360},45%,55%)">${esc(initials.toUpperCase())}</span>`;
}

// Group avatar: up to 3 overlapping circles, most-recent commenter on top (members arrive recency-ordered).
function spMsgGroupAvatar(members) {
	const list = (members || []).slice(0, 3);
	if (!list.length) return spMsgAvatar('Group', '');
	const circles = list.map((m, i) => `<span class="sp-msg-stack-item" style="z-index:${10 - i}">${spMsgAvatar(m.name, m.photo)}</span>`).join('');
	return `<span class="sp-msg-stack">${circles}</span>`;
}

function renderMsgThread(data) {
	const thread = $('#sp-msg-thread');
	if (!thread) return;
	const conv = data.conversation || {};
	const isGroup = !!conv.is_group;
	// Name + avatar come from the server (the DM "other person" is resolved there, same as the title).
	let html = '<div class="sp-card-header sp-header sp-msg-thread-header">';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-msg-back" title="Back to messages" aria-label="Back to messages">&#8592;</button>';
	html += isGroup ? spMsgGroupAvatar(conv.members) : spMsgAvatar(conv.title || 'Conversation', conv.avatar || '');
	html += '<div class="sp-msg-thread-heading">';
	if (isGroup) html += `<button type="button" class="unique sp-msg-members sp-msg-manage">${(conv.participants || []).length} members</button>`;
	html += `<div class="sp-msg-thread-title-wrap"><span class="sp-msg-thread-title">${esc(conv.title || 'Conversation')}</span></div>`;
	html += '</div>';
	html += '<span class="sp-msg-thread-actions">';
	html += `<button type="button" class="unique sp-btn sp-icon-btn sp-msg-search-btn" title="Search this conversation" aria-label="Search conversation">${ICON_SEARCH}</button>`;
	html += `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-msg-delete" title="Delete this conversation from your list" aria-label="Delete conversation">${ICON_DELETE}</button>`;
	html += '</span>';
	html += '</div>';
	// In-thread find bar (hidden until the magnifier is tapped) — highlights matches + prev/next.
	html += '<div class="sp-msg-search-bar" id="sp-msg-search-bar" hidden>'
		+ '<input type="search" class="sp-input sp-msg-search-input" placeholder="Search this conversation…" aria-label="Search this conversation">'
		+ '<span class="sp-msg-search-count" aria-live="polite"></span>'
		+ `<button type="button" class="unique sp-btn sp-icon-btn sp-msg-search-prev" title="Previous match" aria-label="Previous match">&#9650;</button>`
		+ `<button type="button" class="unique sp-btn sp-icon-btn sp-msg-search-next" title="Next match" aria-label="Next match">&#9660;</button>`
		+ `<button type="button" class="unique sp-btn sp-icon-btn sp-msg-search-close" title="Close search" aria-label="Close search">&times;</button>`
		+ '</div>';
	html += '<div class="sp-msg-bubbles" id="sp-msg-bubbles">';
	(data.messages || []).forEach(m => { html += msgBubbleHTML(m, isGroup); });
	html += spMsgReceiptHTML(data);
	html += '</div>';
	html += '<form class="sp-msg-composer sp-card-footer" id="sp-msg-composer">'
		+ '<button type="button" class="sp-btn sp-btn-ghost sp-msg-mic-btn unique" id="sp-msg-mic" title="Dictate a message" aria-label="Dictate a message" hidden>' + ICON_MIC + '</button>'
		+ '<label class="sp-btn sp-btn-ghost sp-msg-attach-btn unique" title="Attach a file" aria-label="Attach a file">' + ICON_ATTACH + '<input type="file" id="sp-msg-file" hidden></label>'
		+ '<textarea class="sp-msg-input" id="sp-msg-input" rows="1" placeholder="Type a message…"></textarea>'
		+ '<button type="submit" class="unique sp-btn sp-btn-primary">Send</button></form>';
	thread.innerHTML = html;
	markUniqueSpans(thread);

	// Open scrolled to the FIRST UNREAD message (not the top like iOS, not always the bottom like Android).
	// First unread = the earliest incoming message newer than what this user had previously read.
	const bubbles = $('#sp-msg-bubbles', thread);
	if (bubbles) {
		const lastRead = parseInt(data.last_read_id, 10) || 0;
		let firstUnread = null;
		for (const m of (data.messages || [])) {
			if (!m.mine && parseInt(m.id, 10) > lastRead) { firstUnread = m.id; break; }
		}
		const pinScroll = () => {
			const el = firstUnread != null ? bubbles.querySelector(`.sp-msg-bubble[data-id="${firstUnread}"]`) : null;
			if (el) {
				// Pin the first unread message near the top of the thread (confined to the bubbles scroller).
				const top = el.getBoundingClientRect().top - bubbles.getBoundingClientRect().top + bubbles.scrollTop;
				bubbles.scrollTop = Math.max(0, top - 8);
			} else {
				bubbles.scrollTop = bubbles.scrollHeight; // all caught up → jump to the latest
			}
		};
		// iOS Safari hasn't laid the bubbles out yet on the same tick as innerHTML, so scrollTop here is a
		// no-op (lands at the top). Defer past layout (double rAF), then re-pin once any images/attachments
		// finish loading and shift the heights.
		requestAnimationFrame(() => requestAnimationFrame(pinScroll));
		setTimeout(pinScroll, 80);
		bubbles.querySelectorAll('img').forEach(img => { if (!img.complete) img.addEventListener('load', pinScroll, { once: true }); });
	}

	$('#sp-msg-composer', thread)?.addEventListener('submit', (e) => { e.preventDefault(); sendMsg(); });
	const input = $('#sp-msg-input', thread);
	input?.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); } });
	// Paste an image straight from the clipboard (e.g. a screenshot + Ctrl/Cmd-V) → send it as an attachment.
	input?.addEventListener('paste', (e) => {
		const items = e.clipboardData && e.clipboardData.items;
		if (!items) return;
		for (let i = 0; i < items.length; i++) {
			const it = items[i];
			if (it.kind === 'file' && it.type && it.type.indexOf('image/') === 0) {
				let file = it.getAsFile();
				if (!file) continue;
				if (!file.name) file = new File([file], `pasted-${Date.now()}.png`, { type: file.type || 'image/png' });
				e.preventDefault(); // don't dump the image's raw data into the textarea
				uploadMsgFile(file); // includes any text already typed as the caption
				return;
			}
		}
	});
	input?.focus();
	const fileInput = $('#sp-msg-file', thread);
	fileInput?.addEventListener('change', () => { if (fileInput.files && fileInput.files[0]) { uploadMsgFile(fileInput.files[0]); fileInput.value = ''; } });
	$('.sp-msg-back', thread)?.addEventListener('click', () => spMsgBackToList());
	$('.sp-msg-manage', thread)?.addEventListener('click', () => openGroupManage(conv));
	// Slowly scroll a too-long name back and forth so it can be read in full. Deferred past layout (and
	// past the mobile full-screen overlay class) so the available width is measured correctly.
	requestAnimationFrame(() => spMsgMarqueeTitle(thread));
	$('.sp-msg-delete', thread)?.addEventListener('click', async () => {
		if (!confirm('Delete this conversation from your list? It will reappear if a new message arrives.')) return;
		const r = await spAjax('site_pulse_messages_delete_thread', { conversation_id: conv.id });
		if (r.success) { spMsgBackToList(); }
		else alert(r.data?.message || 'Could not delete.');
	});
	spMsgWireSearch(thread);
	spMsgInitMic(thread);

	// "Seen" dwell: track the latest message and arm the 3s timer.
	const msgs = data.messages || [];
	_spMsg.maxId = msgs.length ? parseInt(msgs[msgs.length - 1].id, 10) : 0;
	_spMsg.threadSig = spMsgThreadSig(data); // so the first poll doesn't needlessly re-render
	spMsgStartSeenTimer(conv.id || _spMsg.cid);
}

// ── In-thread search (find bar): highlight matches in the current conversation + prev/next ──────────
let _spMsgSearch = { hits: [], idx: -1, query: '' };

function spMsgWireSearch(thread) {
	_spMsgSearch = { hits: [], idx: -1, query: '' }; // fresh per thread render
	const btn = $('.sp-msg-search-btn', thread);
	const bar = $('#sp-msg-search-bar', thread);
	if (!btn || !bar) return;
	const input = $('.sp-msg-search-input', bar);

	const open  = () => { bar.hidden = false; input.focus(); input.select(); };
	const close = () => {
		bar.hidden = true;
		input.value = '';
		_spMsgSearch = { hits: [], idx: -1, query: '' };
		spMsgClearHighlights($('#sp-msg-bubbles', thread));
	};

	btn.addEventListener('click', () => { if (bar.hidden) open(); else close(); });
	$('.sp-msg-search-close', bar)?.addEventListener('click', close);
	$('.sp-msg-search-prev', bar)?.addEventListener('click', () => spMsgSearchStep(-1));
	$('.sp-msg-search-next', bar)?.addEventListener('click', () => spMsgSearchStep(1));
	input?.addEventListener('input', () => spMsgRunSearch(input.value));
	input?.addEventListener('keydown', (e) => {
		if (e.key === 'Enter') { e.preventDefault(); spMsgSearchStep(e.shiftKey ? -1 : 1); }
		else if (e.key === 'Escape') { e.preventDefault(); close(); }
	});
}

function spMsgRunSearch(query) {
	const bubbles = $('#sp-msg-bubbles');
	if (!bubbles) return;
	query = (query || '').trim();
	_spMsgSearch.query = query;
	const hits = spMsgHighlight(bubbles, query);
	_spMsgSearch.hits = hits;
	_spMsgSearch.idx = hits.length ? 0 : -1;
	if (hits.length) spMsgSearchGoto(0);
	spMsgSearchUpdateCount();
}

function spMsgSearchStep(dir) {
	const n = _spMsgSearch.hits.length;
	if (!n) return;
	let i = _spMsgSearch.idx + dir;
	if (i < 0) i = n - 1;
	if (i >= n) i = 0;
	spMsgSearchGoto(i);
}

function spMsgSearchGoto(i) {
	const hits = _spMsgSearch.hits;
	if (!hits.length) return;
	hits.forEach(h => h.classList.remove('current'));
	_spMsgSearch.idx = i;
	const el = hits[i];
	if (el) { el.classList.add('current'); el.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
	spMsgSearchUpdateCount();
}

function spMsgSearchUpdateCount() {
	const c = $('.sp-msg-search-count');
	if (!c) return;
	const n = _spMsgSearch.hits.length;
	c.textContent = n ? `${_spMsgSearch.idx + 1}/${n}` : (_spMsgSearch.query ? 'No results' : '');
}

// Restore the original text nodes by unwrapping any highlight <mark>s.
function spMsgClearHighlights(container) {
	if (!container) return;
	container.querySelectorAll('mark.sp-msg-search-hit').forEach(m => {
		const parent = m.parentNode;
		if (!parent) return;
		parent.replaceChild(document.createTextNode(m.textContent), m);
		parent.normalize();
	});
}

// Wrap every case-insensitive match of `query` in the message bodies; returns the <mark>s in order.
function spMsgHighlight(container, query) {
	spMsgClearHighlights(container);
	const hits = [];
	if (!query) return hits;
	const q = query.toLowerCase();
	container.querySelectorAll('.sp-msg-bubble-body').forEach(body => {
		const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT, null);
		const nodes = [];
		while (walker.nextNode()) nodes.push(walker.currentNode);
		nodes.forEach(node => {
			const text = node.nodeValue;
			const lower = text.toLowerCase();
			if (lower.indexOf(q) === -1) return;
			const frag = document.createDocumentFragment();
			let pos = 0, idx = lower.indexOf(q, 0);
			while (idx !== -1) {
				if (idx > pos) frag.appendChild(document.createTextNode(text.slice(pos, idx)));
				const mark = document.createElement('mark');
				mark.className = 'sp-msg-search-hit';
				mark.textContent = text.slice(idx, idx + q.length);
				frag.appendChild(mark);
				hits.push(mark);
				pos = idx + q.length;
				idx = lower.indexOf(q, pos);
			}
			if (pos < text.length) frag.appendChild(document.createTextNode(text.slice(pos)));
			node.parentNode.replaceChild(frag, node);
		});
	});
	return hits;
}

// The 10s poll swaps the bubbles' innerHTML, wiping highlights — re-apply the active search afterward.
function spMsgReapplySearch() {
	const bar = $('#sp-msg-search-bar');
	if (!bar || bar.hidden || !_spMsgSearch.query) return;
	const prev = _spMsgSearch.idx;
	const hits = spMsgHighlight($('#sp-msg-bubbles'), _spMsgSearch.query);
	_spMsgSearch.hits = hits;
	_spMsgSearch.idx = hits.length ? Math.min(prev < 0 ? 0 : prev, hits.length - 1) : -1;
	if (_spMsgSearch.idx >= 0) hits[_spMsgSearch.idx].classList.add('current');
	spMsgSearchUpdateCount();
}

// Lightweight refresh used by the poll — swap just the bubbles so the composer text isn't lost.
async function refreshMsgThread(cid) {
	if (_spMsg.cid !== cid || _spMsg.editing) return; // don't clobber an open inline edit
	try {
		const res = await spAjax('site_pulse_messages_thread', { conversation_id: cid });
		if (!res.success) return;
		const bubbles = $('#sp-msg-bubbles');
		if (!bubbles) return;
		// Nothing changed since the last render → leave the DOM (and scroll) untouched. This is the
		// common poll outcome and skipping it is what stops the every-10s flicker.
		const sig = spMsgThreadSig(res.data);
		if (sig === _spMsg.threadSig) return;
		_spMsg.threadSig = sig;

		const isGroup = !!(res.data.conversation && res.data.conversation.is_group);
		const msgs = res.data.messages || [];
		const atBottom = Math.abs(bubbles.scrollHeight - bubbles.scrollTop - bubbles.clientHeight) < 40;
		const prevTop = bubbles.scrollTop;
		bubbles.innerHTML = msgs.map(m => msgBubbleHTML(m, isGroup)).join('') + spMsgReceiptHTML(res.data);
		// Keep the viewport steady: snap to bottom only if they were already there, else hold position.
		// Bottom-snap must defer past layout (iOS lays bubbles out a tick late), or it lands a few up.
		if (atBottom) spMsgScrollBottom(bubbles); else bubbles.scrollTop = prevTop;
		spMsgReapplySearch(); // keep highlights alive across the poll re-render

		// New messages arrived while viewing → they need their own 3s dwell before counting as seen.
		const newMax = msgs.length ? parseInt(msgs[msgs.length - 1].id, 10) : 0;
		if (newMax > _spMsg.maxId) { _spMsg.maxId = newMax; spMsgStartSeenTimer(cid); }
	} catch (e) {}
}

// Robustly pin the bubbles scroller to the bottom. iOS Safari hasn't laid out freshly-inserted bubbles
// on the same tick, so a single synchronous scrollTop lands short ("rewinds" a few messages up); set it
// now, after layout (double rAF), again shortly after, and once any images finish loading.
function spMsgScrollBottom(bubbles) {
	if (!bubbles) return;
	const go = () => { bubbles.scrollTop = bubbles.scrollHeight; };
	go();
	requestAnimationFrame(() => requestAnimationFrame(go));
	setTimeout(go, 80);
	bubbles.querySelectorAll('img').forEach(img => { if (!img.complete) img.addEventListener('load', go, { once: true }); });
}

async function sendMsg() {
	const input = $('#sp-msg-input');
	const cid = _spMsg.cid;
	if (!input || !cid) return;
	const body = input.value.trim();
	if (!body) return;
	const replyId = (_spMsg.replyTo && _spMsg.replyTo.id) || 0;
	input.value = '';
	input.focus();
	spMsgCancelReply(); // clear the reply bar as the message goes out
	try {
		const res = await spAjax('site_pulse_messages_send', { conversation_id: cid, body, reply_to_id: replyId });
		if (res.success) {
			const bubbles = $('#sp-msg-bubbles');
			if (bubbles) spMsgAppendBubbleOnce(bubbles, res.data.message);
			loadMsgConversations();
		} else { alert(res.data?.message || 'Could not send.'); input.value = body; }
	} catch (e) { alert('Could not send.'); input.value = body; }
}

function renderMsgThreadEmpty() {
	const thread = $('#sp-msg-thread');
	if (thread) thread.innerHTML = '<div class="sp-msg-empty">Select a conversation, or start a new message.</div>';
}

// "New Message" — searchable people picker. One person → DM; two or more → a (named) group.
async function openMsgNew() {
	const backdrop = spFormModal();
	backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><div class="sp-loading"></div></div>';
	let res;
	try { res = await spAjax('site_pulse_messages_contacts', {}); } catch (e) {}
	if (!res || !res.success) {
		const why = (res && res.data && res.data.message) ? ' (' + res.data.message + ')' : ' Make sure the latest update is deployed.';
		backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><p>Could not load contacts.' + esc(why) + '</p></div>';
		return;
	}
	const contacts = res.data.contacts || [];

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>New Message</h3><button type="button" class="unique sp-btn sp-btn-ghost sp-msg-new-cancel">Cancel</button></div>';
	html += '<p class="sp-help-text" style="margin:0 0 10px;">Pick one person for a direct message, or several for a group.</p>';
	html += '<input type="text" class="sp-input" id="sp-msg-new-search" placeholder="Search people…" style="margin-bottom:10px;">';
	html += '<div id="sp-msg-new-grouprow" hidden style="margin-bottom:10px;"><input type="text" class="sp-input" id="sp-msg-new-title" placeholder="Group name (optional)"></div>';
	html += '<div class="sp-msg-contacts" id="sp-msg-new-list">';
	contacts.forEach(c => {
		html += `<label class="sp-msg-contact-pick"><input type="checkbox" class="sp-msg-pick" value="${c.id}" data-name="${esc(c.name)}"><span class="sp-msg-contact-name">${esc(c.name)}</span></label>`;
	});
	html += '</div>';
	html += '<div class="sp-report-form-actions" style="margin-top:12px;"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-msg-new-start" disabled>Start conversation</button></div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);

	const search = $('#sp-msg-new-search', backdrop);
	const startBtn = $('#sp-msg-new-start', backdrop);
	const groupRow = $('#sp-msg-new-grouprow', backdrop);
	const sync = () => {
		const picked = $$('.sp-msg-pick:checked', backdrop);
		startBtn.disabled = picked.length === 0;
		groupRow.hidden = picked.length < 2;
		startBtn.textContent = picked.length >= 2 ? `Start group (${picked.length})` : 'Start conversation';
	};
	$$('.sp-msg-pick', backdrop).forEach(cb => cb.addEventListener('change', sync));
	search?.addEventListener('input', () => {
		const q = search.value.toLowerCase();
		$$('.sp-msg-contact-pick', backdrop).forEach(l => {
			const cb = l.querySelector('.sp-msg-pick');
			l.style.display = cb && cb.dataset.name.toLowerCase().includes(q) ? '' : 'none';
		});
	});
	$('.sp-msg-new-cancel', backdrop)?.addEventListener('click', () => closeFormModal());
	startBtn?.addEventListener('click', async () => {
		const picked = $$('.sp-msg-pick:checked', backdrop).map(cb => parseInt(cb.value, 10));
		if (!picked.length) return;
		startBtn.disabled = true;
		try {
			let cid;
			if (picked.length === 1) {
				const r = await spAjax('site_pulse_messages_start_dm', { user_id: picked[0] });
				if (!r.success) throw new Error(r.data?.message);
				cid = r.data.conversation_id;
			} else {
				const title = $('#sp-msg-new-title', backdrop)?.value || '';
				const r = await spAjax('site_pulse_messages_create_group', { user_ids: JSON.stringify(picked), title });
				if (!r.success) throw new Error(r.data?.message);
				cid = r.data.conversation_id;
			}
			closeFormModal();
			openMsgConversation(cid);
		} catch (e) { alert((e && e.message) || 'Could not start conversation.'); startBtn.disabled = false; }
	});
	search?.focus();
}

// Group members dialog — view members; the creator can add/remove; anyone can leave.
async function openGroupManage(conv) {
	const backdrop = spFormModal();
	backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><div class="sp-loading"></div></div>';
	let cres;
	try { cres = await spAjax('site_pulse_messages_contacts', {}); } catch (e) {}
	const allContacts = (cres && cres.success) ? (cres.data.contacts || []) : [];
	const creatorId = parseInt(conv.created_by, 10);
	const isCreator = creatorId === parseInt(D.userId, 10);
	const memberIds = new Set((conv.participants || []).map(p => p.id));

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += `<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${esc(conv.title || 'Group')}</h3><button type="button" class="unique sp-btn sp-btn-ghost sp-msg-manage-cancel">Close</button></div>`;
	html += '<h4 style="margin:0 0 8px;">Members</h4><div class="sp-msg-members">';
	(conv.participants || []).forEach(p => {
		const canRemove = isCreator && p.id !== creatorId;
		html += `<div class="sp-msg-member"><span>${esc(p.name)}${p.id === creatorId ? ' · creator' : ''}</span>${canRemove ? `<button type="button" class="unique sp-btn sp-btn-ghost sp-msg-remove" data-uid="${p.id}">Remove</button>` : ''}</div>`;
	});
	html += '</div>';
	if (isCreator) {
		const addable = allContacts.filter(c => !memberIds.has(c.id));
		if (addable.length) {
			html += '<h4 style="margin:14px 0 8px;">Add member</h4><div class="sp-msg-addrow"><select class="sp-select" id="sp-msg-add-select">';
			addable.forEach(c => { html += `<option value="${c.id}">${esc(c.name)}${c.meta ? ' — ' + esc(c.meta) : ''}</option>`; });
			html += '</select><button type="button" class="unique sp-btn sp-btn-primary" id="sp-msg-add-btn">Add</button></div>';
		}
	}
	html += '<div class="sp-report-form-actions" style="margin-top:16px;"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-msg-leave-btn">Leave group</button></div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);

	// Refresh thread header + reopen this dialog with fresh membership after a change.
	const reload = async () => {
		const r = await spAjax('site_pulse_messages_thread', { conversation_id: conv.id });
		if (r.success) { _spMsg.meta = r.data.conversation; renderMsgThread(r.data); closeFormModal(); openGroupManage(r.data.conversation); }
	};

	$('.sp-msg-manage-cancel', backdrop)?.addEventListener('click', () => closeFormModal());
	$('#sp-msg-add-btn', backdrop)?.addEventListener('click', async () => {
		const uid = $('#sp-msg-add-select', backdrop)?.value;
		if (!uid) return;
		const r = await spAjax('site_pulse_messages_add_member', { conversation_id: conv.id, user_id: uid });
		if (r.success) reload(); else alert(r.data?.message || 'Could not add.');
	});
	$$('.sp-msg-remove', backdrop).forEach(b => b.addEventListener('click', async () => {
		const r = await spAjax('site_pulse_messages_remove_member', { conversation_id: conv.id, user_id: b.dataset.uid });
		if (r.success) reload(); else alert(r.data?.message || 'Could not remove.');
	}));
	$('#sp-msg-leave-btn', backdrop)?.addEventListener('click', async () => {
		if (!confirm('Leave this group?')) return;
		const r = await spAjax('site_pulse_messages_leave', { conversation_id: conv.id });
		if (r.success) { closeFormModal(); spMsgBackToList(); }
		else alert(r.data?.message || 'Could not leave.');
	});
}

// Dashboard widget: the logged-in user's own mileage for the current month.
async function loadWidgetMileage() {
	const body = $('#sp-widget-mileage-body');
	if (!body) return;
	const now = new Date();
	const start = `${now.getFullYear()}-${mPad2(now.getMonth() + 1)}-01`;
	const end = mIso(now);
	try {
		const res = await spAjax('site_pulse_get_mileage_entries', { start, end });
		const entries = (res.success && res.data.entries) ? res.data.entries : [];
		if (!entries.length) { body.innerHTML = '<div class="sp-widget-empty">No trips logged this month yet.</div>'; return; }
		const miles = entries.reduce((s, e) => s + parseFloat(e.total_miles || 0), 0);
		const reimb = entries.reduce((s, e) => s + parseFloat(e.reimbursement_amount || 0), 0);
		const tolls = entries.reduce((s, e) => s + parseFloat(e.total_tolls || 0), 0);
		const trailer = entries.reduce((s, e) => s + parseFloat(e.total_trailer || 0), 0);
		body.innerHTML = `<div class="sp-mileage-widget-stats">
			<div><div class="sp-card-value">${miles.toFixed(1)}</div><div class="sp-card-label">Miles</div></div>
			<div><div class="sp-card-value">$${reimb.toFixed(2)}</div><div class="sp-card-label">Reimbursement</div></div>
			${trailer > 0 ? `<div><div class="sp-card-value">$${trailer.toFixed(2)}</div><div class="sp-card-label">Trailer</div></div>` : ''}
			${tolls > 0 ? `<div><div class="sp-card-value">$${tolls.toFixed(2)}</div><div class="sp-card-label">Tolls</div></div>` : ''}
			<div><div class="sp-card-value">${entries.length}</div><div class="sp-card-label">Days logged</div></div>
		</div>`;
	} catch (e) { body.innerHTML = '<div class="sp-widget-empty">—</div>'; }
}

async function loadWidgetReports() {
	const body = $('#sp-widget-reports-body');
	if (!body) return;

	try {
		const res = await spAjax('site_pulse_get_reports', { limit: 5, scope: 'own' });
		if (!res.success || !res.data.reports || res.data.reports.length === 0) {
			body.innerHTML = '<div class="sp-widget-empty">No reports yet.</div>';
			return;
		}

		body.innerHTML = res.data.reports.map(r => `
			<div class="sp-widget-item" data-report-id="${r.id}">
				<div>
					<div class="sp-widget-item-title">${esc(r.location_name || 'Unknown')}</div>
					<div class="sp-widget-item-meta">${formatDate(r.report_period_start)}${r.author_name ? ' &middot; ' + esc(r.author_name) : ''}</div>
				</div>
				${reportStatusPill(r.status)}
			</div>
		`).join('');

		markUniqueSpans(body);

		$$('.sp-widget-item', body).forEach(item => {
			item.addEventListener('click', () => {
				// These are the viewer's OWN reports → open them in My Reports when possible.
				const navTarget = spCanSubmitReports() ? 'reports-my' : 'reports-review';
				activatePanel(navTarget);
				currentReportList = res.data.reports;
				showReportDetail(item.dataset.reportId, navTarget === 'reports-review' ? 'review' : '');
			});
		});
	} catch (err) {
		body.innerHTML = '<div class="sp-widget-empty">Error loading reports.</div>';
	}
}

async function loadWidgetActions() {
	const body = $('#sp-widget-actions-body');
	if (!body) return;

	try {
		const res = await spAjax('site_pulse_get_action_items', { mine: '1', status: 'open' });
		if (!res.success || !res.data.items || res.data.items.length === 0) {
			body.innerHTML = '<div class="sp-widget-empty">No open action items.</div>';
			return;
		}

		const items = res.data.items.slice(0, 5);
		const priorityOrder = { high: 0, medium: 1, low: 2 };
		items.sort((a, b) => (priorityOrder[a.priority] ?? 1) - (priorityOrder[b.priority] ?? 1));

		body.innerHTML = items.map(item => `
			<div class="sp-widget-action-item">
				<div class="sp-widget-action-bar priority-${item.priority}"></div>
				<div>
					<div class="sp-widget-action-category"><span class="unique">${esc(item.category)}${item.location_name ? ' &middot; ' + esc(item.location_name) : ''}</span></div>
					<div class="sp-widget-action-desc">${esc(item.description)}</div>
				</div>
			</div>
		`).join('');

		if (res.data.items.length > 5) {
			body.innerHTML += `<div class="sp-widget-empty" style="padding:8px 0;font-size:13px;">+ ${res.data.items.length - 5} more items</div>`;
		}

		markUniqueSpans(body);
		// Clicking any item opens the Action Items panel (same as "View All").
		$$('.sp-widget-action-item', body).forEach(it => it.addEventListener('click', () => activatePanel('action-items')));
	} catch (err) {
		body.innerHTML = '<div class="sp-widget-empty">Error loading action items.</div>';
	}
}


/*--------------------------------------------------------------
# Reports — List
--------------------------------------------------------------*/

function initReports() {
	const list = $('#sp-reports-list');
	if (!list) return;

	loadReports();

	const newBtn = $('#sp-new-report-btn');
	if (newBtn) {
		newBtn.addEventListener('click', () => showReportForm());
	}

	$('#sp-filter-template')?.addEventListener('change', () => loadReports());

	$$('#sp-filter-date-start, #sp-filter-date-end').forEach(el => {
		el.addEventListener('change', () => loadReports());
	});
}

async function loadReports() {
	const list = $('#sp-reports-list');
	if (!list) return;

	list.innerHTML = '<div class="sp-loading"></div>';

	const filters = {
		scope: 'own', // the "My Reports" panel is always strictly the viewer's own reports
		template_id: $('#sp-filter-template')?.value || '',
		period_start: $('#sp-filter-date-start')?.value || '',
		period_end: $('#sp-filter-date-end')?.value || '',
	};

	try {
		const res = await spAjax('site_pulse_get_reports', filters);
		if (res.success) {
			renderReportList(list, res.data.reports);
		} else {
			list.innerHTML = '<div class="sp-empty-state"><p>Error loading reports.</p></div>';
		}
	} catch (err) {
		list.innerHTML = '<div class="sp-empty-state"><p>Error loading reports.</p></div>';
	}
}

const REPORTS_PAGE_SIZE = 16;

function reportCardHTML(r) {
	return `
		<div class="sp-tile sp-report-tile sp-button" data-report-id="${r.id}">
			<div class="sp-report-tile-left">
				<div class="sp-report-tile-title">${esc(r.template_role === 'supervisor' ? (r.author_name || 'Supervisor Report') : (r.location_name || 'Unknown Location'))}</div>
				<div class="sp-report-tile-meta">
					<span>${formatDate(r.report_period_start)}</span>
					${(r.template_role !== 'supervisor' && r.author_name) ? `<span aria-hidden="true">&#9642;</span><span>${esc(r.author_name)}</span>` : ''}
				</div>
			</div>
			<div class="sp-report-tile-right">
				${reportStatusPill(r.status)}
			</div>
		</div>
	`;
}

// Render report cards 16 at a time with a full-width "View More" button that reveals the next 16.
// currentReportList holds the FULL list so the detail view's Previous/Next still traverse everything.
// onCardClick(id, report) handles a card tap.
function renderReportCards(container, reports, onCardClick) {
	currentReportList = reports || [];
	if (!reports || reports.length === 0) {
		container.innerHTML = '<div class="sp-empty-state"><p>No reports found.</p></div>';
		return;
	}

	container.innerHTML = '';
	let shown = 0;

	const showNext = () => {
		$('.sp-reports-more', container)?.remove();

		const batch = reports.slice(shown, shown + REPORTS_PAGE_SIZE);
		container.insertAdjacentHTML('beforeend', batch.map(reportCardHTML).join(''));
		shown += batch.length;

		// Wire only the freshly added cards (already-bound ones are flagged).
		$$('.sp-report-tile', container).forEach(card => {
			if (card._spBound) return;
			card._spBound = true;
			card.addEventListener('click', () => onCardClick(card.dataset.reportId, reports.find(r => String(r.id) === card.dataset.reportId)));
		});

		const remaining = reports.length - shown;
		if (remaining > 0) {
			container.insertAdjacentHTML('beforeend',
				`<div class="sp-reports-more"><button type="button" class="unique sp-btn sp-btn-secondary sp-reports-more-btn">View More (${remaining})</button></div>`);
			$('.sp-reports-more-btn', container)?.addEventListener('click', showNext);
		}
		markUniqueSpans(container);
	};

	showNext();
}

function renderReportList(container, reports, prefix = '') {
	renderReportCards(container, reports, (id, report) => {
		if (report && report.status === 'draft') {
			showReportForm(report);
		} else {
			showReportDetail(id);
		}
	});
}


/*--------------------------------------------------------------
# Reports — Form
--------------------------------------------------------------*/

// True when the viewer can submit at least one report type — the new per-report submit caps
// (submit_report_{slug}) OR the legacy blanket submit_reports cap. Gods hold every per-report cap.
function spCanSubmitReports() {
	const c = D.userCaps || [];
	return c.includes('submit_reports') || c.some(x => x.indexOf('submit_report_') === 0);
}

async function showReportForm(existingReport = null, chosenTemplateId = null) {
	const wrap = $('#sp-report-form-wrap');
	const listWrap = $('#sp-reports-list');
	const detailWrap = $('#sp-report-detail-wrap');
	if (!wrap) return;

	const panel = $('#sp-panel-reports-my');
	if (panel) {
		$$('.sp-panel-header, .sp-report-filters, .sp-reports-list, .sp-report-detail-wrap', panel).forEach(el => el.classList.add('sp-hidden'));
	}
	wrap.classList.remove('sp-hidden');
	wrap.hidden = false;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_get_dashboard', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading form.</p>'; return; }

		const templates = res.data.templates || [];
		if (templates.length === 0) {
			wrap.innerHTML = '<div class="sp-empty-state"><p>No report templates available.</p></div>';
			return;
		}

		// The reports this user may submit — driven by the per-report submit caps (submit_report_{slug}),
		// with legacy submit_reports honored for their role's report. >1 → a type picker appears above the form.
		const _caps = D.userCaps || [];
		const submittable = templates.filter(t =>
			_caps.includes('submit_report_' + t.slug) ||
			(_caps.includes('submit_reports') && t.required_role_slug === D.userRole)
		);

		// Starting a brand-new report with more than one report type available → ask WHICH first,
		// instead of dropping the user into whichever one happens to sort first.
		if (!existingReport && !chosenTemplateId && submittable.length > 1) {
			renderReportTypeChooser(wrap, submittable);
			return;
		}

		const pool = submittable.length ? submittable : templates;
		const template = (chosenTemplateId && pool.find(t => t.id == chosenTemplateId))
			|| pool.find(t => t.required_role_slug === D.userRole)
			|| pool[0];
		let fields = [];
		let answers = {};
		let previousReport = null;

		if (existingReport) {
			const detail = await spAjax('site_pulse_get_report_detail', { report_id: existingReport.id });
			if (detail.success) {
				fields = detail.data.fields || [];
				(detail.data.answers || []).forEach(a => { answers[a.field_key] = a.answer_text || ''; });
				previousReport = detail.data.previous_report || null;
			}
		} else {
			const tplData = await loadTemplateFields(template.id);
			fields = tplData.fields;
			previousReport = tplData.previousReport;
		}

		// No picker when editing an existing report (its type is fixed).
		renderReportForm(wrap, template, fields, answers, existingReport, previousReport, existingReport ? [] : submittable);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading form.</p>';
	}
}

// The "which report?" step shown when a user can submit more than one type. Picking one opens that
// report's form (showReportForm with the chosen template id); Back returns to the reports list.
function renderReportTypeChooser(wrap, submittable) {
	let html = '<div class="sp-card-header sp-header-grid sp-report-form-header">';
	html += '<h3>New Report</h3>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-form-back-btn">Back</button>';
	html += '</div>';
	html += '<div class="sp-report-type-choices">';
	submittable.forEach(t => {
		const freq = ({ daily: 'Daily', weekly: 'Weekly', biweekly: 'Bi-Weekly', monthly: 'Monthly', quarterly: 'Quarterly' })[t.frequency] || '';
		html += `<button type="button" class="unique sp-report-type-choice" data-template-id="${t.id}">`;
		html += `<span class="sp-report-type-choice-name">${esc(t.name)}</span>`;
		if (t.description) html += `<span class="sp-report-type-choice-desc">${esc(t.description)}</span>`;
		else if (freq) html += `<span class="sp-report-type-choice-desc">${esc(freq)}</span>`;
		html += '</button>';
	});
	html += '</div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-report-type-choice', wrap).forEach(btn => {
		btn.addEventListener('click', () => showReportForm(null, btn.dataset.templateId));
	});
	$('.sp-form-back-btn', wrap)?.addEventListener('click', () => hideReportForm());
}

async function loadTemplateFields(templateId) {
	try {
		const res = await spAjax('site_pulse_get_template_fields', { template_id: templateId });
		if (!res.success) return { fields: [], previousReport: null };
		return {
			fields: res.data.fields || [],
			previousReport: res.data.previous_report || null,
		};
	} catch (e) {
		return { fields: [], previousReport: null };
	}
}

function renderReportForm(wrap, template, fields, answers, existingReport, previousReport = null, submittableList = []) {
	const previousAnswers = previousReport?.answers || {};
	const previousDate = previousReport ? formatDate(previousReport.date) : '';
	const today = new Date();
	const reportDate = existingReport?.report_period_start || toDateStr(today);

	let html = '<div class="sp-card-header sp-header-grid sp-report-form-header">';
	html += `<h3>${esc(template.name)}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-form-back-btn">Back</button>';
	html += '</div>';

	// Report-type picker — only when the user can submit more than one report (e.g. GM + Nightly).
	if (submittableList.length > 1) {
		html += '<div class="sp-form-group sp-report-type-picker"><label for="sp-report-type-sel">Report</label>'
			+ `<select id="sp-report-type-sel" class="sp-select">${submittableList.map(t => `<option value="${t.id}"${t.id == template.id ? ' selected' : ''}>${esc(t.name)}</option>`).join('')}</select></div>`;
	}

	html += '<form id="sp-report-form" class="sp-card-body">';
	html += `<input type="hidden" name="template_id" value="${template.id}">`;
	html += `<input type="hidden" name="report_id" value="${existingReport?.id || ''}">`;
	html += `<input type="hidden" name="period_start" value="${reportDate}">`;
	html += `<input type="hidden" name="period_end" value="${reportDate}">`;

	// Auto-fill header
	const headerFields = D.reportHeaderFields || [];
	const headerCount = headerFields.length + 2;
	html += `<div class="sp-tile sp-card-grid sp-report-header-grid">`;

	headerFields.forEach(hf => {
		const val = calcHeaderValue(hf.calc);
		html += `<div><div class="sp-card-label">${esc(hf.label)}</div><div class="sp-card-value" style="font-size:16px;">${esc(val)}</div></div>`;
	});

	html += `<div><div class="sp-card-label">Date</div><div class="sp-card-value" style="font-size:16px;">${formatDate(reportDate)}</div></div>`;
	html += `<div><div class="sp-card-label">Location</div><div class="sp-card-value" style="font-size:16px;">${esc(D.locationName || 'Not assigned')}</div></div>`;
	html += '</div>';

	let currentSection = '';
	fields.forEach(f => {
		if (f.section && f.section !== currentSection) {
			if (currentSection) html += '</div>';
			currentSection = f.section;
			html += '<div class="sp-form-section">';
			html += `<h4 class="sp-form-section-title">${esc(currentSection)}</h4>`;
		}

		const val = answers[f.field_key] || '';
		const prior = previousAnswers[f.field_key] || '';
		html += '<div class="sp-form-group sp-card-item">';
		html += `<label for="sp-field-${f.field_key}">${esc(f.label)}${f.is_required == 1 ? ' <span class="sp-text-danger">*</span>' : ''}</label>`;
		if (prior) {
			html += `<div class="sp-prior-answer"><span class="sp-prior-answer-date">${esc(previousDate)}:</span> ${esc(prior)}</div>`;
		}

		switch (f.field_type) {
			case 'textarea':
				html += `<textarea id="sp-field-${f.field_key}" name="answers[${f.field_key}]" class="sp-textarea" placeholder="${esc(f.placeholder || '')}"${f.is_required == 1 ? ' required' : ''}>${esc(val)}</textarea>`;
				break;
			case 'text':
				html += `<input type="text" id="sp-field-${f.field_key}" name="answers[${f.field_key}]" class="sp-input" value="${esc(val)}" placeholder="${esc(f.placeholder || '')}"${f.is_required == 1 ? ' required' : ''}>`;
				break;
			case 'number':
				html += `<input type="number" id="sp-field-${f.field_key}" name="answers[${f.field_key}]" class="sp-input" value="${esc(val)}" placeholder="${esc(f.placeholder || '')}"${f.is_required == 1 ? ' required' : ''}>`;
				break;
			case 'math':
				// Auto-calculated from other fields' values via the stored equation (in f.options).
				// Read-only, but still submitted (readonly inputs post their value). wireReportMathFields()
				// recomputes it live as the referenced fields change.
				html += `<input type="text" inputmode="decimal" readonly id="sp-field-${f.field_key}" name="answers[${f.field_key}]" class="sp-input sp-math-field" value="${esc(val)}" data-equation="${esc(f.options || '')}">`;
				break;
			case 'select':
				html += `<select id="sp-field-${f.field_key}" name="answers[${f.field_key}]" class="sp-select"${f.is_required == 1 ? ' required' : ''}>`;
				html += '<option value="">Select...</option>';
				const opts = JSON.parse(f.options || '[]');
				opts.forEach(o => {
					const selected = val === o ? ' selected' : '';
					html += `<option value="${esc(o)}"${selected}>${esc(o)}</option>`;
				});
				html += '</select>';
				break;
			case 'rating':
				html += `<select id="sp-field-${f.field_key}" name="answers[${f.field_key}]" class="sp-select"${f.is_required == 1 ? ' required' : ''}>`;
				html += '<option value="">Select rating...</option>';
				for (let i = 1; i <= 10; i++) {
					const selected = val == i ? ' selected' : '';
					html += `<option value="${i}"${selected}>${i}</option>`;
				}
				html += '</select>';
				break;
			default:
				html += `<textarea id="sp-field-${f.field_key}" name="answers[${f.field_key}]" class="sp-textarea" placeholder="${esc(f.placeholder || '')}"${f.is_required == 1 ? ' required' : ''}>${esc(val)}</textarea>`;
		}

		if (f.help_text) {
			html += `<div class="sp-help-text">${esc(f.help_text)}</div>`;
		}
		html += '</div>';
	});
	if (currentSection) html += '</div>';

	// Editing an already-submitted report: save the changes in place (status stays submitted)
	// WITHOUT re-running submit — action items may already be assigned / in progress, so we don't
	// regenerate them. A fresh report keeps the normal Save Draft + Submit flow.
	const editingSubmitted = !!(existingReport && existingReport.status === 'submitted');
	html += '<div class="sp-report-form-actions">';
	if (editingSubmitted) {
		html += '<button type="submit" class="unique sp-btn sp-btn-primary" id="sp-submit-report-btn">Save Changes</button>';
	} else {
		html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-save-draft-btn">Save Draft</button>';
		html += '<button type="submit" class="unique sp-btn sp-btn-primary" id="sp-submit-report-btn">Submit Report</button>';
	}
	html += '</div>';
	html += '</form>';

	wrap.innerHTML = html;

	markUniqueSpans(wrap);

	$('.sp-form-back-btn', wrap)?.addEventListener('click', () => hideReportForm());
	// Switching report type reloads the blank form for the chosen template.
	$('#sp-report-type-sel', wrap)?.addEventListener('change', (e) => showReportForm(null, e.target.value));
	$('#sp-save-draft-btn')?.addEventListener('click', () => saveReport('save'));
	$('#sp-report-form')?.addEventListener('submit', (e) => {
		e.preventDefault();
		saveReport(editingSubmitted ? 'save' : 'submit');
	});
	wireReportMathFields(wrap, fields);
}

// Evaluate a "Math" field's equation (field keys + arithmetic) against the form's current values.
// Field keys are replaced with their numeric value (blank/non-numeric → 0); the result must reduce to
// pure arithmetic (digits, + - * / % . ( ) only) before it's evaluated. Returns '' if it can't compute.
function spEvalMathExpr(equation, values) {
	if (!equation) return '';
	const expr = String(equation).replace(/[A-Za-z_][A-Za-z0-9_]*/g, (tok) => {
		const n = parseFloat(values[tok]);
		return isFinite(n) ? String(n) : '0';
	});
	if (expr.trim() === '' || !/^[\d+\-*/%().\s]*$/.test(expr)) return '';
	try {
		const r = Function('"use strict"; return (' + expr + ');')();
		if (typeof r !== 'number' || !isFinite(r)) return '';
		return String(Math.round(r * 100) / 100);   // 2-decimal precision
	} catch (e) { return ''; }
}

// Keep every Math field live: recompute on any input in the form, and once on load. Runs a few passes
// so a Math field may reference another Math field (e.g. Grand Total = SubtotalA + SubtotalB).
function wireReportMathFields(wrap, fields) {
	const mathEls = $$('.sp-math-field', wrap);
	if (!mathEls.length) return;
	const form = $('#sp-report-form', wrap) || wrap;

	const recompute = () => {
		const values = {};
		(fields || []).forEach(f => {
			const el = wrap.querySelector(`[name="answers[${f.field_key}]"]`);
			if (el) values[f.field_key] = el.value;
		});
		for (let pass = 0; pass < 3; pass++) {
			mathEls.forEach(el => {
				const v = spEvalMathExpr(el.dataset.equation, values);
				el.value = v;
				const key = (el.name.match(/^answers\[(.+)\]$/) || [])[1];
				if (key) values[key] = v;
			});
		}
	};

	form.addEventListener('input', recompute);
	recompute();
}

let isSaving = false;

async function saveReport(actionType) {
	if (isSaving) return;
	isSaving = true;

	const form = $('#sp-report-form');
	if (!form) { isSaving = false; return; }

	const saveBtn = $('#sp-save-draft-btn');
	const submitBtn = $('#sp-submit-report-btn');
	const wrap = $('#sp-report-form-wrap');

	if (saveBtn) saveBtn.disabled = true;
	if (submitBtn) submitBtn.disabled = true;

	// Show overlay
	const overlay = document.createElement('div');
	overlay.className = 'sp-submit-overlay';
	overlay.style.cssText = 'position:fixed;inset:0;background:rgba(255,255,255,0.92);display:grid;place-items:center;z-index:9999;';
	// "Save Draft" exists only on a fresh report; its absence means this is an edit of an
	// already-submitted report, so the save-in-place copy reads "Saving changes…" not "Saving draft…".
	const isDraftSave = actionType === 'save' && !!$('#sp-save-draft-btn');
	overlay.innerHTML = '<div class="sp-submit-overlay-inner"><div class="sp-loading"></div><div class="sp-submit-message">' +
		(actionType === 'submit' ? 'Submitting report & generating action items...' : (isDraftSave ? 'Saving draft...' : 'Saving changes...')) +
		'</div></div>';
	document.body.appendChild(overlay);

	const formData = new FormData(form);
	const answers = {};
	for (const [key, val] of formData.entries()) {
		const match = key.match(/^answers\[(.+)\]$/);
		if (match) answers[match[1]] = val;
	}

	const data = {
		template_id: formData.get('template_id'),
		report_id: formData.get('report_id') || '',
		period_start: formData.get('period_start'),
		period_end: formData.get('period_end'),
		answers: answers,
		action_type: actionType,
	};

	try {
		const res = await spAjax('site_pulse_save_report', data);
		if (res.success) {
			hideReportForm();
			loadReports();
			if (actionType === 'submit' && (res.data?.pending_count || 0) > 0) {
				activatePanel('action-items');
			}
		} else {
			alert(res.data?.message || 'Error saving report.');
		}
	} catch (err) {
		alert('Error saving report. Please try again.');
	} finally {
		isSaving = false;
		if (saveBtn) saveBtn.disabled = false;
		if (submitBtn) submitBtn.disabled = false;
		if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
	}
}

function hideReportForm() {
	const wrap = $('#sp-report-form-wrap');
	const panel = $('#sp-panel-reports-my');

	if (wrap) { wrap.classList.add('sp-hidden'); wrap.hidden = true; }
	if (panel) {
		$$('.sp-panel-header, .sp-report-filters, .sp-reports-list', panel).forEach(el => el.classList.remove('sp-hidden'));
	}
	loadReports();
}


/*--------------------------------------------------------------
# Reports — Detail
--------------------------------------------------------------*/

let currentReportList = [];
let currentReportIndex = -1;
let currentReportPrefix = '';
let reviewTemplateId = '';   // which report (template id) the review panel shows
let reviewTemplateName = ''; // its name, for the panel/mobile title

async function showReportDetail(reportId, panelPrefix = '') {
	currentReportPrefix = panelPrefix;
	const panel = $(panelPrefix ? '#sp-panel-reports-review' : '#sp-panel-reports-my');
	const detailWrap = $(panelPrefix ? '#sp-review-detail-wrap' : '#sp-report-detail-wrap');
	if (!detailWrap) return;

	// Hide everything except the detail wrap
	if (panel) {
		$$('.sp-panel-header, .sp-report-filters, .sp-reports-list, .sp-report-form-wrap', panel).forEach(el => el.classList.add('sp-hidden'));
	}
	detailWrap.classList.remove('sp-hidden');
	detailWrap.hidden = false;
	detailWrap.innerHTML = '<div class="sp-loading"></div>';

	// Track position in list for prev/next
	currentReportIndex = currentReportList.findIndex(r => String(r.id) === String(reportId));

	// If we arrived without the sibling list loaded (e.g. via the dashboard "Recent Reports"
	// widget, a restored view, or a deep link), fetch it so Previous/Next work instead of sitting
	// disabled. Skipped entirely when the report is already in the in-memory list.
	if (currentReportIndex === -1) {
		try {
			const filters = panelPrefix === 'review' ? { template_id: reviewTemplateId } : { scope: 'own' };
			const lres = await spAjax('site_pulse_get_reports', filters);
			if (lres && lres.success && Array.isArray(lres.data?.reports)) {
				currentReportList = lres.data.reports;
				currentReportIndex = currentReportList.findIndex(r => String(r.id) === String(reportId));
			}
		} catch (e) {}
	}

	try {
		const res = await spAjax('site_pulse_get_report_detail', { report_id: reportId });
		if (!res.success) { detailWrap.innerHTML = '<p>Error loading report.</p>'; return; }

		const { report, answers, fields, location, author, template } = res.data;
		if (report && template) report.template_role = template.required_role_slug; // supervisor reports hide Location
		renderReportDetail(detailWrap, report, answers, fields, location, author, panelPrefix, template);
	} catch (err) {
		detailWrap.innerHTML = '<p>Error loading report.</p>';
	}
}

function renderReportDetail(wrap, report, answers, fields, location, author, panelPrefix, template) {
	const answerMap = {};
	answers.forEach(a => { answerMap[a.field_key] = a; });

	const hasPrev = currentReportIndex > 0;
	const hasNext = currentReportIndex < currentReportList.length - 1 && currentReportIndex >= 0;

	const showNewBtn = !panelPrefix && spCanSubmitReports();

	// Title header — matches the report form's header (centered title + Back).
	let html = '<div class="sp-card-header sp-header-grid sp-report-form-header">';
	html += `<h3>${esc(template?.name || 'Report')}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-back-btn">Back</button>';
	html += '</div>';

	// Everything below the header lives in the card body, so padding/styles apply uniformly
	// (matches the report form's <form class="sp-card-body">).
	html += '<div class="sp-card-body">';

	// Navigation / actions bar (Back now lives in the header above)
	html += '<div class="sp-detail-nav">';
	html += '<div class="sp-detail-nav-left">';
	if (showNewBtn) {
		html += '<button type="button" class="unique sp-btn sp-btn-primary sp-detail-new-btn">+ New Report</button>';
	}
	// Author can edit their own report (My Reports view only — the backend authorizes edits to
	// your own report; the Review view shows others' reports, which you can't edit). Sits to the
	// left of the trash button.
	const canEdit = !panelPrefix && spCanSubmitReports();
	if (canEdit) {
		html += iconBtn('edit', 'sp-detail-edit', `data-report-id="${report.id}" title="Edit report"`);
	}
	// GOD only: reassign attribution (fix a wrong AI-matched author/store), edit content (temporary —
	// for fixing PDF/JPG-imported reports), and permanently delete. The edit button shows in the
	// review (GM Reports) view, where the normal author-edit button isn't available.
	if (D.isGod) {
		html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-reassign" data-report-id="${report.id}" title="Reassign submitter / store">Reassign</button>`;
		if (panelPrefix) {
			html += iconBtn('edit', 'sp-detail-god-edit', `data-report-id="${report.id}" title="Edit report (Odin only)"`);
		}
		html += iconBtn('delete', 'sp-detail-god-delete', `data-report-id="${report.id}" title="Delete report (Odin only)"`);
	}
	// Status pill sits inline, to the right of the actions — only a draft shows one.
	html += reportStatusPill(report.status);
	html += '</div>';
	html += '<div class="sp-detail-nav-arrows">';
	html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-prev"${hasPrev ? '' : ' disabled'}>&lsaquo; Previous</button>`;
	html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-next"${hasNext ? '' : ' disabled'}>Next &rsaquo;</button>`;
	html += '</div></div>';

	// Header info — a single bar matching the report form (Period · Week · Date · Location · Submitted By).
	const headerFields = D.reportHeaderFields || [];
	const isSupervisorReport = report && report.template_role === 'supervisor';
	const cell = (l, v) => `<div><div class="sp-card-label">${esc(l)}</div><div class="sp-card-value" style="font-size:16px;">${v}</div></div>`;
	const cells = [];
	headerFields.forEach(hf => cells.push(cell(hf.label, esc(calcHeaderValue(hf.calc, report.report_period_start)))));
	cells.push(cell('Date', esc(formatDate(report.report_period_start))));
	if (!isSupervisorReport) cells.push(cell('Location', esc(location?.name || '—')));
	if (author) cells.push(cell('Submitted By', esc(author.name)));
	html += `<div class="sp-tile sp-card-grid sp-report-header-grid">`;
	html += cells.join('');
	html += '</div>';

	// Answers
	let currentSection = '';
	fields.forEach(f => {
		if (f.section && f.section !== currentSection) {
			currentSection = f.section;
			html += `<h4 class="sp-form-section-title">${esc(currentSection)}</h4>`;
		}
		const a = answerMap[f.field_key];
		html += '<div class="sp-answer-group">';
		html += `<div class="sp-answer-label">${esc(f.label)}</div>`;
		html += `<div class="sp-answer-text">${esc(a?.answer_text || '—')}</div>`;
		html += '</div>';
	});

	html += '</div>'; // close .sp-card-body

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	// Event listeners
	$('.sp-detail-back-btn', wrap)?.addEventListener('click', () => closeReportDetail(panelPrefix));
	$('.sp-detail-new-btn', wrap)?.addEventListener('click', () => showReportForm());
	$('.sp-detail-edit', wrap)?.addEventListener('click', () => showReportForm(report));
	$('.sp-detail-reassign', wrap)?.addEventListener('click', () => spOpenReassign(report, author, location, panelPrefix));
	$('.sp-detail-god-edit', wrap)?.addEventListener('click', () => spGodEditReport(report, panelPrefix));

	$('.sp-detail-god-delete', wrap)?.addEventListener('click', async (e) => {
		const btn = e.currentTarget;
		if (!confirm('Permanently delete this report, along with its answers and any action items it created? This cannot be undone.')) return;
		btn.disabled = true;
		try {
			const res = await spAjax('site_pulse_god_delete_report', { report_id: btn.dataset.reportId });
			if (res.success) {
				closeReportDetail(panelPrefix);
				if (panelPrefix === 'review') loadReviewReports(); else loadReports();
			} else { alert(res.data?.message || 'Error.'); btn.disabled = false; }
		} catch (err) { alert('Error deleting report.'); btn.disabled = false; }
	});

	$('.sp-detail-prev', wrap)?.addEventListener('click', () => {
		if (currentReportIndex > 0) {
			const prevId = currentReportList[currentReportIndex - 1].id;
			showReportDetail(prevId, panelPrefix);
		}
	});

	$('.sp-detail-next', wrap)?.addEventListener('click', () => {
		if (currentReportIndex < currentReportList.length - 1) {
			const nextId = currentReportList[currentReportIndex + 1].id;
			showReportDetail(nextId, panelPrefix);
		}
	});
}

// GOD-only Reassign dialog — fix a report's attribution (submitter, and store for GM reports).
// Loads every active user so god can pick the correct person when the AI matched the wrong one.
async function spOpenReassign(report, author, location, panelPrefix) {
	const backdrop = spFormModal();
	backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><div class="sp-loading"></div></div>';

	let opts;
	try {
		const res = await spAjax('site_pulse_god_report_options', {});
		if (!res.success) throw new Error();
		opts = res.data;
	} catch (e) {
		backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><p>Could not load options.</p></div>';
		return;
	}

	const curUser = String(report.user_id || (author && author.id) || '');
	const curLoc  = String(report.location_id || (location && location.id) || '0');
	const isSup   = report.template_role === 'supervisor'; // supervisor reports aren't store-bound

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>Reassign Report</h3>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-reassign-cancel">Cancel</button></div>';

	html += '<div class="sp-form-group"><label>Submitted By</label><select class="sp-select" id="sp-reassign-user">';
	opts.users.forEach(u => {
		html += `<option value="${u.id}"${String(u.id) === curUser ? ' selected' : ''}>${esc(u.name)}${u.meta ? ' — ' + esc(u.meta) : ''}</option>`;
	});
	html += '</select></div>';

	if (!isSup) {
		html += '<div class="sp-form-group"><label>Location</label><select class="sp-select" id="sp-reassign-loc">';
		html += '<option value="0">— None —</option>';
		opts.locations.forEach(l => {
			html += `<option value="${l.id}"${String(l.id) === curLoc ? ' selected' : ''}>${esc(l.name)}</option>`;
		});
		html += '</select></div>';
	}

	html += '<div class="sp-report-form-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary sp-reassign-save">Save</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-reassign-cancel">Cancel</button>';
	html += '</div></div>';

	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);

	$$('.sp-reassign-cancel', backdrop).forEach(b => b.addEventListener('click', () => closeFormModal()));
	$('.sp-reassign-save', backdrop)?.addEventListener('click', async () => {
		const uid = $('#sp-reassign-user', backdrop)?.value || '';
		const lid = isSup ? '0' : ($('#sp-reassign-loc', backdrop)?.value || '0');
		const btn = $('.sp-reassign-save', backdrop);
		if (btn) btn.disabled = true;
		try {
			const res = await spAjax('site_pulse_god_reassign_report', { report_id: report.id, user_id: uid, location_id: lid });
			if (res.success) {
				closeFormModal();
				showReportDetail(report.id, panelPrefix);                 // reload the detail with the fix
				if (panelPrefix === 'review') loadReviewReports(); else loadReports();
			} else { alert(res.data?.message || 'Could not reassign.'); if (btn) btn.disabled = false; }
		} catch (e) { alert('Could not reassign.'); if (btn) btn.disabled = false; }
	});
}

// GOD-only editor (temporary) — fix a report imported from PDF/JPG: its date, submitter, location,
// AND field values, in one save. Self-contained modal so it works from the GM Reports (review) view.
async function spGodEditReport(report, panelPrefix) {
	const backdrop = spFormModal();
	backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><div class="sp-loading"></div></div>';

	let data, opts;
	try {
		const [detRes, optRes] = await Promise.all([
			spAjax('site_pulse_get_report_detail', { report_id: report.id }),
			spAjax('site_pulse_god_report_options', {}),
		]);
		if (!detRes.success) throw new Error();
		data = detRes.data;
		opts = (optRes && optRes.success) ? optRes.data : { users: [], locations: [] };
	} catch (e) {
		backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><p>Could not load the report.</p><div class="sp-report-form-actions"><button type="button" class="unique sp-btn sp-btn-ghost sp-ger-cancel">Close</button></div></div>';
		$('.sp-ger-cancel', backdrop)?.addEventListener('click', () => closeFormModal());
		return;
	}

	const fields = data.fields || [];
	const template = data.template || {};
	const rpt = data.report || report;
	const isSupervisor = template.required_role_slug === 'supervisor'; // supervisor reports have no location
	const answerMap = {};
	(data.answers || []).forEach(a => { answerMap[a.field_key] = a.answer_text || ''; });
	const dateVal = String(rpt.report_period_start || '').split(' ')[0].split('T')[0];

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>Edit Report</h3><button type="button" class="unique sp-btn sp-btn-ghost sp-ger-cancel">Cancel</button></div>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">Fix the imported report — date, submitter, location, and any field values.</p>';

	// Attribution row: date · submitted by · location.
	html += '<div class="sp-ger-attr">';
	html += `<div class="sp-ger-col"><label class="sp-field-label">Report date</label><input type="date" class="sp-input sp-ger-date" value="${dateVal}"></div>`;
	html += '<div class="sp-ger-col"><label class="sp-field-label">Submitted by</label><select class="sp-select sp-ger-user">'
		+ (opts.users || []).map(u => `<option value="${u.id}"${u.id === +rpt.user_id ? ' selected' : ''}>${esc(u.name)}${u.meta ? ' — ' + esc(u.meta) : ''}</option>`).join('')
		+ '</select></div>';
	if (!isSupervisor) {
		html += '<div class="sp-ger-col"><label class="sp-field-label">Location</label><select class="sp-select sp-ger-loc"><option value="0">—</option>'
			+ (opts.locations || []).map(l => `<option value="${l.id}"${l.id === +rpt.location_id ? ' selected' : ''}>${esc(l.name)}</option>`).join('')
			+ '</select></div>';
	}
	html += '</div>';

	// Field answers.
	let currentSection = '';
	fields.forEach(f => {
		if (f.section && f.section !== currentSection) {
			currentSection = f.section;
			html += `<h4 class="sp-form-section-title">${esc(currentSection)}</h4>`;
		}
		html += '<div class="sp-form-group">';
		html += `<label class="sp-field-label">${esc(f.label)}</label>`;
		html += `<textarea class="sp-input sp-ger-field" data-key="${esc(f.field_key)}" rows="3">${esc(answerMap[f.field_key] || '')}</textarea>`;
		html += '</div>';
	});

	html += '<div class="sp-report-form-actions"><button type="button" class="unique sp-btn sp-btn-primary sp-ger-save">Save Changes</button><button type="button" class="unique sp-btn sp-btn-ghost sp-ger-cancel">Cancel</button></div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);

	$$('.sp-ger-cancel', backdrop).forEach(b => b.addEventListener('click', () => closeFormModal()));
	$('.sp-ger-save', backdrop)?.addEventListener('click', async () => {
		const answers = {};
		$$('.sp-ger-field', backdrop).forEach(t => { answers[t.dataset.key] = t.value; });
		const save = $('.sp-ger-save', backdrop);
		if (save) { save.disabled = true; save.textContent = 'Saving…'; }
		try {
			const res = await spAjax('site_pulse_god_edit_report', {
				report_id: report.id,
				user_id: $('.sp-ger-user', backdrop)?.value || rpt.user_id,
				location_id: isSupervisor ? (rpt.location_id || 0) : ($('.sp-ger-loc', backdrop)?.value || 0),
				report_date: $('.sp-ger-date', backdrop)?.value || '',
				answers: answers,
			});
			if (res.success) {
				closeFormModal();
				if (panelPrefix === 'review') loadReviewReports(); else loadReports();
				showReportDetail(report.id, panelPrefix);   // reload the detail with the fixes
			} else { alert(res.data?.message || 'Could not save.'); if (save) { save.disabled = false; save.textContent = 'Save Changes'; } }
		} catch (e) { alert('Could not save.'); if (save) { save.disabled = false; save.textContent = 'Save Changes'; } }
	});
}

function closeReportDetail(panelPrefix) {
	const panel = $(panelPrefix ? '#sp-panel-reports-review' : '#sp-panel-reports-my');
	const detailWrap = $(panelPrefix ? '#sp-review-detail-wrap' : '#sp-report-detail-wrap');

	if (detailWrap) detailWrap.classList.add('sp-hidden');
	if (panel) {
		$$('.sp-panel-header, .sp-report-filters, .sp-reports-list', panel).forEach(el => el.classList.remove('sp-hidden'));
	}
}


/*--------------------------------------------------------------
# Review Panel
--------------------------------------------------------------*/

async function initReview() {
	const list = $('#sp-review-list');
	if (!list) return;

	// Default to the first report tab (the first "{Report} Reports" nav item) when none is chosen yet.
	if (!reviewTemplateId) {
		const firstBtn = $('.sp-nav-child[data-action="review-template"]');
		if (firstBtn) { reviewTemplateId = firstBtn.dataset.templateId || ''; reviewTemplateName = (firstBtn.textContent || '').trim(); }
	}

	// Await the filters so the location default (the viewer's own store) is set before the first load.
	await populateReviewFilters();
	loadReviewReports();

	$('#sp-review-filter-location')?.addEventListener('change', () => loadReviewReports());

	$$('#sp-review-filter-start, #sp-review-filter-end').forEach(el => {
		el.addEventListener('change', () => loadReviewReports());
	});
}

async function populateReviewFilters() {
	try {
		const res = await spAjax('site_pulse_get_review_filters', {});
		if (!res.success) return;

		const locSelect = $('#sp-review-filter-location');
		if (locSelect) {
			// First option is the default: "My Stores" (the viewer's managed stores) when they have a
			// team, otherwise "All Locations". The full store list follows either way, so anyone can
			// drill into any specific store.
			const myIds = Array.isArray(res.data.my_location_ids) ? res.data.my_location_ids : [];
			const first = locSelect.options[0];
			if (first) {
				if (myIds.length) { first.value = 'mine'; first.textContent = 'My Stores'; }
				else { first.value = ''; first.textContent = 'All Locations'; }
			}
			(res.data.locations || []).forEach(l => {
				const opt = document.createElement('option');
				opt.value = l.id;
				opt.textContent = l.name;
				locSelect.appendChild(opt);
			});
		}
		applyReviewLocationDefault();
	} catch (err) {}
}

// Reset the location filter to its default (the first option = "My Stores" for a viewer with a team,
// else "All Locations"). Called on first populate and on each GM/Supervisor toggle so a prior
// specific-store pick doesn't carry across the mode switch. Uses selectedIndex (not value='') because
// the first option's value may be "mine" rather than "".
function applyReviewLocationDefault() {
	const sel = $('#sp-review-filter-location');
	if (sel) sel.selectedIndex = 0;
}

async function loadReviewReports() {
	const list = $('#sp-review-list');
	if (!list) return;

	const titleEl = $('#sp-review-title');
	if (titleEl) titleEl.textContent = reviewTemplateName || 'Reports';
	spUpdateMobileTitle(); // reflect the current report name in the mobile header title

	list.innerHTML = '<div class="sp-loading"></div>';

	const locVal = $('#sp-review-filter-location')?.value || '';
	const filters = {
		template_id: reviewTemplateId, // which report (template) this tab shows
		period_start: $('#sp-review-filter-start')?.value || '',
		period_end: $('#sp-review-filter-end')?.value || '',
	};
	// "mine" → backend limits to the viewer's managed stores; a numeric id → that one store; "" → all.
	if (locVal === 'mine') filters.mine = '1';
	else if (locVal) filters.location_id = locVal;

	try {
		const res = await spAjax('site_pulse_get_reports', filters);
		if (res.success) {
			renderReviewList(list, res.data.reports);
		}
	} catch (err) {
		list.innerHTML = '<div class="sp-empty-state"><p>Error loading reports.</p></div>';
	}
}

function renderReviewList(container, reports) {
	renderReportCards(container, reports, (id) => showReportDetail(id, 'review'));
}


/*--------------------------------------------------------------
# Analytics
--------------------------------------------------------------*/

let analyticsLoaded = false;
let analyticsFiltersPopulated = false;

// God-only: build/refresh the AI report index (condense each report into a search/trend digest). Loops
// batches like the review-analyzer so a big backlog finishes on one click; the cron keeps it current.
function initReportsDigestBtn() {
	const btn = $('#sp-reports-digest-btn');
	if (btn && !btn._wired) { btn._wired = true; btn.addEventListener('click', runReportsDigest); }

	const sBtn = $('#sp-reports-ai-search-btn');
	if (sBtn && !sBtn._wired) { sBtn._wired = true; sBtn.addEventListener('click', () => runReportSearch()); }

	const aBtn = $('#sp-reports-ai-ask-btn');
	if (aBtn && !aBtn._wired) {
		aBtn._wired = true;
		aBtn.addEventListener('click', () => runReportAsk());
		// Enter in the box asks a question (the headline action); use the Search button for keyword lookups.
		$('#sp-reports-ai-q')?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runReportAsk(); } });
	}
}

// Keyword search across the report corpus (digests + raw answers). Results link into the report detail.
async function runReportSearch() {
	const box = $('#sp-reports-ai-results');
	if (!box) return;
	const q = $('#sp-reports-ai-q')?.value.trim() || '';
	const type = $('#sp-reports-ai-type')?.value || '';
	const location_id = $('#sp-reports-ai-location')?.value || '';
	const range = $('#sp-reports-ai-range')?.value || '';
	box.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_reports_search', { q, type, location_id, range });
		if (!res.success) { box.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Search failed.')}</div>`; return; }
		spFillReportLocations(res.data.locations || []);
		renderReportSearch(res.data.results || []);
	} catch (e) { box.innerHTML = '<div class="sp-empty">Search error.</div>'; }
}

let _spReportLocLoaded = false;
function spFillReportLocations(locs) {
	const sel = $('#sp-reports-ai-location');
	if (!sel || _spReportLocLoaded) return;
	_spReportLocLoaded = true;
	const cur = sel.value;
	sel.innerHTML = '<option value="">All locations</option>' + locs.map(l => `<option value="${esc(l.id)}">${esc(l.name)}</option>`).join('');
	sel.value = cur;
}

function renderReportSearch(results) {
	const box = $('#sp-reports-ai-results');
	if (!box) return;
	if (!results.length) { box.innerHTML = '<div class="sp-empty">No reports match.</div>'; return; }
	box.innerHTML = results.map(r => {
		const date = r.period_end ? new Date(r.period_end + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
		const chips = (r.categories || []).map(c => {
			const cls = c.score >= 4 ? 'sp-report-chip-good' : (c.score === 3 ? 'sp-report-chip-ok' : 'sp-report-chip-bad');
			return `<span class="unique sp-report-chip ${cls}">${esc(c.label)}${c.score ? ' ' + c.score : ''}</span>`;
		}).join('');
		return `<div class="sp-report-result" data-report-id="${r.id}">` +
			`<div class="sp-card-header sp-header sp-report-result-head"><span class="unique sp-report-result-title">${esc(r.location || r.author || 'Report')}</span>` +
			`<span class="unique sp-report-result-meta">${esc(r.type)}${date ? ' · ' + esc(date) : ''}${r.author ? ' · ' + esc(r.author) : ''}</span></div>` +
			(r.summary ? `<p class="sp-report-result-summary">${esc(r.summary)}</p>` : '') +
			(chips ? `<div class="sp-report-result-chips">${chips}</div>` : '') +
		'</div>';
	}).join('');
	markUniqueSpans(box);
	$$('.sp-report-result', box).forEach(el => el.addEventListener('click', () => {
		activatePanel('reports-review');
		showReportDetail(el.dataset.reportId, 'review');
	}));
}

// Ask AI: send the question + scope; Sonnet reads the in-scope digests and answers trends/insights.
async function runReportAsk() {
	const box = $('#sp-reports-ai-results');
	if (!box) return;
	const q = $('#sp-reports-ai-q')?.value.trim() || '';
	if (!q) { spFlash('Type a question first.'); return; }
	const type = $('#sp-reports-ai-type')?.value || '';
	const location_id = $('#sp-reports-ai-location')?.value || '';
	const range = $('#sp-reports-ai-range')?.value || '';
	box.innerHTML = '<div class="sp-loading"></div><p class="sp-help-text" style="text-align:center;margin-top:8px;">Reading the reports…</p>';
	try {
		const res = await spAjax('site_pulse_reports_ask', { q, type, location_id, range });
		if (!res.success) { box.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not answer that.')}</div>`; return; }
		renderReportAnswer(res.data.answer || '', res.data.count || 0, q);
	} catch (e) { box.innerHTML = '<div class="sp-empty">Could not answer that.</div>'; }
}

// Minimal markdown → HTML for the AI answer (escape first, then **bold**, bullets, and paragraphs).
function spLightMarkdown(t) {
	let h = esc(t).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
	const lines = h.split('\n');
	let out = '', inList = false;
	lines.forEach(line => {
		const m = line.match(/^\s*[-*•]\s+(.*)$/);
		if (m) { if (!inList) { out += '<ul>'; inList = true; } out += `<li>${m[1]}</li>`; return; }
		if (inList) { out += '</ul>'; inList = false; }
		if (line.trim() !== '') out += `<p>${line}</p>`;
	});
	if (inList) out += '</ul>';
	return out;
}

function renderReportAnswer(answer, count, question) {
	const box = $('#sp-reports-ai-results');
	if (!box) return;
	box.innerHTML =
		'<div class="sp-card-sm sp-report-answer">' +
			`<div class="sp-report-answer-q">${esc(question)}</div>` +
			`<div class="sp-report-answer-body">${spLightMarkdown(answer)}</div>` +
			`<div class="sp-report-answer-meta">Based on ${spComma(count)} report${count === 1 ? '' : 's'}.</div>` +
		'</div>';
}

async function runReportsDigest() {
	const btn = $('#sp-reports-digest-btn');
	const status = $('#sp-reports-digest-status');
	if (!btn || btn.disabled) return;
	const orig = btn.textContent;
	btn.disabled = true;
	btn.textContent = 'Indexing…';
	let errored = false, total = 0;
	try {
		while (true) {
			const res = await spAjax('site_pulse_reports_digest_batch', {});
			if (!res.success) { spFlash(res.data?.message || 'Indexing failed.'); errored = true; break; }
			total += res.data.done || 0;
			if (status) status.textContent = res.data.remaining > 0 ? `Indexed ${spComma(total)} — ${spComma(res.data.remaining)} to go…` : `Indexed ${spComma(total)}.`;
			if (res.data.error) { spFlash(res.data.error); errored = true; break; }
			if (!res.data.remaining || res.data.done === 0) break;
		}
	} catch (e) { spFlash('Indexing failed.'); errored = true; }
	btn.disabled = false;
	btn.textContent = orig;
	if (!errored) spFlash('Report index up to date.');
}

async function populateAnalyticsFilters() {
	if (analyticsFiltersPopulated) return;
	analyticsFiltersPopulated = true;

	const locSelect = $('#sp-analytics-filter-location');
	if (!locSelect) return;

	try {
		const res = await spAjax('site_pulse_get_review_filters', {});
		if (!res.success) return;
		if (res.data.locations) {
			res.data.locations.forEach(l => {
				const opt = document.createElement('option');
				opt.value = l.id;
				opt.textContent = l.name;
				locSelect.appendChild(opt);
			});
		}
	} catch (err) {}

	locSelect.addEventListener('change', () => loadAnalytics(true));
}

async function loadAnalytics(force = false) {
	if (analyticsLoaded && !force) return;
	analyticsLoaded = true;

	const locationId = $('#sp-analytics-filter-location')?.value || '';

	try {
		const res = await spAjax('site_pulse_get_analytics', { location_id: locationId });
		if (!res.success) return;
		const d = res.data;

		renderPriorityChart(d.priority);
		renderCategoryChart(d.categories);
		renderLocationsChart(d.locations);
		renderResolutionCard(d.resolution);
		renderMileageAnalytics(d.mileage);

		// Survey-results-by-location widget (only present for users who can view surveys).
		if ($('#sp-analytics-surveys')) {
			const sres = await spAjax('site_pulse_get_survey_analytics', {});
			if (sres.success) renderSurveyLocationChart(sres.data);
		}
	} catch (err) {}
}

let _spSurveyAnalytics = null;

// Stores down the side, each with a 1-5 distribution bar chart (reusing surveyBars). The
// dropdown toggles which question is charted; defaults to Overall Impression.
function renderSurveyLocationChart(data) {
	const card = $('#sp-analytics-surveys');
	if (!card) return;
	_spSurveyAnalytics = data;
	const sel  = $('#sp-analytics-survey-dim');
	const body = card.querySelector('.sp-analytics-card-body');

	// "View Surveys" jumps to the Surveys tab. Wire once.
	const linkBtn = $('#sp-analytics-survey-link');
	if (linkBtn && !linkBtn.dataset.wired) {
		linkBtn.dataset.wired = '1';
		linkBtn.addEventListener('click', () => activatePanel('surveys'));
	}

	if (!data || !data.locations || !data.locations.length) {
		if (body) body.innerHTML = '<div class="sp-widget-empty">No comment cards yet.</div>';
		return;
	}
	if (sel && !sel.dataset.filled) {
		const dims = data.dimensions || {};
		sel.innerHTML = Object.keys(dims).map(k =>
			`<option value="${esc(k)}"${k === 'impression' ? ' selected' : ''}>${esc(dims[k])}</option>`).join('');
		sel.dataset.filled = '1';
		sel.addEventListener('change', drawSurveyLocationChart);
	}
	drawSurveyLocationChart();
}

function drawSurveyLocationChart() {
	const card = $('#sp-analytics-surveys');
	if (!card || !_spSurveyAnalytics) return;
	const body = card.querySelector('.sp-analytics-card-body');
	if (!body) return;
	const dim = $('#sp-analytics-survey-dim')?.value || 'impression';
	const rows = _spSurveyAnalytics.locations.map(loc => {
		const dist = (loc.dists && loc.dists[dim]) || null;
		const bar  = surveyStackBar(dist) || '<div class="sp-survey-loc-empty">No ratings</div>';
		return `<div class="sp-survey-loc-row">` +
			`<div class="sp-survey-loc-name">${esc(loc.location)} <span class="unique sp-survey-loc-count">${loc.count}</span></div>` +
			bar +
		`</div>`;
	}).join('');
	body.innerHTML = `<div class="sp-survey-loc-chart">${rows}</div>`;
}

// One compact horizontal bar per store: red (1-3) → yellow (4) → green (5), each segment's width
// = its share of responses. Far more scalable than a 5-row chart once all stores report. Segment
// widths are exact (sum to 100%); the % text is rounded and clipped if a segment is too narrow.
function surveyStackBar(dist) {
	if (!dist) return '';
	const c = n => parseInt(dist[n], 10) || 0;
	const total = c(1) + c(2) + c(3) + c(4) + c(5);
	if (!total) return '';
	const seg = (n, cls) => {
		if (n <= 0) return '';
		const w = (n / total) * 100;
		return `<div class="sp-survey-seg ${cls}" style="width:${w}%" title="${Math.round(w)}%">${Math.round(w)}%</div>`;
	};
	return `<div class="sp-survey-stack">` +
		seg(c(1) + c(2) + c(3), 'sp-rate-bad') +
		seg(c(4), 'sp-rate-ok') +
		seg(c(5), 'sp-rate-good') +
	`</div>`;
}

// Analytics cards: Business Mileage (MTD/YTD) + Top Destinations. No-op if the cards
// aren't on the page (user without mileage caps).
function renderMileageAnalytics(m) {
	const stat = $('#sp-analytics-mileage .sp-analytics-card-body');
	if (stat) {
		const mo = (m && m.month) || {}, yt = (m && m.ytd) || {};
		stat.innerHTML = `<div class="sp-mileage-widget-stats">
			<div><div class="sp-card-value">${parseFloat(mo.miles || 0).toFixed(1)}</div><div class="sp-card-label">Miles (MTD)</div></div>
			<div><div class="sp-card-value">$${parseFloat(mo.reimb || 0).toFixed(2)}</div><div class="sp-card-label">Reimb (MTD)</div></div>
			<div><div class="sp-card-value">${parseFloat(yt.miles || 0).toFixed(0)}</div><div class="sp-card-label">Miles (YTD)</div></div>
			<div><div class="sp-card-value">$${parseFloat(yt.reimb || 0).toFixed(2)}</div><div class="sp-card-label">Reimb (YTD)</div></div>
		</div>`;
	}
	const dest = $('#sp-analytics-mileage-dest .sp-analytics-card-body');
	if (dest) {
		const top = (m && m.top_destinations) || [];
		if (!top.length) { dest.innerHTML = '<div class="sp-widget-empty">No trips logged yet.</div>'; return; }
		const max = Math.max(...top.map(t => parseInt(t.count)));
		let html = '<div class="sp-chart-bars">';
		top.forEach(t => {
			const pct = max > 0 ? Math.round((parseInt(t.count) / max) * 100) : 0;
			html += `<div class="sp-chart-bar-row"><span class="unique sp-chart-label">${esc(t.name)}</span><div class="sp-chart-bar-track"><div class="sp-chart-bar-fill" style="width:${pct}%;background:#15243a"></div></div><span class="unique sp-chart-value">${t.count}</span></div>`;
		});
		html += '</div>';
		dest.innerHTML = html;
		markUniqueSpans(dest);
	}
}

function renderPriorityChart(data) {
	const body = $('#sp-analytics-priority .sp-analytics-card-body');
	if (!body) return;

	if (!data || data.length === 0) {
		body.innerHTML = '<div class="sp-widget-empty">No open action items.</div>';
		return;
	}

	const colors = { high: '#dc2626', medium: '#f97316', low: '#eab308' };
	const labels = { high: 'High', medium: 'Medium', low: 'Low' };
	const total = data.reduce((sum, d) => sum + parseInt(d.count), 0);

	let html = '<div class="sp-chart-bars">';
	data.forEach(d => {
		const pct = total > 0 ? Math.round((parseInt(d.count) / total) * 100) : 0;
		html += `<div class="sp-chart-bar-row">`;
		html += `<span class="unique sp-chart-label">${labels[d.priority] || d.priority}</span>`;
		html += `<div class="sp-chart-bar-track"><div class="sp-chart-bar-fill" style="width:${pct}%;background:${colors[d.priority] || '#94a3b8'}"></div></div>`;
		html += `<span class="unique sp-chart-value">${d.count}</span>`;
		html += '</div>';
	});
	html += '</div>';

	body.innerHTML = html;
	markUniqueSpans(body);
}

function renderCategoryChart(data) {
	const body = $('#sp-analytics-category .sp-analytics-card-body');
	if (!body) return;

	if (!data || data.length === 0) {
		body.innerHTML = '<div class="sp-widget-empty">No data yet.</div>';
		return;
	}

	const max = Math.max(...data.map(d => parseInt(d.count)));

	let html = '<div class="sp-chart-bars">';
	data.forEach(d => {
		const pct = max > 0 ? Math.round((parseInt(d.count) / max) * 100) : 0;
		html += `<div class="sp-chart-bar-row">`;
		html += `<span class="unique sp-chart-label">${esc(d.category)}</span>`;
		html += `<div class="sp-chart-bar-track"><div class="sp-chart-bar-fill" style="width:${pct}%;background:var(--sp-accent-color)"></div></div>`;
		html += `<span class="unique sp-chart-value">${d.count}</span>`;
		html += '</div>';
	});
	html += '</div>';

	body.innerHTML = html;
	markUniqueSpans(body);
}

function renderLocationsChart(data) {
	const body = $('#sp-analytics-locations .sp-analytics-card-body');
	if (!body) return;

	if (!data || data.length === 0) {
		body.innerHTML = '<div class="sp-widget-empty">No reports yet.</div>';
		return;
	}

	const max = Math.max(...data.map(d => parseInt(d.count)));

	let html = '<div class="sp-chart-bars">';
	data.forEach(d => {
		const pct = max > 0 ? Math.round((parseInt(d.count) / max) * 100) : 0;
		html += `<div class="sp-chart-bar-row">`;
		html += `<span class="unique sp-chart-label">${esc(d.name || 'Unknown')}</span>`;
		html += `<div class="sp-chart-bar-track"><div class="sp-chart-bar-fill" style="width:${pct}%;background:var(--sp-accent-color)"></div></div>`;
		html += `<span class="unique sp-chart-value">${d.count}</span>`;
		html += '</div>';
	});
	html += '</div>';

	body.innerHTML = html;
	markUniqueSpans(body);
}

function renderResolutionCard(data) {
	const body = $('#sp-analytics-resolution .sp-analytics-card-body');
	if (!body) return;

	if (!data || data.total === 0) {
		body.innerHTML = '<div class="sp-widget-empty">No action items yet.</div>';
		return;
	}

	const pct = data.rate;
	const color = pct >= 75 ? 'var(--sp-success)' : pct >= 50 ? '#f97316' : 'var(--sp-danger)';

	let html = '<div class="sp-resolution-stats">';
	html += `<div class="sp-resolution-ring">`;
	html += `<svg viewBox="0 0 36 36" class="sp-ring-svg">`;
	html += `<path class="sp-ring-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>`;
	html += `<path class="sp-ring-fill" stroke="${color}" stroke-dasharray="${pct}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>`;
	html += `<text x="18" y="20.35" class="sp-ring-text">${pct}%</text>`;
	html += `</svg></div>`;
	html += '<div class="sp-resolution-details">';
	html += `<div><span class="unique sp-chart-label">Resolved</span> <strong>${data.resolved}</strong></div>`;
	html += `<div><span class="unique sp-chart-label">Open</span> <strong>${data.open}</strong></div>`;
	html += `<div><span class="unique sp-chart-label">Total</span> <strong>${data.total}</strong></div>`;
	if (data.avg_days !== null) {
		html += `<div><span class="unique sp-chart-label">Avg Resolution</span> <strong>${data.avg_days} days</strong></div>`;
	}
	html += '</div></div>';

	body.innerHTML = html;
	markUniqueSpans(body);
}


/*--------------------------------------------------------------
# Admin Panel
--------------------------------------------------------------*/

function initAdmin() {
	const usersContent = $('#sp-admin-users-content');
	const tiersContent = $('#sp-admin-tiers-content');
	const locsContent = $('#sp-admin-locations-content');
	const tplsContent = $('#sp-admin-templates-content');
	const settingsContent = $('#sp-admin-settings-content');
	const notifContent = $('#sp-admin-notifications-content');
	const apiKeysContent = $('#sp-admin-apikeys-content');
	const modulesContent = $('#sp-admin-modules-content');
	const formsCatContent = $('#sp-admin-forms-content');
	const aiPromptsContent = $('#sp-admin-ai-prompts-content');
	const ccSettingsContent = $('#sp-admin-customer-connect-content');

	if (usersContent) loadAdminUsers();
	if (tiersContent) loadAdminTiers();
	if (locsContent) loadAdminLocations();
	if (tplsContent) loadAdminTemplates();
	if (settingsContent) loadAdminSettings();
	if (notifContent) loadNotificationSettings();
	if (apiKeysContent) loadApiKeys();
	if (modulesContent) loadAdminModules();
	if (formsCatContent) loadFormSettings();
	if (aiPromptsContent) loadAiPrompts();
	if (ccSettingsContent) loadCcSettings();
	if ($('#sp-admin-ai-roles-content')) loadAiRoles();

	initActionItems();
}

/* ---- AI Assistant: company-voiced chat (role + brand voice → tailored output) ---- */
let _spAi = { messages: [], wired: false, sending: false };

function initAiAssistant() {
	const roleSel = $('#sp-ai-role');
	if (roleSel && !roleSel._filled) {
		roleSel._filled = true;
		spAjax('site_pulse_ai_assistant_roles', {}).then(res => {
			const roles = (res.success && res.data.roles) ? res.data.roles : [];
			roleSel.innerHTML = roles.map(r => `<option value="${esc(r.name)}">${esc(r.name)}</option>`).join('') + '<option value="__custom__">+ Custom…</option>';
		}).catch(() => {});
	}
	spAiRender();
	if (_spAi.wired) return;
	_spAi.wired = true;

	roleSel?.addEventListener('change', () => {
		const custom = $('#sp-ai-role-custom');
		const isCustom = roleSel.value === '__custom__';
		if (custom) { custom.style.display = isCustom ? '' : 'none'; if (isCustom) custom.focus(); }
	});
	$('#sp-ai-composer')?.addEventListener('submit', (e) => { e.preventDefault(); spAiSend(); });
	$('#sp-ai-input')?.addEventListener('keydown', (e) => { if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); spAiSend(); } });
	$('#sp-ai-new')?.addEventListener('click', () => { _spAi.messages = []; spAiRender(); });
}

function spAiRoleValue() {
	const sel = $('#sp-ai-role');
	if (!sel) return '';
	return (sel.value === '__custom__') ? ($('#sp-ai-role-custom')?.value || '').trim() : sel.value;
}

async function spAiSend() {
	if (_spAi.sending) return;
	const input = $('#sp-ai-input');
	const text = (input?.value || '').trim();
	if (!text) return;
	const role = spAiRoleValue();
	if (!role) { alert('Pick a role (or type a custom one) first.'); return; }
	_spAi.messages.push({ role: 'user', content: text });
	if (input) input.value = '';
	_spAi.sending = true;
	spAiRender(true);
	try {
		const res = await spAjax('site_pulse_ai_assistant_send', { role, messages: JSON.stringify(_spAi.messages) });
		_spAi.messages.push(res.success
			? { role: 'assistant', content: res.data.reply }
			: { role: 'assistant', content: '⚠️ ' + (res.data?.message || 'No response.'), error: true });
	} catch (e) {
		_spAi.messages.push({ role: 'assistant', content: '⚠️ Could not reach the AI — please try again.', error: true });
	}
	_spAi.sending = false;
	spAiRender();
}

function spAiRender(thinking) {
	const chat = $('#sp-ai-chat');
	if (!chat) return;
	if (!_spAi.messages.length && !thinking) {
		chat.innerHTML = '<div class="sp-ai-empty">Pick a role, then ask for what you need — each answer comes back in your company voice. (Ctrl/⌘+Enter to send.)</div>';
		return;
	}
	let h = _spAi.messages.map((m, i) => {
		const body = esc(m.content).replace(/\n/g, '<br>');
		const copy = (m.role === 'assistant' && !m.error) ? `<button type="button" class="unique sp-btn sp-btn-ghost sp-ai-copy" data-i="${i}">Copy</button>` : '';
		return `<div class="sp-ai-msg sp-ai-${m.role}${m.error ? ' sp-ai-error' : ''}">${body}${copy}</div>`;
	}).join('');
	if (thinking) h += '<div class="sp-ai-msg sp-ai-assistant sp-ai-thinking">…thinking</div>';
	chat.innerHTML = h;
	markUniqueSpans(chat);
	$$('.sp-ai-copy', chat).forEach(b => b.addEventListener('click', () => {
		const m = _spAi.messages[+b.dataset.i];
		if (m && navigator.clipboard) { navigator.clipboard.writeText(m.content); b.textContent = 'Copied'; setTimeout(() => { b.textContent = 'Copy'; }, 1500); }
	}));
	chat.scrollTop = chat.scrollHeight;
}

/* ---- AI Roles (Settings → AI Roles): admin-editable role list + assistant name for the AI Assistant ---- */
let _spAiRoles = [];
let _spAiName = 'AI Assistant';

async function loadAiRoles() {
	const wrap = $('#sp-admin-ai-roles-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_ai_roles', {});
		_spAiRoles = (res.success && Array.isArray(res.data.roles)) ? res.data.roles : [];
		_spAiName = (res.success && res.data.name) ? res.data.name : 'AI Assistant';
	} catch (e) { _spAiRoles = []; }
	if (!_spAiRoles.length) _spAiRoles = [{ name: '', persona: '' }];
	if (!wrap._wired) { wrap._wired = true; wireAiRoles(wrap); }
	renderAiRoles();
}

function aiRolesSyncFromDom() {
	const wrap = $('#sp-admin-ai-roles-content');
	if (!wrap) return;
	const nameEl = $('.sp-airole-aname', wrap);
	if (nameEl) _spAiName = nameEl.value;
	_spAiRoles = $$('.sp-airole', wrap).map(el => ({
		name: el.querySelector('.sp-airole-name')?.value || '',
		persona: el.querySelector('.sp-airole-persona')?.value || ''
	}));
}

function renderAiRoles() {
	const wrap = $('#sp-admin-ai-roles-content');
	if (!wrap) return;
	let h = '<div class="sp-card sp-ai-assistant-card">';
	h += '<div class="sp-card-body">';
	h += '<h3 style="margin:0 0 4px;">AI Assistant</h3>';
	h += '<p class="sp-help-text" style="margin:0 0 14px;">The company-voiced chat employees use. Name it whatever you like and define the roles they can pick.</p>';
	h += '<label class="sp-field-label">Assistant name (what employees see)</label>';
	h += `<input type="text" class="sp-input sp-airole-aname" value="${esc(_spAiName)}" placeholder="AI Assistant" maxlength="40">`;
	h += '<p class="sp-help-text" style="margin:14px 0 14px">Roles employees pick in the AI Assistant. Each persona steers the AI for that role; the company voice (Settings → AI Prompts) is always applied on top.</p>';
	h += '<div id="sp-airole-list">';
	_spAiRoles.forEach((r, i) => {
		h += `<div class="sp-tile sp-airole" data-i="${i}">`;
		h += `<div class="sp-airole-top"><input type="text" class="sp-input sp-airole-name" value="${esc(r.name || '')}" placeholder="Role name (e.g. Ad Copy Writer)"><button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-airole-del" data-i="${i}" title="Remove role" aria-label="Remove role">${ICON_DELETE}</button></div>`;
		h += `<textarea class="sp-input sp-airole-persona" rows="3" placeholder="Persona / guidance for this role (how it should write)…">${esc(r.persona || '')}</textarea>`;
		h += '</div>';
	});
	h += '</div>';
	h += '<button type="button" class="unique sp-btn sp-btn-secondary sp-airole-add">+ Add Role</button>';
	h += '<span class="sp-help-text sp-airole-status" style="margin-left:10px"></span>';
	h += '</div>';
	h += '</div>';
	wrap.innerHTML = h;
	markUniqueSpans(wrap);
}

let _spAiRolesTimer = null;
function scheduleAiRolesSave() { clearTimeout(_spAiRolesTimer); _spAiRolesTimer = setTimeout(() => saveAiRoles(), 700); }

function wireAiRoles(wrap) {
	wrap.addEventListener('click', (e) => {
		const b = e.target.closest('button');
		if (!b) return;
		if (b.classList.contains('sp-airole-add')) { aiRolesSyncFromDom(); _spAiRoles.push({ name: '', persona: '' }); renderAiRoles(); }
		else if (b.classList.contains('sp-airole-del')) { aiRolesSyncFromDom(); _spAiRoles.splice(+b.dataset.i, 1); if (!_spAiRoles.length) _spAiRoles = [{ name: '', persona: '' }]; renderAiRoles(); saveAiRoles(); }
	});
	// Auto-save as the name / roles are typed (debounced). No re-render → keeps focus while typing.
	wrap.addEventListener('input', (e) => {
		if (e.target.matches('.sp-airole-name, .sp-airole-persona, .sp-airole-aname')) scheduleAiRolesSave();
	});
}

// Auto-save (no Save button). Doesn't re-render — the server cleans blanks and the list reloads next open.
async function saveAiRoles() {
	clearTimeout(_spAiRolesTimer);
	aiRolesSyncFromDom();
	const clean = _spAiRoles.map(r => ({ name: (r.name || '').trim(), persona: (r.persona || '').trim() })).filter(r => r.name);
	const status = $('.sp-airole-status', $('#sp-admin-ai-roles-content'));
	if (status) status.textContent = 'Saving…';
	try {
		const res = await spAjax('site_pulse_save_ai_roles', { roles: JSON.stringify(clean), name: (_spAiName || '').trim() });
		if (status) status.textContent = res.success ? 'Saved · refresh to update the menu name' : (res.data?.message || 'Could not save.');
		const rs = $('#sp-ai-role'); if (rs) rs._filled = false; // assistant dropdown refreshes next open
	} catch (e) { if (status) status.textContent = 'Could not save.'; }
}

/* ---- AI Prompts (Settings → AI Prompts): company voice + keyword-targeted context for review replies ---- */

// State carries a per-row `editing` flag. Rows show a read-only view (with edit + trash icons) until
// edited; everything auto-saves (debounced) — no Save button.
let _spAiPrompts  = [];  // [{brand, keyword, details, editing}]
let _spAiVoices   = [];  // [{brand, voice, editing}]
let _spAiCritical = [];  // [{brand, details, editing}] — standing guidance injected into ≤3-star replies
let _spAiBrands   = [];  // brand/label suggestions for the pickers (review brands + agency client labels)
let _spAiSaveTimer = null;

// Pull the values of any rows currently in edit mode back into state (view rows keep their state). Index
// by data-i, which matches the array index at render time.
function spAiSync(wrap) {
	$$('.sp-ai-voice-row.is-editing', wrap).forEach(row => {
		const v = _spAiVoices[+row.dataset.i]; if (!v) return;
		v.brand = ($('.sp-ai-voice-brand', row)?.value || '').trim();
		v.voice = ($('.sp-ai-voice-text', row)?.value || '').trim();
	});
	$$('.sp-ai-prompt-row.is-editing', wrap).forEach(row => {
		const p = _spAiPrompts[+row.dataset.i]; if (!p) return;
		p.brand   = ($('.sp-ai-prompt-brand', row)?.value || '').trim();
		p.keyword = ($('.sp-ai-prompt-kw', row)?.value || '').trim();
		p.details = ($('.sp-ai-prompt-details', row)?.value || '').trim();
	});
	$$('.sp-ai-crit-row.is-editing', wrap).forEach(row => {
		const c = _spAiCritical[+row.dataset.i]; if (!c) return;
		c.brand   = ($('.sp-ai-crit-brand', row)?.value || '').trim();
		c.details = ($('.sp-ai-crit-text', row)?.value || '').trim();
	});
}

// Persist both sections from state. No re-render on success, so typing isn't interrupted (like the
// Notifications matrix). Empty rows are filtered out of what's saved.
async function saveAiPromptsNow(wrap) {
	spAiSync(wrap);
	const prompts  = _spAiPrompts.filter(p => p.keyword && p.details).map(p => ({ brand: p.brand || '', keyword: p.keyword, details: p.details }));
	const voices   = _spAiVoices.filter(v => v.voice).map(v => ({ brand: v.brand, voice: v.voice }));
	const critical = _spAiCritical.filter(c => c.details).map(c => ({ brand: c.brand || '', details: c.details }));
	const st = $('#sp-ai-prompts-status', wrap);
	try {
		const res = await spAjax('site_pulse_save_ai_prompts', { prompts: JSON.stringify(prompts), voices: JSON.stringify(voices), critical: JSON.stringify(critical) });
		if (st) st.textContent = res.success ? 'Saved.' : (res.data?.message || 'Could not save.');
	} catch (e) { if (st) st.textContent = 'Could not save.'; }
}

function scheduleAiSave(wrap) {
	const st = $('#sp-ai-prompts-status', wrap);
	if (st) st.textContent = 'Saving…';
	clearTimeout(_spAiSaveTimer);
	_spAiSaveTimer = setTimeout(() => saveAiPromptsNow(wrap), 600);
}

async function loadAiPrompts() {
	const wrap = $('#sp-admin-ai-prompts-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_ai_prompts', {});
		if (!res.success) { wrap.innerHTML = '<p>Could not load AI prompts.</p>'; return; }
		_spAiPrompts  = (res.data.prompts  || []).map(p => ({ brand: p.brand || '', keyword: p.keyword || '', details: p.details || '', editing: false }));
		_spAiVoices   = (res.data.voices   || []).map(v => ({ brand: v.brand || '', voice: v.voice || '', editing: false }));
		_spAiCritical = (res.data.critical || []).map(c => ({ brand: c.brand || '', details: c.details || '', editing: false }));
		_spAiBrands   = res.data.brands || [];
		renderAiPrompts(wrap);
	} catch (e) { wrap.innerHTML = '<p>Could not load AI prompts.</p>'; }
}

const ICON_AI_DONE = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';

function aiVoiceRowHtml(v, i) {
	const trash = `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-ai-voice-remove" title="Delete" aria-label="Delete">${ICON_DELETE}</button>`;
	if (v.editing) {
		return `<div class="sp-tile sp-settings-tile sp-ai-voice-row is-editing" data-i="${i}">`
			+ '<div class="sp-form-group"><label>Brand</label>'
			+ `<input type="text" class="sp-input sp-ai-voice-brand" list="sp-ai-brand-list" value="${esc(v.brand)}" placeholder="Pick or type a brand — leave blank to apply to all" autocomplete="off">`
			+ '<div class="sp-help-text">Start typing to search your brands/clients. Blank = the default voice for every brand.</div></div>'
			+ '<div class="sp-form-group"><label>Voice</label>'
			+ `<textarea class="sp-input sp-ai-voice-text" rows="3" placeholder="How replies should sound — tone, personality, what the brand is about…">${esc(v.voice)}</textarea></div>`
			+ `<div class="sp-ai-row-actions"><button type="button" class="unique sp-btn sp-icon-btn sp-icon-approve sp-ai-voice-done" title="Done" aria-label="Done">${ICON_AI_DONE}</button>${trash}</div>`
			+ '</div>';
	}
	return `<div class="sp-tile sp-settings-tile sp-ai-voice-row" data-i="${i}">`
		+ `<div class="sp-ai-row-title">${v.brand ? esc(v.brand) : 'All brands'}</div>`
		+ `<div class="sp-ai-row-text">${esc(v.voice)}</div>`
		+ `<div class="sp-ai-row-actions"><button type="button" class="unique sp-btn sp-icon-btn sp-icon-edit sp-ai-voice-edit" title="Edit" aria-label="Edit">${ICON_EDIT}</button>${trash}</div>`
		+ '</div>';
}

function aiPromptRowHtml(p, i) {
	const trash = `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-ai-prompt-remove" title="Delete" aria-label="Delete">${ICON_DELETE}</button>`;
	if (p.editing) {
		return `<div class="sp-tile sp-settings-tile sp-ai-prompt-row is-editing" data-i="${i}">`
			+ '<div class="sp-aai-row">'
			+ '<div class="sp-aai-col"><label class="sp-field-label">Keyword / keyphrase</label>'
			+ `<input type="text" class="sp-input sp-ai-prompt-kw" value="${esc(p.keyword)}" placeholder="e.g. green beans, green bean"></div>`
			+ '<div class="sp-aai-col"><label class="sp-field-label">Brand</label>'
			+ `<input type="text" class="sp-input sp-ai-prompt-brand" list="sp-ai-brand-list" value="${esc(p.brand)}" placeholder="Pick/type — blank = all" autocomplete="off"></div>`
			+ '</div>'
			+ '<div class="sp-help-text" style="margin-top:4px;">Keyword: comma-separate alternates — any match triggers this. Brand: blank applies to every brand/client.</div>'
			+ '<div class="sp-form-group"><label>Details</label>'
			+ `<textarea class="sp-input sp-ai-prompt-details" rows="3" placeholder="What's going on / how the reply should address it when this topic comes up…">${esc(p.details)}</textarea></div>`
			+ `<div class="sp-ai-row-actions"><button type="button" class="unique sp-btn sp-icon-btn sp-icon-approve sp-ai-prompt-done" title="Done" aria-label="Done">${ICON_AI_DONE}</button>${trash}</div>`
			+ '</div>';
	}
	const scope = p.brand ? ` <span class="unique sp-ai-row-scope">· ${esc(p.brand)}</span>` : '';
	return `<div class="sp-tile sp-settings-tile sp-ai-prompt-row" data-i="${i}">`
		+ `<div class="sp-ai-row-title">${esc(p.keyword)}${scope}</div>`
		+ `<div class="sp-ai-row-text">${esc(p.details)}</div>`
		+ `<div class="sp-ai-row-actions"><button type="button" class="unique sp-btn sp-icon-btn sp-icon-edit sp-ai-prompt-edit" title="Edit" aria-label="Edit">${ICON_EDIT}</button>${trash}</div>`
		+ '</div>';
}

function aiCriticalRowHtml(c, i) {
	const trash = `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-ai-crit-remove" title="Delete" aria-label="Delete">${ICON_DELETE}</button>`;
	if (c.editing) {
		return `<div class="sp-tile sp-settings-tile sp-ai-crit-row is-editing" data-i="${i}">`
			+ '<div class="sp-form-group"><label>Brand</label>'
			+ `<input type="text" class="sp-input sp-ai-crit-brand" list="sp-ai-brand-list" value="${esc(c.brand)}" placeholder="Pick or type a brand — leave blank to apply to all" autocomplete="off">`
			+ '<div class="sp-help-text">Blank = the guidance for every brand. Add a row per brand to differ.</div></div>'
			+ '<div class="sp-form-group"><label>Guidance for bad reviews (3★ or lower)</label>'
			+ `<textarea class="sp-input sp-ai-crit-text" rows="3" placeholder="How you want negative reviews handled — own the problem, apologize sincerely, offer to make it right, who/how to contact you offline…">${esc(c.details)}</textarea></div>`
			+ `<div class="sp-ai-row-actions"><button type="button" class="unique sp-btn sp-icon-btn sp-icon-approve sp-ai-crit-done" title="Done" aria-label="Done">${ICON_AI_DONE}</button>${trash}</div>`
			+ '</div>';
	}
	return `<div class="sp-tile sp-settings-tile sp-ai-crit-row" data-i="${i}">`
		+ `<div class="sp-ai-row-title">${c.brand ? esc(c.brand) : 'All brands'}</div>`
		+ `<div class="sp-ai-row-text">${esc(c.details)}</div>`
		+ `<div class="sp-ai-row-actions"><button type="button" class="unique sp-btn sp-icon-btn sp-icon-edit sp-ai-crit-edit" title="Edit" aria-label="Edit">${ICON_EDIT}</button>${trash}</div>`
		+ '</div>';
}

function renderAiPrompts(wrap) {
	let html = '';
	// Shared brand/label suggestions for every Brand picker (searchable + free-text via native datalist).
	html += '<datalist id="sp-ai-brand-list">' + _spAiBrands.map(b => `<option value="${esc(b)}"></option>`).join('') + '</datalist>';
	// --- Company Voice (own card) ---
	html += '<div class="sp-card" style="margin-bottom:18px">';
	html += '<div class="sp-card-body">';
	html += '<h3 style="margin:0 0 4px;">Company Voice</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">The tone &amp; personality the AI writes review replies in. Leave <strong>Brand</strong> blank for one voice across the company, or add a row per brand (matched to the review\'s brand) — e.g. Babe\'s vs Bubba\'s.</p>';
	html += '<div id="sp-ai-voices-list">' + (_spAiVoices.length ? _spAiVoices.map((v, i) => aiVoiceRowHtml(v, i)).join('') : '<p class="sp-help-text">No voice set yet — replies use a neutral default tone.</p>') + '</div>';
	html += '<div style="margin-top:12px;"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-ai-voice-add">+ Add Voice</button></div>';
	html += '</div>';
	html += '</div>';

	// --- Review Reply Prompts (own card) ---
	html += '<div class="sp-card" style="margin-bottom:18px">';
	html += '<div class="sp-card-body">';
	html += '<h3 style="margin:0 0 4px;">Review Reply Prompts</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">Extra context to weave into a reply — but <strong>only when the review mentions a topic</strong>. Example: keyword <em>green beans</em> + details explaining a recent recipe change; mentioned → factored in, otherwise ignored.</p>';
	html += '<div id="sp-ai-prompts-list">' + (_spAiPrompts.length ? _spAiPrompts.map((p, i) => aiPromptRowHtml(p, i)).join('') : '<p class="sp-help-text">No prompts yet. Add one below.</p>') + '</div>';
	html += '<div style="margin-top:12px;"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-ai-prompt-add">+ Add Prompt</button></div>';
	html += '<div class="sp-report-form-actions"><span class="sp-help-text" id="sp-ai-prompts-status">Changes save automatically.</span></div>';
	html += '</div>';
	html += '</div>';

	// --- Critical Review Prompt (own card) ---
	html += '<div class="sp-card" style="margin-bottom:18px">';
	html += '<div class="sp-card-body">';
	html += '<h3 style="margin:0 0 4px;">Critical Review Prompt</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">Standing guidance woven into <strong>every reply to a 3★-or-lower review</strong> — no keyword needed. Tell the AI how you want bad reviews handled (own the issue, apologize, offer to make it right, point them to a contact…). Leave <strong>Brand</strong> blank to apply to all, or add a row per brand — e.g. Babe\'s vs Bubba\'s.</p>';
	html += '<div id="sp-ai-crit-list">' + (_spAiCritical.length ? _spAiCritical.map((c, i) => aiCriticalRowHtml(c, i)).join('') : '<p class="sp-help-text">No critical-review guidance yet — low-rated reviews use the default tone.</p>') + '</div>';
	html += '<div style="margin-top:12px;"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-ai-crit-add">+ Add Critical Prompt</button></div>';
	html += '</div>';
	html += '</div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	// Auto-save while editing — listeners ride the (re-created-each-render) list containers, so they
	// don't accumulate across re-renders.
	$('#sp-ai-voices-list', wrap)?.addEventListener('input', () => scheduleAiSave(wrap));
	$('#sp-ai-prompts-list', wrap)?.addEventListener('input', () => scheduleAiSave(wrap));
	$('#sp-ai-crit-list', wrap)?.addEventListener('input', () => scheduleAiSave(wrap));

	// --- Company Voice handlers ---
	$('#sp-ai-voice-add', wrap)?.addEventListener('click', () => {
		spAiSync(wrap); _spAiVoices.push({ brand: '', voice: '', editing: true }); renderAiPrompts(wrap);
		$(`.sp-ai-voice-row[data-i="${_spAiVoices.length - 1}"] .sp-ai-voice-text`, wrap)?.focus();
	});
	$$('.sp-ai-voice-edit', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-voice-row').dataset.i;
		if (_spAiVoices[i]) _spAiVoices[i].editing = true;
		renderAiPrompts(wrap); $(`.sp-ai-voice-row[data-i="${i}"] .sp-ai-voice-text`, wrap)?.focus();
	}));
	$$('.sp-ai-voice-done', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-voice-row').dataset.i;
		if (_spAiVoices[i]) { if (!_spAiVoices[i].voice) _spAiVoices.splice(i, 1); else _spAiVoices[i].editing = false; }
		renderAiPrompts(wrap); saveAiPromptsNow(wrap);
	}));
	$$('.sp-ai-voice-remove', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-voice-row').dataset.i;
		_spAiVoices.splice(i, 1); renderAiPrompts(wrap); saveAiPromptsNow(wrap);
	}));

	// --- Keyword Prompt handlers ---
	$('#sp-ai-prompt-add', wrap)?.addEventListener('click', () => {
		spAiSync(wrap); _spAiPrompts.push({ keyword: '', details: '', editing: true }); renderAiPrompts(wrap);
		$(`.sp-ai-prompt-row[data-i="${_spAiPrompts.length - 1}"] .sp-ai-prompt-kw`, wrap)?.focus();
	});
	$$('.sp-ai-prompt-edit', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-prompt-row').dataset.i;
		if (_spAiPrompts[i]) _spAiPrompts[i].editing = true;
		renderAiPrompts(wrap); $(`.sp-ai-prompt-row[data-i="${i}"] .sp-ai-prompt-kw`, wrap)?.focus();
	}));
	$$('.sp-ai-prompt-done', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-prompt-row').dataset.i;
		if (_spAiPrompts[i]) { if (!(_spAiPrompts[i].keyword && _spAiPrompts[i].details)) _spAiPrompts.splice(i, 1); else _spAiPrompts[i].editing = false; }
		renderAiPrompts(wrap); saveAiPromptsNow(wrap);
	}));
	$$('.sp-ai-prompt-remove', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-prompt-row').dataset.i;
		_spAiPrompts.splice(i, 1); renderAiPrompts(wrap); saveAiPromptsNow(wrap);
	}));

	// --- Critical Review Prompt handlers ---
	$('#sp-ai-crit-add', wrap)?.addEventListener('click', () => {
		spAiSync(wrap); _spAiCritical.push({ brand: '', details: '', editing: true }); renderAiPrompts(wrap);
		$(`.sp-ai-crit-row[data-i="${_spAiCritical.length - 1}"] .sp-ai-crit-text`, wrap)?.focus();
	});
	$$('.sp-ai-crit-edit', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-crit-row').dataset.i;
		if (_spAiCritical[i]) _spAiCritical[i].editing = true;
		renderAiPrompts(wrap); $(`.sp-ai-crit-row[data-i="${i}"] .sp-ai-crit-text`, wrap)?.focus();
	}));
	$$('.sp-ai-crit-done', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-crit-row').dataset.i;
		if (_spAiCritical[i]) { if (!_spAiCritical[i].details) _spAiCritical.splice(i, 1); else _spAiCritical[i].editing = false; }
		renderAiPrompts(wrap); saveAiPromptsNow(wrap);
	}));
	$$('.sp-ai-crit-remove', wrap).forEach(b => b.addEventListener('click', () => {
		spAiSync(wrap); const i = +b.closest('.sp-ai-crit-row').dataset.i;
		_spAiCritical.splice(i, 1); renderAiPrompts(wrap); saveAiPromptsNow(wrap);
	}));
}

/* ---- Notifications routing matrix (Settings → Notifications) ---- */

async function loadNotificationSettings() {
	const wrap = $('#sp-admin-notifications-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	let data;
	try {
		const res = await spAjax('site_pulse_get_notification_settings', {});
		if (!res.success) { wrap.innerHTML = '<p>Could not load notification settings.</p>'; return; }
		data = res.data;
	} catch (e) { wrap.innerHTML = '<p>Could not load notification settings.</p>'; return; }

	const events = data.events || {};
	const columns = data.columns || {};
	const routing = data.routing || {};
	const emailOn = String(data.email_enabled) === '1';
	const pushOn  = String(data.push_enabled) === '1';
	// Channel columns are hidden when their app-level switch is off (their per-event values are kept).
	const colKeys = Object.keys(columns).filter(ck => (ck !== 'email' || emailOn) && (ck !== 'push' || pushOn));

	// A row with no columns set arrives as an empty array []. Reading row['push'] on an array returns
	// Array.prototype.push (a truthy function!) — which falsely checked the box. Test for an OWN property.
	const isOn = (row, col) => !!(row && Object.prototype.hasOwnProperty.call(row, col) && row[col]);

	// Global notifications card — app-wide switches.
	let html = '<div class="sp-card sp-settings-card">';
	html += '<h3 style="margin:0 0 4px;">Global notifications</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">App-wide switches. Turning one off hides its column below and stops every notification of that kind (including new-message emails). Each person still opts in to push per device from the bell.</p>';
	html += `<label class="sp-reminder-toggle" style="display:block;margin-bottom:8px;"><input type="checkbox" id="sp-gn-email"${emailOn ? ' checked' : ''}> Allow email notifications for this app</label>`;
	html += `<label class="sp-reminder-toggle" style="display:block;"><input type="checkbox" id="sp-gn-push"${pushOn ? ' checked' : ''}> Allow push notifications for this app</label>`;
	html += '<span class="sp-help-text" id="sp-gn-status" style="display:block;margin-top:8px;"></span>';
	if (pushOn) html += '<div style="margin-top:12px;"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-push-test-btn">Send test notification to my devices</button> <span class="sp-help-text" id="sp-push-test-status" style="margin-left:12px;"></span></div>';
	html += '</div>';

	// Individual notifications matrix.
	html += '<div class="sp-card sp-settings-card">';
	html += '<h3 style="margin:0 0 4px;">Individual notifications</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 16px;">For each situation pick the delivery channels and who is notified. <strong>Dashboard</strong> shows a banner, <strong>Email</strong> emails it, <strong>Push</strong> wakes devices, <strong>Bell</strong> is the in-app notification. <strong>User</strong> = the person the event is about; <strong>User\'s Supervisor</strong> = their direct supervisor; the rest notify everyone in that role.</p>';

	// Divider between delivery channels and recipients — class lands on the first recipient column.
	const channelCols = ['dashboard', 'email', 'push', 'bell'];
	const firstPeopleCol = colKeys.find(ck => !channelCols.includes(ck));
	const divCls = ck => (ck === firstPeopleCol ? ' sp-col-divider' : '');

	html += '<div class="sp-card sp-table-card sp-admin-table-wrap"><table class="sp-table sp-notif-table">';
	html += '<thead class="sp-thead"><tr><th>Situation</th>';
	colKeys.forEach(ck => { html += `<th class="sp-notif-col${divCls(ck)}">${esc(columns[ck])}</th>`; });
	html += '</tr></thead><tbody>';

	Object.keys(events).forEach(ev => {
		const row = routing[ev] || {};
		html += `<tr data-event="${esc(ev)}"><td>${esc(events[ev])}</td>`;
		colKeys.forEach(ck => {
			html += `<td class="sp-notif-cell${divCls(ck)}"><input type="checkbox" autocomplete="off" class="sp-notif-cb" data-event="${esc(ev)}" data-col="${esc(ck)}"${isOn(row, ck) ? ' checked' : ''}></td>`;
		});
		html += '</tr>';
	});
	html += '</tbody></table></div>';
	html += '<div style="margin-top:12px;"><span class="sp-help-text" id="sp-notif-status">Changes save automatically.</span></div>';
	html += '</div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	// Force every box to match the loaded routing (browsers cache checkbox state across reloads).
	$$('.sp-notif-cb', wrap).forEach(cb => { cb.checked = isOn(routing[cb.dataset.event], cb.dataset.col); });

	// Auto-save the matrix. Preserve values for any channel column not currently shown (email/push hidden
	// by their global switch) so toggling a switch off never wipes those per-event settings.
	let notifSaveTimer = null;
	const saveNotifMatrix = () => {
		const out = {};
		Object.keys(events).forEach(ev => {
			out[ev] = {};
			const saved = routing[ev] || {};
			Object.keys(saved).forEach(c => { if (!colKeys.includes(c) && saved[c]) out[ev][c] = 1; });
		});
		$$('.sp-notif-cb', wrap).forEach(cb => { if (cb.checked) out[cb.dataset.event][cb.dataset.col] = 1; });
		const st = $('#sp-notif-status', wrap);
		if (st) st.textContent = 'Saving…';
		clearTimeout(notifSaveTimer);
		notifSaveTimer = setTimeout(async () => {
			try {
				const res = await spAjax('site_pulse_save_notification_settings', { routing: JSON.stringify(out) });
				if (st) st.textContent = res.success ? 'Saved.' : (res.data?.message || 'Could not save.');
			} catch (e) { if (st) st.textContent = 'Could not save.'; }
		}, 350);
	};
	$$('.sp-notif-cb', wrap).forEach(cb => cb.addEventListener('change', saveNotifMatrix));

	// Global switches — save, then reload so the Email/Push columns show or hide.
	const saveGlobal = async (key, on) => {
		const st = $('#sp-gn-status', wrap);
		if (st) st.textContent = 'Saving…';
		try {
			await spAjax('site_pulse_admin_save_setting', { key, value: on ? '1' : '0' });
			if (key === 'push_enabled' && typeof spPushInit === 'function') spPushInit();
			loadNotificationSettings();
		} catch (e) { if (st) st.textContent = 'Could not save.'; }
	};
	$('#sp-gn-email', wrap)?.addEventListener('change', (e) => saveGlobal('notifications_email_enabled', e.target.checked));
	$('#sp-gn-push', wrap)?.addEventListener('change', (e) => saveGlobal('push_enabled', e.target.checked));

	// Diagnostic: push a test to the admin's own devices.
	const pushTest = $('#sp-push-test-btn', wrap);
	pushTest?.addEventListener('click', async () => {
		const st = $('#sp-push-test-status', wrap);
		if (st) st.textContent = 'Sending…';
		pushTest.disabled = true;
		try {
			const res = await spAjax('site_pulse_push_test', {});
			if (st) st.textContent = (res?.data?.message) || (res?.success ? 'Sent.' : 'Could not send.');
		} catch (e) { if (st) st.textContent = 'Could not send.'; }
		pushTest.disabled = false;
	});
}


/* ---- Users ---- */

async function loadAdminUsers() {
	const wrap = $('#sp-admin-users-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_users', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading users.</p>'; return; }
		renderAdminUsers(wrap, res.data);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading users.</p>';
	}
}

let _spUsers = { users: [], roles: [], locations: [], mileageLocations: [], sort: { col: 'display_name', dir: 'asc' }, search: '' };

function renderAdminUsers(wrap, data) {
	const { users, roles, locations } = data;
	const mileageLocations = data.mileage_locations || [];
	spPermCatalog = data.catalog || spPermCatalog;
	// Keep the sort across reloads (e.g. after a delete); reset the search box.
	_spUsers = { users: users || [], roles, locations, mileageLocations, sort: _spUsers.sort || { col: 'display_name', dir: 'asc' }, search: '' };

	let html = '<div class="sp-admin-toolbar">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-user-btn">+ Add User</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-link-user-btn">Link Existing WP User</button>';
	// GOD only: one-click cleanup of orphaned profiles (rows whose WP login is already gone —
	// they show with blank name/username/email).
	if (D.isGod) {
		html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-god-purge-orphans">Purge Orphaned Profiles</button>';
	}
	html += '</div>';

	if (!users || users.length === 0) {
		html += '<div class="sp-empty-state"><p>No users yet. Create your first user above.</p></div>';
		html += '<div id="sp-user-form-wrap" hidden></div>';
		wrap.innerHTML = html;
		markUniqueSpans(wrap);
		spWireUsersToolbar(wrap);
		return;
	}

	html += '<div class="sp-users-toolbar"><input type="search" class="sp-input sp-users-search" placeholder="Search names…" aria-label="Search users"></div>';
	html += '<div class="sp-card sp-table-card sp-admin-table-wrap"><table class="sp-table sp-admin-table sp-users-table">';
	html += '<thead class="sp-thead"><tr>'
		+ '<th class="sp-users-sort" data-sort="display_name">Name</th>'
		+ '<th class="sp-users-sort" data-sort="user_login">Username</th>'
		+ '<th class="sp-users-sort" data-sort="user_email">Email</th>'
		+ '<th class="sp-users-sort" data-sort="role_label">Role</th>'
		+ '<th class="sp-users-sort" data-sort="location_name">Location</th>'
		+ '<th class="sp-users-sort" data-sort="status">Status</th>'
		+ '<th class="sp-users-app" title="Has installed the app to a home screen / desktop">App</th>'
		+ '<th class="sp-users-sort sp-users-active-col" data-sort="last_active">Last Active</th>'
		+ '<th></th></tr></thead>';
	html += '<tbody class="sp-users-tbody"></tbody>';
	html += '</table></div>';
	html += '<div id="sp-user-form-wrap" hidden></div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	spWireUsersToolbar(wrap);

	const search = $('.sp-users-search', wrap);
	if (search) search.addEventListener('input', () => { _spUsers.search = search.value.trim().toLowerCase(); renderUsersRows(wrap); });
	$$('.sp-users-sort', wrap).forEach(th => {
		th.addEventListener('click', () => {
			const col = th.getAttribute('data-sort');
			if (_spUsers.sort.col === col) _spUsers.sort.dir = _spUsers.sort.dir === 'asc' ? 'desc' : 'asc';
			else { _spUsers.sort.col = col; _spUsers.sort.dir = 'asc'; }
			renderUsersRows(wrap);
		});
	});

	renderUsersRows(wrap);
}

// Wire the Add User + Purge Orphans buttons (present in both the empty and populated states).
function spWireUsersToolbar(wrap) {
	$('#sp-add-user-btn')?.addEventListener('click', () => showUserForm(null, _spUsers.roles, _spUsers.locations, _spUsers.users, _spUsers.mileageLocations));
	$('#sp-link-user-btn')?.addEventListener('click', () => showLinkUserForm(_spUsers.roles, _spUsers.locations));
	$('#sp-god-purge-orphans')?.addEventListener('click', async (e) => {
		const btn = e.currentTarget;
		if (!confirm('Purge ALL orphaned profiles (users whose login no longer exists) and their leftover data? This cannot be undone.')) return;
		btn.disabled = true;
		try {
			const res = await spAjax('site_pulse_god_purge_orphans', {});
			if (res.success) { alert(res.data.message); loadAdminUsers(); }
			else { alert(res.data?.message || 'Error.'); btn.disabled = false; }
		} catch (err) { alert('Error purging orphaned profiles.'); btn.disabled = false; }
	});
}

// Filter + sort the users into the tbody, then (re)wire the per-row edit/delete buttons.
function renderUsersRows(wrap) {
	const tbody = $('.sp-users-tbody', wrap);
	if (!tbody) return;
	const st = _spUsers;

	let rows = st.users.slice();
	if (st.search) {
		rows = rows.filter(u =>
			(u.display_name || '').toLowerCase().includes(st.search) ||
			(u.user_login || '').toLowerCase().includes(st.search) ||
			(u.user_email || '').toLowerCase().includes(st.search));
	}
	const { col, dir } = st.sort;
	rows.sort((a, b) => {
		if (col === 'last_active') { // numeric timestamp, not string
			const an = parseInt(a.last_active || 0, 10), bn = parseInt(b.last_active || 0, 10);
			return dir === 'asc' ? an - bn : bn - an;
		}
		const av = (a[col] || '').toString().toLowerCase();
		const bv = (b[col] || '').toString().toLowerCase();
		if (av < bv) return dir === 'asc' ? -1 : 1;
		if (av > bv) return dir === 'asc' ? 1 : -1;
		return 0;
	});

	$$('.sp-users-sort', wrap).forEach(th => {
		const isCol = th.getAttribute('data-sort') === col;
		th.classList.toggle('sp-sort-asc', isCol && dir === 'asc');
		th.classList.toggle('sp-sort-desc', isCol && dir === 'desc');
	});

	if (!rows.length) {
		tbody.innerHTML = '<tr><td colspan="9" class="sp-users-empty">No users match.</td></tr>';
		return;
	}

	let html = '';
	rows.forEach(u => {
		const statusClass = u.status === 'active' ? 'sp-status-submitted' : 'sp-status-draft';
		html += `<tr data-user-id="${u.user_id}">`;
		html += `<td>${esc(u.display_name)}</td>`;
		html += `<td>${esc(u.user_login)}</td>`;
		html += `<td>${esc(u.user_email)}</td>`;
		html += `<td>${esc(u.role_label || '—')}</td>`;
		html += `<td>${esc(u.location_name || '—')}</td>`;
		html += `<td><span class="unique sp-status-badge ${statusClass}">${u.status}</span></td>`;
		html += `<td class="sp-users-app">${u.app_installed ? '<span class="sp-app-yes" title="App installed">&#10003;</span>' : '<span class="sp-app-no">&mdash;</span>'}</td>`;
		html += `<td class="sp-users-active-col">${spLastActive(u.last_active)}</td>`;
		let userActions = iconBtn('edit', 'sp-edit-user-btn', `data-user-id="${u.user_id}"`);
		// Hard-delete a user (test/mistake cleanup). Regular Gods can delete normal users; only
		// the super-admin can delete a God. Never your own row or battleplanweb. Server enforces
		// the same, plus a WP-admin block.
		const isSelfRow = String(u.user_id) === String(D.userId);
		const isBpRow = u.user_login === 'battleplanweb';
		const canDelete = D.isGod && !isSelfRow && !isBpRow && (u.role_slug !== 'god' || D.isSuperadmin);
		if (canDelete) {
			userActions += ' ' + iconBtn('delete', 'sp-god-delete-user', `data-user-id="${u.user_id}" data-user-name="${esc(u.display_name)}" title="Delete user"`);
		}
		html += `<td><div class="sp-row-actions">${userActions}</div></td>`;
		html += '</tr>';
	});
	tbody.innerHTML = html;
	markUniqueSpans(tbody);

	$$('.sp-edit-user-btn', tbody).forEach(btn => {
		btn.addEventListener('click', () => {
			const uid = btn.dataset.userId;
			const user = st.users.find(u => String(u.user_id) === uid);
			showUserForm(user, st.roles, st.locations, st.users, st.mileageLocations);
		});
	});

	$$('.sp-god-delete-user', tbody).forEach(btn => {
		btn.addEventListener('click', async () => {
			const name = btn.dataset.userName || 'this user';
			if (!confirm(`Permanently delete ${name} and ALL their reports, action items, and mileage, plus their login? Real users should be set Inactive instead. This cannot be undone.`)) return;
			btn.disabled = true;
			try {
				const res = await spAjax('site_pulse_god_delete_user', { user_id: btn.dataset.userId });
				if (res.success) loadAdminUsers();
				else { alert(res.data?.message || 'Error.'); btn.disabled = false; }
			} catch (err) { alert('Error deleting user.'); btn.disabled = false; }
		});
	});
}

// --- Per-user permission overrides (layered on top of the assigned role's capabilities) ---
let spPermCatalog = {}; // cap => friendly label, from the admin get-users payload

function spRoleCaps(roles, roleId) {
	const r = (roles || []).find(x => String(x.id) === String(roleId));
	if (!r) return [];
	try { return JSON.parse(r.capabilities || '[]') || []; } catch (e) { return []; }
}

function spParseOverrides(raw) {
	if (!raw) return {};
	try { const o = JSON.parse(raw); return (o && typeof o === 'object') ? o : {}; } catch (e) { return {}; }
}

// Two-column grid: left = role default (read-only), right = this user (clickable). applyStored
// seeds the right column from the user's saved overrides; on a Role change we pass false so the
// right column resets to the newly-selected role's defaults.
function spPermGridHtml(roles, roleId, user, applyStored) {
	const roleCaps = spRoleCaps(roles, roleId);
	const overrides = applyStored ? spParseOverrides(user && user.capability_overrides) : {};
	let h = '<div class="sp-perm-grid">';
	h += '<div class="sp-perm-row sp-perm-head"><span class="sp-perm-headcols">Default / Override</span><button type="button" class="unique sp-perm-reset">Reset</button></div>';
	let sawManagePerm = false;
	Object.keys(spPermCatalog).forEach(cap => {
		const inRole = roleCaps.includes(cap);
		let eff = inRole;
		if (Object.prototype.hasOwnProperty.call(overrides, cap)) eff = !!overrides[cap];
		const changed = eff !== inRole;
		const divider = (!sawManagePerm && /^Manage/i.test(spPermCatalog[cap])) ? ' sp-cap-admin-start' : '';
		if (divider) sawManagePerm = true;
		h += `<label class="sp-perm-row${changed ? ' sp-perm-changed' : ''}${divider}">`;
		h += `<span class="sp-perm-cell"><input type="checkbox" tabindex="-1" disabled${inRole ? ' checked' : ''}></span>`;
		h += `<span class="sp-perm-cell"><input type="checkbox" class="sp-perm-user-cb" data-cap="${cap}" data-default="${inRole ? '1' : '0'}"${eff ? ' checked' : ''}></span>`;
		h += `<span class="sp-perm-label">${esc(spPermCatalog[cap])}</span>`;
		h += '</label>';
	});
	h += '</div>';
	return h;
}

// Link an EXISTING WordPress account as a Site Pulse user instead of creating a new one. Fetches WP
// users who don't yet have a Site Pulse profile, then attaches the chosen role/location. Their WP
// login and existing role are kept (see site_pulse_link_existing_user on the server).
async function showLinkUserForm(roles, locations) {
	const wrap = spFormModal();
	wrap.innerHTML = '<div class="sp-card sp-report-form-wrap" style="margin-top:20px;"><div class="sp-loading"></div></div>';

	let linkable = [];
	try {
		const res = await spAjax('site_pulse_admin_get_linkable_users', {});
		if (!res.success) { wrap.innerHTML = `<div class="sp-card sp-report-form-wrap" style="margin-top:20px;"><div class="sp-empty">${esc(res.data?.message || 'Could not load users.')}</div></div>`; return; }
		linkable = res.data.users || [];
	} catch (e) {
		wrap.innerHTML = '<div class="sp-card sp-report-form-wrap" style="margin-top:20px;"><div class="sp-empty">Error loading WordPress users.</div></div>';
		return;
	}

	let html = '<div class="sp-card sp-report-form-wrap" style="margin-top:20px;">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>Link Existing WP User</h3>';
	html += '<div class="sp-form-header-actions"><button type="button" class="unique sp-btn sp-btn-ghost sp-user-form-cancel">Cancel</button></div></div>';

	if (!linkable.length) {
		html += '<div class="sp-empty-state"><p>Every WordPress user is already a Site Pulse user.</p></div></div>';
		wrap.innerHTML = html;
		markUniqueSpans(wrap);
		$$('.sp-user-form-cancel', wrap).forEach(b => b.addEventListener('click', () => closeFormModal()));
		return;
	}

	const userOpts = linkable.map(u => `<option value="${u.id}">${esc(u.label)}</option>`).join('');
	const roleOpts = roles.map(r => `<option value="${r.id}">${esc(r.label)}</option>`).join('');
	const locOpts = '<option value="0">None</option>' + locations.map(l => `<option value="${l.id}">${esc(l.name)}</option>`).join('');

	html += '<form id="sp-link-user-form">';
	html += '<p class="sp-text-secondary" style="margin:0 0 4px;">Adopt a WordPress account that already exists (e.g. a client who already logs in). Their WordPress login and existing role are kept — this only adds a Site Pulse role.</p>';
	html += `<div class="sp-form-group"><label>WordPress user</label><select name="wp_user_id" class="sp-select" required>${userOpts}</select></div>`;
	html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
	html += `<div class="sp-form-group"><label>Role</label><select name="role_id" class="sp-select" required>${roleOpts}</select></div>`;
	html += `<div class="sp-form-group"><label>Home Base</label><select name="location_id" class="sp-select">${locOpts}</select></div>`;
	html += '</div>';
	html += '<div class="sp-report-form-actions">';
	html += '<button type="submit" class="unique sp-btn sp-btn-primary">Link User</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-user-form-cancel">Cancel</button>';
	html += '</div></form></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-user-form-cancel', wrap).forEach(b => b.addEventListener('click', () => closeFormModal()));
	$('#sp-link-user-form', wrap)?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const form = e.currentTarget;
		const btn = form.querySelector('button[type="submit"]');
		if (btn) btn.disabled = true;
		try {
			const res = await spAjax('site_pulse_admin_link_user', {
				wp_user_id:  form.wp_user_id.value,
				role_id:     form.role_id.value,
				location_id: form.location_id.value,
			});
			if (res.success) { closeFormModal(); loadAdminUsers(); }
			else { alert(res.data?.message || 'Could not link user.'); if (btn) btn.disabled = false; }
		} catch (err) { alert('Error linking user.'); if (btn) btn.disabled = false; }
	});
}

function showUserForm(user, roles, locations, allUsers, mileageLocations = []) {
	const wrap = spFormModal();

	const isEdit = !!user;
	const title = isEdit ? 'Edit User' : 'Add New User';

	const roleOptions = roles.map(r =>
		`<option value="${r.id}"${user && String(user.role_id) === String(r.id) ? ' selected' : ''}>${esc(r.label)}</option>`
	).join('');

	const locOptions = '<option value="0">None</option>' + locations.map(l =>
		`<option value="${l.id}"${user && String(user.location_id) === String(l.id) ? ' selected' : ''}>${esc(l.name)}</option>`
	).join('');

	const supervisorOptions = '<option value="0">None</option>' + allUsers.filter(u => {
		const rSlug = u.role_slug || '';
		return rSlug === 'owner' || rSlug === 'admin' || rSlug === 'supervisor';
	}).map(u =>
		`<option value="${u.user_id}"${user && String(user.supervisor_id) === String(u.user_id) ? ' selected' : ''}>${esc(u.display_name)}</option>`
	).join('');

	let html = `<div class="sp-card sp-report-form-wrap" style="margin-top:20px;">`;
	html += `<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${title}</h3>`;
	// A Save next to the top Cancel lets you save without scrolling to the bottom actions. It's
	// outside <form>, so it submits the form programmatically (see wiring below).
	html += '<div class="sp-form-header-actions">';
	html += `<button type="button" class="unique sp-btn sp-btn-primary sp-user-form-save-top">${isEdit ? 'Save Changes' : 'Create User'}</button>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-user-form-cancel">Cancel</button>';
	html += '</div></div>';
	html += '<form id="sp-user-form">';

	if (isEdit) {
		html += `<input type="hidden" name="user_id" value="${user.user_id}">`;
	}

	if (!isEdit) {
		html += '<div class="sp-form-group"><label>Username</label>';
		html += '<input type="text" name="username" class="sp-input" required placeholder="Login username"></div>';
	}

	html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>First Name</label>';
	html += `<input type="text" name="first_name" class="sp-input" value="${esc(user?.first_name || isEdit && user ? getUserMeta(user, 'first_name') : '')}" required></div>`;
	html += '<div class="sp-form-group"><label>Last Name</label>';
	html += `<input type="text" name="last_name" class="sp-input" value="${esc(user?.last_name || isEdit && user ? getUserMeta(user, 'last_name') : '')}" required></div>`;
	html += '</div>';

	if (!isEdit) {
		html += '<div class="sp-form-group"><label>Email</label>';
		html += '<input type="email" name="email" class="sp-input" required placeholder="email@example.com"></div>';
		html += '<div class="sp-form-group"><label>Password</label>';
		// name="user_pass", not "password" — WP Engine's WAF strips a `password` POST field.
		html += '<input type="text" name="user_pass" class="sp-input" placeholder="Leave blank to auto-generate"></div>';
	}

	// A God's role isn't in the dropdown (by design), so show it as a locked field; God membership
	// is changed only via the super-admin's Grant/Revoke buttons.
	const isGodUser = isEdit && user && user.role_slug === 'god';
	html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
	if (isGodUser) {
		html += '<div class="sp-form-group"><label>Role</label><input type="text" class="sp-input" value="Odinson" disabled></div>';
	} else {
		html += `<div class="sp-form-group"><label>Role</label><select name="role_id" class="sp-select" required>${roleOptions}</select></div>`;
	}
	html += `<div class="sp-form-group"><label>Home Base</label><select name="location_id" class="sp-select">${locOptions}</select></div>`;
	html += '</div>';

	html += `<div class="sp-form-group"><label>Supervisor</label><select name="supervisor_id" class="sp-select">${supervisorOptions}</select></div>`;

	// Work-from-home only: shown when there's no store Location. Becomes the mileage home base.
	const privHome = (user && parseInt(user.home_is_private)) ? (user.home_address || '') : '';
	const hasLocation = user && parseInt(user.location_id) > 0;
	html += `<div class="sp-form-group" id="sp-user-privhome-group"${hasLocation ? ' style="display:none;"' : ''}><label>Home address <span class="unique sp-text-secondary" style="font-weight:400;">(work-from-home — used as the mileage start/end, hidden from other drivers)</span></label>`;
	html += `<input type="text" name="home_private_address" class="sp-input" placeholder="e.g. 123 Maple St, Frisco, TX 75034" value="${esc(privHome)}"></div>`;

	if (isEdit) {
		html += '<div class="sp-form-group"><label>New Password</label>';
		html += '<input type="text" name="new_user_pass" class="sp-input" placeholder="Leave blank to keep current"></div>';

		html += `<div class="sp-form-group"><label>Status</label><select name="status" class="sp-select">`;
		html += `<option value="active"${user.status === 'active' ? ' selected' : ''}>Active</option>`;
		html += `<option value="inactive"${user.status === 'inactive' ? ' selected' : ''}>Inactive</option>`;
		html += '</select></div>';
	}

	// Per-user permission overrides. Left column is the role default (read-only); right column is
	// this user's effective setting. Ticking the right box to differ from the role creates an
	// override (highlighted); matching it again clears the override. God users bypass caps entirely.
	if (isEdit && !isGodUser) {
		html += '<div class="sp-form-group" id="sp-user-perms-section">';
		html += '<label>Permission Overrides</label>';
		html += `<div id="sp-user-perms">${spPermGridHtml(roles, user.role_id, user, true)}</div>`;
		html += '</div>';
	}

	html += '<div class="sp-report-form-actions">';
	html += `<button type="submit" class="unique sp-btn sp-btn-primary">${isEdit ? 'Save Changes' : 'Create User'}</button>`;
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-user-form-cancel">Cancel</button>';
	// Super-admin (battleplanweb) only: manage God membership. The role dropdown has no God option
	// by design, so this is the sole path. Other Gods never see these.
	if (isEdit && D.isSuperadmin && user && String(user.user_id) !== String(D.userId)) {
		if (user.role_slug === 'god') {
			html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-user-revoke-god" style="margin-left:auto;">Revoke Odin Access</button>';
		} else {
			html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-user-grant-god" style="margin-left:auto;">Grant Odin Access</button>';
		}
	}
	html += '</div></form></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-user-form-cancel', wrap).forEach(btn => {
		btn.addEventListener('click', () => closeFormModal());
	});

	// Top Save mirrors the bottom Save — submit the form (requestSubmit runs HTML5 validation + the
	// existing submit handler). Lets the user save without scrolling down.
	$('.sp-user-form-save-top', wrap)?.addEventListener('click', () => {
		const form = $('#sp-user-form', wrap);
		if (!form) return;
		if (form.requestSubmit) form.requestSubmit();
		else form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
	});

	$('.sp-user-grant-god', wrap)?.addEventListener('click', async () => {
		if (!confirm(`Give ${user.display_name} full Odin access? An Odinson can delete users, wipe data, and impersonate anyone.`)) return;
		try {
			const res = await spAjax('site_pulse_god_grant', { user_id: user.user_id });
			if (res.success) { alert(res.data.message); closeFormModal(); loadAdminUsers(); }
			else alert(res.data?.message || 'Error granting access.');
		} catch (err) { alert('Error granting access.'); }
	});

	$('.sp-user-revoke-god', wrap)?.addEventListener('click', async () => {
		if (!confirm(`Remove Odin access from ${user.display_name}? Their account stays, but they're demoted to the lowest role — edit them afterward to set the right one.`)) return;
		try {
			const res = await spAjax('site_pulse_god_revoke', { user_id: user.user_id });
			if (res.success) { alert(res.data.message); closeFormModal(); loadAdminUsers(); }
			else alert(res.data?.message || 'Error revoking access.');
		} catch (err) { alert('Error revoking access.'); }
	});

	// Show the private home-address field only when there's no store Location.
	const locSel = wrap.querySelector('select[name="location_id"]');
	const privGroup = wrap.querySelector('#sp-user-privhome-group');
	locSel?.addEventListener('change', () => {
		if (privGroup) privGroup.style.display = (parseInt(locSel.value) > 0) ? 'none' : '';
	});

	// Permission grid: highlight a row the instant it diverges from the role default, and rebuild
	// the grid (resetting to defaults) when the Role dropdown changes.
	const permsBox = wrap.querySelector('#sp-user-perms');
	const bindPermRows = () => {
		$$('.sp-perm-user-cb', wrap).forEach(cb => cb.addEventListener('change', () => {
			const row = cb.closest('.sp-perm-row');
			if (row) row.classList.toggle('sp-perm-changed', cb.checked !== (cb.dataset.default === '1'));
		}));
		// Reset: snap every User box back to its role default and clear all override highlights.
		// (Persists when "Save Changes" is clicked, like the rest of the form.)
		$('.sp-perm-reset', wrap)?.addEventListener('click', () => {
			$$('.sp-perm-user-cb', wrap).forEach(cb => {
				cb.checked = cb.dataset.default === '1';
				cb.closest('.sp-perm-row')?.classList.remove('sp-perm-changed');
			});
		});
	};
	bindPermRows();
	const roleSelEl = wrap.querySelector('select[name="role_id"]');
	// Changing the role re-baselines the override grid on that role's caps.
	roleSelEl?.addEventListener('change', () => {
		if (permsBox) { permsBox.innerHTML = spPermGridHtml(roles, roleSelEl.value, user, false); bindPermRows(); }
	});

	$('#sp-user-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;

		// Per-user permission overrides: send only the caps whose right-column box differs from the
		// role default. An empty map ({}) tells the server to clear all overrides for this user.
		const permCbs = $$('.sp-perm-user-cb', wrap);
		if (permCbs.length) {
			const overrides = {};
			permCbs.forEach(cb => { if (cb.checked !== (cb.dataset.default === '1')) overrides[cb.dataset.cap] = cb.checked; });
			data.capability_overrides = JSON.stringify(overrides);
		}

		const action = isEdit ? 'site_pulse_admin_update_user' : 'site_pulse_admin_create_user';
		try {
			const res = await spAjax(action, data);
			if (res.success) {
				if (!isEdit && res.data.password) {
					alert('User created. Password: ' + res.data.password);
				}
				closeFormModal();
				loadAdminUsers();
			} else {
				alert(res.data?.message || 'Error saving user.');
			}
		} catch (err) {
			alert('Error saving user.');
		}
	});
}

function getUserMeta(user, key) {
	if (key === 'first_name') {
		const parts = (user.display_name || '').split(' ');
		return parts[0] || '';
	}
	if (key === 'last_name') {
		const parts = (user.display_name || '').split(' ');
		return parts.slice(1).join(' ') || '';
	}
	return '';
}


/* ---- Locations ---- */

async function loadAdminLocations() {
	const wrap = $('#sp-admin-locations-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_locations', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading locations.</p>'; return; }
		renderAdminLocations(wrap, res.data.locations);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading locations.</p>';
	}
}

function renderAdminLocations(wrap, locations) {
	let html = '<div class="sp-admin-toolbar">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-location-btn">+ Add Location</button>';
	html += '</div>';

	if (!locations || locations.length === 0) {
		html += '<div class="sp-empty-state"><p>No locations yet.</p></div>';
	} else {
		html += '<div class="sp-card sp-table-card sp-admin-table-wrap"><table class="sp-table sp-admin-table">';
		html += '<thead class="sp-thead"><tr><th>Name</th><th>Location #</th><th>Brand</th><th>Reports</th><th>City</th><th>State</th><th>Status</th><th></th></tr></thead>';
		html += '<tbody>';
		locations.forEach(l => {
			const statusClass = l.status === 'active' ? 'sp-status-submitted' : 'sp-status-draft';
			html += `<tr data-loc-id="${l.id}">`;
			html += `<td>${esc(l.name)}</td>`;
			html += `<td>${esc(l.location_number || '')}</td>`;
			html += `<td>${esc(l.location_type)}</td>`;
			html += `<td>${(l.is_store ?? 1) != 0 ? '&#10003;' : '&mdash;'}</td>`;
			html += `<td>${esc(l.city || '')}</td>`;
			html += `<td>${esc(l.state || '')}</td>`;
			html += `<td><span class="unique sp-status-badge ${statusClass}">${l.status}</span></td>`;
			let locActions = iconBtn('edit', 'sp-edit-loc-btn', `data-loc-id="${l.id}"`);
			// GOD only: permanently delete a Home Base (the regular Status field only deactivates it).
			if (D.isGod) {
				locActions += ' ' + iconBtn('delete', 'sp-god-delete-loc', `data-loc-id="${l.id}" data-loc-name="${esc(l.name)}" title="Delete location"`);
			}
			html += `<td>${locActions}</td>`;
			html += '</tr>';
		});
		html += '</tbody></table></div>';
	}

	html += '<div id="sp-location-form-wrap" hidden></div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-add-location-btn')?.addEventListener('click', () => showLocationForm(null));
	$$('.sp-edit-loc-btn', wrap).forEach(btn => {
		btn.addEventListener('click', () => {
			const lid = btn.dataset.locId;
			const loc = locations.find(l => String(l.id) === lid);
			showLocationForm(loc);
		});
	});

	$$('.sp-god-delete-loc', wrap).forEach(btn => {
		btn.addEventListener('click', async () => {
			const name = btn.dataset.locName || 'this location';
			if (!confirm(`Permanently delete ${name}? Any driver based here will have their Home Base cleared. To just retire it, set Status to Inactive instead. This cannot be undone.`)) return;
			btn.disabled = true;
			try {
				const res = await spAjax('site_pulse_god_delete_location', { id: btn.dataset.locId });
				if (res.success) loadAdminLocations();
				else { alert(res.data?.message || 'Error.'); btn.disabled = false; }
			} catch (err) { alert('Error deleting location.'); btn.disabled = false; }
		});
	});
}

function showLocationForm(loc) {
	const wrap = spFormModal();

	const isEdit = !!loc;
	const title = isEdit ? 'Edit Location' : 'Add Location';

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += `<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${title}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-loc-form-cancel">Cancel</button></div>';
	html += '<form id="sp-location-form">';

	if (isEdit) html += `<input type="hidden" name="id" value="${loc.id}">`;

	html += '<div class="sp-form-group"><label>Location Name</label>';
	html += `<input type="text" name="name" class="sp-input" value="${esc(loc?.name || '')}" required></div>`;

	// Reports flag (stored as is_store) — only checked locations appear in the GM/Supervisor Reports
	// location filter. Hidden 0 + checkbox 1 so clearing it still posts a value (an unchecked checkbox
	// submits nothing on its own).
	const isStore = (loc?.is_store ?? 1) != 0;
	html += '<div class="sp-form-group"><label class="sp-reminder-toggle">';
	html += '<input type="hidden" name="is_store" value="0">';
	html += `<input type="checkbox" name="is_store" value="1"${isStore ? ' checked' : ''}> Show in Reports`;
	html += '</label>';
	html += '<div class="sp-help-text">Only checked locations appear in the GM &amp; Supervisor Reports location filter. Uncheck for non-reporting locations (accounting office, storage, vendors).</div></div>';

	html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>Brand</label>';
	html += `<input type="text" name="location_type" class="sp-input" value="${esc(loc?.location_type || '')}" placeholder="e.g. Babe's Chicken"></div>`;
	html += '<div class="sp-form-group"><label>Location #</label>';
	html += `<input type="text" name="location_number" class="sp-input" value="${esc(loc?.location_number || '')}" placeholder="e.g. 1042"></div>`;
	html += '<div class="sp-form-group"><label>Phone</label>';
	html += `<input type="text" name="phone" class="sp-input" value="${esc(loc?.phone || '')}"></div>`;
	html += '</div>';

	html += '<div class="sp-form-group"><label>Address</label>';
	html += `<input type="text" name="address" class="sp-input" value="${esc(loc?.address || '')}"></div>`;

	html += '<div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>City</label>';
	html += `<input type="text" name="city" class="sp-input" value="${esc(loc?.city || '')}"></div>`;
	html += '<div class="sp-form-group"><label>State</label>';
	html += `<input type="text" name="state" class="sp-input" value="${esc(loc?.state || 'TX')}"></div>`;
	html += '<div class="sp-form-group"><label>Zip</label>';
	html += `<input type="text" name="zip" class="sp-input" value="${esc(loc?.zip || '')}"></div>`;
	html += '</div>';

	if (isEdit) {
		html += '<div class="sp-form-group"><label>Status</label><select name="status" class="sp-select">';
		html += `<option value="active"${loc.status === 'active' ? ' selected' : ''}>Active</option>`;
		html += `<option value="inactive"${loc.status === 'inactive' ? ' selected' : ''}>Inactive</option>`;
		html += '</select></div>';
	}

	html += '<div class="sp-report-form-actions">';
	html += `<button type="submit" class="unique sp-btn sp-btn-primary">${isEdit ? 'Save Changes' : 'Add Location'}</button>`;
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-loc-form-cancel">Cancel</button>';
	html += '</div></form></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-loc-form-cancel', wrap).forEach(btn => {
		btn.addEventListener('click', () => closeFormModal());
	});

	$('#sp-location-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;

		try {
			const res = await spAjax('site_pulse_admin_save_location', data);
			if (res.success) {
				closeFormModal();
				loadAdminLocations();
			} else {
				alert(res.data?.message || 'Error saving location.');
			}
		} catch (err) {
			alert('Error saving location.');
		}
	});
}


/* ---- Action Items ---- */

let _spActionLabels = []; // labels seen across the user's items, for the Add-item suggestions
let _spActionItemsById = {}; // id → item, so the edit button can look up the row's current text/label

function initActionItems() {
	const list = $('#sp-action-items-list');
	if (!list) return;

	// Restore the user's last-used sort (remembered server-side) before the first load, so the list
	// comes up in that order. Only changes after this when they pick a new option.
	const sortSel = $('#sp-action-sort');
	if (sortSel && D.actionSort && [...sortSel.options].some(o => o.value === D.actionSort)) sortSel.value = D.actionSort;

	populateActionItemFilters();
	loadActionItems();

	$$('#sp-action-filter-status, #sp-action-filter-location, #sp-action-filter-category, #sp-action-sort').forEach(el => {
		el?.addEventListener('change', () => loadActionItems());
	});

	// Persist the sort choice so it loads this way next time.
	sortSel?.addEventListener('change', () => { spAjax('site_pulse_set_action_sort', { sort: sortSel.value }).catch(() => {}); });

	const addBtn = $('#sp-add-action-item-btn');
	if (addBtn && !addBtn._wired) {
		addBtn._wired = true;
		addBtn.addEventListener('click', () => spAddActionItem());
	}
}

// Shared "Label" control for the Add/Edit modals: a real dropdown of common + existing labels, with a
// "+ Custom label…" option that reveals a free-text box. Returns the selected (or typed) label.
function spLabelOptions(current) {
	const seed = ['To-Do', 'Follow-up', 'Fix', 'Feature', 'Meeting', 'Call', 'Email', 'Errand'];
	const seen = {}, list = [];
	_spActionLabels.concat(seed).forEach(l => { const k = String(l).toLowerCase(); if (l && !seen[k]) { seen[k] = 1; list.push(l); } });
	if (current && !seen[String(current).toLowerCase()]) list.unshift(current); // keep an existing custom label selectable
	return list;
}
function spLabelFieldHtml(current) {
	const cur = current || 'To-Do';
	let opts = spLabelOptions(current).map(l => `<option value="${esc(l)}"${l === cur ? ' selected' : ''}>${esc(l)}</option>`).join('');
	opts += '<option value="__custom__">+ Custom label…</option>';
	return `<select class="sp-select sp-cat-sel">${opts}</select>`
		+ '<input class="sp-input sp-cat-custom" maxlength="50" placeholder="New label" autocomplete="off" style="display:none;margin-top:8px;">';
}
function spWireLabelField(scope) {
	const sel = $('.sp-cat-sel', scope), custom = $('.sp-cat-custom', scope);
	if (!sel || !custom) return;
	const sync = () => {
		const isCustom = sel.value === '__custom__';
		custom.style.display = isCustom ? '' : 'none';
		if (isCustom) custom.focus();
	};
	sel.addEventListener('change', sync);
	sync();
}
function spLabelFieldValue(scope) {
	const sel = $('.sp-cat-sel', scope), custom = $('.sp-cat-custom', scope);
	if (!sel) return '';
	return (sel.value === '__custom__') ? (custom?.value || '').trim() : sel.value;
}

// "+ Add Action Item" — create your own to-do (description, urgency, due date), optionally assigning
// it to additional people via a picker that mirrors the New Message dialog (each gets their own copy,
// linked so completing one closes all).
// Photo section markup for the Add/Edit Action Item modals — an empty thumbnail strip + an "Add Photo"
// button. spWireActionPhotoSection() fills/wires it.
function spActionPhotoSectionHtml() {
	// A real <button> (not a <label>) so it gets the normal .sp-btn styling — a <label> here would also
	// match `.sp-report-form-wrap label` and inherit block/uppercase/left-label rules. The hidden file
	// input is a sibling, triggered by the button's click handler.
	return '<div class="sp-form-group sp-aai-photo-group"><label class="sp-field-label">Photos</label>'
		+ '<div class="sp-aai-photos-strip"></div>'
		+ '<button type="button" class="unique sp-btn sp-btn-ghost sp-aai-photos-add">+ Add Photo</button>'
		+ '<input type="file" accept="image/*" multiple class="sp-aai-photos-input" hidden></div>';
}

// Upload one image to an action item (multipart, like chat attachments).
async function spUploadActionPhoto(itemId, file) {
	const fd = new FormData();
	fd.append('action', 'site_pulse_action_item_upload');
	fd.append('nonce', spGetNonce());
	fd.append('item_id', itemId);
	fd.append('file', file);
	const r = await fetch(D.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: fd });
	return r.json();
}

// Wire the photo section: render EXISTING attachments (remove hits the server live), stage NEWLY-picked
// files as local previews, and expose uploadPending(id) to push the staged files after the item is saved.
function spWireActionPhotoSection(backdrop, existing, itemId) {
	const strip = $('.sp-aai-photos-strip', backdrop);
	const input = $('.sp-aai-photos-input', backdrop);
	const pending = [];
	if (!strip) return { uploadPending: async () => {} };

	$('.sp-aai-photos-add', backdrop)?.addEventListener('click', () => input && input.click());

	const makeCell = (src, isImg, onRemove) => {
		const cell = document.createElement('div');
		cell.className = 'sp-action-attach';
		cell.innerHTML = `<span class="sp-action-attach-thumb${isImg ? '' : ' sp-action-attach-file'}">${isImg ? `<img src="${src}" alt="">` : ICON_ATTACH}</span>`
			+ '<button type="button" class="sp-action-attach-remove" title="Remove photo" aria-label="Remove photo">&times;</button>';
		cell.querySelector('.sp-action-attach-remove').addEventListener('click', onRemove);
		strip.appendChild(cell);
		return cell;
	};

	// Already-saved photos (Edit) — × removes on the server right away.
	(existing || []).forEach(a => {
		const isImg = (a.mime || '').indexOf('image/') === 0;
		const cell = makeCell(a.url, isImg, async () => {
			if (itemId) {
				if (!confirm('Remove this photo?')) return;
				try {
					const res = await spAjax('site_pulse_action_item_remove_attachment', { item_id: itemId, url: a.url });
					if (!res.success) { alert(res.data?.message || 'Could not remove the photo.'); return; }
				} catch (e) { alert('Could not remove the photo.'); return; }
			}
			cell.remove();
		});
	});

	// Newly-picked files — staged locally (object-URL preview), uploaded on Save. × just unstages.
	input?.addEventListener('change', () => {
		Array.from(input.files || []).forEach(file => {
			if (!/^image\//.test(file.type)) return;
			const url = URL.createObjectURL(file);
			const entry = { file };
			entry.cell = makeCell(url, true, () => {
				URL.revokeObjectURL(url);
				const i = pending.indexOf(entry);
				if (i !== -1) pending.splice(i, 1);
				entry.cell.remove();
			});
			pending.push(entry);
		});
		input.value = '';
	});

	return {
		uploadPending: async (id) => {
			for (const entry of pending) {
				try { await spUploadActionPhoto(id, entry.file); } catch (e) {}
			}
		}
	};
}

async function spAddActionItem() {
	const backdrop = spFormModal();
	backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><div class="sp-loading"></div></div>';

	// Same contacts the New Message picker uses; if it fails or there's no one, we just omit the picker.
	let contacts = [];
	try { const cr = await spAjax('site_pulse_messages_contacts', {}); if (cr && cr.success) contacts = cr.data.contacts || []; } catch (e) {}
	const hasPeople = contacts.length > 0;

	// Urgency drives the due date: high → 3 days out, medium → 7, low → 14. The field is still
	// editable, so this is a smart default the user can override.
	const dueDaysForPriority = { high: 3, medium: 7, low: 14 };
	const due = spDatePlusDays(dueDaysForPriority.medium);
	let html = '<div class="sp-card sp-report-form-wrap">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>Add Action Item</h3><button type="button" class="unique sp-btn sp-btn-ghost sp-aai-cancel">Cancel</button></div>';
	html += '<label class="sp-field-label">To-do</label>';
	html += '<textarea class="sp-input sp-aai-desc" rows="2" placeholder="What needs to get done?"></textarea>';

	// Label (dropdown of common + existing labels), optional "Also assign to" people picker, urgency
	// and due date — laid out 2-up in a single grid holder.
	const labelField = '<div><label class="sp-field-label">Label</label>' + spLabelFieldHtml('To-Do') + '</div>';
	const urgencyField = '<div><label class="sp-field-label">Urgency</label><select class="sp-select sp-aai-pri"><option value="high">High</option><option value="medium" selected>Medium</option><option value="low">Low</option></select></div>';
	const dueField = `<div><label class="sp-field-label">Due date</label><input type="date" class="sp-input sp-aai-due" value="${due}"></div>`;
	if (hasPeople) {
		const picks = contacts.map(c => `<label class="sp-msg-contact-pick"><input type="checkbox" class="sp-msg-pick sp-aai-people-pick" value="${c.id}" data-name="${esc(c.name)}"><span class="sp-msg-contact-name">${esc(c.name)}</span></label>`).join('');
		const peopleField = '<div><label class="sp-field-label">Also assign to</label>'
			+ '<div class="sp-aai-people" id="sp-aai-people">'
			+ '<button type="button" class="sp-select sp-aai-people-toggle" id="sp-aai-people-toggle">Just me</button>'
			+ '<div class="sp-aai-people-panel" id="sp-aai-people-panel" hidden>'
			+ '<input type="text" class="sp-input sp-aai-people-search" placeholder="Search people…">'
			+ '<div class="sp-msg-contacts sp-aai-people-list">' + picks + '</div>'
			+ '</div></div></div>';
		html += '<div class="sp-aai-grid">' + labelField + peopleField + urgencyField + dueField + '</div>';
	} else {
		// No people to assign → Label spans full width above the urgency/due pair.
		html += labelField;
		html += '<div class="sp-aai-grid">' + urgencyField + dueField + '</div>';
	}
	html += spActionPhotoSectionHtml();
	html += '<div class="sp-report-form-actions"><button type="button" class="unique sp-btn sp-btn-primary sp-aai-save">Add</button><button type="button" class="unique sp-btn sp-btn-ghost sp-aai-cancel">Cancel</button></div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);
	spWireLabelField(backdrop);
	const photos = spWireActionPhotoSection(backdrop, [], 0);

	// People picker (mirrors New Message): toggle the panel, filter by search, show a live count.
	const ppToggle = $('#sp-aai-people-toggle', backdrop);
	const ppPanel  = $('#sp-aai-people-panel', backdrop);
	const ppSearch = $('.sp-aai-people-search', backdrop);
	const syncPeople = () => {
		const n = $$('.sp-aai-people-pick:checked', backdrop).length;
		if (ppToggle) ppToggle.textContent = n ? `Me + ${n} ${n === 1 ? 'person' : 'people'}` : 'Just me';
	};
	if (ppToggle && ppPanel) {
		ppToggle.addEventListener('click', (e) => { e.stopPropagation(); ppPanel.hidden = !ppPanel.hidden; if (!ppPanel.hidden) ppSearch?.focus(); });
		backdrop.addEventListener('click', (e) => { if (!e.target.closest('#sp-aai-people')) ppPanel.hidden = true; }); // click-away closes
	}
	$$('.sp-aai-people-pick', backdrop).forEach(cb => cb.addEventListener('change', syncPeople));
	ppSearch?.addEventListener('input', () => {
		const q = ppSearch.value.toLowerCase();
		$$('.sp-aai-people-list .sp-msg-contact-pick', backdrop).forEach(l => {
			const cb = l.querySelector('.sp-aai-people-pick');
			l.style.display = cb && cb.dataset.name.toLowerCase().includes(q) ? '' : 'none';
		});
	});

	// Changing Urgency re-fills the Due date to match (user can still edit it afterward).
	const priSel = $('.sp-aai-pri', backdrop);
	const dueInp = $('.sp-aai-due', backdrop);
	priSel?.addEventListener('change', () => { if (dueInp) dueInp.value = spDatePlusDays(dueDaysForPriority[priSel.value] ?? 7); });

	$('.sp-aai-desc', backdrop)?.focus();
	$$('.sp-aai-cancel', backdrop).forEach(b => b.addEventListener('click', () => closeFormModal()));
	$('.sp-aai-save', backdrop)?.addEventListener('click', async () => {
		const desc = ($('.sp-aai-desc', backdrop)?.value || '').trim();
		if (!desc) { alert('Please enter the to-do.'); return; }
		const priority = $('.sp-aai-pri', backdrop)?.value || 'medium';
		const due_date = $('.sp-aai-due', backdrop)?.value || '';
		const category = spLabelFieldValue(backdrop);
		const assignees = $$('.sp-aai-people-pick:checked', backdrop).map(cb => parseInt(cb.value, 10)).filter(Boolean);
		const save = $('.sp-aai-save', backdrop);
		if (save) save.disabled = true;
		try {
			const res = await spAjax('site_pulse_create_action_item', { description: desc, priority, due_date, category, assignees: JSON.stringify(assignees) });
			if (res.success) { await photos.uploadPending(res.data?.id); closeFormModal(); loadActionItems(); }
			else { alert(res.data?.message || 'Could not add the item.'); if (save) save.disabled = false; }
		} catch (e) { alert('Could not add the item.'); if (save) save.disabled = false; }
	});
}

// Edit an existing item — full parity with Add: text, label, "Also assign to" people, urgency, due
// date, plus Delete. Owner-only (the pencil only shows on your own open items).
async function spEditActionItem(item) {
	const backdrop = spFormModal();
	backdrop.innerHTML = '<div class="sp-card sp-report-form-wrap"><div class="sp-loading"></div></div>';

	// Same contacts the Add / New Message pickers use; assigning someone adds a linked copy for them.
	let contacts = [];
	try { const cr = await spAjax('site_pulse_messages_contacts', {}); if (cr && cr.success) contacts = cr.data.contacts || []; } catch (e) {}
	const hasPeople = contacts.length > 0;

	// Urgency drives the due date (same mapping as Add): high → 3 days out, medium → 7, low → 14. Both
	// fields stay editable — pre-filled from the item's current values.
	const dueDaysForPriority = { high: 3, medium: 7, low: 14 };
	const prio = (['high', 'medium', 'low'].indexOf(item.priority) !== -1) ? item.priority : 'medium';
	const dueVal = (item.due_date && /^\d{4}-\d{2}-\d{2}$/.test(item.due_date)) ? item.due_date : spDatePlusDays(dueDaysForPriority[prio]);
	const opt = p => `<option value="${p}"${p === prio ? ' selected' : ''}>${p.charAt(0).toUpperCase() + p.slice(1)}</option>`;

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>Edit Action Item</h3><button type="button" class="unique sp-btn sp-btn-ghost sp-aae-cancel">Cancel</button></div>';
	html += '<label class="sp-field-label">To-do</label>';
	html += `<textarea class="sp-input sp-aae-desc" rows="2" placeholder="What needs to get done?">${esc(item.description || '')}</textarea>`;

	// Label, optional "Also assign to" people picker, urgency and due date — laid out in one grid,
	// mirroring Add (reuses the same sp-aai-people* markup so it inherits the picker styling).
	const labelField = '<div><label class="sp-field-label">Label</label>' + spLabelFieldHtml(item.category || 'To-Do') + '</div>';
	const urgencyField = '<div><label class="sp-field-label">Urgency</label><select class="sp-select sp-aae-pri">' + opt('high') + opt('medium') + opt('low') + '</select></div>';
	const dueField = `<div><label class="sp-field-label">Due date</label><input type="date" class="sp-input sp-aae-due" value="${dueVal}"></div>`;
	if (hasPeople) {
		const picks = contacts.map(c => `<label class="sp-msg-contact-pick"><input type="checkbox" class="sp-msg-pick sp-aai-people-pick" value="${c.id}" data-name="${esc(c.name)}"><span class="sp-msg-contact-name">${esc(c.name)}</span></label>`).join('');
		const peopleField = '<div><label class="sp-field-label">Also assign to</label>'
			+ '<div class="sp-aai-people" id="sp-aai-people">'
			+ '<button type="button" class="sp-select sp-aai-people-toggle" id="sp-aai-people-toggle">Just me</button>'
			+ '<div class="sp-aai-people-panel" id="sp-aai-people-panel" hidden>'
			+ '<input type="text" class="sp-input sp-aai-people-search" placeholder="Search people…">'
			+ '<div class="sp-msg-contacts sp-aai-people-list">' + picks + '</div>'
			+ '</div></div></div>';
		html += '<div class="sp-aai-grid">' + labelField + peopleField + urgencyField + dueField + '</div>';
	} else {
		html += labelField;
		html += '<div class="sp-aai-grid">' + urgencyField + dueField + '</div>';
	}
	html += spActionPhotoSectionHtml();
	html += '<div class="sp-report-form-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary sp-aae-save">Save</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-aae-cancel">Cancel</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-btn-delete sp-aae-delete">Delete</button>';
	html += '</div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);
	spWireLabelField(backdrop);

	// Existing photos from the item's meta → shown with a live remove; new picks upload on Save.
	let existingAtts = [];
	try { const m = item.meta ? (typeof item.meta === 'string' ? JSON.parse(item.meta) : item.meta) : null; if (m && Array.isArray(m.attachments)) existingAtts = m.attachments; } catch (e) {}
	const photos = spWireActionPhotoSection(backdrop, existingAtts, item.id);

	// People picker (mirrors Add / New Message): toggle the panel, filter by search, show a live count.
	const ppToggle = $('#sp-aai-people-toggle', backdrop);
	const ppPanel  = $('#sp-aai-people-panel', backdrop);
	const ppSearch = $('.sp-aai-people-search', backdrop);
	const syncPeople = () => {
		const n = $$('.sp-aai-people-pick:checked', backdrop).length;
		if (ppToggle) ppToggle.textContent = n ? `Me + ${n} ${n === 1 ? 'person' : 'people'}` : 'Just me';
	};
	if (ppToggle && ppPanel) {
		ppToggle.addEventListener('click', (e) => { e.stopPropagation(); ppPanel.hidden = !ppPanel.hidden; if (!ppPanel.hidden) ppSearch?.focus(); });
		backdrop.addEventListener('click', (e) => { if (!e.target.closest('#sp-aai-people')) ppPanel.hidden = true; }); // click-away closes
	}
	$$('.sp-aai-people-pick', backdrop).forEach(cb => cb.addEventListener('change', syncPeople));
	ppSearch?.addEventListener('input', () => {
		const q = ppSearch.value.toLowerCase();
		$$('.sp-aai-people-list .sp-msg-contact-pick', backdrop).forEach(l => {
			const cb = l.querySelector('.sp-aai-people-pick');
			l.style.display = cb && cb.dataset.name.toLowerCase().includes(q) ? '' : 'none';
		});
	});

	// Changing Urgency re-fills the Due date to match (still editable afterward).
	const priSel = $('.sp-aae-pri', backdrop);
	const dueInp = $('.sp-aae-due', backdrop);
	priSel?.addEventListener('change', () => { if (dueInp) dueInp.value = spDatePlusDays(dueDaysForPriority[priSel.value] ?? 7); });

	$('.sp-aae-desc', backdrop)?.focus();
	$$('.sp-aae-cancel', backdrop).forEach(b => b.addEventListener('click', () => closeFormModal()));
	$('.sp-aae-save', backdrop)?.addEventListener('click', async () => {
		const desc = ($('.sp-aae-desc', backdrop)?.value || '').trim();
		if (!desc) { alert('Please enter the to-do.'); return; }
		const category = spLabelFieldValue(backdrop);
		const priority = priSel?.value || 'medium';
		const due_date = dueInp?.value || '';
		const assignees = $$('.sp-aai-people-pick:checked', backdrop).map(cb => parseInt(cb.value, 10)).filter(Boolean);
		const save = $('.sp-aae-save', backdrop);
		if (save) save.disabled = true;
		try {
			const res = await spAjax('site_pulse_update_action_item', { item_id: item.id, description: desc, category, priority, due_date, assignees: JSON.stringify(assignees) });
			if (res.success) { await photos.uploadPending(item.id); closeFormModal(); loadActionItems(); }
			else { alert(res.data?.message || 'Could not save the item.'); if (save) save.disabled = false; }
		} catch (e) { alert('Could not save the item.'); if (save) save.disabled = false; }
	});
	$('.sp-aae-delete', backdrop)?.addEventListener('click', async () => {
		// Shared item (2+ people) → let them delete for everyone OR just remove themselves. Solo → plain confirm.
		const shared = Array.isArray(item.visible_to) && item.visible_to.length > 1;
		let scope = 'all';
		if (shared) {
			const choice = await spChoiceDialog({
				title: 'Delete action item',
				message: 'This action item is shared with other people. Delete it for everyone, or just remove yourself and leave it for the others?',
				options: [
					{ label: 'Delete for everyone', value: 'all', class: 'sp-btn-primary sp-btn-delete' },
					{ label: 'Just remove me', value: 'me', class: 'sp-btn-secondary' },
					{ label: 'Cancel', value: null, class: 'sp-btn-ghost' },
				],
			});
			if (!choice) return;
			scope = choice;
		} else if (!confirm('Delete this action item completely? This removes it entirely — it will NOT show under Completed. This cannot be undone.')) {
			return;
		}
		const del = $('.sp-aae-delete', backdrop);
		if (del) del.disabled = true;
		try {
			const res = await spAjax('site_pulse_delete_action_item', { item_id: item.id, scope });
			if (res.success) { closeFormModal(); loadActionItems(); loadNotificationCount(); }
			else { alert(res.data?.message || 'Could not delete the item.'); if (del) del.disabled = false; }
		} catch (e) { alert('Could not delete the item.'); if (del) del.disabled = false; }
	});
}

// YYYY-MM-DD for today + n days (local).
function spDatePlusDays(n) {
	const d = new Date();
	d.setDate(d.getDate() + n);
	return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

async function populateActionItemFilters() {
	const locSelect = $('#sp-action-filter-location');
	if (!locSelect) return;

	try {
		const res = await spAjax('site_pulse_get_review_filters', {});
		if (!res.success) return;

		if (res.data.locations) {
			res.data.locations.forEach(l => {
				const opt = document.createElement('option');
				opt.value = l.id;
				opt.textContent = l.name;
				locSelect.appendChild(opt);
			});
		}
	} catch (err) {}
}

async function loadActionItems() {
	const list = $('#sp-action-items-list');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';

	// "Order by Due Date" becomes "Order by Completed Date" while the Completed filter is active.
	const sortDueOpt = $('#sp-action-sort option[value="duedate"]');
	if (sortDueOpt) sortDueOpt.textContent = (($('#sp-action-filter-status')?.value || '') === 'resolved') ? 'Order by Completed Date' : 'Order by Due Date';

	const locVal = $('#sp-action-filter-location')?.value || 'mine';
	const filters = { status: $('#sp-action-filter-status')?.value || '' };
	if (locVal === 'mine') filters.mine = '1';
	else if (locVal) filters.location_id = locVal;
	const catVal = $('#sp-action-filter-category')?.value || '';
	if (catVal) filters.category = catVal;

	try {
		const res = await spAjax('site_pulse_get_action_items', filters);
		if (!res.success) { list.innerHTML = '<div class="sp-empty-state"><p>Error loading action items.</p></div>'; return; }
		// (Re)build the label filter from the labels present across the user's items (all statuses).
		_spActionLabels = res.data.categories || []; // remembered for the Add-item label suggestions
		const catSel = $('#sp-action-filter-category');
		if (catSel) {
			const cats = _spActionLabels;
			catSel.innerHTML = '<option value="">All labels</option>' + cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
			catSel.value = (cats.indexOf(catVal) !== -1) ? catVal : '';
		}
		renderActionItems(list, res.data.items, res.data.pending || []);
	} catch (err) {
		list.innerHTML = '<div class="sp-empty-state"><p>Error loading action items.</p></div>';
	}
}

function renderActionItems(container, items, pending = []) {
	// Index every row so the edit button can pull its current text/label without unsafe data attributes.
	_spActionItemsById = {};
	(items || []).forEach(i => { _spActionItemsById[i.id] = i; });
	(pending || []).forEach(i => { _spActionItemsById[i.id] = i; });

	if ((!items || items.length === 0) && (!pending || pending.length === 0)) {
		container.innerHTML = '<div class="sp-empty-state"><p>No action items.</p></div>';
		return;
	}

	const sortMode = $('#sp-action-sort')?.value || 'importance';
	const priorityOrder = { high: 0, medium: 1, low: 2 };

	if (sortMode === 'importance' && items) {
		items.sort((a, b) => (priorityOrder[a.priority] ?? 1) - (priorityOrder[b.priority] ?? 1));
	} else if (sortMode === 'duedate' && items) {
		// When viewing Completed items this option sorts by COMPLETION date (most recent first);
		// otherwise it's the usual due-date sort (soonest first). The label is swapped to match.
		if (($('#sp-action-filter-status')?.value || '') === 'resolved') {
			items.sort((a, b) => (b.resolved_at || '').localeCompare(a.resolved_at || ''));
		} else {
			items.sort((a, b) => (a.due_date || '9999-12-31').localeCompare(b.due_date || '9999-12-31'));
		}
	}
	// 'custom' uses the display_order from the DB, which is how items arrive by default

	let html = '';

	if (pending.length > 0) {
		pending.sort((a, b) => (priorityOrder[a.priority] ?? 1) - (priorityOrder[b.priority] ?? 1));
		html += '<div class="sp-pending-section">';
		html += `<h3 class="sp-pending-heading">Pending Approval <span class="sp-pending-count">${pending.length}</span></h3>`;
		html += '<p class="sp-pending-intro">Use the checkmark to approve (adds it to your action items) or the trash to discard.</p>';
		pending.forEach(item => { html += renderActionItemCard(item, 'pending'); });
		html += '</div>';
	}

	if (items && items.length > 0) {
		items.forEach(item => { html += renderActionItemCard(item, item.status === 'open' ? 'open' : 'resolved'); });
	}

	container.innerHTML = html;
	markUniqueSpans(container);

	$$('.sp-review-approve-btn', container).forEach(btn => {
		btn.addEventListener('click', () => reviewActionItem(btn.dataset.itemId, 'approve', btn));
	});
	$$('.sp-review-reject-btn', container).forEach(btn => {
		btn.addEventListener('click', () => reviewActionItem(btn.dataset.itemId, 'reject', btn));
	});

	$$('.sp-god-delete-action', container).forEach(btn => {
		btn.addEventListener('click', async () => {
			if (!confirm('Permanently delete this action item? This cannot be undone.')) return;
			btn.disabled = true;
			try {
				const res = await spAjax('site_pulse_god_delete_action_item', { item_id: btn.dataset.itemId });
				if (res.success) loadActionItems();
				else { alert(res.data?.message || 'Error.'); btn.disabled = false; }
			} catch (err) { alert('Error deleting item.'); btn.disabled = false; }
		});
	});

	// Add Note: append a note; the server rewrites the item via AI and keeps the notes list.
	$$('.sp-action-note-btn', container).forEach(btn => {
		btn.addEventListener('click', () => {
			const itemEl = btn.closest('.sp-action-item');
			if (itemEl.querySelector('.sp-note-form')) return;

			const form = document.createElement('div');
			form.className = 'sp-note-form';
			form.innerHTML = `
				<textarea class="sp-input sp-note-text" placeholder="Add details — what happened or the next step…" rows="2"></textarea>
				<div style="display:flex;gap:8px;margin-top:8px;">
					<button type="button" class="unique sp-btn sp-btn-primary sp-note-save">Save Details</button>
					<button type="button" class="unique sp-btn sp-btn-ghost sp-note-cancel">Cancel</button>
				</div>`;
			itemEl.querySelector('.sp-action-item-content').appendChild(form);
			markUniqueSpans(form);
			form.querySelector('.sp-note-text').focus();
			form.querySelector('.sp-note-cancel').addEventListener('click', () => form.remove());

			form.querySelector('.sp-note-save').addEventListener('click', async () => {
				const note = form.querySelector('.sp-note-text').value.trim();
				if (!note) { alert('Please enter a note.'); return; }
				const save = form.querySelector('.sp-note-save');
				save.disabled = true;
				save.textContent = 'Saving…';
				try {
					const res = await spAjax('site_pulse_add_action_note', { item_id: btn.dataset.itemId, note: note });
					if (res.success) { loadActionItems(); }
					else { alert(res.data?.message || 'Error saving note.'); save.disabled = false; save.textContent = 'Save Details'; }
				} catch (err) { alert('Error saving note.'); save.disabled = false; save.textContent = 'Save Details'; }
			});
		});
	});

	// Clickable due date → native date picker → save the new date.
	$$('.sp-action-due-btn', container).forEach(btn => {
		btn.addEventListener('click', () => {
			const input = btn.parentElement.querySelector('.sp-action-due-input');
			if (!input) return;
			if (typeof input.showPicker === 'function') { try { input.showPicker(); return; } catch (e) {} }
			input.focus();
			input.click();
		});
	});
	$$('.sp-action-due-input', container).forEach(input => {
		input.addEventListener('change', async () => {
			const due = input.value;
			if (!due) return;
			try {
				const res = await spAjax('site_pulse_set_action_due_date', { item_id: input.dataset.itemId, due_date: due });
				if (res.success) loadActionItems();
				else alert(res.data?.message || 'Could not update the due date.');
			} catch (e) { alert('Could not update the due date.'); }
		});
	});

	// Item Complete: mark done → moves to the completed/archived view.
	$$('.sp-action-complete-btn', container).forEach(btn => {
		btn.addEventListener('click', async () => {
			btn.disabled = true;
			try {
				const res = await spAjax('site_pulse_complete_action_item', { item_id: btn.dataset.itemId });
				if (res.success) { loadActionItems(); loadNotificationCount(); }
				else { alert(res.data?.message || 'Error.'); btn.disabled = false; }
			} catch (err) { alert('Error completing item.'); btn.disabled = false; }
		});
	});

	// Edit: change the item's original text + label.
	$$('.sp-action-edit-btn', container).forEach(btn => {
		btn.addEventListener('click', () => {
			const item = _spActionItemsById[btn.dataset.itemId];
			if (item) spEditActionItem(item);
		});
	});

	// Reopen: undo an accidental completion → back to the open list.
	$$('.sp-action-reopen-btn', container).forEach(btn => {
		btn.addEventListener('click', async () => {
			btn.disabled = true;
			try {
				const res = await spAjax('site_pulse_reopen_action_item', { item_id: btn.dataset.itemId });
				if (res.success) { loadActionItems(); loadNotificationCount(); }
				else { alert(res.data?.message || 'Error.'); btn.disabled = false; }
			} catch (err) { alert('Error reopening item.'); btn.disabled = false; }
		});
	});

	// Thumbnail → enlarge in the shared attachment viewer. (Add/remove happen in the Edit modal.)
	$$('.sp-action-attach-thumb', container).forEach(btn => {
		btn.addEventListener('click', () => spMsgAttachModal(btn.dataset.url, btn.dataset.mime, btn.dataset.name));
	});

	initActionItemDragDrop(container);
}

function renderActionItemCard(item, mode) {
	const priorityClass = item.priority === 'high' ? 'sp-priority-high' : item.priority === 'medium' ? 'sp-priority-medium' : 'sp-priority-low';
	const isOpen = mode === 'open';
	const isPending = mode === 'pending';
	const isResolved = mode === 'resolved';

	const resolvedInfo = isResolved && item.resolved_at
		? `<div class="sp-action-resolved">Completed ${timeAgo(item.resolved_at)}${item.resolution_note ? ' — ' + esc(item.resolution_note) : ''}</div>`
		: '';

	const classes = [ 'sp-action-item', priorityClass ];
	if (isResolved) classes.push('sp-action-resolved-item');
	if (isPending) classes.push('sp-action-pending');
	if (isOpen) classes.push('sp-reorderable'); // marker for drag-reorder (drag starts from the handle only)

	// NOT draggable by default, so the text stays selectable; the handle flips draggable on while held.
	let html = `<div class="${classes.join(' ')}" data-item-id="${item.id}">`;
	if (isOpen) html += '<span class="sp-action-drag" title="Drag to reorder" aria-label="Drag to reorder">&#9776;</span>';
	if (isPending) {
		// Review controls on the LEFT so they line up down the list: checkmark approves, trash rejects.
		html += '<div class="sp-action-review-actions">';
		html += `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-approve sp-review-approve-btn" data-item-id="${item.id}" title="Approve" aria-label="Approve">${ICON_CHECK}</button>`;
		html += `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-review-reject-btn" data-item-id="${item.id}" title="Reject" aria-label="Reject">${ICON_DELETE}</button>`;
		html += '</div>';
	}
	html += '<div class="sp-action-item-content">';

	html += `<div class="sp-action-item-category"><span class="unique">${esc(item.category)}</span></div>`;
	html += `<div class="sp-action-item-desc">${esc(item.description)}</div>`;

	// Who it's for ▪ where — only when viewing SOMEONE ELSE'S item. On your own list it's redundant,
	// so the card goes straight from the to-do to "Added by".
	if (!item.mine) {
		const where = [];
		if (item.user_name) where.push(esc(item.user_name));
		if (item.location_name) where.push(esc(item.location_name));
		if (where.length) html += `<div class="sp-action-item-who"><span class="unique">${where.join(' ▪ ')}</span></div>`;
	}

	// Where it came from + when, and the due date. If YOU created it, drop the "by <name>" — just "Added on".
	if (!isPending) {
		const added = item.created_at ? formatDate(String(item.created_at).split(' ')[0]) : '';
		if (added) {
			const fromReport = parseInt(item.report_id, 10) > 0;
			const mineCreated = !fromReport && String(item.created_by) === String(D.userId);
			const source = fromReport ? 'Reports' : (item.creator_name ? esc(item.creator_name) : 'the team');
			const label = mineCreated ? `Added on ${added}` : `Added by ${source} on ${added}`;
			html += `<div class="sp-action-item-sub"><span class="unique">${label}</span></div>`;
		}
	}

	// Who this item is visible to — only worth showing when it's SHARED (2+ people). The viewer's own
	// name becomes "you" (moved to the end): e.g. "Visible to Victor Vazquez & you". Solo items → no line.
	const vt = Array.isArray(item.visible_to) ? item.visible_to : [];
	if (!isPending && vt.length > 1) {
		const others = [];
		let hasMe = false;
		vt.forEach(p => { if (String(p.id) === String(D.userId)) hasMe = true; else others.push(esc(p.name)); });
		const parts = hasMe ? others.concat('you') : others;
		let label;
		if (parts.length <= 1) label = parts[0] || '';
		else label = parts.slice(0, -1).join(', ') + ' & ' + parts[parts.length - 1];
		if (label) html += `<div class="sp-action-item-sub sp-action-item-visible"><span class="unique">Visible to ${label}</span></div>`;
	}

	if (item.due_date) {
		if (isResolved) {
			html += `<div class="sp-action-item-sub"><span class="unique">Was due by ${formatDate(item.due_date)}</span></div>`;
		} else {
			const d = daysUntil(item.due_date);
			let cls = 'sp-action-item-sub';
			let text = `Due for completion by ${formatDate(item.due_date)}`;
			if (d !== null) {
				if (d < 0) { const n = -d; text = `${n} day${n === 1 ? '' : 's'} past due`; cls += ' sp-due-urgent'; }
				else if (d <= 1) { cls += ' sp-due-urgent'; }   // today or tomorrow
				else if (d <= 3) { cls += ' sp-due-soon'; }     // 2–3 days out
			}
			// Clickable → date picker to extend/move the due date.
			html += `<div class="${cls}">`
				+ `<button type="button" class="unique sp-action-due-btn" data-item-id="${item.id}" title="Change due date">${text}</button>`
				+ `<input type="date" class="sp-action-due-input" data-item-id="${item.id}" value="${item.due_date}" tabindex="-1" aria-label="Due date">`
				+ `</div>`;
		}
	}

	// Notes accumulated via "Add Note".
	let meta = null;
	if (item.meta) { try { meta = (typeof item.meta === 'string') ? JSON.parse(item.meta) : item.meta; } catch (e) {} }
	const notes = (meta && Array.isArray(meta.notes)) ? meta.notes : [];
	if (notes.length) {
		html += '<div class="sp-action-notes"><div class="sp-action-notes-label">Notes:</div><ul>';
		notes.slice().reverse().forEach(n => { html += `<li><span class="unique">${esc(n)}</span></li>`; });
		html += '</ul></div>';
	}

	html += resolvedInfo;

	// Buttons sit under the info lines (inside the content column). Anyone who can see the item can
	// Add Details; only the OWNER sees Mark Complete.
	if (isOpen) {
		html += '<div class="sp-action-item-actions">';
		if (item.mine) html += iconBtn('edit', 'sp-action-edit-btn', `data-item-id="${item.id}"`);
		html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-action-note-btn" data-item-id="${item.id}">+ Add Details</button>`;
		if (item.mine) html += `<button type="button" class="unique sp-btn sp-btn-primary sp-btn-ico sp-action-complete-btn" data-item-id="${item.id}">${ICON_CHECK}<span>Mark Complete</span></button>`;
		html += '</div>';
	}
	// Completed items: let the owner undo an accidental completion.
	if (isResolved && item.mine) {
		html += '<div class="sp-action-item-actions">';
		html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-action-reopen-btn" data-item-id="${item.id}">Reopen</button>`;
		html += '</div>';
	}

	// Photo attachments — thumbnails at the bottom; click to enlarge. (Add/remove live in the Edit modal.)
	const atts = (meta && Array.isArray(meta.attachments)) ? meta.attachments : [];
	if (atts.length) {
		html += '<div class="sp-action-item-attachments">';
		atts.forEach(a => {
			const isImg = (a.mime || '').indexOf('image/') === 0;
			html += '<div class="sp-action-attach">';
			html += `<button type="button" class="unique sp-action-attach-thumb${isImg ? '' : ' sp-action-attach-file'}" data-url="${esc(a.url)}" data-mime="${esc(a.mime || '')}" data-name="${esc(a.name || '')}">`
				+ (isImg ? `<img src="${esc(a.url)}" alt="${esc(a.name || '')}" loading="lazy">` : ICON_ATTACH)
				+ '</button>';
			html += '</div>';
		});
		html += '</div>';
	}

	html += '</div>';

	html += '</div>';
	return html;
}

async function reviewActionItem(itemId, decision, btn) {
	if (decision === 'reject' && !confirm('Reject this action item? It will be removed permanently.')) return;

	const itemEl = btn?.closest('.sp-action-item');
	const buttons = itemEl ? $$('.sp-btn', itemEl) : [];
	buttons.forEach(b => b.disabled = true);

	try {
		const res = await spAjax('site_pulse_review_action_item', { item_id: itemId, decision: decision });
		if (res.success) {
			loadActionItems();
			loadNotificationCount();
		} else {
			alert(res.data?.message || 'Error reviewing item.');
			buttons.forEach(b => b.disabled = false);
		}
	} catch (err) {
		alert('Error reviewing item.');
		buttons.forEach(b => b.disabled = false);
	}
}

function initActionItemDragDrop(wrap) {
	let dragItem = null;

	$$('.sp-action-item.sp-reorderable', wrap).forEach(item => {
		// Only a grab on the ≡ handle turns the card draggable — otherwise the text is selectable as
		// normal. draggable is flipped back off after the drag (or a click that didn't drag).
		const handle = item.querySelector('.sp-action-drag');
		if (handle) {
			const arm   = () => { item.draggable = true; };
			const disarm = () => { item.draggable = false; };
			handle.addEventListener('mousedown', arm);
			handle.addEventListener('touchstart', arm, { passive: true });
			handle.addEventListener('mouseup', disarm);
			handle.addEventListener('touchend', disarm);
		}

		item.addEventListener('dragstart', (e) => {
			dragItem = item;
			item.classList.add('sp-dragging');
			e.dataTransfer.effectAllowed = 'move';
		});

		item.addEventListener('dragend', () => {
			item.classList.remove('sp-dragging');
			item.draggable = false; // re-lock so the text is selectable again
			dragItem = null;
			$$('.sp-action-item', wrap).forEach(el => el.classList.remove('sp-drag-over'));
		});

		item.addEventListener('dragover', (e) => {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			if (dragItem && item !== dragItem) {
				item.classList.add('sp-drag-over');
			}
		});

		item.addEventListener('dragleave', () => {
			item.classList.remove('sp-drag-over');
		});

		item.addEventListener('drop', async (e) => {
			e.preventDefault();
			item.classList.remove('sp-drag-over');
			if (!dragItem || item === dragItem) return;

			const items = [...wrap.querySelectorAll('.sp-action-item.sp-reorderable')];
			const fromIndex = items.indexOf(dragItem);
			const toIndex = items.indexOf(item);

			if (fromIndex < toIndex) {
				wrap.insertBefore(dragItem, item.nextSibling);
			} else {
				wrap.insertBefore(dragItem, item);
			}

			const sortSelect = $('#sp-action-sort');
			if (sortSelect) sortSelect.value = 'custom';

			const newOrder = [...wrap.querySelectorAll('.sp-action-item.sp-reorderable')].map(el => el.dataset.itemId);
			try {
				await spAjax('site_pulse_reorder_action_items', { order: newOrder });
			} catch (err) {
				loadActionItems();
			}
		});
	});
}


/* ---- API Keys ---- */

async function loadApiKeys() {
	const wrap = $('#sp-admin-apikeys-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_settings', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading settings.</p>'; return; }
		renderApiKeys(wrap, res.data);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading settings.</p>';
	}
}

function renderApiKeys(wrap, data) {
	// One key field: optional "Active + masked" line, an input, a Save button (the setting key
	// + input id ride on the button as data-attrs so a single handler covers all of them).
	const keyRow = (o) => {
		let h = '<div class="sp-form-group">';
		h += `<label>${esc(o.label)}</label>`;
		if (o.set) {
			h += `<div style="margin-bottom:8px;"><span class="unique sp-status-badge sp-status-submitted">Active</span> <span class="unique" style="color:var(--sp-text-light);font-size:13px;">${esc(o.masked || '')}</span></div>`;
		}
		h += '<div style="display:grid;grid-template-columns:1fr auto;gap:8px;">';
		h += `<input type="text" id="${o.inputId}" class="sp-input" placeholder="${esc(o.placeholder)}">`;
		h += `<button type="button" class="unique sp-btn sp-btn-primary sp-save-setting-key" data-key="${o.settingKey}" data-input="${o.inputId}">Save Key</button>`;
		h += '</div>';
		if (o.help) h += `<div class="sp-help-text">${o.help}</div>`;
		h += '</div>';
		return '<div class="sp-card sp-settings-card">' + h + '</div>';
	};

	let html = '<p class="sp-help-text" style="margin:0 0 20px;">Keys are stored securely and shown masked once saved. Re-enter a key to replace it.</p>';

	html += keyRow({
		label: 'Claude API Key', set: data.claude_api_key_set, masked: data.claude_api_key_masked,
		inputId: 'sp-setting-claude-key', settingKey: 'claude_api_key',
		placeholder: 'Enter Claude API key (sk-ant-...)',
		help: 'Get your API key from <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>. Required for AI-powered action items and insights.',
	});

	html += keyRow({
		label: 'Google API Key — Website-Restricted (browser)', set: data.maps_js_api_key_set, masked: data.maps_js_api_key_masked,
		inputId: 'sp-setting-maps-key', settingKey: 'maps_js_api_key',
		placeholder: 'Enter the website-restricted key (AIza...)',
		help: 'Runs in visitors\' browsers — the route map and place search. Restrict by <strong>Website (HTTP referrer)</strong> to <code>rovin.work</code>, and enable <strong>Maps JavaScript API</strong> + <strong>Places API (New)</strong>.',
	});

	html += keyRow({
		label: 'Google API Key — IP-Restricted (server)', set: data.google_api_key_set, masked: data.google_api_key_masked,
		inputId: 'sp-setting-google-key', settingKey: 'google_api_key',
		placeholder: 'Enter the IP-restricted key (AIza...)',
		help: 'Runs server-side — mileage distance calculations + geocoding. Restrict by <strong>IP address</strong> (your WP Engine outbound IP), and enable <strong>Geocoding API</strong> + <strong>Routes API</strong>.',
	});

	html += keyRow({
		label: 'TollGuru API Key', set: data.tollguru_api_key_set, masked: data.tollguru_api_key_masked,
		inputId: 'sp-setting-tollguru-key', settingKey: 'tollguru_api_key',
		placeholder: 'Enter TollGuru API key',
		help: 'Prices tolls when a driver checks the Toll box on a mileage leg. Get a key at <a href="https://tollguru.com/" target="_blank" rel="noopener">tollguru.com</a> (free tier: 150/day on a business email). Vehicle type &amp; cost basis are set under Mileage admin → Toll Pricing.',
	});

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-save-setting-key', wrap).forEach(btn => {
		btn.addEventListener('click', async () => {
			const settingKey = btn.dataset.key;
			const inp = $('#' + btn.dataset.input, wrap);
			const key = inp?.value?.trim();
			if (!key) { alert('Please enter a key.'); return; }
			btn.disabled = true;
			try {
				const res = await spAjax('site_pulse_admin_save_setting', { key: settingKey, value: key });
				if (res.success) { if (inp) inp.value = ''; loadApiKeys(); }
				else { alert(res.data?.message || 'Error saving.'); btn.disabled = false; }
			} catch (err) { alert('Error saving key.'); btn.disabled = false; }
		});
	});
}


/* ---- Color Scheme (Site Settings) — AI-generated theming ---- */

// Normalize a typed/pasted hex (#abc, abc, #aabbcc, aabbcc) → '#rrggbb', or '' if invalid.
function spSanitizeHex(val) {
	val = String(val || '').trim().replace(/^#/, '');
	if (/^[0-9a-fA-F]{3}$/.test(val)) val = val.split('').map(c => c + c).join('');
	return /^[0-9a-fA-F]{6}$/.test(val) ? '#' + val.toLowerCase() : '';
}

// Apply a full CSS-variable map to the live document (instant preview before Save).
function spApplyVars(vars) {
	const root = document.documentElement;
	Object.keys(vars || {}).forEach(k => root.style.setProperty(k, vars[k]));
}

let spColorState = null;

// Settings → Customer Connect — the white-label customer app's branding/config, saved into Site
// Pulse settings (cc_-prefixed) which cc_get() now prefers over the functions-site.php option.
async function loadCcSettings() {
	const wrap = $('#sp-admin-customer-connect-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	let s = {};
	try {
		const res = await spAjax('site_pulse_cc_get_settings', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading Customer Connect settings.</p>'; return; }
		s = res.data.settings || {};
	} catch (e) { wrap.innerHTML = '<p>Error loading Customer Connect settings.</p>'; return; }

	const field = (id, label, val, ph, hint) =>
		`<div class="sp-form-group"><label>${esc(label)}</label>`
		+ (hint ? `<p class="sp-help-text" style="margin:0 0 6px;">${hint}</p>` : '')
		+ `<input type="text" id="sp-cc-${id}" class="sp-input" value="${esc(val || '')}" placeholder="${esc(ph || '')}"></div>`;

	let html = '<div class="sp-card sp-settings-card">';
	html += '<h3 style="margin:0 0 4px;">Customer Connect</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 16px;">Branding and config for the white-label customer app. These override what\'s set in functions-site.php. Reinstall the app on a device to see branding changes.</p>';
	html += field('app_name', 'App name', s.app_name, 'e.g. MAK Comfort Companion');
	html += field('company_name', 'Company name', s.company_name, 'e.g. MAK Comfort');
	html += field('pwa_short_name', 'Home-screen label', s.pwa_short_name, 'e.g. Comfort', 'Short name shown under the installed icon (defaults to the first word of the app name).');
	html += '<div class="sp-form-group"><label>Brand colors</label><p class="sp-help-text" style="margin:0;">The customer app uses the site color scheme — its app bar &amp; buttons follow your <strong>primary</strong> color and highlights follow your <strong>accent</strong> color. Change them in <em>Settings → Color scheme</em>.</p></div>';
	html += field('pwa_background_color', 'Splash background', s.pwa_background_color, '#ffffff');
	html += field('pwa_icon_url', 'App icon URL', s.pwa_icon_url, 'https://…/app-icon.png', 'Media Library URL of a square icon. Blank = the child-theme app icon / Site Icon.');
	html += field('push_contact', 'Push contact', s.push_contact, 'mailto:you@domain.com', 'VAPID contact address for push (defaults to the site admin email).');
	html += '<div style="margin-top:6px;display:flex;align-items:center;gap:12px;"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-cc-settings-save">Save</button><span class="sp-help-text" id="sp-cc-settings-status"></span></div>';
	html += '</div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-cc-settings-save', wrap)?.addEventListener('click', async () => {
		const btn = $('#sp-cc-settings-save', wrap);
		const st = $('#sp-cc-settings-status', wrap);
		const keys = ['app_name', 'company_name', 'pwa_short_name', 'pwa_background_color', 'pwa_icon_url', 'push_contact'];
		const data = {};
		keys.forEach(k => { const el = $('#sp-cc-' + k, wrap); if (el) data[k] = el.value.trim(); });
		btn.disabled = true;
		if (st) st.textContent = 'Saving…';
		try {
			const res = await spAjax('site_pulse_cc_save_settings', data);
			if (st) st.textContent = res.success ? 'Saved.' : (res.data?.message || 'Error.');
		} catch (e) { if (st) st.textContent = 'Error saving.'; }
		btn.disabled = false;
	});
}

async function loadAdminSettings() {
	const wrap = $('#sp-admin-settings-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_admin_get_color_scheme', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading settings.</p>'; return; }
		renderColorScheme(wrap, res.data || {});
	} catch (err) {
		wrap.innerHTML = '<p>Error loading settings.</p>';
	}
}

function renderColorScheme(wrap, data) {
	spColorState = { vars: null, dirty: false };

	// Two MAIN colors + an ACCENT (slot 3). The engine makes the darker main color the dark UI tone
	// (primary) and the lighter one the light backgrounds (secondary); the accent is the pop.
	const SP_BRAND_SLOTS = [
		{ label: 'Main color 1', hint: '' },
		{ label: 'Main color 2', hint: '' },
		{ label: 'Accent color', hint: 'Tertiary pop — buttons, links & highlights' },
	];
	const brandSeed = ['#474c54', '#cfe8d6', '#ec9a3c'];
	const savedBrand = Array.isArray(data.brand) ? data.brand.map(spSanitizeHex).filter(Boolean) : [];
	let brandColors = SP_BRAND_SLOTS.map((s, i) => savedBrand[i] || brandSeed[i]);

	// App icon — the installed-app / push-notification icon. Paste a Media Library URL.
	let html = '<div class="sp-card sp-settings-card">';
	html += '<h3 style="margin:0 0 4px;">App Icon</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">The icon for the installed app (home screen / desktop) and push notifications. Paste the image’s URL from the Media Library — a square PNG or WEBP works best. Leave blank to use the site default. It rebuilds automatically; reinstall the app to see the new icon on a device.</p>';
	html += '<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">';
	html += `<img id="sp-appicon-preview" src="${esc(data.app_icon_built || '')}" alt="" style="width:56px;height:56px;border-radius:12px;border:1px solid var(--sp-border);object-fit:cover;${data.app_icon_built ? '' : 'display:none;'}">`;
	html += `<input type="text" id="sp-appicon-url" class="sp-input" value="${esc(data.app_icon || '')}" placeholder="https://…/app-icon.webp" style="flex:1 1 320px;min-width:240px;">`;
	html += '</div>';
	html += '<div style="margin-top:10px;display:flex;align-items:center;gap:12px;"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-appicon-save">Save Icon</button><span class="sp-help-text" id="sp-appicon-status"></span></div>';
	html += '</div>';

	// Dashboard news feed — keywords for the full-width Google Alerts widget on the dashboard.
	html += '<div class="sp-card sp-settings-card">';
	html += '<h3 style="margin:0 0 4px;">Dashboard News Feed</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">Show a Google Alerts feed across the top of everyone’s dashboard — enter the keywords to track (usually your brand name).</p>';
	html += `<label class="sp-reminder-toggle" style="margin:0 0 12px;"><input type="checkbox" id="sp-alert-enabled"${(String(data.news_enabled) !== '0') ? ' checked' : ''}> Show news feed on dashboards</label>`;
	html += `<input type="text" id="sp-alert-keywords" class="sp-input" value="${esc(data.alert_keywords || '')}" placeholder="e.g. Babe&#39;s Chicken" style="max-width:24em;">`;
	html += '<div style="margin-top:10px;display:flex;align-items:center;gap:12px;"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-alert-save">Save</button><span class="sp-help-text" id="sp-alert-status"></span></div>';
	html += '</div>';

	html += '<div class="sp-card sp-settings-card">';
	html += '<h3 style="margin:0 0 4px;">Color Scheme</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 18px;">Pick two main colors plus an accent and the app themes everything — backgrounds, text, menus, buttons and hovers — keeping it all legible. The darker of your two main colors becomes the dark UI tone (sidebar, headings); the lighter becomes light backgrounds; the accent drives buttons and highlights. The preview updates instantly; click Save to keep it.</p>';

	html += '<div class="sp-ai-colors">';
	html += '<h4 class="sp-ai-colors-title">Your company colors</h4>';
	html += '<p class="sp-help-text" style="margin:4px 0 12px;">Click a swatch for the color wheel, or type / paste a hex code. The app builds a full light→dark range from each. Your primary (dark) tone stays as dark as the color you pick.</p>';
	html += '<div class="sp-brand-rows" id="sp-brand-rows"></div>';
	html += '<div class="sp-brand-controls" style="margin-top:12px;">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-ai-generate">Generate color scheme</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-color-save" disabled>Save</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-color-reset">Reset to Default</button>';
	html += '<span class="sp-color-status" id="sp-color-status"></span>';
	html += '</div>';
	html += '<div class="sp-ai-rationale" id="sp-ai-rationale" hidden></div>';
	html += '</div>';
	html += '</div>';

	// Fine-tune: per-element color overrides on top of the generated scheme (wired by spWireOverrides).
	html += '<div class="sp-card sp-settings-card"><h3 style="margin:0 0 4px;">Fine-tune elements</h3>'
		+ '<p class="sp-help-text" style="margin:0 0 12px;">Optional. Override individual pieces of the generated scheme — each change previews instantly. Reset a row to return it to the generated color. Overrides survive re-generating the scheme.</p>'
		+ '<div id="sp-color-overrides"></div>'
		+ '<div style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-ov-save">Save overrides</button><button type="button" class="unique sp-btn sp-btn-ghost" id="sp-ov-reset-all">Reset all</button><span class="sp-help-text" id="sp-ov-status"></span></div>'
		+ '</div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);
	spWireOverrides(wrap, data);

	// App icon — save the pasted Media URL; refresh the preview from the rebuilt icon.
	const aiSave = $('#sp-appicon-save', wrap);
	aiSave?.addEventListener('click', async () => {
		const url = ($('#sp-appicon-url', wrap)?.value || '').trim();
		const st = $('#sp-appicon-status', wrap);
		aiSave.disabled = true;
		if (st) st.textContent = 'Saving…';
		try {
			const r = await spAjax('site_pulse_admin_save_app_icon', { url });
			if (r && r.success) {
				const prev = $('#sp-appicon-preview', wrap);
				if (prev) {
					if (r.data.preview) { prev.src = r.data.preview + (r.data.preview.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now(); prev.style.display = ''; }
					else { prev.style.display = 'none'; }
				}
				if (st) {
					if (r.data.built) st.textContent = 'Saved — icon rebuilt' + (r.data.resolved ? ' from ' + r.data.resolved : '') + '.';
					else if (url) st.textContent = 'Saved, but couldn’t build the icon from that image' + (r.data.resolved ? ' (server used "' + r.data.resolved + '")' : ' (couldn’t find it on the server)') + '. Check the URL or try a PNG.';
					else st.textContent = 'Cleared — using the default icon.';
				}
			} else { if (st) st.textContent = ''; alert((r && r.data && r.data.message) || 'Could not save.'); }
		} catch (e) { if (st) st.textContent = ''; alert('Could not save.'); }
		aiSave.disabled = false;
	});

	// Dashboard news-feed settings — saved independently of the color scheme.
	const akSave = $('#sp-alert-save', wrap);
	const akEnabled = $('#sp-alert-enabled', wrap);
	const akKw = $('#sp-alert-keywords', wrap);
	// Grey the keywords field out when the feed is switched off.
	const syncAk = () => { if (akKw && akEnabled) akKw.disabled = !akEnabled.checked; };
	if (akEnabled) { akEnabled.addEventListener('change', syncAk); syncAk(); }
	if (akSave) akSave.addEventListener('click', async () => {
		const val = (akKw ? akKw.value : '').trim();
		const enabled = (akEnabled && akEnabled.checked) ? '1' : '0';
		const st = $('#sp-alert-status', wrap);
		akSave.disabled = true;
		if (st) st.textContent = 'Saving…';
		try {
			const r1 = await spAjax('site_pulse_admin_save_setting', { key: 'dashboard_news_enabled', value: enabled });
			const r2 = await spAjax('site_pulse_admin_save_setting', { key: 'dashboard_alert_keywords', value: val });
			if (r1 && r1.success && r2 && r2.success) { if (st) st.textContent = 'Saved — reload the dashboard to see it.'; }
			else { if (st) st.textContent = ''; alert('Could not save.'); }
		} catch (e) { if (st) st.textContent = ''; alert('Could not save.'); }
		akSave.disabled = false;
	});

	const saveBtn = $('#sp-color-save', wrap);
	const status = $('#sp-color-status', wrap);

	/* ---- Brand color inputs (three fixed slots) ---- */
	const renderBrandRows = () => {
		const box = $('#sp-brand-rows', wrap);
		if (!box) return;
		box.innerHTML = SP_BRAND_SLOTS.map((s, i) => {
			const c = brandColors[i] || '#888888';
			return `<div class="sp-brand-row">`
				+ `<div class="sp-brand-slot-label"><span class="sp-brand-slot-name">${esc(s.label)}</span><span class="sp-help-text">${esc(s.hint)}</span></div>`
				+ `<button type="button" class="sp-color-swatch sp-brand-swatch" data-i="${i}" style="background:${esc(c)};" aria-label="${esc(s.label)} color"></button>`
				+ `<input type="text" class="sp-color-hex-input sp-brand-hex" data-i="${i}" value="${esc(c)}" maxlength="7" spellcheck="false" autocomplete="off">`
				+ `<input type="color" class="sp-brand-picker" data-i="${i}" value="${esc(c)}" tabindex="-1" aria-hidden="true">`
				+ `</div>`;
		}).join('');

		$$('.sp-brand-swatch', box).forEach(sw => sw.addEventListener('click', () => {
			const p = $(`.sp-brand-picker[data-i="${sw.dataset.i}"]`, box);
			if (p) p.click();
		}));
		$$('.sp-brand-picker', box).forEach(p => {
			const apply = () => {
				const i = +p.dataset.i;
				brandColors[i] = p.value;
				const sw = $(`.sp-brand-swatch[data-i="${i}"]`, box); if (sw) sw.style.background = p.value;
				const hx = $(`.sp-brand-hex[data-i="${i}"]`, box); if (hx) { hx.value = p.value; hx.classList.remove('invalid'); }
			};
			p.addEventListener('input', apply);
			p.addEventListener('change', apply);
		});
		$$('.sp-brand-hex', box).forEach(hx => {
			hx.addEventListener('input', () => {
				const i = +hx.dataset.i;
				const hex = spSanitizeHex(hx.value);
				if (hex) {
					hx.classList.remove('invalid');
					brandColors[i] = hex;
					const sw = $(`.sp-brand-swatch[data-i="${i}"]`, box); if (sw) sw.style.background = hex;
					const p = $(`.sp-brand-picker[data-i="${i}"]`, box); if (p) p.value = hex;
				} else { hx.classList.add('invalid'); }
			});
			hx.addEventListener('blur', () => { const i = +hx.dataset.i; hx.classList.remove('invalid'); hx.value = brandColors[i]; });
		});
	};
	renderBrandRows();

	const genBtn = $('#sp-ai-generate', wrap);
	if (genBtn) genBtn.addEventListener('click', async () => {
		const valid = brandColors.map(spSanitizeHex).filter(Boolean);
		if (!valid.length) { alert('Enter at least one company color.'); return; }
		const rat = $('#sp-ai-rationale', wrap);
		genBtn.disabled = true;
		const orig = genBtn.textContent;
		genBtn.textContent = 'Generating…';
		if (rat) { rat.hidden = false; rat.textContent = 'Asking AI for a balanced, legible scheme…'; }
		try {
			const res = await spAjax('site_pulse_admin_generate_color_scheme', { brand_colors: JSON.stringify(valid) });
			if (res.success && res.data.vars) {
				spColorState.vars = res.data.vars;
				spColorState.dirty = true;
				spApplyVars(res.data.vars);
				if (saveBtn) saveBtn.disabled = false;
				if (rat) rat.textContent = res.data.rationale ? ('💡 ' + res.data.rationale + ' — looks good? Click Save.') : 'Scheme applied — looks good? Click Save.';
				if (status) status.textContent = '';
			} else {
				if (rat) rat.hidden = true;
				alert(res.data?.message || 'Could not generate a scheme.');
			}
		} catch (e) {
			if (rat) rat.hidden = true;
			alert('AI request failed.');
		}
		genBtn.disabled = false;
		genBtn.textContent = orig;
	});

	if (saveBtn) saveBtn.addEventListener('click', async () => {
		if (!spColorState.vars) return;
		saveBtn.disabled = true;
		if (status) status.textContent = 'Saving…';
		const brand = brandColors.map(spSanitizeHex).filter(Boolean);
		try {
			const res = await spAjax('site_pulse_admin_save_color_scheme', {
				vars: JSON.stringify(spColorState.vars || {}),
				brand_colors: JSON.stringify(brand),
			});
			if (res.success) { if (status) status.textContent = '✓ Saved'; spColorState.dirty = false; }
			else { if (status) status.textContent = res.data?.message || 'Error saving.'; saveBtn.disabled = false; }
		} catch (err) { if (status) status.textContent = 'Error saving.'; saveBtn.disabled = false; }
	});

	const resetBtn = $('#sp-color-reset', wrap);
	if (resetBtn) resetBtn.addEventListener('click', async () => {
		if (!confirm('Reset all colors to the default scheme?')) return;
		resetBtn.disabled = true;
		try {
			const res = await spAjax('site_pulse_admin_reset_color_scheme', {});
			if (res.success) { location.reload(); }
			else { alert(res.data?.message || 'Error resetting.'); resetBtn.disabled = false; }
		} catch (err) { alert('Error resetting.'); resetBtn.disabled = false; }
	});
}


// Read a CSS custom property's computed value off :root ('' if unset).
function spReadVar(tok) {
	try { return getComputedStyle(document.documentElement).getPropertyValue(tok).trim(); } catch (e) { return ''; }
}

// Per-element color overrides: grouped rows (swatch + picker + reset) that layer on top of the
// generated scheme, preview live on :root, and save to the color_overrides setting.
function spWireOverrides(wrap, data) {
	const host = $('#sp-color-overrides', wrap);
	if (!host) return;
	const root = document.documentElement;
	const base = Object.assign({}, (data && data.override_base) || {});
	const brand = ((data && data.brand) || []).map(spSanitizeHex).filter(Boolean);
	let overrides = Object.assign({}, (data && data.overrides) || {});

	if (!Object.keys(base).length) {
		host.innerHTML = '<p class="sp-help-text" style="margin:0;">Generate &amp; save a color scheme above first — then you can fine-tune individual elements here.</p>';
		return;
	}

	const GROUPS = [
		{ col: 0, title: 'Sidebar', rows: [
			['--sp-sidebar-bg', 'Background'], ['--sp-sidebar-text', 'Text'],
			['--sp-nav-hover-bg', 'Item hover background'], ['--sp-nav-hover-text', 'Item hover text'],
			['--sp-sidebar-active-bg', 'Active item background'], ['--sp-sidebar-active-text', 'Active item text'],
		] },
		{ col: 0, title: 'Cards', rows: [
			['--sp-card-band', 'Header / footer background'], ['--sp-card-band-text', 'Header / footer text'],
			['--sp-card-border', 'Border'], ['--sp-bg-white', 'Card background'], ['--sp-card-text', 'Body text'],
		] },
		{ col: 0, title: 'Tiles', rows: [
			['--sp-tile-bg', 'Background'], ['--sp-tile-border', 'Border'],
			['--sp-tile-value', 'Value text'], ['--sp-tile-label', 'Label text'],
		] },
		{ col: 0, title: 'Surfaces', rows: [
			['--sp-accent-color', 'Accent (links & highlights)'], ['--sp-bg', 'Page background'],
			['--sp-text', 'Body text'], ['--sp-border', 'Borders'],
		] },
		{ col: 1, title: 'Primary button', btn: 'sp-btn-primary', rows: [
			['--sp-btn-bg', 'Background'], ['--sp-btn-border', 'Border'], ['--sp-btn-text', 'Text'],
			['--sp-btn-bg-hover', 'Hover background'], ['--sp-btn-border-hover', 'Hover border'], ['--sp-btn-text-hover', 'Hover text'],
		] },
		{ col: 1, title: 'Secondary button', btn: 'sp-btn-secondary', rows: [
			['--sp-btn2-bg', 'Background'], ['--sp-btn2-border', 'Border'], ['--sp-btn2-text', 'Text'],
			['--sp-btn2-bg-hover', 'Hover background'], ['--sp-btn2-border-hover', 'Hover border'], ['--sp-btn2-text-hover', 'Hover text'],
		] },
		{ col: 1, title: 'Ghost button', btn: 'sp-btn-ghost', rows: [
			['--sp-btn-ghost-text', 'Text & border'], ['--sp-btn-ghost-bg-hover', 'Hover background'], ['--sp-btn-ghost-text-hover', 'Hover text'],
		] },
	];

	// token -> friendly label (first element that uses it), for naming in-use theme colors.
	const tokLabel = {};
	GROUPS.forEach((g) => g.rows.forEach((r) => { if (!tokLabel[r[0]]) tokLabel[r[0]] = g.title + ' · ' + r[1]; }));

	// The "theme colors" swatch list, computed LIVE from current state: the company's brand colors
	// (always, sticky) + every colour currently applied to an element. So a custom colour picked on
	// one element is instantly reusable on another, and a colour nothing uses anymore drops off.
	function computePalette() {
		const out = [], seen = {};
		const push = (hex, label) => {
			hex = (spSanitizeHex(hex) || '').toLowerCase();
			if (!hex || seen[hex]) return;
			seen[hex] = true; out.push({ hex: hex, label: label });
		};
		const bl = ['Main color 1', 'Main color 2', 'Accent color'];
		brand.forEach((h, i) => push(h, bl[i] || ('Company color ' + (i + 1))));
		Object.keys(base).forEach((tok) => push(overrides[tok] || base[tok], tokLabel[tok] || 'In use'));
		Object.keys(overrides).forEach((tok) => push(overrides[tok], tokLabel[tok] || 'Custom'));
		return out;
	}

	const eff = (tok) => spSanitizeHex(overrides[tok] || base[tok] || spReadVar(tok)) || '#888888';

	// Number key: each theme color gets a 1-based number (palette order); every element row shows the
	// number of the colour it currently uses, so shared colours are obvious at a glance.
	function paletteIndex() { const m = {}; computePalette().forEach((p, i) => { m[p.hex] = i + 1; }); return m; }
	function palNum(hex) { return paletteIndex()[(spSanitizeHex(hex) || '').toLowerCase()] || ''; }
	function repaintNumbers() {
		const idx = paletteIndex();
		$$('.sp-ov-item', host).forEach((it) => {
			const n = $('.sp-ov-num', it);
			if (n) n.textContent = idx[(spSanitizeHex(eff(it.getAttribute('data-token'))) || '').toLowerCase()] || '';
		});
	}

	function rowHtml(tok, label) {
		const val = eff(tok), isOv = !!overrides[tok];
		return '<div class="sp-ov-item" data-token="' + tok + '" style="position:relative;">'
			+ '<div class="sp-ov-row" style="display:flex;align-items:center;gap:9px;padding:5px 0;">'
			+ '<button type="button" class="sp-ov-swatch" title="Change color" style="width:28px;height:24px;padding:0;border:1px solid var(--sp-border);border-radius:6px;cursor:pointer;flex:0 0 auto;background:' + val + ';"></button>'
			+ '<span class="sp-ov-num" title="Theme color number" style="flex:0 0 auto;min-width:1.2em;text-align:center;font-size:0.74em;font-weight:700;color:var(--sp-text-light);">' + palNum(val) + '</span>'
			+ '<span style="flex:1 1 auto;">' + esc(label) + '</span>'
			+ '<code class="sp-ov-hex" style="font-size:0.76em;color:var(--sp-text-light);">' + val + '</code>'
			+ '<button type="button" class="sp-ov-reset unique sp-btn sp-btn-ghost" style="padding:2px 7px;font-size:0.72em;' + (isOv ? '' : 'visibility:hidden;') + '">Reset</button>'
			+ '</div>'
			+ '<div class="sp-ov-pop" hidden style="position:absolute;z-index:20;left:0;top:100%;min-width:230px;padding:8px;border:1px solid var(--sp-border);border-radius:8px;background:var(--sp-bg-white);box-shadow:0 8px 24px rgba(0,0,0,0.14);"></div>'
			+ '</div>';
	}
	function colHtml(colIdx) {
		let h = '';
		GROUPS.filter((g) => g.col === colIdx).forEach((g) => {
			h += '<h4 style="margin:16px 0 6px;font-size:0.95em;font-weight:700;color:var(--sp-text);text-align:left;">' + esc(g.title) + '</h4>';
			if (g.btn) h += '<div style="margin:0 0 8px;"><button type="button" class="unique sp-btn ' + g.btn + '">Test Me</button></div>';
			g.rows.forEach((r) => { h += rowHtml(r[0], r[1]); });
		});
		return h;
	}
	host.innerHTML = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0 40px;align-items:start;">'
		+ '<div>' + colHtml(0) + '</div><div>' + colHtml(1) + '</div></div>';
	markUniqueSpans(host);
	repaintNumbers();

	const preview = (tok, hex) => root.style.setProperty(tok, hex);
	const unpreview = (tok) => { if (base[tok]) root.style.setProperty(tok, base[tok]); else root.style.removeProperty(tok); };

	function applyColor(item, tok, hex) {
		hex = spSanitizeHex(hex) || hex;
		overrides[tok] = hex;
		$('.sp-ov-swatch', item).style.background = hex;
		$('.sp-ov-hex', item).textContent = hex;
		$('.sp-ov-reset', item).style.visibility = 'visible';
		preview(tok, hex);
		repaintNumbers();
	}
	function closePops() { $$('.sp-ov-pop', host).forEach((p) => { p.hidden = true; }); }
	function buildPop(item, tok) {
		const pop = $('.sp-ov-pop', item);
		let ph = '<div style="font-size:0.72em;font-weight:700;color:var(--sp-text-secondary);margin-bottom:5px;">THEME COLORS</div><div style="display:flex;flex-wrap:wrap;gap:5px;">';
		computePalette().forEach((p, i) => { ph += '<span style="display:inline-flex;flex-direction:column;align-items:center;gap:2px;"><button type="button" class="sp-ov-pal" data-hex="' + p.hex + '" title="' + esc(p.label) + ' (' + p.hex + ')" style="width:24px;height:24px;border:1px solid var(--sp-border);border-radius:5px;cursor:pointer;background:' + p.hex + ';"></button><span style="font-size:0.66em;font-weight:700;color:var(--sp-text-light);line-height:1;">' + (i + 1) + '</span></span>'; });
		ph += '</div><label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-size:0.8em;color:var(--sp-text-secondary);cursor:pointer;">Custom color <input type="color" class="sp-ov-custom" value="' + (spSanitizeHex(eff(tok)) || '#888888') + '" style="width:34px;height:24px;padding:0;border:1px solid var(--sp-border);border-radius:5px;background:none;cursor:pointer;"></label>';
		pop.innerHTML = ph;
		$$('.sp-ov-pal', pop).forEach((sw) => sw.addEventListener('click', () => { applyColor(item, tok, sw.getAttribute('data-hex')); closePops(); }));
		$('.sp-ov-custom', pop).addEventListener('input', (e) => applyColor(item, tok, e.target.value));
	}

	$$('.sp-ov-item', host).forEach((item) => {
		const tok = item.getAttribute('data-token');
		$('.sp-ov-swatch', item).addEventListener('click', (e) => {
			e.stopPropagation();
			const pop = $('.sp-ov-pop', item), wasOpen = !pop.hidden;
			closePops();
			if (!wasOpen) { buildPop(item, tok); pop.hidden = false; }
		});
		$('.sp-ov-reset', item).addEventListener('click', () => {
			delete overrides[tok]; unpreview(tok);
			const b = spSanitizeHex(base[tok]) || '#888888';
			$('.sp-ov-swatch', item).style.background = b;
			$('.sp-ov-hex', item).textContent = b;
			$('.sp-ov-reset', item).style.visibility = 'hidden';
			repaintNumbers();
			closePops();
		});
	});
	host.addEventListener('click', (e) => { if (!e.target.closest('.sp-ov-item')) closePops(); });

	const saveBtn = $('#sp-ov-save', wrap), st = $('#sp-ov-status', wrap);
	saveBtn && saveBtn.addEventListener('click', async () => {
		saveBtn.disabled = true; if (st) st.textContent = 'Saving…';
		try {
			const res = await spAjax('site_pulse_admin_save_color_overrides', { overrides: JSON.stringify(overrides) });
			if (st) st.textContent = res.success ? '✓ Saved' : (res.data && res.data.message || 'Error.');
		} catch (e) { if (st) st.textContent = 'Error saving.'; }
		saveBtn.disabled = false;
	});
	const resetAll = $('#sp-ov-reset-all', wrap);
	resetAll && resetAll.addEventListener('click', () => {
		Object.keys(overrides).forEach((tok) => unpreview(tok));
		spWireOverrides(wrap, { override_base: base, overrides: {}, brand: brand });
		if (st) st.textContent = 'Reset — click Save overrides to keep it.';
	});
}


/* ---- Modules (superadmin only) ---- */

async function loadAdminModules() {
	const wrap = $('#sp-admin-modules-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_get_modules', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading modules.</p>'; return; }
		renderAdminModules(wrap, res.data.modules || []);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading modules.</p>';
	}
}

function renderAdminModules(wrap, modules) {
	let html = '<div class="sp-card sp-settings-card">';
	html += '<h3 style="margin:0 0 8px;">Active Modules</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 20px;">Turn features on or off for this install. Disabled modules disappear from the menu and their permissions go inactive (saved settings are kept). Changes take effect on the next page load.</p>';

	modules.forEach(m => {
		html += '<label class="sp-module-row" style="display:flex;align-items:flex-start;gap:12px;padding:14px 2px;border-bottom:1px solid #e5e7eb;cursor:pointer;">';
		html += `<input type="checkbox" class="sp-module-toggle" data-slug="${esc(m.slug)}"${m.enabled ? ' checked' : ''} style="margin-top:3px;flex:0 0 auto;">`;
		html += '<span>';
		html += `<span style="display:block;font-weight:600;">${m.label}</span>`;
		html += `<span class="sp-help-text" style="display:block;margin-top:2px;">${m.desc}</span>`;
		html += '</span>';
		html += '</label>';
	});

	html += '<div style="margin-top:20px;display:flex;align-items:center;gap:12px;">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-save-modules">Save Modules</button>';
	html += '<span id="sp-modules-saved" class="sp-help-text"></span>';
	html += '</div>';
	html += '</div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	const saveBtn = $('#sp-save-modules', wrap);
	saveBtn.addEventListener('click', async () => {
		const map = {};
		$$('.sp-module-toggle', wrap).forEach(cb => { map[cb.dataset.slug] = cb.checked ? '1' : '0'; });
		saveBtn.disabled = true;
		const note = $('#sp-modules-saved', wrap);
		if (note) note.textContent = 'Saving…';
		try {
			const res = await spAjax('site_pulse_save_modules', { modules: map });
			if (res.success) { if (note) note.textContent = 'Saved. Reload to apply.'; }
			else { if (note) note.textContent = ''; alert(res.data?.message || 'Error saving.'); }
		} catch (err) { if (note) note.textContent = ''; alert('Error saving modules.'); }
		saveBtn.disabled = false;
	});
}


/* ---- Tiers (Roles) ---- */

let spTierData = { roles: [], catalog: {}, grouped: [], counts: {} };

async function loadAdminTiers() {
	const wrap = $('#sp-admin-tiers-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_roles', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading roles.</p>'; return; }
		spTierData = { roles: res.data.roles || [], catalog: res.data.catalog || {}, grouped: res.data.grouped || [], counts: res.data.user_counts || {} };
		renderAdminTiers(wrap);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading roles.</p>';
	}
}

// Capability grid grouped by module (shared look with the roles cards) — checkboxes + per-module All/None.
function tierGridHtml(caps) {
	const groups = spTierData.grouped || [];
	if (!groups.length) return '';
	let html = '<div class="sp-arole-grid">';
	groups.forEach(g => {
		html += `<div class="sp-arole-modgroup" data-module="${esc(g.key)}">`;
		html += `<div class="sp-arole-modhead"><span class="sp-arole-modtitle">${esc(g.label)}</span>`;
		html += '<span class="sp-arole-modquick"><button type="button" class="unique sp-btn sp-btn-ghost sp-arole-all">All</button><button type="button" class="unique sp-btn sp-btn-ghost sp-arole-none">None</button></span></div>';
		html += '<div class="sp-arole-modcaps">';
		g.caps.forEach(c => {
			const checked = caps.includes(c.cap) ? ' checked' : '';
			html += `<label class="sp-tier-cap${c.admin ? ' sp-cap-admin' : ''}"><input type="checkbox" value="${esc(c.cap)}"${checked}> ${esc(c.label)}</label>`;
		});
		html += '</div></div>';
	});
	html += '</div>';
	return html;
}

// Tier 1..N by distinct hierarchy_level, highest = Tier 1. Roles arrive sorted by rank desc,
// so lateral roles (same level) share a number.
function tierNumbers(roles) {
	const map = {};
	let tier = 0, lastLevel = null;
	roles.forEach(r => {
		const lvl = parseInt(r.hierarchy_level, 10);
		if (lvl !== lastLevel) { tier++; lastLevel = lvl; }
		map[r.id] = tier;
	});
	return map;
}

function renderAdminTiers(wrap) {
	// ROLES = one flexible category (name + order + module-grouped permissions). A company models it as
	// org positions, software roles, or a mix — whatever fits.
	const { roles, counts } = spTierData;
	const tiers = tierNumbers(roles);

	let html = '<div class="sp-admin-toolbar">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-tier-btn">+ Add Role</button>';
	html += '</div>';
	html += '<p class="sp-help-text" style="margin:0 0 16px;">Rename or reorder roles (top = most senior), and tick what each can do — grouped by module. Fine-tune one individual below.</p>';
	html += '<div id="sp-tier-form-wrap" hidden></div>';

	if (!roles.length) {
		html += '<div class="sp-empty-state"><p>No roles yet.</p></div>';
	} else {
		html += '<div class="sp-tier-list">';
		roles.forEach((r, idx) => {
			const n = counts[r.id] || 0;
			let caps = [];
			try { caps = JSON.parse(r.capabilities || '[]') || []; } catch (e) {}
			html += `<div class="sp-card sp-tier-card sp-arole-card" data-id="${r.id}">`;
			html += '<div class="sp-arole-head">';
			html += `<span class="unique sp-tier-badge">${tiers[r.id]}</span>`;
			html += `<input type="text" class="sp-input sp-tier-name" value="${esc(r.label)}" aria-label="Role name">`;
			html += `<span class="sp-tier-count">${n} member${n === 1 ? '' : 's'}</span>`;
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-tier-up" title="Move up"${idx === 0 ? ' disabled' : ''}>&uarr;</button>`;
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-tier-down" title="Move down"${idx === roles.length - 1 ? ' disabled' : ''}>&darr;</button>`;
			html += `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-tier-delete"${n ? ' disabled title="Reassign members first"' : ' title="Delete" aria-label="Delete"'}>${ICON_DELETE}</button>`;
			html += '</div>';
			html += `<div class="sp-arole-body">${tierGridHtml(caps)}</div>`;
			html += '</div>';
		});
		html += '</div>';
	}

	// Fine-tune one individual on top of their role default.
	html += '<div class="sp-roles-user-picker" id="sp-roles-user-picker" hidden><label for="sp-roles-user-select">Customize an individual user’s permissions <span class="unique sp-text-secondary" style="font-weight:400;">(overrides their role — the role itself stays intact)</span></label>';
	html += '<select class="sp-select" id="sp-roles-user-select"><option value="">Select a user…</option></select></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-add-tier-btn')?.addEventListener('click', () => showTierForm());

	$$('.sp-tier-card', wrap).forEach(card => {
		const id = card.dataset.id;
		$('.sp-tier-name', card)?.addEventListener('change', () => saveTier(id, card));
		$('.sp-tier-delete', card)?.addEventListener('click', () => deleteTier(id, card));
		$('.sp-tier-up', card)?.addEventListener('click', () => moveTier(id, -1));
		$('.sp-tier-down', card)?.addEventListener('click', () => moveTier(id, 1));
		$$('.sp-arole-grid input[type="checkbox"]', card).forEach(cb => cb.addEventListener('change', () => saveTier(id, card)));
		$$('.sp-arole-modgroup', card).forEach(grp => {
			$('.sp-arole-all', grp)?.addEventListener('click', () => { $$('input[type="checkbox"]', grp).forEach(cb => cb.checked = true); saveTier(id, card); });
			$('.sp-arole-none', grp)?.addEventListener('click', () => { $$('input[type="checkbox"]', grp).forEach(cb => cb.checked = false); saveTier(id, card); });
		});
	});

	populateRolesUserPicker();
}

// Fill the Roles-page "Customize an individual user" dropdown (grouped by role) and open that
// user's Edit popup on selection — the same popup reached from the Users screen, permission grid
// and all. Needs the users/roles/locations payload, so it fetches get-users; if the admin lacks
// manage_users the call fails and the picker simply stays hidden.
async function populateRolesUserPicker() {
	const sel = $('#sp-roles-user-select');
	const box = $('#sp-roles-user-picker');
	if (!sel || !box) return;

	let data;
	try {
		const res = await spAjax('site_pulse_admin_get_users', {});
		if (!res.success) return;
		data = res.data;
	} catch (e) { return; }

	spPermCatalog = data.catalog || spPermCatalog;
	const users = data.users || [];
	const roles = data.roles || [];
	const locations = data.locations || [];
	const mileageLocations = data.mileage_locations || [];
	if (!users.length) return;

	const byRole = {};
	users.forEach(u => { const k = u.role_label || 'No role'; (byRole[k] = byRole[k] || []).push(u); });
	let opts = '<option value="">Select a user…</option>';
	Object.keys(byRole).forEach(label => {
		opts += `<optgroup label="${esc(label)}">`;
		byRole[label].forEach(u => { opts += `<option value="${u.user_id}">${esc(u.display_name || u.user_login)}</option>`; });
		opts += '</optgroup>';
	});
	sel.innerHTML = opts;
	box.hidden = false;

	sel.addEventListener('change', () => {
		const uid = sel.value;
		sel.value = '';
		if (!uid) return;
		const user = users.find(u => String(u.user_id) === String(uid));
		if (user) showUserForm(user, roles, locations, users, mileageLocations);
	});
}

function showTierForm() {
	const wrap = spFormModal();

	let html = '<div class="sp-tier-form">';
	html += '<h3 style="margin:0 0 12px;">New Role</h3>';
	html += '<div class="sp-form-group"><label>Role Name</label>';
	html += '<input type="text" id="sp-new-tier-name" class="sp-input" placeholder="e.g. Shift Lead"></div>';
	html += '<p class="sp-help-text" style="margin:0 0 12px;">Tick its permissions afterward on the role card.</p>';
	html += '<div class="sp-report-form-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-create-tier-btn">Create Role</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-cancel-tier-btn">Cancel</button>';
	html += '</div></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-cancel-tier-btn')?.addEventListener('click', () => closeFormModal());
	$('#sp-create-tier-btn')?.addEventListener('click', async () => {
		const label = $('#sp-new-tier-name')?.value?.trim();
		if (!label) { alert('Please enter a role name.'); return; }
		try {
			const res = await spAjax('site_pulse_admin_save_role', { id: 0, label, capabilities: [] });
			if (res.success) { closeFormModal(); loadAdminTiers(); }
			else alert(res.data?.message || 'Error creating role.');
		} catch (e) { alert('Error creating role.'); }
	});
}

async function saveTier(id, card) {
	const label = $('.sp-tier-name', card)?.value?.trim();
	if (!label) { spFlash('Role name required'); return; }
	const caps = $$('.sp-arole-grid input[type="checkbox"]:checked', card).map(c => c.value);
	try {
		const res = await spAjax('site_pulse_admin_save_role', { id, label, capabilities: caps });
		if (res.success) {
			spFlash('Saved');
			// Keep in-memory state current so reorder/delete use the latest name + caps (no full re-render).
			const role = spTierData.roles.find(x => String(x.id) === String(id));
			if (role) { role.label = label; role.capabilities = JSON.stringify(caps); }
		} else alert(res.data?.message || 'Error saving role.');
	} catch (e) { alert('Error saving role.'); }
}

async function deleteTier(id, card) {
	const name = $('.sp-tier-name', card)?.value || 'this role';
	if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
	try {
		const res = await spAjax('site_pulse_admin_delete_role', { id });
		if (res.success) loadAdminTiers();
		else alert(res.data?.message || 'Error deleting role.');
	} catch (e) { alert('Error deleting role.'); }
}

async function moveTier(id, dir) {
	const roles = spTierData.roles.slice();
	const idx = roles.findIndex(r => String(r.id) === String(id));
	const swap = idx + dir;
	if (idx < 0 || swap < 0 || swap >= roles.length) return;
	[roles[idx], roles[swap]] = [roles[swap], roles[idx]];
	try {
		const res = await spAjax('site_pulse_admin_reorder_roles', { order: roles.map(r => r.id) });
		if (res.success) loadAdminTiers();
		else alert(res.data?.message || 'Error reordering roles.');
	} catch (e) { alert('Error reordering roles.'); }
}


/* ---- Report Templates ---- */

// Roles available for the template "Required Role" picker (labels from the DB).
let spTemplateRoles = [];

/* ---- Review Settings: super-categories + sub-categories editor ---- */
let _spReviewCats = [];

async function loadReviewSettings() {
	const wrap = $('#sp-review-settings-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_review_categories', {});
		_spReviewCats = (res.success && Array.isArray(res.data.categories)) ? res.data.categories : [];
	} catch (e) { _spReviewCats = []; }
	if (!_spReviewCats.length) _spReviewCats = [{ name: '', subs: [] }]; // start with one
	if (!wrap._rcWired) { wrap._rcWired = true; wireReviewSettings(wrap); }
	renderReviewSettings();
}

// Pull the current input values back into the model before any structural change (add/remove/save),
// so typed-but-unsaved text isn't lost on re-render.
function spReviewCatsSyncFromDom() {
	const wrap = $('#sp-review-settings-content');
	if (!wrap) return;
	const cats = [];
	$$('.sp-rc-cat', wrap).forEach(catEl => {
		const name = catEl.querySelector('.sp-rc-name')?.value || '';
		const subs = [];
		$$('.sp-rc-sub', catEl).forEach(s => subs.push(s.value || ''));
		cats.push({ name, subs });
	});
	_spReviewCats = cats;
}

function renderReviewSettings() {
	const wrap = $('#sp-review-settings-content');
	if (!wrap) return;
	let html = '<div class="sp-card" style="max-width:760px">';
	html += '<div class="sp-card-body">';
	html += '<h3 style="margin:0 0 6px">Review Categories</h3>';
	html += '<p class="sp-help-text" style="margin:0 0 14px">The “super categories” reviews are ranked into. Each can have sub-categories that guide the AI (e.g. <em>Quality</em> → Food, Ingredients, Presentation). Saving re-analyzes all reviews under the new categories.</p>';
	html += '<div id="sp-rc-list">';
	_spReviewCats.forEach((c, ci) => {
		html += `<div class="sp-rc-cat sp-tile" data-ci="${ci}" style="border:1px solid var(--sp-border);border-radius:8px;padding:12px;margin-bottom:12px">`;
		html += '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">';
		html += `<input type="text" class="sp-input sp-rc-name" value="${esc(c.name || '')}" placeholder="Category (e.g. Quality)" style="font-weight:600">`;
		html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-rc-del-cat" data-ci="${ci}" title="Remove category">&times;</button>`;
		html += '</div>';
		html += '<div class="sp-rc-subs" style="padding-left:14px">';
		(c.subs || []).forEach((s, si) => {
			html += '<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">';
			html += `<input type="text" class="sp-input sp-rc-sub" value="${esc(s)}" placeholder="Sub-category (e.g. Food)">`;
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-rc-del-sub" data-ci="${ci}" data-si="${si}" title="Remove sub-category">&times;</button>`;
			html += '</div>';
		});
		html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-rc-add-sub" data-ci="${ci}">+ Add sub-category</button>`;
		html += '</div></div>';
	});
	html += '</div>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-rc-add-cat">+ Add Category</button>';
	html += '<span class="sp-help-text sp-rc-status" style="margin-left:10px"></span>';
	html += '</div>';
	html += '</div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);
}

let _spRcSaveTimer = null;
function scheduleReviewCatsSave() {
	clearTimeout(_spRcSaveTimer);
	_spRcSaveTimer = setTimeout(() => saveReviewCategories(), 700); // debounce typing
}

function wireReviewSettings(wrap) {
	wrap.addEventListener('click', (e) => {
		const b = e.target.closest('button');
		if (!b) return;
		const ci = b.dataset.ci != null ? parseInt(b.dataset.ci, 10) : -1;
		if (b.classList.contains('sp-rc-add-cat')) {
			spReviewCatsSyncFromDom(); _spReviewCats.push({ name: '', subs: [] }); renderReviewSettings();
		} else if (b.classList.contains('sp-rc-del-cat')) {
			spReviewCatsSyncFromDom(); _spReviewCats.splice(ci, 1);
			if (!_spReviewCats.length) _spReviewCats = [{ name: '', subs: [] }];
			renderReviewSettings(); saveReviewCategories(); // removal is meaningful → save now
		} else if (b.classList.contains('sp-rc-add-sub')) {
			spReviewCatsSyncFromDom();
			if (!_spReviewCats[ci].subs) _spReviewCats[ci].subs = [];
			_spReviewCats[ci].subs.push(''); renderReviewSettings();
		} else if (b.classList.contains('sp-rc-del-sub')) {
			spReviewCatsSyncFromDom(); _spReviewCats[ci].subs.splice(parseInt(b.dataset.si, 10), 1);
			renderReviewSettings(); saveReviewCategories();
		}
	});
	// Auto-save as the names are typed (debounced). Don't re-render on input → keeps focus while typing.
	wrap.addEventListener('input', (e) => {
		if (e.target.matches('.sp-rc-name, .sp-rc-sub')) scheduleReviewCatsSave();
	});
}

// Auto-save. Does NOT re-render (that would steal focus mid-typing); the server cleans blanks and the
// canonical list reloads next time the tab is opened.
async function saveReviewCategories() {
	clearTimeout(_spRcSaveTimer);
	spReviewCatsSyncFromDom();
	const clean = _spReviewCats
		.map(c => ({ name: (c.name || '').trim(), subs: (c.subs || []).map(s => (s || '').trim()).filter(Boolean) }))
		.filter(c => c.name);
	const status = $('.sp-rc-status', $('#sp-review-settings-content'));
	if (status) status.textContent = 'Saving…';
	try {
		const res = await spAjax('site_pulse_save_review_categories', { categories: JSON.stringify(clean) });
		if (!res.success) { if (status) status.textContent = res.data?.message || 'Could not save.'; return; }
		if (status) status.textContent = res.data.recategorized ? 'Saved · reviews will re-analyze' : 'Saved';
	} catch (e) { if (status) status.textContent = 'Could not save.'; }
}


/* ---- Review Trends: per-store scorecard vs the company average ---- */
let _spTrends = { data: null, series: null, lineHidden: {}, sortKey: 'stars', sortDir: -1, metric: 'stars', group: 'store', supervisor: '' };

async function loadReviewTrends() {
	const wrap = $('#sp-trends-content');
	if (!wrap) return;
	const rangeSel = $('#sp-trends-range'), brandSel = $('#sp-trends-brand');
	const groupSel = $('#sp-trends-group'), supSel = $('#sp-trends-supervisor');
	if (rangeSel && !rangeSel._wired) { rangeSel._wired = true; rangeSel.addEventListener('change', loadReviewTrends); }
	if (brandSel && !brandSel._wired) { brandSel._wired = true; brandSel.addEventListener('change', loadReviewTrends); }
	if (groupSel && !groupSel._wired) {
		groupSel._wired = true;
		groupSel.addEventListener('change', () => {
			_spTrends.group = groupSel.value;
			if (_spTrends.group === 'supervisor') { _spTrends.supervisor = ''; if (supSel) supSel.value = ''; } // ranking shows all
			loadReviewTrends();
		});
	}
	if (supSel && !supSel._wired) { supSel._wired = true; supSel.addEventListener('change', () => { _spTrends.supervisor = supSel.value; loadReviewTrends(); }); }
	if (supSel) supSel.disabled = (_spTrends.group === 'supervisor'); // filter is moot when already ranking supervisors

	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const params = {
			range: rangeSel ? rangeSel.value : 'all',
			brand: brandSel ? brandSel.value : '',
			group: _spTrends.group,
			supervisor: _spTrends.supervisor || ''
		};
		const [res, serRes] = await Promise.all([
			spAjax('site_pulse_review_scorecard', params),
			spAjax('site_pulse_review_trend_series', params)
		]);
		if (!res.success) { wrap.innerHTML = '<p class="sp-help-text">Could not load trends.</p>'; return; }
		_spTrends.data = res.data;
		_spTrends.series = (serRes && serRes.success) ? serRes.data : null;
		// Fill the brand dropdown once from the returned brands.
		if (brandSel && !brandSel._filled && Array.isArray(res.data.brands) && res.data.brands.length) {
			brandSel._filled = true;
			res.data.brands.forEach(b => { const o = document.createElement('option'); o.value = b; o.textContent = b; brandSel.appendChild(o); });
		}
		// (Re)build the supervisor dropdown, preserving the current selection.
		if (supSel) {
			const cur = _spTrends.supervisor || '';
			supSel.innerHTML = '<option value="">All supervisors</option>' +
				(res.data.supervisors || []).map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
			supSel.value = cur;
		}
		renderReviewTrends();
	} catch (e) { wrap.innerHTML = '<p class="sp-help-text">Could not load trends.</p>'; }
}

function renderReviewTrends() {
	const wrap = $('#sp-trends-content');
	const d = _spTrends.data;
	if (!wrap || !d) return;
	const cats = d.categories || [];
	const company = d.company || { count: 0, stars: null, scores: {} };
	const stores = (d.stores || []).slice();

	if (!stores.length) { wrap.innerHTML = '<p class="sp-help-text">No reviews in this range yet.</p>'; return; }

	// In supervisor mode rows represent supervisors and are click-to-drill into that supervisor's stores.
	const isSup = _spTrends.group === 'supervisor';
	const groupName = isSup ? 'supervisor' : 'store';
	const drillAttr = (s) => (isSup && s.sup_id) ? ` data-sup="${s.sup_id}"` : '';
	const drillCls  = (s) => (isSup && s.sup_id) ? ' sp-trend-drill' : '';

	const sk = _spTrends.sortKey, dir = _spTrends.sortDir;
	const valOf = (row) => {
		if (sk === 'store') return (row.store || '').toLowerCase();
		if (sk === 'count') return row.count || 0;
		if (sk === 'stars') return (row.stars == null ? -1 : row.stars);
		return (row.scores && row.scores[sk] != null) ? row.scores[sk] : -1;
	};
	stores.sort((a, b) => { const va = valOf(a), vb = valOf(b); return (va < vb ? -1 : (va > vb ? 1 : 0)) * dir; });

	// One value cell with a colored gap-vs-company badge + a heatmap background tinted by the gap.
	const cell = (val, base) => {
		if (val == null) return '<td class="sp-trend-cell"><span class="unique sp-trend-na">—</span></td>';
		const gap = (base == null) ? null : Math.round((val - base) * 10) / 10;
		let delta = '', bg = '';
		if (gap != null && gap !== 0) {
			const cls = gap > 0 ? 'sp-trend-up' : 'sp-trend-down';
			delta = ` <span class="unique ${cls}">${gap > 0 ? '▲' : '▼'}${Math.abs(gap).toFixed(1)}</span>`;
			const a = (Math.min(Math.abs(gap) / 0.8, 1) * 0.20).toFixed(3); // alpha scales with the gap
			bg = ` style="background:rgba(${gap > 0 ? '31,170,89' : '210,59,59'},${a})"`;
		}
		return `<td class="sp-trend-cell"${bg}>${Number(val).toFixed(1)}${delta}</td>`;
	};

	const arrow = (key) => (sk === key) ? (dir < 0 ? ' ▾' : ' ▴') : '';
	const th = (key, label, extra) => `<th class="sp-trend-th${extra || ''}" data-sort="${esc(key)}" role="button">${esc(label)}${arrow(key)}</th>`;

	// Ranked bar chart for the SELECTED metric (Overall or any category) — sorted best→worst
	// (independent of table sort), bars green/red vs that metric's company average, axis zoomed to
	// that metric's data so small differences pop (labelled, so it's honest).
	if (_spTrends.metric !== 'stars' && cats.indexOf(_spTrends.metric) === -1) _spTrends.metric = 'stars';
	const metric = _spTrends.metric;
	const mVal = (row) => metric === 'stars' ? row.stars : (row.scores ? row.scores[metric] : null);
	const coVal = metric === 'stars' ? company.stars : (company.scores ? company.scores[metric] : null);
	const metricLabel = metric === 'stars' ? 'Overall rating' : metric;

	let h = '';
	const metricVals = stores.map(mVal).filter(v => v != null);
	if (coVal != null && metricVals.length) {
		const lo   = Math.min.apply(null, metricVals.concat([coVal]));
		const dmin = Math.max(0, Math.floor((lo - 0.3) * 10) / 10);
		const span = (5 - dmin) || 1;
		const pct  = (v) => Math.max(0, Math.min(100, (v - dmin) / span * 100));
		const avgPct = pct(coVal).toFixed(1);
		const ranked = stores.filter(s => mVal(s) != null).slice().sort((a, b) => mVal(b) - mVal(a));
		let rows = '';
		ranked.forEach(s => {
			const v = mVal(s);
			const up = v >= coVal;
			rows += `<div class="sp-trend-bar-row${drillCls(s)}"${drillAttr(s)}>` +
				`<div class="sp-trend-bar-name" title="${esc(s.store)}">${esc(s.store)}</div>` +
				'<div class="sp-trend-bar-track">' +
					`<div class="sp-trend-bar-fill ${up ? 'sp-trend-bar-up' : 'sp-trend-bar-down'}" style="width:${pct(v).toFixed(1)}%"></div>` +
					`<div class="sp-trend-avgline" style="left:${avgPct}%"></div>` +
				'</div>' +
				`<div class="sp-trend-bar-val">${Number(v).toFixed(1)}</div>` +
			'</div>';
		});
		let opts = `<option value="stars"${metric === 'stars' ? ' selected' : ''}>Overall</option>`;
		cats.forEach(c => { opts += `<option value="${esc(c)}"${metric === c ? ' selected' : ''}>${esc(c)}</option>`; });
		h += '<div class="sp-card sp-trend-chart">' +
			'<div class="sp-trend-chart-head">' +
				`<strong>${esc(metricLabel)} by ${groupName}</strong> ` +
				`<select id="sp-trends-metric" class="sp-select sp-trend-metric-sel">${opts}</select>` +
				`<span class="unique sp-help-text"> · dashed line = company average (${Number(coVal).toFixed(1)}) · scale ${dmin.toFixed(1)}–5</span>` +
			'</div>' +
			`<div class="sp-trend-bars">${rows}</div>` +
		'</div>';
	}

	h += '<div class="sp-card" style="padding:0;overflow-x:auto">';
	h += '<table class="sp-table sp-trend-table"><thead><tr>';
	h += th('store', isSup ? 'Supervisor' : 'Store', ' sp-trend-th-store');
	h += th('count', 'Reviews');
	h += th('stars', 'Overall');
	cats.forEach(c => { h += th(c, c); });
	h += '</tr></thead><tbody>';

	// Company baseline row (the comparison line).
	h += '<tr class="sp-trend-company">';
	h += '<td class="sp-trend-th-store"><strong>Company average</strong></td>';
	h += `<td>${esc(spComma(company.count || 0))}</td>`;
	h += `<td>${company.stars != null ? Number(company.stars).toFixed(1) : '—'}</td>`;
	cats.forEach(c => { const v = company.scores ? company.scores[c] : null; h += `<td>${v != null ? Number(v).toFixed(1) : '—'}</td>`; });
	h += '</tr>';

	stores.forEach(s => {
		h += `<tr data-key="${esc(s.store)}"${drillAttr(s)} class="${drillCls(s).trim()}">`;
		h += `<td class="sp-trend-th-store">${esc(s.store)}</td>`;
		h += `<td>${esc(spComma(s.count || 0))}</td>`;
		h += cell(s.stars, company.stars);
		cats.forEach(c => { h += cell(s.scores ? s.scores[c] : null, company.scores ? company.scores[c] : null); });
		h += '</tr>';
	});

	h += '</tbody></table></div>';
	h += `<p class="sp-help-text" style="margin-top:10px">Each cell shows the ${groupName}’s average rating (1–5); the small number is the gap vs the company average. Click a column to sort.${isSup ? ' Click a supervisor to drill into their stores.' : ''}</p>`;

	// Over-time line chart goes third — after the ranked bar chart and the scorecard table.
	h += spTrendLineChartHtml();

	wrap.innerHTML = h;

	// Chart metric ↔ table sort are kept in lock-step so both always show the same metric.
	const mSel = $('#sp-trends-metric', wrap);
	if (mSel) mSel.addEventListener('change', () => {
		const before = spTrendCaptureRects();
		_spTrends.metric  = mSel.value;
		_spTrends.sortKey = mSel.value;  // table follows the chart
		_spTrends.sortDir = -1;          // best first
		renderReviewTrends();
		spTrendAnimate(before);
	});

	$$('.sp-trend-th', wrap).forEach(thEl => {
		thEl.addEventListener('click', () => {
			const before = spTrendCaptureRects();        // FLIP: measure current row positions
			const k = thEl.dataset.sort;
			if (_spTrends.sortKey === k) { _spTrends.sortDir *= -1; }
			else { _spTrends.sortKey = k; _spTrends.sortDir = (k === 'store') ? 1 : -1; }
			// Clicking a metric column (Overall / a category) drives the chart too; Store/Reviews don't.
			if (k === 'stars' || cats.indexOf(k) !== -1) _spTrends.metric = k;
			renderReviewTrends();                         // re-render in new order
			spTrendAnimate(before);                       // slide each row from old → new spot
		});
	});

	// Supervisor mode: click a supervisor (row or bar) to drill into just their stores.
	$$('.sp-trend-drill', wrap).forEach(el => {
		el.addEventListener('click', () => {
			const sup = el.dataset.sup || '';
			if (!sup) return;
			_spTrends.group = 'store';
			_spTrends.supervisor = sup;
			const gSel = $('#sp-trends-group'), sSel = $('#sp-trends-supervisor');
			if (gSel) gSel.value = 'store';
			if (sSel) { sSel.disabled = false; sSel.value = sup; }
			loadReviewTrends();
		});
	});

	// Line-chart legend: click a store (or "Company avg") to toggle its line on/off.
	$$('.sp-tl-legend-item', wrap).forEach(el => {
		el.addEventListener('click', () => {
			const k = el.dataset.skey;
			_spTrends.lineHidden[k] = !_spTrends.lineHidden[k];
			renderReviewTrends();
		});
	});
}

// Build the over-time line chart (SVG) for the selected metric from the fetched series. Returns '' when
// there isn't enough data (need ≥2 monthly buckets).
function spTrendLineChartHtml() {
	const S = _spTrends.series;
	const metric = _spTrends.metric;
	if (!S || !S.metrics || !S.metrics[metric]) return '';
	const md = S.metrics[metric];
	const buckets = S.buckets || [];
	const labels = S.bucket_labels || buckets;
	const series = (md.series || []);
	if (buckets.length < 2 || !series.length) return '';

	const hidden = _spTrends.lineHidden || {};
	const PALETTE = ['#2f6fed', '#1faa59', '#d23b3b', '#e08a1e', '#7d4fd6', '#1198a6', '#c43d8a', '#5a7d2a', '#444444', '#a05a2c', '#0f7b8a', '#b0357f'];

	// Y domain zoomed to the visible data (labelled, like the bar chart).
	let lo = Infinity, hi = -Infinity;
	const consider = (v) => { if (v != null) { if (v < lo) lo = v; if (v > hi) hi = v; } };
	series.forEach(s => { if (!hidden[s.key]) s.values.forEach(consider); });
	if (!hidden['__company__']) (md.company || []).forEach(consider);
	if (lo === Infinity) { lo = 0; hi = 5; }
	let dmin = Math.max(0, Math.floor((lo - 0.2) * 10) / 10);
	let dmax = Math.min(5, Math.ceil((hi + 0.2) * 10) / 10);
	if (dmax <= dmin) dmax = Math.min(5, dmin + 1);

	const W = 720, H = 300, padL = 38, padR = 12, padT = 12, padB = 40;
	const plotW = W - padL - padR, plotH = H - padT - padB, n = buckets.length;
	const xAt = (i) => padL + (n === 1 ? plotW / 2 : (i / (n - 1)) * plotW);
	const yAt = (v) => padT + plotH - ((v - dmin) / (dmax - dmin)) * plotH;

	let svg = `<svg class="sp-trendline-svg" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Stores over time">`;
	const ticks = 4;
	for (let t = 0; t <= ticks; t++) {
		const val = dmin + (dmax - dmin) * t / ticks, y = yAt(val);
		svg += `<line x1="${padL}" y1="${y.toFixed(1)}" x2="${W - padR}" y2="${y.toFixed(1)}" class="sp-tl-grid"/>`;
		svg += `<text x="${padL - 6}" y="${(y + 3).toFixed(1)}" text-anchor="end" class="sp-tl-axis">${val.toFixed(1)}</text>`;
	}
	const xevery = Math.ceil(n / 8);
	buckets.forEach((b, i) => {
		if (i % xevery !== 0 && i !== n - 1) return;
		svg += `<text x="${xAt(i).toFixed(1)}" y="${H - padB + 16}" text-anchor="middle" class="sp-tl-axis">${esc(labels[i] || '')}</text>`;
	});
	if (!hidden['__company__']) {
		const p = spTrendLinePath(md.company || [], xAt, yAt);
		if (p) svg += `<path d="${p}" fill="none" class="sp-tl-company sp-tl-line" pathLength="100"/>`;
	}
	series.forEach((s, i) => {
		if (hidden[s.key]) return;
		const color = PALETTE[i % PALETTE.length];
		const p = spTrendLinePath(s.values, xAt, yAt);
		if (p) svg += `<path d="${p}" fill="none" stroke="${color}" stroke-width="2" class="sp-tl-line" pathLength="100"/>`;
		s.values.forEach((v, xi) => { if (v != null) svg += `<circle class="sp-tl-dot" cx="${xAt(xi).toFixed(1)}" cy="${yAt(v).toFixed(1)}" r="2.5" fill="${color}"/>`; });
	});
	svg += '</svg>';

	let legend = '<div class="sp-tl-legend">';
	series.forEach((s, i) => {
		const color = PALETTE[i % PALETTE.length];
		legend += `<button type="button" class="unique sp-tl-legend-item${hidden[s.key] ? ' sp-tl-legend-off' : ''}" data-skey="${esc(s.key)}"><span class="sp-tl-swatch" style="background:${color}"></span>${esc(s.key)}</button>`;
	});
	legend += `<button type="button" class="unique sp-tl-legend-item${hidden['__company__'] ? ' sp-tl-legend-off' : ''}" data-skey="__company__"><span class="sp-tl-swatch sp-tl-swatch-co"></span>Company avg</button>`;
	legend += '</div>';

	const groupName = (S.group === 'supervisor') ? 'supervisors' : 'stores';
	return '<div class="sp-card sp-trendline-card">'
		+ `<div class="sp-trend-chart-head"><strong>${esc(metricLabel())} over time</strong> <span class="unique sp-help-text">· monthly average by ${groupName} · scale ${dmin.toFixed(1)}–${dmax.toFixed(1)}</span></div>`
		+ svg + legend + '</div>';

	function metricLabel() { return metric === 'stars' ? 'Overall rating' : metric; }
}

// SVG path for a line, smoothed (Catmull-Rom → bezier so it eases through each dot) and broken at null
// gaps (months with no data).
function spTrendLinePath(values, xAt, yAt) {
	const segs = [];
	let cur = [];
	values.forEach((v, i) => {
		if (v == null) { if (cur.length) { segs.push(cur); cur = []; } return; }
		cur.push({ x: +xAt(i), y: +yAt(v) });
	});
	if (cur.length) segs.push(cur);
	return segs.map(spSmoothPath).join(' ').trim();
}

// Smooth path through pixel points using a Catmull-Rom spline expressed as cubic beziers.
function spSmoothPath(pts) {
	if (!pts.length) return '';
	const f = (n) => n.toFixed(1);
	if (pts.length === 1) return `M${f(pts[0].x)} ${f(pts[0].y)}`;
	let d = `M${f(pts[0].x)} ${f(pts[0].y)}`;
	for (let i = 0; i < pts.length - 1; i++) {
		const p0 = pts[i - 1] || pts[i], p1 = pts[i], p2 = pts[i + 1], p3 = pts[i + 2] || p2;
		const c1x = p1.x + (p2.x - p0.x) / 6, c1y = p1.y + (p2.y - p0.y) / 6;
		const c2x = p2.x - (p3.x - p1.x) / 6, c2y = p2.y - (p3.y - p1.y) / 6;
		d += ` C${f(c1x)} ${f(c1y)} ${f(c2x)} ${f(c2y)} ${f(p2.x)} ${f(p2.y)}`;
	}
	return d;
}

// FLIP helpers — capture each keyed row's top before re-render, then animate from the delta to zero.
function spTrendCaptureRects() {
	const wrap = $('#sp-trends-content'), map = {};
	if (wrap) $$('tr[data-key]', wrap).forEach(tr => { map[tr.dataset.key] = tr.getBoundingClientRect().top; });
	return map;
}
function spTrendAnimate(before) {
	const wrap = $('#sp-trends-content');
	if (!wrap || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
	const rows = $$('tr[data-key]', wrap);
	rows.forEach(tr => {
		const old = before[tr.dataset.key];
		if (old == null) return;
		const dy = old - tr.getBoundingClientRect().top;
		if (!dy) return;
		tr.style.transition = 'none';
		tr.style.transform = `translateY(${dy}px)`;
	});
	void wrap.offsetHeight; // force reflow so the start transforms take effect
	rows.forEach(tr => {
		tr.style.transition = 'transform .35s ease';
		tr.style.transform = '';
	});
}

let spReportSchedule = {};

// "Report Frequency" card (top of the Reports admin) — how often each report is required + the first
// due date. This is the SINGLE SOURCE OF TRUTH for the report cadence (it relabels the templates too).
// Opting in to due-date reminders is separate: Settings → Notifications.
function spReportScheduleCardHtml() {
	const s = spReportSchedule || {};
	const freqOpts = (cur) => {
		const opts = [['weekly', 'Weekly'], ['biweekly', 'Bi-Weekly'], ['monthly', 'Monthly']];
		let h = cur ? '' : '<option value="">— Select —</option>';
		h += opts.map(([v, l]) => `<option value="${v}"${(cur || '') === v ? ' selected' : ''}>${l}</option>`).join('');
		return h;
	};
	const row = (key, label, freq, anchor) =>
		'<div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px">' +
			`<div class="sp-form-group" style="margin:0"><label>${label} — frequency</label><select class="sp-select sp-rsched-freq" data-key="${key}">${freqOpts(freq)}</select></div>` +
			`<div class="sp-form-group" style="margin:0"><label>First due date</label><input type="date" class="sp-input sp-rsched-anchor" data-key="${key}" value="${esc(anchor || '')}"></div>` +
		'</div>';
	return '<div class="sp-card" id="sp-report-schedule" style="margin-bottom:20px">' +
		'<div class="sp-card-body">' +
			'<h3 style="margin:0 0 6px">Report Frequency</h3>' +
			'<p class="sp-help-text" style="margin:0 0 12px">How often each report is required and its first due date — this sets the cadence shown across the app. To get a “report due today” alert on each due date, enable it in <strong>Settings → Notifications</strong>.</p>' +
			row('gm', 'GM report', s.gm_frequency, s.gm_anchor) +
			row('supervisor', 'Supervisor report', s.supervisor_frequency, s.supervisor_anchor) +
		'</div>' +
	'</div>';
}

async function loadAdminTemplates() {
	const wrap = $('#sp-admin-templates-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_templates', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading templates.</p>'; return; }
		spTemplateRoles = res.data.roles || [];
		spReportSchedule = res.data.schedule || {};
		renderAdminTemplates(wrap, res.data.templates);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading templates.</p>';
	}
}

function renderAdminTemplates(wrap, templates) {
	// Reports are fully client-managed: add a new one, rename, change frequency/first-due, add/remove
	// fields, or delete. Each report auto-generates "Submit …"/"View all …" permissions (Roles/Individual).
	let html = '<div class="sp-admin-toolbar" style="margin-bottom:16px;"><button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-report-btn">+ Add Report</button></div>';

	if (!templates || templates.length === 0) {
		html += '<div class="sp-empty-state"><p>No report templates yet.</p></div>';
	} else {
		templates.forEach(t => {
			const statusClass = t.is_active == 1 ? 'sp-status-submitted' : 'sp-status-draft';
			const statusLabel = t.is_active == 1 ? 'active' : 'inactive';
			const fieldCount = (t.fields || []).length;

			html += `<div class="sp-card sp-template-card" data-template-id="${t.id}">`;
			html += '<div class="sp-header-grid sp-template-card-header">';
			html += `<div><strong>${esc(t.name)}</strong> <span class="unique sp-status-badge ${statusClass}">${statusLabel}</span></div>`;
			html += `<div style="display:grid;grid-auto-flow:column;gap:8px;">`;
			html += iconBtn('edit', 'sp-edit-template-btn', `data-id="${t.id}"`);
			html += iconBtn('delete', 'sp-delete-template-btn', `data-id="${t.id}" title="Delete report"`);
			html += '</div></div>';
			const freqLabel = ({ daily: 'Daily', weekly: 'Weekly', biweekly: 'Bi-Weekly', monthly: 'Monthly', quarterly: 'Quarterly' })[t.frequency] || t.frequency;
			const dueTxt    = t.report_anchor ? ' &middot; first due ' + esc(formatDate(t.report_anchor)) : '';
			const remindTxt = (t.send_reminders == 1) ? ' &middot; 🔔 reminders' : '';
			const emailTxt  = t.email_on_submit ? ' &middot; ✉ ' + esc(t.email_on_submit) : '';
			html += `<div class="sp-template-meta">${esc(freqLabel)}${dueTxt}${remindTxt}${emailTxt} &middot; ${fieldCount} field${fieldCount !== 1 ? 's' : ''}</div>`;

			html += '<div class="sp-template-fields">';
			if (t.fields && t.fields.length > 0) {
				html += '<div class="sp-field-list">';
				t.fields.forEach((f, i) => {
					const archived = f.display_order >= 999 ? ' sp-field-archived' : '';
					html += `<div class="sp-field-item${archived}" data-field-id="${f.id}" data-index="${i}" draggable="true">`;
					html += `<span class="sp-field-drag">&#9776;</span>`;
					html += `<span class="sp-field-label">${esc(f.label)}</span>`;
					html += `<span class="sp-field-type">${esc(f.field_type)}</span>`;
					html += iconBtn('edit', 'sp-edit-field-btn', `data-field-id="${f.id}" data-template-id="${t.id}"`);
					html += iconBtn('delete', 'sp-delete-field-btn', `data-field-id="${f.id}" title="Delete field"`);
					html += '</div>';
				});
				html += '</div>';
			}
			html += `<button type="button" class="unique sp-btn sp-btn-secondary sp-add-field-btn" data-template-id="${t.id}" style="margin-top:10px;">+ Add Field</button>`;
			html += '</div></div>';
		});
	}

	html += '<div id="sp-template-form-wrap" hidden></div>';
	html += '<div id="sp-field-form-wrap" hidden></div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-add-report-btn', wrap)?.addEventListener('click', () => showTemplateForm(null));

	$$('.sp-delete-template-btn', wrap).forEach(btn => {
		btn.addEventListener('click', async () => {
			const t = templates.find(x => String(x.id) === btn.dataset.id);
			if (!confirm(`Delete the "${t?.name || ''}" report? A report with existing submissions is deactivated (data kept) instead.`)) return;
			try {
				const res = await spAjax('site_pulse_admin_delete_template', { id: btn.dataset.id });
				if (res.success) { if (res.data?.message) spFlash(res.data.message); loadAdminTemplates(); }
				else alert(res.data?.message || 'Error deleting report.');
			} catch (e) { alert('Error deleting report.'); }
		});
	});

	$$('.sp-edit-template-btn', wrap).forEach(btn => {
		btn.addEventListener('click', () => {
			const tid = btn.dataset.id;
			const tpl = templates.find(t => String(t.id) === tid);
			showTemplateForm(tpl);
		});
	});

	$$('.sp-add-field-btn', wrap).forEach(btn => {
		btn.addEventListener('click', () => showFieldForm(null, btn.dataset.templateId));
	});

	$$('.sp-edit-field-btn', wrap).forEach(btn => {
		btn.addEventListener('click', () => {
			const tid = btn.dataset.templateId;
			const fid = btn.dataset.fieldId;
			const tpl = templates.find(t => String(t.id) === tid);
			const field = tpl ? tpl.fields.find(f => String(f.id) === fid) : null;
			showFieldForm(field, tid);
		});
	});

	$$('.sp-delete-field-btn', wrap).forEach(btn => {
		btn.addEventListener('click', async () => {
			if (!confirm('Remove this field? If it has existing answers it will be archived.')) return;
			try {
				const res = await spAjax('site_pulse_admin_delete_field', { id: btn.dataset.fieldId });
				if (res.success) loadAdminTemplates();
				else alert(res.data?.message || 'Error.');
			} catch (err) { alert('Error removing field.'); }
		});
	});

	initFieldDragDrop(wrap);
}

function initFieldDragDrop(wrap) {
	let dragItem = null;

	$$('.sp-field-item[draggable]', wrap).forEach(item => {
		item.addEventListener('dragstart', (e) => {
			dragItem = item;
			item.classList.add('sp-dragging');
			e.dataTransfer.effectAllowed = 'move';
		});

		item.addEventListener('dragend', () => {
			item.classList.remove('sp-dragging');
			dragItem = null;
			$$('.sp-field-item', wrap).forEach(el => el.classList.remove('sp-drag-over'));
		});

		item.addEventListener('dragover', (e) => {
			e.preventDefault();
			e.dataTransfer.dropEffect = 'move';
			if (dragItem && item !== dragItem) {
				item.classList.add('sp-drag-over');
			}
		});

		item.addEventListener('dragleave', () => {
			item.classList.remove('sp-drag-over');
		});

		item.addEventListener('drop', async (e) => {
			e.preventDefault();
			item.classList.remove('sp-drag-over');
			if (!dragItem || item === dragItem) return;

			const list = item.closest('.sp-field-list');
			if (!list) return;

			const items = [...list.children];
			const fromIndex = items.indexOf(dragItem);
			const toIndex = items.indexOf(item);

			if (fromIndex < toIndex) {
				list.insertBefore(dragItem, item.nextSibling);
			} else {
				list.insertBefore(dragItem, item);
			}

			const newOrder = [...list.children].map(el => el.dataset.fieldId);
			try {
				await spAjax('site_pulse_admin_reorder_fields', { order: newOrder });
			} catch (err) {
				loadAdminTemplates();
			}
		});
	});
}

function showTemplateForm(tpl) {
	const wrap = spFormModal();

	const isEdit = !!tpl;
	const title = isEdit ? 'Edit Report' : 'Add Report';

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += `<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${title}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-tpl-form-cancel">Cancel</button></div>';
	html += '<form id="sp-template-form">';
	if (isEdit) html += `<input type="hidden" name="id" value="${tpl.id}">`;

	html += '<div class="sp-form-group"><label>Report Name</label>';
	html += `<input type="text" name="name" class="sp-input" value="${esc(tpl?.name || '')}" required></div>`;

	html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>Frequency</label>';
	html += `<select name="frequency" class="sp-select">`;
	[['daily','Daily'],['weekly','Weekly'],['biweekly','Bi-Weekly'],['monthly','Monthly'],['quarterly','Quarterly']].forEach(([v,l]) => {
		html += `<option value="${v}"${tpl?.frequency === v ? ' selected' : ''}>${l}</option>`;
	});
	html += '</select></div>';

	html += '<div class="sp-form-group"><label>Required Role</label>';
	html += `<select name="required_role_slug" class="sp-select">`;
	(spTemplateRoles.length ? spTemplateRoles : [{slug:'manager',label:'GM'},{slug:'supervisor',label:'Supervisor'},{slug:'admin',label:'Administrator'},{slug:'owner',label:'Owner'}]).forEach(r => {
		html += `<option value="${esc(r.slug)}"${tpl?.required_role_slug === r.slug ? ' selected' : ''}>${esc(r.label)}</option>`;
	});
	html += '</select></div>';

	if (isEdit) {
		html += '<div class="sp-form-group"><label>Status</label>';
		html += `<select name="is_active" class="sp-select">`;
		html += `<option value="1"${tpl.is_active == 1 ? ' selected' : ''}>Active</option>`;
		html += `<option value="0"${tpl.is_active == 0 ? ' selected' : ''}>Inactive</option>`;
		html += '</select></div>';
	}
	html += '</div>';

	html += '<div class="sp-form-group"><label>Description</label>';
	html += `<input type="text" name="description" class="sp-input" value="${esc(tpl?.description || '')}"></div>`;

	// Self-contained schedule + delivery, per report.
	html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>First due date</label>';
	html += `<input type="date" name="report_anchor" class="sp-input" value="${esc(tpl?.report_anchor || '')}"></div>`;
	html += '<div class="sp-form-group"><label>Email each submission to <span class="sp-help-text" style="font-weight:400;">(optional)</span></label>';
	html += `<input type="text" name="email_on_submit" class="sp-input" value="${esc(tpl?.email_on_submit || '')}" placeholder="owner@company.com"></div>`;
	html += '</div>';
	html += `<div class="sp-form-group"><label class="sp-reminder-toggle"><input type="checkbox" name="send_reminders" value="1"${tpl?.send_reminders == 1 ? ' checked' : ''}> Send &ldquo;report due&rdquo; reminders on this schedule</label>`;
	html += '<div class="sp-help-text">When on, this report shows up in Settings &rarr; Notifications so you can pick which roles get the due-date alert.</div></div>';

	html += '<div class="sp-report-form-actions">';
	html += `<button type="submit" class="unique sp-btn sp-btn-primary">${isEdit ? 'Save Changes' : 'Create Report'}</button>`;
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-tpl-form-cancel">Cancel</button>';
	html += '</div></form></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-tpl-form-cancel', wrap).forEach(btn => {
		btn.addEventListener('click', () => closeFormModal());
	});

	$('#sp-template-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;
		try {
			const res = await spAjax('site_pulse_admin_save_template', data);
			if (res.success) {
				closeFormModal();
				loadAdminTemplates();
			} else alert(res.data?.message || 'Error.');
		} catch (err) { alert('Error saving template.'); }
	});
}

function showFieldForm(field, templateId) {
	const wrap = spFormModal();

	const isEdit = !!field;
	const title = isEdit ? 'Edit Field' : 'Add Field';

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += `<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${title}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-field-form-cancel">Cancel</button></div>';
	html += '<form id="sp-field-form">';
	if (isEdit) html += `<input type="hidden" name="id" value="${field.id}">`;
	html += `<input type="hidden" name="template_id" value="${templateId}">`;

	html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>Label</label>';
	html += `<input type="text" name="label" class="sp-input" value="${esc(field?.label || '')}" required></div>`;
	html += '<div class="sp-form-group"><label>Field Key</label>';
	html += `<input type="text" name="field_key" class="sp-input" value="${esc(field?.field_key || '')}" placeholder="Auto-generated from label"></div>`;
	html += '</div>';

	html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">';
	const isMath = field?.field_type === 'math';
	html += '<div class="sp-form-group"><label>Type</label>';
	html += `<select name="field_type" class="sp-select">`;
	['textarea','text','number','select','rating','math'].forEach(t => {
		html += `<option value="${t}"${field?.field_type === t ? ' selected' : ''}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>`;
	});
	html += '</select></div>';

	// Required (normal fields) ↔ Equation (Math fields) share this slot — toggled by the Type dropdown.
	html += `<div class="sp-form-group" id="sp-field-required-group"${isMath ? ' hidden' : ''}><label>Required</label>`;
	html += `<select name="is_required" class="sp-select">`;
	html += `<option value="0"${!field || field.is_required == 0 ? ' selected' : ''}>No</option>`;
	html += `<option value="1"${field?.is_required == 1 ? ' selected' : ''}>Yes</option>`;
	html += '</select></div>';

	html += `<div class="sp-form-group" id="sp-field-equation-group"${isMath ? '' : ' hidden'}><label>Equation</label>`;
	html += `<input type="text" name="equation_input" class="sp-input" value="${esc(isMath ? (field?.options || '') : '')}" placeholder="e.g. lunch + dinner">`;
	html += `<div class="sp-help-text">Use other fields' keys with + - * / and ( ). e.g. <code>lunch + dinner</code></div></div>`;

	html += '<div class="sp-form-group"><label>Section</label>';
	html += `<input type="text" name="section" class="sp-input" value="${esc(field?.section || '')}"></div>`;
	html += '</div>';

	html += '<div class="sp-form-group"><label>Placeholder Text</label>';
	html += `<input type="text" name="placeholder" class="sp-input" value="${esc(field?.placeholder || '')}"></div>`;

	html += '<div class="sp-form-group"><label>Help Text</label>';
	html += `<input type="text" name="help_text" class="sp-input" value="${esc(field?.help_text || '')}"></div>`;

	html += '<div class="sp-report-form-actions">';
	html += `<button type="submit" class="unique sp-btn sp-btn-primary">${isEdit ? 'Save Changes' : 'Add Field'}</button>`;
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-field-form-cancel">Cancel</button>';
	html += '</div></form></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-field-form-cancel', wrap).forEach(btn => {
		btn.addEventListener('click', () => closeFormModal());
	});

	// Type dropdown swaps the Required slot for the Equation slot (Math fields have no "required").
	const typeSel = $('[name="field_type"]', wrap);
	const syncMathUI = () => {
		const m = typeSel && typeSel.value === 'math';
		const reqG = $('#sp-field-required-group', wrap);
		const eqG  = $('#sp-field-equation-group', wrap);
		if (reqG) reqG.hidden = !!m;
		if (eqG)  eqG.hidden  = !m;
	};
	typeSel?.addEventListener('change', syncMathUI);
	syncMathUI();

	$('#sp-field-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;
		// A Math field stores its equation in `options` and is never "required".
		if (data.field_type === 'math') {
			data.options = data.equation_input || '';
			data.is_required = '0';
		}
		delete data.equation_input;
		try {
			const res = await spAjax('site_pulse_admin_save_field', data);
			if (res.success) {
				closeFormModal();
				loadAdminTemplates();
			} else alert(res.data?.message || 'Error.');
		} catch (err) { alert('Error saving field.'); }
	});
}


/*--------------------------------------------------------------
# Helpers
--------------------------------------------------------------*/

function spGetNonce() {
	if (D.nonce) return D.nonce;
	const field = document.querySelector('#site_pulse_nonce_field, [name="site_pulse_nonce_field"]');
	return field ? field.value : '';
}

async function spAjax(action, data = {}) {
	const body = new FormData();
	body.append('action', action);
	body.append('nonce', spGetNonce());

	for (const [key, val] of Object.entries(data)) {
		if (typeof val === 'object' && val !== null) {
			for (const [k, v] of Object.entries(val)) {
				body.append(`${key}[${k}]`, v);
			}
		} else {
			body.append(key, val);
		}
	}

	const ajaxUrl = D.ajaxUrl || '/wp-admin/admin-ajax.php';
	const res = await fetch(ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		body: body,
	});

	if (!res.ok) {
		const text = await res.text();
		console.error('Site Pulse AJAX error:', res.status, text.substring(0, 200));
		throw new Error('AJAX request failed: ' + res.status);
	}

	const text = await res.text();
	try {
		return JSON.parse(text);
	} catch (e) {
		console.error('Site Pulse: Invalid JSON response:', text.substring(0, 200));
		throw new Error('Invalid response from server');
	}
}

/*--------------------------------------------------------------
# Home Base — staff side (customer roster, push composer, scheduling inbox)
--------------------------------------------------------------*/

// --- Customers roster ---
let _spCcCustWired = false;
let _spCcCustT = null;

function initCcCustomers() {
	if (_spCcCustWired) return;
	$('#sp-cc-cust-search')?.addEventListener('input', () => { clearTimeout(_spCcCustT); _spCcCustT = setTimeout(loadCcCustomers, 300); });
	$('#sp-cc-cust-clear')?.addEventListener('click', () => { const s = $('#sp-cc-cust-search'); if (s) s.value = ''; loadCcCustomers(); });
	_spCcCustWired = true;
}

async function loadCcCustomers() {
	const list = $('#sp-cc-customers-list');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_cc_customers', { search: $('#sp-cc-cust-search')?.value.trim() || '' });
		if (!res.success) { list.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load customers.')}</div>`; return; }
		const items = res.data.items || [];
		const cnt = $('#sp-cc-cust-count');
		if (cnt) cnt.textContent = `${items.length} ${items.length === 1 ? 'customer' : 'customers'}`;
		if (!items.length) { list.innerHTML = '<div class="sp-empty">No customers yet.</div>'; return; }
		list.innerHTML = items.map(ccCustCard).join('');
		markUniqueSpans(list);
	} catch (e) {
		list.innerHTML = '<div class="sp-empty">Error loading customers.</div>';
	}
}

function ccCustCard(c) {
	const sub = c.subscribed
		? '<span class="unique sp-status-badge sp-status-review">Subscribed</span>'
		: '<span class="unique sp-status-badge sp-status-draft">No app</span>';
	const meta = [c.location, c.equipment ? `${c.equipment} unit${c.equipment === 1 ? '' : 's'}` : ''].filter(Boolean).join(' &middot; ');
	return `<div class="sp-card sp-cc-cust-card">
		<div class="sp-card-body sp-cc-cust-body">
			<div class="sp-cc-cust-main">
				<span class="unique sp-cc-cust-name">${esc(c.name)}</span>
				${c.contact ? `<span class="unique sp-cc-cust-contact">${esc(c.contact)}</span>` : ''}
				${meta ? `<span class="unique sp-cc-cust-meta">${meta}</span>` : ''}
			</div>
			${sub}
		</div>
	</div>`;
}

// --- Push composer (segment broadcast) ---
let _spCcSendWired = false;

function initCcSend() {
	if (_spCcSendWired) return;
	$('#sp-cc-send-btn')?.addEventListener('click', ccSend);
	_spCcSendWired = true;
}

async function loadCcSegments() {
	const sel = $('#sp-cc-audience');
	const notice = $('#sp-cc-send-notice');
	if (!sel) return;
	try {
		const res = await spAjax('site_pulse_cc_segments', {});
		if (!res.success) { sel.innerHTML = `<option value="">${esc(res.data?.message || 'Unavailable')}</option>`; return; }
		const segs = res.data.segments || [];
		sel.innerHTML = segs.map(s => `<option value="${esc(s.key)}">${esc(s.label)} (${s.count})</option>`).join('');
		const ready = !!res.data.push_ready;
		if (notice) notice.innerHTML = ready ? '' : '<div class="sp-empty">Push is not configured on this site yet.</div>';
		const btn = $('#sp-cc-send-btn'); if (btn) btn.disabled = !ready;
	} catch (e) {
		sel.innerHTML = '<option value="">Error</option>';
	}
}

async function ccSend() {
	const btn = $('#sp-cc-send-btn');
	const result = $('#sp-cc-send-result');
	const setMsg = (txt, cls) => { if (result) { result.textContent = txt; result.className = 'sp-cc-send-result' + (cls ? ' ' + cls : ''); } };
	const segment = $('#sp-cc-audience')?.value || '';
	const title = $('#sp-cc-title')?.value.trim() || '';
	if (!title)   { setMsg('Add a title.', 'is-error'); return; }
	if (!segment) { setMsg('Pick an audience.', 'is-error'); return; }
	if (btn) btn.disabled = true;
	setMsg('Sending…', '');
	try {
		const res = await spAjax('site_pulse_cc_send', {
			target: 'segment',
			segment: segment,
			title: title,
			body: $('#sp-cc-body')?.value.trim() || '',
			url: $('#sp-cc-url')?.value.trim() || '',
		});
		if (!res.success) {
			setMsg(res.data?.message || 'Could not send.', 'is-error');
		} else {
			const d = res.data || {};
			const got = d.delivered ?? 0, to = d.recipients ?? got;
			setMsg(`Sent to ${got} of ${to} device${to === 1 ? '' : 's'}.`, 'is-ok');
			['sp-cc-title', 'sp-cc-body', 'sp-cc-url'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
		}
	} catch (e) {
		setMsg('Error sending.', 'is-error');
	}
	if (btn) btn.disabled = false;
}

// --- Scheduling inbox ---
let _spCcSchedWired = false;
let _spCcSchedCanManage = false;

function initCcSchedule() {
	if (_spCcSchedWired) return;
	$('#sp-cc-schedule-list')?.addEventListener('click', onCcSchedClick);
	_spCcSchedWired = true;
}

async function loadCcSchedule() {
	const list = $('#sp-cc-schedule-list');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_cc_schedule', {});
		if (!res.success) { list.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load requests.')}</div>`; return; }
		const items = res.data.items || [];
		_spCcSchedCanManage = !!res.data.can_manage;
		if (!items.length) { list.innerHTML = '<div class="sp-empty">No scheduling requests.</div>'; return; }
		list.innerHTML = items.map(ccSchedCard).join('');
		markUniqueSpans(list);
	} catch (e) {
		list.innerHTML = '<div class="sp-empty">Error loading requests.</div>';
	}
}

function ccSchedCard(r) {
	const when = [r.preferred_date, r.preferred_window].filter(Boolean).join(' ') || 'No preference';
	const badge = r.status === 'new' ? 'sp-status-pending' : (r.status === 'dismissed' ? 'sp-status-draft' : 'sp-status-review');
	const actions = _spCcSchedCanManage
		? `<div class="sp-cc-sched-actions">
			<button type="button" class="unique sp-btn sp-btn-ghost" data-sched="scheduled" data-id="${r.id}">Scheduled</button>
			<button type="button" class="unique sp-btn sp-btn-ghost" data-sched="done" data-id="${r.id}">Done</button>
			<button type="button" class="unique sp-btn sp-btn-ghost" data-sched="dismissed" data-id="${r.id}">Dismiss</button>
		</div>` : '';
	return `<div class="sp-card sp-cc-sched-card" data-id="${r.id}">
		<div class="sp-card-body">
			<div class="sp-cc-sched-head">
				<span class="unique sp-cc-sched-name">${esc(r.customer)}</span>
				<span class="unique sp-status-badge ${badge}">${esc(r.status)}</span>
			</div>
			${r.contact ? `<div class="unique sp-cc-sched-contact">${esc(r.contact)}</div>` : ''}
			<div class="sp-cc-sched-meta">${esc(r.type || 'Service')} &middot; ${esc(when)}${r.urgency && r.urgency !== 'normal' ? ' &middot; ' + esc(r.urgency) : ''}</div>
			${r.message ? `<div class="sp-cc-sched-msg">${esc(r.message)}</div>` : ''}
			${actions}
		</div>
	</div>`;
}

async function onCcSchedClick(e) {
	const btn = e.target.closest('[data-sched]');
	if (!btn) return;
	const id = btn.getAttribute('data-id');
	const status = btn.getAttribute('data-sched');
	btn.disabled = true;
	try {
		const res = await spAjax('site_pulse_cc_schedule_update', { id: id, status: status });
		if (res.success) loadCcSchedule();
		else btn.disabled = false;
	} catch (err) { btn.disabled = false; }
}

/*--------------------------------------------------------------
# Company Directory
--------------------------------------------------------------*/

let _spDirWired = false;
let _spDirCanManage = false;
let _spDirOptions = { positions: {}, brands: {}, locations: {} };
let _spDirData = [];
let _spDirSearchT = null;

const DIR_DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Pull one of the company's registered framework icons (battleplan_icon_map, 512-viewBox fill-based,
// exposed as D.icons) by name, so the directory matches the rest of the site. '' if not registered.
function dirIcon(key) {
	const p = D.icons && D.icons[key];
	return p ? `<svg viewBox="0 0 512 512" fill="currentColor" aria-hidden="true">${p}</svg>` : '';
}

// Shared contact block — email (icon + link) and each phone number as a tappable pill button with a
// phone icon (phone-cell for cell, phone receiver for work). No CELL/WORK text labels.
function dirContactsHtml(e) {
	const items = [];
	if (e.email)      items.push(`<a class="unique sp-btn sp-btn-ghost sp-dir-cbtn sp-dir-email-btn" href="mailto:${esc(e.email)}">${dirIcon('email')}<span>${esc(e.email)}</span></a>`);
	if (e.cell_phone) items.push(`<a class="unique sp-btn sp-btn-ghost sp-dir-cbtn" href="tel:${esc(e.cell_phone)}" title="Cell">${dirIcon('phone-cell')}<span>${esc(e.cell_phone)}</span></a>`);
	if (e.sec_phone)  items.push(`<a class="unique sp-btn sp-btn-ghost sp-dir-cbtn" href="tel:${esc(e.sec_phone)}" title="Work">${dirIcon('phone')}<span>${esc(e.sec_phone)}</span></a>`);
	return items.length ? `<div class="sp-dir-contact-btns">${items.join('')}</div>` : '';
}

function initDirectory() {
	const panel = $('#sp-panel-directory');
	if (!panel || _spDirWired) return;
	$('#sp-dir-search')?.addEventListener('input', () => { clearTimeout(_spDirSearchT); _spDirSearchT = setTimeout(loadDirectory, 300); });
	['sp-dir-filter-brand', 'sp-dir-filter-location', 'sp-dir-filter-position', 'sp-dir-filter-status'].forEach(id => {
		document.getElementById(id)?.addEventListener('change', loadDirectory);
	});
	$('#sp-dir-clear')?.addEventListener('click', () => {
		['sp-dir-filter-brand', 'sp-dir-filter-location', 'sp-dir-filter-position'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
		const st = $('#sp-dir-filter-status'); if (st) st.value = 'active';
		const s = $('#sp-dir-search'); if (s) s.value = '';
		loadDirectory();
	});
	$('#sp-dir-add-btn')?.addEventListener('click', () => openDirForm(null));
	$('#sp-directory-grid')?.addEventListener('click', onDirGridClick);
	_spDirWired = true;
}

async function loadDirectory() {
	const grid = $('#sp-directory-grid');
	if (!grid) return;
	grid.innerHTML = '<div class="sp-loading"></div>';
	const params = {
		search:   $('#sp-dir-search')?.value.trim() || '',
		brand:    $('#sp-dir-filter-brand')?.value || '',
		location: $('#sp-dir-filter-location')?.value || '',
		position: $('#sp-dir-filter-position')?.value || '',
		status:   $('#sp-dir-filter-status')?.value || 'active',
	};
	try {
		const res = await spAjax('site_pulse_directory_list', params);
		if (!res.success) { grid.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load the directory.')}</div>`; return; }
		_spDirData      = res.data.employees || [];
		_spDirCanManage = !!res.data.can_manage;
		_spDirOptions   = res.data.options || _spDirOptions;
		const cnt = $('#sp-dir-count');
		if (cnt) cnt.textContent = `${_spDirData.length} ${_spDirData.length === 1 ? 'person' : 'people'}`;
		renderDirectory();
	} catch (e) {
		grid.innerHTML = '<div class="sp-empty">Error loading the directory.</div>';
	}
}

function dirLabel(kind, key) {
	if (!key) return '';
	const map = _spDirOptions[kind] || {};
	return map[key] || key;
}

function dirInitials(e) {
	const a = (e.first_name || '').trim()[0] || '';
	const b = (e.last_name || '').trim()[0] || '';
	return (a + b).toUpperCase() || '?';
}

function renderDirectory() {
	const grid = $('#sp-directory-grid');
	if (!grid) return;
	if (!_spDirData.length) { grid.innerHTML = '<div class="sp-empty">No one matches these filters.</div>'; return; }
	grid.innerHTML = _spDirData.map(dirCard).join('');
	markUniqueSpans(grid);
}

function dirCard(e) {
	const name = [e.first_name, e.last_name].filter(Boolean).join(' ');
	const photo = e.photo_url
		? `<div class="sp-dir-photo" style="background-image:url('${esc(e.photo_url)}')"></div>`
		: `<div class="sp-dir-photo sp-dir-photo-empty">${esc(dirInitials(e))}</div>`;
	const sub = [dirLabel('positions', e.position), dirLabel('brands', e.brand), dirLabel('locations', e.location)].filter(Boolean).join(' &middot; ');
	const contact = dirContactsHtml(e);
	const inactive = e.status === 'inactive' ? '<span class="unique sp-status-badge sp-status-draft">Inactive</span>' : '';
	const editBtn = _spDirCanManage ? `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-edit sp-dir-edit" data-id="${e.id}" title="Edit" aria-label="Edit">${ICON_EDIT}</button>` : '';
	return `<div class="sp-card sp-dir-card${e.status === 'inactive' ? ' is-inactive' : ''}" data-id="${e.id}">
		<div class="sp-card-body">
		${photo}
		<div class="sp-dir-body">
			<div class="sp-dir-name-row"><span class="unique sp-dir-name">${esc(name)}</span>${inactive}</div>
			${sub ? `<div class="unique sp-dir-sub">${sub}</div>` : ''}
			<div class="sp-dir-contacts">${contact}</div>
		</div>
		${editBtn}
		</div>
	</div>`;
}

function onDirGridClick(e) {
	const btn = e.target.closest('.sp-dir-edit');
	if (btn) {
		e.stopPropagation();
		const emp = _spDirData.find(x => String(x.id) === String(btn.dataset.id));
		if (emp) openDirForm(emp);
		return;
	}
	// A click anywhere else on the card opens the read-only detail (but let contact links work).
	if (e.target.closest('a')) return;
	const card = e.target.closest('.sp-dir-card');
	if (!card) return;
	const emp = _spDirData.find(x => String(x.id) === String(card.dataset.id));
	if (emp) openDirDetail(emp);
}

function dirFmtMonthDay(ymd) {
	if (!ymd) return '';
	const p = String(ymd).split('-');
	if (p.length < 3) return '';
	return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(undefined, { month: 'long', day: 'numeric' });
}

function dirFmtDate(ymd) {
	if (!ymd) return '';
	const p = String(ymd).split('-');
	if (p.length < 3) return '';
	return new Date(+p[0], +p[1] - 1, +p[2]).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

// Read-only detail view — everything we hold on a person. Visible to anyone with view access;
// the Edit button only appears for managers and hands off to the edit form.
function openDirDetail(e) {
	const name = [e.first_name, e.last_name].filter(Boolean).join(' ') || 'Employee';
	const photo = e.photo_url
		? `<div class="sp-dir-photo" style="background-image:url('${esc(e.photo_url)}')"></div>`
		: `<div class="sp-dir-photo sp-dir-photo-empty">${esc(dirInitials(e))}</div>`;
	const sub = [dirLabel('positions', e.position), dirLabel('brands', e.brand), dirLabel('locations', e.location)].filter(Boolean).join(' &middot; ');
	const inactive = e.status === 'inactive' ? '<span class="unique sp-status-badge sp-status-draft">Inactive</span>' : '';

	const contact = dirContactsHtml(e);

	const addr = [e.home_address, [e.address_city, e.address_zip].filter(Boolean).join(' ')].filter(Boolean).join(', ');
	const days = (e.days_off || '').split(',').map(s => s.trim()).filter(Boolean).join(', ');

	const row = (label, val) => val ? `<div class="sp-dir-detail-row"><span class="unique sp-dir-detail-label">${label}</span><span class="unique sp-dir-detail-val">${esc(val)}</span></div>` : '';
	let rows = '';
	rows += row('Birthday', dirFmtMonthDay(e.date_of_birth));
	rows += row('Date of hire', dirFmtDate(e.date_of_hire));
	rows += row('Address', addr);
	rows += row('Spouse', e.spouse);
	rows += row('Children', e.children);
	rows += row('Hobbies', e.hobbies);
	rows += row('Days off', days);
	rows += row('Notes', e.other_info);

	const modal = document.createElement('div');
	modal.id = 'sp-dir-detail-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal sp-dir-detail">
			<div class="sp-dir-detail-head">
				${photo}
				<div class="sp-dir-detail-headtext">
					<div class="sp-dir-name-row"><span class="unique sp-dir-detail-name">${esc(name)}</span>${inactive}</div>
					${sub ? `<div class="unique sp-dir-sub">${sub}</div>` : ''}
					${contact ? `<div class="sp-dir-contacts">${contact}</div>` : ''}
				</div>
			</div>
			${rows ? `<div class="sp-dir-detail-grid">${rows}</div>` : '<p class="sp-help-text">No additional details on file.</p>'}
			<div class="sp-modal-actions">
				${_spDirCanManage ? '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-dird-edit">Edit</button>' : ''}
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-dird-close">Close</button>
			</div>
		</div>`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);

	const close = () => modal.remove();
	modal.addEventListener('click', (ev) => { if (ev.target === modal) close(); });
	modal.querySelector('#sp-dird-close').addEventListener('click', close);
	modal.querySelector('#sp-dird-edit')?.addEventListener('click', () => { close(); openDirForm(e); });
}

// Build a <select> from one of the option maps; "---" entries are rendered as disabled separators.
function dirSelect(kind, current) {
	const map = _spDirOptions[kind] || {};
	let html = '<option value="">—</option>';
	for (const k in map) {
		if (k === '---') { html += '<option disabled>──────────</option>'; continue; }
		html += `<option value="${esc(k)}"${k === current ? ' selected' : ''}>${esc(map[k])}</option>`;
	}
	return html;
}

async function dirUploadPhoto(file) {
	const fd = new FormData();
	fd.append('action', 'site_pulse_directory_upload_photo');
	fd.append('nonce', spGetNonce());
	fd.append('file', file);
	try {
		const res = await fetch(D.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body: fd });
		const json = await res.json();
		if (json.success) return json.data.url;
		alert(json.data?.message || 'Upload failed.');
	} catch (e) { alert('Upload failed.'); }
	return '';
}

function openDirForm(emp) {
	const e = emp || {};
	const isEdit = !!emp;
	const name = [e.first_name, e.last_name].filter(Boolean).join(' ') || 'this employee';
	const daysSet = new Set((e.days_off || '').split(',').map(s => s.trim()).filter(Boolean));
	const dayChecks = DIR_DAYS.map(d => `<label class="sp-dir-day"><input type="checkbox" value="${d}"${daysSet.has(d) ? ' checked' : ''}> ${d.slice(0, 3)}</label>`).join('');
	const photoBox = e.photo_url
		? `<div class="sp-dir-photo" id="sp-dirf-preview" style="background-image:url('${esc(e.photo_url)}')"></div>`
		: `<div class="sp-dir-photo sp-dir-photo-empty" id="sp-dirf-preview">${esc(dirInitials(e))}</div>`;

	const modal = document.createElement('div');
	modal.id = 'sp-dir-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal sp-dir-form">
			<h3>${isEdit ? 'Edit Employee' : 'Add Employee'}</h3>
			<div class="sp-dir-form-top">
				<div class="sp-dir-photo-edit">
					${photoBox}
					<input type="file" id="sp-dirf-photo-input" accept="image/*" hidden>
					<button type="button" class="unique sp-btn sp-btn-ghost sp-btn-sm" id="sp-dirf-photo-btn">Photo</button>
					<input type="hidden" id="sp-dirf-photo-url" value="${esc(e.photo_url || '')}">
				</div>
				<div class="sp-dir-form-names">
					<div class="sp-form-row">
						<div class="sp-form-group"><label>First name</label><input type="text" id="sp-dirf-first" class="sp-input" value="${esc(e.first_name || '')}"></div>
						<div class="sp-form-group"><label>Last name</label><input type="text" id="sp-dirf-last" class="sp-input" value="${esc(e.last_name || '')}"></div>
					</div>
					<div class="sp-form-row">
						<div class="sp-form-group"><label>Status</label><select id="sp-dirf-status" class="sp-select"><option value="active"${e.status !== 'inactive' ? ' selected' : ''}>Active</option><option value="inactive"${e.status === 'inactive' ? ' selected' : ''}>Inactive</option></select></div>
						<div class="sp-form-group"><label>Position</label><select id="sp-dirf-position" class="sp-select">${dirSelect('positions', e.position)}</select></div>
					</div>
				</div>
			</div>

			<div class="sp-form-row">
				<div class="sp-form-group"><label>Brand</label><select id="sp-dirf-brand" class="sp-select">${dirSelect('brands', e.brand)}</select></div>
				<div class="sp-form-group"><label>Location</label><select id="sp-dirf-location" class="sp-select">${dirSelect('locations', e.location)}</select></div>
			</div>

			<div class="sp-form-row">
				<div class="sp-form-group"><label>Email</label><input type="email" id="sp-dirf-email" class="sp-input" value="${esc(e.email || '')}"></div>
				<div class="sp-form-group"><label>Cell phone</label><input type="text" id="sp-dirf-cell" class="sp-input" value="${esc(e.cell_phone || '')}" placeholder="xxx-xxx-xxxx"></div>
				<div class="sp-form-group"><label>Work phone</label><input type="text" id="sp-dirf-sec" class="sp-input" value="${esc(e.sec_phone || '')}" placeholder="xxx-xxx-xxxx"></div>
			</div>

			<div class="sp-form-row">
				<div class="sp-form-group"><label>Birthday</label><input type="date" id="sp-dirf-dob" class="sp-input" value="${esc(e.date_of_birth || '')}"></div>
				<div class="sp-form-group"><label>Date of hire</label><input type="date" id="sp-dirf-hire" class="sp-input" value="${esc(e.date_of_hire || '')}"></div>
			</div>

			<div class="sp-form-row">
				<div class="sp-form-group sp-grow2"><label>Home address</label><input type="text" id="sp-dirf-addr" class="sp-input" value="${esc(e.home_address || '')}"></div>
				<div class="sp-form-group"><label>City</label><input type="text" id="sp-dirf-city" class="sp-input" value="${esc(e.address_city || '')}"></div>
				<div class="sp-form-group"><label>Zip</label><input type="text" id="sp-dirf-zip" class="sp-input" value="${esc(e.address_zip || '')}"></div>
			</div>

			<div class="sp-form-row">
				<div class="sp-form-group"><label>Spouse</label><input type="text" id="sp-dirf-spouse" class="sp-input" value="${esc(e.spouse || '')}"></div>
				<div class="sp-form-group"><label>Children</label><input type="text" id="sp-dirf-children" class="sp-input" value="${esc(e.children || '')}"></div>
				<div class="sp-form-group"><label>Hobbies</label><input type="text" id="sp-dirf-hobbies" class="sp-input" value="${esc(e.hobbies || '')}"></div>
			</div>

			<div class="sp-form-group"><label>Days off</label><div class="sp-dir-days">${dayChecks}</div></div>
			<div class="sp-form-group"><label>Other info</label><textarea id="sp-dirf-other" class="sp-textarea" rows="2">${esc(e.other_info || '')}</textarea></div>

			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-dirf-save">${isEdit ? 'Save' : 'Add'}</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-dirf-cancel">Cancel</button>
				${isEdit ? `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete" id="sp-dirf-delete" title="Delete" aria-label="Delete" style="margin-left:auto;">${ICON_DELETE}</button>` : ''}
			</div>
		</div>`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);

	const close = () => modal.remove();
	modal.addEventListener('click', (ev) => { if (ev.target === modal) close(); });
	modal.querySelector('#sp-dirf-cancel').addEventListener('click', close);

	const fileInput = modal.querySelector('#sp-dirf-photo-input');
	modal.querySelector('#sp-dirf-photo-btn').addEventListener('click', () => fileInput.click());
	fileInput.addEventListener('change', async () => {
		if (!fileInput.files || !fileInput.files[0]) return;
		const pbtn = modal.querySelector('#sp-dirf-photo-btn');
		pbtn.disabled = true; pbtn.textContent = 'Uploading…';
		const url = await dirUploadPhoto(fileInput.files[0]);
		pbtn.disabled = false; pbtn.textContent = 'Photo';
		if (url) {
			modal.querySelector('#sp-dirf-photo-url').value = url;
			const prev = modal.querySelector('#sp-dirf-preview');
			prev.classList.remove('sp-dir-photo-empty');
			prev.textContent = '';
			prev.style.backgroundImage = `url('${url}')`;
		}
	});

	modal.querySelector('#sp-dirf-save').addEventListener('click', async () => {
		const first = modal.querySelector('#sp-dirf-first').value.trim();
		const last  = modal.querySelector('#sp-dirf-last').value.trim();
		if (!first && !last) { alert('Please enter a first or last name.'); return; }
		const days = [...modal.querySelectorAll('.sp-dir-days input:checked')].map(c => c.value);
		const sbtn = modal.querySelector('#sp-dirf-save');
		sbtn.disabled = true; sbtn.textContent = 'Saving…';
		const r = await spAjax('site_pulse_directory_save', {
			id: e.id || 0,
			first_name: first,
			last_name: last,
			status: modal.querySelector('#sp-dirf-status').value,
			position: modal.querySelector('#sp-dirf-position').value,
			brand: modal.querySelector('#sp-dirf-brand').value,
			location: modal.querySelector('#sp-dirf-location').value,
			email: modal.querySelector('#sp-dirf-email').value.trim(),
			cell_phone: modal.querySelector('#sp-dirf-cell').value.trim(),
			sec_phone: modal.querySelector('#sp-dirf-sec').value.trim(),
			date_of_birth: modal.querySelector('#sp-dirf-dob').value,
			date_of_hire: modal.querySelector('#sp-dirf-hire').value,
			home_address: modal.querySelector('#sp-dirf-addr').value.trim(),
			address_city: modal.querySelector('#sp-dirf-city').value.trim(),
			address_zip: modal.querySelector('#sp-dirf-zip').value.trim(),
			spouse: modal.querySelector('#sp-dirf-spouse').value.trim(),
			children: modal.querySelector('#sp-dirf-children').value.trim(),
			hobbies: modal.querySelector('#sp-dirf-hobbies').value.trim(),
			other_info: modal.querySelector('#sp-dirf-other').value.trim(),
			days_off: days,
			photo_url: modal.querySelector('#sp-dirf-photo-url').value,
		});
		if (r.success) { close(); loadDirectory(); }
		else { alert(r.data?.message || 'Error saving.'); sbtn.disabled = false; sbtn.textContent = isEdit ? 'Save' : 'Add'; }
	});

	const delBtn = modal.querySelector('#sp-dirf-delete');
	if (delBtn) {
		delBtn.addEventListener('click', async () => {
			if (!confirm(`Delete ${name}? This permanently removes them from the directory. To keep the record but hide them, set Status to Inactive instead.`)) return;
			const r = await spAjax('site_pulse_directory_delete', { id: e.id });
			if (r.success) { close(); loadDirectory(); }
			else alert(r.data?.message || 'Error deleting.');
		});
	}
}


/*--------------------------------------------------------------
# Customer Emails (Customer Feedback → Emails)
--------------------------------------------------------------*/

let _spEmailsWired = false;
let _spEmailsCanManage = false;
let _spEmails = [];
let _spEmailsSearchT = null;

function initEmails() {
	const panel = $('#sp-panel-emails');
	if (!panel || _spEmailsWired) return;
	$('#sp-emails-search')?.addEventListener('input', () => { clearTimeout(_spEmailsSearchT); _spEmailsSearchT = setTimeout(loadEmails, 300); });
	['sp-emails-filter-status', 'sp-emails-filter-category', 'sp-emails-filter-brand', 'sp-emails-filter-location'].forEach(id => {
		document.getElementById(id)?.addEventListener('change', loadEmails);
	});
	$('#sp-emails-list')?.addEventListener('click', onEmailsListClick);
	_spEmailsWired = true;
}

async function loadEmails() {
	const list = $('#sp-emails-list');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';
	const params = {
		status:   $('#sp-emails-filter-status')?.value || 'new',
		category: $('#sp-emails-filter-category')?.value || '',
		brand:    $('#sp-emails-filter-brand')?.value || '',
		location: $('#sp-emails-filter-location')?.value || '',
		search:   $('#sp-emails-search')?.value.trim() || '',
	};
	try {
		const res = await spAjax('site_pulse_emails_list', params);
		if (!res.success) { list.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load emails.')}</div>`; return; }
		_spEmails = res.data.emails || [];
		_spEmailsCanManage = !!res.data.can_manage;
		spEmailsSyncFilters(res.data);
		const cnt = $('#sp-emails-count');
		if (cnt) cnt.textContent = `${spComma(res.data.new_count || 0)} new · ${spComma(res.data.handled_count || 0)} handled`;
		renderEmails();
	} catch (e) {
		list.innerHTML = '<div class="sp-empty">Error loading emails.</div>';
	}
}

// Keep the type/brand/location dropdowns populated from whatever's actually present, preserving the pick.
function spEmailsSyncFilters(d) {
	const fill = (id, vals, allLabel) => {
		const sel = document.getElementById(id);
		if (!sel) return;
		const cur = sel.value;
		sel.innerHTML = `<option value="">${allLabel}</option>` + (vals || []).map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
		if (cur && (vals || []).indexOf(cur) !== -1) sel.value = cur;
	};
	fill('sp-emails-filter-category', d.categories, 'All types');
	fill('sp-emails-filter-brand', d.brands, 'All brands');
	fill('sp-emails-filter-location', d.locations, 'All locations');
}

function emailCategoryClass(cat) {
	const c = String(cat || '').toLowerCase();
	if (c.indexOf('complaint') !== -1) return 'sp-email-cat-neg';
	if (c.indexOf('compliment') !== -1 || c.indexOf('comment') !== -1) return 'sp-email-cat-pos';
	return 'sp-email-cat-neutral';
}

function renderEmails() {
	const list = $('#sp-emails-list');
	if (!list) return;
	if (!_spEmails.length) { list.innerHTML = '<div class="sp-empty">No customer emails match this filter.</div>'; return; }
	list.innerHTML = _spEmails.map(emailCard).join('');
	markUniqueSpans(list);
}

function emailCard(m) {
	const date = m.created_at ? new Date(m.created_at.replace(' ', 'T')).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '';
	const where = [m.brand, m.location].filter(Boolean).join(' · ');
	const cat = m.category ? `<span class="unique sp-email-cat ${emailCategoryClass(m.category)}">${esc(m.category)}</span>` : '';
	const handled = m.status === 'handled' ? '<span class="unique sp-status-badge sp-status-draft">Handled</span>' : '';
	let contact = '';
	if (m.email) contact += `<a class="unique sp-dir-cbtn sp-btn sp-btn-ghost" href="mailto:${esc(m.email)}">${dirIcon('email')}<span>${esc(m.email)}</span></a>`;
	if (m.phone) contact += `<a class="unique sp-dir-cbtn sp-btn sp-btn-ghost" href="tel:${esc(m.phone)}">${dirIcon('phone-cell')}<span>${esc(m.phone)}</span></a>`;
	let actions = '';
	if (_spEmailsCanManage) {
		actions = '<div class="unique sp-email-actions">' +
			`<button type="button" class="unique sp-btn sp-btn-ghost sp-email-toggle" data-id="${m.id}" data-status="${m.status === 'handled' ? 'new' : 'handled'}">${m.status === 'handled' ? 'Mark new' : 'Mark handled'}</button>` +
			`<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-email-delete" data-id="${m.id}" title="Delete" aria-label="Delete">${ICON_DELETE}</button>` +
		'</div>';
	}
	return `<div class="sp-card sp-email-card${m.status === 'handled' ? ' is-handled' : ''}">
		<div class="sp-card-header sp-header sp-email-head">
			<div class="sp-email-who"><span class="unique sp-email-name">${esc(m.customer_name || 'Anonymous')}</span>${cat}${handled}</div>
			<span class="unique sp-email-date">${esc(date)}</span>
		</div>
		<div class="sp-card-body">
		${where ? `<div class="unique sp-email-where">${esc(where)}</div>` : ''}
		${m.message ? `<p class="unique sp-email-message">${esc(m.message)}</p>` : ''}
		<div class="sp-email-foot">
			<div class="sp-email-contacts">${contact}</div>
			${actions}
		</div>
		</div>
	</div>`;
}

async function onEmailsListClick(e) {
	const toggle = e.target.closest('.sp-email-toggle');
	if (toggle) {
		toggle.disabled = true;
		const res = await spAjax('site_pulse_emails_set_status', { id: toggle.dataset.id, status: toggle.dataset.status });
		if (res.success) loadEmails(); else { toggle.disabled = false; spFlash(res.data?.message || 'Could not update.'); }
		return;
	}
	const del = e.target.closest('.sp-email-delete');
	if (del) {
		if (!confirm('Delete this email? This cannot be undone.')) return;
		const res = await spAjax('site_pulse_emails_delete', { id: del.dataset.id });
		if (res.success) loadEmails(); else spFlash(res.data?.message || 'Could not delete.');
	}
}


/* ---- Customer Messages (Facebook / Instagram DMs) — reuses the Messages styling ---- */
let _spCm = { cid: 0, timer: null };
let _spCmManage = false;

function initCustomerMessages() {
	loadCmConversations();
	if (_spCm.cid) openCmConversation(_spCm.cid); else renderCmThreadEmpty();
	stopCmPolling();
	_spCm.timer = setInterval(() => { loadCmConversations(); if (_spCm.cid) refreshCmThread(_spCm.cid); }, 12000);
}
function stopCmPolling() { if (_spCm.timer) { clearInterval(_spCm.timer); _spCm.timer = null; } }

async function loadCmConversations() {
	const list = $('#sp-cm-list');
	if (!list) return;
	try {
		const res = await spAjax('site_pulse_cm_conversations', {});
		if (!res.success) { list.innerHTML = '<div class="sp-msg-empty">Could not load messages.</div>'; return; }
		_spCmManage = !!res.data.can_manage;
		updateCmBadge(res.data.unread || 0);
		const convos = res.data.conversations || [];
		if (!convos.length) { list.innerHTML = '<div class="sp-msg-empty">No customer messages yet.</div>'; return; }
		list.innerHTML = convos.map(c => {
			const plat = c.platform === 'instagram' ? 'Instagram' : 'Facebook';
			return `<button type="button" class="unique sp-msg-convo${c.id === _spCm.cid ? ' active' : ''}${c.unread ? ' unread' : ''}" data-cid="${c.id}">
				<div class="sp-msg-convo-top"><span class="sp-msg-convo-name">${esc(c.customer_name)} <span class="unique sp-cm-tag sp-cm-${c.platform}">${plat}</span></span>${c.unread ? '<span class="sp-msg-convo-badge">•</span>' : ''}</div>
				<div class="sp-msg-convo-preview">${esc((c.last_text || '').slice(0, 60))}</div>
				<div class="sp-msg-convo-preview" style="opacity:.65">${esc(c.page_name || '')}</div>
			</button>`;
		}).join('');
		$$('.sp-msg-convo', list).forEach(b => b.addEventListener('click', () => openCmConversation(parseInt(b.dataset.cid, 10))));
	} catch (e) { list.innerHTML = '<div class="sp-msg-empty">Error loading messages.</div>'; }
}

function renderCmThreadEmpty() { const t = $('#sp-cm-thread'); if (t) t.innerHTML = '<div class="sp-msg-empty">Select a conversation.</div>'; }

async function openCmConversation(cid) {
	_spCm.cid = cid;
	const thread = $('#sp-cm-thread');
	if (!thread) return;
	thread.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_cm_thread', { conversation_id: cid });
		if (!res.success) { thread.innerHTML = '<div class="sp-msg-empty">Could not load.</div>'; return; }
		renderCmThread(res.data);
		$('#sp-cm-messenger')?.classList.add('thread-open');
		loadCmConversations();
	} catch (e) { thread.innerHTML = '<div class="sp-msg-empty">Error.</div>'; }
}

async function refreshCmThread(cid) {
	try { const res = await spAjax('site_pulse_cm_thread', { conversation_id: cid }); if (res.success && _spCm.cid === cid) renderCmThread(res.data); } catch (e) {}
}

function renderCmThread(data) {
	const thread = $('#sp-cm-thread');
	if (!thread) return;
	const c = data.conversation || {};
	const plat = c.platform === 'instagram' ? 'Instagram' : 'Facebook';
	let html = '<div class="sp-card-header sp-header sp-msg-thread-header">';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-msg-back sp-cm-back" aria-label="Back to messages">&#8592;</button>';
	html += `<span class="sp-msg-thread-title">${esc(c.customer_name)} <span class="unique sp-cm-tag sp-cm-${c.platform}">${plat}</span></span>`;
	html += `<span class="sp-msg-thread-actions" style="color:var(--sp-text-secondary);font-size:12px">${esc(c.page_name || '')}</span>`;
	html += '</div><div class="sp-msg-bubbles" id="sp-cm-bubbles">';
	(data.messages || []).forEach(m => {
		const mine = m.direction === 'out';
		html += `<div class="unique sp-msg-bubble ${mine ? 'mine' : 'theirs'}">${esc(m.body)}</div>`;
	});
	html += '</div>';
	if (data.can_manage) {
		html += data.reply_open
			? '<div class="sp-msg-composer sp-card-footer"><textarea class="unique sp-input sp-msg-input" id="sp-cm-input" rows="1" placeholder="Reply…"></textarea><button type="button" class="unique sp-btn sp-btn-primary" id="sp-cm-send">Send</button></div>'
			: '<div class="sp-msg-composer sp-card-footer"><span class="sp-help-text">The 24-hour reply window has closed — Meta only allows a reply within 24h of the customer’s last message.</span></div>';
	}
	thread.innerHTML = html;
	markUniqueSpans(thread);
	const bubbles = $('#sp-cm-bubbles', thread); if (bubbles) bubbles.scrollTop = bubbles.scrollHeight;
	$('.sp-cm-back', thread)?.addEventListener('click', () => { $('#sp-cm-messenger')?.classList.remove('thread-open'); _spCm.cid = 0; renderCmThreadEmpty(); loadCmConversations(); });
	$('#sp-cm-send', thread)?.addEventListener('click', sendCmReply);
	$('#sp-cm-input', thread)?.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendCmReply(); } });
}

async function sendCmReply() {
	const input = $('#sp-cm-input');
	const text = input?.value.trim() || '';
	if (!text || !_spCm.cid) return;
	const btn = $('#sp-cm-send'); if (btn) btn.disabled = true;
	try {
		const res = await spAjax('site_pulse_cm_send', { conversation_id: _spCm.cid, body: text });
		if (!res.success) { alert(res.data?.message || 'Could not send.'); if (btn) btn.disabled = false; return; }
		if (input) input.value = '';
		openCmConversation(_spCm.cid);
	} catch (e) { alert('Could not send.'); if (btn) btn.disabled = false; }
}

function updateCmBadge(count) {
	const navBtn = $('[data-nav="customer-messages"]');
	if (!navBtn) return;
	let badge = navBtn.querySelector('.sp-nav-badge');
	if (count > 0) { if (!badge) { badge = document.createElement('span'); badge.className = 'sp-nav-badge'; navBtn.appendChild(badge); } badge.textContent = count > 99 ? '99+' : count; }
	else if (badge) badge.remove();
}

function esc(str) {
	if (!str) return '';
	let s = String(str);
	// Some sources (e.g. Google review text) arrive already HTML-encoded — "it&#39;s", "Babe&amp;s".
	// Decode once first so we don't double-encode and render the raw entity. Only when an "&" is
	// present, to keep the common (entity-free) case cheap.
	if (s.indexOf('&') !== -1) {
		const d = document.createElement('textarea');
		d.innerHTML = s;
		s = d.value;
	}
	const el = document.createElement('span');
	el.textContent = s;
	return el.innerHTML;
}

function formatDate(dateStr) {
	if (!dateStr) return '';
	const d = new Date(dateStr + 'T00:00:00');
	return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Whole-day difference from today (local) to a YYYY-MM-DD date: 0 = today, 1 = tomorrow, <0 = past.
function daysUntil(dateStr) {
	if (!dateStr) return null;
	const due = new Date(String(dateStr).split(' ')[0] + 'T00:00:00');
	if (isNaN(due)) return null;
	const now = new Date();
	const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
	return Math.round((due - today) / 86400000);
}


/*--------------------------------------------------------------
# Reviews — Google Business Profile
--------------------------------------------------------------*/

let spReviews = [];
let spReviewsCanManage = false;
let spReviewsLoaded = false;
let _spReviewsAutoAnalyzed = false; // one-shot per fresh load: auto-run analysis on untagged reviews (no button click)
let spReviewsAvg = null;
let spReviewsTotal = 0;
let spReviewsHasOlder = false;
let spReviewTopicFilter = ''; // when set (from a stat-card click), the list shows only reviews mentioning this topic

// Infinite-scroll pagination state. spReviews holds the rows loaded so far (page 1..N appended);
// spReviewsMatched is the total stored rows matching the current filters (the "of Y" count).
const SP_REVIEWS_PER_PAGE = 50;
let spReviewsPage = 1;
let spReviewsHasMore = false;
let spReviewsMatched = 0;
let spReviewsLoadingMore = false;
let spReviewsIO = null;

// All list filters now run server-side so the list can paginate: scope (range/store/brand) + the
// secondary star/reply filters + the topic chosen by a stat-card/chip click.
function reviewQueryParams() {
	return {
		range:    $('#sp-reviews-stat-range')?.value || '30',
		store:    $('#sp-reviews-stat-restaurant')?.value || '',
		brand:    $('#sp-reviews-stat-brand')?.value || '',
		stars:    $('#sp-reviews-filter-stars')?.value || '',
		reply:    $('#sp-reviews-filter-reply')?.value || '',
		source:   $('#sp-reviews-filter-platform')?.value || '',
		topic:    spReviewTopicFilter || '',
		per_page: SP_REVIEWS_PER_PAGE,
	};
}

// Integer with thousands separators (2157 → "2,157"); leaves non-numerics untouched.
function spComma(n) {
	const v = parseInt(n, 10);
	return isNaN(v) ? String(n ?? '') : v.toLocaleString('en-US');
}

function initReviews() {
	const panel = $('#sp-panel-reviews');
	if (!panel) return;

	$('#sp-reviews-refresh-btn')?.addEventListener('click', () => loadReviews(true));
	$('#sp-reviews-analyze-btn')?.addEventListener('click', analyzeReviews);
	$('#sp-reviews-load-older')?.addEventListener('click', loadOlderReviews);
	// These below-graph filters scope the LIST and the stat cards, so the graphs move with them too.
	$('#sp-reviews-filter-stars')?.addEventListener('change', () => { loadReviews(); loadReviewStats(); });
	$('#sp-reviews-filter-reply')?.addEventListener('change', () => { loadReviews(); loadReviewStats(); });
	$('#sp-reviews-filter-platform')?.addEventListener('change', () => { loadReviews(); loadReviewStats(); });

	// Analytics scope (restaurant / brand / time range) re-scopes the list (server-side) AND the stat cards.
	const onScopeChange = () => { loadReviews(); loadReviewStats(); };
	$('#sp-reviews-stat-range')?.addEventListener('change', onScopeChange);
	$('#sp-reviews-stat-restaurant')?.addEventListener('change', onScopeChange);
	$('#sp-reviews-stat-brand')?.addEventListener('change', onScopeChange);

	// Clicking a stat card filters the list to that topic.
	$('#sp-reviews-stats')?.addEventListener('click', onReviewStatClick);

	// Per-review action buttons are rendered dynamically, so delegate from the list.
	$('#sp-reviews-list')?.addEventListener('click', onReviewListClick);
}

// Pull Google's older reviews into the DB in small reliable batches (each well under the hub timeout),
// looping up to ~1000 per click. This only fills the DB; the list itself is paginated, so afterward we
// refresh page 1 (the newly-stored older reviews surface as you scroll). Progress is measured by the
// server's stored-row count, not the on-screen list.
async function loadOlderReviews() {
	const btn = $('#sp-reviews-load-older');
	if (!btn || btn.disabled) return;
	const TARGET = 1000; // aim to add roughly this many per click
	btn.disabled = true;
	btn.textContent = 'Loading older reviews…';
	let errored = false, zeroGain = 0, startStored = null, lastStored = null, lastData = null;
	try {
		while (true) {
			const res = await spAjax('site_pulse_load_older_reviews', spReviewScope());
			if (!res.success) { spFlash(res.data?.message || 'Could not load older reviews.'); errored = true; break; }
			lastData = res.data;
			spReviewsHasOlder = !!res.data.has_older;
			if (res.data.totalReviewCount != null) spReviewsTotal = res.data.totalReviewCount;
			const stored = res.data.stored != null ? res.data.stored : null;
			if (startStored === null) startStored = stored;
			// A batch can legitimately add 0 (it re-walks pages we already hold to advance the cursor), so
			// don't stop on that alone — keep going while there's more, with a small cap as a runaway guard.
			const gained = (stored != null && lastStored != null) ? stored - lastStored : 0;
			lastStored = stored;
			zeroGain = gained === 0 ? zeroGain + 1 : 0;
			if (!res.data.has_older || (stored != null && startStored != null && stored - startStored >= TARGET) || zeroGain >= 6) break;
		}
	} catch (e) {
		spFlash('Could not load older reviews.');
		errored = true;
	}
	spUpdateLoadOlderBtn(lastData);
	btn.disabled = false;
	btn.textContent = 'Load older reviews';

	await loadReviews(); // refresh the list (page 1) so newly-stored older reviews are reachable

	const added = (lastStored != null && startStored != null) ? lastStored - startStored : 0;
	if (!errored && added > 0)               spFlash(`Loaded ${spComma(added)} older review${added === 1 ? '' : 's'}.`);
	else if (!errored && !spReviewsHasOlder) spFlash('All reviews are now loaded.');
	else if (!errored)                       spFlash('Still compiling older reviews — click again to continue.');
}

// AI-tag the review back-catalogue in chunks (each server call analyzes ~60 with Claude). Loops until
// the queue is empty, showing live progress, then re-reads the list so the new chips appear.
async function analyzeReviews() {
	const btn = $('#sp-reviews-analyze-btn');
	const status = $('#sp-reviews-analyze-status');
	if (!btn || btn.disabled) return;
	const orig = btn.textContent;
	btn.disabled = true;
	btn.textContent = 'Analyzing…';
	let errored = false, total = 0;
	try {
		while (true) {
			const res = await spAjax('site_pulse_analyze_reviews', {});
			if (!res.success) { spFlash(res.data?.message || 'Analysis failed.'); errored = true; break; }
			total += res.data.tagged || 0;
			if (status) status.textContent = res.data.remaining > 0 ? `Analyzed ${spComma(total)} — ${spComma(res.data.remaining)} to go…` : `Analyzed ${spComma(total)}.`;
			if (res.data.error) { spFlash(res.data.error); errored = true; break; }
			if (!res.data.remaining || res.data.tagged === 0) break; // done, or no progress this pass
		}
	} catch (e) {
		spFlash('Analysis failed.');
		errored = true;
	}
	btn.disabled = false;
	btn.textContent = orig;
	// Re-read from the store (no hub re-sync) so the freshly-tagged chips render.
	spReviewsLoaded = false;
	loadReviews();
	if (!errored) spFlash('Review analysis complete.');
}

/*--------------------------------------------------------------
# Review analytics — sentiment stat cards (scoped by the dropdowns)
--------------------------------------------------------------*/

async function loadReviewStats() {
	const box = $('#sp-reviews-stats');
	if (!box) return;
	spReviewTopicFilter = ''; // a scope change resets any card-driven topic filter
	const range = $('#sp-reviews-stat-range')?.value || 'all';
	const store = $('#sp-reviews-stat-restaurant')?.value || '';
	const brand = $('#sp-reviews-stat-brand')?.value || '';
	// The below-graph filters (ratings / reviews / platform) scope the cards too, so the graphs reflect
	// exactly what the list is showing — e.g. only Facebook, or only 1-star.
	const stars  = $('#sp-reviews-filter-stars')?.value || '';
	const reply  = $('#sp-reviews-filter-reply')?.value || '';
	const source = $('#sp-reviews-filter-platform')?.value || '';
	// First load shows the full-size spinner; a re-scope dims the existing cards + overlays a spinner
	// (the server re-aggregates tens of thousands of reviews, so this can take a few seconds).
	if (!box.innerHTML) box.innerHTML = '<div class="sp-loading"></div>';
	else box.classList.add('is-loading');
	try {
		const res = await spAjax('site_pulse_review_stats', { range, store, brand, stars, reply, source });
		if (!res.success) { box.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load stats.')}</div>`; return; }
		spPopulateStatDropdowns(res.data);
		renderReviewStats(res.data);
	} catch (e) {
		box.innerHTML = '<div class="sp-empty">Error loading stats.</div>';
	} finally {
		box.classList.remove('is-loading');
	}
}

// Fill the restaurant/brand selects from the data (only when the option set actually changed).
function spPopulateStatDropdowns(d) {
	spFillStatSelect('#sp-reviews-stat-restaurant', 'All restaurants', d.stores || []);
	spFillStatSelect('#sp-reviews-stat-brand', 'All brands', d.brands || []);
}
function spFillStatSelect(sel, allLabel, opts) {
	const el = $(sel);
	if (!el) return;
	const want = [''].concat(opts);
	const have = Array.from(el.options).map(o => o.value);
	if (have.length === want.length && have.every((v, i) => v === want[i])) return; // unchanged
	const cur = el.value;
	el.innerHTML = `<option value="">${esc(allLabel)}</option>` + opts.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('');
	el.value = want.indexOf(cur) >= 0 ? cur : '';
}

function renderReviewStats(d) {
	const box = $('#sp-reviews-stats');
	if (!box) return;
	_spReviewStatsData = d;   // remember for re-render when the chart style is toggled
	const cards = [];
	// Count tile — clicking it clears any topic filter (shows all reviews again).
	cards.push(
		'<div class="sp-tile sp-feedback-stat-tile sp-tile-clickable" data-topic="">' +
			`<div class="sp-survey-stat-left"><div class="sp-survey-stat-label">Reviews</div><div class="sp-survey-stat-value">${esc(spComma(d.count || 0))}</div></div>` +
		'</div>'
	);
	// Average rating tile with a 5→1 star distribution (not a topic filter).
	cards.push(
		'<div class="sp-tile sp-feedback-stat-tile">' +
			`<div class="sp-survey-stat-left"><div class="sp-survey-stat-label">Avg Rating</div><div class="sp-survey-stat-value">${d.avg != null ? esc(Number(d.avg).toFixed(1)) : '—'}</div></div>` +
			spStarDistBars(d.stardist) +
		'</div>'
	);
	// One tile per topic: mention count + positive / neutral / negative split. Clicking filters the list.
	(d.topics || []).forEach(t => {
		// Headline is the category's average 1-5 rating (the "ranking"); mention count shown in parens.
		const avg = (t.avg_score != null) ? Number(t.avg_score).toFixed(1) : '—';
		cards.push(
			`<div class="sp-tile sp-feedback-stat-tile sp-tile-clickable" data-topic="${esc(t.label)}">` +
				'<div class="sp-survey-stat-left">' +
					`<div class="sp-survey-stat-label">${esc(t.label)} <span style="font-weight:400;color:var(--sp-text-light)">(${esc(spComma(t.mentions))})</span></div>` +
					`<div class="sp-survey-stat-value">${avg}<span style="font-size:.6em;color:var(--sp-text-light)"> / 5</span></div>` +
				'</div>' +
				spSentimentBars(t) +
			'</div>'
		);
	});

	let html = spChartToggle() + `<div class="sp-survey-stat-grid">${cards.join('')}</div>`;
	// Longer ranges depend on the background back-fill; warn while any location is still being walked.
	const range = $('#sp-reviews-stat-range')?.value || '30';
	if (d.syncing && range !== '30') {
		html += `<div class="sp-reviews-stat-note">Older reviews are still syncing in the background — figures for this range will keep rising until the full history finishes loading.</div>`;
	}
	if (d.untagged > 0) {
		// Auto-analyze: kick the same loop the button runs, once per fresh load, so new reviews get their
		// topic/sentiment chips without anyone clicking "Analyze reviews". The cron handles the background
		// path; this covers "I just opened Reviews and there's a new one". Only managers can tag (the
		// button only exists for them) — view-only users still get the note + the hourly cron.
		const aBtn = $('#sp-reviews-analyze-btn');
		if (aBtn && !aBtn.disabled && !_spReviewsAutoAnalyzed) {
			_spReviewsAutoAnalyzed = true; // guard against re-triggering (e.g. chart toggle, or un-taggable rows)
			html += `<div class="sp-reviews-stat-note">Analyzing ${spComma(d.untagged)} new review${d.untagged === 1 ? '' : 's'}…</div>`;
			setTimeout(analyzeReviews, 50);
		} else {
			html += `<div class="sp-reviews-stat-note">${spComma(d.untagged)} review${d.untagged === 1 ? '' : 's'} not yet analyzed${aBtn ? ' — click “Analyze reviews” above for complete topic stats' : ' — analysis runs automatically in the background'}.</div>`;
		}
	}
	box.innerHTML = html;
	spMarkActiveStatCard();
}

// Highlight the topic card matching the active filter (the "Reviews"/all card is never highlighted).
function spMarkActiveStatCard() {
	$$('#sp-reviews-stats .sp-tile-clickable').forEach(c => {
		const topic = c.getAttribute('data-topic') || '';
		c.classList.toggle('sp-tile-active', !!topic && topic === spReviewTopicFilter);
	});
}

// Click a stat card → filter the list to that topic (toggle off if it's already active; the count card clears).
function onReviewStatClick(e) {
	const card = e.target.closest('.sp-tile-clickable');
	if (!card) return;
	const topic = card.getAttribute('data-topic') || '';
	spReviewTopicFilter = (topic && spReviewTopicFilter === topic) ? '' : topic;
	spMarkActiveStatCard();
	loadReviews(); // topic filter runs server-side now — re-query from page 1
	if (spReviewTopicFilter) $('#sp-reviews-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// 5→1 star distribution — bar or pie per the user's choice.
function spStarDistBars(dist) {
	return spDistChart(spStarSegments(dist));
}

// Positive / Neutral / Negative split for one topic tile — bar or pie per the user's choice.
function spSentimentBars(t) {
	const segs = [
		{ label: 'Pos', pct: t.pos_pct || 0, cls: 'sp-rev-fill-pos' },
		{ label: 'Neu', pct: t.neu_pct || 0, cls: 'sp-rev-fill-neu' },
		{ label: 'Neg', pct: t.neg_pct || 0, cls: 'sp-rev-fill-neg' },
	];
	return spDistChart(segs, { labelClass: 'sp-rev-bar-lbl' });
}

// "Showing X of Y reviews" — footer count. X = rows loaded so far (pages appended), Y = total stored
// rows matching the current filters. Owns the count element; always shown once anything is loaded.
function spRenderReviewsCount() {
	const cnt = $('#sp-reviews-count');
	if (!cnt) return;
	const loaded = spReviews.length;
	const total  = spReviewsMatched || loaded;
	cnt.textContent = `Showing ${spComma(loaded)} of ${spComma(total)} reviews`;
	cnt.hidden = loaded === 0;
}

// The "Load older" button pulls Google's back-catalogue into the DB; it only applies to the All-time
// view (a bounded range already returns its complete slice). The footer count is owned separately by
// spRenderReviewsCount. (The cron keeps back-filling history regardless of this button.)
function spUpdateLoadOlderBtn(d) {
	const btn = $('#sp-reviews-load-older');
	if (d) {
		spReviewsHasOlder = !!d.has_older;
		if (d.totalReviewCount != null) spReviewsTotal = d.totalReviewCount;
	}
	const allTime  = ( $('#sp-reviews-stat-range')?.value || '30' ) === 'all';
	const hasOlder = spReviewsHasOlder && allTime;
	if (btn) {
		btn.hidden = !hasOlder;
		if (hasOlder) btn.textContent = 'Load older reviews';
	}
}

// Current analytics scope (restaurant / brand / time range) sent to the server so it only returns and
// aggregates that slice — the tab no longer pulls tens of thousands of rows just to filter them client-side.
function spReviewScope() {
	return {
		range: $('#sp-reviews-stat-range')?.value || '30',
		store: $('#sp-reviews-stat-restaurant')?.value || '',
		brand: $('#sp-reviews-stat-brand')?.value || '',
	};
}

// (Re)load the list from page 1 — used on first open, scope/filter change, refresh, and after a backfill.
async function loadReviews(force = false) {
	const list = $('#sp-reviews-list');
	if (!list) return;

	if (force) _spReviewsAutoAnalyzed = false; // a manual Refresh re-arms auto-analyze for anything still untagged
	spReviewsPage = 1;
	spReviewsLoadingMore = false;
	if (spReviewsIO) spReviewsIO.disconnect();
	list.innerHTML = '<div class="sp-loading"></div>';
	try {
		const params = reviewQueryParams();
		params.page = 1;
		if (force) params.refresh = 1;
		const res = await spAjax('site_pulse_get_reviews', params);
		if (!res.success) {
			list.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load reviews.')}</div>`;
			return;
		}
		spReviews = res.data.reviews || [];
		spReviewsCanManage = !!res.data.can_manage;
		spReviewsHasOlder = !!res.data.has_older;
		spReviewsHasMore = !!res.data.has_more;
		spReviewsMatched = res.data.matched != null ? res.data.matched : spReviews.length;
		if (res.data.totalReviewCount != null) spReviewsTotal = res.data.totalReviewCount;
		spReviewsLoaded = true;
		renderReviewsSummary(res.data);
		renderReviews();
		spUpdateLoadOlderBtn(res.data);
		spRenderReviewsCount(res.data);
		if (res.data.stale && res.data.error) spFlash('Showing saved reviews — ' + res.data.error);
	} catch (e) {
		list.innerHTML = '<div class="sp-empty">Error loading reviews.</div>';
	}
}

// Fetch and append the next page when the scroll sentinel comes into view.
async function loadMoreReviews() {
	if (spReviewsLoadingMore || !spReviewsHasMore) return;
	spReviewsLoadingMore = true;
	const sentinel = $('#sp-reviews-sentinel');
	if (sentinel) sentinel.innerHTML = '<div class="sp-loading sp-loading-inline"></div>';
	try {
		const params = reviewQueryParams();
		params.page = spReviewsPage + 1;
		const res = await spAjax('site_pulse_get_reviews', params);
		if (res.success) {
			const rows = res.data.reviews || [];
			spReviewsPage   += 1;
			spReviewsHasMore = !!res.data.has_more;
			if (res.data.matched != null) spReviewsMatched = res.data.matched;
			spReviews = spReviews.concat(rows);
			appendReviews(rows);
			spRenderReviewsCount(res.data);
		}
	} catch (e) { /* leave the sentinel; a later scroll retries */ }
	spReviewsLoadingMore = false;
}

// Re-arm the IntersectionObserver on the bottom sentinel (only while more pages remain).
function observeReviewsSentinel() {
	const sentinel = $('#sp-reviews-sentinel');
	if (!sentinel) return;
	if (!spReviewsIO) {
		spReviewsIO = new IntersectionObserver((entries) => {
			if (entries.some(e => e.isIntersecting)) loadMoreReviews();
		}, { rootMargin: '600px 0px' });
	}
	spReviewsIO.disconnect();
	if (spReviewsHasMore) spReviewsIO.observe(sentinel);
}

// Append a freshly-fetched page's cards just above the sentinel, then re-arm the observer.
function appendReviews(rows) {
	const sentinel = $('#sp-reviews-sentinel');
	const html = rows.map(renderReviewCard).join('');
	if (sentinel) { sentinel.innerHTML = ''; sentinel.insertAdjacentHTML('beforebegin', html); }
	else $('#sp-reviews-list')?.insertAdjacentHTML('beforeend', html);
	observeReviewsSentinel();
}

// Average + total, plus a live "Loaded so far" stat while older reviews remain. Rendered into the
// always-present summary box so the progress count shows even if the footer count element is absent.
function renderReviewsSummary(d) {
	const box = $('#sp-reviews-summary');
	if (!box) return;
	if (d) {
		if (d.averageRating != null) spReviewsAvg = d.averageRating;
		if (d.totalReviewCount != null) spReviewsTotal = d.totalReviewCount;
	}
	const avg    = spReviewsAvg ? Number(spReviewsAvg).toFixed(1) : '—';
	const total  = spReviewsTotal || spReviews.length;
	// Live load progress is shown by the footer "Showing X of Y" (it updates as you infinite-scroll),
	// so the summary just carries the average + total.
	box.innerHTML =
		`<div class="sp-reviews-stat"><span class="sp-reviews-stat-num">${esc(avg)}</span><span class="sp-reviews-stat-label">Average rating</span></div>` +
		`<div class="sp-reviews-stat"><span class="sp-reviews-stat-num">${esc(String(total))}</span><span class="sp-reviews-stat-label">Total reviews</span></div>`;
}

function starString(n) {
	n = Math.max(0, Math.min(5, parseInt(n, 10) || 0));
	return '★★★★★'.slice(0, n) + '☆☆☆☆☆'.slice(0, 5 - n);
}

function reviewMatchesFilters(r) {
	const sf = $('#sp-reviews-filter-stars')?.value || '';
	const rf = $('#sp-reviews-filter-reply')?.value || '';
	const pf = $('#sp-reviews-filter-platform')?.value || '';
	if (sf && String(r.starRating) !== sf) return false;
	if (rf === 'replied'   && !r.reply) return false;
	if (rf === 'unreplied' &&  r.reply) return false;
	if (pf && (r.source || 'google') !== pf) return false;
	// Note: restaurant / brand / time-range scoping is applied server-side (loadReviews re-fetches),
	// so the list already holds only the scoped set — only the star/reply/topic filters run here.
	// Clicking a topic stat card narrows the list to reviews mentioning that topic.
	if (spReviewTopicFilter) {
		const tags = Array.isArray(r.tags) ? r.tags : [];
		if (!tags.some(t => String(t.label || '').toLowerCase() === spReviewTopicFilter.toLowerCase())) return false;
	}
	return true;
}

// Is a review's createTime within the selected range? (30/90/365 days, year-to-date, or all.)
function reviewInRange(createTime, range) {
	if (range === 'all') return true;
	if (!createTime) return false;
	const d = new Date(createTime);
	if (isNaN(d)) return true;
	const now = new Date();
	if (range === 'ytd') return d.getFullYear() === now.getFullYear();
	const days = range === '30' ? 30 : range === '90' ? 90 : range === '365' ? 365 : 0;
	if (!days) return true;
	return (now - d) <= days * 86400000;
}

// The server has already applied every filter + paged the set, so render spReviews as-is and drop a
// sentinel at the bottom that the IntersectionObserver watches to pull the next page.
function renderReviews() {
	const list = $('#sp-reviews-list');
	if (!list) return;
	if (!spReviews.length) { list.innerHTML = '<div class="sp-empty">No reviews match this filter.</div>'; return; }
	list.innerHTML = spReviews.map(renderReviewCard).join('') + '<div id="sp-reviews-sentinel"></div>';
	observeReviewsSentinel();
}

// Multi-colour Google "G" — marks the source platform next to the star row.
const SP_GOOGLE_ICON =
	'<svg class="unique sp-review-platform-icon" viewBox="0 0 48 48" width="15" height="15" aria-hidden="true">' +
		'<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>' +
		'<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>' +
		'<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>' +
		'<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>' +
	'</svg>';

// Facebook recommendation badge (used in place of the Google icon on FB review cards).
const SP_FB_ICON =
	'<svg class="unique sp-review-platform-icon" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">' +
		'<path fill="#1877F2" d="M24 12c0-6.627-5.373-12-12-12S0 5.373 0 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078V12h3.047V9.356c0-3.007 1.792-4.668 4.533-4.668 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874V12h3.328l-.532 3.469h-2.796v8.385C19.612 22.954 24 17.99 24 12z"/>' +
	'</svg>';

// AI sentiment chips: each tag is { label, sentiment } → green (positive) / red (negative) / grey (neutral).
function renderReviewTags(tags) {
	if (!Array.isArray(tags) || !tags.length) return '';
	const chips = tags.map(t => {
		const s = String(t.sentiment || '').toLowerCase();
		const cls = s === 'negative' ? 'sp-review-tag-neg' : (s === 'neutral' ? 'sp-review-tag-neutral' : 'sp-review-tag-pos');
		const score = parseInt(t.score, 10);
		const badge = (score >= 1 && score <= 5) ? `<span class="unique sp-review-tag-score">${score}/5</span>` : '';
		return `<span class="unique sp-review-tag ${cls}" data-topic="${esc(t.label)}" role="button" tabindex="0" title="Filter to ${esc(t.label)}">${esc(t.label)}${badge}</span>`;
	}).join('');
	return `<div class="unique sp-review-tags">${chips}</div>`;
}

function renderReviewCard(r) {
	const id   = esc(r.reviewId);
	const isFb = (r.source === 'facebook'); // Facebook recommendations are read-only here (Meta has no owner-reply API for them)
	const date = r.createTime ? new Date(r.createTime).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';

	// Google serves a tiny default thumbnail (e.g. ...=s120-c-rp-mo-ba12-br100). Swap the trailing
	// size directive for a larger square crop so the avatar is crisp.
	const photoUrl = r.photo ? r.photo.replace(/=s\d+-c[\w-]*$/, '=s400-c') : '';
	const photo = photoUrl
		? `<img class="unique sp-review-avatar" src="${esc(photoUrl)}" alt="" referrerpolicy="no-referrer">`
		: '<div class="unique sp-review-avatar sp-review-avatar-blank"></div>';

	const reply = r.reply
		? `<div class="unique sp-review-reply"><span class="unique sp-review-reply-label">Owner reply${r.reply.by ? `<span class="unique sp-review-reply-to"> ▪ ${esc(r.reply.by)}</span>` : ''}</span><p class="unique">${esc(r.reply.comment)}</p></div>`
		: '';

	// Restaurant / location label — so a combined multi-location list shows which store each review is for.
	const store = r.store ? `<span class="unique sp-review-store">${esc(r.store)}</span>` : '';

	// Action buttons sit in the head's right column (under the stars); the reply form stays full-width below.
	// Facebook recommendations are read-only (no reply/testimonial actions) — you can't reply to them via the API.
	let actions = '', form = '';
	if (spReviewsCanManage && !isFb) {
		actions =
			'<div class="unique sp-review-actions">' +
				`<button type="button" class="unique sp-btn sp-btn-ghost sp-review-reply-btn" data-id="${id}">${r.reply ? 'Edit reply' : 'Reply'}</button>` +
				(r.imported
					? '<span class="unique sp-review-imported">✓ Testimonial</span>'
					: `<button type="button" class="unique sp-btn sp-btn-ghost sp-review-import-btn" data-id="${id}">Add as testimonial</button>`) +
			'</div>';
		form =
			`<div class="unique sp-review-reply-form ${r.reply ? 'sp-review-reply-edit' : 'sp-review-reply-new'}" data-id="${id}" hidden>` +
				`<textarea class="unique sp-input sp-review-reply-input" rows="3" placeholder="Write a public reply…">${r.reply ? esc(r.reply.comment) : ''}</textarea>` +
				'<div class="unique sp-review-reply-formbtns">' +
					`<button type="button" class="unique sp-btn sp-btn-primary sp-review-reply-save" data-id="${id}">Post reply</button>` +
					`<button type="button" class="unique sp-btn sp-btn-ghost sp-review-reply-regen" data-id="${id}">Regenerate</button>` +
					`<button type="button" class="unique sp-btn sp-btn-ghost sp-review-reply-cancel" data-id="${id}">Cancel</button>` +
					(r.reply ? `<button type="button" class="unique sp-btn sp-btn-ghost sp-review-reply-delete" data-id="${id}">Delete reply</button>` : '') +
				'</div>' +
			'</div>';
	}

	const tags = renderReviewTags(r.tags);
	// Reviewer edited their review after posting (updateTime meaningfully later than createTime) → flag it.
	// 60s tolerance avoids false positives from Google's sub-minute create/update timestamp jitter.
	let edited = false;
	if (r.updateTime && r.createTime) {
		const c = new Date(r.createTime).getTime(), u = new Date(r.updateTime).getTime();
		edited = isFinite(c) && isFinite(u) && (u - c) > 60000;
	}
	const editTag = edited ? '<span class="unique sp-review-edited" title="The reviewer edited this review after posting">EDIT:</span> ' : '';
	const body = r.comment
		? `<p class="unique sp-review-body">${editTag}${esc(r.comment)}</p>`
		: `<p class="unique sp-review-body sp-review-nobody">${editTag}(no comment)</p>`;

	return (
		`<div class="unique sp-card sp-review-card" data-id="${id}">` +
			'<div class="unique sp-review-head sp-card-body">' +
				photo +
				// Right block comes before the main column in source so the body can float-wrap around it
				// on small screens (≤1024px); desktop keeps the visual order via grid-template-areas.
				'<div class="unique sp-review-headright">' +
					`<span class="unique sp-review-rating">${isFb ? SP_FB_ICON : SP_GOOGLE_ICON}<span class="unique sp-review-stars" title="${parseInt(r.starRating, 10) || 0} of 5">${starString(r.starRating)}</span></span>` +
					store +
					tags +
					actions +
				'</div>' +
				'<div class="unique sp-review-headmain">' +
					'<div class="unique sp-review-topline">' +
						`<div class="unique sp-review-byline"><span class="unique sp-review-author">${esc(r.reviewer)}</span><span class="unique sp-review-date">${esc(date)}</span></div>` +
					'</div>' +
					body +
				'</div>' +
			'</div>' +
			reply +
			form +
		'</div>'
	);
}

function onReviewListClick(e) {
	// A sentiment chip filters the list to that topic — same effect as clicking the matching stat card.
	const tag = e.target.closest('.sp-review-tag');
	if (tag && tag.dataset.topic) {
		spReviewTopicFilter = (spReviewTopicFilter === tag.dataset.topic) ? '' : tag.dataset.topic;
		spMarkActiveStatCard();
		loadReviews(); // topic filter runs server-side now — re-query from page 1
		$('#sp-reviews-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
		return;
	}

	const btn = e.target.closest('button');
	if (!btn || !btn.dataset.id) return;
	const id   = btn.dataset.id;
	const form = $(`.sp-review-reply-form[data-id="${CSS.escape(id)}"]`);

	if (btn.classList.contains('sp-review-reply-btn')) {
		if (form) {
			form.hidden = !form.hidden;
			if (!form.hidden) {
				const ta = form.querySelector('textarea');
				ta?.focus();
				// Remember the starting text (existing reply or empty) so a later Regenerate can tell whether
				// the user edited the box vs. wants a from-scratch redraft.
				if (ta && ta.dataset.baseline === undefined) ta.dataset.baseline = ta.value;
				// Auto-draft an AI reply when opening on a review that has no reply yet.
				if (ta && !ta.value.trim()) generateReviewReply(id, form);
			}
		}
	} else if (btn.classList.contains('sp-review-reply-regen')) {
		generateReviewReply(id, form);
	} else if (btn.classList.contains('sp-review-reply-cancel')) {
		if (form) form.hidden = true;
	} else if (btn.classList.contains('sp-review-reply-save')) {
		const comment = form?.querySelector('textarea')?.value.trim() || '';
		if (!comment) { alert('Write a reply first.'); return; }
		submitReviewReply(id, comment, btn);
	} else if (btn.classList.contains('sp-review-reply-delete')) {
		if (confirm('Delete the public reply to this review?')) submitReviewReply(id, '', btn);
	} else if (btn.classList.contains('sp-review-import-btn')) {
		importReviewAsTestimonial(id, btn);
	}
}

// Swap em-dashes (—, with any spaces around them) for an ellipsis before a drafted reply hits the box.
function spReplaceEmDash(s) {
	return String( s == null ? '' : s ).replace(/\s*—\s*/g, '... ');
}

// Draft (or regenerate) an AI reply into the form's textarea, in the brand voice. Editable before posting.
async function generateReviewReply(id, form) {
	const ta    = form?.querySelector('textarea');
	const regen = form?.querySelector('.sp-review-reply-regen');
	const save  = form?.querySelector('.sp-review-reply-save');
	if (!ta) return;
	const prev = ta.value, ph = ta.placeholder;
	// If the box differs from the last draft/baseline, the user edited it — feed their version back in as
	// heavily-weighted guidance. Untouched → regenerate from scratch (baseline captured on open/last draft).
	if (ta.dataset.baseline === undefined) ta.dataset.baseline = prev;
	const guidance = (prev.trim() && prev.trim() !== (ta.dataset.baseline || '').trim()) ? prev : '';
	ta.disabled = true; ta.value = ''; ta.placeholder = guidance ? 'Reworking your draft…' : 'Drafting a reply…';
	if (regen) { regen.disabled = true; regen.textContent = 'Generating…'; }
	if (save) save.disabled = true;
	try {
		const res = await spAjax('site_pulse_generate_review_reply', { review_id: id, guidance });
		if (res.success && res.data.reply) { ta.value = spReplaceEmDash(res.data.reply); ta.dataset.baseline = ta.value; }
		else { ta.value = prev; spFlash(res.data?.message || 'Could not draft a reply.'); }
	} catch (e) {
		ta.value = prev;
		spFlash('Could not draft a reply.');
	}
	ta.disabled = false; ta.placeholder = ph;
	if (regen) { regen.disabled = false; regen.textContent = 'Regenerate'; }
	if (save) save.disabled = false;
	ta.focus();
}

async function submitReviewReply(id, comment, btn) {
	btn.disabled = true;
	try {
		const res = await spAjax('site_pulse_reply_review', { review_id: id, comment });
		if (!res.success) { alert(res.data?.message || 'Reply failed.'); btn.disabled = false; return; }
		const r = spReviews.find(x => x.reviewId === id);
		if (r) r.reply = res.data.reply;
		renderReviews();
		spFlash(comment === '' ? 'Reply deleted' : 'Reply posted');
	} catch (e) {
		alert('Reply failed.');
		btn.disabled = false;
	}
}

async function importReviewAsTestimonial(id, btn) {
	btn.disabled = true;
	btn.textContent = 'Adding…';
	try {
		const res = await spAjax('site_pulse_review_to_testimonial', { review_id: id });
		if (!res.success) {
			alert(res.data?.message || 'Could not create testimonial.');
			btn.disabled = false; btn.textContent = 'Add as testimonial';
			return;
		}
		const r = spReviews.find(x => x.reviewId === id);
		if (r) r.imported = true;
		renderReviews();
		spFlash('Testimonial created (draft)');
	} catch (e) {
		alert('Could not create testimonial.');
		btn.disabled = false; btn.textContent = 'Add as testimonial';
	}
}

/*--------------------------------------------------------------
# Client Reviews — agency view (hub only): all clients' Google reviews
--------------------------------------------------------------*/

let spAgencyClients = [];   // [{ site_key, label, site_url, averageRating, totalReviewCount, reviews:[], error }]
let spAgencyLoaded  = false;

function initAgencyReviews() {
	const panel = $('#sp-panel-agency-reviews');
	if (!panel) return;
	$('#sp-agency-reviews-refresh-btn')?.addEventListener('click', () => loadAgencyReviews(true));
	$('#sp-agency-filter-client')?.addEventListener('change', () => {
		// Keep the search box in sync when the dropdown is used directly.
		const sel = $('#sp-agency-filter-client'), inp = $('#sp-agency-client-search');
		if (inp) inp.value = sel.value ? (spAgencyClients.find(c => c.site_key === sel.value)?.label || '') : '';
		renderAgencyReviews();
	});
	$('#sp-agency-client-search')?.addEventListener('input', agencyClientSearch);
	$('#sp-agency-filter-stars')?.addEventListener('change', renderAgencyReviews);
	$('#sp-agency-filter-platform')?.addEventListener('change', renderAgencyReviews);
	$('#sp-agency-filter-reply')?.addEventListener('change', renderAgencyReviews);
	$('#sp-agency-filter-sort')?.addEventListener('change', renderAgencyReviews);
	$('#sp-agency-reviews-list')?.addEventListener('click', onAgencyReviewListClick);
	// FB photo chosen (via "Add photo" OR clicking the avatar): mark the button + preview it on the avatar.
	$('#sp-agency-reviews-list')?.addEventListener('change', (e) => {
		const f = e.target.closest('.sp-fb-photo');
		if (f && f.files && f.files[0]) {
			const card = f.closest('.sp-review-card');
			const t = f.closest('.sp-fb-photo-label')?.querySelector('.sp-fb-photo-text');
			if (t) t.textContent = '✓ Photo';
			const av = card?.querySelector('.sp-review-avatar');
			if (av) {
				const url = URL.createObjectURL(f.files[0]);
				av.style.backgroundImage = `url(${url})`;
				av.style.backgroundSize = 'cover';
				av.style.backgroundPosition = 'center';
				av.classList.remove('sp-review-avatar-blank');
			}
		}
	});
	// Two-way sync for FB cards: typing in the name box mirrors to the on-card name (and vice-versa), so
	// the card always shows exactly what will be posted.
	$('#sp-agency-reviews-list')?.addEventListener('input', (e) => {
		const box = e.target.closest('.sp-fb-name');
		if (box) {
			const a = box.closest('.sp-review-card')?.querySelector('.sp-review-author');
			if (a) a.textContent = box.value || 'Facebook user';
			return;
		}
		const auth = e.target.closest('.sp-review-author[contenteditable="true"]');
		if (auth) {
			const box2 = auth.closest('.sp-review-card')?.querySelector('.sp-fb-name');
			if (box2) box2.value = auth.textContent.trim();
		}
	});
	// Finish inline name editing: commit to the box and drop edit mode.
	$('#sp-agency-reviews-list')?.addEventListener('focusout', (e) => {
		const auth = e.target.closest('.sp-review-author[contenteditable="true"]');
		if (auth) {
			const box = auth.closest('.sp-review-card')?.querySelector('.sp-fb-name');
			if (box) box.value = auth.textContent.trim();
			auth.removeAttribute('contenteditable');
		}
	});
}

let _spAgencyRefreshing = false;

async function loadAgencyReviews(force = false) {
	const list = $('#sp-agency-reviews-list');
	if (!list) return;

	// First open loads the client list from CACHE — one cheap call, no live upstream pulls — so the
	// panel paints instantly. (Returning to an already-loaded panel just re-renders.)
	if (!spAgencyLoaded) {
		list.innerHTML = '<div class="sp-loading"></div>';
		try {
			const res = await spAjax('site_pulse_get_agency_reviews', {});
			if (!res.success) {
				list.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load client reviews.')}</div>`;
				return;
			}
			spAgencyClients = res.data.clients || [];
			spAgencyLoaded = true;
			populateAgencyClientFilter();
			renderAgencyReviews();
		} catch (e) {
			list.innerHTML = '<div class="sp-empty">Error loading client reviews.</div>';
			return;
		}
	} else if (!force) {
		renderAgencyReviews();
	}

	// Refresh re-pulls every client LIVE, one request each, so a single click covers everyone. (The old
	// all-at-once refresh could only reach a handful of companies before the server request timed out —
	// which is why so many showed blank.)
	if (force) await refreshAgencyClientsStepped();
}

// Walk every client, live-refreshing ONE per request. Each card updates the moment its fetch returns,
// with a running "Refreshing X/Y" on the button. A slow or broken client (e.g. a dead Facebook token)
// only slows itself — it can't starve the rest, which was the whole problem with the budgeted bulk pull.
async function refreshAgencyClientsStepped() {
	if (_spAgencyRefreshing) return;
	_spAgencyRefreshing = true;
	const btn   = $('#sp-agency-reviews-refresh-btn');
	const label = btn ? btn.textContent : '';
	if (btn) btn.disabled = true;

	const keys = spAgencyClients.map(c => c.site_key);
	let done = 0;
	const tick = () => { if (btn) btn.textContent = `Refreshing ${done}/${keys.length}…`; };
	tick();

	for (const key of keys) {
		try {
			const res = await spAjax('site_pulse_refresh_agency_client', { site_key: key });
			if (res.success && res.data.client) {
				const i = spAgencyClients.findIndex(c => c.site_key === key);
				if (i >= 0) spAgencyClients[i] = res.data.client; else spAgencyClients.push(res.data.client);
				renderAgencyReviews(false); // repaint with the newly-arrived reviews, keeping scroll position
			}
		} catch (e) { /* one client's failure can't stall the sweep */ }
		done++;
		tick();
	}

	if (btn) { btn.disabled = false; btn.textContent = label || 'Refresh'; }
	_spAgencyRefreshing = false;
	spFlash('Reviews refreshed');
}

// Type-to-search the client list: matches the typed company name (exact, or a unique partial) to a
// client and applies it as the filter. Empty clears back to All clients.
function agencyClientSearch() {
	const inp = $('#sp-agency-client-search'), sel = $('#sp-agency-filter-client');
	if (!inp || !sel) return;
	const q = inp.value.trim().toLowerCase();
	if (q === '') { if (sel.value) { sel.value = ''; renderAgencyReviews(); } return; }
	let hit = spAgencyClients.find(c => (c.label || '').toLowerCase() === q);
	if (!hit) {
		const m = spAgencyClients.filter(c => (c.label || '').toLowerCase().includes(q));
		if (m.length === 1) hit = m[0]; // a unique partial match selects it
	}
	if (hit && sel.value !== hit.site_key) { sel.value = hit.site_key; renderAgencyReviews(); }
}

function populateAgencyClientFilter() {
	const sel = $('#sp-agency-filter-client');
	if (!sel) return;
	const cur = sel.value;
	// Alphabetize by company name (copy so the underlying review order is untouched).
	const sorted = spAgencyClients.slice().sort((a, b) => (a.label || '').localeCompare(b.label || '', undefined, { sensitivity: 'base' }));
	sel.innerHTML = '<option value="">All clients</option>' +
		sorted.map(c => `<option value="${esc(c.site_key)}">${esc(c.label)}</option>`).join('');
	sel.value = cur;
	// Feed the type-to-search datalist with the same alphabetized company names.
	const dl = $('#sp-agency-client-list');
	if (dl) dl.innerHTML = sorted.map(c => `<option value="${esc(c.label)}"></option>`).join('');
}

// Client Reviews are all held in memory (spAgencyClients), so pagination here is client-side windowing:
// render a page of cards, then infinite-scroll to append more from the filtered+sorted set.
const SP_AGENCY_PAGE = 50;
let _spAgencyItems = [];
let _spAgencyShown = 0;
let _spAgencyIO = null;

// reset=true (filter/sort change) snaps back to the first page; reset=false (after a reply/push) keeps
// however many were already scrolled into view so the list doesn't jump back to the top.
function renderAgencyReviews(reset = true) {
	const list = $('#sp-agency-reviews-list');
	if (!list) return;
	if (reset !== false) reset = true; // change handlers pass an Event; treat anything non-false as reset
	const clientFilter = $('#sp-agency-filter-client')?.value || '';
	const sf = $('#sp-agency-filter-stars')?.value || '';
	const pf = $('#sp-agency-filter-platform')?.value || '';
	const rf = $('#sp-agency-filter-reply')?.value || '';

	if (!spAgencyClients.length) { list.innerHTML = '<div class="sp-empty">No clients mapped yet.</div>'; return; }

	const clients = spAgencyClients.filter(c => !clientFilter || c.site_key === clientFilter);

	// Surface client-level errors as notices so a failed pull isn't silently missing from the list.
	const notices = clients
		.filter(c => c.error || c.fb_error)
		.map(c => `<div class="sp-empty">${esc(c.label)}: ${esc(c.error || '')}${c.error && c.fb_error ? ' · ' : ''}${c.fb_error ? 'Facebook — ' + esc(c.fb_error) : ''}</div>`)
		.join('');

	// Flatten every (filtered) client's reviews into one list, tagging each with its client, then sort
	// across all companies (they interleave by date, which is intended).
	const items = [];
	clients.forEach(c => (c.reviews || []).forEach(r => {
		if (sf && String(r.starRating) !== sf) return;
		if (pf && (r.source || 'google') !== pf) return;
		if (rf === 'replied'   && !r.reply) return;
		if (rf === 'unreplied' &&  r.reply) return;
		items.push({ c, r });
	}));
	const sortOrder = $('#sp-agency-filter-sort')?.value || 'newest';
	items.sort((a, b) => {
		const ta = a.r.createTime ? Date.parse(a.r.createTime) : 0;
		const tb = b.r.createTime ? Date.parse(b.r.createTime) : 0;
		return sortOrder === 'oldest' ? (ta - tb) : (tb - ta);
	});

	_spAgencyItems = items;
	if (_spAgencyIO) _spAgencyIO.disconnect();

	if (!items.length) {
		const anyLoaded = spAgencyClients.some(c => (c.reviews || []).length);
		const msg = anyLoaded ? 'No reviews match this filter.' : 'No reviews loaded yet — click Refresh to pull the latest.';
		list.innerHTML = notices + `<div class="sp-empty">${msg}</div>`;
		_spAgencyShown = 0;
		return;
	}

	const keep = reset ? SP_AGENCY_PAGE : Math.max(SP_AGENCY_PAGE, _spAgencyShown);
	_spAgencyShown = Math.min(keep, items.length);
	list.innerHTML = notices + items.slice(0, _spAgencyShown).map(({ c, r }) => renderAgencyReviewCard(c, r)).join('');
	if (_spAgencyShown < items.length) {
		list.insertAdjacentHTML('beforeend', '<div id="sp-agency-sentinel"></div>');
		agencyObserveSentinel();
	}
}

// Append the next window of cards above the sentinel when it scrolls into view.
function agencyAppendPage() {
	const list = $('#sp-agency-reviews-list');
	if (!list || _spAgencyShown >= _spAgencyItems.length) return;
	const next = _spAgencyItems.slice(_spAgencyShown, _spAgencyShown + SP_AGENCY_PAGE);
	const html = next.map(({ c, r }) => renderAgencyReviewCard(c, r)).join('');
	const sentinel = $('#sp-agency-sentinel');
	if (sentinel) sentinel.insertAdjacentHTML('beforebegin', html);
	else list.insertAdjacentHTML('beforeend', html);
	_spAgencyShown += next.length;
	if (_spAgencyShown >= _spAgencyItems.length) { $('#sp-agency-sentinel')?.remove(); if (_spAgencyIO) _spAgencyIO.disconnect(); }
	else agencyObserveSentinel();
}

function agencyObserveSentinel() {
	const sentinel = $('#sp-agency-sentinel');
	if (!sentinel) return;
	if (!_spAgencyIO) {
		_spAgencyIO = new IntersectionObserver((entries) => {
			if (entries.some(e => e.isIntersecting)) agencyAppendPage();
		}, { rootMargin: '600px 0px' });
	}
	_spAgencyIO.disconnect();
	_spAgencyIO.observe(sentinel);
}

function renderAgencyReviewCard(c, r) {
	const id   = esc(r.reviewId);
	const sk   = esc(c.site_key);
	const date = r.createTime ? new Date(r.createTime).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
	const isFb = (r.source === 'facebook'); // FB recommendations: read-only here (no reply); posting comes in the next step

	const photoUrl = r.photo ? r.photo.replace(/=s\d+-c[\w-]*$/, '=s400-c') : '';
	const photo = photoUrl
		? `<img class="unique sp-review-avatar" src="${esc(photoUrl)}" alt="" referrerpolicy="no-referrer">`
		: '<div class="unique sp-review-avatar sp-review-avatar-blank"></div>';

	const reply = ( ! isFb && r.reply )
		? `<div class="unique sp-review-reply"><span class="unique sp-review-reply-label">Owner reply${r.reply.by ? `<span class="unique sp-review-reply-to"> ▪ ${esc(r.reply.by)}</span>` : ''}</span><p class="unique">${esc(r.reply.comment)}</p></div>`
		: '';

	// The client's company name sits in the store slot (under the stars), labeling which client this is.
	const store = `<span class="unique sp-review-store">${esc(c.label)}</span>`;

	// Two send buttons: "Post Review" omits the photo; "Post Review w/ Pic" includes it (only offered when
	// the reviewer actually has a photo). Once sent, both collapse to the "✓ Sent to site" badge.
	let pushBtns = '';
	if ( ! isFb && c.site_url ) {
		if ( r.imported ) {
			pushBtns = '<span class="unique sp-review-imported">✓ Sent to site</span>';
		} else {
			pushBtns = `<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-push-btn" data-site="${sk}" data-id="${id}" data-photo="0">Post Review</button>`;
			if ( r.photo ) pushBtns += `<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-push-btn" data-site="${sk}" data-id="${id}" data-photo="1">Post Review w/ Pic</button>`;
		}
	}

	// FB recommendations are anonymous: add the name (look it up on FB) + optionally a photo, then post to
	// the client site as a testimonial. No reply (read-only on FB). Posting removes it from this list.
	const actions = isFb
		? '<div class="unique sp-review-actions sp-fb-actions">' +
				`<input type="text" class="unique sp-input sp-fb-name" data-site="${sk}" data-id="${id}" placeholder="Customer Name">` +
				`<label class="unique sp-btn sp-btn-ghost sp-fb-photo-label"><span class="unique sp-fb-photo-text">Add photo</span><input type="file" class="sp-fb-photo" accept="image/*" data-site="${sk}" data-id="${id}" hidden></label>` +
				( c.site_url ? `<button type="button" class="unique sp-btn sp-btn-ghost sp-fb-post-btn" data-site="${sk}" data-id="${id}">Post to site</button>` : '' ) +
				`<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-dismiss-btn" data-site="${sk}" data-id="${id}">Remove</button>` +
			'</div>'
		: '<div class="unique sp-review-actions">' +
				`<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-draft-btn" data-site="${sk}" data-id="${id}">Draft Reply</button>` +
				`<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-generate-btn" data-site="${sk}" data-id="${id}">Generate Reply</button>` +
				pushBtns +
				`<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-dismiss-btn" data-site="${sk}" data-id="${id}">Remove</button>` +
			'</div>';

	const form = isFb ? '' :
		`<div class="unique sp-review-reply-form ${r.reply ? 'sp-review-reply-edit' : 'sp-review-reply-new'}" data-site="${sk}" data-id="${id}" hidden>` +
			`<textarea class="unique sp-input sp-review-reply-input" rows="3" placeholder="Write a public reply…">${r.reply ? esc(r.reply.comment) : ''}</textarea>` +
			'<div class="unique sp-review-reply-formbtns">' +
				`<button type="button" class="unique sp-btn sp-btn-primary sp-agency-reply-save" data-site="${sk}" data-id="${id}">Post reply</button>` +
				`<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-reply-regen" data-site="${sk}" data-id="${id}">Regenerate</button>` +
				`<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-reply-cancel" data-site="${sk}" data-id="${id}">Cancel</button>` +
				(r.reply ? `<button type="button" class="unique sp-btn sp-btn-ghost sp-agency-reply-delete" data-site="${sk}" data-id="${id}">Delete reply</button>` : '') +
			'</div>' +
		'</div>';

	const tags = renderReviewTags(r.tags);
	const body = r.comment ? `<p class="unique sp-review-body">${esc(r.comment)}</p>` : '<p class="unique sp-review-body sp-review-nobody">(no comment)</p>';

	return (
		`<div class="unique sp-card sp-review-card" data-site="${sk}" data-id="${id}">` +
			'<div class="unique sp-review-head sp-card-body">' +
				photo +
				'<div class="unique sp-review-headright">' +
					`<span class="unique sp-review-rating">${isFb ? SP_FB_ICON : SP_GOOGLE_ICON}<span class="unique sp-review-stars" title="${parseInt(r.starRating, 10) || 0} of 5">${starString(r.starRating)}</span></span>` +
					store +
					tags +
					actions +
				'</div>' +
				'<div class="unique sp-review-headmain">' +
					'<div class="unique sp-review-topline">' +
						`<div class="unique sp-review-byline"><span class="unique sp-review-author">${esc(r.reviewer)}</span><span class="unique sp-review-date">${esc(date)}</span></div>` +
					'</div>' +
					body +
				'</div>' +
			'</div>' +
			reply +
			form +
		'</div>'
	);
}

function agencyFindReview(site, id) {
	const c = spAgencyClients.find(x => x.site_key === site);
	if (!c) return [null, null];
	return [c, (c.reviews || []).find(r => String(r.reviewId) === String(id)) || null];
}

function onAgencyReviewListClick(e) {
	// FB cards: the avatar is a photo upload, and the name is click-to-edit inline — both mirror the
	// fields used by "Post to site". (FB cards are the ones carrying a .sp-fb-name input.)
	const fbCard = e.target.closest('.sp-review-card');
	if (fbCard && fbCard.querySelector('.sp-fb-name')) {
		if (e.target.closest('.sp-review-avatar')) { fbCard.querySelector('.sp-fb-photo')?.click(); return; }
		const auth = e.target.closest('.sp-review-author');
		if (auth && auth.getAttribute('contenteditable') !== 'true') {
			auth.setAttribute('contenteditable', 'true');
			auth.focus();
			const range = document.createRange(); range.selectNodeContents(auth); range.collapse(false);
			const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
			return;
		}
	}

	const btn = e.target.closest('button');
	if (!btn || !btn.dataset.id) return;
	const site = btn.dataset.site;
	const id   = btn.dataset.id;
	const form = $(`.sp-review-reply-form[data-site="${CSS.escape(site)}"][data-id="${CSS.escape(id)}"]`);

	if (btn.classList.contains('sp-agency-generate-btn')) {
		if (form) { form.hidden = false; generateAgencyReply(site, id, form); }       // AI draft
	} else if (btn.classList.contains('sp-agency-draft-btn')) {
		if (form) { form.hidden = false; draftCannedReply(site, id, form); }          // canned, no AI
	} else if (btn.classList.contains('sp-agency-reply-regen')) {
		generateAgencyReply(site, id, form);
	} else if (btn.classList.contains('sp-agency-reply-cancel')) {
		if (form) form.hidden = true;
	} else if (btn.classList.contains('sp-agency-reply-save')) {
		const comment = form?.querySelector('textarea')?.value.trim() || '';
		if (!comment) { alert('Write a reply first.'); return; }
		submitAgencyReply(site, id, comment, btn);
	} else if (btn.classList.contains('sp-agency-reply-delete')) {
		if (confirm('Delete the public reply to this review?')) submitAgencyReply(site, id, '', btn);
	} else if (btn.classList.contains('sp-agency-push-btn')) {
		pushAgencyTestimonial(site, id, btn, btn.dataset.photo === '1');
	} else if (btn.classList.contains('sp-fb-post-btn')) {
		postAgencyFbTestimonial(site, id, btn);
	} else if (btn.classList.contains('sp-agency-dismiss-btn')) {
		dismissAgencyReview(site, id, btn);
	}
}

// Read a File as a base64 data URL (for the manual FB profile photo upload).
function spFileToDataURL(file) {
	return new Promise((resolve, reject) => {
		const fr = new FileReader();
		fr.onload = () => resolve(fr.result);
		fr.onerror = reject;
		fr.readAsDataURL(file);
	});
}

// Post a Facebook recommendation to the client site as a testimonial, with the manually-added name +
// (optional) uploaded photo. Transient list → on success it's removed (like Google once handled).
async function postAgencyFbTestimonial(site, id, btn) {
	const card = btn.closest('.sp-review-card');
	const name = card?.querySelector('.sp-fb-name')?.value.trim() || '';
	const file = card?.querySelector('.sp-fb-photo')?.files?.[0] || null;
	if (!name && !confirm('No customer name entered — post without a name?')) return;
	const label = btn.textContent;
	btn.disabled = true; btn.textContent = 'Sending…';
	try {
		const payload = { site_key: site, review_id: id, reviewer: name, with_photo: file ? 1 : 0 };
		if (file) payload.photo_data = await spFileToDataURL(file);
		const res = await spAjax('site_pulse_agency_push_testimonial', payload);
		if (!res.success) { alert(res.data?.message || 'Could not send to site.'); btn.disabled = false; btn.textContent = label; return; }
		const p = res.data.photo;
		const extra = p === 'set' ? ' — photo added' : (p && p !== 'none' ? ' — photo: ' + p : '');
		const plat = res.data.platform ? ' as ' + res.data.platform : '';
		dismissAgencyReview(site, id, null, 'Posted to client site' + plat + extra + ' — removed');
	} catch (e) { alert('Could not send to site.'); btn.disabled = false; btn.textContent = label; }
}

// Permanently remove a handled review from the Client Reviews list — it's recorded on the hub so it
// stays gone on every future pull (these aren't kept for analysis like Rovin's, so this keeps the list lean).
async function dismissAgencyReview(site, id, btn, flashMsg) {
	if (btn) btn.disabled = true;
	try {
		const res = await spAjax('site_pulse_agency_dismiss_review', { site_key: site, review_id: id });
		if (!res.success) { spFlash(res.data?.message || 'Could not remove.'); if (btn) btn.disabled = false; return; }
		const c = spAgencyClients.find(x => x.site_key === site);
		if (c) c.reviews = (c.reviews || []).filter(r => String(r.reviewId) !== String(id));
		renderAgencyReviews(false);
		spFlash(flashMsg || 'Review removed');
	} catch (e) {
		spFlash('Could not remove.');
		if (btn) btn.disabled = false;
	}
}

// Once a review is BOTH replied to and pushed to the client site, it's fully handled — remove it.
function agencyMaybeAutoRemove(site, id, flashMsg) {
	const [, r] = agencyFindReview(site, id);
	if (r && r.reply && r.imported) { dismissAgencyReview(site, id, null, flashMsg); return true; }
	return false;
}

// Draft (or regenerate) an AI reply into the agency form's textarea. Editable before posting.
async function generateAgencyReply(site, id, form) {
	const ta    = form?.querySelector('textarea');
	const regen = form?.querySelector('.sp-agency-reply-regen');
	const save  = form?.querySelector('.sp-agency-reply-save');
	if (!ta) return;
	const prev = ta.value, ph = ta.placeholder;
	// Edited box (vs. the last draft/baseline) → feed it back as heavily-weighted guidance; untouched → from
	// scratch. The first draft captures the baseline, so the initial "Generate Reply" is always from scratch.
	if (ta.dataset.baseline === undefined) ta.dataset.baseline = prev;
	const guidance = (prev.trim() && prev.trim() !== (ta.dataset.baseline || '').trim()) ? prev : '';
	ta.disabled = true; ta.value = ''; ta.placeholder = guidance ? 'Reworking your draft…' : 'Drafting a reply…';
	if (regen) { regen.disabled = true; regen.textContent = 'Generating…'; }
	if (save) save.disabled = true;
	try {
		// Pass the displayed review as a fallback so drafting works even if the server cache has rotated.
		const [, rev] = agencyFindReview(site, id);
		const res = await spAjax('site_pulse_agency_generate_reply', {
			site_key: site,
			review_id: id,
			reviewer: rev?.reviewer || '',
			comment: rev?.comment || '',
			star_rating: rev?.starRating || 0,
			guidance,
		});
		if (res.success && res.data.reply) { ta.value = spReplaceEmDash(res.data.reply); ta.dataset.baseline = ta.value; }
		else { ta.value = prev; spFlash(res.data?.message || 'Could not draft a reply.'); }
	} catch (e) {
		ta.value = prev;
		spFlash('Could not draft a reply.');
	}
	ta.disabled = false; ta.placeholder = ph;
	if (regen) { regen.disabled = false; regen.textContent = 'Regenerate'; }
	if (save) save.disabled = false;
	ta.focus();
}

// "Draft Reply" — skip the AI and drop a random canned thank-you into the box (instant, no tokens).
const AGENCY_CANNED_REPLIES = [
	'Thank you!',
	'Thank you for the review!',
	'We appreciate the review!',
	'Thanks for taking the time to review our business!',
	'We appreciate your business!',
	'Thank you for your business!',
	'Thanks!',
];
function draftCannedReply(site, id, form) {
	const ta = form?.querySelector('textarea');
	if (!ta) return;
	const [, rev] = agencyFindReview(site, id);
	const pool = AGENCY_CANNED_REPLIES.slice();
	if (rev && parseInt(rev.starRating, 10) === 5) pool.push('Thanks for the 5 stars!'); // only when truly 5 stars
	ta.value = pool[Math.floor(Math.random() * pool.length)];
	ta.dataset.baseline = ta.value; // so a later Regenerate treats edits to this canned line as guidance
	ta.focus();
}

async function submitAgencyReply(site, id, comment, btn) {
	btn.disabled = true;
	try {
		const res = await spAjax('site_pulse_agency_reply_review', { site_key: site, review_id: id, comment });
		if (!res.success) { alert(res.data?.message || 'Reply failed.'); btn.disabled = false; return; }
		const [, r] = agencyFindReview(site, id);
		if (r) r.reply = res.data.reply;
		// If this review is now both replied AND already on the client site, it's done — auto-remove.
		if (comment !== '' && agencyMaybeAutoRemove(site, id, 'Reply posted — removed (replied + on site)')) {
			// handled (re-rendered + flashed by the dismiss)
		} else {
			renderAgencyReviews(false);
			spFlash(comment === '' ? 'Reply deleted' : 'Reply posted');
		}
	} catch (e) {
		alert('Reply failed.');
		btn.disabled = false;
	}
}

async function pushAgencyTestimonial(site, id, btn, withPhoto) {
	const label = btn.textContent;
	btn.disabled = true;
	btn.textContent = 'Sending…';
	try {
		const res = await spAjax('site_pulse_agency_push_testimonial', { site_key: site, review_id: id, with_photo: withPhoto ? 1 : 0 });
		if (!res.success) {
			alert(res.data?.message || 'Could not send to site.');
			btn.disabled = false; btn.textContent = label;
			return;
		}
		const [, r] = agencyFindReview(site, id);
		if (r) r.imported = true;
		const p = res.data.photo;
		let extra = '';
		if (p === 'set') extra = ' — photo added';
		else if (p === 'skipped-monogram') extra = ' — no photo (default avatar)';
		else if (p === 'exists') extra = ' — kept existing photo';
		else if (typeof p === 'string' && p.indexOf('error') === 0) extra = ' — photo upload failed';
		else if (typeof p === 'string' && p.indexOf('skipped') === 0) extra = ' — no photo';
		else if (p == null) extra = ' — photo not processed (update the client site)';
		const base = res.data.already_imported ? 'Already on the client site' : 'Published to client site';
		// If this review already has a reply too, it's fully handled — auto-remove it.
		if (!agencyMaybeAutoRemove(site, id, base + extra + ' — removed (replied + on site)')) {
			renderAgencyReviews(false);
			spFlash(base + extra);
		}
	} catch (e) {
		alert('Could not send to site.');
		btn.disabled = false; btn.textContent = label;
	}
}

/*--------------------------------------------------------------
# Customer Surveys (forwarded in from the public restaurant sites)
--------------------------------------------------------------*/

let spSurveys        = [];
let spSurveyDims     = {};   // canonical dimension key => label (display order)
let spSurveyCanManage = false;
let spSurveyCanDelete = false; // delete requires the delete_surveys cap (or god)
let spSurveyViewArchived = false; // viewing the archived list vs the active one
let spSurveyArchivedCount = 0;
let _spSurveyWired    = false;

function initSurveys() {
	const panel = $('#sp-panel-surveys');
	if (!panel) return;
	if (!_spSurveyWired) {
		$('#sp-survey-refresh-btn')?.addEventListener('click', () => loadSurveys());
		$('#sp-survey-filter-location')?.addEventListener('change', () => loadSurveys());
		$('#sp-survey-filter-range')?.addEventListener('change', () => loadSurveys());
		// Source (Web / Imported) is a client-side view filter — the value is already on each card, so
		// just re-render (no refetch).
		$('#sp-survey-filter-source')?.addEventListener('change', () => renderSurveys());
		$('#sp-survey-archive-toggle')?.addEventListener('click', () => { spSurveyViewArchived = !spSurveyViewArchived; loadSurveys(); });
		let _surveySearchT;
		$('#sp-survey-search')?.addEventListener('input', () => { clearTimeout(_surveySearchT); _surveySearchT = setTimeout(() => loadSurveys(), 300); });
		$('#sp-survey-list')?.addEventListener('click', onSurveyListClick);
		_spSurveyWired = true;
	}
}

// Translate the range <select> into a {start,end} YYYY-MM-DD pair ('' = no bound).
function spSurveyRange() {
	const v = $('#sp-survey-filter-range')?.value || '';
	if (!v) return { start: '', end: '' };
	const today = new Date();
	let start;
	if (v === 'ytd') {
		start = new Date(today.getFullYear(), 0, 1);
	} else {
		start = new Date();
		start.setDate(start.getDate() - parseInt(v, 10));
	}
	const fmt = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
	return { start: fmt(start), end: fmt(today) };
}

async function loadSurveys() {
	const list = $('#sp-survey-list');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';

	const { start, end } = spSurveyRange();
	const location = $('#sp-survey-filter-location')?.value || '';
	const search = $('#sp-survey-search')?.value.trim() || '';

	try {
		const res = await spAjax('site_pulse_get_surveys', { start, end, location, search, archived: spSurveyViewArchived ? 1 : 0 });
		if (!res.success) {
			list.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load surveys.')}</div>`;
			return;
		}
		spSurveys        = res.data.surveys || [];
		spSurveyDims     = res.data.dimensions || {};
		spSurveyCanManage = !!res.data.can_manage;
		spSurveyCanDelete    = !!res.data.can_delete;
		spSurveyArchivedCount = res.data.archived_count || 0;
		updateSurveyArchiveToggle();
		populateSurveyLocations(res.data.locations || []);
		renderSurveysSummary(res.data.summary);
		renderSurveys();
	} catch (e) {
		list.innerHTML = '<div class="sp-empty">Error loading surveys.</div>';
	}
}

// Toggle button label reflects which list you're on + how many archived exist.
function updateSurveyArchiveToggle() {
	const btn = $('#sp-survey-archive-toggle');
	if (!btn) return;
	btn.textContent = spSurveyViewArchived
		? '← Back to Active Comment Cards'
		: `View Archived Comment Cards${spSurveyArchivedCount ? ' (' + spSurveyArchivedCount + ')' : ''}`;
}

// Keep the location dropdown in sync with the locations actually present (across the whole
// table, so a location filter never hides the other options). Preserves the current pick.
function populateSurveyLocations(locs) {
	const sel = $('#sp-survey-filter-location');
	if (!sel) return;
	const cur = sel.value;
	sel.innerHTML = '<option value="">All locations</option>' +
		locs.map(l => `<option value="${esc(l)}">${esc(l)}</option>`).join('');
	if (cur && locs.indexOf(cur) !== -1) sel.value = cur;
}

// 5 = green, 4 = yellow, 1-3 = red — matches the legacy survey-email coloring.
function spRateClass(n) {
	n = parseInt(n, 10) || 0;
	return n >= 5 ? 'sp-rate-good' : (n === 4 ? 'sp-rate-ok' : 'sp-rate-bad');
}

function spSurveyDimLabel(k) {
	if (spSurveyDims[k]) return spSurveyDims[k];
	return k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function renderSurveysSummary(s) {
	const box = $('#sp-survey-summary');
	if (!box) return;
	_spSurveySummaryData = s;   // remember for re-render when the chart style is toggled
	if (!s) { box.innerHTML = ''; return; }

	const dists = s.dim_distributions || {};
	const da    = s.dim_averages || {};

	let cards  = surveyCountCard(s.count);
	cards     += surveyStatCard('Avg overall', s.avg_overall, s.overall_dist);
	Object.keys(spSurveyDims).forEach(k => {
		if (da[k] != null) cards += surveyStatCard(spSurveyDimLabel(k), da[k], dists[k]);
	});

	box.innerHTML = spChartToggle() + `<div class="sp-survey-stat-grid">${cards}</div>`;
}

// Plain count tile — left-justified label + value, no bars.
function surveyCountCard(count) {
	return `<div class="sp-tile sp-feedback-stat-tile">` +
		`<div class="sp-survey-stat-left"><div class="sp-survey-stat-label">Comment Cards</div><div class="sp-survey-stat-value">${esc(String(count))}</div></div>` +
	`</div>`;
}

// Rating tile — left-justified label + average, with an Amazon-style distribution bar chart on the right.
function surveyStatCard(label, avg, dist) {
	const value = (avg != null) ? Number(avg).toFixed(1) : '—';
	return `<div class="sp-tile sp-feedback-stat-tile">` +
		`<div class="sp-survey-stat-left"><div class="sp-survey-stat-label">${esc(label)}</div><div class="sp-survey-stat-value">${esc(value)}</div></div>` +
		surveyBars(dist) +
	`</div>`;
}

// 5→1 star distribution for survey stat cards — bar or pie per the user's choice.
function surveyBars(dist) {
	return spDistChart(spStarSegments(dist));
}

function renderSurveys() {
	const list = $('#sp-survey-list');
	if (!list) return;
	// Client-side Source filter (Web / Imported); '' = all. Source is 'imported' on cards without a
	// web submission fingerprint, else 'web'.
	const src = $('#sp-survey-filter-source')?.value || '';
	const items = src ? spSurveys.filter(s => (s.source === 'imported' ? 'imported' : 'web') === src) : spSurveys;
	list.innerHTML = items.length
		? items.map(renderSurveyCard).join('')
		: '<div class="sp-empty">No comment cards in this view.</div>';
}

function renderSurveyCard(s) {
	const raw  = s.visit_date || (s.created_at ? s.created_at.replace(' ', 'T') : '');
	const dObj = s.visit_date ? new Date(s.visit_date + 'T00:00:00') : (raw ? new Date(raw) : null);
	const date = dObj && !isNaN(dObj) ? dObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';

	const ratings = s.ratings || {};
	// Canonical dimensions first (in defined order), then any brand-specific extras.
	const keys = Object.keys(spSurveyDims).filter(k => ratings[k] != null);
	Object.keys(ratings).forEach(k => { if (keys.indexOf(k) === -1) keys.push(k); });

	const rows = keys.map(k =>
		`<div class="sp-survey-rate-row"><span class="unique sp-survey-rate-label">${esc(spSurveyDimLabel(k))}</span>` +
		`<span class="unique sp-survey-rate-val ${spRateClass(ratings[k])}">${parseInt(ratings[k], 10) || 0}</span></div>`
	).join('');

	const tags      = [s.experience, s.referral].filter(Boolean).map(esc).join(' · ');
	const cityState = [s.city, s.state].filter(Boolean).map(esc).join(', ');
	const phoneHref = s.phone ? s.phone.replace(/[^\d+]/g, '') : '';

	// Person block (bottom): name, email (mailto), phone (tel), City, State.
	const person =
		`<div class="sp-survey-name">${esc(s.name || 'Anonymous')}</div>` +
		(s.email ? `<div class="sp-survey-contact-line"><a href="mailto:${esc(s.email)}">${esc(s.email)}</a></div>` : '') +
		(s.phone ? `<div class="sp-survey-contact-line"><a href="tel:${esc(phoneHref)}">${esc(s.phone)}</a></div>` : '') +
		(cityState ? `<div class="sp-survey-contact-line">${cityState}</div>` : '');

	// Archive (text) shows for everyone who can view; Edit (pencil) for managers; Delete (trash) god-only.
	const archiveBtn = `<button type="button" class="unique sp-btn sp-btn-ghost sp-survey-archive-btn" data-id="${s.id}">${spSurveyViewArchived ? 'Restore' : 'Archive'}</button>`;
	const editBtn = (spSurveyCanManage || spSurveyCanDelete) ? iconBtn('edit', 'sp-survey-edit-btn', `data-id="${s.id}"`) : '';
	const del = spSurveyCanDelete ? iconBtn('delete', 'sp-survey-del-btn', `data-id="${s.id}"`) : '';

	// How the card arrived: forwarded from a public survey form ("Web") vs digitized from a photo/
	// upload ("Imported"). Server sends s.source = 'web' | 'imported'.
	const imported = s.source === 'imported';
	const sourcePill = `<span class="sp-survey-source sp-survey-source-${imported ? 'imported' : 'web'}">${imported ? 'Imported' : 'Web'}</span>`;

	return (
		`<div class="sp-card-sm sp-survey-card" data-id="${s.id}">` +
			'<div class="sp-survey-top">' +
				(s.location ? `<div class="sp-survey-loc">${esc(s.location)}</div>` : '') +
				(date ? `<div class="sp-survey-date">${esc(date)}</div>` : '') +
				sourcePill +
				(tags ? `<div class="sp-survey-tags">${tags}</div>` : '') +
			'</div>' +
			`<div class="sp-survey-rates">${rows}</div>` +
			(s.comments ? `<p class="sp-survey-comments">${esc(s.comments)}</p>` : '') +
			`<div class="sp-survey-person">${person}</div>` +
			`<div class="sp-survey-actions">${archiveBtn}${editBtn}${del}</div>` +
		'</div>'
	);
}

function onSurveyListClick(e) {
	const btn = e.target.closest('button');
	if (!btn || !btn.dataset.id) return;
	if (btn.classList.contains('sp-survey-archive-btn')) {
		archiveSurvey(btn.dataset.id, btn);
	} else if (btn.classList.contains('sp-survey-edit-btn')) {
		const s = spSurveys.find(x => String(x.id) === String(btn.dataset.id));
		if (s) spEditSurvey(s);
	} else if (btn.classList.contains('sp-survey-del-btn')) {
		if (confirm('Delete this survey? This cannot be undone.')) deleteSurvey(btn.dataset.id, btn);
	}
}

// Archive (active view) or restore (archived view). Either way the row leaves the current list
// so the screen stays uncluttered; the toggle count updates.
async function archiveSurvey(id, btn) {
	btn.disabled = true;
	const target = spSurveyViewArchived ? 0 : 1; // archived view → restore; active view → archive
	try {
		const res = await spAjax('site_pulse_archive_survey', { id, archived: target });
		if (!res.success) { alert(res.data?.message || 'Action failed.'); btn.disabled = false; return; }
		spSurveys = spSurveys.filter(s => String(s.id) !== String(id));
		spSurveyArchivedCount = Math.max(0, spSurveyArchivedCount + (target ? 1 : -1));
		renderSurveys();
		updateSurveyArchiveToggle();
		spFlash(target ? 'Survey archived' : 'Survey restored');
	} catch (e) {
		alert('Action failed.');
		btn.disabled = false;
	}
}

async function deleteSurvey(id, btn) {
	btn.disabled = true;
	try {
		const res = await spAjax('site_pulse_delete_survey', { id });
		if (!res.success) { alert(res.data?.message || 'Delete failed.'); btn.disabled = false; return; }
		spSurveys = spSurveys.filter(s => String(s.id) !== String(id));
		renderSurveys();
		spFlash('Survey deleted');
	} catch (e) {
		alert('Delete failed.');
		btn.disabled = false;
	}
}

// Edit a comment card — fix a mis-read import or update contact/location/ratings. Manager (or god) only.
function spEditSurvey(s) {
	const backdrop = spFormModal();
	const ratings = s.ratings || {};

	// Canonical dimensions first (defined order), then any extra keys the card actually carries.
	const dimKeys = Object.keys(spSurveyDims).slice();
	Object.keys(ratings).forEach(k => { if (dimKeys.indexOf(k) === -1) dimKeys.push(k); });

	const ratingSel = (k) => {
		const cur = parseInt(ratings[k], 10) || 0;
		let opts = `<option value=""${cur ? '' : ' selected'}>—</option>`;
		for (let n = 5; n >= 1; n--) opts += `<option value="${n}"${cur === n ? ' selected' : ''}>${n}</option>`;
		return `<label class="sp-survey-edit-rate"><span>${esc(spSurveyDimLabel(k))}</span><select class="sp-select sp-sve-rating" data-key="${esc(k)}">${opts}</select></label>`;
	};

	const field = (label, cls, val, type = 'text') =>
		`<div><label class="sp-field-label">${label}</label><input type="${type}" class="sp-input ${cls}" value="${esc(val || '')}"></div>`;

	let html = '<div class="sp-card sp-report-form-wrap">';
	html += '<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>Edit Comment Card</h3><button type="button" class="unique sp-btn sp-btn-ghost sp-sve-cancel">Cancel</button></div>';
	html += '<div class="sp-aai-grid">';
	html += field('Location', 'sp-sve-location', s.location);
	html += field('Visit date', 'sp-sve-visit', (s.visit_date || '').slice(0, 10), 'date');
	html += field('Customer name', 'sp-sve-name', s.name);
	html += field('Email', 'sp-sve-email', s.email, 'email');
	html += field('Phone', 'sp-sve-phone', s.phone);
	html += field('City', 'sp-sve-city', s.city);
	html += field('State', 'sp-sve-state', s.state);
	html += field('Experience', 'sp-sve-experience', s.experience);
	html += field('Referral', 'sp-sve-referral', s.referral);
	html += '</div>';
	html += '<label class="sp-field-label">Comments</label>';
	html += `<textarea class="sp-input sp-sve-comments" rows="3">${esc(s.comments || '')}</textarea>`;
	html += '<label class="sp-field-label">Ratings</label>';
	html += '<div class="sp-survey-edit-rates">' + dimKeys.map(ratingSel).join('') + '</div>';
	html += '<div class="sp-report-form-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary sp-sve-save">Save</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-sve-cancel">Cancel</button>';
	html += '</div>';
	html += '</div>';
	backdrop.innerHTML = html;
	markUniqueSpans(backdrop);

	$$('.sp-sve-cancel', backdrop).forEach(b => b.addEventListener('click', () => closeFormModal()));
	$('.sp-sve-save', backdrop)?.addEventListener('click', async () => {
		const ratingsOut = {};
		$$('.sp-sve-rating', backdrop).forEach(sel => { const n = parseInt(sel.value, 10); if (n >= 1 && n <= 5) ratingsOut[sel.dataset.key] = n; });
		const payload = {
			id:         s.id,
			location:   $('.sp-sve-location', backdrop)?.value || '',
			visit_date: $('.sp-sve-visit', backdrop)?.value || '',
			name:       $('.sp-sve-name', backdrop)?.value || '',
			email:      $('.sp-sve-email', backdrop)?.value || '',
			phone:      $('.sp-sve-phone', backdrop)?.value || '',
			city:       $('.sp-sve-city', backdrop)?.value || '',
			state:      $('.sp-sve-state', backdrop)?.value || '',
			experience: $('.sp-sve-experience', backdrop)?.value || '',
			referral:   $('.sp-sve-referral', backdrop)?.value || '',
			comments:   $('.sp-sve-comments', backdrop)?.value || '',
			ratings:    JSON.stringify(ratingsOut),
		};
		const save = $('.sp-sve-save', backdrop);
		if (save) save.disabled = true;
		try {
			const res = await spAjax('site_pulse_update_survey', payload);
			if (res.success) { closeFormModal(); loadSurveys(); spFlash('Comment card updated'); }
			else { alert(res.data?.message || 'Could not save.'); if (save) save.disabled = false; }
		} catch (e) { alert('Could not save.'); if (save) save.disabled = false; }
	});
}


/*--------------------------------------------------------------
# Import Past Reports (GOD-only) — PDF/CSV → AI parse → review → save
--------------------------------------------------------------*/

let spImportMeta   = null;   // {gm_template_id, supervisor_template_id, fields, users(with type), locations, has_api_key}
let spImportRows   = [];     // [{fileName, status, data, userId, type, locationId, periodStart, periodEnd, include, error}]
let _spImportWired = false;

async function initImportReports() {
	const panel = $('#sp-panel-import-reports');
	if (!panel) return;

	if (!_spImportWired) {
		$('#sp-import-pick')?.addEventListener('click', () => $('#sp-import-files')?.click());
		$('#sp-import-files')?.addEventListener('change', e => { handleImportFiles(e.target.files); e.target.value = ''; });
		$('#sp-import-save-all')?.addEventListener('click', saveAllImports);
		$('#sp-import-list')?.addEventListener('change', onImportListChange);
		$('#sp-import-list')?.addEventListener('click', onImportListClick);

		const drop = $('#sp-import-drop');
		if (drop) {
			['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-drag'); }));
			['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('is-drag'); }));
			drop.addEventListener('drop', e => { if (e.dataTransfer && e.dataTransfer.files.length) handleImportFiles(e.dataTransfer.files); });
		}
		_spImportWired = true;
	}

	if (!spImportMeta) await spLoadImportMeta();
}

async function spLoadImportMeta() {
	const res = await spAjax('site_pulse_import_meta', {});
	if (!res.success) {
		const l = $('#sp-import-list');
		if (l) l.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load.')}</div>`;
		return;
	}
	spImportMeta = res.data;
	if (!spImportMeta.has_api_key) spFlash('No Claude API key set (Settings → API Keys) — parsing will fail until it is.');
}

// Role-based report type for a user id, via the meta user list ('supervisor' | 'gm').
function spImportUserType(userId) {
	const u = (spImportMeta && spImportMeta.users || []).find(x => x.id === userId);
	return u ? u.type : 'gm';
}

function spFileToDataURL(file) {
	return new Promise((resolve, reject) => {
		const r = new FileReader();
		r.onload  = () => resolve(r.result);
		r.onerror = reject;
		r.readAsDataURL(file);
	});
}

// Parse each dropped/picked file (sequential — keeps API load sane and order stable).
async function handleImportFiles(files) {
	for (const f of Array.from(files || [])) {
		const row = { fileName: f.name, status: 'parsing', data: null, userId: 0, type: 'gm', locationId: 0, periodStart: '', periodEnd: '', include: true, error: '' };
		spImportRows.push(row);
		renderImportRows();
		try {
			const dataUrl = await spFileToDataURL(f);
			const res = await spAjax('site_pulse_import_parse', { file: dataUrl, mime: f.type || '' });
			if (!res.success) { row.status = 'error'; row.error = res.data?.message || 'Parse failed.'; }
			else {
				row.status      = 'ok';
				row.data        = res.data;
				row.userId      = res.data.matched_user_id || 0;
				row.type        = res.data.detected_type || 'gm';   // auto-detected from who wrote it
				row.locationId  = res.data.matched_location_id || 0;
				row.periodStart = res.data.period_start || '';
				row.periodEnd   = res.data.period_end || '';
			}
		} catch (e) { row.status = 'error'; row.error = 'Upload/parse error.'; }
		renderImportRows();
	}
}

function renderImportRows() {
	const list = $('#sp-import-list');
	if (!list) return;
	list.innerHTML = spImportRows.map((row, i) => renderImportRow(row, i)).join('');
	const actions = $('#sp-import-actions');
	if (actions) actions.hidden = !spImportRows.some(r => r.status === 'ok');
}

function spImportFieldLabel(key) {
	const f = spImportMeta && spImportMeta.fields.find(x => x.key === key);
	return f ? f.label : key;
}

function renderImportRow(row, i) {
	if (row.status === 'parsing') {
		return `<div class="sp-card-sm sp-import-card" data-i="${i}"><div class="sp-import-card-head"><span class="unique sp-import-fname">${esc(row.fileName)}</span><span class="unique sp-import-status">Reading…</span></div><div class="sp-loading"></div></div>`;
	}
	if (row.status === 'error') {
		return `<div class="sp-card-sm sp-import-card sp-import-card-error" data-i="${i}"><div class="sp-import-card-head"><span class="unique sp-import-fname">${esc(row.fileName)}</span><button type="button" class="unique sp-btn sp-btn-ghost sp-import-remove" data-i="${i}">Remove</button></div><div class="sp-import-err">${esc(row.error)}</div></div>`;
	}

	const d         = row.data || {};
	const ans       = d.answers || {};
	const users     = spImportMeta ? spImportMeta.users : [];
	const locs      = spImportMeta ? spImportMeta.locations : [];
	const rtype     = row.type === 'supervisor' ? 'supervisor' : 'gm';
	const needsLoc  = rtype === 'gm';
	const who       = rtype === 'supervisor' ? 'Supervisor' : 'GM';
	const userOpts  = `<option value="0">— Select ${who} —</option>` + users.map(u => `<option value="${u.id}"${u.id === row.userId ? ' selected' : ''}>${esc(u.name)}</option>`).join('');
	const locOpts   = '<option value="0">— Select Location —</option>' + locs.map(l => `<option value="${l.id}"${l.id === row.locationId ? ' selected' : ''}>${esc(l.name)}</option>`).join('');
	const typeOpts  = `<option value="gm"${rtype === 'gm' ? ' selected' : ''}>GM Report</option><option value="supervisor"${rtype === 'supervisor' ? ' selected' : ''}>Supervisor Report</option>`;

	const detected  = [ d.gm_name ? `${who}: “${esc(d.gm_name)}”` : '', ( needsLoc && d.location_name ) ? `Location: “${esc(d.location_name)}”` : '' ].filter(Boolean).join(' · ');
	const warn      = ( !row.userId || ( needsLoc && !row.locationId ) ) ? `<span class="unique sp-import-warn">needs ${who}${needsLoc ? ' + location' : ''}</span>` : '';

	const ansKeys  = Object.keys(ans);
	const ansRows  = ansKeys.length
		? ansKeys.map(k => `<div class="sp-import-ans-row"><span class="unique sp-import-ans-label">${esc(spImportFieldLabel(k))}</span><span class="unique sp-import-ans-val">${esc(Array.isArray(ans[k]) ? ans[k].join(', ') : String(ans[k]))}</span></div>`).join('')
		: '<div class="sp-import-ans-row">No fields were extracted.</div>';

	return `<div class="sp-card-sm sp-import-card" data-i="${i}">` +
		'<div class="sp-import-card-head">' +
			`<label class="sp-import-inc"><input type="checkbox" class="sp-import-include" data-i="${i}" ${row.include ? 'checked' : ''}> <span class="unique sp-import-fname">${esc(row.fileName)}</span></label>` +
			warn +
			`<button type="button" class="unique sp-btn sp-btn-ghost sp-import-remove" data-i="${i}">Remove</button>` +
		'</div>' +
		(detected ? `<div class="sp-import-detected">Detected — ${detected}</div>` : '') +
		'<div class="sp-import-grid">' +
			`<label class="sp-import-f"><span>Report type</span><select class="sp-select sp-import-type-sel" data-i="${i}">${typeOpts}</select></label>` +
			`<label class="sp-import-f"><span>${who}</span><select class="sp-select sp-import-user" data-i="${i}">${userOpts}</select></label>` +
			( needsLoc ? `<label class="sp-import-f"><span>Location</span><select class="sp-select sp-import-loc" data-i="${i}">${locOpts}</select></label>` : '' ) +
			`<label class="sp-import-f"><span>Period start</span><input type="date" class="sp-input sp-import-start" data-i="${i}" value="${esc(row.periodStart)}"></label>` +
			`<label class="sp-import-f"><span>Period end</span><input type="date" class="sp-input sp-import-end" data-i="${i}" value="${esc(row.periodEnd)}"></label>` +
		'</div>' +
		`<details class="sp-import-details"><summary>Extracted values (${ansKeys.length})</summary><div class="sp-import-ans">${ansRows}</div></details>` +
	'</div>';
}

function onImportListChange(e) {
	const el = e.target;
	const i  = parseInt(el.dataset.i, 10);
	if (isNaN(i) || !spImportRows[i]) return;

	if (el.classList.contains('sp-import-type-sel')) {
		spImportRows[i].type = el.value === 'supervisor' ? 'supervisor' : 'gm';
		renderImportRows();                                   // toggle the location field + labels
	} else if (el.classList.contains('sp-import-user')) {
		spImportRows[i].userId = parseInt(el.value, 10) || 0;
		// Auto-correct the report type to the chosen person's role (this is the Ed-is-a-supervisor fix).
		if (spImportRows[i].userId) spImportRows[i].type = spImportUserType(spImportRows[i].userId);
		renderImportRows();
	} else if (el.classList.contains('sp-import-loc'))     { spImportRows[i].locationId  = parseInt(el.value, 10) || 0; }
	else if (el.classList.contains('sp-import-start'))     { spImportRows[i].periodStart = el.value; }
	else if (el.classList.contains('sp-import-end'))       { spImportRows[i].periodEnd   = el.value; }
	else if (el.classList.contains('sp-import-include'))   { spImportRows[i].include     = el.checked; }
}

function onImportListClick(e) {
	const btn = e.target.closest('.sp-import-remove');
	if (!btn) return;
	const i = parseInt(btn.dataset.i, 10);
	if (!isNaN(i)) { spImportRows.splice(i, 1); renderImportRows(); }
}

async function saveAllImports() {
	const btn    = $('#sp-import-save-all');
	const toSave = spImportRows.filter(r => r.status === 'ok' && r.include);
	if (!toSave.length) { alert('Nothing checked to save.'); return; }
	// GM rows need a location; supervisor rows don't. Every row needs a person.
	if (toSave.some(r => !r.userId || (r.type !== 'supervisor' && !r.locationId))) {
		alert('Some checked reports still need a GM/Supervisor (and a Location for GM reports). Fix or uncheck them first.');
		return;
	}

	btn.disabled = true;
	let saved = 0, failed = 0;
	for (const r of toSave) {
		const isSup = r.type === 'supervisor';
		try {
			const res = await spAjax('site_pulse_import_save', {
				type:         isSup ? 'supervisor' : 'gm',
				user_id:      r.userId,
				location_id:  isSup ? 0 : r.locationId,
				period_start: r.periodStart,
				period_end:   r.periodEnd,
				answers:      JSON.stringify((r.data && r.data.answers) || {}),
			});
			if (res.success) { r.saved = true; saved++; }
			else { failed++; r.error = res.data?.message || 'Save failed.'; }
		} catch (e) { failed++; }
	}
	spImportRows = spImportRows.filter(r => !r.saved); // drop the ones that imported
	renderImportRows();
	btn.disabled = false;
	spFlash(`Imported ${saved} report${saved === 1 ? '' : 's'}${failed ? `, ${failed} failed` : ''}.`);
}


/* ─────────────── Import Comment Cards (GOD-only) — AI-read physical cards into Surveys ─────────────── */

let spCardMeta   = null;   // {dimensions:{key:label}, locations:[{id,name}], has_api_key}
let spCardRows   = [];     // [{fileName, status, data, location, customerName, email, phone, visitDate, include, error, saved}]
let _spCardWired = false;

async function initImportCards() {
	const panel = $('#sp-panel-import-cards');
	if (!panel) return;

	if (!_spCardWired) {
		$('#sp-card-import-pick')?.addEventListener('click', () => $('#sp-card-import-files')?.click());
		$('#sp-card-import-files')?.addEventListener('change', e => { handleCardFiles(e.target.files); e.target.value = ''; });
		$('#sp-card-import-save-all')?.addEventListener('click', saveAllCards);
		$('#sp-card-import-list')?.addEventListener('change', onCardListChange);
		$('#sp-card-import-list')?.addEventListener('click', onCardListClick);

		const drop = $('#sp-card-import-drop');
		if (drop) {
			['dragover', 'dragenter'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-drag'); }));
			['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('is-drag'); }));
			drop.addEventListener('drop', e => { if (e.dataTransfer && e.dataTransfer.files.length) handleCardFiles(e.dataTransfer.files); });
		}
		_spCardWired = true;
	}

	if (!spCardMeta) await spLoadCardMeta();
}

async function spLoadCardMeta() {
	const res = await spAjax('site_pulse_card_import_meta', {});
	if (!res.success) {
		const l = $('#sp-card-import-list');
		if (l) l.innerHTML = `<div class="sp-empty">${esc(res.data?.message || 'Could not load.')}</div>`;
		return;
	}
	spCardMeta = res.data;
	if (!spCardMeta.has_api_key) spFlash('No Claude API key set (Settings → API Keys) — parsing will fail until it is.');
}

function spCardDimLabel(key) {
	const d = spCardMeta && spCardMeta.dimensions;
	return (d && d[key]) ? d[key] : key;
}

// Default store for imported comment cards when the AI didn't match one — nearly all cards are Bubba's.
// Resolves the exact canonical name from the loaded locations so the dropdown option selects cleanly.
function spCardDefaultLocation() {
	const locs = (spCardMeta && spCardMeta.locations) || [];
	const hit = locs.find(l => /bubba/i.test(l.name));
	return hit ? hit.name : "Bubba's";
}

// Parse each dropped/picked card (sequential — keeps API load sane and order stable).
async function handleCardFiles(files) {
	for (const f of Array.from(files || [])) {
		const row = { fileName: f.name, status: 'parsing', data: null, location: '', customerName: '', email: '', phone: '', visitDate: '', include: true, error: '' };
		spCardRows.push(row);
		renderCardRows();
		try {
			const dataUrl = await spFileToDataURL(f);
			const res = await spAjax('site_pulse_card_import_parse', { file: dataUrl, mime: f.type || '' });
			if (!res.success) { row.status = 'error'; row.error = res.data?.message || 'Parse failed.'; }
			else {
				row.status       = 'ok';
				row.data         = res.data;
				row.location     = res.data.matched_location || spCardDefaultLocation();   // AI match wins; otherwise default to Bubba's
				row.customerName = res.data.customer_name || '';
				row.email        = res.data.email || '';
				row.phone        = res.data.phone || '';
				row.visitDate    = res.data.visit_date || '';
			}
		} catch (e) { row.status = 'error'; row.error = 'Upload/parse error.'; }
		renderCardRows();
	}
}

function renderCardRows() {
	const list = $('#sp-card-import-list');
	if (!list) return;
	list.innerHTML = spCardRows.map((row, i) => renderCardRow(row, i)).join('');
	const actions = $('#sp-card-import-actions');
	if (actions) actions.hidden = !spCardRows.some(r => r.status === 'ok');
}

function renderCardRow(row, i) {
	if (row.status === 'parsing') {
		return `<div class="sp-card-sm sp-import-card" data-i="${i}"><div class="sp-import-card-head"><span class="unique sp-import-fname">${esc(row.fileName)}</span><span class="unique sp-import-status">Reading…</span></div><div class="sp-loading"></div></div>`;
	}
	if (row.status === 'error') {
		return `<div class="sp-card-sm sp-import-card sp-import-card-error" data-i="${i}"><div class="sp-import-card-head"><span class="unique sp-import-fname">${esc(row.fileName)}</span><button type="button" class="unique sp-btn sp-btn-ghost sp-card-import-remove" data-i="${i}">Remove</button></div><div class="sp-import-err">${esc(row.error)}</div></div>`;
	}

	const d          = row.data || {};
	const ratings    = d.ratings || {};
	const locs       = spCardMeta ? spCardMeta.locations : [];
	const ratingKeys = Object.keys(ratings);
	const avg        = ratingKeys.length ? (ratingKeys.reduce((s, k) => s + Number(ratings[k] || 0), 0) / ratingKeys.length) : null;

	const locOpts = '<option value="">— Select Location —</option>' + locs.map(l => `<option value="${esc(l.name)}"${l.name === row.location ? ' selected' : ''}>${esc(l.name)}</option>`).join('');

	const ratingRows = ratingKeys.length
		? ratingKeys.map(k => { const n = Math.max(0, Math.min(5, parseInt(ratings[k], 10) || 0)); return `<div class="sp-import-ans-row"><span class="unique sp-import-ans-label">${esc(spCardDimLabel(k))}</span><span class="unique sp-import-ans-val">${'★'.repeat(n)}${'☆'.repeat(5 - n)} (${n})</span></div>`; }).join('')
		: '<div class="sp-import-ans-row">No ratings were read.</div>';

	const extra = [
		d.experience ? `Experience: ${esc(d.experience)}` : '',
		(d.address || d.city || d.state || d.zip) ? `Address: ${esc([d.address, d.city, d.state, d.zip].filter(Boolean).join(', '))}` : '',
		d.referral ? `Referral: ${esc(d.referral)}` : '',
	].filter(Boolean).map(t => `<div class="sp-import-ans-row">${t}</div>`).join('');

	const warn = !row.location ? '<span class="unique sp-import-warn">needs location</span>' : '';

	return `<div class="sp-card-sm sp-import-card" data-i="${i}">` +
		'<div class="sp-import-card-head">' +
			`<label class="sp-import-inc"><input type="checkbox" class="sp-card-import-include" data-i="${i}" ${row.include ? 'checked' : ''}> <span class="unique sp-import-fname">${esc(row.fileName)}</span></label>` +
			warn +
			(avg !== null ? `<span class="unique sp-import-detected">avg ${avg.toFixed(1)}★</span>` : '') +
			`<button type="button" class="unique sp-btn sp-btn-ghost sp-card-import-remove" data-i="${i}">Remove</button>` +
		'</div>' +
		'<div class="sp-import-grid">' +
			`<label class="sp-import-f"><span>Location</span><select class="sp-select sp-card-loc" data-i="${i}">${locOpts}</select></label>` +
			`<label class="sp-import-f"><span>Customer name</span><input type="text" class="sp-input sp-card-name" data-i="${i}" value="${esc(row.customerName)}"></label>` +
			`<label class="sp-import-f"><span>Visit date</span><input type="date" class="sp-input sp-card-visit" data-i="${i}" value="${esc(row.visitDate)}"></label>` +
			`<label class="sp-import-f"><span>Phone</span><input type="text" class="sp-input sp-card-phone" data-i="${i}" value="${esc(row.phone)}"></label>` +
			`<label class="sp-import-f"><span>Email</span><input type="text" class="sp-input sp-card-email" data-i="${i}" value="${esc(row.email)}"></label>` +
		'</div>' +
		`<details class="sp-import-details"><summary>Ratings &amp; comments (${ratingKeys.length})</summary><div class="sp-import-ans">${ratingRows}${extra}${d.comments ? `<div class="sp-import-ans-row sp-card-comments"><em>${esc(d.comments)}</em></div>` : ''}</div></details>` +
	'</div>';
}

function onCardListChange(e) {
	const el = e.target;
	const i  = parseInt(el.dataset.i, 10);
	if (isNaN(i) || !spCardRows[i]) return;

	if (el.classList.contains('sp-card-loc'))            { spCardRows[i].location     = el.value; renderCardRows(); }
	else if (el.classList.contains('sp-card-name'))      { spCardRows[i].customerName = el.value; }
	else if (el.classList.contains('sp-card-visit'))     { spCardRows[i].visitDate    = el.value; }
	else if (el.classList.contains('sp-card-phone'))     { spCardRows[i].phone        = el.value; }
	else if (el.classList.contains('sp-card-email'))     { spCardRows[i].email        = el.value; }
	else if (el.classList.contains('sp-card-import-include')) { spCardRows[i].include = el.checked; }
}

function onCardListClick(e) {
	const btn = e.target.closest('.sp-card-import-remove');
	if (!btn) return;
	const i = parseInt(btn.dataset.i, 10);
	if (!isNaN(i)) { spCardRows.splice(i, 1); renderCardRows(); }
}

async function saveAllCards() {
	const btn    = $('#sp-card-import-save-all');
	const toSave = spCardRows.filter(r => r.status === 'ok' && r.include);
	if (!toSave.length) { alert('Nothing checked to save.'); return; }
	if (toSave.some(r => !r.location) && !confirm('Some checked cards have no location set. Save them anyway?')) return;

	btn.disabled = true;
	let saved = 0, failed = 0;
	for (const r of toSave) {
		try {
			const res = await spAjax('site_pulse_card_import_save', {
				location:      r.location || '',
				customer_name: r.customerName || '',
				email:         r.email || '',
				phone:         r.phone || '',
				visit_date:    r.visitDate || '',
				card:          JSON.stringify(r.data || {}),
			});
			if (res.success) { r.saved = true; saved++; }
			else { failed++; r.error = res.data?.message || 'Save failed.'; }
		} catch (e) { failed++; }
	}
	spCardRows = spCardRows.filter(r => !r.saved); // drop the ones that imported
	renderCardRows();
	btn.disabled = false;
	spFlash(`Imported ${saved} card${saved === 1 ? '' : 's'}${failed ? `, ${failed} failed` : ''}.`);
}

function toDateStr(d) {
	return d.toISOString().split('T')[0];
}

function toDateStr(d) {
	return d.toISOString().split('T')[0];
}

function calcHeaderValue(calc, dateStr) {
	const now = dateStr ? new Date(dateStr + 'T00:00:00') : new Date();
	const month = now.getMonth();
	const quarter = Math.floor(month / 3) + 1;

	switch (calc) {
		case 'quarter':
			return 'Q' + quarter + ' ' + now.getFullYear();
		case 'week_of_quarter': {
			const quarterStart = new Date(now.getFullYear(), (quarter - 1) * 3, 1);
			const diffMs = now - quarterStart;
			const week = Math.ceil(diffMs / (7 * 24 * 60 * 60 * 1000));
			return 'Week ' + week;
		}
		case 'week_of_year': {
			const start = new Date(now.getFullYear(), 0, 1);
			const diff = now - start;
			return 'Week ' + Math.ceil(diff / (7 * 24 * 60 * 60 * 1000));
		}
		case 'month':
			return now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
		case 'year':
			return String(now.getFullYear());
		default:
			return calc || '';
	}
}


/*--------------------------------------------------------------
# Mileage — Manager
--------------------------------------------------------------*/

let mileageLocations = [];
let mileageChargeOptions = []; // Home Bases (stores) with a Location #, for the "Charge To" picker
let mileageRate = 0.67;
let mileageHomeId = 0; // this driver's customer-connect location id (start/end bookend), 0 if unset
let mileagePurposes = []; // editable library of common business purposes (datalist source)
let mileageRequireApproval = true; // when false, drivers may save destinations straight to the database (global or personal)
let mileagePeriodLength = 0;  // pay-period length in days (0 = use calendar months for the Jump menu)
let mileagePeriodAnchor = ''; // any date a period begins on (YYYY-MM-DD); periods extrapolate from here
const MILEAGE_CATEGORIES = ['Restaurant', 'Office', 'Vendor', 'Supplier', 'Bank', 'Client', 'Other'];

// Parse a location's pinned_purposes (stored as a JSON string) into an array.
function locationPinnedPurposes(loc) {
	if (!loc || !loc.pinned_purposes) return [];
	try { const a = JSON.parse(loc.pinned_purposes); return Array.isArray(a) ? a : []; }
	catch (e) { return []; }
}

// Current state of the "Return home" toggle; defaults on, matching the prototype.
function getReturnHome() {
	const cb = $('#sp-mileage-return-home');
	return cb ? cb.checked : true;
}

function initMileage() {
	const panel = $('#sp-panel-mileage');
	if (!panel) return;

	// A manual date / month / clear selection drops the active preset-pill highlight.
	const clearRangeActive = () => $$('.sp-mileage-range', panel).forEach(b => b.classList.remove('active'));

	$('#sp-mileage-add-btn')?.addEventListener('click', () => showMileageForm());
	$('#sp-mileage-map-btn')?.addEventListener('click', () => activatePanel('mileage-map'));
	$('#sp-mileage-pdf-btn')?.addEventListener('click', () => exportMileagePDF());
	$('#sp-mileage-filter-clear')?.addEventListener('click', () => {
		$('#sp-mileage-filter-start').value = '';
		$('#sp-mileage-filter-end').value = '';
		clearRangeActive();
		loadMileageEntries();
	});
	$('#sp-mileage-filter-start')?.addEventListener('change', () => { clearRangeActive(); loadMileageEntries(); });
	$('#sp-mileage-filter-end')?.addEventListener('change', () => { clearRangeActive(); loadMileageEntries(); });

	$$('.sp-mileage-range', panel).forEach(b => b.addEventListener('click', () => setMileageRange(b.dataset.range)));
	populateMileagePeriods();
	$('#sp-mileage-month-picker')?.addEventListener('change', (e) => {
		const v = e.target.value;
		if (!v) return;
		clearRangeActive();
		const [start, end] = v.split('|');
		setMileageFilter(start, end);
	});

	// Click a leg's miles to adjust them (detour, closure, accident). Delegated on the list
	// container so it keeps working after each re-render replaces the rows inside it.
	$('#sp-mileage-list')?.addEventListener('click', (ev) => {
		const el = ev.target.closest('.sp-leg-edit');
		if (el && !el.querySelector('input')) openLegMilesEditor(el);
	});

	// Re-populate once the period config arrives (locations load resolves after the initial paint),
	// then default the view to This Period — unless the user already picked a range/dates this
	// session (matches the expense sections, which default to This Period on first load too).
	loadMileageLocations().then(() => {
		populateMileagePeriods();
		const s = $('#sp-mileage-filter-start'), e = $('#sp-mileage-filter-end');
		if ((!s || !s.value) && (!e || !e.value)) setMileageRange('period');
		else loadMileageEntries();
	});
}

/* ---- Reusable Google Maps loader (shared by the mileage map and, later, toll maps) ----
   Loads the Maps JS API exactly once with the geometry library (needed for decoding toll
   route polylines down the line). Resolves with google.maps; rejects if no key/load fails. */
let _spGmapsPromise = null;
function ensureGoogleMaps() {
	if (window.google && window.google.maps) return Promise.resolve(window.google.maps);
	if (_spGmapsPromise) return _spGmapsPromise;
	const key = (typeof D !== 'undefined' && D.mapsKey) ? D.mapsKey : '';
	if (!key) return Promise.reject(new Error('no-maps-key'));
	_spGmapsPromise = new Promise((resolve, reject) => {
		window.__spGmapsReady = () => resolve(window.google.maps);
		const s = document.createElement('script');
		s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=geometry,places&callback=__spGmapsReady`;
		s.async = true; s.defer = true;
		s.onerror = () => reject(new Error('maps-load-failed'));
		document.head.appendChild(s);
	});
	return _spGmapsPromise;
}

// Google Places autocomplete on a destination's Name field — built on the NEW Places API
// (AutocompleteSuggestion + Place). We render our OWN dropdown rather than Google's widget, so a
// denied/erroring API (or no key) just shows nothing and the field stays fully usable — no broken
// icons, no locked line. Type a business name → pick a suggestion → fills the name + full address.
function attachPlacesAutocomplete(nameEl, addrEl) {
	if (!nameEl) return;

	ensureGoogleMaps().then(async (maps) => {
		// Prefer importLibrary (guarantees the new classes); fall back to the already-loaded ns.
		let places = null;
		try { places = await maps.importLibrary('places'); } catch (e) { places = maps.places || null; }
		const Suggestion = places?.AutocompleteSuggestion || maps.places?.AutocompleteSuggestion;
		const SessionToken = places?.AutocompleteSessionToken || maps.places?.AutocompleteSessionToken;
		if (!Suggestion || typeof Suggestion.fetchAutocompleteSuggestions !== 'function') return; // new API unavailable → plain text field

		// Wrap the input so our dropdown can sit directly beneath it.
		const wrap = document.createElement('div');
		wrap.style.position = 'relative';
		nameEl.parentNode.insertBefore(wrap, nameEl);
		wrap.appendChild(nameEl);

		const menu = document.createElement('div');
		menu.style.cssText = 'position:absolute;left:0;right:0;top:100%;z-index:100001;margin-top:4px;background:#fff;border:1px solid var(--sp-border,#e2e0dc);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,0.12);max-height:260px;overflow-y:auto;display:none;';
		wrap.appendChild(menu);
		nameEl.setAttribute('autocomplete', 'off');

		let token = SessionToken ? new SessionToken() : null;
		let suggestions = [];
		let activeIdx = -1;
		let debounce = null;

		const ftext = (ft) => ft ? (ft.text != null ? ft.text : String(ft)) : '';
		const close = () => { menu.style.display = 'none'; menu.innerHTML = ''; activeIdx = -1; };

		const choose = async (sugg) => {
			close();
			try {
				const place = sugg.placePrediction.toPlace();
				await place.fetchFields({ fields: ['displayName', 'formattedAddress'] });
				if (place.displayName) nameEl.value = place.displayName;
				if (place.formattedAddress && addrEl) addrEl.value = place.formattedAddress;
			} catch (e) { /* keep whatever was typed */ }
			token = SessionToken ? new SessionToken() : null; // a selection ends the billing session
			if (addrEl) addrEl.focus();
		};

		const render = () => {
			if (!suggestions.length) { close(); return; }
			menu.innerHTML = suggestions.map((s, i) => {
				const p = s.placePrediction;
				const main = ftext(p.mainText) || ftext(p.text);
				const sub = ftext(p.secondaryText);
				const bg = i === activeIdx ? 'background:var(--sp-accent-lightest,#fdf0e3);' : '';
				return `<div data-i="${i}" style="padding:8px 12px;font-size:14px;line-height:1.3;cursor:pointer;${bg}">`
					+ `<div style="color:var(--sp-text,#2c2a27);">${esc(main)}</div>`
					+ (sub ? `<div style="font-size:12px;color:var(--sp-text-light,#8a8580);">${esc(sub)}</div>` : '')
					+ '</div>';
			}).join('');
			menu.style.display = 'block';
			$$('div[data-i]', menu).forEach(el => {
				const i = parseInt(el.dataset.i);
				el.addEventListener('mousedown', (e) => { e.preventDefault(); choose(suggestions[i]); });
				el.addEventListener('mouseenter', () => { activeIdx = i; render(); });
			});
		};

		const fetchSuggestions = async (q) => {
			try {
				const req = { input: q };
				if (token) req.sessionToken = token;
				const res = await Suggestion.fetchAutocompleteSuggestions(req);
				suggestions = (res.suggestions || []).filter(s => s.placePrediction);
				activeIdx = -1;
				render();
			} catch (e) { close(); } // REQUEST_DENIED / quota / etc → silently no suggestions
		};

		nameEl.addEventListener('input', () => {
			const q = nameEl.value.trim();
			if (q.length < 2) { close(); return; }
			clearTimeout(debounce);
			debounce = setTimeout(() => fetchSuggestions(q), 200);
		});
		nameEl.addEventListener('keydown', (e) => {
			if (menu.style.display === 'none' || !suggestions.length) return;
			if (e.key === 'ArrowDown') { e.preventDefault(); activeIdx = Math.min(activeIdx + 1, suggestions.length - 1); render(); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); activeIdx = Math.max(activeIdx - 1, 0); render(); }
			else if (e.key === 'Enter' && activeIdx >= 0) { e.preventDefault(); choose(suggestions[activeIdx]); }
			else if (e.key === 'Escape') { close(); }
		});
		nameEl.addEventListener('blur', () => setTimeout(close, 150));
	}).catch(() => {});
}

let _spMileageMap = null, _spMileageMarkers = [], _spMileageInfo = null, _spMileageLocs = [], _spMileageHomeId = 0;
let _spMileageMarkerIcons = {}; // per-type marker image URLs (admin-configured); blank = use a dot

// Which marker "bucket" a location falls in — drives both the dot color and the image lookup.
function mileageMarkerBucket(loc) {
	if (parseInt(loc.id) === _spMileageHomeId) return 'home';
	const t = (loc.location_type || '').toLowerCase();
	const c = (loc.category || '').toLowerCase();
	if (t === 'restaurant' || c === 'restaurant') return 'restaurant';
	if (c === 'office') return 'office';
	if (t === 'vendor' || c === 'vendor' || c === 'supplier') return 'vendor';
	return 'other';
}

// Marker color by role: home (navy), restaurant/office (blue), vendor (amber), else grey.
function mileageMapColor(loc) {
	const b = mileageMarkerBucket(loc);
	if (b === 'home') return '#15243a';
	if (b === 'restaurant' || b === 'office') return '#3b82f6';
	if (b === 'vendor') return '#f59e0b';
	return '#94a3b8';
}

async function loadMileageMap() {
	const canvas = $('#sp-mileage-map-canvas');
	const notice = $('#sp-mileage-map-notice');
	if (!canvas) return;

	// Pull locations (reuses the picker endpoint — already privacy-filtered, now incl. lat/lng).
	try {
		const res = await spAjax('site_pulse_get_mileage_locations', {});
		if (res.success) {
			_spMileageLocs = (res.data.locations || []).filter(l => l.lat && l.lng);
			_spMileageHomeId = parseInt(res.data.home_location_id) || 0;
			_spMileageMarkerIcons = res.data.marker_icons || {};
		}
	} catch (e) {}

	let maps;
	try { maps = await ensureGoogleMaps(); }
	catch (e) { if (notice) notice.hidden = false; canvas.style.display = 'none'; return; }
	if (notice) notice.hidden = true;
	canvas.style.display = '';

	if (!_spMileageMap) {
		_spMileageMap = new maps.Map(canvas, {
			center: { lat: 33.15, lng: -96.82 }, zoom: 10,
			mapTypeControl: false, streetViewControl: false, fullscreenControl: false,
		});
		_spMileageInfo = new maps.InfoWindow();
	} else {
		maps.event.trigger(_spMileageMap, 'resize'); // re-entering the panel
	}

	populateMileageMapCategories();
	renderMileageMapMarkers(maps, $('#sp-mileage-map-category')?.value || '');

	// Wire toolbar once (loadMileageMap re-runs on every panel re-entry).
	if (!canvas.dataset.wired) {
		canvas.dataset.wired = '1';
		$('#sp-mileage-map-category')?.addEventListener('change', (e) => renderMileageMapMarkers(maps, e.target.value));
		$('#sp-mileage-map-fit')?.addEventListener('click', () => renderMileageMapMarkers(maps, $('#sp-mileage-map-category')?.value || ''));
	}
}

function populateMileageMapCategories() {
	const sel = $('#sp-mileage-map-category');
	if (!sel || sel.dataset.filled) return;
	const cats = [...new Set(_spMileageLocs.map(l => l.category).filter(Boolean))].sort();
	sel.innerHTML = '<option value="">All categories</option>' + cats.map(c => `<option value="${esc(c)}">${esc(c)}</option>`).join('');
	sel.dataset.filled = '1';
}

function renderMileageMapMarkers(maps, filterCat = '') {
	_spMileageMarkers.forEach(m => m.setMap(null));
	_spMileageMarkers = [];
	const bounds = new maps.LatLngBounds();
	_spMileageLocs.forEach(l => {
		if (filterCat && (l.category || '') !== filterCat) return;
		const pos = { lat: parseFloat(l.lat), lng: parseFloat(l.lng) };
		// Per-location image override wins; else the type's default image; else the color dot.
		const iconUrl = l.marker_icon || _spMileageMarkerIcons[mileageMarkerBucket(l)];
		const icon = iconUrl
			? { url: iconUrl, scaledSize: new maps.Size(38, 38), anchor: new maps.Point(19, 19) }
			: { path: maps.SymbolPath.CIRCLE, scale: 7, fillColor: mileageMapColor(l), fillOpacity: 1, strokeColor: '#fff', strokeWeight: 2 };
		const marker = new maps.Marker({
			position: pos, map: _spMileageMap, title: l.name, icon,
		});
		marker.addListener('click', () => {
			const cat = l.category ? `<div class="sp-map-info-cat">${esc(l.category)}</div>` : '';
			_spMileageInfo.setContent(`<div class="sp-map-info"><strong>${esc(l.name)}</strong>${cat}<div>${esc(l.address || '')}</div></div>`);
			_spMileageInfo.open(_spMileageMap, marker);
		});
		_spMileageMarkers.push(marker);
		bounds.extend(pos);
	});
	if (_spMileageMarkers.length) _spMileageMap.fitBounds(bounds);
}

function mPad2(n) { return String(n).padStart(2, '0'); }
function mIso(d) { return `${d.getFullYear()}-${mPad2(d.getMonth() + 1)}-${mPad2(d.getDate())}`; }
function mMonthEnd(y, m) { return `${y}-${mPad2(m)}-${mPad2(new Date(y, m, 0).getDate())}`; }

function setMileageFilter(start, end) {
	const s = $('#sp-mileage-filter-start'), e = $('#sp-mileage-filter-end');
	if (s) s.value = start || '';
	if (e) e.value = end || '';
	loadMileageEntries();
}

// Modal to adjust one leg's miles. Driver enters the actual miles driven (prefilled with the
// current value) and an optional reason; the delta from the computed base is saved as the
// adjustment. On save the report reloads so the leg $, the day total, and the grand total update.
function openLegMilesEditor(el) {
	const base   = parseFloat(el.dataset.base) || 0;
	const adj    = parseFloat(el.dataset.adjust) || 0;
	const reason = el.dataset.reason || '';

	const modal = document.createElement('div');
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal">
			<h3>Adjust Trip Miles</h3>
			<p class="sp-text-secondary">Calculated distance for this trip: <strong>${mileageNumFmt(base)} mi</strong>. Enter the actual miles driven — the difference is recorded as an adjustment.</p>
			<div class="sp-form-group"><label>Actual miles</label><input type="number" step="0.01" min="0" id="sp-leg-miles" class="sp-input" value="${(base + adj).toFixed(2)}"></div>
			<div class="sp-form-group"><label>Reason for change</label><input type="text" id="sp-leg-reason" class="sp-input" value="${esc(reason)}" placeholder="e.g. detour around road closure"></div>
			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-leg-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-leg-cancel">Cancel</button>
			</div>
		</div>`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);

	const milesInput = modal.querySelector('#sp-leg-miles');
	milesInput.focus(); milesInput.select();

	const close = () => modal.remove();
	modal.querySelector('#sp-leg-cancel').addEventListener('click', close);
	modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

	modal.querySelector('#sp-leg-save').addEventListener('click', async () => {
		const val = parseFloat(milesInput.value);
		if (isNaN(val) || val < 0) { alert('Enter a valid mileage.'); return; }
		const newAdjust = Math.round((val - base) * 100) / 100; // delta vs the computed distance
		try {
			const r = await spAjax('site_pulse_save_mileage_leg_adjust', {
				entry_id: el.dataset.entryId, leg_id: el.dataset.legId,
				adjust: newAdjust, reason: modal.querySelector('#sp-leg-reason').value.trim(),
			});
			if (r.success) { spFlash('Miles updated'); close(); loadMileageEntries(); }
			else alert(r.data?.message || 'Error.');
		} catch (err) { alert('Error saving miles.'); }
	});

	modal.querySelectorAll('input').forEach(inp => inp.addEventListener('keydown', (e) => {
		if (e.key === 'Enter') { e.preventDefault(); modal.querySelector('#sp-leg-save').click(); }
		else if (e.key === 'Escape') { e.preventDefault(); close(); }
	}));
}

// Quick date ranges for the report (period selector + presets, mirroring the prototype).
function setMileageRange(range) {
	const now = new Date(), y = now.getFullYear(), m = now.getMonth() + 1;
	let start = '', end = mIso(now);
	if (range === 'period') {
		if (mileagePeriodLength > 0 && mileagePeriodAnchor) {
			const cur = computeMileagePeriods(mileagePeriodAnchor, mileagePeriodLength, 0, 0).find(p => p.current);
			if (cur) { start = cur.start; end = cur.end; }
		} else { start = `${y}-${mPad2(m)}-01`; end = mMonthEnd(y, m); } // no period configured → calendar month
	}
	else if (range === 'month') { start = `${y}-${mPad2(m)}-01`; end = mMonthEnd(y, m); }
	else if (range === 'ytd') { start = `${y}-01-01`; }
	else if (range === 'last30') { const d = new Date(now); d.setDate(d.getDate() - 30); start = mIso(d); }
	else if (range === 'last90') { const d = new Date(now); d.setDate(d.getDate() - 90); start = mIso(d); }
	const mp = $('#sp-mileage-month-picker'); if (mp) mp.value = '';
	$$('.sp-mileage-range').forEach(b => b.classList.toggle('active', b.dataset.range === range));
	setMileageFilter(start, end);
}

const MILEAGE_MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function mDateFromIso(iso) { const [y, m, d] = iso.split('-').map(Number); return new Date(y, m - 1, d); }
function mAddDays(d, n) { const r = new Date(d); r.setDate(r.getDate() + n); return r; }

// Human label for a date range, e.g. "Jan 25 – Feb 24, 2026" (shows both years if they differ).
function mPeriodLabel(s, e) {
	const sM = MILEAGE_MONTH_ABBR[s.getMonth()], eM = MILEAGE_MONTH_ABBR[e.getMonth()];
	if (s.getFullYear() === e.getFullYear()) return `${sM} ${s.getDate()} – ${eM} ${e.getDate()}, ${e.getFullYear()}`;
	return `${sM} ${s.getDate()}, ${s.getFullYear()} – ${eM} ${e.getDate()}, ${e.getFullYear()}`;
}

// Extrapolate pay periods of `lengthDays` from an anchor date, indefinitely in both directions.
// Returns the period containing today plus `back` earlier and `fwd` later ones, newest first.
// fwd defaults to 0: the jump menus must not offer a future period — the current one is the latest
// selectable. Callers that genuinely need the next period (e.g. the Settings preview) pass fwd=1.
function computeMileagePeriods(anchorIso, lengthDays, back = 24, fwd = 0) {
	const anchor = mDateFromIso(anchorIso);
	const today = new Date(); today.setHours(0, 0, 0, 0);
	const MS_DAY = 86400000;
	// Round the raw day gap first so a DST hour shift can't push us into the wrong period.
	const dayGap = Math.round((today - anchor) / MS_DAY);
	const k = Math.floor(dayGap / lengthDays); // which period today falls in
	const out = [];
	for (let i = k + fwd; i >= k - back; i--) {
		const start = mAddDays(anchor, i * lengthDays);
		const end = mAddDays(anchor, (i + 1) * lengthDays - 1);
		out.push({ start: mIso(start), end: mIso(end), startDate: start, endDate: end, current: i === k });
	}
	return out;
}

// The Jump menu: pay periods when an admin has configured length + anchor, else calendar months.
// Every option's value is "start|end" so the change handler is the same for both modes.
function populateMileagePeriods() {
	const sel = $('#sp-mileage-month-picker');
	if (!sel) return;

	// Keep the "This Period" / "This Month" preset button in sync with the configured mode.
	const periodBtn = $('#sp-mileage-range-period');
	if (periodBtn) periodBtn.textContent = (mileagePeriodLength > 0 && mileagePeriodAnchor) ? 'This Period' : 'This Month';

	if (mileagePeriodLength > 0 && mileagePeriodAnchor) {
		let opts = '<option value="">Jump to period…</option>';
		computeMileagePeriods(mileagePeriodAnchor, mileagePeriodLength).forEach(p => {
			opts += `<option value="${p.start}|${p.end}">${mPeriodLabel(p.startDate, p.endDate)}${p.current ? ' (current)' : ''}</option>`;
		});
		sel.innerHTML = opts;
		return;
	}

	const now = new Date();
	let opts = '<option value="">Jump to month…</option>';
	for (let i = 0; i < 24; i++) {
		const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
		const y = d.getFullYear(), m = d.getMonth() + 1;
		opts += `<option value="${y}-${mPad2(m)}-01|${mMonthEnd(y, m)}">${MILEAGE_MONTH_ABBR[m - 1]} ${y}</option>`;
	}
	sel.innerHTML = opts;
}

/* ---- Generic period toolbar (shared by the expense sections, mirrors the Mileage screen) ----
   Renders the same presets + jump-to-period + date-range + Clear UI as Mileage into a container
   and calls onApply(start,end) on every change. Pay-period vs calendar-month mode reuses the
   mileage period config (same employee, same pay periods). */
function spRangeDates(range) {
	const now = new Date(), y = now.getFullYear(), m = now.getMonth() + 1;
	let start = '', end = mIso(now);
	if (range === 'period') {
		if (mileagePeriodLength > 0 && mileagePeriodAnchor) {
			const cur = computeMileagePeriods(mileagePeriodAnchor, mileagePeriodLength, 0, 0).find(p => p.current);
			if (cur) { start = cur.start; end = cur.end; }
		} else { start = `${y}-${mPad2(m)}-01`; end = mMonthEnd(y, m); }
	}
	else if (range === 'ytd')    { start = `${y}-01-01`; }
	else if (range === 'last30') { const d = new Date(now); d.setDate(d.getDate() - 30); start = mIso(d); }
	else if (range === 'last90') { const d = new Date(now); d.setDate(d.getDate() - 90); start = mIso(d); }
	return [start, end];
}

function spPopulatePeriodPicker(sel) {
	if (!sel) return;
	if (mileagePeriodLength > 0 && mileagePeriodAnchor) {
		let opts = '<option value="">Jump to period…</option>';
		computeMileagePeriods(mileagePeriodAnchor, mileagePeriodLength).forEach(p => {
			opts += `<option value="${p.start}|${p.end}">${mPeriodLabel(p.startDate, p.endDate)}${p.current ? ' (current)' : ''}</option>`;
		});
		sel.innerHTML = opts; return;
	}
	const now = new Date();
	let opts = '<option value="">Jump to month…</option>';
	for (let i = 0; i < 24; i++) {
		const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
		const y = d.getFullYear(), m = d.getMonth() + 1;
		opts += `<option value="${y}-${mPad2(m)}-01|${mMonthEnd(y, m)}">${MILEAGE_MONTH_ABBR[m - 1]} ${y}</option>`;
	}
	sel.innerHTML = opts;
}

// The period config is loaded by the mileage screen; ensure it's available even when an expense
// section is opened first, so "This Period" honors the configured pay period (not a calendar month).
let _spPeriodCfgLoaded = false;
async function spEnsurePeriodConfig() {
	if (mileagePeriodLength || mileagePeriodAnchor || _spPeriodCfgLoaded) return;
	_spPeriodCfgLoaded = true;
	try { await loadMileageLocations(); } catch (e) {}
}

// Build + wire the toolbar into `container`. Defaults the selection to "This Period" and returns
// that [start,end] so the caller can do its first load with the same filter the UI shows.
// `actionsEl` (optional) is moved into the toolbar's right-aligned actions slot — used to pull
// the panel's "+ Add …" button out of the header and onto the filter row (matches Mileage).
function spBuildPeriodToolbar(prefix, container, onApply, actionsEl) {
	if (!container) return ['', ''];
	const periodLabel = (mileagePeriodLength > 0 && mileagePeriodAnchor) ? 'This Period' : 'This Month';
	container.innerHTML = `
		<div class="sp-toolbar sp-period-toolbar" id="sp-${prefix}-toolbar">
			<div class="sp-mileage-period-controls">
				<div class="sp-toolbar-group sp-${prefix}-quickranges">
					<button type="button" class="unique sp-btn sp-btn-secondary sp-${prefix}-range" data-range="period">${periodLabel}</button>
					<button type="button" class="unique sp-btn sp-btn-secondary sp-${prefix}-range" data-range="ytd">Year to Date</button>
					<button type="button" class="unique sp-btn sp-btn-secondary sp-${prefix}-range" data-range="last30">Last 30 Days</button>
					<button type="button" class="unique sp-btn sp-btn-secondary sp-${prefix}-range" data-range="last90">Last 90 Days</button>
				</div>
				<div class="sp-toolbar-group">
					<select id="sp-${prefix}-month-picker" class="sp-select sp-mileage-month-picker"></select>
					<input type="date" id="sp-${prefix}-filter-start" class="sp-input">
					<input type="date" id="sp-${prefix}-filter-end" class="sp-input">
					<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-${prefix}-filter-clear">Clear</button>
				</div>
			</div>
			<div class="sp-toolbar-actions" id="sp-${prefix}-toolbar-actions"></div>
		</div>`;
	markUniqueSpans(container);

	// Relocate the panel's action button(s) from the header onto the toolbar's right edge.
	if (actionsEl) $(`#sp-${prefix}-toolbar-actions`, container)?.appendChild(actionsEl);

	const startEl = $(`#sp-${prefix}-filter-start`, container);
	const endEl   = $(`#sp-${prefix}-filter-end`, container);
	const mp      = $(`#sp-${prefix}-month-picker`, container);
	const ranges  = $$(`.sp-${prefix}-range`, container);
	const clearActive = () => ranges.forEach(b => b.classList.remove('active'));

	spPopulatePeriodPicker(mp);

	const apply = () => onApply(startEl.value || '', endEl.value || '');

	ranges.forEach(b => b.addEventListener('click', () => {
		const [s, e] = spRangeDates(b.dataset.range);
		startEl.value = s; endEl.value = e;
		if (mp) mp.value = '';
		ranges.forEach(x => x.classList.toggle('active', x === b));
		apply();
	}));
	mp?.addEventListener('change', () => {
		if (!mp.value) return;
		clearActive();
		const [s, e] = mp.value.split('|');
		startEl.value = s; endEl.value = e || '';
		apply();
	});
	startEl.addEventListener('change', () => { clearActive(); if (mp) mp.value = ''; apply(); });
	endEl.addEventListener('change',   () => { clearActive(); if (mp) mp.value = ''; apply(); });
	$(`#sp-${prefix}-filter-clear`, container).addEventListener('click', () => {
		startEl.value = ''; endEl.value = ''; if (mp) mp.value = ''; clearActive(); apply();
	});

	// Default to This Period: set the inputs + highlight, but don't fire onApply — the caller
	// does the first load itself with the returned dates.
	const [s0, e0] = spRangeDates('period');
	startEl.value = s0; endEl.value = e0;
	const periodBtn = ranges.find(b => b.dataset.range === 'period');
	if (periodBtn) periodBtn.classList.add('active');
	return [s0, e0];
}

async function loadMileageLocations() {
	try {
		const res = await spAjax('site_pulse_get_mileage_locations', {});
		if (res.success) {
			mileageLocations = res.data.locations || [];
			mileageChargeOptions = res.data.charge_options || [];
			mileageRate = parseFloat(res.data.rate) || 0.67;
			mileageHomeId = parseInt(res.data.home_location_id) || 0;
			mileagePurposes = res.data.purposes || [];
			mileageRequireApproval = res.data.require_approval !== false;
			mileagePeriodLength = parseInt(res.data.period_length) || 0;
			mileagePeriodAnchor = res.data.period_anchor || '';
		}
	} catch (e) { mileageLocations = []; }
}

// Thousands-separated number formatting for mileage totals (e.g. 1941.71 -> "1,941.71").
function mileageNumFmt(n, dp = 2) {
	return (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: dp, maximumFractionDigits: dp });
}

async function loadMileageEntries() {
	const list = $('#sp-mileage-list');
	const summary = $('#sp-mileage-summary');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';

	const start = $('#sp-mileage-filter-start')?.value || '';
	const end = $('#sp-mileage-filter-end')?.value || '';

	try {
		const res = await spAjax('site_pulse_get_mileage_entries', { start, end });
		if (!res.success) { list.innerHTML = '<div class="sp-empty-state"><p>Error loading entries.</p></div>'; return; }

		const entries = res.data.entries || [];
		mileageRate = parseFloat(res.data.rate) || mileageRate;

		if (entries.length === 0) {
			summary.innerHTML = '';
			list.innerHTML = '<div class="sp-empty-state"><p>No mileage entries yet. Click "+ Add a Day" to get started.</p></div>';
			return;
		}

		const totalMiles = entries.reduce((s, e) => s + parseFloat(e.total_miles || 0), 0);
		// Mileage total = (total miles × rate), rounded once — not the sum of each trip's
		// already-rounded reimbursement. Keeps the headline figure matching total × rate; the
		// per-trip $ column can sum a cent or two differently (rounding), which is fine here.
		const totalAmt = Math.round(totalMiles * mileageRate * 100) / 100;
		const totalTolls = entries.reduce((s, e) => s + parseFloat(e.total_tolls || 0), 0);
		const totalTrailer = entries.reduce((s, e) => s + parseFloat(e.total_trailer || 0), 0);
		const hasTrailer = totalTrailer > 0;
		const hasTolls = totalTolls > 0;       // still gates the per-entry table's Tolls column
		const pendingCount = entries.reduce((s, e) => s + (parseInt(e.pending_legs) > 0 ? 1 : 0), 0);

		let statCards = '';
		// Total leads (matches the other sections), then the breakdown.
		statCards += spStatCard('Total', `$${mileageNumFmt(totalAmt + totalTolls + totalTrailer)}`, 'dollar-bill');
		statCards += spStatCard('Miles', mileageNumFmt(totalMiles), 'speedometer');
		statCards += spStatCard('Mileage', `$${mileageNumFmt(totalAmt)}`, 'car');
		if (hasTrailer) statCards += spStatCard('Trailer', `$${mileageNumFmt(totalTrailer)}`, 'trailer');
		statCards += spStatCard('Tolls', `$${mileageNumFmt(totalTolls)}`, 'toll');
		if (pendingCount > 0) statCards += spStatCard('Pending', String(pendingCount), 'warning', 'warning');
		summary.innerHTML = `<div class="sp-stat-grid">${statCards}</div>`;

		// Per-leg table: each leg is its own row (Date shown once per day), capped by a bold
		// "Total for Day" row that carries the day's status + edit/delete. One <tbody> per day.
		const colCount = 6 + (hasTrailer ? 1 : 0) + (hasTolls ? 1 : 0) + 1;
		let html = '<div class="sp-card sp-table-card"><table class="sp-table sp-mileage-table"><thead class="sp-thead"><tr>'
			+ '<th>Date</th><th class="sp-mileage-charge">Charge&nbsp;To</th><th>Route</th><th>Purpose</th>'
			+ '<th class="sp-th-num">Miles</th><th class="sp-th-num">$</th>'
			+ (hasTrailer ? '<th class="sp-th-num">Trailer</th>' : '')
			+ (hasTolls ? '<th class="sp-th-num">Tolls</th>' : '')
			+ '<th class="sp-mileage-status">Status</th></tr></thead>';
		entries.forEach(e => {
			html += `<tbody class="sp-mileage-day" data-entry-id="${e.id}"><tr><td>${formatDate(e.entry_date)}</td>`
				+ `<td colspan="${colCount - 1}"><span class="unique sp-mileage-path-loading">Loading…</span></td></tr></tbody>`;
		});
		html += '</table></div>';
		list.innerHTML = html;
		markUniqueSpans(list);

		// Lazy-load each day's legs, then replace its tbody with one row per leg + a day-total row.
		entries.forEach(async (e) => {
			try {
				const r = await spAjax('site_pulse_get_mileage_entry', { entry_id: e.id });
				if (!r.success) return;
				const legs = r.data.legs || [];
				const tbody = list.querySelector(`tbody[data-entry-id="${e.id}"]`);
				if (!tbody) return;
				const legRate = parseFloat(r.data.entry?.rate_used) || mileageRate;
				const isPending = parseInt(e.pending_legs) > 0;
				const trailerD = parseFloat(e.total_trailer) || 0;
				const tollD = parseFloat(e.total_tolls) || 0;

				let rows = '';
				legs.forEach((leg, i) => {
					const charge = (leg.charge_to || '').trim();
					const purpose = (leg.purpose || '').trim();
					const base = leg.miles == null ? null : (parseFloat(leg.miles) || 0);
					const adj = parseFloat(leg.miles_adjust) || 0;
					const reasonText = (leg.miles_adjust_reason || '').trim();
					const eff = base == null ? null : base + adj;

					let milesInner = '—';
					if (base != null) {
						const badge = adj ? ` <span class="sp-leg-adjust">(${adj > 0 ? '+' : ''}${mileageNumFmt(adj)})</span>` : '';
						const title = !adj ? "Click to adjust this trip's miles"
							: `Calculated ${mileageNumFmt(base)} mi${reasonText ? ' · ' + reasonText : ''} · click to edit`;
						milesInner = `<span class="sp-leg-edit" data-entry-id="${e.id}" data-leg-id="${leg.id}" data-base="${base}" data-adjust="${adj}" data-reason="${esc(reasonText)}" title="${esc(title)}"><span class="sp-leg-val">${mileageNumFmt(eff)}</span>${badge}</span>`;
					}
					const dollarInner = eff == null ? '—' : '$' + mileageNumFmt(eff * legRate);
					const tc = parseFloat(leg.toll_cost);
					const tollInner = (leg.toll_cost && tc > 0) ? '$' + mileageNumFmt(tc) : '—';

					rows += '<tr class="sp-mileage-leg">';
					rows += `<td class="sp-m-date">${i === 0 ? formatDate(e.entry_date) : ''}</td>`;
					rows += `<td class="sp-mileage-charge">${esc(charge)}</td>`;
					rows += `<td class="sp-mileage-leg-route">${esc(leg.from_name || '?')} → ${esc(leg.to_name || '?')}</td>`;
					rows += `<td class="sp-mileage-leg-purpose">${purpose ? esc(purpose) : ''}</td>`;
					rows += `<td class="sp-num">${milesInner}</td>`;
					rows += `<td class="sp-num">${dollarInner}</td>`;
					if (hasTrailer) rows += '<td class="sp-num"></td>';
					if (hasTolls) rows += `<td class="sp-num">${tollInner}</td>`;
					// The status + actions live in ONE cell that spans the whole day (every leg + the
					// Total row), rendered on the first leg so the Pending pill sits at the TOP of the
					// Status column — aligned with the date/first leg — and the buttons drop to the bottom.
					if (i === 0) {
						rows += `<td class="sp-m-status" rowspan="${legs.length + 1}"><div class="sp-m-status-inner">`
							+ (isPending ? '<span class="unique sp-status-badge sp-status-pending">Pending</span>' : '<span class="unique sp-status-badge sp-status-submitted">Final</span>')
							+ `<div class="sp-mileage-row-actions">${iconBtn('edit', 'sp-mileage-edit-btn', `data-id="${e.id}"`)}${iconBtn('delete', 'sp-mileage-delete-btn', `data-id="${e.id}"`)}</div></div></td>`;
					}
					rows += '</tr>';
				});

				rows += '<tr class="sp-mileage-day-total">';
				rows += '<td></td><td></td><td class="sp-mileage-day-total-label">Total for Day</td><td></td>';
				rows += `<td class="sp-num">${mileageNumFmt(parseFloat(e.total_miles) || 0)}</td>`;
				rows += `<td class="sp-num">$${mileageNumFmt(parseFloat(e.reimbursement_amount) || 0)}</td>`;
				if (hasTrailer) rows += `<td class="sp-num">${trailerD > 0 ? '$' + mileageNumFmt(trailerD) : '—'}</td>`;
				if (hasTolls) rows += `<td class="sp-num">${tollD > 0 ? '$' + mileageNumFmt(tollD) : '—'}</td>`;
					// Status cell intentionally omitted - the first leg's rowspan status cell already covers this column for the whole day.
				rows += '</tr>';

				if (!legs.length) rows = `<tr><td>${formatDate(e.entry_date)}</td><td colspan="${colCount - 1}">—</td></tr>`;

				tbody.innerHTML = rows;
				markUniqueSpans(tbody);
				$$('.sp-mileage-edit-btn', tbody).forEach(b => b.addEventListener('click', () => showMileageForm(parseInt(b.dataset.id))));
				$$('.sp-mileage-delete-btn', tbody).forEach(b => b.addEventListener('click', async () => {
					if (!confirm('Delete this mileage entry?')) return;
					const rr = await spAjax('site_pulse_delete_mileage_entry', { entry_id: b.dataset.id });
					if (rr.success) loadMileageEntries();
					else alert(rr.data?.message || 'Error.');
				}));
			} catch (err) {}
		});
	} catch (err) {
		list.innerHTML = '<div class="sp-empty-state"><p>Error loading entries.</p></div>';
	}
}

// --- Mileage day auto-save (replaces the explicit "Save Day" button) ---
// The form persists itself on every change. The first valid save of a new day creates the
// entry and captures its id, so later edits update that same row instead of duplicating it.
let mileageFormEntryId = 0;
let mileageSaveTimer = null;
let mileageSaving = false;
let mileageResavePending = false;
let mileageFormDirty = false;   // any edit this session → the Back button becomes "Cancel"
let mileageBlockedDate = '';    // a date the server rejected as already-logged; stops autosave from retrying/re-alerting until the date changes

// Flag the form as edited and flip the discard button's label from "Back" to "Cancel".
function markMileageDirty() {
	mileageFormDirty = true;
	const b = document.querySelector('.sp-mileage-discard-btn');
	if (b && b.textContent !== 'Cancel') b.textContent = 'Cancel';
}

function queueMileageAutoSave() {
	markMileageDirty();
	clearTimeout(mileageSaveTimer);
	mileageSaveTimer = setTimeout(autoSaveMileageEntry, 600);
}

async function autoSaveMileageEntry() {
	const form = $('#sp-mileage-form');
	if (!form) return;                          // form closed — nothing to save
	if (mileageSaving) { mileageResavePending = true; return; }

	// Drop empty stop picks, keeping purposes/tolls/trailers/charge-to/notes aligned.
	const allStops = readMileageStops(), allPurposes = readMileageStopPurposes(), allTolls = readMileageStopTolls(), allTrailers = readMileageStopTrailers(), allCharge = readMileageStopChargeTo(), allNotes = readMileageStopNotes();
	const stopsArr = [], purposesArr = [], tollsArr = [], trailersArr = [], chargeArr = [], notesArr = [];
	allStops.forEach((sid, i) => { if (sid > 0) { stopsArr.push(sid); purposesArr.push(allPurposes[i] || ''); tollsArr.push(allTolls[i] || 0); trailersArr.push(allTrailers[i] || 0); chargeArr.push(allCharge[i] || ''); notesArr.push(allNotes[i] || ''); } });
	const min = mileageHomeId ? 1 : 2;
	if (stopsArr.length < min) return;          // not a valid day yet — wait for more stops

	// One trip per day: if the server already told us this date is taken (create mode only), don't keep
	// retrying — wait until they change the date. Editing an existing day (entry id set) is unaffected.
	if (!mileageFormEntryId && form.entry_date.value === mileageBlockedDate) return;

	const data = {
		entry_id: mileageFormEntryId || '',
		entry_date: form.entry_date.value,
		stops: stopsArr, purposes: purposesArr, tolls: tollsArr, trailers: trailersArr, charge_to: chargeArr, line_notes: notesArr,
		auto_return_home: mileageHomeId ? 1 : 0,
		end_toll: readMileageEndToll(), end_trailer: readMileageEndTrailer(), end_charge: readMileageEndChargeTo(),
		end_location: readMileageEndLocation(),
	};

	mileageSaving = true;
	try {
		const r = await spAjax('site_pulse_save_mileage_entry', data);
		if (r.success) {
			mileageFormEntryId = parseInt(r.data.entry_id) || mileageFormEntryId;
			spFlash('Saved');
		} else if (r.data?.code === 'duplicate_day') {
			// A trip already exists for this date — block further retries for it and let them jump to it.
			mileageBlockedDate = data.entry_date;
			const existingId = parseInt(r.data.existing_id) || 0;
			if (existingId && confirm((r.data.message || 'You already have mileage logged for this date.') + '\n\nOpen that day to edit it? Anything entered here will be discarded.')) {
				setTimeout(() => showMileageForm(existingId), 0);
			}
		} else {
			spFlash(r.data?.message || 'Save failed');
		}
	} catch (e) { /* transient — next change will retry */ }
	mileageSaving = false;
	if (mileageResavePending) { mileageResavePending = false; queueMileageAutoSave(); }
}

async function showMileageForm(entryId = 0) {
	const wrap = $('#sp-mileage-form-wrap');
	const list = $('#sp-mileage-list');
	const summary = $('#sp-mileage-summary');
	if (!wrap) return;

	mileageFormEntryId = entryId || 0; // create mode (0) until the first save returns an id
	mileageFormDirty = false;
	mileageBlockedDate = '';           // fresh form → clear any prior "already logged" block
	clearTimeout(mileageSaveTimer);

	await loadMileageLocations();

	let entry = null;
	let stops = [];               // editable MIDDLE stops only
	let purposes = [];            // per-stop business purpose, parallel to stops
	let tolls = [];               // per-stop toll flag (arriving leg has tolls), parallel to stops
	let trailers = [];            // per-stop trailer flag (arriving leg pulled a trailer)
	let chargeTos = [];           // per-stop "Charge To" store number (or '99' = Misc), parallel to stops
	let lineNotes = [];           // per-stop free-text note, parallel to stops
	let endCharge = '';           // "Charge To" for the final leg home (END bookend)
	let endToll = 0;              // toll flag on the final leg home (END bookend)
	let endTrailer = 0;           // trailer flag on the final leg home (END bookend)
	let endLoc = 0;               // END-location override (0 = day ends at Home / round trip)
	let autoReturn = true;
	if (entryId) {
		const res = await spAjax('site_pulse_get_mileage_entry', { entry_id: entryId });
		if (res.success) {
			entry = res.data.entry;
			autoReturn = entry ? !!parseInt(entry.auto_return_home) : true;
			const legs = res.data.legs || [];
			// Rebuild the full sequence + per-node purpose/toll/trailer (all = arriving at that node).
			let seq = [], seqP = [], seqT = [], seqTr = [], seqC = [], seqN = [];
			if (legs.length > 0) {
				seq.push(parseInt(legs[0].from_location_id)); seqP.push(''); seqT.push(0); seqTr.push(0); seqC.push(''); seqN.push('');
				legs.forEach(l => { seq.push(parseInt(l.to_location_id)); seqP.push(l.purpose || ''); seqT.push(parseInt(l.has_toll) ? 1 : 0); seqTr.push(parseInt(l.has_trailer) ? 1 : 0); seqC.push(l.charge_to || ''); seqN.push(l.note || ''); });
			}
			// Stored legs are [home, ...middle, home?]. Strip the home bookends so the
			// form shows only the editable middle; the home start/end are re-applied by render.
			if (mileageHomeId) {
				if (seq.length && seq[0] === mileageHomeId) { seq = seq.slice(1); seqP = seqP.slice(1); seqT = seqT.slice(1); seqTr = seqTr.slice(1); seqC = seqC.slice(1); seqN = seqN.slice(1); }
				// The final node is the END bookend — Home on a round trip, or an overridden
				// destination on a rare non-round-trip day. Keep its toll/trailer/charge, remember the
				// override when it isn't Home, then strip it so the form shows only the middle stops.
				if (autoReturn && seq.length) {
					endToll = seqT[seqT.length - 1] ? 1 : 0;
					endTrailer = seqTr[seqTr.length - 1] ? 1 : 0;
					endCharge = seqC[seqC.length - 1] || '';
					if (seq[seq.length - 1] !== mileageHomeId) endLoc = seq[seq.length - 1];
					seq = seq.slice(0, -1); seqP = seqP.slice(0, -1); seqT = seqT.slice(0, -1); seqTr = seqTr.slice(0, -1); seqC = seqC.slice(0, -1); seqN = seqN.slice(0, -1);
				}
			}
			stops = seq; purposes = seqP; tolls = seqT; trailers = seqTr; chargeTos = seqC; lineNotes = seqN;
		}
	}
	const minMiddle = mileageHomeId ? 1 : 2;
	while (stops.length < minMiddle) { stops.push(0); purposes.push(''); tolls.push(0); trailers.push(0); chargeTos.push(''); lineNotes.push(''); }

	setMileageListChrome(true); // hide list + report controls while the form is open
	wrap.hidden = false;

	const today = new Date().toISOString().split('T')[0];
	const date = entry?.entry_date || today;

	// Snapshot the original day so Back/Cancel can revert this session's auto-saved edits
	// (existing entries only — a brand-new day is just removed on Cancel).
	const _mileageOrig = entryId ? {
		date, autoReturn,
		stops: stops.slice(), purposes: purposes.slice(), tolls: tolls.slice(),
		trailers: trailers.slice(), chargeTos: chargeTos.slice(), lineNotes: lineNotes.slice(),
		endCharge, endToll, endTrailer, endLoc,
	} : null;

	let html = '<div class="sp-card-header sp-header-grid sp-report-form-header">';
	html += `<h3>${entryId ? 'Edit' : 'New'} Mileage Day</h3>`;
	html += '</div>';
	html += '<form id="sp-mileage-form" class="sp-card-body">';
	html += `<input type="hidden" name="entry_id" value="${entryId || ''}">`;
	html += '<div class="sp-mileage-form-top">';
	html += '<div class="sp-form-group">';
	html += `<label>Date</label><input type="date" name="entry_date" class="sp-input" value="${date}" required>`;
	html += '</div>';
	html += '<div class="sp-form-group">';
	html += '<label>Route</label>';
	html += '<div class="sp-quickentry">';
	html += '<input type="text" id="sp-mileage-quick" class="sp-input" placeholder="Quick add — type or speak stops, e.g. Carrollton, Garland, Burleson">';
	html += '<button type="button" class="sp-btn sp-btn-ghost sp-msg-mic-btn unique" id="sp-mileage-mic" title="Speak stops" aria-label="Speak stops"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><path d="M12 19v4M8 23h8"/></svg></button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-quick-fill">Fill</button>';
	html += '</div>';
	html += '<div class="sp-quickentry-note" id="sp-mileage-quick-note" hidden></div>';
	html += '</div>';
	html += '</div>';
	html += '<div class="sp-mileage-stops" id="sp-mileage-stops"></div>';
	html += '<div class="sp-mileage-totals" id="sp-mileage-totals"></div>';
	html += '<div class="sp-report-form-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary sp-mileage-log-btn">Log Day</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-discard-btn">Back</button>';
	if (entryId) html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-delete-day-btn">Delete</button>';
	html += '</div>';
	html += '</form>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	renderMileageStops(stops, autoReturn, purposes, tolls, trailers, endToll, endTrailer, chargeTos, endCharge, lineNotes, endLoc);

	// Log Day: commit — flush any pending auto-save, then close and refresh the list.
	$('.sp-mileage-log-btn', wrap)?.addEventListener('click', async () => {
		clearTimeout(mileageSaveTimer);
		await autoSaveMileageEntry();
		hideMileageForm();
		loadMileageEntries();
	});
	// Back / Cancel: leave WITHOUT keeping this session's edits. The form auto-saves as you go, so
	// for an existing day we revert it to the on-open snapshot; a brand-new day that auto-saved is
	// removed. No edits made → just close. (Label is "Back" until the first edit, then "Cancel".)
	$('.sp-mileage-discard-btn', wrap)?.addEventListener('click', async () => {
		clearTimeout(mileageSaveTimer);
		if (mileageFormDirty) {
			if (entryId && _mileageOrig) {
				renderMileageStops(_mileageOrig.stops, _mileageOrig.autoReturn, _mileageOrig.purposes, _mileageOrig.tolls, _mileageOrig.trailers, _mileageOrig.endToll, _mileageOrig.endTrailer, _mileageOrig.chargeTos, _mileageOrig.endCharge, _mileageOrig.lineNotes, _mileageOrig.endLoc);
				const dEl = $('input[name="entry_date"]', wrap); if (dEl) dEl.value = _mileageOrig.date;
				try { await autoSaveMileageEntry(); } catch (e) {}
			} else if (mileageFormEntryId) {
				try { await spAjax('site_pulse_delete_mileage_entry', { entry_id: mileageFormEntryId }); } catch (e) {}
				mileageFormEntryId = 0;
			}
		}
		hideMileageForm();
		loadMileageEntries();
	});
	// Delete: remove the entire day (existing entries only). Destructive → confirm.
	$('.sp-mileage-delete-day-btn', wrap)?.addEventListener('click', async () => {
		if (!confirm('Delete this entire mileage day?')) return;
		clearTimeout(mileageSaveTimer);
		const id = mileageFormEntryId || entryId;
		if (id) { try { await spAjax('site_pulse_delete_mileage_entry', { entry_id: id }); } catch (e) {} }
		mileageFormEntryId = 0;
		hideMileageForm();
		loadMileageEntries();
	});
	// Add Stop / Add Location buttons live inside the route list (above END) and are
	// wired in renderMileageStops, so they survive re-renders.
	$('#sp-mileage-quick-fill', wrap)?.addEventListener('click', () => applyMileageQuickEntry());
	$('#sp-mileage-quick', wrap)?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyMileageQuickEntry(); } });
	$('#sp-mileage-mic', wrap)?.addEventListener('click', () => startMileageVoice());

	// Auto-save: any field change (date, a stop dropdown, purpose, toll, notes) bubbles a
	// `change` to the form. Stop add/remove and quick-fill call queueMileageAutoSave directly.
	const form = $('#sp-mileage-form', wrap);
	form?.addEventListener('submit', (e) => e.preventDefault()); // Enter in a field shouldn't reload
	form?.addEventListener('change', () => queueMileageAutoSave());
	// Changing the date clears any "already logged" block so the newly-picked date can save.
	form?.querySelector('[name="entry_date"]')?.addEventListener('change', () => { mileageBlockedDate = ''; });
}

function hideMileageForm() {
	clearTimeout(mileageSaveTimer);
	const wrap = $('#sp-mileage-form-wrap');
	if (wrap) { wrap.hidden = true; wrap.innerHTML = ''; }
	setMileageListChrome(false); // restore the list + report controls
}

// Toggle the list-view chrome (summary, quick-ranges, date filters, action buttons, list)
// so the Add/Edit-a-Day form shows on its own, without the report controls bleeding in.
function setMileageListChrome(hidden) {
	const panel = $('#sp-panel-mileage');
	if (!panel) return;
	['.sp-mileage-actions', '#sp-mileage-toolbar', '#sp-mileage-summary', '#sp-mileage-list']
		.forEach(sel => panel.querySelector(sel)?.classList.toggle('sp-hidden', hidden));
}

// Does a purpose (matched by label, case-insensitive) require the additional-notes line?
// Free-typed purposes not in the library never require it.
function purposeRequiresNote(label) {
	const t = (label || '').trim().toLowerCase();
	if (!t) return false;
	return mileagePurposes.some(p => p && typeof p === 'object' && (p.label || '').toLowerCase() === t && p.requires_note);
}

// Show/hide a stop row's note line based on its purpose. Keeps a note visible if it already has
// text, so existing detail is never hidden out from under the driver.
function syncStopNote(row) {
	if (!row) return;
	const pInput = row.querySelector('.sp-mileage-stop-purpose');
	const noteEl = row.querySelector('.sp-mileage-stop-note');
	if (!pInput || !noteEl) return;
	noteEl.hidden = !(purposeRequiresNote(pInput.value) || (noteEl.value || '').trim() !== '');
}

// Options for a "Charge To" picker: every Home Base with a Location #, plus a fixed Accounting / Admin (#99) catch-all.
function mileageChargeOptionsHtml(selected) {
	const v = selected != null ? String(selected) : '';
	let h = `<option value=""${v === '' ? ' selected' : ''}>Charge to</option>`;
	mileageChargeOptions.forEach(o => {
		const num = String(o.number);
		h += `<option value="${esc(num)}"${v === num ? ' selected' : ''}>${esc(o.name)} (#${esc(num)})</option>`;
	});
	h += `<option value="99"${v === '99' ? ' selected' : ''}>Accounting / Admin (#99)</option>`;
	return h;
}

function renderMileageStops(stops, autoReturn = true, purposes = [], tolls = [], trailers = [], endToll = 0, endTrailer = 0, chargeTos = [], endCharge = '', notes = [], endLoc = 0) {
	const wrap = $('#sp-mileage-stops');
	if (!wrap) return;
	const minMiddle = mileageHomeId ? 1 : 2;
	const homeLoc = mileageHomeId ? mileageLocations.find(l => parseInt(l.id) === mileageHomeId) : null;
	const homeName = homeLoc ? homeLoc.name : 'Home';
	let html = '';

	// On phones, render the Location/Purpose pickers as native <select> (full-screen picker) instead of
	// the type-to-search <input list=datalist> combos, which on mobile show that awkward suggestion bar
	// above the keyboard. Desktop keeps the combos. Same hidden/text source-of-truth fields either way.
	const isMobile = window.matchMedia('(max-width: 860px)').matches;
	const locOptionsHtml = (selId) => {
		let o = '<option value="">Choose a location</option>';
		mileageLocations.forEach(l => {
			if (parseInt(l.id) === mileageHomeId) return; // home is a bookend, never a mid-stop
			const label = l.status === 'pending' ? `${l.name} (pending)` : l.name;
			o += `<option value="${esc(label)}"${parseInt(l.id) === selId ? ' selected' : ''}>${esc(label)}</option>`;
		});
		return o;
	};
	const purposeLabels = () => mileagePurposes.map(p => (typeof p === 'string') ? p : (p.label || '')).filter(Boolean);

	// Shared datalist of common business purposes (free text still allowed).
	html += '<datalist id="sp-mileage-purpose-list">';
	mileagePurposes.forEach(p => { const lbl = (typeof p === 'string') ? p : (p.label || ''); if (lbl) html += `<option value="${esc(lbl)}">`; });
	html += '</datalist>';

	// Shared datalist of pickable locations — lets the location field type-to-search like the
	// purpose field. The visible input holds the label; a hidden sibling holds the resolved id.
	html += '<datalist id="sp-mileage-loc-list">';
	mileageLocations.forEach(l => {
		if (parseInt(l.id) === mileageHomeId) return; // home is a bookend, never a mid-stop
		const label = l.status === 'pending' ? `${l.name} (pending)` : l.name;
		html += `<option value="${esc(label)}">`;
	});
	html += '</datalist>';

	// Locked round-trip START bookend — Home, when a home base is configured.
	if (mileageHomeId) {
		html += '<div class="sp-mileage-stop sp-mileage-stop-fixed">';
		html += '<span class="unique sp-mileage-bookend-label">START</span>';
		html += `<div class="sp-mileage-stop-home">${esc(homeName)}</div>`;
		html += '</div>';
	} else {
		html += '<div class="sp-mileage-home-hint unique">No home base set for this driver, so the day isn\'t auto-bookended with Home. Set one in <strong>Users → Home Base</strong> to start &amp; end each day at home automatically.</div>';
	}

	// Editable middle stops: location select + business purpose.
	stops.forEach((stopId, idx) => {
		const purpose = purposes[idx] || '';
		const note = notes[idx] || '';
		const trailer = trailers[idx] ? 1 : 0;
		const selLoc = stopId ? mileageLocations.find(l => parseInt(l.id) === stopId) : null;
		const selLabel = selLoc ? (selLoc.status === 'pending' ? `${selLoc.name} (pending)` : selLoc.name) : '';
		html += '<div class="sp-mileage-stop sp-mileage-stop-row">';
		html += `<span class="unique sp-mileage-stop-num">${idx + 1}.</span>`;
		// Type-to-search location picker: visible input + hidden id (read by readMileageStops).
		html += '<div class="sp-combo sp-mileage-stop-loc-wrap">';
		if (isMobile) {
			html += `<select class="sp-select sp-mileage-stop-loc${stopId ? '' : ' sp-loc-empty'}">${locOptionsHtml(stopId)}</select>`;
		} else {
			html += `<input type="text" class="sp-input sp-combo-input sp-mileage-stop-loc" list="sp-mileage-loc-list" placeholder="Choose a location" autocomplete="off" value="${esc(selLabel)}">`;
		}
		html += `<input type="hidden" class="sp-mileage-stop-select" value="${stopId || 0}">`;
		html += '</div>';
		// "Charge To": which store this leg's mileage is billed to. Required per leg — an empty one
		// keeps the day Pending. Greyed like a placeholder until a store is picked.
		const chargeTo = chargeTos[idx] != null ? String(chargeTos[idx]) : '';
		html += `<select class="sp-select sp-mileage-charge sp-mileage-stop-charge${chargeTo === '' ? ' sp-charge-empty' : ''}" title="Charge this trip to a store">${mileageChargeOptionsHtml(chargeTo)}</select>`;
		// Purpose + trailer flag share the purpose column. (Tolls are no longer flagged here —
		// they're matched from the driver's uploaded toll CSV in Reconcile Tolls.)
		html += '<div class="sp-mileage-stop-purpose-cell">';
		if (isMobile) {
			const labels = purposeLabels();
			const isCustom = purpose !== '' && !labels.includes(purpose);
			let o = '<option value="">Business purpose</option>';
			labels.forEach(lbl => { o += `<option value="${esc(lbl)}"${lbl === purpose ? ' selected' : ''}>${esc(lbl)}</option>`; });
			o += `<option value="__other__"${isCustom ? ' selected' : ''}>Other…</option>`;
			html += `<select class="sp-select sp-mileage-purpose-pick">${o}</select>`;
			// The text field stays the source of truth read by readMileageStopPurposes(); shown only for "Other".
			html += `<input type="text" class="sp-input sp-mileage-stop-purpose sp-mileage-purpose-custom" placeholder="Type a purpose" value="${esc(purpose)}"${isCustom ? '' : ' hidden'}>`;
		} else {
			html += `<input type="text" class="sp-input sp-combo-input sp-mileage-stop-purpose" list="sp-mileage-purpose-list" placeholder="Business purpose" value="${esc(purpose)}">`;
		}
		html += '</div>';
		html += `<label class="sp-mileage-trailer-toggle" title="Check if a trailer was pulled on this leg — the extra trailer rate per mile is added."><input type="checkbox" class="sp-mileage-stop-trailer"${trailer ? ' checked' : ''}><span class="sp-mileage-toll-name">Trailer</span></label>`;
		if (stops.length > minMiddle) {
			html += `<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete sp-mileage-stop-remove" data-idx="${idx}" aria-label="Remove stop" title="Remove stop">${ICON_DELETE}</button>`;
		}
		// Per-stop note on its own line beneath the row — e.g. who you met with. Hidden until the
		// chosen purpose is flagged "Requires note" in settings (or an existing note is present).
		const showNote = purposeRequiresNote(purpose) || (note.trim() !== '');
		html += `<input type="text" class="sp-input sp-mileage-stop-note" placeholder="Additional details required" value="${esc(note)}"${showNote ? '' : ' hidden'}>`;
		html += '</div>';
	});

	// Add buttons sit just above END, so the next-stop controls stay next to the active stop.
	html += '<div class="sp-mileage-stop-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-add-stop">+ Add Stop</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-add-loc">+ Add Destination</button>';
	html += '</div>';

	// END bookend — defaults to Home (round trip), but the locked Home is CLICKABLE so the rare
	// non-round-trip day can end somewhere else. endLoc (a location id) overrides Home when set;
	// clearing the picker reverts to Home. The drive can carry its own trailer flag like a stop.
	if (mileageHomeId) {
		const endOverride = endLoc ? mileageLocations.find(l => parseInt(l.id) === endLoc) : null;
		const endName = endOverride ? endOverride.name : homeName;
		html += '<div class="sp-mileage-stop sp-mileage-stop-fixed sp-mileage-stop-end">';
		html += '<span class="unique sp-mileage-bookend-label">END</span>';
		html += '<div class="sp-combo sp-mileage-end-loc-wrap">';
		html += `<div class="sp-mileage-stop-home sp-mileage-end-home${endOverride ? ' sp-mileage-end-home-changed' : ''}" tabindex="0" role="button" title="Click to end the day somewhere other than Home">${esc(endName)}</div>`;
		if (isMobile) {
			html += `<select class="sp-select sp-mileage-end-loc" hidden>${locOptionsHtml(endLoc)}</select>`;
		} else {
			html += `<input type="text" class="sp-input sp-combo-input sp-mileage-end-loc" list="sp-mileage-loc-list" placeholder="End location (blank = Home)" autocomplete="off" value="${esc(endOverride ? endName : '')}" hidden>`;
		}
		html += `<input type="hidden" class="sp-mileage-end-select" value="${endLoc || 0}">`;
		html += '</div>';
		// The drive home is also charged to a store — required, same as the stops above.
		html += `<select class="sp-select sp-mileage-charge sp-mileage-end-charge${endCharge === '' ? ' sp-charge-empty' : ''}" title="Charge the drive home to a store">${mileageChargeOptionsHtml(endCharge)}</select>`;
		html += '<div class="sp-mileage-stop-purpose-cell sp-mileage-end-extras">';
		html += '</div>';
		html += `<label class="sp-mileage-trailer-toggle" title="Check if a trailer was pulled on the drive home — the extra trailer rate per mile is added."><input type="checkbox" class="sp-mileage-end-trailer"${endTrailer ? ' checked' : ''}><span class="sp-mileage-toll-name">Trailer</span></label>`;
		html += '</div>';
	}

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	// Resolve a typed/selected location label back to its id (0 if it isn't a real location).
	const resolveLocId = (label) => {
		const t = (label || '').trim();
		if (!t) return 0;
		const m = mileageLocations.find(l => {
			if (parseInt(l.id) === mileageHomeId) return false;
			const lbl = l.status === 'pending' ? `${l.name} (pending)` : l.name;
			return lbl === t;
		});
		return m ? parseInt(m.id) : 0;
	};
	$$('.sp-mileage-stop-loc', wrap).forEach(inp => {
		const apply = () => {
			const row = inp.closest('.sp-mileage-stop-row');
			const hidden = row?.querySelector('.sp-mileage-stop-select');
			const id = resolveLocId(inp.value);
			if (hidden) hidden.value = id;
			if (inp.tagName === 'SELECT') inp.classList.toggle('sp-loc-empty', !id); // mobile required-field cue
			// When a destination with pinned purposes is picked, pre-fill the (empty) purpose.
			const purposeInput = row?.querySelector('.sp-mileage-stop-purpose');
			if (id && purposeInput && !purposeInput.value.trim()) {
				const loc = mileageLocations.find(l => parseInt(l.id) === id);
				const pinned = locationPinnedPurposes(loc);
				if (pinned.length) {
					purposeInput.value = pinned[0];
					// Keep the mobile purpose <select> in step with the pre-filled value.
					const pick = row?.querySelector('.sp-mileage-purpose-pick');
					if (pick) {
						const known = Array.from(pick.options).some(o => o.value === pinned[0]);
						pick.value = known ? pinned[0] : '__other__';
						purposeInput.hidden = known;
					}
				}
			}
			syncStopNote(row); // a pinned purpose may itself require the note line
			updateMileageTotals();
		};
		inp.addEventListener('input', apply);
		inp.addEventListener('change', apply);
	});

	// END bookend: the locked Home is clickable — click it to reveal a location picker and end the
	// day elsewhere (non-round-trip). Leaving it blank / unresolved reverts to Home.
	const endWrap = $('.sp-mileage-end-loc-wrap', wrap);
	if (endWrap) {
		const endHome = $('.sp-mileage-end-home', endWrap);
		const endInput = $('.sp-mileage-end-loc', endWrap);
		const endHidden = $('.sp-mileage-end-select', endWrap);
		const homeLabel = homeLoc ? homeLoc.name : 'Home';
		const showEndPicker = () => {
			if (endHome) endHome.hidden = true;
			if (endInput) {
				endInput.hidden = false;
				endInput.focus();
				if (endInput.tagName !== 'SELECT' && endInput.select) endInput.select();
				// Pop the same location dropdown the stop pickers use (datalist / native select),
				// right on this click — it's a user gesture, so showPicker() is allowed.
				try { if (endInput.showPicker) endInput.showPicker(); } catch (e) {}
			}
		};
		endHome?.addEventListener('click', showEndPicker);
		endHome?.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showEndPicker(); } });
		const applyEnd = () => {
			const id = resolveLocId(endInput.value);   // 0 when empty / Home / unrecognized → revert to Home
			if (endHidden) endHidden.value = id;
			if (!id) {
				if (endInput) { endInput.value = ''; endInput.hidden = true; }
				if (endHome) { endHome.hidden = false; endHome.textContent = homeLabel; endHome.classList.remove('sp-mileage-end-home-changed'); }
			} else {
				const loc = mileageLocations.find(l => parseInt(l.id) === id);
				if (endHome) { endHome.textContent = loc ? loc.name : endInput.value; endHome.classList.add('sp-mileage-end-home-changed'); endHome.hidden = false; }
				if (endInput) endInput.hidden = true;
			}
			queueMileageAutoSave();
		};
		endInput?.addEventListener('change', applyEnd);
		endInput?.addEventListener('blur', applyEnd);
	}

	// Purpose change: reveal/hide that stop's note line (per the purpose's "Requires note" flag) and persist.
	$$('.sp-mileage-stop-purpose', wrap).forEach(inp => inp.addEventListener('input', () => {
		syncStopNote(inp.closest('.sp-mileage-stop-row'));
		queueMileageAutoSave();
	}));
	// Mobile purpose <select>: a normal pick fills the (hidden) text field; "Other…" reveals it to type a
	// custom purpose. Either way we fire the text field's input handler so notes/auto-save stay in sync.
	$$('.sp-mileage-purpose-pick', wrap).forEach(sel => sel.addEventListener('change', () => {
		const txt = sel.closest('.sp-mileage-stop-purpose-cell')?.querySelector('.sp-mileage-stop-purpose');
		if (!txt) return;
		if (sel.value === '__other__') {
			txt.hidden = false;
			txt.focus();
		} else {
			txt.value = sel.value;
			txt.hidden = true;
		}
		txt.dispatchEvent(new Event('input', { bubbles: true }));
	}));
	// Picking a "Charge To" just needs to persist (doesn't affect totals). Toggle the placeholder
	// grey when it returns to empty. Covers both the stop rows and the END drive-home row.
	$$('.sp-mileage-charge', wrap).forEach(sel => sel.addEventListener('change', () => {
		sel.classList.toggle('sp-charge-empty', sel.value === '');
		queueMileageAutoSave();
	}));
	// Per-stop notes just need to persist as they're typed.
	$$('.sp-mileage-stop-note', wrap).forEach(ta => ta.addEventListener('input', queueMileageAutoSave));
	$$('.sp-mileage-stop-remove', wrap).forEach(b => b.addEventListener('click', () => {
		const s = readMileageStops(), p = readMileageStopPurposes(), t = readMileageStopTolls(), tr = readMileageStopTrailers(), c = readMileageStopChargeTo(), n = readMileageStopNotes();
		const i = parseInt(b.dataset.idx);
		s.splice(i, 1); p.splice(i, 1); t.splice(i, 1); tr.splice(i, 1); c.splice(i, 1); n.splice(i, 1);
		while (s.length < minMiddle) { s.push(0); p.push(''); t.push(0); tr.push(0); c.push(''); n.push(''); }
		renderMileageStops(s, getReturnHome(), p, t, tr, readMileageEndToll(), readMileageEndTrailer(), c, readMileageEndChargeTo(), n, readMileageEndLocation());
		updateMileageTotals();
		queueMileageAutoSave();
	}));
	$('#sp-mileage-add-stop', wrap)?.addEventListener('click', () => {
		const s = readMileageStops(), p = readMileageStopPurposes(), t = readMileageStopTolls(), tr = readMileageStopTrailers(), c = readMileageStopChargeTo(), n = readMileageStopNotes();
		s.push(0); p.push(''); t.push(0); tr.push(0); c.push(''); n.push('');
		renderMileageStops(s, getReturnHome(), p, t, tr, readMileageEndToll(), readMileageEndTrailer(), c, readMileageEndChargeTo(), n, readMileageEndLocation());
	});
	$('#sp-mileage-add-loc', wrap)?.addEventListener('click', () => showAddLocationModal());

	updateMileageTotals();
}

function readMileageStops() {
	return $$('.sp-mileage-stop-select').map(s => parseInt(s.value) || 0);
}

// "Charge To" for the locked END (drive-home) leg — separate from the per-stop charges.
function readMileageEndChargeTo() {
	const s = $('.sp-mileage-end-charge');
	return s ? (s.value || '') : '';
}

// The END-location override id (0 = day ends at Home). Set when the driver clicks the locked END and
// picks a different final destination.
function readMileageEndLocation() {
	const h = $('.sp-mileage-end-select');
	return h ? (parseInt(h.value) || 0) : 0;
}

function readMileageStopChargeTo() {
	return $$('.sp-mileage-stop-charge').map(s => s.value || '');
}

function readMileageStopNotes() {
	return $$('.sp-mileage-stop-note').map(t => t.value.trim());
}

function readMileageStopPurposes() {
	return $$('.sp-mileage-stop-purpose').map(i => i.value.trim());
}

// Toll flags were removed from the form — tolls are now matched from the driver's uploaded
// toll CSV (Reconcile Tolls). These readers return empty now so the existing call sites that
// still thread a tolls[] array keep working without change; the server ignores them.
function readMileageStopTolls() {
	return $$('.sp-mileage-stop-toll').map(i => i.checked ? 1 : 0);
}

function readMileageStopTrailers() {
	return $$('.sp-mileage-stop-trailer').map(i => i.checked ? 1 : 0);
}

// Toll / trailer flags for the final leg home (the locked END bookend).
function readMileageEndToll() {
	const cb = $('.sp-mileage-end-toll');
	return cb && cb.checked ? 1 : 0;
}

function readMileageEndTrailer() {
	const cb = $('.sp-mileage-end-trailer');
	return cb && cb.checked ? 1 : 0;
}

// ---- Quick entry (typed/spoken shorthand → fills the route, then user reviews & saves) ----

function mileageNorm(s) {
	return (s || '').toLowerCase().replace(/[^a-z0-9 ]/g, ' ').replace(/\s+/g, ' ').trim();
}

// Best matching approved location id for a shorthand token (e.g. "carrollton"), or 0.
function quickMatchLocation(token) {
	const words = mileageNorm(token).split(' ').filter(Boolean);
	if (!words.length) return 0;
	const cands = mileageLocations.filter(l => {
		if (parseInt(l.id) === mileageHomeId) return false; // home is a bookend, not a stop
		const n = mileageNorm(l.name);
		return words.every(w => n.includes(w));
	});
	if (!cands.length) return 0;
	cands.sort((a, b) => (a.name || '').length - (b.name || '').length); // most specific match
	return parseInt(cands[0].id);
}

// Parse the quick-entry text into stops and render them into the form for review.
function applyMileageQuickEntry() {
	const inp = $('#sp-mileage-quick');
	const note = $('#sp-mileage-quick-note');
	if (!inp) return;
	const raw = inp.value.trim();
	if (!raw) return;
	const tokens = raw.split(/[,\n;]+/).map(s => s.trim()).filter(Boolean);
	const ids = [], purposes = [], unmatched = [];
	tokens.forEach(t => {
		const id = quickMatchLocation(t);
		if (id) {
			ids.push(id);
			const pp = locationPinnedPurposes(mileageLocations.find(l => parseInt(l.id) === id));
			purposes.push(pp.length ? pp[0] : '');
		} else {
			unmatched.push(t);
		}
	});
	if (!ids.length) {
		if (note) { note.hidden = false; note.textContent = `Couldn't match any stops. Try names like "Carrollton" or "Garland".`; }
		return;
	}
	const minMiddle = mileageHomeId ? 1 : 2;
	while (ids.length < minMiddle) { ids.push(0); purposes.push(''); }
	renderMileageStops(ids, getReturnHome(), purposes, ids.map(() => 0), ids.map(() => 0), readMileageEndToll(), readMileageEndTrailer(), [], readMileageEndChargeTo(), [], readMileageEndLocation());
	queueMileageAutoSave();
	if (note) {
		if (unmatched.length) { note.hidden = false; note.textContent = `Added ${ids.filter(Boolean).length}. Couldn't match: ${unmatched.join(', ')} — add those manually below.`; }
		else { note.hidden = false; note.textContent = `Added ${ids.length} stop${ids.length !== 1 ? 's' : ''}.`; }
	}
}

// Speech-to-text into the quick-entry box (Web Speech API; Chrome/Edge/Safari).
function startMileageVoice() {
	const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
	if (!SR) { alert('Voice input isn\'t supported in this browser — try Chrome, or type the stops.'); return; }
	const mic = $('#sp-mileage-mic');
	const inp = $('#sp-mileage-quick');
	const rec = new SR();
	rec.lang = 'en-US';
	rec.interimResults = false;
	rec.maxAlternatives = 1;
	rec.onresult = (e) => {
		const text = Array.from(e.results).map(r => r[0].transcript).join(' ').trim();
		if (inp && text) inp.value = inp.value ? `${inp.value}, ${text}` : text;
	};
	rec.onerror = () => {};
	rec.onend = () => mic?.classList.remove('sp-mic-active');
	mic?.classList.add('sp-mic-active');
	try { rec.start(); } catch (e) { mic?.classList.remove('sp-mic-active'); }
}

// Auto-writes a defensible trip summary from the current stops + their purposes,
// e.g. "Babe's Arlington (Manager meeting), Babe's Garland (Store visit)". ~200 char cap.
function buildMileageSummary() {
	const stops = readMileageStops(), purposes = readMileageStopPurposes();
	const parts = [];
	stops.forEach((id, i) => {
		if (!id) return;
		const loc = mileageLocations.find(l => parseInt(l.id) === parseInt(id));
		if (!loc) return;
		const p = (purposes[i] || '').trim();
		parts.push(p ? `${loc.name} (${p})` : loc.name);
	});
	if (!parts.length) return '';
	let s = parts.join(', ');
	if (s.length > 200) s = s.slice(0, 197) + '…';
	return s;
}

function findMileageDistance(a, b) {
	if (!a || !b || a === b) return null;
	// Optimistic local lookup is fine for instant feedback only;
	// the server is the authority on miles.
	return null;
}

function updateMileageTotals() {
	const totals = $('#sp-mileage-totals');
	if (!totals) return;
	const stops = readMileageStops();
	const valid = stops.filter(v => v > 0);
	if (valid.length < (mileageHomeId ? 1 : 2)) { totals.innerHTML = ''; return; }

	let pendingLocs = 0;
	stops.forEach(id => {
		const loc = mileageLocations.find(l => parseInt(l.id) === id);
		if (loc && loc.status === 'pending') pendingLocs++;
	});

	const note = pendingLocs > 0
		? `<span class="unique sp-text-warning">${pendingLocs} location${pendingLocs > 1 ? 's' : ''} awaiting admin approval — totals will calculate once approved.</span>`
		: `<span class="unique sp-text-secondary">Distances calculated server-side after save.</span>`;
	totals.innerHTML = note;
}

function showAddLocationModal() {
	const existing = $('#sp-mileage-loc-modal');
	if (existing) existing.remove();

	// With approvals off, the driver may save the destination straight to the database —
	// either globally (everyone can pick it) or kept personal (only they see it). The
	// checkbox controls that; with approvals on it's the usual "send for approval" flow.
	const needsApproval = mileageRequireApproval;
	const intro = needsApproval
		? `It'll be sent for admin approval. Once approved, the distance will be calculated automatically.`
		: `The distance will be calculated automatically. Tick "Save to Global Database" to share it with every driver; leave it off to keep it just for your own trips.`;
	const actionLabel = needsApproval ? 'Submit for Approval' : 'Submit';
	const globalRow = needsApproval ? '' :
		`<div class="sp-form-group"><label class="sp-reminder-toggle"><input type="checkbox" id="sp-loc-global"> Save to Global Database</label></div>`;

	const modal = document.createElement('div');
	modal.id = 'sp-mileage-loc-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal">
			<h3>Add a New Location</h3>
			<p class="sp-text-secondary">${intro}</p>
			<div class="sp-form-group"><label>Name</label><input type="text" id="sp-loc-name" class="sp-input" placeholder="Big D Mechanical"></div>
			<div class="sp-form-group"><label>Address</label><input type="text" id="sp-loc-address" class="sp-input" placeholder="123 Main St, City, TX 75001"></div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
				<div class="sp-form-group"><label>Type</label><select id="sp-loc-type" class="sp-select"><option value="vendor">Vendor</option><option value="other">Other</option></select></div>
				<div class="sp-form-group"><label>Category</label><select id="sp-loc-category" class="sp-select">${MILEAGE_CATEGORIES.map(c => `<option value="${c}">${c}</option>`).join('')}</select></div>
			</div>
			<div class="sp-form-group"><label>Business or personal</label><select id="sp-loc-business" class="sp-select"><option value="1">Business</option><option value="0">Personal</option></select></div>
			<div class="sp-form-group"><label>Notes (optional)</label><textarea id="sp-loc-notes" class="sp-textarea" rows="2"></textarea></div>
			${globalRow}
			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-loc-submit">${actionLabel}</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-loc-cancel">Cancel</button>
			</div>
		</div>
	`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);
	attachPlacesAutocomplete(modal.querySelector('#sp-loc-name'), modal.querySelector('#sp-loc-address'));

	const close = () => modal.remove();
	modal.querySelector('#sp-loc-cancel').addEventListener('click', close);
	modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
	modal.querySelector('#sp-loc-submit').addEventListener('click', async () => {
		const name = modal.querySelector('#sp-loc-name').value.trim();
		const address = modal.querySelector('#sp-loc-address').value.trim();
		const type = modal.querySelector('#sp-loc-type').value;
		const category = modal.querySelector('#sp-loc-category').value;
		const is_business = modal.querySelector('#sp-loc-business').value;
		const notes = modal.querySelector('#sp-loc-notes').value.trim();
		const save_global = modal.querySelector('#sp-loc-global')?.checked ? 1 : 0;
		if (!name || !address) { alert('Name and address required.'); return; }
		const r = await spAjax('site_pulse_add_mileage_location', { name, address, location_type: type, category, is_business, notes, save_global });
		if (r.success) {
			await loadMileageLocations();
			const stops = readMileageStops(), purposes = readMileageStopPurposes(), tolls = readMileageStopTolls(), trailers = readMileageStopTrailers(), charge = readMileageStopChargeTo(), lnotes = readMileageStopNotes();
			// Auto-fill the first empty stop with the new location
			const emptyIdx = stops.findIndex(v => !v);
			if (emptyIdx >= 0) stops[emptyIdx] = parseInt(r.data.id);
			else { stops.push(parseInt(r.data.id)); purposes.push(''); tolls.push(0); trailers.push(0); charge.push(''); lnotes.push(''); }
			renderMileageStops(stops, getReturnHome(), purposes, tolls, trailers, readMileageEndToll(), readMileageEndTrailer(), charge, readMileageEndChargeTo(), lnotes, readMileageEndLocation());
			queueMileageAutoSave();
			close();
		} else {
			alert(r.data?.message || 'Error.');
		}
	});
}

// Admin: add a destination directly. Skips the approval queue — saved, geocoded, and
// distance-priced on the spot (no waiting for a driver to propose it).
function showAdminAddLocationModal(onSaved) {
	const existing = $('#sp-mileage-admin-addloc-modal');
	if (existing) existing.remove();

	const typeOptions = ['restaurant', 'vendor', 'other'].map(t => `<option value="${t}">${t.charAt(0).toUpperCase() + t.slice(1)}</option>`).join('');
	const catOptions = MILEAGE_CATEGORIES.map(c => `<option value="${c}">${c}</option>`).join('');

	const modal = document.createElement('div');
	modal.id = 'sp-mileage-admin-addloc-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal">
			<h3>Add Destination</h3>
			<p class="sp-text-secondary">Added straight to the database — geocoded and distance-priced right away. No approval needed.</p>
			<div class="sp-form-group"><label>Name</label><input type="text" id="sp-addloc-name" class="sp-input" placeholder="Big D Mechanical"></div>
			<div class="sp-form-group"><label>Address</label><input type="text" id="sp-addloc-address" class="sp-input" placeholder="123 Main St, City, TX 75001"></div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
				<div class="sp-form-group"><label>Type</label><select id="sp-addloc-type" class="sp-select">${typeOptions}</select></div>
				<div class="sp-form-group"><label>Category</label><select id="sp-addloc-category" class="sp-select">${catOptions}</select></div>
			</div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div class="sp-form-group"><label>Business or personal</label><select id="sp-addloc-business" class="sp-select"><option value="1">Business</option><option value="0">Personal</option></select></div><div class="sp-form-group"><label>Store # / code <span class="unique sp-text-secondary" style="font-weight:400;">(optional)</span></label><input type="text" id="sp-addloc-code" class="sp-input" placeholder="e.g. 1042"></div></div>
			<div class="sp-form-group"><label>Notes (optional)</label><textarea id="sp-addloc-notes" class="sp-textarea" rows="2"></textarea></div>
			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-addloc-submit">Add Destination</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-addloc-cancel">Cancel</button>
			</div>
		</div>
	`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);
	attachPlacesAutocomplete(modal.querySelector('#sp-addloc-name'), modal.querySelector('#sp-addloc-address'));

	const close = () => modal.remove();
	modal.querySelector('#sp-addloc-cancel').addEventListener('click', close);
	modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
	modal.querySelector('#sp-addloc-submit').addEventListener('click', async () => {
		const name = modal.querySelector('#sp-addloc-name').value.trim();
		const address = modal.querySelector('#sp-addloc-address').value.trim();
		if (!name || !address) { alert('Name and address required.'); return; }
		const btn = modal.querySelector('#sp-addloc-submit');
		btn.disabled = true; btn.textContent = 'Adding…';
		const r = await spAjax('site_pulse_admin_add_mileage_location', {
			name, address,
			location_type: modal.querySelector('#sp-addloc-type').value,
			category: modal.querySelector('#sp-addloc-category').value,
			code: modal.querySelector('#sp-addloc-code').value.trim(),
			is_business: modal.querySelector('#sp-addloc-business').value,
			notes: modal.querySelector('#sp-addloc-notes').value.trim(),
		});
		if (r.success) { close(); if (onSaved) onSaved(); }
		else { alert(r.data?.message || 'Error.'); btn.disabled = false; btn.textContent = 'Add Destination'; }
	});
}

// Admin: edit an approved location's metadata (category, business/personal, notes, and the
// pinned purposes that pre-fill a stop's purpose when this place is chosen), or delete it.
function showEditLocationModal(loc, onSaved) {
	const existing = $('#sp-mileage-editloc-modal');
	if (existing) existing.remove();

	const catOptions = MILEAGE_CATEGORIES.map(c => `<option value="${c}"${(loc.category === c) ? ' selected' : ''}>${c}</option>`).join('');
	const typeOptions = ['restaurant', 'vendor', 'other', 'home'].map(t => `<option value="${t}"${(loc.location_type === t) ? ' selected' : ''}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>`).join('');
	const pinned = locationPinnedPurposes(loc).join('\n');

	const modal = document.createElement('div');
	modal.id = 'sp-mileage-editloc-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal">
			<h3>Edit Location</h3>
			<div class="sp-form-group"><label>Name</label><input type="text" id="sp-editloc-name" class="sp-input" value="${esc(loc.name || '')}"></div>
			<div class="sp-form-group"><label>Address</label><input type="text" id="sp-editloc-address" class="sp-input" value="${esc(loc.address || '')}"></div>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
				<div class="sp-form-group"><label>Type</label><select id="sp-editloc-type" class="sp-select">${typeOptions}</select></div>
				<div class="sp-form-group"><label>Category</label><select id="sp-editloc-category" class="sp-select">${catOptions}</select></div>
				<div class="sp-form-group"><label>Business or personal</label><select id="sp-editloc-business" class="sp-select"><option value="1"${parseInt(loc.is_business) ? ' selected' : ''}>Business</option><option value="0"${!parseInt(loc.is_business) ? ' selected' : ''}>Personal</option></select></div>
			</div>
			<div class="sp-form-group"><label>Store # / accounting code <span class="unique sp-text-secondary" style="font-weight:400;">(optional)</span></label><input type="text" id="sp-editloc-code" class="sp-input" value="${esc(loc.code || '')}" placeholder="e.g. 1042"></div>
				<div class="sp-form-group"><label>Notes</label><textarea id="sp-editloc-notes" class="sp-textarea" rows="2">${esc(loc.notes || '')}</textarea></div>
			<div class="sp-form-group"><label>Pinned purposes <span class="unique sp-text-secondary" style="font-weight:400;">(one per line — first one pre-fills the purpose when this place is chosen)</span></label><textarea id="sp-editloc-pinned" class="sp-textarea" rows="3" placeholder="Store visit&#10;Manager meeting">${esc(pinned)}</textarea></div>
			<div class="sp-form-group"><label>Marker image URL <span class="unique sp-text-secondary" style="font-weight:400;">(optional — overrides this location type's default marker on the map; blank = use the default)</span></label><input type="text" id="sp-editloc-marker" class="sp-input" value="${esc(loc.marker_icon || '')}" placeholder="https://rovin.work/wp-content/uploads/brand-logo.png"></div>
			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-editloc-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-editloc-cancel">Cancel</button>
				<button type="button" class="unique sp-btn sp-icon-btn sp-icon-delete" id="sp-editloc-delete" title="Delete" aria-label="Delete" style="margin-left:auto;">${ICON_DELETE}</button>
			</div>
		</div>
	`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);

	const close = () => modal.remove();
	modal.querySelector('#sp-editloc-cancel').addEventListener('click', close);
	modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
	modal.querySelector('#sp-editloc-save').addEventListener('click', async () => {
		const pinnedArr = modal.querySelector('#sp-editloc-pinned').value.split('\n').map(s => s.trim()).filter(Boolean);
		const r = await spAjax('site_pulse_admin_update_mileage_location', {
			id: loc.id,
			name: modal.querySelector('#sp-editloc-name').value.trim(),
			address: modal.querySelector('#sp-editloc-address').value.trim(),
			location_type: modal.querySelector('#sp-editloc-type').value,
			category: modal.querySelector('#sp-editloc-category').value,
			code: modal.querySelector('#sp-editloc-code').value.trim(),
			is_business: modal.querySelector('#sp-editloc-business').value,
			notes: modal.querySelector('#sp-editloc-notes').value.trim(),
			pinned_purposes: pinnedArr,
			marker_icon: modal.querySelector('#sp-editloc-marker').value.trim(),
		});
		if (r.success) { close(); if (onSaved) onSaved(); }
		else alert(r.data?.message || 'Error saving.');
	});
	modal.querySelector('#sp-editloc-delete').addEventListener('click', async () => {
		if (!confirm(`Delete "${loc.name}"? This removes it from the database. Any trip legs that use it will be removed and those entries recalculated.`)) return;
		const r = await spAjax('site_pulse_admin_delete_mileage_location', { id: loc.id });
		if (r.success) { close(); if (onSaved) onSaved(); }
		else alert(r.data?.message || 'Error deleting.');
	});
}

function printMileageLog() {
	document.body.classList.add('sp-printing-mileage');
	window.print();
	setTimeout(() => document.body.classList.remove('sp-printing-mileage'), 500);
}

async function emailMileageLog() {
	const start = $('#sp-mileage-filter-start')?.value || '';
	const end = $('#sp-mileage-filter-end')?.value || '';
	const to = prompt('Email log to:', '');
	if (!to) return;
	const r = await spAjax('site_pulse_email_mileage_log', { to, start, end });
	if (r.success) alert('Sent to ' + r.data.to);
	else alert(r.data?.message || 'Error sending.');
}

// Pulls the report dataset for the current date filter (or all entries if unfiltered).
const MILEAGE_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Format a YYYY-MM-DD string (or Date) as "Jan 1, 2026" without timezone drift.
function fmtReportDate(val) {
	if (!val) return '';
	if (val instanceof Date) return `${MILEAGE_MONTHS[val.getMonth()]} ${val.getDate()}, ${val.getFullYear()}`;
	const m = String(val).match(/^(\d{4})-(\d{2})-(\d{2})/);
	if (!m) return String(val);
	return `${MILEAGE_MONTHS[parseInt(m[2], 10) - 1]} ${parseInt(m[3], 10)}, ${m[1]}`;
}

async function fetchMileageReport(start, end, userId) {
	// Default to the Mileage screen's date range; the Expense Report cover page passes its own.
	if (start === undefined) start = $('#sp-mileage-filter-start')?.value || '';
	if (end === undefined) end = $('#sp-mileage-filter-end')?.value || '';
	// An approver / archive viewer passes report_user to pull another employee's report (server-authorized).
	const payload = { start, end };
	if (userId) payload.report_user = userId;
	const res = await spAjax('site_pulse_get_mileage_report', payload);
	if (!res.success) { alert(res.data?.message || 'Could not build the report.'); return null; }
	const label = (start || end) ? `${fmtReportDate(start) || '…'} to ${fmtReportDate(end) || '…'}` : 'All entries';
	return { ...res.data, start, end, label };
}

function downloadBlob(filename, content, type) {
	const blob = content instanceof Blob ? content : new Blob([content], { type });
	const url = URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url; a.download = filename;
	document.body.appendChild(a); a.click();
	document.body.removeChild(a);
	setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/*--------------------------------------------------------------
# Toll Reconciliation — upload CSV, AI-match per day, review & apply
--------------------------------------------------------------*/

// Wire the upload controls once per page load (activatePanel calls this each open).
function initTollReconcile() {
	const panel = $('#sp-panel-mileage-tolls');
	if (!panel || panel.dataset.wired === '1') return;
	panel.dataset.wired = '1';

	const file = $('#sp-toll-file');
	const btn = $('#sp-toll-upload-btn');
	if (file && btn) {
		file.addEventListener('change', () => { btn.disabled = !file.files.length; });
		btn.addEventListener('click', tollUpload);
	}
}

async function tollUpload() {
	const file = $('#sp-toll-file');
	const btn = $('#sp-toll-upload-btn');
	const status = $('#sp-toll-status');
	if (!file || !file.files.length) return;

	const f = file.files[0];

	btn.disabled = true; btn.textContent = 'Uploading…';
	if (status) { status.hidden = false; status.className = 'sp-toll-status'; status.textContent = 'Reading your toll statement…'; }
	$('#sp-toll-days').innerHTML = '';

	try {
		// Detect the format by CONTENT, not the file name (NTTA files sometimes lack a visible
		// extension). An .xlsx is a ZIP → first bytes are "PK". Anything else is treated as CSV text;
		// the server validates it's really a plain CSV (rejecting .xls / PDF / etc.).
		const buf = await f.arrayBuffer();
		const bytes = new Uint8Array(buf);
		const isZip = bytes.length > 3 && bytes[0] === 0x50 && bytes[1] === 0x4B; // "PK" → xlsx
		const payload = isZip
			? { xlsx_base64: spArrayBufferToBase64(buf), filename: f.name }
			: { csv: new TextDecoder('utf-8').decode(buf), filename: f.name };
		const r = await spAjax('site_pulse_upload_toll_csv', payload);
		if (!r.success) {
			if (status) { status.className = 'sp-toll-status sp-toll-status-error'; status.textContent = r.data?.message || 'Upload failed.'; }
		} else {
			renderTollDays(r.data);
		}
	} catch (e) {
		if (status) { status.className = 'sp-toll-status sp-toll-status-error'; status.textContent = 'Upload failed. Please try again.'; }
	} finally {
		btn.disabled = false; btn.textContent = 'Upload';
	}
}

// Base64-encode an ArrayBuffer in chunks (avoids the call-stack limit of String.fromCharCode(...bytes)).
function spArrayBufferToBase64(buf) {
	const bytes = new Uint8Array(buf);
	let bin = '';
	const CH = 0x8000;
	for (let i = 0; i < bytes.length; i += CH) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + CH));
	return btoa(bin);
}

// The "applied" done-state body for a day, with Review (show stored result, no AI) and
// Re-analyze (send back to Claude) buttons. Shared by upload render + just-applied.
function tollDoneBodyHtml(total) {
	return `<div class="sp-toll-status sp-toll-status-ok">✓ $${Number(total || 0).toFixed(2)} in tolls applied to this day. `
		+ `<button type="button" class="unique sp-btn sp-btn-ghost sp-btn-tiny sp-toll-review-btn">Review</button> `
		+ `<button type="button" class="unique sp-btn sp-btn-ghost sp-btn-tiny sp-toll-reanalyze-btn">Re-analyze</button></div>`;
}

function renderTollDays(data) {
	const status = $('#sp-toll-status');
	const wrap = $('#sp-toll-days');
	const days = data.days || [];
	const pending = days.filter(d => !d.applied);

	if (status) {
		status.hidden = false;
		status.className = 'sp-toll-status';
		if (!days.length) {
			status.textContent = `Found ${data.total_txns} charge${data.total_txns !== 1 ? 's' : ''}, but none fall on a day you logged a trip — nothing to analyze.`;
			wrap.innerHTML = '';
			return;
		}
		const ign = data.ignored_count ? ` ${data.ignored_count} charge${data.ignored_count !== 1 ? 's' : ''} on non-trip days ${data.ignored_count !== 1 ? 'were' : 'was'} ignored.` : '';
		const cleaned = (data.skipped_invalid || 0) + (data.skipped_dupes || 0);
		const cln = cleaned ? ` ${cleaned} invalid or duplicate row${cleaned !== 1 ? 's' : ''} auto-removed.` : '';
		const allBtnHtml = pending.length ? ` <button type="button" class="unique sp-btn sp-btn-secondary sp-btn-tiny" id="sp-toll-analyze-all">Analyze all</button>` : '';
		status.innerHTML = `Found <strong>${days.length}</strong> logged day${days.length !== 1 ? 's' : ''} with toll charges.${ign}${cln}${allBtnHtml}`;
		markUniqueSpans(status);
	}

	let h = '';
	days.forEach(d => {
		h += `<div class="sp-toll-day sp-card sp-table-card${d.applied ? ' sp-toll-day-done' : ''}" data-entry="${d.entry_id}" data-total="${d.total_tolls || 0}">`;
		h += `<div class="sp-toll-day-head"><span class="unique sp-toll-day-date">${tollFmtDate(d.date)}</span>`;
		h += `<span class="unique sp-toll-day-sep" aria-hidden="true">·</span>`;
		h += `<span class="unique sp-toll-day-meta">${d.applied ? 'applied' : d.txn_count + ' charge' + (d.txn_count !== 1 ? 's' : '')}</span>`;
		if (!d.applied) h += `<button type="button" class="unique sp-btn sp-btn-secondary sp-btn-tiny sp-toll-analyze-btn">Analyze</button>`;
		h += `</div>`;
		h += `<div class="sp-toll-day-body">${d.applied ? tollDoneBodyHtml(d.total_tolls) : ''}</div>`;
		h += `</div>`;
	});
	wrap.innerHTML = h;
	markUniqueSpans(wrap);

	$$('.sp-toll-analyze-btn', wrap).forEach(b => b.addEventListener('click', () => reconcileTollDay(b.closest('.sp-toll-day'))));
	$$('.sp-toll-review-btn', wrap).forEach(b => b.addEventListener('click', () => reviewTollDay(b.closest('.sp-toll-day'))));
	$$('.sp-toll-reanalyze-btn', wrap).forEach(b => b.addEventListener('click', () => reconcileTollDay(b.closest('.sp-toll-day'))));

	const allBtn = $('#sp-toll-analyze-all');
	if (allBtn) allBtn.addEventListener('click', async () => {
		allBtn.disabled = true;
		for (const card of $$('.sp-toll-day:not(.sp-toll-day-done)', wrap)) { await reconcileTollDay(card); }
		allBtn.disabled = false;
	});
}

// Send the day's charges to Claude for fresh AI matching.
async function reconcileTollDay(card) {
	if (!card) return;
	const entryId = parseInt(card.dataset.entry);
	const body = card.querySelector('.sp-toll-day-body');
	card.querySelector('.sp-toll-day-head .sp-btn')?.remove(); // the review renders in the body
	body.innerHTML = '<div class="sp-toll-loading">AI is analyzing your tolls against this day\'s route…</div>';
	try {
		const r = await spAjax('site_pulse_reconcile_toll_day', { entry_id: entryId });
		if (!r.success) {
			body.innerHTML = `<div class="sp-toll-status sp-toll-status-error">${esc(r.data?.message || 'Could not analyze this day.')}</div>`;
		} else {
			renderTollReview(r.data, card, { mode: 'analyze' });
		}
	} catch (e) {
		body.innerHTML = '<div class="sp-toll-status sp-toll-status-error">Something went wrong. Please try again.</div>';
	}
}

// Show what was already found/applied for this day — reads the stored result, no AI call.
async function reviewTollDay(card) {
	if (!card) return;
	const entryId = parseInt(card.dataset.entry);
	const body = card.querySelector('.sp-toll-day-body');
	card.querySelector('.sp-toll-day-head .sp-btn')?.remove();
	body.innerHTML = '<div class="sp-toll-loading">Loading…</div>';
	try {
		const r = await spAjax('site_pulse_get_toll_day', { entry_id: entryId });
		if (!r.success) {
			body.innerHTML = `<div class="sp-toll-status sp-toll-status-error">${esc(r.data?.message || 'Could not load this day.')}</div>`;
		} else {
			renderTollReview(r.data, card, { mode: 'review' });
		}
	} catch (e) {
		body.innerHTML = '<div class="sp-toll-status sp-toll-status-error">Something went wrong. Please try again.</div>';
	}
}

// Flatten the proposal into one editable list of charges, each with its current leg
// assignment (null = not on this trip) and the AI's state for highlighting.
function tollFlattenCharges(p) {
	const out = [];
	(p.legs || []).forEach(leg => (leg.transactions || []).forEach(t => out.push(Object.assign({}, t, { legId: leg.leg_id, state: 'matched' }))));
	(p.ambiguous || []).forEach(t => out.push(Object.assign({}, t, { legId: null, state: 'ambiguous' })));
	(p.excluded || []).forEach(t => out.push(Object.assign({}, t, { legId: null, state: 'excluded' })));
	out.sort((a, b) => String(a.datetime || '').localeCompare(String(b.datetime || '')));
	return out;
}

function renderTollReview(p, card, opts) {
	const mode = (opts && opts.mode) || 'analyze'; // 'analyze' (fresh/re-analyze) | 'review' (stored)
	const body = card.querySelector('.sp-toll-day-body');
	const charges = tollFlattenCharges(p);
	const estMap = {};
	(p.legs || []).forEach(l => { if (l.estimate != null) estMap[l.leg_id] = l.estimate; });

	if (!charges.length) {
		body.innerHTML = '<div class="sp-toll-status">No toll charges to review for this day.</div>';
		return;
	}

	const legOpts = (selId) => {
		let o = `<option value="">✕ Not this trip</option>`;
		(p.all_legs || []).forEach(l => {
			o += `<option value="${l.leg_id}"${String(selId) === String(l.leg_id) ? ' selected' : ''}>Leg ${l.leg_order + 1}: ${esc(l.label)}</option>`;
		});
		return o;
	};

	let h = '<table class="sp-table sp-toll-table"><thead class="sp-thead"><tr><th>When</th><th>Road / Gantry</th><th class="sp-m-num">Charge</th><th>Assign to leg</th></tr></thead><tbody>';
	charges.forEach(c => {
		const cls = c.state === 'ambiguous' ? ' sp-toll-row-amb' : (c.state === 'excluded' ? ' sp-toll-row-exc' : '');
		const reason = c.reason ? ` title="${esc(c.reason)}"` : '';
		const flag = c.state === 'ambiguous' ? ' <span class="unique sp-toll-flag" title="Flagged for your review">⚠</span>' : '';
		const loc = tollSplitLocation(c.road, c.gantry);
		h += `<tr class="sp-toll-row${cls}"${reason}>`;
		h += `<td data-label="When">${tollFmtTime(c.datetime)}${flag}</td>`;
		h += `<td data-label="Road / Gantry"><div class="sp-toll-road">${esc(loc.road || '—')}</div>${loc.gantry ? `<div class="sp-toll-gantry">${esc(loc.gantry)}</div>` : ''}${c.reason ? `<div class="sp-toll-reason">${esc(c.reason)}</div>` : ''}</td>`;
		h += `<td class="sp-m-num" data-label="Charge">$${Number(c.amount).toFixed(2)}</td>`;
		h += `<td data-label="Assign to leg"><select class="sp-select sp-toll-assign" data-id="${c.id}" data-amount="${Number(c.amount)}">${legOpts(c.legId)}</select></td>`;
		h += '</tr>';
	});
	h += '</tbody></table>';

	// Applied total (from the CSV), right-aligned just above the apply buttons.
	h += '<div class="sp-toll-applied"><span class="sp-toll-applied-label">Tolls from CSV (applied)</span><span class="sp-card-value" data-toll-actual>$0.00</span></div>';
	// Buttons: Cancel always; Apply shows immediately for a fresh/re-analyze, but in Review
	// mode it only appears once the driver actually changes a leg assignment (then "Apply Changes").
	const applyLabel  = mode === 'review' ? 'Apply Changes' : 'Apply to this day';
	const applyHidden = mode === 'review' ? ' hidden' : '';
	h += '<div class="sp-toll-apply-row">';
	h += '<button type="button" class="unique sp-btn sp-btn-secondary sp-toll-cancel-btn">Cancel</button>';
	h += `<button type="button" class="unique sp-btn sp-btn-primary sp-toll-apply-btn"${applyHidden}>${applyLabel}</button>`;
	h += '</div>';

	body.innerHTML = h;
	markUniqueSpans(body);

	// Snapshot the initial assignments so we can tell when one actually changes.
	const initial = {};
	$$('.sp-toll-assign', body).forEach(s => { initial[s.dataset.id] = s.value; });
	const applyBtn = body.querySelector('.sp-toll-apply-btn');
	const isDirty = () => $$('.sp-toll-assign', body).some(s => s.value !== initial[s.dataset.id]);

	const recalc = () => tollRecalc(card, estMap);
	$$('.sp-toll-assign', body).forEach(sel => sel.addEventListener('change', () => {
		const row = sel.closest('.sp-toll-row');
		if (row) { row.classList.remove('sp-toll-row-exc'); if (!sel.value) row.classList.add('sp-toll-row-exc'); }
		recalc();
		if (mode === 'review' && applyBtn) applyBtn.hidden = !isDirty(); // reveal "Apply Changes" only on edit
	}));
	applyBtn.addEventListener('click', () => applyTollDay(card));
	body.querySelector('.sp-toll-cancel-btn').addEventListener('click', () => tollCollapseDay(card));
	recalc();
}

// Collapse an open review back to the card's resting state without applying anything.
// An already-applied day returns to its "✓ applied" summary; a never-applied day returns
// to the pending "Analyze" button.
function tollCollapseDay(card) {
	const body = card.querySelector('.sp-toll-day-body');
	card.querySelector('.sp-toll-day-head .sp-btn')?.remove();
	if (card.classList.contains('sp-toll-day-done')) {
		body.innerHTML = tollDoneBodyHtml(card.dataset.total || 0);
		markUniqueSpans(body);
		body.querySelector('.sp-toll-review-btn')?.addEventListener('click', () => reviewTollDay(card));
		body.querySelector('.sp-toll-reanalyze-btn')?.addEventListener('click', () => reconcileTollDay(card));
	} else {
		body.innerHTML = '';
		const head = card.querySelector('.sp-toll-day-head');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'unique sp-btn sp-btn-secondary sp-btn-tiny sp-toll-analyze-btn';
		btn.textContent = 'Analyze';
		btn.addEventListener('click', () => reconcileTollDay(card));
		head.appendChild(btn);
		markUniqueSpans(head);
	}
}

function tollRecalc(card) {
	const sels = $$('.sp-toll-assign', card);
	let actual = 0;
	sels.forEach(s => { if (s.value) actual += parseFloat(s.dataset.amount) || 0; });
	const el = card.querySelector('[data-toll-actual]');
	if (el) el.textContent = '$' + actual.toFixed(2);
}

async function applyTollDay(card) {
	const entryId = parseInt(card.dataset.entry);
	const btn = card.querySelector('.sp-toll-apply-btn');
	const decisions = $$('.sp-toll-assign', card).map(s => ({
		id: parseInt(s.dataset.id),
		status: s.value ? 'matched' : 'excluded',
		leg_id: s.value ? parseInt(s.value) : 0,
	}));
	if (btn) { btn.disabled = true; btn.textContent = 'Applying…'; }
	try {
		const r = await spAjax('site_pulse_apply_toll_day', { entry_id: entryId, decisions: JSON.stringify(decisions) });
		if (!r.success) {
			if (btn) { btn.disabled = false; btn.textContent = 'Apply to this day'; }
			alert(r.data?.message || 'Could not apply.');
			return;
		}
		const body = card.querySelector('.sp-toll-day-body');
		card.dataset.total = r.data.total_tolls; // so Cancel can restore the summary
		body.innerHTML = tollDoneBodyHtml(r.data.total_tolls);
		markUniqueSpans(body);
		card.classList.add('sp-toll-day-done');
		body.querySelector('.sp-toll-review-btn')?.addEventListener('click', () => reviewTollDay(card));
		body.querySelector('.sp-toll-reanalyze-btn')?.addEventListener('click', () => reconcileTollDay(card));
		// Refresh the My Mileage list so the newly-applied tolls show there too (it's a
		// separate panel that doesn't otherwise reload after a toll apply).
		if (typeof loadMileageEntries === 'function') loadMileageEntries();
	} catch (e) {
		if (btn) { btn.disabled = false; btn.textContent = 'Apply to this day'; }
		alert('Something went wrong applying these tolls.');
	}
}

// "2026-01-05" → "Jan 5, 2026" (TZ-safe; no Date parsing).
function tollFmtDate(s) {
	const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
	if (!m) return esc(s);
	const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	return `${months[+m[2] - 1]} ${+m[3]}, ${m[1]}`;
}

// "2026-01-05 08:15:00" → "Jan 5, 8:15 AM".
function tollFmtDT(s) {
	const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
	if (!m) return esc(s);
	const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	let h = +m[4]; const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
	return `${months[+m[2] - 1]} ${+m[3]}, ${h}:${m[5]} ${ap}`;
}

// Time-only "8:15 AM" — the date already shows in the day header above the table.
function tollFmtTime(s) {
	const m = String(s || '').match(/[ T](\d{2}):(\d{2})/);
	if (!m) return '';
	let h = +m[1]; const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
	return `${h}:${m[2]} ${ap}`;
}

// Split an NTTA Location ("Road - Gantry Name - CODE") into road (line 1) + the gantry
// name & code (line 2). If a separate road field exists, use it as-is.
function tollSplitLocation(road, gantry) {
	if (road && gantry) return { road, gantry };
	const g = gantry || '';
	const i = g.indexOf(' - ');
	if (i === -1) return { road: road || g, gantry: '' };
	return { road: g.slice(0, i).trim(), gantry: g.slice(i + 3).trim() };
}


// Polished PDF report — adopts the layout from the original mileage prototype
// (header, stat boxes, IRS-rate note, trip table with footer totals), driven by
// server data and the Site Pulse navy palette.
/* ---- Reusable receipt scanner (shared by every expense module) ---- */

// Downscale an image File to a JPEG data URL so camera photos stay small over the wire.
function spImageToDataURL(file, maxDim = 1600, quality = 0.82) {
	return new Promise((resolve, reject) => {
		const img = new Image();
		const url = URL.createObjectURL(file);
		img.onload = () => {
			URL.revokeObjectURL(url);
			let w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
			if (Math.max(w, h) > maxDim) { const s = maxDim / Math.max(w, h); w = Math.round(w * s); h = Math.round(h * s); }
			const canvas = document.createElement('canvas');
			canvas.width = w; canvas.height = h;
			canvas.getContext('2d').drawImage(img, 0, 0, w, h);
			try { resolve(canvas.toDataURL('image/jpeg', quality)); }
			catch (e) { reject(e); }
		};
		img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Could not read that image.')); };
		img.src = url;
	});
}

// Heckbert square→quad projective map: returns [a,b,c,d,e,f,g,h] mapping unit-square corners
// (0,0)(1,0)(1,1)(0,1) → the four points; sample with sx=(a·u+b·v+c)/(g·u+h·v+1), sy likewise.
function spSquareToQuad(x0, y0, x1, y1, x2, y2, x3, y3) {
	const dx1 = x1 - x2, dx2 = x3 - x2, dx3 = x0 - x1 + x2 - x3;
	const dy1 = y1 - y2, dy2 = y3 - y2, dy3 = y0 - y1 + y2 - y3;
	let a, b, c, d, e, f, g, h;
	if (Math.abs(dx3) < 1e-9 && Math.abs(dy3) < 1e-9) {
		a = x1 - x0; b = x2 - x1; c = x0; d = y1 - y0; e = y2 - y1; f = y0; g = 0; h = 0;
	} else {
		const den = dx1 * dy2 - dx2 * dy1;
		if (Math.abs(den) < 1e-12) return null;
		g = (dx3 * dy2 - dx2 * dy3) / den;
		h = (dx1 * dy3 - dx3 * dy1) / den;
		a = x1 - x0 + g * x1; b = x3 - x0 + h * x3; c = x0;
		d = y1 - y0 + g * y1; e = y3 - y0 + h * y3; f = y0;
	}
	return [a, b, c, d, e, f, g, h];
}

// Order 4 points as [TL, TR, BR, BL] (by x+y for the diagonals, then x for the other two).
function spOrderQuad(pts) {
	const byS = pts.slice().sort((a, b) => (a[0] + a[1]) - (b[0] + b[1]));
	const tl = byS[0], br = byS[3];
	const rem = pts.filter(p => p !== tl && p !== br);
	if (rem.length !== 2) return null;
	const [tr, bl] = rem[0][0] >= rem[1][0] ? [rem[0], rem[1]] : [rem[1], rem[0]];
	return [tl, tr, br, bl];
}
function spQuadArea(q) {
	let a = 0;
	for (let i = 0; i < 4; i++) { const p = q[i], n = q[(i + 1) % 4]; a += p[0] * n[1] - n[0] * p[1]; }
	return Math.abs(a) / 2;
}

// Perspective de-skew + crop: warp the receipt quad (AI corners, 0–1 fractions) onto a straight
// rectangle. Returns a JPEG data URL, or null if the corners are missing/degenerate (→ caller
// falls back to the brightness crop). An angled photo comes out flat AND cropped in one pass.
function spWarpReceipt(img, W, H, corners, maxWidth, quality) {
	if (!Array.isArray(corners) || corners.length !== 4) return null;
	const pts = corners.map(c => [(+c[0]) * W, (+c[1]) * H]);
	if (pts.some(p => !isFinite(p[0]) || !isFinite(p[1]))) return null;
	const ord = spOrderQuad(pts);
	if (!ord) return null;
	if (spQuadArea(ord) < 0.06 * W * H) return null;        // too small → probably wrong
	const [tl, tr, br, bl] = ord;
	let outW = Math.round((Math.hypot(tr[0] - tl[0], tr[1] - tl[1]) + Math.hypot(br[0] - bl[0], br[1] - bl[1])) / 2);
	let outH = Math.round((Math.hypot(bl[0] - tl[0], bl[1] - tl[1]) + Math.hypot(br[0] - tr[0], br[1] - tr[1])) / 2);
	if (outW < 8 || outH < 8) return null;
	if (outW > maxWidth) { const s = maxWidth / outW; outW = Math.round(outW * s); outH = Math.round(outH * s); }
	outH = Math.max(1, Math.min(outH, maxWidth * 3));
	const M = spSquareToQuad(tl[0], tl[1], tr[0], tr[1], br[0], br[1], bl[0], bl[1]);
	if (!M) return null;
	let src;
	try {
		const sc = document.createElement('canvas'); sc.width = W; sc.height = H;
		sc.getContext('2d').drawImage(img, 0, 0);
		src = sc.getContext('2d').getImageData(0, 0, W, H).data;
	} catch (e) { return null; }
	const out = document.createElement('canvas'); out.width = outW; out.height = outH;
	const octx = out.getContext('2d'), odata = octx.createImageData(outW, outH), od = odata.data;
	const a = M[0], b = M[1], c = M[2], d = M[3], e = M[4], f = M[5], g = M[6], h = M[7];
	for (let y = 0; y < outH; y++) {
		const v = (y + 0.5) / outH;
		for (let x = 0; x < outW; x++) {
			const u = (x + 0.5) / outW;
			const wp = g * u + h * v + 1;
			let sxp = (a * u + b * v + c) / wp, syp = (d * u + e * v + f) / wp;
			if (sxp < 0) sxp = 0; else if (sxp > W - 1) sxp = W - 1;
			if (syp < 0) syp = 0; else if (syp > H - 1) syp = H - 1;
			const x0 = sxp | 0, y0 = syp | 0, x1 = Math.min(x0 + 1, W - 1), y1 = Math.min(y0 + 1, H - 1);
			const fx = sxp - x0, fy = syp - y0;
			const i00 = (y0 * W + x0) * 4, i10 = (y0 * W + x1) * 4, i01 = (y1 * W + x0) * 4, i11 = (y1 * W + x1) * 4;
			const o = (y * outW + x) * 4;
			for (let ch = 0; ch < 3; ch++) {
				const top = src[i00 + ch] * (1 - fx) + src[i10 + ch] * fx;
				const bot = src[i01 + ch] * (1 - fx) + src[i11 + ch] * fx;
				od[o + ch] = (top * (1 - fy) + bot * fy) | 0;
			}
			od[o + 3] = 255;
		}
	}
	octx.putImageData(odata, 0, 0);
	try { return out.toDataURL('image/jpeg', quality); } catch (e) { return null; }
}

// Otsu threshold (0–255) that best separates a luminance array into dark/bright classes.
function spOtsu(lum, N) {
	const hist = new Int32Array(256);
	for (let i = 0; i < N; i++) { let v = lum[i] | 0; if (v < 0) v = 0; else if (v > 255) v = 255; hist[v]++; }
	let sum = 0; for (let t = 0; t < 256; t++) sum += t * hist[t];
	let sumB = 0, wB = 0, max = -1, thr = 127;
	for (let t = 0; t < 256; t++) {
		wB += hist[t]; if (!wB) continue; const wF = N - wB; if (!wF) break;
		sumB += t * hist[t];
		const mB = sumB / wB, mF = (sum - sumB) / wF, between = wB * wF * (mB - mF) * (mB - mF);
		if (between > max) { max = between; thr = t; }
	}
	return thr;
}

// Geometrically find the receipt's four corners: the receipt is a bright quad on a darker
// background, so threshold (Otsu), take the largest bright connected blob, and use its four
// diagonal-extreme points (min/max of x+y and x−y) — these ARE the corners of a rotated
// rectangle, which the warp can straighten. Returns [[x,y]×4] as 0–1 fractions or null.
function spDetectReceiptCorners(img, W, H) {
	try {
		const aw = Math.min(360, W), ah = Math.max(1, Math.round(H * (aw / W)));
		if (aw < 16 || ah < 16) return null;
		const cv = document.createElement('canvas'); cv.width = aw; cv.height = ah;
		cv.getContext('2d').drawImage(img, 0, 0, aw, ah);
		const data = cv.getContext('2d').getImageData(0, 0, aw, ah).data, N = aw * ah;
		const lum = new Float32Array(N);
		for (let i = 0, p = 0; p < N; i += 4, p++) lum[p] = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
		const thr = spOtsu(lum, N);
		const mask = new Uint8Array(N); let maskCount = 0;
		for (let p = 0; p < N; p++) if (lum[p] >= thr) { mask[p] = 1; maskCount++; }
		if (maskCount < N * 0.05 || maskCount > N * 0.97) return null;   // no separation
		const visited = new Uint8Array(N), stack = new Int32Array(N);
		let best = null, bestSize = 0;
		for (let s = 0; s < N; s++) {
			if (!mask[s] || visited[s]) continue;
			let sp = 0; stack[sp++] = s; visited[s] = 1; let size = 0;
			let tl, tr, br, bl, minSum = Infinity, maxSum = -Infinity, minDiff = Infinity, maxDiff = -Infinity;
			while (sp) {
				const idx = stack[--sp]; size++;
				const x = idx % aw, y = (idx / aw) | 0, su = x + y, df = x - y;
				if (su < minSum) { minSum = su; tl = [x, y]; }
				if (su > maxSum) { maxSum = su; br = [x, y]; }
				if (df > maxDiff) { maxDiff = df; tr = [x, y]; }
				if (df < minDiff) { minDiff = df; bl = [x, y]; }
				if (x > 0) { const n = idx - 1; if (mask[n] && !visited[n]) { visited[n] = 1; stack[sp++] = n; } }
				if (x < aw - 1) { const n = idx + 1; if (mask[n] && !visited[n]) { visited[n] = 1; stack[sp++] = n; } }
				if (y > 0) { const n = idx - aw; if (mask[n] && !visited[n]) { visited[n] = 1; stack[sp++] = n; } }
				if (y < ah - 1) { const n = idx + aw; if (mask[n] && !visited[n]) { visited[n] = 1; stack[sp++] = n; } }
			}
			if (size > bestSize) { bestSize = size; best = [tl, tr, br, bl]; }
		}
		if (!best || bestSize < N * 0.08) return null;
		return best.map(c => [c[0] / aw, c[1] / ah]);
	} catch (e) { return null; }
}

// Turn a receipt photo into a small, straight, cropped image. It first detects the receipt's
// corners geometrically and perspective-de-skews + crops (straightens an angled photo); if that
// fails it tries the AI `corners`; if both fail it falls back to a conservative brightness crop +
// downscale. Returns a JPEG data URL.
function spProcessReceipt(dataUrl, corners, maxWidth = 1000, quality = 0.82) {
	return new Promise((resolve) => {
		const img = new Image();
		img.onload = () => {
			const W = img.naturalWidth || img.width, H = img.naturalHeight || img.height;
			if (!W || !H) { resolve(dataUrl); return; }
			const detected = spDetectReceiptCorners(img, W, H);
			let warped = detected ? spWarpReceipt(img, W, H, detected, maxWidth, quality) : null;
			if (!warped) warped = spWarpReceipt(img, W, H, corners, maxWidth, quality);
			if (warped) { resolve(warped); return; }
			let sx = 0, sy = 0, sw = W, sh = H;
			try {
				const aw = Math.min(240, W), ah = Math.max(1, Math.round(H * (aw / W)));
				const ac = document.createElement('canvas'); ac.width = aw; ac.height = ah;
				const actx = ac.getContext('2d'); actx.drawImage(img, 0, 0, aw, ah);
				const d = actx.getImageData(0, 0, aw, ah).data;
				let sum = 0; const lum = new Float32Array(aw * ah);
				for (let i = 0, p = 0; i < d.length; i += 4, p++) { const l = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2]; lum[p] = l; sum += l; }
				const mean = sum / (aw * ah), thr = Math.max(140, mean + 18);
				let minX = aw, minY = ah, maxX = -1, maxY = -1, bright = 0;
				for (let y = 0; y < ah; y++) for (let x = 0; x < aw; x++) {
					if (lum[y * aw + x] >= thr) { bright++; if (x < minX) minX = x; if (x > maxX) maxX = x; if (y < minY) minY = y; if (y > maxY) maxY = y; }
				}
				const frac = bright / (aw * ah);
				if (maxX > minX && maxY > minY && frac > 0.12 && frac < 0.86) {
					const bw = maxX - minX + 1, bh = maxY - minY + 1;
					if (bw < aw * 0.93 || bh < ah * 0.93) {            // noticeably smaller than the frame
						const mx = bw * 0.04, my = bh * 0.04;            // small margin so we don't shave the edge
						const x0 = Math.max(0, minX - mx), y0 = Math.max(0, minY - my);
						const x1 = Math.min(aw, maxX + 1 + mx), y1 = Math.min(ah, maxY + 1 + my);
						sx = x0 / aw * W; sy = y0 / ah * H; sw = (x1 - x0) / aw * W; sh = (y1 - y0) / ah * H;
					}
				}
			} catch (e) { sx = 0; sy = 0; sw = W; sh = H; }
			let dw = sw, dh = sh;
			if (dw > maxWidth) { const s = maxWidth / dw; dw = Math.round(dw * s); dh = Math.round(dh * s); }
			const oc = document.createElement('canvas');
			oc.width = Math.max(1, Math.round(dw)); oc.height = Math.max(1, Math.round(dh));
			oc.getContext('2d').drawImage(img, sx, sy, sw, sh, 0, 0, oc.width, oc.height);
			try { resolve(oc.toDataURL('image/jpeg', quality)); } catch (e) { resolve(dataUrl); }
		};
		img.onerror = () => resolve(dataUrl);
		img.src = dataUrl;
	});
}

// Send a receipt data URL to AI for `section` → {category, amount, description, date, place}.
async function spScanReceiptFromDataURL(dataUrl, section) {
	const res = await spAjax('site_pulse_scan_receipt', { section, image: dataUrl });
	if (!res.success) throw new Error(res.data?.message || 'Could not read the receipt.');
	return res.data;
}

// Show the attached receipt thumbnail (new photo data URL or an existing receipt URL) inside the
// form, with a Remove control. Clicking the thumb opens the full receipt popup.
function spShowReceiptThumb(wrap, src) {
	if (!wrap || !src) return;
	let prev = $('.sp-receipt-preview', wrap);
	if (!prev) {
		prev = document.createElement('div');
		prev.className = 'sp-receipt-preview';
		const row = $('.sp-receipt-row', wrap);
		if (row) row.insertAdjacentElement('afterend', prev); else wrap.appendChild(prev);
	}
	prev.innerHTML = `<img class="sp-receipt-thumb" src="${src}" alt="Receipt"><button type="button" class="unique sp-btn sp-btn-ghost sp-btn-tiny sp-receipt-remove">Remove</button>`;
	prev.hidden = false;
	markUniqueSpans(prev);
	$('.sp-receipt-thumb', prev)?.addEventListener('click', () => spShowReceiptModal(src));
	$('.sp-receipt-remove', prev)?.addEventListener('click', () => {
		wrap._receiptData = null;
		wrap._receiptRemove = true;   // also drops an existing receipt on save
		prev.hidden = true; prev.innerHTML = '';
	});
}

// Fields derived from the form wrap to fold into an expense save payload: the receipt (a fresh
// photo, an explicit removal, or nothing). Called by every expense save*().
function spReceiptPayload(wrap) {
	const out = {};
	if (wrap && wrap._receiptData) out.receipt = wrap._receiptData;
	else if (wrap && wrap._receiptRemove) out.receipt_remove = 1;
	return out;
}

// Does this expense form currently have a receipt attached? True for a freshly shot/uploaded photo,
// or an existing receipt (shown as the thumb preview) that hasn't been removed. Receipts are REQUIRED
// on every expense, so each save*() calls this before submitting (the server enforces it too).
function spHasReceipt(wrap) {
	if (!wrap) return false;
	if (wrap._receiptData) return true;          // new photo attached this session
	const prev = $('.sp-receipt-preview', wrap); // existing receipt shows its thumb until removed
	return !!(prev && !prev.hidden);
}

// Full-size receipt popup (clicked from a form thumb or a list row's receipt icon).
function spShowReceiptModal(src) {
	if (!src) return;
	const back = document.createElement('div');
	back.className = 'sp-modal-backdrop sp-receipt-modal';
	back.innerHTML = `<div class="sp-receipt-modal-box"><button type="button" class="unique sp-receipt-modal-close" aria-label="Close">×</button><img src="${esc(src)}" alt="Receipt"></div>`;
	document.body.appendChild(back);
	markUniqueSpans(back);
	const close = () => back.remove();
	back.addEventListener('click', (e) => { if (e.target === back) close(); });
	$('.sp-receipt-modal-close', back)?.addEventListener('click', close);
	document.addEventListener('keydown', function esc2(e) { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc2); } });
}

/* ===========================================================================
   Forms library — Training / Kitchen / FOH / Misc repositories. The list is loaded
   per repository, sorted + filtered client-side; upload/replace/delete hit AJAX.
   =========================================================================== */
const _spForms = {};   // category -> { items, sort:{col,dir}, filter, canUpload }

function initFormsPanel(panelId) {
	const panel = document.getElementById('sp-panel-' + panelId);
	if (!panel) return;
	const cat = panel.getAttribute('data-forms-cat');
	if (!cat) return;
	if (!_spForms[cat]) _spForms[cat] = { items: [], sort: { col: 'date', dir: 'desc' }, search: '', filter: '', canUpload: false, selected: new Set() };
	if (!panel._formsWired) { spWireFormsPanel(panel, cat); panel._formsWired = true; }
	loadForms(cat);
}

function spWireFormsPanel(panel, cat) {
	const st = _spForms[cat];
	const search = panel.querySelector('.sp-forms-search');
	if (search) search.addEventListener('input', () => { st.search = search.value.trim().toLowerCase(); renderForms(cat); });

	// Sub-folder chips (present only when the repository has sub-folders).
	panel.querySelectorAll('.sp-forms-subchip').forEach(chip => {
		chip.addEventListener('click', () => {
			st.filter = chip.dataset.sub || '';
			panel.querySelectorAll('.sp-forms-subchip').forEach(c => c.classList.toggle('active', c === chip));
			renderForms(cat);
		});
	});

	panel.querySelectorAll('.sp-forms-sort').forEach(th => {
		th.addEventListener('click', () => {
			const col = th.getAttribute('data-sort');
			if (st.sort.col === col) st.sort.dir = st.sort.dir === 'asc' ? 'desc' : 'asc';
			else { st.sort.col = col; st.sort.dir = col === 'date' ? 'desc' : 'asc'; }
			renderForms(cat);
		});
	});

	const checkAll = panel.querySelector('.sp-forms-check-all');
	if (checkAll) checkAll.addEventListener('change', () => {
		const on = checkAll.checked;
		panel.querySelectorAll('.sp-forms-row-check').forEach(cb => {
			cb.checked = on;
			const id = parseInt(cb.getAttribute('data-id'), 10);
			if (on) st.selected.add(id); else st.selected.delete(id);
		});
		spFormsUpdateBulk(cat);
	});
	const bulkBtn = panel.querySelector('.sp-forms-download-selected');
	if (bulkBtn) bulkBtn.addEventListener('click', () => spDownloadFormsZip([...st.selected]));

	// Bulk Move (reveals a destination-repository picker) and bulk Delete.
	const moveBtn = panel.querySelector('.sp-forms-move-selected');
	const moveWrap = panel.querySelector('.sp-forms-move-wrap');
	if (moveBtn && moveWrap) {
		moveBtn.addEventListener('click', () => { moveWrap.hidden = !moveWrap.hidden; });
		panel.querySelector('.sp-forms-move-cancel')?.addEventListener('click', () => { moveWrap.hidden = true; });
		panel.querySelector('.sp-forms-move-go')?.addEventListener('click', () => spMoveForms(cat, panel));
	}
	const delSelBtn = panel.querySelector('.sp-forms-delete-selected');
	if (delSelBtn) delSelBtn.addEventListener('click', () => spBulkDeleteForms(cat));

	const uploadBtn = panel.querySelector('.sp-forms-upload-btn');
	const form = panel.querySelector('.sp-forms-upload-form');
	if (uploadBtn && form) {
		uploadBtn.addEventListener('click', () => spOpenFormUpload(panel, cat, null));
		panel.querySelector('.sp-forms-cancel-btn')?.addEventListener('click', () => spCloseFormUpload(panel));
		form.addEventListener('submit', (e) => { e.preventDefault(); spSubmitFormUpload(panel, cat); });
	}
}

// Open the upload form for a new upload (item=null) or a Replace (item=the row to edit).
function spOpenFormUpload(panel, cat, item) {
	const form = panel.querySelector('.sp-forms-upload-form');
	const wrap = panel.querySelector('.sp-forms-upload-wrap');
	if (!form || !wrap) return;
	const title = form.querySelector('.sp-forms-upload-title');
	const note = form.querySelector('.sp-forms-replace-note');
	const fileLabel = form.querySelector('.sp-forms-file-label');
	const idEl = form.querySelector('.sp-forms-edit-id');
	const nameEl = form.querySelector('.sp-forms-field-name');
	const fileEl = form.querySelector('.sp-forms-field-file');
	const subEl = form.querySelector('.sp-forms-field-sub');
	const saveBtn = form.querySelector('.sp-forms-save-btn');
	const status = form.querySelector('.sp-forms-upload-status');
	if (status) { status.hidden = true; status.textContent = ''; }
	fileEl.value = '';
	// Sub-folder: editing keeps the form's folder; a new upload defaults to the active chip filter.
	if (subEl) subEl.value = item ? (item.sub_category || '') : ((_spForms[cat] && _spForms[cat].filter) || '');
	if (item) {
		idEl.value = item.id;
		nameEl.value = item.name || '';
		if (title) title.textContent = 'Replace Form';
		if (note) note.hidden = false;
		if (fileLabel) fileLabel.textContent = 'Replace file (optional)';
		if (saveBtn) saveBtn.textContent = 'Save Changes';
	} else {
		idEl.value = '';
		nameEl.value = '';
		if (title) title.textContent = 'Upload Form';
		if (note) note.hidden = true;
		if (fileLabel) fileLabel.textContent = 'File';
		if (saveBtn) saveBtn.textContent = 'Upload';
	}
	wrap.hidden = false;
	nameEl.focus();
}

function spCloseFormUpload(panel) {
	const wrap = panel.querySelector('.sp-forms-upload-wrap');
	if (wrap) wrap.hidden = true;
}

async function spSubmitFormUpload(panel, cat) {
	const form = panel.querySelector('.sp-forms-upload-form');
	const idEl = form.querySelector('.sp-forms-edit-id');
	const nameEl = form.querySelector('.sp-forms-field-name');
	const fileEl = form.querySelector('.sp-forms-field-file');
	const saveBtn = form.querySelector('.sp-forms-save-btn');
	const status = form.querySelector('.sp-forms-upload-status');
	const say = (msg, err) => { if (status) { status.hidden = false; status.classList.toggle('sp-forms-err', !!err); status.textContent = msg; } };

	const editId = parseInt(idEl.value, 10) || 0;
	const name = nameEl.value.trim();
	const file = fileEl.files && fileEl.files[0];
	if (!name) { say('Please give the form a name.', true); nameEl.focus(); return; }
	if (!editId && !file) { say('Please choose a file to upload.', true); return; }

	const subEl = form.querySelector('.sp-forms-field-sub');
	const fields = { name, category: cat };
	if (subEl) fields.sub_category = subEl.value || '';
	let action = 'site_pulse_upload_form';
	if (editId) { action = 'site_pulse_replace_form'; fields.id = editId; }

	if (saveBtn) saveBtn.disabled = true;
	say(editId ? 'Saving…' : 'Uploading…', false);
	try {
		const res = await spFormUpload(action, fields, file);
		if (!res || !res.success) { say((res && res.data && res.data.message) || 'Upload failed.', true); return; }
		spCloseFormUpload(panel);
		await loadForms(cat);
	} catch (e) {
		say('Upload failed — please try again.', true);
	} finally {
		if (saveBtn) saveBtn.disabled = false;
	}
}

// Multipart upload (spAjax can't carry a File). Returns the parsed JSON response.
async function spFormUpload(action, fields, file) {
	const body = new FormData();
	body.append('action', action);
	body.append('nonce', spGetNonce());
	for (const [k, v] of Object.entries(fields)) body.append(k, v);
	if (file) body.append('file', file);
	const res = await fetch(D.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body });
	const text = await res.text();
	return JSON.parse(text);
}

async function loadForms(cat) {
	const st = _spForms[cat];
	st.selected = new Set();   // a fresh load clears any selection
	const tbody = $(`#sp-panel-forms-${cat} .sp-forms-tbody`);
	if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="sp-forms-loading">Loading…</td></tr>';
	try {
		const res = await spAjax('site_pulse_get_forms', { category: cat });
		if (res && res.success) {
			st.items = res.data.forms || [];
			st.canUpload = !!res.data.can_upload;
		} else { st.items = []; }
	} catch (e) { st.items = []; }
	renderForms(cat);
}

function spFormsSortVal(item, col) {
	if (col === 'date') return item.date || '';
	return (item[col] || '').toString().toLowerCase();
}

function renderForms(cat) {
	const st = _spForms[cat];
	const panel = $(`#sp-panel-forms-${cat}`);
	if (!panel) return;
	const tbody = panel.querySelector('.sp-forms-tbody');
	const empty = panel.querySelector('.sp-forms-empty');
	if (!tbody) return;

	const isAll = cat === 'all';   // the All search is broadened to format/repository
	let rows = st.items.slice();

	// Sub-folder chip filter (keep the chips' active state in sync — covers nav deep-links too).
	const chips = panel.querySelectorAll('.sp-forms-subchip');
	if (chips.length) chips.forEach(c => c.classList.toggle('active', (c.dataset.sub || '') === (st.filter || '')));
	if (st.filter) rows = rows.filter(x => x.sub_category === st.filter);

	// Search box → form name (broadened to format/repository on the All view).
	if (st.search) {
		const q = st.search;
		rows = rows.filter(x =>
			(x.name || '').toLowerCase().includes(q) ||
			(isAll && (
				(x.format || '').toLowerCase().includes(q) ||
				(x.category_label || '').toLowerCase().includes(q))));
	}
	const { col, dir } = st.sort;
	rows.sort((a, b) => {
		const av = spFormsSortVal(a, col), bv = spFormsSortVal(b, col);
		if (av < bv) return dir === 'asc' ? -1 : 1;
		if (av > bv) return dir === 'asc' ? 1 : -1;
		return 0;
	});

	panel.querySelectorAll('.sp-forms-sort').forEach(th => {
		const isCol = th.getAttribute('data-sort') === col;
		th.classList.toggle('sp-sort-asc', isCol && dir === 'asc');
		th.classList.toggle('sp-sort-desc', isCol && dir === 'desc');
	});

	if (!rows.length) {
		tbody.innerHTML = '';
		if (empty) { empty.hidden = false; const p = empty.querySelector('p'); if (p) p.textContent = st.search ? 'No forms match.' : 'No forms here yet.'; }
		return;
	}
	if (empty) empty.hidden = true;

	// Each row: a select checkbox, Name · Repository · Format · Date, then actions (Download for
	// everyone; Replace/Delete only for an editor on a category panel).
	let html = '';
	rows.forEach(x => {
		const dl = `<a class="unique sp-forms-row-btn sp-forms-download" href="${esc(x.url)}" download="${esc(x.file_name || '')}">Download</a>`;
		const edit = (!isAll && x.can_edit)
			? `<button type="button" class="unique sp-forms-row-btn sp-forms-replace" data-id="${x.id}">Replace</button><button type="button" class="unique sp-forms-row-btn sp-forms-delete" data-id="${x.id}">Delete</button>`
			: '';
		html += '<tr>'
			+ `<td class="sp-forms-check"><input type="checkbox" class="sp-forms-row-check" data-id="${x.id}" aria-label="Select form"${st.selected.has(x.id) ? ' checked' : ''}></td>`
			+ `<td class="sp-forms-name"><a class="unique" href="${esc(x.url)}" target="_blank" rel="noopener">${esc(x.name)}</a>${(x.sub_label && (isAll || !st.filter)) ? ` <span class="unique sp-forms-folder-badge">${esc(x.sub_label)}</span>` : ''}</td>`
			+ `<td><span class="unique sp-forms-repo">${esc(x.category_label || '—')}</span></td>`
			+ `<td><span class="unique sp-forms-format">${esc(x.format || '—')}</span></td>`
			+ `<td>${esc(x.date_label || '')}</td>`
			+ `<td class="sp-forms-actions">${dl}${edit}</td>`
			+ '</tr>';
	});
	tbody.innerHTML = html;
	markUniqueSpans(tbody);

	tbody.querySelectorAll('.sp-forms-row-check').forEach(cb => {
		cb.addEventListener('change', () => {
			const id = parseInt(cb.getAttribute('data-id'), 10);
			if (cb.checked) st.selected.add(id); else st.selected.delete(id);
			spFormsUpdateBulk(cat);
		});
	});
	tbody.querySelectorAll('.sp-forms-replace').forEach(btn => {
		btn.addEventListener('click', () => {
			const item = st.items.find(i => i.id === parseInt(btn.getAttribute('data-id'), 10));
			if (item) spOpenFormUpload(panel, cat, item);
		});
	});
	tbody.querySelectorAll('.sp-forms-delete').forEach(btn => {
		btn.addEventListener('click', () => spDeleteForm(cat, parseInt(btn.getAttribute('data-id'), 10)));
	});
	spFormsUpdateBulk(cat);
}

// Sync the "Download selected" button + the select-all checkbox to the current selection.
function spFormsUpdateBulk(cat) {
	const panel = $(`#sp-panel-forms-${cat}`);
	const st = _spForms[cat];
	if (!panel || !st) return;
	const n = st.selected.size;
	const bulkBtn = panel.querySelector('.sp-forms-download-selected');
	if (bulkBtn) { bulkBtn.disabled = n === 0; bulkBtn.textContent = n ? `Download selected (${n})` : 'Download selected'; }
	const moveBtn = panel.querySelector('.sp-forms-move-selected');
	if (moveBtn) { moveBtn.disabled = n === 0; moveBtn.textContent = n ? `Move selected (${n})` : 'Move selected'; }
	const delSelBtn = panel.querySelector('.sp-forms-delete-selected');
	if (delSelBtn) { delSelBtn.disabled = n === 0; delSelBtn.textContent = n ? `Delete selected (${n})` : 'Delete selected'; }
	if (n === 0) { const mw = panel.querySelector('.sp-forms-move-wrap'); if (mw) mw.hidden = true; }
	const checkAll = panel.querySelector('.sp-forms-check-all');
	if (checkAll) {
		const boxes = [...panel.querySelectorAll('.sp-forms-row-check')];
		const checked = boxes.filter(b => b.checked).length;
		checkAll.checked = boxes.length > 0 && checked === boxes.length;
		checkAll.indeterminate = checked > 0 && checked < boxes.length;
	}
}

// Bulk delete the selected forms (only your own unless god; server enforces + reports skips).
async function spBulkDeleteForms(cat) {
	const st = _spForms[cat];
	const ids = [...st.selected];
	if (!ids.length) return;
	if (!confirm(`Delete ${ids.length} selected form${ids.length === 1 ? '' : 's'}? This cannot be undone.`)) return;
	try {
		const res = await spAjax('site_pulse_bulk_delete_forms', { ids: JSON.stringify(ids) });
		if (!res || !res.success) { alert((res && res.data && res.data.message) || 'Delete failed.'); return; }
		const d = res.data;
		spFlash(`Deleted ${d.deleted} form${d.deleted === 1 ? '' : 's'}${d.skipped ? `, ${d.skipped} skipped (not yours)` : ''}`);
		await loadForms(cat);
	} catch (e) { alert('Delete failed — please try again.'); }
}

// Bulk move the selected forms to the repository chosen in the move picker.
async function spMoveForms(cat, panel) {
	const st = _spForms[cat];
	const ids = [...st.selected];
	if (!ids.length) return;
	const sel = panel.querySelector('.sp-forms-move-target');
	const val = sel ? sel.value : '';
	if (!val) { alert('Choose a destination repository or sub-folder.'); return; }
	const [category, sub_category] = val.split('::');   // "cat" or "cat::sub"
	try {
		const res = await spAjax('site_pulse_move_forms', { ids: JSON.stringify(ids), category, sub_category: sub_category || '' });
		if (!res || !res.success) { alert((res && res.data && res.data.message) || 'Move failed.'); return; }
		const d = res.data;
		const wrap = panel.querySelector('.sp-forms-move-wrap'); if (wrap) wrap.hidden = true;
		if (sel) sel.value = '';
		spFlash(`Moved ${d.moved} form${d.moved === 1 ? '' : 's'}${d.skipped ? `, ${d.skipped} skipped (not yours)` : ''}`);
		await loadForms(cat);
	} catch (e) { alert('Move failed — please try again.'); }
}

// Zip the selected forms server-side and save the archive.
async function spDownloadFormsZip(ids) {
	if (!ids.length) return;
	const body = new FormData();
	body.append('action', 'site_pulse_download_forms');
	body.append('nonce', spGetNonce());
	ids.forEach(id => body.append('ids[]', id));
	let res;
	try { res = await fetch(D.ajaxUrl || '/wp-admin/admin-ajax.php', { method: 'POST', credentials: 'same-origin', body }); }
	catch (e) { alert('Download failed — please try again.'); return; }
	const ct = res.headers.get('content-type') || '';
	if (!res.ok || ct.indexOf('application/json') !== -1) {
		let msg = 'Could not prepare the download.';
		try { const j = await res.json(); if (j && j.data && j.data.message) msg = j.data.message; } catch (e) {}
		alert(msg);
		return;
	}
	const blob = await res.blob();
	const url = URL.createObjectURL(blob);
	const a = document.createElement('a');
	a.href = url; a.download = 'forms.zip';
	document.body.appendChild(a); a.click(); a.remove();
	setTimeout(() => URL.revokeObjectURL(url), 2000);
}

async function spDeleteForm(cat, id) {
	const st = _spForms[cat];
	const item = st.items.find(i => i.id === id);
	if (!confirm(`Delete "${item ? item.name : 'this form'}"? This cannot be undone.`)) return;
	try {
		const res = await spAjax('site_pulse_delete_form', { id });
		if (res && res.success) {
			st.items = st.items.filter(i => i.id !== id);
			renderForms(cat);
		} else {
			alert((res && res.data && res.data.message) || 'Could not delete the form.');
		}
	} catch (e) { alert('Could not delete the form.'); }
}

/* ---- Settings → Forms: manage repositories (add / rename / delete) ---- */

let _spFormCats = [];   // [{key, label, count}] — key='' for unsaved new rows

async function loadFormSettings() {
	const wrap = $('#sp-admin-forms-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_form_settings', {});
		if (!res || !res.success) { wrap.innerHTML = '<p>Error loading form settings.</p>'; return; }
		_spFormCats = (res.data.categories || []).map(c => ({ key: c.key, label: c.label, count: c.count || 0, visible: c.visible !== false, subs: (c.children || []).map(s => s.label) }));
		renderFormSettings(wrap);
	} catch (e) { wrap.innerHTML = '<p>Error loading form settings.</p>'; }
}

function renderFormSettings(wrap) {
	let html = '<div class="sp-card sp-settings-card">';
	html += '<h3 class="unique sp-forms-set-title">Form Repositories</h3>';
	html += '<p class="sp-help-text sp-forms-set-help">Add, rename, remove, or drag to reorder the repositories that forms are filed under. Add optional <strong>sub-folders</strong> per repository (comma-separated — e.g. “Babe’s, Bubba’s”) to get a third menu level. Renaming keeps the forms inside it; deleting one moves its forms into the first repository. Changes save automatically — the menu reflects the new order/names on the next page reload.</p>';
	html += '<div class="sp-forms-cat-rows">';
	_spFormCats.forEach((c, i) => { html += spFormCatRow(c, i); });
	html += '</div>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-forms-cat-add">+ Add Repository</button>';
	html += '<div class="sp-forms-set-foot"><span class="sp-help-text sp-forms-cat-status"></span></div>';
	html += '</div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	wrap.querySelector('.sp-forms-cat-add').addEventListener('click', () => {
		_spFormCats.push({ key: '', label: '', count: 0 });
		renderFormSettings(wrap);
		const inputs = wrap.querySelectorAll('.sp-forms-cat-label');
		if (inputs.length) inputs[inputs.length - 1].focus();
	});

	wrap.querySelectorAll('.sp-forms-cat-row').forEach(row => {
		const idx = parseInt(row.getAttribute('data-idx'), 10);
		row.querySelector('.sp-forms-cat-label').addEventListener('input', e => {
			_spFormCats[idx].label = e.target.value;
			spQueueFormCatSave(false);                  // debounced auto-save while typing
		});
		const subField = row.querySelector('.sp-forms-cat-subfield');
		if (subField) subField.addEventListener('input', e => {
			_spFormCats[idx].subs = e.target.value.split(',').map(s => s.trim()).filter(Boolean);
			spQueueFormCatSave(false);
		});
		const visInput = row.querySelector('.sp-forms-cat-vis-input');
		if (visInput) visInput.addEventListener('change', e => {
			_spFormCats[idx].visible = e.target.checked;
			const lbl = row.querySelector('.sp-forms-cat-vis-label');
			if (lbl) lbl.textContent = e.target.checked ? 'Visible' : 'Hidden';
			spAutoSaveFormCats(false);                  // deliberate toggle — save now
		});
		row.querySelector('.sp-forms-cat-del').addEventListener('click', () => {
			const c = _spFormCats[idx];
			const msg = c.count > 0
				? `Delete "${c.label}"? Its ${c.count} form${c.count === 1 ? '' : 's'} will move to the first repository.`
				: `Delete "${c.label || 'this repository'}"?`;
			if (!confirm(msg)) return;
			_spFormCats.splice(idx, 1);
			renderFormSettings(wrap);
			spAutoSaveFormCats(true);                   // save now; re-fetch to refresh counts after any move
		});
	});

	initFormCatDragDrop(wrap);
}

function spFormCatRow(c, i) {
	const count = c.key
		? `<span class="unique sp-forms-cat-count">${c.count} form${c.count === 1 ? '' : 's'}</span>`
		: '<span class="unique sp-forms-cat-count sp-forms-cat-new">new</span>';
	const vis = c.visible !== false;
	return '<div class="sp-forms-cat-row" data-idx="' + i + '" draggable="true">'
		+ '<span class="unique sp-forms-cat-drag" aria-hidden="true">⠿</span>'
		+ `<input type="text" class="sp-input sp-forms-cat-label" maxlength="80" value="${esc(c.label)}" placeholder="Repository name">`
		+ count
		+ `<label class="sp-forms-cat-vis" title="Show this repository in the Forms menu"><input type="checkbox" class="sp-forms-cat-vis-input"${vis ? ' checked' : ''}><span class="sp-switch"><span class="sp-switch-thumb"></span></span><span class="unique sp-forms-cat-vis-label">${vis ? 'Visible' : 'Hidden'}</span></label>`
		+ iconBtn('delete', 'sp-forms-cat-del', 'title="Delete repository" aria-label="Delete repository"')
		+ `<div class="sp-forms-cat-subwrap"><span class="unique sp-forms-cat-subs-label">Sub-folders:</span><input type="text" class="sp-input sp-forms-cat-subfield" value="${esc((c.subs || []).join(', '))}" placeholder="comma-separated — e.g. Babe’s, Bubba’s"></div>`
		+ '</div>';
}

// Drag to reorder — mirrors the report-fields reorder. Reorders _spFormCats, re-renders, autosaves.
function initFormCatDragDrop(wrap) {
	let dragItem = null;
	wrap.querySelectorAll('.sp-forms-cat-row[draggable]').forEach(item => {
		item.addEventListener('dragstart', (e) => { dragItem = item; item.classList.add('sp-dragging'); e.dataTransfer.effectAllowed = 'move'; });
		item.addEventListener('dragend', () => { item.classList.remove('sp-dragging'); dragItem = null; wrap.querySelectorAll('.sp-forms-cat-row').forEach(el => el.classList.remove('sp-drag-over')); });
		item.addEventListener('dragover', (e) => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; if (dragItem && item !== dragItem) item.classList.add('sp-drag-over'); });
		item.addEventListener('dragleave', () => item.classList.remove('sp-drag-over'));
		item.addEventListener('drop', (e) => {
			e.preventDefault();
			item.classList.remove('sp-drag-over');
			if (!dragItem || item === dragItem) return;
			const rows = [...wrap.querySelectorAll('.sp-forms-cat-row')];
			const from = rows.indexOf(dragItem), to = rows.indexOf(item);
			if (from < 0 || to < 0 || from === to) return;
			const [moved] = _spFormCats.splice(from, 1);
			_spFormCats.splice(to, 0, moved);
			renderFormSettings(wrap);
			spAutoSaveFormCats(false);
		});
	});
}

let _spFormCatSaveTimer = null;
function spQueueFormCatSave(refetch) {
	clearTimeout(_spFormCatSaveTimer);
	_spFormCatSaveTimer = setTimeout(() => spAutoSaveFormCats(refetch), 600);
}

async function spAutoSaveFormCats(refetch) {
	clearTimeout(_spFormCatSaveTimer);
	const wrap = $('#sp-admin-forms-content');
	if (!wrap) return;
	const status = wrap.querySelector('.sp-forms-cat-status');
	const setStatus = (msg, err) => { if (status) { status.textContent = msg; status.classList.toggle('sp-forms-cat-err', !!err); } };

	// Guard against accidental data loss: an EXISTING repository can't be blanked out here.
	if (_spFormCats.some(c => c.key && (c.label || '').trim() === '')) { setStatus('Repository names can’t be empty.', true); return; }
	const cats = _spFormCats.map(c => ({
		key:      c.key || '',
		label:    (c.label || '').trim(),
		visible:  c.visible !== false ? 1 : 0,
		children: (c.subs || []).map(l => ({ label: l })),   // server slugs + dedups these
	})).filter(c => c.label !== '');
	if (!cats.length) { setStatus('Add at least one repository.', true); return; }

	setStatus('Saving…', false);
	try {
		const res = await spAjax('site_pulse_save_form_categories', { categories: JSON.stringify(cats) });
		if (res && res.success) {
			// Sync server-assigned keys back onto the non-empty rows (same order) so a new row keeps
			// its key on later edits — without re-rendering (preserves the input's focus while typing).
			const saved = res.data.categories || [];
			let si = 0;
			_spFormCats.forEach(c => { if ((c.label || '').trim() !== '') { if (saved[si]) c.key = saved[si].key; si++; } });
			const moved = res.data.moved || 0;
			setStatus(moved ? `Saved ✓ — moved ${moved} form${moved === 1 ? '' : 's'} to “${res.data.moved_to}”` : 'Saved ✓', false);
			if (refetch) loadFormSettings();
		} else {
			setStatus((res && res.data && res.data.message) || 'Save failed', true);
		}
	} catch (e) {
		setStatus('Save failed', true);
	}
}

// Full-screen "AI is thinking" overlay: a dimmed backdrop + accent spinner that fades in/out.
// Ref-counted so overlapping AI calls share one overlay. Call spShowAiSpinner(label) when an AI
// request starts and spHideAiSpinner() when it ends (use try/finally so it always clears).
let _spAiSpinnerEl = null, _spAiSpinnerCount = 0;
function spShowAiSpinner(label) {
	_spAiSpinnerCount++;
	if (!_spAiSpinnerEl) {
		const o = document.createElement('div');
		o.className = 'sp-ai-overlay';
		o.setAttribute('role', 'status');
		o.setAttribute('aria-live', 'polite');
		o.innerHTML = '<div class="sp-ai-spinner" aria-hidden="true"></div><div class="sp-ai-overlay-label"></div>';
		document.body.appendChild(o);
		_spAiSpinnerEl = o;
		void o.offsetWidth;                 // reflow so the fade-in transition runs
		o.classList.add('sp-ai-show');
	}
	const lbl = $('.sp-ai-overlay-label', _spAiSpinnerEl);
	if (lbl) lbl.textContent = label || 'Thinking…';
}
function spHideAiSpinner() {
	_spAiSpinnerCount = Math.max(0, _spAiSpinnerCount - 1);
	if (_spAiSpinnerCount > 0) return;
	const o = _spAiSpinnerEl; _spAiSpinnerEl = null;
	if (!o) return;
	o.classList.remove('sp-ai-show');
	setTimeout(() => { o.remove(); }, 300);
}

// Wire receipt-upload controls inside `wrap`. Supports one or more .sp-receipt-btn buttons, each
// immediately followed by its own .sp-receipt-input, plus a shared .sp-receipt-status. On a photo
// it crops + shrinks + attaches the receipt, then AI-scans (best effort) and calls fill(...).
function spWireReceiptScan(wrap, section, fill) {
	const status = $('.sp-receipt-status', wrap);
	const buttons = $$('.sp-receipt-btn', wrap);
	if (!buttons.length) return;
	const setBusy = b => buttons.forEach(x => x.disabled = b);
	const say = (msg, err) => { if (status) { status.hidden = false; status.classList.toggle('sp-receipt-err', !!err); status.textContent = msg; } };

	const handle = async (input) => {
		const file = input.files && input.files[0];
		if (!file) return;
		setBusy(true); say('Reading receipt…', false); spShowAiSpinner('Reading receipt…');
		try {
			let scanUrl;
			try { scanUrl = await spImageToDataURL(file, 1600); }
			catch (e) { say('Could not read that image.', true); return; }
			// Scan first (best-effort) so we get the AI corners, then de-skew + crop with them.
			let scan = null;
			try { scan = await spScanReceiptFromDataURL(scanUrl, section); } catch (e) {}
			try {
				const receipt = await spProcessReceipt(scanUrl, scan && scan.corners, 1000);
				wrap._receiptData = receipt; wrap._receiptRemove = false;
				spShowReceiptThumb(wrap, receipt);
			} catch (e) {}
			if (scan) {
				fill(scan);
				say('✓ Receipt attached and fields filled — review, then Save.', false);
			}
			else { say('Receipt attached. Couldn’t auto-read it — enter the details, then Save.', false); }
		} finally {
			setBusy(false); input.value = ''; spHideAiSpinner();
		}
	};

	buttons.forEach(btn => {
		const input = btn.nextElementSibling;
		if (!input || !input.classList || !input.classList.contains('sp-receipt-input')) return;
		btn.addEventListener('click', () => input.click());
		input.addEventListener('change', () => handle(input));
	});
}


/* ---- Vehicle Expenses (Section B of the expense report) ---- */

let _spVexp = { cats: {}, list: [], editId: 0, start: '', end: '' };

// Category → stat-card icon (falls back to a wallet for anything unmapped).
const VEXP_ICONS = { fuel: 'fuel', wash: 'wash', parking: 'parking', repairs: 'car-repairs', trailers: 'trailer', parade_vehicle: 'car' };

async function initVehicleExpenses() {
	const addBtn = $('#sp-vexp-add-btn');
	if (addBtn && !addBtn._wired) {
		addBtn._wired = true;
		addBtn.addEventListener('click', () => showVexpForm());
	}
	const panel = $('#sp-panel-vehicle-expenses');
	if (panel && !panel._filterWired) {
		panel._filterWired = true;
		await spEnsurePeriodConfig();
		const [s, e] = spBuildPeriodToolbar('vexp', $('#sp-vexp-toolbar-wrap'), (st, en) => {
			_spVexp.start = st; _spVexp.end = en; loadVehicleExpenses();
		}, addBtn);
		_spVexp.start = s; _spVexp.end = e;
	}
	loadVehicleExpenses();
}

// Shared across all four expense sections: every "move to" destination (section + category) for the
// edit-form picker, filled from any get_expenses response. Lets a receipt filed in the wrong panel be
// re-categorized (even across sections, e.g. Vehicle/Fuel → Business Meals) on save.
let _spExpDestinations = [];
function spExpDestOptions(curSection, curCategory) {
	return _spExpDestinations.map(d => {
		const val = `${d.section}|${d.category}`;
		const sel = (d.section === curSection && (d.category || '') === (curCategory || '')) ? ' selected' : '';
		return `<option value="${esc(val)}"${sel}>${esc(d.label)}</option>`;
	}).join('');
}
function spExpDestParse(v) {
	const parts = String(v || '').split('|');
	return { section: parts[0] || 'B', category: parts[1] || '' };
}
function spExpDestLabel(section, category) {
	const d = _spExpDestinations.find(d => d.section === section && (d.category || '') === (category || ''));
	return d ? d.label : 'another category';
}

// Lightweight, auto-dismissing toast (bottom-center). Reusable anywhere; used here to confirm a
// cross-section expense move, since the item then leaves the current panel.
let _spToastTimer = null;
function spToast(message, ms = 3200) {
	let el = document.getElementById('sp-toast');
	if (!el) { el = document.createElement('div'); el.id = 'sp-toast'; el.className = 'sp-toast'; document.body.appendChild(el); }
	el.textContent = message;
	// force reflow so re-triggering the same toast replays the transition
	void el.offsetWidth;
	el.classList.add('sp-toast-show');
	clearTimeout(_spToastTimer);
	_spToastTimer = setTimeout(() => el.classList.remove('sp-toast-show'), ms);
}

// When a period is submitted/approved it locks: hide the "+ Add Expense" button and show a banner.
// (Row edit/delete + save are also blocked server-side, so this is the friendly front for that.)
function spApplyExpenseLock(prefix, locked) {
	const addBtn = $('#sp-' + prefix + '-add-btn');
	if (addBtn) addBtn.style.display = locked ? 'none' : '';
	const content = $('#sp-' + prefix + '-content');
	const host = content && content.parentElement;
	if (!host) return;
	let banner = host.querySelector('.sp-locked-banner-' + prefix);
	if (locked) {
		if (!banner) {
			banner = document.createElement('div');
			banner.className = 'sp-locked-banner sp-locked-banner-' + prefix;
			banner.textContent = 'This pay period has been submitted for approval and is locked. Ask your supervisor to send it back if you need to make changes.';
			host.insertBefore(banner, content);
		}
	} else if (banner) { banner.remove(); }
}

async function loadVehicleExpenses() {
	const wrap = $('#sp-vexp-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_expenses', { section: 'B', start: _spVexp.start, end: _spVexp.end });
		if (!res.success) { wrap.innerHTML = '<p>Error loading expenses.</p>'; return; }
		_spVexp.cats = res.data.categories || {};
		_spVexp.list = res.data.expenses || [];
		_spExpDestinations = res.data.destinations || _spExpDestinations;
		renderVehicleExpenses();
		spApplyExpenseLock('vexp', res.data.locked);
	} catch (e) { wrap.innerHTML = '<p>Error loading expenses.</p>'; }
}

function renderVehicleExpenses() {
	const wrap = $('#sp-vexp-content');
	if (!wrap) return;
	const cats = _spVexp.cats, catKeys = Object.keys(cats), list = _spVexp.list;
	const fmt = n => '$' + mileageNumFmt(n);
	const catLabel = k => (cats[k] && cats[k].label) || k || '—';

	const summary = $('#sp-vexp-summary');

	if (!list.length) {
		if (summary) summary.innerHTML = '';
		wrap.innerHTML = '<div class="sp-empty-state"><p>No vehicle expenses in this period. Click “+ Add Expense” to add one, or scan a receipt.</p></div>';
		return;
	}

	// One row per expense: Date | Category | Description | Amount. Category subtotals + the
	// Section B total summarize below (scales to any number of categories).
	const totals = {}; catKeys.forEach(k => totals[k] = 0);
	let grand = 0;

	let html = '<div class="sp-card sp-table-card"><table class="sp-table sp-mileage-table sp-vexp-table"><thead class="sp-thead"><tr>';
	html += '<th>Date</th><th>Category</th><th>Description</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th><th></th>';
	html += '</tr></thead><tbody>';

	list.forEach(x => {
		const amt = parseFloat(x.amount) || 0;
		grand += amt;
		if (totals[x.category] != null) totals[x.category] += amt;
		html += '<tr>';
		html += `<td>${esc(formatDate(x.expense_date))}</td>`;
		html += `<td>${esc(catLabel(x.category))}</td>`;
		html += `<td>${esc(x.description || '')}</td>`;
		html += `<td>${esc(x.store_number || '')}</td>`;
		html += `<td class="sp-num">${fmt(amt)}</td>`;
		html += `<td class="sp-mileage-row-actions">${x.receipt_url ? receiptIconBtn('sp-vexp-receipt-btn', x.id) : ''}${iconBtn('edit', 'sp-vexp-edit-btn', `data-id="${x.id}"`)}${iconBtn('delete', 'sp-vexp-del-btn', `data-id="${x.id}"`)}</td>`;
		html += '</tr>';
	});

	html += '</tbody></table></div>';

	// Stat cards: the Section B total + a card per used category (the GL-account groupings the
	// Summary block will key on later). Mirrors the Mileage screen's at-a-glance header.
	const used = catKeys.filter(k => totals[k] > 0);
	if (summary) {
		let cards = spStatCard('Total', fmt(grand), 'dollar-sign');
		used.forEach(k => cards += spStatCard(catLabel(k), fmt(totals[k]), VEXP_ICONS[k] || 'wallet'));
		summary.innerHTML = `<div class="sp-stat-grid">${cards}</div>`;
	}

	wrap.innerHTML = html;

	$$('.sp-vexp-edit-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spVexp.list.find(e => String(e.id) === String(b.dataset.id));
		if (x) showVexpForm(x);
	}));
	$$('.sp-vexp-del-btn', wrap).forEach(b => b.addEventListener('click', async () => {
		if (!confirm('Delete this expense?')) return;
		const r = await spAjax('site_pulse_delete_expense', { id: b.dataset.id });
		if (r.success) loadVehicleExpenses();
		else alert(r.data?.message || 'Error deleting.');
	}));
	$$('.sp-vexp-receipt-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spVexp.list.find(e => String(e.id) === String(b.dataset.id));
		if (x && x.receipt_url) spShowReceiptModal(x.receipt_url);
	}));
}

function showVexpForm(x) {
	const wrap = $('#sp-vexp-form-wrap');
	if (!wrap) return;
	_spVexp.editId = x ? x.id : 0;
	wrap._receiptData = null; wrap._receiptRemove = false;	const d = new Date();
	const today = `${d.getFullYear()}-${mPad2(d.getMonth() + 1)}-${mPad2(d.getDate())}`;

	wrap.innerHTML = `
		<div class="sp-card sp-vexp-form">
			<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${x ? 'Edit' : 'Add'} Vehicle Expense</h3></div>
			<div class="sp-card-body">
			<div class="sp-receipt-row sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">${ICON_CAMERA} Take Photo</button>
				<input type="file" accept="image/*" capture="environment" class="sp-receipt-input" hidden>
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">Upload</button>
				<input type="file" accept="image/*" class="sp-receipt-input" hidden>
				<span class="sp-receipt-status sp-help-text" hidden></span>
			</div>
			<div class="sp-vexp-fields sp-card-item">
				<div class="sp-vexp-form-grid">
					<div class="sp-form-group"><label>Date</label><input type="date" id="sp-vexp-date" class="sp-input" value="${esc(x ? x.expense_date : today)}"></div>
					<div class="sp-form-group"><label>Category</label><select id="sp-vexp-cat" class="sp-select">${spExpDestOptions(x ? x.section : 'B', x ? x.category : (Object.keys(_spVexp.cats || {})[0] || ''))}</select></div>
					<div class="sp-form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" id="sp-vexp-amount" class="sp-input" value="${x ? (parseFloat(x.amount) || 0).toFixed(2) : ''}"></div>
					<div class="sp-form-group"><label>Charge To</label><select id="sp-vexp-store" class="sp-select">${mileageChargeOptionsHtml((x && x.store_number) ? x.store_number : '99')}</select></div>
				</div>
				<div class="sp-form-group"><label>Description</label><input type="text" id="sp-vexp-desc" class="sp-input" value="${esc(x ? (x.description || '') : '')}" placeholder="e.g. Shell — fuel"></div>
			</div>
			<div class="sp-vexp-form-actions sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-vexp-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-vexp-cancel">Cancel</button>
			</div>
			</div>
		</div>`;
	wrap.hidden = false;
	setExpenseChrome('vexp', true);
	markUniqueSpans(wrap);

	// Receipt scan → fill the fields (reusable across expense modules).
	spWireReceiptScan(wrap, 'B', (r) => {
		if (r.date) { const el = $('#sp-vexp-date', wrap); if (el) el.value = r.date; }
		if (r.category) { const el = $('#sp-vexp-cat', wrap); if (el) el.value = 'B|' + r.category; }
		if (r.amount > 0) { const el = $('#sp-vexp-amount', wrap); if (el) el.value = (parseFloat(r.amount) || 0).toFixed(2); }
		if (r.description) { const el = $('#sp-vexp-desc', wrap); if (el) el.value = r.description; }
	});

	if (x && x.receipt_url) spShowReceiptThumb(wrap, x.receipt_url);	$('#sp-vexp-cancel', wrap).addEventListener('click', hideVexpForm);
	$('#sp-vexp-save', wrap).addEventListener('click', saveVexp);
	// No auto-focus on the date: on iOS, focusing a date input pops the calendar immediately, but
	// most users open this form to shoot/upload a receipt — so leave the photo buttons in front.
}

function hideVexpForm() {
	const wrap = $('#sp-vexp-form-wrap');
	if (wrap) { wrap.hidden = true; wrap.innerHTML = ''; }
	setExpenseChrome('vexp', false);
	_spVexp.editId = 0;
}

// Save an expense with the duplicate-receipt guard. If the server flags a likely duplicate (same
// section + date + amount already on file), ask the user once whether it's really a separate receipt;
// on confirm, resubmit with allow_duplicate so it goes through. Returns the server result, with
// `cancelled: true` when the user declined — callers skip the generic "Error saving" alert in that case.
async function spSaveExpenseGuarded(payload) {
	let r = await spAjax('site_pulse_save_expense', payload);
	if (!r.success && r.data && r.data.code === 'duplicate') {
		if (confirm(r.data.message || 'This looks like a duplicate expense. Add it anyway?')) {
			r = await spAjax('site_pulse_save_expense', { ...payload, allow_duplicate: 1 });
		} else {
			return { success: false, cancelled: true };
		}
	}
	return r;
}

/* Show the add/edit expense form as its own screen (like the Mileage "Add a Day" flow) by hiding the
   panel's list chrome — summary, period toolbar, intro, list and the Add button — while the form is
   open, then restoring them on close. Same id pattern across vexp / meal / shop / oexp panels. */
function setExpenseChrome(prefix, hidden) {
	['#sp-' + prefix + '-add-btn', '#sp-' + prefix + '-summary', '#sp-' + prefix + '-toolbar-wrap', '#sp-' + prefix + '-content']
		.forEach(sel => $(sel)?.classList.toggle('sp-hidden', hidden));
	$('.sp-' + prefix + '-intro')?.closest('.sp-meta-bar')?.classList.toggle('sp-hidden', hidden);
}

async function saveVexp() {
	const date = $('#sp-vexp-date')?.value;
	const dest = spExpDestParse($('#sp-vexp-cat')?.value);
	const amount = parseFloat($('#sp-vexp-amount')?.value);
	const description = $('#sp-vexp-desc')?.value || '';
	const store_number = $('#sp-vexp-store')?.value || '';
	if (!date) { alert('Please choose a date.'); return; }
	if (!(amount > 0)) { alert('Please enter an amount greater than zero.'); return; }
	if (!spHasReceipt($('#sp-vexp-form-wrap'))) { alert('A receipt photo is required for every expense. Use Take Photo or Upload to attach one.'); return; }
	const btn = $('#sp-vexp-save');
	if (btn) btn.disabled = true;
	const payload = { section: dest.section, category: dest.category, expense_date: date, amount, description, store_number };
	if (_spVexp.editId) payload.id = _spVexp.editId;
	Object.assign(payload, spReceiptPayload($('#sp-vexp-form-wrap')));
	try {
		const r = await spSaveExpenseGuarded(payload);
		if (r.success) { if (dest.section !== 'B') spToast('Moved to ' + spExpDestLabel(dest.section, dest.category)); hideVexpForm(); loadVehicleExpenses(); }
		else { if (!r.cancelled) alert(r.data?.message || 'Error saving.'); if (btn) btn.disabled = false; }
	} catch (e) { alert('Error saving expense.'); if (btn) btn.disabled = false; }
}


/* ---- Travel Expenses (Section F) ---- */
// Built exactly like Vehicle Expenses (Section B): several named categories (Airfare / Hotel /
// Car Rental·Taxi / Meals) that all post to one GL account (91110). One line = category + amount.
let _spTrav = { cats: {}, list: [], editId: 0, start: '', end: '' };

// Category → stat-card icon (unmapped keys fall back to a wallet).
const TRAV_ICONS = { airfare: 'ticket', car_rental: 'car' };

async function initTravelExpenses() {
	const addBtn = $('#sp-trav-add-btn');
	if (addBtn && !addBtn._wired) {
		addBtn._wired = true;
		addBtn.addEventListener('click', () => showTravForm());
	}
	const panel = $('#sp-panel-travel-expenses');
	if (panel && !panel._filterWired) {
		panel._filterWired = true;
		await spEnsurePeriodConfig();
		const [s, e] = spBuildPeriodToolbar('trav', $('#sp-trav-toolbar-wrap'), (st, en) => {
			_spTrav.start = st; _spTrav.end = en; loadTravelExpenses();
		}, addBtn);
		_spTrav.start = s; _spTrav.end = e;
	}
	loadTravelExpenses();
}

async function loadTravelExpenses() {
	const wrap = $('#sp-trav-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_expenses', { section: 'F', start: _spTrav.start, end: _spTrav.end });
		if (!res.success) { wrap.innerHTML = '<p>Error loading expenses.</p>'; return; }
		_spTrav.cats = res.data.categories || {};
		_spTrav.list = res.data.expenses || [];
		_spExpDestinations = res.data.destinations || _spExpDestinations;
		renderTravelExpenses();
		spApplyExpenseLock('trav', res.data.locked);
	} catch (e) { wrap.innerHTML = '<p>Error loading expenses.</p>'; }
}

function renderTravelExpenses() {
	const wrap = $('#sp-trav-content');
	if (!wrap) return;
	const cats = _spTrav.cats, catKeys = Object.keys(cats), list = _spTrav.list;
	const fmt = n => '$' + mileageNumFmt(n);
	const catLabel = k => (cats[k] && cats[k].label) || k || '—';

	const summary = $('#sp-trav-summary');

	if (!list.length) {
		if (summary) summary.innerHTML = '';
		wrap.innerHTML = '<div class="sp-empty-state"><p>No travel expenses in this period. Click “+ Add Expense” to add one, or scan a receipt.</p></div>';
		return;
	}

	// One row per expense: Date | Category | Description | Amount. All four categories roll up to
	// the Section F total below (GL 91110).
	const totals = {}; catKeys.forEach(k => totals[k] = 0);
	let grand = 0;

	let html = '<div class="sp-card sp-table-card"><table class="sp-table sp-mileage-table sp-trav-table"><thead class="sp-thead"><tr>';
	html += '<th>Date</th><th>Category</th><th>Description</th><th class="sp-num">Amount</th><th></th>';
	html += '</tr></thead><tbody>';

	list.forEach(x => {
		const amt = parseFloat(x.amount) || 0;
		grand += amt;
		if (totals[x.category] != null) totals[x.category] += amt;
		html += '<tr>';
		html += `<td>${esc(formatDate(x.expense_date))}</td>`;
		html += `<td>${esc(catLabel(x.category))}</td>`;
		html += `<td>${esc(x.description || '')}</td>`;
		html += `<td class="sp-num">${fmt(amt)}</td>`;
		html += `<td class="sp-mileage-row-actions">${x.receipt_url ? receiptIconBtn('sp-trav-receipt-btn', x.id) : ''}${iconBtn('edit', 'sp-trav-edit-btn', `data-id="${x.id}"`)}${iconBtn('delete', 'sp-trav-del-btn', `data-id="${x.id}"`)}</td>`;
		html += '</tr>';
	});

	html += '</tbody></table></div>';

	// Stat cards: the Section F total + a card per used category.
	const used = catKeys.filter(k => totals[k] > 0);
	if (summary) {
		let cards = spStatCard('Total', fmt(grand), 'dollar-sign');
		used.forEach(k => cards += spStatCard(catLabel(k), fmt(totals[k]), TRAV_ICONS[k] || 'wallet'));
		summary.innerHTML = `<div class="sp-stat-grid">${cards}</div>`;
	}

	wrap.innerHTML = html;

	$$('.sp-trav-edit-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spTrav.list.find(e => String(e.id) === String(b.dataset.id));
		if (x) showTravForm(x);
	}));
	$$('.sp-trav-del-btn', wrap).forEach(b => b.addEventListener('click', async () => {
		if (!confirm('Delete this expense?')) return;
		const r = await spAjax('site_pulse_delete_expense', { id: b.dataset.id });
		if (r.success) loadTravelExpenses();
		else alert(r.data?.message || 'Error deleting.');
	}));
	$$('.sp-trav-receipt-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spTrav.list.find(e => String(e.id) === String(b.dataset.id));
		if (x && x.receipt_url) spShowReceiptModal(x.receipt_url);
	}));
}

function showTravForm(x) {
	const wrap = $('#sp-trav-form-wrap');
	if (!wrap) return;
	_spTrav.editId = x ? x.id : 0;
	wrap._receiptData = null; wrap._receiptRemove = false;	const d = new Date();
	const today = `${d.getFullYear()}-${mPad2(d.getMonth() + 1)}-${mPad2(d.getDate())}`;

	wrap.innerHTML = `
		<div class="sp-card sp-vexp-form">
			<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${x ? 'Edit' : 'Add'} Travel Expense</h3></div>
			<div class="sp-card-body">
			<div class="sp-receipt-row sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">${ICON_CAMERA} Take Photo</button>
				<input type="file" accept="image/*" capture="environment" class="sp-receipt-input" hidden>
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">Upload</button>
				<input type="file" accept="image/*" class="sp-receipt-input" hidden>
				<span class="sp-receipt-status sp-help-text" hidden></span>
			</div>
			<div class="sp-vexp-fields sp-card-item">
				<div class="sp-vexp-form-grid">
					<div class="sp-form-group"><label>Date</label><input type="date" id="sp-trav-date" class="sp-input" value="${esc(x ? x.expense_date : today)}"></div>
					<div class="sp-form-group"><label>Category</label><select id="sp-trav-cat" class="sp-select">${spExpDestOptions(x ? x.section : 'F', x ? x.category : (Object.keys(_spTrav.cats || {})[0] || ''))}</select></div>
					<div class="sp-form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" id="sp-trav-amount" class="sp-input" value="${x ? (parseFloat(x.amount) || 0).toFixed(2) : ''}"></div>
				</div>
				<div class="sp-form-group"><label>Description</label><input type="text" id="sp-trav-desc" class="sp-input" value="${esc(x ? (x.description || '') : '')}" placeholder="e.g. Delta — DFW to ATL"></div>
			</div>
			<div class="sp-vexp-form-actions sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-trav-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-trav-cancel">Cancel</button>
			</div>
			</div>
		</div>`;
	wrap.hidden = false;
	setExpenseChrome('trav', true);
	markUniqueSpans(wrap);

	// Receipt scan → fill the fields (reusable across expense modules).
	spWireReceiptScan(wrap, 'F', (r) => {
		if (r.date) { const el = $('#sp-trav-date', wrap); if (el) el.value = r.date; }
		if (r.category) { const el = $('#sp-trav-cat', wrap); if (el) el.value = 'F|' + r.category; }
		if (r.amount > 0) { const el = $('#sp-trav-amount', wrap); if (el) el.value = (parseFloat(r.amount) || 0).toFixed(2); }
		if (r.description) { const el = $('#sp-trav-desc', wrap); if (el) el.value = r.description; }
	});

	if (x && x.receipt_url) spShowReceiptThumb(wrap, x.receipt_url);	$('#sp-trav-cancel', wrap).addEventListener('click', hideTravForm);
	$('#sp-trav-save', wrap).addEventListener('click', saveTrav);
	// No auto-focus on the date (see showVexpForm): it pops the iOS calendar over the photo buttons.
}

function hideTravForm() {
	const wrap = $('#sp-trav-form-wrap');
	if (wrap) { wrap.hidden = true; wrap.innerHTML = ''; }
	setExpenseChrome('trav', false);
	_spTrav.editId = 0;
}

async function saveTrav() {
	const date = $('#sp-trav-date')?.value;
	const dest = spExpDestParse($('#sp-trav-cat')?.value);
	const amount = parseFloat($('#sp-trav-amount')?.value);
	const description = $('#sp-trav-desc')?.value || '';
	if (!date) { alert('Please choose a date.'); return; }
	if (!(amount > 0)) { alert('Please enter an amount greater than zero.'); return; }
	if (!spHasReceipt($('#sp-trav-form-wrap'))) { alert('A receipt photo is required for every expense. Use Take Photo or Upload to attach one.'); return; }
	const btn = $('#sp-trav-save');
	if (btn) btn.disabled = true;
	const payload = { section: dest.section, category: dest.category, expense_date: date, amount, description };
	if (_spTrav.editId) payload.id = _spTrav.editId;
	Object.assign(payload, spReceiptPayload($('#sp-trav-form-wrap')));
	try {
		const r = await spSaveExpenseGuarded(payload);
		if (r.success) { if (dest.section !== 'F') spToast('Moved to ' + spExpDestLabel(dest.section, dest.category)); hideTravForm(); loadTravelExpenses(); }
		else { if (!r.cancelled) alert(r.data?.message || 'Error saving.'); if (btn) btn.disabled = false; }
	} catch (e) { alert('Error saving expense.'); if (btn) btn.disabled = false; }
}


/* ---- Business Meals (Section C) ---- */
let _spMeal = { list: [], editId: 0, start: '', end: '' };

async function initBusinessMeals() {
	const addBtn = $('#sp-meal-add-btn');
	if (addBtn && !addBtn._wired) {
		addBtn._wired = true;
		addBtn.addEventListener('click', () => showMealForm());
	}
	const panel = $('#sp-panel-business-meals');
	if (panel && !panel._filterWired) {
		panel._filterWired = true;
		await spEnsurePeriodConfig();
		const [s, e] = spBuildPeriodToolbar('meal', $('#sp-meal-toolbar-wrap'), (st, en) => {
			_spMeal.start = st; _spMeal.end = en; loadBusinessMeals();
		}, addBtn);
		_spMeal.start = s; _spMeal.end = e;
	}
	loadBusinessMeals();
}

async function loadBusinessMeals() {
	const wrap = $('#sp-meal-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_expenses', { section: 'C', start: _spMeal.start, end: _spMeal.end });
		if (!res.success) { wrap.innerHTML = '<p>Error loading meals.</p>'; return; }
		_spMeal.list = res.data.expenses || [];
		_spExpDestinations = res.data.destinations || _spExpDestinations;
		renderBusinessMeals();
		spApplyExpenseLock('meal', res.data.locked);
	} catch (e) { wrap.innerHTML = '<p>Error loading meals.</p>'; }
}

function renderBusinessMeals() {
	const wrap = $('#sp-meal-content');
	if (!wrap) return;
	const list = _spMeal.list;
	const fmt = n => '$' + mileageNumFmt(n);
	const summary = $('#sp-meal-summary');

	if (!list.length) {
		if (summary) summary.innerHTML = '';
		wrap.innerHTML = '<div class="sp-empty-state"><p>No business meals in this period. Click “+ Add Meal” to add one, or scan a receipt.</p></div>';
		return;
	}

	// One row per meal: Date | Place | Business Purpose | Attendees | Store # | Amount, with a
	// Section C total below (all of section C posts to GL 91110).
	let grand = 0;
	let html = '<div class="sp-card sp-table-card"><table class="sp-table sp-mileage-table sp-meal-table"><thead class="sp-thead"><tr>';
	html += '<th>Date</th><th>Place</th><th>Business Purpose</th><th>Attendees</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th><th></th>';
	html += '</tr></thead><tbody>';

	list.forEach(x => {
		const amt = parseFloat(x.amount) || 0;
		grand += amt;
		html += '<tr>';
		html += `<td>${esc(formatDate(x.expense_date))}</td>`;
		html += `<td>${esc(x.place || '')}</td>`;
		html += `<td>${esc(x.business_purpose || '')}</td>`;
		html += `<td>${esc(x.attendees || '')}</td>`;
		html += `<td>${esc(x.store_number || '')}</td>`;
		html += `<td class="sp-num">${fmt(amt)}</td>`;
		html += `<td class="sp-mileage-row-actions">${x.receipt_url ? receiptIconBtn('sp-meal-receipt-btn', x.id) : ''}${iconBtn('edit', 'sp-meal-edit-btn', `data-id="${x.id}"`)}${iconBtn('delete', 'sp-meal-del-btn', `data-id="${x.id}"`)}</td>`;
		html += '</tr>';
	});

	html += '</tbody></table></div>';

	// Stat cards: total + number of meals.
	if (summary) {
		const count = list.length;
		let cards = spStatCard('Total', fmt(grand), 'dollar-sign');
		cards += spStatCard('Meals', String(count), 'food');
		summary.innerHTML = `<div class="sp-stat-grid">${cards}</div>`;
	}

	wrap.innerHTML = html;

	$$('.sp-meal-edit-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spMeal.list.find(e => String(e.id) === String(b.dataset.id));
		if (x) showMealForm(x);
	}));
	$$('.sp-meal-del-btn', wrap).forEach(b => b.addEventListener('click', async () => {
		if (!confirm('Delete this meal?')) return;
		const r = await spAjax('site_pulse_delete_expense', { id: b.dataset.id });
		if (r.success) loadBusinessMeals();
		else alert(r.data?.message || 'Error deleting.');
	}));
	$$('.sp-meal-receipt-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spMeal.list.find(e => String(e.id) === String(b.dataset.id));
		if (x && x.receipt_url) spShowReceiptModal(x.receipt_url);
	}));
}

function showMealForm(x) {
	const wrap = $('#sp-meal-form-wrap');
	if (!wrap) return;
	_spMeal.editId = x ? x.id : 0;
	wrap._receiptData = null; wrap._receiptRemove = false;	wrap._mealTotal = null; wrap._mealTip = 0;   // scanned total + tip, for the Business/Personal toggle
	const mealType = (x && x.meal_type === 'personal') ? 'personal' : 'business';
	const d = new Date();
	const today = `${d.getFullYear()}-${mPad2(d.getMonth() + 1)}-${mPad2(d.getDate())}`;

	wrap.innerHTML = `
		<div class="sp-card sp-vexp-form">
			<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${x ? 'Edit' : 'Add'} Business Meal</h3></div>
			<div class="sp-card-body">
			<div class="sp-receipt-row sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">${ICON_CAMERA} Take Photo</button>
				<input type="file" accept="image/*" capture="environment" class="sp-receipt-input" hidden>
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">Upload</button>
				<input type="file" accept="image/*" class="sp-receipt-input" hidden>
				<span class="sp-receipt-status sp-help-text" hidden></span>
			</div>
			<div class="sp-vexp-fields sp-card-item">
				<div class="sp-vexp-form-grid">
					<div class="sp-form-group"><label>Date</label><input type="date" id="sp-meal-date" class="sp-input" value="${esc(x ? x.expense_date : today)}"></div>
					<div class="sp-form-group"><label>Place</label><input type="text" id="sp-meal-place" class="sp-input" value="${esc(x ? (x.place || '') : '')}" placeholder="Restaurant / venue"></div>
					<div class="sp-form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" id="sp-meal-amount" class="sp-input" value="${x ? (parseFloat(x.amount) || 0).toFixed(2) : ''}"></div>
				</div>
				<div class="sp-form-group"><label>Category</label><select id="sp-meal-move" class="sp-select">${spExpDestOptions(x ? x.section : 'C', x ? x.category : 'meals')}</select></div>
				<div class="sp-form-group"><label>Meal Type</label>
						<div class="sp-radio-row">
							<label class="sp-radio-inline"><input type="radio" name="sp-meal-type" class="sp-meal-type-radio" value="business"${mealType === 'personal' ? '' : ' checked'}> Business</label>
							<label class="sp-radio-inline"><input type="radio" name="sp-meal-type" class="sp-meal-type-radio" value="personal"${mealType === 'personal' ? ' checked' : ''}> Personal</label>
						</div>
						<span class="sp-help-text">Personal (family) meals: the tip is removed from the reimbursed total — tips aren&rsquo;t reimbursed.</span>
					</div>
					<div class="sp-form-group"><label>Purpose</label><input type="text" id="sp-meal-purpose" class="sp-input" value="${esc(x ? (x.business_purpose || '') : '')}" placeholder="e.g. Vendor meeting, candidate interview"></div>
				<div class="sp-form-group"><label>Attendees</label><input type="text" id="sp-meal-attendees" class="sp-input" value="${esc(x ? (x.attendees || '') : '')}" placeholder="Who attended"></div>
				<div class="sp-form-group"><label>Charge To</label><select id="sp-meal-store" class="sp-select">${mileageChargeOptionsHtml((x && x.store_number) ? x.store_number : '99')}</select></div>
			</div>
			<div class="sp-vexp-form-actions sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-meal-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-meal-cancel">Cancel</button>
			</div>
			</div>
		</div>`;
	wrap.hidden = false;
	setExpenseChrome('meal', true);
	markUniqueSpans(wrap);

	// Set the Amount from the scanned total, minus the tip when this is a Personal meal (tips aren't
	// reimbursed). Only runs when a receipt was scanned this session (wrap._mealTotal set).
	const applyMealAmount = () => {
		if (wrap._mealTotal == null) return;
		const personal = wrap.querySelector('.sp-meal-type-radio[value="personal"]')?.checked;
		const amt = personal ? Math.max(0, wrap._mealTotal - (wrap._mealTip || 0)) : wrap._mealTotal;
		const el = $('#sp-meal-amount', wrap);
		if (el) el.value = amt.toFixed(2);
	};
	// Toggling Business/Personal recomputes the amount from the scanned total + tip.
	$$('.sp-meal-type-radio', wrap).forEach(rb => rb.addEventListener('change', applyMealAmount));

	// Receipt scan → fills Date, Place, and the Amount (tip stashed so Personal can drop it). Purpose
	// + attendees stay manual.
	spWireReceiptScan(wrap, 'C', (r) => {
		if (r.date) { const el = $('#sp-meal-date', wrap); if (el) el.value = r.date; }
		if (r.amount > 0) { wrap._mealTotal = parseFloat(r.amount) || 0; wrap._mealTip = parseFloat(r.tip) || 0; applyMealAmount(); }
		if (r.place) { const el = $('#sp-meal-place', wrap); if (el && !el.value) el.value = r.place; }
	});

	if (x && x.receipt_url) spShowReceiptThumb(wrap, x.receipt_url);	$('#sp-meal-cancel', wrap).addEventListener('click', hideMealForm);
	$('#sp-meal-save', wrap).addEventListener('click', saveMeal);
	// No auto-focus on the date (see showVexpForm): it pops the iOS calendar over the photo buttons.
}

function hideMealForm() {
	const wrap = $('#sp-meal-form-wrap');
	if (wrap) { wrap.hidden = true; wrap.innerHTML = ''; }
	setExpenseChrome('meal', false);
	_spMeal.editId = 0;
}

async function saveMeal() {
	const date = $('#sp-meal-date')?.value;
	const place = $('#sp-meal-place')?.value || '';
	const amount = parseFloat($('#sp-meal-amount')?.value);
	const business_purpose = $('#sp-meal-purpose')?.value || '';
	const attendees = $('#sp-meal-attendees')?.value || '';
	const store_number = $('#sp-meal-store')?.value || '';
	const dest = spExpDestParse($('#sp-meal-move')?.value);
	const meal_type = $('#sp-meal-form-wrap')?.querySelector('.sp-meal-type-radio[value="personal"]')?.checked ? 'personal' : 'business';
	if (!date) { alert('Please choose a date.'); return; }
	if (!(amount > 0)) { alert('Please enter an amount greater than zero.'); return; }
	if (!spHasReceipt($('#sp-meal-form-wrap'))) { alert('A receipt photo is required for every expense. Use Take Photo or Upload to attach one.'); return; }
	const btn = $('#sp-meal-save');
	if (btn) btn.disabled = true;
	const payload = { section: dest.section, category: dest.category, expense_date: date, amount, place, business_purpose, attendees, store_number, meal_type };
	if (_spMeal.editId) payload.id = _spMeal.editId;
	Object.assign(payload, spReceiptPayload($('#sp-meal-form-wrap')));
	try {
		const r = await spSaveExpenseGuarded(payload);
		if (r.success) { if (dest.section !== 'C') spToast('Moved to ' + spExpDestLabel(dest.section, dest.category)); hideMealForm(); loadBusinessMeals(); }
		else { if (!r.cancelled) alert(r.data?.message || 'Error saving.'); if (btn) btn.disabled = false; }
	} catch (e) { alert('Error saving meal.'); if (btn) btn.disabled = false; }
}


/* ---- Competitive Shopping (Section D) ---- */
// Like Business Meals but with no Attendees column; everything posts to GL 81095 (R&D-Food).
let _spShop = { list: [], editId: 0, start: '', end: '' };

async function initCompetitiveShopping() {
	const addBtn = $('#sp-shop-add-btn');
	if (addBtn && !addBtn._wired) {
		addBtn._wired = true;
		addBtn.addEventListener('click', () => showShopForm());
	}
	const panel = $('#sp-panel-competitive-shopping');
	if (panel && !panel._filterWired) {
		panel._filterWired = true;
		await spEnsurePeriodConfig();
		const [s, e] = spBuildPeriodToolbar('shop', $('#sp-shop-toolbar-wrap'), (st, en) => {
			_spShop.start = st; _spShop.end = en; loadCompetitiveShopping();
		}, addBtn);
		_spShop.start = s; _spShop.end = e;
	}
	loadCompetitiveShopping();
}

async function loadCompetitiveShopping() {
	const wrap = $('#sp-shop-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_expenses', { section: 'D', start: _spShop.start, end: _spShop.end });
		if (!res.success) { wrap.innerHTML = '<p>Error loading visits.</p>'; return; }
		_spShop.list = res.data.expenses || [];
		_spExpDestinations = res.data.destinations || _spExpDestinations;
		renderCompetitiveShopping();
		spApplyExpenseLock('shop', res.data.locked);
	} catch (e) { wrap.innerHTML = '<p>Error loading visits.</p>'; }
}

function renderCompetitiveShopping() {
	const wrap = $('#sp-shop-content');
	if (!wrap) return;
	const list = _spShop.list;
	const fmt = n => '$' + mileageNumFmt(n);
	const summary = $('#sp-shop-summary');

	if (!list.length) {
		if (summary) summary.innerHTML = '';
		wrap.innerHTML = '<div class="sp-empty-state"><p>No competitive shopping in this period. Click “+ Add Visit” to add one, or scan a receipt.</p></div>';
		return;
	}

	// One row per visit: Date | Place | Business Purpose | Store # | Amount, with a Section D
	// total below (all of section D posts to GL 81095, R&D-Food).
	let grand = 0;
	let html = '<div class="sp-card sp-table-card"><table class="sp-table sp-mileage-table sp-shop-table"><thead class="sp-thead"><tr>';
	html += '<th>Date</th><th>Place</th><th>Business Purpose</th><th>Attendees</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th><th></th>';
	html += '</tr></thead><tbody>';

	list.forEach(x => {
		const amt = parseFloat(x.amount) || 0;
		grand += amt;
		html += '<tr>';
		html += `<td>${esc(formatDate(x.expense_date))}</td>`;
		html += `<td>${esc(x.place || '')}</td>`;
		html += `<td>${esc(x.business_purpose || '')}</td>`;
		html += `<td>${esc(x.attendees || '')}</td>`;
		html += `<td>${esc(x.store_number || '')}</td>`;
		html += `<td class="sp-num">${fmt(amt)}</td>`;
		html += `<td class="sp-mileage-row-actions">${x.receipt_url ? receiptIconBtn('sp-shop-receipt-btn', x.id) : ''}${iconBtn('edit', 'sp-shop-edit-btn', `data-id="${x.id}"`)}${iconBtn('delete', 'sp-shop-del-btn', `data-id="${x.id}"`)}</td>`;
		html += '</tr>';
	});

	html += '</tbody></table></div>';

	// Stat cards: total + number of visits.
	if (summary) {
		const count = list.length;
		let cards = spStatCard('Total', fmt(grand), 'dollar-sign');
		cards += spStatCard('Visits', String(count), 'store');
		summary.innerHTML = `<div class="sp-stat-grid">${cards}</div>`;
	}

	wrap.innerHTML = html;

	$$('.sp-shop-edit-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spShop.list.find(e => String(e.id) === String(b.dataset.id));
		if (x) showShopForm(x);
	}));
	$$('.sp-shop-del-btn', wrap).forEach(b => b.addEventListener('click', async () => {
		if (!confirm('Delete this visit?')) return;
		const r = await spAjax('site_pulse_delete_expense', { id: b.dataset.id });
		if (r.success) loadCompetitiveShopping();
		else alert(r.data?.message || 'Error deleting.');
	}));
	$$('.sp-shop-receipt-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spShop.list.find(e => String(e.id) === String(b.dataset.id));
		if (x && x.receipt_url) spShowReceiptModal(x.receipt_url);
	}));
}

function showShopForm(x) {
	const wrap = $('#sp-shop-form-wrap');
	if (!wrap) return;
	_spShop.editId = x ? x.id : 0;
	wrap._receiptData = null; wrap._receiptRemove = false;	const d = new Date();
	const today = `${d.getFullYear()}-${mPad2(d.getMonth() + 1)}-${mPad2(d.getDate())}`;

	wrap.innerHTML = `
		<div class="sp-card sp-vexp-form">
			<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${x ? 'Edit' : 'Add'} Competitive Shopping Visit</h3></div>
			<div class="sp-card-body">
			<div class="sp-receipt-row sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">${ICON_CAMERA} Take Photo</button>
				<input type="file" accept="image/*" capture="environment" class="sp-receipt-input" hidden>
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">Upload</button>
				<input type="file" accept="image/*" class="sp-receipt-input" hidden>
				<span class="sp-receipt-status sp-help-text" hidden></span>
			</div>
			<div class="sp-vexp-fields sp-card-item">
				<div class="sp-vexp-form-grid">
					<div class="sp-form-group"><label>Date</label><input type="date" id="sp-shop-date" class="sp-input" value="${esc(x ? x.expense_date : today)}"></div>
					<div class="sp-form-group"><label>Place</label><input type="text" id="sp-shop-place" class="sp-input" value="${esc(x ? (x.place || '') : '')}" placeholder="Restaurant / place shopped"></div>
					<div class="sp-form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" id="sp-shop-amount" class="sp-input" value="${x ? (parseFloat(x.amount) || 0).toFixed(2) : ''}"></div>
				</div>
				<div class="sp-form-group"><label>Category</label><select id="sp-shop-move" class="sp-select">${spExpDestOptions(x ? x.section : 'D', x ? x.category : 'shopping')}</select></div>
				<div class="sp-form-group"><label>Business Purpose</label><input type="text" id="sp-shop-purpose" class="sp-input" value="${esc(x ? (x.business_purpose || '') : '')}" placeholder="e.g. Menu/pricing research"></div>
					<div class="sp-form-group"><label>Attendees</label><input type="text" id="sp-shop-attendees" class="sp-input" value="${esc(x ? (x.attendees || '') : '')}" placeholder="Who went (optional)"></div>
				<div class="sp-form-group"><label>Charge To</label><select id="sp-shop-store" class="sp-select">${mileageChargeOptionsHtml((x && x.store_number) ? x.store_number : '99')}</select></div>
			</div>
			<div class="sp-vexp-form-actions sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-shop-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-shop-cancel">Cancel</button>
			</div>
			</div>
		</div>`;
	wrap.hidden = false;
	setExpenseChrome('shop', true);
	markUniqueSpans(wrap);

	// Receipt scan → fills Date, Amount and Place. Business purpose stays manual.
	spWireReceiptScan(wrap, 'D', (r) => {
		if (r.date) { const el = $('#sp-shop-date', wrap); if (el) el.value = r.date; }
		if (r.amount > 0) { const el = $('#sp-shop-amount', wrap); if (el) el.value = (parseFloat(r.amount) || 0).toFixed(2); }
		if (r.place) { const el = $('#sp-shop-place', wrap); if (el && !el.value) el.value = r.place; }
	});

	if (x && x.receipt_url) spShowReceiptThumb(wrap, x.receipt_url);	$('#sp-shop-cancel', wrap).addEventListener('click', hideShopForm);
	$('#sp-shop-save', wrap).addEventListener('click', saveShop);
	// No auto-focus on the date (see showVexpForm): it pops the iOS calendar over the photo buttons.
}

function hideShopForm() {
	const wrap = $('#sp-shop-form-wrap');
	if (wrap) { wrap.hidden = true; wrap.innerHTML = ''; }
	setExpenseChrome('shop', false);
	_spShop.editId = 0;
}

async function saveShop() {
	const date = $('#sp-shop-date')?.value;
	const place = $('#sp-shop-place')?.value || '';
	const amount = parseFloat($('#sp-shop-amount')?.value);
	const business_purpose = $('#sp-shop-purpose')?.value || '';
	const attendees = $('#sp-shop-attendees')?.value || '';
	const store_number = $('#sp-shop-store')?.value || '';
	const dest = spExpDestParse($('#sp-shop-move')?.value);
	if (!date) { alert('Please choose a date.'); return; }
	if (!(amount > 0)) { alert('Please enter an amount greater than zero.'); return; }
	if (!spHasReceipt($('#sp-shop-form-wrap'))) { alert('A receipt photo is required for every expense. Use Take Photo or Upload to attach one.'); return; }
	const btn = $('#sp-shop-save');
	if (btn) btn.disabled = true;
	const payload = { section: dest.section, category: dest.category, expense_date: date, amount, place, business_purpose, attendees, store_number };
	if (_spShop.editId) payload.id = _spShop.editId;
	Object.assign(payload, spReceiptPayload($('#sp-shop-form-wrap')));
	try {
		const r = await spSaveExpenseGuarded(payload);
		if (r.success) { if (dest.section !== 'D') spToast('Moved to ' + spExpDestLabel(dest.section, dest.category)); hideShopForm(); loadCompetitiveShopping(); }
		else { if (!r.cancelled) alert(r.data?.message || 'Error saving.'); if (btn) btn.disabled = false; }
	} catch (e) { alert('Error saving visit.'); if (btn) btn.disabled = false; }
}


/* ---- Other Expenses (Section E) ---- */
// Catch-all section: Date | Description | Account | Store # | Amount. The GL account is entered
// per line (free text) — section E has no fixed category list, so each row carries its own.
let _spOexp = { list: [], editId: 0, start: '', end: '', accounts: [], recurring: [] };

async function initOtherExpenses() {
	const addBtn = $('#sp-oexp-add-btn');
	if (addBtn && !addBtn._wired) {
		addBtn._wired = true;
		addBtn.addEventListener('click', () => showOexpForm());
	}
	const panel = $('#sp-panel-other-expenses');
	if (panel && !panel._filterWired) {
		panel._filterWired = true;
		await spEnsurePeriodConfig();
		const [s, e] = spBuildPeriodToolbar('oexp', $('#sp-oexp-toolbar-wrap'), (st, en) => {
			_spOexp.start = st; _spOexp.end = en; loadOtherExpenses();
		}, addBtn);
		_spOexp.start = s; _spOexp.end = e;
	}
	loadOtherExpenses();
}

async function loadOtherExpenses() {
	const wrap = $('#sp-oexp-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	try {
		const res = await spAjax('site_pulse_get_expenses', { section: 'E', start: _spOexp.start, end: _spOexp.end });
		if (!res.success) { wrap.innerHTML = '<p>Error loading expenses.</p>'; return; }
		_spOexp.list = res.data.expenses || [];
		_spOexp.accounts = res.data.accounts || [];
		_spOexp.recurring = res.data.recurring || [];
		_spExpDestinations = res.data.destinations || _spExpDestinations;
		renderOtherExpenses();
		spApplyExpenseLock('oexp', res.data.locked);
	} catch (e) { wrap.innerHTML = '<p>Error loading expenses.</p>'; }
}

// The Recurring strip: templates the driver taps to drop a pre-filled line into this period. Shows
// "Added" when a matching line already exists this period (by account + description). "×" stops it.
function spOexpRecurringStrip() {
	const tpls = _spOexp.recurring || [];
	if (!tpls.length) return '';
	const fmt = n => '$' + mileageNumFmt(n);
	const norm = s => String(s || '').toLowerCase().trim();
	const chips = tpls.map(t => {
		const added = (_spOexp.list || []).some(e => norm(e.account_code) === norm(t.account_code) && norm(e.description) === norm(t.description));
		const label = `${esc(t.description || t.account_code || 'Recurring')} · ${fmt(t.amount)}`;
		const action = added
			? '<span class="sp-recurring-chip-done">Added ✓</span>'
			: `<button type="button" class="unique sp-btn sp-btn-secondary sp-btn-tiny sp-recurring-add" data-key="${esc(t.key)}">Add</button>`;
		return `<div class="sp-recurring-chip${added ? ' sp-recurring-chip-added' : ''}">`
			+ `<span class="sp-recurring-chip-label">${label}</span>${action}`
			+ `<button type="button" class="unique sp-recurring-stop" data-key="${esc(t.key)}" title="Stop recurring" aria-label="Stop recurring">×</button>`
			+ '</div>';
	}).join('');
	return `<div class="sp-recurring-strip"><div class="sp-recurring-strip-title">Recurring</div><div class="sp-recurring-chips">${chips}</div></div>`;
}

function spWireOexpRecurring(wrap) {
	$$('.sp-recurring-add', wrap).forEach(b => b.addEventListener('click', () => {
		const t = (_spOexp.recurring || []).find(r => r.key === b.dataset.key);
		if (t) showOexpForm({ account_code: t.account_code, description: t.description, store_number: t.store_number, amount: t.amount });
	}));
	$$('.sp-recurring-stop', wrap).forEach(b => b.addEventListener('click', async () => {
		if (!confirm('Stop this from recurring? Any lines already entered stay — it just won’t show here anymore.')) return;
		const r = await spAjax('site_pulse_delete_recurring', { key: b.dataset.key });
		if (r.success) { _spOexp.recurring = r.data.recurring || []; renderOtherExpenses(); }
	}));
}

function renderOtherExpenses() {
	const wrap = $('#sp-oexp-content');
	if (!wrap) return;
	const list = _spOexp.list;
	const fmt = n => '$' + mileageNumFmt(n);
	const summary = $('#sp-oexp-summary');
	const strip = spOexpRecurringStrip();

	if (!list.length) {
		if (summary) summary.innerHTML = '';
		wrap.innerHTML = strip + '<div class="sp-empty-state"><p>No other expenses in this period. Click “+ Add Expense” to add one, or tap a recurring item above.</p></div>';
		spWireOexpRecurring(wrap);
		return;
	}

	// One row per expense: Date | Description | Account | Store # | Amount, with a Section E total
	// below. Each row carries its own GL account (no fixed category for section E).
	let grand = 0;
	let html = strip;
	html += '<div class="sp-card sp-table-card"><table class="sp-table sp-mileage-table sp-oexp-table"><thead class="sp-thead"><tr>';
	html += '<th>Date</th><th>Description</th><th>Account</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th><th></th>';
	html += '</tr></thead><tbody>';

	list.forEach(x => {
		const amt = parseFloat(x.amount) || 0;
		grand += amt;
		html += '<tr>';
		html += `<td>${esc(formatDate(x.expense_date))}</td>`;
		html += `<td>${esc(x.description || '')}</td>`;
		html += `<td>${esc(x.account_code || '')}</td>`;
		html += `<td>${esc(x.store_number || '')}</td>`;
		html += `<td class="sp-num">${fmt(amt)}</td>`;
		html += `<td class="sp-mileage-row-actions">${x.receipt_url ? receiptIconBtn('sp-oexp-receipt-btn', x.id) : ''}${iconBtn('edit', 'sp-oexp-edit-btn', `data-id="${x.id}"`)}${iconBtn('delete', 'sp-oexp-del-btn', `data-id="${x.id}"`)}</td>`;
		html += '</tr>';
	});

	html += '</tbody></table></div>';

	// Stat cards: total + number of items.
	if (summary) {
		const count = list.length;
		let cards = spStatCard('Total', fmt(grand), 'dollar-sign');
		cards += spStatCard('Items', String(count), 'items');
		summary.innerHTML = `<div class="sp-stat-grid">${cards}</div>`;
	}

	wrap.innerHTML = html;
	spWireOexpRecurring(wrap);

	$$('.sp-oexp-edit-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spOexp.list.find(e => String(e.id) === String(b.dataset.id));
		if (x) showOexpForm(x);
	}));
	$$('.sp-oexp-del-btn', wrap).forEach(b => b.addEventListener('click', async () => {
		if (!confirm('Delete this expense?')) return;
		const r = await spAjax('site_pulse_delete_expense', { id: b.dataset.id });
		if (r.success) loadOtherExpenses();
		else alert(r.data?.message || 'Error deleting.');
	}));
	$$('.sp-oexp-receipt-btn', wrap).forEach(b => b.addEventListener('click', () => {
		const x = _spOexp.list.find(e => String(e.id) === String(b.dataset.id));
		if (x && x.receipt_url) spShowReceiptModal(x.receipt_url);
	}));
}

// Options for the Other Expenses "Account" dropdown, fed by the admin-managed chart of accounts
// (Settings → Expense Reports). Value = the GL number; label = "91200 Office Supplies". A saved
// account that's since left the chart (legacy row) stays selectable.
function spOexpAccountOptions(selected) {
	const v = selected == null ? '' : String(selected);
	let o = `<option value=""${v === '' ? ' selected' : ''}>Choose an account…</option>`;
	(_spOexp.accounts || []).forEach(a => {
		const num = String(a.number || '');
		if (!num) return;
		const lbl = a.description ? `${num} ${a.description}` : num;
		o += `<option value="${esc(num)}"${v === num ? ' selected' : ''}>${esc(lbl)}</option>`;
	});
	if (v && !(_spOexp.accounts || []).some(a => String(a.number) === v)) o += `<option value="${esc(v)}" selected>${esc(v)}</option>`;
	return o;
}

function showOexpForm(x) {
	const wrap = $('#sp-oexp-form-wrap');
	if (!wrap) return;
	// x may be an existing expense (has .id → Edit) OR a recurring-template PRE-FILL (no .id → Add,
	// fields seeded from the template). Guard on x.id, not just x, so a prefill opens as a new expense.
	const isEdit = !!(x && x.id);
	_spOexp.editId = isEdit ? x.id : 0;
	wrap._receiptData = null; wrap._receiptRemove = false;	const d = new Date();
	const today = `${d.getFullYear()}-${mPad2(d.getMonth() + 1)}-${mPad2(d.getDate())}`;

	wrap.innerHTML = `
		<div class="sp-card sp-vexp-form">
			<div class="sp-card-header sp-header-grid sp-report-form-header"><h3>${isEdit ? 'Edit' : 'Add'} Other Expense</h3></div>
			<div class="sp-card-body">
			<div class="sp-receipt-row sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">${ICON_CAMERA} Take Photo</button>
				<input type="file" accept="image/*" capture="environment" class="sp-receipt-input" hidden>
				<button type="button" class="unique sp-btn sp-btn-secondary sp-receipt-btn">Upload</button>
				<input type="file" accept="image/*" class="sp-receipt-input" hidden>
				<span class="sp-receipt-status sp-help-text" hidden></span>
			</div>
			<div class="sp-vexp-fields sp-card-item">
				<div class="sp-vexp-form-grid">
					<div class="sp-form-group"><label>Date</label><input type="date" id="sp-oexp-date" class="sp-input" value="${esc(x && x.expense_date ? x.expense_date : today)}"></div>
					<div class="sp-form-group"><label>Account</label><select id="sp-oexp-account" class="sp-select">${spOexpAccountOptions(x ? (x.account_code || '') : '')}</select></div>
					<div class="sp-form-group"><label>Amount ($)</label><input type="number" step="0.01" min="0" id="sp-oexp-amount" class="sp-input" value="${x ? (parseFloat(x.amount) || 0).toFixed(2) : ''}"></div>
				</div>
				<div class="sp-form-group"><label>Category</label><select id="sp-oexp-move" class="sp-select">${spExpDestOptions(x ? x.section : 'E', x ? x.category : '')}</select></div>
				<div class="sp-form-group"><label>Description</label><input type="text" id="sp-oexp-desc" class="sp-input" value="${esc(x ? (x.description || '') : '')}" placeholder="e.g. Postage — overnight to vendor"></div>
				<div class="sp-form-group"><label>Charge To</label><select id="sp-oexp-store" class="sp-select">${mileageChargeOptionsHtml((x && x.store_number) ? x.store_number : '99')}</select></div>
					<div class="sp-form-group sp-recurring-group">
						<label for="sp-oexp-recurring">Recurring expense</label><input type="checkbox" id="sp-oexp-recurring" class="sp-recurring-check">
					</div>
			</div>
			<div class="sp-vexp-form-actions sp-card-item">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-oexp-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-oexp-cancel">Cancel</button>
			</div>
			</div>
		</div>`;
	wrap.hidden = false;
	setExpenseChrome('oexp', true);
	markUniqueSpans(wrap);

	// Receipt scan → fills Date, Amount and Description. The GL account stays manual.
	spWireReceiptScan(wrap, 'E', (r) => {
		if (r.date) { const el = $('#sp-oexp-date', wrap); if (el) el.value = r.date; }
		if (r.amount > 0) { const el = $('#sp-oexp-amount', wrap); if (el) el.value = (parseFloat(r.amount) || 0).toFixed(2); }
		if (r.description) { const el = $('#sp-oexp-desc', wrap); if (el && !el.value) el.value = r.description; }
	});

	if (x && x.receipt_url) spShowReceiptThumb(wrap, x.receipt_url);	$('#sp-oexp-cancel', wrap).addEventListener('click', hideOexpForm);
	$('#sp-oexp-save', wrap).addEventListener('click', saveOexp);
	// No auto-focus on the date (see showVexpForm): it pops the iOS calendar over the photo buttons.
}

function hideOexpForm() {
	const wrap = $('#sp-oexp-form-wrap');
	if (wrap) { wrap.hidden = true; wrap.innerHTML = ''; }
	setExpenseChrome('oexp', false);
	_spOexp.editId = 0;
}

async function saveOexp() {
	const date = $('#sp-oexp-date')?.value;
	const account_code = $('#sp-oexp-account')?.value || '';
	const amount = parseFloat($('#sp-oexp-amount')?.value);
	const description = $('#sp-oexp-desc')?.value || '';
	const store_number = $('#sp-oexp-store')?.value || '';
	const dest = spExpDestParse($('#sp-oexp-move')?.value);
	if (!date) { alert('Please choose a date.'); return; }
	if (!(amount > 0)) { alert('Please enter an amount greater than zero.'); return; }
	if (!spHasReceipt($('#sp-oexp-form-wrap'))) { alert('A receipt photo is required for every expense. Use Take Photo or Upload to attach one.'); return; }
	const btn = $('#sp-oexp-save');
	if (btn) btn.disabled = true;
	const makeRecurring = !!$('#sp-oexp-recurring')?.checked;
	const payload = { section: dest.section, category: dest.category, expense_date: date, amount, description, account_code, store_number };
	if (_spOexp.editId) payload.id = _spOexp.editId;
	Object.assign(payload, spReceiptPayload($('#sp-oexp-form-wrap')));
	try {
		const r = await spSaveExpenseGuarded(payload);
		if (r.success) {
			// If "Make recurring" is ticked, remember this line as a template (Other Expenses only).
			if (makeRecurring && dest.section === 'E') {
				try { await spAjax('site_pulse_save_recurring', { account_code, description, store_number, amount }); } catch (e) {}
			}
			if (dest.section !== 'E') spToast('Moved to ' + spExpDestLabel(dest.section, dest.category));
			hideOexpForm(); loadOtherExpenses();
		}
		else { if (!r.cancelled) alert(r.data?.message || 'Error saving.'); if (btn) btn.disabled = false; }
	} catch (e) { alert('Error saving expense.'); if (btn) btn.disabled = false; }
}

/* ---- Expense Report (cover page that composes Sections A–F into one report) ---- */
let _spRep = { start: '', end: '' };

async function initExpenseReport() {
	const panel = $('#sp-panel-expense-report');
	if (panel && !panel._filterWired) {
		panel._filterWired = true;
		await spEnsurePeriodConfig();
		const [s, e] = spBuildPeriodToolbar('rep', $('#sp-rep-toolbar-wrap'), (st, en) => {
			_spRep.start = st; _spRep.end = en; loadExpenseReport();
		});
		_spRep.start = s; _spRep.end = e;

		// PDF action + the Submit-for-Approval control on the toolbar's right edge (the submit slot is
		// filled per period by renderExpenseReport, since its state depends on the loaded report).
		const actions = $('#sp-rep-toolbar-actions');
		if (actions) {
			actions.innerHTML =
				'<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-rep-pdf-btn">PDF</button>' +
				'<span id="sp-rep-submit-slot" class="sp-rep-submit-slot"></span>';
			markUniqueSpans(actions);
			$('#sp-rep-pdf-btn', actions).addEventListener('click', () => exportMileagePDF(_spRep.start, _spRep.end));
		}
	}
	loadExpenseReport();
}

async function loadExpenseReport() {
	const wrap = $('#sp-rep-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	const data = await fetchMileageReport(_spRep.start, _spRep.end);
	if (!data) { wrap.innerHTML = '<div class="sp-empty-state"><p>Could not load the report.</p></div>'; return; }
	renderExpenseReport(data);
}

// Build the Summary of Expenses in SECTION order (A, B, C, D, E, F). Each section emits one row per
// distinct GL account its lines post to — so a section that spans two accounts (e.g. Vehicle →
// 91300 and 91310) shows two rows, each with its account + amount. Accounts come from the chart, so
// the labels reflect whatever the business configured. Shared by the on-screen overview AND the PDF.
// Returns { rows: [{ desc, account, account_label, amount }], total }.
function spBuildAccountSummary(data) {
	const rate = parseFloat(data.rate) || 0;
	const entries = data.entries || [];
	const num = n => parseFloat(n) || 0;
	const accounts = data.accounts || [];
	const ma = Object.assign({ reimbursement: '91310', tolls: '91310', trailer: '91310' }, data.mileage_accounts || {});
	const acctLabel = (n) => {
		if (!n) return '';
		const hit = accounts.find(a => String(a.number) === String(n));
		return hit && hit.description ? `${hit.number} ${hit.description}` : String(n);
	};

	const rows = [];
	// Emit a section's account groups (sorted by account number) as summary rows.
	const emit = (desc, map) => {
		Object.keys(map)
			.filter(k => Math.abs(map[k]) > 0.0001)
			.sort((a, b) => String(a).localeCompare(String(b), undefined, { numeric: true }))
			.forEach(acct => rows.push({ desc, account: acct, account_label: acctLabel(acct), amount: map[acct] }));
	};
	const groupRows = (arr) => {
		const m = {};
		(arr || []).forEach(x => { const amt = num(x.amount); if (!amt) return; const k = String(x.account_code || ''); m[k] = (m[k] || 0) + amt; });
		return m;
	};

	// A — Mileage: reimbursement + tolls + trailer, each to its configured account.
	const aMap = {};
	const addA = (acct, amt) => { if (!amt) return; const k = String(acct || ''); aMap[k] = (aMap[k] || 0) + amt; };
	const totalMiles = entries.reduce((s, e) => s + num(e.total_miles), 0);
	addA(ma.reimbursement, rate > 0 ? Math.round(totalMiles * rate * 100) / 100 : 0);
	addA(ma.tolls, entries.reduce((s, e) => s + num(e.total_tolls), 0));
	addA(ma.trailer, entries.reduce((s, e) => s + num(e.total_trailer), 0));
	emit('A - Mileage', aMap);

	emit('B - Vehicle Expenses', groupRows(data.vehicle_expenses));
	emit('C - Business Meals', groupRows(data.business_meals));
	emit('D - Competitive Shopping', groupRows(data.competitive_shopping));
	emit('E - Other Expenses', groupRows(data.other_expenses));
	emit('F - Travel Expenses', groupRows(data.travel_expenses));

	return { rows, total: rows.reduce((s, r) => s + r.amount, 0) };
}

function renderExpenseReport(data, opts) {
	opts = opts || {};
	const readonly = !!opts.readonly;
	const wrap = opts.target ? (typeof opts.target === 'string' ? $(opts.target) : opts.target) : $('#sp-rep-content');
	if (!wrap) return;
	if (!opts.target) _spRep.lastData = data; // stash for the Submit action (own Overview only)
	const money = n => '$' + mileageNumFmt(n);
	const sum = (arr, f) => arr.reduce((s, x) => s + (parseFloat(f(x)) || 0), 0);

	const rate = parseFloat(data.rate) || 0;
	const entries = data.entries || [];
	const vehicle = data.vehicle_expenses || [];
	const vcats = data.vehicle_categories || {};
	const meals = data.business_meals || [];
	const shopping = data.competitive_shopping || [];
	const other = data.other_expenses || [];
	const travel = data.travel_expenses || [];
	const tcats = data.travel_categories || {};

	// --- Section totals (mirrors the form's Summary of Expenses) ---
	const totalMiles = sum(entries, e => e.total_miles);
	const mileageReimb = rate > 0 ? Math.round(totalMiles * rate * 100) / 100 : 0;
	const mileageTolls = sum(entries, e => e.total_tolls);
	const mileageTrailer = sum(entries, e => e.total_trailer);

	// Per-section totals still feed the detail-table footers below.
	const cTotal = sum(meals, x => x.amount);
	const dTotal = sum(shopping, x => x.amount);
	const eTotal = sum(other, x => x.amount);
	const fTotal = sum(travel, x => x.amount);

	// Summary of Expenses is built dynamically by GL account (mileage + every B–F line), so it
	// reflects whatever chart of accounts / categories the business has configured.
	const summary = spBuildAccountSummary(data);
	const totalDue = summary.total;

	const hasAny = entries.length || vehicle.length || meals.length || shopping.length || other.length || travel.length;

	let html = '';

	// --- Report header (Payee / Period / Week Ending / Home Base) ---
	const headField = (l, v) => `<div class="sp-tile sp-rep-field"><div class="sp-rep-text"><div class="sp-tile-label sp-rep-field-label">${l}</div><div class="sp-tile-value sp-rep-field-value">${esc(v || '—')}</div></div></div>`;
	html += '<div class="sp-rep-head">';
	html += `<div class="sp-rep-head-title">${esc(data.company_name || data.app_name || 'Expense Report')}</div>`;
	html += '<div class="sp-rep-head-grid">';
	html += headField('Payee', data.user_name);
	html += headField('Period', data.label);
	html += headField('Week Ending', data.end ? formatDate(data.end) : '—');
	if (data.home_label) html += headField('Home Base', data.home_label);
	html += '</div></div>';

	if (!hasAny) {
		html += '<div class="sp-empty-state"><p>No expenses in this period.</p></div>';
		wrap.innerHTML = html;
		markUniqueSpans(wrap);
		return;
	}

	// --- Summary of Expenses — section order A–F, one row per account each section posts to ---
	html += '<h3 class="sp-rep-section-title">Summary of Expenses</h3>';
	html += '<div class="sp-card sp-table-card"><table class="sp-table sp-rep-summary"><thead class="sp-thead"><tr><th>Description</th><th class="sp-num">Amount</th><th>Account</th></tr></thead><tbody>';
	summary.rows.forEach(r => {
		html += `<tr><td>${esc(r.desc)}</td><td class="sp-num">${money(r.amount)}</td><td>${esc(r.account_label || r.account)}</td></tr>`;
	});
	html += `<tr class="sp-rep-total"><td>Total Due to Employee</td><td class="sp-num">${money(summary.total)}</td><td></td></tr>`;
	html += '</tbody></table></div>';

	// --- Section detail tables (only sections that have rows; mirrors the PDF body) ---
	// A — Mileage: built EXACTLY like the on-screen Mileage screen — the `sp-mileage-table` class
	// and one <tbody class="sp-mileage-day"> per day — so the global mileage CSS controls the look.
	if (entries.length) {
		const hasToll = mileageTolls > 0, hasTrl = mileageTrailer > 0;
		html += '<h3 class="sp-rep-section-title">A — Mileage</h3>';
		html += '<div class="sp-card sp-table-card"><table class="sp-table sp-mileage-table"><thead class="sp-thead"><tr>'
			+ '<th>Date</th><th class="sp-mileage-charge">Charge&nbsp;To</th><th>Route</th><th>Purpose</th>'
			+ '<th class="sp-th-num">Miles</th><th class="sp-th-num">$</th>'
			+ (hasTrl ? '<th class="sp-th-num">Trailer</th>' : '')
			+ (hasToll ? '<th class="sp-th-num">Tolls</th>' : '') + '</tr></thead>';
		entries.forEach(e => {
			const stops = e.route_stops || [];
			const dayMiles = parseFloat(e.total_miles) || 0;
			html += '<tbody class="sp-mileage-day">';
			// One row per leg: stops[i-1] → stops[i], with that destination stop's charge/purpose/miles/toll.
			for (let i = 1; i < stops.length; i++) {
				const from = stops[i - 1], to = stops[i];
				const miles = to.miles == null ? null : parseFloat(to.miles) || 0;
				const dollar = miles == null ? '—' : money(rate > 0 ? miles * rate : 0);
				const tc = to.toll == null ? 0 : parseFloat(to.toll) || 0;
				html += '<tr class="sp-mileage-leg">';
				html += `<td class="sp-m-date">${i === 1 ? esc(formatDate(e.entry_date)) : ''}</td>`;
				html += `<td class="sp-mileage-charge">${esc(to.charge_to || '')}</td>`;
				html += `<td class="sp-mileage-leg-route">${esc(from.name || '?')} → ${esc(to.name || '?')}</td>`;
				html += `<td class="sp-mileage-leg-purpose">${esc(to.purpose || '')}</td>`;
				html += `<td class="sp-num">${miles == null ? '—' : mileageNumFmt(miles)}</td>`;
				html += `<td class="sp-num">${dollar}</td>`;
				if (hasTrl) html += '<td class="sp-num"></td>';
				if (hasToll) html += `<td class="sp-num">${tc > 0 ? money(tc) : '—'}</td>`;
				html += '</tr>';
			}
			html += '<tr class="sp-mileage-day-total"><td></td><td></td><td class="sp-mileage-day-total-label">Total for Day</td><td></td>';
			html += `<td class="sp-num">${mileageNumFmt(dayMiles)}</td><td class="sp-num">${money(e.reimbursement_amount)}</td>`;
			if (hasTrl) html += `<td class="sp-num">${(parseFloat(e.total_trailer) || 0) > 0 ? money(e.total_trailer) : '—'}</td>`;
			if (hasToll) html += `<td class="sp-num">${(parseFloat(e.total_tolls) || 0) > 0 ? money(e.total_tolls) : '—'}</td>`;
			html += '</tr>';
			html += '</tbody>';
		});
		// Section A grand total — label + amount combined in one right-aligned cell so every
		// section's total lines up on the right edge regardless of its columns.
		const aCols = 6 + (hasTrl ? 1 : 0) + (hasToll ? 1 : 0);
		html += `<tbody><tr class="sp-vexp-grand"><td colspan="${aCols}" class="sp-rep-total-line">Section A Total · ${money(mileageReimb)}</td></tr></tbody>`;
		html += '</table></div>';
	}

	// B — Vehicle Expenses
	if (vehicle.length) {
		const catLabel = k => (vcats[k] && vcats[k].label) || k || '—';
		let bGrand = 0;
		html += '<h3 class="sp-rep-section-title">B — Vehicle Expenses</h3>';
		html += '<div class="sp-card sp-table-card"><table class="sp-table sp-rep-table"><thead class="sp-thead"><tr><th>Date</th><th>Category</th><th>Description</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th></tr></thead><tbody>';
		vehicle.forEach(x => {
			const amt = parseFloat(x.amount) || 0; bGrand += amt;
			html += `<tr><td>${esc(formatDate(x.expense_date))}</td><td>${esc(catLabel(x.category))}</td><td>${esc(x.description || '')}</td><td>${esc(x.store_number || '')}</td><td class="sp-num">${money(amt)}</td></tr>`;
		});
		html += `<tr class="sp-vexp-grand"><td colspan="5" class="sp-rep-total-line">Section B Total · ${money(bGrand)}</td></tr>`;
		html += '</tbody></table></div>';
	}

	// C — Business Meals
	if (meals.length) {
		html += '<h3 class="sp-rep-section-title">C — Business Meals</h3>';
		html += '<div class="sp-card sp-table-card"><table class="sp-table sp-rep-table"><thead class="sp-thead"><tr><th>Date</th><th>Place</th><th>Business Purpose</th><th>Attendees</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th></tr></thead><tbody>';
		meals.forEach(x => {
			html += `<tr><td>${esc(formatDate(x.expense_date))}</td><td>${esc(x.place || '')}</td><td>${esc(x.business_purpose || '')}</td><td>${esc(x.attendees || '')}</td><td>${esc(x.store_number || '')}</td><td class="sp-num">${money(x.amount)}</td></tr>`;
		});
		html += `<tr class="sp-vexp-grand"><td colspan="6" class="sp-rep-total-line">Section C Total · ${money(cTotal)}</td></tr>`;
		html += '</tbody></table></div>';
	}

	// D — Competitive Shopping
	if (shopping.length) {
		html += '<h3 class="sp-rep-section-title">D — Competitive Shopping</h3>';
		html += '<div class="sp-card sp-table-card"><table class="sp-table sp-rep-table"><thead class="sp-thead"><tr><th>Date</th><th>Place</th><th>Business Purpose</th><th>Attendees</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th></tr></thead><tbody>';
		shopping.forEach(x => {
			html += `<tr><td>${esc(formatDate(x.expense_date))}</td><td>${esc(x.place || '')}</td><td>${esc(x.business_purpose || '')}</td><td>${esc(x.attendees || '')}</td><td>${esc(x.store_number || '')}</td><td class="sp-num">${money(x.amount)}</td></tr>`;
		});
		html += `<tr class="sp-vexp-grand"><td colspan="6" class="sp-rep-total-line">Section D Total · ${money(dTotal)}</td></tr>`;
		html += '</tbody></table></div>';
	}

	// E — Other Expenses
	if (other.length) {
		html += '<h3 class="sp-rep-section-title">E — Other Expenses</h3>';
		html += '<div class="sp-card sp-table-card"><table class="sp-table sp-rep-table"><thead class="sp-thead"><tr><th>Date</th><th>Description</th><th>Account</th><th>Store&nbsp;#</th><th class="sp-num">Amount</th></tr></thead><tbody>';
		other.forEach(x => {
			html += `<tr><td>${esc(formatDate(x.expense_date))}</td><td>${esc(x.description || '')}</td><td>${esc(x.account_code || '')}</td><td>${esc(x.store_number || '')}</td><td class="sp-num">${money(x.amount)}</td></tr>`;
		});
		html += `<tr class="sp-vexp-grand"><td colspan="5" class="sp-rep-total-line">Section E Total · ${money(eTotal)}</td></tr>`;
		html += '</tbody></table></div>';
	}

	// F — Travel Expenses (Date | Category | Description | Amount, like Section B; all GL 91110)
	if (travel.length) {
		const tcatLabel = k => (tcats[k] && tcats[k].label) || k || '—';
		html += '<h3 class="sp-rep-section-title">F — Travel Expenses</h3>';
		html += '<div class="sp-card sp-table-card"><table class="sp-table sp-rep-table"><thead class="sp-thead"><tr><th>Date</th><th>Category</th><th>Description</th><th class="sp-num">Amount</th></tr></thead><tbody>';
		travel.forEach(x => {
			html += `<tr><td>${esc(formatDate(x.expense_date))}</td><td>${esc(tcatLabel(x.category))}</td><td>${esc(x.description || '')}</td><td class="sp-num">${money(x.amount)}</td></tr>`;
		});
		html += `<tr class="sp-vexp-grand"><td colspan="4" class="sp-rep-total-line">Section F Total · ${money(fTotal)}</td></tr>`;
		html += '</tbody></table></div>';
	}

	wrap.innerHTML = html;
	markUniqueSpans(wrap);
	// Fill the toolbar's Submit slot (own Overview only) — button, or a status pill once submitted.
	if (!readonly) {
		const slot = $('#sp-rep-submit-slot');
		if (slot) {
			slot.innerHTML = spRepSubmitControl(data);
			markUniqueSpans(slot);
			const sb = slot.querySelector('#sp-rep-submit-btn');
			if (sb) sb.addEventListener('click', submitExpenseReport);
		}
	}
}

// The submit control (toolbar, next to PDF): a Submit button, or a status pill once the period is
// submitted. Rejection note + "waiting" hint ride the pill's tooltip to stay compact on the toolbar.
function spRepSubmitControl(data) {
	const rep = data.report;
	const dt = s => s ? fmtReportDate(String(s).slice(0, 10)) : '';
	if (rep && rep.status === 'submitted') {
		return `<span class="sp-submit-pill sp-submit-pill-sent" title="Waiting for your supervisor's approval — this period is locked.">Submitted on ${dt(rep.submitted_at)}</span>`;
	}
	if (rep && rep.status === 'approved') {
		return `<span class="sp-submit-pill sp-submit-pill-approved" title="${esc(rep.approved_by_name ? 'Approved by ' + rep.approved_by_name : 'Approved')}">Approved on ${dt(rep.approved_at)}</span>`;
	}
	let h = '';
	if (rep && rep.status === 'rejected') h += `<span class="sp-submit-pill sp-submit-pill-rejected" title="${esc(rep.reject_note || 'Sent back for edits')}">Sent back</span>`;
	if (data.can_submit_report) h += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-rep-submit-btn">Submit for Approval</button>';
	return h;
}

// Freeze the current period to a PDF, then post it for the supervisor's approval. Locks the period.
async function submitExpenseReport() {
	if (!_spRep.start || !_spRep.end) { alert('Pick a pay period first.'); return; }
	const btn = $('#sp-rep-submit-btn');
	if (!confirm('Submit this expense report to your supervisor for approval?\n\nYou won\'t be able to edit this period afterward unless it is sent back to you.')) return;
	if (btn) { btn.disabled = true; btn.textContent = 'Building PDF…'; }
	let pdf = null;
	try { pdf = await exportMileagePDF(_spRep.start, _spRep.end, { returnBase64: true }); } catch (e) { pdf = null; }
	if (!pdf) { if (btn) { btn.disabled = false; btn.textContent = 'Submit for Approval'; } return; }
	const data = _spRep.lastData || {};
	const total = spBuildAccountSummary(data).total;
	if (btn) btn.textContent = 'Submitting…';
	const res = await spAjax('site_pulse_submit_expense_report', {
		start: _spRep.start, end: _spRep.end, label: data.label || '', total: total, pdf_base64: pdf,
	});
	if (res.success) {
		alert(res.data && res.data.auto_approved ? 'Submitted — your supervisor auto-approves your reports, so it has already been approved and sent to accounting.' : 'Submitted for approval.');
		loadExpenseReport();
	} else {
		alert((res.data && res.data.message) || 'Could not submit.');
		if (btn) { btn.disabled = false; btn.textContent = 'Submit for Approval'; }
	}
}

/* ---- Expense report approval: supervisor queue (approve/reject/auto-approve) + approved archive ---- */
let _spAppr = { reports: [], auto: [], canAll: false };
let _spArch = { reports: [] };

function initApproveExpense() { loadApproveExpense(); }

async function loadApproveExpense() {
	const wrap = $('#sp-approve-content'), detail = $('#sp-approve-detail');
	if (detail) { detail.hidden = true; detail.innerHTML = ''; }
	if (!wrap) return;
	wrap.hidden = false;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	const res = await spAjax('site_pulse_get_expense_approvals', {});
	if (!res.success) { wrap.innerHTML = `<div class="sp-empty-state"><p>${esc(res.data?.message || 'Could not load the queue.')}</p></div>`; return; }
	_spAppr.reports = res.data.reports || [];
	_spAppr.auto = (res.data.auto_approve || []).map(n => parseInt(n, 10));
	_spAppr.canAll = !!res.data.can_view_all;
	renderApproveList();
}

function spApprCard(r) {
	const money = n => '$' + mileageNumFmt(n);
	const dt = s => s ? fmtReportDate(String(s).slice(0, 10)) : '';
	const label = r.period_label || (dt(r.period_start) + ' – ' + dt(r.period_end));
	let pill = '';
	if (r.status === 'submitted') pill = '<span class="sp-submit-pill sp-submit-pill-sent">Pending</span>';
	else if (r.status === 'approved') pill = `<span class="sp-submit-pill sp-submit-pill-approved">Approved ${dt(r.approved_at)}</span>`;
	else if (r.status === 'rejected') pill = '<span class="sp-submit-pill sp-submit-pill-rejected">Sent back</span>';
	return `<div class="sp-appr-card unique" data-report="${r.id}">`
		+ `<div class="sp-appr-card-main"><div class="sp-appr-card-name">${esc(r.user_name)}</div><div class="sp-appr-card-sub">${esc(label)} · ${money(r.total_amount)}</div></div>`
		+ `<div class="sp-appr-card-meta">${pill}<div class="sp-appr-card-date">Submitted ${dt(r.submitted_at)}</div></div></div>`;
}

function renderApproveList() {
	const wrap = $('#sp-approve-content');
	if (!wrap) return;
	const reps = _spAppr.reports;
	if (!reps.length) { wrap.innerHTML = '<div class="sp-empty-state"><p>No expense reports in your queue yet.</p></div>'; return; }
	const pending = reps.filter(r => r.status === 'submitted');
	const done = reps.filter(r => r.status !== 'submitted');
	let html = `<h3 class="sp-rep-section-title">Pending approval (${pending.length})</h3>`;
	html += '<div class="sp-appr-list">' + (pending.length ? pending.map(spApprCard).join('') : '<div class="sp-empty-state"><p>Nothing waiting.</p></div>') + '</div>';
	if (done.length) {
		html += '<h3 class="sp-rep-section-title">Recently actioned</h3>';
		html += '<div class="sp-appr-list">' + done.map(spApprCard).join('') + '</div>';
	}
	wrap.innerHTML = html;
	markUniqueSpans(wrap);
	wrap.querySelectorAll('.sp-appr-card').forEach(c => c.addEventListener('click', () => openApproveDetail(parseInt(c.dataset.report, 10))));
}

function openApproveDetail(id) {
	const r = _spAppr.reports.find(x => parseInt(x.id, 10) === id);
	if (r) openReportDetail(r, 'approve', $('#sp-approve-detail'), $('#sp-approve-content'));
}

// Shared detail renderer for the queue (mode 'approve') and the archive (mode 'view').
async function openReportDetail(r, mode, detail, listWrap) {
	if (!detail) return;
	if (listWrap) listWrap.hidden = true;
	detail.hidden = false;
	detail.innerHTML = '<div class="sp-loading"></div>';
	const money = n => '$' + mileageNumFmt(n);
	const dt = s => s ? fmtReportDate(String(s).slice(0, 10)) : '';
	const label = r.period_label || (dt(r.period_start) + ' – ' + dt(r.period_end));

	let bar = '<div class="sp-approve-actionbar">';
	bar += '<button type="button" class="unique sp-btn sp-btn-ghost" data-appr-back>← Back</button>';
	bar += `<div class="sp-approve-actionbar-title">${esc(r.user_name)} · ${esc(label)} · ${money(r.total_amount)}</div>`;
	bar += '<div class="sp-approve-actionbar-buttons">';
	bar += '<button type="button" class="unique sp-btn sp-btn-secondary" data-appr-pdf>Download PDF</button>';
	if (mode === 'approve' && r.status === 'submitted') {
		bar += '<button type="button" class="unique sp-btn sp-btn-secondary sp-appr-reject" data-appr-reject>Send back</button>';
		bar += '<button type="button" class="unique sp-btn sp-btn-primary" data-appr-approve>Approve</button>';
	} else if (r.status === 'approved') {
		bar += `<span class="sp-submit-pill sp-submit-pill-approved">Approved on ${dt(r.approved_at)}${r.approved_by_name ? ' by ' + esc(r.approved_by_name) : ''}</span>`;
	} else if (r.status === 'rejected') {
		bar += '<span class="sp-submit-pill sp-submit-pill-rejected">Sent back</span>';
	}
	bar += '</div></div>';

	if (mode === 'approve') {
		const on = _spAppr.auto.indexOf(parseInt(r.user_id, 10)) !== -1;
		bar += `<label class="sp-appr-auto"><input type="checkbox" class="sp-recurring-check" data-appr-auto ${on ? 'checked' : ''}><span class="unique">Auto-approve all reports from ${esc(r.user_name)}</span></label>`;
	}
	if (r.reject_note && r.status === 'rejected') bar += `<div class="sp-appr-note">Sent back: ${esc(r.reject_note)}</div>`;

	detail.innerHTML = bar + '<div class="sp-approve-report" id="sp-approve-report-host"></div>';
	markUniqueSpans(detail);

	const back = () => { detail.hidden = true; detail.innerHTML = ''; if (listWrap) listWrap.hidden = false; };
	detail.querySelector('[data-appr-back]')?.addEventListener('click', back);
	detail.querySelector('[data-appr-pdf]')?.addEventListener('click', () => downloadReportPdf(r.id));
	detail.querySelector('[data-appr-approve]')?.addEventListener('click', () => approveReport(r, mode));
	detail.querySelector('[data-appr-reject]')?.addEventListener('click', () => rejectReport(r, mode));
	detail.querySelector('[data-appr-auto]')?.addEventListener('change', (e) => toggleAutoApprove(r, e.target.checked, mode));

	const data = await fetchMileageReport(r.period_start, r.period_end, r.user_id);
	const host = detail.querySelector('#sp-approve-report-host');
	if (!host) return;
	if (!data) { host.innerHTML = '<div class="sp-empty-state"><p>Could not load the report.</p></div>'; return; }
	renderExpenseReport(data, { target: host, readonly: true });
}

async function approveReport(r, mode) {
	if (!confirm('Approve this report? It will be emailed to accounting and ' + r.user_name + ' will be notified.')) return;
	const res = await spAjax('site_pulse_approve_expense_report', { report_id: r.id });
	if (!res.success) { alert(res.data?.message || 'Could not approve.'); return; }
	if (res.data && res.data.emailed === false) alert('Approved — but no accounting email is set (Settings → Expense Reports), so nothing was emailed. Set it, then re-approve if needed.');
	refreshAfterAction(mode);
}

async function rejectReport(r, mode) {
	const note = prompt('Send this report back to ' + r.user_name + ' for edits. Add a note (optional):', '');
	if (note === null) return;
	const res = await spAjax('site_pulse_reject_expense_report', { report_id: r.id, note: note });
	if (!res.success) { alert(res.data?.message || 'Could not send it back.'); return; }
	refreshAfterAction(mode);
}

async function toggleAutoApprove(r, on, mode) {
	const res = await spAjax('site_pulse_set_expense_auto_approve', { user_id: r.user_id, on: on ? 1 : 0 });
	if (!res.success) { alert(res.data?.message || 'Could not update auto-approve.'); return; }
	_spAppr.auto = (res.data.auto_approve || []).map(n => parseInt(n, 10));
	if (on) refreshAfterAction(mode); // approves this + any other queued reports server-side
}

function refreshAfterAction(mode) {
	const detail = $(mode === 'view' ? '#sp-approved-detail' : '#sp-approve-detail');
	if (detail) { detail.hidden = true; detail.innerHTML = ''; }
	if (mode === 'view') loadApprovedExpense(); else loadApproveExpense();
}

async function downloadReportPdf(id) {
	const res = await spAjax('site_pulse_get_expense_report_pdf', { report_id: id });
	if (!res.success) { alert(res.data?.message || 'Could not fetch the PDF.'); return; }
	const uri = res.data.pdf_base64 || '';
	const b64 = uri.indexOf('base64,') !== -1 ? uri.split('base64,')[1] : uri;
	try {
		const bin = atob(b64);
		const bytes = new Uint8Array(bin.length);
		for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
		downloadBlob(res.data.filename || 'expense-report.pdf', new Blob([bytes], { type: 'application/pdf' }));
	} catch (e) { alert('Could not decode the PDF.'); }
}

function initApprovedExpense() {
	const panel = $('#sp-panel-approved-expense');
	if (panel && !panel._wired) {
		panel._wired = true;
		$('#sp-approved-start')?.addEventListener('change', loadApprovedExpense);
		$('#sp-approved-end')?.addEventListener('change', loadApprovedExpense);
		$('#sp-approved-clear')?.addEventListener('click', () => { const s = $('#sp-approved-start'), e = $('#sp-approved-end'); if (s) s.value = ''; if (e) e.value = ''; loadApprovedExpense(); });
	}
	loadApprovedExpense();
}

async function loadApprovedExpense() {
	const wrap = $('#sp-approved-content'), detail = $('#sp-approved-detail');
	if (detail) { detail.hidden = true; detail.innerHTML = ''; }
	if (!wrap) return;
	wrap.hidden = false;
	wrap.innerHTML = '<div class="sp-loading"></div>';
	const res = await spAjax('site_pulse_get_approved_expense_reports', { start: $('#sp-approved-start')?.value || '', end: $('#sp-approved-end')?.value || '' });
	if (!res.success) { wrap.innerHTML = `<div class="sp-empty-state"><p>${esc(res.data?.message || 'Could not load the archive.')}</p></div>`; return; }
	_spArch.reports = res.data.reports || [];
	renderApprovedList();
}

function renderApprovedList() {
	const wrap = $('#sp-approved-content');
	if (!wrap) return;
	const reps = _spArch.reports;
	if (!reps.length) { wrap.innerHTML = '<div class="sp-empty-state"><p>No approved reports found for this range.</p></div>'; return; }
	const money = n => '$' + mileageNumFmt(n);
	const dt = s => s ? fmtReportDate(String(s).slice(0, 10)) : '';
	let html = '<div class="sp-card sp-table-card"><table class="sp-table"><thead class="sp-thead"><tr><th>Employee</th><th>Period</th><th class="sp-num">Total</th><th>Approved</th><th>By</th><th></th></tr></thead><tbody>';
	reps.forEach(r => {
		const label = r.period_label || (dt(r.period_start) + ' – ' + dt(r.period_end));
		html += `<tr class="sp-arch-row unique" data-report="${r.id}"><td>${esc(r.user_name)}</td><td>${esc(label)}</td><td class="sp-num">${money(r.total_amount)}</td><td>${dt(r.approved_at)}</td><td>${esc(r.approved_by_name || '')}</td><td class="sp-num"><button type="button" class="unique sp-btn sp-btn-ghost sp-arch-pdf" data-report="${r.id}">PDF</button></td></tr>`;
	});
	html += '</tbody></table></div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);
	wrap.querySelectorAll('.sp-arch-pdf').forEach(b => b.addEventListener('click', (e) => { e.stopPropagation(); downloadReportPdf(parseInt(b.dataset.report, 10)); }));
	wrap.querySelectorAll('.sp-arch-row').forEach(row => row.addEventListener('click', () => {
		const r = _spArch.reports.find(x => parseInt(x.id, 10) === parseInt(row.dataset.report, 10));
		if (r) openReportDetail(r, 'view', $('#sp-approved-detail'), $('#sp-approved-content'));
	}));
}

async function exportMileagePDF(start, end, opts) {
	opts = opts || {};
	if (typeof window.jspdf === 'undefined' || !window.jspdf.jsPDF) {
		alert('PDF library is still loading — please try again in a moment.');
		return;
	}
	const data = await fetchMileageReport(start, end);
	if (!data) return;
	const entries = data.entries || [];
	const vehicle = data.vehicle_expenses || [];
	const meals = data.business_meals || [];
	const shopping = data.competitive_shopping || [];
	const other = data.other_expenses || [];
	const travel = data.travel_expenses || [];
	if (!entries.length && !vehicle.length && !meals.length && !shopping.length && !other.length && !travel.length) { if (!opts.returnBase64) alert('No expenses for this period.'); return null; }

	const rate = parseFloat(data.rate) || 0;
	const money = n => '$' + mileageNumFmt(n);
	const sumOf = (arr, f) => arr.reduce((s, x) => s + (parseFloat(f(x)) || 0), 0);

	// Section totals — computed exactly like the on-screen Expense Report (renderExpenseReport),
	// so the PDF's Summary of Expenses and every section total match the screen to the penny.
	const totalMiles = sumOf(entries, e => e.total_miles);
	const mileageReimb = rate > 0 ? Math.round(totalMiles * rate * 100) / 100 : 0;
	const mileageTolls = sumOf(entries, e => e.total_tolls);
	const mileageTrailer = sumOf(entries, e => e.total_trailer);
	const hasTolls = mileageTolls > 0 && rate > 0;
	const hasTrailer = mileageTrailer > 0 && rate > 0;

	const cTotal = sumOf(meals, x => x.amount);
	const dTotal = sumOf(shopping, x => x.amount);
	const eTotal = sumOf(other, x => x.amount);
	const fTotal = sumOf(travel, x => x.amount); // Section F — all categories roll up to GL 91110
	// Summary of Expenses is grouped dynamically by GL account (shared with the on-screen report).
	const summary = spBuildAccountSummary(data);
	const totalDue = summary.total;

	const { jsPDF } = window.jspdf;
	const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'letter' });
	const NAVY = [21, 36, 58], SOFT = [230, 234, 241], GREY = [90, 90, 85], FAINT = [154, 154, 149];

	const pageH = doc.internal.pageSize.getHeight();
	const pageW = doc.internal.pageSize.getWidth();

	// Header — mirrors the on-screen report header (title + Payee / Period / Week Ending / Home Base).
	doc.setFont('helvetica', 'bold'); doc.setFontSize(18); doc.setTextColor(...NAVY);
	doc.text(String(data.company_name || data.app_name || 'Expense Report'), 40, 48);
	doc.setFont('helvetica', 'normal'); doc.setFontSize(10.5); doc.setTextColor(...GREY);
	let y = 68;
	const hf = (l, v) => { doc.text(`${l}:  ${v || '—'}`, 40, y); y += 14; };
	hf('Payee', data.user_name);
	hf('Period', data.label);
	hf('Week Ending', data.end ? formatDate(data.end) : '—');
	if (data.home_label) hf('Home Base', data.home_label);
	doc.setFontSize(8); doc.setTextColor(...FAINT);
	doc.text(`Generated: ${fmtReportDate(new Date())}`, 40, y + 1);

	// Summary of Expenses — one line per GL account (from the chart of accounts), then Total Due.
	// Built from the same shared helper as the on-screen report, so the two always match.
	doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(...NAVY);
	doc.text('Summary of Expenses', 40, y + 26);
	doc.setFont('helvetica', 'normal');
	doc.autoTable({
		startY: y + 34,
		head: [['Description', 'Amount', 'Account']],
		body: summary.rows.map(r => [r.desc, { content: money(r.amount), styles: { halign: 'right' } }, r.account_label || r.account]),
		foot: [['Total Due to Employee', { content: money(totalDue), styles: { halign: 'right' } }, '']],
		headStyles: { fillColor: NAVY, textColor: 255, fontSize: 9, fontStyle: 'bold' },
		footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 10 },
		bodyStyles: { fontSize: 9, textColor: [26, 26, 24] },
		alternateRowStyles: { fillColor: [248, 248, 245] },
		columnStyles: {
			0: { cellWidth: 532 - 90 - 150 },
			1: { cellWidth: 90, halign: 'right' },
			2: { cellWidth: 150 },
		},
		margin: { left: 40, right: 40 },
	});

	// Section A — Mileage: one row per leg (Date · Charge To · Route · Purpose · Miles · $ ·
	// [Trailer] · [Tolls]), a bold "Total for Day" row per day, then the Section A total — laid
	// out exactly like the on-screen report so the two read the same.
	if (entries.length) {
		let aY = doc.lastAutoTable.finalY + 28;
		if (aY > pageH - 130) { doc.addPage(); aY = 54; }
		doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(...NAVY);
		doc.text('A — Mileage', 40, aY);
		doc.setFont('helvetica', 'normal');

		const aHead = ['Date', 'Charge To', 'Route', 'Purpose', 'Miles', '$'];
		if (hasTrailer) aHead.push('Trailer');
		if (hasTolls) aHead.push('Tolls');
		const aBody = [], aDayRows = new Set();
		entries.forEach(e => {
			const stops = e.route_stops || [];
			for (let i = 1; i < stops.length; i++) {
				const from = stops[i - 1], to = stops[i];
				const miles = to.miles == null ? null : parseFloat(to.miles) || 0;
				const tc = to.toll == null ? 0 : parseFloat(to.toll) || 0;
				const row = [
					i === 1 ? formatDate(e.entry_date) : '',
					to.charge_to || '',
					`${from.name || '?'} › ${to.name || '?'}`,
					to.purpose || '',
					miles == null ? '—' : mileageNumFmt(miles),
					miles == null ? '—' : money(rate > 0 ? miles * rate : 0),
				];
				if (hasTrailer) row.push('');
				if (hasTolls) row.push(tc > 0 ? money(tc) : '—');
				aBody.push(row);
			}
			const tr = ['', '', 'Total for Day', '', mileageNumFmt(e.total_miles), money(e.reimbursement_amount)];
			if (hasTrailer) tr.push((parseFloat(e.total_trailer) || 0) > 0 ? money(e.total_trailer) : '—');
			if (hasTolls) tr.push((parseFloat(e.total_tolls) || 0) > 0 ? money(e.total_tolls) : '—');
			aDayRows.add(aBody.length);
			aBody.push(tr);
		});

		const aExtra = (hasTrailer ? 44 : 0) + (hasTolls ? 44 : 0);
		const aRP = 532 - 50 - 58 - 36 - 46 - aExtra;   // Route + Purpose share the remainder
		const aCols = {
			0: { cellWidth: 50 },
			1: { cellWidth: 58, halign: 'center' },
			2: { cellWidth: Math.round(aRP * 0.55) },
			3: { cellWidth: aRP - Math.round(aRP * 0.55) },
			4: { cellWidth: 36, halign: 'right' },
			5: { cellWidth: 46, halign: 'right' },
		};
		let aci = 6;
		if (hasTrailer) { aCols[aci] = { cellWidth: 44, halign: 'right' }; aci++; }
		if (hasTolls) { aCols[aci] = { cellWidth: 44, halign: 'right' }; aci++; }

		doc.autoTable({
			startY: aY + 8,
			head: [aHead],
			body: aBody,
			foot: [[{ content: `Section A Total · ${money(mileageReimb)}`, colSpan: aHead.length, styles: { halign: 'right' } }]],
			headStyles: { fillColor: NAVY, textColor: 255, fontSize: 8.5, fontStyle: 'bold' },
			footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 9.5 },
			bodyStyles: { fontSize: 8, textColor: [26, 26, 24], valign: 'top' },
			alternateRowStyles: { fillColor: [248, 248, 245] },
			columnStyles: aCols,
			margin: { left: 40, right: 40 },
			// Bold + tinted "Total for Day" rows, matching the on-screen day-total styling.
			didParseCell: (d) => {
				if (d.section === 'body' && aDayRows.has(d.row.index)) {
					d.cell.styles.fontStyle = 'bold';
					d.cell.styles.fillColor = [233, 236, 241];
				}
			},
		});
	}

	// Section B — Vehicle Expenses: Date | Category | Description | Amount (mirrors the report).
	// The GL groupings live in the Summary above, so no per-category subtotals here.
	if (vehicle.length) {
		const vcats = data.vehicle_categories || {};
		const vcatLabel = k => (vcats[k] && vcats[k].label) || k || '—';
		let bY = doc.lastAutoTable.finalY + 28;
		if (bY > pageH - 130) { doc.addPage(); bY = 54; }
		doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(...NAVY);
		doc.text('B — Vehicle Expenses', 40, bY);
		doc.setFont('helvetica', 'normal');

		let bGrand = 0;
		const vBody = vehicle.map(x => {
			const amt = parseFloat(x.amount) || 0; bGrand += amt;
			return [formatDate(x.expense_date), vcatLabel(x.category), String(x.description || ''), String(x.store_number || ''), money(amt)];
		});

		const VDATE_W = 60, VCAT_W = 120, VSTORE_W = 56, VAMT_W = 72;
		doc.autoTable({
			startY: bY + 8,
			head: [['Date', 'Category', 'Description', 'Store #', 'Amount']],
			body: vBody,
			foot: [[{ content: `Section B Total · ${money(bGrand)}`, colSpan: 5, styles: { halign: 'right' } }]],
			headStyles: { fillColor: NAVY, textColor: 255, fontSize: 9, fontStyle: 'bold' },
			footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 9 },
			bodyStyles: { fontSize: 9, textColor: [26, 26, 24], valign: 'top' },
			alternateRowStyles: { fillColor: [248, 248, 245] },
			columnStyles: {
				0: { cellWidth: VDATE_W },
				1: { cellWidth: VCAT_W },
				2: { cellWidth: 532 - VDATE_W - VCAT_W - VSTORE_W - VAMT_W },
				3: { cellWidth: VSTORE_W, halign: 'center' },
				4: { cellWidth: VAMT_W, halign: 'right' },
			},
			margin: { left: 40, right: 40 },
		});
	}

	// Section C — Business Meals: Date | Place | Business Purpose | Attendees | Store # | Amount,
	// with a Section C total (all GL 91110).
	if (meals.length) {
		let cY = doc.lastAutoTable.finalY + 28;
		if (cY > pageH - 130) { doc.addPage(); cY = 54; }
		doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(...NAVY);
		doc.text('C — Business Meals', 40, cY);
		doc.setFont('helvetica', 'normal');

		let cGrand = 0;
		const cBody = meals.map(x => {
			const amt = parseFloat(x.amount) || 0;
			cGrand += amt;
			return [
				formatDate(x.expense_date), String(x.place || ''), String(x.business_purpose || ''),
				String(x.attendees || ''), String(x.store_number || ''), `$${mileageNumFmt(amt)}`,
			];
		});

		const CDATE_W = 58, CSTORE_W = 44, CAMT_W = 64, CFREE = 532 - CDATE_W - CSTORE_W - CAMT_W;
		doc.autoTable({
			startY: cY + 8,
			head: [['Date', 'Place', 'Business Purpose', 'Attendees', 'Store #', 'Amount']],
			body: cBody,
			foot: [[{ content: `Section C Total · ${money(cGrand)}`, colSpan: 6, styles: { halign: 'right' } }]],
			headStyles: { fillColor: NAVY, textColor: 255, fontSize: 9, fontStyle: 'bold' },
			footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 9 },
			bodyStyles: { fontSize: 8.5, textColor: [26, 26, 24], valign: 'top' },
			alternateRowStyles: { fillColor: [248, 248, 245] },
			columnStyles: {
				0: { cellWidth: CDATE_W },
				1: { cellWidth: Math.round(CFREE * 0.30) },
				2: { cellWidth: Math.round(CFREE * 0.40) },
				3: { cellWidth: CFREE - Math.round(CFREE * 0.30) - Math.round(CFREE * 0.40) },
				4: { cellWidth: CSTORE_W },
				5: { cellWidth: CAMT_W, halign: 'right' },
			},
			margin: { left: 40, right: 40 },
		});
	}

	// Section D — Competitive Shopping: Date | Place | Business Purpose | Attendees | Store # | Amount,
	// with a Section D total (all GL 81095, R&D-Food).
	if (shopping.length) {
		let dY = doc.lastAutoTable.finalY + 28;
		if (dY > pageH - 130) { doc.addPage(); dY = 54; }
		doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(...NAVY);
		doc.text('D — Competitive Shopping', 40, dY);
		doc.setFont('helvetica', 'normal');

		let dGrand = 0;
		const dBody = shopping.map(x => {
			const amt = parseFloat(x.amount) || 0;
			dGrand += amt;
			return [
				formatDate(x.expense_date), String(x.place || ''), String(x.business_purpose || ''),
				String(x.attendees || ''), String(x.store_number || ''), `$${mileageNumFmt(amt)}`,
			];
		});

		const DDATE_W = 58, DSTORE_W = 44, DAMT_W = 64, DFREE = 532 - DDATE_W - DSTORE_W - DAMT_W;
		doc.autoTable({
			startY: dY + 8,
			head: [['Date', 'Place', 'Business Purpose', 'Attendees', 'Store #', 'Amount']],
			body: dBody,
			foot: [[{ content: `Section D Total · ${money(dGrand)}`, colSpan: 6, styles: { halign: 'right' } }]],
			headStyles: { fillColor: NAVY, textColor: 255, fontSize: 9, fontStyle: 'bold' },
			footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 9 },
			bodyStyles: { fontSize: 8.5, textColor: [26, 26, 24], valign: 'top' },
			alternateRowStyles: { fillColor: [248, 248, 245] },
			columnStyles: {
				0: { cellWidth: DDATE_W },
				1: { cellWidth: Math.round(DFREE * 0.34) },
				2: { cellWidth: Math.round(DFREE * 0.36) },
				3: { cellWidth: DFREE - Math.round(DFREE * 0.34) - Math.round(DFREE * 0.36) },
				4: { cellWidth: DSTORE_W },
				5: { cellWidth: DAMT_W, halign: 'right' },
			},
			margin: { left: 40, right: 40 },
		});
	}

	// Section E — Other Expenses: Date | Description | Account | Store # | Amount, with a Section E
	// total. Each row carries its own GL account (free entry), so there's no single GL line here.
	if (other.length) {
		let eY = doc.lastAutoTable.finalY + 28;
		if (eY > pageH - 130) { doc.addPage(); eY = 54; }
		doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(...NAVY);
		doc.text('E — Other Expenses', 40, eY);
		doc.setFont('helvetica', 'normal');

		let eGrand = 0;
		const eBody = other.map(x => {
			const amt = parseFloat(x.amount) || 0;
			eGrand += amt;
			return [
				formatDate(x.expense_date), String(x.description || ''), String(x.account_code || ''),
				String(x.store_number || ''), `$${mileageNumFmt(amt)}`,
			];
		});

		const EDATE_W = 58, EACCT_W = 64, ESTORE_W = 44, EAMT_W = 64, EDESC_W = 532 - EDATE_W - EACCT_W - ESTORE_W - EAMT_W;
		doc.autoTable({
			startY: eY + 8,
			head: [['Date', 'Description', 'Account', 'Store #', 'Amount']],
			body: eBody,
			foot: [[{ content: `Section E Total · ${money(eGrand)}`, colSpan: 5, styles: { halign: 'right' } }]],
			headStyles: { fillColor: NAVY, textColor: 255, fontSize: 9, fontStyle: 'bold' },
			footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 9 },
			bodyStyles: { fontSize: 8.5, textColor: [26, 26, 24], valign: 'top' },
			alternateRowStyles: { fillColor: [248, 248, 245] },
			columnStyles: {
				0: { cellWidth: EDATE_W },
				1: { cellWidth: EDESC_W },
				2: { cellWidth: EACCT_W },
				3: { cellWidth: ESTORE_W },
				4: { cellWidth: EAMT_W, halign: 'right' },
			},
			margin: { left: 40, right: 40 },
		});
	}

	// Section F — Travel Expenses: Date | Category | Description | Amount (mirrors the report).
	// Like Section B — several categories, all GL 91110, so no per-category subtotals here.
	if (travel.length) {
		const tcats = data.travel_categories || {};
		const tcatLabel = k => (tcats[k] && tcats[k].label) || k || '—';
		let fY = doc.lastAutoTable.finalY + 28;
		if (fY > pageH - 130) { doc.addPage(); fY = 54; }
		doc.setFont('helvetica', 'bold'); doc.setFontSize(13); doc.setTextColor(...NAVY);
		doc.text('F — Travel Expenses', 40, fY);
		doc.setFont('helvetica', 'normal');

		let fGrand = 0;
		const fBody = travel.map(x => {
			const amt = parseFloat(x.amount) || 0; fGrand += amt;
			return [formatDate(x.expense_date), tcatLabel(x.category), String(x.description || ''), money(amt)];
		});

		const FDATE_W = 60, FCAT_W = 120, FAMT_W = 72;
		doc.autoTable({
			startY: fY + 8,
			head: [['Date', 'Category', 'Description', 'Amount']],
			body: fBody,
			foot: [[{ content: `Section F Total · ${money(fGrand)}`, colSpan: 4, styles: { halign: 'right' } }]],
			headStyles: { fillColor: NAVY, textColor: 255, fontSize: 9, fontStyle: 'bold' },
			footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 9 },
			bodyStyles: { fontSize: 9, textColor: [26, 26, 24], valign: 'top' },
			alternateRowStyles: { fillColor: [248, 248, 245] },
			columnStyles: {
				0: { cellWidth: FDATE_W },
				1: { cellWidth: FCAT_W },
				2: { cellWidth: 532 - FDATE_W - FCAT_W - FAMT_W },
				3: { cellWidth: FAMT_W, halign: 'right' },
			},
			margin: { left: 40, right: 40 },
		});
	}

	// Receipt photos — one page each, appended after the report. Built from every section's
	// receipt_url (vehicle/meals/shopping/other/travel), with a caption tying it back to the line.
	const receipts = [];
	const collect = (arr, sec) => (arr || []).forEach(x => {
		if (!x.receipt_url) return;
		const who = x.place || x.description || '';
		receipts.push({ url: x.receipt_url, label: `${sec} · ${formatDate(x.expense_date)}${who ? ' · ' + who : ''}` });
	});
	collect(vehicle, 'B — Vehicle Expenses');
	collect(meals, 'C — Business Meals');
	collect(shopping, 'D — Competitive Shopping');
	collect(other, 'E — Other Expenses');
	collect(travel, 'F — Travel Expenses');

	for (const rc of receipts) {
		const img = await spLoadImage(rc.url);
		if (!img) continue;
		doc.addPage();
		doc.setFont('helvetica', 'bold'); doc.setFontSize(12); doc.setTextColor(...NAVY);
		doc.text('Receipt — ' + rc.label, 40, 48);
		doc.setFont('helvetica', 'normal');
		const availW = pageW - 80, availH = pageH - 100;
		const iw = img.naturalWidth || img.width || 1, ih = img.naturalHeight || img.height || 1;
		let dw = availW, dh = dw * ih / iw;
		if (dh > availH) { dh = availH; dw = dh * iw / ih; }
		try { doc.addImage(img, 'JPEG', (pageW - dw) / 2, 66, dw, dh); } catch (e) {}
	}

	const now = new Date();
	const genISO = `${now.getFullYear()}-${mPad2(now.getMonth() + 1)}-${mPad2(now.getDate())}`;
	const periodSlug = (data.label || '').replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '') || 'all';
	// Submit flow: hand the base64 back instead of triggering a download, so it can be frozen server-side.
	if (opts.returnBase64) return doc.output('datauristring');
	doc.save(`${genISO}_Expense_Report_${periodSlug}.pdf`);
}

// Load an image URL into an <img> for jsPDF (resolves null on failure so the export continues).
function spLoadImage(url) {
	return new Promise((resolve) => {
		const img = new Image();
		img.onload = () => resolve(img);
		img.onerror = () => resolve(null);
		img.src = url;
	});
}

async function exportMileageCSV(start, end) {
	const data = await fetchMileageReport(start, end);
	if (!data) return;
	const entries = data.entries || [];
	if (!entries.length) { alert('No entries for this period.'); return; }
	const rate = parseFloat(data.rate) || 0;

	const rows = [['Date', 'Purpose', 'Route', 'Total Miles', 'Rate', 'Reimbursement', 'Trailer', 'Tolls', 'Grand Total']];
	entries.forEach(e => {
		const reimb = rate > 0 ? parseFloat(e.reimbursement_amount || 0) : 0;
		const toll = parseFloat(e.total_tolls || 0);
		const trailer = parseFloat(e.total_trailer || 0);
		rows.push([
			e.entry_date,
			e.purpose || '',
			e.route || '',
			parseFloat(e.total_miles || 0).toFixed(2),
			rate > 0 ? rate : '',
			rate > 0 ? reimb.toFixed(2) : '',
			trailer.toFixed(2),
			toll.toFixed(2),
			(reimb + toll + trailer).toFixed(2),
		]);
	});
	const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
	const now = new Date();
	const genISO = `${now.getFullYear()}-${mPad2(now.getMonth() + 1)}-${mPad2(now.getDate())}`;
	const periodSlug = (data.label || '').replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '') || 'all';
	downloadBlob(`${genISO}_Mileage_Report_${periodSlug}.csv`, csv, 'text/csv');
}


/*--------------------------------------------------------------
# Mileage — Admin
--------------------------------------------------------------*/

function renderApiTestResult(out, data) {
	const row = (label, ok, body) => {
		const dot = ok === true ? '✓' : ok === false ? '✗' : '•';
		const cls = ok === true ? 'sp-api-ok' : ok === false ? 'sp-api-fail' : '';
		return `<div class="sp-api-row ${cls}"><strong>${dot} ${esc(label)}</strong><div>${body}</div></div>`;
	};

	let html = '';
	html += row('API key', data.key_source !== 'NOT SET', `Source: <code>${esc(data.key_source)}</code><br>Preview: <code>${esc(data.key_preview)}</code>`);

	if (data.error) {
		html += `<div class="sp-api-row sp-api-fail"><strong>✗ Setup error</strong><div>${esc(data.error)}</div></div>`;
	}

	if (data.geocode) {
		const g = data.geocode;
		const body = g.ok
			? `Status: <code>${esc(g.status)}</code><br>Geocoded "Arlington, TX" to <code>${esc(g.result)}</code>`
			: `Status: <code>${esc(g.status || 'N/A')}</code><br>${esc(g.error || '')}`;
		html += row('Geocoding API', g.ok, body);
	}

	if (data.distance_matrix) {
		const d = data.distance_matrix;
		let body;
		if (d.ok) {
			body = `Status: <code>${esc(d.status)}</code> / condition: <code>${esc(d.element_status)}</code><br>HTTP <code>${esc(String(d.http_code || ''))}</code><br>Arlington → Burleson: <strong>${d.miles} mi</strong>`;
		} else {
			body = `Status: <code>${esc(d.status || 'N/A')}</code> / condition: <code>${esc(d.element_status || 'N/A')}</code> / HTTP <code>${esc(String(d.http_code || ''))}</code><br>${esc(d.error || '')}`;
			if (d.raw) body += `<details style="margin-top:8px;"><summary>Raw response</summary><pre style="white-space:pre-wrap;word-break:break-word;font-size:11px;margin:4px 0 0;">${esc(d.raw)}</pre></details>`;
		}
		html += row('Routes API (Compute Route Matrix)', d.ok, body);
	}

	if (data.geocode && !data.geocode.ok) {
		const status = data.geocode.status || '';
		html += '<div class="sp-api-hint">';
		if (status === 'REQUEST_DENIED') html += 'Likely cause: <strong>Geocoding API not enabled</strong> on the project, or the key has API restrictions excluding it. Enable it in Google Cloud Console → APIs & Services → Library.';
		else if (status === 'OVER_QUERY_LIMIT') html += 'Daily quota exceeded or billing not enabled. Enable billing in Google Cloud Console → Billing.';
		else if (status === 'INVALID_REQUEST') html += 'Address parameter rejected. Unusual — verify the key has no IP restrictions blocking the WP Engine outbound IP.';
		else html += 'Check the activity log entry for the raw response.';
		html += '</div>';
	} else if (data.distance_matrix && !data.distance_matrix.ok) {
		const err = (data.distance_matrix.error || '').toLowerCase();
		const raw = (data.distance_matrix.raw || '').toLowerCase();
		html += '<div class="sp-api-hint">';
		if (err.includes('has not been used') || err.includes('is disabled') || raw.includes('service_disabled')) {
			html += 'Likely cause: <strong>Routes API service is not enabled on the project.</strong> Click the link in the raw response above (or Cloud Console → APIs &amp; Services → Library → Routes API → Enable). Wait ~60 seconds, then re-test.';
		} else if (raw.includes('api_key_service_blocked') || raw.includes('api key not valid')) {
			html += 'Likely cause: <strong>API key restrictions exclude Routes API.</strong> Cloud Console → Credentials → your key → API restrictions → check "Routes API". Wait ~60 seconds, then re-test.';
		} else if (err.includes('legacy') || err.includes('not activated')) {
			html += 'Likely cause: <strong>Trying to use legacy Distance Matrix API on a new project.</strong> Routes API is the replacement; make sure it\'s enabled and selected in the key restrictions.';
		} else if (data.distance_matrix.status === 'PERMISSION_DENIED' || err.includes('permission') || err.includes('denied')) {
			html += 'Likely cause: <strong>Permission denied.</strong> Either the API isn\'t enabled on the project, or the key restrictions exclude it. Check both: APIs &amp; Services → Library (enable Routes API) and Credentials → your key → API restrictions (check Routes API).';
		} else {
			html += 'Geocoding worked but Routes API call failed. Check the activity log <code>mileage_error</code> entry for the raw response.';
		}
		html += '</div>';
	}

	out.innerHTML = html;
}

function initAdminMileage() {
	const navItems = $$('.sp-nav-item[data-nav="admin-mileage"], .sp-nav-child[data-nav="admin-mileage"]');
	if (navItems.length === 0) return;
	navItems.forEach(b => b.addEventListener('click', () => loadAdminMileage()));
}

async function loadPendingMileageCount() {
	if (!D.userCaps?.includes('manage_mileage') && !D.isGod) return;
	try {
		const res = await spAjax('site_pulse_get_pending_mileage_count', {});
		if (res.success) updateMileageBadges(res.data.count || 0);
	} catch (e) {}
}

function updateMileageBadges(count) {
	const targets = [
		document.querySelector('.sp-nav-child[data-nav="admin-mileage"] > span'),
		document.querySelector('.sp-nav-item[data-nav="admin"] .sp-nav-label'),
	].filter(Boolean);

	targets.forEach(host => {
		host.classList.add('sp-nav-label-with-badge');
		let badge = host.querySelector('.sp-nav-badge');
		if (count <= 0) {
			badge?.remove();
			host.classList.remove('sp-nav-label-with-badge');
			return;
		}
		if (!badge) {
			badge = document.createElement('span');
			badge.className = 'unique sp-nav-badge';
			host.appendChild(badge);
		}
		badge.textContent = String(count);
	});
}

async function loadAdminMileage() {
	const wrap = $('#sp-admin-mileage-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_mileage_locations', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading.</p>'; return; }

		const locs = res.data.locations || [];
		const rate = parseFloat(res.data.rate) || 0.67;
		const trailerRate = res.data.trailer_rate != null ? parseFloat(res.data.trailer_rate) : 0.10;
		const adminPurposes = res.data.purposes || [];
		const reminders = res.data.reminders || { enabled: false, hour: 7 };
		const requireApproval = res.data.require_approval !== false;
		const periodLength = parseInt(res.data.period_length) || 0;
		const periodAnchor = res.data.period_anchor || '';
		const apEmail = res.data.expense_ap_email || '';
		const pending = locs.filter(l => l.status === 'pending');
		const approved = locs.filter(l => l.status === 'approved');

		// Each settings segment sits in its own card (matches the report-template cards).
		const card = (title, actions = '') => `<div class="sp-card sp-settings-card"><div class="sp-card-header sp-header sp-settings-card-header"><h3>${title}</h3>${actions ? `<div class="sp-settings-card-actions">${actions}</div>` : ''}</div><div class="sp-settings-card-body">`;
		const cardEnd = '</div></div>';

		// Each settings segment is built into its own variable, then assembled in the admin's
		// preferred order on ONE line below — change that line to re-order the panel.
		let secRates = card('Rates');
		secRates += '<div class="sp-admin-mileage-rate">';
		secRates += '<label>Reimbursement rate ($/mile)</label>';
		secRates += `<input type="number" step="0.01" min="0" max="5" id="sp-mileage-rate-input" class="sp-input" value="${rate.toFixed(2)}">`;
		secRates += '<label title="Extra $/mile paid on legs where a trailer is pulled. Set 0 to disable.">Trailer rate (+$/mile)</label>';
		secRates += `<input type="number" step="0.01" min="0" max="5" id="sp-mileage-trailer-rate-input" class="sp-input" value="${trailerRate.toFixed(2)}">`;
		secRates += '</div>';
		secRates += cardEnd;

		// Pay periods — drives the report's "Jump to period" menu. Length 0 = calendar months.
		let secPeriods = card('Pay Periods');
		secPeriods += '<p class="sp-text-secondary">Define your reimbursement period and the report\'s “Jump to” menu will list each period instead of calendar months. Periods are extrapolated indefinitely from the start date below. Leave length at 0 to keep calendar months.</p>';
		secPeriods += '<div class="sp-admin-mileage-rate">';
		secPeriods += '<label title="Number of days each pay period spans, e.g. 30 for a 25th–24th cycle of a 30-day month.">How long is a period (days)?</label>';
		secPeriods += `<input type="number" step="1" min="0" max="366" id="sp-mileage-period-length" class="sp-input" value="${periodLength}">`;
		secPeriods += '<label title="Any date a period begins on. Earlier and later periods are calculated from this anchor.">When does a period start?</label>';
		secPeriods += `<input type="date" id="sp-mileage-period-anchor" class="sp-input" value="${esc(periodAnchor)}">`;
		secPeriods += '</div>';
		secPeriods += '<p class="sp-text-secondary" id="sp-mileage-period-preview"></p>';
		secPeriods += cardEnd;

		// Optional per-type map marker images (logo/icon). Blank = the default colored dot.
		const markerIcons = res.data.marker_icons || {};
		const MARKER_TYPES = [
			{ key: 'home', label: 'Home base' },
			{ key: 'restaurant', label: 'Restaurant' },
			{ key: 'vendor', label: 'Vendor' },
			{ key: 'office', label: 'Office' },
			{ key: 'other', label: 'Other' },
		];
		let secMarkers = card('Map Markers');
		secMarkers += '<p class="sp-text-secondary">Optional logo/icon image URL per location type, shown on the mileage map. Leave blank to use the default colored dot. A square image around 64–128px (PNG/SVG with transparency) works best.</p>';
		MARKER_TYPES.forEach(t => {
			secMarkers += '<div class="sp-form-group" style="max-width:560px;">';
			secMarkers += `<label>${t.label} marker image URL</label>`;
			secMarkers += `<input type="text" class="sp-input sp-marker-url" data-marker="${t.key}" value="${esc(markerIcons[t.key] || '')}" placeholder="https://rovin.work/wp-content/uploads/marker.png">`;
			secMarkers += '</div>';
		});
		secMarkers += cardEnd;

		// Destination approval policy.
		let secNewDest = card('New Destinations');
		secNewDest += '<p class="sp-text-secondary">Do new destinations need admin approval before being added to the database? When unchecked, a destination a driver adds goes straight into the database — geocoded and ready to use — with no approval step.</p>';
		secNewDest += '<div class="sp-toolbar">';
		secNewDest += `<label class="sp-reminder-toggle"><input type="checkbox" id="sp-mileage-require-approval"${requireApproval ? ' checked' : ''}> Require admin approval for new destinations</label>`;
		secNewDest += '</div>';
		secNewDest += cardEnd;

		// Import sits with the destination list it adds to, next to the other list-level actions.
		const destActions = '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-import-btn">Import Destinations</button>'
			+ '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-geocode-missing" title="Add map coordinates to any destinations that are missing them (e.g. the seeded restaurants)">Geocode Missing</button>'
			+ '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-mileage-add-dest">+ Add Destination</button>';
		let secDest = card('Destinations', destActions);

		// With approval off there's no pending queue, so hide that section — unless leftover
		// pending items remain from before it was switched off, so they can still be cleared.
		const showPending = requireApproval || pending.length > 0;
		if (showPending) {
			secDest += `<h4>Pending (${pending.length})</h4>`;
			if (pending.length === 0) {
				secDest += '<p class="sp-text-secondary">No pending locations.</p>';
			} else {
				secDest += '<table class="sp-table sp-mileage-table"><thead class="sp-thead"><tr><th>Name</th><th>Address</th><th>Type</th><th>Added by</th><th></th></tr></thead><tbody>';
				pending.forEach(l => {
					secDest += '<tr>';
					secDest += `<td>${esc(l.name)}</td>`;
					secDest += `<td>${esc(l.address || '')}</td>`;
					secDest += `<td>${esc(l.location_type)}</td>`;
					secDest += `<td>${esc(l.created_by_name || '')}</td>`;
					secDest += `<td class="sp-mileage-row-actions">`;
					secDest += `<button type="button" class="unique sp-btn sp-btn-primary sp-mileage-approve-btn" data-id="${l.id}">Approve</button>`;
					secDest += `<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-reject-btn" data-id="${l.id}">Reject</button>`;
					secDest += `</td></tr>`;
				});
				secDest += '</tbody></table>';
			}
		}

		// Only sub-label the approved list when a Pending section is also shown; otherwise the
		// "Destinations" header above is the only heading the single list needs.
		if (showPending) secDest += `<h4>Approved (${approved.length})</h4>`;
		secDest += '<table class="sp-table sp-mileage-table"><thead class="sp-thead"><tr><th>Name</th><th>Code</th><th>Address</th><th>Category</th><th>Business</th><th></th></tr></thead><tbody>';
		approved.forEach(l => {
			const priv = parseInt(l.is_private) ? ' <span class="unique sp-status-badge sp-status-draft">private</span>' : '';
			secDest += '<tr>';
			secDest += `<td>${esc(l.name)}${priv}</td>`;
			secDest += `<td>${esc(l.code || '')}</td>`;
			secDest += `<td>${esc(l.address || '')}</td>`;
			secDest += `<td>${esc(l.category || l.location_type || '')}</td>`;
			secDest += `<td>${parseInt(l.is_business) ? 'Business' : 'Personal'}</td>`;
			secDest += `<td>${iconBtn('edit', 'sp-mileage-edit-loc-btn', `data-id="${l.id}"`)}</td>`;
			secDest += '</tr>';
		});
		secDest += '</tbody></table>';
		secDest += cardEnd;

		// Daily reminder email (opt-in)
		const hourOpts = Array.from({ length: 24 }, (_, h) => {
			const label = h === 0 ? '12 AM' : h < 12 ? `${h} AM` : h === 12 ? '12 PM' : `${h - 12} PM`;
			return `<option value="${h}"${reminders.hour === h ? ' selected' : ''}>${label}</option>`;
		}).join('');
		let secReminder = card('Daily Reminder Email');
		secReminder += '<p class="sp-text-secondary">Emails active drivers each morning to log the previous day\'s miles (skips anyone who already logged it). Links straight to quick entry.</p>';
		secReminder += '<div class="sp-toolbar">';
		secReminder += `<label class="sp-reminder-toggle"><input type="checkbox" id="sp-mileage-reminder-enabled"${reminders.enabled ? ' checked' : ''}> Send daily reminders</label>`;
		secReminder += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Send at</span><select id="sp-mileage-reminder-hour" class="sp-select">${hourOpts}</select></div>`;
		secReminder += '</div>';
		secReminder += cardEnd;

		// Toll pricing (TollGuru). The secret key lives in god-only Site Settings; the
		// operational vehicle type + cost basis live here with the rest of mileage admin.
		const toll = res.data.toll || { key_set: false, vehicle_type: '2AxlesAuto', cost_basis: 'tag' };
		const vTypes = [
			['2AxlesAuto', 'Car / SUV / Pickup (2 axles)'], ['2AxlesTaxi', 'Taxi (2 axles)'], ['2AxlesEV', 'EV (2 axles)'],
			['2AxlesTruck', 'Truck — 2 axles'], ['3AxlesTruck', 'Truck — 3 axles'], ['4AxlesTruck', 'Truck — 4 axles'],
			['5AxlesTruck', 'Truck — 5 axles'], ['6AxlesTruck', 'Truck — 6 axles'],
		];
		let secToll = card('Tolls');
		const tollMode = toll.mode === 'ntta_csv' ? 'ntta_csv' : 'tollguru';
		secToll += '<p class="sp-text-secondary">Choose how tolls get onto a reimbursement. <strong>TollGuru</strong> auto-prices each leg where the driver checks the Toll box; <strong>NTTA CSV</strong> means you upload the toll statement and reconcile it against routes (from each driver’s expense report).</p>';
		secToll += '<div class="sp-toll-mode-choice" style="margin-bottom:14px;">';
		secToll += `<label class="sp-reminder-toggle" style="display:block;margin-bottom:6px;"><input type="radio" name="sp-toll-mode" value="tollguru"${tollMode === 'tollguru' ? ' checked' : ''}> Use Toll Guru for tolls</label>`;
		secToll += `<label class="sp-reminder-toggle" style="display:block;"><input type="radio" name="sp-toll-mode" value="ntta_csv"${tollMode === 'ntta_csv' ? ' checked' : ''}> Upload CSVs from NTTA for tolls</label>`;
		secToll += '</div>';

		// TollGuru configuration — shown only in TollGuru mode (the integration stays intact when off).
		secToll += `<div id="sp-toll-tollguru-config"${tollMode === 'ntta_csv' ? ' hidden' : ''}>`;
		secToll += `<p class="sp-text-secondary">${toll.key_set ? 'TollGuru API key: <strong>set &#10003;</strong>' : 'No TollGuru API key set yet — add the TollGuru key in <strong>Site Settings</strong> first.'}</p>`;
		secToll += '<div class="sp-toolbar">';
		secToll += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Vehicle</span><select id="sp-toll-vehicle" class="sp-select">${vTypes.map(([v, l]) => `<option value="${v}"${toll.vehicle_type === v ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></div>`;
		secToll += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Reimburse at</span><select id="sp-toll-basis" class="sp-select"><option value="tag"${toll.cost_basis !== 'cash' ? ' selected' : ''}>Tag / transponder rate</option><option value="cash"${toll.cost_basis === 'cash' ? ' selected' : ''}>Cash rate</option></select></div>`;
		secToll += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-toll-save">Save</button>';
		secToll += '</div>';
		if (D.isGod) {
			const tollLocOpts = approved.map(l => `<option value="${l.id}">${esc(l.name)}</option>`).join('');
			secToll += '<div class="sp-toolbar" style="margin-top:8px;">';
			secToll += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Test toll</span><select id="sp-toll-test-from" class="sp-select"><option value="">From…</option>${tollLocOpts}</select><select id="sp-toll-test-to" class="sp-select"><option value="">To…</option>${tollLocOpts}</select></div>`;
			secToll += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-toll-test">Test Toll API</button>';
			secToll += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-toll-test-polyline" title="Probe whether this key may price tolls along the EXACT Google route (polyline endpoint), instead of letting TollGuru re-route.">Test Polyline Tolls</button>';
			secToll += '</div>';
			secToll += '<div id="sp-toll-test-result" class="sp-mileage-api-test" hidden></div>';
		}
		secToll += '</div>'; // #sp-toll-tollguru-config

		// NTTA CSV mode — the reconciliation itself happens in each driver’s expense report.
		secToll += `<div id="sp-toll-ntta-config"${tollMode === 'tollguru' ? ' hidden' : ''}>`;
		secToll += '<p class="sp-text-secondary">Tolls are entered by uploading NTTA toll-statement CSVs and reconciling the charges against driver routes — that’s done from each driver’s <strong>expense report</strong>, not here. TollGuru auto-pricing is off in this mode.</p>';
		secToll += '</div>';
		secToll += cardEnd;

		// Distance matrix (lazy-rendered on demand — can be a large grid). Everything that fills or
		// diagnoses the grid lives on this card's header, beside the grid it acts on.
		let matrixActions = '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-recompute" title="Ask Google for every missing pair, and geocode any destination without map coordinates">Recompute All Distances</button>'
			+ '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-find-dupes" title="Find destinations that are really the same place, and merge them">Find Duplicates</button>';
		if (D.isGod) {
			matrixActions += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-test-api">Test Google API</button>';
		}
		let secMatrix = card('Distance Matrix', matrixActions);
		secMatrix += '<p class="sp-text-secondary">Cached miles between approved locations. Click any cell to set a manual value. Row and column headers stay pinned as you scroll — hover a truncated name to see it in full.</p>';
		secMatrix += '<p class="sp-matrix-legend"><b class="sp-matrix-key sp-matrix-api"></b> calculated by Google <b class="sp-matrix-key sp-matrix-manual"></b> manual override <b class="sp-matrix-key sp-matrix-missing"></b> not computed yet</p>';
		secMatrix += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-matrix-toggle">Show matrix</button>';
		secMatrix += '<div class="sp-mileage-matrix-wrap" id="sp-mileage-matrix" hidden></div>';
		if (D.isGod) {
			secMatrix += '<div id="sp-mileage-api-test-result" class="sp-mileage-api-test" hidden></div>';
		}
		secMatrix += '<div id="sp-mileage-dupes" class="sp-dupe-results" hidden></div>';
		secMatrix += cardEnd;

		// Purpose library editor
		let secPurpose = card('Trip Purpose Library');
		secPurpose += '<p class="sp-text-secondary">Suggestions shown to drivers when they pick a stop\'s business purpose. Tick <strong>Requires note</strong> to prompt the driver for extra detail (e.g. who they met) whenever they choose that purpose.</p>';
		secPurpose += '<div class="sp-purpose-rows" id="sp-mileage-purposes-list"></div>';
		secPurpose += '<div class="sp-mileage-purpose-add"><input type="text" id="sp-mileage-purpose-new" class="sp-input" placeholder="Add a purpose…"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-purpose-add-btn">Add</button></div>';
		secPurpose += cardEnd;

		// Chart of Accounts editor — the GL accounts (number + description) that feed the Other
		// Expenses account dropdown and the category account pickers below.
		let secAccounts = card('Chart of Accounts');
		secAccounts += '<p class="sp-text-secondary">Your GL accounts. These feed the account dropdown on <strong>Other Expenses</strong> and the account picker for each category below — shown to staff as “91200 Office Supplies”.</p>';
		secAccounts += '<div class="sp-exp-acct-rows" id="sp-exp-acct-list"></div>';
		secAccounts += '<div class="sp-exp-add-row"><input type="text" id="sp-exp-acct-num" class="sp-input sp-exp-acct-num-in" placeholder="Number (e.g. 91200)"><input type="text" id="sp-exp-acct-desc" class="sp-input" placeholder="Description (e.g. Office Supplies)"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-exp-acct-add-btn">Add</button></div>';
		secAccounts += cardEnd;

		// Expense Categories editor — the categories inside each section + the GL account each posts
		// to (from the chart above), plus which accounts Mileage posts to. Other Expenses has none.
		let secCategories = card('Expense Categories');
		secCategories += '<p class="sp-text-secondary">The categories staff pick inside each expense section, and the GL account each posts to. Classify these however your business needs. <strong>Other Expenses</strong> has no fixed categories — staff choose an account per line.</p>';
		secCategories += '<div id="sp-exp-cat-sections"></div>';
		secCategories += '<div class="sp-exp-mileage-accts"><h4>Mileage posts to</h4><div class="sp-exp-mileage-accts-grid" id="sp-exp-mileage-accts"></div></div>';
		secCategories += cardEnd;

		// Approval & accounting — the email address approved expense reports are sent to.
		let secApproval = card('Approval &amp; Accounting');
		secApproval += '<p class="sp-text-secondary">When a supervisor approves an expense report, the frozen PDF is emailed here. Leave blank to skip the email (reports still archive under <strong>Approved Reports</strong>).</p>';
		secApproval += '<div class="sp-admin-mileage-rate"><label for="sp-exp-ap-email">Accounting (AP) email</label>';
		secApproval += `<input type="email" id="sp-exp-ap-email" class="sp-input" placeholder="ap@example.com" value="${esc(apEmail)}">`;
		secApproval += '<span class="sp-help-text" id="sp-exp-ap-email-status" style="margin-left:10px;"></span></div>';
		secApproval += cardEnd;

		// Panel order (edit this line to re-arrange the Expense Reports settings sections):
		const html = secDest + secNewDest + secApproval + secAccounts + secCategories + secPurpose + secMatrix + secReminder + secToll + secRates + secPeriods + secMarkers;

		wrap.innerHTML = html;
		markUniqueSpans(wrap);

		// Purpose library: one row per purpose with an inline-edit name, a "Requires note" toggle,
		// and a delete button. Each purpose is { label, requires_note }. Auto-saves on every change.
		let purposeList = adminPurposes.map(p => (typeof p === 'string')
			? { label: p, requires_note: false }
			: { label: p.label || '', requires_note: !!p.requires_note });
		const savePurposes = async () => {
			// JSON string — spAjax can't serialize an array of objects through FormData.
			const r = await spAjax('site_pulse_admin_save_mileage_purposes', { purposes: JSON.stringify(purposeList) });
			if (r.success) spFlash('Purposes saved');
			else alert(r.data?.message || 'Error saving purposes.');
		};
		let editingIdx = -1; // index of the row whose name is being edited inline (-1 = none)
		const renderPurposeRows = () => {
			const box = $('#sp-mileage-purposes-list', wrap);
			if (!box) return;
			if (!purposeList.length) {
				box.innerHTML = '<span class="unique sp-text-secondary">No purposes yet.</span>';
				markUniqueSpans(box);
				return;
			}
			box.innerHTML = purposeList.map((p, i) => {
				const nameCell = (i === editingIdx)
					? `<input type="text" class="sp-input sp-purpose-edit-input" data-i="${i}" value="${esc(p.label)}">`
					: `<div class="sp-purpose-text">${esc(p.label)}</div>`;
				return `<div class="sp-purpose-row" data-i="${i}">`
					+ `<label class="sp-purpose-req unique" title="Prompt the driver for an additional note whenever they pick this purpose"><input type="checkbox" class="sp-purpose-req-cb" data-i="${i}"${p.requires_note ? ' checked' : ''}></label>`
					+ nameCell + iconBtn('edit', 'sp-purpose-edit', `data-i="${i}"`)
					+ iconBtn('delete', 'sp-purpose-del', `data-i="${i}"`) + '</div>';
			}).join('');
			markUniqueSpans(box);

			$$('.sp-purpose-del', box).forEach(b => b.addEventListener('click', () => {
				purposeList.splice(parseInt(b.dataset.i), 1);
				editingIdx = -1;
				renderPurposeRows();
				savePurposes();
			}));
			$$('.sp-purpose-edit', box).forEach(b => b.addEventListener('click', () => {
				editingIdx = parseInt(b.dataset.i);
				renderPurposeRows();
			}));
			$$('.sp-purpose-req-cb', box).forEach(cb => cb.addEventListener('change', () => {
				purposeList[parseInt(cb.dataset.i)].requires_note = cb.checked;
				savePurposes();
			}));

			const inp = $('.sp-purpose-edit-input', box);
			if (inp) {
				const commit = () => {
					const i = parseInt(inp.dataset.i);
					const v = (inp.value || '').trim();
					editingIdx = -1;
					// Cancel on empty or a name that already exists elsewhere; otherwise save.
					const dup = purposeList.some((p, j) => j !== i && p.label.toLowerCase() === v.toLowerCase());
					if (!v || dup) { renderPurposeRows(); return; }
					const changed = purposeList[i].label !== v;
					purposeList[i].label = v;
					renderPurposeRows();
					if (changed) savePurposes();
				};
				inp.addEventListener('keydown', (e) => {
					if (e.key === 'Enter') { e.preventDefault(); commit(); }
					else if (e.key === 'Escape') { e.preventDefault(); editingIdx = -1; renderPurposeRows(); }
				});
				inp.addEventListener('blur', commit);
				inp.focus();
				inp.select();
			}
		};
		renderPurposeRows();
		const addPurpose = () => {
			const inp = $('#sp-mileage-purpose-new', wrap);
			const v = (inp.value || '').trim();
			if (!v || purposeList.some(p => p.label.toLowerCase() === v.toLowerCase())) { inp.value = ''; return; }
			purposeList.push({ label: v, requires_note: false });
			inp.value = '';
			renderPurposeRows();
			savePurposes();
		};
		$('#sp-mileage-purpose-add-btn', wrap)?.addEventListener('click', addPurpose);
		$('#sp-mileage-purpose-new', wrap)?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addPurpose(); } });

		// ---- Expense config editors: Chart of Accounts, Categories, and mileage→account map ----
		let acctList = (res.data.expense_accounts || []).map(a => ({ number: String(a.number || ''), description: String(a.description || '') }));
		const CAT_SECTIONS = [['B', 'Vehicle Expenses'], ['C', 'Business Meals'], ['D', 'Competitive Shopping'], ['F', 'Travel Expenses']];
		const catSrc = res.data.expense_categories || {};
		let catArrays = {};
		Object.keys(catSrc).forEach(sec => {
			const cats = (catSrc[sec] && catSrc[sec].categories) || {};
			catArrays[sec] = Object.keys(cats).map(k => ({ key: k, label: cats[k].label || k, account: String(cats[k].account || '') }));
		});
		CAT_SECTIONS.forEach(([sec]) => { if (!catArrays[sec]) catArrays[sec] = []; });
		let mileageAcct = Object.assign({ reimbursement: '91310', tolls: '91310', trailer: '91310' }, res.data.expense_mileage_accounts || {});

		// <option> list for an account picker; keeps a saved-but-unlisted account selectable.
		const acctOptions = (selected) => {
			const v = selected == null ? '' : String(selected);
			let o = '<option value="">— account —</option>';
			acctList.forEach(a => {
				if (!a.number) return;
				const lbl = a.description ? `${a.number} ${a.description}` : a.number;
				o += `<option value="${esc(a.number)}"${v === String(a.number) ? ' selected' : ''}>${esc(lbl)}</option>`;
			});
			if (v && !acctList.some(a => String(a.number) === v)) o += `<option value="${esc(v)}" selected>${esc(v)}</option>`;
			return o;
		};

		const saveAccounts = async () => {
			const r = await spAjax('site_pulse_admin_save_expense_accounts', { accounts: JSON.stringify(acctList) });
			if (r.success) spFlash('Accounts saved'); else alert(r.data?.message || 'Error saving accounts.');
		};
		const saveCategories = async () => {
			const r = await spAjax('site_pulse_admin_save_expense_categories', { categories: JSON.stringify(catArrays) });
			if (r.success) spFlash('Categories saved'); else alert(r.data?.message || 'Error saving categories.');
		};
		const saveMileageAccts = async () => {
			const r = await spAjax('site_pulse_admin_save_mileage_accounts', { mileage_accounts: JSON.stringify(mileageAcct) });
			if (r.success) spFlash('Saved'); else alert(r.data?.message || 'Error.');
		};

		const renderAcctRows = () => {
			const box = $('#sp-exp-acct-list', wrap);
			if (!box) return;
			box.innerHTML = acctList.length ? acctList.map((a, i) =>
				`<div class="sp-exp-acct-row" data-i="${i}">`
				+ `<input type="text" class="sp-input sp-exp-acct-num-in sp-exp-acct-num-edit" data-i="${i}" value="${esc(a.number)}" placeholder="Number">`
				+ `<input type="text" class="sp-input sp-exp-acct-desc-edit" data-i="${i}" value="${esc(a.description)}" placeholder="Description">`
				+ iconBtn('delete', 'sp-exp-acct-del', `data-i="${i}"`) + '</div>').join('')
				: '<span class="unique sp-text-secondary">No accounts yet.</span>';
			markUniqueSpans(box);
			$$('.sp-exp-acct-num-edit', box).forEach(inp => inp.addEventListener('change', () => { acctList[+inp.dataset.i].number = inp.value.trim(); saveAccounts(); renderCatSections(); renderMileageAccts(); }));
			$$('.sp-exp-acct-desc-edit', box).forEach(inp => inp.addEventListener('change', () => { acctList[+inp.dataset.i].description = inp.value.trim(); saveAccounts(); renderCatSections(); renderMileageAccts(); }));
			$$('.sp-exp-acct-del', box).forEach(b => b.addEventListener('click', () => { acctList.splice(+b.dataset.i, 1); renderAcctRows(); renderCatSections(); renderMileageAccts(); saveAccounts(); }));
		};

		const renderCatSections = () => {
			const box = $('#sp-exp-cat-sections', wrap);
			if (!box) return;
			box.innerHTML = CAT_SECTIONS.map(([sec, label]) => {
				const rows = (catArrays[sec] || []).map((c, i) =>
					`<div class="sp-exp-cat-row" data-sec="${sec}" data-i="${i}">`
					+ `<input type="text" class="sp-input sp-exp-cat-label" data-sec="${sec}" data-i="${i}" value="${esc(c.label)}" placeholder="Category name">`
					+ `<select class="sp-select sp-exp-cat-acct" data-sec="${sec}" data-i="${i}">${acctOptions(c.account)}</select>`
					+ iconBtn('delete', 'sp-exp-cat-del', `data-sec="${sec}" data-i="${i}"`) + '</div>').join('');
				return `<div class="sp-exp-cat-group"><h4>${esc(label)}</h4><div class="sp-exp-cat-rows">${rows || '<span class="unique sp-text-secondary">No categories.</span>'}</div>`
					+ `<div class="sp-exp-add-row"><input type="text" class="sp-input sp-exp-cat-new" data-sec="${sec}" placeholder="Add a category…"><button type="button" class="unique sp-btn sp-btn-secondary sp-exp-cat-add" data-sec="${sec}">Add</button></div></div>`;
			}).join('') + '<div class="sp-exp-cat-group"><h4>Other Expenses</h4><p class="sp-text-secondary">No fixed categories — staff choose a GL account per line from the chart above.</p></div>';
			markUniqueSpans(box);
			$$('.sp-exp-cat-label', box).forEach(inp => inp.addEventListener('change', () => { catArrays[inp.dataset.sec][+inp.dataset.i].label = inp.value.trim(); saveCategories(); }));
			$$('.sp-exp-cat-acct', box).forEach(sel => sel.addEventListener('change', () => { catArrays[sel.dataset.sec][+sel.dataset.i].account = sel.value; saveCategories(); }));
			$$('.sp-exp-cat-del', box).forEach(b => b.addEventListener('click', () => { catArrays[b.dataset.sec].splice(+b.dataset.i, 1); renderCatSections(); saveCategories(); }));
			$$('.sp-exp-cat-add', box).forEach(b => b.addEventListener('click', () => {
				const inp = box.querySelector(`.sp-exp-cat-new[data-sec="${b.dataset.sec}"]`);
				const v = (inp.value || '').trim(); if (!v) return;
				catArrays[b.dataset.sec].push({ key: '', label: v, account: '' }); // empty key → server slugs it
				inp.value = ''; renderCatSections(); saveCategories();
			}));
			$$('.sp-exp-cat-new', box).forEach(inp => inp.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); box.querySelector(`.sp-exp-cat-add[data-sec="${inp.dataset.sec}"]`)?.click(); } }));
		};

		const renderMileageAccts = () => {
			const box = $('#sp-exp-mileage-accts', wrap);
			if (!box) return;
			const field = (k, label) => `<label class="sp-exp-mileage-acct"><span>${label}</span><select class="sp-select sp-exp-mileage-acct-sel" data-k="${k}">${acctOptions(mileageAcct[k])}</select></label>`;
			box.innerHTML = field('reimbursement', 'Mileage reimbursement') + field('tolls', 'Tolls') + field('trailer', 'Trailer');
			$$('.sp-exp-mileage-acct-sel', box).forEach(sel => sel.addEventListener('change', () => { mileageAcct[sel.dataset.k] = sel.value; saveMileageAccts(); }));
		};

		renderAcctRows();
		renderCatSections();
		renderMileageAccts();

		const addAcct = () => {
			const numI = $('#sp-exp-acct-num', wrap), descI = $('#sp-exp-acct-desc', wrap);
			const num = (numI.value || '').trim(); if (!num) return;
			if (acctList.some(a => a.number === num)) { numI.value = ''; descI.value = ''; return; }
			acctList.push({ number: num, description: (descI.value || '').trim() });
			numI.value = ''; descI.value = '';
			renderAcctRows(); renderCatSections(); renderMileageAccts(); saveAccounts();
		};
		$('#sp-exp-acct-add-btn', wrap)?.addEventListener('click', addAcct);
		$('#sp-exp-acct-desc', wrap)?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addAcct(); } });

		// Accounting (AP) email — save on change.
		$('#sp-exp-ap-email', wrap)?.addEventListener('change', async (e) => {
			const status = $('#sp-exp-ap-email-status', wrap);
			const r = await spAjax('site_pulse_admin_save_expense_ap_email', { email: e.target.value.trim() });
			if (r.success) { if (status) status.textContent = 'Saved.'; spFlash('Saved'); }
			else { if (status) status.textContent = ''; alert(r.data?.message || 'Error saving.'); }
		});

		// Edit an approved location's metadata.
		$$('.sp-mileage-edit-loc-btn', wrap).forEach(b => b.addEventListener('click', () => {
			const loc = locs.find(l => String(l.id) === String(b.dataset.id));
			if (loc) showEditLocationModal(loc, () => { loadAdminMileage(); });
		}));

		$('#sp-mileage-add-dest', wrap)?.addEventListener('click', () => showAdminAddLocationModal(() => loadAdminMileage()));

		$$('.sp-marker-url', wrap).forEach(inp => {
			inp.addEventListener('change', async () => {
				try {
					const r = await spAjax('site_pulse_admin_save_setting', { key: 'mileage_marker_' + inp.dataset.marker, value: inp.value.trim() });
					if (r.success) spFlash('Saved'); else alert(r.data?.message || 'Error saving.');
				} catch (err) { alert('Error saving marker.'); }
			});
		});

		$('#sp-mileage-geocode-missing', wrap)?.addEventListener('click', async (e) => {
			const btn = e.currentTarget;
			const orig = btn.textContent;
			btn.disabled = true; btn.textContent = 'Geocoding…';
			try {
				const res = await spAjax('site_pulse_admin_geocode_missing', {});
				if (res.success) { alert(res.data.message); loadAdminMileage(); }
				else { alert(res.data?.message || 'Error.'); btn.disabled = false; btn.textContent = orig; }
			} catch (err) { alert('Error geocoding destinations.'); btn.disabled = false; btn.textContent = orig; }
		});

		$('#sp-mileage-import-btn', wrap)?.addEventListener('click', () => showImportDestinationsModal(locs, () => loadAdminMileage()));

		// Approval toggle auto-saves, then re-renders so the Pending section appears/disappears.
		$('#sp-mileage-require-approval', wrap)?.addEventListener('change', async () => {
			const require_approval = $('#sp-mileage-require-approval')?.checked ? 1 : 0;
			const r = await spAjax('site_pulse_admin_save_mileage_approval', { require_approval });
			if (r.success) { spFlash(r.data.require_approval ? 'Approval required' : 'Approval off'); loadAdminMileage(); }
			else alert(r.data?.message || 'Error.');
		});

		const saveReminders = async () => {
			const enabled = $('#sp-mileage-reminder-enabled')?.checked ? 1 : 0;
			const hour = parseInt($('#sp-mileage-reminder-hour')?.value || '7');
			const r = await spAjax('site_pulse_admin_save_mileage_reminders', { enabled, hour });
			if (r.success) spFlash(r.data.enabled ? 'Reminders on' : 'Reminders off');
			else alert(r.data?.message || 'Error.');
		};
		$('#sp-mileage-reminder-enabled', wrap)?.addEventListener('change', saveReminders);
		$('#sp-mileage-reminder-hour', wrap)?.addEventListener('change', saveReminders);

		// Distance matrix: render on first show, then toggle.
		$('#sp-mileage-matrix-toggle', wrap)?.addEventListener('click', async (e) => {
			const box = $('#sp-mileage-matrix', wrap);
			if (!box) return;
			if (box.hidden) {
				e.target.textContent = 'Hide matrix';
				box.hidden = false;
				await renderMileageMatrix(box);
			} else {
				e.target.textContent = 'Show matrix';
				box.hidden = true;
			}
		});

		// Rate + trailer rate auto-save when either field is committed (blur / Enter / spinner).
		const saveMileageRates = async () => {
			const rate = parseFloat($('#sp-mileage-rate-input').value);
			if (isNaN(rate)) return;
			const trailer_rate = parseFloat($('#sp-mileage-trailer-rate-input')?.value);
			const payload = { rate };
			if (!isNaN(trailer_rate)) payload.trailer_rate = trailer_rate;
			const r = await spAjax('site_pulse_admin_save_mileage_rate', payload);
			if (r.success) spFlash('Saved');
			else alert(r.data?.message || 'Error.');
		};
		$('#sp-mileage-rate-input', wrap)?.addEventListener('change', saveMileageRates);
		$('#sp-mileage-trailer-rate-input', wrap)?.addEventListener('change', saveMileageRates);

		// Pay periods auto-save; a live preview confirms the extrapolated current period.
		const updatePeriodPreview = () => {
			const el = $('#sp-mileage-period-preview', wrap);
			if (!el) return;
			const len = parseInt($('#sp-mileage-period-length')?.value || '0') || 0;
			const anc = $('#sp-mileage-period-anchor')?.value || '';
			if (!(len > 0 && anc)) { el.textContent = 'Using calendar months.'; return; }
			const ps = computeMileagePeriods(anc, len, 0, 1); // [next, current]
			const cur = ps.find(p => p.current), next = ps.find(p => !p.current);
			el.textContent = cur
				? `Current period: ${mPeriodLabel(cur.startDate, cur.endDate)}`
					+ (next ? `  ·  next starts ${MILEAGE_MONTH_ABBR[next.startDate.getMonth()]} ${next.startDate.getDate()}` : '')
				: '';
		};
		const savePeriods = async () => {
			const period_length = parseInt($('#sp-mileage-period-length')?.value || '0') || 0;
			const period_anchor = $('#sp-mileage-period-anchor')?.value || '';
			const r = await spAjax('site_pulse_admin_save_mileage_periods', { period_length, period_anchor });
			if (r.success) { spFlash('Saved'); updatePeriodPreview(); }
			else alert(r.data?.message || 'Error.');
		};
		$('#sp-mileage-period-length', wrap)?.addEventListener('change', savePeriods);
		$('#sp-mileage-period-anchor', wrap)?.addEventListener('change', savePeriods);
		updatePeriodPreview();

		// Toll method radio — persist immediately and toggle which config block is shown.
		$$('input[name="sp-toll-mode"]', wrap).forEach(radio => radio.addEventListener('change', async () => {
			const mode = wrap.querySelector('input[name="sp-toll-mode"]:checked')?.value === 'ntta_csv' ? 'ntta_csv' : 'tollguru';
			const tg = $('#sp-toll-tollguru-config', wrap), nt = $('#sp-toll-ntta-config', wrap);
			if (tg) tg.hidden = (mode !== 'tollguru');
			if (nt) nt.hidden = (mode === 'tollguru');
			const r = await spAjax('site_pulse_admin_save_toll_settings', { mode });
			if (r.success) spFlash('Toll method saved');
			else alert(r.data?.message || 'Error saving toll method.');
		}));

		$('#sp-toll-save', wrap)?.addEventListener('click', async () => {
			const vehicle_type = $('#sp-toll-vehicle')?.value || '2AxlesAuto';
			const cost_basis   = $('#sp-toll-basis')?.value || 'tag';
			const r = await spAjax('site_pulse_admin_save_toll_settings', { vehicle_type, cost_basis });
			if (r.success) spFlash('Toll settings saved');
			else alert(r.data?.message || 'Error saving toll settings.');
		});

		$('#sp-toll-test', wrap)?.addEventListener('click', async (e) => {
			const btn = e.currentTarget, out = $('#sp-toll-test-result', wrap);
			btn.disabled = true;
			if (out) { out.hidden = false; out.textContent = 'Testing…'; }
			try {
				const from = $('#sp-toll-test-from')?.value || '';
				const to = $('#sp-toll-test-to')?.value || '';
				const r = await spAjax('site_pulse_admin_test_toll_api', { from, to });
				const d = (r && r.data) || {};
				if (out) {
					if (d.error) {
						let h = '⚠ ' + esc(d.error);
						if (d.attempts && d.attempts.length) {
							h += '<div style="margin-top:8px;font-family:monospace;font-size:12px;white-space:pre-wrap;line-height:1.4;">';
							d.attempts.forEach(a => { h += `\n${esc(a.endpoint)}\n  HTTP ${esc(String(a.status))}: ${esc(String(a.body || '').slice(0, 400))}\n`; });
							h += `\n(polyline length: ${d.polyline_len || 0}, vehicle: ${esc(d.vehicle_type || '')})</div>`;
						}
						out.innerHTML = h;
					} else {
						let h = `&#10003; Key works (${esc(d.key_preview || '')}) via <code>${esc((d.endpoint || '').split('/').pop())}</code>. ${esc(d.from || '')} &rarr; ${esc(d.to || '')} [${esc(d.vehicle_type || '')}]: tag $${(+d.tag || 0).toFixed(2)} &middot; cash $${(+d.cash || 0).toFixed(2)} &middot; ${d.plaza_count || 0} plaza(s).`;
						// List the toll plazas TollGuru routed through, in order.
						if (Array.isArray(d.plazas) && d.plazas.length) {
							h += '<ol style="margin:8px 0 0;padding-left:20px;line-height:1.5;">';
							d.plazas.forEach(name => { h += `<li>${esc(name)}</li>`; });
							h += '</ol>';
						}
						// When the price comes back $0 despite plazas, show the raw fields so we can
						// see whether TollGuru withheld pricing or used a different field name.
						if ((+d.tag || 0) === 0 && (+d.cash || 0) === 0 && (d.plaza_count || 0) > 0) {
							h += '<div style="margin-top:8px;font-family:monospace;font-size:12px;white-space:pre-wrap;line-height:1.4;">';
							h += 'route costs: ' + esc(JSON.stringify(d.raw_costs)) + '\n\nsample plaza: ' + esc(JSON.stringify(d.sample_toll, null, 1));
							h += '</div>';
						}
						out.innerHTML = h;
					}
				}
			} catch (err) { if (out) out.textContent = 'Error running test.'; }
			btn.disabled = false;
		});

		// Probe the polyline endpoint to learn whether this key can price the EXACT route.
		$('#sp-toll-test-polyline', wrap)?.addEventListener('click', async (e) => {
			const btn = e.currentTarget, out = $('#sp-toll-test-result', wrap);
			btn.disabled = true;
			if (out) { out.hidden = false; out.textContent = 'Probing polyline endpoint…'; }
			try {
				const from = $('#sp-toll-test-from')?.value || '';
				const to = $('#sp-toll-test-to')?.value || '';
				const r = await spAjax('site_pulse_admin_test_toll_polyline', { from, to });
				const d = (r && r.data) || {};
				if (out) {
					let h;
					if (d.error) {
						h = '⚠ ' + esc(d.error);
					} else if (d.authorized) {
						h = `&#10003; <strong>Polyline endpoint is AUTHORIZED</strong> on this key (${esc(d.key_preview || '')}) — no upgrade needed. `;
						h += `${esc(d.from || '')} &rarr; ${esc(d.to || '')} [${esc(d.vehicle_type || '')}]: tag $${(+d.tag || 0).toFixed(2)} &middot; cash $${(+d.cash || 0).toFixed(2)} &middot; ${(d.plazas || []).length} plaza(s) along the exact Google route.`;
						if (Array.isArray(d.plazas) && d.plazas.length) {
							h += '<ol style="margin:8px 0 0;padding-left:20px;line-height:1.5;">';
							d.plazas.forEach(name => { h += `<li>${esc(name)}</li>`; });
							h += '</ol>';
						}
					} else if (d.gated) {
						h = `&#10007; <strong>Polyline endpoint is NOT available on this key</strong> (HTTP ${esc(String(d.status))}) — an upgrade would be required. The basic origin→destination pricing keeps working.`;
						if (d.message) h += `<div style="margin-top:6px;">TollGuru says: ${esc(String(d.message))}</div>`;
					} else {
						h = `&#8505; Inconclusive — HTTP ${esc(String(d.status))} from <code>${esc(d.endpoint || '')}</code> (polyline ${d.polyline_len || 0} chars).`;
						if (d.message) h += `<div style="margin-top:6px;">Message: ${esc(String(d.message))}</div>`;
					}
					// Always show the raw response for diagnosis on a non-authorized result.
					if (!d.authorized && d.body) {
						h += '<div style="margin-top:8px;font-family:monospace;font-size:12px;white-space:pre-wrap;line-height:1.4;">' + esc(String(d.body)) + '</div>';
					}
					out.innerHTML = h;
				}
			} catch (err) { if (out) out.textContent = 'Error running polyline test.'; }
			btn.disabled = false;
		});

		$('#sp-mileage-recompute', wrap)?.addEventListener('click', async (e) => {
			// The server handles a few locations per request (two Google round trips each), so walk the
			// batches here rather than asking it to do the whole list in one shot and time out mid-run.
			const btn = e.target;
			btn.disabled = true;
			btn.textContent = 'Computing…';

			let offset = 0, added = 0, geocoded = 0, failed = 0, apiError = '', error = '', guard = 0;
			for (;;) {
				const r = await spAjax('site_pulse_admin_recompute_mileage_distances', { offset });
				if (!r.success) { error = r.data?.message || 'Error.'; break; }
				added += r.data.distances_added;
				geocoded += r.data.geocoded;
				failed += r.data.failed || 0;
				if (r.data.error) apiError = r.data.error;
				offset = r.data.next_offset;
				btn.textContent = `Computing… ${offset}/${r.data.total}`;
				if (r.data.done || ++guard > 500) break;
			}

			btn.disabled = false;
			btn.textContent = 'Recompute All Distances';
			if (error) { alert(error); return; }

			// A run that stores nothing used to look like "everything was already up to date". Say so
			// when Google actually rejected the calls, and quote its reason.
			let msg = `${added} distance${added === 1 ? '' : 's'} stored across ${offset} location${offset === 1 ? '' : 's'}.`;
			if (geocoded) msg += `\n${geocoded} location${geocoded === 1 ? '' : 's'} geocoded.`;
			if (failed) {
				msg += `\n\n⚠ Google returned no distances for ${failed} location${failed === 1 ? '' : 's'}.`;
				if (apiError) msg += `\nGoogle said: ${apiError}`;
				msg += '\n\nUse "Test Google API" on this card to check the key and that the Routes API is enabled.';
			}
			alert(msg);

			const box = $('#sp-mileage-matrix', wrap);
			if (box && !box.hidden) renderMileageMatrix(box);
		});

		$('#sp-mileage-find-dupes', wrap)?.addEventListener('click', async (e) => {
			const box = $('#sp-mileage-dupes', wrap);
			if (!box) return;
			e.target.disabled = true;
			box.hidden = false;
			box.innerHTML = '<div class="sp-loading"></div>';
			const r = await spAjax('site_pulse_admin_find_mileage_duplicates', {});
			e.target.disabled = false;
			if (!r.success) { box.innerHTML = '<p class="sp-text-warning">Could not check for duplicates.</p>'; return; }
			renderMileageDuplicates(box, r.data.groups || []);
		});

		$('#sp-mileage-test-api', wrap)?.addEventListener('click', async (e) => {
			const out = $('#sp-mileage-api-test-result');
			e.target.disabled = true;
			e.target.textContent = 'Testing…';
			out.hidden = false;
			out.innerHTML = '<div class="sp-loading"></div>';
			const r = await spAjax('site_pulse_admin_test_mileage_api', {});
			e.target.disabled = false;
			e.target.textContent = 'Test Google API';
			if (!r.success) { out.innerHTML = `<p class="sp-text-warning">Request failed.</p>`; return; }
			renderApiTestResult(out, r.data);
		});

		$$('.sp-mileage-approve-btn', wrap).forEach(b => b.addEventListener('click', async () => {
			b.disabled = true;
			b.textContent = 'Approving…';
			const r = await spAjax('site_pulse_admin_approve_mileage_location', { id: b.dataset.id });
			if (r.success) { loadAdminMileage(); loadPendingMileageCount(); }
			else { alert(r.data?.message || 'Error.'); b.disabled = false; b.textContent = 'Approve'; }
		}));
		$$('.sp-mileage-reject-btn', wrap).forEach(b => b.addEventListener('click', async () => {
			if (!confirm('Reject this location? Any legs referencing it will be removed.')) return;
			const r = await spAjax('site_pulse_admin_reject_mileage_location', { id: b.dataset.id });
			if (r.success) { loadAdminMileage(); loadPendingMileageCount(); }
			else alert(r.data?.message || 'Error.');
		}));
	} catch (err) {
		wrap.innerHTML = '<p>Error loading.</p>';
	}
}

/* ---- Destination discovery: import candidates from Google Timeline JSON or MileIQ CSV ----
   Ports the prototype's timeline-reviewer: best-effort client-side parse → review/edit →
   bulk add as approved locations. Parsing is heuristic; the admin reviews before adding. */

function parseCsvLine(line) {
	const out = []; let cur = '', q = false;
	for (let i = 0; i < line.length; i++) {
		const c = line[i];
		if (q) {
			if (c === '"' && line[i + 1] === '"') { cur += '"'; i++; }
			else if (c === '"') q = false;
			else cur += c;
		} else if (c === '"') q = true;
		else if (c === ',') { out.push(cur); cur = ''; }
		else cur += c;
	}
	out.push(cur);
	return out.map(s => s.trim());
}

function parseMileIQCsv(text) {
	const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
	if (lines.length < 2) return [];
	// Header cells often wrapped in Excel ="..." — strip to bare names.
	const header = parseCsvLine(lines[0]).map(h => h.replace(/^="?|"?$/g, '').replace(/\*/g, '').trim().toUpperCase());
	const find = (...names) => header.findIndex(h => names.includes(h));
	const cols = [find('START', 'FROM', 'START LOCATION'), find('STOP', 'TO', 'END', 'END LOCATION')].filter(i => i >= 0);
	if (!cols.length) return [];
	const seen = new Set(), out = [];
	for (let i = 1; i < lines.length; i++) {
		const cells = parseCsvLine(lines[i]).map(c => c.replace(/^="?|"?$/g, '').trim());
		cols.forEach(ci => {
			const v = cells[ci] || '';
			if (!v || /^total/i.test(v)) return;
			const k = v.toLowerCase();
			if (seen.has(k)) return;
			seen.add(k);
			out.push({ name: v, address: v, lat: '', lng: '' });
		});
	}
	return out;
}

function parseTimelineJson(obj) {
	const out = [], seen = new Set();
	const push = (name, address, lat, lng, placeId) => {
		const k = placeId || `${name}|${lat},${lng}`;
		if (!k || seen.has(k)) return;
		seen.add(k);
		out.push({ name: name || '', address: address || '', lat: lat ?? '', lng: lng ?? '' });
	};
	const geo = (s) => { // "geo:lat,lng" or "lat,lng"
		if (typeof s !== 'string') return [null, null];
		const m = s.replace(/^geo:/, '').split(',').map(parseFloat);
		return (m.length === 2 && !isNaN(m[0])) ? m : [null, null];
	};
	// Newer export: semanticSegments[].visit.topCandidate
	(obj.semanticSegments || []).forEach(seg => {
		const tc = seg.visit?.topCandidate;
		if (!tc) return;
		const [lat, lng] = geo(tc.placeLocation?.latLng || tc.placeLocation);
		push(tc.placeName || tc.semanticType || '', tc.address || '', lat, lng, tc.placeId);
	});
	// Legacy export: timelineObjects[].placeVisit.location
	(obj.timelineObjects || []).forEach(o => {
		const loc = o.placeVisit?.location;
		if (!loc) return;
		const lat = loc.latitudeE7 != null ? loc.latitudeE7 / 1e7 : null;
		const lng = loc.longitudeE7 != null ? loc.longitudeE7 / 1e7 : null;
		push(loc.name || '', loc.address || '', lat, lng, loc.placeId);
	});
	return out;
}

function showImportDestinationsModal(existingLocs, onSaved) {
	const existing = $('#sp-mileage-import-modal');
	if (existing) existing.remove();
	const have = new Set((existingLocs || []).map(l => (l.name || '').toLowerCase()));

	const modal = document.createElement('div');
	modal.id = 'sp-mileage-import-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal sp-modal-wide">
			<h3>Import Destinations</h3>
			<p class="sp-text-secondary">Upload a Google Timeline <code>.json</code> or a MileIQ <code>.csv</code>. Candidates are parsed for review — edit names, untick any you don't want, then add. New ones are geocoded automatically.</p>
			<input type="file" id="sp-import-file" accept=".json,.csv" class="sp-input">
			<div id="sp-import-candidates" class="sp-import-candidates"></div>
			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-import-add" disabled>Add Selected</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-import-cancel">Cancel</button>
			</div>
		</div>
	`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);
	const close = () => modal.remove();
	modal.querySelector('#sp-import-cancel').addEventListener('click', close);
	modal.addEventListener('click', (e) => { if (e.target === modal) close(); });

	let candidates = [];
	const box = modal.querySelector('#sp-import-candidates');
	const addBtn = modal.querySelector('#sp-import-add');

	const renderCandidates = () => {
		if (!candidates.length) { box.innerHTML = '<p class="sp-text-secondary">No candidates found in that file.</p>'; addBtn.disabled = true; return; }
		let h = `<p class="sp-text-secondary">${candidates.length} candidate${candidates.length !== 1 ? 's' : ''} — already-known names are unticked by default.</p>`;
		h += '<table class="sp-table sp-mileage-table"><thead class="sp-thead"><tr><th></th><th>Name</th><th>Address</th></tr></thead><tbody>';
		candidates.forEach((c, i) => {
			const dup = have.has((c.name || '').toLowerCase());
			h += '<tr>';
			h += `<td><input type="checkbox" class="sp-import-pick" data-i="${i}" ${dup ? '' : 'checked'}></td>`;
			h += `<td><input type="text" class="sp-input sp-import-name" data-i="${i}" value="${esc(c.name)}">${dup ? ' <span class="unique sp-text-secondary">(exists)</span>' : ''}</td>`;
			h += `<td><input type="text" class="sp-input sp-import-addr" data-i="${i}" value="${esc(c.address)}"></td>`;
			h += '</tr>';
		});
		h += '</tbody></table>';
		box.innerHTML = h;
		markUniqueSpans(box);
		addBtn.disabled = false;
	};

	modal.querySelector('#sp-import-file').addEventListener('change', (e) => {
		const file = e.target.files[0];
		if (!file) return;
		const reader = new FileReader();
		reader.onload = () => {
			try {
				if (/\.json$/i.test(file.name)) candidates = parseTimelineJson(JSON.parse(reader.result));
				else candidates = parseMileIQCsv(String(reader.result));
			} catch (err) { candidates = []; alert('Could not parse that file.'); }
			renderCandidates();
		};
		reader.readAsText(file);
	});

	addBtn.addEventListener('click', async () => {
		// Read back edited values + selection.
		const picks = $$('.sp-import-pick', box);
		const items = [];
		picks.forEach(cb => {
			if (!cb.checked) return;
			const i = cb.dataset.i;
			const name = box.querySelector(`.sp-import-name[data-i="${i}"]`)?.value.trim() || '';
			const addr = box.querySelector(`.sp-import-addr[data-i="${i}"]`)?.value.trim() || '';
			if (name) items.push({ name, address: addr, lat: candidates[i].lat, lng: candidates[i].lng });
		});
		if (!items.length) { alert('Nothing selected.'); return; }
		addBtn.disabled = true; addBtn.textContent = 'Adding…';
		const r = await spAjax('site_pulse_admin_bulk_add_mileage_locations', { items: JSON.stringify(items) });
		if (r.success) { alert(`Added ${r.data.added} location(s).`); close(); if (onSaved) onSaved(); }
		else { alert(r.data?.message || 'Error.'); addBtn.disabled = false; addBtn.textContent = 'Add Selected'; }
	});
}

// Duplicate destinations, grouped, with the survivor chosen per group. Merging rewrites trip
// history onto the kept record, so the choice is always explicit — never a bulk "merge all".
function renderMileageDuplicates(box, groups) {
	if (!groups.length) {
		box.innerHTML = '<p class="sp-text-secondary">No duplicate destinations found.</p>';
		return;
	}

	// Default to the record most expensive to lose: the one carrying the store link (home-base
	// resolution runs through it), then the one with the most trip history, then the original.
	const bestOf = (rows) => rows.slice().sort((a, b) =>
		(b.store_linked - a.store_linked) || (b.legs - a.legs) || (a.id - b.id))[0];

	let html = `<p class="sp-text-secondary">${groups.length} possible duplicate set${groups.length === 1 ? '' : 's'} found. Pick the record to <strong>keep</strong> in each set — the others are folded into it, carrying their trips, cached miles and home-base assignments with them.</p>`;

	groups.forEach((rows, gi) => {
		const keep = bestOf(rows);
		html += `<div class="sp-dupe-group" data-group="${gi}">`;
		rows.forEach(r => {
			const tags = [];
			if (r.store_linked) tags.push('linked to a store');
			if (r.is_private) tags.push('private home');
			if (r.status !== 'approved') tags.push(esc(r.status));
			if (!r.geocoded) tags.push('not geocoded');
			if (r.legs) tags.push(`${r.legs} trip leg${r.legs === 1 ? '' : 's'}`);
			if (r.home_for) tags.push(`home base for ${r.home_for}`);
			html += `<label class="sp-dupe-option">
				<input type="radio" name="sp-dupe-keep-${gi}" value="${r.id}"${r.id === keep.id ? ' checked' : ''}>
				<b class="sp-dupe-name">${esc(r.name)}</b>
				<small class="sp-dupe-addr">${esc(r.address || 'No address')}</small>
				${tags.length ? `<small class="sp-dupe-tags">${tags.join(' · ')}</small>` : ''}
			</label>`;
		});
		html += `<button type="button" class="unique sp-btn sp-btn-secondary sp-dupe-merge" data-group="${gi}">Merge this set</button>`;
		html += '</div>';
	});

	box.innerHTML = html;
	markUniqueSpans(box);

	$$('.sp-dupe-merge', box).forEach(btn => btn.addEventListener('click', async () => {
		const gi = btn.dataset.group;
		const rows = groups[gi];
		const picked = box.querySelector(`input[name="sp-dupe-keep-${gi}"]:checked`);
		if (!picked) { alert('Pick which record to keep.'); return; }

		const survivorId = parseInt(picked.value, 10);
		const survivor = rows.find(r => r.id === survivorId);
		const losers = rows.filter(r => r.id !== survivorId);
		const legs = losers.reduce((n, r) => n + r.legs, 0);

		const ok = confirm(
			`Keep "${survivor.name}" and merge ${losers.length} other record${losers.length === 1 ? '' : 's'} into it:\n\n` +
			losers.map(r => `  • ${r.name}`).join('\n') +
			`\n\n${legs} trip leg${legs === 1 ? '' : 's'} will be repointed to "${survivor.name}". This cannot be undone.`
		);
		if (!ok) return;

		btn.disabled = true;
		btn.textContent = 'Merging…';
		const r = await spAjax('site_pulse_admin_merge_mileage_locations', {
			survivor_id: survivorId,
			loser_ids: losers.map(l => l.id),
		});
		if (!r.success) {
			alert(r.data?.message || 'Error.');
			btn.disabled = false;
			btn.textContent = 'Merge this set';
			return;
		}

		const d = r.data;
		alert(`Merged ${d.merged} record${d.merged === 1 ? '' : 's'} into "${d.survivor}".\n` +
			`${d.legs_repointed} trip leg${d.legs_repointed === 1 ? '' : 's'} repointed, ` +
			`${d.entries_recalced} day${d.entries_recalced === 1 ? '' : 's'} recalculated.`);

		// Re-detect rather than patching the DOM — the merge may have collapsed other sets too.
		box.innerHTML = '<div class="sp-loading"></div>';
		const again = await spAjax('site_pulse_admin_find_mileage_duplicates', {});
		renderMileageDuplicates(box, again.success ? (again.data.groups || []) : []);

		const matrix = $('#sp-mileage-matrix');
		if (matrix && !matrix.hidden) renderMileageMatrix(matrix);
	}));
}

// Renders the editable distance-matrix grid into `box`.
async function renderMileageMatrix(box) {
	box.innerHTML = '<div class="sp-loading"></div>';
	const res = await spAjax('site_pulse_admin_get_mileage_matrix', {});
	if (!res.success) { box.innerHTML = '<p class="sp-text-warning">Could not load the matrix.</p>'; return; }

	const locs = res.data.locations || [];
	const dist = res.data.distances || {};
	if (locs.length < 2) { box.innerHTML = '<p class="sp-text-secondary">Need at least two approved locations.</p>'; return; }

	const key = (a, b) => (a <= b ? `${a}-${b}` : `${b}-${a}`);

	let html = '<table class="sp-table sp-matrix-table"><thead class="sp-thead"><tr><th class="sp-matrix-corner"></th>';
	locs.forEach(c => { html += `<th scope="col" title="${esc(c.name)}">${esc(c.name)}</th>`; });
	html += '</tr></thead><tbody>';
	locs.forEach(rLoc => {
		html += `<tr><th scope="row" class="sp-matrix-rowhead" title="${esc(rLoc.name)}">${esc(rLoc.name)}</th>`;
		locs.forEach(cLoc => {
			if (rLoc.id === cLoc.id) { html += '<td class="sp-matrix-self">—</td>'; return; }
			const d = dist[key(parseInt(rLoc.id), parseInt(cLoc.id))];
			const cls = !d ? 'sp-matrix-missing' : (d.source === 'manual' ? 'sp-matrix-manual' : 'sp-matrix-api');
			const val = d ? d.miles.toFixed(1) : '+';
			const tip = d
				? `${rLoc.name} ↔ ${cLoc.name}: ${d.miles.toFixed(1)} mi (${d.source === 'manual' ? 'manual' : 'Google'})`
				: `${rLoc.name} ↔ ${cLoc.name}: not computed yet — click to enter manually`;
			html += `<td class="sp-matrix-cell ${cls}" title="${esc(tip)}" data-from="${rLoc.id}" data-to="${cLoc.id}" data-from-name="${esc(rLoc.name)}" data-to-name="${esc(cLoc.name)}" data-miles="${d ? d.miles : ''}">${val}</td>`;
		});
		html += '</tr>';
	});
	html += '</tbody></table>';
	box.innerHTML = html;

	$$('.sp-matrix-cell', box).forEach(td => td.addEventListener('click', () => {
		showMatrixCellModal(td.dataset, () => renderMileageMatrix(box));
	}));
}

// Small editor for one matrix cell — manual override of a pair's miles (symmetric).
function showMatrixCellModal(d, onSaved) {
	const existing = $('#sp-matrix-cell-modal');
	if (existing) existing.remove();
	const modal = document.createElement('div');
	modal.id = 'sp-matrix-cell-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal">
			<h3>Set distance</h3>
			<p class="sp-text-secondary">${esc(d.fromName)} ↔ ${esc(d.toName)}</p>
			<div class="sp-form-group"><label>Miles</label><input type="number" step="0.1" min="0" id="sp-matrix-miles" class="sp-input" value="${d.miles || ''}"></div>
			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-matrix-save">Save</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-matrix-cancel">Cancel</button>
			</div>
		</div>
	`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);
	const close = () => modal.remove();
	modal.querySelector('#sp-matrix-cancel').addEventListener('click', close);
	modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
	modal.querySelector('#sp-matrix-save').addEventListener('click', async () => {
		const miles = parseFloat(modal.querySelector('#sp-matrix-miles').value);
		if (isNaN(miles) || miles < 0) { alert('Enter a valid mileage.'); return; }
		const r = await spAjax('site_pulse_admin_save_mileage_distance', { from_id: d.from, to_id: d.to, miles });
		if (r.success) { close(); if (onSaved) onSaved(); }
		else alert(r.data?.message || 'Error saving.');
	});
}

})();
