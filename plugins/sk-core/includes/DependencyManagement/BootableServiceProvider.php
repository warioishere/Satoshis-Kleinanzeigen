<?php
/**
 * Bootable service provider base class.
 */

namespace SK\Core\DependencyManagement;

abstract class BootableServiceProvider extends BaseServiceProvider {

	abstract public function boot(): void;
}
