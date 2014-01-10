<?php
/*
Plugin Name: Export: Google Calendar
Description: Adds a convenience button for your vistors to schedule events in their Google Calendars.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 0.1
Author: Ve Bailovity
*/

class Eab_Export_GCalButton {

	private $_data;

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
	}

	function append_export_link ($content, $event) {
		$time_callback = /*'gmt' == $this->_calculus ? 'gmdate' : */'date';
		$zulu = 'Z';

		$start = $time_callback("Ymd\THis", $event->get_start_timestamp()) . $zulu;
		$end = $time_callback("Ymd\THis", $event->get_end_timestamp()) . $zulu;
		$data = array (
			'action=TEMPLATE',
			'text=' . esc_attr($event->get_title()),
			'dates=' . esc_attr("{$start}/{$end}"),
			'details=' . esc_attr(wp_strip_all_tags($event->get_excerpt_or_fallback(64))),
			'location=' . esc_attr($event->get_venue_location(Eab_EventModel::VENUE_AS_ADDRESS)),
			'trp=false',
			'sprop=' . esc_attr('website:' . parse_url(home_url(), PHP_URL_HOST)),
		);
		return "{$content} <a href='http://www.google.com/calendar/event?" . 
			join('&', $data) . 
		"'><span class='eab_export' style='display:none'>" . __('Export to GCAL', Eab_EventsHub::TEXT_DOMAIN) . '</span><img src="//www.google.com/calendar/images/ext/gc_button1.gif" border=0></a>';
	}
}

Eab_Export_GCalButton::serve();