<?php

abstract class Eab_Codec {
	
	protected $_shortcodes = array();
	
	private $_positive_values = array(
		true, 'true', 'yes', 'on', '1'
	);
	
	private $_negative_values = array(
		false, 'false', 'no', 'off', '0'
	);

	protected function _arg_to_bool ($val) {
		return in_array($val, $this->_positive_values);
	}

	protected function _arg_to_int ($val) {
		if (!is_numeric($val)) return 0;
		return (int)$val;
	}

	protected function _arg_to_time ($val) {
		$timestamp = strtotime($val);
		return $timestamp
			? $timestamp
			: eab_current_time()
		;
	}

	protected function _preparse_arguments ($raw, $accepted) {
		$args = wp_parse_args($raw, $accepted);
		if (isset($accepted['network'])) $args['network'] = $this->_arg_to_bool($args['network']);
		if (isset($accepted['date'])) $args['date'] = $this->_arg_to_time($args['date']);
		
		if (isset($accepted['lookahead'])) $args['lookahead'] = $this->_arg_to_bool($args['lookahead']);
		if (isset($accepted['weeks'])) $args['weeks'] = $this->_arg_to_int($args['weeks']);
		
		if (isset($accepted['limit'])) $args['limit'] = $this->_arg_to_int($args['limit']);
		if (isset($accepted['order'])) $args['order'] = $args['order'] && in_array(strtoupper($args['order']), array('ASC', 'DESC'))
			? strtoupper($args['order'])
			: false
		;
		if (isset($accepted['category']) && $args['category']) {
			$args['category'] = $this->_arg_to_int($args['category'])
				? array('type' => 'id', 'value' => $this->_arg_to_int($args['category']))
				: array('type' => 'slug', 'value' => $args['category'])
			;
		}

		if (isset($accepted['override_styles'])) $args['override_styles'] = $this->_arg_to_bool($args['override_styles']);
		if (isset($accepted['override_scripts'])) $args['override_scripts'] = $this->_arg_to_bool($args['override_scripts']);
		return $args;
	}

	protected function _to_query_args ($args) {
		$query = array();
		// Parse query arguments
		if ($args['category'] && is_array($args['category'])) {
			$query['tax_query'] = array(array(
				'taxonomy' => 'eab_events_category',
				'field' => $args['category']['type'],
				'terms' => $args['category']['value'],
			));
		}
		if ($args['limit']) {
			$query['posts_per_page'] = $args['limit'];
		}
		return $query;
	}
	
	/**
	 * Registers shortcode handlers.
	 */
	protected function _register () {
		$shortcodes = $this->_shortcodes;
		foreach ($shortcodes as $key => $shortcode) {
			add_shortcode($shortcode, array($this, "process_{$key}_shortcode"));
		}
	}
}

class Eab_Shortcodes extends Eab_Codec {
	
	protected $_shortcodes = array (
		'calendar' => 'eab_calendar',
		'archive' => 'eab_archive',
		'single' => 'eab_single',
		'expired' => 'eab_expired',
		'events_map' => 'eab_events_map',
	);
	
	public static function serve () {
		$me = new Eab_Shortcodes;
		$me->_register();
	}

	/**
	 * Events map shortcode
	 * @param  array   $args    Shortcode arguments
	 * @param  boolean $content Fallback content
	 * @return string           Map string or fallback content
	 */
	function process_events_map_shortcode ($args=array(), $content=false) {
		if (!class_exists('AgmMapModel') || !class_exists('AgmMarkerReplacer')) return $content;
		
		$map_args = $args;
		$args = $this->_preparse_arguments($args, array(
		// Date arguments	
			'date' => false, // Starting date - default to now
			'lookahead' => false, // Don't use default monthly page - use weeks count instead
			'weeks' => false, // Look ahead this many weeks
		// Query arguments
			'category' => false, // ID or slug
			'limit' => false, // Show at most this many events
			'order' => false,
		// Appearance arguments
			'featured_image' => false,
			'class' => false,
			'template' => 'get_shortcode_events_map_marker_body_output', // Always a template class call
		));
		$args['featured_image'] = $this->_arg_to_bool($args['featured_image']);
		$class = $args['class'] ? 'class="' . $args['class'] . '"' : '';

		$query = $this->_to_query_args($args);

		$order_method = $args['order']
			? create_function('', 'return "' . $args['order'] . '";')
			: false
		;
		if ($order_method) add_filter('eab-collection-date_ordering_direction', $order_method);

		// Lookahead - depending on presence, use regular upcoming query, or poll week count
		if ($args['lookahead']) {
			$method = $args['weeks']
				? create_function('', 'return ' . $args['weeks'] . ';')
				: false;
			;
			if ($method) add_filter('eab-collection-upcoming_weeks-week_number', $method);
			$events = Eab_CollectionFactory::get_upcoming_weeks($args['date'], $query);
			if ($method) remove_filter('eab-collection-upcoming_weeks-week_number', $method);
		} else {
			// No lookahead, get the full month only
			$events = Eab_CollectionFactory::get_upcoming($args['date'], $query);
		}
		if ($order_method) remove_filter('eab-collection-date_ordering_direction', $order_method);
		
		$model = new AgmMapModel;
		$raw_maps = $model->get_custom_maps($events->query);
		if (empty($raw_maps)) return $content;

		$maps = array();
		foreach ($raw_maps as $key => $map) {
			if (empty($map['markers']) || count($map['markers']) > 1) continue;
			$event = !empty($map['post_ids']) && !empty($map['post_ids'][0])
				? new Eab_EventModel(get_post($map['post_ids'][0]))
				: false
			;
			if (!$event) continue;

			$map['markers'][0]['title'] = $event->get_title();
			$map['markers'][0]['body'] = eab_call_template('util_apply_shortcode_template', $event, $args);
			if ($args['featured_image']) {
				$icon = $event->get_featured_image_url();
				if ($icon) $map['markers'][0]['icon'] = $icon;
			}

			$maps[] = $map;
		}
		if (!$maps) return $content;

		$codec = new AgmMarkerReplacer;
		return "<div {$class}>" . $codec->create_overlay_tag($maps, $map_args) . '</div>';
	}
	
