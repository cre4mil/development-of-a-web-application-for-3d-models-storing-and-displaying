<?php

declare(strict_types=1);

namespace App\Services;

final class Authentication
{
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

    public static function verifyPassword(string $password, string $storedHash): bool
    {
        if (Security::isPasswordHash($storedHash)) {
            return password_verify($password, $storedHash);
        }

        return $storedHash !== '' && hash_equals($storedHash, $password);
    }
}
