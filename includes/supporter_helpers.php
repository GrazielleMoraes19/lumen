<?php
/**
 * LUMEN — apoio à área do apoiador / profissional.
 *
 * Concentra rótulos, regras de verificação, mascaramento de dados pessoais e
 * registro de auditoria usados pelo painel do apoiador e pela revisão da equipe.
 */

const SUPPORTER_ROLES = ['supporter', 'professional'];

/** Tipos de perfil apoiados. Cada tipo tem campos e exigências próprias. */
function supporter_types(): array
{
    return [
        'professional' => [
            'label' => 'Profissional autônomo',
            'summary' => 'Atendimento individual com registro profissional (CRM, CRP, OAB, CREF e similares).',
            'requires_council' => true,
            'requires_legal_name' => false,
            'max_cases_hint' => 'Até 10 atendimentos simultâneos',
        ],
        'institution' => [
            'label' => 'Instituição / ONG',
            'summary' => 'Organização com equipe própria, CNPJ e pessoa responsável pelo cadastro.',
            'requires_council' => false,
            'requires_legal_name' => true,
            'max_cases_hint' => 'Sem limite fixo de atendimentos',
        ],
        'volunteer' => [
            'label' => 'Voluntário(a)',
            'summary' => 'Apoio voluntário sem registro profissional, com escopo de atuação supervisionado.',
            'requires_council' => false,
            'requires_legal_name' => false,
            'max_cases_hint' => 'Até 3 atendimentos simultâneos',
        ],
    ];
}

function supporter_type_label(string $type): string
{
    return supporter_types()[$type]['label'] ?? 'Apoiador(a)';
}

function supporter_type_summary(string $type): string
{
    return supporter_types()[$type]['summary'] ?? '';
}

/** Documentos exigidos para a verificação, conforme o tipo de perfil. */
function supporter_required_documents(string $type): array
{
    $base = ['identity' => 'Documento de identidade (RG ou CNH)'];
    if ($type === 'institution') {
        return $base + [
            'cnpj' => 'Cartão CNPJ ou estatuto da instituição',
            'proof_of_address' => 'Comprovante de endereço da sede',
        ];
    }
    if ($type === 'professional') {
        return $base + [
            'professional_license' => 'Comprovante de registro no conselho profissional',
            'proof_of_address' => 'Comprovante de endereço',
        ];
    }
    return $base + ['proof_of_address' => 'Comprovante de endereço'];
}

function document_kind_label(string $kind): string
{
    return [
        'identity' => 'Documento de identidade',
        'professional_license' => 'Registro profissional',
        'cnpj' => 'Cartão CNPJ / estatuto',
        'proof_of_address' => 'Comprovante de endereço',
        'other' => 'Documento complementar',
    ][$kind] ?? 'Documento';
}

function verification_badge(string $status): array
{
    return match ($status) {
        'approved' => ['Verificado', 'green'],
        'rejected' => ['Não aprovado', 'pink'],
        'suspended' => ['Suspenso', 'red'],
        default => ['Em análise', 'yellow'],
    };
}

function document_badge(string $status): array
{
    return match ($status) {
        'approved' => ['Aprovado', 'green'],
        'rejected' => ['Recusado', 'pink'],
        default => ['Aguardando revisão', 'yellow'],
    };
}

function assignment_badge(string $status): array
{
    return match ($status) {
        'accepted' => ['Aceito', 'yellow'],
        'in_progress' => ['Em atendimento', 'green'],
        'completed' => ['Concluído', 'green'],
        'cancelled' => ['Cancelado', 'pink'],
        'declined' => ['Recusado', 'pink'],
        default => ['Registrado', 'gray'],
    };
}

function appointment_badge(string $status): array
{
    return match ($status) {
        'confirmed' => ['Confirmado', 'green'],
        'done' => ['Realizado', 'green'],
        'missed' => ['Não compareceu', 'pink'],
        'cancelled' => ['Cancelado', 'pink'],
        default => ['Agendado', 'yellow'],
    };
}

function appointment_kind_label(string $kind): string
{
    return ['first' => 'Primeiro atendimento', 'return' => 'Retorno', 'follow_up' => 'Acompanhamento'][$kind] ?? 'Atendimento';
}

function service_mode_label(string $mode): string
{
    return ['online' => 'Online', 'presencial' => 'Presencial', 'hibrido' => 'Híbrido'][$mode] ?? 'A definir';
}

function weekday_label(int $weekday): string
{
    return ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'][$weekday] ?? '—';
}

function risk_category_label(string $category): string
{
    return [
        'risco_beneficiaria' => 'Risco à beneficiária',
        'risco_equipe' => 'Risco à equipe ou ao apoiador',
        'assedio' => 'Assédio ou conduta inadequada',
        'fraude' => 'Fraude ou uso indevido da plataforma',
        'outro' => 'Outra situação',
    ][$category] ?? 'Situação reportada';
}

/** Mascaramento usado antes do aceite: evita expor dados pessoais de quem não é atendida(o) pelo apoiador. */
/**
 * Devolve apenas a região aproximada (cidade/UF) de um endereço, para que o apoiador
 * veja o suficiente para decidir sem receber dados de localização da beneficiária.
 */
