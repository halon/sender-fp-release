<?php

define('SENDER_FP_RELEASE', true);
define('BASE', dirname(__FILE__));
require_once BASE.'/settings.php';
require_once BASE.'/vendor/autoload.php';

class UIException extends Exception { }

if ($_SERVER['QUERY_STRING'] == 'xhr') {
	require_once BASE.'/xhr.php';
	die();
}

try {
	if (isset($_GET['token']) && isset($_GET['id'])) {
		$page = 'release';
		$javascript[] = 'static/js/release.js';

		$q = $dbh->prepare('SELECT * FROM release_sender AS r INNER JOIN release_rcpt AS rr ON r.id = rr.release_id WHERE rr.id = :id AND token = :token;');
		$q->execute([':id' => $_GET['id'], ':token' => $_GET['token']]);
		$result = $q->fetch(PDO::FETCH_ASSOC);
		if (!$result)
			throw new UIException('Invalid token.');
	
		$release['rpdscores'] = [-1=>'n/a', 0=>'unknown',10=>'suspect',40=>'valid-bulk',50=>'bulk',100=>'spam'];
		$release['result'] = $result;
	} else if (isset($_GET['msgid']) && isset($_GET['node'])) {
		$page = 'report';
		$javascript[] = 'static/js/report.js';

		$q = $dbh->prepare('SELECT found, comment FROM release_sender WHERE msgid = :msgid AND node = :node;');
		$q->execute([':msgid' => $_GET['msgid'], ':node' => $_GET['node']]);
		$result = $q->fetch(PDO::FETCH_ASSOC);
		if (!isset($settings['soap_hosts'][$_GET['node']]))
			throw new UIException('Invalid node "'.$_GET['node'].'", please check your link.');

		if (isset($result['comment']))
			$report['comment'] = $result['comment'];
		else
			$report['comment'] = (isset($_POST['comment'])) ? $_POST['comment'] : null;
		$report['recaptcha_sitekey'] = $settings['recaptcha_sitekey'];
		$report['found'] = (isset($result['found'])) ? $result['found'] : null;
	} else {
		throw new UIException('Invalid link.');
	}
} catch (UIException $e) {
	$page = 'base';	
	$pageerror = $e->getMessage();
}

$loader = new Twig_Loader_Filesystem(BASE.'/templates');
$twig = new Twig_Environment($loader);
echo $twig->render($page.'.twig', [
	'msgid' => isset($_GET['msgid']) ? $_GET['msgid'] : null,
	'node' => isset($_GET['node']) ? $_GET['node'] : null,
	'token' => isset($_GET['token']) ? $_GET['token'] : null,
	'id' => isset($_GET['id']) ? $_GET['id'] : null,
	'javascript' => isset($javascript) ? $javascript : [],
	'template' => isset($settings['template']) ? $settings['template'] : [],
	'pageerror' => isset($pageerror) ? $pageerror : null,
	'release' => isset($release) ? $release : null,
	'report' => isset($report) ? $report : null
]);

?>
