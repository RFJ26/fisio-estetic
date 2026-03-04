<?php
session_start();

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require '../../src/helpers.php'; // Necessário para o formatarData e converterSlotParaHora

$categoria = null;
$result_servicos = null;
$result_marcacoes = null;

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 1. Dados da Categoria (SEM ABREVIAÇÕES)
    $sql = "SELECT categoria.id, categoria.designacao, categoria.created_at FROM categoria WHERE categoria.id = '$id'";
    $query = mysqli_query($conn, $sql);
    
    if($query && mysqli_num_rows($query) > 0){
        $categoria = mysqli_fetch_array($query);

        // 2. Serviços Associados (SEM ABREVIAÇÕES)
        $sql_servicos = "SELECT servico.designacao, servico.num_slots, servico.preco FROM servico WHERE servico.id_categoria = '$id' ORDER BY servico.designacao ASC";
        $result_servicos = mysqli_query($conn, $sql_servicos);

        // 3. Próximas Marcações (SEM ABREVIAÇÕES e com as relações corretas)
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
                          LIMIT 5";
        $result_marcacoes = mysqli_query($conn, $sql_marcacoes);
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
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </header>

            <?php if($categoria): ?>
            
            <div class="view-card">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="view-label">Nome da Categoria</div>
                        <div class="view-value fw-bold fs-5"><?= htmlspecialchars($categoria['designacao']) ?></div>
                    </div>

                    <div class="col-md-6 mb-4">
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
                    <a href="delete.php?id=<?= $categoria['id'] ?>" class="btn-delete-action" onclick="return confirm('Tem a certeza que deseja apagar esta categoria?');">
                        <i class="bi bi-trash me-2"></i>Apagar
                    </a>
                </div>
            </div>

            <button class="toggle-bar collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseServicos" aria-expanded="false" aria-controls="collapseServicos">
                <div class="toggle-title">
                    <div class="toggle-icon-box" style="background-color: #e8f5e9; color: #2e7d32;">
                        <i class="bi bi-scissors"></i>
                    </div>
                    <span>Serviços Associados</span>
                </div>
                <i class="bi bi-chevron-down"></i>
            </button>

            <div class="collapse collapse-container" id="collapseServicos">
                <?php if($result_servicos && mysqli_num_rows($result_servicos) > 0): ?>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Serviço</th>
                                    <th>Duração (Slots)</th>
                                    <th>Preço</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($serv = mysqli_fetch_assoc($result_servicos)): ?>
                                    <tr>
                                        <td class="fw-medium text-dark">
                                            <?= htmlspecialchars($serv['designacao']) ?>
                                        </td>
                                        <td class="text-secondary">
                                            <?= htmlspecialchars($serv['num_slots']) ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold" style="color: #2e7d32;">
                                                <?= number_format($serv['preco'], 2, ',', '.') ?> €
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="bg-white p-5 rounded-4 text-center shadow-sm mt-3">
                        <div class="mb-3 text-secondary opacity-25">
                            <i class="bi bi-inbox display-1"></i>
                        </div>
                        <h5 class="fw-bold text-secondary">Sem serviços</h5>
                        <p class="text-muted small">Ainda não existem serviços associados a esta categoria.</p>
                    </div>
                <?php endif; ?>
            </div>

            <button class="toggle-bar mt-4 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMarcacoes" aria-expanded="false" aria-controls="collapseMarcacoes">
                <div class="toggle-title">
                    <div class="toggle-icon-box" style="background-color: #e3f2fd; color: #1565c0;">
                        <i class="bi bi-calendar-check"></i>
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
                                    <th>Serviço</th>
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
                            <i class="bi bi-calendar-x display-1"></i>
                        </div>
                        <h5 class="fw-bold text-secondary">Sem marcações</h5>
                        <p class="text-muted small">Não existem agendamentos futuros para esta categoria.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
                <div class="alert alert-warning text-center p-5 mt-4">
                    <h4>Categoria não encontrada</h4>
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

        // Efeito de rotação na seta (ícone chevron) do toggle-bar quando o collapse abre e fecha
        document.querySelectorAll('.toggle-bar').forEach(button => {
            button.addEventListener('click', function() {
                const icon = this.querySelector('.bi-chevron-down');
                if(icon) {
                    // O setTimeout garante que esperamos o Bootstrap atualizar a class 'collapsed'
                    setTimeout(() => {
                        if (this.classList.contains('collapsed')) {
                            icon.style.transform = 'rotate(0deg)';
                        } else {
                            icon.style.transform = 'rotate(180deg)';
                        }
                    }, 50);
                }
            });
        });
    </script>
</body>
</html>