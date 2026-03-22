/**
 * SK WP Pointers — Admin tooltip guide
 */
jQuery(function ($) {
    function openPointer(id) {
        var pointer = SK_Pointers.pointers[id];
        var options = $.extend(pointer.options, {
            close: function () {
                $.post(sk_pointer_data.ajaxurl, {
                    screen: sk_pointer_data.screen,
                    action: 'sk-dismiss-wp-pointer',
                    _wpnonce: sk_pointer_data.nonce
                });
            }
        });

        var $el = $(pointer.target).pointer(options);
        $el.pointer('open');

        if ('next_button' in pointer) {
            $('.wp-pointer-buttons').append(pointer.next_button);
        }

        $('.wp-pointer-buttons').find('a.close').addClass('sk button button-secondary');

        if (pointer.next_trigger) {
            $(pointer.next_trigger.target).on(pointer.next_trigger.event, function () {
                setTimeout(function () {
                    $el.pointer('close');
                    openPointer(pointer.next);
                }, 200);
            });
        }
    }

    setTimeout(function () {
        $.each(SK_Pointers.pointers, function (id) {
            openPointer(id);
            return false;
        });
    }, 800);
});
