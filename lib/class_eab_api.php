<?php

class Eab_Api {

	private $_data;

	public function __construct () {
		$this->_data = Eab_Options::get_instance();
		add_filter('eab-settings-before_save', array($this, 'save_settings'));
	}

	public function initialize () {
		add_action('wp_ajax_nopriv_eab_get_form', array($this, 'handle_get_form'));
		add_action('wp_ajax_eab_get_form', array($this, 'handle_get_form'));
		if ( $this->_data->get_option('accept_api_logins') ) {
			add_action('wp_ajax_nopriv_eab_facebook_login', array($this, 'handle_facebook_login'));

			add_action('wp_ajax_nopriv_eab_get_twitter_auth_url', array($this, 'handle_get_twitter_auth_url'));
			add_action('wp_ajax_nopriv_eab_twitter_login', array($this, 'handle_twitter_login'));

			add_action('wp_ajax_nopriv_eab_get_google_auth_url', array($this, 'handle_get_google_auth_url'));
			add_action('wp_ajax_nopriv_eab_google_login', array($this, 'handle_google_login'));
			add_action('wp_ajax_nopriv_eab_google_plus_login', array($this, 'handle_google_plus_login'));

			add_action('wp_ajax_nopriv_eab_wordpress_login', array($this, 'handle_wordpress_login'));
			add_action('wp_ajax_nopriv_eab_wordpress_register', array($this, 'handle_wordpress_register'));

			// API avatars
			add_filter('get_avatar', array($this, 'get_social_api_avatar'), 10, 3);

			// Google
			if ( !class_exists( 'LightOpenID' ) ) {
				include_once  EAB_PLUGIN_DIR . 'lib/lightopenid/openid.php';
			} 
			$this->openid 			= new LightOpenID;

			$this->openid->identity = 'https://www.google.com/accounts/o8/id';
			$this->openid->required = array('namePerson/first', 'namePerson/last', 'namePerson/friendly', 'contact/email');
			if (!empty($_REQUEST['openid_ns'])) {
				$cache = $this->openid->getAttributes();
				if (isset($cache['namePerson/first']) || isset($cache['namePerson/last']) || isset($cache['contact/email'])) {
					$_SESSION['wdcp_google_user_cache'] = $cache;
				}
			}
			$this->_google_user_cache = isset($_SESSION['wdcp_google_user_cache']) ? $_SESSION['wdcp_google_user_cache'] : false;

		}
	}

