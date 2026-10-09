<?php
require_once __DIR__ . '/../../includes/helpers.php';

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Método não permitido.', [], 405);
}

try {
    check_csrf();
    $pdo = db();

    switch ($action) {
        case 'login':
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $requestedRole = $_POST['role'] ?? 'beneficiary';
            if (!$email || !$password) json_response(false, 'Informe e-mail e senha.', [], 422);
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $staffRoles = ['admin', 'supporter', 'professional'];
            $roleAllowed = $requestedRole === 'team' ? ($user && in_array($user['role'], $staffRoles, true)) : ($user && in_array($user['role'], ['beneficiary', 'trusted_contact'], true));
            if (!$user || !$roleAllowed || !password_verify($password, $user['password_hash'])) {
                json_response(false, 'E-mail, senha ou perfil incorreto.', [], 422);
            }
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$user['id'], 'role' => $user['role'], 'name' => $user['name'], 'email' => $user['email']];
            if (filter_var($_POST['remember'] ?? false, FILTER_VALIDATE_BOOLEAN)) issue_remember_me($pdo, (int)$user['id']);
            else clear_remember_me($pdo);
            json_response(true, 'Login realizado. Bem-vinda ao Lumen.', ['user' => $_SESSION['user']]);

        case 'logout':
            clear_remember_me($pdo);
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            json_response(true, 'Você saiu da sua conta.');

        case 'register':
            $name = trim($_POST['name'] ?? '');
            $cpf = trim($_POST['cpf'] ?? '');
            $rg = trim($_POST['rg'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $cep = trim($_POST['cep'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = strtolower(trim($_POST['email'] ?? ''));
            $emailConfirmation = strtolower(trim($_POST['email_confirmation'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $passwordConfirmation = (string)($_POST['password_confirmation'] ?? '');
            $role = $_POST['role'] ?? 'beneficiary';
            if (!$name || !$cpf || !$rg || !$address || !$cep || !$phone || !$email || !$password) json_response(false, 'Preencha todos os campos obrigatórios.', [], 422);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(false, 'Informe um e-mail válido.', [], 422);
            if ($email !== $emailConfirmation) json_response(false, 'A confirmação de e-mail não confere.', [], 422);
            if (strlen($password) < 8 || $password !== $passwordConfirmation) json_response(false, 'A senha deve ter pelo menos 8 caracteres e as confirmações devem conferir.', [], 422);
            // Cadastro público nunca concede papel de equipe. A criação de staff é restrita ao administrador.
            $role = 'beneficiary';
            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$email]);
            if ($exists->fetch()) json_response(false, 'Este e-mail já está cadastrado.', [], 422);
            $stmt = $pdo->prepare('INSERT INTO users (role, name, cpf, rg, address, cep, phone, email, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$role, $name, $cpf, $rg, $address, $cep, $phone, $email, password_hash($password, PASSWORD_DEFAULT)]);
            json_response(true, 'Cadastro realizado. Agora você já pode entrar no Lumen.');

        case 'forgot_request':
            $email = strtolower(trim($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(false, 'Informe um e-mail válido.', [], 422);
            $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = ? AND status = "active"');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) json_response(true, 'Se o e-mail estiver cadastrado, enviaremos as instruções de recuperação.');
            $token = bin2hex(random_bytes(32));
            $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?')->execute([$token, $user['id']]);
            $resetUrl = app_url('reset.php?token=' . urlencode($token));
            $body = '<p>Olá, ' . e($user['name']) . '.</p><p>Recebemos um pedido para redefinir sua senha do Lumen.</p><p><a href="' . e($resetUrl) . '">Clique aqui para criar uma nova senha</a>. Este link expira em 1 hora.</p><p>Se você não fez este pedido, ignore esta mensagem.</p>';
            $sent = send_email($email, 'Recuperação de senha — Lumen', $body);
            json_response(true, $sent ? 'Enviamos o link de recuperação para seu e-mail.' : 'O pedido foi registrado. Configure o SMTP/mail() no XAMPP para receber o e-mail.', APP_DEBUG ? ['reset_url' => $resetUrl] : []);

        case 'dashboard':
            $user = require_roles(['beneficiary']);
            $a = $pdo->prepare('SELECT COUNT(*) FROM support_requests WHERE beneficiary_id = ? AND status IN ("pending", "triage")'); $a->execute([$user['id']]);
            $b = $pdo->prepare('SELECT COUNT(*) FROM emergency_contacts WHERE beneficiary_id = ?'); $b->execute([$user['id']]);
            json_response(true, '', ['requests' => (int)$a->fetchColumn(), 'contacts' => (int)$b->fetchColumn()]);

        case 'profile':
            $sessionUser = require_login();
            $stmt = $pdo->prepare('SELECT id, role, name, cpf, rg, address, cep, phone, email, created_at FROM users WHERE id = ?'); $stmt->execute([$sessionUser['id']]);
            $profile = $stmt->fetch();
            $profile['initials'] = initials($profile['name']); $profile['role_label'] = role_label($profile['role']);
            json_response(true, '', ['user' => $profile]);

        case 'profile_update':
            $actor=require_login(); $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $phone=trim($_POST['phone']??'');
            if (!$name || strlen($name)>160 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190 || strlen($phone)>30) json_response(false,'Confira nome, e-mail e telefone.',[],422);
            $check=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?');$check->execute([$email,$actor['id']]);if($check->fetch())json_response(false,'Este e-mail já está em uso.',[],422);
            $pdo->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?')->execute([$name,$email,$phone?:null,$actor['id']]);
            $_SESSION['user']['name']=$name;$_SESSION['user']['email']=$email;
            json_response(true,'Seus dados foram atualizados.',['user'=>['name'=>$name,'email'=>$email,'phone'=>$phone,'initials'=>initials($name)]]);

        case 'support_request_create':
            $user = require_roles(['beneficiary']);
            $category = trim($_POST['category'] ?? ''); $description = trim($_POST['description'] ?? ''); $preferred = trim($_POST['preferred_contact'] ?? '');
            if (!$category || !$description) json_response(false, 'Selecione uma categoria e conte como podemos ajudar.', [], 422);
            $stmt = $pdo->prepare('INSERT INTO support_requests (beneficiary_id, category, description, preferred_contact) VALUES (?, ?, ?, ?)'); $stmt->execute([$user['id'], $category, $description, $preferred]);
            notify_admins($pdo, 'Nova solicitação de atendimento', 'Uma nova solicitação de atendimento foi registrada no Lumen.');
            json_response(true, 'Solicitação enviada. A equipe Lumen irá analisar seu pedido.');

        case 'my_requests':
            $user = require_roles(['beneficiary']);
            // Mostra quem está atendendo, o próximo agendamento e a conversa vinculada.
            $stmt = $pdo->prepare('SELECT q.id, q.category, q.description, q.preferred_contact, q.status, DATE_FORMAT(q.created_at, "%d/%m/%Y %H:%i") AS created_at,
                    a.id AS assignment_id, a.status AS assignment_status, COALESCE(p.display_name, s.name) AS supporter_name, p.supporter_type,
                    c.id AS conversation_id,
                    (SELECT DATE_FORMAT(ap.scheduled_at, "%d/%m/%Y %H:%i") FROM support_appointments ap WHERE ap.assignment_id = a.id AND ap.status IN ("scheduled", "confirmed") ORDER BY ap.scheduled_at ASC LIMIT 1) AS next_appointment
                FROM support_requests q
                LEFT JOIN support_assignments a ON a.request_id = q.id AND a.status IN ("accepted", "in_progress", "completed")
                LEFT JOIN users s ON s.id = a.supporter_id
                LEFT JOIN supporter_profiles p ON p.user_id = a.supporter_id
                LEFT JOIN support_conversations c ON c.assignment_id = a.id
                WHERE q.beneficiary_id = ? ORDER BY q.created_at DESC'); $stmt->execute([$user['id']]); $requests = $stmt->fetchAll();
            $stmt = $pdo->prepare('SELECT id, protocol, category, status, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at FROM reports WHERE beneficiary_id = ? ORDER BY created_at DESC'); $stmt->execute([$user['id']]); $reports = $stmt->fetchAll();
            json_response(true, '', ['requests' => $requests, 'reports' => $reports]);

        case 'emergency_add':
            $user = require_roles(['beneficiary']);
            $name = trim($_POST['name'] ?? ''); $email = strtolower(trim($_POST['email'] ?? '')); $phone = trim($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
            if (!$name || !$phone || !$address || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(false, 'Preencha nome, e-mail, telefone e endereço corretamente.', [], 422);
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('INSERT INTO emergency_contacts (beneficiary_id, name, email, phone, address, invite_token) VALUES (?, ?, ?, ?, ?, ?)'); $stmt->execute([$user['id'], $name, $email, $phone, $address, $token]);
            $inviteUrl = app_url('convite.php?token=' . urlencode($token));
            $message = 'Você foi cadastrada como contato de emergência de uma pessoa atendida pelo Lumen. Crie sua conta para receber avisos: ' . $inviteUrl;
            $emailResult = queue_notification($pdo, null, $email, 'email', 'Convite para ser contato de emergência — Lumen', $message, $phone);
            $whatsappResult = queue_notification($pdo, null, $phone, 'whatsapp', 'Convite Lumen', $message, $phone);
            json_response(true, 'Convite enviado por e-mail e mensagem de WhatsApp preparada.', ['whatsapp_url' => $whatsappResult['whatsapp_url'], 'invite_url' => $inviteUrl, 'email_status' => $emailResult['status']]);

        case 'emergency_list':
            $user = require_roles(['beneficiary']);
            $stmt = $pdo->prepare('SELECT id, name, email, phone, address, status FROM emergency_contacts WHERE beneficiary_id = ? ORDER BY invited_at DESC'); $stmt->execute([$user['id']]); $contacts = $stmt->fetchAll();
            foreach ($contacts as &$contact) { $contact['whatsapp_url'] = 'https://wa.me/' . preg_replace('/\D+/', '', $contact['phone']) . '?text=' . rawurlencode('Convite do Lumen: ' . app_url('convite.php')); }
            json_response(true, '', ['contacts' => $contacts]);

        case 'emergency_delete':
            $user = require_roles(['beneficiary']); $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM emergency_contacts WHERE id = ? AND beneficiary_id = ?'); $stmt->execute([$id, $user['id']]);
            json_response(true, $stmt->rowCount() ? 'Contato removido da sua rede.' : 'Contato não encontrado.');

        case 'report_create':
            $user = require_roles(['beneficiary']);
            $category = trim($_POST['category'] ?? ''); $when = trim($_POST['occurred_when'] ?? ''); $safe = trim($_POST['safe_now'] ?? ''); $location = trim($_POST['location'] ?? ''); $narrative = trim($_POST['narrative'] ?? '');
            if (!$category || !$when || !$safe || !$location || !$narrative) json_response(false, 'Preencha os campos obrigatórios da denúncia.', [], 422);
            $protocol = make_protocol();
            $stmt = $pdo->prepare('INSERT INTO reports (beneficiary_id, protocol, category, occurred_when, safe_now, location, narrative) VALUES (?, ?, ?, ?, ?, ?, ?)'); $stmt->execute([$user['id'], $protocol, $category, $when, $safe, $location, $narrative]);
            $reportId = (int)$pdo->lastInsertId();
            if (!empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0775, true);
                $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                foreach ($_FILES['files']['name'] as $index => $original) {
                    if ($_FILES['files']['error'][$index] === UPLOAD_ERR_NO_FILE) continue;
                    if ($_FILES['files']['error'][$index] !== UPLOAD_ERR_OK || $_FILES['files']['size'][$index] > MAX_UPLOAD_BYTES) continue;
                    $tmp = $_FILES['files']['tmp_name'][$index]; $mime = $finfo->file($tmp);
                    if (!in_array($mime, $allowed, true)) continue;
                    $stored = bin2hex(random_bytes(12)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($original));
                    if (move_uploaded_file($tmp, UPLOAD_DIR . '/' . $stored)) { $fileStmt = $pdo->prepare('INSERT INTO report_files (report_id, original_name, stored_name, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?)'); $fileStmt->execute([$reportId, basename($original), $stored, $mime, (int)$_FILES['files']['size'][$index]]); }
                }
            }
            $urgency = $safe === 'Não, preciso de ajuda' ? ' URGENTE: a pessoa informou que não está em segurança.' : '';
            notify_admins($pdo, 'Nova denúncia ' . $protocol, 'Uma nova denúncia foi registrada.' . $urgency);
            json_response(true, 'Denúncia registrada com o protocolo #' . $protocol . '. A equipe fará a triagem com cuidado.', ['protocol' => $protocol]);

        case 'report_appeal':
            $user = require_roles(['beneficiary']); $id = (int)($_POST['id'] ?? 0); $text = trim($_POST['appeal_text'] ?? '');
            if (!$text) json_response(false, 'Inclua novas informações ou provas para recorrer.', [], 422);
            $stmt = $pdo->prepare('UPDATE reports SET appeal_text = ?, appeal_status = "pending", status = "triage" WHERE id = ? AND beneficiary_id = ? AND status = "rejected"'); $stmt->execute([$text, $id, $user['id']]);
            if (!$stmt->rowCount()) json_response(false, 'Esta denúncia não está disponível para recurso.', [], 422);
            notify_admins($pdo, 'Recurso de denúncia', 'Uma beneficiária enviou novas informações para revisão de uma denúncia.');
            json_response(true, 'Seu recurso foi enviado para nova análise.');

        case 'admin_summary':
            require_roles(['admin']);
            $counts = [];
            foreach (['pending','triage','forwarded','rejected'] as $status) { $stmt = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE status = ?'); $stmt->execute([$status]); $counts[$status] = (int)$stmt->fetchColumn(); }
            $stmt = $pdo->query('SELECT COUNT(*) FROM support_requests WHERE status IN ("pending", "triage")'); $active = (int)$stmt->fetchColumn();
            $stmt = $pdo->query('SELECT r.id, r.protocol, r.category, r.status, u.name, DATE_FORMAT(r.created_at, "%d/%m/%Y %H:%i") AS created_at FROM reports r JOIN users u ON u.id = r.beneficiary_id ORDER BY r.created_at DESC LIMIT 5'); $recent = $stmt->fetchAll();
            json_response(true, '', ['pending' => $counts['pending'], 'triage' => $counts['triage'], 'active' => $active, 'resolved' => $counts['forwarded'], 'recent' => $recent]);

        case 'admin_reports':
            require_roles(['admin']);
            $stmt = $pdo->query('SELECT r.id, r.protocol, r.category, r.status, DATE_FORMAT(r.created_at, "%d/%m/%Y %H:%i") AS created_at, u.name FROM reports r JOIN users u ON u.id = r.beneficiary_id ORDER BY r.created_at DESC');
            json_response(true, '', ['reports' => $stmt->fetchAll()]);

        case 'report_detail':
            require_roles(['admin']); $id = (int)($_POST['id'] ?? 0);
            $actor = require_roles(['admin']); $stmt = $pdo->prepare('SELECT r.*, u.name, u.email, u.phone FROM reports r JOIN users u ON u.id = r.beneficiary_id WHERE r.id = ?'); $stmt->execute([$id]); $report = $stmt->fetch();
            if (!$report) json_response(false, 'Denúncia não encontrada.', [], 404);
            $stmt = $pdo->prepare('SELECT original_name FROM report_files WHERE report_id = ?'); $stmt->execute([$id]); $files=$stmt->fetchAll();
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)')->execute([$actor['id'],'report.viewed','report',(string)$id,'Detalhes sensíveis consultados',$_SERVER['REMOTE_ADDR']??null]);
            $historyStmt=$pdo->prepare('SELECT a.action,a.details,DATE_FORMAT(a.created_at,"%d/%m/%Y %H:%i") AS created_at,u.name AS actor FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id WHERE a.entity_type="report" AND a.entity_id=? ORDER BY a.created_at DESC LIMIT 30');$historyStmt->execute([(string)$id]);
            json_response(true, '', ['report' => $report, 'files' => $files, 'history'=>$historyStmt->fetchAll()]);

        case 'report_decide':
            require_roles(['admin']); $id = (int)($_POST['id'] ?? 0); $decision = $_POST['decision'] ?? ''; $notes = trim($_POST['notes'] ?? '');
            if (!in_array($decision, ['approve', 'reject'], true)) json_response(false, 'Decisão inválida.', [], 422);
            if ($decision === 'reject' && !$notes) json_response(false, 'Informe o motivo da desaprovação.', [], 422);
            $stmt = $pdo->prepare('SELECT r.*, u.email, u.name FROM reports r JOIN users u ON u.id = r.beneficiary_id WHERE r.id = ?'); $stmt->execute([$id]); $report = $stmt->fetch();
            if (!$report) json_response(false, 'Denúncia não encontrada.', [], 404);
            if ($decision === 'reject') {
                $pdo->prepare('UPDATE reports SET status = "rejected", admin_notes = ?, appeal_status = NULL WHERE id = ?')->execute([$notes, $id]);
                queue_notification($pdo, (int)$report['beneficiary_id'], $report['email'], 'email', 'Atualização da denúncia #' . $report['protocol'], 'Sua denúncia foi desaprovada após a triagem. Motivo: ' . $notes . ' Você pode recorrer e inserir novas informações pelo Histórico do Lumen.');
                json_response(true, 'Denúncia desaprovada e a beneficiária foi notificada.');
            }
            $pdo->prepare('UPDATE reports SET status = "forwarded", admin_notes = ? WHERE id = ?')->execute([$notes ?: 'Aprovada pela equipe Lumen e encaminhada à rede de apoio.', $id]);
            $service = $pdo->query('SELECT name, phone FROM support_services WHERE status = "active" ORDER BY id LIMIT 1')->fetch();
            $destination = $service ? 'Órgão de apoio: ' . $service['name'] . '. ' : 'A equipe de apoio foi acionada. ';
            queue_notification($pdo, (int)$report['beneficiary_id'], $report['email'], 'email', 'Denúncia encaminhada #' . $report['protocol'], $destination . 'Sua denúncia foi aprovada e encaminhada para atendimento.');
            json_response(true, 'Denúncia aprovada e encaminhada para a rede de apoio.');

        case 'admin_beneficiaries':
            require_roles(['admin']);
            $stmt = $pdo->query('SELECT u.id, u.name, u.email, u.phone, DATE_FORMAT(u.created_at, "%d/%m/%Y") AS created_at, (SELECT COUNT(*) FROM reports r WHERE r.beneficiary_id = u.id AND r.status IN ("pending", "triage", "forwarded")) AS active_cases FROM users u WHERE u.role = "beneficiary" ORDER BY u.created_at DESC');
            json_response(true, '', ['users' => $stmt->fetchAll()]);

        case 'admin_beneficiary_detail':
            $actor=require_roles(['admin']); $id=(int)($_POST['id']??0);
            $stmt=$pdo->prepare('SELECT id,name,email,phone,status,DATE_FORMAT(created_at,"%d/%m/%Y") AS created_at FROM users WHERE id=? AND role="beneficiary"'); $stmt->execute([$id]); $person=$stmt->fetch();
            if(!$person) json_response(false,'Beneficiária não encontrada.',[],404);
            $stmt=$pdo->prepare('SELECT protocol,category,status,DATE_FORMAT(created_at,"%d/%m/%Y %H:%i") AS created_at FROM reports WHERE beneficiary_id=? ORDER BY created_at DESC');$stmt->execute([$id]);$person['reports']=$stmt->fetchAll();
            $stmt=$pdo->prepare('SELECT category,status,DATE_FORMAT(created_at,"%d/%m/%Y %H:%i") AS created_at FROM support_requests WHERE beneficiary_id=? ORDER BY created_at DESC');$stmt->execute([$id]);$person['requests']=$stmt->fetchAll();
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)')->execute([$actor['id'],'beneficiary.profile_viewed','user',(string)$id,'Perfil e histórico de atendimento consultados',$_SERVER['REMOTE_ADDR']??null]);
            json_response(true,'',['person'=>$person]);

        case 'admin_beneficiary_toggle':
            $actor=require_roles(['admin']);$id=(int)($_POST['id']??0);$stmt=$pdo->prepare('SELECT status FROM users WHERE id=? AND role="beneficiary"');$stmt->execute([$id]);$status=$stmt->fetchColumn();
            if($status===false)json_response(false,'Beneficiária não encontrada.',[],404);$next=$status==='active'?'inactive':'active';$pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$next,$id]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)')->execute([$actor['id'],'beneficiary.status_changed','user',(string)$id,'Conta alterada para '.$next,$_SERVER['REMOTE_ADDR']??null]);json_response(true,$next==='active'?'Conta reativada.':'Conta desativada; os registros foram preservados.');

        case 'admin_requests':
            require_roles(['admin']);
            $stmt = $pdo->query('SELECT q.id, q.category, q.description, q.preferred_contact, q.status, DATE_FORMAT(q.created_at, "%d/%m/%Y %H:%i") AS created_at, u.name, u.email FROM support_requests q JOIN users u ON u.id=q.beneficiary_id ORDER BY FIELD(q.status,"pending","triage","forwarded","resolved"), q.created_at DESC');
            json_response(true, '', ['requests' => $stmt->fetchAll()]);

        case 'admin_request_update':
            $actor = require_roles(['admin']);
            $id=(int)($_POST['id']??0); $next=trim($_POST['status']??''); $note=trim($_POST['note']??'');
            if (!in_array($next,['pending','triage','forwarded','resolved'],true)) json_response(false,'Status inválido.',[],422);
            $stmt=$pdo->prepare('SELECT status FROM support_requests WHERE id=?'); $stmt->execute([$id]); $old=$stmt->fetchColumn();
            if ($old===false) json_response(false,'Solicitação não encontrada.',[],404);
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE support_requests SET status=? WHERE id=?')->execute([$next,$id]);
            $pdo->prepare('INSERT INTO support_request_history(request_id,actor_id,old_status,new_status,note) VALUES(?,?,?,?,?)')->execute([$id,$actor['id'],$old,$next,$note ?: null]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)')->execute([$actor['id'],'request.status_changed','support_request',(string)$id,"{$old} → {$next}" . ($note ? '; nota registrada' : ''),$_SERVER['REMOTE_ADDR']??null]);
            $pdo->commit();
            json_response(true,'Status atualizado. O histórico foi preservado.');

        case 'admin_audit':
            require_roles(['admin']);
            $stmt=$pdo->query('SELECT a.id,a.action,a.entity_type,a.entity_id,a.details,a.ip_address,DATE_FORMAT(a.created_at,"%d/%m/%Y %H:%i") AS created_at,u.name AS actor FROM audit_logs a LEFT JOIN users u ON u.id=a.actor_id ORDER BY a.created_at DESC LIMIT 200');
            json_response(true,'',['events'=>$stmt->fetchAll()]);

        case 'admin_reports_stats':
            require_roles(['admin']);
            $stmt=$pdo->query('SELECT status,COUNT(*) AS total FROM reports GROUP BY status'); $byStatus=$stmt->fetchAll();
            $stmt=$pdo->query('SELECT category,COUNT(*) AS total FROM reports GROUP BY category ORDER BY total DESC LIMIT 8'); $byCategory=$stmt->fetchAll();
            $stmt=$pdo->query('SELECT COUNT(*) FROM users WHERE role="beneficiary"'); $beneficiaries=(int)$stmt->fetchColumn();
            $stmt=$pdo->query('SELECT COUNT(*) FROM support_requests WHERE status IN("pending","triage")'); $active=(int)$stmt->fetchColumn();
            json_response(true,'',['by_status'=>$byStatus,'by_category'=>$byCategory,'beneficiaries'=>$beneficiaries,'active_requests'=>(int)$active]);

        case 'admin_notifications':
            $actor=require_roles(['admin']);
            $stmt=$pdo->prepare('SELECT id,channel,subject,message,status,DATE_FORMAT(created_at,"%d/%m/%Y %H:%i") AS created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100'); $stmt->execute([$actor['id']]);
            json_response(true,'',['notifications'=>$stmt->fetchAll()]);

        case 'admin_staff':
            require_roles(['admin']);
            $stmt=$pdo->query('SELECT id,name,email,role,status,DATE_FORMAT(created_at,"%d/%m/%Y") AS created_at FROM users WHERE role IN("admin","supporter","professional") ORDER BY name');
            json_response(true,'',['staff'=>$stmt->fetchAll()]);

        case 'admin_staff_create':
            $actor=require_roles(['admin']); $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $role=$_POST['role']??'supporter'; $password=(string)($_POST['password']??'');
            if (!$name || !filter_var($email,FILTER_VALIDATE_EMAIL) || !in_array($role,['admin','supporter','professional'],true) || strlen($password)<12) json_response(false,'Confira nome, e-mail, função e senha temporária (mínimo 12 caracteres).',[],422);
            $stmt=$pdo->prepare('INSERT INTO users(role,name,email,password_hash) VALUES(?,?,?,?)');
            try{$stmt->execute([$role,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);}catch(PDOException $e){if((string)$e->getCode()==='23000')json_response(false,'Este e-mail já está cadastrado.',[],422);throw $e;}
            $newId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)')->execute([$actor['id'],'staff.created','user',(string)$newId,'Novo acesso da equipe: '.$role,$_SERVER['REMOTE_ADDR']??null]);
            json_response(true,'Acesso criado. Oriente a pessoa a trocar a senha após o primeiro login.');

        case 'admin_user_toggle':
            $actor=require_roles(['admin']); $id=(int)($_POST['id']??0);
            if ($id===(int)$actor['id']) json_response(false,'Não é possível desativar sua própria conta nesta sessão.',[],422);
            $stmt=$pdo->prepare('SELECT status FROM users WHERE id=? AND role IN("admin","supporter","professional")'); $stmt->execute([$id]); $status=$stmt->fetchColumn();
            if ($status===false) json_response(false,'Conta da equipe não encontrada.',[],404);
            $next=$status==='active'?'inactive':'active'; $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$next,$id]);
            $pdo->prepare('INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?)')->execute([$actor['id'],'staff.status_changed','user',(string)$id,"Conta alterada para {$next}",$_SERVER['REMOTE_ADDR']??null]);
            json_response(true,$next==='active'?'Acesso reativado.':'Acesso desativado.');

        case 'services':
            require_login(); $stmt = $pdo->query('SELECT id, name, category, city, phone, address, status FROM support_services WHERE status = "active" ORDER BY name'); json_response(true, '', ['services' => $stmt->fetchAll()]);

        case 'service_add':
            require_roles(['admin']);
            $name = trim($_POST['name'] ?? ''); $category = trim($_POST['category'] ?? ''); $city = trim($_POST['city'] ?? ''); $phone = trim($_POST['phone'] ?? ''); $address = trim($_POST['address'] ?? '');
            if (!$name || !$category || !$city) json_response(false, 'Informe nome, categoria e cidade.', [], 422);
            $pdo->prepare('INSERT INTO support_services (name, category, city, phone, address) VALUES (?, ?, ?, ?, ?)')->execute([$name, $category, $city, $phone, $address]); json_response(true, 'Serviço adicionado à rede de apoio.');

        case 'notifications':
            $user = require_login(); $stmt = $pdo->prepare('SELECT id, channel, subject, message, status, whatsapp_url, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at FROM notifications WHERE user_id = ? OR (user_id IS NULL AND recipient = ?) ORDER BY created_at DESC LIMIT 20'); $stmt->execute([$user['id'], $user['email']]); json_response(true, '', ['notifications' => $stmt->fetchAll()]);

        case 'chat':
            require_login();
            $message = trim((string)($_POST['message'] ?? ''));
            $messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
            if ($message === '') json_response(false, 'Digite uma mensagem antes de enviar.', [], 422);
            if ($messageLength > 4000) json_response(false, 'A mensagem deve ter no máximo 4.000 caracteres.', [], 422);

            $historyJson = (string)($_POST['history'] ?? '[]');
            if (strlen($historyJson) > 60000) json_response(false, 'A conversa está muito longa. Atualize a página para continuar.', [], 422);
            $history = json_decode($historyJson, true);
            if (!is_array($history)) json_response(false, 'Não foi possível ler a conversa. Atualize a página e tente novamente.', [], 422);

            $messages = [[
                'role' => 'system',
                'content' => 'Você é o assistente virtual do Lumen. Responda em português do Brasil de forma acolhedora, clara e objetiva. O Lumen é uma plataforma de apoio e orientação para mulheres em situação de violência doméstica. Não substitua profissionais, serviços especializados ou serviços de emergência. Não faça julgamentos, não culpe a pessoa e nunca incentive que permaneça em uma situação perigosa. Se houver risco imediato, oriente a procurar um local seguro se for possível e acionar o serviço de emergência local; no Brasil, a Polícia Militar atende pelo 190 e a Central de Atendimento à Mulher pelo 180. Não afirme que a equipe Lumen está monitorando esta conversa, que foi notificada ou que acionará ajuda por meio deste chat. Não solicite dados pessoais desnecessários.'
            ]];

            foreach (array_slice(array_values($history), -12) as $item) {
                if (!is_array($item) || !in_array($item['role'] ?? null, ['user', 'assistant'], true) || !is_string($item['content'] ?? null)) {
                    json_response(false, 'Não foi possível validar o histórico da conversa. Atualize a página e tente novamente.', [], 422);
                }
                $historyMessage = trim($item['content']);
                if ($historyMessage === '') continue;
                $historyLength = function_exists('mb_strlen') ? mb_strlen($historyMessage, 'UTF-8') : strlen($historyMessage);
                if ($historyLength > 4000) json_response(false, 'Uma mensagem anterior excede o limite permitido. Atualize a página para continuar.', [], 422);
                $messages[] = ['role' => $item['role'], 'content' => $historyMessage];
            }
            $messages[] = ['role' => 'user', 'content' => $message];

            if (!function_exists('curl_init')) {
                error_log('[Lumen][chat] PHP cURL extension is not available.');
                json_response(false, 'O chat está temporariamente indisponível. Tente novamente mais tarde.', [], 503);
            }

            $requestBody = json_encode([
                'model' => OLLAMA_MODEL,
                'messages' => $messages,
                'stream' => false,
                'keep_alive' => '10m',
                'options' => [
                    'temperature' => 0.4,
                    'num_predict' => 600,
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($requestBody === false) json_response(false, 'Não foi possível preparar sua mensagem. Tente novamente.', [], 422);

            $curl = curl_init(OLLAMA_URL);
            if ($curl === false) {
                error_log('[Lumen][chat] Could not initialize the Ollama request.');
                json_response(false, 'O chat está temporariamente indisponível. Tente novamente mais tarde.', [], 503);
            }
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => $requestBody,
            ]);
            $providerResponse = curl_exec($curl);
            $curlError = $providerResponse === false ? curl_error($curl) : '';
            $providerStatus = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($providerResponse === false) {
                error_log('[Lumen][chat] Ollama request failed: ' . $curlError);
                json_response(false, 'Não foi possível conectar ao assistente. Tente novamente em instantes.', [], 502);
            }
            if ($providerStatus < 200 || $providerStatus >= 300) {
                error_log('[Lumen][chat] Ollama returned HTTP ' . $providerStatus . '.');
                json_response(false, 'O assistente está indisponível no momento. Tente novamente em instantes.', [], 502);
            }

            $providerData = json_decode($providerResponse, true);
            $replyContent = $providerData['message']['content'] ?? null;
            $reply = is_string($replyContent) ? trim($replyContent) : '';
            if ($reply === '') {
                error_log('[Lumen][chat] Ollama returned an empty or invalid completion.');
                json_response(false, 'Não foi possível obter uma resposta do assistente. Tente novamente.', [], 502);
            }

            json_response(true, '', ['reply' => $reply]);

        case 'sos':
            $user = require_roles(['beneficiary']); $lat = $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null; $lng = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
            $message = 'SOS acionado por ' . $user['name'] . '. A pessoa informou que precisa de ajuda agora. ' . ($lat !== null ? 'Localização: https://maps.google.com/?q=' . $lat . ',' . $lng : 'Localização não compartilhada.');
            $pdo->prepare('INSERT INTO sos_alerts (beneficiary_id, latitude, longitude, message) VALUES (?, ?, ?, ?)')->execute([$user['id'], $lat, $lng, $message]);
            $stmt = $pdo->prepare('SELECT * FROM emergency_contacts WHERE beneficiary_id = ? AND status IN ("accepted", "invited")'); $stmt->execute([$user['id']]); $contacts = $stmt->fetchAll(); $firstWhatsApp = null;
            foreach ($contacts as $contact) { if ($contact['email']) queue_notification($pdo, $contact['user_id'] ? (int)$contact['user_id'] : null, $contact['email'], 'email', 'SOS Lumen — pedido de ajuda', $message, $contact['phone']); if ($contact['phone']) { $wa = queue_notification($pdo, $contact['user_id'] ? (int)$contact['user_id'] : null, $contact['phone'], 'whatsapp', 'SOS Lumen', $message, $contact['phone']); $firstWhatsApp = $firstWhatsApp ?: $wa['whatsapp_url']; } }
            notify_admins($pdo, 'SOS acionado no Lumen', $message);
            json_response(true, 'Pedido de ajuda enviado. Seus contatos e a equipe Lumen foram notificados.', ['whatsapp_url' => $firstWhatsApp]);


        /* ------------------------------------------- Mensagens entre apoiador e beneficiária */

        case 'conversation_list':
            $user = require_login();
            if (is_supporter_role($user['role'])) {
                $stmt = $pdo->prepare('SELECT c.id, c.status, c.assignment_id, r.category, r.id AS request_id, u.name AS counterpart,
                        DATE_FORMAT(COALESCE(c.last_message_at, c.created_at), "%d/%m/%Y %H:%i") AS last_message_at,
                        (SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id = c.id AND m.sender_role = "beneficiary" AND m.read_at IS NULL) AS unread,
                        (SELECT LEFT(m.body, 90) FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS preview
                    FROM support_conversations c
                    JOIN support_assignments a ON a.id = c.assignment_id
                    JOIN support_requests r ON r.id = a.request_id
                    JOIN users u ON u.id = c.beneficiary_id
                    WHERE c.supporter_id = ? ORDER BY COALESCE(c.last_message_at, c.created_at) DESC LIMIT 100');
            } else {
                $stmt = $pdo->prepare('SELECT c.id, c.status, c.assignment_id, r.category, r.id AS request_id, COALESCE(p.display_name, s.name) AS counterpart,
                        DATE_FORMAT(COALESCE(c.last_message_at, c.created_at), "%d/%m/%Y %H:%i") AS last_message_at,
                        (SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id = c.id AND m.sender_role = "supporter" AND m.read_at IS NULL) AS unread,
                        (SELECT LEFT(m.body, 90) FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS preview
                    FROM support_conversations c
                    JOIN support_assignments a ON a.id = c.assignment_id
                    JOIN support_requests r ON r.id = a.request_id
                    JOIN users s ON s.id = c.supporter_id
                    LEFT JOIN supporter_profiles p ON p.user_id = c.supporter_id
                    WHERE c.beneficiary_id = ? ORDER BY COALESCE(c.last_message_at, c.created_at) DESC LIMIT 100');
            }
            $stmt->execute([(int)$user['id']]);
            json_response(true, '', ['conversations' => $stmt->fetchAll()]);

        case 'conversation_detail':
            $user = require_login(); $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT c.*, a.status AS assignment_status, r.category,
                    b.name AS beneficiary_name, b.phone AS beneficiary_phone,
                    COALESCE(p.display_name, s.name) AS supporter_name, p.supporter_type, p.city AS supporter_city
                FROM support_conversations c
                JOIN support_assignments a ON a.id = c.assignment_id
                JOIN support_requests r ON r.id = a.request_id
                JOIN users b ON b.id = c.beneficiary_id
                JOIN users s ON s.id = c.supporter_id
                LEFT JOIN supporter_profiles p ON p.user_id = c.supporter_id
                WHERE c.id = ? AND (c.beneficiary_id = ? OR c.supporter_id = ?) LIMIT 1');
            $stmt->execute([$id, (int)$user['id'], (int)$user['id']]);
            $conversation = $stmt->fetch();
            if (!$conversation) json_response(false, 'Conversa não encontrada entre os seus atendimentos.', [], 404);
            $isSupporter = is_supporter_role($user['role']);
            // Quem abre a conversa marca como lida apenas o que a outra parte enviou.
            $pdo->prepare('UPDATE support_messages SET read_at = NOW() WHERE conversation_id = ? AND sender_id <> ? AND read_at IS NULL')->execute([$id, (int)$user['id']]);
            $messages = $pdo->prepare('SELECT id, sender_id, sender_role, body, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at FROM support_messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 300');
            $messages->execute([$id]);
            $appointments = $pdo->prepare('SELECT id, kind, status, mode, location, notes, DATE_FORMAT(scheduled_at, "%d/%m/%Y %H:%i") AS scheduled_at FROM support_appointments WHERE assignment_id = ? AND status IN ("scheduled", "confirmed") ORDER BY scheduled_at ASC LIMIT 5');
            $appointments->execute([(int)$conversation['assignment_id']]);
            // A beneficiária vê o apoiador pelo nome de exibição e tipo; o contato pessoal fica protegido.
            $counterpart = $isSupporter
                ? ['name' => $conversation['beneficiary_name'], 'detail' => 'Beneficiária atendida por você', 'contact' => $conversation['beneficiary_phone']]
                : ['name' => $conversation['supporter_name'], 'detail' => supporter_type_label((string)($conversation['supporter_type'] ?? 'professional')) . ($conversation['supporter_city'] ? ' · ' . $conversation['supporter_city'] : ''), 'contact' => null];
            if ($isSupporter) {
                log_audit($pdo, (int)$user['id'], 'supporter.conversation_viewed', 'support_conversation', (string)$id, 'Conversa de atendimento autorizado consultada');
            }
            json_response(true, '', [
                'conversation' => ['id' => (int)$conversation['id'], 'status' => $conversation['status'], 'category' => $conversation['category'], 'assignment_id' => (int)$conversation['assignment_id'], 'assignment_status' => $conversation['assignment_status']],
                'counterpart' => $counterpart,
                'messages' => $messages->fetchAll(),
                'appointments' => $appointments->fetchAll(),
                'role' => $isSupporter ? 'supporter' : 'beneficiary',
            ]);

        case 'message_send':
            $user = require_login();
            $id = (int)($_POST['conversation_id'] ?? 0);
            $body = trim((string)($_POST['message'] ?? ''));
            $length = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
            if ($body === '') json_response(false, 'Digite uma mensagem antes de enviar.', [], 422);
            if ($length > 4000) json_response(false, 'A mensagem deve ter no máximo 4.000 caracteres.', [], 422);
            $stmt = $pdo->prepare('SELECT c.id, c.status, c.beneficiary_id, c.supporter_id, b.name AS beneficiary_name, b.email AS beneficiary_email,
                    s.email AS supporter_email, COALESCE(p.display_name, s.name) AS supporter_display
                FROM support_conversations c
                JOIN users b ON b.id = c.beneficiary_id
                JOIN users s ON s.id = c.supporter_id
                LEFT JOIN supporter_profiles p ON p.user_id = c.supporter_id
                WHERE c.id = ? AND (c.beneficiary_id = ? OR c.supporter_id = ?) LIMIT 1');
            $stmt->execute([$id, (int)$user['id'], (int)$user['id']]);
            $conversation = $stmt->fetch();
            if (!$conversation) json_response(false, 'Conversa não encontrada entre os seus atendimentos.', [], 404);
            if ($conversation['status'] === 'closed') json_response(false, 'Este atendimento foi encerrado. Abra uma nova solicitação para continuar o cuidado.', [], 409);
            $isSupporter = (int)$conversation['supporter_id'] === (int)$user['id'];
            $pdo->prepare('INSERT INTO support_messages (conversation_id, sender_id, sender_role, body) VALUES (?, ?, ?, ?)')->execute([$id, (int)$user['id'], $isSupporter ? 'supporter' : 'beneficiary', $body]);
            $pdo->prepare('UPDATE support_conversations SET last_message_at = NOW() WHERE id = ?')->execute([$id]);
            if ($isSupporter) {
                notify_user($pdo, (int)$conversation['beneficiary_id'], (string)$conversation['beneficiary_email'], 'Nova mensagem do seu atendimento no Lumen',
                    $conversation['supporter_display'] . ' enviou uma mensagem sobre o seu atendimento. Acesse Mensagens para responder.');
            } else {
                notify_user($pdo, (int)$conversation['supporter_id'], (string)$conversation['supporter_email'], 'Nova mensagem de beneficiária',
                    'Você recebeu uma nova mensagem no atendimento de ' . $conversation['beneficiary_name'] . '. Acesse Mensagens para responder.');
            }
            json_response(true, 'Mensagem enviada.', ['created_at' => date('d/m/Y H:i')]);

        /* ------------------------------------------- Verificação de apoiadores (equipe) */

        case 'admin_supporters':
            require_roles(['admin']);
            $stmt = $pdo->query('SELECT u.id, u.name, u.email, u.status, p.supporter_type, p.display_name, p.verification_status, p.city, p.state,
                    DATE_FORMAT(p.submitted_at, "%d/%m/%Y %H:%i") AS submitted_at,
                    (SELECT COUNT(*) FROM supporter_documents d WHERE d.supporter_id = u.id) AS documents,
                    (SELECT COUNT(*) FROM supporter_documents d WHERE d.supporter_id = u.id AND d.status = "pending") AS pending_documents,
                    (SELECT COUNT(*) FROM support_assignments a WHERE a.supporter_id = u.id AND a.status IN ("accepted", "in_progress")) AS active_cases,
                    (SELECT COUNT(*) FROM support_assignments a WHERE a.supporter_id = u.id AND a.status = "completed") AS completed_cases
                FROM users u JOIN supporter_profiles p ON p.user_id = u.id
                WHERE u.role IN ("supporter", "professional")
                ORDER BY FIELD(p.verification_status, "pending", "rejected", "suspended", "approved"), u.name');
            $risks = $pdo->query('SELECT rr.id, rr.category, rr.urgency, rr.status, rr.description, DATE_FORMAT(rr.created_at, "%d/%m/%Y %H:%i") AS created_at, u.name AS supporter
                FROM supporter_risk_reports rr JOIN users u ON u.id = rr.supporter_id ORDER BY FIELD(rr.status, "new", "reviewing", "resolved"), rr.created_at DESC LIMIT 100');
            json_response(true, '', ['supporters' => $stmt->fetchAll(), 'risk_reports' => $risks->fetchAll()]);

        case 'admin_supporter_detail':
            $actor = require_roles(['admin']); $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT u.id, u.name, u.email, u.phone, u.cpf, u.address, u.cep, u.status, DATE_FORMAT(u.created_at, "%d/%m/%Y") AS created_at, p.*
                FROM users u JOIN supporter_profiles p ON p.user_id = u.id WHERE u.id = ? LIMIT 1');
            $stmt->execute([$id]);
            $supporter = $stmt->fetch();
            if (!$supporter) json_response(false, 'Apoiador não encontrado.', [], 404);
            $docs = $pdo->prepare('SELECT id, kind, original_name, mime_type, size_bytes, status, review_notes, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at, DATE_FORMAT(reviewed_at, "%d/%m/%Y %H:%i") AS reviewed_at FROM supporter_documents WHERE supporter_id = ? ORDER BY created_at DESC');
            $docs->execute([$id]);
            $specialties = $pdo->prepare('SELECT id, name, notes FROM supporter_specialties WHERE supporter_id = ? ORDER BY name');
            $specialties->execute([$id]);
            $availability = $pdo->prepare('SELECT weekday, TIME_FORMAT(start_time, "%H:%i") AS start_time, TIME_FORMAT(end_time, "%H:%i") AS end_time, slot_minutes, mode FROM supporter_availability WHERE supporter_id = ? ORDER BY weekday, start_time');
            $availability->execute([$id]);
            $services = $pdo->prepare('SELECT name, category, mode, price_info, status FROM supporter_services WHERE supporter_id = ? ORDER BY name');
            $services->execute([$id]);
            $cases = $pdo->prepare('SELECT a.id, a.status, r.category, DATE_FORMAT(a.accepted_at, "%d/%m/%Y") AS accepted_at, DATE_FORMAT(a.closed_at, "%d/%m/%Y") AS closed_at FROM support_assignments a JOIN support_requests r ON r.id = a.request_id WHERE a.supporter_id = ? ORDER BY a.accepted_at DESC LIMIT 30');
            $cases->execute([$id]);
            $history = $pdo->prepare('SELECT a.action, a.details, DATE_FORMAT(a.created_at, "%d/%m/%Y %H:%i") AS created_at FROM audit_logs a WHERE a.actor_id = ? OR (a.entity_type = "supporter" AND a.entity_id = ?) ORDER BY a.created_at DESC LIMIT 40');
            $history->execute([$id, (string)$id]);
            log_audit($pdo, (int)$actor['id'], 'supporter.profile_reviewed', 'supporter', (string)$id, 'Cadastro e documentos do apoiador consultados para verificação');
            json_response(true, '', [
                'supporter' => $supporter,
                'documents' => $docs->fetchAll(),
                'specialties' => $specialties->fetchAll(),
                'availability' => $availability->fetchAll(),
                'services' => $services->fetchAll(),
                'cases' => $cases->fetchAll(),
                'history' => $history->fetchAll(),
            ]);

        case 'admin_supporter_decide':
            $actor = require_roles(['admin']); $id = (int)($_POST['id'] ?? 0);
            $decision = trim((string)($_POST['decision'] ?? '')); $notes = trim((string)($_POST['notes'] ?? ''));
            if (!in_array($decision, ['approve', 'reject', 'suspend'], true)) json_response(false, 'Decisão inválida.', [], 422);
            if ($decision !== 'approve' && $notes === '') json_response(false, 'Informe o motivo da decisão.', [], 422);
            $stmt = $pdo->prepare('SELECT u.id, u.name, u.email FROM users u JOIN supporter_profiles p ON p.user_id = u.id WHERE u.id = ? LIMIT 1');
            $stmt->execute([$id]);
            $supporter = $stmt->fetch();
            if (!$supporter) json_response(false, 'Apoiador não encontrado.', [], 404);
            $status = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended'][$decision];
            $pdo->prepare('UPDATE supporter_profiles SET verification_status = ?, verification_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE user_id = ?')
                ->execute([$status, $notes ?: null, (int)$actor['id'], $id]);
            log_audit($pdo, (int)$actor['id'], 'supporter.verification_' . $decision, 'supporter', (string)$id, 'Cadastro ' . verification_badge($status)[0] . ($notes ? ': ' . $notes : ''));
            $messages = [
                'approve' => 'Seu cadastro foi verificado. Você já pode receber solicitações e atender pela plataforma.',
                'reject' => 'Seu cadastro não foi aprovado nesta análise. Motivo: ' . $notes . ' Você pode corrigir as informações e enviar novamente.',
                'suspend' => 'Seu acesso de atendimento foi suspenso. Motivo: ' . $notes . ' Entre em contato com a equipe Lumen para revisar a situação.',
            ];
            notify_user($pdo, (int)$supporter['id'], (string)$supporter['email'], 'Atualização da verificação do seu cadastro', $messages[$decision]);
            json_response(true, 'Decisão registrada e o apoiador foi notificado.');

        case 'admin_supporter_document_review':
            $actor = require_roles(['admin']); $documentId = (int)($_POST['id'] ?? 0);
            $decision = trim((string)($_POST['decision'] ?? '')); $notes = trim((string)($_POST['notes'] ?? ''));
            if (!in_array($decision, ['approve', 'reject'], true)) json_response(false, 'Decisão inválida.', [], 422);
            if ($decision === 'reject' && $notes === '') json_response(false, 'Informe o motivo da recusa do documento.', [], 422);
            $stmt = $pdo->prepare('SELECT d.id, d.kind, d.supporter_id, u.email FROM supporter_documents d JOIN users u ON u.id = d.supporter_id WHERE d.id = ? LIMIT 1');
            $stmt->execute([$documentId]);
            $document = $stmt->fetch();
            if (!$document) json_response(false, 'Documento não encontrado.', [], 404);
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare('UPDATE supporter_documents SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$status, $notes ?: null, (int)$actor['id'], $documentId]);
            log_audit($pdo, (int)$actor['id'], 'supporter.document_' . $decision, 'supporter', (string)$document['supporter_id'], document_kind_label((string)$document['kind']) . ($status === 'approved' ? ' aprovado' : ' recusado') . ($notes ? ': ' . $notes : ''));
            notify_user($pdo, (int)$document['supporter_id'], (string)$document['email'], 'Revisão de documento do seu cadastro',
                document_kind_label((string)$document['kind']) . ' foi ' . ($status === 'approved' ? 'aprovado' : 'recusado') . '.' . ($notes ? ' Observação: ' . $notes : ''));
            json_response(true, 'Documento revisado.');

        case 'admin_risk_report_update':
            $actor = require_roles(['admin']); $id = (int)($_POST['id'] ?? 0);
            $status = trim((string)($_POST['status'] ?? '')); $notes = trim((string)($_POST['notes'] ?? ''));
            if (!in_array($status, ['new', 'reviewing', 'resolved'], true)) json_response(false, 'Situação inválida.', [], 422);
            $stmt = $pdo->prepare('SELECT rr.id, rr.supporter_id, u.email FROM supporter_risk_reports rr JOIN users u ON u.id = rr.supporter_id WHERE rr.id = ? LIMIT 1');
            $stmt->execute([$id]);
            $risk = $stmt->fetch();
            if (!$risk) json_response(false, 'Relato não encontrado.', [], 404);
            $pdo->prepare('UPDATE supporter_risk_reports SET status = ?, review_notes = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?')
                ->execute([$status, $notes ?: null, (int)$actor['id'], $id]);
            log_audit($pdo, (int)$actor['id'], 'risk_report.updated', 'risk_report', (string)$id, 'Situação: ' . $status);
            notify_user($pdo, (int)$risk['supporter_id'], (string)$risk['email'], 'Atualização do seu relato de risco', 'A equipe Lumen atualizou o relato para: ' . $status . '.' . ($notes ? ' Retorno da equipe: ' . $notes : ''));
            json_response(true, 'Relato atualizado.');

        default:
            json_response(false, 'Ação não reconhecida.', [], 404);
    }
} catch (Throwable $error) {
    error_log('[Lumen] ' . $error->getMessage());
    json_response(false, APP_DEBUG ? 'Erro do servidor: ' . $error->getMessage() : 'Não foi possível concluir a ação. Tente novamente.', [], 500);
}
