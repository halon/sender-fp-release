<?php
require_once 'settings.php';

class UIException extends Exception {
}

try {
	if (isset($_POST['report'])) {
		$url = 'https://www.google.com/recaptcha/api/siteverify';
		$url .= '?secret='.$recaptcha_secret;
		$url .= '&response='.urlencode($_POST['g-recaptcha-response']);
		$url .= '&remoteip='.$_SERVER['REMOTE_ADDR'];
		$res = json_decode(file_get_contents($url), true);
		if ($res['success'] !== true)
			throw new UIException('Click the reCAPTCHA checkbox to continue: '.implode($res['error-codes'], ', '));
		$q = $dbh->prepare('INSERT INTO release (node, msgid, ip, comment) VALUES (:node, :msgid, :ip, :comment);');
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

function p($str) {
	echo htmlspecialchars($str);
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Report blocked email</title>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" integrity="sha512-dTfge/zgoMYpP7QbHy4gWMEGsbsdZeCXz7irItjcC3sPUFtf0kuFbDz/ixG7ArTxmDjLXDmezHubeNikyKGVyQ==" crossorigin="anonymous">
		<script src='https://www.google.com/recaptcha/api.js'></script>
	</head>
	<body>
		<nav class="navbar navbar-inverse navbar-static-top" role="navigation">
			<div class="container">
				<!-- Brand and toggle get grouped for better mobile display -->
				<div class="navbar-header">
					<a class="navbar-brand" href="https://example.com">
						<img src="logo.png">
					</a>
				</div>
			</div>
		</nav>
		<div class="container">
		<?php
		try {
		if (isset($_GET['token'])) {
			$q = $dbh->prepare('SELECT * FROM release AS r INNER JOIN release_rcpt AS rr ON r.id = rr.release_id WHERE rr.id = :id AND token = :token;');
			$q->execute([':id' => $_GET['id'], ':token' => $_GET['token']]);
			$row = $q->fetch(PDO::FETCH_ASSOC);
			if (!$row)
				throw new UIException('Invalid token');
			$rpdscores = [-1=>'n/a', 0=>'unknown',10=>'suspect',40=>'valid-bulk',50=>'bulk',100=>'spam'];
		?>
		<div class="row">
			<div class="col-md-6">
				<div class="alert alert-warning">
					<strong>Warning!</strong>
					This is an email which was blocked
					by an anti-spam/virus filter. Just like any other email,
					it may contain malicious content.
				</div>
				<?php if ($row['status'] == 2) { ?>
				<div class="alert alert-success"><strong>Done!</strong> The email has been released, and is on its way to you.</div>
				<?php } else if ($row['status'] == 1) { ?>
				<div class="alert alert-info"><strong>Sending...</strong> Please wait while the email is being sent to you.</div>
				<script>setTimeout('location.reload()', 1000);</script>
				<?php } else if ($row['status'] == 0) { ?>
				<div class="panel panel-primary">
					<div class="panel-heading">
						<h3 class="panel-title">Release blocked email</h3>
					</div>
					<div class="panel-body">
						<dl class="dl-horizontal">
							<dt>Sender's comment</dt>
							<dd><?php p($row['comment']) ?></dd>
							<dt>From</dt>
							<dd><?php p($row['msgfrom']) ?></dd>
							<dt>To</dt>
							<dd><?php p($row['msgto']) ?></dd>
							<dt>Subject</dt>
							<dd><?php p($row['msgsubject']) ?></dd>
						</dl>
						<form method="post">
							<button class="btn btn-primary pull-right" name="release" type="submit">
								<span class="glyphicon glyphicon-send"></span>
								Release
							</button>
						</form>
					</div>
				</div>
				<?php } ?>
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3 class="panel-title">
							<a role="button" data-toggle="collapse" href="#collapse_tech">
								Technical information
							</a>
						</h3>
					</div>
					<div id="collapse_tech" class="panel-collapse collapse">
					<div class="panel-body">
						<dl class="dl-horizontal">
							<dt>RPD</dt>
							<dd><?php p($rpdscores[$row['msgrpdscore']]) ?></dd>
							<dt>Reference</dt>
							<dd><?php p($row['msgrpdrefid']) ?></dd>
							<dt>ID</dt>
							<dd><?php p($row['msgid']) ?></dd>
							<dt>Node</dt>
							<dd><?php p($row['node']) ?></dd>
						</dl>
					</div>
					</div>
				</div>
			</div>
			<div class="col-md-6">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3 class="panel-title">What has happened?</h3>
					</div>
					<div class="panel-body small">
						<p>
							You probably came to this page because someone sent
							an email to you, which was blocked (and quarantined)
							by the spam/virus filter.
							The sender was informed about this, and given the
							option to report this to you (after passing a
							<a href="https://en.wikipedia.org/wiki/CAPTCHA">CAPTCHA</a> test).
						</p>
						<p>
							Within 7 days after being sent, you can release this email
							from the quarantine. If you press the "Release" button to the
							left, the email will be sent to you.
							<strong>Please keep in mind that the message, just like any other
							email, could contain malicious content!</strong>
						</p>
						<p>
							This is the complete process:
							<ol>
								<li>Someone (the <em>sender</em>) sends an email to you</li>
								<li>
									The email is blocked, but
									<ul>
										<li>The email is retained on our servers for 1 day</li>
										<li>The sender receives a bounce (notification) with a report link</li>
									</ul>
								</li>
								<li>
									The sender follows the link in the bounce and presses the button, which
									<ul>
										<li>Extends the retention period of the email to 7 days</li>
										<li>Generates an email to you with a link</li>
									</ul>
								</li>
								<li>
									You follow the link in that report, and end up on this page
									<ul>
										<li>If you release the email within 7 days, it will be delivered to you</li>
										<li>If the email was legitimate, we encourage you to report this incident to your administrator (including the text from the technical information panel)</li>
									</ul>
								</li>
							</ol>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
		} else if (isset($_GET['msgid']) && isset($_GET['node'])) {
			$q = $dbh->prepare('SELECT * FROM release WHERE msgid = :msgid AND node = :node;');
			$q->execute([':msgid' => $_GET['msgid'], ':node' => $_GET['node']]);
			$row = $q->fetch(PDO::FETCH_ASSOC);
			if (!isset($soap_hosts[$_GET['node']]))
				throw new UIException('Invalid node "'.$_GET['node'].'", please check your link');
		?>
		<div class="row">
			<div class="col-md-6">
				<?php if (isset($error)) { ?>
				<div class="alert alert-danger"><?php p($error) ?></div>
				<?php } ?>
				<?php if (!$row) { ?>
				<div class="panel panel-success">
					<div class="panel-heading">
						<h3 class="panel-title">Report blocked email</h3>
					</div>
					<div class="panel-body">
						<form method="post">
							<div class="form-group">
								<label for="comment">Comment <span class="text-muted">(visible to the recipient)</span></label>
								<textarea name="comment" class="form-control"><?php if (isset($_POST['comment'])) p($_POST['comment']) ?></textarea>
							</div>
							<button name="report" type="submit" class="btn btn-success pull-right">
								<span class="glyphicon glyphicon-send"></span>
								Notify recipient
							</button>
							<div class="g-recaptcha" data-size="compact" data-sitekey="<?php p($recaptcha_sitekey) ?>"></div>
						</form>
					</div>
				</div>
				<?php } else if ($row['found'] == -1) { ?>
				<div class="alert alert-info"><strong>Loading...</strong> Please wait while the email is being located.</div>
				<script>setTimeout('location.reload()', 1000);</script>
				<?php } else if ($row['found'] == 0) { ?>
				<div class="alert alert-danger"><strong>The email couldn't be found!</strong> It might be more than a day since it was sent; we only store blocked email for 24 hours. Please contact the recipient(s) by some other mean.</div>
				<?php } else if ($row['found'] == 1) { ?>
				<div class="alert alert-success"><strong>Thanks for the report!</strong> The recipient(s) have been notified.</div>
				<?php } ?>
			</div>
			<div class="col-md-6">
				<div class="panel panel-default">
					<div class="panel-heading">
						<h3 class="panel-title">What has happened?</h3>
					</div>
					<div class="panel-body small">
						<p>
							You probably came to this web page because
							your email was blocked by the spam filter,
							and pressed the link in the error report you
							received. <strong>We're very sorry that your
							email was blocked.</strong>
						</p>
						<p>
							Please provide some information about
							what was in your email and press
							the "Notify recipient" button. If the email 
							was sent within 24 hours from now, the recipient(s) will
							receive an email with a link that allows them
							to release your email.
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php } else { throw new UIException('Invalid link'); } ?>
		<?php } catch (UIException $e) { ?>
			<div class="alert alert-warning"><?php p($e->getMessage()) ?></div>
		<?php } ?>
		</div>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js" integrity="sha512-K1qjQ+NcF2TYO/eI3M6v8EiNYZfA95pQumfvcVrTHtwQVDG+aHRqLi/ETn2uB+1JqwYqVG3LIvdm9lj6imS/pQ==" crossorigin="anonymous"></script>
	</body>
</html>
