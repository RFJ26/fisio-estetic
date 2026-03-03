<?php
// Define a função que recebe um número de slot (ex: 1) e devolve a hora formatada (ex: "08:00")
function converterSlotParaHora($slot) {
    
    // Define a hora de abertura do estabelecimento (neste caso, 8 da manhã)
    $hora_inicio = 8; 
    
    // Define a duração de cada atendimento/slot em minutos (15 minutos)
    $minutos_por_slot = 15;
    
    // Calcula quantos minutos totais passaram desde a hora de abertura até este slot.
    // Subtrai-se 1 ($slot - 1) porque o Slot 1 acontece no minuto 0 (08:00).
    // Exemplo: Slot 3 -> (3-1)*15 = 30 minutos passados.
    $minutos_totais = ($slot - 1) * $minutos_por_slot;
    
    // Calcula a hora atual somando a hora de início às horas inteiras que passaram.
    // A função floor() arredonda a divisão para baixo para pegar apenas a hora cheia.
    // Exemplo: Se passaram 75 min -> 75/60 = 1.25 -> floor dá 1 hora. 8 + 1 = 9 horas.
    $horas = $hora_inicio + floor($minutos_totais / 60);
    
    // Calcula os minutos restantes que não completaram uma hora cheia.
    // O operador % (módulo) dá o resto da divisão.
    // Exemplo: 75 min % 60 = sobram 15 minutos.
    $minutos = $minutos_totais % 60;
    
    // Retorna a string formatada como "HH:MM" (Horas:Minutos).
    // %02d força o número a ter sempre 2 dígitos (ex: 8 vira "08", 0 vira "00").
    return sprintf("%02d:%02d", $horas, $minutos);
}

// Define a função inversa (opcional): recebe uma hora (ex: "08:15") e descobre qual é o número do slot
function converterHoraParaSlot($hora_string) {
    
    // Define a mesma hora de abertura usada na outra função para manter a consistência
    $hora_inicio = 8;
    
    // Define a mesma duração de slot
    $minutos_por_slot = 15;
    
    // Divide a string da hora (ex: "09:30") pelo símbolo ":", criando um array.
    // $partes[0] será "09" e $partes[1] será "30".
    $partes = explode(':', $hora_string);
    
    // Converte a primeira parte (horas) de texto para um número inteiro
    $horas = intval($partes[0]);
    
    // Converte a segunda parte (minutos) de texto para um número inteiro
    $minutos = intval($partes[1]);
    
    // Calcula quantos minutos totais passaram desde a abertura (8h) até à hora fornecida.
    // (Horas da marcação - 8h) * 60 minutos + os minutos da marcação.
    $total_minutos_desde_inicio = (($horas - $hora_inicio) * 60) + $minutos;
    
    // Divide o total de minutos pela duração do slot (15) para saber quantos slots cabem.
    // Soma-se +1 porque a contagem começa no 1 (Slot 1 é o minuto 0).
    $slot = ($total_minutos_desde_inicio / $minutos_por_slot) + 1;
    
    // Devolve o número do slot correspondente
    return $slot;
}
// Converte quantidade de slots em Duração (ex: 3 slots -> "45 Minutos")
function converterSlotsParaDuracao($num_slots) {
    $minutos_por_slot = 15; 
    $total_minutos = $num_slots * $minutos_por_slot;

    // Se for menos de 1 hora, mostra só minutos
    if ($total_minutos < 60) {
        return $total_minutos . " Minutos";
    }

    // Se for mais de 1 hora, calcula horas e minutos
    $horas = floor($total_minutos / 60);
    $minutos_restantes = $total_minutos % 60;

    if ($minutos_restantes == 0) {
        return $horas . " Hora(s)";
    }

    return sprintf("%dh %02dm", $horas, $minutos_restantes);
}
// Func. para formatar a data
function formatarData($data) {
    // Se a data for empty retorna 'N/A'
    if (!$data) return 'N/A';
    //Muda o formato da data
    return date('d/m/Y', strtotime($data));
}
?>  