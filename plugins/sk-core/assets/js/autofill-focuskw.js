(function () {
  'use strict';

  // Candidate selectors for the title input
  var TITLE_SELECTORS = [
    'input#post_title',
    'input[name="post_title"]',
    'input.sk-product-title',
    'input[name="product_title"]'
  ];

  // Yoast focus keyword field in the vendor dashboard
  var FOCUSKW_SELECTOR = 'input#_yoast_wpseo_focuskw';

  function $(sel) { return document.querySelector(sel); }

  function findTitleInput() {
    for (var i = 0; i < TITLE_SELECTORS.length; i++) {
      var el = $(TITLE_SELECTORS[i]);
      if (el) return el;
    }
    return null;
  }

  function initOnce() {
    var titleEl   = findTitleInput();
    var focusEl   = $(FOCUSKW_SELECTOR);
    if (!titleEl || !focusEl) return false; // not present yet

    // Once the user types in the SEO field, never auto-overwrite it again
    var userTouched = false;
    var isSyncing   = false;
    focusEl.addEventListener('input', function () {
      if (isSyncing) return;
      userTouched = true;
    }, { passive: true });

    var getTrimmedValue = function (el) {
      return (el.value || '').trim();
    };

    var syncFocusWithTitle = function () {
      if (userTouched) return;

      var trimmedTitle = getTrimmedValue(titleEl);
      var sanitizedTitle = trimmedTitle.replace(/,/g, '');
      if (getTrimmedValue(focusEl) === sanitizedTitle) return;

      isSyncing = true;
      focusEl.value = sanitizedTitle;
      isSyncing = false;
    };

    // On load, ensure the focus keyword matches the title
    syncFocusWithTitle();

    // Follow the title as long as the user hasn't changed the SEO field
    var updateFromTitle = function () {
      syncFocusWithTitle();
    };
    titleEl.addEventListener('input', updateFromTitle, { passive: true });
    titleEl.addEventListener('blur', updateFromTitle, { passive: true });

    // Safety net: sync before submit in case it's still empty
    var form = titleEl.closest('form') || focusEl.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        syncFocusWithTitle();
      });
    }

    return true;
  }

  // Wait until the DOM and any Ajax-loaded blocks are present
  function boot() {
    if (initOnce()) return;

    // In case the fields render late: poll briefly
    var tries = 0, maxTries = 40; // ~4s
    var iv = setInterval(function () {
      tries++;
      if (initOnce() || tries >= maxTries) clearInterval(iv);
    }, 100);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
