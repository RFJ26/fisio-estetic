<?php
session_start();
require_once __DIR__ . '/../src/conexao.php';
require_once '../src/send_email.php';

$msg = "";
$msg_tipo = "";

if (isset($_POST['recuperar'])) {
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));

    // 1. Verificar se o email existe na BD
    $query = "SELECT id, nome FROM cliente WHERE email = '$email' LIMIT 1";
    $resultado = mysqli_query($conn, $query);

    if (mysqli_num_rows($resultado) > 0) {
        $cliente = mysqli_fetch_assoc($resultado);
        
        // 2. Gerar Token e Validade (1 hora)
        $token = bin2hex(random_bytes(50));
        $validade = date("Y-m-d H:i:s", strtotime('+1 hour'));

        // 3. Guardar na Base de Dados
        $update = "UPDATE cliente SET reset_token = '$token', reset_expires = '$validade' WHERE email = '$email'";
        
        if (mysqli_query($conn, $update)) {
            // 4. LÓGICA DE ENVIO DE EMAIL
            // Cria o link automaticamente baseando-se no teu servidor (localhost ou domínio real)
            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $dominio = $_SERVER['HTTP_HOST'];
            $caminho = rtrim(dirname($_SERVER['REQUEST_URI']), '/');
            $link_recuperacao = $protocolo . "://" . $dominio . $caminho . "/nova_passe.php?token=" . $token;
            
            // Envia o email!
            $envio = enviarEmailRecuperacao($email, $cliente['nome'], $link_recuperacao);

            if ($envio) {
                $msg = "Enviámos as instruções de recuperação para o seu email.";
                $msg_tipo = "success";
            } else {
                $msg = "Erro ao enviar o email. Tente novamente mais tarde.";
                $msg_tipo = "error";
            }
        } else {
            $msg = "Erro ao processar o pedido. Tente novamente.";
            $msg_tipo = "error";
        }
    } else {
        // Por segurança, mostramos sucesso na mesma para que não descubram os emails registados
        $msg = "Se o email existir na nossa base de dados, receberá as instruções.";
        $msg_tipo = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Palavra-passe - Fisioestetic</title>
    <link rel="stylesheet" href="css/register.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
  
  <div class="container">
    <div class="register-card" style="max-width: 450px;">
        <div class="text-center mb-4">
            <h2 class="brand-title">Fisioestetic</h2>
        </div>

        <h3>Esqueceu-se da senha?</h3>
        <p class="subtitle" style="margin-bottom: 30px;">Introduza o seu email e enviaremos um link para criar uma nova palavra-passe.</p>

        <?php if ($msg != ""): ?>
            <div class="alert-error" style="background-color: <?= $msg_tipo == 'success' ? '#dcfce7' : '#fee2e2' ?>; color: <?= $msg_tipo == 'success' ? '#166534' : '#dc2626' ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="bi <?= $msg_tipo == 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
                <?= $msg; ?>
            </div> 
        <?php endif; ?>

        <form action="recuperar_passe.php" method="POST">
            <div class="input-group">
                <label for="email">Email de Registo</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" required>
                </div>
            </div>
            
            <button type="submit" name="recuperar" class="btn-register" style="margin-top: 20px;">
                Enviar link de recuperação
            </button>
        </form>

        <div class="login-link mt-4" style="margin-top: 25px;">
            <p><a href="index.php"><i class="bi bi-arrow-left"></i> Voltar ao Login</a></p>
        </div>
    </div>
  </div>

</body>
</html>