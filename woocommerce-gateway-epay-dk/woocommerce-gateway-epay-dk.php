<?php
/*
Plugin Name: WooCommerce ePay Payment Solutions Gateway
Plugin URI: http://www.epay.dk
Description: A payment gateway for ePay payment solutions standard
Version: 2.6.7
Author: ePay
Author URI: http://www.epay.dk/epay-payment-solutions
Text Domain: epay
 */

/*
Add Bambora Stylesheet and javascript to plugin
 */
add_action('admin_enqueue_scripts', 'enqueue_wc_epay_style');

function enqueue_wc_epay_style()
{
    wp_enqueue_style('epay_style',  WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/style/epay.css');
}

add_action('plugins_loaded', 'init_wc_epay_dk_gateway');

function init_wc_epay_dk_gateway()
{
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	define('epay_LIB', dirname(__FILE__) . '/lib/');

    include(epay_LIB . 'epaysoap.php');
    include(epay_LIB . 'epayhelper.php');

	/**
     * Gateway class
     **/
	class WC_Gateway_EPayDk extends WC_Payment_Gateway
	{
        const MODULE_VERSION = '2.6.7';

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
                'subscription_date_changes',
                'multiple_subscriptions'
                );

			// Load the form fields.
			$this->initFormFields();

			// Load the settings.
			$this->init_settings();

            // Define user set variables
			$this->enabled = array_key_exists("enabled", $this->settings) ? $this->settings["enabled"] : "yes";
			$this->title = array_key_exists("title", $this->settings) ? $this->settings["title"] : __('ePay Payment Solutions', 'woocommerce-gateway-epay-dk');
			$this->description = array_key_exists("description", $this->settings) ? $this->settings["description"] : __("Pay using ePay Payment Solutions", 'woocommerce-gateway-epay-dk');
			$this->merchant = array_key_exists("merchant", $this->settings) ? $this->settings["merchant"] : "";
			$this->windowid = array_key_exists("windowid", $this->settings) ? $this->settings["windowid"] : "1";
			$this->windowstate = array_key_exists("windowstate", $this->settings) ? $this->settings["windowstate"] : 1;
			$this->md5key = array_key_exists("md5key", $this->settings) ? $this->settings["md5key"] : "";
			$this->instantcapture = array_key_exists("instantcapture", $this->settings) ? $this->settings["instantcapture"] : "no";
			$this->group = array_key_exists("group", $this->settings) ? $this->settings["group"] : "";
			$this->authmail = array_key_exists("authmail", $this->settings) ? $this->settings["authmail"] : "";
			$this->ownreceipt = array_key_exists("ownreceipt", $this->settings) ? $this->settings["ownreceipt"] : "no";
			$this->remoteinterface = array_key_exists("remoteinterface", $this->settings) ? $this->settings["remoteinterface"] : "no";
			$this->remotepassword = array_key_exists("remotepassword", $this->settings) ? $this->settings["remotepassword"] : "";
            $this->enableinvoice = array_key_exists("enableinvoice", $this->settings) ? $this->settings["enableinvoice"] : "no";
            $this->addfeetoorder = array_key_exists("addfeetoorder", $this->settings) ? $this->settings["addfeetoorder"] : "no";
            $this->enablemobilepaymentwindow = array_key_exists("enablemobilepaymentwindow", $this->settings) ? $this->settings["enablemobilepaymentwindow"] : "yes";


            $this->set_epay_description_for_checkout($this->merchant);

            if($this->yesnotoint($this->remoteinterface))
            {
                $this->supports = array_merge($this->supports, array('refunds'));
            }
		}

        function init_hooks()
        {
            // Actions
			add_action('valid-epay-callback', array($this, 'successful_request'));
			add_action('woocommerce_api_' . strtolower(get_class()), array($this, 'check_callback'));
			add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
            add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'subscription_cancellation'));
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            if(is_admin())
            {
                if($this->remoteinterface == "yes")
                {
				    add_action( 'add_meta_boxes', array( $this, 'epay_meta_boxes'));
				}

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('wp_before_admin_bar_render', array($this, 'epay_action', ));
            }
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
	    function initFormFields()
		{
	    	$this->form_fields = array(
				'enabled' => array(
								'title' => 'Enable/Disable',
								'type' => 'checkbox',
								'label' => 'Enable ePay',
								'default' => 'yes'
							),
				'title' => array(
								'title' => 'Title',
								'type' => 'text',
								'description' => 'This controls the title which the user sees during checkout.',
								'default' => 'ePay Payment Solutions'
							),
				'description' => array(
								'title' => 'Description',
								'type' => 'textarea',
								'description' => 'This controls the description which the user sees during checkout.',
								'default' => 'Pay using ePay Payment Solutions'
							),
				'merchant' => array(
								'title' => 'Merchant number',
								'type' => 'text',
								'default' => ''
							),
				'windowid' => array(
								'title' => 'Window ID',
								'type' => 'text',
								'default' => '1'
							),
				'windowstate' => array(
								'title' => 'Window state',
								'type' => 'select',
								'options' => array(1 => 'Overlay', 3 => 'Full screen'),
								'label' => 'How to open the ePay Payment Window',
								'default' => 1
							),
				'md5key' => array(
								'title' => 'MD5 Key',
								'type' => 'text',
								'label' => 'Your md5 key'
							),
				'instantcapture' => array(
								'title' => 'Instant capture',
								'type' => 'checkbox',
								'label' => 'Enable instant capture',
								'default' => 'no'
							),
				'group' => array(
								'title' => 'Group',
								'type' => 'text',
							),
				'authmail' => array(
								'title' => 'Auth Mail',
								'type' => 'text',
							),
				'ownreceipt' => array(
								'title' => 'Own receipt',
								'type' => 'checkbox',
								'label' => 'Enable own receipt',
								'default' => 'no'
							),
                'addfeetoorder' => array(
								'title' => 'Add fee to order',
								'type' => 'checkbox',
								'label' => 'Add transaction fee to the order',
								'default' => 'no'
							),
				'enableinvoice' => array(
								'title' => 'Invoice data',
								'type' => 'checkbox',
								'label' => 'Enable invoice data',
								'default' => 'no'
							),
				'remoteinterface' => array(
								'title' => 'Remote interface',
								'type' => 'checkbox',
								'label' => 'Use remote interface',
								'default' => 'no'
							),
				'remotepassword' => array(
								'title' => 'Remote password',
								'type' => 'password',
								'label' => 'Remote password'
							),
                'enablemobilepaymentwindow' => array(
								'title' => 'Mobile Payment Window',
								'type' => 'checkbox',
								'label' => 'Enable Mobile Payment Window',
                                'default' => 'yes'
							)
				);

	    }

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
            {
				echo wpautop(wptexturize($this->description));
            }
        }

        /**
         * Set the WC Payment Gateway description for the checkout page
         */
        function set_epay_description_for_checkout($merchantnumber)
        {
            global $woocommerce;
            $cart = $woocommerce->cart;
            if(!$cart || !$merchantnumber)
            {
                return;
            }

            $this->description .= '<span id="epay_card_logos"></span><script type="text/javascript" src="https://relay.ditonlinebetalingssystem.dk/integration/paymentlogos/PaymentLogos.aspx?merchantnumber='.$merchantnumber.'&direction=2&padding=2&rows=1&logo=0&showdivs=0&cardwidth=45&divid=epay_card_logos"></script>';
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
            $order = new WC_Order($order_id);
            $minorUnits = EpayHelper::getCurrencyMinorunits($order->get_order_currency());
			$epay_args = array
			(
                'encoding' => "UTF-8",
			    'cms' => $this->getModuleHeaderInfo(),
                'windowstate' => $this->windowstate,
                'mobile' => $this->enablemobilepaymentwindow === 'yes' ? 1 : 0,
                'merchantnumber' => $this->merchant,
				'windowid' => $this->windowid,
                'currency' => $order->get_order_currency(),
                'amount' => EpayHelper::convertPriceToMinorUnits($order->get_total(), $minorUnits),
                'orderid' => str_replace(_x( '#', 'hash before order number', 'woocommerce'), "", $order->get_order_number()),
                'accepturl' => $this->fix_url($this->get_return_url($order)),
				'cancelurl' => $this->fix_url($order->get_cancel_order_url()),
                'callbackurl' => $this->fix_url(add_query_arg ('wooorderid', $order_id, add_query_arg ('wc-api', 'WC_Gateway_EPayDk', $this->get_return_url( $order )))),
                'mailreceipt' => $this->authmail,
                'instantcapture' => $this->yesnotoint($this->instantcapture),
                'group' => $this->group,
                'language' => EpayHelper::get_language_code(get_locale()),
                'ownreceipt' => $this->yesnotoint($this->ownreceipt),
                'timeout' => "60",
                'invoice' => $this->createInvoice($order, $minorUnits),
			);

            if($this->woocommerce_subscription_plugin_is_active() && wcs_order_contains_subscription($order))
            {
                $epay_args['subscription'] = 1;
            }


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

        private function createInvoice($order, $minorUnits)
        {
            if($this->enableinvoice  == "yes")
            {
                $invoice["customer"]["emailaddress"] = $order->billing_email;
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
                        "price" => EpayHelper::convertPriceToMinorUnits($item["line_subtotal"] / $item["qty"], $minorUnits),
                        "vat" => round(($item["line_subtotal_tax"] / $item["line_subtotal"]) * 100)
                    );
                }

                $discount = $order->get_total_discount();
                if($discount > 0)
                {
                    $invoice["lines"][] = array(
                        "id" => "discount",
                        "description" => "discount",
                        "quantity" => 1,
                        "price" => EpayHelper::convertPriceToMinorUnits($discount * -1, $minorUnits),
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
                        "price" => EpayHelper::convertPriceToMinorUnits($shipping, $minorUnits),
                        "vat" => round(($order->get_shipping_tax() / $shipping) * 100)
                    );
                }

                return json_encode($invoice, JSON_UNESCAPED_UNICODE);
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
            $order = new WC_Order($order_id);
            $transactionId = get_post_meta($order->id, 'Transaction ID', true);
            $minorUnits = EpayHelper::getCurrencyMinorunits($order->get_order_currency);
            $webservice = new EpaySoap($this->remotepassword);
            $credit = $webservice->credit($this->merchant, $transactionId, EpayHelper::convertPriceToMinorUnits($amount, $minorUnits));
            if($credit->creditResult === true)
            {
                echo $this->message('updated', 'Payment successfully <strong>credited</strong>.');
                return true;
            }
            else
            {
                $orderNote = __('Credit action failed', 'woocommerce-gateway-epay-dk');
                if($credit->epayresponse != "-1")
                {
                    $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $credit->epayresponse);
                }
                elseif($credit->pbsresponse != "-1")
                {
                    $orderNote .= ' - ' . $webservice->getPbsError($this->merchant, $credit->pbsresponse);
                }

                echo $this->message("error", $orderNote);
                return false;
            }
        }

        private function getSubscription($order)
        {
            if(!function_exists('wcs_get_subscriptions_for_renewal_order'))
            {
                return null;
            }
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
            return end($subscriptions);
        }

        function scheduled_subscription_payment($amount_to_charge, $renewal_order)
        {
            try
            {
                // Get the subscription based on the renewal order
                $subscription = $this->getSubscription($renewal_order);
                if(isset($subscription))
                {
                    $parentOrder = $subscription->order;
                    $bamboraSubscriptionId = get_post_meta($parentOrder->id, 'Subscription ID', true);
                    $orderCurrency = $renewal_order->get_order_currency();
                    $webservice = new EpaySoap($this->remotepassword, true);

                    $minorUnits = EpayHelper::getCurrencyMinorunits($orderCurrency);
                    $amount = EpayHelper::convertPriceToMinorUnits($amount_to_charge, $minorUnits);

                    $authorize = $webservice->authorize($this->merchant, $bamboraSubscriptionId, $renewal_order->id, $amount, EpayHelper::get_iso_code($orderCurrency), (bool)$this->yesnotoint($this->instantcapture), $this->group, $this->authmail);
                    if($authorize->authorizeResult === true)
                    {
                        update_post_meta($renewal_order->id,'Transaction ID', $authorize->transactionid);
                        $renewal_order->payment_complete();
                    }
                    else
                    {
                        $orderNote = __('Subscription could not be authorized', 'woocommerce-gateway-epay-dk');
                        if($authorize->epayresponse != "-1")
                        {
                            $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $authorize->epayresponse);
                        }
                        elseif($authorize->pbsresponse != "-1")
                        {
                            $orderNote .= ' - ' . $webservice->getPbsError($this->merchant, $authorize->pbsresponse);
                        }
                        $renewal_order->update_status('failed', $orderNote);
                        $subscription->add_order_note($orderNote . ' ID: ' . $renewal_order->id);
                    }
                }
                else
                {
                    $renewal_order->update_status('failed', __('No subscription found', 'woocommerce-gateway-epay-dk'));
                }

            }
            catch(Exception $ex)
            {
                $renewal_order->update_status('failed', $ex->getMessage());
            }
        }

        public function subscription_cancellation($subscription)
        {
            try
            {
                if (function_exists('wcs_is_subscription') && wcs_is_subscription($subscription))
                {
                    $parentOrder = $subscription->order;
                    $bamboraSubscriptionId = get_post_meta($parentOrder->id, 'Subscription ID', true);
                    if(empty($bamboraSubscriptionId))
                    {
                        throw new Exception(__('Bambora Subscription ID was not found', 'woocommerce-gateway-epay-dk'));
                    }
                    $webservice = new EpaySoap($this->remotepassword, true);
                    $deletesubscription = $webservice->deletesubscription($this->merchant, $bamboraSubscriptionId);
                    if($deletesubscription->deletesubscriptionResult === true)
                    {
                        $subscription->add_order_note(__('Subscription successfully Canceled.', 'woocommerce-gateway-epay-dk'));
                    }
                    else
                    {
                        $orderNote = __('Subscription could not be canceled', 'woocommerce-gateway-epay-dk');
                        if($deletesubscription->epayresponse != "-1")
                        {
                            $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $deletesubscription->epayresponse);;
                        }
                       
                        $subscription->add_order_note($orderNote);
                        throw new Exception($orderNote);
                    }
                }
            }
            catch(Exception $ex)
            {
                //TODO Implement loging function
            }
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
		function successful_request($posted)
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
                        {
                            $var .= $value;
                        }
                    }

                    $genstamp = md5($var . $this->md5key);

                    if($genstamp != $posted["hash"])
                    {
                        $message = 'MD5 check failed for ePay callback with order_id:' . $posted["wooorderid"];
                        $order->add_order_note($message);
                        error_log($message);
                        status_header(500);
                        die($message);
                    }
                }

				// Payment completed
				$order->add_order_note(__('Callback completed', 'woocommerce-gateway-epay-dk'));
                $minorUnits = EpayHelper::getCurrencyMinorunits($order->get_order_currency);

                if($posted['txnfee'] > 0 && $this->addfeetoorder == "yes")
                {
                    $feeAmount = floatval(EpayHelper::convertPriceFromMinorUnits($posted['txnfee'], $minorUnits));
                    $order_fee              = new stdClass();
                    $order_fee->id          = 'epay_surcharge_fee';
                    $order_fee->name        = __('Surcharge Fee', 'woocommerce-gateway-epay-dk');
                    $order_fee->amount      = $feeAmount;
                    $order_fee->taxable     = false;
                    $order_fee->tax         = 0;
                    $order_fee->tax_data    = array();

                    $order->add_fee($order_fee);
                    $order->set_total($order->order_total + floatval(EpayHelper::convertPriceFromMinorUnits($posted['txnfee'], $minorUnits)));
                }

				$order->payment_complete();

				update_post_meta((int)$posted["wooorderid"], 'Transaction ID', $posted["txnid"]);
                update_post_meta((int)$posted["wooorderid"], 'Payment Type ID', $posted["paymenttype"]);

				if($this->woocommerce_subscription_plugin_is_active() && isset($posted["subscriptionid"]))
                {
                    WC_Subscriptions_Manager::activate_subscriptions_for_order($order);
					update_post_meta((int)$posted["wooorderid"], 'Subscription ID', $posted["subscriptionid"]);
                }
                status_header(200);
                die("Order Created");
			}
            else
            {
                status_header(200);
                die("Order already Created");
            }
		}

        /**
         * Checks if Woocommerce Subscriptions is enabled or not
         */
        private function woocommerce_subscription_plugin_is_active()
        {
            return class_exists('WC_Subscriptions') && WC_Subscriptions::$name = 'subscription';
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
				$order = new WC_Order($_GET['post']);
				$transactionId = get_post_meta($order->id, 'Transaction ID', true);
                $minorUnits = EpayHelper::getCurrencyMinorunits($order->get_order_currency);
				try
				{
					switch($_GET["epay_action"])
					{
						case 'capture':
                            $amount = str_replace(wc_get_price_decimal_separator(),".",$_GET["amount"]);
							$webservice = new EpaySoap($this->remotepassword);

							$capture = $webservice->capture($this->merchant, $transactionId, EpayHelper::convertPriceToMinorUnits($amount, $minorUnits));
							if($capture->captureResult === true)
                            {
                                echo $this->message('updated', 'Payment successfully <strong>captured</strong>.');
                            }
                            else
                            {
                                $orderNote = __('Capture action failed', 'woocommerce-gateway-epay-dk');
                                if($capture->epayresponse != "-1")
                                {
                                    $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $capture->epayresponse);
                                }
                                elseif($capture->pbsResponse != "-1")
                                {
                                    $orderNote .= ' - ' . $webservice->getPbsError($this->merchant, $capture->pbsResponse);
                                }

                                echo $this->message("error", $orderNote);
                            }

                            break;

                        case 'credit':
                            $amount = str_replace(wc_get_price_decimal_separator(),".",$_GET["amount"]);
							$webservice = new EpaySoap($this->remotepassword);
							$credit = $webservice->credit($this->merchant, $transactionId, EpayHelper::convertPriceToMinorUnits($amount, $minorUnits));
                            if($credit->creditResult === true)
                            {
                                echo $this->message('updated', 'Payment successfully <strong>credited</strong>.');
                            }
                            else
                            {
                                $orderNote = __('Credit action failed', 'woocommerce-gateway-epay-dk');
                                if($credit->epayresponse != "-1")
                                {
                                    $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $credit->epayresponse);
                                }
                                elseif($credit->pbsresponse != "-1")
                                {
                                    $orderNote .= ' - ' . $webservice->getPbsError($this->merchant, $credit->pbsresponse);
                                }

                                echo $this->message("error", $orderNote);
                            }

							break;

						case 'delete':
							$webservice = new EpaySoap($this->remotepassword);
							$delete = $webservice->delete($this->merchant, $transactionId);
                            if($delete->deleteResult === true)
                            {
                                echo $this->message('updated', 'Payment successfully <strong>deleted</strong>.');
                            }
                            else
                            {
                                $orderNote = __('Delete action failed', 'woocommerce-gateway-epay-dk');
                                if($delete->epayresponse != "-1")
                                {
                                    $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $delete->epayresponse);
                                }

                                echo $this->message("error", $orderNote);
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
			global $post;

			$order = new WC_Order($post->ID);
            $transactionId = get_post_meta($order->id, 'Transaction ID', true);

            $paymentMethod = get_post_meta($order->id , '_payment_method', true);
            if($paymentMethod === $this->id && strlen($transactionId) > 0)
            {
				try
				{
                    $paymentTypeId = get_post_meta($order->id, 'Payment Type ID', true);
					$webservice = new EpaySoap($this->remotepassword);
					$transaction = $webservice->gettransaction($this->merchant, $transactionId);

					if($transaction->gettransactionResult === true)
					{
                        echo '<div class="epay-info">';
                        echo    '<div class="epay-transactionid">';
                        echo        '<p>';
                        _e('Transaction ID', 'woocommerce-gateway-epay-dk');
                        echo        '</p>';
                        echo        '<p>'.$transaction->transactionInformation->transactionid.'</p>';
                        echo    '</div>';

                        if(strlen($paymentTypeId) > 0)
                        {
                            echo '<div class="epay-paymenttype">';
                            echo    '<p>';
                            _e('Payment Type', 'woocommerce-gateway-epay-dk');
                            echo    '</p>';
                            echo    '<div class="epay-paymenttype-group">';
                            echo        '<img src="https://d25dqh6gpkyuw6.cloudfront.net/paymentlogos/external/'. intval($paymentTypeId) . '.png" alt="' . $this->getCardNameById(intval($paymentTypeId)) . '" title="' . $this->getCardNameById(intval($paymentTypeId)) . '"/><div>'.$this->getCardNameById(intval($paymentTypeId));
                            if(strlen($transaction->transactionInformation->tcardno) > 0)
                            {
                                echo '<br/>'. $transaction->transactionInformation->tcardno;
                            }
                            echo '</div></div></div>';
                        }

                        $epayhelper = new epayhelper();
                        $currencycode = $transaction->transactionInformation->currency;
                        $currency = $epayhelper->get_iso_code($currencycode, false);
                        $minorUnits = EpayHelper::getCurrencyMinorunits($currency);

                        echo '<div class="epay-info-overview">';
                        echo    '<p>';
                        _e('Authorized amount', 'woocommerce-gateway-epay-dk');
                        echo    ':</p>';
                        echo    '<p>'. EpayHelper::convertPriceFromMinorUnits($transaction->transactionInformation->authamount, $minorUnits, wc_get_price_decimal_separator()). ' ' .$currency .'</p>';
                        echo '</div>';

                        echo '<div class="epay-info-overview">';
                        echo    '<p>';
                        _e('Captured amount', 'woocommerce-gateway-epay-dk');
                        echo    ':</p>';
                        echo    '<p>'.EpayHelper::convertPriceFromMinorUnits($transaction->transactionInformation->capturedamount, $minorUnits, wc_get_price_decimal_separator()). ' ' .$currency .'</p>';
                        echo '</div>';

                        echo '<div class="epay-info-overview">';
                        echo    '<p>';
                        _e('Credited amount', 'woocommerce-gateway-epay-dk');
                        echo    ':</p>';
                        echo    '<p>'.EpayHelper::convertPriceFromMinorUnits($transaction->transactionInformation->creditedamount, $minorUnits, wc_get_price_decimal_separator()). ' ' .$currency .'</p>';
                        echo '</div>';

                        echo '</div>';

						if($transaction->transactionInformation->status == "PAYMENT_NEW")
						{
                            echo '<div class="epay-input-group">';
                            echo '<div class="epay-input-group-currency">' .$currency. '</div><input type="text" value="' . EpayHelper::convertPriceFromMinorUnits(($transaction->transactionInformation->authamount - $transaction->transactionInformation->capturedamount), $minorUnits, wc_get_price_decimal_separator(), "") . '" id="epay_amount" name="epay_amount" />';
                            echo '</div>';
                            echo '<div class="epay-action">';
                            echo '<a class="button capture" onclick="javascript:location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=capture') . '&amount=\' + document.getElementById(\'epay_amount\').value">';
                            _e('Capture', 'woocommerce-gateway-epay-dk');
                            echo '</a>';
                            echo '</div>';
                            if(!$transaction->transactionInformation->capturedamount)
                            {
                                echo '<div class="epay-action">';
                                echo '<a class="button delete"  onclick="javascript: (confirm(\'' . __('Are you sure you want to delete?', 'woocommerce-gateway-epay-dk') . '\') ? (location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=delete') . '\') : (false));">';
							    _e('Delete', 'woocommerce-gateway-epay-dk');
							    echo '</a>';
                                echo '</div>';
                            }

						}
						elseif($transaction->transactionInformation->status == "PAYMENT_CAPTURED" && $transaction->transactionInformation->creditedamount == 0)
						{
                            echo '<div class="epay-input-group">';
                            echo '<div class="epay-input-group-currency">' .$currency. '</div><input type="text" value="' . EpayHelper::convertPriceFromMinorUnits($transaction->transactionInformation->capturedamount, $minorUnits, wc_get_price_decimal_separator(), "") . '" id="epay_credit_amount" name="epay_credit_amount" />';
                            echo '</div>';
                            echo '<div class="epay-action">';
                            echo '<a class="button credit" onclick="javascript: (confirm(\'' . __('Are you sure you want to credit?', 'woocommerce-gateway-epay-dk') . '\') ? (location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=credit') . '&amount=\' + document.getElementById(\'epay_credit_amount\').value) : (false));">';
                            _e('Credit', 'woocommerce-gateway-epay-dk');
                            echo '</a>';
                            echo '</div>';
						}

						$historyArray = $transaction->transactionInformation->history->TransactionHistoryInfo;

						if(!array_key_exists(0, $transaction->transactionInformation->history->TransactionHistoryInfo))
						{
							$historyArray = array($transaction->transactionInformation->history->TransactionHistoryInfo);
						}
                        if(count($historyArray) > 0)
                        {
                            echo '<h4 class="epay-header">';
                            _e('TRANSACTION HISTORY', 'woocommerce-gateway-epay-dk');
                            echo '</h4>';
                            echo '<table class="epay-table">';
                            for($i = 0; $i < count($historyArray); $i++)
                            {
                                echo '<tr class="epay-transaction-date"><td>';
                                echo str_replace("T", " ", $historyArray[$i]->created);
                                echo '</td></tr><tr class="epay-transaction"><td>';
                                if(strlen($historyArray[$i]->username) > 0)
                                    echo ($historyArray[$i]->username . ": ");
                                echo $historyArray[$i]->eventMsg;
                                echo '</td></tr>';
                            }
                            echo '</table>';
                        }
					}
					else
					{
						$orderNote = __('Get Transaction action failed', 'woocommerce-gateway-epay-dk');
                        if($transaction->epayresponse != "-1")
                        {
                            $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $transaction->epayresponse);
                        }

                        echo $this->message("error", $orderNote);
					}
				}
				catch(Exception $e)
				{
					echo $this->message("error", $e->getMessage());
				}
			}
			else
            {
				echo __('No transaction was found.', 'woocommerce-gateway-epay-dk');
            }
		}

		private function message($type, $message) {
			return '<div id="message" class="'.$type.'">
				<p>'.$message.'</p>
			</div>';
		}

        private function getCardNameById($card_id)
        {
            switch($card_id)
            {
                case 1:
                    return 'Dankort / VISA/Dankort';
                case 2:
                    return 'eDankort';
                case 3:
                    return 'VISA / VISA Electron';
                case 4:
                    return 'MasterCard';
                case 6:
                    return 'JCB';
                case 7:
                    return 'Maestro';
                case 8:
                    return 'Diners Club';
                case 9:
                    return 'American Express';
                case 10:
                    return 'ewire';
                case 11:
                    return 'Forbrugsforeningen';
                case 12:
                    return 'Nordea e-betaling';
                case 13:
                    return 'Danske Netbetalinger';
                case 14:
                    return 'PayPal';
                case 16:
                    return 'MobilPenge';
                case 17:
                    return 'Klarna';
                case 18:
                    return 'Svea';
                case 19:
                    return 'SEB';
                case 20:
                    return 'Nordea';
                case 21:
                    return 'Handelsbanken';
                case 22:
                    return 'Swedbank';
                case 23:
                    return 'ViaBill';
                case 24:
                    return 'Beeptify';
                case 25:
                    return 'iDEAL';
                case 26:
                    return 'Gavekort';
                case 27:
                    return 'Paii';
                case 28:
                    return 'Brandts Gavekort';
                case 29:
                    return 'MobilePay Online';
                case 30:
                    return 'Resurs Bank';
                case 31:
                    return 'Ekspres Bank';
                case 32:
                    return 'Swipp';
            }

            return 'Unknown';
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
