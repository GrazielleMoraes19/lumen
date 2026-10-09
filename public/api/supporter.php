<?php
/**
 * LUMEN — API da área do apoiador / profissional.
 *
 * Toda ação é autenticada (exceto `register`) e limitada ao próprio apoiador.
 * Um apoiador só acessa dados de beneficiárias de atendimentos que aceitou.
 */

require_once __DIR__ . '/../../includes/helpers.php';

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Método não permitido.', [], 405);
}

/** Clamp numérico simples. */
function clamp_int($value, int $min, int $max, int $fallback): int
{
    $number = (int) $value;
    if ($number < $min || $number > $max) {
        return $fallback;
    }
    return $number;
}

/** Converte data/hora local (`Y-m-d\TH:i` ou `Y-m-d H:i`) em `Y-m-d H:i:s`. */
function parse_local_datetime(string $value): ?string
{
    $value = trim(str_replace('T', ' ', $value));
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i'] as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    return null;
}

function guard_supporter_area(PDO $pdo): array
{
    $user = require_supporter();
    $state = supporter_area_state($pdo, $user);
    if (!$state['profile']) {
        json_response(false, 'Complete seu cadastro profissional para usar esta área.', ['state' => $state], 409);
    }
    return [$user, $state];
}

function guard_supporter_working(PDO $pdo): array
{
    [$user, $state] = guard_supporter_area($pdo);
    if (!$state['can_work']) {
        json_response(false, 'Seu cadastro ainda está em análise. Conclua o envio de documentos para começar a receber solicitações.', ['state' => $state], 403);
    }
    return [$user, $state];
}

