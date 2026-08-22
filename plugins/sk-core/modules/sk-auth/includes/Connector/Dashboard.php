<?php
/**
 * SK Auth Dashboard Integration
 *
 * Adds authentication linking functionality to the SK vendor dashboard.
 */
class SK_Auth_Dashboard extends \SK\Core\Dashboard\DashboardModule {

    /**
     * @var UAC_Account_Linker
     */
    private $account_linker;

    /**
     * Constructor.
     *
     * @param UAC_Account_Linker $account_linker The account linker instance
     */
    public function __construct($account_linker) {
        $this->account_linker = $account_linker;
        parent::__construct();
    }

    public function config(): ?array {
        return [
            'slug'       => 'auth-connector',
            'title'      => 'Nostr/LN Link',
            'icon'       => '<i class="fas fa-key"></i>',
            'pos'        => 190,
            'permission' => 'read',
            'template'   => [ $this, 'render_dashboard' ],
        ];
    }

    public function render_dashboard( $query_vars ): void {
        require_once SK_AUTH_TEMPLATES . '/dashboard-auth-connector.php';
    }

    /**
     * Render the authentication linking page.
     */
    public function render_auth_page() {
        $user_id = get_current_user_id();

        wp_enqueue_script(
            'sk-auth-connector',
            SK_AUTH_ASSETS . '/js/auth-connector.js',
            array( 'jquery' ),
            SK_AUTH_VERSION,
            true
        );
        wp_localize_script(
            'sk-auth-connector',
            'skAuthConnector',
            array(
                // the nsec is deliberately NOT shipped here — it is fetched over
                // admin-ajax when the user asks to see it, see sk_get_nostr_nsec
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'uob_ajax_nonce' ),
            )
        );
        $linked_methods = $this->account_linker->get_linked_methods($user_id);

        // Both LNURL + Nostr login are bundled in the sk_auth module — if this
        // Connector page renders, sk_auth is active, so both flows are available.
        $lnurl_active = sk_module_active( 'sk_auth' );
        $nostr_active = sk_module_active( 'sk_auth' );

        // Get original auth methods (if this account was created by one of the plugins)
        $original_lnurl = get_user_meta($user_id, 'lnurl-auth-bjm-id', true);
        $original_nostr = get_user_meta($user_id, 'nostr_public_key', true);

        // Check if user needs to be asked about Nostr profile sync
        $profile_sync = new UAC_Nostr_Profile_Sync();
        $show_sync_choice = $original_nostr && $profile_sync->needs_sync_choice($user_id);

        ?>
        <div class="sk-followers-page-header">
            <h2><i class="fas fa-link"></i> Nostr / LN Linking</h2>
        </div>

        <div class="uac-content">
                        <?php if ($show_sync_choice): ?>
                            <div class="sk-alert sk-alert-warning uac-sync-choice-notice" id="uac-sync-choice-notice">
                                <h3 style="margin-top: 0;">🎨 Nostr-Profil jetzt synchronisieren?</h3>
                                <p>
                                    Möchtest Du Dein Nostr-Profil (Name, Bild, Banner und Biografie) <strong>einmalig</strong> mit Deinem SK-Shop synchronisieren?
                                </p>
                                <p style="margin-bottom: 15px;">
                                    <small>Dies ist ein einmaliger Vorgang. Spätere Änderungen an Deinem Nostr-Profil werden nicht automatisch übernommen.</small>
                                </p>
                                <button type="button" class="button button-primary" id="uac-enable-sync">
                                    Ja, jetzt synchronisieren
                                </button>
                                <button type="button" class="button" id="uac-disable-sync">
                                    Nein, überspringen
                                </button>
                            </div>
                        <?php endif; ?>

                        <div class="sk-alert sk-alert-info">
                            <p>
                                Verknüpfe mehrere Authentifizierungsmethoden mit Deinem Konto. Nach der Verknüpfung kannst Du Dich mit jeder verbundenen Methode anmelden. Falls die andere Methode bereits ein eigenes Konto hat, werden die Konten automatisch zusammengeführt — das Konto mit Deinen Shop-Daten bleibt erhalten.
                            </p>
                        </div>

