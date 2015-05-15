<?php
/*
Plugin Name: Recurring Event ShortCode
Description: Use [eab_recurring id="xx"] to show all instances of a recurring event
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Events
*/

/*
Detail: <b>Note:</b> this may take time and resources if you have a lot of events.
*/ 

class Eab_Events_RecurringShortCode {
	
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Events_RecurringShortCode;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_shortcode( 'eab_recurring', array( $this, 'eab_recurring_cb' ) );
	}
	
	function eab_recurring_cb( $args ) {
		$args = shortcode_atts( array(
			'id' => false,
			'slug' => false,
			'class' => false,
			'template' => 'get_shortcode_recurring_output', // Subtemplate file, or template class call
			'override_styles' => false,
			'override_scripts' => false,
		), $args, 'eab_recurring' );
		
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
		
		$rec_events = Eab_CollectionFactory::get_all_recurring_children_events($event);
	
		$out = '<section class="eab-events-archive ' . $args['class'] . '">';
		foreach ($rec_events as $event) {
			$event = $event instanceof Eab_EventModel ? $event : new Eab_EventModel($event);
			$out .= '<article class="eab-event ' . eab_call_template('get_status_class', $event) . '" id="eab-event-' . $event->get_id() . '">' .
				'<h4>' . $event->get_title() . '</h4>' .
				'<div class="eab-event-body">' .
					$this->get_recurring_content( $event ) .
				'</div>' .
			'</article>';
		}
		$out .= '</section>';
		
		
		$output = $out ? $out : $content;
	
		if (!$args['override_styles']) wp_enqueue_style('eab_front');
		if (!$args['override_scripts']) {
			wp_enqueue_script('eab_event_js');
			do_action('eab-javascript-do_enqueue_api_scripts');
		}
		return $output;
		
	}
	
	
	function get_recurring_content($post, $content=false) {
		$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		if ('incsub_event' != $event->get_type()) return $content;
		
		$start_day = date_i18n('m', $event->get_start_timestamp());
	
		$network = $event->from_network();
		$link = $network 
			? get_blog_permalink($network, $event->get_id())
			: get_permalink($event->get_id())
		;
		
		$new_content  = '';
		
		$new_content .= '<div class="event ' . Eab_Template::get_status_class($event) . '" itemscope itemtype="http://schema.org/Event">';
		$new_content .= '<meta itemprop="name" content="' . esc_attr($event->get_title()) . '" />';
		$new_content .= '<a href="' . $link . '" class="wpmudevevents-viewevent">' .
			__('View event', Eab_EventsHub::TEXT_DOMAIN) . 
		'</a>';
		$new_content .= apply_filters('eab-template-archive_after_view_link', '', $event);
		$new_content .= '<div style="clear: both;"></div>';
		$new_content .= '<hr />';
		$new_content .= '<div id="wpmudevevents-contentbody" itemprop="description">' . ($content ? $content : $event->get_content()) . '</div>';
		$new_content .= '<hr />';
		$new_content .= Eab_Template::get_event_details($event);
		$new_content .= Eab_Template::get_rsvp_form($event);
		$new_content .= '</div>';
		$new_content .= '<div style="clear:both"></div>';
		
		return $new_content;
	}
	
}

if (!is_admin()) Eab_Events_RecurringShortCode::serve();
