<?php
session_start();
date_default_timezone_set('Europe/Lisbon');

include('../verifica_login.php');

// --- CONFIGURAÇÃO DE SESSÃO E ACESSO ---
require_once '../../src/conexao.php';
require_once '../../src/helpers.php';      
require_once '../../src/send_email.php';

mysqli_set_charset($conn, "utf8mb4");

// Validar ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: list.php"); exit;
}

$id_marcacao = intval($_GET['id']);
$erro = "";
$hoje = date('Y-m-d');

// --- 1. OBTER DADOS DA MARCAÇÃO ATUAL ---
$query_atual = "
    SELECT 
        marcacao.*, 
        cliente.nome AS cliente_nome, 
        servico.designacao AS servico_nome, 
        servico.num_slots,
        funcionario.nome AS funcionario_nome,
        servico_funcionario.id_funcionario,
        servico_funcionario.id_servico,
        marcacao.id_servico_funcionario AS id_relacao
    FROM marcacao
    INNER JOIN cliente ON marcacao.id_cliente = cliente.id
    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
    INNER JOIN servico ON servico_funcionario.id_servico = servico.id
    INNER JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id
    WHERE marcacao.id = $id_marcacao
";
$res_atual = mysqli_query($conn, $query_atual);

if (mysqli_num_rows($res_atual) == 0) {
    header("Location: list.php"); exit;
}
$dados = mysqli_fetch_assoc($res_atual);

// ============================================================================
// AUTO-ATUALIZAÇÃO: Se a marcação for de uma data passada, passa a realizada
// ============================================================================
if ($dados['data'] < $hoje && in_array(strtolower($dados['estado']), ['ativa', 'por confirmar', 'pendente'])) {
    mysqli_query($conn, "UPDATE marcacao SET estado = 'realizada' WHERE id = $id_marcacao");
    $dados['estado'] = 'realizada';
}
// ============================================================================

// Definir valores iniciais para o formulário
// Se foi submetido via POST (para recarregar slots ao mudar data), usa o POST. Senão, usa o da BD.
$data_selecionada = isset($_POST['data_edit']) ? $_POST['data_edit'] : $dados['data'];
$estado_selecionado = isset($_POST['estado_edit']) ? $_POST['estado_edit'] : $dados['estado'];
$slots_disponiveis = [];

// --- 2. CALCULAR SLOTS DISPONÍVEIS ---
if ($data_selecionada) {
    
    $id_funcionario = $dados['id_funcionario'];
    $id_servico = $dados['id_servico'];
    $duracao = intval($dados['num_slots']);
    if ($duracao < 1) $duracao = 1;

    $mapa_dias = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];
    $dia_semana_num = date('w', strtotime($data_selecionada));
    $coluna_dia = $mapa_dias[$dia_semana_num];

    // Buscar horário base
    $q_disp = "SELECT slot_inicial, slot_final FROM disponibilidade WHERE id_servico = $id_servico AND '$data_selecionada' BETWEEN data_inicio AND data_fim AND $coluna_dia = 1";
    $r_disp = mysqli_query($conn, $q_disp);
    $horario_base = mysqli_fetch_assoc($r_disp);

    if ($horario_base) {
        $inicio_turno = intval($horario_base['slot_inicial']);
        $fim_turno = intval($horario_base['slot_final']);
        $bloqueados = [];

        // Verificar indisponibilidade do funcionário
        $q_indis = "SELECT slot_inicial, slot_final, $coluna_dia FROM indisponibilidade WHERE id_funcionario = $id_funcionario AND '$data_selecionada' BETWEEN data_inicio AND data_fim";
        $r_indis = mysqli_query($conn, $q_indis);

        while ($ind = mysqli_fetch_assoc($r_indis)) {
            if ($ind[$coluna_dia] == 1) {
                for ($k = $ind['slot_inicial']; $k < $ind['slot_final']; $k++) {
                    $bloqueados[$k] = true;
                }
            }
        }

        // Verificar Marcações Existentes (Ignorando a marcação atual)
        $q_marc = "
            SELECT marcacao.slot_inicial, marcacao.slot_final 
            FROM marcacao
            INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
            WHERE servico_funcionario.id_funcionario = $id_funcionario 
              AND marcacao.data = '$data_selecionada' 
              AND marcacao.estado != 'cancelada'
              AND marcacao.id != $id_marcacao 
        ";
        
        $r_marc = mysqli_query($conn, $q_marc);
        while ($m = mysqli_fetch_assoc($r_marc)) {
            for ($k = $m['slot_inicial']; $k < $m['slot_final']; $k++) {
                $bloqueados[$k] = true;
            }
        }

        // Gerar Slots Livres
        for ($i = $inicio_turno; $i <= ($fim_turno - $duracao); $i++) {
            $livre = true;
            for ($j = 0; $j < $duracao; $j++) {
                if (isset($bloqueados[$i + $j])) {
                    $livre = false; break;
                }
            }
            
            if ($livre) {
                $hora_fmt = converterSlotParaHora($i);
                $slots_disponiveis[] = ['id' => $i, 'hora' => $hora_fmt];
            }
        }
    } 
}

