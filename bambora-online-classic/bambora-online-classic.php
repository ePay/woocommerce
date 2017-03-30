<?php
/*
Plugin Name: Bambora Online ePay
Plugin URI: http://www.epay.dk
Description: A payment gateway for ePay payment solutions
Version: 3.0.2
Author: ePay (a Bambora company)
Author URI: http://www.epay.dk/epay-payment-solutions
Text Domain: bambora-online-classic
 */

add_action( 'plugins_loaded', 'init_bambora_online_classic' );

function init_bambora_online_classic() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	define( 'EPAY_LIB', dirname( __FILE__ ) . '/lib/' );

	include( EPAY_LIB . 'epay-soap.php' );
	include( EPAY_LIB . 'epay-helper.php' );

	/**
     * Gateway class
     **/
	class Bambora_Online_Classic extends WC_Payment_Gateway {

		const MODULE_VERSION = '3.0.2';

		public static $_instance;
		/**
         * get_instance
         *
         * Returns a new instance of self, if it does not already exist.
         *
         * @access public
         * @static
         * @return object Bambora_Online_Classic
         */
		public static function get_instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			$this->id = 'epay_dk';
			$this->method_title = 'Bambora Online ePay';
			$this->icon = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/bambora-logo.png';
			$this->has_fields = false;

			$this->supports = array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_reactivation',
				'subscription_suspension',
				'subscription_amount_changes',
				'subscription_date_changes',
				'multiple_subscriptions',
				);

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->enabled = array_key_exists( 'enabled', $this->settings ) ? $this->settings['enabled'] : 'yes';
			$this->title = array_key_exists( 'title', $this->settings ) ? $this->settings['title'] : 'Bambora Online ePay';
			$this->description = array_key_exists( 'description', $this->settings ) ? $this->settings['description'] : 'Pay using Bambora Online ePay';
			$this->merchant = array_key_exists( 'merchant', $this->settings ) ? $this->settings['merchant'] : '';
			$this->windowid = array_key_exists( 'windowid', $this->settings ) ? $this->settings['windowid'] : '1';
			$this->windowstate = array_key_exists( 'windowstate', $this->settings ) ? $this->settings['windowstate'] : 1;
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

			$this->set_epay_description_for_checkout( $this->merchant );

			if ( $this->yesnotoint( $this->remoteinterface ) ) {
				$this->supports = array_merge( $this->supports, array( 'refunds' ) );
			}
		}

		function init_hooks() {
			// Actions
			add_action( 'valid-epay-callback', array( $this, 'successful_request' ) );
			add_action( 'woocommerce_api_' . strtolower( get_class() ), array( $this, 'check_callback' ) );
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'woocommerce_subscription_cancelled_' . $this->id, array( $this, 'subscription_cancellation' ) );
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );

			if ( is_admin() ) {
				if ( $this->remoteinterface == 'yes' ) {
					add_action( 'add_meta_boxes', array( $this, 'epay_meta_boxes' ) );
				}

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'wp_before_admin_bar_render', array( $this, 'epay_action' ) );
			}

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bambora_online_classic_style' ) );
		}

		/**
         * Enqueue Styles
         */
		public function enqueue_bambora_online_classic_style() {
			wp_register_style( 'epay_style', WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/style/epay.css' );
			wp_enqueue_style( 'epay_style' );
		}

		/**
         * Initialise Gateway Settings Form Fields
         */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
								'title' => 'Enable/Disable',
								'type' => 'checkbox',
								'label' => 'Enable ePay',
								'default' => 'yes',
							),
				'title' => array(
								'title' => 'Title',
								'type' => 'text',
								'description' => 'This controls the title which the user sees during checkout.',
								'default' => 'ePay Payment Solutions',
							),
				'description' => array(
								'title' => 'Description',
								'type' => 'textarea',
								'description' => 'This controls the description which the user sees during checkout.',
								'default' => 'Pay using ePay Payment Solutions',
							),
				'merchant' => array(
								'title' => 'Merchant number',
								'type' => 'text',
								'default' => '',
							),
				'windowid' => array(
								'title' => 'Window ID',
								'type' => 'text',
								'default' => '1',
							),
				'windowstate' => array(
								'title' => 'Window state',
								'type' => 'select',
								'options' => array( 1 => 'Overlay', 3 => 'Full screen' ),
								'label' => 'How to open the ePay Payment Window',
								'default' => 1,
							),
				'md5key' => array(
								'title' => 'MD5 Key',
								'type' => 'text',
								'label' => 'Your md5 key',
								'default' => '',
							),
				'instantcapture' => array(
								'title' => 'Instant capture',
								'type' => 'checkbox',
								'label' => 'Enable instant capture',
								'default' => 'no',
							),
				'group' => array(
								'title' => 'Group',
								'type' => 'text',
								'default' => '',
							),
				'authmail' => array(
								'title' => 'Auth Mail',
								'type' => 'text',
								'default' => '',
								'custom_attributes' => array( 'autocomplete' => 'new-password' ),// Fix for input field gets populated with saved login info
							),
				'ownreceipt' => array(
								'title' => 'Own receipt',
								'type' => 'checkbox',
								'label' => 'Enable own receipt',
								'default' => 'no',
							),
				'addfeetoorder' => array(
								'title' => 'Add fee to order',
								'type' => 'checkbox',
								'label' => 'Add transaction fee to the order',
								'default' => 'no',
							),
				'enableinvoice' => array(
								'title' => 'Invoice data',
								'type' => 'checkbox',
								'label' => 'Enable invoice data',
								'default' => 'no',
							),
				'remoteinterface' => array(
								'title' => 'Remote interface',
								'type' => 'checkbox',
								'label' => 'Use remote interface',
								'default' => 'no',
							),
				'remotepassword' => array(
								'title' => 'Remote password',
								'type' => 'password',
								'label' => 'Remote password',
								'default' => '',
								'custom_attributes' => array( 'autocomplete' => 'new-password' ),// Fix for input field gets populated with saved login info
							),
				'enablemobilepaymentwindow' => array(
								'title' => 'Mobile Payment Window',
								'type' => 'checkbox',
								'label' => 'Enable Mobile Payment Window',
								'default' => 'yes',
							),
				);

		}

		/**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @since 1.0.0
         */
		public function admin_options() {
			$plugin_data = get_plugin_data( __FILE__, false, false );
			$version = $plugin_data['Version'];

			echo '<h3>' . 'Bambora Online ePay' . ' v' . $version . '</h3>';
			echo '<a href="http://woocommerce.wpguiden.dk/en/configuration#709" target="_blank">' . __( 'Documentation can be found here', 'bambora-online-classic' ) . '</a>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		}

		/**
         * There are no payment fields for epay, but we want to show the description if set.
         **/
		function payment_fields() {
			if ( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		}

		/**
         * Set the WC Payment Gateway description for the checkout page
         */
		function set_epay_description_for_checkout( $merchantnumber ) {
			global $woocommerce;
			$cart = $woocommerce->cart;
			if ( ! $cart || ! $merchantnumber ) {
				return;
			}

			$this->description .= '<span id="epay_card_logos"></span><script type="text/javascript" src="https://relay.ditonlinebetalingssystem.dk/integration/paymentlogos/PaymentLogos.aspx?merchantnumber=' . $merchantnumber . '&direction=2&padding=2&rows=1&logo=0&showdivs=0&cardwidth=45&divid=epay_card_logos"></script>';
		}

		function fix_url( $url ) {
			$url = str_replace( '&#038;', '&amp;', $url );
			$url = str_replace( '&amp;', '&', $url );

			return $url;
		}

		function yesnotoint( $str ) {
			return $str === 'yes' ? 1 : 0;
		}

		/**
         * Generate the epay button link
         **/
		public function generate_epay_form( $order_id ) {
			$order = new WC_Order( $order_id );
			$minorUnits = Epay_Helper::get_currency_minorunits( $order->get_order_currency() );
			$epay_args = array(
				'encoding' => 'UTF-8',
				'cms' => $this->get_module_header_info(),
				'windowstate' => $this->windowstate,
				'mobile' => $this->enablemobilepaymentwindow === 'yes' ? 1 : 0,
				'merchantnumber' => $this->merchant,
				'windowid' => $this->windowid,
				'currency' => $order->get_order_currency(),
				'amount' => Epay_Helper::convert_price_to_minorunits( $order->get_total(), $minorUnits ),
				'orderid' => str_replace( _x( '#', 'hash before order number', 'woocommerce' ), '', $order->get_order_number() ),
				'accepturl' => $this->fix_url( $this->get_return_url( $order ) ),
				'cancelurl' => $this->fix_url( $order->get_cancel_order_url() ),
				'callbackurl' => $this->fix_url( add_query_arg( 'wooorderid', $order_id, add_query_arg( 'wc-api', 'Bambora_Online_Classic', $this->get_return_url( $order ) ) ) ),
				'mailreceipt' => $this->authmail,
				'instantcapture' => $this->yesnotoint( $this->instantcapture ),
				'group' => $this->group,
				'language' => Epay_Helper::get_language_code( get_locale() ),
				'ownreceipt' => $this->yesnotoint( $this->ownreceipt ),
				'timeout' => '60',
				'invoice' => $this->create_invoice( $order, $minorUnits ),
			);

			if ( $this->woocommerce_subscription_plugin_is_active() && wcs_order_contains_subscription( $order ) ) {
				$epay_args['subscription'] = 1;
			}

			if ( strlen( $this->md5key ) > 0 ) {
				$hash = '';
				foreach ( $epay_args as $value ) {
					$hash .= $value;
				}
				$epay_args['hash'] = md5( $hash . $this->md5key );
			}

			$epay_args_array = array();
			foreach ( $epay_args as $key => $value ) {
				$epay_args_array[] = "'" . esc_attr( $key ) . "':'" . $value . "'";
			}

			$payment_script = '<script type="text/javascript">
			function PaymentWindowReady() {
				paymentwindow = new PaymentWindow({
					' . implode( ',', $epay_args_array ) . '
				});
				paymentwindow.open();
			}
			</script>
			<script type="text/javascript" src="https://ssl.ditonlinebetalingssystem.dk/integration/ewindow/paymentwindow.js" charset="UTF-8"></script>
			<a class="button" onclick="javascript: paymentwindow.open();" id="submit_epay_payment_form" />' . __( 'Pay via ePay', 'bambora-online-classic' ) . '</a>
			<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'bambora-online-classic' ) . '</a>';

			return $payment_script;
		}

		private function create_invoice( $order, $minorunits ) {
			if ( $this->enableinvoice == 'yes' ) {
				$invoice['customer']['emailaddress'] = $order->billing_email;
				$invoice['customer']['firstname'] = $this->json_value_remove_special_characters( $order->billing_first_name );
				$invoice['customer']['lastname'] = $this->json_value_remove_special_characters( $order->billing_last_name );
				$invoice['customer']['address'] = $this->json_value_remove_special_characters( $order->billing_address_1 );
				$invoice['customer']['zip'] = $this->json_value_remove_special_characters( $order->billing_postcode );
				$invoice['customer']['city'] = $this->json_value_remove_special_characters( $order->billing_city );
				$invoice['customer']['country'] = $this->json_value_remove_special_characters( $order->billing_country );

				$invoice['shippingaddress']['firstname'] = $this->json_value_remove_special_characters( $order->shipping_first_name );
				$invoice['shippingaddress']['lastname'] = $this->json_value_remove_special_characters( $order->shipping_last_name );
				$invoice['shippingaddress']['address'] = $this->json_value_remove_special_characters( $order->shipping_address_1 );
				$invoice['shippingaddress']['zip'] = $this->json_value_remove_special_characters( $order->shipping_postcode );
				$invoice['shippingaddress']['city'] = $this->json_value_remove_special_characters( $order->shipping_city );
				$invoice['shippingaddress']['country'] = $this->json_value_remove_special_characters( $order->shipping_country );

				$invoice['lines'] = array();

				$items = $order->get_items();
				foreach ( $items as $item ) {
					$invoice['lines'][] = array(
						'id' => $item['product_id'],
						'description' => $this->json_value_remove_special_characters( $item['name'] ),
						'quantity' => $item['qty'],
						'price' => Epay_Helper::convert_price_to_minorunits( $item['line_subtotal'] / $item['qty'], $minorunits ),
						'vat' => round( ($item['line_subtotal_tax'] / $item['line_subtotal']) * 100 ),
					);
				}

				$discount = $order->get_total_discount();
				if ( $discount > 0 ) {
					$invoice['lines'][] = array(
						'id' => 'discount',
						'description' => 'discount',
						'quantity' => 1,
						'price' => Epay_Helper::convert_price_to_minorunits( $discount * -1, $minorunits ),
						'vat' => round( $order->get_total_tax() / ($order->get_total() - $order->get_total_tax()) * 100 ),
					);
				}

				$shipping = $order->get_total_shipping();
				if ( $shipping > 0 ) {
					$invoice['lines'][] = array(
						'id' => 'shipping',
						'description' => 'shipping',
						'quantity' => 1,
						'price' => Epay_Helper::convert_price_to_minorunits( $shipping, $minorunits ),
						'vat' => round( ($order->get_shipping_tax() / $shipping) * 100 ),
					);
				}

				return wp_json_encode( $invoice, JSON_UNESCAPED_UNICODE );
			} else {
				return '';
			}
		}

		function json_value_remove_special_characters( $value ) {
			return preg_replace( '/[^\p{Latin}\d ]/u', '', $value );
		}

		/**
         * Returns the module header
         *
         * @return string
         */
		private function get_module_header_info() {
			global $woocommerce;
			$ePayVersion = Bambora_Online_Classic::MODULE_VERSION;
			$woocommerce_version = $woocommerce->version;
			$result = 'WooCommerce/' . $woocommerce_version . ' Module/' . $ePayVersion;
			return $result;
		}

		/**
         * Process the payment and return the result
         **/
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true ),
			);
		}

		function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order = new WC_Order( $order_id );
			$transaction_id = get_post_meta( $order->id, 'Transaction ID', true );
			$minorunits = Epay_Helper::get_currency_minorunits( $order->get_order_currency );
			$webservice = new Epay_Soap( $this->remotepassword );
			$credit = $webservice->credit( $this->merchant, $transaction_id, Epay_Helper::convert_price_to_minorunits( $amount, $minorunits ) );
			if ( !$credit->creditResult ) {
				$orderNote = __( 'Credit action failed', 'bambora-online-classic' );
				if ( $credit->epayresponse != '-1' ) {
					$orderNote .= ' - ' . $webservice->getEpayError( $this->merchant, $credit->epayresponse );
				} elseif ( $credit->pbsresponse != '-1' ) {
					$orderNote .= ' - ' . $webservice->getPbsError( $this->merchant, $credit->pbsresponse );
				}

				echo $this->message( 'error', $orderNote );
				return false;
			}

            return true;
		}

		private function get_subscription( $order ) {
			if ( ! function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
				return null;
			}
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			return end( $subscriptions );
		}

		function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
			try {
				// Get the subscription based on the renewal order
				$subscription = $this->get_subscription( $renewal_order );
				if ( isset( $subscription ) ) {
					$parent_order = $subscription->order;
					$bambora_subscription_id = get_post_meta( $parent_order->id, 'Subscription ID', true );
					$order_currency = $renewal_order->get_order_currency();
					$webservice = new Epay_Soap( $this->remotepassword, true );

					$minorUnits = Epay_Helper::get_currency_minorunits( $order_currency );
					$amount = Epay_Helper::convert_price_to_minorunits( $amount_to_charge, $minorUnits );

					$authorize = $webservice->authorize( $this->merchant, $bambora_subscription_id, $renewal_order->id, $amount, Epay_Helper::get_iso_code( $order_currency ), (bool) $this->yesnotoint( $this->instantcapture ), $this->group, $this->authmail );
					if ( $authorize->authorizeResult === true ) {
						update_post_meta( $renewal_order->id,'Transaction ID', $authorize->transactionid );
						$renewal_order->payment_complete();
					} else {
						$orderNote = __( 'Subscription could not be authorized', 'bambora-online-classic' );
						if ( $authorize->epayresponse != '-1' ) {
							$orderNote .= ' - ' . $webservice->getEpayError( $this->merchant, $authorize->epayresponse );
						} elseif ( $authorize->pbsresponse != '-1' ) {
							$orderNote .= ' - ' . $webservice->getPbsError( $this->merchant, $authorize->pbsresponse );
						}
						$renewal_order->update_status( 'failed', $orderNote );
						$subscription->add_order_note( $orderNote . ' ID: ' . $renewal_order->id );
					}
				} else {
					$renewal_order->update_status( 'failed', __( 'No subscription found', 'bambora-online-classic' ) );
				}
			}
            catch (Exception $ex) {
				$renewal_order->update_status( 'failed', $ex->getMessage() );
				error_log( $ex->getMessage() );
			}
		}

		public function subscription_cancellation( $subscription ) {
			try {
				if ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $subscription ) ) {
					$parent_order = $subscription->order;
					$bambora_subscription_id = get_post_meta( $parent_order->id, 'Subscription ID', true );
					if ( empty( $bambora_subscription_id ) ) {
						throw new Exception( __( 'Bambora Subscription ID was not found', 'bambora-online-classic' ) );
					}
					$webservice = new Epay_Soap( $this->remotepassword, true );
					$deletesubscription = $webservice->deletesubscription( $this->merchant, $bambora_subscription_id );
					if ( $deletesubscription->deletesubscriptionResult === true ) {
						$subscription->add_order_note( __( 'Subscription successfully Canceled.', 'bambora-online-classic' ) );
					} else {
						$orderNote = __( 'Subscription could not be canceled', 'bambora-online-classic' );
						if ( $deletesubscription->epayresponse != '-1' ) {
							$orderNote .= ' - ' . $webservice->getEpayError( $this->merchant, $deletesubscription->epayresponse );
							;
						}

						$subscription->add_order_note( $orderNote );
						throw new Exception( $orderNote );
					}
				}
			}
            catch (Exception $ex) {
				$subscription->update_status( 'failed', $ex->getMessage() );
				error_log( $ex->getMessage() );
			}
		}

		/**
         * receipt_page
         **/
		function receipt_page( $order ) {
			echo '<p>' . __( 'Thank you for your order, please click the button below to pay with ePay.', 'bambora-online-classic' ) . '</p>';
			echo $this->generate_epay_form( $order );
		}

		/**
         * Check for epay IPN Response
         **/
		function check_callback() {
			$_GET = stripslashes_deep( $_GET );
			do_action( 'valid-epay-callback', $_GET );
		}

		/**
         * Successful Payment!
         **/
		function successful_request( $posted ) {
			$order = new WC_Order( (int) $posted['wooorderid'] );
			$psb_reference = get_post_meta( (int) $posted['wooorderid'],'Transaction ID',true );

			if ( empty( $psb_reference ) ) {
				// Check for MD5 validity
				$var = '';

				if ( strlen( $this->md5key ) > 0 ) {
					foreach ( $posted as $key => $value ) {
						if ( $key != 'hash' ) {
							$var .= $value;
						}
					}

					$genstamp = md5( $var . $this->md5key );

					if ( $genstamp != $posted['hash'] ) {
						$message = 'MD5 check failed for ePay callback with order_id:' . $posted['wooorderid'];
						$order->add_order_note( $message );
						error_log( $message );
						status_header( 500 );
						die( $message );
					}
				}

				// Payment completed
				$order->add_order_note( __( 'Callback completed', 'bambora-online-classic' ) );
				$minorunits = Epay_Helper::get_currency_minorunits( $order->get_order_currency );

				if ( $posted['txnfee'] > 0 && $this->addfeetoorder == 'yes' ) {
					$feeAmount = floatval( Epay_Helper::convert_price_from_minorunits( $posted['txnfee'], $minorunits ) );
					$order_fee              = new stdClass();
					$order_fee->id          = 'epay_surcharge_fee';
					$order_fee->name        = __( 'Surcharge Fee', 'bambora-online-classic' );
					$order_fee->amount      = $feeAmount;
					$order_fee->taxable     = false;
					$order_fee->tax         = 0;
					$order_fee->tax_data    = array();

					$order->add_fee( $order_fee );
					$order->set_total( $order->order_total + floatval( Epay_Helper::convert_price_from_minorunits( $posted['txnfee'], $minorunits ) ) );
				}

				$order->payment_complete();

				update_post_meta( (int) $posted['wooorderid'], 'Transaction ID', $posted['txnid'] );
				update_post_meta( (int) $posted['wooorderid'], 'Payment Type ID', $posted['paymenttype'] );

				if ( $this->woocommerce_subscription_plugin_is_active() && isset( $posted['subscriptionid'] ) ) {
					WC_Subscriptions_Manager::activate_subscriptions_for_order( $order );
					update_post_meta( (int) $posted['wooorderid'], 'Subscription ID', $posted['subscriptionid'] );
				}

				status_header( 200 );
				die( 'Order Created' );
			} else {
				status_header( 200 );
				die( 'Order already Created' );
			}
		}

		/**
         * Checks if Woocommerce Subscriptions is enabled or not
         */
		private function woocommerce_subscription_plugin_is_active() {
			return class_exists( 'WC_Subscriptions' ) && WC_Subscriptions::$name = 'subscription';
		}

		public function epay_meta_boxes() {
			global $post;
			$order_id = $post->ID;
			$payment_method = get_post_meta( $order_id, '_payment_method', true );
			if ( $this->id === $payment_method ) {
				add_meta_box(
					'epay-payment-actions',
					'Bambora Online ePay',
					array( &$this, 'epay_meta_box_payment' ),
					'shop_order',
					'side',
					'high'
				);
			}
		}

		public function epay_action() {
			if ( isset( $_GET['epay_action'] ) ) {
				$order = new WC_Order( $_GET['post'] );
				$transaction_id = get_post_meta( $order->id, 'Transaction ID', true );
				$minorunits = Epay_Helper::get_currency_minorunits( $order->get_order_currency );
                $success = false;
				try {
					switch ( $_GET['epay_action'] ) {
						case 'capture':
							$amount = str_replace( wc_get_price_decimal_separator(), '.', $_GET['amount'] );
							$webservice = new Epay_Soap( $this->remotepassword );

							$capture = $webservice->capture( $this->merchant, $transaction_id, Epay_Helper::convert_price_to_minorunits( $amount, $minorunits ) );
							if ( $capture->captureResult === true ) {
								echo $this->message( 'updated', 'Payment successfully <strong>captured</strong>.' );
                                $success = true;
							} else {
								$order_note = __( 'Capture action failed', 'bambora-online-classic' );
								if ( $capture->epayresponse != '-1' ) {
									$order_note .= ' - ' . $webservice->getEpayError( $this->merchant, $capture->epayresponse );
								} elseif ( $capture->pbsResponse != '-1' ) {
									$order_note .= ' - ' . $webservice->getPbsError( $this->merchant, $capture->pbsResponse );
								}

								echo $this->message( 'error', $order_note );
							}

							break;

						case 'credit':
							$amount = str_replace( wc_get_price_decimal_separator(),'.',$_GET['amount'] );
							$webservice = new Epay_Soap( $this->remotepassword );
							$credit = $webservice->credit( $this->merchant, $transaction_id, Epay_Helper::convert_price_to_minorunits( $amount, $minorunits ) );
							if ( $credit->creditResult === true ) {
								echo $this->message( 'updated', 'Payment successfully <strong>credited</strong>.' );
                                $success = true;
							} else {
								$order_note = __( 'Credit action failed', 'bambora-online-classic' );
								if ( $credit->epayresponse != '-1' ) {
									$order_note .= ' - ' . $webservice->getEpayError( $this->merchant, $credit->epayresponse );
								} elseif ( $credit->pbsresponse != '-1' ) {
									$order_note .= ' - ' . $webservice->getPbsError( $this->merchant, $credit->pbsresponse );
								}

								echo $this->message( 'error', $order_note );
							}

							break;

						case 'delete':
							$webservice = new Epay_Soap( $this->remotepassword );
							$delete = $webservice->delete( $this->merchant, $transaction_id );
							if ( $delete->deleteResult === true ) {
								echo $this->message( 'updated', 'Payment successfully <strong>deleted</strong>.' );
                                $success = true;
							} else {
								$order_note = __( 'Delete action failed', 'bambora-online-classic' );
								if ( $delete->epayresponse != '-1' ) {
									$order_note .= ' - ' . $webservice->getEpayError( $this->merchant, $delete->epayresponse );
								}

								echo $this->message( 'error', $order_note );
							}

							break;
					}
				}
                catch (Exception $e) {
					echo $this->message( 'error', $e->getMessage() );
				}

                if($success) {
                    global $post;
                    $url = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );
                    wp_safe_redirect( $url );
                }
			}
		}

		public function epay_meta_box_payment() {
			global $post;

			$order = new WC_Order( $post->ID );
			$transaction_id = get_post_meta( $order->id, 'Transaction ID', true );

			$payment_method = get_post_meta( $order->id , '_payment_method', true );
			if ( $payment_method === $this->id && strlen( $transaction_id ) > 0 ) {
				try {
					$payment_type_id = get_post_meta( $order->id, 'Payment Type ID', true );
					$webservice = new Epay_Soap( $this->remotepassword );
					$transaction = $webservice->gettransaction( $this->merchant, $transaction_id );

					if ( $transaction->gettransactionResult === true ) {
						echo '<div class="epay-info">';
						echo    '<div class="epay-transactionid">';
						echo        '<p>';
						_e( 'Transaction ID', 'bambora-online-classic' );
						echo        '</p>';
						echo        '<p>' . $transaction->transactionInformation->transactionid . '</p>';
						echo    '</div>';

						if ( strlen( $payment_type_id ) > 0 ) {
							echo '<div class="epay-paymenttype">';
							echo    '<p>';
							_e( 'Payment Type', 'bambora-online-classic' );
							echo    '</p>';
							echo    '<div class="epay-paymenttype-group">';
							echo        '<img src="https://d25dqh6gpkyuw6.cloudfront.net/paymentlogos/external/' . intval( $payment_type_id ) . '.png" alt="' . $this->get_card_name_by_id( intval( $payment_type_id ) ) . '" title="' . $this->get_card_name_by_id( intval( $payment_type_id ) ) . '" /><div>' . $this->get_card_name_by_id( intval( $payment_type_id ) );
							if ( strlen( $transaction->transactionInformation->tcardno ) > 0 ) {
								echo '<br />' . $transaction->transactionInformation->tcardno;
							}
							echo '</div></div></div>';
						}

						$epayhelper = new Epay_Helper();
						$currency_code = $transaction->transactionInformation->currency;
						$currency = $epayhelper->get_iso_code( $currency_code, false );
						$minorunits = Epay_Helper::get_currency_minorunits( $currency );

						echo '<div class="epay-info-overview">';
						echo    '<p>';
						_e( 'Authorized amount', 'bambora-online-classic' );
						echo    ':</p>';
						echo    '<p>' . Epay_Helper::convert_price_from_minorunits( $transaction->transactionInformation->authamount, $minorunits, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) . ' ' . $currency . '</p>';
						echo '</div>';

						echo '<div class="epay-info-overview">';
						echo    '<p>';
						_e( 'Captured amount', 'bambora-online-classic' );
						echo    ':</p>';
						echo    '<p>' . Epay_Helper::convert_price_from_minorunits( $transaction->transactionInformation->capturedamount, $minorunits, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) . ' ' . $currency . '</p>';
						echo '</div>';

						echo '<div class="epay-info-overview">';
						echo    '<p>';
						_e( 'Credited amount', 'bambora-online-classic' );
						echo    ':</p>';
						echo    '<p>' . Epay_Helper::convert_price_from_minorunits( $transaction->transactionInformation->creditedamount, $minorunits, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() ) . ' ' . $currency . '</p>';
						echo '</div>';

						echo '</div>';

						if ( $transaction->transactionInformation->status == 'PAYMENT_NEW' ) {
							echo '<div class="epay-input-group">';
							echo '<div class="epay-input-group-currency">' . $currency . '</div><input type="text" value="' . Epay_Helper::convert_price_from_minorunits( ($transaction->transactionInformation->authamount - $transaction->transactionInformation->capturedamount), $minorunits, wc_get_price_decimal_separator(), '' ) . '" id="epay_amount" name="epay_amount" />';
							echo '</div>';
							echo '<div class="epay-action">';
							echo '<a class="button capture" onclick="javascript:location.href=\'' . admin_url( 'post.php?post=' . $post->ID . '&action=edit&epay_action=capture' ) . '&amount=\' + document.getElementById(\'epay_amount\').value">';
							_e( 'Capture', 'bambora-online-classic' );
							echo '</a>';
							echo '</div>';
							if ( ! $transaction->transactionInformation->capturedamount ) {
								echo '<div class="epay-action">';
								echo '<a class="button delete"  onclick="javascript: (confirm(\'' . __( 'Are you sure you want to delete?', 'bambora-online-classic' ) . '\') ? (location.href=\'' . admin_url( 'post.php?post=' . $post->ID . '&action=edit&epay_action=delete' ) . '\') : (false));">';
								_e( 'Delete', 'bambora-online-classic' );
								echo '</a>';
								echo '</div>';
							}
						} elseif ( $transaction->transactionInformation->status == 'PAYMENT_CAPTURED' && $transaction->transactionInformation->creditedamount == 0 ) {
							echo '<div class="epay-input-group">';
							echo '<div class="epay-input-group-currency">' . $currency . '</div><input type="text" value="' . Epay_Helper::convert_price_from_minorunits( $transaction->transactionInformation->capturedamount, $minorunits, wc_get_price_decimal_separator(), '' ) . '" id="epay_credit_amount" name="epay_credit_amount" />';
							echo '</div>';
							echo '<div class="epay-action">';
							echo '<a class="button credit" onclick="javascript: (confirm(\'' . __( 'Are you sure you want to credit?', 'bambora-online-classic' ) . '\') ? (location.href=\'' . admin_url( 'post.php?post=' . $post->ID . '&action=edit&epay_action=credit' ) . '&amount=\' + document.getElementById(\'epay_credit_amount\').value) : (false));">';
							_e( 'Credit', 'bambora-online-classic' );
							echo '</a>';
							echo '</div>';
						}

						$history_array = $transaction->transactionInformation->history->TransactionHistoryInfo;

						if ( ! array_key_exists( 0, $transaction->transactionInformation->history->TransactionHistoryInfo ) ) {
							$history_array = array( $transaction->transactionInformation->history->TransactionHistoryInfo );
						}
						if ( count( $history_array ) > 0 ) {
							echo '<h4 class="epay-header">';
							_e( 'TRANSACTION HISTORY', 'bambora-online-classic' );
							echo '</h4>';
							echo '<table class="epay-table">';
							for ( $i = 0; $i < count( $history_array ); $i++ ) {
								echo '<tr class="epay-transaction-date"><td>';
								echo str_replace( 'T', ' ', $history_array[ $i ]->created );
								echo '</td></tr><tr class="epay-transaction"><td>';
								if ( strlen( $history_array[ $i ]->username ) > 0 ) {
									echo ($history_array[ $i ]->username . ': ');
								}
								echo $history_array[ $i ]->eventMsg;
								echo '</td></tr>';
							}
							echo '</table>';
						}
					} else {
						$order_note = __( 'Get Transaction action failed', 'bambora-online-classic' );
						if ( $transaction->epayresponse != '-1' ) {
							$order_note .= ' - ' . $webservice->getEpayError( $this->merchant, $transaction->epayresponse );
						}

						echo $this->message( 'error', $order_note );
					}
				}
                catch (Exception $e) {
					echo $this->message( 'error', $e->getMessage() );
				}
			} else {
				echo __( 'No transaction was found.', 'bambora-online-classic' );
			}
		}

		private function message( $type, $message ) {
			return '<div id="message" class="' . $type . '">
				<p>' . $message . '</p>
			</div>';
		}

		private function get_card_name_by_id( $card_id ) {
			switch ( $card_id ) {
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

	add_filter( 'woocommerce_payment_gateways', 'add_bambora_online_classic' );
	Bambora_Online_Classic::get_instance()->init_hooks();

	/**
     * Add the Gateway to WooCommerce
     **/
	function add_bambora_online_classic( $methods ) {
		$methods[] = 'Bambora_Online_Classic';
		return $methods;
	}

	$plugin_dir = basename( dirname( __FILE__ ) );
	load_plugin_textdomain( 'bambora-online-classic', false, $plugin_dir . '/languages' );
}
