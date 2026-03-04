<?php
session_start();

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php'; 

// =========================================================================
// FUNÇÃO DE CONVERSÃO EXATA (Slot para Hora)
// =========================================================================
function slotParaHora15m($slot) {
    $s = $slot - 1; 
    $h = 8 + floor($s / 4);
    $m = ($s % 4) * 15;
    return sprintf("%02d:%02d", $h, $m);
}

// =========================================================================
// 1. DADOS DO FUNCIONÁRIO E SEGURANÇA
// =========================================================================
$id_funcionario = $_SESSION['id'] ?? 0;
$nome_funcionario = $_SESSION['nome'] ?? 'Colaborador';

if ($id_funcionario == 0 && !empty($nome_funcionario)) {
    $nome_seguro = mysqli_real_escape_string($conn, $nome_funcionario);
    $query_recupera = "SELECT id FROM funcionario WHERE nome = '$nome_seguro' LIMIT 1";
    $res_recupera = mysqli_query($conn, $query_recupera);
    if ($dados = mysqli_fetch_assoc($res_recupera)) {
        $id_funcionario = $dados['id'];
        $_SESSION['id'] = $id_funcionario;
    }
}

// =========================================================================
// 2. PROCESSAR FORMULÁRIO (POST)
// =========================================================================
$mensagem_sucesso = '';
$mensagem_erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ADICIONAR ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'adicionar') {
        
        $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio']);
        $data_fim    = mysqli_real_escape_string($conn, $_POST['data_fim']);
        $motivo      = mysqli_real_escape_string($conn, $_POST['motivo']);
        $slot_inicial = (int)$_POST['slot_inicial'];
        $slot_final   = (int)$_POST['slot_final'];

        // Receber os dias da semana marcados (0=Dom, 1=Seg, ..., 6=Sáb)
        $dias_selecionados = $_POST['dias'] ?? [];
        
        $domingo = in_array('0', $dias_selecionados) ? 1 : 0;
        $segunda = in_array('1', $dias_selecionados) ? 1 : 0;
        $terca   = in_array('2', $dias_selecionados) ? 1 : 0;
        $quarta  = in_array('3', $dias_selecionados) ? 1 : 0;
        $quinta  = in_array('4', $dias_selecionados) ? 1 : 0;
        $sexta   = in_array('5', $dias_selecionados) ? 1 : 0;
        $sabado  = in_array('6', $dias_selecionados) ? 1 : 0;

        // Validações
        if ($slot_inicial >= $slot_final) {
            $mensagem_erro = "A hora de fim deve ser superior à hora de início.";
        } elseif ($data_inicio > $data_fim) {
            $mensagem_erro = "A data de fim não pode ser anterior à data de início.";
        } elseif (empty($dias_selecionados)) {
            $mensagem_erro = "Por favor, selecione pelo menos um dia da semana.";
        } else {
            // Inserir na base de dados
            $query_insert = "
                INSERT INTO indisponibilidade 
                (id_funcionario, data_inicio, data_fim, motivo, 
                 domingo, segunda, terca, quarta, quinta, sexta, sabado, 
                 slot_inicial, slot_final) 
                VALUES 
                ('$id_funcionario', '$data_inicio', '$data_fim', '$motivo',
                 '$domingo', '$segunda', '$terca', '$quarta', '$quinta', '$sexta', '$sabado',
                 '$slot_inicial', '$slot_final')
            ";
            
            if (mysqli_query($conn, $query_insert)) {
                $query_cancelar = "
                    UPDATE marcacao
                    INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
                    SET marcacao.estado = 'cancelada'
                    WHERE servico_funcionario.id_funcionario = '$id_funcionario'
                    AND marcacao.data BETWEEN '$data_inicio' AND '$data_fim'
                    AND marcacao.estado IN ('ativa', 'por confirmar')
                    AND (marcacao.slot_inicial < '$slot_final' AND marcacao.slot_final > '$slot_inicial')
                    AND (
                        (DAYOFWEEK(marcacao.data) = 1 AND $domingo = 1) OR
                        (DAYOFWEEK(marcacao.data) = 2 AND $segunda = 1) OR
                        (DAYOFWEEK(marcacao.data) = 3 AND $terca = 1) OR
                        (DAYOFWEEK(marcacao.data) = 4 AND $quarta = 1) OR
                        (DAYOFWEEK(marcacao.data) = 5 AND $quinta = 1) OR
                        (DAYOFWEEK(marcacao.data) = 6 AND $sexta = 1) OR
                        (DAYOFWEEK(marcacao.data) = 7 AND $sabado = 1)
                    )
                ";

                mysqli_query($conn, $query_cancelar);
                $linhas_afetadas = mysqli_affected_rows($conn);

                $mensagem_sucesso = "Bloqueio criado com sucesso.";
                if ($linhas_afetadas > 0) {
                    $mensagem_sucesso .= " <strong>Nota:</strong> Foram canceladas $linhas_afetadas marcações coincidentes.";
                }

            } else {
                $mensagem_erro = "Erro ao inserir: " . mysqli_error($conn);
            }
        }
    }
    
    // --- REMOVER ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'remover') {
        $id_bloqueio = intval($_POST['id_bloqueio']);
        $query_delete = "DELETE FROM indisponibilidade WHERE id = '$id_bloqueio' AND id_funcionario = '$id_funcionario'";
        
        if (mysqli_query($conn, $query_delete)) {
            $mensagem_sucesso = "Bloqueio removido com sucesso.";
        } else {
            $mensagem_erro = "Erro ao remover: " . mysqli_error($conn);
        }
    }
}

