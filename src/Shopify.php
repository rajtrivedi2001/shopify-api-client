<?php

namespace Shopify;

use Shopify\Exceptions\ShopifyCurlException as ShopifyCurlException;
use Shopify\Exceptions\ShopifyApiException as ShopifyApiException;

class Shopify
{
	public $shop;
	public $api_key;
	public $password;
	private $last_response_headers = null;

	public function __construct($shop, $api_key, $password)
	{
		$this->shop = $shop;
		$this->api_key = $api_key;
		$this->password = $password;
	}

	public function call($method, $endpoint, $params = array())
	{
		$base_url = "https://{$this->api_key}:{$this->password}@{$this->shop}.myshopify.com/";

		$url = $base_url . ltrim($endpoint, '/');

		$query = in_array($method, array('GET', 'DELETE')) ? $params : array();
		$payload = in_array($method, array('POST', 'PUT')) ? json_encode($params) : array();
		$request_headers = in_array($method, array('POST', 'PUT')) ? array("Content-Type: application/json; charset=utf-8") : array();

		$response = $this->curlHttpApiRequest($method, $url, $query, $payload, $request_headers);
		$response = json_decode($response);

		if (isset($response->errors) || ($this->last_response_headers['http_status_code'] >= 400))
			throw new ShopifyApiException($method, $endpoint, $params, $this->last_response_headers, $response);

		return (is_array($response) and (count($response) > 0)) ? array_shift($response) : $response;
	}

	private function curlHttpApiRequest($method, $url, $query = '', $payload = '', $request_headers = array())
	{
		$url = $this->curlAppendQuery($url, $query);
		$ch = curl_init($url);
		$this->curlSetopts($ch, $method, $payload, $request_headers);
		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);
		if ($errno) throw new ShopifyCurlException($error, $errno);
		list($message_headers, $message_body) = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);
		$this->last_response_headers = $this->curlParseHeaders($message_headers);
		return $message_body;
	}

	private function curlAppendQuery($url, $query)
	{
		if (empty($query)) return $url;
		if (is_array($query)) return "$url?".http_build_query($query);
		else return "$url?$query";
	}

	private function curlSetopts($ch, $method, $payload, $request_headers)
	{
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_USERAGENT, 'ohShopify-php-api-client');
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, $method);
		if (!empty($request_headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
		
		if ($method != 'GET' && !empty($payload))
		{
			if (is_array($payload)) $payload = http_build_query($payload);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $payload);
		}
	}

	private function curlParseHeaders($message_headers)
	{
		$header_lines = preg_split("/\r\n|\n|\r/", $message_headers);
		$headers = array();
		list(, $headers['http_status_code'], $headers['http_status_message']) = explode(' ', trim(array_shift($header_lines)), 3);
		foreach ($header_lines as $header_line)
		{
			list($name, $value) = explode(':', $header_line, 2);
			$name = strtolower($name);
			$headers[$name] = trim($value);
		}
		return $headers;
	}
}