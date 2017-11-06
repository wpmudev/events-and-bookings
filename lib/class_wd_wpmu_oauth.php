<?php

abstract class WpmuDev_Wp_Oauth {

	const SCOPE_LOGIN = 'login';
	const SCOPE_VERIFICATION = 'verification';
	const SCOPE_REFRESH = 'refresh';

	/**
	 * AJAX handler base URL.
	 * @var string
	 */
	protected $_ajax_url;

	/**
	 * HTTP headers used for remote request, including request TYPE.
	 * The UA string will be set in the constructor.
	 * @var array
	 */
	protected $_http_headers = array (
		'method' => 'GET',
		'timeout' => '5',
		'redirection' => '5',
		'blocking' => true,
		'compress' => false,
		'decompress' => true,
		'sslverify' => false,
	);

/* ----- Implementation-specific ----- */

	/**
	 * oAuth login endpoint handler.
	 * Should be overriden in the implementing class.
	 * @var string
	 */
	protected $_oauth_login_endpoint;

	/**
	 * oAuth verification endpoint handler.
	 * Should be overriden in the implementing class.
	 * @var string
	 */
	protected $_oauth_verification_endpoint;

	/**
	 * These are login request parameters. 
	 * Should be overriden in the implementing class.
	 * @var array
	 */
	protected $_login_parameters = array();

	/**
	 * These are verification request parameters. 
	 * Should be overriden in the implementing class.
	 * @var array
	 */
	protected $_verification_parameters = array();

	/**
	 * These are token refresh request parameters. 
	 * Should be overriden in the implementing class.
	 * @var array
	 */
	protected $_refresh_parameters = array();

	/**
	 * WP AJAX handler action.
	 * Should be overriden in the implementing class.
	 * @var string
	 */
	protected $_action = 'wpmudev_wp_oauth';


/* ----- Implementation-specific Interface ----- */

	/**
	 * Initialize login parameters and perform other implementation-dependent bootstrap.
	 * Run right after self::__construct();
	 */
	abstract public function initialize_parameters ();
	
	/**
	 * Implementation-specific token retrieval interface.
	 */
	abstract public function is_authenticated ();	

	/**
	 * Implementation-specific authentication interface.
	 */
	abstract public function get_authentication ();
	
	/**
	 * Implementation-specific oAuth login response processor.
	 */
	abstract public function process_oauth_login_response ();

	/**
	 * Implementation-specific raw token data fetcher
	 * @return mixed Raw token data
	 */
	abstract public function get_token_data ();

	/**
	 * Implementation-specific token fetcher.
	 * @return string Token
	 */
	abstract public function get_token ();

	/**
	 * Implementation-specific token reset procedure.
	 */
	abstract public function reset_token ();


/* ----- Shared + Helpers ----- */
	
	/**
	 * Instantiates oAuth handler and sets UA string.
	 */
	public function __construct () {
		$this->_http_headers['user-agent'] = get_class($this);
		$this->_ajax_url = admin_url('admin-ajax.php');
		add_action("wp_ajax_{$this->_action}", array($this, 'process_oauth_login_response'));
		$this->initialize_parameters();
	}

	/**
	 * Sets login parameter.
	 * @param string $key   Login parameter array key
	 * @param string $value Login parameter array value
	 */
	public function set_parameter ($key, $value, $scope=false) {
		if (self::SCOPE_VERIFICATION == $scope) $this->_verification_parameters[$key] = $value;
		else if (self::SCOPE_LOGIN == $scope) $this->_login_parameters[$key] = $value;
		else if (self::SCOPE_REFRESH == $scope) $this->_refresh_parameters[$key] = $value;
	}

	/**
	 * Gets a login parameter value
	 * @param  string  $key     Login parameter array key
	 * @param  mixed $default Default value if no such key is set, defaults to (bool)false
	 * @return mixed           Login parameter array value, or $default if key not set
	 */
	public function get_parameter ($key, $default=false, $scope=false) {
		$parameters = self::SCOPE_VERIFICATION == $scope 
			? $this->_verification_parameters 
			: (self::SCOPE_LOGIN == $scope ? $this->_login_parameters : $this->_refresh_parameters)
		;
		return isset($parameters[$key])
			? $parameters[$key]
			: $default
		;
	}

