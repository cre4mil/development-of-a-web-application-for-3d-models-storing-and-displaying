<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ModelManagement;
use PHPUnit\Framework\TestCase;

final class ModelManagementTest extends TestCase
{
    public function testOnlyOwnerCanManageModel(): void
    {
        $this->assertTrue(ModelManagement::isOwner(7, 7));
        $this->assertFalse(ModelManagement::isOwner(7, 8));
        $this->assertFalse(ModelManagement::isOwner(0, 0));
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
    }
}
