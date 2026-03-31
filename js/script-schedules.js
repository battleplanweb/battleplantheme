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
    function schedulesAjax(data) {
        var params = new URLSearchParams();
        params.append('nonce', schedulesData.nonce);
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

    function loadNotifications() {
        var body = document.getElementById('notif-panel-body');
        if (!body) return;
        body.innerHTML = '<p class="notif-loading">Loading\u2026</p>';

        schedulesAjax({ action: 'schedules_get_notifications' }).then(function (res) {
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
                var cls = 'notif-item' + (n.is_read ? ' notif-read' : '');
                html += '<div class="' + cls + '" data-id="' + n.id + '">';
                html += '<div class="notif-message">' + esc(n.message) + '</div>';
                html += '<div class="notif-time">' + formatNotifTime(n.created_at) + '</div>';

                // Approve/deny buttons for pending PDO requests
                if (n.type === 'timeoff_request' && n.related_id) {
                    html += '<div class="notif-actions">';
                    html += '<button class="schedules-btn schedules-btn-primary notif-approve-btn" data-timeoff-id="' + n.related_id + '">Approve</button>';
                    html += '<button class="schedules-btn schedules-btn-secondary notif-deny-btn" data-timeoff-id="' + n.related_id + '">Deny</button>';
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

    // Approve / Deny from notification
    document.addEventListener('click', function (e) {
        var approveBtn = e.target.closest('.notif-approve-btn');
        var denyBtn    = e.target.closest('.notif-deny-btn');
        var btn = approveBtn || denyBtn;
        if (!btn) return;

        var timeoffId = btn.dataset.timeoffId;
        var decision  = approveBtn ? 'approved' : 'denied';

        btn.disabled = true;

        schedulesAjax({
            action:     'schedules_review_timeoff',
            timeoff_id: timeoffId,
            decision:   decision,
        }).then(function (res) {
            if (res.success) {
                var item = btn.closest('.notif-item');
                var actions = btn.closest('.notif-actions');
                if (actions) {
                    actions.innerHTML = '<span class="notif-decision notif-decision-' + decision + '">' + (decision === 'approved' ? 'Approved' : 'Denied') + '</span>';
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
        if (noteEl) noteEl.textContent = 'You are signing up for ' + claimHours + ' hour' + (claimHours > 1 ? 's' : '') + ' of OT, from ' + rangeStr;

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

        schedulesAjax({ action: 'schedules_claim_block', block_id: blockId })
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

                    // Add undo buttons with 5-minute grace period
                    var graceMs = 5 * 60 * 1000;
                    claimedIds.forEach(function (cid) {
                        var el = document.querySelector('.time-block[data-block-id="' + cid + '"]');
                        if (!el) return;
                        el.classList.add('claim-undoable');
                        var undoBtn = document.createElement('button');
                        undoBtn.className = 'claim-undo-btn';
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

    function refreshMyClaims() {
        var claimsSection = document.getElementById('schedules-my-claims');
        if (claimsSection) {
            setTimeout(function () { window.location.reload(); }, 800);
        }
    }

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

    function setCustomDay(form, dow, start, end) {
        var row = form.querySelector('.custom-day-row[data-dow="' + dow + '"]');
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
        for (var dow in schedule) {
            if (Object.prototype.hasOwnProperty.call(schedule, dow)) {
                var entry = schedule[dow];
                setCustomDay(form, parseInt(dow, 10), entry.start, entry.end);
            }
        }
    }

    function readCustomScheduleData(form) {
        var data = {};
        form.querySelectorAll('.custom-day-check').forEach(function (cb) {
            if (!cb.checked) return;
            var row = cb.closest('.custom-day-row');
            var dow = row ? row.dataset.dow : null;
            if (dow == null) return;
            var startSel = row.querySelector('.custom-start-hour');
            var endSel   = row.querySelector('.custom-end-hour');
            data['custom_day[' + dow + ']']   = '1';
            data['custom_start[' + dow + ']'] = startSel ? startSel.value : '0';
            data['custom_end[' + dow + ']']   = endSel   ? endSel.value   : '0';
        });
        return data;
    }

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
            pay_rate        : btn.dataset.payRate       || '0',
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
            // For priority: only '1' and '5' are manual; 2/3/4 map to '' (auto)
            var prio = data.priority || '';
            mset('priority',   (prio === '1' || prio === '5') ? prio : '');
            mset('title_id',   data.title_id  || '0');
            mset('pay_rate',   data.pay_rate   || '0');
            mset('member_role', data.role || 'member');
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
                    def.days.forEach(function (dow) {
                        setCustomDay(form, dow, def.start, def.end);
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
            priority     : fd.get('priority')     || '',
            member_role  : fd.get('member_role')  || 'member',
            title_id     : parseInt(fd.get('title_id') || '0', 10),
            pay_rate     : parseFloat(fd.get('pay_rate') || '0'),
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

            var startSel = card.querySelector('select[name$="[start_hour]"]');
            var endSel   = card.querySelector('select[name$="[end_hour]"]');
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

            shiftsObj[shift] = {
                start_hour:   startSel ? parseInt(startSel.value, 10) : 6,
                end_hour:     endSel   ? parseInt(endSel.value, 10)   : 18,
                member_count: memberIn ? parseInt(memberIn.value, 10) : 0,
                max_capacity: maxCapIn ? parseInt(maxCapIn.value, 10) : 14,
                days_week1:   w1,
                days_week2:   w2,
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

    function activateSupTab(view) {
        document.querySelectorAll('.sup-tab').forEach(function (t) {
            var active = t.dataset.view === view;
            t.classList.toggle('active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.sup-view').forEach(function (v) {
            v.hidden = true;
            v.classList.remove('active');
        });
        var activeView = document.getElementById('sup-view-' + view);
        if (activeView) {
            activeView.hidden = false;
            activeView.classList.add('active');
        }
    }

    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.sup-tab');
        if (!tab) return;
        var view = tab.dataset.view;
        activateSupTab(view);
        try { sessionStorage.setItem('schedules_sup_tab', view); } catch(ex) {}
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
        if (!confirm('Remove "' + name + '"? This cannot be undone if members are assigned to it.')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_delete_discipline', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Removed.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not remove.', 'error');
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
        if (!confirm('Remove "' + name + '"?')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_delete_position', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Removed.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not remove.', 'error');
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
        if (!confirm('Remove "' + name + '"?')) return;

        btn.disabled = true;
        schedulesAjax({ action: 'schedules_delete_title', id: btn.dataset.id })
            .then(function (res) {
                if (res.success) {
                    showToast((res.data && res.data.message) || 'Removed.', 'success');
                    var row = btn.closest('tr');
                    if (row) { row.style.opacity = '0'; setTimeout(function () { row.remove(); }, 300); }
                } else {
                    showToast((res.data && res.data.message) || 'Could not remove.', 'error');
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

    // Compute left% and width% for a bar within the shift window
    function barGeometry(startT, endT, shiftStartHour) {
        var shiftStartMins = shiftStartHour * 60;
        var shiftDurMins   = 12 * 60; // all CCSO shifts are 12h
        var sMins = timeToMins(startT);
        var eMins = timeToMins(endT);
        // Night-shift wrap: times < shiftStart mean they're past midnight
        if (sMins < shiftStartMins) sMins += 24 * 60;
        if (eMins <= shiftStartMins || eMins < sMins) eMins += 24 * 60;
        var left  = Math.max(0, Math.min(100, ((sMins - shiftStartMins) / shiftDurMins) * 100));
        var width = Math.max(0, Math.min(100 - left, ((eMins - sMins) / shiftDurMins) * 100));
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
        var totalHours = 12;

        // Group assignments by position_id, sorted chronologically
        var byPos = {};
        (data.assignments || []).forEach(function (a) {
            var pid = String(a.position_id);
            if (!byPos[pid]) byPos[pid] = [];
            byPos[pid].push(a);
        });
        Object.keys(byPos).forEach(function (pid) {
            byPos[pid].sort(function (a, b) {
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
                    html += '<button class="duty-remove-btn" type="button"' +
                            ' data-id="' + esc(String(a.id)) + '"' +
                            ' aria-label="Remove">&times;</button>';
                }
                html += '</div>';
            });

            html += '</div></div>'; // .duty-track / .duty-track-col
            html += '</div>'; // .duty-row
        });

        html += '</div></div>'; // .duty-timeline, .duty-timeline-col

        // --- Roster ---
        var roster = data.roster || [];
        if (roster.length) {
            html += '<div class="duty-roster-col"><table class="duty-roster">';
            html += '<thead><tr>';
            html += '<th>#</th><th>Name</th><th>Title</th><th>Assignment</th>';
            html += '</tr></thead><tbody>';
            roster.forEach(function (m, idx) {
                var isOt      = m.type === 'ot';
                var isAbsent  = !!m.timeoff;
                var rowClass  = isAbsent ? ' class="duty-roster-absent"' : (isOt ? ' class="duty-roster-ot"' : '');
                var dragAttrs = isSup && !isAbsent ? ' draggable="true" data-user-id="' + esc(String(m.user_id)) + '"' : '';
                html += '<tr' + rowClass + dragAttrs + '>';
                html += '<td>' + (idx + 1) + '</td>';
                html += '<td>' + esc(formatName(m)) + (isOt ? ' <span class="duty-roster-badge ot">OT</span>' : '') + '</td>';
                html += '<td>' + esc(m.title || '') + '</td>';

                // Assignment cell — show assignments with gap detection
                // For OT members, use their claimed hours as the window; for regulars, full shift
                var memberWindows;
                if (isOt && m.ot_hours && m.ot_hours.length) {
                    memberWindows = m.ot_hours.map(function (h) {
                        var ws = (h.start < 10 ? '0' : '') + h.start + ':00';
                        var we = (h.end < 10 ? '0' : '') + h.end + ':00';
                        return { startTime: ws, endTime: we };
                    });
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
                html += '</tr>';
            });
            html += '</tbody></table></div>'; // .duty-roster, .duty-roster-col
        }

        grid.innerHTML = html;
        markUniqueSpans(grid);

        if (!data.day_id && msg) {
            msg.textContent = 'No shift record exists yet for this date \u2014 it will be created on first assignment.';
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
        loadDutyGrid(dateEl.value, shiftEl.value);
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
                        dutyData.assignments = dutyData.assignments.filter(function (a) {
                            return String(a.id) !== String(id);
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
        if (titleEl)    titleEl.textContent   = isEdit ? 'Edit Assignment'    : 'Add Assignment';
        if (submitBtn)  submitBtn.textContent  = isEdit ? 'Update Assignment' : 'Add Assignment';

        // Member select
        var memberSel = modal.querySelector('#duty-member');
        if (!memberSel) return;
        memberSel.innerHTML = '<option value="">Select member\u2026</option>';
        (dutyData.members || []).forEach(function (m) {
            if (!isEdit && !opts.userId && opts.posDisc && (!m.disciplines || m.disciplines.indexOf(opts.posDisc) === -1)) return;
            var opt = document.createElement('option');
            opt.value = m.user_id;
            opt.textContent = formatName(m) + (m.type === 'ot' ? ' (OT)' : '');
            var freeSlots = getMemberFreeSlots(m.user_id, sh, isEdit ? opts.assignmentId : null);
            var freeTotal = freeSlots.reduce(function (sum, s) { return sum + (s.e - s.s); }, 0);
            if (freeTotal === 0) {
                opt.disabled = true;
                opt.textContent += ' \u2014 fully assigned';
            } else if (freeTotal < 12 * 60) {
                opt.textContent += ' \u2014 avail. ' + minsToTime(freeSlots[0].s) + '\u2013' + minsToTime(freeSlots[0].e);
                if (freeSlots.length > 1) opt.textContent += ' (+' + (freeSlots.length - 1) + ')';
            }
            memberSel.appendChild(opt);
        });
        if (opts.userId) {
            memberSel.value = String(opts.userId);
            if (!isEdit) {
                var memberSlots = getMemberFreeSlots(opts.userId, sh, null);
                var posSlots    = opts.posId ? getPosFreeSots(opts.posId, sh, opts.posName) : [];
                var free        = posSlots.length ? intersectSlots(posSlots, memberSlots) : memberSlots;
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
        if (isEdit && opts.userId) {
            allowedSlots = getMemberFreeSlots(opts.userId, sh, opts.assignmentId);
        } else if (opts.userId) {
            var mSlots = getMemberFreeSlots(opts.userId, sh, null);
            var pSlots = opts.posId ? getPosFreeSots(opts.posId, sh, opts.posName) : [];
            allowedSlots = pSlots.length ? intersectSlots(pSlots, mSlots) : mSlots;
        } else {
            allowedSlots = opts.posId ? getPosFreeSots(opts.posId, sh, opts.posName) : [];
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
    function buildTimeSelects(startSel, endSel, allowedSlots, sh, increment, currentStart, currentEnd) {
        var shiftStart = sh * 60;
        var shiftEnd   = sh * 60 + 12 * 60;
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
        var e     = slots.length ? slots[0].e : sh2 + 12 * 60;
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
        var posIdHid    = modal.querySelector('[name="position_id"]');
        var curPosId    = posIdHid ? posIdHid.value : null;
        var posSlots    = curPosId ? getPosFreeSots(curPosId, sh) : [];
        var free        = posSlots.length ? intersectSlots(posSlots, memberSlots) : memberSlots;
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

    // Returns free time slots [{s, e}] for a position within the current shift window.
    // Optionally pass posName to also exclude conflict-blocked ranges from other positions.
    function getPosFreeSots(posId, sh, posName) {
        var shiftStart = sh * 60;
        var shiftEnd   = sh * 60 + 12 * 60;
        var blocked = (dutyData.assignments || [])
            .filter(function (a) { return String(a.position_id) === String(posId); })
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
        var shiftEnd   = sh * 60 + 12 * 60;

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

    document.addEventListener('dragover', function (e) {
        var bar = e.target.closest('.duty-add-btn');
        if (!bar || !dutyData) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        bar.classList.add('duty-drop-hover');
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

        var userId = e.dataTransfer.getData('text/plain');
        if (!userId) return;

        var posId   = bar.dataset.posId;
        var posName = bar.dataset.posName;
        var posDisc = bar.dataset.posDisc || '';
        var sh      = dutyData.shift_start_hour || 6;
        var times   = calcDefaultTimes(posId, sh, posName);

        openDutyModal({ mode: 'add', posId: posId, posName: posName, posDisc: posDisc,
                        userId: userId, startTime: times.start, endTime: times.end });
    });

    // --- Open edit-assignment modal (click on bar) ---
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.duty-bar') || e.target.closest('.duty-remove-btn')) return;
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
        });
    });

    // --- Submit add-assignment form ---
    document.addEventListener('submit', function (e) {
        if (!e.target.matches('#duty-form')) return;
        e.preventDefault();

        var form    = e.target;
        var errEl   = document.getElementById('duty-form-error');
        var submit  = document.getElementById('duty-form-submit');
        if (errEl) errEl.textContent = '';

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
        };

        if (!payload.user_id || payload.user_id === '0') {
            if (errEl) errEl.textContent = 'Please select a member.';
            return;
        }
        if (!payload.start_time || !payload.end_time) {
            if (errEl) errEl.textContent = 'Please select start and end times.';
            return;
        }
        if (payload.start_time >= payload.end_time) {
            if (errEl) errEl.textContent = 'End time must be after start time.';
            return;
        }

        if (dutyData) {
            var sh2          = dutyData.shift_start_hour || 6;
            var startAbsMins = timeToAbsMins(payload.start_time, sh2);
            var endAbsMins   = timeToAbsMins(payload.end_time, sh2);
            var freeSlots2   = getMemberFreeSlots(payload.user_id, sh2, isEdit ? assignmentId : null);
            var fits         = freeSlots2.some(function (slot) { return slot.s <= startAbsMins && slot.e >= endAbsMins; });
            if (!fits) {
                if (errEl) errEl.textContent = 'This member is already assigned during part of that time.';
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
                                return x.start_time < y.start_time ? -1 : 1;
                            });
                        }
                        syncRosterAssignments();
                        renderDutyTimeline(dutyData);
                    }
                    showToast(isEdit ? 'Assignment updated.' : 'Assignment added.', 'success');
                } else {
                    if (errEl) errEl.textContent = (res.data && res.data.message) || (isEdit ? 'Failed to update.' : 'Failed to add.');
                }
            })
            .catch(function () {
                if (submit) { submit.disabled = false; submit.textContent = isEdit ? 'Update Assignment' : 'Add Assignment'; }
                if (errEl) errEl.textContent = 'Network error.';
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

        var sh     = data.shift_start_hour || 6;
        var byPos  = {};
        (data.assignments || []).forEach(function (a) {
            var pid = String(a.position_id);
            if (!byPos[pid]) byPos[pid] = [];
            byPos[pid].push(a);
        });

        var html = '<div class="duty-timeline duty-timeline-readonly">';

        // Header
        html += '<div class="duty-timeline-header">';
        html += '<div class="duty-row-label"></div>';
        html += '<div class="duty-track-col"><div class="duty-track duty-track-header">';
        for (var hi = 0; hi <= 12; hi++) {
            var hr = (sh + hi) % 24;
            var lp = ((hi / 12) * 100).toFixed(3);
            var tx = -((hi / 12) * 100).toFixed(3);
            html += '<span class="duty-hour-tick" style="left:' + lp + '%;transform:translateX(' + tx + '%)">' +
                    (hr < 10 ? '0' : '') + hr + '00</span>';
        }
        html += '</div></div></div>';

        // Rows
        data.positions.forEach(function (pos) {
            var asgns = byPos[String(pos.id)] || [];
            html += '<div class="duty-row">';
            html += '<div class="duty-row-label"><span class="duty-pos-name">' + esc(pos.name) + '</span></div>';
            html += '<div class="duty-track-col"><div class="duty-track">';
            html += '<div class="duty-bg-bar"></div>';
            asgns.forEach(function (a) {
                var geo   = barGeometry(a.start_time, a.end_time, sh);
                var label = esc(formatName(a)) + ' \u2022 ' + fmtTime(a.start_time) + '\u2013' + fmtTime(a.end_time);
                html += '<div class="duty-bar" style="left:' + geo.left + '%;width:' + geo.width + '%">';
                html += '<span class="duty-bar-label">' + label + '</span>';
                html += '</div>';
            });
            html += '</div></div></div>';
        });

        html += '</div>';
        content.innerHTML = html;
        markUniqueSpans(content);
    }

    function loadMemberDuty(date) {
        var content = document.getElementById('member-duty-content');
        if (!content) return;

        content.innerHTML = '<p class="duty-loading">Loading\u2026</p>';

        schedulesAjax({ action: 'schedules_get_member_duty', date: date || '' })
            .then(function (res) {
                if (res.success) {
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

    document.addEventListener('change', function (e) {
        if (!e.target.matches('#member-duty-date')) return;
        var dateEl = document.getElementById('member-duty-date');
        loadMemberDuty(dateEl ? dateEl.value : '');
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
            for (var i = 0; i < days.length; i += 7) {
                html += '<tr>';
                for (var j = i; j < i + 7 && j < days.length; j++) {
                    var day = days[j];
                    var cls = 'schedule-day-cell';
                    if (!day.in_month) cls += ' outside-month';
                    if (day.is_today) cls += ' is-today';

                    if (day.shift && day.in_month) cls += ' schedule-day-clickable';

                    html += '<td class="' + cls + '"' + (day.shift && day.in_month ? ' data-date="' + esc(day.date) + '"' : '') + '>';
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
                            if (to.status === 'pending') toCls += ' schedule-timeoff-pending';
                            html += '<div class="' + toCls + '">';
                            if (to.status === 'pending') {
                                html += esc(to.type.toUpperCase()) + ' <small>(requested)</small>';
                            } else {
                                html += esc(to.type.toUpperCase());
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
        return (h < 10 ? '0' : '') + h + '00';
    }

    /*--------------------------------------------------------------
    # Schedule Calendar — Time Off Request
    --------------------------------------------------------------*/

    // Track active schedule user ID
    var scheduleActiveUserId = '';

    // Open popup when clicking a shift day
    document.addEventListener('click', function (e) {
        var cell = e.target.closest('.schedule-day-clickable');
        if (!cell) return;

        var date = cell.dataset.date;
        if (!date || !scheduleActiveUserId) return;

        var popup     = document.getElementById('timeoff-popup');
        var dateLabel = document.getElementById('timeoff-popup-date');
        var dateInput = document.getElementById('timeoff-date');
        var userInput = document.getElementById('timeoff-user-id');

        if (!popup || !dateLabel || !dateInput || !userInput) return;

        // Format date for display
        var parts = date.split('-');
        var d = new Date(parseInt(parts[0],10), parseInt(parts[1],10) - 1, parseInt(parts[2],10));
        var dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        dateLabel.textContent = dayNames[d.getDay()] + ', ' + monthNames[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();

        dateInput.value = date;
        userInput.value = scheduleActiveUserId;
        document.getElementById('timeoff-type').value = 'pdo';
        document.getElementById('timeoff-hours').value = '12';
        document.getElementById('timeoff-notes').value = '';
        clearMsg(document.getElementById('timeoff-error'));

        popup.hidden = false;
    });

    // Close popup
    document.addEventListener('click', function (e) {
        if (e.target.closest('#timeoff-cancel-btn') || e.target.closest('.timeoff-popup-close')) {
            var popup = document.getElementById('timeoff-popup');
            if (popup) popup.hidden = true;
        }
    });

    // Submit time off request
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#timeoff-submit-btn');
        if (!btn) return;

        var errorEl = document.getElementById('timeoff-error');
        clearMsg(errorEl);

        var date   = document.getElementById('timeoff-date').value;
        var userId = document.getElementById('timeoff-user-id').value;
        var type   = document.getElementById('timeoff-type').value;
        var hours  = document.getElementById('timeoff-hours').value;
        var notes  = document.getElementById('timeoff-notes').value;

        if (!date || !userId || !type) {
            showMsg(errorEl, 'Missing required fields.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Submitting\u2026';

        schedulesAjax({
            action:  'schedules_submit_timeoff',
            user_id: userId,
            date:    date,
            type:    type,
            hours:   hours,
            notes:   notes,
        }).then(function (res) {
            if (res.success) {
                showToast('Time off request submitted.', 'success');
                document.getElementById('timeoff-popup').hidden = true;
                // Reload calendar
                loadScheduleCalendar(userId, scheduleYear, scheduleMonth);
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'Request failed.';
                showMsg(errorEl, msg);
            }
            btn.disabled = false;
            btn.textContent = 'Submit Request';
        }).catch(function () {
            showMsg(errorEl, 'Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Submit Request';
        });
    });

    // Member autocomplete
    (function () {
        var inp  = document.getElementById('schedule-member-search');
        var list = document.getElementById('schedule-member-suggestions');
        var sel  = document.getElementById('schedule-member-select');
        if (!inp || !list || !sel) return;

        var members     = Array.prototype.slice.call(sel.options).filter(function (o) { return o.value; });
        var selectedId  = '';
        var activeIdx   = -1;

        function getFiltered(q) {
            return q ? members.filter(function (o) { return o.text.toLowerCase().indexOf(q) !== -1; }) : members;
        }

        function renderList(filtered) {
            list.innerHTML = '';
            activeIdx = -1;
            filtered.forEach(function (o) {
                var li = document.createElement('li');
                li.textContent  = o.text;
                li.dataset.value = o.value;
                li.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    selectMember(o.value, o.text);
                });
                list.appendChild(li);
            });
            list.hidden = !filtered.length;
        }

        function hideList() { list.hidden = true; activeIdx = -1; }

        function selectMember(id, label) {
            selectedId    = id;
            sel.value     = id;
            scheduleActiveUserId = id;
            inp.value     = label;
            hideList();
            try { sessionStorage.setItem('schedules_schedule_uid',  id);    } catch(ex) {}
            try { sessionStorage.setItem('schedules_schedule_name', label); } catch(ex) {}
            loadScheduleCalendar(id, scheduleYear, scheduleMonth);
        }

        function setActive(items) {
            items.forEach(function (li, i) { li.classList.toggle('active', i === activeIdx); });
            if (activeIdx >= 0) items[activeIdx].scrollIntoView({ block: 'nearest' });
        }

        inp.addEventListener('input', function () {
            renderList(getFiltered(this.value.toLowerCase()));
        });

        inp.addEventListener('focus', function () {
            this.value = '';
            renderList(members);
        });

        inp.addEventListener('blur', function () {
            setTimeout(hideList, 150);
        });

        inp.addEventListener('keydown', function (e) {
            var items = list.querySelectorAll('li');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, items.length - 1);
                setActive(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                setActive(items);
            } else if (e.key === 'Enter' && activeIdx >= 0) {
                e.preventDefault();
                var li = items[activeIdx];
                selectMember(li.dataset.value, li.textContent);
            } else if (e.key === 'Escape') {
                hideList();
            }
        });

        // Month picker
        if (schedulePicker) {
            schedulePicker.addEventListener('change', function () {
                var parts  = this.value.split('-');
                scheduleYear  = parseInt(parts[0], 10);
                scheduleMonth = parseInt(parts[1], 10);
                try { sessionStorage.setItem('schedules_schedule_date', this.value); } catch(ex) {}
                if (selectedId) loadScheduleCalendar(selectedId, scheduleYear, scheduleMonth);
            });
        }

        // Restore saved schedule state
        var savedSchUid  = '';
        var savedSchName = '';
        var savedSchDate = '';
        try { savedSchUid  = sessionStorage.getItem('schedules_schedule_uid')  || ''; } catch(ex) {}
        try { savedSchName = sessionStorage.getItem('schedules_schedule_name') || ''; } catch(ex) {}
        try { savedSchDate = sessionStorage.getItem('schedules_schedule_date') || ''; } catch(ex) {}
        if (savedSchUid) {
            selectedId = savedSchUid;
            sel.value  = savedSchUid;
            scheduleActiveUserId = savedSchUid;
            if (inp) inp.value = savedSchName;
            if (schedulePicker && savedSchDate) {
                schedulePicker.value = savedSchDate.slice(0, 7);
                var sp = savedSchDate.split('-');
                scheduleYear  = parseInt(sp[0], 10);
                scheduleMonth = parseInt(sp[1], 10);
            }
            loadScheduleCalendar(savedSchUid, scheduleYear, scheduleMonth);
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
        if (savedTab && document.querySelector('.sup-tab[data-view="' + savedTab + '"]')) {
            activateSupTab(savedTab);
        }

        // Restore duty grid state
        var savedDutyDate  = '';
        var savedDutyShift = '';
        try { savedDutyDate  = sessionStorage.getItem('schedules_duty_date')  || ''; } catch(ex) {}
        try { savedDutyShift = sessionStorage.getItem('schedules_duty_shift') || ''; } catch(ex) {}
        if (savedDutyDate && document.getElementById('duty-date')) {
            var dutyDateEl  = document.getElementById('duty-date');
            var dutyShiftEl = document.getElementById('duty-shift');
            dutyDateEl.value = savedDutyDate;
            if (savedDutyShift && dutyShiftEl) dutyShiftEl.value = savedDutyShift;
            loadDutyGrid(savedDutyDate, dutyShiftEl ? dutyShiftEl.value : 'A');
        }

        // Auto-load member duty chart
        if (document.getElementById('member-duty-content')) {
            loadMemberDuty('');
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

}());