	public function enqueue_api_scripts () {
		if (!$this->_data->get_option('accept_api_logins')) return false;
		$domain = get_bloginfo('name');
		$domain = $domain ? $domain : __('WordPress', Eab_EventsHub::TEXT_DOMAIN);

		$show_facebook	= !$this->_data->get_option('api_login-hide-facebook');
		$show_twitter 	= !$this->_data->get_option('api_login-hide-twitter');
		$show_google 	= !$this->_data->get_option('api_login-hide-google');

		$registration_msg = '';
		$registration_services = array();
		if ($show_facebook) $registration_services[] = 'Facebook';
		if ($show_twitter) $registration_services[] = 'Twitter';
		if ($show_google) $registration_services[] = 'Google';

		// Properly enumerate supported service IDs and construct the registration supplement message.
		if (!empty($registration_services)) {
			if (count($registration_services) > 1) {
				$supported_ids = sprintf(
					_x('%s or %s', 'Supported registration services: the first variable can be single service or comma-separated enumeration', Eab_EventsHub::TEXT_DOMAIN),
					join(', ', array_slice(
						$registration_services,
						0,
						count($registration_services)-1
					)),
					end($registration_services)
				);
			} else $supported_ids = end($registration_services);
			$registration_msg = sprintf(_x(' - or just click cancel to register using your %s ID', 'Registration supplemental message part', Eab_EventsHub::TEXT_DOMAIN), $supported_ids);
		}

		wp_enqueue_script('eab_api_js', EAB_PLUGIN_URL . 'js/eab-api.js', array('jquery'), Eab_EventsHub::CURRENT_VERSION);
		wp_localize_script('eab_api_js', 'l10nEabApi', apply_filters('eab-javascript-api_vars', array(
			'facebook' 				=> __('Login with Facebook', Eab_EventsHub::TEXT_DOMAIN),
			'twitter' 				=> __('Login with Twitter', Eab_EventsHub::TEXT_DOMAIN),
			'google' 				=> __('Login with Google', Eab_EventsHub::TEXT_DOMAIN),
			'wordpress' 			=> sprintf(__('Login with %s', Eab_EventsHub::TEXT_DOMAIN), $domain),
			'cancel' 				=> __('Cancel', Eab_EventsHub::TEXT_DOMAIN),
			'please_wait' 			=> __('Please, wait...', Eab_EventsHub::TEXT_DOMAIN),

			'wp_register' 			=> __('Register', Eab_EventsHub::TEXT_DOMAIN),
			'wp_registration_msg' 	=> sprintf(_x('Create a username in order to register for this event %s', 'The variable is registration supplemental part', Eab_EventsHub::TEXT_DOMAIN), $registration_msg),
			'wp_login' 				=> __('Log in', Eab_EventsHub::TEXT_DOMAIN),
			'wp_login_msg' 			=> sprintf(_x('Login with your existing username in order to register for this event %s', 'The variable is registration supplemental part', Eab_EventsHub::TEXT_DOMAIN), $registration_msg),
			'wp_username' 			=> __('Username', Eab_EventsHub::TEXT_DOMAIN),
			'wp_password' 			=> __('Password', Eab_EventsHub::TEXT_DOMAIN),
			'wp_email' 				=> __('Email', Eab_EventsHub::TEXT_DOMAIN),
			'wp_toggle_on' 			=> __('Already a member? Log in here', Eab_EventsHub::TEXT_DOMAIN),
			'wp_toggle_off' 		=> __('Click here to register', Eab_EventsHub::TEXT_DOMAIN),
			'wp_lost_pw_text' 		=> __('Forgot your password', Eab_EventsHub::TEXT_DOMAIN),
			'wp_lost_pw_url' 		=> wp_lostpassword_url(),
			'wp_submit' 			=> __('Submit', Eab_EventsHub::TEXT_DOMAIN),
			'wp_cancel' 			=> __('Cancel', Eab_EventsHub::TEXT_DOMAIN),
			// Vars
			'data' 					=> array(
				'show_facebook' 		=> $show_facebook,
				'show_twitter' 			=> $show_twitter,
				'show_google' 			=> $show_google,
				'show_wordpress' 		=> !$this->_data->get_option('api_login-hide-wordpress'),
				'gg_client_id' 			=> $this->_data->get_option('google-client_id'),
			),
			//validation error for worpress popup
			'wp_missing_username_password' 	=> __( 'Username and password are required!', Eab_EventsHub::TEXT_DOMAIN ),
			'wp_username_pass_invalid' 		=> __( 'Invalid username or password!', Eab_EventsHub::TEXT_DOMAIN ),
			'wp_missing_user_email' 		=> __( 'Username and email are required!', Eab_EventsHub::TEXT_DOMAIN ),
			'wp_signup_error' 				=> __( 'Your email/username is already taken or email is invalid!', Eab_EventsHub::TEXT_DOMAIN ),
		)));
		if (!$this->_data->get_option('facebook-no_init')) {
			if (defined('EAB_INTERNAL_FLAG__FB_INIT_ADDED')) return false;
			add_action('wp_footer', create_function('', "echo '" .
			sprintf(
				'<div id="fb-root"></div><script type="text/javascript">
				window.fbAsyncInit = function() {
					FB.init({
					  appId: "%s",
					  status: true,
					  cookie: true,
					  xfbml: true,
					  version    : "v2.5"
					});
				};
				// Load the FB SDK Asynchronously
				(function(d){
					var js, id = "facebook-jssdk"; if (d.getElementById(id)) {return;}
					js = d.createElement("script"); js.id = id; js.async = true;
					js.src = "//connect.facebook.net/en_US/all.js";
					d.getElementsByTagName("head")[0].appendChild(js);
				}(document));
				</script>',
				$this->_data->get_option('facebook-app_id')
			) .
			"';"));
			define('EAB_INTERNAL_FLAG__FB_INIT_ADDED', true, true);
		}
    }

