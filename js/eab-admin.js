
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
    });
    
    if (jQuery("#incsub_event-accept_payments:checked") && jQuery("#incsub_event-accept_payments:checked").length == 0) {
	jQuery(".incsub_event-payment_method_row").hide();
    }
    
    jQuery("#incsub_event-accept_payments").change(function () {
	if (jQuery("#incsub_event-accept_payments:checked") && jQuery("#incsub_event-accept_payments:checked").length == 0) {
	    jQuery(".incsub_event-payment_method_row").hide();
	} else {
	    jQuery(".incsub_event-payment_method_row").show();
	}
    });
    
    if (jQuery("#incsub_event_paid:checked") && jQuery("#incsub_event_paid:checked").length == 0) {
	jQuery(".incsub_event-fee_row").hide();
    }
    jQuery("#incsub_event_paid").change(function () {
	if (jQuery("#incsub_event_paid:checked") && jQuery("#incsub_event_paid:checked").length == 0) {
	    jQuery(".incsub_event-fee_row").hide();
	} else {
	    jQuery(".incsub_event-fee_row").show();
	}
    });
});
