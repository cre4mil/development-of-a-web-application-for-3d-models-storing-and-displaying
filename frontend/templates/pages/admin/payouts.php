<?php
/**
 * @var list<array<string, mixed>> $wallets
 * @var list<array<string, mixed>> $payouts
 * @var array{pending: int, paid: float, ready: int} $stats
 * @var int $threshold
 * @var float $feePercent
 */
use App\Support\Format;

$labels = ['pending' => 'รอโอน', 'transferred' => 'โอนแล้ว', 'rejected' => 'ปฏิเสธ'];
?>
<div class="page">
  <div class="page-head">
    <div>
      <div class="eyebrow">Admin</div>
      <h1>Payout Management</h1>
      <p>จัดการการโอนเงินให้ Creator · เกณฑ์ขั้นต่ำ <?= e(money($threshold)) ?></p>
    </div>
  </div>
  <?= partial('admin_nav', ['section' => 'payouts']) ?>

  <div class="stat-grid">
    <div class="stat"><span class="stat-icon amber"><i class="bi bi-hourglass-split"></i></span><div><div class="num"><?= $stats['pending'] ?></div><div class="lbl">รอโอนเงิน</div></div></div>
    <div class="stat"><span class="stat-icon green"><i class="bi bi-check-circle"></i></span><div><div class="num"><?= e(money($stats['paid'])) ?></div><div class="lbl">โอนแล้วทั้งหมด</div></div></div>
    <div class="stat"><span class="stat-icon violet"><i class="bi bi-people"></i></span><div><div class="num"><?= $stats['ready'] ?></div><div class="lbl">Creator ถึงเกณฑ์</div></div></div>
  </div>

  <section class="panel mb-4">
    <div class="panel-head"><h2><i class="bi bi-sliders me-2 text-primary"></i>ค่าธรรมเนียมแพลตฟอร์ม</h2></div>
    <div class="panel-body">
      <form id="feeForm" class="d-flex flex-wrap align-items-end gap-3">
        <div>
          <div class="text-muted small">ปัจจุบัน</div>
          <div class="fs-2 fw-bold lh-1"><span id="feeCurrent"><?= e(rtrim(rtrim(number_format($feePercent, 2), '0'), '.')) ?></span>%</div>
        </div>
        <div>
          <label class="form-label" for="feeInput">ปรับเป็น (%)</label>
          <input type="number" id="feeInput" class="form-control" min="0" max="100" step="0.5" value="<?= e($feePercent) ?>" style="width: 120px">
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>บันทึก</button>
        <div class="text-muted small ms-auto">มีผลกับคำสั่งซื้อ <strong>ใหม่</strong> เท่านั้น</div>
      </form>
    </div>
  </section>

  <section class="panel mb-4">
    <div class="panel-head"><h2><i class="bi bi-wallet2 me-2"></i>กระเป๋าเงิน Creator</h2></div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr><th>Creator</th><th>คงเหลือ</th><th>รอโอน</th><th>รายได้รวม</th><th>บัญชีธนาคาร</th><th>สถานะ</th><th class="text-end">จัดการ</th></tr></thead>
        <tbody>
          <?php foreach ($wallets as $wallet) : ?>
            <?php $balance = (float) $wallet['available_balance']; ?>
            <tr>
              <td><div class="cell-model"><span class="avatar"><?= e(Format::initial($wallet['username'])) ?></span><div><div class="t"><?= e($wallet['username']) ?></div><div class="text-muted small"><?= e($wallet['email']) ?></div></div></div></td>
              <td class="fw-bold"><?= e(money($balance)) ?></td>
              <td><?= (float) $wallet['pending_payout'] > 0 ? e(money($wallet['pending_payout'])) : '-' ?></td>
              <td class="text-muted"><?= e(money($wallet['total_earned'])) ?></td>
              <td><?php if ($wallet['bank_account_no']) : ?><div class="small"><?= e($wallet['bank_name']) ?></div><div class="mono small text-muted"><?= e($wallet['bank_account_no']) ?></div><?php else : ?><span class="text-muted small">ยังไม่ระบุ</span><?php endif; ?></td>
              <td><?php if ($balance >= $threshold) : ?><span class="status ready"><i class="bi bi-check-circle"></i>พร้อมโอน</span><?php else : ?><span class="text-muted small">ยังไม่ถึงเกณฑ์</span><?php endif; ?></td>
              <td class="text-end"><?php if ($balance > 0) : ?><button type="button" class="btn btn-primary btn-sm" data-admin-action="payout-create" data-id="<?= (int) $wallet['creator_id'] ?>" data-name="<?= e($wallet['username']) ?>" data-balance="<?= e($balance) ?>"><i class="bi bi-send me-1"></i>สร้าง Payout</button><?php else : ?>-<?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$wallets) : ?><tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีข้อมูล Wallet</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2><i class="bi bi-list-check me-2"></i>ประวัติ Payout</h2></div>
    <div class="table-responsive">
      <table class="table table-hover">
        <thead><tr><th>#</th><th>Creator</th><th>ยอด</th><th>สถานะ</th><th>สร้างเมื่อ</th><th>โอนเมื่อ</th><th>สลิป</th><th class="text-end">จัดการ</th></tr></thead>
        <tbody>
          <?php foreach ($payouts as $payout) : ?>
            <tr>
              <td class="mono text-muted">#<?= (int) $payout['id'] ?></td>
              <td><?= e($payout['creator_name']) ?></td>
              <td class="fw-bold"><?= e(money($payout['amount'])) ?></td>
              <td><span class="status <?= e($payout['status']) ?>"><?= e($labels[$payout['status']] ?? $payout['status']) ?></span></td>
              <td class="text-muted small"><?= e(Format::dateTime($payout['created_at'])) ?></td>
              <td class="text-muted small"><?= e(Format::dateTime($payout['transferred_at'])) ?></td>
              <td><?php if ($payout['admin_transfer_slip']) : ?><a class="btn btn-soft btn-sm" href="<?= e($payout['admin_transfer_slip']) ?>" target="_blank" rel="noopener noreferrer"><i class="bi bi-image me-1"></i>ดูสลิป</a><?php else : ?>-<?php endif; ?></td>
              <td class="text-end text-nowrap">
                <?php if ($payout['status'] === 'pending') : ?>
                  <button type="button" class="btn btn-primary btn-sm" data-admin-action="payout-transfer" data-id="<?= (int) $payout['id'] ?>" data-name="<?= e($payout['creator_name']) ?>" data-amount="<?= e($payout['amount']) ?>"><i class="bi bi-upload me-1"></i>แนบสลิป</button>
                  <button type="button" class="btn btn-soft btn-sm text-danger" data-admin-action="payout-reject" data-id="<?= (int) $payout['id'] ?>">ปฏิเสธ</button>
                <?php else : ?>-<?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$payouts) : ?><tr><td colspan="8" class="text-center text-muted py-4">ยังไม่มีรายการ Payout</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<div class="modal fade" id="payoutModal" tabindex="-1" aria-labelledby="payoutTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="payoutTitle">สร้าง Payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body">
        <div class="text-muted small">Creator</div><div class="fw-bold mb-2" id="payoutName"></div>
        <div class="text-muted small">ยอดคงเหลือ</div><div class="fs-4 fw-bold mb-3" id="payoutBalance"></div>
        <label class="form-label" for="payoutAmount">ยอดที่ต้องการโอน (฿)</label>
        <input type="number" id="payoutAmount" class="form-control" step="0.01" min="1">
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">ยกเลิก</button><button type="button" class="btn btn-primary" id="payoutConfirm">ยืนยันสร้าง Payout</button></div>
    </div>
  </div>
</div>

<div class="modal fade" id="transferModal" tabindex="-1" aria-labelledby="transferTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="transferTitle">แนบสลิปการโอน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
      <div class="modal-body">
        <div class="text-muted small">Creator</div><div class="fw-bold mb-2" id="transferName"></div>
        <div class="text-muted small">ยอดโอน</div><div class="fs-4 fw-bold mb-3" id="transferAmount"></div>
        <label class="form-label" for="transferSlip">สลิปการโอน</label>
        <input type="file" id="transferSlip" class="form-control" accept="image/jpeg,image/png,image/webp">
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-soft" data-bs-dismiss="modal">ยกเลิก</button><button type="button" class="btn btn-primary" id="transferConfirm">ยืนยันโอนแล้ว</button></div>
    </div>
  </div>
</div>
