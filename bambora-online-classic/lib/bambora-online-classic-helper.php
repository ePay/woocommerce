<?php
/**
 * Copyright (c) 2017. All rights reserved ePay A/S (a Bambora Company).
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    Bambora Online
 * @copyright Bambora Online (https://bambora.com) (http://www.epay.dk)
 * @license   Bambora Online
 */
class Bambora_Online_Classic_Helper
{
	const ROUND_UP = "round_up";
	const ROUND_DOWN = "round_down";
	const ROUND_DEFAULT = "round_default";
	const BAMBORA_ONLINE_CLASSIC_TRANSACTION_ID_LEGACY = 'Transaction ID';
	const BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID = 'bambora_online_classic_subscription_id';
	const BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID_LEGACY = 'Subscription ID';
    const BAMBORA_ONLINE_CLASSIC_PAYMENT_TYPE_ID = 'Payment Type ID';

	/**
	 * Returns the module header
	 *
	 * @return string
	 */
	public static function get_module_header_info() {
		global $woocommerce;

		$epay_version = BOCLASSIC_VERSION;
		$woocommerce_version = $woocommerce->version;
		$php_version = phpversion();
		$result = "WooCommerce/{$woocommerce_version} Module/{$epay_version} PHP/{$php_version}";
		return $result;
	}

	/**
	 * Create the admin debug section
	 *
	 * @return string
	 */
	public static function create_admin_debug_section() {
		$documentation_link = 'http://woocommerce.wpguiden.dk/en/configuration#709';
		$html = '<h3 class="wc-settings-sub-title">Debug</h3>';
		$html .= sprintf( '<a id="boclassic-admin-documentation" class="button button-primary" href="%s" target="_blank">Module documentation</a>', $documentation_link );
		$html .= sprintf( '<a id="boclassic-admin-log" class="button" href="%s" target="_blank">View debug logs</a>', self::BOCLASSIC_instance()->get_boclassic_logger()->get_admin_link() );

		return $html;
	}


	/**
	 * Checks if Woocommerce Subscriptions is enabled or not
	 */
	public static function woocommerce_subscription_plugin_is_active() {
		return class_exists( 'WC_Subscriptions' ) && WC_Subscriptions::$name = 'subscription';
	}

	/**
	 * Get the subscription for a renewal order
	 *
	 * @param WC_Order $renewal_order
	 * @return WC_Subscription|null
	 */
	public static function get_subscriptions_for_renewal_order( $renewal_order ) {
		if( function_exists( 'wcs_get_subscriptions_for_renewal_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );
			return end( $subscriptions );
		}
		return null;
	}

	/**
	 * Check if order contains switching products
	 *
	 * @param WC_Order|int $order The WC_Order object or ID of a WC_Order order.
	 * @return bool
	 */
	public static function order_contains_switch( $order ) {
		if ( function_exists( 'wcs_order_contains_switch' ) ) {
			return wcs_order_contains_switch( $order );
		}
		return false;
	}

	/**
	 * Check if order contains subscriptions.
	 *
	 * @param  WC_Order|int $order_id
	 * @return bool
	 */
	public static function order_contains_subscription( $order_id ) {
		if( function_exists( 'wcs_order_contains_subscription' ) ) {
			return wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id );
		}
		return false;
	}

