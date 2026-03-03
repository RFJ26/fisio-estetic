<?php
session_start();
include('../src/conexao.php'); // Confirma se o caminho para a tua base de dados está correto

// ==========================================================
// LIMPEZA DE SEGURANÇA E VALIDAÇÃO INICIAL
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// Verifica se os campos estão vazios
if (empty($_POST['email']) || empty($_POST['password'])) {
    $_SESSION['nao_autenticado'] = true;
    header('Location: index.php');
    exit();
}

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

    // Verifica se a password coincide
    if ($password_md5 === $cliente['palavra_passe']) {
        
        // --- NOVA BARREIRA: VERIFICAÇÃO DE EMAIL ---
        if ($cliente['email_verificado'] == 0) {
            $_SESSION['email_nao_validado'] = true; // Aciona o aviso amarelo no index.php
            header('Location: index.php');
            exit();
        }
        
        // Se estiver validado, cria as variáveis de sessão para o cliente
        $_SESSION['email_cliente'] = $email;
        $_SESSION['nome_cliente']  = $cliente['nome'];
        $_SESSION['id_cliente']    = $cliente['id']; 
        
        // Variáveis genéricas
        $_SESSION['id']            = $cliente['id'];
        $_SESSION['nome']          = $cliente['nome'];
        
        // Redireciona para a área do cliente
        header('Location: customer/dashboard.php');
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

    // Verifica se a password coincide
    if ($password_md5 === $func['palavra_passe']) {
        
        // Variáveis genéricas
        $_SESSION['id']            = $func['id'];
        $_SESSION['nome']          = $func['nome'];
        
        // Verifica se é Administrador (adm = 1) ou Funcionário normal (adm = 0)
        if ($func['adm'] == 1) {
            $_SESSION['email_admin'] = $email;
            $_SESSION['nome_admin']  = $func['nome'];
            $_SESSION['id_admin']    = $func['id'];
            header('Location: adm/dashboard.php');
        } else {
            $_SESSION['email_worker'] = $email;
            $_SESSION['nome_worker']  = $func['nome'];
            $_SESSION['id_worker']    = $func['id'];
            header('Location: worker/dashboard.php');
        }
        exit();
    }
}

// ---------------------------------------------------------
// 3. FALHA NO LOGIN (Password errada ou email não existe)
// ---------------------------------------------------------
$_SESSION['nao_autenticado'] = true; // Aciona o aviso vermelho no index.php
header('Location: index.php');
exit();
?>