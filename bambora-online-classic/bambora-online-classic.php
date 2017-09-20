<?php
/*
Plugin Name: Bambora Online ePay
Plugin URI: http://www.epay.dk
Description: A payment gateway for Bambora Online ePay
Version: 3.1.5
Author: Bambora Online
Author URI: http://www.epay.dk/epay-payment-solutions
Text Domain: bambora-online-classic
 */

add_action('plugins_loaded', 'init_bambora_online_classic');

function init_bambora_online_classic()
{
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }

    define('EPAY_LIB', dirname(__FILE__) . '/lib/');

    include(EPAY_LIB . 'epay-soap.php');
    include(EPAY_LIB . 'epay-helper.php');

    /**
     * Gateway class
     **/
    class Bambora_Online_Classic extends WC_Payment_Gateway
    {
        const MODULE_VERSION = '3.1.4';
        const PSP_REFERENCE = 'Transaction ID';

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
        public static function get_instance()
        {
            if (! isset(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function __construct()
        {
            $this->id = 'epay_dk';
            $this->method_title = 'Bambora Online ePay';
            $this->icon = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/bambora-logo.svg';
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
            $this->enabled = array_key_exists('enabled', $this->settings) ? $this->settings['enabled'] : 'yes';
            $this->title = array_key_exists('title', $this->settings) ? $this->settings['title'] : 'Bambora Online ePay';
            $this->description = array_key_exists('description', $this->settings) ? $this->settings['description'] : 'Pay using Bambora Online ePay';
            $this->merchant = array_key_exists('merchant', $this->settings) ? $this->settings['merchant'] : '';
            $this->windowid = array_key_exists('windowid', $this->settings) ? $this->settings['windowid'] : '1';
            $this->windowstate = array_key_exists('windowstate', $this->settings) ? $this->settings['windowstate'] : 1;
            $this->md5key = array_key_exists('md5key', $this->settings) ? $this->settings['md5key'] : '';
            $this->instantcapture = array_key_exists('instantcapture', $this->settings) ? $this->settings['instantcapture'] : 'no';
            $this->group = array_key_exists('group', $this->settings) ? $this->settings['group'] : '';
            $this->authmail = array_key_exists('authmail', $this->settings) ? $this->settings['authmail'] : '';
            $this->ownreceipt = array_key_exists('ownreceipt', $this->settings) ? $this->settings['ownreceipt'] : 'no';
            $this->remoteinterface = array_key_exists('remoteinterface', $this->settings) ? $this->settings['remoteinterface'] : 'no';
            $this->remotepassword = array_key_exists('remotepassword', $this->settings) ? $this->settings['remotepassword'] : '';
            $this->enableinvoice = array_key_exists('enableinvoice', $this->settings) ? $this->settings['enableinvoice'] : 'no';
            $this->addfeetoorder = array_key_exists('addfeetoorder', $this->settings) ? $this->settings['addfeetoorder'] : 'no';
            $this->enablemobilepaymentwindow = array_key_exists('enablemobilepaymentwindow', $this->settings) ? $this->settings['enablemobilepaymentwindow'] : 'yes';
            $this->roundingmode = array_key_exists('roundingmode', $this->settings) ? $this->settings['roundingmode'] : Epay_Helper::ROUND_DEFAULT;

            $this->set_epay_description_for_checkout($this->merchant);

            if ($this->yesnotoint($this->remoteinterface)) {
                $this->supports = array_merge($this->supports, array( 'refunds' ));
            }
        }

        public function init_hooks()
        {
            // Actions
            add_action('valid-epay-callback', array( $this, 'successful_request' ));
            add_action('woocommerce_api_' . strtolower(get_class()), array( $this, 'check_callback' ));
            add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2);
            add_action('woocommerce_subscription_cancelled_' . $this->id, array( $this, 'subscription_cancellation' ));
            add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ));

            if (is_admin()) {
                if ($this->remoteinterface == 'yes') {
                    add_action('add_meta_boxes', array( $this, 'epay_meta_boxes' ));
                }

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
                add_action('wp_before_admin_bar_render', array( $this, 'epay_action' ));
            }

            // Register styles!
            add_action('admin_enqueue_scripts', array( $this, 'enqueue_wc_epay_admin_styles_and_scripts' ));
            add_action('wp_enqueue_scripts', array( $this, 'enqueue_wc_epay_front_styles' ));
        }

        /**
         * Enqueue Admin Styles and Scripts
         */
        public function enqueue_wc_epay_admin_styles_and_scripts()
        {
            wp_register_style('epay_admin_style', plugins_url('bambora-online-classic/style/epay-admin.css'));
            wp_enqueue_style('epay_admin_style');
        }

        /**
         * Enqueue Frontend Styles and Scripts
         */
        public function enqueue_wc_epay_front_styles()
        {
            wp_register_style('epay_front_style', plugins_url('bambora-online-classic/style/epay-front.css'));
            wp_enqueue_style('epay_front_style');
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                                'title' => 'Activate module',
                                'type' => 'checkbox',
                                'label' => 'Set to Yes to allow your customers to use Bambora Online ePay as a payment option.',
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
                                'description' => 'The number identifying your ePay merchant account.',
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
                                'description' => 'If a Remote password is set in the ePay administration, then the same password must be entered here',
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
                'windowstate' => array(
                                'title' => 'Window state',
                                'type' => 'select',
                                'options' => array( 1 => 'Overlay', 3 => 'Full screen' ),
                                'description' => 'Please select if you want the Payment window shown as an overlay or as full screen.',
                                'default' => 1,
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
                                'options' => array( Epay_Helper::ROUND_DEFAULT => 'Default', Epay_Helper::ROUND_UP => 'Always up', Epay_Helper::ROUND_DOWN => 'Always down' ),
                                'default' => 'normal',
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
            $version = $plugin_data['Version'];

            echo '<h3>' . 'Bambora Online ePay' . ' v' . $version . '</h3>';
            echo '<a href="http://woocommerce.wpguiden.dk/en/configuration#709" target="_blank">' . __('Documentation can be found here', 'bambora-online-classic') . '</a>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * There are no payment fields for epay, but we want to show the description if set.
         **/
        public function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }

        /**
         * Set the WC Payment Gateway description for the checkout page
         */
        public function set_epay_description_for_checkout($merchantnumber)
        {
            global $woocommerce;
            $cart = $this->is_woocommerce_3() ? WC()->cart : $woocommerce->cart;
            if (! $cart || ! $merchantnumber) {
                return;
            }

            $this->description .= '<div id="epay_card_logos"><script type="text/javascript" src="https://relay.ditonlinebetalingssystem.dk/integration/paymentlogos/PaymentLogos.aspx?merchantnumber=' . $merchantnumber . '&direction=2&padding=2&rows=1&logo=0&showdivs=0&divid=epay_card_logos"></script></div>';
        }

        public function fix_url($url)
        {
            $url = str_replace('&#038;', '&amp;', $url);
            $url = str_replace('&amp;', '&', $url);

            return $url;
        }

        public function yesnotoint($str)
        {
            return $str === 'yes' ? 1 : 0;
        }

        /**
         * Generate the epay button link
         **/
        public function generate_epay_form($order_id)
        {
            $order = wc_get_order($order_id);
            $order_currency = $this->is_woocommerce_3() ? $order->get_currency() : $order->get_order_currency();
            $minorUnits = Epay_Helper::get_currency_minorunits($order_currency);
            $epay_args = array(
                'encoding' => 'UTF-8',
                'cms' => $this->get_module_header_info(),
                'windowstate' => $this->windowstate,
                'mobile' => $this->enablemobilepaymentwindow === 'yes' ? 1 : 0,
                'merchantnumber' => $this->merchant,
                'windowid' => $this->windowid,
                'currency' => $order_currency,
                'amount' => Epay_Helper::convert_price_to_minorunits($order->get_total(), $minorUnits, $this->roundingmode),
                'orderid' => str_replace(_x('#', 'hash before order number', 'woocommerce'), '', $order->get_order_number()),
                'accepturl' => $this->fix_url($this->get_return_url($order)),
                'cancelurl' => $this->fix_url($order->get_cancel_order_url()),
                'callbackurl' => $this->fix_url(add_query_arg('wooorderid', $order_id, add_query_arg('wc-api', 'Bambora_Online_Classic', $this->get_return_url($order)))),
                'mailreceipt' => $this->authmail,
                'instantcapture' => $this->yesnotoint($this->instantcapture),
                'group' => $this->group,
                'language' => Epay_Helper::get_language_code(get_locale()),
                'ownreceipt' => $this->yesnotoint($this->ownreceipt),
                'timeout' => '60',
                'invoice' => $this->create_invoice($order, $minorUnits),
            );

            if ($this->woocommerce_subscription_plugin_is_active() && wcs_order_contains_subscription($order)) {
                $epay_args['subscription'] = 1;
            }

            if (strlen($this->md5key) > 0) {
                $hash = '';
                foreach ($epay_args as $value) {
                    $hash .= $value;
                }
                $epay_args['hash'] = md5($hash . $this->md5key);
            }

            $epay_args_array = array();
            foreach ($epay_args as $key => $value) {
                $epay_args_array[] = "'" . esc_attr($key) . "':'" . $value . "'";
            }

            $payment_script = '<script type="text/javascript">
            function PaymentWindowReady() {
                paymentwindow = new PaymentWindow({
                    ' . implode(',', $epay_args_array) . '
                });
                paymentwindow.open();
            }
            </script>
            <script type="text/javascript" src="https://ssl.ditonlinebetalingssystem.dk/integration/ewindow/paymentwindow.js" charset="UTF-8"></script>
            <a class="button" onclick="javascript: paymentwindow.open();" id="submit_epay_payment_form" />' . __('Pay via ePay', 'bambora-online-classic') . '</a>
            <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'bambora-online-classic') . '</a>';

            return $payment_script;
        }

        /**
         * Create invoice lines
         *
         * @param WC_Order $order
         * @param int $minorunits
         * @return string
         * */
        private function create_invoice($order, $minorunits)
        {
            if ($this->enableinvoice == 'yes') {
                if ($this->is_woocommerce_3()) {
                    $invoice['customer']['emailaddress'] = $order->get_billing_email();
                    $invoice['customer']['firstname'] = $this->json_value_remove_special_characters($order->get_billing_first_name());
                    $invoice['customer']['lastname'] = $this->json_value_remove_special_characters($order->get_billing_last_name());
                    $invoice['customer']['address'] = $this->json_value_remove_special_characters($order->get_billing_address_1());
                    $invoice['customer']['zip'] = $this->json_value_remove_special_characters($order->get_billing_postcode());
                    $invoice['customer']['city'] = $this->json_value_remove_special_characters($order->get_billing_city());
                    $invoice['customer']['country'] = $this->json_value_remove_special_characters($order->get_billing_country());

                    $invoice['shippingaddress']['firstname'] = $this->json_value_remove_special_characters($order->get_shipping_first_name());
                    $invoice['shippingaddress']['lastname'] = $this->json_value_remove_special_characters($order->get_shipping_last_name());
                    $invoice['shippingaddress']['address'] = $this->json_value_remove_special_characters($order->get_shipping_address_1());
                    $invoice['shippingaddress']['zip'] = $this->json_value_remove_special_characters($order->get_shipping_postcode());
                    $invoice['shippingaddress']['city'] = $this->json_value_remove_special_characters($order->get_shipping_city());
                    $invoice['shippingaddress']['country'] = $this->json_value_remove_special_characters($order->get_shipping_country());
                } else {
                    $invoice['customer']['emailaddress'] = $order->billing_email;
                    $invoice['customer']['firstname'] = $this->json_value_remove_special_characters($order->billing_first_name);
                    $invoice['customer']['lastname'] = $this->json_value_remove_special_characters($order->billing_last_name);
                    $invoice['customer']['address'] = $this->json_value_remove_special_characters($order->billing_address_1);
                    $invoice['customer']['zip'] = $this->json_value_remove_special_characters($order->billing_postcode);
                    $invoice['customer']['city'] = $this->json_value_remove_special_characters($order->billing_city);
                    $invoice['customer']['country'] = $this->json_value_remove_special_characters($order->billing_country);

                    $invoice['shippingaddress']['firstname'] = $this->json_value_remove_special_characters($order->shipping_first_name);
                    $invoice['shippingaddress']['lastname'] = $this->json_value_remove_special_characters($order->shipping_last_name);
                    $invoice['shippingaddress']['address'] = $this->json_value_remove_special_characters($order->shipping_address_1);
                    $invoice['shippingaddress']['zip'] = $this->json_value_remove_special_characters($order->shipping_postcode);
                    $invoice['shippingaddress']['city'] = $this->json_value_remove_special_characters($order->shipping_city);
                    $invoice['shippingaddress']['country'] = $this->json_value_remove_special_characters($order->shipping_country);
                }

                $invoice['lines'] = array();

                $items = $order->get_items();
                foreach ($items as $item) {
                    $invoice['lines'][] = array(
                        'id' => $item['product_id'],
                        'description' => $this->json_value_remove_special_characters($item['name']),
                        'quantity' => $item['qty'],
                        'price' => Epay_Helper::convert_price_to_minorunits(($item['line_subtotal'] / $item['qty']), $minorunits, $this->roundingmode),
                        'vat' => round(($item['line_subtotal_tax'] / $item['line_subtotal']) * 100),
                    );
                }

                $discount = $order->get_total_discount();
                if ($discount > 0) {
                    $invoice['lines'][] = array(
                        'id' => 'discount',
                        'description' => 'discount',
                        'quantity' => 1,
                        'price' => Epay_Helper::convert_price_to_minorunits(($discount * -1), $minorunits, $this->roundingmode),
                        'vat' => round($order->get_total_tax() / ($order->get_total() - $order->get_total_tax()) * 100),
                    );
                }

                $shipping = $this->is_woocommerce_3() ? $order->get_shipping_total() : $order->get_total_shipping();
                if ($shipping > 0) {
                    $invoice['lines'][] = array(
                        'id' => 'shipping',
                        'description' => 'shipping',
                        'quantity' => 1,
                        'price' => Epay_Helper::convert_price_to_minorunits($shipping, $minorunits, $this->roundingmode),
                        'vat' => round(($order->get_shipping_tax() / $shipping) * 100),
                    );
                }

                return wp_json_encode($invoice, JSON_UNESCAPED_UNICODE);
            } else {
                return '';
            }
        }

        public function json_value_remove_special_characters($value)
        {
            return preg_replace('/[^\p{Latin}\d ]/u', '', $value);
        }

        /**
         * Process the payment and return the result
         **/
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);

            return array(
                'result'    => 'success',
                'redirect'    => $order->get_checkout_payment_url(true),
            );
        }

        public function process_refund($order_id, $amount = null, $reason = '')
        {
            $order = wc_get_order($order_id);
            $transaction_id = $this->get_bambora_transaction_id($order);
            $order_currency = $this->is_woocommerce_3() ? $order->get_currency() : $order->get_order_currency;
            $minorunits = Epay_Helper::get_currency_minorunits($order_currency);
            $webservice = new Epay_Soap($this->remotepassword);
            $credit = $webservice->credit($this->merchant, $transaction_id, Epay_Helper::convert_price_to_minorunits($amount, $minorunits, $this->roundingmode));
            if (!$credit->creditResult) {
                $orderNote = __('Credit action failed', 'bambora-online-classic');
                if ($credit->epayresponse != '-1') {
                    $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $credit->epayresponse);
                } elseif ($credit->pbsresponse != '-1') {
                    $orderNote .= ' - ' . $webservice->getPbsError($this->merchant, $credit->pbsresponse);
                }

                echo $this->message('error', $orderNote);
                return false;
            }

            return true;
        }

        private function get_subscription($order)
        {
            if (! function_exists('wcs_get_subscriptions_for_renewal_order')) {
                return null;
            }
            $subscriptions = wcs_get_subscriptions_for_renewal_order($order);
            return end($subscriptions);
        }

        public function scheduled_subscription_payment($amount_to_charge, $renewal_order)
        {
            try {
                // Get the subscription based on the renewal order
                $subscription = $this->get_subscription($renewal_order);
                if (isset($subscription)) {
                    $parent_order = $subscription->order;
                    $parent_order_id = $this->is_woocommerce_3() ? $parent_order->get_id() : $parent_order->id;
                    $bambora_subscription_id = get_post_meta($parent_order_id, 'Subscription ID', true);
                    if( strlen( $bambora_subscription_id ) > 0) {
                        $order_currency = $this->is_woocommerce_3() ? $renewal_order->get_currency() : $renewal_order->get_order_currency();
                        $webservice = new Epay_Soap($this->remotepassword, true);

                        $renewal_order_id = $this->is_woocommerce_3() ? $renewal_order->get_id() : $renewal_order->id;
                        $minorUnits = Epay_Helper::get_currency_minorunits($order_currency);
                        $amount = Epay_Helper::convert_price_to_minorunits($amount_to_charge, $minorUnits, $this->roundingmode);

                        $authorize = $webservice->authorize($this->merchant, $bambora_subscription_id, $renewal_order_id, $amount, Epay_Helper::get_iso_code($order_currency), (bool) $this->yesnotoint($this->instantcapture), $this->group, $this->authmail);
                        if ($authorize->authorizeResult === true) {
                            if( $this->is_woocommerce_3() ) {
                                $renewal_order->set_transaction_id( $authorize->transactionid );
                                $renewal_order->save();
                            } else {
                                update_post_meta($renewal_order_id, $this::PSP_REFERENCE, $authorize->transactionid);
                            }


                            $renewal_order->payment_complete();
                        } else {
                            $orderNote = __('Subscription could not be authorized', 'bambora-online-classic');
                            if ($authorize->epayresponse != '-1') {
                                $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $authorize->epayresponse);
                            } elseif ($authorize->pbsresponse != '-1') {
                                $orderNote .= ' - ' . $webservice->getPbsError($this->merchant, $authorize->pbsresponse);
                            }
                            $renewal_order->update_status('failed', $orderNote);
                            $subscription->add_order_note($orderNote . ' ID: ' . $renewal_order_id);
                        }
                    } else {
                        $renewal_order->update_status('failed', __('Bambora Subscription ID was not found', 'bambora-online-classic'));
                    }
                } else {
                    $renewal_order->update_status('failed', __('No subscription found', 'bambora-online-classic'));
                }

            }
            catch (Exception $ex) {
                $renewal_order->update_status('failed', $ex->getMessage());
                error_log($ex->getMessage());
            }
        }

        public function subscription_cancellation($subscription)
        {
            try {
                if (function_exists('wcs_is_subscription') && wcs_is_subscription($subscription)) {
                    $parent_order = $subscription->order;
                    $parent_order_id = $this->is_woocommerce_3() ? $parent_order->get_id() : $parent_order->id;
                    $bambora_subscription_id = get_post_meta($parent_order_id, 'Subscription ID', true);
                    if ( strlen( $bambora_subscription_id ) === 0) {
                        $orderNote = __('Bambora Subscription ID was not found', 'bambora-online-classic');
                        $subscription->add_order_note($orderNote);
                        throw new Exception($orderNote);
                    }
                    $webservice = new Epay_Soap($this->remotepassword, true);
                    $deletesubscription = $webservice->deletesubscription($this->merchant, $bambora_subscription_id);
                    if ($deletesubscription->deletesubscriptionResult === true) {
                        $subscription->add_order_note(__('Subscription successfully Canceled.', 'bambora-online-classic'));
                    } else {
                        $orderNote = __('Subscription could not be canceled', 'bambora-online-classic');
                        if ($deletesubscription->epayresponse != '-1') {
                            $orderNote .= ' - ' . $webservice->getEpayError($this->merchant, $deletesubscription->epayresponse);
                        }
                        $subscription->add_order_note($orderNote);
                        throw new Exception($orderNote);
                    }
                }
            }
            catch (Exception $ex) {
                error_log($ex->getMessage());
                return false;
            }
            return true;
        }

        /**
         * receipt_page
         **/
        public function receipt_page($order)
        {
            echo '<p>' . __('Thank you for your order, please click the button below to pay with ePay.', 'bambora-online-classic') . '</p>';
            echo $this->generate_epay_form($order);
        }

        /**
         * Check for epay IPN Response
         **/
        public function check_callback()
        {
            $_GET = stripslashes_deep($_GET);
            do_action('valid-epay-callback', $_GET);
        }

        /**
         * Successful Payment!
         **/
        public function successful_request($posted)
        {
            $woo_order_id = $posted['wooorderid'];
            $order = wc_get_order((int) $posted['wooorderid']);
            $psp_reference = $this->get_bambora_transaction_id($order);
            if (empty($psp_reference)) {
                // Check for MD5 validity
                $var = '';

                if (strlen($this->md5key) > 0) {
                    foreach ($posted as $key => $value) {
                        if ($key != 'hash') {
                            $var .= $value;
                        }
                    }

                    $genstamp = md5($var . $this->md5key);
                    if ($genstamp != $posted['hash']) {
                        $message = 'MD5 check failed for ePay callback with order_id:' . $posted['wooorderid'];
                        $order->add_order_note($message);
                        error_log($message);
                        status_header(500);
                        die($message);
                    }
                }

                // Payment completed
                $order_currency = $this->is_woocommerce_3() ? $order->get_currency() : $order->get_order_currency;
                $minorunits = Epay_Helper::get_currency_minorunits($order_currency);

                if ($posted['txnfee'] > 0 && $this->addfeetoorder == 'yes') {
                    $feeAmount = floatval(Epay_Helper::convert_price_from_minorunits($posted['txnfee'], $minorunits));
                    if ($this->is_woocommerce_3()) {
                        $order_fee = new WC_Order_Item_Fee();
                        $order_fee->set_total($feeAmount);
                        $order_fee->set_tax_status('none');
                        $order_fee->set_total_tax(0);
                        $order_fee->save();

                        $order->add_item($order_fee);
                        $order->calculate_totals();
                    } else {
                        $order_fee              = new stdClass();
                        $order_fee->id          = 'epay_surcharge_fee';
                        $order_fee->name        = __('Surcharge Fee', 'bambora-online-classic');
                        $order_fee->amount      = $feeAmount;
                        $order_fee->taxable     = false;
                        $order_fee->tax         = 0;
                        $order_fee->tax_data    = array();

                        $order->add_fee($order_fee);
                        $order_total = ($this->is_woocommerce_3() ? $order->get_total() : $order->order_total) + $feeAmount;
                        $order->set_total($order_total);
                    }
                }

                $order->payment_complete();

                $transaction_id = $posted['txnid'];

                if($this->is_woocommerce_3()) {
                    $order->set_transaction_id( $transaction_id );
                } else {
                    update_post_meta( $woo_order_id, $this::PSP_REFERENCE, $transaction_id );
                }

                update_post_meta((int) $woo_order_id, 'Payment Type ID', $posted['paymenttype']);

                if ($this->woocommerce_subscription_plugin_is_active() && isset($posted['subscriptionid'])) {
                    WC_Subscriptions_Manager::activate_subscriptions_for_order($order);

                    update_post_meta((int) $woo_order_id, 'Subscription ID', $posted['subscriptionid']);
                    $order->add_order_note( __( 'Subscription activated', 'bambora-online-classic' ) );
                }
                $order->add_order_note( __( 'Callback completed', 'bambora-online-classic' ) );

                if($this->is_woocommerce_3()) {
                    $order->save();
                }

                status_header(200);
                die('Order Created');
            } else {
                status_header(200);
                die('Order already Created');
            }
        }

        public function epay_meta_boxes()
        {
            global $post;
            $order_id = $post->ID;
            $payment_method = get_post_meta($order_id, '_payment_method', true);
            if ($this->id === $payment_method) {
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

        public function epay_action()
        {
            if (isset($_GET['epay_action'])) {
                $order = wc_get_order($_GET['post']);
                if ( isset( $order ) ) {
                    $order_currency = $this->is_woocommerce_3() ? $order->get_currency() : $order->get_order_currency;
                    $transaction_id = $this->get_bambora_transaction_id($order);
                    $minorunits = Epay_Helper::get_currency_minorunits($order_currency);
                    $success = false;
                    try {
                        switch ($_GET['epay_action']) {
                            case 'capture':
                                $amount = str_replace(wc_get_price_decimal_separator(), '.', $_GET['amount']);
                                $webservice = new Epay_Soap($this->remotepassword);

                                $capture = $webservice->capture($this->merchant, $transaction_id, Epay_Helper::convert_price_to_minorunits($amount, $minorunits, $this->roundingmode));
                                if ($capture->captureResult === true) {
                                    echo $this->message('updated', 'Payment successfully <strong>captured</strong>.');
                                    $success = true;
                                } else {
                                    $order_note = __('Capture action failed', 'bambora-online-classic');
                                    if ($capture->epayresponse != '-1') {
                                        $order_note .= ' - ' . $webservice->getEpayError($this->merchant, $capture->epayresponse);
                                    } elseif ($capture->pbsResponse != '-1') {
                                        $order_note .= ' - ' . $webservice->getPbsError($this->merchant, $capture->pbsResponse);
                                    }

                                    echo $this->message('error', $order_note);
                                }

                                break;

                            case 'credit':
                                $amount = str_replace(wc_get_price_decimal_separator(), '.', $_GET['amount']);
                                $webservice = new Epay_Soap($this->remotepassword);
                                $credit = $webservice->credit($this->merchant, $transaction_id, Epay_Helper::convert_price_to_minorunits($amount, $minorunits, $this->roundingmode));
                                if ($credit->creditResult === true) {
                                    echo $this->message('updated', 'Payment successfully <strong>credited</strong>.');
                                    $success = true;
                                } else {
                                    $order_note = __('Credit action failed', 'bambora-online-classic');
                                    if ($credit->epayresponse != '-1') {
                                        $order_note .= ' - ' . $webservice->getEpayError($this->merchant, $credit->epayresponse);
                                    } elseif ($credit->pbsresponse != '-1') {
                                        $order_note .= ' - ' . $webservice->getPbsError($this->merchant, $credit->pbsresponse);
                                    }

                                    echo $this->message('error', $order_note);
                                }

                                break;

                            case 'delete':
                                $webservice = new Epay_Soap($this->remotepassword);
                                $delete = $webservice->delete($this->merchant, $transaction_id);
                                if ($delete->deleteResult === true) {
                                    echo $this->message('updated', 'Payment successfully <strong>deleted</strong>.');
                                    $success = true;
                                } else {
                                    $order_note = __('Delete action failed', 'bambora-online-classic');
                                    if ($delete->epayresponse != '-1') {
                                        $order_note .= ' - ' . $webservice->getEpayError($this->merchant, $delete->epayresponse);
                                    }

                                    echo $this->message('error', $order_note);
                                }

                                break;
                        }
                    }
                    catch (Exception $e) {
                        echo $this->message('error', $e->getMessage());
                    }

                    if ($success) {
                        global $post;
                        $url = admin_url('post.php?post=' . $post->ID . '&action=edit');
                        wp_safe_redirect($url);
                    }
                }
            }
        }

        public function epay_meta_box_payment()
        {
            global $post;

            $order = wc_get_order($post->ID);
            $order_id = $this->is_woocommerce_3() ? $order->get_id() : $order->id;
            $transaction_id = $this->get_bambora_transaction_id($order);

            $payment_method = get_post_meta($order_id, '_payment_method', true);
            if ($payment_method === $this->id && strlen($transaction_id) > 0) {
                try {
                    $payment_type_id = get_post_meta($order_id, 'Payment Type ID', true);
                    $webservice = new Epay_Soap($this->remotepassword);
                    $transaction = $webservice->gettransaction($this->merchant, $transaction_id);

                    if ($transaction->gettransactionResult === true) {
                        echo '<div class="epay-info">';
                        echo    '<div class="epay-transactionid">';
                        echo        '<p>';
                        _e('Transaction ID', 'bambora-online-classic');
                        echo        '</p>';
                        echo        '<p>' . $transaction->transactionInformation->transactionid . '</p>';
                        echo    '</div>';

                        if (strlen($payment_type_id) > 0) {
                            echo '<div class="epay-paymenttype">';
                            echo    '<p>';
                            _e('Payment Type', 'bambora-online-classic');
                            echo    '</p>';
                            echo    '<div class="epay-paymenttype-group">';
                            $cardName = Epay_Helper::get_card_name_by_id((int) $payment_type_id);
                            echo        '<img src="https://d25dqh6gpkyuw6.cloudfront.net/paymentlogos/external/' . intval($payment_type_id) . '.png" alt="' . $cardName . '" title="' . $cardName . '" /><div>' . $cardName;
                            if (strlen($transaction->transactionInformation->tcardno) > 0) {
                                echo '<br />' . $transaction->transactionInformation->tcardno;
                            }
                            echo '</div></div></div>';
                        }

                        $epayhelper = new Epay_Helper();
                        $currency_code = $transaction->transactionInformation->currency;
                        $currency = $epayhelper->get_iso_code($currency_code, false);
                        $minorunits = Epay_Helper::get_currency_minorunits($currency);

                        echo '<div class="epay-info-overview">';
                        echo    '<p>';
                        _e('Authorized amount', 'bambora-online-classic');
                        echo    ':</p>';
                        echo    '<p>' . Epay_Helper::convert_price_from_minorunits($transaction->transactionInformation->authamount, $minorunits, wc_get_price_decimal_separator(), wc_get_price_thousand_separator()) . ' ' . $currency . '</p>';
                        echo '</div>';

                        echo '<div class="epay-info-overview">';
                        echo    '<p>';
                        _e('Captured amount', 'bambora-online-classic');
                        echo    ':</p>';
                        echo    '<p>' . Epay_Helper::convert_price_from_minorunits($transaction->transactionInformation->capturedamount, $minorunits, wc_get_price_decimal_separator(), wc_get_price_thousand_separator()) . ' ' . $currency . '</p>';
                        echo '</div>';

                        echo '<div class="epay-info-overview">';
                        echo    '<p>';
                        _e('Credited amount', 'bambora-online-classic');
                        echo    ':</p>';
                        echo    '<p>' . Epay_Helper::convert_price_from_minorunits($transaction->transactionInformation->creditedamount, $minorunits, wc_get_price_decimal_separator(), wc_get_price_thousand_separator()) . ' ' . $currency . '</p>';
                        echo '</div>';

                        echo '</div>';

                        if ($transaction->transactionInformation->status == 'PAYMENT_NEW') {
                            echo '<div class="epay-input-group">';
                            echo '<div class="epay-input-group-currency">' . $currency . '</div><input type="text" value="' . Epay_Helper::convert_price_from_minorunits(($transaction->transactionInformation->authamount - $transaction->transactionInformation->capturedamount), $minorunits, wc_get_price_decimal_separator(), '') . '" id="epay_amount" name="epay_amount" />';
                            echo '</div>';
                            echo '<div class="epay-action">';
                            echo '<a class="button capture" onclick="javascript:location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=capture') . '&amount=\' + document.getElementById(\'epay_amount\').value">';
                            _e('Capture', 'bambora-online-classic');
                            echo '</a>';
                            echo '</div>';
                            if (! $transaction->transactionInformation->capturedamount) {
                                echo '<div class="epay-action">';
                                echo '<a class="button delete"  onclick="javascript: (confirm(\'' . __('Are you sure you want to delete?', 'bambora-online-classic') . '\') ? (location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=delete') . '\') : (false));">';
                                _e('Delete', 'bambora-online-classic');
                                echo '</a>';
                                echo '</div>';
                            }
                        } elseif ($transaction->transactionInformation->status == 'PAYMENT_CAPTURED' && $transaction->transactionInformation->creditedamount == 0) {
                            echo '<div class="epay-input-group">';
                            echo '<div class="epay-input-group-currency">' . $currency . '</div><input type="text" value="' . Epay_Helper::convert_price_from_minorunits($transaction->transactionInformation->capturedamount, $minorunits, wc_get_price_decimal_separator(), '') . '" id="epay_credit_amount" name="epay_credit_amount" />';
                            echo '</div>';
                            echo '<div class="epay-action">';
                            echo '<a class="button credit" onclick="javascript: (confirm(\'' . __('Are you sure you want to credit?', 'bambora-online-classic') . '\') ? (location.href=\'' . admin_url('post.php?post=' . $post->ID . '&action=edit&epay_action=credit') . '&amount=\' + document.getElementById(\'epay_credit_amount\').value) : (false));">';
                            _e('Credit', 'bambora-online-classic');
                            echo '</a>';
                            echo '</div>';
                        }

                        $history_array = $transaction->transactionInformation->history->TransactionHistoryInfo;

                        if (! array_key_exists(0, $transaction->transactionInformation->history->TransactionHistoryInfo)) {
                            $history_array = array( $transaction->transactionInformation->history->TransactionHistoryInfo );
                        }
                        if (count($history_array) > 0) {
                            echo '<h4 class="epay-header">';
                            _e('TRANSACTION HISTORY', 'bambora-online-classic');
                            echo '</h4>';
                            echo '<table class="epay-table">';
                            for ($i = 0; $i < count($history_array); $i++) {
                                echo '<tr class="epay-transaction-date"><td>';
                                echo str_replace('T', ' ', $history_array[ $i ]->created);
                                echo '</td></tr><tr class="epay-transaction"><td>';
                                if (strlen($history_array[ $i ]->username) > 0) {
                                    echo($history_array[ $i ]->username . ': ');
                                }
                                echo $history_array[ $i ]->eventMsg;
                                echo '</td></tr>';
                            }
                            echo '</table>';
                        }
                    } else {
                        $order_note = __('Get Transaction action failed', 'bambora-online-classic');
                        if ($transaction->epayresponse != '-1') {
                            $order_note .= ' - ' . $webservice->getEpayError($this->merchant, $transaction->epayresponse);
                        }

                        echo $this->message('error', $order_note);
                    }
                }
                catch (Exception $e) {
                    echo $this->message('error', $e->getMessage());
                }
            } else {
                echo __('No transaction was found.', 'bambora-online-classic');
            }
        }

        private function message($type, $message)
        {
            return '<div id="message" class="' . $type . '">
                <p>' . $message . '</p>
            </div>';
        }

        /**
         * Checks if Woocommerce Subscriptions is enabled or not
         */
        private function woocommerce_subscription_plugin_is_active()
        {
            return class_exists('WC_Subscriptions') && WC_Subscriptions::$name = 'subscription';
        }

        /**
         * Returns the module header
         *
         * @return string
         */
        private function get_module_header_info()
        {
            global $woocommerce;
            $ePayVersion = Bambora_Online_Classic::MODULE_VERSION;
            $woocommerce_version = $woocommerce->version;
            $result = 'WooCommerce/' . $woocommerce_version . ' Module/' . $ePayVersion;
            return $result;
        }

        private function get_bambora_transaction_id($order)
        {
            $order_id = $this->is_woocommerce_3() ? $order->get_id() : $order->id;
            $transaction_id = "";
            if( $this->is_woocommerce_3() ) {
                $transaction_id = $order->get_transaction_id();
                // For backward compability
                if( strlen( $transaction_id ) === 0 ) {
                    $transaction_id = get_post_meta( $order_id, $this::PSP_REFERENCE, true );
                }
            } else {
                $transaction_id = get_post_meta( $order_id, $this::PSP_REFERENCE, true );
            }

            return $transaction_id;
        }

        /**
         * Determines if the current WooCommerce version is >= 3.0.x
         *
         * @return boolean
         */
        private function is_woocommerce_3()
        {
            return version_compare(WC()->version, '3.0', 'ge');
        }
    }

    add_filter('woocommerce_payment_gateways', 'add_bambora_online_classic');
    Bambora_Online_Classic::get_instance()->init_hooks();

    /**
     * Add the Gateway to WooCommerce
     **/
    function add_bambora_online_classic($methods)
    {
        $methods[] = 'Bambora_Online_Classic';
        return $methods;
    }

    $plugin_dir = basename(dirname(__FILE__));
    load_plugin_textdomain('bambora-online-classic', false, $plugin_dir . '/languages');
}
