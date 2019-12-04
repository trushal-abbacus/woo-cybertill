<?php
   /*
   Plugin Name: Woo Cybertill New
   Plugin URI: http://example.com
   description: Plugin create for stock
   Version: 1.0
   Author: Mr. Team
   Author URI: http://example.com
   License: GPL2
   */

  register_activation_hook( __FILE__, 'WooSoapCybertilProduct_activate' );
  register_deactivation_hook( __FILE__, 'WooSoapCybertilProduct_deactivate');


  //plugin active
  function WooSoapCybertilProduct_activate(){

   //first cron
   if ( ! wp_next_scheduled( 'cybertil_manage_everyhalfhour' ) ) {
      wp_schedule_event( time(), 'every_thirty_minutes', 'cybertil_manage_everyhalfhour' );
   }

   //second cron
   if ( ! wp_next_scheduled( 'cybertil_halfhour' ) ) {
      wp_schedule_event( time(), 'thirty_minutes_cr', 'cybertil_halfhour' );
   }


  }

 add_filter( 'cron_schedules', 'cybertil_manage_everyhalfhour');
 add_action( 'cybertil_manage_everyhalfhour', 'cybertil_demo_test_fn');
 add_action( 'cybertil_manage_everyhalfhour', 'cybertil_multi_product_fn');
 add_action( 'cybertil_manage_everyhalfhour', 'cybertil_product_ref_id_fn');

 
  //first cron
  function cybertil_manage_everyhalfhour( $schedules ) {
      $schedules['every_thirty_minutes'] = array(
            'interval'  => 60*30,
            'display'   => __( 'Every 30 Minutes', '' )
      );
      return $schedules;
   }

   //first cron function
   function cybertil_demo_test_fn(){
     
      $filepath=plugin_dir_path( __FILE__ ).'cybertilcronjobfile/demotest.txt';
      $fp = fopen($filepath, "a") or die("Unable to open file!");
      fwrite($fp, "\n ---------------------------------------------------\n");
      fwrite($fp, date("Y-m-d H:i:s"));
      fwrite($fp, "\n ---------------------------------------------------\n\n\n");
      fclose($fp);

   }



   //second cron
   add_filter( 'cron_schedules', 'cybertil_halfhour');
   function cybertil_halfhour( $schedules ) {
      $schedules['thirty_minutes_cr'] = array(
            'interval'  => 60*30,
            'display'   => __( 'Every 30 Minutes', '' )
      );
      return $schedules;
   }

   //second cron function
   add_action( 'cybertil_halfhour', 'resychronize_cybertill_om_request_fn');

   /* function cybertil_resync_ord_manage_fn(){
     
      $filepath=plugin_dir_path( __FILE__ ).'cybertilcronjobfile/newdemotest.txt';
      $fp = fopen($filepath, "a") or die("Unable to open file!");
      fwrite($fp, "\n ---------------------------------------------------\n");
      fwrite($fp, date("Y-m-d H:i:s"));
      fwrite($fp, "\n ---------------------------------------------------\n\n\n");
      fclose($fp);

   } */

   function get_ref_product_id($product_title,$sku,$client){
      try{
         $products_data = $client->product_search($product_title);
         if(count($products_data->item) > 1){
            foreach($products_data->item as $product_data){
              
              try{
                 $product_stock = $client->stock_product($product_data->id);
                 if(count($product_stock->item) == 1){
                    $stkItemId = $product_stock->item->stkItemId;
                 }else{
                    $stkItemId = $product_stock->item[0]->stkItemId;
                 }
                 $product_item = $client->item_get($stkItemId);
                 if(explode(':', $product_item->productOption->ref)[0] == $sku){
                    return $product_data->id;
                 }
              }catch(Exception $e){
                 
              }
            }
          }
          if(count($products_data->item)==1){
            $product_data = $products_data->item;

            $product_stock = $client->stock_product($product_data->id);
            if(count($product_stock->item) == 1){
              $stkItemId = $product_stock->item->stkItemId;
            }else{
              $stkItemId = $product_stock->item[0]->stkItemId;
            }
            $product_item = $client->item_get($stkItemId);
            if(explode(':', $product_item->productOption->ref)[0] == $sku){
              return $product_data->id;
            }
          }
         return '';
      }catch(Exception $e){
         return '';
      }
   }

   

   //add_action("init","cybertil_product_ref_id_fn");
   function cybertil_product_ref_id_fn(){

      global $product;

      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
      $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));

      /* $filepath = plugin_dir_path( __FILE__ ).'product-reference-id-log.txt';
      $fp = fopen($filepath, "a") or die("Unable to open file!");
      fwrite($fp, "\n Start ---------------------------------------------------\n");
      fwrite($fp, date("Y-m-d H:i:s")); */
      try{

      $args = array(
         'post_type'   => 'product',
         'post_status' => 'publish',
         'posts_per_page' => -1,
      );

      $products_data = get_posts( $args );
      $refProductArray = array();
      $i=0;
      foreach($products_data as $product_data){
         $meta_value = get_post_meta($product_data->ID,'ref_product_id',true); 
         if($meta_value==''){
            $product_title = $product_data->post_title;
            $productDetail = wc_get_product( $product_data->ID );
            $ref_product_id = get_ref_product_id($product_title,$productDetail->get_sku(),$client);
            
            if($ref_product_id != ''){

              $refProductArray[$i]['productId'] = $product_data->ID;
              $refProductArray[$i]['productRefId'] = $ref_product_id;
               update_post_meta( $product_data->ID, 'ref_product_id', $ref_product_id );
               $i++;
            }
         }
      }

     /*  fwrite($fp, print_r($refProductArray,true));
      fwrite($fp, date("Y-m-d H:i:s")); */

      /* weekly log information for traction  */

      $data = '';
      $year = date("Y");
      $month = date("m");
      $day = date("D");

      $directory = plugin_dir_path( __FILE__ )."cornjob/regular_order/$day/";

      $data .="\n ********************************** \n";
      $data .= date('Y-m-d H:i:s');
      $data .="\n ********************************** \n";
      $data .="\n Product Infotmation: \n";
      $data .=print_r($refProductArray,true);
      $data .="\n********* End ********* \n";

      $f_name =  'product-reference-id-log-'.date('Y-m-d').'.txt';

      $filename = $directory.$f_name;

      $beforeweekdate = date('Y-m-d');
      $beforeweekdate = strtotime($beforeweekdate);
      $beforeweekdate = strtotime("-7 day", $beforeweekdate);
      $beforeweekfilename = 'product-reference-id-log-'.date('Y-m-d', $beforeweekdate).'.txt';
      $beforeweekpath=$directory.$beforeweekfilename;

      if(!is_dir($directory)){
         
         mkdir($directory, 0775, true);

         if (!file_exists($filename)) {
            $fh = fopen($filename, 'w');
         }
         
         $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

      }else{

         if(file_exists($beforeweekpath)){

            unlink($beforeweekpath);
         }

         if (!file_exists($filename)) {
            $fh = fopen($filename, 'w') or die("Can't create file");
         }

         $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

      }

   /* end week log information */


      }
      catch(Exception $e){
         return '';
      }
      /* fwrite($fp, "\n End ---------------------------------------------------\n\n\n");
      fclose($fp); */

   }

   //Multiple product stock update cron function
   function cybertil_multi_product_fn(){
     
      global $product;
      global $woocommerce;

      $prodsku = $product->sku;

      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
      $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));

      /* $filepath = plugin_dir_path( __FILE__ ).'multiple-product-log.txt';
      $fp = fopen($filepath, "a") or die("Unable to open file!");
      fwrite($fp, "\n Start ---------------------------------------------------\n");
      fwrite($fp, date("Y-m-d H:i:s"));
      fwrite($fp, "\n"); */
      try{
      $args = array(
         'post_type'   => 'product',
         'post_status' => 'publish',
         'posts_per_page' => -1,
      );

      $products_data = get_posts( $args );
      $myProductArray = array();
      $i=0;
      foreach($products_data as $product_data){

         $product_id = get_post_meta($product_data->ID,'ref_product_id',true);
         if($product_id!=''){
            $product_info = wc_get_product( $product_data->ID );
            $myProductArray[$i]['productId']=$product_data->ID;
            $myProductArray[$i]['productRefId']=$product_id;
            $myProductArray[$i]['productType']=$product_info->product_type;
      
            if($product_info->product_type == 'variable'){

               $product_variable = new WC_Product_Variable($product_data->ID);
               $product_variations = $product_variable->get_available_variations();
               $sku_array = array();
               $variation_arr = array();

               $variations1=$product_variable->get_children();
               foreach ($variations1 as $value) {
                  $single_variation=new WC_Product_Variation($value);
                  $sku_array[] = $single_variation->sku;
                  $variation_arr[$single_variation->sku] = $single_variation->variation_id;
               }
                    
                  $product_stock = $client->stock_product($product_id);
                  $j=0;
                  foreach($product_stock->item as $stock_detail){
                     $product_item = $client->item_get ($stock_detail->stkItemId);
                     $ref_sku = explode(":",$product_item->productOption->ref);
                     if(in_array($ref_sku[0].$ref_sku[2], $sku_array)){
                        $myProductArray[$i][$j]['sku']=$ref_sku[0].$ref_sku[2];
                        $myProductArray[$i][$j]['stock']=$stock_detail->stock;
                        $variation_obj = new  WC_Product_Variation($variation_arr[$ref_sku[0].$ref_sku[2]]);
                        $variation_obj->set_stock_quantity($stock_detail->stock);
                        $variation_obj->set_manage_stock(true);
                        $variation_obj->set_stock_status(true);
                        $variation_obj->save();
                        $j++;
                     }
                  }
                     
            }else{
              // $pro_sku = $product->get_sku();

               $product_stock = $client->stock_product($product_id);
               foreach($product_stock->item as $stock_detail){
                  $myProductArray[$i]['stock']=$stock_detail->stock;
                 
                  $quantity = $stock_detail->stock;
                  $product_item = $client->item_get($stock_detail->stkItemId);
                  $ref_sku = explode(":",$product_item->productOption->ref); 

                  $product_data = new WC_Product( $product->id );
                  $product_data->set_stock_quantity($quantity);
                  $product_data->set_manage_stock(true);
                  $product_data->set_stock_status(true);
                  $product_data->save();

               }
            }
            $i++;
         }
       
      }

     /*  fwrite($fp, print_r($myProductArray,true));
         fwrite($fp, date("Y-m-d H:i:s")); */

       /* weekly log information for traction  */

       $data = '';
       $year = date("Y");
       $month = date("m");
       $day = date("D");
 
       $directory = plugin_dir_path( __FILE__ )."cornjob/regular_order/$day/";
 
       $data .="\n ********************************** \n";
       $data .= date('Y-m-d H:i:s');
       $data .="\n ********************************** \n";
       $data .="\n Product Infotmation: \n";
       $data .=print_r($myProductArray,true);
       $data .="\n********* End ********* \n";
 
       $f_name =  'multiple-product-log-'.date('Y-m-d').'.txt';
 
       $filename = $directory.$f_name;
 
       $beforeweekdate = date('Y-m-d');
       $beforeweekdate = strtotime($beforeweekdate);
       $beforeweekdate = strtotime("-7 day", $beforeweekdate);
       $beforeweekfilename = 'multiple-product-log-'.date('Y-m-d', $beforeweekdate).'.txt';
       $beforeweekpath=$directory.$beforeweekfilename;
 
       if(!is_dir($directory)){
          
          mkdir($directory, 0775, true);
 
          if (!file_exists($filename)) {
             $fh = fopen($filename, 'w');
          }
          
          $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);
 
       }else{
 
          if(file_exists($beforeweekpath)){
 
             unlink($beforeweekpath);
          }
 
          if (!file_exists($filename)) {
             $fh = fopen($filename, 'w') or die("Can't create file");
          }
 
          $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);
 
       }
 
    /* end week log information */


      }catch(Exception $e){
         return '';
      }

      /* fwrite($fp, "\n End ---------------------------------------------------\n\n\n");
      fclose($fp); */
   }

   //plugin deactive
   function WooSoapCybertilProduct_deactivate(){

      //remove fisrt cron
      wp_clear_scheduled_hook('cybertil_manage_everyhalfhour');
      wp_clear_scheduled_hook('cybertil_halfhour');

   }



