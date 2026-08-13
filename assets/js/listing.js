/* CampusMart - listing form image handling */
(function () {
  'use strict';

  const input = document.getElementById('imageInput');
  if (!input) return;

  const previews = document.getElementById('imagePreviews');
  const zone = document.querySelector('.upload-zone');
  const maxFiles = parseInt(input.getAttribute('data-max') || '5', 10);
  const existing = Array.from(document.querySelectorAll('.preview-item[data-existing]')).map(function (el) {
    return el.getAttribute('data-existing');
  });

  const files = [];

  function render() {
    if (!previews) return;
    previews.innerHTML = '';
    const total = files.length;

    files.forEach(function (f, i) {
      const item = document.createElement('div');
      item.className = 'preview-item';
      const img = document.createElement('img');
      img.src = f.url;
      img.alt = f.name;
      item.appendChild(img);

      if (i === 0) {
        const tag = document.createElement('span');
        tag.className = 'preview-primary';
        tag.textContent = 'Primary';
        item.appendChild(tag);
      }

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'preview-remove';
      btn.textContent = '×';
      btn.setAttribute('aria-label', 'Remove image');
      btn.addEventListener('click', function () {
        files.splice(files.indexOf(f), 1);
        rebuildInput();
        render();
      });
      item.appendChild(btn);
      previews.appendChild(item);
    });

    if (zone) {
      zone.classList.toggle('has-files', total > 0);
      const remaining = maxFiles - total - existing.length;
      zone.querySelector('.upload-hint').textContent =
        remaining > 0 ? (remaining + ' more image' + (remaining > 1 ? 's' : '') + ' can be added (JPG, PNG, WebP or GIF, up to 5 MB).') : 'Maximum images reached.';
      zone.style.pointerEvents = remaining > 0 ? 'auto' : 'none';
      zone.style.opacity = remaining > 0 ? '1' : '0.5';
    }
  }

  function rebuildInput() {
    const dt = new DataTransfer();
    files.forEach(function (f) { dt.items.add(f.file); });
    input.files = dt.files;
  }

  input.addEventListener('change', function () {
    const selected = Array.from(input.files || []);
    const remaining = maxFiles - files.length - existing.length;
    const toAdd = selected.slice(0, remaining);

    toAdd.forEach(function (file) {
      if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) {
        window.cmToast(file.name + ' is not a supported image type.', 'error');
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        window.cmToast(file.name + ' is larger than 5 MB.', 'error');
        return;
      }
      files.push({ file: file, url: URL.createObjectURL(file), name: file.name });
    });

    if (selected.length > toAdd.length) {
      window.cmToast('You can add a maximum of ' + maxFiles + ' images.', 'error');
    }
    rebuildInput();
    render();
  });

  zone.addEventListener('click', function () { input.click(); });
  zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.style.borderColor = '#4f46e5'; });
  zone.addEventListener('dragleave', function () { zone.style.borderColor = ''; });
  zone.addEventListener('drop', function (e) {
    e.preventDefault();
    zone.style.borderColor = '';
    const dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(function (f) { dt.items.add(f); });
    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
  });

  render();

  /* Existing images: remove button posts a hidden flag */
  document.querySelectorAll('.preview-item[data-existing] .preview-remove').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const item = btn.closest('.preview-item');
      const imgId = item.getAttribute('data-image-id');
      if (!imgId) return;
      let hidden = document.getElementById('removeImages');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'remove_images[]';
        hidden.id = 'removeImages';
        input.form.appendChild(hidden);
      }
      hidden.value = hidden.value ? hidden.value + ',' + imgId : String(imgId);
      item.remove();
    });
  });
})();
