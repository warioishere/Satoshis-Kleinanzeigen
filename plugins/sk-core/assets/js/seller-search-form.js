/**
 * Live search of the seller listing: debounce keystrokes, fetch the matching
 * listing via AJAX and swap the result into the page.
 */
jQuery( document ).ready( function ( $ ) {
    var form = $( '.sk-seller-search-form' );
    var xhr;
    var timer = null;

    form.on( 'sk_seller_search', function () {
        if ( timer ) {
            clearTimeout( timer );
        }

        if ( xhr ) {
            xhr.abort();
        }

        var data = {
            pagination_base: form.find('#pagination_base').val(),
            per_row: form.attr( 'data-per-row' ),
            action: 'sk_seller_listing_search',
            _wpnonce: form.find('#nonce').val()
        };

        form.trigger( 'sk_seller_search_populate_data', data );

        timer = setTimeout(function() {
            form.find('.sk-overlay').show();

            xhr = $.post( sk.ajaxurl, data, function( response ) {
                if ( response.success ) {
                    form.find('.sk-overlay').hide();

                    var data = response.data;
                    $('#sk-seller-listing-wrap').html( $(data).find( '.seller-listing-content' ) );
                }
            } );
        }, 500 );
    } );

    form.on( 'sk_seller_search_populate_data', function ( e, data ) {
        data.search_term = form.find( '#search' ).val();
    } );

    form.on( 'keyup', '#search', function() {
        form.trigger( 'sk_seller_search' );
    } );
} );
