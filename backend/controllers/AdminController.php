<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repositories\AccountRepository;
use App\Repositories\CatalogRepository;
use App\Repositories\CommerceQueries;
use App\Repositories\PdoPayoutRepository;
use App\Services\Commerce;
use App\Services\ModelFiles;
use App\Services\ModelRemover;
use App\Services\Payout;
use App\Services\PayoutService;
use App\Support\Config;
use PDO;

/** Admin dashboard pages and moderation / payout actions. */
final class AdminController extends Controller
{
    protected const ACTIONS = [
        'delete_user' => 'deleteUser',
        'delete_model' => 'deleteModel',
        'create_payout' => 'createPayout',
        'transfer_payout' => 'transferPayout',
        'reject_payout' => 'rejectPayout',
        'update_fee' => 'updateFee',
    ];

    private const SLIP_ERRORS = [
        'slip_required' => 'กรุณาแนบสลิปการโอน',
        'slip_format' => 'สลิปต้องเป็นไฟล์รูปภาพ (jpg, png, webp)',
        'slip_too_large' => 'สลิปต้องมีขนาดไม่เกิน 5 MB',
    ];
    private const PAYOUT_NOT_FOUND = 'ไม่พบรายการ หรือดำเนินการไปแล้ว';

    private readonly AccountRepository $accounts;
    private readonly CatalogRepository $catalog;
    private readonly CommerceQueries $commerce;
    private readonly ModelFiles $files;
    private readonly ModelRemover $remover;
    private readonly PayoutService $payouts;

    public function __construct(PDO $pdo, ?ModelFiles $files = null, ?PayoutService $payouts = null)
    {
        parent::__construct($pdo);
        $this->accounts = new AccountRepository($pdo);
        $this->catalog = new CatalogRepository($pdo);
        $this->commerce = new CommerceQueries($pdo);
        $this->files = $files ?? new ModelFiles(Config::uploadDir());
        $this->remover = new ModelRemover($this->catalog, $this->files);
        $this->payouts = $payouts ?? new PayoutService(new PdoPayoutRepository($pdo));
    }

    // ── Pages ────────────────────────────────────────────────────────

    public function dashboard(Request $request): Response
    {
        return $this->pageGuard($request, admin: true) ?? View::page($request, 'pages/admin/dashboard', [
            'title' => 'Admin Dashboard – 3D Gallery',
            'nav' => 'admin',
            'scripts' => ['admin.js'],
            'stats' => [
                'users' => $this->accounts->count(),
                'models' => $this->catalog->count(),
                'pending' => $this->commerce->orderCounts()['pending'],
                'revenue' => $this->commerce->revenue(),
                'ready' => $this->accounts->payoutStats((float) Config::PAYOUT_THRESHOLD)['ready'],
            ],
            'orders' => $this->commerce->dashboardOrders(),
            'users' => $this->accounts->all(),
            'models' => $this->catalog->adminList(),
        ]);
    }

    public function orders(Request $request): Response
    {
        $status = $request->string('status');

        return $this->pageGuard($request, admin: true) ?? View::page($request, 'pages/admin/orders', [
            'title' => 'จัดการ Orders – 3D Gallery',
            'nav' => 'admin',
            'scripts' => ['admin.js'],
            'status' => $status,
            'orders' => $this->commerce->adminOrders($status),
            'counts' => $this->commerce->orderCounts(),
        ]);
    }

    public function payouts(Request $request): Response
    {
        return $this->pageGuard($request, admin: true) ?? View::page($request, 'pages/admin/payouts', [
            'title' => 'Payout Management – 3D Gallery',
            'nav' => 'admin',
            'scripts' => ['admin.js'],
            'wallets' => $this->accounts->wallets(),
            'payouts' => $this->accounts->payouts(100),
            'stats' => $this->accounts->payoutStats((float) Config::PAYOUT_THRESHOLD),
            'threshold' => Config::PAYOUT_THRESHOLD,
            'feePercent' => $this->accounts->feePercent(Config::PLATFORM_FEE_PCT),
        ]);
    }