/*   function set_product_ref(){
      global $product;

      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
      $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));

      $args = array(
         'post_type'   => 'product',
         'post_status' => 'publish',
         'posts_per_page' => -1,
      );

      $products_data = get_posts( $args );
      $i==0;
      foreach($products_data as $product_data){
         $meta_value = get_post_meta($product_data->ID,'ref_product_id',true);
         if($meta_value==''){
            $product_title = $product_data->post_title;
            $productDetail = wc_get_product( $product_data->ID );
            $ref_product_id = get_ref_product_id($product_title,$productDetail->get_sku(),$client);
            $i++;
           //echo $i." -> ".$product_data->ID." -> ";print_r($ref_product_id);
            if($ref_product_id != ''){
               //update_post_meta( $product_data->ID, 'ref_product_id', $ref_product_id );
            }
         }
      }
      
   }
   add_action("init","set_product_ref");*/

   add_action( 'woocommerce_before_single_product', 'single_product_fn1', 5);

   function single_product_fn1(){
      global $product;
      global $woocommerce;

      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
      $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));

      /* start get product reference id */

      $meta_value = get_post_meta($product->id,'ref_product_id',true); 
      if($meta_value==''){
         $product_title = $product->get_name();
         $productDetail = wc_get_product( $product->id );

         $ref_product_id = get_ref_product_id($product_title,$productDetail->get_sku(),$client);

         //print_r($ref_product_id);

         if($ref_product_id != ''){

            update_post_meta( $product->id, 'ref_product_id', $ref_product_id );
         }
      }

      /*  end get product reference id */


      $prodsku = $product->sku;


      

      $filepath=plugin_dir_path( __FILE__ ).'test-log.txt';

      $product_variable = new WC_Product_Variable($product->id);
      $product_variations = $product_variable->get_available_variations();
      $sku_array = array();
      $variation_arr = array();

      $variations1=$product_variable->get_children();
      foreach ($variations1 as $value) {
         $single_variation=new WC_Product_Variation($value);
         $sku_array[] = $single_variation->sku;
         $variation_arr[$single_variation->sku] = $single_variation->variation_id;
      }
   
       /* weekly log information for traction  */
        $data = '';
        $year = date("Y");
        $month = date("m");
        $day = date("D");

        $directory = plugin_dir_path( __FILE__ )."cornjob/resynch_order/$day/";

        $data .= "\n ********************************** \n";
        $data .= date('Y-m-d H:i:s');
        $data .= "\n ********************************** \n";

      if($product->product_type == 'variable'){
               $product_id = get_post_meta($product->id,'ref_product_id',true);
               if($product_id!=''){
                  $product_stock = $client->stock_product($product_id);
                  //print_r($product_stock);exit;
                  foreach($product_stock->item as $stock_detail){
                     $product_item = $client->item_get ($stock_detail->stkItemId);
                     $ref_sku = explode(":",$product_item->productOption->ref);
                     if(in_array($ref_sku[0].$ref_sku[2], $sku_array)){
                        $variation_obj = new  WC_Product_Variation($variation_arr[$ref_sku[0].$ref_sku[2]]);
                        $variation_obj->set_stock_quantity($stock_detail->stock);
                        $variation_obj->set_manage_stock(true);
                        $variation_obj->set_stock_status(true);
                        $variation_obj->save(); 
                     }
                  }

                  $data .= "\n Start Variable Product Information : \n";
                  $data .=  print_r($product_item,true);
                  $data .= "\n End Variable Product Information  \n";

               }
      }else{
         $pro_sku = $product->get_sku();

         $product_id = get_post_meta($product->id,'ref_product_id',true);
         $product_stock = $client->stock_product($product_id);
         //print_r($product_stock);

         $product_stock = $client->stock_product($product_id);
         //print_r($product_stock);exit;
         foreach($product_stock->item as $stock_detail){
            
            $quantity = $stock_detail->stock;
            $product_item = $client->item_get ($stock_detail->stkItemId);
            $ref_sku = explode(":",$product_item->productOption->ref); 
            // get_post_meta( $product_id, '_stock', false );
            /*update_post_meta($product->id, '_stock', 0);
            update_post_meta( $product->id, '_manage_stock', true );
            update_post_meta( $product->id, '_stock_status', true );*/

            $product_data = new WC_Product( $product->id );
            $product_data->set_stock_quantity($quantity);
            $product_data->set_manage_stock(true);
            $product_data->set_stock_status(true);
            $product_data->save();

         }

         $data .= "\n Start Single Product Information : \n";
         $data .=  print_r($product_item,true);
         $data .= "\n End Single Product Information  \n";

      }

      $data .= "\n ********* End ********* \n";

      $f_name =  'single-product-'.date('Y-m-d').'.txt';

      $filename = $directory.$f_name;

      $beforeweekdate = date('Y-m-d');
      $beforeweekdate = strtotime($beforeweekdate);
      $beforeweekdate = strtotime("-7 day", $beforeweekdate);
      $beforeweekfilename = 'single-product-'.date('Y-m-d', $beforeweekdate).'.txt';
      $beforeweekpath=$directory.$beforeweekfilename;

      if(!is_dir($directory)){
         
         mkdir($directory, 0775, true);

         if (!file_exists($filename)) {
            $fh = fopen($filename, 'w');
         }
         
         $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

      }else{

         if(file_exists($beforeweekpath)){

            unlink($beforeweekpath);
         }

         if (!file_exists($filename)) {
            $fh = fopen($filename, 'w') or die("Can't create file");
         }

         $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

      }

     /* end week log information */


      
   }

