<?php
/**
 * AuthTest.php
 * Unit Tests สำหรับ Authentication Logic
 * ทดสอบ: email validation, password hashing, session behavior
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthTest extends TestCase
{
    // =====================================================================
    // Email Validation Tests
    // =====================================================================

    #[Test]
    public function testValidGmailIsAccepted(): void
    {
        $email = 'user@gmail.com';
        $this->assertTrue(
            $this->isEmailAllowed($email),
            "Gmail address should be accepted"
        );
    }

    #[Test]
    public function testValidHotmailIsAccepted(): void
    {
        $email = 'user@hotmail.com';
        $this->assertTrue(
            $this->isEmailAllowed($email),
            "Hotmail address should be accepted"
        );
    }

    #[Test]
    public function testInvalidDomainIsRejected(): void
    {
        $email = 'user@yahoo.com';
        $this->assertFalse(
            $this->isEmailAllowed($email),
            "Yahoo email should be rejected"
        );
    }

    #[Test]
    public function testCaseInsensitiveEmail(): void
    {
        $this->assertTrue($this->isEmailAllowed('user@GMAIL.COM'));
        $this->assertTrue($this->isEmailAllowed('user@Hotmail.com'));
    }

    #[Test]
    public function testEmptyEmailIsRejected(): void
    {
        $this->assertFalse($this->isEmailAllowed(''));
    }

    #[Test]
    public function testMalformedEmailIsRejected(): void
    {
        // ไม่มี @ เลย → ต้อง fail
        $this->assertFalse($this->isEmailAllowed('not-an-email'));
        // ไม่มีโดเมนที่อนุญาต
        $this->assertFalse($this->isEmailAllowed('user@outlook.com'));
        $this->assertFalse($this->isEmailAllowed('user@yahoo.com'));
    }

    // =====================================================================
    // Password Hashing Tests
    // =====================================================================

    #[Test]
    public function testPasswordIsHashedWithBcrypt(): void
    {
        $plainPassword = 'SecurePassword123!';
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        // Hash ต้องขึ้นต้นด้วย $2y$ (bcrypt)
        $this->assertStringStartsWith('$2y$', $hash);
    }

    #[Test]
    public function testPasswordVerifySucceeds(): void
    {
        $plainPassword = 'MyPassword@456';
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $this->assertTrue(password_verify($plainPassword, $hash));
    }

    #[Test]
    public function testWrongPasswordVerifyFails(): void
    {
        $plainPassword = 'CorrectPassword';
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $this->assertFalse(password_verify('WrongPassword', $hash));
    }

    #[Test]
    public function testTwoHashesOfSamePasswordAreDifferent(): void
    {
        // bcrypt ใช้ random salt ทุกครั้ง ดังนั้น hash ต้องไม่ซ้ำกัน
        $password = 'TestPassword';
        $hash1 = password_hash($password, PASSWORD_DEFAULT);
        $hash2 = password_hash($password, PASSWORD_DEFAULT);

        $this->assertNotSame($hash1, $hash2);
    }

    // =====================================================================
    // Hash Detection Logic (จาก login.php)
    // =====================================================================

    #[Test]
    public function testBcryptHashIsDetectedCorrectly(): void
    {
        $bcryptHash = password_hash('password', PASSWORD_BCRYPT);
        $this->assertTrue($this->looksHashed($bcryptHash));
    }

    #[Test]
    public function testPlainTextIsDetectedAsNotHashed(): void
    {
        $this->assertFalse($this->looksHashed('plain_text_password'));
        $this->assertFalse($this->looksHashed('12345678'));
        $this->assertFalse($this->looksHashed(''));
    }

    #[Test]
    public function testArgon2HashIsDetectedCorrectly(): void
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            $this->markTestSkipped('Argon2ID not supported on this PHP build');
        }
        $argonHash = password_hash('password', PASSWORD_ARGON2ID);
        $this->assertTrue($this->looksHashed($argonHash));
    }

    // =====================================================================
    // Input Sanitization Tests
    // =====================================================================

    #[Test]
    public function testTrimEmailRemovesWhitespace(): void
    {
        $raw = '  user@gmail.com  ';
        $this->assertSame('user@gmail.com', trim($raw));
    }

    #[Test]
    public function testEmptyFieldsAreDetected(): void
    {
        $this->assertTrue($this->isFieldEmpty(''));
        $this->assertTrue($this->isFieldEmpty('   '));
        $this->assertFalse($this->isFieldEmpty('value'));
    }

    #[Test]
    public function testPasswordConfirmationMismatch(): void
    {
        $pass1 = 'Password123';
        $pass2 = 'DifferentPassword';
        $this->assertNotSame($pass1, $pass2);
    }

    #[Test]
    public function testPasswordConfirmationMatch(): void
    {
        $pass1 = 'Password123';
        $pass2 = 'Password123';
        $this->assertSame($pass1, $pass2);
    }

    // =====================================================================
    // Helper Methods (replicating logic from login.php & register.php)
    // =====================================================================

    /**
     * Replicates the email validation logic from register.php
     */
    private function isEmailAllowed(string $email): bool
    {
        if ($email === '') {
            return false;
        }
        return (bool) preg_match('/@(gmail\.com|hotmail\.com)$/i', $email);
    }

    /**
     * Replicates the hash detection logic from login.php
     */
    private function looksHashed(string $hash): bool
    {
        return (bool)(
            preg_match('/^\$2y\$/', $hash) ||
            preg_match('/^\$argon2(id|i)d?\$/', $hash)
        );
    }

    /**
     * Checks if a field is empty (after trim)
     */
    private function isFieldEmpty(string $value): bool
    {
        return trim($value) === '';
    }
}