// =========================================================================
// 3. CONSULTA SQL
// =========================================================================
$query_indis = "
    SELECT * FROM indisponibilidade 
    WHERE id_funcionario = '$id_funcionario' 
    AND data_fim >= CURDATE()
    ORDER BY data_inicio ASC, slot_inicial ASC
";
$resultado_indis = mysqli_query($conn, $query_indis);

if (!$resultado_indis) {
    die("Erro na query: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indisponibilidade - Fisioestetic</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/indisponibilidade.css"> 
    
    </head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none">
        <i class="bi bi-list"></i>
    </button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area">
            <h2>Fisioestetic</h2>
        </div>
        
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-grid-1x2-fill me-3"></i> Início
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="minhas_marcacoes.php">
                    <i class="bi bi-calendar-check me-3"></i> Agenda
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="indisponibilidade.php">
                    <i class="bi bi-slash-circle me-3"></i> Indisponibilidade
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="perfil.php">
                    <i class="bi bi-person-circle me-3"></i> Meu Perfil
                </a>
            </li>
            
            <li class="nav-item mt-auto">
                <a class="nav-link logout" href="../logout.php">
                    <i class="bi bi-box-arrow-left me-3"></i> Sair
                </a>
            </li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">

            <header class="page-header">
                <div>
                    <h2 class="fw-bold m-0 text-dark">Indisponibilidade</h2>
                    <p class="text-muted m-0">Gerir ausências e bloqueios de agenda.</p>
                </div>
                <button class="btn-add d-md-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalAdicionar">
                    <i class="bi bi-plus-lg"></i> Novo Bloqueio
                </button>
            </header>

            <?php if(!empty($mensagem_sucesso)): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $mensagem_sucesso ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($mensagem_erro)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $mensagem_erro ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-responsive">
                    <table class="table custom-table mb-0">
                        <thead>
                            <tr>
                                <th width="30%" class="ps-4">Período</th>
                                <th width="25%">Horário</th>
                                <th width="30%">Motivo</th>
                                <th width="15%" class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($resultado_indis && mysqli_num_rows($resultado_indis) > 0): ?>
                                <?php while($item = mysqli_fetch_assoc($resultado_indis)): ?>
                                    <?php 
                                        $hora_inicio_vis = slotParaHora15m($item['slot_inicial']);
                                        $hora_fim_vis = slotParaHora15m($item['slot_final']);
                                        
                                        // Construir string dos dias da semana
                                        $dias_str = [];
                                        if($item['segunda']) $dias_str[] = 'Seg';
                                        if($item['terca']) $dias_str[] = 'Ter';
                                        if($item['quarta']) $dias_str[] = 'Qua';
                                        if($item['quinta']) $dias_str[] = 'Qui';
                                        if($item['sexta']) $dias_str[] = 'Sex';
                                        if($item['sabado']) $dias_str[] = 'Sáb';
                                        if($item['domingo']) $dias_str[] = 'Dom';
                                        
                                        $todos_os_dias = (count($dias_str) == 7) ? "Todos os dias" : implode(', ', $dias_str);
                                        $mesmo_dia = ($item['data_inicio'] === $item['data_fim']);
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="date-text">
                                                <i class="bi bi-calendar4-week text-muted"></i>
                                                <?php if($mesmo_dia): ?>
                                                    <?= date('d/m/Y', strtotime($item['data_inicio'])) ?>
                                                <?php else: ?>
                                                    <?= date('d/m/Y', strtotime($item['data_inicio'])) ?> <i class="bi bi-arrow-right text-muted mx-1"></i> <?= date('d/m/Y', strtotime($item['data_fim'])) ?>
                                                <?php endif; ?>
                                            </div>
                                            <span class="days-text">
                                                <i class="bi bi-arrow-return-right me-1"></i> <?= $todos_os_dias ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="time-badge">
                                                <i class="bi bi-clock"></i>
                                                <?= $hora_inicio_vis ?> <i class="bi bi-arrow-right mx-1"></i> <?= $hora_fim_vis ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-medium"><?= htmlspecialchars($item['motivo']) ?></span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <form method="POST" action="" onsubmit="return confirm('Tem a certeza que deseja libertar este bloqueio?');" class="d-flex justify-content-end">
                                                <input type="hidden" name="acao" value="remover">
                                                <input type="hidden" name="id_bloqueio" value="<?= $item['id'] ?>">
                                                
                                                <button type="submit" class="btn-delete" title="Apagar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-muted">
                                        <i class="bi bi-slash-circle display-4 d-block mb-3 opacity-50"></i>
                                        Nenhuma indisponibilidade registada para si.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAdicionar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom-0 pb-0 mt-3 mx-3">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-calendar-minus" style="color: var(--primary-color, #0d6efd);"></i> Nova Indisponibilidade
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <input type="hidden" name="acao" value="adicionar">
                        
                        <div class="alert alert-warning border-0 d-flex align-items-start mb-4" style="background-color: #fff8e1; color: #f57f17; border-radius: 12px;">
                            <i class="bi bi-exclamation-triangle-fill me-3 mt-1 fs-5"></i>
                            <div class="small">
                                <strong>Atenção:</strong> Ao confirmar, qualquer marcação existente neste período será <span class="fw-bold">cancelada automaticamente</span>.
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Data Início</label>
                                <input type="date" name="data_inicio" class="form-control bg-light" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Data Fim</label>
                                <input type="date" name="data_fim" class="form-control bg-light" required min="<?= date('Y-m-d') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-muted small text-uppercase mb-2">Dias da Semana afetados</label>
                            <div class="btn-group w-100" role="group">
                                <input type="checkbox" class="btn-check" name="dias[]" id="dia1" value="1" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="dia1">2ª</label>

                                <input type="checkbox" class="btn-check" name="dias[]" id="dia2" value="2" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="dia2">3ª</label>

                                <input type="checkbox" class="btn-check" name="dias[]" id="dia3" value="3" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="dia3">4ª</label>

                                <input type="checkbox" class="btn-check" name="dias[]" id="dia4" value="4" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="dia4">5ª</label>

                                <input type="checkbox" class="btn-check" name="dias[]" id="dia5" value="5" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="dia5">6ª</label>

                                <input type="checkbox" class="btn-check" name="dias[]" id="dia6" value="6" autocomplete="off">
                                <label class="btn btn-outline-primary" for="dia6">Sáb</label>

                                <input type="checkbox" class="btn-check" name="dias[]" id="dia0" value="0" autocomplete="off">
                                <label class="btn btn-outline-primary" for="dia0">Dom</label>
                            </div>
                        </div>
                        
                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Hora de Início</label>
                                <select name="slot_inicial" class="form-select bg-light" required>
                                    <option value="" disabled selected>Selecione...</option>
                                    <?php for($i=1; $i<=56; $i++): // 08:00 às 21:45 ?>
                                        <option value="<?= $i ?>"><?= slotParaHora15m($i) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Hora de Fim</label>
                                <select name="slot_final" class="form-select bg-light" required>
                                    <option value="" disabled selected>Selecione...</option>
                                    <?php for($i=2; $i<=57; $i++): // 08:15 às 22:00 ?>
                                        <option value="<?= $i ?>"><?= slotParaHora15m($i) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold text-muted small text-uppercase">Motivo</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-card-text text-muted"></i></span>
                                <select name="motivo" class="form-select border-start-0 ps-0 bg-light" required>
                                    <option value="" selected disabled>Selecione...</option>
                                    <option value="Almoço">Pausa para Almoço</option>
                                    <option value="Consulta Médica">Consulta Médica</option>
                                    <option value="Férias">Férias</option>
                                    <option value="Formação">Formação</option>
                                    <option value="Reunião semanal">Reunião semanal</option>
                                    <option value="Pessoal">Pessoal</option>
                                    <option value="Outro">Outro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top-0 pt-0 pb-4 px-4">
                        <button type="button" class="btn btn-light fw-medium w-100 mb-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary w-100 m-0 justify-content-center" style="background-color: var(--primary-color, #0d6efd); border: none;">Confirmar Bloqueio</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        if(sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                document.querySelector('.sidebar').classList.toggle('active');
            });
        }
    </script>
</body>
</html>