try {
    check_csrf();
    $pdo = db();

    switch ($action) {
        /* ---------------------------------------------------------------- Cadastro */

        case 'register':
            $type = trim((string) ($_POST['supporter_type'] ?? ''));
            if (!array_key_exists($type, supporter_types())) {
                json_response(false, 'Escolha o tipo de perfil: profissional autônomo, instituição/ONG ou voluntário(a).', [], 422);
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $emailConfirmation = strtolower(trim((string) ($_POST['email_confirmation'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $cpf = trim((string) ($_POST['cpf'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
            $displayName = trim((string) ($_POST['display_name'] ?? ''));
            $city = trim((string) ($_POST['city'] ?? ''));
            $state = strtoupper(substr(trim((string) ($_POST['state'] ?? '')), 0, 2));
            $serviceMode = trim((string) ($_POST['service_mode'] ?? 'online'));

            if ($name === '' || $cpf === '' || $phone === '' || $city === '') {
                json_response(false, 'Preencha nome, CPF, telefone e cidade de atendimento.', [], 422);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(false, 'Informe um e-mail válido.', [], 422);
            }
            if ($email !== $emailConfirmation) {
                json_response(false, 'A confirmação de e-mail não confere.', [], 422);
            }
            if (strlen($password) < 10 || $password !== $passwordConfirmation) {
                json_response(false, 'A senha deve ter pelo menos 10 caracteres e as confirmações devem conferir.', [], 422);
            }
            if (!in_array($serviceMode, ['online', 'presencial', 'hibrido'], true)) {
                $serviceMode = 'online';
            }

            $profile = [
                'display_name' => $displayName !== '' ? $displayName : $name,
                'document_type' => $type === 'institution' ? 'cnpj' : 'cpf',
                'document_number' => trim((string) ($_POST['document_number'] ?? '')) ?: $cpf,
                'council_name' => trim((string) ($_POST['council_name'] ?? '')),
                'council_number' => trim((string) ($_POST['council_number'] ?? '')),
                'council_state' => strtoupper(substr(trim((string) ($_POST['council_state'] ?? '')), 0, 2)),
                'legal_name' => trim((string) ($_POST['legal_name'] ?? '')),
                'responsible_name' => trim((string) ($_POST['responsible_name'] ?? '')),
                'responsible_document' => trim((string) ($_POST['responsible_document'] ?? '')),
                'team_size' => clamp_int($_POST['team_size'] ?? 0, 1, 5000, 1),
                'bio' => trim((string) ($_POST['bio'] ?? '')),
                'approach' => trim((string) ($_POST['approach'] ?? '')),
                'languages' => trim((string) ($_POST['languages'] ?? '')),
                'max_active_cases' => clamp_int($_POST['max_active_cases'] ?? 0, 1, 50, 3),
            ];

            if (supporter_types()[$type]['requires_council'] && ($profile['council_name'] === '' || $profile['council_number'] === '')) {
                json_response(false, 'Informe o conselho profissional e o número de registro (ex.: CRP 06/123456).', [], 422);
            }
            if (supporter_types()[$type]['requires_legal_name'] && ($profile['legal_name'] === '' || $profile['responsible_name'] === '')) {
                json_response(false, 'Informe a razão social da instituição e o nome da pessoa responsável.', [], 422);
            }

            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $exists->execute([$email]);
            if ($exists->fetch()) {
                json_response(false, 'Este e-mail já está cadastrado. Use a recuperação de senha se precisar.', [], 422);
            }

            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO users (role, name, cpf, phone, email, password_hash) VALUES ("supporter", ?, ?, ?, ?, ?)')
                ->execute([$name, $cpf, $phone, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $newId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO supporter_profiles (user_id, supporter_type, display_name, document_type, document_number, council_name, council_number, council_state,
                    legal_name, responsible_name, responsible_document, team_size, bio, approach, city, state, service_mode, languages, max_active_cases,
                    verification_status, submitted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", NOW())'
            )->execute([
                $newId, $type, $profile['display_name'], $profile['document_type'], $profile['document_number'],
                $profile['council_name'] ?: null, $profile['council_number'] ?: null, $profile['council_state'] ?: null,
                $profile['legal_name'] ?: null, $profile['responsible_name'] ?: null, $profile['responsible_document'] ?: null,
                $profile['team_size'], $profile['bio'] ?: null, $profile['approach'] ?: null, $city, $state ?: null,
                $serviceMode, $profile['languages'] ?: null, $profile['max_active_cases'],
            ]);
            $pdo->commit();

            log_audit($pdo, $newId, 'supporter.registered', 'supporter', (string) $newId, 'Novo cadastro de ' . supporter_type_label($type));
            notify_admins($pdo, 'Novo cadastro de apoiador aguardando verificação', 'Um novo cadastro do tipo ' . supporter_type_label($type) . ' foi enviado e precisa de análise de documentos.');

            $required = supporter_required_documents($type);
            json_response(true, 'Cadastro enviado para análise. Nossa equipe vai verificar seus documentos.', [
                'verification' => 'pending',
                'required_documents' => array_map(static fn($kind, $label) => ['kind' => $kind, 'label' => $label], array_keys($required), array_values($required)),
                'type_label' => supporter_type_label($type),
            ]);

        /* ---------------------------------------------------------------- Perfil e verificação */

        case 'state':
            $user = require_supporter();
            $state = supporter_area_state($pdo, $user);
            $stmt = $pdo->prepare('SELECT id, kind, original_name, status, review_notes, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at FROM supporter_documents WHERE supporter_id = ? ORDER BY created_at DESC');
            $stmt->execute([(int) $user['id']]);
            json_response(true, '', [
                'state' => $state,
                'type_label' => supporter_type_label($state['type']),
                'required_documents' => supporter_required_documents($state['type']),
                'documents' => $stmt->fetchAll(),
            ]);

        case 'profile':
            [$user, $state] = guard_supporter_area($pdo);
            $stmt = $pdo->prepare('SELECT id, name, cpf, phone, email, address, cep, created_at FROM users WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
            $account = $stmt->fetch();
            $account['initials'] = initials($account['name']);

            $stmt = $pdo->prepare('SELECT id, name, notes FROM supporter_specialties WHERE supporter_id = ? ORDER BY name');
            $stmt->execute([(int) $user['id']]);
            $specialties = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT id, weekday, TIME_FORMAT(start_time, "%H:%i") AS start_time, TIME_FORMAT(end_time, "%H:%i") AS end_time, slot_minutes, mode, active FROM supporter_availability WHERE supporter_id = ? ORDER BY weekday, start_time');
            $stmt->execute([(int) $user['id']]);
            $availability = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT id, off_date, TIME_FORMAT(start_time, "%H:%i") AS start_time, TIME_FORMAT(end_time, "%H:%i") AS end_time, reason FROM supporter_time_off WHERE supporter_id = ? AND off_date >= CURDATE() ORDER BY off_date');
            $stmt->execute([(int) $user['id']]);
            $timeOff = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT id, label, address, city, state, cep, notes, is_primary FROM supporter_locations WHERE supporter_id = ? ORDER BY is_primary DESC, label');
            $stmt->execute([(int) $user['id']]);
            $locations = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM supporter_services WHERE supporter_id = ? AND status = "active"');
            $stmt->execute([(int) $user['id']]);
            $activeServices = (int) $stmt->fetchColumn();

            json_response(true, '', [
                'account' => $account,
                'profile' => $state['profile'],
                'type' => $state['type'],
                'type_label' => supporter_type_label($state['type']),
                'state' => $state,
                'specialties' => $specialties,
                'availability' => $availability,
                'time_off' => $timeOff,
                'locations' => $locations,
                'active_services' => $activeServices,
            ]);

        case 'profile_save':
            [$user] = guard_supporter_area($pdo);
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            $cep = trim((string) ($_POST['cep'] ?? ''));
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(false, 'Confira o nome e o e-mail informados.', [], 422);
            }
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
            $check->execute([$email, (int) $user['id']]);
            if ($check->fetch()) {
                json_response(false, 'Este e-mail já está em uso por outra conta.', [], 422);
            }

            $mode = trim((string) ($_POST['service_mode'] ?? 'online'));
            if (!in_array($mode, ['online', 'presencial', 'hibrido'], true)) {
                $mode = 'online';
            }
            $type = trim((string) ($_POST['supporter_type'] ?? ''));
            $current = supporter_profile_row($pdo, (int) $user['id']);
            $finalType = array_key_exists($type, supporter_types()) ? $type : (string) ($current['supporter_type'] ?? 'professional');

            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET name = ?, email = ?, phone = ?, address = ?, cep = ? WHERE id = ?')
                ->execute([$name, $email, $phone ?: null, $address ?: null, $cep ?: null, (int) $user['id']]);
            $pdo->prepare(
                'UPDATE supporter_profiles SET supporter_type = ?, display_name = ?, document_number = ?, council_name = ?, council_number = ?, council_state = ?,
                    legal_name = ?, responsible_name = ?, responsible_document = ?, team_size = ?, bio = ?, approach = ?, city = ?, state = ?,
                    service_mode = ?, languages = ?, max_active_cases = ? WHERE user_id = ?'
            )->execute([
                $finalType,
                trim((string) ($_POST['display_name'] ?? '')) ?: $name,
                trim((string) ($_POST['document_number'] ?? '')) ?: null,
                trim((string) ($_POST['council_name'] ?? '')) ?: null,
                trim((string) ($_POST['council_number'] ?? '')) ?: null,
                strtoupper(substr(trim((string) ($_POST['council_state'] ?? '')), 0, 2)) ?: null,
                trim((string) ($_POST['legal_name'] ?? '')) ?: null,
                trim((string) ($_POST['responsible_name'] ?? '')) ?: null,
                trim((string) ($_POST['responsible_document'] ?? '')) ?: null,
                clamp_int($_POST['team_size'] ?? 0, 1, 5000, 1),
                trim((string) ($_POST['bio'] ?? '')) ?: null,
                trim((string) ($_POST['approach'] ?? '')) ?: null,
                trim((string) ($_POST['city'] ?? '')) ?: null,
                strtoupper(substr(trim((string) ($_POST['state'] ?? '')), 0, 2)) ?: null,
                $mode,
                trim((string) ($_POST['languages'] ?? '')) ?: null,
                clamp_int($_POST['max_active_cases'] ?? 0, 1, 50, 5),
                (int) $user['id'],
            ]);
            $pdo->commit();

            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            log_audit($pdo, (int) $user['id'], 'supporter.profile_updated', 'supporter', (string) $user['id'], 'Dados pessoais e profissionais atualizados');
            json_response(true, 'Dados atualizados.', ['name' => $name, 'initials' => initials($name)]);

        case 'specialty_save':
            [$user] = guard_supporter_area($pdo);
            $name = trim((string) ($_POST['name'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $id = (int) ($_POST['id'] ?? 0);
            if ($name === '' || strlen($name) > 120) {
                json_response(false, 'Informe o nome da especialidade (até 120 caracteres).', [], 422);
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE supporter_specialties SET name = ?, notes = ? WHERE id = ? AND supporter_id = ?');
                $stmt->execute([$name, $notes ?: null, $id, (int) $user['id']]);
                log_audit($pdo, (int) $user['id'], 'supporter.specialty_updated', 'supporter', (string) $user['id'], 'Especialidade atualizada');
            } else {
                $pdo->prepare('INSERT INTO supporter_specialties (supporter_id, name, notes) VALUES (?, ?, ?)')->execute([(int) $user['id'], $name, $notes ?: null]);
                log_audit($pdo, (int) $user['id'], 'supporter.specialty_added', 'supporter', (string) $user['id'], 'Especialidade adicionada');
            }
            json_response(true, 'Especialidades atualizadas.');

        case 'specialty_delete':
            [$user] = guard_supporter_area($pdo);
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM supporter_specialties WHERE id = ? AND supporter_id = ?')->execute([$id, (int) $user['id']]);
            json_response(true, 'Especialidade removida.');

        case 'availability_save':
            [$user] = guard_supporter_area($pdo);
            $id = (int) ($_POST['id'] ?? 0);
            $weekday = clamp_int($_POST['weekday'] ?? -1, 0, 6, -1);
            $start = trim((string) ($_POST['start_time'] ?? ''));
            $end = trim((string) ($_POST['end_time'] ?? ''));
            $slot = clamp_int($_POST['slot_minutes'] ?? 60, 15, 240, 60);
            $mode = trim((string) ($_POST['mode'] ?? 'online'));
            if ($weekday < 0 || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
                json_response(false, 'Informe o dia da semana e um intervalo válido (início antes do fim).', [], 422);
            }
            if (!in_array($mode, ['online', 'presencial', 'hibrido'], true)) {
                $mode = 'online';
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE supporter_availability SET weekday = ?, start_time = ?, end_time = ?, slot_minutes = ?, mode = ? WHERE id = ? AND supporter_id = ?');
                $stmt->execute([$weekday, $start, $end, $slot, $mode, $id, (int) $user['id']]);
            } else {
                $pdo->prepare('INSERT INTO supporter_availability (supporter_id, weekday, start_time, end_time, slot_minutes, mode) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([(int) $user['id'], $weekday, $start, $end, $slot, $mode]);
            }
            log_audit($pdo, (int) $user['id'], 'supporter.availability_updated', 'supporter', (string) $user['id'], 'Horários de disponibilidade atualizados');
            json_response(true, 'Disponibilidade atualizada.');

        case 'availability_delete':
            [$user] = guard_supporter_area($pdo);
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM supporter_availability WHERE id = ? AND supporter_id = ?')->execute([$id, (int) $user['id']]);
            json_response(true, 'Faixa de horário removida.');

        case 'timeoff_save':
            [$user] = guard_supporter_area($pdo);
            $date = trim((string) ($_POST['off_date'] ?? ''));
            $parsed = parse_local_datetime($date . ' 00:00');
            if (!$parsed) {
                json_response(false, 'Informe uma data válida para o bloqueio.', [], 422);
            }
            $start = trim((string) ($_POST['start_time'] ?? ''));
            $end = trim((string) ($_POST['end_time'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $pdo->prepare('INSERT INTO supporter_time_off (supporter_id, off_date, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?)')
                ->execute([(int) $user['id'], substr($parsed, 0, 10), $start !== '' ? $start : null, $end !== '' ? $end : null, $reason ?: null]);
            log_audit($pdo, (int) $user['id'], 'supporter.timeoff_added', 'supporter', (string) $user['id'], 'Bloqueio de agenda em ' . substr($parsed, 0, 10));
            json_response(true, 'Bloqueio registrado na sua agenda.');

        case 'timeoff_delete':
            [$user] = guard_supporter_area($pdo);
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM supporter_time_off WHERE id = ? AND supporter_id = ?')->execute([$id, (int) $user['id']]);
            json_response(true, 'Bloqueio removido.');

        case 'location_save':
            [$user] = guard_supporter_area($pdo);
            $id = (int) ($_POST['id'] ?? 0);
            $label = trim((string) ($_POST['label'] ?? ''));
            if ($label === '') {
                json_response(false, 'Informe um nome para o local de atendimento.', [], 422);
            }
            $values = [
                $label,
                trim((string) ($_POST['address'] ?? '')) ?: null,
                trim((string) ($_POST['city'] ?? '')) ?: null,
                strtoupper(substr(trim((string) ($_POST['state'] ?? '')), 0, 2)) ?: null,
                trim((string) ($_POST['cep'] ?? '')) ?: null,
                trim((string) ($_POST['notes'] ?? '')) ?: null,
                !empty($_POST['is_primary']) ? 1 : 0,
            ];
            if ($values[6] === 1) {
                $pdo->prepare('UPDATE supporter_locations SET is_primary = 0 WHERE supporter_id = ?')->execute([(int) $user['id']]);
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE supporter_locations SET label = ?, address = ?, city = ?, state = ?, cep = ?, notes = ?, is_primary = ? WHERE id = ? AND supporter_id = ?');
                $stmt->execute([...$values, $id, (int) $user['id']]);
            } else {
                $pdo->prepare('INSERT INTO supporter_locations (supporter_id, label, address, city, state, cep, notes, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([(int) $user['id'], ...$values]);
            }
            log_audit($pdo, (int) $user['id'], 'supporter.location_saved', 'supporter', (string) $user['id'], 'Local de atendimento atualizado');
            json_response(true, 'Local de atendimento salvo.');

        case 'location_delete':
            [$user] = guard_supporter_area($pdo);
            $id = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM supporter_locations WHERE id = ? AND supporter_id = ?')->execute([$id, (int) $user['id']]);
            json_response(true, 'Local removido.');

        case 'document_upload':
            [$user, $state] = guard_supporter_area($pdo);
            $kind = trim((string) ($_POST['kind'] ?? ''));
            if (!array_key_exists($kind, supporter_required_documents($state['type'])) && $kind !== 'other') {
                json_response(false, 'Tipo de documento inválido para o seu perfil.', [], 422);
            }
            if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                json_response(false, 'Selecione um arquivo para enviar.', [], 422);
            }
            if ((int) $_FILES['file']['size'] > MAX_UPLOAD_BYTES) {
                json_response(false, 'O arquivo deve ter no máximo 10 MB.', [], 422);
            }
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['file']['tmp_name']);
            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
                json_response(false, 'Envie um arquivo PDF, JPG ou PNG.', [], 422);
            }
            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0775, true);
            }
            $original = basename((string) $_FILES['file']['name']);
            $stored = 'doc_' . bin2hex(random_bytes(12)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $original);
            if (!move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
                json_response(false, 'Não foi possível guardar o arquivo. Verifique a permissão da pasta storage/uploads.', [], 500);
            }
            $pdo->prepare('DELETE FROM supporter_documents WHERE supporter_id = ? AND kind = ? AND status = "rejected"')->execute([(int) $user['id'], $kind]);
            $pdo->prepare('INSERT INTO supporter_documents (supporter_id, kind, original_name, stored_name, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([(int) $user['id'], $kind, $original, $stored, $mime, (int) $_FILES['file']['size']]);
            // Reenvio de documento reabre a análise do cadastro.
            $pdo->prepare('UPDATE supporter_profiles SET verification_status = "pending", verification_notes = NULL, submitted_at = NOW() WHERE user_id = ? AND verification_status <> "approved"')
                ->execute([(int) $user['id']]);
            log_audit($pdo, (int) $user['id'], 'supporter.document_uploaded', 'supporter', (string) $user['id'], 'Documento enviado: ' . document_kind_label($kind));
            notify_admins($pdo, 'Documento de apoiador enviado', 'Um apoiador enviou ' . document_kind_label($kind) . ' para verificação.');
            json_response(true, 'Documento enviado para verificação.');

        case 'document_delete':
            [$user] = guard_supporter_area($pdo);
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT stored_name, kind FROM supporter_documents WHERE id = ? AND supporter_id = ? LIMIT 1');
            $stmt->execute([$id, (int) $user['id']]);
            $doc = $stmt->fetch();
            if (!$doc) {
                json_response(false, 'Documento não encontrado.', [], 404);
            }
            if ($doc['stored_name'] && is_file(UPLOAD_DIR . '/' . $doc['stored_name'])) {
                @unlink(UPLOAD_DIR . '/' . $doc['stored_name']);
            }
            $pdo->prepare('DELETE FROM supporter_documents WHERE id = ? AND supporter_id = ?')->execute([$id, (int) $user['id']]);
            log_audit($pdo, (int) $user['id'], 'supporter.document_deleted', 'supporter', (string) $user['id'], 'Documento removido pelo próprio apoiador');
            json_response(true, 'Documento removido.');

        /* ---------------------------------------------------------------- Painel */

        case 'dashboard':
            [$user, $state] = guard_supporter_area($pdo);
            $id = (int) $user['id'];

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_requests r WHERE r.status IN ("pending", "triage") AND NOT EXISTS (SELECT 1 FROM support_assignments a WHERE a.request_id = r.id AND a.supporter_id = ?)');
            $stmt->execute([$id]);
            $available = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_assignments WHERE supporter_id = ? AND status IN ("accepted", "in_progress")');
            $stmt->execute([$id]);
            $active = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_assignments WHERE supporter_id = ? AND status = "completed"');
            $stmt->execute([$id]);
            $completed = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_appointments ap JOIN support_assignments a ON a.id = ap.assignment_id WHERE a.supporter_id = ? AND ap.scheduled_at >= NOW() AND ap.status IN ("scheduled", "confirmed")');
            $stmt->execute([$id]);
            $upcoming = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_messages m JOIN support_conversations c ON c.id = m.conversation_id WHERE c.supporter_id = ? AND m.sender_role = "beneficiary" AND m.read_at IS NULL');
            $stmt->execute([$id]);
            $unread = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                'SELECT ap.id, ap.kind, ap.status, ap.mode, ap.location, DATE_FORMAT(ap.scheduled_at, "%d/%m/%Y %H:%i") AS scheduled_at,
                        a.id AS assignment_id, u.name AS beneficiary_name
                 FROM support_appointments ap
                 JOIN support_assignments a ON a.id = ap.assignment_id
                 JOIN support_requests r ON r.id = a.request_id
                 JOIN users u ON u.id = r.beneficiary_id
                 WHERE a.supporter_id = ? AND ap.scheduled_at >= NOW() AND ap.status IN ("scheduled", "confirmed")
                 ORDER BY ap.scheduled_at ASC LIMIT 4'
            );
            $stmt->execute([$id]);
            $nextAppointments = $stmt->fetchAll();

            $stmt = $pdo->prepare(
                'SELECT r.id, r.category, DATE_FORMAT(r.created_at, "%d/%m/%Y %H:%i") AS created_at, u.name AS beneficiary_name
                 FROM support_requests r JOIN users u ON u.id = r.beneficiary_id
                 WHERE r.status IN ("pending", "triage") AND NOT EXISTS (SELECT 1 FROM support_assignments a WHERE a.request_id = r.id AND a.supporter_id = ?)
                 ORDER BY r.created_at DESC LIMIT 3'
            );
            $stmt->execute([$id]);
            $latestRequests = $stmt->fetchAll();
            foreach ($latestRequests as &$request) {
                $request['beneficiary_name'] = mask_name((string) $request['beneficiary_name']);
            }
            unset($request);

            $stmt = $pdo->prepare(
                'SELECT m.body, m.created_at, m.sender_role, u.name AS beneficiary_name, a.id AS assignment_id
                 FROM support_messages m
                 JOIN support_conversations c ON c.id = m.conversation_id
                 JOIN support_assignments a ON a.id = c.assignment_id
                 JOIN users u ON u.id = c.beneficiary_id
                 WHERE c.supporter_id = ? ORDER BY m.created_at DESC LIMIT 3'
            );
            $stmt->execute([$id]);
            $recentMessages = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT subject, message, status, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 4');
            $stmt->execute([$id]);
            $notifications = $stmt->fetchAll();

            json_response(true, '', [
                'state' => $state,
                'type_label' => supporter_type_label($state['type']),
                'counts' => ['available' => $available, 'active' => $active, 'completed' => $completed, 'upcoming' => $upcoming, 'unread' => $unread],
                'appointments' => $nextAppointments,
                'requests' => $latestRequests,
                'messages' => $recentMessages,
                'notifications' => $notifications,
            ]);

        case 'notifications':
            $user = require_supporter();
            $stmt = $pdo->prepare('SELECT id, channel, subject, message, status, whatsapp_url, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
            $stmt->execute([(int) $user['id']]);
            $rows = $stmt->fetchAll();
            $pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')->execute([(int) $user['id']]);
            json_response(true, '', ['notifications' => $rows]);

        /* ---------------------------------------------------------------- Solicitações recebidas */

        case 'requests_available':
            [$user] = guard_supporter_working($pdo);
            $id = (int) $user['id'];
            $stmt = $pdo->prepare(
                'SELECT r.id, r.category, LEFT(r.description, 220) AS preview, r.status,
                        DATE_FORMAT(r.created_at, "%d/%m/%Y %H:%i") AS created_at,
                        u.name AS beneficiary_name, u.address AS region,
                        EXISTS (SELECT 1 FROM support_assignments a WHERE a.request_id = r.id) AS taken
                 FROM support_requests r JOIN users u ON u.id = r.beneficiary_id
                 WHERE r.status IN ("pending", "triage")
                   AND NOT EXISTS (SELECT 1 FROM support_assignments a WHERE a.request_id = r.id AND a.supporter_id = ?)
                 ORDER BY r.created_at ASC'
            );
            $stmt->execute([$id]);
            $requests = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT s.name FROM supporter_specialties s WHERE s.supporter_id = ?');
            $stmt->execute([$id]);
            $specialties = array_map(static fn($row) => mb_strtolower((string) $row['name']), $stmt->fetchAll());

            foreach ($requests as &$request) {
                $request['beneficiary_name'] = mask_name((string) $request['beneficiary_name']);
                // A região é apresentada apenas como cidade/UF aproximada, nunca o endereço completo.
                $request['region'] = mask_region((string) ($request['region'] ?? ''));
                $category = mb_strtolower((string) $request['category']);
                $request['matches'] = $specialties && array_filter($specialties, static fn($item) => $item !== '' && (str_contains($category, $item) || str_contains($item, $category)));
                $request['matches'] = (bool) $request['matches'];
            }
            unset($request);

            $stmt = $pdo->prepare(
                'SELECT r.id, r.category, DATE_FORMAT(a.decided_at, "%d/%m/%Y %H:%i") AS decided_at, a.decline_reason
                 FROM support_assignments a JOIN support_requests r ON r.id = a.request_id
                 WHERE a.supporter_id = ? AND a.status = "declined" ORDER BY a.decided_at DESC LIMIT 20'
            );
            $stmt->execute([$id]);
            $declined = $stmt->fetchAll();

            json_response(true, '', ['requests' => $requests, 'declined' => $declined]);

        case 'request_detail':
            [$user] = guard_supporter_working($pdo);
            $id = (int) $user['id'];
            $requestId = (int) ($_POST['id'] ?? 0);

            $stmt = $pdo->prepare(
                'SELECT r.id, r.category, r.description, r.preferred_contact, r.status, DATE_FORMAT(r.created_at, "%d/%m/%Y %H:%i") AS created_at,
                        u.name AS beneficiary_name, u.address AS region
                 FROM support_requests r JOIN users u ON u.id = r.beneficiary_id WHERE r.id = ? LIMIT 1'
            );
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request) {
                json_response(false, 'Solicitação não encontrada.', [], 404);
            }

            $stmt = $pdo->prepare('SELECT id, status FROM support_assignments WHERE request_id = ? AND supporter_id = ? LIMIT 1');
            $stmt->execute([$requestId, $id]);
            $mine = $stmt->fetch();
            $authorized = $mine && in_array($mine['status'], ['accepted', 'in_progress', 'completed'], true);

            // Antes do aceite, apenas dados mínimos: sem nome completo, contato ou endereço.
            if (!$authorized) {
                $request['beneficiary_name'] = mask_name((string) $request['beneficiary_name']);
                $request['region'] = mask_region((string) $request['region']);
                $request['preferred_contact'] = null;
                $request['authorized'] = false;
                $takenStmt = $pdo->prepare('SELECT 1 FROM support_assignments WHERE request_id = ? LIMIT 1');
                $takenStmt->execute([$requestId]);
                $request['taken'] = (bool) $takenStmt->fetchColumn();
            } else {
                $request['authorized'] = true;
                $request['assignment_id'] = (int) $mine['id'];
                $request['taken'] = true;
            }
            log_audit($pdo, $id, 'supporter.request_viewed', 'support_request', (string) $requestId, $authorized ? 'Solicitação de atendimento autorizado consultada' : 'Solicitação disponível consultada (dados restritos)');
            json_response(true, '', ['request' => $request]);

        case 'request_accept':
            [$user, $state] = guard_supporter_working($pdo);
            $id = (int) $user['id'];
            $requestId = (int) ($_POST['id'] ?? 0);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT r.id, r.status, r.beneficiary_id, r.category, u.name AS beneficiary_name, u.email AS beneficiary_email FROM support_requests r JOIN users u ON u.id = r.beneficiary_id WHERE r.id = ? FOR UPDATE');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch();
            if (!$request) {
                $pdo->rollBack();
                json_response(false, 'Solicitação não encontrada.', [], 404);
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_assignments WHERE request_id = ? AND status IN ("accepted", "in_progress")');
            $stmt->execute([$requestId]);
            if ((int) $stmt->fetchColumn() > 0) {
                $pdo->rollBack();
                json_response(false, 'Esta solicitação já está sendo atendida por outro apoiador da rede.', [], 409);
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_assignments WHERE supporter_id = ? AND status IN ("accepted", "in_progress")');
            $stmt->execute([$id]);
            $limit = (int) ($state['profile']['max_active_cases'] ?? 5);
            if ((int) $stmt->fetchColumn() >= $limit) {
                $pdo->rollBack();
                json_response(false, 'Você atingiu o limite de ' . $limit . ' atendimentos simultâneos definido no seu perfil.', [], 409);
            }

            $pdo->prepare('INSERT INTO support_assignments (request_id, supporter_id, status, accepted_at) VALUES (?, ?, "accepted", NOW())')->execute([$requestId, $id]);
            $assignmentId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO support_assignment_history (assignment_id, actor_id, old_status, new_status, note) VALUES (?, ?, NULL, "accepted", "Atendimento aceito pelo apoiador")')
                ->execute([$assignmentId, $id]);
            $old = (string) $request['status'];
            if (in_array($old, ['pending', 'triage'], true)) {
                $pdo->prepare('UPDATE support_requests SET status = "forwarded" WHERE id = ?')->execute([$requestId]);
                $pdo->prepare('INSERT INTO support_request_history (request_id, actor_id, old_status, new_status, note) VALUES (?, ?, ?, "forwarded", ?)')
                    ->execute([$requestId, $id, $old, 'Atendimento aceito por ' . $state['profile']['display_name']]);
            }
            $pdo->prepare('INSERT INTO support_conversations (assignment_id, beneficiary_id, supporter_id) VALUES (?, ?, ?)')->execute([$assignmentId, (int) $request['beneficiary_id'], $id]);
            $conversationId = (int) $pdo->lastInsertId();
            $pdo->commit();

            log_audit($pdo, $id, 'supporter.assignment_accepted', 'support_request', (string) $requestId, 'Atendimento aceito (vínculo #' . $assignmentId . ')');
            notify_user($pdo, (int) $request['beneficiary_id'], (string) $request['beneficiary_email'], 'Um apoiador aceitou seu pedido de apoio',
                'Seu pedido de ' . $request['category'] . ' foi aceito por ' . $state['profile']['display_name'] . '. Você já pode conversar por mensagens e combinar o primeiro atendimento.');
            json_response(true, 'Solicitação aceita. Combine o atendimento pela conversa.', ['assignment_id' => $assignmentId, 'conversation_id' => $conversationId]);

        case 'request_decline':
            [$user] = guard_supporter_working($pdo);
            $id = (int) $user['id'];
            $requestId = (int) ($_POST['id'] ?? 0);
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($reason === '') {
                json_response(false, 'Registre brevemente o motivo da recusa.', [], 422);
            }
            $stmt = $pdo->prepare('SELECT id FROM support_requests WHERE id = ?');
            $stmt->execute([$requestId]);
            if (!$stmt->fetch()) {
                json_response(false, 'Solicitação não encontrada.', [], 404);
            }
            $pdo->prepare(
                'INSERT INTO support_assignments (request_id, supporter_id, status, decline_reason, decided_at) VALUES (?, ?, "declined", ?, NOW())
                 ON DUPLICATE KEY UPDATE status = "declined", decline_reason = VALUES(decline_reason), decided_at = NOW()'
            )->execute([$requestId, $id, $reason]);
            log_audit($pdo, $id, 'supporter.request_declined', 'support_request', (string) $requestId, 'Solicitação recusada');
            json_response(true, 'Solicitação recusada. Ela continua disponível para a rede.');

        /* ---------------------------------------------------------------- Atendimentos */

        case 'assignments':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $scope = trim((string) ($_POST['scope'] ?? 'active'));
            $where = $scope === 'history' ? 'a.status IN ("completed", "cancelled")' : 'a.status IN ("accepted", "in_progress")';
            $stmt = $pdo->prepare(
                "SELECT a.id, a.status, a.request_id, r.category, r.status AS request_status,
                        DATE_FORMAT(a.accepted_at, \"%d/%m/%Y %H:%i\") AS accepted_at,
                        DATE_FORMAT(a.closed_at, \"%d/%m/%Y %H:%i\") AS closed_at,
                        u.name AS beneficiary_name,
                        (SELECT COUNT(*) FROM support_messages m JOIN support_conversations c ON c.id = m.conversation_id WHERE c.assignment_id = a.id AND m.sender_role = 'beneficiary' AND m.read_at IS NULL) AS unread,
                        (SELECT DATE_FORMAT(ap.scheduled_at, \"%d/%m/%Y %H:%i\") FROM support_appointments ap WHERE ap.assignment_id = a.id AND ap.status IN ('scheduled','confirmed') ORDER BY ap.scheduled_at ASC LIMIT 1) AS next_appointment,
                        (SELECT COUNT(*) FROM support_appointments ap WHERE ap.assignment_id = a.id) AS appointments_total
                 FROM support_assignments a
                 JOIN support_requests r ON r.id = a.request_id
                 JOIN users u ON u.id = r.beneficiary_id
                 WHERE a.supporter_id = ? AND $where
                 ORDER BY a.updated_at DESC LIMIT 200"
            );
            $stmt->execute([$id]);
            json_response(true, '', ['assignments' => $stmt->fetchAll(), 'scope' => $scope]);

        case 'assignment_detail':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $assignmentId = (int) ($_POST['id'] ?? 0);
            $assignment = supporter_assignment($pdo, $assignmentId, $id);
            if (!$assignment) {
                json_response(false, 'Atendimento não encontrado entre os seus vínculos autorizados.', [], 404);
            }
            $stmt = $pdo->prepare('SELECT h.old_status, h.new_status, h.note, DATE_FORMAT(h.created_at, "%d/%m/%Y %H:%i") AS created_at, u.name AS actor FROM support_assignment_history h LEFT JOIN users u ON u.id = h.actor_id WHERE h.assignment_id = ? ORDER BY h.created_at DESC LIMIT 50');
            $stmt->execute([$assignmentId]);
            $history = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT id, kind, status, mode, location, notes, duration_minutes, DATE_FORMAT(scheduled_at, "%d/%m/%Y %H:%i") AS scheduled_at FROM support_appointments WHERE assignment_id = ? ORDER BY scheduled_at DESC');
            $stmt->execute([$assignmentId]);
            $appointments = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT id FROM support_conversations WHERE assignment_id = ? LIMIT 1');
            $stmt->execute([$assignmentId]);
            $conversationId = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM support_messages m WHERE m.conversation_id = ?');
            $stmt->execute([$conversationId]);
            $messageCount = (int) $stmt->fetchColumn();

            log_audit($pdo, $id, 'supporter.assignment_viewed', 'support_assignment', (string) $assignmentId, 'Detalhes do atendimento autorizado consultados');
            json_response(true, '', [
                'assignment' => $assignment,
                'history' => $history,
                'appointments' => $appointments,
                'conversation_id' => $conversationId,
                'message_count' => $messageCount,
            ]);

        case 'assignment_status':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $assignmentId = (int) ($_POST['id'] ?? 0);
            $next = trim((string) ($_POST['status'] ?? ''));
            $note = trim((string) ($_POST['note'] ?? ''));
            if (!in_array($next, ['accepted', 'in_progress', 'cancelled'], true)) {
                json_response(false, 'Status inválido. Use "em atendimento" ou "cancelado"; para encerrar use o encerramento do atendimento.', [], 422);
            }
            $assignment = supporter_assignment($pdo, $assignmentId, $id);
            if (!$assignment) {
                json_response(false, 'Atendimento não encontrado entre os seus vínculos autorizados.', [], 404);
            }
            if (in_array($assignment['status'], ['completed', 'cancelled'], true)) {
                json_response(false, 'Este atendimento já foi encerrado e o histórico foi preservado.', [], 409);
            }
            $old = (string) $assignment['status'];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE support_assignments SET status = ? WHERE id = ?')->execute([$next, $assignmentId]);
            $pdo->prepare('INSERT INTO support_assignment_history (assignment_id, actor_id, old_status, new_status, note) VALUES (?, ?, ?, ?, ?)')
                ->execute([$assignmentId, $id, $old, $next, $note ?: null]);
            $pdo->commit();
            log_audit($pdo, $id, 'supporter.assignment_status_changed', 'support_assignment', (string) $assignmentId, $old . ' → ' . $next . ($note ? '; nota registrada' : ''));
            json_response(true, 'Status do atendimento atualizado.');

        case 'assignment_close':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $assignmentId = (int) ($_POST['id'] ?? 0);
            $summary = trim((string) ($_POST['summary'] ?? ''));
            $outcome = trim((string) ($_POST['outcome'] ?? 'concluido'));
            if (mb_strlen($summary) < 20) {
                json_response(false, 'Descreva o resultado do atendimento com pelo menos 20 caracteres.', [], 422);
            }
            if (!in_array($outcome, ['concluido', 'encaminhado', 'interrompido'], true)) {
                $outcome = 'concluido';
            }
            $assignment = supporter_assignment($pdo, $assignmentId, $id);
            if (!$assignment) {
                json_response(false, 'Atendimento não encontrado entre os seus vínculos autorizados.', [], 404);
            }
            if (in_array($assignment['status'], ['completed', 'cancelled'], true)) {
                json_response(false, 'Este atendimento já foi encerrado.', [], 409);
            }
            $old = (string) $assignment['status'];
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE support_assignments SET status = "completed", closed_at = NOW(), closing_summary = ? WHERE id = ?')->execute([$summary, $assignmentId]);
            $pdo->prepare('INSERT INTO support_assignment_history (assignment_id, actor_id, old_status, new_status, note) VALUES (?, ?, ?, "completed", ?)')
                ->execute([$assignmentId, $id, $old, 'Encerramento (' . $outcome . '): ' . $summary]);
            $pdo->prepare('UPDATE support_appointments SET status = "cancelled" WHERE assignment_id = ? AND status IN ("scheduled", "confirmed")')->execute([$assignmentId]);
            $pdo->prepare('UPDATE support_conversations SET status = "closed" WHERE assignment_id = ?')->execute([$assignmentId]);
            $pdo->prepare('UPDATE support_requests SET status = "resolved" WHERE id = ?')->execute([(int) $assignment['request_id']]);
            $pdo->prepare('INSERT INTO support_request_history (request_id, actor_id, old_status, new_status, note) VALUES (?, ?, ?, "resolved", ?)')
                ->execute([(int) $assignment['request_id'], $id, (string) $assignment['request_status'], 'Atendimento encerrado pelo apoiador']);
            $pdo->commit();

            log_audit($pdo, $id, 'supporter.assignment_closed', 'support_assignment', (string) $assignmentId, 'Atendimento encerrado (' . $outcome . ')');
            notify_user($pdo, (int) $assignment['beneficiary_id'], (string) $assignment['beneficiary_email'], 'Atendimento encerrado no Lumen',
                'O atendimento do seu pedido de ' . $assignment['category'] . ' foi encerrado. Você continua podendo registrar novas solicitações, denúncias e usar o SOS.');
            notify_admins($pdo, 'Atendimento encerrado por apoiador', 'Um atendimento da rede foi encerrado e registrado na trilha de auditoria.');
            json_response(true, 'Atendimento encerrado. O histórico foi preservado para consulta.');

        /* ---------------------------------------------------------------- Agenda */

        case 'appointments':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $stmt = $pdo->prepare(
                'SELECT ap.id, ap.kind, ap.status, ap.mode, ap.location, ap.notes, ap.duration_minutes, ap.assignment_id,
                        DATE_FORMAT(ap.scheduled_at, "%d/%m/%Y %H:%i") AS scheduled_at,
                        DATE(ap.scheduled_at) AS scheduled_date, r.category, u.name AS beneficiary_name,
                        (ap.scheduled_at >= NOW() AND ap.status IN ("scheduled", "confirmed")) AS is_upcoming
                 FROM support_appointments ap
                 JOIN support_assignments a ON a.id = ap.assignment_id
                 JOIN support_requests r ON r.id = a.request_id
                 JOIN users u ON u.id = r.beneficiary_id
                 WHERE a.supporter_id = ?
                 ORDER BY ap.scheduled_at ASC LIMIT 300'
            );
            $stmt->execute([$id]);
            $rows = $stmt->fetchAll();
            $upcoming = array_values(array_filter($rows, static fn($row) => (int) $row['is_upcoming'] === 1));
            $past = array_values(array_filter($rows, static fn($row) => (int) $row['is_upcoming'] !== 1));

            $stmt = $pdo->prepare('SELECT id, off_date, reason FROM supporter_time_off WHERE supporter_id = ? AND off_date >= CURDATE() ORDER BY off_date LIMIT 30');
            $stmt->execute([$id]);
            json_response(true, '', ['upcoming' => $upcoming, 'past' => $past, 'time_off' => $stmt->fetchAll()]);

        case 'available_slots':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $date = trim((string) ($_POST['date'] ?? ''));
            $parsed = parse_local_datetime($date . ' 00:00');
            if (!$parsed) {
                json_response(false, 'Informe uma data válida.', [], 422);
            }
            $day = substr($parsed, 0, 10);
            $weekday = (int) date('w', strtotime($day));

            $stmt = $pdo->prepare('SELECT TIME_FORMAT(start_time, "%H:%i") AS start_time, TIME_FORMAT(end_time, "%H:%i") AS end_time, slot_minutes, mode FROM supporter_availability WHERE supporter_id = ? AND weekday = ? AND active = 1 ORDER BY start_time');
            $stmt->execute([$id, $weekday]);
            $rules = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT TIME_FORMAT(start_time, "%H:%i") AS start_time, TIME_FORMAT(end_time, "%H:%i") AS end_time, reason FROM supporter_time_off WHERE supporter_id = ? AND off_date = ?');
            $stmt->execute([$id, $day]);
            $blocks = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT DATE_FORMAT(ap.scheduled_at, "%H:%i") AS at, ap.duration_minutes FROM support_appointments ap JOIN support_assignments a ON a.id = ap.assignment_id WHERE a.supporter_id = ? AND DATE(ap.scheduled_at) = ? AND ap.status IN ("scheduled", "confirmed")');
            $stmt->execute([$id, $day]);
            $busy = $stmt->fetchAll();

            $slots = [];
            foreach ($rules as $rule) {
                $cursor = strtotime($day . ' ' . $rule['start_time']);
                $limit = strtotime($day . ' ' . $rule['end_time']);
                $step = max(15, (int) $rule['slot_minutes']) * 60;
                while ($cursor + $step <= $limit + 1) {
                    $time = date('H:i', $cursor);
                    $conflict = false;
                    foreach ($blocks as $block) {
                        $from = $block['start_time'] ?: '00:00';
                        $to = $block['end_time'] ?: '23:59';
                        if ($time >= $from && $time < $to) {
                            $conflict = true;
                        }
                    }
                    foreach ($busy as $item) {
                        if (abs(strtotime($day . ' ' . $item['at']) - $cursor) < max(15, (int) $item['duration_minutes']) * 60) {
                            $conflict = true;
                        }
                    }
                    if (!$conflict) {
                        $slots[] = ['time' => $time, 'mode' => $rule['mode']];
                    }
                    $cursor += $step;
                }
            }
            usort($slots, static fn($a, $b) => strcmp($a['time'], $b['time']));
            json_response(true, '', ['slots' => $slots, 'blocks' => $blocks, 'weekday' => weekday_label($weekday)]);

        case 'appointment_save':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            $appointmentId = (int) ($_POST['id'] ?? 0);
            $assignment = supporter_assignment($pdo, $assignmentId, $id);
            if (!$assignment) {
                json_response(false, 'Atendimento não encontrado entre os seus vínculos autorizados.', [], 404);
            }
            if (in_array($assignment['status'], ['completed', 'cancelled'], true)) {
                json_response(false, 'Não é possível agendar em um atendimento encerrado.', [], 409);
            }
            $kind = trim((string) ($_POST['kind'] ?? 'first'));
            if (!in_array($kind, ['first', 'return', 'follow_up'], true)) {
                $kind = 'first';
            }
            $scheduled = parse_local_datetime((string) ($_POST['scheduled_at'] ?? ''));
            if (!$scheduled) {
                json_response(false, 'Informe a data e o horário do atendimento.', [], 422);
            }
            if (strtotime($scheduled) < time() - 3600) {
                json_response(false, 'Escolha uma data e horário a partir de agora.', [], 422);
            }
            $mode = trim((string) ($_POST['mode'] ?? 'online'));
            if (!in_array($mode, ['online', 'presencial', 'hibrido'], true)) {
                $mode = 'online';
            }
            $duration = clamp_int($_POST['duration_minutes'] ?? 60, 15, 240, 60);
            $location = trim((string) ($_POST['location'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if ($appointmentId > 0) {
                $stmt = $pdo->prepare('SELECT id FROM support_appointments WHERE id = ? AND assignment_id = ? LIMIT 1');
                $stmt->execute([$appointmentId, $assignmentId]);
                if (!$stmt->fetch()) {
                    json_response(false, 'Agendamento não encontrado.', [], 404);
                }
                $pdo->prepare('UPDATE support_appointments SET kind = ?, scheduled_at = ?, duration_minutes = ?, mode = ?, location = ?, notes = ?, status = "scheduled" WHERE id = ?')
                    ->execute([$kind, $scheduled, $duration, $mode, $location ?: null, $notes ?: null, $appointmentId]);
                $message = 'Atendimento reagendado.';
            } else {
                $pdo->prepare('INSERT INTO support_appointments (assignment_id, created_by, kind, scheduled_at, duration_minutes, mode, location, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$assignmentId, $id, $kind, $scheduled, $duration, $mode, $location ?: null, $notes ?: null]);
                $appointmentId = (int) $pdo->lastInsertId();
                $message = 'Atendimento agendado.';
            }
            $pdo->prepare('INSERT INTO support_assignment_history (assignment_id, actor_id, old_status, new_status, note) VALUES (?, ?, NULL, ?, ?)')
                ->execute([$assignmentId, $id, 'scheduled', appointment_kind_label($kind) . ' em ' . date('d/m/Y H:i', strtotime($scheduled))]);

            log_audit($pdo, $id, 'supporter.appointment_saved', 'support_assignment', (string) $assignmentId, appointment_kind_label($kind) . ' agendado para ' . date('d/m/Y H:i', strtotime($scheduled)));
            notify_user($pdo, (int) $assignment['beneficiary_id'], (string) $assignment['beneficiary_email'], 'Novo agendamento no Lumen',
                appointment_kind_label($kind) . ' marcado para ' . date('d/m/Y H:i', strtotime($scheduled)) . ' (' . service_mode_label($mode) . '). ' . ($location ? 'Local: ' . $location . '. ' : '') . ($notes ? 'Observações: ' . $notes : ''));
            json_response(true, $message, ['id' => $appointmentId]);

        case 'appointment_update':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $appointmentId = (int) ($_POST['id'] ?? 0);
            $status = trim((string) ($_POST['status'] ?? ''));
            if (!in_array($status, ['scheduled', 'confirmed', 'done', 'missed', 'cancelled'], true)) {
                json_response(false, 'Status de agendamento inválido.', [], 422);
            }
            $stmt = $pdo->prepare(
                'SELECT ap.id, ap.assignment_id, ap.scheduled_at, ap.kind, r.beneficiary_id, u.email AS beneficiary_email
                 FROM support_appointments ap
                 JOIN support_assignments a ON a.id = ap.assignment_id
                 JOIN support_requests r ON r.id = a.request_id
                 JOIN users u ON u.id = r.beneficiary_id
                 WHERE ap.id = ? AND a.supporter_id = ? LIMIT 1'
            );
            $stmt->execute([$appointmentId, $id]);
            $appointment = $stmt->fetch();
            if (!$appointment) {
                json_response(false, 'Agendamento não encontrado entre os seus atendimentos.', [], 404);
            }
            $pdo->prepare('UPDATE support_appointments SET status = ? WHERE id = ?')->execute([$status, $appointmentId]);
            $labels = ['scheduled' => 'Agendado', 'confirmed' => 'Confirmado', 'done' => 'Realizado', 'missed' => 'Não compareceu', 'cancelled' => 'Cancelado'];
            $pdo->prepare('INSERT INTO support_assignment_history (assignment_id, actor_id, old_status, new_status, note) VALUES (?, ?, NULL, ?, ?)')
                ->execute([(int) $appointment['assignment_id'], $id, 'agenda_' . $status, appointment_kind_label((string) $appointment['kind']) . ': ' . $labels[$status]]);
            log_audit($pdo, $id, 'supporter.appointment_status_changed', 'support_assignment', (string) $appointment['assignment_id'], 'Agendamento #' . $appointmentId . ' → ' . $labels[$status]);
            notify_user($pdo, (int) $appointment['beneficiary_id'], (string) $appointment['beneficiary_email'], 'Atualização do seu agendamento',
                appointment_kind_label((string) $appointment['kind']) . ' de ' . date('d/m/Y H:i', strtotime((string) $appointment['scheduled_at'])) . ' agora está: ' . $labels[$status] . '.');
            json_response(true, 'Agendamento atualizado.');

        /* ---------------------------------------------------------------- Serviços oferecidos */

        case 'services_list':
            [$user] = guard_supporter_area($pdo);
            $stmt = $pdo->prepare('SELECT id, name, category, description, mode, duration_minutes, price_info, status, DATE_FORMAT(updated_at, "%d/%m/%Y %H:%i") AS updated_at FROM supporter_services WHERE supporter_id = ? ORDER BY status, name');
            $stmt->execute([(int) $user['id']]);
            json_response(true, '', ['services' => $stmt->fetchAll()]);

        case 'service_save':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $serviceId = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            if ($name === '' || $category === '') {
                json_response(false, 'Informe o nome do serviço e a categoria.', [], 422);
            }
            $mode = trim((string) ($_POST['mode'] ?? 'online'));
            if (!in_array($mode, ['online', 'presencial', 'hibrido'], true)) {
                $mode = 'online';
            }
            $values = [
                $name,
                $category,
                trim((string) ($_POST['description'] ?? '')) ?: null,
                $mode,
                clamp_int($_POST['duration_minutes'] ?? 60, 15, 480, 60),
                trim((string) ($_POST['price_info'] ?? '')) ?: null,
                in_array(trim((string) ($_POST['status'] ?? 'active')), ['active', 'paused'], true) ? trim((string) $_POST['status']) : 'active',
            ];
            if ($serviceId > 0) {
                if (!supporter_service_row($pdo, $serviceId, $id)) {
                    json_response(false, 'Serviço não encontrado.', [], 404);
                }
                $pdo->prepare('UPDATE supporter_services SET name = ?, category = ?, description = ?, mode = ?, duration_minutes = ?, price_info = ?, status = ? WHERE id = ? AND supporter_id = ?')
                    ->execute([...$values, $serviceId, $id]);
                $message = 'Serviço atualizado.';
                $action = 'supporter.service_updated';
            } else {
                $pdo->prepare('INSERT INTO supporter_services (supporter_id, name, category, description, mode, duration_minutes, price_info, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$id, ...$values]);
                $message = 'Serviço cadastrado.';
                $action = 'supporter.service_created';
            }
            log_audit($pdo, $id, $action, 'supporter', (string) $id, 'Serviço: ' . $name);
            json_response(true, $message);

        case 'service_delete':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $serviceId = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('DELETE FROM supporter_services WHERE id = ? AND supporter_id = ?')->execute([$serviceId, $id]);
            log_audit($pdo, $id, 'supporter.service_deleted', 'supporter', (string) $id, 'Serviço #' . $serviceId . ' removido');
            json_response(true, 'Serviço removido da sua lista.');

        case 'service_toggle':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $serviceId = (int) ($_POST['id'] ?? 0);
            $service = supporter_service_row($pdo, $serviceId, $id);
            if (!$service) {
                json_response(false, 'Serviço não encontrado.', [], 404);
            }
            $next = $service['status'] === 'active' ? 'paused' : 'active';
            $pdo->prepare('UPDATE supporter_services SET status = ? WHERE id = ? AND supporter_id = ?')->execute([$next, $serviceId, $id]);
            json_response(true, $next === 'active' ? 'Serviço reativado.' : 'Serviço pausado; ele não aparece mais na vitrine.');
        /* ---------------------------------------------------------------- Segurança e suporte */

        case 'security_overview':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM remember_tokens WHERE user_id = ? AND expires_at > NOW()');
            $stmt->execute([$id]);
            $remembered = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT action, details, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at, ip_address FROM audit_logs WHERE actor_id = ? ORDER BY created_at DESC LIMIT 25');
            $stmt->execute([$id]);
            $events = $stmt->fetchAll();

            $stmt = $pdo->prepare('SELECT DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at FROM audit_logs WHERE actor_id = ? AND action = "supporter.password_changed" ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([$id]);
            $lastPasswordChange = $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM supporter_risk_reports WHERE supporter_id = ? AND status <> "resolved"');
            $stmt->execute([$id]);
            $openRiskReports = (int) $stmt->fetchColumn();

            json_response(true, '', [
                'remembered_devices' => $remembered,
                'last_password_change' => $lastPasswordChange ?: null,
                'events' => $events,
                'open_risk_reports' => $openRiskReports,
            ]);

        case 'password_change':
            $user = require_supporter();
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
            if (strlen($new) < 10 || $new !== $confirmation) {
                json_response(false, 'A nova senha deve ter pelo menos 10 caracteres e as confirmações devem conferir.', [], 422);
            }
            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
            $hash = (string) $stmt->fetchColumn();
            if (!password_verify($current, $hash)) {
                json_response(false, 'A senha atual não confere.', [], 422);
            }
            if (password_verify($new, $hash)) {
                json_response(false, 'Escolha uma senha diferente da atual.', [], 422);
            }
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([(int) $user['id']]);
            clear_remember_me($pdo);
            log_audit($pdo, (int) $user['id'], 'supporter.password_changed', 'supporter', (string) $user['id'], 'Senha alterada e dispositivos lembrados revogados');
            json_response(true, 'Senha alterada. Os dispositivos com "lembrar de mim" foram desconectados.');

        case 'remember_revoke':
            $user = require_supporter();
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
            $stmt->execute([(int) $user['id']]);
            $removed = $stmt->rowCount();
            clear_remember_me($pdo);
            log_audit($pdo, (int) $user['id'], 'supporter.remember_revoked', 'supporter', (string) $user['id'], 'Dispositivos lembrados revogados');
            json_response(true, $removed > 0 ? 'Acessos lembrados revogados.' : 'Não havia dispositivos lembrados ativos.');

        case 'risk_report_create':
            [$user] = guard_supporter_area($pdo);
            $id = (int) $user['id'];
            $category = trim((string) ($_POST['category'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $urgency = trim((string) ($_POST['urgency'] ?? 'attention'));
            $assignmentId = (int) ($_POST['assignment_id'] ?? 0);
            if (!array_key_exists($category, ['risco_beneficiaria' => 1, 'risco_equipe' => 1, 'assedio' => 1, 'fraude' => 1, 'outro' => 1])) {
                json_response(false, 'Escolha o tipo de situação.', [], 422);
            }
            if (mb_strlen($description) < 20) {
                json_response(false, 'Descreva a situação com pelo menos 20 caracteres.', [], 422);
            }
            if (!in_array($urgency, ['imediata', 'attention', 'informativa'], true)) {
                $urgency = 'attention';
            }
            if ($assignmentId > 0 && !supporter_assignment($pdo, $assignmentId, $id)) {
                json_response(false, 'Atendimento não encontrado entre os seus vínculos.', [], 404);
            }
            $pdo->prepare('INSERT INTO supporter_risk_reports (supporter_id, assignment_id, category, description, urgency) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $assignmentId ?: null, $category, $description, $urgency]);
            $riskId = (int) $pdo->lastInsertId();
            log_audit($pdo, $id, 'supporter.risk_reported', 'risk_report', (string) $riskId, 'Situação reportada: ' . risk_category_label($category));
            notify_admins($pdo, 'Novo relato de risco ou abuso', 'Um apoiador registrou uma situação (' . risk_category_label($category) . '). Prioridade: ' . $urgency . '.');
            json_response(true, 'Relato enviado. A equipe Lumen vai analisar com prioridade e sigilo.');

        case 'risk_report_list':
            [$user] = guard_supporter_area($pdo);
            $stmt = $pdo->prepare('SELECT id, category, urgency, status, description, review_notes, DATE_FORMAT(created_at, "%d/%m/%Y %H:%i") AS created_at, DATE_FORMAT(reviewed_at, "%d/%m/%Y %H:%i") AS reviewed_at FROM supporter_risk_reports WHERE supporter_id = ? ORDER BY created_at DESC LIMIT 50');
            $stmt->execute([(int) $user['id']]);
            json_response(true, '', ['reports' => $stmt->fetchAll()]);

        default:
            json_response(false, 'Ação não reconhecida.', [], 404);
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Lumen][supporter] ' . $error->getMessage());
    json_response(false, APP_DEBUG ? 'Erro do servidor: ' . $error->getMessage() : 'Não foi possível concluir a ação. Tente novamente.', [], 500);
}
