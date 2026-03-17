<?php
session_start();
// --- FORÇAR FUSO HORÁRIO DE PORTUGAL ---
date_default_timezone_set('Europe/Lisbon');

require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/send_email.php';

// ============================================================================
// 1. VERIFICAR LOGIN PADRÃO DO CLIENTE
// ============================================================================
// Se o cliente não estiver logado, é redirecionado para a página inicial
if (!isset($_COOKIE['id'])) {
    header("Location: ../index.php");
    exit();
}
$id_cliente = $_COOKIE['id'];
$nome_cliente = $_COOKIE['nome'];
$primeiro_nome = explode(" ", $nome_cliente)[0]; // Extrai apenas o primeiro nome para o menu

// ============================================================================
// 2. GESTÃO DE ESTADO DA PÁGINA (VIA GET)
// ============================================================================
// Obtém o passo atual do "Wizard" (1 a 4) e os IDs selecionados no URL
$passo_atual = isset($_GET['step']) ? intval($_GET['step']) : 1;
$id_categoria = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
$id_servico = isset($_GET['serv']) ? intval($_GET['serv']) : 0;
$id_funcionario = isset($_GET['func']) ? intval($_GET['func']) : 0;
$data_selecionada = isset($_GET['data']) ? $_GET['data'] : '';

// Segurança: Impede que o cliente salte para um passo avançado sem preencher os anteriores
if ($passo_atual > 1 && $id_categoria == 0) $passo_atual = 1;
if ($passo_atual > 2 && $id_servico == 0) $passo_atual = 1;
if ($passo_atual > 3 && $id_funcionario == 0) $passo_atual = 1;

// Obter os nomes descritivos das opções selecionadas para mostrar no "Resumo"
$nome_categoria_selecionada = "";
$nome_servico_selecionado = "";
$nome_funcionario_selecionado = "";

if ($id_categoria > 0) {
    $q_cat = mysqli_query($conn, "SELECT designacao FROM categoria WHERE id = '$id_categoria'");
    if ($r = mysqli_fetch_assoc($q_cat)) $nome_categoria_selecionada = $r['designacao'];
}
if ($id_servico > 0) {
    $q_serv = mysqli_query($conn, "SELECT designacao FROM servico WHERE id = '$id_servico'");
    if ($r = mysqli_fetch_assoc($q_serv)) $nome_servico_selecionado = $r['designacao'];
}
if ($id_funcionario > 0) {
    $q_func = mysqli_query($conn, "SELECT nome FROM funcionario WHERE id = '$id_funcionario'");
    if ($r = mysqli_fetch_assoc($q_func)) $nome_funcionario_selecionado = $r['nome'];
}

// Função auxiliar para converter o número do slot (ex: 1) numa hora legível (ex: 08:00)
if (!function_exists('converterSlotParaHoraLocal')) {
    function converterSlotParaHoraLocal($slot) {
        $hora_inicio = 8; // O serviço começa às 8h da manhã
        $minutos_por_slot = 15; // Cada slot tem 15 minutos
        $minutos_totais = ($slot - 1) * $minutos_por_slot;
        $horas = $hora_inicio + floor($minutos_totais / 60);
        $minutos = $minutos_totais % 60;
        return sprintf("%02d:%02d", $horas, $minutos);
    }
}

