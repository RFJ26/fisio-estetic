<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se não houver ID de sessão, expulsa
if (!isset($_SESSION['id']) && !isset($_SESSION['id_cliente'])) {
    header('Location: /index.php');
    exit();
}