function mask_region(?string $address): string
{
    $address = trim((string) $address);
    if ($address === '') {
        return 'Não informada';
    }
    $parts = preg_split('/\s+-\s+|,\s*/', $address) ?: [];
    $region = trim((string) end($parts));
    if ($region === '' || preg_match('/^(rua|av|avenida|alameda|travessa|estrada|rodovia|pra[cç]a|viela|beco|largo)\b/iu', $region)) {
        return 'Não informada';
    }
    return $region;
}

function mask_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? 'Beneficiária';
    $lastInitial = '';
    if (count($parts) > 1) {
        $last = $parts[count($parts) - 1];
        $lastInitial = ' ' . strtoupper(function_exists('mb_substr') ? mb_substr($last, 0, 1) : substr($last, 0, 1)) . '.';
    }
    return $first . $lastInitial;
}

function is_supporter_role(?string $role): bool
{
    return in_array((string) $role, SUPPORTER_ROLES, true);
}

function require_supporter(): array
{
    return require_roles(SUPPORTER_ROLES);
}

function supporter_profile_row(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM supporter_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Situação da área do apoiador: perfil, verificação e pendências que impedem o atendimento.
 */
function supporter_area_state(PDO $pdo, array $user): array
{
    $profile = supporter_profile_row($pdo, (int) $user['id']);
    if (!$profile) {
        return ['profile' => null, 'type' => 'professional', 'verification' => 'pending', 'can_work' => false, 'pending_documents' => supporter_required_documents('professional'), 'missing' => ['Perfil profissional não cadastrado']];
    }
    $type = (string) $profile['supporter_type'];
    $required = supporter_required_documents($type);
    $stmt = $pdo->prepare('SELECT kind, status FROM supporter_documents WHERE supporter_id = ?');
    $stmt->execute([(int) $user['id']]);
    $byKind = [];
    foreach ($stmt->fetchAll() as $doc) {
        $byKind[$doc['kind']] = $doc['status'];
    }
    $pendingDocuments = [];
    foreach ($required as $kind => $label) {
        if (!isset($byKind[$kind]) || $byKind[$kind] !== 'approved') {
            $pendingDocuments[$kind] = $label;
        }
    }
    $missing = [];
    if (trim((string) $profile['document_number']) === '') {
        $missing[] = 'Documento principal não informado';
    }
    if (supporter_types()[$type]['requires_council'] && trim((string) $profile['council_number']) === '') {
        $missing[] = 'Registro no conselho profissional não informado';
    }
    if (supporter_types()[$type]['requires_legal_name'] && trim((string) $profile['legal_name']) === '') {
        $missing[] = 'Razão social da instituição não informada';
    }
    if (trim((string) $profile['city']) === '') {
        $missing[] = 'Cidade de atendimento não informada';
    }
    $verification = (string) $profile['verification_status'];
    // A verificação aprovada é o que autoriza o atendimento; documentos pendentes
    // continuam visíveis como pendência a regularizar, sem bloquear quem já foi aprovado.
    return [
        'profile' => $profile,
        'type' => $type,
        'verification' => $verification,
        'can_work' => $verification === 'approved',
        'pending_documents' => $pendingDocuments,
        'missing' => $missing,
    ];
}

/** Registro de alterações importantes (item "registrar alterações importantes"). */
function log_audit(PDO $pdo, ?int $actorId, string $action, string $entityType, ?string $entityId = null, string $details = ''): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, $entityType, $entityId, $details !== '' ? $details : null, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $error) {
        error_log('[Lumen][audit] ' . $error->getMessage());
    }
}

/** Notifica uma pessoa específica por e-mail e registra na caixa de notificações dela. */
function notify_user(PDO $pdo, int $userId, ?string $email, string $subject, string $message): void
{
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        queue_notification($pdo, $userId, $email, 'email', $subject, $message);
    }
    // Aviso interno sempre registrado, mesmo sem SMTP configurado.
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, recipient, channel, subject, message, status, created_at) VALUES (?, ?, "interno", ?, ?, "sent", NOW())');
    $stmt->execute([$userId, $email ?: ('usuario-' . $userId), $subject, $message]);
}

/**
 * Confere se o apoiador pode acessar um atendimento autorizado.
 * Retorna o vínculo com os dados da solicitação e da beneficiária.
 */
function supporter_assignment(PDO $pdo, int $assignmentId, int $supporterId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT a.*, r.beneficiary_id, r.category, r.description, r.preferred_contact, r.status AS request_status,
                DATE_FORMAT(r.created_at, "%d/%m/%Y %H:%i") AS request_created_at,
                u.name AS beneficiary_name, u.email AS beneficiary_email, u.phone AS beneficiary_phone,
                DATE_FORMAT(a.accepted_at, "%d/%m/%Y %H:%i") AS accepted_at_label
         FROM support_assignments a
         JOIN support_requests r ON r.id = a.request_id
         JOIN users u ON u.id = r.beneficiary_id
         WHERE a.id = ? AND a.supporter_id = ? LIMIT 1'
    );
    $stmt->execute([$assignmentId, $supporterId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Serviços oferecidos pelo próprio apoiador. */
function supporter_service_row(PDO $pdo, int $serviceId, int $supporterId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM supporter_services WHERE id = ? AND supporter_id = ? LIMIT 1');
    $stmt->execute([$serviceId, $supporterId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
