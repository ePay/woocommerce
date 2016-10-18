<?php
/*
Plugin Name: WooCommerce ePay Payment Solutions Gateway
Plugin URI: http://www.epay.dk
Description: A payment gateway for ePay payment solutions standard
Version: 2.6.1
Author: ePay
Author URI: http://www.epay.dk/epay-payment-solutions
Text Domain: epay
 */

add_action('plugins_loaded', 'init_wc_epay_dk_gateway');

function init_wc_epay_dk_gateway()
{
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	define('epay_LIB', dirname(__FILE__) . '/lib/');

	/**
     * Gateway class
     **/
	class WC_Gateway_EPayDk extends WC_Payment_Gateway
	{
        const MODULE_VERSION = '2.6.1';

        public static $_instance;
        /**
         * get_instance
         *
         * Returns a new instance of self, if it does not already exist.
         *
         * @access public
         * @static
         * @return object WC_Gateway_EPayDK
         */
		public static function get_instance() {
			if (!isset( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct()
		{
			$this->id = 'epay_dk';
			$this->method_title = 'ePay';
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/ePay-logo.png';
			$this->has_fields = false;

			$this->supports = array('subscriptions',
                'products',
                'subscription_cancellation',
                'subscription_reactivation',
                'subscription_suspension',
                'subscription_amount_changes',
                'subscription_date_changes'
                );

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

            if($this->settings["remoteinterface"] == "yes")
                $this->supports = array_merge($this->supports, array('refunds'));

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
            $this->enableinvoice = $this->settings["enableinvoice"];
            $this->addfeetoorder = $this->settings["addfeetoorder"];
		}


        function init_hooks()
        {
            // Actions
			add_action('valid-epay-callback', array($this, 'successful_request'));

            if(is_admin())
            {
                if($this->remoteinterface == "yes")
                {
				    add_action( 'add_meta_boxes', array( $this, 'epay_meta_boxes'));
				}
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('wp_before_admin_bar_render', array($this, 'epay_action', ));
            }

			add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'check_callback'));
			add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
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
								'default' => __('ePay Payment Solutions', 'epay')
							),
				'description' => array(
								'title' => __('Description', 'woocommerce' , 'woocommerce-gateway-epay-dk'),
								'type' => 'textarea',
								'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce'),
								'default' => __("Pay using ePay Payment Solutions", 'woocommerce-gateway-epay-dk')
							),
				'merchant' => array(
								'title' => __('Merchant number', 'woocommerce-gateway-epay-dk'),
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
                'addfeetoorder' => array(
								'title' => __( 'Add fee to order', 'woocommerce-gateway-epay-dk'),
								'type' => 'checkbox',
								'label' => __( 'Add transaction fee to the order', 'woocommerce-gateway-epay-dk'),
								'default' => 'no'
							),
				'enableinvoice' => array(
								'title' => __( 'Invoice data', 'woocommerce-gateway-epay-dk'),
								'type' => 'checkbox',
								'label' => __( 'Enable invoice data', 'woocommerce-gateway-epay-dk'),
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
			echo '<a href="http://woocommerce.wpguiden.dk/en/configuration#709" target="_blank">'. __('Documentation can be found here', 'woocommerce-gateway-epay-dk').'</a>';
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

		function fix_url($url)
		{
			$url = str_replace('&#038;', '&amp;', $url);
			$url = str_replace('&amp;', '&', $url);

			return $url;
		}

		function yesnotoint($str)
		{
            return $str === 'yes' ? 1 : 0;
		}

		/**
         * Generate the epay button link
         **/
	    public function generate_epay_form($order_id)
		{
            require_once(epay_LIB . 'epayhelper.php');

            $helper = new epayhelper();
			$order = new WC_Order($order_id);

			$epay_args = array
			(
                'encoding' => "UTF-8",
			    'cms' => $this->getModuleHeaderInfo(),
                'windowstate' => $this->windowstate,
                'merchantnumber' => $this->merchant,
				'windowid' => $this->windowid,
                'amount' => (class_exists('WC_Subscriptions_Order')) ? (WC_Subscriptions_Order::order_contains_subscription($order ) ? (WC_Subscriptions_Order::get_total_initial_payment($order)*100) : ($order->order_total * 100)) : ($order->order_total * 100),
                'currency' => get_woocommerce_currency(),
                'orderid' => str_replace(_x( '#', 'hash before order number', 'woocommerce'), "", $order->get_order_number()),
                'accepturl' => $this->fix_url($this->get_return_url($order)),
				'cancelurl' => $this->fix_url($order->get_cancel_order_url()),
                'callbackurl' => $this->fix_url(add_query_arg ('wooorderid', $order_id, add_query_arg ('wc-api', 'WC_Gateway_EPayDk', $this->get_return_url( $order )))),
                'mailreceipt' => $this->authmail,
                'instantcapture' => $this->yesnotoint($this->instantcapture),
                'group' => $this->group,
                'language' => $helper->get_language_code(get_locale()),
                'ownreceipt' => $this->yesnotoint($this->ownreceipt),
                'timeout' => "60",
                'invoice' => $this->createInvoice($order),
				'subscription' => (class_exists('WC_Subscriptions_Order')) ? (WC_Subscriptions_Order::order_contains_subscription($order)) ? 1 : 0 : 0
			);

			if(strlen($this->md5key) > 0)
			{
				$hash = "";
				foreach($epay_args as $value)
				{
					$hash .= $value;
				}
				$epay_args["hash"] = md5($hash . $this->md5key);
			}

            $epay_args_array = array();
            foreach ($epay_args as $key => $value)
            {
                $epay_args_array[] = "'" . esc_attr($key) . "':'" . $value . "'";
            }

            $paymentScript = '<script type="text/javascript">
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

            return $paymentScript;
		}

        private function createInvoice($order)
        {
            if($this->enableinvoice  == "yes")
            {
                $invoice["customer"]["emailaddress"] = $this->jsonValueRemoveSpecialCharacters($order->billing_email);
                $invoice["customer"]["firstname"] = $this->jsonValueRemoveSpecialCharacters($order->billing_first_name);
                $invoice["customer"]["lastname"] = $this->jsonValueRemoveSpecialCharacters($order->billing_last_name);
                $invoice["customer"]["address"] = $this->jsonValueRemoveSpecialCharacters($order->billing_address_1);
                $invoice["customer"]["zip"] = $this->jsonValueRemoveSpecialCharacters($order->billing_postcode);
                $invoice["customer"]["city"] = $this->jsonValueRemoveSpecialCharacters($order->billing_city);
                $invoice["customer"]["country"] = $this->jsonValueRemoveSpecialCharacters($order->billing_country);

                $invoice["shippingaddress"]["firstname"] = $this->jsonValueRemoveSpecialCharacters($order->shipping_first_name);
                $invoice["shippingaddress"]["lastname"] = $this->jsonValueRemoveSpecialCharacters($order->shipping_last_name);
                $invoice["shippingaddress"]["address"] = $this->jsonValueRemoveSpecialCharacters($order->shipping_address_1);
                $invoice["shippingaddress"]["zip"] = $this->jsonValueRemoveSpecialCharacters($order->shipping_postcode);
                $invoice["shippingaddress"]["city"] = $this->jsonValueRemoveSpecialCharacters($order->shipping_city);
                $invoice["shippingaddress"]["country"] = $this->jsonValueRemoveSpecialCharacters($order->shipping_country);

                $invoice["lines"] = array();
                $items = $order->get_items();
                foreach($items as $item)
                {
                    $invoice["lines"][] = array(
                        "id" => $item["product_id"],
                        "description" => $this->jsonValueRemoveSpecialCharacters($item["name"]),
                        "quantity" => $item["qty"],
                        "price" => round($item["line_subtotal"] / $item["qty"] * 100),
                        "vat" => round($item["line_subtotal_tax"] / $item["line_subtotal"] * 100)
                    );
                }

                $discount = $order->get_total_discount();
                if($discount > 0)
                {
                    $invoice["lines"][] = array(
                        "id" => "discount",
                        "description" => "discount",
                        "quantity" => 1,
                        "price" => -round($discount * 100),
                        "vat" => round($order->get_total_tax() / ($order->get_total() - $order->get_total_tax())  * 100)
                    );
                }

                $shipping = $order->get_total_shipping();
                if($shipping > 0)
                {
                    $invoice["lines"][] = array(
                        "id" => "shipping",
                        "description" => "shipping",
                        "quantity" => 1,
                        "price" => round($shipping * 100),
                        "vat" => round($order->get_shipping_tax() / $shipping * 100)
                    );
                }

                return json_encode($invoice,JSON_UNESCAPED_UNICODE);
            }
            else
            {
                return "";
            }
        }

        function jsonValueRemoveSpecialCharacters($value)
        {
            return preg_replace('/[^\p{Latin}\d ]/u', '', $value);
        }

        /**
         * Returns the module header
         *
         * @return string
         */
        private function getModuleHeaderInfo()
        {
            global $woocommerce;
            $ePayVersion = WC_Gateway_EPayDk::MODULE_VERSION;
            $woocommerceVersion = $woocommerce->version;
            $result = 'WooCommerce/' . $woocommerceVersion . ' Module/' . $ePayVersion;
            return $result;
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

        function process_refund($order_id, $amount = null, $reason = '')
        {
            require_once(epay_LIB . 'class.epaysoap.php');

            $order = new WC_Order($order_id);
            $transactionId = get_post_meta($order->id, 'Transaction ID', true);

            $webservice = new epaysoap($this->remotepassword);
            $credit = $webservice->credit($this->merchant, $transactionId, $amount * 100);
            if(!is_wp_error($credit))
            {
                if($credit)
                    return true;
            }
            else
            {
                foreach($credit->get_error_messages() as $error)
                    $reason .= $error->get_error_message();
            }

            return false;
        }

         function scheduled_subscription_payment($amount_to_charge, $order)
         {
             require_once(epay_LIB . 'class.epaysoap.php');
             require_once(epay_LIB . 'helper.php');
             try
             {
                 $helper = new epayhelper();
                 $key = WC_Subscriptions_Manager::get_subscription_key($order->id);
                 $subscription = WC_Subscriptions_Manager::get_subscription($key);
                 $subscriptionOrderId = $subscription["order_id"];
                 $subscriptionid = get_post_meta($subscriptionOrderId, 'Subscription ID', true);
                 $webservice = new epaysoap($this->remotepassword, true);
                 $authorize = $webservice->authorize($this->merchant, $subscriptionid, date("dmY") . $subscriptionOrderId, $amount_to_charge * 100, $helper->get_iso_code(get_woocommerce_currency()), (bool)$this->yesnotoint($this->instantcapture), $this->group, $this->authmail);

                 if($authorize->authorizeResult)
                 {
                         WC_Subscriptions_Manager::process_subscription_payments_on_order($subscriptionOrderId);
                         update_post_meta($order->id,'Transaction ID', $authorize->transactionid);
                         $order->payment_complete();
                 }
                 else
                 {
                     $orderNote = __('Subscription could not be authorized', 'woocommerce-gateway-epay-dk');
                     if($authorize->epayresponse != "-1")
                     {
                         $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $authorize->epayresponse);;
                     }
                     elseif($authorize->pbsresponse != "-1")
                     {
                         $orderNote .= ' - ' . $webservice->getPbsError($this->merchant, $authorize->epayresponse);
                     }

                     $order->add_order_note($orderNote);
                     WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($subscriptionOrderId);
                 }
             }
             catch(Exception $error)
             {
                 $order->add_order_note(__('Subscription could not be authorized', 'woocommerce-gateway-epay-dk'));
                 WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($subscriptionOrderId);
             }
         }


        public function get_initial_subscription_id($order)
        {
            $is_subscription = wcs_is_subscription( $order->id );
            if($is_subscription)
            {
                $original_order = new WC_Order( $order->post->post_parent );
                $subscriptionid = get_post_meta($original_order->id, 'Subscription ID', true);
                return $subscriptionid;
            }
            else if(wcs_order_contains_renewal( $order ))
            {
                $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
                $subscription = end( $subscriptions );
                $original_order = new WC_Order($subscription->post->post_parent);
                $subscriptionid = get_post_meta($original_order->id, 'Subscription ID', true);
                return $subscriptionid;
            }

            return null;
        }


		/**
         * receipt_page
         **/
		function receipt_page( $order )
		{
			echo '<p>' . __("Thank you for your order, please click the button below to pay with ePay.", "woocommerce-gateway-epay-dk") . '</p>';
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
            $psbReference = get_post_meta((int)$posted["wooorderid"],'Transaction ID',true);

			if(empty($psbReference))
            {
                //Check for MD5 validity
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
                        return;
                    }
                }

				// Payment completed
				$order->add_order_note(__('Callback completed', 'woocommerce-gateway-epay-dk'));

                if($this->addfeetoorder == "yes")
                {
                    $order_fee              = new stdClass();
                    $order_fee->id          = 'epay_fee';
                    $order_fee->name        = __('Fee', 'woocommerce-gateway-epay-dk');
                    $order_fee->amount      = isset( $posted['txnfee'] ) ? floatval( $posted['txnfee'] / 100) : 0;
                    $order_fee->taxable     = false;
                    $order_fee->tax         = 0;
                    $order_fee->tax_data    = array();

                    $order->add_fee($order_fee);
                    $order->set_total($order->order_total + floatval($posted['txnfee'] / 100));
                }

				$order->payment_complete();

				update_post_meta((int)$posted["wooorderid"], 'Transaction ID', $posted["txnid"]);
				update_post_meta((int)$posted["wooorderid"], 'Card no', $posted["cardno"]);

				if(isset($posted["subscriptionid"]))
                {
					update_post_meta((int)$posted["wooorderid"], 'Subscription ID', $posted["subscriptionid"]);
                }
                echo "OK - Order Created";
			}
            else
            {
                echo "OK - Order already Created";
            }

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
            require_once (epay_LIB . 'class.epaysoap.php');
			global $post;

			$order = new WC_Order($post->ID);
            $transactionId = get_post_meta($order->id, 'Transaction ID', true);

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
						echo '<strong>' . _e('Authorized amount', 'woocommerce-gateway-epay-dk') . ':</strong> ' . $order->get_order_currency() . ' ' . number_format($transaction->transactionInformation->authamount / 100, 2, ".", "");
						echo '</p>';
						echo '<p>';
						echo '<strong>' . _e('Captured amount', 'woocommerce-gateway-epay-dk') . ':</strong> ' . $order->get_order_currency() . ' ' . number_format($transaction->transactionInformation->capturedamount / 100, 2, ".", "");
						echo '</p>';
						echo '<p>';
						echo '<strong>' . _e('Credited amount', 'woocommerce-gateway-epay-dk') . ':</strong> ' . $order->get_order_currency() . ' ' . number_format($transaction->transactionInformation->creditedamount / 100, 2, ".", "");
						echo '</p>';

						if($transaction->transactionInformation->status == "PAYMENT_NEW")
						{
							echo '<ul>';
                            echo '<li>';
                            echo '<p>';
                            echo $order->get_order_currency() . ' <span><input type="text" value="' . number_format(($transaction->transactionInformation->authamount - $transaction->transactionInformation->capturedamount) / 100, 2, ".", "") . '" id="epay_amount" name="epay_amount" /></span>';
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
                            echo $order->get_order_currency() . ' <span><input type="text" value="' . number_format(($transaction->transactionInformation->capturedamount) / 100, 2, ".", "") . '" id="epay_credit_amount" name="epay_credit_amount" /></span>';
                            echo '</p>';
                            echo '<a class="button" onclick="javascript: (confirm(\'' . __('Are you sure you want to credit?', 'woocommerce-gateway-epay-dk') . '\') ? (location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=credit') . '&amount=\' + document.getElementById(\'epay_credit_amount\').value) : (false));">';
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
	}

	add_filter('woocommerce_payment_gateways', 'add_epay_dk_gateway');
	WC_Gateway_EPayDk::get_instance()->init_hooks();

    /**
     * Add the Gateway to WooCommerce
     **/
	function add_epay_dk_gateway($methods)
	{
		$methods[] = 'WC_Gateway_EPayDk';
		return $methods;
	}

    $plugin_dir = basename(dirname(__FILE__ ));
    load_plugin_textdomain('woocommerce-gateway-epay-dk', false, $plugin_dir . '/languages');
}
