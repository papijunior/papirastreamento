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
    $input = $_POST;
}

try {
    $db = (new Database())->getConnection();
    $users = new UserRepository($db);
    $users->ensureSchema();

    $viewer = Auth::user();
    $novo = array_key_exists('compartilhando', $input)
        ? (bool) $input['compartilhando']
        : !Auth::compartilhando();

    $users->setCompartilhando($viewer['id'], $novo);
    $full = $users->find($viewer['id']);
    if ($full !== null) {
        Auth::refreshFromUser($full);
    }

    echo json_encode([
        'status' => 'sucesso',
        'compartilhando' => $novo,
        'mensagem' => $novo
            ? 'Compartilhamento de localização reativado.'
            : 'Compartilhamento de localização pausado.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'erro', 'mensagem' => $e->getMessage()]);
}
