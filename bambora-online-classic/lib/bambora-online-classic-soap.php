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
class Bambora_Online_Classic_Soap {

	private $pwd = '';
	private $client = null;
	private $isSubscription = false;

	/**
	 * Constructor
	 *
	 * @param mixed $pwd
	 * @param bool  $subscription
	 */
	public function __construct( $pwd = '', $subscription = false ) {
		$this->pwd = $pwd;

		if ( $subscription ) {
			$this->client  = new SoapClient( 'https://ssl.ditonlinebetalingssystem.dk/remote/subscription.asmx?WSDL' );
			$this->isSubscription = $subscription;
		} else {
			$this->client  = new SoapClient( 'https://ssl.ditonlinebetalingssystem.dk/remote/payment.asmx?WSDL' );
		}
	}

	/**
	 * Authorize subscription
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $subscriptionid
	 * @param mixed $orderid
	 * @param mixed $amount
	 * @param mixed $currency
	 * @param mixed $instantcapture
	 * @param mixed $group
	 * @param mixed $email
	 * @return mixed
	 * @throws Exception
	 */
	public function authorize( $merchantnumber, $subscriptionid, $orderid, $amount, $currency, $instantcapture, $group, $email ) {
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['subscriptionid'] = $subscriptionid;
			$epay_params['orderid'] = $orderid;
			$epay_params['amount'] = (string) $amount;
			$epay_params['currency'] = $currency;
			$epay_params['instantcapture'] = $instantcapture;
			$epay_params['group'] = $group;
			$epay_params['email'] = $email;
			$epay_params['pwd'] = $this->pwd;
			$epay_params['fraud'] = 0;
			$epay_params['transactionid'] = 0;
			$epay_params['pbsresponse'] = '-1';
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->authorize( $epay_params );
		} catch ( Exception $ex ) {
			throw $ex;
		}

		return $result;
	}

	/**
	 * Delete subscription
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $subscriptionid
	 * @return mixed
	 * @throws Exception
	 */
	public function delete_subscription( $merchantnumber, $subscriptionid ) {
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['subscriptionid'] = $subscriptionid;
			$epay_params['pwd'] = $this->pwd;
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->deletesubscription( $epay_params );
		} catch (Exception $ex) {
			throw $ex;
		}

		return $result;
	}

	/**
	 * Capture payment
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 * @param mixed $amount
	 * @return mixed
	 * @throws Exception
	 */
	public function capture( $merchantnumber, $transactionid, $amount ) {
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['transactionid'] = $transactionid;
			$epay_params['amount'] = (string) $amount;
			$epay_params['pwd'] = $this->pwd;
			$epay_params['pbsResponse'] = '-1';
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->capture( $epay_params );
		} catch ( Exception $ex ) {
			throw $ex;
		}

		return $result;
	}

	/**
	 * Credit payment
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 * @param mixed $amount
	 * @return mixed
	 * @throws Exception
	 */
	public function refund( $merchantnumber, $transactionid, $amount ) {
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['transactionid'] = $transactionid;
			$epay_params['amount'] = (string) $amount;
			$epay_params['pwd'] = $this->pwd;
			$epay_params['epayresponse'] = '-1';
			$epay_params['pbsresponse'] = '-1';

			$result = $this->client->credit( $epay_params );
		} catch ( Exception $ex ) {
			throw $ex;
		}

		return $result;
	}

	/**
	 * Delete payment
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 * @return mixed
	 * @throws Exception
	 */
	public function delete( $merchantnumber, $transactionid ) {
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['transactionid'] = $transactionid;
			$epay_params['pwd'] = $this->pwd;
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->delete( $epay_params );
		} catch ( Exception $ex ) {
			throw $ex;
		}

		return $result;
	}

	/**
	 * Get an ePay transaction
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $transactionid
	 * @return mixed
	 * @throws Exception
	 */
	public function get_transaction( $merchantnumber, $transactionid ) {
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['transactionid'] = $transactionid;
			$epay_params['pwd'] = $this->pwd;
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->gettransaction( $epay_params );
		} catch ( Exception $ex ) {
			throw $ex;
		}

		return $result;
	}

	/**
	 * Get The ePay error message based on epay response code
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $epay_response_code
	 * @return mixed
	 */
	public function get_epay_error( $merchantnumber, $epay_response_code ) {
		$res = 'Unable to lookup errorcode';
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['pwd'] = $this->pwd;
			$epay_params['language'] = Bambora_Online_Classic_Helper::get_language_code( get_locale() );
			$epay_params['epayresponsecode'] = $epay_response_code;
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->getEpayError( $epay_params );

			if ( $result->getEpayErrorResult == 'true' ) {
				if ( array_key_exists( 'epayresponsestring', $result ) ) {
					$res = $result->epayresponsestring;
				} else {
					$res = $result->epayResponseString;
				}
			}
		} catch ( Exception $ex ) {
			$res .= ' ' . $ex->getMessage();
		}

		return $res;
	}

	/**
	 * Get The PBS error message based on pbs response code
	 *
	 * @param mixed $merchantnumber
	 * @param mixed $pbs_response_code
	 * @return mixed
	 */
	public function get_pbs_error( $merchantnumber, $pbs_response_code ) {
		$res = 'Unable to lookup errorcode';
		try {
			$epay_params = array();
			$epay_params['merchantnumber'] = $merchantnumber;
			$epay_params['language'] = Bambora_Online_Classic_Helper::get_language_code( get_locale() );
			if ( $this->isSubscription ) {
				$epay_params['pbsResponseCode'] = $pbs_response_code;
			} else {
				$epay_params['pbsresponsecode'] = $pbs_response_code;
			}
			$epay_params['pwd'] = $this->pwd;
			$epay_params['epayresponse'] = '-1';

			$result = $this->client->getPbsError( $epay_params );

			if ( $result->getPbsErrorResult == 'true' ) {
				if ( array_key_exists( 'pbsResponeString', $result ) ) {
					$res = $result->pbsResponeString;
				} else {
					$res = $result->pbsresponestring;
				}
			}
		} catch ( Exception $ex ) {
			$res .= ' ' . $ex->getMessage();
		}

		return $res;
	}
}
