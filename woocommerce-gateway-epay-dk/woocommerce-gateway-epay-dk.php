<?php
/*
Plugin Name: WooCommerce ePay Payment Solutions Gateway
Plugin URI: http://www.epay.dk
Description: A payment gateway for ePay payment solutions standard (http://www.epay.dk/epay-payment-solutions/).
Version: 2.1
Author: ePay - Michael Korsgaard
Author URI: http://www.epay.dk/epay-payment-solutions/
Text Domain: epay
*/

add_action('plugins_loaded', 'add_wc_epay_dk_gateway', 0);

function add_wc_epay_dk_gateway() 
{
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }
	
	define('epay_LIB', dirname(__FILE__) . '/lib/');

	/**
 	* Gateway class
 	**/
	class WC_Gateway_EPayDk extends WC_Payment_Gateway
	{	
		public function __construct()
		{
			global $woocommerce;
			
			$this->id = 'epay_dk';
			$this->method_title = 'ePay';
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/ePay-logo.png';
			$this->has_fields = false;

			$this->supports = array('subscriptions', 'products', 'subscription_cancellation', 'subscription_reactivation', 'subscription_suspension', 'subscription_amount_changes', 'subscription_date_changes');
			
			// Load the form fields.
			$this->init_form_fields();
			
			// Load the settings.
			$this->init_settings();
			
			// Define user set variables
			$this->enabled = $this->settings["enabled"];
			$this->title = $this->settings["title"];
			$this->description = $this->settings["description"];
			$this->merchant = $this->settings["merchant"];
			$this->windowid = $this->settings["windowid"];
			$this->windowstate = $this->settings["windowstate"];
			$this->md5key = $this->settings["md5key"];
			$this->instantcapture = $this->settings["instantcapture"];
			$this->group = $this->settings["group"];
			$this->authmail = $this->settings["authmail"];
			$this->ownreceipt = $this->settings["ownreceipt"];
			$this->remoteinterface = $this->settings["remoteinterface"];
			$this->remotepassword = $this->settings["remotepassword"];
			
			// Actions
			add_action('init', array(& $this, 'check_callback', ));
			add_action('valid-epay-callback', array(&$this, 'successful_request', ));
			
			if($this->settings["remoteinterface"] == "yes")
				add_action('add_meta_boxes', array( &$this, 'epay_meta_boxes' ), 10, 0);

			add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'check_callback'));
			add_action('wp_before_admin_bar_render', array($this, 'epay_action', ));		
			add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options', ));			
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));			
			add_action('scheduled_subscription_payment_epay_dk', array($this, 'scheduled_subscription_payment'), 10, 3);		
			add_action('woocommerce_receipt_epay_dk', array($this, 'receipt_page'));
		}
	    
	  	/**
	     * Initialise Gateway Settings Form Fields
	     */
	    function init_form_fields()
		{			
	    	$this->form_fields = array(
				'enabled' => array(
								'title' => __( 'Enable/Disable', 'woocommerce'), 
								'type' => 'checkbox', 
								'label' => __( 'Enable ePay', 'woocommerce'), 
								'default' => 'yes'
							), 
				'title' => array(
								'title' => __( 'Title', 'epay' , 'woocommerce-gateway-epay-dk'), 
								'type' => 'text', 
								'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce'), 
								'default' => __( 'ePay Payment Solutions', 'epay')
							),
				'description' => array(
								'title' => __( 'Description', 'woocommerce' , 'woocommerce-gateway-epay-dk'), 
								'type' => 'textarea', 
								'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce'), 
								'default' => __("Pay using ePay Payment Solutions", 'woocommerce-gateway-epay-dk')
							),
				'merchant' => array(
								'title' => __( 'Merchant number', 'woocommerce-gateway-epay-dk'), 
								'type' => 'text',  
								'default' => ''
							),
				'windowid' => array(
								'title' => __( 'Window ID', 'woocommerce-gateway-epay-dk'), 
								'type' => 'text',  
								'default' => '1'
							),
				'windowstate' => array(
								'title' => __( 'Window state', 'woocommerce-gateway-epay-dk'), 
								'type' => 'select',
								'options' => array(1 => 'Overlay', 3 => 'Full screen'),
								'label' => __( 'How to open the ePay Payment Window', 'woocommerce-gateway-epay-dk'), 
								'default' => 1
							),
				'md5key' => array(
								'title' => __( 'MD5 Key', 'woocommerce-gateway-epay-dk'), 
								'type' => 'text', 
								'label' => __( 'Your md5 key', 'woocommerce-gateway-epay-dk')
							), 
				'instantcapture' => array(
								'title' => __( 'Instant capture', 'woocommerce-gateway-epay-dk'), 
								'type' => 'checkbox', 
								'label' => __( 'Enable instant capture', 'woocommerce-gateway-epay-dk'), 
								'default' => 'no'
							), 
				'group' => array(
								'title' => __( 'Group', 'woocommerce-gateway-epay-dk'), 
								'type' => 'text', 
							), 
				'authmail' => array(
								'title' => __( 'Auth Mail', 'woocommerce-gateway-epay-dk'), 
								'type' => 'text', 
							), 
				'ownreceipt' => array(
								'title' => __( 'Own receipt', 'woocommerce-gateway-epay-dk'), 
								'type' => 'checkbox', 
								'label' => __( 'Enable own receipt', 'woocommerce-gateway-epay-dk'), 
								'default' => 'no'
							), 
				'remoteinterface' => array(
								'title' => __( 'Remote interface', 'woocommerce-gateway-epay-dk'), 
								'type' => 'checkbox', 
								'label' => __( 'Use remote interface', 'woocommerce-gateway-epay-dk'), 
								'default' => 'no'
							), 
				'remotepassword' => array(
								'title' => __( 'Remote password', 'woocommerce-gateway-epay-dk'), 
								'type' => 'text', 
								'label' => __( 'Remote password', 'woocommerce-gateway-epay-dk')
							)			
				);
	    
	    } // End init_form_fields()
	    
		/**
		 * Admin Panel Options 
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */
		public function admin_options()
		{
			$plugin_data = get_plugin_data(__FILE__, false, false);
			$version = $plugin_data["Version"];
			
			echo '<h3>' . 'ePay Payment Solutions' . ' v' . $version . '</h3>';
			echo __('<a href="http://woocommerce.wpguiden.dk/en/configuration#709" target="_blank">Documentation can be found here</a>', 'woocommerce-gateway-epay-dk');
			echo '<table class="form-table">';
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
			echo '</table>';
		}
	    
	    /**
		 * There are no payment fields for epay, but we want to show the description if set.
		 **/
		function payment_fields()
		{
			if($this->description)
				echo wpautop(wptexturize($this->description));
		}
	    
		function yesnotoint($str)
		{
			switch($str)
			{
				case 'yes':
					return 1;
					break;
				case 'no':
					return 0;
					break;
			}
		}
		
		/**
		 * Generate the epay button link
		 **/
	    public function generate_epay_form($order_id)
		{
			global $woocommerce;
			
			$order = new WC_Order($order_id);
			
			$epay_args = array
			(
				'merchantnumber' => $this->merchant,
				'windowid' => $this->windowid,
				'windowstate' => $this->windowstate,
				'instantcallback' => 1,
				'instantcapture' => $this->yesnotoint($this->instantcapture),
				'group' => $this->group,
				'mailreceipt' => $this->authmail,
				'ownreceipt' => $this->yesnotoint($this->ownreceipt),
				'amount' => (class_exists('WC_Subscriptions_Order')) ? (WC_Subscriptions_Order::order_contains_subscription($order ) ? (WC_Subscriptions_Order::get_total_initial_payment($order)*100) : ($order->order_total * 100)) : ($order->order_total * 100),
				'orderid' => str_replace(_x( '#', 'hash before order number', 'woocommerce'), "", $order->get_order_number()),
				'currency' => get_woocommerce_currency(),
				'callbackurl' => add_query_arg ('wooorderid', $order_id, add_query_arg ('wc-api', 'WC_Gateway_EPayDk', $this->get_return_url( $order ))),
				'accepturl' => $this->get_return_url($order),
				'cancelurl' => $order->get_cancel_order_url(),
				'language' => 0,
				'subscription' => (class_exists('WC_Subscriptions_Order')) ? (WC_Subscriptions_Order::order_contains_subscription($order)) ? 1 : 0 : 0
			);
			
			if(strlen($this->md5key) > 0)
			{
				$hash = "";
				
				foreach($epay_args as $key => $value)
				{
					$hash .= $value;
				}
				
				$epay_args["hash"] = md5($hash . $this->md5key);
			}
			
			$epay_args_array = array();
			
			foreach ($epay_args as $key => $value)
			{
				$epay_args_array[] = '\'' . esc_attr($key) . '\': "' . $value . '"';
			}
			
			return '<script type="text/javascript">
			function PaymentWindowReady() {
				paymentwindow = new PaymentWindow({					
					' . implode(',', $epay_args_array) . '
				});
				paymentwindow.open();
			}
			</script>
			<script type="text/javascript" src="https://ssl.ditonlinebetalingssystem.dk/integration/ewindow/paymentwindow.js" charset="UTF-8"></script>
			<a class="button" onclick="javascript: paymentwindow.open();" id="submit_epay_payment_form" />' . __('Pay via ePay', 'woocommerce-gateway-epay-dk') . '</a>
			<a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce-gateway-epay-dk') . '</a>';
		}
		
		/**
		 * Process the payment and return the result
		 **/
		function process_payment($order_id)
		{
			$order = new WC_Order($order_id);
			
			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);
		}
		
		function scheduled_subscription_payment($amount_to_charge, $order, $product_id)
		{
			require_once (epay_LIB . 'class.epaysoap.php');
			
			try
			{
				$subscriptionid = get_post_meta($order->id, 'Subscription ID', true);
				
				$webservice = new epaysoap($this->remotepassword, true);
				$authorize = $webservice->authorize($this->merchant, $subscriptionid, date("d-m-Y") . $order->id, $amount_to_charge * 100, $this->get_iso_code(get_option('woocommerce_currency')), (bool)$this->yesnotoint($this->instantcapture), $this->group, $this->authmail);
				
				if(!is_wp_error($authorize))
				{
					if($authorize)
						WC_Subscriptions_Manager::process_subscription_payments_on_order($order);
				}
				else
				{
					foreach ($authorize->get_error_messages() as $error)
						throw new Exception ($error->get_error_message());
				}
			}
			catch(Exception $error)
			{
				WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order, $product_id);	
			}
		}


		/**
		 * receipt_page
		 **/
		function receipt_page( $order )
		{
			echo '<p>' . __('Thank you for your order, please click the button below to pay with ePay.', 'woocommerce-gateway-epay-dk') . '</p>';
			echo $this->generate_epay_form($order);
		}
		
		/**
		 * Check for epay IPN Response
		 **/
		function check_callback()
		{
			$_GET = stripslashes_deep($_GET);
			do_action("valid-epay-callback", $_GET);
		}
		
		/**
		 * Successful Payment!
		 **/
		function successful_request( $posted )
		{
			$order = new WC_Order((int)$posted["wooorderid"]);
			$var = "";
			
			if(strlen($this->md5key) > 0)
			{
				foreach($posted as $key => $value)
				{
					if($key != "hash")
						$var .= $value;
				}
				
				$genstamp = md5($var . $this->md5key);
				
				if($genstamp != $posted["hash"])
				{
					echo "MD5 error";
					error_log('MD5 check failed for ePay callback with order_id:' . $posted["wooorderid"]);
					status_header(500);
					exit;
				}
			}
				
			if($order->status !== 'completed')
			{
				// Payment completed
				$order->add_order_note(__('Callback completed', 'woocommerce-gateway-epay-dk'));
				$order->payment_complete();
				
				update_post_meta((int)$posted["wooorderid"], 'Transaction ID', $posted["txnid"]);
				update_post_meta((int)$posted["wooorderid"], 'Card no', $posted["cardno"]);
				
				if(isset($posted["subscriptionid"]))
					update_post_meta((int)$posted["wooorderid"], 'Subscription ID', $posted["subscriptionid"]);
			}
			
			echo "OK";
			status_header(200);
			
			exit;
		}
		
		public function epay_meta_boxes()
		{
			add_meta_box( 
				'epay-payment-actions', 
				__('ePay Payment Solutions', 'woocommerce-gateway-epay-dk'), 
				array(&$this, 'epay_meta_box_payment'), 
				'shop_order', 
				'side', 
				'high'
			);
		}
		
		public function epay_action()
		{
			global $woocommerce;
			
			if(isset($_GET["epay_action"]))
			{
				require_once (epay_LIB . 'class.epaysoap.php');

				$order = new WC_Order($_GET['post']);
				$transactionId = get_post_meta($order->id, 'Transaction ID', true);
				
				try
				{
					switch($_GET["epay_action"])
					{
						case 'capture':
							$webservice = new epaysoap($this->remotepassword);
							$capture = $webservice->capture($this->merchant, $transactionId, $_GET["amount"] * 100);
							if(!is_wp_error($capture))
							{
								if($capture)
									echo $this->message('updated', 'Payment successfully <strong>captured</strong>.');
							}
							else
							{
								foreach ($capture->get_error_messages() as $error)
									throw new Exception ($error->get_error_message());
							}
							
							break;
							
						case 'credit':
							$webservice = new epaysoap($this->remotepassword);
							$credit = $webservice->credit($this->merchant, $transactionId, $_GET["amount"] * 100);
							if(!is_wp_error($credit))
							{
								if($credit)
									echo $this->message('updated', 'Payment successfully <strong>credited</strong>.');
							}
							else
							{
								foreach($credit->get_error_messages() as $error)
									throw new Exception ($error->get_error_message());
							}
							
							break;
							
						case 'delete':
							$webservice = new epaysoap($this->remotepassword);
							$delete = $webservice->delete($this->merchant, $transactionId);
							if(!is_wp_error($delete))
							{
								if($delete)
									echo $this->message('updated', 'Payment successfully <strong>deleted</strong>.');
							}
							else
							{
								foreach ($delete->get_error_messages() as $error)
									throw new Exception ($error->get_error_message());
							}
							
							break;
					}
				}
				catch(Exception $e)
				{
					echo $this->message("error", $e->getMessage());
				}
			}
		}
		
		public function epay_meta_box_payment()
		{
			global $post, $woocommerce;
			
			$order = new WC_Order($post->ID);
			
			$transactionId = get_post_meta($order->id, 'Transaction ID', true);
			
			require_once (epay_LIB . 'class.epaysoap.php');
			
			if(strlen($transactionId) > 0)
			{
				try
				{
					$webservice = new epaysoap($this->remotepassword);
					$transaction = $webservice->gettransaction($this->merchant, $transactionId);
					
					if(!is_wp_error($transaction))
					{
						echo '<p>';
						echo '<strong>' . _e('Transaction ID', 'woocommerce-gateway-epay-dk') . ':</strong> ' . $transaction->transactionInformation->transactionid;
						echo '</p>';
						echo '<p>';
						echo '<strong>' . _e('Authorized amount', 'woocommerce-gateway-epay-dk') . ':</strong> ' . get_option('woocommerce_currency') . ' ' . number_format($transaction->transactionInformation->authamount / 100, 2, ".", "");
						echo '</p>';
						echo '<p>';
						echo '<strong>' . _e('Captured amount', 'woocommerce-gateway-epay-dk') . ':</strong> ' . get_option('woocommerce_currency') . ' ' . number_format($transaction->transactionInformation->capturedamount / 100, 2, ".", "");
						echo '</p>';
						echo '<p>';
						echo '<strong>' . _e('Credited amount', 'woocommerce-gateway-epay-dk') . ':</strong> ' . get_option('woocommerce_currency') . ' ' . number_format($transaction->transactionInformation->creditedamount / 100, 2, ".", "");
						echo '</p>';
						
						if($transaction->transactionInformation->status == "PAYMENT_NEW")
						{
							echo '<ul>';
								echo '<li>';
									echo '<p>';
										echo get_option('woocommerce_currency') . ' <span><input type="text" value="' . number_format(($transaction->transactionInformation->authamount - $transaction->transactionInformation->capturedamount) / 100, 2, ".", "") . '" id="epay_amount" name="epay_amount" /></span>';
									echo '</p>';
									echo '<a class="button" onclick="javascript:location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=capture') . '&amount=\' + document.getElementById(\'epay_amount\').value">';
										echo _e('Capture', 'woocommerce-gateway-epay-dk');
									echo '</a>';
								echo '</li>';
							echo '</ul><br />';
							
							echo '<a class="button" href="' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=delete') . '">';
							echo _e('Delete', 'woocommerce-gateway-epay-dk');
							echo '</a>';
						}
						elseif($transaction->transactionInformation->status == "PAYMENT_CAPTURED" && $transaction->transactionInformation->creditedamount == 0)
						{
							echo '<ul>';
								echo '<li>';
									echo '<p>';
										echo get_option('woocommerce_currency') . ' <span><input type="text" value="' . number_format(($transaction->transactionInformation->capturedamount) / 100, 2, ".", "") . '" id="epay_credit_amount" name="epay_credit_amount" /></span>';
									echo '</p>';
									echo '<a class="button" onclick="javascript:location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=credit') . '&amount=\' + document.getElementById(\'epay_credit_amount\').value">';
										echo _e('Credit', 'woocommerce-gateway-epay-dk');
									echo '</a>';
								echo '</li>';
							echo '</ul><br />';
						}
						
						echo '<br /><br />';
						
						$historyArray = $transaction->transactionInformation->history->TransactionHistoryInfo;
						
						if(!array_key_exists(0, $transaction->transactionInformation->history->TransactionHistoryInfo))
						{
							$historyArray = array($transaction->transactionInformation->history->TransactionHistoryInfo);
						}
						
						for($i = 0; $i < count($historyArray); $i++)
						{
							echo str_replace("T", " ", $historyArray[$i]->created) . ": ";
							if(strlen($historyArray[$i]->username) > 0)
								echo ($historyArray[$i]->username . ": ");
							echo $historyArray[$i]->eventMsg . "<br />";
						}
					}
					else
					{
						foreach ($transaction->get_error_messages() as $error)
						{
							throw new Exception ($error->get_error_message());
						}
					}
				}
				catch(Exception $e)
				{
					echo $this->message("error", $e->getMessage());
				}
			}
			else
				echo "No transaction was found.";
		}

		private function message($type, $message) {
			return '<div id="message" class="'.$type.'">
				<p>'.$message.'</p>
			</div>';
		}
		
		private function get_iso_code($code)
		{
			switch(strtoupper($code))
			{
				case 'ADP':
					return '020';
					break;
				case 'AED':
					return '784';
					break;
				case 'AFA':
					return '004';
					break;
				case 'ALL':
					return '008';
					break;
				case 'AMD':
					return '051';
					break;
				case 'ANG':
					return '532';
					break;
				case 'AOA':
					return '973';
					break;
				case 'ARS':
					return '032';
					break;
				case 'AUD':
					return '036';
					break;
				case 'AWG':
					return '533';
					break;
				case 'AZM':
					return '031';
					break;
				case 'BAM':
					return '977';
					break;
				case 'BBD':
					return '052';
					break;
				case 'BDT':
					return '050';
					break;
				case 'BGL':
					return '100';
					break;
				case 'BGN':
					return '975';
					break;
				case 'BHD':
					return '048';
					break;
				case 'BIF':
					return '108';
					break;
				case 'BMD':
					return '060';
					break;
				case 'BND':
					return '096';
					break;
				case 'BOB':
					return '068';
					break;
				case 'BOV':
					return '984';
					break;
				case 'BRL':
					return '986';
					break;
				case 'BSD':
					return '044';
					break;
				case 'BTN':
					return '064';
					break;
				case 'BWP':
					return '072';
					break;
				case 'BYR':
					return '974';
					break;
				case 'BZD':
					return '084';
					break;
				case 'CAD':
					return '124';
					break;
				case 'CDF':
					return '976';
					break;
				case 'CHF':
					return '756';
					break;
				case 'CLF':
					return '990';
					break;
				case 'CLP':
					return '152';
					break;
				case 'CNY':
					return '156';
					break;
				case 'COP':
					return '170';
					break;
				case 'CRC':
					return '188';
					break;
				case 'CUP':
					return '192';
					break;
				case 'CVE':
					return '132';
					break;
				case 'CYP':
					return '196';
					break;
				case 'CZK':
					return '203';
					break;
				case 'DJF':
					return '262';
					break;
				case 'DKK':
					return '208';
					break;
				case 'DOP':
					return '214';
					break;
				case 'DZD':
					return '012';
					break;
				case 'ECS':
					return '218';
					break;
				case 'ECV':
					return '983';
					break;
				case 'EEK':
					return '233';
					break;
				case 'EGP':
					return '818';
					break;
				case 'ERN':
					return '232';
					break;
				case 'ETB':
					return '230';
					break;
				case 'EUR':
					return '978';
					break;
				case 'FJD':
					return '242';
					break;
				case 'FKP':
					return '238';
					break;
				case 'GBP':
					return '826';
					break;
				case 'GEL':
					return '981';
					break;
				case 'GHC':
					return '288';
					break;
				case 'GIP':
					return '292';
					break;
				case 'GMD':
					return '270';
					break;
				case 'GNF':
					return '324';
					break;
				case 'GTQ':
					return '320';
					break;
				case 'GWP':
					return '624';
					break;
				case 'GYD':
					return '328';
					break;
				case 'HKD':
					return '344';
					break;
				case 'HNL':
					return '340';
					break;
				case 'HRK':
					return '191';
					break;
				case 'HTG':
					return '332';
					break;
				case 'HUF':
					return '348';
					break;
				case 'IDR':
					return '360';
					break;
				case 'ILS':
					return '376';
					break;
				case 'INR':
					return '356';
					break;
				case 'IQD':
					return '368';
					break;
				case 'IRR':
					return '364';
					break;
				case 'ISK':
					return '352';
					break;
				case 'JMD':
					return '388';
					break;
				case 'JOD':
					return '400';
					break;
				case 'JPY':
					return '392';
					break;
				case 'KES':
					return '404';
					break;
				case 'KGS':
					return '417';
					break;
				case 'KHR':
					return '116';
					break;
				case 'KMF':
					return '174';
					break;
				case 'KPW':
					return '408';
					break;
				case 'KRW':
					return '410';
					break;
				case 'KWD':
					return '414';
					break;
				case 'KYD':
					return '136';
					break;
				case 'KZT':
					return '398';
					break;
				case 'LAK':
					return '418';
					break;
				case 'LBP':
					return '422';
					break;
				case 'LKR':
					return '144';
					break;
				case 'LRD':
					return '430';
					break;
				case 'LSL':
					return '426';
					break;
				case 'LTL':
					return '440';
					break;
				case 'LVL':
					return '428';
					break;
				case 'LYD':
					return '434';
					break;
				case 'MAD':
					return '504';
					break;
				case 'MDL':
					return '498';
					break;
				case 'MGF':
					return '450';
					break;
				case 'MKD':
					return '807';
					break;
				case 'MMK':
					return '104';
					break;
				case 'MNT':
					return '496';
					break;
				case 'MOP':
					return '446';
					break;
				case 'MRO':
					return '478';
					break;
				case 'MTL':
					return '470';
					break;
				case 'MUR':
					return '480';
					break;
				case 'MVR':
					return '462';
					break;
				case 'MWK':
					return '454';
					break;
				case 'MXN':
					return '484';
					break;
				case 'MXV':
					return '979';
					break;
				case 'MYR':
					return '458';
					break;
				case 'MZM':
					return '508';
					break;
				case 'NAD':
					return '516';
					break;
				case 'NGN':
					return '566';
					break;
				case 'NIO':
					return '558';
					break;
				case 'NOK':
					return '578';
					break;
				case 'NPR':
					return '524';
					break;
				case 'NZD':
					return '554';
					break;
				case 'OMR':
					return '512';
					break;
				case 'PAB':
					return '590';
					break;
				case 'PEN':
					return '604';
					break;
				case 'PGK':
					return '598';
					break;
				case 'PHP':
					return '608';
					break;
				case 'PKR':
					return '586';
					break;
				case 'PLN':
					return '985';
					break;
				case 'PYG':
					return '600';
					break;
				case 'QAR':
					return '634';
					break;
				case 'ROL':
					return '642';
					break;
				case 'RUB':
					return '643';
					break;
				case 'RUR':
					return '810';
					break;
				case 'RWF':
					return '646';
					break;
				case 'SAR':
					return '682';
					break;
				case 'SBD':
					return '090';
					break;
				case 'SCR':
					return '690';
					break;
				case 'SDD':
					return '736';
					break;
				case 'SEK':
					return '752';
					break;
				case 'SGD':
					return '702';
					break;
				case 'SHP':
					return '654';
					break;
				case 'SIT':
					return '705';
					break;
				case 'SKK':
					return '703';
					break;
				case 'SLL':
					return '694';
					break;
				case 'SOS':
					return '706';
					break;
				case 'SRG':
					return '740';
					break;
				case 'STD':
					return '678';
					break;
				case 'SVC':
					return '222';
					break;
				case 'SYP':
					return '760';
					break;
				case 'SZL':
					return '748';
					break;
				case 'THB':
					return '764';
					break;
				case 'TJS':
					return '972';
					break;
				case 'TMM':
					return '795';
					break;
				case 'TND':
					return '788';
					break;
				case 'TOP':
					return '776';
					break;
				case 'TPE':
					return '626';
					break;
				case 'TRL':
					return '792';
					break;
				case 'TRY':
					return '949';
					break;
				case 'TTD':
					return '780';
					break;
				case 'TWD':
					return '901';
					break;
				case 'TZS':
					return '834';
					break;
				case 'UAH':
					return '980';
					break;
				case 'UGX':
					return '800';
					break;
				case 'USD':
					return '840';
					break;
				case 'UYU':
					return '858';
					break;
				case 'UZS':
					return '860';
					break;
				case 'VEB':
					return '862';
					break;
				case 'VND':
					return '704';
					break;
				case 'VUV':
					return '548';
					break;
				case 'XAF':
					return '950';
					break;
				case 'XCD':
					return '951';
					break;
				case 'XOF':
					return '952';
					break;
				case 'XPF':
					return '953';
					break;
				case 'YER':
					return '886';
					break;
				case 'YUM':
					return '891';
					break;
				case 'ZAR':
					return '710';
					break;
				case 'ZMK':
					return '894';
					break;
				case 'ZWD':
					return '716';
					break;
			}
			
			return '208';
		}
	}

	/**
 	* Add the Gateway to WooCommerce
 	**/
	function add_epay_dk_gateway($methods) 
	{
		$methods[] = 'WC_Gateway_EPayDk';
		return $methods;
	}
	
	function init_epay_dk_gateway()
	{
		$plugin_dir = basename(dirname(__FILE__ ));
		load_plugin_textdomain('woocommerce-gateway-epay-dk', false, $plugin_dir . '/languages/');
	}
	
	add_filter('woocommerce_payment_gateways', 'add_epay_dk_gateway');
	add_action('plugins_loaded', 'init_epay_dk_gateway');
	
	function WC_Gateway_EPayDk() 
	{
	    return new WC_Gateway_EPayDk();
	}
	
	if (is_admin())
    	add_action('load-post.php', 'WC_Gateway_EPayDk');
}
