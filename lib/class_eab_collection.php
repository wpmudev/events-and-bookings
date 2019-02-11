<?php

/**
 * Abstract collection root class.
 */
abstract class WpmuDev_Collection {

	/**
	 * Holds a WP_Query instance.
	 */
	private $_query;

	/**
	 * Constructs WP_Query object with overriden arugment set.
	 * NEVER NEVER EVER call this directly. Use Factory instead.
	 * @param array WP_Query arguments.
	 */
	public function __construct ($args) {
		$query = $this->build_query_args($args);
		$this->_query = new WP_Query($query);
	}

	/**
	 * Returns a WP_Query instance.
	 */
	public function to_query () {
		return apply_filters('wpmudev-query', $this->_query);
	}

	abstract public function build_query_args ($args);
	abstract public function to_collection ();
}

/**
 * Abstract Event collection root class.
 */
abstract class Eab_Collection extends WpmuDev_Collection {

	/**
	 * Converts WP_Query result set into an array of Eab_EventModel objects.
	 * @return array
	 */
	public function to_collection () {
		$events = array();
		$query = $this->to_query();
		if (!$query->posts) return $events;
		foreach ($query->posts as $post) {
			$events[] = new Eab_EventModel($post);
		}
		return apply_filters('eab-collection', $events);
	}

}


/**
 * General purpose time-restricted collection.
 */
abstract class Eab_TimedCollection extends Eab_Collection {

	protected $_timestamp;

	/**
	 * NEVER NEVER EVER call this directly. Use Factory instead.
	 */
	public function __construct ($timestamp=false, $args=array()) {
		$this->_timestamp = $timestamp ? $timestamp : eab_current_time();
		$query = $this->build_query_args($args);
		parent::__construct($query);
	}

	public function get_timestamp () {
		return $this->_timestamp;
	}

}


/**
 * Upcoming events time-restricted collection implementation.
 */
class Eab_UpcomingCollection extends Eab_TimedCollection {

	public function __construct ($timestamp=false, $args=array()) {
		Eab_Filter::start_date_ordering_set_up();
		add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
		parent::__construct($timestamp, $args);
		Eab_Filter::start_date_ordering_tear_down();
	}

	public function propagate_direction_filter ($direction) {
		return apply_filters('eab-collection-date_ordering_direction', $direction);
	}

	public function build_query_args ($args) {
		
		$hide_old = apply_filters( 'eab-collection/hide_old', true );
		$time = $this->get_timestamp();
		
		if( $hide_old ){
			$current_month = date( 'm' );
			$calendar_month = date( 'm', $time );

			if ( $current_month >= $calendar_month ){
				$time = time();	
			}			
		}

		$year = (int)date('Y', $time);
		$month = date('m', $time);
		$day = date('d', $time);
		if( $month != date( 'm' ) ){
			$day = '01';
		}
		$time = strtotime("{$year}-{$month}-{$day}");

		$forbidden_statuses = array(Eab_EventModel::STATUS_CLOSED);
		if (!isset($args['incsub_event'])) { // If not single
			$forbidden_statuses[] = Eab_EventModel::STATUS_EXPIRED;
		}
		$forbidden_statuses = apply_filters('eab-collection-forbidden_statuses', $forbidden_statuses);

		$start_day = $day ? sprintf('%02d', $day) : $day;
		$start_month = $month ? sprintf("%02d", $month) : date('m');
		if ($start_month < 12) {
			$end_month = sprintf("%02d", (int)$month+1);
			$end_year = $year;
		} else {
			$end_month = '01';
			$end_year = $year+1;
		}

		if (!isset($args['posts_per_page'])) $args['posts_per_page'] = apply_filters('eab-collection-upcoming-max_results', EAB_MAX_UPCOMING_EVENTS);
		
		$args = array_merge(
			$args,
			array(
			 	'post_type' => 'incsub_event',
			 	'post_status' => array('publish', Eab_EventModel::RECURRENCE_STATUS),
				'suppress_filters' => false,
				'meta_query' => array(
					array(
		    			'key' => 'incsub_event_start',
		    			'value' => apply_filters('eab-collection-upcoming-end_timestamp', "{$end_year}-{$end_month}-01 00:00"),
		    			'compare' => '<',
		    			'type' => 'DATETIME'
					),
					array(
		    			'key' => 'incsub_event_end',
		    			'value' => apply_filters('eab-collection-upcoming-start_timestamp', "{$year}-{$start_month}-{$start_day} 00:00"),
		    			'compare' => '>=',
		    			'type' => 'DATETIME'
					),
					array(
						'key' => 'incsub_event_status',
						'value' => $forbidden_statuses,
						'compare' => 'NOT IN',
					),
				),
			)
		);
		return $args;
	}
}


