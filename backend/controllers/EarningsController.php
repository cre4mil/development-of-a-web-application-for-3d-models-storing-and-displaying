<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Http\View;
use App\Repositories\AccountRepository;
use App\Repositories\CommerceQueries;
use App\Support\Config;
use PDO;

/** Creator earnings dashboard and payout bank details. */
final class EarningsController extends Controller
{
    private readonly AccountRepository $accounts;
    private readonly CommerceQueries $commerce;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->accounts = new AccountRepository($pdo);
        $this->commerce = new CommerceQueries($pdo);
    }

    public function index(Request $request): Response
    {
        if ($request->isPost()) {
            return $this->saveBank($request);
        }
        if (($redirect = $this->pageGuard($request)) !== null) {
            return $redirect;
        }
        $userId = $request->session()->userId();
        $sales = $this->commerce->sales($userId, 100);

        return View::page($request, 'pages/earnings', [
            'title' => 'รายได้ของฉัน – 3D Gallery',
            'nav' => 'earnings',
            'scripts' => ['earnings.js'],
            'wallet' => $this->accounts->wallet($userId),
            'sales' => $sales,
            'monthly' => $this->commerce->monthlyEarnings($userId, 6),
            'payouts' => $this->commerce->payoutHistory($userId),
            'soldModels' => $this->commerce->soldModelCount($userId),
            'threshold' => Config::PAYOUT_THRESHOLD,
            'feePercent' => $this->accounts->feePercent(Config::PLATFORM_FEE_PCT),
            'saved' => $request->query('saved') !== null,
        ]);
    }

    public function saveBank(Request $request): Response
    {
        if (($redirect = $this->pageGuard($request)) !== null) {
            return $redirect;
        }
        if (!$request->isPost() || !$request->hasValidCsrf()) {
            $request->session()->set('error', 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง');
            return Response::redirect('creator_earnings.php');
        }
        $this->accounts->saveBank(
            $request->session()->userId(),
            $request->string('bank_name'),
            $request->string('bank_account_no'),
            $request->string('bank_account_name')
        );

        return Response::redirect('creator_earnings.php?saved=1#bank');
    }
}
