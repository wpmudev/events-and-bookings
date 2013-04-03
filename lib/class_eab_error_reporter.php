<?php

//if (!defined('EAB_CORE_ERROR_LOGGER')) define('EAB_CORE_ERROR_LOGGER', true);

class Eab_ErrorRepository {
	private $_errors;

	private static $_instance;

	private function __construct () {
		$this->_errors = array();
		add_action('plugins_loaded', array($this, 'initialize'));
	}

	public static function get_instance () {
		if (!self::$_instance) {
			self::$_instance = new Eab_ErrorRepository;
		}
		return self::$_instance;
	}

	public function initialize () {
		if (!session_id()) session_start();
		if (!empty($_SESSION['eab_error_log'])) $this->_errors = $_SESSION['eab_error_log'];
	}

	public function log ($error) {
		$key = microtime(true) . '-' . rand();
		$this->_errors[$key] = $error;
		if (isset($_SESSION)) $_SESSION['eab_error_log'] = $this->_errors;
	}

	public function get_all () {
		return $this->_errors;
	}

	public function purge () {
		$this->_errors = array();
		if (isset($_SESSION)) $_SESSION['eab_error_log'] = $this->_errors;
	}
}

abstract class Eab_ErrorReporter {

	private static $_loggers = array();

	public static function serve () {
		$loggers = defined('EAB_CORE_ERROR_LOGGER') && EAB_CORE_ERROR_LOGGER
			? explode(',', EAB_CORE_ERROR_LOGGER)
			: false
		;
		if (!$loggers) return false;
		$loggers = preg_match('/[a-z]/', EAB_CORE_ERROR_LOGGER)
			? array_map('trim', $loggers)
			: array('admin')
		;
		foreach ($loggers as $logger) {
			$class = 'Eab_ErrorLogger_' . ucfirst(strtolower($logger));
			if (!class_exists($class)) continue;
			self::$_loggers[] = new $class;
		}
		add_action('eab-debug-log_error', array('Eab_ErrorReporter', 'log'));
	}

	public static function log ($error) {
		if (empty(self::$_loggers)) return false;
		foreach (self::$_loggers as $logger) {
			if (!is_object($logger)) continue;
			if (!method_exists($logger, 'log')) continue;
			$logger->log($error);
		}
	}
}

abstract class Eab_ErrorLogger {

	protected $_repository;

	public function __construct () {
		$this->_repository = Eab_ErrorRepository::get_instance();
		add_action('plugins_loaded', array($this, 'initialize'));
	}

	abstract public function initialize ();
	abstract public function log ($error);
}


class Eab_ErrorLogger_Admin extends Eab_ErrorLogger {

	public function initialize () {
		add_action('admin_notices', array($this, 'show_nags'));
	}

	public function log ($error) {
		$this->_repository->log($error);
	}

	function show_nags () {
		$errors = $this->_repository->get_all();
		$this->_repository->purge();
		if (empty($errors)) return false;
		
		$msg = '<ul><li>' . join('</li><li>', $errors) . '</li></ul>';
		echo "<div class='error'><p>{$msg}</p></div>";
	}
}