<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
  $pdo = new PDO('mysql:host=localhost;dbname=rastreamento;charset=utf8', 'rastreador', '123456');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $pdo->query("SELECT usuario, ip, dispositivo, latitude, longitude, data_chegada, data_saida FROM localizacoes ORDER BY data_chegada ASC");
  $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($dados);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['erro' => $e->getMessage()]);
}