<?php
add_action('woocommerce_thankyou', 'cybertill_deactivalte_plugin_order_data_fn',10, 1);

function cybertill_deactivalte_plugin_order_data_fn($order_id){

    if( !is_plugin_active( 'woo-cybertill-new/woo-cybertill-new.php' ) ) {

        $cybertill_orderinfo = wc_get_order( $order_id );
        $cybertill_orderinfo->update_status( 'processing' );  
        update_post_meta($order_id, 'cybertill_order_flag', 0);
        
    }

}