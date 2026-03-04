<?php
require_once __DIR__ . '/../src/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php'); 
    exit();
}

$email = mysqli_real_escape_string($conn, trim($_POST['email']));
$password = mysqli_real_escape_string($conn, $_POST['password']);
$password_md5 = md5($password);

// 1. TENTAR LOGIN COMO CLIENTE
$query_cli = "SELECT id, nome, palavra_passe, email_verificado FROM cliente WHERE email = '$email' LIMIT 1";
$result_cli = mysqli_query($conn, $query_cli);

if ($result_cli && mysqli_num_rows($result_cli) > 0) {
    $cliente = mysqli_fetch_assoc($result_cli);

    if ($password_md5 === $cliente['palavra_passe']) {
        if ($cliente['email_verificado'] == 0) {
            $_SESSION['email_nao_validado'] = true; 
            session_write_close();
            header('Location: /index.php'); 
            exit();
        }
        
        $_SESSION['id_cliente'] = $cliente['id']; 
        $_SESSION['nome'] = $cliente['nome'];
        
        session_write_close(); // IMPORTANTE: Grava antes de redirecionar
        header('Location: /customer/dashboard.php'); 
        exit();
    }
}

// 2. TENTAR LOGIN COMO FUNCIONÁRIO/ADM
$query_func = "SELECT id, nome, palavra_passe, adm FROM funcionario WHERE email = '$email' LIMIT 1";
$result_func = mysqli_query($conn, $query_func);

if ($result_func && mysqli_num_rows($result_func) > 0) {
    $func = mysqli_fetch_assoc($result_func);

    if ($password_md5 === $func['palavra_passe']) {
        if ($func['adm'] == 1) {
            $_SESSION['id_admin'] = $func['id'];
            header('Location: /adm/dashboard.php');
        } else {
            $_SESSION['id_worker'] = $func['id'];
            header('Location: /worker/dashboard.php');
        }
        session_write_close();
        exit();
    }
}

// 3. FALHA NO LOGIN
$_SESSION['nao_autenticado'] = true; 
session_write_close();
header('Location: /index.php'); 
exit();