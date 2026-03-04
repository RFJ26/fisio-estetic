<?php
session_start();


include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

$id = $_GET['id'] ?? $_POST['id'] ?? null;
$erros = [];
$funcionario = [];

if (!$id) {
    header("Location: list.php");
    exit;
}

// Buscar dados atuais do funcionário
$stmt = mysqli_prepare($conn, "SELECT * FROM funcionario WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$funcionario = mysqli_fetch_assoc($result);

if (!$funcionario) {
    echo "<script>alert('Funcionário não encontrado.'); window.location.href = 'list.php';</script>";
    exit;
}

$nome     = $funcionario['nome'];
$email    = $funcionario['email'];
$telefone = $funcionario['telefone'];
$nif      = $funcionario['nif'];
$adm      = $funcionario['adm']; 
$data_registo = $funcionario['created_at'] ?? date('Y-m-d'); 

if (isset($_POST['edit_worker'])) {
    $nome     = trim($_POST['nome']);
    $email    = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $nif      = trim($_POST['nif']);
    $adm      = $_POST['adm']; 
    $password = $_POST['password'];

    // --- VALIDAÇÕES ---
    
    // Nome
    $regexpNome = "/^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+(?: [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)*$/u";
    if (!preg_match($regexpNome, $nome)) {
        $erros['nome'] = "Nome incorreto. Use letra maiúscula inicial.";
    }

    // Email (CORRIGIDO PARA REGEX)
    $regexEmail = "/^[a-zA-Z0-9\\-]+(\\.[a-zA-Z0-9]+)*@[a-zA-Z0-9]+(\\.[a-zA-Z0-9]+)*$/";
    if (!preg_match($regexEmail, $email)) {
        $erros['email'] = "Email inválido.";
    }

    // Telefone
    $regexpTelefone = "/^[92][0-9]{8}$/";
    if (!preg_match($regexpTelefone, $telefone)) {
        $erros['telefone'] = "Telefone inválido (9 dígitos).";
    }

    // NIF
    $regexpNif = "/^[0-9]{9}$/";
    if (!preg_match($regexpNif, $nif)) {
        $erros['nif'] = "NIF inválido (9 dígitos).";
    }

    // --- VERIFICAÇÕES DE DUPLICADOS ---
    if (empty($erros)) {
        
        // 1. Verificar se existe OUTRO funcionário com este email/nif (excluindo o próprio ID)
        $checkQuery = "SELECT id FROM funcionario WHERE (email = ? OR nif = ?) AND id != ?";
        $stmtCheck = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmtCheck, "ssi", $email, $nif, $id);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);

        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            $erros['bd'] = "Este email ou NIF já pertence a outro funcionário.";
        } else {
            
            // 2. Verificar se existe ALGUM Cliente com este email/nif
            $checkClient = "SELECT id FROM cliente WHERE email = ? OR nif = ?";
            $stmtClient = mysqli_prepare($conn, $checkClient);
            mysqli_stmt_bind_param($stmtClient, "ss", $email, $nif);
            mysqli_stmt_execute($stmtClient);
            mysqli_stmt_store_result($stmtClient);

            if (mysqli_stmt_num_rows($stmtClient) > 0) {
                $erros['bd'] = "Este email ou NIF já está associado a um Cliente.";
            } else {

                // --- ATUALIZAÇÃO NA BD ---
                if (!empty($password)) {
                    if(strlen($password) < 6) {
                        $erros['password'] = "A nova password deve ter min. 6 caracteres.";
                    } else {
                        $passHash = md5($password);
                        $query = "UPDATE funcionario SET nome=?, email=?, telefone=?, nif=?, adm=?, palavra_passe=? WHERE id=?";
                        $stmtUpdate = mysqli_prepare($conn, $query);
                        mysqli_stmt_bind_param($stmtUpdate, "ssssisi", $nome, $email, $telefone, $nif, $adm, $passHash, $id);
                    }
                } else {
                    $query = "UPDATE funcionario SET nome=?, email=?, telefone=?, nif=?, adm=? WHERE id=?";
                    $stmtUpdate = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmtUpdate, "ssssii", $nome, $email, $telefone, $nif, $adm, $id);
                }

                if (empty($erros) && isset($stmtUpdate)) {
                    if (mysqli_stmt_execute($stmtUpdate)) {
                        echo "<script>alert('Funcionário atualizado com sucesso!'); window.location.href = 'list.php';</script>";
                        exit();
                    } else {
                        $erros['bd'] = "Erro ao atualizar na base de dados.";
                    }
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
    <title>Editar Funcionário - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/customer/edit.css">
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
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link" href="../customer/list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
            <li class="nav-item"><a class="nav-link" href="../service/list.php"><i class="bi bi-scissors me-3"></i>Serviços</a></li>
            <li class="nav-item"><a class="nav-link" href="../service_category/list.php"><i class="bi bi-tag me-3"></i>Categorias</a></li>
            <li class="nav-item"><a class="nav-link" href="../booking/list.php"><i class="bi bi-calendar-check me-3"></i>Marcações</a></li>
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i>Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">
            
            <header class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h1>Editar Funcionário</h1>
                    <p class="text-muted">Atualize as informações e permissões.</p>
                </div>
                <a href="list.php" class="btn-cancel">
                    <i class="bi bi-arrow-left me-2"></i>Voltar
                </a>
            </header>

            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div>
                        <?php foreach ($erros as $erro) echo "<div>$erro</div>"; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if($funcionario): ?>
            
            <div class="edit-card">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $id ?>">

                    <div class="row g-4">
                        <div class="col-12">
                            <label for="nome" class="form-label">Nome Completo</label>
                            <input type="text" id="nome" name="nome" class="form-control" 
                            pattern="[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+(\s((de|da|do|das|dos|e)\s)?[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+)+"
                            title="Nome completo. Ex: João das Dores. Use iniciais maiúsculas." value="<?= htmlspecialchars($nome) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Profissional</label>
                            <input type="email" id="email" name="email" class="form-control"
                            pattern="[a-zA-Z0-9\-\.]+@[a-zA-Z0-9]+\.[a-zA-Z0-9\.]+"
                            title="Introduza um email válido" value="<?= htmlspecialchars($email) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" id="telefone" name="telefone" class="form-control" 
                            pattern="(\+351)?(2\d{8}|9[1236]\d{7})"
                                   title="Deve começar por 2 ou 9(1,2,3,6) e ter 9 dígitos"  value="<?= htmlspecialchars($telefone) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="nif" class="form-label">NIF</label>
                            <input type="text" id="nif" name="nif" class="form-control" 
                            pattern="[0-9]{9}"
                            title="O NIF deve ter 9 dígitos" value="<?= htmlspecialchars($nif) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="adm" class="form-label">Cargo / Permissões</label>
                            <select class="form-select" id="adm" name="adm">
                                <option value="0" <?= $adm == 0 ? 'selected' : '' ?>>Funcionário (Limitado)</option>
                                <option value="1" <?= $adm == 1 ? 'selected' : '' ?>>Administrador (Total)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="password" class="form-label">Palavra-passe</label>
                            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••">
                            <small class="text-help" style="color: #6c757d; font-size: 0.85em;">Deixe vazio para manter a atual.</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Data de Registo</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($data_registo) ?>" readonly style="background-color: #e9ecef;">
                        </div>
                    </div>

                    <div class="form-actions d-flex justify-content-between mt-4">
                        <div>
                            <button type="submit" name="edit_worker" class="btn-save">
                                <i class="bi bi-check-lg me-2"></i>Guardar Alterações
                            </button>
                        </div>

                        <div>
                            <button type="button" class="btn btn-danger" style="padding: 10px 20px; border-radius: 8px;"
                                onclick="if(confirm('Tem a certeza que deseja apagar este funcionário?')) window.location.href='delete.php?id=<?= $id ?>'">
                                <i class="bi bi-trash me-2"></i>Apagar Funcionário
                            </button>
                        </div>
                    </div>

                </form>
            </div>

            <?php else: ?>
                <div class="alert alert-warning text-center">
                    Funcionário não encontrado.
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const toggle = document.getElementById('sidebarToggle');
        toggle.addEventListener('click', () => sidebar.classList.toggle('active'));
    </script>
</body>
</html>