<?php
/*
Plugin Name: Email: send reminder email to non-paid visitors
Description: Allows you to send reminder email who wanted to attend in the event but didn't pay yet.
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Email, RSVP
*/

class Eab_Events_RsvpEmailNonPaid {

	private $_data;

	function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Events_RsvpEmailNonPaid;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_rsvp_email_non_paid-preview_email', array($this, 'ajax_preview_email'));

		add_action('wp_ajax_send_email_non_paid_members', array($this, 'send_email_non_paid_members_callback'));

		add_filter('eab-metabox-bookings-has_bookings', array($this, 'add_action_button'), 10, 2);
	}

	function add_action_button ($content, $event) {
		if (!$event->is_premium()) return $content;
		$content .= '<button type="button" class="eab_event-eab_rsvps-non_paid-send_email" data-event_id="' . (int)$event->get_id() . '">' .
			__('Send reminder e-mail to all non-paid visitors', Eab_EventsHub::TEXT_DOMAIN) .
		'</button><div class="eab_event-eab_rsvps-non_paid-result"></div>';
		$error = esc_js(__('Something went wrong', Eab_EventsHub::TEXT_DOMAIN));
		$success = esc_js(__('Number of messages sent:', Eab_EventsHub::TEXT_DOMAIN));
		$content .=<<<EOJS
<script>
(function ($) {

$(function () {
	$(".eab_event-eab_rsvps-non_paid-send_email").click(function () {
		var me = $(this),
			event_id = me.attr("data-event_id"),
			result = me.parent().find(".eab_event-eab_rsvps-non_paid-result")
		;
		$.post(ajaxurl, {
			action: "send_email_non_paid_members",
			event_id: event_id
		}, function (data) {
			if (data && 0 === data.status) {
				result.html('<div class="updated below-h2"><p>{$success} ' + data.sent + '</p></div>');
			} else {
				result.html('<div class="error below-h2"><p>{$error}</p></div>');
			}
		}, 'json');
	});
});
})(jQuery);
</script>
EOJS;
		return $content;
	}

function send_email_non_paid_members_callback() {
	    global $wpdb;

	    $data = stripslashes_deep($_POST);
	    $event_id = !empty($data['event_id']) && (int)$data['event_id'] ? (int)$data['event_id'] : false;
	    $status = 'yes'; //!empty($data['status']) ? $data['status'] : false;
	    $response = array(
	    	'status' => 1,
	    	'sent' => 0,
	    );
	    if (!$event_id || !$status) die(json_encode($response));

	    $event = new Eab_EventModel(get_post($event_id));
	    if (!$event->is_premium()) die(json_encode($response)); // Not a paid event

	    $all_events = array($event);
		if ($event->is_recurring()) $all_events = Eab_CollectionFactory::get_all_recurring_children_events($event);
		$all_event_ids = array();
		foreach ($all_events as $e) { $all_event_ids[] = $e->get_id(); }
		$all_event_ids = array_filter(array_map('intval', $all_event_ids));

		$bookings = $wpdb->get_results($wpdb->prepare("SELECT id,user_id,event_id FROM ".Eab_EventsHub::tablename(Eab_EventsHub::BOOKING_TABLE)." WHERE event_id IN(" . join(',', $all_event_ids) . ") AND status = %s ORDER BY timestamp;", $status));
		if (!count($bookings)) die(json_encode($response));

		foreach ( $bookings as $booking ) {
			if ( $event->user_paid( $booking->user_id ) ) {
				continue;
			}
			$this->_send_notification_email( $event->get_id(), $booking->user_id );
			$response['sent']+=1;
		}
		$response['status'] = 0;
		die(json_encode($response));
	}

	private function _send_notification_email ($event_id, $user_id) {
		$user 			= get_user_by('id', $user_id);
		if ( $user && !is_wp_error( $user ) ) {
			$from 			= $this->_data->get_option('eab_rsvps-email_non_paid-from');
			$subject 		= $this->_data->get_option('eab_rsvps-email_non_paid-subject');
			$body 			= $this->_data->get_option('eab_rsvps-email_non_paid-body');
			$admin_email 	= get_option('admin_email');

			$from 			= $from ? $from : $admin_email;

			if ( empty( $subject ) || empty( $body ) ) {
				return false;
			}
			$headers = array(
				'Content-Type: ' . $this->email_charset() . '; charset="' . get_option('blog_charset') . '"',
				'From: ' . $from,
			);

			$codec = new Eab_Macro_Codec( $event_id, $user_id );
			add_filter( 'wp_mail_content_type', array( $this, 'email_charset' ) );
			
			wp_mail(
				$user->user_email,
				$codec->expand( $subject, Eab_Macro_Codec::FILTER_TITLE ),
				$codec->expand( $body, Eab_Macro_Codec::FILTER_BODY ),
				$headers
			);
			remove_filter( 'wp_mail_content_type', array( $this, 'email_charset' ) );
		}
	}

	function email_charset () { return 'text/html'; }

	function ajax_preview_email () {
		$data = stripslashes_deep($_POST);
		$event_id = !empty($data['event_id']) ? $data['event_id'] : false;
		if (!$event_id) die;
		$user = wp_get_current_user();
		$codec = new Eab_Macro_Codec($event_id, $user->ID);
		die(
			'<strong>' . $codec->expand($data['subject'], Eab_Macro_Codec::FILTER_TITLE) . '</strong>' .
			'<div>' . $codec->expand($data['body'], Eab_Macro_Codec::FILTER_BODY) . '</div>'
		);
	}

	function save_settings ($options) {
		$data = stripslashes_deep($_POST);
		$options['eab_rsvps-email_non_paid-from'] = @$data['eab_rsvps_non_paid']['email-from'];
		$options['eab_rsvps-email_non_paid-subject'] = @$data['eab_rsvps_non_paid']['email-subject'];
		$options['eab_rsvps-email_non_paid-body'] = @$data['eab_rsvps-email_non_paid-body'];
		return $options;
	}

	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		
		$from = $this->_data->get_option('eab_rsvps-email_non_paid-from');
		$subject = $this->_data->get_option('eab_rsvps-email_non_paid-subject');
		$body = $this->_data->get_option('eab_rsvps-email_non_paid-body');
		
		$codec = new Eab_Macro_Codec;
		$macros = join('</code>, <code>', $codec->get_macros());

		$events = Eab_CollectionFactory::get_upcoming_events(eab_current_time(), array('posts_per_page' => 10));
		?>