	public function set_header ($key, $value) {
		$this->_http_headers[$key] = $value;
	}

	public function get_action () {
		return $this->_action;
	}

	/**
	 * oAuth login response endpoint URL.
	 * Defaults to AJAX handler
	 * @return string AJAX response enpoint URL.
	 */
	public function get_login_response_endpoint () {
		return sprintf(
			'%s?action=%s',
			$this->_ajax_url,
			urlencode($this->_action)
		);
	}

	protected function _get_parameter_query ($scope=false) {
		$parameters = self::SCOPE_VERIFICATION == $scope 
			? $this->_verification_parameters 
			: (self::SCOPE_LOGIN == $scope ? $this->_login_parameters : $this->_refresh_parameters)
		;
		return http_build_query($parameters, null, '&');
	}

	protected function _verify_authentication_code () {
		$args = $this->_http_headers;
		$args['body'] = $this->_verification_parameters;
		$response = wp_remote_request($this->_oauth_verification_endpoint, $args);

		return $this->_extract_body($response);
	}

	protected function _refresh_authentication_code () {
		$args = $this->_http_headers;
		$args['body'] = $this->_refresh_parameters;
		$response = wp_remote_request($this->_oauth_verification_endpoint, $args);
		return $this->_extract_body($response);
	}

	protected function _extract_body ($page) {
		if(is_wp_error($page)) return false; // Request fail
		if (wp_remote_retrieve_response_code($page) != 200) return false; // Request fail
		return wp_remote_retrieve_body($page);
	}
}


/**
 * Stored data oAuth abstraction.
 */
abstract class WpmuDev_Wp_StoredOauth extends WpmuDev_Wp_Oauth {

	abstract public function get_data ($key);

	abstract public function set_data ($key, $value);

	abstract public function get_data_key ($key);

	protected function _get_data_key ($key) {
		$class = get_class($this);
		return apply_filters(strtolower(__CLASS__) . '-get_data_storage_key', 
			apply_filters(strtolower($class) . "-get_data_storage_key", $key, $this),
			$this
		);
	}
}


/**
 * Plugin-wide gCalendar oAuth implementation.
 */
abstract class Eab_Gcal_Plugin_Oauth extends WpmuDev_Wp_StoredOauth {

	/**
	 * https://developers.google.com/accounts/docs/OAuth2Login
	 * @var array
	 */
	protected $_login_parameters = array(
		'response_type' => 'code',
		//'scope' => 'https://www.googleapis.com/auth/calendar.readonly', --> set this in the implementation
		'access_type' => 'offline',
		'client_id' => '',
		'redirect_uri' => '',
		'state' => '',
	);
	/**
	 * https://developers.google.com/accounts/docs/OAuth2WebServer
	 * @var array
	 */
	protected $_verification_parameters = array(
		'grant_type' => 'authorization_code',
		'client_id' => '',
		'client_secret' => '',
		'redirect_uri' => '',
		'code' => '',
	);
	/**
	 * https://developers.google.com/accounts/docs/OAuth2WebServer#refresh
	 * @var array
	 */
	protected $_refresh_parameters = array(
		'grant_type' => 'refresh_token',
		'client_id' => '',
		'client_secret' => '',
		'refresh_token' => '',
	);
	protected $_oauth_login_endpoint = 'https://accounts.google.com/o/oauth2/auth';
	protected $_oauth_verification_endpoint = 'https://accounts.google.com/o/oauth2/token';
	protected $_action = 'eab_gcal_oauth';

	private $_data;

	private $_client_id;
	private $_client_secret;

	public function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_client_id = $this->get_data('client_id');
		$this->_client_secret = $this->get_data('client_secret');

