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
		if (preg_match("/\b{$postmeta}\b/", $q) && !preg_match('/\bORDER BY\b/i', $q) && !preg_match('/\bLIMIT\b/i', $q)) $q .= " ORDER BY {$wpdb->postmeta}.meta_id";
		remove_filter('query', '_eab_wpdb_filter_postmeta_query'); // Clean up
		return $q;
	}

	/**
	 * Late binding filter for forced query ordering on postmeta requests.
	 */
	function _eab_filter_meta_query ($check, $object_id, $meta_key, $single) {
		if (!preg_match('/incsub_event_(.*?)start$/', $meta_key) && !preg_match('/incsub_event_(.*)end$/', $meta_key)) return $check;

		if (!(defined('EAB_SKIP_FORCED_META_ID_SORT_OPTIMIZATION') && EAB_SKIP_FORCED_META_ID_SORT_OPTIMIZATION)) {
			// First, let's see what we have custom-cached
			$cache = wp_cache_get($object_id, 'post_meta_sorted');
			if (!empty($cache) && isset($cache[$meta_key])) return $single
				? maybe_unserialize($cache[$meta_key][0])
				: array_map('maybe_unserialize', $cache[$meta_key])
			;

			// Nothing... move on
			global $wpdb;
			$query = $wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY {$wpdb->postmeta}.meta_id", $object_id);
			$metas = $wpdb->get_results($query, ARRAY_A);
			if (!empty($metas)) {
				$cache = array();
				foreach ($metas as $meta) {
					if (empty($meta['meta_key'])) continue;
					$key = $meta['meta_key'];
					if (!isset($cache[$key]) || !is_array($cache[$key])) $cache[$key] = array();
					$cache[$key][] = $meta['meta_value'];
				}
				if (!empty($cache)) wp_cache_add($object_id, $cache, 'post_meta_sorted');
				if (isset($cache[$meta_key])) return $single
					? maybe_unserialize($cache[$meta_key][0])
					: array_map('maybe_unserialize', $cache[$meta_key])
				;
			}
		} else {
			wp_cache_delete($object_id, 'post_meta'); // Throw away the caches!
			add_filter('query', '_eab_wpdb_filter_postmeta_query');
		}
		return $check;
	}
	add_filter('get_post_metadata', '_eab_filter_meta_query', 10, 4);
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
		if (!$query->is_main_query()) return false;
		if (empty($query->query_vars['eab_events_category'])) return false;
		Eab_Filter::start_date_ordering_set_up();
		add_filter('found_posts', '_eab_tear_down_event_categories_for_ordering');
	}
	add_action('pre_get_posts', '_eab_dispatch_event_categories_for_ordering', 1);
}
// End Category sorting in default WP requests

// Archive sorting and pagination in default WP requests
function _eab_dispatch_event_archives($query) {
	if ( ! empty( $query->query['_avoid_pgp_action'] ) && 1 == $query->query['_avoid_pgp_action'] ) return;
    if ( is_admin() || ! is_post_type_archive('incsub_event') ) return;
    $data = Eab_Options::get_instance();
    if ( $pagination = $data->get_option('pagination') ) {
        $query->set( 'posts_per_page', $pagination );
    }
    if ( $data->get_option('ordering_direction') ) {
        add_filter( 'eab-ordering-date_ordering_direction', 'eab_ordering_date_ordering_direction_cb' );
    }
}
add_action('pre_get_posts', '_eab_dispatch_event_archives', 1);
// End Archive sorting and pagination in default WP requests

// Exclude expired posts from eab_events_category archive pages
function _eab_hide_past_events_from_archive_pages( $query ) {

	if ( is_tax( 'eab_events_category' ) && $query->is_main_query() && ! is_admin() ) {
	
		$meta_query = array(
             array(
                'key' 		=> 'incsub_event_start',
                'value' 	=> gmdate( 'Y-m-d H:i:s' ),
                'compare' 	=>'>=',
             ),
		);

		$query->set('meta_query',$meta_query);

	}

}

