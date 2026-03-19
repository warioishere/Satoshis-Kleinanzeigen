<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Shows subscription pack product thumbnails under pack titles.
 * Ported from mu-plugin: abo-picture.php
 */
class AboPicture {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    public function enqueue(): void {
        $is_dashboard         = function_exists( 'sk_is_seller_dashboard' ) && sk_is_seller_dashboard();
        $is_subscription_page = function_exists( 'is_page' ) && is_page( 'inserate-abos' );

        if ( ! $is_dashboard && ! $is_subscription_page ) {
            return;
        }

        $optimizations_active = function_exists( 'sk_dashboard_optimizations_enabled' )
            ? sk_dashboard_optimizations_enabled()
            : true;

        wp_register_style( 'dst-sub-thumbs', false );
        wp_enqueue_style( 'dst-sub-thumbs' );
        wp_add_inline_style( 'dst-sub-thumbs', '
.dst-sub-thumb, .dst-sub-thumb-public {
  margin: 8px 0 12px;
  text-align: center !important;
  width: 100%;
}
.dst-sub-thumb img, .dst-sub-thumb-public img {
  display: inline-block !important;
  margin: 0 auto !important;
  max-height: 140px;
  width: auto;
  border-radius: 8px;
}' );

        $packs = $this->get_all_subscription_packs();
        wp_register_script( 'dst-sub-thumbs', '', [], null, true );
        wp_enqueue_script( 'dst-sub-thumbs' );
        wp_add_inline_script(
            'dst-sub-thumbs',
            'window.DST_ALL_PACKS = ' . wp_json_encode( $packs, JSON_UNESCAPED_UNICODE ) . ';',
            'before'
        );

        $js = $optimizations_active ? $this->get_optimized_js() : $this->get_fallback_js();
        wp_add_inline_script( 'dst-sub-thumbs', $js );
    }

    private function get_all_subscription_packs(): array {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }

        $products = wc_get_products( [
            'status'  => 'publish',
            'limit'   => -1,
            'type'    => 'product_pack',
            'orderby' => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
        ] );

        $out = [];
        foreach ( $products as $p ) {
            $pid      = $p->get_id();
            $title    = $p->get_name();
            $thumb_id = get_post_thumbnail_id( $pid );
            $thumb    = get_the_post_thumbnail_url( $pid, 'medium' ) ?: '';
            $alt      = ( $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '' ) ?: $title;
            $out[]    = [
                'id'    => (int) $pid,
                'title' => $title,
                'thumb' => $thumb,
                'alt'   => $alt,
            ];
        }

        return $out;
    }

