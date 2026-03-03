<?php
session_start();
include('../verifica_login.php');
require '../../src/conexao.php';

$erro = '';
$sucesso = '';

// --- 1. CONFIGURAÇÃO DE HORÁRIOS (56 SLOTS) ---
$hora_abertura = '08:00'; 
$intervalo_minutos = 15; 
$total_slots_dia = 56;    

$lista_horarios = [];
$time_atual = strtotime($hora_abertura);

for ($i = 1; $i <= $total_slots_dia; $i++) {
    $lista_horarios[$i] = date('H:i', $time_atual);
    $time_atual = strtotime("+$intervalo_minutos minutes", $time_atual);
}

// Buscar categorias
$query_cat = "SELECT * FROM categoria ORDER BY categoria.designacao ASC";
$result_cat = mysqli_query($conn, $query_cat);

// --- 2. PROCESSAMENTO DO FORMULÁRIO ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Recolha e Limpeza de Dados
    $designacao   = trim($_POST['designacao']);
    
    // Converter preço para float seguro e tratar vírgula
    $precoStr     = str_replace(',', '.', $_POST['preco']); 
    $preco        = floatval($precoStr);

    $num_slots    = intval($_POST['num_slots']);
    $id_categoria = intval($_POST['id_categoria']);
    
    // 2. Upload de Imagem
    $caminho_img = null;
    if(isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0){
        $diretorio = "../../public/uploads/";
        if(!is_dir($diretorio)) mkdir($diretorio, 0777, true);
        
        $extensao = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
        $novo_nome = uniqid() . "." . $extensao;
        $destino = $diretorio . $novo_nome;
        
        if(move_uploaded_file($_FILES['imagem']['tmp_name'], $destino)){
            $caminho_img = "uploads/" . $novo_nome; 
        }
    }

    // 3. Validações
    if (empty($designacao) || empty($_POST['preco']) || empty($num_slots) || empty($id_categoria)) {
        $erro = "Preencha os campos obrigatórios do serviço.";
    } 
    // --- NOVA VALIDAÇÃO DE PREÇO ---
    elseif ($preco <= 0) {
        $erro = "O preço deve ser maior que 0€ (não são permitidos valores negativos ou gratuitos).";
    }
    else {
        
        // 4. VERIFICAR DUPLICADOS (Nome do Serviço)
        $checkQuery = "SELECT id FROM servico WHERE designacao = ?";
        $stmtCheck = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmtCheck, "s", $designacao);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);

        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            $erro = "Já existe um serviço registado com o nome '$designacao'.";
        } else {

            // 5. Inserir Serviço
            $query_insert = "INSERT INTO servico (id_categoria, designacao, preco, num_slots, caminho_img) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query_insert);
            mysqli_stmt_bind_param($stmt, "isdis", $id_categoria, $designacao, $preco, $num_slots, $caminho_img);
            
            if (mysqli_stmt_execute($stmt)) {
                $id_servico_novo = mysqli_insert_id($conn);
                
                // Dados da Disponibilidade
                $data_inicio = $_POST['data_inicio'];
                $data_fim    = $_POST['data_fim'];
                
                $domingo = isset($_POST['domingo']) ? 1 : 0;
                $segunda = isset($_POST['segunda']) ? 1 : 0;
                $terca   = isset($_POST['terca'])   ? 1 : 0;
                $quarta  = isset($_POST['quarta'])  ? 1 : 0;
                $quinta  = isset($_POST['quinta'])  ? 1 : 0;
                $sexta   = isset($_POST['sexta'])   ? 1 : 0;
                $sabado  = isset($_POST['sabado'])  ? 1 : 0;

                // --- Gravar Manhã ---
                if (isset($_POST['ativar_manha'])) {
                    $m_ini = $_POST['manha_inicio'];
                    $m_fim = $_POST['manha_fim'];
                    
                    if(!empty($m_ini) && !empty($m_fim) && $m_ini < $m_fim){
                        $sql_m = "INSERT INTO disponibilidade (data_inicio, data_fim, domingo, segunda, terca, quarta, quinta, sexta, sabado, id_servico, slot_inicial, slot_final) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmtM = mysqli_prepare($conn, $sql_m);
                        mysqli_stmt_bind_param($stmtM, "ssiiiiiiiiii", 
                            $data_inicio, $data_fim, 
                            $domingo, $segunda, $terca, $quarta, $quinta, $sexta, $sabado, 
                            $id_servico_novo, $m_ini, $m_fim
                        );
                        mysqli_stmt_execute($stmtM);
                    }
                }

                // --- Gravar Tarde ---
                if (isset($_POST['ativar_tarde'])) {
                    $t_ini = $_POST['tarde_inicio'];
                    $t_fim = $_POST['tarde_fim'];
                    
                    if(!empty($t_ini) && !empty($t_fim) && $t_ini < $t_fim){
                        $sql_t = "INSERT INTO disponibilidade (data_inicio, data_fim, domingo, segunda, terca, quarta, quinta, sexta, sabado, id_servico, slot_inicial, slot_final) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmtT = mysqli_prepare($conn, $sql_t);
                        mysqli_stmt_bind_param($stmtT, "ssiiiiiiiiii", 
                            $data_inicio, $data_fim, 
                            $domingo, $segunda, $terca, $quarta, $quinta, $sexta, $sabado, 
                            $id_servico_novo, $t_ini, $t_fim
                        );
                        mysqli_stmt_execute($stmtT);
                    }
                }

                echo "<script>alert('Serviço criado com sucesso!'); window.location.href = 'list.php';</script>";
                exit;

            } else {
                $erro = "Erro ao inserir serviço: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Serviço - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/service/create.css">
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
            
            <header class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">Novo Serviço</h2>
                    <p class="text-muted mb-0">Registar tratamento e definir horários.</p>
                </div>
                <div>
                    <a href="list.php" class="btn-cancel">
                        <i class="bi bi-arrow-left me-1"></i> Voltar
                    </a>
                </div>
            </header>

            <?php if ($erro): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?= $erro ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                
                <div class="section-card">
                    <div class="section-title">
                        <span><i class="bi bi-info-circle me-2"></i>Detalhes do Tratamento</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome do Serviço</label>
                            <input type="text" name="designacao" class="form-control" required placeholder="Ex: Massagem Relaxante" value="<?= isset($_POST['designacao']) ? htmlspecialchars($_POST['designacao']) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <select name="id_categoria" class="form-select" required>
                                <option value="" selected disabled>Selecionar...</option>
                                <?php 
                                mysqli_data_seek($result_cat, 0);
                                while($cat = mysqli_fetch_assoc($result_cat)): 
                                    $selected = (isset($_POST['id_categoria']) && $_POST['id_categoria'] == $cat['id']) ? 'selected' : '';
                                ?>
                                    <option value="<?= $cat['id'] ?>" <?= $selected ?>><?= htmlspecialchars($cat['designacao']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Preço</label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="number" name="preco" step="0.01" min="0.01" class="form-control" required placeholder="0.00" value="<?= isset($_POST['preco']) ? htmlspecialchars($_POST['preco']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duração</label>
                            <select name="num_slots" class="form-select" required>
                                <?php for($k=1; $k<=12; $k++): 
                                    $selected = (isset($_POST['num_slots']) && $_POST['num_slots'] == $k) ? 'selected' : '';
                                ?>
                                    <option value="<?= $k ?>" <?= $selected ?>><?= $k ?> slots (<?= ($k*15) ?> min)</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Imagem (Opcional)</label>
                            <input type="file" name="imagem" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">
                        <span><i class="bi bi-calendar-range me-2"></i>Configuração de Horário Inicial</span>
                        <small class="text-muted fw-normal" style="font-size: 0.85rem;">Horário Geral (08:00 - 22:00)</small>
                    </div>

                    <div class="row mb-4 g-3">
                        <div class="col-md-6">
                            <label class="form-label">Intervalo de Datas</label>
                            <div class="input-group">
                                <input type="date" name="data_inicio" class="form-control" value="<?= isset($_POST['data_inicio']) ? $_POST['data_inicio'] : date('Y-m-d') ?>">
                                <span class="input-group-text bg-white">até</span>
                                <input type="date" name="data_fim" class="form-control" value="<?= isset($_POST['data_fim']) ? $_POST['data_fim'] : date('Y-m-d', strtotime('+1 year')) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dias da Semana</label>
                            <div class="day-selector">
                                <div class="day-check"><input type="checkbox" name="segunda" id="d_seg" checked><label for="d_seg" title="Segunda">S</label></div>
                                <div class="day-check"><input type="checkbox" name="terca" id="d_ter" checked><label for="d_ter" title="Terça">T</label></div>
                                <div class="day-check"><input type="checkbox" name="quarta" id="d_qua" checked><label for="d_qua" title="Quarta">Q</label></div>
                                <div class="day-check"><input type="checkbox" name="quinta" id="d_qui" checked><label for="d_qui" title="Quinta">Q</label></div>
                                <div class="day-check"><input type="checkbox" name="sexta" id="d_sex" checked><label for="d_sex" title="Sexta">S</label></div>
                                <div class="day-check"><input type="checkbox" name="sabado" id="d_sab"><label for="d_sab" title="Sábado">S</label></div>
                                <div class="day-check"><input type="checkbox" name="domingo" id="d_dom"><label for="d_dom" title="Domingo">D</label></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="ativar_manha" id="switchManha" onchange="toggleInputs('manha')">
                                    <label class="form-check-label fw-bold" for="switchManha">Ativar Manhã</label>
                                </div>
                                <div id="area-manha" style="opacity: 0.5; pointer-events: none; transition: opacity 0.3s;">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="small text-muted mb-1">Início</label>
                                            <select name="manha_inicio" class="form-select form-select-sm time-select">
                                                <option value="">--</option>
                                                <?php foreach($lista_horarios as $id => $hora): ?>
                                                    <option value="<?= $id ?>"><?= $hora ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted mb-1">Fim</label>
                                            <select name="manha_fim" class="form-select form-select-sm time-select">
                                                <option value="">--</option>
                                                <?php foreach($lista_horarios as $id => $hora): ?>
                                                    <option value="<?= $id ?>"><?= $hora ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="ativar_tarde" id="switchTarde" onchange="toggleInputs('tarde')">
                                    <label class="form-check-label fw-bold" for="switchTarde">Ativar Tarde</label>
                                </div>
                                <div id="area-tarde" style="opacity: 0.5; pointer-events: none; transition: opacity 0.3s;">
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="small text-muted mb-1">Início</label>
                                            <select name="tarde_inicio" class="form-select form-select-sm time-select">
                                                <option value="">--</option>
                                                <?php foreach($lista_horarios as $id => $hora): ?>
                                                    <option value="<?= $id ?>"><?= $hora ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="small text-muted mb-1">Fim</label>
                                            <select name="tarde_fim" class="form-select form-select-sm time-select">
                                                <option value="">--</option>
                                                <?php foreach($lista_horarios as $id => $hora): ?>
                                                    <option value="<?= $id ?>"><?= $hora ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mb-5">
                    <a href="list.php" class="btn-cancel">Cancelar</a>
                    <button type="submit" class="btn-save">
                        <i class="bi bi-check-lg"></i> Gravar Serviço
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
            // Converter primeira letra para maiúscula para coincidir com o ID
            const periodoCap = periodo.charAt(0).toUpperCase() + periodo.slice(1);
            const switchEl = document.getElementById('switch' + periodoCap);
            const area = document.getElementById('area-' + periodo);
            
            if(switchEl && switchEl.checked) {
                area.style.opacity = '1';
                area.style.pointerEvents = 'auto';
            } else {
                area.style.opacity = '0.5';
                area.style.pointerEvents = 'none';
                
                // Limpar selects quando desativar
                const selects = area.getElementsByTagName('select');
                for(let s of selects) s.value = "";
            }
        }
        
        // Executar ao carregar para garantir estado correto se houve postback
        document.addEventListener('DOMContentLoaded', function() {
            if(document.getElementById('switchManha')) toggleInputs('manha');
            if(document.getElementById('switchTarde')) toggleInputs('tarde');
        });
    </script>
</body>
</html>