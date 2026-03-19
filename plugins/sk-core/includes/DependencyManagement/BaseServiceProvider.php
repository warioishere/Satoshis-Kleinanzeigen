<?php
/**
 * Base class for service providers.
 */

namespace SK\Core\DependencyManagement;

abstract class BaseServiceProvider {

	protected Container $container;
	protected array $services = [];
	protected array $tags = [];

	public function setContainer( Container $container ): void {
		$this->container = $container;
	}

	public function getContainer(): Container {
		return $this->container;
	}

	abstract public function register(): void;

	/**
	 * Register a shared service and tag it with all interfaces it implements.
	 */
	protected function share_with_implements_tags( string $id, $concrete = null ): Definition {
		$definition = $this->container->addShared( $id, $concrete );

		if ( class_exists( $id ) ) {
			foreach ( class_implements( $id ) as $interface ) {
				$definition->addTag( $interface );
			}
		}

		return $definition;
	}

	/**
	 * Add multiple tags to a definition.
	 */
	protected function add_tags( Definition $definition, array $tags ): void {
		foreach ( $tags as $tag ) {
			$definition->addTag( $tag );
		}
	}
}