	/**
	 * Calendar shortcode handler.
	 */
	function process_calendar_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
			'network' => false,
			'date' => false,
			'footer' => false,
			'class' => false,
			'template' => 'get_shortcode_calendar_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		));
		
		$events = ($args['network'] && is_multisite()) 
			? Eab_Network::get_upcoming_events(30) 
			: Eab_CollectionFactory::get_upcoming_events($args['date'])
		;

		$output = eab_call_template('util_apply_shortcode_template', $events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_calendar_shortcode', eab_call_template('util_get_default_template_style', 'calendar'));
		return $output;
	}
	
	/**
	 * Archive shortcode handler.
	 */
	function process_archive_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
			'network' => false, // Query type
		// Date arguments	
			'date' => false, // Starting date - default to now
			'lookahead' => false, // Don't use default monthly page - use weeks count instead
			'weeks' => false, // Look ahead this many weeks
		// Query arguments
			'category' => false, // ID or slug
			'limit' => false, // Show at most this many events
			'order' => false,
		// Appearance arguments
			'class' => false,
			'template' => 'get_shortcode_archive_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		));

		$events = array();
		if (is_multisite() && $args['network']) {
			$events = Eab_Network::get_upcoming_events(30);
		} else {
			$query = $this->_to_query_args($args);

			$order_method = $args['order']
				? create_function('', 'return "' . $args['order'] . '";')
				: false
			;
			if ($order_method) add_filter('eab-collection-date_ordering_direction', $order_method);

			// Lookahead - depending on presence, use regular upcoming query, or poll week count
			if ($args['lookahead']) {
				$method = $args['weeks']
					? create_function('', 'return ' . $args['weeks'] . ';')
					: false;
				;
				if ($method) add_filter('eab-collection-upcoming_weeks-week_number', $method);
				$events = Eab_CollectionFactory::get_upcoming_weeks_events($args['date'], $query);
				if ($method) remove_filter('eab-collection-upcoming_weeks-week_number', $method);
			} else {
				// No lookahead, get the full month only
				$events = Eab_CollectionFactory::get_upcoming_events($args['date'], $query);
			}
			if ($order_method) remove_filter('eab-collection-date_ordering_direction', $order_method);
		}

		$output = eab_call_template('util_apply_shortcode_template', $events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) wp_enqueue_script('eab_event_js');
		return $output;
	}

	/**
	 * Expired shortcode handler.
	 */
	function process_expired_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
		// Appearance arguments
			'class' => false,
			'template' => 'get_shortcode_archive_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		));
		
		$events = Eab_CollectionFactory::get_expired_events();

		$output = eab_call_template('util_apply_shortcode_template', $events, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) wp_enqueue_script('eab_event_js');
		return $output;
	}
	
	/**
	 * Single event shortcode handler.
	 */
	function process_single_shortcode ($args=array(), $content=false) {
		$args = $this->_preparse_arguments($args, array(
			'id' => false,
			'slug' => false,
		// Appearance arguments
			'class' => false,
			'template' => 'get_shortcode_single_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		));
		$args['id'] = $this->_arg_to_int($args['id']);
		$event = false;

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
		
		$output = eab_call_template('util_apply_shortcode_template', $event, $args);
		$output = $output ? $output : $content;

		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) wp_enqueue_script('eab_event_js');
		return $output;
	}
}
