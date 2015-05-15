<?php
/*
Plugin Name: Recurring Event in Calendar ShortCode
Description: Use [eab_calendar_recurring id="xx"] to show all instances of a recurring event
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Events
*/

/*
Detail: <b>Note:</b> this may take time and resources if you have a lot of events.
*/ 

class Eab_Events_Calendar_RecurringShortCode {
	
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Events_Calendar_RecurringShortCode;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_shortcode( 'eab_calendar_recurring', array( $this, 'eab_calendar_recurring_cb' ) );
	}
	
	function eab_calendar_recurring_cb( $args ) {
		$args = shortcode_atts( array(
                        'id' => false,
			'network' => false,
			'date' => false,
			'relative_date' => false,
		// Query arguments
			'category' => false, // ID or slug
			'categories' => false, // Comma-separated list of IDs
		// Appearance arguments
			'footer' => false,
			'class' => 'eab-shortcode_calendar',
			'navigation' => false,
			'title_format' => 'M Y',
			'short_title_format' => 'm-Y',
			'long_date_format' => false,
			'template' => 'get_shortcode_calendar_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
			'with_thumbnail' => false,
			'default_thumbnail' => false,
			'show_excerpt' => false,
			'excerpt_length' => 55,
		), $args, 'eab_recurring' );
                
		if (!empty($_GET['date'])) {
			$date = strtotime($_GET['date']);
			if ($date) $args['date'] = $date;
		}

		if ($args['id']) $event = new Eab_EventModel(get_post($args['id']));
		else {
			$q = new WP_Query(array(
				'post_type' => Eab_EventModel::POST_TYPE,
				'name' => $args['slug'],
				'posts_per_page' => 1,
			));
			if (isset($q->posts[0])) $event = new Eab_EventModel($q->posts[0]);
		}
		if (!$event) return $content;
		
		$rec_events = Eab_CollectionFactory::get_all_recurring_children_events($event);

		$output = $this->get_shortcode_calendar_output($rec_events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_calendar_shortcode', eab_call_template('util_get_default_template_style', 'calendar'));
		return $output;
		
	}
	
	
	function get_shortcode_calendar_output ($events, $args) {
		if (!class_exists('Eab_CalendarTable_EventShortcodeCalendar')) require_once EAB_PLUGIN_DIR . 'lib/class_eab_calendar_helper.php';
		$renderer = new Eab_CalendarTable_EventShortcodeCalendar($events);

		$renderer->set_class($args['class']);
		$renderer->set_footer($args['footer']);
		$renderer->set_scripts(!$args['override_scripts']);
		$renderer->set_navigation($args['navigation']);
		$renderer->set_title_format($args['title_format']);
		$renderer->set_short_title_format($args['short_title_format']);
		$renderer->set_long_date_format($args['long_date_format']);
		$renderer->set_thumbnail($args);
		$renderer->set_excerpt($args);

		return '<section class="wpmudevevents-list">' . $renderer->get_month_calendar($args['date']) . '</section>';
	}
	
}

if (!is_admin()) Eab_Events_Calendar_RecurringShortCode::serve();
