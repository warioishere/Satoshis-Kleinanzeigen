<?php

namespace SK\Core\Dashboard\Modules;

/**
 * Outputs Bitcoin coin icon after WooCommerce price amounts.
 * Ported from kadence-child/functions.php.
 */
class CurrencyIcon {

    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_currency_icon_css' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'remove_category_count_brackets' ], 20 );
    }

    public function output_currency_icon_css(): void {
        $icon = esc_url( get_stylesheet_directory_uri() . '/assets/icons/coin.png' );
        ?>
        <style id="yd-currency-icon-all">
          /* WCPS: Price-Span wieder inline erzwingen */
          .wcps-container .woocommerce-Price-amount.amount { display:inline !important; }

          /* --- Woo Katalog + Produktseite: nur mit <bdi> --- */
          .products .product .price .amount > bdi::after,
          .single-product .entry-summary .price .amount > bdi::after {
            content: "";
            display: inline-block;
            width: 1.5em;
            height: 1.5em;
            margin-left: .35em;
            vertical-align: -0.275em;
            background: url('<?php echo $icon; ?>') no-repeat center;
            background-size: contain;
          }

          /* --- SK Dashboard + WCPS: auch ohne <bdi> --- */
          .sk-dashboard .price .woocommerce-Price-amount.amount::after,
          .sk-dashboard td[data-title="Preis"] .woocommerce-Price-amount.amount::after,
          .wcps-container .woocommerce-Price-amount.amount::after,
          .wcps-items .woocommerce-Price-amount.amount::after,
          [class*="wcps"] .woocommerce-Price-amount.amount::after {
            content: "";
            display: inline-block;
            width: 1.5em;
            height: 1.5em;
            margin-left: .35em;
            vertical-align: -0.275em;
            background: url('<?php echo $icon; ?>') no-repeat center;
            background-size: contain;
          }

          /* Währungssymbol ausblenden */
          .woocommerce-Price-currencySymbol { font-size:0 !important; }
        </style>
        <?php
    }

    public function remove_category_count_brackets(): void {
        $css = '.wc-block-product-categories-list-item-count::before,'
             . '.wc-block-product-categories-list-item-count::after{content:none!important}';
        wp_register_style( 'sk-remove-brackets', false, [], null );
        wp_enqueue_style( 'sk-remove-brackets' );
        wp_add_inline_style( 'sk-remove-brackets', $css );

        $js = <<<'JS'
(function(){
  function strip(node){
    if(!node)return;
    var els=node.querySelectorAll('.wc-block-product-categories-list-item-count,.product-categories-count,.count');
    els.forEach(function(el){
      var t=(el.textContent||'').trim();
      var no=t.replace(/^\(\s*(\d+)\s*\)$/,'$1');
      no=no.replace(/^\((.*)$/,'$1').replace(/^(.*)\)$/,'$1').trim();
      if(no!==t)el.textContent=no;
    });
  }
  function run(){strip(document);}
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',run);}else{run();}
  var b=document.querySelector('body');
  if(b){new MutationObserver(function(m){for(var i=0;i<m.length;i++){if(m[i].addedNodes.length){strip(document);break;}}}).observe(b,{childList:true,subtree:true});}
  setTimeout(run,300);setTimeout(run,1200);
})();
JS;
        wp_add_inline_script( 'jquery-core', $js );
    }
}
