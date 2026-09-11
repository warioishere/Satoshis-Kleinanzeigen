<?php
/**
 * Template: the store's trust page at /store/{slug}/vertrauen/
 *
 * Every signal with its source and the way to check it yourself.
 */

use SK\Modules\Reputation\SocialGraph;
use SK\Modules\Reputation\TrustPage;

$custom_store_url = sk_get_option( 'custom_store_url', 'sk_general', 'store' );
$store_name       = get_query_var( $custom_store_url );
$store_user       = get_user_by( 'slug', $store_name );

if ( ! $store_user ) {
    return;
}

$vendor_id    = (int) $store_user->ID;
$vendor       = sk()->vendor->get( $vendor_id );
$map_location = $vendor->get_location();
$store_info   = sk_get_store_info( $vendor_id );
$layout       = get_theme_mod( 'store_layout', 'left' );

$nostr     = TrustPage::nostr( $vendor_id );
$links     = TrustPage::verified_links( $vendor_id );
$zaps      = TrustPage::zaps( $vendor_id );
$lightning = TrustPage::lightning( $vendor_id );

$proof_types = [
    'login'   => __( 'Der Anbieter hat sich mit diesem Schlüssel hier angemeldet; die Anmeldung ist eine signierte Anfrage an diese Seite.', 'sk-core' ),
    'held'    => __( 'Der Schlüssel wurde von dieser Seite für den Anbieter erzeugt und wird hier verwahrt.', 'sk-core' ),
    'binding' => __( 'Der Anbieter hat mit seiner Nostr-Erweiterung ein Event signiert, das diesen Shop nennt.', 'sk-core' ),
    // A key on the account without a kept event: linked, but how is not on record.
    'linked'  => __( 'Der Schlüssel ist als Anmeldeschlüssel dieses Kontos hinterlegt.', 'sk-core' ),
];

$graph_chip = ( sk_module_active( 'sk_reputation' ) && class_exists( SocialGraph::class ) )
    ? ( new SocialGraph() )->chip( $vendor_id, \SK\Core\Trust\TrustSignals::CONTEXT_PAGE )
    : '';

