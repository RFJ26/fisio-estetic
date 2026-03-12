<?php
session_start();


include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

$erro = false;
$mensagem_erro = "";

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // 1. VERIFICAÇÃO DE SEGURANÇA:
    // Verificar se existem serviços associados a esta categoria antes de apagar.
    // Tabela assumida: servico, Coluna assumida: id_categoria
    $sql_check = "SELECT id FROM servico WHERE id_categoria = '$id'";
    $query_check = mysqli_query($conn, $sql_check);

    if (mysqli_num_rows($query_check) > 0) {
        // Existem serviços usando esta categoria! Não apagar.
        $erro = true;
        $mensagem_erro = "Não é possível apagar esta categoria pois existem serviços associados a ela. Apague ou mova os serviços primeiro.";
    } else {
        // 2. Se não houver serviços, proceder com a eliminação
        $sql_delete = "DELETE FROM categoria WHERE id = '$id'";
        
        if (mysqli_query($conn, $sql_delete)) {
            // SUCESSO: Redireciona de volta para a lista
            header("Location: list.php?msg=deleted");
            exit;
        } else {
            // Erro de SQL
            $erro = true;
            $mensagem_erro = "Erro ao tentar apagar a categoria no banco de dados: " . mysqli_error($conn);
        }
    }
} else {
    // ID não fornecido
    header("Location: list.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erro ao Apagar - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    <link rel="stylesheet" href="../css/service_category/delete.css">
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
        <div class="message-card error">
            <div class="icon-box">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            
            <h3 class="message-title">Ação Bloqueada</h3>
            
            <p class="message-text">
                <?= $mensagem_erro ?>
            </p>
            
            <a href="list.php" class="btn-back">
                <i class="bi bi-arrow-left me-2"></i>Voltar à Lista
            </a>
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