                        <?php if (!$lnurl_active && !$nostr_active): ?>
                            <div class="sk-alert sk-alert-warning">
                                <p>
                                    Es sind derzeit keine Authentifizierungs-Plugins aktiv. Bitte aktiviere LNURL-Auth oder Nostr Login, um diese Funktion zu nutzen.
                                </p>
                            </div>
                        <?php endif; ?>

                        <div class="uac-auth-methods">
                            <!-- Nostr Login -->
                            <?php if ($nostr_active): ?>
                                <div class="uac-auth-method-card">
                                    <div class="uac-auth-method-header">
                                        <h3>
                                            <span class="uac-icon">🔑</span>
                                            Nostr-Anmeldung
                                        </h3>
                                        <?php if ($original_nostr || $linked_methods['nostr']): ?>
                                            <span class="uac-badge uac-badge-success">
                                                Verknüpft
                                            </span>
                                        <?php else: ?>
                                            <span class="uac-badge uac-badge-secondary">
                                                Nicht verknüpft
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="uac-auth-method-body">
                                        <p class="uac-method-description">
                                            Verknüpfe Deine Nostr-Identität mit diesem Konto über Deine Browser-Erweiterung oder Nostr-Schlüssel.
                                        </p>

                                        <?php if ($original_nostr): ?>
                                            <div class="uac-linked-info">
                                                <strong>Öffentlicher Schlüssel:</strong>
                                                <code class="uac-key-display"><?php echo esc_html(substr($original_nostr, 0, 16) . '...' . substr($original_nostr, -8)); ?></code>
                                                <span class="uac-primary-badge">
                                                    (Primär)
                                                </span>
                                            </div>
                                        <?php elseif ($linked_methods['nostr']): ?>
                                            <div class="uac-linked-info">
                                                <strong>Öffentlicher Schlüssel:</strong>
                                                <code class="uac-key-display"><?php echo esc_html(substr($linked_methods['nostr'], 0, 16) . '...' . substr($linked_methods['nostr'], -8)); ?></code>
                                                <button type="button" class="button uac-unlink-btn" data-auth-type="nostr">
                                                    Trennen
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" class="button button-primary uac-link-nostr-btn" id="uac-link-nostr">
                                                Nostr-Konto verknüpfen
                                            </button>
                                            <div class="uac-status-message" id="uac-nostr-status"></div>

                                            <?php
                                            // Ohne diesen Weg gaebe es im Frontend keinen: der Hinweisbanner
                                            // auf dem Dashboard war die einzige Stelle, die eine Identitaet
                                            // anlegen konnte, und er laesst sich dauerhaft wegklicken.
                                            \SK\Core\Dashboard\Modules\UserOnboarding::enqueue_nostr_script();
                                            ?>
                                            <p class="uac-method-description" style="margin-top:16px;">
                                                <strong>Noch kein Nostr-Account?</strong>
                                                Wir legen dir einen an. Den privaten Schlüssel kannst du dir
                                                anschliessend hier anzeigen lassen und überall im Nostr-Netz
                                                verwenden — er gehört dir, nicht der Plattform.
                                            </p>
                                            <button type="button" class="button" id="sk-nostr-create">
                                                Nostr-Identität erstellen
                                            </button>
                                            <div class="uac-status-message" id="sk-nostr-create-status"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- LNURL-Auth -->
                            <?php if ($lnurl_active): ?>
                                <div class="uac-auth-method-card">
                                    <div class="uac-auth-method-header">
                                        <h3>
                                            <span class="uac-icon">⚡</span>
                                            Lightning-Anmeldung (LNURL-Auth)
                                        </h3>
                                        <?php if ($original_lnurl || $linked_methods['lnurl']): ?>
                                            <span class="uac-badge uac-badge-success">
                                                Verknüpft
                                            </span>
                                        <?php else: ?>
                                            <span class="uac-badge uac-badge-secondary">
                                                Nicht verknüpft
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="uac-auth-method-body">
                                        <p class="uac-method-description">
                                            Verknüpfe Deine Lightning-Wallet mit diesem Konto, indem Du einen QR-Code scannst.
                                        </p>

