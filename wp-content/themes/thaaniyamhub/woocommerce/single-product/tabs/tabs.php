<?php
/**
 * Single Product Tabs
 *
 * @package WooCommerce/Templates
 * @version 9.8.0
 */

defined( 'ABSPATH' ) || exit;
$tabs = apply_filters( 'woocommerce_product_tabs', array() );

if ( ! empty( $tabs ) ) : ?>
    <div class="accordion" id="productAccordion">
        <?php $i = 0; foreach ( $tabs as $key => $tab ) : $i++; ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?php echo $i > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#tab-<?php echo $key; ?>">
                        <?php echo esc_html( $tab['title'] ); ?>
                    </button>
                </h2>
                <div id="tab-<?php echo $key; ?>" class="accordion-collapse collapse <?php echo $i == 1 ? 'show' : ''; ?>">
                    <div class="accordion-body">
                        <?php call_user_func( $tab['callback'], $key, $tab ); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
