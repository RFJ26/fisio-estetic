<?php
// Configurações de Sessão para Vercel
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '1');
    session_start();
}

// Credenciais da Vercel (Environment Variables)
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');
$port = getenv('DB_PORT') ?: 11494;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Se a senha estiver vazia, a Vercel não está a ler o .env corretamente
    if (empty($pass)) {
        throw new Exception("Erro: Variável DB_PASSWORD não encontrada.");
    }

    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    die("Erro de ligação: " . $e->getMessage());
}