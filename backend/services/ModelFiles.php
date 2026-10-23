<?php

declare(strict_types=1);

namespace App\Services;

// จัดการไฟล์ในโฟลเดอร์ uploads/: บันทึก หา เช็กขนาด และลบ
/** Stores, locates and removes files inside the uploads directory. */
final class ModelFiles
{
    /** Model columns that reference a file on disk. */
    public const FILE_COLUMNS = ['filename', 'file_gltf', 'file_glb', 'file_usdz', 'file_obj', 'thumb'];

    /** Extension of a converted file => extension of the companion file Blender writes beside it. */
    private const COMPANIONS = ['obj' => 'mtl', 'gltf' => 'bin'];

    /** @var callable(string, string): bool */
    private $mover;

    /** @param callable(string, string): bool|null $mover moves an uploaded temp file; defaults to move_uploaded_file */
    public function __construct(private readonly string $directory, ?callable $mover = null)
    {
        $this->mover = $mover ?? 'move_uploaded_file';
    }

    public function directory(): string
    {
        return $this->directory;
    }

    // ที่อยู่เต็มของไฟล์ใน uploads/
    public function path(string $name): string
    {
        return $this->directory . $name;
    }

    // สร้างชื่อไฟล์สุ่ม (กันชื่อซ้ำและเดาไม่ได้)
    public function randomName(string $extension, string $suffix = ''): string
    {
        return bin2hex(random_bytes(8)) . $suffix . '.' . $extension;
    }

    // ย้ายไฟล์ที่อัปโหลดเข้า uploads/
    /** @param array<string, mixed> $upload one entry of $_FILES */
    public function store(array $upload, string $name): bool
    {
        $target = $this->path($name);
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }

        return ($this->mover)((string) ($upload['tmp_name'] ?? ''), $target);
    }

    // เช็กว่าไฟล์มีอยู่จริงและอยู่ใน uploads/ (กันการเข้าถึงไฟล์นอกโฟลเดอร์)
    /** True for an existing file below the uploads directory; rejects traversal, backslashes and absolute paths. */
    public function exists(?string $name): bool
    {
        return $name !== null
            && $name !== ''
            && preg_match('#(\.\.|\\\\|\x00|^/)#', $name) !== 1
            && is_file($this->path($name));
    }

    // ขนาดไฟล์ (byte)
    public function size(?string $name): int
    {
        return $this->exists($name) ? (int) filesize($this->path((string) $name)) : 0;
    }

    // ลบไฟล์พร้อมไฟล์ประกอบ (.mtl ของ OBJ, .bin ของ glTF)
    /** Deletes a file together with the sidecar Blender writes next to it (.mtl for OBJ, .bin for glTF). */
    public function delete(?string $name): void
    {
        $companion = self::COMPANIONS[strtolower(pathinfo((string) $name, PATHINFO_EXTENSION))] ?? null;
        foreach ([$name, $companion === null ? null : pathinfo((string) $name, PATHINFO_FILENAME) . '.' . $companion] as $target) {
            if ($this->exists($target)) {
                @unlink($this->path((string) $target));
            }
        }
    }

    // ลบทุกไฟล์ของโมเดล (ต้นฉบับ ไฟล์แปลง รูปปก)
    /** Removes every file a model row points at. @param array<string, mixed> $model */
    public function deleteAll(array $model): void
    {
        foreach (self::FILE_COLUMNS as $column) {
            $this->delete(isset($model[$column]) ? (string) $model[$column] : null);
        }
    }
}
