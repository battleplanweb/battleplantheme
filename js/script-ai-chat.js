/* Battle Plan Web Design — AI Chat widget (Phase 1)
 *
 * Floating launcher → chat panel. Talks to /wp-json/bp-chat/v1/message.
 * Keeps the full transcript in memory + sessionStorage (so a page nav
 * within the visit doesn't lose the conversation). Vanilla JS only.
 *
 * Config comes from the localized `bpChat` object:
 *   restUrl, company, greeting, launcher, consent
 */
(function () {
	if (typeof bpChat === 'undefined' || !bpChat.restUrl) return;

	var root = document.getElementById('bp-chat-root');
	if (!root) return;

	var STORE_KEY = 'bp_chat_v1';
	var messages = [];          // [{role, content}] — visible transcript
	var cid = '';
	var open = false;
	var busy = false;
	var leadSent = false;

	// ---- Conversation id + restore -------------------------------------
	function loadState() {
		try {
			var saved = JSON.parse(sessionStorage.getItem(STORE_KEY) || '{}');
			if (saved.cid) cid = saved.cid;
			if (Array.isArray(saved.messages)) messages = saved.messages;
			if (saved.leadSent) leadSent = true;
		} catch (e) {}
		if (!cid) cid = 'c-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
	}
	function saveState() {
		try {
			sessionStorage.setItem(STORE_KEY, JSON.stringify({ cid: cid, messages: messages, leadSent: leadSent }));
		} catch (e) {}
	}

	// ---- DOM build ------------------------------------------------------
	var launcher, panel, log, input, sendBtn;

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (text != null) n.textContent = text;
		return n;
	}

	function build() {
		launcher = el('button', 'bp-chat-launcher');
		launcher.type = 'button';
		launcher.setAttribute('aria-label', bpChat.launcher || 'Chat with us');
		launcher.innerHTML = '<span class="bp-chat-launcher-icon" aria-hidden="true">' +
			'<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
			'<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4v-4H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z" ' +
			'fill="currentColor"/></svg></span>';
		launcher.addEventListener('click', toggle);

		panel = el('div', 'bp-chat-panel');
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-label', (bpChat.company || 'Chat') + ' chat');

		var head = el('div', 'bp-chat-head');
		head.appendChild(el('span', 'bp-chat-title', bpChat.company || 'Chat'));
		var close = el('button', 'bp-chat-close', '×');
		close.type = 'button';
		close.setAttribute('aria-label', 'Close chat');
		close.addEventListener('click', toggle);
		head.appendChild(close);

		log = el('div', 'bp-chat-log');
		log.setAttribute('aria-live', 'polite');

		var form = el('form', 'bp-chat-form');
		input = el('textarea', 'bp-chat-input');
		input.rows = 1;
		input.placeholder = 'Type your message…';
		input.setAttribute('aria-label', 'Type your message');
		input.addEventListener('input', autoGrow);
		input.addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(); }
		});

		sendBtn = el('button', 'bp-chat-send', 'Send');
		sendBtn.type = 'submit';

		form.appendChild(input);
		form.appendChild(sendBtn);
		form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });

		var consent = el('div', 'bp-chat-consent', bpChat.consent || '');

		panel.appendChild(head);
		panel.appendChild(log);
		panel.appendChild(form);
		panel.appendChild(consent);

		root.appendChild(panel);
		root.appendChild(launcher);
	}

	// ---- Rendering ------------------------------------------------------
	function addBubble(role, text) {
		var wrap = el('div', 'bp-chat-msg bp-chat-' + role);
		wrap.appendChild(el('div', 'bp-chat-bubble', text));
		log.appendChild(wrap);
		log.scrollTop = log.scrollHeight;
		return wrap;
	}

	function renderAll() {
		log.innerHTML = '';
		// Greeting is always shown first. It's UI-only — never pushed into `messages`,
		// so it's never sent to the API — then any stored transcript follows.
		addBubble('assistant', bpChat.greeting || 'Hi! How can I help you today?');
		messages.forEach(function (m) { addBubble(m.role, m.content); });
	}

	// ---- Consent card (explicit SMS opt-in) ----------------------------
	// Shown when the server signals request_consent. The visitor must tap Yes
	// before any text is sent — the click is the A2P-verifiable opt-in. The
	// phone number lives server-side, so the card only carries the choice.
	function showConsentCard() {
		var wrap = el('div', 'bp-chat-msg bp-chat-assistant');
		var card = el('div', 'bp-chat-consent-card');

		card.appendChild(el('p', 'bp-chat-consent-disclosure', bpChat.textConsent || ''));

		if (bpChat.privacyUrl || bpChat.termsUrl) {
			var links = el('p', 'bp-chat-consent-links');
			if (bpChat.privacyUrl) {
				var a = el('a', null, 'Privacy Policy');
				a.href = bpChat.privacyUrl; a.target = '_blank'; a.rel = 'noopener';
				links.appendChild(a);
			}
			if (bpChat.privacyUrl && bpChat.termsUrl) links.appendChild(document.createTextNode(' · '));
			if (bpChat.termsUrl) {
				var t = el('a', null, 'Terms');
				t.href = bpChat.termsUrl; t.target = '_blank'; t.rel = 'noopener';
				links.appendChild(t);
			}
			card.appendChild(links);
		}

		var btns = el('div', 'bp-chat-consent-btns');
		var yes = el('button', 'bp-chat-consent-yes', bpChat.consentYes || 'Yes, text me');
		var no  = el('button', 'bp-chat-consent-no', bpChat.consentNo || 'No thanks');
		yes.type = 'button'; no.type = 'button';
		btns.appendChild(yes); btns.appendChild(no);
		card.appendChild(btns);

		wrap.appendChild(card);
		log.appendChild(wrap);
		log.scrollTop = log.scrollHeight;

		function choose(choice) {
			yes.disabled = true; no.disabled = true;
			fetch(bpChat.consentUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({ cid: cid, choice: choice })
			})
				.then(function (r) { return r.json(); })
				.then(function (j) {
					wrap.remove();
					if (j && j.reply) {
						messages.push({ role: 'assistant', content: j.reply });
						addBubble('assistant', j.reply);
						saveState();
					}
				})
				.catch(function () {
					wrap.remove();
					addBubble('assistant', 'Sorry — something went wrong. Please call us instead.');
				});
		}
		yes.addEventListener('click', function () { choose('yes'); });
		no.addEventListener('click', function () { choose('no'); });
	}

	function showTyping() {
		var wrap = el('div', 'bp-chat-msg bp-chat-assistant bp-chat-typing');
		var b = el('div', 'bp-chat-bubble');
		b.innerHTML = '<span></span><span></span><span></span>';
		wrap.appendChild(b);
		log.appendChild(wrap);
		log.scrollTop = log.scrollHeight;
		return wrap;
	}

	// ---- Behavior -------------------------------------------------------
	function toggle() {
		open = !open;
		root.classList.toggle('bp-chat-open', open);
		if (open) {
			setTimeout(function () { input.focus(); }, 50);
		}
	}

	function autoGrow() {
		input.style.height = 'auto';
		input.style.height = Math.min(input.scrollHeight, 120) + 'px';
	}

	function setBusy(state) {
		busy = state;
		sendBtn.disabled = state;
		input.disabled = state;
	}

	function submit() {
		if (busy) return;
		var text = input.value.trim();
		if (!text) return;

		input.value = '';
		autoGrow();

		messages.push({ role: 'user', content: text });
		addBubble('user', text);
		saveState();

		setBusy(true);
		var typing = showTyping();

		fetch(bpChat.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: JSON.stringify({ cid: cid, messages: messages })
		})
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				typing.remove();
				if (!res.ok || !res.j || !res.j.reply) {
					var msg = (res.j && res.j.error) ? res.j.error : 'Sorry — something went wrong. Please call us instead.';
					addBubble('assistant', msg);
					return;
				}
				messages.push({ role: 'assistant', content: res.j.reply });
				addBubble('assistant', res.j.reply);
				if (res.j.lead_sent) leadSent = true;
				saveState();
				// The AI asked the visitor to opt in to texts — show the consent card.
				if (res.j.request_consent) showConsentCard();
			})
			.catch(function () {
				typing.remove();
				addBubble('assistant', 'Sorry — I could not reach the server. Please call us instead.');
			})
			.finally(function () {
				setBusy(false);
				input.focus();
			});
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	// ---- Init -----------------------------------------------------------
	loadState();
	build();
	renderAll(); // paint the greeting (or restored transcript) up front
})();
