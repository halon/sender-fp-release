var lastState = null;
var currentState = 0;

$(document).ready(function() {

	currentState = $('#state').val();

	if (currentState != 0) {
		disableForm();
	}

	if (currentState == 1) {
		checkStatus();
	}

	$('#release-form').submit(function() {
		disableForm();

		var id = $('#id').val();
		var token = $('#token').val();

		$.post('?xhr', {
			'page': 'release',
			'id': id,
			'token': token
		}, function(data) {
			if (data.error) {
				showError(data.error);
				enableForm();
				return;
			}
			currentState = 1;
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
	if (currentState == 1)
		setTimeout(checkStatus, 1000);

	if (lastState != currentState) {
		if (currentState == 1) {
			setFormState('info');
		} else if (currentState == 2) {
			setFormState('success');
		}
	}
	lastState = currentState;
}

function fetchStatus () {
	var id = $('#id').val();
	var token = $('#token').val();

	$.post('?xhr', {
		'page': 'check',
		'type': 'release',
		'id': id,
		'token': token
	}).done(function(data) {
		if (data.error) {
			showError(data.error);
		} else {
			currentState = data.result.status;
		}
	}).fail(function(jqXHR, textStatus, errorThrown) {
		showError(errorThrown);
	});
}

function setFormState (state) {
	$('#release-card').removeClass('border-success border-info border-warning');
	$('#release-card').addClass('border-' + state);

	$('#release-footer').removeClass('alert-success alert-info alert-warning');
	$('#release-footer').addClass('alert-' + state);

	$('#btn-release').removeClass('btn-success btn-info btn-warning');
	$('#btn-release').addClass('btn-' + state);

	switch (state) {
		case 'info':
			$('#btn-sending-label').prop('hidden', false);
			$('#btn-release-label').prop('hidden', true);

			$('#status-warning').prop('hidden', true);
			$('#status-sending').prop('hidden', false);
			$('#status-success').prop('hidden', true);
			break;
		case 'success':
			$('#btn-sending-label').prop('hidden', true);
			$('#btn-release-label').prop('hidden', false);

			$('#status-warning').prop('hidden', true);
			$('#status-sending').prop('hidden', true);
			$('#status-success').prop('hidden', false);
			break;
	}
}

function disableForm () {
	$('#btn-release').prop('disabled', true);
}

function enableForm () {
	$('#btn-release').prop('disabled', false);
}

function showError ($msg) {
	$('#error').prop('hidden', false);
	$('#error-msg').html('<strong>Error:</strong> ' + $msg);
}
