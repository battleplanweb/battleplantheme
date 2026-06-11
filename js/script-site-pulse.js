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

// kind: 'edit' | 'delete'. cls = extra functional class(es); attrs = raw attribute string (data-*, disabled…).
function iconBtn(kind, cls = '', attrs = '') {
	const del = kind === 'delete';
	return `<button type="button" class="unique sp-icon-btn ${del ? 'sp-icon-delete' : 'sp-icon-edit'} ${cls}" title="${del ? 'Delete' : 'Edit'}" aria-label="${del ? 'Delete' : 'Edit'}" ${attrs}>${del ? ICON_DELETE : ICON_EDIT}</button>`;
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
	initReports();
	initReview();
	initAdmin();
	initMileage();
	initAdminMileage();
	restoreViewState();
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
				group.classList.toggle('expanded');
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
			activatePanel(nav);
			// Highlight the child that was actually clicked (several children can share a panel
			// slug, e.g. GM Reports / Supervisor Reports both use reports-review).
			$$('.sp-nav-child').forEach(b => b.classList.remove('active'));
			btn.classList.add('active');
			closeMobileSidebar();
			// Action children open a form / switch the review type (after their panel is active).
			const action = btn.dataset.action;
			if (action === 'new-report') showReportForm();
			else if (action === 'mileage-add') showMileageForm();
			else if (action === 'review-gm') { reviewReportType = 'manager'; closeReportDetail('review'); loadReviewReports(); }
			else if (action === 'review-sup') { reviewReportType = 'supervisor'; closeReportDetail('review'); loadReviewReports(); }
		});
	});
}

function activatePanel(panelId) {
	$$('.sp-panel').forEach(p => p.classList.remove('active'));
	const panel = $(`#sp-panel-${panelId}`);
	if (panel) panel.classList.add('active');

	$$('.sp-nav-item').forEach(b => b.classList.remove('active'));
	$$('.sp-nav-child').forEach(b => b.classList.remove('active'));

	const navBtn = $(`[data-nav="${panelId}"]`);
	if (navBtn) {
		navBtn.classList.add('active');

		const group = navBtn.closest('.sp-nav-group');
		if (group) {
			group.classList.add('expanded');
			const parentBtn = group.querySelector('.sp-nav-item');
			if (parentBtn) parentBtn.classList.add('active');
		}
	}

	if (panelId === 'analytics') {
		populateAnalyticsFilters();
		loadAnalytics(true);
	}
	if (panelId === 'mileage-map') {
		loadMileageMap();
	}
	if (panelId === 'mileage-tolls') {
		initTollReconcile();
	}
	// Returning to a list view closes any open form/detail in that section, so the nav
	// item always lands on the main list (e.g. My Mileage while on Add-a-Day).
	if (panelId === 'mileage' && typeof hideMileageForm === 'function') hideMileageForm();
	if (panelId === 'reports-my' && typeof hideReportForm === 'function') hideReportForm();

	saveViewState({ panel: panelId });
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
}

async function loadNotificationCount() {
	try {
		const res = await spAjax('site_pulse_get_unread_count', {});
		if (res.success) {
			updateNotificationBadge(res.data.count || 0);
		}
	} catch (err) {}
}

