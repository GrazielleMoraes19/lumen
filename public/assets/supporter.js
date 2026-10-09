/**
 * LUMEN — área do apoiador / profissional.
 * Usa a ponte window.LumenApp exposta por app.js para reaproveitar api(), modais e navegação.
 */
(() => {
  const app = window.LumenApp;
  if (!app) return;
  const { api, esc, toast, showView, openModal, closeModal, bindModalButtons, registerView, registerBinder, statusBadge, goToLogin } = app;

  const sapi = (action, payload = {}, multipart = false) => api(action, payload, multipart, 'supporter.php');
  const el = (selector) => document.querySelector(selector);
  const view = (selector) => document.querySelector(`#viewContainer ${selector}`);
  const state = { area: null, type: 'professional', requests: [], assignments: [], conversations: [], scope: 'active', conversationId: null, documents: [] };

  const badge = (label, tone) => `<span class="status ${tone}">${esc(label)}</span>`;
  const verificationBadge = (status) => ({ approved: ['Verificado', 'green'], rejected: ['Não aprovado', 'pink'], suspended: ['Suspenso', 'red'] }[status] || ['Em análise', 'yellow']);
  const documentBadge = (status) => ({ approved: ['Aprovado', 'green'], rejected: ['Recusado', 'pink'] }[status] || ['Aguardando revisão', 'yellow']);
  const assignmentBadge = (status) => ({ accepted: ['Aceito', 'yellow'], in_progress: ['Em atendimento', 'green'], completed: ['Concluído', 'green'], cancelled: ['Cancelado', 'pink'], declined: ['Recusado', 'pink'] }[status] || [status, 'gray']);
  const appointmentBadge = (status) => ({ scheduled: ['Agendado', 'yellow'], confirmed: ['Confirmado', 'green'], done: ['Realizado', 'green'], missed: ['Não compareceu', 'pink'], cancelled: ['Cancelado', 'pink'] }[status] || [status, 'gray']);
  const kindLabel = (kind) => ({ first: 'Primeiro atendimento', return: 'Retorno', follow_up: 'Acompanhamento' }[kind] || 'Atendimento');
  const modeLabel = (mode) => ({ online: 'Online', presencial: 'Presencial', hibrido: 'Híbrido' }[mode] || 'A definir');
  const riskLabel = (category) => ({ risco_beneficiaria: 'Risco à beneficiária', risco_equipe: 'Risco à equipe ou ao apoiador', assedio: 'Assédio ou conduta inadequada', fraude: 'Fraude ou uso indevido', outro: 'Outra situação' }[category] || 'Situação reportada');
  const urgencyLabel = (urgency) => ({ imediata: ['Imediata', 'red'], attention: ['Atenção', 'yellow'], informativa: ['Informativa', 'gray'] }[urgency] || [urgency, 'gray']);
  const emptyState = (message) => `<div class="empty-state">${esc(message)}</div>`;
  const requestCard = (title, meta, badgeHtml) => `<div class="request-card"><div class="req-icon">♡</div><div><h4>${esc(title)}</h4><p>${meta}</p></div>${badgeHtml}</div>`;

  /* ------------------------------------------------------------------ Estado */

  async function loadAreaState() {
    const result = await sapi('state');
    state.area = result.data.state;
    state.type = state.area.type;
    state.documents = result.data.documents || [];
    const badgeElement = el('#supporterDocsBadge');
    if (badgeElement) badgeElement.textContent = Object.keys(state.area.pending_documents || {}).length;
    return result.data;
  }

  const canWork = () => !!(state.area && state.area.can_work);
  const workGuard = () => { if (!canWork()) { showView('supporter-verificacao'); toast('Conclua a verificação do cadastro para acessar esta área.'); return false; } return true; };

  /* ------------------------------------------------------------------ Verificação */

  async function loadVerification() {
    const data = await loadAreaState();
    const area = data.state;
    const info = verificationBadge(area.verification);
    const badgeElement = el('#supVerificationBadge');
    if (badgeElement) { badgeElement.className = `status ${info[1]}`; badgeElement.textContent = info[0]; }

    const pendingDocuments = Object.values(area.pending_documents || {});
    const missing = area.missing || [];
    const banner = el('#supVerificationBanner');
    if (banner) {
      if (area.can_work) {
        banner.className = 'state-banner ok';
        banner.innerHTML = `<strong>Cadastro verificado</strong><p>Seu perfil está liberado para atendimentos. Você pode receber solicitações compatíveis com suas especialidades.</p>`;
      } else if (area.verification === 'rejected' || area.verification === 'suspended') {
        banner.className = 'state-banner warn';
        banner.innerHTML = `<strong>Atenção à verificação</strong><p>${esc(area.profile && area.profile.verification_notes ? area.profile.verification_notes : 'A equipe Lumen solicitou ajustes no seu cadastro.')}</p>`;
      } else {
        banner.className = 'state-banner';
        banner.innerHTML = `<strong>O que falta agora</strong><p>${esc(pendingDocuments.length ? `Envie ${pendingDocuments.length} documento(s) pendente(s)${missing.length ? ' e complete os dados faltantes' : ''}.` : 'Complete os dados faltantes do seu perfil profissional.')}</p>`;
      }
    }

    const checklist = el('#supVerificationChecklist');
    if (checklist) {
      const rows = [];
      rows.push(`<div class="metric-row"><span>Perfil: ${esc(data.type_label)}</span><b>${area.profile ? 'Cadastrado' : 'Pendente'}</b></div>`);
      rows.push(`<div class="metric-row"><span>Situação da análise</span><b>${esc(info[0])}</b></div>`);
      rows.push(`<div class="metric-row"><span>Documentos aprovados</span><b>${Object.keys(data.required_documents || {}).length - pendingDocuments.length}/${Object.keys(data.required_documents || {}).length}</b></div>`);
      missing.forEach((item) => rows.push(`<div class="metric-row"><span>${esc(item)}</span><b>${badge('Pendente', 'yellow')}</b></div>`));
      checklist.innerHTML = rows.join('');
    }

    const documents = el('#supVerificationDocuments');
    if (documents) {
      documents.innerHTML = Object.entries(data.required_documents || {}).map(([kind, label]) => {
        const sent = (state.documents || []).find((doc) => doc.kind === kind);
        const info2 = sent ? documentBadge(sent.status) : ['Não enviado', 'yellow'];
        return `<article class="notice-item"><strong>${esc(label)} ${badge(info2[0], info2[1])}</strong>${sent && sent.review_notes ? `<p>Observação da equipe: ${esc(sent.review_notes)}</p>` : '<p>Envie um arquivo PDF, JPG ou PNG de até 10 MB.</p>'}</article>`;
      }).join('');
    }
  }

  /* ------------------------------------------------------------------ Dashboard */

  async function loadSupporterDashboard() {
    const result = await sapi('dashboard');
    const data = result.data;
    state.area = data.state;
    state.type = data.state.type;
    const counts = data.counts || {};
    if (el('#supAvailable')) el('#supAvailable').textContent = counts.available ?? 0;
    if (el('#supActive')) el('#supActive').textContent = counts.active ?? 0;
    if (el('#supUpcoming')) el('#supUpcoming').textContent = counts.upcoming ?? 0;
    if (el('#supUnread')) el('#supUnread').textContent = counts.unread ?? 0;
    if (el('#supDashboardSubtitle')) el('#supDashboardSubtitle').textContent = `Perfil: ${data.type_label}. ${counts.completed ?? 0} atendimento(s) já concluído(s).`;
    const docsBadge = el('#supporterDocsBadge');
    if (docsBadge) docsBadge.textContent = Object.keys(data.state.pending_documents || {}).length;

    const banner = el('#supStateBanner');
    if (banner) {
      if (data.state.can_work) {
        banner.className = 'state-banner hidden';
        banner.innerHTML = '';
      } else {
        banner.className = 'state-banner';
        banner.innerHTML = `<strong>Cadastro em análise</strong><p>Você ainda não recebe solicitações. Envie os documentos pendentes para liberar o atendimento.</p><button class="secondary" data-view="supporter-documentos" type="button">Ver documentos</button>`;
        app.bindNavigation();
      }
    }

    if (el('#supNextAppointments')) {
      el('#supNextAppointments').innerHTML = (data.appointments || []).length ? data.appointments.map((item) => requestCard(`${kindLabel(item.kind)} · ${item.beneficiary_name}`, `${esc(item.scheduled_at)} · ${esc(modeLabel(item.mode))}${item.location ? ` · ${esc(item.location)}` : ''}`, badge(...appointmentBadge(item.status)))).join('') : emptyState('Nenhum atendimento agendado.');
    }
    if (el('#supLatestRequests')) {
      el('#supLatestRequests').innerHTML = (data.requests || []).length ? data.requests.map((item) => `<div class="request-card"><div class="req-icon">▤</div><div><h4>${esc(item.category)}</h4><p>${esc(item.beneficiary_name)} · ${esc(item.created_at)}</p></div><button class="table-action" data-view="supporter-solicitacoes" type="button">Analisar</button></div>`).join('') : emptyState('Nenhuma solicitação disponível agora.');
      app.bindNavigation();
    }
    if (el('#supRecentMessages')) {
      el('#supRecentMessages').innerHTML = (data.messages || []).length ? data.messages.map((item) => `<article class="notice-item"><strong>${item.sender_role === 'supporter' ? 'Você' : esc(item.beneficiary_name)}</strong><p>${esc(String(item.body).slice(0, 140))}</p><small>${esc(item.created_at)}</small></article>`).join('') : emptyState('Nenhuma mensagem recente.');
    }
    if (el('#supNotifications')) {
      el('#supNotifications').innerHTML = (data.notifications || []).length ? data.notifications.map((item) => `<article class="notice-item"><strong>${esc(item.subject)}</strong><p>${esc(item.message)}</p><small>${esc(item.created_at)}</small></article>`).join('') : emptyState('Nenhuma notificação recente.');
    }
  }

  async function loadSupporterNotifications() {
    const result = await sapi('notifications');
    const target = el('#supNotificationList');
    if (!target) return;
    const items = result.data.notifications || [];
    target.innerHTML = items.length ? items.map((item) => `<article class="notice-item"><strong>${esc(item.subject)} ${badge(item.status, item.status === 'sent' ? 'green' : 'yellow')}</strong><p>${esc(item.message)}</p><small>${esc(item.channel)} · ${esc(item.created_at)}</small>${item.whatsapp_url ? `<a class="whatsapp-link" href="${esc(item.whatsapp_url)}" target="_blank" rel="noreferrer">Abrir mensagem</a>` : ''}</article>`).join('') : emptyState('Nenhuma notificação recente.');
  }

  /* ------------------------------------------------------------------ Solicitações recebidas */

  async function loadSupporterRequests() {
    if (!workGuard()) return;
    const result = await sapi('requests_available');
    state.requests = result.data.requests || [];
    const declined = result.data.declined || [];
    renderSupporterRequests();
    const target = el('#supDeclinedList');
    if (target) target.innerHTML = declined.length ? declined.map((item) => `<div class="request-card"><div class="req-icon">×</div><div><h4>${esc(item.category)}</h4><p>Recusada em ${esc(item.decided_at)} · ${esc(item.decline_reason || 'sem motivo registrado')}</p></div></div>`).join('') : emptyState('Nenhuma recusa registrada.');
  }

  function renderSupporterRequests() {
    const target = el('#supRequestsTable');
    if (!target) return;
    const term = (el('#supRequestSearch')?.value || '').toLowerCase();
    const onlyMatches = !!el('#supRequestOnlyMatches')?.checked;
    const rows = state.requests.filter((item) => (!term || `${item.category} ${item.region}`.toLowerCase().includes(term)) && (!onlyMatches || item.matches));
    target.innerHTML = rows.length ? rows.map((item) => `<tr>
        <td><strong>${esc(item.category)}</strong><br><small class="muted-note">${esc(String(item.preview || '').slice(0, 120))}…</small></td>
        <td>${esc(item.region)}</td>
        <td>${esc(item.created_at)}</td>
        <td>${item.matches ? badge('Compatível', 'green') : badge('Fora do perfil', 'gray')}</td>
        <td class="row-actions">
          <button class="table-action" data-sup-request="${item.id}" type="button">Detalhes</button>
          <button class="table-action" data-sup-accept="${item.id}" type="button">Aceitar</button>
          <button class="table-action danger" data-sup-decline="${item.id}" type="button">Recusar</button>
        </td></tr>`).join('') : `<tr><td colspan="5" class="empty-state">${state.requests.length ? 'Nenhuma solicitação corresponde aos filtros.' : 'Nenhuma solicitação aguardando atendimento agora.'}</td></tr>`;
    target.querySelectorAll('[data-sup-request]').forEach((button) => { button.onclick = () => openSupporterRequest(button.dataset.supRequest); });
    target.querySelectorAll('[data-sup-accept]').forEach((button) => { button.onclick = () => acceptRequest(button.dataset.supAccept); });
    target.querySelectorAll('[data-sup-decline]').forEach((button) => { button.onclick = () => declineRequest(button.dataset.supDecline); });
  }

  async function openSupporterRequest(id) {
    try {
      const result = await sapi('request_detail', { id });
      const request = result.data.request;
      const restricted = !request.authorized;
      el('#caseContent').innerHTML = `
        <div class="eyebrow">SOLICITAÇÃO DE ATENDIMENTO</div>
        <h2>${esc(request.category)}</h2>
        <p class="helper-text">${esc(request.beneficiary_name)} · ${esc(request.created_at)}${request.region ? ` · ${esc(request.region)}` : ''}</p>
        <div class="case-full">${esc(request.description)}</div>
        ${restricted ? '<div class="privacy-strip"><b>Dados protegidos</b><span>Nome completo, contato e endereço são liberados somente depois que você aceita o atendimento.</span></div>' : '<p class="helper-text">Preferência de contato: ' + esc(request.preferred_contact || 'Não informada') + '</p>'}
        <div class="case-actions">
          ${restricted ? `<button class="secondary" type="button" data-modal-decline="${request.id}">Recusar</button><button class="primary" type="button" data-modal-accept="${request.id}">Aceitar atendimento</button>` : `<button class="primary" type="button" data-open-assignment="${request.assignment_id}">Abrir atendimento</button>`}
          <button class="secondary" type="button" data-close-modal="caseModal">Fechar</button>
        </div>`;
      openModal('caseModal');
      bindModalButtons();
      el('[data-modal-accept]') && (el('[data-modal-accept]').onclick = () => acceptRequest(request.id));
      el('[data-modal-decline]') && (el('[data-modal-decline]').onclick = () => declineRequest(request.id));
      el('[data-open-assignment]') && (el('[data-open-assignment]').onclick = () => { closeModal('caseModal'); openAssignment(el('[data-open-assignment]').dataset.openAssignment); });
    } catch (error) { toast(error.message); }
  }

  async function acceptRequest(id) {
    if (!window.confirm('Confirmar que você assume este atendimento? A beneficiária será avisada e você passa a ver os dados necessários.')) return;
    try {
      const result = await sapi('request_accept', { id });
      closeModal('caseModal');
      toast(`${result.message} Conversa #${result.data.conversation_id} criada.`);
      state.scope = 'active';
      if (state.area) state.area.can_work = true;
      await loadSupporterRequests();
    } catch (error) { toast(error.message); }
  }

  async function declineRequest(id) {
    const reason = window.prompt('Motivo da recusa (a solicitação volta para a rede):');
    if (reason === null) return;
    if (!reason.trim()) { toast('Registre brevemente o motivo da recusa.'); return; }
    try {
      const result = await sapi('request_decline', { id, reason });
      closeModal('caseModal');
      toast(result.message);
      await loadSupporterRequests();
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Atendimentos */

  async function loadAssignments(scope) {
    if (scope) state.scope = scope;
    const result = await sapi('assignments', { scope: state.scope });
    state.assignments = result.data.assignments || [];
    document.querySelectorAll('.scope-tabs [data-sup-scope]').forEach((button) => button.classList.toggle('active', button.dataset.supScope === state.scope));
    const target = el('#supAssignmentsTable');
    if (!target) return;
    target.innerHTML = state.assignments.length ? state.assignments.map((item) => `<tr>
        <td><strong>${esc(item.beneficiary_name)}</strong>${Number(item.unread) ? ` ${badge(`${item.unread} nova(s)`, 'yellow')}` : ''}</td>
        <td>${esc(item.category)}</td>
        <td>${esc(item.accepted_at)}${item.closed_at ? `<br><small class="muted-note">Encerrado em ${esc(item.closed_at)}</small>` : ''}</td>
        <td>${item.next_appointment ? esc(item.next_appointment) : '<span class="muted-note">Sem agendamento</span>'}</td>
        <td>${badge(...assignmentBadge(item.status))}</td>
        <td class="row-actions"><button class="table-action" data-open-assignment="${item.id}" type="button">Abrir</button></td>
      </tr>`).join('') : `<tr><td colspan="6" class="empty-state">${state.scope === 'history' ? 'Nenhum atendimento encerrado ainda.' : 'Você não tem atendimentos em andamento.'}</td></tr>`;
    target.querySelectorAll('[data-open-assignment]').forEach((button) => { button.onclick = () => openAssignment(button.dataset.openAssignment); });
  }

  async function openAssignment(id) {
    try {
      const result = await sapi('assignment_detail', { id });
      const item = result.data.assignment;
      const history = result.data.history || [];
      const appointments = result.data.appointments || [];
      const closed = ['completed', 'cancelled'].includes(item.status);
      el('#caseContent').innerHTML = `
        <div class="eyebrow">ATENDIMENTO #${item.id} · ${esc(item.category)}</div>
        <h2>${esc(item.beneficiary_name)}</h2>
        <p class="helper-text">Aceito em ${esc(item.accepted_at_label || '')} · ${badge(...assignmentBadge(item.status))}</p>
        <div class="case-detail-grid">
          <div class="case-detail"><small>Contato</small><strong>${esc(item.beneficiary_email || 'Não informado')}</strong></div>
          <div class="case-detail"><small>Telefone</small><strong>${esc(item.beneficiary_phone || 'Não informado')}</strong></div>
          <div class="case-detail"><small>Preferência de contato</small><strong>${esc(item.preferred_contact || 'Não informada')}</strong></div>
          <div class="case-detail"><small>Situação da solicitação</small><strong>${esc(item.request_status)}</strong></div>
        </div>
        <h3>Relato da beneficiária</h3>
        <div class="case-full">${esc(item.description)}</div>
        <h3>Agendamentos</h3>
        <div class="request-list">${appointments.length ? appointments.map((ap) => `<div class="request-card"><div class="req-icon">◷</div><div><h4>${esc(kindLabel(ap.kind))}</h4><p>${esc(ap.scheduled_at)} · ${esc(modeLabel(ap.mode))}${ap.location ? ` · ${esc(ap.location)}` : ''}${ap.notes ? `<br>${esc(ap.notes)}` : ''}</p></div>${badge(...appointmentBadge(ap.status))}</div>`).join('') : emptyState('Nenhum agendamento registrado.')}</div>
        <h3>Histórico do atendimento</h3>
        <div class="metric-list">${history.length ? history.map((h) => `<div class="metric-row"><span>${esc(h.created_at)} · ${esc(h.actor || 'Sistema')}</span><b>${esc(h.note || `${h.old_status || '—'} → ${h.new_status}`)}</b></div>`).join('') : emptyState('Sem movimentações registradas.')}</div>
        <div class="case-actions">
          ${closed ? '' : `<button class="secondary" type="button" data-assignment-status="in_progress">Marcar em atendimento</button><button class="secondary" type="button" data-assignment-status="cancelled">Cancelar atendimento</button><button class="secondary" type="button" data-assignment-schedule="${item.id}">Agendar / reagendar</button><button class="primary" type="button" data-assignment-close="${item.id}">Encerrar atendimento</button>`}
          <button class="secondary" type="button" data-open-conversation="${result.data.conversation_id}">Abrir conversa (${result.data.message_count})</button>
          <button class="secondary" type="button" data-close-modal="caseModal">Fechar</button>
        </div>`;
      openModal('caseModal');
      bindModalButtons();
      el('[data-open-conversation]') && (el('[data-open-conversation]').onclick = () => { closeModal('caseModal'); state.conversationId = Number(el('[data-open-conversation]').dataset.openConversation); showView('supporter-mensagens'); });
      document.querySelectorAll('[data-assignment-status]').forEach((button) => { button.onclick = () => changeAssignmentStatus(item.id, button.dataset.assignmentStatus); });
      el('[data-assignment-schedule]') && (el('[data-assignment-schedule]').onclick = () => { closeModal('caseModal'); openAppointmentModal({ assignment_id: item.id, beneficiary: item.beneficiary_name }); });
      el('[data-assignment-close]') && (el('[data-assignment-close]').onclick = () => { closeModal('caseModal'); openCloseModal(item.id); });
    } catch (error) { toast(error.message); }
  }

  async function changeAssignmentStatus(id, status) {
    if (status === 'cancelled' && !window.confirm('Cancelar este atendimento? A beneficiária será avisada e o histórico preservado.')) return;
    const note = window.prompt('Observação para o histórico (opcional):') || '';
    try {
      const result = await sapi('assignment_status', { id, status, note });
      closeModal('caseModal');
      toast(result.message);
      loadAssignments();
    } catch (error) { toast(error.message); }
  }

  function openCloseModal(id) {
    const form = el('#supporterCloseForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = id;
    openModal('supporterCloseModal');
  }

  async function submitClose(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi('assignment_close', Object.fromEntries(new FormData(form).entries()));
      closeModal('supporterCloseModal');
      form.reset();
      toast(result.message);
      loadAssignments();
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Agenda */

  async function loadAgenda() {
    const result = await sapi('appointments');
    const render = (items, empty) => items.length ? items.map((item) => `<div class="request-card"><div class="req-icon">◷</div><div><h4>${esc(kindLabel(item.kind))} · ${esc(item.beneficiary_name)}</h4><p>${esc(item.scheduled_at)} · ${esc(modeLabel(item.mode))} · ${esc(item.category)}${item.location ? `<br>${esc(item.location)}` : ''}</p><div class="row-actions">${item.status === 'scheduled' ? `<button class="table-action" data-appointment-status="confirmed" data-id="${item.id}">Confirmar</button>` : ''}${['scheduled', 'confirmed'].includes(item.status) ? `<button class="table-action" data-appointment-status="done" data-id="${item.id}">Realizado</button><button class="table-action" data-appointment-status="missed" data-id="${item.id}">Não compareceu</button><button class="table-action danger" data-appointment-status="cancelled" data-id="${item.id}">Cancelar</button><button class="table-action" data-appointment-reschedule="${item.id}" data-assignment="${item.assignment_id}" data-beneficiary="${esc(item.beneficiary_name)}">Reagendar</button>` : ''}</div></div>${badge(...appointmentBadge(item.status))}</div>`).join('') : emptyState(empty);
    if (el('#supAgendaUpcoming')) el('#supAgendaUpcoming').innerHTML = render(result.data.upcoming || [], 'Nenhum atendimento futuro agendado.');
    if (el('#supAgendaPast')) el('#supAgendaPast').innerHTML = render(result.data.past || [], 'Nenhum atendimento anterior.');
    if (el('#supTimeOffList')) {
      el('#supTimeOffList').innerHTML = (result.data.time_off || []).length ? result.data.time_off.map((item) => `<div class="request-card"><div class="req-icon">×</div><div><h4>${esc(item.off_date)}</h4><p>${esc(item.reason || 'Bloqueio de agenda')}</p></div><button class="table-action danger" data-timeoff="${item.id}" type="button">Remover</button></div>`).join('') : emptyState('Nenhum bloqueio registrado.');
    }
    document.querySelectorAll('[data-appointment-status]').forEach((button) => { button.onclick = () => updateAppointment(button.dataset.id, button.dataset.appointmentStatus); });
    document.querySelectorAll('[data-appointment-reschedule]').forEach((button) => { button.onclick = () => openAppointmentModal({ id: button.dataset.appointmentReschedule, assignment_id: button.dataset.assignment, beneficiary: button.dataset.beneficiary }); });
    document.querySelectorAll('[data-timeoff]').forEach((button) => { button.onclick = () => removeTimeOff(button.dataset.timeoff); });
  }

  async function updateAppointment(id, status) {
    try {
      const result = await sapi('appointment_update', { id, status });
      toast(result.message);
      loadAgenda();
    } catch (error) { toast(error.message); }
  }

  async function removeTimeOff(id) {
    try {
      const result = await sapi('timeoff_delete', { id });
      toast(result.message);
      loadAgenda();
    } catch (error) { toast(error.message); }
  }

  async function submitTimeOff(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi('timeoff_save', Object.fromEntries(new FormData(form).entries()));
      form.reset();
      toast(result.message);
      loadAgenda();
    } catch (error) { toast(error.message); }
  }

  async function openAppointmentModal(options = {}) {
    const form = el('#supporterAppointmentForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = options.id || '';
    el('#supAppointmentTitle').textContent = options.id ? 'Reagendar atendimento' : 'Agendar atendimento';
    try {
      const result = await sapi('assignments', { scope: 'active' });
      const options2 = (result.data.assignments || []);
      form.elements.assignment_id.innerHTML = options2.map((item) => `<option value="${item.id}">${esc(item.beneficiary_name)} · ${esc(item.category)}</option>`).join('') || '<option value="">Nenhum atendimento em andamento</option>';
      if (options.assignment_id) form.elements.assignment_id.value = String(options.assignment_id);
      if (!options2.length) { toast('Você não tem atendimentos em andamento para agendar.'); return; }
      el('#supSlotList').innerHTML = '';
      openModal('supporterAppointmentModal');
    } catch (error) { toast(error.message); }
  }

  async function submitAppointment(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi('appointment_save', Object.fromEntries(new FormData(form).entries()));
      closeModal('supporterAppointmentModal');
      form.reset();
      toast(result.message);
      if (state.view === 'supporter-agenda') loadAgenda(); else loadAssignments();
    } catch (error) { toast(error.message); }
  }

  async function loadSlots() {
    const date = el('#supSlotDate')?.value;
    const target = el('#supSlotList');
    if (!target) return;
    if (!date) { toast('Escolha uma data para ver os horários livres.'); return; }
    try {
      const result = await sapi('available_slots', { date });
      const slots = result.data.slots || [];
      target.innerHTML = slots.length
        ? `<p class="helper-text">${esc(result.data.weekday)} · horários livres:</p><div class="slot-buttons">${slots.map((slot) => `<button class="slot-chip" type="button" data-slot="${slot.time}" data-mode="${slot.mode}">${esc(slot.time)}</button>`).join('')}</div>`
        : `<p class="helper-text">Nenhum horário livre nesta data. Verifique sua disponibilidade ou registre um bloqueio.</p>`;
      target.querySelectorAll('[data-slot]').forEach((button) => { button.onclick = () => { el('#supAppointmentDate').value = `${date}T${button.dataset.slot}`; el('#supAppointmentMode').value = button.dataset.mode || 'online'; toast(`Horário ${button.dataset.slot} selecionado.`); }; });
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Conversas */

  async function loadConversations() {
    const result = await api('conversation_list');
    state.conversations = result.data.conversations || [];
    const target = view('[data-conversation-list]');
    if (!target) return;
    target.innerHTML = state.conversations.length ? state.conversations.map((item) => `<button class="conversation-item ${Number(item.id) === Number(state.conversationId) ? 'active' : ''}" type="button" data-conversation="${item.id}">
        <strong>${esc(item.counterpart)}${Number(item.unread) ? ` <span class="status yellow">${item.unread}</span>` : ''}</strong>
        <small>${esc(item.category)} · ${esc(item.last_message_at || 'sem mensagens')}</small>
        <span class="conversation-preview">${esc(item.preview || 'Conversa iniciada — envie a primeira mensagem.')}</span>
      </button>`).join('') : emptyState('Nenhuma conversa ativa. As conversas aparecem quando você aceita um atendimento.');
    target.querySelectorAll('[data-conversation]').forEach((button) => { button.onclick = () => openConversation(button.dataset.conversation); });
    if (!state.conversations.length) return;
    const exists = state.conversations.some((item) => Number(item.id) === Number(state.conversationId));
    await openConversation(exists ? state.conversationId : state.conversations[0].id);
  }

  async function openConversation(id) {
    try {
      const result = await api('conversation_detail', { id });
      state.conversationId = Number(id);
      document.querySelectorAll('[data-conversation]').forEach((button) => button.classList.toggle('active', Number(button.dataset.conversation) === Number(id)));
      if (view('[data-conversation-title]')) view('[data-conversation-title]').textContent = result.data.counterpart.name;
      if (view('[data-conversation-meta]')) view('[data-conversation-meta]').textContent = `${result.data.counterpart.detail} · ${result.data.conversation.category}${result.data.counterpart.contact ? ` · ${result.data.counterpart.contact}` : ''} · ${result.data.conversation.status === 'closed' ? 'atendimento encerrado' : 'atendimento ativo'}`;
      const appointments = result.data.appointments || [];
      if (view('[data-conversation-appointments]')) {
        view('[data-conversation-appointments]').innerHTML = appointments.length ? appointments.map((item) => `<span class="appointment-chip">${esc(kindLabel(item.kind))}: ${esc(item.scheduled_at)} · ${esc(modeLabel(item.mode))}</span>`).join('') : '';
      }
      const thread = view('[data-conversation-thread]');
      if (thread) {
        thread.innerHTML = result.data.messages.length ? result.data.messages.map((message) => `<div class="chat-message ${message.sender_role === 'supporter' ? 'chat-message-user' : 'chat-message-assistant'}"><span class="chat-message-author">${esc(message.sender_role === 'supporter' ? 'Apoiador(a)' : 'Beneficiária')} · ${esc(message.created_at)}</span><p class="chat-message-body">${esc(message.body)}</p></div>`).join('') : emptyState('Nenhuma mensagem ainda. Comece a conversa com acolhimento.');
        thread.scrollTop = thread.scrollHeight;
      }
      const input = view('[data-message-input]');
      if (input) { input.disabled = result.data.conversation.status === 'closed'; input.placeholder = input.disabled ? 'Atendimento encerrado' : input.placeholder; }
    } catch (error) { toast(error.message); }
  }

  async function submitMessage(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const input = form.querySelector('[data-message-input]');
    const message = (input?.value || '').trim();
    if (!message) { toast('Digite uma mensagem antes de enviar.'); return; }
    if (!state.conversationId) { toast('Selecione uma conversa.'); return; }
    try {
      await api('message_send', { conversation_id: state.conversationId, message });
      input.value = '';
      await openConversation(state.conversationId);
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Serviços oferecidos */

  async function loadSupporterServices() {
    const result = await sapi('services_list');
    const services = result.data.services || [];
    const target = el('#supServicesTable');
    if (!target) return;
    target.innerHTML = services.length ? services.map((item) => `<tr>
        <td><strong>${esc(item.name)}</strong><br><small class="muted-note">Atualizado em ${esc(item.updated_at)}</small></td>
        <td>${esc(item.category)}</td>
        <td>${esc(modeLabel(item.mode))}</td>
        <td>${esc(item.duration_minutes)} min</td>
        <td>${esc(item.price_info || 'Não informado')}</td>
        <td>${item.status === 'active' ? badge('Ativo', 'green') : badge('Pausado', 'gray')}</td>
        <td class="row-actions">
          <button class="table-action" data-service-edit="${item.id}" type="button">Editar</button>
          <button class="table-action" data-service-toggle="${item.id}" type="button">${item.status === 'active' ? 'Pausar' : 'Reativar'}</button>
          <button class="table-action danger" data-service-delete="${item.id}" type="button">Remover</button>
        </td></tr>`).join('') : '<tr><td colspan="7" class="empty-state">Nenhum serviço cadastrado. Use “Novo serviço” para começar.</td></tr>';
    target.querySelectorAll('[data-service-edit]').forEach((button) => { button.onclick = () => openServiceModal(services.find((item) => String(item.id) === button.dataset.serviceEdit)); });
    target.querySelectorAll('[data-service-toggle]').forEach((button) => { button.onclick = () => toggleService(button.dataset.serviceToggle); });
    target.querySelectorAll('[data-service-delete]').forEach((button) => { button.onclick = () => deleteService(button.dataset.serviceDelete); });
  }

  function openServiceModal(service) {
    const form = el('#supporterServiceForm');
    if (!form) return;
    form.reset();
    form.elements.id.value = service ? service.id : '';
    el('#supServiceModalTitle').textContent = service ? 'Editar serviço' : 'Novo serviço';
    if (service) {
      form.elements.name.value = service.name || '';
      form.elements.category.value = service.category || '';
      form.elements.mode.value = service.mode || 'online';
      form.elements.duration_minutes.value = service.duration_minutes || 60;
      form.elements.price_info.value = service.price_info || '';
      form.elements.description.value = service.description || '';
      form.elements.status.value = service.status || 'active';
    }
    openModal('supporterServiceModal');
  }

  async function submitService(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi('service_save', Object.fromEntries(new FormData(form).entries()));
      closeModal('supporterServiceModal');
      form.reset();
      toast(result.message);
      loadSupporterServices();
    } catch (error) { toast(error.message); }
  }

  async function toggleService(id) {
    try {
      const result = await sapi('service_toggle', { id });
      toast(result.message);
      loadSupporterServices();
    } catch (error) { toast(error.message); }
  }

  async function deleteService(id) {
    if (!window.confirm('Remover este serviço da sua vitrine?')) return;
    try {
      const result = await sapi('service_delete', { id });
      toast(result.message);
      loadSupporterServices();
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Perfil */

  async function loadSupporterProfile() {
    const result = await sapi('profile');
    const data = result.data;
    state.area = data.state;
    state.type = data.type;
    const form = el('#supporterProfileForm');
    if (form) {
      const fields = ['name', 'email', 'phone', 'address', 'cep'];
      fields.forEach((field) => { if (form.elements[field]) form.elements[field].value = data.account[field] || ''; });
      form.elements.supporter_type.value = data.type;
      const profile = data.profile || {};
      ['display_name', 'document_number', 'council_name', 'council_number', 'council_state', 'legal_name', 'responsible_name', 'responsible_document', 'city', 'state', 'service_mode', 'languages', 'approach', 'bio'].forEach((field) => { if (form.elements[field]) form.elements[field].value = profile[field] || ''; });
      if (form.elements.team_size) form.elements.team_size.value = profile.team_size || 1;
      if (form.elements.max_active_cases) form.elements.max_active_cases.value = profile.max_active_cases || 5;
    }
    const info = verificationBadge(data.state.verification);
    if (el('#supProfileBadge')) { el('#supProfileBadge').className = `status ${info[1]}`; el('#supProfileBadge').textContent = info[0]; }

    if (el('#supSpecialties')) {
      el('#supSpecialties').innerHTML = data.specialties.length ? data.specialties.map((item) => `<div class="metric-row"><span>${esc(item.name)}${item.notes ? `<br><small class="muted-note">${esc(item.notes)}</small>` : ''}</span><button class="table-action danger" data-specialty="${item.id}" type="button">Remover</button></div>`).join('') : emptyState('Nenhuma especialidade cadastrada.');
    }
    if (el('#supAvailability')) {
      const weekdays = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
      el('#supAvailability').innerHTML = data.availability.length ? data.availability.map((item) => `<div class="metric-row"><span>${esc(weekdays[item.weekday] || '—')} · ${esc(item.start_time)} às ${esc(item.end_time)} · ${esc(modeLabel(item.mode))}</span><button class="table-action danger" data-availability="${item.id}" type="button">Remover</button></div>`).join('') : emptyState('Nenhum horário cadastrado.');
    }
    if (el('#supLocations')) {
      el('#supLocations').innerHTML = data.locations.length ? data.locations.map((item) => `<div class="request-card"><div class="req-icon">⌖</div><div><h4>${esc(item.label)}${Number(item.is_primary) ? ' ' + badge('Principal', 'green') : ''}</h4><p>${esc([item.address, item.city, item.state].filter(Boolean).join(' · ') || 'Endereço não informado')}${item.notes ? `<br>${esc(item.notes)}` : ''}</p></div><button class="table-action danger" data-location="${item.id}" type="button">Remover</button></div>`).join('') : emptyState('Nenhum local cadastrado.');
    }
    document.querySelectorAll('[data-specialty]').forEach((button) => { button.onclick = () => removeRow('specialty_delete', button.dataset.specialty, loadSupporterProfile); });
    document.querySelectorAll('[data-availability]').forEach((button) => { button.onclick = () => removeRow('availability_delete', button.dataset.availability, loadSupporterProfile); });
    document.querySelectorAll('[data-location]').forEach((button) => { button.onclick = () => removeRow('location_delete', button.dataset.location, loadSupporterProfile); });
  }

  async function removeRow(action, id, reload) {
    try {
      const result = await sapi(action, { id });
      toast(result.message);
      reload();
    } catch (error) { toast(error.message); }
  }

  async function submitProfile(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi('profile_save', Object.fromEntries(new FormData(form).entries()));
      toast(result.message);
      if (el('#sidebarName')) el('#sidebarName').textContent = result.data.name;
      if (el('#topAvatar')) el('#topAvatar').textContent = result.data.initials;
      if (el('#sidebarAvatar')) el('#sidebarAvatar').textContent = result.data.initials;
      await loadSupporterProfile();
    } catch (error) { toast(error.message); }
  }

  async function submitInlineForm(event, action, reload) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi(action, Object.fromEntries(new FormData(form).entries()));
      form.reset();
      if (form.elements.id) form.elements.id.value = '';
      toast(result.message);
      reload();
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Documentos */

  async function loadSupporterDocuments() {
    const data = await loadAreaState();
    const info = verificationBadge(data.state.verification);
    if (el('#supDocumentBadge')) { el('#supDocumentBadge').className = `status ${info[1]}`; el('#supDocumentBadge').textContent = info[0]; }
    const select = el('#supDocumentKind');
    if (select) {
      select.innerHTML = Object.entries(data.required_documents).map(([kind, label]) => `<option value="${kind}">${esc(label)}</option>`).join('') + '<option value="other">Documento complementar</option>';
    }
    const target = el('#supDocumentsList');
    if (target) {
      target.innerHTML = (state.documents || []).length ? state.documents.map((item) => {
        const itemInfo = documentBadge(item.status);
        return `<article class="notice-item"><strong>${esc(item.original_name)} ${badge(itemInfo[0], itemInfo[1])}</strong><p>${esc(item.created_at)} · ${(item.size_bytes / 1024).toFixed(0)} KB</p>${item.review_notes ? `<p>Observação da equipe: ${esc(item.review_notes)}</p>` : ''}<button class="table-action danger" data-document="${item.id}" type="button">Remover arquivo</button></article>`;
      }).join('') : emptyState('Nenhum documento enviado ainda.');
      target.querySelectorAll('[data-document]').forEach((button) => { button.onclick = () => removeDocument(button.dataset.document); });
    }
  }

  async function submitDocument(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const file = form.elements.file?.files?.[0];
    if (!file) { toast('Selecione um arquivo para enviar.'); return; }
    const body = new FormData();
    body.append('kind', form.elements.kind.value);
    body.append('file', file);
    try {
      const result = await sapi('document_upload', body, true);
      form.reset();
      toast(result.message);
      await loadSupporterDocuments();
    } catch (error) { toast(error.message); }
  }

  async function removeDocument(id) {
    if (!window.confirm('Remover este arquivo enviado?')) return;
    try {
      const result = await sapi('document_delete', { id });
      toast(result.message);
      await loadSupporterDocuments();
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Segurança */

  async function loadSecurity() {
    const result = await sapi('security_overview');
    const data = result.data;
    if (el('#supSecuritySummary')) {
      el('#supSecuritySummary').innerHTML = `
        <div class="metric-row"><span>Dispositivos com “lembrar de mim”</span><b>${esc(data.remembered_devices)}</b></div>
        <div class="metric-row"><span>Última alteração de senha</span><b>${esc(data.last_password_change || 'Não registrada')}</b></div>
        <div class="metric-row"><span>Relatos de risco em análise</span><b>${esc(data.open_risk_reports)}</b></div>`;
    }
    if (el('#supAccessLog')) {
      el('#supAccessLog').innerHTML = (data.events || []).length ? data.events.map((item) => `<div class="metric-row"><span>${esc(item.created_at)} · ${esc(item.action)}</span><b>${esc(item.details || '')}${item.ip_address ? ` · ${esc(item.ip_address)}` : ''}</b></div>`).join('') : emptyState('Nenhum evento registrado.');
    }
    const riskList = el('#supRiskList');
    if (riskList) {
      const reports = (await sapi('risk_report_list')).data.reports || [];
      riskList.innerHTML = reports.length ? reports.map((item) => {
        const urgency = urgencyLabel(item.urgency);
        const statusInfo = { new: ['Recebido', 'yellow'], reviewing: ['Em análise', 'yellow'], resolved: ['Resolvido', 'green'] }[item.status] || [item.status, 'gray'];
        return `<article class="notice-item"><strong>${esc(riskLabel(item.category))} ${badge(statusInfo[0], statusInfo[1])} ${badge(urgency[0], urgency[1])}</strong><p>${esc(item.description)}</p><small>Enviado em ${esc(item.created_at)}</small>${item.review_notes ? `<p>Retorno da equipe: ${esc(item.review_notes)}</p>` : ''}</article>`;
      }).join('') : emptyState('Nenhum relato enviado.');
    }
    const select = el('#supRiskAssignment');
    if (select) {
      const assignments = (await sapi('assignments', { scope: 'active' })).data.assignments || [];
      select.innerHTML = '<option value="0">Não se aplica</option>' + assignments.map((item) => `<option value="${item.id}">#${item.id} · ${esc(item.beneficiary_name)} · ${esc(item.category)}</option>`).join('');
    }
  }

  async function submitPassword(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi('password_change', Object.fromEntries(new FormData(form).entries()));
      form.reset();
      toast(result.message);
      loadSecurity();
    } catch (error) { toast(error.message); }
  }

  async function revokeRemember() {
    try {
      const result = await sapi('remember_revoke');
      toast(result.message);
      loadSecurity();
    } catch (error) { toast(error.message); }
  }

  async function submitRisk(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
      const result = await sapi('risk_report_create', Object.fromEntries(new FormData(form).entries()));
      form.reset();
      toast(result.message);
      loadSecurity();
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Revisão de apoiadores (equipe) */

  async function loadAdminSupporters() {
    const result = await api('admin_supporters');
    const supporters = result.data.supporters || [];
    const risks = result.data.risk_reports || [];
    const render = () => {
      const term = (el('#adminSupporterSearch')?.value || '').toLowerCase();
      const filter = el('#adminSupporterFilter')?.value || '';
      const rows = supporters.filter((item) => (!filter || item.verification_status === filter) && (!term || `${item.name} ${item.email} ${item.city || ''}`.toLowerCase().includes(term)));
      const target = el('#adminSupportersTable');
      if (!target) return;
      target.innerHTML = rows.length ? rows.map((item) => {
        const info = verificationBadge(item.verification_status);
        const typeInfo = { professional: ['Profissional autônomo', 'green'], institution: ['Instituição / ONG', 'yellow'], volunteer: ['Voluntário(a)', 'gray'] }[item.supporter_type] || ['Apoiador(a)', 'gray'];
        return `<tr>
          <td><strong>${esc(item.display_name || item.name)}</strong><br><small class="muted-note">${esc(item.email)}</small></td>
          <td>${badge(typeInfo[0], typeInfo[1])}</td>
          <td>${esc([item.city, item.state].filter(Boolean).join('/') || 'Não informada')}</td>
          <td>${esc(item.documents)} enviado(s)${Number(item.pending_documents) ? ` · ${badge(`${item.pending_documents} pendente(s)`, 'yellow')}` : ''}</td>
          <td>${esc(item.active_cases)} em andamento · ${esc(item.completed_cases)} concluído(s)</td>
          <td>${badge(info[0], info[1])}</td>
          <td class="row-actions"><button class="table-action" data-supporter="${item.id}" type="button">Analisar</button></td></tr>`;
      }).join('') : '<tr><td colspan="7" class="empty-state">Nenhum apoiador corresponde aos filtros.</td></tr>';
      target.querySelectorAll('[data-supporter]').forEach((button) => { button.onclick = () => openAdminSupporter(button.dataset.supporter); });
    };
    render();
    if (el('#adminSupporterSearch')) el('#adminSupporterSearch').oninput = render;
    if (el('#adminSupporterFilter')) el('#adminSupporterFilter').onchange = render;

    const riskTarget = el('#adminRiskReportsTable');
    if (riskTarget) {
      riskTarget.innerHTML = risks.length ? risks.map((item) => {
        const urgency = urgencyLabel(item.urgency);
        const statusInfo = { new: ['Recebido', 'yellow'], reviewing: ['Em análise', 'yellow'], resolved: ['Resolvido', 'green'] }[item.status] || [item.status, 'gray'];
        return `<tr><td>${esc(item.created_at)}</td><td>${esc(item.supporter)}</td><td><strong>${esc(riskLabel(item.category))}</strong><br><small class="muted-note">${esc(String(item.description).slice(0, 140))}</small></td><td>${badge(urgency[0], urgency[1])}</td><td>${badge(statusInfo[0], statusInfo[1])}</td><td class="row-actions"><button class="table-action" data-risk="${item.id}" data-status="${item.status}" type="button">Tratar</button></td></tr>`;
      }).join('') : '<tr><td colspan="6" class="empty-state">Nenhum relato registrado.</td></tr>';
      riskTarget.querySelectorAll('[data-risk]').forEach((button) => { button.onclick = () => updateRiskReport(button.dataset.risk, button.dataset.status); });
    }
  }

  async function updateRiskReport(id, status) {
    const next = window.prompt('Nova situação do relato: new (recebido), reviewing (em análise) ou resolved (resolvido):', status === 'new' ? 'reviewing' : 'resolved');
    if (next === null) return;
    const notes = window.prompt('Retorno para o apoiador (opcional):') || '';
    try {
      const result = await api('admin_risk_report_update', { id, status: next.trim(), notes });
      toast(result.message);
      loadAdminSupporters();
    } catch (error) { toast(error.message); }
  }

  async function openAdminSupporter(id) {
    try {
      const result = await api('admin_supporter_detail', { id });
      const supporter = result.data.supporter;
      const info = verificationBadge(supporter.verification_status);
      const typeInfo = { professional: 'Profissional autônomo', institution: 'Instituição / ONG', volunteer: 'Voluntário(a)' }[supporter.supporter_type] || 'Apoiador(a)';
      el('#caseContent').innerHTML = `
        <div class="eyebrow">VERIFICAÇÃO DE APOIADOR · ${esc(typeInfo)}</div>
        <h2>${esc(supporter.display_name || supporter.name)}</h2>
        <p class="helper-text">${esc(supporter.name)} · ${esc(supporter.email)} · ${esc(supporter.phone || 'telefone não informado')} ${badge(info[0], info[1])}</p>
        <div class="case-detail-grid">
          <div class="case-detail"><small>Documento</small><strong>${esc(supporter.document_number || 'Não informado')}</strong></div>
          <div class="case-detail"><small>Registro profissional</small><strong>${esc([supporter.council_name, supporter.council_number, supporter.council_state].filter(Boolean).join(' ') || 'Não se aplica')}</strong></div>
          <div class="case-detail"><small>Razão social</small><strong>${esc(supporter.legal_name || 'Não se aplica')}</strong></div>
          <div class="case-detail"><small>Cidade</small><strong>${esc([supporter.city, supporter.state].filter(Boolean).join('/') || 'Não informada')}</strong></div>
          <div class="case-detail"><small>Modalidade</small><strong>${esc(modeLabel(supporter.service_mode))}</strong></div>
          <div class="case-detail"><small>Limite de atendimentos</small><strong>${esc(supporter.max_active_cases)}</strong></div>
        </div>
        <h3>Apresentação</h3><div class="case-full">${esc(supporter.bio || 'Sem apresentação enviada.')}</div>
        <h3>Documentos enviados</h3>
        <div class="request-list">${result.data.documents.length ? result.data.documents.map((doc) => {
          const docInfo = documentBadge(doc.status);
          return `<div class="request-card"><div class="req-icon">▧</div><div><h4>${esc(doc.original_name)}</h4><p>${esc(doc.kind)} · ${esc(doc.created_at)} · ${(doc.size_bytes / 1024).toFixed(0)} KB ${badge(docInfo[0], docInfo[1])}</p>${doc.review_notes ? `<p>Observação: ${esc(doc.review_notes)}</p>` : ''}</div><div class="row-actions"><a class="table-action" href="documento.php?id=${doc.id}" target="_blank" rel="noreferrer">Abrir arquivo</a><button class="table-action" data-doc-approve="${doc.id}" type="button">Aprovar</button><button class="table-action danger" data-doc-reject="${doc.id}" type="button">Recusar</button></div></div>`;
        }).join('') : emptyState('Nenhum documento enviado.')}</div>
        <h3>Especialidades e agenda</h3>
        <div class="metric-list">${result.data.specialties.map((item) => `<div class="metric-row"><span>${esc(item.name)}</span><b>${esc(item.notes || '')}</b></div>`).join('') || emptyState('Sem especialidades cadastradas.')}
        ${result.data.availability.map((item) => `<div class="metric-row"><span>Dia ${esc(item.weekday)} · ${esc(item.start_time)}–${esc(item.end_time)}</span><b>${esc(modeLabel(item.mode))}</b></div>`).join('')}</div>
        <h3>Histórico e auditoria</h3>
        <div class="metric-list">${result.data.history.map((item) => `<div class="metric-row"><span>${esc(item.created_at)}</span><b>${esc(item.action)}${item.details ? ` · ${esc(item.details)}` : ''}</b></div>`).join('') || emptyState('Sem eventos registrados.')}</div>
        <div class="field" style="margin-top:16px"><label for="adminSupporterNotes">Observação da análise</label><textarea id="adminSupporterNotes" placeholder="Justificativa para aprovar, recusar ou suspender."></textarea></div>
        <div class="case-actions">
          <button class="secondary" type="button" data-supporter-decision="reject">Não aprovar</button>
          <button class="secondary" type="button" data-supporter-decision="suspend">Suspender</button>
          <button class="primary" type="button" data-supporter-decision="approve">Aprovar cadastro</button>
          <button class="secondary" type="button" data-close-modal="caseModal">Fechar</button>
        </div>`;
      openModal('caseModal');
      bindModalButtons();
      document.querySelectorAll('[data-supporter-decision]').forEach((button) => { button.onclick = () => decideSupporter(id, button.dataset.supporterDecision); });
      document.querySelectorAll('[data-doc-approve]').forEach((button) => { button.onclick = () => reviewDocument(button.dataset.docApprove, 'approve', id); });
      document.querySelectorAll('[data-doc-reject]').forEach((button) => { button.onclick = () => reviewDocument(button.dataset.docReject, 'reject', id); });
    } catch (error) { toast(error.message); }
  }

  async function decideSupporter(id, decision) {
    const notes = el('#adminSupporterNotes')?.value || '';
    if (decision !== 'approve' && !notes.trim()) { toast('Informe o motivo da decisão.'); return; }
    try {
      const result = await api('admin_supporter_decide', { id, decision, notes });
      closeModal('caseModal');
      toast(result.message);
      loadAdminSupporters();
    } catch (error) { toast(error.message); }
  }

  async function reviewDocument(documentId, decision, supporterId) {
    const notes = decision === 'reject' ? window.prompt('Motivo da recusa do documento:') : (window.prompt('Observação (opcional):') || '');
    if (decision === 'reject' && !notes) { toast('Informe o motivo da recusa do documento.'); return; }
    try {
      const result = await api('admin_supporter_document_review', { id: documentId, decision, notes });
      toast(result.message);
      closeModal('caseModal');
      openAdminSupporter(supporterId);
    } catch (error) { toast(error.message); }
  }

  /* ------------------------------------------------------------------ Cadastro público */

  function bindOnboarding() {
    const cards = document.querySelectorAll('#supporterTypeCards .type-card');
    const field = el('#supporterTypeField');
    const hint = el('#supporterTypeHint');
    const documents = el('#supporterRegisterDocuments');
    const hints = {
      professional: 'Perfil de profissional autônomo: informe o registro no conselho profissional.',
      institution: 'Perfil de instituição/ONG: informe razão social, pessoa responsável e CNPJ.',
          };
    const requiredDocs = {
      professional: ['Documento de identidade (RG ou CNH)', 'Comprovante de registro no conselho profissional', 'Comprovante de endereço'],
      institution: ['Documento de identidade do responsável', 'Cartão CNPJ ou estatuto da instituição', 'Comprovante de endereço da sede'],
          };
    const applyType = (type) => {
      if (field) field.value = type;
      cards.forEach((card) => card.classList.toggle('active', card.dataset.type === type));
      document.querySelectorAll('[data-type-field]').forEach((block) => {
        const active = block.dataset.typeField === type;
        block.classList.toggle('hidden', !active);
        block.querySelectorAll('input').forEach((input) => { input.required = active && ['council_name', 'council_number', 'legal_name', 'responsible_name'].includes(input.name); });
      });
      if (hint) hint.textContent = hints[type];
      if (documents) documents.innerHTML = requiredDocs[type].map((label) => `<article class="notice-item"><strong>${esc(label)}</strong><p>Você poderá enviar este arquivo depois de entrar na plataforma.</p></article>`).join('');
    };
    cards.forEach((card) => { card.onclick = () => applyType(card.dataset.type); });
    applyType(field?.value || 'professional');

    el('#supporterRegisterForm')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      try {
        const result = await sapi('register', Object.fromEntries(new FormData(form).entries()));
        form.classList.add('hidden');
        el('#supporterRegisterSuccess')?.classList.remove('hidden');
        const text = el('#supporterRegisterSuccessText');
        if (text) text.textContent = `Perfil ${result.data.type_label} registrado. Nossa equipe vai conferir seus documentos antes de liberar os atendimentos.`;
        const list = el('#supporterRegisterSuccessList');
        if (list) list.innerHTML = (result.data.required_documents || []).map((doc) => `<article class="notice-item"><strong>${esc(doc.label)}</strong><p>Envie este documento em “Documentos e verificação” depois de entrar.</p></article>`).join('');
        toast(result.message);
      } catch (error) { toast(error.message); }
    });
    el('#onboardingBack')?.addEventListener('click', () => { el('#supporterOnboarding')?.classList.add('hidden'); el('#landingScreen')?.classList.remove('hidden'); });
    el('#supporterRegisterLogin')?.addEventListener('click', () => { el('#supporterOnboarding')?.classList.add('hidden'); goToLogin(); });
    el('#supporterRegisterHome')?.addEventListener('click', () => { el('#supporterOnboarding')?.classList.add('hidden'); el('#landingScreen')?.classList.remove('hidden'); });
  }

  document.addEventListener('DOMContentLoaded', bindOnboarding);
  setTimeout(bindOnboarding, 0);
  // Delegação direta: o botão fica dentro da tela de login, que pode ser
  // montada/alternada depois da inicialização principal do app.js.
  document.addEventListener('click', (event) => {
    const button = event.target.closest('#supporterRegisterBtn');
    if (!button) return;
    event.preventDefault();
    document.querySelector('#loginScreen')?.classList.add('hidden');
    document.querySelector('#landingScreen')?.classList.add('hidden');
    document.querySelector('#supporterOnboarding')?.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  /* ------------------------------------------------------------------ Registro de telas */

  registerView('supporter-verificacao', loadVerification);
  registerView('supporter-dashboard', loadSupporterDashboard);
  registerView('supporter-notificacoes', loadSupporterNotifications);
  registerView('supporter-solicitacoes', loadSupporterRequests);
  registerView('supporter-atendimentos', () => loadAssignments());
  registerView('supporter-agenda', loadAgenda);
  registerView('supporter-mensagens', loadConversations);
  registerView('mensagens', loadConversations);
  registerView('supporter-servicos', loadSupporterServices);
  registerView('supporter-perfil', loadSupporterProfile);
  registerView('supporter-documentos', loadSupporterDocuments);
  registerView('supporter-seguranca', loadSecurity);
  registerView('admin-apoiadores', loadAdminSupporters);

  registerBinder(() => {
    el('#supporterProfileForm')?.addEventListener('submit', submitProfile);
    el('#supSpecialtyForm')?.addEventListener('submit', (event) => submitInlineForm(event, 'specialty_save', loadSupporterProfile));
    el('#supAvailabilityForm')?.addEventListener('submit', (event) => submitInlineForm(event, 'availability_save', loadSupporterProfile));
    el('#supLocationForm')?.addEventListener('submit', (event) => submitInlineForm(event, 'location_save', loadSupporterProfile));
    el('#supTimeOffForm')?.addEventListener('submit', submitTimeOff);
    el('#supDocumentForm')?.addEventListener('submit', submitDocument);
    el('#supPasswordForm')?.addEventListener('submit', submitPassword);
    el('#supRiskForm')?.addEventListener('submit', submitRisk);
    el('#supRevokeRemember')?.addEventListener('click', revokeRemember);
    el('#supporterAppointmentForm')?.addEventListener('submit', submitAppointment);
    el('#supporterCloseForm')?.addEventListener('submit', submitClose);
    el('#supporterServiceForm')?.addEventListener('submit', submitService);
    el('#supNewService')?.addEventListener('click', () => openServiceModal(null));
    el('#supNewAppointment')?.addEventListener('click', () => openAppointmentModal({}));
    el('#supLoadSlots')?.addEventListener('click', loadSlots);
    el('#supRequestSearch')?.addEventListener('input', renderSupporterRequests);
    el('#supRequestOnlyMatches')?.addEventListener('change', renderSupporterRequests);
    document.querySelectorAll('.scope-tabs [data-sup-scope]').forEach((button) => { button.onclick = () => loadAssignments(button.dataset.supScope); });
    document.querySelectorAll('[data-message-form]').forEach((form) => {
      form.addEventListener('submit', submitMessage);
      const input = form.querySelector('[data-message-input]');
      input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); }
      });
    });
    el('#supporterRegisterBtn')?.addEventListener('click', () => {
      el('#loginScreen')?.classList.add('hidden');
      el('#landingScreen')?.classList.add('hidden');
      el('#supporterOnboarding')?.classList.remove('hidden');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  // Atalho: abre a tela de verificação quando o cadastro ainda não foi aprovado.
  document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-view^="supporter-"], [data-view="mensagens"]');
    if (!target) return;
    const name = target.dataset.view;
    if (!canWork() && ['supporter-dashboard', 'supporter-solicitacoes', 'supporter-atendimentos', 'supporter-agenda'].includes(name)) {
      event.preventDefault();
      event.stopPropagation();
      toast('Seu cadastro está em análise. Envie os documentos pendentes para liberar o atendimento.');
      showView('supporter-verificacao');
    }
  }, true);
})();
