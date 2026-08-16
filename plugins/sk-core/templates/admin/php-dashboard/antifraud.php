<?php
/**
 * Anti-Fraud tab — settings + suspension list.
 *
 * @var string $sub       Active sub tab (general|suspended)
 * @var string $base_url  admin.php?page=sk&tab=antifraud
 * @var array  $fields    Field definitions
 * @var array  $opts      Current values
 * @var array  $suspended Suspended vendors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="sk-antifraud-wrap">

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellungen gespeichert.', 'sk-core' ); ?></p></div>
    <?php endif; ?>

    <?php if ( isset( $_GET['restored'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php
                $restored = absint( $_GET['restored'] );
                printf(
                    esc_html( _n( 'Anbieter freigeschaltet, %d Inserat wieder online.', 'Anbieter freigeschaltet, %d Inserate wieder online.', $restored, 'sk-core' ) ),
                    $restored
                );
            ?></p>
        </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper" style="margin-bottom:16px;">
        <a href="<?php echo esc_url( $base_url . '&sub=general' ); ?>"
           class="nav-tab <?php echo $sub === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Allgemein', 'sk-core' ); ?>
        </a>
        <a href="<?php echo esc_url( $base_url . '&sub=suspended' ); ?>"
           class="nav-tab <?php echo $sub === 'suspended' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Auto-Suspend', 'sk-core' ); ?>
            <?php if ( ! empty( $suspended ) ) : ?>
                <span class="count">(<?php echo esc_html( count( $suspended ) ); ?>)</span>
            <?php endif; ?>
        </a>
    </h2>

    <?php if ( 'general' === $sub ) : ?>

        <form method="post">
            <?php wp_nonce_field( 'sk_antifraud_action', 'sk_antifraud_nonce' ); ?>
            <input type="hidden" name="antifraud_action" value="save_settings">

            <table class="form-table" role="presentation">
                <?php foreach ( $fields as $name => $field ) :
                    $type  = $field['type'];
                    $value = $opts[ $name ] ?? ( $field['default'] ?? '' );

                    if ( 'sub_section' === $type ) : ?>
                        <tr>
                            <th colspan="2" style="padding-bottom:0;">
                                <h3 style="margin:20px 0 4px;"><?php echo esc_html( $field['label'] ); ?></h3>
                                <?php if ( ! empty( $field['desc'] ) ) : ?>
                                    <p class="description" style="font-weight:400;"><?php echo esc_html( $field['desc'] ); ?></p>
                                <?php endif; ?>
                            </th>
                        </tr>
                        <?php continue;
                    endif; ?>

                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
                        </th>
                        <td>
                            <?php if ( 'switcher' === $type ) : ?>
                                <input type="hidden" name="sk_antifraud[<?php echo esc_attr( $name ); ?>]" value="off">
                                <label>
                                    <input type="checkbox" id="<?php echo esc_attr( $name ); ?>"
                                           name="sk_antifraud[<?php echo esc_attr( $name ); ?>]"
                                           value="on" <?php checked( 'on', $value ); ?>>
                                    <?php esc_html_e( 'Aktiv', 'sk-core' ); ?>
                                </label>
                            <?php else : ?>
                                <input type="text" id="<?php echo esc_attr( $name ); ?>"
                                       name="sk_antifraud[<?php echo esc_attr( $name ); ?>]"
                                       value="<?php echo esc_attr( $value ); ?>"
                                       class="regular-text"
                                       <?php if ( ! empty( $field['placeholder'] ) ) : ?>placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"<?php endif; ?>>
                            <?php endif; ?>

                            <?php if ( ! empty( $field['desc'] ) ) : ?>
                                <p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <?php submit_button(); ?>
        </form>

    <?php else : ?>

        <?php if ( empty( $suspended ) ) : ?>
            <p><?php esc_html_e( 'Aktuell ist kein Anbieter offline genommen.', 'sk-core' ); ?></p>
        <?php else : ?>

            <p class="description" style="margin-bottom:12px;">
                <?php esc_html_e( 'Beim Freischalten werden genau die Inserate wieder veröffentlicht, die durch die Sperre offline gingen — selbst gespeicherte Entwürfe des Anbieters bleiben unberührt.', 'sk-core' ); ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:18%;"><?php esc_html_e( 'Anbieter', 'sk-core' ); ?></th>
                        <th scope="col" style="width:14%;"><?php esc_html_e( 'Seit', 'sk-core' ); ?></th>
                        <th scope="col" style="width:16%;"><?php esc_html_e( 'Grund', 'sk-core' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Offline genommene Inserate', 'sk-core' ); ?></th>
                        <th scope="col" style="width:14%;"><?php esc_html_e( 'Aktion', 'sk-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $suspended as $row ) :
                        $user  = $row['user'];
                        $store = get_user_meta( $user->ID, 'sk_store_name', true );
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $store ? $store : $user->display_name ); ?></strong><br>
                                <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ); ?>">
                                    <?php echo esc_html( $user->user_login ); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo $row['since']
                                    ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $row['since'] ) ) )
                                    : '—'; ?>
                            </td>
                            <td><code style="font-size:11px;"><?php echo esc_html( $row['reason'] ?: '—' ); ?></code></td>
                            <td>
                                <?php if ( empty( $row['products'] ) ) : ?>
                                    <em><?php esc_html_e( 'keine', 'sk-core' ); ?></em>
                                <?php else : ?>
                                    <ul style="margin:0;">
                                        <?php foreach ( $row['products'] as $product ) : ?>
                                            <li>
                                                <a href="<?php echo esc_url( admin_url( 'post.php?post=' . $product->ID . '&action=edit' ) ); ?>">
                                                    <?php echo esc_html( $product->post_title ); ?>
                                                </a>
                                                <span style="color:#787c82;">
                                                    (<?php echo esc_html( get_post_status( $product->ID ) ); ?>)
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Anbieter freischalten und Inserate wieder veröffentlichen?', 'sk-core' ) ); ?>');">
                                    <?php wp_nonce_field( 'sk_antifraud_action', 'sk_antifraud_nonce' ); ?>
                                    <input type="hidden" name="antifraud_action" value="unsuspend">
                                    <input type="hidden" name="vendor_id" value="<?php echo esc_attr( $user->ID ); ?>">
                                    <button type="submit" class="button button-primary button-small">
                                        <?php esc_html_e( 'Freischalten', 'sk-core' ); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php endif; ?>
</div>
