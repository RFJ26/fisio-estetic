<?php
// ============================================================================
// INICIALIZAÇÃO E CONFIGURAÇÕES DE BASE
// ============================================================================
session_start();
date_default_timezone_set('Europe/Lisbon'); 

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/send_email.php';

mysqli_set_charset($conn, "utf8");

$hoje = date('Y-m-d');

// Auto-atualização de estados
$query_auto_update = "UPDATE marcacao SET estado = 'realizada' WHERE data < '$hoje' AND estado IN ('ativa', 'por confirmar')";
mysqli_query($conn, $query_auto_update);

// Ações dos botões (Confirmar, Cancelar, Concluir)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id_marcacao = intval($_GET['id']);
    $acao = $_GET['action'];
    $novo_estado = '';
    $mensagem = '';

    if ($acao === 'confirm') { 
        $novo_estado = 'ativa'; 
        $mensagem = 'Marcação confirmada!';
    }
    elseif ($acao === 'cancel') { 
        $novo_estado = 'cancelada'; 
        $mensagem = 'Marcação cancelada.';
    }
    elseif ($acao === 'complete') { 
        $novo_estado = 'realizada'; 
        $mensagem = 'Serviço concluído!';
    }

    if ($novo_estado !== '') {
        $stmt = mysqli_prepare($conn, "UPDATE marcacao SET estado = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $novo_estado, $id_marcacao);
        mysqli_stmt_execute($stmt);
        
        enviarEmailEstado($conn, $id_marcacao, $novo_estado);

        header("Location: list.php?msg=" . urlencode($mensagem)); 
        exit;
    }
}

// Filtros, Pesquisa e Ordenação
$filtro_data = $_GET['data'] ?? 'future';
$filtro_estado = $_GET['estado'] ?? 'all';
$filtro_ocultar = isset($_GET['ocultar']) && is_array($_GET['ocultar']) ? $_GET['ocultar'] : []; 
$filtro_ordem = $_GET['ordenacao'] ?? 'asc'; 
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$condicao = "WHERE 1=1";

if ($filtro_data == 'today') { $condicao .= " AND marcacao.data = '$hoje'"; }
elseif ($filtro_data == 'future') { $condicao .= " AND marcacao.data >= '$hoje'"; }

if ($filtro_estado != 'all') {
    $est = mysqli_real_escape_string($conn, $filtro_estado);
    $condicao .= " AND marcacao.estado = '$est'";
}

// Lógica para Ocultar Estados
if (!empty($filtro_ocultar)) {
    $estados_ocultos = [];
    foreach ($filtro_ocultar as $occ) {
        if (in_array($occ, ['ativa', 'cancelada', 'realizada', 'por confirmar'])) {
            $estados_ocultos[] = "'" . mysqli_real_escape_string($conn, $occ) . "'";
        }
    }
    
    if (count($estados_ocultos) > 0) {
        $lista_in = implode(",", $estados_ocultos);
        $condicao .= " AND marcacao.estado NOT IN ($lista_in)";
    }
}

if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $condicao .= " AND (cliente.nome LIKE '%$s%' OR funcionario.nome LIKE '%$s%' OR servico.designacao LIKE '%$s%')";
}

// Definir a cláusula ORDER BY
$clausula_ordem = "ORDER BY marcacao.data ASC, marcacao.slot_inicial ASC";
if ($filtro_ordem === 'desc') {
    $clausula_ordem = "ORDER BY marcacao.data DESC, marcacao.slot_inicial DESC";
}

// Paginação
$registos_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $registos_por_pagina;

$query_count = "
    SELECT COUNT(marcacao.id) AS total
    FROM marcacao
    INNER JOIN cliente ON marcacao.id_cliente = cliente.id
    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
    INNER JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
    INNER JOIN servico ON servico_funcionario.id_servico = servico.id
    $condicao
";
$resultado_count = mysqli_query($conn, $query_count);
$total_registos = mysqli_fetch_assoc($resultado_count)['total'];
$total_paginas = ceil($total_registos / $registos_por_pagina);

// Query Principal
$query = "
    SELECT 
        marcacao.id, marcacao.data, marcacao.slot_inicial, marcacao.estado,
        cliente.nome AS nome_cliente, cliente.telefone,
        funcionario.nome AS nome_funcionario,
        servico.designacao AS nome_servico
    FROM marcacao
    INNER JOIN cliente ON marcacao.id_cliente = cliente.id
    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
    INNER JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
    INNER JOIN servico ON servico_funcionario.id_servico = servico.id
    $condicao
    $clausula_ordem
    LIMIT $registos_por_pagina OFFSET $offset
";
$resultado = mysqli_query($conn, $query);

