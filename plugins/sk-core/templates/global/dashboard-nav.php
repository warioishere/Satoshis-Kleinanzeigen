<?php
$home_url     = home_url();
$active_class = ' class="active"'
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
<script>
(function () {
    var nav = document.getElementById('sk-navigation');
    if (!nav) return;
    nav.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (!a) return;
        var toggle = document.getElementById('toggle-mobile-menu');
        if (toggle) toggle.checked = false;
    });
})();
</script>
