<?php

namespace SK\Core\REST;

use SK\Core\Traits\VendorAuthorizable;

/**
 * Vendor REST Controller for SK
 *
 *
 */
abstract class SkBaseVendorController extends SkBaseController {
    use VendorAuthorizable;

    /**
     * Endpoint base.
     *
     * @var string
     */
    protected $rest_base = 'vendor';
}
