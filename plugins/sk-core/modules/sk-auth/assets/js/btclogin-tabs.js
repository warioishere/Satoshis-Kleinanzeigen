/**
 * BTC Login — tab switcher for login/register forms.
 */
(function () {
    'use strict';
    document.querySelectorAll('.btclogin-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.btclogin-tab').forEach(function (t) { t.classList.remove('active'); });
            this.classList.add('active');
            var target = this.getAttribute('data-tab');
            var login = document.getElementById('btclogin-login');
            var reg   = document.getElementById('btclogin-register');
            if (login) login.style.display = target === 'login'    ? '' : 'none';
            if (reg)   reg.style.display   = target === 'register' ? '' : 'none';
        });
    });
})();
