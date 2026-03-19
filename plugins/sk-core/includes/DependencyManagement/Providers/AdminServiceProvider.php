<?php

namespace SK\Core\DependencyManagement\Providers;

use SK\Core\DependencyManagement\BaseServiceProvider;

class AdminServiceProvider extends BaseServiceProvider {

	public const TAG = 'admin-service';

	public function register(): void {
		$this->getContainer()
			->addShared( \SK\Core\Admin\Hooks::class, \SK\Core\Admin\Hooks::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\Menu::class, \SK\Core\Admin\Menu::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\AdminBar::class, \SK\Core\Admin\AdminBar::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\Pointers::class, \SK\Core\Admin\Pointers::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\Settings::class, \SK\Core\Admin\Settings::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\UserProfile::class, \SK\Core\Admin\UserProfile::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\UserList::class, \SK\Core\Admin\UserList::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\Status\Status::class, \SK\Core\Admin\Status\Status::class )
			->addTag( self::TAG );

		$this->getContainer()
			->addShared( \SK\Core\Admin\PhpDashboard::class, \SK\Core\Admin\PhpDashboard::class )
			->addTag( self::TAG )
			->addTag( \SK\Core\Contracts\Hookable::class );
	}
}
