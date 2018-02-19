<?php

if (!defined('SENDER_FP_RELEASE')) die('File not included.');

header('Content-Type: application/json charset=UTF-8');

if (isset($_POST['page']) && $_POST['page'] == 'report') {
	try {
		if (!isset($_POST['msgid']) || !isset($_POST['node']) || !isset($_POST['comment']) || !isset($_POST['g-recaptcha-response']))
			throw new UIException('Missing argument');

		$url = 'https://www.google.com/recaptcha/api/siteverify';
		$url .= '?secret='.$recaptcha_secret;
		$url .= '&response='.urlencode($_POST['g-recaptcha-response']);
		$url .= '&remoteip='.$_SERVER['REMOTE_ADDR'];
		$res = json_decode(file_get_contents($url), true);
		if ($res['success'] !== true)
			throw new UIException('Recaptcha failed. Please try again.');

		$q = $dbh->prepare('INSERT INTO release_sender (node, msgid, ip, comment) VALUES (:node, :msgid, :ip, :comment);');
		$result = $q->execute([
			':node' => $_POST['node'],
			':msgid' => $_POST['msgid'],
			':ip' => $_SERVER['REMOTE_ADDR'],
			':comment' => $_POST['comment'],
		]);
		if (!$result)
			throw new UIException('Query error.');
	} catch (UIException $e) {
		die(json_encode(['error' => $e->getMessage()]));
	}

	die(json_encode(['status' => 'ok']));
}

if (isset($_POST['page']) && $_POST['page'] == 'release') {
	try {
		if (!isset($_POST['id']) || !isset($_POST['token']))
			throw new UIException('Missing argument.');

		$q = $dbh->prepare('UPDATE release_rcpt SET status = 1 WHERE id=:id AND token=:token;');
		$result = $q->execute([
			':id' => $_POST['id'],
			':token' => $_POST['token'],
		]);
		if (!$result)
			throw new UIException('Query error.');
	} catch (UIException $e) {
		die(json_encode(['error' => $e->getMessage()]));
	}

	die(json_encode(['status' => 'ok']));
}

if (isset($_POST['page']) && $_POST['page'] == 'check') {
	if (isset($_POST['type']) && $_POST['type'] == 'report') {
		try {
			if (!isset($_POST['msgid']) || !isset($_POST['node']))
				throw new UIException('Missing argument.');

			$q = $dbh->prepare('SELECT found FROM release_sender WHERE msgid = :msgid AND node = :node;');
			$q->execute([':msgid' => $_POST['msgid'], ':node' => $_POST['node']]);
			$result = $q->fetch(PDO::FETCH_ASSOC);
			if (!isset($soap_hosts[$_POST['node']]))
				throw new UIException('Invalid node.');

			die(json_encode(['status' => 'ok', 'result' => $result]));
		} catch (UIException $e) {
			die(json_encode(['error' => $e->getMessage()]));
		}
	}
	if (isset($_POST['type']) && $_POST['type'] == 'release') {
		try {
			if (!isset($_POST['id']) || !isset($_POST['token']))
				throw new UIException('Missing argument.');

			$q = $dbh->prepare('SELECT status FROM release_sender AS r INNER JOIN release_rcpt AS rr ON r.id = rr.release_id WHERE rr.id = :id AND token = :token;');
			$q->execute([':id' => $_POST['id'], ':token' => $_POST['token']]);
			$result = $q->fetch(PDO::FETCH_ASSOC);
			if (!$result)
				throw new UIException('Invalid token.');

			die(json_encode(['status' => 'ok', 'result' => $result]));
		} catch (UIException $e) {
			die(json_encode(['error' => $e->getMessage()]));
		}
	}
}

die(json_encode(['error' => 'Unsupported request.']));