/**
 * events time-restricted collection (Date Range) implementation.
 * 
 */
class Eab_DateRangeCollection extends Eab_TimedCollection {

	public function __construct ($timestamp=false, $args=array()) {
		Eab_Filter::start_date_ordering_set_up();
		add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
		parent::__construct($timestamp, $args);
		Eab_Filter::start_date_ordering_tear_down();
	}

	public function propagate_direction_filter ($direction) {
		return apply_filters('eab-collection-date_ordering_direction', $direction);
	}

	public function build_query_args ($args) {
		$forbidden_statuses = array(Eab_EventModel::STATUS_CLOSED);
		if (!isset($args['incsub_event'])) { // If not single
			$forbidden_statuses[] = Eab_EventModel::STATUS_EXPIRED;
		}
		$forbidden_statuses = apply_filters('eab-collection-forbidden_statuses', $forbidden_statuses);

		if (!isset($args['posts_per_page'])) $args['posts_per_page'] = -1;
		
		$args = array_merge(
			$args,
			array(
			 	'post_type' => 'incsub_event',
			 	'post_status' => array('publish', Eab_EventModel::RECURRENCE_STATUS),
				'suppress_filters' => false,
				'meta_query' => array(
					array(
		    			'key' => 'incsub_event_start',
					'value' => apply_filters('eab-collection-date_range_end', date('Y-m', eab_current_time()) . '-01 23:59'),
		    			'compare' => '<',
		    			'type' => 'DATETIME'
					),
					array(
		    			'key' => 'incsub_event_end',
					'value' => apply_filters('eab-collection-date_range_start', date('Y-m-d', eab_current_time()) . ' 00:00'),
		    			'compare' => '>=',
		    			'type' => 'DATETIME'
					),
					array(
						'key' => 'incsub_event_status',
						'value' => $forbidden_statuses,
						'compare' => 'NOT IN',
					),
				),
			)
		);
		return $args;
	}
}

class Eab_DateRangeArchiveCollection extends Eab_TimedCollection {
	
		public function __construct ($timestamp=false, $args=array()) {
			Eab_Filter::start_date_ordering_set_up();
			add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
			parent::__construct($timestamp, $args);
			Eab_Filter::start_date_ordering_tear_down();
		}
	
		public function propagate_direction_filter ($direction) {
			return apply_filters('eab-collection-date_ordering_direction', $direction);
		}
	
		public function build_query_args ($args) {
		    $forbidden_statuses = array(Eab_EventModel::STATUS_CLOSED);
		    if (!isset($args['incsub_event'])) { // If not single
			    $forbidden_statuses[] = Eab_EventModel::STATUS_EXPIRED;
		    }
		    $forbidden_statuses = apply_filters('eab-collection-forbidden_statuses', $forbidden_statuses);

		    if (!isset($args['posts_per_page'])) $args['posts_per_page'] = -1;

			$args = array_merge(
				$args,
				array(
					 'post_type' 		=> 'incsub_event',
					 'post_status' 		=> array('publish', Eab_EventModel::RECURRENCE_STATUS),
					'suppress_filters' 	=> false,
					'meta_query' 		=> array(
						array(
							'key' 		=> 'incsub_event_start',
							'value' 	=> apply_filters('eab-collection-date_range_end', date('Y-m', eab_current_time()) . '-01 23:59'),
							'compare' 	=> '<',
							'type' 		=> 'DATETIME'
						),
						array(
							'key' 		=> 'incsub_event_end',
							'value' 	=> apply_filters('eab-collection-date_range_start', date('Y-m-d', eab_current_time()) . ' 00:00'),
							'compare' 	=> '>=',
							'type' 		=> 'DATETIME'
						),
						array(
							'key' 	=> 'incsub_event_status',
							'value' => Eab_EventModel::STATUS_ARCHIVED,
						),
					)
				)
			);
			return $args;
		}
	}

/**
 * Upcoming events time-restricted collection (5 weeks period) implementation.
 * @author: Hakan Evin
 */
class Eab_UpcomingWeeksCollection extends Eab_TimedCollection {

	const WEEK_COUNT = 5;

