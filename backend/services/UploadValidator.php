<?php

declare(strict_types=1);

namespace App\Services;

final class UploadValidator
{
    public const MAX_IMAGE_BYTES = 5_242_880;

    /** @param array{name?: string, error?: int, size?: int}|null $file */
    public static function validateModel(?array $file): ?string
    {
        $error = null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = 'กรุณาตรวจสอบไฟล์โมเดล (ไม่พบไฟล์ หรือไม่ได้เลือกไฟล์)';
        } elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $error = 'ไฟล์โมเดลมีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รองรับ';
        } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'อัปโหลดไฟล์โมเดลไม่สำเร็จ';
        } elseif (!Security::isAllowedUploadExtension((string) ($file['name'] ?? ''), 'model')) {
            $error = 'ไฟล์โมเดลต้องเป็น .obj .glb หรือ .gltf';
        }

        return $error;
    }

    /** @param array{name?: string, error?: int, size?: int}|null $file */
    public static function validateThumbnail(?array $file): ?string
    {
        $error = null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = 'กรุณาอัปโหลดรูปภาพตัวอย่าง (Thumbnail)';
        } elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || (int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            $error = 'รูปภาพมีขนาดใหญ่เกินไป';
        } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'อัปโหลดรูปภาพไม่สำเร็จ';
        } elseif (!Security::isAllowedUploadExtension((string) ($file['name'] ?? ''), 'image')) {
            $error = 'รูปต้องเป็น .jpg หรือ .png';
        }

        return $error;
    }
}
