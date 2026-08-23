<?php
/**
 * Sponsorenraster für [sk_sponsors].
 *
 * Gibt bewusst dieselbe Struktur und dieselben Klassen aus wie der frühere
 * kadence/posts-Block, damit das Aussehen unverändert bleibt: Das Raster
 * (grid-cols, grid-lg-col-3, content-bg, loop-entry, kadence-thumbnail-ratio-1-1)
 * kommt aus der Theme-CSS, die auf jeder Seite geladen wird. Nur die wenigen
 * Regeln, die sonst das Blocks-Plugin beisteuern würde, liegen in
 * assets/css/sk-sponsors.css.
 *
 * @var \WP_Post[] $sponsors
 * @var string     $heading
 * @var int        $columns
 * @var string     $tier
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\Sponsors\Tracker;

$grid_class = 'grid-lg-col-' . max( 1, min( 6, (int) $columns ) );
$instance   = 'kb-posts-id-sk-sponsors-' . ( $tier !== '' ? $tier : 'all' );
?>
<?php if ( $heading !== '' ) : ?>
    <h2 class="wp-block-heading has-text-align-center"><?php echo esc_html( $heading ); ?></h2>
<?php endif; ?>
<ul class="wp-block-kadence-posts kb-posts kadence-posts-list <?php echo esc_attr( $instance ); ?> sk-sponsors content-wrap grid-cols kb-posts-style-boxed grid-sm-col-2 <?php echo esc_attr( $grid_class ); ?> item-image-style-above">
    <?php foreach ( $sponsors as $sponsor ) : ?>
        <?php $link = Tracker::link_for( $sponsor ); ?>
        <li class="kb-post-list-item">
            <article class="entry content-bg loop-entry sk-sponsor-entry sk-sponsor-<?php echo (int) $sponsor->ID; ?>">
                <?php if ( has_post_thumbnail( $sponsor->ID ) ) : ?>
                    <a aria-hidden="true" tabindex="-1" role="presentation"
                       class="post-thumbnail kadence-thumbnail-ratio-1-1"
                       href="<?php echo esc_url( $link ); ?>"
                       aria-label="<?php echo esc_attr( $sponsor->post_title ); ?>"
                       target="_blank" rel="nofollow sponsored noopener">
                        <div class="post-thumbnail-inner">
                            <?php
                            echo get_the_post_thumbnail(
                                $sponsor->ID,
                                'medium_large',
                                [
                                    'loading' => 'lazy',
                                    'alt'     => esc_attr( $sponsor->post_title ),
                                ]
                            );
                            ?>
                        </div>
                    </a><!-- .post-thumbnail -->
                <?php endif; ?>
                <div class="entry-content-wrap">
                    <header class="entry-header">
                        <h3 class="entry-title">
                            <a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $sponsor->post_title ); ?></a>
                        </h3>
                    </header><!-- .entry-header -->
                    <?php
                    $text = trim( wp_strip_all_tags( $sponsor->post_content ) );
                    if ( $text !== '' ) :
                        ?>
                        <div class="entry-summary">
                            <p><?php echo esc_html( $text ); ?></p>
                        </div><!-- .entry-summary -->
                    <?php endif; ?>
                    <footer class="entry-footer">
                    </footer><!-- .entry-footer -->
                </div>
            </article>
        </li>
    <?php endforeach; ?>
</ul>
