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
                          AND marcacao.estado = 'ativa'
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

    <button id="sidebarToggle" class="sidebar-toggle d-md-none">
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
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center mb-4 mx-auto" style="max-width: 900px;">
                <div>
                    <h2 class="fw-bold text-dark">Detalhes do Funcionário</h2>
                    <p class="text-muted small">Informação completa do registo.</p>
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
                        <div class="view-label mb-2">Perfil de Administrador</div>
                        <div class="pb-2">
                            <?php if($utilizador['adm'] == 1): ?>
                                <span class="badge bg-success" style="border-radius: 6px; padding: 8px 12px;">
                                    <i class="bi bi-shield-check me-1"></i> Administrador
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary" style="border-radius: 6px; padding: 8px 12px;">
                                    <i class="bi bi-person me-1"></i> Funcionário
                                </span>
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
                    <a href="delete.php?id=<?= $utilizador['id'] ?>" class="btn-delete-action ms-auto" onclick="return confirm('Apagar funcionário?');">
                        <i class="bi bi-trash me-2"></i>Apagar
                    </a>
                </div>
            </div>

            <button class="btn-toggle-area" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcacoes" aria-expanded="false">
                <span class="d-flex align-items-center">
                    <i class="bi bi-calendar-week me-3 fs-5 text-success"></i>
                    <span>Ver Agenda Futura <span style="color: var(--brand-primary); opacity: 0.8;">(<?= $total_futuras ?>)</span></span>
                </span>
                <i class="bi bi-chevron-down text-muted"></i>
            </button>

            <div class="collapse" id="collapseMarcacoes">
                <div class="collapse-card">
                    <?php if($total_futuras > 0): ?>
                        <div class="table-responsive">
                            <table class="table-stylish">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Horário</th>
                                        <th>Cliente</th>
                                        <th>Serviço</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($marc = mysqli_fetch_assoc($result_marcacoes)): 
                                        $est = strtolower($marc['estado']);
                                        $badge = 'badge-secondary-soft';
                                        if($est == 'ativa') $badge = 'badge-success-soft';
                                        if($est == 'por conficmar   ') $badge = 'badge-warning-soft';
                                        if($est == 'cancelada') $badge = 'badge-danger-soft';
                                    ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= date('d/m/Y', strtotime($marc['data'])) ?></td>
                                            <td><i class="bi bi-clock me-1"></i> <?= converterSlotParaHora($marc['slot_inicial']) ?></td>
                                            <td><?= htmlspecialchars($marc['nome_cliente']) ?></td>
                                            <td><?= htmlspecialchars($marc['nome_servico']) ?></td>
                                            <td><span class="badge-pill <?= $badge ?>"><?= ucfirst($est) ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-calendar-x d-block fs-2 mb-2"></i> Sem marcações futuras.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>