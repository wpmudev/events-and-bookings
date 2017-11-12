(function ($) {

/**
 * Shows/hides attendance information.
 */
function toggle_rsvps () {
	var $rsvps = $("#eab-events-fpe-rsvps-wrapper");
	if ($rsvps.is(":visible")) $rsvps.slideUp('slow');
	else $rsvps.slideDown('slow');
	return false;
}

/**
 * Shows Fee box if the event is premium.
 */
function toggle_fee () {
	var $select = $("#eab-events-fpe-is_premium");
	if (!$select.val()) return false;
	
	var $fee = $("#eab-events-fpe-event_fee-wrapper");
	
	var is_premium = parseInt($select.val());
	if (is_premium) $fee.show();
	else $fee.hide(); 
}

/**
 * Shows missing date/time-specific error.
 */
function missing_datetime_error ($erroneous) {
	if ($("#eab-events-fpe-date_time-error").length) $("#eab-events-fpe-date_time-error").remove();
	$("#eab-events-fpe-date_time").append(
		'<div id="eab-events-fpe-date_time-error">' +
			l10nFpe.mising_time_date + 
		'</div>'
	);
	return false;
}

/**
 * Shows invalid date/time-specific error.
 */
function invalid_datetime_error ($erroneous) {
	if ($("#eab-events-fpe-date_time-error").length) $("#eab-events-fpe-date_time-error").remove();
	$("#eab-events-fpe-date_time").append(
		'<div id="eab-events-fpe-date_time-error">' +
			l10nFpe.check_time_date + 
		'</div>'
	);
	return false;
}

/**
 * Shows general purpose level messages.
 */
function show_message (msg, is_error) {
	var cls = is_error ? 'eab-events-fpe-error' : 'eab-events-fpe-success';
	$(".eab-events-fpe-notification").remove();
	$("#eab-events-fpe-ok_cancel").append(
		'<div class="eab-events-fpe-notification ' + cls + '"><p>' +
			msg +
		'</p></div>'
	);
	setTimeout(function () {
		$(".eab-events-fpe-notification").slideUp("slow");
	}, 2000);
}

/**
 * Normalizes a time string into 24-hours format array.
 * @param string Time string to normalilze
 * @return array [HH,mm]
 */
function _time_string_to_array (time_string) {
	var time_parts = false;
	if (time_string.match(/[ap]m/i)) { // Yanks have been here
		var is_night = time_string.match(/pm/i);
		var normalized = time_string.replace(/[ap]m/i, '');
		time_parts = (normalized.indexOf(':') >= 0)
			? normalized.split(':')
			: [normalized, '00']
		;
		if (is_night) {
			time_parts[0] = 12 + parseInt(time_parts[0], 10);
		}
	} else {
		time_parts = (time_string.indexOf(':') >= 0)
			? time_string.split(':') 
			: [time_string, '00']
		;
	}
	return time_parts ? time_parts : ['12', '00'];
}

/**
* Manage no start and end time checkboxes
*/
jQuery(document).ready(function(e) {
   	jQuery(document).on('change', '#incsub_event_no_start_time_0',function(){
		if ( document.getElementById("incsub_event_no_start_time_0").checked == true ){
	   		jQuery('#eab-events-fpe-start_time').hide();
		}else{
			jQuery('#eab-events-fpe-start_time').show();
		}
    });
	jQuery(document).on('change', '#incsub_event_no_end_time_0',function(){
		if ( document.getElementById("incsub_event_no_end_time_0").checked == true ){
	   		jQuery('#eab-events-fpe-end_time').hide();
		}else{
			jQuery('#eab-events-fpe-end_time').show();
		}
    });
	
	if ( jQuery('#eab-events-fpe-start_time').val() == "00:00"){
		jQuery('#eab-events-fpe-start_time').hide();
		jQuery('#incsub_event_no_start_time_0').attr('checked',true);
	}
	if ( jQuery('#eab-events-fpe-end_time').val() == "00:00"){
		jQuery('#eab-events-fpe-end_time').hide();
		jQuery('#incsub_event_no_end_time_0').attr('checked',true);
	}
});
/**
 * Sends save request and shows general message
 */
function send_save_request () {
	if ($("#eab-events-fpe-date_time-error").length) $("#eab-events-fpe-date_time-error").remove();
	var has_start = false,
		has_end = false,
		$start_date = $("#eab-events-fpe-start_date");

	if (!$start_date.val()) return missing_datetime_error($start_date);
	var start = new Date($start_date.val());

	var start_time_parts = [];
	var end_time_parts = [];
	
	if ( $( '#eab-events-fpe-toggle_time__start' ).is( ':checked' ) ){
		var $start_time = $("#eab-events-fpe-start_time");
		if (!$start_time.val()) return missing_datetime_error($start_time);
	
		start_time_parts = _time_string_to_array($start_time.val());

		start.setHours(start_time_parts[0]);
		start.setMinutes(start_time_parts[1]);
		has_start = true;
	}
	
	var $end_date = $("#eab-events-fpe-end_date");
	if (!$end_date.val()) return missing_datetime_error($end_date);
	var end = new Date($end_date.val());
	
	
	if ( $( '#eab-events-fpe-toggle_time__end' ).is( ':checked' ) ){
		var $end_time = $("#eab-events-fpe-end_time");
		if (!$end_time.val()) return missing_datetime_error($end_time);
		
		end_time_parts = _time_string_to_array($end_time.val());
		end.setHours(end_time_parts[0]);
		end.setMinutes(end_time_parts[1]);
		has_end = true;
	}
	
	if (start >= end) return invalid_datetime_error();
	
	$("#eab-events-fpe-ok").after(
		'<img src="' + _eab_events_fpe_data.root_url + '/waiting.gif" id="eab-events-fpe-waiting_indicator" />'
	);
	var content = $("#eab-events-fpe-content").is(":visible") ? $("#eab-events-fpe-content").val() : tinyMCE.activeEditor.getContent();
        
	var modified_start_time = start_time_parts.join(':');
	var modified_end_time = end_time_parts.join(':');
	
	modified_start_time = modified_start_time.replace(/ /g, '');
	modified_end_time = modified_end_time.replace(/ /g, '');
        
	var data = {
		"id": $("#eab-events-fpe-event_id").val(),
		"title": $("#eab-events-fpe-event_title").val(),
		"content": content,
		"start": $start_date.val() + ' ' + modified_start_time,
		"end": $end_date.val() + ' ' + modified_end_time,
		"no_start_time": $( '#eab-events-fpe-toggle_time__start' ).is( ':checked' ),
		"no_end_time": $( '#eab-events-fpe-toggle_time__end' ).is( ':checked' ),
		"venue": $("#eab-events-fpe-venue").val(),
		"status": $("#eab-events-fpe-status").val(),
		"is_premium": ($("#eab-events-fpe-is_premium").length ? $("#eab-events-fpe-is_premium").val() : 0),
		"category": $("#eab-events-fpe-categories").val(),
		/* Added by Ashok */
		"featured" : $('#eab-fpe-attach_id').val(),
		/* End of adding by Ashok */
		/* Added by Lindeni */
		"has_start" : has_start,
		"has_end" : has_end
		/* End of adding by Lindeni */
	};
	if ($("#eab-events-fpe-event_fee").length) {
		data["fee"] = $("#eab-events-fpe-event_fee").val();
	}
	$(document).trigger('eab-events-fpe-save_request', [data]);
        
	// Start sending!!
	$.post(_eab_events_fpe_data.ajax_url, {
		"action": "eab_events_fpe-save_event",
		"data": data
	}, function (response) {
		$("#eab-events-fpe-waiting_indicator").remove();
		var status = false;
		var message = false;
		try { status = parseInt(response.status, 10); } catch (e) { status = 0; }
		try { message = response.message; } catch (e) { message = false; }
		if (!status) return show_message((message ? message : l10nFpe.general_error), true);
		
		var post_id = false;
		try { post_id = parseInt(response.post_id, 10); } catch (e) { post_id = 0; }
		if (!post_id) return show_message((message ? message : l10nFpe.missing_id), true);
		
		var link = false;
		try { link = response.permalink; } catch (e) { link = false; }
		if (link) {
			$("#eab-events-fpe-back_to_event").attr("href", link).show();
			$("#eab-events-fpe-cancel").off("click").on("click", function () {
				window.location = link;
			}).show();
		}
		
		$("#eab-events-fpe-event_id").val(post_id);
        $(".eab-attendance-event_id").val(post_id);
		return show_message((message ? message : l10nFpe.all_good), false);
	});
	return false;
}
	

// Init
$(function () {
	
	$("#fpe-editor").append($("#fpe-editor-root"));
	$("#fpe-editor-root").show();
	
	// Toggle RSVPs
	$("#eab-events-fpe-toggle_rsvps").click(toggle_rsvps);
	
	// Init date pickers
	$("#eab-events-fpe-start_date, #eab-events-fpe-end_date").datepicker({
		minDate: 0,
		dateFormat: "yy-mm-dd",
		changeMonth: true,
		changeYear: true
	});
	
	// Init Fee toggling
	if ($("#eab-events-fpe-is_premium")) {
		$("#eab-events-fpe-is_premium").change(toggle_fee);
		toggle_fee();
	}
	
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
			$(".eab-add_attendance-container").html(data);
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
			$(".eab-add_attendance-container").html(data);
            window.location.reload();
		});
		return false;
	});
    
    $("body").on("click", ".eab-guest-cancel_attendance", function () {
		var $me = $(this);
		var user_id = $me.attr("data-eab-user_id");
		var post_id = $me.attr("data-eab-event_id");
		$.post(ajaxurl, {
			"action": "eab_cancel_attendance",
			"user_id": user_id,
			"post_id": post_id
		}, function (data) {
			$(".eab-add_attendance-container").html(data);
            window.location.reload();
		});
		return false;
	});
        
	// Init save request processing
	$("#eab-events-fpe-ok").click(send_save_request);

	var link = $("#eab-events-fpe-back_to_event").is(":visible") && $("#eab-events-fpe-back_to_event").attr("href");
	if (link) {
		$("#eab-events-fpe-cancel").off("click").on("click", function () {
			window.location = link;
		}).show();
	} else $("#eab-events-fpe-cancel").hide();
	/* Added by Ashok */
	$('.eab-fpe-upload').click(function() {
		var _old_send = window.send_to_editor;
        tb_show('&nbsp;', l10nFpe.base_url+'/wp-admin/media-upload.php?type=image&TB_iframe=true&post_id=0', false);
        window.send_to_editor = function(html) {
	    	var id = html.split('wp-image-')[1].split('"')[0];
	    	$('#eab-fpe-attach_id').val(id);
            var src = $('img', html).attr('src');
    		$('#eab-fpe-preview-upload')
    			.attr('src', src)
    			.show()
    		;
            tb_remove();
            window.send_to_editor = _old_send;
        }
        return false;
    });
	/* End of adding by Ashok */

	/* Toggle time options */
	$( document ).on( 'click', '#eab-events-fpe-date_time .eab_time_toggle', function(){
		
		var affect = $( this ).data( 'time-affect' ),
			source = ( $( this ).attr( 'type' ) == 'checkbox' ) ? 'checkbox' : 'other_trigger',
			target = $( '.eab-events-fpe_wrap_time_' + affect ),
			checkbox = $( '#eab-events-fpe-toggle_time__' + affect );
		
		if( source == 'other_trigger' ){
			checkbox.prop("checked", !checkbox.prop("checked"));
		}
		
		if( checkbox.is( ':checked' ) ){
			target.fadeOut( 300 );
		}
		else{
			target.show( 300 );	
		}

	});
});

	
	
})(jQuery);
