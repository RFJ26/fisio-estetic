<?php
// Carrega a ligação à base de dados (e a sessão configurada no conexao.php)
require_once __DIR__ . '/../src/conexao.php'; 

// ==========================================================
// LIMPEZA DE SEGURANÇA E VALIDAÇÃO INICIAL
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /'); 
    exit();
}

if (empty($_POST['email']) || empty($_POST['password'])) {
    $_SESSION['nao_autenticado'] = true;
    session_write_close(); // Obriga a gravar a sessão antes do redirecionamento
    header('Location: /'); 
    exit();
}

// Proteção contra SQL Injection
$email = mysqli_real_escape_string($conn, trim($_POST['email']));
$password = mysqli_real_escape_string($conn, $_POST['password']);
$password_md5 = md5($password);

// ---------------------------------------------------------
// 1. TENTAR LOGIN COMO CLIENTE
// ---------------------------------------------------------
$query_cli = "SELECT id, nome, palavra_passe, email_verificado FROM cliente WHERE email = '$email' LIMIT 1";
$result_cli = mysqli_query($conn, $query_cli);

if ($result_cli && mysqli_num_rows($result_cli) > 0) {
    $cliente = mysqli_fetch_assoc($result_cli);

    // Verifica se a password em MD5 coincide
    if ($password_md5 === $cliente['palavra_passe']) {
        
        // Verifica se o email está validado
        if ($cliente['email_verificado'] == 0) {
            $_SESSION['email_nao_validado'] = true; 
            session_write_close(); 
            header('Location: /'); 
            exit();
        }
        
        // Cria as variáveis de sessão para o cliente
        $_SESSION['email_cliente'] = $email;
        $_SESSION['nome_cliente']  = $cliente['nome'];
        $_SESSION['id_cliente']    = $cliente['id']; 
        $_SESSION['id']            = $cliente['id'];
        $_SESSION['nome']          = $cliente['nome'];
        
        session_write_close(); // O SEGREDO PARA A VERCEL: Grava a sessão agora!
        header('Location: /customer/dashboard.php'); 
        exit();
    }
}

// ---------------------------------------------------------
// 2. TENTAR LOGIN COMO FUNCIONÁRIO OU ADM
// ---------------------------------------------------------
$query_func = "SELECT id, nome, palavra_passe, adm FROM funcionario WHERE email = '$email' LIMIT 1";
$result_func = mysqli_query($conn, $query_func);

if ($result_func && mysqli_num_rows($result_func) > 0) {
    $func = mysqli_fetch_assoc($result_func);

    // Verifica se a password em MD5 coincide
    if ($password_md5 === $func['palavra_passe']) {
        
        // Variáveis genéricas
        $_SESSION['id']   = $func['id'];
        $_SESSION['nome'] = $func['nome'];
        
        if ($func['adm'] == 1) {
            $_SESSION['email_admin'] = $email;
            $_SESSION['nome_admin']  = $func['nome'];
            $_SESSION['id_admin']    = $func['id'];
            
            session_write_close();
            header('Location: /adm/dashboard.php'); 
        } else {
            $_SESSION['email_worker'] = $email;
            $_SESSION['nome_worker']  = $func['nome'];
            $_SESSION['id_worker']    = $func['id'];
            
            session_write_close();
            header('Location: /worker/dashboard.php'); 
        }
        exit(); // Finaliza a execução após o redirecionamento
    }
}

// ---------------------------------------------------------
// 3. FALHA NO LOGIN (Password errada ou email não existe)
// ---------------------------------------------------------
$_SESSION['nao_autenticado'] = true; 
session_write_close(); 
header('Location: /'); 
exit();
?>