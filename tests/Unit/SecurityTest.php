<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Security;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    #[DataProvider('allowedEmailProvider')]
    public function testAllowedEmailValidation(string $email, bool $expected): void
    {
        self::assertSame($expected, Security::isAllowedEmail($email));
    }

    public static function allowedEmailProvider(): array
    {
        return [
            'gmail' => ['person@gmail.com', true],
            'hotmail in mixed case' => ['person@Hotmail.COM', true],
            'unsupported domain' => ['person@example.com', false],
            'malformed email' => ['not-an-email', false],
            'empty email' => ['', false],
        ];
    }

    public function testDetectsBcryptAndArgonHashes(): void
    {
        self::assertTrue(Security::isPasswordHash(password_hash('secret', PASSWORD_BCRYPT)));
        self::assertTrue(Security::isPasswordHash('$argon2id$v=19$m=65536,t=4,p=1$abc$def'));
        self::assertFalse(Security::isPasswordHash('plaintext-password'));
    }

    public function testCsrfValidationRequiresMatchingNonEmptyTokens(): void
    {
        self::assertTrue(Security::isValidCsrfToken('matching', 'matching'));
        self::assertFalse(Security::isValidCsrfToken('different', 'matching'));
        self::assertFalse(Security::isValidCsrfToken('', 'matching'));
        self::assertFalse(Security::isValidCsrfToken(null, 'matching'));
    }

    #[DataProvider('uploadExtensionProvider')]
    public function testUploadExtensionValidation(string $filename, string $kind, bool $expected): void
    {
        self::assertSame($expected, Security::isAllowedUploadExtension($filename, $kind));
    }

    public static function uploadExtensionProvider(): array
    {
        return [
            'glb model' => ['model.glb', 'model', true],
            'uppercase model' => ['model.OBJ', 'model', true],
            'php disguised as model' => ['shell.php', 'model', false],
            'jpg thumbnail' => ['preview.jpg', 'image', true],
            'svg thumbnail is rejected' => ['payload.svg', 'image', false],
        ];
    }

    #[DataProvider('storedFilenameProvider')]
    public function testStoredFilenameCannotTraverseDirectories(string $filename, bool $expected): void
    {
        self::assertSame($expected, Security::isSafeStoredFilename($filename));
    }

    public static function storedFilenameProvider(): array
    {
        return [
            'generated filename' => ['a1b2c3.glb', true],
            'nested directory' => ['uploads/a1b2c3.glb', false],
            'traversal' => ['../secrets.txt', false],
            'empty string' => ['', false],
        ];
    }
}
