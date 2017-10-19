<?php
/*
Plugin Name: Email: E-Newsletter integration
Description: Allows you to automatically send newsletters about your events created with e-Newsletter plugin. <br /><b>Requires <a href="http://premium.wpmudev.org/project/e-newsletter">e-Newsletter plugin</a>.</b>
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.1
AddonType: Integration
Author: WPMU DEV
*/

class Eab_Email_eNewsletterIntegration {
	
	private $_model;
	
	private function __construct () {}
	
	public static function serve () {
		$me = new Eab_Email_eNewsletterIntegration;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('init', array($this, 'populate_newsletter_object'));
		add_action('admin_notices', array($this, 'show_nags'));
		
		add_filter('eab-event_meta-meta_box_registration', array($this, 'add_meta_box'));
		add_action('eab-event_meta-after_save_meta', array($this, 'save_meta'));

		// Keep RSVP groups up to date
		add_action('incsub_event_booking_yes', array($this, 'process_booking_yes'), 10, 2);
		add_action('incsub_event_booking_maybe', array($this, 'process_booking_maybe'), 10, 2);
		add_action('incsub_event_booking_no', array($this, 'process_booking_no'), 10, 2);
	}

	function process_booking_yes ($event_id, $user_id) {
		$this->_model->update_rsvp_group($event_id, $user_id, Eab_EventModel::BOOKING_YES);
	}
	function process_booking_maybe ($event_id, $user_id) {
		$this->_model->update_rsvp_group($event_id, $user_id, Eab_EventModel::BOOKING_MAYBE);
	}
	function process_booking_no ($event_id, $user_id) {
		$this->_model->update_rsvp_group($event_id, $user_id, Eab_EventModel::BOOKING_NO);
	}
	
	function populate_newsletter_object () {
		$this->_model = new Eab_Emi_Model;
	}
	
	function show_nags () {
		if (!$this->_model->has_newsletter()) {
			echo '<div class="error"><p>' .
				__("You'll need <a href='http://premium.wpmudev.org/project/e-newsletter'>e-Newsletter</a> plugin installed and activated for E-Newsletter integration add-on to work", Eab_EventsHub::TEXT_DOMAIN) .
			'</p></div>';
		}
	}
	
	function add_meta_box () {
		if (!$this->_model->has_newsletter()) return false;
		add_meta_box('eab-email-newsletter', __('e-Newsletter', Eab_EventsHub::TEXT_DOMAIN), array($this, 'create_meta_box'), 'incsub_event', 'side', 'low');	
	}
	
	function create_meta_box () {
		$newsletters = $this->_model->get_newsletters();
		$expanded = $this->_model->get_expanded_newsletter_ids();
		if (!is_array($expanded)) $expanded = array();
		
		$ret = '';
		$ret .= __('When I save my event, send this newsletter:', Eab_EventsHub::TEXT_DOMAIN);
		$ret .= ' <select name="eab_event-email-enewsletter" id="eab_event-email-enewsletter">';
		$ret .= '<option value="">' . __('Do not send a newsletter', Eab_EventsHub::TEXT_DOMAIN) . '&nbsp;</option>';
		foreach ($newsletters as $news) {
			if (in_array($news['newsletter_id'], $expanded)) continue; // Don't use the already expanded newsletters
			$ret .= "<option value='{$news['newsletter_id']}'>{$news['subject']}</option>";
		}
		$ret .= '</select> ';

		$ret .= '<br />';
		$ret .= '<label for="eab_event-email-enewsletter-as_template">';
		$ret .= '	<input type="checkbox" name="eab_event-email-enewsletter-as_template" id="eab_event-email-enewsletter-as_template" value="1" /> ';
		$ret .= 	__('Use this newsletter as template and expand macros with the event data', Eab_EventsHub::TEXT_DOMAIN);
		$ret .= '</label>';

		$ret .= '<h3>' . __('RSVPs to newsletter group', Eab_EventsHub::TEXT_DOMAIN) . '</h3>';
		$ret .= '<p>' . __('Convert the RSVPs to a new newsletter member group, or update the existing event group with new RSVP info.', Eab_EventsHub::TEXT_DOMAIN) . '</p>';
		$ret .= '<label for="eab_event-email-enewsletter-use_rsvps-yes">';
		$ret .= '	<input type="checkbox" name="eab_event-email-enewsletter-use_rsvps[yes]" id="eab_event-email-enewsletter-use_rsvps-yes" value="1" /> ';
		$ret .= 	__('Positive (&quot;Yes&quot;)', Eab_EventsHub::TEXT_DOMAIN);
		$ret .= '</label>';
		$ret .= '<br />';
		$ret .= '<label for="eab_event-email-enewsletter-use_rsvps-maybe">';
		$ret .= '	<input type="checkbox" name="eab_event-email-enewsletter-use_rsvps[maybe]" id="eab_event-email-enewsletter-use_rsvps-maybe" value="1" /> ';
		$ret .= 	__('Undecided (&quot;Maybe&quot;)', Eab_EventsHub::TEXT_DOMAIN);
		$ret .= '</label>';
		$ret .= '<br />';
		$ret .= '<label for="eab_event-email-enewsletter-use_rsvps-no">';
		$ret .= '	<input type="checkbox" name="eab_event-email-enewsletter-use_rsvps[no]" id="eab_event-email-enewsletter-use_rsvps-no" value="1" /> ';
		$ret .= 	__('Negative (&quot;No&quot;)', Eab_EventsHub::TEXT_DOMAIN);
		$ret .= '</label>';
		$ret .= '<br />';
		
		echo $ret;
	}
	
