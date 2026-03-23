<?php
session_start();

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php'; 
require_once __DIR__ . '/../../src/send_email.php';

$id_funcionario = $_COOKIE['id'] ?? 0; 
$nome_funcionario = $_COOKIE['nome'] ?? 'Colaborador';
$primeiro_nome = explode(' ', trim($nome_funcionario))[0];
$data_hoje = date('Y-m-d');

// ============================================================================
// PROCESSAR AÇÕES DOS BOTÕES (Confirmar / Cancelar / Concluir)
// ============================================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $acao = $_GET['action'];
    $id_marcacao = intval($_GET['id']);
    $novo_estado = '';

    // SEGURANÇA MÁXIMA: Verificar se a marcação pertence mesmo a este funcionário
    $check_query = "SELECT marcacao.id FROM marcacao 
                    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id 
                    WHERE marcacao.id = $id_marcacao AND servico_funcionario.id_funcionario = '$id_funcionario'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        
        if ($acao === 'confirm') {
            $novo_estado = 'ativa'; 
        } elseif ($acao === 'cancel') {
            $novo_estado = 'cancelada'; 
        } elseif ($acao === 'complete') {
            $novo_estado = 'realizada'; 
        }

        if ($novo_estado !== '') {
            $stmt = mysqli_prepare($conn, "UPDATE marcacao SET estado = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $novo_estado, $id_marcacao);
            
            if (mysqli_stmt_execute($stmt)) {
                // ENVIA O EMAIL AO CLIENTE AVISANDO DA MUDANÇA DE ESTADO
                enviarEmailEstado($conn, $id_marcacao, $novo_estado);
                
                // Redireciona para limpar o URL
                echo "<script>window.location.href='dashboard.php?msg=success';</script>";
                exit;
            }
        }
    } else {
        echo "<script>alert('Acesso negado: Não pode alterar marcações de outros colegas.'); window.location.href='dashboard.php';</script>";
        exit;
    }
}

// ============================================================================
// QUERIES DE LISTAGEM
// ============================================================================

$total_hoje = 0;
$query_count = "
    SELECT COUNT(*) as total 
    FROM marcacao
    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
    WHERE servico_funcionario.id_funcionario = '$id_funcionario' 
    AND marcacao.data = '$data_hoje' 
    AND marcacao.estado != 'cancelada'
";
if($res = mysqli_query($conn, $query_count)) {
    $row = mysqli_fetch_assoc($res);
    $total_hoje = $row['total'];
}

$query_lista = "
    SELECT marcacao.id, marcacao.slot_inicial, marcacao.estado, cliente.nome AS nome_cliente, servico.designacao AS nome_servico
    FROM marcacao
    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
    INNER JOIN cliente ON marcacao.id_cliente = cliente.id
    INNER JOIN servico ON servico_funcionario.id_servico = servico.id
    WHERE servico_funcionario.id_funcionario = '$id_funcionario' 
    AND marcacao.data = '$data_hoje'
    ORDER BY marcacao.slot_inicial ASC
