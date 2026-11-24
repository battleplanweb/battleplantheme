/* Battle Plan Web Design - HVAC Replacement Estimator ([hvac-estimator])
 *
 * Walks the question flow the server hands over in data-estimator (ask_if / end_if / depends_on /
 * choices_by — defined once in includes-hvac-estimator.php), then asks the server for the result. No
 * pricing or eligibility rule lives here. Answers persist in localStorage so a refresh or a return visit
 * reopens the same quiz (and the server's completion reference stays the same, so no duplicate email).
 */
document.addEventListener('DOMContentLoaded', function () { 'use strict';

	document.querySelectorAll('.estimator[data-estimator]').forEach(initEstimator);

	function initEstimator(root) {
		let cfg;
		try { cfg = JSON.parse(root.dataset.estimator); } catch (e) { return; }

		const Q       = cfg.questions;
		const byId    = Object.fromEntries(Q.map(q => [q.id, q]));
		const stage   = root.querySelector('.estimator-stage');
		const actions = root.querySelector('.estimator-actions');
		const pLabel  = root.querySelector('.estimator-progress-label');
		const pTrack  = root.querySelector('.estimator-progress-track');
		const pFill   = root.querySelector('.estimator-progress-fill');

		let state = load() || fresh();

		/* ---------- storage (per-viewer convenience; works without it) ---------- */
		function fresh() {
			return { sid: newSid(), answers: {}, current: Q[0].id, done: false };
		}
		function newSid() {
			const a = new Uint8Array(16);
			(window.crypto || window.msCrypto).getRandomValues(a);
			return Array.from(a, b => b.toString(16).padStart(2, '0')).join('');
		}
		function load() {
			try {
				const s = JSON.parse(localStorage.getItem(cfg.storage) || 'null');
				if (s && /^[a-f0-9]{32}$/.test(s.sid) && s.answers && byId[s.current]) return s;
			} catch (e) {}
			return null;
		}
		function save() {
			try { localStorage.setItem(cfg.storage, JSON.stringify(state)); } catch (e) {}
		}

		/* ---------- flow rules (data from the server) ---------- */
		function isAsked(q, ans) {
			return Object.entries(q.ask_if || {}).every(([dep, allowed]) => allowed.includes(String(ans[dep] ?? '')));
		}
		function choiceKeys(q, ans) {
			let keys = q.choices.map(c => c.key);
			Object.entries(q.choices_by || {}).forEach(([dep, map]) => {
				const a = ans[dep];
				if (a != null && map[a]) keys = keys.filter(k => map[a].includes(k));
			});
			return keys;
		}
		/* The questions on the path for these answers. An unanswered question's dependants are assumed
		   not asked, so the count on question 1 is the common (one-system) path and grows if needed. */
		function path(ans) {
			const out = [];
			for (const q of Q) {
				if (!isAsked(q, ans)) continue;
				out.push(q.id);
				if (ans[q.id] != null && (q.end_if || []).includes(ans[q.id])) return { ids: out, ended: true };
			}
			return { ids: out, ended: false };
		}
		/* Answers the server should see: exactly the answered path. */
		function pathAnswers() {
			const out = {};
			path(state.answers).ids.forEach(id => { if (state.answers[id] != null) out[id] = state.answers[id]; });
			return out;
		}
		function setAnswer(id, value) {
			const before = state.answers[id];
			state.answers[id] = value;
			if (before === value) return;
			// Clear what this change invalidates: dependants (recursively) and answers no longer valid/asked.
			let changed = [id];
			while (changed.length) {
				const next = [];
				Q.forEach(q => {
					if (state.answers[q.id] == null) return;
					const dependent = (q.depends_on || []).some(d => changed.includes(d));
					const invalid   = !isAsked(q, state.answers) || !choiceKeys(q, state.answers).includes(state.answers[q.id]);
					if (dependent || invalid) { delete state.answers[q.id]; next.push(q.id); }
				});
				changed = next;
			}
		}

		/* ---------- progress ---------- */
		function progress(id) {
			const p     = path(state.answers);
			const total = p.ids.length;
			if (id === 'result') {
				setProgress(100, 'Your estimate');
				return;
			}
			const n = p.ids.indexOf(id) + 1;
			setProgress(Math.round(n / (total + 1) * 100), 'Question ' + n + ' of ' + total);
		}
		function setProgress(pct, label) {
			pFill.style.width = pct + '%';
			pTrack.setAttribute('aria-valuenow', pct);
			pTrack.setAttribute('aria-valuetext', label);
			pLabel.textContent = label;
		}

		/* ---------- DOM helpers ---------- */
		function el(tag, cls, text) {
			const e = document.createElement(tag);
			if (cls) e.className = cls;
			if (text != null) e.textContent = text;
			return e;
		}
		function telLink(cls) {
			const a = el('a', cls, cfg.phone);
			a.href = 'tel:' + cfg.tel;
			return a;
		}
		function focusHeading(focus) {
			if (!focus) return;
			const h = stage.querySelector('.estimator-heading');
			if (!h) return;
			h.focus({ preventScroll: true });
			const top = root.getBoundingClientRect().top;
			if (top < 0 || top > window.innerHeight * 0.4) root.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		/* ---------- question screen ---------- */
		function showQuestion(id, focus) {
			const q = byId[id];
			state.current = id; state.done = false; save();
			actions.hidden = true;
			progress(id);

			const form   = el('form', 'estimator-step');
			form.noValidate = true;
			const fs     = el('fieldset', 'estimator-fieldset');
			const legend = el('legend', 'estimator-legend');
			const h      = el('h2', 'estimator-heading', q.title);
			h.tabIndex = -1;
			legend.append(h);
			fs.append(legend);
			if (q.help) {
				const help = el('p', 'estimator-help', q.help);
				help.id = 'bpe-help-' + q.id;
				fs.setAttribute('aria-describedby', help.id);
				fs.append(help);
			}

			const list = el('div', 'estimator-choices');
			const keys = choiceKeys(q, state.answers);
			q.choices.filter(c => keys.includes(c.key)).forEach(c => {
				const lab = el('label', 'estimator-choice');
				const inp = document.createElement('input');
				inp.type = 'radio'; inp.name = 'bpe-' + q.id; inp.value = c.key; inp.id = 'bpe-' + q.id + '-' + c.key;
				inp.className = 'estimator-radio';
				if (state.answers[q.id] === c.key) inp.checked = true;
				const txt = el('span', 'estimator-choice-text');
				txt.append(el('span', 'estimator-choice-label', c.label));
				if (c.desc) txt.append(el('span', 'estimator-choice-desc', c.desc));
				lab.append(inp, txt);
				list.append(lab);
			});
			fs.append(list);

			const err = el('p', 'estimator-error');
			err.setAttribute('role', 'alert');
			fs.append(err);
			form.append(fs);

			const nav  = el('div', 'estimator-nav');
			const idx  = path(state.answers).ids.indexOf(id);
			if (idx > 0) {
				const back = el('button', 'estimator-back', 'Back');
				back.type = 'button';
				back.addEventListener('click', goBack);
				nav.append(back);
			}
			const next = el('button', 'estimator-next', 'Next');
			next.type = 'submit';
			nav.append(next);
			form.append(nav);

			// Picking a card only selects it. Next (or Enter) moves on, so a mis-tap can be fixed first.
			form.addEventListener('submit', e => {
				e.preventDefault();
				const sel = form.querySelector('input:checked');
				if (!sel) { err.textContent = 'Choose an answer to continue.'; list.querySelector('input').focus(); return; }
				answer(q.id, sel.value);
			});

			stage.replaceChildren(form);
			focusHeading(focus);
		}

		let advancing = false;
		function answer(id, value) {
			if (advancing) return;
			advancing = true;
			setTimeout(() => { advancing = false; }, 300);
			setAnswer(id, value);
			const p = path(state.answers);
			const i = p.ids.indexOf(id);
			if (p.ended && i === p.ids.length - 1) return getQuote(true);
			const nextId = p.ids[i + 1];
			if (!nextId) return getQuote(true);
			showQuestion(nextId, true);
		}

		function goBack() {
			const ids = path(state.answers).ids;
			const i   = state.done ? ids.length : ids.indexOf(state.current);
			if (i > 0) showQuestion(ids[i - 1], true);
		}

		/* ---------- result ---------- */
		function post(endpoint, body) {
			return fetch(cfg.rest + endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(Object.assign({ sid: state.sid, answers: pathAnswers() }, body))
			}).then(r => r.json().catch(() => ({ ok: false })).then(j => Object.assign({ httpStatus: r.status }, j)));
		}

		function getQuote(focus) {
			state.done = true; save();
			progress('result');
			actions.hidden = true;
			const wait = el('p', 'estimator-loading', 'Calculating your estimate…');
			wait.setAttribute('role', 'status');
			stage.replaceChildren(wait);

			post('quote', {}).then(j => {
				if (!j.ok || !j.result) throw new Error(j.message || '');
				state.t = j.t; state.sig = j.sig; state.ref = j.result.ref; save();
				showResult(j.result, focus);
			}).catch(e => showQuoteError(e.message, focus));
		}

		function showQuoteError(msg, focus) {
			const box = el('div', 'estimator-result estimator-result-error');
			const h   = el('h2', 'estimator-heading', 'We couldn\'t load your estimate');
			h.tabIndex = -1;
			const p   = el('p', '', (msg || 'Please check your connection and try again.') + ' You can also call us at ');
			p.append(telLink(), '.');
			const nav   = el('div', 'estimator-nav');
			const back  = el('button', 'estimator-back', 'Back'); back.type = 'button'; back.addEventListener('click', goBack);
			const retry = el('button', 'estimator-next', 'Try again'); retry.type = 'button'; retry.addEventListener('click', () => getQuote(true));
			nav.append(back, retry);
			box.append(h, p, nav);
			stage.replaceChildren(box);
			focusHeading(focus);
		}

		function showResult(r, focus) {
			const box = el('div', 'estimator-result estimator-result-' + r.outcome);

			if (r.outcome === 'priced') {
				box.append(el('p', 'estimator-result-system', r.type + ' · ' + r.tier + (r.series ? ' (' + r.series + ')' : '')));

				const price = el('div', 'estimator-price');
				const h = el('h2', 'estimator-heading estimator-monthly');
				h.tabIndex = -1;
				// The asterisk ties the monthly figure to the terms printed under the range (screen readers
				// skip both marks; the terms are still read, right after the price).
				const mark = () => { const m = el('span', 'estimator-mark', '*'); m.setAttribute('aria-hidden', 'true'); return m; };
				// The heading's mark is a <sup>, not a <span>: the framework turns any span that is a direct child
				// of a heading into a block subtitle, which dropped the * onto its own line. No space before it, so it
				// can't wrap away from 'month'.
				const sup = el('sup', 'estimator-mark', '*'); sup.setAttribute('aria-hidden', 'true');
				h.append('As low as ', el('strong', '', r.monthly), '/month', sup);
				const range = el('p', 'estimator-range');
				range.append(el('span', 'estimator-range-label', 'Estimated installed price'), el('strong', 'estimator-range-amount', r.range));
				const fin = el('p', 'estimator-financing');
				fin.append(mark(), el('span', '', r.financing + (r.provider ? ' Financing through ' + r.provider + '.' : '')));
				// The site's own product photo for this series, when the server found one (see bp_est_product()).
				// Opens in a new tab so the estimate stays on screen.
				if (r.product && r.product.img) {
					const main = el('div', 'estimator-price-main');
					main.append(h, range, fin);
					const fig = el('figure', 'estimator-product');
					const a = el('a', 'estimator-product-link');
					a.href = r.product.url; a.target = '_blank'; a.rel = 'noopener';
					const img = document.createElement('img');
					img.src = r.product.img; img.alt = r.product.alt || '';
					if (r.product.width) { img.width = r.product.width; img.height = r.product.height; }
					img.loading = 'lazy'; img.decoding = 'async';
					a.append(img, el('span', 'estimator-product-title', r.product.title));
					fig.append(a);
					price.classList.add('has-product');
					price.append(main, fig);
				} else {
					price.append(h, range, fin);
				}
				box.append(price);

				if (r.load_calc) box.append(el('p', 'estimator-load-calc', r.load_calc));
				box.append(el('p', 'estimator-note', r.note));
			} else {
				const h = el('h2', 'estimator-heading', r.title);
				h.tabIndex = -1;
				box.append(h, el('p', 'estimator-result-text', r.text));
				const call = el('p', 'estimator-result-call');
				const a = telLink('button estimator-tel');
				a.textContent = 'Call ' + cfg.phone;
				call.append(a);
				box.append(call);
			}

			const nav  = el('div', 'estimator-nav estimator-result-nav');
			// Change my answers: back to question 1 with every answer still selected, so they can step through and change any of them
			const back = el('button', 'estimator-back', 'Change my answers'); back.type = 'button'; back.addEventListener('click', () => showQuestion(path(state.answers).ids[0], true));
			const over = el('button', 'estimator-back', 'Start over'); over.type = 'button';
			over.addEventListener('click', () => { state = fresh(); save(); resetForms(); showQuestion(Q[0].id, true); });
			nav.append(back, over);

			stage.replaceChildren(box, nav);

			// Optional actions — email-my-estimate only when there is an estimate to send.
			resetForms();
			actions.hidden = false;
			actions.querySelector('[data-open="email"]').hidden = r.outcome !== 'priced';
			focusHeading(focus);
		}

		/* ---------- optional contact actions ---------- */
		function resetForms() {
			actions.querySelectorAll('.estimator-form').forEach(f => {
				f.hidden = true;
				f.querySelector('.estimator-status').textContent = '';
				f.querySelector('.estimator-status').className = 'estimator-status';
				f.querySelector('button[type="submit"]').hidden = false;
				f.querySelectorAll('.form-input').forEach(fi => fi.hidden = false);
			});
			actions.querySelectorAll('.estimator-open').forEach(b => b.setAttribute('aria-expanded', 'false'));
		}

		actions.querySelectorAll('.estimator-open').forEach(btn => {
			btn.addEventListener('click', () => {
				const form = actions.querySelector('.estimator-form[data-action="' + btn.dataset.open + '"]');
				const open = form.hidden;
				actions.querySelectorAll('.estimator-form').forEach(f => { f.hidden = true; });
				actions.querySelectorAll('.estimator-open').forEach(b => b.setAttribute('aria-expanded', 'false'));
				if (!open) return;
				form.hidden = false;
				btn.setAttribute('aria-expanded', 'true');
				const first = form.querySelector('input:not([name="hp"])');
				if (first) first.focus({ preventScroll: true });
				form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			});
		});

		actions.querySelectorAll('.estimator-form').forEach(form => {
			const status = form.querySelector('.estimator-status');
			const submit = form.querySelector('button[type="submit"]');
			const field  = n => form.querySelector('[name="user-' + n + '"]');

			function fieldError(n, msg) {
				const inp = field(n);
				if (!inp) return;
				const out = form.querySelector('#' + inp.id + '-err');
				inp.setAttribute('aria-invalid', msg ? 'true' : 'false');
				if (out) out.textContent = msg || '';
			}

			form.addEventListener('submit', e => {
				e.preventDefault();
				const action = form.dataset.action;
				const vals = { name: field('name').value.trim(), email: field('email').value.trim(), phone: field('phone') ? field('phone').value.trim() : '' };

				const bad = {};
				if (vals.name.length < 2) bad.name = 'Please enter your name.';
				if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(vals.email)) bad.email = 'Please enter a valid email address.';
				if (action === 'estimate') {
					const d = vals.phone.replace(/\D/g, '').replace(/^1(?=\d{10}$)/, '');
					if (d.length !== 10) bad.phone = 'Please enter a 10-digit phone number.';
				}
				['name', 'email', 'phone'].forEach(n => fieldError(n, bad[n]));
				if (Object.keys(bad).length) {
					status.className = 'estimator-status is-error';
					status.textContent = 'Please check the highlighted fields.';
					field(Object.keys(bad)[0]).focus();
					return;
				}

				submit.disabled = true;
				status.className = 'estimator-status is-busy';
				status.textContent = 'Sending…';

				post('contact', Object.assign({ action, t: state.t, sig: state.sig, hp: form.querySelector('[name="hp"]').value }, vals))
					.then(j => {
						if (j.ok) {
							status.className = 'estimator-status is-success';
							status.textContent = j.message;
							submit.hidden = true;
							form.querySelectorAll('.form-input').forEach(fi => fi.hidden = true);
							status.setAttribute('tabindex', '-1');
							status.focus({ preventScroll: true });
							return;
						}
						Object.entries(j.invalid || {}).forEach(([n, m]) => fieldError(n, m));
						throw new Error(j.message || '');
					})
					.catch(err => {
						status.className = 'estimator-status is-error';
						status.textContent = err.message || ('Sorry, that didn\'t go through. Please try again, or call us at ' + cfg.phone + '.');
					})
					.finally(() => { submit.disabled = false; });
			});
		});

		/* ---------- start / resume ---------- */
		const p = path(state.answers);
		if (state.done && p.ids.every(id => state.answers[id] != null)) getQuote(false);
		else showQuestion(p.ids.includes(state.current) ? state.current : (p.ids.find(id => state.answers[id] == null) || Q[0].id), false);
	}
});
