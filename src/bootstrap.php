<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/UserTypes.php';
require_once __DIR__ . '/GroupModes.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/PhotoUpload.php';
require_once __DIR__ . '/AudioUpload.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/GroupRepository.php';
require_once __DIR__ . '/LocationRepository.php';
require_once __DIR__ . '/EmpresaRepository.php';
require_once __DIR__ . '/RegiaoRepository.php';
require_once __DIR__ . '/EscalaRepository.php';
require_once __DIR__ . '/MessageRepository.php';
require_once __DIR__ . '/WhatsAppClient.php';
require_once __DIR__ . '/nav.php';

function e(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