	function get_social_api_avatar ($avatar, $id_or_email, $size = '96') {
		$wp_uid = false;
		if ( is_object( $id_or_email ) ) {
			if (isset($id_or_email->comment_author_email)) $id_or_email = $id_or_email->comment_author_email;
			else return $avatar;
		}

		if (is_numeric($id_or_email)) {
			$wp_uid = (int)$id_or_email;
		} else if (is_email($id_or_email)) {
			$user = get_user_by('email', $id_or_email);
			if ($user) $wp_uid = $user->ID;
		} else return $avatar;
		if (!$wp_uid) return $avatar;

		$fb = get_user_meta($wp_uid, '_eab_fb', true);
		if ($fb && isset($fb['id'])) {
			return "<img class='avatar avatar-{$size} photo eab-avatar eab-avatar-facebook' width='{$size}' height='{$size}' src='https://graph.facebook.com/" . $fb['id'] . "/picture' />";
		}
		$tw = get_user_meta($wp_uid, '_eab_tw', true);
		if ($tw && isset($tw['avatar'])) {
			return "<img class='avatar avatar-{$size} photo eab-avatar eab-avatar-twitter' width='{$size}' height='{$size}' src='" . $tw['avatar'] . "' />";
		}

		return $avatar;
	}

	/**
	 * Handles Facebook user login and creation
	 */
	function handle_facebook_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		$fb_uid = @$_POST['user_id'];
		$token = @$_POST['token'];
		if (!$token) die(json_encode($resp));

		$result = wp_remote_get( 'https://graph.facebook.com/me?fields=email,name,first_name,last_name&oauth_token=' . $token, array('sslverify' => false) );
		if (200 != $result['response']['code']) die(json_encode($resp)); // Couldn't fetch info

		$data = json_decode($result['body']);
		if (!$data->email) die(json_encode($resp)); // No email, can't go further

		$email = is_email($data->email);
		if (!$email) die(json_encode($resp)); // Wrong email

		$wordp_user = get_user_by('email', $email);

