<tr>
	<td class="file_name"><input type="text" class="input_text" placeholder="<?php esc_attr_e( 'File Name', 'sk-core' ); ?>" name="_wc_variation_file_names[<?php echo absint( $variation_id ); ?>][]" value="<?php echo esc_attr( $file['name'] ); ?>" /></td>
	<td class="file_url"><input type="text" class="input_text wc_file_url" placeholder="http://" name="_wc_variation_file_urls[<?php echo absint( $variation_id ); ?>][]" value="<?php echo esc_attr( $file['file'] ); ?>" /></td>
	<td class="file_url_choose" width="1%"><a href="#" class="sk-btn sk-btn-sm sk-btn-default upload_file_button" data-choose="<?php esc_attr_e( 'Choose file', 'sk-core' ); ?>" data-update="<?php esc_attr_e( 'Insert file URL', 'sk-core' ); ?>"><?php echo str_replace( ' ', '&nbsp;', __( 'Choose file', 'sk-core' ) ); ?></a></td>
	<td width="1%"><a href="#" class="sk-btn sk-btn-sm sk-btn-danger sk-product-delete"><?php esc_html_e( 'Delete', 'sk-core' ); ?></a></td>
</tr>
