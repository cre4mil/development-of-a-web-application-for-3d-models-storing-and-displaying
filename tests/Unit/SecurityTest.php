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

    /** @return array<string, array{string, bool}> */
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
        self::assertFalse(Security::isValidCsrfToken('matching', null));
        self::assertFalse(Security::isValidCsrfToken('matching', ''));
    }

    #[DataProvider('uploadExtensionProvider')]
    public function testUploadExtensionValidation(string $filename, string $kind, bool $expected): void
    {
        self::assertSame($expected, Security::isAllowedUploadExtension($filename, $kind));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function uploadExtensionProvider(): array
    {
        return [
            'glb model' => ['model.glb', 'model', true],
            'uppercase model' => ['model.OBJ', 'model', true],
            'stl model' => ['part.stl', 'model', true],
            'php disguised as model' => ['shell.php', 'model', false],
            'blend is not viewable' => ['scene.blend', 'model', false],
            'jpg thumbnail' => ['preview.jpg', 'image', true],
            'webp thumbnail' => ['preview.webp', 'image', true],
            'svg thumbnail is rejected' => ['payload.svg', 'image', false],
        ];
    }

    #[DataProvider('storedFilenameProvider')]
    public function testStoredFilenameCannotTraverseDirectories(string $filename, bool $expected): void
    {
        self::assertSame($expected, Security::isSafeStoredFilename($filename));
    }

    /** @return array<string, array{string, bool}> */
    public static function storedFilenameProvider(): array
    {
        return [
            'generated filename' => ['a1b2c3.glb', true],
            'nested directory' => ['uploads/a1b2c3.glb', false],
            'traversal' => ['../secrets.txt', false],
            'empty string' => ['', false],
        ];
    }

    #[DataProvider('redirectProvider')]
    public function testRedirectTargetsMustBeLocalPages(string $target, string $expected): void
    {
        self::assertSame($expected, Security::safeRedirect($target));
    }

    /** @return array<string, array{string, string}> */
    public static function redirectProvider(): array
    {
        return [
            'plain page' => ['profile.php', 'profile.php'],
            'page with query' => ['model.php?id=3&tab=a%20b', 'model.php?id=3&tab=a%20b'],
            'external URL' => ['https://evil.example/x.php', 'index.php'],
            'protocol relative' => ['//evil.example/x.php', 'index.php'],
            'traversal' => ['../admin.php', 'index.php'],
            'script injection' => ['index.php?x=<script>', 'index.php'],
            'empty' => ['', 'index.php'],
        ];
    }

    public function testRedirectDefaultCanBeChanged(): void
    {
        self::assertSame('home.php', Security::safeRedirect('http://x', 'home.php'));
    }
}
