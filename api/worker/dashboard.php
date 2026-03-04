<?php
session_start();


include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require '../../src/helpers.php'; 

$id_funcionario = $_SESSION['id'] ?? 0; 
$nome_funcionario = $_SESSION['nome'] ?? 'Colaborador';
$primeiro_nome = explode(' ', trim($nome_funcionario))[0];
$data_hoje = date('Y-m-d');

// 1. Total Hoje
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

// 2. Lista de Marcações
$query_lista = "
    SELECT marcacao.slot_inicial, marcacao.estado, cliente.nome AS nome_cliente, servico.designacao AS nome_servico
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
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/dashboard.css">
    
    <link rel="stylesheet" href="../css/mouse-fix.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle">
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
                <h1 class="h3 mb-1">Olá, <span class="text-highlight"><?= htmlspecialchars($primeiro_nome); ?></span></h1>
                <p class="text-muted">Resumo da tua atividade diária.</p>
            </div>
            <div class="d-none d-md-block">
                <span class="text-muted fs-6">
                    <i class="bi bi-calendar3 me-2"></i> <?= date('d/m/Y'); ?>
                </span>
            </div>
        </header>

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
                    <div class="icon-box red">
                        <i class="bi bi-ban"></i>
                    </div>
                    <div class="card-info">
                        <span class="card-value" style="color: #dc3545;">Gerir</span>
                        <span class="card-label">Bloqueios</span>
                    </div>
                    <a href="indisponibilidade.php" class="stretched-link"></a>
                </div>
            </div>

            <div class="col-md-4">
                <div class="metric-card">
                    <div class="icon-box blue">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <div class="card-info">
                        <span class="card-value" style="color: #0dcaf0;">Conta</span>
                        <span class="card-label">Meus Dados</span>
                    </div>
                    <a href="perfil.php" class="stretched-link"></a>
                </div>
            </div>
        </div>

        <div class="custom-table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="section-title m-0">Agenda de Hoje</h4>
                <a href="minhas_marcacoes.php" class="btn-link-custom">
                    Ver Completa <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Cliente</th>
                            <th>Serviço</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($total_hoje > 0): ?>
                            <?php while($marcacao = mysqli_fetch_assoc($result_lista)): ?>
                                <tr>
                                    <td class="fw-bold text-highlight">
                                        <?= converterSlotParaHora($marcacao['slot_inicial']); ?>
                                    </td>
                                    <td><?= htmlspecialchars($marcacao['nome_cliente']); ?></td>
                                    <td><?= htmlspecialchars($marcacao['nome_servico']); ?></td>
                                    <td>
                                        <?php 
                                            $estado = strtolower($marcacao['estado']);
                                            $badgeClass = 'bg-secondary-soft'; // Default
                                            
                                            if(in_array($estado, ['ativa', 'confirmada'])) $badgeClass = 'bg-success-soft';
                                            if($estado == 'por confirmar') $badgeClass = 'bg-warning-soft';
                                            if($estado == 'realizada') $badgeClass = 'bg-info-soft';
                                            if($estado == 'cancelada') $badgeClass = 'bg-danger text-white';
                                        ?>
                                        <span class="badge badge-status <?= $badgeClass ?>">
                                            <?= ucfirst($estado); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-cup-hot fs-3 d-block mb-2"></i>
                                    Tudo livre por agora.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Lógica simples para abrir/fechar sidebar no mobile
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        
        if(toggle){
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
        }
    </script>
</body>
</html>