<?php
// 1. Conexão e Sessão
require_once __DIR__ . '/../../src/conexao.php';

// 2. Segurança (Verifica se está logado)
require_once __DIR__ . '/../verifica_login.php';

// 3. Outros auxiliares
require_once __DIR__ . '/../../src/send_email.php';
// ============================================================================
// PROCESSAR AÇÕES DOS BOTÕES (Confirmar / Cancelar)
// ============================================================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $acao = $_GET['action'];
    $id_marcacao = intval($_GET['id']);
    $novo_estado = '';
    $mensagem = '';

    if ($acao === 'confirm') {
        $novo_estado = 'ativa'; // Confirma a marcação
        $mensagem = 'Marcação confirmada com sucesso!';
    } elseif ($acao === 'cancel') {
        $novo_estado = 'cancelada'; // Cancela a marcação
        $mensagem = 'Marcação cancelada.';
    }

    if ($novo_estado !== '') {
        $stmt = mysqli_prepare($conn, "UPDATE marcacao SET estado = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $novo_estado, $id_marcacao);
        
        if (mysqli_stmt_execute($stmt)) {
            // Envia o email com o novo estado
            enviarEmailEstado($conn, $id_marcacao, $novo_estado);
            
            // Redireciona para limpar o URL e mostra a mensagem
            header("Location: dashboard.php?msg=" . urlencode($mensagem));
            exit();
        }
    }
}

// ============================================================================
// CONSULTAS PARA AS MÉTRICAS
// ============================================================================
$total_marcacoes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM marcacao"))['total'];
$total_categorias = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM categoria"))['total'];
$total_clientes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM cliente"))['total'];

// ============================================================================
// CONSULTA DAS MARCAÇÕES RECENTES
// ============================================================================
$marcacoes_query = "
SELECT 
    marcacao.id,  /* Precisamos do ID para os botões */
    cliente.nome AS cliente,
    funcionario.nome AS funcionario,
    servico.designacao AS servico,
    marcacao.data,
    marcacao.estado
FROM marcacao
JOIN cliente ON marcacao.id_cliente = cliente.id
JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
JOIN servico ON servico_funcionario.id_servico = servico.id
WHERE estado = 'por confirmar'
ORDER BY marcacao.data ASC
LIMIT 7;
";
$marcacoes = mysqli_query($conn, $marcacoes_query);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/mouse-fix.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/adm/dashboard.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none" style="position:fixed; top:20px; left:20px; z-index:1100; border:none; background:var(--green-accent, #2e7d32); color:white; padding:8px 12px; border-radius:5px;">
        <i class="bi bi-list"></i>
    </button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area">
            <h2>Fisioestetic</h2>
        </div>

        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link active" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="header-section d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h1>Olá, <span class="text-gold"><?= htmlspecialchars($_COOKIE['nome']) ?></span></h1>
                    <p class="text-muted mb-0">Bem-vindo ao painel de gestão.</p>
                </div>
                <div class="d-none d-md-block text-end">
                    <span class="text-muted small"><?= date('d/m/Y') ?></span>
                </div>
            </header>

            <?php if (isset($_GET['msg'])): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($_GET['msg']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <section class="metrics-section mb-5">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="d-flex justify-content-between align-items-center h-100">
                                <div>
                                    <span class="card-label">Total Marcações</span>
                                    <div class="card-value"><?= $total_marcacoes ?></div>
                                </div>
                                <div class="icon-box">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="d-flex justify-content-between align-items-center h-100">
                                <div>
                                    <span class="card-label">Categorias</span>
                                    <div class="card-value"><?= $total_categorias ?></div>
                                </div>
                                <div class="icon-box">
                                    <i class="bi bi-tag"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="metric-card">
                            <div class="d-flex justify-content-between align-items-center h-100">
                                <div>
                                    <span class="card-label">Clientes</span>
                                    <div class="card-value"><?= $total_clientes ?></div>
                                </div>
                                <div class="icon-box">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="table-section">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title">Marcações Pendentes</h2>
                    <a href="../booking/list.php" class="btn-link-gold">Ver Todas <i class="bi bi-arrow-right ms-1"></i></a>
                </div>
                
                <div class="custom-table-container">
                    <div class="table-responsive">
                        <table class="table custom-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Funcionário</th>
                                    <th>Serviço</th>
                                    <th>Data</th>
                                    <th>Estado</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if(mysqli_num_rows($marcacoes) > 0){
                                    while($row = mysqli_fetch_assoc($marcacoes)){
                                        $data_formatada = date('d/m/Y', strtotime($row['data']));
                                        echo "<tr>
                                            <td class='fw-bold text-dark'>".htmlspecialchars($row['cliente'])."</td>
                                            <td>".htmlspecialchars($row['funcionario'])."</td>
                                            <td>".htmlspecialchars($row['servico'])."</td>
                                            <td>{$data_formatada}</td>
                                            <td><span class='badge-status'>".htmlspecialchars($row['estado'])."</span></td>
                                            <td class='text-center'>
                                                <a href='dashboard.php?action=confirm&id={$row['id']}' class='btn btn-success btn-sm me-1' title='Confirmar Marcação'>
                                                    <i class='bi bi-check-lg'></i>
                                                </a>
                                                <a href='dashboard.php?action=cancel&id={$row['id']}' class='btn btn-danger btn-sm' title='Cancelar Marcação' onclick='return confirm(\"Tem a certeza que deseja cancelar esta marcação?\")'>
                                                    <i class='bi bi-x-lg'></i>
                                                </a>
                                            </td>
                                        </tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='text-center py-5 text-muted'>Nenhuma marcação pendente.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

        </div> 
    </div> 

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        
        // Toggle básico
        if(toggle){
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });
        }

        // Fechar ao clicar fora (Mobile/Tablet)
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 991) {
                if (!sidebar.contains(e.target) && !toggle.contains(e.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Remove automaticamente a mensagem de sucesso da tela após 4 segundos
        setTimeout(() => {
            let alertBox = document.querySelector('.alert');
            if(alertBox) {
                alertBox.classList.remove('show');
                setTimeout(() => alertBox.remove(), 200); // Remove o HTML após a transição
                
                // Limpa o URL para não repetir a mensagem ao dar refresh
                window.history.replaceState({}, document.title, "dashboard.php");
            }
        }, 4000);
    </script>
</body>
</html>