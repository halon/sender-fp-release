<?php

error_reporting(0);
ini_set('display_errors', 0);

$soap_user = 'reportfp';
$soap_pass = 'XXX';
$soap_hosts = [
	'se1' => 'se1.example.com',
	'se2' => 'se2.example.com'
];

$quarantine_short = 'mailquarantine:X';
$quarantine_long = 'mailquarantine:X';

$recaptcha_secret = 'XXX';
$recaptcha_sitekey = 'XXX';

$mail_headers  = "Content-type: text/plain; charset=utf-8\r\n";
$mail_headers .= "From: Example <support@example.com>";

$dsn = 'mysql:host=127.0.0.1;dbname=reportfp';
$db_user = 'XXX';
$db_pass = 'XXX';

$dbh = new PDO($dsn, $db_user, $db_pass);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
