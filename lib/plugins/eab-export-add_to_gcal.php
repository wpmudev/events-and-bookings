<?php
/*
Plugin Name: Export: Google Calendar
Description: Adds a convenience button for your vistors to schedule events in their Google Calendars.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 0.1
Author: WPMU DEV
AddonType: Integration
*/

class Eab_Export_GCalButton {

	private $_data;

	/**
	 * Injected/processed event IDs cache
	 *
	 * @var array
	 */
	private $_added = array();

	function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Export_GCalButton;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_filter('eab-template-archive_after_view_link', array($this, 'append_export_link'), 10, 2);
		add_filter('eab-events-after_single_event', array($this, 'append_export_link'), 10, 2);
		
		// Catch-all filter, in case we missed the injection previously.
		add_filter('eab-events-after_event_details', array($this, 'append_export_link'), 10, 2);
	}

	function append_export_link ($content, $event) {
		if (in_array($event->get_id(), $this->_added)) return $content;

		$time_callback = 'date';
		$zulu = '';
		$zone_string = get_option('timezone_string');
		$gmt_offset = (float)get_option('gmt_offset');

		if (empty($gmt_offset)) $zulu = 'Z';
		else if (empty($zone_string)) {
			$hour_tz = sprintf('%02d', abs((int)$gmt_offset));
			$minute_offset = (abs($gmt_offset) - abs((int)$gmt_offset)) * 60;
			$min_tz = sprintf('%02d', $minute_offset);
			$zone_string = 'UTC' . ($gmt_offset > 0 ? '+' : '-') . $hour_tz . ':' . $min_tz;
		}

		$start = $time_callback("Ymd\THis", $event->get_start_timestamp()) . $zulu;
		$end = $time_callback("Ymd\THis", $event->get_end_timestamp()) . $zulu;
		$data = array(
			'action=TEMPLATE',
			'text=' . esc_attr($event->get_title()),
			'dates=' . esc_attr("{$start}/{$end}"),
			'details=' . esc_attr(wp_strip_all_tags($event->get_excerpt_or_fallback(64))),
			'location=' . esc_attr($event->get_venue_location(Eab_EventModel::VENUE_AS_ADDRESS)),
			'trp=false',
			'sprop=' . esc_attr('website:' . parse_url(home_url(), PHP_URL_HOST)),
		);
		if (!empty($zone_string) && !empty($gmt_offset)) {
			$data[] = 'ctz=' . esc_attr($zone_string);
		}

		$this->_added[] = $event->get_id();

		/*return "{$content} <a class='export_to_gcal' href='" .
			esc_url('http://www.google.com/calendar/event?' . join('&', $data)) .
		"'><span class='eab_export' style='display:none'>" . __('Export to GCAL', Eab_EventsHub::TEXT_DOMAIN) . '</span><img src="//www.google.com/calendar/images/ext/gc_button1.gif" border=0></a>';*/
                
                /**
                 * Added by Ashok
                 *
                 * New Export to gCal button
                 */
                return "{$content} <a class='export_to_gcal' href='" .
			esc_url('http://www.google.com/calendar/event?' . join('&', $data)) .
		"'><span class='eab_export'>" . __('Export to GCAL', Eab_EventsHub::TEXT_DOMAIN) . '</span></a>';
	}
}

Eab_Export_GCalButton::serve();