<?php
/**
 * Holds calendar abstract hub class, 
 * and concrete implementations.
 * V1.3
 */

/**
 * Calendar table hub class.
 */
abstract class WpmuDev_CalendarTable {
	
	protected $_events = array();
	protected $_current_timestamp;
	
	public function __construct ($events) {
		$this->_events = $events;
		// To follow WP Start of week setting
		if ( !$this->start_of_week = get_option('start_of_week') )
			$this->start_of_week = 0;
	}
	/**
	 * Arranges days array acc. to start of week, e.g 1234560 (Week starting with Monday)
	 * @ days: input array
	 */	
	public function arrange( $days ) {
		if ( $this->start_of_week ) {
			for ( $n = 1; $n<=$this->start_of_week; $n++ )
				array_push( $days, array_shift( $days ) );
		}

		return $days;
	}
	
	public function get_timestamp () {
		return $this->_current_timestamp;
	}
	
	public function get_month_calendar ( $timestamp=false ) {
		$date = $timestamp ? $timestamp : current_time('timestamp');
		
		$this->_current_timestamp = $date;
		
		$year 	= date('Y', $date);
		$month 	= date('m', $date);
		$time 	= strtotime("{$year}-{$month}-01");
		
		$days 	= (int)date('t', $time);
		$first 	= (int)date('w', strtotime(date('Y-m-01', $time)));
		$last 	= (int)date('w', strtotime(date('Y-m-' . $days, $time)));
		
		
		$post_info = array();
		if ( is_array( $this->_events ) ) foreach ( $this->_events as $event ) {
			$post_info[] = $this->_get_item_data($event);
		}

		$tbl_id 	= $this->get_calendar_id();
		$tbl_id 	= $tbl_id ? "id='{$tbl_id}'" : '';
		$tbl_class 	= $this->get_calendar_class();
		$tbl_class 	= $tbl_class ? "class='{$tbl_class}'" : '';
		
		$ret = '';
		$ret .= "<table width='100%' {$tbl_id} {$tbl_class}>";
		$ret .= $this->_get_table_meta_row('thead');
		$ret .= '<tbody>';
		
		$ret .= $this->_get_first_row();
		
		if ( $first > $this->start_of_week )
			$ret .= '<tr><td class="no-left-border" colspan="' . ($first - $this->start_of_week) . '">&nbsp;</td>';
		else if ( $first < $this->start_of_week )
			$ret .= '<tr><td class="no-left-border" colspan="' . (7 + $first - $this->start_of_week) . '">&nbsp;</td>';
		else
			$ret .= '<tr>';

		$today_timestamp = date('Y-m-d', eab_current_time() );
		
		
		for ( $i=1; $i<=$days; $i++ ) {
			$date = date('Y-m-' . sprintf("%02d", $i), $time);
			$dow = (int)date('w', strtotime($date));
			$current_day_start = strtotime("{$date} 00:00"); 
			$current_day_end = strtotime("{$date} 23:59");
			if ($this->start_of_week == $dow) $ret .= '</tr><tr>';
			
			$this->reset_event_info_storage();
			if( apply_filters( 'eab_event_calendar_display_list_customize', true ) ) {
				foreach ($post_info as $ipost) {
					for ($k = 0; $k < count($ipost['event_starts']); $k++) {
						$start = strtotime($ipost['event_starts'][$k]);
						$end = strtotime($ipost['event_ends'][$k]);
						if ($start < $current_day_end && $end > $current_day_start) {
							$this->set_event_info(
								array('start' => $start, 'end'=> $end), 
								array('start' => $current_day_start, 'end'=> $current_day_end),
								$ipost
							);
						}
					}
				} 
			} else {
				do_action( 'eab_event_calendar_display_list_reorder', $post_info, $this, $current_day_start, $current_day_end );
			}
			
			$activity = $this->get_event_info_as_string( $i );
			$class_names = array();
			if ( $activity ) {
				$class_names[] = 'eab-has_events';
			} else {
				$activity = "<p>{$i}</p>";
			}
			if ($date == $today_timestamp) {
				$class_names[] = 'today';
			}
			$class_attribute = !empty($class_names) 
				? 'class="' . esc_attr(join(' ', $class_names)) . '"'
				: ''
			;
			$ret .= "<td {$class_attribute}>{$activity}</td>";
		}
                
		$final_last = $last + 1;
		$final_last = $final_last > 6 ? $final_last - 7 : $final_last;
		if ( $final_last == $this->start_of_week ) {
			$ret .= '</tr>'; 
		} else {
			$cal_diff = 6 - $last + $this->start_of_week;
			if( $cal_diff > 6 ) $cal_diff -= 7;
			$ret .= '<td class="no-right-border" colspan="' . $cal_diff . '">&nbsp;</td></tr>';
		}
		
		/*if ( $last > $this->start_of_week )
			$ret .= '<td class="no-right-border" colspan="' . (6 - $last + $this->start_of_week) . '">&nbsp;</td></tr>'; 
		else if ( $last + 1 == $this->start_of_week )
			$ret .= '</tr>'; 
		else
			$ret .= '<td class="no-right-border" colspan="' . (6 + $last - $this->start_of_week) . '">&nbsp;</td></tr>';*/
		
		$ret .= $this->_get_last_row();
		
		$ret .= '</tbody>';
		$ret .= $this->_get_table_meta_row('tfoot');
		$ret .= '</table>';
		
		return $ret;
	}
	
