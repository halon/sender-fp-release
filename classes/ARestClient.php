<?php

require_once dirname(__FILE__).'/RestStreamSocket.php';
require_once dirname(__FILE__).'/RestResponse.php';
require_once dirname(__FILE__).'/RestException.php';

class ARestPromise
{
	private $socket = null;

	public function __construct($socket)
	{
		$this->socket = $socket;
	}

	public function get()
	{
		while (RestStreamSocketPool::Get()->poll(true) && !$this->socket->done());

		$headers = $this->socket->getHeaders();
		$body = json_decode($this->socket->getBody());
		$this->socket = null;

		foreach ($headers as $header) {
			if ('HTTP' === substr($header, 0, 4)) {
				list($version, $code, $message) = explode(' ', $header, 3);
				$code = (integer) $code;
				if ($code >= 400) {
					throw new RestException($code, $body->error ?? $body->message ?? $message, $headers, $body->detail);
				}
				break;
			}
		}

		$response = new StdClass;
		$response->code = $code;
		if ($body) $response->body = $body;
		$response->headers = $headers;

		return $response;
	}
}

class ARestClient
{
	private $id = null;
	private $host = null;
	private $tls = null;
	private $timeout = null;
	private $username = null;
	private $password = null;

	public function __construct($opts = [])
	{
		if (isset($opts['id'])) $this->id = $opts['id'];
		if (isset($opts['host'])) $this->host = $opts['host'];
		if (isset($opts['tls'])) $this->tls = $opts['tls'];
		if (isset($opts['proxy'])) $this->proxy = $opts['proxy'];
		$this->timeout = $opts['timeout'] ?? 5;
		$this->username = $opts['username'] ?? $_SESSION['username'];
		$this->password = $opts['password'] ?? $_SESSION['password'];
	}

	public function getID()
	{
		return $this->id;
	}

	public function operation($path, $method = 'GET', $query = null, $body = null)
	{
		$url = parse_url($this->host);
		if (!isset($url['port'])) $url['port'] = ($url['scheme'] === 'https' ? 443 : 80);

		$uri = "api/1.2.0$path";
		if (isset($query)) {
			$uri .= '?'.http_build_query($query);
		}

		if ($body) $encoded_body = json_encode($body);

		$proxy = $proxy_request = null;
		if ($this->proxy && $url['scheme'] === 'http' && $_ENV['HTTP_PROXY'] != '')
		{
			$proxy = parse_url($_ENV['HTTP_PROXY']);
			$request = "$method http://{$url['host']}:{$url['port']}/$uri HTTP/1.1\r\n";
			$request .= "Proxy-Connection: close\r\n";
		}
		else
		{
			if ($this->proxy && $url['scheme'] === 'https' && $_ENV['HTTPS_PROXY'] != '')
			{
				$proxy = parse_url($_ENV['HTTPS_PROXY']);
				$proxy_request = "CONNECT {$url['host']}:{$url['port']} HTTP/1.1\r\n";
				$proxy_request .= "Host: {$url['host']}:{$url['port']}\r\n";
				$proxy_request .= "Proxy-Connection: close\r\n\r\n";
			}
			$request = "$method /$uri HTTP/1.1\r\n";
			$request .= "Connection: close\r\n";
		}
		$request .= "Host: {$url['host']}:{$url['port']}\r\n";
		$request .= "Content-Type: application/json; charset=utf-8\r\n";
		$request .= "Authorization: Basic ".base64_encode($this->username.':'.$this->password)."\r\n";
		$request .= "X-Forwarded-For: [{$_SERVER['REMOTE_ADDR']}] webui\r\n";
		if ($body) $request .= "Content-Length: ".strlen($encoded_body)."\r\n";
		$request .= "\r\n";
		if ($body) $request .= $encoded_body;

		$socket = new RestStreamSocket(
			$url['scheme'],
			$proxy['host'] ?? $url['host'],
			$proxy['port'] ?? $url['port'],
			$this->timeout,
			$this->tls,
			$request, $proxy_request);

		RestStreamSocketPool::Get()->add($socket);

		return new ARestPromise($socket);
	}
}

class RestClient extends ARestClient
{
	public function operation($path, $method = 'GET', $query = null, $body = null)
	{
		$response = parent::operation($path, $method, $query, $body)->get();
		return new RestResponse($response->code, $response->body, $response->headers);
	}
}