$params = $_GET;
unset($params['pagina'], $params['action'], $params['id'], $params['msg']);
$query_string_filtros = http_build_query($params);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agenda - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/booking/list.css"> 
    
    <style>
        .dropdown-menu-checkboxes .form-check { padding-left: 2rem; margin-bottom: 0.2rem; }
        .dropdown-menu-checkboxes .form-check-label { cursor: pointer; width: 100%; display: block; }
        .dropdown-menu-checkboxes .form-check-input { cursor: pointer; }
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
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            
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
        <h1 class="h3 mb-1 fw-bold text-dark">Agenda</h1>
        <p class="text-muted mb-0">Gestão de todas as marcações.</p>
    </div>
    
    <a href="create.php" class="btn-add">
        <i class="bi bi-plus-lg"></i> <span class="d-none d-sm-inline">Nova Marcação</span>
    </a>
</div>

<div class="filter-bar bg-white p-3 rounded-4 shadow-sm border mb-4" style="border-color: #f0f0f0 !important;">
    <form method="GET" class="row g-3 align-items-center" id="form-filtros">
        <div class="col-12 col-md-12 col-lg-3">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="campo-pesquisa" name="search" autocomplete="off" placeholder="Procurar cliente..." value="<?= htmlspecialchars($search) ?>">
            </div>
        </div>
        
        <div class="col-6 col-md-3 col-lg-2">
            <select name="data" id="filtro-data" class="form-select filter-select w-100">
                <option value="future" <?= $filtro_data == 'future' ? 'selected' : '' ?>>Futuras</option>
                <option value="today" <?= $filtro_data == 'today' ? 'selected' : '' ?>>Hoje</option>
                <option value="all" <?= $filtro_data == 'all' ? 'selected' : '' ?>>Todas</option>
            </select>
        </div>
        
        <div class="col-6 col-md-3 col-lg-2">
            <select name="estado" id="filtro-estado" class="form-select filter-select w-100">
                <option value="all" <?= $filtro_estado == 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="por confirmar" <?= $filtro_estado == 'por confirmar' ? 'selected' : '' ?>>Por Confirmar</option>
                <option value="ativa" <?= $filtro_estado == 'ativa' ? 'selected' : '' ?>>Por realizar</option>
                <option value="realizada" <?= $filtro_estado == 'realizada' ? 'selected' : '' ?>>Realizadas</option>
                <option value="cancelada" <?= $filtro_estado == 'cancelada' ? 'selected' : '' ?>>Canceladas</option>
            </select>
        </div>

        <div class="col-6 col-md-3 col-lg-2">
            <div class="dropdown w-100">
                <button class="form-select filter-select text-start w-100 d-flex justify-content-between align-items-center bg-white" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="color: #dc2626;" id="btn-ocultar-dropdown">
                    <?= count($filtro_ocultar) > 0 ? count($filtro_ocultar) . ' Oculto(s)' : 'Ocultar...' ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-checkboxes w-100 shadow-sm p-2" onclick="event.stopPropagation()">
                    <li>
                        <div class="form-check">
                            <input class="form-check-input filtro-ocultar-chk" type="checkbox" name="ocultar[]" value="cancelada" id="chk-canc" <?= in_array('cancelada', $filtro_ocultar) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chk-canc">Canceladas</label>
                        </div>
                    </li>
                    <li>
                        <div class="form-check">
                            <input class="form-check-input filtro-ocultar-chk" type="checkbox" name="ocultar[]" value="realizada" id="chk-real" <?= in_array('realizada', $filtro_ocultar) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chk-real">Realizadas</label>
                        </div>
                    </li>
                    <li>
                        <div class="form-check">
                            <input class="form-check-input filtro-ocultar-chk" type="checkbox" name="ocultar[]" value="por confirmar" id="chk-pend" <?= in_array('por confirmar', $filtro_ocultar) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chk-pend">Por Confirmar</label>
                        </div>
                    </li>
                    <li>
                        <div class="form-check">
                            <input class="form-check-input filtro-ocultar-chk" type="checkbox" name="ocultar[]" value="ativa" id="chk-ativa" <?= in_array('ativa', $filtro_ocultar) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chk-ativa">Por realizar</label>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-6 col-md-3 col-lg-3">
            <select name="ordenacao" id="filtro-ordem" class="form-select filter-select w-100">
                <option value="asc" <?= $filtro_ordem == 'asc' ? 'selected' : '' ?>>Mais Antigas Primeiro</option>
                <option value="desc" <?= $filtro_ordem == 'desc' ? 'selected' : '' ?>>Mais Recentes Primeiro</option>
            </select>
        </div>
        
        <input type="hidden" name="pagina" id="pagina-atual" value="1">
    </form>
