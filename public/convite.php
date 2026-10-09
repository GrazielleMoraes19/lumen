<?php
require_once __DIR__ . '/../includes/helpers.php';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$message = '';
$error = '';
$contact = null;
try {
    $stmt = db()->prepare('SELECT ec.*, u.name AS beneficiary_name FROM emergency_contacts ec JOIN users u ON u.id = ec.beneficiary_id WHERE ec.invite_token = ? AND ec.status = "invited"');
    $stmt->execute([$token]);
    $contact = $stmt->fetch();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $contact) {
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');
        $name = trim($_POST['name'] ?? $contact['name']);
        if (strlen($password) < 8 || $password !== $confirmation) throw new RuntimeException('As senhas precisam ter pelo menos 8 caracteres e conferir.');
        $pdo = db();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?'); $check->execute([$contact['email']]);
        $existing = $check->fetch();
        if ($existing) { $userId = (int)$existing['id']; $pdo->prepare('UPDATE users SET name = ?, password_hash = ?, role = "trusted_contact", status = "active" WHERE id = ?')->execute([$name, password_hash($password, PASSWORD_DEFAULT), $userId]); }
        else { $pdo->prepare('INSERT INTO users (role, name, email, password_hash) VALUES ("trusted_contact", ?, ?, ?)')->execute([$name, $contact['email'], password_hash($password, PASSWORD_DEFAULT)]); $userId = (int)$pdo->lastInsertId(); }
        $pdo->prepare('UPDATE emergency_contacts SET user_id = ?, status = "accepted", accepted_at = NOW() WHERE id = ?')->execute([$userId, $contact['id']]);
        $message = 'Conta criada com sucesso. Você já pode entrar no Lumen com o e-mail do convite.';
        $contact = null;
    }
} catch (Throwable $e) { $error = $e->getMessage(); }
?><!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Convite de emergência — Lumen</title><link rel="stylesheet" href="assets/styles.css"><link rel="stylesheet" href="assets/app.css"></head><body><main class="login-screen"><div class="login-visual"><a class="landing-brand" href="index.php"><img class="landing-brand-mark" src="assets/logo.png" alt="Logo do Lumen"><span><strong>LUMEN</strong><small>cuidado que ilumina</small></span></a><div class="login-quote"><h1>Sua presença,<br><em>é importante.</em></h1><p>Você foi convidada para fazer parte da rede de proteção do Lumen.</p></div></div><div class="login-panel"><div class="login-panel-inner"><div class="eyebrow">CONVITE LUMEN</div><h1>Criar conta de contato</h1><?php if ($message): ?><div class="flash-message"><?= e($message) ?><br><a href="index.php">Ir para o login →</a></div><?php elseif ($error): ?><div class="status pink" style="display:block;margin-bottom:16px;line-height:1.5"><?= e($error) ?></div><?php elseif (!$contact): ?><div class="panel"><p class="helper-text">Este convite é inválido, já foi utilizado ou expirou.</p></div><?php else: ?><p class="login-subtitle">Você foi convidada por <?= e($contact['beneficiary_name']) ?>. Use o e-mail <strong><?= e($contact['email']) ?></strong> para criar sua conta.</p><form method="post"><input type="hidden" name="token" value="<?= e($token) ?>"><div class="field"><label>Nome completo</label><input name="name" value="<?= e($contact['name']) ?>" required></div><div class="field"><label>E-mail</label><input value="<?= e($contact['email']) ?>" disabled></div><div class="field"><label>Senha</label><input name="password" type="password" minlength="8" required></div><div class="field"><label>Confirme sua senha</label><input name="password_confirmation" type="password" minlength="8" required></div><button class="primary login-button" type="submit">Criar conta <span>→</span></button></form><?php endif; ?></div></div></main></body></html>
