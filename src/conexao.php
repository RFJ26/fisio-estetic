<?php
// 1. Configurações de Sessão para Vercel (Serverless)
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Dados da Conexão (Lendo das Environment Variables da Vercel)
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$db   = getenv('DB_NAME');
$port = getenv('DB_PORT') ?: 11494;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Importante: Se $pass estiver vazio, a Vercel não leu a variável de ambiente
    if (empty($pass)) {
        throw new Exception("Senha do banco de dados não encontrada nas variáveis de ambiente.");
    }

    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // Erro detalhado para diagnóstico (podes remover o $e->getMessage() depois de funcionar)
    die("Erro de ligação: " . $e->getMessage());
}