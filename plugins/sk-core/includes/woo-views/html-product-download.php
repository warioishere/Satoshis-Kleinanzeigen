<tr>
    <td>
        <input type="text" class="sk-form-control input_text" placeholder="<?php esc_attr_e( 'File Name', 'sk-core' ); ?>" name="_wc_file_names[]" value="<?php echo esc_attr( $file['name'] ); ?>" />
    </td>
    <td>
        <p>
            <input type="text" class="sk-form-control sk-w8 input_text wc_file_url" placeholder="https://" name="_wc_file_urls[]" value="<?php echo esc_attr( $file['file'] ); ?>" style="margin-right: 8px;" />
            <a href="#" class="sk-btn sk-btn-sm sk-btn-default upload_file_button" data-choose="<?php esc_attr_e( 'Choose file', 'sk-core' ); ?>" data-update="<?php esc_attr_e( 'Insert file URL', 'sk-core' ); ?>"><?php echo esc_html( str_replace( ' ', '&nbsp;', __( 'Choose file', 'sk-core' ) ) ); ?></a>
        </p>
    </td>

    <td>
        <p>
            <a href="#" class="sk-btn sk-btn-sm sk-btn-danger sk-product-delete"><span><?php esc_html_e( 'Delete', 'sk-core' ); ?></span></a>
        </p>
    </td>
</tr>
