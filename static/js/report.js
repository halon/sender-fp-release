var lastState = null;
var currentState = -1;

$(document).ready(function() {

	currentState = $('#state').val();

	if (currentState != '') {
		disableForm();
	}

	if (currentState == -1) {
		checkStatus();
	}

	$('#report-form').submit(function() {
		disableForm();

		var node = $('#node').val();
		var msgid = $('#msgid').val();
		var comment = $('#comment').val();
		var recaptcha = $('#g-recaptcha-response').val();

		$.post('?xhr', {
			'page': 'report',
			'node': node,
			'msgid': msgid,
			'comment': comment,
			'g-recaptcha-response': recaptcha
		}, function(data) {
			if (data.error) {
				showError(data.error);
				enableForm();
				return;
			}
			currentState = -1;
			checkStatus();
		}).fail(function(jqXHR, textStatus, errorThrown) {
			showError(errorThrown);
			enableForm();
		});

		return false;
	});
});

function checkStatus () {
	fetchStatus();
	if (currentState == -1)
		setTimeout(checkStatus, 1000);

	if (lastState != currentState) {
		if (currentState == -1) {
			setFormState('info');
		} else if (currentState == 0) {
			setFormState('danger');
		} else if (currentState == 1) {
			setFormState('success');
		}
	}
	lastState = currentState;
}

function fetchStatus () {
	var node = $('#node').val();
	var msgid = $('#msgid').val();

	$.post('?xhr', {
		'page': 'check',
		'type': 'report',
		'node': node,
		'msgid': msgid
	}).done(function(data) {
		if (data.error) {
			showError(data.error);
		} else {
			currentState = data.result.found;
		}
	}).fail(function(jqXHR, textStatus, errorThrown) {
		showError(errorThrown);
	});
}

function setFormState (state) {
	$('#report-card').removeClass('border-success border-info border-danger');
	$('#report-card').addClass('border-' + state);

	$('#report-footer').removeClass('alert-success alert-info alert-danger');
	$('#report-footer').addClass('alert-' + state);

	$('#btn-report').removeClass('btn-success btn-danger btn-info');
	$('#btn-report').addClass('btn-' + state);

	switch (state) {
		case 'info':
			$('#status-loading').prop('hidden', false);
			$('#status-error').prop('hidden', true);
			$('#status-success').prop('hidden', true);

			$('#btn-loading-label').prop('hidden', false);
			$('#btn-notify-label').prop('hidden', true);
			break;
		case 'danger':
			$('#status-loading').prop('hidden', true);
			$('#status-error').prop('hidden', false);
			$('#status-success').prop('hidden', true);

			$('#btn-loading-label').prop('hidden', true);
			$('#btn-notify-label').prop('hidden', false);
			break;
		case 'success':
			$('#status-loading').prop('hidden', true);
			$('#status-error').prop('hidden', true);
			$('#status-success').prop('hidden', false);

			$('#btn-loading-label').prop('hidden', true);
			$('#btn-notify-label').prop('hidden', false);
			break;
	}

	$('#report-footer').prop('hidden', false);
}

function disableForm () {
	$('#comment').prop('disabled', true);
	$('#btn-report').prop('disabled', true);
	$('#recaptcha-div').prop('hidden', true);
}

function enableForm () {
	grecaptcha.reset();
	$('#comment').prop('disabled', false);
	$('#btn-report').prop('disabled', false);
	$('#recaptcha-div').prop('hidden', false);
}

function showError ($msg) {
	$('#error').prop('hidden', false);
	$('#error-msg').html('<strong>Error:</strong> ' + $msg);
}
