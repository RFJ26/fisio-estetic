<?php
session_start();


require_once __DIR__ . '/../../src/conexao.php';
include_once __DIR__ . '/../../src/helpers.php';
if (!isset($_COOKIE['id])) {
    header("Location: ../index.php"); 
    exit();
}

$id_cliente = $_COOKIE['id];
$nome_cliente = $_COOKIE['nome'];
$primeiro_nome = explode(" ", $nome_cliente)[0];

// Query para buscar a próxima marcação futura
$query_proxima = "
    SELECT 
        servico.designacao AS nome_servico,
        marcacao.data,
        marcacao.slot_inicial,
        marcacao.slot_final,
        marcacao.estado
    FROM marcacao
    INNER JOIN servico_funcionario  ON marcacao.id_servico_funcionario = servico_funcionario.id
    INNER JOIN servico ON servico_funcionario.id_servico = servico.id
    WHERE marcacao.id_cliente = '$id_cliente' 
      AND marcacao.data >= CURDATE()
      AND marcacao.estado IN ('ativa', 'por confirmar')
    ORDER BY marcacao.data ASC, marcacao.slot_inicial ASC 
    LIMIT 1
";

$result_proxima = mysqli_query($conn, $query_proxima);
$tem_marcacao = false;
$dados_marcacao = [];
$hora_inicio = "--:--";
$hora_fim = "--:--";
$data_legivel = "--/--/----";

if ($result_proxima && mysqli_num_rows($result_proxima) > 0) {
    $tem_marcacao = true;
    $dados_marcacao = mysqli_fetch_assoc($result_proxima);
    
    $data_obj = new DateTime($dados_marcacao['data']);
    $data_legivel = $data_obj->format('d/m/Y');
    
    $hora_inicio = converterSlotParaHora($dados_marcacao['slot_inicial']);
    $hora_fim = converterSlotParaHora($dados_marcacao['slot_final'] + 1);
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Área - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/mouse-fix.css">
    <link rel="stylesheet" href="../css/customer/dashboard.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <img src="../images/logo_nova.png" alt="Logo" class="navbar-logo me-2">
                Fisioestetic
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-3">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking_new.php">Nova Marcação</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_bookings.php">Histórico</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-profile-link" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($primeiro_nome) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                            <li><a class="dropdown-item" href="profile.php">Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container main-content mt-4">
        
        <div class="row align-items-center mb-5 welcome-header">
            <div class="col-md-8">
                <h1>Olá, <span><?= htmlspecialchars($primeiro_nome) ?></span> 👋</h1>
                <p class="text-muted">Bem-vindo(a) à Fisioestetic. Cuide de si hoje.</p>
            </div>
            <div class="col-md-4 text-md-end d-none d-md-block">
                <div class="date-badge">
                    <i class="bi bi-calendar3 me-2"></i><?= date('d/m/Y') ?>
                </div>
            </div>
        </div>

        <div class="next-appointment-card mb-5">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="card-label"><i class="bi bi-stars me-2"></i>Sua Próxima Sessão</h5>
                    
                    <div class="appointment-info">
                        <?php if ($tem_marcacao): ?>
                            <h3 class="mb-2"><?= htmlspecialchars($dados_marcacao['nome_servico']) ?></h3>
                            <p class="mb-0 fs-5 opacity-75">
                                <i class="bi bi-calendar-check me-2"></i> <?= $data_legivel ?> 
                                <span class="mx-2">|</span> 
                                <i class="bi bi-clock me-2"></i> <?= $hora_inicio ?> - <?= $hora_fim ?>
                            </p>
                            
                            <div class="mt-3">
                                <span class="badge bg-white">
                                    <?= ($dados_marcacao['estado'] == 'por confirmar') ? "Aguardando Confirmação" : "Confirmada" ?>
                                </span>
                            </div>

                        <?php else: ?>
                            <div class="no-appointment">
                                <h3 class="mb-1">Sem marcações agendadas</h3>
                                <p class="mb-0 opacity-75">Que tal reservar um momento para si?</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4 text-md-end mt-4 mt-md-0">
                    <?php if ($tem_marcacao): ?>
                        <a href="my_bookings.php" class="btn btn-light-green">
                            Ver Detalhes <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    <?php else: ?>
                        <a href="booking_new.php" class="btn btn-light-green">
                            Marcar Agora <i class="bi bi-arrow-right ms-2"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h4 class="section-title">O que deseja fazer?</h4>
        <div class="row g-4 mb-5">
            <div class="col-md-4 col-sm-6">
                <a href="booking_new.php" class="action-card">
                    <div class="icon-circle">
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <h3>Nova Marcação</h3>
                    <p>Escolha o serviço e horário</p>
                </a>
            </div>

            <div class="col-md-4 col-sm-6">
                <a href="my_bookings.php" class="action-card">
                    <div class="icon-circle">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h3>Histórico</h3>
                    <p>Ver sessões anteriores</p>
                </a>
            </div>

            <div class="col-md-4 col-sm-6">
                <a href="profile.php" class="action-card">
                    <div class="icon-circle">
                        <i class="bi bi-person-gear"></i>
                    </div>
                    <h3>Meus Dados</h3>
                    <p>Atualizar contacto e senha</p>
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>