		parent::__construct();
	}

	public function get_data ($key) {
		$key = $this->get_data_key($key);
		return $this->_data->get_option($key, false);
	}

	public function set_data ($key, $value) {
		$key = $this->get_data_key($key);
		$this->_data->set_option($key, $value);
		return $this->_data->update();
	}

	public function initialize_parameters () {
		$this->set_parameter('redirect_uri', $this->get_login_response_endpoint(), self::SCOPE_LOGIN);
		$this->set_parameter('client_id', $this->_client_id, self::SCOPE_LOGIN);
		$this->set_parameter('state', __CLASS__, self::SCOPE_LOGIN);
		
		$this->set_parameter('redirect_uri', $this->get_login_response_endpoint(), self::SCOPE_VERIFICATION);
		$this->set_parameter('client_id', $this->_client_id, self::SCOPE_VERIFICATION);
		$this->set_parameter('client_secret', $this->_client_secret, self::SCOPE_VERIFICATION);

		$this->set_parameter('client_id', $this->_client_id, self::SCOPE_REFRESH);
		$this->set_parameter('client_secret', $this->_client_secret, self::SCOPE_REFRESH);
	}

	public function is_authenticated () {
		$token_data 	= $this->get_token_data();

		$token 			= isset( $token_data['access_token'] ) ? $token_data['access_token'] : false;
		$expires 		= isset( $token_data['expires_in'] ) ? (int)$token_data['expires_in'] : 0;
		$request_time 	= isset( $token_data['time'] ) ? (int)$token_data['time'] : 0;
		if ( $request_time > 0 ) {
			if ( time() > $expires + $request_time ) {
				$refresh_token = isset( $token_data['refresh_token'] ) ? $token_data['refresh_token'] : false;
				if ( !$refresh_token ) {
					return false;
				}
				$token_data = $this->_refresh_token( $refresh_token );
				$token 		= isset( $token_data['access_token'] ) ? $token_data['access_token'] : false;
			}
		}
		return $token;
	}

	public function get_authentication () {
		$url = $this->_oauth_login_endpoint . '?' . $this->_get_parameter_query(self::SCOPE_LOGIN);
		return $url;
	}

	public function get_token_data () {
		$token_data = $this->get_data('token_data');
		return (!$token_data || !is_array($token_data)) 
			? array()
			: $token_data
		;
	}

	public function get_token () {
		$token_data = $this->get_token_data();
		return empty($token_data['access_token'])
			? ''
			: $token_data['access_token']
		;
	}

	public function reset_token () {
		$this->set_data('token_data', '');
	}

	public function process_oauth_login_response () {
		$state 	= isset( $_GET['state'] ) ? $_GET['state'] : false;
		$code 	= isset( $_GET['code'] ) ? $_GET['code'] : false;

		if (!$state || !$code) die;

		// Verify state...
		// ...
		
		// Verify code...
		// ...
		
		$this->set_parameter('code', $code, self::SCOPE_VERIFICATION);
		$this->set_header('method', 'POST');
		$raw_token = $this->_verify_authentication_code();
		if (!$raw_token) die;

		$token_data = json_decode($raw_token, true);
		if (!$token_data) die;

		$token_data['time'] = time();

		$this->set_data('token_data', $token_data);
		
		die;
	}

	private function _refresh_token ($token) {
		$this->set_parameter('refresh_token', $token, self::SCOPE_REFRESH);
		$this->set_header('method', 'POST');

		$raw_token = $this->_refresh_authentication_code();
		if (!$raw_token) die;

		$token_data = json_decode($raw_token, true);
		if (!$token_data) die;

		$token_data['time'] = time();
		if (!isset($token_data['refresh_token'])) $token_data['refresh_token'] = $token;

		$this->set_data('token_data', $token_data);

		return $token_data;
	}
}


abstract class Eab_Gcal_Plugin_Oauth_RO extends Eab_Gcal_Plugin_Oauth {
	protected $_login_parameters = array(
		'response_type' => 'code',
		'scope' => 'https://www.googleapis.com/auth/calendar.readonly',
		'access_type' => 'offline',
		'client_id' => '',
		'redirect_uri' => '',
		'state' => '',
	);
}

