<?php

namespace SK\Core\DependencyManagement\Providers;

use SK\Core\DependencyManagement\BootableServiceProvider;

class ServiceProvider extends BootableServiceProvider {

	public const TAG = 'container-service';

	protected array $services = [
		'product_block'       => \SK\Core\Blocks\ProductBlock::class,
		'pageview'            => \SK\Core\PageViews::class,
		'core'                => \SK\Core\Core::class,
		'scripts'             => \SK\Core\Assets::class,
		'email'               => \SK\Core\Emails\Manager::class,
		'vendor'              => \SK\Core\Vendor\Manager::class,
		'product'             => \SK\Core\Product\Manager::class,
		'shortcodes'          => \SK\Core\Shortcodes\Shortcodes::class,
		'registration'        => \SK\Core\Registration::class,
		'api'                 => \SK\Core\REST\Manager::class,
		'dashboard'           => \SK\Core\Dashboard\Manager::class,
		'customizer'          => \SK\Core\Customizer::class,
		'product_sections'    => \SK\Core\ProductSections\Manager::class,
		'catalog_mode'        => \SK\Core\CatalogMode\Controller::class,
		'bg_process'          => \SK\Core\BackgroundProcess\Manager::class,
		'frontend_manager'    => \SK\Core\Frontend\Frontend::class,
		'rewrite'             => \SK\Core\Rewrites::class,
		'widgets'             => \SK\Core\Widgets\Manager::class,
	];

	public function boot(): void {
		$this->getContainer()->addServiceProvider( new AdminServiceProvider() );
		$this->getContainer()->addServiceProvider( new CommonServiceProvider() );
		$this->getContainer()->addServiceProvider( new FrontendServiceProvider() );
		$this->getContainer()->addServiceProvider( new AjaxServiceProvider() );
		$this->getContainer()->addServiceProvider( new UtilsServiceProvider() );
	}

	public function register(): void {
		foreach ( $this->services as $key => $class_name ) {
			$this->getContainer()->addShared( $key, $class_name )->addTag( self::TAG );
		}
	}
}
