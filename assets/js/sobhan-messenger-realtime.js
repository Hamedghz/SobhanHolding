(async () => {
  const root = document.querySelector('[data-messenger]');
  if (!root) return;
  try {
    const response = await fetch('/api/messenger/socket-token.php');
    const json = await response.json();
    const config = json.data;
    if (!json.ok || !config?.realtime_url) return;
    await new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = config.realtime_url.replace(/\/$/, '') + '/socket.io/socket.io.js';
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
    const socket = io(config.realtime_url, { auth: { token: config.token }, transports: ['websocket', 'polling'] });
    window.SobhanMessengerSocket = socket;
    const conversation = () => Number(new URLSearchParams(location.search).get('conversation') || 0);
    const refresh = () => document.querySelector('.sm-thread.active')?.click();
    const join = () => { if (conversation()) socket.emit('conversation:join', conversation()); };
    socket.on('connect', join);
    socket.on('message:new', refresh);
    socket.on('message:edited', refresh);
    socket.on('message:deleted', refresh);
    socket.on('reaction:updated', refresh);
    socket.on('receipt:read', refresh);
    socket.on('location:updated', refresh);

    const form = root.querySelector('[data-composer]');
    const input = root.querySelector('[data-body]');
    form.addEventListener('submit', event => {
      if (!socket.connected || !conversation() || !input.value.trim()) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      input.disabled = true;
      socket.emit('message:send', { conversation_id: conversation(), body: input.value.trim() }, result => {
        input.disabled = false;
        if (result?.ok) { input.value = ''; refresh(); }
        else alert(result?.message || 'ارسال پیام انجام نشد.');
        input.focus();
      });
    }, true);
    let typingTimer;
    input.addEventListener('input', () => {
      if (!socket.connected || !conversation()) return;
      socket.emit('message:typing:start', { conversation_id: conversation(), typing: true });
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => socket.emit('message:typing:stop', { conversation_id: conversation(), typing: false }), 900);
    });
    socket.on('typing:update', data => {
      const meta = root.querySelector('[data-chat-meta]');
      if (Number(data.conversation_id) === conversation() && data.user_id !== window.SobhanUserId) {
        const previous = meta.textContent;
        meta.textContent = data.typing === false ? previous : 'در حال نوشتن…';
        if (data.typing !== false) setTimeout(() => { if (meta.textContent === 'در حال نوشتن…') meta.textContent = previous; }, 1200);
      }
    });
    window.addEventListener('popstate', join);
  } catch {
    console.info('Sobhan realtime fallback is active.');
  }
})();
