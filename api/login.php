<?php
// Primeiro carregamos a ligação ($conn)
require_once __DIR__ . '/../src/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit();
}

// AGORA sim, usamos o $conn que foi criado no ficheiro acima
$email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$password_md5 = md5($password);

// 1. LOGIN ADMIN/FUNCIONÁRIO
$query_func = "SELECT id, nome, palavra_passe, adm FROM funcionario WHERE email = '$email' LIMIT 1";
$result_func = mysqli_query($conn, $query_func);

if ($result_func && mysqli_num_rows($result_func) > 0) {
    $func = mysqli_fetch_assoc($result_func);

    if ($password_md5 === $func['palavra_passe']) {
        $_SESSION['nome'] = $func['nome'];
        $_SESSION['id']   = $func['id'];

        if ($func['adm'] == 1) {
            $_SESSION['id_admin'] = $func['id'];
            $redirect = '/adm/dashboard.php';
        } else {
            $_SESSION['id_worker'] = $func['id'];
            $redirect = '/worker/dashboard.php';
        }
        
        session_write_close(); // GRAVA A SESSÃO ANTES DE SAIR
        header("Location: $redirect");
        exit();
    }
}

// 2. LOGIN CLIENTE (Opcional, se tiveres esta tabela)
$query_cli = "SELECT id, nome, palavra_passe FROM cliente WHERE email = '$email' LIMIT 1";
$result_cli = mysqli_query($conn, $query_cli);
if ($result_cli && mysqli_num_rows($result_cli) > 0) {
    $cli = mysqli_fetch_assoc($result_cli);
    if ($password_md5 === $cli['palavra_passe']) {
        $_SESSION['id_cliente'] = $cli['id'];
        $_SESSION['nome'] = $cli['nome'];
        session_write_close();
        header('Location: /customer/dashboard.php');
        exit();
    }
}

// 3. FALHA
$_SESSION['nao_autenticado'] = true;
session_write_close();
header('Location: /');
exit();