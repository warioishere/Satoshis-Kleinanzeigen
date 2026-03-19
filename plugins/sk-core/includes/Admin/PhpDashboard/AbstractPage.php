<?php

namespace SK\Core\Admin\PhpDashboard;

abstract class AbstractPage {

    abstract public function get_slug(): string;

    abstract public function get_title(): string;

    abstract public function render(): void;

    abstract public function handle_post(): void;

    public function get_capability(): string {
        return 'manage_woocommerce';
    }

    public function is_pro(): bool {
        return false;
    }

    public function get_menu_position(): int {
        return 10;
    }
}
