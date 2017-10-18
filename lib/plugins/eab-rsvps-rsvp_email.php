<?php
/*
Plugin Name: Email: send email on RSVP
Description: Automatically send your user an email on event RSVP
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Email, RSVP
*/


class Eab_Events_RsvpEmail_Codec extends Eab_Macro_Codec {

	public function __construct ($event_id=false, $user_id=false) {
		parent::__construct($event_id, $user_id);
		$this->_macros = apply_filters('eab-events-rsvp_email-codec-macros', $this->_macros);
	}

	public function expand ($str, $filter=false) {
		return apply_filters('eab-events-rsvp_email-codec-expand', parent::expand($str, $filter), $this->_event);
	}

}

class Eab_Events_RsvpEmail {

	private $_data;

	function __construct () {
		$this->_data = Eab_Options::get_instance();
	}

	public static function serve () {
		$me = new Eab_Events_RsvpEmail;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-settings-after_plugin_settings', array($this, 'show_settings'));
		add_filter('eab-settings-before_save', array($this, 'save_settings'));

		add_action('wp_ajax_eab_rsvp_email-preview_email', array($this, 'ajax_preview_email'));

		add_action('incsub_event_booking_yes', array($this, 'dispatch_positive_rsvp_update'), 10, 2);
	}

	function dispatch_positive_rsvp_update ($event_id, $user_id) {
		$user = get_user_by('id', $user_id);
		$from = $this->_data->get_option('eab_rsvps-email-from');
		$from = $from ? $from : get_option('admin_email');
		$subject = $this->_data->get_option('eab_rsvps-email-subject');
		$body = $this->_data->get_option('eab_rsvps-email-body');
		
		$from_name = $this->_data->get_option('eab_rsvps-email-from-name');
		$from_name = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );

		if (!is_email($user->user_email) || empty($from) || empty($subject) || empty($body)) return false;
		$headers = array(
			'From: ' . $from_name . ' <' . $from . '>',
			'Content-Type: ' . $this->email_charset() . '; charset="' . get_option('blog_charset') . '"'
		);

