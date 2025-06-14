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

// Pega dados enviados por POST (ex: JSON ou form-data)
$input = json_decode(file_get_contents('php://input'), true);

// Validação básica
if (
    empty($input['usuario']) ||
    empty($input['ip']) ||
    !isset($input['latitude']) ||
    !isset($input['longitude'])
) {
    http_response_code(400);
    echo json_encode(['error' => 'Parâmetros insuficientes']);
    exit;
}

$usuario = $input['usuario'];
$ip = $input['ip'];
$latitude = $input['latitude'];
$longitude = $input['longitude'];

// Insere no banco
try {
    $stmt = $pdo->prepare('INSERT INTO localizacoes (usuario, ip, latitude, longitude) VALUES (?, ?, ?, ?)');
    $stmt->execute([$usuario, $ip, $latitude, $longitude]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao salvar: ' . $e->getMessage()]);
}