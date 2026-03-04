<?php
// 1. Verifica se o Cookie principal existe
if (!isset($_COOKIE['id'])) {
    die("<h1>ERRO: O Vercel não guardou o Cookie!</h1><p>O login foi feito, mas o cookie 'id' desapareceu pelo caminho.</p>");
}

// 2. Verifica a Role (Cargo) e o URL
$url_atual = $_SERVER['REQUEST_URI'];

if (strpos($url_atual, '/adm/') !== false) {
    if (!isset($_COOKIE['role']) || $_COOKIE['role'] !== 'admin') {
        $cargo_atual = $_COOKIE['role'] ?? 'Nenhum cookie de role';
        die("<h1>ERRO DE ACESSO</h1><p>Tentaste aceder a uma pasta de Admin, mas o teu cargo registado é: <b>$cargo_atual</b></p>");
    }
}

if (strpos($url_atual, '/worker/') !== false) {
    if (!isset($_COOKIE['role']) || $_COOKIE['role'] !== 'worker') {
        $cargo_atual = $_COOKIE['role'] ?? 'Nenhum cookie de role';
        die("<h1>ERRO DE ACESSO</h1><p>Tentaste aceder a uma pasta de Funcionário, mas o teu cargo registado é: <b>$cargo_atual</b></p>");
    }
}
?>