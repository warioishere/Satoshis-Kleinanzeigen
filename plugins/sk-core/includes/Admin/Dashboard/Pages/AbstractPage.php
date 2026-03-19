<?php

namespace SK\Core\Admin\Dashboard\Pages;

use SK\Core\Contracts\Hookable;

/**
 * Deprecated stub — kept only so sk-pro modules that extend it don't crash.
 * The React/Vue admin dashboard has been replaced by PhpDashboard.
 */
abstract class AbstractPage implements Hookable {

    public function register_hooks(): void {
    }

    abstract public function get_id(): string;

    public function menu( string $capability, string $position ): array {
        return [];
    }

    public function settings(): array {
        return [];
    }

    public function scripts(): array {
        return [];
    }

    public function styles(): array {
        return [];
    }

    public function register(): void {
        // No-op.
    }
}
