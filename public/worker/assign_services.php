<?php
session_start();


include('../verifica_login.php');
require '../../src/conexao.php';

// 1. Validar ID do Funcionário
if (!isset($_GET['id']) && !isset($_POST['id_funcionario'])) {
    echo "<script>window.location.href = 'list.php';</script>";
    exit();
}

$id_funcionario = isset($_POST['id_funcionario']) ? $_POST['id_funcionario'] : $_GET['id'];
$mensagem = "";
$tipo_mensagem = "";

// 2. Processar Formulário (SALVAR)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_services'])) {
    $servicos_enviados = isset($_POST['servicos']) ? $_POST['servicos'] : [];
    
    // A. Buscar todos os serviços já associados a este funcionário
    $sql_todos = "SELECT id_servico FROM servico_funcionario WHERE id_funcionario = '$id_funcionario'";
    $query_todos = mysqli_query($conn, $sql_todos);
    
    $ids_na_bd = [];
    while($row = mysqli_fetch_assoc($query_todos)) {
        $ids_na_bd[] = $row['id_servico'];
    }

    // B. Ativar ou Inserir os serviços selecionados
    foreach ($servicos_enviados as $id_servico) {
        if (in_array($id_servico, $ids_na_bd)) {
            // Se já existe, garante que está ATIVO (ativo = 1)
            $sql_update = "UPDATE servico_funcionario SET ativo = 1 WHERE id_funcionario = '$id_funcionario' AND id_servico = '$id_servico'";
            mysqli_query($conn, $sql_update);
        } else {
            // Se não existe, INSERE como ATIVO
            $sql_insert = "INSERT INTO servico_funcionario (id_funcionario, id_servico, ativo) VALUES ('$id_funcionario', '$id_servico', 1)";
            mysqli_query($conn, $sql_insert);
        }
    }

    // C. Desativar os serviços que NÃO foram selecionados
    foreach ($ids_na_bd as $id_servico_existente) {
        if (!in_array($id_servico_existente, $servicos_enviados)) {
            // Update para ativo = 0
            $sql_desativar = "UPDATE servico_funcionario SET ativo = 0 WHERE id_funcionario = '$id_funcionario' AND id_servico = '$id_servico_existente'";
            mysqli_query($conn, $sql_desativar);
        }
    }

    $mensagem = "Competências atualizadas com sucesso!";
    $tipo_mensagem = "success";
}

// 3. Buscar Nome do Funcionário
$sql_func = "SELECT nome FROM funcionario WHERE id = '$id_funcionario'";
$query_func = mysqli_query($conn, $sql_func);
if ($row = mysqli_fetch_assoc($query_func)) {
    $nome_funcionario = $row['nome'];
} else {
    echo "<script>window.location.href = 'list.php';</script>";
    exit();
}

// 4. Buscar TODOS os Serviços + Categorias
$sql_servicos = "SELECT servico.id, servico.designacao, servico.preco, categoria.designacao as nome_categoria 
                 FROM servico  
                 LEFT JOIN categoria ON servico.id_categoria = categoria.id 
                 ORDER BY categoria.designacao ASC, servico.designacao ASC";
$query_servicos = mysqli_query($conn, $sql_servicos);

// 5. Buscar IDs já associados E ATIVOS
$sql_checked = "SELECT id_servico FROM servico_funcionario WHERE id_funcionario = '$id_funcionario' AND ativo = 1";
$query_checked = mysqli_query($conn, $sql_checked);
$ids_associados = [];
while($row = mysqli_fetch_assoc($query_checked)) {
    $ids_associados[] = $row['id_servico'];
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Associar Serviços - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/assign.css">
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
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="mb-4">
                <div class="d-flex align-items-center gap-2 text-muted mb-2">
                    <a href="list.php" class="btn-back"><i class="bi bi-arrow-left"></i> Voltar</a>
                    <span>/</span>
                    <span class="small">Gerir Competências</span>
                </div>
                <h2><span class="fw-light">Serviços de:</span> <?= htmlspecialchars($nome_funcionario) ?></h2>
            </header>

            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show shadow-sm" role="alert">
                    <?= $mensagem ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="id_funcionario" value="<?= $id_funcionario ?>">

                <div class="row g-3 pb-5"> <?php
                    $categoria_atual = null;
                    
                    if (mysqli_num_rows($query_servicos) > 0) {
                        while ($servico = mysqli_fetch_assoc($query_servicos)) {
                            
                            $cat_nome = $servico['nome_categoria'] ? $servico['nome_categoria'] : 'Geral';
                            
                            if ($categoria_atual !== $cat_nome) {
                                echo '<div class="col-12"><h5 class="category-divider">' . htmlspecialchars($cat_nome) . '</h5></div>';
                                $categoria_atual = $cat_nome;
                            }

                            $esta_ativo = in_array($servico['id'], $ids_associados);
                            $checked = $esta_ativo ? 'checked' : '';
                            ?>

                            <div class="col-md-4 col-lg-3 col-sm-6">
                                <input type="checkbox" class="btn-check" 
                                       id="servico_<?= $servico['id'] ?>" 
                                       name="servicos[]" 
                                       value="<?= $servico['id'] ?>" 
                                       <?= $checked ?>>

                                <label class="service-selection-card" for="servico_<?= $servico['id'] ?>">
                                    <i class="bi bi-check-circle-fill check-icon"></i>
                                    
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($servico['designacao']) ?></h6>
                                    
                                    <span class="text-muted small price-tag">
                                        <?= number_format($servico['preco'], 2, ',', '.') ?> €
                                    </span>
                                </label>
                            </div>

                            <?php
                        }
                    } else {
                        echo '<div class="col-12"><div class="alert alert-warning">Ainda não existem serviços registados na base de dados.</div></div>';
                    }
                    ?>
                </div>

                <div class="bottom-action-bar">
                    <div class="d-none d-md-block text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        As alterações afetam a disponibilidade na agenda imediatamente.
                    </div>

                    <div class="d-flex gap-3 ms-auto">
                        <a href="list.php" class="btn btn-cancel">
                            Cancelar
                        </a>
                        
                        <button type="submit" name="save_services" class="btn btn-save">
                            <i class="bi bi-check-lg me-2"></i>Guardar Alterações
                        </button>
                    </div>
                </div>
            </form>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if(toggle){
            toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
        }
    </script>
</body>
</html>