// Obter dias de trabalho do serviço para enviar ao calendário Javascript
$q_dias_trabalho = "SELECT domingo, segunda, terca, quarta, quinta, sexta, sabado FROM disponibilidade WHERE id_servico = " . $dados['id_servico'];
$r_dias = mysqli_query($conn, $q_dias_trabalho);
$dias_permitidos_js = [];
if ($row_dias = mysqli_fetch_assoc($r_dias)) {
    if ($row_dias['domingo'] == 1) $dias_permitidos_js[] = 0;
    if ($row_dias['segunda'] == 1) $dias_permitidos_js[] = 1;
    if ($row_dias['terca'] == 1) $dias_permitidos_js[] = 2;
    if ($row_dias['quarta'] == 1) $dias_permitidos_js[] = 3;
    if ($row_dias['quinta'] == 1) $dias_permitidos_js[] = 4;
    if ($row_dias['sexta'] == 1) $dias_permitidos_js[] = 5;
    if ($row_dias['sabado'] == 1) $dias_permitidos_js[] = 6;
}
$json_dias_permitidos = json_encode($dias_permitidos_js);


// --- 3. PROCESSAR SALVAR (POST DO BOTÃO GUARDAR) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['salvar_alteracoes'])) {
    
    $nova_data = isset($_POST['data_edit']) ? $_POST['data_edit'] : $dados['data'];
    $novo_estado = $_POST['estado_edit'];
    $novo_slot_ini = isset($_POST['slot_edit']) ? $_POST['slot_edit'] : null; 

    // Bloqueio de Segurança: Não permite concluir uma marcação com data no futuro
    if ($novo_estado == 'realizada' && $nova_data > $hoje) {
        $erro = "Não pode marcar como concluída uma marcação com data futura.";
    }

    if (empty($novo_slot_ini) && empty($erro)) {
        if ($nova_data != $dados['data']) {
             $erro = "Ao mudar a data, selecione o horário novamente.";
        } else {
            $novo_slot_ini = $dados['slot_inicial'];
        }
    }

    if (empty($erro)) {
        $novo_slot_fim = intval($novo_slot_ini) + intval($dados['num_slots']);
        
        $stmt = mysqli_prepare($conn, "UPDATE marcacao SET data=?, slot_inicial=?, slot_final=?, estado=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "siisi", $nova_data, $novo_slot_ini, $novo_slot_fim, $novo_estado, $id_marcacao);
        
        if (mysqli_stmt_execute($stmt)) {
            enviarEmailEstado($conn, $id_marcacao, $novo_estado);
            echo "<script>window.location.href='list.php?msg=Editado com sucesso';</script>";
            exit;
        } else {
            $erro = "Erro SQL: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Marcação | Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/booking/edit.css">

    <style>
        .calendar-wrapper { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e9ecef; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .weekdays-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .days-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center; }
        .weekday { font-weight: 600; font-size: 0.85rem; color: #6c757d; }
        
        .date-card { padding: 12px 5px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s; background: #fff; font-weight: 500; font-size: 0.95rem; text-align: center; display: block; width: 100%;}
        .date-card.empty { background: transparent; cursor: default; }
        
        .date-card.disabled { color: #ccc; cursor: not-allowed; background: #fafafa; }
        .date-card.available { background: #f8f9fa; border: 1px solid #dee2e6; color: #333; }
        .date-card.available:hover { border-color: #2e7d32; background: #e8f5e9; color: #2e7d32; transform: translateY(-2px); }
        .date-card.active { background: #2e7d32 !important; color: #fff !important; border-color: #2e7d32 !important; box-shadow: 0 4px 10px rgba(46,125,50,0.2); transform: translateY(-2px); }
    </style>
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>
    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold m-0 text-dark">Editar Marcação #<?= $id_marcacao ?></h3>
                <a href="list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Cancelar</a>
            </header>

            <div class="edit-container">
                <?php if($erro): ?><div class="alert alert-danger mb-4"><?= $erro ?></div><?php endif; ?>

                <form method="POST" id="editForm">

                    <div class="static-info">
                        <div>
                            <div class="info-label">Cliente</div>
                            <div class="info-value"><?= htmlspecialchars($dados['cliente_nome']) ?></div>
                        </div>
                        <div>
                            <div class="info-label">Serviço</div>
                            <div class="info-value"><?= htmlspecialchars($dados['servico_nome']) ?></div>
                            <div class="info-sub"><i class="bi bi-person"></i> <?= htmlspecialchars($dados['funcionario_nome']) ?></div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <div class="section-title">Estado da Marcação</div>
                        <select name="estado_edit" class="status-select">
                            <option value="por confirmar" <?= $estado_selecionado == 'por confirmar' ? 'selected' : '' ?>>Por Confirmar</option>
                            <option value="ativa" <?= $estado_selecionado == 'ativa' ? 'selected' : '' ?>>Ativa / Confirmada</option>
                            
                            <?php if ($data_selecionada <= $hoje || $estado_selecionado == 'realizada'): ?>
                                <option value="realizada" <?= $estado_selecionado == 'realizada' ? 'selected' : '' ?>>Realizada</option>
                            <?php endif; ?>
                            
                            <option value="cancelada" <?= $estado_selecionado == 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                        </select>
                        
                        <?php if ($data_selecionada > $hoje): ?>
                            <small class="text-muted mt-2 d-block"><i class="bi bi-info-circle"></i> O serviço só pode ser marcado como "Realizado" no próprio dia ou após o mesmo.</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-5">
                        <div class="section-title">Reagendar Data</div>
                        
                        <div class="calendar-wrapper">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <button type="button" class="btn btn-light btn-sm" onclick="changeMonth(-1)"><i class="bi bi-chevron-left"></i></button>
                                <h5 id="monthYearDisplay" class="m-0 fw-bold text-dark"></h5>
                                <button type="button" class="btn btn-light btn-sm" onclick="changeMonth(1)"><i class="bi bi-chevron-right"></i></button>
                            </div>
                            <div class="weekdays-grid">
                                <div class="weekday">Dom</div>
                                <div class="weekday">Seg</div>
                                <div class="weekday">Ter</div>
                                <div class="weekday">Qua</div>
                                <div class="weekday">Qui</div>
                                <div class="weekday">Sex</div>
                                <div class="weekday">Sáb</div>
                            </div>
                            <div id="calendarDays" class="days-grid"></div>
                        </div>
                        <input type="hidden" name="data_edit" id="inputData" value="<?= htmlspecialchars($data_selecionada) ?>">
                    </div>

                    <div class="mb-4">
                        <div class="section-title">Horário Disponível (<?= date('d/m/Y', strtotime($data_selecionada)) ?>)</div>
                        
                        <?php if (empty($slots_disponiveis)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Nenhum horário disponível para esta data ou profissional em folga.
                            </div>
                        <?php else: ?>
                            <div class="slots-grid">
                                <?php foreach($slots_disponiveis as $slot): 
                                    $isCurrent = ($data_selecionada == $dados['data'] && $slot['id'] == $dados['slot_inicial']);
                                    $checkSlot = $isCurrent ? 'checked' : '';
                                ?>
                                    <input type="radio" name="slot_edit" id="s_<?= $slot['id'] ?>" value="<?= $slot['id'] ?>" class="input-slot-hidden" <?= $checkSlot ?>>
                                    <label for="s_<?= $slot['id'] ?>" class="slot-card <?= $isCurrent ? 'slot-current' : '' ?>">
                                        <?= $slot['hora'] ?>
                                        <?php if($isCurrent): ?><br><small>(Atual)</small><?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" name="salvar_alteracoes" class="btn-save">
                        GUARDAR ALTERAÇÕES
                    </button>
                    
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggle = document.getElementById('sidebarToggle');
        if(toggle) toggle.addEventListener('click', () => document.querySelector('.sidebar').classList.toggle('active'));
        
        // VARIÁVEIS DO CALENDÁRIO
        const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        const diasTrabalhoPermitidos = <?= $json_dias_permitidos ?>;
        
        let selectedDateIso = "<?= $data_selecionada ?>";
        let originalBookingDate = "<?= $dados['data'] ?>";
        
        // Inicializa o calendário no mês da data selecionada
        let initDate = new Date(selectedDateIso + "T00:00:00");
        let currentMonth = initDate.getMonth();
        let currentYear = initDate.getFullYear();

        const today = new Date();
        today.setHours(0,0,0,0);

        const maxDateLimit = new Date();
        maxDateLimit.setMonth(today.getMonth() + 4);
        maxDateLimit.setHours(0,0,0,0);

        function renderCalendar() {
            const daysContainer = document.getElementById('calendarDays');
            daysContainer.innerHTML = '';
            document.getElementById('monthYearDisplay').innerText = `${monthNames[currentMonth]} ${currentYear}`;

            const firstDayIndex = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

            for (let i = 0; i < firstDayIndex; i++) {
                const emptyDiv = document.createElement('div');
                emptyDiv.className = 'date-card empty';
                daysContainer.appendChild(emptyDiv);
            }

            for (let i = 1; i <= daysInMonth; i++) {
                const dateObj = new Date(currentYear, currentMonth, i);
                const wday = dateObj.getDay(); 
                const dateIso = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;

                const dayDiv = document.createElement('div');
                dayDiv.className = 'date-card';
                dayDiv.innerText = i;

                // Lógica de Bloqueio
                // Bloqueia passado (mas permite clicar no dia atual original da marcação mesmo que no passado)
                if ((dateObj < today && dateIso !== originalBookingDate) || dateObj > maxDateLimit) {
                    dayDiv.classList.add('disabled'); 
                } else if (!diasTrabalhoPermitidos.includes(wday)) {
                    dayDiv.classList.add('disabled'); 
                } else {
                    dayDiv.classList.add('available'); 
                    dayDiv.onclick = () => cliqueDia(dateIso);
                }

                if (selectedDateIso === dateIso) {
                    dayDiv.classList.add('active');
                }

                daysContainer.appendChild(dayDiv);
            }
        }

        function changeMonth(dir) {
            const testDate = new Date(currentYear, currentMonth + dir, 1);
            
            // Permite voltar atrás apenas até ao mês atual OU ao mês da marcação original
            let minDateForMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            let originalBookingMonthDate = new Date(originalBookingDate.substring(0,4), parseInt(originalBookingDate.substring(5,7))-1, 1);
            
            if (originalBookingMonthDate < minDateForMonth) {
                minDateForMonth = originalBookingMonthDate;
            }

            const maxMonth = new Date(today.getFullYear(), today.getMonth() + 4, 1);

            if (testDate < minDateForMonth || testDate > maxMonth) {
                return; 
            }

            currentMonth += dir;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            else if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            renderCalendar();
        }

        function cliqueDia(dataIso) {
            // Ao clicar num dia disponível, atualiza o campo oculto e recarrega a página para buscar os slots
            document.getElementById('inputData').value = dataIso;
            document.getElementById('editForm').submit();
        }

        // Inicia o calendário
        renderCalendar();
    </script>
</body>
</html>