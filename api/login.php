<?php
// Garante que o caminho para a conexão está correto (ajusta se necessário)
require_once __DIR__ . '/../src/conexao.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Receber os dados do formulário
    $email = trim($_POST['email'] ?? '');
    // Tenta apanhar o campo da password, quer se chame 'palavra_passe' ou 'password' no HTML
    $password_raw = trim($_POST['palavra_passe'] ?? $_POST['password'] ?? '');

    // Se vierem vazios, manda de volta
    if (empty($email) || empty($password_raw)) {
        header('Location: /index.php?erro=vazio');
        exit();
    }

    // 2. Escapar e encriptar (EXATAMENTE igual ao teu register.php)
    $email_escaped = mysqli_real_escape_string($conn, $email);
    $password_escaped = mysqli_real_escape_string($conn, $password_raw);
    $password_md5 = md5($password_escaped); 

    $um_ano = time() + (86400 * 365); // Tempo de validade dos cookies (1 ano)

    // ==========================================
    // 3. VERIFICAR SE É FUNCIONÁRIO OU ADMIN
    // ==========================================
    $query_func = "SELECT id, nome, palavra_passe, adm FROM funcionario WHERE email = '$email_escaped' LIMIT 1";
    $res_func = mysqli_query($conn, $query_func);

    if ($res_func && mysqli_num_rows($res_func) > 0) {
        $user_func = mysqli_fetch_assoc($res_func);
        
        // Verifica a password
        if ($password_md5 === $user_func['palavra_passe']) {
            
            // Cria os cookies
            setcookie('id', $user_func['id'], $um_ano, '/');
            setcookie('nome', $user_func['nome'], $um_ano, '/');
            setcookie('role', $user_func['adm'], $um_ano, '/'); // Guarda 'admin' ou 'worker'
            
            // Redireciona consoante o cargo
            if ($user_func['adm'] === 'admin') {
                header("Location: /adm/dashboard.php"); // Se o teu dashboard de admin tiver outro nome, altera aqui!
            } else {
                header("Location: /worker/dashboard.php");
            }
            exit();
        }
    }

    // ==========================================
    // 4. VERIFICAR SE É CLIENTE
    // ==========================================
    $query_cli = "SELECT id, nome, palavra_passe, email_verificado FROM cliente WHERE email = '$email_escaped' LIMIT 1";
    $res_cli = mysqli_query($conn, $query_cli);

    if ($res_cli && mysqli_num_rows($res_cli) > 0) {
        $user_cli = mysqli_fetch_assoc($res_cli);
        
        // Verifica a password
        if ($password_md5 === $user_cli['palavra_passe']) {
            
            // Verifica se o email já foi validado!
            if ($user_cli['email_verificado'] == 1) {
                
                // Cria os cookies para o cliente
                setcookie('id', $user_cli['id'], $um_ano, '/');
                setcookie('nome', $user_cli['nome'], $um_ano, '/');
                setcookie('role', 'customer', $um_ano, '/'); // Cargo exclusivo de cliente
                
                // Manda para a área do cliente
                header("Location: /customer/dashboard.php");
                exit();
                
            } else {
                // Password certa, mas falta clicar no link do email
                echo "<script>
                        alert('Atenção: A sua conta ainda não foi ativada. Por favor, verifique a sua caixa de entrada (e o spam) e clique no link de validação.');
                        window.location.href='/index.php';
                      </script>";
                exit();
            }
        }
    }

    // ==========================================
    // 5. SE CHEGAR AQUI, EMAIL OU PASSWORD ERRADOS
    // ==========================================
    header('Location: /index.php?erro=auth');
    exit();

} else {
    // Se tentarem aceder ao login.php diretamente pelo URL sem preencher o formulário
    header('Location: /index.php');
    exit();
}
?>