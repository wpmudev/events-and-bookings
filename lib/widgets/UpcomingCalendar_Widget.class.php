<?php

class Eab_CalendarUpcoming_Widget extends Eab_Widget {
	
	function __construct () {
		$widget_ops = array('classname' => __CLASS__, 'description' => __('Displays List of Upcoming Events from your entire network', $this->translation_domain));
		
		add_action('wp_enqueue_scripts', array($this, 'css_load_styles'));
		add_action('wp_enqueue_scripts', array($this, 'js_load_scripts'));
		add_action('wp_ajax_eab_cuw_get_calendar', array($this, 'handle_calendar_request'));
		add_action('wp_ajax_nopriv_eab_cuw_get_calendar', array($this, 'handle_calendar_request'));
		
		parent::WP_Widget(__CLASS__, __('Calendar Upcoming', $this->translation_domain), $widget_ops);
	}
	
	function css_load_styles () {
		if (!is_admin()) wp_enqueue_style('eab-upcoming_calendar_widget-style', plugins_url('events-and-bookings/css/upcoming_calendar_widget.css'));
	}

	function js_load_scripts () {
		if (!is_admin()) wp_enqueue_script('eab-upcoming_calendar_widget-script', plugins_url('events-and-bookings/js/upcoming_calendar_widget.js'), array('jquery'));
	}
	
	function form ($instance) {
		$title = esc_attr($instance['title']);
		$date = esc_attr($instance['date']);
		$network = esc_attr($instance['network']) ? 'checked="checked"' : '';
		$category = !empty($instance['category']) ? esc_attr($instance['category']) : false;
		
		$html .= '<p>';
		$html .= '<label for="' . $this->get_field_id('title') . '">' . __('Title:', $this->translation_domain) . '</label>';
		$html .= '<input type="text" name="' . $this->get_field_name('title') . '" id="' . $this->get_field_id('title') . '" class="widefat" value="' . $title . '"/>';
		$html .= '</p>';

		if (is_multisite() && eab_has_post_indexer()) {
			$html .= '<p>' .
				'<label for="' . $this->get_field_id('network') . '">' . 
				'<input type="checkbox" name="' . $this->get_field_name('network') . '" id="' . $this->get_field_id('network') . '" value="1" ' . $network . ' /> ' .
				__('Network-wide?', $this->translation_domain) .
				'</label> ' .
			'</p>';
		} else {
			$options = false;
			$all_categories = get_terms('eab_events_category');
			foreach ($all_categories as $cat) {
				$options .= '<option value="' . $cat->term_id . '" ' . selected($cat->term_id, $category, false) . '>' . $cat->name . '</option>';
			}
			if ($options) {
				$html .= '<p>' .
					'<label for="' . $this->get_field_id('category') . '">' .
	            		__('Only Events from this category', $this->translation_domain) .
						'<select id="' . $this->get_field_id('category') . '" name="' . $this->get_field_name('category') . '">' .
							'<option>' . __('Any', $this->translation_domain) . '</option>' .
							$options . 
						'</select>' .
	           		'</label>' .
				'</p>';
			}
		}
	
		echo $html;
	}
	
	function update ($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['date'] = strip_tags($new_instance['date']);
		$instance['network'] = strip_tags($new_instance['network']);
		$instance['category'] = !empty($instance['category']) ? strip_tags($new_instance['category']) : false;

		delete_transient($this->get_field_id('cache'));

		return $instance;
	}
	
	function widget ($args, $instance) {
		extract($args);
		$title = apply_filters('widget_title', $instance['title']);
		$network = is_multisite() ? (int)$instance['network'] : false;
		$category = $network ? false : (!empty($instance['category']) ? (int)$instance['category'] : false);

		echo $before_widget;
		if ($title) echo $before_title . $title . $after_title;
		echo $this->_get_calendar_output(eab_current_time(), $network, $category);
		echo $after_widget;	
	}

	/**
	 * Allow for calendar widget caching.
	 * @TODO: Caching should have a more organized approach.
	 */
	private function _get_calendar_output ($date, $network, $category=false) {
		if (!(defined('EAB_CALENDAR_USE_CACHE') && EAB_CALENDAR_USE_CACHE)) return $this->_render_calendar($date, $network, $category);

		$key = $this->get_field_id('cache');
		$output = get_transient($key);
		if (empty($output)) {
			$output = $this->_render_calendar($date, $network);
			set_transient($key, $output, 3600); // 1 hour
		}
		return $output;
	}
	
	private function _render_calendar ($date, $network=false, $category=false) {
		$args = array();
		if ($category && (int)$category) {
			$args['tax_query'] =  array(array(
				'taxonomy' => 'eab_events_category',
				'field' => 'id',
				'terms' => $category,
			));
		}
		$events = $network
			? Eab_Network::get_upcoming_events(10)
			: Eab_CollectionFactory::get_upcoming_events($date, $args)
		;

		if (!class_exists('Eab_CalendarTable_UpcomingCalendarWidget')) require_once EAB_PLUGIN_DIR . 'lib/class_eab_calendar_helper.php';
		$renderer = new Eab_CalendarTable_UpcomingCalendarWidget($events);
		return $renderer->get_month_calendar($date);
	}
	
	function handle_calendar_request () {
		$now = (int)@$_POST['now'];
		$now = $now ? $now : eab_current_time();
		
		$unit = ("year" == @$_POST['unit']) ? "year" : "month";
		$operand = ("prev" == $_POST['direction']) ? "+1" : "-1";
		
		$date = strtotime("{$operand} {$unit}", $now);
		echo $this->_render_calendar($date);
		die;
	}
}
