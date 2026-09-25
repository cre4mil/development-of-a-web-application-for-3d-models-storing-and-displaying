<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ModelManagement;
use Tests\Support\AppTestCase;

final class ModelManagementTest extends AppTestCase
{
    public function testOnlyOwnerCanManageModel(): void
    {
        $this->assertTrue(ModelManagement::isOwner(7, 7));
        $this->assertFalse(ModelManagement::isOwner(7, 8));
        $this->assertFalse(ModelManagement::isOwner(0, 0));
    }

    public function testVisibilityRules(): void
    {
        $public = ['is_public' => 1, 'user_id' => 5];
        $hidden = ['is_public' => 0, 'user_id' => 5];

        $this->assertTrue(ModelManagement::canView($public, 0, false));
        $this->assertFalse(ModelManagement::canView($hidden, 0, false));
        $this->assertFalse(ModelManagement::canView($hidden, 6, false));
        $this->assertTrue(ModelManagement::canView($hidden, 5, false), 'owner');
        $this->assertTrue(ModelManagement::canView($hidden, 9, true), 'admin');
        $this->assertFalse(ModelManagement::canView(null, 5, true));
    }

    public function testNormalizesPriceAndLicense(): void
    {
        $this->assertSame(12.35, ModelManagement::normalizePrice('12.345'));
        $this->assertSame(0.0, ModelManagement::normalizePrice(-10));
        $this->assertSame('CC0', ModelManagement::normalizeLicense('CC0'));
        $this->assertSame(ModelManagement::DEFAULT_LICENSE, ModelManagement::normalizeLicense('custom'));
    }

    public function testNormalizesAndDeduplicatesTags(): void
    {
        $this->assertSame(['art', '3d', 'thai'], ModelManagement::normalizeTags(' Art, 3D,art, , THAI '));
        $this->assertSame([str_repeat('x', 50)], ModelManagement::normalizeTags(str_repeat('x', 80)));
        $this->assertSame([], ModelManagement::normalizeTags(' , ,'));
    }

    public function testLicenseCatalogue(): void
    {
        $this->assertContains('CC0', ModelManagement::licenseCodes());
        $this->assertSame(
            ['name' => 'Creative Commons Attribution 4.0', 'url' => 'https://creativecommons.org/licenses/by/4.0/'],
            ModelManagement::licenseInfo('CC BY')
        );
        $this->assertNull(ModelManagement::licenseInfo('All Rights Reserved')['url']);
        $this->assertSame('All Rights Reserved', ModelManagement::licenseInfo('unknown')['name']);
    }

    public function testDownloadNameIsAFriendlyFileName(): void
    {
        $this->assertSame('Old_Telephone.glb', ModelManagement::downloadName('Old Telephone', 'abc123.GLB'));
        $this->assertSame('ปาณิสรา_3D.obj', ModelManagement::downloadName('ปาณิสรา 3D!', 'x.obj'));
        $this->assertSame('model.glb', ModelManagement::downloadName('???', 'x.glb'));
    }

    public function testViewerPrefersTheConvertedGlbWhenItExists(): void
    {
        $files = $this->files();
        file_put_contents($this->uploads . 'orig.obj', 'x');
        file_put_contents($this->uploads . 'orig_converted.glb', 'x');

        $withGlb = ModelManagement::viewerFile(['filename' => 'orig.obj', 'file_glb' => 'orig_converted.glb'], $files);
        $missingGlb = ModelManagement::viewerFile(['filename' => 'orig.obj', 'file_glb' => 'gone.glb'], $files);
        $noGlb = ModelManagement::viewerFile(['filename' => 'orig.obj', 'file_glb' => null], $files);

        $this->assertSame(['file' => 'orig_converted.glb', 'ext' => 'glb'], $withGlb);
        $this->assertSame(['file' => 'orig.obj', 'ext' => 'obj'], $missingGlb);
        $this->assertSame(['file' => 'orig.obj', 'ext' => 'obj'], $noGlb);
    }

    public function testDownloadFormatsListExistingFilesOnceEach(): void
    {
        $files = $this->files();
        foreach (['m.glb', 'm_converted.gltf', 'm_converted.obj'] as $name) {
            file_put_contents($this->uploads . $name, str_repeat('x', 10));
        }
        $model = [
            'filename' => 'm.glb', 'file_glb' => 'm.glb', 'file_gltf' => 'm_converted.gltf',
            'file_obj' => 'm_converted.obj', 'file_usdz' => 'missing.usdz',
        ];

        $formats = ModelManagement::downloadFormats($model, $files);

        $this->assertSame(['original', 'gltf', 'obj'], array_column($formats, 'format'));
        $this->assertSame(['GLB', 'GLTF', 'OBJ'], array_column($formats, 'ext'));
        $this->assertSame(10, $formats[0]['size']);
        $this->assertSame([], ModelManagement::downloadFormats(['filename' => 'nope.glb'], $files));
    }
}
