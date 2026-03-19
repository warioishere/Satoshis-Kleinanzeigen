<?php
/**
 * SK Settings Payment Template
 *
 *
 */
$has_methods = false;

do_action( 'sk_payment_settings_before_form', $current_user, $profile_info ); ?>

<div class="sk-payment-settings-summary">
    <h2 id="vendor-dashboard-payment-settings-error"></h2>
    <div id="sk-payment-methods-listing-wrapper" class="payment-methods-listing-header">
        <h2> <?php esc_html_e( 'Payment Methods', 'sk-core' ); ?></h2>
        <div>
            <div id="vendor-dashboard-payment-settings-toggle-dropdown">
                <a id="toggle-vendor-payment-method-drop-down"> <?php esc_html_e( 'Add Payment Method', 'sk-core' ); ?></a>
                <div id="vendor-payment-method-drop-down-wrapper">
                    <div id="vendor-payment-method-drop-down">
                        <?php if ( is_array( $unused_methods ) && ! empty( $unused_methods ) ) : ?>
                            <ul>
                                <?php foreach ( $unused_methods as $method_key => $method ) : ?>
                                    <li>
                                        <a href="<?php echo esc_url( sk_get_navigation_url( 'settings/payment-manage-' . $method_key ) ); ?>">
                                            <div>
                                                <span>
                                                <?php
                                                printf(
                                                    // translators: %s: payment method title
                                                    esc_html__( 'Direct to %s', 'sk-core' ),
                                                    esc_html( apply_filters( 'sk_payment_method_title', $method['title'], $method ) )
                                                );
                                                ?>
                                            </span>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <div class="no-content">
                                <?php esc_html_e( 'There is no payment method to add.', 'sk-core' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if ( is_array( $methods ) && ! empty( $methods ) ) : ?>
        <ul>
            <?php foreach ( $methods as $method_key => $method ) : ?>
                <li>
                    <div>
                        <div>
                            <span>
                            <?php
                            echo esc_html( apply_filters( 'sk_payment_method_title', $method['title'], $method ) );
                            ?>
                        </span>
                        </div>
                        <div>
                            <a href="<?php echo esc_url( sk_get_navigation_url( 'settings/payment-manage-' . $method_key . '-edit' ) ); ?>">
                                <button class="sk-btn-theme sk-btn-sm"><?php esc_html_e( 'Manage', 'sk-core' ); ?></button>
                            </a>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <div class="no-content">
            <?php esc_html_e( 'There is no payment method to show.', 'sk-core' ); ?>
        </div>
    <?php endif; ?>
</div>

<?php
/**
 */
do_action( 'sk_payment_settings_after_form', $current_user, $profile_info ); ?>
