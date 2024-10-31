<?php
/**
 * Plugin Name: PLINK Payment Gateway Woocommerce
 * Plugin URI: http://plink.co.id/
 * Description: PLINK Payment gateway (Virtual Account, Credit Card, QRIS, Direct Debit).
 * Author: Prismalink International
 * Author URI: http://prismalink.co.id/
 * Version: 2.0.8
 * Text Domain: plink-payment-gateway-woocommerce
 * Domain Path: /languages
 */
add_action( 'init', 'wpdocs_load_textdomain' );
 
function wpdocs_load_textdomain() {
    load_plugin_textdomain( 'plink-payment-gateway-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); 
}

add_action('plugins_loaded', 'woocommerce_plinkpg_init', 0);
function construct_merchant_refno($merchant_id,$order_id){
    return 'WOO-'.$merchant_id.'-'.$order_id;
}
function woocommerce_plinkpg_init(){
    if(!class_exists('WC_Payment_Gateway')) return;

    class WC_Gateway_PLINK_PG extends WC_Payment_Gateway{
        public function __construct(){
            $this->id                   = 'plinkpg'; //unique reference for the gateway
            $this->icon                 = WP_PLUGIN_URL . "/" . basename(dirname(__FILE__)) . '/assets/img/plink-logo.png';
            $this->has_fields           = false;
            $this->method_title         = __( 'PLINK Payment Gateway', 'plink-payment-gateway-woocommerce' );
            $this->method_description   = __( 'PLINK Payment Gateway (Virtual Account, Credit Card, QRIS, Direct Debit.)', 'plink-payment-gateway-woocommerce' );
            $this->initTable();
            $this->init_form_fields();
            $this->init_settings();

            foreach ( $this->settings as $setting_key => $value ) {
                $this->$setting_key = $value;
                
                if($setting_key == 'frontend_callback_url'){
                    $this->settings['frontend_callback_url'] = get_permalink( wc_get_page_id( 'shop' ));
                }
                if($setting_key == 'backend_callback_url'){
                    $this->settings['backend_callback_url'] = get_rest_url(null, 'plinkpg/action/set-payment-flag');
                }
            }
            
            
            if (is_admin()) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
        }
        /**
        * Plugin options
        */
        public function init_form_fields(){
            $this->form_fields = array(
                'enabled'   => array(
                    'title'   => __('Enable', 'plink-payment-gateway-woocommerce'),
                    'type'    => 'checkbox',
                    'label'   => __('Enable PLINK Payment Gateway Module.', 'plink-payment-gateway-woocommerce' ),
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => __('Title', 'plink-payment-gateway-woocommerce'),
                    'type'        => 'text',
                    'description' => __('The title which user sees when checkout.', 'plink-payment-gateway-woocommerce' ),
                    'default' 	  => "PLINK Payment Gateway"
                ),
                'phase' => array(
                    'title'   => __('Phase', 'plink-payment-gateway-woocommerce' ),
                    'type'    => 'select',
                    'options' => array(
                        "testing"     => "Test",
                        "live"  => "Live",
                    ),
                    'description' => __('PLINK plugin phase.', 'plink-payment-gateway-woocommerce' )
                ),
                'secret_key' => array(
                    'title'       => __('Secret Key', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'text',
                    'description' => __('Secret Key given by PLINK', 'plink-payment-gateway-woocommerce' )
                ),
                'merchant_id' => array(
                    'title'       => __('Merchant ID', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'text',
                    'description' => __('Your ID given by PLINK', 'plink-payment-gateway-woocommerce' )
                ),
                'merchant_key_id' => array(
                    'title'       => __('Merchant Key ID', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'text',
                    'description' => __('Your Key ID given by PLINK', 'plink-payment-gateway-woocommerce' )
                ),
                'payment_validity' => array(
                    'default'     => 1440,
                    'title'       => __('Virtual account expired time*(in minutes)*', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'number',
                    'description' => __('Transaction expired time for Virtual Account after order placed. <br>For example: 60 (for 1 hour), 1440 (for 1 day), 4320 (for 3 days), 10080 (for 1 week)', 'plink-payment-gateway-woocommerce' )
                ),
                'paymentpage_type' => array(
                    'title'       => __('Paymentpage', 'plink-payment-gateway-woocommerce' ),
                    'type'    => 'select',
                    'options' => array(
                        "new_tab"     => __('New Tab', 'plink-payment-gateway-woocommerce' ),
                        // "parent_window"  => __('Popup', 'plink-payment-gateway-woocommerce' ),
                        "same_window"  => __('Redirect Same Tab', 'plink-payment-gateway-woocommerce' ),
                    ),
                    'description' => __('How paymentpage shows after place order', 'plink-payment-gateway-woocommerce' )
                ),
                'confirm_text_button' => array(
                    'title'       => __('Confirm text button ', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'text',
                    'description' => __('Your text button for button confirm payment', 'plink-payment-gateway-woocommerce' )
                ),
                'other_payment_text_button' => array(
                    'title'       => __('Other payment method text button', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'text',
                    'description' => __('Your text button for button other payment method', 'plink-payment-gateway-woocommerce' )
                ),
                'description' => array(
                    'title'       => __('Description','plink-payment-gateway-woocommerce'),
                    'default'     =>__('Checkout with PLINK Payment Gateway(Virtual Account, Credit Card, Direct Debit)','plink-payment-gateway-woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.','plink-payment-gateway-woocommerce'),
                ),
                'backend_callback_url' => array(
                    'default'     => get_permalink( wc_get_page_id( 'plinkpg-paymentflag' )),
                    'title'       => __('Backend Callback Url', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'text',
                    'disabled'   =>true,
                    'description' => __('*Backend callback url needed to be registered before start using this plugin', 'plink-payment-gateway-woocommerce' ),
                    
                ),
                'frontend_callback_url' => array(
                    'default'     => get_permalink( wc_get_page_id( 'shop' )),
                    'value'       =>  'alo',
                    'title'       => __('Frontend Callback Url', 'plink-payment-gateway-woocommerce' ),
                    'type'        => 'text',
                    'disabled'   =>true,
                    'description' => __('Frontend callback url needed to be registered before start using this plugin', 'plink-payment-gateway-woocommerce' ),
                    
                ),
                
            );
        }
        /**
        * @Addtitional option to create table for plink to monitor 
        */
        public function initTable(){
            global $wpdb;
            $table_name = $wpdb->prefix.'plinkpg';
            $sql = "CREATE TABLE IF NOT EXISTS ".$table_name." (
                order_id INT(18) NOT NULL, 
                payment_status varchar(10) NOT NULL,
                user_id varchar(255) NOT NULL,
                plink_ref_no varchar(255) NOT NULL,
                merchant_ref_no varchar(255) NOT NULL,
                email varchar(255) NOT NULL,
                order_created varchar(255) NOT NULL,
                payment_date varchar(255),
                trx_type varchar(255),
                PRIMARY KEY ( order_id )
            );";
            require_once(ABSPATH.'wp-admin/includes/upgrade.php' );
            $wpdb->query($sql);
        }

        function process_payment($order_id){ //PLACE ORDER
            $order = wc_get_order( $order_id );
            session_start();
            //check credentials
            $merchOption = get_option('woocommerce_plinkpg_settings');
            $isEmptyOpt = false;
            $opts = ['secret_key','phase','merchant_id','merchant_key_id'];
            foreach($opts as $opt){
                if(empty($merchOption[$opt])){
                    $isEmptyOpt  = true;
                    break;
                }
            }
            
            //is IDR
            if($order->get_currency() !== "IDR"){
                wc_add_notice(__("This payment method requires 'IDR' for currency","plink-payment-gateway-woocommerce"),'error');
            }
            //is minimum payment amount 10000
            else if(intval(ceil($order->get_total())) < 10000){
                wc_add_notice(__("Minimum payment amount is IDR 10,000","plink-payment-gateway-woocommerce"),'error');
            }
            else if($isEmptyOpt){
                wc_add_notice(__("Merchant credential for this plugin is incomplete","plink-payment-gateway-woocommerce"),'error');
            }
            else{
                return array(
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_payment_url( true )
                );
            }
        }

        function receipt_page($order){                 
            $merchOption = get_option('woocommerce_plinkpg_settings');
            $img_url=esc_url(WP_PLUGIN_URL."/".basename(dirname(__FILE__)) . "/assets/img/plink-logo.png");
            wp_enqueue_style('google-fonts','https://fonts.googleapis.com/css2?family=Poppins:wght@300;700&display=swap');
            echo "<style>
                    .plink-payment-checkout-msg{
                        padding:20px 30px;
                        font-weight: bold;
                        -webkit-box-shadow: 0px 0px 15px -6px rgba(0,0,0,0.93);
                        -moz-box-shadow: 0px 0px 15px -6px rgba(0,0,0,0.93);
                        box-shadow: 0px 0px 15px -6px rgba(0,0,0,0.93);
                        margin-top:20px;
                        background:#ffff;
                        color:#000;
                        border-radius:2vh;
                    }
                    .plink-payment-checkout-msg img{
                        width:10rem;
                    }
                </style>
            ";  
            echo '<div class="plink-payment-checkout-msg">';
            echo '<img src="'.$img_url.'"/>';
            echo '<p>'.__('Thank you for your order, please click confirmation button below to pay with PLINK Payment Gateway.', 'plink-payment-gateway-woocommerce').'</p>';
            if($merchOption['phase'] === 'testing'){
                echo '<p style="color:red;">('.__("Testing phase detected - real payment won't be created", 'plink-payment-gateway-woocommerce').")</p>";
            }
            echo "</div>";
            echo esc_html( $this->generate_plinkpg_checkout_form($order) );
            echo "<br>";
        }

        function generate_plinkpg_checkout_form($order_id){
            $merchOption = get_option('woocommerce_plinkpg_settings');
            
            global $woocommerce;
            $out='';
            $out .= '<form style="border:none" sprbk-form method="post" action="'.esc_url(get_rest_url(null, 'plinkpg/action/plinkpg-redirection')).'"';
            if($merchOption['paymentpage_type']==='same_window'){
                $out.= '>';
            }else{
                $out.= 'target="_blank">';
            }
            $out .= '<input type="hidden" name="order_id" value="'.sanitize_key($order_id).'" >';
            $out .= '<br><br><button class="button alt btn-plinkpg" style="float:left;border-radius:2vh" type="button" onclick="document.location.href=\''. wc_get_checkout_url() .'\'" >'.esc_html(__(empty($merchOption['other_payment_text_button']) ? __('Other payment method','plink-payment-gateway-woocommerce') : $merchOption['other_payment_text_button'] )).'</button>&nbsp;';
            $out .= '<button class="button alt btn-plinkpg" style="float:right;border-radius:2vh" type="submit" >'.esc_html(__(empty($merchOption['confirm_text_button']) ? __('Confirm order','plink-payment-gateway-woocommerce') : $merchOption['confirm_text_button'])).'</button>';
            $out .= '</form>';
            echo $out;
        }
    }

    /*
    * Payment page redirect
    */
    if(!function_exists("plinkpg_redirection")){
        function plinkpg_redirection(){
            global $woocommerce,$post;
            global $wpdb;

            $transaction_time = date("Y-m-d H:i:s").substr((string)microtime(), 1, 4).date(' O');
            
            $merchOption = get_option('woocommerce_plinkpg_settings');
            $order_id = sanitize_key($_POST['order_id']); //ambil post data
            $table_name = $wpdb->prefix.'plinkpg';
            $order = wc_get_order($order_id); //ambil data order
             
            if(!isset($order_id)){ plink_generate_msg(__("Your order has expired please place order again",'plink-payment-gateway-woocommerce'),wc_get_checkout_url());  exit; }
            if (!$order){ plink_generate_msg(__("Order not found",'plink-payment-gateway-woocommerce'),wc_get_checkout_url());; exit;}
            
            
            $sql_check = "SELECT count(*) FROM ".$table_name." WHERE order_id=".$order_id;
            $query_check=$wpdb->get_var($sql_check);

            $merchant_ref_no = construct_merchant_refno($merchOption['merchant_id'],$order_id);
            
            /* Check if not exist */
            if($query_check == 0){
                /* Insert Order into plink order history table */
                $sql = "INSERT INTO ".$table_name." (order_id,payment_status,user_id,plink_ref_no,merchant_ref_no,email,order_created,payment_date)
                VALUES('".$order_id."','','".$order->get_customer_id()."','','".$merchant_ref_no."','".$order->get_billing_email()."','".date('Y-m-d H:i:s')."','');";
                
                $query = $wpdb->query($sql);
                
                if(!$query){ plink_generate_msg("Sorry Can't insert new payment history ",wc_get_checkout_url()); exit; }
                $order->update_status('pending', 'PLINK Paymentgateway Awaiting payment'); //update status
            }
            
            $actionurl = array(
                "testing"     => 'https://api-staging.plink.co.id',
                "live"  => 'https://secure2.plink.co.id',
            );
            
            $payment_page_url = array(
                "testing"     => 'https://secure2-staging.plink.co.id',
                "live"  => 'https://secure2.plink.co.id',
            );
            
            $endpoint = array(
                "testing" => '/gateway/v2/payment/integration/transaction/api/submit-trx',
                "live" => '/integration/transaction/api/submit-trx'
            );

            $frontend_callback_url = array(
                "testing" => get_permalink( wc_get_page_id( 'shop' )),
                "live"  => get_permalink( wc_get_page_id( 'shop' )),
            );

            $backend_callback_url = array(
                "testing" => get_rest_url(null, 'plinkpg/action/set-payment-flag'),
                "live"  => get_rest_url(null, 'plinkpg/action/set-payment-flag'),
            );
            

            if (class_exists('WC_Seq_Order_Number')) { $transactionNo = WC_Seq_Order_Number::get_order_number($order_id, $order);} 
            else { $transactionNo = $order_id; }
            
            //header
            $header=array(
                'Content-Type' => 'application/json'
            );

            /* Payload */
            $product_details=array();
            
            //remapping items data
            foreach($order->get_items() as $item_id => $item){
                $product_details[]=array(
                    "item_code" => $item->get_product_id(), 
                    "item_title" => $item->get_name(), 
                    "quantity" => $item->get_quantity(), 
                    "total" => intval(ceil($item->get_total()/$item->get_quantity())), 
                    "currency" => "IDR" 
                );
            }

            $other_bills=array(
                array( "title" => __('Tax',"plink-payment-gateway-woocommerce"),"value" => intval(ceil($order->get_total_tax()))),
                array( "title" => __('Shipping',"plink-payment-gateway-woocommerce"),"value" => intval(ceil($order->get_total_shipping())))
            );
            
            $shipping_address=[
                "address" => $order->get_formatted_billing_address(), 
                "telephoneNumber" => $order->get_billing_phone(), 
                "handphoneNumber" => "" 
            ];
            $fase=$merchOption['phase'];
            

            $va_name=$order->get_billing_first_name()." ".$order->get_billing_last_name();
            $max_length=20;
            
            if(strlen($va_name)>$max_length){ $va_name=substr($va_name,0,20); }

            
            $invoice_number = 'WOOINV-'.$merchOption['merchant_id'].'-'.$order->get_order_number();
            
            
            $validity = strtotime("+".($merchOption['payment_validity'] ?? 1440)." minutes");
			$validity_formatted = date("Y-m-d H:i:s",$validity).'.000'.date(' O',$validity);
            $body=[
                "invoice_number"=> $invoice_number,
                "merchant_id" => $merchOption['merchant_id'], 
                "merchant_key_id" => $merchOption['merchant_key_id'], 
                "merchant_ref_no" => $merchant_ref_no, 
                "transaction_currency" => "IDR", 
                "transaction_amount" => intval(ceil($order->get_total())), 
                "product_details" => json_encode($product_details),
                "va_name" => $va_name,
                "user_name" => $order->get_billing_first_name()." ".$order->get_billing_last_name(), 
                "user_email" => $order->get_billing_email(), 
                "user_phone_number" => plink_format_phone_number( $order->get_billing_phone()), 
                "user_id" => $order->get_customer_id(), 
                "backend_callback_url" =>$backend_callback_url[$fase], 
                "frontend_callback_url" => $frontend_callback_url[$fase], 
                "transaction_date_time" => $transaction_time, 
                "transmission_date_time" => date("Y-m-d H:i:s").substr((string)microtime(), 1, 4).date(' O'), 
                "remarks" => $order->get_customer_note(), 
                "user_device_id" => $order->get_customer_user_agent(), 
                "user_ip_address" => $order->get_customer_ip_address() ?? '127.0.0.1', 
                "shipping_details" => json_encode($shipping_address), 
                "payment_method" => "", 
                "other_bills" => json_encode($other_bills),
                "validity"=>$validity_formatted
            ];

            
            $data=array(
                'order_id'=> $order_id,
                'iframe_url'=>$payment_page_url[$fase],
                'base_url' => $actionurl[$fase],
                'end_point' => $endpoint[$fase],
                'body' => $body,
                'header' => $header,
                'secret_key' =>$merchOption['secret_key']
            );

            plinkpg_inqury_payment_status($data,$order_id);
            call_to_pg($data);
        }
    }

    /*
    * Create plink log
    */
    if(!function_exists("plink_log")){
        function plink_log($msg,$type='info'){
            $log_prefix="PLINK_PG";
            $merchOption = get_option('woocommerce_plinkpg_settings');
            // if($merchOption->phase === 'development'){
                $log = new WC_Logger();
                switch($type){
                    case "error":
                        $log->error($log_prefix." : ".$msg);
                        break;
                    case "critical":
                        $log->critical($log_prefix." : ".$msg);
                        break;
                    case "debug":
                        $log->debug($log_prefix." : ".$msg);
                        break;
                    case "info":
                        $log->info($log_prefix." : ".$msg);
                        break;
                    case "warning":
                        $log->warning($log_prefix." : ".$msg);
                        break;
                    case "notice":
                        $log->notice($log_prefix." : ".$msg);
                        break;
                    default:
                        break;            
                }
            // }
        }
    }
    
    /*
    * Convert 0 phone number to +6
    */
    if(!(function_exists("plink_format_phone_number"))){
        function plink_format_phone_number($nohp) {
            // for ex. 0811 239 345
            $nohp = str_replace(" ","",$nohp);
            // for ex. (0274) 778787
            $nohp = str_replace("(","",$nohp);
            // for ex. (0274) 778787
            $nohp = str_replace(")","",$nohp);
            // for ex. 0811.239.345
            $nohp = str_replace(".","",$nohp);
        
            // check nubmer contain characters + and 0-9
            if(!preg_match('/[^+0-9]/',trim($nohp))){
                // check number wheter its contain 1-3 adalah +62
                if(substr(trim($nohp), 0, 3)=='+62'){
                    $hp = trim($nohp);
                }
                // check if number 1 is 0
                elseif(substr(trim($nohp), 0, 1)=='0'){
                    $hp = '+62'.substr(trim($nohp), 1);
                }
            }
            return $hp;
        }
    }

    /*
    * Call merchant to backend return a plink url
    */
    if(!(function_exists("call_to_pg"))){
        function call_to_pg($data){
            global $woocommerce,$wpdb;
            $order = wc_get_order($data['order_id']); 
                try {
                    $merchOption = get_option('woocommerce_plinkpg_settings');
                    /*  create HMAC and update header */
                    $key = $data['secret_key']; //secret key
                    $payload = json_encode($data['body'],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
                    $token = hash_hmac('sha256', $payload, $key);
                    $data['header']['mac'] = $token;
                    $header = $data['header'];
                    $url = $data['base_url'].$data['end_point'];

                    //log data
                    plink_log("(Call to pg) Send data to PLINK backend : ".print_r(json_encode($data),TRUE),"notice");
                    
                    /*  call to pg backend */
                    $res = wp_remote_post($url, array(
                        'method'      => 'POST',
                        'timeout'     => 300,
                        'headers'     => $data['header'],
                        'body'        => $payload
                    ));

                    if(is_wp_error($res)){
                        throw new Exception($res->get_error_message());
                    }

                    if(!$res || !isset($res['body'])){
                        throw new Exception("Payment Page Not Found");
                    }

                    $result = json_decode($res['body']);
                    
                    if(!isset($result->response_code)){
                        plink_log("(Call to pg) call to backend : ".print_r($result),"error");
                        throw new Exception("System Error");
                    }

                    if($result->response_code === "PL000" || $result->response_code === "MBDD00"){
                        $response_incoming= array(
                            "response_code" =>$result->response_code,
                            "response_message" =>$result->response_message,
                            "response_description" =>$result->response_description,
                            "paymentpage_url" =>$result->payment_page_url,
                            "plink_ref_no" =>$result->plink_ref_no,
                            "va_number_list" =>$result->va_number_list
                        );

                        plink_log("(Call to pg) Get data from PLINK backend : ".print_r($response_incoming,TRUE),"notice");
                        
                        $table_name = $wpdb->prefix.'plinkpg';
                        
                        $sql_check = "SELECT payment_status FROM ".$table_name." WHERE order_id=".$data['order_id']." LIMIT 1";
                        $query_check=$wpdb->get_row($sql_check);
                        

                        $sql_update = "UPDATE ".$table_name." SET plink_ref_no = '".$response_incoming['plink_ref_no']."' WHERE order_id='".$data['order_id']."'";
                       
                        $wpdb->query($sql_update);
                        
                        if($query_check->payment_status === ""){ $order->update_status('on-hold', 'PLINK Payment Gateway Awaiting payment'); } //update status
                        
                        // $ifid="plink-iframe-id";
                        
                        header('Content-Type: text/html; charset=utf-8');
          
                        if($merchOption['paymentpage_type']==='new_tab'){ 
                            echo "<script> window.location.href = '".$data['iframe_url'].$result->payment_page_url."' </script> ";
                        }
                        else if($merchOption['paymentpage_type']==='parent_window'){ 
                            echo " <script> 
                                    window.open('".$data['iframe_url'].$result->payment_page_url."','','directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes,width=600, height=280,top=200,left=200') 
                                </script>";
                        }else if($merchOption['paymentpage_type']==='same_window'){
                            echo "<script> window.location.href = '".$data['iframe_url'].$result->payment_page_url."' </script> ";
                        }
                        else{ 
                            echo "<script> window.location.href = '".$data['iframe_url'].$result->payment_page_url."' </script> ";
                        }
                        // generate_plink_payment_page(array("paymentpage_url"=>$data['iframe_url'].$result->payment_page_url,"return_url"=>get_permalink( wc_get_page_id( 'shop' ))));                   
                        exit;
                    }else if($result->response_code === "PL032" || $result->response_code === "MBDD32"){
                        plink_change_status_onduplicate($result->response_description,$data['order_id']);
                    }else{
                        //Log Error
                        plink_log("(Call to pg) Error Response: ". print_r($result,true),'error');
                        throw new Exception($result->response_message);
                    }   
                }catch (\Exception $e) {
                    //Log Error
                    $order->update_status('failed','PLINK Payment Gateway Failed Sending data to server: '.$e->getMessage());
                    plink_log("(Call to pg) ".$e->getMessage(),'error');
                    plink_generate_msg(__('Something went wrong. ','plink-payment-gateway-woocommerce').$e->getMessage(),wc_get_checkout_url());
                }
        }
    }

    /*
    * Accepts incoming callback from plink
    */
    if(!function_exists("plinkpg_paymentflag")){
        function plinkpg_paymentflag(){
            if($_SERVER['REQUEST_METHOD'] === 'GET'){ header('location:'.get_permalink( woocommerce_get_page_id( 'shop' ))); }
            global $wpdb;
            global $woocommerce;
            $merchOption = get_option('woocommerce_plinkpg_settings');
            $incomingdata=json_decode(file_get_contents('php://input')); //body request
            
            //Log incoming data
            plink_log("/plinkpg-paymentflag INCOMING DATA: ".json_encode($incomingdata),"notice");

            if(!isset($incomingdata)&&!isset($_SERVER['HTTP_MAC'])){ header('location'.home_url()); } //validate is nopayload & hmacis exist
            else if(!isset($incomingdata)){ echo "No payload received"; exit;} //validate payload is exist
            else{
                try{
                    header('Content-Type: application/json');
                    $table_name=$wpdb->prefix."plinkpg";
                    $secret_key = $merchOption['secret_key'];
                    $hash = hash_hmac('sha256',file_get_contents('php://input'), $secret_key);
                    $hmac = $_SERVER['HTTP_MAC'];
                    plink_log("/plinkpg-paymentflag DATA MAC VALIDATION: ".print_r(array("mac generated"=>$hash,"incoming mac"=>$_SERVER['HTTP_MAC']),TRUE),"notice");
                    if(is_null($hmac) || empty($hmac)){ throw new Exception("Mac doesn't exist");}                     
                    //validation incoming payloads
                    if($hmac != $hash){ throw new Exception("Access denied. Invalid Payload"); }

                    $merch_refno = $incomingdata->merchant_ref_no;
                    $exp_refno =  explode('-',$merch_refno);
                    $order_id = $exp_refno[2];

                    $order = wc_get_order($order_id); 
                    if(!$order){ throw new Exception("Transaction not found"); }

                    /*update status  to plink table*/ 
                    $sql = "UPDATE ".$table_name." SET ";
                    $update_column=[ 
                        "payment_status"=>$incomingdata->payment_status,
                        "plink_ref_no"=>$incomingdata->plink_ref_no,
                        "payment_date"=>$incomingdata->payment_date
                    ];

                    $last_index = count($update_column) - 1;
                    $index = 0;


                    foreach($update_column as $column => $value){
                        $sql .= $column."='";   
                        $index === $last_index ? $sql .= $value."'" : $sql .= $value."'," ; 
                        $index++;
                    }
                    $sql .=" WHERE order_id='".$order_id."';";
                    $query=$wpdb->query($sql);

                    
                    /*update status to woocommerce*/
                    $backend_status=[
                        "SETLD"=>"PLINK  Payment Success",
                        "REJCT"=>"PLINK  Payment Failed",
                        "PNDNG"=>"PLINK  Payment Pending"
                    ];

                    
                    if(!$query){
                        throw new Exception("Something wrong. maybe your order is not exist or has been updated with same data");
                    }else{
                        
                        if($incomingdata->payment_status === "SETLD"){
                            $order->reduce_order_stock();
                            $order->payment_complete();
                        }
                        else if( $incomingdata->payment_status === "REJCT"){ 
                            $order->update_status('failed',$backend_status[$incomingdata->payment_status]); 
                        }
                        else{
                            throw new Exception("Unknown payment status");
                        }
                        header("HTTP/1.1 200 OK");
                    }
                }catch(\Exception $e){
                        plink_log("/plinkpg-pamentflag : ".$e->getMessage(),'error');
                        header("Status: 400 ".$e->getMessage());
                }
            }
        }
    }

    /*
    * Status checker to loopback endpoint 
    */
    if(!function_exists("plinkpg_inqury_payment_status")){
        function plinkpg_inqury_payment_status($data,$order_id){
            try {
                global $woocommerce,$wpdb;

                $merchOption = get_option('woocommerce_plinkpg_settings');
                
                $table_name=$wpdb->prefix."plinkpg";
                
                $row_data=$wpdb->get_row("SELECT payment_status FROM {$table_name} WHERE order_id='{$order_id}' LIMIT 1;");

                if($row_data->payment_status === "SETLD"){
                    plink_generate_msg("Payment succeed. Thank u.",get_permalink( woocommerce_get_page_id( 'shop' ) ),"Continue Shopping","success");
                    exit;
                }
                // else if($row_data->payment_status === "PNDNG"){
                //     plink_generate_msg("Sorry your payment is still pending, please checkout your email to track your order. Thank u.",get_permalink( woocommerce_get_page_id( 'shop' ) ),"Continue Shopping","warning");
                //     exit;
                // }
                else if($row_data->payment_status === "REJEC"){
                    plink_generate_msg("Sorry your payment is failed.",get_permalink( woocommerce_get_page_id( 'shop' ) ),"Continue Shopping","error");
                    exit;
                }else{
                    if(isset($row_data->plink_ref_no)){
                        $body = array(
                            'merchant_ref_no' => $data['body']['merchant_ref_no'],
                            'plink_ref_no' => $row_data->plink_ref_no,
                            'merchant_id' => $data['body']['merchant_id'],
                            'merchant_key_id' => $data['body']['merchant_key_id'],
                        );

                        /*  create HMAC and update header */
                        $payload = json_encode($body,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
                        $token = hash_hmac('sha256', $payload, $data['secret_key']);
                        $data['header']['mac'] = $token;
                        $header = $data['header'];
                        $url = $data['inquryUrl'].$data['inquryEndpoint'];

                        //log data
                        plink_log("(Call to pg) inqury data to PLINK backend : ".print_r(json_encode($data,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),TRUE),"notice");
                        
                        /*  call to pg backend */
                        $res = wp_remote_post($url, array(
                            'method'      => 'POST',
                            'timeout'     => 300,
                            'headers'     => $header,
                            'body'        => $payload
                        ));

                        if(is_wp_error($res)){
                            throw new Exception($res->get_error_message());
                        }

                        if(!$res || !isset($res['body'])){
                            throw new Exception("Server Error");
                        }

                        $result = json_decode($res['body']);

                        plink_log("(Call to pg) response inqury to PLINK backend : ".print_r(json_encode($result,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),TRUE),"notice");

                        if($result->response_code === "PL000" || $result->response_code === "MBDD00"){
                            $sql_check = "UPDATE ".$table_name." SET payment_status='".$result->transaction_status."' WHERE order_id='".$order_id."'";
                            $qry = $wpdb->query($sql);
                            if(!$qry){ plink_generate_msg(printf(__("Sorry we can't update transaction data : %s"),$wpdb->last_error),wc_get_checkout_url()); exit; }
                            plinkpg_inqury_payment_status($data,$order_id);                        
                        }else{
                            throw new Exception("Can't inqury data to server");
                        }
                    }
                }

            }catch (\Exception $e) {
                plink_log("(Inqury payment status) : ".$e->getMessage(),'error');
                plink_generate_msg(__("Something went wrong. ".$e->getMessage(),"woocommerce"),wc_get_checkout_url());
            }
        }
    }

    /*
    * Generate plink iframe and payment status checker 
    */
    if(!(function_exists("generate_plink_payment_page"))){
        function generate_plink_payment_page($url){
            header('Content-Type: text/html; charset=utf-8');
            echo '<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">';
            echo "
            <script>jQuery('header').hide();</script>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;700&display=swap');
                .plink-payment-modal {
                    display: block; 
                    position: fixed; 
                    z-index: 1; 
                    left: 0;
                    top: 0;
                    width: 100%; 
                    height: 100%; 
                    overflow: auto; 
                    background-color: rgb(0,0,0); 
                    background-color: rgba(0,0,0,0.4);
                }
                .plink-payment-modal-content button{
                    border:none;
                    font-weight:bold;
                    box-shadow: none;
                    padding:10px;
                    border-radius: 2vh;
                    outline:none
                }
                .plink-payment-modal-content button:hover{
                    cursor:pointer;
                    background:grey;
                }
                .plink-payment-modal-content iframe{
                    border:none;
                    outline:none;
                }
                .plink-info-payment{
                    padding:30px 40px;
                    background:#FF8800;
                    color:white;
                    text-align:center;
                    font-family: 'poppins','sans-serif';
                    
                }
                .plink-payment-modal-content {
                    background-color: #fefefe;
                    margin: 15% auto; 
                    padding: 20px;
                    border: 1px solid #888;
                    width: 80%; 
                }
                .plink-logo{
                    width:10rem;
                }
                .return-btn{
                    background:#fbc531;
                }
                .try-again-btn{
                    background:#4cd137;
                }
            </style>
            ";
            echo '
                <div class="plink-payment-modal">
                    <div class="plink-payment-modal-content">';
            echo '<br><br><center><p class="plink-info-payment">'.__('Please make a payment soon by using Virtual Account numbers in the PLINK window below. To check your payment status you can click the button check payment status','plink-payment-gateway-woocommerce').'</p><center>';
            echo '<br><br>
                    <center>
                    <button class="return-btn" onclick="location.href=\''.esc_url($url['return_url']).'\'">'.__('Return to store','plink-payment-gateway-woocommerce').'</button>
                    &nbsp;
                    &nbsp;
                    <button class="try-again-btn" onclick="location.reload()">'.__('Check Payment Status','plink-payment-gateway-woocommerce').'</button></center>
                    ';
            echo '<br><iframe style="width:100%;height:100%;" src="'.esc_url($url['paymentpage_url']).'"> </iframe>';
            echo '
                    </div>
                </div>
            ';

            exit;
        }
    }

    /*
    * Generate message according to respond
    */
    if(!(function_exists("plink_generate_msg"))){
        function plink_generate_msg($msg,$redirect_url,$btn_msg='',$type="error"){
            if($btn_msg === ''){ $btn_msg = __('Go to checkout page','plink-payment-gateway-woocommerce'); }
            
            $img_url=WP_PLUGIN_URL."/".basename(dirname(__FILE__)) . "/assets/img/plink-logo.png";
            
            header('Content-Type: text/html; charset=utf-8');
            
            $colors=array(
                "waiting" => "#00a8ff",
                "error" => "#e84118",
                "warning" => "#fbc531",  
                "success" => "#4cd137",
            );
            $icon=array(
                "waiting" => '<i class="plink-payment-status-icon fas fa-history"></i>',
                "error" => '<i class="plink-payment-status-icon fas fa-times-circle"></i>',
                "warning" => '<i class="plink-payment-status-icon fas fa-history"></i>',  
                "success" => '<i class="plink-payment-status-icon fas fa-check-circle"></i>',
            );


            echo "<style>

                *{
                    font-family:'poppins','sans-serif';
                }
                body{
                    display:flex;
                    align-items:center;
                    justify-content:center;
                }
                .plink-box-succes-payment-container{
                    background:".$colors[$type].";
                    -webkit-box-shadow: 0px 0px 40px -6px rgba(0,0,0,0.93);
                    -moz-box-shadow: 0px 0px 40px -6px rgba(0,0,0,0.93);
                    box-shadow: 0px 0px 40px -6px rgba(0,0,0,0.93);
                    width:30rem;
                    border-radius:4vh;
                }
                
                .plink-box-succes-payment-container-content{
                    box-sizing:border-box;
                    padding:30px;
                    display:flex;
                    align-items:center;
                    flex-direction:column;
                }
                .plink-box-succes-payment-container-content h2{
                    font-size:1.5rem;
                    font-weight:bold;
                    color:white;
                }
                .plink-box-succes-payment-container-content .plink-payment-status-icon{
                    margin-top:2rem;
                    font-size:10rem;
                    color:white;
                }
                
                .plink-box-succes-payment-container-content button{
                    background:none;
                    outline:none;
                    border:none;
                    border-radius:2vh;
                    border:2px white solid;
                    box-sizing:border-box;
                    padding:1rem;
                    margin-top:3rem;
                    color:white;
                    font-weight:bold;
                }
                .plink-box-succes-payment-container-content button:hover{
                    background:white;
                    outline:none;
                    border:none;
                    border-radius:2vh;
                    border:2px white solid;
                    box-sizing:border-box;
                    padding:1rem;
                    margin-top:3rem;
                    color:".$colors[$type].";
                    font-weight:bold;
                    cursor:pointer;
                    -webkit-box-shadow: 0px 0px 10px -6px rgba(0,0,0,0.93);
                    -moz-box-shadow: 0px 0px 10px -6px rgba(0,0,0,0.93);
                    box-shadow: 0px 0px 10px -6px rgba(0,0,0,0.93);
                }
                h2{
                    text-align:center;
                }
            </style>";

        wp_enqueue_style('google-font','https://fonts.googleapis.com/css2?family=Poppins:wght@300;700&display=swap',null);

        wp_enqueue_style('font-awesome-5','https://pro.fontawesome.com/releases/v5.10.0/css/all.css',null);

        echo '
            <div class="plink-box-succes-payment-container">
                <div class="plink-box-succes-payment-container-content">
                        <img class="plink-logo" src="'.esc_url($img_url).'"/>
                        <br>    
                        <br>
                        <h2>'.esc_html($msg).'</h2>
                        '.$icon[$type].'
                        <br>';

                        if(is_null($redirect_url) || is_null($btn_msg)){
                            echo '<button onclick="window.close()">'.__('Close','plink-payment-gateway-woocommerce').'</button>';
                        }else{
                            echo '<button onclick="location.href='.("'".esc_url($redirect_url."'")).';">'.esc_html($btn_msg) ?? __('Try again','plink-payment-gateway-woocommerce').'</button>';
                        }     
        echo      '</div>
            </div>';
        }
    }
     /*
    * Status checker to loopback endpoint 
    */
    if(!function_exists("plink_change_status_onduplicate")){
        function plink_change_status_onduplicate($desc,$order_id){
            global $wpdb;
            $table_name = $wpdb->prefix.'plinkpg';
            $order = wc_get_order( $order_id );
            if($desc === "transaction exist. Status payment: SETLD"){
                $order->reduce_order_stock();
                $order->payment_complete();
                $wpdb->query("UPDATE {$table_name} SET  payment_status='SETLD' WHERE order_id='{$order_id}' ;");
                plink_generate_msg("Payment succeed. Thank u.",get_permalink( woocommerce_get_page_id( 'shop' ) ),"Continue Shopping","success");
                exit;
            }else if($desc === "transaction exist. Status payment: PNDNG"){
                $order->update_status('on-hold','Transaction awaiting for payment');
                $wpdb->query("UPDATE {$table_name}  SET payment_status='PNDNG' WHERE order_id='{$order_id}' ;"); 
                plink_generate_msg("Sorry your payment is still pending, please checkout your email to track your order. Thank u.",get_permalink( woocommerce_get_page_id( 'shop' ) ),"Continue Shopping","warning");
                exit;
            }else if($desc === "transaction exist. Status payment: REJEC"){
                $order->update_status('failed','Transaction failed'); 
                $wpdb->query("UPDATE {$table_name}  SET payment_status='REJEC' WHERE order_id='{$order_id}' ;"); 
                plink_generate_msg("Sorry your payment is failed.",get_permalink( woocommerce_get_page_id( 'shop' ) ),"Continue Shopping","error");
                exit;
            }else{
                throw new Exception("System error. please contact to plink administrator");
            }
        }
    }

    function wc_add_plinkpg($methods) {
        $methods[] = 'WC_Gateway_PLINK_PG';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'wc_add_plinkpg' );
}

/**
 * Adding columns to order page
 */
add_filter( 'manage_edit-shop_order_columns', 'custom_shop_order_column', 20 );
if(!function_exists("custom_shop_order_column")){
    function custom_shop_order_column($columns)
    {
        $reordered_columns = array();

        // Inserting columns to a specific location
        foreach( $columns as $key => $column){
            $reordered_columns[$key] = $column;
            if( $key ==  'order_status' ){
                // Inserting after "Status" column
                $reordered_columns['plink-check-status'] = __( 'Check Payment Status','plink-payment-gateway-woocommerce');
                // $reordered_columns['plink-trx-type'] = __( 'Transaction Type','theme_domain');
            }
        }
        return $reordered_columns;
    }
}

/**
 * Adding custom fields meta data for each new column (example)
 */
add_action( 'manage_shop_order_posts_custom_column' , 'custom_orders_list_column_content', 20, 2 );
if(!function_exists("custom_orders_list_column_content")){
    function custom_orders_list_column_content( $column, $post_id )
    {
        global $woocommerce,$post;
        global $wpdb;
        
        $merchOption = get_option('woocommerce_plinkpg_settings');
        $merchant_ref_no = construct_merchant_refno($merchOption['merchant_id'],$post_id);
        $table_name = $wpdb->prefix.'plinkpg';
        $sql_check = "SELECT order_id,plink_ref_no,merchant_ref_no FROM ".$table_name." WHERE order_id = ".$post_id;
        $query_check=$wpdb->get_row($sql_check);

        switch ( $column )
        {
            case 'plink-check-status' :
                    if($query_check && $query_check->plink_ref_no && $query_check->merchant_ref_no){
                        echo '<a class="button" href="'.esc_url(get_rest_url(null, 'plinkpg/action/admin-plinkpg-inqury')).'?merch_refno='.$query_check->merchant_ref_no.'&plink_refno='.$query_check->plink_ref_no.'&order_id='.$query_check->order_id.'" target="_blank">'.$query_check->merchant_ref_no.'</a>';
                    }else{
                        echo "-";
                    }
                break;
            // case 'plink-trx-type':
            //     break;
        }
    }
}

/**
 * Check kredensial if complete
 */
if(!function_exists("isCredentialComplete")){
    function isCredentialComplete(){
        $merchOption = get_option('woocommerce_plinkpg_settings');
        $opts = ['secret_key','phase','merchant_id','merchant_key_id'];

        $isEmptyOpt = false;
            foreach($opts as $opt){
                if(empty($merchOption[$opt])){
                    $isEmptyOpt  = true;
                    break;
                }
            }
        return $isEmptyOpt;
    }
}

/**
 * Inqury transaction from admin page
 */
if(!function_exists("plinkpg_admin_inqury_trx")){
    function plinkpg_admin_inqury_trx(WP_REST_Request $request){
        global $wpdb;
        $merchOption = get_option('woocommerce_plinkpg_settings');
        $plink_ref_no = sanitize_text_field($request->get_param('plink_refno'));
        $merchant_ref_no = sanitize_text_field($request->get_param('merch_refno'));
        $order_id = sanitize_text_field($request->get_param('order_id'));
        $order = wc_get_order($order_id); 

        if(!isset($order_id) || !isset($merchant_ref_no) || !isset($plink_ref_no)){
            $wp_query->set_404();
            status_header( 404 );
            get_template_part( 404 ); exit();
        }

        $merchant_key_id = $merchOption['merchant_key_id'];
        $merchant_id = $merchOption['merchant_id'];

        //inqury
        $inquryUrl = array(
            "testing"     => 'https://api-dev.plink.co.id',
            "live"  => 'https://secure2.plink.co.id',
        );
        
        $inquryEndpoint = array(
            "testing" => '/gateway/v2/payment/integration/transaction/api/inquiry-transaction',
            "live" => '/paymentpage/api/payment-method/inquiry-transaction'
        );

        try {

            if(isCredentialComplete()){ throw new Exception(__("Credential is incomplete. Please check your credential to continue using this feature",'plink-payment-gateway-woocommerce')); }

            $data = array(
                "body" => array(
                    "plink_ref_no"=> $plink_ref_no,
                    "merchant_ref_no"=>$merchant_ref_no,
                    "merchant_key_id"=> $merchant_key_id,
                    "merchant_id"=> $merchant_id,
                    "transmission_date_time"=>date("Y-m-d H:i:s").substr((string)microtime(), 1, 6).date(' O')
                )
            );

            $payload = json_encode($data['body'],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
            $token = hash_hmac('sha256', $payload, $merchOption['secret_key']);
            $data['header']['mac'] = $token;
            $data['header']['content-type'] = 'application/json';

            //log data
            plink_log("(Call to pg) send data Admin Inqury Trx : ".print_r(json_encode($data),TRUE)."\n ===> ip: ".$_SERVER['SERVER_ADDR'],"notice");
            
            $url = $inquryUrl[$merchOption['phase']].$inquryEndpoint[$merchOption['phase']];

            /*  call to pg backend */
            $res = wp_remote_post($url, array(
                'method'      => 'POST',
                'timeout'     => 30000,
                'headers'     => $data['header'],
                'body'        => $payload
            ));

            if(is_wp_error($res)){
                throw new Exception($res->get_error_message());
            }

            if(!$res || !isset($res['body'])){
                throw new Exception("Result not found");
            }
            
            $result = json_decode($res['body']);

            if(!isset($result->response_code)){
                plink_log("(Call to pg) inqury trx : ".json_encode($result),"error");
                throw new Exception("System Error");
            }

            if(isset($result->response_code)){
                if($result->response_code === "PL000" || $result->response_code === "MBDD00"){
                    /*update status  to plink table*/ 
                    $table_name = $wpdb->prefix.'plinkpg';
                    $sql = "UPDATE ".$table_name." SET ";
                    $update_column=[ 
                        "payment_status"=>$result->transaction_status,
                        "plink_ref_no"=>$result->plink_ref_no,
                        "payment_date"=>$result->payment_date
                    ];

                    $last_index = count($update_column) - 1;
                    $index = 0;

                    foreach($update_column as $column => $value){
                        $sql .= $column."='";   
                        $index === $last_index ? $sql .= $value."'" : $sql .= $value."'," ; 
                        $index++;
                    }
                    $sql .=" WHERE order_id='".$order_id."';";
                    $wpdb->query($sql);
                    if($result->transaction_status === "SETLD"){
                        $order->reduce_order_stock();
                        $order->payment_complete();
                        plink_generate_msg(__("Payment status for merchant refno: ",'plink-payment-gateway-woocommerce').$merchant_ref_no.__(" is SUCCED",'plink-payment-gateway-woocommerce'),null,null,"success");
                        exit;
                    }else if($result->transaction_status === "PNDNG"){
                        $order->update_status('on-hold','Transaction awaiting for payment');
                        plink_generate_msg(__("Payment status for merchant refno: ",'plink-payment-gateway-woocommerce').$merchant_ref_no.__(" is still PENDING",'plink-payment-gateway-woocommerce'),NULL,NULL,"warning");
                        exit;
                    }else if($result->transaction_status === "REJEC"){
                        $order->update_status('failed','Transaction failed'); 
                        plink_generate_msg(__("Payment status for merchant refno: ",'plink-payment-gateway-woocommerce').$merchant_ref_no.__(" is FAILED",'plink-payment-gateway-woocommerce'),NULL,NULL,"error");
                        exit;
                    }
                }else{
                    //Log Error
                    plink_log("(Call to pg) Admin Inqury Trx: ". print_r($result,true),'error');
                    throw new Exception($result->response_message);
                }   
            }
        }catch (\Exception $e) {
            plink_log("(Call to pg) Admin Inqury Trx: ".$e->getMessage(),'error');
            plink_generate_msg(__('Something went wrong. ','plink-payment-gateway-woocommerce').$e->getMessage(),null);
        }
    }
}

/*
* Register payment flag REST endpoint
* Callback endpoint for PLINK for update payment status 
*/
add_action( 'rest_api_init', function () {
        register_rest_route('plinkpg','action/plinkpg-redirection',array(
            'methods' => 'POST',
            'callback' => 'plinkpg_redirection',
            'permission_callback' => '__return_true'
        ) );
        register_rest_route('plinkpg','action/set-payment-flag',array(
            'methods' => 'POST',
            'callback' => 'plinkpg_paymentflag',
            'permission_callback' => '__return_true'
        ) );
        register_rest_route('plinkpg','action/admin-plinkpg-inqury',array(
            'methods' => 'GET',
            'callback' => 'plinkpg_admin_inqury_trx',
            'permission_callback' => '__return_true'
        ) );
    } 
);

function plinkpg_activate() {
  add_option( 'Activated_Plugin', 'plinkpg' );
}


register_activation_hook(__FILE__, 'plinkpg_activate');