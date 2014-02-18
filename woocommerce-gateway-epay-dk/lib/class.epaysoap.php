<?php

class epaysoap
{	
	private $pwd = "";
	private $client = null;
	
	function __construct($pwd = "", $subscription = false)
	{
		if($subscription)
			$client = new SoapClient('https://ssl.ditonlinebetalingssystem.dk/remote/subscription.asmx?WSDL');
		else
			$client = new SoapClient('https://ssl.ditonlinebetalingssystem.dk/remote/payment.asmx?WSDL');
		
		$this->pwd = $pwd;
		
		$this->client = $client;
	}
	
	public function authorize($merchantnumber, $subscriptionid, $orderid, $amount, $currency, $instantcapture, $group, $email)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["subscriptionid"] = $subscriptionid;
		$epay_params["orderid"] = $orderid;
		$epay_params["amount"] = $amount;
		$epay_params["currency"] = $currency;
		$epay_params["instantcapture"] = $instantcapture;
		$epay_params["group"] = $group;
		$epay_params["email"] = $email;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["fraud"] = 0;
		$epay_params["transactionid"] = 0;
		$epay_params["pbsresponse"] = "-1";
		$epay_params["epayresponse"] = "-1";
		
		$result = $this->client->authorize($epay_params);
		
		if($result->authorizeResult == true)
			return true;
		else
		{
			if($result->epayresponse != "-1")
				return new WP_Error('broke', $this->getEpayError($merchantnumber, $result->epayresponse));
			elseif($result->pbsresponse != "-1")
				return new WP_Error('broke', $this->getPbsError($merchantnumber, $result->pbsresponse));
			else
				return new WP_Error('broke', 'An unknown error occured');
		}
	}
	
	public function deletesubscription($merchantnumber, $subscriptionid)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["subscriptionid"] = $subscriptionid;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["epayresponse"] = "-1";
		
		$result = $this->client->deletesubscription($epay_params);
		
		if($result->deletesubscriptionResult == true)
			return true;
		else
		{
			if($result->epayresponse != "-1")
				return new WP_Error('broke', $this->getEpayError($merchantnumber, $result->epayresponse));
			else
				return new WP_Error('broke', 'An unknown error occured');
		}	
	}
	
	public function capture($merchantnumber, $transactionid, $amount)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["transactionid"] = $transactionid;
		$epay_params["amount"] = $amount;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["pbsResponse"] = "-1";
		$epay_params["epayresponse"] = "-1";
		
		$result = $this->client->capture($epay_params);
		
		if($result->captureResult == true)
			return true;
		else
		{
			if($result->epayresponse != "-1")
				return new WP_Error('broke', $this->getEpayError($merchantnumber, $result->epayresponse));
			elseif($result->pbsResponse != "-1")
				return new WP_Error('broke', $this->getPbsError($merchantnumber, $result->pbsResponse));
			else
				return new WP_Error('broke', 'An unknown error occured');
		}
	}
	
	public function moveascaptured($merchantnumber, $transactionid)
	{
		return new WP_Error('broke', 'An unknown error occured');
		
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["transactionid"] = $transactionid;
		$epay_params["epayresponse"] = "-1";
		$epay_params["pwd"] = $this->pwd;
		
		$result = $this->client->move_as_captured($epay_params);
		
		if($result->move_as_capturedResult == true)
			return true;
		else
		{
			if($result->epayresponse != "-1")
				return new WP_Error('broke', $this->getEpayError($merchantnumber, $result->epayresponse));
			else
				return new WP_Error('broke', 'An unknown error occured');
		}
	}
	
	public function credit($merchantnumber, $transactionid, $amount)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["transactionid"] = $transactionid;
		$epay_params["amount"] = $amount;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["epayresponse"] = "-1";
		$epay_params["pbsresponse"] = "-1";
		
		$result = $this->client->credit($epay_params);
		
		if($result->creditResult == true)
			return true;
		else
		{
			if($result->epayresponse != "-1")
				return new WP_Error('broke', $this->getEpayError($merchantnumber, $result->epayresponse));
			elseif($result->pbsresponse != "-1")
				return new WP_Error('broke', $this->getPbsError($merchantnumber, $result->pbsresponse));
			else
				return new WP_Error('broke', 'An unknown error occured');
		}
	}
	
	public function delete($merchantnumber, $transactionid)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["transactionid"] = $transactionid;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["epayresponse"] = "-1";
		
		$result = $this->client->delete($epay_params);
		
		if($result->deleteResult == true)
			return true;
		else
		{
			if($result->epayresponse != "-1")
				return new WP_Error('broke', $this->getEpayError($merchantnumber, $result->epayresponse));
			else
				return new WP_Error('broke', 'An unknown error occured');
		}
	}
	
	public function getEpayError($merchantnumber, $epay_response_code)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["language"] = 2;
		$epay_params["epayresponsecode"] = $epay_response_code;
		$epay_params["epayresponse"] = "-1";
		
		$result = $this->client->getEpayError($epay_params);
		
		if($result->getEpayErrorResult == "true")
			return new WP_Error('broke', $result->epayresponsestring);
		else
			return new WP_Error('broke', 'An unknown error occured');
		
	}
	
	public function getPbsError($merchantnumber, $pbs_response_code)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["language"] = 2;
		$epay_params["pbsresponsecode"] = $pbs_response_code;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["epayresponse"] = "-1";
		
		$result = $this->client->getPbsError($epay_params);
		
		if($result->getPbsErrorResult == "true")
			return new WP_Error('broke', $result->pbsresponsestring);
		else
			return new WP_Error('broke', 'An unknown error occured');
	}
	
	public function gettransaction($merchantnumber, $transactionid)
	{
		$epay_params = array();
		$epay_params["merchantnumber"] = $merchantnumber;
		$epay_params["transactionid"] = $transactionid;
		$epay_params["pwd"] = $this->pwd;
		$epay_params["epayresponse"] = "-1";
		
		$result = $this->client->gettransaction($epay_params);
		
		if($result->gettransactionResult == true)
			return $result;
		else
		{
			if($result->epayresponse != "-1")
				return new WP_Error('broke', $this->getEpayError($merchantnumber, $result->epayresponse));
			else
				return new WP_Error('broke', 'An unknown error occured');
		}
	}
}
?>