function updateNotificationBadge(count) {
	const badge = $('#sp-notification-badge');
	if (!badge) return;
	if (count > 0) {
		badge.textContent = count > 99 ? '99+' : count;
		badge.hidden = false;
	} else {
		badge.hidden = true;
	}
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

				// Mark this notification as read
				if (item.classList.contains('unread')) {
					try {
						await spAjax('site_pulse_mark_notification_read', { id: id });
						item.classList.remove('unread');
						loadNotificationCount();
					} catch (err) {}
				}

				// Navigate to the related content
				$('#sp-notification-panel').hidden = true;
				const ntype = item.dataset.ntype || '';

				if (ntype === 'action_items' || ntype === 'action_resolved' || relatedType === 'action_item') {
					activatePanel('action-items');
				} else if (relatedType === 'report' && relatedId) {
					activatePanel('reports-review');
					showReportDetail(relatedId, 'review');
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
	if ($('#sp-widget-reports-body')) loadWidgetReports();
	if ($('#sp-widget-actions-body')) loadWidgetActions();
	if ($('#sp-widget-mileage-body')) loadWidgetMileage();

	$$('.sp-widget-link').forEach(btn => {
		btn.addEventListener('click', () => activatePanel(btn.dataset.nav));
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
		const res = await spAjax('site_pulse_get_reports', { limit: 5 });
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
				<span class="unique sp-status-badge sp-status-${r.status}">${r.status}</span>
			</div>
		`).join('');

		markUniqueSpans(body);

		$$('.sp-widget-item', body).forEach(item => {
			item.addEventListener('click', () => {
				const navTarget = D.userCaps?.includes('view_gm_reports') || D.userCaps?.includes('view_supervisor_reports') ? 'reports-review' : 'reports-my';
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
		const res = await spAjax('site_pulse_get_action_items', { status: 'open' });
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
					<div class="sp-widget-action-desc">${esc(item.description)}</div>
					<div class="sp-widget-action-category"><span class="unique">${esc(item.category)}${item.location_name ? ' &middot; ' + esc(item.location_name) : ''}</span></div>
				</div>
			</div>
		`).join('');

		if (res.data.items.length > 5) {
			body.innerHTML += `<div class="sp-widget-empty" style="padding:8px 0;font-size:13px;">+ ${res.data.items.length - 5} more items</div>`;
		}

		markUniqueSpans(body);
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

	$$('#sp-filter-template, #sp-filter-status').forEach(el => {
		el.addEventListener('change', () => loadReports());
	});

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
		status: $('#sp-filter-status')?.value || '',
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

function renderReportList(container, reports, prefix = '') {
	currentReportList = reports || [];
	if (!reports || reports.length === 0) {
		container.innerHTML = '<div class="sp-empty-state"><p>No reports found.</p></div>';
		return;
	}

	container.innerHTML = reports.map(r => `
		<div class="sp-report-card" data-report-id="${r.id}">
			<div class="sp-report-card-left">
				<div class="sp-report-card-title">${esc(r.location_name || 'Unknown Location')}</div>
				<div class="sp-report-card-meta">
					<span>${formatDate(r.report_period_start)}</span>
					<span>${esc(r.author_name || '')}</span>
				</div>
			</div>
			<div class="sp-report-card-right">
				<span class="unique sp-status-badge sp-status-${r.status}">${r.status}</span>
			</div>
		</div>
	`).join('');

	markUniqueSpans(container);

	$$('.sp-report-card', container).forEach(card => {
		card.addEventListener('click', () => {
			const id = card.dataset.reportId;
			const report = reports.find(r => String(r.id) === id);
			if (report && report.status === 'draft') {
				showReportForm(report);
			} else {
				showReportDetail(id);
			}
		});
	});
}


/*--------------------------------------------------------------
# Reports — Form
--------------------------------------------------------------*/

async function showReportForm(existingReport = null) {
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

		// Each role fills out its own report: pick the template whose required role matches the
		// current (or impersonated) user's role; fall back to the first if there's no exact match.
		const template = templates.find(t => t.required_role_slug === D.userRole) || templates[0];
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

		renderReportForm(wrap, template, fields, answers, existingReport, previousReport);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading form.</p>';
	}
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

function renderReportForm(wrap, template, fields, answers, existingReport, previousReport = null) {
	const previousAnswers = previousReport?.answers || {};
	const previousDate = previousReport ? formatDate(previousReport.date) : '';
	const today = new Date();
	const reportDate = existingReport?.report_period_start || toDateStr(today);

	let html = '<div class="sp-report-form-header">';
	html += `<h3>${esc(template.name)}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-form-back-btn">Back</button>';
	html += '</div>';

	html += '<form id="sp-report-form">';
	html += `<input type="hidden" name="template_id" value="${template.id}">`;
	html += `<input type="hidden" name="report_id" value="${existingReport?.id || ''}">`;
	html += `<input type="hidden" name="period_start" value="${reportDate}">`;
	html += `<input type="hidden" name="period_end" value="${reportDate}">`;

	// Auto-fill header
	const headerFields = D.reportHeaderFields || [];
	const headerCount = headerFields.length + 2;
	html += `<div class="sp-report-header-grid" style="display:grid;grid-template-columns:repeat(${headerCount}, 1fr);gap:12px;margin-bottom:20px;padding:16px 20px;background:var(--sp-bg);border-radius:var(--sp-radius);border:1px solid var(--sp-border);">`;

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
		html += '<div class="sp-form-group">';
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

	html += '<div class="sp-report-form-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-save-draft-btn">Save Draft</button>';
	html += '<button type="submit" class="unique sp-btn sp-btn-primary" id="sp-submit-report-btn">Submit Report</button>';
	html += '</div>';
	html += '</form>';

	wrap.innerHTML = html;

	markUniqueSpans(wrap);

	$('.sp-form-back-btn', wrap)?.addEventListener('click', () => hideReportForm());
	$('#sp-save-draft-btn')?.addEventListener('click', () => saveReport('save'));
	$('#sp-report-form')?.addEventListener('submit', (e) => {
		e.preventDefault();
		saveReport('submit');
	});
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
	overlay.innerHTML = '<div class="sp-submit-overlay-inner"><div class="sp-loading"></div><div class="sp-submit-message">' +
		(actionType === 'submit' ? 'Submitting report & generating action items...' : 'Saving draft...') +
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
let reviewReportType = 'manager'; // which report type the review panel shows: 'manager' (GM) | 'supervisor'

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

	try {
		const res = await spAjax('site_pulse_get_report_detail', { report_id: reportId });
		if (!res.success) { detailWrap.innerHTML = '<p>Error loading report.</p>'; return; }

		const { report, answers, fields, location, author } = res.data;
		renderReportDetail(detailWrap, report, answers, fields, location, author, panelPrefix);
	} catch (err) {
		detailWrap.innerHTML = '<p>Error loading report.</p>';
	}
}

function renderReportDetail(wrap, report, answers, fields, location, author, panelPrefix) {
	const answerMap = {};
	answers.forEach(a => { answerMap[a.field_key] = a; });

	const hasPrev = currentReportIndex > 0;
	const hasNext = currentReportIndex < currentReportList.length - 1 && currentReportIndex >= 0;

	const showNewBtn = !panelPrefix && D.userCaps?.includes('submit_reports');

	// Navigation bar
	let html = '<div class="sp-detail-nav">';
	html += '<div class="sp-detail-nav-left">';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-back-btn">&larr; Back to Reports</button>';
	if (showNewBtn) {
		html += '<button type="button" class="unique sp-btn sp-btn-primary sp-detail-new-btn">+ New Report</button>';
	}
	// GOD only: permanently delete this report (and its answers + action items).
	if (D.isGod) {
		html += iconBtn('delete', 'sp-detail-god-delete', `data-report-id="${report.id}" title="Delete report (Odin only)"`);
	}
	html += '</div>';
	html += '<div class="sp-detail-nav-arrows">';
	html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-prev"${hasPrev ? '' : ' disabled'}>&lsaquo; Previous</button>`;
	html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-next"${hasNext ? '' : ' disabled'}>Next &rsaquo;</button>`;
	html += '</div></div>';

	// Header info bar
	const headerFields = D.reportHeaderFields || [];
	const headerCount = headerFields.length + 3;
	// Shared .sp-meta-bar; only the explicit column count is report-specific.
	html += `<div class="sp-meta-bar" style="grid-template-columns:repeat(${headerCount}, 1fr);">`;

	headerFields.forEach(hf => {
		const val = calcHeaderValue(hf.calc, report.report_period_start);
		html += `<div><div class="sp-card-label">${esc(hf.label)}</div><div class="sp-card-value">${esc(val)}</div></div>`;
	});

	html += `<div><div class="sp-card-label">Date</div><div class="sp-card-value">${formatDate(report.report_period_start)}</div></div>`;
	html += `<div><div class="sp-card-label">Location</div><div class="sp-card-value">${esc(location?.name || '—')}</div></div>`;
	if (author) {
		html += `<div><div class="sp-card-label">Submitted By</div><div class="sp-card-value">${esc(author.name)}</div></div>`;
	}
	html += '</div>';

	// Status
	html += `<div style="margin-bottom:20px;"><span class="unique sp-status-badge sp-status-${report.status}">${report.status}</span></div>`;

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

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	// Event listeners
	$('.sp-detail-back-btn', wrap)?.addEventListener('click', () => closeReportDetail(panelPrefix));
	$('.sp-detail-new-btn', wrap)?.addEventListener('click', () => showReportForm());

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

function initReview() {
	const list = $('#sp-review-list');
	if (!list) return;

	// Default to GM reports, unless the viewer can ONLY see supervisor reports.
	const canGm = D.userCaps?.includes('view_gm_reports');
	if (!canGm && D.userCaps?.includes('view_supervisor_reports')) reviewReportType = 'supervisor';

	populateReviewFilters();
	loadReviewReports();

	$$('#sp-review-filter-location, #sp-review-filter-user, #sp-review-filter-status').forEach(el => {
		el.addEventListener('change', () => loadReviewReports());
	});

	$$('#sp-review-filter-start, #sp-review-filter-end').forEach(el => {
		el.addEventListener('change', () => loadReviewReports());
	});
}

async function populateReviewFilters() {
	try {
		const res = await spAjax('site_pulse_get_review_filters', {});
		if (!res.success) return;

		const locSelect = $('#sp-review-filter-location');
		if (locSelect && res.data.locations) {
			res.data.locations.forEach(l => {
				const opt = document.createElement('option');
				opt.value = l.id;
				opt.textContent = l.name;
				locSelect.appendChild(opt);
			});
		}

		const userSelect = $('#sp-review-filter-user');
		if (userSelect && res.data.users) {
			res.data.users.forEach(u => {
				const opt = document.createElement('option');
				opt.value = u.user_id;
				opt.textContent = u.display_name;
				userSelect.appendChild(opt);
			});
		}
	} catch (err) {}
}

async function loadReviewReports() {
	const list = $('#sp-review-list');
	if (!list) return;

	const titleEl = $('#sp-review-title');
	if (titleEl) titleEl.textContent = reviewReportType === 'supervisor' ? 'Supervisor Reports' : 'GM Reports';

	list.innerHTML = '<div class="sp-loading"></div>';

	const filters = {
		template_role: reviewReportType, // GM ('manager') vs Supervisor reports
		location_id: $('#sp-review-filter-location')?.value || '',
		user_id: $('#sp-review-filter-user')?.value || '',
		status: $('#sp-review-filter-status')?.value || '',
		period_start: $('#sp-review-filter-start')?.value || '',
		period_end: $('#sp-review-filter-end')?.value || '',
	};

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
	currentReportList = reports || [];
	if (!reports || reports.length === 0) {
		container.innerHTML = '<div class="sp-empty-state"><p>No reports found.</p></div>';
		return;
	}

	container.innerHTML = reports.map(r => `
		<div class="sp-report-card" data-report-id="${r.id}">
			<div class="sp-report-card-left">
				<div class="sp-report-card-title">${esc(r.location_name || 'Unknown Location')}</div>
				<div class="sp-report-card-meta">
					<span>${formatDate(r.report_period_start)}</span>
					<span>${esc(r.author_name || '')}</span>
				</div>
			</div>
			<div class="sp-report-card-right">
				<span class="unique sp-status-badge sp-status-${r.status}">${r.status}</span>
			</div>
		</div>
	`).join('');

	$$('.sp-report-card', container).forEach(card => {
		card.addEventListener('click', () => showReportDetail(card.dataset.reportId, 'review'));
	});
}


/*--------------------------------------------------------------
# Analytics
--------------------------------------------------------------*/

let analyticsLoaded = false;
let analyticsFiltersPopulated = false;

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
	} catch (err) {}
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
		html += `<div class="sp-chart-bar-track"><div class="sp-chart-bar-fill" style="width:${pct}%;background:var(--sp-primary)"></div></div>`;
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
		html += `<div class="sp-chart-bar-track"><div class="sp-chart-bar-fill" style="width:${pct}%;background:var(--sp-primary)"></div></div>`;
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
	const modulesContent = $('#sp-admin-modules-content');

	if (usersContent) loadAdminUsers();
	if (tiersContent) loadAdminTiers();
	if (locsContent) loadAdminLocations();
	if (tplsContent) loadAdminTemplates();
	if (settingsContent) loadAdminSettings();
	if (modulesContent) loadAdminModules();

	initActionItems();
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

function renderAdminUsers(wrap, data) {
	const { users, roles, locations } = data;
	const mileageLocations = data.mileage_locations || [];
	spPermCatalog = data.catalog || spPermCatalog;

	let html = '<div class="sp-admin-toolbar">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-user-btn">+ Add User</button>';
	// GOD only: one-click cleanup of orphaned profiles (rows whose WP login is already gone —
	// they show with blank name/username/email).
	if (D.isGod) {
		html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-god-purge-orphans">Purge Orphaned Profiles</button>';
	}
	html += '</div>';

	if (!users || users.length === 0) {
		html += '<div class="sp-empty-state"><p>No users yet. Create your first user above.</p></div>';
	} else {
		html += '<div class="sp-admin-table-wrap"><table class="sp-admin-table">';
		html += '<thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Location</th><th>Status</th><th></th></tr></thead>';
		html += '<tbody>';
		users.forEach(u => {
			const statusClass = u.status === 'active' ? 'sp-status-submitted' : 'sp-status-draft';
			html += `<tr data-user-id="${u.user_id}">`;
			html += `<td>${esc(u.display_name)}</td>`;
			html += `<td>${esc(u.user_login)}</td>`;
			html += `<td>${esc(u.user_email)}</td>`;
			html += `<td>${esc(u.role_label || '—')}</td>`;
			html += `<td>${esc(u.location_name || '—')}</td>`;
			html += `<td><span class="unique sp-status-badge ${statusClass}">${u.status}</span></td>`;
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
			html += `<td>${userActions}</td>`;
			html += '</tr>';
		});
		html += '</tbody></table></div>';
	}

	html += '<div id="sp-user-form-wrap" hidden></div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-add-user-btn')?.addEventListener('click', () => showUserForm(null, roles, locations, users, mileageLocations));

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
	$$('.sp-edit-user-btn', wrap).forEach(btn => {
		btn.addEventListener('click', () => {
			const uid = btn.dataset.userId;
			const user = users.find(u => String(u.user_id) === uid);
			showUserForm(user, roles, locations, users, mileageLocations);
		});
	});

	$$('.sp-god-delete-user', wrap).forEach(btn => {
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
	Object.keys(spPermCatalog).forEach(cap => {
		const inRole = roleCaps.includes(cap);
		let eff = inRole;
		if (Object.prototype.hasOwnProperty.call(overrides, cap)) eff = !!overrides[cap];
		const changed = eff !== inRole;
		h += `<label class="sp-perm-row${changed ? ' sp-perm-changed' : ''}">`;
		h += `<span class="sp-perm-cell"><input type="checkbox" tabindex="-1" disabled${inRole ? ' checked' : ''}></span>`;
		h += `<span class="sp-perm-cell"><input type="checkbox" class="sp-perm-user-cb" data-cap="${cap}" data-default="${inRole ? '1' : '0'}"${eff ? ' checked' : ''}></span>`;
		h += `<span class="sp-perm-label">${esc(spPermCatalog[cap])}</span>`;
		h += '</label>';
	});
	h += '</div>';
	return h;
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

	let html = `<div class="sp-report-form-wrap" style="margin-top:20px;">`;
	html += `<div class="sp-report-form-header"><h3>${title}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-user-form-cancel">Cancel</button></div>';
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
		html += '<div class="sp-admin-table-wrap"><table class="sp-admin-table">';
		html += '<thead><tr><th>Name</th><th>Location #</th><th>Type</th><th>City</th><th>State</th><th>Status</th><th></th></tr></thead>';
		html += '<tbody>';
		locations.forEach(l => {
			const statusClass = l.status === 'active' ? 'sp-status-submitted' : 'sp-status-draft';
			html += `<tr data-loc-id="${l.id}">`;
			html += `<td>${esc(l.name)}</td>`;
			html += `<td>${esc(l.location_number || '')}</td>`;
			html += `<td>${esc(l.location_type)}</td>`;
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

	let html = '<div class="sp-report-form-wrap">';
	html += `<div class="sp-report-form-header"><h3>${title}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-loc-form-cancel">Cancel</button></div>';
	html += '<form id="sp-location-form">';

	if (isEdit) html += `<input type="hidden" name="id" value="${loc.id}">`;

	html += '<div class="sp-form-group"><label>Location Name</label>';
	html += `<input type="text" name="name" class="sp-input" value="${esc(loc?.name || '')}" required></div>`;

	html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>Type / Brand</label>';
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

function initActionItems() {
	const list = $('#sp-action-items-list');
	if (!list) return;

	populateActionItemFilters();
	loadActionItems();

	$$('#sp-action-filter-status, #sp-action-filter-location, #sp-action-filter-user, #sp-action-sort').forEach(el => {
		el?.addEventListener('change', () => loadActionItems());
	});
}

async function populateActionItemFilters() {
	const locSelect = $('#sp-action-filter-location');
	const userSelect = $('#sp-action-filter-user');
	if (!locSelect && !userSelect) return;

	try {
		const res = await spAjax('site_pulse_get_review_filters', {});
		if (!res.success) return;

		if (locSelect && res.data.locations) {
			res.data.locations.forEach(l => {
				const opt = document.createElement('option');
				opt.value = l.id;
				opt.textContent = l.name;
				locSelect.appendChild(opt);
			});
		}

		if (userSelect && res.data.users) {
			res.data.users.forEach(u => {
				const opt = document.createElement('option');
				opt.value = u.user_id;
				opt.textContent = u.display_name;
				userSelect.appendChild(opt);
			});
		}
	} catch (err) {}
}

async function loadActionItems() {
	const list = $('#sp-action-items-list');
	if (!list) return;
	list.innerHTML = '<div class="sp-loading"></div>';

	const filters = {
		status: $('#sp-action-filter-status')?.value || '',
		location_id: $('#sp-action-filter-location')?.value || '',
		user_id: $('#sp-action-filter-user')?.value || '',
	};

	try {
		const res = await spAjax('site_pulse_get_action_items', filters);
		if (!res.success) { list.innerHTML = '<div class="sp-empty-state"><p>Error loading action items.</p></div>'; return; }
		renderActionItems(list, res.data.items, res.data.pending || []);
	} catch (err) {
		list.innerHTML = '<div class="sp-empty-state"><p>Error loading action items.</p></div>';
	}
}

function renderActionItems(container, items, pending = []) {
	if ((!items || items.length === 0) && (!pending || pending.length === 0)) {
		container.innerHTML = '<div class="sp-empty-state"><p>No action items.</p></div>';
		return;
	}

	const sortMode = $('#sp-action-sort')?.value || 'importance';
	const priorityOrder = { high: 0, medium: 1, low: 2 };

	if (sortMode === 'importance' && items) {
		items.sort((a, b) => (priorityOrder[a.priority] ?? 1) - (priorityOrder[b.priority] ?? 1));
	}
	// 'custom' uses the display_order from the DB, which is how items arrive by default

	let html = '';

	if (pending.length > 0) {
		pending.sort((a, b) => (priorityOrder[a.priority] ?? 1) - (priorityOrder[b.priority] ?? 1));
		html += '<div class="sp-pending-section">';
		html += `<h3 class="sp-pending-heading">Pending Approval <span class="sp-pending-count">${pending.length}</span></h3>`;
		html += '<p class="sp-pending-intro">Approve to add to your action items list. Reject to discard.</p>';
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

	$$('.sp-resolve-item-btn', container).forEach(btn => {
		btn.addEventListener('click', () => {
			const itemEl = btn.closest('.sp-action-item');
			const itemId = btn.dataset.itemId;

			if (itemEl.querySelector('.sp-resolve-form')) return;

			const form = document.createElement('div');
			form.className = 'sp-resolve-form';
			form.innerHTML = `
				<textarea class="sp-input sp-resolve-note" placeholder="Describe what you did to resolve this..." rows="3"></textarea>
				<div style="display:grid;grid-auto-flow:column;gap:8px;justify-content:start;margin-top:8px;">
					<button type="button" class="unique sp-btn sp-btn-primary sp-resolve-submit">Submit Resolution</button>
					<button type="button" class="unique sp-btn sp-btn-ghost sp-resolve-cancel">Cancel</button>
				</div>
				<div class="sp-resolve-feedback" hidden></div>
			`;

			itemEl.querySelector('.sp-action-item-content').appendChild(form);
			markUniqueSpans(form);

			form.querySelector('.sp-resolve-cancel').addEventListener('click', () => form.remove());

			form.querySelector('.sp-resolve-submit').addEventListener('click', async () => {
				const note = form.querySelector('.sp-resolve-note').value.trim();
				if (!note) { alert('Please describe what you did.'); return; }

				const submitBtn = form.querySelector('.sp-resolve-submit');
				const feedback = form.querySelector('.sp-resolve-feedback');
				submitBtn.disabled = true;
				submitBtn.textContent = 'Evaluating...';

				try {
					const res = await spAjax('site_pulse_resolve_action_item', { item_id: itemId, note: note });
					if (res.success) {
						if (res.data.resolved === false) {
							feedback.hidden = false;
							feedback.innerHTML = '<div class="sp-resolve-not-resolved">' +
								'<strong>Not fully resolved</strong>' +
								(res.data.reason ? '<p>' + esc(res.data.reason) + '</p>' : '') +
								'<p>Follow-up created: <em>' + esc(res.data.follow_up) + '</em></p>' +
								'</div>';
							submitBtn.textContent = 'Submit Resolution';
							submitBtn.disabled = false;
							setTimeout(() => loadActionItems(), 3000);
						} else {
							loadActionItems();
							loadNotificationCount();
						}
					} else {
						alert(res.data?.message || 'Error.');
						submitBtn.textContent = 'Submit Resolution';
						submitBtn.disabled = false;
					}
				} catch (err) {
					alert('Error resolving item.');
					submitBtn.textContent = 'Submit Resolution';
					submitBtn.disabled = false;
				}
			});
		});
	});

	initActionItemDragDrop(container);
}

function renderActionItemCard(item, mode) {
	const priorityClass = item.priority === 'high' ? 'sp-priority-high' : item.priority === 'medium' ? 'sp-priority-medium' : 'sp-priority-low';
	const isOpen = mode === 'open';
	const isPending = mode === 'pending';
	const isResolved = mode === 'resolved';

	const resolvedInfo = isResolved && item.resolved_at
		? `<div class="sp-action-resolved">Resolved ${timeAgo(item.resolved_at)}${item.resolution_note ? ' — ' + esc(item.resolution_note) : ''}</div>`
		: '';

	const classes = [ 'sp-action-item', priorityClass ];
	if (isResolved) classes.push('sp-action-resolved-item');
	if (isPending) classes.push('sp-action-pending');

	let html = `<div class="${classes.join(' ')}" data-item-id="${item.id}"${isOpen ? ' draggable="true"' : ''}>`;
	if (isOpen) html += '<span class="sp-action-drag">&#9776;</span>';
	html += '<div class="sp-action-item-content">';

	const history = item.meta ? (typeof item.meta === 'string' ? JSON.parse(item.meta || '[]') : item.meta) : [];
	if (Array.isArray(history) && history.length > 0) {
		html += '<div class="sp-action-history">';
		history.forEach(h => {
			html += '<div class="sp-action-history-entry">';
			html += `<div class="sp-action-history-label">Original Item:</div>`;
			html += `<div class="sp-action-history-text">${esc(h.original)}</div>`;
			html += `<div class="sp-action-history-label">Response:</div>`;
			html += `<div class="sp-action-history-text">${esc(h.response)}</div>`;
			if (h.ai_reason) {
				html += `<div class="sp-action-history-label">Assessment:</div>`;
				html += `<div class="sp-action-history-ai">${esc(h.ai_reason)}</div>`;
			}
			html += '</div>';
		});
		html += '</div>';
	}

	html += `<div class="sp-action-item-category"><span class="unique">${esc(item.category)}</span></div>`;
	html += `<div class="sp-action-item-desc">${esc(item.description)}</div>`;
	html += `<div class="sp-action-item-meta"><span class="unique">${esc(item.location_name || '')}</span>`;
	if (item.user_name) html += ` <span class="unique">&middot; ${esc(item.user_name)}</span>`;
	if (item.due_date) html += ` <span class="unique">&middot; Due ${formatDate(item.due_date)}</span>`;
	html += '</div>';
	html += resolvedInfo;
	html += '</div>';

	if (isPending) {
		html += '<div class="sp-action-review-actions">';
		html += `<button type="button" class="unique sp-btn sp-btn-primary sp-review-approve-btn" data-item-id="${item.id}">Approve</button>`;
		html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-review-reject-btn" data-item-id="${item.id}">Reject</button>`;
		html += '</div>';
	} else if (isOpen) {
		html += `<button type="button" class="unique sp-btn sp-btn-secondary sp-resolve-item-btn" data-item-id="${item.id}">Resolve</button>`;
	}

	// GOD only: permanently delete this action item, in any state.
	if (D.isGod) {
		html += iconBtn('delete', 'sp-god-delete-action', `data-item-id="${item.id}" title="Delete item (Odin only)"`);
	}

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

	$$('.sp-action-item[draggable]', wrap).forEach(item => {
		item.addEventListener('dragstart', (e) => {
			dragItem = item;
			item.classList.add('sp-dragging');
			e.dataTransfer.effectAllowed = 'move';
		});

		item.addEventListener('dragend', () => {
			item.classList.remove('sp-dragging');
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

			const items = [...wrap.querySelectorAll('.sp-action-item[draggable]')];
			const fromIndex = items.indexOf(dragItem);
			const toIndex = items.indexOf(item);

			if (fromIndex < toIndex) {
				wrap.insertBefore(dragItem, item.nextSibling);
			} else {
				wrap.insertBefore(dragItem, item);
			}

			const sortSelect = $('#sp-action-sort');
			if (sortSelect) sortSelect.value = 'custom';

			const newOrder = [...wrap.querySelectorAll('.sp-action-item[draggable]')].map(el => el.dataset.itemId);
			try {
				await spAjax('site_pulse_reorder_action_items', { order: newOrder });
			} catch (err) {
				loadActionItems();
			}
		});
	});
}


/* ---- Settings ---- */

async function loadAdminSettings() {
	const wrap = $('#sp-admin-settings-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_settings', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading settings.</p>'; return; }
		renderAdminSettings(wrap, res.data);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading settings.</p>';
	}
}

function renderAdminSettings(wrap, data) {
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
		return h;
	};

	let html = '<h3 style="margin:0 0 20px;">API Keys</h3>';

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
				if (res.success) { if (inp) inp.value = ''; loadAdminSettings(); }
				else { alert(res.data?.message || 'Error saving.'); btn.disabled = false; }
			} catch (err) { alert('Error saving key.'); btn.disabled = false; }
		});
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
	let html = '<h3 style="margin:0 0 8px;">Active Modules</h3>';
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

let spTierData = { roles: [], catalog: {}, counts: {} };

async function loadAdminTiers() {
	const wrap = $('#sp-admin-tiers-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_roles', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading roles.</p>'; return; }
		spTierData = { roles: res.data.roles || [], catalog: res.data.catalog || {}, counts: res.data.user_counts || {} };
		renderAdminTiers(wrap);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading roles.</p>';
	}
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
	const { roles, catalog, counts } = spTierData;
	const tiers = tierNumbers(roles);

	let html = '<div class="sp-admin-toolbar">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-tier-btn">+ Add Role</button>';
	html += '</div>';
	html += '<p class="sp-help-text" style="margin:0 0 16px;">Rename a role and the new name shows everywhere it appears. The internal identity, members, and reports are unaffected.</p>';
	html += '<div id="sp-tier-form-wrap" hidden></div>';

	if (!roles.length) {
		html += '<div class="sp-empty-state"><p>No roles yet.</p></div>';
	} else {
		html += '<div class="sp-tier-list">';
		roles.forEach((r, idx) => {
			const n = counts[r.id] || 0;
			let caps = [];
			try { caps = JSON.parse(r.capabilities || '[]') || []; } catch (e) {}

			html += `<div class="sp-tier-card" data-id="${r.id}">`;
			html += '<div class="sp-tier-card-head">';
			html += `<span class="unique sp-tier-badge">Role ${tiers[r.id]}</span>`;
			html += `<input type="text" class="sp-input sp-tier-name" value="${esc(r.label)}" aria-label="Role name">`;
			html += `<span class="sp-tier-count">${n} member${n === 1 ? '' : 's'}</span>`;
			html += '<div class="sp-tier-move">';
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-tier-up" title="Move up"${idx === 0 ? ' disabled' : ''}>&uarr;</button>`;
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-tier-down" title="Move down"${idx === roles.length - 1 ? ' disabled' : ''}>&darr;</button>`;
			html += '</div></div>';

			html += '<div class="sp-tier-caps">';
			Object.entries(catalog).forEach(([cap, label]) => {
				const checked = caps.includes(cap) ? ' checked' : '';
				html += `<label class="sp-tier-cap"><input type="checkbox" value="${cap}"${checked}> ${esc(label)}</label>`;
			});
			html += '</div>';

			html += '<div class="sp-tier-card-foot">';
			html += `<button type="button" class="unique sp-icon-btn sp-icon-delete sp-tier-delete"${n ? ' disabled title="Reassign members first"' : ' title="Delete" aria-label="Delete"'}>${ICON_DELETE}</button>`;
			html += '</div>';
			html += '</div>';
		});
		html += '</div>';
	}

	// Below the role cards: jump to one user's Edit popup to fine-tune their permissions on top
	// of their role default. Hidden until populated (and only if the admin can manage users).
	html += '<div class="sp-roles-user-picker" id="sp-roles-user-picker" hidden><label for="sp-roles-user-select">Customize an individual user’s permissions <span class="unique sp-text-secondary" style="font-weight:400;">(overrides their role default — the role itself stays intact)</span></label>';
	html += '<select class="sp-select" id="sp-roles-user-select"><option value="">Select a user…</option></select></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-add-tier-btn')?.addEventListener('click', () => showTierForm());

	$$('.sp-tier-card', wrap).forEach(card => {
		const id = card.dataset.id;
		// Auto-save: name on commit (blur/Enter), capabilities the moment a box is ticked.
		$('.sp-tier-name', card)?.addEventListener('change', () => saveTier(id, card));
		$$('.sp-tier-caps input', card).forEach(cb => cb.addEventListener('change', () => saveTier(id, card)));
		$('.sp-tier-delete', card)?.addEventListener('click', () => deleteTier(id, card));
		$('.sp-tier-up', card)?.addEventListener('click', () => moveTier(id, -1));
		$('.sp-tier-down', card)?.addEventListener('click', () => moveTier(id, 1));
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
	const { catalog } = spTierData;

	let html = '<div class="sp-tier-form">';
	html += '<h3 style="margin:0 0 12px;">New Role</h3>';
	html += '<div class="sp-form-group"><label>Role Name</label>';
	html += '<input type="text" id="sp-new-tier-name" class="sp-input" placeholder="e.g. Shift Lead"></div>';
	html += '<div class="sp-form-group"><label>Capabilities</label>';
	html += '<div class="sp-tier-caps">';
	Object.entries(catalog).forEach(([cap, label]) => {
		html += `<label class="sp-tier-cap"><input type="checkbox" value="${cap}"> ${esc(label)}</label>`;
	});
	html += '</div></div>';
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
		const caps = $$('.sp-tier-caps input:checked', wrap).map(c => c.value);
		try {
			const res = await spAjax('site_pulse_admin_save_role', { id: 0, label, capabilities: caps });
			if (res.success) { closeFormModal(); loadAdminTiers(); }
			else alert(res.data?.message || 'Error creating role.');
		} catch (e) { alert('Error creating role.'); }
	});
}

async function saveTier(id, card) {
	const label = $('.sp-tier-name', card)?.value?.trim();
	if (!label) { spFlash('Role name required'); return; }
	const caps = $$('.sp-tier-caps input:checked', card).map(c => c.value);
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

async function loadAdminTemplates() {
	const wrap = $('#sp-admin-templates-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_templates', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading templates.</p>'; return; }
		spTemplateRoles = res.data.roles || [];
		renderAdminTemplates(wrap, res.data.templates);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading templates.</p>';
	}
}

function renderAdminTemplates(wrap, templates) {
	// No "+ Add Template" here — the report set is fixed (GM + Supervisor). Templates are
	// edited in place (rename, toggle, add/remove fields) via the per-template controls below.
	let html = '';

	if (!templates || templates.length === 0) {
		html += '<div class="sp-empty-state"><p>No report templates yet.</p></div>';
	} else {
		templates.forEach(t => {
			const statusClass = t.is_active == 1 ? 'sp-status-submitted' : 'sp-status-draft';
			const statusLabel = t.is_active == 1 ? 'active' : 'inactive';
			const fieldCount = (t.fields || []).length;

			html += `<div class="sp-template-card" data-template-id="${t.id}">`;
			html += '<div class="sp-template-card-header">';
			html += `<div><strong>${esc(t.name)}</strong> <span class="unique sp-status-badge ${statusClass}">${statusLabel}</span></div>`;
			html += `<div style="display:grid;grid-auto-flow:column;gap:8px;">`;
			html += iconBtn('edit', 'sp-edit-template-btn', `data-id="${t.id}"`);
			html += '</div></div>';
			const roleLabel = spTemplateRoles.find(r => r.slug === t.required_role_slug)?.label || t.required_role_slug;
			html += `<div class="sp-template-meta">${esc(t.frequency)} &middot; ${esc(roleLabel)} &middot; ${fieldCount} field${fieldCount !== 1 ? 's' : ''}</div>`;

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
					html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-delete-field-btn" data-field-id="${f.id}">×</button>`;
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
	const title = isEdit ? 'Edit Template' : 'Add Template';

	let html = '<div class="sp-report-form-wrap">';
	html += `<div class="sp-report-form-header"><h3>${title}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-tpl-form-cancel">Cancel</button></div>';
	html += '<form id="sp-template-form">';
	if (isEdit) html += `<input type="hidden" name="id" value="${tpl.id}">`;

	html += '<div class="sp-form-group"><label>Template Name</label>';
	html += `<input type="text" name="name" class="sp-input" value="${esc(tpl?.name || '')}" required></div>`;

	html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>Frequency</label>';
	html += `<select name="frequency" class="sp-select">`;
	['weekly','biweekly','monthly','quarterly'].forEach(f => {
		html += `<option value="${f}"${tpl?.frequency === f ? ' selected' : ''}>${f.charAt(0).toUpperCase() + f.slice(1)}</option>`;
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

	html += '<div class="sp-report-form-actions">';
	html += `<button type="submit" class="unique sp-btn sp-btn-primary">${isEdit ? 'Save Changes' : 'Create Template'}</button>`;
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

	let html = '<div class="sp-report-form-wrap">';
	html += `<div class="sp-report-form-header"><h3>${title}</h3>`;
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
	html += '<div class="sp-form-group"><label>Type</label>';
	html += `<select name="field_type" class="sp-select">`;
	['textarea','text','number','select','rating'].forEach(t => {
		html += `<option value="${t}"${field?.field_type === t ? ' selected' : ''}>${t.charAt(0).toUpperCase() + t.slice(1)}</option>`;
	});
	html += '</select></div>';

	html += '<div class="sp-form-group"><label>Required</label>';
	html += `<select name="is_required" class="sp-select">`;
	html += `<option value="0"${!field || field.is_required == 0 ? ' selected' : ''}>No</option>`;
	html += `<option value="1"${field?.is_required == 1 ? ' selected' : ''}>Yes</option>`;
	html += '</select></div>';

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

	$('#sp-field-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;
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

function esc(str) {
	if (!str) return '';
	const el = document.createElement('span');
	el.textContent = String(str);
	return el.innerHTML;
}

function formatDate(dateStr) {
	if (!dateStr) return '';
	const d = new Date(dateStr + 'T00:00:00');
	return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
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
let mileageHomeId = 0; // this driver's home-base location id (start/end bookend), 0 if unset
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

	$('#sp-mileage-add-btn')?.addEventListener('click', () => showMileageForm());
	$('#sp-mileage-print-btn')?.addEventListener('click', () => printMileageLog());
	$('#sp-mileage-email-btn')?.addEventListener('click', () => emailMileageLog());
	$('#sp-mileage-pdf-btn')?.addEventListener('click', () => exportMileagePDF());
	$('#sp-mileage-csv-btn')?.addEventListener('click', () => exportMileageCSV());
	$('#sp-mileage-filter-clear')?.addEventListener('click', () => {
		$('#sp-mileage-filter-start').value = '';
		$('#sp-mileage-filter-end').value = '';
		loadMileageEntries();
	});
	$('#sp-mileage-filter-start')?.addEventListener('change', loadMileageEntries);
	$('#sp-mileage-filter-end')?.addEventListener('change', loadMileageEntries);

	$$('.sp-mileage-range', panel).forEach(b => b.addEventListener('click', () => setMileageRange(b.dataset.range)));
	populateMileagePeriods();
	$('#sp-mileage-month-picker')?.addEventListener('change', (e) => {
		const v = e.target.value;
		if (!v) return;
		const [start, end] = v.split('|');
		setMileageFilter(start, end);
	});

	// Click a leg's miles to adjust them (detour, closure, accident). Delegated on the list
	// container so it keeps working after each re-render replaces the rows inside it.
	$('#sp-mileage-list')?.addEventListener('click', (ev) => {
		const el = ev.target.closest('.sp-leg-edit');
		if (el && !el.querySelector('input')) openLegMilesEditor(el);
	});

	// Re-populate once the period config arrives (locations load resolves after the initial paint).
	loadMileageLocations().then(() => { populateMileagePeriods(); loadMileageEntries(); });
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
				const bg = i === activeIdx ? 'background:var(--sp-primary-light,#fdf0e3);' : '';
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
function computeMileagePeriods(anchorIso, lengthDays, back = 24, fwd = 1) {
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

		summary.innerHTML = `
			<div class="sp-meta-bar">
				<div><div class="sp-card-label">Total Miles</div><div class="sp-card-value">${mileageNumFmt(totalMiles)}</div></div>
				<div><div class="sp-card-label">Mileage</div><div class="sp-card-value">$${mileageNumFmt(totalAmt)}</div></div>
				${hasTrailer ? `<div><div class="sp-card-label">Trailer</div><div class="sp-card-value">$${mileageNumFmt(totalTrailer)}</div></div>` : ''}
				<div><div class="sp-card-label">Tolls</div><div class="sp-card-value">$${mileageNumFmt(totalTolls)}</div></div>
				<div><div class="sp-card-label">Total Reimbursement</div><div class="sp-card-value">$${mileageNumFmt(totalAmt + totalTolls + totalTrailer)}</div></div>
				${pendingCount > 0 ? `<div><div class="sp-card-label">Pending</div><div class="sp-card-value sp-text-warning">${pendingCount}</div></div>` : ''}
			</div>
		`;

		let html = '<div class="sp-table-card"><table class="sp-mileage-table"><thead><tr><th>Date</th><th>Route</th><th class="sp-th-num">Miles</th><th class="sp-th-num">$</th>' + (hasTrailer ? '<th class="sp-th-num">Trailer</th>' : '') + (hasTolls ? '<th class="sp-th-num">Tolls</th>' : '') + '<th>Status</th></tr></thead><tbody>';
		entries.forEach(e => {
			const isPending = parseInt(e.pending_legs) > 0;
			const toll = parseFloat(e.total_tolls || 0);
			const trailer = parseFloat(e.total_trailer || 0);
			html += `<tr data-entry-id="${e.id}">`;
			// Cell classes (sp-m-*) + data-label drive the mobile card reflow (see
			// style-site-pulse.css @media): Date/Route span full width, the numeric
			// columns stack into their own labeled row beneath.
			html += `<td class="sp-m-date"><div class="sp-mileage-date">${formatDate(e.entry_date)}</div></td>`;
			html += `<td class="sp-mileage-path-cell"><span class="unique sp-mileage-path-loading">Loading…</span></td>`;
			html += `<td class="sp-m-num" data-label="Miles">${parseFloat(e.total_miles).toFixed(2)}</td>`;
			html += `<td class="sp-m-num" data-label="$">$${parseFloat(e.reimbursement_amount).toFixed(2)}</td>`;
			if (hasTrailer) html += `<td class="sp-m-num" data-label="Trailer">${trailer > 0 ? '$' + trailer.toFixed(2) : '—'}</td>`;
			if (hasTolls) html += `<td class="sp-m-num" data-label="Tolls">${toll > 0 ? '$' + toll.toFixed(2) : '—'}</td>`;
			html += `<td class="sp-m-status">${isPending ? '<span class="unique sp-status-badge sp-status-pending">Pending</span>' : '<span class="unique sp-status-badge sp-status-submitted">Final</span>'}`;
			html += `<div class="sp-mileage-row-actions">`;
			html += iconBtn('edit', 'sp-mileage-edit-btn', `data-id="${e.id}"`);
			html += iconBtn('delete', 'sp-mileage-delete-btn', `data-id="${e.id}"`);
			html += `</div></td>`;
			html += `</tr>`;
		});
		html += '</tbody></table></div>';
		list.innerHTML = html;
		markUniqueSpans(list);

		// Lazy-load each entry's path inline
		entries.forEach(async (e) => {
			try {
				const r = await spAjax('site_pulse_get_mileage_entry', { entry_id: e.id });
				if (!r.success) return;
				const legs = r.data.legs || [];
				const row = list.querySelector(`tr[data-entry-id="${e.id}"]`);
				if (!row) return;
				const cell = row.querySelector('.sp-mileage-path-cell');
				if (legs.length === 0) { if (cell) cell.textContent = '—'; return; }
				const legRate = parseFloat(r.data.entry?.rate_used) || mileageRate;

				// One stop per line: origin, then each destination with its purpose in italic.
				// A bold "Day total" line caps each cell so the day's totals sit at the BOTTOM.
				const nodes = [{ name: legs[0].from_name || '?', purpose: '', leg: null }];
				legs.forEach(leg => nodes.push({ name: leg.to_name || '?', purpose: (leg.purpose || '').trim(), leg }));
				const BLANK = '<div class="sp-leg-blank">&nbsp;</div>'; // holds a row on desktop, hidden on mobile

				if (cell) cell.innerHTML = nodes.map(n => {
					const p = n.purpose ? ` <em class="sp-route-purpose">— ${esc(n.purpose)}</em>` : '';
					return `<div class="sp-mileage-route-line">${esc(n.name)}${p}</div>`;
				}).join('');

				const milesCell = row.querySelector('td[data-label="Miles"]');
				const amtCell   = row.querySelector('td[data-label="$"]');

				// Miles: blank on the origin line, each leg's miles (clickable to adjust, with a
				// "(+10)" badge for a saved adjustment), then the bold day total at the bottom.
				if (milesCell) milesCell.innerHTML = nodes.map(n => {
					if (!n.leg) return BLANK;
					if (n.leg.miles == null) return '<div class="sp-leg-stat">—</div>';
					const base = parseFloat(n.leg.miles) || 0;
					const adj = parseFloat(n.leg.miles_adjust) || 0;
					const reasonText = (n.leg.miles_adjust_reason || '').trim();
					const badge = adj ? ` <span class="sp-leg-adjust">(${adj > 0 ? '+' : ''}${mileageNumFmt(adj)})</span>` : '';
					const title = !adj ? "Click to adjust this trip's miles"
						: `Calculated ${mileageNumFmt(base)} mi${reasonText ? ' · ' + reasonText : ''} · click to edit`;
					return `<div class="sp-leg-stat sp-leg-edit" data-entry-id="${e.id}" data-leg-id="${n.leg.id}" data-base="${base}" data-adjust="${adj}" data-reason="${esc(reasonText)}" title="${esc(title)}"><span class="sp-leg-val">${mileageNumFmt(base + adj)}</span>${badge}</div>`;
				}).join('') + `<div class="sp-day-total-line">${mileageNumFmt(parseFloat(e.total_miles) || 0)}</div>`;

				// $ per leg uses the effective (adjusted) miles; the day total $ caps the column.
				if (amtCell) amtCell.innerHTML = nodes.map(n => {
					if (!n.leg) return BLANK;
					if (n.leg.miles == null) return '<div class="sp-leg-stat">—</div>';
					const eff = (parseFloat(n.leg.miles) || 0) + (parseFloat(n.leg.miles_adjust) || 0);
					return `<div class="sp-leg-stat">$${mileageNumFmt(eff * legRate)}</div>`;
				}).join('') + `<div class="sp-day-total-line">$${mileageNumFmt(parseFloat(e.reimbursement_amount) || 0)}</div>`;

				// Trailer isn't broken down per leg — blank stop lines, day total at the bottom.
				const blanks = nodes.map(() => BLANK).join('');
				const trailerCell = row.querySelector('td[data-label="Trailer"]');
				if (trailerCell) trailerCell.innerHTML = blanks + `<div class="sp-day-total-line">$${mileageNumFmt(parseFloat(e.total_trailer) || 0)}</div>`;

				// Tolls: per-leg, aligned with the route lines (matched from the toll CSV), then
				// the bold day total at the bottom — same shape as the Miles/$ columns.
				const tollsCell = row.querySelector('td[data-label="Tolls"]');
				if (tollsCell) {
					const dt = parseFloat(e.total_tolls) || 0;
					tollsCell.innerHTML = nodes.map(n => {
						if (!n.leg) return BLANK;
						const tc = parseFloat(n.leg.toll_cost);
						if (!n.leg.toll_cost || !(tc > 0)) return '<div class="sp-leg-stat">—</div>';
						return `<div class="sp-leg-stat">$${mileageNumFmt(tc)}</div>`;
					}).join('') + `<div class="sp-day-total-line">${dt > 0 ? '$' + mileageNumFmt(dt) : '—'}</div>`;
				}
			} catch (err) {}
		});

		$$('.sp-mileage-edit-btn', list).forEach(b => b.addEventListener('click', () => showMileageForm(parseInt(b.dataset.id))));
		$$('.sp-mileage-delete-btn', list).forEach(b => b.addEventListener('click', async () => {
			if (!confirm('Delete this mileage entry?')) return;
			const r = await spAjax('site_pulse_delete_mileage_entry', { entry_id: b.dataset.id });
			if (r.success) loadMileageEntries();
			else alert(r.data?.message || 'Error.');
		}));
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

function queueMileageAutoSave() {
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

	const data = {
		entry_id: mileageFormEntryId || '',
		entry_date: form.entry_date.value,
		stops: stopsArr, purposes: purposesArr, tolls: tollsArr, trailers: trailersArr, charge_to: chargeArr, line_notes: notesArr,
		auto_return_home: mileageHomeId ? 1 : 0,
		end_toll: readMileageEndToll(), end_trailer: readMileageEndTrailer(), end_charge: readMileageEndChargeTo(),
	};

	mileageSaving = true;
	try {
		const r = await spAjax('site_pulse_save_mileage_entry', data);
		if (r.success) {
			mileageFormEntryId = parseInt(r.data.entry_id) || mileageFormEntryId;
			spFlash('Saved');
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
				if (seq.length && seq[seq.length - 1] === mileageHomeId) {
					// the leg ARRIVING home carries the END toll/trailer/charge — keep them before stripping
					endToll = seqT[seqT.length - 1] ? 1 : 0;
					endTrailer = seqTr[seqTr.length - 1] ? 1 : 0;
					endCharge = seqC[seqC.length - 1] || '';
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

	let html = '<div class="sp-report-form-header">';
	html += `<h3>${entryId ? 'Edit' : 'New'} Mileage Day</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-back-btn">Back</button>';
	html += '</div>';
	html += '<form id="sp-mileage-form">';
	html += `<input type="hidden" name="entry_id" value="${entryId || ''}">`;
	html += '<div class="sp-mileage-form-top">';
	html += '<div class="sp-form-group">';
	html += `<label>Date</label><input type="date" name="entry_date" class="sp-input" value="${date}" required>`;
	html += '</div>';
	html += '<div class="sp-form-group">';
	html += '<label>Route</label>';
	html += '<div class="sp-quickentry">';
	html += '<input type="text" id="sp-mileage-quick" class="sp-input" placeholder="Quick add — type or speak stops, e.g. Carrollton, Garland, Burleson">';
	html += '<button type="button" class="unique sp-icon-btn sp-quickentry-mic" id="sp-mileage-mic" title="Speak stops" aria-label="Speak stops"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><path d="M12 19v4M8 23h8"/></svg></button>';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-quick-fill">Fill</button>';
	html += '</div>';
	html += '<div class="sp-quickentry-note" id="sp-mileage-quick-note" hidden></div>';
	html += '</div>';
	html += '</div>';
	html += '<div class="sp-mileage-stops" id="sp-mileage-stops"></div>';
	html += '<div class="sp-mileage-totals" id="sp-mileage-totals"></div>';
	html += '<div class="sp-report-form-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary sp-mileage-back-btn">Log Day</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-cancel-btn">Cancel</button>';
	html += '<span class="sp-autosave-hint">Changes save automatically</span>';
	html += '</div>';
	html += '</form>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	renderMileageStops(stops, autoReturn, purposes, tolls, trailers, endToll, endTrailer, chargeTos, endCharge, lineNotes);

	// "Log Day"/Back: flush any pending save, then close and refresh the list.
	$$('.sp-mileage-back-btn', wrap).forEach(b => b.addEventListener('click', async () => {
		clearTimeout(mileageSaveTimer);
		await autoSaveMileageEntry();
		hideMileageForm();
		loadMileageEntries();
	}));
	// Cancel: discard the day entirely — delete whatever was auto-saved, then close. If nothing
	// has been saved yet (no valid stops), it just closes. Confirmed because it's destructive.
	$('.sp-mileage-cancel-btn', wrap)?.addEventListener('click', async () => {
		clearTimeout(mileageSaveTimer);
		if (mileageFormEntryId) {
			if (!confirm('Cancel this day and remove everything entered for it?')) return;
			try { await spAjax('site_pulse_delete_mileage_entry', { entry_id: mileageFormEntryId }); } catch (e) {}
			mileageFormEntryId = 0;
		}
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

function renderMileageStops(stops, autoReturn = true, purposes = [], tolls = [], trailers = [], endToll = 0, endTrailer = 0, chargeTos = [], endCharge = '', notes = []) {
	const wrap = $('#sp-mileage-stops');
	if (!wrap) return;
	const minMiddle = mileageHomeId ? 1 : 2;
	const homeLoc = mileageHomeId ? mileageLocations.find(l => parseInt(l.id) === mileageHomeId) : null;
	const homeName = homeLoc ? homeLoc.name : 'Home';
	let html = '';

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
		html += `<input type="text" class="sp-input sp-combo-input sp-mileage-stop-loc" list="sp-mileage-loc-list" placeholder="Choose a location" autocomplete="off" value="${esc(selLabel)}">`;
		html += `<input type="hidden" class="sp-mileage-stop-select" value="${stopId || 0}">`;
		html += '</div>';
		// "Charge To": which store this leg's mileage is billed to. Required per leg — an empty one
		// keeps the day Pending. Greyed like a placeholder until a store is picked.
		const chargeTo = chargeTos[idx] != null ? String(chargeTos[idx]) : '';
		html += `<select class="sp-select sp-mileage-charge sp-mileage-stop-charge${chargeTo === '' ? ' sp-charge-empty' : ''}" title="Charge this trip to a store">${mileageChargeOptionsHtml(chargeTo)}</select>`;
		// Purpose + trailer flag share the purpose column. (Tolls are no longer flagged here —
		// they're matched from the driver's uploaded toll CSV in Reconcile Tolls.)
		html += '<div class="sp-mileage-stop-purpose-cell">';
		html += `<input type="text" class="sp-input sp-combo-input sp-mileage-stop-purpose" list="sp-mileage-purpose-list" placeholder="Business purpose" value="${esc(purpose)}">`;
		html += `<label class="sp-mileage-toll-toggle unique" title="Check if a trailer was pulled on this leg — the extra trailer rate per mile is added."><input type="checkbox" class="sp-mileage-stop-trailer"${trailer ? ' checked' : ''}>Trailer</label>`;
		html += '</div>';
		if (stops.length > minMiddle) {
			html += `<button type="button" class="unique sp-mileage-stop-remove" data-idx="${idx}" aria-label="Remove stop" title="Remove stop">&times;</button>`;
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

	// Locked round-trip END bookend — always returns to Home. The drive home can carry
	// its own trailer flag, just like a middle stop. (Tolls come from the uploaded CSV.)
	if (mileageHomeId) {
		html += '<div class="sp-mileage-stop sp-mileage-stop-fixed sp-mileage-stop-end">';
		html += '<span class="unique sp-mileage-bookend-label">END</span>';
		html += `<div class="sp-mileage-stop-home">${esc(homeName)}</div>`;
		// The drive home is also charged to a store — required, same as the stops above.
		html += `<select class="sp-select sp-mileage-charge sp-mileage-end-charge${endCharge === '' ? ' sp-charge-empty' : ''}" title="Charge the drive home to a store">${mileageChargeOptionsHtml(endCharge)}</select>`;
		html += '<div class="sp-mileage-stop-purpose-cell sp-mileage-end-extras">';
		html += `<label class="sp-mileage-toll-toggle unique" title="Check if a trailer was pulled on the drive home — the extra trailer rate per mile is added."><input type="checkbox" class="sp-mileage-end-trailer"${endTrailer ? ' checked' : ''}>Trailer</label>`;
		html += '</div>';
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
			// When a destination with pinned purposes is picked, pre-fill the (empty) purpose.
			const purposeInput = row?.querySelector('.sp-mileage-stop-purpose');
			if (id && purposeInput && !purposeInput.value.trim()) {
				const loc = mileageLocations.find(l => parseInt(l.id) === id);
				const pinned = locationPinnedPurposes(loc);
				if (pinned.length) purposeInput.value = pinned[0];
			}
			syncStopNote(row); // a pinned purpose may itself require the note line
			updateMileageTotals();
		};
		inp.addEventListener('input', apply);
		inp.addEventListener('change', apply);
	});
	// Purpose change: reveal/hide that stop's note line (per the purpose's "Requires note" flag) and persist.
	$$('.sp-mileage-stop-purpose', wrap).forEach(inp => inp.addEventListener('input', () => {
		syncStopNote(inp.closest('.sp-mileage-stop-row'));
		queueMileageAutoSave();
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
		renderMileageStops(s, getReturnHome(), p, t, tr, readMileageEndToll(), readMileageEndTrailer(), c, readMileageEndChargeTo(), n);
		updateMileageTotals();
		queueMileageAutoSave();
	}));
	$('#sp-mileage-add-stop', wrap)?.addEventListener('click', () => {
		const s = readMileageStops(), p = readMileageStopPurposes(), t = readMileageStopTolls(), tr = readMileageStopTrailers(), c = readMileageStopChargeTo(), n = readMileageStopNotes();
		s.push(0); p.push(''); t.push(0); tr.push(0); c.push(''); n.push('');
		renderMileageStops(s, getReturnHome(), p, t, tr, readMileageEndToll(), readMileageEndTrailer(), c, readMileageEndChargeTo(), n);
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
	renderMileageStops(ids, getReturnHome(), purposes, ids.map(() => 0), ids.map(() => 0), readMileageEndToll(), readMileageEndTrailer(), [], readMileageEndChargeTo(), []);
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
			renderMileageStops(stops, getReturnHome(), purposes, tolls, trailers, readMileageEndToll(), readMileageEndTrailer(), charge, readMileageEndChargeTo(), lnotes);
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
				<button type="button" class="unique sp-icon-btn sp-icon-delete" id="sp-editloc-delete" title="Delete" aria-label="Delete" style="margin-left:auto;">${ICON_DELETE}</button>
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

async function fetchMileageReport() {
	const start = $('#sp-mileage-filter-start')?.value || '';
	const end = $('#sp-mileage-filter-end')?.value || '';
	const res = await spAjax('site_pulse_get_mileage_report', { start, end });
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

	// Hard gate to CSV only — the file input's accept= is just a picker hint, so enforce it.
	const isCsv = /\.csv$/i.test(f.name) && (!f.type || /csv|text\/plain|application\/vnd\.ms-excel/i.test(f.type));
	if (!isCsv) {
		if (status) { status.hidden = false; status.className = 'sp-toll-status sp-toll-status-error'; status.textContent = 'Please upload a .csv file — that\'s the export format from your toll account.'; }
		file.value = ''; btn.disabled = true;
		return;
	}

	const text = await f.text();

	btn.disabled = true; btn.textContent = 'Uploading…';
	if (status) { status.hidden = false; status.className = 'sp-toll-status'; status.textContent = 'Reading your toll statement…'; }
	$('#sp-toll-days').innerHTML = '';

	try {
		const r = await spAjax('site_pulse_upload_toll_csv', { csv: text, filename: f.name });
		if (!r.success) {
			if (status) { status.className = 'sp-toll-status sp-toll-status-error'; status.textContent = r.data?.message || 'Upload failed.'; }
		} else {
			renderTollDays(r.data);
		}
	} catch (e) {
		if (status) { status.className = 'sp-toll-status sp-toll-status-error'; status.textContent = 'Upload failed. Please try again.'; }
	} finally {
		btn.disabled = false; btn.textContent = 'Upload CSV';
	}
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
		h += `<div class="sp-toll-day sp-table-card${d.applied ? ' sp-toll-day-done' : ''}" data-entry="${d.entry_id}" data-total="${d.total_tolls || 0}">`;
		h += `<div class="sp-toll-day-head"><span class="unique sp-toll-day-date">${tollFmtDate(d.date)}</span>`;
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

	let h = '<table class="sp-toll-table"><thead><tr><th>When</th><th>Road / Gantry</th><th class="sp-m-num">Charge</th><th>Assign to leg</th></tr></thead><tbody>';
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

	// Comparison strip + apply.
	h += '<div class="sp-toll-compare">';
	h += '<div class="sp-toll-compare-stat"><div class="sp-card-value" data-toll-actual>$0.00</div><div class="sp-card-label">Tolls from CSV (applied)</div></div>';
	h += '<div class="sp-toll-compare-stat"><div class="sp-card-value" data-toll-estimate>$0.00</div><div class="sp-card-label">TollGuru estimate</div></div>';
	h += '<div class="sp-toll-compare-stat"><div class="sp-card-value" data-toll-variance>$0.00</div><div class="sp-card-label">Difference</div></div>';
	h += '</div>';
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

function tollRecalc(card, estMap) {
	const sels = $$('.sp-toll-assign', card);
	let actual = 0;
	const legs = new Set();
	sels.forEach(s => { if (s.value) { actual += parseFloat(s.dataset.amount) || 0; legs.add(s.value); } });
	let estimate = 0;
	legs.forEach(id => { if (estMap[id] != null) estimate += estMap[id]; });
	const setVal = (sel, v) => { const el = card.querySelector(sel); if (el) el.textContent = '$' + v.toFixed(2); };
	setVal('[data-toll-actual]', actual);
	setVal('[data-toll-estimate]', estimate);
	const varEl = card.querySelector('[data-toll-variance]');
	if (varEl) {
		const diff = actual - estimate;
		varEl.textContent = (diff >= 0 ? '+$' : '-$') + Math.abs(diff).toFixed(2);
		varEl.classList.toggle('sp-toll-var-over', diff > 0.005);
		varEl.classList.toggle('sp-toll-var-under', diff < -0.005);
	}
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
async function exportMileagePDF() {
	if (typeof window.jspdf === 'undefined' || !window.jspdf.jsPDF) {
		alert('PDF library is still loading — please try again in a moment.');
		return;
	}
	const data = await fetchMileageReport();
	if (!data) return;
	const entries = data.entries || [];
	if (!entries.length) { alert('No entries for this period.'); return; }

	const rate = parseFloat(data.rate) || 0;
	const totalMiles = entries.reduce((s, e) => s + parseFloat(e.total_miles || 0), 0);
	// Total = (total miles × rate) rounded once, matching the on-screen header (not the sum
	// of each trip's rounded reimbursement). The per-trip Reimb. column may sum a cent or two off.
	const totalReimb = rate > 0 ? Math.round(totalMiles * rate * 100) / 100 : 0;
	const totalTolls = entries.reduce((s, e) => s + parseFloat(e.total_tolls || 0), 0);
	const totalTrailer = entries.reduce((s, e) => s + parseFloat(e.total_trailer || 0), 0);
	const hasTolls = totalTolls > 0 && rate > 0;       // extra $ columns piggyback the $ columns
	const hasTrailer = totalTrailer > 0 && rate > 0;
	const hasExtras = hasTolls || hasTrailer;
	const grand = totalReimb + totalTolls + totalTrailer;

	const { jsPDF } = window.jspdf;
	const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'letter' });
	const NAVY = [21, 36, 58], SOFT = [230, 234, 241], GREY = [90, 90, 85], FAINT = [154, 154, 149];

	// Header
	doc.setFontSize(18); doc.setTextColor(...NAVY);
	doc.text('Business Mileage Report', 40, 48);
	doc.setFontSize(11); doc.setTextColor(...GREY);
	let y = 66;
	if (data.company_name || data.app_name) { doc.text(String(data.company_name || data.app_name), 40, y); y += 14; }
	if (data.user_name) { doc.text(`Employee: ${data.user_name}`, 40, y); y += 14; }
	doc.text(`Period: ${data.label}`, 40, y); y += 14;
	doc.text(`Generated: ${fmtReportDate(new Date())}`, 40, y);

	// Stat boxes — mirror the on-screen mileage header exactly: Total Miles, Mileage,
	// [Trailer when used], Tolls, Total Reimbursement (same categories, order, gating, and
	// thousands-separated formatting). Cards flow across the top and size to fit their count.
	const statCards = [
		{ label: 'TOTAL MILES', value: mileageNumFmt(totalMiles) },
		{ label: 'MILEAGE',     value: `$${mileageNumFmt(totalReimb)}` },
	];
	if (hasTrailer) statCards.push({ label: 'TRAILER', value: `$${mileageNumFmt(totalTrailer)}` });
	statCards.push({ label: 'TOLLS',               value: `$${mileageNumFmt(totalTolls)}` });
	statCards.push({ label: 'TOTAL REIMBURSEMENT', value: `$${mileageNumFmt(grand)}` });

	const boxY = y + 16, boxH = 52;
	const rowX = 40, rowW = 532, gap = 10;
	const boxW = (rowW - gap * (statCards.length - 1)) / statCards.length;
	const valueFont = statCards.length >= 5 ? 15 : 18;
	doc.setDrawColor(216, 213, 204);
	statCards.forEach((card, i) => {
		const bx = rowX + i * (boxW + gap);
		// Re-assert the box fill each iteration: jsPDF's setTextColor() also mutates the
		// fill color, so the previous card's NAVY value text would otherwise bleed into
		// this card's box fill (leaving dark text on a dark box).
		doc.setFillColor(...SOFT);
		doc.roundedRect(bx, boxY, boxW, boxH, 4, 4, 'FD');
		doc.setFontSize(7);  doc.setTextColor(...GREY); doc.text(card.label, bx + 8, boxY + 14);
		doc.setFontSize(valueFont); doc.setTextColor(...NAVY); doc.text(card.value, bx + 8, boxY + 39);
	});
	doc.setFontSize(8); doc.setTextColor(...FAINT);
	doc.text(`Mileage rate applied: $${rate > 0 ? rate.toFixed(3) : 'N/A'}/mile`, 40, boxY + boxH + 12);

	// Per-entry route lines: [{name, purpose}]. Prefer the structured route_stops; if absent
	// (e.g. older server), split the route string and drop the arrow so jsPDF never sees it.
	const routeData = entries.map(e => {
		if (e.route_stops && e.route_stops.length && typeof e.route_stops[0] === 'object') {
			return e.route_stops.map(s => ({ name: String(s.name || '').trim(), purpose: String(s.purpose || '').trim() }));
		}
		const names = (e.route_stops && e.route_stops.length)
			? e.route_stops
			: String(e.route || '').split(/\s*(?:→|->|!')\s*/);
		return names.map(n => ({ name: String(n).trim(), purpose: '' })).filter(l => l.name);
	});

	const ROUTE_FS = 7.5;
	const lineFactor = (typeof doc.getLineHeightFactor === 'function') ? doc.getLineHeightFactor() : 1.15;

	// Trip table — no Purpose column; purposes are drawn inline (italic) in the Route cell.
	// Money columns are dynamic: Reimb. always (when rate set), then Trailer and/or Tolls
	// only when present, then a Total column when there's any extra. The Route column flexes
	// to fill whatever width the money columns leave.
	const moneyDefs = [];
	if (rate > 0) {
		moneyDefs.push({ head: 'Reimb.', width: 56, val: e => `$${(+e.reimbursement_amount || 0).toFixed(2)}`, foot: `$${totalReimb.toFixed(2)}` });
		if (hasTrailer) moneyDefs.push({ head: 'Trailer', width: 52, val: e => { const t = +e.total_trailer || 0; return t > 0 ? `$${t.toFixed(2)}` : '—'; }, foot: `$${totalTrailer.toFixed(2)}` });
		if (hasTolls) moneyDefs.push({ head: 'Tolls', width: 52, val: e => { const t = +e.total_tolls || 0; return t > 0 ? `$${t.toFixed(2)}` : '—'; }, foot: `$${totalTolls.toFixed(2)}` });
		if (hasExtras) moneyDefs.push({ head: 'Total', width: 56, val: e => `$${((+e.reimbursement_amount || 0) + (+e.total_tolls || 0) + (+e.total_trailer || 0)).toFixed(2)}`, foot: `$${grand.toFixed(2)}` });
	}

	const cols = ['Date', 'Route', 'Miles', ...moneyDefs.map(m => m.head)];
	const rows = entries.map((e, i) => {
		// Layout text (for autoTable's row-height calc); actual draw is custom in didDrawCell.
		const layout = routeData[i].map(l => l.purpose ? `${l.name} — ${l.purpose}` : l.name).join('\n') || '—';
		return [ formatDate(e.entry_date), layout, parseFloat(e.total_miles || 0).toFixed(1), ...moneyDefs.map(m => m.val(e)) ];
	});
	const footMoney = moneyDefs.map(m => m.foot);

	const DATE_W = hasExtras ? 56 : 65, MILES_W = hasExtras ? 40 : 48;
	const moneySum = moneyDefs.reduce((s, m) => s + m.width, 0);
	const ROUTE_W = 532 - DATE_W - MILES_W - moneySum;   // letter portrait usable width = 612 - 80
	const moneyStyles = {};
	moneyDefs.forEach((m, k) => { moneyStyles[3 + k] = { cellWidth: m.width, halign: 'right' }; });

	const ROUTE_COL = 1;
	doc.autoTable({
		startY: boxY + boxH + 24,
		head: [cols],
		body: rows,
		foot: [['', { content: 'Total', styles: { halign: 'right' } }, totalMiles.toFixed(1), ...footMoney]],
		headStyles: { fillColor: NAVY, textColor: 255, fontSize: 9, fontStyle: 'bold' },
		footStyles: { fillColor: SOFT, textColor: NAVY, fontStyle: 'bold', fontSize: 9 },
		bodyStyles: { fontSize: 9, textColor: [26, 26, 24] },
		alternateRowStyles: { fillColor: [248, 248, 245] },
		columnStyles: {
			0: { cellWidth: DATE_W },
			1: { cellWidth: ROUTE_W, fontSize: ROUTE_FS, overflow: 'visible' },
			2: { cellWidth: MILES_W, halign: 'right' },
			...moneyStyles,
		},
		margin: { left: 40, right: 40 },
		// Suppress autoTable's own text for the Route cells so we can draw mixed normal/italic.
		willDrawCell: (d) => {
			if (d.section === 'body' && d.column.index === ROUTE_COL) d.cell.text = [];
		},
		// Custom-draw the Route: store name (normal) + purpose (italic, grey) per line.
		didDrawCell: (d) => {
			if (d.section !== 'body' || d.column.index !== ROUTE_COL) return;
			const lines = routeData[d.row.index] || [];
			if (!lines.length) return;
			const x = d.cell.x + d.cell.padding('left');
			const step = ROUTE_FS * lineFactor;
			let ty = d.cell.y + d.cell.padding('top') + ROUTE_FS;
			doc.setFontSize(ROUTE_FS);
			lines.forEach(l => {
				doc.setFont('helvetica', 'normal'); doc.setTextColor(26, 26, 24);
				doc.text(l.name, x, ty);
				if (l.purpose) {
					const w = doc.getTextWidth(l.name + '  ');
					doc.setFont('helvetica', 'italic'); doc.setTextColor(110, 110, 105);
					doc.text(`— ${l.purpose}`, x + w, ty);
				}
				ty += step;
			});
			doc.setFont('helvetica', 'normal'); doc.setTextColor(26, 26, 24);
		},
	});

	const now = new Date();
	const genISO = `${now.getFullYear()}-${mPad2(now.getMonth() + 1)}-${mPad2(now.getDate())}`;
	const periodSlug = (data.label || '').replace(/[^a-z0-9]+/gi, '-').replace(/^-+|-+$/g, '') || 'all';
	doc.save(`${genISO}_Mileage_Report_${periodSlug}.pdf`);
}

async function exportMileageCSV() {
	const data = await fetchMileageReport();
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
		const pending = locs.filter(l => l.status === 'pending');
		const approved = locs.filter(l => l.status === 'approved');

		// Each settings segment sits in its own card (matches the report-template cards).
		const card = (title, actions = '') => `<div class="sp-settings-card"><div class="sp-settings-card-header"><h3>${title}</h3>${actions ? `<div class="sp-settings-card-actions">${actions}</div>` : ''}</div><div class="sp-settings-card-body">`;
		const cardEnd = '</div></div>';

		let html = card('Rates');
		html += '<div class="sp-admin-mileage-rate">';
		html += '<label>Reimbursement rate ($/mile)</label>';
		html += `<input type="number" step="0.01" min="0" max="5" id="sp-mileage-rate-input" class="sp-input" value="${rate.toFixed(2)}">`;
		html += '<label title="Extra $/mile paid on legs where a trailer is pulled. Set 0 to disable.">Trailer rate (+$/mile)</label>';
		html += `<input type="number" step="0.01" min="0" max="5" id="sp-mileage-trailer-rate-input" class="sp-input" value="${trailerRate.toFixed(2)}">`;
		html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-recompute">Recompute All Distances</button>';
		html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-import-btn">Import Destinations</button>';
		if (D.isGod) {
			html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-test-api">Test Google API</button>';
		}
		html += '</div>';
		if (D.isGod) {
			html += '<div id="sp-mileage-api-test-result" class="sp-mileage-api-test" hidden></div>';
		}
		html += cardEnd;

		// Pay periods — drives the report's "Jump to period" menu. Length 0 = calendar months.
		html += card('Pay Periods');
		html += '<p class="sp-text-secondary">Define your reimbursement period and the report\'s “Jump to” menu will list each period instead of calendar months. Periods are extrapolated indefinitely from the start date below. Leave length at 0 to keep calendar months.</p>';
		html += '<div class="sp-admin-mileage-rate">';
		html += '<label title="Number of days each pay period spans, e.g. 30 for a 25th–24th cycle of a 30-day month.">How long is a period (days)?</label>';
		html += `<input type="number" step="1" min="0" max="366" id="sp-mileage-period-length" class="sp-input" value="${periodLength}">`;
		html += '<label title="Any date a period begins on. Earlier and later periods are calculated from this anchor.">When does a period start?</label>';
		html += `<input type="date" id="sp-mileage-period-anchor" class="sp-input" value="${esc(periodAnchor)}">`;
		html += '</div>';
		html += '<p class="sp-text-secondary" id="sp-mileage-period-preview"></p>';
		html += cardEnd;

		// Optional per-type map marker images (logo/icon). Blank = the default colored dot.
		const markerIcons = res.data.marker_icons || {};
		const MARKER_TYPES = [
			{ key: 'home', label: 'Home base' },
			{ key: 'restaurant', label: 'Restaurant' },
			{ key: 'vendor', label: 'Vendor' },
			{ key: 'office', label: 'Office' },
			{ key: 'other', label: 'Other' },
		];
		html += card('Map Markers');
		html += '<p class="sp-text-secondary">Optional logo/icon image URL per location type, shown on the mileage map. Leave blank to use the default colored dot. A square image around 64–128px (PNG/SVG with transparency) works best.</p>';
		MARKER_TYPES.forEach(t => {
			html += '<div class="sp-form-group" style="max-width:560px;">';
			html += `<label>${t.label} marker image URL</label>`;
			html += `<input type="text" class="sp-input sp-marker-url" data-marker="${t.key}" value="${esc(markerIcons[t.key] || '')}" placeholder="https://rovin.work/wp-content/uploads/marker.png">`;
			html += '</div>';
		});

		html += cardEnd;

		// Destination approval policy.
		html += card('New Destinations');
		html += '<p class="sp-text-secondary">Do new destinations need admin approval before being added to the database? When unchecked, a destination a driver adds goes straight into the database — geocoded and ready to use — with no approval step.</p>';
		html += '<div class="sp-toolbar">';
		html += `<label class="sp-reminder-toggle"><input type="checkbox" id="sp-mileage-require-approval"${requireApproval ? ' checked' : ''}> Require admin approval for new destinations</label>`;
		html += '</div>';
		html += cardEnd;

		const destActions = '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-geocode-missing" title="Add map coordinates to any destinations that are missing them (e.g. the seeded restaurants)">Geocode Missing</button>'
			+ '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-mileage-add-dest">+ Add Destination</button>';
		html += card('Destinations', destActions);

		// With approval off there's no pending queue, so hide that section — unless leftover
		// pending items remain from before it was switched off, so they can still be cleared.
		const showPending = requireApproval || pending.length > 0;
		if (showPending) {
			html += `<h4>Pending (${pending.length})</h4>`;
			if (pending.length === 0) {
				html += '<p class="sp-text-secondary">No pending locations.</p>';
			} else {
				html += '<table class="sp-mileage-table"><thead><tr><th>Name</th><th>Address</th><th>Type</th><th>Added by</th><th></th></tr></thead><tbody>';
				pending.forEach(l => {
					html += '<tr>';
					html += `<td>${esc(l.name)}</td>`;
					html += `<td>${esc(l.address || '')}</td>`;
					html += `<td>${esc(l.location_type)}</td>`;
					html += `<td>${esc(l.created_by_name || '')}</td>`;
					html += `<td class="sp-mileage-row-actions">`;
					html += `<button type="button" class="unique sp-btn sp-btn-primary sp-mileage-approve-btn" data-id="${l.id}">Approve</button>`;
					html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-reject-btn" data-id="${l.id}">Reject</button>`;
					html += `</td></tr>`;
				});
				html += '</tbody></table>';
			}
		}

		// Only sub-label the approved list when a Pending section is also shown; otherwise the
		// "Destinations" header above is the only heading the single list needs.
		if (showPending) html += `<h4>Approved (${approved.length})</h4>`;
		html += '<table class="sp-mileage-table"><thead><tr><th>Name</th><th>Code</th><th>Address</th><th>Category</th><th>Business</th><th></th></tr></thead><tbody>';
		approved.forEach(l => {
			const priv = parseInt(l.is_private) ? ' <span class="unique sp-status-badge sp-status-draft">private</span>' : '';
			html += '<tr>';
			html += `<td>${esc(l.name)}${priv}</td>`;
			html += `<td>${esc(l.code || '')}</td>`;
			html += `<td>${esc(l.address || '')}</td>`;
			html += `<td>${esc(l.category || l.location_type || '')}</td>`;
			html += `<td>${parseInt(l.is_business) ? 'Business' : 'Personal'}</td>`;
			html += `<td>${iconBtn('edit', 'sp-mileage-edit-loc-btn', `data-id="${l.id}"`)}</td>`;
			html += '</tr>';
		});
		html += '</tbody></table>';

		// Daily reminder email (opt-in)
		const hourOpts = Array.from({ length: 24 }, (_, h) => {
			const label = h === 0 ? '12 AM' : h < 12 ? `${h} AM` : h === 12 ? '12 PM' : `${h - 12} PM`;
			return `<option value="${h}"${reminders.hour === h ? ' selected' : ''}>${label}</option>`;
		}).join('');
		html += cardEnd;

		html += card('Daily Reminder Email');
		html += '<p class="sp-text-secondary">Emails active drivers each morning to log the previous day\'s miles (skips anyone who already logged it). Links straight to quick entry.</p>';
		html += '<div class="sp-toolbar">';
		html += `<label class="sp-reminder-toggle"><input type="checkbox" id="sp-mileage-reminder-enabled"${reminders.enabled ? ' checked' : ''}> Send daily reminders</label>`;
		html += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Send at</span><select id="sp-mileage-reminder-hour" class="sp-select">${hourOpts}</select></div>`;
		html += '</div>';

		// Toll pricing (TollGuru). The secret key lives in god-only Site Settings; the
		// operational vehicle type + cost basis live here with the rest of mileage admin.
		const toll = res.data.toll || { key_set: false, vehicle_type: '2AxlesAuto', cost_basis: 'tag' };
		const vTypes = [
			['2AxlesAuto', 'Car / SUV / Pickup (2 axles)'], ['2AxlesTaxi', 'Taxi (2 axles)'], ['2AxlesEV', 'EV (2 axles)'],
			['2AxlesTruck', 'Truck — 2 axles'], ['3AxlesTruck', 'Truck — 3 axles'], ['4AxlesTruck', 'Truck — 4 axles'],
			['5AxlesTruck', 'Truck — 5 axles'], ['6AxlesTruck', 'Truck — 6 axles'],
		];
		html += cardEnd;

		html += card('Toll Pricing');
		html += `<p class="sp-text-secondary">Tolls are priced via TollGuru when a driver checks the Toll box on a leg, then added to reimbursement as a separate line. ${toll.key_set ? 'API key: <strong>set &#10003;</strong>' : 'No API key set yet — add the TollGuru key in <strong>Site Settings</strong> first.'}</p>`;
		html += '<div class="sp-toolbar">';
		html += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Vehicle</span><select id="sp-toll-vehicle" class="sp-select">${vTypes.map(([v, l]) => `<option value="${v}"${toll.vehicle_type === v ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></div>`;
		html += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Reimburse at</span><select id="sp-toll-basis" class="sp-select"><option value="tag"${toll.cost_basis !== 'cash' ? ' selected' : ''}>Tag / transponder rate</option><option value="cash"${toll.cost_basis === 'cash' ? ' selected' : ''}>Cash rate</option></select></div>`;
		html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-toll-save">Save</button>';
		html += '</div>';
		if (D.isGod) {
			const tollLocOpts = approved.map(l => `<option value="${l.id}">${esc(l.name)}</option>`).join('');
			html += '<div class="sp-toolbar" style="margin-top:8px;">';
			html += `<div class="sp-toolbar-group"><span class="sp-toolbar-label">Test toll</span><select id="sp-toll-test-from" class="sp-select"><option value="">From…</option>${tollLocOpts}</select><select id="sp-toll-test-to" class="sp-select"><option value="">To…</option>${tollLocOpts}</select></div>`;
			html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-toll-test">Test Toll API</button>';
			html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-toll-test-polyline" title="Probe whether this key may price tolls along the EXACT Google route (polyline endpoint), instead of letting TollGuru re-route.">Test Polyline Tolls</button>';
			html += '</div>';
			html += '<div id="sp-toll-test-result" class="sp-mileage-api-test" hidden></div>';
		}

		html += cardEnd;

		// Distance matrix (lazy-rendered on demand — can be a large grid).
		html += card('Distance Matrix');
		html += '<p class="sp-text-secondary">Cached miles between approved locations. Click a cell to set a manual value; blue = from Google, green = manual, grey = not computed yet.</p>';
		html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-matrix-toggle">Show matrix</button>';
		html += '<div class="sp-mileage-matrix-wrap" id="sp-mileage-matrix" hidden></div>';
		html += cardEnd;

		// Purpose library editor
		html += card('Trip Purpose Library');
		html += '<p class="sp-text-secondary">Suggestions shown to drivers when they pick a stop\'s business purpose. Tick <strong>Requires note</strong> to prompt the driver for extra detail (e.g. who they met) whenever they choose that purpose.</p>';
		html += '<div class="sp-purpose-rows" id="sp-mileage-purposes-list"></div>';
		html += '<div class="sp-mileage-purpose-add"><input type="text" id="sp-mileage-purpose-new" class="sp-input" placeholder="Add a purpose…"><button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-purpose-add-btn">Add</button></div>';
		html += cardEnd;

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
					: `<span class="sp-purpose-text">${esc(p.label)}</span>`;
				return `<div class="sp-purpose-row" data-i="${i}">${nameCell}`
					+ `<label class="sp-purpose-req unique" title="Prompt the driver for an additional note whenever they pick this purpose"><input type="checkbox" class="sp-purpose-req-cb" data-i="${i}"${p.requires_note ? ' checked' : ''}> Requires note</label>`
					+ iconBtn('edit', 'sp-purpose-edit', `data-i="${i}"`)
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
			e.target.disabled = true;
			e.target.textContent = 'Computing…';
			const r = await spAjax('site_pulse_admin_recompute_mileage_distances', {});
			e.target.disabled = false;
			e.target.textContent = 'Recompute All Distances';
			if (r.success) alert(`${r.data.distances_added} distances stored across ${r.data.locations_processed} locations.`);
			else alert(r.data?.message || 'Error.');
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
		h += '<table class="sp-mileage-table"><thead><tr><th></th><th>Name</th><th>Address</th></tr></thead><tbody>';
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

// Renders the editable distance-matrix grid into `box`.
async function renderMileageMatrix(box) {
	box.innerHTML = '<div class="sp-loading"></div>';
	const res = await spAjax('site_pulse_admin_get_mileage_matrix', {});
	if (!res.success) { box.innerHTML = '<p class="sp-text-warning">Could not load the matrix.</p>'; return; }

	const locs = res.data.locations || [];
	const dist = res.data.distances || {};
	if (locs.length < 2) { box.innerHTML = '<p class="sp-text-secondary">Need at least two approved locations.</p>'; return; }

	const key = (a, b) => (a <= b ? `${a}-${b}` : `${b}-${a}`);

	let html = '<table class="sp-matrix-table"><thead><tr><th></th>';
	locs.forEach(c => { html += `<th title="${esc(c.name)}">${esc(c.name)}</th>`; });
	html += '</tr></thead><tbody>';
	locs.forEach(rLoc => {
		html += `<tr><th title="${esc(rLoc.name)}">${esc(rLoc.name)}</th>`;
		locs.forEach(cLoc => {
			if (rLoc.id === cLoc.id) { html += '<td class="sp-matrix-self">—</td>'; return; }
			const d = dist[key(parseInt(rLoc.id), parseInt(cLoc.id))];
			const cls = !d ? 'sp-matrix-missing' : (d.source === 'manual' ? 'sp-matrix-manual' : 'sp-matrix-api');
			const val = d ? d.miles.toFixed(1) : '+';
			html += `<td class="sp-matrix-cell ${cls}" data-from="${rLoc.id}" data-to="${cLoc.id}" data-from-name="${esc(rLoc.name)}" data-to-name="${esc(cLoc.name)}" data-miles="${d ? d.miles : ''}">${val}</td>`;
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
