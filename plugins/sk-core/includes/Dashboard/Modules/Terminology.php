<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Replaces "Store/Stores" with "Anbieter" in sk-core strings that carry no
 * translation yet; the catalogue itself already uses "Anbieter".
 * Ported from kadence-child/functions.php.
 */
class Terminology {

    public function __construct() {
        add_filter( 'gettext',  [ $this, 'replace_store_with_anbieter' ], 20, 3 );
        add_filter( 'ngettext', [ $this, 'replace_store_with_anbieter' ], 20, 3 );
    }

    public function replace_store_with_anbieter( string $translated, string $text, string $domain ): string {
        if ( 'sk-core' !== $domain ) {
            return $translated;
        }

        $map = [
            'Store'               => 'Anbieter',
            'store'               => 'Anbieter',
            'Stores'              => 'Anbieter',
            'stores'              => 'Anbieter',
            'Visit Store'         => 'Zum Anbieter',
            'Visit store'         => 'Zum Anbieter',
            'Vendor Store'        => 'Anbieter',
            'Store Name'          => 'Anbieter-Name',
            'Store name'          => 'Anbieter-Name',
            'Store Address'       => 'Anbieter-Adresse',
            'Store address'       => 'Anbieter-Adresse',
            'Store Phone'         => 'Anbieter-Telefon',
            'Store phone'         => 'Anbieter-Telefon',
            'Store Categories'    => 'Anbieter-Kategorien',
            'Store Category'      => 'Anbieter-Kategorie',
            'All Stores'          => 'Alle Anbieter',
            'Featured Stores'     => 'Empfohlene Anbieter',
            'Store Listing'       => 'Anbieterliste',
            'Store location'      => 'Anbieter-Standort',
            'Search Stores'       => 'Anbieter suchen',
        ];

        if ( isset( $map[ $text ] ) ) {
            return $map[ $text ];
        }

        $translated = preg_replace( '/\bStores\b/u', 'Anbieter', $translated );
        $translated = preg_replace( '/\bstores\b/u', 'Anbieter', $translated );
        $translated = preg_replace( '/\bStore\b/u',  'Anbieter', $translated );
        $translated = preg_replace( '/\bstore\b/u',  'Anbieter', $translated );

        return $translated;
    }
}
