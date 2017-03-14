<?php
/**
 * Copyright (c) 2017. All rights reserved ePay A/S (a Bambora Company).
 *
 * This program is free software. You are allowed to use the software but NOT allowed to modify the software.
 * It is also not legal to do any changes to the software and distribute it in your own name / brand.
 *
 * All use of the payment modules happens at your own risk. We offer a free test account that you can use to test the module.
 *
 * @author    ePay A/S (a Bambora Company)
 * @copyright Bambora (http://bambora.com) (http://www.epay.dk)
 * @license   ePay A/S (a Bambora Company)
 */
class Epay_Helper {

	public static function get_language_code( $locale = null ) {
		if ( ! isset( $locale ) ) {
			$locale = get_locale();
		}

		$languageArray = array(
			'da_DK' => '1',
			'de_CH' => '7',
			'de_DE' => '7',
			'en_AU' => '2',
			'en_GB' => '2',
			'en_NZ' => '2',
			'en_US' => '2',
			'sv_SE' => '3',
			'nb_NO' => '4',
			'nn_NO' => '4',
			);

		return key_exists( $locale, $languageArray ) ? $languageArray[ $locale ] : '2';
	}

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
			return $isoCodeArray[ strtoupper( $code ) ];
		}

		return array_search( strtoupper( $code ), $isoCodeArray );
	}

	/**
	 * Convert an amount to minorunits
	 *
	 * @param $amount
	 * @param $minorUnits
	 * @param $defaultMinorUnits = 2
	 * @return int
	 */
	public static function convert_price_to_minorunits( $amount, $minorUnits ) {
		if ( $amount == '' || $amount == null ) {
			return 0;
		}

		return $amount * pow( 10, $minorUnits );
	}

	/**
	 * Convert an amount from minorunits
	 *
	 * @param $amount
	 * @param $minorUnits
	 * @param $defaultMinorUnits = 2
	 * @return string
	 */
	public static function convert_price_from_minorunits( $amount, $minorUnits, $decimalSeperator = '.', $thousand_separator = '' ) {
		if ( $amount == '' || $amount == null ) {
			return 0;
		}

		return number_format( $amount / pow( 10, $minorUnits ), $minorUnits, $decimalSeperator, $thousand_separator );
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

		return key_exists( $currencyCode, $currencyArray ) ? $currencyArray[ $currencyCode ] : 2;
	}

}