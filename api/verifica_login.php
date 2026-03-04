<?php
// 1. Se não tem cookie nenhum, vai direto para o ecrã de login
if (!isset($_COOKIE['id']) || !isset($_COOKIE['role'])) {
    header('Location: /index.php');
    exit();
}

$url_atual = $_SERVER['REQUEST_URI'];
$role = $_COOKIE['role'];

// ==========================================
// 2. REGRAS DE ACESSO POR CARGO
// ==========================================

// -> Se o cargo for 'admin', ele salta todas estas regras e tem ACESSO LIVRE a todas as pastas!

// Regras para o FUNCIONÁRIO (Worker)
if ($role === 'worker') {
    // O funcionário só é bloqueado se tentar entrar na pasta do Admin
    if (strpos($url_atual, '/adm/') !== false) {
        header('Location: /worker/dashboard.php');
        exit();
    }
}

// Regras para o CLIENTE (Customer)
if ($role === 'customer') {
    // O cliente é muito restrito. SÓ pode andar dentro da pasta /customer/ ou no /logout.php
    // Se tentar ir para qualquer outro sítio (ex: /worker/list.php), é recambiado para o seu dashboard.
    if (strpos($url_atual, '/customer/') === false && strpos($url_atual, '/logout.php') === false) {
        header('Location: /customer/dashboard.php');
        exit();
    }
}
?>