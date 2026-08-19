/**
 * Vendor field in the WooCommerce products quick edit.
 */
;(function($){
    $('#the-list').off('click.editinline').on('click.editinline', '.editinline', function(){
        const post_id = $(this).closest('tr').attr('id').replace( 'post-', '' );
        const selector = `.inline-edit-row#edit-${post_id}`;
        // use setTimeout to ensure the inline edit form is ready
        setTimeout(function() {
            const element = $(selector).find('.sk_product_author_override_quick');
            if( ! $(element).hasClass('select2-hidden-accessible')) {
                window.SkAdminProduct.searchVendors(element);
            }
        }, 100)
    });
})(jQuery);
