<?php

namespace SK\Modules\Subscription;

use SK\Core\Traits\Singleton;
use SK\Modules\Subscription\Helper;
use SK\Modules\Subscription\SubscriptionPack;

/**
* Description of Pack_On_Registration
*
* Show dropdown of Subscription packs on Registration form
*
* @author SK
*
*/
class Registration {
    use Singleton;

    /**
     * Boot method
     *
     * @return void
     */
    public function boot() {
        $this->init_hooks();
    }

    /**
     * Init hooks and filters
     *
     * @return void
     */
    function init_hooks() {
        add_action( 'sk_seller_registration_field_after', array( $this, 'generate_form_fields' ) );
        add_action( 'sk_after_seller_migration_fields', array( $this, 'generate_form_fields') );
        add_filter( 'woocommerce_registration_redirect', array( $this, 'redirect_to_checkout' ), 99, 1 );
        add_filter( 'sk_customer_migration_required_fields', array( $this, 'add_subscription_to_sk_customer_migration_required_fields' ) );
        add_filter( 'sk_customer_migration_redirect', array( $this, 'redirect_after_migration' ) );
        add_action( 'woocommerce_thankyou', array( $this, 'redirect_to_seller_setup_wizard_after_checkout' ) );
        add_action( 'sk_seller_wizard_introduction', array( $this, 'make_vendor_has_seen_setup_wizard' ) );
    }

    /**
     * Generate select options and details for created subscription packs
     *
     *
     */
    public function generate_form_fields() {
        $subscription_packs         = sk()->subscription->all();
        $available_recurring_period = Helper::get_subscription_period_strings();

        $packs = $subscription_packs->get_posts();

        //if packs not empty show dropdown
        if ( empty( $packs ) ) {
            return;
        }
        ?>
        <label for="sk-subscription-pack"><?php _e( 'Choose Subscription Pack', 'sk' ) ?><span class="required"> *</span></label>
        <div class="form-row form-group form-row-wide dps-pack-wrappper" style="border: 1px solid #D3CED2;">

            <select required="required" class="sk-form-control" name="sk-subscription-pack" id="sk-subscription-pack">
                <?php
                while ( $subscription_packs->have_posts() ) {
                    $subscription_packs->the_post();
                    ?>
                    <option value="<?php echo get_the_ID() ?>"><?php echo the_title() ?></option>
                    <?php
                }
                ?>
            </select>
            <?php
            while ( $subscription_packs->have_posts() ) {
                $subscription_packs->the_post();

                // get individual subscriptoin pack details
                $sub_pack           = sk()->subscription->get( get_the_ID() );
                $is_recurring       = $sub_pack->is_recurring();
                $recurring_interval = $sub_pack->get_recurring_interval();
                $recurring_period   = $sub_pack->get_period_type();
                ?>

                <div class="dps-pack dps-pack-<?php echo get_the_ID() ?>">
                    <div class="dps-pack-price">

                        <span class="dps-amount">
                            <i>
                                <?php
                                    if ( $sub_pack->get_price() <= 0 ) {
                                        esc_html_e( 'Free', 'sk' );
                                    } else {
                                        echo wc_price( $sub_pack->get_price() );
                                    }
                                ?>
                            </i>
                        </span>

                        <?php if ( $is_recurring && $recurring_interval === 1 ) { ?>
                            <span class="dps-rec-period">
                                <span class="sep">/</span><?php echo isset( $available_recurring_period[$recurring_period] ) ? $available_recurring_period[$recurring_period] : ''; ?>
                            </span>
                        <?php } ?>
                    </div><!-- .pack_price -->

                    <div class="pack_content">
                        <b><?php the_title(); ?></b>

                        <?php the_content(); ?>

                        <?php if ( $is_recurring && $recurring_interval > 1 ) { ?>
                            <span class="dps-rec-period">
                                <i>
                                    <?php printf( __( 'In every %d %s(s)', 'sk' ), $recurring_interval, $recurring_period ); ?>
                                </i>
                            </span>
                        <?php } ?>
                    </div>
                </div>
                <?php
            }
            ?>

        </div>
            <?php
            wp_reset_query();
        }

