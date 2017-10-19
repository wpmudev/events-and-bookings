<?php
/*
Plugin Name: RSVP status auto-reset
Description: Automatically resets RSVP status on your paid events after a preconfigured time if the user hasn't paid yet.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Events, RSVP
*/

class Eab_Rsvps_RsvpAutoReset {

	const SCHEDULE_KEY = '_eab-rsvps-rsvp_auto_reset-run_lock';

	private $_data;

	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Rsvps_RsvpAutoReset;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('eab_scheduled_jobs', array($this, 'check_schedule'));
	}

	public function check_schedule () {
		$last_run = (int)get_option(self::SCHEDULE_KEY);
		$run_each = $this->_data->get_option('rsvp_auto_reset-run_each');
		$run_each = $run_each ? $run_each : 3600;
		$next_run = $last_run + $run_each;
		if ($next_run < eab_current_time()) {
			$this->_reset_expired_bookings($last_run);
			update_option(self::SCHEDULE_KEY, eab_current_time());
		}
	}

	private function _reset_expired_bookings ($since) {
		//$rsvps = Eab_EventModel::get_bookings(Eab_EventModel::BOOKING_YES, $since);
		$rsvps = Eab_EventModel::get_bookings(Eab_EventModel::BOOKING_YES); // Just reset all the expired bookings.
		$now = eab_current_time();
		
		$cutoff_limit = $this->_data->get_option('rsvp_auto_reset-cutoff');
		$cutoff_limit = $cutoff_limit ? $cutoff_limit : 3600;
		
		$callback = (int)$this->_data->get_option('rsvp_auto_reset-remove_attendance')
			? 'delete_attendance'
			: 'cancel_attendance'
		;
		$events = array(); // Events cache
		foreach ($rsvps as $rsvp) {
			// Check time difference
			$time_diff = $now - strtotime($rsvp->timestamp);
			if ($time_diff < $cutoff_limit) continue; // This one still has time to pay
			
			// Check event premium status
			if (empty($events[$rsvp->event_id])) $events[$rsvp->event_id] = new Eab_EventModel(get_post($rsvp->event_id));
			if (!$events[$rsvp->event_id]->is_premium()) continue; // Not a paid event, carry on

			// Check user payment
			if ($events[$rsvp->event_id]->user_paid($rsvp->user_id)) continue; // User paid for event, we're good here.

			// If we got here, we should reset the users RSVP
			if (is_callable(array($events[$rsvp->event_id], $callback))) $events[$rsvp->event_id]->$callback($rsvp->user_id);
		}
	}

	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		$runs = array(
			'3600' => __('Hour', Eab_EventsHub::TEXT_DOMAIN),
			'7200' => __('Two hours', Eab_EventsHub::TEXT_DOMAIN),
			'10800' => __('Three hours', Eab_EventsHub::TEXT_DOMAIN),
			'21600' => __('Six hours', Eab_EventsHub::TEXT_DOMAIN),
			'43200' => __('Twelve hours', Eab_EventsHub::TEXT_DOMAIN),
			'86400' => __('Day', Eab_EventsHub::TEXT_DOMAIN),
		);
		$runs = apply_filters( 'eab_rsvp_scheduled_rsvp_reset_cron_times', $runs );
		$run_each = $this->_data->get_option('rsvp_auto_reset-run_each');
		$run_each = $run_each ? $run_each : 3600;

		$cutoff = $this->_data->get_option('rsvp_auto_reset-cutoff');
		$cutoff = $cutoff ? $cutoff : 3600;

		$remove_attendance = $this->_data->get_option('rsvp_auto_reset-remove_attendance')
			? 'checked="checked"'
			: ''
		;
?>
<div id="eab-settings-rsvp_status_auto_reset" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('RSVP status auto-reset settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label><?php _e('Schedule checks to run every:', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<select name="eab_rsvps[rsvp_auto_reset-run_each]">
			<?php foreach ($runs as $sinterval => $slabel) { ?>
				<option value="<?php echo (int)$sinterval; ?>" <?php echo selected($sinterval, $run_each); ?>><?php echo $slabel; ?></option>
			<?php } ?>
			</select>
			<span><?php echo $tips->add_tip(__('Schedule runs to execute this often', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label><?php _e('Auto-reset unpaid RSVPs older then:', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<select name="eab_rsvps[rsvp_auto_reset-cutoff]">
			<?php foreach ($runs as $cinterval => $clabel) { ?>
				<option value="<?php echo (int)$cinterval; ?>" <?php echo selected($cinterval, $cutoff); ?>><?php echo $clabel; ?></option>
			<?php } ?>
			</select>
			<span><?php echo $tips->add_tip(__('Unpaid positive RSVPs cutoff time', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-rsvp_auto_reset-remove_attendance"><?php _e('Remove attendance entirely', Eab_EventsHub::TEXT_DOMAIN); ?>?</label>
			<input type="checkbox" id="eab_event-eab_rsvps-rsvp_auto_reset-remove_attendance" name="eab_rsvps[rsvp_auto_reset-remove_attendance]" value="1" <?php print $remove_attendance; ?> />
			<span><?php echo $tips->add_tip(__('By default, the plugin will reset the user attendance to "no". Select this option if you wish to remove their attendance records entirely instead.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
	</div>
</div>
<?php
	}

	function save_settings ($options) {
		$options['rsvp_auto_reset-run_each'] 			= isset( $_POST['eab_rsvps']['rsvp_auto_reset-run_each'] ) ? $_POST['eab_rsvps']['rsvp_auto_reset-run_each'] : false;
		$options['rsvp_auto_reset-cutoff'] 				= isset( $_POST['eab_rsvps']['rsvp_auto_reset-cutoff'] ) ? $_POST['eab_rsvps']['rsvp_auto_reset-cutoff'] : '';
		$options['rsvp_auto_reset-remove_attendance'] 	= isset( $_POST['eab_rsvps']['rsvp_auto_reset-remove_attendance'] ) ? $_POST['eab_rsvps']['rsvp_auto_reset-remove_attendance'] : '';
		return $options;
	}

}
Eab_Rsvps_RsvpAutoReset::serve();