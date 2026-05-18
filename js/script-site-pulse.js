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
	initMileage();
	initAdminMileage();
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
	html += '</div>';
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
	$('.sp-detail-new-btn', wrap)?.addEventListener('click', () => showReportForm());

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


/*--------------------------------------------------------------
# Mileage — Manager
--------------------------------------------------------------*/

let mileageLocations = [];
let mileageRate = 0.67;

function initMileage() {
	const panel = $('#sp-panel-mileage');
	if (!panel) return;

	$('#sp-mileage-add-btn')?.addEventListener('click', () => showMileageForm());
	$('#sp-mileage-print-btn')?.addEventListener('click', () => printMileageLog());
	$('#sp-mileage-email-btn')?.addEventListener('click', () => emailMileageLog());
	$('#sp-mileage-filter-clear')?.addEventListener('click', () => {
		$('#sp-mileage-filter-start').value = '';
		$('#sp-mileage-filter-end').value = '';
		loadMileageEntries();
	});
	$('#sp-mileage-filter-start')?.addEventListener('change', loadMileageEntries);
	$('#sp-mileage-filter-end')?.addEventListener('change', loadMileageEntries);

	loadMileageLocations().then(() => loadMileageEntries());
}

async function loadMileageLocations() {
	try {
		const res = await spAjax('site_pulse_get_mileage_locations', {});
		if (res.success) {
			mileageLocations = res.data.locations || [];
			mileageRate = parseFloat(res.data.rate) || 0.67;
		}
	} catch (e) { mileageLocations = []; }
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
		const totalAmt = entries.reduce((s, e) => s + parseFloat(e.reimbursement_amount || 0), 0);
		const pendingCount = entries.reduce((s, e) => s + (parseInt(e.pending_legs) > 0 ? 1 : 0), 0);

		summary.innerHTML = `
			<div class="sp-mileage-summary-grid">
				<div><div class="sp-card-label">Entries</div><div class="sp-card-value">${entries.length}</div></div>
				<div><div class="sp-card-label">Total Miles</div><div class="sp-card-value">${totalMiles.toFixed(2)}</div></div>
				<div><div class="sp-card-label">Reimbursement</div><div class="sp-card-value">$${totalAmt.toFixed(2)}</div></div>
				<div><div class="sp-card-label">Rate</div><div class="sp-card-value">$${mileageRate.toFixed(2)}/mi</div></div>
				${pendingCount > 0 ? `<div><div class="sp-card-label">Pending</div><div class="sp-card-value sp-text-warning">${pendingCount}</div></div>` : ''}
			</div>
		`;

		let html = '<table class="sp-mileage-table"><thead><tr><th>Date</th><th>Stops</th><th>Miles</th><th>$</th><th>Status</th><th></th></tr></thead><tbody>';
		entries.forEach(e => {
			const isPending = parseInt(e.pending_legs) > 0;
			html += `<tr data-entry-id="${e.id}">`;
			html += `<td>${formatDate(e.entry_date)}</td>`;
			html += `<td class="sp-mileage-path-cell"><span class="unique sp-mileage-path-loading">Loading…</span></td>`;
			html += `<td>${parseFloat(e.total_miles).toFixed(2)}</td>`;
			html += `<td>$${parseFloat(e.reimbursement_amount).toFixed(2)}</td>`;
			html += `<td>${isPending ? '<span class="unique sp-status-badge sp-status-pending">Pending</span>' : '<span class="unique sp-status-badge sp-status-submitted">Final</span>'}</td>`;
			html += `<td class="sp-mileage-row-actions">`;
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-edit-btn" data-id="${e.id}">Edit</button>`;
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-delete-btn" data-id="${e.id}">Delete</button>`;
			html += `</td></tr>`;
		});
		html += '</tbody></table>';
		list.innerHTML = html;
		markUniqueSpans(list);

		// Lazy-load each entry's path inline
		entries.forEach(async (e) => {
			try {
				const r = await spAjax('site_pulse_get_mileage_entry', { entry_id: e.id });
				if (!r.success) return;
				const legs = r.data.legs || [];
				const cell = list.querySelector(`tr[data-entry-id="${e.id}"] .sp-mileage-path-cell`);
				if (!cell) return;
				if (legs.length === 0) { cell.textContent = '—'; return; }
				let path = esc(legs[0].from_name || '?');
				legs.forEach(leg => { path += ' → ' + esc(leg.to_name || '?'); });
				cell.innerHTML = path;
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

async function showMileageForm(entryId = 0) {
	const wrap = $('#sp-mileage-form-wrap');
	const list = $('#sp-mileage-list');
	const summary = $('#sp-mileage-summary');
	if (!wrap) return;

	await loadMileageLocations();

	let entry = null;
	let stops = [];
	if (entryId) {
		const res = await spAjax('site_pulse_get_mileage_entry', { entry_id: entryId });
		if (res.success) {
			entry = res.data.entry;
			const legs = res.data.legs || [];
			if (legs.length > 0) {
				stops.push(parseInt(legs[0].from_location_id));
				legs.forEach(l => stops.push(parseInt(l.to_location_id)));
			}
		}
	}
	if (stops.length < 2) stops = [0, 0];

	list.classList.add('sp-hidden');
	if (summary) summary.classList.add('sp-hidden');
	wrap.hidden = false;

	const today = new Date().toISOString().split('T')[0];
	const date = entry?.entry_date || today;

	let html = '<div class="sp-report-form-header">';
	html += `<h3>${entryId ? 'Edit' : 'New'} Mileage Day</h3>`;
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-back-btn">Back</button>';
	html += '</div>';
	html += '<form id="sp-mileage-form">';
	html += `<input type="hidden" name="entry_id" value="${entryId || ''}">`;
	html += '<div class="sp-form-group">';
	html += `<label>Date</label><input type="date" name="entry_date" class="sp-input" value="${date}" required>`;
	html += '</div>';
	html += '<div class="sp-form-group">';
	html += '<label>Route</label>';
	html += '<div class="sp-mileage-stops" id="sp-mileage-stops"></div>';
	html += '<div class="sp-mileage-stop-actions">';
	html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-add-stop">+ Add Stop</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-add-loc">+ Add New Location</button>';
	html += '</div>';
	html += '<div class="sp-mileage-totals" id="sp-mileage-totals"></div>';
	html += '</div>';
	html += '<div class="sp-form-group">';
	html += `<label>Notes (optional)</label><textarea name="notes" class="sp-textarea" rows="2">${esc(entry?.notes || '')}</textarea>`;
	html += '</div>';
	html += '<div class="sp-report-form-actions">';
	html += '<button type="submit" class="unique sp-btn sp-btn-primary">Save</button>';
	html += '<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-back-btn">Cancel</button>';
	html += '</div>';
	html += '</form>';

	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	renderMileageStops(stops);

	$$('.sp-mileage-back-btn', wrap).forEach(b => b.addEventListener('click', () => hideMileageForm()));
	$('#sp-mileage-add-stop', wrap)?.addEventListener('click', () => {
		const current = readMileageStops();
		current.push(0);
		renderMileageStops(current);
	});
	$('#sp-mileage-add-loc', wrap)?.addEventListener('click', () => showAddLocationModal());

	$('#sp-mileage-form', wrap)?.addEventListener('submit', async (e) => {
		e.preventDefault();
		const stopsArr = readMileageStops().filter(v => v > 0);
		if (stopsArr.length < 2) { alert('Please pick at least two stops.'); return; }
		const data = {
			entry_id: entryId || '',
			entry_date: e.target.entry_date.value,
			notes: e.target.notes.value,
			stops: stopsArr,
		};
		const r = await spAjax('site_pulse_save_mileage_entry', data);
		if (r.success) { hideMileageForm(); loadMileageEntries(); }
		else alert(r.data?.message || 'Error saving.');
	});
}

function hideMileageForm() {
	const wrap = $('#sp-mileage-form-wrap');
	const list = $('#sp-mileage-list');
	const summary = $('#sp-mileage-summary');
	if (wrap) { wrap.hidden = true; wrap.innerHTML = ''; }
	list?.classList.remove('sp-hidden');
	summary?.classList.remove('sp-hidden');
}

function renderMileageStops(stops) {
	const wrap = $('#sp-mileage-stops');
	if (!wrap) return;
	let html = '';
	stops.forEach((stopId, idx) => {
		html += '<div class="sp-mileage-stop">';
		html += `<span class="unique sp-mileage-stop-num">${idx + 1}.</span>`;
		html += `<select class="sp-select sp-mileage-stop-select">`;
		html += '<option value="0">Choose a location…</option>';
		mileageLocations.forEach(l => {
			const sel = parseInt(l.id) === stopId ? ' selected' : '';
			const label = l.status === 'pending' ? `${l.name} (pending)` : l.name;
			html += `<option value="${l.id}"${sel}>${esc(label)}</option>`;
		});
		html += '</select>';
		if (stops.length > 2) {
			html += `<button type="button" class="unique sp-btn sp-btn-ghost sp-mileage-stop-remove" data-idx="${idx}" aria-label="Remove stop">&times;</button>`;
		}
		html += '</div>';
	});
	wrap.innerHTML = html;
	markUniqueSpans(wrap);

	$$('.sp-mileage-stop-select', wrap).forEach(s => s.addEventListener('change', () => updateMileageTotals()));
	$$('.sp-mileage-stop-remove', wrap).forEach(b => b.addEventListener('click', () => {
		const current = readMileageStops();
		current.splice(parseInt(b.dataset.idx), 1);
		if (current.length < 2) current.push(0);
		renderMileageStops(current);
		updateMileageTotals();
	}));

	updateMileageTotals();
}

function readMileageStops() {
	return $$('.sp-mileage-stop-select').map(s => parseInt(s.value) || 0);
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
	if (valid.length < 2) { totals.innerHTML = ''; return; }

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

	const modal = document.createElement('div');
	modal.id = 'sp-mileage-loc-modal';
	modal.className = 'sp-modal-backdrop';
	modal.innerHTML = `
		<div class="sp-modal">
			<h3>Add a New Location</h3>
			<p class="sp-text-secondary">It'll be sent for admin approval. Once approved, the distance will be calculated automatically.</p>
			<div class="sp-form-group"><label>Name</label><input type="text" id="sp-loc-name" class="sp-input" placeholder="Big D Mechanical"></div>
			<div class="sp-form-group"><label>Address</label><input type="text" id="sp-loc-address" class="sp-input" placeholder="123 Main St, City, TX 75001"></div>
			<div class="sp-form-group"><label>Type</label><select id="sp-loc-type" class="sp-select"><option value="vendor">Vendor</option><option value="other">Other</option></select></div>
			<div class="sp-modal-actions">
				<button type="button" class="unique sp-btn sp-btn-primary" id="sp-loc-submit">Submit for Approval</button>
				<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-loc-cancel">Cancel</button>
			</div>
		</div>
	`;
	document.body.appendChild(modal);
	markUniqueSpans(modal);

	const close = () => modal.remove();
	modal.querySelector('#sp-loc-cancel').addEventListener('click', close);
	modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
	modal.querySelector('#sp-loc-submit').addEventListener('click', async () => {
		const name = modal.querySelector('#sp-loc-name').value.trim();
		const address = modal.querySelector('#sp-loc-address').value.trim();
		const type = modal.querySelector('#sp-loc-type').value;
		if (!name || !address) { alert('Name and address required.'); return; }
		const r = await spAjax('site_pulse_add_mileage_location', { name, address, location_type: type });
		if (r.success) {
			await loadMileageLocations();
			const stops = readMileageStops();
			// Auto-fill the first empty stop with the new location
			const emptyIdx = stops.findIndex(v => !v);
			if (emptyIdx >= 0) stops[emptyIdx] = parseInt(r.data.id);
			else stops.push(parseInt(r.data.id));
			renderMileageStops(stops);
			close();
		} else {
			alert(r.data?.message || 'Error.');
		}
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
		const pending = locs.filter(l => l.status === 'pending');
		const approved = locs.filter(l => l.status === 'approved');

		let html = '<div class="sp-admin-mileage-rate">';
		html += '<label>Reimbursement rate ($/mile)</label>';
		html += `<input type="number" step="0.01" min="0" max="5" id="sp-mileage-rate-input" class="sp-input" value="${rate.toFixed(2)}">`;
		html += '<button type="button" class="unique sp-btn sp-btn-secondary" id="sp-mileage-rate-save">Save Rate</button>';
		html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-recompute">Recompute All Distances</button>';
		if (D.isGod) {
			html += '<button type="button" class="unique sp-btn sp-btn-ghost" id="sp-mileage-test-api">Test Google API</button>';
		}
		html += '</div>';
		if (D.isGod) {
			html += '<div id="sp-mileage-api-test-result" class="sp-mileage-api-test" hidden></div>';
		}

		html += `<h3>Pending (${pending.length})</h3>`;
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

		html += `<h3>Approved (${approved.length})</h3>`;
		html += '<table class="sp-mileage-table"><thead><tr><th>Name</th><th>Address</th><th>Type</th></tr></thead><tbody>';
		approved.forEach(l => {
			html += '<tr>';
			html += `<td>${esc(l.name)}</td>`;
			html += `<td>${esc(l.address || '')}</td>`;
			html += `<td>${esc(l.location_type)}</td>`;
			html += '</tr>';
		});
		html += '</tbody></table>';

		wrap.innerHTML = html;
		markUniqueSpans(wrap);

		$('#sp-mileage-rate-save', wrap)?.addEventListener('click', async () => {
			const rate = parseFloat($('#sp-mileage-rate-input').value);
			const r = await spAjax('site_pulse_admin_save_mileage_rate', { rate });
			if (r.success) alert('Rate saved.');
			else alert(r.data?.message || 'Error.');
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

})();
