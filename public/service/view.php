<?php
session_start();

include('../verifica_login.php');
require '../../src/conexao.php';
require '../../src/helpers.php';

$servico = null;
$result_funcionarios = null;
$result_marcacoes = null;

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 1. Dados do Serviço
    $sql = "SELECT 
                servico.id, 
                servico.designacao, 
                servico.preco, 
                servico.num_slots, 
                servico.created_at,
                categoria.designacao AS nome_categoria
            FROM servico 
            LEFT JOIN categoria ON servico.id_categoria = categoria.id 
            WHERE servico.id = '$id'";
            
    $query = mysqli_query($conn, $sql);
    
    if(mysqli_num_rows($query) > 0){
        $servico = mysqli_fetch_array($query);

        // 2. Funcionários associados a este serviço
        $sql_funcionarios = "SELECT 
                                funcionario.id, 
                                funcionario.nome, 
                                funcionario.email, 
                                funcionario.telefone,
                                servico_funcionario.ativo
                             FROM servico_funcionario
                             INNER JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
                             WHERE servico_funcionario.id_servico = '$id'
                             ORDER BY funcionario.nome ASC";
        
        $result_funcionarios = mysqli_query($conn, $sql_funcionarios);

        // 3. Próximas Marcações deste Serviço
        $sql_marcacoes = "SELECT 
                            marcacao.id, 
                            marcacao.data, 
                            marcacao.slot_inicial, 
                            marcacao.estado,
                            cliente.nome AS nome_cliente,
                            funcionario.nome AS nome_funcionario
                          FROM marcacao
                          INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
                          INNER JOIN cliente ON marcacao.id_cliente = cliente.id
                          INNER JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
                          WHERE servico_funcionario.id_servico = '$id' 
                            AND marcacao.data >= CURDATE()
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
    <title>Visualizar Serviço - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service/view.css">
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
                <a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="list.php"><i class="bi bi-scissors me-3"></i>Serviços</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a>
            </li>
            
            <li class="nav-item mt-auto">
                <a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a>
            </li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>Detalhes do Serviço</h1>
                    <p class="text-muted">Informação completa do registo.</p>
                </div>
                <a href="list.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </header>

            <?php if($servico): ?>
            
            <div class="view-card">
                <div class="row">
                    <div class="col-12 mb-4 d-flex justify-content-between align-items-start">
                        <div>
                            <div class="view-label">Designação do Serviço</div>
                            <div class="view-value fw-bold fs-4 border-0 pb-0"><?= htmlspecialchars($servico['designacao']) ?></div>
                        </div>
                        <div class="fs-4 fw-bold text-success">
                            <?= number_format($servico['preco'], 2, ',', '.') ?> €
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="view-label">Categoria</div>
                        <div class="view-value">
                            <i class="bi bi-tag text-primary me-2"></i>
                            <?= !empty($servico['nome_categoria']) ? htmlspecialchars($servico['nome_categoria']) : 'Sem Categoria' ?>
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="view-label">Duração</div>
                        <div class="view-value">
                            <i class="bi bi-clock-history text-muted me-2"></i>
                            <?= htmlspecialchars($servico['num_slots']) ?> Slot(s)
                        </div>
                    </div>

                    <div class="col-md-4 mb-4">
                        <div class="view-label">Data de Criação</div>
                        <div class="view-value"><?= formatarData($servico['created_at']) ?></div>
                    </div>
                </div> 

                <div class="card-actions">
                    <a href="edit.php?id=<?= $servico['id'] ?>" class="btn-edit-action">
                        <i class="bi bi-pencil-square me-2"></i>Editar
                    </a>
                    <a href="delete.php?id=<?= $servico['id'] ?>" class="btn-delete-action" onclick="return confirm('Tem a certeza que deseja apagar este serviço?');">
                        <i class="bi bi-trash me-2"></i>Apagar
                    </a>
                </div>
            </div>

            <button class="toggle-bar" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFuncionarios" aria-expanded="false" aria-controls="collapseFuncionarios">
                <div class="toggle-title">
                    <div class="toggle-icon-box">
                        <i class="bi bi-people"></i>
                    </div>
                    <span>Profissionais Habilitados</span>
                </div>
                <i class="bi bi-chevron-down"></i>
            </button>

            <div class="collapse collapse-container" id="collapseFuncionarios">
                <?php if($result_funcionarios && mysqli_num_rows($result_funcionarios) > 0): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Nome do Profissional</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th class="text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($func = mysqli_fetch_assoc($result_funcionarios)): ?>
                                    <tr>
                                        <td class="fw-medium text-dark">
                                            <i class="bi bi-person-circle me-2 text-secondary"></i>
                                            <?= htmlspecialchars($func['nome']) ?>
                                        </td>
                                        <td class="text-secondary">
                                            <?= htmlspecialchars($func['email']) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($func['telefone']) ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if($func['ativo'] == 1): ?>
                                                <span class="status-pill status-success">Ativo</span>
                                            <?php else: ?>
                                                <span class="status-pill status-default">Inativo</span>
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
                            <i class="bi bi-person-x display-1"></i>
                        </div>
                        <h5 class="fw-bold text-secondary">Sem profissionais</h5>
                        <p class="text-muted small">Ainda não existem funcionários habilitados a realizar este serviço.</p>
                    </div>
                <?php endif; ?>
            </div>

            <button class="toggle-bar mt-4" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcacoes" aria-expanded="false" aria-controls="collapseMarcacoes">
                <div class="toggle-title">
                    <div class="toggle-icon-box">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <span>Próximas Marcações</span>
                </div>
                <i class="bi bi-chevron-down"></i>
            </button>

            <div class="collapse collapse-container" id="collapseMarcacoes">
                <?php if($result_marcacoes && mysqli_num_rows($result_marcacoes) > 0): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Data & Hora</th>
                                    <th>Cliente</th>
                                    <th>Profissional</th>
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
                                            <?= htmlspecialchars($marc['nome_cliente']) ?>
                                        </td>
                                        <td class="text-secondary">
                                            <?= htmlspecialchars($marc['nome_funcionario']) ?>
                                        </td>
                                        <td>
                                            <?php if($marc['estado'] == 'ativa' || $marc['estado'] == 'realizada'): ?>
                                                <span class="status-pill status-success"><?= ucfirst($marc['estado']) ?></span>
                                            <?php elseif($marc['estado'] == 'por confirmar'): ?>
                                                <span class="status-pill status-pending">Por Confirmar</span>
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
                            <i class="bi bi-calendar-x display-1"></i>
                        </div>
                        <h5 class="fw-bold text-secondary">Sem marcações</h5>
                        <p class="text-muted small">Não existem marcações futuras agendadas para este serviço.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
                <div class="alert alert-warning text-center p-5 mt-4">
                    <h4>Serviço não encontrado</h4>
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