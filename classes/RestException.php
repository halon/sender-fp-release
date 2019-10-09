<?php

class RestException extends Exception
{
	private $headers = null;

	public function __construct($code, $message = null, $headers = [], $detail = null, Exception $previous = null)
	{
		$this->headers = $headers;
		$this->detail = $detail;

		$errors = [
			400 => 'Bad Request',
			401 => 'Unauthorized',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			500 => 'Internal Server Error'
		];

		if (!$message && isset($errors[$code])) {
			$message = $errors[$code];
		}

		parent::__construct($message, $code, $previous);
	}
	
	public function getHeaders()
	{
		return $this->headers;
	}

	public function getDetail()
	{
		return $this->detail;
	}
}
