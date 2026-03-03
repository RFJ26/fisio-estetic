<?php
session_start();


include('../verifica_login.php');
require '../../src/conexao.php';
require '../../src/helpers.php';

// Configuração
$hora_inicio_dia = 8; 
$minutos_por_slot = 30;

// Validação e IDs
if (!isset($_GET['id']) && !isset($_POST['id_funcionario'])) {
    header('Location: list.php');
    exit();
}
$id_funcionario = isset($_POST['id_funcionario']) ? $_POST['id_funcionario'] : $_GET['id'];
$mensagem = ""; $tipo_msg = "";

// Lógica POST (Adicionar)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nova_indisponibilidade'])) {
    $motivo = mysqli_real_escape_string($conn, $_POST['motivo']);
    $data_inicio = $_POST['data_inicio']; $data_fim = $_POST['data_fim'];
    $hora_in = $_POST['hora_inicio']; $hora_out = $_POST['hora_fim'];
    
    $seg = isset($_POST['seg']) ? 1 : 0;
    $ter = isset($_POST['ter']) ? 1 : 0;
    $qua = isset($_POST['qua']) ? 1 : 0;
    $qui = isset($_POST['qui']) ? 1 : 0;
    $sex = isset($_POST['sex']) ? 1 : 0;
    $sab = isset($_POST['sab']) ? 1 : 0;
    $dom = isset($_POST['dom']) ? 1 : 0;
    
    $slot_inicial = converterHoraParaSlot($hora_in, $hora_inicio_dia, $minutos_por_slot);
    $slot_final = converterHoraParaSlot($hora_out, $hora_inicio_dia, $minutos_por_slot) - 1;

    if (strtotime($data_inicio) > strtotime($data_fim)) {
        $mensagem = "A data de início não pode ser superior à data de fim."; $tipo_msg = "danger";
    } elseif ($slot_inicial > $slot_final) {
        $mensagem = "A hora de início deve ser anterior à hora de fim."; $tipo_msg = "danger";
    } else {
        $sql_insert = "INSERT INTO indisponibilidade 
            (id_funcionario, motivo, data_inicio, data_fim, slot_inicial, slot_final, segunda, terca, quarta, quinta, sexta, sabado, domingo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql_insert);
        mysqli_stmt_bind_param($stmt, "isssiiiiiiiii", $id_funcionario, $motivo, $data_inicio, $data_fim, $slot_inicial, $slot_final, $seg, $ter, $qua, $qui, $sex, $sab, $dom);
        
        if (mysqli_stmt_execute($stmt)) { 
            $mensagem = "Indisponibilidade registada com sucesso!"; 
            $tipo_msg = "success"; 
        } else { 
            $mensagem = "Erro: " . mysqli_error($conn); 
            $tipo_msg = "danger"; 
        }
    }
}

// Lógica GET (Apagar)
if (isset($_GET['delete_id'])) {
    $id_del = $_GET['delete_id'];
    $sql_del = "DELETE FROM indisponibilidade WHERE id = ? AND id_funcionario = ?";
    $stmt = mysqli_prepare($conn, $sql_del);
    mysqli_stmt_bind_param($stmt, "ii", $id_del, $id_funcionario);
    if (mysqli_stmt_execute($stmt)) { $mensagem = "Bloqueio removido."; $tipo_msg = "success"; }
}

// Consultas
$query_func = mysqli_query($conn, "SELECT nome FROM funcionario WHERE id = '$id_funcionario'");
$funcionario = mysqli_fetch_assoc($query_func);

