
jQuery(function() {
    var dates = jQuery( "#incsub_event_start, #incsub_event_end" ).datepicker({
	minDate: 0,
        dateFormat: "yy-mm-dd",
	changeMonth: true,
        changeYear: true,
	onSelect: function( selectedDate ) {
	    var option = this.id == "incsub_event_start" ? "minDate" : "maxDate",
	    instance = jQuery( this ).data( "datepicker" ),
	    date = jQuery.datepicker.parseDate(instance.settings.dateFormat || jQuery.datepicker._defaults.dateFormat, selectedDate, instance.settings );
	    dates.not( this ).datepicker( "option", option, date );
	}
    });
});