	protected function _get_table_meta_row ($which) {
		$day_names_array = $this->arrange( $this->get_day_names() );
		$cells = '<th>' . join('</th><th>', $day_names_array) . '</th>';
		return "<{$which}><tr>{$cells}</tr></{$which}>";
	}
	
	public function get_day_names () {
		return array(
			__('Sunday', $this->_get_text_domain()),
			__('Monday', $this->_get_text_domain()),
			__('Tuesday', $this->_get_text_domain()),
			__('Wednesday', $this->_get_text_domain()),
			__('Thursday', $this->_get_text_domain()),
			__('Friday', $this->_get_text_domain()),
			__('Saturday', $this->_get_text_domain()),
		);
	}
	
	
	protected function _get_first_row () { return ''; }
	protected function _get_last_row () { return ''; }

	abstract protected function _get_text_domain ();
	
	abstract public function get_calendar_id ();
	abstract public function get_calendar_class ();
	
	/**
	 * @return array Hash of post data
	 */
	abstract protected function _get_item_data ($post);
	
	abstract public function reset_event_info_storage ();
	abstract public function set_event_info ($event_tstamps, $current_tstamps, $event_info);
	abstract public function get_event_info_as_string ($day);
	
}


/**
 * Abstract event hub class.
 */
abstract class Eab_CalendarTable extends WpmuDev_CalendarTable {
	
	protected function _get_item_data ($post) {
		if (isset($post->blog_id)) { // Originates from network
			switch_to_blog($post->blog_id);
			$event = new Eab_EventModel($post);
			$event_starts = $event->get_start_dates();
			$event_ends = $event->get_end_dates();
			restore_current_blog();
		} else { // Originates from this blog
			$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
			$event_starts = $event->get_start_dates();
			$event_ends = $event->get_end_dates();
		}
		$res = array(
			'id' => $event->get_id(),
			'title' => $event->get_title(),
			'event_starts' => $event_starts,
			'event_ends' => $event_ends,
			'status_class' => Eab_Template::get_status_class($event),
			'event_venue' => $event->get_venue_location(),
			'categories' => $event->get_categories(),
		);
		if (isset($post->blog_id)) $res['blog_id'] = $post->blog_id;
		return $res;
	}
	
	protected function _get_text_domain () {
		return Eab_EventsHub::TEXT_DOMAIN;
	}
}


/**
 * Upcoming calendar widget concrete implementation.
 */
class Eab_CalendarTable_UpcomingCalendarWidget extends Eab_CalendarTable {
	
	protected $_titles = array();
	protected $_data = array();

	private $_class;

	public function set_class ($cls) {
		if (
			!empty($cls) && (
				!is_array($cls)
				||
				is_array($cls) && 1 == count($cls)
			)) {
				$this->_class = is_array($cls) ? $cls[0] : $cls;
		}
	}

