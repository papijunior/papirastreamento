<?php
// salvar_localizacao.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Verifica se os dados necessários foram recebidos
if (isset($data['usuario']) && isset($data['ip']) && isset($data['latitude']) && isset($data['longitude']) && isset($data['dispositivo']) && isset($data['endereco'])) {
    $usuario = $data['usuario'];
    $ip = $data['ip'];
    $latitude = $data['latitude'];
    $longitude = $data['longitude'];
    $dispositivo = $data['dispositivo'];
    $endereco = $data['endereco']; // <--- Nova variável para o endereço

    try {
        // Ajuste conforme suas credenciais
        $pdo = new PDO('mysql:host=localhost;dbname=rastreamento;charset=utf8', 'rastreador', '123456');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Prepara a query de inserção com a nova coluna 'endereco'
        $stmt = $pdo->prepare("INSERT INTO localizacoes (usuario, ip, latitude, longitude, endereco, dispositivo, data_chegada) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        
        // Executa a query
        $stmt->execute([$usuario, $ip, $latitude, $longitude, $endereco, $dispositivo]); // <--- Inclui o endereço aqui

        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Localização salva com sucesso!']);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro de banco de dados: ' . $e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro inesperado: ' . $e->getMessage()]);
    }

} else {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'erro', 'mensagem' => 'Dados incompletos recebidos.']);
}
?>