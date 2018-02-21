<?php

error_reporting(0);
ini_set('display_errors', 0);

$settings['soap_hosts']['se1'] = [
	'address' => 'https://se1.example.com',
	'username' => 'reportfp',
	'password' => 'XXX',
	'tls' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]
];

$settings['soap_hosts']['se2'] = [
	'address' => 'https://se2.example.com',
	'username' => 'reportfp',
	'password' => 'XXX',
	'tls' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]
];

$settings['quarantine_short'] = 'mailquarantine:X';
$settings['quarantine_long'] = 'mailquarantine:X';

$settings['recaptcha_secret'] = 'XXX';
$settings['recaptcha_sitekey'] = 'XXX';

$settings['mail_template']['headers'][] = "Content-type: text/plain; charset=utf-8";
$settings['mail_template']['headers'][] = "From: Example <support@example.com>";
$settings['mail_template']['subject'] = "Release blocked email";

$settings['database']['dsn'] = 'mysql:host=127.0.0.1;dbname=reportfp';
$settings['database']['username'] = 'XXX';
$settings['database']['password'] = 'XXX';

$dbh = new PDO($settings['database']['dsn'], $settings['database']['username'], $settings['database']['password']);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$settings['template']['public-url'] = 'https://example.com/';
$settings['template']['page-name'] = 'Report blocked email';
//$settings['template']['brand-logo'] = '';
//$settings['template']['brand-logo-height'] = '20';
