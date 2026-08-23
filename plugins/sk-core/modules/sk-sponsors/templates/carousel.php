<?php
/**
 * Sponsorenslider — Markup identisch zu wp-post-image-carousel.
 *
 * @var \WP_Post[] $sponsors
 * @var string     $uid
 * @var string     $direction
 * @var int        $gap
 * @var int        $h
 * @var int        $v
 * @var string     $arrows
 * @var string     $extra
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\Sponsors\Tracker;
?>
<div id="<?php echo esc_attr( $uid ); ?>"
     class="wppis-slider <?php echo esc_attr( $direction . $extra ); ?>"
     data-direction="<?php echo esc_attr( $direction ); ?>"
     data-gap="<?php echo esc_attr( (string) $gap ); ?>"
     data-h="<?php echo esc_attr( (string) $h ); ?>"
     data-v="<?php echo esc_attr( (string) $v ); ?>"
     data-arrows="<?php echo esc_attr( $arrows ); ?>">
  <div class="wppis-track">
    <?php foreach ( $sponsors as $sponsor ) : ?>
        <div class="wppis-slide">
          <a href="<?php echo esc_url( Tracker::link_for( $sponsor ) ); ?>" class="wppis-link"
             aria-label="<?php echo esc_attr( $sponsor->post_title ); ?>"
             target="_blank" rel="nofollow sponsored noopener">
            <figure class="wppis-figure">
              <?php echo get_the_post_thumbnail( $sponsor->ID, 'large', [ 'loading' => 'lazy', 'decoding' => 'async' ] ); ?>
            </figure>
          </a>
        </div>
    <?php endforeach; ?>
  </div>
  <button class="wppis-arrow prev" aria-label="<?php esc_attr_e( 'Zurück', 'sk-core' ); ?>">&lsaquo;</button>
  <button class="wppis-arrow next" aria-label="<?php esc_attr_e( 'Weiter', 'sk-core' ); ?>">&rsaquo;</button>
</div>