	function save_meta ($event_id) {
		$newsletter_id = !empty($_POST['eab_event-email-enewsletter']) && is_numeric($_POST['eab_event-email-enewsletter'])
			? (int)$_POST['eab_event-email-enewsletter']
			: false
		;

		if (!empty($_POST['eab_event-email-enewsletter-as_template']) && $newsletter_id) {
			$new_id = $this->_model->newsletter_from_template($newsletter_id, $event_id);
			if (empty($new_id)) return false;
			$newsletter_id = $new_id;
		}

		if (!empty($_POST['eab_event-email-enewsletter-use_rsvps'])) {
			$bookings = array();
			if (!empty($_POST['eab_event-email-enewsletter-use_rsvps']['yes'])) $bookings[] = Eab_EventModel::BOOKING_YES;
			if (!empty($_POST['eab_event-email-enewsletter-use_rsvps']['maybe'])) $bookings[] = Eab_EventModel::BOOKING_MAYBE;
			if (!empty($_POST['eab_event-email-enewsletter-use_rsvps']['no'])) $bookings[] = Eab_EventModel::BOOKING_NO;
			$this->_model->rsvps_to_member_group($event_id, $bookings);
		}

		if (!$newsletter_id) return false;
		
		wp_redirect(
			admin_url('admin.php?page=newsletters&newsletter_action=send_newsletter&newsletter_id=' . $newsletter_id)
		);
		die;
	}

	
}



class Eab_Emi_Model {

	const GROUP_POST_META_KEY = "eab_event_rsvp_group";

	private $_newsletter = null;
	private $_db;

	public function __construct () {
		global $email_newsletter, $wpdb;
		$this->_db = $wpdb;
		if ( isset( $email_newsletter ) && is_object( $email_newsletter ) ) {
			if ( $this->newsletter_tables_exist() ) {
				$this->_newsletter = $email_newsletter;
			}
		}
	}

	public function has_newsletter () {
		return !empty( $this->_newsletter);
	}

	public function get_newsletters () {
		return $this->_newsletter->get_newsletters();
	}

	public function get_meta ($newsletter_id, $meta_key) {
		return $this->_newsletter->get_newsletter_meta($newsletter_id, $meta_key);
	}

	/**
	 * Enewsletter plugin has some issues where the plugin does not create tables. 
	 * So we need to check
	 */
	public function newsletter_tables_exist() {
		global $email_newsletter;
		$email_newsletter->install( get_current_blog_id() );
		$exists 	= false;
		$prefix 	= $this->_get_table_prefix();
		$table_name = $prefix . "enewsletter_meta";
		if ( $this->_db->get_var( $this->_db->prepare( "show tables like %s", $table_name ) ) == $table_name ) {
			$exists = true;
		} else {
			$email_newsletter->upgrade( get_current_blog_id(), 2.704 );
			$exists = false;
		}
		$table_name = $prefix . "enewsletter_newsletters";
		if ( $this->_db->get_var( $this->_db->prepare( "show tables like %s", $table_name ) ) == $table_name ) {
			$exists = true;
		} else {
			$email_newsletter->upgrade( get_current_blog_id(), 2.704 );
			$exists = false;
		}
		return $exists;
	}

	public function get_expanded_newsletter_ids () {
		$prefix = $this->_get_table_prefix();
		$ret = $this->_db->get_col("SELECT DISTINCT email_id FROM {$prefix}enewsletter_meta WHERE meta_key='event_id'");
		return $ret;
	}

