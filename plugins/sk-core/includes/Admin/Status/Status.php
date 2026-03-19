<?php

namespace SK\Core\Admin\Status;

use Exception;
use SK\Core\Abstracts\StatusElement;
use SK\Core\VendorNavMenuChecker;

class Status extends StatusElement {

    protected bool $support_children = true;
    protected string $hook_key = 'sk_status';

    public function __construct() {
        parent::__construct( 'sk-status' );
    }

    /**
     * @inheritDoc
     */
    public function escape_data( string $data ): string {
        return $data;
    }

    public function render(): array {
        try {
            $this->describe();
        } catch ( Exception $e ) {
            sk_log( $e->getMessage() );
        }
        return parent::render()['children'];
    }

    /**
     * Describe the settings options.
     *
     * @return void
     * @throws Exception
     */
    public function describe() {
		//        $this->add(
		//            StatusElementFactory::heading( 'main_heading' )
		//                ->set_title( __( 'SK Status', 'sk-core' ) )
		//                ->set_description( __( 'Check the status of your SK installation.', 'sk-core' ) )
		//        );

		//        $this->add(
		//            StatusElementFactory::section( 'overridden_features' )
		//                ->set_title( __( 'Overridden Templates', 'sk-core' ) )
		//                ->set_description( __( 'The templates currently overridden that is preventing enabling new features.', 'sk-core' ) )
		//                ->add(
		//                    StatusElementFactory::table( 'override_table' )
		//                        ->set_title( __( 'General Heading', 'sk-core' ) )
		//                        ->set_headers(
		//                            [
		//                                __( 'Template', 'sk-core' ),
		//                                __( 'Feature', 'sk-core' ),
		//                                'Action',
		//                            ]
		//                        )
		//                        ->add(
		//                            StatusElementFactory::table_row( 'override_row' )
		//                                ->add(
		//                                    StatusElementFactory::table_column( 'template' )
		//                                        ->add(
		//                                            StatusElementFactory::paragraph( 'file' )
		//                                                ->set_title( __( 'FileA.php', 'sk-core' ) )
		//                                        )
		//                                )
		//                                ->add(
		//                                    StatusElementFactory::table_column( 'action' )
		//                                        ->add(
		//                                            StatusElementFactory::button( 'action' )
		//                                                ->set_title( __( 'Remove', 'sk-core' ) )
		//                                        )
		//                                )
		//                        )
		//                )
		//        );

        do_action( 'sk_status_after_describing_elements', $this );
    }
}
