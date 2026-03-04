<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$caminho_atual = strtolower($_SERVER['SCRIPT_NAME']);

// ==========================================================
// 0. SE NÃO HOUVER NINGUÉM LOGADO -> EXPULSA PARA O LOGIN
// ==========================================================
if (!isset($_SESSION['id_admin']) && !isset($_SESSION['id_worker']) && !isset($_SESSION['id_cliente'])) {
    header('Location: ../index.php');
    exit();
}

// ==========================================================
// 1. REGRAS PARA O ADMINISTRADOR (Passe-Livre)
// ==========================================================
if (isset($_SESSION['id_admin'])) {
    // O Administrador tem acesso a todas as pastas e ficheiros.
    // O return aprova o acesso e ignora o resto do código abaixo.
    return; 
}

// ==========================================================
// 2. REGRAS PARA O FUNCIONÁRIO (Worker)
// ==========================================================
if (isset($_SESSION['id_worker'])) {
    // Bloqueia acesso à pasta do administrador e dos clientes
    if (strpos($caminho_atual, '/adm/') !== false || strpos($caminho_atual, '/customer/') !== false) {
        header('Location: ../worker/dashboard.php');
        exit();
    }
    return;
}

// ==========================================================
// 3. REGRAS PARA O CLIENTE
// ==========================================================
if (isset($_SESSION['id_cliente'])) {
    
    // Ficheiros de gestão do Administrador que estão na pasta /customer/
    $ficheiros_proibidos = ['list.php', 'create.php', 'edit.php', 'view.php'];
    $ficheiro_atual = basename($caminho_atual);

    // Bloqueia as pastas de gestão e os ficheiros proibidos listados acima
    if (strpos($caminho_atual, '/adm/') !== false || 
        strpos($caminho_atual, '/worker/') !== false || 
        strpos($caminho_atual, '/service/') !== false || 
        strpos($caminho_atual, '/service_category/') !== false || 
        in_array($ficheiro_atual, $ficheiros_proibidos)) {
        
        header('Location: ../customer/dashboard.php');
        exit();
    }
}
?>