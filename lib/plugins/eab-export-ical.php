<?php
/*
Plugin Name: Export: iCal
Description: Export your Event(s) in iCal format.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 0.1
Author: WPMU DEV
AddonType: Integration
*/

/*
Detail: This Addon will allow you to export one or more of your events in iCal format.<br />Usage: just add <code>?eab_format=ical</code> at the end of the Event or Events archive URL.

*/

if (!class_exists('Eab_ExporterFactory')) require_once EAB_PLUGIN_DIR . 'lib/class_eab_exporter.php';

class Eab_Export_iCal {

	private $_data;
        
        private $_added = array();

	function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Export_iCal;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		
		add_action('wp', array($this, 'intercept_page_load'), 99);

		if ($this->_data->get_option('eab_export-ical-auto_show_links')) {
			add_filter('eab-template-archive_after_view_link', array($this, 'append_export_link'), 10, 2);
			add_filter('eab-events-after_single_event', array($this, 'append_export_link'), 10, 2);
            add_filter('eab-events-after_event_details', array($this, 'append_export_link'), 10, 2);
		}

		add_action('eab-events-rsvp_email-codec-macros', array($this, 'rsvp_email_macro_addition'));
		add_action('eab-events-rsvp_email-codec-expand', array($this, 'rsvp_email_macro_expansion'), 10, 2);
	}

	function rsvp_email_macro_addition ($macros) {
		$macros[] = 'EVENT_ICAL_URL';
		$macros[] = 'EVENT_ICAL_LINK';
		$macros[] = 'EVENT_ICAL_DOWNLOAD_LINK';
		return $macros;
	}

	function rsvp_email_macro_expansion ($str, $event) {
		if (!$event) return $str;
		$ical_url = get_permalink($event->get_id()) . '?eab_format=ical';
		$ical_link = '<a href="' . $ical_url . '">' . $event->get_title() . '</a>';
		$dload_link = '<a href="' . $ical_url . '&attachment">' . $event->get_title() . '</a>';
		$str = preg_replace('/(?:^|\b)EVENT_ICAL_URL(?:\b|$)/', $ical_url, $str);
		$str = preg_replace('/(?:^|\b)EVENT_ICAL_LINK(?:\b|$)/', $ical_link, $str);
		$str = preg_replace('/(?:^|\b)EVENT_ICAL_DOWNLOAD_LINK(?:\b|$)/', $dload_link, $str);
		return $str;
	}

	function save_settings ($options) {
		$options['eab_export-ical-auto_show_links'] = @$_POST['event_default']['eab_export-ical-auto_show_links'];
		$options['eab_export-ical-download_links'] = @$_POST['event_default']['eab_export-ical-download_links'];
		$options['eab_export-ical-time'] = @$_POST['event_default']['eab_export-ical-time'];
		return $options;
	}

	function show_settings () {
		$export_time = $this->_data->get_option('eab_export-ical-time', 'gmt');
		$checked_time_gmt = 'gmt' == $export_time ? 'checked="checked"' : '';
		$checked_time_entered = 'local' == $export_time ? 'checked="checked"' : '';
		$checked_time_tz_local = 'tz_local' == $export_time ? 'checked="checked"' : '';
		$checked_auto = $this->_data->get_option('eab_export-ical-auto_show_links') ? 'checked="checked"' : '';
		$checked_dload = $this->_data->get_option('eab_export-ical-download_links') ? 'checked="checked"' : '';
?>
<div id="eab-settings-ical_export" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('iCal export settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
	    <div class="eab-settings-settings_item">
			<b><?php _e('Exported event times', Eab_EventsHub::TEXT_DOMAIN); ?></b>
			<div style="line-height:1.5em; padding-bottom:.5em;">
				<label for="eab_export-ical-time-gmt">
					<input type="radio" id="eab_export-ical-time-gmt" name="event_default[eab_export-ical-time]" value="gmt" <?php echo $checked_time_gmt; ?> />
					<?php _e('Greenwich Mean Time (GMT)', Eab_EventsHub::TEXT_DOMAIN); ?>
				</label>
				<br />
				<label for="eab_export-ical-time-entered">
					<input type="radio" id="eab_export-ical-time-entered" name="event_default[eab_export-ical-time]" value="local" <?php echo $checked_time_entered; ?> />
					<?php _e('Local time (as entered)', Eab_EventsHub::TEXT_DOMAIN); ?>
				</label>
				<br />
				<label for="eab_export-ical-time-tz_local">
					<input type="radio" id="eab_export-ical-time-tz_local" name="event_default[eab_export-ical-time]" value="tz_local" <?php echo $checked_time_tz_local; ?> />
					<?php _e('Local time (as entered) with timezone information', Eab_EventsHub::TEXT_DOMAIN); ?>
				</label>
			</div>
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_export-ical-auto_show_links"><?php _e('Automatically add export links to Events and archives', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_export-ical-auto_show_links" name="event_default[eab_export-ical-auto_show_links]" value="1" <?php print $checked_auto; ?> />
		</div>
		<div class="eab-settings-settings_item">
			<label for="eab_export-ical-download_links"><?php _e('Auto-added links are download links', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_export-ical-download_links" name="event_default[eab_export-ical-download_links]" value="1" <?php print $checked_dload; ?> />
	    </div>
	</div>
</div>
<?php
	}

	function append_export_link ($content, $event) {
                
		if (in_array($event->get_id(), $this->_added)) return $content;
            $this->_added[] = $event->get_id();
            
		$download = $this->_data->get_option('eab_export-ical-download_links') ? '&attachment' : '';
		return "{$content} <a class='export_to_ical' href='" . 
			get_permalink($event->get_id()) . 
			'?eab_format=ical' . $download .
		"'><span class='eab_export'>" . __('Export to iCal', Eab_EventsHub::TEXT_DOMAIN) . '</span></a>';
	}

	function intercept_page_load () {
		if (is_admin()) return false;
		
		global $wp_query;
		if (!isset($_GET['eab_format'])) return false;
		if ('ical' != strtolower($_GET['eab_format'])) return false;
		if (!(isset($wp_query->query_vars) && isset($wp_query->query_vars['post_type']) && Eab_EventModel::POST_TYPE == $wp_query->query_vars['post_type'])) return false;
		if (!$wp_query->posts) return false;

		$request = array(
			'format' => 'ical',
			Eab_ExporterFactory::DISPOSITION_KEY => isset($_GET['attachment']) ? Eab_Exporter::DISPOSITION_ATTACHMENT : Eab_Exporter::DISPOSITION_INLINE,
		);
		$request[Eab_ExporterFactory::EXPORTER_KEY] = is_singular() ? Eab_Exporter::SCOPE_EVENT : Eab_Exporter::SCOPE_COLLECTION;
		if (is_singular()) $request['event_id'] = $wp_query->get_queried_object_id();

		if (isset($_GET['recurring'])) add_filter('eab-export-ical-recurring_instances', '__return_true');

		$request['event_time_calculus'] = $this->_data->get_option('eab_export-ical-time', 'gmt');

		Eab_ExporterFactory::serve($request);
	}
}

class Eab_Exporter_Ical extends Eab_Exporter {

	private $_processed = array();
	private $_calculus = 'gmt';

	public function __construct ($args) {
		if (!empty($args['event_time_calculus'])) $this->_calculus = $args['event_time_calculus'];
		parent::__construct($args);
	}

	public function get_mime_type () {
		return 'text/calendar';
	}

	public function get_file_extension () {
		return 'ics';
	}

	public function export_event () {
		if (!$this->_event_id) die(__('No event to export', Eab_EventsHub::TEXT_DOMAIN));
		$ret = $this->_get_header();
		$ret .= $this->_get_event_as_ical(new Eab_EventModel(get_post($this->_event_id)), apply_filters('eab-export-ical-recurring_instances', true));
		$ret .= "END:VCALENDAR";
		echo $ret;
	}

	public function export_events_collection () {
		global $wp_query;
		if (!$wp_query->posts) return false;

		$ret = $this->_get_header();
		foreach ($wp_query->posts as $post) {
			$event = $this->_get_event_as_ical($post, apply_filters('eab-export-ical-recurring_instances', false));
			if (!$event) continue;
			$ret .= $event;
		}
		$ret .= "END:VCALENDAR";
		echo $ret;
	}

	public function export_attendees () {$event = new Eab_EventModel(get_post($this->_event_id));
		die(__('Not supported', Eab_EventsHub::TEXT_DOMAIN));
	}

	private function _get_header () {
		return '' .
			"BEGIN:VCALENDAR\n" .
			"VERSION:2.0\n" .
			"PRODID:-//hacksw/handcal//NONSGML v1.0//EN\n" .
		'';
	}

	private function _get_event_as_ical ($post, $recurring_instances=false) {
		$ret = '';
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		if (in_array($event->get_id(), $this->_processed)) return '';

		// Branch off into recurrence processing
		if ($event->is_recurring()) {
			$events = Eab_CollectionFactory::get_all_recurring_children_events($event);
			foreach ($events as $event) $ret .= $this->_get_event_as_ical($event, $recurring_instances);
			return $ret;
		} else if ($event->is_recurring_child() && $recurring_instances) {
			return $this->_get_event_as_ical(get_post($event->get_parent()), false);
		}

		$domain = preg_replace('/^www\./', '', parse_url(home_url(), PHP_URL_HOST));
		$author = get_userdata($event->get_author());
		$location = $event->get_venue_location(Eab_EventModel::VENUE_AS_ADDRESS);

		$start_dates = $event->get_start_dates();
		$tz_offset = get_option('gmt_offset') * 3600;
		$time_callback = /*'gmt' == $this->_calculus ? 'gmdate' : */'date';
		
		$zulu = ''; $tzid = '';
		if ('gmt' == $this->_calculus) {
			$zulu = 'Z';
		} else if ('tz_local' == $this->_calculus) {
			$zone_string = get_option('timezone_string');
			if (!empty($zone_string)) $tzid = ";TZID={$zone_string}";
		}

		foreach ($start_dates as $key => $start) {
			$start = $event->get_start_timestamp($key) + (('gmt' == $this->_calculus) ? $tz_offset : 0);
			$end = $event->get_end_timestamp($key) + (('gmt' == $this->_calculus) ? $tz_offset : 0);
			$ret .= "BEGIN:VEVENT\n" .
				'UID:' . $event->get_id() . rand() . "@{$domain}\n" .
				"ORGANIZER;CN={$author->display_name}:MAILTO:{$author->user_email}\n".
				"DTSTART{$tzid}:" . $time_callback('Ymd', $start) . "T" . $time_callback('His', $start) . "{$zulu}\n" .
				"DTEND{$tzid}:" . $time_callback('Ymd', $end) . "T" . $time_callback('His', $end) . "{$zulu}\n" .
				"SUMMARY:" . $event->get_title() . "\n" .
                                // Old format with no paragraph - all texts are in same paragraph
				//"DESCRIPTION:" . strip_tags(preg_replace('/\s\s+/', ' ', preg_replace('/\r|\n/', ' ', $event->get_content()))) . "\n" .
                                // Enable paragraph
                                "DESCRIPTION:" . str_replace("\r\n", "\\n", $event->get_content()) . "\n" .
				"URL:" . get_permalink($event->get_id()) . "\n" .
				($location ? "LOCATION:{$location}\n" : '') .
			"END:VEVENT\n";
		}
		$this->_processed[] = $event->get_id();

		return "\n{$ret}\n";
	}
}

Eab_Export_iCal::serve();