add_action( 'pre_get_posts', '_eab_hide_past_events_from_archive_pages', 10 );
// End Exclude expired posts from eab_events_category archive pages

function eab_ordering_date_ordering_direction_cb() {
	return 'DESC';
}

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


// Script concatenation start
if (defined('EAB_OPTMIZIE_SCRIPT_LOAD') && EAB_OPTMIZIE_SCRIPT_LOAD) {
	class Eab_FrontendDependencies {

		private static $_cache = array();

		private function __construct () {}

		public static function serve () {
			$me = new self;
			$me->_add_hooks();
		}

		private function _add_hooks () {
			if (!is_admin()) {
				add_action('script_loader_src', array($this, 'optimize_scripts'), 10, 2);
				add_action('wp_enqueue_scripts', array($this, 'enqueue_optimized_scripts'), 999);
				add_action('wp_footer', array($this, 'write_optimized_cache'), 99);
			}
			add_action('wp_ajax_eab_get_optimized_scripts', array($this, 'output_cached_scripts'));
			add_action('wp_ajax_nopriv_eab_get_optimized_scripts', array($this, 'output_cached_scripts'));
		}

		public function optimize_scripts ($src, $handle) {
			if ('eab-optimized' === $handle) return $src; // We're good :)
			if (!preg_match('/^eab[-_]/', $handle)) return $src;
			if ($this->_endpoint_has_optimized_scripts()) return false; // We know we're good here, so don't add this

			$filepath = $this->_eab_src_to_filepath($src);
			if (!$filepath) return $src; // Unknown file

			$this->_endpoint_add_to_optimized_cache(file_get_contents($filepath));
		}

		public function enqueue_optimized_scripts () {
			wp_enqueue_script('eab-optimized', admin_url('admin-ajax.php?action=eab_get_optimized_scripts&key=' . $this->_get_request_key()), array('jquery'), Eab_EventsHub::CURRENT_VERSION);
		}

		public function write_optimized_cache () {
			if (empty($this->_cache)) return false;
			$this->_endpoint_set_optimized_cache(join("\n", $this->_cache));
		}

		public function output_cached_scripts () {
			$data = stripslashes_deep($_GET);
			$key = !empty($data['key']) ? $data['key'] : false;
			if (empty($key)) die;

			$cache = $this->_endpoint_get_optimized_cache($key);
			if (empty($cache)) die;

			header("Content-type: text/javascript");
			die($cache);
		}

		private function _eab_src_to_filepath ($src) {
			$src = preg_replace('/\?.*$/', '', $src);
			$raw = preg_replace('/' . preg_quote(trailingslashit(plugins_url(basename(EAB_PLUGIN_DIR))), '/') . '/', EAB_PLUGIN_DIR, $src);
			$filepath = escapeshellcmd($raw);
			return file_exists($filepath)
				? $filepath
				: false
			;
		}

		private function _get_request_key () {
			global $wp;
			$url = home_url($wp->request); // Use simplified baseurl fetching
			return 'eab-js-' . md5($url);
		}

		private function _endpoint_has_optimized_scripts () {
			$cache = $this->_endpoint_get_optimized_cache();
			return !empty($cache);
		}

		private function _endpoint_get_optimized_cache ($key=false) {
			$key = !empty($key) ? $key : $this->_get_request_key();
			return get_transient($key);
		}
		
		private function _endpoint_set_optimized_cache ($cache) {
			$key = $this->_get_request_key();
			return set_transient($key, $cache, DAY_IN_SECONDS);
		}

		private function _endpoint_add_to_optimized_cache ($cache) {
			$this->_cache[] = $cache;
		}

	}
	Eab_FrontendDependencies::serve();
}
// End script concatenation

// Twitter delta threshold correction
define('EAB_OAUTH_TIMESTAMP_DELTA_THRESHOLD', 10, true);
