<?php

namespace Admitad\Api;

use Admitad\Api\Exception\ApiException;
use Admitad\Api\Exception\Exception;
use Admitad\Api\Exception\InvalidResponseException;
use Admitad\Api\Exception\InvalidSignedRequestException;
use Buzz\Client\Curl;
use Buzz\Message\RequestInterface;

class Api {
	protected mixed $accessToken;
	protected string $host = 'https://api.admitad.com';
	private $lastRequest;
	private $lastResponse;

	public function __construct($accessToken = null)
	{
		$this->accessToken = $accessToken;
	}

	public function getAccessToken() {
		return $this->accessToken;
	}

	public function setAccessToken($accessToken): static {
		$this->accessToken = $accessToken;
		return $this;
	}

	/**
	 * @throws Exception
	 * @throws ApiException
	 */
	public function authorizeByPassword($clientId, $clientSecret, $scope, $username, $password): Response {
		$query = [
			'client_id' => $clientId,
			'grant_type' => 'client_credentials',
			'username' => $username,
			'password' => $password,
			'scope' => $scope,
		];

		$request = new Request(RequestInterface::METHOD_POST, '/token/');
		$request->setContent(http_build_query($query));
		$request->addHeader('Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret));

		return $this->send(request: $request, useAuth: false);
	}

	public function getAuthorizeUrl($clientId, $redirectUri, $scope, $responseType = 'code'): string {
		return $this->host . '/authorize/?' . http_build_query([
			'client_id' => $clientId,
			'redirect_uri' => $redirectUri,
			'scope' => $scope,
			'response_type' => $responseType,
		]);
	}

	/**
	 * @throws InvalidSignedRequestException
	 */
	public function parseSignedRequest($signedRequest, $clientSecret) {
		if(!$signedRequest || !str_contains($signedRequest, '.')) {
			throw new InvalidSignedRequestException("Invalid signed request " . $signedRequest);
		}

		list ($key, $data) = explode('.', $signedRequest);

		$hash = hash_hmac('sha256', $data, $clientSecret);
		if($hash != $key) {
			throw new InvalidSignedRequestException("Invalid signed request " . $signedRequest);
		}
		return json_decode(base64_decode($data), true);
	}

	/**
	 * @throws Exception
	 * @throws ApiException
	 */
	public function requestAccessToken(string $clientId, string $clientSecret, $code, string $redirectUri): Response {
		$query = [
			'code' => $code,
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'grant_type' => 'authorization_code',
			'redirect_uri' => $redirectUri,
		];

		$request = new Request(RequestInterface::METHOD_POST, '/token/');
		$request->setContent(http_build_query($query));

		return $this->send(request: $request, useAuth: false);
	}

	/**
	 * @throws Exception
	 * @throws ApiException
	 */
	public function refreshToken(string $clientId, string $clientSecret, $refreshToken): Response {
		$query = [
			'refresh_token' => $refreshToken,
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'grant_type' => 'refresh_token',
		];

		$request = new Request(RequestInterface::METHOD_POST, '/token/');
		$request->setContent(http_build_query($query));

		return $this->send(request: $request, useAuth: false);
	}

	/**
	 * @throws ApiException
	 * @throws Exception
	 */
	public function send(Request $request, Response $response = null, array $params = [], bool $useAuth = true): Response {
		if(is_null($response)) {
			$response = new Response();
		}

		if(null === $request->getHost()) {
			$request->setHost($this->host);
		}

		$this->lastRequest = $request;
		$this->lastResponse = $response;

		if($useAuth) {
			if(!$this->accessToken) {
				throw new Exception("Access token not provided");
			}
			$request->addHeader('Authorization: Bearer ' . $this->accessToken);
		}

		$this->createClient()->send($request, $response, empty($params) ? [] : [
			CURLOPT_POSTFIELDS => $params,
		]);

		if(!$response->isSuccessful()) {
			throw new ApiException('Send failed', $request, $response);
		}

		return $response;
	}

	/**
	 * @throws Exception
	 * @throws ApiException
	 */
	public function get(string $resource, array $params = []): Response {
		$resource = $resource . '?' . http_build_query($params);
		$request = new Request(RequestInterface::METHOD_GET, $resource);

		return $this->send($request);
	}

	public function getIterator($method, $params = [], $limit = 200): Iterator {
		return new Iterator($this, $method, $params, $limit);
	}

	/**
	 * @throws ApiException
	 * @throws Exception
	 */
	public function post(string $resource, array $params = []): Response {
		$request = new Request(RequestInterface::METHOD_POST, $resource);
		//$request->addHeader("Accept-Language: en-US;q=0.6,en;q=0.4");
		//$request->addHeader("Content-Language: en-US;q=0.6,en;q=0.4");
		//$request->addHeader("Content-Type: multipart/form-data");

		//$request->setContent(http_build_query($params)); //tolto
		return $this->send($request, null, $params);
	}

	/**
	 * @throws Exception
	 * @throws ApiException
	 */
	public function me(): Response {
		return $this->get('/me/');
	}

	/**
	 * @throws Exception
	 * @throws ApiException
	 */
	public function authorizeClient(string $clientId, string $clientSecret, string $scope): Response {
		$query = [
			'client_id' => $clientId,
			'scope' => $scope,
			'grant_type' => 'client_credentials',
		];

		$request = new Request(RequestInterface::METHOD_POST, '/token/');
		$request->addHeader('Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret));
		$request->setContent(http_build_query($query));
		return $this->send($request, null, [], false);
	}

	/**
	 * @throws Exception
	 * @throws ApiException
	 * @throws InvalidResponseException
	 */
	public function selfAuthorize(string $clientId, string $clientSecret, string $scope): static {
		$response = $this->authorizeClient($clientId, $clientSecret, $scope);
		$accessToken = $response->getResult('access_token');
		$this->setAccessToken($accessToken);
		return $this;
	}

	protected function createClient(): Curl {
		$curl = new Curl();
		$curl->setTimeout(300);
		return $curl;
	}

	public function getLastRequest() {
		return $this->lastRequest;
	}

	public function getLastResponse() {
		return $this->lastResponse;
	}
}
