<?php
session_start();
include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

// ============================================================================
// LÓGICA DE APAGAR
// ============================================================================
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Verifica se existem serviços associados
    $check_query = "SELECT id FROM servico WHERE id_categoria = '$id'";
    $check = mysqli_query($conn, $check_query);
    
    if(mysqli_num_rows($check) > 0) {
        $erro_msg = "Não é possível apagar: Existem serviços associados a esta categoria.";
    } else {
        $sql_del = "DELETE FROM categoria WHERE id = '$id'";
        if(mysqli_query($conn, $sql_del)){
            header('Location: list.php?msg=deleted');
            exit();
        }
    }
}

// ============================================================================
// PESQUISA E PAGINAÇÃO
// ============================================================================
$where = "";
if(isset($_GET['search']) && !empty(trim($_GET['search']))){
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $where = "WHERE categoria.designacao LIKE '%$search%'";
}

$registos_por_pagina = 10; 
$pagina_atual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $registos_por_pagina;

$query_count = "SELECT COUNT(*) as total FROM categoria $where";
$resultado_count = mysqli_query($conn, $query_count);
$row_count = mysqli_fetch_assoc($resultado_count);
$total_registos = intval($row_count['total']);
$total_paginas = ceil($total_registos / $registos_por_pagina);

$query = "SELECT * FROM categoria $where ORDER BY categoria.designacao ASC LIMIT $registos_por_pagina OFFSET $offset";
$result = mysqli_query($conn, $query);

$params = $_GET;
unset($params['pagina'], $params['delete'], $params['msg']);
$query_string_filtros = http_build_query($params);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service_category/list.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none"><i class="bi bi-list"></i></button>
    
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
            
            <div class="header-actions d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-dark">Categorias</h1>
                    <p class="text-muted mb-0">Organização e agrupamento de serviços.</p>
                </div>
                
                <a href="create.php" class="btn-add">
                    <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Nova Categoria</span>
                </a>
            </div>

            <div class="filter-bar bg-white p-3 rounded-4 shadow-sm border mb-4" style="border-color: #f0f0f0 !important;">
                <form class="row g-3 align-items-center" action="" method="GET" id="form-pesquisa">
                    <div class="col-12">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="campo-pesquisa" name="search" autocomplete="off" placeholder="Pesquisar categoria..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        </div>
                    </div>
                    <input type="hidden" name="pagina" value="1" id="pagina-atual">
                </form>
            </div>

            <?php if(isset($erro_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?= $erro_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" id="successAlert">
                    <i class="bi bi-check-circle me-2"></i> Categoria removida com sucesso.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-container" id="conteudo-tabela">
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th width="80%">Designação</th>
                                <th width="20%" class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td data-label="Designação">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['designacao']) ?></div>
                                    </td>
                                    <td data-label="Ações" class="text-end pe-4">
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?= $row['id'] ?>" class="btn-icon btn-view" title="Visualizar"><i class="bi bi-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn-icon btn-edit" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                            <a href="list.php?delete=<?= $row['id'] ?>" class="btn-icon btn-delete" onclick="return confirm('Tem a certeza que deseja apagar?')" title="Apagar"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center py-5 text-muted">
                                        Nenhuma categoria encontrada.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="text-muted small">Página <?= $pagina_atual ?> de <?= $total_paginas ?></span>
                        <nav aria-label="Navegação de páginas">
                            <ul class="pagination pagination-sm mb-0 justify-content-center justify-content-md-end">
                                <li class="page-item <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= $query_string_filtros ?>&pagina=<?= $pagina_atual - 1 ?>" aria-label="Anterior">&laquo;</a>
                                </li>
                                
                                <?php 
                                $adjacentes = 1; 
                                
                                $pmin = ($pagina_atual > $adjacentes) ? ($pagina_atual - $adjacentes) : 1;
                                $pmax = ($pagina_atual < ($total_paginas - $adjacentes)) ? ($pagina_atual + $adjacentes) : $total_paginas;

                                if ($pmin > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string_filtros.'&pagina=1">1</a></li>';
                                    if ($pmin > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link border-0 text-muted">...</span></li>';
                                    }
                                }

                                for ($i = $pmin; $i <= $pmax; $i++) {
                                    $active = ($pagina_atual == $i) ? 'active' : '';
                                    echo '<li class="page-item '.$active.'"><a class="page-link" href="?'.$query_string_filtros.'&pagina='.$i.'">'.$i.'</a></li>';
                                }

                                if ($pmax < $total_paginas) {
                                    if ($pmax < $total_paginas - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link border-0 text-muted">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string_filtros.'&pagina='.$total_paginas.'">'.$total_paginas.'</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= $query_string_filtros ?>&pagina=<?= $pagina_atual + 1 ?>" aria-label="Próxima">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if(toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));

        setTimeout(() => {
            const alertBox = document.getElementById('successAlert');
            if (alertBox) {
                alertBox.classList.remove('show');
                setTimeout(() => alertBox.remove(), 200);
            }
        }, 4000);

        // AJAX Search
        const campoPesquisa = document.getElementById('campo-pesquisa');
        const conteudoTabela = document.getElementById('conteudo-tabela');
        let timer;

        campoPesquisa.addEventListener('input', function() {
            clearTimeout(timer); 
            timer = setTimeout(() => {
                const termo = this.value;
                const url = `list.php?search=${encodeURIComponent(termo)}&pagina=1`;
                conteudoTabela.style.opacity = '0.5';
                fetch(url).then(r => r.text()).then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    conteudoTabela.innerHTML = doc.getElementById('conteudo-tabela').innerHTML;
                    conteudoTabela.style.opacity = '1';
                    window.history.pushState({}, '', url);
                });
            }, 300);
        });
    </script>
</body>
</html>