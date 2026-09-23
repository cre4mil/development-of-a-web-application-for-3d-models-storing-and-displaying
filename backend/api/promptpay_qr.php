<?php
/**
 * PromptPay QR Code Generator
 * สร้าง PromptPay QR Code ตามมาตรฐาน EMVCo
 * ใช้ได้กับทุกธนาคารในไทย + TrueMoney Wallet
 *
 * Usage:
 *   GET  promptpay_qr.php?amount=299.00  → JSON { payload, amount }
 */
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

$modelId = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
if ($modelId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_model_id']);
    exit;
}

// Fetch model price and seller's payment details
$st = $pdo->prepare("SELECT price FROM models WHERE id = ?");
$st->execute([$modelId]);
$modelInfo = $st->fetch();

if (!$modelInfo) {
    http_response_code(404);
    echo json_encode(['error' => 'model_not_found']);
    exit;
}

$amount = round(floatval($modelInfo['price']), 2);

// บัญชีรับเงินกลางของ Admin (Centralized Payment)
$method  = defined('PAYMENT_METHOD') ? PAYMENT_METHOD : 'promptpay';
$account = defined('PAYMENT_ACCOUNT') ? PAYMENT_ACCOUNT : '';
$name    = defined('PAYMENT_NAME') ? PAYMENT_NAME : '';

if (empty($account)) {
    http_response_code(400);
    echo json_encode(['error' => 'no_payment_setup']);
    exit;
}

if ($method === 'promptpay' || $method === 'truemoney') {
    $payload = generatePromptPayPayload($account, $amount);
} else {
    // Other banks don't have standard PromptPay QR payloads automatically unless they registered PromptPay.
    // For this app, we will just pass a flag so the frontend can display bank info.
    $payload = '';
}

echo json_encode([
    'method'        => $method,
    'payload'       => $payload,
    'amount'        => $amount,
    'account'       => $account,
    'name'          => $name,
]);

// ========== PromptPay EMVCo Payload Generator ==========

/**
 * สร้าง PromptPay payload ตามมาตรฐาน EMVCo
 * รองรับทั้งเบอร์โทร (10 หลัก) และเลขบัตรประชาชน (13 หลัก)
 */
function generatePromptPayPayload(string $promptpayId, float $amount = 0): string {
    // ทำความสะอาด ID — เอาเฉพาะตัวเลข
    $id = preg_replace('/\D/', '', $promptpayId);
    $idLen = strlen($id);

    // แปลงเบอร์โทรเป็นรูปแบบสากล 0066xxxxxxxxx
    if ($idLen === 10) {
        // เบอร์โทร: 08xxxxxxxx → 0066 8xxxxxxxx (13 chars)
        $formattedId = '0066' . substr($id, 1);
        $subTag01 = '01'; // Phone
    } elseif ($idLen === 13) {
        // เลขบัตรประชาชน: ใช้ตรงๆ
        $formattedId = $id;
        $subTag01 = '02'; // National ID / Tax ID
    } else {
        $formattedId = $id;
        $subTag01 = strlen($id) <= 13 ? '01' : '02';
    }

    // Tag 29: Merchant Account Information (PromptPay)
    $merchantAccountSub  = tlv('00', 'A000000677010111'); // PromptPay AID
    $merchantAccountSub .= tlv($subTag01, $formattedId);
    $merchantAccount     = tlv('29', $merchantAccountSub);

    // สร้าง payload
    $data  = tlv('00', '01');                              // Payload Format Indicator
    $data .= tlv('01', $amount > 0 ? '12' : '11');        // 11 = static, 12 = dynamic (มี amount)
    $data .= $merchantAccount;
    $data .= tlv('53', '764');                             // Currency: THB
    if ($amount > 0) {
        $data .= tlv('54', number_format($amount, 2, '.', '')); // Amount
    }
    $data .= tlv('58', 'TH');                              // Country Code
    $data .= tlv('59', 'PromptPay');                        // Merchant Name
    $data .= tlv('60', 'Bangkok');                          // Merchant City

    // Tag 63: CRC — ใส่ placeholder ก่อนแล้วคำนวณ
    $data .= '6304';
    $crc   = crc16ccitt($data);
    $data .= strtoupper(sprintf('%04X', $crc));

    return $data;
}

/**
 * TLV (Tag-Length-Value) format
 */
function tlv(string $tag, string $value): string {
    return $tag . sprintf('%02d', strlen($value)) . $value;
}

/**
 * CRC-16/CCITT-FALSE
 */
function crc16ccitt(string $data): int {
    $crc = 0xFFFF;
    for ($i = 0, $len = strlen($data); $i < $len; $i++) {
        $crc ^= (ord($data[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc <<= 1;
            }
            $crc &= 0xFFFF;
        }
    }
    return $crc;
}

/**
 * ปิดบังเลขบัญชีสำหรับแสดงผลด้าน frontend
 */
function maskPaymentAccount(string $id): string {
    $id = preg_replace('/\D/', '', $id);
    $len = strlen($id);
    $maskedAccount = $id;
    if ($len === 10) {
        $maskedAccount = substr($id, 0, 3) . '-xxx-' . substr($id, -2);
    } elseif ($len === 13) {
        $maskedAccount = substr($id, 0, 1) . '-xxxx-xxxxx-' . substr($id, -3, 2) . '-' . substr($id, -1);
    } elseif ($len > 4) {
        $maskedAccount = str_repeat('x', max(0, $len - 4)) . substr($id, -4);
    }

    return $maskedAccount;
}
