
jQuery(function() {
    var dates = jQuery( ".incsub_event_picker" ).datepicker({
	minDate: 0,
        dateFormat: "yy-mm-dd",
	changeMonth: true,
        changeYear: true
    });
    
    jQuery("#eab-add-more").show();
    jQuery("#eab-add-more-button").click(function() {
	row_id = jQuery('.eab-start-section').length-1;
	jQuery("#eab-add-more-rows").append(jQuery("#eab-add-more-bank").html().replace(/_bank/gi, '_'+row_id).replace(/bank/gi, row_id+1).replace(/_b/gi, ''));
	
	jQuery( "#incsub_event_start_"+row_id+" , #incsub_event_end_"+row_id ).datepicker({
	    minDate: 0,
	    dateFormat: "yy-mm-dd",
	    changeMonth: true,
	    changeYear: true
	});
	
	if (jQuery('.eab-section-block').length > 2) {
	    jQuery('.eab-section-heading').show();
	}
    });
    
    if (jQuery("#incsub_event-accept_payments:checked") && jQuery("#incsub_event-accept_payments:checked").length == 0) {
	jQuery(".incsub_event-payment_method_row").hide();
    }
    
    jQuery("#incsub_event-accept_payments").change(function () {
	if (jQuery("#incsub_event-accept_payments:checked") && jQuery("#incsub_event-accept_payments:checked").length == 0) {
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
    
    jQuery('input.incsub_event').blur(function () {
	
	_c = jQuery(this).attr('id').replace(/incsub_event_[a-z_]+_/gi, '');
	
	_eab_validate_when(_c);
    });
    
    jQuery('form#post').submit(function () {
	
	_c = 0;
	
	return _eab_validate_when(_c);
    });
    
    function _eab_validate_when(_c) {
	_start = new Date();
	if (jQuery('#incsub_event_start_'+_c).val() != '') {
	    _start = new Date(jQuery('#incsub_event_start_'+_c).val());
	}
	if (jQuery('#incsub_event_start_time_'+_c).val() != '') {
	    _start_time = jQuery('#incsub_event_start_time_'+_c).val();
	    _start_time_parts = _start_time.split(/:/gi);
	    
	    _start.setHours(_start_time_parts[0]);
	    _start.setMinutes(_start_time_parts[1]);
	}
	
	_end = new Date();
	if (jQuery('#incsub_event_end_'+_c).val() != '') {
	    _end = new Date(jQuery('#incsub_event_end_'+_c).val());
	}
	
	if (jQuery('#incsub_event_end_time_'+_c).val() != '') {
	    _end_time = jQuery('#incsub_event_end_time_'+_c).val();
	    _end_time_parts = _end_time.split(/:/gi);
	    
	    _end.setHours(_end_time_parts[0]);
	    _end.setMinutes(_end_time_parts[1]);
	}
	
	if (_start >= _end) {
	    jQuery('#incsub_event_start_'+_c).addClass('error');
	    jQuery('#incsub_event_start_time_'+_c).addClass('error');
	    jQuery('#incsub_event_end_'+_c).addClass('error');
	    jQuery('#incsub_event_end_time_'+_c).addClass('error');
	    jQuery('input.button-primary').attr('disabled', true);
	    return false;
	} else {
	    jQuery('#incsub_event_start_'+_c).removeClass('error');
	    jQuery('#incsub_event_start_time_'+_c).removeClass('error');
	    jQuery('#incsub_event_end_'+_c).removeClass('error');
	    jQuery('#incsub_event_end_time_'+_c).removeClass('error');
	    jQuery('input.button-primary').removeAttr('disabled');
	}
	return true;
    }
    
    _eab_location = window.location;
    
(function ($) {
// API toggling
function toggle_api_settings () {
	if ($("#incsub_event-accept_api_logins").is(":checked")) $("#eab-settings-apis").show();
	else $("#eab-settings-apis").hide();
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
		})
		return false;
	});
});
})(jQuery);
    
});
