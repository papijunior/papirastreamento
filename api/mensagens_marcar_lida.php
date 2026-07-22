<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireLoginApi();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Método não permitido.']);
    exit;
}

try {
    $raw = file_get_contents('php://input');
    $data = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    $deId = isset($data['de_usuario_id']) ? (int) $data['de_usuario_id'] : null;
    if ($deId !== null && $deId <= 0) {
        $deId = null;
    }

    $db = (new Database())->getConnection();
    $msgs = new MessageRepository($db);
    $msgs->ensureSchema();

    $inbound = $msgs->markRead((int) $user['id'], $deId);
    $wa = new WhatsAppClient();
    $marcadasZap = 0;
    foreach ($inbound as $wid) {
        if ($wa->markInboundRead($wid)) {
            $marcadasZap++;
        }
    }

    echo json_encode([
        'status' => 'ok',
        'nao_lidas' => $msgs->countUnread((int) $user['id']),
        'whatsapp_lidas' => $marcadasZap,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
