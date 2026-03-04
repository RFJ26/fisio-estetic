<?php
session_start();


include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // SEGURANÇA: Impedir que o administrador apague a si próprio
    // Assume que o ID do utilizador logado está em $_SESSION['id'] (confirma no teu verifica_login.php)
    if (isset($_SESSION['id']) && $id == $_SESSION['id']) {
        echo "<script>alert('Erro: Não pode apagar a sua própria conta enquanto está ligado.'); window.location.href = 'list.php';</script>";
        exit;
    }

    // Tenta apagar o funcionário
    $query = "DELETE FROM funcionario WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Funcionário eliminado com sucesso!'); window.location.href = 'list.php';</script>";
    } else {
        // Se falhar (ex: tem marcações associadas e a BD bloqueia)
        echo "<script>alert('Erro: Não é possível eliminar este funcionário. Verifique se existem marcações ou dados associados.'); window.location.href = 'list.php';</script>";
    }

    mysqli_stmt_close($stmt);
} else {
    header("Location: list.php");
}
?>