/** Small, dependency-free chat client. All business rules live on the server. */
(function () {
	'use strict';
	if (!window.SHU_CHAT_CONFIG || !document.body) return;

	var config = window.SHU_CHAT_CONFIG;
	var isSpanish = /^es\b/i.test(navigator.language || 'en');
	var history = [];
	var sessionType = 'unknown';
	var waiting = false;
	var opened = false;
	var choices;
	var sessionId = Array.from(crypto.getRandomValues(new Uint8Array(16)), function (n) {
		return n.toString(16).padStart(2, '0');
	}).join('');
	var word = function (en, es) { return isSpanish ? es : en; };
	var root = document.createElement('div');
	root.id = 'shu-chat-widget';
	root.className = config.position === 'bottom-right' ? 'position-bottom-right' : 'position-bottom-left';
	var colors = {
		triggerBg: '--shu-chat-trigger-bg', triggerIconColor: '--shu-chat-trigger-icon',
		headerBg: '--shu-chat-header-bg', headerTextColor: '--shu-chat-header-text',
		userBubbleBg: '--shu-chat-user-bg', userBubbleText: '--shu-chat-user-text',
		botBubbleBg: '--shu-chat-bot-bg', botBubbleText: '--shu-chat-bot-text',
		sendButtonColor: '--shu-chat-send-btn', fontFamily: '--shu-chat-font'
	};
	Object.keys(colors).forEach(function (key) {
		if (config[key]) root.style.setProperty(colors[key], config[key]);
	});
	if (Number.isInteger(Number(config.cornerRadius))) {
		root.style.setProperty('--shu-chat-radius', Math.max(0, Math.min(30, Number(config.cornerRadius))) + 'px');
	}

	root.innerHTML = '<button type="button" class="shu-chat-trigger" aria-controls="shu-chat-panel" aria-expanded="false" aria-label="' + word('Open security assistant', 'Abrir asistente de seguridad') + '">' +
		'<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.2L4 17.2V4h16v12z"/></svg></button>' +
		'<section id="shu-chat-panel" class="shu-chat-panel" role="dialog" aria-modal="true" aria-label="' + word('Safety Host Unit assistant', 'Asistente de Safety Host Unit') + '" aria-hidden="true" inert>' +
		'<div class="shu-chat-header"><div class="shu-chat-header-info"><div class="shu-chat-title"></div><div class="shu-chat-subtitle"></div></div>' +
		'<button type="button" class="shu-chat-close" aria-label="' + word('Close chat', 'Cerrar chat') + '">&times;</button></div>' +
		'<div class="shu-chat-messages" role="log" aria-live="polite" aria-relevant="additions text"></div>' +
		'<div class="shu-chat-footer"><input type="text" class="shu-chat-input" maxlength="2000" autocomplete="off" placeholder="' + word('Type your message…', 'Escriba su mensaje…') + '" aria-label="' + word('Message', 'Mensaje') + '">' +
		'<button type="button" class="shu-chat-send" aria-label="' + word('Send message', 'Enviar mensaje') + '">&#10148;</button></div>' +
		'<div class="shu-chat-disclosure"></div></section>';
	document.body.appendChild(root);

	var trigger = root.querySelector('.shu-chat-trigger');
	var panel = root.querySelector('.shu-chat-panel');
	var close = root.querySelector('.shu-chat-close');
	var input = root.querySelector('.shu-chat-input');
	var send = root.querySelector('.shu-chat-send');
	var messages = root.querySelector('.shu-chat-messages');
	root.querySelector('.shu-chat-title').textContent = isSpanish && config.headerTitleEs ? config.headerTitleEs : config.headerTitle || 'Safety Host Unit';
	root.querySelector('.shu-chat-subtitle').textContent = isSpanish && config.headerSubtitleEs ? config.headerSubtitleEs : config.headerSubtitle || 'Virtual assistant';
	var disclosure = root.querySelector('.shu-chat-disclosure');
	disclosure.textContent = word('Chats are stored for follow-up. Please avoid sharing sensitive details. ', 'Guardamos las conversaciones para dar seguimiento. Evite compartir datos sensibles. ');
	if (config.privacyUrl) {
		var policy = document.createElement('a');
		policy.href = config.privacyUrl;
		policy.textContent = word('Privacy policy', 'Política de privacidad');
		disclosure.appendChild(policy);
	}

	function bubble(role, value) {
		var item = document.createElement('div');
		item.className = 'shu-msg-bubble ' + role;
		item.textContent = value;
		messages.appendChild(item);
		messages.scrollTop = messages.scrollHeight;
	}

	function openPanel() {
		opened = true;
		panel.removeAttribute('inert');
		panel.setAttribute('aria-hidden', 'false');
		root.classList.add('is-open');
		trigger.setAttribute('aria-expanded', 'true');
		trigger.setAttribute('aria-label', word('Close security assistant', 'Cerrar asistente de seguridad'));
		if (!messages.children.length) {
			bubble('bot', isSpanish && config.greetingEs ? config.greetingEs : config.greeting || 'How can I help you today?');
			renderChoices();
		}
		input.focus();
	}

	function closePanel() {
		opened = false;
		root.classList.remove('is-open');
		panel.setAttribute('aria-hidden', 'true');
		panel.setAttribute('inert', '');
		trigger.setAttribute('aria-expanded', 'false');
		trigger.setAttribute('aria-label', word('Open security assistant', 'Abrir asistente de seguridad'));
		trigger.focus();
	}

	function renderChoices() {
		choices = document.createElement('div');
		choices.className = 'shu-fastpath-choices';
		[
			['existing', word('Existing client', 'Soy cliente existente')],
			['new', word('New security inquiry', 'Nueva consulta de seguridad')]
		].forEach(function (pair) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'shu-fastpath-btn';
			button.textContent = pair[1];
			button.addEventListener('click', function () {
				choices.remove();
				choices = null;
				sessionType = pair[0];
				bubble('user', pair[1]);
				if (sessionType === 'existing') {
					request(pair[1]);
				} else {
					bubble('bot', word('What type of security service do you need?', '¿Qué tipo de servicio de seguridad necesita?'));
					input.focus();
				}
			});
			choices.appendChild(button);
		});
		messages.appendChild(choices);
	}

	function request(text) {
		if (waiting) return;
		waiting = true;
		input.disabled = true;
		send.disabled = true;
		var typing = document.createElement('div');
		typing.className = 'shu-typing-indicator';
		typing.setAttribute('aria-label', word('Assistant is typing', 'El asistente está escribiendo'));
		typing.innerHTML = '<span class="shu-typing-dot"></span><span class="shu-typing-dot"></span><span class="shu-typing-dot"></span>';
		messages.appendChild(typing);
		messages.scrollTop = messages.scrollHeight;

		fetch(config.restUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ message: text, history: history.slice(-12), session_id: sessionId,
				session_type: sessionType, lang: navigator.language || 'en' })
		}).then(function (response) {
			if (!response.ok) throw new Error('Request failed');
			return response.json();
		}).then(function (data) {
			if (!data || !data.reply) throw new Error('Empty reply');
			bubble('bot', data.reply);
			if (!data.error) {
				history.push({ role: 'user', content: text }, { role: 'assistant', content: data.reply });
				history = history.slice(-12);
			}
		}).catch(function () {
			bubble('bot', word('The chat is unavailable right now. Please call (888) 703-4004.', 'El chat no está disponible. Llame al (888) 703-4004.'));
		}).finally(function () {
			typing.remove();
			waiting = false;
			input.disabled = false;
			send.disabled = false;
			if (opened) input.focus();
		});
	}

	function sendMessage() {
		var text = input.value.trim();
		if (!text || waiting) return;
		if (choices) { choices.remove(); choices = null; }
		if (sessionType === 'unknown') {
			sessionType = /^(?:i am an |i'm an |soy )?(?:existing client|cliente existente)\b/i.test(text) ? 'existing' : 'new';
		}
		bubble('user', text);
		input.value = '';
		request(text);
	}

	trigger.addEventListener('click', function () { opened ? closePanel() : openPanel(); });
	close.addEventListener('click', closePanel);
	send.addEventListener('click', sendMessage);
	input.addEventListener('keydown', function (event) {
		if (event.key === 'Enter' && !event.isComposing) { event.preventDefault(); sendMessage(); }
	});
	panel.addEventListener('keydown', function (event) {
		if (event.key !== 'Tab') return;
		var focusable = Array.from(panel.querySelectorAll('button:not(:disabled), input:not(:disabled), a[href]'));
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
	});
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && opened) closePanel();
	});
})();
