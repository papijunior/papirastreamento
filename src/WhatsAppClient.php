<?php

declare(strict_types=1);

/**
 * Cliente opcional da WhatsApp Cloud API.
 */
final class WhatsAppClient
{
    /** @var array<string, mixed> */
    private array $cfg;

    public function __construct(?array $cfg = null)
    {
        $path = dirname(__DIR__) . '/config/whatsapp.php';
        $this->cfg = $cfg ?? (is_file($path) ? require $path : []);
    }

    public function isEnabled(): bool
    {
        return !empty($this->cfg['enabled'])
            && trim((string) ($this->cfg['token'] ?? '')) !== ''
            && trim((string) ($this->cfg['phone_number_id'] ?? '')) !== '';
    }

    /**
     * Envia texto. Retorna wamid ou null.
     */
    public function sendText(string $telefoneE164Digits, string $texto): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $to = $this->normalizeTo($telefoneE164Digits);
        if ($to === null) {
            return null;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $texto],
        ];

        return $this->postMessage($payload);
    }

    /**
     * Envia áudio por URL pública (HTTPS). Retorna wamid ou null.
     */
    public function sendAudioByUrl(string $telefoneE164Digits, string $audioUrl): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $to = $this->normalizeTo($telefoneE164Digits);
        if ($to === null || $audioUrl === '') {
            return null;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'audio',
            'audio' => ['link' => $audioUrl],
        ];

        return $this->postMessage($payload);
    }

    /**
     * Marca mensagem inbound (cliente → empresa) como lida no WhatsApp.
     * Não limpa unread no celular do destinatário de mensagens outbound.
     */
    public function markInboundRead(string $waMessageId): bool
    {
        if (!$this->isEnabled() || $waMessageId === '') {
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
        ];

        $id = $this->postMessage($payload, false);
        return $id !== null || $this->lastHttpOk;
    }

    private bool $lastHttpOk = false;

    /**
     * @param array<string, mixed> $payload
     */
    private function postMessage(array $payload, bool $expectMessageId = true): ?string
    {
        $phoneId = rawurlencode((string) $this->cfg['phone_number_id']);
        $version = (string) ($this->cfg['graph_version'] ?? 'v21.0');
        $url = "https://graph.facebook.com/{$version}/{$phoneId}/messages";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . (string) $this->cfg['token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->lastHttpOk = $code >= 200 && $code < 300;
        if (!$this->lastHttpOk || !is_string($raw)) {
            return null;
        }

        if (!$expectMessageId) {
            return 'ok';
        }

        $data = json_decode($raw, true);
        $wamid = $data['messages'][0]['id'] ?? null;
        return is_string($wamid) && $wamid !== '' ? $wamid : null;
    }

    private function normalizeTo(string $digits): ?string
    {
        $d = preg_replace('/\D+/', '', $digits) ?? '';
        if ($d === '') {
            return null;
        }
        if (!str_starts_with($d, '55') && strlen($d) <= 11) {
            $d = '55' . $d;
        }
        return $d;
    }

    public static function waMeUrl(string $telefone, string $texto): ?string
    {
        $d = preg_replace('/\D+/', '', $telefone) ?? '';
        if ($d === '') {
            return null;
        }
        if (!str_starts_with($d, '55')) {
            $d = '55' . $d;
        }
        return 'https://wa.me/' . $d . '?text=' . rawurlencode($texto);
    }
}
