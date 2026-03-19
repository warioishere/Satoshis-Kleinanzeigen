<div class="sk-download-options sk-edit-row sk-clearfix <?php echo esc_attr( $class ); ?>">
    <div class="sk-section-heading" data-togglehandler="sk_download_options">
        <h2><i class="fas fa-download" aria-hidden="true"></i> <?php esc_html_e( 'Downloadable Options', 'sk-core' ); ?></h2>
        <p><?php esc_html_e( 'Configure your downloadable product settings', 'sk-core' ); ?></p>
        <a href="#" class="sk-section-toggle">
            <i class="fas fa-sort-down fa-flip-vertical" aria-hidden="true"></i>
        </a>
        <div class="sk-clearfix"></div>
    </div>

    <div class="sk-section-content">
        <div class="sk-divider-top sk-clearfix">

            <?php do_action( 'sk_product_edit_before_sidebar' ); ?>

            <div class="sk-side-body sk-download-wrapper">
                <table class="sk-table">
                    <tfoot>
                        <tr>
                            <th colspan="3">
                                <?php
                                $file = [
                                    'file' => '',
                                    'name' => '',
                                ];
                                ob_start();
                                require SK_CORE_INC_DIR . '/woo-views/html-product-download.php';
                                $row_html = ob_get_clean();
                                ?>
                                <a href="#" class="insert-file-row sk-btn sk-btn-sm sk-btn-success" data-row="<?php echo esc_attr( $row_html ); ?>">
                                    <?php esc_html_e( 'Add File', 'sk-core' ); ?>
                                </a>
                            </th>
                        </tr>
                    </tfoot>
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'sk-core' ); ?> <span class="tips" title="<?php esc_attr_e( 'This is the name of the download shown to the customer.', 'sk-core' ); ?>">[?]</span></th>
                            <th><?php esc_html_e( 'File URL', 'sk-core' ); ?> <span class="tips" title="<?php esc_attr_e( 'This is the URL or absolute path to the file which customers will get access to.', 'sk-core' ); ?>">[?]</span></th>
                            <th><?php esc_html_e( 'Action', 'sk-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $downloadable_files = get_post_meta( $post_id, '_downloadable_files', true );

                        if ( $downloadable_files ) {
                            foreach ( $downloadable_files as $key => $file ) {
                                include SK_CORE_INC_DIR . '/woo-views/html-product-download.php';
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <div class="sk-clearfix">
                    <div class="content-half-part">
                        <label for="_download_limit" class="form-label"><?php esc_html_e( 'Download Limit', 'sk-core' ); ?></label>
                        <?php sk_post_input_box( $post_id, '_download_limit', array( 'placeholder' => __( 'Unlimited', 'sk-core' ) ) ); ?>
                    </div><!-- .content-half-part -->

                    <div class="content-half-part">
                        <label for="_download_expiry" class="form-label"><?php esc_html_e( 'Download Expiry', 'sk-core' ); ?></label>
                        <?php sk_post_input_box( $post_id, '_download_expiry', array( 'placeholder' => __( 'Never', 'sk-core' ) ) ); ?>
                    </div><!-- .content-half-part -->
                </div>

            </div> <!-- .sk-side-body -->
        </div> <!-- .downloadable -->
    </div>
</div>
