<?php

namespace SK\Core\DependencyManagement\Providers;

use SK\Core\DependencyManagement\BaseServiceProvider;
use SK\Core\Ajax;

class AjaxServiceProvider extends BaseServiceProvider {

	protected array $tags = [ 'ajax-service' ];

	protected array $services = [
		Ajax::class,
	];

	public function register(): void {
		$this->add_tags(
			$this->getContainer()->addShared( Ajax::class ),
			$this->tags
		);
	}
}
