<?php
// Ativar reporte de erros do mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');
$port = getenv('DB_PORT');

try {
    // Tenta a ligação
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // ISTO VAI MOSTRAR O ERRO REAL NO TEU BROWSER
    die("ERRO DE LIGAÇÃO REAL: " . $e->getMessage());
}