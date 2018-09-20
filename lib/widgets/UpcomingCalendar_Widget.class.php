<?php

class Eab_CalendarUpcoming_Widget extends Eab_Widget {
	
	function __construct () {
		$widget_ops = array(
			'classname' => __CLASS__, 
			'description' => __('Displays List of Upcoming Events from your site', $this->translation_domain),
		);
		
		add_action('wp_enqueue_scripts', array($this, 'css_load_styles'));
		add_action('wp_enqueue_scripts', array($this, 'js_load_scripts'));
		add_action('wp_ajax_eab_cuw_get_calendar', array($this, 'handle_calendar_request'));
		add_action('wp_ajax_nopriv_eab_cuw_get_calendar', array($this, 'handle_calendar_request'));
		
		parent::__construct(__CLASS__, __('Calendar Upcoming', $this->translation_domain), $widget_ops);
	}
	
	function css_load_styles () {
		if (!is_admin()) wp_enqueue_style('eab-upcoming_calendar_widget-style', EAB_PLUGIN_URL . 'css/upcoming_calendar_widget.css');
	}

	function js_load_scripts () {
		if (!is_admin()) wp_enqueue_script(
			'eab-upcoming_calendar_widget-script', 
			EAB_PLUGIN_URL . 'js/upcoming_calendar_widget.js', 
			array('jquery'), 
			Eab_EventsHub::CURRENT_VERSION
		);
	}
	
