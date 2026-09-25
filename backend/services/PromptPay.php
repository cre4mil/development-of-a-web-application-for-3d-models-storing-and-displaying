<?php

declare(strict_types=1);

namespace App\Services;

/** Builds EMVCo PromptPay payloads (phone number or national ID) for QR codes. */
final class PromptPay
{
    public static function payload(string $promptPayId, float $amount = 0): string
    {
        $id = (string) preg_replace('/\D/', '', $promptPayId);
        if (strlen($id) === 10) {
            $account = self::tlv('01', '0066' . substr($id, 1));
        } elseif (strlen($id) === 13) {
            $account = self::tlv('02', $id);
        } else {
            $account = self::tlv(strlen($id) <= 13 ? '01' : '02', $id);
        }

        $merchant = self::tlv('29', self::tlv('00', 'A000000677010111') . $account);
        $data = self::tlv('00', '01')
            . self::tlv('01', $amount > 0 ? '12' : '11')
            . $merchant
            . self::tlv('53', '764');
        if ($amount > 0) {
            $data .= self::tlv('54', number_format($amount, 2, '.', ''));
        }
        $data .= self::tlv('58', 'TH') . self::tlv('59', 'PromptPay') . self::tlv('60', 'Bangkok') . '6304';

        return $data . sprintf('%04X', self::crc16($data));
    }

    public static function tlv(string $tag, string $value): string
    {
        return $tag . sprintf('%02d', strlen($value)) . $value;
    }

    /** CRC-16/CCITT-FALSE as required by the EMVCo QR specification. */
    public static function crc16(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0, $length = strlen($data); $i < $length; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? ($crc << 1) ^ 0x1021 : $crc << 1;
                $crc &= 0xFFFF;
            }
        }

        return $crc;
    }
}
