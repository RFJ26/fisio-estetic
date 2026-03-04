<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: ../index.php");
    exit();
}

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php';

$id_funcionario = $_SESSION['id'];
$mensagem_sucesso = '';
$mensagem_erro = '';

$query_perfil = "SELECT * FROM funcionario WHERE id = '$id_funcionario'";
$resultado_perfil = mysqli_query($conn, $query_perfil);

if (!$resultado_perfil || mysqli_num_rows($resultado_perfil) == 0) {
    session_destroy();
    header("Location: ../index.php?erro=sessao_invalida");
    exit();
}

$perfil = mysqli_fetch_assoc($resultado_perfil);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_dados') {
        
        $nome     = mysqli_real_escape_string($conn, trim($_POST['nome']));
        $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
        $telefone = mysqli_real_escape_string($conn, trim($_POST['telefone']));
        $nif      = mysqli_real_escape_string($conn, trim($_POST['nif']));

        $erros = [];

        $regexpNome = "/(^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)| (^[A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)* (((de)|(dos)|(da)|(do Ó)))?( [A-ZAÁÀÃÂEÉÈÊIÍÌÎOÓÒÕÔUÚÙÛ][a-zaáàãâeéèêiíìîoóòõôuúùû]+)+$)/";

        if (!preg_match($regexpNome, $nome)) {
            $erros[] = "Nome incorreto. Verifique as maiúsculas e o formato.";
        }

        if (!preg_match("/^[1-9]\d{8}$/", $nif)) {
            $erros[] = "NIF inválido (9 dígitos, não pode começar por 0).";
        }

        if (!preg_match("/^(\\+351)?((2\\d{8})|(9[1236]\\d{7}))$/", $telefone)) {
            $erros[] = "Telefone inválido.";
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = "Email inválido.";
        }

        if (!empty($erros)) {
            $mensagem_erro = implode("<br>", $erros);
        } else {
            $check = "SELECT id FROM funcionario WHERE (email = '$email' OR nif = '$nif') AND id != '$id_funcionario'";
            $res_check = mysqli_query($conn, $check);
            
            if (mysqli_num_rows($res_check) > 0) {
                $mensagem_erro = "O Email ou NIF já estão a ser usados.";
            } else {
                $query_update = "UPDATE funcionario SET nome = '$nome', email = '$email', telefone = '$telefone', nif = '$nif' WHERE id = '$id_funcionario'";
                
                if (mysqli_query($conn, $query_update)) {
                    $mensagem_sucesso = "Dados atualizados com sucesso.";
                    $_SESSION['nome'] = $nome;
                    
                    $perfil['nome'] = $nome;
                    $perfil['email'] = $email;
                    $perfil['telefone'] = $telefone;
                    $perfil['nif'] = $nif;
                } else {
                    $mensagem_erro = "Erro ao atualizar: " . mysqli_error($conn);
                }
            }
        }
    }

    if (isset($_POST['acao']) && $_POST['acao'] === 'alterar_senha') {
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/", $nova_senha)) {
            $mensagem_erro = "A nova senha deve ter: Min 8 caracteres, 1 Maiúscula, 1 minúscula, 1 número.";
        } else {
            if (md5($senha_atual) === $perfil['palavra_passe']) {
                if ($nova_senha === $confirmar_senha) {
                    $nova_hash = md5($nova_senha);
                    $query_pass = "UPDATE funcionario SET palavra_passe = '$nova_hash' WHERE id = '$id_funcionario'";
                    
                    if (mysqli_query($conn, $query_pass)) {
                        $mensagem_sucesso = "Palavra-passe alterada com sucesso.";
                        $perfil['palavra_passe'] = $nova_hash;
                    } else {
                        $mensagem_erro = "Erro na base de dados.";
                    }
                } else {
                    $mensagem_erro = "As novas senhas não coincidem.";
                }
            } else {
                $mensagem_erro = "A senha atual está incorreta.";
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
    <title>Meu Perfil - Fisioestetic</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/mouse-fix.css">
    <link rel="stylesheet" href="../css/worker/perfil.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-md-none">
        <i class="bi bi-list"></i>
    </button>

    <nav class="sidebar">
        <div class="logo-area">
            <h2>Fisioestetic</h2>
        </div>
        
        <ul class="nav flex-column mt-4">
            <li class="nav-item">
                <a class="nav-link " href="dashboard.php">
                    <i class="bi bi-grid-1x2-fill"></i> Início
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="minhas_marcacoes.php">
                    <i class="bi bi-calendar-check"></i> Agenda
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link " href="indisponibilidade.php">
                    <i class="bi bi-slash-circle"></i> Indisponibilidade
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="perfil.php">
                    <i class="bi bi-person-circle"></i> Meu Perfil
                </a>
            </li>
            
            <li class="nav-item mt-auto">
                <a class="nav-link logout" href="../logout.php">
                    <i class="bi bi-box-arrow-left"></i> Sair
                </a>
            </li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">

            <header class="mb-4">
                <h2 class="fw-bold m-0">Meu Perfil</h2>
            </header>

            <?php if(!empty($mensagem_sucesso)): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $mensagem_sucesso ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($mensagem_erro)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= $mensagem_erro ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                
                <div class="col-lg-5">
                    <div class="card-custom">
                        <div class="text-center mb-4">
                            <div class="avatar-circle">
                                <i class="bi bi-person"></i>
                            </div>
                            <h4 class="fw-bold"><?= htmlspecialchars($perfil['nome'] ?? 'Colaborador') ?></h4>
                            <span class="badge bg-light text-dark border">Colaborador</span>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="atualizar_dados">
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted">Nome Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" name="nome" class="form-control" 
                                           value="<?= htmlspecialchars($perfil['nome'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($perfil['email'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted">Telemóvel</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="text" name="telefone" class="form-control" 
                                           value="<?= htmlspecialchars($perfil['telefone'] ?? '') ?>" required
                                           pattern="(\+351)?(2\d{8}|9[1236]\d{7})">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small text-muted">NIF</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                    <input type="text" name="nif" class="form-control" 
                                           value="<?= htmlspecialchars($perfil['nif'] ?? '') ?>" required
                                           pattern="[1-9]\d{8}">
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-gold">
                                    Guardar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card-custom">
                        <div class="section-title">
                            <i class="bi bi-shield-lock me-2 text-gold"></i>Alterar Palavra-passe
                        </div>

                        <div class="alert alert-light border small text-muted mb-4">
                            <i class="bi bi-info-circle me-1 text-gold"></i>
                            A senha deve ter no mínimo <strong>8 caracteres</strong>, incluir letra <strong>maiúscula</strong>, <strong>minúscula</strong> e <strong>número</strong>.
                        </div>
                        
                        <form method="POST" action="" id="formSenha">
                            <input type="hidden" name="acao" value="alterar_senha">

                            <div class="mb-3">
                                <label class="form-label small text-muted">Senha Atual</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                                    <input type="password" placeholder="******" name="senha_atual" class="form-control" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small text-muted">Nova Senha</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                        <input type="password" name="nova_senha" id="nova_senha" class="form-control" 
                                               required placeholder="******"
                                               pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}"
                                               title="A senha deve ter pelo menos 8 caracteres, uma maiúscula, uma minúscula e um número.">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label small text-muted">Confirmar Nova</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-check-lg"></i></span>
                                        <input type="password" name="confirmar_senha" id="confirmar_senha" placeholder="******" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-outline-dark">
                                    Atualizar Senha
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebarToggle = document.getElementById('sidebarToggle');
        if(sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                document.querySelector('.sidebar').classList.toggle('active');
            });
        }

        const formSenha = document.getElementById('formSenha');
        const novaSenha = document.getElementById('nova_senha');
        const confSenha = document.getElementById('confirmar_senha');

        if(formSenha) {
            formSenha.addEventListener('submit', function(e) {
                if(novaSenha.value !== confSenha.value) {
                    e.preventDefault();
                    alert('As senhas não coincidem!');
                }
            });
        }
    </script>
</body>
</html>