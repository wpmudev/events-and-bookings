<?php

/**
 * Handles network integration,
 * through Post Indexer.
 */
class Eab_Network {
	
	private function __construct () {}
	
	/**
	 * Available only on multisite.
	 */
	public static function serve () {
		if (!is_multisite()) return false;
		$me = new Eab_Network;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('plugins_loaded', array($this, 'load_dependencies'));
	}
	
	/**
	 * Check if PI is available, and proceed if it is.
	 */
	function load_dependencies () {
		if (!eab_has_post_indexer()) return false;
		add_action('widgets_init', array($this, 'load_widgets'), 20);
	}
	
	/**
	 * We have all we need, let's register widgets.
	 */
	function load_widgets () {
		require_once EAB_PLUGIN_DIR . 'lib/widgets/NetworkUpcoming_Widget.class.php';
		register_widget('Eab_NetworkUpcoming_Widget');
	}
	
/* ----- Model procedures ----- */

	/**
	 * Gets a list of upcoming events.
	 * Only the events that are not yet over will be returned.
	 */
	public static function get_upcoming_events ($limit=5) {
		if (!eab_has_post_indexer()) return array();
		$limit = (int)$limit ? (int)$limit : 5;
		
		global $wpdb;
		$result = array();
		$count = 0;
		$pi_table = eab_pi_get_table();
		$pi_published = eab_pi_get_post_date();
		$pi_blog_id = eab_pi_get_blog_id();
		$pi_post_id = eab_pi_get_post_id();
		$raw_network_events = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}{$pi_table} WHERE post_type='incsub_event' ORDER BY {$pi_published} DESC");
		if (!$raw_network_events) return $result;
		
		foreach ($raw_network_events as $event) {
			if ($count == $limit) break;
			switch_to_blog($event->$pi_blog_id);
			$post = get_post($event->$pi_post_id);
			$tmp_event_instance = new Eab_EventModel($post);
			$tmp_event_instance->cache_data();
			if ($tmp_event_instance->is_expired()) continue;
			$post->blog_id = $event->$pi_blog_id;
			$result[] = $post;
			$count++;
			restore_current_blog();
		}
		return apply_filters( 'eab_get_upcoming_events', $result, $limit );
	}
        
        public static function get_archive_events ($limit=5) {
		if (!eab_has_post_indexer()) return array();
		$limit = (int)$limit ? (int)$limit : 5;
		
		global $wpdb;
		$result = array();
		$count = 0;
		$pi_table = eab_pi_get_table();
		$pi_published = eab_pi_get_post_date();
		$pi_blog_id = eab_pi_get_blog_id();
		$pi_post_id = eab_pi_get_post_id();
		$raw_network_events = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}{$pi_table} WHERE post_type='incsub_event' ORDER BY {$pi_published} DESC");
		if (!$raw_network_events) return $result;
		
		foreach ($raw_network_events as $event) {
			if ($count == $limit) break;
			switch_to_blog($event->$pi_blog_id);
			$post = get_post($event->$pi_post_id);
			$tmp_event_instance = new Eab_EventModel($post);
			$tmp_event_instance->cache_data();
			//if (!$tmp_event_instance->is_archived()) continue;
			$post->blog_id = $event->$pi_blog_id;
			$result[] = $post;
			$count++;
			restore_current_blog();
		}
		return apply_filters( 'eab_get_upcoming_events', $result, $limit );
	}
}
