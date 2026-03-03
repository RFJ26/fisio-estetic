<?php
session_start();
include('../verifica_login.php');
require '../../src/conexao.php';

// Receber o ID (via GET ao abrir, ou POST ao guardar)
$id = $_GET['id'] ?? $_POST['id'] ?? null;
$erro = '';

// --- VALIDAÇÃO DE ENTRADA ---
if (!$id) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Erro: Nenhum ID de serviço foi fornecido. Volte à listagem e tente novamente.</h3>");
}

// 1. Buscar dados do Serviço
$stmt = mysqli_prepare($conn, "SELECT * FROM servico WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$servico = mysqli_fetch_assoc($result);

if (!$servico) {
    die("<h3 style='color:red; text-align:center; margin-top:50px;'>Erro: Serviço não encontrado (ID: $id).</h3>");
}

// 2. Buscar categorias
$query_cat = "SELECT * FROM categoria ORDER BY categoria.designacao ASC";
$result_cat = mysqli_query($conn, $query_cat);

// 3. Processar o UPDATE
if (isset($_POST['update_service'])) {
    $designacao = mysqli_real_escape_string($conn, $_POST['designacao']);
    $preco = str_replace(',', '.', $_POST['preco']); 
    $num_slots = intval($_POST['num_slots']);
    $id_categoria = intval($_POST['id_categoria']);
    
    if (empty($designacao) || empty($preco) || empty($num_slots) || empty($id_categoria)) {
        $erro = "Preencha todos os campos obrigatórios.";
    } else {
        
        // --- Lógica de Imagem ---
        $caminho_final = $servico['caminho_img']; 

        if(isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0){
            $diretorio = "../../public/uploads/";
            if(!is_dir($diretorio)) mkdir($diretorio, 0777, true);

            $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
            $novo_nome = uniqid() . "." . $extensao;
            $destino = $diretorio . $novo_nome;

            if(move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)){
                // Apagar imagem antiga
                if(!empty($servico['caminho_img']) && file_exists("../../public/" . $servico['caminho_img'])){
                    unlink("../../public/" . $servico['caminho_img']);
                }
                $caminho_final = "uploads/" . $novo_nome;
            }
        }

        // --- SQL UPDATE ---
        $sql_update = "UPDATE servico SET id_categoria=?, designacao=?, preco=?, num_slots=?, caminho_img=? WHERE id=?";
        
        $stmt_up = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_up, "isdisi", $id_categoria, $designacao, $preco, $num_slots, $caminho_final, $id);

        if (mysqli_stmt_execute($stmt_up)) {
            echo "<script>alert('Serviço atualizado com sucesso!'); window.location.href = 'list.php';</script>";
            exit;
        } else {
            $erro = "Erro ao atualizar: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Serviço - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service/edit.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>
    
    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold" style="color: var(--brand-dark);">Editar Serviço</h2>
                    <p class="text-muted">Alterar dados do serviço ID: <strong><?= $id ?></strong></p>
                </div>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Voltar
                </a>
            </header>

            <?php if ($erro): ?>
                <div class="alert alert-danger shadow-sm border-0"><?= $erro ?></div>
            <?php endif; ?>

            <div class="edit-card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <div class="row">
                        <div class="col-md-8 mb-4">
                            <label class="form-label">Nome do Serviço</label>
                            <input type="text" name="designacao" class="form-control" value="<?= htmlspecialchars($servico['designacao']) ?>" required>
                        </div>

                        <div class="col-md-4 mb-4">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" class="form-select" required>
                                <option value="" disabled>Selecione...</option>
                                <?php 
                                // Resetar ponteiro do resultset se necessário
                                mysqli_data_seek($result_cat, 0);
                                while($cat = mysqli_fetch_assoc($result_cat)): 
                                ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($cat['id'] == $servico['id_categoria']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['designacao']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Preço (€)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-muted">€</span>
                                <input type="number" name="preco" step="0.01" class="form-control" value="<?= $servico['preco'] ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6 mb-4">
                            <label class="form-label">Duração (Slots)</label>
                            <input type="number" name="num_slots" class="form-control" value="<?= $servico['num_slots'] ?>" min="1" max="12" required>
                            <div class="form-text text-muted small">Cada slot = 15 min.</div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="form-label">Imagem do Serviço</label>
                        <input type="file" name="imagem" class="form-control" accept="image/*">
                        
                        <?php if(!empty($servico['caminho_img']) && file_exists("../../public/" . $servico['caminho_img'])): ?>
                            <div class="current-img-container">
                                <small class="d-block mb-2 text-muted fw-bold">Imagem Atual:</small>
                                <img src="../../public/<?= htmlspecialchars($servico['caminho_img']) ?>" class="img-preview" alt="Atual">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-end align-items-center border-top pt-4">
                        <a href="list.php" class="btn-cancel">Cancelar</a>
                        <button type="submit" name="update_service" class="btn btn-save shadow-sm">
                            <i class="bi bi-check-lg me-2"></i>Guardar Alterações
                        </button>
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