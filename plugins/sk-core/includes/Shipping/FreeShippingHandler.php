<?php
namespace SK\Core\Shipping;

use WC_Coupon;
use Automattic\WooCommerce\Utilities\NumberUtil;

/**
 * Manages sk free shipping.
 *
 */
class FreeShippingHandler {

    /**
     * Shipping package for a specific vendor.
     *
     * @var array
     *
     */
    protected array $shipping_package;

    /**
     * Min amount to be valid.
     *
     * @var integer
     *
     */
    protected $min_amount = 0;

    /**
     * Requires option.
     *
     * @var string
     *
     */
    protected $requires = '';

    /**
     * Ignore discounts.
     *
     * If set, free shipping would be available based on pre-discount order amount.
     *
     * @var string
     *
     */
    protected string $ignore_discounts;

    /**
     * Free shipping availability.
     *
     * @var bool Indicates if free shipping is available.
     *
     */
    protected bool $is_available = false;

    /**
     * Remaining value from free shipping.
     *
     * @var float Remaining amount to qualify for free shipping.
     *
     */
    protected float $remaining = 0.0;

    /**
     * Check coupon need for availability.
     *
     * @var bool Indicates if a coupon is needed for free shipping.
     *
     */
    protected bool $needs_coupon = false;

    /**
     * Coupon seller id.
     *
     * @var int
     *
     */
    protected int $vendor_id = 0;

    /**
     * Check the coupon scope.
     *
     * @var bool
     *
     */
    protected bool $is_admin_shipping = false;

    /**
     * Initializes the FreeShipping object.
     *
     *
     * @param \WC_Shipping_Free_Shipping|array $method
     * @param array                            $shipping_package
     *
     * @return void
     */
    public function __construct( $method, array $shipping_package ) {
        $this->shipping_package = $shipping_package;
        $this->vendor_id        = absint( $shipping_package['seller_id'] );
        // Check if $method is an instance of \WC_Shipping_Free_Shipping or comes from vendor settings.
        if ( $method instanceof \WC_Shipping_Free_Shipping ) {
            // Set properties based on the \WC_Shipping_Free_Shipping instance.
            $this->requires          = $method->requires ?? '';
            $this->min_amount        = $method->min_amount ?? 0;
            $this->ignore_discounts  = $method->ignore_discounts ?? 'yes';
            $this->is_admin_shipping = true;
        } elseif ( is_array( $method ) && ! empty( $method['settings'] ) ) {
            // Determine if discounts should be ignored based on the settings.
            $ignore_discounts = ! empty( $method['settings']['apply_before_coupon_discount'] ) && 'true' === $method['settings']['apply_before_coupon_discount'];

            // Set properties based on the vendor free shipping settings array.
            $this->requires         = $method['settings']['requires'] ?? '';
            $this->min_amount       = $method['settings']['min_amount'] ?? 0;
            $this->ignore_discounts = $ignore_discounts ? 'yes' : 'no';
        }

        // Validate free shipping eligibility based on the initialized properties.
        $this->check_free_shipping_eligibility();
    }

