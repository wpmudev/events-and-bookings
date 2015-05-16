;(function ($, undefined) {


$(function () {
	var locale = $("html").attr("lang");
	$("time[datetime]").each(function () {
		var $time = $(this),
			$parent = $time.closest(".wpmudevevents-date, .eab-has_events"),
			datetime = $time.attr("datetime"),
			$dts = $time.find((
				$parent.is(".eab-has_events")
					? "var.eab-date_format-time,var.eab-date_format-date" // Table dates
					: "var.eab-date_format-time:visible,var.eab-date_format-date:visible" // Regular dates
			))
		;
		if (!$time.length || !datetime || !$dts.length) return true;
		//2015-05-23T10:00:00-0000
	
		if( navigator.userAgent.indexOf("Safari") > -1 ){
			datetime = datetime.split( '-0000' );
			dateValue = datetime[0];
		}
		else{
			dateValue = datetime;	
		}
		
		
		var date = new Date(Date.parse(dateValue));
		if (isNaN(date)) return false;
		$dts.each(function () {
			var $me = $(this),
				old = $me.text()
			;
			$me
				.text((
					$me.is(".eab-date_format-time")
						? date.toLocaleTimeString(locale).split( ' GMT' )[0]
						: date.toLocaleDateString(locale).split( ' GMT' )[0]
				))
				.attr("title", old)
			;
		});

	});
});
})(jQuery);