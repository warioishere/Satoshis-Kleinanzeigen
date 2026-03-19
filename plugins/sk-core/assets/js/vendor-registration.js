/**
 * SK Vendor Registration — Form logic, slug check, role toggle
 */
(function ($) {
    'use strict';

    var VendorRegistration = {

        init: function () {
            var $form = $('form.register');

            $('.user-role input[type=radio]', $form).on('change', this.showSellerForm);
            $(document).on('sk_event_seller_registration_form', this.showSellerForm);
            $('.tc_check_box', $form).on('click', this.onTOC);
            $('#shop-phone', $form).on('keydown', sk_sanitize_phone_number);
            $('#company-name', $form).on('focusout', this.generateSlugFromCompany);
            $('#seller-url', $form).on('keydown', this.constrainSlug);
            $('#seller-url', $form).on('keyup', this.renderUrl);
            $('#seller-url', $form).on('focusout', this.checkSlugAvailability);

            this.validationLocalized();
            this.handlePasswordStrengthObserver();
            $(document).trigger('sk_event_seller_registration_form');
        },

        showSellerForm: function () {
            var role = $(this).val();
            if (!role) role = skRegistrationI18n.defaultRole;

            if (role === 'seller') {
                $('.show_if_seller').find('input, select').prop('disabled', false);
                $('.show_if_seller').slideDown();
                if ($('.tc_check_box').length > 0) $('button[name=register]').prop('disabled', true);
                $('.user-role .sk-role-seller').prop('checked', true);
            } else {
                $('.show_if_seller').find('input, select').prop('disabled', true);
                $('.show_if_seller').slideUp();
                if ($('.tc_check_box').length > 0) $('button[name=register]').prop('disabled', false);
                $('.user-role .sk-role-customer').prop('checked', true);
            }
        },

        onTOC: function () {
            if ($(this).prop('checked')) {
                $('input[name=register], button[name=register], input[name=sk_migration]').removeAttr('disabled');
            } else {
                $('input[name=register], button[name=register], input[name=sk_migration]').attr('disabled', 'disabled');
            }
        },

        generateSlugFromCompany: function () {
            var slug = getSlug($(this).val());
            $('#seller-url').val(slug);
            $('#url-alart').text(slug);
            $('#seller-url').focus();
        },

        constrainSlug: function (e) {
            var allowed = [46, 8, 9, 27, 13, 91, 109, 110, 173, 189, 190];
            if ($.inArray(e.keyCode, allowed) !== -1) return;
            if (e.keyCode === 65 && e.ctrlKey) return;
            if (e.keyCode >= 35 && e.keyCode <= 39) return;
            if ((e.shiftKey || (e.keyCode < 65 || e.keyCode > 90) && (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        },

        checkSlugAvailability: function () {
            var $input = $(this);
            if ($input.val() === '') return;

            var $row = $input.closest('.form-row');
            $row.block({ message: null, overlayCSS: { background: '#fff url(' + sk.ajax_loader + ') no-repeat center', opacity: 0.6 } });

            $.post(sk.ajaxurl, {
                action: 'shop_url',
                url_slug: $input.val(),
                _nonce: sk.nonce
            }, function (res) {
                if (res.success === true) {
                    $('#url-alart').removeClass('text-danger').addClass('text-success');
                    $('#url-alart-mgs').removeClass('text-danger').addClass('text-success').text(sk.seller.available);
                    $('.woocommerce-form-register__submit').prop('disabled', false);
                } else {
                    $('#url-alart').removeClass('text-success').addClass('text-danger');
                    $('#url-alart-mgs').removeClass('text-success').addClass('text-danger').text(sk.seller.notAvailable);
                    $('.woocommerce-form-register__submit').prop('disabled', true);
                }
                $row.unblock();
            });
        },

        renderUrl: function () {
            $('#url-alart').text($(this).val());
        },

        validationLocalized: function () {
            var r = SkValidateMsg;
            r.maxlength = $.validator.format(r.maxlength_msg);
            r.minlength = $.validator.format(r.minlength_msg);
            r.rangelength = $.validator.format(r.rangelength_msg);
            r.range = $.validator.format(r.range_msg);
            r.max = $.validator.format(r.max_msg);
            r.min = $.validator.format(r.min_msg);
            $.validator.messages = r;
        },

        handlePasswordStrengthObserver: function () {
            var self = this;
            var el = document.querySelector('.woocommerce-form-register .password-input');
            var good = ['good', 'strong'];
            if (!el) return;

            new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    if (good.some(function (cls) { return mutations[i].target.classList.contains(cls); })) {
                        self.ensureShopSlugAvailability();
                    }
                }
            }).observe(el, { subtree: true, childList: true });
        },

        ensureShopSlugAvailability: function () {
            var slugOk = $('#url-alart-mgs').hasClass('text-success');
            var $role = $('.vendor-customer-registration input[name="role"]:checked');
            var $submit = $('.woocommerce-form-register__submit');

            if ($role.val() === 'seller') {
                $submit.prop('disabled', !slugOk);
            }
        }
    };

    $(function () {
        window.SK_Vendor_Registration = VendorRegistration;
        window.SK_Vendor_Registration.init();

        if ($('.woocommerce ul').hasClass('woocommerce-error') && !$('.show_if_seller').is(':hidden')) {
            $('form.register .user-role input[type=radio]').trigger('change');
        }

        if ($('.tc_check_box').length > 0) {
            $('input[name=sk_migration], input[name=register]').attr('disabled', 'disabled');
        }
    });

})(jQuery);
