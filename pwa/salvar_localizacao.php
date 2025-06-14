<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['usuario'], $input['ip'], $input['latitude'], $input['longitude'], $input['dispositivo'])) {
  http_response_code(400);
  echo json_encode(['erro' => 'Dados inválidos']);
  exit;
}

$usuario = $input['usuario'];
$ip = $input['ip'];
$lat = floatval($input['latitude']);
$lng = floatval($input['longitude']);
$disp = $input['dispositivo'];

try {
  $pdo = new PDO('mysql:host=localhost;dbname=rastreamento;charset=utf8', 'rastreador', '123456');
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Pega a última localização desse usuário e dispositivo
  $stmt = $pdo->prepare("SELECT id, latitude, longitude FROM localizacoes WHERE usuario = ? AND dispositivo = ? ORDER BY id DESC LIMIT 1");
  $stmt->execute([$usuario, $disp]);
  $ultima = $stmt->fetch(PDO::FETCH_ASSOC);

  $agora = date('Y-m-d H:i:s');

  if ($ultima) {
    $latAnt = floatval($ultima['latitude']);
    $lngAnt = floatval($ultima['longitude']);

    $distancia = sqrt(pow($latAnt - $lat, 2) + pow($lngAnt - $lng, 2));

    if ($distancia < 0.00001) {
      echo json_encode(['status' => 'repetida', 'msg' => 'Localização igual à última. Não salva.']);
      exit;
    }

    $upd = $pdo->prepare("UPDATE localizacoes SET data_saida = ? WHERE id = ?");
    $upd->execute([$agora, $ultima['id']]);
  }

  try {
    //$ins = $pdo->prepare("INSERT INTO localizacoes (usuario, ip, dispositivo, latitude, longitude, data_chegada) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$ins->execute([$usuario, $ip, $disp, $lat, $lng, $agora])) {
        $errorInfo = $ins->errorInfo();
        throw new Exception("Erro ao inserir localização: " . $errorInfo[2]);
    }

    $ins->execute([$usuario, $ip, $disp, $lat, $lng, $agora]);
  } catch (\Throwable $th) {
    echo json_encode(['erro' => 'Erro ao executar SQL: ' . $e->getMessage()]);
  }


  echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['erro' => $e->getMessage()]);
}