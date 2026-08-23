<?php
/**
 * Metabox im Sponsor-Editor.
 *
 * @var string $url
 * @var string $email
 * @var bool   $manual
 * @var string $tier
 * @var int    $monthly
 * @var int    $balance
 * @var string $sort
 * @var string $starts
 * @var string $expires
 * @var string $slug
 */

defined( 'ABSPATH' ) || exit;

use SK\Modules\Sponsors\Backlink;
use SK\Modules\Sponsors\Billing;
use SK\Modules\Sponsors\PostType;

$months_left = $monthly > 0 ? (int) floor( $balance / $monthly ) : null;
?>
<style>
    .sk-sponsor-fields { display: grid; gap: 16px; max-width: 680px; }
    .sk-sponsor-fields label { display: block; font-weight: 600; margin-bottom: 4px; }
    .sk-sponsor-fields input, .sk-sponsor-fields select { width: 100%; }
    .sk-sponsor-fields .sk-hint { font-weight: 400; color: #646970; font-size: 12px; margin-top: 4px; }
    .sk-sponsor-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .sk-sponsor-note { background: #f6f7f7; border-left: 4px solid #db6218; padding: 8px 12px; font-size: 12px; }
</style>

<div class="sk-sponsor-fields">
    <div>
        <label for="sk_sponsor_url"><?php esc_html_e( 'Ziel-URL', 'sk-core' ); ?></label>
        <input type="url" id="sk_sponsor_url" name="sk_sponsor_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://">
        <p class="sk-hint">
            <?php esc_html_e( 'Auf der Seite steht nicht diese Adresse, sondern die Zählweiche:', 'sk-core' ); ?>
            <code><?php echo esc_html( home_url( '/go/' . $slug . '/' ) ); ?></code>
        </p>
    </div>

    <div>
        <label for="sk_sponsor_email"><?php esc_html_e( 'E-Mail des Sponsors', 'sk-core' ); ?></label>
        <input type="email" id="sk_sponsor_email" name="sk_sponsor_email" value="<?php echo esc_attr( $email ); ?>" placeholder="kontakt@shop.example">
        <p class="sk-hint">
            <?php esc_html_e( 'Empfänger der Erinnerung, wenn das Guthaben zur Neige geht. Ohne Adresse wird nicht erinnert.', 'sk-core' ); ?>
            <?php if ( get_the_ID() ) : ?>
                <br><?php esc_html_e( 'Selbstbedienungsseite:', 'sk-core' ); ?>
                <code><?php echo esc_html( home_url( '/sponsor/' . PostType::token( (int) get_the_ID() ) . '/' ) ); ?></code>
            <?php endif; ?>
        </p>
    </div>

    <div>
        <label style="font-weight:600;">
            <input type="checkbox" name="sk_sponsor_backlink_manual" value="1" <?php checked( $manual ); ?>>
            <?php esc_html_e( 'Rücklink von Hand bestätigt', 'sk-core' ); ?>
        </label>
        <p class="sk-hint">
            <?php esc_html_e( 'Setzen, wenn die Seite nachweislich zurückverlinkt, die automatische Prüfung sie aber nicht erreicht — etwa weil sie im selben Netz liegt oder serverseitige Abrufe blockt. Das Häkchen gewinnt gegen die automatische Prüfung.', 'sk-core' ); ?>
        </p>
    </div>

    <div class="sk-sponsor-row">
        <div>
            <label for="sk_sponsor_monthly"><?php esc_html_e( 'Monatsrate (Sats)', 'sk-core' ); ?></label>
            <input type="number" id="sk_sponsor_monthly" name="sk_sponsor_monthly" value="<?php echo esc_attr( (string) $monthly ); ?>" min="0" step="1000">
            <p class="sk-hint"><?php esc_html_e( 'Bestimmt die Reihenfolge: höhere Rate steht weiter vorn. 0 = zahlt nichts.', 'sk-core' ); ?></p>
        </div>

        <div>
            <label for="sk_sponsor_balance"><?php esc_html_e( 'Guthaben (Sats)', 'sk-core' ); ?></label>
            <input type="number" id="sk_sponsor_balance" name="sk_sponsor_balance" value="<?php echo esc_attr( (string) $balance ); ?>" min="0" step="1000">
            <p class="sk-hint">
                <?php
                if ( $months_left === null ) {
                    esc_html_e( 'Vorkasse. Ohne Monatsrate wird nichts abgebucht.', 'sk-core' );
                } else {
                    printf(
                        /* translators: %d: number of months */
                        esc_html__( 'Reicht noch für %d volle Monate.', 'sk-core' ),
                        (int) $months_left
                    );
                }
                ?>
            </p>
        </div>
    </div>

    <?php if ( ! Billing::is_enabled() ) : ?>
        <p class="sk-sponsor-note">
            <?php esc_html_e( 'Die monatliche Abbuchung ist derzeit abgeschaltet. Raten und Guthaben lassen sich schon pflegen, es wird aber nichts verbraucht und niemand fällt wegen leeren Guthabens von der Seite.', 'sk-core' ); ?>
        </p>
    <?php endif; ?>

    <div class="sk-sponsor-row">
        <div>
            <label for="sk_sponsor_tier"><?php esc_html_e( 'Stufe', 'sk-core' ); ?></label>
            <select id="sk_sponsor_tier" name="sk_sponsor_tier">
                <option value="<?php echo esc_attr( PostType::TIER_STANDARD ); ?>" <?php selected( $tier, PostType::TIER_STANDARD ); ?>><?php echo esc_html( _x( 'Standard', 'Sponsorenstufe', 'sk-core' ) ); ?></option>
                <option value="<?php echo esc_attr( PostType::TIER_TOP ); ?>" <?php selected( $tier, PostType::TIER_TOP ); ?>><?php echo esc_html( _x( 'Top', 'Sponsorenstufe', 'sk-core' ) ); ?></option>
            </select>
            <p class="sk-hint"><?php esc_html_e( 'Top ist der obere, knappe Block mit drei Plätzen.', 'sk-core' ); ?></p>
        </div>

        <div>
            <label for="sk_sponsor_sort_hint"><?php esc_html_e( 'Reihenfolge bei gleicher Rate', 'sk-core' ); ?></label>
            <input type="number" id="sk_sponsor_sort_hint" name="sk_sponsor_sort_hint" value="<?php echo esc_attr( $sort ); ?>" min="0" step="1">
            <p class="sk-hint"><?php esc_html_e( 'Übernommen aus der alten Rangzahl. Zählt nur, wenn zwei dieselbe Monatsrate haben.', 'sk-core' ); ?></p>
        </div>
    </div>

    <div class="sk-sponsor-row">
        <div>
            <label for="sk_sponsor_starts"><?php esc_html_e( 'Sichtbar ab', 'sk-core' ); ?></label>
            <input type="date" id="sk_sponsor_starts" name="sk_sponsor_starts" value="<?php echo esc_attr( $starts ); ?>">
            <p class="sk-hint"><?php esc_html_e( 'Leer = sofort.', 'sk-core' ); ?></p>
        </div>

        <div>
            <label for="sk_sponsor_expires"><?php esc_html_e( 'Sichtbar bis', 'sk-core' ); ?></label>
            <input type="date" id="sk_sponsor_expires" name="sk_sponsor_expires" value="<?php echo esc_attr( $expires ); ?>">
            <p class="sk-hint"><?php esc_html_e( 'Leer = unbefristet. Nützlich für eine Übergangsfrist.', 'sk-core' ); ?></p>
        </div>
    </div>
</div>
