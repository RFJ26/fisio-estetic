<?php
require_once __DIR__ . '/../src/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit();
}

// Agora o $conn já existe e está ligado via DB_PASS
$email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$password_md5 = md5($password);

// Tentar Funcionário/ADM
$query_func = "SELECT id, nome, palavra_passe, adm FROM funcionario WHERE email = '$email' LIMIT 1";
$res_func = mysqli_query($conn, $query_func);

if ($res_func && mysqli_num_rows($res_func) > 0) {
    $user = mysqli_fetch_assoc($res_func);
    if ($password_md5 === $user['palavra_passe']) {
        $_SESSION['id'] = $user['id'];
        $_SESSION['nome'] = $user['nome'];

        if ($user['adm'] == 1) {
            $_SESSION['id_admin'] = $user['id'];
            $dest = '/adm/dashboard.php';
        } else {
            $_SESSION['id_worker'] = $user['id'];
            $dest = '/worker/dashboard.php';
        }
        
        session_write_close(); // Força a gravação da sessão na Vercel
        header("Location: $dest");
        exit();
    }
}

// Se chegar aqui, falhou
$_SESSION['nao_autenticado'] = true;
session_write_close();
header('Location: /');
exit();