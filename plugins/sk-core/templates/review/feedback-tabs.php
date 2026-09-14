<?php
/**
 * Product reviews, shop reviews and questions as three tabs.
 *
 * @var string $current
 * @var int    $open_asked
 * @var int    $open_shop
 */

defined( 'ABSPATH' ) || exit;

use SK\Core\Dashboard\Modules\FeedbackTabs;

$sk_entries = [
    FeedbackTabs::PRODUCT => [ 'label' => __( 'Produkt-Bewertungen', 'sk-core' ), 'open' => 0 ],
    FeedbackTabs::SHOP    => [ 'label' => __( 'Shop-Bewertungen', 'sk-core' ), 'open' => (int) $open_shop ],
    FeedbackTabs::ASKED   => [ 'label' => __( 'Fragen', 'sk-core' ), 'open' => (int) $open_asked ],
];
?>
<div class="sk-review-page-header">
    <h2><i class="far fa-comments"></i> <?php esc_html_e( 'Rückmeldungen', 'sk-core' ); ?></h2>
</div>

<ul class="sk-questions-tabs sk-feedback-tabs">
    <?php foreach ( $sk_entries as $sk_key => $sk_entry ) : ?>
        <li<?php echo $sk_key === $current ? ' class="active"' : ''; ?>>
            <a href="<?php echo esc_url( FeedbackTabs::url( $sk_key ) ); ?>">
                <?php echo esc_html( $sk_entry['label'] ); ?>
                <?php if ( $sk_entry['open'] > 0 ) : ?>
                    <span class="sk-feedback-tabs__dot" aria-label="<?php esc_attr_e( 'offen', 'sk-core' ); ?>"><?php echo (int) $sk_entry['open']; ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
