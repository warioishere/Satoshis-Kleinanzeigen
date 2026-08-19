/**
 * SK fields on the WordPress user profile screen.
 */
jQuery(function($){
    var SK_Settings = {

        init: function() {
            $('a.sk-banner-drag').on('click', this.imageUpload);
            $('a.sk-remove-banner-image').on('click', this.removeBanner);
        },

        imageUpload: function(e) {
            e.preventDefault();

            var file_frame,
                self = $(this);

            if ( file_frame ) {
                file_frame.open();
                return;
            }

            // Create the media frame.
            file_frame = wp.media.frames.file_frame = wp.media({
                title: jQuery( this ).data( 'uploader_title' ),
                button: {
                    text: jQuery( this ).data( 'uploader_button_text' )
                },
                multiple: false
            });

            file_frame.on( 'select', function() {
                var attachment = file_frame.state().get('selection').first().toJSON();

                var wrap = self.closest('.sk-banner');
                wrap.find('input.sk-file-field').val(attachment.id);
                wrap.find('img.sk-banner-img').attr('src', attachment.url);
                $('.image-wrap', wrap).removeClass('sk-hide');

                $('.button-area').addClass('sk-hide');
            });

            file_frame.open();

        },

        removeBanner: function(e) {
            e.preventDefault();

            var self = $(this);
            var wrap = self.closest('.image-wrap');
            var instruction = wrap.siblings('.button-area');

            wrap.find('input.sk-file-field').val('0');
            wrap.addClass('sk-hide');
            instruction.removeClass('sk-hide');
        }
    };

    SK_Settings.init();

    $('#seller-url').on( 'keydown', function(e) {
        var text = $(this).val();

        // Allow: backspace, delete, tab, escape, enter and .
        if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 91, 109, 110, 173, 189, 190]) !== -1 ||
            // Allow: Ctrl+A
            (e.keyCode == 65 && e.ctrlKey === true) ||
            // Allow: home, end, left, right
            (e.keyCode >= 35 && e.keyCode <= 39)) {
                // let it happen, don't do anything
                return;
        }

        if ((e.shiftKey || (e.keyCode < 65 || e.keyCode > 90) && (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105) ) {
            e.preventDefault();
        }
    });

    $('#seller-url').on( 'keyup', function(e) {
        $('#url-alart').text( getSlug( $(this).val() ) );
    });

    $('#seller-url').on('focusout', function() {
        var self = $(this),
        data = {
            action : 'shop_url',
            url_slug : self.val(),
            vendor_id: self.data('vendor'),
            _nonce : sk_user_profile.nonce,
        };

        if ( self.val() === '' ) {
            return;
        }

        var row = self.closest('td');

        row.block({ message: null, overlayCSS: { background: '#f1f1f1 url(' + sk_user_profile.ajax_loader + ') no-repeat center', opacity: 0.3 } });

        $.post( sk_user_profile.ajaxurl, data, function(resp) {

            if ( resp.success === true ) {
                $('#url-alart').removeClass('text-danger').addClass('text-success');
                $('#url-alart-mgs').removeClass('text-danger').addClass('text-success').text(sk_user_profile.seller.available);
            } else {
                $('#url-alart').removeClass('text-success').addClass('text-danger');
                $('#url-alart-mgs').removeClass('text-success').addClass('text-danger').text(sk_user_profile.seller.notAvailable);
            }

            row.unblock();
        } );
    });
});
