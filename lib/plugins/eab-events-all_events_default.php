<?php
/*
Plugin Name: Default to all Events
Description: If no year or month arguments are passed to your archive page requests, this simple add-on will show all applicable Events instead of truncating them to montly archives. 
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Events
*/

/*
Detail: <b>Note:</b> this may take time and resources if you have a lot of events.
*/ 

class Eab_Events_AllEventsDefault {
	
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Events_AllEventsDefault;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('eab-query_rewrite-before_query_replacement', array($this, 'bind_replacements'), 10, 2);
	}
	
	function bind_replacements ($year, $month) {
		if (!$year) {
			add_filter('eab-collection-upcoming-start_timestamp', array($this, 'all_events_start')); 
			add_filter('eab-collection-upcoming-end_timestamp', array($this, 'all_events_end')); 
			add_action('eab-query_rewrite-after_query_replacement', array($this, 'unbind_all_replacements'));
		} else if (!$month) {
			add_filter('eab-collection-upcoming-start_timestamp', array($this, 'yearly_events_start')); 
			add_filter('eab-collection-upcoming-end_timestamp', array($this, 'yearly_events_end')); 
			add_action('eab-query_rewrite-after_query_replacement', array($this, 'unbind_yearly_replacements'));
		}
	}

	function all_events_start () { return '1971-01-01 00:01'; }
	function all_events_end () {
		return '2037-01-01 23:59';
	}
	function unbind_all_replacements () {
		remove_filter('eab-collection-upcoming-start_timestamp', array($this, 'all_events_start')); 
		remove_filter('eab-collection-upcoming-end_timestamp', array($this, 'all_events_end')); 
	}
	
	function yearly_events_start () {
	    global $wp_query;
	    
	    return $wp_query->query_vars['event_year'] . '-01-01 00:01';
	}
	
	function yearly_events_end () {
	    global $wp_query;
	    
	    return $wp_query->query_vars['event_year'] . '-12-31 23:59';
	}
	
	function unbind_yearly_replacements () {
		remove_filter('eab-collection-upcoming-start_timestamp', array($this, 'yearly_events_start')); 
		remove_filter('eab-collection-upcoming-end_timestamp', array($this, 'yearly_events_end')); 
	}
	
}

if (!is_admin()) Eab_Events_AllEventsDefault::serve();
