<?php

/**
 * Abstract shortcode codec class.
 */
abstract class Eab_Codec {

	protected $_shortcodes = array();

	private $_positive_values = array(
		true, 'true', 'yes', 'on', '1'
	);

	private $_negative_values = array(
		false, 'false', 'no', 'off', '0'
	);

	private $_thumbnail_sizes = array(
		'thumbnail', 'large', 'medium-large', 'medium', 'full'
	);

	protected function _arg_to_bool ($val) {
		return in_array($val, $this->_positive_values, true);
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

	protected function _arg_to_int_list ($val) {
		if (!strpos($val, ',')) return array();
		return array_filter(array_map('intval', array_map('trim', explode(',', $val))));
	}

	protected function _arg_to_str_list ($val) {
		if (!strpos($val, ',')) return array();
		return array_filter(array_map('trim', explode(',', $val)));
	}

	protected function _preparse_arguments ($raw, $accepted) {
		$_template = false;
		if (!empty($raw['template']) && (defined('EAB_DISALLOW_SHORTCODE_TEMPLATES') && EAB_DISALLOW_SHORTCODE_TEMPLATES)) {
			$_template = $raw['template'];
			unset($raw['template']);
		}

		$args = wp_parse_args($raw, $accepted);
		if (isset($raw['network'])) $args['network'] = $this->_arg_to_bool($args['network']);
		if (isset($raw['relative_date']) && !empty($args['relative_date'])) {
			$pivot = !empty($args['date'])
				? strtotime($args['date'])
				: eab_current_time()
			;
			$relative_date = strtotime($args["relative_date"], $pivot);
			if ($relative_date) $args['date'] = date("Y-m-d H:i:s", $relative_date);
		}
		if (isset($args['date'])) {
			//$args['date'] = $this->_arg_to_time($args['date']);
		}

		if(isset($raw['show_old']) && $this->_arg_to_bool($raw['show_old'])) {
			$args['date'] = $accepted['date'];
		}
		
		if (isset($raw['lookahead'])) $args['lookahead'] = $this->_arg_to_bool($args['lookahead']);
		if (isset($raw['weeks'])) $args['weeks'] = $this->_arg_to_int($args['weeks']);

		if (isset($raw['limit'])) $args['limit'] = $this->_arg_to_int($args['limit']);
		if (isset($raw['order'])) $args['order'] = $args['order'] && in_array(strtoupper($args['order']), array('ASC', 'DESC'))
			? strtoupper($args['order'])
			: false
		;
		if (isset($raw['category']) && $args['category']) {
			$args['category'] = $this->_arg_to_int($args['category'])
				? array('type' => 'id', 'value' => $this->_arg_to_int($args['category']))
				: array('type' => 'slug', 'value' => $args['category'])
			;
		}
		if (isset($raw['categories']) && !empty($args['categories'])) {
			$args['categories'] = $this->_arg_to_int_list($args['categories'])
				? array('type' => 'id', 'value' => $this->_arg_to_int_list($args['categories']))
				: false
			;
		}
		if (isset($raw['paged'])) $args['paged'] = $this->_arg_to_bool($args['paged']);
		if (isset($raw['page'])) $args['page'] = $this->_arg_to_int($args['page']);

		if (isset($raw['navigation'])) $args['navigation'] = $this->_arg_to_bool($args['navigation']);

		if (isset($raw['override_styles'])) $args['override_styles'] = $this->_arg_to_bool($args['override_styles']);
		if (isset($raw['override_scripts'])) $args['override_scripts'] = $this->_arg_to_bool($args['override_scripts']);

		if (isset($raw['show_old'])) $args['show_old'] = $this->_arg_to_bool($args['show_old']);

		if (isset($raw['end_date'])) $args['end_date'] = $this->_arg_to_time($args['end_date']);

		if ($_template && defined('EAB_DISALLOW_SHORTCODE_TEMPLATES') && EAB_DISALLOW_SHORTCODE_TEMPLATES) {
			$args['template'] = $_template;
		}

		if (isset($raw['with_thumbnail'])) $args['with_thumbnail'] = $this->_arg_to_bool($args['with_thumbnail']);

		$args['thumbnail_size'] = 'post-thumbnail';
		if ($args['with_thumbnail'] && isset($raw['thumbnail_size'])) {
		    if(in_array($raw['thumbnail_size'], $this->_thumbnail_sizes)) {
				$args['thumbnail_size'] = $raw['thumbnail_size'];
		    } else {
				if(strpos($raw['thumbnail_size'], ',')) {
			    	$thumbsize = explode(',', $raw['thumbnail_size']);
			    	$args['thumbnail_size'] = array($thumbsize[0], $thumbsize[1]);
				}
		    }
		}

		if (isset($raw['day_only'])) $args['day_only'] = $this->_arg_to_bool($args['day_only']);

		return $args;
	}

	protected function _to_query_args ($args) {
		$query = array();
		// Parse query arguments
		$taxonomy = !empty($args['category']) && is_array($args['category'])
			? $args['category']
			: (!empty($args['categories']) && is_array($args['categories']) ? $args['categories'] : false)
		;
		if (!empty($taxonomy)) {
			$query['tax_query'] = array(array(
				'taxonomy' => 'eab_events_category',
				'field' => $taxonomy['type'],
				'terms' => $taxonomy['value'],
			));
		}
		if (isset( $args['limit'] ) ) {
			$query['posts_per_page'] = $args['limit'];
		}
		if ( isset( $args['paged'] ) ) {
			$query['paged'] = $args['page'];
		}
		return $query;
	}

	/**
	 * Registers shortcode handlers.
	 */
	protected function _register () {
		$shortcodes = $this->_shortcodes;
		foreach ($shortcodes as $key => $shortcode) {
			if (is_callable(array($this, "process_{$key}_shortcode"))) add_shortcode($shortcode, array($this, "process_{$key}_shortcode"));
			if (is_callable(array($this, "add_{$key}_shortcode_help"))) add_filter('eab-shortcodes-shortcode_help', array($this, "add_{$key}_shortcode_help"));
		}
	}
}

/**
 * Simple arguments parsing implementation for add-ons that do not implement Eab_Codec.
 */
class Eab_Codec_ArgumentsCodec extends Eab_Codec {
	protected function _register () {} // We won't be registering anything

