<?php

if (php_sapi_name() !== 'cli') die('only executable from CLI');

require_once 'settings.php';

$q = $dbh->prepare('SELECT * FROM release_sender WHERE found = -1;');
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
	sleep(3);
	$host = $soap_hosts[$row['node']];
	if (!$host) {
		echo "Invalid node\n";
		continue;
	}
	$id = $row['msgid'];
	echo "Fetching $id from $host\n";
	try {
		$client = new SoapClient('https://'.$host.'/remote/?wsdl', [
		    'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
		    'location' => 'https://'.$host.'/remote/',
		    'uri' => 'urn:halon',
		    'login' => $soap_user,
		    'password' => $soap_pass
		    ]);
		$items = $client->mailQueue(array('filter' => 'messageid='.$id.' quarantine='.$quarantine_short, 'offset' => '0', 'limit' => 50));
	} catch (Exception $e) {
		echo $e->getMessage();
		continue;
	}
	if (!isset($items->result->item) || count($items->result->item) < 1) {
		echo "Email not found\n";
		$q2 = $dbh->prepare('UPDATE release_sender SET found=0 WHERE id = :id;');
		$q2->execute([':id' => $row['id']]);
		continue;
	}
	$mail = $items->result->item[0];
	$rpdscore = -1;
	$rpdrefid = '';
	if (isset($mail->msgscore->item))
		foreach ($mail->msgscore->item as $score)
			if ($score->first == 3)
				list ($rpdscore, $rpdrefid) = explode('|', $score->second);
	$q2 = $dbh->prepare('UPDATE release_sender SET found=1,msgfrom=:msgfrom,msgsubject=:msgsubject,msgrpdscore=:msgrpdscore,msgrpdrefid=:msgrpdrefid WHERE id = :id;');
	$q2->execute([
		':id' => $row['id'],
		':msgfrom' => $mail->msgfrom,
		':msgsubject' => $mail->msgsubject,
		':msgrpdscore' => $rpdscore,
		':msgrpdrefid' => $rpdrefid,
	]);
	foreach ($items->result->item as $mail) {
		sleep(3);
               // Move to long-term quarantine
               try {
                       $client = new SoapClient('https://'.$host.'/remote/?wsdl', [
                               'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                               'location' => 'https://'.$host.'/remote/',
                               'uri' => 'urn:halon',
                               'login' => $soap_user,
                               'password' => $soap_pass
                               ]);
                       $items = $client->mailQueueUpdateBulk(array('filter' => 'messageid='.$id.' quarantine='.$quarantine_short, 'fields' => [["first"=>"quarantine", "second"=>$quarantine_long]]));
               } catch (Exception $e) {
                       echo $e->getMessage();
                       continue;
               }
		$token = bin2hex(openssl_random_pseudo_bytes(12));
		$q2 = $dbh->prepare('INSERT INTO release_rcpt (release_id,queueid,msgto,token) VALUES (:id,:queueid,:msgto,:token);');
		$q2->execute([
			':id' => $row['id'],
			':queueid' => $mail->id,
			':msgto' => $mail->msgto,
			':token' => $token,
		]);
		$insertid = $dbh->lastInsertId();
		if ($dbh->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
			// XXX PDO lastInsertId() doesnt work for PostgreSQL
			$q2 = $dbh->prepare('SELECT id FROM release_rcpt WHERE release_id = :id AND queueid = :queueid AND token = :token;');
			$q2->execute([
				':id' => $row['id'],
				':queueid' => $mail->id,
				':token' => $token,
			]);
			$row2 = $q2->fetch(PDO::FETCH_ASSOC);
			$insertid = $row2['id']; 
		}
		mail($mail->msgto, 'Release blocked email', "Hi,\r\n\r\nThe spam filter blocked an email from {$mail->msgfrom}, but the sender has insisted that the email is genuine. To view or release the email, please click this link: https://release.example.com/?id={$insertid}&token={$token}\r\n\r\nThe Example.com support team\r\nhttp://example.com\r\n\r\nThis email message was auto-generated.", $mail_headers);
	}
}

$q = $dbh->prepare('SELECT * FROM release_sender AS r INNER JOIN release_rcpt AS rr ON r.id = rr.release_id WHERE status = 1;');
$q->execute();
while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
	$host = $soap_hosts[$row['node']];
	$id = $row['queueid'];
	echo "Release $id from $host\n";
	try {
		$client = new SoapClient('https://'.$host.'/remote/?wsdl', [
		    'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
		    'location' => 'https://'.$host.'/remote/',
		    'uri' => 'urn:halon',
		    'login' => $soap_user,
		    'password' => $soap_pass
		    ]);
		$ret = $client->mailQueueRetry(['id' => $id]);
	} catch (Exception $e) {
		if ($e->getMessage() == 'queueid not found') {
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
