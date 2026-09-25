<?php

declare(strict_types=1);

namespace App\Services;

/** Verifies payment slips against the SlipOK API when an API key is configured. */
final class SlipVerifier
{
    public const APPROVED = 'approved';
    public const MANUAL = 'pending';
    public const REJECTED = 'rejected';

    /** @var callable(string, string, string): string|false */
    private $http;

    /** @param callable(string, string, string): string|false|null $http performs the upload: (url, file path, branch id) => response body */
    public function __construct(private readonly string $apiKey, private readonly string $branchId, ?callable $http = null)
    {
        $this->http = $http ?? [self::class, 'upload'];
    }

    public function enabled(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @return array{status: string, note: ?string} `approved` (auto-approve), `pending` (needs an admin) or `rejected` (fake/duplicate slip)
     */
    public function verify(string $slipPath, float $expectedTotal): array
    {
        if (!$this->enabled()) {
            return ['status' => self::MANUAL, 'note' => null];
        }
        $body = ($this->http)('https://api.slipok.com/api/line/apikey/' . $this->apiKey, $slipPath, $this->branchId);

        return $body === false
            ? ['status' => self::MANUAL, 'note' => 'API Error: ไม่สามารถเชื่อมต่อระบบตรวจสลิปได้']
            : $this->interpret($body, $expectedTotal);
    }

    /** @return array{status: string, note: ?string} */
    private function interpret(string $body, float $expectedTotal): array
    {
        $json = json_decode($body, true);
        $valid = is_array($json) && ($json['success'] ?? false) === true && isset($json['data']);
        $paid = (float) ($valid ? ($json['data']['amount'] ?? 0) : 0);

        return match (true) {
            !$valid => ['status' => self::REJECTED, 'note' => null],
            abs($paid - $expectedTotal) < 0.005 => ['status' => self::APPROVED, 'note' => 'อนุมัติอัตโนมัติ (SlipOK API)'],
            default => ['status' => self::MANUAL, 'note' => "API: ยอดเงินไม่ตรง (โอน {$paid} / สั่งซื้อ {$expectedTotal})"],
        };
    }

    /** Default transport: multipart upload with cURL. */
    public static function upload(string $url, string $slipPath, string $branchId): string|false
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['files' => new \CURLFile($slipPath)],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $branchId === '' ? [] : ['x-authorization: ' . $branchId],
        ]);
        $body = curl_exec($curl);
        curl_close($curl);

        return is_string($body) ? $body : false;
    }
}