abstract class Eab_Gcal_Plugin_Oauth_RW extends Eab_Gcal_Plugin_Oauth {
	protected $_login_parameters = array(
		'response_type' => 'code',
		'scope' => 'https://www.googleapis.com/auth/calendar',
		'access_type' => 'offline',
		'client_id' => '',
		'redirect_uri' => '',
		'state' => '',
	);
}


/**
 * Plugin-wide FB oAuth implementation.
 */
abstract class Eab_FB_Plugin_Oauth extends WpmuDev_Wp_StoredOauth {

	/**
	 * http://developers.facebook.com/docs/howtos/login/client-side-without-js-sdk/
	 * @var array
	 */
	protected $_login_parameters = array(
		'scope' => 'user_events',
		'response_type' => 'token',
		'client_id' => '',
		'redirect_uri' => '',
	);
	/**
	 * https://developers.facebook.com/docs/howtos/login/login-as-app/
	 * @var array
	 */
	protected $_verification_parameters = array(
		'code' => '',
		'redirect_uri' => '',
		'client_id' => '',
		'client_secret' => '',
	);
	// No refresh parameters for App access - however, we need User access token.
	protected $_refresh_parameters = array(
		'grant_type' => 'fb_exchange_token',
		'client_id' => '',
		'client_secret' => '',
		'fb_exchange_token' => '',
	);

	protected $_oauth_login_endpoint = 'https://www.facebook.com/dialog/oauth';
	protected $_oauth_verification_endpoint = 'https://graph.facebook.com/oauth/access_token';
	protected $_action = 'eab_fbe_oauth';

	private $_data;

	private $_client_id;
	private $_client_secret;

	public function __construct () {
		$this->_data = Eab_Options::get_instance();
		$this->_client_id = $this->get_data('client_id');
		$this->_client_secret = $this->get_data('client_secret');

		parent::__construct();
	}

	public function get_data ($key) {
		$key = $this->get_data_key($key);
		return $this->_data->get_option($key, false);
	}

	public function set_data ($key, $value) {
		$key = $this->get_data_key($key);
		$this->_data->set_option($key, $value);
		return $this->_data->update();
	}

	public function initialize_parameters () {
		$this->set_parameter('redirect_uri', $this->get_login_response_endpoint(), self::SCOPE_LOGIN);
		$this->set_parameter('client_id', $this->_client_id, self::SCOPE_LOGIN);
		$this->set_parameter('state', __CLASS__, self::SCOPE_LOGIN);
		
		$this->set_parameter('redirect_uri', $this->get_login_response_endpoint(), self::SCOPE_VERIFICATION);
		$this->set_parameter('client_id', $this->_client_id, self::SCOPE_VERIFICATION);
		$this->set_parameter('client_secret', $this->_client_secret, self::SCOPE_VERIFICATION);

		$this->set_parameter('client_id', $this->_client_id, self::SCOPE_REFRESH);
		$this->set_parameter('client_secret', $this->_client_secret, self::SCOPE_REFRESH);
	}

	public function is_authenticated () {
		$token_data = $this->get_token_data();

		return isset( $token_data['access_token'] ) ? $token_data['access_token'] : false;
	}

	public function get_authentication () {
		$url = $this->_oauth_login_endpoint . '?' . $this->_get_parameter_query(self::SCOPE_LOGIN);
		return $url;
	}

	public function get_token_data () {
		$token_data = $this->get_data('token_data');
		return (!$token_data || !is_array($token_data)) 
			? array()
			: $token_data
		;
	}

	public function get_token () {
		$token_data = $this->get_token_data();
		return isset($token_data['access_token'])
			? ''
			: $token_data['access_token']
		;
	}

	public function reset_token () {
		$this->set_data('token_data', '');
	}

