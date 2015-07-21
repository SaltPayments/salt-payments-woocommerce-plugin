<?php
/*
   Plugin Name: Salt Payments Gateway
   Description: Extends WooCommerce to Process Payments with Salt Payments gateway.
   Version: 1.0.1
   Plugin URI: http://salttechnology.github.io
   Author: Salt Payments
   Author URI: http://www.saltpayments.com
   Original Author: nalparam
   Original Author URI: https://twitter.com/nalparam
   License: Under GPL2

*/
 
	// sue SALT php libraray
	include __DIR__.'/lib/SALT.php';
	
	/** Credit Card Single Purchase with SALT Payments API */
	use \SALT\Merchant;
	use \SALT\HttpsCreditCardService;
	use \SALT\CreditCard;
	use \SALT\VerificationRequest;

 

add_action('plugins_loaded', 'woocommerce_salt_init', 0);

function woocommerce_salt_init() {		      

	if ( !class_exists( 'WC_Payment_Gateway' ) ) 
    	return;

   /**
   * Localisation
   */
   load_plugin_textdomain('salt_woo', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
   
   
   /**
   * Authorize.net Payment Gateway class
   */
   class Salt_Woo_Gateway extends WC_Payment_Gateway 
   {
      	protected $msg = array();
 
      	public function __construct(){

		$this->id               = 'salt_woo';
		$this->method_title     = __('SALT Payments', 'salt_woo');
		$this->icon             = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.gif';
		$this->has_fields       = true;
		$this->init_form_fields();
		$this->init_settings();
		$this->title            = $this->settings['title'];
		$this->description      = $this->settings['description'];
		$this->merchant_id            = $this->settings['merchant_id'];
		$this->api_token            = $this->settings['api_token'];
		$this->test_mode             = $this->settings['test_mode'];
		//$this->liveurl  = 'https://gateway.admeris.com/platform2/gateway/processor.do';
		$this->liveurl  = 'https://gateway.saltpayments.com/gateway/creditcard/processor.do';
		$this->testurl  = 'https://test.saltpayments.com/gateway/creditcard/processor.do';
		
		// support subscription
		$this->supports = array(
					'products',
					'subscriptions',
					'subscription_suspension',
					'subscription_cancellation',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
					'pre-orders'
					
				);
     
         
         if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
             add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
          } else {
             add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
         }

         add_action('woocommerce_receipt_salt_woo', array(&$this, 'receipt_page'));
         add_action('woocommerce_thankyou_salt_woo',array(&$this, 'thankyou_page'));
         
   		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'process_scheduled_subscription_payment' ), 10, 3 );
			add_filter( 'woocommerce_subscriptions_renewal_order_meta_query', array( $this, 'remove_subscriptions_renewal_order_meta' ), 10, 4 );
			add_action( 'woocommerce_subscriptions_changed_failing_payment_method_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 3 );
		}

		if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this, 'process_pre_order_release_payment' ) );
		}

         
         
      }
      
		  
	function init_form_fields()
	{
	
		$this->form_fields = array(
		    'enabled'      => array(
		          'title'        => __('Enable/Disable', 'salt_woo'),
		          'type'         => 'checkbox',
		          'label'        => __('Enable SALT Payments Gateway Module.', 'salt_woo'),
		          'default'      => 'no'),
		          
		    'title'        => array(
		          'title'        => __('Title:', 'salt_woo'),
		          'type'         => 'text',
		          'description'  => __('This controls the title which the user sees during checkout.', 'salt_woo'),
		          'default'      => __('SALT Payments Gateway', 'salt_woo')),
		          
		    'description'  => array(
		          'title'        => __('Description:', 'salt_woo'),
		          'type'         => 'textarea',
		          'description'  => __('This controls the description which the user sees during checkout.', 'salt_woo'),
		          'default'      => __('Pay securely by Credit Card through SALT Payments Gateway.', 'salt_woo')),
		    
		    'merchant_id'     => array(
		          'title'        => __('Merchant ID', 'salt_woo'),
		          'type'         => 'text',
		          'description'  => __('SALT Payments assigned merchant ID', 'salt_woo')),
		          
		    'api_token' => array(
		          'title'        => __('API Token', 'salt_woo'),
		          'type'         => 'text',
		          'description'  =>  __('SALT Payments assigned API token', 'salt_woo')),
		     

		    'test_mode'    => array(
		          'title'        => __('API Mode'),
		          'type'         => 'select',
		          'options'      => array('true'=>'Sandbox Mode', 'false'=>'Production Mode'),
		          'description'  => "Sandbox / Production Mode" )
		 );
	}
      
     
      /**
       * Admin Panel Options
       * - Options for bits like 'title' and availability on a country-by-country basis
      **/
      public function admin_options()
      {
         echo '<h3>'.__('SALT Payments Gateway', 'tech').'</h3>';
         echo '<p>'.__('SALT Payments: Simple, secure, and frictionless commerce').'</p>';
         echo '<table class="form-table">';
         $this->generate_settings_html();
         echo '</table>';

      }
      
	/**
	*  There are no payment fields for Authorize.net, but want to show the description if set.
	**/
	function payment_fields()
	{
		$description = $this->description;
		   	
		if($this->test_mode == 'true')
		{
			$description = "Sandbox Mode Enabled\r\n";
			$description .= "In Sandbox Mode, you can test with sandbox account";
		 }
		
		if ( $description ) {
			echo wpautop( wptexturize( trim( $description ) ) );
		}

		?>
		<script>
		function checkCvv()
		{
			if(document.getElementById('chk_cvv').checked)
			{
			   document.getElementById('salt_cvv').style.display = "block";
			   document.getElementById('use_cvv').value = 1;
			}
			else
			{
			   document.getElementById('salt_cvv').style.display = "none";
			   document.getElementById('use_cvv').value = 0;
			}			
		}

		</script>
		<div class="salt_credit_card">
			<fieldset id="salt_woo_card_info">
			<p class="form-row">
				<label for="card_number"><?php _e( "Credit Card Number", 'salt_woo'); ?> <span class="required">*</span></label>
				<input class="wc-credit-card-form-card-number" type="text" id="salt_card_number" name="salt_card_number" maxlength="20" autocomplete="off" />
			</p>
			<div class="clear"></div>

			<p class="form-row form-row-first">
				<label for="salt_exp_month"><?php _e( "Expiration Date", 'slat_woo'); ?> <span class="required">*</span></label>
				<select name="salt_exp_month" id="salt_exp_month" class="woocommerce-select woocommerce-cc-month" style="width:auto;">
					<option value=""><?php _e( 'Month', 'salt_woo'); ?></option>
					<?php foreach ( range( 1, 12 ) as $month ) : ?>
					<option value="<?php printf( '%02d', $month ) ?>"><?php printf( '%02d', $month ) ?></option>
					<?php endforeach; ?>
				</select>
				<select name="salt_exp_year" id="salt_exp_year" class="woocommerce-select woocommerce-cc-year" style="width:auto;">
					<option value=""><?php _e( 'Year', 'slat_woo'); ?></option>
					<?php foreach ( range( date( 'Y' ), date( 'Y' ) + 10 ) as $year ) : ?>
					<option value="<?php echo $year ?>"><?php echo $year ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="form-row form-row-first">
				<label for="salt_cvv"><?php _e( "CVV", 'slat_woo'); ?> <span class="required">*</span></label>
				<input type="text" id="salt_cvv" name="salt_cvv" autocomplete="off" maxlength="4"/>
				<input type="hidden" id="use_cvv" name="use_cvv" value=1 />
				
			</p>

			</fieldset>
		</div>
		
		<?php
		//$this->credit_card_form();
	
	}
    
      public function thankyou_page($order_id) 
      {
       
      }
      /**
      * Receipt Page
      **/
      function receipt_page($order)
      {
         echo '<p>'.__('Your paymet through SALT was successfully completed.', 'salt_woo').'</p>';
         //echo $this->generate_salt_form($order);
      }
      
      
      function process_subscription_initial_payment($order, $service, $credit_card, $vr)
      {
		//debugPrint('process_subscription_initial_payment');

      	$order_id = $order->id;
      	
      	$amount = WC_Subscriptions_Order::get_total_initial_payment( $order ) * 100; // cents
      	
		$timestamp = date('YmdHis');
		$order_id_salt = $timestamp . '-' . $order_id;
			
		// send request
		$receipt = $service->singlePurchase( $order_id_salt, $credit_card, $amount, $vr );

		$response = $receipt->params;
		
		//debugPrint($response);
	
		if($response['APPROVED'])
		{
			
			// Payment has been successful
			$order->add_order_note( sprintf( __( 'SALT payment approved (ORDER_ID: %s)', 'salt_woo' ), $response['ORDER_ID']) );

			                                        
			// Mark order as Paid
			$order->payment_complete();
			
			// save credit card information as postmeta.
			$card_info = array();
			$card_info['CARD_NUMBER'] = $credit_card->creditCardNumber;
			$card_info['EXP_DATE'] = $credit_card->expiryDate;
			$card_info['ZIP_CODE'] = $credit_card->zip;
			
			if($credit_card->cvv2)
				$card_info['CVV'] = $credit_card->cvv2;
				
			//debugPrint($card_info);
				
			update_post_meta( $order_id, '_salt_card_info', $card_info );
			
			// Empty the cart (Very important step)
			WC()->cart->empty_cart();
			
			return array(
			      'result'  => 'success',
			      //'redirect'  => $order->get_checkout_payment_url( true )
			      'redirect'  => $this->get_return_url( $order )
			      
			    );
		
		}
		else
		{
			$order->update_status('failed');
			
			throw new Exception( __( 'Sorry, Payment has failed.', 'salt_woo' ) );
		}


      }
      
      function process_preorder_payment($order, $service, $credit_card, $vr)
      {
 
      	//debugPrint('process_preorder_payment');
 
      	$order_id = $order->id;

		if ( WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) )
		{
			// upon release preorder payment
			
			//debugPrint('upon release preorder payment');

			// save credit card information as postmeta.
			$card_info = array();
			$card_info['CARD_NUMBER'] = $credit_card->creditCardNumber;
			$card_info['EXP_DATE'] = $credit_card->expiryDate;
			$card_info['ZIP_CODE'] = $credit_card->zip;
			
			if($credit_card->cvv2)
				$card_info['CVV'] = $credit_card->cvv2;
				
			//debugPrint($card_info);				
			
			update_post_meta( $order_id, '_salt_card_info', $card_info );
			
			// mark order as pre-ordered / reduce order stock
			WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

			$order->reduce_order_stock();

			
			// Empty the cart (Very important step)
			WC()->cart->empty_cart();
			
			return array(
			      'result'  => 'success',
			      //'redirect'  => $order->get_checkout_payment_url( true )
			      'redirect'  => $this->get_return_url( $order )
			      
			 );

			
		}
		else
		{
			// upfront preorder payment
		  //debugPrint('upfront preorder payment');

			return $this->process_standard_payment($order, $service, $credit_card, $vr);
		}
			
      }
      
      function process_standard_payment($order, $service, $credit_card, $vr)
      {
      	
      	//debugPrint('process_standard_payment');
      	
      	
      	$order_id = $order->id;
      	
      	$amount = $order->order_total * 100; // cents
      	
      	$timestamp = date('YmdHis');
		$order_id_salt = $timestamp . '-' . $order_id;
			
		// send request
		$receipt = $service->singlePurchase( $order_id_salt, $credit_card, $amount, $vr );
			
		// array dump of response params, you can access each param individually as well
		// (see DataClasses.php, class CreditCardReceipt)
		$response = $receipt->params;
		
		//debugPrint($response);
      	
      	
		if($response['APPROVED'])
		{
				
			// Payment has been successful
			$order->add_order_note( sprintf( __( 'SALT payment approved (ORDER_ID: %s)', 'salt_woo' ), $response['ORDER_ID']) );

				                                        
			// Mark order as Paid
			$order->payment_complete();
				
			// Empty the cart (Very important step)
			WC()->cart->empty_cart();
				
			return array(
			      'result'  => 'success',
			      //'redirect'  => $order->get_checkout_payment_url( true )
			      'redirect'  => $this->get_return_url( $order )
			      
			    );
			
		 }
		else
		{
			$order->update_status('failed');
					
			throw new Exception( __( 'Sorry, Payment has failed.', 'salt_woo' ) );
		}


      }
      /**
       * Process the payment and return the result
      **/
      function process_payment($order_id)
      {
      		//debugPrint('process_payment');
  		
			$order = new WC_Order($order_id);
      				
			// set SALT url
			if($this->test_mode == 'true')
			{
				$salt_url = $this->testurl;
			}
			else
			{
				$salt_url = $this->liveurl;
			}
			 
			// Mercahnt Inforamtion
			$merchant_id = $this->merchant_id;
			$api_token = $this->api_token;
			$merchant = new Merchant ($merchant_id, $api_token);
			//$merchant = new Merchant ( VALID_MERCHANT_ID, VALID_API_TOKEN );
			
			$service = new HttpsCreditCardService( $merchant, $salt_url );
			//debugPrint($salt_url);
			
			// Credit Card Information
			if(!empty($_POST['salt_card_number']))
			{
				$credit_card_number = $_POST['salt_card_number'];
			}
			else
			{
				throw new Exception( __( 'Credit Card Number is required.', 'salt_woo' ) );
			}
			
			// Validate Credit Card Number
			if(preg_match('/[^0-9]/', $credit_card_number))
			{
				throw new Exception( __( 'Credit Card Number is invalid.', 'salt_woo' ) );
			}
			
			if(!empty($_POST['salt_exp_month']))
			{
				$exp_month = $_POST['salt_exp_month'];
			}
			else
			{
				throw new Exception( __( 'Credit Card Expiry Month is required.', 'salt_woo' ) );
			}

			if(!empty($_POST['salt_exp_year']))
			{
				$exp_year = $_POST['salt_exp_year'];
			}
			else
			{
				throw new Exception( __( 'Credit Card Expiry Year is required.', 'salt_woo' ) );
			}
			
			// check using cvv
			$use_cvv = 0;
			$salt_cvv = '';
			if(!empty($_POST['use_cvv']))
			{
				$use_cvv = $_POST['use_cvv'];
			}
			
			//debugPrint($use_cvv);
			
			if($use_cvv)
			{
				if(!empty($_POST['salt_cvv']))
				{
					$salt_cvv = $_POST['salt_cvv'];
				}
				else
				{
					throw new Exception( __( 'CVV is required.', 'salt_woo' ) );
				}
				
				if(preg_match('/[^0-9]/', $salt_cvv))
				{
					throw new Exception( __( 'CVV is invalid.', 'salt_woo' ) );
				}


			}

			// Expiry date is of mmyy format
			$exp_yy = $exp_year % 100;
			$exp_date = $exp_month . $exp_yy;
			
			// postal /zip code
			$zip_code = $order->billing_postcode;
			
			if($use_cvv)
			{
				//debugPrint('use_cvv');
				//debugPrint($salt_cvv);
				$creditCard = new CreditCard( $credit_card_number, $exp_date, $salt_cvv, null, $zip_code);
				$vr = new VerificationRequest( SKIP_AVS_VALIDATE, VALIDATE_CVV );
				
			}
			else
			{
				$creditCard = new CreditCard( $credit_card_number, $exp_date, null, null, $zip_code);
				$vr = new VerificationRequest( SKIP_AVS_VALIDATE, SKIP_CVV_VALIDATE );
				
			}
			
			
			// Order Information
			if(class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Order::order_contains_subscription( $order_id ))
			{
				// pay subscription sign up payment
				return $this->process_subscription_initial_payment($order, $service, $creditCard, $vr);
			
			}
			else if(class_exists( 'WC_Pre_Orders_Order' ) && WC_Pre_Orders_Order::order_contains_pre_order( $order_id ))
			{
				return $this->process_preorder_payment($order, $service, $creditCard, $vr);
			}
			else
			{
				return $this->process_standard_payment($order, $service, $creditCard, $vr);
			}
			
	
    }
      
       
      public function web_redirect($url){
      
         echo "<html><head><script language=\"javascript\">
                <!--
                window.location=\"{$url}\";
                //-->
                </script>
                </head><body><noscript><meta http-equiv=\"refresh\" content=\"0;url={$url}\"></noscript></body></html>";
      
      }
 
 
 	public function process_subscription_payment( $order = '', $amount = 0 ) {
		
		$card_info = get_post_meta( $order->id, '_salt_card_info');
		
		//debugPrint('process_subscription_payment');
		//debugPrint($card_info);
		
		$credit_card_number = $card_info[0]['CARD_NUMBER'];
		$exp_date = $card_info[0]['EXP_DATE'];
		$zip_code =	$card_info[0]['ZIP_CODE'];

		
		if(isset($card_info[0]['CVV']) && !empty($card_info[0]['CVV']))
		{
			$creditCard = new CreditCard( $credit_card_number, $exp_date, $card_info[0]['CVV'], null, $zip_code);
			$vr = new VerificationRequest( SKIP_AVS_VALIDATE, VALIDATE_CVV );
		}
		else
		{
			$creditCard = new CreditCard( $credit_card_number, $exp_date, null, null, $zip_code);
			$vr = new VerificationRequest( SKIP_AVS_VALIDATE, SKIP_CVV_VALIDATE );
		}

		$timestamp = date('YmdHis');
		$order_id_salt = $timestamp . '- '. $order->id .'-' . 'SUB';
		$charge_amount = $amount * 100; // cents
		
		if($this->test_mode == 'true')
		{
			$salt_url = $this->testurl;
		}
		else
		{
			$salt_url = $this->liveurl;
		}
			 
		// Mercahnt Inforamtion
		$merchant_id = $this->merchant_id;
		$api_token = $this->api_token;
		$merchant = new Merchant ($merchant_id, $api_token);
		//$merchant = new Merchant ( VALID_MERCHANT_ID, VALID_API_TOKEN );
		
		$service = new HttpsCreditCardService( $merchant, $salt_url );

			
		// send request
		$receipt = $service->singlePurchase( $order_id_salt, $creditCard, $charge_amount, $vr );
		$response = $receipt->params;
		
		//debugPrint($response);
		
		if($response['APPROVED'])
		{

			//debugPrint('subscription payment success');
			
			// Payment has been successful
			$order->add_order_note( sprintf( __( 'SALT subscription payment approved (ORDER_ID: %s)', 'salt_woo' ), $response['ORDER_ID']) );

                                 
			// Mark order as Paid
			$order->payment_complete();
			
			return true;
			
		}
		else
		{
			//debugPrint('subscription payment failed');
			$order->add_order_note( __( 'SALT subscription payment failed', 'salt_woo' ) );

			return new WP_Error( 'salt_payment_failed', __( 'SALT subscription payment failed.', 'salt_woo' ) );

		}


	}

	/**
	 * scheduled_subscription_payment function.
	 *
	 * @param float $amount_to_charge The amount to charge.
	 * @param WC_Order $order The WC_Order object of the order which the subscription was purchased in.
	 * @param int $product_id The ID of the subscription product for which this payment relates.
	 * @return void
	 */
	public function process_scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
		$result = $this->process_subscription_payment( $order, $amount_to_charge );
	
		if ( is_wp_error( $result ) ) {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		} else {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}


	/**
	 * Don't copy over card information meta when creating a parent renewal order
	 *
	 * @since 1.0
	 * @param array $order_meta_query MySQL query for pulling the metadata
	 * @param int $original_order_id Post ID of the order being used to purchased the subscription being renewed
	 * @param int $renewal_order_id Post ID of the order created for renewing the subscription
	 * @param string $new_order_role The role the renewal order is taking, one of 'parent' or 'child'
	 * @return string
	 */
	public function remove_subscriptions_renewal_order_meta( $order_meta_query, $original_order_id, $renewal_order_id, $new_order_role ) {

		if ( 'parent' == $new_order_role ) {
			$order_meta_query .= " AND `meta_key` NOT IN ('_salt_card_info')";
		}

		return $order_meta_query;
	}


	/**
	 * Update the customer_id for a subscription after using SALT to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @param WC_Order $original_order The original order in which the subscription was purchased.
	 * @param WC_Order $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @param string $subscription_key A subscription key of the form created by @see WC_Subscriptions_Manager::get_subscription_key()
	 * @return void
	 */
	public function update_failing_payment_method( $original_order, $renewal_order, $subscription_key ) {
		$card_info = get_post_meta( $renewal_order->id, '_salt_card_info');

		update_post_meta( $original_order->id, '_salt_card_info', $card_info[0] );
	}

	public function process_pre_order_release_payment($order)
	{
			//debugPrint('pre_order_release_payment');
		
      		$order_id = $order->id;
      		
			// set SALT url
			if($this->test_mode == 'true')
			{
				$salt_url = $this->testurl;
			}
			else
			{
				$salt_url = $this->liveurl;
			}
			 
			// Mercahnt Inforamtion
			$merchant_id = $this->merchant_id;
			$api_token = $this->api_token;
			$merchant = new Merchant ($merchant_id, $api_token);
			//$merchant = new Merchant ( VALID_MERCHANT_ID, VALID_API_TOKEN );
			
			$service = new HttpsCreditCardService( $merchant, $salt_url );

			$card_info = get_post_meta( $order->id, '_salt_card_info');
			
			//debugPrint($card_info);

			
			
			$credit_card_number = $card_info[0]['CARD_NUMBER'];
			$exp_date = $card_info[0]['EXP_DATE'];
			$zip_code =	$card_info[0]['ZIP_CODE'];
		
			
			if(isset($card_info[0]['CVV']) && !empty($card_info[0]['CVV']))
			{
				$creditCard = new CreditCard( $credit_card_number, $exp_date, $card_info[0]['CVV'], null, $zip_code);
				$vr = new VerificationRequest( SKIP_AVS_VALIDATE, VALIDATE_CVV );
			}
			else
			{
				$creditCard = new CreditCard( $credit_card_number, $exp_date, null, null, $zip_code);
				$vr = new VerificationRequest( SKIP_AVS_VALIDATE, SKIP_CVV_VALIDATE );
			}
		
			$timestamp = date('YmdHis');
			$order_id_salt = $timestamp . '- '. $order->id .'-' . 'PRE';
			$charge_amount = $amount * 100;

			
			$timestamp = date('YmdHis');
			$order_id_salt = $timestamp . '-' . $order_id;
			
			// send request
			$receipt = $service->singlePurchase( $order_id_salt, $creditCard, $amount, $vr );
			
			// array dump of response params, you can access each param individually as well
			// (see DataClasses.php, class CreditCardReceipt)
			$response = $receipt->params;
			
			//debugPrint($response);

			if($response['APPROVED'])
			{
				
				// Payment has been successful
				$order->add_order_note( sprintf( __( 'SALT payment approved (ORDER_ID: %s)', 'salt_woo' ), $response['ORDER_ID']) );

				                                        
				// Mark order as Paid
				$order->payment_complete();
				
			
				// Empty the cart (Very important step)
				WC()->cart->empty_cart();
				
				return array(
				      'result'  => 'success',
				      //'redirect'  => $order->get_checkout_payment_url( true )
				      'redirect'  => $this->get_return_url( $order )
				      
				    );
			
			}
			else
			{
				$order->update_status('failed');
				
				throw new Exception( __( 'Sorry, Payment has failed.', 'salt_woo' ) );
			}
	}
       
   }// class end

   /**
    * Add this Gateway to WooCommerce
   **/
   function add_salt_woo_gateway($methods) 
   {
      $methods[] = 'Salt_Woo_Gateway';
      return $methods;
   }

   add_filter('woocommerce_payment_gateways', 'add_salt_woo_gateway' );
}

