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
    $db = (new Database())->getConnection();
    $users = new UserRepository($db);
    $msgs = new MessageRepository($db);
    $msgs->ensureSchema();

    $paraId = (int) ($_POST['para_usuario_id'] ?? 0);
    $texto = trim((string) ($_POST['texto'] ?? ''));
    $audioFile = $_FILES['audio'] ?? null;

    if ($paraId <= 0) {
        throw new RuntimeException('Destinatário inválido.');
    }
    if ($paraId === (int) $user['id']) {
        throw new RuntimeException('Não é possível enviar mensagem para si mesmo.');
    }

    $dest = $users->find($paraId);
    if ($dest === null || empty($dest['ativo'])) {
        throw new RuntimeException('Usuário destinatário não encontrado.');
    }

    // mesma empresa (quando ambos têm empresa)
    $empMe = $user['empresa_id'] ?? null;
    $empDest = isset($dest['empresa_id']) ? (int) $dest['empresa_id'] : null;
    if ($empMe && $empDest && (int) $empMe !== $empDest) {
        throw new RuntimeException('Destinatário de outra empresa.');
    }

    if (!$users->canViewUser((int) $user['id'], Auth::podeVerTodosDaEmpresa(), $paraId)) {
        throw new RuntimeException('Você só pode enviar mensagem a usuários dos seus grupos.');
    }

    $temAudio = is_array($audioFile) && (int) ($audioFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($texto === '' && !$temAudio) {
        throw new RuntimeException('Digite um texto ou grave um áudio.');
    }

    $tipo = $temAudio ? 'audio' : 'texto';
    $audioPath = $temAudio ? AudioUpload::store($audioFile) : null;
    $corpo = $texto !== '' ? $texto : ($temAudio ? '[Áudio]' : null);

    $wa = new WhatsAppClient();
    $waMessageId = null;
    $waEnviado = false;
    $waMeUrl = null;

    $telefone = (string) ($dest['telefone'] ?? '');
    $nomeDe = (string) (($user['nome'] ?? null) ?: $user['usuario']);
    $preview = $tipo === 'audio'
        ? "{$nomeDe} enviou um áudio pelo PAPI Rastro."
        : "{$nomeDe}: {$texto}";

    if ($telefone !== '') {
        if ($wa->isEnabled()) {
            if ($tipo === 'texto') {
                $waMessageId = $wa->sendText($telefone, $preview);
                $waEnviado = $waMessageId !== null;
            } else {
                // URL pública do áudio (precisa HTTPS acessível pela Meta)
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
                $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
                $audioUrl = $scheme . '://' . $host . $base . '/' . ltrim((string) $audioPath, '/');
                if (str_starts_with($scheme, 'https')) {
                    $waMessageId = $wa->sendAudioByUrl($telefone, $audioUrl);
                    $waEnviado = $waMessageId !== null;
                    if (!$waEnviado) {
                        // fallback: avisa por texto + link
                        $waMessageId = $wa->sendText($telefone, $preview . "\n" . $audioUrl);
                        $waEnviado = $waMessageId !== null;
                    }
                } else {
                    $waMeUrl = WhatsAppClient::waMeUrl($telefone, $preview);
                }
            }
        } else {
            $waMeUrl = WhatsAppClient::waMeUrl(
                $telefone,
                $tipo === 'audio' ? $preview . ' (abra o PAPI Rastro para ouvir)' : $preview
            );
        }
    }

    $msg = $msgs->create(
        $empMe ? (int) $empMe : ($empDest ?: null),
        (int) $user['id'],
        $paraId,
        $tipo,
        $corpo,
        $audioPath,
        $waMessageId,
        $waEnviado
    );

    echo json_encode([
        'status' => 'ok',
        'mensagem' => $msg,
        'whatsapp' => [
            'enviado_api' => $waEnviado,
            'abrir_url' => $waMeUrl,
            'api_ativa' => $wa->isEnabled(),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
