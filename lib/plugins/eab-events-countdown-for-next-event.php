<?php
/*
Plugin Name: Next Event Countdown
Description: Generates a flexible countdown shortcode for the next upcoming event that has not started yet. Visitor viewing the page can be redirected to any url when countdown expires. 
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 0.27
Author: WPMU DEV
AddonType: Events
*/

/*
Detail: Minimal usage: [next_event_countdown]<br />Extended Usage: [next_event_countdown id="1" format="dHMS" goto="http://example.com" class="countdown-class" type="flip" size="70" add="-120" expired="Too late!" title="yes"]<br />For explanation of the parameters, please see Event Countdown.


Minimal usage:
[next_event_countdown]

Extended Usage:
[next_event_countdown id="1" format="dHMS" goto="http://example.com" class="countdown-class" type="flip" size="70" add="-120" expired="Expired"]
Where:
@id is a unique id. Only necessary and mandatory if more than one instance will be used on the same page. Default is null.

@format is the countdown format of the output as defined in http://keith-wood.name/countdown.html
e.g. "dHMS", which is the default, will countdown using days (unless it is not zero), hours, minutes and seconds. 
Lowercase means, that time part will be showed if not zero.
Uppercase means, that time part will always be displayed. 
As default, days will only be displayed when necessary, the rest will be shown even if they are zero.

@goto is the page that visitor will be redirected to when countdown expires. Default is null (No redirection).
Tip: Just to refresh the current page (e.g. letting other plugins to redirect the visitor or cleaning the countdown) enter: window.location.href

@class is the name of the wrapper class. Default is null.

More Styling: Modify the css file events-and-bookings/js/jquery.countdown.css

@type: if entered "flip", creates a flip counter, like in airport terminals.

@size: Width of a digit in px only for flip counter. Supported sizes are 70, 82, 127, 254. Default is 70.
Note that if the content width is not wide enough, digits may overlap.

@add: How many minutes to add to the countdown. Default is naturally zero. It can take negative values.
For example, if you have a "Doors open time" of 2 hours before the event, enter -120 (=>2 hours) here.

@title: Show event title. Supported values: "yes" or "no"
If set to "yes", the event countdown will also include the event title.

Localization: Download the language pack from http://keith-wood.name/countdown.html and upload it in events-and-bookings/js/ folder.
Countdown will automatically switch to your local settings as defined in locale setting or WPLANG of wp-config.php. 
If this language javascript file does not exist, English will be used.
Note from wordpress.org: If you have a site network (Wordpress multisite), 
the language is set on a per-blog basis through the "Site language" option in the Settings->General subpanel.

*/

class Eab_Events_CountdownforNextEvent {

	private static $_scripts;

	/**
	 * Constructor
	 */	
	private function __construct () {
		$this->add_countdown = false;
	}

	/**
	 * Run the Addon
	 */	
	public static function serve () {
		$me = new Eab_Events_CountdownforNextEvent;
		$me->_add_hooks();
	}

	/**
	 * Hooks 
	 */	
	private function _add_hooks () {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts') );
		add_shortcode( 'next_event_countdown', array($this, 'shortcode') );
		add_action( 'wp_footer', array($this, 'load_scripts_footer') );
		add_filter( 'the_posts', array($this, 'load_styles') );
	}

	/**
	 * Register jQuery countdown
	 */		
	function register_scripts() {
		wp_register_script('jquery-countdown', EAB_PLUGIN_URL.'js/jquery.countdown.min.js', array('jquery','jquery-ui-widget'), Eab_EventsHub::CURRENT_VERSION);
	}

