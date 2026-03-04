<?php
session_start();

include('../verifica_login.php');
require_once __DIR__ . '/../../src/conexao.php';
require '../../src/helpers.php'; // Essencial para as funções de data e hora

$utilizador = null;
$result_marcacoes = null;

// Verificação do ID
if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 1. Buscar dados do Funcionário
    $sql = "SELECT id, nome, email, telefone, nif, adm, created_at FROM funcionario WHERE id = '$id'";
    $query = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($query) > 0){
        $utilizador = mysqli_fetch_array($query);

        // 2. Buscar Marcações Futuras (Query SEM ABREVIAÇÕES)
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
            
            <header class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark">Detalhes do Funcionário</h2>
                    <p class="text-muted small">Informação completa do registo.</p>
                </div>
                <a href="list.php" class="btn-back">
                    <i class="bi bi-arrow-left me-2"></i>Voltar
                </a>
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
                        <div class="pb-3 border-bottom">
                            <?php if($utilizador['adm'] == 1): ?>
                                <span class="badge bg-success badge-status">
                                    <i class="bi bi-check-circle me-1"></i> Sim, é Administrador
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary badge-status">
                                    <i class="bi bi-person me-1"></i> Acesso Padrão (Funcionário)
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

                    <a href="delete.php?id=<?= $utilizador['id'] ?>" class="btn-delete-action ms-auto" onclick="return confirm('ATENÇÃO: Tem a certeza que deseja apagar este funcionário? Esta ação é irreversível.');">
                        <i class="bi bi-trash me-2"></i>Apagar
                    </a>
                </div>
            </div>

            <div class="mt-4">
                
                <button class="btn-toggle-area" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcacoes" aria-expanded="false" aria-controls="collapseMarcacoes">
                    <span class="d-flex align-items-center">
                        <i class="bi bi-calendar-week me-3 fs-5 text-success"></i>
                        <span>Ver Agenda Futura</span>
                    </span>
                    <i class="bi bi-chevron-down text-muted"></i>
                </button>

                <div class="collapse" id="collapseMarcacoes">
                    <div class="collapse-card">
                        
                        <?php if($result_marcacoes && mysqli_num_rows($result_marcacoes) > 0): ?>
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
                                        <?php while($marc = mysqli_fetch_assoc($result_marcacoes)): ?>
                                            <tr>
                                                <td class="fw-bold text-dark">
                                                    <?= formatarData($marc['data']) ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center text-muted">
                                                        <i class="bi bi-clock me-2 small"></i>
                                                        <?= converterSlotParaHora($marc['slot_inicial']) ?>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($marc['nome_cliente']) ?></td>
                                                <td><?= htmlspecialchars($marc['nome_servico']) ?></td>
                                                <td>
                                                    <?php if($marc['estado'] == 'confirmada'): ?>
                                                        <span class="badge-pill badge-success-soft">Confirmada</span>
                                                    <?php elseif($marc['estado'] == 'pendente'): ?>
                                                        <span class="badge-pill badge-warning-soft">Pendente</span>
                                                    <?php else: ?>
                                                        <span class="badge-pill badge-secondary-soft"><?= ucfirst($marc['estado']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x display-6 text-muted mb-3"></i>
                                <p class="text-muted">Não existem marcações futuras agendadas.</p>
                            </div>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>

            <?php else: ?>
                <div class="alert alert-warning text-center p-5 shadow-sm rounded-3">
                    <i class="bi bi-exclamation-triangle display-4 d-block mb-3"></i>
                    <h4>Funcionário não encontrado</h4>
                    <p>O registo que procura não existe ou foi removido.</p>
                    <a href="list.php" class="btn btn-outline-dark mt-2">Voltar à lista</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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