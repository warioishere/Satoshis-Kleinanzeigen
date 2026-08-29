<?php

namespace SK\Core\DependencyManagement\Providers;

use SK\Core\DependencyManagement\BaseServiceProvider;

class CommonServiceProvider extends BaseServiceProvider {

	protected array $tags = [ 'common-service' ];

	protected array $services = [
		\SK\Core\Product\Hooks::class,
		\SK\Core\ProductCategory\Hooks::class,
		\SK\Core\Vendor\Hooks::class,
		\SK\Core\Vendor\UserSwitch::class,
		\SK\Core\Vendor\SuspensionGuard::class,
		\SK\Core\Vendor\ProfileGuard::class,
		\SK\Core\CacheInvalidate::class,
		\SK\Core\Privacy::class,
		\SK\Core\Exceptions\Handler::class,
		\SK\Core\StoreCategory::class,
		\SK\Core\Store::class,
		\SK\Core\Review::class,
		\SK\Core\Announcement\Announcement::class,
	];

	public function register(): void {
		foreach ( $this->services as $service ) {
			$definition = $this->share_with_implements_tags( $service );
			$this->add_tags( $definition, $this->tags );
		}
	}
}
