/**
 * Responsible for hooking Maps to the Events interface.
 */

(function($){
$(function() {


/**
 * Creates tag markup.
 */
function create_map_marker (id) {
	if (!id) return '';
//Deal with this at later stage
/*
	var shortcode = window._agmConfig && window._agmConfig.shortcode
		? window._agmConfig.shortcode
		: 'map'
	;
*/
	var shortcode = 'map';
	return '[' + shortcode + ' id="' + id + '"] ';
}

function close_editor_window () {
	if ("function" === typeof closeMapEditor) return closeMapEditor();

	var $cls = $(".wpmui-wnd-close:visible");
	if ($cls.length) $cls.click();
}

/**
 * Inserts the map marker into editor.
 * Supports TinyMCE and regular editor (textarea).
 */
function insert_map_text (mapMarker) {
	var $venue = $("#incsub_event_venue");
	if (!$venue.length) return false;

	return $venue.is("input,textarea")
		? $venue.val(mapMarker)
		: $venue.text(mapMarker)
	;
}

function insert_map_item_handler () {
	var $me = $(this);
	var mapMarker = create_map_marker($me.parents('li').find('input:hidden').val());
	insert_map_text(mapMarker);
	close_editor_window();
	return false;
}

function insert_map_preview_handler (e, id) {
	var mapMarker = create_map_marker(id);
	insert_map_text(mapMarker);
	close_editor_window();
	return false;
}

// Find Media Buttons strip and add the new one
var maps_url = ("undefined" != typeof _agm && _agm.root_url
		? _agm.root_url
		: _agm_root_url
	),
	eab_mbuttons_container = $('#eab_insert_map')
;
if (!eab_mbuttons_container.length) return;
if (window.openMapEditor) {
	// Old API
	eab_mbuttons_container.append('' +
		'<a onclick="return openMapEditor();" title="' + eab_l10nEditor.add_map + '" class="thickbox" id="eab_add_map" href="#TB_inline?width=640&height=594&inlineId=map_container">' +
			'<img onclick="return false;" alt="' + eab_l10nEditor.add_map + '" src="' + maps_url + '/img/system/globe-button.gif">' +
		'</a>'
	);
	$('#map_preview_container')
		.unbind('agm_map_insert')
		.bind('agm_map_insert', insert_map_preview_handler)
	;
} else {
	// New API
	eab_mbuttons_container.append('' +
		'<a class="add_map" title="' + eab_l10nEditor.add_map + '">' +
			'<img onclick="return false;" alt="' + eab_l10nEditor.add_map + '" src="' + maps_url + '/img/system/globe-button.gif">' +
		'</a>'
	);
	$(document)
		.off('agm_map_insert', '.agm-editor .map_preview_container')
		.on('agm_map_insert', '.agm-editor .map_preview_container', insert_map_preview_handler)
	;
}

$('body')
	.off("click", "li.existing_map_item .add_map_item")
	.on("click", "li.existing_map_item .add_map_item", insert_map_item_handler)
;

$('#add_map').hide();

});
})(jQuery);
