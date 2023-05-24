<?php

namespace Admitad\Api\Exception;

use Admitad\Api\Request;
use Admitad\Api\Response;

class ApiException extends Exception {
	/**
	 * @var Response
	 */
	protected Response $response;

	/**
	 * @var Request
	 */
	protected Request $request;

	const REQUEST_EXISTS = 1100;
	const ORDER_ALLOCATED_TO_YOU = 1200;
	const ORDER_ALLOCATED_TO_ANOTHER_PUBLISHER = 1300;

	const ERRORS = [
		self::REQUEST_EXISTS => 'A request with this order number already exists',
		self::ORDER_ALLOCATED_TO_YOU => 'Order with this Order ID has already been allocated to you',
		self::ORDER_ALLOCATED_TO_ANOTHER_PUBLISHER => 'Order with this Order ID has been allocated to another publisher',
	];

	/**
	 * @throws InvalidResponseException
	 */
	public function isAdvcampaignNotFound(): bool {
		return
			($this->getResponse()->getArrayResult('advcampaign')[0] ?? '') === 'advcampaign not found';
	}

	/**
	 * @throws InvalidResponseException
	 */
	public function __construct($message, Request $request, Response $response) {
		$this->request = $request;
		$this->response = $response;
		$code = 0;

		$nonFieldError = $response->getResult('non_field_errors');

		if($nonFieldError) {
			$code = self::getErrorCode($nonFieldError[0]);
		}

		parent::__construct($message, $code);
	}

	private static function getErrorCode(string $message): int {
		foreach(self::ERRORS as $code => $error) {
			if(strtolower($error) === strtolower(trim($message))) {
				return $code;
			}
		}
		return 0;
	}

	/**
	 * @return Response
	 */
	public function getResponse(): Response {
		return $this->response;
	}

	/**
	 * @param Response $response
	 */
	public function setResponse(Response $response) {
		$this->response = $response;
	}

	/**
	 * @return Request|null
	 */
	public function getRequest(): ?Request {
		return $this->request;
	}

	/**
	 * @param Request $request
	 */
	public function setRequest(Request $request) {
		$this->request = $request;
	}
}
