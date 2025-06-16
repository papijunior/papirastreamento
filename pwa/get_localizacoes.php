<?php
// get_localizacoes.php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=rastreamento;charset=utf8', 'rastreador', '123456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Seleciona a nova coluna 'endereco'
    $stmt = $pdo->query("SELECT usuario, ip, dispositivo, latitude, longitude, endereco, data_chegada, data_saida FROM localizacoes ORDER BY usuario, dispositivo, data_chegada ASC");
    $localizacoes = $stmt->fetchAll();

    echo json_encode($localizacoes);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro inesperado: ' . $e->getMessage()]);
}
?>