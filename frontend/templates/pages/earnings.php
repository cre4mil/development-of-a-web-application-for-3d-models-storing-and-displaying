<?php
/**
 * Creator earnings dashboard.
 *
 * @var array<string, mixed> $wallet
 * @var list<array<string, mixed>> $sales
 * @var list<array{month: string, earning: float, sales: int}> $monthly
 * @var list<array<string, mixed>> $payouts
 * @var int $soldModels
 * @var int $threshold
 * @var float $feePercent
 * @var bool $saved
 * @var array{id: int, name: string, admin: bool} $user
 * @var string $csrf
 */
use App\Support\Format;

$available = (float) $wallet['available_balance'];
$progress = $threshold > 0 ? min(100, $available / $threshold * 100) : 100;
$payoutLabels = ['transferred' => 'โอนแล้ว', 'pending' => 'รอโอน', 'rejected' => 'ปฏิเสธ'];
?>
<div class="page">
  <div class="page-head">
    <div>
      <h1><i class="bi bi-cash-stack me-2 text-primary"></i>รายได้ของฉัน</h1>
      <p>สวัสดี <?= e($user['name']) ?> · ค่าธรรมเนียมแพลตฟอร์ม <?= e(rtrim(rtrim(number_format($feePercent, 2), '0'), '.')) ?>% ต่อรายการขาย</p>
    </div>
  </div>

  <section class="wallet-hero">
    <div>
      <div class="lbl"><i class="bi bi-wallet2 me-1"></i>ยอดคงเหลือ (พร้อมถอน)</div>
      <div class="amount"><?= e(money($available)) ?></div>
      <?php if ((float) $wallet['pending_payout'] > 0) : ?>
        <div class="sub"><i class="bi bi-clock me-1"></i>รอโอน <?= e(money($wallet['pending_payout'])) ?></div>
      <?php endif; ?>
      <div class="progress-track"><div class="progress-fill" style="width: <?= round($progress, 1) ?>%"></div></div>
      <div class="sub">
        <?php if ($available >= $threshold) : ?>
          <i class="bi bi-check-circle-fill me-1"></i>ยอดถึงเกณฑ์แล้ว แอดมินจะโอนให้ในรอบถัดไป
        <?php else : ?>
          อีก <?= e(money($threshold - $available)) ?> จึงจะถึงเกณฑ์ขั้นต่ำ (<?= e(money($threshold)) ?>)
        <?php endif; ?>
      </div>
    </div>
    <div class="wallet-stats">
      <div><div class="n"><?= e(money($wallet['total_earned'])) ?></div><div class="l">รายได้รวมทั้งหมด</div></div>
      <div><div class="n"><?= $soldModels ?></div><div class="l">โมเดลที่ขายได้</div></div>
      <div><div class="n"><?= count($sales) ?></div><div class="l">รายการขาย</div></div>
    </div>
  </section>

  <ul class="nav nav-pills mb-3" id="earningsTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-chart" type="button" role="tab"><i class="bi bi-graph-up me-1"></i>กราฟรายได้</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-sales" type="button" role="tab"><i class="bi bi-receipt me-1"></i>ประวัติการขาย</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-payouts" type="button" role="tab"><i class="bi bi-cash-coin me-1"></i>ประวัติรับเงิน</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-bank" type="button" role="tab" id="bank-tab"><i class="bi bi-bank me-1"></i>บัญชีธนาคาร</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active" id="tab-chart" role="tabpanel">
      <?php if ($monthly) : ?>
        <div class="panel"><div class="panel-body"><div class="chart-box"><canvas id="earningsChart"></canvas></div></div></div>
        <script type="application/json" id="chartData"><?= json_encode($monthly, JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
      <?php else : ?>
        <div class="empty"><i class="bi bi-bar-chart"></i><h3>ยังไม่มีข้อมูลรายได้ที่อนุมัติแล้ว</h3></div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-sales" role="tabpanel">
      <?php if ($sales) : ?>
        <div class="panel"><div class="table-responsive">
          <table class="table table-hover">
            <thead><tr><th>โมเดล</th><th>Order</th><th>ผู้ซื้อ</th><th>ราคา</th><th>ค่าธรรมเนียม</th><th>สุทธิ</th><th>สถานะ</th><th>วันที่</th></tr></thead>
            <tbody>
              <?php foreach ($sales as $sale) : ?>
                <tr>
                  <td><div class="cell-model"><img class="thumb-sm" src="<?= e(uploadUrl($sale['model_thumb'])) ?>" alt="" loading="lazy"><span class="t"><?= e($sale['model_title']) ?></span></div></td>
                  <td><span class="order-ref"><?= e($sale['order_ref']) ?></span></td>
                  <td><?= e($sale['buyer_name']) ?></td>
                  <td><?= e(money($sale['price'])) ?></td>
                  <td class="text-danger">-<?= e(money($sale['platform_fee'])) ?></td>
                  <td class="fw-bold text-success"><?= e(money($sale['creator_earning'])) ?></td>
                  <td><span class="status <?= $sale['payment_status'] === 'approved' ? 'approved' : 'pending' ?>"><?= $sale['payment_status'] === 'approved' ? 'อนุมัติ' : 'รอตรวจ' ?></span></td>
                  <td class="text-muted small text-nowrap"><?= e(Format::dateTime($sale['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div></div>
      <?php else : ?>
        <div class="empty"><i class="bi bi-bag-x"></i><h3>ยังไม่มีประวัติการขาย</h3></div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-payouts" role="tabpanel">
      <?php if ($payouts) : ?>
        <div class="panel"><div class="table-responsive">
          <table class="table table-hover">
            <thead><tr><th>#</th><th>ยอดโอน</th><th>สถานะ</th><th>สร้างเมื่อ</th><th>โอนเมื่อ</th><th>สลิป</th><th>หมายเหตุ</th></tr></thead>
            <tbody>
              <?php foreach ($payouts as $payout) : ?>
                <tr>
                  <td class="mono text-muted">#<?= (int) $payout['id'] ?></td>
                  <td class="fw-bold"><?= e(money($payout['amount'])) ?></td>
                  <td><span class="status <?= e($payout['status']) ?>"><?= e($payoutLabels[$payout['status']] ?? $payout['status']) ?></span></td>
                  <td class="text-muted small"><?= e(Format::dateTime($payout['created_at'])) ?></td>
                  <td class="text-muted small"><?= e(Format::dateTime($payout['transferred_at'])) ?></td>
                  <td><?php if ($payout['admin_transfer_slip']) : ?><a class="btn btn-soft btn-sm" href="<?= e($payout['admin_transfer_slip']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-image me-1"></i>ดูสลิป</a><?php else : ?>-<?php endif; ?></td>
                  <td class="text-muted small"><?= e($payout['admin_note'] ?: '-') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div></div>
      <?php else : ?>
        <div class="empty"><i class="bi bi-cash"></i><h3>ยังไม่มีประวัติการรับเงิน</h3></div>
      <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="tab-bank" role="tabpanel">
      <div class="panel" style="max-width: 520px">
        <div class="panel-body">
          <?php if ($saved) : ?><div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>บันทึกข้อมูลธนาคารเรียบร้อยแล้ว</div><?php endif; ?>
          <p class="text-muted small">ข้อมูลนี้ใช้ให้ผู้ดูแลระบบโอนเงินรายได้ให้คุณ กรุณากรอกให้ถูกต้อง</p>
          <form method="post" action="creator_earnings.php">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <div class="mb-3"><label class="form-label" for="bank_name">ธนาคาร</label><input id="bank_name" name="bank_name" class="form-control" placeholder="เช่น กสิกรไทย, SCB" value="<?= e($wallet['bank_name']) ?>"></div>
            <div class="mb-3"><label class="form-label" for="bank_account_no">เลขบัญชี</label><input id="bank_account_no" name="bank_account_no" class="form-control" inputmode="numeric" value="<?= e($wallet['bank_account_no']) ?>"></div>
            <div class="mb-3"><label class="form-label" for="bank_account_name">ชื่อเจ้าของบัญชี</label><input id="bank_account_name" name="bank_account_name" class="form-control" value="<?= e($wallet['bank_account_name']) ?>"></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>บันทึกข้อมูล</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
