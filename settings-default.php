<?php

error_reporting(0);
ini_set('display_errors', 0);

$soap_hosts['se1'] = [
	'address' => 'https://se1.example.com',
	'username' => 'reportfp',
	'password' => 'XXX',
	'tls' => array('verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false)];

$soap_hosts['se2'] = [
	'address' => 'https://se2.example.com',
	'username' => 'reportfp',
	'password' => 'XXX',
	'tls' => array('verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false)];

$quarantine_short = 'mailquarantine:X';
$quarantine_long = 'mailquarantine:X';

$recaptcha_secret = 'XXX';
$recaptcha_sitekey = 'XXX';

$mail_template['headers'][] = "Content-type: text/plain; charset=utf-8";
$mail_template['headers'][] = "From: Example <support@example.com>";
$mail_template['subject'] = "Release blocked email";

$dsn = 'mysql:host=127.0.0.1;dbname=reportfp';
$db_user = 'XXX';
$db_pass = 'XXX';

$dbh = new PDO($dsn, $db_user, $db_pass);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$template['public-url'] = 'https://example.com/';
$template['page-name'] = 'Report blocked email';
//$template['brand-logo'] = '';
//$template['brand-logo-height'] = '20';
