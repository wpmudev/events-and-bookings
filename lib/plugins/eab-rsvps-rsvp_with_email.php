<?php
/*
Plugin Name: RSVP with email address
Description: Allows your users to RSVP to events just with their e-mail address
Plugin URI: http://premium.wpmudev.org/project/events-and-booking
Version: 1.0
Author: WPMU DEV
AddonType: Email, RSVP
*/

class Eab_Events_RsvpWithEmail {

	private function __construct () {}

	public static function serve () {
		$me = new self;
		$me->_add_hooks();
	}

	private function _add_hooks () {
		add_action('eab-javascript-do_enqueue_api_scripts', array($this, 'enqueue_api_scripts'));
		add_action('eab-javascript-enqueue_scripts', array($this, 'enqueue_api_scripts'));

		add_action('wp_ajax_nopriv_eab-rsvps-rsvp_with_email', array($this, 'json_create_user'));
	}

	public function json_create_user () {
		$error = array(
			"status" => 0,
			"msg" => __('There has been an error processing your request. Please, reload the page and try again.', Eab_EventsHub::TEXT_DOMAIN)
		);
		$data = stripslashes_deep($_POST);
		$email = $data['email'];

		if (empty($email)) {
			$error['msg'] = __('Please, submit an email.', Eab_EventsHub::TEXT_DOMAIN);
			die(json_encode($error));
		}
		if (!is_email($email)) {
			$error['msg'] = __('Please, submit a valid email.', Eab_EventsHub::TEXT_DOMAIN);
			die(json_encode($error));
		}
        if (email_exists($email)){
        	$current_location = get_permalink();
        	if (!empty($data['location'])) {
        		// Let's make this sane first - it's coming from a POST request, so make that sane
        		$loc = wp_validate_redirect(wp_sanitize_redirect($data['location']));
        		if (!empty($loc)) $current_location = $loc;
        	}
            $login_link = wp_login_url( $current_location );
            $login_message = sprintf( __( 'The email address already exists. Please <a href="%s">Login</a> and RSVP to the event.', Eab_EventsHub::TEXT_DOMAIN ), $login_link );
            $error['msg'] = $login_message;
            die(json_encode($error));
        }

		$status = apply_filters( 'eab-user_registration-wordpress-field_validation', true, $data, true );
		if ( !$status ) {
		   $error['msg'] =  __('A required field is missing.', Eab_EventsHub::TEXT_DOMAIN);
		   die(json_encode($error));
		}

		$wordp_user = $this->_create_user($email);

		if (is_object($wordp_user) && !empty($wordp_user->ID)) $this->_login_user($wordp_user);
		else die(json_encode($error));

		do_action('eab-user_registered-wordpress', $wordp_user->ID, $data, true);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	private function _create_user ($email) {
		list($username, $domain) = explode('@', $email, 2);
		$username = sanitize_user(trim($username));
		while (username_exists($username)) {
			$username .= rand(0,9);
		}

		$password = wp_generate_password(12, false);
		$user_id = wp_create_user($username, $password, $email);

		if (empty($user_id) || is_wp_error($user_id)) return false;

// Notification email??
		wp_new_user_notification($user_id, $password);

		return get_userdata($user_id);
		
	}

	private function _login_user ($user) {
		if (empty($user->ID) || empty($user->user_login)) return false;
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with email, yay
		do_action('wp_login', $user->user_login);
	}

	public function enqueue_api_scripts () {
		if (is_user_logged_in()) return false;
		wp_enqueue_script('eab-rsvp_with_email', EAB_PLUGIN_URL . "js/eab-rsvp_with_email.js", array('jquery'), Eab_EventsHub::CURRENT_VERSION);
		wp_localize_script('eab-rsvp_with_email', 'l10nRsvpWithEmail', array(
			'email' => __('Email', Eab_EventsHub::TEXT_DOMAIN),
		));
	}
}
Eab_Events_RsvpWithEmail::serve();