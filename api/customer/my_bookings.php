<?php
/**
 * PÁGINA: As Minhas Marcações
 * SOLUÇÃO: Sincronizada com helpers.php (Slots de 15min / Início 08:00)
 */

session_start();
require_once __DIR__ . '/../../src/conexao.php';
include_once __DIR__ . '/../../src/helpers.php';require_once __DIR__ . '/../../src/send_email.php';// Importa a lógica de email

$tz_lisboa = new DateTimeZone('Europe/Lisbon');
date_default_timezone_set('Europe/Lisbon');

if (!isset($_COOKIE['id])) {
    header("Location: ../index.php"); 
    exit();
}

$id_cliente = $_COOKIE['id];
$primeiro_nome = explode(" ", $_COOKIE['nome'])[0];

$agora = new DateTime('now', $tz_lisboa);

// =================================================================================
// 2. LÓGICA DE CANCELAMENTO (COM ENVIO DE EMAIL)
// =================================================================================
$msg = "";
$msg_tipo = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancelar_id'])) {
    $id_marcacao = intval($_POST['cancelar_id']);
    
    $check_sql = "SELECT id FROM marcacao WHERE id = '$id_marcacao' AND id_cliente = '$id_cliente' AND estado NOT LIKE 'cancelad%'";
    $check_res = mysqli_query($conn, $check_sql);
    
    if (mysqli_num_rows($check_res) > 0) {
        $update_sql = "UPDATE marcacao SET estado = 'cancelada' WHERE id = '$id_marcacao'";
        
        if (mysqli_query($conn, $update_sql)) {
            // ENVIO DO EMAIL DE CANCELAMENTO
            enviarEmailEstado($conn, $id_marcacao, 'cancelada');
            
            $msg = "Marcação cancelada com sucesso. Enviámos um e-mail de confirmação.";
            $msg_tipo = "success";
        } else {
            $msg = "Erro ao cancelar.";
            $msg_tipo = "danger";
        }
    }
}

// =================================================================================
// 3. BUSCAR TUDO
// =================================================================================
$filtro_status = isset($_GET['filtro_status']) ? $_GET['filtro_status'] : 'todos';
$sql_filtro = "";

if ($filtro_status == 'confirmada') $sql_filtro = " AND marcacao.estado = 'ativa' ";
elseif ($filtro_status == 'pendente') $sql_filtro = " AND marcacao.estado = 'por confirmar' ";
elseif ($filtro_status == 'cancelada') $sql_filtro = " AND marcacao.estado LIKE 'cancelad%' ";

$sql_geral = "
    SELECT 
        marcacao.id, marcacao.data, marcacao.slot_inicial, marcacao.estado, 
        COALESCE(servico.designacao, 'Serviço Indisponível') AS nome_servico, 
        COALESCE(funcionario.nome, '-') AS nome_funcionario
    FROM marcacao
    LEFT JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
    LEFT JOIN servico ON servico_funcionario.id_servico = servico.id
    LEFT JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
    WHERE marcacao.id_cliente = '$id_cliente' 
    $sql_filtro
    ORDER BY marcacao.data ASC, marcacao.slot_inicial ASC
";

$resultado = mysqli_query($conn, $sql_geral);

$lista_agendadas = [];
$lista_historico = [];

while ($row = mysqli_fetch_assoc($resultado)) {
    $hora_texto = converterSlotParaHora($row['slot_inicial']); 
    $data_hora_string = $row['data'] . ' ' . $hora_texto . ':00';
    $data_marcacao = DateTime::createFromFormat('Y-m-d H:i:s', $data_hora_string, $tz_lisboa);
    
    $ja_passou = ($data_marcacao < $agora);
    $row['debug_info'] = "Slot {$row['slot_inicial']} = {$hora_texto}";

    $estado = strtolower($row['estado']);
    
    if (strpos($estado, 'cancelad') !== false || $estado == 'realizada') {
        $lista_historico[] = $row;
    } else {
        if ($ja_passou) {
            $lista_historico[] = $row; 
        } else {
            $lista_agendadas[] = $row; 
        }
    }
}

usort($lista_agendadas, function($a, $b) {
    if ($a['data'] == $b['data']) return $a['slot_inicial'] - $b['slot_inicial'];
    return strcmp($a['data'], $b['data']);
});

usort($lista_historico, function($a, $b) {
    if ($a['data'] == $b['data']) return $b['slot_inicial'] - $a['slot_inicial'];
    return strcmp($b['data'], $a['data']);
});

function getStatusVisual($row, $agora_obj) {
    global $tz_lisboa; 
    $hora_texto = converterSlotParaHora($row['slot_inicial']);
    $dt_str = $row['data'] . ' ' . $hora_texto . ':00';
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $dt_str, $tz_lisboa);
    
    $passou = ($d < $agora_obj);
    $st = strtolower($row['estado']);

    if (strpos($st, 'cancelad') !== false) return ['class'=>'cancelada', 'label'=>'Cancelada', 'badge'=>'danger'];
    if ($st == 'ativa') {
        if($passou) return ['class'=>'concluida', 'label'=>'Realizada', 'badge'=>'success'];
        return ['class'=>'confirmada', 'label'=>'Confirmada', 'badge'=>'success'];
    }
    if ($st == 'por confirmar') {
        if($passou) return ['class'=>'pendente', 'label'=>'Expirada', 'badge'=>'secondary'];
        return ['class'=>'pendente', 'label'=>'Pendente', 'badge'=>'warning'];
    }
    return ['class'=>'concluida', 'label'=>'Realizada', 'badge'=>'success'];
}

$mesesPT = [1=>'Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Marcações</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/customer/my_bookings.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <img src="../images/logo_nova.png" alt="Logo" class="navbar-logo me-2"> FISIOESTETIC
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-3">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Início</a></li>
                    <li class="nav-item"><a class="nav-link" href="booking_new.php">Nova Marcação</a></li>
                    <li class="nav-item"><a class="nav-link active" href="my_bookings.php">Histórico</a></li>
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

    <div class="container py-4 mt-5">
        <div class="d-flex align-items-center mb-4">
            <a href="dashboard.php" class="btn btn-light rounded-circle shadow-sm me-4" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            <div>
                <h1 class="page-title mb-0">Marcações</h1>
                <p class="page-subtitle mb-0">Gerencie os seus agendamentos</p>
            </div>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_tipo ?> alert-dismissible fade show"><?= $msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
            <li class="nav-item"><button class="nav-link active" id="pills-proximas-tab" data-bs-toggle="pill" data-bs-target="#pills-proximas" type="button">Agendadas</button></li>
            <li class="nav-item"><button class="nav-link" id="pills-historico-tab" data-bs-toggle="pill" data-bs-target="#pills-historico" type="button">Histórico</button></li>
        </ul>

        <div class="tab-content" id="pills-tabContent">
            <div class="tab-pane fade show active" id="pills-proximas">
                <?php if (count($lista_agendadas) > 0): ?>
                    <div class="row">
                        <?php foreach ($lista_agendadas as $row): 
                            $dataObj = new DateTime($row['data']);
                            $visual = getStatusVisual($row, $agora);
                            $horaShow = converterSlotParaHora($row['slot_inicial']); 
                        ?>
                        <div class="col-lg-6">
                            <div class="booking-card status-<?= $visual['class'] ?>">
                                <div class="d-flex align-items-center">
                                    <div class="date-box me-3 flex-shrink-0">
                                        <span class="date-day"><?= $dataObj->format('d') ?></span>
                                        <span class="date-month"><?= $mesesPT[$dataObj->format('n')] ?></span>
                                        <span class="date-year"><?= $dataObj->format('Y') ?></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="booking-title"><?= htmlspecialchars($row['nome_servico']) ?></h5>
                                        <div class="booking-info"><i class="bi bi-clock"></i> <?= $horaShow ?></div>
                                        <div class="booking-info"><i class="bi bi-person"></i> <?= htmlspecialchars($row['nome_funcionario']) ?></div>
                                    </div>
                                    <div class="text-end ms-2">
                                        <span class="badge status-badge bg-<?= $visual['badge'] ?> mb-2"><?= $visual['label'] ?></span><br>
                                        <button type="button" class="btn btn-cancel-booking btn-sm" data-bs-toggle="modal" data-bs-target="#modalCancel<?= $row['id'] ?>">Cancelar</button>
                                    </div>
                                </div>
                            </div>
                            <div class="modal fade" id="modalCancel<?= $row['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-body text-center py-4">
                                            <h5 class="fw-bold mb-3">Cancelar Marcação?</h5>
                                            <p>Deseja cancelar <strong><?= htmlspecialchars($row['nome_servico']) ?></strong>?</p>
                                        </div>
                                        <div class="modal-footer border-0 justify-content-center">
                                            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Não</button>
                                            <form method="POST">
                                                <input type="hidden" name="cancelar_id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-danger rounded-pill px-4">Sim, Cancelar</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar4-week fs-1 text-muted mb-3"></i>
                        <h4>Sem agendamentos futuros</h4>
                        <a href="booking_new.php" class="btn btn-success rounded-pill px-4 mt-2">Nova Marcação</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="tab-pane fade" id="pills-historico">
                <?php if (count($lista_historico) > 0): ?>
                    <div class="row">
                        <?php foreach ($lista_historico as $row): 
                            $dataObj = new DateTime($row['data']);
                            $visual = getStatusVisual($row, $agora);
                            $horaShow = converterSlotParaHora($row['slot_inicial']);
                        ?>
                        <div class="col-lg-6">
                            <div class="booking-card status-<?= $visual['class'] ?> opacity-75">
                                <div class="d-flex align-items-center">
                                    <div class="date-box me-3 flex-shrink-0" style="background-color: #f1f1f1; color: #555;">
                                        <span class="date-day"><?= $dataObj->format('d') ?></span>
                                        <span class="date-month"><?= $mesesPT[$dataObj->format('n')] ?></span>
                                        <span class="date-year"><?= $dataObj->format('Y') ?></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="booking-title text-muted"><?= htmlspecialchars($row['nome_servico']) ?></h5>
                                        <div class="booking-info text-muted"><i class="bi bi-clock"></i> <?= $horaShow ?></div>
                                        <div class="booking-info text-muted"><i class="bi bi-person"></i> <?= htmlspecialchars($row['nome_funcionario']) ?></div>
                                    </div>
                                    <div class="text-end ms-2">
                                        <span class="badge rounded-pill bg-<?= $visual['badge'] ?>"><?= $visual['label'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-clock-history fs-1 text-muted mb-3"></i>
                        <h4>Histórico Vazio</h4>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>