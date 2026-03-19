(function () {
  'use strict';

  // Kandidaten-Selektoren für Titel-Input
  var TITLE_SELECTORS = [
    'input#post_title',
    'input[name="post_title"]',
    'input.sk-product-title',
    'input[name="product_title"]'
  ];

  // Yoast-Fokus-Keyword-Feld im Vendor Dashboard
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
    if (!titleEl || !focusEl) return false; // noch nicht da

    // Wenn User im SEO-Feld tippt, nie wieder automatisch überschreiben
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

    // Beim Laden sicherstellen, dass Fokus-Keyword dem Titel entspricht
    syncFocusWithTitle();

    // Solange der User das SEO-Feld nicht verändert hat, dem Titel folgen
    var updateFromTitle = function () {
      syncFocusWithTitle();
    };
    titleEl.addEventListener('input', updateFromTitle, { passive: true });
    titleEl.addEventListener('blur', updateFromTitle, { passive: true });

    // Sicherheit: vor dem Absenden, falls leer
    var form = titleEl.closest('form') || focusEl.closest('form');
    if (form) {
      form.addEventListener('submit', function () {
        syncFocusWithTitle();
      });
    }

    return true;
  }

  // Warten bis DOM & evtl. Ajax-Blöcke da sind
  function boot() {
    if (initOnce()) return;

    // Falls die Felder spät gerendert werden: kurz pollen
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
