<?php

declare(strict_types=1);

final class AudioUpload
{
    private const MAX_BYTES = 8_000_000;
    private const ALLOWED = [
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
    ];

    public static function uploadDir(): string
    {
        return dirname(__DIR__) . '/uploads/audios';
    }

    public static function ensureDir(): void
    {
        $dir = self::uploadDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * @param array<string, mixed>|null $file $_FILES['audio']
     */
    public static function store(?array $file): ?string
    {
        if ($file === null || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload do áudio.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('O áudio deve ter no máximo 8 MB.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';

        if (!isset(self::ALLOWED[$mime])) {
            // alguns browsers mandam application/octet-stream
            $nameHint = strtolower((string) ($file['name'] ?? ''));
            if (str_ends_with($nameHint, '.webm')) {
                $mime = 'audio/webm';
            } elseif (str_ends_with($nameHint, '.ogg')) {
                $mime = 'audio/ogg';
            } elseif (str_ends_with($nameHint, '.m4a')) {
                $mime = 'audio/mp4';
            } else {
                throw new RuntimeException('Formato de áudio não suportado.');
            }
        }

        self::ensureDir();
        $ext = self::ALLOWED[$mime];
        $name = 'a_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = self::uploadDir() . '/' . $name;

        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Não foi possível salvar o áudio.');
        }

        return 'uploads/audios/' . $name;
    }
}
