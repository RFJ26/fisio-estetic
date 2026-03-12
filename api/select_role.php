<?php
session_start();

if (!isset($_COOKIE['role']) || 
   ($_COOKIE['role'] !== 'admin' && $_COOKIE['role'] !== '1')) {
    header("Location: /index.php");
    exit();
}

$nome = $_COOKIE['nome'] ?? 'Administrador';

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escolher Perfil - Fisioestetic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-accent: #2e7d32;
            --green-soft: #e8f5e9;
            --bg-body: #f8f9fa;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-body);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .selection-card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .role-button {
            display: flex;
            align-items: center;
            padding: 20px;
            margin: 15px 0;
            border: 2px solid #eee;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        .role-button:hover {
            border-color: var(--green-accent);
            background-color: var(--green-soft);
            transform: translateY(-3px);
            color: var(--green-accent);
        }
        .role-icon {
            font-size: 2rem;
            margin-right: 20px;
            color: var(--green-accent);
        }
        .role-text { text-align: left; }
        .role-text h4 { margin: 0; font-weight: 600; font-size: 1.1rem; }
        .role-text p { margin: 0; font-size: 0.85rem; color: #777; }
        .text-gold { color: var(--green-accent); font-weight: 700; }
    </style>
</head>
<body>

    <div class="selection-card">
        <h2 class="mb-2">Olá, <span class="text-gold"><?php echo htmlspecialchars($nome); ?></span></h2>
        <p class="text-muted mb-4">Como deseja aceder ao sistema hoje?</p>

        <a href="/adm/dashboard.php" class="role-button">
            <div class="role-icon">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div class="role-text">
                <h4>Administrador</h4>
                <p>Gestão total, relatórios e configurações.</p>
            </div>
        </a>

        <a href="/worker/dashboard.php" class="role-button">
            <div class="role-icon">
                <i class="bi bi-calendar-week"></i>
            </div>
            <div class="role-text">
                <h4>Funcionário</h4>
                <p>Ver a minha agenda e marcações.</p>
            </div>
        </a>

        <div class="mt-4">
            <a href="/logout.php" class="text-muted small text-decoration-none">
                <i class="bi bi-box-arrow-left"></i> Sair da conta
            </a>
        </div>
    </div>

</body>
</html>