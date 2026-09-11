<?php
$store_user    = sk()->vendor->get( get_query_var( 'author' ) );
$store_info    = $store_user->get_shop_info();
$social_info   = $store_user->get_social_profiles();
$store_tabs    = sk_get_store_tabs( $store_user->get_id() );
$social_fields = sk_get_social_profile_fields();

$store_address    = sk_get_seller_short_address( $store_user->get_id(), false );

$general_settings = get_option( 'sk_general', [] );
$banner_width     = sk_get_vendor_store_banner_width();

?>
<div class="sk-profile-frame-wrapper">
    <div class="profile-frame">

        <div class="profile-info-box profile-layout-default">
            <?php if ( $store_user->get_banner() ) { ?>
                <img src="<?php echo esc_url( $store_user->get_banner() ); ?>"
                    alt="<?php echo esc_attr( $store_user->get_shop_name() ); ?>"
                    title="<?php echo esc_attr( $store_user->get_shop_name() ); ?>"
                    class="profile-info-img">
            <?php } else { ?>
                <div class="profile-info-img dummy-image">&nbsp;</div>
            <?php } ?>

            <div class="profile-info-summery-wrapper sk-clearfix">
                <div class="profile-info-summery">
                    <div class="profile-info-head">
                        <div class="profile-img profile-img-circle">
                            <img src="<?php echo esc_url( $store_user->get_avatar() ); ?>"
                                alt="<?php echo esc_attr( $store_user->get_shop_name() ); ?>"
                                size="150">
                        </div>
                        <?php if ( ! empty( $store_user->get_shop_name() ) ) { ?>
                            <h1 class="store-name">
                                <?php echo esc_html( $store_user->get_shop_name() ); ?>
                                <?php
                                // Inline instead of via a hook: the badge
                                // belongs next to the name, not in an
                                // extension point.
                                if ( function_exists( 'sk_verified_badge' ) ) {
                                    echo sk_verified_badge( $store_user->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput
                                }
                                ?>
                                <?php do_action( 'sk_store_header_after_store_name', $store_user ); ?>
                            </h1>
                        <?php } ?>
                    </div>

                    <div class="profile-info">
                        <ul class="sk-store-info">
                            <?php if ( ! empty( $store_address ) ) { ?>
                                <li class="sk-store-address"><i class="fas fa-map-marker-alt"></i>
                                    <?php echo wp_kses_post( $store_address ); ?>
                                </li>
                            <?php } ?>

                            <?php
                            /*
                             * The email address used to sit here as a mailto
                             * link in the source. antispambot() only encodes
                             * a few characters as HTML entities — any scraper
                             * decodes that in one line. It now appears below
                             * the banner in the contact list and is only
                             * loaded on click.
                             */
                            ?>

                            <li class="sk-store-rating">
                                <i class="fas fa-star"></i>
                                <?php echo wp_kses_post( sk_get_readable_seller_rating( $store_user->get_id() ) ); ?>
                            </li>

                            <?php
                            // Trust strip: one <li> per signal the vendor
                            // has (zaps received, later the social graph
                            // line and Lightning proofs). Nothing for a
                            // vendor without signals.
                            \SK\Core\Trust\TrustSignals::render( (int) $store_user->get_id(), \SK\Core\Trust\TrustSignals::CONTEXT_STORE );
                            ?>
                        </ul>

                        <?php if ( $social_fields ) { ?>
                            <div class="store-social-wrapper">
                                <ul class="store-social">
                                    <?php foreach ( $social_fields as $key => $field ) { ?>
                                        <?php if ( ! empty( $social_info[ $key ] ) ) { ?>
                                            <li>
                                                <a href="<?php echo esc_url( $social_info[ $key ] ); ?>" target="_blank"><i class="fab fa-<?php echo esc_attr( $field['icon'] ); ?>"></i></a>
                                            </li>
                                        <?php } ?>
                                    <?php } ?>
                                </ul>
                            </div>
                        <?php } ?>

                    </div> <!-- .profile-info -->
                </div><!-- .profile-info-summery -->
            </div><!-- .profile-info-summery-wrapper -->
        </div> <!-- .profile-info-box -->
    </div> <!-- .profile-frame -->

    <?php
    // Contact methods below the banner instead of inside it: in the header
    // they sat between address and rating and got lost as soon as the
    // vendor offered more than one method.
    $sk_contacts = \SK\Core\Dashboard\Modules\ContactDetails::contact_list_html( $store_user->get_id() );
    if ( $sk_contacts !== '' ) {
        echo '<div class="sk-store-contacts">' . $sk_contacts . '</div>'; // phpcs:ignore
    }
    ?>

    <?php if ( $store_tabs ) { ?>
        <div class="sk-store-tabs">
            <ul class="sk-modules-button">
                <?php do_action( 'sk_after_store_tabs', $store_user->get_id() ); ?>
            </ul>
            <ul class="sk-list-inline">
                <?php
                $current_url = trailingslashit( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ) );
                foreach ( $store_tabs as $key => $tab ) {
                    if ( ! $tab['url'] ) {
                        continue;
                    }
                    $tab_path  = trailingslashit( wp_parse_url( $tab['url'], PHP_URL_PATH ) ?: '' );
                    $is_active = ( $tab_path === $current_url );
                    ?>
                    <li<?php echo $is_active ? ' class="active"' : ''; ?>><a href="<?php echo esc_url( $tab['url'] ); ?>"><?php echo esc_html( $tab['title'] ); ?></a></li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>
</div>