    // ── JSON API ─────────────────────────────────────────────────────

    protected function deleteUser(Request $request): Response
    {
        $this->guard($request, admin: true);
        $id = $request->int('id');
        $this->abortUnless($id !== $request->session()->userId(), 422, 'self_delete', 'ไม่สามารถลบบัญชีของตนเองได้');
        $this->abortUnless($this->accounts->find($id) !== null, 404, 'not_found', 'ไม่พบผู้ใช้งาน');
        $this->abortUnless(!$this->accounts->hasFinancialHistory($id), 409, 'has_history', 'ผู้ใช้นี้มีประวัติการซื้อขายหรือรับเงิน จึงลบไม่ได้');
        foreach ($this->accounts->modelsOf($id) as $model) {
            $this->remover->remove($model);
        }
        $this->accounts->delete($id);

        return $this->ok(['message' => 'ลบผู้ใช้งานสำเร็จ']);
    }

    protected function deleteModel(Request $request): Response
    {
        $this->guard($request, admin: true);
        $model = $this->catalog->find($request->int('id'));
        $this->abortUnless($model !== null, 404, 'not_found', 'ไม่พบโมเดลนี้');
        $this->abortUnless($this->remover->remove((array) $model), 409, 'has_sales', 'โมเดลนี้มีประวัติการขายจึงลบไม่ได้');

        return $this->ok(['message' => 'ลบโมเดลสำเร็จ']);
    }

    protected function createPayout(Request $request): Response
    {
        $this->guard($request, admin: true);
        $creatorId = $request->int('creator_id');
        $amount = round((float) $request->string('amount', '0'), 2);
        $this->abortUnless($creatorId > 0 && $amount > 0, 422, 'bad_params', self::INVALID_DATA);
        $payoutId = $this->payouts->create($creatorId, $amount, $request->session()->userId());
        $this->abortUnless($payoutId !== null, 422, 'insufficient_balance', 'ยอดคงเหลือไม่เพียงพอ');

        return $this->ok(['payout_id' => $payoutId]);
    }

    protected function transferPayout(Request $request): Response
    {
        $this->guard($request, admin: true);
        $payoutId = $request->int('payout_id');
        $this->abortUnless($payoutId > 0, 422, 'bad_id', self::INVALID_DATA);
        $slip = $request->file('slip');
        $slipError = Commerce::validateSlip($slip);
        $this->abortUnless($slipError === null, 422, (string) $slipError, self::SLIP_ERRORS[(string) $slipError] ?? '');
        $name = 'payout_slips/payout_slip_' . $this->files->randomName(strtolower(pathinfo((string) $slip['name'], PATHINFO_EXTENSION)));
        $this->abortUnless($this->files->store((array) $slip, $name), 500, 'slip_save_failed', 'บันทึกสลิปไม่สำเร็จ');

        $done = false;
        try {
            $done = $this->payouts->transfer($payoutId, $request->session()->userId(), 'uploads/' . $name);
        } finally {
            if (!$done) {
                $this->files->delete($name);
            }
        }
        $this->abortUnless($done, 404, 'not_found_or_done', self::PAYOUT_NOT_FOUND);

        return $this->ok();
    }

    protected function rejectPayout(Request $request): Response
    {
        $this->guard($request, admin: true);
        $payoutId = $request->int('payout_id');
        $this->abortUnless($payoutId > 0, 422, 'bad_id', self::INVALID_DATA);
        $done = $this->payouts->reject($payoutId, $request->string('note'));
        $this->abortUnless($done, 404, 'not_found_or_done', self::PAYOUT_NOT_FOUND);

        return $this->ok();
    }

    protected function updateFee(Request $request): Response
    {
        $this->guard($request, admin: true);
        $fee = (float) $request->string('fee_pct', '-1');
        $this->abortUnless(Payout::isValidPlatformFee($fee), 422, 'bad_value', 'ค่าธรรมเนียมต้องอยู่ระหว่าง 0–100%');
        $this->accounts->saveFee($fee, $request->session()->userId());

        return $this->ok(['fee_pct' => $fee]);
    }
}