	public function get_calendar_id () { return false; }
	public function get_calendar_class () {
		$cls = $this->get_calendar_root_class();
		return $this->_class 
			? sanitize_html_class($cls) . ' ' . sanitize_html_class($this->_class)
			: sanitize_html_class($cls)
		; 
	}
	public function get_calendar_root_class () {
		return 'eab-upcoming_calendar_widget';
	}
	
	public function get_day_names () {
		return array(
			__('Su', Eab_EventsHub::TEXT_DOMAIN),
			__('Mo', Eab_EventsHub::TEXT_DOMAIN),
			__('Tu', Eab_EventsHub::TEXT_DOMAIN),
			__('We', Eab_EventsHub::TEXT_DOMAIN),
			__('Th', Eab_EventsHub::TEXT_DOMAIN),
			__('Fr', Eab_EventsHub::TEXT_DOMAIN),
			__('Sa', Eab_EventsHub::TEXT_DOMAIN),
		);
	}
	
	protected function _get_table_meta_row ($which) {
		if ('tfoot' == $which) return '';
		return parent::_get_table_meta_row($which);
	}
	
	public function reset_event_info_storage () {
		$this->_titles = array();
		$this->_data = array();
	}

	public function set_event_info ($event_tstamps, $current_tstamps, $event_info) {
		$this->_titles[] = esc_attr($event_info['title']);
		$css_classes = $event_info['status_class'];
		$permalink = isset($event_info['blog_id']) ? get_blog_permalink($event_info['blog_id'], $event_info['id']) : get_permalink($event_info['id']);
		$tstamp = esc_attr(date_i18n("Y-m-d\TH:i:sO", $event_tstamps['start']));
		$this->_data[] = '<a class="wpmudevevents-upcoming_calendar_widget-event ' . $css_classes . '" href="' . $permalink . '">' . 
			$event_info['title'] .
			'<span class="wpmudevevents-upcoming_calendar_widget-event-info"><time datetime="' . $tstamp . '">' . 
				'<var class="eab-date_format-date">' . apply_filters('eab-calendar-upcoming_calendar_widget-start_time', date_i18n(get_option('date_format'), $event_tstamps['start']), $event_tstamps['start'], $event_info['id']) . '</var>' .
				'</time> ' . $event_info['event_venue'] .
			'</span>' .
		'</a>'; 
	}
	
	public function get_event_info_as_string ($day) {
		$activity = '';
		if ($this->_titles && $this->_data) {
			$ttl = join(', ', $this->_titles);
			$einfo = join('<br />', $this->_data);
			$activity = "<p><a href='#' title='{$ttl}'>{$day}</a><span class='wdpmudevevents-upcoming_calendar_widget-info_wrapper' style='display:none'>{$einfo}</span></p>";
		}
		return $activity;
	}

	protected function _get_last_row () {
		$time = $this->get_timestamp();
		// Make sure we subtract for the end of the month.
		if (date('j', $time) == date('t', $time)) $time -= 4*86400;
		return '<tr>' .
			'<td>' .
				'<a class="' . $this->get_calendar_root_class() . '-navigation-link eab-navigation-next eab-time_unit-year" href="' . 
					Eab_Template::get_archive_url_prev_year($time, true) . '">' . 
					'&nbsp;&laquo;' .
				'</a>' .
			'</td>' .
			'<td>' .
				'<a class="' . $this->get_calendar_root_class() . '-navigation-link eab-navigation-next eab-time_unit-month" href="' . 
					Eab_Template::get_archive_url_prev($time, true) . '">' . 
					'&nbsp;&lsaquo;' .
				'</a>' .
			'</td>' .
			'<td colspan="3" style="text-align:center;">' .
				'<input type="hidden" class="eab-cuw-calendar_date" value="' . $time . '" />' .
				'<a href="' . Eab_Template::get_archive_url($time, true) . '" class="' . $this->get_calendar_root_class() . '-navigation-link eab-cuw-calendar_date">' . date_i18n('M Y', $time) . '</a>' .
			'</td>' .
			'<td>' .
				'<a class="' . $this->get_calendar_root_class() . '-navigation-link eab-navigation-prev eab-time_unit-month" href="' . 
					Eab_Template::get_archive_url_next($time, true) . '">&rsaquo;&nbsp;' . 
				'</a>' .
			'</td>' .
			'<td>' .
				'<a class="' . $this->get_calendar_root_class() . '-navigation-link eab-navigation-prev eab-time_unit-year" href="' . 
					Eab_Template::get_archive_url_next_year($time, true) . '">&raquo;&nbsp;' . 
				'</a>' .
			'</td>' .
		'</tr>';
	}
	
