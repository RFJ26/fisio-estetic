<?php
session_start();



include('../verifica_login.php');
require_once __DIR__ . '/../../src/conexao.php';

$id = $_GET['id'] ?? $_POST['id'] ?? null;
$erros = [];
$utilizador = [];


if (!$id) {
    header("Location: list.php");
    exit;
}


$stmt = mysqli_prepare($conn, "SELECT * FROM cliente WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$utilizador = mysqli_fetch_assoc($result);

if (!$utilizador) {
    echo "<script>alert('Cliente não encontrado.'); window.location.href = 'list.php';</script>";
    exit;
}


$nome = $utilizador['nome'];
$email = $utilizador['email'];
$telefone = $utilizador['telefone'];
$nif = $utilizador['nif'];
$obs = $utilizador['obs'];
$data_registo = isset($utilizador['created_at']) ? date('d/m/Y H:i', strtotime($utilizador['created_at'])) : '-';


if (isset($_POST['edit_customer'])) {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $telefone = trim($_POST['telefone']);
    $nif = trim($_POST['nif']);
    $password = $_POST['password'] ?? '';
    $obs = trim($_POST['obs']);

    // --- VALIDAÇÕES ---

    // Nome
    $regexpNome = "/^[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+(\s((de|da|do|das|dos|e)\s)?[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+)+$/u";
    if (!preg_match($regexpNome, $nome)) {
        $erros['nome'] = "Nome incorreto. Use iniciais maiúsculas.";
    }
    // Email
    $regexEmail = "/^[a-zA-Z0-9\\-]+(\\.[a-zA-Z0-9]+)*@[a-zA-Z0-9]+(\\.[a-zA-Z0-9]+)*$/";
    if (!preg_match($regexEmail, $email)) {
        $erros['email'] = "Email inválido.";
    }
    // Telefone
    $regexpTelefone = "/^(\\+351)?((2\\d{8})|(9[1236]\\d{7}))$/";
    if (!preg_match($regexpTelefone, $telefone)) {
        $erros['telefone'] = "Telefone inválido.";
    }
    // NIF
    $regexpNif = "/^[0-9]{9}$/";
    if (!preg_match($regexpNif, $nif)) {
        $erros['nif'] = "NIF inválido.";
    }

    if (empty($erros)) {
        // Verifica duplicados na tabela clien.
        $checkQuery = "SELECT id FROM cliente WHERE (email = ? OR nif = ?) AND id != ?";
        $stmtCheck = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($stmtCheck, "ssi", $email, $nif, $id);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_store_result($stmtCheck);

        if (mysqli_stmt_num_rows($stmtCheck) > 0) {
            $erros['bd'] = "Já existe outro cliente com este Email ou NIF.";
        } else {

            // Verifica duplicados na tabela func
            $checkWorker = "SELECT id FROM funcionario WHERE email = ? OR nif = ?";
            $stmtWorker = mysqli_prepare($conn, $checkWorker);
            mysqli_stmt_bind_param($stmtWorker, "ss", $email, $nif);
            mysqli_stmt_execute($stmtWorker);
            mysqli_stmt_store_result($stmtWorker);

            if (mysqli_stmt_num_rows($stmtWorker) > 0) {
                $erros['bd'] = "Este Email ou NIF já está registado como Funcionário.";
            } else {


                if (!empty($password)) {
                    // Com Password nova
                    $passHash = md5($password);
                    $query = "UPDATE cliente SET nome=?, email=?, telefone=?, nif=?, obs=?, palavra_passe=? WHERE id=?";
                    $stmtUpdate = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmtUpdate, "ssssssi", $nome, $email, $telefone, $nif, $obs, $passHash, $id);
                } else {
                    // Sem mudar Password
                    $query = "UPDATE cliente SET nome=?, email=?, telefone=?, nif=?, obs=? WHERE id=?";
                    $stmtUpdate = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmtUpdate, "sssssi", $nome, $email, $telefone, $nif, $obs, $id);
                }

                if (mysqli_stmt_execute($stmtUpdate)) {
                    echo "<script>alert('Cliente atualizado com sucesso!'); window.location.href = 'list.php';</script>";
                    exit();
                } else {
                    $erros['bd'] = "Erro ao atualizar na base de dados.";
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
    <title>Editar Cliente - Fisioestetic</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/customer/edit.css">
</head>

<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none"><i class="bi bi-list"></i></button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area">
            <h2>Fisioestetic</h2>
        </div>
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="../adm/dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i>Início</a></li>
            <li class="nav-item"><a class="nav-link" href="../worker/list.php"><i class="bi bi-person-badge me-3"></i>Funcionários</a></li>
            <li class="nav-item"><a class="nav-link active" href="list.php"><i class="bi bi-people me-3"></i>Clientes</a></li>
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
                    <h1>Editar Cliente</h1>
                    <p class="text-muted">Atualize as informações do cliente abaixo.</p>
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

            <?php if ($utilizador): ?>

                <div class="edit-card">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="row g-4">
                            <div class="col-12">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" id="nome" name="nome" class="form-control"
                                    value="<?= htmlspecialchars($nome) ?>"
                                    pattern="[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+(\s((de|da|do|das|dos|e)\s)?[A-ZÁÀÃÂÉÈÊÍÌÎÓÒÕÔÚÙÛÇ][a-záàãâéèêíìîóòõôúùûç]+)+"
                                    title="Nome completo. Ex: João das Dores. Use iniciais maiúsculas."
                                    required>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" id="email" name="email" class="form-control"
                                    value="<?= htmlspecialchars($email) ?>"
                                    pattern="[a-zA-Z0-9\-\.]+@[a-zA-Z0-9]+\.[a-zA-Z0-9\.]+"
                                    title="Introduza um email válido"
                                    required>
                            </div>

                            <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="text" id="telefone" name="telefone" class="form-control"
                                    value="<?= htmlspecialchars($telefone) ?>"
                                    pattern="(\+351)?(2\d{8}|9[1236]\d{7})"
                                    title="Deve começar por 2 ou 9(1,2,3,6) e ter 9 dígitos"
                                    maxlength="13" required>
                            </div>

                            <div class="col-md-6">
                                <label for="nif" class="form-label">NIF / Contribuinte</label>
                                <input type="text" id="nif" name="nif" class="form-control"
                                    value="<?= htmlspecialchars($nif) ?>"
                                    pattern="[0-9]{9}"
                                    title="O NIF deve ter 9 dígitos"
                                    maxlength="9" required>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label">Palavra-passe</label>
                                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••">
                                <small class="text-muted" style="font-size: 0.8em;">Deixe em branco para manter a atual.</small>
                            </div>

                            <div class="col-12">
                                <label for="obs" class="form-label">Observações</label>
                                <textarea class="form-control" id="obs" name="obs" rows="3"></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Data de Registo</label>
                                <input type="text" class="form-control" value="<?= $data_registo ?>" readonly style="background-color: #f8f9fa; color: #6c757d;">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-5">
                            <button type="button" class="btn btn-delete"
                                onclick="if(confirm('Tem a certeza que deseja apagar este cliente? Esta ação é irreversível.')) window.location.href='list.php?delete=<?= $id ?>'">
                                <i class="bi bi-trash me-2"></i>Apagar Cliente
                            </button>

                            <button type="submit" name="edit_customer" class="btn-save">
                                <i class="bi bi-check-lg me-2"></i>Guardar Alterações
                            </button>
                        </div>

                    </form>
                </div>

            <?php else: ?>
                <div class="alert alert-warning text-center">Cliente não encontrado.</div>
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