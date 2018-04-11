<?PHP

/**
 * Class RestClient
 * @package NocksCheckout
 */
class Nocks_RestClient
{

	public $versionHeaders = array();
	protected $apiEndpoint;
	protected $headers;

	public function __construct($apiEndpoint, $merchantApiKey) {
		$this->apiEndpoint = $apiEndpoint;
		$this->headers = array(
			'Accept: application/json',
			'Content-type: application/x-www-form-urlencoded',
			'Authorization: Bearer ' . $merchantApiKey,
			'User-Agent :'.join(' ', $this->versionHeaders),
			'X-Nocks-Client-Info :'. php_uname()
		);
	}

	public function get($endpointUrl, $queryString = null) {
		return $this->request('GET', $endpointUrl, $queryString, null);
	}

	/**
	 * @param   string       $endpointUrl
	 * @param   null         $queryString
	 * @param   array|string $postData
	 *
	 * @return  string
	 */
	public function post($endpointUrl, $queryString = null, $postData = '') {
		if (is_array($postData))
			$postData = json_encode($postData);

		return $this->request('POST', $endpointUrl, $queryString, $postData);
	}

	/**
	 * @param   string       $endpointUrl
	 * @param   null         $queryString
	 * @param   array|string $putData
	 *
	 * @return  string
	 */
	public function put($endpointUrl, $queryString = null, $putData = '') {
		return $this->request('PUT', $endpointUrl, $queryString, $putData);
	}

	/**
	 * @param   string       $endpointUrl
	 * @param   null         $queryString
	 * @param   array|string $postData
	 *
	 * @return  string
	 */
	public function delete($endpointUrl, $queryString = null, $postData = null) {
		return $this->request('DELETE', $endpointUrl, $queryString, $postData);
	}

	/**
	 * generic request executor
	 *
	 * @param   string       $method GET, POST, PUT, DELETE
	 * @param   string       $endpointUrl
	 * @param   array        $queryString
	 * @param   array|string $body
	 *
	 * @return string
	 */
	public function request($method, $endpointUrl, $queryString = null, $body = null) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->apiEndpoint . $endpointUrl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

		if (in_array($method, ['POST', 'PUT'])) {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$body);  //Post Fields
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		$server_output = curl_exec($ch);
		curl_close($ch);
		return $server_output;
	}
}