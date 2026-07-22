<?php

declare(strict_types=1);

final class PhotoUpload
{
    private const MAX_BYTES = 2_500_000;
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function uploadDir(): string
    {
        return dirname(__DIR__) . '/uploads/fotos';
    }

    public static function ensureDir(): void
    {
        $dir = self::uploadDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * @param array<string, mixed>|null $file $_FILES['foto']
     */
    public static function store(?array $file, ?string $oldRelativePath = null): ?string
    {
        if ($file === null || !isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return $oldRelativePath;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload da foto.');
        }

        if ((int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('A foto deve ter no máximo 2,5 MB.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';

        if (!isset(self::ALLOWED[$mime])) {
            throw new RuntimeException('Use foto JPG, PNG ou WEBP.');
        }

        self::ensureDir();
        $ext = self::ALLOWED[$mime];
        $name = 'u_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = self::uploadDir() . '/' . $name;

        if (!move_uploaded_file($tmp, $dest)) {
            throw new RuntimeException('Não foi possível salvar a foto.');
        }

        if ($oldRelativePath !== null && $oldRelativePath !== '') {
            self::delete($oldRelativePath);
        }

        return 'uploads/fotos/' . $name;
    }

    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (!preg_match('#^uploads/fotos/[a-zA-Z0-9._-]+$#', $relativePath)) {
            return;
        }

        $full = dirname(__DIR__) . '/' . $relativePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
