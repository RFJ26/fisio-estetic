<?php
session_start();

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php';

$utilizador = null;
$result_marcacoes = null;
$total_futuras = 0;

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 1. Buscar dados do Funcionário
    $sql = "SELECT id, nome, email, telefone, nif, adm, created_at FROM funcionario WHERE id = '$id'";
    $query = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($query) > 0){
        $utilizador = mysqli_fetch_array($query);

        // 2. Buscar Marcações Futuras
        $sql_marcacoes = "SELECT 
                            marcacao.id, 
                            marcacao.data, 
                            marcacao.slot_inicial, 
                            marcacao.estado,
                            cliente.nome AS nome_cliente,
                            servico.designacao AS nome_servico
                          FROM marcacao
                          INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
                          INNER JOIN cliente ON marcacao.id_cliente = cliente.id
                          INNER JOIN servico ON servico_funcionario.id_servico = servico.id
                          WHERE servico_funcionario.id_funcionario = '$id' 
                          AND marcacao.data >= CURDATE() 
                          AND marcacao.estado != 'cancelada'
                          ORDER BY marcacao.data ASC, marcacao.slot_inicial ASC";
        
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
    <title>Visualizar Funcionário - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css"> 
    <link rel="stylesheet" href="../css/worker/view.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none">
        <i class="bi bi-list"></i>
    </button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
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
            
            <header class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>Detalhes do Funcionário</h1>
                    <p class="text-muted">Informação completa do registo.</p>
                </div>
                <a href="list.php" class="btn-back"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
            </header>

            <?php if($utilizador): ?>
            
            <div class="view-card">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="view-label">Nome Completo</div>
                        <div class="view-value fw-bold fs-5"><?= htmlspecialchars($utilizador['nome']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="view-label">Email</div>
                        <div class="view-value"><?= htmlspecialchars($utilizador['email']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="view-label">Telefone</div>
                        <div class="view-value"><?= htmlspecialchars($utilizador['telefone']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="view-label">NIF</div>
                        <div class="view-value"><?= htmlspecialchars($utilizador['nif']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="view-label">Data de Registo</div>
                        <div class="view-value"><?= formatarData($utilizador['created_at']) ?></div>
                    </div>
                    <div class="col-12">
                        <div class="view-label mb-2">Perfil de Acesso</div>
                        <div class="pb-2">
                            <?php if($utilizador['adm'] == 1): ?>
                                <span class="status-pill status-success"><i class="bi bi-shield-check me-1"></i> Administrador</span>
                            <?php else: ?>
                                <span class="status-pill status-default"><i class="bi bi-person me-1"></i> Funcionário</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card-actions">
                    <a href="edit.php?id=<?= $utilizador['id'] ?>" class="btn-edit-action">
                        <i class="bi bi-pencil-square me-2"></i>Editar Dados
                    </a>
                    <a href="assign_services.php?id=<?= $utilizador['id'] ?>" class="btn-assign-action">
                        <i class="bi bi-list-check me-2"></i>Competências
                    </a>
                    <a href="delete.php?id=<?= $utilizador['id'] ?>" class="btn-delete-action ms-md-auto" onclick="return confirm('Apagar funcionário?');">
                        <i class="bi bi-trash me-2"></i>Apagar
                    </a>
                </div>
            </div>

            <button class="toggle-bar" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcacoes" aria-expanded="false">
                <div class="toggle-title">
                    <div class="toggle-icon-box icon-box-blue">
                        <i class="bi bi-calendar-week-fill"></i>
                    </div>
                    <span>Ver Agenda Futura <span style="color: #1976d2; opacity: 0.8; font-size: 0.9rem;">(<?= $total_futuras ?>)</span></span>
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
                                    <th>Cliente</th>
                                    <th>Serviço</th>
                                    <th class="text-end pe-4">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($marc = mysqli_fetch_assoc($result_marcacoes)): 
                                    $est = strtolower($marc['estado']);
                                    $badge = 'status-default';
                                    if($est == 'realizada') $badge = 'status-success';
                                    if($est == 'ativa') $badge = 'status-pending';
                                    if($est == 'por confirmar') $badge = 'status-danger';
                                ?>
                                    <tr>
                                        <td data-label="Data & Hora">
                                            <div class="date-highlight"><?= date('d/m/Y', strtotime($marc['data'])) ?></div>
                                            <div class="time-sub"><i class="bi bi-clock me-1 text-muted"></i> <?= converterSlotParaHora($marc['slot_inicial']) ?></div>
                                        </td>
                                        <td data-label="Cliente" class="fw-medium text-dark"><?= htmlspecialchars($marc['nome_cliente']) ?></td>
                                        <td data-label="Serviço" class="text-secondary"><?= htmlspecialchars($marc['nome_servico']) ?></td>
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
                        Sem marcações futuras agendadas.
                    </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
                <div class="alert alert-warning text-center p-5 mt-4" style="border-radius: 12px; border: 1px solid #ffeeba;">
                    <i class="bi bi-exclamation-triangle-fill d-block fs-1 mb-3 text-warning"></i>
                    <h4>Funcionário não encontrado</h4>
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