/*if( ! is_single() ) {

   add_action("init","multi_product_fn");

   function multi_product_fn(){
      global $product;
      global $woocommerce;

      $prodsku = $product->sku;

      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
      $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
      $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));

      $filepath=plugin_dir_path( __FILE__ ).'test-log.txt';

      

      $args = array(
         'post_type'   => 'product',
         'post_status' => 'publish',
         'posts_per_page' => -1,
      );

      $products_data = get_posts( $args );

      foreach($products_data as $product_data){

         $product_id = get_post_meta($product_data->ID,'ref_product_id',true);
         if($product_id!=''){
            $product_info = wc_get_product( $product_data->ID );
      
            if($product_info->product_type == 'variable'){

               $product_variable = new WC_Product_Variable($product_data->ID);
               $product_variations = $product_variable->get_available_variations();
               $sku_array = array();
               $variation_arr = array();

               $variations1=$product_variable->get_children();
               foreach ($variations1 as $value) {
                  $single_variation=new WC_Product_Variation($value);
                  $sku_array[] = $single_variation->sku;
                  $variation_arr[$single_variation->sku] = $single_variation->variation_id;
               }
                    
                  $product_stock = $client->stock_product($product_id);
                  foreach($product_stock->item as $stock_detail){

                    //print_r($stock_detail);
                     $product_item = $client->item_get ($stock_detail->stkItemId);
                     $ref_sku = explode(":",$product_item->productOption->ref);
                     print_r($ref_sku[0].$ref_sku[2]);
                     if(in_array($ref_sku[0].$ref_sku[2], $sku_array)){
                        $variation_obj = new  WC_Product_Variation($variation_arr[$ref_sku[0].$ref_sku[2]]);
                        $variation_obj->set_stock_quantity($stock_detail->stock);
                        $variation_obj->set_manage_stock(true);
                        $variation_obj->set_stock_status(true);
                        $variation_obj->save(); 
                     }
                  }
                     
            }else{
               //$pro_sku = $product->get_sku();

               $product_stock = $client->stock_product($product_id);
               //print_r($product_stock);

                     $product_stock = $client->stock_product($product_id);
                     foreach($product_stock->item as $stock_detail){
                       
                        $quantity = $stock_detail->stock;
                        $product_item = $client->item_get ($stock_detail->stkItemId);
                        $ref_sku = explode(":",$product_item->productOption->ref); 

                        $product_data = new WC_Product( $product->id );
                        $product_data->set_stock_quantity($quantity);
                        $product_data->set_manage_stock(true);
                        $product_data->set_stock_status(true);
                        $product_data->save();

                     }

            }
         }
       
      }  
      
   } 
}*/

//add_action("init","order_sink_fn");
//add_action( 'woocommerce_thankyou', 'order_sink_fn', 10, 1 );
function order_sink_fn($order_id) {

    $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
    $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
    $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));

    global $woocommerce;
    global $wpdb;
    $order_id = 7750;
    if ( ! $order_id ) return;
     
    $order = wc_get_order($order_id);
    
   // echo "<pre>";

    foreach ($order->get_items() as $item) {

     // print_r($item->get_variation_id());
      $product = wc_get_product($item->get_variation_id());
      $item_sku[] = $product->get_sku();

    }
  
    //echo "<pre>";
    //print_r($item_sku);
   // exit;

    $first_name = $order->get_billing_first_name();
    $last_name = $order->get_billing_last_name();
    $initial =  '';
    $salutation = '';
    $dob = '';
    $gender = '';
    $vatNumber = '';
    $giftAid = '';
    $ukTaxpayer = '';
    $privacy = '';
    $webEmail = $order->get_billing_email();
    $webPassword = 'test123';
    $webPasswordAgain = 'test123';

    $addName = '';
    $add1 = $order->get_billing_address_1();
    $add2 = $order->get_billing_address_2();
    $city = $order->get_billing_city();
    $state = $order->get_billing_state();
    $countryId = $order->get_billing_country();
    $zip = $order->get_billing_postcode();
    $tel1 = $order->get_billing_phone();
    $tel2 = '';
    $fax = '';
    
   /*-- For Guest user Account Creation-- */
      /*
       $user_name = $first_name.'_'.$last_name;
       $fullName = $first_name.' '.$last_name;
       $email = email_exists( $webEmail );  
       $user = username_exists( $user_name );
     
       if( $user == false && $email == false ){
       
         $random_password = wp_generate_password();

         $userdata = array(
                           'user_login'    => strtolower($user_name),
                           'user_nicename' => strtolower($user_name),
                           'user_email'    => $webEmail,
                           'user_pass'    => $random_password,
                           'display_name'  => ucwords($fullName),
                           'role' => 'customer'
                       );
         $user_insert = wp_insert_user( $userdata ) ;

       }

       $user = get_user_by( 'email', $webEmail );
       $user_id = $user->id;*/
    

   /*--End For Guest user Account Creation-- */
   
   $row = $wpdb->get_row("SELECT * FROM `wp_customer_details` WHERE `email` = '".$webEmail."' ");


   if(!empty($row)){

        $customerId = $row->customer_id;
        $addressId = $row->address_id; 

   }else{

      $customer_details = array(
       'firstName' => $first_name,
       'lastName' => $last_name,
       'initial' => $initial,
       'salutation' => $salutation,
       'dob' => $dob,
       'gender' => $gender,
       'company' => '',
       'position' => '',
       'vatNumber' => '',
       'giftAid' => '',
       'ukTaxpayer' => '',
       'privacy' => '',
       'webEmail' => $webEmail,
       'webPassword' => $webPassword,
       'webPasswordAgain' => $webPasswordAgain,
       'spamOptout' => '',
       'thirdpartyOptout' => '',
       'postmailshot' => '',
       'defaultAddress' => array(
         'addName' => $addName,
         'add1' => $add1,
         'add2' => $add2,
         'city' => $city,
         'state' => $state,
         'countryId' => 202,
         'zip' => $zip,
         'tel1' => $tel1,
         'tel2' => '',
         'fax' => ''
       )
      );

      $customer_info = $client->customer_add($customer_details); 
      $customerId = $customer_info->customerId;
      $addressId = $customer_info->addressId;

      if($customerId != ''){

         $table = 'wp_customer_details';
         $data =  array('firstname' => $first_name, 
                        'lastname' => $last_name,
                        'email' => $webEmail,
                        'customer_id' => $customerId,
                        'address_id' => $addressId
                     );
         $format = array('%s','%s','%s','%d','%d');
         $wpdb->insert($table,$data,$format);
         $my_id = $wpdb->insert_id;

      }
   }

   


}


    

