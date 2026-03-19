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
        }
    });

    /*--------------------------------------------------------------
    # Block Selection & Claiming (OT Page)
    --------------------------------------------------------------*/

    var activePopup   = null;
    var activeBlockEl = null;

    function closeBlockPopup() {
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

        var dateFmt = date;
        if (date) {
            var d = new Date(date + 'T00:00:00');
            dateFmt = d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        }

        var popup = document.getElementById('block-confirm-popup');
        closeBlockPopup();

        var msgEl = popup.querySelector('.popup-message');
        if (msgEl) msgEl.textContent = 'Claim ' + timeStr + ' on Shift ' + shift + ', ' + dateFmt + '?';

        popup.dataset.blockId = block.dataset.blockId;
        activeBlockEl = block;

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
                    block.classList.remove('available', 'limited');
                    block.classList.add('claimed');
                    var statusEl = block.querySelector('.block-status');
                    if (statusEl) statusEl.textContent = 'Claimed';
                    block.dataset.available = response.data.remaining;
                    block.removeAttribute('tabindex');
                    showToast('Block claimed successfully!', 'success');
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

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#claims-load-btn');
        if (!btn) return;

        var dateEl   = document.getElementById('claims-date-filter');
        var shiftEl  = document.getElementById('claims-shift-filter');
        var results  = document.getElementById('claims-results');
        var date     = dateEl ? dateEl.value : '';
        var shift    = shiftEl ? shiftEl.value : '';

        if (!date) {
            if (results) results.innerHTML = '<p class="form-error">Please select a date.</p>';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Loading\u2026';
        if (results) results.innerHTML = '<p class="loading-msg">Loading claims\u2026</p>';

        schedulesAjax({ action: 'schedules_get_claims', date: date, shift_letter: shift })
            .then(function (response) {
                if (response.success) {
                    renderClaimsTable(response.data, results);
                } else {
                    if (results) results.innerHTML = '<p class="form-error">Could not load claims.</p>';
                }
                btn.disabled = false;
                btn.textContent = 'Load Claims';
            })
            .catch(function () {
                if (results) results.innerHTML = '<p class="form-error">Network error.</p>';
                btn.disabled = false;
                btn.textContent = 'Load Claims';
            });
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
    }

    /*--------------------------------------------------------------
    # Supervisor: Members
    --------------------------------------------------------------*/

    document.addEventListener('click', function (e) {
        if (e.target.closest('#add-member-btn')) openMemberModal(null);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.edit-member-btn');
        if (!btn) return;
        openMemberModal({
            user_id    : btn.dataset.userId,
            first_name : btn.dataset.firstName,
            last_name  : btn.dataset.lastName,
            badge      : btn.dataset.badge,
            email      : btn.dataset.email,
            shift      : btn.dataset.shift,
            discipline : btn.dataset.discipline,
            priority   : btn.dataset.priority,
            role       : btn.dataset.role,
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
            mset('priority',     data.priority || '');
            mset('is_supervisor', data.role === 'supervisor' ? '1' : '0');
            var pwField = form.elements['password'];
            if (pwField) pwField.removeAttribute('required');
            form.querySelectorAll('.new-only').forEach(function (el) { el.style.display = 'none'; });
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
        var box = modal.querySelector('.schedules-modal-box');
        if (box) box.focus();
    }

    function closeMemberModal() {
        var modal = document.getElementById('member-modal');
        if (modal) modal.hidden = true;
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
            is_supervisor: fd.get('is_supervisor')|| '0',
        };

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


    // Delete member
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.delete-member-btn');
        if (!btn) return;

        var userId = btn.dataset.userId;
        var name   = btn.dataset.name || 'this member';

        if (!confirm('Remove ' + name + ' from the scheduling system? This will revoke their access.')) return;

        btn.disabled = true;
        btn.textContent = 'Removing\u2026';

        schedulesAjax({ action: 'schedules_delete_member', user_id: userId })
            .then(function (response) {
                if (response.success) {
                    showToast((response.data && response.data.message) ? response.data.message : 'Member removed.', 'success');
                    var row = btn.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity .3s';
                        row.style.opacity = '0';
                        setTimeout(function () {
                            if (row.parentNode) row.parentNode.removeChild(row);
                        }, 300);
                    }
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Could not remove member.';
                    showToast(msg, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Remove';
                }
            })
            .catch(function () {
                showToast('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.textContent = 'Remove';
            });
    });

    /*--------------------------------------------------------------
    # Supervisor: Shift Settings
    --------------------------------------------------------------*/

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('#save-all-floor-counts-btn');
        if (!btn) return;

        var errorEl   = document.getElementById('settings-error');
        var successEl = document.getElementById('settings-success');
        clearMsg(errorEl);
        clearMsg(successEl);

        var counts = {};
        var valid  = true;
        document.querySelectorAll('.floor-count-input').forEach(function (input) {
            var val = parseInt(input.value, 10);
            if (isNaN(val) || val < 0) { valid = false; return; }
            counts[input.dataset.shift] = val;
        });

        if (!valid) {
            showMsg(errorEl, 'All floor counts must be 0 or higher.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Saving\u2026';

        var params = new URLSearchParams();
        params.append('nonce', schedulesData.nonce);
        params.append('action', 'schedules_save_all_floor_counts');
        Object.keys(counts).forEach(function (shift) {
            params.append('counts[' + shift + ']', counts[shift]);
        });

        fetch(schedulesData.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (response) {
            if (response.success) {
                showToast('Floor counts saved. Refreshing\u2026', 'success');
                setTimeout(function () { window.location.reload(); }, 1000);
            } else {
                var msg = (response.data && response.data.message) ? response.data.message : 'Save failed.';
                showMsg(errorEl, msg);
                btn.disabled = false;
                btn.textContent = 'Save All Floor Counts';
            }
        })
        .catch(function () {
            showMsg(errorEl, 'Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Save All Floor Counts';
        });
    });

    /*--------------------------------------------------------------
    # Supervisor Nav Tabs
    --------------------------------------------------------------*/

    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.sup-tab');
        if (!tab) return;

        var view = tab.dataset.view;

        document.querySelectorAll('.sup-tab').forEach(function (t) {
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');

        document.querySelectorAll('.sup-view').forEach(function (v) {
            v.hidden = true;
            v.classList.remove('active');
        });
        var activeView = document.getElementById('sup-view-' + view);
        if (activeView) {
            activeView.hidden = false;
            activeView.classList.add('active');
        }
    });

    /*--------------------------------------------------------------
    # Init
    --------------------------------------------------------------*/

    document.addEventListener('DOMContentLoaded', function () {
        // Show first week on page load
        var firstTab = document.querySelector('.week-tab[data-week="1"]');
        if (firstTab) firstTab.click();

        // Hide error/success messages on page load
        document.querySelectorAll('.form-error, .form-success').forEach(function (el) {
            el.style.display = 'none';
        });
    });

}());