	/**
	 * Get subscriptions for order
	 *
	 * @param mixed $order_id
	 * @return array
	 */
	public static function get_subscriptions_for_order( $order_id ) {
		if( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			return wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'any' ) );
		}
		return array();
	}

	/**
	 * Check if an order is of type subscription
	 *
	 * @param object $order
	 * @return boolean
	 */
	public static function order_is_subscription( $order ) {
		if( function_exists( 'wcs_is_subscription' ) ) {
			return wcs_is_subscription( $order );
		}
		return false;
	}

	/**
	 * Get the Bambora Online Classic Subscription id from the order
	 *
	 * @param WC_Subscription $subscription
	 */
	public static function get_bambora_online_classic_subscription_id( $subscription ) {
		$subscription_id = self::is_woocommerce_3() ? $subscription->get_id() : $subscription->id;
		$bambora_subscription_id = get_post_meta( $subscription_id, self::BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID, true );

		//For Legacy
		if( empty( $bambora_subscription_id ) ) {
			$parent_order_id = self::is_woocommerce_3() ? $subscription->get_parent_id() : $subscription->parent_id;
			$bambora_subscription_id = get_post_meta( $parent_order_id, self::BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID_LEGACY, true );
			if( !empty( $bambora_subscription_id ) ) {
				//Transform Legacy to new standards
				update_post_meta( $subscription_id, self::BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID, $bambora_subscription_id );
				delete_post_meta( $parent_order_id, self::BAMBORA_ONLINE_CLASSIC_SUBSCRIPTION_ID_LEGACY );
			}
		}

		return $bambora_subscription_id;
	}

	/**
	 * get the Bambora Online Classic Transaction id from the order
	 *
	 * @param WC_Order $order
	 */
	public static function get_bambora_online_classic_transaction_id( $order ) {
		$transaction_id = $order->get_transaction_id();
		//For Legacy
		if( empty( $transaction_id ) ) {
			$order_id = self::is_woocommerce_3() ? $order->get_id() : $order->id;
			$transaction_id = get_post_meta( $order_id, self::BAMBORA_ONLINE_CLASSIC_TRANSACTION_ID_LEGACY, true );
			if( !empty( $transaction_id ) ) {
				//Transform Legacy to new standards
				delete_post_meta( $order_id, self::BAMBORA_ONLINE_CLASSIC_TRANSACTION_ID_LEGACY );
				$order->set_transaction_id( $transaction_id );
				$order->save();
			}
		}

		return $transaction_id;
	}

	/**
	 * Returns the Callback url
	 *
	 * @param WC_Order $order
	 */
	public static function get_bambora_online_classic_callback_url( $order_id ) {
		$args = array( 'wc-api' => 'Bambora_Online_Classic', 'wcorderid' => $order_id);
		return add_query_arg( $args , site_url( '/' ) );
	}

	/**
     * Returns the Accept url
     *
     * @param WC_Order $order
     */
	public static function get_accept_url( $order ) {
        if ( method_exists( $order, 'get_checkout_order_received_url' ) ) {
			$acceptUrlRaw = $order->get_checkout_order_received_url();
            $acceptUrlTemp = str_replace( '&amp;', '&', $acceptUrlRaw );
            $acceptUrl = str_replace( '&#038', '&', $acceptUrlTemp );

            return $acceptUrl;
		}

		return add_query_arg( 'key', $order->order_key, add_query_arg(
				'order', self::is_woocommerce_3() ? $order->get_id() : $order->id,
				get_permalink( get_option( 'woocommerce_thanks_page_id' ) )
			)
		);
	}

	/**
	 * Returns the Decline url
	 *
	 * @param WC_Order $order
	 */
	public static function get_decline_url( $order ) {
		if ( method_exists( $order, 'get_cancel_order_url' ) ) {
			$declineUrlRaw = $order->get_cancel_order_url();
            $declineUrlTemp = str_replace( '&amp;', '&', $declineUrlRaw );
            $declineUrl = str_replace( '&#038', '&', $declineUrlTemp );
            
            return $declineUrl;
		}

		return add_query_arg( 'key', $order->get_order_key(), add_query_arg(
				array(
					'order' => self::is_woocommerce_3() ? $order->get_id() : $order->id,
					'payment_cancellation' => 'yes',
				),
				get_permalink( get_option( 'woocommerce_cart_page_id' ) ) )
		);
	}

	/**
	 * Create the Bambora Online Classic payment html
	 *
	 * @param mixed $json_data
	 * @return string
	 */
	public static function create_bambora_online_classic_payment_html( $json_data ) {
		$html = '<section>';
		$html .= '<h3>' . __( 'Thank you for using Bambora Online ePay.', 'bambora-online-classic' ) . '</h3>';
		$html .= '<p>' . __( 'Please wait...', 'bambora-online-classic' ) . '</p>';
		$html .= sprintf( '<script type="text/javascript" src="%s" charset="UTF-8"></script>', self::BOCLASSIC_instance()->plugin_url( '/scripts/bambora-online-classic-payment-window.js' ) );
		$html .= sprintf( '<script type="text/javascript" charset="UTF-8">BamboraOnlineClassicPaymentWindow.init(%s);</script>', $json_data );
		$html .= '<script type="text/javascript" src="https://ssl.ditonlinebetalingssystem.dk/integration/ewindow/paymentwindow.js" charset="UTF-8"></script>';
		$html .= '</section>';
		return $html;
	}

	/**
	 * Validate Callback
	 *
	 * @param mixed $params
	 * @param string $md5_key
	 * @param WC_Order $order
	 * @param string $message
	 * @return bool
	 */
	public static function validate_bambora_online_classic_callback_params( $params, $md5_key, &$order, &$message ) {
		// Check for empty params
		if ( ! isset( $params ) || empty( $params ) ) {
			$message = "No GET parameteres supplied to the system";
			return false;
		}

		// Validate woocommerce order!
		if( empty( $params['wcorderid'] ) ) {
			$message = "No WooCommerce Order Id was supplied to the system!";
			return false;
		}

		$order = wc_get_order( $params['wcorderid'] );
		if ( empty( $order ) ) {
			$message = "Could not find order with WooCommerce Order id {$params["wcorderid"]}";
			return false;
		}

		// Check exists transactionid!
		if ( !isset( $params['txnid'] ) ) {
			$message = 'No GET(txnid) was supplied to the system!';
			return false;
		}

		// Validate MD5!
		$var = '';
		if ( strlen( $md5_key ) > 0 ) {
			foreach ( $params as $key => $value ) {
				if ( 'hash' !== $key ) {
					$var .= $value;
				}
			}
			$genstamp = md5( $var . $md5_key );
			if ( ! hash_equals( $genstamp, $params['hash'] ) ) {
				$message = 'Hash validation failed - Please check your MD5 key';
				return false;
			}
		}

		return true;
	}

	/**
	 * Remove all special characters
	 *
	 * @param string $value
	 * @return string
	 */
	public static function json_value_remove_special_characters( $value ) {
		return preg_replace( '/[^\p{Latin}\d ]/u', ' ', $value );
	}

	/**
	 * Return the Bambora Online Classic instance
	 *
	 * @return Bambora_Online_Classic
	 */
	public static function BOCLASSIC_instance() {
		return Bambora_Online_Classic::get_instance();
	}

	/**
	 * Determines if the current WooCommerce version is 3.x.x
	 *
	 * @return boolean
	 */
	public static function is_woocommerce_3() {
		return version_compare( WC()->version, '3.0', '>=' );
	}

    /**
     * Determines if the current WooCommerce version is 3.1 or higher
     *
     * @return boolean
     */
    public static function is_woocommerce_3_1() {
        return version_compare( WC()->version, '3.1', '>=' );
    }

	/**
	 * Converts bool string to int
	 *
	 * @param string $str
	 * @return int
	 */
	public static function yes_no_to_int($str) {
		return $str === 'yes' ? 1 : 0;
	}

	/**
	 * Format date time
	 *
	 * @param string $raw_date_time
	 * @return string
	 */
	public static function format_date_time( $raw_date_time ) {
		$date_format = wc_date_format();
		$time_format = wc_time_format();
		$date_time_format = "{$date_format} - {$time_format}";
		$formated_date = "";
        if ( self::is_woocommerce_3_1() ) {
            $date_time = wc_string_to_datetime( $raw_date_time );
            $formated_date = wc_format_datetime( $date_time, $date_time_format );
        } else {
            $formated_date = date( $date_time_format, strtotime($raw_date_time));
        }

        return $formated_date;
	}

	/**
	 * Get language code id based on name
	 *
	 * @param string $locale
	 * @return string
	 */
	public static function get_language_code( $locale = null ) {
		if ( !isset( $locale ) ) {
			$locale = get_locale();
		}
		$languageArray = array(
			'da_DK' => '1',
			'en_AU' => '2',
			'en_GB' => '2',
			'en_NZ' => '2',
			'en_US' => '2',
			'sv_SE' => '3',
			'nb_NO' => '4',
			'nn_NO' => '4',
			'is-IS' => '6',
			'de_CH' => '7',
			'de_DE' => '7',
			'fi-FI' => '8',
			'es-ES' => '9',
			'fr-FR' => '10',
			'pl-PL' => '11',
			'it-IT' => '12',
			'nl-NL' => '13'
			);

		return key_exists( $locale, $languageArray ) ? $languageArray[$locale] : '2';
	}

	/**
	 * Get the iso code based iso name
	 *
	 * @param string $code
	 * @param boolean $isKey
	 * @return string
	 */
	public static function get_iso_code( $code, $isKey = true ) {
		$isoCodeArray = array(
		 'ADP' => '020', 'AED' => '784', 'AFA' => '004',
		 'ALL' => '008', 'AMD' => '051', 'ANG' => '532',
		 'AOA' => '973', 'ARS' => '032', 'AUD' => '036',
		 'AWG' => '533', 'AZM' => '031', 'BAM' => '052',
		 'BBD' => '004', 'BDT' => '050', 'BGL' => '100',
		 'BGN' => '975', 'BHD' => '048', 'BIF' => '108',
		 'BMD' => '060', 'BND' => '096', 'BOB' => '068',
		 'BOV' => '984', 'BRL' => '986', 'BSD' => '044',
		 'BTN' => '064', 'BWP' => '072', 'BYR' => '974',
		 'BZD' => '084', 'CAD' => '124', 'CDF' => '976',
		 'CHF' => '756', 'CLF' => '990', 'CLP' => '152',
		 'CNY' => '156', 'COP' => '170', 'CRC' => '188',
		 'CUP' => '192', 'CVE' => '132', 'CYP' => '196',
		 'CZK' => '203', 'DJF' => '262', 'DKK' => '208',
		 'DOP' => '214', 'DZD' => '012', 'ECS' => '218',
		 'ECV' => '983', 'EEK' => '233', 'EGP' => '818',
		 'ERN' => '232', 'ETB' => '230', 'EUR' => '978',
		 'FJD' => '242', 'FKP' => '238', 'GBP' => '826',
		 'GEL' => '981', 'GHC' => '288', 'GIP' => '292',
		 'GMD' => '270', 'GNF' => '324', 'GTQ' => '320',
		 'GWP' => '624', 'GYD' => '328', 'HKD' => '344',
		 'HNL' => '340', 'HRK' => '191', 'HTG' => '332',
		 'HUF' => '348', 'IDR' => '360', 'ILS' => '376',
		 'INR' => '356', 'IQD' => '368', 'IRR' => '364',
		 'ISK' => '352', 'JMD' => '388', 'JOD' => '400',
		 'JPY' => '392', 'KES' => '404', 'KGS' => '417',
		 'KHR' => '116', 'KMF' => '174', 'KPW' => '408',
		 'KRW' => '410', 'KWD' => '414', 'KYD' => '136',
		 'KZT' => '398', 'LAK' => '418', 'LBP' => '422',
		 'LKR' => '144', 'LRD' => '430', 'LSL' => '426',
		 'LTL' => '440', 'LVL' => '428', 'LYD' => '434',
		 'MAD' => '504', 'MDL' => '498', 'MGF' => '450',
		 'MKD' => '807', 'MMK' => '104', 'MNT' => '496',
		 'MOP' => '446', 'MRO' => '478', 'MTL' => '470',
		 'MUR' => '480', 'MVR' => '462', 'MWK' => '454',
		 'MXN' => '484', 'MXV' => '979', 'MYR' => '458',
		 'MZM' => '508', 'NAD' => '516', 'NGN' => '566',
		 'NIO' => '558', 'NOK' => '578', 'NPR' => '524',
		 'NZD' => '554', 'OMR' => '512', 'PAB' => '590',
		 'PEN' => '604', 'PGK' => '598', 'PHP' => '608',
		 'PKR' => '586', 'PLN' => '985', 'PYG' => '600',
		 'QAR' => '634', 'ROL' => '642', 'RUB' => '643',
		 'RUR' => '810', 'RWF' => '646', 'SAR' => '682',
		 'SBD' => '090', 'SCR' => '690', 'SDD' => '736',
		 'SEK' => '752', 'SGD' => '702', 'SHP' => '654',
		 'SIT' => '705', 'SKK' => '703', 'SLL' => '694',
		 'SOS' => '706', 'SRG' => '740', 'STD' => '678',
		 'SVC' => '222', 'SYP' => '760', 'SZL' => '748',
		 'THB' => '764', 'TJS' => '972', 'TMM' => '795',
		 'TND' => '788', 'TOP' => '776', 'TPE' => '626',
		 'TRL' => '792', 'TRY' => '949', 'TTD' => '780',
		 'TWD' => '901', 'TZS' => '834', 'UAH' => '980',
		 'UGX' => '800', 'USD' => '840', 'UYU' => '858',
		 'UZS' => '860', 'VEB' => '862', 'VND' => '704',
		 'VUV' => '548', 'XAF' => '950', 'XCD' => '951',
		 'XOF' => '952', 'XPF' => '953', 'YER' => '886',
		 'YUM' => '891', 'ZAR' => '710', 'ZMK' => '894',
		 'ZWD' => '716',
		);

		if ( $isKey ) {
			return $isoCodeArray[strtoupper($code)];
		}

		return array_search( strtoupper( $code ), $isoCodeArray );
	}

	/**
	 * Get Payment type name based on Card id
	 *
	 * @param int $card_id
	 * @return string
	 */
	public static function get_card_name_by_id( $card_id ) {
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

	/**
	 * Convert an amount to minorunits
	 *
	 * @param float $amount
	 * @param int $minorunits
	 * @param string $rounding
	 * @return int
	 */
	public static function convert_price_to_minorunits( $amount, $minorunits, $rounding ) {
		if ($amount == '' || $amount == null) {
			return 0;
		}

		switch ($rounding) {
			case self::ROUND_UP:
				$amount = ceil($amount * pow(10, $minorunits));
				break;
			case self::ROUND_DOWN:
				$amount = floor($amount * pow(10, $minorunits));
				break;
			default:
				$amount = round($amount * pow(10, $minorunits));
				break;
		}

		return $amount;
	}

	/**
	 * Convert an amount from minorunits
	 *
	 * @param float $amount_in_minorunits
	 * @param int $minorunits
	 * @return float
	 */
	public static function convert_price_from_minorunits( $amount_in_minorunits, $minorunits ) {
		if ( empty( $amount_in_minorunits ) || $amount_in_minorunits === 0 ) {
			return 0;
		}

		return (float) ( $amount_in_minorunits / pow( 10, $minorunits ) );
	}

	/**
	 * Return minorunits based on Currency Code
	 *
	 * @param $currencyCode
	 * @return int
	 */
	public static function get_currency_minorunits( $currencyCode ) {
		$currencyArray = array(
		'TTD' => 0, 'KMF' => 0, 'ADP' => 0,
		'TPE' => 0, 'BIF' => 0, 'DJF' => 0,
		'MGF' => 0, 'XPF' => 0, 'GNF' => 0,
		'BYR' => 0, 'PYG' => 0, 'JPY' => 0,
		'CLP' => 0, 'XAF' => 0, 'TRL' => 0,
		'VUV' => 0, 'CLF' => 0, 'KRW' => 0,
		'XOF' => 0, 'RWF' => 0, 'IQD' => 3,
		'TND' => 3, 'BHD' => 3, 'JOD' => 3,
		'OMR' => 3, 'KWD' => 3, 'LYD' => 3,
		);

		return key_exists( $currencyCode, $currencyArray ) ? $currencyArray[$currencyCode] : 2;
	}

	/**
	 * Convert message to HTML
	 *
	 * @param string $type
	 * @param string $message
	 * @return string
	 * */
	public static function message_to_html( $type, $message ) {
		$first_letter = substr( $type, 0, 1 );
		$first_letter_to_upper = strtoupper( $first_letter );
		$type_upper = str_replace( $first_letter, $first_letter_to_upper, $type );

		$html = '<div id="message" class=" '.$type. ' boclassic-message">
						<strong>' . $type_upper . '! </strong>'
				. $message . '</div>';
		return ent2ncr( $html );
	}

    /**
     * Get the Card type group id and Name by card type id
     * @param int $card_type_id
     * @return array
     */
    public static function get_cardtype_groupid_and_name( $card_type_id ) {
        $card_type_array = array(
           1 => array('Dankort', '1'),
           2 => array('Visa/Dankort', '1'),
           3 => array('Visa Electron', '3'),
           4 => array('Mastercard', '4'),
           5 => array('Mastercard', '4'),
           6 => array('Visa Electron', '3'),
           7 => array('JCB', '6'),
           8 => array('Diners Club', '8'),
           9 => array('Maestro', '7'),
           10 => array('American Express', '9'),
           11 => array('Unknown', '15'),
           12 => array('eDankort', '2'),
           13 => array('Diners Club', '8'),
           14 => array('American Express','9'),
           15 => array('Maestro', '7'),
           16 => array('Forbrugsforeningen', '11'),
           17 => array('ewire', '10'),
           18 => array('Visa', '3'),
           19 => array('IKANO Kort', '15'),
           20 => array('Other', '15'),
           21 => array('Nordea e-betaling', '12'),
           22 => array('Danske Netbetalinger', '13'),
           23 => array('BG Netbetalinger', '15'),
           24 => array('LIC/Mastercard', '4'),
           25 => array('LIC/Mastercard', '4'),
           26 => array('PayPal', '14'),
           27 => array('MobilPenge','16'),
           28 => array('Klarna', '17'),
           29 => array('Svea', '18'),
           30 => array('SEB Direktbetalning', '19'),
           31 => array('Nordea SE E-payment', '20'),
           32 => array('Handelsbanken SE Direktbetalningar', '21'),
           33 => array('Swedbank Direktbetalningar', '22'),
           34 => array('ViaBill', '23'),
           35 => array('Beeptify', '24'),
           36 => array('iDeal', '25'),
           37 => array('Oberthur', '26'),
           38 => array('4T', '27'),
           39 => array('Brandts', '28'),
           40 => array('MobilePay','29'),
           41 => array('Resurs', '30'),
           42 => array('Ekspres Bank', '31'),
           43 => array('Swipp', '32'),
           44 => array('Masterpass', '34')
           );

           if($card_type_id == null || !key_exists( $card_type_id, $card_type_array ) ) {
               return array('Unknown', '-1');
           }
           return $card_type_array[$card_type_id];
    }

}