	function form ($instance) {
		$html = '';
		$title 		= isset( $instance['title'] ) ? esc_attr($instance['title']) : '';
		$date 		= isset( $instance['date'] ) ? esc_attr($instance['date']) : '';
		$network 	= ( isset( $instance['network'] ) && esc_attr($instance['network']) ) ? 'checked="checked"' : '';
		$category 	= ( isset( $instance['category'] ) && !empty($instance['category']) ) ? 
			(is_array($instance['category']) ? array_filter(array_map('esc_attr', $instance['category'])) : array_filter(array(esc_attr($instance['category']))))
			: array() ;

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
				$options .= '<option value="' . $cat->term_id . '" ' . (in_array($cat->term_id, $category) ? 'selected="selected"' : '') . '>' . $cat->name . '</option>';
			}
			if ($options) {
				$html .= '<p>' .
					'<label for="' . $this->get_field_id('category') . '">' .
	            		__('Only Events from this category', $this->translation_domain) .
						'<select id="' . $this->get_field_id('category') . '" name="' . $this->get_field_name('category') . '[]" multiple class="widefat">' .
							'<option ' . (empty($category) ? 'selected="selected"' : '') . ' value="">' . __('Any', $this->translation_domain) . '</option>' .
							$options . 
						'</select>' .
	           		'</label>' .
				'</p>';
			}
		}
	
		echo $html;
	}
	
	function update ($new_instance, $old_instance) {
		$instance 				= $old_instance;
		$instance['title']	 	= isset( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['date'] 		= isset( $new_instance['date'] ) ? strip_tags( $new_instance['date'] ): '';
		$instance['network'] 	= isset( $new_instance['network'] ) ? strip_tags( $new_instance['network'] ): '';
		$instance['category'] 	= isset( $new_instance['category'] ) ? array_map( 'strip_tags', $new_instance['category'] ) : false;

		delete_transient( $this->get_field_id('cache') );

		return $instance;
	}
	
	function widget ($args, $instance) {
		extract($args);
		$title 		= isset( $instance['title'] ) ? apply_filters('widget_title', $instance['title']) : '';
		$network 	= is_multisite() ? ( isset( $instance['network'] ) ? (int)$instance['network'] : false) : false;
		$category 	= ( isset( $instance['category'] ) && !empty($instance['category']) ) ? 
			(is_array($instance['category']) ? array_filter(array_map('esc_attr', $instance['category'])) : array_filter(array(esc_attr($instance['category']))))
			: array()
		;

		echo $before_widget;
		if ($title) echo $before_title . $title . $after_title;
		echo '<div data-eab-widget_id="' . (int)$this->number . '">' . $this->_get_calendar_output(eab_current_time(), $network, $category) . '</div>';
		echo $after_widget;	
	}

	/**
	 * Allow for calendar widget caching.
	 * @TODO: Caching should have a more organized approach.
	 */
	private function _get_calendar_output ($date, $network, $category=array()) {
		if (!(defined('EAB_CALENDAR_USE_CACHE') && EAB_CALENDAR_USE_CACHE)) return $this->_render_calendar($date, $network, $category);

		$key = $this->get_field_id('cache');
		$output = get_transient($key);
		if (empty($output)) {
			$output = $this->_render_calendar($date, $network);
			set_transient($key, $output, 3600); // 1 hour
		}
		return $output;
	}
	
	private function _render_calendar ($date, $network=false, $category=array()) {
		$args = array();
		$category_class = false;
		if (!empty($category)) {
			$args['tax_query'] = array(
				'relation' => 'OR',
			);
			foreach ($category as $cat) {
				if (!$cat) continue;
				$args['tax_query'][] = array(
					'taxonomy' => 'eab_events_category',
					'field' => 'id',
					'terms' => $cat,
				);
			}
			if (1 === count($category)) {
				$term = get_term_by('id', $category[0], 'eab_events_category');
				$category_class = !empty($term->slug) ? $term->slug : false;
			}
		}
        
		$args['_avoid_pgp_action'] = 1; // Avoids the later pre_get_posts action for Archive pagination
		
		if( defined( 'EAB_UPCOMING_EVENT_FROM_TODAY' ) && EAB_UPCOMING_EVENT_FROM_TODAY ) add_filter( 'eab-collection-upcoming-start_timestamp', array( $this, 'eab_widget_start_date' ) );
		$events = $network
			? Eab_Network::get_upcoming_events(10)
			: Eab_CollectionFactory::get_upcoming_events($date, $args)
		;
        if( defined( 'EAB_UPCOMING_EVENT_FROM_TODAY' ) && EAB_UPCOMING_EVENT_FROM_TODAY ) remove_filter( 'eab-collection-upcoming-start_timestamp', array( $this, 'eab_widget_start_date' ) );

		if (!class_exists('Eab_CalendarTable_UpcomingCalendarWidget')) require_once EAB_PLUGIN_DIR . 'lib/class_eab_calendar_helper.php';
		$renderer = new Eab_CalendarTable_UpcomingCalendarWidget($events);
		$renderer->set_class($category_class);
		return $renderer->get_month_calendar($date);
	}
    
    function eab_widget_start_date( $date ) {
        return date( 'Y' ) . '-' . date( 'm' ) . '-' . date( 'd' ) . ' 00:00';
    }
	
	function handle_calendar_request () {
		$now = !empty($_POST['now']) ? (int)$_POST['now'] : false;
		$now = $now ? $now : eab_current_time();
		
		$unit = !empty($_POST["unit"]) && ("year" == $_POST['unit']) ? "year" : "month";
		$operand = !empty($_POST['direction']) && ("prev" == $_POST['direction']) ? "+1" : "-1";
		$widget_id = !empty($_POST['widget_id']) && (int)$_POST['widget_id'] ? (int)$_POST['widget_id'] : $this->number;
		
		$date = strtotime("{$operand} {$unit}", $now);

		$all_data = $this->get_settings();
		$instance = !empty($all_data[$widget_id]) ? $all_data[$widget_id] : array();

		$network = is_multisite() ? (int)$instance['network'] : false;
		$category = !empty($instance['category']) ? 
			(is_array($instance['category']) ? array_filter(array_map('esc_attr', $instance['category'])) : array_filter(array(esc_attr($instance['category']))))
			: array()
		;

		echo $this->_render_calendar($date, $network, $category);
		die;
	}
}
