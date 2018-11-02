<?php

abstract class WpmuDev_DatedItem {

	/**
	 * Packs event start dates as an array of (string)MySQL dates.
	 * @return array Start dates.
	 */
	abstract public function get_start_dates ();
	abstract public function has_no_start_time ($key=0);

	/**
	 * Packs event end dates as an array of (string)MySQL dates.
	 * @return array End dates.
	 */
	abstract public function get_end_dates ();
	abstract public function has_no_end_time ($key=0);

	/**
	 * Gets indexed start date as (string)MySQL date.
	 * Calls get_start_dates() if needed.
	 * @param int Date index
	 * @return string Date.
	 */
	public function get_start_date ($idx=0) {
		$dates = $this->get_start_dates();
		return isset($dates[$idx]) ? $dates[$idx] : false;
	}

	/**
	 * Gets indexed start date timestamp.
	 * @param int Date index
	 * @return int Date timestamp.
	 */
	public function get_start_timestamp ($idx=0) {
		return strtotime($this->get_start_date($idx));
	}

	/**
	 * Gets indexed end date as (string)MySQL date.
	 * Calls get_end_dates() if needed.
	 * @param int Date index
	 * @return string Date.
	 */
	public function get_end_date ($idx=0) {
		$dates = $this->get_end_dates();
		return isset($dates[$idx]) ? $dates[$idx] : false;
	}

	/**
	 * Gets last end date.
	 * @return string Date.
	 */
	public function get_last_end_date () {
		$dates = $this->get_end_dates();
		return end($dates);
	}

	/**
	 * Gets indexed start date timestamp.
	 * @param int Date index
	 * @return int Date timestamp.
	 */
	public function get_end_timestamp ($idx=0) {
		return strtotime($this->get_end_date($idx));
	}

	/**
	 * Gets last end date timestamp.
	 * @return int Date timestamp.
	 */
	public function get_last_end_timestamp () {
		return strtotime($this->get_last_end_date());
	}
}


abstract class WpmuDev_RecurringDatedItem extends WpmuDev_DatedItem {

	const RECURRANCE_DAILY = 'daily';
	const RECURRANCE_WEEKLY = 'weekly';
	const RECURRANCE_WEEK_COUNT = 'week_count';
	const RECURRANCE_DOW = 'dow';
	const RECURRANCE_MONTHLY = 'monthly';
	const RECURRANCE_YEARLY = 'yearly';

	const RECURRENCE_STATUS = 'recurrent';
	const RECURRENCE_TRASH_STATUS = 'recurrent_trash';

	/**
	 * @return array Hash of supported recurrance items and their labels
	 */
	abstract public function get_supported_recurrence_intervals ();

	/**
	 * @return array A list of instances occuring between start and end dates.
	 */
	abstract public function spawn_recurring_instances ($start, $end, $interval, $time_parts); // @TODO: REFACTOR

	/**
	 * @return mixed Recurrence interval (see constants)
	 */
	abstract public function get_recurrence ();

	/**
	 * @param mixed $key See constants. Optional recurrence constant. If not passed, will check if the item recurs at all.
	 * @return bool
	 */
	public function is_recurring ($key=false) {
		$recurrence = $this->get_recurrence();
		if (!$key) return $recurrence;
		else return ($key == $recurrence);
	}

	public function is_recurring_child () {
		return $this->get_parent();
	}
}


abstract class WpmuDev_DatedVenueItem extends WpmuDev_RecurringDatedItem {

	const VENUE_AS_ADDRESS = 'address';
	const VENUE_AS_MAP = 'map';

	/**
	 * Pack venue info, and return it.
	 * Venue type agnostic.
	 * @return string Venue
	 */
	abstract public function get_venue ();

	/**
	 * Does the event has venue info set?
	 * @return bool
	 */
	public function has_venue () {
		return $this->get_venue() ? true : false;
	}

	/**
	 * Returns venue as requested type.
	 * @param mixed $as Venue type (see constants)
	 * @param array $args Optional map overrides
	 * @return string Venue
	 */
	public function get_venue_location ($as=false, $args=array()) {
		$as = $as ? $as : self::VENUE_AS_ADDRESS;
		$venue = $this->get_venue();
		return (self::VENUE_AS_ADDRESS == $as) ? $this->_venue_to_address($venue) : $this->_venue_to_map($venue, $args);
	}

	/**
	 * Is the event venue a map?
	 * @param string $venue Optional venue
	 * @return bool
	 */
	public function has_venue_map ($venue=false) {
		if (!class_exists('AgmMapModel')) return false;
		$venue = $venue ? $venue : $this->get_venue();
		if (!$venue) {
			// Check associated map
			$map_id = get_post_meta($this->get_id(), 'agm_map_created', true);
			return $map_id ? true : false;
		}
		if (preg_match_all('/map id="([0-9]+)"/', $venue, $matches) > 0) return true;
		$map_id = get_post_meta($this->get_id(), 'agm_map_created', true);
		return $map_id ? true : false;
	}

	/**
	 * Get raw venue map.
	 * @param string $venue Venue
	 * @return mixed (array)Map or (bool)false
	 */
	public function get_raw_map ($venue=false) {
		$venue = $venue ? $venue : $this->get_venue();
		if (!class_exists('AgmMapModel')) return false;

		$map = $this->_get_venue_map($venue);
		return is_array($map) && !empty($map)
			? $map
			: false
		;
	}

