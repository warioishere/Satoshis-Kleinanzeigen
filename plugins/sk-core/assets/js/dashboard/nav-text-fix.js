/**
 * Replace "Shop Insgesamt Anzeigen" label on the store-count badge.
 */
document.addEventListener('DOMContentLoaded', function () {
    var el = document.querySelector('p.store-count');
    if (el && el.textContent.includes('Shop Insgesamt Anzeigen')) {
        el.textContent = el.textContent.replace('Shop Insgesamt Anzeigen', 'Anbieter insgesamt');
    }
});
