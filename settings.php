<?php

error_reporting(0);
ini_set('display_errors', 0);

$soap_user = 'reportfp';
$soap_pass = 'XXX';
$soap_hosts = [
	'se1' => 'se1.example.com',
	...
];

$recaptcha_secret = 'XXX';
$recaptcha_sitekey = 'XXX';

$apikey = 'secret';

$mail_headers  = "Content-type: text/plain; charset=utf-8\r\n";
$mail_headers .= "From: Example <support@example.com>";

$dbh = new PDO('pgsql:host=127.0.0.1;dbname=XXX;user=XXX;password=XXX');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