    /**
     * Check and set free shipping eligibility based on the conditions.
     *
     * This method checks if the current shipping package qualifies for free shipping
     * based on the set requirements (minimum amount, coupon, both, or either). It updates
     * the class properties to reflect the free shipping status, the remaining amount to
     * qualify for free shipping, and whether a coupon is needed for free shipping.
     *
     *
     * @see \WC_Discounts::set_items_from_cart()
     *
     * @return void
     */
    protected function check_free_shipping_eligibility() {
        $has_coupon         = false;
        $has_met_min_amount = false;

        // Check if a coupon is required for free shipping.
        if ( in_array( $this->requires, array( 'coupon', 'either', 'both' ), true ) ) {
            $coupons = $this->shipping_package['applied_coupons'];

            // Check each applied coupon for validity and free shipping.
            if ( $coupons ) {
                foreach ( $coupons as $code ) {
                    // Create a new coupon object from the coupon code.
                    $coupon = new WC_Coupon( $code );

                    // Check if the current context is admin shipping.
                    $discounts = new \WC_Discounts();
                    // Set the discount items for the coupon validation.
                    $discounts->set_items( $this->format_shipping_package_content_as_discount_items() );

                    try {
                        $valid = $discounts->is_coupon_valid( $coupon ); // Check if the coupon is valid.
                    } catch ( \Exception $exception ) {
                        $valid = false; // If there is an exception, set the valid flag to false.
                    }

                    // Check if the coupon is valid, not an error, and offers free shipping.
                    if ( ! is_wp_error( $valid ) && $valid && $coupon->get_free_shipping() ) {
                        $has_coupon = true; // If a valid free shipping coupon is found, set the flag and break the loop
                        break;
                    }
                }
            }
        }

        // Check if a minimum amount is required for free shipping.
        if ( in_array( $this->requires, array( 'min_amount', 'either', 'both' ), true ) ) {
            $total          = 0.0;
            $discount_total = 0.0;
            $discount_tax   = 0.0;

            // Calculate total, discount total, and discount tax based on cart contents.
            foreach ( $this->shipping_package['contents'] as $line_item ) {
                $total          += WC()->cart->display_prices_including_tax() ? ( $line_item['line_subtotal'] + $line_item['line_subtotal_tax'] ) : $line_item['line_subtotal'];
                $discount_total += ( $line_item['line_subtotal'] - $line_item['line_total'] );
                $discount_tax   += ( $line_item['line_subtotal_tax'] - $line_item['line_tax'] );
            }

            // Adjust total for tax display settings.
            if ( WC()->cart->display_prices_including_tax() ) {
                $total = $total - $discount_tax;
            }

            // Adjust total for discounts if applicable.
            if ( 'no' === $this->ignore_discounts ) {
                $total = $total - $discount_total;
            }

            // Round the total amount.
            $total = NumberUtil::round( $total, wc_get_price_decimals() );

            // Check if the total meets the minimum amount required.
            if ( $total >= $this->min_amount ) {
                $has_met_min_amount = true;
            }

            // Calculate the remaining amount needed to qualify for free shipping.
            $this->remaining = abs( $this->min_amount - $total );
        }

        // Determine if free shipping is available based on the requirements.
        switch ( $this->requires ) {
            case 'min_amount':
                $this->is_available = $has_met_min_amount;
                break;
            case 'coupon':
                $this->is_available = $has_coupon;
                $this->needs_coupon = true;
                break;
            case 'both':
                $this->is_available = $has_met_min_amount && $has_coupon;
                $this->needs_coupon = $has_met_min_amount;
                break;
            case 'either':
                $this->is_available = $has_met_min_amount || $has_coupon;
                $this->needs_coupon = $has_coupon;
                break;
            default:
                $this->is_available = true;
                break;
        }
    }

    /**
     * Formats the shipping package contents as discount items.
     *
     * This method processes the shipping package contents and formats them as discount items.
     * Each item in the shipping package is converted into an object containing key, object,
     * product, quantity, and price properties. The items are then sorted in descending order
     * based on the total price (price * quantity).
     *
     *
     * @return array An array of formatted discount items.
     */
    protected function format_shipping_package_content_as_discount_items(): array {
        $items = [];

        // Iterate over each item in the shipping package contents.
        foreach ( $this->shipping_package['contents'] as $key => $cart_item ) {
            $item           = new \stdClass();
            $item->key      = $key;
            $item->object   = $cart_item;
            $item->product  = $cart_item['data'];
            $item->quantity = $cart_item['quantity'];

            // Calculate the price with precision.
            $item->price = wc_add_number_precision_deep( (float) $item->quantity );

            // Add the formatted item to the items array.
            $items[ $key ] = $item;
        }

        // Sort items in descending order based on the total price (price * quantity).
        uasort(
            $items, function ( $a, $b ) {
				$price_1 = $a->price * $a->quantity;
				$price_2 = $b->price * $b->quantity;
				if ( $price_1 === $price_2 ) {
					return 0;
				}
				return ( $price_1 < $price_2 ) ? 1 : -1;
			}
        );

        return $items;
    }

    /**
     * Checks if free shipping is available.
     *
     *
     * @return bool True if free shipping is available, false otherwise
     */
    public function is_free_shipping_available(): bool {
        return $this->is_available;
    }

    /**
     * Retrieves the remaining amount to qualify for free shipping.
     *
     *
     * @return float The remaining amount needed to qualify for free shipping
     */
    public function get_remaining_amount(): float {
        return $this->remaining;
    }

    /**
     * Checks if a coupon is needed for free shipping.
     *
     *
     * @return bool True if a coupon is needed, false otherwise
     */
    public function needs_coupon_for_free_shipping(): bool {
        return $this->needs_coupon;
    }
}
