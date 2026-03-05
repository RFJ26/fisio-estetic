<?php
session_start();
date_default_timezone_set('Europe/Lisbon');

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php'; 
require_once __DIR__ . '/../../src/send_email.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['finalizar'])) {
    $id_c = intval($_POST['id_cliente']);
    $id_r = intval($_POST['id_relacao']);
    $data = mysqli_real_escape_string($conn, $_POST['data_escolhida']);
    $slot_ini = intval($_POST['slot_escolhido']);

    if ($id_c && $id_r && $data && $slot_ini !== '') {
        $q_dur = "SELECT servico.num_slots FROM servico_funcionario 
                  INNER JOIN servico ON servico_funcionario.id_servico = servico.id 
                  WHERE servico_funcionario.id = $id_r";
        
        $dur_res = mysqli_query($conn, $q_dur);
        if ($row_dur = mysqli_fetch_assoc($dur_res)) {
            $dur = intval($row_dur['num_slots']);
            $slot_fim = $slot_ini + $dur;

            // VERIFICAÇÃO DUPLA NA BASE DE DADOS
            $q_colisao = "SELECT marcacao.id FROM marcacao 
                          JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
                          WHERE marcacao.data = '$data' 
                          AND marcacao.estado != 'cancelada' 
                          AND (servico_funcionario.id_funcionario = (SELECT id_funcionario FROM servico_funcionario WHERE id = $id_r) 
                               OR marcacao.id_cliente = $id_c)
                          AND (marcacao.slot_inicial < $slot_fim AND marcacao.slot_final > $slot_ini)";
            
            $res_colisao = mysqli_query($conn, $q_colisao);

            if (mysqli_num_rows($res_colisao) > 0) {
                $erro = "Erro: O profissional ou o cliente já têm outra marcação neste horário.";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO marcacao (id_cliente, id_servico_funcionario, data, slot_inicial, slot_final, estado) VALUES (?, ?, ?, ?, ?, 'por confirmar')");
                mysqli_stmt_bind_param($stmt, "iisii", $id_c, $id_r, $data, $slot_ini, $slot_fim);
                
                try {
                    if (mysqli_stmt_execute($stmt)) {
                        $novo_id = mysqli_insert_id($conn);
                        enviarEmailEstado($conn, $novo_id, 'por confirmar');

                        echo "<script>window.location.href = 'list.php?msg=Marcação criada e email enviado!';</script>";
                        exit;
                    } else {
                        $erro = "Erro SQL: " . mysqli_error($conn);
                    }
                } catch (Exception $e) {
                    $erro = "Erro: " . $e->getMessage();
                }
            }
        } else {
            $erro = "Erro: Serviço não encontrado.";
        }
    } else {
        $erro = "Preencha todos os campos.";
    }
}

$res_cli = mysqli_query($conn, "SELECT id, nome FROM cliente ORDER BY nome ASC");

$q_servicos = "
    SELECT 
        servico_funcionario.id AS id_relacao, 
        servico.designacao, 
        servico.preco, 
        servico.num_slots,
        funcionario.nome AS nome_funcionario 
    FROM servico_funcionario 
    INNER JOIN servico ON servico_funcionario.id_servico = servico.id 
    INNER JOIN funcionario ON servico_funcionario.id_funcionario = funcionario.id 
    WHERE servico_funcionario.ativo = 1 
    ORDER BY servico.designacao ASC
