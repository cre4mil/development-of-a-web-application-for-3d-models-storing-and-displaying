<?php

declare(strict_types=1);

namespace App\Services;

// ตรวจไฟล์โมเดลและรูปปกที่อัปโหลด
final class UploadValidator
{
    public const MAX_IMAGE_BYTES = 5_242_880;

    // ตรวจไฟล์โมเดล: ต้องมี นามสกุลถูก และไม่ error
    /** @param array{name?: string, error?: int, size?: int}|null $file */
    public static function validateModel(?array $file): ?string
    {
        $error = null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = 'กรุณาเลือกไฟล์โมเดล';
        } elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            $error = 'ไฟล์โมเดลมีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รองรับ';
        } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'อัปโหลดไฟล์โมเดลไม่สำเร็จ';
        } elseif (!Security::isAllowedUploadExtension((string) ($file['name'] ?? ''), 'model')) {
            $error = 'รองรับไฟล์ ' . self::modelFormats() . ' เท่านั้น';
        }

        return $error;
    }

    // ตรวจรูปปก: ไม่ใส่ก็ได้ แต่ถ้าใส่ต้องเป็นรูปที่ถูกต้อง
    /**
     * Thumbnails are optional: a missing file is valid, a broken one is not.
     *
     * @param array{name?: string, error?: int, size?: int}|null $file
     */
    public static function validateThumbnail(?array $file): ?string
    {
        $error = null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = null;
        } elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || (int) ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            $error = 'รูปภาพมีขนาดใหญ่เกินไป';
        } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'อัปโหลดรูปภาพไม่สำเร็จ';
        } elseif (!Security::isAllowedUploadExtension((string) ($file['name'] ?? ''), 'image')) {
            $error = 'รูปต้องเป็น .jpg .png หรือ .webp';
        }

        return $error;
    }

    // เช็กว่ามีการเลือกไฟล์มา
    public static function hasFile(?array $file): bool
    {
        return $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    // รายการนามสกุลไฟล์โมเดลที่รองรับ (ใช้ใน accept ของ input)
    public static function modelFormats(): string
    {
        return '.' . implode(' .', Security::MODEL_EXTENSIONS);
    }
}
