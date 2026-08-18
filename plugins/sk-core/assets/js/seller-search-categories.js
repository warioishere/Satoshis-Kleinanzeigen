/**
 * Store category dropdown of the seller search form: feed its value into the
 * search request and re-run the search whenever it changes.
 */
jQuery( document ).ready( function ( $ ) {
    var form = $( '.sk-seller-search-form' ),
        category = form.find( '[name="sk_seller_category"]' );

    form.on( 'sk_seller_search_populate_data', function ( e, data ) {
        data.store_categories = category.val();
    } );

    category.on( 'change', function () {
        form.trigger( 'sk_seller_search' );
    } );
} );
