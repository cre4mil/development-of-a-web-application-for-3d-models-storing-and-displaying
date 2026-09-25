<?php

declare(strict_types=1);

namespace App\Services;

final class Security
{
    /** Formats the browser viewer can render directly. */
    public const MODEL_EXTENSIONS = ['glb', 'gltf', 'obj', 'fbx', 'stl', 'ply', 'dae', '3ds'];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private const ALLOWED_EMAIL_DOMAINS = ['gmail.com', 'hotmail.com'];

    public static function isAllowedEmail(string $email): bool
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = strtolower((string) substr(strrchr($email, '@'), 1));
        return in_array($domain, self::ALLOWED_EMAIL_DOMAINS, true);
    }

    public static function isPasswordHash(string $value): bool
    {
        return preg_match('/^\$(2y|argon2(id|i))\$/', $value) === 1;
    }

    public static function isValidCsrfToken(?string $submitted, ?string $sessionToken): bool
    {
        return $submitted !== null
            && $sessionToken !== null
            && $submitted !== ''
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submitted);
    }

    public static function isAllowedUploadExtension(string $filename, string $kind): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = $kind === 'model' ? self::MODEL_EXTENSIONS : self::IMAGE_EXTENSIONS;

        return in_array($extension, $allowedExtensions, true);
    }

    public static function isSafeStoredFilename(string $filename): bool
    {
        return $filename !== ''
            && basename($filename) === $filename
            && !str_contains($filename, "\0")
            && !str_contains($filename, '..');
    }

    /**
     * Accepts only same-site page paths such as "model.php?id=3" to prevent open redirects.
     */
    public static function safeRedirect(string $target, string $default = 'index.php'): string
    {
        return preg_match('/^[A-Za-z0-9_\-]+\.php(\?[A-Za-z0-9_=&%.\-\[\]]*)?$/', $target) === 1 ? $target : $default;
    }
}