	/**
	 * Update cached groups with newly created RSVPs.
	 */
	public function update_rsvp_group ($event_id, $user_id, $rsvp) {
		$event 		= new Eab_EventModel( get_post( $event_id ) );
		$parent 	= $event->is_recurring_child();
		$group_pack = get_post_meta( $event_id, self::GROUP_POST_META_KEY, true );
		if ( empty( $group_pack ) && !empty( $parent ) ) 
			return $this->update_rsvp_group( $parent, $user_id, $rsvp );
		if ( empty( $group_pack ) ) 
			return false;

		$group_ids = array();
		$other_groups = array();
		foreach ($group_pack as $key => $status) {
			if (in_array($rsvp, $status)) $group_ids[] = $key;
			else $other_groups[] = $key;
		}
		if (empty($group_ids)) return false;

		foreach ($group_ids as $group_id) {
			$this->_newsletter->add_members_to_groups($user_id, $group_id, false);
		}

		// Alright, so by now, we added the user to the group.
		// Check her other groups for this event, and see if we need any cleanup/removal

		// Populate drop groups.
		$drop_groups = array();
		if (!empty($other_groups)) {
			$member_groups = $this->_newsletter->get_memeber_groups($user_id);
			$member_groups = !empty($member_groups) && is_array($member_groups)
				? $member_groups
				: array()
			;
			if (!empty($member_groups)) foreach ($other_groups as $other_id) {
				if (in_array($other_id, $member_groups)) $drop_groups[] = $other_id;
			}
		}

		// Process drop groups.
		if (!empty($drop_groups)) {
			$members = array($user_id);
			foreach ($drop_groups as $drop_id) {
				$this->_newsletter->delete_members_group($members, $drop_id);
			}
		}

		
		return true;
	}

	/**
	 * Converts event RSVPs into a send list.
	 */
	public function rsvps_to_member_group ($event_id, $bookings=array()) {
		if (empty($event_id) || empty($bookings)) return false;
		$event = new Eab_EventModel(get_post($event_id));

		$all_events = array($event);
		if ($event->is_recurring()) $all_events = Eab_CollectionFactory::get_all_recurring_children_events($event);
		$all_event_ids = array();
		foreach ($all_events as $e) { $all_event_ids[] = $e->get_id(); }
		$all_event_ids = array_filter(array_map('intval', $all_event_ids));

		$rsvps = $this->_db->get_col(
			"SELECT DISTINCT user_id FROM " . 
				Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE) .
			" WHERE event_id IN(" . join(',', $all_event_ids) . ") AND status IN ('" . join("','", $bookings) . "')"
		);
		//if (empty($rsvps)) return false;

		$group_id = $this->_spawn_event_rsvp_group($event, $bookings);
		if (empty($group_id)) return false;

