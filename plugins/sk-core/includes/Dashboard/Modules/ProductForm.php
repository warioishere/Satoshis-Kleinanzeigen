<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Product form customizations: brand field removal, form reorder, map hint.
 * Ported from kadence-child/functions.php.
 */
class ProductForm {

    public function __construct() {
        add_action( 'wp_footer', [ $this, 'output_brand_removal_script' ] );
        add_action( 'wp_footer', [ $this, 'output_form_reorder_script' ] );
        add_action( 'wp_footer', [ $this, 'output_map_hint_script' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_product_guard' ], 20 );
        add_filter( 'wp_insert_post_data', [ $this, 'enforce_draft_if_incomplete' ], 10, 2 );

        // P2P Versandkosten-Feld
        add_action( 'sk_process_product_meta', [ $this, 'save_shipping_note' ] );
        add_action( 'woocommerce_single_product_summary', [ $this, 'output_shipping_display' ], 11 );

        // Sats Converter
        add_action( 'admin_menu', [ $this, 'sats_converter_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'sats_converter_register_settings' ] );
        add_action( 'wp_footer', [ $this, 'output_sats_converter_script' ] );

        // SEO Autofill (Yoast Focus Keyword)
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_seo_autofill' ] );
        add_action( 'admin_menu', [ $this, 'seo_autofill_admin_menu' ] );
    }

    /**
     * Server-side guard: force a vendor-submitted product to 'draft'
     * if price or featured image is missing.
     * Never runs for wp-admin saves (is_admin check).
     */
    public function enforce_draft_if_incomplete( array $data, array $postarr ): array {
        // Only frontend vendor form submissions
        if ( is_admin() ) {
            return $data;
        }

        // Only products headed for publish
        if ( $data['post_type'] !== 'product' || $data['post_status'] !== 'publish' ) {
            return $data;
        }

        // Verify this is a vendor product-edit form submission
        if ( empty( $_POST['sk_edit_product_nonce'] ) ||
             ! wp_verify_nonce( sanitize_key( $_POST['sk_edit_product_nonce'] ), 'sk_edit_product' ) ) {
            return $data;
        }

        $price    = isset( $_POST['_regular_price'] ) ? floatval( $_POST['_regular_price'] ) : 0;
        $image_id = isset( $_POST['feat_image_id'] )  ? intval( $_POST['feat_image_id'] )    : 0;

        // Also accept an already-saved thumbnail (editing an existing product)
        if ( $image_id <= 0 && ! empty( $postarr['ID'] ) ) {
            $image_id = (int) get_post_thumbnail_id( $postarr['ID'] );
        }

        if ( $price <= 0 || $image_id <= 0 ) {
            $data['post_status'] = 'draft';
        }

        return $data;
    }

    public function enqueue_product_guard(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        wp_enqueue_script(
            'sk-product-guard',
            SK_CORE_ASSETS . '/js/sk-product-guard.js',
            [],
            SK_CORE_VERSION,
            true
        );
    }

    public function output_brand_removal_script(): void {
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        ?>
        <script>
        (function () {
          var observers = [];

          function disconnectObserver(observer){
            observer.disconnect();
            for (var i = observers.length - 1; i >= 0; i--) {
              if (observers[i] === observer) { observers.splice(i, 1); }
            }
          }

          function scheduleCleanup(observer, timeout){
            var stopAt = performance.now() + timeout;
            (function loop(){
              if (observers.indexOf(observer) === -1) return;
              if (performance.now() > stopAt) { disconnectObserver(observer); return; }
              requestAnimationFrame(loop);
            })();
          }

          function findBrandSelect(scope){
            if (!scope) return null;
            if (scope.nodeType === 1 && scope.matches && scope.matches('#product_brand, #product_brand_edit')) return scope;
            if (scope.querySelector) return scope.querySelector('#product_brand, #product_brand_edit');
            return null;
          }

          function removeBrandField(scope){
            var root = scope || document;
            var removed = false;
            var select = findBrandSelect(root);
            while (select) {
              var group = select.closest ? select.closest('.sk-form-group') : null;
              var select2 = select.nextElementSibling;
              if (select2 && select2.classList && select2.classList.contains('select2')) select2.remove();
              if (group) { group.remove(); } else { select.style.display = 'none'; }
              if (select.id) {
                var label = root.querySelector ? root.querySelector('label[for="'+select.id+'"]') : null;
                if (!label) label = document.querySelector('label[for="'+select.id+'"]');
                if (label) label.remove();
              }
              removed = true;
              select = findBrandSelect(root);
            }
            return removed;
          }

          function observeTarget(selector){
            var target = document.querySelector(selector);
            if (!target) return;
            removeBrandField(target);
            var observer = new MutationObserver(function(mutations){
              var updated = false;
              for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes || [];
                for (var j = 0; j < nodes.length; j++) {
                  var node = nodes[j];
                  if (node.nodeType !== 1) continue;
                  updated = removeBrandField(node) || updated;
                }
              }
              if (updated) { disconnectObserver(observer); removeBrandField(document); }
            });
            observers.push(observer);
            observer.observe(target, { childList: true, subtree: true });
            scheduleCleanup(observer, 1500);
          }

          function init(){
            while (observers.length) { disconnectObserver(observers.pop()); }
            removeBrandField(document);
            observeTarget('#sk-product-edit-form');
            observeTarget('.product-edit-new-container');
          }

          document.addEventListener('DOMContentLoaded', init);
        })();
        </script>
        <?php
    }

    public function output_form_reorder_script(): void {
        if ( ! is_user_logged_in() ) {
            return;
        }
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) {
            return;
        }
        ?>
        <script>
        document.addEventListener("DOMContentLoaded", function () {
            var converterBox = document.querySelector('[data-togglehandler="sats_converter_box"]')?.closest('.sk-edit-row');
            var shippingBox  = document.querySelector('[data-togglehandler="p2p_shipping_box"]')?.closest('.sk-edit-row');
            var excerptLabel = document.querySelector('label[for="post_excerpt"]');
            if (excerptLabel) {
                var target = excerptLabel.closest('.sk-product-short-description');
                if (target && target.parentElement) {
                    if (shippingBox)  target.parentElement.insertBefore(shippingBox,  target);
                    if (converterBox) target.parentElement.insertBefore(converterBox, target);
                }
            }
        });
        </script>
        <?php
    }

    public function output_map_hint_script(): void {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var label = document.querySelector('label[for="setting_map"]');
            if (!label) return;
            var targetDiv = label.nextElementSibling;
            if (!targetDiv) return;
            var hint = document.createElement('p');
            hint.innerText = 'Klicke auf die Lupe oben auf der Karte und gib deine Adresse ein, um zu zeigen, wo du dich befindest. Das hilft anderen, eine mögliche Abholung besser einschätzen zu können. Du kannst natürlich auch nur deinen groben Standort angeben, wie z.B. nur Köln.';
            hint.style.marginTop = '10px';
            hint.style.fontSize  = '14px';
            hint.style.color     = '#ccc';
            targetDiv.appendChild(hint);
        });
        </script>
        <?php
    }

