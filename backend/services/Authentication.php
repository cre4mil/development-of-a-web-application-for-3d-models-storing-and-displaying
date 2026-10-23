<?php

declare(strict_types=1);

namespace App\Services;

// ตรวจข้อมูลสมัครสมาชิกและตรวจรหัสผ่าน
final class Authentication
{
    // ตรวจชื่อผู้ใช้ อีเมล รหัสผ่าน และยืนยันรหัสผ่าน คืนข้อความ error หรือ null ถ้าผ่าน
    public static function validateRegistration(string $username, string $email, string $password, string $confirmation): ?string
    {
        $error = null;
        if (trim($username) === '' || trim($email) === '' || $password === '' || $confirmation === '') {
            $error = 'กรอกข้อมูลไม่ครบ';
        } elseif ($password !== $confirmation) {
            $error = 'ยืนยันรหัสผ่านไม่ตรงกัน';
        } elseif (!Security::isAllowedEmail($email)) {
            $error = 'อนุญาตให้ใช้อีเมล @gmail.com หรือ @hotmail.com เท่านั้น';
        }

        return $error;
    }

    // เทียบรหัสผ่านกับค่าที่เก็บใน DB (รองรับทั้งแบบเข้ารหัสและข้อมูลเก่าที่ไม่ได้เข้ารหัส)
    public static function verifyPassword(string $password, string $storedHash): bool
    {
        if (Security::isPasswordHash($storedHash)) {
            return password_verify($password, $storedHash);
        }

        return $storedHash !== '' && hash_equals($storedHash, $password);
    }
}
