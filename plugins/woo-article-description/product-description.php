<?php
/*
Plugin Name: Produktbeschreibung im Shop anzeigen
Description: Zeigt einen Auszug der Produktbeschreibung (nicht Kurzbeschreibung) auf der Shop- und Kategorieseite. Anzahl der Wörter kann in den Einstellungen geändert werden.
Version: 1.0
Author: Wario
*/

// Einstellungen registrieren
add_action('admin_init', function() {
    register_setting('pbs_einstellungen', 'pbs_wortanzahl');

    add_settings_section(
        'pbs_einstellungen_section',
        'Produktbeschreibung im Shop anzeigen',
        function() {
            echo '<p>Lege fest, wie viele Wörter der Beschreibung angezeigt werden sollen.</p>';
        },
        'pbs_einstellungen'
    );

    add_settings_field(
        'pbs_wortanzahl',
        'Wortanzahl für Beschreibungsauszug',
        function() {
            $wert = get_option('pbs_wortanzahl', 20);
            echo "<input type='number' name='pbs_wortanzahl' value='" . esc_attr($wert) . "' min='1' max='100'>";
        },
        'pbs_einstellungen',
        'pbs_einstellungen_section'
    );
});

// Optionsseite hinzufügen
add_action('admin_menu', function() {
    add_options_page(
        'Produktbeschreibung Shop',
        'Produktbeschreibung Shop',
        'manage_options',
        'pbs_einstellungen',
        function() {
            echo '<div class="wrap">';
            echo '<h1>Produktbeschreibung im Shop anzeigen</h1>';
            echo '<form method="post" action="options.php">';
            settings_fields('pbs_einstellungen');
            do_settings_sections('pbs_einstellungen');
            submit_button();
            echo '</form></div>';
        }
    );
});

// Beschreibung unter Produkttitel einfügen
add_action('woocommerce_after_shop_loop_item_title', function() {
    global $post;
    $anzahl = get_option('pbs_wortanzahl', 20);
    $inhalt = wp_strip_all_tags($post->post_content);
    $wortliste = explode(' ', $inhalt);
    if (count($wortliste) > $anzahl) {
        $inhalt = implode(' ', array_slice($wortliste, 0, $anzahl)) . '...';
    }
    echo '<div class="produkt-beschreibung-auszug" style="margin-top:5px; color:#ccc; font-size:0.9em;">' . esc_html($inhalt) . '</div>';
}, 9);
