<?php

if (php_sapi_name() !== 'cli') die('only executable from CLI');

define('BASE', dirname(__FILE__));
require_once BASE.'/settings.php';
require_once BASE.'/vendor/autoload.php';
require_once BASE.'/classes/ARestClient.php';

$rest_options = [];
foreach ($settings['soap_hosts'] as $host => $options) {
	$rest_options[$host] = [
		'id' => $host,
		'host' => $options['address'],
		'username' => $options['username'],
		'password' => $options['password'],
		'tls' => $options['tls']
	];
}

if (empty($rest_options))
	die("Couldn't find any hosts.");

$loader = new Twig_Loader_Filesystem(BASE.'/templates');
$twig = new Twig_Environment($loader);

$q = $dbh->prepare('SELECT * FROM release_sender WHERE found = -1;');
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
	sleep(3);
	$host_options = $rest_options[$row['node']];
	if (!$host_options) {
		echo "Invalid node\n";
		continue;
	}
	$id = $row['msgid'];
	echo "Fetching $id from ".$host_options['host']."\n";
	try {
		$client = new RestClient($host_options);
		$items = $client->operation('/protobuf', 'POST', null, [
			'command' => 'F',
			'program' => 'smtpd',
			'payload' => [
				'conditions' => [
					'ids' => [
						['transaction' => $id]
					]
				]
			]
		])->body->items;
	} catch (RestException $e) {
		echo $e->getMessage();
		continue;
	}
	$items = array_filter($items, function ($email) { global $settings; return 'mailquarantine:'.$email->metadata->{'_quarantineid'} === $settings['quarantine_short']; });
	if (count($items) < 1) {
		echo "Email not found\n";
		$q2 = $dbh->prepare('UPDATE release_sender SET found=0 WHERE id = :id;');
		$q2->execute([':id' => $row['id']]);
		continue;
	}
	$mail = $items[0];
	$rpdscore = -1;
	$rpdrefid = '';
	if (isset($mail->metadata) && isset($mail->metadata->{'_scores.rpd.refid'}) && isset($mail->metadata->{'_scores.rpd.score'})) {
		$rpdscore = $mail->metadata->{'_scores.rpd.score'};
		$rpdrefid = $mail->metadata->{'_scores.rpd.refid'};
	}
	$q2 = $dbh->prepare('UPDATE release_sender SET found=1,msgfrom=:msgfrom,msgsubject=:msgsubject,msgrpdscore=:msgrpdscore,msgrpdrefid=:msgrpdrefid WHERE id = :id;');
	$q2->execute([
		':id' => $row['id'],
		':msgfrom' => isset($mail->sender) ? strtolower($mail->sender->localpart.'@'.$mail->sender->domain) : '',
		':msgsubject' => $mail->subject,
		':msgrpdscore' => $rpdscore,
		':msgrpdrefid' => $rpdrefid,
	]);
	foreach ($items as $mail) {
		sleep(3);
		// Move to long-term quarantine
		try {
			$client = new RestClient($host_options);
			$client->operation('/protobuf', 'POST', null, [
				'command' => 'G',
				'program' => 'smtpd',
				'payload' => [
					'conditions' => [
						'ids' => [$mail->id]
					],
					'metadata' => [
						'_quarantineid' => substr($settings['quarantine_long'], 15)
					]
				]
			]);
		} catch (RestException $e) {
			echo $e->getMessage();
			continue;
		}
		$token = bin2hex(openssl_random_pseudo_bytes(12));
		$q2 = $dbh->prepare('INSERT INTO release_rcpt (release_id,queueid,msgto,token) VALUES (:id,:queueid,:msgto,:token);');
		$q2->execute([
			':id' => $row['id'],
			':queueid' => $mail->id->queue,
			':msgto' => strtolower($mail->recipient->localpart.'@'.$mail->recipient->domain),
			':token' => $token,
		]);
		$insertid = $dbh->lastInsertId();
		if ($dbh->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
			// XXX PDO lastInsertId() doesnt work for PostgreSQL
			$q2 = $dbh->prepare('SELECT id FROM release_rcpt WHERE release_id = :id AND queueid = :queueid AND token = :token;');
			$q2->execute([
				':id' => $row['id'],
				':queueid' => $mail->id->queue,
				':token' => $token,
			]);
			$row2 = $q2->fetch(PDO::FETCH_ASSOC);
			$insertid = $row2['id']; 
		}
		mail(strtolower($mail->recipient->localpart.'@'.$mail->recipient->domain), $settings['mail_template']['subject'], $twig->render('mail.twig', [
			'msgfrom' => isset($mail->sender) ? strtolower($mail->sender->localpart.'@'.$mail->sender->domain) : '',
			'template' => $settings['template'],
			'id' => $insertid,
			'token' => $token
		]), implode("\r\n", $settings['mail_template']['headers']));
	}
}

$q = $dbh->prepare('SELECT * FROM release_sender AS r INNER JOIN release_rcpt AS rr ON r.id = rr.release_id WHERE status = 1;');
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
	$host_options = $rest_options[$row['node']];
	$msgid = $row['msgid'];
	$id = $row['queueid'];
	echo "Release $msgid:$id from ".$host_options['host']."\n";
	try {
		$client = new RestClient($host_options);
		$client->operation('/protobuf', 'POST', null, [
			'command' => 'G',
			'program' => 'smtpd',
			'payload' => [
				'conditions' => [
					'ids' => [
						[
							'transaction' => $msgid,
							'queue' => $id
						]
					]
				],
				'move' => [
					'queue' => 0 // "ACTIVE"
				]
			]
		]);
	} catch (RestException $e) {
		if ($e->getCode() === 404) {
			$q2 = $dbh->prepare('UPDATE release_rcpt SET status = -1 WHERE id = :id;');
			$q2->execute([':id' => $row['id']]);
		} else {
			echo $e->getMessage();
			continue;
		}
	}
	$q2 = $dbh->prepare('UPDATE release_rcpt SET status = 2 WHERE id = :id;');
	$q2->execute([':id' => $row['id']]);
}
