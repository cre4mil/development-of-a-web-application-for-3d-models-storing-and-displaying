<?php
/**
 * checkout.php — หน้า Checkout สรุปคำสั่งซื้อ + PromptPay QR + อัปโหลดสลิป
 */
require 'connect.php';
if (!$_SESSION['uid']) { header('Location: index.php?login=1'); exit; }

$uid   = (int)$_SESSION['uid'];
$uname = htmlspecialchars($_SESSION['uname'] ?? '', ENT_QUOTES);

// ดึงรายการในตะกร้า
$cartIds = $_SESSION['cart'] ?? [];

// ถ้าตะกร้าว่าง redirect กลับ
if (empty($cartIds)) {
    header('Location: index.php?cart_empty=1');
    exit;
}

$placeholders = implode(',', array_fill(0, count($cartIds), '?'));
$st = $pdo->prepare("
    SELECT m.id, m.title, m.thumb, m.price, u.username AS creator_name, u.id AS creator_id
    FROM models m
    JOIN users u ON u.id = m.user_id
    WHERE m.id IN ($placeholders) AND m.is_public = 1 AND m.price > 0
");
$st->execute($cartIds);
$cartItems = $st->fetchAll();

// กรองโมเดลของตัวเองออก
$cartItems = array_filter($cartItems, fn($i) => (int)$i['creator_id'] !== $uid);
$cartItems = array_values($cartItems);

if (empty($cartItems)) {
    header('Location: index.php?cart_empty=1');
    exit;
}

$total      = array_sum(array_column($cartItems, 'price'));
$feePct     = PLATFORM_FEE_PCT;
$ppId       = PROMPTPAY_ID;
$ppName     = PROMPTPAY_NAME;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Checkout — 3D Gallery</title>
  <meta name="description" content="สรุปรายการสั่งซื้อและชำระเงินสำหรับ 3D Gallery">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --bg:        #f4f4f6;
      --surface:   #ffffff;
      --surface2:  #f0f0f2;
      --border:    rgba(0,0,0,0.09);
      --accent:    #111111;
      --accent2:   #555555;
      --text:      #111111;
      --muted:     #777777;
      --danger:    #d93025;
      --warn:      #f59e0b;
    }
    * { box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    .site-header {
      background: #111;
      padding: 1rem 0;
      position: sticky; top: 0; z-index: 100;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    .site-header .container {
      display: flex; justify-content: space-between; align-items: center;
      max-width: 1200px; margin: 0 auto; padding: 0 1.5rem;
    }
    .logo {
      font-weight: 800; font-size: 1.2rem;
      color: #fff; text-decoration: none;
      display: flex; align-items: center; gap: .4rem;
    }
    .logo .logo-icon {
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .logo .logo-icon i { color: #fff; font-size: 1.4rem; }
    .btn-back {
      background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.2);
      color: #fff; font-size: .85rem; padding: .45rem 1rem;
      border-radius: 8px; text-decoration: none;
      display: inline-flex; align-items: center; gap: .35rem;
      font-weight: 500; transition: .2s;
    }
    .btn-back:hover { background: rgba(255,255,255,0.22); color: #fff; }

    /* ── Layout ── */
    .checkout-wrap {
      max-width: 1000px; margin: 0 auto;
      padding: 2.5rem 1.5rem 5rem;
    }
    .checkout-title {
      font-size: 1.75rem; font-weight: 800;
      margin-bottom: 0.25rem;
    }
    .checkout-subtitle { color: var(--muted); font-size: 0.9rem; margin-bottom: 2rem; }

    .checkout-grid {
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 1.5rem;
      align-items: start;
    }
    @media (max-width: 768px) {
      .checkout-grid { grid-template-columns: 1fr; }
    }

    /* ── Card ── */
    .card-panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2rem;
      box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    }
    .card-panel-title {
      font-weight: 700; font-size: 1rem;
      display: flex; align-items: center; gap: 0.5rem;
      margin-bottom: 1.25rem;
    }
    .card-panel-title i { color: var(--accent); font-size: 1.1rem; }

    /* ── Cart Items ── */
    .cart-item {
      display: flex; align-items: center; gap: 1rem;
      padding: 0.875rem 0;
      border-bottom: 1px solid var(--border);
    }
    .cart-item:last-child { border-bottom: none; }
    .cart-thumb {
      width: 48px; height: 48px; border-radius: 8px;
      object-fit: cover; background: #eee;
      flex-shrink: 0;
    }
    .cart-item-info { flex: 1; min-width: 0; }
    .cart-item-title {
      font-weight: 600; font-size: 0.875rem;
      color: #111;
    }
    .cart-item-creator { color: var(--muted); font-size: 0.75rem; margin-top: 2px; }
    .cart-item-price {
      font-weight: 700; color: #111;
      white-space: nowrap;
    }
    .btn-remove-item {
      background: transparent; border: none; color: var(--muted);
      padding: 0.25rem; border-radius: 6px; cursor: pointer;
      transition: 0.2s; line-height: 1;
    }
    .btn-remove-item:hover { color: var(--danger); background: rgba(239,69,101,0.1); }

    /* ── Summary ── */
    .summary-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.4rem 0; font-size: 0.875rem; color: #333;
    }
    .summary-row.total {
      border-top: 2px solid rgba(0,0,0,0.1);
      margin-top: 0.5rem; padding-top: 0.75rem;
      font-weight: 800; font-size: 1rem; color: #111;
    }
    .section-label {
      font-size: 0.75rem; font-weight: 600; color: #888;
      text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.75rem;
    }
    .text-accent { color: var(--accent); }
    .text-success-c { color: var(--accent2); }

    /* ── QR / Payment ── */
    .qr-wrap {
      text-align: center; padding: 1rem 0;
    }
    .qr-wrap canvas, .qr-wrap img {
      max-width: 200px; border-radius: 12px;
      background: #fff; padding: 12px;
    }
    .promptpay-name {
      font-weight: 700; font-size: 1rem;
      margin-top: 0.75rem;
    }
    .promptpay-id-display {
      color: var(--muted); font-size: 0.8rem; margin-top: 0.2rem;
    }
    .amount-highlight {
      display: inline-block;
      color: #111;
      font-size: 1.6rem; font-weight: 800; margin-top: 0.5rem;
    }
    .amount-label { color: var(--muted); font-size: 0.8rem; }

    /* ── Upload Slip ── */
    .slip-drop-zone {
      border: 2px dashed rgba(0,0,0,0.15);
      border-radius: 12px;
      padding: 1.5rem;
      text-align: center;
      cursor: pointer;
      transition: 0.2s;
      position: relative;
      background: #fafafa;
    }
    .slip-drop-zone:hover, .slip-drop-zone.drag-over {
      border-color: #111;
      background: #f5f5f5;
    }
    .slip-drop-zone input[type=file] {
      position: absolute; inset: 0; opacity: 0; cursor: pointer;
    }
    .slip-drop-icon { font-size: 2.5rem; color: var(--muted); margin-bottom: 0.5rem; }
    .slip-drop-text { color: var(--muted); font-size: 0.875rem; }
    .slip-preview {
      display: none; margin-top: 1rem;
      border-radius: 10px; overflow: hidden;
    }
    .slip-preview img { width: 100%; border-radius: 10px; }

    .btn-checkout {
      width: 100%;
      background: #111;
      border: none; color: #fff; font-weight: 700;
      font-size: 1rem; border-radius: 14px;
      padding: 1rem; margin-top: 1rem;
      cursor: pointer; transition: 0.3s;
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    }
    .btn-checkout:hover { background: #333; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
    .btn-checkout:disabled { opacity: 0.35; cursor: not-allowed; transform: none; }
    .btn-checkout .spinner-border { width: 1rem; height: 1rem; border-width: 2px; }

    /* ── Status Banner ── */
    .step-indicator {
      display: flex; align-items: center; gap: 0;
      margin-bottom: 2rem; overflow-x: auto;
    }
    .step-item {
      display: flex; flex-direction: column; align-items: center;
      min-width: 80px;
    }
    .step-dot {
      width: 32px; height: 32px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.8rem; font-weight: 700;
      background: var(--surface2); border: 2px solid var(--border);
      color: var(--muted); margin-bottom: 0.35rem;
    }
    .step-dot.active { background: #fff; border-color: #fff; color: #111; }
    .step-dot.done   { background: #555; border-color: #555; color: #fff; }
    .step-label { font-size: 0.65rem; color: var(--muted); text-align: center; }
    .step-line {
      flex: 1; height: 2px; background: var(--border);
      margin-bottom: 1.5rem;
    }

    /* ── Success State ── */
    #successPanel {
      display: none;
      text-align: center; padding: 3rem 1rem;
    }
    .success-icon { font-size: 4rem; color: #198754; margin-bottom: 1rem; }
    .success-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem; }
    .success-sub { color: var(--muted); font-size: 0.9rem; }
    .order-ref-box {
      background: #f8f8f8; border: 1px solid rgba(0,0,0,0.08);
      border-radius: 10px; padding: 1rem; margin: 1.25rem 0;
      font-family: monospace; font-size: 1.1rem; font-weight: 700;
      color: #111; letter-spacing: 2px;
    }
  </style>
</head>
<body>

<!-- Header -->
<header class="site-header">
  <div class="container">
    <a href="index.php" class="logo">
      <span class="logo-icon"><i class="bi bi-box"></i></span>
      3D Gallery
    </a>
    <a href="index.php" class="btn-back"><i class="bi bi-arrow-left me-1"></i>กลับ</a>
  </div>
</header>

<div class="checkout-wrap">

  <!-- Step Indicator -->
  <div class="step-indicator">
    <div class="step-item">
      <div class="step-dot done"><i class="bi bi-cart-check"></i></div>
      <div class="step-label">ตะกร้า</div>
    </div>
    <div class="step-line"></div>
    <div class="step-item">
      <div class="step-dot active">2</div>
      <div class="step-label">ชำระเงิน</div>
    </div>
    <div class="step-line"></div>
    <div class="step-item">
      <div class="step-dot">3</div>
      <div class="step-label">รอยืนยัน</div>
    </div>
    <div class="step-line"></div>
    <div class="step-item">
      <div class="step-dot">4</div>
      <div class="step-label">ดาวน์โหลด</div>
    </div>
  </div>

  <h1 class="checkout-title"><i class="bi bi-bag-check me-2" style="color:var(--accent)"></i>Checkout</h1>
  <p class="checkout-subtitle">ตรวจสอบรายการและชำระเงินด้วย PromptPay</p>

  <div id="checkoutForm">
    <div class="checkout-grid">

      <!-- Left: Order Items -->
      <div>
        <div class="card-panel mb-3">
          <div class="card-panel-title">
            <i class="bi bi-bag"></i>
            รายการสั่งซื้อ (<?= count($cartItems) ?> รายการ)
          </div>
          <div id="cartItemsList">
            <?php foreach ($cartItems as $item): ?>
            <div class="cart-item" data-id="<?= $item['id'] ?>" data-price="<?= $item['price'] ?>">
              <img
                src="uploads/<?= htmlspecialchars($item['thumb'] ?? '') ?>"
                class="cart-thumb"
                onerror="this.src='https://placehold.co/52x52/1a1a24/6e6e8a?text=3D'"
                alt="">
              <div class="cart-item-info">
                <div class="cart-item-title"><?= htmlspecialchars($item['title']) ?></div>
                <div class="cart-item-creator"><i class="bi bi-person me-1"></i><?= htmlspecialchars($item['creator_name']) ?></div>
              </div>
              <div class="cart-item-price">฿<?= number_format($item['price'], 2) ?></div>
              <button class="btn-remove-item" onclick="removeItem(<?= $item['id'] ?>)" title="ลบออก">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Summary -->
        <div class="card-panel">
          <div class="card-panel-title"><i class="bi bi-receipt"></i>สรุปยอด</div>
          <div class="summary-row">
            <span>ราคารวม</span>
            <span id="subtotalDisplay">฿<?= number_format($total, 2) ?></span>
          </div>
          <div class="summary-row">
            <span>ค่าธรรมเนียมบริการ</span>
            <span class="text-accent">ฟรี</span>
          </div>
          <div class="summary-row total">
            <span>ยอดที่ต้องชำระ</span>
            <span class="text-success-c" id="totalDisplay">฿<?= number_format($total, 2) ?></span>
          </div>
          <div class="mt-2" style="font-size:0.75rem; color:var(--muted)">
            <i class="bi bi-info-circle me-1"></i>
            ค่าธรรมเนียมแพลตฟอร์ม <?= PLATFORM_FEE_PCT ?>% จะถูกหักจากรายได้ Creator อัตโนมัติ
          </div>
        </div>
      </div>

      <!-- Right: Payment -->
      <div>
        <div class="card-panel">
          <div class="card-panel-title"><i class="bi bi-qr-code"></i>ชำระผ่าน PromptPay</div>

          <div class="qr-wrap">
            <canvas id="qrCanvas"></canvas>
            <div class="promptpay-name"><?= htmlspecialchars($ppName) ?></div>
            <div class="promptpay-id-display"><?= htmlspecialchars($ppId) ?></div>
            <div class="amount-label mt-2">ยอดที่ต้องโอน</div>
            <div class="amount-highlight" id="qrAmountDisplay">฿<?= number_format($total, 2) ?></div>
          </div>

          <hr style="border-color:var(--border); margin: 1.25rem 0;">

          <!-- Upload Slip -->
          <div class="card-panel-title"><i class="bi bi-upload"></i>แนบหลักฐานการโอน</div>
          <div class="slip-drop-zone" id="slipDropZone">
            <input type="file" id="slipInput" accept="image/jpeg,image/png,image/webp" required>
            <div class="slip-drop-icon"><i class="bi bi-image"></i></div>
            <div class="slip-drop-text">คลิกหรือลากไฟล์สลิปมาวางที่นี่<br><small>JPG, PNG, WEBP — สูงสุด 5MB</small></div>
          </div>
          <div class="slip-preview" id="slipPreview">
            <img id="slipPreviewImg" alt="slip preview">
            <button class="btn-remove-item mt-2" onclick="clearSlip()" style="display:block;margin:0 auto;">
              <i class="bi bi-x-circle me-1"></i> ลบสลิป
            </button>
          </div>

          <button class="btn-checkout" id="btnSubmit" disabled>
            <i class="bi bi-lock-fill"></i>
            ยืนยันคำสั่งซื้อ
          </button>

          <div style="font-size:0.72rem; color:var(--muted); text-align:center; margin-top:0.75rem;">
            <i class="bi bi-shield-check me-1"></i>
            แอดมินจะตรวจสอบสลิปและปลดล็อกการดาวน์โหลดให้คุณ
          </div>
        </div>
      </div>

    </div><!-- end grid -->
  </div><!-- end #checkoutForm -->

  <!-- Success Panel -->
  <div id="successPanel" class="card-panel">
    <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
    <div class="success-title">ส่งคำสั่งซื้อเรียบร้อย!</div>
    <div class="success-sub">แอดมินจะตรวจสอบสลิปและปลดล็อกให้คุณภายในไม่นาน</div>
    <div class="order-ref-box" id="orderRefDisplay"></div>
    <div class="d-flex gap-2 justify-content-center mt-3 flex-wrap">
      <a href="payment.php" class="btn-back"><i class="bi bi-receipt me-1"></i>ดูคำสั่งซื้อ</a>
      <a href="index.php" class="btn-back"><i class="bi bi-house me-1"></i>กลับหน้าหลัก</a>
    </div>
  </div>

</div><!-- end checkout-wrap -->

<!-- QR Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
// ─── State ───────────────────────────────────────────────
let cartData = <?= json_encode(array_map(fn($i) => [
  'id'    => (int)$i['id'],
  'price' => (float)$i['price'],
  'title' => $i['title'],
], $cartItems)) ?>;
let total = <?= $total ?>;
const ppId = <?= json_encode($ppId) ?>;

// ─── PromptPay QR ────────────────────────────────────────
function generatePromptPayPayload(id, amount) {
  // EMV QRCPS format for PromptPay
  function fmtTLV(tag, val) {
    const len = String(val.length).padStart(2, '0');
    return tag + len + val;
  }
  const mobile = id.replace(/\D/g, '');
  const phoneTag = '0066' + mobile.substring(1); // country code
  const aid = fmtTLV('00', '0001') + fmtTLV('01', phoneTag.startsWith('0066') ? phoneTag : '0066' + mobile.substring(1));
  const merchant = fmtTLV('00', 'A000000677010111') + fmtTLV('01', '0066' + mobile.substring(1));

  let payload = fmtTLV('00', '01') + fmtTLV('01', '12') +
    fmtTLV('29', fmtTLV('00','A000000677010111') + fmtTLV('01', '0066' + mobile.substring(1))) +
    '5802TH' + fmtTLV('54', amount.toFixed(2)) + fmtTLV('58', 'TH') + fmtTLV('62', fmtTLV('07','3DSHOP'));

  // CRC16
  function crc16(str) {
    let crc = 0xFFFF;
    for (let i = 0; i < str.length; i++) {
      crc ^= str.charCodeAt(i) << 8;
      for (let j = 0; j < 8; j++) {
        crc = (crc & 0x8000) ? (crc << 1) ^ 0x1021 : crc << 1;
      }
    }
    return (crc & 0xFFFF).toString(16).toUpperCase().padStart(4, '0');
  }
  payload += '6304';
  payload += crc16(payload);
  return payload;
}

function renderQR(amount) {
  const canvas = document.getElementById('qrCanvas');
  const payload = generatePromptPayPayload(ppId, amount);
  QRCode.toCanvas(canvas, payload, { width: 200, color: { dark: '#000', light: '#fff' } });
}

function updateDisplay() {
  total = cartData.reduce((s, i) => s + i.price, 0);
  document.getElementById('subtotalDisplay').textContent = '฿' + total.toLocaleString('th-TH', {minimumFractionDigits:2});
  document.getElementById('totalDisplay').textContent = '฿' + total.toLocaleString('th-TH', {minimumFractionDigits:2});
  document.getElementById('qrAmountDisplay').textContent = '฿' + total.toLocaleString('th-TH', {minimumFractionDigits:2});
  renderQR(total);
}

// ─── Remove item ─────────────────────────────────────────
async function removeItem(modelId) {
  if (cartData.length <= 1) {
    if (!confirm('ตะกร้าจะว่างเปล่า ต้องการลบใช่ไหม?')) return;
  }
  await fetch('cart.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({action:'remove', model_id: modelId})
  });
  cartData = cartData.filter(i => i.id !== modelId);
  const el = document.querySelector(`.cart-item[data-id="${modelId}"]`);
  if (el) el.remove();
  updateCartBadge();
  if (cartData.length === 0) {
    window.location = 'index.php?cart_empty=1';
    return;
  }
  updateDisplay();
}

function updateCartBadge() {
  const badges = document.querySelectorAll('.cart-count-badge');
  badges.forEach(b => b.textContent = cartData.length);
}

// ─── Slip Upload ──────────────────────────────────────────
const slipInput    = document.getElementById('slipInput');
const slipPreview  = document.getElementById('slipPreview');
const slipImg      = document.getElementById('slipPreviewImg');
const btnSubmit    = document.getElementById('btnSubmit');
const dropZone     = document.getElementById('slipDropZone');

slipInput.addEventListener('change', handleSlipFile);

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
  e.preventDefault(); dropZone.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file) { slipInput.files = e.dataTransfer.files; handleSlipFile(); }
});

