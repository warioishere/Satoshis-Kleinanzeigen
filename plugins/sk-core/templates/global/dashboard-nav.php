<?php
$home_url     = home_url();
$active_class = ' class="active"';

wp_enqueue_script( 'sk-dashboard-nav' );
?>

<div class="sk-dash-sidebar">
    <?php
    global $allowedposttags;
    do_action( 'sk_dashboard_sidebar_start' );

    // These are required for the hamburger menu.
    if ( is_array( $allowedposttags ) ) {
        $allowedposttags['input'] = [ // phpcs:ignore
            'id'      => [],
            'type'    => [],
            'checked' => [],
        ];
        $allowedposttags['svg'] = [ // phpcs:ignore
            'fill'        => [],
            'role'        => [],
            'xmlns'       => [],
            'width'       => [],
            'height'      => [],
            'viewbox'     => [],
            'focusable'   => [],
            'aria-hidden' => [],
        ];
        $allowedposttags['path'] = [ // phpcs:ignore
            'd'    => [],
            'fill' => [],
        ];
    }

    echo wp_kses( sk_dashboard_nav( $active_menu ), $allowedposttags );

    do_action( 'sk_dashboard_sidebar_end' );
    ?>
</div>
