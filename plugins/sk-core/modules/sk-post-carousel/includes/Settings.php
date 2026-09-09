<?php

namespace SK\Modules\PostCarousel;

defined( 'ABSPATH' ) || exit;

/**
 * The module's settings section, replacing the old Settings → Post Image
 * Carousel page (`add_options_page`, array option `wppic_settings`).
 *
 * That option is migrated into this section once: if it still exists when
 * the section is read for the first time, its values are copied in
 * (arrows "1"/"0" becomes "on"/"off") and the old option is dropped.
 */
class Settings {

    const SECTION = 'sk_post_carousel';

    /** The option this section replaces (Settings → Post Image Carousel). */
    const LEGACY_OPTION = 'wppic_settings';

    public function __construct() {
        $this->migrate_legacy_option();

        add_filter( 'sk_settings_sections', [ $this, 'add_section' ] );
        add_filter( 'sk_settings_fields', [ $this, 'add_fields' ] );
    }

    private function migrate_legacy_option(): void {
        $legacy = get_option( self::LEGACY_OPTION, null );

        if ( ! is_array( $legacy ) ) {
            return;
        }

        $section = get_option( self::SECTION );
        $section = is_array( $section ) ? $section : [];

        if ( ! isset( $section['posts'] ) ) {
            $section['posts']      = $legacy['posts'] ?? 8;
            $section['categories'] = $legacy['categories'] ?? '';
            $section['gap']        = $legacy['gap'] ?? 15;
            $section['h_height']   = $legacy['h_height'] ?? 200;
            $section['v_width']    = $legacy['v_width'] ?? 200;
            $section['direction']  = ( $legacy['direction'] ?? 'horizontal' ) === 'vertical' ? 'vertical' : 'horizontal';
            $section['arrows']     = ( $legacy['arrows'] ?? '0' ) === '1' ? 'on' : 'off';
            update_option( self::SECTION, $section );
        }

        delete_option( self::LEGACY_OPTION );
    }

    public function add_section( $sections ) {
        $sections[] = [
            'id'                   => self::SECTION,
            'title'                => __( 'Post Image Carousel', 'sk-core' ),
            'icon_url'             => '',
            'description'          => __( 'Karussell für Beitrags-Bilder', 'sk-core' ),
            'settings_title'       => __( 'Post Image Carousel', 'sk-core' ),
            'settings_description' => __( 'Standardwerte für den Shortcode [post_image_carousel]. Shortcode-Attribute überschreiben diese Einstellungen.', 'sk-core' ),
        ];

        return $sections;
    }

    public function add_fields( $settings_fields ) {
        $settings_fields[ self::SECTION ] = [
            'posts' => [
                'name'    => 'posts',
                'label'   => __( 'Anzahl Beiträge', 'sk-core' ),
                'type'    => 'number',
                'min'     => '1',
                'default' => '8',
            ],
            'categories' => [
                'name'    => 'categories',
                'label'   => __( 'Kategorien', 'sk-core' ),
                'type'    => 'text',
                'default' => '',
                'desc'    => __( 'Slugs oder IDs, komma-getrennt. Beispiel: news,12,events.', 'sk-core' ),
            ],
            'gap' => [
                'name'    => 'gap',
                'label'   => __( 'Abstand zwischen Bildern (px, horizontal)', 'sk-core' ),
                'type'    => 'number',
                'min'     => '0',
                'default' => '15',
            ],
            'h_height' => [
                'name'    => 'h_height',
                'label'   => __( 'Höhe der Bilder (horizontal, px)', 'sk-core' ),
                'type'    => 'number',
                'min'     => '50',
                'default' => '200',
            ],
            'v_width' => [
                'name'    => 'v_width',
                'label'   => __( 'Breite der Bilder (vertikal, px)', 'sk-core' ),
                'type'    => 'number',
                'min'     => '50',
                'default' => '200',
            ],
            'direction' => [
                'name'    => 'direction',
                'label'   => __( 'Standard-Richtung', 'sk-core' ),
                'type'    => 'select',
                'options' => [
                    'horizontal' => __( 'Horizontal', 'sk-core' ),
                    'vertical'   => __( 'Vertikal', 'sk-core' ),
                ],
                'default' => 'horizontal',
            ],
            'arrows' => [
                'name'    => 'arrows',
                'label'   => __( 'Pfeile anzeigen', 'sk-core' ),
                'type'    => 'switcher',
                'default' => 'off',
            ],
        ];

        return $settings_fields;
    }
}
