<?php
/**
 * SK → Händler.
 *
 * @var \WP_User[] $vendors
 * @var string $notice
 * @var string $search
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\ShopImport\Dealer;

$base = add_query_arg( [ 'page' => 'sk', 'tab' => 'dealers' ], admin_url( 'admin.php' ) );
?>

<?php if ( $notice === 'saved' ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Gespeichert.', 'sk-core' ); ?></p></div>
<?php endif; ?>

<h2 style="margin-top:0;"><?php esc_html_e( 'Händler', 'sk-core' ); ?></h2>
<p style="color:#646970;max-width:760px;">
    <?php esc_html_e( 'Geprüft heisst: Du hast dich vergewissert, dass hinter dem Konto der genannte Shop steht. Das ist die Grundlage für den Katalogimport — und später für den Sofortkauf über Lightning, bei dem der Käufer ohne Treuhand direkt in die Wallet des Verkäufers zahlt.', 'sk-core' ); ?>
</p>

<form method="get" style="margin:14px 0;">
    <input type="hidden" name="page" value="sk">
    <input type="hidden" name="tab" value="dealers">
    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Verkäufer suchen', 'sk-core' ); ?>">
    <button type="submit" class="button"><?php esc_html_e( 'Suchen', 'sk-core' ); ?></button>
    <?php if ( $search !== '' ) : ?>
        <a class="button" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'nur Händler zeigen', 'sk-core' ); ?></a>
    <?php endif; ?>
</form>

<?php if ( empty( $vendors ) ) : ?>
    <p><?php esc_html_e( 'Kein Verkäufer gefunden. Nutze die Suche, um einen freizuschalten.', 'sk-core' ); ?></p>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped" style="max-width:1200px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Verkäufer', 'sk-core' ); ?></th>
                <th style="width:240px;"><?php esc_html_e( 'Shop-Adresse', 'sk-core' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( 'bestätigte Domain', 'sk-core' ); ?></th>
                <th style="width:90px;"><?php esc_html_e( 'geprüft', 'sk-core' ); ?></th>
                <th style="width:110px;"><?php esc_html_e( 'darf importieren', 'sk-core' ); ?></th>
                <th style="width:150px;"><?php esc_html_e( 'letzter Import', 'sk-core' ); ?></th>
                <th style="width:110px;"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $vendors as $vendor ) : ?>
            <?php $last = (int) get_user_meta( $vendor->ID, Dealer::META_LAST_RUN, true ); ?>
            <tr>
                <form method="post" action="<?php echo esc_url( $base ); ?>">
                    <?php wp_nonce_field( 'sk_dealer_action', 'sk_dealer_nonce' ); ?>
                    <input type="hidden" name="vendor_id" value="<?php echo (int) $vendor->ID; ?>">
                    <td>
                        <strong><?php echo esc_html( $vendor->display_name ); ?></strong><br>
                        <span style="color:#646970;font-size:12px;"><?php echo esc_html( $vendor->user_email ); ?></span>
                    </td>
                    <td><input type="url" name="shop_url" class="regular-text" style="width:100%;"
                               value="<?php echo esc_attr( Dealer::shop_url( $vendor->ID ) ); ?>" placeholder="https://"></td>
                    <td>
                        <?php
                        /*
                         * Der Haendler bestaetigt seine Domain selbst und darf
                         * damit importieren — ohne dass hier ein Haekchen
                         * gesetzt wird. Ohne diese Spalte sieht es im Admin
                         * aus, als sei nichts passiert.
                         */
                        $sk_hosts = \SK\Core\Verification\VerifiedLinks::confirmed_hosts( $vendor->ID );

                        if ( $sk_hosts ) {
                            echo '<span style="color:#f7931a;">✓ ' . esc_html( implode( ', ', $sk_hosts ) ) . '</span>';
                        } else {
                            echo '<span style="color:#646970;">—</span>';
                        }
                        ?>
                    </td>
                    <td><input type="checkbox" name="verified" value="1" <?php checked( Dealer::is_verified( $vendor->ID ) ); ?>></td>
                    <td><input type="checkbox" name="import" value="1" <?php checked( Dealer::is_enabled( $vendor->ID ) ); ?>></td>
                    <td><?php echo $last ? esc_html( wp_date( 'd.m.Y H:i', $last ) ) : '—'; ?></td>
                    <td><button type="submit" class="button"><?php esc_html_e( 'Speichern', 'sk-core' ); ?></button></td>
                </form>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
