<?php

namespace SK\Modules\ReportAbuse;

use SK\Core\Cache;

/**
 * Abuse Report Cache class.
 *
 * Manage all of the cachings for abuse report.
 *
 *
 * @see \SK\Core\Cache
 */
class ReportAbuseCache {

    public function __construct() {
        add_action( 'sk_report_abuse_created_report', [ $this, 'clear_abuse_report_cache' ], 10 );
        add_action( 'sk_report_abuse_deleted_report', [ $this, 'clear_abuse_report_cache' ], 10 );
    }

    /**
     * Clear Abuse Reports cache
     *
     *
     * @return void
     */
    public function clear_abuse_report_cache() {
        Cache::invalidate_group( 'abuse_reports' );
    }
}
