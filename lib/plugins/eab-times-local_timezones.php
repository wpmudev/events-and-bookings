<?php
/*
Plugin Name: Local Timezones
Description: Auto-converts your event dates and times for your visitors
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Events
*/

class Eab_Events_LocalTimezones {
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Events_LocalTimezones;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('eab-javascript-enqueue_scripts', array($this, 'include_scripts'));
	}

	public function include_scripts () {
		wp_enqueue_script('eab-events-local_timezones', EAB_PLUGIN_URL . "js/eab-events-local_timezones.js", array('jquery'), Eab_EventsHub::CURRENT_VERSION);
	}
}
Eab_Events_LocalTimezones::serve();