<?php
/*
Plugin Name: Email: send notification on RSVP
Description: Automatically send a notification to yourself and/or event author when an user RSVPs
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Email, RSVP
*/


class Eab_Events_RsvpEmailMe_Codec extends Eab_Macro_Codec {

	public function __construct ($event_id=false, $user_id=false) {
		parent::__construct($event_id, $user_id);
		$this->_macros[] = 'HAS_PAID';
		$this->_macros = apply_filters('eab-events-rsvp_email_me-codec-macros', $this->_macros);
	}

	public function expand ($str, $filter=false) {
		return apply_filters('eab-events-rsvp_email_me-codec-expand', parent::expand($str, $filter), $this->_event);
	}

	public function replace_has_paid () {
		if (!$this->_event->is_premium()) return '';

		$has_paid = apply_filters('eab-events-rsvp_email_me-codec-message-has_paid', __('The user paid for the event.', Eab_EventsHub::TEXT_DOMAIN));
		$not_paid = apply_filters('eab-events-rsvp_email_me-codec-message-not_paid', __('The user did not pay for the event.', Eab_EventsHub::TEXT_DOMAIN));

		return $this->_event->user_paid($this->_user->ID)
			? $has_paid
			: $not_paid
		;
	}

}

class Eab_Events_RsvpEmailMe {

	private $_data;

	function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Events_RsvpEmailMe;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_rsvp_email_me-preview_email', array($this, 'ajax_preview_email'));

		if ($this->_data->get_option('eab_rsvps-email_me-positive_rsvp')) {
			add_action('incsub_event_booking_yes', array($this, 'dispatch_positive_rsvp_update'), 10, 2);
		}
		if ($this->_data->get_option('eab_rsvps-email_me-paid_rsvp')) {
			add_action('eab-ipn-event_paid', array($this, 'dispatch_paid_rsvp_update'), 10, 3);
		}
	}

	function dispatch_positive_rsvp_update ($event_id, $user_id) {
		$this->_send_notification_email($event_id, $user_id);
	}

	function dispatch_paid_rsvp_update ($event_id, $amount, $booking_id) {
		$booking = Eab_EventModel::get_booking($booking_id);
		if (!is_object($booking) || empty($booking->event_id) || empty($booking->user_id) || $booking->event_id != $event_id) return false;

		$this->_send_notification_email($event_id, $booking->user_id);
	}

	private function _send_notification_email ($event_id, $user_id) {
		$user = get_user_by('id', $user_id);
		$subject = $this->_data->get_option('eab_rsvps-email_me-subject');
		$body = $this->_data->get_option('eab_rsvps-email_me-body');
		$admin_email = get_option('admin_email');
                
        $from = $this->_data->get_option('eab_rsvps-email_me-from');
		$from = $from ? $from : get_option('admin_email');
		
		$from_name = $this->_data->get_option('eab_rsvps-email_me-from-name');
		$from_name = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );

		if (empty($subject) || empty($body)) return false;
		$headers = array(
			'From: ' . $from_name . ' <' . $from . '>',
            'From: ' . $from,
			'Content-Type: ' . $this->email_charset() . '; charset="' . get_option('blog_charset') . '"'
		);

		$codec = new Eab_Events_RsvpEmailMe_Codec($event_id, $user_id);
		add_filter('wp_mail_content_type', array($this, 'email_charset'));
		
		if ($this->_data->get_option('eab_rsvps-email_me-notify_admin')) {
			wp_mail(
				$admin_email,
				$codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE),
				$codec->expand($body, Eab_Macro_Codec::FILTER_BODY),
				$headers
			);
		}
		if ($this->_data->get_option('eab_rsvps-email_me-notify_author')) {
			$event = new Eab_EventModel(get_post($event_id));
			$author_id = $event->get_author();
			if ($author_id) {
				$author = get_user_by('id', $author_id);
				if ($author->user_email != $admin_email) {
					wp_mail(
						$author->user_email,
						$codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE),
						$codec->expand($body, Eab_Macro_Codec::FILTER_BODY),
						$headers
					);
				}
			}
		}
		remove_filter('wp_mail_content_type', array($this, 'email_charset'));
	}

	function email_charset () { return 'text/html'; }

	function ajax_preview_email () {
		$data = stripslashes_deep($_POST);
		$event_id = !empty($data['event_id']) ? $data['event_id'] : false;
		if (!$event_id) die;
		$user = wp_get_current_user();
		$codec = new Eab_Events_RsvpEmailMe_Codec($event_id, $user->ID);
		$body = !empty($data['body'])
			? $data['body']
			: $this->_data->get_option('eab_rsvps-email_me-body')
		;
		die(
			'<strong>' . $codec->expand($data['subject'], Eab_Macro_Codec::FILTER_TITLE) . '</strong>' .
			'<div>' . $codec->expand($body, Eab_Macro_Codec::FILTER_BODY) . '</div>'
		);
	}

	function save_settings ($options) {
		$data = stripslashes_deep($_POST);
		$options['eab_rsvps-email_me-positive_rsvp'] = @$data['eab_rsvps_me']['email-positive_rsvp'];
		$options['eab_rsvps-email_me-paid_rsvp'] = @$data['eab_rsvps_me']['email-paid_rsvp'];
		$options['eab_rsvps-email_me-notify_admin'] = @$data['eab_rsvps_me']['email-notify_admin'];
		$options['eab_rsvps-email_me-notify_author'] = @$data['eab_rsvps_me']['email-notify_author'];
		$options['eab_rsvps-email_me-subject'] = @$data['eab_rsvps_me']['email-subject'];
        $options['eab_rsvps-email_me-from'] = @$data['eab_rsvps_me']['email-from'];
		$options['eab_rsvps-email_me-from-name'] = @$data['eab_rsvps_me']['email-from-name'];
		$options['eab_rsvps-email_me-body'] = @$data['eab_rsvps-email_me-body'];
		return $options;
	}

	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		
		$positive_rsvp = $this->_data->get_option('eab_rsvps-email_me-positive_rsvp') ? 'checked="checked"' : '';
		$paid_rsvp = $this->_data->get_option('eab_rsvps-email_me-paid_rsvp') ? 'checked="checked"' : '';
		$notify_admin = $this->_data->get_option('eab_rsvps-email_me-notify_admin') ? 'checked="checked"' : '';
		$notify_author = $this->_data->get_option('eab_rsvps-email_me-notify_author') ? 'checked="checked"' : '';
		$subject = $this->_data->get_option('eab_rsvps-email_me-subject');
        $from = $this->_data->get_option('eab_rsvps-email_me-from');
		$from_name = $this->_data->get_option('eab_rsvps-email_me-from-name');
		$body = $this->_data->get_option('eab_rsvps-email_me-body');
		
		$codec = new Eab_Events_RsvpEmailMe_Codec;
		$macros = join('</code>, <code>', $codec->get_macros());

		$events = Eab_CollectionFactory::get_upcoming_events(eab_current_time(), array('posts_per_page' => 10));
		?>
