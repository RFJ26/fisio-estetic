<?php
// Definir a validade para o passado (1 hora atrás) apaga os cookies
setcookie('user_id', '', time() - 3600, '/');
setcookie('user_nome', '', time() - 3600, '/');
setcookie('user_role', '', time() - 3600, '/');

// Redireciona de volta para a página inicial/login
header('Location: /index.php');
exit();
?>