		$codec = new Eab_Events_RsvpEmail_Codec($event_id, $user_id);
		add_filter('wp_mail_content_type', array($this, 'email_charset'));
		wp_mail(
			$user->user_email, 
			$codec->expand($subject, Eab_Macro_Codec::FILTER_TITLE),
			$codec->expand($body, Eab_Macro_Codec::FILTER_BODY),
			$headers
		);
		remove_filter('wp_mail_content_type', array($this, 'email_charset'));
	}

	function email_charset () { return 'text/html'; }

	function ajax_preview_email () {
		$data = stripslashes_deep($_POST);
		$event_id = !empty($data['event_id']) ? $data['event_id'] : false;
		if (!$event_id) die;
		$user = wp_get_current_user();
		$codec = new Eab_Events_RsvpEmail_Codec($event_id, $user->ID);
		die(
			'<strong>' . $codec->expand($data['subject'], Eab_Macro_Codec::FILTER_TITLE) . '</strong>' .
			'<div>' . $codec->expand($data['body'], Eab_Macro_Codec::FILTER_BODY) . '</div>'
		);
	}

	function save_settings ($options) {
		$data = stripslashes_deep($_POST);
		$options['eab_rsvps-email-from'] = @$data['eab_rsvps']['email-from'];
		$options['eab_rsvps-email-from-name'] = @$data['eab_rsvps']['email-from-name'];
		$options['eab_rsvps-email-subject'] = @$data['eab_rsvps']['email-subject'];
		$options['eab_rsvps-email-body'] = @$data['eab_rsvps-email-body'];
		return $options;
	}

	function show_settings () {
		$tips = new WpmuDev_HelpTooltips();
		$tips->set_icon_url(EAB_PLUGIN_URL . 'img/information.png' );
		
		$from = $this->_data->get_option('eab_rsvps-email-from');
		$from = $from ? $from : get_option('admin_email');
		$from_name = $this->_data->get_option('eab_rsvps-email-from-name');
		$from_name = ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' );
		$subject = $this->_data->get_option('eab_rsvps-email-subject');
		$body = $this->_data->get_option('eab_rsvps-email-body');
		
		$codec = new Eab_Events_RsvpEmail_Codec;
		$macros = join('</code>, <code>', $codec->get_macros());

		$events = Eab_CollectionFactory::get_upcoming_events(eab_current_time(), array('posts_per_page' => 10));
		?>
<div id="eab-settings-eab_rsvps_email" class="eab-metabox postbox">
	<h3 class="eab-hndle"><?php _e('RSVP Email settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
	<div class="eab-inside">
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-from-name"><?php _e('From email name', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(__('This is the name the RSVP email will be sent from', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
			<input type="text" id="eab_event-eab_rsvps-from-name" name="eab_rsvps[email-from-name]" value="<?php esc_attr_e($from_name); ?>" />
	    </div>
		<div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-from"><?php _e('From email address', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(__('This is the address the RSVP email will be sent from', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
			<input type="text" id="eab_event-eab_rsvps-from" name="eab_rsvps[email-from]" value="<?php esc_attr_e($from); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-subject"><?php _e('Email subject', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('This is your email subject. You can use these macros: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros)); ?></span>
			<input type="text" class="widefat" id="eab_event-eab_rsvps-subject" name="eab_rsvps[email-subject]" value="<?php esc_attr_e($subject); ?>" />
	    </div>
	    <div class="eab-settings-settings_item">
	    	<label for="eab_event-eab_rsvps-body"><?php _e('Email body', Eab_EventsHub::TEXT_DOMAIN); ?></label>
			<span><?php echo $tips->add_tip(sprintf(__('This is your email body. You can use these macros: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros)); ?></span>
			<?php wp_editor($body, 'eab_rsvps-email-body', array(
				'name' => 'eab_rsvps-email-body',
			)); ?>
	    </div>
	    <div class="eab-settings-settings_item"><small><?php printf(__('You can use these macros in your subject and body: <code>%s</code>', Eab_EventsHub::TEXT_DOMAIN), $macros) ?></small></div>
	<?php if ($events) { ?>
	    <div class="eab-settings-settings_item">
	    	<input type="button" class="button" id="eab_event-eab_rsvps-preview" value="<?php esc_attr_e(__('Preview', Eab_EventsHub::TEXT_DOMAIN)); ?>" />
	    	<?php _e('using this event data:', Eab_EventsHub::TEXT_DOMAIN); ?>
	    	<select id="eab_event-eab_rsvps-events">
	    	<?php foreach ($events as $event) { ?>
	    		<option value="<?php esc_attr_e($event->get_id()); ?>"><?php echo $event->get_title(); ?></option>
	    	<?php } ?>
	    	</select>
	    	<div id="eab_event-eab_rsvp-email_preview_container" style="line-height: 1.2em"></div>
	    </div>
	<?php } ?>
	</div>
</div>
<script type="text/javascript">
(function ($) {
$(function () {
	var $container = $("#eab_event-eab_rsvp-email_preview_container"),
		$subject = $("#eab_event-eab_rsvps-subject"),
		$events = $("#eab_event-eab_rsvps-events")
	;
	$("#eab_event-eab_rsvps-preview").on("click", function () {
		var body_string = (tinyMCE && tinyMCE.activeEditor 
			? tinyMCE.activeEditor.getContent()
			: $("#eab_rsvps-email-body").val()
		);
		$container.html('<?php echo esc_js(__("Please, hold on... ", Eab_EventsHub::TEXT_DOMAIN)); ?>');
		$.post(ajaxurl, {
			"action": "eab_rsvp_email-preview_email",
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
Eab_Events_RsvpEmail::serve();