	/**
	 * Convert venue map to address.
	 * @param string $venue Venue
	 * @return string Venue address
	 */
	private function _venue_map_to_address ($venue) {
		$venue = $venue ? $venue : $this->get_venue();
		$map = $this->_get_venue_map($venue);
		return @$map['markers'][0]['title'];
	}

	/**
	 * Venue address getting dispatcher.
	 * @param string $venue Venue
	 * @return string Venue address
	 */
	private function _venue_to_address ($venue) {
		$venue = $venue ? $venue : $this->get_venue();
		return $this->has_venue_map($venue) ? $this->_venue_map_to_address($venue) : $venue;
	}

	/**
	 * Get venue map tag.
	 * @param string $venue Venue
	 * @param array $args Optional map overrides
	 * @return string Map tag
	 */
	private function _venue_to_map ($venue, $args=array()) {
		$venue = $venue ? $venue : $this->get_venue();
		if (!class_exists('AgmMarkerReplacer')) return $venue;
		if (!$this->has_venue_map($venue)) return $venue;
		$codec = new AgmMarkerReplacer;

		if (empty($args)) {
			$args = apply_filters('eab-maps-map_defaults', array());
			$args = apply_filters('agm_google_maps-autogen_map-shortcode_attributes', $args);
		}

		return $codec->create_tag($this->_get_venue_map($venue), $args);
	}

	protected function _get_venue_map_id ($venue=false) {
		$venue = $venue ? $venue : $this->get_venue();
		$map_id = false;
		if (preg_match_all('/map id="([0-9]+)"/', $venue, $matches) <= 0) {
			$map_id = get_post_meta($this->get_id(), 'agm_map_created', true);
			if (!$map_id) return false;
		} else if (!isset($matches[1]) || !isset($matches[1][0])) return false;
		$map = $map_id ? $map_id : $matches[1][0];
		
		return apply_filters( 'eab_event_location_map', $map );
	}

	/**
	 * Get map object.
	 * @param string $venue Venue
	 * @param array $args Optional map overrides
	 * @return object Map object
	 */
	private function _get_venue_map ($venue, $args=array()) {
		$venue = $venue ? $venue : $this->get_venue();
		if (!class_exists('AgmMapModel')) return $venue;

		$map_id = $this->_get_venue_map_id($venue);
		if (!$map_id) return $venue;

		$model = new AgmMapModel();

		return $model->get_map($map_id);
	}
}



abstract class WpmuDev_DatedVenuePremiumItem extends WpmuDev_DatedVenueItem {

	/**
	 * Does the event require payment?
	 * @return bool
	 */
	public function is_premium () {
		return $this->get_price() ? true : false;
	}

	/**
	 * Packs price meta info and returns it.
	 * @return price
	 */
	abstract public function get_price ();

	abstract public function user_paid ($user_id=false);
}


abstract class WpmuDev_DatedVenuePremiumModel extends WpmuDev_DatedVenuePremiumItem {

	const POST_STATUS_TRASH = 'trash';

	abstract public function get_id();
	abstract public function get_title();
	abstract public function get_author();
	abstract public function get_excerpt();
	abstract public function get_excerpt_or_fallback();
	abstract public function get_content();
	abstract public function get_type();
	abstract public function get_parent();
	abstract public function is_trashed();
}



class Eab_EventModel extends WpmuDev_DatedVenuePremiumModel {

	const POST_TYPE = 'incsub_event';

	const STATUS_OPEN = 'open';
	const STATUS_CLOSED = 'closed';
	const STATUS_ARCHIVED = 'archived';
	const STATUS_EXPIRED = 'expired';

	const BOOKING_YES = 'yes';
	const BOOKING_MAYBE = 'maybe';
	const BOOKING_NO = 'no';

	public $_event_id;
	public $_event;

	private $_start_dates;
	private $_no_start_dates;
	private $_end_dates;
	private $_no_end_dates;

	private $_venue;
	private $_price;
	private $_status;

	public function __construct ($post=false) {
		$this->_event_id = is_object($post) ? (int)@$post->ID : $post;
		$this->_event = $post;
	}

	/**
	 * General purpose get_* override.
	 * Used for getting post properties.
	 */
	/*
	public function __call ($method, $args) {
		if ('get_' != substr($method, 0, 4)) return false;
		if (!$this->_event) return false;
		$what =  substr($method, 4);
		$property = "post_{$what}";
		if (isset($this->_event->$property)) return $this->_event->$property;
		if (isset($this->_event->$what)) return $this->_event->$what;
		return false;
	}
	*/

	public function get_id () {
		return $this->_event_id;
	}

	public function get_title () {
		return !empty($this->_event)
			? $this->_event->post_title
			: false
		;
	}

	public function get_author () {
		return !empty($this->_event)
			? $this->_event->post_author
			: false
		;
	}

	public function get_excerpt () {
		return !empty($this->_event)
			? $this->_event->post_excerpt
			: false
		;
	}

