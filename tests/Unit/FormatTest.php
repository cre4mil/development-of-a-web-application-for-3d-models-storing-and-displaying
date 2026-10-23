<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    #[DataProvider('bytesProvider')]
    public function testBytes(int|float $input, string $expected): void
    {
        self::assertSame($expected, Format::bytes($input));
    }

    /** @return array<string, array{int|float, string}> */
    public static function bytesProvider(): array
    {
        return [
            'zero' => [0, '0 B'],
            'negative' => [-5, '0 B'],
            'bytes' => [512, '512 B'],
            'kilobytes' => [1536, '1.5 KB'],
            'megabytes' => [5 * 1048576, '5 MB'],
            'clamped to the largest unit' => [2 * 1024 ** 5, '2048 TB'],
        ];
    }

    #[DataProvider('compactProvider')]
    public function testCompact(int|float $input, string $expected): void
    {
        self::assertSame($expected, Format::compact($input));
    }

    /** @return array<string, array{int|float, string}> */
    public static function compactProvider(): array
    {
        return [
            'small' => [999, '999'],
            'thousands' => [1500, '1.5k'],
            'round thousand' => [1000, '1k'],
            'millions' => [2_400_000, '2.4M'],
            'fraction is truncated' => [12.9, '12'],
        ];
    }

    public function testMoney(): void
    {
        self::assertSame('฿1,234.50', Format::money(1234.5));
        self::assertSame('฿0.00', Format::money('0'));
    }

    public function testDateTime(): void
    {
        self::assertSame('05/03/2026 14:30', Format::dateTime('2026-03-05 14:30:00'));
        self::assertSame('-', Format::dateTime(null));
        self::assertSame('-', Format::dateTime(''));
        self::assertSame('-', Format::dateTime('not a date'));
    }

    public function testInitial(): void
    {
        self::assertSame('A', Format::initial(' alice'));
        self::assertSame('ป', Format::initial('ปาณิสรา'));
        self::assertSame('?', Format::initial('   '));
    }
}
