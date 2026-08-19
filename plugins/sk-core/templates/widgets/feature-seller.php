<?php
/**
 * SK Featured Seller Widget Content Template
 *
 *
 */
?>

<?php if ( $sellers ) : ?>
    <ul class="sk-feature-sellers">
        <?php
		foreach ( $sellers as $key => $seller ) {
			$vendor = sk()->vendor->get( $seller->ID );
			$rating = $vendor->get_rating();
			$display_rating = $rating['rating'];

			if ( ! $rating['count'] ) {
				$display_rating = __( 'No ratings found yet!', 'sk-core' );
			}
			?>
                <li>
                    <a href="<?php echo $vendor->get_shop_url(); ?>">
					<?php echo esc_html( $vendor->get_shop_name() ); ?>
                    </a><br />
                    <i class='fa fa-star'></i>
				<?php echo $display_rating; ?>
                </li>

                <?php
		}
        ?>
    </ul>
<?php else : ?>
    <p><?php esc_html_e( 'No vendor found', 'sk-core' ); ?></p>
<?php endif; ?>
