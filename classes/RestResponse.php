<?php

class RestResponse
{
	public $code = null;
	public $body = null;
	public $headers = null;

	public function __construct($code, $body = null, $headers = [])
	{
		$this->code = $code;
		$this->body = $body;
		$this->headers = $headers;
	}

	public function get()
	{
		$response = new StdClass;
		$response->code = $this->code;
		if (isset($this->body)) $response->body = $this->body;
		$response->headers = $this->headers;
		return $response;
	}
}