<?php
/*
Plugin Name: BuddyPress: Activity auto-updates
Description: Auto-post an activity update when something happens with your Events.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.1
AddonType: BuddyPress
Author: WPMU DEV
*/

/*
Detail: This add-on will automatically publish an activity update whenever a predefined action happens in Events+, according to your settings.
*/ 

class Eab_BuddyPress_AutoUpdateActivity {
	
	private $_data;
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}
	
	public static function serve () {
		$me = new Eab_BuddyPress_AutoUpdateActivity;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action('admin_notices', array($this, 'show_nags'));
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
		
		add_action('eab-event_meta-after_save_meta', array($this, 'dispatch_creation_activity_update'));
		add_action('eab-events-fpe-save_meta', array($this, 'dispatch_creation_activity_update'));
		
		add_action('incsub_event_booking_yes', array($this, 'dispatch_positive_rsvp_activity_update'), 10, 2);
		add_action('incsub_event_booking_maybe', array($this, 'dispatch_maybe_rsvp_activity_update'), 10, 2);
		add_action('incsub_event_booking_no', array($this, 'dispatch_negative_rsvp_activity_update'), 10, 2);
	}

	function dispatch_creation_activity_update ($post_id) {
		if (!function_exists('bp_activity_get')) return false; // WTF
		
		$created = $this->_data->get_option('bp-activity_autoupdate-event_created');
		if (!$created) return false;

		$event = new Eab_EventModel(get_post($post_id));
		if (!$event->is_published()) return false;
		
		$user_link = bp_core_get_userlink($event->get_author());
		$update = false;

		$group_id =  $this->_is_group_event($event->get_id());
		$public_announcement = $this->_is_public_announcement($event->get_id());
		
		if ('any' == $created) {
			$update = sprintf(__('%s created an event', Eab_EventsHub::TEXT_DOMAIN), $user_link);
		} else if ('group' == $created && $group_id) {
			$group = groups_get_group(array('group_id' => $group_id));
			$group_link = bp_get_group_permalink($group);
			$group_name = bp_get_group_name($group);
			$update = sprintf(__('%s created an event in <a href="%s">%s</a>', Eab_EventsHub::TEXT_DOMAIN), $user_link, $group_link, $group_name);
		} else if ('pa' == $created && $public_announcement) {
			$update = sprintf(__('%s created a public announcement', Eab_EventsHub::TEXT_DOMAIN), $user_link);
		}

		if (!$update) return false;
		$update = sprintf("{$update}, <a href='%s'>%s</a>", get_permalink($event->get_id()), $event->get_title());

		$existing = bp_activity_get(array("filter" => array(
			"object" => 'eab_events',
			"action" => 'event_created',
			'primary_id' => $event->get_id(),
		)));
		if (isset($existing['activities']) && !empty($existing['activities'])) return false;

		$activity = array(
			'action' => $update,
			'component' => 'eab_events',
			'type' => 'event_created',
			'item_id' => $event->get_id(),
			'user_id' => $event->get_author()
		);
		bp_activity_add($activity);

		if ($this->_data->get_option('bp-activity_autoupdate-created_group_post') && $group_id) {
			global $bp;
			$group_activity = $activity;
			$group_activity['component'] = $bp->groups->id;
			$group_activity['item_id'] = $group_id;
			$group_activity['secondary_item_id'] = $event->get_id();
			$existing = bp_activity_get(array("filter" => array(
				'user_id' => $user_id,
				"object" => $bp->groups->id,
				"action" => 'event_created',
				'primary_id' => $group_id,
				'secondary_id' => $event->get_id(),
			)));
			if (isset($existing['activities']) && !empty($existing['activities'])) {
				$old = reset($existing['activities']);
				if (is_object($old) && isset($old->id)) $group_activity['id'] = $old->id;
			}
			
			// Add group activity update
			groups_record_activity($group_activity);
		}
	}

	function dispatch_positive_rsvp_activity_update ($event_id, $user_id) {
		if (!$this->_data->get_option('bp-activity_autoupdate-user_rsvp_yes')) return false;
		return $this->_construct_unique_rsvp_activity($event_id, $user_id, Eab_EventModel::BOOKING_YES);
	}

	function dispatch_maybe_rsvp_activity_update ($event_id, $user_id) {
		if (!$this->_data->get_option('bp-activity_autoupdate-user_rsvp_maybe')) return false;
		return $this->_construct_unique_rsvp_activity($event_id, $user_id, Eab_EventModel::BOOKING_MAYBE);
	}

	function dispatch_negative_rsvp_activity_update ($event_id, $user_id) {
		if (!$this->_data->get_option('bp-activity_autoupdate-user_rsvp_no')) return false;
		return $this->_construct_unique_rsvp_activity($event_id, $user_id, Eab_EventModel::BOOKING_NO);
	}

	private function _construct_unique_rsvp_activity ($event_id, $user_id, $rsvp) {
		$group_id =  $this->_is_group_event($event_id);
		if ($this->_data->get_option('bp-activity_autoupdate-user_rsvp_group_only') && !$group_id) return false;

		$event = new Eab_EventModel(get_post($event_id));
		$user_link = bp_core_get_userlink($user_id);
		$update = false;

		switch ($rsvp) {
			case Eab_EventModel::BOOKING_YES:
				$update = sprintf(__('%s will be attending <a href="%s">%s</a>', Eab_EventsHub::TEXT_DOMAIN), $user_link, get_permalink($event->get_id()), $event->get_title());
				break;
			case Eab_EventModel::BOOKING_MAYBE:
				$update = sprintf(__('%s will maybe attend <a href="%s">%s</a>', Eab_EventsHub::TEXT_DOMAIN), $user_link, get_permalink($event->get_id()), $event->get_title());
				break;
			case Eab_EventModel::BOOKING_NO:
				$update = sprintf(__('%s won\'t be attending <a href="%s">%s</a>', Eab_EventsHub::TEXT_DOMAIN), $user_link, get_permalink($event->get_id()), $event->get_title());
				break;
		}
		if (!$update) return false;

		$raw_activity = array(
			'user_id' => $user_id,
			'action' => $update,
			'type' => 'event_rsvp',
			'item_id' => $event->get_id(),
		);
		
		if ($this->_data->get_option('bp-activity_autoupdate-user_rsvp_group_post') && $group_id) {
			global $bp;
			$group_activity = $raw_activity;
			$group_activity['component'] = $bp->groups->id;
			$group_activity['item_id'] = $group_id;
			$group_activity['secondary_item_id'] = $event->get_id();
			$existing = bp_activity_get(array("filter" => array(
				'user_id' => $user_id,
				"object" => $bp->groups->id,
				"action" => 'event_rsvp',
				'primary_id' => $group_id,
				'secondary_id' => $event->get_id(),
			)));
			if (isset($existing['activities']) && !empty($existing['activities'])) {
				$old = reset($existing['activities']);
				if (is_object($old) && isset($old->id)) $group_activity['id'] = $old->id;
			}
			
			// Add group activity update
			groups_record_activity($group_activity);
		} else {
			$activity = $raw_activity;
			$activity['component'] = 'eab_events';
			$existing = bp_activity_get(array("filter" => array(
				'user_id' => $user_id,
				"object" => 'eab_events',
				"action" => 'event_rsvp',
				'primary_id' => $event->get_id(),
			)));
			if (isset($existing['activities']) && !empty($existing['activities'])) {
				$old = reset($existing['activities']);
				if (is_object($old) && isset($old->id)) $activity['id'] = $old->id;
			}
			
			// Add site/user activity update
			bp_activity_add($activity);
		}
	}
	
	function show_nags () {
		$msg = false;
		if (!defined('BP_VERSION')) {
			$msg = __("You'll need BuddyPress installed and activated for Activity auto-updates add-on to work", Eab_EventsHub::TEXT_DOMAIN);
		} else if (!class_exists('BP_Activity_Activity')) {
			$msg = __("BuddyPress Activities component has to be enabled for Activity auto-updates add-on to work", Eab_EventsHub::TEXT_DOMAIN);
		}
		if (!$msg) return false;

		echo "<div class='error'><p>{$msg}</p></div>";
	}

	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url( EAB_PLUGIN_URL . 'img/information.png' );

		$_created = $this->_data->get_option('bp-activity_autoupdate-event_created');
		$event_created = 'any' == $_created ? 'checked="checked"' : false;
		$group_event_created = class_exists('Eab_BuddyPress_GroupEvents') && 'group' == $_created ? 'checked="checked"' : false;
		$pa_event_created = class_exists('Eab_Events_Pae') && 'pa' == $_created ? 'checked="checked"' : false;
		$skip_created = !$_created ? 'checked="checked"' : false;
		
		$created_group_post = class_exists('Eab_BuddyPress_GroupEvents') && $this->_data->get_option('bp-activity_autoupdate-created_group_post') ? 'checked="checked"' : false;
		
		$user_rsvp_yes = $this->_data->get_option('bp-activity_autoupdate-user_rsvp_yes') ? 'checked="checked"' : false;
		$user_rsvp_maybe = $this->_data->get_option('bp-activity_autoupdate-user_rsvp_maybe') ? 'checked="checked"' : false;
		$user_rsvp_no = $this->_data->get_option('bp-activity_autoupdate-user_rsvp_no') ? 'checked="checked"' : false;

		
		$user_rsvp_group_only = class_exists('Eab_BuddyPress_GroupEvents') && $this->_data->get_option('bp-activity_autoupdate-user_rsvp_group_only') ? 'checked="checked"' : false;
		$user_rsvp_group_post = class_exists('Eab_BuddyPress_GroupEvents') && $this->_data->get_option('bp-activity_autoupdate-user_rsvp_group_post') ? 'checked="checked"' : false;
