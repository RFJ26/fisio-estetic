<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$caminho_atual = strtolower($_SERVER['SCRIPT_NAME']);

// 0. SE NÃO HOUVER NINGUÉM LOGADO -> Redireciona para a raiz
if (!isset($_SESSION['id_admin']) && !isset($_SESSION['id_worker']) && !isset($_SESSION['id_cliente'])) {
    // Na Vercel, o index.php na raiz da API é o seu site.com/
    header('Location: /index.php'); 
    exit();
}

// 1. REGRAS PARA O ADMINISTRADOR
if (isset($_SESSION['id_admin'])) {
    return; 
}

// 2. REGRAS PARA O FUNCIONÁRIO (Worker)
if (isset($_SESSION['id_worker'])) {
    if (strpos($caminho_atual, '/adm/') !== false || strpos($caminho_atual, '/customer/') !== false) {
        header('Location: /worker/dashboard.php'); // Caminho absoluto
        exit();
    }
    return;
}

// 3. REGRAS PARA O CLIENTE
if (isset($_SESSION['id_cliente'])) {
    $ficheiros_proibidos = ['list.php', 'create.php', 'edit.php', 'view.php'];
    $ficheiro_atual = basename($caminho_atual);

    if (strpos($caminho_atual, '/adm/') !== false || 
        strpos($caminho_atual, '/worker/') !== false || 
        strpos($caminho_atual, '/service/') !== false || 
        strpos($caminho_atual, '/service_category/') !== false || 
        in_array($ficheiro_atual, $ficheiros_proibidos)) {
        
        header('Location: /customer/dashboard.php'); // Caminho absoluto
        exit();
    }
}
?>