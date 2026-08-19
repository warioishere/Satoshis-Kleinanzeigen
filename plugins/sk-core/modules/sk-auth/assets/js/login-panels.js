/**
 * Panel switcher of the [sk_login] shortcode.
 */
(function() {
    var btns = document.querySelectorAll('.sk-login-tab-btn');
    var panels = document.querySelectorAll('.sk-login-panel');
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            btns.forEach(function(b) { b.classList.remove('active'); });
            panels.forEach(function(p) { p.classList.remove('active'); });
            btn.classList.add('active');
            document.getElementById('sk-login-' + btn.dataset.tab).classList.add('active');
        });
    });
    /* LNURL copy button */
    var retries = 0, maxRetries = 100;
    var iv = setInterval(function() {
        var link = document.querySelector('.lnurl-auth-permalink a[href^="lightning:"]');
        var container = document.getElementById('lnurl-copy-button-container');
        if (link && container && !document.getElementById('lnurl-copy-button')) {
            var lnurl = link.getAttribute('href').replace(/^lightning:/, '').trim();
            if (lnurl) {
                var btn = document.createElement('button');
                btn.id = 'lnurl-copy-button';
                btn.textContent = 'LNURL kopieren';
                btn.className = 'sk-btn';
                btn.style.marginTop = '1em';
                btn.onclick = function() {
                    navigator.clipboard.writeText(lnurl).then(function() {
                        btn.textContent = 'Kopiert!';
                        setTimeout(function() { btn.textContent = 'LNURL kopieren'; }, 2000);
                    });
                };
                container.appendChild(btn);
            }
            clearInterval(iv);
        }
        if (++retries >= maxRetries) clearInterval(iv);
    }, 100);
})();