	public function get_excerpt_or_fallback ($final_length=false, $default_suffix='... ') {
		$excerpt = $this->get_excerpt();
		$excerpt = $excerpt
			? $excerpt
			: $this->get_content()
		;
		$excerpt = str_replace(array("\r\n", "\r", "\n"), " ", wp_strip_all_tags(strip_shortcodes($excerpt))); // Strip shortcodes, tags and newlines
		if (!function_exists('eab_call_template')) return $excerpt;

		$suffix = false;
		$length = eab_call_template('util_strlen', $excerpt);
		if ($final_length && $length > $final_length) {
			$length = $final_length;
			$suffix = $default_suffix;
			if (!preg_match('/...\s*$/', $excerpt)) {
				$excerpt = preg_replace('/\s*...\s*$/', '', $excerpt);
				$length -= strlen($suffix);
			}
		}
		$excerpt = eab_call_template('util_safe_substr', $excerpt, 0, $length);
		return "{$excerpt}{$suffix}";
	}

	public function get_content () {
		return !empty($this->_event)
			? $this->_event->post_content
			: false
		;
	}

	public function get_type () {
		return !empty($this->_event)
			? $this->_event->post_type
			: false
		;
	}

	public function get_parent () {
		return !empty($this->_event)
			? $this->_event->post_parent
			: false
		;
	}

	public function is_trashed () {
		return ($this->_event->post_status == self::POST_STATUS_TRASH);
	}

	public function is_published () {
		return in_array($this->_event->post_status, array('publish', self::RECURRENCE_STATUS));
	}

	public function get_categories () {
		$event_id = $this->get_id();
		$list = wp_cache_get('eab_events_category-' . $event_id);
		if ($list) return $list;

		$list = get_the_terms($event_id, 'eab_events_category');
		if (is_wp_error($list)) return false;
		wp_cache_set('eab_events_category-' . $event_id, $list);
		return $list;
	}

	public function get_category_ids () {
		$list = $this->get_categories();
		if (!$list) return false;
		$cats = array();
		foreach ($list as $category) $cats[] = $category->term_id;
		return $cats;
	}

	public function has_featured_image () {
		return has_post_thumbnail($this->get_id());
	}

	public function get_featured_image ($size=false) {
		$size = $size ? $size : 'thumbnail';
		return get_the_post_thumbnail($this->get_id(), $size);
	}

	public function get_featured_image_url ($size=false) {
		return wp_get_attachment_url(get_post_thumbnail_id($this->get_id()));
	}

	public function get_featured_image_id () {
		return get_post_thumbnail_id($this->get_id());
	}


/* ----- Date/Time methods ----- */

	public function has_no_start_time ($key=0) {
		if (empty($this->_no_start_dates) && !is_array($this->_no_start_dates)) {
			$raw = get_post_meta($this->get_id(), 'incsub_event_no_start');
			$raw = is_array($raw) ? $raw : array();
			$this->_no_start_dates = $raw;
		}
		return isset($this->_no_start_dates[$key]) ? $this->_no_start_dates[$key] : false;
	}

	public function has_no_end_time ($key=0) {
		if (empty($this->_no_end_dates) && !is_array($this->_no_end_dates)) {
			$raw = get_post_meta($this->get_id(), 'incsub_event_no_end');
			$raw = is_array($raw) ? $raw : array();
			$this->_no_end_dates = $raw;
		}
		return isset($this->_no_end_dates[$key]) ? $this->_no_end_dates[$key] : false;
	}

	/**
	 * Packs event start dates as an array of (string)MySQL dates.
	 * @return array Start dates.
	 */
	public function get_start_dates () {
		if ($this->_start_dates) return $this->_start_dates;
		$this->_start_dates = get_post_meta($this->get_id(), 'incsub_event_start');
		return $this->_start_dates;
	}

	/**
	 * Packs event end dates as an array of (string)MySQL dates.
	 * @return array End dates.
	 */
	public function get_end_dates () {
		if ($this->_end_dates) return $this->_end_dates;
		$this->_end_dates = get_post_meta($this->get_id(), 'incsub_event_end');
		return $this->_end_dates;
	}


/* ----- Recurrence methods ----- */

	public function get_supported_recurrence_intervals () {
		return array (
			self::RECURRANCE_DAILY => __('Day', Eab_EventsHub::TEXT_DOMAIN),
			self::RECURRANCE_WEEKLY => __('Week', Eab_EventsHub::TEXT_DOMAIN),
			self::RECURRANCE_WEEK_COUNT => __('Week Count', Eab_EventsHub::TEXT_DOMAIN),
			self::RECURRANCE_DOW => __('Day of the Week', Eab_EventsHub::TEXT_DOMAIN),
			self::RECURRANCE_MONTHLY => __('Month', Eab_EventsHub::TEXT_DOMAIN),
			self::RECURRANCE_YEARLY => __('Year', Eab_EventsHub::TEXT_DOMAIN),
		);
	}

	public function get_recurrence () {
		return get_post_meta($this->get_id(), 'eab_event_recurring', true);
	}

	public function get_recurrence_parts () {
		return get_post_meta($this->get_id(), 'eab_event_recurrence_parts', true);
	}

	public function get_recurrence_starts () {
		return get_post_meta($this->get_id(), 'eab_event_recurrence_starts', true);
	}

	public function get_recurrence_ends () {
		return get_post_meta($this->get_id(), 'eab_event_recurrence_ends', true);
	}

