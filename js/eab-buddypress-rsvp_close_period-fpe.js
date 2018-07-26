(function ($) {

function append_meta_data (e, request) {
	request['eab-ecp_close_period'] = $("#eab_event_close_period").val();
}

function close_period_change () {
	var $close_period = $("#eab_event_close_period");
	var $nolimit = $("#eab_event_close_period-nolimit");
	var cp = parseInt($close_period.val(), 10);
	cp = cp ? cp : 0;
	
	if (cp > 0) {
		$nolimit.attr("checked", false);
	} else {
		$nolimit.attr("checked", true);
	}
}

function nolimit_change () {
	var $close_period = $("#eab_event_close_period");
	var $nolimit = $("#eab_event_close_period-nolimit");
	
	if ($nolimit.is(":checked")) return $close_period.val(0);
	else return $close_period.focus();
}

$(document).bind('eab-events-fpe-close_period_save_request', append_meta_data);
$(function () {
	$("#eab_event_close_period").change(close_period_change);
	$("#eab_event_close_period-nolimit").change(nolimit_change);
});
	
})(jQuery);
