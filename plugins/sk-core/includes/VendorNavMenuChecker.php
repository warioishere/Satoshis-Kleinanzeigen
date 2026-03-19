<?php

namespace SK\Core;

use Exception;
use SK\Core\Admin\Status\Status;
use SK\Core\Admin\Status\StatusElementFactory;

class VendorNavMenuChecker {

    /**
     *
     * @var array $template_dependencies List of template dependencies.
     * [ 'route' => [ ['slug' => 'template-slug', 'name' => 'template-name' (Optional), 'args' = [] (Optional)  ] ] ]
     */
    protected array $template_dependencies = [];


    /**
     * Forcefully resolved dependencies.
     *
     * Using `sk_is_dashboard_nav_dependency_resolved` filter hook.
     *
     *
     * @var array $forcefully_resolved_dependencies List of forcefully resolved dependencies.
     */
    protected array $forcefully_resolved_dependencies = [];

    /**
     * Constructor.
     */

    public function __construct() {
        add_filter( 'sk_get_dashboard_nav', [ $this, 'convert_to_react_menu' ], 999 );
        add_filter( 'sk_admin_notices', [ $this, 'display_notice' ] );
        add_action( 'sk_status_after_describing_elements', [ $this, 'add_status_section' ] );
    }

    /**
     * Get template dependencies.
     *
     *
     * @return array
     */
    public function get_template_dependencies(): array {
        return apply_filters( 'sk_get_dashboard_nav_template_dependency', $this->template_dependencies );
    }

    /**
     * Convert menu items to react menu items
     *
     *
     * @param array $menu_items Menu items.
     *
     * @return array
     */

    public function convert_to_react_menu( array $menu_items ): array {
        return array_map(
            function ( $item ) {
                if ( isset( $item['submenu'] ) ) {
                    $item['submenu'] = $this->convert_to_react_menu( $item['submenu'] );
                }

                return $item;
            }, $menu_items
        );
    }

    /**
     * Check if the dependency is cleared or not.
     *
     *
     * @param string $route Route.
     *
     * @return bool
     */
    protected function is_dependency_resolved( string $route ): bool {
        $clear        = true;
        $dependencies = $this->get_template_dependencies_resolutions();

        if ( ! empty( $dependencies[ trim( $route, '/' ) ] ) ) {
            $clear = false;
        }

        $filtered_clear = apply_filters( 'sk_is_dashboard_nav_dependency_resolved', $clear, $route );

        if ( $clear !== $filtered_clear ) {
            $this->forcefully_resolved_dependencies[ $route ] = $filtered_clear;
        }

        return $filtered_clear;
    }

    /**
     * List forcefully resolved dependencies.
     *
     *
     * @return array
     */
    public function list_force_dependency_resolved_alteration(): array {
        // Forcefully rebuild dependencies resolutions.
        sk_get_dashboard_nav();

        return $this->forcefully_resolved_dependencies;
    }

    /**
     * Get URL for the route.
     *
     *
     * @param string $route Route.
     *
     * @return string
     */
    protected function get_url_for_route( string $route ): string {
        return sk_get_navigation_url( 'new' ) . '#' . trim( $route, '/' );
    }

    /**
     * Get template dependencies resolutions.
     *
     *
     * @return array
     */
    protected function get_template_dependencies_resolutions(): array {
        $dependencies = $this->get_template_dependencies();

        $resolved_dependencies = array_map(
            fn( $dependency_array ): array => array_filter(
                array_map(
                    fn( $dependency ) => $this->get_overridden_template(
                        $dependency['slug'],
                        $dependency['name'] ?? '',
                        $dependency['args'] ?? []
                    ),
                    $dependency_array
                )
            ),
            $dependencies
        );

        return apply_filters( 'sk_get_dashboard_nav_template_dependency_resolutions', $resolved_dependencies );
    }