function handleSlipFile() {
  const file = slipInput.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { alert('ไฟล์ใหญ่เกิน 5MB'); return; }
  const reader = new FileReader();
  reader.onload = e => {
    slipImg.src = e.target.result;
    slipPreview.style.display = 'block';
    dropZone.style.display = 'none';
    btnSubmit.disabled = false;
  };
  reader.readAsDataURL(file);
}

function clearSlip() {
  slipInput.value = '';
  slipPreview.style.display = 'none';
  dropZone.style.display = 'block';
  btnSubmit.disabled = true;
}

// ─── Submit Order ─────────────────────────────────────────
btnSubmit.addEventListener('click', async () => {
  if (!slipInput.files[0]) { alert('กรุณาแนบสลิปการโอนเงิน'); return; }

  btnSubmit.disabled = true;
  btnSubmit.innerHTML = '<span class="spinner-border me-2"></span>กำลังส่ง...';

  const fd = new FormData();
  fd.append('action', 'create_order');
  fd.append('slip', slipInput.files[0]);
  cartData.forEach(i => fd.append('model_ids[]', i.id));

  try {
    const res = await fetch('payment.php', { method: 'POST', body: fd });
    const d   = await res.json();

    if (d.ok) {
      document.getElementById('checkoutForm').style.display = 'none';
      const sp = document.getElementById('successPanel');
      sp.style.display = 'block';
      document.getElementById('orderRefDisplay').textContent = 'หมายเลขอ้างอิง: ' + d.order_ref;
      // update step indicator
      document.querySelectorAll('.step-dot')[1].className = 'step-dot done';
      document.querySelectorAll('.step-dot')[1].innerHTML = '<i class="bi bi-check-lg"></i>';
      document.querySelectorAll('.step-dot')[2].className = 'step-dot active';
    } else {
      alert('เกิดข้อผิดพลาด: ' + (d.error || 'unknown'));
      btnSubmit.disabled = false;
      btnSubmit.innerHTML = '<i class="bi bi-lock-fill"></i> ยืนยันคำสั่งซื้อ';
    }
  } catch (err) {
    alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ กรุณาลองใหม่');
    btnSubmit.disabled = false;
    btnSubmit.innerHTML = '<i class="bi bi-lock-fill"></i> ยืนยันคำสั่งซื้อ';
  }
});

// ─── Init ─────────────────────────────────────────────────
updateDisplay();
</script>
</body>
</html>
