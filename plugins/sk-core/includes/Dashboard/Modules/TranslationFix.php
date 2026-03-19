<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Fixes display text for free subscription in the dashboard.
 * Ported from mu-plugin: sk-translation-fix.php
 */
class TranslationFix {

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'output_fix_script' ] );
    }

    public function output_fix_script(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( strpos( $uri, '/dashboard' ) === false ) {
            return;
        }
        ?>
        <script>
        (function () {
          function norm(s) {
            return String(s||'').replace(/\u00a0/g,' ').replace(/\s+/g,' ').trim();
          }

          function fixText() {
            var container = document.querySelector('.sk-dashboard-content')
                         || document.querySelector('#sk-vendor-dashboard-root')
                         || document;
            container.querySelectorAll('li').forEach(function(li) {
              var text = norm(li.textContent);
              if (/Dein\s+Abo\s+ist\s+für\s+0\s+Tage\s+gültig/i.test(text)) {
                li.textContent = 'Dein Abo ist unbegrenzt gültig.';
              }
              if (/Dein\s+Abo\s+läuft\s+am\s+Unbegrenzt\s+viele\s+ab/i.test(text)) {
                li.textContent = 'Dein Abo läuft nicht ab.';
              }
            });
          }

          setTimeout(fixText, 500);
          setTimeout(fixText, 1500);

          window.addEventListener('hashchange', function() {
            setTimeout(fixText, 300);
          });

          var root = document.querySelector('#sk-vendor-dashboard-root');
          if (root) {
            root.addEventListener('sk:page-loaded', function() {
              setTimeout(fixText, 300);
            });
          }

          var timer = null;
          var target = document.querySelector('.sk-dashboard-content')
                    || document.querySelector('#sk-vendor-dashboard-root');
          if (target) {
            new MutationObserver(function() {
              if (timer) clearTimeout(timer);
              timer = setTimeout(fixText, 600);
            }).observe(target, { childList: true, subtree: true });
          }
        })();
        </script>
        <?php
    }
}
