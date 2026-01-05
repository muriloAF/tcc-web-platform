// ==================== MENU RESPONSIVO ====================
const menuBtn = document.getElementById("menu-btn");
const menu = document.getElementById("menu");
if (menuBtn && menu) {
  menuBtn.addEventListener("click", () => menu.classList.toggle("show"));
}

document.addEventListener('DOMContentLoaded', () => {
  const chatListEl = document.getElementById("chat-list");
  const messagesEl = document.getElementById("chat-messages");
  const userNameEl = document.getElementById("chat-user-name");
  const userPhotoEl = document.getElementById("chat-user-photo");
  const userStatusEl = document.getElementById("chat-user-status");
  const searchInput = document.getElementById("search-input");
  const messageInput = document.getElementById("message-input");
  const sendBtn = document.getElementById("send-btn");
  const attachBtn = document.getElementById("attach-btn");
  const fileInput = document.getElementById("file-input");


  let chats = [];
  let activeChatId = null;
  let chatHistories = {};
  let currentUserId = null;
  let pollingTimer = null;
  let lastMsgIdMap = {};
  let heartbeatTimer = null;
  let presencePollTimer = null;
  let dotsTimer = null;
  let dotsStep = 0;
  let lastTypingPing = 0;
  let typingAnimating = false;

  // tenta restaurar estado salvo no navegador
  try {
    const saved = JSON.parse(localStorage.getItem("chat_state"));
    if (saved) {
      chats = saved.chats || [];
      chatHistories = saved.chatHistories || {};
      activeChatId = saved.activeChatId || null;
      lastMsgIdMap = saved.lastMsgIdMap || {};
    }
  } catch (_) {}
  // Sincroniza com window para quem usa fora do escopo
  window.chats = chats;
  window.chatHistories = chatHistories;
  window.activeChatId = activeChatId;

  function saveState() {
    localStorage.setItem(
      "chat_state",
      JSON.stringify({ chats, chatHistories, activeChatId, lastMsgIdMap })
    );
  }

  async function sendAttachment(idPara, file, textoOptional='') {
    try {
      const fd = new FormData();
      fd.append('id_para', idPara);
      if (textoOptional) fd.append('texto', textoOptional);
      fd.append('arquivo', file);
      const resp = await fetch('../php/sendAttachment.php', {
        method: 'POST',
        credentials: 'same-origin',
        body: fd
      });
      return await resp.json();
    } catch (e) {
      console.error('sendAttachment erro:', e);
      return { ok:false, erro:e.message };
    }
  }

  // Helper: verifica se a última mensagem conhecida do chat veio de mim
  function lastMessageIsFromMe(chatId, lastId) {
    try {
      const hist = chatHistories[chatId];
      if (!Array.isArray(hist) || hist.length === 0) return false;
      const last = hist[hist.length - 1];
      if (lastId && typeof lastId === 'number') {
        // se temos id, tenta achar exatamente
        const found = hist.find(m => Number(m.id) === Number(lastId));
        if (found) return found.from === 'me';
      }
      // fallback: compara com o último conhecido
      return last.from === 'me';
    } catch (_) { return false; }
  }

  function escapeHtml(str) {
    if (!str) return "";
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  // Converte URLs simples em links estilizados; evita quebrar HTML usando escape antes
  function linkify(raw) {
    if (!raw) return '';
    // Mantém newlines
    const parts = raw.split(/(https?:\/\/[^\s]+)/g);
    return parts.map(p => {
      if (/^https?:\/\//i.test(p)) {
        const safe = escapeHtml(p);
        return `<span class="link-card" data-url="${safe}"><a href="${safe}" target="_blank" rel="noopener">${safe}</a></span>`;
      }
      return escapeHtml(p);
    }).join('');
  }

  // fetchChatList: manter lastMessage atualizado e sinalizar não lidas
  // Marca supressão de som ao clicar botões
  window.__SUPPRESS_SOUND_TS = 0;
  function suppressSound(ms=900){ window.__SUPPRESS_SOUND_TS = Date.now()+ms; }
  if (sendBtn && !sendBtn.__sndSup) { sendBtn.__sndSup = true; sendBtn.addEventListener('click', ()=>suppressSound()); }
  if (attachBtn && !attachBtn.__sndSup){ attachBtn.__sndSup = true; attachBtn.addEventListener('click', ()=>suppressSound()); }

  async function fetchChatList() {
    const resp = await fetch('../php/getChatList.php', { credentials:'same-origin', cache:'no-store' });
    if (!resp.ok) throw new Error('HTTP ' + resp.status + ' - ' + (await resp.text()));
    const data = await resp.json();
    if (!data || !data.ok) throw new Error('fetchChatList erro: ' + (data?.erro || 'retorno inválido'));

    const prev = window.chats || [];
    const incoming = Array.isArray(data.chats) ? data.chats : [];
    // Detecta chats novos (sem mensagens ainda) para mostrar banner
    if (data.role && data.role !== window.chatRole) { window.chatRole = data.role; }
    const anyNewChat = incoming.some(c => c.newChat);
    if (anyNewChat) { showNewChatBanner(); } else { hideNewChatBanner(); }
    const updated = incoming.map(nc => {
      const exist = prev.find(p => p.id === nc.id) || {};
      const id = nc.id, isActive = (id === window.activeChatId);
      const isPlaceholder = !!nc.placeholder;
      const lastId   = nc.lastMessageId ?? exist.lastMessageId ?? null;
      const lastText = nc.lastMessage   ?? exist.lastMessage   ?? '';
      const lastTime = nc.lastMessageTime ?? exist.lastMessageTime ?? null;

      // Se estou no chat ativo e há msg nova, considero lida agora
      const lr0 = getLastReadId(id);
      if (isActive && lastId && lastId > lr0) setLastReadId(id, lastId);

      const lastRead = getLastReadId(id);
      const lastSent = getLastSentId(id);

      // Não lidas: só fora do chat ativo, depois do lastRead e não sendo sua própria última
      const wasUnread = Number(exist.unread || 0) > 0;
      const nowUnread = (!isActive && lastId && lastId > lastRead && lastId !== lastSent) ? 1 : 0;

      if (!wasUnread && nowUnread) {
        if (!(Date.now() < window.__SUPPRESS_SOUND_TS)) window.__SND__?.playNew();
      }

      return {
        id,
        chatId: (typeof nc.chatId !== 'undefined' ? nc.chatId : exist.chatId) || null,
        name: nc.name,
        photo: nc.photo,
        online: !!nc.online,
        lastMessage: lastText,
        lastMessageId: lastId,
        lastMessageTime: lastTime,
        unread: isPlaceholder ? 0 : nowUnread,
        hasNewMessage: isPlaceholder ? false : !!nowUnread,
        placeholder: isPlaceholder,
        placeholderText: isPlaceholder ? nc.lastMessage : null,
        messageCount: typeof nc.messageCount === 'number' ? nc.messageCount : null,
        newChat: !!nc.newChat
      };
    });

    // Ordena sempre por atividade (tempo > id) para ordem base
    updated.sort((a,b) => {
      const ta = a.lastMessageTime ? new Date(a.lastMessageTime).getTime() : 0;
      const tb = b.lastMessageTime ? new Date(b.lastMessageTime).getTime() : 0;
      if (tb !== ta) return tb - ta; // mais recente primeiro
      return (b.lastMessageId||0) - (a.lastMessageId||0);
    });
    window.chats = updated.length ? updated : prev; // mantém baseline
    window.renderChatList?.(window.chats); // filtro aplicado dentro de renderChatList

    // Se o ativo avançou no servidor, traga só novas e marque lido
    const a = window.activeChatId && (window.chats || []).find(c => c.id === window.activeChatId);
    if (a) {
      const hist = (window.chatHistories || {})[a.id] || [];
      const lastLocalId = hist.length ? (hist[hist.length-1].id || 0) : 0;
      if (a.lastMessageId && a.lastMessageId > lastLocalId && typeof refreshActiveChatOnce === 'function') {
        await refreshActiveChatOnce();
      }
    }
    return window.chats;
  }

  // ===== Notificação de novo chat disponível (sem mensagens) =====
  let newChatBannerEl = null;
  function ensureNewChatBanner(){
    if (!newChatBannerEl) {
      newChatBannerEl = document.createElement('div');
      newChatBannerEl.id = 'new-chat-banner';
      newChatBannerEl.style.position = 'absolute';
      newChatBannerEl.style.top = '0';
      newChatBannerEl.style.left = '0';
      newChatBannerEl.style.right = '0';
      newChatBannerEl.style.padding = '10px 14px';
      newChatBannerEl.style.background = '#dc2626';
      newChatBannerEl.style.color = '#fff';
      newChatBannerEl.style.fontSize = '14px';
      newChatBannerEl.style.fontWeight = '600';
      newChatBannerEl.style.textAlign = 'center';
      newChatBannerEl.style.zIndex = '50';
      newChatBannerEl.style.cursor = 'pointer';
      newChatBannerEl.style.boxShadow = '0 2px 6px rgba(0,0,0,.25)';
      newChatBannerEl.addEventListener('click', () => {
        const target = (window.chats||[]).find(c => c.newChat);
        if (target) {
          window.openChat?.(target.id, { force:true });
          target.newChat = false;
          hideNewChatBanner();
        }
      });
      const container = document.querySelector('#chat-container') || document.body;
      container.appendChild(newChatBannerEl);
    }
    let roleTxt = 'Novo chat disponível! Você pode iniciar conversa para acertar detalhes do serviço.';
    if (window.chatRole === 'cliente') roleTxt = 'O chat do prestador está disponível.';
    else if (window.chatRole === 'prestadora') roleTxt = 'O chat do cliente está disponível.';
    newChatBannerEl.textContent = roleTxt;
    return newChatBannerEl;
  }
  function showNewChatBanner(){ ensureNewChatBanner(); if (newChatBannerEl) newChatBannerEl.style.display='block'; }
  function hideNewChatBanner(){ if (newChatBannerEl) newChatBannerEl.style.display='none'; }

  async function fetchOpenChat(otherId) {
    try {
      const resp = await fetch(`../php/openChat.php?other_id=${encodeURIComponent(otherId)}`,{ credentials:'same-origin' });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      return await resp.json();
    } catch (err) {
      console.error("fetchOpenChat erro:", err);
      return null;
    }
  }

  async function sendMessage(idPara, conteudo) {
    try {
      const body = new URLSearchParams();
      body.append("id_para", idPara);
      body.append("conteudo", conteudo);
      const resp = await fetch("../php/sendMessage.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body,
      });
      return await resp.json();
    } catch (err) {
      console.error("sendMessage erro:", err);
      return { ok: false, erro: err.message };
    }
  }
  // ====== renderChatList (ordem base + filtro visual) ======
  function renderChatList(list) {
    const chatListEl = document.getElementById('chat-list');
    if (!chatListEl) return;
    const q = (searchInput && searchInput.value.trim().toLowerCase()) || '';
    let working = list;
    if (q) {
      working = list.filter(c => {
        const nameL = (c.name||'').toLowerCase();
        const msgL  = (c.lastMessage||'').toLowerCase();
        return nameL.includes(q) || msgL.includes(q);
      }).sort((a,b) => (a.name||'').localeCompare(b.name||'', 'pt-BR', { sensitivity:'base' }));
    }
    const desiredIds = new Set(working.map(c => String(c.id)));
    working.forEach(chat => {
      const idStr = String(chat.id);
      let item = chatListEl.querySelector(`.chat-item[data-chat-id='${CSS.escape(idStr)}']`);
      if (!item) {
        item = document.createElement('div');
        item.className = 'chat-item';
        item.dataset.chatId = idStr;
        chatListEl.appendChild(item);
        item.addEventListener('click', () => {
          const lastId = chat.lastMessageId || 0;
          if (lastId) setLastReadId(chat.id, lastId);
          const cRow = (chats || []).find(x => x.id === chat.id);
          if (cRow) { cRow.unread = 0; cRow.hasNewMessage = false; }
          window.openChat?.(chat.id, { force: true });
        });
      }
      if (chat.id === activeChatId) item.classList.add('active'); else item.classList.remove('active');
      let avatar = item.querySelector('.avatar');
      if (!avatar) { avatar = document.createElement('div'); avatar.className='avatar'; item.appendChild(avatar); }
      const bgUrl = chat.photo || '../img/SemFoto.jpg';
      if (!avatar.__bg || avatar.__bg !== bgUrl) { avatar.style.backgroundImage = `url('${bgUrl}')`; avatar.__bg = bgUrl; }
      let meta = item.querySelector('.meta');
      const nameHtml = escapeHtml(chat.name);
      let previewHtml = '';
      const hist = (window.chatHistories || {})[chat.id];
      const lastMsg = Array.isArray(hist) && hist.length ? hist[hist.length - 1] : null;
      if (lastMsg && lastMsg.tipo && lastMsg.tipo !== 'text') {
        const ext = (lastMsg.arquivo || '').split('/').pop()?.toLowerCase().split('.').pop() || '';
        const tipo = lastMsg.tipo;
        let emoji='📦', label='Arquivo';
        if (tipo==='image'){ emoji='🖼️'; label='Imagem'; }
        else if (tipo==='video'){ emoji='🎬'; label='Vídeo'; }
        else if (tipo==='audio'){ emoji='🎵'; label='Áudio'; }
        else if (tipo==='file') {
          if (['pdf'].includes(ext)) { emoji='📄'; label='PDF'; }
          else if (['doc','docx'].includes(ext)) { emoji='📝'; label='Documento'; }
          else if (['xls','xlsx'].includes(ext)) { emoji='📊'; label='Planilha'; }
          else if (['ppt','pptx'].includes(ext)) { emoji='📈'; label='Apresentação'; }
          else if (['txt'].includes(ext)) { emoji='📃'; label='Texto'; }
          else if (['zip','rar','7z'].includes(ext)) { emoji='🗜️'; label='Arquivo'; }
        }
        const caption = (lastMsg.text||'').trim();
        const safeCaption = caption ? ' – '+escapeHtml(caption.slice(0,40)) : '';
        previewHtml = `<i>${emoji} ${label}${safeCaption}</i>`;
      } else {
        previewHtml = escapeHtml(chat.lastMessage || '');
      }
      const metaHtml = `<h4>${nameHtml}</h4><p>${previewHtml}</p>`;
      if (!meta) { meta=document.createElement('div'); meta.className='meta'; meta.innerHTML=metaHtml; item.appendChild(meta); }
      else if (meta.__html !== metaHtml) { meta.innerHTML=metaHtml; meta.__html=metaHtml; }
      item.dataset.name = chat.name || '';
      item.dataset.lastMessage = (chat.lastMessage || '').toString();
      if (q) item.style.display=''; else item.style.display='';
      // Dot colors: purple for brand new empty chat, red for unread messages.
      const isNewEmpty = chat.newChat && (chat.messageCount === 0 || chat.lastMessageId == null);
      const shouldShowDot = chat.id !== activeChatId && (isNewEmpty || (chat.unread ?? 0) > 0 || chat.hasNewMessage);
      let dot = item.querySelector('.chat-dot');
      if (shouldShowDot && !dot) {
        dot = document.createElement('span');
        dot.className = 'chat-dot' + (isNewEmpty ? ' new-chat' : '');
        item.appendChild(dot);
      } else if (dot && shouldShowDot) {
        if (isNewEmpty) dot.classList.add('new-chat'); else dot.classList.remove('new-chat');
      } else if (!shouldShowDot && dot && !dot.classList.contains('fade-out')) {
        dot.classList.add('fade-out');
        const removeFn = () => { dot?.remove(); };
        dot.addEventListener('transitionend', removeFn, { once:true });
        setTimeout(removeFn, 600);
      }
    });
    // itens fora do working: se há filtro apenas oculta, senão remove
    [...chatListEl.querySelectorAll('.chat-item')].forEach(item => {
      const id = item.dataset.chatId;
      if (!desiredIds.has(id)) { if (q) item.style.display='none'; else item.remove(); }
    });
  }

  // ====== renderMessages ======
  function renderMessages(messages) {
    if (!messagesEl) return;
    messagesEl.innerHTML='';
    messages.forEach(m => {
      const div = document.createElement('div');
      div.classList.add('msg', m.from === 'me' ? 'outgoing' : 'incoming');
      div.style.whiteSpace='pre-wrap'; div.style.wordBreak='break-word'; div.style.overflowWrap='break-word';
      const tipo = m.tipo || 'text';
      let filePath = m.arquivo || null;
      if (filePath && !/^https?:\/\//i.test(filePath)) {
        if (!filePath.startsWith('/')) {
          filePath = filePath.replace(/^\.\.\/?/, '');
          if (filePath.startsWith('uploads/')) filePath = '../' + filePath;
        }
      }
      if (!filePath && tipo === 'text' && typeof m.text === 'string') {
        const markerMatch = m.text.match(/\[\[ATTACH:type=([^;]+);file=([^\]]+)\]\]/);
        if (markerMatch) {
          const mkTipo = markerMatch[1]; let mkFile = markerMatch[2];
          if (mkFile && !mkFile.startsWith('/')) {
            if (mkFile.startsWith('uploads/')) mkFile = '../' + mkFile;
          }
          filePath = mkFile; m.tipo = mkTipo; m.text = m.text.replace(/\[\[ATTACH:type=[^;]+;file=[^\]]+\]\]/,'').trim();
        }
      }
      if (m.tipo === 'image' && filePath) {
        div.classList.add('attachment');
        const wrap = document.createElement('div'); wrap.className='image-attachment';
        const img = document.createElement('img'); img.src=filePath; img.alt='imagem'; img.className='image-el';
        wrap.appendChild(img);
        if (m.text && m.text.trim() !== '') { const cap=document.createElement('div'); cap.className='att-caption'; cap.textContent=m.text.trim(); wrap.appendChild(cap); }
        div.appendChild(wrap);
      } else if (m.tipo === 'video' && filePath) {
        div.classList.add('attachment');
        const wrap=document.createElement('div'); wrap.className='video-attachment';
        const vid=document.createElement('video'); vid.src=filePath; vid.controls=true; vid.className='video-el'; wrap.appendChild(vid);
        if (m.text && m.text.trim() !== '') { const cap=document.createElement('div'); cap.className='att-caption'; cap.textContent=m.text.trim(); wrap.appendChild(cap); }
        div.appendChild(wrap);
      } else if (m.tipo === 'audio' && filePath) {
        div.classList.add('attachment','audio-msg');
        const wrap=document.createElement('div'); wrap.className='audio-attachment';
        const aud=document.createElement('audio'); aud.src=filePath; aud.controls=true; aud.className='audio-el'; wrap.appendChild(aud);
        if (m.text && m.text.trim()!==''){ const cap=document.createElement('div'); cap.className='att-caption'; cap.textContent=m.text.trim(); wrap.appendChild(cap);} div.appendChild(wrap);
      } else if (m.tipo === 'file' && filePath) {
        div.classList.add('attachment');
        const wrap=document.createElement('div'); wrap.className='file-attachment';
        const icon=document.createElement('span'); icon.className='fa-icon';
        const fname=(filePath.split('/').pop()||''); const lower=fname.toLowerCase(); const ext=lower.split('.').pop()||'';
        icon.textContent = ext==='pdf' ? '📄' : (['jpg','jpeg','png','gif','webp'].includes(ext) ? '🖼️' : '📦');
        const link=document.createElement('a'); link.href=filePath; link.target='_blank'; link.rel='noopener'; link.className='file-name'; link.textContent = fname; // sempre nome real
        const meta=document.createElement('span'); meta.className='file-meta';
        if (m.tamanho){ const kb=Math.max(1,Math.round(m.tamanho/1024)); meta.textContent=kb+' KB'; } else { meta.textContent=ext.toUpperCase(); }
        wrap.appendChild(icon); wrap.appendChild(link); wrap.appendChild(meta); div.appendChild(wrap);
        if (m.text && m.text.trim()!=='') { const cap=document.createElement('div'); cap.className='att-caption'; cap.textContent=m.text.trim(); div.appendChild(cap); }
      } else {
        const html = linkify(m.text || m.conteudo || '');
        div.innerHTML = html;
      }
      let timeStr='';
      if (m.enviado_em){ try { const d=new Date(m.enviado_em.replace(' ','T')); if(!isNaN(d.getTime())){ const hh=String(d.getHours()).padStart(2,'0'); const mm=String(d.getMinutes()).padStart(2,'0'); timeStr=hh+':'+mm; } } catch {}
        if (!timeStr){ const m2=m.enviado_em.match(/\b(\d{2}:\d{2})\b/); if(m2) timeStr=m2[1]; }
      }
      if (timeStr){ const t=document.createElement('span'); t.className='msg-time'; t.textContent=timeStr; div.appendChild(t); }
      messagesEl.appendChild(div);
      const spacer=document.createElement('div'); spacer.className='msg-spacer'; messagesEl.appendChild(spacer);
    });
    messagesEl.scrollTop = messagesEl.scrollHeight;
    setTimeout(()=>{ if(messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight; },50);
    requestAnimationFrame(()=>{ if(messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight; });
    upgradeLinkCards();
    // Garantir que intro permanece enquanto não houver mensagens reais
    if (messages.length === 0 && !messagesEl.querySelector('.system-intro')) {
      const intro = document.createElement('div');
      intro.className = 'msg system-intro';
      intro.textContent = 'Discuta sobre o serviço com o prestador ou cliente aqui.';
      intro.style.background = '#f3f4f6';
      intro.style.color = '#374151';
      intro.style.borderRadius = '12px';
      intro.style.padding = '10px 14px';
      intro.style.fontSize = '14px';
      intro.style.fontStyle = 'italic';
      intro.style.maxWidth = '640px';
      messagesEl.appendChild(intro);
    }
  }

  window.renderMessages = renderMessages;
  window.renderChatList = renderChatList;

  async function openChat(otherId) {
    if (!otherId && otherId !== 0) return;
    // Marca instante de abertura para suprimir sons imediatos
    window.__CHAT_OPEN_TS = Date.now();
    // Estende supressão de som usada em fetchChatList para evitar tocar ao abrir
    window.__SUPPRESS_SOUND_TS = Date.now() + 1800;
    // Detecta placeholder antes de qualquer chamada backend
    const placeholderChat = chats.find(c => c.id === otherId && c.placeholder);
    if (placeholderChat) {
      activeChatId = placeholderChat.id;
      window.activeChatId = activeChatId;
      // Renderiza mensagem placeholder sem buscar mensagens reais
      if (messagesEl) {
        messagesEl.innerHTML = '';
        const div = document.createElement('div');
        div.className = 'msg incoming';
        div.style.maxWidth = '640px';
        div.textContent = placeholderChat.placeholderText || placeholderChat.lastMessage || 'Chat indisponível.';
        messagesEl.appendChild(div);
      }
      if (userNameEl) userNameEl.textContent = placeholderChat.name || 'Avena';
      if (userPhotoEl) userPhotoEl.style.backgroundImage = `url('${placeholderChat.photo || '../img/avenaChat.png'}')`;
      // Desativa interação
      if (sendBtn) { sendBtn.disabled = true; sendBtn.style.opacity = '0.4'; }
      if (attachBtn) { attachBtn.disabled = true; attachBtn.style.opacity = '0.4'; }
      if (messageInput) { messageInput.disabled = true; messageInput.placeholder = 'Chat indisponível'; }
      // Marca como visto: remove newChat para tirar bolinha
      placeholderChat.newChat = false;
      renderChatList(chats);
      return; // Não prossegue para backend
    }

    if (!otherId) return;
    // Habilita botões de teste se existirem
    setTimeout(()=>{ if(typeof window.__enableTestButtons==='function'){ window.__enableTestButtons(); } }, 100);
    const data = await fetchOpenChat(otherId);
    if (!data || !data.ok) return;

    currentUserId = data.current_user_id ?? null;
    const currentRole = data.current_role || null;
    // Se mudou de role (login diferente) limpa estado antigo para não reaproveitar marcações 'me'
    if (window.chatRole && currentRole && window.chatRole !== currentRole) {
      chats = []; chatHistories = {}; activeChatId = null; lastMsgIdMap = {}; localStorage.removeItem('chat_state');
    }
    window.chatRole = currentRole;
    const chat = chats.find((c) => c.id === otherId) || {
      id: otherId,
      name: data.other?.nome ?? "Usuário",
    };
    // (placeholder já tratado antes)
    activeChatId = chat.id;
    window.activeChatId = activeChatId; // <- mantém global

    const messages = (data.messages || []).map((m) => {
      const fromRole = m.from_role || null;
      const from = (fromRole && currentRole) ? (fromRole === currentRole ? 'me' : 'them') : (m.de === currentUserId ? 'me' : 'them');
      return {
        id: m.id,
        from,
        text: m.conteudo,
        enviado_em: m.enviado_em,
        tipo: m.tipo || 'text',
        arquivo: m.arquivo || null,
        from_role: fromRole
      };
    });

    chatHistories[otherId] = messages;
    window.chatHistories = chatHistories; // <- mantém global
    renderMessages?.(messages);

    // Atualiza objeto do chat com id_chat real para habilitar botões de teste
    try {
      const realChatId = data.id_chat || null;
      const existing = chats.find(c => c.id === otherId);
      const lastIdUpdate = messages.length ? messages[messages.length-1].id || null : null;
      if (existing) {
        existing.chatId = realChatId;
        existing.lastMessageId = lastIdUpdate;
        existing.lastMessage = messages.length ? (messages[messages.length-1].text || '') : '';
        existing.lastMessageTime = messages.length ? (messages[messages.length-1].enviado_em || null) : null;
        existing.messageCount = messages.length;
        existing.newChat = messages.length === 0;
      } else {
        chats.push({
          id: otherId,
          chatId: realChatId,
          name: headerName,
          photo: headerPhoto,
          lastMessageId: lastIdUpdate,
          lastMessage: messages.length ? (messages[messages.length-1].text || '') : '',
          lastMessageTime: messages.length ? (messages[messages.length-1].enviado_em || null) : null,
          unread: 0,
          messageCount: messages.length,
          newChat: messages.length === 0
        });
      }
      window.chats = chats;
      if (typeof window.__enableTestButtons === 'function') { window.__enableTestButtons(); }
    } catch(e) { /* silencia */ }

    // limpa status de digitação imediatamente ao trocar de chat
    try {
      stopDots();
      setStatusText('');
      if (userStatusEl) userStatusEl.classList.remove('online','typing');
    } catch {}

    // Atualiza cabeçalho imediatamente para evitar 'layout do outro'
    const headerName = data.other?.nome || chat.name || "";
    const headerPhoto = data.other?.photo || chat.photo || "../img/SemFoto.jpg";
    if (userNameEl) userNameEl.textContent = headerName;
    if (userPhotoEl) userPhotoEl.style.backgroundImage = `url('${headerPhoto}')`;

    const lastId = Array.isArray(messages) && messages.length ? (messages[messages.length-1].id || 0) : 0;
    if (lastId) setLastReadId(otherId, lastId);
    const c = (chats || []).find(x => x.id === otherId);
    if (c) { c.unread = 0; c.hasNewMessage = false; /* mantém newChat para mostrar ponto roxo até primeira mensagem */ }
    // Remove somente ponto vermelho de unread; preserva roxo de novo chat
    const itemEl = document.querySelector(`.chat-item[data-chat-id='${CSS.escape(String(otherId))}'] .chat-dot:not(.new-chat)`);
    if (itemEl) itemEl.remove();
    // Mensagem de sistema se chat vazio (intro)
    if (!messages.length && messagesEl && !messagesEl.querySelector('.system-intro')) {
      const intro = document.createElement('div');
      intro.className = 'msg system-intro';
      intro.textContent = 'Discuta sobre o serviço com o prestador ou cliente aqui.';
      intro.style.background = '#f3f4f6';
      intro.style.color = '#374151';
      intro.style.borderRadius = '12px';
      intro.style.padding = '10px 14px';
      intro.style.fontSize = '14px';
      intro.style.fontStyle = 'italic';
      intro.style.maxWidth = '640px';
      messagesEl.appendChild(intro);
    }
    // Esconde badge global imediatamente
    const gBadge = document.getElementById('global-chat-badge'); if (gBadge) gBadge.style.display='none';
    renderChatList(chats || []);
    window.chats = chats; // <- mantém global
    // reinicia polling de presença do outro usuário
    startPresencePolling();
    saveState();
    // Atualiza lista do servidor para garantir chatId preenchido em placeholders recém-criados
    try { await fetchChatList(); if (typeof window.__enableTestButtons === 'function') window.__enableTestButtons(); } catch {}
  }

  // Expor openChat com guarda contra auto-switch (só abre se for ação do usuário ou se já for o chat ativo)
  window.openChat = async function(otherId, opts = {}) {
    const isUserAction = !!opts.force || (window.__USER_NAV_TS && (Date.now() - window.__USER_NAV_TS) < 1000);
    if (!isUserAction && otherId !== activeChatId) return;

    // Antes de trocar de chat, marque o chat atual como lido até o último conhecido
    const prevActive = activeChatId;
    if (prevActive && prevActive !== otherId) {
      let lastKnown = 0;
      const hist = chatHistories[prevActive];
      if (Array.isArray(hist) && hist.length) lastKnown = Number(hist[hist.length-1].id || 0);
      if (!lastKnown) {
        const row = (chats || []).find(c => c.id === prevActive);
        if (row && row.lastMessageId) lastKnown = Number(row.lastMessageId);
      }
      if (lastKnown && typeof setLastReadId === 'function') setLastReadId(prevActive, lastKnown);
      // Atualiza cache local para não tocar som nem mostrar bolinha no chat que está sendo deixado
      if (lastKnown) {
        lastMsgIdMap[prevActive] = lastKnown;
      }
      const row = (chats || []).find(c => c.id === prevActive);
      if (row) {
        row.unread = 0;
        row.hasNewMessage = false;
      }
      renderChatList?.(chats || []);
    }

    return openChat(otherId);
  };

  // (removido listener antigo de envio simples para evitar duplicação)

  // ===== Link Preview (OpenGraph) =====
  function upgradeLinkCards(){
    const cards = messagesEl?.querySelectorAll('.link-card[data-url]:not([data-preview])');
    if (!cards || !cards.length) return;
    cards.forEach(card => {
      const url = card.getAttribute('data-url');
      if (!url) return;
      card.setAttribute('data-preview','loading');
      const cacheKey = 'lp_'+url;
      try {
        const cached = sessionStorage.getItem(cacheKey);
        if (cached) { renderPreview(card, JSON.parse(cached)); return; }
      } catch {}
      fetch('../php/linkPreview.php?url='+encodeURIComponent(url), { credentials:'same-origin' })
        .then(r => r.ok ? r.json() : null)
        .then(data => {
          if (data && data.ok) {
            try { sessionStorage.setItem(cacheKey, JSON.stringify(data)); } catch {}
            renderPreview(card, data);
          } else {
            card.removeAttribute('data-preview');
          }
        })
        .catch(()=>{ card.removeAttribute('data-preview'); });
    });
  }

  function renderPreview(card, data){
    if (!card || !data) return;
    card.classList.add('link-preview');
    const title = escapeHtml(data.title || data.url || 'Link');
    const desc  = escapeHtml((data.description || '').slice(0,140));
    const img   = data.image && /^https?:\/\//i.test(data.image) ? data.image : null;
    const a = card.querySelector('a');
    const url = a ? a.getAttribute('href') : (data.url || '');
    card.innerHTML = `
      <a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="lp-wrap">
        ${img ? `<div class="lp-thumb" style="background-image:url('${escapeHtml(img)}')"></div>` : ''}
        <div class="lp-text">
          <div class="lp-title">${title}</div>
          ${desc ? `<div class="lp-desc">${desc}</div>` : ''}
          <div class="lp-host">${escapeHtml(hostFromUrl(url))}</div>
        </div>
      </a>`;
  }

  function hostFromUrl(u){ try { return new URL(u).host.replace(/^www\./,''); } catch { return ''; } }

  // Botão de anexar: apenas abre o seletor de arquivos por enquanto
  if (attachBtn && fileInput) {
    attachBtn.addEventListener('click', () => {
      try { window.__SND__?.unlock?.(); } catch {}
      fileInput.click();
    });
    fileInput.addEventListener('change', () => {
      if (!fileInput.files || !fileInput.files.length) return;
      stageFiles(Array.from(fileInput.files));
      fileInput.value='';
    });
  }

  if (messageInput) {
    messageInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        try { window.__SND__?.unlock?.(); } catch {}
        sendBtn.click();
      }
    });
  }

  // ===== Pending attachments staging =====
  let pendingAttachments = [];
  const attachedBar = document.getElementById('attached-bar');
  function formatSize(bytes){ if (!bytes) return ''; const kb = bytes/1024; if (kb<1024) return Math.max(1,Math.round(kb))+' KB'; const mb=kb/1024; return mb.toFixed(2)+' MB'; }
  function stageFiles(files){
    const accepted = files.filter(f => f.size>0);
    if (!accepted.length) return;
    pendingAttachments.push(...accepted);
    renderPendingAttachments();
  }
  function removePending(idx){ pendingAttachments.splice(idx,1); renderPendingAttachments(); }
  function renderPendingAttachments(){
    if (!attachedBar) return;
    if (!pendingAttachments.length){ attachedBar.hidden = true; attachedBar.innerHTML=''; return; }
    attachedBar.hidden = false;
    attachedBar.innerHTML = pendingAttachments.map((f,i)=>{
      const size = formatSize(f.size);
      const name = f.name;
      return `<div class="attachment-chip" data-idx="${i}" title="${name}"><span class="att-name">${escapeHtml(name)}</span><span class="att-size">${escapeHtml(size)}</span><button type="button" class="remove-att" aria-label="Remover">×</button></div>`;
    }).join('');
  }
  if (attachedBar && !attachedBar.__bindRemove){
    attachedBar.__bindRemove = true;
    attachedBar.addEventListener('click', e => {
      const btn = e.target.closest('button.remove-att'); if (!btn) return;
      const chip = btn.closest('.attachment-chip'); if (!chip) return;
      const idx = Number(chip.dataset.idx); if (!isNaN(idx)) removePending(idx);
    });
  }

  // ===== Drag & Drop to stage =====
  const dropZone = document.getElementById('chat-input-area');
  if (dropZone && !dropZone.__ddBound) {
    dropZone.__ddBound = true;
    ['dragenter','dragover'].forEach(ev => dropZone.addEventListener(ev, e => {
      e.preventDefault(); e.stopPropagation();
      dropZone.classList.add('drag-hover');
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
    }));
    ['dragleave','dragend'].forEach(ev => dropZone.addEventListener(ev, e => {
      if (e.relatedTarget === dropZone) return; // still inside
      dropZone.classList.remove('drag-hover');
    }));
    dropZone.addEventListener('drop', async e => {
      e.preventDefault(); e.stopPropagation();
      dropZone.classList.remove('drag-hover');
      const dt = e.dataTransfer;
      if (!dt || !dt.files || dt.files.length === 0) return;
      stageFiles(Array.from(dt.files));
    });
  }

  async function pollChats() {
    try {
      const latest = await fetchChatList();
      if (!Array.isArray(latest)) return;
      chats = latest;
      window.chats = chats; // <- mantém global

      const active = chats.find(c => c.id === activeChatId);
      if (active) {
        const localHist = chatHistories[active.id];
        if (localHist && localHist.length > 0) {
          active.lastMessage = localHist[localHist.length - 1].text;
        }
      }

      // Notificações ao detectar mudança de último id em cada chat
      chats.forEach((c) => {
        const cid = c.id;
        const newId = c.lastMessageId || null;
        if (newId) {
          const prevId = lastMsgIdMap[cid];
          if (prevId !== newId) {
            if (cid !== activeChatId) {
              const lastRead = getLastReadId(cid);
              if (newId > lastRead && !lastMessageIsFromMe(cid, newId)) {
                window.__SND__?.playNew();
              }
            }
          }
          lastMsgIdMap[cid] = newId;
        }
      });
      renderChatList(chats);
      // Reaplica filtro de busca se estiver ativo, para persistir durante polling
      if (searchInput && searchInput.value.trim() !== '') {
        const q = searchInput.value.trim().toLowerCase();
        const filtered = chats.filter(c => (c.name||'').toLowerCase().includes(q) || (c.lastMessage||'').toLowerCase().includes(q));
        renderChatList(filtered);
      }

      if (activeChatId && activeChatId !== 0) {
        const open = await fetchOpenChat(activeChatId);
        if (sendBtn && !sendBtn.__stagedBound) {
          sendBtn.__stagedBound = true;
          sendBtn.addEventListener('click', async () => {
            try { window.__SND__?.unlock?.(); } catch {}
            if (!activeChatId) return;
            const text = messageInput.value.trim();
            // If there are staged attachments, send them sequentially first with caption
            const removeIntro = () => {
              const intro = messagesEl?.querySelector('.msg.system-intro');
              if (intro) intro.remove();
            };
            if (pendingAttachments.length) {
              const files = [...pendingAttachments];
              pendingAttachments = []; renderPendingAttachments();
              let first = true;
              for (const f of files) {
                const placeholder = { id: 'temp_'+Date.now()+'_'+Math.random().toString(36).slice(2), from:'me', tipo:'file', arquivo:null, text:'Enviando '+f.name+'...' };
                chatHistories[activeChatId] = chatHistories[activeChatId] || [];
                chatHistories[activeChatId].push(placeholder); renderMessages(chatHistories[activeChatId]); pinScrollBottom();
                let r; try { r = await sendAttachment(activeChatId, f, first ? text : ''); } catch(e){ r={ok:false, erro:e.message}; }
                const hist = chatHistories[activeChatId]; const idx = hist.findIndex(x => x.id === placeholder.id); if (idx>=0) hist.splice(idx,1);
                if (r.ok) {
                  // Fallback parse caso migração incompleta tenha retornado marker
                  let tipo = r.tipo;
                  let arquivo = r.arquivo;
                  if ((!arquivo || tipo === 'text') && r.fallback_marker) {
                    const mm = r.fallback_marker.match(/type=([^;]+);file=([^\]]+)/);
                    if (mm) { tipo = mm[1]; arquivo = mm[2]; }
                  }
                  const msg = { id:r.id_mensagem, from:'me', text:r.texto||'', tipo, arquivo, enviado_em:r.enviado_em, tamanho:r.tamanho };
                  hist.push(msg);
                  const chat = chats.find(c=>c.id===activeChatId); if (chat){ chat.lastMessage = msg.text || ('['+msg.tipo+']'); chat.messageCount = (chat.messageCount||0)+1; chat.newChat = false; }
                  removeIntro();
                  renderMessages(hist); pinScrollBottom(); window.onMessageSent?.(activeChatId, r.id_mensagem);
                } else {
                  hist.push({ id:'fail_'+Date.now(), from:'me', text:'Falha: '+(r.erro||'erro upload') }); renderMessages(hist); pinScrollBottom();
                }
                first=false;
              }
              messageInput.value='';
              saveState();
              return;
            }
            // No attachments, normal text send
            if (!text) return;
            const result = await sendMessage(activeChatId, text);
            if (result.ok) {
              const msg = { id: result.id_mensagem, from:'me', text, enviado_em: result.enviado_em };
              chatHistories[activeChatId] = chatHistories[activeChatId] || [];
              chatHistories[activeChatId].push(msg);
              renderMessages(chatHistories[activeChatId]); pinScrollBottom();
              const chat = chats.find(c=>c.id===activeChatId); if (chat){ chat.lastMessage = text; chat.messageCount = (chat.messageCount||0)+1; chat.newChat = false; }
              removeIntro();
              if (result.id_mensagem) { lastMsgIdMap[activeChatId] = result.id_mensagem; setLastReadId(activeChatId, result.id_mensagem); }
              renderChatList(chats); messageInput.value=''; window.onMessageSent?.(activeChatId, (result.id_mensagem||result.id)); saveState();
            }
          });
          // Atualiza header
          const chat = chats.find(c => c.id === activeChatId);
          const name = (open.other && open.other.nome) || (chat && chat.name) || '';
          const photo = (open.other && open.other.photo) || (chat && chat.photo) || "../img/SemFoto.jpg";
          if (userNameEl) userNameEl.textContent = name;
          if (userPhotoEl) userPhotoEl.style.backgroundImage = `url('${photo}')`;
        }
        // ===== Merge de novas mensagens (inclui anexos) mesmo após bind inicial =====
        if (open && open.ok && Array.isArray(open.messages)) {
          const role = open.current_role || window.chatRole || null;
          const meId = open.current_user_id ?? null;
          const hist = chatHistories[activeChatId] || [];
          const remote = open.messages.map(m => {
            const fromRole = m.from_role || null;
            const from = (fromRole && role) ? (fromRole === role ? 'me' : 'them') : (m.de === meId ? 'me' : 'them');
            return { id:m.id, from, text:m.conteudo, enviado_em:m.enviado_em, tipo:m.tipo||'text', arquivo:m.arquivo||null, from_role:fromRole };
          });
          const numericLocal = hist.filter(h => typeof h.id === 'number').map(h => h.id);
          const numericRemote = remote.filter(r => typeof r.id === 'number').map(r => r.id);
          const lastLocal = numericLocal.length ? Math.max(...numericLocal) : 0;
          const lastRemote = numericRemote.length ? Math.max(...numericRemote) : 0;
          // Se remoto avançou (inclui novos anexos), substitui histórico completo para evitar perda de ordenação/anexos.
          if (!hist.length || lastRemote > lastLocal) {
            chatHistories[activeChatId] = remote;
            window.chatHistories = chatHistories;
            renderMessages?.(remote);
            if (lastRemote) setLastReadId(activeChatId, lastRemote);
            const row = (chats || []).find(c => c.id === activeChatId);
            if (row && remote.length) row.lastMessage = remote[remote.length - 1].text || ('[' + remote[remote.length - 1].tipo + ']');
            renderChatList(chats || []);
          }
        }
      }
      saveState();
    } catch (err) {
      console.error("pollChats erro:", err);
    }
  }

  // mantém scroll no final (chamado após envios e renders)
  function pinScrollBottom(){
    if (!messagesEl) return;
    messagesEl.scrollTop = messagesEl.scrollHeight;
    // várias tentativas para mídia/preview async
    setTimeout(()=>{ if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight; }, 120);
    setTimeout(()=>{ if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight; }, 400);
    requestAnimationFrame(()=>{ if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight; });
  }

  function startPolling() {
    if (pollingTimer) clearInterval(pollingTimer);
    pollingTimer = setInterval(pollChats, 1000);
  }

  // =============== PRESENÇA (online/digitando) ===============
  async function presenceUpdate(typing) {
    try {
      const body = new URLSearchParams();
      if (typeof typing === "number") body.append("typing", String(typing));
      // inclui alvo do chat para escopo de digitando
      if (typeof typing === 'number' && window.activeChatId) {
        body.append('other_id', String(window.activeChatId));
      }
      await fetch("../php/presence_update.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body,
      });
    } catch (e) {
      // silencioso
    }
  }

  async function presenceGet(otherId) {
    try {
      if (!otherId) return { ok: false };
      const resp = await fetch(
        `../php/presence_get.php?other_id=${encodeURIComponent(
          otherId
        )}`,
        { credentials: "same-origin" }
      );
      if (!resp.ok) return { ok: false };
      return await resp.json();
    } catch (_) {
      return { ok: false };
    }
  }

  function setStatusText(text) {
    if (!userStatusEl) return;
    userStatusEl.textContent = text || "";
  }

  function stopDots() {
    if (dotsTimer) {
      clearInterval(dotsTimer);
      dotsTimer = null;
    }
    dotsStep = 0;
    typingAnimating = false;
  }

  function startDots(baseText) {
    if (!userStatusEl) return;
    if (typingAnimating) return; // não reinicia se já está animando
    stopDots();
    typingAnimating = true;
    // Ciclo desejado: Digitando. Digitando.. Digitando...
    const states = ['.', '..', '...'];
    let idx = 0;
    userStatusEl.textContent = `${baseText}${states[idx]}`;
    dotsTimer = setInterval(() => {
      idx = (idx + 1) % states.length;
      userStatusEl.textContent = `${baseText}${states[idx]}`;
    }, 550); // ligeiramente mais lento
  }

  async function pollPresenceOnce() {
    const requestedId = activeChatId; // captura id no início para comparar após fetch
    if (!requestedId) {
      stopDots();
      setStatusText("");
      return;
    }
    const res = await presenceGet(requestedId);
    // se mudou de chat enquanto buscava, ignora resultado
    if (activeChatId !== requestedId) return;
    if (!res || !res.ok) return;
    if (userStatusEl) userStatusEl.classList.remove('online','typing');
    if (res.typing) {
      startDots("Digitando");
      if (userStatusEl) userStatusEl.classList.add('typing');
    } else if (res.online) {
      stopDots();
      setStatusText("online");
      if (userStatusEl) userStatusEl.classList.add('online');
    } else {
      stopDots();
      setStatusText(""); // permanece oculto
    }
  }

  function startPresencePolling() {
    if (presencePollTimer) clearInterval(presencePollTimer);
    // faz um ping imediato
    pollPresenceOnce();
    presencePollTimer = setInterval(pollPresenceOnce, 1000);
  }

  (async function init() {
    const latest = await fetchChatList();
    if (latest.length > 0) chats = latest;
    renderChatList(chats);
    // Auto-seleção robusta do primeiro chat:
    // - Se não houver ativo, seleciona o primeiro
    // - Se houver ativo salvo mas ele não existe mais, seleciona o primeiro
    const activeExists = activeChatId && (chats || []).some(c => c.id === activeChatId);
    if ((!activeChatId || !activeExists) && chats.length) {
      try {
        const first = chats[0];
        if (first.placeholder || first.id === 0) {
          // Apenas ativa placeholder sem backend
          window.openChat?.(first.id, { force:true });
        } else {
          await window.openChat?.(first.id, { force:true });
        }
      } catch(e) {
        console.warn('[chat] auto-open falhou:', e);
      }
    } else if (activeExists) {
      renderMessages(chatHistories[activeChatId] || []);
    }
    startPolling();
    // Inicia heartbeat do usuário atual para marcar "online"
    if (heartbeatTimer) clearInterval(heartbeatTimer);
    await presenceUpdate(); // ping inicial
    heartbeatTimer = setInterval(() => presenceUpdate(), 20000);
    // typing: envia sinal quando o usuário digita
    if (messageInput) {
      messageInput.addEventListener("input", () => {
        const now = Date.now();
        if (now - lastTypingPing > 2500) {
          lastTypingPing = now;
          presenceUpdate(1);
        }
      });
    }

    // Ping presença a cada 20s + typing quando digita
    if (!window.__presenceTimer) {
      window.__presenceTimer = setInterval(() => {
        fetch('../php/statusPing.php', { method:'POST', credentials:'same-origin' }).catch(()=>{});
      }, 20000);
      fetch('../php/statusPing.php', { method:'POST', credentials:'same-origin' }).catch(()=>{});
    }
    const input = document.querySelector('#message-input, #msg-input, textarea[name="mensagem"]');
    if (input && !input.__typingBound) {
      input.__typingBound = true;
      let lastSent = 0;
      input.addEventListener('input', () => {
        const now = Date.now(); if (now - lastSent < 1200) return; lastSent = now;
        const fd = new FormData(); fd.append('typing','1');
        if (window.activeChatId) fd.append('other_id', String(window.activeChatId));
        fetch('../php/statusPing.php', { method:'POST', body: fd, credentials:'same-origin' }).catch(()=>{});
      });
    }

    // Atualiza status do ativo a cada 1s
    if (!window.__statusTimer) {
      window.__statusTimer = setInterval(async () => {
        const id = window.activeChatId; if (!id) return;
        try {
          const r = await fetch(`../php/getStatus.php?other_id=${id}&t=${Date.now()}`, { credentials:'same-origin', cache:'no-store' });
          if (!r.ok) return; const st = await r.json(); if (!st?.ok) return;
          const headerStatus = document.getElementById('chat-user-status') || document.querySelector('.chat-header .status');
          if (headerStatus) {
            headerStatus.classList.remove('online','typing');
            if (st.typing) {
              // inicia se não estiver rodando
              startDots('Digitando');
              headerStatus.classList.add('typing');
            } else if (st.online) {
              stopDots();
              headerStatus.textContent = 'online';
              headerStatus.classList.add('online');
            } else {
              stopDots();
              headerStatus.textContent = '';
            }
          }
          // se chat mudou durante a requisição, limpa status para evitar mostrar "digitando" incorreto
          if (id !== window.activeChatId && headerStatus) {
            stopDots();
            headerStatus.textContent = '';
            headerStatus.classList.remove('online','typing');
          }
          const row = (window.chats || []).find(c => c.id === id);
          if (row) { row.online = !!st.online; window.renderChatList?.(window.chats || []); }
        } catch {}
      }, 1000);
    }
  })();

  // ====== CHAT SEARCH BAR ======
  // ====== CHAT SEARCH BAR (sem piscar, preserva ordem) ======
  if (searchInput && !searchInput.__bound) {
    searchInput.__bound = true;
    searchInput.addEventListener('input', () => renderChatList(chats));
  }

  // Ao clicar no item da lista, marque navegação do usuário
  if (chatListEl && !chatListEl.__guardClick) {
    chatListEl.__guardClick = true;
    chatListEl.addEventListener('click', (ev) => {
      const item = ev.target.closest('.chat-item');
      if (!item) return;
      try { window.__SND__?.unlock?.(); } catch {}
      // Suprime som de nova mensagem próximo à navegação
      window.__SUPPRESS_SOUND_TS = Date.now()+900;
      window.__USER_NAV_TS = Date.now();
      const id = Number(item.dataset.chatId);
      if (id) window.openChat?.(id, { force: true });
    });
  }


  // Sons WAV (unlock silencioso + fallback beep)
  (function initSoundsWav(){
    if (window.__SND__) return;
    const newSrc  = '../sounds/NovaMensagem.wav';
    const sameSrc = '../sounds/NovaMensagemMesmoChat.wav';
    const aNew  = new Audio(newSrc);  aNew.preload='auto';  aNew.volume=0.28;
    const aSame = new Audio(sameSrc); aSame.preload='auto'; aSame.volume=0.28;

    const AC = window.AudioContext || window.webkitAudioContext;
    const ctx = AC ? new AC() : null;
    let unlocked = false;

    async function unlockOnce(){
      if (unlocked) return;
      try {
        if (ctx?.state === 'suspended') { try { await ctx.resume(); } catch {} }
        for (const a of [aNew, aSame]) {
          try { a.muted = true; a.currentTime = 0; await a.play(); a.pause(); a.currentTime = 0; a.muted = false; } catch {}
        }
      } finally {
        unlocked = true;
      }
    }
    ['pointerdown','click','keydown','touchstart'].forEach(e => window.addEventListener(e, unlockOnce, { once:true }));

    function beep(freq=880, dur=0.12, vol=0.18){
      if (!ctx) return;
      const t0 = ctx.currentTime, osc = ctx.createOscillator(), g = ctx.createGain();
      osc.type = 'sine'; osc.frequency.value = freq;
      g.gain.setValueAtTime(0.0001,t0);
      g.gain.exponentialRampToValueAtTime(vol,t0+0.01);
      g.gain.exponentialRampToValueAtTime(0.0001,t0+dur);
      osc.connect(g); g.connect(ctx.destination);
      osc.start(t0); osc.stop(t0+dur);
    }
    async function play(tag){
      const au = tag === 'new' ? aNew : aSame;
      try { au.currentTime = 0; await au.play(); }
      catch { beep(tag === 'new' ? 920 : 620); }
    }
    window.__SND__ = { playNew: ()=>play('new'), playSame: ()=>play('same') };
  })();

  // Estado local: lastRead/lastSent (para bolinha e som)
  (function initReadMaps(){
    if (window.getLastReadId) return;
    const LS_READ='chatLastReadId', LS_SENT='chatLastSentId';
    function load(k){ try { return JSON.parse(localStorage.getItem(k)||'{}'); } catch { return {}; } }
    function save(k,m){ localStorage.setItem(k, JSON.stringify(m)); }
    window.__lastRead = load(LS_READ);
    window.__lastSent = load(LS_SENT);
    window.getLastReadId = id => Number(window.__lastRead[id] || 0);
    window.setLastReadId = (id,val) => { window.__lastRead[id] = Number(val)||0; save(LS_READ, window.__lastRead); };
    window.getLastSentId = id => Number(window.__lastSent[id] || 0);
    window.setLastSentId = (id,val) => { window.__lastSent[id] = Number(val)||0; save(LS_SENT, window.__lastSent); };
    window.onMessageSent = (chatId, msgId) => { if (!chatId || !msgId) return; setLastSentId(chatId, msgId); setLastReadId(chatId, msgId); };
  })();

  // Último lido (client-side) — controla a bolinha roxa
  if (!window.__lastRead) {
    const LS_KEY='chatLastReadId';
    function load(){ try { return JSON.parse(localStorage.getItem(LS_KEY)||'{}'); } catch { return {}; } }
    function save(m){ localStorage.setItem(LS_KEY, JSON.stringify(m)); }
    window.__lastRead = load();
    window.getLastReadId = (id)=>Number(window.__lastRead[id]||0);
    window.setLastReadId = (id,val)=>{ window.__lastRead[id]=Number(val)||0; save(window.__lastRead); };
  }
  // Último ENVIADO por chat (evita tocar "NovaMensagem.wav" pela sua própria msg)
  if (!window.__lastSent) window.__lastSent = {};
  function getLastSentId(chatId){ return Number(window.__lastSent[chatId] || 0); }
  function setLastSentId(chatId, id){ window.__lastSent[chatId] = Number(id)||0; }

  // Callback global para ser chamado após enviar mensagem com sucesso
  window.onMessageSent = function(chatId, newMsgId){
    if (!chatId || !newMsgId) return;
    setLastSentId(chatId, newMsgId);
    // marca como lido para você (não gerar bolinha do seu próprio envio)
    if (typeof setLastReadId === 'function') setLastReadId(chatId, newMsgId);
  };

  // ====== IMAGE LIGHTBOX ======
  function openImageLightbox(src, caption='') {
    if (!src) return;
    const old = document.querySelector('.img-lightbox-overlay');
    if (old) old.remove();
    const overlay = document.createElement('div');
    overlay.className = 'img-lightbox-overlay';
    const content = document.createElement('div');
    content.className = 'img-lightbox-content';
    const img = document.createElement('img');
    img.src = src;
    img.alt = caption || 'imagem';
    const btn = document.createElement('button');
    btn.className = 'img-lightbox-close';
    btn.type = 'button';
    btn.setAttribute('aria-label','Fechar');
    btn.textContent = '×';
    btn.addEventListener('click', () => closeLightbox());
    const capEl = document.createElement('div');
    capEl.className = 'img-lightbox-caption';
    capEl.textContent = caption || '';
    content.appendChild(img);
    content.appendChild(btn);
    if (caption) content.appendChild(capEl);
    overlay.appendChild(content);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeLightbox(); });
    document.body.appendChild(overlay);
    document.body.classList.add('lightbox-open');
    document.addEventListener('keydown', escHandler);
  }
  function closeLightbox(){
    document.body.classList.remove('lightbox-open');
    const ov = document.querySelector('.img-lightbox-overlay');
    if (ov) ov.remove();
    document.removeEventListener('keydown', escHandler);
  }
  function escHandler(e){ if (e.key === 'Escape') closeLightbox(); }
  if (messagesEl && !messagesEl.__lbBound) {
    messagesEl.__lbBound = true;
    messagesEl.addEventListener('click', (ev) => {
      const img = ev.target.closest('.image-el');
      if (!img) return;
      let caption = '';
      const cap = img.parentElement?.querySelector('.att-caption');
      if (cap) caption = cap.textContent.trim();
      openImageLightbox(img.src, caption);
    });
  }

});
async function refreshActiveChatOnce() {
  const activeId = window.activeChatId; // garantir uso da var global
  if (!activeId) return;
  try {
    const url = `../php/openChat.php?other_id=${encodeURIComponent(activeId)}&t=${Date.now()}`;
    const r = await fetch(url, { credentials:'same-origin', cache:'no-store' });
    if (!r.ok) return;
    const data = await r.json();
    if (!data || !data.ok) return;

    const me = Number(data.current_user_id ?? 0);
    const role = data.current_role || window.chatRole || null;
    const remote = (data.messages || []).map(m => ({
      id: m.id ?? m.id_mensagem,
      from: (m.from_role && role) ? (m.from_role === role ? 'me' : 'them') : (Number(m.de ?? m.id_de) === me ? 'me' : 'them'),
      text: String(m.conteudo ?? '').trim(),
      enviado_em: m.enviado_em || null,
      tipo: m.tipo || 'text',
      arquivo: m.arquivo || null,
      from_role: m.from_role || null
    }));

    window.chatHistories = window.chatHistories || {};
  const hist = window.chatHistories[activeId] || [];
    const last = hist[hist.length - 1] || null;

    let newMsgs = [];
    if (!last) newMsgs = remote;
    else if (last.id != null) newMsgs = remote.filter(x => x.id != null && x.id > last.id);
    else if (last.enviado_em) newMsgs = remote.filter(x => x.enviado_em && x.enviado_em > last.enviado_em);
    if (!newMsgs.length) return;

    const merged = hist.concat(newMsgs);
  window.chatHistories[activeId] = merged;
  window.renderMessages?.(merged);

    // Som no mesmo chat (apenas recebidas e aba visível)
    // Só toca som se não for abertura recente (supressão) e se não estiver oculto
    const suppress = window.__SUPPRESS_SOUND_TS && Date.now() < window.__SUPPRESS_SOUND_TS;
    if (newMsgs.some(x => x.from === 'them') && !document.hidden && !suppress) {
      window.__SND__?.playSame();
    }

    // Marca como lido até a última
    const lastIdAll = merged[merged.length-1]?.id || 0;
  if (lastIdAll) setLastReadId(activeId, lastIdAll);

    // Atualiza preview e limpa bolinha do ativo
    const row = (window.chats || []).find(c => c.id === activeId);
    if (row) { row.lastMessage = merged[merged.length-1]?.text || row.lastMessage; row.unread = 0; row.hasNewMessage = false; }
    window.renderChatList?.(window.chats || []);
  } catch {}
}
