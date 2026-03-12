<?php
session_start();
date_default_timezone_set('Europe/Lisbon');

if (!isset($_COOKIE['id'])) {
    header("Location: ../index.php");
    exit();
}

include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';
require_once __DIR__ . '/../../src/helpers.php';

$id_funcionario = $_COOKIE['id'];
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

// =========================================================================
// PROCESSAR FORMULÁRIOS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- ATUALIZAR DADOS PESSOAIS ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'atualizar_dados') {
        
        $nome     = mysqli_real_escape_string($conn, trim($_POST['nome']));
        $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
        $telefone = mysqli_real_escape_string($conn, trim($_POST['telefone']));
        $nif      = mysqli_real_escape_string($conn, trim($_POST['nif']));

        $erros = [];

        // Validações
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
            // Verificar duplicidade de Email ou NIF
            $check = "SELECT id FROM funcionario WHERE (email = '$email' OR nif = '$nif') AND id != '$id_funcionario'";
            $res_check = mysqli_query($conn, $check);
            
            if (mysqli_num_rows($res_check) > 0) {
                $mensagem_erro = "O Email ou NIF já estão a ser usados por outro utilizador.";
            } else {
                $query_update = "UPDATE funcionario SET nome = '$nome', email = '$email', telefone = '$telefone', nif = '$nif' WHERE id = '$id_funcionario'";
                
                if (mysqli_query($conn, $query_update)) {
                    $mensagem_sucesso = "Dados atualizados com sucesso.";
                    $_COOKIE['nome'] = $nome; // Atualizar cookie localmente
                    
                    // Atualizar array local para refletir na view imediata
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

    // --- ALTERAR PALAVRA-PASSE ---
    if (isset($_POST['acao']) && $_POST['acao'] === 'alterar_senha') {
        $senha_atual = $_POST['senha_atual'];
        $nova_senha = $_POST['nova_senha'];
        $confirmar_senha = $_POST['confirmar_senha'];

        if (!preg_match("/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/", $nova_senha)) {
            $mensagem_erro = "A nova senha deve ter: Min 8 caracteres, 1 Maiúscula, 1 minúscula e 1 número.";
        } else {
            if (md5($senha_atual) === $perfil['palavra_passe']) {
                if ($nova_senha === $confirmar_senha) {
                    $nova_hash = md5($nova_senha);
                    $query_pass = "UPDATE funcionario SET palavra_passe = '$nova_hash' WHERE id = '$id_funcionario'";
                    
                    if (mysqli_query($conn, $query_pass)) {
                        $mensagem_sucesso = "Palavra-passe alterada com sucesso.";
                        $perfil['palavra_passe'] = $nova_hash;
                    } else {
                        $mensagem_erro = "Erro na base de dados ao atualizar senha.";
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
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/worker/perfil.css">
</head>
<body>

    <button id="sidebarToggle" class="sidebar-toggle d-lg-none"><i class="bi bi-list"></i></button>

    <nav class="sidebar d-flex flex-column">
        <div class="logo-area"><h2>Fisioestetic</h2></div>
        
        <ul class="nav flex-column mt-4">
            <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-grid-1x2-fill me-3"></i> Início</a></li>
            <li class="nav-item"><a class="nav-link" href="minhas_marcacoes.php"><i class="bi bi-calendar-check me-3"></i> Agenda</a></li>
            <li class="nav-item"><a class="nav-link" href="indisponibilidade.php"><i class="bi bi-slash-circle me-3"></i> Indisponibilidade</a></li>
            <li class="nav-item"><a class="nav-link active" href="perfil.php"><i class="bi bi-person-circle me-3"></i> Meu Perfil</a></li>
            
            <?php if(isset($_COOKIE['role']) && ($_COOKIE['role'] === 'admin' || $_COOKIE['role'] === '1')): ?>
                <li class="nav-item mt-3"><a class="nav-link" href="/select_role.php" style="color: #ef6c00;"><i class="bi bi-arrow-left-right me-3"></i>Mudar Perfil</a></li>
            <?php endif; ?>
            
            <li class="nav-item mt-auto"><a class="nav-link logout" href="../logout.php"><i class="bi bi-box-arrow-left me-3"></i> Sair</a></li>
        </ul>
    </nav>

    <div class="content">
        <div class="container-fluid">

            <div class="header-actions mb-4">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-dark">Meu Perfil</h1>
                    <p class="text-muted mb-0">Faça a gestão dos seus dados pessoais e de segurança.</p>
                </div>
            </div>

            <?php if(!empty($mensagem_sucesso)): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $mensagem_sucesso ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($mensagem_erro)): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-3 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $mensagem_erro ?>
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
                            <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($perfil['nome'] ?? 'Colaborador') ?></h4>
                            <span class="badge bg-light text-muted border px-3 py-2 rounded-pill fw-medium">Colaborador</span>
                        </div>

                        <form method="POST" action="">
                            <input type="hidden" name="acao" value="atualizar_dados">
                            
                            <div class="mb-3">
                                <label class="form-label small text-muted text-uppercase fw-semibold">Nome Completo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                                    <input type="text" name="nome" class="form-control border-start-0 ps-0 bg-light" 
                                           value="<?= htmlspecialchars($perfil['nome'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted text-uppercase fw-semibold">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control border-start-0 ps-0 bg-light" 
                                           value="<?= htmlspecialchars($perfil['email'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-muted text-uppercase fw-semibold">Telemóvel</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-phone text-muted"></i></span>
                                    <input type="text" name="telefone" class="form-control border-start-0 ps-0 bg-light" 
                                           value="<?= htmlspecialchars($perfil['telefone'] ?? '') ?>" required
                                           pattern="(\+351)?(2\d{8}|9[1236]\d{7})">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label small text-muted text-uppercase fw-semibold">NIF</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-card-heading text-muted"></i></span>
                                    <input type="text" name="nif" class="form-control border-start-0 ps-0 bg-light" 
                                           value="<?= htmlspecialchars($perfil['nif'] ?? '') ?>" required
                                           pattern="[1-9]\d{8}">
                                </div>
                            </div>

                            <div class="d-grid mt-2">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="bi bi-floppy me-2"></i>Guardar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card-custom">
                        <div class="section-title text-start d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock" style="color: var(--primary-color);"></i> Segurança da Conta
                        </div>

                        <div class="alert border-0 d-flex align-items-start mb-4 mt-3" style="background-color: #fff8e1; color: #f57f17; border-radius: 12px;">
                            <i class="bi bi-info-circle-fill me-3 mt-1 fs-5"></i>
                            <div class="small">
                                A sua nova palavra-passe deve conter pelo menos <strong>8 caracteres</strong>, incluindo uma <strong>letra maiúscula</strong>, uma <strong>letra minúscula</strong> e um <strong>número</strong>.
                            </div>
                        </div>
                        
                        <form method="POST" action="" id="formSenha">
                            <input type="hidden" name="acao" value="alterar_senha">

                            <div class="mb-4">
                                <label class="form-label small text-muted text-uppercase fw-semibold">Palavra-passe Atual</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-key text-muted"></i></span>
                                    <input type="password" name="senha_atual" class="form-control border-start-0 ps-0 bg-light" required placeholder="Digite a sua senha atual">
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label small text-muted text-uppercase fw-semibold">Nova Palavra-passe</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                                        <input type="password" name="nova_senha" id="nova_senha" class="form-control border-start-0 ps-0 bg-light" 
                                               required placeholder="Mínimo 8 caracteres"
                                               pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label small text-muted text-uppercase fw-semibold">Confirmar Nova</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-check-lg text-muted"></i></span>
                                        <input type="password" name="confirmar_senha" id="confirmar_senha" class="form-control border-start-0 ps-0 bg-light" required placeholder="Repita a nova senha">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-outline-primary-custom w-100 d-md-inline-block w-md-auto">
                                    <i class="bi bi-shield-check me-2"></i>Atualizar Palavra-passe
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

        // Validação front-end das senhas
        const formSenha = document.getElementById('formSenha');
        const novaSenha = document.getElementById('nova_senha');
        const confSenha = document.getElementById('confirmar_senha');

        if(formSenha) {
            formSenha.addEventListener('submit', function(e) {
                if(novaSenha.value !== confSenha.value) {
                    e.preventDefault();
                    alert('As novas palavras-passe não coincidem. Por favor, verifique.');
                }
            });
        }
    </script>
</body>
</html>