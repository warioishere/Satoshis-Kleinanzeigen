<tr>
	<td class="file_name">
        <input type="text" class="sk-form-control wc_variation_file_name" placeholder="<?php esc_attr_e( 'File Name', 'sk' ); ?>" name="_wc_variation_file_names[<?php echo $variation_id; ?>][]" value="<?php echo esc_attr( $file['name'] ); ?>" />
        <input type="hidden" name="_wc_variation_file_hashes[<?php echo $variation_id; ?>][]" value="<?php echo esc_attr( $key ); ?>" />
    </td>
    <td class="file_url"><input type="text" class="sk-form-control wc_variation_file_url" placeholder="<?php esc_attr_e( "http://", 'sk' ); ?>" name="_wc_variation_file_urls[<?php echo $variation_id; ?>][]" value="<?php echo esc_attr( $file['file'] ); ?>" /></td>
	<td class="file_url_choose" width="1%"><a href="#" class="sk-btn sk-btn-default upload_file_button" data-choose="<?php esc_attr_e( 'Choose file', 'sk' ); ?>" data-update="<?php esc_attr_e( 'Insert file URL', 'sk' ); ?>"><?php echo str_replace( ' ', '&nbsp;', __( 'Choose file', 'sk' ) ); ?></a></td>
	<td width="1%"><a href="#" class="sk-btn sk-btn-theme sk-product-delete"><?php _e( 'Delete', 'sk' ); ?></a></td>
</tr>
