(() => {
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const state = { role: 'beneficiary', view: 'dashboard', reports: [] };
  // Módulos externos (área do apoiador) registram aqui seus carregadores de tela.
  const viewLoaders = {};
  const viewBinders = [];

  const esc = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
  const toast = (message) => {
    const element = $('#toast');
    if (!element) return;
    element.textContent = message;
    element.classList.add('show');
    clearTimeout(window.__lumenToast);
    window.__lumenToast = setTimeout(() => element.classList.remove('show'), 4200);
  };

  function setMenuOpen(id, trigger, open) {
    const menu = document.getElementById(id), button = document.getElementById(trigger);
    if (!menu || !button) return;
    menu.hidden = !open; button.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  function applyAccessibilityPreferences() {
    const prefs = JSON.parse(localStorage.getItem('lumenA11y') || '{}');
    const font = Math.max(0, Math.min(2, Number(prefs.font) || 0));
    document.body.classList.toggle('font-scale-1', font === 1);
    document.body.classList.toggle('font-scale-2', font === 2);
    document.body.classList.toggle('theme-dark', prefs.theme === 'dark');
    document.body.classList.toggle('high-contrast', !!prefs.contrast);
    const theme = $('#themeState'), contrast = $('#contrastState');
    if (theme) theme.textContent = prefs.theme === 'dark' ? 'Ativado' : 'Desativado';
    if (contrast) contrast.textContent = prefs.contrast ? 'Ativado' : 'Desativado';
    $('#themeToggle')?.setAttribute('aria-pressed', prefs.theme === 'dark' ? 'true' : 'false');
    $('#contrastToggle')?.setAttribute('aria-pressed', prefs.contrast ? 'true' : 'false');
  }
  function updateAccessibility(change) {
    const prefs = JSON.parse(localStorage.getItem('lumenA11y') || '{}');
    if ('font' in change) prefs.font = change.font === 0 ? 0 : Math.max(0, Math.min(2, (Number(prefs.font) || 0) + change.font));
    if ('theme' in change) prefs.theme = change.theme;
    if ('contrast' in change) prefs.contrast = change.contrast;
    localStorage.setItem('lumenA11y', JSON.stringify(prefs)); applyAccessibilityPreferences();
  }
  async function logout() { try { await api('logout'); localStorage.removeItem('lumenRememberEmail'); window.location.reload(); } catch (error) { toast(error.message); } }
  function openProfileEdit(open = true) {
    const form = $('#profileEditForm'); if (!form) return;
    form.classList.toggle('hidden', !open);
    if (open) form.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  async function api(action, payload = {}, multipart = false, endpoint = 'index.php') {
    let body;
    if (multipart) {
      body = payload instanceof FormData ? payload : new FormData();
      if (!(payload instanceof FormData)) Object.entries(payload).forEach(([key, value]) => body.append(key, value));
      body.append('csrf', window.LUMEN.csrf);
    } else {
      body = new URLSearchParams();
      Object.entries(payload).forEach(([key, value]) => body.append(key, value ?? ''));
      body.append('csrf', window.LUMEN.csrf);
    }
    const response = await fetch(`api/${endpoint}?action=${encodeURIComponent(action)}`, { method: 'POST', body, headers: { 'Accept': 'application/json' } });
    const result = await response.json().catch(() => ({ ok: false, message: 'Resposta inválida do servidor.' }));
    if (!result.ok) { const error = new Error(result.message || 'Não foi possível concluir a ação.'); error.data = result.data || {}; error.status = response.status; throw error; }
    return result;
  }

  function openModal(id) { const modal = document.getElementById(id); if (modal) modal.classList.add('open'); }
  function closeModal(id) { const modal = document.getElementById(id); if (modal) modal.classList.remove('open'); }
  function bindModalButtons() {
    $$('[data-close-modal]').forEach((button) => button.onclick = () => closeModal(button.dataset.closeModal));
    $$('.modal-backdrop').forEach((modal) => modal.addEventListener('click', (event) => { if (event.target === modal) modal.classList.remove('open'); }));
  }

  function goToLogin() {
    const landing = $('#landingScreen');
    const login = $('#loginScreen');
    if (landing) { landing.hidden = true; landing.classList.add('hidden'); }
    if (login) { login.hidden = false; login.classList.remove('hidden'); }
    $('#loginEmail')?.focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function showView(name) {
    const template = document.getElementById(`view-${name}`) || document.getElementById('view-dashboard');
    const container = $('#viewContainer');
    if (!template || !container) return;
    state.view = name;
    container.innerHTML = template.innerHTML;
    $('#pageLabel').textContent = template.dataset.label || '';
    $$('.nav-item').forEach((button) => button.classList.toggle('active', button.dataset.view === name));
    $('#sidebar')?.classList.remove('open');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    bindViewActions();
    loadViewData(name);
  }

  function bindNavigation() {
    $$('[data-view]').forEach((element) => element.onclick = (event) => { event.preventDefault(); setMenuOpen('profileMenu','topAvatar',false); const edit=element.dataset.editProfile==='1'; showView(element.dataset.view); if(edit) setTimeout(()=>openProfileEdit(true),80); });
  }

  function bindLanding() {
    $('#landingLogin')?.addEventListener('click', goToLogin);
    $('#landingLoginTop')?.addEventListener('click', goToLogin);
    $('#backLanding')?.addEventListener('click', () => {
      const landing = $('#landingScreen');
      const login = $('#loginScreen');
      if (login) { login.hidden = true; login.classList.add('hidden'); }
      if (landing) { landing.hidden = false; landing.classList.remove('hidden'); }
    });
    $('#landingMenu')?.addEventListener('click', () => {
      const nav = $('.landing-nav');
      const menu = $('#landingMenu');
      const open = nav?.classList.toggle('open');
      menu?.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    $$('[data-landing-login]').forEach((button) => button.onclick = goToLogin);
    $$('[data-scroll]').forEach((button) => button.onclick = () => document.querySelector(button.dataset.scroll)?.scrollIntoView({ behavior: 'smooth' }));
    $$('[data-help]').forEach((button) => button.onclick = () => showView('ajuda'));
  }

  function bindLogin() {
    $$('#loginRoles .role-tab').forEach((button) => button.onclick = () => {
      $$('#loginRoles .role-tab').forEach((tab) => tab.classList.remove('active'));
      button.classList.add('active');
      state.role = button.dataset.role;
      $('#loginEmail').placeholder = state.role === 'team' ? 'admin@lumen.org' : 'seuemail@exemplo.com';
    });
    $('#loginForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = $('#loginForm button[type="submit"]');
      button.disabled = true;
      try {
        const data = new FormData(event.currentTarget);
        const remember = data.has('remember');
        const email = String(data.get('email') || '').trim();
        const result = await api('login', { email, password: data.get('password'), role: state.role, remember: remember ? '1' : '0' });
        if (remember) localStorage.setItem('lumenRememberEmail', email); else localStorage.removeItem('lumenRememberEmail');
        toast(result.message);
        setTimeout(() => window.location.reload(), 450);
      } catch (error) { toast(error.message); } finally { button.disabled = false; }
    });
    const savedEmail=localStorage.getItem('lumenRememberEmail');
    if(savedEmail){$('#loginEmail').value=savedEmail;const remember=$('#rememberLogin');if(remember)remember.checked=true;}
    $('#toggleLoginPassword')?.addEventListener('click',()=>{const input=$('#loginPassword'),button=$('#toggleLoginPassword');const reveal=input.type==='password';input.type=reveal?'text':'password';button.setAttribute('aria-pressed',reveal?'true':'false');button.setAttribute('aria-label',reveal?'Ocultar senha':'Mostrar senha');button.textContent=reveal?'Ocultar':'Mostrar';});
    $('#registerBtn')?.addEventListener('click', () => openModal('registerModal'));
    $('#forgotBtn')?.addEventListener('click', () => openModal('forgotModal'));
  }

  async function bindGlobal() {
    $('#menuBtn')?.addEventListener('click', () => $('#sidebar')?.classList.toggle('open'));
    $('#logoutBtn')?.addEventListener('click', logout);
    $('#topAvatar')?.addEventListener('click', () => { const menu=$('#profileMenu'); setMenuOpen('profileMenu','topAvatar',!!menu?.hidden); setMenuOpen('accessibilityMenu','accessibilityBtn',false); });
    $('#accessibilityBtn')?.addEventListener('click', () => { const menu=$('#accessibilityMenu'); setMenuOpen('accessibilityMenu','accessibilityBtn',!!menu?.hidden); setMenuOpen('profileMenu','topAvatar',false); });
    $$('[data-font-step]').forEach(button=>button.addEventListener('click',()=>updateAccessibility({font:Number(button.dataset.fontStep)})));
    $('#themeToggle')?.addEventListener('click',()=>{const prefs=JSON.parse(localStorage.getItem('lumenA11y')||'{}');updateAccessibility({theme:prefs.theme==='dark'?'light':'dark'});});
    $('#contrastToggle')?.addEventListener('click',()=>{const prefs=JSON.parse(localStorage.getItem('lumenA11y')||'{}');updateAccessibility({contrast:!prefs.contrast});});
    $('#notifyBtn')?.addEventListener('click',()=>openNotificationsView());
    $('[data-menu-notifications]')?.addEventListener('click',()=>{setMenuOpen('profileMenu','topAvatar',false);openNotificationsView();});
    document.addEventListener('pointerdown',event=>{if(!event.target.closest('#profileMenu')&&!event.target.closest('#topAvatar'))setMenuOpen('profileMenu','topAvatar',false);if(!event.target.closest('#accessibilityMenu')&&!event.target.closest('#accessibilityBtn'))setMenuOpen('accessibilityMenu','accessibilityBtn',false);});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'){setMenuOpen('profileMenu','topAvatar',false);setMenuOpen('accessibilityMenu','accessibilityBtn',false);}});
    $('#closeSos')?.addEventListener('click', () => $('#sosModal')?.classList.remove('open'));
    $('#cancelSos')?.addEventListener('click', () => $('#sosModal')?.classList.remove('open'));
    $('#sosBtn')?.addEventListener('click', () => openModal('sosModal'));
    $('#confirmSos')?.addEventListener('click', sendSos);
    $('#registerForm')?.addEventListener('submit', registerUser);
    $('[data-profile-logout]')?.addEventListener('click', logout);
    $('#forgotForm')?.addEventListener('submit', requestReset);
    bindModalButtons();
  }

  async function registerUser(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const data = new FormData(form);
      const result = await api('register', Object.fromEntries(data.entries()));
      closeModal('registerModal');
      form.reset();
      toast(result.message);
    } catch (error) { toast(error.message); }
  }

  async function requestReset(event) {
    event.preventDefault();
    try {
      const data = new FormData(event.currentTarget);
      const result = await api('forgot_request', { email: data.get('email') });
      closeModal('forgotModal');
      toast(result.message);
      if (result.data.reset_url) setTimeout(() => toast(`Ambiente local: link de teste ${result.data.reset_url}`), 500);
    } catch (error) { toast(error.message); }
  }

  async function sendSos() {
    const button = $('#confirmSos');
    button.disabled = true;
    const send = (latitude = '', longitude = '') => api('sos', { latitude, longitude }).then((result) => { closeModal('sosModal'); toast(result.message); if (result.data.whatsapp_url) setTimeout(() => toast('Os links de WhatsApp estão disponíveis em Notificações.'), 500); }).catch((error) => toast(error.message)).finally(() => { button.disabled = false; });
    if (navigator.geolocation) navigator.geolocation.getCurrentPosition((position) => send(position.coords.latitude, position.coords.longitude), () => send(), { timeout: 5000 }); else send();
  }

  function bindViewActions() {
    bindNavigation();
    $('#sosBtn')?.addEventListener('click', () => openModal('sosModal'));
    const reportForm = $('#reportForm');
    reportForm?.addEventListener('submit', submitReport);
    $('#reportFiles')?.addEventListener('change', (event) => { $('#fileList').innerHTML = [...event.target.files].map((file) => `<span>▧ ${esc(file.name)}</span>`).join(''); });
    $('#supportRequestForm')?.addEventListener('submit', submitSupportRequest);
    $('#contactForm')?.addEventListener('submit', addContact);
    $$('.preference-toggle').forEach((button) => {
      const key=button.dataset.setting||button.getAttribute('aria-label')||'preference';
      let saved={};try{saved=JSON.parse(localStorage.getItem('lumenSettings')||'{}')}catch(_e){}
      const enabled=Object.prototype.hasOwnProperty.call(saved,key)?!!saved[key]:button.classList.contains('active');
      button.classList.toggle('active',enabled);button.classList.toggle('off',!enabled);button.setAttribute('aria-pressed',String(enabled));
      button.onclick=()=>{const next=button.getAttribute('aria-pressed')!=='true';button.classList.toggle('active',next);button.classList.toggle('off',!next);button.setAttribute('aria-pressed',String(next));let prefs={};try{prefs=JSON.parse(localStorage.getItem('lumenSettings')||'{}')}catch(_e){}prefs[key]=next;localStorage.setItem('lumenSettings',JSON.stringify(prefs));toast(next?'Preferência ativada.':'Preferência desativada.');};
    });
    $('#exportReports')?.addEventListener('click', exportReports);
    $('#editProfileBtn')?.addEventListener('click',()=>openProfileEdit(true));
    $('#cancelProfileEdit')?.addEventListener('click',()=>openProfileEdit(false));
    $('#profileEditForm')?.addEventListener('submit',saveProfile);
    $('#reportSearch')?.addEventListener('input', renderReports);
    $('#reportStatusFilter')?.addEventListener('change', renderReports);
    $('#reportTypeFilter')?.addEventListener('change', renderReports);
    $('#adminRegisterButton')?.addEventListener('click', () => { openModal('registerModal'); $('#registerRole').value = 'beneficiary'; });
    $('#addServiceButton')?.addEventListener('click', () => openModal('serviceModal'));
    $('#serviceForm')?.addEventListener('submit', addService);
    $('#requestSearch')?.addEventListener('input', renderAdminRequests);
    $('#requestStatusFilter')?.addEventListener('change', renderAdminRequests);
    $('#exportStats')?.addEventListener('click', exportStats);
    $('#exportStatsPdf')?.addEventListener('click', exportStatsPdf);
    $('#exportReportsPdf')?.addEventListener('click', exportReportsPdf);
    $('#inviteStaff')?.addEventListener('click', () => openModal('staffModal'));
    $('#staffForm')?.addEventListener('submit', createStaff);
    $('#adminProfileInfo')?.addEventListener('click', () => showView('perfil'));
    const chatForm = $('#chatForm');
    const chatInput = $('#chatInput', chatForm || document);
    chatForm?.addEventListener('submit', sendChatMessage);
    chatInput?.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        if (!chatForm.querySelector('button[type="submit"]').disabled) chatForm.requestSubmit();
      }
    });
    viewBinders.forEach((binder) => binder());
  }

  async function loadUserNotifications(){try{const r=await api('notifications'),items=r.data.notifications||[];$('#userNotifications').innerHTML=items.length?items.map(x=>`<article class=\"notice-item\"><strong>${esc(x.subject)}</strong><p>${esc(x.message)}</p><small>${esc(x.channel)} · ${esc(x.created_at)} · ${esc(x.status)}</small>${x.whatsapp_url?`<a class=\"whatsapp-link\" href=\"${esc(x.whatsapp_url)}\" target=\"_blank\" rel=\"noreferrer\">Abrir mensagem</a>`:''}</article>`).join(''):'<div class=\"empty-state\">Você não tem notificações recentes.</div>';}catch(e){toast(e.message);}}
  async function saveProfile(event){event.preventDefault();try{const data=Object.fromEntries(new FormData(event.currentTarget).entries());const r=await api('profile_update',data);toast(r.message);openProfileEdit(false);await loadProfile();const u=r.data.user;$('#topAvatar').textContent=u.initials;$('#sidebarName').textContent=u.name;$('#sidebarAvatar').textContent=u.initials;}catch(e){toast(e.message);}}

  async function sendChatMessage(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const input = $('#chatInput', form);
    const messages = $('#chatMessages');
    const button = form.querySelector('button[type="submit"]');
    if (!input || !messages || !button || button.disabled) return;

    const message = input.value.trim();
    if (!message) return;
    if (message.length > 4000) { toast('A mensagem deve ter no máximo 4.000 caracteres.'); return; }

    const history = [...messages.querySelectorAll('.chat-message[data-role]')]
      .slice(-12)
      .map((item) => ({
        role: item.dataset.role,
        content: $('.chat-message-body', item)?.textContent.trim() || '',
      }))
      .filter((item) => item.content);

    const sentMessage = appendChatMessage(messages, 'user', message);
    input.value = '';
    input.disabled = true;
    button.disabled = true;
    form.setAttribute('aria-busy', 'true');
    const loading = appendChatLoading(messages);

    try {
      const result = await api('chat', { message, history: JSON.stringify(history) });
      const reply = result.data?.reply;
      if (typeof reply !== 'string' || !reply.trim()) throw new Error('Não foi possível obter uma resposta do assistente.');
      loading.remove();
      appendChatMessage(messages, 'assistant', reply);
    } catch (error) {
      loading.remove();
      sentMessage.remove();
      appendChatMessage(messages, 'assistant', error.message || 'Não foi possível enviar sua mensagem. Tente novamente.', { error: true });
      input.value = message;
    } finally {
      input.disabled = false;
      button.disabled = false;
      form.removeAttribute('aria-busy');
      if (document.body.contains(input)) input.focus();
    }
  }

  function appendChatMessage(container, role, content, options = {}) {
    const message = document.createElement('div');
    message.className = `chat-message chat-message-${role}${options.error ? ' chat-message-error' : ''}`;
    if (!options.error) message.dataset.role = role;
    if (options.error) message.setAttribute('role', 'alert');

    const author = document.createElement('span');
    author.className = 'chat-message-author';
    author.textContent = options.error ? 'Aviso' : role === 'user' ? 'Você' : 'Assistente Lumen';

    const body = document.createElement('p');
    body.className = 'chat-message-body';
    body.textContent = content;
    message.append(author, body);
    container.append(message);
    container.scrollTop = container.scrollHeight;
    return message;
  }

  function appendChatLoading(container) {
    const loading = document.createElement('div');
    loading.className = 'chat-message chat-message-assistant chat-loading';
    loading.setAttribute('role', 'status');
    const spinner = document.createElement('span');
    spinner.className = 'chat-spinner';
    spinner.setAttribute('aria-hidden', 'true');
    const label = document.createElement('span');
    label.textContent = 'O assistente está respondendo...';
    loading.append(spinner, label);
    container.append(loading);
    container.scrollTop = container.scrollHeight;
    return loading;
  }

  async function submitSupportRequest(event) {
    event.preventDefault();
    try { const data = new FormData(event.currentTarget); const result = await api('support_request_create', data, true); toast(result.message); setTimeout(() => showView('solicitacoes'), 500); } catch (error) { toast(error.message); }
  }

  async function submitReport(event) {
    event.preventDefault();
    try { const data = new FormData(event.currentTarget); const result = await api('report_create', data, true); toast(result.message); setTimeout(() => showView('solicitacoes'), 600); } catch (error) { toast(error.message); }
  }

  async function addContact(event) {
    event.preventDefault();
    try { const data = new FormData(event.currentTarget); const result = await api('emergency_add', data, true); event.currentTarget.reset(); toast(result.message); loadViewData('contatos'); } catch (error) { toast(error.message); }
  }

  async function removeContact(id) {
    if (!window.confirm('Remover esta pessoa da sua rede de emergência?')) return;
    try { const result = await api('emergency_delete', { id }); toast(result.message); loadViewData('contatos'); } catch (error) { toast(error.message); }
  }

  async function addService(event) {
    event.preventDefault();
    try { const result = await api('service_add', Object.fromEntries(new FormData(event.currentTarget).entries())); closeModal('serviceModal'); event.currentTarget.reset(); toast(result.message); loadViewData('admin-rede'); } catch (error) { toast(error.message); }
  }

  function statusBadge(status) {
    const map = { pending: ['Nova', 'pink'], triage: ['Em triagem', 'yellow'], approved: ['Aprovada', 'green'], forwarded: ['Encaminhada', 'green'], rejected: ['Desaprovada', 'pink'], active: ['Ativa', 'green'], inactive: ['Desativada', 'gray'], resolved: ['Concluída', 'green'], invited: ['Convite enviado', 'yellow'], accepted: ['Aceito', 'green'] };
    const item = map[status] || [status, 'yellow'];
    return `<span class="status ${item[1]}">${esc(item[0])}</span>`;
  }

  async function loadViewData(view) {
    if (viewLoaders[view]) { try { await viewLoaders[view](); } catch (error) { toast(error.message); } return; }
    try {
      if (view === 'dashboard') { const result = await api('dashboard'); $('#statRequests').textContent = result.data.requests; $('#statContacts').textContent = result.data.contacts; }
      if (view === 'solicitacoes') loadRequests();
      if (view === 'contatos') loadContacts();
      if (view === 'perfil') loadProfile();
      if (view === 'notificacoes') loadUserNotifications();
      if (view === 'historico') loadHistory();
      if (view === 'admin-dashboard') loadAdminDashboard();
      if (view === 'admin-denuncias') loadReports();
      if (view === 'admin-beneficiarias') loadBeneficiaries();
      if (view === 'admin-rede') loadServices();
      if (view === 'admin-solicitacoes') loadAdminRequests();
      if (view === 'admin-stats') loadAdminStats();
      if (view === 'admin-notificacoes') loadAdminNotifications();
      if (view === 'admin-seguranca') loadSecurity();
    } catch (error) { toast(error.message); }
  }

  async function loadRequests() {
    const result = await api('my_requests');
    const target = $('#requestsContent');
    const items = [...(result.data.requests || []), ...(result.data.reports || []).map((report) => ({ ...report, isReport: true }))];
    target.innerHTML = items.length ? items.map((item) => `<div class="request-card"><div class="req-icon">${item.isReport ? '▣' : '♡'}</div><div><h4>${esc(item.isReport ? `Denúncia · ${item.category}` : item.category)}</h4><p>${esc(item.isReport ? item.protocol : 'Solicitação de atendimento')} · ${esc(item.created_at)}</p></div>${statusBadge(item.status)}</div>`).join('') : '<div class="empty-state">Você ainda não possui solicitações.</div>';
  }

  async function loadContacts() {
    const result = await api('emergency_list');
    const target = $('#contactsContent');
    target.innerHTML = result.data.contacts?.length ? result.data.contacts.map((contact) => `<div class="contact-card"><div class="req-icon">♡</div><div class="contact-info"><h4>${esc(contact.name)}</h4><p>${esc(contact.email)}<br>${esc(contact.phone)}<br>${esc(contact.address)}</p>${contact.whatsapp_url ? `<a class="whatsapp-link" href="${esc(contact.whatsapp_url)}" target="_blank" rel="noreferrer">Abrir mensagem no WhatsApp</a>` : ''}</div><div class="contact-actions">${statusBadge(contact.status)}<button class="table-action danger" type="button" data-remove-contact="${contact.id}">Remover</button></div></div>`).join('') : '<div class="empty-state">Nenhum contato cadastrado. Adicione uma pessoa de confiança ao lado.</div>';
    $$('[data-remove-contact]').forEach((button) => button.onclick = () => removeContact(button.dataset.removeContact));
  }

  async function loadProfile() {
    const result = await api('profile'); const user = result.data.user;
    $('#profileAvatar').textContent = user.initials; $('#profileName').textContent = user.name; $('#profileRole').textContent = user.role_label;
    $('#profileEditName').value=user.name||'';$('#profileEditEmail').value=user.email||'';$('#profileEditPhone').value=user.phone||'';
    $('#profileInfo').innerHTML = [['Nome completo', user.name], ['E-mail', user.email], ['Telefone', user.phone || 'Não informado'], ['CPF', user.cpf || 'Não informado'], ['RG', user.rg || 'Não informado'], ['Endereço', `${user.address || 'Não informado'}${user.cep ? ` · CEP ${user.cep}` : ''}`]].map(([label, value]) => `<div class="info-row"><small>${esc(label)}</small><strong>${esc(value)}</strong></div>`).join('');
  }

  async function loadHistory() {
    const result = await api('my_requests'); const items = [...(result.data.reports || []).map((report) => ({ ...report, title: report.category })), ...(result.data.requests || []).map((request) => ({ ...request, title: request.category, protocol: 'Atendimento' }))];
    $('#historyContent').innerHTML = items.length ? items.map((item) => `<tr><td>${esc(item.protocol)}</td><td>${esc(item.title)}</td><td>${esc(item.created_at)}</td><td>${statusBadge(item.status)}</td><td>${item.id && item.status === 'rejected' ? `<button class="table-action" data-appeal="${item.id}">Recorrer</button>` : '—'}</td></tr>`).join('') : '<tr><td colspan="5" class="empty-state">Nenhum registro encontrado.</td></tr>';
    $$('[data-appeal]').forEach((button) => button.onclick = () => openAppeal(button.dataset.appeal));
  }

  async function openAppeal(id) {
    const text = window.prompt('Inclua novas informações ou provas para recorrer da desaprovação:');
    if (!text) return;
    try { const result = await api('report_appeal', { id, appeal_text: text }); toast(result.message); loadHistory(); } catch (error) { toast(error.message); }
  }

  async function loadAdminDashboard() {
    const result = await api('admin_summary'); const data = result.data;
    $('#adminPending').textContent = data.pending; $('#adminTriage').textContent = data.triage; $('#adminActive').textContent = data.active; $('#adminResolved').textContent = data.resolved; $('#pendingBadge').textContent = data.pending;
    $('#recentReports').innerHTML = data.recent.length ? data.recent.map((report) => `<div class="request-card recent-report-card"><div class="req-icon urgent">!</div><div><h4>${esc(report.category)}</h4><p>${esc(report.protocol)} · ${esc(report.name)} · ${esc(report.created_at)}</p></div>${statusBadge(report.status)}<button class="table-action" type="button" data-open-case="${report.id}">Ver mais</button></div>`).join('') : '<div class="empty-state">Nenhuma denúncia recente.</div>';
    $$('#recentReports [data-open-case]').forEach((button) => button.onclick = () => openCase(button.dataset.openCase));
  }

  async function loadReports() { const result = await api('admin_reports'); state.reports = result.data.reports || []; renderReports(); }
  async function quickForwardReport(id) {
    const report = state.reports.find((item) => String(item.id) === String(id));
    if (!report) return;
    if (!window.confirm(`Encaminhar a denúncia ${report.protocol} para acompanhamento da equipe?`)) return;
    try { const result = await api('report_decide', { id, decision: 'approve', notes: 'Encaminhada pela triagem rápida.' }); toast(result.message); loadReports(); } catch (error) { toast(error.message); }
  }
  function renderReports() {
    const search = ($('#reportSearch')?.value || '').toLowerCase(); const status = $('#reportStatusFilter')?.value || ''; const type = $('#reportTypeFilter')?.value || '';
    const rows = state.reports.filter((report) => (!status || report.status === status) && (!type || report.category === type) && (!search || `${report.protocol} ${report.name} ${report.category}`.toLowerCase().includes(search)));
    $('#reportsTable').innerHTML = rows.length ? rows.map((report) => `<tr><td><strong>${esc(report.protocol)}</strong></td><td>${esc(report.name)}</td><td>${esc(report.category)}</td><td>${esc(report.created_at)}</td><td>${statusBadge(report.status)}</td><td class="report-actions"><button class="table-action" type="button" data-open-case="${report.id}">Revisar</button>${['pending','triage'].includes(report.status) ? `<button class="primary compact-action" type="button" data-quick-forward="${report.id}">Encaminhar</button>` : ''}</td></tr>`).join('') : '<tr><td colspan="6" class="empty-state">Nenhuma denúncia encontrada.</td></tr>';
    $$('[data-open-case]').forEach((button) => button.onclick = () => openCase(button.dataset.openCase));
    $$('[data-quick-forward]').forEach((button) => button.onclick = () => quickForwardReport(button.dataset.quickForward));
    $$('[data-report-filter]').forEach((button) => button.onclick = () => { const value = button.dataset.reportFilter || ''; const select = $('#reportStatusFilter'); if (select) select.value = value; $$('[data-report-filter]').forEach((item) => item.classList.toggle('active', item === button)); renderReports(); });
  }

  async function openCase(id) {
    try { const result = await api('report_detail', { id }); const report = result.data.report; const files = result.data.files || []; const history = result.data.history || []; $('#caseContent').innerHTML = `<div class="eyebrow">PROTOCOLO ${esc(report.protocol)}</div><h2>${esc(report.category)}</h2><p class="helper-text">Recebida em ${esc(report.created_at)} · ${esc(report.name)}</p><div class="case-detail-grid"><div class="case-detail"><small>Quando</small><strong>${esc(report.occurred_when)}</strong></div><div class="case-detail"><small>Está em segurança?</small><strong>${esc(report.safe_now)}</strong></div><div class="case-detail"><small>Local</small><strong>${esc(report.location)}</strong></div><div class="case-detail"><small>Status</small><strong>${statusBadge(report.status)}</strong></div></div><h3>Relato</h3><div class="case-full">${esc(report.narrative)}</div>${files.length ? `<p class="helper-text">Anexos: ${files.map((file) => esc(file.original_name)).join(', ')}</p>` : ''}<h3>Histórico e acessos</h3><div class="metric-list">${history.map(x=>`<div class="metric-row"><span>${esc(x.created_at)} · ${esc(x.actor||'Equipe')} · ${esc(x.action)}</span><b>${esc(x.details||'')}</b></div>`).join('')}</div><div class="field" style="margin-top:16px"><label for="adminNotes">Observação da triagem</label><textarea id="adminNotes" placeholder="Informe o motivo do encaminhamento ou da desaprovação..."></textarea></div><div class="case-actions">${report.status !== 'rejected' && report.status !== 'forwarded' ? `<button class="secondary" type="button" data-decide="reject" data-id="${report.id}">Desaprovar</button><button class="primary" type="button" data-decide="approve" data-id="${report.id}">Aprovar e encaminhar</button>` : ''}<button class="secondary" type="button" data-close-modal="caseModal">Fechar</button></div>`; openModal('caseModal'); bindModalButtons(); $$('[data-decide]').forEach((button) => button.onclick = () => decideReport(button.dataset.id, button.dataset.decide)); } catch (error) { toast(error.message); }
  }

  async function decideReport(id, decision) {
    const notes = $('#adminNotes')?.value || '';
    if (decision === 'reject' && !notes.trim()) { toast('Informe o motivo da desaprovação para que a beneficiária possa recorrer.'); return; }
    try { const result = await api('report_decide', { id, decision, notes }); closeModal('caseModal'); toast(result.message); loadReports(); } catch (error) { toast(error.message); }
  }

  async function loadBeneficiaries() {
    const result = await api('admin_beneficiaries'); const rows = result.data.users || [];
    $('#beneficiariesContent').innerHTML = rows.length ? rows.map((user) => `<tr><td>${esc(user.name)}</td><td>${esc(user.email)}<br>${esc(user.phone || '')}</td><td>${esc(user.created_at)}</td><td>${esc(user.active_cases)}</td><td><button class="table-action" type="button" data-view-profile="${user.id}">Ver perfil</button></td></tr>`).join('') : '<tr><td colspan="5" class="empty-state">Nenhuma beneficiária cadastrada.</td></tr>';
    $$('[data-view-profile]').forEach((button) => button.onclick = () => openBeneficiary(button.dataset.viewProfile));
  }

  async function openBeneficiary(id){try{const r=await api('admin_beneficiary_detail',{id}),p=r.data.person;const reports=p.reports||[],requests=p.requests||[];$('#caseContent').innerHTML=`<div class="eyebrow">PERFIL RESTRITO · ${esc(p.created_at)}</div><h2>${esc(p.name)}</h2><p class="helper-text">${esc(p.email)} · ${esc(p.phone||'Contato não informado')} · ${statusBadge(p.status)}</p><h3>Denúncias e encaminhamentos</h3><div class="request-list">${reports.length?reports.map(x=>`<div class="request-card"><div><h4>${esc(x.category)}</h4><p>${esc(x.protocol)} · ${esc(x.created_at)}</p></div>${statusBadge(x.status)}</div>`).join(''):'<div class="empty-state">Nenhum registro de denúncia.</div>'}</div><h3 style="margin-top:18px">Solicitações de atendimento</h3><div class="request-list">${requests.length?requests.map(x=>`<div class="request-card"><div><h4>${esc(x.category)}</h4><p>${esc(x.created_at)}</p></div>${statusBadge(x.status)}</div>`).join(''):'<div class="empty-state">Nenhuma solicitação.</div>'}</div><div class="case-actions">${window.LUMEN.role==='admin'?`<button class="secondary" data-toggle-beneficiary="${p.id}" data-status="${esc(p.status)}">${p.status==='active'?'Desativar conta':'Reativar conta'}</button>`:''}<button class="secondary" data-close-modal="caseModal">Fechar</button></div>`;openModal('caseModal');bindModalButtons();$('[data-toggle-beneficiary]')?.addEventListener('click',async e=>{if(!window.confirm('Confirma alterar o acesso desta beneficiária? Os registros de atendimento serão preservados.'))return;try{const out=await api('admin_beneficiary_toggle',{id:p.id});toast(out.message);closeModal('caseModal');loadBeneficiaries();}catch(err){toast(err.message);}});}catch(e){toast(e.message);}}

  async function loadServices() {
    const result = await api('services'); const services = result.data.services || [];
    $('#servicesContent').innerHTML = services.length ? services.map((service) => `<div class="request-card"><div class="req-icon">♧</div><div><h4>${esc(service.name)}</h4><p>${esc(service.category)} · ${esc(service.city)}${service.phone ? ` · ${esc(service.phone)}` : ''}</p></div>${statusBadge(service.status)}</div>`).join('') : '<div class="empty-state">Nenhum serviço cadastrado.</div>';
  }

  let adminRequests = [];
  async function createStaff(event){event.preventDefault();try{const data=Object.fromEntries(new FormData(event.currentTarget).entries());const r=await api('admin_staff_create',data);closeModal('staffModal');event.currentTarget.reset();toast(r.message);loadSecurity();}catch(e){toast(e.message);}}
  async function loadAdminRequests() { const r=await api('admin_requests'); adminRequests=r.data.requests||[]; renderAdminRequests(); }
  function renderAdminRequests() {
    const target=$('#requestsAdminTable'); if(!target)return;
    const q=($('#requestSearch')?.value||'').toLowerCase(), status=$('#requestStatusFilter')?.value||'';
    const filtered=adminRequests.filter(x=>(!status||x.status===status)&&(!q||`${x.name} ${x.category}`.toLowerCase().includes(q)));
    target.innerHTML=filtered.length?filtered.map(x=>`<tr><td><strong>${esc(x.name)}</strong><br><small>${esc(x.email)}</small></td><td>${esc(x.category)}</td><td>${esc(x.created_at)}</td><td>${statusBadge(x.status)}</td><td><button class="table-action" data-request="${x.id}">Atualizar</button> <button class="table-action" data-request-detail="${x.id}">Detalhes</button></td></tr>`).join(''):'<tr><td colspan="5" class="empty-state">Nenhuma solicitação encontrada.</td></tr>';
    $$('[data-request]').forEach(b=>b.onclick=()=>updateAdminRequest(b.dataset.request));
    $$('[data-request-detail]').forEach(b=>b.onclick=()=>{const x=adminRequests.find(a=>a.id==b.dataset.requestDetail);if(x){$('#caseContent').innerHTML=`<div class="eyebrow">SOLICITAÇÃO DE ATENDIMENTO</div><h2>${esc(x.category)}</h2><p class="helper-text">${esc(x.name)} · ${esc(x.created_at)}</p><div class="case-full">${esc(x.description)}</div><p class="helper-text">Preferência de contato: ${esc(x.preferred_contact||'Não informada')}</p><div class="case-actions"><button class="secondary" data-close-modal="caseModal">Fechar</button></div>`;openModal('caseModal');bindModalButtons();}});
  }
  async function updateAdminRequest(id) {
    const req=adminRequests.find(x=>x.id==id); if(!req)return;
    const options={pending:'Recebida',triage:'Em triagem',forwarded:'Encaminhada',resolved:'Concluída'};
    const status=window.prompt(`Atualizar status (pending / triage / forwarded / resolved). Status atual: ${req.status}`,'triage');
    if(!status)return; const note=window.prompt('Nota interna opcional (não informe detalhes desnecessários):','')||'';
    try{const r=await api('admin_request_update',{id,status:status.trim(),note});toast(r.message);loadAdminRequests();}catch(e){toast('Use um dos status válidos: ' + Object.keys(options).join(', ') + '. ' + e.message);}
  }
  async function loadAdminStats(){const r=await api('admin_reports_stats'),d=r.data;$('#statsBeneficiaries').textContent=d.beneficiaries;$('#statsActive').textContent=d.active_requests;const total=(d.by_status||[]).reduce((n,x)=>n+Number(x.total),0);$('#statsReports').textContent=total;$('#statsForwarded').textContent=(d.by_status||[]).find(x=>x.status==='forwarded')?.total||0;const labels={pending:'Nova',triage:'Em triagem',forwarded:'Encaminhada',rejected:'Desaprovada'};$('#statsStatus').innerHTML=(d.by_status||[]).map(x=>`<div class="metric-row"><span>${esc(labels[x.status]||x.status)}</span><b>${esc(x.total)}</b></div>`).join('')||'<div class="empty-state">Sem dados registrados.</div>';$('#statsCategory').innerHTML=(d.by_category||[]).map(x=>`<div class="metric-row"><span>${esc(x.category)}</span><b>${esc(x.total)}</b></div>`).join('')||'<div class="empty-state">Sem dados registrados.</div>';}
  async function loadAdminNotifications(){const r=await api('admin_notifications'),items=r.data.notifications||[];$('#adminNotifications').innerHTML=items.length?items.map(x=>`<article class="notice-item"><strong>${esc(x.subject)} <span class="status ${x.status==='sent'?'green':'yellow'}">${esc(x.status)}</span></strong><p>${esc(x.message)}</p><small>${esc(x.channel)} · ${esc(x.created_at)}</small></article>`).join(''):'<div class="empty-state">Nenhuma notificação administrativa recente.</div>';}
  async function loadSecurity(){
    if(window.LUMEN.role!=='admin'){ $('#staffTable').innerHTML='<tr><td colspan="5" class="empty-state">A lista da equipe e o log detalhado são restritos a administradoras.</td></tr>';$('#auditTable').innerHTML='<tr><td colspan="5" class="empty-state">Acesso restrito.</td></tr>';$('#inviteStaff')?.classList.add('hidden');return; }
    const [s,a]=await Promise.all([api('admin_staff'),api('admin_audit')]);const staff=s.data.staff||[], events=a.data.events||[];
    $('#staffTable').innerHTML=staff.length?staff.map(x=>`<tr><td>${esc(x.name)}<br><small>${esc(x.email)}</small></td><td>${esc(x.role)}</td><td>${statusBadge(x.status)}</td><td>${esc(x.created_at)}</td><td><button class="table-action" data-toggle-staff="${x.id}" data-status="${x.status}">${x.status==='active'?'Desativar':'Reativar'}</button></td></tr>`).join(''):'<tr><td colspan="5" class="empty-state">Nenhum perfil de equipe encontrado.</td></tr>';
    $('#auditTable').innerHTML=events.length?events.map(x=>`<tr><td>${esc(x.created_at)}</td><td>${esc(x.actor||'Conta removida')}</td><td>${esc(x.action)}</td><td>${esc(x.entity_type)} #${esc(x.entity_id||'—')}</td><td>${esc(x.details||'—')}</td></tr>`).join(''):'<tr><td colspan="5" class="empty-state">Nenhuma ação registrada ainda.</td></tr>';
    $$('[data-toggle-staff]').forEach(b=>b.onclick=async()=>{if(!window.confirm('Confirma alterar o acesso desta pessoa da equipe? O histórico será mantido.'))return;try{const r=await api('admin_user_toggle',{id:b.dataset.toggleStaff});toast(r.message);loadSecurity();}catch(e){toast(e.message);}});
  }
  function filteredAdminReports(){
    const search=($('#reportSearch')?.value||'').toLowerCase(),status=$('#reportStatusFilter')?.value||'',type=$('#reportTypeFilter')?.value||'';
    return state.reports.filter(r=>(!status||r.status===status)&&(!type||r.category===type)&&(!search||`${r.protocol} ${r.name} ${r.category}`.toLowerCase().includes(search)));
  }
  function openPdfPrint(title, body){
    const win=window.open('','_blank');
    if(!win){toast('Permita a abertura de pop-ups para preparar o PDF.');return;}
    const safeTitle=esc(title);
    win.document.open();win.document.write(`<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>${safeTitle} — LUMEN</title><style>
      *{box-sizing:border-box}body{margin:0;padding:34px;color:#25182d;font:13px Arial,sans-serif}header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #54108f;padding-bottom:14px;margin-bottom:22px}h1{font-size:22px;color:#54108f;margin:0 0 6px}p{margin:5px 0;color:#594b60}small{color:#75657d}.conf{border:1px solid #b88cc9;color:#54108f;border-radius:20px;padding:6px 10px;font-size:10px;font-weight:bold}section{margin:0 0 20px}h2{font-size:15px;color:#54108f;border-bottom:1px solid #e5d9eb;padding-bottom:7px}table{width:100%;border-collapse:collapse;font-size:11px}th,td{text-align:left;padding:9px;border-bottom:1px solid #e8e0eb}th{background:#f4eafa;color:#4b285c}.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.kpi{border:1px solid #e5d9eb;border-radius:8px;padding:12px}.kpi b{display:block;font-size:20px;color:#54108f}.kpi span{font-size:10px;color:#65566d}.foot{border-top:1px solid #e5d9eb;padding-top:10px;font-size:9px;color:#776b7c}@page{size:A4;margin:14mm}@media print{body{padding:0}button{display:none}}
      </style></head><body><header><div><h1>${safeTitle}</h1><p>LUMEN · Relatório administrativo agregado</p><small>Gerado em ${esc(new Date().toLocaleString('pt-BR'))}</small></div><span class="conf">CONFIDENCIAL</span></header>${body}<p class="foot">Documento de uso interno. Encaminhamento ou arquivamento administrativo não significa que a situação foi resolvida. Proteja e armazene este arquivo conforme a política de privacidade do LUMEN.</p><script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));</script></body></html>`);win.document.close();win.focus();
  }
  function exportStatsPdf(){
    const kpis=[['Beneficiárias cadastradas',$('#statsBeneficiaries')?.textContent||'—'],['Solicitações em andamento',$('#statsActive')?.textContent||'—'],['Denúncias recebidas',$('#statsReports')?.textContent||'—'],['Encaminhadas',$('#statsForwarded')?.textContent||'—']];
    const rows=(selector)=>$$(`${selector} .metric-row`).map(row=>`<tr><td>${esc(row.children[0]?.textContent||'')}</td><td>${esc(row.children[1]?.textContent||'')}</td></tr>`).join('')||'<tr><td colspan="2">Sem dados registrados.</td></tr>';
    const body=`<section class="kpis">${kpis.map(x=>`<div class="kpi"><b>${esc(x[1])}</b><span>${esc(x[0])}</span></div>`).join('')}</section><section><h2>Denúncias por status</h2><table><thead><tr><th>Status</th><th>Total</th></tr></thead><tbody>${rows('#statsStatus')}</tbody></table></section><section><h2>Categorias registradas</h2><table><thead><tr><th>Categoria</th><th>Total</th></tr></thead><tbody>${rows('#statsCategory')}</tbody></table></section>`;
    openPdfPrint('Relatórios e estatísticas',body);
  }
  function exportReportsPdf(){
    const rows=filteredAdminReports();
    const body=`<p>Registros: ${rows.length} · Filtros atuais aplicados. Este documento não contém nomes, endereços, contatos, relatos ou anexos.</p><table><thead><tr><th>Protocolo</th><th>Categoria</th><th>Recebida em</th><th>Status</th></tr></thead><tbody>${rows.map(r=>`<tr><td>${esc(r.protocol)}</td><td>${esc(r.category)}</td><td>${esc(r.created_at)}</td><td>${esc(({pending:'Nova',triage:'Em triagem',forwarded:'Encaminhada',rejected:'Desaprovada'})[r.status]||r.status)}</td></tr>`).join('')||'<tr><td colspan="4">Nenhum registro corresponde aos filtros.</td></tr>'}</tbody></table>`;
    openPdfPrint('Relatório de denúncias',body);
  }

  function exportStats(){const rows=[['Indicador','Valor'],['Beneficiárias cadastradas',$('#statsBeneficiaries')?.textContent||'0'],['Solicitações em andamento',$('#statsActive')?.textContent||'0'],['Denúncias recebidas',$('#statsReports')?.textContent||'0'],['Encaminhadas',$('#statsForwarded')?.textContent||'0']];const csv=rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(';')).join('\n');const a=document.createElement('a');a.href=URL.createObjectURL(new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8'}));a.download='lumen-indicadores-agregados.csv';a.click();URL.revokeObjectURL(a.href);toast('Indicadores agregados exportados.');}

  function exportReports() {
    const rows = [['Protocolo','Denunciante','Categoria','Data','Status'], ...filteredAdminReports().map((report) => [report.protocol, report.name, report.category, report.created_at, report.status])];
    const csv = rows.map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(';')).join('\n');
    const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8' })); link.download = 'relatorio-lumen.csv'; link.click(); URL.revokeObjectURL(link.href); toast('Relatório exportado.');
  }

  function removeOrphanedDemoElements() {
    document.querySelectorAll('.demo-hint').forEach((element) => element.remove());
    document.querySelectorAll('body > button.nav-item[data-view="mensagens"]').forEach((element) => element.remove());
  }

  function initialize() {
    removeOrphanedDemoElements();
    applyAccessibilityPreferences(); bindLanding(); bindLogin(); bindGlobal(); bindNavigation();
    if (!window.LUMEN.authenticated) {
      const landing = $('#landingScreen');
      const login = $('#loginScreen');
      if (landing) { landing.hidden = false; landing.classList.remove('hidden'); }
      if (login) { login.hidden = true; login.classList.add('hidden'); }
      return;
    }
    const role = window.LUMEN.role || 'beneficiary';
    const isAdmin = role === 'admin';
    const isSupporter = ['supporter', 'professional'].includes(role);
    state.role = role;
    $('#userNav')?.classList.toggle('hidden', isAdmin || isSupporter);
    $('#adminNav')?.classList.toggle('hidden', !isAdmin);
    $('#supporterNav')?.classList.toggle('hidden', !isSupporter);
    if ($('#areaLabel')) $('#areaLabel').textContent = isAdmin ? 'Área da equipe' : (isSupporter ? 'Área do apoiador' : 'Área da beneficiária');
    if (isSupporter) prepareSupporterMenu();
    if (isAdmin) { showView('admin-dashboard'); return; }
    if (isSupporter) {
      const ready = window.LUMEN.verification === 'approved';
      showView(ready ? 'supporter-dashboard' : 'supporter-verificacao');
      return;
    }
    showView('dashboard');
  }
  // Aponta os itens do menu de perfil para as telas do apoiador.
  function prepareSupporterMenu() {
    $$('#profileMenu [data-view]').forEach((button) => {
      if (button.dataset.view === 'perfil') button.dataset.view = 'supporter-perfil';
      if (button.dataset.view === 'privacidade') button.dataset.view = 'supporter-seguranca';
    });
    bindNavigation();
  }
  // Aguarda os módulos externos registrarem suas telas antes de abrir a primeira.
  // Ponte usada por supporter.js (e por módulos futuros) para reaproveitar estas funções.
  window.LumenApp = {
    api, toast, esc, showView, openModal, closeModal, bindModalButtons, bindNavigation, statusBadge, goToLogin, state,
    registerView: (name, loader) => { viewLoaders[name] = loader; },
    registerBinder: (binder) => { viewBinders.push(binder); },
  };

  setTimeout(initialize, 0);
})();
  function openNotificationsView() { const role = window.LUMEN.role; showView(role === 'admin' ? 'admin-notificacoes' : (['supporter','professional'].includes(role) ? 'supporter-notificacoes' : 'notificacoes')); }
