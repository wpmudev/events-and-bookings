/**
 * Responsible for hooking Maps to the Events interface.
 */

(function($){
$(function() {


/**
 * Creates tag markup.
 */
function createMapIdMarkerMarkup (id) {
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
function updateEditorContents (mapMarker) {
	$("#incsub_event_venue").text(mapMarker);
}

function insertMapItem () {
        var $me = $(this);
        var mapMarker = createMapIdMarkerMarkup($me.parents('li').find('input:hidden').val());
        updateEditorContents(mapMarker);
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
} else {
    // New API
    eab_mbuttons_container.append('' +
        '<a class="add_map" title="' + eab_l10nEditor.add_map + '">' +
            '<img onclick="return false;" alt="' + eab_l10nEditor.add_map + '" src="' + maps_url + '/img/system/globe-button.gif">' +
        '</a>'
    );
}

//$("li.existing_map_item").off("click", "a.add_map_item");
$('body').off("click", "li.existing_map_item a.add_map_item");
//$("li.existing_map_item").on("click", "a.add_map_item", insertMapItem);
$('body').on("click", "li.existing_map_item a.add_map_item", insertMapItem);

$('#map_preview_container').unbind('agm_map_insert');
$('#map_preview_container').bind('agm_map_insert', function (e, id) {
        var mapMarker = createMapIdMarkerMarkup(id);
        updateEditorContents(mapMarker);
        close_editor_window();
});

$('#add_map').hide();

});
})(jQuery);
