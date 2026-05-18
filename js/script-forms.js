document.addEventListener("DOMContentLoaded", function () {	"use strict";

// Raw Script: Forms (Multi-Step + Fetch Submission)

	// Submission handler — applies to ALL .bp-form-el forms (steps optional)
	document.querySelectorAll('form.bp-form-el').forEach(form => {
		// Skip if this form is inside a multi-step wrapper — that path is handled below
		const inSteps = form.closest('.bp-form-steps');
		if (!inSteps) bindFetchSubmit(form);
	});

	// Multi-step wrapper
	const wrap = getObject('.bp-form-steps');
	const form = wrap ? getObject('form', wrap) : null;
	if (form) {
		bindFetchSubmit(form);
		initMultiStep(form, wrap);
	}

	function bindFetchSubmit(form) {
		form.addEventListener('submit', e => {
			// In multi-step forms the submit handler may already have prevented default
			// when not yet on the last step. Only intercept when it would otherwise post.
			if (e.defaultPrevented) return;
			e.preventDefault();
			submitViaFetch(form);
		});
	}

	function submitViaFetch(form) {
		setFormState(form, 'submitting');
		clearResponse(form);

		const fd = new FormData(form);
		const url = '/wp-json/bp/v1/contact';

		fetch(url, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json' }
		})
		.then(r => r.json().then(d => ({ ok: r.ok, data: d })))
		.then(({ ok, data }) => {
			if (!ok || !data || !data.status) {
				setFormState(form, 'failed');
				showResponse(form, 'Sorry, something went wrong. Please try again or call us.');
				return;
			}
			if (data.status === 'mail_sent') {
				form.dispatchEvent(new CustomEvent('bp:form:sent', { bubbles: true, detail: data }));
				if (data.redirect) {
					// Keep .submitting state so the spinner stays visible until the new page loads
					window.location.href = data.redirect;
				} else {
					setFormState(form, 'mail-sent-ok');
					showResponse(form, data.message || 'Thank you. Your message has been sent.');
					form.reset();
				}
			} else if (data.status === 'validation_failed') {
				setFormState(form, 'invalid');
				showResponse(form, data.message || 'Please correct the highlighted fields.');
				if (Array.isArray(data.invalid)) {
					data.invalid.forEach(name => {
						const el = form.querySelector('[name="' + name + '"]');
						if (el) el.classList.add('bp-not-valid');
					});
				}
			} else {
				setFormState(form, 'failed');
				showResponse(form, data.message || 'Sorry, your message could not be sent.');
			}
		})
		.catch(() => {
			setFormState(form, 'failed');
			showResponse(form, 'Network error. Please check your connection and try again.');
		});
	}

	function setFormState(form, state) {
		form.classList.remove('init', 'submitting', 'invalid', 'mail-sent-ok', 'failed', 'validating');
		form.classList.add(state);
	}

	function showResponse(form, msg) {
		const out = form.querySelector('.bp-response');
		if (out) out.textContent = msg;
	}

	function clearResponse(form) {
		const out = form.querySelector('.bp-response');
		if (out) out.textContent = '';
		form.querySelectorAll('.bp-not-valid').forEach(el => el.classList.remove('bp-not-valid'));
	}

	function initMultiStep(form, wrap) {
		const scope = wrap.closest('article') || wrap.parentElement || document;
		const clipRect = getObject('#bpLogoClip rect', scope) || null;
		const barEl = getObject('.bp-progress__bar > span', scope) || getObject('.bp-progress__bar', scope) || null;

		const steps = [...form.querySelectorAll('.bp-step')];
		const isIncluded = el => el && el.classList && el.classList.contains('bp-step-included');
		const nextInc = el => isIncluded(el.nextElementSibling) ? el.nextElementSibling : null;
		const toggle = (el,on)=> on ? (el.removeAttribute('hidden'), el.style.display='') : (el.setAttribute('hidden',''), el.style.display='none');

		// --- weights: read from step OR its included sibling; else equal split
		const weights = (() => {
			const raw = steps.map(s => {
				const inc = nextInc(s);
				const wEl = s.hasAttribute('data-progress') ? s : (inc && inc.hasAttribute('data-progress') ? inc : null);
				if(!wEl) return null;
				const str = (wEl.getAttribute('data-progress') || '').trim();
				const n = str ? parseFloat(str.replace('%','')) : NaN;
				return Number.isFinite(n) && n >= 0 ? n : null;
			});

			const specified = raw.filter(v=>v!=null), unspecifiedCount = raw.length - specified.length;
			if(!specified.length) return raw.map(()=> 100/raw.length);

			const sumSpecified = specified.reduce((a,b)=>a+b,0);
			const fillEach = unspecifiedCount ? (100 - sumSpecified) / unspecifiedCount : 0;
			let w = raw.map(v => v==null ? fillEach : v);

			const total = w.reduce((a,b)=>a+b,0);
			return (!Number.isFinite(total) || total===0) ? raw.map(()=>100/raw.length) : w.map(v=> (v/total)*100);
		})();

		let i = 0, started = false;

		const completedPct = idx => {
			let s = 0; for(let n=0;n<idx;n++) s += weights[n] || 0;
			return Math.max(0, Math.min(100, +s.toFixed(4)));
		};

		if (clipRect) {
			clipRect.style.transformOrigin='left center';
			clipRect.style.transition='transform 2s ease';
			clipRect.style.transform='scaleX(1)';
			setTimeout(()=> clipRect.style.transform='scaleX(0)', 2000);
		}

		const paint = (final=false) => {
			const pct = final ? 100 : (started ? completedPct(i) : 0);
			clipRect ? (clipRect.style.transform = `scaleX(${pct/100})`) : null;
			barEl ? (barEl.style.width = pct + '%') : null;
		};

		const show = idx => {
			steps.forEach((s,n)=> {
				const on = n===idx;
				toggle(s,on);
				const inc = nextInc(s); inc ? toggle(inc,on) : null;
			});
			wrap.dataset.current = idx;

			const base = steps[idx], inc = nextInc(base);
			const prevBtn = (inc && inc.querySelector('.bp-prev')) || base.querySelector('.bp-prev');
			prevBtn ? prevBtn.disabled = idx===0 : null;

			paint();
		};

		const validateStep = idx => {
			const base = steps[idx], inc = nextInc(base);
			const fields = [...base.querySelectorAll('input,select,textarea'), ...(inc ? [...inc.querySelectorAll('input,select,textarea')] : [])];
			let ok = true, firstBad = null;
			fields.forEach(el=>{
				const hidden = el.offsetParent ? false : true;
				(hidden || el.disabled) ? null : (!el.checkValidity() ? (ok=false, firstBad = firstBad || el) : null);
			});
			!ok && firstBad ? firstBad.reportValidity() : null;
			return ok;
		};

		form.addEventListener('click', e => {
			if(e.target.classList.contains('bp-next')){
				e.preventDefault();
				if (validateStep(i)) { started = true; i = Math.min(i+1, steps.length-1); show(i); }
				animateScroll('.bp-progress');
			}
			if(e.target.classList.contains('bp-prev')){
				e.preventDefault();
				i = Math.max(i-1, 0); show(i);
				animateScroll('.bp-progress');
			}
		});

		form.addEventListener('submit', e=>{
			const last = steps.length-1;
			if (i===last){
				if (!validateStep(i)){ e.preventDefault(); return; }
				paint(true);
				// fall through — bindFetchSubmit will handle the actual POST
			} else {
				e.preventDefault();
				started = true;
				i = Math.min(i+1,last); show(i);
			}
		});

		form.addEventListener('bp:form:sent', ()=> { i=0; started=false; show(i); });

		show(0);
	}
});
