<?php

/**
 * Class Nocks
 * @package NocksCheckout
 */
class Nocks_Checkout
{
	/* @var Nocks_RestClient $client */
	protected $client;

	// Merchant Profile
	protected $merchant_profile;

	/* @var $apiEndpoint */
	protected $apiEndpoint = "https://api.nocks.com/api/v2/";

	/* @var $domain */
	protected $domain = 'https://nocks.com/';

	public function __construct($merchantApiKey, $merchantProfile) {
		$this->merchant_profile = $merchantProfile;
		$this->client = new Nocks_RestClient($this->apiEndpoint, $merchantApiKey);
		$curl_version = curl_version();
		$this->addVersionString("PHP/" . phpversion());
		$this->addVersionString("cURL/" . $curl_version["version"]);
		$this->addVersionString($curl_version["ssl_version"]);
	}

	public function getMerchants() {
		try {
			$response = $this->client->get('merchant');
			$merchants = array('' => '== Please Select ==');
			$jsonObj = json_decode($response);
			foreach ($jsonObj->data as $merchant) {

				foreach ($merchant->merchant_profiles->data as $profile) {
					$merchants[$profile->uuid] = $merchant->name . " : " . $profile->name;
				}
			}
		} catch (\Exception $e) {
			$merchants[] = "== Error, no API key ==";
		}

		return $merchants;
	}

	public function getPaymentUrl($payment_id) {
		return $this->domain.'payment/url/'.$payment_id;
	}

	public function addVersionString($string) {
		$this->client->versionHeaders[] = $string;
	}

	public function getCurrentRate($currencyCode) {
		$rate = 0;
		$response = $this->client->get('http://api.nocks.com/api/market?call=nlg');
		$response = json_decode($response, true);
		if (isset($response['last'])) {
			$rate = number_format($response['last'], 8);
		}

		return str_replace(',', '', $rate);
	}

	public function round_up ( $value, $precision ) {
		$pow = pow ( 10, $precision );
		return ( ceil ( $pow * $value ) + ceil ( $pow * $value - ceil ( $pow * $value ) ) ) / $pow;
	}

	public function createTransaction($data) {
		$amount = $data['amount'];
		$currency = $data['currency'];
		$callback_url = $data['webhookUrl'];
		$return_url = $data['redirectUrl'];

		$post = array(
			"merchant_profile" => $this->merchant_profile,
			"source_currency"  => "NLG",
			"amount"           => array(
				"amount"   => (string)($currency==="NLG"?$this->round_up($amount, 8):$this->round_up($amount,2)),
				"currency" => $currency
			),
			"payment_method"   => array(
				"method" => "gulden",
			),
			"metadata"         => array(),
			"redirect_url"     => $return_url,
			"callback_url"     => $callback_url,
			"locale"           => "nl_NL"
		);

		$response = ($this->client->post('transaction', null, $post));
		$transaction = json_decode($response, true);

		if (isset($transaction['data']['payments']["data"][0]['uuid'])) {
			return $transaction;

		} else {
			return false;
		}

	}

	public function getTransaction($uuid) {
		$response = ($this->client->get('transaction/'.$uuid, null));
		$transaction = json_decode($response, true);

		return new Nocks_Transaction($transaction);
	}

	/**
	 * Calculates the price for the transaction
	 *
	 * @param $target_currency
	 * @param $amount
	 * @param $source_currency
	 * @return array|int
	 */
	public function calculatePrice($target_currency, $amount, $source_currency) {
		$data = array(
			'source_currency'  => $source_currency,
			'target_currency'  => $target_currency,
			'merchant_profile' => $this->merchant_profile,
			'amount'           => array(
				"amount"   => (string)$amount,
				"currency" => $target_currency
			),
			'payment_method'   => array("method" => "gulden")
		);

		$price = $this->client->post('transaction/quote', null, $data);
		$price = json_decode($price, true);

		if (isset($price['data']) && isset($price['data'])) {
			return $price['data'];
		}

		return 0;
	}

}