                                        <?php if ($original_lnurl): ?>
                                            <div class="uac-linked-info">
                                                <strong>Node-Schlüssel:</strong>
                                                <code class="uac-key-display"><?php echo esc_html(substr($original_lnurl, 0, 16) . '...' . substr($original_lnurl, -8)); ?></code>
                                                <span class="uac-primary-badge">
                                                    (Primär)
                                                </span>
                                            </div>
                                        <?php elseif ($linked_methods['lnurl']): ?>
                                            <div class="uac-linked-info">
                                                <strong>Node-Schlüssel:</strong>
                                                <code class="uac-key-display"><?php echo esc_html(substr($linked_methods['lnurl'], 0, 16) . '...' . substr($linked_methods['lnurl'], -8)); ?></code>
                                                <button type="button" class="button uac-unlink-btn" data-auth-type="lnurl">
                                                    Trennen
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <button type="button" class="button button-primary uac-link-lnurl-btn" id="uac-link-lnurl">
                                                Lightning-Wallet verknüpfen
                                            </button>
                                            <div class="uac-lnurl-qr-container" id="uac-lnurl-qr" style="display: none;">
                                                <div class="uac-qr-code"></div>
                                                <p class="uac-qr-instructions">
                                                    Scanne diesen QR-Code mit Deiner Lightning-Wallet, um sie mit Deinem Konto zu verknüpfen.
                                                </p>
                                                <div class="uac-qr-timer"></div>
                                            </div>
                                            <div class="uac-status-message" id="uac-lnurl-status"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Primary Authentication Method Info -->
                            <?php
                            // Only show primary auth info if we can confidently detect it
                            $primary = $linked_methods['primary'];
                            $show_primary_info = ($primary === 'nostr' || $primary === 'lnurl');

