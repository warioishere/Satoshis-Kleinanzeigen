<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Dashboard notice boxes: welcome message, subscription note, verification note.
 * Ported from mu-plugins: welcome-message.php, subscription-note.php, verification-note.php
 */
class Notices {

    public function __construct() {
        add_action( 'sk_dashboard_before_widgets', [ $this, 'output_welcome_box' ] );
        add_action( 'wp_head',   [ $this, 'output_subscription_css' ], 20 );
        add_action( 'wp_footer', [ $this, 'output_subscription_js' ] );
        add_action( 'wp_head',   [ $this, 'output_verification_css' ], 20 );
        add_action( 'wp_footer', [ $this, 'output_verification_js' ], 20 );
    }

    public function output_welcome_box(): void {
        $optimizations_active = function_exists( 'sk_dashboard_optimizations_enabled' )
            ? sk_dashboard_optimizations_enabled()
            : true;
        ?>
        <div id="welcome-box" style="display:none;background:#252d38;color:#fff;padding:20px;margin:20px 0;border-radius:6px;">
            <h2 style="margin-top:0;">Willkommen im Verkäufer-Dashboard!</h2>
            <p>Hier kannst du deine Inserate verwalten, neue Gesuche einstellen und deine Angebote organisieren.</p>
            <p>Standardmäßig kannst du bis zu <strong>6 Inserate kostenlos</strong> einstellen und bearbeiten.
               Wenn du mehr Inserate gleichzeitig online haben möchtest, kannst du uns mit einem
               <a href="https://satoshiskleinanzeigen.space/inserate-abos/" style="color:#F7931A;">Abo</a> unterstützen.</p>
            <p>Wir möchten bewusst <strong>keine Verkaufsgebühren</strong> erheben – denn damit würden in vielen Ländern rechtliche KYC-Pflichten greifen.
               Um weiterhin <strong>KYC-frei</strong> zu bleiben und deine <strong>Privatsphäre</strong> zu schützen, finanzieren wir die Plattform über Abos statt über Gebühren.</p>
            <p>So stellen wir sicher, dass SatoshisKleinanzeigen langfristig bestehen bleibt – unabhängig, nutzerfreundlich und mit maximalem Fokus auf Privatsphäre.</p>
            <p>Wenn du Probleme oder Anregungen hast, lass uns gerne ein
               <a href="https://new.satoshiskleinanzeigen.space/feedback/" style="color:#F7931A;text-decoration:underline;">Feedback</a> da.</p>
            <p>Dein Satoshis Kleinanzeigen Team</p>
        </div>
        <script>
        <?php if ( $optimizations_active ) : ?>
          (function () {
            function routeHas(key){ var hash = (window.location.hash || '').toLowerCase(); return hash.indexOf(key) !== -1; }
            function matchesSelector(selector){ return !!document.querySelector(selector); }
            function shouldHideBox(){
              if (routeHas('subscription') || routeHas('announcement')) return true;
              if (routeHas('#settings/verification') || routeHas('verification')) return true;
              if (routeHas('review')) return true;
              if (matchesSelector('.sk-subscription-content, .sk-subscription-pack-content')) return true;
              if (matchesSelector('[data-vue-root="sk-verification"], .sk-verification, .verification-content')) return true;
              if (matchesSelector('.sk-reviews-area')) return true;
              return false;
            }
            function toggleBox(attempt){
              var box = document.getElementById('welcome-box');
              if (!box) return;
              box.style.display = shouldHideBox() ? 'none' : '';
              if (attempt < 5) requestAnimationFrame(function(){ toggleBox(attempt + 1); });
            }
            function onRouteChange(){ document.getElementById('welcome-box') && (document.getElementById('welcome-box').style.display='none'); requestAnimationFrame(function(){ toggleBox(0); }); }
            toggleBox(0);
            window.addEventListener('hashchange', onRouteChange);
            if (typeof jQuery !== 'undefined') jQuery(document).on('sk_turbo_load', function(){ toggleBox(0); });
            var root = document.querySelector('#sk-vendor-dashboard-root');
            if (root) root.addEventListener('sk:page-loaded', onRouteChange);
          })();
        <?php else : ?>
          (function () {
            function toggleBox() {
              var box = document.getElementById('welcome-box');
              if (!box) return;
              var h = (window.location.hash || '').toLowerCase();
              var shouldHide = h.indexOf('subscription') !== -1 || h.indexOf('announcement') !== -1 || h.indexOf('verification') !== -1 || h.indexOf('review') !== -1
                            || document.querySelector('.sk-subscription-content, .sk-subscription-pack-content')
                            || document.querySelector('[data-vue-root="sk-verification"], .sk-verification, .verification-content')
                            || document.querySelector('.sk-reviews-area');
              box.style.display = shouldHide ? 'none' : '';
            }
            document.addEventListener('DOMContentLoaded', toggleBox);
            window.addEventListener('hashchange', toggleBox);
            var target = document.querySelector('.sk-dashboard-wrap');
            if (target) new MutationObserver(toggleBox).observe(target, { childList: true, subtree: true });
            toggleBox();
          })();
        <?php endif; ?>
        </script>
        <?php
    }

