/**
 * Subscription admin screens.
 */
;(function () {
    var image_enable = document.querySelector('#_enable_gallery_restriction');
    var image_count = document.querySelector('._gallery_image_restriction_count_field');
    if (image_enable.checked === true) {
        image_count.style.display = '';
    } else {
        image_count.style.display = 'none';
    }
    image_enable.addEventListener('click', function () {
        if (image_enable.checked === true) {
            image_count.style.display = '';
        } else {
            image_count.style.display = 'none';
        }
    })
})();
