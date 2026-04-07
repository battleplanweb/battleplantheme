/**
 * CCSO Overtime Scheduling — Front-end Script
 * Pure vanilla JS. Depends on: schedulesData (wp_localize_script)
 */

(function () {
    'use strict';

    // Guard: schedulesData is injected by wp_localize_script.
    // If it's missing the script was not properly enqueued — bail out clearly.
    if (typeof schedulesData === 'undefined') {
        console.error('Schedules: schedulesData not found. Check that the schedules module is active and wp_localize_script is running on this page.');
        return;
    }

    /*--------------------------------------------------------------
    # Utility: HTML Escape
    --------------------------------------------------------------*/

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }

    function formatName(m) {
        if ((schedulesData.dutyNameFormat || 'first_last') === 'last_first' && m.last_name && m.first_name) {
            return m.last_name + ', ' + m.first_name;
        }
        return m.display_name;
    }

    /*--------------------------------------------------------------
    # Utility: AJAX Helper
    --------------------------------------------------------------*/

    /**
     * POST to admin-ajax.php, always including the nonce.
     * Returns a Promise resolving to the parsed JSON response.
     */
    // Active proxy member — set when supervisor assumes a member's identity
    var scheduleProxyUserId = '';
    var scheduleResetProxy  = null; // set by proxy IIFE; called when leaving Schedule group

    function schedulesAjax(data) {
        var params = new URLSearchParams();
        params.append('nonce', schedulesData.nonce);
        if (scheduleProxyUserId) params.append('proxy_user_id', scheduleProxyUserId);
        for (var key in data) {
            if (Object.prototype.hasOwnProperty.call(data, key)) {
                params.append(key, data[key]);
            }
        }
        return fetch(schedulesData.ajaxUrl, {
            method      : 'POST',
            credentials : 'same-origin',
            headers     : { 'Content-Type': 'application/x-www-form-urlencoded' },
            body        : params.toString(),
        }).then(function (r) { return r.json(); });
    }

    // Build grouped <optgroup> HTML from an array of {id, name, role, shift} objects.
    // Groups: Members (shift A/B/C/D), Overtimers (member, no shift), Supervisors, Admin
    function buildGroupedOptions(members, blankLabel) {
        var shiftLetters = ['A', 'B', 'C', 'D'];
        var groups = { member: [], overtimer: [], supervisor: [], admin: [] };
        members.forEach(function (m) {
            var r = m.role || 'member';
            if (r === 'member') {
                var s = (m.shift || '').toUpperCase();
                r = shiftLetters.indexOf(s) !== -1 ? 'member' : 'overtimer';
            }
            groups[r].push(m);
        });
        var labels = { member: 'Members', overtimer: 'Overtimers', supervisor: 'Supervisors', admin: 'Admin' };
        var html = blankLabel ? '<option value="">' + blankLabel + '</option>' : '';
        ['member', 'overtimer', 'supervisor', 'admin'].forEach(function (r) {
            var list = groups[r];
            if (!list.length) return;
            list.sort(function (a, b) { return a.name.localeCompare(b.name); });
            html += '<optgroup label="' + labels[r] + '">';
            list.forEach(function (m) {
                html += '<option value="' + esc(String(m.id)) + '">' + esc(m.name) + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }

    /*--------------------------------------------------------------
    # Notification Panel
    --------------------------------------------------------------*/

    // Toggle panel
    document.addEventListener('click', function (e) {
        var bell = e.target.closest('#notif-bell');
        if (!bell) return;

        var panel = document.getElementById('notif-panel');
        if (!panel) return;

        if (panel.hidden) {
            panel.hidden = false;
            loadNotifications();
        } else {
            panel.hidden = true;
        }
    });

    // Close panel
    document.addEventListener('click', function (e) {
        if (e.target.closest('.notif-panel-close')) {
            var panel = document.getElementById('notif-panel');
            if (panel) panel.hidden = true;
        }
    });

    // Poll for new notifications every 60 seconds
    var notifLastCount = parseInt((document.querySelector('.notif-count') || {}).textContent || '0', 10);

    function pollNotifCount() {
        schedulesAjax({ action: 'schedules_get_unread_count' }).then(function (res) {
            if (!res.success) return;
            var count = res.data.count;
            var bell  = document.getElementById('notif-bell');
            if (!bell) return;

            var badge = bell.querySelector('.notif-count');

            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notif-count';
                    bell.appendChild(badge);
                }
                badge.textContent = count;
            } else {
                if (badge) badge.remove();
            }

            // If new notifications arrived, flash the bell
            if (count > notifLastCount) {
                bell.classList.add('new-notif');
                setTimeout(function () { bell.classList.remove('new-notif'); }, 2000);

                // If the panel is already open, silently reload it
                var panel = document.getElementById('notif-panel');
                if (panel && !panel.hidden) {
                    loadNotifications();
                }
            }

            notifLastCount = count;
        }).catch(function () { /* silently ignore poll failures */ });
    }

    if (document.getElementById('notif-bell')) {
        setInterval(pollNotifCount, 60000);
    }

    var notifShowArchived = false;

    function loadNotifications() {
        var body = document.getElementById('notif-panel-body');
        if (!body) return;
        body.innerHTML = '<p class="notif-loading">Loading\u2026</p>';

        var payload = { action: 'schedules_get_notifications' };
        if (notifShowArchived) payload.include_archived = '1';

        schedulesAjax(payload).then(function (res) {
            if (!res.success) {
                body.innerHTML = '<p class="notif-empty">Failed to load notifications.</p>';
                return;
            }

            var items = res.data.notifications;
            if (!items.length) {
                body.innerHTML = '<p class="notif-empty">No notifications.</p>';
                return;
            }

            var html = '';
            items.forEach(function (n) {
                var cls = 'notif-item' + (n.is_read ? ' notif-read' : '') + (n.is_archived ? ' notif-archived' : '');
                html += '<div class="' + cls + '" data-id="' + n.id + '">';
                if (!n.is_archived) {
                    html += '<button class="basic-btn notif-dismiss" data-id="' + n.id + '" aria-label="Archive">&times;</button>';
                }
                html += '<div class="notif-message">' + esc(n.message) + '</div>';
                html += '<div class="notif-time">' + formatNotifTime(n.created_at) + '</div>';

                // Review buttons for pending PDO requests (not shown on archived items)
                if (n.type === 'timeoff_request' && n.related_id && !n.is_archived) {
                    html += '<div class="notif-actions">';
                    html += '<button class="schedules-btn schedules-btn-primary notif-review-btn" data-timeoff-id="' + n.related_id + '" data-decision="approved">Approve</button>';
                    html += '<button class="schedules-btn schedules-btn-secondary notif-review-btn" data-timeoff-id="' + n.related_id + '" data-decision="coverage">Approve (pending coverage)</button>';
                    html += '<button class="schedules-btn schedules-btn-danger notif-review-btn" data-timeoff-id="' + n.related_id + '" data-decision="denied">Reject</button>';
                    html += '</div>';
                }

                // Accept/decline buttons for incoming coverage requests
                if (n.type === 'cover_request_received' && n.related_id && !n.is_archived) {
                    html += '<div class="notif-actions">';
                    html += '<button class="schedules-btn schedules-btn-primary notif-cover-respond" data-trade-id="' + n.related_id + '" data-response="accept">Accept</button>';
                    html += '<button class="schedules-btn schedules-btn-danger notif-cover-respond" data-trade-id="' + n.related_id + '" data-response="decline">Decline</button>';
                    html += '</div>';
                }

                html += '</div>';
            });

            body.innerHTML = html;
            markUniqueSpans(body);

            // Mark all as read
            schedulesAjax({ action: 'schedules_mark_notifications_read' }).then(function () {
                var badge = document.querySelector('.notif-count');
                if (badge) badge.remove();
            });
        }).catch(function () {
            body.innerHTML = '<p class="notif-empty">Network error.</p>';
        });
    }

    function formatNotifTime(dateStr) {
        var d = new Date(dateStr.replace(' ', 'T'));
        var now = new Date();
        var diff = Math.floor((now - d) / 60000);
        if (diff < 1) return 'Just now';
        if (diff < 60) return diff + 'm ago';
        if (diff < 1440) return Math.floor(diff / 60) + 'h ago';
        if (diff < 10080) return Math.floor(diff / 1440) + 'd ago';
        return d.toLocaleDateString();
    }

    // Archive notification (X button)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.notif-dismiss');
        if (!btn) return;
        var notifId = btn.dataset.id;
        var item    = btn.closest('.notif-item');
        schedulesAjax({ action: 'schedules_archive_notifications', notification_id: notifId });
        if (item) item.remove();
        var body = document.getElementById('notif-panel-body');
        if (body && !body.querySelector('.notif-item')) {
            body.innerHTML = '<p class="notif-empty">No notifications.</p>';
        }
    });

    // Review PDO request from notification (Approve / Approve pending coverage / Reject)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.notif-review-btn');
        if (!btn) return;

        var timeoffId = btn.dataset.timeoffId;
        var decision  = btn.dataset.decision; // 'approved' | 'coverage' | 'denied'

        btn.disabled = true;

        schedulesAjax({
            action:     'schedules_review_timeoff',
            timeoff_id: timeoffId,
            decision:   decision,
        }).then(function (res) {
            if (res.success) {
                var actions = btn.closest('.notif-actions');
                if (actions) {
                    var labels = { approved: 'Approved', coverage: 'Approved (pending coverage)', denied: 'Rejected' };
                    actions.innerHTML = '<span class="notif-decision notif-decision-' + decision + '">' + (labels[decision] || decision) + '</span>';
                }
                showToast(res.data.message, 'success');
            } else {
                showToast((res.data && res.data.message) || 'Action failed.', 'error');
                btn.disabled = false;
            }
        }).catch(function () {
            showToast('Network error.', 'error');
            btn.disabled = false;
        });
    });

    // Accept/decline coverage request from notification
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.notif-cover-respond');
        if (!btn) return;

        var tradeId  = btn.dataset.tradeId;
        var response = btn.dataset.response; // 'accept' | 'decline'
        btn.disabled = true;

        schedulesAjax({
            action:    'schedules_respond_cover_request',
            trade_id:  tradeId,
            response:  response,
        }).then(function (res) {
            if (res.success) {
                var actions = btn.closest('.notif-actions');
                if (actions) {
                    var label = response === 'accept' ? 'Accepted' : 'Declined';
                    actions.innerHTML = '<span class="notif-decision notif-decision-' + response + '">' + label + '</span>';
                }
                showToast(res.data.message, 'success');
                // Refresh the schedule calendar so incoming button disappears
                if (scheduleActiveUserId) {
                    loadScheduleCalendar(scheduleActiveUserId, scheduleYear, scheduleMonth);
                }
            } else {
                showToast((res.data && res.data.message) || 'Action failed.', 'error');
                btn.disabled = false;
            }
        }).catch(function () {
            showToast('Network error.', 'error');
            btn.disabled = false;
        });
    });

    // Inline accept/decline on calendar cell
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cover-inline-accept, .cover-inline-decline');
        if (!btn) return;

        var tradeId  = btn.dataset.tradeId;
        var response = btn.classList.contains('cover-inline-accept') ? 'accept' : 'decline';
        btn.disabled = true;

        schedulesAjax({
            action:   'schedules_respond_cover_request',
            trade_id: tradeId,
            response: response,
        }).then(function (res) {
            if (res.success) {
                showToast(res.data.message, 'success');
                loadScheduleCalendar(scheduleActiveUserId, scheduleYear, scheduleMonth);
            } else {
                showToast((res.data && res.data.message) || 'Action failed.', 'error');
                btn.disabled = false;
            }
        }).catch(function () {
            showToast('Network error.', 'error');
            btn.disabled = false;
        });
    });

    // Archive all notifications
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#notif-archive-all')) return;
        schedulesAjax({ action: 'schedules_archive_notifications' });
        var body = document.getElementById('notif-panel-body');
        if (body) {
            body.querySelectorAll('.notif-item:not(.notif-archived)').forEach(function (item) { item.remove(); });
            if (!body.querySelector('.notif-item')) {
                body.innerHTML = '<p class="notif-empty">No notifications.</p>';
            }
        }
    });

    // Toggle archived notifications
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#notif-show-archived');
        if (!btn) return;
        notifShowArchived = !notifShowArchived;
        btn.textContent = notifShowArchived ? 'Hide Archived' : 'Show Archived';
        loadNotifications();
    });

    /*--------------------------------------------------------------
    # Toast Notifications
    --------------------------------------------------------------*/

    function showToast(message, type) {
        type = type || 'info';

        var container = document.getElementById('schedules-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'schedules-toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'false');
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'schedules-toast schedules-toast-' + type;
        toast.setAttribute('role', 'alert');
        toast.innerHTML =
            '<span class="toast-message">' + esc(message) + '</span>' +
            '<button class="toast-close" aria-label="Dismiss">&times;</button>';

        container.appendChild(toast);

        // Animate in
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add('toast-visible');
            });
        });

        var timer = setTimeout(function () { dismissToast(toast); }, 4000);

        toast.querySelector('.toast-close').addEventListener('click', function () {
            clearTimeout(timer);
            dismissToast(toast);
        });
    }

    function dismissToast(toast) {
        toast.classList.remove('toast-visible');
        toast.classList.add('toast-hiding');
        setTimeout(function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 300);
    }

    /*--------------------------------------------------------------
    # Utility: Show / Hide Elements
    --------------------------------------------------------------*/

    function show(el) { if (el) el.style.display = 'block'; }
    function hide(el) { if (el) el.style.display = 'none'; }

    function showMsg(el, text) {
        if (!el) return;
        el.textContent = text;
        show(el);
    }

    function clearMsg(el) {
        if (!el) return;
        el.textContent = '';
        hide(el);
    }

    /*--------------------------------------------------------------
    # Print Utility
    --------------------------------------------------------------*/

    // schedulePrint(el, headerText)
    // Marks `el` as the print target, injects an optional header line,
    // triggers window.print(), then cleans up on afterprint.
    // Usage: schedulePrint(document.getElementById('sup-view-duty'), 'Duty Assignment — Shift A — 2026-04-02')
    function schedulePrint(el, headerText) {
        if (!el) return;

        // Clone the target and append directly to body so body > * selector works
        var clone = el.cloneNode(true);
        clone.removeAttribute('hidden');
        clone.classList.remove('sup-view');
        clone.classList.add('schedules-print-target');

        if (headerText) {
            var headerEl = document.createElement('div');
            headerEl.className = 'schedules-print-header';
            headerEl.textContent = headerText;
            clone.insertBefore(headerEl, clone.firstChild);
        }

        document.body.appendChild(clone);
        document.body.classList.add('schedules-printing');

        function cleanup() {
            document.body.classList.remove('schedules-printing');
            if (clone.parentNode) clone.parentNode.removeChild(clone);
            window.removeEventListener('afterprint', cleanup);
        }

        window.addEventListener('afterprint', cleanup);
        window.print();
    }

    /*--------------------------------------------------------------
    # Login Form
    --------------------------------------------------------------*/

    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#schedules-login-form')) return;
        e.preventDefault();

        var submit  = document.getElementById('schedules-login-submit');
        var btnText = submit && submit.querySelector('.btn-text');
        var loading = submit && submit.querySelector('.btn-loading');
        var error   = document.getElementById('schedules-login-error');

        var badgeEl    = e.target.querySelector('#schedules-badge, [name="badge"]');
        var passwordEl = e.target.querySelector('#schedules-password, [name="password"]');

        if (!badgeEl || !passwordEl) {
            showMsg(error, 'Form error: required fields not found.');
            return;
        }

        var badge    = badgeEl.value.trim();
        var password = passwordEl.value;

        clearMsg(error);

        if (!badge || !password) {
            showMsg(error, 'Please enter your badge number and password.');
            return;
        }

        submit.disabled = true;
        if (btnText) btnText.hidden = true;
        if (loading) loading.hidden = false;

        schedulesAjax({ action: 'schedules_login', badge: badge, password: password })
            .then(function (response) {
                var resetBtn = function () {
                    submit.disabled = false;
                    if (btnText) btnText.hidden = false;
                    if (loading) loading.hidden = true;
                };
                if (response === -1) {
                    showMsg(error, 'Security token expired. Please refresh the page and try again.');
                    resetBtn();
                } else if (response === 0) {
                    showMsg(error, 'Login handler not found. Please contact support. (Code: 0)');
                    resetBtn();
                } else if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Login failed. Please try again.';
                    showMsg(error, msg);
                    resetBtn();
                }
            })
            .catch(function () {
                showMsg(error, 'A network error occurred. Please try again.');
                submit.disabled = false;
                if (btnText) btnText.hidden = false;
                if (loading) loading.hidden = true;
            });
    });

    // Toggle password visibility
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.toggle-password');
        if (!btn) return;

        var wrap  = btn.closest('.password-input-wrap, .form-group');
        var input = wrap && wrap.querySelector('input[type="password"], input[type="text"]');
        if (!input) return;

        var isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');

        var eyeOpen   = btn.querySelector('.eye-open');
        var eyeClosed = btn.querySelector('.eye-closed');
        if (eyeOpen)   eyeOpen.hidden   = !isText;
        if (eyeClosed) eyeClosed.hidden = isText;
    });

    /*--------------------------------------------------------------
    # Logout
    --------------------------------------------------------------*/

    document.addEventListener('click', function (e) {
        // TEST ONLY — nuclear reset
        if (e.target.closest('#nuclear-reset-btn')) {
            if (!confirm('⚠️ CLEAR ALL TEST DATA?\n\nThis will permanently delete:\n  • OT claims\n  • PDO / Sick / FMLA requests\n  • Coverage requests\n  • Notifications\n\nDuty assignments will NOT be affected.\nMember accounts will NOT be affected.\n\nThis cannot be undone.')) return;
            var rb = e.target.closest('#nuclear-reset-btn');
            rb.disabled = true;
            rb.textContent = 'Clearing\u2026';
            schedulesAjax({ action: 'schedules_nuclear_reset' })
                .then(function (res) {
                    rb.disabled = false;
                    rb.innerHTML = '&#9888; Clear All Test Data';
                    alert(res.success ? 'Done. All activity data has been cleared.' : ((res.data && res.data.message) || 'Failed.'));
                    if (res.success) window.location.reload();
                })
                .catch(function () { rb.disabled = false; rb.innerHTML = '&#9888; Clear All Test Data'; alert('Network error.'); });
            return;
        }

        var btn = e.target.closest('#schedules-logout-btn');
        if (!btn) return;

        btn.disabled = true;
        btn.textContent = 'Signing out\u2026';

        schedulesAjax({ action: 'schedules_logout' })
            .then(function (response) {
                window.location.href = (response.success && response.data.redirect) ? response.data.redirect : '/schedules-login/';
            })
            .catch(function () {
                window.location.href = '/schedules-login/';
            });
    });

    /*--------------------------------------------------------------
    # Change Password Panel
    --------------------------------------------------------------*/

    // --- Settings panel (overtime page) ---
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#schedules-settings-btn')) return;
        var panel = document.getElementById('schedules-settings-panel');
        if (panel) panel.hidden = !panel.hidden;
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#schedules-settings-cancel')) return;
        var panel = document.getElementById('schedules-settings-panel');
        if (panel) panel.hidden = true;
    });

    // --- User settings form (all pages) ---
    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#user-settings-form')) return;
        e.preventDefault();
        var errEl  = document.getElementById('user-settings-error');
        var sucEl  = document.getElementById('user-settings-success');
        var submit = document.getElementById('user-settings-submit');
        if (errEl) errEl.textContent = '';
        if (sucEl) sucEl.textContent = '';
        var fd = new FormData(e.target);
        var payload = {
            action      : 'schedules_save_user_settings',
            nonce       : schedulesData.nonce,
            name_format : fd.get('name_format') || 'first_last',
        };
        if (submit) { submit.disabled = true; submit.textContent = 'Saving\u2026'; }
        schedulesAjax(payload)
            .then(function (res) {
                if (submit) { submit.disabled = false; submit.textContent = 'Save Preferences'; }
                if (res.success) {
                    window.location.reload();
                } else {
                    if (errEl) errEl.textContent = (res.data && res.data.message) || 'Could not save.';
                }
            })
            .catch(function () {
                if (submit) { submit.disabled = false; submit.textContent = 'Save Preferences'; }
                if (errEl) errEl.textContent = 'Network error.';
            });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#schedules-change-pw-btn');
        if (!btn) return;

        var panel = document.getElementById('schedules-change-pw-panel');
        if (!panel) return;

        if (panel.hidden) {
            panel.hidden = false;
            btn.textContent = 'Hide';
            var first = panel.querySelector('input');
            if (first) first.focus();
        } else {
            panel.hidden = true;
            btn.textContent = 'Change Password';
        }
    });

    // --- OT calendar view toggle (Row / Column) ---
    (function () {
        var cal = document.querySelector('.schedules-calendar');
        var btn = document.getElementById('ot-view-toggle');
        if (!cal || !btn) return;
        // Restore saved preference
        var saved = '';
        try { saved = sessionStorage.getItem('schedules_ot_view') || ''; } catch (ex) {}
        if (saved === 'col') {
            cal.classList.add('ot-col-view');
            btn.textContent = 'Row View';
        }
        btn.addEventListener('click', function () {
            var isCol = cal.classList.contains('ot-col-view');
            cal.classList.toggle('ot-col-view', !isCol);
            btn.textContent = isCol ? 'Column View' : 'Row View';
            try { sessionStorage.setItem('schedules_ot_view', isCol ? 'row' : 'col'); } catch (ex) {}
        });
    }());

    // --- Members view toggle ---
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#members-view-toggle');
        if (!btn) return;
        var wrap = document.getElementById('members-table-wrap');
        if (!wrap) return;
        var isCard   = wrap.classList.contains('members-wrap-card');
        var newView  = isCard ? 'row' : 'card';
        wrap.classList.toggle('members-wrap-card', !isCard);
        wrap.classList.toggle('members-wrap-row',   isCard);
        btn.textContent = isCard ? 'Card View' : 'Row View';
        schedulesAjax({
            action      : 'schedules_save_user_settings',
            nonce       : schedulesData.nonce,
            name_format : schedulesData.dutyNameFormat || 'first_last',
            members_view: newView,
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#schedules-change-pw-cancel')) return;
        var panel = document.getElementById('schedules-change-pw-panel');
        if (panel) panel.hidden = true;
        var btn = document.getElementById('schedules-change-pw-btn');
        if (btn) btn.textContent = 'Change Password';
    });

    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#schedules-change-pw-form')) return;
        e.preventDefault();

        var form      = e.target;
        var errorEl   = document.getElementById('change-pw-error');
        var successEl = document.getElementById('change-pw-success');
        var submitBtn = form.querySelector('[type="submit"]');

        var currentPw = document.getElementById('current-password').value;
        var newPw     = document.getElementById('new-password').value;
        var confirmPw = document.getElementById('confirm-password').value;

        clearMsg(errorEl);
        clearMsg(successEl);

        if (!currentPw || !newPw || !confirmPw) {
            showMsg(errorEl, 'All fields are required.');
            return;
        }
        if (newPw.length < 8) {
            showMsg(errorEl, 'New password must be at least 8 characters.');
            return;
        }
        if (newPw !== confirmPw) {
            showMsg(errorEl, 'New passwords do not match.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Updating\u2026';

        schedulesAjax({ action: 'schedules_change_password', current_password: currentPw, new_password: newPw })
            .then(function (response) {
                if (response.success) {
                    showMsg(successEl, (response.data && response.data.message) ? response.data.message : 'Password updated.');
                    form.reset();
                    showToast('Password updated successfully.', 'success');
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Password change failed.';
                    showMsg(errorEl, msg);
                }
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Password';
            })
            .catch(function () {
                showMsg(errorEl, 'A network error occurred.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Password';
            });
    });

    /*--------------------------------------------------------------
    # Week Tabs
    --------------------------------------------------------------*/

    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.week-tab');
        if (!tab) return;

        var weekNum  = tab.dataset.week;
        var tabsWrap = tab.closest('.schedules-week-tabs');
        var app      = tab.closest('.sup-view, #schedules-ot-app');

        if (tabsWrap) {
            tabsWrap.querySelectorAll('.week-tab').forEach(function (t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
        }
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');

        if (app) {
            app.querySelectorAll('.schedules-week').forEach(function (w) {
                w.hidden = true;
            });
            var activeWeek = app.querySelector('.schedules-week[data-week="' + weekNum + '"]');
            if (activeWeek) activeWeek.hidden = false;
            if (app.id === 'schedules-ot-app') {
                try { sessionStorage.setItem('schedules_ot_week', weekNum); } catch(ex) {}
            }
        }
    });

    /*--------------------------------------------------------------
    # Month Filter (OT Page)
    --------------------------------------------------------------*/

    function initMonthFilter(selId, appSelector) {
        var app = document.querySelector(appSelector);
        if (!app) return;

        function filterMonth(yyyyMM) {
            app.querySelectorAll('.schedules-week').forEach(function (week) {
                var hasDays = false;
                week.querySelectorAll('.schedule-day').forEach(function (day) {
                    var show = day.dataset.month === yyyyMM;
                    day.hidden = !show;
                    if (show) hasDays = true;
                });
                week.hidden = !hasDays;
            });
        }

        var sel = document.getElementById(selId);
        if (sel) {
            filterMonth(sel.value);
            sel.addEventListener('change', function () { filterMonth(this.value); });
        }
    }

    initMonthFilter('schedules-month-filter', '#schedules-ot-app');

    /*--------------------------------------------------------------
    # Block Selection & Claiming (OT Page)
    --------------------------------------------------------------*/

    var activePopup   = null;
    var activeBlockEl = null;

    function closeBlockPopup() {
        if (activeBlockEl) activeBlockEl.classList.remove('selected');
        if (activePopup) {
            activePopup.hidden = true;
            activePopup.style.top  = '';
            activePopup.style.left = '';
        }
        activePopup   = null;
        activeBlockEl = null;
    }

    document.addEventListener('click', function (e) {
        var block = e.target.closest('.time-block.available');
        if (!block || block.classList.contains('supervisor-block')) return;

        e.stopPropagation();

        var timeStr = block.dataset.time || (block.querySelector('.time-range') ? block.querySelector('.time-range').textContent : '');
        var shift   = block.dataset.shift || (block.closest('.shift-group') ? block.closest('.shift-group').dataset.shift : '');
        var date    = block.dataset.date  || (block.closest('.schedule-day') ? block.closest('.schedule-day').dataset.date : '');

        // Build the display range accounting for min claim hours
        var minHours  = parseInt(schedulesData.minClaimHours, 10) || 1;
        var rangeStr  = timeStr;
        if (minHours > 1) {
            var shiftGroup = block.closest('.shift-blocks') || block.closest('.shift-group');
            if (shiftGroup) {
                // Check if this block is adjacent to an existing claimed chain
                var allTimeBlocks = Array.prototype.slice.call(shiftGroup.querySelectorAll('.time-block'));
                var blockPos      = allTimeBlocks.indexOf(block);
                var adjacentClaimed = false;
                if (blockPos > 0 && allTimeBlocks[blockPos - 1].classList.contains('claimed')) adjacentClaimed = true;
                if (blockPos < allTimeBlocks.length - 1 && allTimeBlocks[blockPos + 1].classList.contains('claimed')) adjacentClaimed = true;

                if (!adjacentClaimed) {
                    // No adjacent claims — show the full min-hours range
                    var availBlocks = Array.prototype.slice.call(shiftGroup.querySelectorAll('.time-block.available:not(.claimed)'));
                    var startIdx    = availBlocks.indexOf(block);
                    if (startIdx !== -1) {
                        var endIdx = startIdx;
                        for (var bi = startIdx + 1; bi < availBlocks.length && (endIdx - startIdx + 1) < minHours; bi++) {
                            endIdx = bi;
                        }
                        var startTime = block.querySelector('.time-range') ? block.querySelector('.time-range').textContent.split('\u2013')[0] : '';
                        var lastBlock = availBlocks[endIdx];
                        var endTime   = lastBlock.querySelector('.time-range') ? lastBlock.querySelector('.time-range').textContent.split('\u2013')[1] : '';
                        if (startTime && endTime) rangeStr = startTime + '\u2013' + endTime;
                    }
                }
                // If adjacent to claimed chain, rangeStr stays as the single block's timeStr
            }
        }

        var dateFmt = date;
        if (date) {
            var d = new Date(date + 'T00:00:00');
            dateFmt = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        }

        closeBlockPopup();

        var popup = document.getElementById('block-confirm-popup');
        if (!popup) return;

        // Calculate hours from military time range (0600 → 6, 1800 → 18)
        var rangeParts = rangeStr.split('\u2013');
        var claimHours = 1;
        if (rangeParts.length === 2) {
            var h1 = Math.floor(parseInt(rangeParts[0], 10) / 100);
            var h2 = Math.floor(parseInt(rangeParts[1], 10) / 100);
            claimHours = h2 > h1 ? h2 - h1 : (24 - h1) + h2;
        }

        // Set popup content
        var noteEl = popup.querySelector('.popup-note');
        if (noteEl) {
            if (scheduleProxyUserId) {
                var proxyNameEl = document.getElementById('sup-proxy-name');
                var proxyName   = proxyNameEl ? proxyNameEl.textContent : 'member';
                noteEl.textContent = 'You are assigning ' + claimHours + ' hour' + (claimHours > 1 ? 's' : '') + ' of OT to ' + proxyName + ', from ' + rangeStr;
            } else {
                noteEl.textContent = 'You are signing up for ' + claimHours + ' hour' + (claimHours > 1 ? 's' : '') + ' of OT, from ' + rangeStr;
            }
        }

        popup.dataset.blockId = block.dataset.blockId;
        activeBlockEl = block;
        block.classList.add('selected');

        // Position popup near block
        var rect   = block.getBoundingClientRect();
        var popW   = 260;
        var left   = Math.max(10, rect.left - (popW / 2) + (block.offsetWidth / 2));
        var maxLeft = window.innerWidth - popW - 16;
        left = Math.min(left, maxLeft);

        popup.style.top  = (rect.bottom + window.scrollY + 8) + 'px';
        popup.style.left = left + 'px';
        popup.style.position = 'absolute';
        popup.hidden = false;

        activePopup = popup;
        var confirmBtn = popup.querySelector('.popup-confirm');
        if (confirmBtn) confirmBtn.focus();
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.popup-confirm')) return;

        var popup   = document.getElementById('block-confirm-popup');
        var blockId = popup ? popup.dataset.blockId : null;
        var block   = activeBlockEl;

        closeBlockPopup();
        if (!blockId || !block) return;

        var claimPayload = { action: 'schedules_claim_block', block_id: blockId };
        if (scheduleProxyUserId) claimPayload.proxy_user_id = scheduleProxyUserId;

        schedulesAjax(claimPayload)
            .then(function (response) {
                if (response.success) {
                    // Mark all claimed blocks (may be multiple if min_hours > 1)
                    var claimedIds = response.data.claimed_blocks || [blockId];
                    claimedIds.forEach(function (cid) {
                        var el = document.querySelector('.time-block[data-block-id="' + cid + '"]');
                        if (!el) return;
                        el.classList.remove('available', 'limited', 'selected', 'supervisor-block');
                        el.classList.add('claimed');
                        var statusEl = el.querySelector('.block-status');
                        var claimsEl = el.querySelector('.block-claims-count');
                        if (claimsEl) {
                            if (statusEl) statusEl.textContent = response.data.remaining + ' open';
                            var count = parseInt(claimsEl.textContent) || 0;
                            claimsEl.textContent = (count + 1) + ' claimed';
                        } else {
                            if (statusEl) statusEl.textContent = 'Claimed';
                        }
                        el.dataset.available = response.data.remaining;
                        el.removeAttribute('tabindex');
                    });

                    // Add undo buttons only when claiming for self (not proxy)
                    if (!proxyUserId) {
                        var graceMs = 5 * 60 * 1000;
                        claimedIds.forEach(function (cid) {
                            var el = document.querySelector('.time-block[data-block-id="' + cid + '"]');
                            if (!el) return;
                            el.classList.add('claim-undoable');
                            var undoBtn = document.createElement('button');
                            undoBtn.className = 'basic-btn claim-undo-btn';
                            undoBtn.type = 'button';
                            undoBtn.innerHTML = '&times;';
                            undoBtn.title = 'Undo claim';
                            undoBtn.dataset.blockId = cid;
                            el.appendChild(undoBtn);
                            setTimeout(function () {
                                if (undoBtn.parentNode) {
                                    undoBtn.parentNode.classList.remove('claim-undoable');
                                    undoBtn.remove();
                                }
                            }, graceMs);
                        });
                    }

                    showToast(response.data.message || 'Claimed!', 'success');
                    refreshMyClaims();
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Could not claim block.';
                    showToast(msg, 'error');
                }
            })
            .catch(function () {
                showToast('A network error occurred. Please try again.', 'error');
            });
    });

    document.addEventListener('click', function (e) {
        // Undo claim
        var undoBtn = e.target.closest('.claim-undo-btn');
        if (undoBtn) {
            e.stopPropagation();
            var bid = undoBtn.dataset.blockId;
            if (!bid) return;
            undoBtn.disabled = true;

            schedulesAjax({ action: 'schedules_unclaim_block', block_id: bid })
                .then(function (res) {
                    if (res.success) {
                        var removedIds = res.data.removed_blocks || [bid];
                        removedIds.forEach(function (rid) {
                            var el = document.querySelector('.time-block[data-block-id="' + rid + '"]');
                            if (!el) return;
                            el.classList.remove('claimed', 'claim-undoable');
                            el.classList.add('available');
                            var statusEl = el.querySelector('.block-status');
                            var claimsEl = el.querySelector('.block-claims-count');
                            if (claimsEl) {
                                var cnt = Math.max(0, (parseInt(claimsEl.textContent) || 0) - 1);
                                claimsEl.textContent = cnt + ' claimed';
                            }
                            if (statusEl) statusEl.textContent = claimsEl ? ((parseInt(el.dataset.available) || 0) + 1) + ' open' : 'Available';
                            el.dataset.available = (parseInt(el.dataset.available) || 0) + 1;
                            var btn = el.querySelector('.claim-undo-btn');
                            if (btn) btn.remove();
                        });
                        showToast(res.data.message || 'Claim undone.', 'info');
                        refreshMyClaims();
                    } else {
                        showToast((res.data && res.data.message) || 'Could not undo.', 'error');
                        undoBtn.disabled = false;
                    }
                })
                .catch(function () {
                    showToast('Network error.', 'error');
                    undoBtn.disabled = false;
                });
            return;
        }

        if (e.target.closest('.popup-cancel')) {
            closeBlockPopup();
            return;
        }
        // Close popup on outside click
        if (activePopup && !e.target.closest('#block-confirm-popup') && !e.target.closest('.time-block')) {
            closeBlockPopup();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeBlockPopup();
    });

    // Proxy member select — refresh board to show selected member's claim state
    (function () {
        var proxySelect = document.getElementById('ot-proxy-member-select');
        if (!proxySelect) return;

        function refreshOtBoardForUser(userId) {
            // Reset all blocks to their natural available/full state
            document.querySelectorAll('.time-block').forEach(function (el) {
                el.classList.remove('claimed', 'claim-undoable', 'selected');
                var undo = el.querySelector('.claim-undo-btn');
                if (undo) undo.remove();
                var avail = parseInt(el.dataset.available, 10) || 0;
                el.classList.remove('available', 'full', 'limited', 'supervisor-block');
                if (avail > 0) {
                    el.classList.add('available');
                    if (avail <= 2) el.classList.add('limited');
                    // Re-add supervisor-block if claiming is not enabled
                    var cal = el.closest('.schedules-supervisor-calendar');
                    if (cal && !cal.classList.contains('sup-can-claim')) el.classList.add('supervisor-block');
                } else {
                    el.classList.add('full');
                }
            });

            schedulesAjax({ action: 'schedules_get_ot_user_state', user_id: userId })
                .then(function (res) {
                    if (!res.success) return;
                    (res.data.claimed_block_ids || []).forEach(function (bid) {
                        var el = document.querySelector('.time-block[data-block-id="' + bid + '"]');
                        if (!el) return;
                        el.classList.remove('available', 'full', 'limited', 'supervisor-block');
                        el.classList.add('claimed');
                    });
                });
        }

        proxySelect.addEventListener('change', function () {
            var activeView = document.querySelector('.sup-view.active');
            if (activeView) {
                activeView.style.animation = 'none';
                activeView.offsetHeight; // force reflow
                activeView.style.animation = '';
            }
            refreshOtBoardForUser(this.value);
        });
    }());

    function refreshMyClaims() {
        var claimsSection = document.getElementById('schedules-my-claims');
        if (claimsSection) {
            setTimeout(function () { window.location.reload(); }, 800);
        }
    }

    // Silently re-fetch block availability and update DOM counts
    function refreshOtCounts() {
        if (!document.getElementById('schedules-ot-app')) return;
        schedulesAjax({ action: 'schedules_get_calendar' })
            .then(function (res) {
                if (!res.success || !res.data || !res.data.weeks) return;
                var weeks = res.data.weeks;
                Object.keys(weeks).forEach(function (wk) {
                    var days = weeks[wk];
                    Object.keys(days).forEach(function (date) {
                        days[date].forEach(function (shift) {
                            (shift.blocks || []).forEach(function (block) {
                                var el = document.querySelector('.time-block[data-block-id="' + block.id + '"]');
                                if (!el) return;
                                // Don't overwrite a block the user themselves just claimed
                                if (el.classList.contains('claimed')) return;
                                var avail = parseInt(block.available, 10) || 0;
                                el.dataset.available = avail;
                                el.classList.remove('available', 'limited', 'full');
                                var statusEl = el.querySelector('.block-status');
                                if (avail <= 0) {
                                    el.classList.add('full');
                                    el.removeAttribute('tabindex');
                                    if (statusEl) statusEl.textContent = 'Full';
                                } else {
                                    el.classList.add('available');
                                    if (avail <= 2) el.classList.add('limited');
                                    el.setAttribute('tabindex', '0');
                                    if (statusEl) statusEl.textContent = avail + ' open';
                                }
                            });
                        });
                    });
                });
            })
            .catch(function () {}); // silent — don't alert on background refresh
    }

    // Refresh OT counts when user returns to this tab after being away
    var _otHiddenAt = 0;
    document.addEventListener('visibilitychange', function () {
        if (!document.getElementById('schedules-ot-app')) return;
        if (document.hidden) {
            _otHiddenAt = Date.now();
        } else if (_otHiddenAt && (Date.now() - _otHiddenAt) > 10000) {
            refreshOtCounts();
        }
    });

    /*--------------------------------------------------------------
    # Supervisor: Add Slot
    --------------------------------------------------------------*/

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.add-slot-btn');
        if (!btn) return;

        var dayId = btn.dataset.dayId;
        var form  = document.getElementById('add-slot-form-' + dayId);
        if (!form) return;

        // Close any other open forms
        document.querySelectorAll('.add-slot-form').forEach(function (f) {
            if (f !== form) f.hidden = true;
        });

        if (form.hidden) {
            form.hidden = false;
            var reason = form.querySelector('.add-slot-reason');
            if (reason) reason.focus();
        } else {
            form.hidden = true;
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.add-slot-cancel');
        if (!btn) return;
        var form = btn.closest('.add-slot-form');
        if (form) form.hidden = true;
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.add-slot-confirm');
        if (!btn) return;

        var dayId      = btn.dataset.dayId;
        var form       = btn.closest('.add-slot-form');
        var reasonEl   = form && form.querySelector('.add-slot-reason');
        var reason     = reasonEl ? reasonEl.value : '';
        var msgEl      = form && form.querySelector('.add-slot-msg');

        btn.disabled = true;
        btn.textContent = 'Saving\u2026';
        if (msgEl) { msgEl.textContent = ''; msgEl.className = 'add-slot-msg'; }

        schedulesAjax({ action: 'schedules_add_adjustment', day_id: dayId, reason: reason })
            .then(function (response) {
                if (response.success) {
                    if (msgEl) { msgEl.textContent = 'Slot added! Eligible members have been notified.'; msgEl.classList.add('success'); }
                    showToast('Slot added successfully.', 'success');

                    // Increment available counts on blocks in this shift group
                    var shiftGroup = form ? form.closest('.shift-group') : null;
                    if (shiftGroup) {
                        shiftGroup.querySelectorAll('.time-block').forEach(function (blk) {
                            var avail = (parseInt(blk.dataset.available, 10) || 0) + 1;
                            blk.dataset.available = avail;
                            var statusEl = blk.querySelector('.block-status');
                            if (statusEl) statusEl.textContent = avail + ' open';
                            if (blk.classList.contains('full')) {
                                blk.classList.remove('full');
                                blk.classList.add('available');
                                blk.setAttribute('tabindex', '0');
                            }
                        });
                    }

                    setTimeout(function () { if (form) form.hidden = true; }, 1500);
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Failed to add slot.';
                    if (msgEl) { msgEl.textContent = msg; msgEl.classList.add('error'); }
                    showToast(msg, 'error');
                }
                btn.disabled = false;
                btn.textContent = 'Open Slot';
            })
            .catch(function () {
                if (msgEl) { msgEl.textContent = 'Network error.'; msgEl.classList.add('error'); }
                btn.disabled = false;
                btn.textContent = 'Open Slot';
            });
    });

    /*--------------------------------------------------------------
    # Supervisor: Mark Absent (Sick / FMLA)
    --------------------------------------------------------------*/

    function openAbsentModal(date, shift, userId) {
        var modal          = document.getElementById('absent-modal');
        if (!modal) return;
        var form           = modal.querySelector('form');
        var shiftInput     = modal.querySelector('[name="shift"]');
        var startDateInput = modal.querySelector('[name="start_date"]');
        var endDateInput   = modal.querySelector('[name="end_date"]');
        var memberSel      = modal.querySelector('[name="user_id"]');
        var startSel       = modal.querySelector('[name="start_time"]');
        var endSel         = modal.querySelector('[name="end_time"]');
        var notesEl        = modal.querySelector('[name="notes"]');
        var errorEl        = modal.querySelector('.form-error');

        if (!memberSel) return;

        // Reset
        if (notesEl) notesEl.value = '';
        clearMsg(errorEl);
        var firstRadio = form && form.querySelector('input[name="type"]');
        if (firstRadio) firstRadio.checked = true;

        // Pre-populate both date fields with the triggered date
        if (shiftInput)     shiftInput.value     = shift;
        if (startDateInput) startDateInput.value = date;
        if (endDateInput)   endDateInput.value   = date;

        // Members on this shift
        var members = (schedulesData.shiftMembers || []).filter(function (m) { return m.shift === shift; });
        memberSel.innerHTML = buildGroupedOptions(members, 'Select member\u2026');

        // Time selects for this shift
        var shiftData = (schedulesData.shifts || []).find(function (s) { return s.letter === shift; });
        var sh        = shiftData ? shiftData.startHour : 6;
        var increment = parseInt(schedulesData.scheduleTimeIncrement, 10) || 60;

        // Default to full shift hours; override with member-specific hours if duty board is loaded
        var _endH    = shiftData ? shiftData.endHour : (sh + 12) % 24;
        var preStart = (sh    < 10 ? '0' : '') + sh    + ':00';
        var preEnd   = (_endH < 10 ? '0' : '') + _endH + ':00';
        if (userId && dutyData) {
            var rm = (dutyData.roster || []).find(function (m) { return String(m.user_id) === String(userId); });
            if (rm) {
                if (rm.type === 'ot' && rm.ot_hours && rm.ot_hours.length) {
                    var ohs  = rm.ot_hours;
                    var oS   = ohs[0].start;
                    var oE   = ohs[ohs.length - 1].end;
                    preStart = (oS < 10 ? '0' : '') + oS + ':00';
                    preEnd   = (oE < 10 ? '0' : '') + oE + ':00';
                } else if (rm.type === 'custom' && rm.custom_hours) {
                    var chS  = rm.custom_hours.start || 0;
                    var chE  = rm.custom_hours.end   || 0;
                    var _fmt = function (h) { var hr = Math.floor(h); var mn = Math.round((h - hr) * 60); return (hr < 10 ? '0' : '') + hr + ':' + (mn < 10 ? '0' : '') + mn; };
                    preStart = _fmt(chS);
                    preEnd   = _fmt(chE);
                }
            }
        }

        buildTimeSelects(startSel, endSel, [], sh, increment, preStart, preEnd, _endH);

        if (userId) memberSel.value = String(userId);

        modal.hidden = false;
        syncBodyLock();
        memberSel.focus();

        // Wire direct onchange listeners so each input immediately re-fetches its own hours
        (function () {
            var _startD = modal.querySelector('[name="start_date"]');
            var _endD   = modal.querySelector('[name="end_date"]');
            var _mem    = memberSel;
            function getUid() { return _mem ? _mem.value : ''; }
            if (_startD) {
                _startD.onchange = function () { refreshAbsentSingleSelect(getUid(), this.value, 'start'); };
            }
            if (_endD) {
                _endD.onchange = function () { refreshAbsentSingleSelect(getUid(), this.value, 'end'); };
            }
            if (_mem) {
                _mem.onchange = function () {
                    var uid = this.value;
                    var sd  = _startD ? _startD.value : '';
                    var ed  = _endD   ? _endD.value   : '';
                    refreshAbsentSingleSelect(uid, sd, 'start');
                    refreshAbsentSingleSelect(uid, ed || sd, 'end');
                };
            }
        })();

        // Initial async fetch for the opened date
        if (userId) {
            refreshAbsentSingleSelect(userId, date, 'start');
            refreshAbsentSingleSelect(userId, date, 'end');
        }
    }

    // Build one time select from server-fetched day-specific hours.
    // side = 'start' → populate start select, pre-select shift start hour
    // side = 'end'   → populate end select,   pre-select shift end hour
    function refreshAbsentSingleSelect(userId, date, side) {
        if (!userId || !date) return;
        var modal = document.getElementById('absent-modal');
        if (!modal || modal.hidden) return;
        var sel = modal.querySelector(side === 'start' ? '[name="start_time"]' : '[name="end_time"]');
        if (!sel) return;
        var increment = parseInt(schedulesData.scheduleTimeIncrement, 10) || 60;

        schedulesAjax({ action: 'schedules_get_member_day_hours', user_id: userId, date: date })
            .then(function (res) {
                if (!res.success || !res.data.work_day) return;
                var sh  = res.data.start_hour;
                var eh  = res.data.end_hour;
                var dur = eh > sh ? (eh - sh) * 60 : (24 - sh + eh) * 60;
                var rangeStart = sh * 60;
                var rangeEnd   = rangeStart + dur;
                var selected   = side === 'start'
                    ? (sh < 10 ? '0' : '') + sh + ':00'
                    : (eh < 10 ? '0' : '') + eh + ':00';
                sel.innerHTML = '';
                for (var t = rangeStart; t <= rangeEnd; t += increment) {
                    var hh  = Math.floor(t / 60) % 24;
                    var mm  = t % 60;
                    var val = (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
                    var lbl = (hh < 10 ? '0' : '') + hh + (mm < 10 ? '0' : '') + mm;
                    var inRange = side === 'start' ? (t < rangeEnd) : (t > rangeStart);
                    if (inRange) {
                        var opt = document.createElement('option');
                        opt.value = val; opt.textContent = lbl;
                        sel.appendChild(opt);
                    }
                }
                sel.value = selected;
            });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.mark-absent-btn');
        if (!btn) return;
        openAbsentModal(btn.dataset.date, btn.dataset.shift);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.duty-ab-btn');
        if (!btn) return;
        openAbsentModal(btn.dataset.date, btn.dataset.shift, btn.dataset.userId);
    });

    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#absent-form')) return;
        e.preventDefault();

        var form      = e.target;
        var errorEl   = form.querySelector('.form-error');
        var submit    = form.querySelector('[type="submit"]');
        var fd        = new FormData(form);
        var userId    = fd.get('user_id')    || '';
        var startDate = fd.get('start_date') || '';
        var endDate   = fd.get('end_date')   || startDate;
        var shift     = fd.get('shift')      || '';
        var typeVal   = fd.get('type')       || '';
        var startTime = fd.get('start_time') || '';
        var endTime   = fd.get('end_time')   || '';
        var notes     = fd.get('notes')      || '';

        clearMsg(errorEl);

        if (!userId)    { showMsg(errorEl, 'Please select a member.'); return; }
        if (!startDate) { showMsg(errorEl, 'Please select a start date.'); return; }
        if (!typeVal)   { showMsg(errorEl, 'Please select a type.'); return; }

        submit.disabled    = true;
        submit.textContent = 'Saving\u2026';

        schedulesAjax({
            action:      'schedules_submit_timeoff',
            user_id:     userId,
            start_date:  startDate,
            end_date:    endDate,
            type:        typeVal,
            start_time:  startTime,
            end_time:    endTime,
            notes:       notes,
        }).then(function (res) {
            submit.disabled    = false;
            submit.textContent = 'Mark Absent';
            if (res.success) {
                var created = res.data.created || 1;
                var skipped = res.data.skipped || 0;
                var label   = typeVal === 'fmla' ? 'FMLA' : 'Sick';
                var msg     = label + ': ' + created + ' day' + (created !== 1 ? 's' : '') + ' marked — OT slot' + (created !== 1 ? 's' : '') + ' opened for Shift ' + shift;
                if (skipped) msg += ' (' + skipped + ' skipped)';
                showToast(msg, 'success');
                var modal = form.closest('.schedules-modal');
                if (modal) { modal.hidden = true; syncBodyLock(); }

                // Reload duty roster if it is currently displaying this shift/date
                if (dutyData && dutyData.date === startDate && dutyData.shift_letter === shift) {
                    loadDutyGrid(startDate, shift);
                }

                // Increment available count on blocks in this shift/date
                document.querySelectorAll('.shift-group[data-shift="' + shift + '"]').forEach(function (sg) {
                    var day = sg.closest('.schedule-day');
                    if (!day || day.dataset.date !== startDate) return;
                    sg.querySelectorAll('.time-block').forEach(function (blk) {
                        var avail = (parseInt(blk.dataset.available, 10) || 0) + 1;
                        blk.dataset.available = avail;
                        var statusEl = blk.querySelector('.block-status');
                        if (statusEl) statusEl.textContent = avail + ' open';
                        if (blk.classList.contains('full')) {
                            blk.classList.remove('full');
                            blk.classList.add('available');
                            blk.setAttribute('tabindex', '0');
                        }
                    });
                });
            } else {
                showMsg(errorEl, (res.data && res.data.message) ? res.data.message : 'Failed to mark absent.');
            }
        }).catch(function () {
            submit.disabled    = false;
            submit.textContent = 'Mark Absent';
            showMsg(errorEl, 'Network error.');
        });
    });

    /*--------------------------------------------------------------
    # Supervisor: Claims Log
    --------------------------------------------------------------*/

    function loadClaims() {
        var dateEl   = document.getElementById('claims-date-filter');
        var shiftEl  = document.getElementById('claims-shift-filter');
        var results  = document.getElementById('claims-results');
        var date     = dateEl ? dateEl.value : '';
        var shift    = shiftEl ? shiftEl.value : '';

        if (!date) return;

        if (results) results.innerHTML = '<p class="loading-msg">Loading claims\u2026</p>';

        schedulesAjax({ action: 'schedules_get_claims', date: date, shift_letter: shift })
            .then(function (response) {
                if (response.success) {
                    renderClaimsTable(response.data, results);
                } else {
                    if (results) results.innerHTML = '<p class="form-error">Could not load claims.</p>';
                }
            })
            .catch(function () {
                if (results) results.innerHTML = '<p class="form-error">Network error.</p>';
            });
    }

    document.addEventListener('change', function (e) {
        if (e.target.matches('#claims-date-filter, #claims-shift-filter')) loadClaims();
    });

    function renderClaimsTable(data, container) {
        if (!container) return;
        var shifts = Object.keys(data);

        if (!shifts.length) {
            container.innerHTML = '<p class="claims-empty">No claims found for the selected date and shift.</p>';
            return;
        }

        var html = '';

        shifts.forEach(function (shiftKey) {
            var rows = data[shiftKey];
            if (!rows || !rows.length) return;

            html += '<h3 class="claims-shift-heading">Shift ' + esc(shiftKey) + '</h3>';
            html += '<div class="table-responsive"><table class="schedules-table claims-table">';
            html += '<thead><tr>' +
                '<th>Shift</th><th>Badge #</th><th>Name</th>' +
                '<th>Discipline</th><th>Block</th><th>Claimed At</th>' +
                '</tr></thead><tbody>';

            rows.forEach(function (row) {
                html += '<tr>';
                html += '<td><span class="shift-pill shift-' + esc(row.shift_letter.toLowerCase()) + '">Shift ' + esc(row.shift_letter) + '</span></td>';
                html += '<td class="badge-col"><strong>' + esc(row.badge_number) + '</strong></td>';
                html += '<td>' + esc(row.display_name) + '</td>';
                html += '<td>' + esc(row.discipline || '\u2014') + '</td>';
                html += '<td>' + esc(row.time_range) + '</td>';
                html += '<td>' + esc(row.claimed_at) + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
        });

        container.innerHTML = html || '<p class="claims-empty">No claims found.</p>';
        markUniqueSpans(container);
    }

    /*--------------------------------------------------------------
    # Supervisor: Sick Time
    --------------------------------------------------------------*/

    function loadSickHistory(userId) {
        var contentEl = document.getElementById('sicktime-content');
        if (!contentEl || !userId) {
            if (contentEl) contentEl.innerHTML = '<p class="sicktime-placeholder">Select a member to view their sick time.</p>';
            return;
        }
        contentEl.innerHTML = '<p class="sicktime-loading">Loading\u2026</p>';
        schedulesAjax({ action: 'schedules_get_sick_history', user_id: userId }).then(function (res) {
            if (!res.success) { contentEl.innerHTML = '<p class="sicktime-error">Error loading sick time.</p>'; return; }
            var d      = res.data;
            var today  = new Date().toISOString().slice(0, 10);
            var shift  = d.shift || '';
            var thresh = d.thresholds && d.thresholds.length ? d.thresholds[0] : 30;
            var overThresh = d.ytd_hours >= thresh;

            var html = '<div class="sicktime-stats">';
            html += '<div class="sicktime-stat' + (overThresh ? ' sicktime-stat-warn' : '') + '">';
            html += '<span class="sicktime-stat-val">' + parseFloat(d.ytd_hours).toFixed(1) + '</span>';
            html += '<span class="sicktime-stat-label">' + d.year + ' YTD Sick Hours</span></div>';
            html += '<div class="sicktime-stat"><span class="sicktime-stat-val">' + thresh + '</span>';
            html += '<span class="sicktime-stat-label">Hour Threshold</span></div>';
            html += '</div>';

            html += '<button type="button" class="schedules-btn schedules-btn-primary sicktime-mark-btn"'
                  + ' data-user-id="' + esc(String(userId)) + '"'
                  + ' data-shift="' + esc(shift) + '"'
                  + ' data-date="' + today + '">+ Mark Sick</button>';

            if (!d.records || !d.records.length) {
                html += '<p class="sicktime-placeholder">No sick time records found.</p>';
            } else {
                html += '<div class="table-responsive"><table class="schedules-table sicktime-table">';
                html += '<thead><tr><th>Date</th><th>Type</th><th>Hours</th><th>Notes</th></tr></thead><tbody>';
                d.records.forEach(function (r) {
                    var typeLabel = r.type === 'fmla' ? 'FMLA' : 'Sick';
                    var dateStr   = r.start_date !== r.end_date ? esc(r.start_date) + ' \u2013 ' + esc(r.end_date) : esc(r.start_date);
                    html += '<tr>';
                    html += '<td>' + dateStr + '</td>';
                    html += '<td><span class="actlog-pill actlog-pill-' + esc(r.type) + '">' + esc(typeLabel) + '</span></td>';
                    html += '<td>' + parseFloat(r.hours || 0).toFixed(1) + '</td>';
                    html += '<td>' + esc(r.notes || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
            }
            contentEl.innerHTML = html;
        });
    }

    (function () {
        var memberSel = document.getElementById('sicktime-member-select');
        if (!memberSel) return;
        memberSel.addEventListener('change', function () { loadSickHistory(this.value); });
    })();

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.sicktime-mark-btn');
        if (!btn) return;
        openAbsentModal(btn.dataset.date, btn.dataset.shift, btn.dataset.userId);
    });

    /*--------------------------------------------------------------
    # Supervisor: Activity Log
    --------------------------------------------------------------*/

    (function () {
        var actlogPage  = 1;
        var actlogTotal = 0;
        var actlogPer   = 50;

        var resultsEl  = document.getElementById('actlog-results');
        var paginEl    = document.getElementById('actlog-pagination');
        var pageInfo   = document.getElementById('actlog-page-info');
        var prevBtn    = document.getElementById('actlog-prev');
        var nextBtn    = document.getElementById('actlog-next');

        var actionLabels = {
            ot_claim:         'OT Claimed',
            ot_unclaim:       'OT Unclaimed',
            timeoff_request:  'Time-Off Request',
            timeoff_review:   'Time-Off Reviewed',
            coverage_request: 'Coverage Requested',
            coverage_respond: 'Coverage Responded',
            coverage_review:  'Coverage Reviewed',
            coverage_cancel:  'Coverage Cancelled',
            member_create:    'Member Created',
            member_update:    'Member Updated',
            duty_add:         'Duty Added',
            duty_remove:      'Duty Removed',
            shift_settings:   'Shift Settings',
            app_settings:     'App Settings',
            nuclear_reset:    'Test Data Cleared',
        };

        function getFilters() {
            return {
                action_filter: (document.getElementById('actlog-action-filter') || {}).value || '',
                search:        (document.getElementById('actlog-search')        || {}).value || '',
                date_from:     (document.getElementById('actlog-date-from')     || {}).value || '',
                date_to:       (document.getElementById('actlog-date-to')       || {}).value || '',
            };
        }

        function loadActivityLog(page) {
            if (!resultsEl) return;
            actlogPage = page || 1;
            resultsEl.innerHTML = '<p class="actlog-loading">Loading\u2026</p>';
            var params = Object.assign({ action: 'schedules_get_activity_log', page: actlogPage }, getFilters());
            schedulesAjax(params).then(function (res) {
                if (!res.success) {
                    resultsEl.innerHTML = '<p class="actlog-error">Error loading log.</p>';
                    return;
                }
                var d = res.data;
                actlogTotal = d.total;
                actlogPer   = d.per;
                renderActivityLog(d.rows);
                updateActlogPagination(d.page, d.total, d.per);
            });
        }

        function renderActivityLog(rows) {
            if (!resultsEl) return;
            if (!rows || !rows.length) {
                resultsEl.innerHTML = '<p class="actlog-empty">No activity found.</p>';
                if (paginEl) paginEl.hidden = true;
                return;
            }
            var html = '<div class="table-responsive"><table class="schedules-table actlog-table">';
            html += '<thead><tr><th>Date / Time</th><th>Badge</th><th>Name</th><th>Event</th><th>Description</th></tr></thead><tbody>';
            rows.forEach(function (row) {
                var dt    = row.created_at ? row.created_at.replace('T', ' ').slice(0, 16).replace(':', '') : '';
                var label = actionLabels[row.action] || row.action;
                html += '<tr>';
                html += '<td class="actlog-dt">'   + esc(dt)               + '</td>';
                html += '<td class="actlog-badge">' + esc(row.actor_badge)  + '</td>';
                html += '<td class="actlog-name">'  + esc(row.actor_name)   + '</td>';
                html += '<td><span class="actlog-pill actlog-pill-' + esc(row.action) + '">' + esc(label) + '</span></td>';
                html += '<td class="actlog-desc">'  + esc(row.description)  + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            resultsEl.innerHTML = html;
        }

        function updateActlogPagination(page, total, per) {
            if (!paginEl) return;
            var pages = Math.max(1, Math.ceil(total / per));
            paginEl.hidden = total <= per;
            if (pageInfo) pageInfo.textContent = 'Page ' + page + ' of ' + pages + ' (' + total + ' entries)';
            if (prevBtn) prevBtn.disabled = page <= 1;
            if (nextBtn) nextBtn.disabled = page >= pages;
        }

        var searchBtn = document.getElementById('actlog-search-btn');
        var resetBtn  = document.getElementById('actlog-reset-btn');

        if (searchBtn) searchBtn.addEventListener('click', function () { loadActivityLog(1); });
        if (resetBtn)  resetBtn.addEventListener('click', function () {
            var s = document.getElementById('actlog-search');
            var a = document.getElementById('actlog-action-filter');
            var f = document.getElementById('actlog-date-from');
            var t = document.getElementById('actlog-date-to');
            if (s) s.value = '';
            if (a) a.value = '';
            if (f) f.value = '';
            if (t) t.value = '';
            loadActivityLog(1);
        });
        if (prevBtn) prevBtn.addEventListener('click', function () { if (actlogPage > 1) loadActivityLog(actlogPage - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function () {
            if (actlogPage * actlogPer < actlogTotal) loadActivityLog(actlogPage + 1);
        });

        // Auto-load when tab activates (first visit only)
        var _actlogLoaded = false;
        document.addEventListener('click', function (e) {
            var tab = e.target.closest('.sup-sub-tab[data-view="actlog"]');
            if (!tab) return;
            if (!_actlogLoaded) { _actlogLoaded = true; loadActivityLog(1); }
        });
    }());

    /*--------------------------------------------------------------
    # Supervisor: Members
    --------------------------------------------------------------*/

    var SHIFT_DEFAULTS = {
        'A': { days: [0, 1, 2, 3], start: 6,  end: 18 },
        'B': { days: [3, 4, 5, 6], start: 6,  end: 18 },
        'C': { days: [0, 1, 2, 3], start: 18, end: 6  },
        'D': { days: [3, 4, 5, 6], start: 18, end: 6  }
    };

    function clearCustomSchedule(form) {
        form.querySelectorAll('.custom-day-check').forEach(function (cb) {
            cb.checked = false;
            var row = cb.closest('.custom-day-row');
            if (row) {
                row.querySelectorAll('.custom-time-select').forEach(function (sel) {
                    sel.disabled = true;
                });
                var times = row.querySelector('.custom-day-times');
                if (times) times.style.opacity = '0.4';
            }
        });
    }

    function setCustomDay(form, week, dow, start, end) {
        var row = form.querySelector('.custom-day-row[data-week="' + week + '"][data-dow="' + dow + '"]');
        if (!row) return;
        var cb = row.querySelector('.custom-day-check');
        if (cb) cb.checked = true;
        var startSel = row.querySelector('.custom-start-hour');
        var endSel   = row.querySelector('.custom-end-hour');
        if (startSel) { startSel.value = start; startSel.disabled = false; }
        if (endSel)   { endSel.value   = end;   endSel.disabled   = false; }
        var times = row.querySelector('.custom-day-times');
        if (times) times.style.opacity = '1';
    }

    function populateCustomSchedule(form, schedule) {
        clearCustomSchedule(form);
        if (!schedule || typeof schedule !== 'object') return;
        var hasWeeks = Object.prototype.hasOwnProperty.call(schedule, 'week1') ||
                       Object.prototype.hasOwnProperty.call(schedule, 'week2');
        if (hasWeeks) {
            [1, 2].forEach(function (wk) {
                var wkData = schedule['week' + wk] || {};
                for (var dow in wkData) {
                    if (Object.prototype.hasOwnProperty.call(wkData, dow)) {
                        var entry = wkData[dow];
                        setCustomDay(form, wk, parseInt(dow, 10), entry.start, entry.end);
                    }
                }
            });
        } else {
            // Old flat format — treat as week 1
            for (var dow in schedule) {
                if (Object.prototype.hasOwnProperty.call(schedule, dow)) {
                    var entry = schedule[dow];
                    setCustomDay(form, 1, parseInt(dow, 10), entry.start, entry.end);
                }
            }
        }
    }

    function readCustomScheduleData(form) {
        var data = {};
        form.querySelectorAll('.custom-day-check').forEach(function (cb) {
            if (!cb.checked) return;
            var row  = cb.closest('.custom-day-row');
            var dow  = row ? row.dataset.dow  : null;
            var week = row ? row.dataset.week : null;
            if (dow == null || week == null) return;
            var startSel = row.querySelector('.custom-start-hour');
            var endSel   = row.querySelector('.custom-end-hour');
            data['custom_day['   + week + '][' + dow + ']'] = '1';
            data['custom_start[' + week + '][' + dow + ']'] = startSel ? startSel.value : '0';
            data['custom_end['   + week + '][' + dow + ']'] = endSel   ? endSel.value   : '0';
        });
        return data;
    }

    document.addEventListener('click', function (e) {
        if (!e.target.matches('#switch-weeks-btn')) return;
        var form = e.target.closest('form');
        if (!form) return;

        // Snapshot both weeks' current state
        var snap = { 1: {}, 2: {} };
        [1, 2].forEach(function (wk) {
            form.querySelectorAll('.custom-day-row[data-week="' + wk + '"]').forEach(function (row) {
                var dow = row.dataset.dow;
                var cb  = row.querySelector('.custom-day-check');
                var s   = row.querySelector('.custom-start-hour');
                var en  = row.querySelector('.custom-end-hour');
                snap[wk][dow] = {
                    checked : cb  ? cb.checked  : false,
                    start   : s   ? s.value     : '6',
                    end     : en  ? en.value    : '18',
                };
            });
        });

        // Write week 2's snapshot into week 1's rows and vice versa
        [1, 2].forEach(function (wk) {
            var src = snap[wk === 1 ? 2 : 1];
            form.querySelectorAll('.custom-day-row[data-week="' + wk + '"]').forEach(function (row) {
                var dow  = row.dataset.dow;
                var data = src[dow];
                var cb   = row.querySelector('.custom-day-check');
                var s    = row.querySelector('.custom-start-hour');
                var en   = row.querySelector('.custom-end-hour');
                var times = row.querySelector('.custom-day-times');
                if (cb)    cb.checked    = data.checked;
                if (s)   { s.value       = data.start; s.disabled  = !data.checked; }
                if (en)  { en.value      = data.end;   en.disabled = !data.checked; }
                if (times) times.style.opacity = data.checked ? '1' : '0.4';
            });
        });
    });

    function filterMembers() {
        var search   = (document.getElementById('members-search') || {}).value || '';
        var shift    = (document.getElementById('members-filter-shift') || {}).value || '';
        var priority = (document.getElementById('members-filter-priority') || {}).value || '';
        var titleId  = (document.getElementById('members-filter-title') || {}).value || '';
        var q        = search.toLowerCase();

        var items = document.querySelectorAll('.member-card, .members-table-rows tbody tr');
        items.forEach(function (el) {
            var name = el.dataset.name || '';
            var s    = el.dataset.shift || '';
            var p    = el.dataset.priority || '';
            var t    = el.dataset.titleId || '';

            var show = true;
            if (q && name.indexOf(q) === -1) show = false;
            if (shift === 'none' && s !== '') show = false;
            else if (shift && shift !== 'none' && s !== shift) show = false;
            if (priority && p !== priority) show = false;
            if (titleId && t !== titleId) show = false;

            el.style.display = show ? '' : 'none';
        });
    }

    document.addEventListener('focusin', function (e) {
        if (e.target.matches('#members-search')) e.target.value = '';
    });

    document.addEventListener('input', function (e) {
        if (e.target.matches('#members-search')) filterMembers();
    });

    document.addEventListener('change', function (e) {
        if (e.target.matches('#members-filter-shift, #members-filter-priority, #members-filter-title')) filterMembers();
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('#add-member-btn')) openMemberModal(null);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-member-btn');
        if (!btn) return;
        var cs = {};
        try { cs = JSON.parse(btn.dataset.customSchedule || '{}'); } catch (ex) {}
        openMemberModal({
            user_id         : btn.dataset.userId,
            first_name      : btn.dataset.firstName,
            last_name       : btn.dataset.lastName,
            badge           : btn.dataset.badge,
            email           : btn.dataset.email,
            shift           : btn.dataset.shift,
            discipline      : btn.dataset.discipline,
            priority        : btn.dataset.priority,
            role            : btn.dataset.role,
            title_id        : btn.dataset.titleId      || '0',
            is_cto          : btn.dataset.isCto        || '0',
            pay_rate        : btn.dataset.payRate       || '0',
            sick_hours      : btn.dataset.sickHours     || '0',
            schedule_type   : btn.dataset.scheduleType || 'shift',
            custom_schedule : cs,
        });
    });

    function openMemberModal(data) {
        var modal   = document.getElementById('member-modal');
        var form    = modal ? modal.querySelector('form') : null;
        var title   = modal ? modal.querySelector('#member-modal-title') : null;
        var errorEl = modal ? modal.querySelector('#member-form-error') : null;

        if (!form) return;

        form.reset();
        if (errorEl) clearMsg(errorEl);
        clearCustomSchedule(form);
        var customGroupEl = document.getElementById('custom-schedule-group');
        if (customGroupEl) customGroupEl.hidden = true;

        function mset(name, val) {
            var el = form.elements[name];
            if (el) el.value = val;
        }

        if (data && data.user_id) {
            if (title) title.textContent = 'Edit Member';
            modal.dataset.mode = 'edit';
            mset('user_id',      data.user_id);
            mset('first_name',   data.first_name || '');
            mset('last_name',    data.last_name  || '');
            mset('badge_number', data.badge      || '');
            var badgeField = form.elements['badge_number'];
            if (badgeField) badgeField.readOnly = true;
            mset('email',        data.email    || '');
            mset('shift',        data.shift    || '');
            var memberDiscs = (data.discipline || '').split(',').map(function (d) { return d.trim(); }).filter(Boolean);
            form.querySelectorAll('input[name="discipline[]"]').forEach(function (cb) {
                cb.checked = memberDiscs.indexOf(cb.value) !== -1;
            });
            var otOverride = form.elements['ot_override'];
            if (otOverride) otOverride.checked = (data.priority === '5');
            mset('title_id',   data.title_id   || '0');
            mset('pay_rate',   data.pay_rate    || '0');
            mset('sick_hours', data.sick_hours  || '0');
            mset('member_role', data.role || 'member');
            var ctoCb = form.elements['is_cto'];
            if (ctoCb) ctoCb.checked = (data.is_cto === '1' || data.is_cto === 1 || data.is_cto === true);
            var pwField = form.elements['password'];
            if (pwField) pwField.removeAttribute('required');
            form.querySelectorAll('.new-only').forEach(function (el) { el.style.display = 'none'; });

            // Schedule type + custom schedule
            var schedType  = data.schedule_type || 'shift';
            var stRadios   = form.elements['schedule_type'];
            if (stRadios && stRadios.length) {
                for (var ri = 0; ri < stRadios.length; ri++) {
                    stRadios[ri].checked = (stRadios[ri].value === schedType);
                }
            }
            if (customGroupEl) customGroupEl.hidden = (schedType !== 'custom');
            if (schedType === 'custom') {
                populateCustomSchedule(form, data.custom_schedule || {});
            }
        } else {
            if (title) title.textContent = 'Add Member';
            modal.dataset.mode = 'add';
            mset('user_id', '0');
            var badgeField2 = form.elements['badge_number'];
            if (badgeField2) badgeField2.readOnly = false;
            var pwField2 = form.elements['password'];
            if (pwField2) pwField2.setAttribute('required', 'required');
            form.querySelectorAll('.new-only').forEach(function (el) { el.style.display = ''; });
        }

        modal.hidden = false;
        syncBodyLock();
        var box = modal.querySelector('.schedules-modal-box');
        if (box) box.focus();
    }

    function closeMemberModal() {
        var modal = document.getElementById('member-modal');
        if (modal) modal.hidden = true;
        syncBodyLock();
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.modal-close') || e.target.closest('.modal-close-btn')) closeMemberModal();
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('.schedules-modal-backdrop')) closeMemberModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('member-modal');
            if (modal && !modal.hidden) closeMemberModal();
        }
    });

    // Custom day checkbox → enable/disable time selects
    document.addEventListener('change', function (e) {
        if (!e.target.matches('.custom-day-check')) return;
        var row = e.target.closest('.custom-day-row');
        if (!row) return;
        var times = row.querySelector('.custom-day-times');
        if (times) times.style.opacity = e.target.checked ? '1' : '0.4';
        row.querySelectorAll('.custom-time-select').forEach(function (sel) {
            sel.disabled = !e.target.checked;
        });
    });

    // Schedule type radio → show/hide custom grid; pre-fill from shift on first switch
    document.addEventListener('change', function (e) {
        if (!e.target.matches('input[name="schedule_type"]')) return;
        var form = e.target.closest('#member-form');
        if (!form) return;
        var customGroup = document.getElementById('custom-schedule-group');
        var isCustom = e.target.value === 'custom';
        if (customGroup) customGroup.hidden = !isCustom;
        if (isCustom) {
            var anyChecked = !!form.querySelector('.custom-day-check:checked');
            if (!anyChecked) {
                var shiftEl = form.elements['shift'];
                var shift   = shiftEl ? shiftEl.value : '';
                var def     = shift ? SHIFT_DEFAULTS[shift] : null;
                if (def) {
                    [1, 2].forEach(function (wk) {
                        def.days.forEach(function (dow) {
                            setCustomDay(form, wk, dow, def.start, def.end);
                        });
                    });
                }
            }
        }
    });

    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#member-form')) return;
        e.preventDefault();

        var form    = e.target;
        var errorEl = document.getElementById('member-form-error');
        var submit  = document.getElementById('member-form-submit');
        var modal   = document.getElementById('member-modal');
        var mode    = modal ? modal.dataset.mode : 'add';

        clearMsg(errorEl);

        var fd = new FormData(form);

        var formData = {
            user_id      : parseInt(fd.get('user_id') || '0', 10),
            first_name   : fd.get('first_name')   || '',
            last_name    : fd.get('last_name')    || '',
            badge_number : fd.get('badge_number') || '',
            email        : fd.get('email')        || '',
            member_pass  : fd.get('password')     || '',
            shift        : fd.get('shift')        || '',
            discipline   : fd.getAll('discipline[]').join(','),
            priority     : fd.get('ot_override') === '1' ? '5' : '',
            member_role  : fd.get('member_role')  || 'member',
            title_id     : parseInt(fd.get('title_id') || '0', 10),
            is_cto       : fd.get('is_cto') === '1' ? 1 : 0,
            pay_rate     : parseFloat(fd.get('pay_rate')   || '0'),
            sick_hours   : parseFloat(fd.get('sick_hours') || '0'),
        };

        var stRadioList = form.elements['schedule_type'];
        formData.schedule_type = (stRadioList && stRadioList.value) ? stRadioList.value : 'shift';
        if (formData.schedule_type === 'custom') {
            var csData = readCustomScheduleData(form);
            for (var k in csData) {
                if (Object.prototype.hasOwnProperty.call(csData, k)) formData[k] = csData[k];
            }
        }

        var action = (mode === 'edit' && formData.user_id > 0) ? 'schedules_update_member' : 'schedules_create_member';
        formData.action = action;

        if (submit) { submit.disabled = true; submit.textContent = 'Saving\u2026'; }

        schedulesAjax(formData)
            .then(function (response) {
                if (response.success) {
                    showToast((response.data && response.data.message) ? response.data.message : 'Member saved.', 'success');
                    closeMemberModal();
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Could not save member.';
                    showMsg(errorEl, msg);
                    if (submit) { submit.disabled = false; submit.textContent = 'Save Member'; }
                }
            })
            .catch(function () {
                showMsg(errorEl, 'Network error. Please try again.');
                if (submit) { submit.disabled = false; submit.textContent = 'Save Member'; }
            });
    });


    // Deactivate member
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.deactivate-member-btn');
        if (!btn) return;

        var userId = btn.dataset.userId;
        var name   = btn.dataset.name || 'this member';

        if (!confirm('Deactivate ' + name + '? Their access will be revoked, but their history will be preserved.')) return;

        btn.disabled = true;
        btn.textContent = 'Deactivating\u2026';

        schedulesAjax({ action: 'schedules_deactivate_member', user_id: userId })
            .then(function (response) {
                if (response.success) {
                    showToast((response.data && response.data.message) ? response.data.message : 'Member deactivated.', 'success');
                    var row = btn.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity .3s';
                        row.style.opacity = '0';
                        setTimeout(function () {
                            if (row.parentNode) row.parentNode.removeChild(row);
                        }, 300);
                    }
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Could not deactivate member.';
                    showToast(msg, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Deactivate';
                }
            })
            .catch(function () {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.textContent = 'Deactivate';
            });
    });

    // Purge member (admin only — triple confirmation)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.purge-member-btn');
        if (!btn) return;

        var userId = btn.dataset.userId;
        var name   = btn.dataset.name || 'this member';

        if (!confirm('WARNING: This will permanently delete ' + name + ' and ALL of their duty assignment history.\n\nThis cannot be undone. Are you sure?')) return;
        if (!confirm('Second confirmation: Permanently delete ' + name + '\'s account and all records?')) return;

        var typed = prompt('Type the member\'s name to confirm permanent deletion:');
        if (!typed || typed.trim().toLowerCase() !== name.toLowerCase()) {
            alert('Name did not match. Purge cancelled.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Purging\u2026';

        schedulesAjax({ action: 'schedules_purge_member', user_id: userId })
            .then(function (response) {
                if (response.success) {
                    showToast((response.data && response.data.message) ? response.data.message : 'Member permanently deleted.', 'success');
                    var row = btn.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity .3s';
                        row.style.opacity = '0';
                        setTimeout(function () {
                            if (row.parentNode) row.parentNode.removeChild(row);
                        }, 300);
                    }
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Could not purge member.';
                    showToast(msg, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Purge Account';
                }
            })
            .catch(function () {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.textContent = 'Purge Account';
            });
    });

    /*--------------------------------------------------------------
    # Supervisor: Shift Settings
    --------------------------------------------------------------*/

    // Toggle day-row active state when checkbox is clicked
    document.addEventListener('change', function (e) {
        var cb = e.target.closest('.shift-day-active-cb');
        if (!cb) return;
        var row = cb.closest('.shift-day-row');
        if (!row) return;
        var isChecked = cb.checked;
        row.classList.toggle('shift-day-row-inactive', !isChecked);
        row.querySelectorAll('select').forEach(function (sel) {
            sel.disabled = !isChecked;
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#save-shift-settings-btn');
        if (!btn) return;

        var errorEl   = document.getElementById('settings-error');
        var successEl = document.getElementById('settings-success');
        clearMsg(errorEl);
        clearMsg(successEl);

        btn.disabled = true;
        btn.textContent = 'Saving\u2026';

        // Build shift data as a plain object, then JSON-encode it
        var shiftsObj = {};
        document.querySelectorAll('.shift-settings-card').forEach(function (card) {
            var shift = card.dataset.shift;
            if (!shift) return;

            var memberIn = card.querySelector('.member-count-input');
            var maxCapIn = card.querySelector('.max-cap-input');

            var w1 = [];
            card.querySelectorAll('input[name$="[days_week1][]"]:checked').forEach(function (cb) {
                w1.push(parseInt(cb.value, 10));
            });
            var w2 = [];
            card.querySelectorAll('input[name$="[days_week2][]"]:checked').forEach(function (cb) {
                w2.push(parseInt(cb.value, 10));
            });

            // Build two-week per-day schedule from checked day rows
            var daySchedule = { week1: {}, week2: {} };
            card.querySelectorAll('.shift-day-row').forEach(function (row) {
                var dow = row.dataset.dow;
                var wk  = row.dataset.week; // 'week1' or 'week2'
                if (dow === undefined || !wk || !daySchedule[wk]) return;
                var cb     = row.querySelector('.shift-day-active-cb');
                var dStart = row.querySelector('.shift-day-start');
                var dEnd   = row.querySelector('.shift-day-end');
                if (cb && cb.checked && dStart && dEnd) {
                    daySchedule[wk][dow] = {
                        start: parseInt(dStart.value, 10),
                        end:   parseInt(dEnd.value,   10),
                    };
                }
            });

            // Derive canonical start_hour/end_hour from first active day of week1
            var derivedStart = 6, derivedEnd = 18;
            var w1Keys = Object.keys(daySchedule.week1).map(Number).sort(function (a, b) { return a - b; });
            if (w1Keys.length > 0) {
                derivedStart = daySchedule.week1[String(w1Keys[0])].start;
                derivedEnd   = daySchedule.week1[String(w1Keys[0])].end;
            }

            shiftsObj[shift] = {
                start_hour:   derivedStart,
                end_hour:     derivedEnd,
                day_schedule: daySchedule,
                member_count: memberIn ? parseInt(memberIn.value, 10) : 0,
                max_capacity: maxCapIn ? parseInt(maxCapIn.value, 10) : 14,
            };
        });

        var anchorEl = document.getElementById('cycle-anchor-date');

        var params = new URLSearchParams();
        params.append('nonce', schedulesData.nonce);
        params.append('action', 'schedules_save_shift_settings');
        if (anchorEl) params.append('cycle_anchor', anchorEl.value);
        params.append('shifts_json', JSON.stringify(shiftsObj));

        fetch(schedulesData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (response) {
            if (response.success) {
                showToast('Shift settings saved. Refreshing\u2026', 'success');
                setTimeout(function () { window.location.reload(); }, 1000);
            } else {
                var msg = (response.data && response.data.message) ? response.data.message : 'Save failed.';
                showMsg(errorEl, msg);
                btn.disabled = false;
                btn.textContent = 'Save Shift Settings';
            }
        })
        .catch(function () {
            showMsg(errorEl, 'Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Save Shift Settings';
        });
    });

    /*--------------------------------------------------------------
    # Supervisor Nav Tabs
    --------------------------------------------------------------*/

    // Show/hide the correct sup-view. Tab active state is managed by click handlers.
    function activateSupTab(view) {
        document.querySelectorAll('.sup-view').forEach(function (v) {
            v.hidden = true;
            v.classList.remove('active');
        });
        var activeView = document.getElementById('sup-view-' + view);
        if (activeView) {
            activeView.hidden = false;
            activeView.classList.add('active');
        }
        // Auto-load PDO calendar when the view is activated and wrap is empty
        if (view === 'pdo') {
            var pdoWrap = document.getElementById('pdo-calendar-wrap');
            var pdoSel  = document.getElementById('pdo-shift-picker');
            if (pdoWrap && pdoSel && pdoWrap.innerHTML.trim() === '') {
                loadPdoCalendar(pdoSel.value, pdoYear, pdoMonth);
            }
        }
        // Auto-load approvals (time-off + coverage) when that view is activated
        if (view === 'coverage') {
            if (document.getElementById('sup-timeoff-pending-list')) loadSupPendingTimeoff();
            if (document.getElementById('sup-cover-pending-list')) {
                loadSupCoverRequests();
            } else {
                loadCoverRequests();
            }
        }
        // Load sick time for proxy member (or prompt if none selected)
        if (view === 'sicktime') {
            if (scheduleProxyUserId) {
                loadSickHistory(scheduleProxyUserId);
            } else {
                var stContent = document.getElementById('sicktime-content');
                if (stContent) stContent.innerHTML = '<p class="sicktime-placeholder">Select a member using the Acting As dropdown to view their sick time.</p>';
            }
        }
    }

    // Group tab (e.g. Schedule, Reports) — toggles sub-nav, auto-activates first/last sub-tab
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.sup-group-tab');
        if (!tab) return;
        var group  = tab.dataset.group;
        var subNav = document.getElementById('sup-sub-' + group);
        if (!subNav) return;
        var isOpen = !subNav.hidden;

        // Close all sub-navs and deactivate all group tabs
        document.querySelectorAll('.schedules-supervisor-nav-sub').forEach(function (s) { s.hidden = true; });
        document.querySelectorAll('.sup-group-tab').forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-expanded', 'false'); });
        // Deactivate direct tabs
        document.querySelectorAll('.sup-tab:not(.sup-group-tab)').forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });

        if (!isOpen) {
            subNav.hidden = false;
            tab.classList.add('active');
            tab.setAttribute('aria-expanded', 'true');
            // Activate the previously-active sub-tab, or the first one
            var activeSub = subNav.querySelector('.sup-sub-tab.active') || subNav.querySelector('.sup-sub-tab');
            if (activeSub) {
                document.querySelectorAll('.sup-sub-tab').forEach(function (t) { t.classList.remove('active'); });
                activeSub.classList.add('active');
                activateSupTab(activeSub.dataset.view);
                try { sessionStorage.setItem('schedules_sup_tab', activeSub.dataset.view); } catch(ex) {}
            }
        }

        // Reset proxy when leaving the Schedule group (after menu logic so it can't block navigation)
        if (group !== 'schedule' && scheduleResetProxy) try { scheduleResetProxy(); } catch(ex) {}
    });

    // Sub-tab — activates the view, keeps parent group tab open and active
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.sup-sub-tab');
        if (!tab) return;
        var view = tab.dataset.view;
        document.querySelectorAll('.sup-sub-tab').forEach(function (t) { t.classList.remove('active'); });
        tab.classList.add('active');
        activateSupTab(view);
        try { sessionStorage.setItem('schedules_sup_tab', view); } catch(ex) {}
    });

    // Direct tab (no data-group) — closes sub-navs, activates view
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.sup-tab');
        if (!tab || tab.dataset.group) return; // skip group tabs
        var view = tab.dataset.view;
        if (!view) return;
        document.querySelectorAll('.schedules-supervisor-nav-sub').forEach(function (s) { s.hidden = true; });
        document.querySelectorAll('.sup-group-tab').forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-expanded', 'false'); });
        document.querySelectorAll('.sup-sub-tab').forEach(function (t) { t.classList.remove('active'); });
        document.querySelectorAll('.sup-tab:not(.sup-group-tab)').forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-selected', 'false'); });
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
        activateSupTab(view);
        try { sessionStorage.setItem('schedules_sup_tab', view); } catch(ex) {}
        // Reset proxy — direct tabs are never under Schedule
        if (scheduleResetProxy) try { scheduleResetProxy(); } catch(ex) {}
    });

    /*--------------------------------------------------------------
    # Config: Discipline & Position CRUD
    --------------------------------------------------------------*/

    // ---- Generic modal helpers ----

    function syncBodyLock() {
        var anyOpen = !!document.querySelector('.schedules-modal:not([hidden])');
        document.body.classList.toggle('locked', anyOpen);
    }

    function openConfigModal(modalId, titleText, data) {
        var modal = document.getElementById(modalId);
        if (!modal) return;
        var form    = modal.querySelector('form');
        var titleEl = modal.querySelector('[id$="-modal-title"]');
        var errEl   = modal.querySelector('[id$="-form-error"]');
        if (form)    form.reset();
        if (errEl)   clearMsg(errEl);
        if (titleEl) titleEl.textContent = titleText;

        if (data) {
            Object.keys(data).forEach(function (k) {
                var el = form ? form.elements[k] : null;
                if (!el) return;
                if (el.type === 'checkbox') {
                    el.checked = !!parseInt(data[k], 10);
                } else {
                    el.value = data[k];
                }
            });
            // Show Active checkbox row on edit
            modal.querySelectorAll('.discipline-active-row, .position-active-row, .title-active-row').forEach(function (r) {
                r.style.display = '';
            });
        } else {
            modal.querySelectorAll('.discipline-active-row, .position-active-row, .title-active-row').forEach(function (r) {
                r.style.display = 'none';
            });
        }

        modal.hidden = false;
        syncBodyLock();
        var box = modal.querySelector('.schedules-modal-box');
        if (box) box.focus();
    }

    function closeConfigModal(modalId) {
        var modal = document.getElementById(modalId);
        if (modal) modal.hidden = true;
        syncBodyLock();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.modal-close, .modal-close-btn');
        if (!btn) return;
        var modal = btn.closest('.schedules-modal');
        if (modal) { modal.hidden = true; syncBodyLock(); }
    });

    // ---- Disciplines ----

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#add-discipline-btn')) return;
        openConfigModal('discipline-modal', 'Add Discipline', null);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-discipline-btn');
        if (!btn) return;
        openConfigModal('discipline-modal', 'Edit Discipline', {
            id            : btn.dataset.id,
            name          : btn.dataset.name,
            display_order : btn.dataset.order,
            is_active     : btn.dataset.active,
        });
    });

    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#app-settings-form')) return;
        e.preventDefault();
        var errEl   = document.getElementById('app-settings-error');
        var sucEl   = document.getElementById('app-settings-success');
        var submit  = document.getElementById('app-settings-submit');
        if (errEl) errEl.textContent = '';
        if (sucEl) sucEl.textContent = '';
        var fd = new FormData(e.target);
        var payload = {
            action                    : 'schedules_save_app_settings',
            nonce                     : schedulesData.nonce,
            duty_time_increment       : fd.get('duty_time_increment')       || '60',
            supervisors_can_claim_ot  : fd.get('supervisors_can_claim_ot')  || '0',
            ot_min_claim_hours        : fd.get('ot_min_claim_hours')        || '0',
            ot_priority_2_max         : fd.get('ot_priority_2_max')         || '0',
            ot_priority_3_max         : fd.get('ot_priority_3_max')         || '0',
            sick_hour_thresholds      : fd.get('sick_hour_thresholds')      || '30',
        };
        if (submit) { submit.disabled = true; submit.textContent = 'Saving\u2026'; }
        schedulesAjax(payload)
            .then(function (res) {
                if (submit) { submit.disabled = false; submit.textContent = 'Save Settings'; }
                if (res.success) {
                    schedulesData.dutyTimeIncrement     = parseInt(payload.duty_time_increment, 10);
                    schedulesData.supervisorsCanClaimOt = payload.supervisors_can_claim_ot;
                    schedulesData.minClaimHours         = Math.max(1, parseInt(payload.ot_min_claim_hours, 10) || 0);
                    if (dutyData) { syncRosterAssignments(); renderDutyTimeline(dutyData); }
                    if (sucEl) sucEl.textContent = 'Settings saved.';
                } else {
                    if (errEl) errEl.textContent = (res.data && res.data.message) || 'Could not save.';
                }
            })
            .catch(function () {
                if (submit) { submit.disabled = false; submit.textContent = 'Save Settings'; }
                if (errEl) errEl.textContent = 'Network error.';
            });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.matches('#discipline-form-submit')) return;

        var form   = document.getElementById('discipline-form');
        var errEl  = document.getElementById('discipline-form-error');
        var submit = e.target;
        clearMsg(errEl);

        var fd = new FormData(form);
        var payload = {
            action    : 'schedules_save_discipline',
            id        : fd.get('id')        || '0',
            name      : fd.get('name')      || '',
            is_active : fd.get('is_active') ? '1' : '0',
        };

        if (!payload.name.trim()) { showMsg(errEl, 'Name is required.'); return; }
        if (submit) { submit.disabled = true; submit.textContent = 'Saving\u2026'; }

        schedulesAjax(payload)
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Saved.', 'success');
                    closeConfigModal('discipline-modal');
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    showMsg(errEl, (res.data && res.data.message) || 'Save failed.');
                    if (submit) { submit.disabled = false; submit.textContent = 'Save'; }
                }
            })
            .catch(function () {
                showMsg(errEl, 'Network error.');
                if (submit) { submit.disabled = false; submit.textContent = 'Save'; }
            });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-discipline-btn');
        if (!btn) return;
        var name = btn.dataset.name || 'this discipline';
        if (!confirm('Deactivate "' + name + '"? It will be hidden but not deleted.')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_delete_discipline', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Deactivated.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not deactivate.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () { showToast('Network error.', 'error'); btn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.purge-discipline-btn');
        if (!btn) return;
        var name = btn.dataset.name || 'this discipline';
        if (!confirm('Permanently DELETE "' + name + '"?\n\nThis cannot be undone.')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_purge_discipline', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Deleted.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not delete.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () { showToast('Network error.', 'error'); btn.disabled = false; });
    });

    // ---- Positions ----

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#add-position-btn')) return;
        openConfigModal('position-modal', 'Add Position', null);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-position-btn');
        if (!btn) return;
        openConfigModal('position-modal', 'Edit Position', {
            id                      : btn.dataset.id,
            name                    : btn.dataset.name,
            required_discipline_id  : btn.dataset.discId,
            display_order           : btn.dataset.order,
            is_active               : btn.dataset.active,
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.matches('#position-form-submit')) return;

        var form   = document.getElementById('position-form');
        var errEl  = document.getElementById('position-form-error');
        var submit = e.target;
        clearMsg(errEl);

        var fd = new FormData(form);
        var payload = {
            action                 : 'schedules_save_position',
            id                     : fd.get('id')                     || '0',
            name                   : fd.get('name')                   || '',
            required_discipline_id : fd.get('required_discipline_id') || '0',
            is_active              : fd.get('is_active')              ? '1' : '0',
        };

        if (!payload.name.trim()) { showMsg(errEl, 'Name is required.'); return; }
        if (submit) { submit.disabled = true; submit.textContent = 'Saving\u2026'; }

        schedulesAjax(payload)
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Saved.', 'success');
                    closeConfigModal('position-modal');
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    showMsg(errEl, (res.data && res.data.message) || 'Save failed.');
                    if (submit) { submit.disabled = false; submit.textContent = 'Save'; }
                }
            })
            .catch(function () {
                showMsg(errEl, 'Network error.');
                if (submit) { submit.disabled = false; submit.textContent = 'Save'; }
            });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-position-btn');
        if (!btn) return;
        var name = btn.dataset.name || 'this position';
        if (!confirm('Deactivate "' + name + '"? It will be hidden but not deleted.')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_delete_position', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Deactivated.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not deactivate.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () { showToast('Network error.', 'error'); btn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.purge-position-btn');
        if (!btn) return;
        var name = btn.dataset.name || 'this position';
        if (!confirm('Permanently DELETE "' + name + '"?\n\nThis cannot be undone.')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_purge_position', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Deleted.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not delete.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () { showToast('Network error.', 'error'); btn.disabled = false; });
    });

    // ---- Titles ----

    document.addEventListener('click', function (e) {
        if (!e.target.closest('#add-title-btn')) return;
        openConfigModal('title-modal', 'Add Title', null);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-title-btn');
        if (!btn) return;
        openConfigModal('title-modal', 'Edit Title', {
            id            : btn.dataset.id,
            name          : btn.dataset.name,
            display_order : btn.dataset.order,
            is_active     : btn.dataset.active,
        });
    });

    document.addEventListener('click', function (e) {
        if (!e.target.matches('#title-form-submit')) return;

        var form   = document.getElementById('title-form');
        var errEl  = document.getElementById('title-form-error');
        var submit = e.target;
        clearMsg(errEl);

        var fd = new FormData(form);
        var payload = {
            action    : 'schedules_save_title',
            id        : fd.get('id')        || '0',
            name      : fd.get('name')      || '',
            is_active : fd.get('is_active') ? '1' : '0',
        };

        if (!payload.name.trim()) { showMsg(errEl, 'Name is required.'); return; }
        if (submit) { submit.disabled = true; submit.textContent = 'Saving\u2026'; }

        schedulesAjax(payload)
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Saved.', 'success');
                    closeConfigModal('title-modal');
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    showMsg(errEl, (res.data && res.data.message) || 'Save failed.');
                    if (submit) { submit.disabled = false; submit.textContent = 'Save'; }
                }
            })
            .catch(function () {
                showMsg(errEl, 'Network error.');
                if (submit) { submit.disabled = false; submit.textContent = 'Save'; }
            });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-title-btn');
        if (!btn) return;
        var name = btn.dataset.name || 'this title';
        if (!confirm('Deactivate "' + name + '"? It will be hidden but not deleted.')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_delete_title', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Deactivated.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not deactivate.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () { showToast('Network error.', 'error'); btn.disabled = false; });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.purge-title-btn');
        if (!btn) return;
        var name = btn.dataset.name || 'this title';
        if (!confirm('Permanently DELETE "' + name + '"?\n\nThis cannot be undone.')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_purge_title', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Deleted.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not delete.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () { showToast('Network error.', 'error'); btn.disabled = false; });
    });

    /*--------------------------------------------------------------
    # Config Table Drag-to-Reorder
    --------------------------------------------------------------*/

    function initDragSort(tableId, ajaxAction, rowIdAttr) {
        var dragging = null;

        document.addEventListener('mousedown', function (e) {
            if (!e.target.closest('.drag-handle')) return;
            var row = e.target.closest('tr');
            if (!row || !row.dataset[rowIdAttr]) return;
            var tbl = row.closest('table');
            if (!tbl || tbl.id !== tableId) return;
            e.preventDefault();
            dragging = row;
            dragging.classList.add('drag-row-active');
            document.body.classList.add('schedules-dragging');
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        });

        function onMouseMove(e) {
            var el = document.elementFromPoint(e.clientX, e.clientY);
            if (!el) return;
            var row = el.closest('tr');
            var tbody = dragging.parentNode;
            if (!row || row === dragging || !tbody || !tbody.contains(row)) return;
            var rect = row.getBoundingClientRect();
            var mid  = rect.top + rect.height / 2;
            var rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.indexOf(dragging) < rows.indexOf(row) && e.clientY > mid) {
                tbody.insertBefore(dragging, row.nextSibling);
            } else if (rows.indexOf(dragging) > rows.indexOf(row) && e.clientY < mid) {
                tbody.insertBefore(dragging, row);
            }
        }

        function onMouseUp() {
            var tbody = dragging.parentNode;
            dragging.classList.remove('drag-row-active');
            document.body.classList.remove('schedules-dragging');
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            dragging = null;
            if (!tbody) return;
            var ids = Array.from(tbody.querySelectorAll('tr')).map(function (r) {
                return r.dataset[rowIdAttr];
            }).filter(Boolean);
            if (ids.length) {
                schedulesAjax({ action: ajaxAction, ids: ids.join(',') })
                    .catch(function () { showToast('Order save failed.', 'error'); });
            }
        }
    }

    initDragSort('disciplines-table', 'schedules_reorder_disciplines', 'disciplineId');
    initDragSort('positions-table',   'schedules_reorder_positions',   'positionId');
    initDragSort('titles-table',      'schedules_reorder_titles',      'titleId');

    /*--------------------------------------------------------------
    # Duty Assignments — Timeline (Supervisor editable)
    --------------------------------------------------------------*/

    var dutyData = null; // current loaded data
    var dutyModalIsSub = false; // true when current modal opening is a sub-assignment

    // Convert "HH:MM:SS" or "HH:MM" to minutes since midnight
    function timeToMins(t) {
        var p = t.split(':');
        return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
    }

    // Format time for display: "06:00:00" → "0600"
    function fmtTime(t) {
        var p = t.split(':');
        return (p[0].length < 2 ? '0' + p[0] : p[0]) + (p[1].length < 2 ? '0' + p[1] : p[1]);
    }

    // Returns the current shift duration in minutes from dutyData (or default 12h).
    function shiftDurMins() {
        return ((dutyData && dutyData.shift_duration_hours) || 12) * 60;
    }

    // Compute left% and width% for a bar within the shift window.
    // shiftDurationHours is optional — reads dutyData if omitted.
    function barGeometry(startT, endT, shiftStartHour, shiftDurationHours) {
        var shiftStartMins = shiftStartHour * 60;
        var durMins        = shiftDurationHours ? shiftDurationHours * 60 : shiftDurMins();
        var sMins = timeToMins(startT);
        var eMins = timeToMins(endT);
        // Night-shift wrap: times < shiftStart mean they're past midnight
        if (sMins < shiftStartMins) sMins += 24 * 60;
        if (eMins <= shiftStartMins || eMins < sMins) eMins += 24 * 60;
        var left  = Math.max(0, Math.min(100, ((sMins - shiftStartMins) / durMins) * 100));
        var width = Math.max(0, Math.min(100 - left, ((eMins - sMins) / durMins) * 100));
        return { left: left.toFixed(3), width: width.toFixed(3) };
    }

    // Rebuild each roster member's assignments from the flat dutyData.assignments array.
    // Call this whenever dutyData.assignments changes before re-rendering.
    function syncRosterAssignments() {
        if (!dutyData || !dutyData.roster) return;
        var posMap = {};
        (dutyData.positions || []).forEach(function (p) { posMap[String(p.id)] = p.name; });
        dutyData.roster.forEach(function (m) {
            m.assignments = (dutyData.assignments || [])
                .filter(function (a) { return String(a.user_id) === String(m.user_id); })
                .map(function (a) {
                    return {
                        position   : posMap[String(a.position_id)] || '',
                        start_time : a.start_time.substring(0, 5),
                        end_time   : a.end_time.substring(0, 5),
                    };
                })
                .sort(function (a, b) { return a.start_time < b.start_time ? -1 : 1; });
        });
    }

    function renderDutyTimeline(data) {
        var grid = document.getElementById('duty-grid');
        var msg  = document.getElementById('duty-msg');
        if (!grid) return;

        dutyData = data;
        grid.innerHTML = '';
        if (msg) msg.textContent = '';

        if (!data.positions || !data.positions.length) {
            grid.innerHTML = '<p class="duty-empty">No active positions configured. Add positions in the Config tab.</p>';
            return;
        }

        if (isLocked && msg) msg.textContent = 'This shift has ended \u2014 assignments are locked.';

        var sh         = data.shift_start_hour || 6;
        var isSup      = schedulesData.isSupervisor === 'true' || schedulesData.isAdmin === 'true';
        var isLocked   = !!data.is_locked;
        var canEdit    = isSup && !isLocked;
        var totalHours = data.shift_duration_hours || 12;

        // Half-day blocked range — computed server-side and passed in the AJAX response
        var halfDayHtml = '';
        if (data.half_day_blocked_start !== null && data.half_day_blocked_start !== undefined) {
            var _bS = (data.half_day_blocked_start < 10 ? '0' : '') + data.half_day_blocked_start + ':00';
            var _bE = (data.half_day_blocked_end   < 10 ? '0' : '') + data.half_day_blocked_end   + ':00';
            var _hg = barGeometry(_bS, _bE, sh);
            halfDayHtml = '<div class="duty-halfday-overlay" style="left:' + _hg.left + '%;width:' + _hg.width + '%" title="Shift unavailable (half day)"></div>';
        }

        // Group assignments by position_id, split regular vs trainee
        var byPos        = {};
        var byPosTrainee = {};
        (data.assignments || []).forEach(function (a) {
            var pid = String(a.position_id);
            if (parseInt(a.is_trainee, 10)) {
                if (!byPosTrainee[pid]) byPosTrainee[pid] = [];
                byPosTrainee[pid].push(a);
            } else {
                if (!byPos[pid]) byPos[pid] = [];
                byPos[pid].push(a);
            }
        });
        Object.keys(byPos).forEach(function (pid) {
            byPos[pid].sort(function (a, b) {
                return timeToAbsMins(a.start_time, sh) - timeToAbsMins(b.start_time, sh);
            });
        });
        Object.keys(byPosTrainee).forEach(function (pid) {
            byPosTrainee[pid].sort(function (a, b) {
                return timeToAbsMins(a.start_time, sh) - timeToAbsMins(b.start_time, sh);
            });
        });

        // Build HTML
        var html = '<div class="duty-timeline-col"><div class="duty-timeline">';

        // --- Header ---
        html += '<div class="duty-timeline-header">';
        html += '<div class="duty-row-label"></div>';
        html += '<div class="duty-track-col"><div class="duty-track duty-track-header">';
        for (var hi = 0; hi <= totalHours; hi++) {
            var hr = (sh + hi) % 24;
            var lp = ((hi / totalHours) * 100).toFixed(3);
            var tx = -((hi / totalHours) * 100).toFixed(3);
            html += '<span class="duty-hour-tick" style="left:' + lp + '%;transform:translateX(' + tx + '%)">' +
                    (hr < 10 ? '0' : '') + hr + '00</span>';
        }
        html += halfDayHtml;
        html += '</div></div>';
        html += '</div>'; // .duty-timeline-header

        // --- Position rows ---
        data.positions.forEach(function (pos) {
            var posId   = String(pos.id);
            var posDisc = pos.discipline_slug || '';
            var asgns   = byPos[posId] || [];

            html += '<div class="duty-row" data-position-id="' + esc(posId) + '">';

            // Label
            html += '<div class="duty-row-label">';
            html += '<span class="duty-pos-name">' + esc(pos.name) + '</span>';
            html += '</div>';

            // Track
            html += '<div class="duty-track-col"><div class="duty-track">';
            if (canEdit) {
                html += '<div class="duty-bg-bar duty-add-btn"' +
                        ' data-pos-id="' + esc(posId) + '"' +
                        ' data-pos-name="' + esc(pos.name) + '"' +
                        ' data-pos-disc="' + esc(posDisc) + '"' +
                        ' title="Add assignment"></div>';
            } else {
                html += '<div class="duty-bg-bar"></div>';
            }

            asgns.forEach(function (a) {
                var geo   = barGeometry(a.start_time, a.end_time, sh);
                var label = esc(formatName(a)) + ' \u2022 ' + fmtTime(a.start_time) + '\u2013' + fmtTime(a.end_time);
                html += '<div class="duty-bar" style="left:' + geo.left + '%;width:' + geo.width + '%"' +
                        ' data-id="' + esc(String(a.id)) + '"' +
                        ' title="' + esc(formatName(a) + ' ' + fmtTime(a.start_time) + '-' + fmtTime(a.end_time)) + '">';
                html += '<span class="duty-bar-label">' + label + '</span>';
                if (canEdit) {
                    html += '<button class="basic-btn duty-sub-btn" type="button"' +
                            ' data-pos-id="' + esc(posId) + '"' +
                            ' data-pos-name="' + esc(pos.name) + '"' +
                            ' data-pos-disc="' + esc(posDisc) + '"' +
                            ' data-main-user-id="' + esc(String(a.user_id)) + '"' +
                            ' aria-label="Add sub-assignment" title="Add person to sit with">+</button>';
                    html += '<button class="basic-btn duty-remove-btn" type="button"' +
                            ' data-id="' + esc(String(a.id)) + '"' +
                            ' aria-label="Remove">&times;</button>';
                }
                html += '</div>';
            });

            html += halfDayHtml;
            html += '</div></div>'; // .duty-track / .duty-track-col
            html += '</div>'; // .duty-row

            // Sub-row (if any sub-assignments for this position)
            var traineeAsgns = byPosTrainee[posId] || [];
            if (traineeAsgns.length) {
                html += '<div class="duty-row duty-row-trainee" data-position-id="' + esc(posId) + '">';
                html += '<div class="duty-row-label"></div>';
                html += '<div class="duty-track-col"><div class="duty-track">';
                html += '<div class="duty-bg-bar duty-bg-bar-trainee"></div>';
                traineeAsgns.forEach(function (a) {
                    var geo   = barGeometry(a.start_time, a.end_time, sh);
                    var label = '(T) ' + esc(formatName(a)) + ' \u2022 ' + fmtTime(a.start_time) + '\u2013' + fmtTime(a.end_time);
                    html += '<div class="duty-bar duty-bar-trainee" style="left:' + geo.left + '%;width:' + geo.width + '%"' +
                            ' data-id="' + esc(String(a.id)) + '"' +
                            ' title="' + esc(formatName(a) + ' (Trainee) ' + fmtTime(a.start_time) + '-' + fmtTime(a.end_time)) + '">';
                    html += '<span class="duty-bar-label">' + label + '</span>';
                    if (canEdit) {
                        html += '<button class="basic-btn duty-remove-btn" type="button"' +
                                ' data-id="' + esc(String(a.id)) + '"' +
                                ' aria-label="Remove">&times;</button>';
                    }
                    html += '</div>';
                });
                html += halfDayHtml;
                html += '</div></div>'; // .duty-track / .duty-track-col
                html += '</div>'; // .duty-row.duty-row-trainee
            }
        });

        html += '</div></div>'; // .duty-timeline, .duty-timeline-col

        // --- Roster ---
        var roster = data.roster || [];
        // Sort: absent members go to the bottom
        roster = roster.slice().sort(function (a, b) {
            var aAbs = !!a.timeoff ? 1 : 0;
            var bAbs = !!b.timeoff ? 1 : 0;
            return aAbs - bAbs;
        });
        if (roster.length || canEdit) {
            html += '<div class="duty-roster-col">';
        if (roster.length) {
            html += '<table class="duty-roster">';
            html += '<thead><tr>';
            html += '<th>#</th><th>Name</th><th>Title</th><th>Assignment</th>';
            if (isSup) html += '<th></th>';
            html += '</tr></thead><tbody>';
            roster.forEach(function (m, idx) {
                var isOt      = m.type === 'ot';
                var isCustom  = m.type === 'custom';
                var isAbsent  = !!m.timeoff;
                var rowClass  = isAbsent ? ' class="duty-roster-absent"' : (isOt ? ' class="duty-roster-ot"' : '');
                var dragAttrs = isSup && !isAbsent ? ' draggable="true" data-user-id="' + esc(String(m.user_id)) + '"' : '';
                html += '<tr' + rowClass + dragAttrs + '>';
                html += '<td>' + (idx + 1) + '</td>';
                html += '<td>' + esc(formatName(m)) + (isOt ? ' <span class="duty-roster-badge ot">OT</span>' : '') + '</td>';
                html += '<td>' + esc(m.title || '') + '</td>';

                // Assignment cell — show assignments with gap detection
                // OT: use claimed hour blocks; custom: use their scheduled hours; regulars: full shift
                var memberWindows;
                if (isOt && m.ot_hours && m.ot_hours.length) {
                    memberWindows = m.ot_hours.map(function (h) {
                        var ws = (h.start < 10 ? '0' : '') + h.start + ':00';
                        var we = (h.end < 10 ? '0' : '') + h.end + ':00';
                        return { startTime: ws, endTime: we };
                    });
                } else if (isCustom && m.custom_hours) {
                    var chFmt = function (h) { return scheduleTimeFmt(h).slice(0, 2) + ':' + scheduleTimeFmt(h).slice(2); };
                    memberWindows = [{ startTime: chFmt(m.custom_hours.start || 0), endTime: chFmt(m.custom_hours.end || 0) }];
                } else {
                    var _ss = (sh < 10 ? '0' : '') + sh + ':00';
                    var _se = ((sh + totalHours) % 24);
                    _se = (_se < 10 ? '0' : '') + _se + ':00';
                    memberWindows = [{ startTime: _ss, endTime: _se }];
                }

                html += '<td>';
                if (isAbsent) {
                    var toLabel = m.timeoff.toUpperCase();
                    html += '<span class="duty-roster-timeoff">(' + esc(toLabel) + ')</span>';
                } else if (m.assignments && m.assignments.length) {
                    var sorted = m.assignments.slice().sort(function (a, b) { return a.start_time < b.start_time ? -1 : 1; });
                    var parts = [];
                    memberWindows.forEach(function (win) {
                        var cursor = win.startTime;
                        sorted.forEach(function (a) {
                            var aStart = a.start_time.substring(0, 5);
                            var aEnd   = a.end_time.substring(0, 5);
                            if (timeToAbsMins(aEnd, sh) <= timeToAbsMins(win.startTime, sh) || timeToAbsMins(aStart, sh) >= timeToAbsMins(win.endTime, sh)) return;
                            if (timeToAbsMins(aStart, sh) > timeToAbsMins(cursor, sh)) {
                                parts.push('<span class="duty-roster-gap">' + esc(fmtTime(cursor) + '\u2013' + fmtTime(aStart)) + '</span>');
                            }
                            parts.push('<span class="duty-roster-asgn">' +
                                       esc(fmtTime(aStart) + '\u2013' + fmtTime(aEnd)) +
                                       ' <strong>' + esc(a.position) + '</strong></span>');
                            if (timeToAbsMins(aEnd, sh) > timeToAbsMins(cursor, sh)) cursor = aEnd;
                        });
                        if (timeToAbsMins(cursor, sh) < timeToAbsMins(win.endTime, sh)) {
                            parts.push('<span class="duty-roster-gap">' + esc(fmtTime(cursor) + '\u2013' + fmtTime(win.endTime)) + '</span>');
                        }
                    });
                    html += parts.join(' ');
                } else {
                    var winParts = memberWindows.map(function (win) {
                        return '<span class="duty-roster-gap">' + esc(fmtTime(win.startTime) + '\u2013' + fmtTime(win.endTime)) + '</span>';
                    });
                    html += winParts.join(' ');
                }
                html += '</td>';
                if (isSup) {
                    html += '<td>';
                    if (!isAbsent) {
                        html += '<button class="basic-btn duty-ab-btn" type="button"' +
                                ' data-user-id="' + esc(String(m.user_id)) + '"' +
                                ' data-date="' + esc(data.date) + '"' +
                                ' data-shift="' + esc(data.shift_letter) + '"' +
                                ' title="Mark absent">AB</button>';
                    }
                    html += '</td>';
                }
                html += '</tr>';
            });
            html += '</tbody></table>';
        } // end if roster.length

        if (canEdit) {
            var rosterIds = {};
            (data.roster || []).forEach(function (m) { rosterIds[String(m.user_id)] = true; });
            var addableMembers = (data.addable_members || []).filter(function (m) {
                return !rosterIds[String(m.user_id)];
            });
            var otIncrement  = parseInt(schedulesData.dutyTimeIncrement, 10) || 60;
            var otShiftMins  = sh * 60;
            var addOtEndMins = otShiftMins + shiftDurMins();
            var addOtEndH0   = Math.floor(addOtEndMins / 60) % 24;
            var addOtEndM0   = addOtEndMins % 60;
            var addOtEndV    = (addOtEndH0 < 10 ? '0' : '') + addOtEndH0 + ':' + (addOtEndM0 < 10 ? '0' : '') + addOtEndM0;

            html += '<div class="duty-add-ot-wrap no-print">';
            html += '<div class="duty-add-ot-panel">';
            html += '<select class="duty-add-ot-member"><option value="">Select member\u2026</option>';
            addableMembers.forEach(function (m) {
                html += '<option value="' + esc(String(m.user_id)) + '">' + esc(m.display_name) + '</option>';
            });
            html += '</select>';
            html += '<select class="duty-add-ot-start">';
            for (var otI = 0; otI < 24 * 60; otI += otIncrement) {
                var otMs = (otShiftMins + otI) % (24 * 60);
                var otH  = Math.floor(otMs / 60); var otM = otMs % 60;
                var otV  = (otH < 10 ? '0' : '') + otH + ':' + (otM < 10 ? '0' : '') + otM;
                html += '<option value="' + otV + '">' + (otH < 10 ? '0' : '') + otH + (otM < 10 ? '0' : '') + otM + '</option>';
            }
            html += '</select>';
            html += '<span class="duty-add-ot-sep">\u2013</span>';
            html += '<select class="duty-add-ot-end">';
            for (var otJ = otIncrement; otJ <= 24 * 60; otJ += otIncrement) {
                var otMs2 = (otShiftMins + otJ) % (24 * 60);
                var otH2  = Math.floor(otMs2 / 60); var otM2 = otMs2 % 60;
                var otV2  = (otH2 < 10 ? '0' : '') + otH2 + ':' + (otM2 < 10 ? '0' : '') + otM2;
                html += '<option value="' + otV2 + '"' + (otV2 === addOtEndV ? ' selected' : '') + '>' + (otH2 < 10 ? '0' : '') + otH2 + (otM2 < 10 ? '0' : '') + otM2 + '</option>';
            }
            html += '</select>';
            html += '<button type="button" class="schedules-btn schedules-btn-small duty-add-ot-submit">+ Add OT Member</button>';
            html += '<span class="duty-add-ot-msg"></span>';
            html += '</div>'; // .duty-add-ot-panel
            html += '</div>'; // .duty-add-ot-wrap
        }

            html += '</div>'; // .duty-roster-col
        }

        grid.innerHTML = html;
        markUniqueSpans(grid);

        if (!data.day_id && msg) {
            msg.textContent = 'No shift record exists yet for this date \u2014 it will be created on first assignment.';
        }
    }

    // --- Shift filter helpers ---
    function getCycleWeek(dateStr, anchor) {
        var d = new Date(dateStr + 'T00:00:00');
        var a = new Date(anchor   + 'T00:00:00');
        var anchorSunday = new Date(a.getTime() - a.getDay() * 86400000);
        var dateSunday   = new Date(d.getTime() - d.getDay() * 86400000);
        var weeks = Math.round((dateSunday - anchorSunday) / (7 * 86400000));
        return ((weeks % 2) + 2) % 2; // 0 or 1
    }

    function filterDutyShifts(dateStr) {
        var shiftEl = document.getElementById('duty-shift');
        if (!shiftEl || !dateStr || !schedulesData.shifts || !schedulesData.shifts.length) return;
        var d          = new Date(dateStr + 'T00:00:00');
        var dow        = d.getDay();
        var cycleWeek  = getCycleWeek(dateStr, schedulesData.cycleAnchor || '2025-01-06');
        var firstValid = null;
        Array.prototype.forEach.call(shiftEl.options, function (opt) {
            var def = null;
            for (var i = 0; i < schedulesData.shifts.length; i++) {
                if (schedulesData.shifts[i].letter === opt.value) { def = schedulesData.shifts[i]; break; }
            }
            var works = def && (cycleWeek === 0 ? def.workDays : def.workDaysWeek2).indexOf(dow) !== -1;
            opt.disabled = !works;
            opt.hidden   = !works;
            if (works && firstValid === null) firstValid = opt.value;
        });
        if (shiftEl.selectedIndex >= 0 && shiftEl.options[shiftEl.selectedIndex].disabled && firstValid) {
            shiftEl.value = firstValid;
        }
    }

    // --- Load button ---
    function loadDutyGrid(date, shift) {
        var grid = document.getElementById('duty-grid');
        var msg  = document.getElementById('duty-msg');
        var btn  = document.getElementById('duty-load-btn');

        if (!date) { if (msg) msg.textContent = 'Please select a date.'; return; }
        if (msg) msg.textContent = '';
        if (grid) grid.innerHTML = '<p class="duty-loading">Loading\u2026</p>';
        if (btn) btn.disabled = true;

        schedulesAjax({ action: 'schedules_get_duty_data', date: date, shift: shift })
            .then(function (res) {
                if (btn) btn.disabled = false;
                if (res.success) {
                    if (res.data && res.data.no_record) {
                        if (grid) grid.innerHTML = '<p class="duty-empty">No duty assignment record exists for this day.</p>';
                        return;
                    }
                    try { sessionStorage.setItem('schedules_duty_date',  date);  } catch(ex) {}
                    try { sessionStorage.setItem('schedules_duty_shift', shift); } catch(ex) {}
                    renderDutyTimeline(res.data);
                } else {
                    if (grid) grid.innerHTML = '';
                    if (msg) msg.textContent = (res.data && res.data.message) || 'Failed to load.';
                }
            })
            .catch(function () {
                if (btn) btn.disabled = false;
                if (grid) grid.innerHTML = '';
                if (msg) msg.textContent = 'Network error.';
            });
    }

    document.addEventListener('change', function (e) {
        if (!e.target.matches('#duty-date, #duty-shift')) return;
        var dateEl  = document.getElementById('duty-date');
        var shiftEl = document.getElementById('duty-shift');
        if (!dateEl || !shiftEl) return;
        if (e.target === dateEl) filterDutyShifts(dateEl.value);
        loadDutyGrid(dateEl.value, shiftEl.value);
    });

    // --- Print duty grid ---
    document.addEventListener('click', function (e) {
        if (!e.target.matches('#duty-print-btn')) return;
        var dateEl  = document.getElementById('duty-date');
        var shiftEl = document.getElementById('duty-shift');
        var date    = dateEl  ? dateEl.value  : '';
        var shift   = shiftEl ? shiftEl.value : '';
        var header  = 'Duty Assignment' + (shift ? ' \u2014 Shift ' + shift : '') + (date ? ' \u2014 ' + date : '');
        schedulePrint(document.getElementById('duty-grid'), header);
    });

    // --- Remove assignment button ---
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.duty-remove-btn');
        if (!btn) return;
        e.stopPropagation();

        var id = btn.dataset.id;
        if (!confirm('Remove this assignment?')) return;

        btn.disabled = true;

        schedulesAjax({ action: 'schedules_remove_duty_assignment', assignment_id: id })
            .then(function (res) {
                if (res.success) {
                    // Remove from dutyData and re-render so roster updates immediately
                    if (dutyData && dutyData.assignments) {
                        var subIds = (res.data && res.data.voided_sub_ids) ? res.data.voided_sub_ids.map(String) : [];
                        dutyData.assignments = dutyData.assignments.filter(function (a) {
                            return String(a.id) !== String(id) && subIds.indexOf(String(a.id)) === -1;
                        });
                        syncRosterAssignments();
                        renderDutyTimeline(dutyData);
                    }
                } else {
                    showToast((res.data && res.data.message) || 'Could not remove.', 'error');
                    btn.disabled = false;
                }
            })
            .catch(function () { showToast('Network error.', 'error'); btn.disabled = false; });
    });

    // --- Add OT Member submit ---
    document.addEventListener('click', function (e) {
        if (!e.target.matches('.duty-add-ot-submit')) return;
        var panel     = e.target.closest('.duty-add-ot-panel');
        if (!panel) return;
        var memberSel = panel.querySelector('.duty-add-ot-member');
        var startSel  = panel.querySelector('.duty-add-ot-start');
        var endSel    = panel.querySelector('.duty-add-ot-end');
        var msgEl     = panel.querySelector('.duty-add-ot-msg');

        var userId = memberSel ? memberSel.value : '';
        var startV = startSel ? startSel.value  : '';
        var endV   = endSel   ? endSel.value    : '';

        if (!userId) { if (msgEl) msgEl.textContent = 'Select a member.'; return; }
        if (!startV || !endV || startV === endV) { if (msgEl) msgEl.textContent = 'Select start and end times.'; return; }

        var date  = (document.getElementById('duty-date')  || {}).value || '';
        var shift = (document.getElementById('duty-shift') || {}).value || '';
        if (!date || !shift) { if (msgEl) msgEl.textContent = 'Load a duty day first.'; return; }

        e.target.disabled = true;
        if (msgEl) msgEl.textContent = 'Adding\u2026';

        schedulesAjax({ action: 'schedules_supervisor_add_ot', date: date, shift: shift, user_id: userId, start_time: startV, end_time: endV })
            .then(function (res) {
                e.target.disabled = false;
                if (res.success) {
                    loadDutyGrid(date, shift);
                } else {
                    if (msgEl) msgEl.textContent = (res.data && res.data.message) || 'Failed.';
                }
            })
            .catch(function () {
                e.target.disabled = false;
                if (msgEl) msgEl.textContent = 'Network error.';
            });
    });

    // --- Shared modal open helper ---
    function timeToAbsMins(timeStr, shiftStartHour) {
        var parts = timeStr.substring(0, 5).split(':');
        var mins  = parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
        if (mins < shiftStartHour * 60) mins += 24 * 60;
        return mins;
    }

    function openDutyModal(opts) {
        // opts: { mode:'add'|'edit', posId, posName, posDisc, assignmentId, userId, startTime, endTime }
        var modal = document.getElementById('duty-modal');
        if (!modal || !dutyData) return;

        var sh     = dutyData.shift_start_hour || 6;
        var isEdit = opts.mode === 'edit';

        var dayIdEl    = modal.querySelector('[name="day_id"]');
        var dateHidEl  = modal.querySelector('[name="date"]');
        var shiftHidEl = modal.querySelector('[name="shift"]');
        var posIdEl    = modal.querySelector('[name="position_id"]');
        var asgIdEl    = modal.querySelector('[name="assignment_id"]');
        var dispEl     = modal.querySelector('#duty-position-display');
        var errEl      = modal.querySelector('#duty-form-error');
        var titleEl    = document.getElementById('duty-modal-title');
        var submitBtn  = document.getElementById('duty-form-submit');

        if (!dayIdEl) return;
        dayIdEl.value = dutyData.day_id || 0;
        if (dateHidEl)  dateHidEl.value  = document.getElementById('duty-date')  ? document.getElementById('duty-date').value  : '';
        if (shiftHidEl) shiftHidEl.value = document.getElementById('duty-shift') ? document.getElementById('duty-shift').value : '';
        if (posIdEl)    posIdEl.value    = opts.posId || '';
        if (asgIdEl)    asgIdEl.value    = opts.assignmentId || '';
        if (dispEl)     dispEl.textContent = opts.posName || '';
        if (errEl)      errEl.textContent  = '';
        dutyModalIsSub = !!opts.isSub;
        var isSubEl = document.getElementById('duty-is-sub');
        if (isSubEl) isSubEl.value = dutyModalIsSub ? '1' : '0';
        if (titleEl)    titleEl.textContent   = isEdit ? (dutyModalIsSub ? 'Edit Trainee' : 'Edit Assignment') : (dutyModalIsSub ? 'Add Trainee' : 'Add Assignment');
        if (submitBtn)  submitBtn.textContent  = isEdit ? 'Update Assignment' : (dutyModalIsSub ? 'Add Trainee' : 'Add Assignment');

        // Member select
        var memberSel = modal.querySelector('#duty-member');
        if (!memberSel) return;
        memberSel.innerHTML = '<option value="">Select member\u2026</option>';
        (dutyData.members || []).forEach(function (m) {
            if (!dutyModalIsSub && opts.posDisc && (!m.disciplines || m.disciplines.indexOf(opts.posDisc) === -1)) return;
            if (dutyModalIsSub && opts.excludeUserId && String(m.user_id) === String(opts.excludeUserId)) return;
            var opt = document.createElement('option');
            opt.value = m.user_id;
            opt.textContent = formatName(m) + (m.type === 'ot' ? ' (OT)' : '');
            var freeSlots = getMemberFreeSlots(m.user_id, sh, isEdit ? opts.assignmentId : null);
            var freeTotal = freeSlots.reduce(function (sum, s) { return sum + (s.e - s.s); }, 0);
            if (freeTotal === 0) {
                opt.disabled = true;
                opt.textContent += ' \u2014 fully assigned';
            } else if (freeTotal < 12 * 60) {
                opt.textContent += ' \u2014 avail. ' + fmtTime(minsToTime(freeSlots[0].s)) + '\u2013' + fmtTime(minsToTime(freeSlots[0].e));
                if (freeSlots.length > 1) opt.textContent += ' (+' + (freeSlots.length - 1) + ')';
            }
            memberSel.appendChild(opt);
        });
        if (opts.userId) {
            memberSel.value = String(opts.userId);
            if (!isEdit) {
                var memberSlots = getMemberFreeSlots(opts.userId, sh, null);
                var free;
                if (dutyModalIsSub) {
                    free = memberSlots;
                } else {
                    var posSlots = opts.posId ? getPosFreeSots(opts.posId, sh, opts.posName) : [];
                    free = posSlots.length ? intersectSlots(posSlots, memberSlots) : memberSlots;
                }
                if (free.length > 0) {
                    opts.startTime = minsToTime(free[0].s);
                    opts.endTime   = minsToTime(free[0].e);
                }
            }
        }

        // Time selects — filtered to member/position availability
        var startSel = modal.querySelector('#duty-start');
        var endSel   = modal.querySelector('#duty-end');
        if (!startSel || !endSel) return;
        var increment = parseInt(schedulesData.dutyTimeIncrement, 10) || 60;
        var allowedSlots;
        if (dutyModalIsSub && isEdit && opts.userId) {
            // Sub-assignments edit: filter to member's current free slots (excluding this assignment)
            allowedSlots = getMemberFreeSlots(opts.userId, sh, opts.assignmentId);
        } else if (dutyModalIsSub && opts.userId) {
            // Sub-assignments add: filter to member's free slots only (no position filter)
            allowedSlots = getMemberFreeSlots(opts.userId, sh, null);
        } else if (dutyModalIsSub) {
            // Sub-assignments with no member yet: full shift
            allowedSlots = [];
        } else if (isEdit && opts.userId) {
            allowedSlots = getMemberFreeSlots(opts.userId, sh, opts.assignmentId);
        } else if (opts.userId) {
            var mSlots = getMemberFreeSlots(opts.userId, sh, null);
            var pSlots = opts.posId ? getPosFreeSots(opts.posId, sh, opts.posName) : [];
            allowedSlots = pSlots.length ? intersectSlots(pSlots, mSlots) : mSlots;
        } else {
            allowedSlots = opts.posId ? getPosFreeSots(opts.posId, sh, opts.posName) : [];
        }
        // Constrain to half-day available window when applicable
        if (dutyData && dutyData.half_day_blocked_start !== null && dutyData.half_day_blocked_start !== undefined) {
            var _blockedS  = dutyData.half_day_blocked_start * 60;
            var _blockedE  = dutyData.half_day_blocked_end   * 60;
            var _shiftS    = sh * 60;
            var _shiftE    = sh * 60 + shiftDurMins();
            var _availSlot = _blockedS === _shiftS
                ? [{ s: _blockedE, e: _shiftE }]   // blocked at start → available from blockedEnd to shiftEnd
                : [{ s: _shiftS,   e: _blockedS }]; // blocked at end   → available from shiftStart to blockedStart
            allowedSlots = allowedSlots && allowedSlots.length
                ? intersectSlots(allowedSlots, _availSlot)
                : _availSlot;
        }
        var defaultStart = opts.startTime || ((sh < 10 ? '0' : '') + sh + ':00');
        var defaultEnd   = opts.endTime   || (((sh + 12) % 24 < 10 ? '0' : '') + ((sh + 12) % 24) + ':00');
        buildTimeSelects(startSel, endSel, allowedSlots, sh, increment, defaultStart, defaultEnd);

        modal.hidden = false;
        syncBodyLock();
        memberSel.focus();
    }

    // Builds start/end <select> options filtered to allowedSlots (or full shift if empty).
    // increment is in minutes (60 = hourly, 30 = half-hour, 15 = quarter-hour).
    function buildTimeSelects(startSel, endSel, allowedSlots, sh, increment, currentStart, currentEnd, endH) {
        var shiftStart = sh * 60;
        var shiftEnd   = endH !== undefined
            ? sh * 60 + (endH > sh ? (endH - sh) * 60 : (24 - sh + endH) * 60)
            : sh * 60 + shiftDurMins();
        var slots = (allowedSlots && allowedSlots.length) ? allowedSlots : [{ s: shiftStart, e: shiftEnd }];
        startSel.innerHTML = '';
        endSel.innerHTML   = '';
        for (var t = shiftStart; t <= shiftEnd; t += increment) {
            var hh  = Math.floor(t / 60) % 24;
            var mm  = t % 60;
            var val = (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
            var lbl = (hh < 10 ? '0' : '') + hh + (mm < 10 ? '0' : '') + mm;
            if (slots.some(function (sl) { return sl.s <= t && t < sl.e; })) {
                var optS = document.createElement('option');
                optS.value = val; optS.textContent = lbl;
                startSel.appendChild(optS);
            }
            if (t > shiftStart && slots.some(function (sl) { return sl.s < t && t <= sl.e; })) {
                var optE = document.createElement('option');
                optE.value = val; optE.textContent = lbl;
                endSel.appendChild(optE);
            }
        }
        if (currentStart) startSel.value = currentStart;
        if (currentEnd)   endSel.value   = currentEnd;
    }

    // --- Smart time defaults for a position (first free slot, conflict-aware) ---
    function calcDefaultTimes(posId, sh, posName) {
        var slots = getPosFreeSots(posId, sh, posName);
        var sh2   = sh * 60;
        var s     = slots.length ? slots[0].s : sh2;
        var e     = slots.length ? slots[0].e : sh2 + shiftDurMins();
        return { start: minsToTime(s), end: minsToTime(e) };
    }

    // --- Rebuild time selects when member selection changes in add mode ---
    document.addEventListener('change', function (e) {
        if (e.target.id !== 'duty-member' || !dutyData) return;
        var modal = document.getElementById('duty-modal');
        if (!modal || modal.hidden) return;
        var asgIdEl = modal.querySelector('[name="assignment_id"]');
        if (asgIdEl && asgIdEl.value) return; // edit mode — don't override
        var userId = e.target.value;
        if (!userId) return;
        var sh          = dutyData.shift_start_hour || 6;
        var increment   = parseInt(schedulesData.dutyTimeIncrement, 10) || 60;
        var memberSlots = getMemberFreeSlots(userId, sh, null);
        var free;
        if (dutyModalIsSub) {
            // Sub-assignments: use member availability only, no position filter
            free = memberSlots;
        } else {
            var posIdHid = modal.querySelector('[name="position_id"]');
            var curPosId = posIdHid ? posIdHid.value : null;
            var posSlots = curPosId ? getPosFreeSots(curPosId, sh) : [];
            free = posSlots.length ? intersectSlots(posSlots, memberSlots) : memberSlots;
        }
        if (!free.length) return;
        var startSel = modal.querySelector('#duty-start');
        var endSel   = modal.querySelector('#duty-end');
        if (startSel && endSel) {
            buildTimeSelects(startSel, endSel, free, sh, increment, minsToTime(free[0].s), minsToTime(free[0].e));
        }
    });

    // --- Open add-assignment modal (click orange bar) ---
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.duty-add-btn');
        if (!btn || !dutyData) return;

        var posId   = btn.dataset.posId;
        var posName = btn.dataset.posName;
        var posDisc = btn.dataset.posDisc || '';
        var sh      = dutyData.shift_start_hour || 6;
        var times   = calcDefaultTimes(posId, sh, posName);

        openDutyModal({ mode: 'add', posId: posId, posName: posName, posDisc: posDisc,
                        startTime: times.start, endTime: times.end });
    });

    // --- Sub-assignment "+" button ---
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.duty-sub-btn');
        if (!btn || !dutyData) return;
        e.stopPropagation();

        var posId      = btn.dataset.posId;
        var posName    = btn.dataset.posName;
        var posDisc    = btn.dataset.posDisc || '';
        var mainUserId = btn.dataset.mainUserId || '';
        var sh         = dutyData.shift_start_hour || 6;
        var times      = calcDefaultTimes(posId, sh, posName);

        openDutyModal({ mode: 'add', posId: posId, posName: posName, posDisc: posDisc,
                        startTime: times.start, endTime: times.end, isSub: true, excludeUserId: mainUserId });
    });

    // Returns free time slots [{s, e}] for a position within the current shift window.
    // Optionally pass posName to also exclude conflict-blocked ranges from other positions.
    function getPosFreeSots(posId, sh, posName) {
        var shiftStart = sh * 60;
        var shiftEnd   = sh * 60 + shiftDurMins();
        var blocked = (dutyData.assignments || [])
            .filter(function (a) { return String(a.position_id) === String(posId) && !parseInt(a.is_trainee, 10); })
            .map(function (a) { return { s: timeToAbsMins(a.start_time, sh), e: timeToAbsMins(a.end_time, sh) }; });

        // Add conflict-blocked ranges from paired positions
        if (posName) {
            var pairs = schedulesData.dutyConflictPairs || [];
            pairs.forEach(function (pair) {
                var otherName = pair[0] === posName ? pair[1] : (pair[1] === posName ? pair[0] : null);
                if (!otherName) return;
                var otherPos = (dutyData.positions || []).filter(function (p) { return p.name === otherName; })[0];
                if (!otherPos) return;
                (dutyData.assignments || []).filter(function (a) { return String(a.position_id) === String(otherPos.id); }).forEach(function (a) {
                    blocked.push({ s: timeToAbsMins(a.start_time, sh), e: timeToAbsMins(a.end_time, sh) });
                });
            });
        }

        blocked.sort(function (a, b) { return a.s - b.s; });
        var slots = [], cursor = shiftStart;
        blocked.forEach(function (a) {
            if (a.s > cursor) slots.push({ s: cursor, e: a.s });
            cursor = Math.max(cursor, a.e);
        });
        if (cursor < shiftEnd) slots.push({ s: cursor, e: shiftEnd });
        return slots;
    }

    // Returns the intersection of two slot arrays [{s, e}], sorted by start.
    function intersectSlots(slotsA, slotsB) {
        var result = [];
        slotsA.forEach(function (a) {
            slotsB.forEach(function (b) {
                var s = Math.max(a.s, b.s);
                var e = Math.min(a.e, b.e);
                if (s < e) result.push({ s: s, e: e });
            });
        });
        result.sort(function (a, b) { return a.s - b.s; });
        return result;
    }

    // Returns free time slots [{s, e}] for a user within the current shift window.
    function getMemberFreeSlots(userId, sh, excludeId) {
        var shiftStart = sh * 60;
        var shiftEnd   = sh * 60 + shiftDurMins();

        // For OT members, constrain to their claimed OT hours
        var member = (dutyData.members || []).find(function (m) { return String(m.user_id) === String(userId); });
        var availWindow;
        if (member && member.type === 'ot' && member.ot_hours && member.ot_hours.length) {
            availWindow = member.ot_hours.map(function (h) {
                var s = h.start * 60;
                var e = h.end * 60;
                if (s < shiftStart) s += 24 * 60;
                if (e <= s) e += 24 * 60;
                return { s: s, e: e };
            });
        } else if (member && member.type === 'custom' && member.custom_hours) {
            var csStart = Math.round((member.custom_hours.start || 0) * 60);
            var csEnd   = Math.round((member.custom_hours.end   || 0) * 60);
            if (csStart < shiftStart) csStart += 24 * 60;
            if (csEnd <= csStart)     csEnd   += 24 * 60;
            availWindow = [{ s: csStart, e: csEnd }];
        } else {
            availWindow = [{ s: shiftStart, e: shiftEnd }];
        }

        // Subtract existing assignments
        var myAsgns = (dutyData.assignments || [])
            .filter(function (a) {
                return String(a.user_id) === String(userId) &&
                       !(excludeId && String(a.id) === String(excludeId));
            })
            .map(function (a) { return { s: timeToAbsMins(a.start_time, sh), e: timeToAbsMins(a.end_time, sh) }; })
            .sort(function (a, b) { return a.s - b.s; });

        // For each available window, subtract assignments to get free slots
        var slots = [];
        availWindow.forEach(function (win) {
            var cursor = win.s;
            myAsgns.forEach(function (a) {
                if (a.e <= win.s || a.s >= win.e) return; // outside this window
                var aStart = Math.max(a.s, win.s);
                var aEnd   = Math.min(a.e, win.e);
                if (aStart > cursor) slots.push({ s: cursor, e: aStart });
                cursor = Math.max(cursor, aEnd);
            });
            if (cursor < win.e) slots.push({ s: cursor, e: win.e });
        });

        return slots;
    }

    function minsToTime(absMin) {
        var h = Math.floor(absMin / 60) % 24;
        var m = absMin % 60;
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    // --- Drag-and-drop: roster member → orange bar ---
    var draggedUserId = null;

    document.addEventListener('dragstart', function (e) {
        var row = e.target.closest('tr[data-user-id]');
        if (!row) return;
        draggedUserId = row.dataset.userId;
        e.dataTransfer.setData('text/plain', draggedUserId);
        e.dataTransfer.effectAllowed = 'copy';
    });

    document.addEventListener('dragend', function () {
        draggedUserId = null;
    });

    function dragMemberAllowed(userId, posDisc) {
        if (!posDisc || !userId || !dutyData) return true;
        var member = (dutyData.members || []).filter(function (m) { return String(m.user_id) === String(userId); })[0];
        return !!(member && member.disciplines && member.disciplines.indexOf(posDisc) !== -1);
    }

    document.addEventListener('dragover', function (e) {
        var bar = e.target.closest('.duty-add-btn');
        if (!bar || !dutyData) return;
        e.preventDefault();
        if (dragMemberAllowed(draggedUserId, bar.dataset.posDisc || '')) {
            e.dataTransfer.dropEffect = 'copy';
            bar.classList.add('duty-drop-hover');
        } else {
            e.dataTransfer.dropEffect = 'none';
        }
    });

    document.addEventListener('dragleave', function (e) {
        var bar = e.target.closest('.duty-add-btn');
        if (!bar) return;
        if (!bar.contains(e.relatedTarget)) {
            bar.classList.remove('duty-drop-hover');
        }
    });

    document.addEventListener('drop', function (e) {
        var bar = e.target.closest('.duty-add-btn');
        if (!bar || !dutyData) return;
        e.preventDefault();
        bar.classList.remove('duty-drop-hover');

        var userId  = e.dataTransfer.getData('text/plain');
        var posDisc = bar.dataset.posDisc || '';
        if (!userId || !dragMemberAllowed(userId, posDisc)) return;

        var posId   = bar.dataset.posId;
        var posName = bar.dataset.posName;
        var sh      = dutyData.shift_start_hour || 6;
        var times   = calcDefaultTimes(posId, sh, posName);

        openDutyModal({ mode: 'add', posId: posId, posName: posName, posDisc: posDisc,
                        userId: userId, startTime: times.start, endTime: times.end });
    });

    // --- Open edit-assignment modal (click on bar) ---
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.duty-bar') || e.target.closest('.duty-remove-btn') || e.target.closest('.duty-sub-btn')) return;
        if (!dutyData || !(schedulesData.isSupervisor === 'true' || schedulesData.isAdmin === 'true')) return;

        var bar  = e.target.closest('.duty-bar');
        var id   = bar.dataset.id;
        var asgn = (dutyData.assignments || []).filter(function (a) { return String(a.id) === String(id); })[0];
        if (!asgn) return;
        var pos  = (dutyData.positions  || []).filter(function (p) { return String(p.id) === String(asgn.position_id); })[0];

        openDutyModal({
            mode: 'edit',
            posId:        String(asgn.position_id),
            posName:      pos ? pos.name : '',
            posDisc:      pos ? (pos.discipline_slug || '') : '',
            assignmentId: String(id),
            userId:       String(asgn.user_id),
            startTime:    asgn.start_time.substring(0, 5),
            endTime:      asgn.end_time.substring(0, 5),
            isSub:        !!parseInt(asgn.is_trainee, 10),
        });
    });

    // --- Submit add-assignment form ---
    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#duty-form')) return;
        e.preventDefault();

        var form    = e.target;
        var errEl   = document.getElementById('duty-form-error');
        var submit  = document.getElementById('duty-form-submit');
        function showDutyErr(msg) {
            if (!errEl) return;
            errEl.textContent = msg;
            errEl.style.display = msg ? 'block' : '';
        }
        showDutyErr('');

        var fd           = new FormData(form);
        var assignmentId = fd.get('assignment_id') || '';
        var isEdit       = !!assignmentId;
        var payload = {
            action        : isEdit ? 'schedules_update_duty_assignment' : 'schedules_add_duty_assignment',
            assignment_id : assignmentId,
            day_id        : fd.get('day_id')      || '0',
            date          : fd.get('date')        || '',
            shift         : fd.get('shift')       || '',
            position_id   : fd.get('position_id') || '0',
            user_id       : fd.get('user_id')     || '0',
            start_time    : fd.get('start_time')  || '',
            end_time      : fd.get('end_time')    || '',
            is_sub        : dutyModalIsSub ? '1' : '0',
        };

        if (!payload.user_id || payload.user_id === '0') {
            showDutyErr('Please select a member.');
            return;
        }
        if (!payload.start_time || !payload.end_time) {
            showDutyErr('Please select start and end times.');
            return;
        }
        // Use shift-aware absolute-minute comparison so overnight shifts (e.g. 1800–0600) pass
        var _shv = (dutyData && dutyData.shift_start_hour) || 0;
        if (timeToAbsMins(payload.start_time, _shv) >= timeToAbsMins(payload.end_time, _shv)) {
            showDutyErr('End time must be after start time.');
            return;
        }

        if (dutyData && !dutyModalIsSub) {
            var sh2          = dutyData.shift_start_hour || 6;
            var startAbsMins = timeToAbsMins(payload.start_time, sh2);
            var endAbsMins   = timeToAbsMins(payload.end_time, sh2);
            var freeSlots2   = getMemberFreeSlots(payload.user_id, sh2, isEdit ? assignmentId : null);
            var fits         = freeSlots2.some(function (slot) { return slot.s <= startAbsMins && slot.e >= endAbsMins; });
            if (!fits) {
                showDutyErr('This member is already assigned during part of that time.');
                return;
            }
        }

        if (submit) { submit.disabled = true; submit.textContent = isEdit ? 'Updating\u2026' : 'Adding\u2026'; }

        schedulesAjax(payload)
            .then(function (res) {
                if (submit) { submit.disabled = false; submit.textContent = isEdit ? 'Update Assignment' : 'Add Assignment'; }
                if (res.success) {
                    var modal = document.getElementById('duty-modal');
                    if (modal) { modal.hidden = true; syncBodyLock(); }
                    if (dutyData) {
                        var a = res.data.assignment;
                        if (isEdit) {
                            dutyData.assignments = (dutyData.assignments || []).map(function (x) {
                                return String(x.id) === String(assignmentId) ? a : x;
                            });
                        } else {
                            dutyData.day_id = a.day_id;
                            if (!dutyData.assignments) dutyData.assignments = [];
                            dutyData.assignments.push(a);
                            dutyData.assignments.sort(function (x, y) {
                                if (x.position_id !== y.position_id) return x.position_id - y.position_id;
                                var xt = parseInt(x.is_trainee, 10) || 0;
                                var yt = parseInt(y.is_trainee, 10) || 0;
                                if (xt !== yt) return xt - yt;
                                return x.start_time < y.start_time ? -1 : 1;
                            });
                        }
                        syncRosterAssignments();
                        renderDutyTimeline(dutyData);
                    }
                    showToast(isEdit ? 'Assignment updated.' : 'Assignment added.', 'success');
                } else {
                    showDutyErr((res.data && res.data.message) || (isEdit ? 'Failed to update.' : 'Failed to add.'));
                }
            })
            .catch(function () {
                if (submit) { submit.disabled = false; submit.textContent = isEdit ? 'Update Assignment' : 'Add Assignment'; }
                showDutyErr('Network error.');
            });
    });

    /*--------------------------------------------------------------
    # Duty Assignments — Timeline (Member read-only)
    --------------------------------------------------------------*/

    function renderMemberDutyTimeline(data) {
        var content = document.getElementById('member-duty-content');
        if (!content) return;

        if (!data.positions || !data.positions.length) {
            content.innerHTML = '<p class="duty-empty">No duty assignments posted for this date.</p>';
            return;
        }

        var sh         = data.shift_start_hour || 6;
        var totalHours = data.shift_duration_hours || 12;

        // Half-day overlay
        var halfDayHtml = '';
        if (data.half_day_blocked_start !== null && data.half_day_blocked_start !== undefined) {
            var _bS = (data.half_day_blocked_start < 10 ? '0' : '') + data.half_day_blocked_start + ':00';
            var _bE = (data.half_day_blocked_end   < 10 ? '0' : '') + data.half_day_blocked_end   + ':00';
            var _hg = barGeometry(_bS, _bE, sh);
            halfDayHtml = '<div class="duty-halfday-overlay" style="left:' + _hg.left + '%;width:' + _hg.width + '%" title="Shift unavailable (half day)"></div>';
        }

        // Group by position, separate trainees
        var byPos        = {};
        var byPosTrainee = {};
        (data.assignments || []).forEach(function (a) {
            var pid = String(a.position_id);
            if (parseInt(a.is_trainee, 10)) {
                if (!byPosTrainee[pid]) byPosTrainee[pid] = [];
                byPosTrainee[pid].push(a);
            } else {
                if (!byPos[pid]) byPos[pid] = [];
                byPos[pid].push(a);
            }
        });
        [byPos, byPosTrainee].forEach(function (map) {
            Object.keys(map).forEach(function (pid) {
                map[pid].sort(function (a, b) { return timeToAbsMins(a.start_time, sh) - timeToAbsMins(b.start_time, sh); });
            });
        });

        var html = '<div class="duty-timeline-col"><div class="duty-timeline">';

        // Header
        html += '<div class="duty-timeline-header">';
        html += '<div class="duty-row-label"></div>';
        html += '<div class="duty-track-col"><div class="duty-track duty-track-header">';
        for (var hi = 0; hi <= totalHours; hi++) {
            var hr = (sh + hi) % 24;
            var lp = ((hi / totalHours) * 100).toFixed(3);
            var tx = -((hi / totalHours) * 100).toFixed(3);
            html += '<span class="duty-hour-tick" style="left:' + lp + '%;transform:translateX(' + tx + '%)">' +
                    (hr < 10 ? '0' : '') + hr + '00</span>';
        }
        html += halfDayHtml;
        html += '</div></div></div>';

        // Position rows
        data.positions.forEach(function (pos) {
            var posId  = String(pos.id);
            var asgns  = byPos[posId] || [];

            html += '<div class="duty-row" data-position-id="' + esc(posId) + '">';
            html += '<div class="duty-row-label"><span class="duty-pos-name">' + esc(pos.name) + '</span></div>';
            html += '<div class="duty-track-col"><div class="duty-track">';
            html += '<div class="duty-bg-bar"></div>';
            asgns.forEach(function (a) {
                var geo   = barGeometry(a.start_time, a.end_time, sh);
                var label = esc(formatName(a)) + ' \u2022 ' + fmtTime(a.start_time) + '\u2013' + fmtTime(a.end_time);
                html += '<div class="duty-bar" style="left:' + geo.left + '%;width:' + geo.width + '%"' +
                        ' title="' + esc(formatName(a) + ' ' + fmtTime(a.start_time) + '-' + fmtTime(a.end_time)) + '">';
                html += '<span class="duty-bar-label">' + label + '</span>';
                html += '</div>';
            });
            html += halfDayHtml;
            html += '</div></div></div>';

            // Trainee sub-row
            var traineeAsgns = byPosTrainee[posId] || [];
            if (traineeAsgns.length) {
                html += '<div class="duty-row duty-row-trainee" data-position-id="' + esc(posId) + '">';
                html += '<div class="duty-row-label"></div>';
                html += '<div class="duty-track-col"><div class="duty-track">';
                html += '<div class="duty-bg-bar duty-bg-bar-trainee"></div>';
                traineeAsgns.forEach(function (a) {
                    var geo   = barGeometry(a.start_time, a.end_time, sh);
                    var label = '(T) ' + esc(formatName(a)) + ' \u2022 ' + fmtTime(a.start_time) + '\u2013' + fmtTime(a.end_time);
                    html += '<div class="duty-bar duty-bar-trainee" style="left:' + geo.left + '%;width:' + geo.width + '%"' +
                            ' title="' + esc(formatName(a) + ' (Trainee) ' + fmtTime(a.start_time) + '-' + fmtTime(a.end_time)) + '">';
                    html += '<span class="duty-bar-label">' + label + '</span>';
                    html += '</div>';
                });
                html += halfDayHtml;
                html += '</div></div></div>';
            }
        });

        html += '</div></div>'; // .duty-timeline / .duty-timeline-col

        // --- Roster (read-only) ---
        var roster = (data.roster || []).slice().sort(function (a, b) {
            return (!!a.timeoff ? 1 : 0) - (!!b.timeoff ? 1 : 0);
        });
        if (roster.length) {
            html += '<div class="duty-roster-col">';
            html += '<table class="duty-roster">';
            html += '<thead><tr><th>#</th><th>Name</th><th>Title</th><th>Assignment</th></tr></thead><tbody>';
            roster.forEach(function (m, idx) {
                var isOt     = m.type === 'ot';
                var isAbsent = !!m.timeoff;
                var rowClass = isAbsent ? ' class="duty-roster-absent"' : (isOt ? ' class="duty-roster-ot"' : '');
                html += '<tr' + rowClass + '>';
                html += '<td>' + (idx + 1) + '</td>';
                html += '<td>' + esc(formatName(m)) + (isOt ? ' <span class="duty-roster-badge ot">OT</span>' : '') + '</td>';
                html += '<td>' + esc(m.title || '') + '</td>';

                // Assignment hours
                var memberWindows;
                if (isOt && m.ot_hours && m.ot_hours.length) {
                    memberWindows = m.ot_hours.map(function (h) {
                        return { startTime: (h.start < 10 ? '0' : '') + h.start + ':00', endTime: (h.end < 10 ? '0' : '') + h.end + ':00' };
                    });
                } else {
                    var _ss = (sh < 10 ? '0' : '') + sh + ':00';
                    var _se = ((sh + totalHours) % 24);
                    _se = (_se < 10 ? '0' : '') + _se + ':00';
                    memberWindows = [{ startTime: _ss, endTime: _se }];
                }
                var assignTxt = isAbsent ? (m.timeoff && m.timeoff.type ? m.timeoff.type.toUpperCase() : 'Off') :
                    memberWindows.map(function (w) { return fmtTime(w.startTime) + '\u2013' + fmtTime(w.endTime); }).join(', ');
                html += '<td>' + esc(assignTxt) + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '</div>'; // .duty-roster-col
        }

        content.innerHTML = html;
        markUniqueSpans(content);
    }

    function loadMemberDuty(date) {
        var content = document.getElementById('member-duty-content');
        if (!content) return;

        var shiftEl = document.getElementById('member-duty-shift');
        var shift   = shiftEl ? shiftEl.value : '';

        content.innerHTML = '<p class="duty-loading">Loading\u2026</p>';

        schedulesAjax({ action: 'schedules_get_member_duty', date: date || '', shift: shift })
            .then(function (res) {
                if (res.success) {
                    if (res.data && res.data.no_record) {
                        content.innerHTML = '<p class="duty-empty">No duty assignment record exists for this day.</p>';
                        return;
                    }
                    renderMemberDutyTimeline(res.data);
                } else {
                    var errMsg = (res.data && res.data.message) || 'Could not load duty assignments.';
                    content.innerHTML = '<p class="duty-empty">' + esc(errMsg) + '</p>';
                }
            })
            .catch(function () {
                content.innerHTML = '<p class="duty-empty">Network error.</p>';
            });
    }

    function refreshMemberDutyShifts(date, thenLoad) {
        var shiftEl = document.getElementById('member-duty-shift');
        if (!shiftEl) { if (thenLoad) loadMemberDuty(date); return; }

        schedulesAjax({ action: 'schedules_get_shifts_for_date', date: date })
            .then(function (res) {
                var shifts   = (res.success && res.data.shifts) ? res.data.shifts : [];
                var prevVal  = shiftEl.value;
                shiftEl.innerHTML = '';
                if (shifts.length) {
                    shifts.forEach(function (s) {
                        var opt = document.createElement('option');
                        opt.value = s;
                        opt.textContent = 'Shift ' + s;
                        if (s === prevVal) opt.selected = true;
                        shiftEl.appendChild(opt);
                    });
                    // If previous selection no longer valid, first option wins (browser default)
                } else {
                    var opt = document.createElement('option');
                    opt.value = '';
                    opt.textContent = 'No shifts';
                    shiftEl.appendChild(opt);
                }
                if (thenLoad) loadMemberDuty(date);
            })
            .catch(function () { if (thenLoad) loadMemberDuty(date); });
    }

    document.addEventListener('change', function (e) {
        var dateEl = document.getElementById('member-duty-date');
        var date   = dateEl ? dateEl.value : new Date().toISOString().slice(0, 10);
        if (e.target.matches('#member-duty-date')) {
            refreshMemberDutyShifts(date, true);
        } else if (e.target.matches('#member-duty-shift')) {
            loadMemberDuty(date);
        }
    });

    /*--------------------------------------------------------------
    # Personal Schedule Calendar
    --------------------------------------------------------------*/

    var schedulePicker = document.getElementById('schedule-month-picker');
    var scheduleYear   = schedulePicker && schedulePicker.value ? parseInt(schedulePicker.value.split('-')[0], 10) : new Date().getFullYear();
    var scheduleMonth  = schedulePicker && schedulePicker.value ? parseInt(schedulePicker.value.split('-')[1], 10) : new Date().getMonth() + 1;

    function loadScheduleCalendar(userId, year, month) {
        var wrap = document.getElementById('schedule-calendar-wrap');
        var label = document.getElementById('schedule-month-label');
        if (!wrap) return;
        if (!userId) { wrap.innerHTML = ''; if (label) label.textContent = ''; return; }

        wrap.innerHTML = '<p class="loading-msg">Loading\u2026</p>';

        schedulesAjax({
            action  : 'schedules_get_personal_calendar',
            user_id : userId,
            year    : year,
            month   : month,
        }).then(function (res) {
            if (!res.success) {
                wrap.innerHTML = '<p class="form-error">' + esc((res.data && res.data.message) || 'Error') + '</p>';
                return;
            }
            var data = res.data;
            scheduleYear  = data.year;
            scheduleMonth = data.month;
            if (label) label.textContent = data.month_label;

            var html = '<table class="schedule-cal">';
            html += '<thead><tr>';
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function (d) {
                html += '<th>' + d + '</th>';
            });
            html += '</tr></thead><tbody>';

            var days = data.days;
            var todayStr = (function () { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }());
            for (var i = 0; i < days.length; i += 7) {
                html += '<tr>';
                for (var j = i; j < i + 7 && j < days.length; j++) {
                    var day = days[j];
                    var cls = 'schedule-day-cell';
                    if (!day.in_month) cls += ' outside-month';
                    if (day.is_today) cls += ' is-today';

                    var isFuture = day.shift && day.in_month && day.date >= todayStr;
                    if (isFuture) cls += ' schedule-day-clickable';

                    html += '<td class="' + cls + '"' + (isFuture ?
                        ' data-date="' + esc(day.date) + '"' +
                        ' data-shift="' + esc(day.shift.letter) + '"' +
                        ' data-shift-start="' + day.shift.start + '"' +
                        ' data-shift-end="' + day.shift.end + '"' : '') + '>';
                    html += '<div class="schedule-cell-inner">';
                    html += '<div class="schedule-day-num">' + day.day + '</div>';

                    // Check if an approved time off covers this day
                    var hasApprovedOff = day.timeoff && day.timeoff.some(function (to) {
                        return to.status === 'approved';
                    });

                    // Shift (hide if approved time off replaces it)
                    if (day.shift && !hasApprovedOff) {
                        var sh = day.shift.start;
                        var eh = day.shift.end;
                        html += '<div class="schedule-entry schedule-shift">';
                        html += '<span class="schedule-shift-badge shift-' + esc(day.shift.letter.toLowerCase()) + '">' + esc(day.shift.letter) + '</span> ';
                        html += scheduleTimeFmt(sh) + '\u2013' + scheduleTimeFmt(eh);
                        html += '</div>';
                    }

                    // OT
                    if (day.ot && day.ot.length) {
                        day.ot.forEach(function (o) {
                            html += '<div class="schedule-entry schedule-ot">';
                            html += '<span class="schedule-ot-badge">OT</span> ';
                            html += scheduleTimeFmt(o.start) + '\u2013' + scheduleTimeFmt(o.end);
                            html += '</div>';
                        });
                    }

                    // Time off
                    if (day.timeoff && day.timeoff.length) {
                        day.timeoff.forEach(function (to) {
                            var toCls = 'schedule-entry schedule-timeoff schedule-timeoff-' + esc(to.type);
                            if (to.status === 'pending')   toCls += ' schedule-timeoff-pending';
                            if (to.status === 'coverage')  toCls += ' schedule-timeoff-coverage';
                            html += '<div class="' + toCls + '">';
                            if (to.status === 'pending') {
                                html += esc(to.type.toUpperCase()) + ' <small>(requested)</small>';
                            } else if (to.status === 'coverage') {
                                html += esc(to.type.toUpperCase()) + ' <small>(pending coverage)</small>';
                            } else {
                                html += esc(to.type.toUpperCase());
                            }
                            html += '</div>';
                        });
                    }

                    // Coverage outgoing (sent by this user)
                    if (day.coverage_out && day.coverage_out.length) {
                        day.coverage_out.forEach(function (co) {
                            var statusLine = co.status === 'approved' ? '(approved)' :
                                             co.status === 'pending_supervisor' ? '(pending approval)' :
                                             '(requested)';
                            html += '<div class="schedule-entry schedule-cover-out">COVERAGE<br><small>' + statusLine + '</small></div>';
                        });
                    }

                    // Coverage incoming — requests asking this user to cover someone else
                    if (day.coverage_in && day.coverage_in.length) {
                        day.coverage_in.forEach(function (ci) {
                            var timeStr = scheduleTimeFmt(ci.start) + '\u2013' + scheduleTimeFmt(ci.end);
                            html += '<div class="schedule-entry schedule-cover-in">';
                            if (ci.status === 'pending_supervisor') {
                                html += timeStr + '<br><small>(pending approval)</small>';
                            } else {
                                html += 'COVER REQUEST<br><small>' + timeStr + '</small>';
                                html += '<div class="cover-inline-actions">';
                                html += '<button type="button" class="schedules-btn schedules-btn-primary cover-inline-accept" data-trade-id="' + esc(ci.id) + '">Accept</button>';
                                html += '<button type="button" class="schedules-btn cover-inline-decline" data-trade-id="' + esc(ci.id) + '">Decline</button>';
                                html += '</div>';
                            }
                            html += '</div>';
                        });
                    }

                    html += '</div>';
                    html += '</td>';
                }
                html += '</tr>';
            }

            html += '</tbody></table>';
            wrap.innerHTML = html;
            markUniqueSpans(wrap);
        }).catch(function () {
            wrap.innerHTML = '<p class="form-error">Network error.</p>';
        });
    }

    function scheduleTimeFmt(h) {
        var hour = Math.floor(h);
        var min  = Math.round((h - hour) * 60);
        return (hour < 10 ? '0' : '') + hour + (min < 10 ? '0' : '') + min;
    }

    /*--------------------------------------------------------------
    # PDO Calendar
    --------------------------------------------------------------*/

    var pdoPicker     = document.getElementById('pdo-month-picker');
    var pdoShiftPicker = document.getElementById('pdo-shift-picker');
    var pdoYear       = pdoPicker && pdoPicker.value ? parseInt(pdoPicker.value.split('-')[0], 10) : new Date().getFullYear();
    var pdoMonth      = pdoPicker && pdoPicker.value ? parseInt(pdoPicker.value.split('-')[1], 10) : new Date().getMonth() + 1;

    function loadPdoCalendar(shift, year, month) {
        var wrap = document.getElementById('pdo-calendar-wrap');
        if (!wrap) return;
        if (!shift) { wrap.innerHTML = ''; return; }

        wrap.innerHTML = '<p class="loading-msg">Loading\u2026</p>';

        schedulesAjax({
            action: 'schedules_get_pdo_calendar',
            shift:  shift,
            year:   year,
            month:  month,
        }).then(function (res) {
            if (!res.success) {
                wrap.innerHTML = '<p class="form-error">' + esc((res.data && res.data.message) || 'Error') + '</p>';
                return;
            }
            var data = res.data;
            pdoYear  = data.year;
            pdoMonth = data.month;

            // Set the active user so the timeoff popup knows who is requesting
            scheduleActiveUserId = scheduleProxyUserId || String(schedulesData.userId);

            var shiftStart = data.shift_start;
            var shiftEnd   = data.shift_end;

            var html = '<table class="schedule-cal pdo-cal">';
            html += '<thead><tr>';
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(function (d) {
                html += '<th>' + d + '</th>';
            });
            html += '</tr></thead><tbody>';

            var days = data.days;
            var todayStr = (function () { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }());
            for (var i = 0; i < days.length; i += 7) {
                html += '<tr>';
                for (var j = i; j < i + 7 && j < days.length; j++) {
                    var day = days[j];
                    var isClickable = day.is_work_day && day.in_month && day.date >= todayStr;
                    var cls = 'schedule-day-cell';
                    if (!day.in_month)   cls += ' outside-month';
                    if (day.is_today)    cls += ' is-today';
                    if (day.is_work_day) cls += ' pdo-work-day';
                    if (isClickable)     cls += ' schedule-day-clickable';

                    html += '<td class="' + cls + '"' + (isClickable ?
                        ' data-date="' + esc(day.date) + '"' +
                        ' data-shift-start="' + shiftStart + '"' +
                        ' data-shift-end="' + shiftEnd + '"' : '') + '>';
                    html += '<div class="schedule-cell-inner">';
                    html += '<div class="schedule-day-num">' + day.day + '</div>';

                    if (day.pdo && day.pdo.length) {
                        day.pdo.forEach(function (p) {
                            var pCls = 'schedule-entry schedule-timeoff schedule-timeoff-' + esc(p.type || 'pdo');
                            if (p.status === 'pending')  pCls += ' schedule-timeoff-pending';
                            if (p.status === 'coverage') pCls += ' schedule-timeoff-coverage';
                            html += '<div class="' + pCls + '">';
                            html += '<span class="pdo-name">' + esc(p.name) + '</span>';
                            if (p.status === 'pending') {
                                html += '<small class="pdo-status">(requested)</small>';
                            } else if (p.status === 'coverage') {
                                html += '<small class="pdo-status">(pending coverage)</small>';
                            }
                            html += '</div>';
                        });
                    }

                    html += '</div></td>';
                }
                html += '</tr>';
            }

            html += '</tbody></table>';
            wrap.innerHTML = html;
            markUniqueSpans(wrap);
        }).catch(function () {
            wrap.innerHTML = '<p class="form-error">Network error.</p>';
        });
    }

    if (pdoPicker) {
        pdoPicker.addEventListener('change', function () {
            var parts = this.value.split('-');
            pdoYear  = parseInt(parts[0], 10);
            pdoMonth = parseInt(parts[1], 10);
            var shift = pdoShiftPicker ? pdoShiftPicker.value : '';
            loadPdoCalendar(shift, pdoYear, pdoMonth);
        });
    }

    // Pre-select the user's own shift
    if (pdoShiftPicker && schedulesData.userShift) {
        pdoShiftPicker.value = schedulesData.userShift;
    }

    if (pdoShiftPicker) {
        pdoShiftPicker.addEventListener('change', function () {
            loadPdoCalendar(this.value, pdoYear, pdoMonth);
        });
    }

    /*--------------------------------------------------------------
    # Schedule Calendar — Time Off Request
    --------------------------------------------------------------*/

    // Track active schedule user ID
    var scheduleActiveUserId = '';

    // Helper: set the popup mode (pdo vs cover)
    function setTimeoffMode(mode) {
        var pdoSection  = document.getElementById('timeoff-pdo-section');
        var covSection  = document.getElementById('timeoff-cover-section');
        var titleEl     = document.getElementById('timeoff-popup-title');
        var submitBtn   = document.getElementById('timeoff-submit-btn');
        var radios      = document.querySelectorAll('input[name="timeoff_type_radio"]');

        if (mode === 'cover') {
            if (pdoSection)  pdoSection.hidden = true;
            if (covSection)  covSection.hidden = false;
            if (titleEl)     titleEl.textContent = 'Request Coverage';
            if (submitBtn)   submitBtn.textContent = 'Send Request';
        } else {
            if (pdoSection)  pdoSection.hidden = false;
            if (covSection)  covSection.hidden = true;
            if (titleEl)     titleEl.textContent = 'Request Time Off';
            if (submitBtn)   submitBtn.textContent = 'Submit Request';
        }
        radios.forEach(function (r) { if (r.value === mode) r.checked = true; });
    }

    // Sync mode when radio changes
    document.addEventListener('change', function (e) {
        var radio = e.target.closest('input[name="timeoff_type_radio"]');
        if (!radio) return;
        setTimeoffMode(radio.value);
    });

    // Open popup when clicking a shift day
    document.addEventListener('click', function (e) {
        var cell = e.target.closest('.schedule-day-clickable');
        if (!cell) return;

        var date = cell.dataset.date;
        if (!date || !scheduleActiveUserId) return;

        var popup          = document.getElementById('timeoff-popup');
        var userInput      = document.getElementById('timeoff-user-id');
        var startDateInput = document.getElementById('timeoff-start-date');
        var endDateInput   = document.getElementById('timeoff-end-date');

        if (!popup || !userInput) return;

        // Pre-populate both date fields with the clicked date
        if (startDateInput) startDateInput.value = date;
        if (endDateInput)   endDateInput.value   = date;
        userInput.value = scheduleActiveUserId;
        document.getElementById('timeoff-notes').value = '';
        clearMsg(document.getElementById('timeoff-error'));

        // Show PDO/Coverage radios on future dates with a shift assigned
        // Members: own schedule only. Supervisors: own schedule or viewing any member.
        var isOwnSchedule  = scheduleActiveUserId === String(schedulesData.userId);
        var isSup          = schedulesData.isSupervisor === 'true';
        var isFuture       = date > new Date().toISOString().slice(0, 10);
        var typeRow        = document.getElementById('timeoff-type-row');
        var hasCoverRadio  = isFuture && cell.dataset.shift && (isOwnSchedule || isSup);
        if (typeRow) typeRow.hidden = !hasCoverRadio;

        // Always reset to PDO mode when opening
        setTimeoffMode('pdo');

        // If coverage radio is available, populate the member dropdown
        if (hasCoverRadio) {
            var shiftLetter = cell.dataset.shift;
            var tradeDate   = date;
            var selEl       = document.getElementById('cover-recipient-select');
            if (selEl) {
                selEl.innerHTML = '<option value="">\u2014 Loading members\u2026 \u2014</option>';
                // Fetch members NOT already working this shift on this date
                schedulesAjax({
                    action:      'schedules_get_cover_members',
                    trade_date:  tradeDate,
                    shift_letter: shiftLetter,
                }).then(function (res) {
                    selEl.innerHTML = '<option value="">\u2014 Select a member \u2014</option>';
                    if (res.success && res.data.members) {
                        var cms = res.data.members.map(function (m) {
                            return { id: m.id, name: m.name + (m.shift ? ' (Shift ' + m.shift + ')' : ''), role: m.role || 'member' };
                        });
                        selEl.innerHTML = buildGroupedOptions(cms, '\u2014 Select a member \u2014');
                    }
                }).catch(function () {
                    selEl.innerHTML = '<option value="">Error loading members</option>';
                });
                selEl.value = '';
            }
            popup.dataset.tradeShift = shiftLetter;
        }

        // Populate start/end time selects
        var shiftStart = parseFloat(cell.dataset.shiftStart) || 6;
        var shiftEnd   = parseFloat(cell.dataset.shiftEnd)   || 18;
        var startSel   = document.getElementById('timeoff-start');
        var endSel     = document.getElementById('timeoff-end');
        if (startSel && endSel) {
            var duration = shiftEnd > shiftStart ? (shiftEnd - shiftStart) : (24 - shiftStart + shiftEnd);
            startSel.innerHTML = '';
            endSel.innerHTML   = '';
            for (var i = 0; i < duration; i++) {
                var h = (shiftStart + i) % 24;
                var v = (h < 10 ? '0' : '') + h + ':00';
                var o = document.createElement('option');
                o.value = v; o.textContent = (h < 10 ? '0' : '') + h + '00';
                startSel.appendChild(o);
            }
            for (var j = 1; j <= duration; j++) {
                var h2 = (shiftStart + j) % 24;
                var v2 = (h2 < 10 ? '0' : '') + h2 + ':00';
                var o2 = document.createElement('option');
                o2.value = v2; o2.textContent = (h2 < 10 ? '0' : '') + h2 + '00';
                endSel.appendChild(o2);
            }
            startSel.selectedIndex = 0;
            endSel.selectedIndex   = endSel.options.length - 1;
        }

        popup.hidden = false;
    });

    // Close popup
    document.addEventListener('click', function (e) {
        if (e.target.closest('#timeoff-cancel-btn') || e.target.closest('.timeoff-popup-close')) {
            var popup = document.getElementById('timeoff-popup');
            if (popup) popup.hidden = true;
        }
    });

    // Submit time off / coverage request
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#timeoff-submit-btn');
        if (!btn) return;

        var errorEl   = document.getElementById('timeoff-error');
        clearMsg(errorEl);

        var startDate = document.getElementById('timeoff-start-date') ? document.getElementById('timeoff-start-date').value : '';
        var endDate   = document.getElementById('timeoff-end-date')   ? document.getElementById('timeoff-end-date').value   : startDate;
        var userId    = document.getElementById('timeoff-user-id').value;
        var notes     = document.getElementById('timeoff-notes').value;
        var typeRadio = document.querySelector('input[name="timeoff_type_radio"]:checked');
        var mode      = typeRadio ? typeRadio.value : 'pdo';

        if (!startDate || !userId) {
            showMsg(errorEl, 'Missing required fields.');
            return;
        }

        // ---- Coverage request ----
        if (mode === 'cover') {
            var recipientId = document.getElementById('cover-recipient-select') ? document.getElementById('cover-recipient-select').value : '';

            if (!recipientId) {
                showMsg(errorEl, 'Please select a member to cover your shift.');
                return;
            }

            btn.disabled    = true;
            btn.textContent = 'Sending\u2026';

            schedulesAjax({
                action:       'schedules_send_cover_request',
                recipient_id: recipientId,
                trade_date:   startDate,
                end_date:     endDate,
                note:         notes,
                user_id:      userId,
            }).then(function (res) {
                btn.disabled    = false;
                btn.textContent = 'Send Request';
                if (res.success) {
                    var created = res.data.created || 1;
                    var skipped = res.data.skipped || 0;
                    var msg = 'Coverage request sent for ' + created + ' day' + (created !== 1 ? 's' : '');
                    if (skipped) msg += ' (' + skipped + ' skipped)';
                    showToast(msg, 'success');
                    document.getElementById('timeoff-popup').hidden = true;
                    loadScheduleCalendar(userId, scheduleYear, scheduleMonth);
                } else {
                    showMsg(errorEl, (res.data && res.data.message) || 'Request failed.');
                }
            }).catch(function () {
                btn.disabled    = false;
                btn.textContent = 'Send Request';
                showMsg(errorEl, 'Network error. Please try again.');
            });
            return;
        }

        // ---- PDO request ----
        var startTime = document.getElementById('timeoff-start') ? document.getElementById('timeoff-start').value : '';
        var endTime   = document.getElementById('timeoff-end')   ? document.getElementById('timeoff-end').value   : '';

        btn.disabled    = true;
        btn.textContent = 'Submitting\u2026';

        schedulesAjax({
            action:      'schedules_submit_timeoff',
            user_id:     userId,
            start_date:  startDate,
            end_date:    endDate,
            type:        'pdo',
            start_time:  startTime,
            end_time:    endTime,
            notes:       notes,
        }).then(function (res) {
            btn.disabled    = false;
            btn.textContent = 'Submit Request';
            if (res.success) {
                var created = res.data.created || 1;
                var skipped = res.data.skipped || 0;
                var msg = created + ' day' + (created !== 1 ? 's' : '') + ' submitted';
                if (skipped) msg += ', ' + skipped + ' skipped (already requested)';
                showToast(msg, 'success');
                document.getElementById('timeoff-popup').hidden = true;
                loadScheduleCalendar(userId, scheduleYear, scheduleMonth);
            } else {
                showMsg(errorEl, (res.data && res.data.message) || 'Request failed.');
            }
        }).catch(function () {
            btn.disabled    = false;
            btn.textContent = 'Submit Request';
            showMsg(errorEl, 'Network error. Please try again.');
        });
    });

    // Month picker — shared handler for supervisors and members
    // Uses scheduleActiveUserId (set by autocomplete for supervisors, by init for members)
    if (schedulePicker) {
        schedulePicker.addEventListener('change', function () {
            var parts = this.value.split('-');
            scheduleYear  = parseInt(parts[0], 10);
            scheduleMonth = parseInt(parts[1], 10);
            try { sessionStorage.setItem('schedules_schedule_date', this.value); } catch(ex) {}
            if (scheduleActiveUserId) loadScheduleCalendar(scheduleActiveUserId, scheduleYear, scheduleMonth);
        });
    }

    // Schedule calendar init for supervisor page (no member dropdown — uses proxy or self)
    (function () {
        if (!document.getElementById('sup-view-schedule')) return; // supervisor page only
        var savedSchDate = '';
        try { savedSchDate = sessionStorage.getItem('schedules_schedule_date') || ''; } catch(ex) {}
        var uid = scheduleProxyUserId || String(schedulesData.userId);
        scheduleActiveUserId = uid;
        if (savedSchDate && schedulePicker) {
            schedulePicker.value = savedSchDate.slice(0, 7);
            var sp = savedSchDate.split('-');
            scheduleYear  = parseInt(sp[0], 10);
            scheduleMonth = parseInt(sp[1], 10);
        }
        loadScheduleCalendar(uid, scheduleYear, scheduleMonth);
    }());

    /*--------------------------------------------------------------
    # Supervisor Proxy Mode
    --------------------------------------------------------------*/

    (function () {
        var proxySel        = document.getElementById('sup-proxy-select');
        var proxyBanner     = document.getElementById('sup-proxy-banner');
        var proxyNameEl     = document.getElementById('sup-proxy-name');
        var proxyClear      = document.getElementById('sup-proxy-clear');
        var markAbsentBtn   = document.querySelector('.sup-sub-tab[data-view="sicktime"]');
        if (!proxySel) return;

        function applyProxy(uid, name) {
            scheduleProxyUserId = uid;

            // Banner
            if (proxyBanner) proxyBanner.hidden = !uid;
            if (proxyNameEl) proxyNameEl.textContent = name || '';

            // Mark Absent only visible when acting as someone
            if (markAbsentBtn) markAbsentBtn.hidden = !uid;

            // Effective user for all member-facing actions
            var effectiveId = uid || String(schedulesData.userId);
            scheduleActiveUserId = effectiveId;


            // Refresh all member-facing views immediately
            loadScheduleCalendar(effectiveId, scheduleYear, scheduleMonth);
            refreshOtBoardForUser(effectiveId);
            var pdoShiftPick = document.getElementById('pdo-shift-picker');
            if (pdoShiftPick && pdoShiftPick.value) loadPdoCalendar(pdoShiftPick.value, pdoYear, pdoMonth);
            loadSickHistory(effectiveId);
        }

        scheduleResetProxy = function () {
            scheduleProxyUserId  = '';
            scheduleActiveUserId = String(schedulesData.userId);
            proxySel.value = '';
            if (proxyBanner) proxyBanner.hidden = true;
            if (proxyNameEl) proxyNameEl.textContent = '';
            if (markAbsentBtn) markAbsentBtn.hidden = true;
        };

        proxySel.addEventListener('change', function () {
            var uid  = this.value;
            var name = uid ? this.options[this.selectedIndex].textContent : '';
            applyProxy(uid, name);
        });

        if (proxyClear) {
            proxyClear.addEventListener('click', function () {
                proxySel.value = '';
                applyProxy('', '');
            });
        }
    }());

    /*--------------------------------------------------------------
    # Init
    --------------------------------------------------------------*/

    function markUniqueSpans(root) {
        (root || document.getElementById('wrapper-content') || document).querySelectorAll('span').forEach(function (el) {
            el.classList.add('unique');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        markUniqueSpans();

        // Show week on page load — restore saved week on OT page
        var otApp = document.getElementById('schedules-ot-app');
        if (otApp) {
            var savedOtWeek = '';
            try { savedOtWeek = sessionStorage.getItem('schedules_ot_week') || ''; } catch(ex) {}
            var otWeekTarget = (savedOtWeek && otApp.querySelector('.week-tab[data-week="' + savedOtWeek + '"]'))
                || otApp.querySelector('.week-tab[data-week="1"]');
            if (otWeekTarget) otWeekTarget.click();
        } else {
            var firstTab = document.querySelector('.week-tab[data-week="1"]');
            if (firstTab) firstTab.click();
        }

        // Restore last active supervisor tab
        var savedTab = '';
        try { savedTab = sessionStorage.getItem('schedules_sup_tab') || ''; } catch(ex) {}
        if (savedTab) {
            var _subTab    = document.querySelector('.sup-sub-tab[data-view="' + savedTab + '"]');
            var _directTab = document.querySelector('.sup-tab:not(.sup-group-tab)[data-view="' + savedTab + '"]');
            if (_subTab) {
                document.querySelectorAll('.schedules-supervisor-nav-sub').forEach(function (s) { s.hidden = true; });
                document.querySelectorAll('.sup-group-tab').forEach(function (t) { t.classList.remove('active'); t.setAttribute('aria-expanded', 'false'); });
                var _subNav = _subTab.closest('.schedules-supervisor-nav-sub');
                if (_subNav) {
                    _subNav.hidden = false;
                    var _groupId  = _subNav.id.replace('sup-sub-', '');
                    var _groupTab = document.querySelector('.sup-group-tab[data-group="' + _groupId + '"]');
                    if (_groupTab) { _groupTab.classList.add('active'); _groupTab.setAttribute('aria-expanded', 'true'); }
                }
                document.querySelectorAll('.sup-sub-tab').forEach(function (t) { t.classList.remove('active'); });
                _subTab.classList.add('active');
                activateSupTab(savedTab);
            } else if (_directTab) {
                document.querySelectorAll('.schedules-supervisor-nav-sub').forEach(function (s) { s.hidden = true; });
                document.querySelectorAll('.sup-group-tab').forEach(function (t) { t.classList.remove('active'); });
                _directTab.classList.add('active');
                _directTab.setAttribute('aria-selected', 'true');
                activateSupTab(savedTab);
            }
        }

        // Restore duty grid state, or default to today + own shift
        var savedDutyDate  = '';
        var savedDutyShift = '';
        try { savedDutyDate  = sessionStorage.getItem('schedules_duty_date')  || ''; } catch(ex) {}
        try { savedDutyShift = sessionStorage.getItem('schedules_duty_shift') || ''; } catch(ex) {}
        if (document.getElementById('duty-date')) {
            var dutyDateEl  = document.getElementById('duty-date');
            var dutyShiftEl = document.getElementById('duty-shift');
            var dutyDate  = savedDutyDate  || new Date().toISOString().slice(0, 10);
            var dutyShift = savedDutyShift || schedulesData.userShift || (dutyShiftEl ? dutyShiftEl.value : 'A');
            dutyDateEl.value = dutyDate;
            filterDutyShifts(dutyDate);
            if (dutyShiftEl) dutyShiftEl.value = dutyShift;
            loadDutyGrid(dutyDate, dutyShift);
        }

        // Auto-load member duty chart — refresh shift dropdown first, then load
        if (document.getElementById('member-duty-content')) {
            var _initDutyDate = (function () {
                var el = document.getElementById('member-duty-date');
                return el && el.value ? el.value : new Date().toISOString().slice(0, 10);
            }());
            refreshMemberDutyShifts(_initDutyDate, true);
        }

        // Auto-load personal calendar on member page (no member search dropdown there)
        if (!document.getElementById('schedule-member-select') && document.getElementById('schedule-calendar-wrap')) {
            scheduleActiveUserId = String(schedulesData.userId);
            var _savedMemberSchDate = '';
            try { _savedMemberSchDate = sessionStorage.getItem('schedules_schedule_date') || ''; } catch(ex) {}
            if (_savedMemberSchDate && schedulePicker) {
                schedulePicker.value = _savedMemberSchDate.slice(0, 7);
                var _msp = _savedMemberSchDate.split('-');
                scheduleYear  = parseInt(_msp[0], 10);
                scheduleMonth = parseInt(_msp[1], 10);
            }
            loadScheduleCalendar(scheduleActiveUserId, scheduleYear, scheduleMonth);
        }

        // Hide error/success messages on page load
        document.querySelectorAll('.form-error, .form-success').forEach(function (el) {
            el.style.display = 'none';
        });

        // Init persisted undo buttons — set remaining timeouts
        document.querySelectorAll('.claim-undo-btn[data-remaining]').forEach(function (btn) {
            var rem = parseInt(btn.dataset.remaining, 10) || 0;
            setTimeout(function () {
                if (btn.parentNode) {
                    btn.parentNode.classList.remove('claim-undoable');
                    btn.remove();
                }
            }, rem * 1000);
        });

        // Duty conflict overlays — only active if pairs are configured via schedules_duty_conflict_pairs filter
        var conflictPairs = (schedulesData.dutyConflictPairs || []);
        if (conflictPairs.length && document.getElementById('duty-grid')) {
            var dutyGrid = document.getElementById('duty-grid');

            function applyDutyConflicts() {
                var posMap = {};
                dutyGrid.querySelectorAll('.duty-row[data-position-id]').forEach(function (row) {
                    var nameEl = row.querySelector('.duty-pos-name');
                    if (nameEl) posMap[nameEl.textContent.trim()] = row;
                });

                dutyGrid.querySelectorAll('.duty-conflict-overlay').forEach(function (el) { el.remove(); });

                var blockRanges = {};
                conflictPairs.forEach(function (pair) {
                    [[pair[0], pair[1]], [pair[1], pair[0]]].forEach(function (p) {
                        var sourceRow  = posMap[p[0]];
                        var targetName = p[1];
                        if (!sourceRow || !posMap[targetName]) return;
                        if (!blockRanges[targetName]) blockRanges[targetName] = [];
                        sourceRow.querySelectorAll('.duty-bar').forEach(function (bar) {
                            var left = parseFloat(bar.style.left);
                            blockRanges[targetName].push([left, left + parseFloat(bar.style.width)]);
                        });
                    });
                });

                Object.keys(blockRanges).forEach(function (posName) {
                    var targetRow = posMap[posName];
                    if (!targetRow) return;
                    var track = targetRow.querySelector('.duty-track');
                    if (!track) return;
                    var merged = [];
                    blockRanges[posName].sort(function (a, b) { return a[0] - b[0]; }).forEach(function (r) {
                        if (!merged.length || r[0] > merged[merged.length - 1][1]) {
                            merged.push([r[0], r[1]]);
                        } else {
                            merged[merged.length - 1][1] = Math.max(merged[merged.length - 1][1], r[1]);
                        }
                    });
                    merged.forEach(function (r) {
                        var overlay = document.createElement('div');
                        overlay.className = 'duty-conflict-overlay';
                        overlay.style.left  = r[0].toFixed(3) + '%';
                        overlay.style.width = (r[1] - r[0]).toFixed(3) + '%';
                        track.appendChild(overlay);
                    });
                });
            }

            var conflictObserver = new MutationObserver(function () {
                conflictObserver.disconnect();
                applyDutyConflicts();
                conflictObserver.observe(dutyGrid, { childList: true, subtree: true });
            });
            conflictObserver.observe(dutyGrid, { childList: true, subtree: true });
        }
    });

    /*--------------------------------------------------------------
    # Cover Requests
    --------------------------------------------------------------*/

    function coverDateFmt(dateStr) {
        var parts = dateStr.split('-');
        var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        var days   = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return days[d.getDay()] + ' ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }


    var coverStatusLabels = {
        'pending':            'Awaiting Response',
        'pending_supervisor': 'Pending Approval',
        'declined':           'Declined',
        'approved':           'Approved',
        'rejected':           'Not Approved',
        'cancelled':          'Cancelled',
    };

    // Member: load incoming + outgoing cover requests
    function loadCoverRequests() {
        var inEl  = document.getElementById('cover-incoming-list');
        var outEl = document.getElementById('cover-outgoing-list');
        if (!inEl && !outEl) return;

        var loading = '<p class="loading-msg">Loading\u2026</p>';
        if (inEl)  inEl.innerHTML  = loading;
        if (outEl) outEl.innerHTML = loading;

        schedulesAjax({ action: 'schedules_get_cover_requests' }).then(function (res) {
            var errHtml = '<p class="form-error">Error loading requests.</p>';
            if (!res.success) {
                if (inEl)  inEl.innerHTML  = errHtml;
                if (outEl) outEl.innerHTML = errHtml;
                return;
            }
            var requests = res.data.requests || [];
            var uid      = String(schedulesData.userId);
            var incoming = requests.filter(function (r) { return String(r.recipient_id) === uid; });
            var outgoing = requests.filter(function (r) { return String(r.requester_id) === uid; });

            if (inEl)  renderCoverList(inEl,  incoming, 'incoming');
            if (outEl) renderCoverList(outEl, outgoing, 'outgoing');
        }).catch(function () {
            var errHtml = '<p class="form-error">Network error.</p>';
            if (inEl)  inEl.innerHTML  = errHtml;
            if (outEl) outEl.innerHTML = errHtml;
        });
    }

    function renderCoverList(el, requests, direction) {
        if (!requests.length) {
            el.innerHTML = '<p class="no-data">No requests.</p>';
            return;
        }
        var html = '<div class="cover-request-list">';
        requests.forEach(function (r) {
            var dateStr     = r.trade_date ? coverDateFmt(r.trade_date) : '\u2014';
            var statusLabel = coverStatusLabels[r.status] || r.status;
            var statusCls   = 'cover-status-badge cover-status-' + r.status.replace(/_/g, '-');
            var otherName   = direction === 'incoming'
                ? esc((r.req_first + ' ' + r.req_last).trim())
                : esc((r.rec_first + ' ' + r.rec_last).trim());
            var nameLabel   = direction === 'incoming' ? 'From: ' : 'To: ';

            html += '<div class="cover-request-card" data-trade-id="' + esc(r.id) + '">';
            html += '<div class="cover-card-main">';
            html += '<span class="cover-shift-badge shift-' + esc((r.shift_letter || '').toLowerCase()) + '">' + esc(r.shift_letter || '') + '</span>';
            html += '<span class="cover-card-date">' + esc(dateStr) + '</span>';
            html += '<span class="cover-card-name">' + nameLabel + otherName + '</span>';
            html += '</div>';
            if (r.requester_note) {
                html += '<div class="cover-card-note">' + esc(r.requester_note) + '</div>';
            }
            html += '<div class="cover-card-footer">';
            html += '<span class="' + esc(statusCls) + '">' + esc(statusLabel) + '</span>';
            html += '<div class="cover-card-actions">';
            if (direction === 'incoming' && r.status === 'pending') {
                html += '<button type="button" class="action-btn cover-accept-btn" data-trade-id="' + esc(r.id) + '">Accept</button>';
                html += '<button type="button" class="basic-btn cover-decline-btn" data-trade-id="' + esc(r.id) + '">Decline</button>';
            }
            if (direction === 'outgoing' && (r.status === 'pending' || r.status === 'pending_supervisor')) {
                html += '<button type="button" class="basic-btn cover-cancel-btn" data-trade-id="' + esc(r.id) + '">Cancel</button>';
            }
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        html += '</div>';
        el.innerHTML = html;
    }

    // Accept or decline an incoming request
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cover-accept-btn, .cover-decline-btn');
        if (!btn) return;

        var tradeId  = btn.dataset.tradeId;
        var response = btn.classList.contains('cover-accept-btn') ? 'accept' : 'decline';
        btn.disabled = true;

        schedulesAjax({ action: 'schedules_respond_cover_request', trade_id: tradeId, response: response })
            .then(function (res) {
                if (res.success) {
                    loadCoverRequests();
                } else {
                    btn.disabled = false;
                    alert((res.data && res.data.message) || 'Error.');
                }
            }).catch(function () {
                btn.disabled = false;
                alert('Network error.');
            });
    });

    // Cancel an outgoing request
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cover-cancel-btn');
        if (!btn) return;

        if (!confirm('Cancel this coverage request?')) return;
        btn.disabled = true;

        schedulesAjax({ action: 'schedules_cancel_cover_request', trade_id: btn.dataset.tradeId })
            .then(function (res) {
                if (res.success) {
                    loadCoverRequests();
                } else {
                    btn.disabled = false;
                    alert((res.data && res.data.message) || 'Error.');
                }
            }).catch(function () {
                btn.disabled = false;
                alert('Network error.');
            });
    });

    // Supervisor: load pending time-off requests
    function loadSupPendingTimeoff() {
        var listEl = document.getElementById('sup-timeoff-pending-list');
        if (!listEl) return;
        listEl.innerHTML = '<p class="loading-msg">Loading\u2026</p>';

        schedulesAjax({ action: 'schedules_get_pending_timeoff' }).then(function (res) {
            if (!res.success) { listEl.innerHTML = '<p class="form-error">Error loading requests.</p>'; return; }
            var requests = res.data.requests || [];
            if (!requests.length) { listEl.innerHTML = '<p class="no-data">No pending time-off requests.</p>'; return; }

            var html = '<div class="cover-request-list">';
            requests.forEach(function (r) {
                var start   = coverDateFmt(r.start_date);
                var end     = r.end_date && r.end_date !== r.start_date ? ' \u2013 ' + coverDateFmt(r.end_date) : '';
                var typeStr = (r.type || 'PDO').toUpperCase();
                var hrs     = r.hours ? ' (' + r.hours + 'h)' : '';
                html += '<div class="cover-request-card" data-timeoff-id="' + esc(r.id) + '">';
                html += '<div class="cover-card-main">';
                html += '<span class="cover-card-type">' + esc(typeStr) + '</span>';
                html += '<span class="cover-card-date">' + esc(start + end) + esc(hrs) + '</span>';
                html += '<span class="cover-card-name">' + esc(r.member) + '</span>';
                html += '</div>';
                if (r.notes) html += '<div class="cover-card-note">' + esc(r.notes) + '</div>';
                html += '<div class="cover-sup-actions">';
                html += '<button type="button" class="action-btn timeoff-approve-btn" data-timeoff-id="' + esc(r.id) + '" data-decision="approved">Approve</button>';
                html += '<button type="button" class="schedules-btn schedules-btn-secondary timeoff-coverage-btn" data-timeoff-id="' + esc(r.id) + '" data-decision="coverage">Pending Coverage</button>';
                html += '<button type="button" class="basic-btn timeoff-deny-btn" data-timeoff-id="' + esc(r.id) + '" data-decision="denied">Deny</button>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            listEl.innerHTML = html;
        }).catch(function () {
            listEl.innerHTML = '<p class="form-error">Network error.</p>';
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.timeoff-approve-btn, .timeoff-coverage-btn, .timeoff-deny-btn');
        if (!btn) return;
        var id       = btn.dataset.timeoffId;
        var decision = btn.dataset.decision;
        btn.disabled = true;
        schedulesAjax({ action: 'schedules_review_timeoff', timeoff_id: id, decision: decision })
            .then(function (res) {
                if (res.success) {
                    loadSupPendingTimeoff();
                } else {
                    btn.disabled = false;
                    alert((res.data && res.data.message) || 'Error.');
                }
            }).catch(function () { btn.disabled = false; alert('Network error.'); });
    });

    // Supervisor: load pending coverage approvals
    function loadSupCoverRequests() {
        var listEl = document.getElementById('sup-cover-pending-list');
        if (!listEl) return;
        listEl.innerHTML = '<p class="loading-msg">Loading\u2026</p>';

        schedulesAjax({ action: 'schedules_get_cover_requests' }).then(function (res) {
            if (!res.success) {
                listEl.innerHTML = '<p class="form-error">Error loading requests.</p>';
                return;
            }
            var requests = res.data.requests || [];

            if (!requests.length) {
                listEl.innerHTML = '<p class="no-data">No pending coverage requests.</p>';
                return;
            }

            var html = '<div class="cover-request-list">';
            requests.forEach(function (r) {
                var dateStr = r.trade_date ? coverDateFmt(r.trade_date) : '\u2014';
                var reqName = esc((r.req_first + ' ' + r.req_last).trim());
                var recName = esc((r.rec_first + ' ' + r.rec_last).trim());

                html += '<div class="cover-request-card" data-trade-id="' + esc(r.id) + '">';
                html += '<div class="cover-card-main">';
                html += '<span class="cover-shift-badge shift-' + esc((r.shift_letter || '').toLowerCase()) + '">' + esc(r.shift_letter || '') + '</span>';
                html += '<span class="cover-card-date">' + esc(dateStr) + '</span>';
                html += '<span class="cover-card-name">' + reqName + ' \u2192 ' + recName + '</span>';
                html += '</div>';
                if (r.requester_note || r.recipient_note) {
                    html += '<div class="cover-card-note">';
                    if (r.requester_note) html += '<span>Requester: ' + esc(r.requester_note) + '</span> ';
                    if (r.recipient_note) html += '<span>Recipient: ' + esc(r.recipient_note) + '</span>';
                    html += '</div>';
                }
                html += '<div class="cover-sup-actions">';
                html += '<button type="button" class="action-btn cover-approve-btn" data-trade-id="' + esc(r.id) + '">Approve</button>';
                html += '<button type="button" class="basic-btn cover-reject-btn"  data-trade-id="' + esc(r.id) + '">Reject</button>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            listEl.innerHTML = html;
        }).catch(function () {
            listEl.innerHTML = '<p class="form-error">Network error.</p>';
        });
    }

    // Supervisor: approve or reject
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.cover-approve-btn, .cover-reject-btn');
        if (!btn) return;

        var tradeId  = btn.dataset.tradeId;
        var decision = btn.classList.contains('cover-approve-btn') ? 'approve' : 'reject';
        btn.disabled = true;

        schedulesAjax({ action: 'schedules_review_cover_request', trade_id: tradeId, decision: decision, note: '' })
            .then(function (res) {
                if (res.success) {
                    loadSupCoverRequests();
                } else {
                    btn.disabled = false;
                    alert((res.data && res.data.message) || 'Error.');
                }
            }).catch(function () {
                btn.disabled = false;
                alert('Network error.');
            });
    });

    // ================================================================
    // GOD MODE (battleplanweb only)
    // ================================================================
    (function () {
        var godView = document.getElementById('sup-view-godmode');
        if (!godView) return;

        var currentTable = '';
        var currentPage  = 1;
        var totalRows    = 0;
        var perPage      = 50;

        // ---- Sub-tab switching ----
        godView.addEventListener('click', function (e) {
            var tab = e.target.closest('.god-tab');
            if (!tab) return;
            var panel = tab.dataset.godTab;
            godView.querySelectorAll('.god-tab').forEach(function (t) { t.classList.remove('active'); });
            tab.classList.add('active');
            godView.querySelectorAll('.god-panel').forEach(function (p) { p.hidden = true; });
            var ap = document.getElementById('god-panel-' + panel);
            if (ap) ap.hidden = false;
            if (panel === 'options') loadGodOptions();
        });

        // ---- Table selector ----
        var tableSelect = document.getElementById('god-table-select');
        if (tableSelect) {
            tableSelect.addEventListener('change', function () {
                currentTable = this.value;
                currentPage  = 1;
                if (currentTable) loadGodTable();
            });
        }

        // ---- Pagination ----
        var prevBtn = document.getElementById('god-prev-page');
        var nextBtn = document.getElementById('god-next-page');
        if (prevBtn) prevBtn.addEventListener('click', function () {
            if (currentPage > 1) { currentPage--; loadGodTable(); }
        });
        if (nextBtn) nextBtn.addEventListener('click', function () {
            if (currentPage * perPage < totalRows) { currentPage++; loadGodTable(); }
        });

        // ---- Load table ----
        function loadGodTable() {
            var wrap = document.getElementById('god-table-wrap');
            if (!wrap || !currentTable) return;
            wrap.innerHTML = '<p class="god-loading">Loading&hellip;</p>';
            schedulesAjax({ action: 'schedules_god_get_table', table: currentTable, page: currentPage })
                .then(function (data) {
                    if (!data.success) {
                        wrap.innerHTML = '<p class="god-error">' + godEsc((data.data && data.data.message) || 'Error') + '</p>';
                        return;
                    }
                    var d = data.data;
                    totalRows = d.total;
                    perPage   = d.per;
                    var pageInfo = document.getElementById('god-page-info');
                    var rowCount = document.getElementById('god-row-count');
                    if (pageInfo) pageInfo.textContent = 'Page ' + d.page + ' of ' + Math.max(1, Math.ceil(d.total / d.per)) + ' (' + d.total + ' rows)';
                    if (rowCount) rowCount.textContent = d.total + ' rows';
                    if (prevBtn) prevBtn.disabled = d.page <= 1;
                    if (nextBtn) nextBtn.disabled = d.page * d.per >= d.total;
                    renderGodTable(d.columns, d.rows, wrap);
                });
        }

        // ---- Render table ----
        function renderGodTable(columns, rows, wrap) {
            if (!rows || !rows.length) {
                wrap.innerHTML = '<p class="god-empty">No rows in this table.</p>';
                return;
            }
            var pk = columns[0];
            var html = '<div class="god-scroll"><table class="god-data-table"><thead><tr>';
            columns.forEach(function (c) { html += '<th>' + godEsc(c) + '</th>'; });
            html += '<th class="god-col-actions">Delete</th></tr></thead><tbody>';
            rows.forEach(function (row) {
                var pkVal = row[pk];
                html += '<tr data-pk-col="' + godEsc(pk) + '" data-pk-val="' + godEsc(pkVal) + '">';
                columns.forEach(function (c) {
                    var val = row[c] !== null && row[c] !== undefined ? String(row[c]) : '';
                    html += '<td class="god-cell" data-col="' + godEsc(c) + '">';
                    html += '<span class="god-display">' + godEsc(val) + '</span>';
                    html += '</td>';
                });
                html += '<td class="god-col-actions"><button type="button" class="schedules-btn schedules-btn-danger god-del-btn" data-pk-col="' + godEsc(pk) + '" data-pk-val="' + godEsc(pkVal) + '">&#10005;</button></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            wrap.innerHTML = html;
        }

        // ---- Inline edit: click a cell ----
        document.addEventListener('click', function (e) {
            var cell = e.target.closest('.god-cell');
            if (!cell || !document.getElementById('god-table-wrap') || !document.getElementById('god-table-wrap').contains(cell)) return;
            if (cell.classList.contains('god-editing')) return;
            cell.classList.add('god-editing');
            var display  = cell.querySelector('.god-display');
            var origVal  = display ? display.textContent : '';
            var col      = cell.dataset.col;
            var row      = cell.closest('tr');
            var pkCol    = row ? row.dataset.pkCol : '';
            var pkVal    = row ? row.dataset.pkVal : '';
            var input    = document.createElement('textarea');
            input.className = 'god-cell-input';
            input.value     = origVal;
            input.rows      = Math.max(1, Math.min(6, Math.ceil(origVal.length / 50)));
            if (display) display.hidden = true;
            cell.appendChild(input);
            input.focus();

            function commit() {
                var newVal = input.value;
                cell.classList.remove('god-editing');
                input.remove();
                if (display) display.hidden = false;
                if (newVal === origVal) return;
                display.textContent = newVal;
                cell.classList.add('god-saving');
                schedulesAjax({ action: 'schedules_god_update_cell', table: currentTable, pk_col: pkCol, pk_val: pkVal, col: col, value: newVal })
                    .then(function (res) {
                        cell.classList.remove('god-saving');
                        cell.classList.add(res.success ? 'god-saved' : 'god-err');
                        setTimeout(function () { cell.classList.remove('god-saved', 'god-err'); }, 1800);
                    });
            }

            input.addEventListener('blur', commit);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    cell.classList.remove('god-editing');
                    input.remove();
                    if (display) { display.hidden = false; display.textContent = origVal; }
                }
            });
        });

        // ---- Delete row ----
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.god-del-btn');
            if (!btn || !document.getElementById('god-table-wrap') || !document.getElementById('god-table-wrap').contains(btn)) return;
            if (!confirm('Delete this row from ' + currentTable + '? This cannot be undone.')) return;
            schedulesAjax({ action: 'schedules_god_delete_row', table: currentTable, pk_col: btn.dataset.pkCol, pk_val: btn.dataset.pkVal })
                .then(function (res) {
                    if (!res.success) { alert((res.data && res.data.message) || 'Delete failed.'); return; }
                    loadGodTable();
                });
        });

        // ---- WP Options ----
        function loadGodOptions() {
            var wrap = document.getElementById('god-options-wrap');
            if (!wrap || wrap.dataset.loaded) return;
            wrap.innerHTML = '<p class="god-loading">Loading options&hellip;</p>';
            schedulesAjax({ action: 'schedules_god_get_options' }).then(function (data) {
                if (!data.success) { wrap.innerHTML = '<p class="god-error">Error loading options.</p>'; return; }
                var opts = data.data.options;
                var html = '<table class="god-data-table"><thead><tr><th>Option Name</th><th>Value</th><th></th></tr></thead><tbody>';
                opts.forEach(function (opt) {
                    html += '<tr>';
                    html += '<td class="god-option-name">' + godEsc(opt.option_name) + '</td>';
                    html += '<td><textarea class="god-option-value" rows="2">' + godEsc(opt.option_value) + '</textarea></td>';
                    html += '<td><button type="button" class="schedules-btn schedules-btn-primary god-save-opt" data-opt="' + godEsc(opt.option_name) + '">Save</button></td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
                wrap.innerHTML = html;
                wrap.dataset.loaded = '1';
            });
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.god-save-opt');
            if (!btn || !document.getElementById('god-options-wrap') || !document.getElementById('god-options-wrap').contains(btn)) return;
            var row = btn.closest('tr');
            var val = row ? row.querySelector('.god-option-value').value : '';
            btn.disabled = true;
            btn.textContent = 'Saving\u2026';
            schedulesAjax({ action: 'schedules_god_update_option', option_name: btn.dataset.opt, option_value: val })
                .then(function (res) {
                    btn.disabled = false;
                    btn.textContent = res.success ? 'Saved \u2713' : 'Error';
                    setTimeout(function () { btn.textContent = 'Save'; }, 2000);
                });
        });

        function godEsc(s) {
            return String(s === null || s === undefined ? '' : s)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    }());

}());
