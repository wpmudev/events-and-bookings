<?php
/*
Plugin Name: Alert RSVPs on event modification
Description: Send an email to all RSVPs when an event is modified
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Events
*/


class Eab_Events_Alert_RSVP_Event_Modify {
	
	private function __construct () {
		$this->_data = Eab_Options::get_instance();
	}
	
	public static function serve () {
		$me = new Eab_Events_Alert_RSVP_Event_Modify;
		$me->_add_hooks();
	}
	
	private function _add_hooks () {
		add_action( 'save_post', array( $this, 'notify_rsvp_on_update_event' ), 99, 2 );
		add_action( 'eab-settings-after_appearance_settings', array( $this, 'show_settings' ) );
		add_filter( 'eab-settings-before_save', array( $this, 'save_settings' ) );
	}
	
	public function notify_rsvp_on_update_event( $post_id, $post ) {
		
		if (!current_user_can('edit_posts')) return false;
		if ( wp_is_post_revision( $post_id ) ) return;
		
		if( 'incsub_event' == get_post_type( $post_id ) ) {
			
			global $wpdb;
			
			$event = ($post instanceof Eab_EventModel) ? $post : new Eab_EventModel($post);
		
			$all_events = array($event);
			if ($event->is_recurring()) $all_events = Eab_CollectionFactory::get_all_recurring_children_events($event);
			$all_event_ids = array();
			foreach ($all_events as $e) { $all_event_ids[] = $e->get_id(); }
			$all_event_ids = array_filter(array_map('intval', $all_event_ids));
			
			$bookings = $wpdb->get_results("SELECT id,user_id,event_id FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id IN(" . join(',', $all_event_ids) . ") ORDER BY timestamp;");
			
			if (!count($bookings)) return false;
			
			$eab_alert = $this->_data->get_option( 'eab_alert' );
			
			
			$start_dates = $event->get_start_dates();
			foreach ($start_dates as $key => $date) {
				$start = $event->get_start_timestamp($key);
				$end = $event->get_end_timestamp($key);
			}
			
			foreach ($bookings as $booking) {
				$user_data = get_userdata($booking->user_id);
				
				$subject = str_replace(
						array( 'DISPLAY_NAME', 'EVENT_NAME', 'START_DATE', 'START_TIME', 'END_DATE', 'END_TIME' ),
						array( $user_data->display_name, $event->get_title(), date('Y-m-d', $start), date('H:i', $start), date('Y-m-d', $end), date('H:i', $end) ),
						$eab_alert['subject']
					);
				$content = str_replace(
						array( 'DISPLAY_NAME', 'EVENT_NAME', 'START_DATE', 'START_TIME', 'END_DATE', 'END_TIME' ),
						array( $user_data->display_name, $event->get_title(), date('Y-m-d', $start), date('H:i', $start), date('Y-m-d', $end), date('H:i', $end) ),
						$eab_alert['content']
					);
				
				add_filter( 'wp_mail_content_type', array( $this, 'set_content_type' ) );
				wp_mail( $user_data->user_email, $subject, nl2br( $content ) );
				remove_filter( 'wp_mail_content_type', array( $this, 'set_content_type' ) );
			}
		}
		
	}
	
	
	public function set_content_type( $content_type ) {
		return 'text/html';
	}
	
	
	public function show_settings() {
		$eab_alert = $this->_data->get_option( 'eab_alert' );
		?>
		<div id="eab-settings-alert" class="eab-metabox postbox">
			<h3 class="eab-hndle"><?php _e( 'Alert RSVP Settings', Eab_EventsHub::TEXT_DOMAIN ); ?></h3>
			<div class="eab-inside">
				<p><?php _e( 'Subject', Eab_EventsHub::TEXT_DOMAIN ) ?></p>
				<input type="text" name="eab_alert[subject]" style="width: 100%" value="<?php echo $eab_alert['subject'] ?>">
				<p><?php _e( 'Email Content', Eab_EventsHub::TEXT_DOMAIN ) ?></p>
				<textarea name="eab_alert[content]" rows="10" style="width: 100%"><?php echo $eab_alert['content'] ?></textarea>
				<em><?php _e( 'You can use these macros: DISPLAY_NAME, EVENT_NAME, START_DATE, START_TIME, END_DATE, END_TIME', Eab_EventsHub::TEXT_DOMAIN ) ?></em>
			</div>
		</div>
		<?php
	}
	
	
	public function save_settings( $options ) {
		if( ! empty( $_POST['eab_alert'] ) ) $options['eab_alert'] = $_POST['eab_alert'];
		return $options;
	}
	
	
	
}

Eab_Events_Alert_RSVP_Event_Modify::serve();
