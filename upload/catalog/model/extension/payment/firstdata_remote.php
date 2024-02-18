<?php
/**
 * Class Firstdata Remote
 *
 * @package Catalog\Model\Extension\Payment
 */
class ModelExtensionPaymentFirstdataRemote extends Model {
	/**
	 * Get Method
	 *
	 * @param array<string, mixed> $address
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address): array {
		$this->load->language('extension/payment/firstdata_remote');

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_firstdata_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

		if (!$this->config->get('payment_firstdata_remote_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$method_data = [
				'code'       => 'firstdata_remote',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_firstdata_remote_sort_order')
			];
		}

		return $method_data;
	}

	/**
	 * Capture Payment
	 *
	 * @param array<string, mixed> $data
	 * @param int                  $order_id
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function capturePayment(array $data, int $order_id): array {
		// Orders
		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($order_id);

		$order_ref = 'API-' . $order_id . '-' . date('Y-m-d-H-i-s') . '-' . mt_rand(10, 500);

		$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);

		if ($this->config->get('payment_firstdata_remote_auto_settle') == 1) {
			$type = 'sale';
		} else {
			$type = 'preAuth';
		}

		$currency = $this->mapCurrency($order_info['currency_code']);

		$token = '';
		$payment_token = '';

		if ($this->config->get('payment_firstdata_remote_card_storage') == 1) {
			if (isset($this->request->post['cc_choice']) && $this->request->post['cc_choice'] != 'new') {
				$payment_token = $this->request->post['cc_choice'];
			} elseif (isset($this->request->post['cc_store']) && $this->request->post['cc_store'] == 1) {
				$token = sha1($this->customer->getId() . '-' . date('Y-m-d-H-i-s') . mt_rand(10, 500));
			}
		}

		$xml = '<?xml version="1.0" encoding="UTF-8"?>';
		$xml .= '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">';
		$xml .= '<SOAP-ENV:Header />';
		$xml .= '<SOAP-ENV:Body>';
		$xml .= '<ipgapi:IPGApiOrderRequest xmlns:v1="http://ipg-online.com/ipgapi/schemas/v1" xmlns:ipgapi="http://ipg-online.com/ipgapi/schemas/ipgapi">';
		$xml .= '<v1:Transaction>';
		$xml .= '<v1:CreditCardTxType>';
		$xml .= '<v1:Type>' . $type . '</v1:Type>';
		$xml .= '</v1:CreditCardTxType>';

		if (empty($payment_token)) {
			$xml .= '<v1:CreditCardData>';
			$xml .= '<v1:CardNumber>' . $data['cc_number'] . '</v1:CardNumber>';
			$xml .= '<v1:ExpMonth>' . $data['cc_expire_date_month'] . '</v1:ExpMonth>';
			$xml .= '<v1:ExpYear>' . $data['cc_expire_date_year'] . '</v1:ExpYear>';
			$xml .= '<v1:CardCodeValue>' . $data['cc_cvv2'] . '</v1:CardCodeValue>';
			$xml .= '</v1:CreditCardData>';
		}

		$xml .= '<v1:Payment>';

		if (!empty($token)) {
			$xml .= '<v1:HostedDataID>' . $token . '</v1:HostedDataID>';
		}

		if (!empty($payment_token)) {
			$xml .= '<v1:HostedDataID>' . $payment_token . '</v1:HostedDataID>';
		}

		$xml .= '<v1:ChargeTotal>' . $amount . '</v1:ChargeTotal>';
		$xml .= '<v1:Currency>' . $currency . '</v1:Currency>';
		$xml .= '</v1:Payment>';

		$xml .= '<v1:TransactionDetails>';
		$xml .= '<v1:OrderId>' . $order_ref . '</v1:OrderId>';
		$xml .= '<v1:Ip>' . $order_info['ip'] . '</v1:Ip>';
		$xml .= '<v1:TransactionOrigin>ECI</v1:TransactionOrigin>';
		$xml .= '<v1:PONumber>OPENCART2.0' . VERSION . '</v1:PONumber>';
		$xml .= '</v1:TransactionDetails>';

		$xml .= '<v1:Billing>';
		$xml .= '<v1:CustomerID>' . (int)$this->customer->getId() . '</v1:CustomerID>';
		$xml .= '<v1:Name>' . $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'] . '</v1:Name>';
		$xml .= '<v1:Company>' . $order_info['payment_company'] . '</v1:Company>';
		$xml .= '<v1:Address1>' . $order_info['payment_address_1'] . '</v1:Address1>';
		$xml .= '<v1:Address2>' . $order_info['payment_address_2'] . '</v1:Address2>';
		$xml .= '<v1:City>' . $order_info['payment_city'] . '</v1:City>';
		$xml .= '<v1:State>' . $order_info['payment_zone'] . '</v1:State>';
		$xml .= '<v1:Zip>' . $order_info['payment_postcode'] . '</v1:Zip>';
		$xml .= '<v1:Country>' . $order_info['payment_iso_code_2'] . '</v1:Country>';
		$xml .= '<v1:Email>' . $order_info['email'] . '</v1:Email>';
		$xml .= '</v1:Billing>';

		$xml .= '<v1:Shipping>';
		$xml .= '<v1:Name>' . $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'] . '</v1:Name>';
		$xml .= '<v1:Address1>' . $order_info['shipping_address_1'] . '</v1:Address1>';
		$xml .= '<v1:Address2>' . $order_info['shipping_address_2'] . '</v1:Address2>';
		$xml .= '<v1:City>' . $order_info['shipping_city'] . '</v1:City>';
		$xml .= '<v1:State>' . $order_info['shipping_zone'] . '</v1:State>';
		$xml .= '<v1:Zip>' . $order_info['shipping_postcode'] . '</v1:Zip>';
		$xml .= '<v1:Country>' . $order_info['shipping_iso_code_2'] . '</v1:Country>';
		$xml .= '</v1:Shipping>';

		$xml .= '</v1:Transaction>';
		$xml .= '</ipgapi:IPGApiOrderRequest>';
		$xml .= '</SOAP-ENV:Body>';
		$xml .= '</SOAP-ENV:Envelope>';

		$xml = simplexml_load_string($this->call($xml));

		$xml->registerXPathNamespace('ipgapi', 'http://ipg-online.com/ipgapi/schemas/ipgapi');
		$xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');

		$fault = $xml->xpath('//soap:Fault');

		$response['fault'] = '';

		if (!empty($fault[0]) && isset($fault[0]->detail)) {
			$response['fault'] = (string)$fault[0]->detail;
		}

		$string = $xml->xpath('//ipgapi:CommercialServiceProvider');
		$response['provider'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:TransactionTime');
		$response['transaction_time'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ProcessorReferenceNumber');
		$response['reference_number'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ProcessorResponseMessage');
		$response['response_message'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ProcessorResponseCode');
		$response['response_code'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ErrorMessage');
		$response['error'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:OrderId');
		$response['order_id'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ApprovalCode');
		$response['approval_code'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:TDate');
		$response['t_date'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:TransactionResult');
		$response['transaction_result'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:PaymentType');
		$response['payment_type'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:Brand');
		$response['brand'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:Country');
		$response['country'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ProcessorReceiptNumber');
		$response['receipt_number'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ProcessorTraceNumber');
		$response['trace_number'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:ProcessorCCVResponse');
		$response['ccv'] = isset($string[0]) ? (string)$string[0] : '';

		$string = $xml->xpath('//ipgapi:AVSResponse');
		$response['avs'] = isset($string[0]) ? (string)$string[0] : '';

		$response['card_number_ref'] = (string)substr($data['cc_number'], -4);

		if (strtoupper($response['transaction_result']) == 'APPROVED' && !empty($token)) {
			$this->storeCard($token, $this->customer->getId(), $response['brand'], $data['cc_expire_date_month'], $data['cc_expire_date_year'], (string)substr($data['cc_number'], -4));
		}

		$this->logger(print_r($response, 1));

		return $response;
	}

	/**
	 * Call
	 *
	 * @param string $xml
	 */
	public function call(string $xml) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, 'https://test.ipg-online.com/ipgapi/services');
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml']);
		curl_setopt($ch, CURLOPT_HTTPAUTH, 'CURLAUTH_BASIC');
		curl_setopt($ch, CURLOPT_USERPWD, $this->config->get('payment_firstdata_remote_user_id') . ':' . $this->config->get('payment_firstdata_remote_password'));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($ch, CURLOPT_CAINFO, $this->config->get('payment_firstdata_remote_ca'));
		curl_setopt($ch, CURLOPT_SSLCERT, $this->config->get('payment_firstdata_remote_certificate'));
		curl_setopt($ch, CURLOPT_SSLKEY, $this->config->get('payment_firstdata_remote_key'));
		curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $this->config->get('payment_firstdata_remote_key_pw'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		//curl_setopt($ch, CURLOPT_STDERR, fopen(DIR_LOGS . '/headers.txt', 'w+'));
		curl_setopt($ch, CURLOPT_VERBOSE, true);

		$response = curl_exec($ch);

		$this->logger('Post data: ' . print_r($this->request->post, 1));
		$this->logger('Request: ' . $xml);
		$this->logger('Curl error #: ' . curl_errno($ch));
		$this->logger('Curl error text: ' . curl_error($ch));
		$this->logger('Curl response info: ' . print_r(curl_getinfo($ch), 1));
		$this->logger('Curl response: ' . $response);

		curl_close($ch);

		return $response;
	}

	/**
	 * Add Order
	 *
	 * @param array<string, mixed> $order_info
	 * @param array<string, mixed> $capture_result
	 *
	 * @return int
	 */
	public function addOrder(array $order_info, array $capture_result): int {
		if ($this->config->get('payment_firstdata_remote_auto_settle') == 1) {
			$settle_status = 1;
		} else {
			$settle_status = 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "firstdata_remote_order` SET `order_id` = '" . (int)$order_info['order_id'] . "', `order_ref` = '" . $this->db->escape($capture_result['order_id']) . "', `authcode` = '" . $this->db->escape($capture_result['approval_code']) . "', `tdate` = '" . $this->db->escape($capture_result['t_date']) . "', `date_added` = NOW(), `date_modified` = NOW(), `capture_status` = '" . (int)$settle_status . "', `currency_code` = '" . $this->db->escape($order_info['currency_code']) . "', `total` = '" . $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false) . "'");

		return $this->db->getLastId();
	}

	/**
	 * Add Transaction
	 *
	 * @param int                  $firstdata_remote_order_id
	 * @param string               $type
	 * @param array<string, mixed> $order_info
	 *
	 * @return void
	 */
	public function addTransaction(int $firstdata_remote_order_id, string $type, array $order_info = []): void {
		if (!empty($order_info)) {
			$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
		} else {
			$amount = 0;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "firstdata_remote_order_transaction` SET `firstdata_remote_order_id` = '" . (int)$firstdata_remote_order_id . "', `date_added` = NOW(), `type` = '" . $this->db->escape($type) . "', `amount` = '" . (float)$amount . "'");
	}

	/**
	 * Logger
	 *
	 * @param string $message
	 *
	 * @return void
	 */
	public function logger(string $message): void {
		if ($this->config->get('payment_firstdata_remote_debug') == 1) {
			// Log
			$log = new \Log('firstdata_remote.log');
			$log->write($message);
		}
	}

	/**
	 * Add History
	 *
	 * @param int    $order_id
	 * @param int    $order_status_id
	 * @param string $comment
	 *
	 * @return void
	 */
	public function addHistory(int $order_id, int $order_status_id, string $comment): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . (int)$order_id . "', `order_status_id` = '" . (int)$order_status_id . "', `notify` = '0', `comment` = '" . $this->db->escape($comment) . "', `date_added` = NOW()");
	}

	/**
	 * Map Currency
	 *
	 * @param string $code
	 *
	 * @return string
	 */
	public function mapCurrency(string $code): string {
		$currency = [
			'GBP' => 826,
			'USD' => 840,
			'EUR' => 978,
		];

		if (array_key_exists($code, $currency)) {
			return $currency[$code];
		} else {
			return '';
		}
	}

	/**
	 * Get Stored Cards
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getStoredCards(): array {
		$customer_id = $this->customer->getId();

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "firstdata_remote_card` WHERE `customer_id` = '" . (int)$customer_id . "'");

		return $query->rows;
	}

	/**
	 * Store Card
	 *
	 * @param string $token
	 * @param int    $customer_id
	 * @param string $type
	 * @param string $month
	 * @param string $year
	 * @param string $digits
	 *
	 * @return void
	 */
	public function storeCard(string $token, int $customer_id, string $type, string $month, string $year, string $digits): void {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "firstdata_remote_card` SET `customer_id` = '" . (int)$customer_id . "', `date_added` = NOW(), `token` = '" . $this->db->escape($token) . "', `card_type` = '" . $this->db->escape($type) . "', `expire_month` = '" . $this->db->escape($month) . "', `expire_year` = '" . $this->db->escape($year) . "', `digits` = '" . $this->db->escape($digits) . "'");
	}
}
