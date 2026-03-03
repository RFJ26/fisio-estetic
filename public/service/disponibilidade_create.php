<?php
session_start();
include('../verifica_login.php');
require '../../src/conexao.php';

// Verifica se temos o ID do serviço
if (!isset($_GET['id_servico']) || empty($_GET['id_servico'])) {
    header('Location: list.php');
    exit();
}

$id_servico = (int)$_GET['id_servico'];

// 1. Buscar nome do serviço
$stmt = $conn->prepare("SELECT designacao FROM servico WHERE id = ?");
$stmt->bind_param("i", $id_servico);
$stmt->execute();
$res_servico = $stmt->get_result();
$servico = $res_servico->fetch_assoc();

if (!$servico) {
    header('Location: list.php');
    exit();
}

// 2. GERAR LISTA DE HORÁRIOS (15 min, 08:00 até 21:45)
$lista_horarios = [];
$inicio = strtotime('08:00');
$fim    = strtotime('21:45');
$slot_id = 1;

while ($inicio <= $fim) {
    $lista_horarios[$slot_id] = date('H:i', $inicio);
    $inicio = strtotime('+15 minutes', $inicio);
    $slot_id++;
}

// 3. INICIALIZAR VARIÁVEIS
$data_inicio_val = date('Y-m-d');
$data_fim_val    = date('Y-m-d', strtotime('+1 year'));

$dias_db = [
    'segunda' => 1, 'terca' => 1, 'quarta' => 1, 
    'quinta' => 1, 'sexta' => 1, 'sabado' => 0, 'domingo' => 0
];

$manha_ativo = false; $m_ini_db = ''; $m_fim_db = '';
$tarde_ativo = false; $t_ini_db = ''; $t_fim_db = '';

// 4. BUSCAR DADOS EXISTENTES
$stmt = $conn->prepare("SELECT * FROM disponibilidade WHERE id_servico = ? ORDER BY slot_inicial ASC");
$stmt->bind_param("i", $id_servico);
$stmt->execute();
$res_disp = $stmt->get_result();

if ($res_disp->num_rows > 0) {
    while($row = $res_disp->fetch_assoc()){
        $data_inicio_val = $row['data_inicio'];
        $data_fim_val    = $row['data_fim'];
        
        $dias_db['domingo'] = $row['domingo'];
        $dias_db['segunda'] = $row['segunda'];
        $dias_db['terca']   = $row['terca'];
        $dias_db['quarta']  = $row['quarta'];
        $dias_db['quinta']  = $row['quinta'];
        $dias_db['sexta']   = $row['sexta'];
        $dias_db['sabado']  = $row['sabado'];

        if($row['slot_inicial'] < 21) { 
            $manha_ativo = true;
            $m_ini_db = $row['slot_inicial'];
            $m_fim_db = $row['slot_final'];
        } else {
            $tarde_ativo = true;
            $t_ini_db = $row['slot_inicial'];
            $t_fim_db = $row['slot_final'];
        }
    }
}

