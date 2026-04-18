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

}

$dps_enable                 = Helper::is_subscription_module_enabled();
$dps_enable_in_registration = Helper::is_subscription_enabled_on_registration();

if ( $dps_enable && $dps_enable_in_registration ) {
    Registration::instance();
}
