<?php

define('BASE', dirname(__FILE__));
require_once BASE.'/settings.php';
require_once BASE.'/vendor/autoload.php';

class UIException extends Exception { }

try {
	if (isset($_POST['report'])) {
		$url = 'https://www.google.com/recaptcha/api/siteverify';
		$url .= '?secret='.$recaptcha_secret;
		$url .= '&response='.urlencode($_POST['g-recaptcha-response']);
		$url .= '&remoteip='.$_SERVER['REMOTE_ADDR'];
		$res = json_decode(file_get_contents($url), true);
		if ($res['success'] !== true)
			throw new UIException('Click the reCAPTCHA checkbox to continue: '.implode(', ', $res['error-codes']));
		$q = $dbh->prepare('INSERT INTO release_sender (node, msgid, ip, comment) VALUES (:node, :msgid, :ip, :comment);');
		$ret = $q->execute([
			':node' => $_GET['node'],
			':msgid' => $_GET['msgid'],
			':ip' => $_SERVER['REMOTE_ADDR'],
			':comment' => $_POST['comment'],
		]);
		if (!$ret)
			throw new UIException('Query failed');
		header('Location: '.$_SERVER['REQUEST_URI']);
		die();
	}
	if (isset($_POST['release'])) {
		$q = $dbh->prepare('UPDATE release_rcpt SET status = 1 WHERE id=:id AND token=:token;');
		$ret = $q->execute([
			':id' => $_GET['id'],
			':token' => $_GET['token'],
		]);
		if (!$ret)
			throw new UIException('Query failed');
		header('Location: '.$_SERVER['REQUEST_URI']);
		die();
	}
} catch (UIException $e) {
	$error = $e->getMessage();
}

try {
	if (isset($_GET['token']) && isset($_GET['id'])) {
		$page = 'release';
		$q = $dbh->prepare('SELECT * FROM release_sender AS r INNER JOIN release_rcpt AS rr ON r.id = rr.release_id WHERE rr.id = :id AND token = :token;');
		$q->execute([':id' => $_GET['id'], ':token' => $_GET['token']]);
		$result = $q->fetch(PDO::FETCH_ASSOC);
		if (!$result)
			throw new UIException('Invalid token.');
	
		$release['rpdscores'] = [-1=>'n/a', 0=>'unknown',10=>'suspect',40=>'valid-bulk',50=>'bulk',100=>'spam'];
		$release['result'] = $result;
	} else if (isset($_GET['msgid']) && isset($_GET['node'])) {
		$page = 'report';
		$q = $dbh->prepare('SELECT * FROM release_sender WHERE msgid = :msgid AND node = :node;');
		$q->execute([':msgid' => $_GET['msgid'], ':node' => $_GET['node']]);
		$result = $q->fetch(PDO::FETCH_ASSOC);
		if (!isset($soap_hosts[$_GET['node']]))
			throw new UIException('Invalid node "'.$_GET['node'].'", please check your link.');
		if (!$result)
			throw new UIException('Invalid link.');

		$report['comment'] = (isset($_POST['comment'])) ? $_POST['comment'] : null;
		$report['recaptcha_sitekey'] = $recaptcha_sitekey;
		$report['result'] = $result;
	} else { 
		throw new UIException('Invalid link.');
	}
} catch (UIException $e) { 
	$page = 'base';	
	$error = $e->getMessage();
}

$loader = new Twig_Loader_Filesystem(BASE.'/templates');
$twig = new Twig_Environment($loader);
echo $twig->render($page.'.twig', [
	'template' => isset($template) ? $template : [],
	'error' => isset($error) ? $error : null,
	'release' => isset($release) ? $release : null,
	'report' => isset($report) ? $report : null
]);

?>