function get_order_information_data($order_id){

   global $woocommerce;
   global $wpdb;

   session_start();
   $transaction_data = array();

   $orderinfo = wc_get_order( $order_id );
   $order_data = $orderinfo->get_data();

   $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
   $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
   $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));

   $locationdata=$client->location_web_list(1);

   /* BILLING INFORMATION: */
   $order_billing_first_name = $order_data['billing']['first_name'];
   $order_billing_last_name = $order_data['billing']['last_name'];
   $order_billing_company = $order_data['billing']['company'];
   $order_billing_address_1 = $order_data['billing']['address_1'];
   $order_billing_address_2 = $order_data['billing']['address_2'];
   $order_billing_city = $order_data['billing']['city'];
   $order_billing_state = $order_data['billing']['state'];
   $order_billing_postcode = $order_data['billing']['postcode'];
   $order_billing_country = $order_data['billing']['country'];
   $order_billing_email = $order_data['billing']['email'];
   $order_billing_phone = $order_data['billing']['phone'];

   /* SHIPPING INFORMATION: */
   $order_shipping_first_name = $order_data['shipping']['first_name'];
   $order_shipping_last_name = $order_data['shipping']['last_name'];
   $order_shipping_company = $order_data['shipping']['company'];
   $order_shipping_address_1 = $order_data['shipping']['address_1'];
   $order_shipping_address_2 = $order_data['shipping']['address_2'];
   $order_shipping_city = $order_data['shipping']['city'];
   $order_shipping_state = $order_data['shipping']['state'];
   $order_shipping_postcode = $order_data['shipping']['postcode'];
   $order_shipping_country = $order_data['shipping']['country'];


   /* ORDER ITEMS INFORMATION */
   $order_items = array();
   $order_all_data = array();
   $order_details = array();

   foreach($orderinfo->get_items() as $item_key => $item ){

      $item_id = $item->get_id();
      $product_id = $item->get_product_id(); 
      $variation_id = $item->get_variation_id(); 

      $item_name    = $item->get_name(); 
      $quantity     = $item->get_quantity();  
      $tax_class    = $item->get_tax_class();
      $line_subtotal     = $item->get_subtotal(); 
      $line_subtotal_tax = $item->get_subtotal_tax(); 
      $line_total        = $item->get_total();   
      $line_total_tax    = $item->get_total_tax(); 

      $product        = $item->get_product();

      $product_type   = $product->get_type();
      $product_sku    = $product->get_sku();
      $product_price  = $product->get_price();
      $stock_quantity = $product->get_stock_quantity();

      $cybertill_parent_product_id = get_post_meta($product_id,'ref_product_id',true);

      if($cybertill_parent_product_id!=''){

         $prod_stocks = $client->stock_product($cybertill_parent_product_id , $locationdata->item->location->id);

         if(!empty($prod_stocks->item)){


            foreach($prod_stocks->item as $prod_stock){

               $product_item = $client->item_get ($prod_stock->stkItemId ,$locationdata->item->location->id);
               $ref_sku = explode(":",$product_item->productOption->ref);

               if($ref_sku[0].$ref_sku[2]==$product_sku){
                 $order_items[] = array(
                     'itemId' => $prod_stock->stkItemId,
                     'salesQty' =>$quantity,
                     'itemPrice' =>$product_price,
                     'discountPrice' =>null,
                     'note' => '',
                     'vatRate' =>null,
                     'issuedValue' =>null
                  );
               }
           
            }
            
         }

      }

      
      $order_all_data[]=array(
         'item_id'=>$item_id,
         'product_id'=>$product_id,
         'variation_id'=>$variation_id,
         'item_name'=>$item_name,
         'quantity'=>$quantity,
         'tax_class'=>$tax_class,
         'line_subtotal'=>$line_subtotal,
         'line_subtotal_tax'=>$line_subtotal_tax,
         'line_total'=>$line_total,
         'line_total_tax'=>$line_total_tax,
         'product_type'=>$product_type,
         'product_sku'=>$product_sku,
         'product_price'=>$product_price,
         'stock_quantity'=>$stock_quantity
      );

      /* $order_items[] = array(
         'itemId' => $item_id,
         'salesQty' =>$quantity,
         'itemPrice' =>$product_price,
         'discountPrice' =>null,
         'note' => '',
         'vatRate' =>null,
         'issuedValue' =>null
      ); */

   }

   /* ORDER SHIPPING INFORAMTION */
   $order_shipping_details = array();
   foreach( $orderinfo->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
      $order_item_name             = $shipping_item_obj->get_name();
      $order_item_type             = $shipping_item_obj->get_type();
      $shipping_method_title       = $shipping_item_obj->get_method_title();
      $shipping_method_id          = $shipping_item_obj->get_method_id(); 
      $shipping_method_instance_id = $shipping_item_obj->get_instance_id();
      $shipping_method_total       = $shipping_item_obj->get_total();
      $shipping_method_total_tax   = $shipping_item_obj->get_total_tax();
      $shipping_method_taxes       = $shipping_item_obj->get_taxes();

      $order_shipping_details = array(
         'order_item_name'=>$order_item_name,
         'order_item_type'=>$order_item_type,
         'shipping_method_title'=>$shipping_method_title,
         'shipping_method_id'=>$shipping_method_id,
         'shipping_method_instance_id'=>$shipping_method_instance_id,
         'shipping_method_total'=>$shipping_method_total,
         'shipping_method_total_tax'=>$shipping_method_total_tax,
         'shipping_method_taxes'=>$shipping_method_taxes
      );
   }

   /* ORDER TOTAL INFORMATION */

   $currency_symbol = get_woocommerce_currency_symbol( get_woocommerce_currency() );

   $currency_currency = get_woocommerce_currency();
      
   $order_total = is_callable(array($orderinfo, 'get_total')) ? $orderinfo->get_total() : $orderinfo->order_total;

   $order_subtotal = $orderinfo->get_subtotal();
   
   $order_subtotal = number_format( $order_subtotal, 2 );

   $order_discount_total = $orderinfo->get_discount_total();
   
   $order_discount_total = number_format( $order_discount_total, 2 );

   $order_customer_note = $orderinfo->get_customer_note();

   

   $row = $wpdb->get_row("SELECT * FROM `wp_customer_details` WHERE `email` = '".$order_billing_email."' ");

   if(!empty($row)){

      $customerId = $row->customer_id;
      $addressId = $row->address_id;
      
      if($customerId!='' && $addressId!=''){

         $customerinfo = array(
            'customerId' => $customerId,
            'firstName' => $order_billing_first_name,
            'lastName' => $order_billing_last_name,
            'allowDuplicatePostcode'=>true,
            'initial' => "",
            'salutation' => "",
            'dob' => "",
            'company' => $order_billing_company,
            'position' => "",
            'vatNumber' => "",
            'privacy' =>false,
            'webEmail' =>$order_billing_email,
            'webPasswordOld' => 'test123',
            'webPassword' => "test123",
            'webPasswordAgain' => "test123",
            'spamOptout' => 1,
            'thirdpartyOptout' => 1,
            'postmailshot' => 0,
            'ukTaxpayer' => 1,
            'giftAid' => 0,
            'addressId' => $addressId
          );
          $client->customer_edit($customerinfo);
         
   
          $edit_addresses = array(
            array(
              'customerId' => $customerId,
              'addressId' => $addressId,
              'addName' => 'home',
              'add1' => $order_shipping_address_1,
              'add2' => $order_billing_address_2,
              'city' => $order_shipping_city,
              'state' => $order_shipping_state,
              'countryId' =>202,
              'allowDuplicatePostcode'=>true,
              'zip' => $order_shipping_postcode,
              'tel1' =>  $order_billing_phone,
              'tel2' => '',
              'fax' => ''
            )
          );
        
          $client->customer_edit_address($edit_addresses);


      }
      
   }else{

   $customer_details = array(
      'firs1tName' => $order_billing_first_name,
      'lastName' => $order_billing_last_name,
      'allowDuplicatePostcode'=>true,
      'initial' => '',
      'salutation' => '',
      'dob' => '', 
      'gender' =>'',
      'company' => $order_billing_company,
      'position' => '',
      'vatNumber' => '',
      'giftAid' => true,
      'ukTaxpayer' =>true,
      'privacy' => false,
      'webEmail' => $order_billing_email,
      'webPassword' => 'test123',
      'webPasswordAgain' => 'test123',
      'spamOptout' => 1,
      'thirdpartyOptout' => 1,
      'postmailshot' => 0,
      
      'defaultAddress' => array(
        'addName' => 'home',
        'add1' => $order_billing_address_1,
        'add2' => $order_billing_address_2,
        'city' => $order_billing_city,
        'state' => $order_billing_state,
        'countryId' => 202,
        'zip' => $order_billing_postcode,
        'tel1' => $order_billing_phone,
        'tel2' => '',
        'fax' => ''
      )
    );
     $customer_info = $client->customer_add($customer_details);

     $customerId = $customer_info->customerId;
     $addressId = $customer_info->addressId;

    
      if($customerId != ''){

         $address = array(
            'addName' => 'home',
            'add1' => $order_shipping_address_1,
            'add2' => $order_shipping_address_2,
            'city' => $order_shipping_city,
            'state' =>$order_shipping_state,
            'countryId' => 202,
            'allowDuplicatePostcode'=>true,
            'zip' => $order_shipping_postcode,
            'tel1' => $order_billing_phone,
            'tel2' => '',
            'fax' => ''
          );
          $client->customer_add_address($customerId, $address);

         $table = 'wp_customer_details';
         $data =  array('firstname' =>$order_billing_first_name, 
                        'lastname' => $order_billing_last_name,
                        'email' => $order_billing_email,
                        'customer_id' => $customerId,
                        'address_id' => $addressId
                     );
         $format = array('%s','%s','%s','%d','%d');
         $wpdb->insert($table,$data,$format);
         $my_id = $wpdb->insert_id;

      }

   }
     
    $courier_info=$client->courier_tariff(202,$order_total); //contryid,total
    $tariffId=$courier_info->value_tariffs->item->productOption->id;
    $serviceId=$courier_info->value_tariffs->item->carriageService->id;
   
    if($customerId!='' && $addressId!='' && $tariffId!='' && $serviceId!=''){

      $order_details = array(
         'websiteId' => 1,
         'customerId' => $customerId,
         'locationId' => $locationdata->item->location->id,
         'status'=>1,
         'orderTotal' => $order_total,
         'orderNote' => $order_customer_note,
         'customerOrderRef' => $order_id
      );

      $order_payments = array(
         array(
         'type' => 2,
         'total' => $order_total,
         'cardNumber' => '',
         'cardPin' => '',
         'currency' => $currency_currency
         )
         
      );

      $order_delivery = array(
         'isCollection' => false,
         'addressId' => $addressId,
         'serviceId' => 12,
         'tariffId' => 37535,
         'tariff' => $order_shipping_details['shipping_method_total'],
         'vatRate' => null,
         'recipient' => $order_billing_first_name.' '.$order_billing_last_name,
         'dateRequired' => '',
         'when' => null,
         'instructions' => '',
         'giftMessage' => '',
         'giftReceipt' => false
      );

      $cust_tran_data = $client->transaction_add(
         $order_details,
         $order_items,
         $order_delivery,
         $order_payments
      );

      $transaction_data[]=$cust_tran_data;

       /* weekly log information for traction  */

         $data = '';
         $year = date("Y");
         $month = date("m");
         $day = date("D");

         $directory = plugin_dir_path( __FILE__ )."cornjob/regular_order/$day/";

         $data .="\n ********************************** \n";
         $data .= date('Y-m-d H:i:s');
         $data .="\n ********************************** \n";
         $data .="\n Order Id=".$order_id."\n";
         $data .="\n Transaction Infotmation: \n";
         $data .=print_r($transaction_data,true);
         $data .="\n********* End ********* \n";

         $f_name =  'order-info-'.date('Y-m-d').'.txt';

         $filename = $directory.$f_name;

         $beforeweekdate = date('Y-m-d');
         $beforeweekdate = strtotime($beforeweekdate);
         $beforeweekdate = strtotime("-7 day", $beforeweekdate);
         $beforeweekfilename = 'order-info-'.date('Y-m-d', $beforeweekdate).'.txt';
         $beforeweekpath=$directory.$beforeweekfilename;

         if(!is_dir($directory)){
            
            mkdir($directory, 0775, true);

            if (!file_exists($filename)) {
               $fh = fopen($filename, 'w');
            }
            
            $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

         }else{

            if(file_exists($beforeweekpath)){

               unlink($beforeweekpath);
            }

            if (!file_exists($filename)) {
               $fh = fopen($filename, 'w') or die("Can't create file");
            }

            $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

         }

      /* end week log information */

      if($cust_tran_data->transaction->status==1){

            $transaction_id = $cust_tran_data->transaction->id;
            $cybertilorderTotal = $cust_tran_data->transaction->orderTotal;
            $cybertilordervat = $cust_tran_data->transaction->vat;

            $despatch_items=array();
            $prod_tran_datas = $client->transaction_get($transaction_id);

            if(!empty($prod_tran_datas)){

               foreach($prod_tran_datas as $prod_tran_data){
 
                  foreach($prod_tran_data->item as $item_data){

                     if($item_data->productOption->ref!='DELIVERYCLASSIC'){

                     $prod_item_id=$item_data->productOption->id;
                     $prod_item_ref=$item_data->productOption->ref;
                     $prod_item_stockQty=$item_data->stockQty;
                     
                        $despatch_items[]=array(
                           'transactionId' =>$transaction_id,
                           'itemId' =>$prod_item_id,
                           'qty' => $prod_item_stockQty,
                           'locationId' => 129,
                           'consignmentRef' => $prod_item_ref
                        );
                     

                     } 

                  }

               }

               if(!empty($despatch_items)){
                  
                  try{

                     $client->transaction_despatch_order_items(1, $despatch_items);
   
                  }catch(Exception $e){
   
                     echo $e->getMessage();
                     
                  } 

               }
            
            }

            update_post_meta($order_id, 'cybertill_order_flag', 1);
            update_post_meta($order_id, 'cybertill_transaction_id', $transaction_id);
            update_post_meta($order_id, 'cybertill_order_total', $cybertilorderTotal);
            update_post_meta($order_id, 'cybertill_order_vat', $cybertilordervat);


            $_SESSION['cybertillflag']=$order_id;
            
            
            $orderinfo->update_status( 'completed' );  

        
      }else{

         $transaction_id=0;
         $cybertilorderTotal=0;
         $cybertilordervat=0;

         update_post_meta($order_id, 'cybertill_order_flag', 0);
         update_post_meta($order_id, 'cybertill_transaction_id', $transaction_id);
         update_post_meta($order_id, 'cybertill_order_total', $cybertilorderTotal);
         update_post_meta($order_id, 'cybertill_order_vat', $cybertilordervat);

         $orderinfo->update_status( 'processing' );  

         
      }

    }else{

         $transaction_id=0;
         $cybertilorderTotal=0;
         $cybertilordervat=0;

         update_post_meta($order_id, 'cybertill_order_flag', 0);
         update_post_meta($order_id, 'cybertill_transaction_id', $transaction_id);
         update_post_meta($order_id, 'cybertill_order_total', $cybertilorderTotal);
         update_post_meta($order_id, 'cybertill_order_vat', $cybertilordervat);

         $orderinfo->update_status( 'processing' );  
        
    } 

}

