<?php

/* ----- Setup procedures ----- */

/**
 * Pure static filtering namespace.
 */
class Eab_Filter {

	/**
	 * Sets up universal date ordering for Events.
	 */
	public static function start_date_ordering_set_up () {
		if (!has_filter('posts_where', array('Eab_Filter', 'ordering_by_start_date_posts_where'))) add_filter('posts_where', array('Eab_Filter', 'ordering_by_start_date_posts_where'));
		if (!has_filter('posts_join', array('Eab_Filter', 'ordering_by_start_date_join_postmeta'))) add_filter('posts_join', array('Eab_Filter', 'ordering_by_start_date_join_postmeta'));
		if (!has_filter('posts_orderby', array('Eab_Filter', 'ordering_by_start_date_posts_orderby'))) add_filter('posts_orderby', array('Eab_Filter', 'ordering_by_start_date_posts_orderby'));
	}

	/**
	 * Tears down universal date ordering for Events.
	 */
	public static function start_date_ordering_tear_down () {
		remove_filter('posts_where', array('Eab_Filter', 'ordering_by_start_date_posts_where'));
		remove_filter('posts_join', array('Eab_Filter', 'ordering_by_start_date_join_postmeta'));
		remove_filter('posts_orderby', array('Eab_Filter', 'ordering_by_start_date_posts_orderby'));
	}

	public static function ordering_by_start_date_posts_orderby ($q) {
		global $wpdb;
		$allowed = array("ASC", "DESC");
		$direction = (defined('EAB_COLLECTION_DATE_ORDERING_DIRECTION') && in_array(strtoupper(EAB_COLLECTION_DATE_ORDERING_DIRECTION), $allowed))
			? strtoupper(EAB_COLLECTION_DATE_ORDERING_DIRECTION)
			: "ASC"
		;
		$direction = apply_filters('eab-ordering-date_ordering_direction', $direction);
		return "eab_meta.meta_value {$direction}";
	}

	public static function ordering_by_start_date_join_postmeta ($q) {
		global $wpdb;
		return "{$q} JOIN {$wpdb->postmeta} AS eab_meta ON ({$wpdb->posts}.ID = eab_meta.post_id)";
	}

	public static function ordering_by_start_date_posts_where ($q) {
		return "{$q} AND eab_meta.meta_key='incsub_event_start'";
	}

}


/* ----- Core ----- */

// Core WP postmeta fetching functions don't order by meta_id - fix that...
// ... unless explicitly told not to.
if (!(defined('EAB_SKIP_FORCED_META_ID_ORDERING') && EAB_SKIP_FORCED_META_ID_ORDERING)) {

	/**
	 * Late-bound `$wpdb` query filter.
	 */
	function _eab_wpdb_filter_postmeta_query ($q) {
		global $wpdb;
		if (!preg_match('/^\s*SELECT/i', $q)) return $q;
		$postmeta = preg_quote($wpdb->postmeta, '/');
		if (preg_match("/\b{$postmeta}\b/", $q) && !preg_match('/\bORDER BY\b/i', $q)) $q .= " ORDER BY {$wpdb->postmeta}.meta_id";
		remove_filter('query', '_eab_wpdb_filter_postmeta_query'); // Clean up
		return $q;
	}

	/**
	 * Late binding filter for forced query ordering on postmeta requests.
	 */
	function _eab_filter_meta_query ($check) {
		add_filter('query', '_eab_wpdb_filter_postmeta_query');
		return $check;
	}
	add_filter('get_post_metadata', '_eab_filter_meta_query');
}
// End Core WP postmeta filtering

// Category sorting in default WP requests
if (!(defined('EAB_SKIP_FORCED_CATEGORY_ORDERING') && EAB_SKIP_FORCED_CATEGORY_ORDERING)) {	

	function _eab_tear_down_event_categories_for_ordering ($posts) {
		Eab_Filter::start_date_ordering_tear_down();
		return $posts;
	}

	function _eab_dispatch_event_categories_for_ordering ($query) {
		if (is_admin()) return false;
		if (!is_main_query()) return false;
		if (empty($query->query_vars['eab_events_category'])) return false;
		Eab_Filter::start_date_ordering_set_up();
		add_filter('found_posts', '_eab_tear_down_event_categories_for_ordering');
	}
	add_action('pre_get_posts', '_eab_dispatch_event_categories_for_ordering', 1);
}
// End Category sorting in default WP requests

