<?php
session_start();
include('../src/conexao.php'); // Ajusta o caminho da conexão se for preciso

if (isset($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    
    // Procura o cliente com este token
    $query = "SELECT id FROM cliente WHERE token_verificacao = '$token' LIMIT 1";
    $resultado = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($resultado) > 0) {
        // Encontrou! Atualiza para verificado (1) e limpa o token
        $update = "UPDATE cliente SET email_verificado = 1, token_verificacao = NULL WHERE token_verificacao = '$token'";
        
        if(mysqli_query($conn, $update)) {
            echo "<script>
                    alert('Email validado com sucesso! Já pode fazer login.');
                    window.location.href = 'index.php';
                  </script>";
            exit();
        } else {
            echo "<script>
                    alert('Erro ao validar a conta. Tente novamente.');
                    window.location.href = 'index.php';
                  </script>";
            exit();
        }
    } else {
        echo "<script>
                alert('Este link é inválido ou a conta já foi validada.');
                window.location.href = 'index.php';
              </script>";
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
?>