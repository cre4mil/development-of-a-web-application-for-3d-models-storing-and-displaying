/**
 * Upload dialog: drag & drop, automatic thumbnail rendered from the chosen
 * model, and an upload progress bar. Requires core.js.
 */
(() => {
  'use strict';

  const { $ } = App;
  const form = document.getElementById('uploadForm');
  if (!form) return;

  const modelInput = $('#upload_model');
  const thumbInput = $('#upload_thumb');
  const preview = $('#thumbPreview');
  const titleInput = $('#upload_title');
  let customThumb = false;
  let generation = 0;

  const showThumb = (file) => {
    preview.src = URL.createObjectURL(file);
    preview.classList.add('show');
  };

  const assignThumb = (blob) => {
    const file = new File([blob], 'thumbnail.png', { type: 'image/png' });
    const transfer = new DataTransfer();
    transfer.items.add(file);
    thumbInput.files = transfer.files;
    showThumb(file);
  };

  /** Renders the chosen model off-screen and stores a snapshot as the thumbnail. */
  const generateThumbnail = async (file) => {
    const ticket = ++generation;
    const ext = file.name.split('.').pop().toLowerCase();
    const holder = document.createElement('div');
    holder.style.cssText = 'position:fixed;left:-9999px;top:0;width:640px;height:480px';
    holder.innerHTML = '<div data-viewer-canvas style="position:absolute;inset:0"></div>';
    document.body.append(holder);
    const url = URL.createObjectURL(file);
    try {
      const viewer = new ModelViewer(holder);
      await viewer.init(ext);
      viewer.resize();
      const object = await viewer.fetchObject(url, ext, viewer.token);
      viewer.setModel(object);
      if (ticket === generation && !customThumb) {
        const blob = await viewer.captureThumbnail();
        if (blob) assignThumb(blob);
      }
      viewer.dispose();
    } catch {
      // Auto-thumbnail is a convenience; the model can still be uploaded without one.
    } finally {
      URL.revokeObjectURL(url);
      holder.remove();
    }
  };

  document.querySelectorAll('[data-dropzone]').forEach((zone) => {
    const input = $('input[type="file"]', zone);
    const label = $('[data-picked]', zone);
    ['dragenter', 'dragover'].forEach((name) => zone.addEventListener(name, () => zone.classList.add('dragover')));
    ['dragleave', 'drop'].forEach((name) => zone.addEventListener(name, () => zone.classList.remove('dragover')));
    input.addEventListener('change', () => {
      const file = input.files[0];
      label.textContent = file ? `${file.name} (${(file.size / 1048576).toFixed(1)} MB)` : 'ลากไฟล์มาวาง หรือคลิกเพื่อเลือก';
    });
  });

  modelInput.addEventListener('change', () => {
    const file = modelInput.files[0];
    if (!file) return;
    if (!titleInput.value) {
      titleInput.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
    }
    if (!customThumb) {
      preview.classList.remove('show');
      generateThumbnail(file);
    }
  });

  thumbInput.addEventListener('change', () => {
    const file = thumbInput.files[0];
    // A file picked by the user always wins over the generated snapshot.
    customThumb = Boolean(file) && file.name !== 'thumbnail.png';
    if (file) showThumb(file);
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    const submit = $('#uploadSubmit');
    const progress = $('#uploadProgress');
    const bar = $('#uploadBar');
    const percent = $('#uploadPercent');
    const status = $('#uploadStatus');
    const request = new XMLHttpRequest();
    // getAttribute: form.action would return the hidden <input name="action"> element.
    request.open('POST', form.getAttribute('action'));
    request.setRequestHeader('X-CSRF-Token', App.csrf());
    request.setRequestHeader('Accept', 'application/json');
    request.upload.onprogress = (progressEvent) => {
      if (!progressEvent.lengthComputable) return;
      const value = Math.round((progressEvent.loaded / progressEvent.total) * 100);
      bar.style.width = `${value}%`;
      percent.textContent = `${value}%`;
      if (value === 100) status.textContent = 'กำลังประมวลผลโมเดล…';
    };
    request.onload = () => {
      let data = null;
      try {
        data = JSON.parse(request.responseText);
      } catch {
        // handled below
      }
      if (request.status === 201 && data?.ok) {
        App.toast('อัปโหลดสำเร็จ กำลังเปิดหน้าโมเดล…', 'success');
        location.href = data.url;
        return;
      }
      App.toast(data?.message || `อัปโหลดไม่สำเร็จ (${request.status})`, 'danger');
      submit.disabled = false;
      progress.classList.remove('show');
    };
    request.onerror = () => {
      App.toast('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'danger');
      submit.disabled = false;
      progress.classList.remove('show');
    };
    submit.disabled = true;
    bar.style.width = '0%';
    percent.textContent = '0%';
    status.textContent = 'กำลังอัปโหลด…';
    progress.classList.add('show');
    request.send(new FormData(form));
  });

  document.getElementById('uploadModal').addEventListener('hidden.bs.modal', () => {
    generation++;
    customThumb = false;
    form.reset();
    preview.classList.remove('show');
    $('[data-picked]', form).textContent = 'ลากไฟล์มาวาง หรือคลิกเพื่อเลือก';
    $('#uploadProgress').classList.remove('show');
    $('#uploadSubmit').disabled = false;
  });

})();
