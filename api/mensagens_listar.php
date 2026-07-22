<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireLoginApi();
$user = Auth::user();

try {
    $db = (new Database())->getConnection();
    $msgs = new MessageRepository($db);
    $msgs->ensureSchema();

    $com = (int) ($_GET['com'] ?? 0);
    if ($com > 0) {
        $thread = $msgs->listThread((int) $user['id'], $com);
        // ao abrir a conversa, marca como lida
        $inbound = $msgs->markRead((int) $user['id'], $com);
        $wa = new WhatsAppClient();
        $marcadasZap = 0;
        foreach ($inbound as $wid) {
            if ($wa->markInboundRead($wid)) {
                $marcadasZap++;
            }
        }
        echo json_encode([
            'status' => 'ok',
            'mensagens' => $thread,
            'whatsapp_lidas' => $marcadasZap,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'threads' => $msgs->listThreads((int) $user['id']),
        'nao_lidas' => $msgs->countUnread((int) $user['id']),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