	/**
	 * Load style only when they are necessary
	 * http://beerpla.net/2010/01/13/wordpress-plugin-development-how-to-include-css-and-javascript-conditionally-and-only-when-needed-by-the-posts/
	 */		
	function load_styles( $posts ) {
		if ( empty($posts) OR is_admin() ) 
			return $posts;
	
		$shortcode_found = false; // use this flag to see if styles and scripts need to be enqueued
		foreach ($posts as $post) {
			if (stripos($post->post_content, 'next_event_countdown') !== false) {
				$shortcode_found = true;
				break;
			}
		}
 
		if ($shortcode_found) {
			wp_enqueue_style('jquery-countdown', EAB_PLUGIN_URL .'css/jquery.countdown.css');
			if ( ! defined('EAB_COUNTDOWN_FLAG_STYLES_INJECTED') ) {
				define( 'EAB_COUNTDOWN_FLAG_STYLES_INJECTED', true ); // Don't double-enqueue
			}
		}
 
		return $posts;
	}
	/**
	 * Load scripts to the footer only when they are necessary
	 */		
	function load_scripts_footer() {
		if ( $this->add_countdown ) {
			wp_enqueue_script('jquery-countdown');
				if ( $locale = $this->locale() )
			wp_enqueue_script('jquery-countdown-'.$locale,EAB_PLUGIN_URL.'js/jquery.countdown-'.$locale.'.js',array('jquery-countdown'));

			if (!(defined('EAB_COUNTDOWN_FLAG_STYLES_INJECTED') && EAB_COUNTDOWN_FLAG_STYLES_INJECTED)) {
				wp_enqueue_style('jquery-countdown',EAB_PLUGIN_URL.'css/jquery.countdown.css'); // E.g. in a widget
			}
		}
	}

	/**
	 * Check if a localized countdown js file exists and locale settings match
	 */		
	function locale() {
		if ( !$locale = str_replace( "_", "-", get_locale() ) )
			return false;
		
		// First check with full match, e.g. zh-CN	
		if ( file_exists( EAB_PLUGIN_DIR . "js/jquery.countdown-".$locale.".js" ) )
			return $locale;
		// Then check the first abbr. e.g. zh
		list( $locale1, $locale2 ) = explode( "-", $locale );
		if ( file_exists( EAB_PLUGIN_DIR . "js/jquery.countdown-".$locale1.".js" ) )
			return $locale1;
			
		// No localized js file exists, use English
		return false;
	}

	public function shortcode ($args=array(), $content='') {
		$original_arguments = $args;
		$codec = new Eab_Codec_ArgumentsCodec;
		$args = $codec->parse_arguments($args, array(
			'id' => '',
			'format' => 'dHMS',
			'goto' => '',
			'class' => '',
			'type' => '',
			'size' => 70,
			'add' => 0,
			'allow_scaling' => false, // Scaling allowing boolean switch
			'compact' => false, // Boolean compact flag
			'title' => false,
			'footer_script' => false,
			'expired' => __('Closed', Eab_EventsHub::TEXT_DOMAIN),
			'legacy' => false,
			'category' => false,
			'categories' => false,
			'weeks' => false
		)); 

		if (!empty($args['legacy'])) return $this->_legacy_shortcode($original_arguments);
		$class = !empty($args['class'])
			? 'class="' . sanitize_html_class($args['class']) . '"'
			: ''
		;

		$id = str_replace(array(" ","'",'"'), "", $args['id']); // We cannot let spaces and quotes in id
			
		// Do not add quotes for page refresh
		if ( $args['goto'] && $args['goto'] != "window.location.href" )
			$args['goto'] = "'". str_replace( array("'",'"'), "", $args['goto'] ). "'"; // Do not allow quotes which may break js

		$transform = false;
		if ($args['size'] < 70 && !empty($args['allow_scaling'])) {
			$transform = $args['size'] / 70;
		}
		switch ($args['size']) {
			case 70:	$height = 72; break;
			case 82:	$height = 84; break;
			case 127:	$height = 130; break;
			case 254:	$height = 260; break;
			default:	$args['size'] = 70; $height = 72; break;
		}
		
		$sprite_file = EAB_PLUGIN_URL . '/img/sprite_'.$args['size'].'x'.$height.'.png';

		$secs = -1;
		$additional = 0;
		if (!empty($args['add']) && (int)$args['add']) {
			$additional = (int)$args['add'] * 60;
		}
		$query = $codec->get_query_args($args);
		$now = eab_current_time() + $additional;
		
		//$events = Eab_CollectionFactory::get_upcoming_events($now, $query);

		$future_peeking_method = false;
		if (!empty($args['weeks']) && is_numeric($args['weeks'])) $future_peeking_method = create_function('', 'return ' . (int)$args['weeks'] . ';');

		if (!empty($future_peeking_method)) add_filter('eab-collection-upcoming_weeks-week_number', $future_peeking_method);
		$events = Eab_CollectionFactory::get_upcoming_weeks_events($now, $query);
		if (!empty($future_peeking_method)) remove_filter('eab-collection-upcoming_weeks-week_number', $future_peeking_method);
		
		$ret = array();
		foreach ($events as $event) {
			$ts = $event->get_start_timestamp();
			if ($ts < $now) continue;
			$ret[$ts] = $event;
		}
		ksort($ret);
		$next = reset($ret);
		if ($next) $secs = $next->get_start_timestamp() - $now;
		else return $content;

		$script  = '';
		$script .= "<script type='text/javascript'>";
		$script .= "jQuery(document).ready(function($) {";
		$script .= "$('#eab_next_event_countdown".$id."').countdown({
					format: '".$args['format']."',
					expiryText: '".$args['expired']."',
					until: ".$secs.","
		;
		if ($args['goto']) {
			$script .= "onExpiry: eab_next_event_refresh".$id.",";
		}
		if ($args['type'] == 'flip') {
			$script .= "onTick: function () { $(document).trigger('eab-event_countdown-tick', [$(this), '{$sprite_file}']);},";
		}
		$script .= "alwaysExpire: true});";
		if ($args['goto']) {
			$script .= "function eab_next_event_refresh".$id."() {window.location.href=".$args['goto'].";}";
		}

		$script .= "});</script>";

		if ('flip' == $args['type']) {
			$script .= '<script type="text/javascript" src="' . plugins_url(basename(EAB_PLUGIN_DIR) . "/js/event_countdown_flip.js") . '"></script>';
		}
		
		// remove line breaks to prevent wpautop break the script
		$script = str_replace( array("\r","\n","\t","<br>","<br />"), "", preg_replace('/\s+/m', ' ', $script) );
		
		$this->add_countdown = true;

		$markup = '<div class="eab_next_event_countdown-wrapper">' .
			($args['title']
				? '<h4><a href="' . get_permalink($next->get_id()) . '">' . $next->get_title() . '</a></h4>'
				: ''
			) . 
			"<div id='eab_next_event_countdown{$id}' {$class} data-height='{$height}' data-size='" . $args['size'] . "'></div>" . 
		'</div>';

		if ($transform && !empty($args['allow_scaling'])) {
			$markup .= <<<EOStandardTransformCSS
<style type="text/css">
#eab_next_event_countdown{$id} .countdown_section { 
	transform: scale({$transform},{$transform});
	-ms-transform: scale({$transform},{$transform});
	-webkit-transform: scale({$transform},{$transform});
}
</style>
EOStandardTransformCSS;
		}

