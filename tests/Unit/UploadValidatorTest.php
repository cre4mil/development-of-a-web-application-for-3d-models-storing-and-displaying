<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UploadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadValidatorTest extends TestCase
{
    #[DataProvider('modelProvider')]
    public function testModelValidation(?array $file, ?string $expected): void
    {
        self::assertSame($expected, UploadValidator::validateModel($file));
    }

    public static function modelProvider(): array
    {
        return [
            'valid glb' => [['name' => 'asset.glb', 'error' => UPLOAD_ERR_OK], null],
            'missing upload' => [null, 'กรุณาตรวจสอบไฟล์โมเดล (ไม่พบไฟล์ หรือไม่ได้เลือกไฟล์)'],
            'too large' => [['name' => 'asset.glb', 'error' => UPLOAD_ERR_INI_SIZE], 'ไฟล์โมเดลมีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รองรับ'],
            'disallowed extension' => [['name' => 'payload.php', 'error' => UPLOAD_ERR_OK], 'ไฟล์โมเดลต้องเป็น .obj .glb หรือ .gltf'],
        ];
    }

    #[DataProvider('thumbnailProvider')]
    public function testThumbnailValidation(?array $file, ?string $expected): void
    {
        self::assertSame($expected, UploadValidator::validateThumbnail($file));
    }

    public static function thumbnailProvider(): array
    {
        return [
            'valid image' => [['name' => 'preview.PNG', 'error' => UPLOAD_ERR_OK, 'size' => 1024], null],
            'missing thumbnail' => [null, 'กรุณาอัปโหลดรูปภาพตัวอย่าง (Thumbnail)'],
            'too large thumbnail' => [['name' => 'preview.png', 'error' => UPLOAD_ERR_OK, 'size' => UploadValidator::MAX_IMAGE_BYTES + 1], 'รูปภาพมีขนาดใหญ่เกินไป'],
            'disallowed thumbnail extension' => [['name' => 'payload.svg', 'error' => UPLOAD_ERR_OK, 'size' => 1024], 'รูปต้องเป็น .jpg หรือ .png'],
        ];
    }
}
