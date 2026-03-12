<?php
session_start();

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php'; // Necessário para o formatarData e converterSlotParaHora

$categoria = null;
$result_servicos = null;
$result_marcacoes = null;
$total_servicos = 0;
$total_futuras = 0;

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 1. Dados da Categoria
    $sql = "SELECT id, designacao, created_at FROM categoria WHERE id = '$id'";
    $query = mysqli_query($conn, $sql);
    
    if($query && mysqli_num_rows($query) > 0){
        $categoria = mysqli_fetch_array($query);

        // 2. Serviços Associados
        $sql_servicos = "SELECT designacao, num_slots, preco FROM servico WHERE id_categoria = '$id' ORDER BY designacao ASC";
        $result_servicos = mysqli_query($conn, $sql_servicos);
        $total_servicos = mysqli_num_rows($result_servicos);

        // 3. Próximas Marcações
        $hoje = date('Y-m-d');
        $sql_marcacoes = "SELECT 
                            marcacao.data, 
                            marcacao.slot_inicial, 
                            marcacao.estado,
                            servico.designacao AS nome_servico
                          FROM marcacao
                          INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
                          INNER JOIN servico ON servico_funcionario.id_servico = servico.id
                          WHERE servico.id_categoria = '$id' AND marcacao.data >= '$hoje'
                          ORDER BY marcacao.data ASC, marcacao.slot_inicial ASC
                          LIMIT 10"; // Aumentei o limite para mostrar mais algumas se houver
        $result_marcacoes = mysqli_query($conn, $sql_marcacoes);
        $total_futuras = mysqli_num_rows($result_marcacoes);
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visualizar Categoria - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service_category/view.css">
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
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            
            <?php if(isset($_COOKIE['role']) && ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === '1')): ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="/select_role.php" style="color: #ef6c00;"><i class="bi bi-arrow-left-right me-3"></i>Mudar Perfil</a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>Detalhes da Categoria</h1>
                    <p class="text-muted">Informação completa da categoria e serviços.</p>
                </div>
                <a href="list.php" class="btn-back">
                    <i class="bi bi-arrow-left me-2"></i> Voltar
                </a>
            </header>

            <?php if($categoria): ?>
            
            <div class="view-card">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="view-label">Nome da Categoria</div>
                        <div class="view-value fw-bold fs-5"><?= htmlspecialchars($categoria['designacao']) ?></div>
                    </div>

                    <div class="col-md-6">
                        <div class="view-label">Data de Criação</div>
                        <div class="view-value">
                            <?= formatarData($categoria['created_at']) ?>
                        </div>
                    </div>
                </div> 

                <div class="card-actions">
                    <a href="edit.php?id=<?= $categoria['id'] ?>" class="btn-edit-action">
                        <i class="bi bi-pencil-square me-2"></i>Editar
                    </a>
                    <a href="delete.php?id=<?= $categoria['id'] ?>" class="btn-delete-action ms-md-auto" onclick="return confirm('Tem a certeza que deseja apagar esta categoria?');">
                        <i class="bi bi-trash me-2"></i>Apagar
                    </a>
                </div>
            </div>

            <button class="toggle-bar" type="button" data-bs-toggle="collapse" data-bs-target="#collapseServicos" aria-expanded="false">
                <div class="toggle-title">
                    <div class="toggle-icon-box icon-box-purple">
                        <i class="bi bi-scissors"></i>
                    </div>
                    <span>Serviços Associados <span style="color: var(--brand-primary); opacity: 0.8; font-size: 0.9rem;">(<?= $total_servicos ?>)</span></span>
                </div>
                <i class="bi bi-chevron-down text-muted"></i>
            </button>

            <div class="collapse collapse-container" id="collapseServicos">
                <?php if($total_servicos > 0): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Serviço</th>
                                    <th>Duração Estimada</th>
                                    <th class="text-end pe-4">Preço</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($serv = mysqli_fetch_assoc($result_servicos)): 
                                    // CÁLCULO DE TEMPO: Slots * 15 min
                                    $minutos_totais = $serv['num_slots'] * 15;
                                    $horas = floor($minutos_totais / 60);
                                    $min_restantes = $minutos_totais % 60;
                                    
                                    $tempo_str = '';
                                    if ($horas > 0) $tempo_str .= $horas . 'h ';
                                    if ($min_restantes > 0 || $horas == 0) $tempo_str .= $min_restantes . ' min';
                                ?>
                                    <tr>
                                        <td data-label="Serviço" class="fw-medium text-dark">
                                            <?= htmlspecialchars($serv['designacao']) ?>
                                        </td>
                                        <td data-label="Duração" class="text-secondary">
                                            <i class="bi bi-clock-history me-1"></i> <?= trim($tempo_str) ?> 
                                            <span class="text-muted ms-1" style="font-size: 0.8rem; opacity: 0.7;">(<?= htmlspecialchars($serv['num_slots']) ?> slots)</span>
                                        </td>
                                        <td data-label="Preço" class="text-md-end pe-md-4">
                                            <span class="fw-bold" style="color: #2e7d32; font-size: 1.1rem;">
                                                <?= number_format($serv['preco'], 2, ',', '.') ?> €
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data-info">
                        Ainda não existem serviços associados a esta categoria.
                    </div>
                <?php endif; ?>
            </div>

            <button class="toggle-bar mt-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcacoes" aria-expanded="false">
                <div class="toggle-title">
                    <div class="toggle-icon-box icon-box-blue">
                        <i class="bi bi-calendar-week-fill"></i>
                    </div>
                    <span>Próximas Marcações <span style="color: #1976d2; opacity: 0.8; font-size: 0.9rem;">(<?= $total_futuras ?>)</span></span>
                </div>
                <i class="bi bi-chevron-down text-muted"></i>
            </button>

            <div class="collapse collapse-container" id="collapseMarcacoes">
                <?php if($total_futuras > 0): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Data & Hora</th>
                                    <th>Serviço</th>
                                    <th class="text-end pe-4">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($marc = mysqli_fetch_assoc($result_marcacoes)): 
                                    $est = strtolower($marc['estado']);
                                    // Cores de Estado
                                    $badge = 'status-default';
                                    if(in_array($est, ['realizada', 'confirmada'])) $badge = 'status-success';
                                    if(in_array($est, ['ativa', 'pendente'])) $badge = 'status-pending';
                                    if($est == 'por confirmar') $badge = 'status-danger';
                                    if($est == 'cancelada') $badge = 'status-cancel';
                                ?>
                                    <tr>
                                        <td data-label="Data & Hora">
                                            <div class="date-highlight">
                                                <?= formatarData($marc['data']) ?>
                                            </div>
                                            <div class="time-sub">
                                                <i class="bi bi-clock me-1 text-muted"></i>
                                                <?= converterSlotParaHora($marc['slot_inicial']) ?>
                                            </div>
                                        </td>
                                        <td data-label="Serviço" class="fw-medium text-dark">
                                            <?= htmlspecialchars($marc['nome_servico']) ?>
                                        </td>
                                        <td data-label="Estado" class="text-md-end pe-md-4">
                                            <span class="status-pill <?= $badge ?>"><?= ucfirst($est) ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data-info">
                        Não existem agendamentos futuros para os serviços desta categoria.
                    </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
                <div class="alert alert-warning text-center p-5 mt-4" style="border-radius: 12px; border: 1px solid #ffeeba;">
                    <i class="bi bi-exclamation-triangle-fill d-block fs-1 mb-3 text-warning"></i>
                    <h4>Categoria não encontrada</h4>
                    <a href="list.php" class="btn btn-primary mt-3 border-0" style="background-color: var(--brand-primary);">Voltar à Lista</a>
                </div>
            <?php endif; ?>

        </div>
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
    </script>
</body>
</html>