<?php

require_once dirname(__FILE__).'/RestException.php';

final class RestStreamSocket
{
	private $socket = null;
	private $context = null;
	private $proxy_request = null;
	private $proxy_response_wait = false;
	private $ssl_handshake = false;
	private $exception = null;
	private $request = null;
	private $response_head = null;
	private $response_body = null;
	private $response_chunk = null;
	private $response_unchunked = '';
	private $response_done = false;
	private $timeout = null;

	public function __construct($scheme, $host, $port, $timeout, $tls, $request, $proxy_request = null)
	{
		$this->timeout = microtime(true) + $timeout;
		$this->request = $request;
		$this->proxy_request = $proxy_request;

		$options = [];
		if ($tls) $options['ssl'] = $tls;
		$this->context = stream_context_create($options);

		if ($scheme === 'http') $scheme = 'tcp';
		if ($scheme === 'https') {
			$this->ssl_handshake = true;
			$scheme = 'tcp';
		}
		if ($scheme === 'unix')
			$socket = $scheme.'://'.$host;
		else
			$socket = $scheme.'://'.$host.':'.$port;

		$retry = false;
		retry:
		$this->socket = @stream_socket_client($socket, $errno, $errstr, null, STREAM_CLIENT_CONNECT|STREAM_CLIENT_ASYNC_CONNECT, $this->context);
		if ($this->socket === false) {
			if ($retry == false and $errno == 65 && strpos($host, ':') === false) { // No route to host
				stream_context_set_option($this->context, 'socket', 'bindto', '0.0.0.0:0');
				$retry = true;
				goto retry;
			}

			$this->setException(new RestException(500, $errstr));
			return;
		}
		stream_set_blocking($this->socket, false);
	}

	public function timeout()
	{
		if ($this->timeout === null)
			return;
		if (microtime(true) > $this->timeout)
			$this->setException(new RestException(500, 'Connection timeout'));
	}

	public function should_write()
	{
		return $this->exception === null && !$this->proxy_response_wait && !empty($this->request);
	}

	public function should_read()
	{
		return $this->should_write() === false && $this->exception === null && $this->response_done === false;
	}

	public function done()
	{
		if ($this->exception)
			throw $this->exception;
		return $this->response_done;
	}

	public function getBody()
	{
		if ($this->exception === null && $this->response_done === false)
			$this->setException(new RestException(500, 'Incomplete read'));
		if ($this->exception)
			throw $this->exception;

		$headers = array_map(function ($header) { return explode(': ', $header, 2); }, explode("\r\n", $this->response_head));
		$gz = false;
		foreach ($headers as $h)
			if ($h[0] == 'Content-Encoding' && $h[1] == 'gzip') $gz = true;

		if ($gz)
			return gzdecode($this->response_body);

		return $this->response_body;
	}

	public function getHeaders()
	{
		return explode("\r\n", $this->response_head);
	}

	public function setException(Exception $e)
	{
		$this->exception = $e;
	}

	public function write()
	{
		if ($this->proxy_request != "")
		{
			$bytes = fwrite($this->socket, $this->proxy_request);
			if ($bytes === false) {
				$this->setException(new RestException(500, 'Proxy-Write failed'));
				return;
			}
			if ($this->timeout !== null && $bytes == 0) {
				$this->setException(new RestException(500, 'Proxy-Connect failed'));
				return;
			}
			$this->timeout = null;
			$this->proxy_request = substr($this->proxy_request, $bytes);
			if ($this->proxy_request == "")
				$this->proxy_response_wait = true;
			return;
		}

		if ($this->ssl_handshake)
		{
			$sslstate = @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
			if ($sslstate === true)
			{
				$this->ssl_handshake = false;
			} elseif ($sslstate === false) {
				$options = stream_context_get_options($this->context);
				if (isset($options['ssl']['peer_fingerprint'])) {
					$fingerprint = openssl_x509_fingerprint($options['ssl']['peer_certificate'], 'md5');
					$fingerprint = strtoupper($fingerprint);
					if ($fingerprint && $options['ssl']['peer_fingerprint'] !== $fingerprint) {
						$fingerprint = implode(':', str_split($fingerprint, 2));
						$this->setException(new RestException(500, 'SSL_FINGERPRINT_MISMATCH', null, $fingerprint));
						return;
					}
				}
				$this->setException(new RestException(500, 'SSL failed'));
				return;
			} else {
				$this->timeout = null;
				return;
			}
		}

		$bytes = fwrite($this->socket, $this->request);
		if ($bytes === false) {
			$this->setException(new RestException(500, 'Write failed'));
			return;
		}
		if ($this->timeout !== null && $bytes == 0) {
			$this->setException(new RestException(500, 'Connect failed'));
			return;
		}
		$this->timeout = null;
		$this->request = substr($this->request, $bytes);
	}

