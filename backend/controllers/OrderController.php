<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repositories\CatalogRepository;
use App\Repositories\CommerceQueries;
use App\Repositories\PdoOrderRepository;
use App\Services\Commerce;
use App\Services\ModelFiles;
use App\Services\OrderService;
use App\Services\PromptPay;
use App\Services\SlipVerifier;
use App\Support\Config;
use PDO;
use Throwable;

/** Buyer orders page plus the checkout, approval and payment-QR endpoints. */
final class OrderController extends Controller
{
    private const SLIP_ERRORS = [
        'slip_required' => 'กรุณาแนบสลิปโอนเงิน',
        'slip_format' => 'สลิปต้องเป็นไฟล์รูปภาพ (jpg, png, webp)',
        'slip_too_large' => 'สลิปต้องมีขนาดไม่เกิน 5 MB',
    ];

    protected const ACTIONS = ['create' => 'create', 'status' => 'status', 'qr' => 'qr'];

    private readonly CatalogRepository $catalog;
    private readonly CommerceQueries $commerce;
    private readonly ModelFiles $files;
    private readonly OrderService $orders;
    private readonly SlipVerifier $verifier;

    public function __construct(PDO $pdo, ?ModelFiles $files = null, ?OrderService $orders = null, ?SlipVerifier $verifier = null)
    {
        parent::__construct($pdo);
        $this->catalog = new CatalogRepository($pdo);
        $this->commerce = new CommerceQueries($pdo);
        $this->files = $files ?? new ModelFiles(Config::uploadDir());
        $this->orders = $orders ?? new OrderService(new PdoOrderRepository($pdo));
        $this->verifier = $verifier ?? new SlipVerifier(Config::env('SLIPOK_API_KEY'), Config::env('SLIPOK_BRANCH_ID'));
    }

    public function index(Request $request): Response
    {
        return $this->pageGuard($request) ?? View::page($request, 'pages/orders', [
            'title' => 'คำสั่งซื้อของฉัน – 3D Gallery',
            'nav' => 'orders',
            'orders' => $this->commerce->buyerOrders($request->session()->userId()),
        ]);
    }

    /** Payment instructions (QR payload or bank details) for one model. */
    protected function qr(Request $request): Response
    {
        $model = $this->catalog->find($request->int('model_id'));
        $this->abortUnless($model !== null && (float) $model['price'] > 0, 404, 'model_not_found', 'ไม่พบโมเดลที่ต้องชำระเงิน');
        $account = Config::paymentAccount();
        $method = Config::paymentMethod();
        $amount = round((float) $model['price'], 2);
        $isQr = in_array($method, ['promptpay', 'truemoney'], true);

        return $this->ok([
            'method' => $method,
            'amount' => $amount,
            'payload' => $isQr ? PromptPay::payload($account, $amount) : '',
        ] + ($isQr ? [] : ['account' => $account, 'name' => Config::paymentName()]));
    }

    protected function create(Request $request): Response
    {
        $this->guard($request);
        $userId = $request->session()->userId();
        $modelIds = Commerce::normalizeModelIds($request->list('model_ids'));
        $this->abortUnless($modelIds !== [], 422, 'no_models', 'ไม่พบโมเดลที่ต้องการสั่งซื้อ');
        $slip = $request->file('slip');
        $slipError = Commerce::validateSlip($slip);
        $this->abortUnless($slipError === null, 422, (string) $slipError, self::SLIP_ERRORS[(string) $slipError] ?? '');
        $quote = $this->orders->quote($userId, $modelIds, Config::PLATFORM_FEE_PCT);
        $this->abortUnless($quote !== null, 422, 'no_valid_items', 'ไม่มีรายการที่สั่งซื้อได้ (อาจเป็นโมเดลของคุณเอง หรือสั่งซื้อไปแล้ว)');

        $name = 'slips/slip_' . $this->files->randomName(strtolower(pathinfo((string) $slip['name'], PATHINFO_EXTENSION)));
        $this->abortUnless($this->files->store((array) $slip, $name), 500, 'slip_save_failed', 'บันทึกสลิปไม่สำเร็จ');
        $order = $this->placeOrder($userId, $modelIds, $name, $quote['total']);

        return $this->ok($order + [
            'message' => $order['status'] === 'approved' ? 'ชำระเงินสำเร็จ ดาวน์โหลดได้ทันที' : 'ส่งสลิปเรียบร้อยแล้ว รอผู้ดูแลระบบตรวจสอบ',
        ]);
    }

    /** Admin approves or rejects a pending order. */
    protected function status(Request $request): Response
    {
        $this->guard($request);
        $status = $request->string('status');
        $this->abortUnless(in_array($status, ['approved', 'rejected'], true), 422, 'bad_status', 'สถานะไม่ถูกต้อง');
        $result = $this->orders->process($request->session()->userId(), $request->int('order_id'), $status, $request->string('note'));
        $this->abortUnless($result !== 'forbidden', 403, 'forbidden', 'ต้องเป็นผู้ดูแลระบบเท่านั้น');
        $this->abortUnless($result !== 'order_not_found', 404, 'order_not_found', 'ไม่พบคำสั่งซื้อ');
        $this->abortUnless($result === 'ok', 409, $result, 'คำสั่งซื้อนี้ถูกดำเนินการไปแล้ว');

        return $this->ok(['status' => $status]);
    }

    /**
     * Verifies the stored slip and creates the order; the slip file is removed when that fails.
     *
     * @param list<int> $modelIds
     * @return array{order_ref: string, items: int, total: float, status: string}
     */
    private function placeOrder(int $userId, array $modelIds, string $slipName, float $total): array
    {
        $verdict = $this->verifier->verify($this->files->path($slipName), $total);
        $order = null;
        if ($verdict['status'] !== SlipVerifier::REJECTED) {
            try {
                $order = $this->orders->create($userId, $modelIds, 'uploads/' . $slipName, $verdict['status'], $verdict['note'], Config::PLATFORM_FEE_PCT);
            } catch (Throwable) {
                $order = null;
            }
        }
        if ($order === null) {
            $this->files->delete($slipName);
            $this->abortUnless($verdict['status'] !== SlipVerifier::REJECTED, 422, 'slip_api_rejected', 'สลิปนี้ไม่ถูกต้อง หรือถูกใช้งานไปแล้ว (ตรวจสอบโดย SlipOK)');
            $this->abort(500, 'order_failed', 'สร้างคำสั่งซื้อไม่สำเร็จ กรุณาลองใหม่');
        }

        return $order + ['status' => $verdict['status']];
    }
}
