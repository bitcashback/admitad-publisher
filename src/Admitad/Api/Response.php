<?php

namespace Admitad\Api;

use Admitad\Api\Exception\InvalidResponseException;

class Response extends \Buzz\Message\Response
{
	private array $arrayResult = [];
	private mixed $result = null;

	/**
	 * @throws InvalidResponseException
	 */
	public function getResult($field = null) {

		if (null === $this->result) {
			$this->result = new Model($this->getArrayResult());
		}

		if (null !== $field) {
			if (null !== $this->result && isset($this->result[$field])) {
				return $this->result[$field];
			}
			return null;
		}

		return $this->result;
	}

	/**
	 * @throws InvalidResponseException
	 */
	public function getArrayResult($field = null): array {

		$result = json_decode($this->getContent(), true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new InvalidResponseException($this->getContent());
		}

		$this->arrayResult = $result;

		if(null !== $field && isset($this->arrayResult[$field])) {
			return $this->arrayResult[$field];
		}

		return $this->arrayResult;
	}

	/**
	 * @throws InvalidResponseException
	 */
	public function getError()
	{
		return $this->getResult('error');
	}

	/**
	 * @throws InvalidResponseException
	 */
	public function getErrorDescription()
	{
		return $this->getResult('error_description');
	}

	/**
	 * @throws InvalidResponseException
	 */
	public function getErrorCode()
	{
		return $this->getResult('error_code');
	}
}