                            if ($show_primary_info):
                            ?>
                            <div class="uac-auth-method-card uac-primary-info">
                                <div class="uac-auth-method-header">
                                    <h3>
                                        <span class="uac-icon">ℹ️</span>
                                        Primäre Authentifizierungsmethode
                                    </h3>
                                </div>
                                <div class="uac-auth-method-body">
                                    <p>
                                        <?php
                                        $primary_label = $primary === 'nostr' ? 'Nostr-Anmeldung' : 'Lightning-Anmeldung';
                                        echo 'Dieses Konto wurde ursprünglich erstellt mit: <strong>' . esc_html($primary_label) . '</strong>';
                                        ?>
                                    </p>
                                    <p class="uac-note">
                                        Alle Profildaten und Einstellungen werden von diesem primären Konto übernommen.
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Manual Sync Option -->
                            <?php if ($original_nostr && sk_is_user_seller($user_id)): ?>
                            <div class="uac-auth-method-card">
                                <div class="uac-auth-method-header">
                                    <h3>
                                        <span class="uac-icon">🔄</span>
                                        Nostr-Profil synchronisieren
                                    </h3>
                                </div>
                                <div class="uac-auth-method-body">
                                    <p class="uac-method-description">
                                        Synchronisiere Dein Nostr-Profil (Name, Bild, Banner, Biografie) manuell mit Deinem SK-Shop.
                                    </p>
                                    <button type="button" class="button button-secondary" id="uac-manual-sync-btn">
                                        Jetzt synchronisieren
                                    </button>
                                    <div class="uac-status-message" id="uac-manual-sync-status" style="margin-top: 10px;"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <!-- Nostr Identity / Key Export -->
                            <?php if ( sk_module_active( 'sk_auth' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $user_id ) ) : ?>
                            <div class="uac-auth-method-card">
                                <div class="uac-auth-method-header">
                                    <h3>
                                        <span class="uac-icon">🔑</span>
                                        Nostr-Schlüssel
                                    </h3>
                                    <span class="uac-badge uac-badge-success">Aktiv</span>
                                </div>
                                <div class="uac-auth-method-body">
                                    <div class="uac-linked-info" style="margin-bottom:12px;">
                                        <strong>npub:</strong>
                                        <code class="uac-key-display"><?php echo esc_html( \SK\Modules\Auth\NostrIdentity::get_npub( $user_id ) ); ?></code>
                                    </div>
                                    <div class="uac-linked-info" id="uac-nsec-container" style="margin-bottom:12px;">
                                        <strong>nsec:</strong>
                                        <code class="uac-key-display" id="uac-nsec-value" style="filter:blur(5px);user-select:none;">••••••••••••••••••••••••</code>
                                        <button type="button" class="button button-small" id="uac-reveal-nsec" style="margin-left:8px;">
                                            Anzeigen
                                        </button>
                                        <button type="button" class="button button-small" id="uac-copy-nsec" style="margin-left:4px;display:none;">
                                            Kopieren
                                        </button>
                                    </div>
                                    <p class="uac-method-description" style="color:#dc3545;font-size:12px;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Teile deinen Private Key (nsec) <strong>niemals</strong> mit anderen! Wer deinen nsec hat, kontrolliert deine Nostr-Identität.
                                    </p>
                                    <div class="uac-method-actions" style="margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,0.08);">
                                        <button type="button" class="button" id="uac-delete-nostr-identity" style="color:#dc3545;border-color:#dc3545;">
                                            Identität löschen
                                        </button>
                                        <p class="uac-method-description" style="font-size:12px;margin-top:6px;">
                                            Löscht die auf SK gespeicherte Nostr-Identität. Danach kannst du deinen eigenen Nostr-Account über die Browser-Extension verknüpfen.
                                            <strong>Exportiere vorher den nsec</strong>, falls du weiter darauf zugreifen willst.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for linking Nostr account.
     */
    public function ajax_link_nostr() {
        check_ajax_referer('uac_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Du musst angemeldet sein.'));
        }

        $user_id = get_current_user_id();
        $authtoken = isset($_POST['authtoken']) ? sanitize_text_field(wp_unslash($_POST['authtoken'])) : '';
        $sync_profile = isset($_POST['sync_profile']) ? filter_var($_POST['sync_profile'], FILTER_VALIDATE_BOOLEAN) : false;

        if (empty($authtoken)) {
            wp_send_json_error(array('message' => 'Authentifizierungstoken erforderlich.'));
        }

        // Get Nostr integration instance
        $nostr_integration = new UAC_Nostr_Login_Integration($this->account_linker);
        $result = $nostr_integration->verify_nostr_identity($authtoken);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $pubkey = $result['pubkey'];
        $force  = isset($_POST['force']) ? filter_var($_POST['force'], FILTER_VALIDATE_BOOLEAN) : false;

        // Check if this Nostr key already belongs to a different account
        $standalone_id = $this->account_linker->get_standalone_user_by_nostr($pubkey);
        if ($standalone_id && $standalone_id !== $user_id) {
            if ($force) {
                // Merge accounts
                $merger = new UAC_Account_Merger($this->account_linker);
                $merge_result = $merger->merge($user_id, $standalone_id);
                if (is_wp_error($merge_result)) {
                    wp_send_json_error(array('message' => $merge_result->get_error_message()));
                }
                $survivor_id = $merge_result['survivor_id'];
                $this->account_linker->link_nostr($survivor_id, $pubkey);
                wp_send_json_success(array(
                    'message' => 'Konten zusammengeführt! Das leere Konto wurde gelöscht.',
                    'merged'  => true,
                ));
            }

            // Return conflict info with merge preview
            $merger = new UAC_Account_Merger($this->account_linker);
            $preview = $merger->preview($user_id, $standalone_id);
            $msg = sprintf(
                'Das Konto «%s» besitzt diese Nostr-Identität.' . "\n\n" .
                'Hauptkonto nach Zusammenführung: «%s» (%d Produkte)' . "\n" .
                'Wird gelöscht: «%s» (%d Produkte werden übertragen, Shop-Einstellungen gehen verloren)' . "\n\n" .
                'Fortfahren?',
                esc_html($preview['absorbed_name']),
                esc_html($preview['survivor_name']),
                $preview['survivor_products'],
                esc_html($preview['absorbed_name']),
                $preview['absorbed_products']
            );
            wp_send_json_error(array(
                'code'    => 'existing_standalone',
                'message' => $msg,
            ));
        }

        // Link the Nostr account
        $link_result = $this->account_linker->link_nostr($user_id, $pubkey);

        if (is_wp_error($link_result)) {
            wp_send_json_error(array('message' => $link_result->get_error_message()));
        }

        $message = 'Nostr-Konto erfolgreich verknüpft!';

        // If user wants to sync profile and is an SK vendor, do it now
        if ($sync_profile && class_exists('SK_Core') && function_exists('sk_is_user_seller') && sk_is_user_seller($user_id)) {
            $profile_sync = new UAC_Nostr_Profile_Sync();
            $synced = $profile_sync->manual_sync($user_id);

            if ($synced) {
                $message .= ' Profil erfolgreich synchronisiert!';
            }
        }

        wp_send_json_success(array(
            'message' => $message,
            'pubkey' => substr($pubkey, 0, 16) . '...' . substr($pubkey, -8)
        ));
    }

    /**
     * AJAX handler for initiating LNURL linking.
     */
    public function ajax_link_lnurl() {
        check_ajax_referer('uac_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Du musst angemeldet sein.'));
        }

        if (!function_exists('lnurl_auth')) {
            wp_send_json_error(array('message' => 'LNURL-Auth-Plugin ist nicht aktiv.'));
        }

        // Create a new LNURL auth session
        $login = lnurl_auth()->Login;
        $response = $login->create_lnurl();

        if ($response->status !== 'Success') {
            wp_send_json_error(array('message' => $response->message));
        }

        // Store the k1 in a temporary user meta to verify later
        set_transient('uac_linking_session_' . get_current_user_id(), $response->k1, 300);

        wp_send_json_success(array(
            'qrcode' => $response->html->qrcode,
            'k1' => $response->k1,
            'lnurl' => $response->lnurl
        ));
    }

    /**
     * AJAX handler for verifying LNURL linking.
     */
    public function ajax_verify_lnurl_link() {
        check_ajax_referer('uac_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Du musst angemeldet sein.'));
        }

        $user_id = get_current_user_id();
        $k1 = isset($_POST['k1']) ? sanitize_text_field(wp_unslash($_POST['k1'])) : '';

        if (empty($k1)) {
            wp_send_json_error(array('message' => 'Ungültige Sitzung.'));
        }

        // Verify this k1 belongs to this user's linking session
        $stored_k1 = get_transient('uac_linking_session_' . $user_id);
        if ($stored_k1 !== $k1) {
            wp_send_json_error(array('message' => 'Ungültige Sitzung.'));
        }

        $force    = isset($_POST['force']) ? filter_var($_POST['force'], FILTER_VALIDATE_BOOLEAN) : false;
        $node_key = null;

        // On force retry, the LNURL session may already be consumed.
        // Try the pending transient first.
        if ($force) {
            $pending_node_key = get_transient('uac_pending_lnurl_node_key_' . $user_id);
            if ($pending_node_key) {
                $standalone_id = $this->account_linker->get_standalone_user_by_lnurl($pending_node_key);
                if ($standalone_id && $standalone_id !== $user_id) {
                    $merger = new UAC_Account_Merger($this->account_linker);
                    $merge_result = $merger->merge($user_id, $standalone_id);
                    if (is_wp_error($merge_result)) {
                        wp_send_json_error(array('message' => $merge_result->get_error_message()));
                    }
                    $survivor_id = $merge_result['survivor_id'];
                    $this->account_linker->link_lnurl($survivor_id, $pending_node_key);
                    delete_transient('uac_linking_session_' . $user_id);
                    delete_transient('uac_pending_lnurl_node_key_' . $user_id);
                    wp_send_json_success(array(
                        'message' => 'Konten zusammengeführt! Das leere Konto wurde gelöscht.',
                        'merged'  => true,
                    ));
                }
                // Standalone gone or same user — just link normally
                $node_key = $pending_node_key;
                delete_transient('uac_pending_lnurl_node_key_' . $user_id);
            }
        }

        // Normal flow: verify LNURL session
        if (empty($node_key)) {
            $lnurl_integration = new UAC_LNURL_Auth_Integration($this->account_linker);
            $result = $lnurl_integration->verify_linking_session($k1);

            if (!$result) {
                wp_send_json_error(array('message' => 'Warten auf Authentifizierung...', 'status' => 'waiting'));
            }

            $node_key = $result['node_key'];
        }

        // Check if this LN key already belongs to a different account
        $standalone_id = $this->account_linker->get_standalone_user_by_lnurl($node_key);
        if ($standalone_id && $standalone_id !== $user_id) {
            // Store node_key so the forced re-submit can skip re-scanning the QR code.
            set_transient('uac_pending_lnurl_node_key_' . $user_id, $node_key, 300);

            $merger_preview = new UAC_Account_Merger($this->account_linker);
            $preview = $merger_preview->preview($user_id, $standalone_id);
            $msg = sprintf(
                'Das Konto «%s» besitzt diese Lightning-Identität.' . "\n\n" .
                'Hauptkonto nach Zusammenführung: «%s» (%d Produkte)' . "\n" .
                'Wird gelöscht: «%s» (%d Produkte werden übertragen, Shop-Einstellungen gehen verloren)' . "\n\n" .
                'Fortfahren?',
                esc_html($preview['absorbed_name']),
                esc_html($preview['survivor_name']),
                $preview['survivor_products'],
                esc_html($preview['absorbed_name']),
                $preview['absorbed_products']
            );
            wp_send_json_error(array(
                'code'    => 'existing_standalone',
                'message' => $msg,
            ));
        }

        // Link the LNURL account
        $link_result = $this->account_linker->link_lnurl($user_id, $node_key);

        if (is_wp_error($link_result)) {
            wp_send_json_error(array('message' => $link_result->get_error_message()));
        }

        // Clean up
        delete_transient('uac_linking_session_' . $user_id);
        delete_transient('uac_pending_lnurl_node_key_' . $user_id);

        wp_send_json_success(array(
            'message' => 'Lightning-Wallet erfolgreich verknüpft!',
            'node_key' => substr($node_key, 0, 16) . '...' . substr($node_key, -8)
        ));
    }

    /**
     * AJAX handler for unlinking authentication methods.
     */
    public function ajax_unlink_auth() {
        check_ajax_referer('uac_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Du musst angemeldet sein.'));
        }

        $user_id = get_current_user_id();
        $auth_type = isset($_POST['auth_type']) ? sanitize_text_field(wp_unslash($_POST['auth_type'])) : '';

        if ($auth_type === 'nostr') {
            $this->account_linker->unlink_nostr($user_id);
            wp_send_json_success(array('message' => 'Nostr-Konto erfolgreich getrennt.'));
        } elseif ($auth_type === 'lnurl') {
            $this->account_linker->unlink_lnurl($user_id);
            wp_send_json_success(array('message' => 'Lightning-Wallet erfolgreich getrennt.'));
        } else {
            wp_send_json_error(array('message' => 'Ungültiger Authentifizierungstyp.'));
        }
    }

    /**
     * AJAX handler for setting Nostr sync preference.
     */
    public function ajax_set_sync_preference() {
        check_ajax_referer('uac_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Du musst angemeldet sein.'));
        }

        $user_id = get_current_user_id();
        $enabled = isset($_POST['enabled']) ? filter_var($_POST['enabled'], FILTER_VALIDATE_BOOLEAN) : false;

        $profile_sync = new UAC_Nostr_Profile_Sync();
        $profile_sync->set_sync_preference($user_id, $enabled);

        $message = $enabled ?
            'Dein Nostr-Profil wurde erfolgreich synchronisiert!' :
            'Synchronisierung übersprungen.';

        wp_send_json_success(array('message' => $message));
    }

    /**
     * AJAX handler for manual Nostr profile sync.
     */
    public function ajax_manual_sync() {
        check_ajax_referer('uac_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Du musst angemeldet sein.'));
        }

        $user_id = get_current_user_id();

        // Check if user has Nostr account
        $nostr_pubkey = get_user_meta($user_id, 'nostr_public_key', true);
        if (empty($nostr_pubkey)) {
            wp_send_json_error(array('message' => 'Kein Nostr-Konto verknüpft.'));
        }

        // Check if user is a vendor
        if (!sk_is_user_seller($user_id)) {
            wp_send_json_error(array('message' => 'Du musst ein Anbieter sein.'));
        }

        $profile_sync = new UAC_Nostr_Profile_Sync();
        $synced = $profile_sync->manual_sync($user_id);

        if ($synced) {
            wp_send_json_success(array('message' => 'Dein Nostr-Profil wurde erfolgreich synchronisiert!'));
        } else {
            wp_send_json_error(array('message' => 'Synchronisierung fehlgeschlagen. Bitte versuche es später erneut.'));
        }
    }
}
