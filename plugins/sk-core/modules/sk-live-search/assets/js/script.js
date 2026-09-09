(function($){

    $(document).ready(function($){

        var xhr ,timeout;

        $('body').addClass('woocommerce');

        $('.ajaxsearchform').on('submit',function(e){
            e.preventDefault();
        });

        $('body').on( 'click', function(evt){
            if(!$(evt.target).is('div#sk-ajax-search-suggestion-result li')) {
                $("#sk-ajax-search-suggestion-result").html('');
            }
        });

        function get_div_id() {
            var div_id = skLiveSearch.themeTags[skLiveSearch.currentTheme];
            if ( div_id === undefined ) {
                return $( '#content' ).find( 'ul.products' ).length
                    ? 'ul.products'
                    : '#content';
            }

            return div_id;
        }

        function debounce_delay(callback, ms) {
            var timer   = 0;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function () {
                  callback.apply(context, args);
                }, ms || 0);
            };
        }

        $('body').on('keyup', '.sk-ajax-search-textfield', debounce_delay( function(evt){
            evt.preventDefault();

            var self            = $(this);
            var nurl            = self.closest('form').attr('action');
            var textfield       = self.val();
            var selectfield     = self.closest('.ajaxsearchform').find('.sk-ajax-search-category').val();
            var search_option   = self.closest('.ajaxsearchform').find('.sk-live-search-option').val();

            var ordershort = $('.woocommerce-ordering .orderby').val();

            for_onkeyup_onchange(evt,self, nurl, textfield, selectfield, ordershort, search_option);

        } ,500 ) );

        $('body').on('change', '.sk-ajax-search-category', function(e) {
            e.preventDefault();

            var self            = $(this);
            var nurl            = self.closest('form').attr('action');
            var textfield       = self.closest('.ajaxsearchform').find('.sk-ajax-search-textfield').val();
            var search_option   = self.closest('.ajaxsearchform').find('.sk-live-search-option').val();
            var selectfield     = self.val();
            var ordershort      = $('.woocommerce-ordering .orderby').val();

            for_onkeyup_onchange(e, self, nurl, textfield, selectfield, ordershort, search_option );
        });

        function for_onkeyup_onchange( evt, self, nurl, textfield, selectfield, ordershort, search_option ) {

            if ( ! ordershort ){
                ordershort = '';
            }

            if(selectfield == 'All' && evt.type == 'change' && ordershort == 'menu_order'){

                var url = nurl +'?s='+ textfield.replace(/\s/g,"+")+'&post_type=product';
                loading_get_request( url, textfield, selectfield, search_option );

            } else if(selectfield == 'All' && ordershort == 'menu_order') {

                var url = nurl +'?s='+ textfield.replace(/\s/g,"+")+'&post_type=product';
                loading_get_request( url, textfield, selectfield, search_option );

            } else if(selectfield == 'All' && ordershort != 'menu_order') {

                var url = nurl +'?s='+ textfield.replace(/\s/g,"+")+'&post_type=product&orderby='+ordershort;
                loading_get_request( url, textfield, selectfield, search_option );

            }else if(selectfield != 'All' && ordershort == 'menu_order'){

                var url = nurl +'?s='+ textfield.replace(/\s/g,"+")+'&post_type=product&product_cat='+ selectfield;
                loading_get_request( url, textfield, selectfield, search_option );

            } else {

                var url = nurl +'?s='+ textfield.replace(/\s/g,"+")+'&post_type=product&product_cat='+ selectfield + '&orderby=' + ordershort;
                loading_get_request( url, textfield, selectfield, search_option );

            }
        }

        function loading_get_request( url, textfield, selectfield, search_option ){

            if(search_option === 'old_live_search'){
                var div_id = get_div_id();

                $(div_id).append('<div id="loading"><img src="' + skLiveSearch.loading_img + '" atr="Loding..."/></div>');
                $(div_id).css({'opacity':0.3,'position':'relative'});
                $('#loading').show();

                clearTimeout(timeout);

                if(xhr) {
                xhr.abort();
                }

                timeout = setTimeout(function(){
                 xhr = get_ajax_request( url, textfield, selectfield );
                },150);
            } else {
                $('.ajaxsearchform-sk .sk-ajax-search-suggestion').addClass('sk-ajax-search-loader');
                $('#sk-ajax-search-suggestion-result').hide();
                $("#sk-ajax-search-suggestion-result").html('');

                jQuery.ajax({
                    type : "post",
                    dataType : "json",
                    url : skLiveSearch.ajaxurl,
                    data: {
                        textfield: textfield,
                        selectfield: selectfield,
                        _wpnonce: skLiveSearch.sk_search_nonce,
                        action: skLiveSearch.sk_search_action
                    },
                    success: function(response) {
                        $('.ajaxsearchform-sk .sk-ajax-search-suggestion').removeClass('sk-ajax-search-loader');
                        if ( response.type == 'success' ){
                            $("#sk-ajax-search-suggestion-result").show('');
                            $("#sk-ajax-search-suggestion-result").html('<ul>'+response.data_list+'</ul>');
                        }
                    }
                });

            }

        }

        function get_ajax_request( url, textfield, selectfield ) {

            xhr = $.get(url, function(resp, status) {

                var dom = $(resp).find(get_div_id()).html();

                $('.sk-ajax-search-textfield').val( textfield );
                $('.sk-ajax-search-category').val( selectfield );
                $(get_div_id()).html(dom);

                const p_selector = 'nav.woocommerce-pagination, .woocommerce-pagination';
                const p_html = $(resp).find(p_selector).html() || null;
                p_html ? $( p_selector ).html( p_html ).css({ 'opacity': '1' }) : $( p_selector ).css({ 'opacity': '0' });
                const count_selector = '.woocommerce-result-count';
                const count_html = $(resp).find(count_selector).html() || null;
                count_html ? $( count_selector ).html( count_html ).css({ 'opacity': '1' }) : $( count_selector ).css({ 'opacity': '0' });
                $('#loading').hide();
                $('.sk-geolocation-filters-loading').hide();
                $(get_div_id()).css({'opacity':1,'position':'auto'});
                $('#content').css({'opacity':'1'});

                $('.woocommerce-ordering').on('change','.orderby',function(e){
                    e.preventDefault();

                    var self = $(this);
                    var nurl = $('.ajaxsearchform').attr('action');
                    var textfield = $('.sk-ajax-search-textfield').val();
                    var selectfield = $('.sk-ajax-search-category').val();
                    var ordershort = self.val();

                    for_onkeyup_onchange(e, self, nurl, textfield, selectfield, ordershort );

                });

            });

            return xhr;
        }
    });

})(jQuery)