    /**
     * Redirect users to checkout directly with selected
     * subscription added in cart
     *
     * @param string redirect_url
     *
     * @return string redirect_url
     */
    public function redirect_to_checkout( $redirect_url ) {

        if ( current_user_can( 'skdar' ) && Helper::is_subscription_enabled_on_registration() ) {

            if ( ! isset( $_POST['sk-subscription-pack'] ) ) {
                return $redirect_url;
            }

            return get_site_url() . '/?add-to-cart=' . $_POST['sk-subscription-pack'];
        }

        return $redirect_url;
    }


    /**
    * Check if subscriptin pack is selected
    * @param array $fields
    * @return array $fields
    */
    public function add_subscription_to_sk_customer_migration_required_fields( $fields ) {
        // check if subscription is enabled on registration
        if ( ! Helper::is_subscription_enabled_on_registration() ) {
            return $fields;
        }

        // check if subscription pack is available
        if ( ! Helper::is_subscription_pack_available() ) {
            return $fields;
        }

        $fields['sk-subscription-pack'] = __( 'Select subscription a pack', 'sk' );

        return $fields;
    }

    /**
    * Redirect after migration
    * @param string $url
    * @return string
    */
    public function redirect_after_migration( $url ) {
        if ( isset( $_POST['sk-subscription-pack'] ) ) {
            return get_site_url() . '/?add-to-cart=' . $_POST['sk-subscription-pack'];
        }

        return $url;
    }

    /**
     * Get subscription pack id
     *
     * @return string
     */
    public function redirect_to_seller_setup_wizard_after_checkout( $order_id ) {
        $order = wc_get_order( $order_id );
        $items = $order->get_items( 'line_item' );

        if ( empty( $items ) || ! is_array( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
            $product_id = $item->get_product_id();
            break;
        }

        if ( ! $product_id ) {
            return;
        }

        if ( ! Helper::is_subscription_product( $product_id ) ) {
            return;
        }

        $redirect_url             = get_site_url() . '/?page=sk-seller-setup';
        $is_setup_wizard_disabled = 'on' === sk_get_option( 'disable_welcome_wizard', 'sk_selling', 'off' );

        if ( $is_setup_wizard_disabled ) {
            return;
        }

        $user_id = sk_get_current_user_id();
        if ( empty( $user_id ) ) {
            return;
        }

        if ( ! sk_is_user_seller( $user_id ) ) {
            return;
        }

        if ( $this->vendor_has_seen_setup_wizard( $user_id ) ) {
            return;
        }

        ?>
        <script>
            jQuery(document).ready(function() {
                setTimeout(function(){
                    window.location.replace("<?php echo $redirect_url; ?>");
                }, 3000);
            });
        </script>
        <?php
    }

    /**
     * Vendor has seen setup wizard
     *
     *
     * @return void
     */
    public function make_vendor_has_seen_setup_wizard( $store ) {
        $vendor_id = $store->store_id;

        if ( ! $vendor_id ) {
            return;
        }

        update_user_meta( $vendor_id, 'sk_vendor_seen_setup_wizard', true );
    }

    /**
     * Check whether vendor has seen setup wizard or not
     *
     *
     * @param int $vendor_id
     *
     * @return boolean
     */
    public function vendor_has_seen_setup_wizard( $vendor_id = null ) {
        if ( empty( $vendor_id ) ) {
            $vendor_id = sk_get_current_user_id();
        }

        return wc_string_to_bool( get_user_meta( $vendor_id, 'sk_vendor_seen_setup_wizard', true ) );
    }
}

$dps_enable                 = Helper::is_subscription_module_enabled();
$dps_enable_in_registration = Helper::is_subscription_enabled_on_registration();

if ( $dps_enable && $dps_enable_in_registration ) {
    Registration::instance();
}
