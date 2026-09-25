/**
 * Gallery quick-view: opens a model in a dialog straight from a card (no page load).
 * Requires core.js and viewer.js.
 */
(() => {
  'use strict';

  const { $, modal } = App;
  const element = document.getElementById('quickViewModal');
  if (!element) return;

  let current = null;

  const fill = (data) => {
    element.dataset.current = data.id;
    $('#qvAvatar').textContent = (data.uploader || 'U').charAt(0).toUpperCase();
    $('#qvTitle').textContent = data.title;
    $('#qvMeta').textContent = `${data.uploader} · ${data.date} · ${data.size} · ${data.license} · ${Number(data.views).toLocaleString()} views`;
    $('#qvDesc').textContent = data.desc || '';
    $('#qvPage').href = `model.php?id=${data.id}`;
    $('#qvDownload').href = `download.php?id=${data.id}`;
    const like = $('#qvLike');
    like.dataset.id = data.id;
    App.applyLike(data.id, data.liked, data.likes);
    like.classList.toggle('is-liked', data.liked);
    $('#qvLikeCount').textContent = data.likes;
    const tags = $('#qvTags');
    tags.replaceChildren(...data.tags.map((tag) => {
      const link = document.createElement('a');
      link.className = 'tag-pill';
      link.href = `index.php?tag=${encodeURIComponent(tag)}`;
      link.textContent = `#${tag}`;
      return link;
    }));
  };

  App.actions.quickview = (button) => {
    current = JSON.parse(button.dataset.model);
    fill(current);
    modal('quickViewModal').show();
  };

  // The viewer needs a laid-out container, so it starts once the dialog is visible.
  element.addEventListener('shown.bs.modal', () => {
    const root = $('[data-viewer]', element);
    const viewer = root._viewer || new ModelViewer(root);
    viewer.resize();
    viewer.load(current.file, current.ext, { title: current.title, poster: current.thumb });
  });

  element.addEventListener('hidden.bs.modal', () => {
    const viewer = $('[data-viewer]', element)._viewer;
    if (viewer) {
      viewer.token += 1; // cancels a load that is still in flight
      viewer.unload();
    }
  });
})();
