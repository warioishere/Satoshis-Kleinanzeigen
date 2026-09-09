<?php
    $sk_geo_latitude  = sk_geo_float_val( $sk_geo_latitude );
    $sk_geo_longitude = sk_geo_float_val( $sk_geo_longitude );
?>
<input
    type="hidden"
    name="sk_geolocation[]"
    value="<?php echo esc_attr( $id ); ?>"
    data-latitude="<?php echo esc_attr( $sk_geo_latitude ); ?>"
    data-longitude="<?php echo esc_attr( $sk_geo_longitude ); ?>"
    data-address="<?php echo esc_attr( $sk_geo_address ); ?>"
    data-info="<?php echo esc_attr( $info ); ?>"
>
