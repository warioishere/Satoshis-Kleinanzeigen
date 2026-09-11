/**
 * SK Product Edit — Gallery, Featured Image, Tags, Add-New Popup, Sale Schedule
 * Only simple products.
 */
(function ($) {
    'use strict';

    if (!window.sk) return;
    var ajaxurl = sk.ajaxurl;
    var nonce = sk.nonce;

    var ProductEdit = {

        init: function () {
            this.featuredImage();
            this.gallery.init();
            this.manageStock();
            this.saleSchedule();
            this.tags();
            this.formValidation();
        },

        /* ── Featured Image ── */
        // PHP renders the cover image via get_the_post_thumbnail() so the <img>
        // has WC's own classes — we target any <img> inside .image-wrap rather
        // than a specific class, which keeps the JS working across initial-load
        // and after-upload states without blowing away the close button.
        featuredImage: function () {
            var frame;

            $(document).on('click', '.sk-feat-image-btn', function (e) {
                e.preventDefault();
                var $wrap = $(this).closest('.sk-feat-image-upload');

                frame = wp.media({
                    title: sk.i18n_choose_feat_img_btn_text || 'Choose Image',
                    button: { text: sk.i18n_choose_feat_img_btn_text || 'Set Image' },
                    multiple: false
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var $imageWrap = $wrap.find('.image-wrap');
                    var $img = $imageWrap.find('img').first();

                    if ( ! $img.length ) {
                        $img = $('<img alt="">');
                        $imageWrap.append( $img );
                    }
                    // Reset attrs so CSS (max-width:100%) can center the new image
                    // — otherwise explicit width/height from placeholder or
                    // a previous upload force an off-size render.
                    $img.attr('src', attachment.url)
                        .removeAttr('srcset')
                        .removeAttr('sizes')
                        .removeAttr('width')
                        .removeAttr('height');

                    $wrap.find('input.sk-feat-image-id').val(attachment.id);
                    $wrap.find('.instruction-inside').addClass('sk-hide');
                    $imageWrap.removeClass('sk-hide');
                });

                frame.open();
            });

            $(document).on('click', '.sk-remove-feat-image', function (e) {
                e.preventDefault();
                var $wrap = $(this).closest('.sk-feat-image-upload');
                $wrap.find('.image-wrap img').attr('src', '').removeAttr('srcset');
                $wrap.find('input.sk-feat-image-id').val('0');
                $wrap.find('.image-wrap').addClass('sk-hide');
                $wrap.find('.instruction-inside').removeClass('sk-hide');
            });
        },

        /* ── Product Gallery ── */
        gallery: {
            frame: null,

            init: function () {
                $(document).on('click', 'a.add-product-images', this.addImages);
                $(document).on('click', '.sk-product-gallery .action-delete', this.deleteImage);
                this.sortable();
            },

            addImages: function (e) {
                e.preventDefault();
                var $container = $('#sk-product-images');

                ProductEdit.gallery.frame = wp.media({
                    title: sk.i18n_choose_gallery_btn_text || 'Add Gallery Images',
                    button: { text: sk.i18n_choose_gallery_btn_text || 'Add to gallery' },
                    multiple: true
                });

                ProductEdit.gallery.frame.on('select', function () {
                    var selection = ProductEdit.gallery.frame.state().get('selection');
                    var ids = ($('#product_image_gallery').val() || '').split(',').filter(Boolean);

                    selection.forEach(function (attachment) {
                        attachment = attachment.toJSON();
                        if (ids.indexOf(String(attachment.id)) !== -1) return;
                        ids.push(attachment.id);

                        var thumb = attachment.sizes && attachment.sizes.thumbnail
                            ? attachment.sizes.thumbnail.url : attachment.url;

                        // Insert before the "add-image" button so new images appear in gallery order.
                        $container.find('ul.product_images li.add-image').before(
                            '<li class="image" data-attachment_id="' + attachment.id + '">' +
                            '<img src="' + thumb + '" alt="">' +
                            '<a href="#" class="action-delete" title="Delete">&times;</a>' +
                            '</li>'
                        );
                    });

                    $('#product_image_gallery').val(ids.join(','));
                });

                ProductEdit.gallery.frame.open();
            },

            deleteImage: function (e) {
                e.preventDefault();
                var $li = $(this).closest('li.image');
                var id = $li.data('attachment_id');
                var ids = ($('#product_image_gallery').val() || '').split(',').filter(Boolean);
                ids = ids.filter(function (v) { return v != id; });
                $('#product_image_gallery').val(ids.join(','));
                $li.remove();
            },

            sortable: function () {
                if (!$.fn.sortable) return;
                $('#sk-product-images ul.product_images').sortable({
                    items: 'li.image',
                    cursor: 'move',
                    placeholder: 'sortable-placeholder',
                    update: function () {
                        var ids = [];
                        $(this).find('li.image').each(function () {
                            ids.push($(this).data('attachment_id'));
                        });
                        $('#product_image_gallery').val(ids.join(','));
                    }
                });
            }
        },

        /* ── Manage Stock ── */
        manageStock: function () {
            $(document).on('change', 'input#_manage_stock', function () {
                var $container = $(this).closest('.product-edit-container, .product-edit-new-container');
                $container.find('.stock_fields')[$(this).is(':checked') ? 'show' : 'hide']();
            });
            $('input#_manage_stock').trigger('change');
        },

        /* ── Sale Schedule ── */
        saleSchedule: function () {
            $(document).on('click', 'a.sale-schedule', function (e) {
                e.preventDefault();
                $(this).hide();
                $(this).closest('.product-edit-container, .product-edit-new-container')
                    .find('.sale-schedule-container').show();
            });

            $(document).on('click', 'a.cancel-sale-schedule', function (e) {
                e.preventDefault();
                var $container = $(this).closest('.product-edit-container, .product-edit-new-container');
                $container.find('.sale-schedule-container').hide();
                $container.find('a.sale-schedule').show();
                $container.find('#_sale_price_dates_from, #_sale_price_dates_to').val('');
            });
        },

        /* ── Product Tags (Select2 AJAX) ── */
        tags: function () {
            var $tagInput = $('select.product_tag_search');
            if (!$tagInput.length || !$.fn.select2) return;

            $tagInput.removeData().off('.select2');
            $tagInput.next('.select2-container').remove();

            $tagInput.select2({
                tags: true,
                tokenSeparators: [','],
                placeholder: 'Tags auswählen',
                width: '100%',
                language: {
                    inputTooShort: function (args) {
                        var n = args.minimum - args.input.length;
                        return 'Bitte ' + n + ' weitere ' + (n === 1 ? 'Zeichen' : 'Zeichen') + ' eingeben';
                    },
                    noResults:      function () { return 'Keine Ergebnisse gefunden'; },
                    searching:      function () { return 'Suche…'; },
                    loadingMore:    function () { return 'Lade mehr Ergebnisse…'; },
                    errorLoading:   function () { return 'Ergebnisse konnten nicht geladen werden'; },
                    removeAllItems: function () { return 'Alle Einträge entfernen'; },
                    removeItem:     function () { return 'Eintrag entfernen'; }
                },
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { action: 'sk_json_search_products_tags', q: params.term };
                    },
                    processResults: function (data) {
                        var results = [];
                        if (data && data.data) {
                            $.each(data.data, function (id, text) {
                                results.push({ id: id, text: text });
                            });
                        }
                        return { results: results };
                    }
                },
                minimumInputLength: 2
            });
        },

        /* ── Form Validation ── */
        formValidation: function () {
            $(document).on('submit', 'form.sk-product-edit-form', function () {
                var $form = $(this);
                var title = $form.find('input[name="post_title"]').val();
                if (!title || !title.trim()) {
                    alert(sk.i18n_product_title_required || 'Product title is required');
                    return false;
                }
                return true;
            });
        }
    };

    $(function () {
        if ($('.product-edit-container, .product-edit-new-container').length) {
            ProductEdit.init();
        }
    });

})(jQuery);
