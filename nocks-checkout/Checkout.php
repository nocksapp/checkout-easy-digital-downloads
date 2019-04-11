<?php

/**
 * Class Nocks
 * @package NocksCheckout
 */
class Nocks_Checkout
{
	/* @var Nocks_RestClient $client */
	protected $client;

	protected $merchantApiKey;

	protected $testMode;

	// Merchant Profile
	protected $merchant_profile;

	public function __construct($merchantApiKey, $merchantProfile, $testMode = null) {
		$this->merchant_profile = $merchantProfile;
		$this->testMode = $testMode === null ? edd_is_test_mode() : $testMode;

		$this->merchantApiKey = $merchantApiKey;
		$this->client = new Nocks_RestClient(self::getEndpoint($this->testMode), $this->merchantApiKey);

		$curl_version = curl_version();
		$this->addVersionString("PHP/" . phpversion());
		$this->addVersionString("cURL/" . $curl_version["version"]);
		$this->addVersionString($curl_version["ssl_version"]);
	}

	public static function getEndpoint($testMode = false) {
		return $testMode ? 'https://sandbox.nocks.com/api/v2/' : 'https://api.nocks.com/api/v2/';
	}

	/**
	 * @return array
	 */
	public function getTokenScopes() {
		$endPoint = $this->testMode ? 'https://sandbox.nocks.com/oauth/' : 'https://www.nocks.com/oauth/';

		$client = new Nocks_RestClient($endPoint, $this->merchantApiKey);

		try {
			$response = $client->get('token-scopes');

			return json_decode($response, true);
		} catch (Exception $e) {
			return [];
		}
	}

	/**
	 * @param null $apiKey
	 * @param null $testMode
	 *
	 * @return array
	 */
	public function getMerchants($apiKey = null, $testMode = null) {
		try {
			if ($apiKey !== null && $testMode !== null) {
				$client = new Nocks_RestClient(self::getEndpoint($testMode), $apiKey);
			} else {
				$client = $this->client;
			}

			$response = $client->get('merchant');
			$merchants = [];
			$jsonObj = json_decode($response);
			foreach ($jsonObj->data as $merchant) {
				foreach ($merchant->merchant_profiles->data as $profile) {
					$merchants[$profile->uuid] = $merchant->name . " : " . $profile->name;
				}
			}

			return $merchants;
		} catch (Exception $e) {
			return [];
		}
	}

	public function addVersionString($string) {
		$this->client->versionHeaders[] = $string;
	}

	public function round_up ( $value, $precision ) {
		$pow = pow ( 10, $precision );
		return ( ceil ( $pow * $value ) + ceil ( $pow * $value - ceil ( $pow * $value ) ) ) / $pow;
	}

	/**
	 * @param $data
	 *
	 * @return array|mixed|null|object
	 * @throws Exception
	 */
	public function createTransaction($data) {
		$amount = $data['amount'];
		$currency = $data['currency'];
		$callback_url = $data['webhookUrl'];
		$return_url = $data['redirectUrl'];

		$post = array(
			'merchant_profile' => $this->merchant_profile,
			'amount'           => array(
				'amount'   => (string)($currency==="NLG"?$this->round_up($amount, 8):$this->round_up($amount,2)),
				'currency' => $currency
			),
			'payment_method'   => array(
				'method' => $data['method'],
				'metadata' => [
					'issuer' => $data['issuer'],
				]
			),
			'metadata'         => [
				'nocks_plugin' => 'edd:1.2.0',
				'edd_version' => EDD_VERSION,
			],
			'redirect_url'     => $return_url,
			'callback_url'     => $callback_url,
			'locale'           => Nocks_Helper_Data::getCurrentLocale(),
			'description'      => $data['reference'] . ' - ' . get_bloginfo('name'),
		);

		if ($data['source_currency']) {
			$post['source_currency'] = $data['source_currency'];
		}

		$response = ($this->client->post('transaction', null, $post));
		$transaction = json_decode($response, true);

		return $transaction;
	}

	public function getTransaction($uuid) {
		$response = ($this->client->get('transaction/'.$uuid, null));
		$transaction = json_decode($response, true);

		return new Nocks_Transaction($transaction);
	}

	/**
	 * Get the ideal issuers
	 *
	 * @return array
	 */
	public function getIdealIssuers() {
		$transient_id = Nocks_Helper_Data::getTransientId('issuers_' . ( $this->testMode ? 'test' : 'live' ));
		$cached_issuers = unserialize(get_transient($transient_id));

		if (is_array($cached_issuers)) {
			return $cached_issuers;
		}

		try {
			// Get fresh issuers
			$response = ($this->client->get('settings', null));
			$settings = json_decode($response, true);
			$issuers = $settings['payment_methods']['ideal']['metadata']['issuers'];

			try {
				// Cache for a day
				set_transient($transient_id, serialize($issuers), MINUTE_IN_SECONDS * 60 * 24);
			} catch (Exception $e) {
				return $issuers;
			}

			return $issuers;
		} catch (Exception $e) {
			return [];
		}
	}
}