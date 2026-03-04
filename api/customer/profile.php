<?php
session_start();
require_once __DIR__ . '/../../src/conexao.php';

if (!isset($_COOKIE['id_cliente'])) {
    header("Location: ../index.php"); 
    exit();
}

$id_cliente = $_COOKIE['id_cliente'];

// Dados para o topo (Igual ao Dashboard)
$nome_cliente = $_COOKIE['nome'];
$primeiro_nome = explode(" ", $nome_cliente)[0];

$mensagem = "";
$tipo_mensagem = "";

// =================================================================================
// LÓGICA DE VALIDAÇÃO (REGEX ESTRITOS)
// =================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nome     = trim(mysqli_real_escape_string($conn, $_POST['nome']));
    $email    = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $telefone = trim(mysqli_real_escape_string($conn, $_POST['telefone']));
    $nif      = trim(mysqli_real_escape_string($conn, $_POST['nif']));
    $nova_senha = $_POST['password']; 

    $erros = [];

    // 1. REGEX NOME
    $regexpNome = "/(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)|(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)* (((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)/";
    if (!preg_match($regexpNome, $nome)) {
        $erros[] = "<strong>Nome:</strong> Formato inválido. Use iniciais maiúsculas (Ex: Ana Silva).";
    }

    // 2. REGEX TELEFONE
    $regexpTelefone = "/^(\+351)?((2\d{8})|(9[1236]\d{7}))$/";
    if (!preg_match($regexpTelefone, $telefone)) {
        $erros[] = "<strong>Telefone:</strong> Inválido. Comece por 91, 92, 93, 96 ou 2.";
    }

    // 3. REGEX NIF
    $regexpNif = "/^[1-79]\d{8}$/";
    if (!preg_match($regexpNif, $nif)) {
        $erros[] = "<strong>NIF:</strong> Inválido (deve ter 9 dígitos).";
    }

    // 4. EMAIL
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "<strong>Email:</strong> Formato inválido.";
    }

    // 5. SENHA
    if (!empty($nova_senha)) {
        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/", $nova_senha)) {
            $erros[] = "<strong>Senha:</strong> Mín. 8 caracteres, 1 Maiúscula, 1 minúscula e 1 número.";
        }
    }

    // --- Processamento ---
    if (!empty($erros)) {
        $mensagem = implode("<br>", $erros);
        $tipo_mensagem = "danger";
    } else {
        $check_duplicate = "SELECT id FROM cliente WHERE (email = '$email' OR nif = '$nif') AND id != '$id_cliente'";
        $rs_check = mysqli_query($conn, $check_duplicate);

        if (mysqli_num_rows($rs_check) > 0) {
            $mensagem = "O Email ou NIF inserido já pertence a outra conta.";
            $tipo_mensagem = "danger";
        } else {
            if (!empty($nova_senha)) {
                $senha_hash = md5($nova_senha);
                $sql_update = "UPDATE cliente SET nome='$nome', email='$email', telefone='$telefone', nif='$nif', palavra_passe='$senha_hash' WHERE id='$id_cliente'";
            } else {
                $sql_update = "UPDATE cliente SET nome='$nome', email='$email', telefone='$telefone', nif='$nif' WHERE id='$id_cliente'";
            }

            if (mysqli_query($conn, $sql_update)) {
                $mensagem = "Dados atualizados com sucesso!";
                $tipo_mensagem = "success";
                $_COOKIE['nome'] = $nome; 
                $nome_cliente = $nome;
                $primeiro_nome = explode(" ", $nome_cliente)[0];
            } else {
                $mensagem = "Erro ao atualizar: " . mysqli_error($conn);
                $tipo_mensagem = "danger";
            }
        }
    }
}

$query_user = "SELECT * FROM cliente WHERE id = '$id_cliente'";
$result_user = mysqli_query($conn, $query_user);
$dados = mysqli_fetch_assoc($result_user);
?>

<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Fisioestetic</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/mouse-fix.css">
    <link rel="stylesheet" href="../css/customer/profile.css">
</head>
<body>

    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
                <img src="../images/logo_nova.png" alt="Logo" class="navbar-logo me-2">
                FISIOESTETIC
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav align-items-center gap-3">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Início</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="booking_new.php">Nova Marcação</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_bookings.php">Histórico</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-profile-link" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($primeiro_nome) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                            <li><a class="dropdown-item active" href="profile.php">Meu Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../logout.php">Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <div class="d-flex align-items-center mb-5">
                    <a href="dashboard.php" class="btn btn-light rounded-circle shadow-sm me-4" style="width: 50px; height: 50px; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-arrow-left fs-5"></i>
                    </a>
                    <div>
                        <h1 class="page-title mb-0">Meu Perfil</h1>
                        <p class="page-subtitle mb-0">Atualize os seus dados pessoais e segurança</p>
                    </div>
                </div>

                <?php if ($mensagem): ?>
                    <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show shadow-sm border-0" role="alert">
                        <?php if($tipo_mensagem == 'success'): ?><i class="bi bi-check-circle-fill me-2"></i><?php endif; ?>
                        <?php if($tipo_mensagem == 'danger'): ?><i class="bi bi-exclamation-triangle-fill me-2"></i><?php endif; ?>
                        <?= $mensagem ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="profile-card">
                    <div class="section-header">
                        <i class="bi bi-person-vcard fs-4 text-success"></i>
                        <h4 class="section-title">Dados Pessoais</h4>
                    </div>

                    <form method="POST" action="profile.php">
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="nome" name="nome" 
                                           value="<?= htmlspecialchars($dados['nome']) ?>" 
                                           pattern="(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)|(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)* (((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)"
                                           title="Nome completo (Ex: Ana Silva). Iniciais Maiúsculas." required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($dados['email']) ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="tel" class="form-control" id="telefone" name="telefone" 
                                           value="<?= htmlspecialchars($dados['telefone']) ?>" 
                                           pattern="(\+351)?(2\d{8}|9[1236]\d{7})" 
                                           title="Comece por 91, 92, 93, 96 ou 2" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="nif" class="form-label">NIF</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                    <input type="text" class="form-control" id="nif" name="nif" 
                                           value="<?= htmlspecialchars($dados['nif']) ?>" 
                                           pattern="[1-9]\d{8}" 
                                           title="9 dígitos, começar por 1-7 ou 9" required>
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <div class="section-header">
                                    <i class="bi bi-shield-lock fs-4 text-success"></i>
                                    <h4 class="section-title">Segurança</h4>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label for="password" class="form-label">Nova Palavra-passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                           title="Mínimo 8 caracteres, 1 maiúscula, 1 minúscula e 1 número"
                                           placeholder="Deixe em branco para manter a atual">
                                </div>
                            </div>

                            <div class="col-12 mt-5 d-flex justify-content-end gap-3">
                                <a href="dashboard.php" class="btn-cancel">
                                    <i class="bi bi-x-lg"></i> Cancelar
                                </a>
                                <button type="submit" class="btn-save">
                                    <i class="bi bi-check-lg"></i> Guardar Alterações
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>