";
$result_lista = mysqli_query($conn, $query_lista);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Funcionário</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/dashboard.css">
    <link rel="stylesheet" href="../css/mouse-fix.css">

    <style>
        .btn-confirm-table {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 6px 10px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-confirm-table:hover { background-color: #c8e6c9; color: #1b5e20; }
        
        .btn-cancel-table {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 6px 10px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-cancel-table:hover { background-color: #fecaca; color: #b91c1c; }

        .btn-complete-table {
            background-color: #e0f2fe;
            color: #0284c7;
            padding: 6px 10px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        .btn-complete-table:hover { background-color: #bae6fd; color: #0369a1; }
        
        /* Ajuste de status pill igual ao admin */
        .status-pill {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-done { background-color: #d1ecf1; color: #0c5460; }
        .status-canceled { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none" style="position:fixed; top:20px; left:20px; z-index:1100; border:none; background:var(--brand-primary, #2e7d32); color:white; padding:8px 12px; border-radius:5px;">
        <i class="bi bi-list"></i>
    </button>

    <nav class="sidebar">
        <div class="logo-area">
            <h2>Fisioestetic</h2>
        </div>
        
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="bi bi-grid-1x2-fill"></i> Início
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="minhas_marcacoes.php">
                    <i class="bi bi-calendar-check"></i> Agenda
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="indisponibilidade.php">
                    <i class="bi bi-slash-circle"></i> Indisponibilidade
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="perfil.php">
                    <i class="bi bi-person-circle"></i> Meu Perfil
                </a>
            </li>
            <?php if(isset($_COOKIE['role']) && ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === '1')): ?>
                <li class="nav-item mt-3"><a class="nav-link" href="/select_role.php" style="color: #ef6c00;"><i class="bi bi-arrow-left-right me-3"></i>Mudar Perfil</a></li>
            <?php endif; ?>
            <li class="nav-item mt-auto">
                <a class="nav-link logout" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i> Sair
                </a>
            </li>
        </ul>
    </nav>

    <div class="content">
        
        <header class="d-flex justify-content-between align-items-center mb-5 header-section">
            <div>
                <h1 class="h3 mb-1">Olá, <span class="text-highlight text-gold" style="color: var(--brand-primary);"><?= htmlspecialchars($primeiro_nome); ?></span></h1>
                <p class="text-muted mb-0">Resumo da tua atividade diária.</p>
            </div>
            <div class="d-none d-md-block text-end">
                <span class="text-muted small">
                    <i class="bi bi-calendar3 me-2"></i> <?= date('d/m/Y'); ?>
                </span>
            </div>
        </header>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Ação realizada com sucesso. O cliente foi notificado!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="icon-box">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="card-info">
                        <span class="card-value"><?= $total_hoje ?></span>
                        <span class="card-label">Marcações Hoje</span>
                    </div>
                    <a href="minhas_marcacoes.php" class="stretched-link"></a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <div class="icon-box red" style="background-color: #fee2e2; color: #dc2626;">
                        <i class="bi bi-ban"></i>
                    </div>
                    <div class="card-info">
                        <span class="card-value" style="color: #dc2626;">Gerir</span>
                        <span class="card-label">Bloqueios</span>
                    </div>
                    <a href="indisponibilidade.php" class="stretched-link"></a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <div class="icon-box blue" style="background-color: #e0f2fe; color: #0284c7;">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <div class="card-info">
                        <span class="card-value" style="color: #0284c7;">Conta</span>
                        <span class="card-label">Meus Dados</span>
                    </div>
                    <a href="perfil.php" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <section class="table-section">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center gap-2">
                    <div class="icon-box-orange" style="width: 32px; height: 32px; background: #e8f5e9; color: #2e7d32; border-radius: 8px; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    <h2 class="section-title m-0" style="font-size: 1.25rem; font-weight: 600;">Agenda de Hoje</h2>
                </div>
                <a href="minhas_marcacoes.php" style="text-decoration: none; color: var(--brand-primary, #2e7d32); font-weight: 500;">
                    Ver Completa <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            
            <div class="collapse-container p-0">
                <div class="table-responsive">
                    <table class="table modern-table custom-table mb-0">
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Estado</th>
                                <th class="text-end pe-4">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($total_hoje > 0): ?>
                                <?php while($marcacao = mysqli_fetch_assoc($result_lista)): ?>
                                    <tr>
                                        <td data-label="Hora" class="fw-bold text-dark">
                                            <?= converterSlotParaHora($marcacao['slot_inicial']); ?>
                                        </td>
                                        <td data-label="Cliente" class="fw-medium text-dark"><?= htmlspecialchars($marcacao['nome_cliente']); ?></td>
                                        <td data-label="Serviço" class="text-secondary"><?= htmlspecialchars($marcacao['nome_servico']); ?></td>
                                        
                                        <td data-label="Estado">
                                            <?php 
                                                $estado = strtolower($marcacao['estado']);
                                                $pillClass = 'status-pending';
                                                
                                                if(in_array($estado, ['ativa', 'confirmada'])) $pillClass = 'status-active';
                                                if($estado == 'realizada') $pillClass = 'status-done';
                                                if($estado == 'cancelada') $pillClass = 'status-canceled';
                                            ?>
                                            <span class="status-pill <?= $pillClass ?>">
                                                <?= ucfirst($estado); ?>
                                            </span>
                                        </td>

                                        <td data-label="Ação" class="text-md-end pe-md-4">
                                            <?php if($estado == 'por confirmar'): ?>
                                                <a href="?action=confirm&id=<?= $marcacao['id'] ?>" class="btn-confirm-table me-1" title="Confirmar" onclick="return confirm('Tem a certeza que deseja confirmar esta marcação?');">
                                                    <i class="bi bi-check-lg fs-5"></i>
                                                </a>
                                                <a href="?action=cancel&id=<?= $marcacao['id'] ?>" class="btn-cancel-table" title="Cancelar" onclick="return confirm('Tem a certeza que deseja cancelar esta marcação?');">
                                                    <i class="bi bi-x-lg"></i>
                                                </a>
                                                
                                            <?php elseif($estado == 'ativa'): ?>
                                                <a href="?action=complete&id=<?= $marcacao['id'] ?>" class="btn-complete-table" title="Concluir Serviço" onclick="return confirm('Deseja dar este serviço como concluído?');">
                                                    <i class="bi bi-check2-all fs-5"></i>
                                                </a>
                                                
                                            <?php else: ?>
                                                <span class="text-muted small">Sem ações</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5 no-data-info">
                                        <i class="bi bi-cup-hot fs-3 d-block mb-2"></i>
                                        Não existem marcações para hoje.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        
        if(toggle){
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });
        }

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 991) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Limpar os parâmetros GET (msg=success) do URL após 4 segundos
        setTimeout(() => {
            let alertBox = document.querySelector('.alert');
            if(alertBox) {
                alertBox.classList.remove('show');
                setTimeout(() => alertBox.remove(), 200); 
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        }, 4000);
    </script>
</body>
</html>