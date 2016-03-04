<?php

require_once 'settings.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_GET['apikey'] !== $apikey) die(json_encode(['error' => 'api-key']));

if ($_GET['type'] == 'status') {
	$q = $dbh->prepare('SELECT * FROM release_sender AS r INNER JOIN release_rcpt AS rr ON r.id = rr.release_id WHERE queueid = :queueid AND node = :node;');
	if (!$q->execute([':queueid' => $_GET['queueid'], ':node' => $_GET['node']]))
		die(json_encode(['error' => 'db']));
	$row = $q->fetch(PDO::FETCH_ASSOC);
	if ($row && $row['status'] == 0)
		die(json_encode(['status' => 'keep']));
	else if ($row && $row['status'] > 0)
		die(json_encode(['status' => 'release']));
	else
		die(json_encode(['status' => 'delete']));
}

die(json_encode(['error' => 'invalid-type']));
