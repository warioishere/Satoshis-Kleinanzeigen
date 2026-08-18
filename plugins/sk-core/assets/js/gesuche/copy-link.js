/* Link kopieren – kleines Komfort-Feature */
document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('copy-gesuch-link');
  var ok  = document.getElementById('copy-ok');
  if(!btn) return;
  btn.addEventListener('click', function(e){
    e.preventDefault();
    var url = btn.getAttribute('data-url');
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(function(){
        ok.classList.add('show');
        setTimeout(function(){ ok.classList.remove('show'); }, 1600);
      });
    } else {
      // Fallback
      var ta = document.createElement('textarea');
      ta.value = url; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); ok.classList.add('show'); setTimeout(function(){ ok.classList.remove('show'); }, 1600); }
      catch(e){}
      document.body.removeChild(ta);
    }
  });
});