/* add transaction integration */
add_action('woocommerce_thankyou', 'cybertill_order_content_data_fn', 10, 1);
function cybertill_order_content_data_fn( $order_id ) {
   session_start();

   try{

        if(!isset($_SESSION['cybertillflag'])){

            $_SESSION['cybertillflag']=0;

        }

        if(isset($_SESSION['cybertillflag']) && $_SESSION['cybertillflag']!=0 && $_SESSION['cybertillflag']!=$order_id){

            $_SESSION['cybertillflag']=0;

        }

        if(isset($_SESSION['cybertillflag']) && $_SESSION['cybertillflag']==0){

            update_post_meta($order_id, 'cybertill_order_flag', 0);
                
            echo get_order_information_data($order_id);
          
        }   

   }catch(Exception $e){
     $data = $e->getMessage();
    // print_r($data);

   }      

} 

function resyn_order_info_data($order_id){

   global $woocommerce;
   global $wpdb;

   session_start();

   $orderinfo = wc_get_order( $order_id );
   $order_data = $orderinfo->get_data();


   $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php");
   $auth_id = $client->authenticate_get('www.loakefactoryshop.co.uk', 'e18b7b83675c99e0edd840671d053f10');
   $client = new SoapClient("https://ct05072.cybertill-cloud.co.uk/current/CybertillApi_v1_6.wsdl.php", array("login" => $auth_id ));
   
   $locationdata=$client->location_web_list(1);


   /* BILLING INFORMATION: */
   $order_billing_first_name = $order_data['billing']['first_name'];
   $order_billing_last_name = $order_data['billing']['last_name'];
   $order_billing_company = $order_data['billing']['company'];
   $order_billing_address_1 = $order_data['billing']['address_1'];
   $order_billing_address_2 = $order_data['billing']['address_2'];
   $order_billing_city = $order_data['billing']['city'];
   $order_billing_state = $order_data['billing']['state'];
   $order_billing_postcode = $order_data['billing']['postcode'];
   $order_billing_country = $order_data['billing']['country'];
   $order_billing_email = $order_data['billing']['email'];
   $order_billing_phone = $order_data['billing']['phone'];

   /* SHIPPING INFORMATION: */
   $order_shipping_first_name = $order_data['shipping']['first_name'];
   $order_shipping_last_name = $order_data['shipping']['last_name'];
   $order_shipping_company = $order_data['shipping']['company'];
   $order_shipping_address_1 = $order_data['shipping']['address_1'];
   $order_shipping_address_2 = $order_data['shipping']['address_2'];
   $order_shipping_city = $order_data['shipping']['city'];
   $order_shipping_state = $order_data['shipping']['state'];
   $order_shipping_postcode = $order_data['shipping']['postcode'];
   $order_shipping_country = $order_data['shipping']['country'];


   /* ORDER ITEMS INFORMATION */
   $order_items = array();
   $order_all_data = array();

   $order_details = array();

   foreach($orderinfo->get_items() as $item_key => $item ){

      $item_id = $item->get_id();
      $product_id = $item->get_product_id(); 
      $variation_id = $item->get_variation_id(); 

      $item_name    = $item->get_name(); 
      $quantity     = $item->get_quantity();  
      $tax_class    = $item->get_tax_class();
      $line_subtotal     = $item->get_subtotal(); 
      $line_subtotal_tax = $item->get_subtotal_tax(); 
      $line_total        = $item->get_total();   
      $line_total_tax    = $item->get_total_tax(); 

      $product        = $item->get_product();

      $product_type   = $product->get_type();
      $product_sku    = $product->get_sku();
      $product_price  = $product->get_price();
      $stock_quantity = $product->get_stock_quantity();



      $cybertill_parent_product_id = get_post_meta($product_id,'ref_product_id',true);

      if($cybertill_parent_product_id!=''){

         $prod_stocks = $client->stock_product($cybertill_parent_product_id , $locationdata->item->location->id);

         if(!empty($prod_stocks->item)){


            foreach($prod_stocks->item as $prod_stock){

               $product_item = $client->item_get($prod_stock->stkItemId,$locationdata->item->location->id);
               $ref_sku = explode(":",$product_item->productOption->ref);

               if($ref_sku[0].$ref_sku[2]==$product_sku){

                 
                 $order_items[] = array(
                     'itemId' => $prod_stock->stkItemId,
                     'salesQty' =>$quantity,
                     'itemPrice' =>$product_price,
                     'discountPrice' =>null,
                     'note' => '',
                     'vatRate' =>null,
                     'issuedValue' =>null
                 );

               }
           
            }
            
         }

      }


      $order_all_data[]=array(
         'item_id'=>$item_id,
         'product_id'=>$product_id,
         'variation_id'=>$variation_id,
         'item_name'=>$item_name,
         'quantity'=>$quantity,
         'tax_class'=>$tax_class,
         'line_subtotal'=>$line_subtotal,
         'line_subtotal_tax'=>$line_subtotal_tax,
         'line_total'=>$line_total,
         'line_total_tax'=>$line_total_tax,
         'product_type'=>$product_type,
         'product_sku'=>$product_sku,
         'product_price'=>$product_price,
         'stock_quantity'=>$stock_quantity
      );

     /*  $order_items[] = array(
         'itemId' => $item_id,
         'salesQty' =>$quantity,
         'itemPrice' =>$product_price,
         'discountPrice' =>null,
         'note' => '',
         'vatRate' =>null,
         'issuedValue' =>null
      ); */

   }

   /* ORDER SHIPPING INFORAMTION */
   $order_shipping_details = array();
   foreach( $orderinfo->get_items( 'shipping' ) as $item_id => $shipping_item_obj ){
      $order_item_name             = $shipping_item_obj->get_name();
      $order_item_type             = $shipping_item_obj->get_type();
      $shipping_method_title       = $shipping_item_obj->get_method_title();
      $shipping_method_id          = $shipping_item_obj->get_method_id(); 
      $shipping_method_instance_id = $shipping_item_obj->get_instance_id();
      $shipping_method_total       = $shipping_item_obj->get_total();
      $shipping_method_total_tax   = $shipping_item_obj->get_total_tax();
      $shipping_method_taxes       = $shipping_item_obj->get_taxes();

      $order_shipping_details = array(
         'order_item_name'=>$order_item_name,
         'order_item_type'=>$order_item_type,
         'shipping_method_title'=>$shipping_method_title,
         'shipping_method_id'=>$shipping_method_id,
         'shipping_method_instance_id'=>$shipping_method_instance_id,
         'shipping_method_total'=>$shipping_method_total,
         'shipping_method_total_tax'=>$shipping_method_total_tax,
         'shipping_method_taxes'=>$shipping_method_taxes
      );
   }

   /* ORDER TOTAL INFORMATION */

   $currency_symbol = get_woocommerce_currency_symbol( get_woocommerce_currency() );

   $currency_currency = get_woocommerce_currency();
      
   $order_total = is_callable(array($orderinfo, 'get_total')) ? $orderinfo->get_total() : $orderinfo->order_total;

   $order_subtotal = $orderinfo->get_subtotal();
   
   $order_subtotal = number_format( $order_subtotal, 2 );

   $order_discount_total = $orderinfo->get_discount_total();
   
   $order_discount_total = number_format( $order_discount_total, 2 );

   $order_customer_note = $orderinfo->get_customer_note();

   $row = $wpdb->get_row("SELECT * FROM `wp_customer_details` WHERE `email` = '".$order_billing_email."'");

   if(!empty($row)){

      $customerId = $row->customer_id;
      $addressId = $row->address_id;
      
      if($customerId!='' && $addressId!=''){

         $customerinfo = array(
            'customerId' => $customerId,
            'firstName' => $order_billing_first_name,
            'lastName' => $order_billing_last_name,
            'allowDuplicatePostcode'=>true,
            'initial' => "",
            'salutation' => "",
            'dob' => "",
            'company' => $order_billing_company,
            'position' => "",
            'vatNumber' => "",
            'privacy' =>false,
            'webEmail' =>$order_billing_email,
            'webPasswordOld' => 'test123',
            'webPassword' => "test123",
            'webPasswordAgain' => "test123",
            'spamOptout' => 1,
            'thirdpartyOptout' => 1,
            'postmailshot' => 0,
            'ukTaxpayer' => 1,
            'giftAid' => 0,
            'addressId' => $addressId
          );
          $client->customer_edit($customerinfo);
         
   
          $edit_addresses = array(
            array(
              'customerId' => $customerId,
              'addressId' => $addressId,
              'addName' => 'home',
              'add1' => $order_shipping_address_1,
              'add2' => $order_billing_address_2,
              'city' => $order_shipping_city,
              'state' => $order_shipping_state,
              'countryId' =>202,
              'allowDuplicatePostcode'=>true,
              'zip' => $order_shipping_postcode,
              'tel1' =>  $order_billing_phone,
              'tel2' => '',
              'fax' => ''
            )
          );
        
          $client->customer_edit_address($edit_addresses);


      }
      
   }else{

   $customer_details = array(
      'firs1tName' => $order_billing_first_name,
      'lastName' => $order_billing_last_name,
      'allowDuplicatePostcode'=>true,
      'initial' => '',
      'salutation' => '',
      'dob' => '', 
      'gender' =>'',
      'company' => $order_billing_company,
      'position' => '',
      'vatNumber' => '',
      'giftAid' => true,
      'ukTaxpayer' =>true,
      'privacy' => false,
      'webEmail' => $order_billing_email,
      'webPassword' => 'test123',
      'webPasswordAgain' => 'test123',
      'spamOptout' => 1,
      'thirdpartyOptout' => 1,
      'postmailshot' => 0,
      
      'defaultAddress' => array(
        'addName' => 'home',
        'add1' => $order_billing_address_1,
        'add2' => $order_billing_address_2,
        'city' => $order_billing_city,
        'state' => $order_billing_state,
        'countryId' => 202,
        'zip' => $order_billing_postcode,
        'tel1' => $order_billing_phone,
        'tel2' => '',
        'fax' => ''
      )
    );
     $customer_info = $client->customer_add($customer_details);

     $customerId = $customer_info->customerId;
     $addressId = $customer_info->addressId;

    
      if($customerId != ''){

         $address = array(
            'addName' => 'home',
            'add1' => $order_shipping_address_1,
            'add2' => $order_shipping_address_2,
            'city' => $order_shipping_city,
            'state' =>$order_shipping_state,
            'countryId' => 202,
            'allowDuplicatePostcode'=>true,
            'zip' => $order_shipping_postcode,
            'tel1' => $order_billing_phone,
            'tel2' => '',
            'fax' => ''
          );
          $client->customer_add_address($customerId, $address);

         $table = 'wp_customer_details';
         $data =  array('firstname' =>$order_billing_first_name, 
                        'lastname' => $order_billing_last_name,
                        'email' => $order_billing_email,
                        'customer_id' => $customerId,
                        'address_id' => $addressId
                     );
         $format = array('%s','%s','%s','%d','%d');
         $wpdb->insert($table,$data,$format);
         $my_id = $wpdb->insert_id;

      }

   }
     
    $courier_info=$client->courier_tariff(202,$order_total);//contryid,total
    $tariffId=$courier_info->value_tariffs->item->productOption->id;
    $serviceId=$courier_info->value_tariffs->item->carriageService->id;
    
    if($customerId!='' && $addressId!='' && $tariffId!='' && $serviceId!=''){

      $order_details = array(
         'websiteId' => 1,
         'customerId' => $customerId,
         'locationId' => $locationdata->item->location->id,
         'status'=>1,
         'orderTotal' => $order_total,
         'orderNote' => $order_customer_note,
         'customerOrderRef' =>$order_id
      );

      $order_payments = array(
         array(
         'type' => 2,
         'total' => $order_total,
         'cardNumber' => '',
         'cardPin' => '',
         'currency' => $currency_currency
         )
         
      );

      $order_delivery = array(
         'isCollection' => false,
         'addressId' => $addressId,
         'serviceId' => 12,
         'tariffId' => 37535,
         'tariff' => $order_shipping_details['shipping_method_total'],
         'vatRate' => null,
         'recipient' => $order_billing_first_name.' '.$order_billing_last_name,
         'dateRequired' => '',
         'when' => null,
         'instructions' => '',
         'giftMessage' => '',
         'giftReceipt' => false
      );

      $cust_tran_data = $client->transaction_add(
         $order_details,
         $order_items,
         $order_delivery,
         $order_payments
      );

      $transaction_data[]=$cust_tran_data;

      /* weekly log information for traction  */

        $data = '';
        $year = date("Y");
        $month = date("m");
        $day = date("D");

        $directory = plugin_dir_path( __FILE__ )."cornjob/resynch_order/$day/";

        $data .= "\n ********************************** \n";
        $data .= date('Y-m-d H:i:s');
        $data .= "\n ********************************** \n";
        $data .= "\n Order Id=".$order_id."\n";
        $data .= "\n Resyn Transaction Infotmation: \n";
        $data .= print_r($transaction_data,true);
        $data .= "\n ********* End ********* \n";

        $f_name =  'resynorder-info-'.date('Y-m-d').'.txt';

        $filename = $directory.$f_name;

        $beforeweekdate = date('Y-m-d');
        $beforeweekdate = strtotime($beforeweekdate);
        $beforeweekdate = strtotime("-7 day", $beforeweekdate);
        $beforeweekfilename = 'resynorder-info-'.date('Y-m-d', $beforeweekdate).'.txt';
        $beforeweekpath=$directory.$beforeweekfilename;

        if(!is_dir($directory)){
           
           mkdir($directory, 0775, true);

           if (!file_exists($filename)) {
              $fh = fopen($filename, 'w');
           }
           
           $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

        }else{

           if(file_exists($beforeweekpath)){

              unlink($beforeweekpath);
           }

           if (!file_exists($filename)) {
              $fh = fopen($filename, 'w') or die("Can't create file");
           }

           $ret = file_put_contents($filename, $data, FILE_APPEND | LOCK_EX);

        }

     /* end week log information */

      if($cust_tran_data->transaction->status==1){

         $transaction_id = $cust_tran_data->transaction->id;
         $cybertilorderTotal = $cust_tran_data->transaction->orderTotal;
         $cybertilordervat = $cust_tran_data->transaction->vat;

         $despatch_items=array();

         $prod_tran_datas = $client->transaction_get($transaction_id);

         if(!empty($prod_tran_datas)){

            foreach($prod_tran_datas as $prod_tran_data){
  
               foreach($prod_tran_data->item as $item_data){

                  if($item_data->productOption->ref!='DELIVERYCLASSIC'){

                  $prod_item_id=$item_data->productOption->id;
                  $prod_item_ref=$item_data->productOption->ref;
                  $prod_item_stockQty=$item_data->stockQty;
                  
                     $despatch_items[]=array(
                        'transactionId' =>$transaction_id,
                        'itemId' =>$prod_item_id,
                        'qty' => $prod_item_stockQty,
                        'locationId' => 129,
                        'consignmentRef' => $prod_item_ref
                     );
                  

                  } 

               }

            }

            if(!empty($despatch_items)){
               try{

                  $client->transaction_despatch_order_items(1, $despatch_items);

               }catch(Exception $e){

                  echo $e->getMessage();

               }  
            }
         
         }

         update_post_meta($order_id, 'cybertill_order_flag', 1);
         update_post_meta($order_id, 'cybertill_transaction_id', $transaction_id);
         update_post_meta($order_id, 'cybertill_order_total', $cybertilorderTotal);
         update_post_meta($order_id, 'cybertill_order_vat', $cybertilordervat);

         $_SESSION['cybertillflag']=$order_id;

         $orderinfo->update_status( 'completed' ); 
        
      }else{

         $transaction_id=0;
         $cybertilorderTotal=0;
         $cybertilordervat=0;

         update_post_meta($order_id, 'cybertill_order_flag', 0);
         update_post_meta($order_id, 'cybertill_transaction_id', $transaction_id);
         update_post_meta($order_id, 'cybertill_order_total', $cybertilorderTotal);
         update_post_meta($order_id, 'cybertill_order_vat', $cybertilordervat);

         $orderinfo->update_status( 'processing' ); 
         
      }

    }else{

         $transaction_id=0;
         $cybertilorderTotal=0;
         $cybertilordervat=0;

         update_post_meta($order_id, 'cybertill_order_flag', 0);
         update_post_meta($order_id, 'cybertill_transaction_id', $transaction_id);
         update_post_meta($order_id, 'cybertill_order_total', $cybertilorderTotal);
         update_post_meta($order_id, 'cybertill_order_vat', $cybertilordervat);
        
         $orderinfo->update_status( 'processing' ); 
    } 

}