	public function __construct ($timestamp=false, $args=array()) {
		if (!defined('EAB_COLLECTION_UPCOMING_WEEKS_COUNT')) define('EAB_COLLECTION_UPCOMING_WEEKS_COUNT', self::WEEK_COUNT, true);

		Eab_Filter::start_date_ordering_set_up();
		add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
		parent::__construct($timestamp, $args);
		Eab_Filter::start_date_ordering_tear_down();
	}

	public function propagate_direction_filter ($direction) {
		return apply_filters('eab-collection-date_ordering_direction', $direction);
	}

	public function build_query_args ($args) {
		// Changes by Hakan
		// Commented lines were not removed intentionally.
		$time = $this->get_timestamp();

		$forbidden_statuses = array(Eab_EventModel::STATUS_CLOSED);
		if (!isset($args['incsub_event'])) { // If not single
			$forbidden_statuses[] = Eab_EventModel::STATUS_EXPIRED;
		}
		$forbidden_statuses = apply_filters('eab-collection-forbidden_statuses', $forbidden_statuses);

		if (!isset($args['posts_per_page'])) $args['posts_per_page'] = -1;

		$weeks = apply_filters('eab-collection-upcoming_weeks-week_number', EAB_COLLECTION_UPCOMING_WEEKS_COUNT);
		$weeks = is_numeric($weeks) ? $weeks : self::WEEK_COUNT;

		$args = array_merge(
			$args,
			array(
			 	'post_type' => 'incsub_event',
			 	'post_status' => array('publish', Eab_EventModel::RECURRENCE_STATUS),
				'suppress_filters' => false,
				'meta_query' => array(
					array(
		    			'key' => 'incsub_event_start',
						'value' => date( "Y-m-d H:i", $time + $weeks * 7 * 86400 ), // Events whose starting dates are $weeks weeks from now
		    			'compare' => '<',
		    			'type' => 'DATETIME'
					),
					array(
		    			'key' => 'incsub_event_end',
						'value' => date( "Y-m-d H:i", $time ), // Events those already started now
		    			'compare' => '>=',
		    			'type' => 'DATETIME'
					),
					array(
						'key' => 'incsub_event_status',
						'value' => $forbidden_statuses,
						'compare' => 'NOT IN',
					),
				)
			)
		);
		return $args;
	}
}

class Eab_UpcomingWeeksArchiveCollection extends Eab_TimedCollection {
	
		const WEEK_COUNT = 5;
	
		public function __construct ($timestamp=false, $args=array()) {
			if (!defined('EAB_COLLECTION_UPCOMING_WEEKS_COUNT')) define('EAB_COLLECTION_UPCOMING_WEEKS_COUNT', self::WEEK_COUNT, true);
	
			Eab_Filter::start_date_ordering_set_up();
			add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
			parent::__construct($timestamp, $args);
			Eab_Filter::start_date_ordering_tear_down();
		}
	
		public function propagate_direction_filter ($direction) {
			return apply_filters('eab-collection-date_ordering_direction', $direction);
		}
	
		public function build_query_args ($args) {
			// Changes by Hakan
			// Commented lines were not removed intentionally.
			$time = $this->get_timestamp();
	
			if (!isset($args['posts_per_page'])) $args['posts_per_page'] = -1;
	
			$weeks = apply_filters( 'eab-collection-upcoming_weeks-archive-week_number', EAB_COLLECTION_UPCOMING_WEEKS_COUNT );
			$weeks = is_numeric($weeks) ? $weeks : self::WEEK_COUNT;
	
			$args = array_merge(
				$args,
				array(
					 'post_type' 		=> 'incsub_event',
					 'post_status' 		=> array('publish', Eab_EventModel::RECURRENCE_STATUS),
					'suppress_filters' 	=> false,
					'meta_query' 		=> array(
						array(
							'key' 		=> 'incsub_event_start',
							'value' 	=> date( "Y-m-d H:i", $time + $weeks * 7 * 86400 ), // Events whose starting dates are $weeks weeks from now
							'compare' 	=> '<',
							'type' 		=> 'DATETIME'
						),
						array(
							'key' 		=> 'incsub_event_end',
							'value' 	=> date( "Y-m-d H:i", $time ), // Events those already started now
							'compare' 	=> '>=',
							'type' 		=> 'DATETIME'
						),
						array(
							'key' 	=> 'incsub_event_status',
							'value' => Eab_EventModel::STATUS_ARCHIVED,
						),
					)
				)
			);
			return $args;
		}
	}


