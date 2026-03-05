<?php
session_start();

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/send_email.php';

$id_funcionario = $_COOKIE['id_funcionario'] ?? $_COOKIE['id'] ?? 0;
$nome_funcionario = $_COOKIE['nome'] ?? 'Colaborador';

if ($id_funcionario == 0 && !empty($nome_funcionario)) {
    $nome_seguro = mysqli_real_escape_string($conn, $nome_funcionario);
    $query_recupera = "SELECT id FROM funcionario WHERE nome = '$nome_seguro' LIMIT 1";
    $res_recupera = mysqli_query($conn, $query_recupera);
    if ($dados = mysqli_fetch_assoc($res_recupera)) {
        $id_funcionario = $dados['id'];
        $_COOKIE['id_funcionario'] = $id_funcionario;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_estado'])) {
    
    $id_marcacao_alvo = intval($_POST['id_marcacao']);
    $novo_estado = mysqli_real_escape_string($conn, $_POST['novo_estado']);

    $query_verifica = "
        SELECT marcacao.id 
        FROM marcacao
        INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
        WHERE marcacao.id = '$id_marcacao_alvo' 
        AND servico_funcionario.id_funcionario = '$id_funcionario'
    ";
    
    if (mysqli_num_rows(mysqli_query($conn, $query_verifica)) > 0) {
        $query_update = "UPDATE marcacao SET estado = '$novo_estado' WHERE id = '$id_marcacao_alvo'";
        
        if (mysqli_query($conn, $query_update)) {
            // DISPARO DE EMAIL APÓS MUDANÇA DE ESTADO
            enviarEmailEstado($conn, $id_marcacao_alvo, $novo_estado);

            $url = $_SERVER['PHP_SELF'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $url .= '?' . $_SERVER['QUERY_STRING'];
            }
            header("Location: " . $url);
            exit;
        }
    }
}

$modo_ver_todas = false;
$data_selecionada = date('Y-m-d');

if (isset($_GET['ver']) && $_GET['ver'] === 'todas') {
    $modo_ver_todas = true;
    $titulo_pagina = "Todas as Próximas Marcações";
} 
elseif (isset($_GET['data_filtro'])) {
    $data_selecionada = $_GET['data_filtro'];
    $titulo_pagina = "Agenda de: " . date('d/m/Y', strtotime($data_selecionada));
} 
else {
    $titulo_pagina = "Agenda de Hoje (" . date('d/m/Y') . ")";
}

$query_marcacoes = "
    SELECT 
        marcacao.id AS id_marcacao,
        marcacao.data AS data_marcacao,
        marcacao.slot_inicial, 
        marcacao.slot_final,
        marcacao.estado, 
        cliente.nome AS nome_cliente, 
        cliente.telefone AS telefone_cliente,
        cliente.obs AS obs_cliente,
        servico.designacao AS nome_servico,
        servico.preco,
        servico.num_slots
    FROM marcacao
    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
    INNER JOIN cliente ON marcacao.id_cliente = cliente.id
    INNER JOIN servico ON servico_funcionario.id_servico = servico.id
    WHERE servico_funcionario.id_funcionario = '$id_funcionario'
";

if ($modo_ver_todas) {
    $query_marcacoes .= " AND marcacao.data >= CURDATE()";
    $query_marcacoes .= " ORDER BY marcacao.data ASC, marcacao.slot_inicial ASC";
} else {
    $query_marcacoes .= " AND marcacao.data = '$data_selecionada'";
    $query_marcacoes .= " ORDER BY marcacao.slot_inicial ASC";
}

$resultado_marcacoes = mysqli_query($conn, $query_marcacoes);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minhas Marcações | Fisioestetic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/minhas_marcacoes.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>

    <nav class="sidebar">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Início</a></li>
            <li class="nav-item"><a class="nav-link active" href="minhas_marcacoes.php"><i class="bi bi-calendar-check"></i> Agenda</a></li>
            <li class="nav-item"><a class="nav-link" href="indisponibilidade.php"><i class="bi bi-slash-circle"></i> Indisponibilidade</a></li>
            <li class="nav-item"><a class="nav-link" href="perfil.php"><i class="bi bi-person-circle"></i> Meu Perfil</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left"></i> Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            <header class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                <div>
                    <h2 class="fw-bold m-0"><?= $titulo_pagina ?></h2>
                    <p class="text-muted m-0">Bem-vindo(a), <?= htmlspecialchars($nome_funcionario) ?></p>
                </div>
                <form action="" method="GET" class="d-flex gap-2 bg-white p-2 rounded shadow-sm border">
                    <input type="date" name="data_filtro" class="form-control border-0" value="<?= $modo_ver_todas ? date('Y-m-d') : $data_selecionada ?>" onchange="this.form.submit()">
                    <?php if(!$modo_ver_todas): ?>
                        <a href="?ver=todas" class="btn btn-outline-secondary text-nowrap"><i class="bi bi-collection me-1"></i> Ver Todas</a>
                    <?php else: ?>
                        <a href="minhas_marcacoes.php" class="btn btn-outline-primary text-nowrap"><i class="bi bi-calendar-event me-1"></i> Ver Hoje</a>
                    <?php endif; ?>
                </form>
            </header>

            <div class="card-custom d-block p-0 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Horário</th>
                                <?php if($modo_ver_todas): ?> <th>Data</th> <?php endif; ?>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Obs.</th> <th>Estado</th>
                                <th class="text-center pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($resultado_marcacoes) > 0): ?>
                                <?php while($marcacao = mysqli_fetch_assoc($resultado_marcacoes)): ?>
                                    <?php 
                                        $hora_inicio = converterSlotParaHora($marcacao['slot_inicial']);
                                        $duracao = converterSlotsParaDuracao($marcacao['num_slots']);
                                        $classe_estado = 'bg-light text-dark';
                                        if($marcacao['estado'] == 'realizada') $classe_estado = 'bg-success text-white';
                                        if($marcacao['estado'] == 'cancelada') $classe_estado = 'bg-danger text-white';
                                        if($marcacao['estado'] == 'ativa') $classe_estado = 'bg-info text-white';
                                        if($marcacao['estado'] == 'por confirmar') $classe_estado = 'bg-warning text-dark';
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="fs-5 text-gold"><?= $hora_inicio ?></span>
                                                <small class="text-muted"><i class="bi bi-stopwatch"></i> <?= $duracao ?></small>
                                            </div>
                                        </td>
                                        <?php if($modo_ver_todas): ?>
                                            <td><span class="badge bg-light text-dark border"><?= date('d/m/Y', strtotime($marcacao['data_marcacao'])) ?></span></td>
                                        <?php endif; ?>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($marcacao['nome_cliente']) ?></div>
                                            <div class="small text-muted"><i class="bi bi-phone"></i> <?= htmlspecialchars($marcacao['telefone_cliente']) ?></div>
                                        </td>
                                        <td><div class="fw-medium"><?= htmlspecialchars($marcacao['nome_servico']) ?></div></td>
                                        <td>
                                            <?php if(!empty($marcacao['obs_cliente'])): ?>
                                                <span class="d-inline-block text-truncate" style="max-width: 150px;" data-bs-toggle="tooltip" title="<?= htmlspecialchars($marcacao['obs_cliente']) ?>"><?= htmlspecialchars($marcacao['obs_cliente']) ?></span>
                                            <?php else: ?> <span class="text-muted small">-</span> <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= $classe_estado ?> rounded-pill px-3"><?= ucfirst($marcacao['estado']) ?></span></td>
                                        <td class="text-end pe-4">
                                            <?php if($marcacao['estado'] != 'realizada' && $marcacao['estado'] != 'cancelada'): ?>
                                                <div class="d-flex justify-content-center gap-2">
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="acao_estado" value="1">
                                                        <input type="hidden" name="id_marcacao" value="<?= $marcacao['id_marcacao'] ?>">
                                                        <?php if($marcacao['estado'] == 'por confirmar'): ?>
                                                            <input type="hidden" name="novo_estado" value="ativa">
                                                            <button type="submit" class="btn-action confirm" title="Confirmar Presença"><i class="bi bi-check-lg"></i></button>
                                                        <?php else: ?>
                                                            <input type="hidden" name="novo_estado" value="realizada">
                                                            <button type="submit" class="btn-action conclude" title="Finalizar Serviço"><i class="bi bi-check-all"></i></button>
                                                        <?php endif; ?>
                                                    </form>
                                                    <form method="POST" action="" onsubmit="return confirm('Tem a certeza que deseja cancelar?');">
                                                        <input type="hidden" name="acao_estado" value="1">
                                                        <input type="hidden" name="id_marcacao" value="<?= $marcacao['id_marcacao'] ?>">
                                                        <input type="hidden" name="novo_estado" value="cancelada">
                                                        <button type="submit" class="btn-action cancel" title="Cancelar"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                </div>
                                            <?php else: ?> <span class="text-muted small opacity-25"><i class="bi bi-dash-lg"></i></span> <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-5"><div class="text-muted"><i class="bi bi-calendar-x fs-1 d-block mb-3"></i><p class="mb-0">Sem marcações.</p></div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if(toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) { return new bootstrap.Tooltip(tooltipTriggerEl) })
    </script>
</body>
</html>