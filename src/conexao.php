<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '1');
    session_start();
}

// Credenciais atualizadas com DB_PASS
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS'); 
$db   = getenv('DB_NAME');
$port = getenv('DB_PORT') ?: 11494;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db, $port );
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Erro de Conexão: " . $e->getMessage());
    die("Erro técnico na base de dados. Verifique as variáveis de ambiente.");
}