get_header( 'shop' );
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="sk-store-wrap layout-<?php echo esc_attr( $layout ); ?>">
    <?php if ( 'left' === $layout ) { ?>
        <?php
        sk_get_template_part( 'store', 'sidebar', [
            'store_user'   => $store_user,
            'store_info'   => $store_info,
            'map_location' => $map_location,
        ] );
        ?>
    <?php } ?>

    <div id="primary" class="content-area sk-single-store">
        <div id="sk-content" class="site-content store-review-wrap woocommerce" role="main">

            <?php sk_get_template_part( 'store-header' ); ?>

            <div class="sk-trust-page">

                <div class="sk-trust-card sk-trust-intro">
                    <h2><?php esc_html_e( 'Vertrauen, das du selbst prüfen kannst', 'sk-core' ); ?></h2>
                    <p><?php esc_html_e( 'Diese Seite behauptet nicht, dass der Anbieter vertrauenswürdig ist. Sie zeigt Fakten, die jeder nachrechnen kann: welcher Nostr-Schlüssel nachweislich zu diesem Shop gehört, wer aus deinem eigenen Netzwerk ihm folgt, was er an Zaps erhalten hat und welche Zahlungen belegt sind. Was fehlt, fehlt einfach; es gibt hier keine Abwertung.', 'sk-core' ); ?></p>
                </div>

                <?php if ( '' !== $graph_chip ) : ?>
                <div class="sk-trust-card">
                    <h3><i class="fas fa-users"></i> <?php esc_html_e( 'Dein Netzwerk', 'sk-core' ); ?></h3>
                    <div class="sk-trust-graph-slot"><?php echo $graph_chip; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
                    <p class="sk-trust-hint" data-sk-trust-nokey hidden><?php esc_html_e( 'Mit einer Nostr-Erweiterung im Browser oder nach einem Nostr-Login siehst du hier, ob du diesem Anbieter folgst und wie viele deiner Kontakte es tun. Das rechnet allein dein Browser aus deiner eigenen Kontaktliste aus.', 'sk-core' ); ?></p>
                    <p class="sk-trust-hint" data-sk-trust-nomatch hidden><?php esc_html_e( 'Aus deiner Kontaktliste folgt diesem Anbieter niemand. Das sagt nichts über den Anbieter, nur über die Überschneidung mit deinem Netzwerk.', 'sk-core' ); ?></p>
                    <p class="sk-trust-note"><?php esc_html_e( 'Prüfen: Deine Kontaktliste ist dein Kind-3-Event auf den Relays. Die Kontaktlisten deiner Kontakte, die diesen Schlüssel enthalten, sind die Treffer. Der Marktplatz ist an dieser Rechnung nicht beteiligt.', 'sk-core' ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( $nostr ) : ?>
                <div class="sk-trust-card">
                    <h3><i class="fas fa-key"></i> <?php esc_html_e( 'Nostr-Schlüssel', 'sk-core' ); ?></h3>
                    <p>
                        <a href="<?php echo esc_url( 'https://njump.me/' . ( $nostr['npub'] ?: $nostr['pubkey'] ) ); ?>" target="_blank" rel="noopener" class="sk-trust-key"><?php echo esc_html( $nostr['npub'] ?: $nostr['pubkey'] ); ?></a>
                    </p>
                    <p><?php echo esc_html( $proof_types[ $nostr['type'] ] ?? '' ); ?></p>

                    <?php if ( $nostr['event'] && 30078 === (int) ( $nostr['event']['kind'] ?? 0 ) ) : ?>
                        <p class="sk-trust-note">
                            <?php esc_html_e( 'Prüfen: Das Bindungs-Event (Kind 30078) ist mit diesem Schlüssel signiert und nennt diese Seite und diesen Shop.', 'sk-core' ); ?>
                            <?php if ( ! empty( $nostr['relays'] ) ) : ?>
                                <?php
                                printf(
                                    /* translators: %s: comma separated relay list */
                                    esc_html__( 'Es liegt auf %s.', 'sk-core' ),
                                    esc_html( implode( ', ', array_map( static fn( $r ) => preg_replace( '#^wss?://#', '', (string) $r ), $nostr['relays'] ) ) )
                                );
                                ?>
                            <?php endif; ?>
                        </p>
                    <?php elseif ( $nostr['event'] ) : ?>
                        <p class="sk-trust-note"><?php esc_html_e( 'Prüfen: Das Anmelde-Event (Kind 27235, NIP-98) ist mit diesem Schlüssel signiert und an diese Seite adressiert.', 'sk-core' ); ?></p>
                    <?php endif; ?>

                    <?php if ( '' !== $nostr['event_json'] ) : ?>
                        <details class="sk-trust-raw">
                            <summary><?php esc_html_e( 'Signiertes Event anzeigen', 'sk-core' ); ?></summary>
                            <pre><?php echo esc_html( $nostr['event_json'] ); ?></pre>
                        </details>
                    <?php endif; ?>

                    <?php
                    /*
                     * NIP-05 for the shop, answered by this site for every proven key.
                     * It is only a fact when the vendor actually uses it: with an
                     * identity this site generated the profile carries it, and a
                     * vendor with their own key may have put it into their profile.
                     * Otherwise visitors see nothing, and the vendor sees the offer.
                     */
                    $nip05    = $store_user->user_nicename . '@' . \SK\Core\Trust\VendorKey::site();
                    $in_use   = ( class_exists( '\SK\Modules\Auth\NostrIdentity' ) && \SK\Modules\Auth\NostrIdentity::has_identity( $vendor_id ) )
                        || strcasecmp( (string) get_user_meta( $vendor_id, 'nip05', true ), $nip05 ) === 0;
                    $is_owner = get_current_user_id() === $vendor_id;
                    ?>
                    <?php if ( $in_use ) : ?>
                        <p class="sk-trust-nip05">
                            <span class="sk-trust-note"><?php esc_html_e( 'NIP-05-Adresse dieses Shops:', 'sk-core' ); ?></span>
                            <code><?php echo esc_html( $nip05 ); ?></code>
                        </p>
                        <p class="sk-trust-note">
                            <?php esc_html_e( 'Prüfen: Diese Seite beantwortet die Adresse mit genau diesem Schlüssel, und sie steht im Nostr-Profil des Anbieters. Jeder Nostr-Client zeigt dafür das Häkchen dieser Seite.', 'sk-core' ); ?>
                        </p>
                    <?php elseif ( $is_owner ) : ?>
                        <p class="sk-trust-note">
                            <?php
                            printf(
                                /* translators: %s: NIP-05 address */
                                esc_html__( 'Diese Seite beantwortet für deinen Schlüssel die NIP-05-Adresse %s. Trägst du sie in deinem Nostr-Profil ein, zeigt jeder Nostr-Client das Häkchen dieser Seite. Einrichten musst du hier nichts.', 'sk-core' ),
                                '<code>' . esc_html( $nip05 ) . '</code>'
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $links ) ) : ?>
                <div class="sk-trust-card">
                    <h3><i class="fas fa-circle-check"></i> <?php esc_html_e( 'Bestätigte Links', 'sk-core' ); ?></h3>
                    <ul class="sk-trust-list">
                        <?php foreach ( $links as $link ) : ?>
                            <li><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener nofollow"><?php echo esc_html( $link['url'] ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="sk-trust-note"><?php esc_html_e( 'Prüfen: Die Seite verlinkt zurück auf diesen Shop. Öffne sie und such den Link.', 'sk-core' ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( $zaps['sats'] > 0 ) : ?>
                <div class="sk-trust-card">
                    <h3><i class="fas fa-bolt"></i> <?php esc_html_e( 'Erhaltene Zaps', 'sk-core' ); ?></h3>
                    <p class="sk-trust-big"><?php echo esc_html( number_format( $zaps['sats'], 0, '', '.' ) ); ?> Sats</p>
                    <p>
                        <?php
                        printf(
                            /* translators: 1: number of zaps, 2: date */
                            esc_html( _n( '%1$s Zap, Stand %2$s.', '%1$s Zaps, Stand %2$s.', $zaps['count'], 'sk-core' ) ),
                            esc_html( number_format_i18n( $zaps['count'] ) ),
                            esc_html( $zaps['time'] ? wp_date( 'd.m.Y H:i', $zaps['time'] ) : '–' )
                        );
                        ?>
                    </p>
                    <p class="sk-trust-note"><?php esc_html_e( 'Prüfen: Zaps sind Kind-9735-Quittungen auf den Relays, adressiert an den Schlüssel des Anbieters. Jeder Nostr-Client zeigt sie am Profil.', 'sk-core' ); ?></p>
                </div>
                <?php endif; ?>

                <?php if ( $lightning ) : ?>
                    <?php include SK_REPUTATION_TEMPLATES . '/store-trust-lightning.php'; ?>
                <?php endif; ?>

            </div><!-- .sk-trust-page -->

        </div><!-- #content .site-content -->
    </div><!-- #primary .content-area -->

    <div class="sk-clearfix"></div>

    <?php if ( 'right' === $layout ) { ?>
        <?php
        sk_get_template_part( 'store', 'sidebar', [
            'store_user'   => $store_user,
            'store_info'   => $store_info,
            'map_location' => $map_location,
        ] );
        ?>
    <?php } ?>

</div><!-- .sk-store-wrap -->

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer(); ?>
