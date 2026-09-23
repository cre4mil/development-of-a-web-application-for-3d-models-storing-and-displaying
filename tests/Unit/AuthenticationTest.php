<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Authentication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticationTest extends TestCase
{
    #[DataProvider('registrationProvider')]
    public function testRegistrationValidation(string $username, string $email, string $password, string $confirmation, ?string $expected): void
    {
        self::assertSame($expected, Authentication::validateRegistration($username, $email, $password, $confirmation));
    }

    public static function registrationProvider(): array
    {
        return [
            'valid registration' => ['name', 'person@gmail.com', 'Secure123!', 'Secure123!', null],
            'missing value' => ['', 'person@gmail.com', 'Secure123!', 'Secure123!', 'กรอกข้อมูลไม่ครบ'],
            'password mismatch' => ['name', 'person@gmail.com', 'Secure123!', 'Mismatch!', 'ยืนยันรหัสผ่านไม่ตรงกัน'],
            'invalid email domain' => ['name', 'person@example.com', 'Secure123!', 'Secure123!', 'อนุญาตให้ใช้อีเมล @gmail.com หรือ @hotmail.com เท่านั้น'],
        ];
    }

    public function testPasswordVerificationSupportsSecureHashes(): void
    {
        $hash = password_hash('Secure123!', PASSWORD_DEFAULT);

        self::assertTrue(Authentication::verifyPassword('Secure123!', $hash));
        self::assertFalse(Authentication::verifyPassword('wrong-password', $hash));
    }

    public function testPasswordVerificationSupportsExistingLegacyAccountUntilMigrated(): void
    {
        self::assertTrue(Authentication::verifyPassword('legacy-password', 'legacy-password'));
        self::assertFalse(Authentication::verifyPassword('wrong-password', 'legacy-password'));
        self::assertFalse(Authentication::verifyPassword('', ''));
    }
}
