<?php

namespace SK\Core\BackgroundProcess;

defined( 'ABSPATH' ) || exit;

use SK\Core\Traits\ChainableContainer;

/**
 * Background Process Manager Class.
 *
 * @property SyncVendorZoneData $sync_vendor_zone_data
 */
class ExtendedManager {

    use ChainableContainer;

    public function __construct() {
        $this->container['sync_vendor_zone_data'] = new SyncVendorZoneData();
        $this->container = apply_filters( 'sk_pro_background_process_container', $this->container );
    }
}