/**
 * Popular (most RSVPd) events collection implementation.
 */
class Eab_PopularCollection extends Eab_Collection {

	public function build_query_args ($args) {
		global $wpdb;
		$result = $wpdb->get_col("SELECT event_id, COUNT(event_id) as cnt FROM " . Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) . " WHERE status IN ('yes', 'maybe') GROUP BY event_id ORDER BY cnt DESC");
		$args = array_merge(
			$args,
			array(
				'post__in' => array_values($result),
				'post_type' => 'incsub_event',
				'post_status' => array('publish', Eab_EventModel::RECURRENCE_STATUS),
				'posts_per_page' => -1,
			)
		);
		return $args;
	}
}


/**
 * Events organized by the user
 */
class Eab_OrganizerCollection extends Eab_Collection {

	public function build_query_args ($arg) {
		$arg = (int)$arg;
		$args = array(
			'author' => $arg,
			'post_type' => 'incsub_event',
			'post_status' => array('publish', Eab_EventModel::RECURRENCE_STATUS),
			'posts_per_page' => EAB_OLD_EVENTS_EXPIRY_LIMIT,
		);
		return $args;
	}
}

/**
 * Old events time-restricted collection implementation.
 * Old events are events with last end time in the past,
 * but not yet expired.
 */
class Eab_OldCollection extends Eab_TimedCollection {

	public function build_query_args ($args) {

		$args = array_merge(
			$args,
			array(
			 	'post_type' => 'incsub_event',
				'post_status' => 'any',
				'suppress_filters' => false,
				'posts_per_page' => EAB_OLD_EVENTS_EXPIRY_LIMIT,
				'meta_query' => array(
					array(
		    			'key' => 'incsub_event_status',
		    			'value' => Eab_EventModel::STATUS_OPEN,
					),
					array(
		    			'key' => 'incsub_event_end',
		    			'value' => date("Y-m-d H:i:s", $this->get_timestamp()),
		    			'compare' => '<',
		    			'type' => 'DATETIME'
					),
				)
			)
		);
		return $args;
	}
}

/**
 * All archived events
 */
class Eab_ArchivedCollection extends Eab_Collection {

	public function build_query_args ($args, $timestamp = false) {

		if ( !isset( $args['posts_per_page'] ) ) $args['posts_per_page'] = -1;

		$args = array_merge(
			$args,
			array(
			 	'post_type' 		=> 'incsub_event',
				'post_status' 		=> 'any',
				'meta_query' 		=> array(
					array(
		    			'key' 	=> 'incsub_event_status',
		    			'value' => Eab_EventModel::STATUS_ARCHIVED,
					),
				),
			)
		);
		if ( $timestamp ) {
			$args['meta_query'][0] = array(
				'key' 		=> 'incsub_event_start',
				'value' 	=> date( "Y-m-d H:i", $timestamp ),
				'compare' 	=> '>=',
				'type' 		=> 'DATETIME'
			);
		}
		return $args;
	}
}

/**
 * All expired events
 */
class Eab_ExpiredCollection extends Eab_Collection {
	public function __construct ($args=array()) {
		Eab_Filter::start_date_ordering_set_up();
		add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
		parent::__construct($args);
		Eab_Filter::start_date_ordering_tear_down();
	}

	public function propagate_direction_filter ($direction) {
		return apply_filters('eab-collection-date_ordering_direction', $direction);
	}

	public function build_query_args ($original) {

		$args = array_merge(
			$original,
			array(
			 	'post_type' => 'incsub_event',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
		    			'key' => 'incsub_event_status',
		    			'value' => Eab_EventModel::STATUS_EXPIRED,
					),
				)
			)
		);
		if (!empty($original['posts_per_page'])) $args['posts_per_page'] = $original['posts_per_page'];
		return $args;
	}
}

class Eab_AllRecurringChildrenCollection extends Eab_Collection {

	public function build_query_args ($arg) {
		if (!$arg instanceof WpmuDev_DatedVenuePremiumModel) return $arg;
		$status = $arg->is_trashed()
			? WpmuDev_RecurringDatedItem::RECURRENCE_TRASH_STATUS
			: WpmuDev_RecurringDatedItem::RECURRENCE_STATUS
		;
		$args = array (
			'post_type' => 'incsub_event',
			'post_status' => $status,
			'post_parent' => $arg->get_id(),
			'posts_per_page' => -1,
			'orderby' => 'ID',
		);
		return $args;
	}
}

