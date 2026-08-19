<?php
/**
 * Plugin Name: SK Checkout Simplify
 * Description: Makes checkout fields optional for SK product advertisements and subscription packs - only first name required
 * Version: 1.0.0
 * Author: Satoshi's Kleinanzeigen
 */

if (!defined('ABSPATH')) exit;

class SK_Checkout_Simplify {

    /**
     * Initialize the plugin
     */
    public function __construct() {
        // Make fields optional at multiple levels to ensure it works
        add_filter('woocommerce_checkout_fields', [$this, 'override_checkout_fields'], 9999);
        add_filter('woocommerce_billing_fields', [$this, 'override_billing_fields'], 9999);
        add_filter('woocommerce_default_address_fields', [$this, 'override_default_address_fields'], 9999);
        add_filter('woocommerce_form_field_args', [$this, 'make_fields_optional'], 9999, 3);

        // Auto-fill empty optional fields with dummy data to prevent validation errors
        add_filter('woocommerce_checkout_posted_data', [$this, 'autofill_optional_fields'], 10);

        // Remove validate-required class from HTML for better UX
        add_filter('woocommerce_form_field', [$this, 'remove_required_class'], 9999, 4);

        // Override WooCommerce validation
        add_action('woocommerce_after_checkout_validation', [$this, 'override_validation'], 10, 2);
    }

    /**
     * Check if cart contains an SK product advertisement
     *
     * @return bool
     */
    private function has_sk_product_advertisement() {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        // Check for product advertisement using the SK helper if available
        if (class_exists('\SK\Modules\ProductAdvertisement\Helper')) {
            return \SK\Modules\ProductAdvertisement\Helper::has_product_advertisement_in_cart();
        }

        // Fallback: Check cart items manually
        foreach (WC()->cart->get_cart() as $item) {
            if (isset($item['sk_product_advertisement'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if cart contains an SK subscription pack
     *
     * @return bool
     */
    private function has_sk_subscription_pack() {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $item) {
            $product = $item['data'];

            // Check if product type is 'product_pack' (SK subscription)
            if ($product && method_exists($product, 'get_type') && $product->get_type() === 'product_pack') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if we should simplify checkout
     *
     * @return bool
     */
    private function should_simplify_checkout() {
        return $this->has_sk_product_advertisement() || $this->has_sk_subscription_pack();
    }

    /**
     * Override checkout fields
     *
     * @param array $fields Checkout fields
     * @return array
     */
    public function override_checkout_fields($fields) {
        if (!$this->should_simplify_checkout()) {
            return $fields;
        }

        $required_fields = ['billing_first_name'];

        if (isset($fields['billing'])) {
            foreach ($fields['billing'] as $key => $field) {
                if (!in_array($key, $required_fields, true)) {
                    $fields['billing'][$key]['required'] = false;
                }
            }
        }

        return $fields;
    }

    /**
     * Override billing fields
     *
     * @param array $fields Billing fields
     * @return array
     */
    public function override_billing_fields($fields) {
        if (!$this->should_simplify_checkout()) {
            return $fields;
        }

        $required_fields = ['billing_first_name'];

        foreach ($fields as $key => $field) {
            if (!in_array($key, $required_fields, true)) {
                $fields[$key]['required'] = false;
            }
        }

        return $fields;
    }

    /**
     * Override default address fields
     *
     * @param array $fields Address fields
     * @return array
     */
    public function override_default_address_fields($fields) {
        if (!$this->should_simplify_checkout()) {
            return $fields;
        }

        // Make all default address fields optional
        $optional_address_fields = ['last_name', 'address_1', 'address_2', 'city', 'postcode', 'state', 'country'];

        foreach ($optional_address_fields as $field_key) {
            if (isset($fields[$field_key])) {
                $fields[$field_key]['required'] = false;
            }
        }

        return $fields;
    }

    /**
     * Make billing fields optional except first name for SK products
     *
     * @param array $args Field arguments
     * @param string $key Field key
     * @param mixed $value Field value
     * @return array
     */
    public function make_fields_optional($args, $key, $value) {
        // Only apply to checkout page for SK products
        if (!is_checkout() || !$this->should_simplify_checkout()) {
            return $args;
        }

        // Only first name is required
        $required_fields = ['billing_first_name'];

        if (!in_array($key, $required_fields, true)) {
            $args['required'] = false;
        }

        return $args;
    }

    /**
     * Auto-fill empty optional fields with dummy data
     * This prevents WooCommerce validation errors
     *
     * @param array $data Posted checkout data
     * @return array
     */
    public function autofill_optional_fields($data) {
        // Only apply for SK products
        if (!$this->should_simplify_checkout()) {
            return $data;
        }

        // Define dummy values for optional fields (using valid Swiss data)
        $optional_fields = [
            'billing_last_name' => 'N/A',
            'billing_address_1' => 'N/A',
            'billing_city' => 'Zürich',
            'billing_postcode' => '8000',
            'billing_country' => 'CH',
            'billing_email' => isset($data['billing_email']) && !empty($data['billing_email'])
                ? $data['billing_email']
                : 'noemail@example.com',
            'billing_phone' => '0000000000'
        ];

        foreach ($optional_fields as $field => $default) {
            if (empty($data[$field])) {
                $data[$field] = $default;
            }
        }

        return $data;
    }

    /**
     * Remove "validate-required" class from non-required fields
     *
     * @param string $field_html Field HTML
     * @param string $field_key Field key
     * @param array $field_data Field data
     * @param mixed $checkout Checkout object
     * @return string
     */
    public function remove_required_class($field_html, $field_key, $field_data, $checkout) {
        // Only apply to checkout page for SK products
        if (!is_checkout() || !$this->should_simplify_checkout()) {
            return $field_html;
        }

        // Only first name is required
        $required_fields = ['billing_first_name'];

        if (!in_array($field_key, $required_fields, true)) {
            // Remove "validate-required" class from non-required fields
            $field_html = str_replace('validate-required', '', $field_html);
        }

        return $field_html;
    }

    /**
     * Override WooCommerce validation errors for optional fields
     *
     * @param array $data Posted data
     * @param WP_Error $errors Validation errors
     * @return void
     */
    public function override_validation($data, $errors) {
        if (!$this->should_simplify_checkout()) {
            return;
        }

        // Remove validation errors for optional fields
        $optional_fields = [
            'billing_last_name',
            'billing_address_1',
            'billing_city',
            'billing_postcode',
            'billing_country',
            'billing_state'
        ];

        foreach ($optional_fields as $field) {
            // Remove any errors related to these fields
            $errors->remove($field);
            $errors->remove('billing_' . $field);
        }
    }
}

// Initialize the plugin
new SK_Checkout_Simplify();
