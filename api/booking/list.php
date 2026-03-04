<?php
// ============================================================================
// INICIALIZAÇÃO E CONFIGURAÇÕES DE BASE
// ============================================================================
session_start();
date_default_timezone_set('Europe/Lisbon'); // Fuso horário de Portugal

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/send_email.php';

mysqli_set_charset($conn, "utf8");

$hoje = date('Y-m-d');

// ============================================================================
// AUTO-ATUALIZAÇÃO DE ESTADOS (CRON JOB MANUAL)
// Passa marcações de dias anteriores que ficaram pendentes/ativas para "realizada"
// ============================================================================
$query_auto_update = "UPDATE marcacao SET estado = 'realizada' WHERE data < '$hoje' AND estado IN ('ativa', 'por confirmar', 'pendente')";
mysqli_query($conn, $query_auto_update);

// ============================================================================
// AÇÕES DOS BOTÕES DA TABELA (CONFIRMAR, CANCELAR, CONCLUIR)
// ============================================================================
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

// ============================================================================
// RECOLHA DE FILTROS E PESQUISA
// ============================================================================
$filtro_data = $_GET['data'] ?? 'future';
$filtro_estado = $_GET['estado'] ?? 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$condicao = "WHERE 1=1";

// Filtro de Datas
if ($filtro_data == 'today') { $condicao .= " AND marcacao.data = '$hoje'"; }
elseif ($filtro_data == 'future') { $condicao .= " AND marcacao.data >= '$hoje'"; }

// Filtro de Estados
if ($filtro_estado != 'all') {
    $est = mysqli_real_escape_string($conn, $filtro_estado);
    $condicao .= " AND marcacao.estado = '$est'";
}

// Filtro de Pesquisa Livre (Cliente, Funcionário ou Serviço)
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $condicao .= " AND (cliente.nome LIKE '%$s%' OR funcionario.nome LIKE '%$s%' OR servico.designacao LIKE '%$s%')";
}

// ============================================================================
// CONFIGURAÇÃO DA PAGINAÇÃO
// ============================================================================
$registos_por_pagina = 10;
$pagina_atual = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
if ($pagina_atual < 1) $pagina_atual = 1;
$offset = ($pagina_atual - 1) * $registos_por_pagina;

// Conta o total de registos baseados na pesquisa/filtros
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

// ============================================================================
// QUERY PRINCIPAL (Busca os dados para a tabela)
// ============================================================================
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
    ORDER BY marcacao.data ASC, marcacao.slot_inicial ASC
    LIMIT $registos_por_pagina OFFSET $offset
";
$resultado = mysqli_query($conn, $query);

