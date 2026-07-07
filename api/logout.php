<?php
$expired = time() - 3600;

setcookie('id', '', $expired, '/');
setcookie('nome', '', $expired, '/');
setcookie('role', '', $expired, '/');

header('Location: /index.php');
exit();
