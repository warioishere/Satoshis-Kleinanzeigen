<?php
/**
 * The vendor is away — shown before anybody writes to them.
 *
 * @var string $note  What the vendor wrote, may be empty.
 * @var string $until Last day of the break as Y-m-d, may be empty.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="sk-vacation-note" role="status">
    <i class="fas fa-umbrella-beach" aria-hidden="true"></i>
    <div>
        <strong>
            <?php
            if ( '' !== $until ) {
                printf(
                    /* translators: %s: date */
                    esc_html__( 'Gerade nicht erreichbar, voraussichtlich bis %s', 'sk-core' ),
                    esc_html( wp_date( 'j. F Y', strtotime( $until . ' 12:00:00' ) ) )
                );
            } else {
                esc_html_e( 'Gerade nicht erreichbar', 'sk-core' );
            }
            ?>
        </strong>
        <?php if ( '' !== $note ) : ?>
            <span><?php echo esc_html( $note ); ?></span>
        <?php endif; ?>
    </div>
</div>
