<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SlipVerifier;
use PHPUnit\Framework\TestCase;

final class SlipVerifierTest extends TestCase
{
    public function testWithoutAnApiKeyEveryOrderNeedsManualReview(): void
    {
        $verifier = new SlipVerifier('', '', static fn (): string => self::fail('the API must not be called'));

        self::assertFalse($verifier->enabled());
        self::assertSame(['status' => 'pending', 'note' => null], $verifier->verify('/slip.png', 100));
    }

    public function testMatchingAmountIsApprovedAutomatically(): void
    {
        $seen = [];
        $verifier = new SlipVerifier('KEY', 'BRANCH', function (string $url, string $path, string $branch) use (&$seen): string {
            $seen = [$url, $path, $branch];
            return '{"success":true,"data":{"amount":150.0}}';
        });

        $result = $verifier->verify('/slip.png', 150.0);

        self::assertTrue($verifier->enabled());
        self::assertSame('approved', $result['status']);
        self::assertSame('อนุมัติอัตโนมัติ (SlipOK API)', $result['note']);
        self::assertSame(['https://api.slipok.com/api/line/apikey/KEY', '/slip.png', 'BRANCH'], $seen);
    }

    public function testDifferentAmountGoesToAnAdmin(): void
    {
        $verifier = new SlipVerifier('KEY', '', static fn (): string => '{"success":true,"data":{"amount":99}}');

        $result = $verifier->verify('/slip.png', 150.0);

        self::assertSame('pending', $result['status']);
        self::assertStringContainsString('ยอดเงินไม่ตรง', (string) $result['note']);
    }

    public function testMissingAmountCountsAsZero(): void
    {
        $verifier = new SlipVerifier('KEY', '', static fn (): string => '{"success":true,"data":{}}');

        self::assertSame('pending', $verifier->verify('/slip.png', 10.0)['status']);
    }

    public function testRejectedOrMalformedResponsesAreTreatedAsFakeSlips(): void
    {
        foreach (['{"success":false}', '{"success":true}', 'not json', '[]'] as $body) {
            $verifier = new SlipVerifier('KEY', '', static fn (): string => $body);
            self::assertSame('rejected', $verifier->verify('/slip.png', 10.0)['status'], $body);
        }
    }

    public function testNetworkFailureFallsBackToManualReview(): void
    {
        $verifier = new SlipVerifier('KEY', '', static fn (): bool => false);

        $result = $verifier->verify('/slip.png', 10.0);

        self::assertSame('pending', $result['status']);
        self::assertStringContainsString('API Error', (string) $result['note']);
    }

    public function testDefaultTransportReturnsFalseWhenTheServerIsUnreachable(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'slip');
        file_put_contents($path, 'x');

        self::assertFalse(SlipVerifier::upload('http://127.0.0.1:9/unreachable', $path, ''));
        self::assertFalse(SlipVerifier::upload('http://127.0.0.1:9/unreachable', $path, 'BRANCH'));
        unlink($path);
    }

    public function testDefaultTransportIsUsedWhenNoneIsInjected(): void
    {
        // Constructing without a transport must not require the network.
        self::assertTrue((new SlipVerifier('KEY', ''))->enabled());
    }
}
