<?php
/**
 * SK Dashboard
 * variation table content
 *
 *
 */
?>
<script type="text/html" id="tmpl-sk-add-attribute">

    <li class="product-attribute-list">
        <div class="sk-product-attribute-heading">
            <span><i class="fas fa-bars" aria-hidden="true"></i> <strong>Title</strong></span>
            <input type="hidden" name="attribute_position[]" value="">
            <a href="#" class="sk-product-remove-attribute"><?php _e( 'Remove', 'sk' ); ?></a>
            <a href="#" class="sk-product-toggle-attribute">
                <i class="fas fa-sort-down fa-flip-horizointal" aria-hidden="true"></i>
            </a>
        </div>
        <div class="sk-product-attribute-item sk-clearfix sk-hide">
            <div class="content-half-part">
                <label class="form-label" for="">Name</label>
                <input type="text" class="sk-form-control sk-product-attribute-name" name="" value="">

                <label for="" class="checkbox-item form-label">
                    <input type="checkbox" name="" value="1">  Visible on the product page
                </label>

                <label for="" class="checkbox-item form-label">
                    <input type="checkbox" name="" value="1">  Use for variations
                </label>
            </div>

            <div class="content-half-part">
                <label for="" class="form-label">Values</label>
                <select name="" id="" multiple style="width:100%" class="sk-select2" data-placeholder="Select name" data-tags="true" data-allow-clear="true" data-token-separators="[',', '|']"></select>
            </div>
        </div>
    </li>

</script>