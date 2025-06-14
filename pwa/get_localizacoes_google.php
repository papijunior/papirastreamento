<?php
// Configurações do banco
$host = 'localhost';
$db   = 'rastreamento';
$user = 'rastreador';
$pass = '123456';
$charset = 'utf8mb4';

// Conexão PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na conexão com banco: ' . $e->getMessage()]);
    exit;
}

// Consulta todas as localizações
try {
    $stmt = $pdo->query('SELECT id, usuario, ip, latitude, longitude, data_registro FROM localizacoes ORDER BY data_registro DESC');
    $localizacoes = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($localizacoes);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro na consulta: ' . $e->getMessage()]);
}