	protected function _get_recurring_children_ids () {
		$events = Eab_CollectionFactory::get_all_recurring_children_events($this);
		$ids = array();
		foreach ($events as $event) {
			$ids[] = $event->get_id();
		}
		return $ids;
	}

	public function trash_recurring_instances () {
		global $wpdb;
		$ids = $this->_get_recurring_children_ids();
		$id_str = join(',', $ids);
		$wpdb->query("UPDATE {$wpdb->posts} SET post_status='" . self::RECURRENCE_TRASH_STATUS . "' WHERE ID IN ({$id_str})");
	}

	public function untrash_recurring_instances () {
		global $wpdb;
		$ids = $this->_get_recurring_children_ids();
		$id_str = join(',', $ids);
		$wpdb->query("UPDATE {$wpdb->posts} SET post_status='" . self::RECURRENCE_STATUS . "' WHERE ID IN ({$id_str})");
	}

	public function delete_recurring_instances () {
		global $wpdb;
		$ids = $this->_get_recurring_children_ids();
		$id_str = join(',', $ids);
		$wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$id_str})");
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$id_str})");
	}

	public function spawn_recurring_instances ($start, $end, $interval, $time_parts) {
		$old_post_ids = false;
		if ($this->is_recurring()) {
			$old_post_ids = $this->_get_recurring_children_ids();
			$this->delete_recurring_instances();
			do_action('eab-events-recurring_instances-deleted', $this->get_id(), $this);
		}

		// Do this first, so we can short out on draft
		update_post_meta($this->get_id(), 'eab_event_recurring', $interval);
		update_post_meta($this->get_id(), 'eab_event_recurrence_parts', $time_parts);
		update_post_meta($this->get_id(), 'eab_event_recurrence_starts', $start);
		update_post_meta($this->get_id(), 'eab_event_recurrence_ends', $end);

		if ('draft' === $this->_event->post_status) return false; // Draft recurring events do not spawn instances

		$check_start = $start && checkdate((int)date('n', $start), (int)date('j', $start), (int)date('Y', $start));
		if (!$check_start) do_action('eab-debug-log_error', sprintf(
			__('Invalid interval start boundary timestamp: [%s]', Eab_EventsHub::TEXT_DOMAIN),
			$check_start
		));
		$check_end = $end && checkdate((int)date('n', $end), (int)date('j', $end), (int)date('Y', $end));
		if (!$check_end) do_action('eab-debug-log_error', sprintf(
			__('Invalid interval end boundary timestamp: [%s]', Eab_EventsHub::TEXT_DOMAIN),
			$check_end
		));
		if ($end < $start) do_action('eab-debug-log_error', sprintf(
			__('Invalid end boundary after start: [%s] - [%s]', Eab_EventsHub::TEXT_DOMAIN),
			$start, $end
		));

		$instances = $this->_get_recurring_instances_timestamps($start, $end, $interval, $time_parts);

		$duration = (float)@$time_parts['duration'];
		
		if( false !== strpos( $time_parts['duration'], ':') ){
			list( $hours, $minutes ) = explode( ':', $time_parts['duration'] );
			$duration = ( $minutes * MINUTE_IN_SECONDS ) + ( $hours * HOUR_IN_SECONDS );
		}

		$duration = $duration ? $duration : 1;

		$venue = $this->get_venue();
		$creation_time = date("Y-m-d H:i:s", eab_current_time());
		foreach ($instances as $key => $instance) {
			$post = array(
				'post_type' => self::POST_TYPE,
				'post_status' => self::RECURRENCE_STATUS,
				'post_parent' => $this->get_id(),
				'post_name' => "{$this->_event->post_name}-{$key}",
				'post_date' => $creation_time,
				'post_modified' => $creation_time,
				'post_title' => $this->get_title(),
				'post_author' => $this->get_author(),
				'post_excerpt' => $this->get_excerpt(),
				'post_content' => $this->get_content(),
				'to_ping' => '',
			);
// Also propagate discussion settings
			if (!empty($this->_event->comment_status)) $post['comment_status'] = $this->_event->comment_status;
			if (!empty($this->_event->ping_status)) $post['ping_status'] = $this->_event->ping_status;

			global $wpdb;
			if (false !== $wpdb->insert($wpdb->posts, $post)) {
				$post_id = $wpdb->insert_id;
				$featured_image_id = $this->has_featured_image()
					? $this->get_featured_image_id()
					: false
				;

				$event_cats = $this->get_category_ids();
				if ($event_cats) {
					wp_set_post_terms($post_id, $event_cats, 'eab_events_category', false);
					do_action('eab-events-recurrent_event_child-assigned_taxonomies', $post_id, $event_cats);
				}

				update_post_meta($post_id, 'incsub_event_start', date("Y-m-d H:i:s", $instance));
				update_post_meta($post_id, 'incsub_event_end', date("Y-m-d H:i:s", $instance + $duration ));
				update_post_meta($post_id, 'incsub_event_venue', $venue);
				update_post_meta($post_id, 'incsub_event_status', self::STATUS_OPEN);
				if ($this->is_premium()) {
					update_post_meta($post_id, 'incsub_event_paid', 1);
					update_post_meta($post_id, 'incsub_event_fee', $this->get_price());
				}
				if (!empty($featured_image_id)) {
					update_post_meta($post_id, '_thumbnail_id', $featured_image_id);
				}
				do_action('eab-events-recurrent_event_child-save_meta', $post_id);
			}
		}
        
        $new_post_ids = $this->_get_recurring_children_ids();
        
		if ($old_post_ids) {
			$this->_remap_bookings($old_post_ids, $new_post_ids);
		}
		
		do_action( 'eab-events-spawn_recurring_instances-after', $old_post_ids, $new_post_ids );
	}

	protected function _remap_bookings ($old, $new) {
		if (!$old || !$new || !is_array($old) || !is_array($new)) return false;
		$sql = 'UPDATE ' . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " SET event_id= CASE\n";
		foreach ($old as $idx => $value) {
			$new_value = !empty($new[$idx]) ? $new[$idx] : false;
			if (!$new_value) continue;
			$sql .= sprintf("WHEN event_id=%d THEN %d\n", (int)$value, (int)$new_value);
		}
		$sql .= "END\n";
		$sql .= "WHERE event_id IN(" . join(',', $old) . ")";

		global $wpdb;
		return $wpdb->query($sql);
	}

	protected function _get_recurring_instances_timestamps ($start, $end, $interval, $time_parts) {
		$instances = array();

		// Validate time
		if (!empty($time_parts['time'])) {
			$time_parts['time'] = preg_match('/\d+:\d+/i', $time_parts['time'])
				? $time_parts['time']
				: (int)$time_parts['time'] . ':00'
			;
		}

		if (self::RECURRANCE_DAILY == $interval) {
			for ($i = $start; $i <= $end; $i+=86400) {
				$timestamp = date("Y-m-d", $i) . ' ' . $time_parts['time'];
				$unix_timestamp = strtotime($timestamp);
				$check = $unix_timestamp >= $start && checkdate((int)date('n', $unix_timestamp), (int)date('j', $unix_timestamp), (int)date('Y', $unix_timestamp));
				if (!$unix_timestamp || !$check) do_action('eab-debug-log_error', sprintf(
					__('Invalid %s instance timestamp: [%s]', Eab_EventsHub::TEXT_DOMAIN),
					$interval,
					$timestamp
				));
				$instances[] = $unix_timestamp;
			}
		}

		if (self::RECURRANCE_WEEKLY == $interval) {
			$time_parts['weekday'] = is_array($time_parts['weekday']) ? $time_parts['weekday'] : array();
			for ($i = 0; $i<=6; $i++) {
				if (!in_array($i, $time_parts['weekday'])) continue;
				$sunday = strtotime("this Sunday", $start) < $start
					? strtotime("this Sunday", $start)
					: strtotime("last Sunday", $start)
				;
				$to_day = $i * 86400;
				$begin = $sunday + $to_day;
				$increment = 7*86400;
				for ($j = $begin; $j<=$end; $j+=$increment) {
					$timestamp = date('Y-m-d', $j) . ' ' . $time_parts['time'];
					$unix_timestamp = strtotime($timestamp);
					$check = $unix_timestamp >= $start && checkdate((int)date('n', $unix_timestamp), (int)date('j', $unix_timestamp), (int)date('Y', $unix_timestamp));
					if (!$unix_timestamp || !$check) do_action('eab-debug-log_error', sprintf(
						__('Invalid %s instance timestamp: [%s]', Eab_EventsHub::TEXT_DOMAIN),
						$interval,
						$timestamp
					));
					$instances[] = $unix_timestamp;
				}
			}
		}
		if (self::RECURRANCE_DOW == $interval) {
			$week_count = !empty($time_parts["week"]) ? $time_parts["week"] : 'first';
			$weekday = !empty($time_parts["weekday"]) ? $time_parts["weekday"] : 'Monday';

			$month_days = date('t', $start)*86400;

			for ($i = $start; $i <= $end; $i+=$month_days) {
				$month_days = date('t', $i)*86400;
				$first = strtotime(date("Y-m-01", $i));

				// PHP 5.3+ - strtotime has "of", we're good.
				// This is because of https://bugs.php.net/bug.php?id=53778
				$day = strtotime("{$week_count} {$weekday} of this month {$time_parts['time']}", $first);

				// "Fifth" test for supporting implementations
				if ($day && "fifth" === strtolower($week_count)) {
					// Special case, as not all months have this.
					$last = strtotime("last {$weekday} of this month {$time_parts['time']}", $first);
					$fourth = strtotime("fourth {$weekday} of this month {$time_parts['time']}", $first);
					if ($fourth === $last) continue; // This month doesn't have five $weekdays, so keep on going
					else if ($last > $fourth) $day = $last; // Oooh, but here we have a fifth occurance, go with that
				}

				if (!$day) {
					// No "of", meaning we're in pre-PHPv5.3 and the bug kicks in - so, we're left with non-timestamp
					$day = strtotime("{$week_count} {$weekday} this month {$time_parts['time']}", $first); // Get a bugged timestamp in pre-PHP5.3 compatible way
					// Now that we have a timestamp possibly affected pre-v5.3 bug, check the other conditions:
					// (1) are we explicitly told to fix the bug?
					// (1) is the first day of month actually the day of month we're after, and
					// (2) generated timestamp isn't first of the month
					if (defined('EAB_LEGACY_PHP_BUG_DOW_FIX') && EAB_LEGACY_PHP_BUG_DOW_FIX && strtolower($weekday) == strtolower(date("l", $first)) && 1 < (int)date("d", $day)) {
						// Get the same day last week
						$this_day_week_before = strtotime('-1 week', $day);
						// Check the result (1) against $start timestamp
						// (2) and make sure we're still in the same month
						// (3) and make sure the generated timestamp is the day name we're after
						// (4) to see if we already have this timestamp
						if (
							$start < $day && $start < $this_day_week_before // 1
							&&
							date("m", $day) === date("m", $this_day_week_before) // 2
							&&
							strtolower($weekday) == strtolower(date("l", $this_day_week_before)) // 3
							&&
							!in_array($this_day_week_before, $instances) // 4
						) {
							// First up, log this for support purposes
							do_action('eab-debug-log_error', sprintf(
								__('Possible DOW precision bug detected, overriding %s with %s for expression [%s %s this month], for %s pivot', Eab_EventsHub::TEXT_DOMAIN),
								$day, $this_day_week_before, // timestamps
								$week_count, $weekday, // expression
								$first // pivot
							));
							$day = $this_day_week_before;
						}
					}
				}

				if (!$day) do_action('eab-debug-log_error', sprintf(
					__('Invalid %s instance timestamp: [%s %s for %s]', Eab_EventsHub::TEXT_DOMAIN),
					$interval,
					$week_count, $weekday, $first
				));
				if ($day < $start) continue;
				$instances[] = $day;
			}
		}

		if (self::RECURRANCE_WEEK_COUNT == $interval) {
			$week_count = !empty($time_parts["week"]) && is_numeric($time_parts["week"]) ? (int)$time_parts["week"] : '1';
			$weekday = !empty($time_parts["weekday"]) ? $time_parts["weekday"] : 'Monday';

			$interval_days = $week_count * 7 * 86400;

			for ($i = $start; $i <= $end; $i+=$interval_days) {
				$day = strtotime("this {$weekday} {$time_parts['time']}", $i);
				if ($day < $start) continue;
				$instances[] = $day;
			}
		}

		if (self::RECURRANCE_MONTHLY == $interval) {
			$month_days = date('t', $start)*86400;
			for ($i = $start; $i <= $end; $i+=$month_days) {
				$month_days = date('t', $i)*86400;
				$timestamp = date("Y-m-d", $i) . ' ' . $time_parts['time'];
				$unix_timestamp = strtotime($timestamp);
				$check = $unix_timestamp >= $start && checkdate((int)date('n', $unix_timestamp), (int)date('j', $unix_timestamp), (int)date('Y', $unix_timestamp));
				if ( date( 'Y-m-d', $unix_timestamp ) > date( 'Y-m-d', $end ) )
					continue;

				if (!$unix_timestamp || !$check) do_action('eab-debug-log_error', sprintf(
					__('Invalid %s instance timestamp: [%s]', Eab_EventsHub::TEXT_DOMAIN),
					$interval,
					$timestamp
				));
				$instances[] = $unix_timestamp;
			}
		}

		if (self::RECURRANCE_YEARLY == $interval) {
			$year_days = (date('L', $start) ? 366 : 365) * 86400;
			for ($i = $start; $i <= $end; $i+=$year_days) {
				$year_days = (date('L', $i) ? 366 : 365) * 86400;
				$timestamp = date("Y-" . $time_parts['month'] . "-" . $time_parts['day'], $i) . ' ' . $time_parts['time'];
				$unix_timestamp = strtotime($timestamp);
				$check = $unix_timestamp >= $start && checkdate((int)date('n', $unix_timestamp), (int)date('j', $unix_timestamp), (int)date('Y', $unix_timestamp));
				if (!$unix_timestamp || !$check) do_action('eab-debug-log_error', sprintf(
					__('Invalid %s instance timestamp: [%s]', Eab_EventsHub::TEXT_DOMAIN),
					$interval,
					$timestamp
				));
				$instances[] = $unix_timestamp;
			}
		}
		return $instances;
	}


