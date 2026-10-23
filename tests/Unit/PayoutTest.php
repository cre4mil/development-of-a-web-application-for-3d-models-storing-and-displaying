<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Payout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PayoutTest extends TestCase
{
    #[DataProvider('payoutAmounts')]
    public function testPayoutCannotExceedAvailableBalance(float $balance, float $amount, bool $expected): void
    {
        $this->assertSame($expected, Payout::canCreate($balance, $amount));
    }

    public static function payoutAmounts(): array
    {
        return [
            'valid full amount' => [300.00, 300.00, true],
            'valid partial amount' => [300.00, 10.50, true],
            'overdraw' => [300.00, 300.01, false],
            'zero' => [300.00, 0.00, false],
            'too many decimal places' => [300.00, 10.555, false],
        ];
    }

    #[DataProvider('transitionProvider')]
    public function testOnlyPendingPayoutCanTransition(string $current, string $next, bool $expected): void
    {
        $this->assertSame($expected, Payout::canTransition($current, $next));
    }

    public static function transitionProvider(): array
    {
        return [
            'transfer pending' => ['pending', 'transferred', true],
            'reject pending' => ['pending', 'rejected', true],
            'transfer twice' => ['transferred', 'transferred', false],
            'unknown state' => ['pending', 'paid', false],
        ];
    }

    #[DataProvider('feeProvider')]
    public function testPlatformFeeHasSafeBounds(float $fee, bool $expected): void
    {
        $this->assertSame($expected, Payout::isValidPlatformFee($fee));
    }

    public static function feeProvider(): array
    {
        return [[0.0, true], [100.0, true], [-0.01, false], [100.01, false]];
    }
}
