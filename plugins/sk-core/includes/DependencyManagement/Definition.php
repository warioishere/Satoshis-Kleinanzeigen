<?php
/**
 * Fluent definition wrapper for tag chaining.
 */

namespace SK\Core\DependencyManagement;

class Definition {

	private Container $container;
	private string $id;

	public function __construct( Container $container, string $id ) {
		$this->container = $container;
		$this->id        = $id;
	}

	/**
	 * Add a tag to this service definition.
	 */
	public function addTag( string $tag ): self {
		$this->container->addTag( $this->id, $tag );
		return $this;
	}
}