class Eab_ArchivedRecurringChildrenCollection extends Eab_AllRecurringChildrenCollection {
	public function build_query_args ($arg) {
		$args = parent::build_query_args($arg);
		$args['meta_query'] = array(
			array(
				'key' => 'incsub_event_status',
				'value' => Eab_EventModel::STATUS_ARCHIVED,
			),
		);
		return $args;
	}

}

/**
 * Upcoming events time-restricted collection (Daily) implementation.
 * 
 */
class Eab_DailyCollection extends Eab_TimedCollection {

	public function __construct ($timestamp=false, $args=array()) {
		Eab_Filter::start_date_ordering_set_up();
		add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
		parent::__construct($timestamp, $args);
		Eab_Filter::start_date_ordering_tear_down();
	}

	public function propagate_direction_filter ($direction) {
		return apply_filters('eab-collection-date_ordering_direction', $direction);
	}

	public function build_query_args ($args) {
		    $forbidden_statuses = array(Eab_EventModel::STATUS_CLOSED);
		    if (!isset($args['incsub_event'])) { // If not single
			    $forbidden_statuses[] = Eab_EventModel::STATUS_EXPIRED;
		    }
		    $forbidden_statuses = apply_filters('eab-collection-forbidden_statuses', $forbidden_statuses);

		    if (!isset($args['posts_per_page'])) $args['posts_per_page'] = -1;

		$date = apply_filters('eab-collection-daily_events_date', date('Y-m-d', eab_current_time()));

		$args = array_merge(
			$args,
			array(
			 	'post_type' => 'incsub_event',
			 	'post_status' => array('publish', Eab_EventModel::RECURRENCE_STATUS),
				'suppress_filters' => false,
				'meta_query' => array(
					array(
		    			'key' => 'incsub_event_start',
						'value' => $date . ' 23:59', // Event for start day
		    			'compare' => '<',
		    			'type' => 'DATETIME'
					),
					array(
		    			'key' => 'incsub_event_end',
						'value' => $date . ' 00:00', // Events those already started now
		    			'compare' => '>=',
		    			'type' => 'DATETIME'
					),
					array(
						'key' => 'incsub_event_status',
						'value' => $forbidden_statuses,
						'compare' => 'NOT IN',
					),
				)
			)
		);
		return $args;
	}
}

class Eab_DailyArchiveCollection extends Eab_TimedCollection {
	
		public function __construct ($timestamp=false, $args=array()) {
			Eab_Filter::start_date_ordering_set_up();
			add_filter('eab-ordering-date_ordering_direction', array($this, 'propagate_direction_filter'));
			parent::__construct($timestamp, $args);
			Eab_Filter::start_date_ordering_tear_down();
		}
	
		public function propagate_direction_filter ($direction) {
			return apply_filters('eab-collection-date_ordering_direction', $direction);
		}
	
		public function build_query_args ($args) {
		    $forbidden_statuses = array(Eab_EventModel::STATUS_CLOSED);
		    if (!isset($args['incsub_event'])) { // If not single
			    $forbidden_statuses[] = Eab_EventModel::STATUS_EXPIRED;
		    }
		    $forbidden_statuses = apply_filters('eab-collection-forbidden_statuses', $forbidden_statuses);

		    if (!isset($args['posts_per_page'])) $args['posts_per_page'] = -1;

		    $date = apply_filters('eab-collection-daily_events_date', date('Y-m-d', eab_current_time()));

			$args = array_merge(
				$args,
				array(
					 'post_type' 		=> 'incsub_event',
					 'post_status' 		=> array('publish', Eab_EventModel::RECURRENCE_STATUS),
					'suppress_filters' 	=> false,
					'meta_query' 		=> array(
						array(
							'key' 		=> 'incsub_event_start',
							'value' 	=> $date . ' 23:59', // Event for start day, // Events whose starting dates are $weeks weeks from now
							'compare' 	=> '<',
							'type' 		=> 'DATETIME'
						),
						array(
							'key' 		=> 'incsub_event_end',
							'value' 	=> $date . ' 00:00', // Event for start day, // Events those already started now
							'compare' 	=> '>=',
							'type' 		=> 'DATETIME'
						),
						array(
							'key' 	=> 'incsub_event_status',
							'value' => Eab_EventModel::STATUS_ARCHIVED,
						),
					)
				)
			);
			return $args;
		}
	}