// Guarda os parâmetros da URL para aplicar nos links da paginação
$params = $_GET;
unset($params['pagina'], $params['action'], $params['id'], $params['msg']);
$query_string_filtros = http_build_query($params);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agenda | Fisioestetic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/booking/list.css"> </head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>
    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <div class="header-actions">
                <h2>Gestão de Agenda</h2>
                <div class="d-flex gap-3 align-items-center flex-wrap flex-grow-1 justify-content-end">
                    
                    <form method="GET" class="filter-form" id="form-filtros">
                        <div class="search-wrapper">
                            <i class="bi bi-search search-icon"></i>
                            <input type="text" id="campo-pesquisa" name="search" autocomplete="off" class="filter-input" placeholder="Procurar" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <select name="data" id="filtro-data" class="filter-select">
                            <option value="future" <?= $filtro_data == 'future' ? 'selected' : '' ?>>Futuras</option>
                            <option value="today" <?= $filtro_data == 'today' ? 'selected' : '' ?>>Hoje</option>
                            <option value="all" <?= $filtro_data == 'all' ? 'selected' : '' ?>>Todas</option>
                        </select>
                        
                        <select name="estado" id="filtro-estado" class="filter-select">
                            <option value="all" <?= $filtro_estado == 'all' ? 'selected' : '' ?>>Todos Estados</option>
                            <option value="por confirmar" <?= $filtro_estado == 'por confirmar' ? 'selected' : '' ?>>Pendentes</option>
                            <option value="ativa" <?= $filtro_estado == 'ativa' ? 'selected' : '' ?>>Confirmadas</option>
                        </select>
                        
                        <input type="hidden" name="pagina" id="pagina-atual" value="1">
                    </form>

                    <a href="create.php" class="btn-create"><i class="bi bi-plus-lg"></i> Nova Marcação</a>
                </div>
            </div>

            <div id="conteudo-tabela">
                <div class="card-list">
                    <div class="table-responsive">
                        <table class="table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>Data / Hora</th>
                                    <th>Cliente</th>
                                    <th>Serviço / Staff</th>
                                    <th>Estado</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($resultado) == 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-calendar-x display-4 d-block mb-3 opacity-50"></i>
                                            Nenhuma marcação encontrada com os filtros atuais.
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php while($row = mysqli_fetch_assoc($resultado)): 
                                    $est = mb_strtolower(trim($row['estado']), 'UTF-8'); 
                                    $status_class = 'st-' . str_replace(' ', '-', $est);
                                    
                                    // Se a função não existir, usa a slot normal
                                    $hora = function_exists('converterSlotParaHora') ? converterSlotParaHora($row['slot_inicial']) : $row['slot_inicial'];
                                    
                                    $data = date('d/m/Y', strtotime($row['data']));
                                    
                                    // Estados que não podem ser alterados/editados
                                    $is_readonly = in_array($est, ['realizada', 'concluida', 'cancelada']);
                                    
                                    // Só permite concluir se a data da marcação for de hoje ou anterior
                                    $pode_concluir = ($row['data'] <= $hoje); 
                                ?>
                                    <tr>
                                        <td>
                                            <div class="date-day"><?= date('d', strtotime($row['data'])) ?> <small><?= date('M', strtotime($row['data'])) ?></small></div>
                                            <div class="date-time"><i class="bi bi-clock me-1"></i><?= $hora ?></div>
                                        </td>
                                        <td>
                                            <span class="client-name"><?= htmlspecialchars($row['nome_cliente']) ?></span>
                                            <small class="text-muted d-block"><?= htmlspecialchars($row['telefone']) ?></small>
                                        </td>
                                        <td>
                                            <span class="service-name"><?= htmlspecialchars($row['nome_servico']) ?></span><br>
                                            <small class="text-muted text-italic"><?= htmlspecialchars($row['nome_funcionario']) ?></small>
                                        </td>
                                        <td><span class="status-badge <?= $status_class ?>"><?= $row['estado'] ?></span></td>
                                        <td class="col-actions">
                                            <div class="action-group">
                                                
                                                <button type="button" class="btn-icon btn-view" data-bs-toggle="modal" data-bs-target="#modalDetalhes"
                                                    data-cliente="<?= htmlspecialchars($row['nome_cliente']) ?>"
                                                    data-servico="<?= htmlspecialchars($row['nome_servico']) ?>"
                                                    data-profissional="<?= htmlspecialchars($row['nome_funcionario']) ?>"
                                                    data-data="<?= $data ?>" data-hora="<?= $hora ?>" data-estado="<?= $row['estado'] ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>

                                                <?php if (!$is_readonly): ?>
                                                    <a href="edit.php?id=<?= $row['id'] ?>" class="btn-icon btn-edit" title="Editar"><i class="bi bi-pencil"></i></a>
                                                <?php endif; ?>

                                                <?php if(in_array($est, ['por confirmar', 'pendente'])): ?>
                                                    <a href="list.php?action=confirm&id=<?= $row['id'] ?>" class="btn-icon btn-confirm" title="Confirmar"><i class="bi bi-check-lg"></i></a>
                                                <?php endif; ?>

                                                <?php if(in_array($est, ['ativa', 'confirmada']) && $pode_concluir): ?>
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
                        <div class="card-footer bg-white border-top py-3 px-4 d-flex justify-content-between align-items-center">
                            <span class="text-muted small">
                                A mostrar página <?= $pagina_atual ?> de <?= $total_paginas ?> (Total: <?= $total_registos ?> registos)
                            </span>
                            
                            <nav aria-label="Navegação de páginas da agenda">
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
            </div> </div>
    </div>

    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Marcação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center mb-4"><span id="modal-estado" class="status-badge">Estado</span></div>
                    <div class="row g-3">
                        <div class="col-6"><span class="modal-label text-muted d-block small">Cliente</span><div class="modal-value fw-medium" id="modal-cliente"></div></div>
                        <div class="col-6"><span class="modal-label text-muted d-block small">Serviço</span><div class="modal-value fw-medium" id="modal-servico"></div></div>
                        <div class="col-6"><span class="modal-label text-muted d-block small">Data</span><div class="modal-value fw-medium" id="modal-data"></div></div>
                        <div class="col-6"><span class="modal-label text-muted d-block small">Hora</span><div class="modal-value fw-medium" id="modal-hora"></div></div>
                        <div class="col-12"><span class="modal-label text-muted d-block small">Profissional</span><div class="modal-value fw-medium border-0" id="modal-profissional"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div class="toast align-items-center text-bg-success border-0" id="toastNotification" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage">Ação realizada com sucesso!</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- Toggles para Sidebar Mobile ---
        const sidebarToggle = document.getElementById('sidebarToggle');
        if(sidebarToggle) sidebarToggle.addEventListener('click', () => document.querySelector('.sidebar').classList.toggle('active'));

        // --- Popular Dados no Modal de Detalhes ---
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
                badge.className = 'status-badge'; 
                
                const est = estTxt.toLowerCase().trim();
                if(['ativa','confirmada'].includes(est)) badge.classList.add('st-ativa');
                else if(['pendente','por confirmar'].includes(est)) badge.classList.add('st-pendente');
                else if(['cancelada'].includes(est)) badge.classList.add('st-cancelada');
                else if(['realizada','concluida'].includes(est)) badge.classList.add('st-realizada');
            });
        }

        // --- Mostrar Notificação (Toast) e limpar a URL ---
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');
        if (msg) {
            const toastEl = document.getElementById('toastNotification');
            const toastMsg = document.getElementById('toastMessage');
            toastMsg.textContent = msg;
            
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
            
            setTimeout(() => {
                urlParams.delete('msg');
                const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                window.history.replaceState({}, document.title, newUrl);
            }, 300);
        }

        // --- Pesquisa e Filtros em Tempo Real (AJAX) ---
        const formFiltros = document.getElementById('form-filtros');
        const campoPesquisa = document.getElementById('campo-pesquisa');
        const filtroData = document.getElementById('filtro-data');
        const filtroEstado = document.getElementById('filtro-estado');
        const conteudoTabela = document.getElementById('conteudo-tabela');
        const paginaAtual = document.getElementById('pagina-atual');
        let timer;

        function atualizarTabela() {
            const formData = new FormData(formFiltros);
            const params = new URLSearchParams(formData);
            const url = `list.php?${params.toString()}`;

            conteudoTabela.style.opacity = '0.5';

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    conteudoTabela.innerHTML = doc.getElementById('conteudo-tabela').innerHTML;
                    conteudoTabela.style.opacity = '1';
                    
                    window.history.pushState({}, '', url);
                })
                .catch(error => {
                    console.error('Erro na requisição AJAX:', error);
                    conteudoTabela.style.opacity = '1';
                });
        }

        if (formFiltros) {
            formFiltros.addEventListener('submit', function(e) { e.preventDefault(); });

            campoPesquisa.addEventListener('input', function() {
                clearTimeout(timer);
                paginaAtual.value = 1; 
                timer = setTimeout(atualizarTabela, 400); // 400ms de delay ao escrever
            });

            filtroData.addEventListener('change', function() {
                paginaAtual.value = 1;
                atualizarTabela();
            });

            filtroEstado.addEventListener('change', function() {
                paginaAtual.value = 1;
                atualizarTabela();
            });
        }
    </script>
</body>
</html>