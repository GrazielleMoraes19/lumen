<?php
require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_response(bool $ok, string $message = '', array $data = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        json_response(false, 'Sua sessão expirou. Atualize a página e tente novamente.', [], 419);
    }
}

function remember_cookie_options(int $expires): array
{
    return [
        'expires' => $expires, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ];
}

function clear_remember_me(PDO $pdo): void
{
    $value = (string)($_COOKIE['lumen_remember'] ?? '');
    $parts = explode('.', $value, 2);
    if (count($parts) === 2 && preg_match('/^[a-f0-9]{24}$/', $parts[0])) {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->execute([$parts[0]]);
    }
    setcookie('lumen_remember', '', remember_cookie_options(time() - 3600));
    unset($_COOKIE['lumen_remember']);
}

function issue_remember_me(PDO $pdo, int $userId): void
{
    $old = explode('.', (string)($_COOKIE['lumen_remember'] ?? ''), 2);
    if (isset($old[0]) && preg_match('/^[a-f0-9]{24}$/', $old[0])) {
        $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')->execute([$old[0]]);
    }
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + (14 * 24 * 60 * 60);
    $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, FROM_UNIXTIME(?))');
    $stmt->execute([$userId, $selector, hash('sha256', $validator), $expires]);
    setcookie('lumen_remember', $selector . '.' . $validator, remember_cookie_options($expires));
}

function current_user(): ?array
{
    if (!empty($_SESSION['user'])) return $_SESSION['user'];
    $value = (string)($_COOKIE['lumen_remember'] ?? '');
    $parts = explode('.', $value, 2);
    if (count($parts) !== 2 || !preg_match('/^[a-f0-9]{24}$/', $parts[0]) || !preg_match('/^[a-f0-9]{64}$/', $parts[1])) return null;
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT t.id AS token_id,t.token_hash,t.user_id,u.role,u.name,u.email FROM remember_tokens t JOIN users u ON u.id=t.user_id WHERE t.selector=? AND t.expires_at>NOW() AND u.status="active" LIMIT 1');
        $stmt->execute([$parts[0]]);
        $row = $stmt->fetch();
        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $parts[1]))) {
            $pdo->prepare('DELETE FROM remember_tokens WHERE selector=?')->execute([$parts[0]]);
            setcookie('lumen_remember', '', remember_cookie_options(time() - 3600));
            return null;
        }
        // Rotaciona o segredo quando uma sessão é restaurada.
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>(int)$row['user_id'],'role'=>$row['role'],'name'=>$row['name'],'email'=>$row['email']];
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + (14 * 24 * 60 * 60);
        $pdo->prepare('UPDATE remember_tokens SET selector=?,token_hash=?,expires_at=FROM_UNIXTIME(?) WHERE id=?')->execute([$selector,hash('sha256',$validator),$expires,$row['token_id']]);
        setcookie('lumen_remember',$selector.'.'.$validator,remember_cookie_options($expires));
        return $_SESSION['user'];
    } catch (Throwable $error) {
        error_log('[Lumen][remember-me] ' . $error->getMessage());
        return null;
    }
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        json_response(false, 'Faça login para continuar.', [], 401);
    }
    return $user;
}

function require_roles(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        json_response(false, 'Você não tem permissão para executar esta ação.', [], 403);
    }
    return $user;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? $parts[count($parts) - 1] : '';
    $slice = static function (string $value): string {
        return function_exists('mb_substr') ? mb_substr($value, 0, 1) : substr($value, 0, 1);
    };
    return strtoupper($slice($first) . $slice($last));
}

function app_url(string $path = ''): string
{
    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administradora',
        'supporter' => 'Apoiador',
        'professional' => 'Profissional',
        'trusted_contact' => 'Contato de emergência',
        default => 'Beneficiária',
    };
}

function format_status(string $status): array
{
    return match ($status) {
        'approved' => ['Aprovada', 'green'],
        'forwarded' => ['Encaminhada', 'green'],
        'rejected' => ['Desaprovada', 'pink'],
        'triage' => ['Em triagem', 'yellow'],
        default => ['Nova', 'pink'],
    };
}

function send_email(string $to, string $subject, string $body): bool
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Lumen <nao-responda@lumen.local>\r\n";
    return @mail($to, $subject, $body, $headers);
}

function queue_notification(PDO $pdo, ?int $userId, string $recipient, string $channel, string $subject, string $message, ?string $phone = null): array
{
    $status = 'pending';
    $whatsappUrl = null;
    if ($channel === 'email') {
        $status = send_email($recipient, $subject, nl2br(e($message))) ? 'sent' : 'pending';
    } elseif ($channel === 'whatsapp') {
        $digits = preg_replace('/\D+/', '', $phone ?: $recipient);
        $whatsappUrl = $digits ? 'https://wa.me/' . $digits . '?text=' . rawurlencode($message) : null;
        // Sem credenciais de API do WhatsApp, o link fica pronto para abertura no WhatsApp Web/app.
        $status = $whatsappUrl ? 'prepared' : 'pending';
    }

    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, recipient, channel, subject, message, status, whatsapp_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$userId, $recipient, $channel, $subject, $message, $status, $whatsappUrl]);
    return ['status' => $status, 'whatsapp_url' => $whatsappUrl];
}

function notify_emergency_contacts(PDO $pdo, int $beneficiaryId, string $message, string $subject = 'Alerta Lumen'): void
{
    $stmt = $pdo->prepare('SELECT * FROM emergency_contacts WHERE beneficiary_id = ? AND status = "accepted"');
    $stmt->execute([$beneficiaryId]);
    foreach ($stmt->fetchAll() as $contact) {
        if (!empty($contact['email'])) {
            queue_notification($pdo, $contact['user_id'] ? (int)$contact['user_id'] : null, $contact['email'], 'email', $subject, $message, $contact['phone']);
        }
        if (!empty($contact['phone'])) {
            queue_notification($pdo, $contact['user_id'] ? (int)$contact['user_id'] : null, $contact['phone'], 'whatsapp', $subject, $message, $contact['phone']);
        }
    }
}

function notify_admins(PDO $pdo, string $subject, string $message): void
{
    $stmt = $pdo->query('SELECT id, email FROM users WHERE role = "admin" AND status = "active"');
    foreach ($stmt->fetchAll() as $admin) {
        queue_notification($pdo, (int)$admin['id'], $admin['email'], 'email', $subject, $message);
    }
}

function make_protocol(): string
{
    return 'LM-' . random_int(1000, 9999);
}

require_once __DIR__ . '/supporter_helpers.php';