<div id="eab-settings-eab_rsvps_me" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('RSVP Notification Email settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label><?php _e('Send an update', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<br />
			<label for="eab_event-eab_rsvps-me-positive_rsvp" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-positive_rsvp]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-positive_rsvp" name="eab_rsvps_me[email-positive_rsvp]" value="1" <?php echo $positive_rsvp; ?> />
				<?php _e('On all positive RSVPs', Eab_EventsHub::TEXT_DOMAIN); ?>
			</label>
			<label for="eab_event-eab_rsvps-me-paid_rsvp" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-paid_rsvp]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-paid_rsvp" name="eab_rsvps_me[email-paid_rsvp]" value="1" <?php echo $paid_rsvp; ?> />
				<?php _e('When user pays for a paid event', Eab_EventsHub::TEXT_DOMAIN); ?>
			</label>
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label><?php _e('Notify', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<br />
			<label for="eab_event-eab_rsvps-me-notify_admin" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-notify_admin]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-notify_admin" name="eab_rsvps_me[email-notify_admin]" value="1" <?php echo $notify_admin; ?> />
				<?php _e('Site administrator', Eab_EventsHub::TEXT_DOMAIN); ?>
			</label>
			<label for="eab_event-eab_rsvps-me-notify_author" style="display:block; line-height:1.8em">
				<input type="hidden" name="eab_rsvps_me[email-notify_author]" value="" />
				<input type="checkbox" id="eab_event-eab_rsvps-me-notify_author" name="eab_rsvps_me[email-notify_author]" value="1" <?php echo $notify_author; ?> />
				<?php _e('Event author', Eab_EventsHub::TEXT_DOMAIN); ?>
			</label>
	    </div>
        <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-from-name"><?php _e('Email from name', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip('This is your email from name'); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-me-from-name" name="eab_rsvps_me[email-from-name]" value="<?php esc_attr_e($from_name); ?>" />
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-from"><?php _e('Email from', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip('This is your email from address'); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-me-from" name="eab_rsvps_me[email-from]" value="<?php esc_attr_e($from); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-subject"><?php _e('Email subject', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('This is your email subject. You can use these macros: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros)); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-me-subject" name="eab_rsvps_me[email-subject]" value="<?php esc_attr_e($subject); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-me-body"><?php _e('Email body', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('This is your email body. You can use these macros: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros)); ?></span>
			<?php wp_editor($body, 'eab_rsvps-email_me-body', array(
				'name' => 'eab_rsvps_me-email_me-body',
			)); ?>
	    </div>
	    <div class="eab-settings-settings_item"><small><?php printf(__('You can use these macros in your subject and body: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros) ?></small></div>
	<?php if ($events) { ?>
	    <div class="eab-settings-settings_item">
	    	<input type="button" class="button" id="eab_event-eab_rsvps-me-preview" value="<?php esc_attr_e(__('Preview', Eab_EventsHub::TEXT_DOMAIN)); ?>" />
	    	<?php _e('using this event data:', Eab_EventsHub::TEXT_DOMAIN); ?>
	    	<select id="eab_event-eab_rsvps-me-events">
	    	<?php foreach ($events as $event) { ?>
	    		<option value="<?php esc_attr_e($event->get_id()); ?>"><?php echo $event->get_title(); ?></option>
	    	<?php } ?>
	    	</select>
	    	<div id="eab_event-eab_rsvp_me-email_preview_container" style="line-height: 1.2em"></div>
	    </div>
	<?php } ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {
$(function () {
	var $container = $("#eab_event-eab_rsvp_me-email_preview_container"),
		$subject = $("#eab_event-eab_rsvps-me-subject"),
		$events = $("#eab_event-eab_rsvps-me-events")
	;
	$("#eab_event-eab_rsvps-me-preview").on("click", function () {
		var body_string = (tinyMCE && tinyMCE.activeEditor 
			? tinyMCE.activeEditor.getContent()
			: $("#eab_rsvps_me-email_me-body").val()
		);
		$container.html('<?php echo esc_js(__("Please, hold on... ", Eab_EventsHub::TEXT_DOMAIN)); ?>');
		$.post(ajaxurl, {
			"action": "eab_rsvp_email_me-preview_email",
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
Eab_Events_RsvpEmailMe::serve();
