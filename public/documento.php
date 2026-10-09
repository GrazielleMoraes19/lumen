<?php
/**
 * LUMEN — download protegido de documentos enviados por apoiadores.
 * Somente o perfil administrador acessa o arquivo, e cada acesso é auditado.
 */

require_once __DIR__ . '/../includes/helpers.php';

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Acesso restrito à equipe responsável pela verificação.';
    exit;
}

$documentId = (int) ($_GET['id'] ?? 0);
$pdo = db();
$stmt = $pdo->prepare('SELECT d.id, d.kind, d.supporter_id, d.stored_name, d.original_name, d.mime_type FROM supporter_documents d WHERE d.id = ? LIMIT 1');
$stmt->execute([$documentId]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Documento não encontrado.';
    exit;
}

$path = UPLOAD_DIR . '/' . basename((string) $document['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Arquivo indisponível no armazenamento.';
    exit;
}

log_audit($pdo, (int) $user['id'], 'supporter.document_viewed', 'supporter', (string) $document['supporter_id'], 'Arquivo consultado: ' . document_kind_label((string) $document['kind']) . ' — ' . $document['original_name']);

header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode((string) $document['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
readfile($path);
