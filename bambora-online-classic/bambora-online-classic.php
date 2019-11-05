<?php
/**
 * Plugin Name: Bambora Online ePay
 * Plugin URI: https://www.epay.dk
 * Description: Bambora Online ePay payment gateway for WooCommerce
 * Version: 5.0.4
 * Author: Bambora Online
 * Author URI: https://www.epay.dk/epay-payment-solutions
 * Text Domain: bambora-online-classic
 *
 * @author Bambora
 * @package bambora_online_classic
 */

define( 'BOCLASSIC_PATH', dirname( __FILE__ ) );
define( 'BOCLASSIC_VERSION', '5.0.4' );

add_action( 'plugins_loaded', 'init_bambora_online_classic', 0 );

/**
 * Initilize Bambora Online Classic
 *
 * @return void
 */
function init_bambora_online_classic() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include( BOCLASSIC_PATH . '/lib/bambora-online-classic-soap.php' );
	include( BOCLASSIC_PATH . '/lib/bambora-online-classic-helper.php' );
	include( BOCLASSIC_PATH . '/lib/bambora-online-classic-log.php' );

	/**
     * Gateway class
     **/
	class Bambora_Online_Classic extends WC_Payment_Gateway {
		/**
         * Singleton instance
         *
         * @var Bambora_Online_Classic
         */
		private static $_instance;

		/**
         * @param Bambora_Online_Classic_Log
         */
		private $_boclassic_log;

		/**
         * get_instance
         *
         * Returns a new instance of self, if it does not already exist.
         *
         * @access public
         * @static
         * @return Bambora_Online_Classic
         */
		public static function get_instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
         * Construct
         */
		public function __construct() {
			$this->id = 'epay_dk';
			$this->method_title = 'Bambora Online ePay';
            $this->method_description = 'Bambora Online ePay enables easy and secure payments on your shop';
			$this->icon = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/bambora-logo.svg';
			$this->has_fields = false;

			$this->supports = array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change_customer',
				'multiple_subscriptions'
				);

			// Init the Bambora Online Classic logger
			$this->_boclassic_log = new Bambora_Online_Classic_Log();

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Initilize Bambora Online Classic Settings
			$this->init_bambora_online_classic_settings();

			if ( $this->remoteinterface === 'yes' ) {
				$this->supports = array_merge( $this->supports, array( 'refunds' ) );
			}
		}

		/**
         * Initilize Bambora Online Classic Settings
         */
		public function init_bambora_online_classic_settings() {
			// Define user set variables
			$this->enabled = array_key_exists( 'enabled', $this->settings ) ? $this->settings['enabled'] : 'yes';
			$this->title = array_key_exists( 'title', $this->settings ) ? $this->settings['title'] : 'Bambora Online ePay';
			$this->description = array_key_exists( 'description', $this->settings ) ? $this->settings['description'] : 'Pay using Bambora Online ePay';
			$this->merchant = array_key_exists( 'merchant', $this->settings ) ? $this->settings['merchant'] : '';
			$this->windowid = array_key_exists( 'windowid', $this->settings ) ? $this->settings['windowid'] : '1';
			$this->md5key = array_key_exists( 'md5key', $this->settings ) ? $this->settings['md5key'] : '';
			$this->instantcapture = array_key_exists( 'instantcapture', $this->settings ) ? $this->settings['instantcapture'] : 'no';
			$this->group = array_key_exists( 'group', $this->settings ) ? $this->settings['group'] : '';
			$this->authmail = array_key_exists( 'authmail', $this->settings ) ? $this->settings['authmail'] : '';
			$this->ownreceipt = array_key_exists( 'ownreceipt', $this->settings ) ? $this->settings['ownreceipt'] : 'no';
			$this->remoteinterface = array_key_exists( 'remoteinterface', $this->settings ) ? $this->settings['remoteinterface'] : 'no';
			$this->remotepassword = array_key_exists( 'remotepassword', $this->settings ) ? $this->settings['remotepassword'] : '';
			$this->enableinvoice = array_key_exists( 'enableinvoice', $this->settings ) ? $this->settings['enableinvoice'] : 'no';
			$this->addfeetoorder = array_key_exists( 'addfeetoorder', $this->settings ) ? $this->settings['addfeetoorder'] : 'no';
			$this->enablemobilepaymentwindow = array_key_exists( 'enablemobilepaymentwindow', $this->settings ) ? $this->settings['enablemobilepaymentwindow'] : 'yes';
			$this->roundingmode = array_key_exists( 'roundingmode', $this->settings ) ? $this->settings['roundingmode'] : Bambora_Online_Classic_Helper::ROUND_DEFAULT;
		}

		/**
         * Init hooks
         */
		public function init_hooks() {
			// Actions
			add_action( 'woocommerce_api_' . strtolower( get_class() ), array( $this, 'bambora_online_classic_callback' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

			if ( is_admin() ) {
				if ( $this->remoteinterface == 'yes' ) {
					add_action( 'add_meta_boxes', array( $this, 'bambora_online_classic_meta_boxes' ) );
				}
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'wp_before_admin_bar_render', array( $this, 'bambora_online_classic_actions' ) );
			}
			if ( class_exists( 'WC_Subscriptions_Order' ) ) {
				// Subscriptions
				add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
				add_action( 'woocommerce_subscription_cancelled_' . $this->id, array( $this, 'subscription_cancellation' ) );
			}
			// Register styles!
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wc_bambora_online_classic_admin_styles_and_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wc_bambora_online_classic_front_styles' ) );
		}

		/**
         * Enqueue Admin Styles and Scripts
         */
		public function enqueue_wc_bambora_online_classic_admin_styles_and_scripts() {
			wp_register_style( 'bambora_online_classic_admin_style', plugins_url( 'bambora-online-classic/style/bambora-online-classic-admin.css' ) );
			wp_enqueue_style( 'bambora_online_classic_admin_style' );

			// Fix for load of Jquery time!
			wp_enqueue_script( 'jquery' );

			wp_enqueue_script( 'bambora_online_classic_admin', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/scripts/bambora-online-classic-admin.js' );
		}

		/**
         * Enqueue Frontend Styles and Scripts
         */
		public function enqueue_wc_bambora_online_classic_front_styles() {
			wp_register_style( 'bambora_online_classic_front_style', plugins_url( 'bambora-online-classic/style/bambora-online-classic-front.css' ) );
			wp_enqueue_style( 'bambora_online_classic_front_style' );
		}

		/**
         * Initialise Gateway Settings Form Fields
         */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
								'title' => 'Activate module',
								'type' => 'checkbox',
								'label' => 'Enable Bambora Online ePay as a payment option.',
								'default' => 'yes',
							),
				'title' => array(
								'title' => 'Title',
								'type' => 'text',
								'description' => 'The title of the payment method displayed to the customers.',
								'default' => 'Bambora Online ePay',
							),
				'description' => array(
								'title' => 'Description',
								'type' => 'textarea',
								'description' => 'The description of the payment method displayed to the customers.',
								'default' => 'Pay using Bambora Online ePay',
							),
				'merchant' => array(
								'title' => 'Merchant number',
								'type' => 'text',
								'description' => 'The number identif ying your ePay merchant account.',
								'default' => '',
							),
				'windowid' => array(
								'title' => 'Window ID',
								'type' => 'text',
								'description' => 'The ID of the payment window to use.',
								'default' => '1',
							),
				'md5key' => array(
								'title' => 'MD5 Key',
								'type' => 'text',
								'description' => 'The MD5 key is used to stamp data sent between WooCommerce and Bambora to prevent it from being tampered with. The MD5 key is optional but if used here, must be the same as in the ePay administration.',
								'default' => '',
							),
				'remotepassword' => array(
								'title' => 'Remote password',
								'type' => 'password',
								'description' => 'if a Remote password is set in the ePay administration, then the same password must be entered here',
								'default' => '',
								'custom_attributes' => array( 'autocomplete' => 'new-password' ),// Fix for input field gets populated with saved login info
							),
				'group' => array(
								'title' => 'Group',
								'type' => 'text',
								'description' => 'The group id is used for grouping payments in the ePay Administration',
								'default' => '',
							),
				'authmail' => array(
								'title' => 'Auth Mail',
								'type' => 'text',
								'default' => '',
								'custom_attributes' => array( 'autocomplete' => 'new-password' ),// Fix for input field gets populated with saved login info
							),
			   'instantcapture' => array(
								'title' => 'Instant capture',
								'type' => 'checkbox',
								'description' => 'Capture the payments at the same time they are authorized. In some countries, this is only permitted if the consumer receives the products right away Ex. digital products.',
								'default' => 'no',
							),
				'ownreceipt' => array(
								'title' => 'Own receipt',
								'type' => 'checkbox',
								'description' => 'Immediately redirect your customer back to you shop after the payment completed.',
								'default' => 'no',
							),
				'addfeetoorder' => array(
								'title' => 'Add surcharge to the order',
								'type' => 'checkbox',
								'description' => 'Display surcharge amount on the order as an item',
								'default' => 'no',
							),
				'enableinvoice' => array(
								'title' => 'Invoice data',
								'type' => 'checkbox',
								'description' => 'Enable invoice data',
								'default' => 'no',
							),
				'remoteinterface' => array(
								'title' => 'Remote interface',
								'type' => 'checkbox',
								'description' => 'Use remote interface',
								'default' => 'no',
							),
				'enablemobilepaymentwindow' => array(
								'title' => 'Mobile Payment Window',
								'type' => 'checkbox',
								'description' => 'Enable Mobile Payment Window',
								'default' => 'yes',
							),
				'roundingmode' => array(
								'title' => 'Rounding mode',
								'type' => 'select',
								'description' => 'Please select how you want the rounding of the amount sendt to the payment system',
								'options' => array( Bambora_Online_Classic_Helper::ROUND_DEFAULT => 'Default', Bambora_Online_Classic_Helper::ROUND_UP => 'Always up', Bambora_Online_Classic_Helper::ROUND_DOWN => 'Always down' ),
								'default' => 'normal',
							),
				);
		}

		/**
         * Admin Panel Options
         */
		public function admin_options() {
			$version = BOCLASSIC_VERSION;

			$html = "<h3>Bambora Online ePay v{$version}</h3>";
			$html .= Bambora_Online_Classic_Helper::create_admin_debug_section();
			$html .= '<h3 class="wc-settings-sub-title">Module configuration</h3>';
			$html .= '<table class="form-table">';

			// Generate the HTML For the settings form.!
			$html .= $this->generate_settings_html( array(), false );
			$html .= '</table>';

			echo ent2ncr( $html );
		}

		/**
         * There are no payment fields for epay, but we want to show the description if set.
         **/
		public function payment_fields() {
    		$text_replace = wptexturize( $this->description );
			$paymentFieldDescription = wpautop( $text_replace );
            $paymentLogoes = '<div id="boclassic_card_logos">';
            $merchant_number = $this->merchant;
            if ( $merchant_number ) {
                $paymentLogoes .= '<script type="text/javascript" src="https://relay.ditonlinebetalingssystem.dk/integration/paymentlogos/PaymentLogos.aspx?merchantnumber=' . $merchant_number . '&direction=2&padding=2&rows=2&showdivs=0&logo=0&divid=boclassic_card_logos"></script>';
            }
            $paymentLogoes .= '</div>';
            $paymentFieldDescription .= $paymentLogoes;
			echo $paymentFieldDescription;
		}

		/**
         * Create invoice lines
         *
         * @param WC_Order $order
         * @param int      $minorunits
         * @return string
         * */
		protected function create_invoice( $order, $minorunits ) {
			if ( $this->enableinvoice == 'yes' ) {
				if ( Bambora_Online_Classic_Helper::is_woocommerce_3() ) {
					$invoice['customer']['emailaddress'] = $order->get_billing_email();
					$invoice['customer']['firstname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_billing_first_name() );
					$invoice['customer']['lastname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_billing_last_name() );
					$invoice['customer']['address'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_billing_address_1() );
					$invoice['customer']['zip'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_billing_postcode() );
					$invoice['customer']['city'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_billing_city() );
					$invoice['customer']['country'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_billing_country() );

					$invoice['shippingaddress']['firstname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_shipping_first_name() );
					$invoice['shippingaddress']['lastname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_shipping_last_name() );
					$invoice['shippingaddress']['address'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_shipping_address_1() );
					$invoice['shippingaddress']['zip'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_shipping_postcode() );
					$invoice['shippingaddress']['city'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_shipping_city() );
					$invoice['shippingaddress']['country'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->get_shipping_country() );
				} else {
					$invoice['customer']['emailaddress'] = $order->billing_email;
					$invoice['customer']['firstname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->billing_first_name );
					$invoice['customer']['lastname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->billing_last_name );
					$invoice['customer']['address'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->billing_address_1 );
					$invoice['customer']['zip'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->billing_postcode );
					$invoice['customer']['city'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->billing_city );
					$invoice['customer']['country'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->billing_country );

					$invoice['shippingaddress']['firstname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->shipping_first_name );
					$invoice['shippingaddress']['lastname'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->shipping_last_name );
					$invoice['shippingaddress']['address'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->shipping_address_1 );
					$invoice['shippingaddress']['zip'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->shipping_postcode );
					$invoice['shippingaddress']['city'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->shipping_city );
					$invoice['shippingaddress']['country'] = Bambora_Online_Classic_Helper::json_value_remove_special_characters( $order->shipping_country );
				}
				$invoice['lines'] = $this->create_invoice_order_lines( $order, $minorunits );

				return wp_json_encode( $invoice, JSON_UNESCAPED_UNICODE );
			} else {
				return '';
			}
		}

		/**
         * Create Bambora Online Classic orderlines for invoice
         *
         * @param WC_Order $order
         * @return array
         */
		protected function create_invoice_order_lines( $order, $minorunits ) {
			$items = $order->get_items();
			$invoice_order_lines = array();
			foreach ( $items as $item ) {
				$item_total = $order->get_line_total( $item, false, true );
                if($item['qty'] > 1) {
                    $item_price = $item_total / $item['qty'];
                } else {
                    $item_price = $item_total;
                }
				$item_vat_amount = $order->get_line_tax( $item );
				$invoice_order_lines[] = array(
						'id' => $item['product_id'],
						'description' => Bambora_Online_Classic_Helper::json_value_remove_special_characters( $item['name'] ),
						'quantity' => $item['qty'],
						'price' => Bambora_Online_Classic_Helper::convert_price_to_minorunits( $item_price, $minorunits, $this->roundingmode ),
						'vat' => $item_vat_amount > 0 ? ( $item_vat_amount / $item_total ) * 100 : 0,
					);
			}
			$shipping_methods = $order->get_shipping_methods();
			if ( $shipping_methods && count( $shipping_methods ) !== 0 ) {
				$shipping_total = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_shipping_total() : $order->get_total_shipping();
				$shipping_tax = (float) $order->get_shipping_tax();
				$shipping_method = reset( $shipping_methods );
				$invoice_order_lines[] = array(
						'id' => $shipping_method->get_method_id(),
						'description' => $shipping_method->get_method_title(),
						'quantity' => 1,
						'price' => Bambora_Online_Classic_Helper::convert_price_to_minorunits( $shipping_total, $minorunits, $this->roundingmode ),
						'vat' => $shipping_tax > 0 ? ( $shipping_tax / $shipping_total ) * 100  : 0,
					);
			}

			return $invoice_order_lines;
		}

		/**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return string[]
         */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}

		/**
         * Process Refund
         *
         * @param int        $order_id
         * @param float|null $amount
         * @param string     $reason
         * @return bool
         */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			if ( ! isset( $amount ) ) {
				return true;
			}
			$order = wc_get_order( $order_id );
			$transaction_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_transaction_id( $order );
			$order_currency = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_currency() : $order->get_order_currency;
			$minorunits = Bambora_Online_Classic_Helper::get_currency_minorunits( $order_currency );
			$webservice = new Bambora_Online_Classic_Soap( $this->remotepassword );
			$credit_response = $webservice->refund( $this->merchant, $transaction_id, Bambora_Online_Classic_Helper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode ) );
			if ( ! $credit_response->creditResult ) {
				$error_message = __( 'Refund action failed', 'bambora-online-classic' );
				if ( $credit_response->epayresponse != '-1' ) {
					$error_message .= ' - ' . $webservice->get_epay_error( $this->merchant, $credit_response->epayresponse );
				} elseif ( $credit_response->pbsResponse != '-1' ) {
					$error_message .= ' - ' . $webservice->get_pbs_error( $this->merchant, $credit_response->pbsResponse );
				}
				$this->_boclassic_log->add( $error_message );
				echo Bambora_Online_Classic_Helper::message_to_html( 'error', $error_message );
				return false;
			}

			return true;
		}

		/**
         * Handle scheduled subscription payments
         *
         * @param mixed    $amount_to_charge
         * @param WC_Order $renewal_order
         */
		public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
			$subscription = Bambora_Online_Classic_Helper::get_subscriptions_for_renewal_order( $renewal_order );
			$result = $this->process_subscription_payment( $amount_to_charge, $renewal_order, $subscription );
			$renewal_order_id = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $renewal_order->get_id() : $renewal_order->id;

			// Remove the Bambora Online Classic subscription id copyid from the subscription
			delete_post_meta( $renewal_order_id, Bambora_Online_Classic_Helper::BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID );

			if ( is_wp_error( $result ) ) {
				$message = sprintf( __( 'Bambora Online ePay Subscription could not be authorized for renewal order # %s - %s', 'bambora-online-classic' ), $renewal_order_id, $result->get_error_message( 'bambora_online_classic_error' ) );
				$renewal_order->update_status( 'failed', $message );
				$this->_boclassic_log->add( $message );
			}
		}

		/**
         * Process a subscription renewal
         *
         * @param mixed           $amount
         * @param WC_Order        $renewal_order
         * @param WC_Subscription $subscription
         */
		public function process_subscription_payment( $amount, $renewal_order, $subscription ) {
			try {
				$bambora_subscription_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_subscription_id( $subscription );
				if ( strlen( $bambora_subscription_id ) === 0 ) {
					return new WP_Error( 'bambora_online_classic_error', __( 'Bambora Online ePay Subscription id was not found', 'bambora-online-classic' ) );
				}

				$order_currency = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $renewal_order->get_currency() : $renewal_order->get_order_currency();
				$minorunits = Bambora_Online_Classic_Helper::get_currency_minorunits( $order_currency );
				$amount = Bambora_Online_Classic_Helper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
				$renewal_order_id = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $renewal_order->get_id() : $renewal_order->id;

				$webservice = new Bambora_Online_Classic_Soap( $this->remotepassword, true );
				$authorize_response = $webservice->authorize( $this->merchant, $bambora_subscription_id, $renewal_order_id, $amount, Bambora_Online_Classic_Helper::get_iso_code( $order_currency ), (bool) Bambora_Online_Classic_Helper::yes_no_to_int( $this->instantcapture ), $this->group, $this->authmail );
				if ( $authorize_response->authorizeResult === false ) {
					$error_message = '';
					if ( $authorize_response->epayresponse != '-1' ) {
						$error_message = $webservice->get_epay_error( $this->merchant, $authorize_response->epayresponse );
					} elseif ( $authorize_response->pbsresponse != '-1' ) {
						$error_message = $webservice->get_pbs_error( $this->merchant, $authorize_response->pbsresponse );
					}
					return new WP_Error( 'bambora_online_classic_error', $error_message );
				}
				$renewal_order->payment_complete( $authorize_response->transactionid );

				// Add order note
				$message = sprintf( __( 'Bambora Online ePay Subscription was authorized for renewal order %s with transaction id %s','bambora-online-classic' ), $renewal_order_id, $authorize_response->transactionid );
				$renewal_order->add_order_note( $message );
				$subscription->add_order_note( $message );

				return true;
			}
			catch ( Exception $ex ) {
				return new WP_Error( 'bambora_online_classic_error', $ex->getMessage() );
			}
		}

		/**
         * Cancel a subscription
         *
         * @param WC_Subscription $subscription
         * @param bool            $force_delete
         */
		public function subscription_cancellation( $subscription, $force_delete = false ) {
			if ( 'cancelled' === $subscription->get_status() || $force_delete ) {
				$result = $this->process_subscription_cancellation( $subscription );

				if ( is_wp_error( $result ) ) {
					$message = sprintf( __( 'Bambora Online ePay Subscription could not be canceled - %s', 'bambora-online-classic' ), $result->get_error_message( 'bambora_online_classic_error' ) );
					$subscription->add_order_note( $message );
					$this->_boclassic_log->add( $message );
				}
			}
		}

		/**
         * Process canceling of a subscription
         *
         * @param WC_Subscription $subscription
         */
		protected function process_subscription_cancellation( $subscription ) {
			try {
				if ( Bambora_Online_Classic_Helper::order_is_subscription( $subscription ) ) {
					$bambora_subscription_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_subscription_id( $subscription );
					if ( strlen( $bambora_subscription_id ) === 0 ) {
						$order_note = __( 'Bambora Online ePay Subscription ID was not found', 'bambora-online-classic' );
						return new WP_Error( 'bambora_online_classic_error', $order_note );
					}

					$webservice = new Bambora_Online_Classic_Soap( $this->remotepassword, true );
					$delete_subscription_response = $webservice->delete_subscription( $this->merchant, $bambora_subscription_id );
					if ( $delete_subscription_response->deletesubscriptionResult === true ) {
						$subscription->add_order_note( sprintf( __( 'Subscription successfully Canceled. - Bambora Online ePay Subscription Id: %s', 'bambora-online-classic' ), $bambora_subscription_id ) );
					} else {
						$order_note = sprintf( __( 'Bambora Online ePay Subscription Id: %s', 'bambora-online-classic' ), $bambora_subscription_id );
						if ( $delete_subscription_response->epayresponse != '-1' ) {
							$order_note .= ' - ' . $webservice->get_epay_error( $this->merchant, $delete_subscription_response->epayresponse );
						}
						return new WP_Error( 'bambora_online_classic_error', $order_note );
					}
				}
				return true;
			}
			catch ( Exception $ex ) {
				return new WP_Error( 'bambora_online_classic_error', $ex->getMessage() );
			}
		}

		/**
         * receipt_page
         **/
		public function receipt_page( $order_id ) {
			$order = wc_get_order( $order_id );
			$is_request_to_change_payment_method = Bambora_Online_Classic_Helper::order_is_subscription( $order );

			$order_currency = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_currency() : $order->get_order_currency();
			$order_total = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_total() : $order->order_total;
			$minorunits = Bambora_Online_Classic_Helper::get_currency_minorunits( $order_currency );

			$epay_args = array(
				'encoding' => 'UTF-8',
				'cms' => Bambora_Online_Classic_Helper::get_module_header_info(),
				'windowstate' => "3",
				'mobile' => Bambora_Online_Classic_Helper::yes_no_to_int( $this->enablemobilepaymentwindow ),
				'merchantnumber' => $this->merchant,
				'windowid' => $this->windowid,
				'currency' => $order_currency,
				'amount' => Bambora_Online_Classic_Helper::convert_price_to_minorunits( $order_total, $minorunits, $this->roundingmode ),
				'orderid' => $this->clean_order_number($order->get_order_number()),
                'accepturl' => Bambora_Online_Classic_Helper::get_accept_url( $order ),
				'cancelurl' => Bambora_Online_Classic_Helper::get_decline_url( $order ),
				'callbackurl' => apply_filters( 'bambora_online_classic_callback_url', Bambora_Online_Classic_Helper::get_bambora_online_classic_callback_url( $order_id ) ),
				'mailreceipt' => $this->authmail,
				'instantcapture' => Bambora_Online_Classic_Helper::yes_no_to_int( $this->instantcapture ),
				'group' => $this->group,
				'language' => Bambora_Online_Classic_Helper::get_language_code( get_locale() ),
				'ownreceipt' => Bambora_Online_Classic_Helper::yes_no_to_int( $this->ownreceipt ),
				'timeout' => '60',
			);

			if ( ! $is_request_to_change_payment_method ) {
				$epay_args['invoice'] = $this->create_invoice( $order, $minorunits );
			}

			if ( Bambora_Online_Classic_Helper::woocommerce_subscription_plugin_is_active() && ( Bambora_Online_Classic_Helper::order_contains_subscription( $order ) || $is_request_to_change_payment_method ) ) {
				$epay_args['subscription'] = 1;
			}

			if ( strlen( $this->md5key ) > 0 ) {
				$hash = '';
				foreach ( $epay_args as $value ) {
					$hash .= $value;
				}
				$epay_args['hash'] = md5( $hash . $this->md5key );
			}

			$epay_args_json = wp_json_encode( $epay_args );
			$payment_html = Bambora_Online_Classic_Helper::create_bambora_online_classic_payment_html( $epay_args_json );

			echo ent2ncr( $payment_html );
		}

        /**
         * Removes any special charactors from the order number
         *
         * @param string $order_number
         * @return string
         */
        protected function clean_order_number($order_number) {
            return preg_replace('/[^a-z\d ]/i', "", $order_number );
        }

		/**
         * Check for epay IPN Response
         **/
		public function bambora_online_classic_callback() {
			$params = stripslashes_deep( $_GET );
			$message = '';
			$order = null;
			$response_code = 400;
			try {
				$is_valid_call = Bambora_Online_Classic_Helper::validate_bambora_online_classic_callback_params( $params, $this->md5key, $order, $message );
				if ( $is_valid_call ) {
					$message = $this->process_bambora_online_classic_callback( $order, $params );
					$response_code = 200;
				} else {
					if ( ! empty( $order ) ) {
						$order->update_status( 'failed', $message );
					}
					$this->_boclassic_log->separator();
					$this->_boclassic_log->add( "Callback failed - {$message} - GET params:" );
					$this->_boclassic_log->add( $params );
					$this->_boclassic_log->separator();
				}
			}
			catch (Exception $ex) {
				$message = 'Callback failed Reason: ' . $ex->getMessage();
				$response_code = 500;
				$this->_boclassic_log->separator();
				$this->_boclassic_log->add( "Callback failed - {$message} - GET params:" );
				$this->_boclassic_log->add( $params );
				$this->_boclassic_log->separator();
			}

			$header = 'X-EPay-System: ' . Bambora_Online_Classic_Helper::get_module_header_info();
			header( $header, true, $response_code );
			die( $message );

		}

		/**
         * Process the Bambora Callback
         *
         * @param WC_Order $order
         * @param mixed    $bambora_transaction
         */
		protected function process_bambora_online_classic_callback( $order, $params ) {
			try {
				$type = '';
				$bambora_subscription_id = array_key_exists( 'subscriptionid', $params ) ? $params['subscriptionid'] : null;
				if ( ( Bambora_Online_Classic_Helper::order_contains_subscription( $order ) || Bambora_Online_Classic_Helper::order_is_subscription( $order ) ) && isset( $bambora_subscription_id ) ) {
					$action = $this->process_subscription( $order, $params );
					$type = "Subscription {$action}";
				} else {
					$action = $this->process_standard_payments( $order, $params );
					$type = "Standard Payment {$action}";
				}
			}
			catch ( Exception $e ) {
				throw $e;
			}

			return  "Bambora Online ePay Callback completed - {$type}";
		}

		/**
         * Process standard payments
         *
         * @param WC_Order $order
         * @param array    $params
         * @return string
         */
		protected function process_standard_payments( $order, $params ) {
			$action = '';
			$old_transaction_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_transaction_id( $order );
			if ( empty( $old_transaction_id ) ) {
				$this->add_surcharge_fee_to_order( $order, $params );
				$order->add_order_note( sprintf( __( 'Bambora Online ePay Payment completed with transaction id %s', 'bambora-online-classic' ), $params['txnid'] ) );
				$this->add_or_update_payment_type_id_to_order( $order, $params['paymenttype'] );
                $action = 'created';
			} else {
				$action = 'created (Called multiple times)';
			}
			$order->payment_complete( $params['txnid'] );
			return $action;
		}

		/**
         * Process the subscription
         *
         * @param WC_Order|WC_Subscription $order
         * @param array                    $params
         * @return string
         */
		protected function process_subscription( $order, $params ) {
			$action = '';
			$bambora_subscription_id = $params['subscriptionid'];
			if ( Bambora_Online_Classic_Helper::order_is_subscription( $order ) ) {
				// Do not cancel subscription if the callback is called more than once !
				$old_bambora_subscription_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_subscription_id( $order );
				if ( $bambora_subscription_id != $old_bambora_subscription_id ) {
					$this->subscription_cancellation( $order, true );
					$action = 'changed';
					$order->add_order_note( sprintf( __( 'Bambora Online ePay Subscription changed from: %s to: %s', 'bambora-online-classic' ), $old_bambora_subscription_id, $bambora_subscription_id ) );
					$order->payment_complete();
					$this->save_subscription_meta( $order, $bambora_subscription_id, true );
                } else {
					$action = 'changed (Called multiple times)';
				}
			} else {
				// Do not add surcharge if the callback is called more than once!
				$old_transaction_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_transaction_id( $order );
				$bambora_transaction_id = $params['txnid'];
				if ( $bambora_transaction_id != $old_transaction_id ) {
					$this->add_surcharge_fee_to_order( $order, $params );
					$action = 'activated';
					$order->add_order_note( sprintf( __( 'Bambora Online ePay Subscription activated with subscription id: %s', 'bambora-online-classic' ), $bambora_subscription_id ) );
					$order->payment_complete( $bambora_transaction_id );
					$this->save_subscription_meta( $order, $bambora_subscription_id, false );
                    $this->add_or_update_payment_type_id_to_order( $order, $params['paymenttype'] );
					do_action( 'processed_subscription_payments_for_order', $order );
				} else {
					$action = 'activated (Called multiple times)';
				}
			}

			return $action;
		}

		/**
         * Add surcharge to order
         *
         * @param WC_Order $order
         * @param array    $params
         */
		protected function add_surcharge_fee_to_order( $order, $params ) {
			$order_currency = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_currency() : $order->get_order_currency;
			$minorunits = Bambora_Online_Classic_Helper::get_currency_minorunits( $order_currency );
			$fee_amount_in_minorunits = $params['txnfee'];
			if ( $fee_amount_in_minorunits > 0 && $this->addfeetoorder === 'yes' ) {
				$fee_amount = Bambora_Online_Classic_Helper::convert_price_from_minorunits( $fee_amount_in_minorunits, $minorunits );
				$fee = (object) array(
					'name'          => __( 'Surcharge Fee', 'bambora-online-classic' ),
					'amount'        => $fee_amount,
					'taxable'       => false,
					'tax_class'     => null,
					'tax_data'      => array(),
					'tax'           => 0,
					);
				if ( ! Bambora_Online_Classic_Helper::is_woocommerce_3() ) {
					$order->add_fee( $fee );
				} else {
					$fee_item = new WC_Order_Item_Fee();
					$fee_item->set_props( array(
						'name' => $fee->name,
						'tax_class' => $fee->tax_class,
						'total' => $fee->amount,
						'total_tax' => $fee->tax,
						'order_id' => $order->get_id(),
						)
					);
					$fee_item->save();
					$order->add_item( $fee_item );
				}

				$total_incl_fee = ( Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_total() : $order->order_total ) + $fee_amount;
				$order->set_total( $total_incl_fee );
			}
		}

        /**
         * Add Payment Type id Meta to the order
         * @param WC_Order $order
         * @param mixed $payment_type_id
         * @return void
         */
        protected function add_or_update_payment_type_id_to_order( $order, $payment_type_id) {
            $order_id = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_id() : $order->id;
            $existing_payment_type_id = get_post_meta($order_id, Bambora_Online_Classic_Helper::BAMBORA_ONLINE_CLASSIC_PAYMENT_TYPE_ID, true);

            if(!isset($existing_payment_type_id) || $existing_payment_type_id !== $payment_type_id) {
                update_post_meta( $order_id, Bambora_Online_Classic_Helper::BAMBORA_ONLINE_CLASSIC_PAYMENT_TYPE_ID, $payment_type_id );
            }
        }

		/**
         * Store the Bambora Online Classic subscription id on subscriptions in the order.
         *
         * @param WC_Order $order_id
         * @param string   $bambora_subscription_id
         * @param bool     $is_subscription
         */
		protected function save_subscription_meta( $order, $bambora_subscription_id, $is_subscription ) {
			$bambora_subscription_id = wc_clean( $bambora_subscription_id );
			$order_id = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $order->get_id() : $order->id;
			if ( $is_subscription ) {
				update_post_meta( $order_id, Bambora_Online_Classic_Helper::BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID, $bambora_subscription_id );
			} else {
				// Also store it on the subscriptions being purchased in the order
				$subscriptions = Bambora_Online_Classic_Helper::get_subscriptions_for_order( $order_id );
				foreach ( $subscriptions as $subscription ) {
					$wc_subscription_id = Bambora_Online_Classic_Helper::is_woocommerce_3() ? $subscription->get_id() : $subscription->id;
					update_post_meta( $wc_subscription_id, Bambora_Online_Classic_Helper::BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID, $bambora_subscription_id );
					$subscription->add_order_note( sprintf( __( 'Bambora Online ePay Subscription activated with subscription id: %s by order %s', 'bambora-online-classic' ), $bambora_subscription_id, $order_id ) );
				}
			}
		}

		/**
         * Handle Bambora Online Classic Actions
         */
		public function bambora_online_classic_actions() {
			if ( isset( $_GET['boclassicaction'] ) ) {
				$params = $_GET;
				$result = $this->process_bambora_online_classic_action( $params );

				if ( is_wp_error( $result ) ) {
					$order_id = isset( $params['post'] ) ?  $params['post'] : '-1';
					$message = sprintf( __( 'Bambora Online ePay Action failed for order %s - %s', 'bambora-online-classic' ), $order_id, $result->get_error_message( 'bambora_online_classic_error' ) );
					$this->_boclassic_log->add( $message );
					echo Bambora_Online_Classic_Helper::message_to_html( 'error', $message );
				} else {
					global $post;
					$url = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );
					wp_safe_redirect( $url );
				}
			}
		}

		/**
         * Validate Action params
         *
         * @param array  $get_params
         * @param string $failed_message
         * @return bool
         */
		protected function validate_bambora_online_classic_action( $get_params, &$failed_message ) {
			$required_params = array(
				'boclassicaction',
				'post',
				'currency',
				'amount',
			);
			foreach ( $required_params as $required_param ) {
				if ( ! array_key_exists( $required_param, $get_params ) || empty( $get_params[ $required_param ] ) ) {
					$failed_message = $required_param;
					return false;
				}
			}
			return true;
		}

		/**
         * Process the action
         *
         * @param array $params
         * @return bool|WP_Error
         */
		protected function process_bambora_online_classic_action( $params ) {
			$failed_message = '';
			if ( ! $this->validate_bambora_online_classic_action( $params, $failed_message ) ) {
				return new WP_Error( 'bambora_online_classic_error', sprintf( __( 'The following get parameter was not provided "%s"' ), $failed_message ) );
			}

			try {
				$order_id = $params['post'];
				$currency = $params['currency'];
				$action = $params['boclassicaction'];
				$amount = $params['amount'];
				$order = wc_get_order( $order_id );

				$minorunits = Bambora_Online_Classic_Helper::get_currency_minorunits( $currency );
				$transaction_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_transaction_id( $order );
				$webservice = new Bambora_Online_Classic_Soap( $this->remotepassword );

				switch ( $action ) {
					case 'capture':
						$amount = str_replace( ',', '.', $amount );
						$amount_in_minorunits = Bambora_Online_Classic_Helper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
						$capture_response = $webservice->capture( $this->merchant, $transaction_id, $amount_in_minorunits );
						if ( $capture_response->captureResult === false ) {
							$message = __( 'Capture action failed', 'bambora-online-classic' );
							if ( $capture_response->epayresponse != '-1' ) {
								$message .= ' - ' . $webservice->get_epay_error( $this->merchant, $capture_response->epayresponse );
							} elseif ( $capture_response->pbsResponse != '-1' ) {
								$message .= ' - ' . $webservice->get_pbs_error( $this->merchant, $capture_response->pbsResponse );
							}
							return new WP_Error( 'bambora_online_classic_error', $message );
						} else {
							do_action( 'bambora_online_classic_after_capture', $order_id );
						}
						break;
					case 'refund':
						$amount = str_replace( ',', '.', $amount );
						$amount_in_minorunits = Bambora_Online_Classic_Helper::convert_price_to_minorunits( $amount, $minorunits, $this->roundingmode );
						$refund_response = $webservice->refund( $this->merchant, $transaction_id, $amount_in_minorunits );
						if ( $refund_response->creditResult === false ) {
							$message = __( 'Refund action failed', 'bambora-online-classic' );
							if ( $refund_response->epayresponse != '-1' ) {
								$message .= ' - ' . $webservice->get_epay_error( $this->merchant, $refund_response->epayresponse );
							} elseif ( $refund_response->pbsResponse != '-1' ) {
								$message .= ' - ' . $webservice->get_pbs_error( $this->merchant, $refund_response->pbsResponse );
							}
							return new WP_Error( 'bambora_online_classic_error', $message );
						}  else {
							do_action( 'bambora_online_classic_after_refund', $order_id );
						}
						break;
					case 'delete':
						$delete_response = $webservice->delete( $this->merchant, $transaction_id );
						if ( $delete_response->deleteResult === false ) {
							$message = __( 'Delete action failed', 'bambora-online-classic' );
							if ( $delete_response->epayresponse != '-1' ) {
								$message .= ' - ' . $webservice->get_epay_error( $this->merchant, $delete_response->epayresponse );
							}
							return new WP_Error( 'bambora_online_classic_error', $message );
						} else {
							do_action( 'bambora_online_classic_after_delete', $order_id );
						}
						break;
				}
			}
			catch (Exception $ex) {
				return new WP_Error( 'bambora_online_classic_error', $ex->getMessage() );
			}
			return true;
		}

		/**
         * Add Bambora Online Classic Meta boxes
         */
		public function bambora_online_classic_meta_boxes() {
			global $post;
			$order_id = $post->ID;
			$payment_method = get_post_meta( $order_id, '_payment_method', true );
			if ( $this->id === $payment_method ) {
				add_meta_box(
					'epay-payment-actions',
					'Bambora Online ePay',
					array( &$this, 'bambora_online_classic_meta_box_payment' ),
					'shop_order',
					'side',
					'high'
				);
			}
		}

		/**
         * Create the Bambora Online Classic Meta Box
         */
		public function bambora_online_classic_meta_box_payment() {
			global $post;
			$html = '';
			try {
				$order_id = $post->ID;
				$order = wc_get_order( $order_id );
				if ( ! empty( $order ) ) {
					$transaction_id = Bambora_Online_Classic_Helper::get_bambora_online_classic_transaction_id( $order );
					if ( strlen( $transaction_id ) > 0 ) {
						$html = $this->bambora_online_classic_meta_box_payment_html( $order, $transaction_id );
					} else {
						$html = sprintf( __( 'No transaction was found for order %s', 'bambora-online-classic' ), $order_id );
						$this->_boclassic_log->add( $html );
					}
				} else {
					$html = sprintf( __( 'The order with id %s could not be loaded', 'bambora-online-classic' ), $order_id );
					$this->_boclassic_log->add( $html );
				}
			}
			catch ( Exception $ex ) {
				$html = $ex->getMessage();
				$this->_boclassic_log->add( $html );
			}
			echo ent2ncr( $html );
		}

		/**
         * Create the HTML for the Bambora Online Classic Meta box payment field
         *
         * @param WC_Order $order
         * @param string   $transaction_id
         * @return string
         */
		protected function bambora_online_classic_meta_box_payment_html( $order, $transaction_id ) {
			try {
				$html = '';
				$webservice = new Bambora_Online_Classic_Soap( $this->remotepassword );
				$get_transaction_response = $webservice->get_transaction( $this->merchant, $transaction_id );
				if ( $get_transaction_response->gettransactionResult === false ) {
					$html = __( 'Get Transaction action failed', 'bambora-online-classic' );
					if ( $get_transaction_response->epayresponse != '-1' ) {
						$html .= ' - ' . $webservice->get_epay_error( $this->merchant, $get_transaction_response->epayresponse );
					}
					return $html;
				}
				$transaction = $get_transaction_response->transactionInformation;
				$currency_code = $transaction->currency;
				$currency = Bambora_Online_Classic_Helper::get_iso_code( $currency_code, false );
				$minorunits = Bambora_Online_Classic_Helper::get_currency_minorunits( $currency );

				$total_authorized = Bambora_Online_Classic_Helper::convert_price_from_minorunits( $transaction->authamount, $minorunits );
				$total_captured = Bambora_Online_Classic_Helper::convert_price_from_minorunits( $transaction->capturedamount, $minorunits );
				$total_credited = Bambora_Online_Classic_Helper::convert_price_from_minorunits( $transaction->creditedamount, $minorunits );
				$available_for_capture = $total_authorized - $total_captured;
				$transaction_status = $transaction->status;

				$card_info = Bambora_Online_Classic_Helper::get_cardtype_groupid_and_name($transaction->cardtypeid);
				$card_group_id = $card_info[1];
				$card_name = $card_info[0];

                if(isset($card_group_id) && $card_group_id != '-1') {
                    $this->add_or_update_payment_type_id_to_order( $order, $card_group_id );
                }

				$html = '<div class="boclassic-info">';
				if(isset($card_group_id) && $card_group_id != '-1') {
					$html .= '<img class="boclassic-paymenttype-img" src="https://d25dqh6gpkyuw6.cloudfront.net/paymentlogos/external/' . $card_group_id . '.png" alt="' . $card_name . '" title="' . $card_name . '" />';
				}
				$html .= '<div class="boclassic-transactionid">';
				$html .= '<p>' . __( 'Transaction ID', 'bambora-online-classic' ) . '</p>';
				$html .= '<p>' . $transaction->transactionid . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-paymenttype">';
				$html .= '<p>' . __( 'Payment Type', 'bambora-online-classic' ) . '</p>';
				$html .= '<p>' . $card_name . '</p>';
				$html .= '</div>';

				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Authorized:', 'bambora-online-classic' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_authorized ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Captured:', 'bambora-online-classic' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_captured ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '<div class="boclassic-info-overview">';
				$html .= '<p>' . __( 'Refunded:', 'bambora-online-classic' ) . '</p>';
				$html .= '<p>' . wc_format_localized_price( $total_credited ) . ' ' . $currency . '</p>';
				$html .= '</div>';
				$html .= '</div>';

				if ( $transaction_status === 'PAYMENT_NEW' || ( $transaction_status === 'PAYMENT_CAPTURED' && $total_credited === 0 ) ) {
					$html .= '<div class="boclassic-action-container">';
					$html .= '<input type="hidden" id="boclassic-currency" name="boclassic-currency" value="' . $currency . '">';
					if ( $transaction_status === 'PAYMENT_NEW' ) {
						$html .= '<input type="hidden" id="boclassic-capture-message" name="boclassic-capture-message" value="' . __( 'Are you sure you want to capture the payment?', 'bambora-online-classic' ) . '" />';
						$html .= '<div class="boclassic-action">';
						$html .= '<p>' . $currency . '</p>';
						$html .= '<input type="text" value="' . $available_for_capture . '" id="boclassic-capture-amount" class="boclassic-amount" name="boclassic-amount" />';
						$html .= '<input id="boclassic-capture-submit" class="button capture" name="boclassic-capture" type="submit" value="' . __( 'Capture', 'bambora-online-classic' ) . '" />';
						$html .= '</div>';
						$html .= '<br />';
						if ( $total_captured === 0 ) {
							$html .= '<input type="hidden" id="boclassic-delete-message" name="boclassic-delete-message" value="' . __( 'Are you sure you want to delete the payment?', 'bambora-online-classic' ) . '" />';
							$html .= '<div class="boclassic-action">';
							$html .= '<input id="boclassic-delete-submit" class="button delete" name="boclassic-delete" type="submit" value="' . __( 'Delete', 'bambora-online-classic' ) . '" />';
							$html .= '</div>';
						}
					} elseif ( $transaction_status === 'PAYMENT_CAPTURED' && $total_credited === 0 ) {
						$html .= '<input type="hidden" id="boclassic-refund-message" name="boclassic-refund-message" value="' . __( 'Are you sure you want to refund the payment?', 'bambora-online-classic' ) . '" />';
						$html .= '<div class="boclassic-action">';
						$html .= '<p>' . $currency . '</p>';
						$html .= '<input type="text" value="' . $total_captured . '" id="boclassic-refund-amount" class="boclassic-amount" name="boclassic-amount" />';
						$html .= '<input id="boclassic-refund-submit" class="button refund" name="boclassic-refund" type="submit" value="' . __( 'Refund', 'bambora-online-classic' ) . '" />';
						$html .= '</div>';
						$html .= '<br />';
					}
					$html .= '</div>';
					$warning_message = __( 'The amount you entered was in the wrong format.', 'bambora-online-classic' );

					$html .= '<div id="boclassic-format-error" class="boclassic boclassic-error"><strong>' . __( 'Warning', 'bambora-online-classic' ) . ' </strong>' . $warning_message . '<br /><strong>' . __( 'Correct format is: 1234.56', 'bambora-online-classic' ) . '</strong></div>';

				}

				$history_array = $transaction->history->TransactionHistoryInfo;

				if ( ! array_key_exists( 0, $transaction->history->TransactionHistoryInfo ) ) {
					$history_array = array( $transaction->history->TransactionHistoryInfo );
				}

				// Sort the history array based on when the history event is created
				$histrory_created = array();
				foreach ( $history_array as $history ) {
					$histrory_created[] = $history->created;
				}
				array_multisort( $histrory_created, SORT_ASC, $history_array );

				if ( count( $history_array ) > 0 ) {
					$html .= '<h4>' . __( 'TRANSACTION HISTORY', 'bambora-online-classic' ) . '</h4>';
					$html .= '<table class="boclassic-table">';

					foreach ( $history_array as $history ) {
						$html .= '<tr class="boclassic-transaction-row-header">';
						$html .= '<td>' . Bambora_Online_Classic_Helper::format_date_time( $history->created ) . '</td>';
						$html .= '</tr>';
						if ( strlen( $history->username ) > 0 ) {
							$html .= '<tr class="boclassic-transaction-row-header boclassic-transaction-row-header-user">';
							$html .= '<td>' . sprintf( __( 'By: %s', 'bambora-online-classic' ), $history->username ) . '</td>';
							$html .= '</tr>';
						}
						$html .= '<tr class="boclassic-transaction">';
						$html .= '<td>' . $history->eventMsg . '</td>';
						$html .= '</tr>';
					}
					$html .= '</table>';
				}

				return $html;
			}
			catch ( Exception $ex ) {
				throw $ex;
			}
		}


		/**
         * Get the bambora online checkout logger
         *
         * @return Bambora_Online_Classic_Log
         */
		public function get_boclassic_logger() {
			return $this->_boclassic_log;
		}

		/**
         * Returns a plugin URL path
         *
         * @param string $path
         * @return string
         */
		public function plugin_url( $path ) {
			return plugins_url( $path, __FILE__ );
		}
	}

	add_filter( 'woocommerce_payment_gateways', 'add_bambora_online_classic_woocommerce' );
	Bambora_Online_Classic::get_instance()->init_hooks();

	/**
     * Add the Gateway to WooCommerce
     **/
	function add_bambora_online_classic_woocommerce( $methods ) {
		$methods[] = 'Bambora_Online_Classic';
		return $methods;
	}

	$plugin_dir = basename( dirname( __FILE__ ) );
	load_plugin_textdomain( 'bambora-online-classic', false, $plugin_dir . '/languages' );
}