</div>

            <div id="conteudo-tabela">
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table custom-table mb-0">
                            <thead>
                                <tr>
                                    <th width="15%">Data / Hora</th>
                                    <th width="25%">Cliente</th>
                                    <th width="25%">Serviço / Staff</th>
                                    <th width="15%">Estado</th>
                                    <th width="20%" class="text-end pe-4">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($resultado) == 0): ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">Nenhuma marcação encontrada.</td></tr>
                                <?php endif; ?>

                                <?php while($row = mysqli_fetch_assoc($resultado)): 
                                    $est = mb_strtolower(trim($row['estado']), 'UTF-8'); 
                                    
                                    // Mapeamento original para as classes do teu list.css
                                    if ($est === 'por confirmar') {
                                        $status_class = 'st-pendente';
                                    } else {
                                        $status_class = 'st-' . str_replace(' ', '-', $est);
                                    }

                                    // Display do texto do estado na tabela
                                    $display_estado = $row['estado'];
                                    if ($est === 'ativa') {
                                        $display_estado = 'Por realizar';
                                    }

                                    $hora = function_exists('converterSlotParaHora') ? converterSlotParaHora($row['slot_inicial']) : $row['slot_inicial'];
                                    $data = date('d/m/Y', strtotime($row['data']));
                                    
                                    // Bloqueia edição/cancelamento em estados finais
                                    $is_readonly = in_array($est, ['realizada', 'cancelada']);
                                    $pode_concluir = ($row['data'] <= $hoje); 
                                ?>
                                    <tr>
                                        <td data-label="Data / Hora">
                                            <div class="date-day">
                                                <?= date('d', strtotime($row['data'])) ?> 
                                                <small><?= date('M Y', strtotime($row['data'])) ?></small>
                                            </div>
                                            <div class="date-time"><i class="bi bi-clock me-1"></i><?= $hora ?></div>
                                        </td>
                                        <td data-label="Cliente">
                                            <span class="client-name fw-bold text-dark"><?= htmlspecialchars($row['nome_cliente']) ?></span>
                                            <small class="text-muted d-block"><?= htmlspecialchars($row['telefone']) ?></small>
                                        </td>
                                        <td data-label="Serviço">
                                            <span class="service-name fw-medium text-dark"><?= htmlspecialchars($row['nome_servico']) ?></span><br>
                                            <small class="text-muted fst-italic"><?= htmlspecialchars($row['nome_funcionario']) ?></small>
                                        </td>
                                        <td data-label="Estado">
                                            <span class="status-badge <?= $status_class ?> text-capitalize"><?= $display_estado ?></span>
                                        </td>
                                        <td data-label="Ações" class="text-end pe-4">
                                            <div class="action-buttons">
                                                <button type="button" class="btn-icon btn-view" data-bs-toggle="modal" data-bs-target="#modalDetalhes"
                                                    data-cliente="<?= htmlspecialchars($row['nome_cliente']) ?>"
                                                    data-servico="<?= htmlspecialchars($row['nome_servico']) ?>"
                                                    data-profissional="<?= htmlspecialchars($row['nome_funcionario']) ?>"
                                                    data-data="<?= $data ?>" data-hora="<?= $hora ?>" data-estado="<?= $display_estado ?>" title="Ver">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                                <?php if (!$is_readonly): ?>
                                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn-icon btn-edit" title="Editar"><i class="bi bi-pencil-square"></i></a>
                                                <?php endif; ?>

                                                <?php if($est === 'por confirmar'): ?>
                                                    <a href="list.php?action=confirm&id=<?= $row['id'] ?>" class="btn-icon btn-confirm" title="Confirmar"><i class="bi bi-check-lg"></i></a>
                                                <?php endif; ?>

                                                <?php if($est === 'ativa'): ?>
                                                    <a href="list.php?action=complete&id=<?= $row['id'] ?>" class="btn-icon btn-complete" title="Concluir"><i class="bi bi-check-all"></i></a>
                                                <?php endif; ?>

                                                <?php if(!$is_readonly): ?>
                                                    <a href="list.php?action=cancel&id=<?= $row['id'] ?>" class="btn-icon btn-cancel" onclick="return confirm('Tem a certeza que deseja cancelar esta marcação?')" title="Cancelar"><i class="bi bi-x-lg"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
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
    </div>

    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom p-4">
                    <h5 class="modal-title fw-bold text-dark">Detalhes da Marcação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4"><span id="modal-estado" class="status-badge text-capitalize">Estado</span></div>
                    <div class="row g-4">
                        <div class="col-6">
                            <span class="text-muted d-block small fw-bold text-uppercase mb-1">Cliente</span>
                            <div class="fw-medium text-dark" id="modal-cliente"></div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small fw-bold text-uppercase mb-1">Serviço</span>
                            <div class="fw-medium text-dark" id="modal-servico"></div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small fw-bold text-uppercase mb-1">Data</span>
                            <div class="fw-medium text-dark" id="modal-data"></div>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block small fw-bold text-uppercase mb-1">Hora</span>
                            <div class="fw-medium text-dark" id="modal-hora"></div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted d-block small fw-bold text-uppercase mb-1">Profissional</span>
                            <div class="fw-medium text-dark" id="modal-profissional"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div class="toast align-items-center text-bg-success border-0" id="toastNotification" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-medium" id="toastMessage">Ação realizada com sucesso!</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        if(sidebarToggle) sidebarToggle.addEventListener('click', () => document.querySelector('.sidebar').classList.toggle('active'));

        const modalDetalhes = document.getElementById('modalDetalhes');
        if (modalDetalhes) {
            modalDetalhes.addEventListener('show.bs.modal', event => {
                const btn = event.relatedTarget;
                modalDetalhes.querySelector('#modal-cliente').textContent = btn.getAttribute('data-cliente');
                modalDetalhes.querySelector('#modal-servico').textContent = btn.getAttribute('data-servico');
                modalDetalhes.querySelector('#modal-profissional').textContent = btn.getAttribute('data-profissional');
                modalDetalhes.querySelector('#modal-data').textContent = btn.getAttribute('data-data');
                modalDetalhes.querySelector('#modal-hora').textContent = btn.getAttribute('data-hora');
                
                const badge = modalDetalhes.querySelector('#modal-estado');
                const estTxt = btn.getAttribute('data-estado');
                badge.textContent = estTxt;
                badge.className = 'status-badge text-capitalize'; 
                
                // Mapeamento original para as tuas classes CSS
                const est = estTxt.toLowerCase().trim();
                if(['ativa','confirmada', 'por realizar'].includes(est)) badge.classList.add('st-ativa');
                else if(['pendente','por confirmar'].includes(est)) badge.classList.add('st-pendente');
                else if(['cancelada'].includes(est)) badge.classList.add('st-cancelada');
                else if(['realizada','concluida'].includes(est)) badge.classList.add('st-realizada');
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        if (msg) {
            const toastEl = document.getElementById('toastNotification');
            document.getElementById('toastMessage').textContent = msg;
            new bootstrap.Toast(toastEl).show();
            setTimeout(() => {
                urlParams.delete('msg');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }, 300);
        }

        const formFiltros = document.getElementById('form-filtros');
        const campoPesquisa = document.getElementById('campo-pesquisa');
        const filtroData = document.getElementById('filtro-data');
        const filtroEstado = document.getElementById('filtro-estado');
        const filtroOrdem = document.getElementById('filtro-ordem');
        const btnOcultarDropdown = document.getElementById('btn-ocultar-dropdown');
        const checkboxesOcultar = document.querySelectorAll('.filtro-ocultar-chk');
        const conteudoTabela = document.getElementById('conteudo-tabela');
        const paginaAtual = document.getElementById('pagina-atual');
        let timer;

        function atualizarTabela() {
            const formData = new FormData(formFiltros);
            const params = new URLSearchParams(formData);
            const url = `list.php?${params.toString()}`;
            conteudoTabela.style.opacity = '0.5';
            fetch(url).then(r => r.text()).then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                conteudoTabela.innerHTML = doc.getElementById('conteudo-tabela').innerHTML;
                conteudoTabela.style.opacity = '1';
                window.history.pushState({}, '', url);
            });
        }

        function atualizarTextoOcultar() {
            let selecionados = 0;
            checkboxesOcultar.forEach(chk => { if(chk.checked) selecionados++; });
            btnOcultarDropdown.textContent = selecionados > 0 ? `${selecionados} Oculto(s)` : 'Ocultar...';
        }

        if (formFiltros) {
            formFiltros.addEventListener('submit', e => e.preventDefault());
            campoPesquisa.addEventListener('input', () => {
                clearTimeout(timer);
                paginaAtual.value = 1; 
                timer = setTimeout(atualizarTabela, 400); 
            });
            filtroData.addEventListener('change', () => { paginaAtual.value = 1; atualizarTabela(); });
            filtroEstado.addEventListener('change', () => { paginaAtual.value = 1; atualizarTabela(); });
            filtroOrdem.addEventListener('change', () => { paginaAtual.value = 1; atualizarTabela(); }); 
            
            checkboxesOcultar.forEach(chk => {
                chk.addEventListener('change', () => {
                    atualizarTextoOcultar();
                    paginaAtual.value = 1; 
                    atualizarTabela();
                });
            });
        }
    </script>
</body>
</html>