	/**
	 * Override the main method to allow output caching.
	 * Calendar widget could be used often, so make sure we're quick.
	 */
	public function get_month_calendar ($timestamp=false) {
		if (!(defined('EAB_CALENDAR_USE_CACHE') && EAB_CALENDAR_USE_CACHE)) return parent::get_month_calendar($timestamp);

		$key = md5(serialize($this->_events)) . '-eab_ucw';
		$output = get_transient($key);
		if (empty($output)) {
			$output = parent::get_month_calendar($timestamp);
			set_transient($key, $output, 3600); // 1 hour
		}
		return $output;
	}
}

class Eab_CalendarTable_EventArchiveCalendar extends Eab_CalendarTable {
	
	protected $_data = array();
	protected $_long_date_format = false;
	protected $_thumbnail = array(
		'with_thumbnail' => false,
		'default_thumbnail' => false,
	);
	protected $_excerpt = array(
		'show_excerpt' => false,
		'excerpt_length' => false,
	);

	public function __construct ($events) {
		parent::__construct($events);
		$this->_long_date_format = get_option("date_format");
	}

	public function set_thumbnail ($args) {
		if (!empty($args['with_thumbnail'])) $this->_thumbnail['with_thumbnail'] = true;
		if (!empty($args['default_thumbnail'])) $this->_thumbnail['default_thumbnail'] = esc_url($args['default_thumbnail']);
	}

	public function set_excerpt ($args) {
		if (!empty($args['show_excerpt'])) $this->_excerpt['show_excerpt'] = true;
		if (!empty($args['excerpt_length'])) $this->_excerpt['excerpt_length'] = intval($args['excerpt_length']);
	}

	public function set_long_date_format ($fmt) {
		$this->_long_date_format = $fmt ? $fmt : $this->_long_date_format;
	}
	
	public function get_calendar_id () { return false; }
	public function get_calendar_class () { return 'eab-monthly_calendar'; }
	public function reset_event_info_storage () { $this->_data = array(); }
	
	public function set_event_info ($event_tstamps, $current_tstamps, $event_info) {
            
                if( is_multisite() && isset( $event_info['blog_id'] ) ) switch_to_blog( $event_info['blog_id'] );
            
		$css_classes = $event_info['status_class'];
		$event_permalink = !empty($event_info['blog_id'])
			? get_blog_permalink($event_info['blog_id'], $event_info['id'])
			: get_permalink($event_info['id'])
		;
                
                $event_permalink = apply_filters('eab-calendar-event_permalink', $event_permalink, $event_info);
                
                $gmt_offset = (float)get_option('gmt_offset');
                $hour_tz = sprintf('%02d', abs((int)$gmt_offset));
                $minute_offset = (abs($gmt_offset) - abs((int)$gmt_offset)) * 60;
                $min_tz = sprintf('%02d', $minute_offset);
                $timezone = ($gmt_offset > 0 ? '+' : '-') . $hour_tz . $min_tz;
                
		$tstamp = esc_attr(date_i18n("Y-m-d\TH:i:s{$timezone}", $event_tstamps['start']));
		$daytime = (int)date("His", $event_tstamps['start']);

		if (!empty($event_info['has_no_start_time'])) {
			$datetime_format = get_option('date_format');
			$datetime_class = 'eab-date_format-date';
			$event_datetime_start = $current_tstamps['start'];
		} else {
			$datetime_format = get_option('time_format');
			$datetime_class = 'eab-date_format-time';
			$event_datetime_start = $event_tstamps['start'];

		}

		$this->_data[$daytime][] = '<a class="wpmudevevents-calendar-event ' . $css_classes . '" href="' . $event_permalink . '">' . 
			$event_info['title'] .
			'<span class="wpmudevevents-calendar-event-info">' .
				(
					!empty($this->_thumbnail['with_thumbnail']) && !empty($event_info['thumbnail'])
						? $event_info['thumbnail']
						: ''
				) .
				'<time datetime="' . $tstamp . '">' .
					'<var class="' . sanitize_html_class($datetime_class) . '">' . apply_filters('eab-calendar-event_archive-start_time', date_i18n($datetime_format, $event_datetime_start), $event_datetime_start, $event_info['id']) . '</var>' .
				'</time> ' . 
				$event_info['event_venue'] .
				(!empty($this->_excerpt['show_excerpt']) ? ' <span class="eab-calendar-event_excerpt">' . esc_html($event_info['excerpt']) . '</span>' : '') .
			'</span>' . 
		'</a>';
                
                if( is_multisite() && isset( $event_info['blog_id'] ) ) restore_current_blog();
                
	}
	