	public function read()
	{
		$data = fread($this->socket, 8192);
		if ($data === false) {
			$this->setException(new RestException(500, 'Read failed'));
			return;
		}
		if ($data == "" && feof($this->socket)) {
			$this->setException(new RestException(500, 'Read failed (EOF)'));
			return;
		}

		if ($this->response_body !== null)
			$this->response_body .= $data;
		else {
			$this->response_head .= $data;
			$s = strpos($this->response_head, "\r\n\r\n");
			if ($s !== false) {
				$this->response_body = substr($this->response_head, $s + 4);
				$this->response_head = substr($this->response_head, 0, $s);
			}
		}

		if ($this->response_body === null)
			return;

		if ($this->proxy_response_wait)
		{
			list($http, $code, $text) = explode(' ', $this->response_head, 3);
			if ($code == '200')
			{
				$this->response_head = null;
				$this->response_body = null;
				$this->response_chunk = null;
				$this->response_unchunked = '';
				$this->response_done = false;
				$this->proxy_response_wait = false;
				return;
			}
		}

		$headers = array_map(function ($header) { return explode(': ', $header, 2); }, explode("\r\n", $this->response_head));
		$l = false;
		$c = false;
		foreach ($headers as $h) {
			if ($h[0] == 'Content-Length') $l = intval($h[1]);
			if ($h[0] == 'Transfer-Encoding' && $h[1] == 'chunked') $c = true;
		}

		if ($l !== false && strlen($this->response_body) == $l) {
			$this->response_done = true;
			fclose($this->socket);
		}
		else if ($c === true) {
			while (true)
			{
				if ($this->response_chunk === null) {
					$c = strpos($this->response_body, "\r\n");
					if ($c === false)
						return;
					$this->response_chunk = hexdec(substr($this->response_body, 0, $c));
					$this->response_body = substr($this->response_body, $c + 2);
				}
				if ($this->response_chunk !== null)
				{
					if (strlen($this->response_body) < $this->response_chunk + 2)
						return;

					$this->response_unchunked .= substr($this->response_body, 0, $this->response_chunk);
					if ($this->response_chunk === 0) {
						$this->response_body = $this->response_unchunked;
						$this->response_unchunked = '';
						$this->response_done = true;
						$this->response_chunk = null;
						fclose($this->socket);
						return;
					}
					$this->response_body = substr($this->response_body, $this->response_chunk + 2);
					$this->response_chunk = null;
				}
			}
		}
	}

	public function socket()
	{
		return $this->socket;
	}
}

final class RestStreamSocketPool
{
	private $sockets = [];

	public static function Get()
	{
		static $inst = null;
		if ($inst === null)
			$inst = new RestStreamSocketPool();
		return $inst;
	}

	private function __construct()
	{
	}

	public function add(RestStreamSocket& $sock)
	{
		$this->sockets[$sock->socket()] = $sock;
	}

	public function poll($wait = false)
	{
		foreach ($this->sockets as $s)
			$s->timeout();

		$w = array_map(function ($s) { return $s->socket(); },
				array_filter($this->sockets, function ($s) { return $s->should_write(); }));
		$r = array_map(function ($s) { return $s->socket(); },
				array_filter($this->sockets, function ($s) { return $s->should_read(); }));
		$a = array_map(function ($s) { return $s->socket(); }, $this->sockets);
		foreach (array_diff($a, $w, $r) as $s)
			unset($this->sockets[$s]);
		if (empty($r) && empty($w))
			return false;
		$e = [];
		if (stream_select($r, $w, $e, $wait ? 1 : 0, 0))
		{
			foreach ($w as $stream)
			{
				$socket = $this->sockets[$stream];
				$socket->write();
			}
			foreach ($r as $stream)
			{
				$socket = $this->sockets[$stream];
				$socket->read();
			}
		}
		return !empty($this->sockets);
	}
}
