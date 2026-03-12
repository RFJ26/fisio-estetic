<?php
session_start();


include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

$erros = [];

if (isset($_POST['create_client'])) {
    $nome     = trim($_POST['nome']);
    $email    = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $nif      = trim($_POST['nif']);
    $password = $_POST['password'];
    $obs      = trim($_POST['obs']);

    $regexpNome = "/(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)| (^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)* (((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)/";
    if (!preg_match($regexpNome, $nome)) $erros['nome'] = "Nome incorreto (Ex: Ana Silva).";

    $regexEmail = "/^[a-zA-Z0-9\\-]+(\\.[a-zA-Z0-9]+)*@[a-zA-Z0-9]+(\\.[a-zA-Z0-9]+)*$/";
    if (!empty($email) && !preg_match($regexEmail, $email)) $erros['email'] = "Email inválido.";

    $regexpTelefone = "/^(\\+351)?((2\\d{8})|(9[1236]\\d{7}))$/";
    if (!preg_match($regexpTelefone, $telefone)) $erros['telefone'] = "Telefone inválido (9 dígitos).";

    $regexpNif = "/^[1-79]\\d{8}$/";
    if (!empty($nif) && !preg_match($regexpNif, $nif)) $erros['nif'] = "NIF inválido (9 dígitos).";

    if (empty($password) || strlen($password) < 6) $erros['password'] = "Palavra-passe requer min. 6 caracteres.";

    if (empty($erros)) {
        $checkClient = "SELECT id FROM cliente WHERE email = ? OR nif = ?";
        $stmtClient = mysqli_prepare($conn, $checkClient);
        mysqli_stmt_bind_param($stmtClient, "ss", $email, $nif);
        mysqli_stmt_execute($stmtClient);
        mysqli_stmt_store_result($stmtClient);

        if (mysqli_stmt_num_rows($stmtClient) > 0) {
            $erros['bd'] = "Email ou NIF já existe como Cliente.";
        } else {
            $checkFunc = "SELECT id FROM funcionario WHERE email = ? OR nif = ?";
            $stmtFunc = mysqli_prepare($conn, $checkFunc);
            mysqli_stmt_bind_param($stmtFunc, "ss", $email, $nif);
            mysqli_stmt_execute($stmtFunc);
            mysqli_stmt_store_result($stmtFunc);

            if (mysqli_stmt_num_rows($stmtFunc) > 0) {
                $erros['bd'] = "Email ou NIF já existe como Funcionário.";
            } else {
                $passHash = md5($password);
                $query = "INSERT INTO cliente (nome, email, telefone, nif, palavra_passe, obs) VALUES (?, ?, ?, ?, ?, ?)";
                $stmtInsert = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmtInsert, "ssssss", $nome, $email, $telefone, $nif, $passHash, $obs);

                if (mysqli_stmt_execute($stmtInsert)) {
                    echo "<script>alert('Cliente criado com sucesso!'); window.location.href = 'list.php';</script>";
                    exit();
                } else {
                    $erros['bd'] = "Erro na BD: " . mysqli_error($conn);
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
    <title>Novo Cliente - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/customer/create.css"> 
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
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
            
            <header class="d-flex justify-content-between align-items-center page-header mb-4">
                <div>
                    <h1>Novo Cliente</h1>
                    <p class="text-muted mb-0">Preencha os dados para registar um novo cliente.</p>
                </div>
                <a href="list.php" class="btn-cancel">
                    <i class="bi bi-arrow-left me-2"></i>Voltar
                </a>
            </header>

            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm border-0" role="alert" style="border-radius: 12px;">
                    <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                    <div>
                        <?php foreach ($erros as $erro) echo "<div>$erro</div>"; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card card-custom">
                <div class="card-body p-4">
                    <form method="POST" autocomplete="off">
                        
                        <div class="row g-4">
                            <div class="col-12">
                                <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                <input type="text" id="nome" name="nome" class="form-control form-control-lg" 
                                    placeholder="Ex: Ana Silva"
                                    title="Nome completo. Ex: João das Dores. Use iniciais maiúsculas." 

                                    value="<?= isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : '' ?>" required>
                                <span class="text-help">Primeira letra maiúscula e pelo menos um sobrenome.</span>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" 
                                    placeholder="cliente@email.com" autocomplete="off"
                                    pattern="[a-zA-Z0-9\-\.]+@[a-zA-Z0-9]+\.[a-zA-Z0-9\.]+"
                                    title="Introduza um email válido"
                                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone <span class="text-danger">*</span></label>
                                <input type="text" id="telefone" name="telefone" class="form-control" 
                                    placeholder="912345678"
                                    pattern="(\+351)?(2\d{8}|9[1236]\d{7})"
                                   title="Deve começar por 2 ou 9(1,2,3,6) e ter 9 dígitos" 
                                    value="<?= isset($_POST['telefone']) ? htmlspecialchars($_POST['telefone']) : '' ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="nif" class="form-label">NIF</label>
                                <input type="text" id="nif" name="nif" class="form-control" 
                                    placeholder="Nº Contribuinte"
                                    pattern="[0-9]{9}"
                                   title="O NIF deve ter 9 dígitos" 
                                    value="<?= isset($_POST['nif']) ? htmlspecialchars($_POST['nif']) : '' ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">Palavra-passe <span class="text-danger">*</span></label>
                                <input type="password" id="password" name="password" class="form-control" 
                                    placeholder="••••••••" autocomplete="new-password" required>
                                <span class="text-help">Mínimo 6 caracteres.</span>
                            </div>

                            <div class="col-12">
                                <label for="obs" class="form-label">Observações</label>
                                <textarea id="obs" name="obs" class="form-control" rows="3"><?= isset($_POST['obs']) ? htmlspecialchars($_POST['obs']) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="form-actions d-flex justify-content-end gap-3">
                            <a href="list.php" class="btn-cancel border-0">Cancelar</a>
                            <button type="submit" name="create_client" class="btn-save">
                                <i class="bi bi-check-lg me-2"></i>Criar Cliente
                            </button>
                        </div>

                    </form>
                </div>
            </div>

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