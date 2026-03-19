/**
 * SK Vendor Address — Country-to-state select, WooCommerce address i18n
 */
(function ($, window) {
    'use strict';

    var $fields = $('.sk-address-fields');

    var addressSelect = {
        init: function () {
            $fields.on('change', 'select.country_to_state', this.stateSelect);
        },

        stateSelect: function () {
            var countriesRaw = wc_country_select_params.countries.replace(/&quot;/g, '"');
            var countries = $.parseJSON(countriesRaw);
            var $state = $('#sk_address_state');
            $state.addClass('wc-enhanced-select');

            var name = $state.attr('name');
            var id = $state.attr('id');
            var cls = $state.attr('class');
            var selected = $('#sk_selected_state').val();
            var country = $(this).val();

            if (countries[country]) {
                if ($.isEmptyObject(countries[country])) {
                    // No states for this country
                    $('div#sk-states-box').slideUp(2);
                    if ($state.is('select')) {
                        $('select#sk_address_state').replaceWith('<input type="text" class="' + cls + '" name="' + name + '" id="' + id + '" required />');
                    }
                    $('#sk_address_state').val('N/A');
                } else {
                    // Build state options
                    var states = countries[country];
                    var options = '';
                    for (var code in states) {
                        if (states.hasOwnProperty(code)) {
                            var sel = (selected && selected == code) ? ' selected="selected"' : '';
                            options += '<option value="' + code + '"' + sel + '>' + states[code] + '</option>';
                        }
                    }
                    var placeholder = '<option value="">' + wc_country_select_params.i18n_select_state_text + '</option>';

                    if ($state.is('select')) {
                        $('select#sk_address_state').html(placeholder + options);
                    }
                    if ($state.is('input')) {
                        $('input#sk_address_state').replaceWith('<select type="text" class="' + cls + '" name="' + name + '" id="' + id + '" required></select>');
                        $('select#sk_address_state').html(placeholder + options);
                    }
                    $('#sk_address_state').removeClass('sk-hide');
                    $('div#sk-states-box').slideDown();
                }
            } else {
                // Unknown country — show text input
                if ($state.is('select')) {
                    $('select#sk_address_state').replaceWith('<input type="text" class="' + cls + '" name="' + name + '" id="' + id + '" required="required"/>');
                }
                var val = $('#sk_address_state').val();
                if (val === 'N/A') $('#sk_address_state').val('');
                $('#sk_address_state').removeClass('sk-hide');
                $('div#sk-states-box').slideDown();
            }

            $(document.body).trigger('sk_vendor_country_to_state_changing', [country]);
        }
    };

    window.sk_address_select = addressSelect;
    window.sk_address_select.init();

    // Update state label and required attribute based on locale
    $(document.body).on('sk_vendor_country_to_state_changing', function (e, country) {
        if (typeof wc_address_i18n_params === 'undefined') return false;

        var localeRaw = wc_address_i18n_params.locale.replace(/&quot;/g, '"');
        var locales = JSON.parse(localeRaw);
        var locale = (locales[country] !== undefined) ? locales[country] : locales['default'];

        var stateRequired = locale?.state?.required || locale?.state?.required === undefined;

        if (locale?.state?.label) {
            var label = locale.state.label + (stateRequired ? ' <span class="required"> *</span>' : '');
            $('.sk-address-fields #sk-states-box label').html(label);
            $('.sk-address-fields #sk-states-box #sk_address_state').attr('data-state', locale.state.label);
        }

        $('.sk-address-fields #sk-states-box #sk_address_state').attr('required', stateRequired);
    });

})(jQuery, window);
