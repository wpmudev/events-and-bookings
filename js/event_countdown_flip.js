(function ($) {

var cnt = 0;

$(document).on('eab-event_countdown-tick', function (e, $me, sprite) {
	if (!$me.length) return false;
	var $amounts = $me.find(".countdown_amount"),
		height = parseInt($me.attr('data-height'), 10),
		size = parseInt($me.attr('data-size'), 10)
	;
	if (!height || !size) return false;

	$amounts
		.css({
			'display': 'inline-block',
			'height': height,
			'overflow': 'hidden'
		})
		.each(function () {
			var $amount = $(this),
				value = parseInt($amount.text(), 10),
				hundreds = parseInt(value / 100, 10),
				tens = parseInt((value - hundreds*100) / 10, 10),
				ones = value - (hundreds*100 + tens*10),
				length = !!hundreds ? 3 : 2,
				compensate_hundreds_width = Math.ceil(0.5 * (hundreds+1)),
				compensate_tens_width = Math.ceil(0.5 * (tens+1)),
				compensate_ones_width = Math.ceil(0.5 * (ones+1)),
				tmp = $amount.html("<span class='eab_event_flip_hundreds'/><span class='eab_event_flip_tens'/><span class='eab_event_flip_ones'/><div style='clear:both'/>"),
				$digits = $amount.find("span")
			;
			$amount.css({
				'width': size*length
			});
			$digits.css({
				'float': 'left',
				'display': 'inline-block',
				'overflow': 'hidden',
				'background': 'url(' + sprite + ') no-repeat',
				'height': height,
				'width': size
			});
			if (!!hundreds) {
				$digits.filter(".eab_event_flip_hundreds").css({
					'background-position': (!!hundreds 
						? (((hundreds+1)*size*-1) -compensate_hundreds_width)  + 'px 0'
						: '0 0'
					)
				});
			} else {
				$digits.filter(".eab_event_flip_hundreds").remove();
			}
			$digits
				.filter(".eab_event_flip_tens").css({
					'background-position': (((tens+1)*size*-1) - compensate_tens_width) + 'px 0'
				}).end()
				.filter(".eab_event_flip_ones").css({
					'background-position': (((ones+1)*size*-1) -compensate_ones_width)  + 'px 0'
				}).end()
			;
		})
	;
	return false;
});
	
})(jQuery);