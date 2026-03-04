<?php
session_start();

// Limpa todas as variáveis de sessão (admin, worker, cliente, tudo!)
session_unset();

// Destrói a sessão no servidor
session_destroy();

// Manda o utilizador de volta para a página inicial/login
header('Location: index.php');
exit();
?>