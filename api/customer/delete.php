<?php
session_start();


include __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/../../src/conexao.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $query = "DELETE FROM cliente WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Cliente eliminado com sucesso!'); window.location.href = 'list.php';</script>";
    } else {
        echo "<script>alert('Erro: Não é possível eliminar este cliente. Verifique se existem marcações associadas.'); window.location.href = 'list.php';</script>";
    }

    mysqli_stmt_close($stmt);
} else {
    header("Location: list.php");
}
?>