/* resynchronize order */

function resychronize_cybertill_om_request_fn(){

   $orders = wc_get_orders( array(
      'limit'        => -1, 
      'orderby'      => 'date',
      'order'        => 'DESC',
      'meta_key'     => 'cybertill_order_flag',
      'meta_value'   => 0, 
      'meta_compare' => '=', 
   ));

   if(!empty($orders)){
        
      foreach($orders as $orderinfo){

         $tranid = !empty($orderinfo->get_transaction_id())?$orderinfo->get_transaction_id():'';

          if(!empty($tranid)){
          
            $order_id=$orderinfo->get_id();
            echo resyn_order_info_data($order_id);
            
          }

      }    
   }

}

/* add part dispatched order status */

add_filter( 'woocommerce_register_shop_order_post_statuses', 'cybertill_register_part_despatched_order_status' );
 
function cybertill_register_part_despatched_order_status( $order_statuses ){
    
   $order_statuses['wc-part-despatched'] = array(                                 
   'label'                     => _x( 'Part Despatched', 'Order status', 'woocommerce' ),
   'public'                    => false,                                 
   'exclude_from_search'       => false,                                 
   'show_in_admin_all_list'    => true,                                 
   'show_in_admin_status_list' => true,                                 
   'label_count'               => _n_noop( 'Part Despatched <span class="count">(%s)</span>', 'Part Despatched <span class="count">(%s)</span>', 'woocommerce' ),                              
   );
         
   return $order_statuses;
}
 
 
add_filter( 'wc_order_statuses', 'cybertill_show_part_despatched_order_status' );
 
