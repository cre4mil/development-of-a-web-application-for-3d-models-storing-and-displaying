/**
 * Admin pages: user / model deletion, order approval, payouts and platform fee.
 * Requires core.js.
 */
(() => {
  'use strict';

  const { $, modal } = App;

  const runAdmin = async (url, data, doneMessage) => {
    const result = await App.attempt(() => App.post(url, data));
    if (result) App.toast(result.message || doneMessage, 'success');
    return result;
  };

  const handlers = {
    async delete_user(button) {
      const accepted = await App.confirm({ title: 'ลบผู้ใช้งาน', message: button.dataset.confirm, ok: 'ลบ' });
      if (accepted && await runAdmin('api/admin.php', { action: 'delete_user', id: button.dataset.id }, 'ลบผู้ใช้งานแล้ว')) {
        button.closest('tr').remove();
      }
    },

    async delete_model(button) {
      const accepted = await App.confirm({ title: 'ลบโมเดล', message: button.dataset.confirm, ok: 'ลบ' });
      if (accepted && await runAdmin('api/admin.php', { action: 'delete_model', id: button.dataset.id }, 'ลบโมเดลแล้ว')) {
        button.closest('tr').remove();
      }
    },

    'view-slip'(button) {
      $('#slipImage').src = button.dataset.src;
      modal('slipModal').show();
    },

    async 'order-approve'(button) {
      if (!await App.confirm({ title: 'อนุมัติคำสั่งซื้อ', message: 'ตรวจสลิปแล้วและต้องการอนุมัติคำสั่งซื้อนี้?', ok: 'อนุมัติ' })) return;
      if (await runAdmin('api/orders.php', { action: 'status', order_id: button.dataset.id, status: 'approved' }, 'อนุมัติแล้ว')) {
        setTimeout(() => location.reload(), 700);
      }
    },

    'order-reject'(button) {
      $('#rejectNote').value = '';
      $('#rejectConfirm').dataset.id = button.dataset.id;
      modal('rejectModal').show();
    },

    'payout-create'(button) {
      $('#payoutName').textContent = button.dataset.name;
      $('#payoutBalance').textContent = `฿${Number(button.dataset.balance).toLocaleString('th-TH', { minimumFractionDigits: 2 })}`;
      $('#payoutAmount').value = button.dataset.balance;
      $('#payoutAmount').max = button.dataset.balance;
      $('#payoutConfirm').dataset.id = button.dataset.id;
      modal('payoutModal').show();
    },

    'payout-transfer'(button) {
      $('#transferName').textContent = button.dataset.name;
      $('#transferAmount').textContent = `฿${Number(button.dataset.amount).toLocaleString('th-TH', { minimumFractionDigits: 2 })}`;
      $('#transferSlip').value = '';
      $('#transferConfirm').dataset.id = button.dataset.id;
      modal('transferModal').show();
    },

    async 'payout-reject'(button) {
      if (!await App.confirm({ title: 'ปฏิเสธ Payout', message: 'ยอดเงินจะถูกคืนเข้ากระเป๋าของ Creator', ok: 'ปฏิเสธ' })) return;
      if (await runAdmin('api/admin.php', { action: 'reject_payout', payout_id: button.dataset.id }, 'ปฏิเสธแล้ว')) {
        setTimeout(() => location.reload(), 700);
      }
    },
  };

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-admin-action]');
    if (button) handlers[button.dataset.adminAction]?.(button);
  });

  $('#rejectConfirm')?.addEventListener('click', async (event) => {
    modal('rejectModal').hide();
    const done = await runAdmin('api/orders.php', { action: 'status', order_id: event.currentTarget.dataset.id, status: 'rejected', note: $('#rejectNote').value }, 'ปฏิเสธแล้ว');
    if (done) setTimeout(() => location.reload(), 700);
  });

  $('#payoutConfirm')?.addEventListener('click', async (event) => {
    const done = await runAdmin('api/admin.php', { action: 'create_payout', creator_id: event.currentTarget.dataset.id, amount: $('#payoutAmount').value }, 'สร้าง Payout แล้ว');
    if (done) {
      modal('payoutModal').hide();
      setTimeout(() => location.reload(), 700);
    }
  });

  $('#transferConfirm')?.addEventListener('click', async (event) => {
    const file = $('#transferSlip').files[0];
    if (!file) {
      App.toast('กรุณาแนบสลิปการโอน', 'danger');
      return;
    }
    const data = new FormData();
    data.append('action', 'transfer_payout');
    data.append('payout_id', event.currentTarget.dataset.id);
    data.append('slip', file);
    const done = await runAdmin('api/admin.php', data, 'บันทึกการโอนแล้ว');
    if (done) {
      modal('transferModal').hide();
      setTimeout(() => location.reload(), 700);
    }
  });

  $('#feeForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const value = $('#feeInput').value;
    const done = await runAdmin('api/admin.php', { action: 'update_fee', fee_pct: value }, 'บันทึกค่าธรรมเนียมแล้ว');
    if (done) $('#feeCurrent').textContent = String(done.fee_pct);
  });
})();
