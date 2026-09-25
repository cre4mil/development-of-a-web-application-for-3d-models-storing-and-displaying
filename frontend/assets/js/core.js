/**
 * Core UI helpers shared by every page: API client, toasts, confirm dialog and
 * the delegated click handlers for like / save / follow / edit / hide / delete.
 *
 * Markup contract: any element with data-action="name" runs App.actions[name](element, event).
 */
(() => {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';
  const isAuth = () => document.body.dataset.auth === '1';
  const modal = (id) => bootstrap.Modal.getOrCreateInstance(document.getElementById(id));

  const App = (window.App = { $, $$, csrf, isAuth, modal, actions: {} });

  /* ---------- Toasts ---------- */
  App.toast = (message, type = 'info') => {
    const stack = document.getElementById('toastStack');
    const item = document.createElement('div');
    item.className = `toast-item ${type}`;
    const icon = document.createElement('i');
    const icons = { danger: 'bi-exclamation-circle', success: 'bi-check-circle' };
    icon.className = `bi ${icons[type] || 'bi-info-circle'}`;
    const text = document.createElement('span');
    text.textContent = message;
    item.append(icon, text);
    stack.append(item);
    setTimeout(() => item.remove(), 4500);
  };

  /* ---------- API client ---------- */
  App.api = async (url, { method = 'GET', data = null } = {}) => {
    const options = { method, headers: { 'X-CSRF-Token': csrf(), Accept: 'application/json' } };
    if (data instanceof FormData) {
      options.body = data;
    } else if (data) {
      options.body = new URLSearchParams(data);
    }
    let response;
    try {
      response = await fetch(url, options);
    } catch {
      throw new Error('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้');
    }
    const json = await response.json().catch(() => null);
    if (!response.ok || !json || json.ok === false) {
      const error = new Error(json?.message || `เกิดข้อผิดพลาด (${response.status})`);
      error.status = response.status;
      error.code = json?.error;
      throw error;
    }
    return json;
  };
  App.post = (url, data) => App.api(url, { method: 'POST', data });

  /** Runs an async task and reports failures as a toast. Returns the result or null. */
  App.attempt = async (task) => {
    try {
      return await task();
    } catch (error) {
      App.toast(error.message, 'danger');
      return null;
    }
  };

  App.requireLogin = () => {
    if (!isAuth()) {
      modal('loginModal').show();
    }
    return isAuth();
  };

  App.confirm = ({ title = 'ยืนยันการดำเนินการ', message = '', ok = 'ยืนยัน' } = {}) =>
    new Promise((resolve) => {
      const element = document.getElementById('confirmModal');
      $('#confirmTitle').textContent = title;
      $('#confirmMessage').textContent = message;
      $('#confirmOk').textContent = ok;
      let accepted = false;
      const onOk = () => {
        accepted = true;
        modal('confirmModal').hide();
      };
      const onHidden = () => {
        $('#confirmOk').removeEventListener('click', onOk);
        resolve(accepted);
      };
      $('#confirmOk').addEventListener('click', onOk, { once: true });
      element.addEventListener('hidden.bs.modal', onHidden, { once: true });
      modal('confirmModal').show();
    });

  App.loadScript = (() => {
    const cache = new Map();
    return (src, integrity = '') => {
      if (!cache.has(src)) {
        cache.set(src, new Promise((resolve, reject) => {
          const script = document.createElement('script');
          script.src = src;
          if (integrity) {
            script.integrity = integrity;
            script.crossOrigin = 'anonymous';
          }
          script.onload = resolve;
          script.onerror = () => reject(new Error(`โหลดสคริปต์ไม่สำเร็จ: ${src}`));
          document.head.append(script);
        }));
      }
      return cache.get(src);
    };
  })();

  /* ---------- UI state updaters ---------- */
  App.applyLike = (id, liked, count) => {
    $$(`[data-action="like"][data-id="${id}"]`).forEach((button) => {
      button.dataset.liked = liked ? '1' : '0';
      button.classList.toggle('is-liked', liked);
      const icon = $('i', button);
      if (icon) {
        icon.className = `bi ${liked ? 'bi-heart-fill' : 'bi-heart'}${icon.className.includes('me-1') ? ' me-1' : ''}`;
      }
      const label = $('[data-label]', button);
      if (label) {
        label.textContent = liked ? 'Liked' : 'Like';
      }
    });
    $$(`[data-card="${id}"] [data-like-count], .page[data-model-id="${id}"] [data-like-count], #quickViewModal[data-current="${id}"] #qvLikeCount`)
      .forEach((element) => { element.textContent = count; });
  };

  App.applySaved = (id, saved) => {
    $$(`[data-action="save"][data-id="${id}"]`).forEach((button) => {
      button.dataset.saved = saved ? '1' : '0';
      button.classList.toggle('is-active', saved);
      button.classList.toggle('text-primary', saved && button.classList.contains('dropdown-item'));
      const icon = $('i', button);
      if (icon) {
        icon.className = `bi ${saved ? 'bi-bookmark-fill' : 'bi-bookmark'}${icon.className.includes('me-1') ? ' me-1' : ''}`;
      }
      const label = $('[data-label]', button);
      if (label) {
        label.textContent = saved ? 'บันทึกแล้ว' : 'บันทึก';
      }
    });
  };

  App.applyVisibility = (id, isPublic) => {
    $$(`[data-action="toggle-visibility"][data-id="${id}"]`).forEach((button) => {
      button.dataset.public = isPublic ? '1' : '0';
      const icon = $('i', button);
      if (icon) {
        icon.className = `bi ${isPublic ? 'bi-eye-slash' : 'bi-eye'}`;
      }
      const label = $('[data-label]', button);
      if (label) {
        label.textContent = isPublic ? 'ซ่อนโมเดล' : 'แสดงโมเดล';
      }
    });
    const card = $(`[data-card="${id}"] .card-badges`);
    if (card) {
      $('.hidden-badge', card)?.remove();
      if (!isPublic) {
        card.insertAdjacentHTML('beforeend', '<span class="hidden-badge"><i class="bi bi-eye-slash me-1"></i>ซ่อน</span>');
      }
    }
    const status = $(`[data-row="${id}"] [data-status]`);
    if (status) {
      status.textContent = isPublic ? 'แสดงบนหน้าหลัก' : 'ซ่อน';
      status.className = `status ${isPublic ? 'public' : 'hidden'}`;
    }
  };

  /* ---------- Delegated actions ---------- */
  App.actions.like = async (button) => {
    if (!App.requireLogin()) return;
    button.disabled = true;
    const data = await App.attempt(() => App.post('api/social.php', { action: 'like', id: button.dataset.id }));
    button.disabled = false;
    if (data) App.applyLike(button.dataset.id, data.liked, data.count);
  };

  App.actions.save = async (button) => {
    if (!App.requireLogin()) return;
    const data = await App.attempt(() => App.post('api/social.php', { action: 'save', id: button.dataset.id }));
    if (data) {
      App.applySaved(button.dataset.id, data.saved);
      App.toast(data.saved ? 'บันทึกโมเดลแล้ว' : 'เอาออกจากรายการที่บันทึกแล้ว', 'success');
    }
  };

  App.actions.follow = async (button) => {
    if (!App.requireLogin()) return;
    const data = await App.attempt(() => App.post('api/social.php', { action: 'follow', user_id: button.dataset.user }));
    if (!data) return;
    button.dataset.following = data.following ? '1' : '0';
    button.textContent = data.following ? 'กำลังติดตาม' : 'ติดตาม';
    button.classList.toggle('btn-soft', data.following);
    button.classList.toggle('btn-primary', !data.following);
    $$('[data-followers]').forEach((element) => { element.textContent = data.followers; });
  };

  App.actions.copy = async (button) => {
    const target = $(button.dataset.target);
    const text = target.value ?? target.textContent;
    try {
      await navigator.clipboard.writeText(text.trim());
      App.toast('คัดลอกแล้ว', 'success');
    } catch {
      target.select?.();
      App.toast('คัดลอกไม่สำเร็จ — กด Ctrl+C เพื่อคัดลอกเอง', 'danger');
    }
  };

  App.actions['edit-model'] = async (button) => {
    const data = await App.attempt(() => App.api(`api/models.php?action=get&id=${encodeURIComponent(button.dataset.id)}`));
    if (!data) return;
    const model = data.model;
    $('#edit_id').value = model.id;
    $('#edit_title').value = model.title;
    $('#edit_description').value = model.description;
    $('#edit_price').value = model.price > 0 ? model.price : '';
    $('#edit_license').value = model.license;
    $('#edit_tags').value = model.tags;
    $$('#editForm input[type="file"]').forEach((input) => { input.value = ''; });
    modal('editModal').show();
  };

  App.actions['toggle-visibility'] = async (button) => {
    const next = button.dataset.public === '1' ? 0 : 1;
    const data = await App.attempt(() => App.post('api/models.php', { action: 'visibility', id: button.dataset.id, is_public: next }));
    if (data) {
      App.applyVisibility(button.dataset.id, data.is_public === 1);
      App.toast(data.is_public === 1 ? 'แสดงโมเดลบนหน้าหลักแล้ว' : 'ซ่อนโมเดลแล้ว', 'success');
    }
  };

  App.deleteModel = async (id, title) => {
    const accepted = await App.confirm({ title: 'ลบโมเดล', message: `ลบ “${title}” ถาวร? ไฟล์และข้อมูลที่เกี่ยวข้องจะถูกลบทั้งหมด`, ok: 'ลบโมเดล' });
    if (!accepted) return false;
    const data = await App.attempt(() => App.post('api/models.php', { action: 'delete', id }));
    if (!data) return false;
    App.toast('ลบโมเดลแล้ว', 'success');
    const holder = $(`[data-card="${id}"]`) || $(`[data-row="${id}"]`);
    if (holder) {
      holder.remove();
    } else {
      location.href = 'profile.php';
    }
    return true;
  };

  App.actions['delete-model'] = (button) => App.deleteModel(button.dataset.id, button.dataset.title || '');

  App.actions['delete-model-from-edit'] = async () => {
    const id = $('#edit_id').value;
    const title = $('#edit_title').value;
    await new Promise((resolve) => {
      document.getElementById('editModal').addEventListener('hidden.bs.modal', resolve, { once: true });
      modal('editModal').hide();
    });
    if (await App.deleteModel(id, title)) {
      if ($('.page[data-model-id]')) return; // redirected already
      location.reload();
    }
  };

  document.addEventListener('click', (event) => {
    const element = event.target.closest('[data-action]');
    const handler = element && App.actions[element.dataset.action];
    if (handler) {
      event.preventDefault();
      handler(element, event);
    }
  });

  // Thumbnails that fail to load (deleted or missing file) fall back to the placeholder image.
  const useFallback = (image) => {
    if (image.tagName === 'IMG' && !image.dataset.fallback) {
      image.dataset.fallback = '1';
      image.src = 'assets/img/placeholder.svg';
    }
  };
  document.addEventListener('error', (event) => useFallback(event.target), true);
  $$('img').filter((image) => image.complete && image.naturalWidth === 0).forEach(useFallback); // failed before this script ran

  /* ---------- Edit form ---------- */
  $('#editForm')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = $('#editSubmit');
    submit.disabled = true;
    const data = await App.attempt(() => App.post('api/models.php', new FormData(event.currentTarget)));
    submit.disabled = false;
    if (data) {
      modal('editModal').hide();
      App.toast('บันทึกการแก้ไขแล้ว', 'success');
      setTimeout(() => location.reload(), 600);
    }
  });

  /* ---------- Page bootstrapping ---------- */
  $$('[data-autosubmit]').forEach((select) => select.addEventListener('change', () => select.form.submit()));

  // Flash messages from the server: show as toast and open the related modal.
  let opened = false;
  $$('[data-flash]').forEach((flash) => {
    App.toast(flash.textContent.trim(), flash.dataset.type);
    if (flash.dataset.modal && !opened) {
      opened = true;
      modal(flash.dataset.modal).show();
    }
  });

  // #bank etc. opens the matching tab (#tab-bank).
  if (location.hash.length > 1) {
    const trigger = $(`[data-bs-toggle="pill"][data-bs-target="#tab-${CSS.escape(location.hash.slice(1))}"]`);
    if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
  }
})();