// ============================================================================
// 3. PROCESSAMENTO DA MARCAÇÃO (VIA POST - QUANDO CLICA EM "CONFIRMAR")
// ============================================================================
$mensagem_erro = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_reserva'])) {

    // Receber os dados submetidos pelo formulário final
    $post_categoria = $_POST['p_cat'];
    $post_servico = $_POST['p_serv'];
    $post_funcionario = $_POST['p_func'];
    $post_data = $_POST['p_data'];
    $post_slot_inicio = isset($_POST['slot_selecionado']) ? intval($_POST['slot_selecionado']) : -1;

    $limite_seguranca = date('Y-m-d', strtotime('+4 months')); // Data limite para marcações

    // Validação de segurança: Não permitir marcações além de 4 meses
    if ($post_data > $limite_seguranca) {
        $mensagem_erro = "Erro: A data excede o limite permitido.";
    } elseif ($post_slot_inicio > 0) { 

        // 3.1. Obter a relação de ID entre o serviço e o funcionário
        $query_relacao = "SELECT id FROM servico_funcionario WHERE id_servico = '$post_servico' AND id_funcionario = '$post_funcionario'";
        $resultado_relacao = mysqli_query($conn, $query_relacao);
        $dados_relacao = mysqli_fetch_assoc($resultado_relacao);

        if ($dados_relacao) {
            $id_relacao_servico_funcionario = $dados_relacao['id'];

            // 3.2. Calcular quantos slots o serviço ocupa
            $query_duracao = "SELECT num_slots FROM servico WHERE id = '$post_servico'";
            $duracao_slots = intval(mysqli_fetch_assoc(mysqli_query($conn, $query_duracao))['num_slots']);
            $slot_final_reserva = $post_slot_inicio + $duracao_slots;

            // 3.3. VERIFICAÇÃO DUPLA (O PROFISSIONAL ESTÁ LIVRE? E O CLIENTE ESTÁ LIVRE?)
            $query_verificacao = "SELECT marcacao.id FROM marcacao 
                                  JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
                                  WHERE marcacao.data = '$post_data' 
                                  AND marcacao.estado != 'cancelada' 
                                  AND (servico_funcionario.id_funcionario = '$post_funcionario' OR marcacao.id_cliente = '$id_cliente') 
                                  AND (marcacao.slot_inicial < $slot_final_reserva AND marcacao.slot_final > $post_slot_inicio)";

            $verificacao = mysqli_query($conn, $query_verificacao);

            // Se não houver marcações a colidir, prosseguir com a inserção
            if (mysqli_num_rows($verificacao) == 0) {
                $query_insert = "INSERT INTO marcacao (id_cliente, id_servico_funcionario, data, slot_inicial, slot_final, estado) VALUES (?, ?, ?, ?, ?, 'por confirmar')";

                if ($stmt = mysqli_prepare($conn, $query_insert)) {
                    mysqli_stmt_bind_param($stmt, "iisii", $id_cliente, $id_relacao_servico_funcionario, $post_data, $post_slot_inicio, $slot_final_reserva);

                    try {
                        if (mysqli_stmt_execute($stmt)) {
                            // Marcação feita com sucesso! Enviar e-mail e redirecionar
                            $id_nova_marcacao = mysqli_insert_id($conn);
                            enviarEmailEstado($conn, $id_nova_marcacao, 'por confirmar');

                            header("Location: my_bookings.php?msg=sucesso");
                            exit;
                        } else {
                            $mensagem_erro = "Erro técnico ao gravar na base de dados.";
                        }
                    } catch (Exception $e) {
                        $mensagem_erro = "Erro: " . $e->getMessage();
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                $mensagem_erro = "Você ou o profissional já têm uma marcação nesse horário.";
            }
        } else {
            $mensagem_erro = "Erro na configuração do serviço.";
        }
    } else {
        $mensagem_erro = "Selecione um horário válido.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Marcação | Fisioestetic</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../css/customer/booking_new.css">

    <style>
        /* =======================================================================
           CORREÇÃO DO CALENDÁRIO: 
           Oculta os dias do mês anterior e do mês seguinte na grelha.
           Assim, se estiveres em Fevereiro, só aparecem mesmo dias de Fevereiro.
           ======================================================================= */
        .flatpickr-day.prevMonthDay, 
        .flatpickr-day.nextMonthDay {
            visibility: hidden !important; 
        }
    </style>
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
                    <li class="nav-item"><a class="nav-link active" href="dashboard.php">Início</a></li>
                    <li class="nav-item"><a class="nav-link" href="booking_new.php">Nova Marcação</a></li>
                    <li class="nav-item"><a class="nav-link" href="my_bookings.php">Histórico</a></li>
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

    <div class="container py-4">

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-danger text-center rounded-3 shadow-sm"><?= $mensagem_erro ?></div>
        <?php endif; ?>

        <div class="wizard-progress">
            <div class="progress-line"></div>
            
            <div class="step-item <?= $passo_atual >= 1 ? 'active' : '' ?> <?= $passo_atual > 1 ? 'completed' : '' ?>" 
                 onclick="window.location.href='?step=1'" style="cursor: pointer;">
                <div class="step-circle">1</div>
                <div class="step-label">Categoria</div>
            </div>
            
            <div class="step-item <?= $passo_atual >= 2 ? 'active' : '' ?> <?= $passo_atual > 2 ? 'completed' : '' ?>" 
                 <?= ($passo_atual >= 2 && $id_categoria > 0) ? "onclick=\"window.location.href='?step=2&cat=$id_categoria'\" style=\"cursor: pointer;\"" : "" ?>>
                <div class="step-circle">2</div>
                <div class="step-label">Serviço</div>
            </div>
            
            <div class="step-item <?= $passo_atual >= 3 ? 'active' : '' ?> <?= $passo_atual > 3 ? 'completed' : '' ?>" 
                 <?= ($passo_atual >= 3 && $id_servico > 0) ? "onclick=\"window.location.href='?step=3&cat=$id_categoria&serv=$id_servico'\" style=\"cursor: pointer;\"" : "" ?>>
                <div class="step-circle">3</div>
                <div class="step-label">Profissional</div>
            </div>
            
            <div class="step-item <?= $passo_atual >= 4 ? 'active' : '' ?>" 
                 <?= ($passo_atual >= 4 && $id_funcionario > 0) ? "onclick=\"window.location.href='?step=4&cat=$id_categoria&serv=$id_servico&func=$id_funcionario'\" style=\"cursor: pointer;\"" : "" ?>>
                <div class="step-circle">4</div>
                <div class="step-label">Horário</div>
            </div>
        </div>

        <?php if ($passo_atual > 1): ?>
            <div class="row justify-content-center mb-4">
                <div class="col-lg-10">
                    <div class="card border-0 shadow-sm" style="border-radius: 12px; background-color: #f8f9fa;">
                        <div class="card-body p-3 d-flex flex-wrap align-items-center gap-2 text-muted" style="font-size: 0.95rem;">
                            <span class="fw-bold text-dark me-2"><i class="bi bi-list-check me-1"></i> Resumo:</span>
                            
                            <?php if ($nome_categoria_selecionada): ?>
                                <span class="badge bg-white text-dark border shadow-sm p-2"><i class="bi bi-grid me-1"></i> <?= htmlspecialchars($nome_categoria_selecionada) ?></span>
                            <?php endif; ?>
                            
                            <?php if ($nome_servico_selecionado): ?>
                                <i class="bi bi-chevron-right small text-black-50"></i>
                                <span class="badge bg-white text-dark border shadow-sm p-2"><i class="bi bi-stars me-1"></i> <?= htmlspecialchars($nome_servico_selecionado) ?></span>
                            <?php endif; ?>
                            
                            <?php if ($nome_funcionario_selecionado): ?>
                                <i class="bi bi-chevron-right small text-black-50"></i>
                                <span class="badge bg-white text-dark border shadow-sm p-2"><i class="bi bi-person me-1"></i> <?= htmlspecialchars($nome_funcionario_selecionado) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-10">

                <?php if ($passo_atual == 1): ?>
                    <h4 class="text-center fw-bold mb-4">Selecione a Categoria</h4>
                    <div class="row g-4">
                        <?php
                        $query_categorias = mysqli_query($conn, "SELECT * FROM categoria ORDER BY designacao ASC");
                        while ($dados_categoria = mysqli_fetch_assoc($query_categorias)): ?>
                            <div class="col-md-4 col-6">
                                <a href="?step=2&cat=<?= $dados_categoria['id'] ?>" class="option-card">
                                    <div class="card-icon"><i class="bi bi-grid-fill"></i></div>
                                    <div class="card-title"><?= htmlspecialchars($dados_categoria['designacao']) ?></div>
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>

                <?php if ($passo_atual == 2): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="?step=1" class="btn-outline-back"><i class="bi bi-arrow-left"></i> Voltar</a>
                        <h4 class="fw-bold m-0">Selecione o Serviço</h4>
                        <div style="width: 80px;"></div>
                    </div>
                    <div class="row g-3">
                        <?php
                        $query_servicos = mysqli_query($conn, "SELECT * FROM servico WHERE id_categoria = '$id_categoria' ORDER BY designacao ASC");

                        if (mysqli_num_rows($query_servicos) > 0):
                            while ($dados_servico = mysqli_fetch_assoc($query_servicos)):
                                $tempo_minutos = $dados_servico['num_slots'] * 15;
                        ?>
                                <div class="col-md-4 col-sm-6">
                                    <a href="?step=3&cat=<?= $id_categoria ?>&serv=<?= $dados_servico['id'] ?>" class="option-card">
                                        <div class="card-icon"><i class="bi bi-stars"></i></div>
                                        <div class="card-title"><?= htmlspecialchars($dados_servico['designacao']) ?></div>
                                        <div class="card-meta">
                                            <?= $tempo_minutos ?> min • <span class="fw-bold text-success">€<?= number_format($dados_servico['preco'], 2) ?></span>
                                        </div>
                                    </a>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <div class="col-12 text-center text-muted">Sem serviços disponíveis nesta categoria.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($passo_atual == 3): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="?step=2&cat=<?= $id_categoria ?>" class="btn-outline-back"><i class="bi bi-arrow-left"></i> Voltar</a>
                        <h4 class="fw-bold m-0">Escolha o Profissional</h4>
                        <div style="width: 80px;"></div>
                    </div>
                    <div class="row g-3">
                        <?php
                        $query_funcionarios = "SELECT funcionario.id, funcionario.nome 
                                     FROM funcionario
                                     JOIN servico_funcionario ON funcionario.id = servico_funcionario.id_funcionario
                                     WHERE servico_funcionario.id_servico = '$id_servico' AND servico_funcionario.ativo = 1";
                        $resultado_funcionarios = mysqli_query($conn, $query_funcionarios);

                        if (mysqli_num_rows($resultado_funcionarios) > 0):
                            while ($dados_funcionario = mysqli_fetch_assoc($resultado_funcionarios)): ?>
                                <div class="col-md-4 col-sm-6">
                                    <a href="?step=4&cat=<?= $id_categoria ?>&serv=<?= $id_servico ?>&func=<?= $dados_funcionario['id'] ?>" class="option-card">
                                        <div class="card-icon"><i class="bi bi-person-fill"></i></div>
                                        <div class="card-title"><?= htmlspecialchars($dados_funcionario['nome']) ?></div>
                                        <div class="card-meta text-primary">Ver Agenda</div>
                                    </a>
                                </div>
                            <?php endwhile;
                        else: ?>
                            <div class="col-12 text-center text-muted">Nenhum profissional disponível para este serviço.</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($passo_atual == 4): ?>

                    <?php
                    // =========================================================
                    // CÁLCULO DE DIAS DISPONÍVEIS (PINTAR O CALENDÁRIO FLAT-PICKR)
                    // =========================================================
                    // =========================================================
                    // CÁLCULO DE DIAS DISPONÍVEIS (OTIMIZADO - PINTAR O CALENDÁRIO)
                    // =========================================================
                    $lista_datas_disponiveis = [];
                    $data_hoje = new DateTime();
                    $data_limite = new DateTime('+4 months');
                    $total_dias = $data_hoje->diff($data_limite)->days;

                    $hoje_str = $data_hoje->format('Y-m-d');
                    $limite_str = $data_limite->format('Y-m-d');

                    $consulta_duracao = "SELECT num_slots FROM servico WHERE id = '$id_servico'";
                    $resultado_duracao = mysqli_query($conn, $consulta_duracao);
                    $duracao_necessaria = ($resultado_duracao) ? intval(mysqli_fetch_assoc($resultado_duracao)['num_slots']) : 1;

                    // 1. CARREGAR TODA A DISPONIBILIDADE (Apenas 1 Query)
                    $q_disp = "SELECT * FROM disponibilidade WHERE id_servico = '$id_servico' AND data_fim >= '$hoje_str'";
                    $res_disp = mysqli_query($conn, $q_disp);
                    $array_disp = [];
                    if ($res_disp) while ($row = mysqli_fetch_assoc($res_disp)) $array_disp[] = $row;

                    // 2. CARREGAR TODA A INDISPONIBILIDADE (Apenas 1 Query)
                    $q_indisp = "SELECT * FROM indisponibilidade WHERE id_funcionario = '$id_funcionario' AND data_fim >= '$hoje_str'";
                    $res_indisp = mysqli_query($conn, $q_indisp);
                    $array_indisp = [];
                    if ($res_indisp) while ($row = mysqli_fetch_assoc($res_indisp)) $array_indisp[] = $row;

                    // 3. CARREGAR TODAS AS MARCAÇÕES DO PERÍODO (Apenas 1 Query)
                    $q_marc = "SELECT m.data, m.slot_inicial, m.slot_final 
                               FROM marcacao m
                               JOIN servico_funcionario sf ON m.id_servico_funcionario = sf.id 
                               WHERE m.data BETWEEN '$hoje_str' AND '$limite_str'
                               AND m.estado != 'cancelada'
                               AND (sf.id_funcionario = '$id_funcionario' OR m.id_cliente = '$id_cliente')";
                    $res_marc = mysqli_query($conn, $q_marc);
                    $array_marc = [];
                    if ($res_marc) {
                        while ($row = mysqli_fetch_assoc($res_marc)) {
                            $array_marc[$row['data']][] = $row; // Agrupado por data para ser imediato a encontrar
                        }
                    }

                    $nomes_colunas_bd = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];

                    // Itera pelos próximos 120 dias usando a memória do PHP (Muito mais rápido!)
                    for ($contador_dias = 0; $contador_dias <= $total_dias; $contador_dias++) {
                        $data_temporaria = clone $data_hoje;
                        $data_temporaria->modify("+$contador_dias days");
                        $data_para_teste = $data_temporaria->format('Y-m-d');
                        $dia_semana_numero = $data_temporaria->format('w');
                        $coluna_dia_semana = $nomes_colunas_bd[$dia_semana_numero];

                        // Arrays que representam os slots do dia (0 a 100)
                        $mapa_slots = array_fill(0, 100, false);

                        // 4.1. Aplica Disponibilidade
                        foreach ($array_disp as $disp) {
                            if ($data_para_teste >= $disp['data_inicio'] && $data_para_teste <= $disp['data_fim'] && $disp[$coluna_dia_semana] == 1) {
                                for ($slot_index = intval($disp['slot_inicial']); $slot_index < intval($disp['slot_final']); $slot_index++) {
                                    if ($slot_index < 100) $mapa_slots[$slot_index] = true;
                                }
                            }
                        }

                        // 4.2. Aplica Indisponibilidade
                        foreach ($array_indisp as $indisp) {
                            if ($data_para_teste >= $indisp['data_inicio'] && $data_para_teste <= $indisp['data_fim'] && $indisp[$coluna_dia_semana] == 1) {
                                for ($slot_index = intval($indisp['slot_inicial']); $slot_index < intval($indisp['slot_final']); $slot_index++) {
                                    if (isset($mapa_slots[$slot_index])) $mapa_slots[$slot_index] = false;
                                }
                            }
                        }

                        // 4.3. Aplica Marcações Existentes
                        if (isset($array_marc[$data_para_teste])) {
                            foreach ($array_marc[$data_para_teste] as $marcacao) {
                                for ($slot_index = intval($marcacao['slot_inicial']); $slot_index < intval($marcacao['slot_final']); $slot_index++) {
                                    if (isset($mapa_slots[$slot_index])) $mapa_slots[$slot_index] = false;
                                }
                            }
                        }

                        // 4.4. Regra Especial para o próprio dia (Bloqueia slots do passado)
                        if ($contador_dias == 0) {
                            $hora_atual_real = intval(date('H'));
                            $minuto_atual_real = intval(date('i'));
                            
                            $minutos_passados = ($hora_atual_real * 60 + $minuto_atual_real) - (8 * 60);
                            
                            if ($minutos_passados > 0) {
                                $slots_para_bloquear = ceil($minutos_passados / 15);
                                for ($slot_index = 0; $slot_index <= ($slots_para_bloquear + 1); $slot_index++) {
                                    if (isset($mapa_slots[$slot_index])) $mapa_slots[$slot_index] = false;
                                }
                            }
                        }

                        // 4.5. Valida se ainda sobra tempo seguido suficiente para fazer o serviço
                        $tem_vaga = false;
                        for ($slot_index = 1; $slot_index <= (100 - $duracao_necessaria); $slot_index++) {
                            if (isset($mapa_slots[$slot_index]) && $mapa_slots[$slot_index] === true) {
                                $cabe_servico = true;
                                for ($d = 0; $d < $duracao_necessaria; $d++) {
                                    if (!isset($mapa_slots[$slot_index + $d]) || $mapa_slots[$slot_index + $d] === false) {
                                        $cabe_servico = false;
                                        break;
                                    }
                                }
                                if ($cabe_servico) {
                                    $tem_vaga = true;
                                    break;
                                }
                            }
                        }

                        // Se encontrou vaga neste dia, adiciona ao array do javascript do calendário
                        if ($tem_vaga) {
                            $lista_datas_disponiveis[] = $data_para_teste;
                        }
                    }
                    $json_datas_disponiveis = json_encode($lista_datas_disponiveis);
                    ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <a href="?step=3&cat=<?= $id_categoria ?>&serv=<?= $id_servico ?>" class="btn-outline-back"><i class="bi bi-arrow-left"></i> Voltar</a>
                        <h4 class="fw-bold m-0">Finalizar Marcação</h4>
                        <div style="width: 80px;"></div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 16px;">
                                <h6 class="text-center text-muted fw-bold mb-3">SELECIONE A DATA</h6>

                                <form method="GET" id="formCalendario">
                                    <input type="hidden" name="step" value="4">
                                    <input type="hidden" name="cat" value="<?= $id_categoria ?>">
                                    <input type="hidden" name="serv" value="<?= $id_servico ?>">
                                    <input type="hidden" name="func" value="<?= $id_funcionario ?>">

                                    <div class="calendar-wrapper">
                                        <input type="text" id="input_calendario" name="data" placeholder="Selecione..." value="<?= $data_selecionada ?>">
                                    </div>
                                </form>

                                <div class="d-flex justify-content-center gap-3 mt-3 text-muted small">
                                    <div class="d-flex align-items-center"><span style="width: 10px; height: 10px; background: var(--primary); border-radius: 50%; display: inline-block; margin-right: 6px;"></span> Selecionado</div>
                                    <div class="d-flex align-items-center"><span style="width: 10px; height: 10px; border: 2px solid var(--primary); border-radius: 50%; display: inline-block; margin-right: 6px;"></span> Hoje</div>
                                    <div class="d-flex align-items-center"><span style="width: 10px; height: 10px; background: #fff; border: 1px solid #ccc; border-radius: 50%; display: inline-block; margin-right: 6px;"></span> Disponível</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm p-4 h-100" style="border-radius: 16px; background: #fdfdfd;">
                                <?php if (empty($data_selecionada)): ?>
                                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted opacity-50">
                                        <i class="bi bi-calendar-event display-4 mb-3"></i>
                                        <p>Selecione uma data para ver os horários.</p>
                                    </div>
                                <?php else: ?>
                                    <h6 class="text-center text-secondary fw-bold mb-4">HORÁRIOS PARA <?= date('d/m/Y', strtotime($data_selecionada)) ?></h6>

                                    <form method="POST" action="booking_new.php">
                                        <input type="hidden" name="p_cat" value="<?= $id_categoria ?>">
                                        <input type="hidden" name="p_serv" value="<?= $id_servico ?>">
                                        <input type="hidden" name="p_func" value="<?= $id_funcionario ?>">
                                        <input type="hidden" name="p_data" value="<?= $data_selecionada ?>">

                                        <div class="slots-container">
                                            <?php
                                            // Mesma lógica de validação de slots aplicada aqui (Apenas para o dia selecionado)
                                            $dia_semana_numero = date('w', strtotime($data_selecionada));
                                            $nomes_colunas_bd = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];
                                            $coluna_dia_semana = $nomes_colunas_bd[$dia_semana_numero];

                                            $mapa_dia_atual = array_fill(0, 100, false);

                                            $consulta_disponibilidade = "SELECT slot_inicial, slot_final 
                                                                         FROM disponibilidade 
                                                                         WHERE id_servico = '$id_servico' 
                                                                         AND '$data_selecionada' BETWEEN data_inicio AND data_fim 
                                                                         AND $coluna_dia_semana = 1";
                                            $resultado_disponibilidade = mysqli_query($conn, $consulta_disponibilidade);

                                            if ($resultado_disponibilidade) {
                                                while ($turno = mysqli_fetch_assoc($resultado_disponibilidade)) {
                                                    for ($i = intval($turno['slot_inicial']); $i < intval($turno['slot_final']); $i++) {
                                                        if ($i < 100) $mapa_dia_atual[$i] = true;
                                                    }
                                                }
                                            }

                                            $consulta_indisponibilidade = "SELECT slot_inicial, slot_final 
                                                                           FROM indisponibilidade 
                                                                           WHERE id_funcionario = '$id_funcionario' 
                                                                           AND '$data_selecionada' BETWEEN data_inicio AND data_fim 
                                                                           AND $coluna_dia_semana = 1";
                                            $resultado_indisponibilidade = mysqli_query($conn, $consulta_indisponibilidade);

                                            if ($resultado_indisponibilidade) {
                                                while ($ausencia = mysqli_fetch_assoc($resultado_indisponibilidade)) {
                                                    for ($i = intval($ausencia['slot_inicial']); $i < intval($ausencia['slot_final']); $i++) {
                                                        if (isset($mapa_dia_atual[$i])) $mapa_dia_atual[$i] = false;
                                                    }
                                                }
                                            }

                                            // Bloqueia as horas já ocupadas (ou pelo funcionário ou pelo cliente atual)
                                            $consulta_marcacoes = "SELECT marcacao.slot_inicial, marcacao.slot_final 
                                                                   FROM marcacao 
                                                                   JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id 
                                                                   WHERE marcacao.data = '$data_selecionada' 
                                                                   AND marcacao.estado != 'cancelada'
                                                                   AND (servico_funcionario.id_funcionario = '$id_funcionario' OR marcacao.id_cliente = '$id_cliente')";
                                            
                                            $resultado_marcacoes = mysqli_query($conn, $consulta_marcacoes);

                                            if ($resultado_marcacoes) {
                                                while ($marcacao = mysqli_fetch_assoc($resultado_marcacoes)) {
                                                    for ($i = intval($marcacao['slot_inicial']); $i < intval($marcacao['slot_final']); $i++) {
                                                        if (isset($mapa_dia_atual[$i])) $mapa_dia_atual[$i] = false;
                                                    }
                                                }
                                            }

                                            // Impede marcações no passado se o dia selecionado for "Hoje"
                                            if ($data_selecionada == date('Y-m-d')) {
                                                $hora_atual_real = intval(date('H'));
                                                $minuto_atual_real = intval(date('i'));
                                                $minutos_passados = ($hora_atual_real * 60 + $minuto_atual_real) - (8 * 60);
                                                
                                                if ($minutos_passados > 0) {
                                                    $slots_para_bloquear = ceil($minutos_passados / 15);
                                                    for ($i = 0; $i <= ($slots_para_bloquear + 1); $i++) {
                                                        if (isset($mapa_dia_atual[$i])) $mapa_dia_atual[$i] = false;
                                                    }
                                                }
                                            }

                                            // Desenhar os botões com as horas formatadas
                                            $slots_encontrados_contador = 0;

                                            for ($slot_index = 1; $slot_index <= (100 - $duracao_necessaria); $slot_index++) {
                                                if (isset($mapa_dia_atual[$slot_index]) && $mapa_dia_atual[$slot_index] === true) {
                                                    
                                                    $cabe_servico_inteiro = true;
                                                    for ($d = 0; $d < $duracao_necessaria; $d++) {
                                                        if (!isset($mapa_dia_atual[$slot_index + $d]) || $mapa_dia_atual[$slot_index + $d] === false) {
                                                            $cabe_servico_inteiro = false;
                                                            break;
                                                        }
                                                    }

                                                    if ($cabe_servico_inteiro) {
                                                        $hora_texto = converterSlotParaHoraLocal($slot_index);
                                            ?>
                                                        <input type="radio" class="slot-input" name="slot_selecionado" id="slot_<?= $slot_index ?>" value="<?= $slot_index ?>" required>
                                                        <label class="slot-label" for="slot_<?= $slot_index ?>"><?= $hora_texto ?></label>
                                            <?php
                                                        $slots_encontrados_contador++;
                                                    }
                                                }
                                            }

                                            // Caso não haja opções mostra esta mensagem
                                            if ($slots_encontrados_contador == 0) {
                                                echo '<div class="col-12 text-center text-muted py-4"><i class="bi bi-emoji-frown mb-2" style="font-size: 1.5rem;"></i><br>Não existem horários disponíveis para esta data.</div>';
                                            }
                                            ?>
                                        </div>

                                        <?php if ($slots_encontrados_contador > 0): ?>
                                            <div class="mt-4">
                                                <button type="submit" name="finalizar_reserva" class="btn-primary-custom shadow">
                                                    Confirmar Marcação <i class="bi bi-check-circle ms-2"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>

                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/pt.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const elementoCalendario = document.getElementById('input_calendario');

            if (elementoCalendario) {
                // Obtém as datas disponíveis enviadas pelo PHP
                const arrayDatasDisponiveis = <?php echo isset($json_datas_disponiveis) ? $json_datas_disponiveis : '[]'; ?>;

                // Configura e Inicializa a biblioteca Flatpickr
                flatpickr(elementoCalendario, {
                    inline: true,                   // Mostra o calendário sempre aberto
                    locale: "pt",                   // Tradução para português
                    minDate: "today",               // Bloqueia dias do passado
                    maxDate: new Date().fp_incr(120), // Permite agendar num limite de +120 dias
                    dateFormat: "Y-m-d",            // Formato de envio ISO
                    defaultDate: "<?= $data_selecionada ?>", // Data em que o clique ocorreu
                    enable: arrayDatasDisponiveis,  // Passa o array das datas calculadas pelo PHP para ativar os botões

                    // Acontece quando o cliente clica num dos dias ativados
                    onChange: function(selectedDates, dateStr, instance) {
                        const cartaoCalendario = document.querySelector('.calendar-wrapper').closest('.card');
                        const cartaoHorarios = document.querySelector('.slots-container')?.closest('.card');

                        // Efeito de opacidade para indicar que a página está a carregar
                        if (cartaoCalendario) {
                            cartaoCalendario.style.opacity = '0.6';
                            cartaoCalendario.style.pointerEvents = 'none';
                        }
                        if (cartaoHorarios) {
                            cartaoHorarios.style.opacity = '0.6';
                        }
                        
                        // Submete de forma invisível o formulário GET para recarregar a página com as horas do dia clicado
                        document.getElementById('formCalendario').submit();
                    }
                });
            }
        });
    </script>
</body>
</html>