<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Commerce;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommerceTest extends TestCase
{
    public function testNormalizesCartModelIds(): void
    {
        $this->assertSame([7, 2], Commerce::normalizeModelIds(['7', 0, -1, 7, 'bad', 2]));
    }

    #[DataProvider('slipProvider')]
    public function testValidatesPaymentSlip(?array $file, ?string $expected): void
    {
        $this->assertSame($expected, Commerce::validateSlip($file));
    }

    public static function slipProvider(): array
    {
        return [
            'valid image' => [['name' => 'payment.PNG', 'size' => 1024, 'error' => UPLOAD_ERR_OK], null],
            'missing file' => [null, 'slip_required'],
            'wrong extension' => [['name' => 'payment.php', 'size' => 1, 'error' => UPLOAD_ERR_OK], 'slip_format'],
            'too large' => [['name' => 'payment.webp', 'size' => Commerce::MAX_SLIP_BYTES + 1, 'error' => UPLOAD_ERR_OK], 'slip_too_large'],
        ];
    }

    public function testCalculatesPlatformFeeAndCreatorEarning(): void
    {
        $this->assertSame(
            ['platform_fee' => 12.35, 'creator_earning' => 111.1],
            Commerce::calculateEarnings(123.45, 10)
        );
    }

    public function testCapsInvalidFeePercentage(): void
    {
        $this->assertSame(['platform_fee' => 20.0, 'creator_earning' => 0.0], Commerce::calculateEarnings(20, 150));
        $this->assertSame(['platform_fee' => 0.0, 'creator_earning' => 20.0], Commerce::calculateEarnings(20, -1));
    }

    #[DataProvider('orderTransitionProvider')]
    public function testOnlyPendingOrdersCanBeProcessed(string $current, string $next, bool $expected): void
    {
        $this->assertSame($expected, Commerce::canProcessOrder($current, $next));
    }

    public static function orderTransitionProvider(): array
    {
        return [
            'approve pending' => ['pending', 'approved', true],
            'reject pending' => ['pending', 'rejected', true],
            'approve twice' => ['approved', 'approved', false],
            'invalid status' => ['pending', 'refunded', false],
        ];
    }
}
