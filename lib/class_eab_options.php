<?php

class Eab_Options {
	
	const OPTIONS_KEY = 'incsub_event_default';
	
	private static $_instance;
	private $_data = array();

	private function __clone () {}
	public function __construct () {
		$this->_populate();
	}
	
	public static function get_instance () {
		if (!isset(self::$_instance)) self::$_instance = new Eab_Options;
		return self::$_instance;
	}
	
	/**
	 * @return array
	 */
	public function get_options () {
		return $this->_data;
	}

	public function get_default_options() {
		return array(
			'currency' => 'USD',
			'slug' => 'events',
			'accept_payments' => 1,
			'accept_api_logins' => 0,
			'display_attendees' => 1,
			'paypal_email' => '',
			'paypal_sandbox' => 0,
			'override_appearance_defaults' => 0,
			'archive_template' => '',
			'single_template' => ''
		);
	}
	
	/**
	 * @param string $name Option name
	 * @param mixed $default Optional default return value
	 * @return mixed Option value, or $default
	 */
	public function get_option ($name, $default=false) {
		return isset($this->_data[$name]) ? $this->_data[$name] : $default;
	}
	
	public function set_option ($name, $value) {
		$this->_data[$name] = $value;
	}
	
	/**
	 * Sets and stores options.
	 * @param array $values A hash of values to be stored.
	 */
	public function set_options ($values) {
		if (!$values) return false;
		foreach ($values as $name => $value) {
			$this->set_option($name, $value);
		}
		$this->update();
	}

	public function update () {
		return update_option(self::OPTIONS_KEY, $this->_data);
	}
	
	private function _populate () {
		$this->_data = get_option(self::OPTIONS_KEY, $this->get_default_options() );
	}
}
