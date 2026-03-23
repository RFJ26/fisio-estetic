<?php
// ============================================================================
// INICIALIZAÇÃO E CONFIGURAÇÕES DE BASE
// ============================================================================
session_start();
date_default_timezone_set('Europe/Lisbon'); 

require_once __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

mysqli_set_charset($conn, "utf8");

// Identificação do Funcionário
$id_funcionario = $_COOKIE['id_funcionario'] ?? $_COOKIE['id'] ?? 0;
$nome_funcionario = $_COOKIE['nome'] ?? 'Colaborador';

if ($id_funcionario == 0 && !empty($nome_funcionario)) {
    $nome_seguro = mysqli_real_escape_string($conn, $nome_funcionario);
    $query_recupera = "SELECT id FROM funcionario WHERE nome = '$nome_seguro' LIMIT 1";
    if ($dados = mysqli_fetch_assoc(mysqli_query($conn, $query_recupera))) {
        $id_funcionario = $dados['id'];
        $_COOKIE['id_funcionario'] = $id_funcionario;
    }
}

// ============================================================================
// API 1: CARREGAR A CONTAGEM DE MARCAÇÕES (SÓ DESTE FUNCIONÁRIO)
// ============================================================================
if (isset($_GET['fetch_counts'])) {
    header("Content-Type: application/json");

    $condicoes = ["marcacao.estado != 'cancelada'", "servico_funcionario.id_funcionario = $id_funcionario"];
    
    if (!empty($_GET['serv'])) { 
        $servs = implode(',', array_map('intval', explode(',', $_GET['serv'])));
        $condicoes[] = "servico_funcionario.id_servico IN ($servs)"; 
    }
    if (!empty($_GET['est'])) { 
        $ests = explode(',', $_GET['est']);
        $ests_limpos = array_map(function($e) use ($conn) { return "'" . mysqli_real_escape_string($conn, $e) . "'"; }, $ests);
        $condicoes[] = "marcacao.estado IN (" . implode(',', $ests_limpos) . ")"; 
    }

    $condicoes_sql = implode(" AND ", $condicoes);

    $sql = "SELECT marcacao.data, COUNT(marcacao.id) as total 
            FROM marcacao 
            INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
            WHERE $condicoes_sql 
            GROUP BY marcacao.data";
    
    $resultado = mysqli_query($conn, $sql);
    $contagens = [];
    
    while ($linha = mysqli_fetch_assoc($resultado)) {
        $contagens[] = [
            'start' => $linha['data'],
            'allDay' => true,
            'extendedProps' => ['total' => $linha['total']]
        ];
    }
    echo json_encode($contagens);
    exit;
}

// ============================================================================
// API 2: CARREGAR DETALHES DO DIA (COM TELEFONE E OBS)
// ============================================================================
if (isset($_GET['fetch_day_details'])) {
    header("Content-Type: application/json");
    
    $data_selecionada = mysqli_real_escape_string($conn, $_GET['data']);
    $condicoes = ["marcacao.data = '$data_selecionada'", "marcacao.estado != 'cancelada'", "servico_funcionario.id_funcionario = $id_funcionario"];
    
    if (!empty($_GET['serv'])) { 
        $servs = implode(',', array_map('intval', explode(',', $_GET['serv'])));
        $condicoes[] = "servico_funcionario.id_servico IN ($servs)"; 
    }
    if (!empty($_GET['est'])) { 
        $ests = explode(',', $_GET['est']);
        $ests_limpos = array_map(function($e) use ($conn) { return "'" . mysqli_real_escape_string($conn, $e) . "'"; }, $ests);
        $condicoes[] = "marcacao.estado IN (" . implode(',', $ests_limpos) . ")"; 
    }

    $condicoes_sql = implode(" AND ", $condicoes);

    $sql = "SELECT 
                marcacao.slot_inicial, 
                marcacao.slot_final,
                cliente.nome as cliente, 
                cliente.telefone as telefone,
                cliente.obs as obs,
                servico.designacao as servico, 
                marcacao.estado
            FROM marcacao 
            INNER JOIN cliente ON marcacao.id_cliente = cliente.id
            INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
            INNER JOIN servico ON servico_funcionario.id_servico = servico.id
            WHERE $condicoes_sql
            ORDER BY marcacao.slot_inicial ASC";
    
    $resultado = mysqli_query($conn, $sql);
    $detalhes_dia = [];
    
    function converterSlotParaHoraFormato($slot) {
        $minutos_totais = ($slot - 1) * 15;
        $hora = floor($minutos_totais / 60) + 8;
        $minutos = $minutos_totais % 60;
        return sprintf('%02d:%02d', $hora, $minutos);
    }

    while ($linha = mysqli_fetch_assoc($resultado)) {
        $linha['hora_inicio'] = converterSlotParaHoraFormato($linha['slot_inicial']);
        $linha['hora_fim'] = converterSlotParaHoraFormato($linha['slot_final']);
        $detalhes_dia[] = $linha;
    }
    
    echo json_encode($detalhes_dia);
    exit;
}

