<?php

namespace SK\Core\DependencyManagement\Providers;

use SK\Core\DependencyManagement\BaseServiceProvider;

class UtilsServiceProvider extends BaseServiceProvider {

	protected array $tags = [ 'utils' ];

	protected array $services = [
		\SK\Core\Utilities\AdminSettings::class,
	];

	public function register(): void {
		foreach ( $this->services as $service ) {
			$definition = $this->getContainer()->add( $service );
			$this->add_tags( $definition, $this->tags );
		}
	}
}
