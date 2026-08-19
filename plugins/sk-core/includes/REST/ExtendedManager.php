<?php

namespace SK\Core\REST;

class ExtendedManager {

    /**
     * Register Pro REST Controllers
     *
     *
     * @param array $class_map
     *
     * @return array
     */
    public static function register_rest_routes( $class_map ) {
        $inc = SK_CORE_INC_DIR;

        $class_map[ $inc . '/REST/StoreCategoryController.php' ]          = StoreCategoryController::class;
        $class_map[ $inc . '/REST/ReviewsController.php' ]                = ReviewsController::class;
        $class_map[ $inc . '/REST/ExtendedStoreController.php' ]               = ProStoreController::class;
        $class_map[ $inc . '/REST/ModulesController.php' ]                = ModulesController::class;
        $class_map[ $inc . '/REST/AnnouncementController.php' ]           = AnnouncementController::class;
        $class_map[ $inc . '/REST/DashboardController.php' ]              = DashboardController::class;
        $class_map[ $inc . '/REST/ExtendedProductController.php' ]             = ProProductController::class;

        $class_map[ $inc . '/REST/TaxClassesController.php' ]             = TaxClassesController::class;
        $class_map[ $inc . '/REST/TaxesController.php' ]                  = TaxesController::class;
        $class_map[ $inc . '/REST/PaymentGatewaysController.php' ]        = PaymentGatewaysController::class;

        return $class_map;
    }
}
