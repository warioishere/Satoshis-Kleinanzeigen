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
        <a href="<?php echo esc_url( $base_url . '&sub=signals' ); ?>"
           class="nav-tab <?php echo $sub === 'signals' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'Ban-Signale', 'sk-core' ); ?>
            <?php if ( ! empty( $signals ) ) : ?>
                <span class="count">(<?php echo esc_html( count( $signals ) ); ?>)</span>
            <?php endif; ?>
        </a>
    </h2>

    <?php if ( isset( $_GET['added'] ) ) : ?>
        <div class="notice notice-<?php echo $_GET['added'] === 'true' ? 'success' : 'warning'; ?> is-dismissible">
            <p><?php echo $_GET['added'] === 'true'
                ? esc_html__( 'Merkmal gesperrt.', 'sk-core' )
                : esc_html__( 'Nicht gespeichert — leer oder bereits vorhanden.', 'sk-core' ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['removed'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Merkmal entfernt.', 'sk-core' ); ?></p></div>
    <?php endif; ?>

    <?php if ( 'signals' === $sub ) : ?>

        <p class="description" style="margin-bottom:12px;">
            <?php esc_html_e( 'Merkmale gesperrter Anbieter. Taucht eins davon auf einem anderen Account auf — beim Login oder beim Speichern der Shop-Einstellungen — bekommst du eine E-Mail. Wirkt auch bei Tor und wechselnder IP, weil ein Betrüger sich bezahlen lassen und erreichbar sein muss.', 'sk-core' ); ?>
        </p>

        <div style="background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin-bottom:16px;max-width:760px;">
            <h3 style="margin-top:0;"><?php esc_html_e( 'Merkmal von Hand sperren', 'sk-core' ); ?></h3>
            <p class="description" style="margin-bottom:10px;">
                <?php esc_html_e( 'Für Fälle, in denen der Account schon gelöscht ist — etwa ein Telegram-Handle aus dem Kanal.', 'sk-core' ); ?>
            </p>
            <form method="post">
                <?php wp_nonce_field( 'sk_antifraud_action', 'sk_antifraud_nonce' ); ?>
                <input type="hidden" name="antifraud_action" value="add_signal">
                <select name="signal_type">
                    <?php foreach ( $signal_types as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="signal_value" class="regular-text"
                       placeholder="<?php esc_attr_e( 'z. B. satoshihunter21', 'sk-core' ); ?>" required>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Sperren', 'sk-core' ); ?></button>
            </form>
        </div>

        <?php if ( empty( $signals ) ) : ?>
            <p><?php esc_html_e( 'Noch keine gesperrten Merkmale.', 'sk-core' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:16%;"><?php esc_html_e( 'Typ', 'sk-core' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Wert', 'sk-core' ); ?></th>
                        <th scope="col" style="width:20%;"><?php esc_html_e( 'Von Account', 'sk-core' ); ?></th>
                        <th scope="col" style="width:14%;"><?php esc_html_e( 'Gesperrt am', 'sk-core' ); ?></th>
                        <th scope="col" style="width:10%;"><?php esc_html_e( 'Aktion', 'sk-core' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $signals as $signal ) :
                        $from = $signal->banned_user_id ? get_userdata( $signal->banned_user_id ) : null;
                        ?>
                        <tr>
                            <td><?php echo esc_html( $signal_types[ $signal->signal_type ] ?? $signal->signal_type ); ?></td>
                            <td><code style="word-break:break-all;"><?php echo esc_html( $signal->signal_value ); ?></code></td>
                            <td>
                                <?php if ( $from ) : ?>
                                    <a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $from->ID ) ); ?>">
                                        <?php echo esc_html( $from->user_login ); ?>
                                    </a>
                                <?php elseif ( $signal->banned_user_id ) : ?>
                                    <em><?php printf( esc_html__( 'gelöscht (#%d)', 'sk-core' ), (int) $signal->banned_user_id ); ?></em>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'von Hand', 'sk-core' ); ?></em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $signal->banned_at ) ) ); ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Merkmal entsperren?', 'sk-core' ) ); ?>');">
                                    <?php wp_nonce_field( 'sk_antifraud_action', 'sk_antifraud_nonce' ); ?>
                                    <input type="hidden" name="antifraud_action" value="delete_signal">
                                    <input type="hidden" name="signal_id" value="<?php echo esc_attr( $signal->id ); ?>">
                                    <button type="submit" class="button button-small"><?php esc_html_e( 'Entfernen', 'sk-core' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php elseif ( 'general' === $sub ) : ?>

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
