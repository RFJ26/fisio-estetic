<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$caminho_atual = strtolower($_SERVER['SCRIPT_NAME']);

// Se não houver ninguém logado, manda para a raiz absoluta
if (!isset($_SESSION['id_admin']) && !isset($_SESSION['id_worker']) && !isset($_SESSION['id_cliente'])) {
    header('Location: /index.php'); 
    exit();
}

// Regras de bloqueio por cargo
if (isset($_SESSION['id_admin'])) {
    return; 
}

if (isset($_SESSION['id_worker'])) {
    if (strpos($caminho_atual, '/adm/') !== false || strpos($caminho_atual, '/customer/') !== false) {
        header('Location: /worker/dashboard.php');
        exit();
    }
    return;
}

if (isset($_SESSION['id_cliente'])) {
    $ficheiros_proibidos = ['list.php', 'create.php', 'edit.php', 'view.php'];
    $ficheiro_atual = basename($caminho_atual);

    if (strpos($caminho_atual, '/adm/') !== false || 
        strpos($caminho_atual, '/worker/') !== false || 
        strpos($caminho_atual, '/service/') !== false || 
        strpos($caminho_atual, '/service_category/') !== false || 
        in_array($ficheiro_atual, $ficheiros_proibidos)) {
        
        header('Location: /customer/dashboard.php');
        exit();
    }
}