;(function ($) {

function create_interface (e) {
	if (e && e.preventDefault) e.preventDefault();
	
	if ($("#eab-rsvps-rsvp_with_email-wrapper").length) {
		$("#eab-rsvps-rsvp_with_email-wrapper").remove();
	}
	var $me = $(this),
		text = $me.text()
	;
	$me.parents('.wpmudevevents-buttons').append('<div id="eab-rsvps-rsvp_with_email-wrapper" />');
	var $root = $("#eab-rsvps-rsvp_with_email-wrapper"),
		post_id = $me.parents(".wpmudevevents-buttons").find('input:hidden[name="event_id"]').val()
	;

	$root.append(
		'<label for="eab-rsvps-rsvp_with_email">' +
			l10nRsvpWithEmail.email +
			'&nbsp;' +
			'<input type="email" id="eab-rsvps-rsvp_with_email" />' +
			'&nbsp;' +
			'<input type="button" id="eab-rsvps-rsvp_with_email-trigger" value="' + text + '" />' +
		'</label>'
	);

	$(document).trigger("eab-api-email_rsvp-form_rendered");

	$("#eab-rsvps-rsvp_with_email-trigger").click(function () {
		do_submit($me.removeClass("active").attr("class"), post_id);
	});
}

function do_submit (selector, post_id) {
	var $email = $("#eab-rsvps-rsvp_with_email"),
		email = $email.val(),
		data
	;
	if (!email) return false;
	data = {
		action: "eab-rsvps-rsvp_with_email",
		email: email,
		location: location.href
	};
	$('.eab-additional-registration-field').each( function() {
		data[$(this).data('key')] = $(this).val();
	});
	$.post(_eab_data.ajax_url, data, function (data) {
		var status = 0;
		try { status = parseInt(data.status, 10); } catch (e) { status = 0; }
		if (status < 1) { // ... handle error
			$email.closest("label").replaceWith('<span>' + data.msg + '</span>');
			return false;
		}
		do_rsvp(selector, post_id);
	}, 'json');
}

function do_rsvp (selector, post_id) {
	// Get form if all went well
	$.post(_eab_data.ajax_url, {
		"action": "eab_get_form",
		"post_id": post_id
	}, function (data) {
		$("body").append('<div id="eab-rsvps-rsvp_with_email-form">' + data + '</div>');
		$("#eab-rsvps-rsvp_with_email-form").find("." + selector).click();
	});
}

$(function () {
	$(
		"a.wpmudevevents-yes-submit, " +
		"a.wpmudevevents-maybe-submit, " +
		"a.wpmudevevents-no-submit"
	).click(create_interface);
});

})(jQuery);
