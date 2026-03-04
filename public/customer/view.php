<?php
session_start();

include('../verifica_login.php');
require_once __DIR__ . '/../../src/conexao.php';
require '../../src/helpers.php'; 
$cliente = null;
$result_marcacoes = null;

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 1. Dados do Cliente (ADICIONADO email_verificado NA QUERY)
    $sql = "SELECT id, nome, email, telefone, nif, created_at, obs, email_verificado FROM cliente WHERE id = '$id'";
    $query = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($query) > 0){
        $cliente = mysqli_fetch_array($query);

        // 2. Histórico de Marcações do Cliente (SEM ABREVIAÇÕES)
        $sql_marcacoes = "SELECT 
                            marcacao.id, 
                            marcacao.data, 
                            marcacao.slot_inicial, 
                            marcacao.estado,
                            servico.designacao AS nome_servico,
                            funcionario.nome AS nome_funcionario
                          FROM marcacao
                          INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
                          INNER JOIN servico ON servico_funcionario.id_servico = servico.id
                          INNER JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
                          WHERE marcacao.id_cliente = '$id'
                          ORDER BY marcacao.data DESC, marcacao.slot_inicial DESC";
        
        $result_marcacoes = mysqli_query($conn, $sql_marcacoes);
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visualizar Cliente - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/customer/view.css">
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
            <li class="nav-item">
                <a class="nav-link" href="../adm/dashboard.php">
                    <i class="bi bi-grid-1x2-fill me-3"></i>Início
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../worker/list.php">
                    <i class="bi bi-person-badge me-3"></i>Funcionários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="list.php">
                    <i class="bi bi-people me-3"></i>Clientes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../service/list.php">
                    <i class="bi bi-scissors me-3"></i>Serviços
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../service_category/list.php">
                    <i class="bi bi-tag me-3"></i>Categorias
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../booking/list.php">
                    <i class="bi bi-calendar-check me-3"></i>Marcações
                </a>
            </li>
            
            <li class="nav-item mt-auto">
                <a class="nav-link logout" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-3"></i>Sair
                </a>
            </li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>Detalhes do Cliente</h1>
                    <p class="text-muted">Informação completa do registo.</p>
                </div>
                <a href="list.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </header>

            <?php if($cliente): ?>
            
            <div class="view-card">
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="view-label">Nome Completo</div>
                        <div class="view-value fw-bold fs-5"><?= htmlspecialchars($cliente['nome']) ?></div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="view-label">Email</div>
                        <div class="view-value d-flex align-items-center gap-2">
                            <?= !empty($cliente['email']) ? htmlspecialchars($cliente['email']) : '<span class="text-muted">-</span>' ?>
                            
                            <?php if(!empty($cliente['email'])): ?>
                                <?php if(isset($cliente['email_verificado']) && $cliente['email_verificado'] == 1): ?>
                                    <span class="badge rounded-pill text-success bg-success-subtle border border-success-subtle px-2 py-1" style="font-size: 0.75rem;" title="Email validado pelo cliente">
                                        <i class="bi bi-check-circle-fill me-1"></i> Validada
                                    </span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-warning bg-warning-subtle border border-warning-subtle px-2 py-1" style="font-size: 0.75rem;" title="A aguardar validação de email">
                                        <i class="bi bi-clock-fill me-1"></i> Pendente
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="view-label">Telefone</div>
                        <div class="view-value"><?= !empty($cliente['telefone']) ? htmlspecialchars($cliente['telefone']) : '<span class="text-muted">-</span>' ?></div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="view-label">NIF</div>
                        <div class="view-value"><?= !empty($cliente['nif']) ? htmlspecialchars($cliente['nif']) : '<span class="text-muted">-</span>' ?></div>
                    </div>

                    <div class="col-md-6 mb-4">
                        <div class="view-label">Data de Registo</div>
                        <div class="view-value"><?= formatarData($cliente['created_at']) ?></div>
                    </div>

                    <div class="col-12 mb-2">
                        <div class="view-label">Observações</div>
                        <div class="view-value" style="min-height: 60px;">
                            <?= !empty($cliente['obs']) ? nl2br(htmlspecialchars($cliente['obs'])) : '<span class="text-muted fst-italic">Sem observações registadas.</span>' ?>
                        </div>
                    </div>
                </div> 

                <div class="card-actions">
                    <a href="edit.php?id=<?= $cliente['id'] ?>" class="btn-edit-action">
                        <i class="bi bi-pencil-square me-2"></i>Editar
                    </a>
                    <a href="delete.php?id=<?= $cliente['id'] ?>" class="btn-delete-action" onclick="return confirm('Tem a certeza que deseja apagar este cliente?');">
                        <i class="bi bi-trash me-2"></i>Apagar
                    </a>
                </div>
            </div>

            <button class="toggle-bar" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHistorico" aria-expanded="false" aria-controls="collapseHistorico">
                <div class="toggle-title">
                    <div class="toggle-icon-box">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <span>Histórico de Marcações</span>
                </div>
                <i class="bi bi-chevron-down"></i>
            </button>

            <div class="collapse collapse-container" id="collapseHistorico">
                <?php if($result_marcacoes && mysqli_num_rows($result_marcacoes) > 0): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Data & Hora</th>
                                    <th>Serviço</th>
                                    <th>Funcionário</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($marc = mysqli_fetch_assoc($result_marcacoes)): ?>
                                    <tr>
                                        <td>
                                            <div class="date-highlight">
                                                <?= formatarData($marc['data']) ?>
                                            </div>
                                            <div class="time-sub">
                                                <i class="bi bi-clock"></i>
                                                <?= converterSlotParaHora($marc['slot_inicial']) ?>
                                            </div>
                                        </td>
                                        <td class="fw-medium text-dark">
                                            <?= htmlspecialchars($marc['nome_servico']) ?>
                                        </td>
                                        <td class="text-secondary">
                                            <?= htmlspecialchars($marc['nome_funcionario']) ?>
                                        </td>
                                        <td>
                                            <?php if($marc['estado'] == 'confirmada'): ?>
                                                <span class="status-pill status-success">Confirmada</span>
                                            <?php elseif($marc['estado'] == 'pendente'): ?>
                                                <span class="status-pill status-pending">Pendente</span>
                                            <?php else: ?>
                                                <span class="status-pill status-default"><?= ucfirst($marc['estado']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="bg-white p-5 rounded-4 text-center shadow-sm mt-3">
                        <div class="mb-3 text-secondary opacity-25">
                            <i class="bi bi-journal-x display-1"></i>
                        </div>
                        <h5 class="fw-bold text-secondary">Sem histórico</h5>
                        <p class="text-muted small">Este cliente ainda não efetuou nenhuma marcação.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
                <div class="alert alert-warning text-center p-5 mt-4">
                    <h4>Cliente não encontrado</h4>
                    <p>O registo que procura não existe ou foi removido.</p>
                    <a href="list.php" class="btn btn-primary mt-3">Voltar à Lista</a>
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
        
        // Fechar sidebar ao clicar fora no mobile
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