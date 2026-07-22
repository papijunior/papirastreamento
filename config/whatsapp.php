<?php

declare(strict_types=1);

/**
 * WhatsApp Cloud API (Meta).
 * Sem estas chaves: o app grava a mensagem e abre o WhatsApp (texto via wa.me).
 * Com chaves: envia texto/áudio direto e pode marcar inbound como lida.
 *
 * Obtenha em: Meta Developers → WhatsApp → API Setup
 */
return [
    'enabled' => false,
    'token' => getenv('PAPI_WA_TOKEN') ?: '',
    'phone_number_id' => getenv('PAPI_WA_PHONE_ID') ?: '',
    'verify_token' => getenv('PAPI_WA_VERIFY') ?: 'papi-rastro-verify',
    'graph_version' => 'v21.0',
];