	public function parse_arguments ($args=array(), $accepted=array()) {
		return $this->_preparse_arguments($args, $accepted);
	}

	public function get_query_args ($args=array()) {
		return $this->_to_query_args($args);
	}
}


/**
 * Macro expansion codec class.
 */
class Eab_Macro_Codec {

	const FILTER_TITLE = 'title';
	const FILTER_BODY = 'body';

	protected $_macros = array(
		'EVENT_NAME',
		'EVENT_START_DATE',
		'EVENT_END_DATE',
		'EVENT_DATE_INFO',
		'EVENT_BODY',
		'EVENT_BODY_HTML',
		'EVENT_VENUE',
		'EVENT_URL',
		'EVENT_LINK',
		'EVENT_HOST',
		'USER_NAME',
	);

	protected $_event;
	protected $_user;

	public function __construct ($event_id=false, $user_id=false) {
		$this->_macros = apply_filters('eab-codec-macros', $this->_macros);
		$this->_event = $event_id ? new Eab_EventModel(get_post($event_id)) : false;
		$this->_user = $user_id ? get_user_by('id', $user_id) : false;
	}

	public function get_macros () {
		return $this->_macros;
	}

	public function set_user ($user) {
		if (is_object($user)) $this->_user = $user;
	}

	public function expand ($str, $filter=false) {
		if (!$str) return $str;
		foreach ($this->_macros as $macro) {
			$callback = false;
			$method = 'replace_' . strtolower($macro);
			if (is_callable(array($this, $method))) {
				$callback = array($this, $method);
				$str = preg_replace_callback(
					'/(?:^|\b)' . preg_quote($macro, '/') . '(?:\b|$)/',
					$callback, $str
				);
			}
		}
		if (!$filter || self::FILTER_BODY == $filter) $str = apply_filters('the_content', $str);
		if (self::FILTER_TITLE == $filter) $str = wp_strip_all_tags($str);
		return apply_filters('eab-codec-expand', $str, $this->_event);
	}

	public function replace_event_name () {
		return $this->_event->get_title();
	}

	public function replace_event_start_date () {
		return date_i18n(
			get_option('date_format') . ' ' . get_option('time_format'),
			$this->_event->get_start_timestamp()
		);
	}

	public function replace_event_end_date () {
		return date_i18n(
			get_option('date_format') . ' ' . get_option('time_format'),
			$this->_event->get_end_timestamp()
		);
	}

	public function replace_event_date_info () {
		return wp_strip_all_tags(eab_call_template('get_event_dates', $this->_event));
	}

	public function replace_event_url () {
		return get_permalink($this->_event->get_id());
	}

	public function replace_event_link () {
		return eab_call_template('get_event_link', $this->_event);
	}

	public function replace_event_host () {
		return wp_strip_all_tags(eab_call_template('get_event_author_link', $this->_event));
	}

	public function replace_event_venue () {
		return $this->_event->get_venue_location(Eab_EventModel::VENUE_AS_ADDRESS);
	}

	public function replace_event_body () {
		return wp_strip_all_tags($this->_event->get_content());
	}

	public function replace_event_body_html () {
		return $this->_event->get_content();
	}

	public function replace_user_name () {
		return $this->_user->display_name;
	}

}
