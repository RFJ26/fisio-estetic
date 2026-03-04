<?php
// Se o cookie 'id' não existir, significa que não há ninguém logado
if (!isset($_COOKIE['id'])) {
    header('Location: /index.php');
    exit();
}

// Opcional: Se quiseres ser ainda mais rigoroso e separar quem pode ver o quê
// Podes ler o $_SERVER['REQUEST_URI'] para saber em que pasta estás:
$url_atual = $_SERVER['REQUEST_URI'];

if (strpos($url_atual, '/adm/') !== false && $_COOKIE['role'] !== 'admin') {
    // Se tentar entrar na pasta /adm/ mas não for admin, rua!
    header('Location: /index.php');
    exit();
}

if (strpos($url_atual, '/worker/') !== false && $_COOKIE['role'] !== 'worker') {
    // Se tentar entrar na pasta /worker/ mas não for worker, rua!
    header('Location: /index.php');
    exit();
}
?>