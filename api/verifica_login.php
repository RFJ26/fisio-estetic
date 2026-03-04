<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se existe alguma sessão ativa
if (!isset($_SESSION['id']) && !isset($_SESSION['id_cliente']) && !isset($_SESSION['id_admin'])) {
    header('Location: /index.php'); 
    exit();
}