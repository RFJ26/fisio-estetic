<?php
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
$user = $_ENV['DB_USER'] ?? getenv('DB_USER');
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS'); 
$db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
$port = ($_ENV['DB_PORT'] ?? getenv('DB_PORT')) ?: 11494;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Erro de Conexão: " . $e->getMessage());
    die("Erro técnico na base de dados.");
}