    public function output_subscription_css(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        ?>
        <style id="subscription-infobox-css">
          #subscription-infobox{display:block;width:100%;box-sizing:border-box;margin:12px 0 18px;padding:16px 18px;border-radius:6px;border:1px solid #334155;background-color:#181e27;color:#e2e8f0;box-shadow:0 2px 10px rgba(0,0,0,.25);line-height:1.5;}
          #subscription-infobox .row{display:flex;gap:.75rem;align-items:flex-start}
          #subscription-infobox .icon{font-size:18px;line-height:1;margin-top:2px}
          #subscription-infobox strong{color:#fff}
        </style>
        <?php
    }

    public function output_subscription_js(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        ?>
        <script id="subscription-infobox-js">
        (function () {
          const BANNER_ID = 'subscription-infobox';
          function buildBanner(){
            const el = document.createElement('div');
            el.id = BANNER_ID;
            el.innerHTML = '<div class="row"><div class="icon" aria-hidden="true">📢</div><div>'
              + '<strong>Satoshis Kleinanzeigen lebt von der Community.</strong><br>'
              + 'Mit einem kleinen Abo unterstützt du nicht nur den Betrieb, sondern erhältst auch mehr Reichweite und Sichtbarkeit für deine Inserate.<br><br>'
              + 'Schon ab 10 000 Sats bist du dabei – unkompliziert, anonym, ohne Zwang.<br><br>'
              + 'Du kannst auch einmalig ein Abo kaufen, mehrere Produkte einstellen, und das Abo auslaufen lassen. Alle zusätzlichen Inserate über dem Grundkontigent, werden nicht gelöscht!'
              + '</div></div>';
            return el;
          }
          function findSubscriptionContainer(){
            const root = document.querySelector('#sk-vendor-dashboard-root') || document;
            const activePanel = root.querySelector('.sk-tab-panel:not([hidden])');
            if (activePanel) {
              const matchCard = Array.from(activePanel.querySelectorAll('.sk-card')).find(card => {
                const h = card.querySelector('.sk-card-title, h1, h2, h3, h4, h5, h6');
                if (!h || !h.textContent) return false;
                const t = h.textContent.trim().toLowerCase();
                return ['current subscription','aktuelles abo','aktuelles abonnement','abonnementen pakete','current plan'].some(l => t.includes(l));
              });
              if (matchCard) return matchCard.closest('.sk-layout') || matchCard;
              const layout = activePanel.querySelector('.sk-layout.mb-5');
              if (layout) return layout;
              const fallbackCard = activePanel.querySelector('.sk-card.mb-5');
              if (fallbackCard) return fallbackCard.closest('.sk-layout') || fallbackCard;
            }
            return root.querySelector('.sk-subscription-pack-content, .sk-subscription-content');
          }
          function ensureBanner(container){
            if (!container || !container.parentNode) return false;
            let box = document.getElementById(BANNER_ID);
            if (box && box.nextElementSibling === container) return true;
            if (!box) box = buildBanner(); else box.remove();
            container.parentNode.insertBefore(box, container);
            return true;
          }
          function isSubscriptionRoute(){
            const path = (location.pathname || '').replace(/\/+$/, '');
            if (!path.endsWith('/dashboard/new')) return false;
            return (location.hash || '').toLowerCase().includes('#/subscription');
          }
          function isSubscriptionPacksPage(){
            if (!isSubscriptionRoute()) return false;
            const match = (location.hash || '').toLowerCase().match(/tab=([^&]+)/);
            const tab = match ? match[1] : null;
            return tab === null || tab === 'packs';
          }
          let routeTickTimer = null, retryCount = 0;
          const MAX_RETRIES = 20;
          function removeBanner(){ const b = document.getElementById(BANNER_ID); if (b) b.remove(); }
          function stopWatcher(){ if (routeTickTimer){ clearInterval(routeTickTimer); routeTickTimer = null; } retryCount = 0; }
          function handleRouteTick(){
            if (!isSubscriptionRoute() || !isSubscriptionPacksPage()){ removeBanner(); stopWatcher(); return; }
            const c = findSubscriptionContainer();
            if (c){ ensureBanner(c); stopWatcher(); }
          }
          function startRouteWatcher(){
            if (routeTickTimer) return;
            retryCount = 0;
            routeTickTimer = setInterval(() => { if (++retryCount > MAX_RETRIES){ stopWatcher(); return; } requestAnimationFrame(handleRouteTick); }, 500);
          }
          function forceRouteCheck(){ stopWatcher(); requestAnimationFrame(handleRouteTick); if (isSubscriptionRoute()) startRouteWatcher(); }
          function init(){
            const root = document.querySelector('#sk-vendor-dashboard-root');
            if (root) root.addEventListener('sk:page-loaded', () => forceRouteCheck(), { passive: true });
            forceRouteCheck();
          }
          if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true }); else init();
          window.addEventListener('hashchange', () => forceRouteCheck());
        })();
        </script>
        <?php
    }

    public function output_verification_css(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        ?>
        <style id="verify-infobox-css">
          #verify-infobox{display:block;width:100%;box-sizing:border-box;margin:12px 0 18px;padding:16px 18px;border-radius:6px;border:1px solid #334155;background-color:#181e27;color:#e2e8f0;box-shadow:0 2px 10px rgba(0,0,0,.25);line-height:1.5;}
          #verify-infobox .row{display:flex;gap:.75rem;align-items:flex-start}
          #verify-infobox .icon{font-size:18px;line-height:1;margin-top:2px}
          #verify-infobox strong{color:#fff}
        </style>
        <?php
    }

    public function output_verification_js(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        ?>
        <script id="verify-infobox-js">
        (function () {
          const BANNER_ID = 'verify-infobox';
          function buildBanner(){
            const el = document.createElement('div');
            el.id = BANNER_ID;
            el.innerHTML = '<div class="row"><div class="icon" aria-hidden="true">🔒</div><div>'
              + '<strong>Die Verifizierung ist optional.</strong><br>'
              + 'Du kannst Dich mit einer der unten stehenden Methoden verifizieren. '
              + 'Sobald wir die Angaben geprüft haben, erhältst Du ein <em>Verifiziert</em>-Badge in Deinem Shop Profil. '
              + 'Das schafft zusätzlich Vertrauen gegenüber anderen die Deine Waren kaufen wollen. Deine Daten werden sicher auf unserem Server gespeichert und mit absolut niemanden geteilt.'
              + ' Hast Du kein Bock Dokumente einzureichen, dann lass Dich via Video Call verifizieren.'
              + '</div></div>';
            return el;
          }
          function findHeaderRow(){ return document.querySelector('#sk-vendor-dashboard-root .sk-header-title-section') || document.querySelector('.sk-header-title-section'); }
          function ensureBanner(){
            const row = findHeaderRow(); if (!row) return false;
            let box = document.getElementById(BANNER_ID);
            if (box && box.previousElementSibling === row) return true;
            if (!box) box = buildBanner(); else box.remove();
            row.insertAdjacentElement('afterend', box);
            return true;
          }
          function isVerificationPage(){ return location.hash.includes('settings/verification'); }
          let observer = null;
          function disconnectObserver(){ if (observer){ observer.disconnect(); observer = null; } }
          function watchForHeader(){
            const root = document.querySelector('#sk-vendor-dashboard-root');
            const container = root ? (root.querySelector('.sk-dashboard-wrap') || root) : null;
            if (!container) return;
            disconnectObserver();
            observer = new MutationObserver(mutations => {
              for (let i = 0; i < mutations.length; i++){
                const nodes = mutations[i].addedNodes || [];
                for (let j = 0; j < nodes.length; j++){
                  const node = nodes[j];
                  if (node.nodeType !== 1) continue;
                  if ((node.matches && node.matches('.sk-header-title-section')) || (node.querySelector && node.querySelector('.sk-header-title-section'))){
                    if (ensureBanner()){ disconnectObserver(); return; }
                  }
                }
              }
            });
            observer.observe(container, { childList: true, subtree: true });
            const stopAt = performance.now() + 6000;
            (function loop(){ if (!observer) return; if (performance.now() > stopAt){ disconnectObserver(); return; } requestAnimationFrame(loop); })();
          }
          function mountBanner(){
            if (!isVerificationPage()){ disconnectObserver(); const e = document.getElementById(BANNER_ID); if (e) e.remove(); return; }
            if (ensureBanner()){ disconnectObserver(); return; }
            requestAnimationFrame(watchForHeader);
          }
          document.addEventListener('DOMContentLoaded', () => {
            mountBanner();
            const root = document.getElementById('sk-vendor-dashboard-root');
            if (root) root.addEventListener('sk:page-loaded', () => requestAnimationFrame(mountBanner));
          });
          window.addEventListener('hashchange', () => requestAnimationFrame(mountBanner));
        })();
        </script>
        <?php
    }
}