// --- LÓGICA DE GRAVAÇÃO ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $data_inicio = $_POST['data_inicio'];
    $data_fim    = $_POST['data_fim'];
    
    $domingo = isset($_POST['domingo']) ? 1 : 0;
    $segunda = isset($_POST['segunda']) ? 1 : 0;
    $terca   = isset($_POST['terca'])   ? 1 : 0;
    $quarta  = isset($_POST['quarta'])  ? 1 : 0;
    $quinta  = isset($_POST['quinta'])  ? 1 : 0;
    $sexta   = isset($_POST['sexta'])   ? 1 : 0;
    $sabado  = isset($_POST['sabado'])  ? 1 : 0;

    $msgs = [];
    $erro = false;

    $conn->begin_transaction();

    try {
        $stmt_del = $conn->prepare("DELETE FROM disponibilidade WHERE id_servico = ?");
        $stmt_del->bind_param("i", $id_servico);
        $stmt_del->execute();

        $sql_insert = "INSERT INTO disponibilidade 
            (data_inicio, data_fim, domingo, segunda, terca, quarta, quinta, sexta, sabado, id_servico, slot_inicial, slot_final) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt_insert = $conn->prepare($sql_insert);

        if (isset($_POST['ativar_manha'])) {
            $m_inicio = (int)$_POST['manha_inicio'];
            $m_fim    = (int)$_POST['manha_fim'];

            if (!empty($m_inicio) && !empty($m_fim)) {
                if ($m_inicio >= $m_fim) {
                    throw new Exception("O horário final da manhã deve ser maior que o inicial.");
                }
                $stmt_insert->bind_param("ssiiiiiiiiii", $data_inicio, $data_fim, $domingo, $segunda, $terca, $quarta, $quinta, $sexta, $sabado, $id_servico, $m_inicio, $m_fim);
                $stmt_insert->execute();
                $msgs[] = "Manhã";
            }
        }

        if (isset($_POST['ativar_tarde'])) {
            $t_inicio = (int)$_POST['tarde_inicio'];
            $t_fim    = (int)$_POST['tarde_fim'];

            if (!empty($t_inicio) && !empty($t_fim)) {
                 if ($t_inicio >= $t_fim) {
                    throw new Exception("O horário final da tarde deve ser maior que o inicial.");
                }
                $stmt_insert->bind_param("ssiiiiiiiiii", $data_inicio, $data_fim, $domingo, $segunda, $terca, $quarta, $quinta, $sexta, $sabado, $id_servico, $t_inicio, $t_fim);
                $stmt_insert->execute();
                $msgs[] = "Tarde";
            }
        }

        $conn->commit();
        
        if (empty($msgs)) {
            $status_msg = "Horários removidos. Nenhum novo horário configurado.";
            $status_type = "warning";
            $manha_ativo = false; $tarde_ativo = false;
        } else {
            $status_msg = "Sucesso: Horários de " . implode(" e ", $msgs) . " atualizados.";
            $status_type = "success";
        }

        $data_inicio_val = $data_inicio;
        $data_fim_val = $data_fim;
        $dias_db = ['domingo'=>$domingo, 'segunda'=>$segunda, 'terca'=>$terca, 'quarta'=>$quarta, 'quinta'=>$quinta, 'sexta'=>$sexta, 'sabado'=>$sabado];
        
        if(isset($_POST['ativar_manha'])) { $manha_ativo = true; $m_ini_db = $_POST['manha_inicio']; $m_fim_db = $_POST['manha_fim']; }
        else { $manha_ativo = false; $m_ini_db = ''; $m_fim_db = ''; }

        if(isset($_POST['ativar_tarde'])) { $tarde_ativo = true; $t_ini_db = $_POST['tarde_inicio']; $t_fim_db = $_POST['tarde_fim']; }
        else { $tarde_ativo = false; $t_ini_db = ''; $t_fim_db = ''; }

    } catch (Exception $e) {
        $conn->rollback();
        $erro = true;
        $status_msg = "Erro: " . $e->getMessage();
        $status_type = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Horário - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service/disponibilidade.css">
    
    
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>
    
    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Configurar Horário</h2>
                    <p class="text-muted">Serviço: <strong class="text-dark"><?= htmlspecialchars($servico['designacao']) ?></strong></p>
                </div>
                <a href="list.php" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-2"></i>Voltar</a>
            </div>

            <?php if(isset($status_msg)): ?>
                <div class="alert alert-<?= $status_type ?> alert-dismissible fade show">
                    <?= $status_msg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                
                <div class="section-card">
                    <div class="section-header">
                        <span><i class="bi bi-calendar-week me-2 text-warning"></i> Configuração Geral</span>
                    </div>
                    <div class="section-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label text-muted small text-uppercase fw-bold">Período de Validade</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-calendar"></i></span>
                                    <input type="date" name="data_inicio" class="form-control" required value="<?= htmlspecialchars($data_inicio_val) ?>">
                                    <span class="input-group-text bg-white">até</span>
                                    <input type="date" name="data_fim" class="form-control" required value="<?= htmlspecialchars($data_fim_val) ?>">
                                </div>
                            </div>
                        </div>

                        <label class="form-label text-muted small text-uppercase fw-bold mb-3">Dias da Semana</label>
                        <div class="day-selector">
                            <div class="day-check"><input type="checkbox" name="segunda" id="d_seg" <?= $dias_db['segunda'] ? 'checked' : '' ?>><label for="d_seg">S</label></div>
                            <div class="day-check"><input type="checkbox" name="terca" id="d_ter" <?= $dias_db['terca'] ? 'checked' : '' ?>><label for="d_ter">T</label></div>
                            <div class="day-check"><input type="checkbox" name="quarta" id="d_qua" <?= $dias_db['quarta'] ? 'checked' : '' ?>><label for="d_qua">Q</label></div>
                            <div class="day-check"><input type="checkbox" name="quinta" id="d_qui" <?= $dias_db['quinta'] ? 'checked' : '' ?>><label for="d_qui">Q</label></div>
                            <div class="day-check"><input type="checkbox" name="sexta" id="d_sex" <?= $dias_db['sexta'] ? 'checked' : '' ?>><label for="d_sex">S</label></div>
                            <div class="day-check"><input type="checkbox" name="sabado" id="d_sab" <?= $dias_db['sabado'] ? 'checked' : '' ?>><label for="d_sab">S</label></div>
                            <div class="day-check"><input type="checkbox" name="domingo" id="d_dom" <?= $dias_db['domingo'] ? 'checked' : '' ?>><label for="d_dom">D</label></div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-2">
                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white shadow-sm h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold text-muted"><i class="bi bi-sunrise me-2 text-warning"></i> Manhã</span>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativar_manha" id="switchManha" onchange="toggleInputs('manha')" <?= $manha_ativo ? 'checked' : '' ?>>
                                </div>
                            </div>
                            
                            <div id="area-manha" style="opacity: <?= $manha_ativo ? '1' : '0.5' ?>; pointer-events: <?= $manha_ativo ? 'auto' : 'none' ?>;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="small text-muted">Hora Início</label>
                                        <select name="manha_inicio" class="form-select time-select">
                                            <option value="">-- Selecione --</option>
                                            <?php foreach($lista_horarios as $id => $hora): ?>
                                                <option value="<?= $id ?>" <?= ($m_ini_db == $id) ? 'selected' : '' ?>><?= $hora ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="small text-muted">Hora Fim</label>
                                        <select name="manha_fim" class="form-select time-select">
                                            <option value="">-- Selecione --</option>
                                            <?php foreach($lista_horarios as $id => $hora): ?>
                                                <option value="<?= $id ?>" <?= ($m_fim_db == $id) ? 'selected' : '' ?>><?= $hora ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 border rounded bg-white shadow-sm h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="fw-bold text-muted"><i class="bi bi-sunset me-2 text-warning"></i> Tarde</span>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativar_tarde" id="switchTarde" onchange="toggleInputs('tarde')" <?= $tarde_ativo ? 'checked' : '' ?>>
                                </div>
                            </div>

                            <div id="area-tarde" style="opacity: <?= $tarde_ativo ? '1' : '0.5' ?>; pointer-events: <?= $tarde_ativo ? 'auto' : 'none' ?>;">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="small text-muted">Hora Início</label>
                                        <select name="tarde_inicio" class="form-select time-select">
                                            <option value="">-- Selecione --</option>
                                            <?php foreach($lista_horarios as $id => $hora): ?>
                                                <option value="<?= $id ?>" <?= ($t_ini_db == $id) ? 'selected' : '' ?>><?= $hora ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="small text-muted">Hora Fim</label>
                                        <select name="tarde_fim" class="form-select time-select">
                                            <option value="">-- Selecione --</option>
                                            <?php foreach($lista_horarios as $id => $hora): ?>
                                                <option value="<?= $id ?>" <?= ($t_fim_db == $id) ? 'selected' : '' ?>><?= $hora ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4 mb-5">
                    <button type="submit" class="btn btn-brand btn-lg px-5 shadow-sm">
                        <i class="bi bi-check-lg me-2"></i> Atualizar Horário
                    </button>
                    
                </div>

            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        if(toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));

        function toggleInputs(periodo) {
            const capitalized = periodo.charAt(0).toUpperCase() + periodo.slice(1);
            const isChecked = document.getElementById('switch' + capitalized).checked;
            const area = document.getElementById('area-' + periodo);
            
            if(isChecked) {
                area.style.opacity = '1';
                area.style.pointerEvents = 'auto';
            } else {
                area.style.opacity = '0.5';
                area.style.pointerEvents = 'none';
                
                const selects = area.querySelectorAll('select');
                selects.forEach(s => s.value = "");
            }
        }
    </script>
</body>
</html>