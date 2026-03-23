<?php
session_start();

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/send_email.php'; // Adicionado para enviar o e-mail

$erros = [];

if (isset($_POST['create_worker'])) {
    
    $nome     = trim($_POST['nome']);
    $email    = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $nif      = trim($_POST['nif']);
    $adm      = $_POST['adm']; // 0 ou 1

    // =========================================================================
    // VALIDAÇÕES ORIGINAIS (MANTIDAS EXATAMENTE COMO PEDISTE)
    // =========================================================================

    // 1. Nome: Apenas letras e espaços
    if (!preg_match("/^[a-zA-ZÀ-ÿ\s]+$/", $nome)) {
        $erros['nome'] = "Nome incorreto. O nome deve conter apenas letras e espaços.";
    }

    // 2. Email: Validação padrão PHP
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = "Email inválido.";
    }

    // 3. Telefone: O Regex específico
    if (!preg_match("/^(\+351)?(2\d{8}|9[1236]\d{7})$/", $telefone)) {
        $erros['telefone'] = "Telefone inválido. Deve começar por 2, 91, 92, 93 ou 96 (opcionalmente com +351).";
    }

    // 4. NIF: Exatamente 9 dígitos
    if (!preg_match("/^[0-9]{9}$/", $nif)) {
        $erros['nif'] = "NIF inválido (deve ter 9 dígitos).";
    }

    // =========================================================================
    // INSERÇÃO NA BASE DE DADOS COM ENVIO DE E-MAIL
    // =========================================================================
    if (empty($erros)) {
        
        // Verifica duplicados (Email ou NIF) na tabela Funcionario
        $checkQuery = "SELECT id FROM funcionario WHERE email = ? OR nif = ?";
        $stmtCheck = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmtCheck, "ss", $email, $nif);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);

        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            $erros['bd'] = "Já existe um funcionário registado com este Email ou NIF.";
        } else {
            // Verifica duplicados na tabela Cliente
            $checkClient = "SELECT id FROM cliente WHERE email = ? OR nif = ?";
            $stmtClient = mysqli_prepare($conn, $checkClient);
            mysqli_stmt_bind_param($stmtClient, "ss", $email, $nif);
            mysqli_stmt_execute($stmtClient);
            mysqli_stmt_store_result($stmtClient);

            if (mysqli_stmt_num_rows($stmtClient) > 0) {
                $erros['bd'] = "Este Email ou NIF já está associado a um Cliente.";
            } else {
                
                // GERA O TOKEN E PREPARA DADOS
                $token = bin2hex(random_bytes(50));
                $passVazia = ""; 
                $emailVerificado = 0;

                // Sucesso: Inserir com o token
                $query = "INSERT INTO funcionario (nome, email, telefone, nif, palavra_passe, adm, email_verificado, token_verificacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt, "sssssiis", $nome, $email, $telefone, $nif, $passVazia, $adm, $emailVerificado, $token);

                if (mysqli_stmt_execute($stmt)) {
                    
                    // ENVIAR O E-MAIL
                    $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                    $dominio = $_SERVER['HTTP_HOST'];
                    $linkValidacao = $protocolo . "://" . $dominio . "/ativar_funcionario.php?token=" . $token;

                    enviarEmailValidacaoFuncionario($email, $nome, $linkValidacao, $adm);

                    echo "<script>alert('Funcionário criado com sucesso! Foi enviado um email para ativar a conta.'); window.location.href = 'list.php';</script>";
                    exit();
                } else {
                    $erros['bd'] = "Erro ao criar funcionário na base de dados.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo Funcionário - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/create.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none">
        <i class="bi bi-list"></i>
    </button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area">
            <h2>Fisioestetic</h2>
        </div>

        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <?php if(isset($_COOKIE['role']) && ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === '1')): ?>
                <li class="nav-item mt-3"><a class="nav-link" href="/select_role.php" style="color: #ef6c00;"><i class="bi bi-arrow-left-right me-3"></i>Mudar Perfil</a></li>
            <?php endif; ?>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 text-muted mb-1">
                        <a href="list.php" class="text-decoration-none small text-secondary">Funcionários</a>
                        <span>/</span>
                        <span class="small">Novo Registo</span>
                    </div>
                    <h1>Novo Funcionário</h1>
                </div>
            </header>

            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                    <div>
                        <strong>Atenção:</strong>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($erros as $erro) echo "<li>$erro</li>"; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="create-card">
                <form method="POST" autocomplete="off">
                    
                    <div class="row g-4">
                        <div class="col-12">
                            <label for="nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   placeholder="Ex: Ana Silva"
                                   pattern="^[a-zA-ZÀ-ÿ\s]+$"
                                   title="O nome deve conter apenas letras e espaços." 
                                   value="<?= isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : '' ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Profissional</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="email@fisioestetic.pt" autocomplete="off"
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" 
                                   placeholder="912345678"
                                   maxlength="13"
                                   oninput="this.value = this.value.replace(/[^0-9+]/g, '')"
                                   pattern="(\+351)?(2\d{8}|9[1236]\d{7})"
                                   title="Formato inválido. Aceita fixos (2...) ou móveis (91, 92, 93, 96), opcionalmente com +351." 
                                   value="<?= isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : '' ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="nif" class="form-label">NIF</label>
                            <input type="text" class="form-control" id="nif" name="nif" 
                                   placeholder="123456789"
                                   maxlength="9"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   pattern="[0-9]{9}"
                                   title="O NIF deve ter 9 dígitos" 
                                   value="<?= isset($_POST['nif']) ? htmlspecialchars($_POST['nif']) : '' ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="adm" class="form-label">Permissões de Acesso</label>
                            <select class="form-select" id="adm" name="adm">
                                <option value="0" selected>Funcionário (Acesso Limitado)</option>
                                <option value="1">Administrador (Acesso Total)</option>
                            </select>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-info-circle me-1"></i> O funcionário receberá um e-mail para definir a sua palavra-passe.
                            </small>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <a href="list.php" class="btn-cancel">Cancelar</a>
                        <button type="submit" name="create_worker" class="btn-save">
                            <i class="bi bi-check-lg me-2"></i>Criar Funcionário
                        </button>
                    </div>

                </form>
            </div>
            
            <div style="height: 60px;"></div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle'); 
        if(toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    </script>
</body>
</html>