// Buscar serviços disponíveis deste funcionário para o filtro
$lista_servicos = mysqli_query($conn, "
    SELECT DISTINCT servico.id, servico.designacao 
    FROM servico 
    INNER JOIN servico_funcionario ON servico.id = servico_funcionario.id_servico
    WHERE servico_funcionario.id_funcionario = $id_funcionario
    ORDER BY servico.designacao ASC
");
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agenda Visual - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>

    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/booking/agenda.css">
</head>
<body>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link active" href="minhas_marcacoes.php"><i class="bi bi-calendar-check me-3"></i>Agenda</a></li>
            <li class="nav-item"><a class="nav-link" href="indisponibilidade.php"><i class="bi bi-slash-circle me-3"></i>Indisponibilidade</a></li>
            <li class="nav-item"><a class="nav-link" href="perfil.php"><i class="bi bi-person-circle me-3"></i>Meu Perfil</a></li>
            
            <?php if(isset($_COOKIE['role']) && ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === '1')): ?>
                <li class="nav-item mt-3"><a class="nav-link" href="/select_role.php" style="color: #ef6c00;"><i class="bi bi-arrow-left-right me-3"></i>Mudar Perfil</a></li>
            <?php endif; ?>

            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Minha Agenda Visual</h1>
                <p class="text-muted mb-0">Visão mensal do teu calendário de trabalho.</p>
            </div>
            <a href="minhas_marcacoes.php" class="btn btn-custom-primary rounded-pill px-4 py-2 fw-semibold">
                <i class="bi bi-list-task me-2"></i> Ver em Lista
            </a>
        </div>

        <div class="filter-bar mb-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-scissors me-1"></i> Meus Serviços</label>
                    <select id="filtroServico" class="form-select" multiple>
                        <?php while($servico = mysqli_fetch_assoc($lista_servicos)): ?>
                            <option value="<?= $servico['id'] ?>"><?= htmlspecialchars($servico['designacao']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="small fw-bold text-muted mb-1"><i class="bi bi-check-circle me-1"></i> Estados</label>
                    <select id="filtroEstado" class="form-select" multiple>
                        <option value="ativa" selected>Confirmadas</option>
                        <option value="por confirmar" selected>Pendentes</option>
                        <option value="realizada">Realizadas</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="calendar-container">
            <div id='calendar'></div>
        </div>
    </div>

    <div class="offcanvas offcanvas-end shadow-lg" tabindex="-1" id="offcanvasDia" style="width: 450px;">
        <div class="offcanvas-header py-4 bg-white border-bottom">
            <div>
                <h5 class="offcanvas-title fw-bold mb-0 text-dark" id="tituloDia">Detalhes do Dia</h5>
                <small class="text-muted" id="subtituloDia">Carregando...</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div id="listaDetalhes"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        const choiceOptions = {
            removeItemButton: true,
            searchPlaceholderValue: 'Procurar...',
            noResultsText: 'Nenhum resultado',
            itemSelectText: 'Selecionar'
        };
        const choiceServ = new Choices('#filtroServico', choiceOptions);
        const choiceEst = new Choices('#filtroEstado', choiceOptions);

        const offcanvasElement = document.getElementById('offcanvasDia');
        const bsOffcanvas = new bootstrap.Offcanvas(offcanvasElement);
        
        function getSelectValues(selectId) {
            const select = document.getElementById(selectId);
            return Array.from(select.selectedOptions).map(opt => opt.value).join(',');
        }

        var calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
          initialView: 'dayGridMonth',
          locale: 'pt',
          firstDay: 1,
          contentHeight: 'auto', 
          headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
          buttonText: { today: 'Hoje' },
          
          events: function(info, successCallback, failureCallback) {
            let servId = getSelectValues('filtroServico');
            let estId = getSelectValues('filtroEstado');
            
            fetch(`minha_agenda.php?fetch_counts=1&serv=${servId}&est=${estId}`)
              .then(res => res.json())
              .then(data => successCallback(data))
              .catch(err => failureCallback(err));
          },

          eventContent: function(arg) {
             let customHtml = `
                <div class="event-count-badge" title="Ver marcações">
                    <i class="bi bi-calendar2-check"></i>
                    <span class="badge-texto-longo">${arg.event.extendedProps.total} marcações</span>
                    <span class="badge-texto-curto">${arg.event.extendedProps.total}</span>
                </div>`;
             return { html: customHtml };
          },

          dateClick: function(info) {
            const dataObjeto = new Date(info.dateStr);
            const dataFormatada = dataObjeto.toLocaleDateString('pt-PT', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            
            document.getElementById('tituloDia').innerText = info.dateStr.split('-').reverse().join('/');
            document.getElementById('subtituloDia').innerText = dataFormatada.charAt(0).toUpperCase() + dataFormatada.slice(1);
            
            document.getElementById('listaDetalhes').innerHTML = `
                <div class="text-center mt-5">
                    <div class="spinner-border text-success" style="color: var(--primary-color) !important;" role="status"></div>
                </div>`;
            
            bsOffcanvas.show();

            let servId = getSelectValues('filtroServico');
            let estId = getSelectValues('filtroEstado');

            fetch(`minha_agenda.php?fetch_day_details=1&data=${info.dateStr}&serv=${servId}&est=${estId}`)
                .then(resposta => resposta.json())
                .then(dados => {
                    let html = '';
                    if(dados.length === 0) {
                        html = `
                        <div class="text-center py-5 mt-4">
                            <i class="bi bi-cup-hot" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-3 fw-medium">Dia livre!<br>Não tens marcações para estes filtros.</p>
                        </div>`;
                    } else {
                        dados.forEach(marcacao => {
                            let classeEstado = 'ativa';
                            let displayEstado = 'Confirmada';
                            
                            if (marcacao.estado === 'por confirmar') { 
                                classeEstado = 'pendente'; 
                                displayEstado = 'Pendente'; 
                            } else if (marcacao.estado === 'realizada') { 
                                classeEstado = 'realizada'; 
                                displayEstado = 'Realizada'; 
                            }

                            let obsHtml = marcacao.obs && marcacao.obs.trim() !== '' 
                                ? `<div class="mt-2 p-2 rounded bg-light border text-muted small"><i class="bi bi-chat-left-text me-2"></i>${marcacao.obs}</div>` 
                                : '';

                            let telefoneHtml = marcacao.telefone 
                                ? `<span class="ms-3"><i class="bi bi-telephone me-1"></i>${marcacao.telefone}</span>` 
                                : '';

                            html += `
                            <div class="timeline-card estado-${classeEstado}">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div class="time-range shadow-sm">
                                        <i class="bi bi-clock text-muted"></i> 
                                        ${marcacao.hora_inicio} <i class="bi bi-arrow-right mx-1" style="color:#adb5bd;"></i> ${marcacao.hora_fim}
                                    </div>
                                    <span class="st-badge ${classeEstado}">${displayEstado}</span>
                                </div>
                                <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-person me-2 text-muted"></i>${marcacao.cliente}</h6>
                                <div class="d-flex flex-column gap-1 small text-secondary">
                                    <span>
                                        <i class="bi bi-scissors me-2"></i>${marcacao.servico}
                                        ${telefoneHtml}
                                    </span>
                                </div>
                                ${obsHtml}
                            </div>`;
                        });
                    }
                    document.getElementById('listaDetalhes').innerHTML = html;
                });
          }
        });
        
        calendar.render();

        document.getElementById('filtroServico').addEventListener('change', () => calendar.refetchEvents());
        document.getElementById('filtroEstado').addEventListener('change', () => calendar.refetchEvents());
      });
    </script>
</body>
</html>