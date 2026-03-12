<?php
session_start();



include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

$designacao = '';
$erro = '';

if (isset($_POST['create_category'])) {
    $designacao = trim($_POST['designacao']);

    if (empty($designacao)) {
        $erro = "A designação é obrigatória.";
    } else {
        $stmt_check = mysqli_prepare($conn, "SELECT id FROM categoria WHERE designacao = ?");
        mysqli_stmt_bind_param($stmt_check, "s", $designacao);
        mysqli_stmt_execute($stmt_check);
        mysqli_stmt_store_result($stmt_check);

        if (mysqli_stmt_num_rows($stmt_check) > 0) {
            $erro = "Esta categoria já existe.";
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO categoria (designacao) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $designacao);
            
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>alert('Categoria criada!'); window.location.href = 'list.php';</script>";
                exit;
            } else {
                $erro = "Erro ao criar: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Categoria - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service/create.css">
    
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>
    
    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <?php if(isset($_COOKIE['role']) && ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === '1')): ?>
                <li class="nav-item mt-3"><a class="nav-link" href="/select_role.php" style="color: #ef6c00;"><i class="bi bi-arrow-left-right me-3"></i>Mudar Perfil</a></li>
            <?php endif; ?>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="mb-5">
                <h1>Nova Categoria</h1>
                <p class="text-muted">Adicionar uma nova categoria de serviços.</p>
            </header>

            <?php if ($erro): ?>
                <div class="alert alert-danger"><?= $erro ?></div>
            <?php endif; ?>

            <div class="create-card">
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label">Designação</label>
                        <input type="text" name="designacao" class="form-control" value="<?= htmlspecialchars($designacao) ?>" placeholder="Ex: Massagens, Rosto..." required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="create_category" class="btn btn-save">
                            <i class="bi bi-check-lg me-2"></i>Criar
                        </button>
                        <a href="list.php" class="btn btn-cancel">Cancelar</a>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if(toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    </script>
</body>
</html>