<?php
require_once __DIR__ . '/../src/conexao.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode([
    'status' => 'ok',
    'time' => date('c'),
]);
