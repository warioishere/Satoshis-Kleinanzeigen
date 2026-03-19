<?php

namespace SK\Core\DependencyManagement\Providers;

use SK\Core\DependencyManagement\BaseServiceProvider;
use SK\Core\DigitalProduct;
use SK\Core\ThemeSupport\Manager;
use SK\Core\Vendor\StoreListsFilter;

class FrontendServiceProvider extends BaseServiceProvider {

	protected array $tags = [ 'frontend-service' ];

	protected array $services = [
		StoreListsFilter::class,
		Manager::class,
		DigitalProduct::class,
	];

	public function register(): void {
		$container = $this->getContainer();

		$this->add_tags(
			$container->addShared( StoreListsFilter::class, StoreListsFilter::class ),
			$this->tags
		);

		$this->add_tags(
			$container->addShared( Manager::class, Manager::class ),
			$this->tags
		);

		$this->add_tags(
			$container->addShared( DigitalProduct::class, DigitalProduct::class ),
			$this->tags
		);
	}
}
