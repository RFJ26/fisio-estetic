<?php
session_start();


include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

$mensagem = '';
$tipo_mensagem = '';

// 1. Processar a atualização (POST)
if (isset($_POST['btn-atualizar'])) {
    // Receber e limpar dados
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $designacao = mysqli_real_escape_string($conn, $_POST['designacao']);

    // Validação simples
    if (empty($designacao)) {
        $mensagem = "O nome da categoria é obrigatório.";
        $tipo_mensagem = "danger";
    } else {
        // Atualizar na base de dados
        $sql_update = "UPDATE categoria SET designacao = '$designacao' WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql_update)) {
            // Sucesso - Redirecionar para a lista ou mostrar sucesso
            echo "<script>alert('Categoria atualizada com sucesso!'); window.location.href='list.php';</script>";
            exit;
        } else {
            $mensagem = "Erro ao atualizar: " . mysqli_error($conn);
            $tipo_mensagem = "danger";
        }
    }
}

// 2. Buscar dados atuais (GET)
if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $sql_get = "SELECT * FROM categoria WHERE id = '$id'";
    $query = mysqli_query($conn, $sql_get);

    if (mysqli_num_rows($query) > 0) {
        $categoria = mysqli_fetch_assoc($query);
    } else {
        echo "<script>alert('Categoria não encontrada.'); window.location.href='list.php';</script>";
        exit;
    }
} else if (!isset($_POST['btn-atualizar'])) {
    // Se não tem ID nem é um POST, volta para a lista
    header('Location: list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Categoria - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    
    <link rel="stylesheet" href="../css/service_category/edit.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none">
        <i class="bi bi-list"></i>
    </button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area">
            <h2>Fisioestetic</h2>
        </div>
        
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
                <div class="d-flex align-items-center gap-2 text-muted mb-2">
                    <a href="list.php" class="text-decoration-none text-secondary small">Categorias</a>
                    <span>/</span>
                    <span class="small">Editar</span>
                </div>
                <h1>Editar Categoria</h1>
            </header>

            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show" role="alert">
                    <?= $mensagem ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="edit-card">
                <form action="edit.php" method="POST">
                    
                    <input type="hidden" name="id" value="<?= htmlspecialchars($categoria['id']) ?>">

                    <div class="mb-4">
                        <label for="designacao" class="form-label">Nome da Categoria</label>
                        <input type="text" class="form-control" id="designacao" name="designacao" 
                               value="<?= htmlspecialchars($categoria['designacao']) ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="btn-atualizar" class="btn-save">
                            <i class="bi bi-check2-circle me-2"></i>Guardar Alterações
                        </button>
                        
                        <a href="list.php" class="btn-cancel">Cancelar</a>
                    </div>

                </form>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle'); 
        if(toggle) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
    </script>
</body>
</html>