    /* ---- P2P Versandkosten ---- */

    public function save_shipping_note( int $post_id ): void {
        if ( isset( $_POST['p2p_shipping_note'] ) ) {
            update_post_meta( $post_id, '_p2p_shipping_note', sanitize_text_field( $_POST['p2p_shipping_note'] ) );
        }
    }

    public function output_shipping_field( $post, $post_id ): void {
        $value = get_post_meta( $post_id, '_p2p_shipping_note', true );
        ?>
        <div class="sk-edit-row sk-other-options sk-clearfix" data-togglehandler="p2p_shipping_box">
            <div class="sk-section-heading">
                <h2><i class="fas fa-truck"></i> P2P Versandkosten</h2>
                <p>Versandkosten in Sats bei p2p Trades</p>
                <a href="#" class="sk-section-toggle"><i class="fas fa-sort-down"></i></a>
            </div>
            <div class="sk-section-content">
                <label for="p2p_shipping_note">Text oder Betrag:</label>
                <input type="text" id="p2p_shipping_note" name="p2p_shipping_note" class="sk-form-control"
                       placeholder="z. B. 2000 Sats oder Inkl. Versand" value="<?php echo esc_attr( $value ); ?>" />
                <small style="color:#666">Hier kannst du angeben, ob Versandkosten enthalten sind oder wie hoch sie sind (z. B. 3000 Sats).</small>
            </div>
        </div>
        <?php
    }