/* ----- Venue methods ----- */

	/**
	 * Pack venue info, and return it.
	 * Venue type agnostic.
	 * @return string Venue
	 */
	public function get_venue () {
		if ($this->_venue) return $this->_venue;
		$this->_venue = get_post_meta($this->get_id(), 'incsub_event_venue', true);
		return $this->_venue;
	}


/* ----- Price methods ----- */

	/**
	 * Packs price meta info and returns it.
	 * @return price
	 */
	public function get_price () {
		if ($this->_price) return apply_filters('eab-payment-event_price', $this->_price, $this->get_id());
		$this->_price = get_post_meta($this->get_id(), 'incsub_event_fee', true);
		return apply_filters('eab-payment-event_price', $this->_price, $this->get_id());
	}

	public function user_paid ($user_id=false) {
		$user_id = $this->_to_user_id($user_id);
		$booking_id = $this->get_user_booking_id($user_id);
		return $this->get_booking_paid($booking_id);
	}

/* ----- Status methods ----- */

	/**
	 * Is event open?
	 * @return bool
	 */
	public function is_open () {
		return (self::STATUS_OPEN == $this->get_status()) ? true : false;
	}

	/**
	 * Is event closed?
	 * @return bool
	 */
	public function is_closed () {
		return (self::STATUS_CLOSED == $this->get_status()) ? true : false;
	}

	/**
	 * Is event archived?
	 * @return bool
	 */
	public function is_archived () {
		return (self::STATUS_ARCHIVED == $this->get_status()) ? true : false;
	}

	/**
	 * Is event expired?
	 * @return bool
	 */
	public function is_expired () {
		return (self::STATUS_EXPIRED == $this->get_status()) ? true : false;
	}

	/**
	 * Pack and return event status info.
	 * @return mixed Event status (see constants)
	 */
	public function get_status () {
		if ($this->_status) return $this->_status;
		$this->_status = get_post_meta($this->get_id(), 'incsub_event_status', true);
		return $this->_status;
	}

	/**
	 * Packs and sets event status.
	 * @param mixed Event status (see constants)
	 * @return mixed Event status (see constants)
	 */
	public function set_status ($status) {
		$this->_status = $status;
		update_post_meta($this->get_id(), 'incsub_event_status', $status);
		return $this->_status;
	}