		if (!$wordp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$username = @$data->name
				? preg_replace('/[^_0-9a-z]/i', '_', strtolower($data->name))
				: preg_replace('/[^_0-9a-z]/i', '_', strtolower($data->first_name)) . '_' . preg_replace('/[^_0-9a-z]/i', '_', strtolower($data->last_name))
			;

			$wordp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wordp_user)) die(json_encode($resp)); // Failure creating user
			else {
				update_user_meta($wordp_user, 'first_name', @$data->first_name);
				update_user_meta($wordp_user, 'last_name', @$data->last_name);
			}
		} else {
			$wordp_user = $wordp_user->ID;
		}

		update_user_meta($wordp_user, '_eab_fb', array(
			'id' => $fb_uid,
			'token' => $token,
		));
		do_action('eab-user_logged_in-facebook', $wordp_user, $fb_uid, $token);

		$user = get_userdata($wordp_user);

		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Facebook, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	/**
	 * Spawn a TwitterOAuth object.
	 */
	private function _get_twitter_object ($token=false, $secret=false) {
		if (!class_exists('TwitterOAuth')) include_once EAB_PLUGIN_DIR . 'lib/twitteroauth/twitteroauth.php';
		$twitter = new TwitterOAuth(
			$this->_data->get_option('twitter-app_id'),
			$this->_data->get_option('twitter-app_secret'),
			$token, $secret
		);
		return $twitter;
	}

	/**
	 * Get OAuth request URL and token.
	 */
	function handle_get_twitter_auth_url () {
		header("Content-type: application/json");
		$twitter = $this->_get_twitter_object();

		/* --- Start delta time correction --- */
		if (method_exists('OAuthRequest', 'generate_raw_timestamp')) {
			$test_time = OAuthRequest::generate_raw_timestamp();
			$test_url = "https://api.twitter.com/1/help/test.json";
			$request = wp_remote_get($test_url, array('sslverify' => false));
			$headers = wp_remote_retrieve_headers($request);
			if (!empty($headers['date'])) {
				$twitter_time = strtotime($headers['date']);
				$delta = $twitter_time - $test_time;
				if (abs($delta) > EAB_OAUTH_TIMESTAMP_DELTA_THRESHOLD) {
					add_action('eab-oauth-twitter-generate_timestamp', create_function('$time', 'return $time + ' . $delta . ';'));
				}
			}
		}
		/* --- End delta time correction --- */

		$request_token = $twitter->getRequestToken(@$_POST['url']);
		echo json_encode(array(
			'url' => $twitter->getAuthorizeURL($request_token['oauth_token']),
			'secret' => $request_token['oauth_token_secret'],
		));
		die;
	}

	/**
	 * Login or create a new user using whatever data we get from Twitter.
	 */
	function handle_twitter_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		$secret = @$_POST['secret'];
		$data_str = @$_POST['data'];
		$data_str = ('?' == substr($data_str, 0, 1)) ? substr($data_str, 1) : $data_str;
		$data = array();
		parse_str($data_str, $data);
		if (!$data) die(json_encode($resp));

		$twitter = $this->_get_twitter_object($data['oauth_token'], $secret);

		/* --- Start delta time correction --- */
		if (method_exists('OAuthRequest', 'generate_raw_timestamp')) {
			$test_time = OAuthRequest::generate_raw_timestamp();
			$test_url = "https://api.twitter.com/1/help/test.json";
			$request = wp_remote_get($test_url, array('sslverify' => false));
			$headers = wp_remote_retrieve_headers($request);
			if (!empty($headers['date'])) {
				$twitter_time = strtotime($headers['date']);
				$delta = $twitter_time - $test_time;
				if (abs($delta) > EAB_OAUTH_TIMESTAMP_DELTA_THRESHOLD) {
					add_action('eab-oauth-twitter-generate_timestamp', create_function('$time', 'return $time + ' . $delta . ';'));
				}
			}
		}
		/* --- End delta time correction --- */

		$access = $twitter->getAccessToken($data['oauth_verifier']);

		$twitter = $this->_get_twitter_object($access['oauth_token'], $access['oauth_token_secret']);
		$tw_user = $twitter->get('account/verify_credentials');

		// Have user, now register him/her
		$domain = preg_replace('/www\./', '', parse_url(site_url(), PHP_URL_HOST));
		$username = preg_replace('/[^_0-9a-z]/i', '_', strtolower($tw_user->name));
		$email = $username . '@twitter.' . $domain; //STUB email
		$wordp_user = get_user_by('email', $email);

		if (!$wordp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$count = 0;
			while (username_exists($username)) {
				$username .= rand(0,9);
				if (++$count > 10) break;
			}

			$wordp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wordp_user)) die(json_encode($resp)); // Failure creating user
			else {
				list($first_name, $last_name) = explode(' ', @$tw_user->name, 2);
				update_user_meta($wordp_user, 'first_name', $first_name);
				update_user_meta($wordp_user, 'last_name', $last_name);
			}
		} else {
			$wordp_user = $wordp_user->ID;
		}

		update_user_meta($wordp_user, '_eab_tw', array(
			'id' => $tw_user->id,
			'avatar' => $tw_user->profile_image_url,
			'token' => $access,
		));
		do_action('eab-user_logged_in-twitter', $wordp_user, $tw_user->id, $tw_user->profile_image_url, $access);

		$user = get_userdata($wordp_user);
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Twitter, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	/**
	 * Get OAuth request URL and token.
	 */
	function handle_get_google_auth_url () {
		header("Content-type: application/json");

		$this->openid->returnUrl = $_POST['url'];

		echo json_encode(array(
			'url' => $this->openid->authUrl()
		));
		exit();
	}

	/**
	 * Login or create a new user using whatever data we get from Google.
	 */
	function handle_google_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);

		$cache = $this->openid->getAttributes();

		if (isset($cache['namePerson/first']) || isset($cache['namePerson/last']) || isset($cache['namePerson/friendly']) || isset($cache['contact/email'])) {
			$this->_google_user_cache = $cache;
		}

		// Have user, now register him/her
		if ( !$username = $this->_google_user_cache['namePerson/friendly'] )
			$username = $this->_google_user_cache['namePerson/first'];
		$email = $this->_google_user_cache['contact/email'];
		$wordp_user = get_user_by('email', $email);

		if (!$wordp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$count = 0;
			while (username_exists($username)) {
				$username .= rand(0,9);
				if (++$count > 10) break;
			}

			$wordp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wordp_user))
				die(json_encode($resp)); // Failure creating user
			else {
				update_user_meta($wordp_user, 'first_name', $this->_google_user_cache['namePerson/first']);
				update_user_meta($wordp_user, 'last_name', $this->_google_user_cache['namePerson/last']);
			}
		}
		else {
			$wordp_user = $wordp_user->ID;
		}


		$user = get_userdata($wordp_user);
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Google, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	public function handle_google_plus_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		$client_id = $this->_data->get_option('google-client_id');
		if (empty($client_id)) die(json_encode($resp)); // Yeah, we're not equipped to deal with this

		$data = stripslashes_deep($_POST);
		$token = !empty($data['token']) ? $data['token'] : false;
		if (empty($token)) die(json_encode($resp));

		// Start verifying
		$page = wp_remote_get('https://www.googleapis.com/userinfo/v2/me', array(
			'sslverify' => false,
			'timeout' => 5,
			'headers' => array(
				'Authorization' => sprintf('Bearer %s', $token),
			)
		));
		if (200 != wp_remote_retrieve_response_code($page)) die(json_encode($resp));

		$body = wp_remote_retrieve_body($page);
		$response = json_decode($body, true); // Body is JSON
		if (empty($response['id'])) die(json_encode($resp));

		$first = !empty($response['given_name']) ? $response['given_name'] : '';
		$last = !empty($response['family_name']) ? $response['family_name'] : '';
		$email = !empty($response['email']) ? $response['email'] : '';

		if (empty($email) || (empty($first) && empty($last))) die(json_encode($resp)); // In case we're missing stuff

		$username = false;
		if (!empty($last) && !empty($first)) $username = "{$first}_{$last}";
		else if (!empty($first)) $username = $first;
		else if (!empty($last)) $username = $last;

		if (empty($username)) die(json_encode($resp)); // In case we're missing stuff

		$wordp_user = get_user_by('email', $email);

		if (!$wordp_user) { // Not an existing user, let's create a new one
			$password = wp_generate_password(12, false);
			$count = 0;
			while (username_exists($username)) {
				$username .= rand(0,9);
				if (++$count > 10) break;
			}

			$wordp_user = wp_create_user($username, $password, $email);
			if (is_wp_error($wordp_user))
				die(json_encode($resp)); // Failure creating user
			else {
				update_user_meta($wordp_user, 'first_name', $first);
				update_user_meta($wordp_user, 'last_name', $last);
			}
		}
		else {
			$wordp_user = $wordp_user->ID;
		}

		$user = get_userdata($wordp_user);
		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with Google, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	function handle_wordpress_login () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		$data = stripslashes_deep(@$_POST['data']);
		$login = @$data['username'];
		$pass = @$data['password'];
		if (!user_pass_ok($login, $pass)) die(json_encode($resp));

		$user = get_user_by('login', $login);
		if (is_wp_error($user)) die(json_encode($resp));

		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with WordPress, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	function handle_wordpress_register () {
		header("Content-type: application/json");
		$resp = array(
			"status" => 0,
		);
		$data = stripslashes_deep(@$_POST['data']);
		$login = @$data['username'];
		$email = @$data['email'];

		// Check the username
		if ( empty($login) ) {
			//$errors[] = __('Please enter a username.');
			die(json_encode($resp));
		}
		if ( !validate_username( $login ) ) {
			//$errors[] = __('This username is invalid.  Please enter a valid username.');
			die(json_encode($resp));
		}
		if ( username_exists( $login ) ) {
			//$errors[] = __('This username is already registered, please choose another.');
			die(json_encode($resp));
		}

		// Check the e-mail address
		if (empty($email)) {
			//$errors[] = __('Please type your e-mail address.');
			die(json_encode($resp));
		} else if ( !is_email( $email ) ) {
			//$errors[] = __('The email address appears invalid.');
			//$email = '';
			die(json_encode($resp));
		}
		if ( email_exists( $email ) ) {
			//$errors[] = __('This email is already registered, please choose another.');
			die(json_encode($resp));
		}

		$password = wp_generate_password(12, false);

		$status = apply_filters('eab-user_registration-wordpress-field_validation', true, $data);
		if (!$status) die(json_encode($resp));

		$wordp_user = wp_create_user($login, $password, $email);
		if (is_wp_error($wordp_user)) die(json_encode($resp));

		do_action('eab-user_registered-wordpress', $wordp_user, $data);

		$user = get_userdata($wordp_user);

		//notify
		wp_new_user_notification($user->ID, '', 'all');

		wp_set_current_user($user->ID, $user->user_login);
		wp_set_auth_cookie($user->ID); // Logged in with WordPress, yay
		do_action('wp_login', $user->user_login);

		die(json_encode(array(
			"status" => 1,
		)));
	}

	/**
	 * Responds with RSVP form
	 */
	function handle_get_form () {
		$post_id = (int)@$_POST['post_id'];
		if (!$post_id) die;

		$post = get_post($post_id);
		echo Eab_Template::get_rsvp_form($post);
		die;
	}

	public function save_settings ($options) {
		$options['facebook-app_id'] = $_POST['event_default']['facebook-app_id'];
		$options['facebook-no_init'] = $_POST['event_default']['facebook-no_init'];

		$options['twitter-app_id'] = $_POST['event_default']['twitter-app_id'];
		$options['twitter-app_secret'] = $_POST['event_default']['twitter-app_secret'];

		$options['google-client_id'] = $_POST['event_default']['google-client_id'];

		$options['api_login-hide-facebook'] = !empty($_POST['event_default']['api_login-hide-facebook']);
		$options['api_login-hide-twitter'] = !empty($_POST['event_default']['api_login-hide-twitter']);
		$options['api_login-hide-google'] = !empty($_POST['event_default']['api_login-hide-google']);
		$options['api_login-hide-wordpress'] = !empty($_POST['event_default']['api_login-hide-wordpress']);

		return $options;
	}

	public function render_settings ($tips) {
		?>
	 	<!-- API settings -->
	    <div id="eab-settings-apis" class="eab-metabox postbox">
			<h3 class="eab-hndle"><?php _e('API settings', Eab_EventsHub::TEXT_DOMAIN); ?></h3>
			<div class="eab-inside">
				<div class="eab-settings-settings_item">
				    <label for="incsub_event-facebook-app_id" id="incsub_event_label-facebook-app_id"><?php _e('Facebook App ID', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<input type="text" id="incsub_event-facebook-app_id" name="event_default[facebook-app_id]" value="<?php echo esc_attr($this->_data->get_option('facebook-app_id')); ?>" />
					<span><?php echo $tips->add_tip(sprintf(__('Enter your App ID number here. If you don\'t have a Facebook App yet, you will need to create one <a target="_blank" href="%s">here</a>', Eab_EventsHub::TEXT_DOMAIN), 'https://developers.facebook.com/apps')); ?></span>
				</div>

				<div class="eab-settings-settings_item">
				    <label for="incsub_event-facebook-no_init" id="incsub_event_label-facebook-no_init"><?php _e('My pages already load scripts from Facebook', Eab_EventsHub::TEXT_DOMAIN); ?></label>
				    <input type="hidden" name="event_default[facebook-no_init]" value="" />
					<input type="checkbox" id="incsub_event-facebook-no_init" name="event_default[facebook-no_init]" <?php print ($this->_data->get_option('facebook-no_init') ? "checked='checked'" : ''); ?> value="1" />
					<span><?php echo $tips->add_tip(__('Check this box if you\'re already using Facebook scripts on your WordPress site. (If you\'re not sure what this means, leave the box unchecked).', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
				</div>

				<div class="eab-settings-settings_item">
				    <label for="incsub_event-twitter-app_id" id="incsub_event_label-twitter-app_id"><?php _e('Twitter Consumer Key', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<input type="text" id="incsub_event-twitter-app_id" name="event_default[twitter-app_id]" value="<?php echo esc_attr($this->_data->get_option('twitter-app_id')); ?>" />
					<span><?php echo $tips->add_tip(sprintf(__('Enter your Twitter App ID number here. If you don\'t have a Twitter App yet, you will need to create one <a target="_blank" href="%s">here</a>.<br />When setting up your app, please remember to also set the <b>Callback URL</b> to appropriate value (<code>%s</code>)', Eab_EventsHub::TEXT_DOMAIN), 'https://dev.twitter.com/apps/new', home_url())); ?></span>
				</div>

				<div class="eab-settings-settings_item">
				    <label for="incsub_event-twitter-app_secret" id="incsub_event_label-twitter-app_secret"><?php _e('Twitter Consumer Secret', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<input type="password" id="incsub_event-twitter-app_secret" name="event_default[twitter-app_secret]" value="<?php echo esc_attr($this->_data->get_option('twitter-app_secret')); ?>" />
					<span><?php echo $tips->add_tip(__('Enter your Twitter App secret here.', Eab_EventsHub::TEXT_DOMAIN)); ?></span>
				</div>

				<div class="eab-settings-settings_item">
				    <label for="incsub_event-google-client_id" id="incsub_event_label-google-client_id"><?php _e('Google Client ID', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<input type="text" id="incsub_event-google-client_id" name="event_default[google-client_id]" value="<?php echo esc_attr($this->_data->get_option('google-client_id')); ?>" />
					<span><?php echo $tips->add_tip(sprintf(__('Enter your Google App Client ID here. If you don\'t have a Google App yet, you will need to create one <a target="_blank" href="%s">here</a>', Eab_EventsHub::TEXT_DOMAIN), 'https://console.developers.google.com/')); ?></span>
					<span>
						<small><?php _e('If you leave this field empty, Google Auth will revert to legacy OpenID.', Eab_EventsHub::TEXT_DOMAIN); ?></small>
					</span>
				</div>

				<div class="eab-settings-settings_item">
					<label><?php _e('Hide login buttons', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<br />
					<input type="checkbox" name="event_default[api_login-hide-facebook]" id="eab-api_login-hide-facebook" value="1" <?php echo ($this->_data->get_option('api_login-hide-facebook') ? 'checked="checked"' : '') ?> />
					<label for="eab-api_login-hide-facebook"><?php _e('Hide Facebook login button', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<br />
					<input type="checkbox" name="event_default[api_login-hide-twitter]" id="eab-api_login-hide-twitter" value="1" <?php echo ($this->_data->get_option('api_login-hide-twitter') ? 'checked="checked"' : '') ?> />
					<label for="eab-api_login-hide-twitter"><?php _e('Hide Twitter login button', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<br />
					<input type="checkbox" name="event_default[api_login-hide-google]" id="eab-api_login-hide-google" value="1" <?php echo ($this->_data->get_option('api_login-hide-google') ? 'checked="checked"' : '') ?> />
					<label for="eab-api_login-hide-google"><?php _e('Hide Google login button', Eab_EventsHub::TEXT_DOMAIN); ?></label>
					<br />
					<input type="checkbox" name="event_default[api_login-hide-wordpress]" id="eab-api_login-hide-wordpress" value="1" <?php echo ($this->_data->get_option('api_login-hide-wordpress') ? 'checked="checked"' : '') ?> />
					<label for="eab-api_login-hide-wordpress"><?php _e('Hide WordPress login button', Eab_EventsHub::TEXT_DOMAIN); ?></label>

				</div>
			</div>
		<?php if (!$this->_data->get_option('accept_api_logins')) { ?>
                    <div style="padding: 0 12px;">
			<p><em><?php _e('To configure and accept API logins, please check the box for "Allow Facebook and Twitter Login?" in plugin settings.', Eab_EventsHub::TEXT_DOMAIN); ?></em></p>
                    </div>
		<?php } ?>
	    </div>
	    <?php
	    do_action('eab-settings-after_api_settings');
	    return false;
	}
}
