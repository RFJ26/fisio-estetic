<?php
// src/send_email.php

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/helpers.php'; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviarEmailEstado($conexao, $idMarcacao, $novoEstado) {
    
    // 1. OBTER DADOS
    $consultaSQL = "SELECT 
                marcacao.id, 
                marcacao.data, 
                marcacao.slot_inicial, 
                cliente.nome AS nome_cliente, 
                cliente.email AS email_cliente, 
                servico.designacao AS nome_servico 
            FROM marcacao 
            INNER JOIN cliente ON marcacao.id_cliente = cliente.id 
            INNER JOIN servico_funcionario ON marcacao.id_servico_funcionario = servico_funcionario.id
            INNER JOIN servico ON servico_funcionario.id_servico = servico.id 
            WHERE marcacao.id = ?";

    $stmt = $conexao->prepare($consultaSQL);
    $stmt->bind_param("i", $idMarcacao);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows == 0) {
        return "Erro: Marcação não encontrada.";
    }

    $dados = $resultado->fetch_assoc();
    
    $emailDestino = $dados['email_cliente']; 
    $nomeDestino  = $dados['nome_cliente'];
    $servico      = $dados['nome_servico']; 
    
    setlocale(LC_TIME, 'pt_PT', 'pt_PT.utf-8', 'portuguese');
    $dataFormatada = date('d/m/Y', strtotime($dados['data']));
    $horaFormatada = converterSlotParaHora($dados['slot_inicial']);
    
    $urlLogotipo = "https://img.icons8.com/ios-filled/100/lotus.png"; 

    // --- CORES BASEADAS NO TEU CSS ---
    $corCabecalho = "#275a29"; 
    $corPrimary = "#4caf50"; 

    // Variáveis dinâmicas
    $corDestaqueTexto = $corPrimary; 
    $tituloEmail = "Confirmada";
    $icone = "✅";
    $estadoFormatado = ucfirst($novoEstado);

    switch (strtolower($novoEstado)) {
        case 'cancelada':
            $corDestaqueTexto = "#d32f2f"; // Vermelho
            $tituloEmail = "Cancelada";
            $icone = "❌";
            break;
        
        case 'realizada':
            $corDestaqueTexto = "#1976d2"; // Azul
            $tituloEmail = "Realizada";
            $icone = "✨";
            break;

        case 'por confirmar':
            $corDestaqueTexto = "#f57c00"; // Laranja
            $tituloEmail = "Pendente";
            $estadoFormatado = "Por Confirmar"; 
            $icone = "⏳";
            break;
            
        default:
            $tituloEmail = "Confirmada";
            $corDestaqueTexto = $corPrimary; // Verde brilhante
            break;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clinic.fisio.estetic@gmail.com'; 
        $mail->Password   = 'zyhd ljzl pzmx yxgu';     
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('clinic.fisio.estetic@gmail.com', 'FisioEstetic');
        $mail->addAddress($emailDestino, $nomeDestino);

        $mail->isHTML(true);
        $mail->Subject = "$tituloEmail - FisioEstetic";

        // HTML
        $corpoEmail = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { background-color: #f6f6f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; }
                .main-box { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background-color: $corCabecalho; padding: 30px; text-align: center; }
                .content { padding: 40px 30px; }
                .dynamic-title { color: $corDestaqueTexto; margin-top: 0; font-size: 24px; font-weight: 700; text-align: center; margin-bottom: 10px; }
                .intro-text { text-align: center; color: #555; font-size: 16px; line-height: 1.5; margin-bottom: 30px; }
                .clean-table { width: 100%; border-collapse: collapse; }
                .clean-table td { padding: 15px 0; border-bottom: 1px solid #eeeeee; font-size: 15px; color: #333; }
                .clean-table tr:last-child td { border-bottom: none; }
                .label-col { text-align: left; color: #888; font-weight: 500; }
                .value-col { text-align: right; font-weight: 600; color: #333; }
                .footer { background-color: #f6f6f6; padding: 20px; text-align: center; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div style='background-color: #f6f6f6; padding: 20px;'>
                <div class='main-box'>
                    <div class='header'>
                        <img src='$urlLogotipo' alt='FisioEstetic' width='80' style='display:block; margin: 0 auto; filter: brightness(0) invert(1);'>
                    </div>
                    <div class='content'>
                        <h2 class='dynamic-title'>$icone $tituloEmail</h2>
                        <p class='intro-text'>
                            Olá <strong>$nomeDestino</strong>,<br>
                            o estado da sua marcação foi atualizado.
                        </p>
                        <table class='clean-table'>
                            <tr><td class='label-col'>Serviço</td><td class='value-col'>$servico</td></tr>
                            <tr><td class='label-col'>Data</td><td class='value-col'>$dataFormatada</td></tr>
                            <tr><td class='label-col'>Hora</td><td class='value-col'>$horaFormatada</td></tr>
                            <tr>
                                <td class='label-col'>Estado</td>
                                <td class='value-col' style='color: $corDestaqueTexto; font-weight: bold;'>
                                    $estadoFormatado
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class='footer'>
                        &copy; 2026 FisioEstetic<br>Email automático.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body    = $corpoEmail;
        $mail->AltBody = strip_tags($corpoEmail);

        $mail->send();
        return true;

    } catch (Exception $e) {
        return "Erro ao enviar email: {$mail->ErrorInfo}";
    }
}

// ========================================================================
// NOVA FUNÇÃO: ENVIAR EMAIL DE RECUPERAÇÃO DE PALAVRA-PASSE
// ========================================================================
function enviarEmailRecuperacao($emailDestino, $nomeDestino, $linkRecuperacao) {
    
    $urlLogotipo = "https://img.icons8.com/ios-filled/100/lotus.png"; 
    $corCabecalho = "#275a29"; 
    $corPrimary = "#4caf50"; 

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clinic.fisio.estetic@gmail.com'; 
        $mail->Password   = 'zyhd ljzl pzmx yxgu';     
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('clinic.fisio.estetic@gmail.com', 'FisioEstetic');
        $mail->addAddress($emailDestino, $nomeDestino);

        $mail->isHTML(true);
        $mail->Subject = "Recuperacao de Palavra-Passe - FisioEstetic";

        // HTML do email com botão
        $corpoEmail = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { background-color: #f6f6f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; }
                .main-box { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background-color: $corCabecalho; padding: 30px; text-align: center; }
                .content { padding: 40px 30px; text-align: center; }
                .dynamic-title { color: #333; margin-top: 0; font-size: 22px; font-weight: 700; margin-bottom: 15px; }
                .intro-text { color: #555; font-size: 16px; line-height: 1.6; margin-bottom: 30px; }
                .btn-recover { display: inline-block; background-color: $corCabecalho; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 16px; }
                .footer { background-color: #f6f6f6; padding: 20px; text-align: center; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div style='background-color: #f6f6f6; padding: 20px;'>
                <div class='main-box'>
                    <div class='header'>
                        <img src='$urlLogotipo' alt='FisioEstetic' width='80' style='display:block; margin: 0 auto; filter: brightness(0) invert(1);'>
                    </div>
                    <div class='content'>
                        <h2 class='dynamic-title'>Recuperação de Palavra-passe</h2>
                        <p class='intro-text'>
                            Olá <strong>$nomeDestino</strong>,<br><br>
                            Recebemos um pedido para redefinir a palavra-passe da sua conta. Se não fez este pedido, pode ignorar este email de forma segura.<br><br>
                            Para criar uma nova palavra-passe, clique no botão abaixo:
                        </p>
                        
                        <a href='$linkRecuperacao' class='btn-recover' style='color:#ffffff;'>Redefinir Palavra-passe</a>
                        
                        <p class='intro-text' style='margin-top: 30px; font-size: 13px; color: #888;'>
                            Este link é válido por 1 hora.<br>
                            Se o botão não funcionar, copie e cole este link no seu navegador:<br>
                            <a href='$linkRecuperacao' style='color: $corPrimary; word-break: break-all;'>$linkRecuperacao</a>
                        </p>
                    </div>
                    <div class='footer'>
                        &copy; 2026 FisioEstetic<br>Email automático.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body    = $corpoEmail;
        $mail->AltBody = "Olá $nomeDestino. Para redefinir a sua senha, copie e cole o seguinte link no seu navegador: $linkRecuperacao";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}

// ========================================================================
// NOVA FUNÇÃO: ENVIAR EMAIL DE VALIDAÇÃO DE CONTA (REGISTO)
// ========================================================================
function enviarEmailValidacao($emailDestino, $nomeDestino, $linkValidacao) {
    
    $urlLogotipo = "https://img.icons8.com/ios-filled/100/lotus.png"; 
    $corCabecalho = "#275a29"; 
    $corPrimary = "#4caf50"; 

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'clinic.fisio.estetic@gmail.com'; 
        $mail->Password   = 'zyhd ljzl pzmx yxgu';     
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('clinic.fisio.estetic@gmail.com', 'FisioEstetic');
        $mail->addAddress($emailDestino, $nomeDestino);

        $mail->isHTML(true);
        $mail->Subject = "Valide a sua conta - FisioEstetic";

        // HTML do email com botão
        $corpoEmail = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { background-color: #f6f6f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; }
                .main-box { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
                .header { background-color: $corCabecalho; padding: 30px; text-align: center; }
                .content { padding: 40px 30px; text-align: center; }
                .dynamic-title { color: #333; margin-top: 0; font-size: 22px; font-weight: 700; margin-bottom: 15px; }
                .intro-text { color: #555; font-size: 16px; line-height: 1.6; margin-bottom: 30px; }
                .btn-recover { display: inline-block; background-color: $corCabecalho; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; font-size: 16px; }
                .footer { background-color: #f6f6f6; padding: 20px; text-align: center; font-size: 12px; color: #999; }
            </style>
        </head>
        <body>
            <div style='background-color: #f6f6f6; padding: 20px;'>
                <div class='main-box'>
                    <div class='header'>
                        <img src='$urlLogotipo' alt='FisioEstetic' width='80' style='display:block; margin: 0 auto; filter: brightness(0) invert(1);'>
                    </div>
                    <div class='content'>
                        <h2 class='dynamic-title'>Bem-vindo(a) à FisioEstetic!</h2>
                        <p class='intro-text'>
                            Olá <strong>$nomeDestino</strong>,<br><br>
                            Obrigado por criar uma conta connosco. Para garantir a segurança da sua conta e concluir o registo, precisamos que valide o seu endereço de email.<br><br>
                            Por favor, clique no botão abaixo:
                        </p>
                        
                        <a href='$linkValidacao' class='btn-recover' style='color:#ffffff;'>Validar a minha conta</a>
                        
                        <p class='intro-text' style='margin-top: 30px; font-size: 13px; color: #888;'>
                            Se o botão não funcionar, copie e cole este link no seu navegador:<br>
                            <a href='$linkValidacao' style='color: $corPrimary; word-break: break-all;'>$linkValidacao</a>
                        </p>
                    </div>
                    <div class='footer'>
                        &copy; 2026 FisioEstetic<br>Email automático.
                    </div>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->Body    = $corpoEmail;
        $mail->AltBody = "Olá $nomeDestino. Para validar a sua conta na FisioEstetic, copie e cole o seguinte link no seu navegador: $linkValidacao";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}
?>