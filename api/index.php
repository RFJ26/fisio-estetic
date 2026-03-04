<?php
// 1. Configuração de sessão compatível com Vercel
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '1');
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar - Fisioestetic</title>
    <link rel="stylesheet" href="css/mouse-fix.css">
    <link rel="stylesheet" href="css/login.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <div class="login-card">
        
        <div class="logo-area">
            <img src="images/logo_nova.png" alt="Fisioestetic Logo" class="login-logo">
            <h2 class="brand-title">Fisioestetic</h2>
        </div>

        <h3>Bem-vindo</h3>
        <p class="subtitle">Faça login para gerir as suas marcações.</p>

        <?php
        if (isset($_SESSION['email_nao_validado'])):
        ?>
            <div class="alert-error" style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em; border: 1px solid #ffeeba;">
                <i class="bi bi-envelope-exclamation-fill"></i>
                <span>Por favor, valide o seu email antes de entrar. Verifique a sua caixa de entrada!</span>
            </div>
        <?php
            unset($_SESSION['email_nao_validado']);
            
        elseif (isset($_SESSION['nao_autenticado'])):
        ?>
            <div class="alert-error" style="background-color: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9em;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <span>Email ou palavra-passe incorretos.</span>
            </div>
        <?php
            unset($_SESSION['nao_autenticado']);
        endif;
        ?>

        <form action="/login.php" method="POST">
            
            <div class="input-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <i class="bi bi-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="seu@email.com" required>
                </div>
            </div>

            <div class="input-group" style="margin-bottom: 5px;">
                <label for="password">Palavra-passe</label>
                <div class="input-wrapper">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <div style="text-align: right; margin-bottom: 25px;">
                <a href="/recuperar_passe.php" style="font-size: 0.85rem; color: #275a29; text-decoration: none; font-weight: 500;">
                    Esqueceu-se da palavra-passe?
                </a>
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>

        <div class="register-link">
            <p>Ainda não tem conta? <a href="/register.php">Registar agora</a></p>
        </div>

    </div>
</div>

</body>
</html>