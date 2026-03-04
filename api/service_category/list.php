<?php
session_start();

// Configuração de sessão
include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

// ============================================================================
// LÓGICA DE APAGAR
// ============================================================================
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    // Verifica se existem serviços associados a esta categoria
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
// LÓGICA DE PESQUISA
// ============================================================================
$where = "";
if(isset($_GET['search']) && !empty(trim($_GET['search']))){
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $where = "WHERE categoria.designacao LIKE '%$search%'";
}

// ============================================================================
// CONFIGURAÇÕES DE PAGINAÇÃO
// ============================================================================
$registos_por_pagina = 10; 
$pagina_atual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $registos_por_pagina;

// 1. Contar o total de categorias
$total_registos = 0;
$query_count = "SELECT COUNT(*) as total FROM categoria $where";
$resultado_count = mysqli_query($conn, $query_count);

if ($resultado_count) {
    $row_count = mysqli_fetch_assoc($resultado_count);
    $total_registos = intval($row_count['total']);
}

// 2. Calcular o número total de páginas
$total_paginas = ceil($total_registos / $registos_por_pagina);

// ============================================================================
// QUERY PRINCIPAL (COM LIMIT E OFFSET)
// ============================================================================
$query = "SELECT * FROM categoria $where ORDER BY categoria.designacao ASC LIMIT $registos_por_pagina OFFSET $offset";
$result = mysqli_query($conn, $query);

// Criar string de URL com os filtros atuais para usar nos links da paginação
$params = $_GET;
unset($params['pagina'], $params['delete'], $params['msg']);
$query_string_filtros = http_build_query($params);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categorias - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service_category/list.css">

    <style>
        .pagination .page-link { color: #2e7d32; border-color: #dee2e6; }
        .pagination .page-link:hover { background-color: #e8f5e9; color: #1b5e20; }
        .pagination .page-item.active .page-link { background-color: #2e7d32; border-color: #2e7d32; color: #fff; }
        .pagination .page-item.disabled .page-link { color: #6c757d; }
        #conteudo-tabela { transition: opacity 0.2s ease-in-out; }
    </style>
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
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <div class="header-actions">
                <div>
                    <h2 class="mb-1">Categorias</h2>
                    <p class="text-muted mb-0">Organização dos serviços.</p>
                </div>
                
                <div class="d-flex gap-3">
                    <form class="d-flex" action="" method="GET" id="form-pesquisa">
                        <input type="text" id="campo-pesquisa" name="search" autocomplete="off" class="form-control me-2" placeholder="Pesquisar..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        <input type="hidden" name="pagina" value="1">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    </form>

                    <a href="create.php" class="btn-create">
                        <i class="bi bi-plus-lg"></i> Nova Categoria
                    </a>
                </div>
            </div>

            <?php if(isset($erro_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $erro_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show" id="successAlert">
                    Categoria removida.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card-list" id="conteudo-tabela">
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead>
                            <tr>
                                <th width="70%">Designação</th>
                                <th width="30%" class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['designacao']) ?></div>
                                    </td>
                                    <td class="text-end">
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?= $row['id'] ?>" class="btn-action view" title="Visualizar"><i class="bi bi-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn-action edit" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                            <a href="list.php?delete=<?= $row['id'] ?>" class="btn-action delete" onclick="return confirm('Tem a certeza?')" title="Apagar"><i class="bi bi-trash-fill"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center py-5 text-muted">
                                        <i class="bi bi-tags display-4 d-block mb-3 opacity-50"></i>
                                        Nenhuma categoria encontrada.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            A mostrar página <?= $pagina_atual ?> de <?= $total_paginas ?> (Total: <?= $total_registos ?> categorias)
                        </span>
                        
                        <nav aria-label="Navegação de páginas de categorias">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= ($pagina_atual <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= $query_string_filtros ?>&pagina=<?= $pagina_atual - 1 ?>" aria-label="Anterior">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?= ($pagina_atual == $i) ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= $query_string_filtros ?>&pagina=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= ($pagina_atual >= $total_paginas) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= $query_string_filtros ?>&pagina=<?= $pagina_atual + 1 ?>" aria-label="Próxima">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
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

        // Limpar alerta de sucesso automaticamente
        setTimeout(() => {
            const alertBox = document.getElementById('successAlert');
            if (alertBox) {
                alertBox.classList.remove('show');
                setTimeout(() => alertBox.remove(), 200);
                
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.delete('msg');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }
        }, 4000);

        // ====================================================================
        // PESQUISA EM TEMPO REAL (AJAX) - Adaptada para Paginação
        // ====================================================================
        const campoPesquisa = document.getElementById('campo-pesquisa');
        const formPesquisa = document.getElementById('form-pesquisa');
        const conteudoTabela = document.getElementById('conteudo-tabela');
        let timer;

        if (campoPesquisa) {
            if(formPesquisa) {
                formPesquisa.addEventListener('submit', function(e) {
                    e.preventDefault();
                });
            }

            campoPesquisa.addEventListener('input', function() {
                clearTimeout(timer); 
                
                timer = setTimeout(() => {
                    const termo = this.value;
                    const url = `list.php?search=${encodeURIComponent(termo)}&pagina=1`;

                    conteudoTabela.style.opacity = '0.5';

                    fetch(url)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            
                            const novaTabela = doc.getElementById('conteudo-tabela').innerHTML;
                            conteudoTabela.innerHTML = novaTabela;
                            conteudoTabela.style.opacity = '1';
                            
                            const novaUrl = termo ? `list.php?search=${encodeURIComponent(termo)}&pagina=1` : 'list.php';
                            window.history.pushState({}, '', novaUrl);
                        })
                        .catch(error => {
                            console.error('Erro na pesquisa:', error);
                            conteudoTabela.style.opacity = '1';
                        });
                }, 300);
            });
        }
    </script>
</body>
</html>