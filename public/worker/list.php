<?php
session_start();
include('../verifica_login.php');
require_once __DIR__ . '/../../src/conexao.php';

// ============================================================================
// LÓGICA DE APAGAR
// ============================================================================
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    try {
        $sql_del = "DELETE FROM funcionario WHERE id = '$id'";
        if(mysqli_query($conn, $sql_del)){
            header('Location: list.php?msg=deleted');
            exit();
        }
    } catch (Exception $e) {
        $erro_msg = "Não é possível apagar: O funcionário tem marcações ou dados associados.";
    }
}

// ============================================================================
// LÓGICA DE PESQUISA
// ============================================================================
$where = "";
if(isset($_GET['search']) && !empty(trim($_GET['search']))){
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $where = "WHERE nome LIKE '%$search%' OR email LIKE '%$search%' OR telefone LIKE '%$search%'";
}

// ============================================================================
// CONFIGURAÇÕES DE PAGINAÇÃO
// ============================================================================
$registos_por_pagina = 10; 
$pagina_atual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $registos_por_pagina;

// 1. Contar o total de funcionários
$total_registos = 0;
$query_count = "SELECT COUNT(*) as total FROM funcionario $where";
$resultado_count = mysqli_query($conn, $query_count);

if ($resultado_count) {
    $row_count = mysqli_fetch_assoc($resultado_count);
    $total_registos = intval($row_count['total']);
}

$total_paginas = ceil($total_registos / $registos_por_pagina);

// ============================================================================
// QUERY PRINCIPAL (COM LIMIT E OFFSET)
// ============================================================================
$query = "SELECT id, nome, email, telefone, adm 
          FROM funcionario 
          $where 
          ORDER BY nome ASC 
          LIMIT $registos_por_pagina OFFSET $offset";
$funcionarios = mysqli_query($conn, $query);

$params = $_GET;
unset($params['pagina'], $params['delete'], $params['msg']);
$query_string_filtros = http_build_query($params);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerir Funcionários - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/list.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none">
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
            
            <div class="header-actions">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-dark">Equipa</h1>
                    <p class="text-muted mb-0">Gestão de funcionários e permissões.</p>
                </div>
                
                <div class="d-flex gap-3 align-items-center flex-wrap">
                    <form class="search-box" action="" method="GET" id="form-pesquisa">
                        <i class="bi bi-search"></i>
                        <input type="text" id="campo-pesquisa" name="search" autocomplete="off" placeholder="Procurar funcionário..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                        <input type="hidden" name="pagina" value="1">
                    </form>

                    <a href="create.php" class="btn-add">
                        <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Novo Funcionário</span>
                    </a>
                </div>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" id="successAlert">
                    <i class="bi bi-check-circle me-2"></i> Funcionário removido com sucesso.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($erro_msg)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?= $erro_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-container" id="conteudo-tabela">
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th width="35%">Nome</th>
                                <th width="25%">Email</th>
                                <th width="20%">Telefone</th>
                                <th width="20%" class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($funcionarios) > 0): ?>
                                <?php while($funcionario = mysqli_fetch_assoc($funcionarios)): 
                                    // Gerar iniciais para o Avatar (Corrigido para lidar com acentos)
                                    $nomes = explode(" ", trim($funcionario['nome']));
                                    $iniciais = mb_strtoupper(mb_substr($nomes[0], 0, 1, 'UTF-8'), 'UTF-8');
                                    if(count($nomes) > 1) { 
                                        $ultimo_nome = end($nomes);
                                        $iniciais .= mb_strtoupper(mb_substr($ultimo_nome, 0, 1, 'UTF-8'), 'UTF-8'); 
                                    }
                                ?> 
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle"><?= $iniciais ?></div>
                                            <div>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($funcionario['nome']) ?></div>
                                                <?php if($funcionario['adm']): ?>
                                                    <small class="text-success fw-medium"><i class="bi bi-shield-check"></i> Admin</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-muted"><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($funcionario['email']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-muted"><i class="bi bi-phone me-2"></i><?= htmlspecialchars($funcionario['telefone']) ?></div>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="action-buttons justify-content-end">
                                            <a href="view_indisponibilidade.php?id=<?= $funcionario['id'] ?>" class="btn-icon btn-indisp" title="Gerir Indisponibilidades">
                                                <i class="bi bi-calendar-minus"></i>
                                            </a>
                                            <a href="assign_services.php?id=<?= $funcionario['id'] ?>" class="btn-icon btn-assign" title="Atribuir Serviços">
                                                <i class="bi bi-clipboard-plus"></i>
                                            </a>
                                            <a href="view.php?id=<?= $funcionario['id'] ?>" class="btn-icon btn-view" title="Ver Detalhes">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $funcionario['id'] ?>" class="btn-icon btn-edit" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="list.php?delete=<?= $funcionario['id'] ?>" class="btn-icon btn-delete" title="Apagar" onclick="return confirm('Tem a certeza que deseja apagar este funcionário?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-person-x display-4 d-block mb-3 opacity-50"></i>
                                        Nenhum funcionário encontrado.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_paginas > 1): ?>
                    <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                        <span class="text-muted small">
                            A mostrar página <?= $pagina_atual ?> de <?= $total_paginas ?> (Total: <?= $total_registos ?> funcionários)
                        </span>
                        
                        <nav aria-label="Navegação de páginas">
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

        // AJAX Pesquisa
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