	public function process_oauth_login_response () {
		$code = isset($_GET['code']) ? $_GET['code'] : false;
		if (!$code) die;
		
		// Verify code...
		// ...
		
		$this->set_parameter('code', $code, self::SCOPE_VERIFICATION);
		$this->set_header('method', 'POST');
		$raw_token = $this->_verify_authentication_code();
		if (!$raw_token) die;

		//parse_str($raw_token, $token_data);
		$token_data = json_decode($raw_token, true);
		if (empty($token_data['access_token'])) die;

		$short_token = $token_data['access_token'];

		$this->set_parameter('fb_exchange_token', $short_token, self::SCOPE_REFRESH);
		$raw_token = $this->_refresh_authentication_code();
		if (!$raw_token) die;
		//parse_str($raw_token, $token_data);
		$token_data = json_decode($raw_token, true);

		$token_data['time'] = time();

		$this->set_data('token_data', $token_data);
		
		die;
	}

	public function get_fb_user () {
		$token = $this->get_token();
		if (!$token) return false;
		$response = wp_remote_get('https://graph.facebook.com/me?access_token=' . $token, $this->_http_headers);
		$body = $this->_extract_body($response);
		return $body
			? json_decode($body, true)
			: false
		;
	}
}


abstract class Eab_FB_Plugin_Oauth_RO extends Eab_FB_Plugin_Oauth {

	/**
	 * https://developers.facebook.com/docs/howtos/login/server-side-login/
	 * @var array
	 */
	protected $_login_parameters = array(
		'client_id' => '',
		'scope' => 'user_events',
		'redirect_uri' => '',
		'state' => '',
	);
}

abstract class Eab_FB_Plugin_Oauth_RW extends Eab_FB_Plugin_Oauth {

	/**
	 * https://developers.facebook.com/docs/howtos/login/server-side-login/
	 * @var array
	 */
	protected $_login_parameters = array(
		'client_id' => '',
		'scope' => 'user_events,create_event',
		'redirect_uri' => '',
		'state' => '',
	);
}



/* ----- gCalendar helper class ----- */


/**
 * Abstract gCalendar helper class.
 */
abstract class WpmuDev_Gcal_Helper {

	/**
	 * Google oAuth token.
	 * @var string
	 */
	protected $_token;

	/**
	 * HTTP headers used for remote request, including request TYPE.
	 * The UA string will be set in the constructor.
	 * @var array
	 */
	protected $_http_headers = array (
		'method' => 'GET',
		'timeout' => '5',
		'redirection' => '5',
		'blocking' => true,
		'compress' => false,
		'decompress' => true,
		'sslverify' => false,
	);

	private $_endpoint = 'https://www.googleapis.com/calendar/v3';


/* ----- Interface ----- */

	/**
	 * Implementation-specific bootstrap
	 */
	abstract public function initialize ();


/* ----- Shared + Helpers ----- */

	public function __construct () {
		$this->_http_headers['user-agent'] = get_class($this);
		$this->initialize();
	}

	public function get_calendars () {
		$args = array_merge($this->_http_headers, array(
			'method' => 'GET',
		));
		$raw_data = $this->_request('/users/me/calendarList', $args);
		return $this->_get_items($raw_data);
	}

	public function get_calendar_events ($calendar_id, $limit=false) {
		$args = array_merge($this->_http_headers, array(
			'method' => 'GET',
		));
		$limit = $limit && (int)$limit ? "maxResults={$limit}" : '';
		$raw_data = $this->_request("/calendars/{$calendar_id}/events?{$limit}", $args);
		return $this->_get_items($raw_data);	
	}



	protected function _get_items ($json) {
		return isset($json['items'])
			? $json['items']
			: array()
		;
	}

	private function _request ($relative, $args) {
		if (!$this->_token) return false;
		$query_separator = preg_match('/\?/', $relative) ? '&' : '?';
		$url = untrailingslashit($this->_endpoint) 
			. '/' . trim($relative, '/') .
			$query_separator . http_build_query(array('access_token' => $this->_token), null, '&')
		;
		$response = wp_remote_request($url, $args);
		return $this->_extract_body_data($response);
	}

	private function _extract_body_data ($page) {
		if(is_wp_error($page)) return false; // Request fail
		if (wp_remote_retrieve_response_code($page) != 200) return false; // Request fail
		$body = wp_remote_retrieve_body($page);
		if (!$body || is_wp_error($body)) return false;
		return json_decode($body, true);
	}
}