";
$res_serv = mysqli_query($conn, $q_servicos);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nova Marcação | Fisioestetic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/booking/create.css">

    <style>
        .calendar-wrapper { background: #fff; padding: 25px; border-radius: 12px; border: 1px solid #e9ecef; box-shadow: 0 2px 10px rgba(0,0,0,0.02); }
        .weekdays-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; text-align: center; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .days-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center; }
        .weekday { font-weight: 600; font-size: 0.85rem; color: #6c757d; }
        .date-card { padding: 12px 5px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s; background: #fff; font-weight: 500; font-size: 0.95rem; }
        .date-card.empty { background: transparent; cursor: default; }
        .date-card.disabled { color: #ccc; cursor: not-allowed; background: #fafafa; }
        .date-card.available { background: #f8f9fa; border: 1px solid #dee2e6; color: #333; }
        .date-card.available:hover { border-color: #2e7d32; background: #e8f5e9; color: #2e7d32; transform: translateY(-2px); }
        .date-card.active { background: #2e7d32 !important; color: #fff !important; border-color: #2e7d32 !important; box-shadow: 0 4px 10px rgba(46,125,50,0.2); transform: translateY(-2px); }
        .select2-container .select2-selection--single { height: 38px; border: 1px solid #dee2e6; border-radius: 0.375rem; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: #212529; line-height: 36px; }
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
        <div class="booking-section" style="max-width: 800px; margin: 0 auto;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold m-0">Nova Marcação</h3>
                <a href="list.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Voltar</a>
            </div>

            <?php if(isset($erro)): ?>
                <div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $erro ?></div>
            <?php endif; ?>

            <form method="POST" id="mainForm">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Cliente</label>
                        <select name="id_cliente" id="selectCliente" class="form-select searchable-select" required>
                            <option value="" selected disabled>Pesquisar Cliente...</option>
                            <?php while($c = mysqli_fetch_assoc($res_cli)): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Serviço</label>
                        <select name="id_relacao" id="selectServico" class="form-select searchable-select" required>
                            <option value="" selected disabled>Pesquisar Serviço ou Profissional...</option>
                            <?php while($s = mysqli_fetch_assoc($res_serv)): ?>
                                <option value="<?= $s['id_relacao'] ?>">
                                    <?= htmlspecialchars($s['designacao']) ?> 
                                    (<?= converterSlotsParaDuracao($s['num_slots']) ?>) 
                                    - <?= htmlspecialchars($s['nome_funcionario']) ?> 
                                    (€<?= $s['preco'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label mb-2">Data da Marcação</label>
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
                    
                    <input type="hidden" name="data_escolhida" id="inputData" required>
                    <input type="hidden" name="slot_escolhido" id="inputSlot" required>
                </div>

                <div id="areaSlots" style="display:none;">
                    <label class="form-label">Horários Disponíveis</label>
                    <hr class="mt-0 mb-3 text-muted">
                    <div id="loader" class="loader"><div class="spinner-border text-success" role="status"></div></div>
                    <div id="gridSlots" class="slots-container"></div>
                    <div id="msgSemVagas" class="alert alert-warning mt-3 text-center small" style="display:none;">Não existem vagas disponíveis para esta data.</div>
                    <button type="submit" name="finalizar" id="btnFinalizar" class="btn btn-success w-100 mt-3 p-3 fw-bold" style="display:none;">CONFIRMAR MARCAÇÃO</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const toggle = document.getElementById('sidebarToggle');
        if(toggle) toggle.addEventListener('click', () => document.querySelector('.sidebar').classList.toggle('active'));

        const selectServico = document.getElementById('selectServico');
        const selectCliente = document.getElementById('selectCliente');
        const inputData = document.getElementById('inputData');
        const inputSlot = document.getElementById('inputSlot');
        const areaSlots = document.getElementById('areaSlots');
        const loader = document.getElementById('loader');
        const gridSlots = document.getElementById('gridSlots');
        const btnFinalizar = document.getElementById('btnFinalizar');
        const msgSemVagas = document.getElementById('msgSemVagas');

        $(document).ready(function() {
            $('.searchable-select').select2({
                width: '100%',
                language: { noResults: function () { return "Nenhum resultado encontrado."; } }
            });

            $('#selectServico').on('change', function() {
                carregarDiasTrabalho();
            });

            // Se o admin mudar o cliente mas já tiver escolhido um dia, atualizamos as horas!
            $('#selectCliente').on('change', function() {
                if (inputData.value !== '') {
                    const elAtivo = document.querySelector('.date-card.active');
                    if(elAtivo) cliqueDia(elAtivo, inputData.value);
                }
            });
        });

        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        let diasTrabalhoPermitidos = []; 

        const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

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

                if (dateObj < today || dateObj > maxDateLimit) {
                    dayDiv.classList.add('disabled'); 
                    if (dateObj > maxDateLimit) dayDiv.title = "Apenas 4 meses de antecedência.";
                } else if (!selectServico.value) {
                    dayDiv.classList.add('disabled'); 
                } else if (diasTrabalhoPermitidos.includes(wday)) {
                    dayDiv.classList.add('available'); 
                    dayDiv.onclick = () => cliqueDia(dayDiv, dateIso);
                } else {
                    dayDiv.classList.add('disabled'); 
                }

                if (inputData.value === dateIso) {
                    dayDiv.classList.add('active');
                }

                daysContainer.appendChild(dayDiv);
            }
        }

        function changeMonth(dir) {
            const testDate = new Date(currentYear, currentMonth + dir, 1);
            const minMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const maxMonth = new Date(today.getFullYear(), today.getMonth() + 4, 1);

            if (testDate < minMonth || testDate > maxMonth) { return; }

            currentMonth += dir;
            if (currentMonth < 0) { currentMonth = 11; currentYear--; }
            else if (currentMonth > 11) { currentMonth = 0; currentYear++; }
            renderCalendar();
        }

        function carregarDiasTrabalho() {
            const idRelacao = selectServico.value;
            resetarSelecao();
            if(!idRelacao) {
                renderCalendar();
                return;
            }
            
            fetch(`get_working_days.php?id_relacao=${idRelacao}`)
                .then(r => r.json())
                .then(diasPermitidos => {
                    diasTrabalhoPermitidos = diasPermitidos;
                    renderCalendar();
                });
        }

        function cliqueDia(el, dataIso) {
            if(!el.classList.contains('available')) return;
            
            // OBRIGAR O ADMIN A SELECIONAR A JOANA ALMEIDA (CLIENTE) PRIMEIRO
            const idCliente = selectCliente.value;
            if (!idCliente) {
                alert("Por favor, selecione primeiro o Cliente!");
                return;
            }
            
            document.querySelectorAll('.date-card').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            
            inputData.value = dataIso;
            inputSlot.value = '';
            areaSlots.style.display = 'block';
            gridSlots.innerHTML = '';
            btnFinalizar.style.display = 'none';
            msgSemVagas.style.display = 'none';
            loader.style.display = 'block';
            
            // ENVIA O ID DO CLIENTE PARA APAGAR AS HORAS QUE A JOANA JÁ TEM OCUPADAS!
            fetch(`get_slots.php?data=${dataIso}&id_relacao=${selectServico.value}&id_cliente=${idCliente}`)
                .then(r => r.json())
                .then(slots => {
                    loader.style.display = 'none';
                    if(slots.length === 0) msgSemVagas.style.display = 'block';
                    else {
                        slots.forEach(slot => {
                            const btn = document.createElement('div');
                            btn.className = 'slot-btn';
                            btn.textContent = slot.hora;
                            btn.onclick = () => cliqueSlot(btn, slot.id);
                            gridSlots.appendChild(btn);
                        });
                    }
                });
        }

        function cliqueSlot(el, id) {
            document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('active'));
            el.classList.add('active');
            inputSlot.value = id;
            btnFinalizar.style.display = 'block';
        }

        function resetarSelecao() {
            inputData.value = '';
            inputSlot.value = '';
            areaSlots.style.display = 'none';
            document.querySelectorAll('.date-card').forEach(c => c.classList.remove('active'));
        }

        renderCalendar();
    </script>
</body>
</html>