<div id="eab-settings-eab_rsvps_non_paid" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('Reminder Email settings for non paid members', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		 <div class="eab-settings-settings_item">
			<label for="eab_event-eab_rsvps-non_paid-from" id="eab_event-eab_rsvps-non_paid-from"><?php _e('Email from:', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<input type="text" size="20" id="eab_event-eab_rsvps-non_paid-from" name="eab_rsvps_non_paid[email-from]" value="<?php echo esc_attr($from); ?>" />
			<span><?php echo $tips->add_tip(__('This is the From address for the reminder emails for non paid members', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-non_paid-subject"><?php _e('Email subject', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('This is your email subject. You can use these macros: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros)); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-non_paid-subject" name="eab_rsvps_non_paid[email-subject]" value="<?php echo esc_attr($subject); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-non_paid-body"><?php _e('Email body', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('This is your email body. You can use these macros: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros)); ?></span>
			<?php wp_editor($body, 'eab_rsvps-email_non_paid-body', array(
				'name' => 'eab_rsvps-email_non_paid-body',
			)); ?>
	    </div>
	    <div class="eab-settings-settings_item"><small><?php printf(__('You can use these macros in your subject and body: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros) ?></small></div>
	<?php if ($events) { ?>
	    <div class="eab-settings-settings_item">
	    	<input type="button" class="button" id="eab_event-eab_rsvps-non_paid-preview" value="<?php esc_attr_e(__('Preview', Eab_EventsHub::TEXT_DOMAIN)); ?>" />
	    	<?php _e('using this event data:', Eab_EventsHub::TEXT_DOMAIN); ?>
	    	<select id="eab_event-eab_rsvps-non_paid-events">
	    	<?php foreach ($events as $event) { ?>
	    		<option value="<?php echo esc_attr($event->get_id()); ?>"><?php echo $event->get_title(); ?></option>
	    	<?php } ?>
	    	</select>
	    	<div id="eab_event-eab_rsvp_non_paid-email_preview_container" style="line-height: 1.2em"></div>
	    </div>
	<?php } ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {
$(function () {
	var $container = $("#eab_event-eab_rsvp_non_paid-email_preview_container"),
		$subject = $("#eab_event-eab_rsvps-non_paid-subject"),
		$events = $("#eab_event-eab_rsvps-non_paid-events")
	;
	$("#eab_event-eab_rsvps-non_paid-preview").on("click", function () {
		var body_string = (tinyMCE && tinyMCE.activeEditor 
			? tinyMCE.activeEditor.getContent()
			: $("eab_rsvps-email_non_paid-body").val()
		);
		$container.html('<?php echo esc_js(__("Please, hold on... ", Eab_EventsHub::TEXT_DOMAIN)); ?>');
		$.post(ajaxurl, {
			"action": "eab_rsvp_email_non_paid-preview_email",
			"subject": $subject.val(),
			"body": body_string,
			"event_id": $events.val()
		}, function (data) {
			$container.html(data);
		}, 'html');
	});
})
})(jQuery);
</script>
		<?php
	}
}
Eab_Events_RsvpEmailNonPaid::serve();