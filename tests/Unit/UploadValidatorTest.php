<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\UploadValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadValidatorTest extends TestCase
{
    /** @param array<string, mixed>|null $file */
    #[DataProvider('modelProvider')]
    public function testModelValidation(?array $file, ?string $expected): void
    {
        self::assertSame($expected, UploadValidator::validateModel($file));
    }

    /** @return array<string, array{array<string, mixed>|null, string|null}> */
    public static function modelProvider(): array
    {
        return [
            'valid glb' => [['name' => 'asset.glb', 'error' => UPLOAD_ERR_OK], null],
            'valid fbx' => [['name' => 'asset.FBX', 'error' => UPLOAD_ERR_OK], null],
            'missing upload' => [null, 'กรุณาเลือกไฟล์โมเดล'],
            'no file selected' => [['name' => '', 'error' => UPLOAD_ERR_NO_FILE], 'กรุณาเลือกไฟล์โมเดล'],
            'too large for the server' => [['name' => 'asset.glb', 'error' => UPLOAD_ERR_INI_SIZE], 'ไฟล์โมเดลมีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รองรับ'],
            'too large for the form' => [['name' => 'asset.glb', 'error' => UPLOAD_ERR_FORM_SIZE], 'ไฟล์โมเดลมีขนาดใหญ่เกินกว่าที่เซิร์ฟเวอร์รองรับ'],
            'partial upload' => [['name' => 'asset.glb', 'error' => UPLOAD_ERR_PARTIAL], 'อัปโหลดไฟล์โมเดลไม่สำเร็จ'],
            'disallowed extension' => [['name' => 'payload.php', 'error' => UPLOAD_ERR_OK], 'รองรับไฟล์ .glb .gltf .obj .fbx .stl .ply .dae .3ds เท่านั้น'],
        ];
    }

    /** @param array<string, mixed>|null $file */
    #[DataProvider('thumbnailProvider')]
    public function testThumbnailValidation(?array $file, ?string $expected): void
    {
        self::assertSame($expected, UploadValidator::validateThumbnail($file));
    }

    /** @return array<string, array{array<string, mixed>|null, string|null}> */
    public static function thumbnailProvider(): array
    {
        return [
            'valid image' => [['name' => 'preview.PNG', 'error' => UPLOAD_ERR_OK, 'size' => 1024], null],
            'thumbnail is optional' => [null, null],
            'no file selected' => [['name' => '', 'error' => UPLOAD_ERR_NO_FILE], null],
            'too large thumbnail' => [['name' => 'preview.png', 'error' => UPLOAD_ERR_OK, 'size' => UploadValidator::MAX_IMAGE_BYTES + 1], 'รูปภาพมีขนาดใหญ่เกินไป'],
            'too large for the server' => [['name' => 'preview.png', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0], 'รูปภาพมีขนาดใหญ่เกินไป'],
            'partial upload' => [['name' => 'preview.png', 'error' => UPLOAD_ERR_PARTIAL, 'size' => 10], 'อัปโหลดรูปภาพไม่สำเร็จ'],
            'disallowed thumbnail extension' => [['name' => 'payload.svg', 'error' => UPLOAD_ERR_OK, 'size' => 1024], 'รูปต้องเป็น .jpg .png หรือ .webp'],
        ];
    }

    public function testHasFile(): void
    {
        self::assertFalse(UploadValidator::hasFile(null));
        self::assertFalse(UploadValidator::hasFile(['error' => UPLOAD_ERR_NO_FILE]));
        self::assertTrue(UploadValidator::hasFile(['error' => UPLOAD_ERR_OK]));
        self::assertTrue(UploadValidator::hasFile(['error' => UPLOAD_ERR_PARTIAL]));
    }
}
