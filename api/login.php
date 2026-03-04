<?php
require_once __DIR__ . '/../src/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit();
}

$email = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$password_md5 = md5($password);

// Tentar Funcionário/ADM
$query_func = "SELECT id, nome, palavra_passe, adm FROM funcionario WHERE email = '$email' LIMIT 1";
$res_func = mysqli_query($conn, $query_func);

if ($res_func && mysqli_num_rows($res_func) > 0) {
    $user = mysqli_fetch_assoc($res_func);
    
    if ($password_md5 === $user['palavra_passe']) {
        
        // Calcular o tempo para "Sempre Ligado" (1 ano = 365 dias * 24h * 60m * 60s)
        $um_ano = time() + (86400 * 365);

        // Criar Cookies no navegador (o '/' garante que funcionam em todas as páginas)
        setcookie('user_id', $user['id'], $um_ano, '/');
        setcookie('user_nome', $user['nome'], $um_ano, '/');

        if ($user['adm'] == 1) {
            setcookie('user_role', 'admin', $um_ano, '/');
            $dest = '/adm/dashboard.php';
        } else {
            setcookie('user_role', 'worker', $um_ano, '/');
            $dest = '/worker/dashboard.php';
        }
        
        header("Location: $dest");
        exit();
    }
}

// Se chegar aqui, a password ou email estão errados
// Mandamos o erro pelo URL em vez de sessão
header('Location: /?erro=auth');
exit();
?>