?>
<div id="eab-settings-activity_autoupdate" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Activity auto-update settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item" style="line-height:1.8em">
			<label><?php _e('Automatically update Activity feed when an Event is created:', Eab_EventsHub::TEXT_DOMAIN); ?></label>	
			<span><?php echo $tips->add_tip(__('An activity update posted each time an Event is created.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
			<br />	
			<input type="radio" id="eab_event-bp-activity_autoupdate-event_created" name="eab-bp-activity_autoupdate[event_created]" value="any" <?php print $event_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-event_created"><?php _e('Any event', Eab_EventsHub::TEXT_DOMAIN); ?></label>
		<?php if (class_exists('Eab_BuddyPress_GroupEvents')) { ?>
			<br />	
			<input type="radio" id="eab_event-bp-activity_autoupdate-group_event_created" name="eab-bp-activity_autoupdate[event_created]" value="group" <?php print $group_event_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-group_event_created"><?php _e('Group event', Eab_EventsHub::TEXT_DOMAIN); ?></label>
		<?php } ?>
		<?php if (class_exists('Eab_Events_Pae')) { ?>
			<br />	
			<input type="radio" id="eab_event-bp-activity_autoupdate-pa_event_created" name="eab-bp-activity_autoupdate[event_created]" value="pa" <?php print $pa_event_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-pa_event_created"><?php _e('Public announcement event', Eab_EventsHub::TEXT_DOMAIN); ?></label>
		<?php } ?>
			<br />
			<input type="radio" id="eab_event-bp-activity_autoupdate-skip_created" name="eab-bp-activity_autoupdate[event_created]" value="any" <?php print $skip_created; ?> />
			<label for="eab_event-bp-activity_autoupdate-skip_created"><?php _e('Do not update activity', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<br />	
			<br />	
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-created_group_post" name="eab-bp-activity_autoupdate[created_group_post]" value="1" <?php print $created_group_post; ?> />
			<label for="eab_event-bp-activity_autoupdate-created_group_post"><?php _e('Always update corresponding group feeds on group event creation', Eab_EventsHub::TEXT_DOMAIN); ?></label>
		</div>
		<div class="eab-settings-settings_item" style="line-height:1.8em">
			<label><?php _e('Automatically update Activity feed when user:', Eab_EventsHub::TEXT_DOMAIN); ?></label>				
			<span><?php echo $tips->add_tip(__('An activity update posted each time an user RSVPs.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_yes" name="eab-bp-activity_autoupdate[user_rsvp_yes]" value="1" <?php print $user_rsvp_yes; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_yes"><?php _e('... is coming', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_maybe" name="eab-bp-activity_autoupdate[user_rsvp_maybe]" value="1" <?php print $user_rsvp_maybe; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_maybe"><?php _e('... is maybe coming', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_no" name="eab-bp-activity_autoupdate[user_rsvp_no]" value="1" <?php print $user_rsvp_no; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_no"><?php _e('... is not coming', Eab_EventsHub::TEXT_DOMAIN); ?></label>
		<?php if (class_exists('Eab_BuddyPress_GroupEvents')) { ?>
			<br />
			<br />
			<input type="checkbox" id="eab_event-bp-activity_autoupdate-user_rsvp_group_post" name="eab-bp-activity_autoupdate[user_rsvp_group_post]" value="1" <?php print $user_rsvp_group_post; ?> />
			<label for="eab_event-bp-activity_autoupdate-user_rsvp_group_post"><?php _e('Update group Activity feed', Eab_EventsHub::TEXT_DOMAIN); ?></label>
		<?php } ?>
		</div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['bp-activity_autoupdate-event_created'] = $_POST['eab-bp-activity_autoupdate']['event_created'];
		$options['bp-activity_autoupdate-created_group_post'] = !empty($_POST['eab-bp-activity_autoupdate']['created_group_post']);
		$options['bp-activity_autoupdate-user_rsvp_yes'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_yes']);
		$options['bp-activity_autoupdate-user_rsvp_maybe'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_maybe']);
		$options['bp-activity_autoupdate-user_rsvp_no'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_no']);
		$options['bp-activity_autoupdate-user_rsvp_group_only'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_group_only']);
		$options['bp-activity_autoupdate-user_rsvp_group_post'] = !empty($_POST['eab-bp-activity_autoupdate']['user_rsvp_group_post']);
		return $options;
	}

	private function _is_group_event ($post_id) {
		if (!class_exists('Eab_BuddyPress_GroupEvents')) return false;
		return get_post_meta($post_id, 'eab_event-bp-group_event', true);
	}

	private function _is_public_announcement ($post_id) {
		if (!class_exists('Eab_Events_Pae')) return false;
		return (int)get_post_meta($post_id, 'eab_public_announcement', true);
	}
	
}

Eab_BuddyPress_AutoUpdateActivity::serve();