	public function get_event_info_as_string ($day) {
		$activity = '';
		$full_date = date_i18n(
			apply_filters('eab-calendar-item-full_date_format', $this->_long_date_format),
			strtotime(
				date('Y', $this->_current_timestamp) .
				'-' .
				date('m', $this->_current_timestamp) .
				'-' .
				$day
			)
		);

		if ($this->_data) {
			$html = '';
			foreach( $this->_data as $key => $val ) {
				ksort($val);
				foreach( $val as $v ) {
					$html .= $v;
				}
			}
			//ksort($this->_data);
			$activity = '<p>' . 
				"<span class='eab-date-ordinal'>{$day}</span> <span class='eab-date-full' style='display:none'>{$full_date}</span>" . 
				'<br />' . $html . 
			'</p>';
		} else {
			$activity = '<p>' . "<span class='eab-date-ordinal'>{$day}</span> <span class='eab-date-full' style='display:none'>{$full_date}</span>" . '</p>';
		}
		return $activity;
	}
	
	public function get_month_calendar ($timestamp=false) {
		return parent::get_month_calendar($timestamp) . $this->_get_js();
	}
	
	protected function _get_js () {
		if (defined('EAB_EVENT_ARCHIVE_CALENDAR_HAS_JS')) return false;
		define('EAB_EVENT_ARCHIVE_CALENDAR_HAS_JS', true);
		return <<<EabEctEacJs
<script type="text/javascript">
(function ($) {
$(function () {
// Info popups
$(".wpmudevevents-calendar-event")
	.mouseenter(function () {
		$(this).find(".wpmudevevents-calendar-event-info").show();
	})
	.mouseleave(function () {
		$(this).find(".wpmudevevents-calendar-event-info").hide();
	})
;
});
})(jQuery);
</script>
EabEctEacJs;
	}

	protected function _get_item_data ($post) {
		$data = parent::_get_item_data($post);
                // Enabling excerpt in calendar
		// if (isset($post->blog_id)) return $data;
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		
		if (!empty($this->_thumbnail['with_thumbnail'])) {
			$thumbnail = $event->get_featured_image();
			if (empty($thumbnail) && !empty($this->_thumbnail['default_thumbnail'])) {
				$thumbnail = '<img src="' . esc_url($this->_thumbnail['default_thumbnail']) . '" />';
			}
			$data['thumbnail'] = $thumbnail;
		}
		
		if (!empty($this->_excerpt['show_excerpt'])) {
			$excerpt_length = !empty($this->_excerpt['excerpt_length']) && (int)$this->_excerpt['excerpt_length']
				? (int)$this->_excerpt['excerpt_length']
				: 30
			;
			$data['excerpt'] = $event->get_excerpt_or_fallback($excerpt_length); // Creating the excerpt
		}

		$data['has_no_start_time'] = $event->has_no_start_time(); // Only check first

		return $data;
	}
}


class Eab_CalendarTable_EventShortcodeCalendar extends Eab_CalendarTable_EventArchiveCalendar {

	protected $_class;
	protected $_use_footer = false;
	protected $_use_scripts = true;
	protected $_navigation = false;
	protected $_track = false;
	protected $_title_format = 'M Y';
	protected $_short_title_format = 'm-Y';
	
	public function set_class ($class) {
		$this->_class = sanitize_html_class($class);
	}

	public function get_calendar_class () {
		return join(' ', array(parent::get_calendar_class(), $this->_class));
	}
	
	public function set_footer ($use) {
		$this->_use_footer = (bool)$use;
	}

	public function set_scripts ($use) {
		$this->_use_scripts = (bool)$use;
	}

