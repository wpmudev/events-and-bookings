<?php
/*
Plugin Name: Noindex meta for Events
Description: Adds noindex meta element to your recurring event instances and non-current archives.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Integration
*/


class Eab_Events_Nre {

	const FUTURE_ONLY = 'future';
	const PAST_ONLY = 'past';
	const ALL_INSTANCES = 'all';

	private $_data;
	private $_options = array();

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_options = wp_parse_args($this->_data->get_option('eab-events-nre'), array(
			'noindex_scope' => self::ALL_INSTANCES,
			'noindex_archives' => self::ALL_INSTANCES,
			'nofollow_too' => false,
		));
	}

	public static function serve () {
		$me = new Eab_Events_Nre;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		if (is_admin()) {
			add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
			add_filter('eab-settings-before_save', array($this, 'save_settings'));
		} else {
			add_action('wp_head', array($this, 'dispatch_noindex'), 1);
		}
	}

	function show_settings () {
		$_temporal = array(
			self::FUTURE_ONLY => __('Future only', Eab_EventsHub::TEXT_DOMAIN),
			self::PAST_ONLY => __('Past only', Eab_EventsHub::TEXT_DOMAIN),
			self::ALL_INSTANCES => __('All recurring instances', Eab_EventsHub::TEXT_DOMAIN),
		);
		$_temporal_archives = array(
			self::FUTURE_ONLY => __('Future only', Eab_EventsHub::TEXT_DOMAIN),
			self::PAST_ONLY => __('Past only', Eab_EventsHub::TEXT_DOMAIN),
			self::ALL_INSTANCES => __('All event archives', Eab_EventsHub::TEXT_DOMAIN),
		);
		$nofollow = (int)$this->_options['nofollow_too'] ? 'checked="checked"' : '';
		$archives = (int)$this->_options['noindex_archives'] ? 'checked="checked"' : '';
?>
<div id="eab-settings-nre" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Noindex meta for Events', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item" style="line-height:1.8em">
			<label for="eab-events-nre-noindex_scope"><?php _e('Add <code>noindex</code> to my recurring events instances', Eab_EventsHub::TEXT_DOMAIN); ?>:</label><br />
			<?php 
			foreach ($_temporal as $key => $label) { 
				$selected = ($key == $this->_options['noindex_scope']) ? 'checked="checked"' : '';
				echo "<input type='radio' name='eab-events-nre[noindex_scope]' id='eab-events-nre-noindex_scope-{$key}' value='{$key}' {$selected} />&nbsp; ";
				echo "<label for='eab-events-nre-noindex_scope-{$key}'>{$label}</label><br />";
			} 
			?>
		</div>
		<div class="eab-settings-settings_item" style="line-height:1.8em">
			<label for="eab-events-nre-noindex_archives"><?php _e('Add <code>noindex</code> to my event archives', Eab_EventsHub::TEXT_DOMAIN); ?>:</label><br />
			<?php 
			foreach ($_temporal_archives as $key => $label) { 
				$selected = ($key == $this->_options['noindex_archives']) ? 'checked="checked"' : '';
				echo "<input type='radio' name='eab-events-nre[noindex_archives]' id='eab-events-nre-noindex_archives-{$key}' value='{$key}' {$selected} />&nbsp; ";
				echo "<label for='eab-events-nre-noindex_archives-{$key}'>{$label}</label><br />";
			} 
			?>
		</div>
		<div class="eab-settings-settings_item">
			<label for="eab-events-nre-nofollow_too">
				<input type="hidden" name="eab-events-nre[nofollow_too]" value="" />
				<input type="checkbox" id="eab-events-nre-nofollow_too" name="eab-events-nre[nofollow_too]" value="1" <?php echo $nofollow; ?> />
				<?php _e('Add <code>nofollow</code> too.', Eab_EventsHub::TEXT_DOMAIN); ?>
			</label>
		</div>
	</div>
</div>
<?php		
	}
	
	function save_settings ($options) {
		$options['eab-events-nre'] = @$_POST['eab-events-nre'];
		return $options;
	}

	function dispatch_noindex () {
		if (is_singular()) $this->_dispatch_singular_noindex();
		else $this->_dispatch_archive_noindex();
	}

	private function _check_known_conflicts () {
		// Greg's High Performance SEO
		if (class_exists('gregsHighPerformanceSEO')) {
			global $ghpseo;
			remove_action('wp_head', array($ghpseo,'robots'), 4);
		}
		// Joost's WordPress SEO
		if (class_exists('WPSEO_Frontend')) {
			remove_all_filters('wpseo_robots');
			add_filter('wpseo_robots', '__return_false');
		}
		// SEO Ultimate
		if (class_exists('SEO_Ultimate') && class_exists('SU_MetaRobots')) {
			remove_all_filters('su_meta_robots');
			add_filter('su_meta_robots', '__return_false');
		}
		// Infinite SEO doesn't add robots unless told to.

		// Allow others to join in...
		do_action('eab-noindex_meta-conflict_check');
	}

	private function _dispatch_archive_noindex () {
		if (!is_archive()) return false;
		$type = get_query_var('post_type');
		if (Eab_EventModel::POST_TYPE != $type) return false;

		if (self::ALL_INSTANCES != $this->_options['noindex_archives']) {
			$meta = get_query_var('meta_query');
			if (!$meta) return false;
			$time = current_time('timestamp');
			$timestamp_1 = $timestamp_2 = false;
			foreach ($meta as $item) {
				if ('incsub_event_start' == $item['key']) $timestamp_1 = strtotime($item['value']);
				if ('incsub_event_end' == $item['key']) $timestamp_2 = strtotime($item['value']);
			}
			$in_past = (bool)($time > max($timestamp_1, $timestamp_2));
			$in_future = (bool)($time < min($timestamp_1, $timestamp_2));

			if (!$in_past && !$in_future) return false; // Present, determined by current timestamp being within viewed archive slice.
			if (self::PAST_ONLY == $this->_options['noindex_archives'] && $in_future) return false;
			if (self::FUTURE_ONLY == $this->_options['noindex_archives'] && $in_past) return false;
		}

		// Unbind SEO plugin output
		$this->_check_known_conflicts();

		$robots = (int)$this->_options['nofollow_too'] ? 'noindex,nofollow' : 'noindex';

		echo "<meta name='robots' content='{$robots}' />\n";

	}

	private function _dispatch_singular_noindex () {
		$event = $this->_get_active_event();
		if (!$event) return false;

		if (self::ALL_INSTANCES != $this->_options['noindex_scope']) {
			$time = current_time('timestamp');
			if (self::PAST_ONLY == $this->_options['noindex_scope'] && $event->get_start_timestamp() > $time) return false;
			if (self::FUTURE_ONLY == $this->_options['noindex_scope'] && $event->get_start_timestamp() < $time) return false;
		}

		// Unbind SEO plugin output
		$this->_check_known_conflicts();

		$robots = (int)$this->_options['nofollow_too'] ? 'noindex,nofollow' : 'noindex';

		echo "<meta name='robots' content='{$robots}' />\n";
	}

	private function _get_active_event () {
		global $post;
		if (!is_singular()) return false;
		if (!$post || !is_object($post) || !isset($post->post_type) || Eab_EventModel::POST_TYPE != $post->post_type) return false;
		
		$event = $post instanceof Eab_EventModel ? $post : new Eab_EventModel($post);
		if (!$event->is_recurring_child()) return false;
		return $event;
	}

}

Eab_Events_Nre::serve();