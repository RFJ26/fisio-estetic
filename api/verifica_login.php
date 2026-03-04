<?php
// Em vez de verificar as $_SESSION, verificamos os $_COOKIE
if (!isset($_COOKIE['user_id'])) {
    // Se o cookie não existir, expulsa para o login
    header('Location: /index.php'); 
    exit();
}
?>