		return !empty($rsvps)
			? $this->_newsletter->add_members_to_groups($rsvps, $group_id, false)
			: true
		;
	}

	/**
	 * Creating or updating the event RSVP group.
	 * Uses cached approach to verifying the group status.
	 */
	private function _spawn_event_rsvp_group ($event, $bookings) {
		asort($bookings);
		$event_id = $event->get_id();
		$group_key = self::GROUP_POST_META_KEY;
		$prefix = $this->_get_table_prefix();
		
		$packed_group_ids = get_post_meta($event_id, $group_key, true);
		$packed_group_ids = is_array($packed_group_ids) ? $packed_group_ids : array();
		$group_id = false;
		foreach ($packed_group_ids as $key => $status) {
			if ($status != $bookings) continue;
			$group_id = $key;
			break;
		}

		$group_name_suffix = array();
		if (in_array(Eab_EventModel::BOOKING_YES, $bookings)) $group_name_suffix[] = __('Yes', Eab_EventsHub::TEXT_DOMAIN);
		if (in_array(Eab_EventModel::BOOKING_MAYBE, $bookings)) $group_name_suffix[] = __('Maybe', Eab_EventsHub::TEXT_DOMAIN);
		if (in_array(Eab_EventModel::BOOKING_NO, $bookings)) $group_name_suffix[] = __('No', Eab_EventsHub::TEXT_DOMAIN);
		$group_name = !empty($group_name_suffix)
			? sprintf("%s (%s)", $event->get_title(), join(', ', $group_name_suffix))
			: $event->get_title()
		;
		$group_name = apply_filters('eab-email-newsletter-rsvp_group_name', $group_name, $event, $bookings);

		if (!$group_id) {
			$this->_db->insert(
				"{$prefix}enewsletter_groups",
				array(
					'group_name' => $group_name,
					'public' => '0',
				)
			);
			$group_id = $this->_db->insert_id;
		} else {
			$this->_db->update(
				"{$prefix}enewsletter_groups",
				array(
					'group_name' => $group_name,
					'public' => '0',
				),
				array(
					'group_id' => $group_id,
				)
			);
		}
		$packed_group_ids[$group_id] = $bookings;
		update_post_meta($event_id, $group_key, $packed_group_ids);
		return $group_id;
	}

	/**
	 * Takes care of the whole newsletter-as-template expansion.
	 */
	public function newsletter_from_template ($newsletter_id, $event_id) {
		if (empty($newsletter_id) || empty($event_id)) return false;

		$event = new Eab_EventModel(get_post($event_id));
		
		$data = $this->_newsletter->get_newsletter_data($newsletter_id);
		if (empty($data)) return false;


		$codec = new Eab_Macro_Codec($event_id, false);

		$content = false;
		if (!empty($data['content'])) {
			$content = $codec->expand($data['content'], Eab_Macro_Codec::FILTER_BODY);
		} else $content = $this->_get_default_content($event);

		$subject = !empty($data['subject']) ? $data['subject'] : '';
		$expanded = $codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE);
		$subject = !empty($expanded) && $expanded != $subject
			? $expanded
			: (!empty($subject) ? $subject : $this->_get_default_subject($event))
		;

		$title = $this->_newsletter->get_newsletter_meta($newsletter_id, 'email_title');
		$expanded_title = $codec->expand($title, Eab_Macro_Codec::FILTER_TITLE);
		$title = !empty($expanded_title) && $expanded_title != $title
			? $expanded_title
			: $title
		;

		$data = wp_parse_args(array(
			'newsletter_id' => false,
			'subject' => $subject,
			'content' => $content,
		), $data);
		$meta = array(
			'email_title' => $title,
			'event_id' => $event_id,
		);

		$new_id = $this->_duplicate($newsletter_id);
		if (empty($new_id)) return false;

		$prefix = $this->_get_table_prefix();
		$data['newsletter_id'] = $new_id;

		$this->_db->update(
			"{$prefix}enewsletter_newsletters",
			$data,
			array(
				"newsletter_id" => $new_id,
			)
		);
		if (!empty($meta)) foreach ($meta as $key => $value) {
			$this->_db->update(
				"{$prefix}enewsletter_meta",
				array(
					'meta_value' => $value,
				),
				array(
					"email_id" => $new_id,
					"meta_key" => $key,
				)
			);
		}

		return $new_id;
	}

	private function _get_table_prefix () {
		return $this->_db->prefix;
	}

	/**
	 * E-Newsletter API override, since we can't use anything directly.
	 */
	private function _duplicate ($newsletter_id) {
		$prefix = $this->_get_table_prefix();
		$new_newsletter_id = false;

		$result = $this->_db->query(
			$this->_db->prepare(
				"INSERT INTO {$prefix}enewsletter_newsletters
					(create_date, template, subject, from_name, from_email, content, contact_info, bounce_email)
				SELECT %d, template, subject, from_name, from_email, content, contact_info, bounce_email
				FROM {$prefix}enewsletter_newsletters
				WHERE newsletter_id = %d",
			time(), 
			$newsletter_id
		));

		$new_newsletter_id = $this->_db->insert_id;
		if (empty($new_newsletter_id) || !is_numeric($new_newsletter_id)) return false;

		$result = $this->_db->query(
			$this->_db->prepare(
				"INSERT INTO {$prefix}enewsletter_meta
					(email_id, meta_key, meta_value)
				SELECT %d, meta_key, meta_value
				FROM {$prefix}enewsletter_meta
				WHERE email_id = %d",
			$new_newsletter_id, $newsletter_id
		));

		// Auto-add event ID meta key
		$result = $this->_db->insert(
			"{$prefix}enewsletter_meta",
			array(
				'email_id' => $new_newsletter_id,
				'meta_key' => 'event_id',
				'meta_value' => false,
			)
		);

		return $new_newsletter_id;
	}

	private function _get_default_content ($event) {
		$network = $event->from_network();
		$link = $network 
			? get_blog_permalink($network, $event->get_id())
			: get_permalink($event->get_id())
		;
		$content = eab_call_template('get_event_dates', $event) .
			'<div class="eab-event-excerpt">' . $event->get_excerpt_or_fallback(256) . '</div>' .
			"<a href='{$link}'>" . $event->get_title() . '</a>' .
		'';
		return apply_filters('eab-email-newsletter-default_content', $content, $event);
	}

	private function _get_default_subject ($event) {
		$subject = sprintf(__('New event: %s', Eab_EventsHub::TEXT_DOMAIN), $event->get_title());
		return apply_filters('eab-email-newsletter-default_subject', $subject, $event);
	}
}
	
Eab_Email_eNewsletterIntegration:: serve();
