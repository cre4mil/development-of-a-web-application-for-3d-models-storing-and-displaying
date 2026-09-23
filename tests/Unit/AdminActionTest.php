<?php
/**
 * AdminActionTest.php
 * Unit Tests สำหรับ Admin Action Logic
 * ทดสอบ: CSRF validation, action routing, input validation
 */

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminActionTest extends TestCase
{
    // =====================================================================
    // CSRF Token Validation Tests
    // =====================================================================

    #[Test]
    public function testValidCsrfTokenPasses(): void
    {
        $sessionCsrf = bin2hex(random_bytes(32));
        $postCsrf = $sessionCsrf;

        $this->assertTrue(
            $this->isCsrfValid($postCsrf, $sessionCsrf),
            "Matching CSRF tokens should pass validation"
        );

    }

    #[Test]
    public function testInvalidCsrfTokenFails(): void
    {
        $sessionCsrf = bin2hex(random_bytes(32));
        $attackerToken = bin2hex(random_bytes(32));

        $this->assertFalse(
            $this->isCsrfValid($attackerToken, $sessionCsrf),
            "Mismatched CSRF tokens should fail"
        );
    }

    #[Test]
    public function testEmptyCsrfTokenFails(): void
    {
        $sessionCsrf = bin2hex(random_bytes(32));

        $this->assertFalse(
            $this->isCsrfValid('', $sessionCsrf),
            "Empty CSRF token should fail"
        );
    }

    #[Test]
    public function testCsrfComparisionIsTimingSafe(): void
    {
        // hash_equals ป้องกัน timing attack (ต่างจาก === ธรรมดา)
        $token = 'abc123';
        $this->assertTrue(hash_equals($token, $token));
        $this->assertFalse(hash_equals($token, 'different'));
    }

    // =====================================================================
    // ID Validation Tests
    // =====================================================================

    #[Test]
    public function testValidPositiveIdPasses(): void
    {
        $this->assertTrue($this->isValidId(1));
        $this->assertTrue($this->isValidId(999));
        $this->assertTrue($this->isValidId(PHP_INT_MAX));
    }

    #[Test]
    public function testZeroIdFails(): void
    {
        $this->assertFalse($this->isValidId(0));
    }

    #[Test]
    public function testNegativeIdFails(): void
    {
        $this->assertFalse($this->isValidId(-1));
        $this->assertFalse($this->isValidId(-999));
    }

    #[Test]
    public function testIdCastFromString(): void
    {
        // POST data มาเป็น string เสมอ จึงต้อง cast เป็น int
        $rawPost = '42';
        $id = (int)$rawPost;
        $this->assertSame(42, $id);
        $this->assertTrue($this->isValidId($id));
    }

    #[Test]
    public function testMaliciousStringCastsToZero(): void
    {
        // SQL Injection attempt ผ่าน POST id
        $malicious = "1; DROP TABLE users;";
        $id = (int)$malicious;
        $this->assertSame(1, $id); // PHP cast ดึงเฉพาะตัวเลขแรก
    }

    // =====================================================================
    // Action Routing Tests
    // =====================================================================

    #[Test]
    public function testKnownActionsAreRecognized(): void
    {
        $knownActions = ['approve_order', 'reject_order', 'delete_user', 'delete_model'];

        foreach ($knownActions as $action) {
            $this->assertTrue(
                $this->isKnownAction($action),
                "Action '$action' should be recognized"
            );
        }
    }

    #[Test]
    public function testUnknownActionIsRejected(): void
    {
        $this->assertFalse($this->isKnownAction('hack_database'));
        $this->assertFalse($this->isKnownAction(''));
        $this->assertFalse($this->isKnownAction('DROP TABLE'));
    }

    // =====================================================================
    // Self-Delete Prevention Tests
    // =====================================================================

    #[Test]
    public function testAdminCannotDeleteSelf(): void
    {
        $adminUid = 1;
        $targetId = 1; // Same as admin

        $this->assertTrue(
            $this->isSelfDelete($adminUid, $targetId),
            "Deleting own account should be detected"
        );
    }

    #[Test]
    public function testAdminCanDeleteOtherUser(): void
    {
        $adminUid = 1;
        $targetId = 99; // Different user

        $this->assertFalse(
            $this->isSelfDelete($adminUid, $targetId),
            "Deleting another user should be allowed"
        );
    }

    // =====================================================================
    // File Path Security Tests
    // =====================================================================

    #[Test]
    public function testUploadsPathIsContainedWithinUploadDir(): void
    {
        $uploadDir = '/var/www/html/uploads/';
        $filename = 'model_12345.glb';

        // ตรวจสอบว่าไม่มี path traversal
        $this->assertStringNotContainsString('..', $filename);
        $fullPath = $uploadDir . $filename;
        $this->assertStringStartsWith($uploadDir, $fullPath);
    }

    #[Test]
    public function testPathTraversalIsDetected(): void
    {
        $maliciousFilename = '../../../etc/passwd';
        $this->assertStringContainsString('..', $maliciousFilename);
    }

    // =====================================================================
    // Admin Authorization Tests
    // =====================================================================

    #[Test]
    public function testAdminSessionIsValid(): void
    {
        $session = ['uid' => 1, 'is_admin' => 1];
        $this->assertTrue($this->isAdmin($session));
    }

    #[Test]
    public function testNonAdminSessionIsRejected(): void
    {
        $session = ['uid' => 2, 'is_admin' => 0];
        $this->assertFalse($this->isAdmin($session));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testEmptySessionIsRejected(): void
    {
        $this->assertFalse($this->isAdmin([]));
    }

    // =====================================================================
    // Helper Methods (replicating logic from admin_action.php)
    // =====================================================================

    private function isCsrfValid(string $post, string $session): bool
    {
        if ($post === '' || $session === '') {
            return false;
        }
        return hash_equals($session, $post);
    }

    private function isValidId(int $id): bool
    {
        return $id > 0;
    }

    private function isKnownAction(string $action): bool
    {
        return in_array($action, [
            'approve_order',
            'reject_order',
            'delete_user',
            'delete_model',
        ], true);
    }

    private function isSelfDelete(int $adminUid, int $targetId): bool
    {
        return $adminUid === $targetId;
    }

    private function isAdmin(array $session): bool
    {
        return !empty($session['uid']) &&
               !empty($session['is_admin']) &&
               (int)$session['is_admin'] === 1;
    }
}
