<?php
/*
Plugin Name: Pop Recarga
Description: POP Recarga Payments gateway plugin for WooCommerce 
Version: 1.0.0
Author: InPDV
Author URI: http://www.poprecarga.com.br/
*/
add_action( 'plugins_loaded', 'woocommerce_pop_payments_init', 0 );
function woocommerce_pop_payments_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { 
		return; 
	}
	/**
	 * Gateway Class
	 */
	class WC_Gateway_POP_Payments extends WC_Payment_Gateway {
		/**
		 *Define POP Payments Variables
		 */
		public $clientid;
		public $secret;
		public $expires;
		public $bearer;
		public $mode;
		public $plugin_url;
		/**
		 * Constructor
		 */
		function __construct() { 
			$this->id 				= 'pop_payments';
			$this->method_title		= __('POP Payments ', 'pop_payments');
			$this->has_fields 		= true;
			// Load the form fields
			$this->init_form_fields();
			// Load the settings.
			$this->init_settings();
			// Get setting values
			$this->title 					= $this->settings['title'];
			$this->description 	  = $this->settings['description'];
			$this->enabled			  = $this->settings['enabled'];
			$this->clientid		  	= $this->settings['clientid'];
			$this->secret       	= $this->settings['secret'];
			$this->expires				= intval($this->settings['expires']);					
			$this->bearer				  = $this->settings['bearer'];		
			$this->mode						= $this->settings['mode'];
	
			// Save admin options
		  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'wp_enqueue_scripts', array($this, 'payment_scripts' ) );
			
		}
		/**
		 * Check if SSL is enabled and notify the user if SSL is not enabled
		 
	 	function ssl_check() {
			if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'no' && $this->enabled == 'yes' ) {
				echo '<div class="error"><p>'.sprintf(__('POP Payments is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout is not secure! Please enable SSL and ensure your server has a valid SSL certificate', 'coderxo-pop_payments'), admin_url('admin.php?page=woocommerce')).'</p></div>';
			}
		}*/
		/**
		 *Initialize Gateway Settings Form Fields
		 */
		function init_form_fields() {
			$this->form_fields = array(
				'title' => array(
								'title' => __( 'Title', 'pop_payments' ), 
								'type' => 'text', 
								'description' => __( 'This controls the title which the user sees during checkout.', 'pop_payments' ), 
								'default' => __( 'POP Payments', 'pop_payments' )
							), 
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'pop_payments' ), 
								'label' => __( 'Enable POP Payments ', 'pop_payments' ), 
								'type' => 'checkbox', 
								'description' => '', 
								'default' => 'no'
							), 
				'description' => array(
								'title' => __( 'Description', 'pop_payments' ), 
								'type' => 'text', 
								'description' => __( 'This controls the description which the user sees during checkout.', 'pop_payments' ), 
								'default' =>  __( 'Pay by Mobile using POP Payments' , 'pop_payments' )
							),  
				'clientid' => array(
								'title' => __( 'Client ID', 'pop_payments' ), 
								'type' => 'text', 
								'description' => __( 'Client ID, provided by POP_Payments .', 'pop_payments' ), 
								'default' => ''
							), 
				'secret' => array(
								'title' => __( 'Client Secret', 'pop_payments' ), 
								'type' => 'text', 
								'description' => __( 'The Client Secret as provided by POP_Payments .', 'pop_payments' ), 
								'default' => ''
							),
				'mode' => array(
								'title' => __( 'Gateway Mode', 'pop_payments' ), 
								'type' => 'select', 
								'default' => 'sandbox',
								'options'  => array(
								      'sandbox'      => __( 'Sandbox', 'pop_payments' ),
									    'production'   => __( 'Production', 'pop-payments' )
										         )
							  )
																		
				);
		}
		/**
		 * Admin panel options
		 */
		function admin_options() {
		
			?>
			<h3><?php __( 'POP_Payments ', 'pop_payments' ); ?></h3>
			<p><?php __( 'POP Payments works by adding a mobile number entry field on the checkout and then sending the details to POP Payments for authorization.', 'pop_payments' ); ?></p>
			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table>
			<?php
		}
		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		function is_available() {
			if ($this->enabled=="yes") {
				return true;
			} else {
				return false;
			}
		}
		
		/*
		 * Payment form on checkout page
		 */
		function payment_fields() {
			?>
			<?php if ( $this->description ) : ?>
				<p><strong><?php echo $this->description; ?></strong></p>
			<?php endif; ?>
      <style>
				.pop_paymentscc { width: 230px }
				#pop_paymentscode { visibility: hidden}
				.pop_payments4 {width: 60px }
				label {display: inline-block; width: 150px }
				
      </style>
			
			<br/>
			<fieldset style="padding-left:25px">
				<p id="pop_paymentsnumber">
					<label for="pop_payments_mobile_number"><?php echo __( 'Mobile Number', 'pop_payments' ) ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" id="pop_payments_mobile_number" class="pop_paymentscc" name="pop_payments_mobile_number" maxlength="14" onkeypress="return isNumberKey(event)" />  (<small><?php echo __('55XXXXXXXXXXX', 'pop_payments'); ?></small>)
				</p>
				<div class="clear"></div>
				<br/>
				<p id="pop_paymentscode" >
					<label for="pop_payments_token"><?php echo __( 'Token', 'pop_payments' ) ?> <span class="required" style="display: inline;">*</span></label>
					<input type="text" class="pop_payments4"  id="pop_payments_token" name="pop_payments_token" maxlength="6"/>   <br/>(<small><?php echo __('Enter token received on your mobile', 'pop_payments'); ?></small>)
				</p>
	
				<div class="clear"></div>
				<p id="pop_paymentsbutton" >
					<input class="button alt" type="button" onclick="popPaymentsPay()" id="pop_payments_button"  value="<?php echo __('Pay Now', 'pop_payments') ?>" />  
				  <input type="hidden" id="pop_payments_id" value="" name="pop_payments_id"  />  
				</p>
			</fieldset>
			<script>
			 var pop_payments_number_required = '<?php echo __('A valid mobile number is required','pop_payments'); ?>';
			 var pop_payments_token_required  = '<?php echo __('Token is required','pop_payments'); ?>';
			 var pop_payments_id_missing  = '<?php echo __('Payment id is missing','pop_payments'); ?>';
			 function isNumberKey(evt)
			 {
           var charCode = (evt.which) ? evt.which : event.keyCode
   				 if (charCode > 31 && (charCode < 48 || charCode > 57))
              return false;
   				 return true;
        }
				
				var sitehome = '<?php echo site_url();?>';
			</script>
			<?php
		}
		/**
		 * Process the payment, receive and validate the results, and redirect to the thank you page upon a successful transaction
		 */
		function process_payment( $order_id ) {
			global $woocommerce;
			error_reporting(0);
			$order = new WC_Order( $order_id );				
				 
			// Validate plugin settings
			if ( ! $this->validate_settings() ) {
				$cancelNote = __('Order was cancelled due to invalid settings (check your credentials).', 'pop_payments');
				$order->add_order_note( $cancelNote );
				$woocommerce->add_error(__('Payment was rejected due to configuration error.', 'pop_payments'));
				return false;
			}
			$error = false;
			if(time() >= $this->expires || !$this->bearer)
			{
				 $this->fetchBearer();
			}
			if( time() >= $this->expires || !$this->bearer)
			{
			   	$woocommerce->add_error( __('Cannot initiate payment transaction', 'pop_payments') );
					return false;
			}
				 
			$token      = ltrim(trim($_REQUEST['pop_payments_token']));
			$payment_id = ltrim(trim($_REQUEST['pop_payments_id']));
		 
		  if( $token && $payment_id ) { 													
		      $result = $this->fetch("/payments/".$payment_id."/token/".$token,$data , "PUT" );					
					$json   = json_decode($result,1);	
					if($json['id'])
					{
					  $result = $this->fetch("/payments/".$payment_id."/execute",$data , "PUT" );		
						$json   = json_decode($result,1);	
					}
					else 
					{
		       		 $woocommerce->add_error(__('Payment authorization failed', 'pop_payments').'<br/>'. $result['message']);
						   return false;
					}
		  }
			else {
			    $error = true;
			    if( strlen($token) < 3 )
			      $woocommerce->add_error(__('Invalid token', 'pop_payments'));
			    if( !$payment_id )
			      $woocommerce->add_error(__('Invalid payment id', 'pop_payments'));
						
					return false;
			}
			
			if($error == false )
			{				
    	   // parse the data received back from the gateway, taking into account the delimiters and encapsulation characters		
				 if(!$result) {
				    $woocommerce->add_error(__( 'Payment Error', 'pop_payments' ) . ': ' .'Could not connect to gateway server'  . '');				
				 }
				 else if(  $json['id'] ) 
		     {
			    	//add transaction id to payment complete message, update woocommerce order and cart
						$order->add_order_note( __( 'POP Payments payment completed', 'pop_payments' ) . '(Transaction ID: ' . $json['id'] . ')' );
						$order->payment_complete();
						$woocommerce->cart->empty_cart();
			
						//redirect to the woocommerce thank you page
						return array(
					    'result' => 'success',
							'redirect' =>$this->get_return_url( $order )
				    );									
					} else {	
				
				        $error = $json['message'];
 	      				$woocommerce->add_error(__( 'Payment Failure<br/>', 'pop_payments' )  . $error  );		
		     }			
		   
			}
			else {
					      $woocommerce->add_error(__( 'Payment Failure<br/>', 'pop_payments' )   );	
			}
			
			return false;
		}
		/**
		 * Validate the payment form prior to submitting via wp_remote_posts
		 */
		function validate_fields() {
			global $woocommerce;
				 
			$token      = ltrim(trim($_REQUEST['pop_payments_token']));
			$payment_id = ltrim(trim($_REQUEST['pop_payments_id']));
		 
			$error = array();
			
			if( strlen($token) < 3  )
			  $error[] = __("Invalid token", "pop_payments",  'pop_payments');

			
			if(!$payment_id)
			  $error[] = __("Invalid payment id", "pop_payments",  'pop_payments');
				
			if(sizeof($error))
			{
			  $woocommerce->add_error( __('Gateway error', 'pop_payments'). '<br/>'.join("<br/>\n",$error));	
				return false;
			}
			
			return true;
		}
		/**
		 * Validate plugin settings
		 */
		function validate_settings() {
			//Check for the settings
			if ( ! $this->clientid || !$this->secret ) {
				return false;
			}
			return true; 
		}
		
	  /**
	   * Get the plugin url.
	   *
	   * @access public
	   * @return string
	   */
	   public function plugin_url() {
		   if ( $this->plugin_url ) return $this->plugin_url;
		   return $this->plugin_url = plugins_url( basename( plugin_dir_path(__FILE__) ), basename( __FILE__ ) );
	   }		
		 
		/*
		 * Get the users country either from their order, or from their customer data
		 */
		function get_country_code() {
			global $woocommerce;
			if ( isset( $_GET['order_id'] ) ) {
				$order = new WC_Order( $_GET['order_id'] );
				return $order->billing_country;
			} elseif ( $woocommerce->customer->get_country() ) {
				return $woocommerce->customer->get_country();
			} else {
			return NULL;
			}
		}		
		
		
		function fetch($path , $data , $method="POST")
		{
    	// Send CURL communication
    	$ch = curl_init();
			
			$host	= $this->mode == 'sandbox' ? "api.sandbox.inpdv.com.br" : "api.inpdv.com.br";
			
			$data = array_merge( array( 'client_id' => $this->clientid , 'client_secret' => $this->secret) , $data );

			
    	curl_setopt($ch, CURLOPT_URL, "https://" .$host . $path);
    	curl_setopt($ch, CURLOPT_HEADER, 0);
			
			if($method == "POST")
    	  curl_setopt($ch, CURLOPT_POST, 1);

		  if( $method == "PUT" )
		    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		  
    	
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); /* compatibility for SSL communications on some Windows servers (IIS 5.0+) */
			
			if($path == "/token")
			{
			   foreach($data as $key => $val)  
			      $string .= "$key=".urlencode($val)."&";		
				 $data = $string;	
			   curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC ) ; 
				 curl_setopt($ch, CURLOPT_USERPWD, $this->clientid.":".$this->secret); 
			}
			else {
			  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json; charset=utf-8", 
												 										 			 "Authorization: Bearer ".$this->bearer
																									 )
									  );
				$data = json_encode($data);
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    	$result = curl_exec($ch);
			global $woocommerce;
	   	$errorNo = curl_errno($ch);
			curl_close ($ch);
			
			return $result;
		} 
		
		function fetchBearer(){
		   $path  =  "/token";
			 $data  = array( 'grant_type' => 'client_credentials');
		   $result = $this->fetch($path , $data , "POST");
			 $result = json_decode($result, 1);
			 if($result['access_token'])
			 {
				 $this->settings['bearer']   = $this->bearer  =  $result['access_token']; 
			   $this->settings['expires']  = $this->expires = time() + intval($result['expires_in']);
				 update_option( $this->plugin_id . $this->id . '_settings', $this->settings);
				 $r = get_option( $this->plugin_id . $this->id . '_settings');
			 }
		}
		
		function createPayment(){
		 
		    global $woocommerce;
				error_reporting(0);
				
		   if(!$_REQUEST['pop_create'])
 			 		return;

			 if(time() >= $this->expires || !$this->bearer)
			    $this->fetchBearer();
					
			 if( time() >= $this->expires || !$this->bearer)
			    $error = array('result' => 1 , 'message' => __('1: Cannot initiate payment transaction', 'pop_payments') );
					
	
					
			 if(empty($error))
			 {					

				 $mobile    = preg_replace("/[^0-9a-z]/i","",$_REQUEST['number']);
       	 $money			= number_format($woocommerce->cart->total,2,'.','');
				
			 	 $error = array();
			 
			 	 if(  ( strlen($mobile) !=  12 && strlen($mobile) != 13) ||  preg_match( "/^(55)/" ,$mobile) == false )
			 	 {
			      $error = array('result' => 1 , 'message' => __('Invalid Mobile Number', 'pop_payments') );
			 	 }
				 else {
				   $mobile = '+'.$mobile;
				 }
			 
			 	 if( !$money )
			 	 {
			      $error = array('result' => 1 , 'message' => __('Invalid Request', 'pop_payments') );
			   }
		  
			   if( empty($error) ) { 
			
             // Populate an array that contains all of the data to be sent to POP_Payments 		
      	  	 $data = array(
                         'transaction'  						=> array( 'currencyCode' => get_woocommerce_currency(),
												 															 				'amount'	 		 => $money,
																															'description'  => 'Order ' ),
                         'identifier' 			    	  => $mobile,
												 //'brandId'									=> $order_id
   
												  );
													
													
		         $result = $this->fetch("/payments",$data, "POST" );	
						 $json   = json_decode($result,1);		
						 if($json['id'])
						 {
					     $return = array('result' => 0 , 'payment_id' => $json['id'] , 'message' => __('Please enter the token received on your mobile in the Token field below', 'pop_payments' ));
						 }	
						 else
					     $error  = array('result' => 1 , 'message' => __('2: Could not initiate payment transaction', 'pop_payments') );
							 
							 if($json['message'])
							   $error['message'] .= "<br/>".$json['message'];					
		      }
			 }
			 
			 if(!empty($error))
			   $this->jsonOut($error);
			 else
			   $this->jsonOut($return);
		}
		
		function jsonOut($data){
		   
			 header("Content-Type: application/json");
			 print json_encode($data);
			 exit;
		
		}
		
		function payment_scripts() {
		 wp_enqueue_script( 'wc-pop_payments', $this->plugin_url() . '/assets/pop_payments.js', array( 'jquery'), WC_VERSION, true );
		}
	}
	/**
	 * Add the POP Payments Gateway to WooCommerce
	 */
	function add_pop_payments_gateway( $methods ) {
		$methods[] = 'WC_Gateway_POP_Payments';
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'add_pop_payments_gateway' );
} 

add_action('init', 'pop_create_action', 99);
function pop_create_action(){  
	if($_REQUEST['pop_create'])
	{   
		  $POP_Payments = new WC_Gateway_POP_Payments();
			$POP_Payments->createPayment();
	}
}