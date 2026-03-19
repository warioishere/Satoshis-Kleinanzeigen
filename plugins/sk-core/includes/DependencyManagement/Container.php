<?php
/**
 * Simple DI Container — replaces League Container.
 *
 * Supports: addShared/add, get (single + tag resolution), has, addServiceProvider, addTag.
 */

namespace SK\Core\DependencyManagement;

class Container {

	/** @var array<string, mixed> class name or callable */
	private array $factories = [];

	/** @var array<string, object> resolved singletons */
	private array $instances = [];

	/** @var array<string, string[]> tag => [service IDs] */
	private array $tags = [];

	/**
	 * Register a shared (singleton) service.
	 */
	public function addShared( string $id, $concrete = null ): Definition {
		$this->factories[ $id ] = $concrete ?? $id;
		return new Definition( $this, $id );
	}

	/**
	 * Alias for addShared — all services are treated as singletons.
	 */
	public function add( string $id, $concrete = null ): Definition {
		return $this->addShared( $id, $concrete );
	}

	/**
	 * Resolve a service by ID, or all services by tag.
	 *
	 * @param string $id Service ID or tag name.
	 * @return mixed
	 */
	public function get( string $id ) {
		// Tag resolution — return array of resolved instances.
		if ( isset( $this->tags[ $id ] ) ) {
			return array_map( fn( $serviceId ) => $this->get( $serviceId ), $this->tags[ $id ] );
		}

		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \RuntimeException( "Service '{$id}' not registered in container." );
		}

		$concrete = $this->factories[ $id ];
		$this->instances[ $id ] = is_string( $concrete ) ? new $concrete() : $concrete;

		return $this->instances[ $id ];
	}

	/**
	 * Check whether a service or tag is registered.
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || isset( $this->instances[ $id ] ) || isset( $this->tags[ $id ] );
	}

	/**
	 * Tag a service ID with a given tag name.
	 */
	public function addTag( string $serviceId, string $tag ): void {
		$this->tags[ $tag ][] = $serviceId;
	}

	/**
	 * Register and boot a service provider.
	 */
	public function addServiceProvider( object $provider ): void {
		$provider->setContainer( $this );
		$provider->register();
		if ( method_exists( $provider, 'boot' ) ) {
			$provider->boot();
		}
	}
}