function cybertill_show_part_despatched_order_status( $order_statuses ) {      
   $order_statuses['wc-part-despatched'] = _x( 'Part Despatched', 'Order status', 'woocommerce' );       
   return $order_statuses;
}
 
add_filter( 'bulk_actions-edit-shop_order', 'cybertill_get_part_despatched_order_status_bulk' );
 
function cybertill_get_part_despatched_order_status_bulk( $bulk_actions ) {
  
   $bulk_actions['part_despatched-status'] = 'Change status to custom status';
   return $bulk_actions;
}

/* add despatched order status */


 add_filter( 'woocommerce_register_shop_order_post_statuses', 'cybertill_register_despatched_order_status' );
 
function cybertill_register_despatched_order_status( $order_statuses ){
    
   $order_statuses['wc-despatched'] = array(                                 
   'label'                     => _x( 'Despatched', 'Order status', 'woocommerce' ),
   'public'                    => false,                                 
   'exclude_from_search'       => false,                                 
   'show_in_admin_all_list'    => true,                                 
   'show_in_admin_status_list' => true,                                 
   'label_count'               => _n_noop( 'Despatched <span class="count">(%s)</span>', 'Despatched <span class="count">(%s)</span>', 'woocommerce' ),                              
   );
         
   return $order_statuses;
}
 
 
add_filter( 'wc_order_statuses', 'cybertill_show_despatched_order_status' );
 
function cybertill_show_despatched_order_status( $order_statuses ) {      
   $order_statuses['wc-despatched'] = _x( 'Despatched', 'Order status', 'woocommerce' );       
   return $order_statuses;
}
 
add_filter( 'bulk_actions-edit-shop_order', 'cybertill_get_despatched_order_status_bulk' );
 
function cybertill_get_despatched_order_status_bulk( $bulk_actions ) {
  
   $bulk_actions['despatched-status'] = 'Change status to custom status';
   return $bulk_actions;
} 

?>