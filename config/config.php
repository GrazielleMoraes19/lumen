<?php
// Copie este projeto para o XAMPP e ajuste os dados abaixo se necessário.
date_default_timezone_set('America/Sao_Paulo');

const DB_HOST = '127.0.0.1';
const DB_NAME = 'lumen';
const DB_USER = 'root';
const DB_PASS = '';
const OLLAMA_URL = 'http://127.0.0.1:11434/api/chat';
const OLLAMA_MODEL = 'qwen2.5:3b';

// URL pública da pasta public. Em um XAMPP padrão: http://localhost/lumen/public
const APP_URL = 'http://localhost/lumen/public';
const APP_NAME = 'Lumen';
const APP_DEBUG = true;
const MAX_UPLOAD_BYTES = 10485760; // 10 MB
const UPLOAD_DIR = __DIR__ . '/../storage/uploads';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('lumen_session');
    // Mantém os dados de sessão no servidor pelo prazo máximo do token remember-me.
    ini_set('session.gc_maxlifetime', '1209600');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
