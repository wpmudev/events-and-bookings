
jQuery(function() {
	if (!("ontouchstart" in window)) {
		jQuery(".incsub_event_picker").datepicker({
			"dateFormat": "yy-mm-dd",
			"changeMonth": true,
			"changeYear": true,
			"defaultDate": new Date(),
			"firstDay": parseInt(eab_event_localized.start_of_week, 10) ? parseInt(eab_event_localized.start_of_week, 10) : 0
		});
	}

    jQuery('[href*="preview=true"]').hide(); // Preview won't work
    jQuery("#eab-add-more").show();
    jQuery("#eab-add-more-button").click(function() {
		row_id = jQuery('.eab-start-section').length-1;
		jQuery("#eab-add-more-rows").append(jQuery("#eab-add-more-bank").html().replace(/_bank/gi, '_'+row_id).replace(/bank/gi, row_id+1).replace(/_b/gi, ''));

		jQuery( "#incsub_event_start_"+row_id+" , #incsub_event_end_"+row_id ).datepicker({
			"dateFormat": "yy-mm-dd",
			"changeMonth": true,
			"changeYear": true,
			"firstDay": parseInt(eab_event_localized.start_of_week, 10) ? parseInt(eab_event_localized.start_of_week, 10) : 0
		});

		if (jQuery('.eab-section-block').length > 2) {
			jQuery('.eab-section-heading').show();
		}
    });

    jQuery("body").on("click", ".eab-event-remove_time", function () {
		var $remove = jQuery(this),
			$parent = $remove.parents("#eab-add-more-rows"),
			$starts = $parent.find(".eab-start-section"),
			$target = $remove.parents(".eab-section-block")
		;
		if ($starts.length <= 1) return false; // Can't remove last one
		if (!$target.length) return false; // Don't know what to remove
		$target.remove();
		return false;
    });

    if (!jQuery("#incsub_event-accept_payments").is(":checked")) {
		jQuery("#eab-settings-paypal").hide();
    }

    jQuery("#incsub_event-accept_payments").change(function () {
		if (!jQuery("#incsub_event-accept_payments").is(":checked")) {
			jQuery("#eab-settings-paypal").hide();
		} else {
			jQuery("#eab-settings-paypal").show();
		}
    });

    if (jQuery("#incsub_event_paid").val() == 0) {
		jQuery(".incsub_event-fee_row").hide();
    }
    jQuery("#incsub_event_paid").change(function () {
		if (jQuery("#incsub_event_paid").val() == 0) {
			jQuery(".incsub_event-fee_row").hide();
		} else {
			jQuery(".incsub_event-fee_row").show();
		}
    });

    jQuery('a.eab-info').click(function () {
		jQuery(jQuery(this).attr('href')).toggle();
		return false;
    });

    if (!jQuery('#incsub-event-bookings').hasClass('closed')) {
		jQuery('#incsub-event-bookings').addClass('closed');
    }
    jQuery('#incsub-event-bookings .hndle').append('<span class="eab-expand-metabox">'+eab_event_localized["view_all_bookings"]+'</span>');
    if (!jQuery('#incsub-event-wizard').hasClass('closed')) {
		jQuery('#incsub-event-wizard').addClass('closed');
    }
    jQuery('#incsub-event-wizard .hndle').append('<a href="edit.php?post_type=incsub_event&page=eab_welcome" class="eab-expand-metabox">'+eab_event_localized["back_to_gettting_started"]+'</a>');

    if (jQuery('.eab-section-block').length == 2) {
		jQuery('.eab-section-heading').hide();
    }

    jQuery("#incsub-event").on("change", 'input.incsub_event', function () {
		var _c = jQuery(this).attr('id').replace(/incsub_event_[a-z_]+_/gi, '');
		_eab_validate_when(_c);
    });

    jQuery("#incsub-event").on("change", ".incsub_event_no_start_time", function () {
		var $me = jQuery(this),
			_c = $me.attr('id').replace(/incsub_event_[a-z_]+_/gi, '')
		;
		if ($me.is(":checked")) jQuery("#incsub_event_start_time_" + _c).hide();
		else jQuery("#incsub_event_start_time_" + _c).show();
    });
    jQuery(".incsub_event_no_start_time").each(function () { jQuery(this).trigger("change");});

    jQuery("#incsub-event").on("change", ".incsub_event_no_end_time", function () {
		var $me = jQuery(this),
			_c = $me.attr('id').replace(/incsub_event_[a-z_]+_/gi, '')
		;
		if ($me.is(":checked")) jQuery("#incsub_event_end_time_" + _c).hide();
		else jQuery("#incsub_event_end_time_" + _c).show();
    });
    jQuery(".incsub_event_no_end_time").each(function () { jQuery(this).trigger("change");});

    jQuery("#incsub-event").on("change", ".incsub_event_start", function () {
		var $me = jQuery(this),
			$end = $me.parents(".eab-section-block").find(".incsub_event_end"),
			_c = $me.attr('id').replace(/incsub_event_[a-z_]+_/gi, ''),
			_start, _end, _tmp
		;
		if (!$end.length) return true;
		_tmp = _eab_get_stat_end_datetime(_c);
		_start = _tmp.length ? _tmp[0] : false;
		_end = _tmp.length ? _tmp[1] : false;
		if (!_start || !_start.getTime) return true;
		$end.datepicker("option", "defaultDate", _start);
    });


    jQuery('form#post').submit(function () {
		_c = 0;
		if (jQuery("#eab_event-repeat_start").is(":visible")) return true;
		if (_eab_validate_when(_c)) {
			jQuery('#ajax-loading').show();
			jQuery('#publish').removeClass('button-primary-disabled');
			return true;
		} else {
			var $times = jQuery("#incsub_event_times_label"),
				top = $times.length ? $times.offset().top : 0
			;
			jQuery('#ajax-loading').hide();
			jQuery('#publish').removeClass('button-primary-disabled');
			jQuery("html,body").scrollTop(top);
			for (var pulse=0; pulse<3; pulse++) {
				jQuery(".incsub_event_date.error")
					.animate({"border-width": 3})
					.animate({"border-width": 1})
				;
			}
			return false;
		}
    });

    function _eab_get_stat_end_datetime (_c) {
		var _start, _end;
		_start = false;
		if (jQuery('#incsub_event_start_'+_c).val() != '') {
			_start = new Date(jQuery('#incsub_event_start_'+_c).val());
			if (jQuery('#incsub_event_start_time_'+_c).val() != '' || jQuery('#incsub_event_no_start_time_'+_c).is(":checked")) {
				var _start_time = (jQuery('#incsub_event_no_start_time_'+_c).is(":checked") ? '00:01' : jQuery('#incsub_event_start_time_'+_c).val()),
					_start_time_parts = _start_time.split(/:/gi)
				;

				_start.setHours(_start_time_parts[0]);
				_start.setMinutes(_start_time_parts[1]);
			}
		}

		_end = false;
		if (jQuery('#incsub_event_end_'+_c).val() != '') {
			_end = new Date(jQuery('#incsub_event_end_'+_c).val());
			if (jQuery('#incsub_event_end_time_'+_c).val() != '' || jQuery('#incsub_event_no_end_time_'+_c).is(":checked")) {
				var _end_time = (jQuery('#incsub_event_no_end_time_'+_c).is(":checked") ? '23:59' : jQuery('#incsub_event_end_time_'+_c).val()),
					_end_time_parts = _end_time.split(/:/gi)
				;

				_end.setHours(_end_time_parts[0]);
				_end.setMinutes(_end_time_parts[1]);
			}
		}
		return [_start, _end];
    }

    function _eab_validate_when(_c) {
		if ("bank" == _c) return true; // Don't check bank dates - they're stubs
		if (jQuery("#icl_translation_of").length) return true; // Assume translation

		var _start, _end, _tmp;
		_tmp = _eab_get_stat_end_datetime(_c);
		_start = _tmp.length ? _tmp[0] : false;
		_end = _tmp.length ? _tmp[1] : false;

		if ((!_start || !_end) || _start.getTime() >= _end.getTime()) {
			jQuery('#incsub_event_start_'+_c).addClass('error');
			jQuery('#incsub_event_start_time_'+_c).addClass('error');
			jQuery('#incsub_event_end_'+_c).addClass('error');
			jQuery('#incsub_event_end_time_'+_c).addClass('error');
			jQuery('input.button-primary.button-large').attr('disabled', true);
			return false;
		} else {
			jQuery('#incsub_event_start_'+_c).removeClass('error');
			jQuery('#incsub_event_start_time_'+_c).removeClass('error');
			jQuery('#incsub_event_end_'+_c).removeClass('error');
			jQuery('#incsub_event_end_time_'+_c).removeClass('error');
			jQuery('input.button-primary.button-large').removeAttr('disabled');
		}
		return true;
    }

    _eab_location = window.location;

(function ($) {
// API toggling
function toggle_api_settings () {
	if ($("#incsub_event-accept_api_logins").is(":checked")) $("#eab-settings-apis .eab-inside").show();
	else $("#eab-settings-apis .eab-inside").hide();
}

// Appearance toggling
function toggle_appearance_settings () {
	if ($("#incsub_event-override_appearance_defaults").is(":checked")) {
		$("#incsub_event-archive_template").attr("disabled", false);
		$("#incsub_event-single_template").attr("disabled", false);
	} else {
		$("#incsub_event-archive_template").attr("disabled", true);
		$("#incsub_event-single_template").attr("disabled", true);
	}
}

// Recurrence toggling
function toggle_recurrence_settings () {
	var text = $("#eab-eab-start_recurrence-button").val();
	var alter = $("#eab-eab-start_recurrence-button").attr("data-eab-alter_label");
	$("#eab-eab-start_recurrence-button").val(alter);
	$("#eab-eab-start_recurrence-button").attr("data-eab-alter_label", text);
	if (!$("#eab_event-recurring_event").is(":visible")) show_event_recurrence();
	else hide_event_recurrence();
	return false;
}
function show_event_recurrence () {
	$("#eab_event-recurring_event").show();

	$("#eab-add-more-rows").hide();
	$("#eab-add-more").hide();

	// Init jQuery UI date selectors
	$("#eab_event-repeat_start,#eab_event-repeat_end").datepicker({
		"dateFormat": "yy-mm-dd",
		"changeMonth": true,
		"changeYear": true,
		"firstDay": parseInt(eab_event_localized.start_of_week, 10) ? parseInt(eab_event_localized.start_of_week, 10) : 0
	});

	// Kill WP stuff
	$("#edit-slug-box").hide();
	toggle_recurrence_mode(true);
}
function hide_event_recurrence () {
	$("#eab_event-recurring_event").hide();

	$("#eab-add-more-rows").show();
	$("#eab-add-more").show();
	$("#edit-slug-box").show();
}

// Recurrence mode toggling
function toggle_recurrence_mode (e) {
	$(".eab_event_recurrence_mode").hide();

	var val = $("#eab_event-repeat_every").val();
	if (!val && "undefined" !== typeof e) {
		$("#publish").attr("disabled", true);
		return false;
	}
	var $el = $("#eab_event-repeat_interval-" + val);
	if (!$el.length) return false;

	$(".eab_event_recurrence_mode").find("input,select").attr("disabled", true);
	$el.find("input,select").attr("disabled", false);
	$("#publish").attr("disabled", false);
	$el.show();

	// Init jQuery UI date selectors
	$("#eab_event-repeat_start,#eab_event-repeat_end").datepicker({
		"dateFormat": "yy-mm-dd",
		"changeMonth": true,
		"changeYear": true,
		"firstDay": parseInt(eab_event_localized.start_of_week, 10) ? parseInt(eab_event_localized.start_of_week, 10) : 0
	});
}

// Recurrence instance editing toggling
function toggle_recurrence_instances () {
	if ($("#eab_event-recurring_instances").is(":visible")) $("#eab_event-recurring_instances").hide();
	else $("#eab_event-recurring_instances").show();
	return false;
}

$(function () {
	// Init API toggle
	$("#incsub_event-accept_api_logins").change(toggle_api_settings);
	toggle_api_settings();

	// Init Appearance toggle
	$("#incsub_event-override_appearance_defaults").change(toggle_appearance_settings);
	toggle_appearance_settings();

	// Tutorial restart
	$(".eab-restart_tutorial").click(function () {
		var $me = $(this);
		$.post(ajaxurl, {
			"action": "eab_restart_tutorial",
			"step": $me.attr("data-eab_tutorial")
		}, function () {
			window.location.reload();
		});
		return false;
	});

	// Init recurrence toggle
	$("#eab-eab-start_recurrence-button").click(toggle_recurrence_settings);
	// Init recurrence mode toggle
	$("#eab_event-repeat_every").change(toggle_recurrence_mode);
	toggle_recurrence_mode();
	// Init recurrence instances toggle
	$("#eab_event-edit_recurring_instances").click(toggle_recurrence_instances);
	// Initialize slug box
	if ($("#eab_event-repeat_every").is(":visible")) $("#edit-slug-box").hide();

	// Attendance canceling
	$("body").on("click", ".eab-guest-cancel_attendance", function () {
		var $me = $(this);
		var user_id = $me.attr("data-eab-user_id");
		var post_id = $me.attr("data-eab-event_id");
		$.post(ajaxurl, {
			"action": "eab_cancel_attendance",
			"user_id": user_id,
			"post_id": post_id
		}, function (data) {
			$("#eab-bookings-response").html(data);
		});
		return false;
	});
	// Attendance deleting
	$("body").on("click", ".eab-guest-delete_attendance", function () {
		var $me = $(this);
		var user_id = $me.attr("data-eab-user_id");
		var post_id = $me.attr("data-eab-event_id");
		$.post(ajaxurl, {
			"action": "eab_delete_attendance",
			"user_id": user_id,
			"post_id": post_id
		}, function (data) {
			$("#eab-bookings-response").html(data);
		});
		return false;
	});

	$("body").on("click", ".eab-add_attendance .button", function () {
		var $root = $(".eab-add_attendance"),
			event_id = $root.find(".eab-attendance-event_id").val()
			email = $root.find(".eab-attendance-email").val(),
			status = $root.find(".eab-attendance-status").val()
		;
		if (!event_id || !email || !status) return false;
		$.post(ajaxurl, {
			action: "eab_add_attendance",
			user: email,
			post_id: event_id,
			status: status
		}, function (data) {
			$("#eab-bookings-response").html(data);
		});
		return false;
	});

	var $times = $("#incsub-event input.incsub_event");
	$times.each(function () {
		var _c = $(this).attr('id').replace(/incsub_event_[a-z_]+_/gi, '');
		_eab_validate_when(_c);
	});

});
})(jQuery);

// Main settings page tabbing
(function ($) {

var MIN_SIZE = 768,
	$win = $(window),
	$submit = false
;

if (!window.location.search.match('page=eab_settings')) return false; // Only on settings page
if ($win.width() < MIN_SIZE) return false; // Only if we have enough space
if (!$(".wrap").is(".tabbable")) return false; // Allow override

function reveal_page (e) {
	e.preventDefault();
	e.stopPropagation();
	var me = $(this),
		box_id = me.attr('data-box_id'),
		box = false
	;
	if (box_id) box = $("#" + box_id);
	if (box_id && box_id.length) {
		$("#eab-root-settings_nav h3").removeClass("active");
		me.addClass("active");
		$('.eab-metabox.postbox').hide();
		box.show();
                /**
                 * Added by Ashok
                 *
                 * Updating URl with correct hash tag
                 * based on selected tab in event settings
                 */
                window.location.hash = box_id;
                set_event_url();
	}
}

function boot () {
	var boxes = $('.eab-metabox.postbox'),
		box_root = $(".eab-metaboxcol.metabox-holder")
	;
	$submit = $submit.length ? $submit : box_root.siblings(".submit")
	if (!boxes.length) return;
	
	var root = $("#eab-root-settings_nav");
	if (root.length) {
		if ($win.width() < MIN_SIZE) {
			box_root.removeClass("tabbed");
			root.remove();
			boxes.show();
		}
		box_root.append($submit);
		return;
	}
	
	box_root.append('<div id="eab-root-settings_nav"></div>');
	root = $("#eab-root-settings_nav");

	root.empty();

	boxes.each(function () {
		var me = $(this),
			box_id = me.attr("id"),
			title = me.find("h3.eab-hndle"),
			new_title = title.clone()
		;
		if (box_id) {
			new_title
				.attr("data-box_id", box_id)
				.on('click', reveal_page)
			;
			root.append(new_title);
			me.hide();
		}
	});
	box_root.append($submit);
        
        /**
         * Added by Ashok
         *
         * Show the correct page based on
         * appropriate hash tag in URL
         */
        var hash = window.location.hash;
        hash = hash.split('#')[1];
        if( hash ) {
            var obj = root.find('h3[data-box_id="'+ hash +'"]');
            if( obj.length ){
                obj.click();
            }else{
                root.find("h3:first").click();
            }
        }else{
            root.find("h3:first").click();
        }
        set_event_url();
        
	box_root.addClass("tabbed");

	$(".eab-loading-cover.tabbable").remove();
	$(".wrap.tabbable.hide").removeClass("hide");
}

/**
 * Added by Ashok
 *
 * Updating hidden field with current page url
 */
function set_event_url() {
    var url = window.location;
    $('.event_settings_url').val(url);
}

$(boot);
$win.on("resize", boot);

})(jQuery);

});
