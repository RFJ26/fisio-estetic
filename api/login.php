<?php
require_once __DIR__ . '/../src/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit();
}

$email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$password_md5 = md5($password);

// 1. Tentar Funcionário/ADM
$query_func = "SELECT id, nome, palavra_passe, adm FROM funcionario WHERE email = '$email' LIMIT 1";
$res_func = mysqli_query($conn, $query_func);

if ($res_func && mysqli_num_rows($res_func) > 0) {
    $user = mysqli_fetch_assoc($res_func);
    if ($password_md5 === $user['palavra_passe']) {
        $_SESSION['id']   = $user['id'];
        $_SESSION['nome'] = $user['nome'];

        if ($user['adm'] == 1) {
            $_SESSION['id_admin'] = $user['id'];
            $url = '/adm/dashboard.php';
        } else {
            $_SESSION['id_worker'] = $user['id'];
            $url = '/worker/dashboard.php';
        }
        session_write_close(); // GRAVA ANTES DE REDIRECIONAR
        header("Location: $url");
        exit();
    }
}

// 2. Tentar Cliente
$query_cli = "SELECT id, nome, palavra_passe FROM cliente WHERE email = '$email' LIMIT 1";
$res_cli = mysqli_query($conn, $query_cli);
if ($res_cli && mysqli_num_rows($res_cli) > 0) {
    $cli = mysqli_fetch_assoc($res_cli);
    if ($password_md5 === $cli['palavra_passe']) {
        $_SESSION['id_cliente'] = $cli['id'];
        $_SESSION['nome'] = $cli['nome'];
        session_write_close();
        header('Location: /customer/dashboard.php');
        exit();
    }
}

// 3. Falha
$_SESSION['nao_autenticado'] = true;
session_write_close();
header('Location: /');
exit();