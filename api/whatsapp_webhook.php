<?php

declare(strict_types=1);

/**
 * Webhook WhatsApp Cloud API.
 * GET: verificação | POST: mensagens inbound do Zap → grava no app.
 */
require_once dirname(__DIR__) . '/src/bootstrap.php';

$cfgPath = dirname(__DIR__) . '/config/whatsapp.php';
$cfg = is_file($cfgPath) ? require $cfgPath : [];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = (string) ($_GET['hub_mode'] ?? '');
    $token = (string) ($_GET['hub_verify_token'] ?? '');
    $challenge = (string) ($_GET['hub_challenge'] ?? '');
    $expected = (string) ($cfg['verify_token'] ?? 'papi-rastro-verify');

    if ($mode === 'subscribe' && hash_equals($expected, $token)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        echo json_encode(['status' => 'ignored']);
        exit;
    }

    $db = (new Database())->getConnection();
    $users = new UserRepository($db);
    $msgs = new MessageRepository($db);
    $msgs->ensureSchema();
    $users->ensureSchema();

    $entries = $payload['entry'] ?? [];
    foreach ($entries as $entry) {
        $changes = $entry['changes'] ?? [];
        foreach ($changes as $change) {
            $value = $change['value'] ?? [];
            $messages = $value['messages'] ?? [];
            foreach ($messages as $m) {
                $from = preg_replace('/\D+/', '', (string) ($m['from'] ?? '')) ?? '';
                $waId = (string) ($m['id'] ?? '');
                if ($from === '' || $waId === '') {
                    continue;
                }

                $remetente = $users->findByTelefoneDigits($from);
                if ($remetente === null) {
                    continue;
                }

                $tipoWa = (string) ($m['type'] ?? 'text');
                $corpo = null;
                $tipo = 'texto';
                $audioPath = null;

                if ($tipoWa === 'text') {
                    $corpo = trim((string) ($m['text']['body'] ?? ''));
                } elseif ($tipoWa === 'audio') {
                    $tipo = 'audio';
                    $corpo = '[Áudio via WhatsApp]';
                } else {
                    $corpo = '[Mensagem WhatsApp: ' . $tipoWa . ']';
                }

                if ($corpo === null || $corpo === '') {
                    continue;
                }

                // destinatário: último interlocutor no app, senão gestor da empresa
                $paraId = $msgs->sugerirDestinatarioResposta((int) $remetente['id']);
                if ($paraId === null) {
                    continue;
                }

                $msg = $msgs->create(
                    isset($remetente['empresa_id']) ? (int) $remetente['empresa_id'] : null,
                    (int) $remetente['id'],
                    $paraId,
                    $tipo,
                    $corpo,
                    $audioPath,
                    null,
                    false
                );
                $msgs->attachInboundWaId((int) $msg['id'], $waId);
            }
        }
    }

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(200); // Meta reenvia se 5xx
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
