/*
 * Uebernommen aus dem Plugin wp-post-image-carousel 2.0.3.
 */

(function(){
  function init(root){
    var track = root.querySelector('.wppis-track');
    var dir = root.dataset.direction === 'vertical' ? 'vertical' : 'horizontal';
    var gap = parseInt(root.dataset.gap || '15', 10);
    var H = parseInt(root.dataset.h || '200', 10);
    var V = parseInt(root.dataset.v || '200', 10);

    // Set CSS custom props
    root.style.setProperty('--wppic-gap', gap + 'px');
    root.style.setProperty('--wppic-h', H + 'px');
    root.style.setProperty('--wppic-v', V + 'px');

    
    // === Hard-disable swipe/drag + arrows for VERTICAL ===
    if (dir === 'vertical') {
      try {
        var prevBtn = root.querySelector('.wppis-arrow.prev');
        var nextBtn = root.querySelector('.wppis-arrow.next');
        if (prevBtn) prevBtn.hidden = true;
        if (nextBtn) nextBtn.hidden = true;
      } catch(_) {}
      return; // no sliding logic for vertical; layout/CSS remain untouched
    }
// Sliding: horizontal nutzt Breite des Viewports; vertikal nutzt Höhe des Viewports
    function stepSize(){ return dir === 'vertical' ? root.clientHeight : root.clientWidth; }

    var offset = 0;
    function maxScroll(){
      return dir === 'vertical'
        ? Math.max(0, track.scrollHeight - root.clientHeight)
        : Math.max(0, track.scrollWidth - root.clientWidth);
    }
    function clamp(){ var m = maxScroll(); if (offset < 0) offset = 0; if (offset > m) offset = m; }
    function apply(){ track.style.transform = (dir==='vertical' ? 'translateY(' : 'translateX(') + (-offset) + 'px)'; }
    function next(){ offset += stepSize(); clamp(); apply(); }
    function prev(){ offset -= stepSize(); clamp(); apply(); }

    var prevBtn = root.querySelector('.wppis-arrow.prev');
    var nextBtn = root.querySelector('.wppis-arrow.next');
    if (prevBtn) prevBtn.addEventListener('click', prev);
    if (nextBtn) nextBtn.addEventListener('click', next);

    // swipe/drag
    var sx=0, sy=0, d=0, dragging=false;
    root.addEventListener('pointerdown', function(e){ dragging=true; sx=e.clientX; sy=e.clientY; root.setPointerCapture(e.pointerId); });
    root.addEventListener('pointermove', function(e){
      if(!dragging) return;
      d = (dir==='vertical') ? (e.clientY - sy) : (e.clientX - sx);
      track.style.transition = 'none';
      if (dir==='vertical') track.style.transform = 'translateY(' + (-offset + (-d)) + 'px)';
      else track.style.transform = 'translateX(' + (-offset + (-d)) + 'px)';
    });
    function endDrag(){
      if(!dragging) return;
      dragging=false;
      track.style.transition = '';
      if (Math.abs(d) > 40){ if (d > 0) prev(); else next(); } else { apply(); }
      d=0;
    }
    root.addEventListener('pointerup', endDrag);
    root.addEventListener('pointercancel', endDrag);

    apply();
    
    (function(){
      if (dir !== 'horizontal') return;
      var HZ_INTERVAL = 4000; // ms
      var timer = null, hovering = false;
      var nextBtn = root.querySelector('.wppis-arrow.next');

      function slides(){
        return Array.prototype.slice.call(track.querySelectorAll('.wppis-slide'));
      }

      function canAuto(){
        var list = slides();
        return list.length > 1 && (track.scrollWidth > track.clientWidth + 4) && document.visibilityState !== 'hidden';
      }

      function currentIndex(){
        var list = slides();
        if (!list.length) return 0;
        var x = Math.round(track.scrollLeft + 1);
        var idx = 0;
        for (var i=0; i<list.length; i++){
          if (Math.round(list[i].offsetLeft) <= x) idx = i; else break;
        }
        return idx;
      }

      function scrollToIndex(i){
        var list = slides();
        if (!list.length) return;
        i = (i % list.length + list.length) % list.length;
        var target = Math.round(list[i].offsetLeft);
        try {
          track.scrollTo({ left: target, behavior: 'smooth' });
        } catch(_) {
          track.scrollLeft = target;
        }
      }

      function nextStep(){
        var list = slides();
        if (list.length <= 1) return;
        var cur = currentIndex();
        var nxt = (cur + 1) % list.length;
        scrollToIndex(nxt);
      }

      function start(){
        if (timer || !canAuto()) return;
        timer = setInterval(function(){
          if (hovering || typeof dragging !== 'undefined' && dragging) return;
          nextStep();
        }, HZ_INTERVAL);
      }
      function stop(){
        if (timer){ clearInterval(timer); timer = null; }
      }

      // Interaction pauses
      root.addEventListener('mouseenter', function(){ hovering = true; stop(); });
      root.addEventListener('mouseleave', function(){ hovering = false; start(); });
      root.addEventListener('pointerdown', function(){ stop(); });
      root.addEventListener('focusin', function(){ stop(); });

      document.addEventListener('visibilitychange', function(){
        if (document.visibilityState === 'hidden') stop(); else start();
      });
      window.addEventListener('resize', function(){ stop(); start(); });

      if (nextBtn) nextBtn.addEventListener('click', function(){ stop(); setTimeout(start, 1000); });

      start();
    })();


  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.wppis-slider').forEach(init);
  });
})();