		if (!empty($args['size']) && !empty($args['compact'])) {
			$base_size = $transform && !empty($args['allow_scaling']) ? $args['size'] * $transform : $args['size'];
			$max_width = ($base_size * 8) + 20;
			$markup .= <<<EOStandardCompactCSS
<style type="text/css">
#eab_next_event_countdown{$id} {
	max-width: {$max_width}px;
}
</style>
EOStandardCompactCSS;
		}

		if ($args['footer_script'] && in_array($args['footer_script'], array('yes', 'true', '1'))) {
			self::add_script($script);
			add_action('wp_footer', array($this, 'inject_queued_scripts'), 99);
		} else {
			$markup .= $script;
		}

		return $markup;
	}

	/**
	 * Generate shortcode
	 */	
	private function _legacy_shortcode( $atts ) {
	
		extract( shortcode_atts( array(
		'id'		=> '',
		'format'	=> 'dHMS',
		'goto'		=> '',
		'class'		=> '',
		'type'		=> '',
		'size'		=> 70,
		'add'		=> 0,
		'allow_scaling' => false, // Scaling allowing boolean switch
		'compact' => false, // Boolean compact flag
		'title'		=> false,
		'footer_script' => false,
		'expired'	=> __('Closed', Eab_EventsHub::TEXT_DOMAIN)
		), $atts ) );
		
		$id = str_replace( array(" ","'",'"'), "", $id ); // We cannot let spaces and quotes in id
		$goto = trim( $goto );
		
		if ( $class )
			$class = " class='".$class."'";
			
		// Do not add quotes for page refresh
		if ( $goto && $goto != "window.location.href" )
			$goto = "'". str_replace( array("'",'"'), "", $goto ). "'"; // Do not allow quotes which may break js

		$transform = false;
		if ($size < 70 && !empty($allow_scaling)) {
			$transform = $size / 70;
		}
		switch ($size) {
			case 70:	$height = 72; break;
			case 82:	$height = 84; break;
			case 127:	$height = 130; break;
			case 254:	$height = 260; break;
			default:	$size = 70; $height = 72; break;
		}
		
		$sprite_file = EAB_PLUGIN_URL . 'img/sprite_'.$size.'x'.$height.'.png';
		
		global $wpdb;
		
		$result = $wpdb->get_row(
			"SELECT estart.* 
			FROM $wpdb->posts wposts, $wpdb->postmeta estart, $wpdb->postmeta eend, $wpdb->postmeta estatus
			WHERE 
			wposts.ID=estart.post_id AND wposts.ID=eend.post_id AND wposts.ID=estatus.post_id 
			AND estart.meta_key='incsub_event_start' AND estart.meta_value > DATE_ADD(UTC_TIMESTAMP(),INTERVAL ". ( current_time('timestamp') - time() - 60 * abs($add) ). " SECOND)
			AND eend.meta_key='incsub_event_end' AND eend.meta_value > estart.meta_value
			AND estatus.meta_key='incsub_event_status' AND estatus.meta_value <> 'closed'
			AND wposts.post_type='incsub_event' AND wposts.post_status='publish'
			ORDER BY estart.meta_value ASC
			LIMIT 1
			");
		
		// Find how many seconds left to the event
		if ( $result == null )
			$secs = -1; 
		else
			$secs = strtotime( $result->meta_value ) - current_time('timestamp') + 60 * (int)$add;

		$script  = '';
		$script .= "<script type='text/javascript'>";
		$script .= "jQuery(document).ready(function($) {";
		$script .= "$('#eab_next_event_countdown".$id."').countdown({
					format: '".$format."',
					expiryText: '".$expired."',
					until: ".$secs.","
		;
		if ($goto) {
			$script .= "onExpiry: eab_next_event_refresh".$id.",";
		}
		if ($type == 'flip') {
			$script .= "onTick: function () { $(document).trigger('eab-event_countdown-tick', [$(this), '{$sprite_file}']);},";
		}
		$script .= "alwaysExpire: true});";
		if ($goto) {
			$script .= "function eab_next_event_refresh".$id."() {window.location.href=".$goto.";}";
		}

		$script .= "});</script>";

		if ('flip' == $type) {
			$script .= '<script type="text/javascript" src="' . plugins_url(basename(EAB_PLUGIN_DIR) . "/js/event_countdown_flip.js") . '"></script>';
		}
		
		// remove line breaks to prevent wpautop break the script
		$script = str_replace( array("\r","\n","\t","<br>","<br />"), "", preg_replace('/\s+/m', ' ', $script) );
		
		$this->add_countdown = true;

		$markup = '<div class="eab_next_event_countdown-wrapper">' .
			($title && in_array($title, array('yes', 'true', '1'))
				? '<h4><a href="' . get_permalink($result->post_id) . '">' . get_the_title($result->post_id) . '</a></h4>'
				: ''
			) . 
			"<div id='eab_next_event_countdown{$id}' {$class} data-height='{$height}' data-size='{$size}'></div>" . 
		'</div>';

		if ($transform && !empty($allow_scaling)) {
			$markup .= <<<EOTransformCSS
<style type="text/css">
#eab_next_event_countdown{$id} .countdown_section { 
	transform: scale({$transform},{$transform});
	-ms-transform: scale({$transform},{$transform});
	-webkit-transform: scale({$transform},{$transform});
}
</style>
EOTransformCSS;
		}

		if (!empty($size) && !empty($compact)) {
			$base_size = $transform && !empty($allow_scaling) ? $size * $transform : $size;
			$max_width = ($base_size * 8) + 20;
			$markup .= <<<EOCompactCSS
<style type="text/css">
#eab_next_event_countdown{$id} {
	max-width: {$max_width}px;
}
</style>
EOCompactCSS;
		}

		if ($footer_script && in_array($footer_script, array('yes', 'true', '1'))) {
			self::add_script($script);
			add_action('wp_footer', array($this, 'inject_queued_scripts'), 99);
		} else {
			$markup .= $script;
		}

		return $markup;
	}

	private static function add_script ($script) {
		if (is_array(self::$_scripts)) self::$_scripts[] = $script;
		else self::$_scripts = array($script);
	}

	function inject_queued_scripts () {
		if (defined('EAB_COUNTDOWN_FLAG_SCRIPTS_INJECTED')) return false;
		if (empty(self::$_scripts)) return false;
		foreach (self::$_scripts as $script) echo $script;
		define('EAB_COUNTDOWN_FLAG_SCRIPTS_INJECTED', true);
	}
}

Eab_Events_CountdownforNextEvent::serve();