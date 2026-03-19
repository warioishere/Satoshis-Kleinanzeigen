<?php
/**
 * Admin PHP Dashboard Layout
 *
 * @var string $tab Current tab slug
 * @var array $tabs Tab slug => title pairs
 * @var \SK\Core\Admin\PhpDashboard\AbstractPage $current_page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap sk-php-dashboard">
    <h1><?php esc_html_e( 'SK Dashboard', 'sk-core' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) && $_GET['saved'] === 'true' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Settings saved successfully.', 'sk-core' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['deleted'] ) && $_GET['deleted'] === 'true' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Item deleted successfully.', 'sk-core' ); ?></p>
        </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $title ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sk&tab=' . $slug ) ); ?>"
               class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $title ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sk-php-dashboard-content">
        <?php $current_page->render(); ?>
    </div>
</div>