/**
 * Factory class for spawning collections.
 * Pure static class.
 */
class Eab_CollectionFactory {

	private function __construct () {}

	/**
	 * events date range factory method
	 * @return array Eab_DateRangeCollection instance
	 */
	public static function get_date_range ($timestamp=false, $args=array()) {
		$me = new Eab_DateRangeCollection($timestamp, $args);
		return $me->to_query();
	}

	/**
	 * events date range factory method
	 * @return array date range events list
	 */
	public static function get_date_range_events ($timestamp=false, $args=array()) {
		$me = new Eab_DateRangeCollection($timestamp, $args);
		return $me->to_collection();
	}

	public static function get_date_range_archive_events ($timestamp=false, $args=array()) {
		$me = new Eab_DateRangeArchiveCollection($timestamp, $args);
		return $me->to_collection();
	}

	/**
	 * Daily events factory method
	 * @return array Daily events list
	 */
	public static function get_daily_events ($timestamp=false, $args=array()) {
		$me = new Eab_DailyCollection($timestamp, $args);
		return $me->to_collection();
	}

	/**
	 * Daily events factory method
	 * @return array Eab_DailyCollection instance
	 */
	public static function get_daily ($timestamp=false, $args=array()) {
		$me = new Eab_DailyCollection($timestamp, $args);
		return $me->to_query();
	}

	public static function get_daily_archive_events ($timestamp=false, $args=array()) {
		$me = new Eab_DailyArchiveCollection($timestamp, $args);
		return $me->to_collection();
	}

	/**
	 * Upcoming events query factory method
	 * @return object Eab_UpcomingCollection instance
	 */
	public static function get_upcoming ($timestamp=false, $args=array()) {
		$me = new Eab_UpcomingCollection($timestamp, $args);
		return $me->to_query();
	}

	/**
	 * Upcoming events factory method
	 * @return array Upcoming events list
	 */
	public static function get_upcoming_events ($timestamp=false, $args=array()) {
		$me = new Eab_UpcomingCollection($timestamp, $args);
		return $me->to_collection();
	}

	/**
	 * Upcoming events weeks factory method
	 * @return array Eab_UpcomingWeeksCollection instance
	 */
	public static function get_upcoming_weeks ($timestamp=false, $args=array()) {
		$me = new Eab_UpcomingWeeksCollection($timestamp, $args);
		return $me->to_query();
	}

	/**
	 * Upcoming events weeks factory method
	 * @return array Upcoming events list
	 */
	public static function get_upcoming_weeks_events ($timestamp=false, $args=array()) {
		$me = new Eab_UpcomingWeeksCollection($timestamp, $args);
		return $me->to_collection();
	}

	/**
	 * Old events query factory method.
	 * @return object Eab_OldCollection instance
	 */
	public static function get_old ($timestamp=false, $args=array()) {
		$me = new Eab_OldCollection($timestamp, $args);
		return $me->to_query();
	}

	/**
	 * Old events factory method
	 * @return array Old events list
	 */
	public static function get_old_events ($timestamp=false, $args=array()) {
		$me = new Eab_OldCollection($timestamp, $args);
		return $me->to_collection();
	}

	/**
	 * Popular events query factory method.
	 * @return object Eab_PopularCollection instance
	 */
	public static function get_popular ($args=array()) {
		$me = new Eab_PopularCollection($args);
		return $me->to_query();
	}

	/**
	 * Popular events factory method
	 * @return array Popular events list
	 */
	public static function get_popular_events ($args=array()) {
		$me = new Eab_PopularCollection($args);
		return $me->to_collection();
	}

	public static function get_all_recurring_children_events ($event) {
		$me = new Eab_AllRecurringChildrenCollection($event);
		return $me->to_collection();
	}

	public static function get_user_organized_events ($user_id) {
		$me = new Eab_OrganizerCollection($user_id);
		return $me->to_collection();
	}

	public static function get_expired_events ($args=array()) {
		$me = new Eab_ExpiredCollection($args);
		return $me->to_collection();
	}

	public static function get_archive_events ( $args=array(), $timestamp=false ) {
		$me = new Eab_ArchivedCollection($args,$timestamp);
		return $me->to_collection();
	}

	public static function get_upcoming_weeks_archive_events ($timestamp=false, $args=array()) {
		$me = new Eab_UpcomingWeeksArchiveCollection($timestamp, $args);
		return $me->to_collection();
	}
}
