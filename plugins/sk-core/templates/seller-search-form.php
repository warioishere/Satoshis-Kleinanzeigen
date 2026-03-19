<?php if ( ! empty( $search_query ) ) : ?>
    <h2>
        <?php
        echo wp_kses_post(
            // translators: 1) search query
            sprintf( esc_html__( 'Search Results for: %s', 'sk-core' ), esc_attr( $search_query ) )
        );
		?>
            </h2>
<?php endif; ?>

<form role="search" method="get" class="sk-seller-search-form" action="">
    <div class="sk-row sk-clearfix">
        <div class="sk-w4">
            <input type="search" id="search" class="search-field sk-form-control sk-seller-search" placeholder="<?php esc_attr_e( 'Search Vendor &hellip;', 'sk-core' ); ?>" value="<?php echo esc_attr( $search_query ); ?>" name="sk_seller_search" title="<?php esc_attr_e( 'Search seller &hellip;', 'sk-core' ); ?>" />
        </div>

        <?php do_action( 'sk_seller_search_form', $search_query ); ?>
    </div>

    <input type="hidden" id="pagination_base" name="pagination_base" value="<?php echo esc_attr( $pagination_base ); ?>" />
    <input type="hidden" id="nonce" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sk-seller-listing-search' ) ); ?>" />
    <div class="sk-overlay" style="display: none;"><span class="sk-ajax-loader"></span></div>
</form>

<script>
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
                per_row: '<?php echo esc_attr( $per_row ); ?>',
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
</script>
