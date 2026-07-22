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

    $uid = (int) $user['id'];
    $cursor = isset($_GET['after_id']) ? (int) $_GET['after_id'] : $msgs->getNotifyCursor($uid);
    $novas = $msgs->listNewForNotify($uid, $cursor);
    $naoLidas = $msgs->countUnread($uid);

    $maxId = $cursor;
    foreach ($novas as $n) {
        $maxId = max($maxId, (int) $n['id']);
    }
    if ($maxId > $cursor) {
        $msgs->setNotifyCursor($uid, $maxId);
    }

    echo json_encode([
        'status' => 'ok',
        'nao_lidas' => $naoLidas,
        'novas' => $novas,
        'cursor' => $maxId,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
