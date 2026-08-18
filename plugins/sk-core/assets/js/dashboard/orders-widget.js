/**
 * Doughnut chart of the dashboard orders widget.
 *
 * Counts, colours and labels come from PHP via wp_localize_script.
 */
jQuery( function () {
    'use strict';

    var config = window.skOrdersWidget || {};
    var canvas = document.getElementById( 'order-stats' );

    if ( ! canvas || typeof Chart === 'undefined' ) {
        return;
    }

    new Chart( canvas.getContext( '2d' ), {
        type: 'doughnut',
        data: {
            datasets: [ {
                data: config.values,
                backgroundColor: config.colors
            } ],
            labels: config.labels,
        },
        options: {
            plugins: {
                legend: false
            }
        }
    } );
} );
