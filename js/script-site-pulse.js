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
	restoreViewState();
}


/*--------------------------------------------------------------
# God Mode — Impersonation
--------------------------------------------------------------*/

function initGodMode() {
	const select = $('#sp-god-user-select');
	const resetBtn = $('#sp-god-reset');

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

	if (resetBtn) {
		resetBtn.addEventListener('click', async () => {
			try {
				const res = await spAjax('site_pulse_impersonate', { user_id: 0 });
				if (res.success) window.location.reload();
			} catch (err) {
				alert('Error resetting view.');
			}
		});
	}

	const nukeBtn = $('#sp-god-nuke');
	if (nukeBtn) {
		nukeBtn.addEventListener('click', async () => {
			if (!confirm('Delete ALL reports, action items, and notifications? This cannot be undone.')) return;
			if (!confirm('Are you sure? This wipes everything.')) return;
			try {
				const res = await spAjax('site_pulse_god_nuke', {});
				if (res.success) {
					alert(res.data.message);
					window.location.reload();
				} else {
					alert(res.data?.message || 'Error.');
				}
			} catch (err) { alert('Error clearing data.'); }
		});
	}
}

function markUniqueSpans(root) {
	(root || document.getElementById('sp-app') || document.getElementById('sp-login-wrap') || document).querySelectorAll('span').forEach(function(el) {
		el.classList.add('unique');
	});
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
			closeMobileSidebar();
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

	const toggleBtn = $('.sp-toggle-password', form);
	if (toggleBtn) {
		toggleBtn.addEventListener('click', () => {
			const input = $('#sp-password');
			const isPass = input.type === 'password';
			input.type = isPass ? 'text' : 'password';
			$('.sp-eye-open', toggleBtn).hidden = !isPass;
			$('.sp-eye-closed', toggleBtn).hidden = isPass;
			toggleBtn.setAttribute('aria-label', isPass ? 'Hide password' : 'Show password');
		});
	}

	form.addEventListener('submit', async (e) => {
		e.preventDefault();
		const errEl = $('#sp-login-error');
		const submitBtn = $('#sp-login-submit');
		const btnText = $('.btn-text', submitBtn);
		const btnLoad = $('.btn-loading', submitBtn);

		errEl.textContent = '';
		submitBtn.disabled = true;
		if (btnText) btnText.hidden = true;
		if (btnLoad) btnLoad.hidden = false;

		try {
			const res = await spAjax('site_pulse_login', {
				username: $('#sp-username').value.trim(),
				password: $('#sp-password').value,
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

	loadNotificationCount();
	setInterval(loadNotificationCount, 60000);
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

	$$('.sp-widget-link').forEach(btn => {
		btn.addEventListener('click', () => activatePanel(btn.dataset.nav));
	});
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
				const navTarget = D.userCaps?.includes('view_all_reports') || D.userCaps?.includes('view_team_reports') ? 'reports-review' : 'reports-my';
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

		const template = templates[0];
		let fields = [];
		let answers = {};

		if (existingReport) {
			const detail = await spAjax('site_pulse_get_report_detail', { report_id: existingReport.id });
			if (detail.success) {
				fields = detail.data.fields || [];
				(detail.data.answers || []).forEach(a => { answers[a.field_key] = a.answer_text || ''; });
			}
		} else {
			const tplFields = await loadTemplateFields(template.id);
			fields = tplFields;
		}

		renderReportForm(wrap, template, fields, answers, existingReport);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading form.</p>';
	}
}

async function loadTemplateFields(templateId) {
	try {
		const res = await spAjax('site_pulse_get_template_fields', { template_id: templateId });
		return res.success ? (res.data.fields || []) : [];
	} catch (e) {
		return [];
	}
}

function renderReportForm(wrap, template, fields, answers, existingReport) {
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
		html += '<div class="sp-form-group">';
		html += `<label for="sp-field-${f.field_key}">${esc(f.label)}${f.is_required == 1 ? ' <span class="sp-text-danger">*</span>' : ''}</label>`;

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

	// Navigation bar
	let html = '<div class="sp-detail-nav">';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-back-btn">&larr; Back to Reports</button>';
	html += '<div class="sp-detail-nav-arrows">';
	html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-prev"${hasPrev ? '' : ' disabled'}>&lsaquo; Previous</button>`;
	html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-detail-next"${hasNext ? '' : ' disabled'}>Next &rsaquo;</button>`;
	html += '</div></div>';

	// Header info bar
	const headerFields = D.reportHeaderFields || [];
	const headerCount = headerFields.length + 3;
	html += `<div class="sp-report-header-grid" style="display:grid;grid-template-columns:repeat(${headerCount}, 1fr);gap:12px;margin-bottom:20px;padding:16px 20px;background:var(--sp-bg);border-radius:var(--sp-radius);border:1px solid var(--sp-border);">`;

	headerFields.forEach(hf => {
		const val = calcHeaderValue(hf.calc, report.report_period_start);
		html += `<div><div class="sp-card-label">${esc(hf.label)}</div><div class="sp-card-value" style="font-size:16px;">${esc(val)}</div></div>`;
	});

	html += `<div><div class="sp-card-label">Date</div><div class="sp-card-value" style="font-size:16px;">${formatDate(report.report_period_start)}</div></div>`;
	html += `<div><div class="sp-card-label">Location</div><div class="sp-card-value" style="font-size:16px;">${esc(location?.name || '—')}</div></div>`;
	if (author) {
		html += `<div><div class="sp-card-label">Submitted By</div><div class="sp-card-value" style="font-size:16px;">${esc(author.name)}</div></div>`;
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

	list.innerHTML = '<div class="sp-loading"></div>';

	const filters = {
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
	} catch (err) {}
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
	const locsContent = $('#sp-admin-locations-content');
	const tplsContent = $('#sp-admin-templates-content');
	const settingsContent = $('#sp-admin-settings-content');

	if (usersContent) loadAdminUsers();
	if (locsContent) loadAdminLocations();
	if (tplsContent) loadAdminTemplates();
	if (settingsContent) loadAdminSettings();

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

	let html = '<div class="sp-admin-toolbar">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-user-btn">+ Add User</button>';
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
			html += `<td><button type="button" class="unique sp-btn sp-btn-ghost sp-edit-user-btn" data-user-id="${u.user_id}">Edit</button></td>`;
			html += '</tr>';
		});
		html += '</tbody></table></div>';
	}

	html += '<div id="sp-user-form-wrap" hidden></div>';
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-add-user-btn')?.addEventListener('click', () => showUserForm(null, roles, locations, users));
	$$('.sp-edit-user-btn', wrap).forEach(btn => {
		btn.addEventListener('click', () => {
			const uid = btn.dataset.userId;
			const user = users.find(u => String(u.user_id) === uid);
			showUserForm(user, roles, locations, users);
		});
	});
}

function showUserForm(user, roles, locations, allUsers) {
	const wrap = $('#sp-user-form-wrap');
	if (!wrap) return;
	wrap.hidden = false;

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
		html += '<input type="text" name="password" class="sp-input" placeholder="Leave blank to auto-generate"></div>';
	}

	html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
	html += `<div class="sp-form-group"><label>Role</label><select name="role_id" class="sp-select" required>${roleOptions}</select></div>`;
	html += `<div class="sp-form-group"><label>Location</label><select name="location_id" class="sp-select">${locOptions}</select></div>`;
	html += '</div>';

	html += `<div class="sp-form-group"><label>Supervisor</label><select name="supervisor_id" class="sp-select">${supervisorOptions}</select></div>`;

	if (isEdit) {
		html += '<div class="sp-form-group"><label>New Password</label>';
		html += '<input type="text" name="new_password" class="sp-input" placeholder="Leave blank to keep current"></div>';

		html += `<div class="sp-form-group"><label>Status</label><select name="status" class="sp-select">`;
		html += `<option value="active"${user.status === 'active' ? ' selected' : ''}>Active</option>`;
		html += `<option value="inactive"${user.status === 'inactive' ? ' selected' : ''}>Inactive</option>`;
		html += '</select></div>';
	}

	html += '<div class="sp-report-form-actions">';
	html += `<button type="submit" class="unique sp-btn sp-btn-primary">${isEdit ? 'Save Changes' : 'Create User'}</button>`;
	html += '<button type="button" class="unique sp-btn sp-btn-secondary sp-user-form-cancel">Cancel</button>';
	html += '</div></form></div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-user-form-cancel', wrap).forEach(btn => {
		btn.addEventListener('click', () => { wrap.hidden = true; wrap.innerHTML = ''; });
	});

	$('#sp-user-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;

		const action = isEdit ? 'site_pulse_admin_update_user' : 'site_pulse_admin_create_user';
		try {
			const res = await spAjax(action, data);
			if (res.success) {
				if (!isEdit && res.data.password) {
					alert('User created. Password: ' + res.data.password);
				}
				wrap.hidden = true;
				wrap.innerHTML = '';
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
		html += '<thead><tr><th>Name</th><th>Type</th><th>City</th><th>State</th><th>Status</th><th></th></tr></thead>';
		html += '<tbody>';
		locations.forEach(l => {
			const statusClass = l.status === 'active' ? 'sp-status-submitted' : 'sp-status-draft';
			html += `<tr data-loc-id="${l.id}">`;
			html += `<td>${esc(l.name)}</td>`;
			html += `<td>${esc(l.location_type)}</td>`;
			html += `<td>${esc(l.city || '')}</td>`;
			html += `<td>${esc(l.state || '')}</td>`;
			html += `<td><span class="unique sp-status-badge ${statusClass}">${l.status}</span></td>`;
			html += `<td><button type="button" class="unique sp-btn sp-btn-ghost sp-edit-loc-btn" data-loc-id="${l.id}">Edit</button></td>`;
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
}

function showLocationForm(loc) {
	const wrap = $('#sp-location-form-wrap');
	if (!wrap) return;
	wrap.hidden = false;

	const isEdit = !!loc;
	const title = isEdit ? 'Edit Location' : 'Add Location';

	let html = '<div class="sp-report-form-wrap" style="margin-top:20px;">';
	html += `<div class="sp-report-form-header"><h3>${title}</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-loc-form-cancel">Cancel</button></div>';
	html += '<form id="sp-location-form">';

	if (isEdit) html += `<input type="hidden" name="id" value="${loc.id}">`;

	html += '<div class="sp-form-group"><label>Location Name</label>';
	html += `<input type="text" name="name" class="sp-input" value="${esc(loc?.name || '')}" required></div>`;

	html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
	html += '<div class="sp-form-group"><label>Type / Brand</label>';
	html += `<input type="text" name="location_type" class="sp-input" value="${esc(loc?.location_type || '')}" placeholder="e.g. Babe's Chicken"></div>`;
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
		btn.addEventListener('click', () => { wrap.hidden = true; wrap.innerHTML = ''; });
	});

	$('#sp-location-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;

		try {
			const res = await spAjax('site_pulse_admin_save_location', data);
			if (res.success) {
				wrap.hidden = true;
				wrap.innerHTML = '';
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
		renderActionItems(list, res.data.items);
	} catch (err) {
		list.innerHTML = '<div class="sp-empty-state"><p>Error loading action items.</p></div>';
	}
}

function renderActionItems(container, items) {
	if (!items || items.length === 0) {
		container.innerHTML = '<div class="sp-empty-state"><p>No action items.</p></div>';
		return;
	}

	const sortMode = $('#sp-action-sort')?.value || 'importance';
	const priorityOrder = { high: 0, medium: 1, low: 2 };

	if (sortMode === 'importance') {
		items.sort((a, b) => (priorityOrder[a.priority] ?? 1) - (priorityOrder[b.priority] ?? 1));
	}
	// 'custom' uses the display_order from the DB, which is how items arrive by default

	let html = '';
	items.forEach(item => {
		const isOpen = item.status === 'open';
		const priorityClass = item.priority === 'high' ? 'sp-priority-high' : item.priority === 'medium' ? 'sp-priority-medium' : 'sp-priority-low';
		const resolvedInfo = !isOpen && item.resolved_at ? `<div class="sp-action-resolved">Resolved ${timeAgo(item.resolved_at)}${item.resolution_note ? ' — ' + esc(item.resolution_note) : ''}</div>` : '';

		html += `<div class="sp-action-item ${isOpen ? '' : 'sp-action-resolved-item'} ${priorityClass}" data-item-id="${item.id}"${isOpen ? ' draggable="true"' : ''}>`;
		if (isOpen) html += '<span class="sp-action-drag">&#9776;</span>';
		html += '<div class="sp-action-item-content">';

		// Show history if this is a follow-up item
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
		if (isOpen) {
			html += `<button type="button" class="unique sp-btn sp-btn-secondary sp-resolve-item-btn" data-item-id="${item.id}">Resolve</button>`;
		}
		html += '</div>';
	});

	container.innerHTML = html;
	markUniqueSpans(container);

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
	let html = '<h3 style="margin:0 0 20px;">AI Configuration</h3>';

	html += '<div class="sp-form-group">';
	html += '<label>Claude API Key</label>';
	if (data.claude_api_key_set) {
		html += `<div style="margin-bottom:8px;"><span class="unique sp-status-badge sp-status-submitted">Active</span> <span class="unique" style="color:var(--sp-text-light);font-size:13px;">${esc(data.claude_api_key_masked)}</span></div>`;
	}
	html += '<div style="display:grid;grid-template-columns:1fr auto;gap:8px;">';
	html += '<input type="text" id="sp-setting-api-key" class="sp-input" placeholder="Enter Claude API key (sk-ant-...)">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-save-api-key">Save Key</button>';
	html += '</div>';
	html += '<div class="sp-help-text">Get your API key from <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a>. Required for AI-powered action items and insights.</div>';
	html += '</div>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$('#sp-save-api-key')?.addEventListener('click', async () => {
		const key = $('#sp-setting-api-key')?.value?.trim();
		if (!key) { alert('Please enter an API key.'); return; }

		try {
			const res = await spAjax('site_pulse_admin_save_setting', { key: 'claude_api_key', value: key });
			if (res.success) {
				$('#sp-setting-api-key').value = '';
				loadAdminSettings();
			} else {
				alert(res.data?.message || 'Error saving.');
			}
		} catch (err) { alert('Error saving API key.'); }
	});
}


/* ---- Report Templates ---- */

async function loadAdminTemplates() {
	const wrap = $('#sp-admin-templates-content');
	if (!wrap) return;
	wrap.innerHTML = '<div class="sp-loading"></div>';

	try {
		const res = await spAjax('site_pulse_admin_get_templates', {});
		if (!res.success) { wrap.innerHTML = '<p>Error loading templates.</p>'; return; }
		renderAdminTemplates(wrap, res.data.templates);
	} catch (err) {
		wrap.innerHTML = '<p>Error loading templates.</p>';
	}
}

function renderAdminTemplates(wrap, templates) {
	let html = '<div class="sp-admin-toolbar">';
	html += '<button type="button" class="unique sp-btn sp-btn-primary" id="sp-add-template-btn">+ Add Template</button>';
	html += '</div>';

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
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-edit-template-btn" data-id="${t.id}">Edit</button>`;
			html += '</div></div>';
			html += `<div class="sp-template-meta">${esc(t.frequency)} &middot; ${esc(t.required_role_slug)} &middot; ${fieldCount} field${fieldCount !== 1 ? 's' : ''}</div>`;

			html += '<div class="sp-template-fields">';
			if (t.fields && t.fields.length > 0) {
				html += '<div class="sp-field-list">';
				t.fields.forEach((f, i) => {
					const archived = f.display_order >= 999 ? ' sp-field-archived' : '';
					html += `<div class="sp-field-item${archived}" data-field-id="${f.id}" data-index="${i}" draggable="true">`;
					html += `<span class="sp-field-drag">&#9776;</span>`;
					html += `<span class="sp-field-label">${esc(f.label)}</span>`;
					html += `<span class="sp-field-type">${esc(f.field_type)}</span>`;
					html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-edit-field-btn" data-field-id="${f.id}" data-template-id="${t.id}">Edit</button>`;
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

	$('#sp-add-template-btn')?.addEventListener('click', () => showTemplateForm(null));
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
	const wrap = $('#sp-template-form-wrap');
	if (!wrap) return;
	wrap.hidden = false;

	const isEdit = !!tpl;
	const title = isEdit ? 'Edit Template' : 'Add Template';

	let html = '<div class="sp-report-form-wrap" style="margin-top:20px;">';
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
	['manager','supervisor','admin','owner'].forEach(r => {
		html += `<option value="${r}"${tpl?.required_role_slug === r ? ' selected' : ''}>${r.charAt(0).toUpperCase() + r.slice(1)}</option>`;
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
		btn.addEventListener('click', () => { wrap.hidden = true; wrap.innerHTML = ''; });
	});

	$('#sp-template-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;
		try {
			const res = await spAjax('site_pulse_admin_save_template', data);
			if (res.success) {
				wrap.hidden = true;
				wrap.innerHTML = '';
				loadAdminTemplates();
			} else alert(res.data?.message || 'Error.');
		} catch (err) { alert('Error saving template.'); }
	});
}

function showFieldForm(field, templateId) {
	const wrap = $('#sp-field-form-wrap');
	if (!wrap) return;
	wrap.hidden = false;

	const isEdit = !!field;
	const title = isEdit ? 'Edit Field' : 'Add Field';

	let html = '<div class="sp-report-form-wrap" style="margin-top:20px;">';
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
		btn.addEventListener('click', () => { wrap.hidden = true; wrap.innerHTML = ''; });
	});

	$('#sp-field-form')?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(e.target);
		const data = {};
		for (const [key, val] of formData.entries()) data[key] = val;
		try {
			const res = await spAjax('site_pulse_admin_save_field', data);
			if (res.success) {
				wrap.hidden = true;
				wrap.innerHTML = '';
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

})();
