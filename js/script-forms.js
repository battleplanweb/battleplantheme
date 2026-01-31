document.addEventListener("DOMContentLoaded", function () {	"use strict";
														   
// Raw Script: Forms (Multi-Step)														   
										
	const wrap = getObject('.cf7-steps');
	const form = wrap ? getObject('form', wrap) : null;
	if(!form) return;

	initMultiStep(form, wrap);

	function initMultiStep(form, wrap) {
		const scope = wrap.closest('article') || wrap.parentElement || document;
		const clipRect = getObject('#cf7LogoClip rect', scope) || null;
		const barEl = getObject('.cf7-progress__bar > span', scope) || getObject('.cf7-progress__bar', scope) || null;

		const steps = [...form.querySelectorAll('.cf7-step')];
		const isIncluded = el => el && el.classList && el.classList.contains('cf7-step-included');
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
			const prevBtn = (inc && inc.querySelector('.cf7-prev')) || base.querySelector('.cf7-prev');
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
			if(e.target.classList.contains('cf7-next')){
				e.preventDefault();
				if (validateStep(i)) { started = true; i = Math.min(i+1, steps.length-1); show(i); }
				animateScroll('.cf7-progress');
			}
			if(e.target.classList.contains('cf7-prev')){
				e.preventDefault();
				i = Math.max(i-1, 0); show(i);
				animateScroll('.cf7-progress');
			}
		});

		form.addEventListener('submit', e=>{
			const last = steps.length-1;
			if (i===last){
				if (!validateStep(i)){ e.preventDefault(); return; }
				paint(true); 
			} else {
				e.preventDefault();
				started = true;
				i = Math.min(i+1,last); show(i);
			}			
		});

		form.addEventListener('wpcf7mailsent', ()=> { i=0; started=false; show(i); });

		show(0);
	}
});	