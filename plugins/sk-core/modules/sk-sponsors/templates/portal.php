<?php
/**
 * Selbstbedienungsseite eines Sponsors: /sponsor/<token>/
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\Sponsors\PostType;
use SK\Modules\Sponsors\Portal;
use SK\Modules\Sponsors\Stats;

$sponsor = PostType::by_token( (string) get_query_var( Portal::QUERY_VAR ) );

if ( ! $sponsor ) {
    wp_safe_redirect( home_url( '/' ) );
    exit;
}

$sponsor_id = (int) $sponsor->ID;
$monthly    = (int) get_post_meta( $sponsor_id, PostType::META_MONTHLY, true );
$balance    = (int) get_post_meta( $sponsor_id, PostType::META_BALANCE, true );
$rate       = $monthly > 0 ? $monthly : Portal::default_rate();
$months     = PostType::months_left( $sponsor_id );
$running    = $sponsor->post_status === 'publish' && PostType::is_running( $sponsor_id );

$stats30 = Stats::for_sponsor( $sponsor_id, gmdate( 'Y-m-d', strtotime( '-30 days' ) ), current_time( 'Y-m-d' ) );
$stats   = Stats::for_sponsor( $sponsor_id, '2000-01-01', current_time( 'Y-m-d' ) );
$error   = isset( $_GET['fehler'] ) ? sanitize_text_field( wp_unslash( $_GET['fehler'] ) ) : '';

get_header();
?>
<div class="content-container site-container">
    <main id="primary" class="site-main sk-sponsor-portal">

        <h1><?php echo esc_html( $sponsor->post_title ); ?></h1>
        <p class="sk-portal-lead">
            <?php esc_html_e( 'Dein Sponsorenplatz auf Satoshis Kleinanzeigen — Zahlen und Verlängerung.', 'sk-core' ); ?>
        </p>

        <?php if ( $error === 'betrag' ) : ?>
            <p class="sk-portal-error"><?php esc_html_e( 'Bitte einen Betrag größer als 0 angeben.', 'sk-core' ); ?></p>
        <?php elseif ( $error === 'rechnung' ) : ?>
            <p class="sk-portal-error"><?php esc_html_e( 'Die Rechnung konnte nicht erstellt werden. Bitte melde dich bei uns.', 'sk-core' ); ?></p>
        <?php endif; ?>

        <div class="sk-portal-cards">
            <div class="sk-portal-card">
                <span class="sk-portal-num"><?php echo esc_html( number_format_i18n( $stats30['clicks'] ) ); ?></span>
                <span class="sk-portal-lbl"><?php esc_html_e( 'Klicks (30 Tage)', 'sk-core' ); ?></span>
            </div>
            <div class="sk-portal-card">
                <span class="sk-portal-num"><?php echo esc_html( number_format_i18n( $stats30['unique'] ) ); ?></span>
                <span class="sk-portal-lbl"><?php esc_html_e( 'davon verschiedene Besucher', 'sk-core' ); ?></span>
            </div>
            <div class="sk-portal-card">
                <span class="sk-portal-num"><?php echo esc_html( number_format_i18n( $stats['clicks'] ) ); ?></span>
                <span class="sk-portal-lbl"><?php esc_html_e( 'Klicks insgesamt', 'sk-core' ); ?></span>
            </div>
            <div class="sk-portal-card">
                <span class="sk-portal-num"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
                <span class="sk-portal-lbl">
                    <?php
                    if ( $months === null ) {
                        esc_html_e( 'Sats Guthaben', 'sk-core' );
                    } else {
                        printf(
                            /* translators: %d: months */
                            esc_html__( 'Sats Guthaben (noch %d Monate)', 'sk-core' ),
                            (int) $months
                        );
                    }
                    ?>
                </span>
            </div>
        </div>

        <p class="sk-portal-status <?php echo $running ? 'is-on' : 'is-off'; ?>">
            <?php
            if ( $running ) {
                esc_html_e( 'Dein Platz wird aktuell angezeigt.', 'sk-core' );
            } else {
                esc_html_e( 'Dein Platz wird aktuell nicht angezeigt.', 'sk-core' );
            }
            ?>
        </p>

        <h2><?php esc_html_e( 'Verlängern', 'sk-core' ); ?></h2>
        <p>
            <?php
            printf(
                /* translators: %s: monthly rate in sats */
                esc_html__( 'Dein Platz kostet %s Sats pro Monat. Du zahlst im Voraus auf ein Guthaben ein, von dem monatlich die Rate abgezogen wird — kein Abo, keine Kündigung. Läuft das Guthaben leer, endet die Anzeige einfach.', 'sk-core' ),
                esc_html( number_format_i18n( $rate ) )
            );
            ?>
        </p>

        <form method="post" action="<?php echo esc_url( Portal::url_for( $sponsor_id ) ); ?>" class="sk-portal-form">
            <div class="sk-portal-options">
                <?php foreach ( [ 3, 6, 12 ] as $i => $m ) : ?>
                    <label class="sk-portal-option">
                        <input type="radio" name="sk_topup_sats" value="<?php echo (int) ( $rate * $m ); ?>" <?php checked( $i, 1 ); ?>>
                        <strong><?php printf( esc_html__( '%d Monate', 'sk-core' ), (int) $m ); ?></strong>
                        <span><?php echo esc_html( number_format_i18n( $rate * $m ) ); ?> Sats</span>
                    </label>
                <?php endforeach; ?>
                <label class="sk-portal-option sk-portal-option--free">
                    <input type="radio" name="sk_topup_choice" value="frei">
                    <strong><?php esc_html_e( 'Anderer Betrag', 'sk-core' ); ?></strong>
                    <input type="number" name="sk_topup_custom" min="1000" step="1000" placeholder="Sats">
                </label>
            </div>

            <button type="submit" class="button sk-portal-submit">
                <?php esc_html_e( 'Rechnung erstellen und bezahlen', 'sk-core' ); ?>
            </button>
            <p class="sk-portal-hint">
                <?php esc_html_e( 'Bezahlung über Bitcoin oder Lightning. Nach der Zahlung wird das Guthaben sofort gutgeschrieben.', 'sk-core' ); ?>
            </p>
        </form>

        <script>
        // Ein freier Betrag soll den Monatsauswahlwert ersetzen, nicht daneben
        // stehen — sonst kaeme beim Absenden die Voreinstellung mit.
        ( function () {
            var form = document.querySelector( '.sk-portal-form' );
            if ( ! form ) { return; }
            var custom = form.querySelector( '[name="sk_topup_custom"]' );
            var freeRadio = form.querySelector( '[name="sk_topup_choice"]' );
            if ( ! custom || ! freeRadio ) { return; }

            custom.addEventListener( 'input', function () {
                freeRadio.checked = true;
                form.querySelectorAll( '[name="sk_topup_sats"]' ).forEach( function ( el ) { el.checked = false; } );
            } );

            form.addEventListener( 'submit', function () {
                if ( freeRadio.checked && custom.value ) {
                    var hidden = document.createElement( 'input' );
                    hidden.type = 'hidden';
                    hidden.name = 'sk_topup_sats';
                    hidden.value = custom.value;
                    form.appendChild( hidden );
                }
            } );
        }() );
        </script>
    </main>
</div>
<?php
get_footer();
