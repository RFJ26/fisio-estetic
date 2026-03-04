<?php
// Tenta ler as variáveis de ambiente da Vercel
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');
$db   = getenv('DB_NAME');
$port = getenv('DB_PORT') ?: '11494'; // Porta padrão do Aiven se não houver variável

try {
    // Usando mysqli conforme o seu erro indicou
    $conn = new mysqli($host, $user, $pass, $db, $port);

    if ($conn->connect_error) {
        throw new Exception("Falha na ligação: " . $conn->connect_error);
    }
    
    // Define o charset para evitar erros de acentuação
    $conn->set_charset("utf8mb4");

} catch (Exception $e) {
    // Em produção, é melhor logar o erro do que mostrar ao utilizador
    error_log($e->getMessage());
    die("Erro interno de ligação ao banco de dados.");
}