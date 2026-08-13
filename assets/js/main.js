/* CampusMart - global UI behaviours (vanilla JS) */
(function () {
  'use strict';

  /* ---------- Mobile navigation ---------- */
  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      const open = navLinks.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.querySelectorAll('.nav-user').forEach(function (el) { el.classList.remove('open'); });
    });
  }

  /* Clicking a user chip toggles the dropdown on touch devices */
  document.querySelectorAll('.user-chip').forEach(function (chip) {
    chip.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 900px)').matches) {
        e.preventDefault();
        chip.closest('.nav-user').classList.toggle('open');
      }
    });
  });

  /* ---------- Initials avatars ---------- */
  document.querySelectorAll('.avatar[data-initials]').forEach(function (el) {
    if (!el.querySelector('img')) {
      el.textContent = el.dataset.initials || 'U';
    }
  });

  /* ---------- Flash alert close ---------- */
  document.querySelectorAll('.alert-close').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const alert = btn.closest('.alert');
      if (alert) { alert.remove(); }
      const container = document.getElementById('flashContainer');
      if (container && !container.querySelector('.alert')) { container.remove(); }
    });
  });

  /* ---------- Auto-dismiss flash after 6s ---------- */
  const flash = document.getElementById('flashContainer');
  if (flash) {
    setTimeout(function () {
      flash.style.transition = 'opacity 0.4s';
      flash.style.opacity = '0';
      setTimeout(function () { flash.remove(); }, 420);
    }, 6000);
  }

  /* ---------- Modal open/close (data-open-modal / data-close-modal) ---------- */
  document.addEventListener('click', function (e) {
    const opener = e.target.closest('[data-open-modal]');
    if (opener) {
      e.preventDefault();
      const target = document.getElementById(opener.getAttribute('data-open-modal'));
      if (target && target.classList.contains('modal-backdrop')) {
        target.classList.add('show');
      }
    }
    const closer = e.target.closest('[data-close-modal]');
    if (closer) {
      const backdrop = closer.closest('.modal-backdrop');
      if (backdrop) { backdrop.classList.remove('show'); }
    }
  });
  document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
    backdrop.addEventListener('click', function (e) {
      if (e.target === backdrop) { backdrop.classList.remove('show'); }
    });
  });

  /* ---------- Toasts ---------- */
  window.cmToast = function (message, type) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast ' + (type || '');
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s';
      setTimeout(function () { toast.remove(); }, 320);
    }, 3200);
  };

  /* ---------- Confirmation dialog ---------- */
  window.cmConfirm = function (message, opts) {
    return new Promise(function (resolve) {
      opts = opts || {};
      let backdrop = document.querySelector('.modal-backdrop');
      if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        document.body.appendChild(backdrop);
      }
      backdrop.innerHTML =
        '<div class="modal" role="dialog" aria-modal="true">' +
          '<h3>' + (opts.title || 'Are you sure?') + '</h3>' +
          '<p>' + message + '</p>' +
          '<div class="modal-actions">' +
            '<button class="btn btn-ghost" data-action="cancel">Cancel</button>' +
            '<button class="btn ' + (opts.dangerous ? 'btn-danger' : 'btn-primary') + '" data-action="confirm">' + (opts.confirmLabel || 'Confirm') + '</button>' +
          '</div>' +
        '</div>';
      backdrop.classList.add('show');
      backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) { close(false); }
      });
      backdrop.querySelector('[data-action="cancel"]').addEventListener('click', function () { close(false); });
      backdrop.querySelector('[data-action="confirm"]').addEventListener('click', function () { close(true); });
      function close(result) {
        backdrop.classList.remove('show');
        backdrop.innerHTML = '';
        resolve(result);
      }
    });
  };

  /* Any form with data-confirm="message" gets a confirmation dialog */
  document.addEventListener('click', function (e) {
    const trigger = e.target.closest('[data-confirm]');
    if (trigger) {
      const msg = trigger.getAttribute('data-confirm');
      e.preventDefault();
      const dangerous = trigger.hasAttribute('data-confirm-dangerous');
      cmConfirm(msg, { dangerous: dangerous, confirmLabel: 'Yes, continue' }).then(function (ok) {
        if (ok) {
          const form = trigger.closest('form');
          if (form) {
            if (trigger.type === 'submit' && form.requestSubmit) {
              form.requestSubmit(trigger);
            } else {
              form.submit();
            }
          } else {
            window.location = trigger.getAttribute('href');
          }
        }
      });
    }
  });

  /* ---------- Favorites (AJAX) ---------- */
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-fav]');
    if (!btn) return;
    e.preventDefault();
    const productId = btn.getAttribute('data-fav');
    if (!productId) return;

    fetch(window.CM_BASE + '/ajax/favorite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'product_id=' + encodeURIComponent(productId) + '&csrf_token=' + encodeURIComponent(window.CM_CSRF || ''),
      credentials: 'same-origin'
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.ok) {
          btn.classList.toggle('active', data.favorited);
          btn.setAttribute('aria-pressed', data.favorited ? 'true' : 'false');
          btn.title = data.favorited ? 'Remove from favorites' : 'Add to favorites';
          window.cmToast(data.message, 'success');
        } else if (data.redirect) {
          window.location = data.redirect;
        } else {
          window.cmToast(data.message || 'Something went wrong.', 'error');
        }
      })
      .catch(function () { window.cmToast('Network error. Please try again.', 'error'); });
  });

  /* ---------- Gallery ---------- */
  document.querySelectorAll('.gallery').forEach(function (gallery) {
    const main = gallery.querySelector('.gallery-main img');
    const thumbs = gallery.querySelectorAll('.gallery-thumbs button');
    thumbs.forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (main && btn.dataset.src) {
          main.src = btn.dataset.src;
          main.alt = btn.getAttribute('data-alt') || '';
        }
        thumbs.forEach(function (t) { t.classList.remove('active'); });
        btn.classList.add('active');
      });
    });
  });

  /* ---------- Search suggestions ---------- */
  const searchInputs = document.querySelectorAll('[data-search-suggest]');
  searchInputs.forEach(function (input) {
    const wrap = input.closest('.search-input-wrap') || input.parentElement;
    let box = document.createElement('div');
    box.className = 'search-suggest';
    wrap.appendChild(box);
    let timer = null;

    input.addEventListener('input', function () {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { box.classList.remove('show'); box.innerHTML = ''; return; }
      timer = setTimeout(function () {
        fetch(window.CM_BASE + '/ajax/search.php?q=' + encodeURIComponent(q))
          .then(function (res) { return res.json(); })
          .then(function (items) {
            if (!items.length) { box.classList.remove('show'); box.innerHTML = ''; return; }
            box.innerHTML = items.map(function (it) {
              return '<a class="sugg-item" href="' + window.CM_BASE + '/product-details.php?id=' + it.id + '">' +
                '<img class="sugg-thumb" src="' + (it.image || window.CM_BASE + '/assets/images/no-image.svg') + '" alt="">' +
                '<span><span class="sugg-title">' + it.title + '</span><br><span class="sugg-sub">' + it.category + ' · ' + it.price + '</span></span>' +
              '</a>';
            }).join('');
            box.classList.add('show');
          });
      }, 260);
    });

    input.addEventListener('blur', function () {
      setTimeout(function () { box.classList.remove('show'); }, 200);
    });
  });

  /* ---------- View tracking for product pages ---------- */
  const viewTrack = document.querySelector('[data-track-view]');
  if (viewTrack) {
    fetch(window.CM_BASE + '/ajax/views.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'product_id=' + encodeURIComponent(viewTrack.dataset.trackView) + '&csrf_token=' + encodeURIComponent(window.CM_CSRF || ''),
      credentials: 'same-origin'
    }).catch(function () {});
  }

  /* ---------- Active nav highlight (static pages) ---------- */
  const currentPath = window.location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.nav-menu a, .nav-actions a').forEach(function (a) {
    if (a.getAttribute('href') && a.getAttribute('href').split('/').pop() === currentPath) {
      a.classList.add('active');
    }
  });
})();
