(function ($) {

$(function () {
	$("table.eab-upcoming_calendar_widget").each(function () {
		var $tbl = $(this);
		$tbl.find("tbody a").click(function () {
			var $a = $(this);
			var $el = $a.parents('td').find(".wdpmudevevents-upcoming_calendar_widget-info_wrapper");
			if (!$el.length) return false;
			
			var $out = $("#wpmudevevents-upcoming_calendar_widget-shelf");
			if ($out.length) $out.remove();
			$tbl.after('<div id="wpmudevevents-upcoming_calendar_widget-shelf" style="display:none" />');
			$out = $("#wpmudevevents-upcoming_calendar_widget-shelf");
			if (!$out.length) return false;
			
			$out
				.html($el.html())
				.slideDown('slow')
			;
			
			return false;
		});
	});
});
})(jQuery);