// Admin side - ensure Maps availability for subscribers
function eab_to_agm__ensure_subscribers_maps () {
	global $post;
	if (!class_exists('AgmAdminMaps')) return false;
	if (empty($post->post_type)) return false;
	if (Eab_EventModel::POST_TYPE != $post->post_type) return false;
	echo <<<EO_EAB_AGM_SUBSCRIBER_SCRIPT
<script type="text/javascript">
(function ($) {
	if ($('#media-buttons').length || $("#wp-content-media-buttons").length) return false;
	$("body").append('<div id="wp-content-media-buttons" style="display:none" />');
})(jQuery);
</script>
EO_EAB_AGM_SUBSCRIBER_SCRIPT;
}
add_action('admin_footer-post-new.php', 'eab_to_agm__ensure_subscribers_maps', 99);
add_action('admin_footer-post.php', 'eab_to_agm__ensure_subscribers_maps', 99);
// End Admin side - ensure Maps availability for subscribers
 
// Render event categories as CSS classes
function eab__event_categories_to_classes ($cls, $event_id) {
	$event = new Eab_EventModel(get_post($event_id));
	$taxonomies = $event->get_categories();
	if (empty($taxonomies)) return $cls;

	$classes = array_values(wp_list_pluck($taxonomies, 'slug'));
	if (empty($classes)) return $cls;

	return trim($cls . ' ' . join(' ', array_map('sanitize_html_class', $classes)));
}
add_filter('eab-render-css_classes', 'eab__event_categories_to_classes', 10, 2);
// End event categories rendering

// Feeds - add Event dates
if (!(defined('EAB_SKIP_FEED_DATES_INJECTION') && EAB_SKIP_FEED_DATES_INJECTION)) {
	function eab_to_feed__add_feed_event_dates ($content) {
    	global $post;
    	if (empty($post->post_type) || Eab_EventModel::POST_TYPE != $post->post_type) return $content;
    	return $content . '<br />' . eab_call_template('get_event_dates', $post);
    }
	add_filter('the_excerpt_rss', 'eab_to_feed__add_feed_event_dates');
	add_filter('the_content_feed', 'eab_to_feed__add_feed_event_dates');
}
// End Feeds - Event date adding



/* ----- Plugins ----- */

// Ultimate Facebook Events posting - prevent override if explicitly told so
if (!(defined('EAB_SKIP_DEFAULT_ULTIMATE_FACEBOOK_EVENT_FILTERING') && EAB_SKIP_DEFAULT_ULTIMATE_FACEBOOK_EVENT_FILTERING)) {
	function eab_to_wdfb__spawn_event_model ($post) {
		if (!class_exists('Eab_EventModel')) return false;
		if (Eab_EventModel::POST_TYPE != $post->post_type) return false;
		return new Eab_EventModel($post);
	}

	function eab_to_wdfb__process_start_time ($time, $post) {
		$event = eab_to_wdfb__spawn_event_model($post);
		return $event ? $event->get_start_timestamp() : $time;
	}
	add_filter('wdfb-autopost-events-start_time', 'eab_to_wdfb__process_start_time', 10, 2);

	function eab_to_wdfb__process_end_time ($time, $post) {
		$event = eab_to_wdfb__spawn_event_model($post);
		return $event ? $event->get_end_timestamp() : $time;
	}
	add_filter('wdfb-autopost-events-end_time', 'eab_to_wdfb__process_end_time', 10, 2);

	function eab_to_wdfb__process_venue ($venue, $post) {
		$event = eab_to_wdfb__spawn_event_model($post);
		return $event ? $event->get_venue() : $venue;
	}
	add_filter('wdfb-autopost-events-location', 'eab_to_wdfb__process_venue', 10, 2);
}
// End Ultimate Facebook Events posting


// WPML translated Events content with directory URL setup
if (!(defined('EAB_SKIP_DEFAULT_WPML_REWRITE_FILTERING') && EAB_SKIP_DEFAULT_WPML_REWRITE_FILTERING)) {
	
	function eab_to_wpml__rewrite_rules ($rules) {
		global $sitepress;
		$wpml_settings = $sitepress->get_settings();
		if ($wpml_settings['language_negotiation_type']==1  && $sitepress->get_current_language()!=$sitepress->get_default_language()) {
			$data = Eab_Options::get_instance();
			$slug = $data->get_option('slug');
			$rules = $sitepress->rewrite_rules_filter(
				(array)$rules + Eab_EventsHub::get_rewrite_rules($slug)
			);
		}
		return $rules;
	}

	function eab_to_wpml__rebind_rewrites () {
		if (!class_exists('SitePress')) return false; // Do nothing if not required
		global $sitepress;
		if (!is_object($sitepress)) return false; // Yeah...

		// Okay, now kill whatever it is that WPML does and rebind.
		remove_filter('option_rewrite_rules', array($sitepress, 'rewrite_rules_filter'));
		add_filter('option_rewrite_rules', 'eab_to_wpml__rewrite_rules');
	}
	add_action('init', 'eab_to_wpml__rebind_rewrites');
}
// End WPML translated Events content with directory URL setup