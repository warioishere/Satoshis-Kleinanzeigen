/**
 * Sale price date pickers of the variation editor: keep the from/to range
 * consistent while either field is picked.
 */
;(function($){
    let htmlBody = $('body');
    htmlBody.on( 'click', '.sale_price_dates_from', function(){
        if ( ! $(this).hasClass( 'hasDatePicker' ) ) {
            let fromInput = $(this);
            let fromName  = fromInput.attr('name');
            let toName    = fromName.replace( '_from', '_to' );
            let toInput   = $( 'input[name^="'+ toName +'"]' );
            $(fromInput).datepicker({
                defaultDate: '',
                dateFormat: 'yy-mm-dd',
                numberOfMonths: 1,
                onSelect: function(selectedDate) {
                    let date = new Date(selectedDate);
                    date.setDate(date.getDate() + 1);
                    if ( ! $(toInput).hasClass( 'hasDatePicker' ) ) {
                        $(toInput).datepicker({
                            defaultDate: '',
                            dateFormat: 'yy-mm-dd',
                            numberOfMonths: 1,
                            minDate: date
                        });
                    } else {
                        $(toInput).datepicker('option', {
                            minDate: date
                        });
                    }
                }
            });

            $(fromInput).datepicker( 'show' );
        }
    });

    htmlBody.on( 'click', '.sale_price_dates_to', function(){
        if ( ! $(this).hasClass( 'hasDatePicker' ) ) {
            let toInput = $(this);
            let toName  = toInput.attr('name');
            let fromName    = toName.replace( '_to', '_from' );
            let fromInput   = $( 'input[name^="'+ fromName +'"]' );
            $(toInput).datepicker({
                defaultDate: '',
                dateFormat: 'yy-mm-dd',
                numberOfMonths: 1,
                onSelect: function(selectedDate) {
                    let date = new Date(selectedDate);
                    date.setDate(date.getDate() + 1);

                    if ( ! $(fromInput).hasClass( 'hasDatePicker' ) ) {
                        $(fromInput).datepicker({
                            defaultDate: '',
                            dateFormat: 'yy-mm-dd',
                            numberOfMonths: 1,
                            maxDate: date
                        });
                    } else {
                        $(fromInput).datepicker('option', {
                            maxDate: date
                        });
                    }
                }
            });

            $(toInput).datepicker( 'show' );
        }
    });
})(jQuery);
