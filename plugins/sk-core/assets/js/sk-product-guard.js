(function () {
  'use strict';

  var _bound = false;
  var _mo, _rafTick = 0;

  // ── Helpers ──────────────────────────────────────────────────────────────

  function getForm() {
    return document.querySelector('form.sk-product-edit-form');
  }

  function inSkProductForm(form) {
    if (!document.body.classList.contains('sk-dashboard') || !form) return false;
    if (form.classList.contains('sk-product-edit-form')) return true;
    if (form.querySelector('input[name="_regular_price"]')) return true;
    return false;
  }

  function getPriceInput(form) {
    return (form || document).querySelector('input[name="_regular_price"]');
  }

  function hasFeaturedImage(form) {
    // Hidden input set when an image is selected
    var idInput = (form || document).querySelector('input.sk-feat-image-id');
    if (idInput && idInput.value && idInput.value !== '0') return true;
    // Fallback: image-wrap visible (not hidden)
    var wrap = (form || document).querySelector('.sk-feat-image-upload .image-wrap');
    if (wrap && !wrap.classList.contains('sk-hide')) return true;
    return false;
  }

  function num(val) {
    var v = ('' + (val == null ? '' : val)).trim().replace(',', '.');
    return v === '' ? NaN : parseFloat(v);
  }

  // ── Toast system ─────────────────────────────────────────────────────────

  var _activeToasts = {};

  function showToast(id, message, opts) {
    opts = opts || {};
    var type    = opts.type    || 'info';   // 'info' | 'error'
    var timeout = opts.timeout != null ? opts.timeout : 5000;

    // Remove existing toast with same id
    removeToast(id);

    var toast = document.createElement('div');
    toast.className = 'dcg-toast dcg-toast--' + type;
    toast.dataset.toastId = id;

    var icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
    toast.innerHTML =
      '<i class="fas ' + icon + '"></i>' +
      '<span>' + message + '</span>' +
      '<button class="close-toast" type="button" aria-label="Schlie\u00dfen">&times;</button>';

    document.body.appendChild(toast);
    _activeToasts[id] = toast;

    toast.querySelector('.close-toast').addEventListener('click', function () {
      dismissToast(toast);
    });

    if (timeout > 0) {
      setTimeout(function () { dismissToast(toast); }, timeout);
    }

    return toast;
  }

  function dismissToast(toast) {
    if (!toast || !toast.parentNode) return;
    toast.style.transition = 'opacity 0.3s, transform 0.3s';
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(420px)';
    setTimeout(function () {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
      if (toast.dataset.toastId) delete _activeToasts[toast.dataset.toastId];
    }, 320);
  }

  function removeToast(id) {
    if (_activeToasts[id]) dismissToast(_activeToasts[id]);
  }

  // ── Validation ───────────────────────────────────────────────────────────

  function validate(form) {
    var errors = [];

    var priceInput = getPriceInput(form);
    if (priceInput) {
      var n = num(priceInput.value);
      if (!isFinite(n) || n <= 0) {
        errors.push('Bitte einen <strong>Preis &gt; 0</strong> angeben.');
      }
    }

    if (!hasFeaturedImage(form)) {
      errors.push('Bitte ein <strong>Titelbild</strong> hochladen.');
    }

    return errors;
  }

  // ── Warn on submit (never block — allow draft saves) ─────────────────────

  function warnIfIncomplete(form) {
    if (!inSkProductForm(form)) return;
    var errors = validate(form);
    if (errors.length === 0) {
      removeToast('guard-error');
      return;
    }
    showToast('guard-error', errors.join('<br>'), { type: 'error', timeout: 7000 });
  }

  function onSubmit(e) {
    if (!(e.target instanceof HTMLFormElement)) return;
    warnIfIncomplete(e.target);
  }

  function onClick(e) {
    var btn = e.target.closest('input[type="submit"]#publish, .sk-pe-save-btn');
    if (!btn) return;
    if (btn.id === 'convert_to_sats') return;
    var form = btn.form || (btn.closest && btn.closest('form')) || getForm();
    if (!form) return;
    warnIfIncomplete(form);
  }

  // ── Image-format hint toast (1:1, shown once on load) ────────────────────

  function showImageHintToast() {
    var form = getForm();
    if (!form || !inSkProductForm(form)) return;
    if (window.location.search.indexOf('message=success') !== -1) return;
    showToast(
      'guard-image-hint',
      '<strong>Tipp:</strong> Das Titelbild sollte im <strong>1:1&nbsp;Format</strong> (quadratisch) hochgeladen werden.',
      { type: 'info', timeout: 5000 }
    );
  }

  // ── Live price feedback (clear error toast when price becomes valid) ──────

  function bindLiveValidation() {
    var form  = getForm();
    if (!form) return;
    var price = getPriceInput(form);
    if (!price || price.dataset._lvBound) return;

    price.setAttribute('min', '0.00000001');
    price.setAttribute('step', 'any');

    price.addEventListener('input', function () {
      var n = num(price.value);
      if (isFinite(n) && n > 0) removeToast('guard-error');
    }, { passive: true });

    price.dataset._lvBound = '1';
  }

  // ── Init ─────────────────────────────────────────────────────────────────

  function initOnce() {
    if (_bound) return;
    _bound = true;

    document.addEventListener('submit', onSubmit, true);
    document.addEventListener('click',  onClick,  true);

    bindLiveValidation();

    setTimeout(showImageHintToast, 1000);

    _mo = new MutationObserver(function () {
      if (_rafTick) return;
      _rafTick = requestAnimationFrame(function () {
        _rafTick = 0;
        bindLiveValidation();
      });
    });
    _mo.observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOnce);
  } else {
    initOnce();
  }

})();
