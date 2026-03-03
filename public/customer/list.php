<?php
session_start();


include('../verifica_login.php');
require '../../src/conexao.php';

// ============================================================================
// LÓGICA DE APAGAR CLIENTE
// ============================================================================
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    $sql_del = "DELETE FROM cliente WHERE id = '$id'";
    if(mysqli_query($conn, $sql_del)){
        header('Location: list.php?msg=deleted');
        exit();
    }
}

// ============================================================================
// LÓGICA DE PESQUISA
// ============================================================================
$where = "";
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $where = "WHERE nome LIKE '%$search%' OR telefone LIKE '%$search%' OR email LIKE '%$search%'";
}

// ============================================================================
// CONFIGURAÇÕES DE PAGINAÇÃO
// ============================================================================
$registos_por_pagina = 10; 
$pagina_atual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $registos_por_pagina;

// 1. Contar o total de clientes
$total_registos = 0;
$query_count = "SELECT COUNT(*) as total FROM cliente $where";
$resultado_count = mysqli_query($conn, $query_count);

if ($resultado_count) {
    $row_count = mysqli_fetch_assoc($resultado_count);
    $total_registos = intval($row_count['total']);
}

// 2. Calcular o número total de páginas
$total_paginas = ceil($total_registos / $registos_por_pagina);

// ============================================================================
// QUERY PRINCIPAL
// ============================================================================
$query = "SELECT * FROM cliente $where ORDER BY nome ASC LIMIT $registos_por_pagina OFFSET $offset";
$result = mysqli_query($conn, $query);

// Criar string de URL com os filtros atuais para usar nos links da paginação
$params = $_GET;
unset($params['pagina']); 
unset($params['delete']); 
unset($params['msg']);
$query_string_filtros = http_build_query($params);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/customer/list.css">

    <style>
        .pagination .page-link { color: #2e7d32; border-color: #dee2e6; }
        .pagination .page-link:hover { background-color: #e8f5e9; color: #1b5e20; }
        .pagination .page-item.active .page-link { background-color: #2e7d32; border-color: #2e7d32; color: #fff; }
        .pagination .page-item.disabled .page-link { color: #6c757d; }
        
        #conteudo-tabela { transition: opacity 0.2s ease-in-out; }
        
        /* Estilos ajustados para a nova coluna de estado */
        .badge-validacao {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.4rem 0.6rem;
        }
    </style>
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none"><i class="bi bi-list"></i></button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <div class="header-actions">
                <div>
                    <h2 class="fw-bold mb-1">Gestão de Clientes</h2>
                    <p class="text-muted mb-0">Gerir a base de dados de clientes.</p>
                </div>
                
                <div class="d-flex gap-3 align-items-center">
                    <form class="search-box" action="" method="GET" id="form-pesquisa">
                        <i class="bi bi-search"></i>
                        <input type="text" id="campo-pesquisa" name="search" class="form-control" autocomplete="off" placeholder="Procurar nome ou telemóvel..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        <input type="hidden" name="pagina" value="1">
                    </form>

                    <a href="create.php" class="btn-add">
                        <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Novo Cliente</span>
                    </a>
                </div>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
                    <i class="bi bi-check-circle me-2"></i> Cliente removido com sucesso.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card-list" id="conteudo-tabela">
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0">
                        <thead>
                            <tr>
                                <th width="30%">Nome</th>
                                <th width="25%">Contactos</th>
                                <th width="15%">Estado Conta</th> <th width="20%">Observações</th>
                                <th width="10%" class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($result && mysqli_num_rows($result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result)): 
                                    $nomes = explode(" ", trim($row['nome']));
                                    $iniciais = strtoupper(substr($nomes[0], 0, 1));
                                    if(count($nomes) > 1) { 
                                        $ultimo_nome = end($nomes);
                                        $iniciais .= strtoupper(substr($ultimo_nome, 0, 1)); 
                                    }
                                ?>
                                <tr>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle"><?= $iniciais ?></div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['nome']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="align-middle">
                                        <div class="d-flex flex-column gap-1">
                                            <span style="font-size:0.9rem"><i class="bi bi-phone me-2 text-muted"></i><?= htmlspecialchars($row['telefone']) ?></span>
                                            
                                            <?php if(!empty($row['email'])): ?>
                                                <span class="text-muted text-truncate" style="font-size: 0.85em; max-width: 200px;" title="<?= htmlspecialchars($row['email']) ?>">
                                                    <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($row['email']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="align-middle">
                                        <?php if(!empty($row['email'])): ?>
                                            <?php if(isset($row['email_verificado']) && $row['email_verificado'] == 1): ?>
                                                <span class="badge rounded-pill text-success bg-success-subtle border border-success-subtle badge-validacao" title="Email validado pelo cliente">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Validada
                                                </span>
                                            <?php else: ?>
                                                <span class="badge rounded-pill text-warning bg-warning-subtle border border-warning-subtle badge-validacao" title="A aguardar validação de email">
                                                    <i class="bi bi-clock-fill me-1"></i> Pendente
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="align-middle">
                                        <span class="text-muted small">
                                            <?= !empty($row['obs']) ? substr(htmlspecialchars($row['obs']), 0, 40) . '...' : '-' ?>
                                        </span>
                                    </td>
                                    
                                    <td class="align-middle text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="view.php?id=<?= $row['id'] ?>" class="btn-action view" title="Ver Detalhes"><i class="bi bi-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn-action edit" title="Editar"><i class="bi bi-pencil"></i></a>
                                            <a href="list.php?delete=<?= $row['id'] ?>" class="btn-action delete" onclick="return confirm('Tem a certeza que deseja apagar este cliente?')" title="Apagar"><i class="bi bi-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-people display-4 d-block mb-3 opacity-50"></i>
                                        Nenhum cliente encontrado.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            A mostrar página <?= $pagina_atual ?> de <?= $total_paginas ?> (Total: <?= $total_registos ?> clientes)
                        </span>
                        
                        <nav aria-label="Navegação de páginas de clientes">
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
        // Funcionalidades visuais básicas (Sidebar e Alertas)
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle'); 
        if(toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));

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
        // MAGIA DA PESQUISA EM TEMPO REAL (AJAX)
        // ====================================================================
        const campoPesquisa = document.getElementById('campo-pesquisa');
        const formPesquisa = document.getElementById('form-pesquisa');
        const conteudoTabela = document.getElementById('conteudo-tabela');
        let timer; 

        if (campoPesquisa) {
            
            if(formPesquisa) {
                formPesquisa.addEventListener('submit', function(e) { e.preventDefault(); });
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