    public function output_shipping_display(): void {
        global $post;
        if ( ! $post ) return;
        $value = get_post_meta( $post->ID, '_p2p_shipping_note', true );
        if ( ! empty( $value ) ) {
            echo '<div class="p2p-shipping-note" style="margin-top: 15px; font-weight: bold; color: #444;">Versand: ' . esc_html( $value ) . '</div>';
        }
    }

    /* ---- Sats Converter ---- */

    public function sats_converter_admin_menu(): void {
        add_options_page(
            'Sats Converter Einstellungen',
            'Sats Converter',
            'manage_options',
            'sats-converter',
            [ $this, 'sats_converter_admin_page' ]
        );
    }

    public function sats_converter_admin_page(): void {
        ?>
        <div class="wrap">
            <h1>Sats Converter Einstellungen</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'sats_converter_settings' );
                do_settings_sections( 'sats_converter_settings' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Converter anzeigen?</th>
                        <td>
                            <label>
                                <input type="checkbox" name="sats_converter_enabled" value="1"
                                       <?php checked( get_option( 'sats_converter_enabled' ), 1 ); ?> />
                                Aktiv
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function sats_converter_register_settings(): void {
        register_setting( 'sats_converter_settings', 'sats_converter_enabled' );
    }

    public function output_sats_converter_box(): void {
        if ( ! is_user_logged_in() || ! get_option( 'sats_converter_enabled' ) ) return;
        ?>
        <div id="sats-converter-box" class="sk-edit-row sk-other-options sk-clearfix">
            <div class="sk-section-heading" data-togglehandler="sats_converter_box">
                <h2><i class="fas fa-coins"></i> Sats Rechner</h2>
                <p>Hilft dir, Preise in Sats umzurechnen</p>
                <a href="#" class="sk-section-toggle"><i class="fas fa-sort-down"></i></a>
            </div>
            <div class="sk-section-content">
                <label for="fiat_to_sats">Betrag in Fiat:</label>
                <input type="number" id="fiat_to_sats" class="sk-form-control" placeholder="z. B. 10" step="any" />
                <select id="fiat_currency" class="sk-form-control" style="margin-top: 5px;">
                    <option value="eur">EUR</option>
                    <option value="chf">CHF</option>
                    <option value="gbp">GBP</option>
                    <option value="usd">USD</option>
                </select>
                <button type="button" id="convert_to_sats" class="button button-primary" style="margin-top: 10px;">
                    In Sats umrechnen
                </button>
                <p id="sats_result" style="margin-top: 8px;"></p>
            </div>
        </div>
        <?php
    }

    public function output_sats_converter_script(): void {
        if ( ! is_user_logged_in() || ! get_option( 'sats_converter_enabled' ) ) return;
        if ( ! function_exists( 'sk_is_seller_dashboard' ) || ! sk_is_seller_dashboard() ) return;
        ?>
        <script>
        (function () {
          function moveConverterBox(root) {
            var box = document.getElementById('sats-converter-box');
            if (!box) return;
            var targetRow =
              (root || document).querySelector('.sk-edit-row[data-togglehandler="p2p_shipping_box"]') ||
              ((root || document).querySelector('.sk-section-heading[data-togglehandler="p2p_shipping_box"]') || {})
                .closest && (root || document).querySelector('.sk-section-heading[data-togglehandler="p2p_shipping_box"]').closest('.sk-edit-row');
            if (!targetRow || !targetRow.parentNode) return;
            if (box.nextElementSibling !== targetRow) {
              targetRow.parentNode.insertBefore(box, targetRow);
            }
          }

          async function convertFiatToSats() {
            var fiatInput = document.getElementById('fiat_to_sats');
            var currSel   = document.getElementById('fiat_currency');
            var resultBox = document.getElementById('sats_result');
            if (!fiatInput || !currSel || !resultBox) return;

            var fiat = parseFloat(fiatInput.value);
            var currency = (currSel.value || 'eur').toUpperCase();
            if (!fiat || fiat <= 0) {
              resultBox.textContent = 'Bitte einen Betrag eingeben.';
              return;
            }

            try {
              var pair = 'XBT' + currency;
              var res  = await fetch('https://api.kraken.com/0/public/Ticker?pair=' + pair);
              var data = await res.json();
              if (!data.result || Object.keys(data.result).length === 0) {
                throw new Error('Kein Kurs gefunden');
              }
              var key   = Object.keys(data.result)[0];
              var price = parseFloat(data.result[key].c[0]);
              if (!price || price <= 0) throw new Error('Ungültiger Kurs');

              var sats = Math.round((fiat / price) * 100000000);
              resultBox.textContent = '≈ ' + sats.toLocaleString() + ' Sats';

              var satsInput = document.querySelector('input[name="regular_price"]');
              if (satsInput) satsInput.value = sats;
            } catch (e) {
              resultBox.textContent = 'Fehler beim Abrufen des Wechselkurses.';
            }
          }

          document.addEventListener('DOMContentLoaded', function () {
            moveConverterBox(document);
            var btn = document.getElementById('convert_to_sats');
            if (btn) btn.addEventListener('click', convertFiatToSats);
            var watch = document.querySelector('.sk-dashboard-wrap') || document;
            new MutationObserver(function () { moveConverterBox(document); })
              .observe(watch, { childList: true, subtree: true });
          });
        })();
        </script>
        <?php
    }

    /* ---- SEO Autofill (Yoast Focus Keyword) ---- */

    public function enqueue_seo_autofill(): void {
        if ( is_admin() ) return;
        $js_path = plugin_dir_path( \SK_CORE_FILE ) . 'assets/js/autofill-focuskw.js';
        if ( ! file_exists( $js_path ) ) return;
        wp_enqueue_script(
            'sk-yoast-autofill',
            \SK_CORE_ASSETS . '/js/autofill-focuskw.js',
            [],
            filemtime( $js_path ),
            true
        );
    }

    public function seo_autofill_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'Focus Keyword Audit', 'sk' ),
            __( 'Focus Keyword Audit', 'sk' ),
            'edit_products',
            'sk-seo-focuskw-audit',
            [ $this, 'seo_autofill_audit_page' ]
        );
    }

    public function seo_autofill_audit_page(): void {
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'sk' ) );
        }

        $per_page = max( 1, absint( apply_filters( 'sk_seo_autofill_focuskw_per_page', 20 ) ) );
        $paged    = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
        $messages = [];

        // Single row sync
        if ( isset( $_GET['sk_seo_sync'] ) ) {
            $product_id = absint( $_GET['sk_seo_sync'] );
            $nonce      = isset( $_GET['_wpnonce'] ) ? wp_unslash( $_GET['_wpnonce'] ) : '';
            if ( $product_id && $nonce && wp_verify_nonce( $nonce, 'sk_seo_sync_' . $product_id ) ) {
                $messages[] = self::sync_focuskw( $product_id );
            } else {
                $messages[] = [ 'type' => 'error', 'message' => __( 'Security check failed.', 'sk' ) ];
            }
        }

        // Bulk sync
        if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['sk_seo_audit_action'] ) ) {
            check_admin_referer( 'sk_seo_audit_bulk' );
            $ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ) : [];
            $ids = array_filter( $ids );
            if ( $ids ) {
                $updated = 0;
                foreach ( $ids as $pid ) {
                    $r = self::sync_focuskw( $pid );
                    if ( 'updated' === $r['type'] ) $updated++;
                }
                $messages[] = [ 'type' => 'updated', 'message' => sprintf( '%d focus keyword(s) updated.', $updated ) ];
            } else {
                $messages[] = [ 'type' => 'error', 'message' => __( 'No products selected.', 'sk' ) ];
            }
        }

        $query = new \WP_Query( [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'ID',
            'order'          => 'DESC',
        ] );

        $current_url = remove_query_arg( [ 'sk_seo_sync', '_wpnonce' ] );
        ?>
        <div class="wrap">
            <h1>Focus Keyword Audit</h1>
            <?php foreach ( $messages as $n ) :
                if ( empty( $n['message'] ) ) continue;
                ?>
                <div class="notice <?php echo esc_attr( $n['type'] ); ?>"><p><?php echo wp_kses_post( $n['message'] ); ?></p></div>
            <?php endforeach; ?>

            <form method="post">
                <?php wp_nonce_field( 'sk_seo_audit_bulk' ); ?>
                <input type="hidden" name="sk_seo_audit_action" value="sync_selected" />
                <p><button type="submit" class="button button-primary">Sync selected focus keywords with title</button></p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column"><input type="checkbox" id="sk-seo-check-all" /></td>
                            <th>Product</th><th>Title</th><th>Focus Keyword</th><th>Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( $query->have_posts() ) :
                        while ( $query->have_posts() ) : $query->the_post();
                            $pid       = get_the_ID();
                            $title     = trim( wp_strip_all_tags( get_the_title( $pid ) ) );
                            $focus     = trim( (string) get_post_meta( $pid, '_yoast_wpseo_focuskw', true ) );
                            $in_sync   = strtolower( $focus ) === strtolower( $title );
                            $missing   = $focus === '';
                            $cls       = $missing ? 'status-missing' : ( $in_sync ? 'status-ok' : 'status-desynced' );
                            $label     = $missing ? 'Missing' : ( $in_sync ? 'In sync' : 'Out of sync' );
                            $sync_url  = wp_nonce_url( add_query_arg( [ 'page' => 'sk-seo-focuskw-audit', 'paged' => $paged, 'sk_seo_sync' => $pid ], admin_url( 'admin.php' ) ), 'sk_seo_sync_' . $pid );
                            ?>
                            <tr class="<?php echo esc_attr( $cls ); ?>">
                                <th class="check-column"><input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( $pid ); ?>" /></th>
                                <td><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a></td>
                                <td><?php echo esc_html( $title ); ?></td>
                                <td><?php echo esc_html( $focus ); ?></td>
                                <td><span class="sk-seo-status <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $label ); ?></span></td>
                                <td><a class="button button-secondary" href="<?php echo esc_url( $sync_url ); ?>">Sync to title</a></td>
                            </tr>
                        <?php endwhile; wp_reset_postdata();
                    else : ?>
                        <tr><td colspan="6">No products found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </form>

            <?php
            if ( $query->max_num_pages > 1 ) {
                $links = paginate_links( [ 'base' => add_query_arg( 'paged', '%#%', $current_url ), 'total' => $query->max_num_pages, 'current' => $paged ] );
                if ( $links ) echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
            }
            ?>
        </div>
        <script>
        (function(){
            var t=document.getElementById('sk-seo-check-all');
            if(!t)return;
            t.addEventListener('change',function(){
                var b=document.querySelectorAll('input[name="product_ids[]"]');
                for(var i=0;i<b.length;i++)b[i].checked=t.checked;
            });
        })();
        </script>
        <style>
            .status-missing td,.status-desynced td{background:#fff8e5}
            .status-missing td:first-child,.status-desynced td:first-child{border-left:3px solid #f0ad4e}
            .sk-seo-status.status-ok{color:#008a20}
            .sk-seo-status.status-missing,.sk-seo-status.status-desynced{color:#d63638}
        </style>
        <?php
    }

    private static function sync_focuskw( int $product_id ): array {
        $post = get_post( $product_id );
        if ( ! $post || $post->post_type !== 'product' ) {
            return [ 'type' => 'error', 'message' => 'Invalid product.' ];
        }
        $title   = trim( wp_strip_all_tags( $post->post_title ) );
        $current = trim( (string) get_post_meta( $product_id, '_yoast_wpseo_focuskw', true ) );
        if ( $current === $title ) {
            return [ 'type' => 'updated', 'message' => sprintf( 'Focus keyword for "%s" already matches.', esc_html( $post->post_title ) ) ];
        }
        update_post_meta( $product_id, '_yoast_wpseo_focuskw', $title );
        return [ 'type' => 'updated', 'message' => sprintf( 'Focus keyword for "%s" synced.', esc_html( $post->post_title ) ) ];
    }
}
