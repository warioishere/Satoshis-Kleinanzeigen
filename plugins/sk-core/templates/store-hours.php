<?php
/**
 * Opening hours in the contact block of the vendor page.
 *
 * Days that were left empty are not listed — a row saying "closed" seven
 * times over is noise. Today is marked, because that is the one line
 * anybody actually came for.
 *
 * @var array<string,array{from:string,to:string}> $hours
 * @var bool                                       $away  Vendor is on a break.
 */

defined( 'ABSPATH' ) || exit;

$sk_days  = sk_store_weekdays();
$sk_keys  = array_keys( $sk_days );
// wp_date('w') counts from Sunday, our week starts on Monday.
$sk_today = $sk_keys[ ( (int) wp_date( 'w' ) + 6 ) % 7 ];
$sk_now   = wp_date( 'H:i' );

// A break beats the timetable, otherwise the notice above would say
// "not reachable" while this line cheerfully says "open now".
$sk_open = empty( $away )
    && isset( $hours[ $sk_today ] )
    && $sk_now >= $hours[ $sk_today ]['from']
    && $sk_now < $hours[ $sk_today ]['to'];
?>
<div class="sk-store-hours">
    <div class="sk-store-hours__now">
        <i class="fas fa-clock" aria-hidden="true"></i>
        <?php if ( $sk_open ) : ?>
            <strong class="is-open">
                <?php
                printf(
                    /* translators: %s: time */
                    esc_html__( 'Jetzt geöffnet, bis %s Uhr', 'sk-core' ),
                    esc_html( $hours[ $sk_today ]['to'] )
                );
                ?>
            </strong>
        <?php elseif ( ! empty( $away ) ) : ?>
            <strong class="is-closed"><?php esc_html_e( 'Wegen Pause geschlossen', 'sk-core' ); ?></strong>
        <?php else : ?>
            <strong class="is-closed"><?php esc_html_e( 'Jetzt geschlossen', 'sk-core' ); ?></strong>
        <?php endif; ?>
    </div>

    <ul class="sk-store-hours__list">
        <?php foreach ( $hours as $sk_day => $sk_span ) : ?>
            <li<?php echo $sk_day === $sk_today ? ' class="is-today"' : ''; ?>>
                <span><?php echo esc_html( $sk_days[ $sk_day ] ); ?></span>
                <span><?php echo esc_html( $sk_span['from'] . ' – ' . $sk_span['to'] ); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
