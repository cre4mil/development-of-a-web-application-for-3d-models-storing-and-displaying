/**
 * Model page: viewer statistics, share / embed dialogs, comments and the
 * PromptPay checkout. Requires core.js and viewer.js.
 */
(() => {
  'use strict';

  const { $, $$, modal } = App;
  const page = $('.page[data-model-id]');
  if (!page) return;
  const modelId = page.dataset.modelId;
  const modelTitle = page.dataset.title;

  /* ---------- Statistics from the loaded model ---------- */
  document.addEventListener('viewer:loaded', (event) => {
    const s = event.detail;
    const values = {
      triangles: ModelViewer.formatCount(s.triangles),
      vertices: ModelViewer.formatCount(s.vertices),
      meshes: s.meshes,
      materials: s.materials,
      textures: s.textures,
      dimensions: s.dimensions.map((n) => n.toFixed(2)).join(' × '),
    };
    $$('[data-stat]').forEach((target) => { target.textContent = values[target.dataset.stat]; });
  });

  App.actions['toggle-info'] = () => $('#infoPanel').classList.toggle('open');

  /* ---------- Share & embed ---------- */
  const absolute = (path) => new URL(path, document.baseURI).href;
  document.getElementById('shareModal')?.addEventListener('show.bs.modal', () => {
    const link = absolute(`model.php?id=${modelId}`);
    $('#shareLink').value = link;
    $('#shareFb').href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`;
    $('#shareX').href = `https://twitter.com/intent/tweet?url=${encodeURIComponent(link)}&text=${encodeURIComponent(modelTitle)}`;
    const lineText = `${modelTitle} ${link}`;
    $('#shareLine').href = `https://line.me/R/msg/text/?${encodeURIComponent(lineText)}`;
  });
  document.getElementById('embedModal')?.addEventListener('show.bs.modal', () => {
    const src = absolute(`model.php?id=${modelId}&embed=1`);
    const title = modelTitle.replaceAll('"', '&quot;');
    $('#embedCode').value = `<iframe title="${title}" src="${src}" width="640" height="480" frameborder="0" allow="autoplay; fullscreen; xr-spatial-tracking" allowfullscreen></iframe>`;
  });

  /* ---------- Comments ---------- */
  const list = $('#commentList');
  const renderComment = (comment, me, isAdmin) => {
    const row = document.createElement('div');
    row.className = 'comment';
    row.dataset.id = comment.id;
    const avatar = document.createElement('span');
    avatar.className = 'avatar avatar-sm';
    avatar.textContent = comment.username.charAt(0).toUpperCase();
    const content = document.createElement('div');
    content.className = 'flex-grow-1';
    const who = document.createElement('div');
    who.className = 'who';
    who.textContent = comment.username;
    const when = document.createElement('span');
    when.className = 'when';
    when.textContent = comment.created_at;
    who.append(when);
    const body = document.createElement('div');
    body.className = 'body';
    body.textContent = comment.body;
    content.append(who, body);
    row.append(avatar, content);
    if (me && (me === Number(comment.user_id) || isAdmin)) {
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'btn btn-ghost btn-sm text-danger';
      remove.dataset.action = 'comment-delete';
      remove.dataset.id = comment.id;
      remove.setAttribute('aria-label', 'ลบความคิดเห็น');
      remove.innerHTML = '<i class="bi bi-trash3"></i>';
      row.append(remove);
    }
    return row;
  };

  const showCount = (count) => {
    $('#commentCount').textContent = `(${count})`;
    $('#commentCountTop').textContent = count;
  };

  const loadComments = async () => {
    const data = await App.attempt(() => App.api(`api/social.php?action=comments&model_id=${modelId}`));
    if (!data) return;
    if (data.comments.length === 0) {
      list.innerHTML = '<p class="text-muted mb-0">ยังไม่มีความคิดเห็น — เป็นคนแรกที่แสดงความคิดเห็น</p>';
    } else {
      list.replaceChildren(...data.comments.map((comment) => renderComment(comment, data.uid, data.admin)));
    }
    showCount(data.comments.length);
  };

  App.actions['comment-delete'] = async (button) => {
    if (!await App.confirm({ title: 'ลบความคิดเห็น', message: 'ต้องการลบความคิดเห็นนี้หรือไม่?', ok: 'ลบ' })) return;
    const data = await App.attempt(() => App.post('api/social.php', { action: 'comment_delete', id: button.dataset.id }));
    if (data) loadComments();
  };

  $('#commentForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const field = $('#commentBody');
    if (field.value.trim() === '') return;
    const data = await App.attempt(() => App.post('api/social.php', { action: 'comment_add', model_id: modelId, body: field.value }));
    if (data) {
      field.value = '';
      loadComments();
    }
  });
  loadComments();

  /* ---------- Checkout ---------- */
  const payment = document.getElementById('paymentModal');
  if (payment) {
    const BANKS = { kbank: 'ธนาคารกสิกรไทย (KBANK)', scb: 'ธนาคารไทยพาณิชย์ (SCB)', bbl: 'ธนาคารกรุงเทพ (BBL)', ktb: 'ธนาคารกรุงไทย (KTB)', krungsri: 'ธนาคารกรุงศรีอยุธยา (BAY)' };
    const QR_LIB = ['https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js', 'sha384-8FWZA6BGMXhsfO+BLtrJK0We6gg5o1JyO8xQm6peWDEUs17ACA5ziE/NIAkl9z2k'];

    payment.addEventListener('show.bs.modal', async () => {
      ['#qrError', '#qrBox', '#bankInfo', '#qrPrice'].forEach((selector) => $(selector).classList.add('d-none'));
      $('#qrLoading').classList.remove('d-none');
      const data = await App.api(`api/orders.php?action=qr&model_id=${modelId}`).catch(() => null);
      $('#qrLoading').classList.add('d-none');
      if (!data) {
        $('#qrError').classList.remove('d-none');
        return;
      }
      if (data.payload) {
        await App.loadScript(...QR_LIB);
        const qr = qrcode(0, 'M');
        qr.addData(data.payload);
        qr.make();
        $('#qrBox').innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
        $('#qrBox').classList.remove('d-none');
        $('#paymentHint').textContent = 'สแกน QR Code ด้วยแอปธนาคารหรือ TrueMoney Wallet';
      } else {
        $('#bankName').textContent = BANKS[data.method] || data.method;
        $('#bankAccount').textContent = data.account;
        $('#bankHolder').textContent = data.name;
        $('#bankInfo').classList.remove('d-none');
        $('#paymentHint').textContent = 'โอนเงินเข้าบัญชีด้านล่างนี้';
      }
      $('#qrPrice').classList.remove('d-none');
    });

    $('#paymentForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const submit = $('button[type="submit"]', event.currentTarget);
      submit.disabled = true;
      submit.textContent = 'กำลังส่ง…';
      const data = await App.attempt(() => App.post('api/orders.php', new FormData(event.currentTarget)));
      if (data) {
        App.toast(data.message, 'success');
        modal('paymentModal').hide();
        setTimeout(() => location.reload(), 900);
        return;
      }
      submit.disabled = false;
      submit.textContent = 'ยืนยันการชำระเงิน';
    });
  }
})();
