<?php
require_once __DIR__ . '/../../src/conexao.php';

$data = isset($_GET['data']) ? mysqli_real_escape_string($conn, $_GET['data']) : '';
$id_relacao = isset($_GET['id_relacao']) ? intval($_GET['id_relacao']) : 0;
// Recebe o ID do Cliente para verificar a agenda dele
$id_cliente = isset($_GET['id_cliente']) ? intval($_GET['id_cliente']) : 0; 

if (!$data || !$id_relacao) {
    echo json_encode([]);
    exit;
}

$q_serv = "SELECT servico.num_slots, servico_funcionario.id_funcionario, servico_funcionario.id_servico 
           FROM servico_funcionario 
           JOIN servico ON servico_funcionario.id_servico = servico.id 
           WHERE servico_funcionario.id = $id_relacao";
$r_serv = mysqli_query($conn, $q_serv);
$d_serv = mysqli_fetch_assoc($r_serv);

if (!$d_serv) {
    echo json_encode([]);
    exit;
}

$duracao = intval($d_serv['num_slots']);
$id_funcionario = $d_serv['id_funcionario'];
$id_servico = $d_serv['id_servico'];

$mapa_dias = [0 => 'domingo', 1 => 'segunda', 2 => 'terca', 3 => 'quarta', 4 => 'quinta', 5 => 'sexta', 6 => 'sabado'];
$dia_semana_num = date('w', strtotime($data));
$coluna_dia = $mapa_dias[$dia_semana_num];

$q_disp = "SELECT slot_inicial, slot_final FROM disponibilidade WHERE id_servico = $id_servico AND '$data' BETWEEN data_inicio AND data_fim AND $coluna_dia = 1 ORDER BY slot_inicial ASC";
$r_disp = mysqli_query($conn, $q_disp);

$mapa_dia_atual = array_fill(0, 100, false);

while ($turno = mysqli_fetch_assoc($r_disp)) {
    for ($i = intval($turno['slot_inicial']); $i < intval($turno['slot_final']); $i++) {
        if ($i < 100) $mapa_dia_atual[$i] = true;
    }
}

$q_indis = "SELECT slot_inicial, slot_final FROM indisponibilidade WHERE id_funcionario = $id_funcionario AND '$data' BETWEEN data_inicio AND data_fim AND $coluna_dia = 1";
$r_indis = mysqli_query($conn, $q_indis);
while ($ausencia = mysqli_fetch_assoc($r_indis)) {
    for ($i = intval($ausencia['slot_inicial']); $i < intval($ausencia['slot_final']); $i++) {
        if (isset($mapa_dia_atual[$i])) $mapa_dia_atual[$i] = false;
    }
}

// BLOQUEIA SE O PROFISSIONAL *OU* O CLIENTE JÁ TIVEREM MARCAÇÃO (Sem abreviações)
$q_marc = "SELECT marcacao.slot_inicial, marcacao.slot_final 
           FROM marcacao 
           JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
           WHERE marcacao.data = '$data' 
           AND marcacao.estado != 'cancelada'
           AND (servico_funcionario.id_funcionario = $id_funcionario OR marcacao.id_cliente = $id_cliente)";

$r_marc = mysqli_query($conn, $q_marc);
while ($marcacao = mysqli_fetch_assoc($r_marc)) {
    for ($i = intval($marcacao['slot_inicial']); $i < intval($marcacao['slot_final']); $i++) {
        if (isset($mapa_dia_atual[$i])) $mapa_dia_atual[$i] = false;
    }
}

// Regra Hoje
if ($data == date('Y-m-d')) {
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

$slots_disponiveis = [];
for ($i = 1; $i <= (100 - $duracao); $i++) {
    if (isset($mapa_dia_atual[$i]) && $mapa_dia_atual[$i] === true) {
        $cabe = true;
        for ($d = 0; $d < $duracao; $d++) {
            if (!isset($mapa_dia_atual[$i + $d]) || $mapa_dia_atual[$i + $d] === false) {
                $cabe = false;
                break;
            }
        }
        if ($cabe) {
            $hora_inicio = 8; 
            $minutos_totais = ($i - 1) * 15;
            $horas = $hora_inicio + floor($minutos_totais / 60);
            $minutos = $minutos_totais % 60;
            $hora_fmt = sprintf("%02d:%02d", $horas, $minutos);

            $slots_disponiveis[] = ['id' => $i, 'hora' => $hora_fmt];
        }
    }
}

echo json_encode($slots_disponiveis);
?>