/* ----- Booking methods ----- */

	/**
	 * Does the event have some RSVPs?
	 * @param bool $coming Only count positive RSVPs (yes and maybe)
	 * @return array
	 */
	public function has_bookings($coming=true) {
		global $wpdb;

		return $coming
			? $wpdb->get_results($wpdb->prepare("SELECT id FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id = %d AND status != 'no' ORDER BY timestamp;", $this->get_id()))
			: $wpdb->get_results($wpdb->prepare("SELECT id FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id = %d ORDER BY timestamp;", $this->get_id()))
		;
	}

	/**
	 * Does the event children have some RSVPs?
	 * @param bool $coming Only count positive RSVPs (yes and maybe)
	 * @return array
	 */
	public function has_child_bookings($coming=true) {
		if (!$this->is_recurring()) return false;
		$children = array_filter(array_map('intval', $this->_get_recurring_children_ids()));
		if (empty($children)) return false;

		global $wpdb;

		return $coming
			? $wpdb->get_results("SELECT id FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id IN(" . join(',', $children) . ") AND status != 'no' ORDER BY timestamp;")
			: $wpdb->get_results("SELECT id FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id IN(" . join(',', $children) . ") ORDER BY timestamp;")
		;
	}

	/**
	 * Returns a list of promised bookings.
	 * @param  mixed $status Booking status (const), or false for all possible bookings.
	 * @param  mixed $since UNIX timestamp, or false for no lower time limit
	 * @return array A list of booking results.
	 */
	public static function get_bookings ($status=false, $since=false) {
		$rsvps = array(
			self::BOOKING_YES,
			self::BOOKING_MAYBE,
			self::BOOKING_NO,
		);
		$status = $status && in_array($status, $rsvps)
			? "status='" . $status . "'"
			: "status IN('" . join("', '", $rsvps) . "')"
		;

		$since = $since
			? "AND timestamp > '" . date('Y-m-d H:i:s', $since) . "'"
			: ''
		;
		global $wpdb;
		return $wpdb->get_results("SELECT * FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE {$status} {$since} ORDER BY timestamp");
	}

	public function get_event_bookings ($status=false, $since=false) {
		$rsvps = array(
			self::BOOKING_YES,
			self::BOOKING_MAYBE,
			self::BOOKING_NO,
		);
		$status = $status && in_array($status, $rsvps)
			? "status='" . $status . "'"
			: "status IN('" . join("', '", $rsvps) . "')"
		;

		$since = $since
			? "AND timestamp > '" . date('Y-m-d H:i:s', $since) . "'"
			: ''
		;
		global $wpdb;
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE {$status} {$since} AND event_id = %d ORDER BY timestamp", $this->get_id()));
	}

	public function get_rsvps () {
		global $wpdb;
		$rsvps = array(
			self::BOOKING_YES => array(),
			self::BOOKING_MAYBE => array(),
			self::BOOKING_NO => array(),
		);
		$bookings = $wpdb->get_results($wpdb->prepare("SELECT user_id, status FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id = %d ORDER BY timestamp;", $this->get_id()));
		foreach ($bookings as $booking) {
			$user_data = get_userdata($booking->user_id);
			$rsvps[$booking->status][] = $user_data->user_login;
		}
		return $rsvps;
	}

	public function get_user_booking_id ($user_id=false) {
		$user_id = (int)$this->_to_user_id($user_id);
		if (!$user_id) return false;

		global $wpdb;
		return (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id = %d AND user_id = %d;", $this->get_id(), $user_id));
	}

	public static function get_booking ($booking_id) {
		$booking_id = (int)$booking_id;
		if (!$booking_id) return false;

		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE id = %d;", $booking_id));
	}

	public function get_user_booking ($user_id=false) {
		$user_id = (int)$this->_to_user_id($user_id);
		if (!$user_id) return false;

		global $wpdb;
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id = %d AND user_id = %d;", $this->get_id(), $user_id));
	}

	public static function get_booking_status ($booking_id) {
		$booking_id = (int)$booking_id;
		if (!$booking_id) return false;

		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT status FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE id = %d;", $booking_id));
	}

	public function get_user_booking_status ($user_id=false) {
		$user_id = (int)$this->_to_user_id($user_id);
		if (!$user_id) return false;

		global $wpdb;
		return $wpdb->get_var($wpdb->prepare("SELECT status FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id = %d AND user_id = %d;", $this->get_id(), $user_id));
	}

	public function user_is_coming ($strict=false, $user_id=false) {
		$user_id = $this->_to_user_id($user_id);
		$checks = array(self::BOOKING_YES);
		if (!$strict) $checks[] = self::BOOKING_MAYBE;
		return in_array($this->get_user_booking_status($user_id), $checks);
	}

	public function get_booking_paid ($booking_id) {
		return $this->get_booking_meta($booking_id, 'booking_transaction_key');
	}

	public static function get_booking_meta ($booking_id, $meta_key, $default=false) {
		$booking_id = (int)$booking_id;
		if (!$booking_id) return $default;

		global $wpdb;
		$meta_value = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_META_TABLE) . " WHERE booking_id = %d AND meta_key = %s;", $booking_id, $meta_key));
		return $meta_value ? $meta_value : $default;
	}

	public static function update_booking_meta ($booking_id, $meta_key, $meta_value) {
		$booking_id = (int)$booking_id;
		if (!$booking_id) return false;

		global $wpdb;
		$meta_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_META_TABLE) . " WHERE booking_id = %d AND meta_key = %s;", $booking_id, $meta_key));
		if (!$meta_id) {
			return $wpdb->query($wpdb->prepare("INSERT INTO " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_META_TABLE) . " VALUES (null, %d, %s, %s);", $booking_id, $meta_key, $meta_value));
		} else {
			return $wpdb->query($wpdb->prepare("UPDATE " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_META_TABLE) . " SET meta_value = %s WHERE id = %d;", $meta_value, $meta_id));
		}
	}

	public function cancel_attendance ($user_id=false) {

		$user_id = (int)$this->_to_user_id($user_id);
        if ( ! $user_id ) return false;

		// Optional via filter
		// Can't edit attendance for paid premium events
		if ( $this->user_paid( $user_id ) ) {

			if ( 
				apply_filters( 'eab-rsvp_forbid-cancel-paid', false, $this, $user_id ) && 
				$this->is_premium()
			) {

				return false;
			}

			// If it is paid we need to remove payment too
			// In case we need to keep the payment, use the `eab-rsvp_can-cancel-payment` filter
			if( 
				apply_filters( 'eab-rsvp_can-cancel-payment', true, $this, $user_id ) && 
				! $this->cancel_payment( $user_id ) ) {
					return false;
			}

		}
		
		global $wpdb;
		return $wpdb->query($wpdb->prepare("UPDATE " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " SET status='no' WHERE event_id = %d AND user_id = %d LIMIT 1;", $this->get_id(), $user_id));
		
	}
	
	public function cancel_payment( $user_id = false ) {

		if ( ! $user_id ) return false;
 
 		global $wpdb;
		
		$booking_id = $this->get_user_booking_id( $user_id );
		$meta_table = Eab_EventsHub::tablename( Eab_EventsHub::BOOKING_META_TABLE );
		$query = $wpdb->prepare( "DELETE FROM {$meta_table} WHERE booking_id = %d AND meta_key = 'booking_transaction_key'", $booking_id );		

		// Used for MarketPress Integration
		do_action( 'eab-rsvp_before_cancel_payment', $this, $user_id );

		return $wpdb->query( $query );

 	}

	public function delete_attendance ($user_id=false) {

		$user_id = (int)$this->_to_user_id($user_id);
		if (!$user_id) return false;
		if ($this->is_premium() && $this->user_paid($user_id)) return false; // Can't edit attendance for paid premium events

		global $wpdb;
		return $wpdb->query($wpdb->prepare("DELETE FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE event_id = %d AND user_id = %d LIMIT 1;", $this->get_id(), $user_id));

	}
	
	public function add_attendance ($user_id, $status) {
		$user_id = (int)$this->_to_user_id($user_id);
		if (!$user_id) return false;

		if ($this->get_user_booking_id($user_id)) return false; // We already have attendance info for this guy.

		global $wpdb;
		return $wpdb->query(
		    $wpdb->prepare("INSERT INTO ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." VALUES(null, %d, %d, NOW(), %s) ON DUPLICATE KEY UPDATE `status` = %s;", $this->get_id(), $user_id, $status, $status)
		);
	}

/* ----- Meta operations ----- */

	public function set_meta ($key, $value) {
		return update_post_meta($this->get_id(), $key, $value);
	}

	public function get_meta ($key) {
		return get_post_meta($this->get_id(), $key, true);
	}


	private function _to_user_id ($user_id) {
		$user_id = (int)$user_id;
		if (!$user_id) {
			global $current_user;
			$user_id = $current_user->ID;
		}
		return (int)$user_id;
	}

	public function from_network () {
		return !empty($this->_event->blog_id) ? $this->_event->blog_id : false;
	}

	public function cache_data () {
		$this->get_start_dates();
		$this->has_no_start_time();
		$this->get_end_dates();
		$this->has_no_end_time();
		$this->get_venue();
		$this->get_price();
		$this->get_status();
	}
}
