<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireLoginApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido.']);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'mensagem' => 'JSON inválido.']);
    exit;
}

$latitude = isset($input['latitude']) ? (float) $input['latitude'] : null;
$longitude = isset($input['longitude']) ? (float) $input['longitude'] : null;
$endereco = isset($input['endereco']) ? (string) $input['endereco'] : null;
$dispositivo = isset($input['dispositivo']) ? (string) $input['dispositivo'] : null;
$ip = $_SERVER['REMOTE_ADDR'] ?? null;

if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Latitude e longitude são obrigatórias.']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $users = new UserRepository($db);
    $users->ensureSchema();

    $viewer = Auth::user();
    $full = $users->find($viewer['id']);
    if ($full === null) {
        throw new RuntimeException('Usuário não encontrado.');
    }

    $escalas = new EscalaRepository($db);
    $compartilhando = !empty($full['compartilhando']);
    $participaEscala = !empty($full['participa_escala']);
    $tipo = UserTypes::normalize((string) $full['tipo']);

    if (!$escalas->podeRastrearAgora($viewer['id'], $tipo, $compartilhando, $participaEscala)) {
        $msg = !$compartilhando
            ? 'Compartilhamento de localização pausado.'
            : 'Fora da escala neste horário — localização não é compartilhada agora.';
        echo json_encode([
            'status' => 'ignorado',
            'mensagem' => $msg,
            'compartilhando' => $compartilhando,
            'participa_escala' => $participaEscala,
            'em_escala' => $participaEscala ? ($escalas->escalaAtivaAgora($viewer['id']) !== null) : null,
        ]);
        exit;
    }

    (new LocationRepository($db))->save(
        $viewer['id'],
        $latitude,
        $longitude,
        $endereco,
        is_string($ip) ? $ip : null,
        $dispositivo
    );

    echo json_encode([
        'status' => 'sucesso',
        'mensagem' => 'Localização salva.',
        'compartilhando' => true,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