$query_lista = "SELECT * FROM indisponibilidade WHERE id_funcionario = '$id_funcionario' ORDER BY data_inicio DESC";
$lista = mysqli_query($conn, $query_lista);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bloqueios - <?= htmlspecialchars($funcionario['nome']) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/view_indisponibilidade.css">
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
            
            <a href="list.php" class="btn-back mb-3"><i class="bi bi-arrow-left"></i> Voltar à lista</a>

            <div class="header-actions d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><?= htmlspecialchars($funcionario['nome']) ?></h1>
                    <p class="text-muted mb-0">Gestão de horários bloqueados e férias.</p>
                </div>
                <button type="button" class="btn btn-gold btn-new-block" data-bs-toggle="modal" data-bs-target="#modalAdd">
                    <i class="bi bi-plus-lg"></i> Novo Bloqueio
                </button>
            </div>

            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $tipo_msg ?> alert-dismissible fade show shadow-sm">
                    <?= $mensagem ?> 
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <section class="card-indisponibilidade">
                <div class="table-responsive">
                    <table class="table table-hover table-indisp align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Motivo</th>
                                <th style="width: 20%;">Período</th>
                                <th style="width: 25%;">Horário</th>
                                <th style="width: 25%;">Dias Bloqueados</th>
                                <th style="width: 10%;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($lista) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($lista)): 
                                    $d_ini = date('d/m/Y', strtotime($row['data_inicio']));
                                    $d_fim = date('d/m/Y', strtotime($row['data_fim']));
                                    $h_ini = converterSlotParaHora($row['slot_inicial'], $hora_inicio_dia, $minutos_por_slot, false);
                                    $h_fim = converterSlotParaHora($row['slot_final'], $hora_inicio_dia, $minutos_por_slot, true);
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="icon-box me-3"><i class="bi bi-slash-circle"></i></div>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($row['motivo']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if($d_ini == $d_fim): ?>
                                            <span class="date-badge single"><?= $d_ini ?></span>
                                        <?php else: ?>
                                            <div class="date-badge period">
                                                <span><i class="bi bi-arrow-right-short me-1"></i>De: <?= $d_ini ?></span>
                                                <span><i class="bi bi-arrow-left-short me-1"></i>Até: <?= $d_fim ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="time-capsule">
                                            <div class="time-values"><?= $h_ini ?> <i class="bi bi-arrow-right time-arrow"></i> <?= $h_fim ?></div>
                                            <div class="slot-badge">Slots: <?= $row['slot_inicial'] ?> - <?= $row['slot_final'] ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="badge-days">
                                            <span class="day-circle <?= $row['segunda'] ? 'active' : '' ?>">S</span>
                                            <span class="day-circle <?= $row['terca'] ? 'active' : '' ?>">T</span>
                                            <span class="day-circle <?= $row['quarta'] ? 'active' : '' ?>">Q</span>
                                            <span class="day-circle <?= $row['quinta'] ? 'active' : '' ?>">Q</span>
                                            <span class="day-circle <?= $row['sexta'] ? 'active' : '' ?>">S</span>
                                            <span class="day-circle <?= $row['sabado'] ? 'active' : '' ?>">S</span>
                                            <span class="day-circle <?= $row['domingo'] ? 'active' : '' ?>">D</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        <a href="view_indisponibilidade.php?id=<?= $id_funcionario ?>&delete_id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-trash" onclick="return confirm('Tem a certeza que deseja remover este bloqueio? O horário voltará a ficar disponível.')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">Nenhum bloqueio registado. O horário está totalmente disponível.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="modal fade" id="modalAdd" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.1);">
                <form method="POST">
                    <div class="modal-header border-bottom-0 pb-0">
                        <h5 class="modal-title fw-bold text-danger">Novo Bloqueio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-4">
                        <input type="hidden" name="id_funcionario" value="<?= $id_funcionario ?>">
                        <input type="hidden" name="nova_indisponibilidade" value="1">
                        
                        <div class="mb-4">
                            <label class="form-label-custom">Motivo</label>
                            <select name="motivo" class="form-select form-select-custom" required>
                                <option value="Folga">Folga Semanal</option>
                                <option value="Férias">Férias</option>
                                <option value="Médico">Consulta Médica / Baixa</option>
                                <option value="Pessoal">Assuntos Pessoais</option>
                                <option value="Outro">Outro</option>
                            </select>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label-custom">Data Início</label>
                                <input type="date" name="data_inicio" class="form-control form-control-custom" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label-custom">Data Fim</label>
                                <input type="date" name="data_fim" class="form-control form-control-custom" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label-custom mb-0">Horário</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="diaTodoToggle">
                                    <label class="form-check-label small text-muted" for="diaTodoToggle">Dia Inteiro</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6"><input type="time" name="hora_inicio" id="horaInicio" class="form-control form-control-custom" value="08:00" required></div>
                                <div class="col-6"><input type="time" name="hora_fim" id="horaFim" class="form-control form-control-custom" value="20:00" required></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Dias Bloqueados (Verde = Livre | Vermelho = Bloqueado):</label>
                            <div class="week-selector">
                                <?php 
                                $dias = ['seg'=>'S', 'ter'=>'T', 'qua'=>'Q', 'qui'=>'Q', 'sex'=>'S', 'sab'=>'S', 'dom'=>'D'];
                                foreach($dias as $key => $label): 
                                ?>
                                <div class="day-checkbox-wrapper">
                                    <input type="checkbox" name="<?= $key ?>" id="check_<?= $key ?>" checked>
                                    <label for="check_<?= $key ?>" class="day-label-styled"><?= $label ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-save px-4">Gravar Bloqueio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle'); 
        if(toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));

        const toggleDia = document.getElementById('diaTodoToggle');
        const hInicio = document.getElementById('horaInicio');
        const hFim = document.getElementById('horaFim');
        toggleDia.addEventListener('change', function() {
            if(this.checked) {
                hInicio.value = '08:00'; hFim.value = '23:59';
                hInicio.setAttribute('readonly', true); hFim.setAttribute('readonly', true);
                hInicio.classList.add('bg-light'); hFim.classList.add('bg-light');
            } else {
                hInicio.removeAttribute('readonly'); hFim.removeAttribute('readonly');
                hInicio.classList.remove('bg-light'); hFim.classList.remove('bg-light');
            }
        });
    </script>
</body>
</html>