<?php
require_once __DIR__ . '/../includes/helpers.php';
$user = current_user();
$loggedIn = (bool) $user;
$isStaff = $user && in_array($user['role'], ['admin', 'supporter', 'professional'], true);
$userName = $user['name'] ?? 'Ana Carolina Silva';
$userInitials = initials($userName);
$roleLabel = $user ? role_label($user['role']) : 'Beneficiária';
$isSupporter = $user && is_supporter_role($user['role']);
$supporterState = $isSupporter ? supporter_area_state(db(), $user) : null;
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>Lumen — Cuidado que ilumina</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="stylesheet" href="assets/app.css">
</head>
<body data-authenticated="<?= $loggedIn ? '1' : '0' ?>" data-role="<?= e($user['role'] ?? 'beneficiary') ?>">
  <section class="landing-screen <?= $loggedIn ? 'hidden' : '' ?>" id="landingScreen">
    <header class="landing-header">
      <a class="landing-brand" href="#inicio" aria-label="Lumen início">
        <img class="landing-brand-mark" src="assets/logo.png" alt="Logo do Lumen">
        <span><strong>LUMEN</strong><small>cuidado que ilumina</small></span>
      </a>
      <nav class="landing-nav" aria-label="Navegação da página inicial">
        <a href="#recursos">Recursos</a>
        <a href="#sobre">Sobre</a>
        <a href="#ajuda">Ajuda</a>
        <button class="landing-login-link" id="landingLoginTop" type="button">Entrar</button>
      </nav>
      <button class="landing-menu" id="landingMenu" type="button" aria-label="Abrir menu" aria-expanded="false">☰</button>
    </header>
    <main id="inicio">
      <section class="landing-hero">
        <div class="landing-copy">
          <span class="landing-eyebrow">UM ESPAÇO PARA VOCÊ</span>
          <h1>Seu cuidado,<br><em>mais perto.</em></h1>
          <p>Uma rede de apoio feita para acolher, orientar e iluminar seus caminhos com segurança, informação e cuidado.</p>
          <div class="landing-actions">
            <button class="primary landing-cta" id="landingLogin" type="button">Começar agora <span>→</span></button>
            <a class="landing-secondary" href="#sobre">Conheça o Lumen</a>
          </div>
          <div class="landing-trust"><span>✓</span> Acolhimento seguro e confidencial</div>
        </div>
        <div class="landing-illustration" aria-label="Ilustração abstrata de acolhimento">
          <div class="landing-sun"></div><div class="landing-blob"><img src="assets/fotolanding.png" alt="Ilustração de acolhimento"></div>
          <div class="landing-note note-one">♡</div><div class="landing-note note-two">✦</div>
        </div>
      </section>
      <section class="landing-resources" id="recursos" aria-labelledby="resourcesTitle">
        <div class="landing-section-heading"><span class="landing-eyebrow">ESTAMOS COM VOCÊ</span><h2 id="resourcesTitle">Apoio para cada momento</h2><p>Encontre orientação e caminhos para cuidar de você.</p></div>
        <div class="landing-cards">
          <article class="landing-card"><span class="landing-card-icon">!</span><h3>Fazer uma denúncia</h3><p>Relate uma situação com segurança e sigilo.</p><button type="button" data-landing-login>Começar denúncia →</button></article>
          <article class="landing-card"><span class="landing-card-icon">♡</span><h3>Rede de apoio</h3><p>Encontre profissionais e serviços perto de você.</p><button type="button" data-landing-login>Conhecer rede →</button></article>
          <article class="landing-card"><span class="landing-card-icon">?</span><h3>Informação e cuidado</h3><p>Conheça seus direitos e receba orientação.</p><button type="button" data-scroll="#sobre">Explorar conteúdos →</button></article>
        </div>
      </section>
      <section class="about-screen" id="sobre" aria-labelledby="aboutTitle">
        <div class="about-grid">
          <div><span class="landing-eyebrow">SOBRE O LUMEN</span><h2 id="aboutTitle">Cuidado que ilumina caminhos.</h2><p>O Lumen é um espaço digital de acolhimento que conecta beneficiárias a orientações, profissionais e serviços de apoio. Aqui, cada relato é tratado com respeito, sigilo e cuidado.</p><p>Nosso propósito é facilitar o acesso à informação e criar uma rede de proteção para que ninguém precise enfrentar uma situação difícil sozinha.</p></div>
          <div class="about-card"><strong>Como o Lumen apoia você</strong><ul><li>Solicitação de atendimento com encaminhamento adequado.</li><li>Registro seguro de denúncias e acompanhamento do protocolo.</li><li>Contatos de emergência avisados quando o SOS é acionado.</li><li>Rede de apoio com serviços e profissionais.</li></ul></div>
        </div>
      </section>
    </main>
    <footer class="landing-footer" id="ajuda">
      <div><strong>LUMEN</strong><span> Cuidado que ilumina.</span></div>
      <div class="landing-footer-links"><a href="#sobre">Sobre o Lumen</a><a href="#ajuda">Central de ajuda</a><button type="button" data-help>Privacidade e ajuda</button></div>
      <small>© <?= date('Y') ?> Lumen</small>
    </footer>
  </section>

  <section class="login-screen <?= $loggedIn ? 'hidden' : '' ?>" id="loginScreen">
    <div class="login-visual">
      <a class="landing-brand" href="#inicio"><img class="landing-brand-mark" src="assets/logo.png" alt="Logo do Lumen"><span><strong>LUMEN</strong><small>cuidado que ilumina</small></span></a>
      <div class="login-quote"><h1>Seu cuidado,<br><em>mais perto.</em></h1><p>Uma rede de apoio feita para acolher, orientar e iluminar seus caminhos.</p></div>
      <div class="login-orb">✿</div>
    </div>
    <div class="login-panel"><div class="login-panel-inner">
      <button class="link-button" id="backLanding" type="button">← Voltar para o início</button>
      <div class="mobile-login-brand">✿ LUMEN</div>
      <div class="eyebrow">BEM-VINDA AO LUMEN</div>
      <h1>Vamos começar?</h1>
      <p class="login-subtitle">Entre para acessar sua área de cuidado.</p>
      <div class="role-switch three" id="loginRoles">
        <button class="role-tab active" data-role="beneficiary" type="button">Beneficiária</button>
        <button class="role-tab" data-role="team" type="button">Admin</button>
        <button class="role-tab" data-role="team" type="button">Apoiador</button>
      </div>
      <form id="loginForm">
        <div class="field"><label for="loginEmail">E-mail</label><input id="loginEmail" name="email" type="email" placeholder="seuemail@exemplo.com" required></div>
        <div class="field password-field"><label for="loginPassword">Senha</label><div class="password-input-wrap"><input id="loginPassword" name="password" type="password" placeholder="Digite sua senha" required autocomplete="current-password"><button class="password-visibility" id="toggleLoginPassword" type="button" aria-label="Mostrar senha" aria-pressed="false">Mostrar</button></div></div>
        <div class="login-options"><label><input id="rememberLogin" name="remember" type="checkbox"> Lembrar de mim neste dispositivo por 14 dias</label><button type="button" class="link-button" id="forgotBtn">Esqueci minha senha</button></div><p class="remember-note">Não use em aparelho compartilhado. Sua senha nunca será salva.</p>
        <button class="primary login-button" type="submit">Entrar <span>→</span></button>
      </form>
      <div class="login-footer">Ainda não tem uma conta? <button class="link-button" id="registerBtn" type="button">Cadastre-se como beneficiária</button></div>
      <div class="login-footer supporter-entry">Faz parte da rede de apoio? <button class="link-button" id="supporterRegisterBtn" type="button">Cadastre-se como apoiador(a)</button></div>
    </div></div>
  </section>

  <div class="app-shell <?= $loggedIn ? '' : 'hidden' ?>" id="appShell">
    <aside class="sidebar" id="sidebar">
      <div class="brand"><span class="brand-mark">✿</span><span>LUMEN</span><small>cuidado que ilumina</small></div>
      <div class="profile-mini"><div class="avatar" id="sidebarAvatar"><?= e($userInitials) ?></div><div><strong id="sidebarName"><?= e($userName) ?></strong><span id="sidebarRole"><?= e($roleLabel) ?></span></div><button class="dots" type="button" data-help>•••</button></div>
      <nav class="nav-menu" id="userNav">
        <button class="nav-item active" data-view="dashboard"><span>⌂</span>Início</button>
        <button class="nav-item" data-view="nova-solicitacao"><span>＋</span>Solicitar atendimento</button>
        <button class="nav-item" data-view="nova-denuncia"><span>▣</span>Nova denúncia</button>
        <button class="nav-item" data-view="solicitacoes"><span>▤</span>Solicitações</button>
        <button class="nav-item" data-view="contatos"><span>♡</span>Contatos de emergência</button>
        <button class="nav-item" data-view="mapa"><span>⌖</span>Rede de apoio</button>
        <button class="nav-item" data-view="chat"><span>◌</span>Chat de apoio</button>
        <button class="nav-item" data-view="perfil"><span>◎</span>Meu perfil</button>
        <button class="nav-item" data-view="historico"><span>↺</span>Histórico</button>
        <button class="nav-item" data-view="config"><span>⚙</span>Configurações</button>
        <button class="nav-item" data-view="ajuda"><span>?</span>Central de ajuda</button>
      </nav>
      <nav class="nav-menu admin-nav hidden" id="adminNav" aria-label="Menu do administrador">
        <div class="nav-caption">VISÃO GERAL</div>
        <button class="nav-item active" data-view="admin-dashboard"><span>▦</span>Dashboard</button>
        <button class="nav-item" data-view="admin-stats"><span>◷</span>Relatórios e estatísticas</button>
        <button class="nav-item" data-view="admin-notificacoes"><span>♧</span>Notificações</button>
        <div class="nav-caption">ATENDIMENTO</div>
        <button class="nav-item" data-view="admin-denuncias"><span>▣</span>Denúncias <b class="nav-badge" id="pendingBadge">0</b></button>
        <button class="nav-item" data-view="admin-solicitacoes"><span>▤</span>Solicitações</button>
        <button class="nav-item" data-view="admin-beneficiarias"><span>◎</span>Beneficiárias</button>
        <button class="nav-item" data-view="admin-rede"><span>⌖</span>Rede de apoio</button>
        <div class="nav-caption">ADMINISTRAÇÃO</div>
        <button class="nav-item" data-view="admin-seguranca"><span>♢</span>Segurança e auditoria</button>
        <button class="nav-item" data-view="admin-config"><span>⚙</span>Configurações</button>
        <button class="nav-item" data-view="ajuda"><span>?</span>Central de ajuda</button>
      </nav>
      <nav class="nav-menu supporter-nav hidden" id="supporterNav" aria-label="Menu do apoiador">
        <div class="nav-caption">MEU PAINEL</div>
        <button class="nav-item active" data-view="supporter-dashboard"><span>▦</span>Dashboard</button>
        <button class="nav-item" data-view="supporter-solicitacoes"><span>▤</span>Solicitações recebidas</button>
        <button class="nav-item" data-view="supporter-atendimentos"><span>♡</span>Atendimentos</button>
        <button class="nav-item" data-view="supporter-agenda"><span>◷</span>Agenda</button>
        <button class="nav-item" data-view="supporter-mensagens"><span>◌</span>Mensagens</button>
        <div class="nav-caption">MEU CADASTRO</div>
        <button class="nav-item" data-view="supporter-servicos"><span>✦</span>Serviços oferecidos</button>
        <button class="nav-item" data-view="supporter-perfil"><span>◎</span>Meu perfil</button>
        <button class="nav-item" data-view="supporter-documentos"><span>▧</span>Documentos e verificação <b class="nav-badge" id="supporterDocsBadge">0</b></button>
        <div class="nav-caption">PROTEÇÃO E AJUDA</div>
        <button class="nav-item" data-view="supporter-seguranca"><span>♢</span>Segurança e orientações</button>
        <button class="nav-item" data-view="ajuda"><span>?</span>Central de ajuda</button>
      </nav>
      <div class="sidebar-bottom"><button class="help-link" type="button" data-help>❔ <span>Central de ajuda</span></button><button class="logout" id="logoutBtn" type="button">↪ <span>Sair da conta</span></button></div>
    </aside>
    <main class="main-content">
      <header class="topbar"><button class="mobile-menu" id="menuBtn" type="button" aria-label="Abrir menu">☰</button><div class="breadcrumb"><span id="areaLabel"><?= $isStaff ? 'Área da equipe' : 'Área da beneficiária' ?></span><strong>/</strong><b id="pageLabel">Início</b></div><div class="top-actions">
        <div class="menu-anchor"><button class="icon-button" id="accessibilityBtn" title="Opções de acessibilidade" type="button" aria-label="Opções de acessibilidade" aria-haspopup="true" aria-expanded="false">◉</button><section class="accessibility-menu" id="accessibilityMenu" aria-label="Opções de acessibilidade" hidden><div class="popover-heading"><strong>Acessibilidade</strong><span>Personalize sua leitura</span></div><div class="access-row"><span>Tamanho da letra</span><div class="access-controls"><button type="button" data-font-step="-1" aria-label="Diminuir tamanho da letra">A−</button><button type="button" data-font-step="0" aria-label="Restaurar tamanho da letra">A</button><button type="button" data-font-step="1" aria-label="Aumentar tamanho da letra">A+</button></div></div><button class="access-option" id="themeToggle" type="button"><span>◐</span><span><strong>Tema escuro</strong><small>Alternar tema claro/escuro</small></span><i id="themeState">Desativado</i></button><button class="access-option" id="contrastToggle" type="button"><span>◒</span><span><strong>Alto contraste</strong><small>Realçar textos e contornos</small></span><i id="contrastState">Desativado</i></button></section></div>
        <button class="icon-button notification-shortcut" id="notifyBtn" title="Notificações" aria-label="Notificações" type="button">♧</button>
        <div class="menu-anchor profile-anchor"><button class="top-avatar" id="topAvatar" type="button" aria-label="Abrir opções do perfil" aria-haspopup="true" aria-expanded="false"><?= e($userInitials) ?></button><nav class="profile-menu" id="profileMenu" aria-label="Opções do perfil" hidden>
          <button type="button" data-view="perfil"><span class="menu-symbol">♙</span><span>Perfil</span></button>
          <button type="button" data-view="perfil" data-edit-profile="1"><span class="menu-symbol">✎</span><span>Editar dados</span></button>
          <button type="button" data-menu-notifications><span class="menu-symbol">♧</span><span>Notificações</span></button>
          <button type="button" data-profile-logout><span class="menu-symbol solid">⇥</span><span>Sair da conta</span></button>
          <div class="profile-menu-divider"></div>
          <button type="button" data-view="privacidade"><span class="menu-symbol">♢</span><span>Privacidade</span></button>
          <button type="button" data-view="sobre"><span class="menu-symbol">ⓘ</span><span>Sobre</span></button>
        </nav></div>
      </div></header>
      <div id="viewContainer"></div>
    </main>
  </div>

  <template id="view-dashboard" data-label="Início"><section class="view-heading"><div><div class="eyebrow">ACOLHIMENTO E PROTEÇÃO</div><h1>Bem-vinda, <span data-user-first><?= e(explode(' ', $userName)[0]) ?></span>!</h1><p>Estamos aqui para cuidar de você. Como podemos ajudar hoje?</p></div><button class="primary" data-view="nova-solicitacao">+ Solicitar atendimento</button></section><div class="stat-grid"><div class="stat-card"><span class="stat-icon">♧</span><small>Solicitações abertas</small><strong id="statRequests">0</strong><em>Atualizado agora</em></div><div class="stat-card"><span class="stat-icon">♡</span><small>Contatos de emergência</small><strong id="statContacts">0</strong><em>Sua rede de confiança</em></div><div class="stat-card"><span class="stat-icon">⌖</span><small>Profissionais próximos</small><strong>83</strong><em>Disponíveis agora</em></div><div class="stat-card"><span class="stat-icon">★</span><small>Benefícios ativos</small><strong>256</strong><em>+ 3 novos benefícios</em></div></div><div class="sos-banner"><div><h2>Precisa de ajuda agora?</h2><p>Seus contatos de emergência serão avisados por e-mail e WhatsApp.</p></div><button class="sos-button" id="sosBtn" type="button">SOS</button></div><div class="panel followup-panel"><div class="panel-title"><div><div class="eyebrow">ACOMPANHAMENTO</div><h3>Evolução dos seus pedidos</h3></div><span class="muted-note">Últimos 6 meses</span></div><div class="followup-chart" aria-label="Gráfico de acompanhamento dos pedidos"><div class="chart-bars"><span style="height:32%" title="Maio"></span><span style="height:46%" title="Junho"></span><span style="height:40%" title="Julho"></span><span style="height:64%" title="Agosto"></span><span style="height:78%" title="Setembro"></span><span style="height:92%" title="Outubro"></span></div><div class="chart-labels"><span>Mai</span><span>Jun</span><span>Jul</span><span>Ago</span><span>Set</span><span>Out</span></div></div><div class="legend"><span>Solicitações acompanhadas</span><span class="legend-green">Atendimentos concluídos</span></div></div><br><div class="two-col"><div class="panel"><div class="panel-title"><h3>Atalhos de cuidado</h3></div><div class="admin-shortcuts"><button type="button" data-view="nova-solicitacao">♡ <span>Solicitar atendimento</span>→</button><button type="button" data-view="nova-denuncia">▣ <span>Registrar uma denúncia</span>→</button><button type="button" data-view="contatos">◎ <span>Gerenciar contatos de emergência</span>→</button></div></div><div class="panel"><div class="panel-title"><h3>Confidencialidade</h3></div><p class="helper-text">Suas informações são acessadas apenas por pessoas autorizadas e utilizadas para oferecer o apoio adequado. Em risco imediato, acione o SOS ou procure o serviço de emergência da sua região.</p></div></div></template>

  <template id="view-nova-solicitacao" data-label="Solicitar atendimento"><section class="view-heading"><div><div class="eyebrow">PRIMEIRO PASSO</div><h1>Solicitação de atendimento</h1><p>Conte pra nós como podemos te ajudar.</p></div><span class="safe-badge">🔒 Ambiente seguro</span></section><form class="panel form-card" id="supportRequestForm"><div class="form-section-title"><span>01</span><div><h3>Vamos entender o que você precisa</h3><p>Escolha o tipo de acolhimento que faz sentido para este momento.</p></div></div><div class="field"><label for="requestCategory">Categoria de atendimento</label><select id="requestCategory" name="category" required><option value="">Selecione o tipo de atendimento</option><option>Orientação</option><option>Atendimento psicológico</option><option>Orientação jurídica</option><option>Assistência social</option><option>Outro</option></select></div><div class="field"><label for="requestDescription">Conte um pouco mais</label><textarea id="requestDescription" name="description" required placeholder="Escreva como podemos ajudar você..."></textarea></div><div class="field"><label for="preferredContact">Como prefere ser contatada? <span class="optional">(opcional)</span></label><select id="preferredContact" name="preferred_contact"><option value="">Escolha uma opção</option><option>E-mail</option><option>Telefone</option><option>WhatsApp</option><option>Não tenho preferência</option></select></div><div class="privacy-note"><span>♧</span><div><strong>Seu pedido é confidencial</strong><p>Uma pessoa da equipe Lumen irá analisar sua solicitação e retornar pelo canal disponível.</p></div></div><div class="form-actions"><button type="button" class="secondary" data-view="dashboard">Voltar</button><button class="primary" type="submit">Enviar solicitação <span>→</span></button></div></form></template>

  <template id="view-nova-denuncia" data-label="Nova denúncia"><section class="view-heading"><div><div class="eyebrow">ACOLHIMENTO E PROTEÇÃO</div><h1>Fazer uma nova denúncia</h1><p>Conte o que está acontecendo. Suas informações serão tratadas com cuidado e sigilo.</p></div><span class="safe-badge">🔒 Ambiente seguro</span></section><div class="stepper"><div class="step active"><b>1</b><span>Triagem</span></div><div class="step-line"></div><div class="step"><b>2</b><span>Relato</span></div><div class="step-line"></div><div class="step"><b>3</b><span>Revisão</span></div></div><form class="panel form-card report-form" id="reportForm" enctype="multipart/form-data"><div class="form-section-title"><span>01</span><div><h3>Vamos entender melhor</h3><p>Responda algumas perguntas para direcionarmos seu atendimento.</p></div></div><div class="field"><label for="reportCategory">Que tipo de situação você deseja denunciar?</label><select id="reportCategory" name="category" required><option value="">Selecione uma opção</option><option>Violência física</option><option>Violência psicológica</option><option>Violência sexual</option><option>Negligência ou abandono</option><option>Discriminação</option><option>Outra situação</option></select></div><div class="inline-fields"><div class="field"><label for="occurredWhen">Quando aconteceu?</label><select id="occurredWhen" name="occurred_when" required><option value="">Selecione</option><option>Está acontecendo agora</option><option>Hoje</option><option>Nos últimos 7 dias</option><option>Há mais tempo</option></select></div><div class="field"><label for="safeNow">Você está em segurança agora?</label><select id="safeNow" name="safe_now" required><option value="">Selecione</option><option>Sim, estou segura</option><option>Não, preciso de ajuda</option><option>Não tenho certeza</option></select></div></div><div class="field"><label for="reportLocation">Onde aconteceu?</label><input id="reportLocation" name="location" required placeholder="Informe o local ou uma referência"></div><div class="form-section-title second"><span>02</span><div><h3>Conte o que está passando</h3><p>Você pode escrever com suas palavras. Não precisa ter certeza de como chamar a situação.</p></div></div><div class="field"><label for="reportNarrative">Seu relato</label><textarea id="reportNarrative" name="narrative" required placeholder="Escreva aqui o que aconteceu, quem está envolvido e como podemos ajudar..."></textarea><small class="field-hint">Evite incluir senhas ou dados bancários.</small></div><div class="field"><label for="reportFiles">Anexar documentos ou laudos <span class="optional">(opcional)</span></label><label class="upload-box" for="reportFiles"><span class="upload-icon">↑</span><strong>Clique para anexar arquivos</strong><small>PDF, JPG ou PNG · até 10 MB por arquivo</small><input id="reportFiles" name="files[]" type="file" multiple accept=".pdf,.jpg,.jpeg,.png"></label><div id="fileList" class="file-list"></div></div><div class="privacy-note"><span>♧</span><div><strong>Seu relato é confidencial</strong><p>As informações serão vistas somente pela equipe autorizada do Lumen e usadas para oferecer o apoio adequado.</p></div></div><div class="form-actions"><button type="button" class="secondary" data-view="dashboard">Cancelar</button><button class="primary" type="submit">Enviar denúncia <span>→</span></button></div></form></template>

  <template id="view-solicitacoes" data-label="Solicitações"><section class="view-heading"><div><div class="eyebrow">ACOMPANHE SEUS PEDIDOS</div><h1>Minhas solicitações</h1><p>Consulte o andamento dos seus pedidos de apoio e denúncias.</p></div><button class="primary" data-view="nova-solicitacao">+ Nova solicitação</button></section><div class="panel"><div id="requestsContent" class="request-list"><div class="empty-state">Carregando solicitações...</div></div></div></template>

  <template id="view-contatos" data-label="Contatos de emergência"><section class="view-heading"><div><div class="eyebrow">SUA REDE DE CONFIANÇA</div><h1>Contatos de emergência</h1><p>Cadastre pessoas de confiança para que sejam avisadas quando você acionar o SOS.</p></div></section><div class="two-col"><form class="panel form-card" id="contactForm"><div class="form-section-title"><span>♡</span><div><h3>Adicionar pessoa de confiança</h3><p>Ela receberá um convite por e-mail e WhatsApp.</p></div></div><div class="field"><label>Nome completo</label><input name="name" required placeholder="Nome da pessoa"></div><div class="field"><label>E-mail</label><input name="email" type="email" required placeholder="pessoa@exemplo.com"></div><div class="field"><label>Telefone / WhatsApp</label><input name="phone" required placeholder="(00) 00000-0000"></div><div class="field"><label>Endereço</label><input name="address" required placeholder="Rua, número, cidade e estado"></div><div class="form-actions"><button class="primary" type="submit">Enviar convite <span>→</span></button></div></form><div class="panel"><div class="panel-title"><h3>Minha rede cadastrada</h3></div><div id="contactsContent" class="request-list"><div class="empty-state">Carregando contatos...</div></div></div></div></template>

  <template id="view-mapa" data-label="Rede de apoio"><section class="view-heading"><div><div class="eyebrow">CUIDADO PERTO DE VOCÊ</div><h1>Rede de apoio</h1><p>Encontre serviços e profissionais disponíveis na sua região.</p></div></section><div class="panel"><div class="map-box"><iframe title="Mapa de delegacias da mulher" src="https://www.google.com/maps?q=Delegacia+da+Mulher+S%C3%A3o+Paulo,+SP&z=11&output=embed" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div><div class="map-locations"><a class="map-location" href="https://maps.google.com/?q=Delegacia+da+Mulher+S%C3%A3o+Paulo,+SP" target="_blank" rel="noreferrer"><strong>São Paulo / SP</strong><span>Delegacia da Mulher</span></a><a class="map-location" href="https://maps.google.com/?q=Delegacia+da+Mulher+Rio+de+Janeiro,+RJ" target="_blank" rel="noreferrer"><strong>Rio de Janeiro / RJ</strong><span>Delegacia da Mulher</span></a><a class="map-location" href="https://maps.google.com/?q=Delegacia+da+Mulher+Recife,+PE" target="_blank" rel="noreferrer"><strong>Recife / PE</strong><span>Delegacia da Mulher</span></a></div></div></template>

  <template id="view-perfil" data-label="Perfil"><section class="view-heading"><div><div class="eyebrow">SUAS INFORMAÇÕES</div><h1>Perfil</h1><p>Consulte e atualize seus dados de contato.</p></div><button class="secondary" id="editProfileBtn" type="button">✎ Editar dados</button></section><div class="panel profile-card"><div class="profile-hero"><div class="large-avatar" id="profileAvatar"><?= e($userInitials) ?></div><strong id="profileName"><?= e($userName) ?></strong><span id="profileRole"><?= e($roleLabel) ?></span></div><div class="info-list" id="profileInfo"><div class="empty-state">Carregando dados...</div></div><form id="profileEditForm" class="profile-edit-form hidden"><h3>Editar dados</h3><p>Atualize somente as informações necessárias para contato.</p><div class="field"><label for="profileEditName">Nome completo</label><input id="profileEditName" name="name" required maxlength="160"></div><div class="field"><label for="profileEditEmail">E-mail</label><input id="profileEditEmail" name="email" type="email" required maxlength="190"></div><div class="field"><label for="profileEditPhone">Telefone</label><input id="profileEditPhone" name="phone" maxlength="30"></div><div class="form-actions"><button class="secondary" id="cancelProfileEdit" type="button">Cancelar</button><button class="primary" type="submit">Salvar alterações</button></div></form></div></template>

  <template id="view-notificacoes" data-label="Notificações"><section class="view-heading"><div><div class="eyebrow">AVISOS E ATUALIZAÇÕES</div><h1>Notificações</h1><p>Mensagens relacionadas à sua conta e aos seus pedidos.</p></div></section><div class="panel"><div class="notice-list" id="userNotifications"><div class="empty-state">Carregando notificações...</div></div></div></template>

  <template id="view-privacidade" data-label="Privacidade"><section class="view-heading"><div><div class="eyebrow">CUIDADO COM SEUS DADOS</div><h1>Privacidade</h1><p>Informações sobre o acesso e o uso dos dados no Lumen.</p></div><span class="safe-badge">♢ Acesso protegido</span></section><div class="privacy-page-grid"><article class="panel"><h3>Quem acessa suas informações?</h3><p>Seus dados e relatos são destinados à equipe autorizada para acolhimento, triagem e encaminhamento. As consultas a registros sensíveis podem ser registradas para fins de auditoria.</p></article><article class="panel"><h3>Como seus dados são utilizados?</h3><p>As informações são usadas para responder às solicitações, acompanhar denúncias e facilitar o contato com serviços de apoio. Compartilhe apenas o necessário para o atendimento.</p></article><article class="panel"><h3>Você pode acompanhar</h3><p>Use as telas de solicitações e histórico para consultar o andamento. Um encaminhamento ou encerramento administrativo não significa, por si só, que a situação de violência terminou.</p></article><article class="panel"><h3>Segurança imediata</h3><p>O Lumen não substitui os serviços de emergência nem representa monitoramento em tempo real. Em risco imediato, procure um local seguro se possível e ligue 190. A Central de Atendimento à Mulher atende pelo 180.</p></article></div></template>

  <template id="view-historico" data-label="Histórico"><section class="view-heading"><div><div class="eyebrow">TUDO O QUE JÁ FIZEMOS JUNTAS</div><h1>Histórico de solicitações</h1><p>Revise seus atendimentos e denúncias anteriores.</p></div></section><div class="panel table-wrap"><table class="data-table"><thead><tr><th>Protocolo</th><th>Categoria</th><th>Data</th><th>Status</th><th>Ação</th></tr></thead><tbody id="historyContent"><tr><td colspan="5" class="empty-state">Carregando...</td></tr></tbody></table></div></template>

  <template id="view-config" data-label="Configurações"><section class="view-heading"><div><div class="eyebrow">PREFERÊNCIAS</div><h1>Configurações</h1><p>Personalize sua experiência no Lumen.</p></div></section><div class="settings-list"><div class="setting"><div><strong>Notificações de solicitações</strong><p>Receba atualizações sobre seus atendimentos.</p></div><button class="toggle preference-toggle active" data-setting="beneficiary-notifications" type="button" aria-label="Alternar notificações"></button></div><div class="setting"><div><strong>Lembretes de consultas</strong><p>Enviaremos um lembrete antes dos seus horários.</p></div><button class="toggle preference-toggle active" data-setting="beneficiary-reminders" type="button" aria-label="Alternar lembretes"></button></div></div></template>

  <template id="view-sobre" data-label="Sobre o Lumen"><section class="view-heading"><div><div class="eyebrow">CUIDADO QUE ILUMINA</div><h1>Sobre o Lumen</h1><p>Uma plataforma para acolher, orientar e conectar você a uma rede de proteção.</p></div></section><div class="about-grid"><div class="panel"><h2>O que é o Lumen?</h2><p>O Lumen é um espaço seguro para solicitar atendimento, registrar denúncias, acompanhar encaminhamentos e organizar uma rede de contatos de emergência.</p><p>A plataforma foi pensada para reduzir barreiras e aproximar informação, acolhimento e serviços especializados de quem precisa.</p></div><div class="about-card"><strong>Princípios do Lumen</strong><ul><li>Acolhimento sem julgamentos.</li><li>Confidencialidade e cuidado com os dados.</li><li>Encaminhamento responsável para a rede de apoio.</li><li>Autonomia para acompanhar, complementar ou recorrer.</li></ul></div></div></template>

  <template id="view-admin-dashboard" data-label="Visão geral"><section class="view-heading"><div><div class="eyebrow">PAINEL ADMINISTRATIVO</div><h1>Olá, equipe Lumen!</h1><p>Veja o que precisa de atenção hoje.</p></div><button class="primary" data-view="admin-denuncias">Ver denúncias</button></section><div class="stat-grid"><div class="stat-card"><span class="stat-icon">▣</span><small>Denúncias novas</small><strong id="adminPending">0</strong><em>Requerem atenção</em></div><div class="stat-card"><span class="stat-icon">◷</span><small>Em triagem</small><strong id="adminTriage">0</strong><em>Em análise</em></div><div class="stat-card"><span class="stat-icon">♡</span><small>Atendimentos ativos</small><strong id="adminActive">0</strong><em>Solicitações abertas</em></div><div class="stat-card"><span class="stat-icon">✓</span><small>Resolvidas este mês</small><strong id="adminResolved">0</strong><em>Encaminhadas</em></div></div><div class="panel followup-panel"><div class="panel-title"><div><div class="eyebrow">ACOMPANHAMENTO OPERACIONAL</div><h3>Fluxo de casos</h3></div><button class="link-button" data-view="admin-stats">Ver estatísticas →</button></div><div class="followup-chart" aria-label="Gráfico do fluxo de casos"><div class="chart-bars admin-bars"><span style="height:78%" title="Novos"></span><span style="height:54%" title="Triagem"></span><span style="height:42%" title="Encaminhados"></span><span style="height:28%" title="Acompanhamento"></span><span style="height:18%" title="Concluídos"></span></div><div class="chart-labels"><span>Novos</span><span>Triagem</span><span>Encam.</span><span>Acomp.</span><span>Concl.</span></div></div><div class="legend"><span>Casos por etapa do fluxo</span></div></div><div class="two-col"><div class="panel"><div class="panel-title"><h3>Denúncias recentes</h3><button class="link-button" data-view="admin-denuncias">Ver todas →</button></div><div id="recentReports" class="request-list"><div class="empty-state">Carregando...</div></div></div><div class="panel"><div class="panel-title"><h3>Atalhos da equipe</h3></div><div class="admin-shortcuts"><button data-view="admin-denuncias">▣ <span>Gerenciar denúncias</span>→</button><button data-view="admin-beneficiarias">◎ <span>Consultar beneficiárias</span>→</button><button data-view="admin-rede">⌖ <span>Atualizar rede de apoio</span>→</button></div></div></div></template>

  <template id="view-admin-denuncias" data-label="Denúncias"><section class="view-heading"><div><div class="eyebrow">GESTÃO DE CASOS</div><h1>Denúncias recebidas</h1><p>Faça a triagem e encaminhe cada caso com cuidado.</p></div><div class="export-actions"><button class="secondary" id="exportReportsPdf" type="button">Exportar PDF</button><button class="secondary" id="exportReports" type="button">Exportar CSV</button></div></section><div class="report-workflow"><div><strong>Triagem rápida</strong><span>Filtre os casos pendentes e encaminhe direto pela lista. O detalhe continua disponível para decisões que exigem mais contexto.</span></div><div class="report-quick-actions"><button class="secondary active" type="button" data-report-filter="">Todos</button><button class="secondary" type="button" data-report-filter="pending">Novas</button><button class="secondary" type="button" data-report-filter="triage">Em triagem</button></div></div><div class="admin-filters"><input id="reportSearch" placeholder="⌕ Buscar por protocolo, nome ou categoria"><select id="reportStatusFilter"><option value="">Todos os status</option><option value="pending">Nova</option><option value="triage">Em triagem</option><option value="forwarded">Encaminhada</option><option value="rejected">Desaprovada</option></select><select id="reportTypeFilter"><option value="">Todos os tipos</option><option>Violência psicológica</option><option>Violência física</option><option>Violência sexual</option><option>Negligência ou abandono</option><option>Discriminação</option></select></div><div class="panel table-wrap"><table class="data-table admin-table"><thead><tr><th>Protocolo</th><th>Denunciante</th><th>Categoria</th><th>Recebida em</th><th>Status</th><th>Ação</th></tr></thead><tbody id="reportsTable"><tr><td colspan="6" class="empty-state">Carregando...</td></tr></tbody></table></div></template>

  <template id="view-admin-beneficiarias" data-label="Beneficiárias"><section class="view-heading"><div><div class="eyebrow">PESSOAS CADASTRADAS</div><h1>Beneficiárias</h1><p>Gerencie os cadastros e acompanhe o histórico de cuidado.</p></div><button class="primary" id="adminRegisterButton" type="button">+ Cadastrar pessoa</button></section><div class="panel table-wrap"><table class="data-table"><thead><tr><th>Nome</th><th>Contato</th><th>Última atividade</th><th>Casos ativos</th><th></th></tr></thead><tbody id="beneficiariesContent"><tr><td colspan="5" class="empty-state">Carregando...</td></tr></tbody></table></div></template>

  <template id="view-admin-rede" data-label="Rede de apoio"><section class="view-heading"><div><div class="eyebrow">PARCEIROS E SERVIÇOS</div><h1>Rede de apoio</h1><p>Atualize os pontos de atendimento disponíveis.</p></div><button class="primary" id="addServiceButton" type="button">+ Adicionar serviço</button></section><div class="panel"><div id="servicesContent" class="request-list"><div class="empty-state">Carregando...</div></div></div></template>

  <template id="view-admin-config" data-label="Configurações administrativas"><section class="view-heading"><div><div class="eyebrow">CONTROLE DA PLATAFORMA</div><h1>Configurações</h1><p>Preferências operacionais da equipe e informações de perfil.</p></div></section><div class="settings-list"><div class="setting"><div><strong>Alertas de denúncia urgente</strong><p>Destacar relatos que indiquem risco imediato para priorização humana.</p></div><button class="toggle preference-toggle active" data-setting="admin-urgent-alerts" type="button" aria-label="Alternar alertas urgentes"></button></div><div class="setting"><div><strong>Dupla revisão antes do encaminhamento</strong><p>Recomendado para situações de maior sensibilidade.</p></div><button class="toggle preference-toggle active" data-setting="admin-double-review" type="button" aria-label="Alternar dupla revisão"></button></div><div class="setting"><div><strong>Notificações administrativas</strong><p>Exibir avisos de novos registros na caixa da equipe.</p></div><button class="toggle preference-toggle active" data-setting="admin-notifications" type="button" aria-label="Alternar notificações"></button></div></div><div class="panel account-panel"><div class="eyebrow">SEU PERFIL</div><h3>Dados do administrador</h3><p>Para alterar senha, use o fluxo “Esqueci minha senha” da tela de entrada. Não compartilhe suas credenciais.</p><button class="secondary" id="adminProfileInfo" type="button">Ver informações do perfil</button></div></template>

  <template id="view-admin-solicitacoes" data-label="Solicitações"><section class="view-heading"><div><div class="eyebrow">ACOMPANHAMENTO DE CUIDADO</div><h1>Solicitações de ajuda</h1><p>Acompanhe cada pedido até o encaminhamento. Encerrar administrativamente não significa que a situação foi resolvida.</p></div><span class="safe-badge">🔒 Acesso restrito</span></section><div class="privacy-strip"><b>Fluxo de atendimento</b><span>Recebida → Em triagem → Encaminhada → Acompanhamento / concluída</span></div><div class="admin-filters"><input id="requestSearch" placeholder="⌕ Buscar por nome ou categoria"><select id="requestStatusFilter"><option value="">Todos os status</option><option value="pending">Recebida</option><option value="triage">Em triagem</option><option value="forwarded">Encaminhada</option><option value="resolved">Concluída</option></select></div><div class="panel table-wrap"><table class="data-table"><thead><tr><th>Beneficiária</th><th>Tipo de apoio</th><th>Recebida em</th><th>Status</th><th>Ação</th></tr></thead><tbody id="requestsAdminTable"><tr><td colspan="5" class="empty-state">Carregando…</td></tr></tbody></table></div></template>

  <template id="view-admin-stats" data-label="Relatórios e estatísticas"><section class="view-heading"><div><div class="eyebrow">VISÃO AGREGADA</div><h1>Relatórios e estatísticas</h1><p>Indicadores agregados. O PDF abre a impressão do navegador; escolha “Salvar como PDF”. Exportações não incluem relatos.</p></div><div class="export-actions"><button class="primary" id="exportStatsPdf" type="button">Exportar PDF</button><button class="secondary" id="exportStats" type="button">Exportar CSV</button></div></section><div class="stat-grid"><div class="stat-card"><small>Beneficiárias cadastradas</small><strong id="statsBeneficiaries">—</strong><em>Cadastros ativos e históricos</em></div><div class="stat-card"><small>Solicitações em andamento</small><strong id="statsActive">—</strong><em>Recebidas ou em triagem</em></div><div class="stat-card"><small>Denúncias recebidas</small><strong id="statsReports">—</strong><em>Total registrado</em></div><div class="stat-card"><small>Encaminhadas</small><strong id="statsForwarded">—</strong><em>Não equivale a caso resolvido</em></div></div><div class="two-col"><div class="panel"><div class="panel-title"><h3>Denúncias por status</h3></div><div id="statsStatus" class="metric-list"></div></div><div class="panel"><div class="panel-title"><h3>Categorias mais registradas</h3></div><div id="statsCategory" class="metric-list"></div></div></div></template>

  <template id="view-admin-notificacoes" data-label="Notificações administrativas"><section class="view-heading"><div><div class="eyebrow">CAIXA DA EQUIPE</div><h1>Notificações administrativas</h1><p>Avisos do sistema e eventos que podem exigir atenção.</p></div></section><div class="privacy-strip"><b>Privacidade</b><span>Notificações não substituem a triagem nem garantem monitoramento em tempo real.</span></div><div class="panel"><div id="adminNotifications" class="notice-list"><div class="empty-state">Carregando…</div></div></div></template>

  <template id="view-admin-seguranca" data-label="Segurança e auditoria"><section class="view-heading"><div><div class="eyebrow">CONTROLE E RASTREABILIDADE</div><h1>Segurança e auditoria</h1><p>Revise acessos e ações administrativas. Dados detalhados disponíveis somente para administradoras.</p></div><span class="safe-badge">♢ Registro protegido</span></section><div class="security-cards"><article class="security-card"><span>01</span><div><b>Acesso por função</b><p>Consultas de relatos e dados pessoais devem ficar limitadas às pessoas autorizadas.</p></div></article><article class="security-card"><span>02</span><div><b>Trilha de auditoria</b><p>Consultas a detalhes sensíveis e mudanças de status são registradas com autor e horário.</p></div></article><article class="security-card"><span>03</span><div><b>Desativação reversível</b><p>Contas de equipe podem ser desativadas sem apagar o histórico de ações.</p></div></article></div><div class="panel-title audit-heading"><h3>Equipe com acesso</h3><button class="primary" id="inviteStaff" type="button">+ Convidar pessoa</button></div><div class="panel table-wrap"><table class="data-table"><thead><tr><th>Nome</th><th>Função</th><th>Status</th><th>Desde</th><th>Ação</th></tr></thead><tbody id="staffTable"><tr><td colspan="5" class="empty-state">Carregando…</td></tr></tbody></table></div><div class="panel-title audit-heading"><h3>Log de atividades recentes</h3><span class="muted-note">Somente perfil administrador</span></div><div class="panel table-wrap"><table class="data-table"><thead><tr><th>Data e hora</th><th>Responsável</th><th>Ação</th><th>Registro</th><th>Detalhe</th></tr></thead><tbody id="auditTable"><tr><td colspan="5" class="empty-state">Carregando…</td></tr></tbody></table></div></template>

  <template id="view-chat" data-label="Chat de apoio">
    <section class="view-heading">
      <div><div class="eyebrow">ACOLHIMENTO</div><h1>Chat de apoio</h1><p>Converse com o assistente virtual do Lumen.</p></div>
    </section>
    <section class="panel chat-panel" aria-label="Conversa com o assistente virtual do Lumen">
      <div class="chat-privacy-note">
        <strong>Antes de começar</strong>
        <p>Suas mensagens serão processadas por um serviço externo de inteligência artificial. Evite compartilhar informações que identifiquem você. Este chat não substitui atendimento especializado.</p>
      </div>
      <div class="chat-messages" id="chatMessages" role="log" aria-live="polite" aria-relevant="additions">
        <div class="chat-message chat-message-assistant" data-role="assistant">
          <span class="chat-message-author">Assistente Lumen</span>
          <p class="chat-message-body">Olá. Estou aqui para ouvir você e ajudar com informações. Como posso apoiar você hoje?</p>
        </div>
      </div>
      <form class="chat-form" id="chatForm">
        <label class="chat-visually-hidden" for="chatInput">Digite sua mensagem para o assistente Lumen</label>
        <textarea id="chatInput" name="message" rows="2" maxlength="4000" required placeholder="Escreva sua mensagem..."></textarea>
        <div class="chat-form-footer">
          <p>Se estiver em perigo imediato, procure um local seguro se puder e ligue 190. A Central de Atendimento à Mulher atende pelo 180.</p>
          <button class="primary chat-send-button" type="submit">Enviar <span>→</span></button>
        </div>
      </form>
    </section>
  </template>
  <div class="toast" id="toast"></div>
  <div class="sos-modal" id="sosModal"><div class="modal-card sos-card"><button class="close-modal" id="closeSos" type="button">×</button><div class="sos-icon">SOS</div><h2>Você precisa de ajuda agora?</h2><p>Ao confirmar, seus contatos de emergência receberão um aviso por e-mail e WhatsApp. A equipe Lumen também será notificada.</p><button class="primary danger" id="confirmSos" type="button">Confirmar pedido de ajuda</button><button class="text-button" id="cancelSos" type="button">Cancelar</button></div></div>

  <div class="modal-backdrop" id="staffModal"><div class="modal-card form-modal"><button class="close-modal" data-close-modal="staffModal" type="button">×</button><div class="eyebrow">ACESSO DA EQUIPE</div><h2>Convidar profissional</h2><p class="helper-text">A conta criada terá acesso de equipe. Compartilhe a senha inicial por canal seguro.</p><form id="staffForm"><div class="field"><label>Nome completo</label><input name="name" required maxlength="160"></div><div class="field"><label>E-mail profissional</label><input name="email" type="email" required></div><div class="field"><label>Função</label><select name="role"><option value="supporter">Apoiador</option><option value="admin">Administradora</option></select></div><div class="field"><label>Senha temporária (mínimo 12 caracteres)</label><input name="password" type="password" minlength="12" required></div><div class="form-actions"><button type="button" class="secondary" data-close-modal="staffModal">Cancelar</button><button class="primary" type="submit">Criar acesso</button></div></form></div></div>
  <div class="modal-backdrop" id="registerModal"><div class="modal-card form-modal"><button class="close-modal" data-close-modal="registerModal" type="button">×</button><div class="eyebrow">NOVO CADASTRO</div><h2>Criar sua conta</h2><p class="helper-text">Preencha seus dados para acessar o Lumen.</p><form id="registerForm"><div class="inline-fields"><div class="field"><label>Nome e sobrenome</label><input name="name" required></div><div class="field"><label>CPF</label><input name="cpf" required></div></div><div class="inline-fields"><div class="field"><label>RG</label><input name="rg" required></div><div class="field"><label>CEP</label><input name="cep" required></div></div><div class="field"><label>Endereço</label><input name="address" required></div><div class="field"><label>Telefone / WhatsApp</label><input name="phone" required></div><div class="field"><label>E-mail</label><input name="email" type="email" required></div><div class="field"><label>Confirme seu e-mail</label><input name="email_confirmation" type="email" required></div><div class="inline-fields"><div class="field"><label>Senha</label><input id="registerPassword" name="password" type="password" minlength="8" required></div><div class="field"><label>Confirme sua senha</label><input name="password_confirmation" type="password" minlength="8" required></div></div><div class="field"><label>Tipo de conta</label><select name="role" id="registerRole"><option value="beneficiary">Beneficiária</option><option value="supporter">Apoiador</option></select></div><div class="form-actions"><button type="button" class="secondary" data-close-modal="registerModal">Cancelar</button><button class="primary" type="submit">Criar conta <span>→</span></button></div></form></div></div>
  <div class="modal-backdrop" id="forgotModal"><div class="modal-card form-modal"><button class="close-modal" data-close-modal="forgotModal" type="button">×</button><div class="eyebrow">RECUPERAÇÃO DE ACESSO</div><h2>Esqueci minha senha</h2><p class="helper-text">Enviaremos um link de recuperação para o seu e-mail cadastrado.</p><form id="forgotForm"><div class="field"><label>E-mail</label><input name="email" type="email" required placeholder="seuemail@exemplo.com"></div><div class="form-actions"><button type="button" class="secondary" data-close-modal="forgotModal">Cancelar</button><button class="primary" type="submit">Enviar link</button></div></form></div></div>
  <div class="modal-backdrop" id="helpModal"><div class="modal-card form-modal"><button class="close-modal" data-close-modal="helpModal" type="button">×</button><div class="eyebrow">ESTAMOS AQUI</div><h2>Central de ajuda</h2><p>Se você estiver em perigo imediato, procure um serviço de emergência da sua região. No Lumen, você pode registrar uma denúncia, solicitar atendimento ou acionar o SOS.</p><p class="helper-text">Privacidade: seus dados são usados somente para oferecer acolhimento, encaminhamento e comunicação sobre seus pedidos.</p><div class="form-actions"><button class="primary" type="button" data-close-modal="helpModal">Entendi</button></div></div></div>
  <div class="modal-backdrop case-modal" id="caseModal"><div class="modal-card form-modal"><button class="close-modal" data-close-modal="caseModal" type="button">×</button><div id="caseContent"></div></div></div>
  <div class="modal-backdrop" id="serviceModal"><div class="modal-card form-modal"><button class="close-modal" data-close-modal="serviceModal" type="button">×</button><div class="eyebrow">REDE DE APOIO</div><h2>Adicionar serviço</h2><form id="serviceForm"><div class="field"><label>Nome do serviço</label><input name="name" required></div><div class="inline-fields"><div class="field"><label>Categoria</label><input name="category" required placeholder="Saúde, jurídico..."></div><div class="field"><label>Cidade / UF</label><input name="city" required></div></div><div class="inline-fields"><div class="field"><label>Telefone</label><input name="phone"></div><div class="field"><label>Endereço</label><input name="address"></div></div><div class="form-actions"><button type="button" class="secondary" data-close-modal="serviceModal">Cancelar</button><button class="primary" type="submit">Salvar serviço</button></div></form></div></div>

  <template id="view-supporter-verificacao" data-label="Verificação do cadastro">
    <section class="view-heading">
      <div><div class="eyebrow">CADASTRO EM ANÁLISE</div><h1>Verificação do cadastro</h1><p>Para proteger as beneficiárias, os dados profissionais passam por verificação antes do primeiro atendimento.</p></div>
      <span id="supVerificationBadge" class="status yellow">Em análise</span>
    </section>
    <div class="state-banner" id="supVerificationBanner"><strong>O que falta agora</strong><p>Carregando pendências…</p></div>
    <div class="two-col">
      <div class="panel"><div class="panel-title"><h3>Checklist do cadastro</h3></div><div id="supVerificationChecklist" class="metric-list"><div class="empty-state">Carregando…</div></div></div>
      <div class="panel"><div class="panel-title"><h3>Documentos exigidos para o seu perfil</h3></div><div id="supVerificationDocuments" class="notice-list"><div class="empty-state">Carregando…</div></div><button class="primary" data-view="supporter-documentos" type="button">Enviar documentos</button></div>
    </div>
    <div class="privacy-strip"><b>Privacidade</b><span>Enquanto a verificação não for concluída, você não recebe solicitações nem acessa dados de beneficiárias. Nenhum dado pessoal é exibido sem autorização de atendimento.</span></div>
    <div class="panel"><div class="panel-title"><h3>Como funciona a análise</h3></div>
      <div class="security-cards">
        <article class="security-card"><span>01</span><div><b>Envio dos documentos</b><p>Você envia identidade, comprovante de endereço e, conforme o perfil, registro profissional ou CNPJ.</p></div></article>
        <article class="security-card"><span>02</span><div><b>Conferência pela equipe</b><p>A equipe Lumen confere os arquivos e pode pedir correções antes de aprovar.</p></div></article>
        <article class="security-card"><span>03</span><div><b>Atendimento liberado</b><p>Aprovado o cadastro, você passa a ver as solicitações compatíveis com suas especialidades.</p></div></article>
      </div>
    </div>
  </template>
  <template id="view-supporter-dashboard" data-label="Dashboard">
    <section class="view-heading">
      <div><div class="eyebrow">PAINEL DO APOIADOR</div><h1>Olá, <span data-user-first><?= e(explode(' ', $userName)[0]) ?></span>!</h1><p id="supDashboardSubtitle">Resumo dos seus atendimentos e do que precisa de atenção hoje.</p></div>
      <button class="primary" data-view="supporter-solicitacoes" type="button">Ver solicitações recebidas</button>
    </section>
    <div id="supStateBanner" class="state-banner hidden"></div>
    <div class="stat-grid">
      <div class="stat-card"><span class="stat-icon">▤</span><small>Solicitações disponíveis</small><strong id="supAvailable">0</strong><em>Compatíveis com seu perfil</em></div>
      <div class="stat-card"><span class="stat-icon">♡</span><small>Atendimentos em andamento</small><strong id="supActive">0</strong><em>Você é o responsável</em></div>
      <div class="stat-card"><span class="stat-icon">◷</span><small>Próximos agendamentos</small><strong id="supUpcoming">0</strong><em>Agenda confirmada</em></div>
      <div class="stat-card"><span class="stat-icon">♧</span><small>Mensagens não lidas</small><strong id="supUnread">0</strong><em>Aguardando resposta</em></div>
    </div>
    <div class="panel followup-panel"><div class="panel-title"><div><div class="eyebrow">ACOMPANHAMENTO PROFISSIONAL</div><h3>Ritmo dos atendimentos</h3></div><span class="muted-note">Últimos 6 meses</span></div><div class="followup-chart" aria-label="Gráfico de acompanhamento dos atendimentos"><div class="chart-bars supporter-bars"><span style="height:34%" title="Maio"></span><span style="height:48%" title="Junho"></span><span style="height:42%" title="Julho"></span><span style="height:68%" title="Agosto"></span><span style="height:58%" title="Setembro"></span><span style="height:86%" title="Outubro"></span></div><div class="chart-labels"><span>Mai</span><span>Jun</span><span>Jul</span><span>Ago</span><span>Set</span><span>Out</span></div></div><div class="legend"><span>Atendimentos realizados</span><span class="legend-green">Solicitações aceitas</span></div></div>
    <div class="two-col">
      <div class="panel"><div class="panel-title"><h3>Próximos atendimentos</h3><button class="table-action" data-view="supporter-agenda" type="button">Ver agenda</button></div><div id="supNextAppointments" class="request-list"><div class="empty-state">Carregando…</div></div></div>
      <div class="panel"><div class="panel-title"><h3>Solicitações recentes</h3><button class="table-action" data-view="supporter-solicitacoes" type="button">Ver todas</button></div><div id="supLatestRequests" class="request-list"><div class="empty-state">Carregando…</div></div></div>
    </div>
    <div class="two-col">
      <div class="panel"><div class="panel-title"><h3>Últimas mensagens</h3><button class="table-action" data-view="supporter-mensagens" type="button">Abrir mensagens</button></div><div id="supRecentMessages" class="notice-list"><div class="empty-state">Carregando…</div></div></div>
      <div class="panel"><div class="panel-title"><h3>Notificações</h3><button class="table-action" data-view="supporter-notificacoes" type="button">Ver todas</button></div><div id="supNotifications" class="notice-list"><div class="empty-state">Carregando…</div></div></div>
    </div>
  </template>
  <template id="view-supporter-solicitacoes" data-label="Solicitações recebidas">
    <section class="view-heading">
      <div><div class="eyebrow">SOLICITAÇÕES DE AJUDA</div><h1>Solicitações recebidas</h1><p>Pedidos de apoio aguardando um responsável. Você vê apenas o necessário para decidir: nome abreviado, região e resumo do relato.</p></div>
      <span class="safe-badge">🔒 Dados completos só após aceitar</span>
    </section>
    <div class="privacy-strip"><b>Uso responsável</b><span>Cada consulta a uma solicitação é registrada na trilha de auditoria. Aceite apenas o que puder acompanhar.</span></div>
    <div class="admin-filters">
      <input id="supRequestSearch" placeholder="⌕ Buscar por categoria ou região">
      <label class="filter-check"><input type="checkbox" id="supRequestOnlyMatches"> Somente compatíveis com minhas especialidades</label>
    </div>
    <div class="panel table-wrap">
      <table class="data-table table-wide"><thead><tr><th>Categoria</th><th>Região</th><th>Recebida em</th><th>Compatibilidade</th><th>Ação</th></tr></thead>
      <tbody id="supRequestsTable"><tr><td colspan="5" class="empty-state">Carregando solicitações…</td></tr></tbody></table>
    </div>
    <details class="panel details-panel"><summary>Solicitações que você recusou</summary><div id="supDeclinedList" class="request-list"><div class="empty-state">Nenhuma recusa registrada.</div></div></details>
  </template>
  <template id="view-supporter-atendimentos" data-label="Atendimentos">
    <section class="view-heading">
      <div><div class="eyebrow">ACOMPANHAMENTO</div><h1>Meus atendimentos</h1><p>Acompanhe os casos sob sua responsabilidade e consulte o histórico dos já encerrados.</p></div>
    </section>
    <div class="admin-filters scope-tabs">
      <button class="secondary active" data-sup-scope="active" type="button">Em andamento</button>
      <button class="secondary" data-sup-scope="history" type="button">Histórico</button>
    </div>
    <div class="panel table-wrap">
      <table class="data-table table-wide"><thead><tr><th>Beneficiária</th><th>Tipo de apoio</th><th>Aceito em</th><th>Próximo atendimento</th><th>Status</th><th>Ação</th></tr></thead>
      <tbody id="supAssignmentsTable"><tr><td colspan="6" class="empty-state">Carregando…</td></tr></tbody></table>
    </div>
  </template>
  <template id="view-supporter-agenda" data-label="Agenda">
    <section class="view-heading">
      <div><div class="eyebrow">ORGANIZE SEU CUIDADO</div><h1>Agenda</h1><p>Agende primeiros atendimentos, retornos e acompanhamentos respeitando seus horários disponíveis.</p></div>
      <button class="primary" id="supNewAppointment" type="button">+ Agendar atendimento</button>
    </section>
    <div class="two-col">
      <div class="panel"><div class="panel-title"><h3>Próximos atendimentos</h3></div><div id="supAgendaUpcoming" class="request-list"><div class="empty-state">Carregando…</div></div></div>
      <div class="panel"><div class="panel-title"><h3>Atendimentos anteriores</h3></div><div id="supAgendaPast" class="request-list"><div class="empty-state">Carregando…</div></div></div>
    </div>
    <div class="two-col">
      <div class="panel"><div class="panel-title"><h3>Bloqueios de agenda</h3></div><div id="supTimeOffList" class="request-list"><div class="empty-state">Nenhum bloqueio registrado.</div></div></div>
      <form class="panel form-card" id="supTimeOffForm">
        <div class="form-section-title"><span>◷</span><div><h3>Bloquear um período</h3><p>Use para férias, folgas ou compromissos pessoais.</p></div></div>
        <div class="inline-fields">
          <div class="field"><label for="supTimeOffDate">Data</label><input id="supTimeOffDate" name="off_date" type="date" required></div>
          <div class="field"><label for="supTimeOffStart">Início (opcional)</label><input id="supTimeOffStart" name="start_time" type="time"></div>
          <div class="field"><label for="supTimeOffEnd">Fim (opcional)</label><input id="supTimeOffEnd" name="end_time" type="time"></div>
        </div>
        <div class="field"><label for="supTimeOffReason">Motivo</label><input id="supTimeOffReason" name="reason" maxlength="190" placeholder="Ex.: férias, formação, compromisso pessoal"></div>
        <div class="form-actions"><button class="secondary" type="submit">Registrar bloqueio</button></div>
      </form>
    </div>
  </template>
  <template id="view-supporter-mensagens" data-label="Mensagens">
    <section class="view-heading">
      <div><div class="eyebrow">COMUNICAÇÃO E ATENDIMENTO</div><h1>Mensagens</h1><p>Converse com as beneficiárias dos atendimentos que você aceitou. O histórico fica registrado no caso.</p></div>
    </section>
    <div class="chat-layout">
      <div class="panel chat-sidebar">
        <div class="panel-title"><h3>Conversas</h3></div>
        <div data-conversation-list class="request-list"><div class="empty-state">Carregando conversas…</div></div>
      </div>
      <div class="panel chat-panel">
        <div class="chat-header"><div><strong data-conversation-title>Selecione uma conversa</strong><small data-conversation-meta></small></div></div>
        <div data-conversation-appointments class="appointment-strip"></div>
        <div class="chat-messages" data-conversation-thread role="log" aria-live="polite"><div class="empty-state">Escolha uma conversa ao lado para ver as mensagens.</div></div>
        <form class="chat-form" data-message-form>
          <label class="chat-visually-hidden" for="supMessageInput">Escreva sua mensagem</label>
          <textarea id="supMessageInput" data-message-input name="message" rows="2" maxlength="4000" placeholder="Escreva uma mensagem para a beneficiária..."></textarea>
          <div class="chat-form-footer"><p>Não compartilhe dados de outras pessoas. Combine sempre o atendimento por aqui.</p><button class="primary chat-send-button" type="submit">Enviar <span>→</span></button></div>
        </form>
      </div>
    </div>
  </template>
  <template id="view-mensagens" data-label="Mensagens">
    <section class="view-heading">
      <div><div class="eyebrow">CONVERSE COM QUEM CUIDA DE VOCÊ</div><h1>Mensagens</h1><p>Fale com o apoiador responsável pelo seu atendimento. Você pode combinar horários e tirar dúvidas por aqui.</p></div>
    </section>
    <div class="chat-layout">
      <div class="panel chat-sidebar">
        <div class="panel-title"><h3>Conversas</h3></div>
        <div data-conversation-list class="request-list"><div class="empty-state">Carregando conversas…</div></div>
      </div>
      <div class="panel chat-panel">
        <div class="chat-header"><div><strong data-conversation-title>Selecione uma conversa</strong><small data-conversation-meta></small></div></div>
        <div data-conversation-appointments class="appointment-strip"></div>
        <div class="chat-messages" data-conversation-thread role="log" aria-live="polite"><div class="empty-state">Escolha uma conversa ao lado para ver as mensagens.</div></div>
        <form class="chat-form" data-message-form>
          <label class="chat-visually-hidden" for="benMessageInput">Escreva sua mensagem</label>
          <textarea id="benMessageInput" data-message-input name="message" rows="2" maxlength="4000" placeholder="Escreva sua mensagem..."></textarea>
          <div class="chat-form-footer"><p>Se estiver em perigo imediato, procure um local seguro se puder e ligue 190.</p><button class="primary chat-send-button" type="submit">Enviar <span>→</span></button></div>
        </form>
      </div>
    </div>
  </template>
  <template id="view-supporter-servicos" data-label="Serviços oferecidos">
    <section class="view-heading">
      <div><div class="eyebrow">VITRINE DA REDE</div><h1>Serviços oferecidos</h1><p>Cadastre o que você oferece para que a rede Lumen saiba como encaminhar pedidos compatíveis.</p></div>
      <button class="primary" id="supNewService" type="button">+ Novo serviço</button>
    </section>
    <div class="panel table-wrap">
      <table class="data-table table-wide"><thead><tr><th>Serviço</th><th>Categoria</th><th>Modalidade</th><th>Duração</th><th>Condição</th><th>Status</th><th>Ação</th></tr></thead>
      <tbody id="supServicesTable"><tr><td colspan="7" class="empty-state">Carregando serviços…</td></tr></tbody></table>
    </div>
    <div class="privacy-strip"><b>Transparência</b><span>Informe com clareza se o serviço é gratuito, social ou pago. Beneficiárias do Lumen têm prioridade em serviços gratuitos.</span></div>
  </template>
  <template id="view-supporter-perfil" data-label="Meu perfil">
    <section class="view-heading">
      <div><div class="eyebrow">PERFIL PROFISSIONAL</div><h1>Meu perfil</h1><p>Mantenha seus dados atualizados. Alterações importantes ficam registradas para auditoria.</p></div>
      <span id="supProfileBadge" class="status yellow">Em análise</span>
    </section>
    <form class="panel form-card" id="supporterProfileForm">
      <div class="form-section-title"><span>01</span><div><h3>Tipo de perfil e dados de acesso</h3><p>Cada tipo tem campos e permissões próprios dentro da mesma área.</p></div></div>
      <div class="field"><label for="supProfileType">Tipo de perfil</label>
        <select id="supProfileType" name="supporter_type">
          <option value="professional">Profissional autônomo</option>
          <option value="institution">Instituição / ONG</option>
        </select>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileName">Nome completo / responsável</label><input id="supProfileName" name="name" maxlength="160" required></div>
        <div class="field"><label for="supProfileDisplay">Nome de exibição para a rede</label><input id="supProfileDisplay" name="display_name" maxlength="160" placeholder="Ex.: Dra. Marina Prado — Psicologia"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileEmail">E-mail</label><input id="supProfileEmail" name="email" type="email" required></div>
        <div class="field"><label for="supProfilePhone">Telefone</label><input id="supProfilePhone" name="phone" maxlength="30"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileAddress">Endereço</label><input id="supProfileAddress" name="address" maxlength="255"></div>
        <div class="field"><label for="supProfileCep">CEP</label><input id="supProfileCep" name="cep" maxlength="12"></div>
      </div>
      <div class="form-section-title"><span>02</span><div><h3>Dados profissionais</h3><p>Os campos mudam conforme o tipo de perfil escolhido.</p></div></div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileDocument">CPF ou CNPJ</label><input id="supProfileDocument" name="document_number" maxlength="40"></div>
        <div class="field"><label for="supProfileCouncilName">Conselho profissional</label><input id="supProfileCouncilName" name="council_name" maxlength="60" placeholder="CRP, CRM, OAB, CREF..."></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileCouncilNumber">Número de registro</label><input id="supProfileCouncilNumber" name="council_number" maxlength="40" placeholder="06/123456"></div>
        <div class="field"><label for="supProfileCouncilState">UF do registro</label><input id="supProfileCouncilState" name="council_state" maxlength="2" placeholder="SP"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileLegalName">Razão social (instituição)</label><input id="supProfileLegalName" name="legal_name" maxlength="190"></div>
        <div class="field"><label for="supProfileResponsible">Pessoa responsável (instituição)</label><input id="supProfileResponsible" name="responsible_name" maxlength="160"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileTeamSize">Equipe envolvida</label><input id="supProfileTeamSize" name="team_size" type="number" min="1" max="5000" value="1"></div>
        <div class="field"><label for="supProfileMaxCases">Atendimentos simultâneos</label><input id="supProfileMaxCases" name="max_active_cases" type="number" min="1" max="50" value="5"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileCity">Cidade de atendimento</label><input id="supProfileCity" name="city" maxlength="120"></div>
        <div class="field"><label for="supProfileState">UF</label><input id="supProfileState" name="state" maxlength="2" placeholder="SP"></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supProfileMode">Modalidade de atendimento</label>
          <select id="supProfileMode" name="service_mode"><option value="online">Online</option><option value="presencial">Presencial</option><option value="hibrido">Híbrido</option></select>
        </div>
        <div class="field"><label for="supProfileLanguages">Idiomas</label><input id="supProfileLanguages" name="languages" maxlength="160" placeholder="Português, Libras, Espanhol"></div>
      </div>
      <div class="field"><label for="supProfileApproach">Abordagem de atendimento</label><input id="supProfileApproach" name="approach" maxlength="255" placeholder="Como você acolhe e conduz o atendimento"></div>
      <div class="field"><label for="supProfileBio">Apresentação</label><textarea id="supProfileBio" name="bio" rows="4" placeholder="Experiência, formação e frentes de atuação."></textarea></div>
      <div class="form-actions"><button class="primary" type="submit">Salvar dados do perfil</button></div>
    </form>
    <div class="two-col">
      <div class="panel">
        <div class="panel-title"><h3>Especialidades</h3></div>
        <div id="supSpecialties" class="metric-list"><div class="empty-state">Nenhuma especialidade cadastrada.</div></div>
        <form id="supSpecialtyForm" class="inline-form">
          <input type="hidden" name="id" value="">
          <div class="field"><label>Especialidade</label><input name="name" maxlength="120" required placeholder="Ex.: Violência doméstica e familiar"></div>
          <div class="field"><label>Observação</label><input name="notes" maxlength="190" placeholder="Opcional"></div>
          <button class="secondary" type="submit">Adicionar especialidade</button>
        </form>
      </div>
      <div class="panel">
        <div class="panel-title"><h3>Disponibilidade e horários</h3></div>
        <div id="supAvailability" class="metric-list"><div class="empty-state">Nenhum horário cadastrado.</div></div>
        <form id="supAvailabilityForm" class="inline-form">
          <input type="hidden" name="id" value="">
          <div class="inline-fields">
            <div class="field"><label>Dia da semana</label><select name="weekday" required><option value="0">Domingo</option><option value="1">Segunda-feira</option><option value="2">Terça-feira</option><option value="3">Quarta-feira</option><option value="4">Quinta-feira</option><option value="5">Sexta-feira</option><option value="6">Sábado</option></select></div>
            <div class="field"><label>Modalidade</label><select name="mode"><option value="online">Online</option><option value="presencial">Presencial</option><option value="hibrido">Híbrido</option></select></div>
          </div>
          <div class="inline-fields">
            <div class="field"><label>Início</label><input name="start_time" type="time" required></div>
            <div class="field"><label>Fim</label><input name="end_time" type="time" required></div>
            <div class="field"><label>Duração (min)</label><input name="slot_minutes" type="number" min="15" max="240" step="15" value="60"></div>
          </div>
          <button class="secondary" type="submit">Adicionar horário</button>
        </form>
      </div>
    </div>
    <div class="panel">
      <div class="panel-title"><h3>Locais de atendimento</h3></div>
      <div id="supLocations" class="request-list"><div class="empty-state">Nenhum local cadastrado.</div></div>
      <form id="supLocationForm" class="inline-form">
        <input type="hidden" name="id" value="">
        <div class="inline-fields">
          <div class="field"><label>Nome do local</label><input name="label" maxlength="120" required placeholder="Ex.: Consultório — Vila Mariana"></div>
          <div class="field"><label>Endereço</label><input name="address" maxlength="255"></div>
        </div>
        <div class="inline-fields">
          <div class="field"><label>Cidade</label><input name="city" maxlength="120"></div>
          <div class="field"><label>UF</label><input name="state" maxlength="2"></div>
          <div class="field"><label>CEP</label><input name="cep" maxlength="12"></div>
        </div>
        <div class="field"><label>Observações de acesso</label><input name="notes" maxlength="190" placeholder="Ex.: recepção com espera reservada"></div>
        <label class="filter-check"><input type="checkbox" name="is_primary" value="1"> Definir como local principal</label>
        <div class="form-actions"><button class="secondary" type="submit">Adicionar local</button></div>
      </form>
    </div>
  </template>
  <template id="view-supporter-documentos" data-label="Documentos e verificação">
    <section class="view-heading">
      <div><div class="eyebrow">DOCUMENTOS E VERIFICAÇÃO</div><h1>Documentos e verificação</h1><p>Envie os documentos exigidos para o seu tipo de perfil. Arquivos aceitos: PDF, JPG ou PNG até 10 MB.</p></div>
      <span id="supDocumentBadge" class="status yellow">Em análise</span>
    </section>
    <div class="privacy-strip"><b>Acesso restrito</b><span>Somente a equipe responsável pela verificação acessa os arquivos enviados, e cada consulta é registrada.</span></div>
    <div class="two-col">
      <div class="panel">
        <div class="panel-title"><h3>Meus documentos</h3></div>
        <div id="supDocumentsList" class="notice-list"><div class="empty-state">Nenhum documento enviado.</div></div>
      </div>
      <form class="panel form-card" id="supDocumentForm" enctype="multipart/form-data">
        <div class="form-section-title"><span>▧</span><div><h3>Enviar documento</h3><p>Prefira arquivos legíveis e sem cortes.</p></div></div>
        <div class="field"><label for="supDocumentKind">Tipo de documento</label><select id="supDocumentKind" name="kind" required></select></div>
        <div class="field"><label for="supDocumentFile">Arquivo</label><input id="supDocumentFile" name="file" type="file" accept="application/pdf,image/jpeg,image/png" required></div>
        <div class="form-actions"><button class="primary" type="submit">Enviar para verificação</button></div>
      </form>
    </div>
  </template>
  <template id="view-supporter-seguranca" data-label="Segurança e orientações">
    <section class="view-heading">
      <div><div class="eyebrow">SEGURANÇA E SUPORTE</div><h1>Segurança e orientações</h1><p>Proteja seu acesso, siga as orientações de atendimento seguro e reporte qualquer situação de risco ou abuso.</p></div>
      <span class="safe-badge">♢ Ambiente protegido</span>
    </section>
    <div class="security-cards">
      <article class="security-card"><span>01</span><div><b>Minimize dados</b><p>Acesse somente as informações necessárias do atendimento autorizado. Não copie relatos para fora do Lumen.</p></div></article>
      <article class="security-card"><span>02</span><div><b>Combine pelo chat</b><p>Agende e converse pela plataforma. Assim o histórico fica registrado e protegido para as duas partes.</p></div></article>
      <article class="security-card"><span>03</span><div><b>Registre ocorrências</b><p>Situações de risco, assédio ou uso indevido devem ser reportadas imediatamente à equipe Lumen.</p></div></article>
    </div>
    <div class="two-col">
      <form class="panel form-card" id="supPasswordForm">
        <div class="form-section-title"><span>⚙</span><div><h3>Alterar senha</h3><p>Use pelo menos 10 caracteres e não reutilize senhas de outros serviços.</p></div></div>
        <div class="field"><label for="supCurrentPassword">Senha atual</label><input id="supCurrentPassword" name="current_password" type="password" required autocomplete="current-password"></div>
        <div class="field"><label for="supNewPassword">Nova senha</label><input id="supNewPassword" name="new_password" type="password" required autocomplete="new-password"></div>
        <div class="field"><label for="supNewPasswordConfirm">Confirmar nova senha</label><input id="supNewPasswordConfirm" name="new_password_confirmation" type="password" required autocomplete="new-password"></div>
        <div class="form-actions"><button class="primary" type="submit">Alterar senha</button></div>
      </form>
      <div class="panel">
        <div class="panel-title"><h3>Sessões e acessos</h3></div>
        <div id="supSecuritySummary" class="metric-list"><div class="empty-state">Carregando…</div></div>
        <button class="secondary" id="supRevokeRemember" type="button">Revogar dispositivos lembrados</button>
      </div>
    </div>
    <div class="panel">
      <div class="panel-title"><h3>Histórico de acessos e alterações</h3><span class="muted-note">Registrado automaticamente</span></div>
      <div id="supAccessLog" class="metric-list"><div class="empty-state">Carregando…</div></div>
    </div>
    <div class="two-col">
      <form class="panel form-card" id="supRiskForm">
        <div class="form-section-title"><span>!</span><div><h3>Reportar risco ou abuso</h3><p>Canal sigiloso para situações de risco à beneficiária, à equipe, assédio, fraude ou uso indevido.</p></div></div>
        <div class="field"><label for="supRiskCategory">Tipo de situação</label>
          <select id="supRiskCategory" name="category" required>
            <option value="risco_beneficiaria">Risco à beneficiária</option>
            <option value="risco_equipe">Risco à equipe ou ao apoiador</option>
            <option value="assedio">Assédio ou conduta inadequada</option>
            <option value="fraude">Fraude ou uso indevido da plataforma</option>
            <option value="outro">Outra situação</option>
          </select>
        </div>
        <div class="inline-fields">
          <div class="field"><label for="supRiskUrgency">Prioridade</label>
            <select id="supRiskUrgency" name="urgency"><option value="imediata">Precisa de ação imediata</option><option value="attention" selected>Atenção</option><option value="informativa">Informativo</option></select>
          </div>
          <div class="field"><label for="supRiskAssignment">Atendimento relacionado</label><select id="supRiskAssignment" name="assignment_id"><option value="0">Não se aplica</option></select></div>
        </div>
        <div class="field"><label for="supRiskDescription">Descrição da situação</label><textarea id="supRiskDescription" name="description" rows="4" required placeholder="Descreva o que aconteceu, quando e quem está envolvido."></textarea></div>
        <div class="form-actions"><button class="primary danger" type="submit">Enviar relato</button></div>
      </form>
      <div class="panel">
        <div class="panel-title"><h3>Meus relatos</h3></div>
        <div id="supRiskList" class="notice-list"><div class="empty-state">Nenhum relato enviado.</div></div>
      </div>
    </div>
  </template>
  <template id="view-ajuda" data-label="Central de ajuda">
    <section class="view-heading">
      <div><div class="eyebrow">CENTRAL DE AJUDA</div><h1>Central de ajuda</h1><p>Dúvidas frequentes sobre cadastro, verificação, atendimento e proteção de dados.</p></div>
    </section>
    <div class="panel faq-list">
      <details><summary>Como funciona a verificação do meu cadastro?</summary><p>A equipe Lumen confere os documentos enviados e pode aprovar, pedir correções ou recusar com justificativa. Você só recebe solicitações depois da aprovação.</p></details>
      <details><summary>Quais dados das beneficiárias eu posso ver?</summary><p>Antes de aceitar, você vê apenas nome abreviado, região e resumo do relato. Depois de aceitar, passam a aparecer as informações necessárias para o atendimento, como nome completo e contato. Todo acesso é registrado na auditoria.</p></details>
      <details><summary>Posso recusar uma solicitação?</summary><p>Sim. Registre brevemente o motivo. A solicitação volta para a rede e continua disponível para outros apoiadores.</p></details>
      <details><summary>Como encerro um atendimento?</summary><p>No atendimento, use "Encerrar atendimento" e descreva o resultado. O histórico permanece disponível para consulta e o caso é marcado como concluído.</p></details>
      <details><summary>O que fazer se a beneficiária estiver em risco imediato?</summary><p>Oriente a procurar um local seguro se for possível e acionar emergência: Polícia Militar 190, Central de Atendimento à Mulher 180, Disque Direitos Humanos 100. Depois, registre o ocorrido no canal "Reportar risco ou abuso".</p></details>
      <details><summary>Meus documentos ficam visíveis para as beneficiárias?</summary><p>Não. Os arquivos de verificação são acessados apenas pela equipe responsável, com registro de auditoria.</p></details>
      <details><summary>Como atualizo meus dados?</summary><p>Acesse Meu perfil para atualizar seus dados pessoais e profissionais. Alterações importantes podem exigir nova verificação pela equipe Lumen.</p></details>
    </div>
    <div class="two-col">
      <div class="panel"><div class="panel-title"><h3>Canais de emergência</h3></div>
        <div class="metric-list">
          <div class="metric-row"><span>Polícia Militar</span><b>190</b></div>
          <div class="metric-row"><span>Central de Atendimento à Mulher</span><b>180</b></div>
          <div class="metric-row"><span>Disque Direitos Humanos</span><b>100</b></div>
          <div class="metric-row"><span>SAMU</span><b>192</b></div>
        </div>
      </div>
      <div class="panel"><div class="panel-title"><h3>Precisa de suporte da equipe?</h3></div>
        <p class="helper-text">Para problemas no cadastro, verificação de documentos ou condutas inadequadas, use o canal de relatos. Situações urgentes de risco devem também acionar os canais de emergência.</p>
        <button class="primary" data-view="supporter-seguranca" type="button">Ir para Segurança e orientações</button>
      </div>
    </div>
  </template>
  <template id="view-supporter-notificacoes" data-label="Notificações">
    <section class="view-heading">
      <div><div class="eyebrow">AVISOS E ATUALIZAÇÕES</div><h1>Notificações</h1><p>Avisos sobre verificações, solicitações, agendamentos e mensagens.</p></div>
    </section>
    <div class="panel"><div class="notice-list" id="supNotificationList"><div class="empty-state">Carregando notificações…</div></div></div>
  </template>
  <template id="view-admin-apoiadores" data-label="Apoiadores e verificação">
    <section class="view-heading">
      <div><div class="eyebrow">REDE DE APOIADORES</div><h1>Apoiadores e verificação</h1><p>Analise cadastros, confira documentos e acompanhe relatos de risco encaminhados pela rede.</p></div>
      <span class="safe-badge">🔒 Dados de verificação restritos à equipe</span>
    </section>
    <div class="admin-filters">
      <input id="adminSupporterSearch" placeholder="⌕ Buscar por nome, e-mail ou cidade">
      <select id="adminSupporterFilter">
        <option value="">Todas as situações</option>
        <option value="pending">Em análise</option>
        <option value="approved">Verificados</option>
        <option value="rejected">Não aprovados</option>
        <option value="suspended">Suspensos</option>
      </select>
    </div>
    <div class="panel table-wrap">
      <table class="data-table table-wide"><thead><tr><th>Apoiador</th><th>Tipo</th><th>Cidade</th><th>Documentos</th><th>Atendimentos</th><th>Situação</th><th>Ação</th></tr></thead>
      <tbody id="adminSupportersTable"><tr><td colspan="7" class="empty-state">Carregando apoiadores…</td></tr></tbody></table>
    </div>
    <div class="panel-title audit-heading"><h3>Relatos de risco e abuso</h3><span class="muted-note">Prioridade: imediata &gt; atenção &gt; informativa</span></div>
    <div class="panel table-wrap">
      <table class="data-table table-wide"><thead><tr><th>Data</th><th>Apoiador</th><th>Situação</th><th>Prioridade</th><th>Status</th><th>Ação</th></tr></thead>
      <tbody id="adminRiskReportsTable"><tr><td colspan="6" class="empty-state">Nenhum relato registrado.</td></tr></tbody></table>
    </div>
  </template>
      <p class="demo-hint">Demonstração: admin@lumen.org · ana.silva@email.com · apoiador@lumen.org (verificado) · senha Lumen@123</p>
  <section class="onboarding-screen hidden" id="supporterOnboarding">
    <div class="onboarding-inner">
      <button class="link-button" id="onboardingBack" type="button">← Voltar para o início</button>
      <div class="eyebrow">REDE DE APOIO LUMEN</div>
      <h1>Quero ser apoiador(a)</h1>
      <p class="onboarding-subtitle">Dois tipos de atuação, uma só plataforma. Escolha o que representa você: os campos e as permissões se ajustam automaticamente.</p>
      <div class="type-cards" id="supporterTypeCards">
        <button type="button" class="type-card active" data-type="professional"><strong>Profissional autônomo</strong><span>Atendimento individual com registro no conselho profissional (CRP, CRM, OAB, CREF e similares).</span></button>
        <button type="button" class="type-card" data-type="institution"><strong>Instituição / ONG</strong><span>Organização com CNPJ, equipe própria e pessoa responsável pelo cadastro.</span></button>
      </div>
      <form class="panel form-card" id="supporterRegisterForm">
        <input type="hidden" name="supporter_type" id="supporterTypeField" value="professional">
        <div class="form-section-title"><span>01</span><div><h3>Dados de acesso e pessoais</h3><p>Sua conta será analisada antes de liberar atendimentos.</p></div></div>
        <div class="inline-fields">
          <div class="field"><label for="supRegName">Nome completo / responsável</label><input id="supRegName" name="name" maxlength="160" required></div>
          <div class="field"><label for="supRegCpf">CPF</label><input id="supRegCpf" name="cpf" maxlength="20" required></div>
        </div>
        <div class="inline-fields">
          <div class="field"><label for="supRegEmail">E-mail</label><input id="supRegEmail" name="email" type="email" required></div>
          <div class="field"><label for="supRegEmail2">Confirmar e-mail</label><input id="supRegEmail2" name="email_confirmation" type="email" required></div>
        </div>
        <div class="inline-fields">
          <div class="field"><label for="supRegPhone">Telefone</label><input id="supRegPhone" name="phone" maxlength="30" required></div>
          <div class="field"><label for="supRegCity">Cidade de atendimento</label><input id="supRegCity" name="city" maxlength="120" required></div>
          <div class="field"><label for="supRegState">UF</label><input id="supRegState" name="state" maxlength="2" placeholder="SP"></div>
        </div>
        <div class="inline-fields">
          <div class="field"><label for="supRegPassword">Senha (mínimo 10 caracteres)</label><input id="supRegPassword" name="password" type="password" required autocomplete="new-password"></div>
          <div class="field"><label for="supRegPassword2">Confirmar senha</label><input id="supRegPassword2" name="password_confirmation" type="password" required autocomplete="new-password"></div>
        </div>
        <div class="form-section-title"><span>02</span><div><h3>Dados profissionais</h3><p id="supporterTypeHint">Perfil de profissional autônomo: informe o registro no conselho.</p></div></div>
        <div class="field"><label for="supRegDisplay">Nome de exibição para a rede</label><input id="supRegDisplay" name="display_name" maxlength="160" placeholder="Ex.: Dra. Marina Prado — Psicologia"></div>
        <div class="inline-fields">
          <div class="field"><label for="supRegDocument">CPF ou CNPJ</label><input id="supRegDocument" name="document_number" maxlength="40"></div>
          <div class="field"><label for="supRegMode">Modalidade de atendimento</label><select id="supRegMode" name="service_mode"><option value="online">Online</option><option value="presencial">Presencial</option><option value="hibrido">Híbrido</option></select></div>
        </div>
        <div class="inline-fields type-field" data-type-field="professional">
          <div class="field"><label for="supRegCouncilName">Conselho profissional</label><input id="supRegCouncilName" name="council_name" maxlength="60" placeholder="CRP, CRM, OAB..."></div>
          <div class="field"><label for="supRegCouncilNumber">Número de registro</label><input id="supRegCouncilNumber" name="council_number" maxlength="40" placeholder="06/123456"></div>
          <div class="field"><label for="supRegCouncilState">UF do registro</label><input id="supRegCouncilState" name="council_state" maxlength="2"></div>
        </div>
        <div class="inline-fields type-field hidden" data-type-field="institution">
          <div class="field"><label for="supRegLegalName">Razão social</label><input id="supRegLegalName" name="legal_name" maxlength="190"></div>
          <div class="field"><label for="supRegResponsible">Pessoa responsável</label><input id="supRegResponsible" name="responsible_name" maxlength="160"></div>
          <div class="field"><label for="supRegResponsibleDoc">CPF do responsável</label><input id="supRegResponsibleDoc" name="responsible_document" maxlength="40"></div>
        </div>
        <div class="inline-fields">
          <div class="field"><label for="supRegTeamSize">Equipe envolvida</label><input id="supRegTeamSize" name="team_size" type="number" min="1" max="5000" value="1"></div>
          <div class="field"><label for="supRegMaxCases">Atendimentos simultâneos</label><input id="supRegMaxCases" name="max_active_cases" type="number" min="1" max="50" value="3"></div>
          <div class="field"><label for="supRegLanguages">Idiomas</label><input id="supRegLanguages" name="languages" maxlength="160" placeholder="Português, Libras"></div>
        </div>
        <div class="field"><label for="supRegApproach">Abordagem de atendimento</label><input id="supRegApproach" name="approach" maxlength="255" placeholder="Como você acolhe e conduz o atendimento"></div>
        <div class="field"><label for="supRegBio">Apresentação</label><textarea id="supRegBio" name="bio" rows="3" placeholder="Experiência, formação e frentes de atuação."></textarea></div>
        <div class="form-section-title"><span>03</span><div><h3>Documentos</h3><p>Após criar a conta, você entra na plataforma e envia os documentos para verificação.</p></div></div>
        <div id="supporterRegisterDocuments" class="notice-list"></div>
        <div class="form-actions"><button class="primary" type="submit">Enviar cadastro para análise</button></div>
      </form>
      <div class="panel hidden" id="supporterRegisterSuccess">
        <div class="eyebrow">CADASTRO ENVIADO</div>
        <h2>Recebemos seu cadastro!</h2>
        <p class="helper-text" id="supporterRegisterSuccessText"></p>
        <div id="supporterRegisterSuccessList" class="notice-list"></div>
        <div class="form-actions">
          <button class="secondary" id="supporterRegisterLogin" type="button">Entrar na minha conta</button>
          <button class="primary" id="supporterRegisterHome" type="button">Voltar ao início</button>
        </div>
      </div>
    </div>
  </section>
  <div class="modal-backdrop" id="supporterAppointmentModal"><div class="modal-card form-modal">
    <button class="close-modal" data-close-modal="supporterAppointmentModal" type="button">×</button>
    <div class="eyebrow">AGENDAMENTO</div><h2 id="supAppointmentTitle">Agendar atendimento</h2>
    <p class="helper-text">Escolha data e horário conforme sua disponibilidade. A beneficiária recebe um aviso com os detalhes.</p>
    <form id="supporterAppointmentForm">
      <input type="hidden" name="id" value="">
      <div class="field"><label for="supAppointmentAssignment">Atendimento</label><select id="supAppointmentAssignment" name="assignment_id" required></select></div>
      <div class="inline-fields">
        <div class="field"><label for="supAppointmentKind">Tipo</label><select id="supAppointmentKind" name="kind"><option value="first">Primeiro atendimento</option><option value="return">Retorno</option><option value="follow_up">Acompanhamento</option></select></div>
        <div class="field"><label for="supAppointmentMode">Modalidade</label><select id="supAppointmentMode" name="mode"><option value="online">Online</option><option value="presencial">Presencial</option><option value="hibrido">Híbrido</option></select></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supAppointmentDate">Data e hora</label><input id="supAppointmentDate" name="scheduled_at" type="datetime-local" required></div>
        <div class="field"><label for="supAppointmentDuration">Duração (min)</label><input id="supAppointmentDuration" name="duration_minutes" type="number" min="15" max="240" step="15" value="60"></div>
      </div>
      <div class="slot-helper">
        <label for="supSlotDate">Ver horários livres na minha agenda</label>
        <div class="inline-fields"><input id="supSlotDate" type="date"><button class="secondary" id="supLoadSlots" type="button">Buscar horários</button></div>
        <div id="supSlotList" class="slot-list"></div>
      </div>
      <div class="field"><label for="supAppointmentLocation">Local (atendimento presencial)</label><input id="supAppointmentLocation" name="location" maxlength="190"></div>
      <div class="field"><label for="supAppointmentNotes">Observações para a beneficiária</label><textarea id="supAppointmentNotes" name="notes" rows="2" maxlength="500"></textarea></div>
      <div class="form-actions"><button class="secondary" type="button" data-close-modal="supporterAppointmentModal">Cancelar</button><button class="primary" type="submit">Confirmar agendamento</button></div>
    </form>
  </div></div>
  <div class="modal-backdrop" id="supporterCloseModal"><div class="modal-card form-modal">
    <button class="close-modal" data-close-modal="supporterCloseModal" type="button">×</button>
    <div class="eyebrow">ENCERRAMENTO</div><h2>Encerrar atendimento</h2>
    <p class="helper-text">O encerramento preserva o histórico do caso e avisa a beneficiária. Não registre dados sensíveis desnecessários.</p>
    <form id="supporterCloseForm">
      <input type="hidden" name="id" value="">
      <div class="field"><label for="supCloseOutcome">Resultado</label>
        <select id="supCloseOutcome" name="outcome">
          <option value="concluido">Atendimento concluído</option>
          <option value="encaminhado">Encaminhado para outro serviço</option>
          <option value="interrompido">Interrompido a pedido da beneficiária</option>
        </select>
      </div>
      <div class="field"><label for="supCloseSummary">Resumo do atendimento</label><textarea id="supCloseSummary" name="summary" rows="4" required placeholder="O que foi realizado, encaminhamentos e próximos passos recomendados."></textarea></div>
      <div class="form-actions"><button class="secondary" type="button" data-close-modal="supporterCloseModal">Cancelar</button><button class="primary" type="submit">Encerrar atendimento</button></div>
    </form>
  </div></div>
  <div class="modal-backdrop" id="supporterServiceModal"><div class="modal-card form-modal">
    <button class="close-modal" data-close-modal="supporterServiceModal" type="button">×</button>
    <div class="eyebrow">SERVIÇOS OFERECIDOS</div><h2 id="supServiceModalTitle">Novo serviço</h2>
    <form id="supporterServiceForm">
      <input type="hidden" name="id" value="">
      <div class="field"><label for="supServiceName">Nome do serviço</label><input id="supServiceName" name="name" maxlength="160" required></div>
      <div class="inline-fields">
        <div class="field"><label for="supServiceCategory">Categoria</label><input id="supServiceCategory" name="category" maxlength="100" required placeholder="Saúde mental, apoio jurídico..."></div>
        <div class="field"><label for="supServiceMode">Modalidade</label><select id="supServiceMode" name="mode"><option value="online">Online</option><option value="presencial">Presencial</option><option value="hibrido">Híbrido</option></select></div>
      </div>
      <div class="inline-fields">
        <div class="field"><label for="supServiceDuration">Duração (min)</label><input id="supServiceDuration" name="duration_minutes" type="number" min="15" max="480" step="15" value="60"></div>
        <div class="field"><label for="supServicePrice">Condição</label><input id="supServicePrice" name="price_info" maxlength="120" placeholder="Gratuito, social, valor..."></div>
      </div>
      <div class="field"><label for="supServiceDescription">Descrição</label><textarea id="supServiceDescription" name="description" rows="3" maxlength="2000"></textarea></div>
      <div class="field"><label for="supServiceStatus">Situação</label><select id="supServiceStatus" name="status"><option value="active">Ativo na vitrine</option><option value="paused">Pausado</option></select></div>
      <div class="form-actions"><button class="secondary" type="button" data-close-modal="supporterServiceModal">Cancelar</button><button class="primary" type="submit">Salvar serviço</button></div>
    </form>
  </div></div>
        <button class="nav-item" data-view="mensagens"><span>✉</span>Mensagens</button>

  <script>window.LUMEN={csrf:<?= json_encode(csrf_token()) ?>,appUrl:<?= json_encode(APP_URL) ?>,authenticated:<?= $loggedIn ? 'true' : 'false' ?>,role:<?= json_encode($user['role'] ?? 'beneficiary') ?>,isSupporter:<?= $isSupporter ? 'true' : 'false' ?>,verification:<?= json_encode($supporterState['verification'] ?? null) ?>,pendingDocuments:<?= json_encode(count($supporterState['pending_documents'] ?? [])) ?>};</script>
  <script src="assets/app.js"></script>
  <script src="assets/supporter.js"></script>
</body>
</html>
  <link rel="stylesheet" href="assets/supporter.css">
