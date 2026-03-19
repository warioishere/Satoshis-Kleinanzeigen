/**
 * SK Customizer Preview — Live preview bindings
 */
(function ($) {
    var api = wp.customize;

    api('sk_appearance[store_map]', function (setting) {
        setting.bind(function (val) {
            var $map = $('.sk-store-widget.sk-store-location');
            val ? $map.show() : $map.hide();
        });
    });
})(jQuery);
