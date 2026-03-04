<?php
require_once __DIR__ . '/../../src/conexao.php';
header('Content-Type: application/json');

if (!isset($_GET['id_relacao'])) {
    echo json_encode([]);
    exit;
}

$id_relacao = intval($_GET['id_relacao']);

// 1. Descobrir qual é o serviço desta relação 
$q_serv = "SELECT id_servico FROM servico_funcionario WHERE id = $id_relacao";
$r_serv = mysqli_query($conn, $q_serv);
$row_serv = mysqli_fetch_assoc($r_serv);

if (!$row_serv) { echo json_encode([]); exit; }
$id_servico = $row_serv['id_servico'];

// 2. Verificar dias da semana na tabela 'disponibilidade' 
$hoje = date('Y-m-d');
$dias_disponiveis = [];

$query = "SELECT * FROM disponibilidade WHERE id_servico = $id_servico AND data_fim >= '$hoje'";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_assoc($result)) {
    if ($row['domingo'] == 1) $dias_disponiveis[] = 0;
    if ($row['segunda'] == 1) $dias_disponiveis[] = 1;
    if ($row['terca']   == 1) $dias_disponiveis[] = 2;
    if ($row['quarta']  == 1) $dias_disponiveis[] = 3;
    if ($row['quinta']  == 1) $dias_disponiveis[] = 4;
    if ($row['sexta']   == 1) $dias_disponiveis[] = 5;
    if ($row['sabado']  == 1) $dias_disponiveis[] = 6;
}

echo json_encode(array_values(array_unique($dias_disponiveis)));
?>