    /**
     * Get overridden template part path.
     *
     *
     * @param string $slug Template slug.
     * @param string $name Template name.
     * @param array $args Arguments.
     *
     * @return false|string Returns the template file if found otherwise false.
     */
    protected function get_overridden_template( string $slug, string $name = '', array $args = [] ) {
        $defaults         = [ 'pro' => false ];
        $args             = wp_parse_args( $args, $defaults );
        $template         = '';
        $default_template = '';

        // Look in yourtheme/sk/slug-name.php and yourtheme/sk/slug.php
        $template_path = ! empty( $name ) ? "{$slug}-{$name}.php" : "{$slug}.php";
        $template      = locate_template( [ sk()->template_path() . $template_path ] );

        /**
         * Change template directory path filter
         *
         */
        $template_path = apply_filters( 'sk_set_template_path', sk()->plugin_path() . '/templates', $template, $args );

        // Get default slug-name.php
        if ( ! $template && $name && file_exists( $template_path . "/{$slug}-{$name}.php" ) ) {
            $template         = $template_path . "/{$slug}-{$name}.php";
            $default_template = $template;
        }

        if ( ! $template && ! $name && file_exists( $template_path . "/{$slug}.php" ) ) {
            $template         = $template_path . "/{$slug}.php";
            $default_template = $template;
        }

        // Allow 3rd party plugin filter template file from their plugin
        $template = apply_filters( 'sk_get_template_part', $template, $slug, $name );

        return $template && $default_template !== $template ? $template : false;
    }

    /**
     * List overridden templates.
     *
     *
     * @return array
     */
    public function list_overridden_templates(): array {
        $dependencies = $this->get_template_dependencies_resolutions();
        $overridden_templates = [];
        foreach ( $dependencies as $dependency ) {
            $overridden_templates = array_merge( $overridden_templates, $dependency );
        }

        return $overridden_templates;
    }

    /**
     * Display notice if templates are overridden.
     *
     *
     * @param array $notices Notices.
     *
     * @return array
     */
    public function display_notice( array $notices ): array {
        return $notices;
    }

    /**
     * Add template dependencies to status page.
     *
     *
     * @return void
     * @throws Exception
     */
    public function add_status_section( Status $status ) {
        $overridden_templates = $this->list_overridden_templates();
        $overridden_routes = $this->list_force_dependency_resolved_alteration();

        if ( empty( $overridden_templates ) && empty( $overridden_routes ) ) {
            return;
        }

        if ( ! empty( $overridden_templates ) ) {
            $template_table = StatusElementFactory::table( 'override_templates_table' )
                ->set_title( __( 'Overridden Template Table', 'sk-core' ) )
                ->set_headers(
                    [
                        __( 'Template', 'sk-core' ),
                    ]
                );

            foreach ( $overridden_templates as $id => $template ) {
                $template_table->add(
                    StatusElementFactory::table_row( 'override_row_' . $id )
                        ->add(
                            StatusElementFactory::table_column( 'template_' . $id )
                                ->add(
                                    StatusElementFactory::paragraph( 'file_location_' . $id )
                                        ->set_title( '<code>' . $template . '</code>' )
                                )
                                ->add(
                                    StatusElementFactory::paragraph( 'file_location_' . $id . '_instruction' )
                                        ->set_title( __( 'Please Remove the above file to enable new features.', 'sk-core' ) )
                                )
                        )
                );
            }
        }

        if ( ! empty( $overridden_routes ) ) {
            $route_table = StatusElementFactory::table( 'override_features_table' )
                ->set_title( __( 'Overridden Template Table', 'sk-core' ) )
                ->set_headers(
                    [
                        __( 'Route', 'sk-core' ),
                        __( 'Override Status', 'sk-core' ),
                    ]
                );

            foreach ( $overridden_routes as $route => $clearance ) {
                $route_table->add(
                    StatusElementFactory::table_row( 'override_feature_row_' . $route )
                        ->add(
                            StatusElementFactory::table_column( 'route_coll_' . $route )
                                ->add(
                                    StatusElementFactory::paragraph( 'route_' . $route )
                                        ->set_title( '<code>' . $route . '</code>' )
                                )
                        )
                        ->add(
                            StatusElementFactory::table_column( 'status_coll_' . $route )
                                ->add(
                                    StatusElementFactory::paragraph( 'status_' . $route )
                                        ->set_title( $clearance ? __( 'Forcefully enabled new feature.', 'sk-core' ) : __( 'Forcefully disabled new feature.', 'sk-core' ) )
                                )
                        )
                );
            }
        }

        $section = StatusElementFactory::section( 'overridden_features' )
            ->set_title( __( 'Overridden Templates or Routes', 'sk-core' ) )
            ->set_description( __( 'The listed templates or vendor dashboard routes are currently overridden, which are preventing enabling new features.', 'sk-core' ) );

        if ( ! empty( $overridden_templates ) ) {
            $section->add( $template_table );
        }

        if ( ! empty( $overridden_routes ) ) {
            $section->add( $route_table );
        }

        $status->add(
            $section
        );
    }
}
