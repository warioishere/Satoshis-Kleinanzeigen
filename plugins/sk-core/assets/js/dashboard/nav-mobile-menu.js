/**
 * Close the dashboard hamburger menu once a navigation link is followed.
 */
(function () {
    var nav = document.getElementById('sk-navigation');
    if (!nav) return;
    nav.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (!a) return;
        var toggle = document.getElementById('toggle-mobile-menu');
        if (toggle) toggle.checked = false;
    });
})();
