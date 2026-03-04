<?php
session_start();
require_once __DIR__ . '/../src/conexao.php';

$msg = "";
$msg_tipo = "";
$token_valido = false;
$email_cliente = "";

// 1. Verificar se o token vem no URL e se é válido
if (isset($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    $data_atual = date("Y-m-d H:i:s");

    $query = "SELECT email FROM cliente WHERE reset_token = '$token' AND reset_expires >= '$data_atual' LIMIT 1";
    $resultado = mysqli_query($conn, $query);

    if (mysqli_num_rows($resultado) > 0) {
        $token_valido = true;
        $row = mysqli_fetch_assoc($resultado);
        $email_cliente = $row['email'];
    } else {
        $msg = "Este link é inválido ou já expirou. Faça um novo pedido.";
        $msg_tipo = "error";
    }
} elseif (!isset($_POST['nova_passe'])) {
    header("Location: index.php");
    exit();
}

// 2. Processar a nova palavra-passe (POST)
if (isset($_POST['nova_passe'])) {
    $nova_senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $email_form = mysqli_real_escape_string($conn, $_POST['email']);
    $token_form = mysqli_real_escape_string($conn, $_POST['token']);

    if ($nova_senha === $confirmar_senha) {
        if (strlen($nova_senha) >= 6) {
            // Usa o MD5 para ficar compatível com o teu sistema de registo
            $senha_hashed = md5($nova_senha);

            // Atualiza a senha e APAGA o token para não ser usado 2 vezes
            $update = "UPDATE cliente SET palavra_passe = '$senha_hashed', reset_token = NULL, reset_expires = NULL WHERE email = '$email_form' AND reset_token = '$token_form'";
            
            if (mysqli_query($conn, $update)) {
                echo "<script>
                        alert('Palavra-passe alterada com sucesso! Já pode fazer login.');
                        window.location.href = 'index.php';
                      </script>";
                exit();
            } else {
                $msg = "Erro ao atualizar a senha.";
                $msg_tipo = "error";
                $token_valido = true; // Para manter o formulário aberto
                $token = $token_form;
                $email_cliente = $email_form;
            }
        } else {
            $msg = "A palavra-passe deve ter pelo menos 6 caracteres.";
            $msg_tipo = "error";
            $token_valido = true;
            $token = $token_form;
            $email_cliente = $email_form;
        }
    } else {
        $msg = "As palavras-passe não coincidem.";
        $msg_tipo = "error";
        $token_valido = true;
        $token = $token_form;
        $email_cliente = $email_form;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Nova Passe - Fisioestetic</title>
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

        <h3>Nova Palavra-passe</h3>

        <?php if ($msg != ""): ?>
            <div class="alert-error" style="background-color: <?= $msg_tipo == 'success' ? '#dcfce7' : '#fee2e2' ?>; color: <?= $msg_tipo == 'success' ? '#166534' : '#dc2626' ?>; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="bi <?= $msg_tipo == 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
                <?= $msg; ?>
            </div> 
        <?php endif; ?>

        <?php if ($token_valido): ?>
            <p class="subtitle">Defina a sua nova palavra-passe abaixo.</p>

            <form action="nova_passe.php" method="POST">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email_cliente) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="input-group">
                    <label for="senha">Nova Palavra-passe</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required minlength="6">
                    </div>
                </div>

                <div class="input-group">
                    <label for="confirmar_senha">Confirmar Palavra-passe</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a palavra-passe" required minlength="6">
                    </div>
                </div>
                
                <button type="submit" name="nova_passe" class="btn-register" style="margin-top: 20px;">
                    Guardar e Entrar
                </button>
            </form>
        <?php else: ?>
            <a href="recuperar_passe.php" class="btn-register" style="text-align:center; text-decoration:none; display:block; margin-top:20px;">
                Pedir Novo Link
            </a>
        <?php endif; ?>

    </div>
  </div>

</body>
</html>