	public function set_title_format ($title_format) {
		$this->_title_format = $title_format ? $title_format : $this->_title_format;
	}
	
	public function set_short_title_format ($title_format) {
		$this->_short_title_format = $title_format ? $title_format : $this->_short_title_format;
	}

	public function set_navigation ($navigation) {
		$this->_navigation = (bool)$navigation;
	}

	public function set_track ($track) {
		$this->_track = (bool)$track;
	}

	protected function _get_js () {
		if (!$this->_use_scripts) return false;
		return parent::_get_js();
	}

	protected function _get_table_meta_row ($which) {
		if ('tfoot' == $which && !$this->_use_footer) return '';
		$day_names_array = $this->arrange( $this->get_day_names() );
		$cells = '<th>' . join('</th><th>', $day_names_array) . '</th>';
		$row = "<tr>{$cells}</tr>";
		if ('thead' == $which) {
			$row = $this->_get_navigation_row('top') . $row;
		}
		if ('tfoot' == $which) {
			$row .= $this->_get_navigation_row('bottom');
		}
		return "<{$which}>{$row}</{$which}>";
	}

	protected function _get_navigation_row ($position) {
		if (!$this->_navigation) return false;
		
		global $post;
		
		$time = $this->get_timestamp();
		
		$calendar_class = $this->get_calendar_class();
		$row_class = "eab-calendar-title {$calendar_class}-title-{$position}";


		$short_attribute = $this->_short_title_format
			? 'datetime="' . esc_attr(date_i18n($this->_short_title_format, $time)) . '"'
			: ''
		;
		$title_format = 'top' == $position
			? date_i18n($this->_title_format, $time)
			: date_i18n($this->_title_format, $time)
		;
		$title_link = '<a href="' . Eab_Template::get_archive_url($time, true) . '" class="' . $calendar_class . '-navigation-link eab-cuw-calendar_date">' .
			"<time {$short_attribute}>" .
				"<span>{$title_format}</span>" . 
			'</time>' .
		'</a>';
		$positional_id = $this->_track ? esc_attr('eab-calendar-' . preg_replace('/[^-_a-z0-9]/', '-', $calendar_class)) : '';
		$id_attr = $this->_track ? "id='{$positional_id}'" : '';
		$id_href = $this->_track ? "#{$positional_id}" : '';
		$title = 'top' == $position
			? "<h4 {$id_attr}>{$title_link}</h4>"
			: "<b>{$title_link}</b>"
		;
                
                $title = apply_filters(
                                'eab_calendar_title',
                                $title,
                                $position,
                                $id_attr,
                                $title_link
                            );
		        
		return "<tr class='{$row_class}'>" .
			'<td>' .
				'<a class="' . $calendar_class . '-navigation-link eab-navigation-prev eab-time_unit-year" href="' . 
					esc_url(add_query_arg('date', date('Y-m', $time - (366*86400)))) . $id_href . '">' . 
					'&nbsp;&laquo;' .
				'</a>' .
			'</td>' .
			'<td>' .
				'<a class="' . $calendar_class . '-navigation-link eab-navigation-prev eab-time_unit-month" href="' . 
					esc_url(add_query_arg('date', date('Y-m', $time - (28*86400)))) . $id_href . '">' .
					'&nbsp;&lsaquo;' .
				'</a>' .
			'</td>' .
			'<td colspan="3" style="text-align:center;">' .
				'<input type="hidden" class="eab-cuw-calendar_date" value="' . $time . '" />' .
				$title .
			'</td>' .
			'<td>' .
				'<a class="' . $calendar_class . '-navigation-link eab-navigation-next eab-time_unit-month" href="' . 
					esc_url(add_query_arg('date', date('Y-m', $time + (32*86400)))) . $id_href . '">' . 
					'&rsaquo;&nbsp;' . 
				'</a>' .
			'</td>' .
			'<td>' .
				'<a class="' . $calendar_class . '-navigation-link eab-navigation-next eab-time_unit-year" href="' . 
					esc_url(add_query_arg('date', date('Y-m', $time + (366*86400)))) . $id_href . '">' . 
					'&raquo;&nbsp;' . 
				'</a>' .
			'</td>' .
		'</tr>';
	}
}
