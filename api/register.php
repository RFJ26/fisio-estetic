<?php
session_start();
require_once __DIR__ . '/../src/conexao.php'; 
include('../src/send_email.php'); // INCLUI O FICHEIRO DE EMAILS

if (isset($_POST['register'])) {

    // 1. Receber e limpar os dados
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $palavra_passe = trim($_POST['palavra_passe']); 
    $telefone = trim($_POST['telefone']);
    $nif = trim($_POST['nif']); 

    $erros = [];

    // --- 2. VALIDAÇÕES REGEX ---
    $regexpNome = "/^[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+(\s((de|da|do|das|dos|e)\s)?[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+)+$/u";
    if (!preg_match($regexpNome, $nome)) {
        $erros[] = "Nome incorreto. Use o nome completo com iniciais maiúsculas.";
    }

    $regexEmail = "/^[a-zA-Z0-9\\-]+(\\.[a-zA-Z0-9]+)*@[a-zA-Z0-9]+(\\.[a-zA-Z0-9]+)*$/";
    if (!preg_match($regexEmail, $email)) {
        $erros[] = "Email inválido.";
    }

    $regexpTelefone = "/^(\\+351)?((2\\d{8})|(9[1236]\\d{7}))$/";
    if (!preg_match($regexpTelefone, $telefone)) {
        $erros[] = "Telefone inválido.";
    }

    $regexpNif = "/^[0-9]{9}$/";
    if (!preg_match($regexpNif, $nif)) {
        $erros[] = "NIF inválido.";
    }

    // 3. SE HOUVER ERROS DE VALIDAÇÃO
    if (!empty($erros)) {
        $_SESSION['msg_erro'] = implode("<br>", $erros);
    } else {
        $nome = mysqli_real_escape_string($conn, $nome);
        $email = mysqli_real_escape_string($conn, $email);
        $palavra_passe = mysqli_real_escape_string($conn, $palavra_passe);
        $telefone = mysqli_real_escape_string($conn, $telefone);
        $nif = mysqli_real_escape_string($conn, $nif);

        // 4. Verificar se o email ou NIF já existe
        $query_check = "SELECT id FROM cliente WHERE email = '$email' OR nif = '$nif' LIMIT 1";
        $result_check = mysqli_query($conn, $query_check);

        if (mysqli_num_rows($result_check) > 0) {
            $_SESSION['msg_erro'] = "Este Email ou NIF já está registado.";
        } else {
            // 5. Criar a conta
            $password_hashed = md5($palavra_passe);
            
            // GERA O TOKEN PARA VALIDAÇÃO
            $token = bin2hex(random_bytes(32)); 
            
            // INSERE NA BASE DE DADOS (email_verificado a 0)
            $insert_query = "INSERT INTO cliente (nome, email, palavra_passe, telefone, nif, email_verificado, token_verificacao, created_at) 
                             VALUES ('$nome', '$email', '$password_hashed', '$telefone', '$nif', 0, '$token', NOW())";

            if (mysqli_query($conn, $insert_query)) {
                
                // === CHAMA A NOVA FUNÇÃO DE EMAIL ===
                // Constrói o URL dinâmico onde o teu site está alojado
                $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $dominio = $_SERVER['HTTP_HOST'];
                $pasta = dirname($_SERVER['PHP_SELF']);
                // Garante que a barra não duplica se estiver na raiz
                if($pasta == '\\' || $pasta == '/') $pasta = ''; 
                
                $link_validacao = $protocolo . "://" . $dominio . $pasta . "/verificar.php?token=" . $token;
                
                // Envia o email usando a função que criámos
                $email_enviado = enviarEmailValidacao($email, $nome, $link_validacao);

                if ($email_enviado) {
                    echo "<script>
                            alert('Conta criada com sucesso! Enviámos um email de validação. Por favor, verifique a sua caixa de entrada (e o spam) antes de fazer login.');
                            window.location.href = 'index.php';
                          </script>";
                } else {
                    echo "<script>
                            alert('Conta criada, mas houve um erro ao enviar o email de validação. Por favor, contacte a clínica.');
                            window.location.href = 'index.php';
                          </script>";
                }
                exit();
            } else {
                $_SESSION['msg_erro'] = "Erro ao registar: " . mysqli_error($conn);
            }
        }
    }
} 
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conta - Fisioestetic</title>
    
    <link rel="stylesheet" href="css/mouse-fix.css">
    <link rel="stylesheet" href="css/register.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <div class="register-card">
        <div class="text-center mb-4">
            <h2 class="brand-title">Fisioestetic</h2>
        </div>

        <h3>Crie a sua conta</h3>
        <p class="subtitle">Preencha os dados para se registar.</p>

        <?php if (isset($_SESSION['msg_erro'])): ?>
            <div class="alert-error" style="background-color: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em; line-height: 1.5;">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= $_SESSION['msg_erro']; ?>
            </div> 
            <?php unset($_SESSION['msg_erro']); ?>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="input-group">
                <label for="nome">Nome Completo</label>
                <div class="input-wrapper">
                    <i class="bi bi-person"></i>
                    <input type="text" id="nome" name="nome" placeholder="Ex: João Silva" 
                           pattern="[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+(\s((de|da|do|das|dos|e)\s)?[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+)+"
                           title="Nome completo. Ex: João das Dores. Use iniciais maiúsculas." 
                           required>
                </div>
            </div>

            <div class="input-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" 
                           pattern="[a-zA-Z0-9\-\.]+@[a-zA-Z0-9]+\.[a-zA-Z0-9\.]+"
                           title="Introduza um email válido" 
                           required>
                </div>
            </div>

            <div class="input-group">
                <label for="palavra_passe">Palavra-passe</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="palavra_passe" name="palavra_passe" placeholder="••••••••" required>
                </div>
            </div>
            
            <div class="row-inputs">
                <div class="input-group">
                    <label for="telefone">Telefone</label>
                    <div class="input-wrapper">
                        <i class="bi bi-phone"></i>
                        <input type="text" id="telefone" name="telefone" placeholder="912..." 
                               pattern="(\+351)?(2\d{8}|9[1236]\d{7})"
                               title="Deve começar por 2 ou 9(1,2,3,6) e ter 9 dígitos"
                               maxlength="13" required>
                    </div>
                </div>
                
                <div class="input-group">
                    <label for="nif">NIF</label>
                    <div class="input-wrapper">
                        <i class="bi bi-card-text"></i>
                        <input type="text" id="nif" name="nif" placeholder="123..." 
                               pattern="[0-9]{9}" 
                               title="O NIF deve ter 9 dígitos"
                               maxlength="9" required>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="register" class="btn-register">Criar Conta</button>
        </form>

        <div class="login-link">
            <p>Já tem conta? <a href="index.php">Faça Login aqui</a></p>
        </div>
    </div>
  </div>
</body>
</html>