<?php
$post_statuses = sk_get_available_post_status( $post->ID );
?>

<div class="sk-other-options sk-edit-row sk-clearfix <?php echo esc_attr( $class ); ?>">
    <div class="sk-section-heading" data-togglehandler="sk_other_options">
        <h2><i class="fas fa-cog" aria-hidden="true"></i> <?php esc_html_e( 'Other Options', 'sk-core' ); ?></h2>
        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">
        <div class="sk-form-group content-half-part">
            <label for="post_status" class="form-label"><?php esc_html_e( 'Product Status', 'sk-core' ); ?></label>
            <select id="post_status" class="sk-form-control" name="post_status">
                <?php foreach ( $post_statuses as $status => $label ) : // phpcs:ignore ?>
                    <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status, $post_status ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sk-form-group content-half-part">
            <label for="_visibility" class="form-label"><?php esc_html_e( 'Visibility', 'sk-core' ); ?></label>
            <select name="_visibility" id="_visibility" class="sk-form-control">
                <?php foreach ( $visibility_options as $name => $label ) : ?>
                    <option value="<?php echo esc_attr( $name ); ?>" <?php selected( $_visibility, $name ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sk-clearfix"></div>

        <div class="sk-form-group">
            <label for="_purchase_note" class="form-label"><?php esc_html_e( 'Purchase Note', 'sk-core' ); ?></label>
            <?php sk_post_input_box( $post_id, '_purchase_note', array( 'placeholder' => __( 'Customer will get this info in their order email', 'sk-core' ) ), 'textarea' ); ?>
        </div>

        <div class="sk-form-group">
            <?php
            sk_post_input_box(
                $post_id,
                '_enable_reviews',
                [
                    'value' => 'open' === $post->comment_status ? 'yes' : 'no',
                    'label' => __( 'Enable product reviews', 'sk-core' ),
                ],
                'checkbox'
            );
            ?>
        </div>

        <?php
        /*
         * Nostr: the decision is made here on the listing, no longer
         * additionally on the vendor profile. Default is off — publishing to
         * a foreign network shouldn't happen without being asked.
         */
        if ( sk_module_active( 'sk_nostr_market' ) && sk_get_option( 'sk_nostr_market_enabled', 'sk_nostr_market', 'off' ) === 'on' ) :
            $nostr_checked = get_post_meta( $post_id, '_sk_nostr_market_post', true ) === '1';

            /*
             * Does the vendor even have a Nostr identity?
             *
             * Without one, their listing can't go on Nostr at all: we won't
             * publish anything for them under our own key. This is the
             * moment they should learn that — not buried somewhere in
             * settings, but right here where they enable the option.
             */
            $nostr_uid  = (int) get_current_user_id();
            $hat_key    = ! empty( get_user_meta( $nostr_uid, 'nostr_public_key', true ) );
            $linker_url = function_exists( 'sk_get_navigation_url' )
                ? sk_get_navigation_url( 'auth-connector' )
                : home_url( '/dashboard/auth-connector/' );
        ?>
        <div class="sk-form-group">
            <label>
                <input type="hidden" name="_sk_nostr_market_post" value="0" />
                <input type="checkbox" name="_sk_nostr_market_post" value="1" <?php checked( $nostr_checked ); ?>>
                <?php esc_html_e( 'Auf Nostr veröffentlichen', 'sk-core' ); ?>
            </label>
            <p class="sk-settings-hint">
                <?php esc_html_e( 'Dein Inserat erscheint zusätzlich im Nostr-Netzwerk unter deinem eigenen Schlüssel, sichtbar in Clients wie Amethyst. Du kannst es jederzeit wieder abwählen, dann wird es dort entfernt.', 'sk-core' ); ?>
            </p>

            <?php if ( ! $hat_key ) : ?>
                <div class="sk-alert sk-alert-info" id="sk-nostr-identity-hint" style="display:none;">
                    <strong><?php esc_html_e( 'Dafür brauchst du einen eigenen Nostr-Schlüssel', 'sk-core' ); ?></strong>
                    <p>
                        <?php esc_html_e( 'Auf Nostr trägt jedes Inserat den Namen dessen, der es eingestellt hat. Wir veröffentlichen nichts unter unserem Namen für dich. Ohne eigenen Schlüssel bleibt dein Inserat also hier auf der Plattform, es geht nur nicht ins Nostr-Netz.', 'sk-core' ); ?>
                    </p>
                    <p>
                        <?php esc_html_e( 'Mit eigenem Schlüssel steht dein Name darunter, und Käufer schreiben dich direkt an — die Antwort landet wieder hier in deinem Chat.', 'sk-core' ); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url( $linker_url ); ?>" class="sk-btn sk-btn-default">
                            <i class="fas fa-plug"></i> <?php esc_html_e( 'Eigene Nostr-Erweiterung verknüpfen', 'sk-core' ); ?>
                        </a>
                        <button type="button" class="sk-btn sk-btn-theme" id="sk-nostr-create-identity">
                            <i class="fas fa-key"></i> <?php esc_html_e( 'Schlüssel von uns erstellen lassen', 'sk-core' ); ?>
                        </button>
                    </p>
                    <div id="sk-nostr-identity-result" style="display:none;">
                        <p>
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php esc_html_e( 'Schreib diesen Schlüssel auf. Er ist deine Identität, wer ihn hat, ist du. Du findest ihn später unter „Nostr/LN Link" wieder.', 'sk-core' ); ?>
                        </p>
                        <code id="sk-nostr-nsec" style="display:block;word-break:break-all;"></code>
                        <button type="button" class="sk-btn sk-btn-default" id="sk-nostr-copy-nsec" style="margin-top:8px;">
                            <i class="fas fa-copy"></i> <?php esc_html_e( 'Kopieren', 'sk-core' ); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div><!-- .sk-other-options -->
