<?php
/*
Plugin Name: Public Announcement Events
Description: Allows you to create Public Announcement events, which will have no RSVP capabilities.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: Ve Bailovity (Incsub)
*/

class Eab_Events_Pae {
	
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Events_Pae;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_filter('eab-event_meta-event_meta_box-after', array($this, 'add_meta_box'));
		add_action('eab-event_meta-save_meta', array($this, 'save_meta'));
		add_action('eab-events-recurrent_event_child-save_meta', array($this, 'save_meta'));
		
		add_filter('eab-rsvps-rsvp_form', array($this, 'handle_rsvp_form'));
	}
	
	function handle_rsvp_form ($content) {
		global $post;
		$post_id = (int)@$post->ID;
		$is_pae = (int)get_post_meta($post_id, 'eab_public_announcement', true);
		return $is_pae ? false : $content;
	}
	
	function add_meta_box ($box) {
		global $post;
		
		$is_pae = (int)get_post_meta($post->ID, 'eab_public_announcement', true);
		$checked = $is_pae ? 'checked="checked"' : '';
		
		$ret = '';
		$ret .= '<div class="eab_meta_box">';
		$ret .= '<div class="misc-eab-section" >';
		$ret .= '<div class="eab_meta_column_box top"><label for="eab_event_pae">' .
			__('Public Announcement', Eab_EventsHub::TEXT_DOMAIN) . 
		'</label></div>';
		
		$ret .= '<input type="hidden" name="eab-is_pae" value="0" /> ';
		$ret .= '<input type="checkbox" name="eab-is_pae" id="eab_event_is_pae" value="1" ' . $checked . '" /> ';
		$ret .= ' <label for="eab_event_is_pae">' . __('This is a Public Announcement event', Eab_EventsHub::TEXT_DOMAIN) . '</label>';
		
		$ret .= '</div>';
		$ret .= '</div>';

		return $box . $ret;
	}
	
	private function _save_meta ($post_id, $request) {
		if (!isset($request['eab-is_pae'])) return false;		
		$is_pae = (int)$request['eab-is_pae'];
		update_post_meta($post_id, 'eab_public_announcement', $is_pae);
	}
	
	function save_meta ($post_id) {
		$this->_save_meta($post_id, $_POST);	
	}
}

Eab_Events_Pae::serve();
