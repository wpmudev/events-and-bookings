(function ($) {


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

$(function () {
	$("#eab_event_close_period").change(close_period_change);
	$("#eab_event_close_period-nolimit").change(nolimit_change);
});

})(jQuery);
