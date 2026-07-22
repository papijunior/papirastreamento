<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

Auth::requireLoginApi();

try {
    $db = (new Database())->getConnection();
    $users = new UserRepository($db);
    $users->ensureSchema();
    $grupos = new GroupRepository($db);
    $escalas = new EscalaRepository($db);

    $viewer = Auth::user();
    $full = $users->find($viewer['id']);
    $ids = $users->visibleUserIds($viewer['id'], Auth::podeVerTodosDaEmpresa());

    $grupoId = isset($_GET['grupo_id']) ? (int) $_GET['grupo_id'] : 0;
    $grupoModo = GroupModes::SOCIAL;
    $grupoNome = null;

    if ($grupoId > 0) {
        $permitidos = $grupos->listGroupsForViewer($viewer['id'], Auth::podeVerTodosDaEmpresa());
        $ok = false;
        foreach ($permitidos as $g) {
            if ((int) $g['id'] === $grupoId) {
                $ok = true;
                $grupoModo = GroupModes::isValid((string) ($g['modo'] ?? ''))
                    ? (string) $g['modo']
                    : GroupModes::SOCIAL;
                $grupoNome = (string) $g['nome'];
                break;
            }
        }
        if (!$ok) {
            http_response_code(403);
            echo json_encode(['erro' => 'Você não tem acesso a este grupo.']);
            exit;
        }
        $membros = $grupos->userIdsInGrupo($grupoId);
        $ids = array_values(array_intersect($ids, $membros));

        // Grupo tipo ronda: só quem está em escala no momento
        if ($grupoModo === GroupModes::RONDA) {
            $emEscala = $escalas->usuariosEmEscalaAgora($ids);
            $ids = array_values(array_intersect($ids, $emEscala));
        }
    }

    // Quem tem "participa de escala" só aparece no mapa se estiver escalado agora
    $ids = $users->filterIdsVisiveisNoMapa($ids, $escalas);

    $onlineMinutos = isset($_GET['online_minutos']) ? (int) $_GET['online_minutos'] : 3;
    if ($onlineMinutos <= 0) {
        $onlineMinutos = 3;
    }

    $locs = (new LocationRepository($db))->latestForUsers($ids, $viewer['id'], $onlineMinutos);

    echo json_encode([
        'viewer' => [
            'id' => $viewer['id'],
            'usuario' => $viewer['usuario'],
            'tipo' => UserTypes::normalize((string) ($viewer['tipo'] ?? UserTypes::USUARIO)),
            'perm_usuarios' => $viewer['perm_usuarios'],
            'compartilhando' => !empty($full['compartilhando']),
        ],
        'filtro' => [
            'grupo_id' => $grupoId > 0 ? $grupoId : null,
            'grupo_nome' => $grupoNome,
            'grupo_modo' => $grupoId > 0 ? $grupoModo : null,
            'online_minutos' => $onlineMinutos,
        ],
        'localizacoes' => $locs,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
