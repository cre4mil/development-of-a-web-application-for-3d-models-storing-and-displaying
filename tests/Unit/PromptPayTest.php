<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PromptPay;
use PHPUnit\Framework\TestCase;

final class PromptPayTest extends TestCase
{
    public function testPhoneNumberPayloadWithAmount(): void
    {
        $payload = PromptPay::payload('094-949-1035', 299.5);

        self::assertStringStartsWith('000201010212', $payload);
        self::assertStringContainsString('A000000677010111', $payload);
        self::assertStringContainsString('01130066949491035', $payload, 'phone numbers are converted to 0066 format');
        self::assertStringContainsString('5406299.50', $payload);
        self::assertStringContainsString('5802TH', $payload);
        self::assertMatchesRegularExpression('/6304[0-9A-F]{4}$/', $payload);
    }

    public function testNationalIdPayloadWithoutAmountIsStatic(): void
    {
        $payload = PromptPay::payload('1234567890123');

        self::assertStringStartsWith('000201010211', $payload);
        self::assertStringContainsString('02131234567890123', $payload);
        self::assertStringContainsString('53037645802TH', $payload, 'no amount tag between currency and country');
    }

    public function testOtherIdLengthsFallBackToTheClosestTag(): void
    {
        self::assertStringContainsString('0109123456789', PromptPay::payload('123456789'));
        self::assertStringContainsString('0215' . '123456789012345', PromptPay::payload('123456789012345'));
    }

    public function testCrcMatchesTheEmvcoCheckValue(): void
    {
        // Standard CRC-16/CCITT-FALSE test vector.
        self::assertSame(0x29B1, PromptPay::crc16('123456789'));
    }

    public function testTlvEncodesLengthWithTwoDigits(): void
    {
        self::assertSame('5303764', PromptPay::tlv('53', '764'));
        self::assertSame('5911PromptPay!!', PromptPay::tlv('59', 'PromptPay!!'));
    }
}