    private function get_optimized_js(): string {
        return <<<'JS'
(function(){
  var packs = Array.isArray(window.DST_ALL_PACKS) ? window.DST_ALL_PACKS : [];
  if (!packs.length) return;

  function norm(s){ return (s||'').trim().toLowerCase().replace(/\s+/g,' '); }
  function findByTitle(text){
    var nt = norm(text);
    for (var i = 0; i < packs.length; i++){
      var pt = norm(packs[i].title);
      if (pt === nt || pt.indexOf(nt) === 0 || nt.indexOf(pt) === 0) return packs[i];
    }
    return null;
  }
  function hasThumbSibling(node, cls){ return !!(node && node.parentElement && node.parentElement.querySelector('.'+cls)); }
  function injectThumb(heading, pack, cls){
    if (!heading || !pack || !pack.thumb || hasThumbSibling(heading, cls)) return false;
    var wrap = document.createElement('div'); wrap.className = cls;
    var img = document.createElement('img'); img.src = pack.thumb; img.alt = pack.alt || pack.title || 'Abo'; img.loading = 'lazy';
    wrap.appendChild(img); heading.parentElement.insertBefore(wrap, heading.nextSibling); return true;
  }
  function processHeading(h, cls){ if (!h) return false; var p = findByTitle(h.textContent || ''); if (!p) return false; return injectThumb(h, p, cls); }
  function processNode(node, selector, cls){
    if (!node || node.nodeType !== 1) return false;
    var matched = false;
    if (node.matches && node.matches(selector)) matched = processHeading(node, cls) || matched;
    var list = node.querySelectorAll ? node.querySelectorAll(selector) : [];
    for (var i=0;i<list.length;i++) matched = processHeading(list[i], cls) || matched;
    return matched;
  }
  function scanContainer(c, sel, cls){
    if (!c || !c.querySelectorAll) return false;
    var found = false;
    var hs = c.querySelectorAll(sel);
    for (var i=0;i<hs.length;i++) found = processHeading(hs[i], cls) || found;
    return found;
  }
  function observeContainer(c, sel, cls){
    if (!c) return;
    scanContainer(c, sel, cls);
    var observer = new MutationObserver(function(mutations){
      for (var i=0;i<mutations.length;i++){
        if (mutations[i].type==='characterData' && mutations[i].target && mutations[i].target.parentElement && mutations[i].target.parentElement.matches && mutations[i].target.parentElement.matches(sel))
          processHeading(mutations[i].target.parentElement, cls);
        var nodes = mutations[i].addedNodes || [];
        for (var j=0;j<nodes.length;j++) processNode(nodes[j], sel, cls);
      }
    });
    observer.observe(c, {childList:true, subtree:true, characterData:true});
  }
  function initDashboard(){
    var root = document.querySelector('#sk-vendor-dashboard-root'); if (!root) return;
    var wrap = root.querySelector('.sk-dashboard-wrap') || root;
    observeContainer(wrap, '.sk-layout h3', 'dst-sub-thumb');
    if (!root._dstThumbsBound){ root._dstThumbsBound=true; root.addEventListener('sk:page-loaded', function(){ requestAnimationFrame(initDashboard); }); }
  }
  function initPublic(){
    var w = document.querySelector('.pack_content_wrapper');
    if (w) observeContainer(w, '.product_pack_item .pack_content h2', 'dst-sub-thumb-public');
    else scanContainer(document, '.pack_content_wrapper .product_pack_item .pack_content h2', 'dst-sub-thumb-public');
  }
  // Watch for .pack_content_wrapper being injected at any time (e.g. via AJAX nav)
  (function(){
    var _bodyObs = new MutationObserver(function(mutations){
      for (var i=0; i<mutations.length; i++){
        var nodes = mutations[i].addedNodes || [];
        for (var j=0; j<nodes.length; j++){
          var n = nodes[j];
          if (n.nodeType !== 1) continue;
          if ((n.matches && n.matches('.pack_content_wrapper')) || (n.querySelector && n.querySelector('.pack_content_wrapper'))) {
            _bodyObs.disconnect();
            initPublic();
            return;
          }
        }
      }
    });
    _bodyObs.observe(document.body || document.documentElement, {childList:true, subtree:true});
  })();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){ initPublic(); initDashboard(); });
  } else {
    initPublic(); initDashboard();
  }
  document.addEventListener('sk:nav-loaded', function(){ initPublic(); });
  window.addEventListener('hashchange', function(){ requestAnimationFrame(initDashboard); });
})();
JS;
    }

    private function get_fallback_js(): string {
        return <<<'JS'
(function(){
  var packs = Array.isArray(window.DST_ALL_PACKS) ? window.DST_ALL_PACKS : [];
  if (!packs.length) return;
  function norm(s){ return (s||'').trim().toLowerCase().replace(/\s+/g,' '); }
  function findByTitle(t){ var nt=norm(t); for(var i=0;i<packs.length;i++){ var pt=norm(packs[i].title); if(pt===nt||pt.startsWith(nt)||nt.startsWith(pt)) return packs[i]; } return null; }
  function already(node){ return node && node.parentElement && node.parentElement.querySelector('.dst-sub-thumb, .dst-sub-thumb-public'); }
  function inject(h, pack, cls){
    if (!h||!pack||!pack.thumb||already(h)) return;
    var wrap=document.createElement('div'); wrap.className=cls;
    var img=document.createElement('img'); img.src=pack.thumb; img.alt=pack.alt||pack.title||'Abo'; img.loading='lazy';
    wrap.appendChild(img); h.parentElement.insertBefore(wrap, h.nextSibling);
  }
  function scan(){
    document.querySelectorAll('.pack_content_wrapper .product_pack_item .pack_content h2').forEach(function(h2){ var p=findByTitle(h2.textContent||''); if(p) inject(h2,p,'dst-sub-thumb-public'); });
    document.querySelectorAll('.sk-layout h3').forEach(function(h3){ var p=findByTitle(h3.textContent||''); if(p) inject(h3,p,'dst-sub-thumb'); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
  document.addEventListener('sk:nav-loaded', scan);
  var mo=new MutationObserver(function(){ if(mo._raf) cancelAnimationFrame(mo._raf); mo._raf=requestAnimationFrame(scan); });
  mo.observe(document.body, {childList:true, subtree:true});
  window.addEventListener('hashchange', function(){ setTimeout(scan,0); });
  var tries=0, iv=setInterval(function(){ scan(); if(++tries>10) clearInterval(iv); }, 700);
})();
JS;
    }
}
