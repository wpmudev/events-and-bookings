<?php

/**
 * Templating class instance
 * @return string Template class
 */
function eab_get_template_class () {
	$class = apply_filters('eab-templating-template_class', 'Eab_Template');
	return class_exists($class)
		? $class 
		: 'Eab_Template'
	;
}

/**
 * Template class caller wrapper.
 * A simplest wrapper, for now.
 */
function eab_call_template ($method) {
	$class_name = eab_get_template_class();
	if (!class_exists($class_name)) return false;

	$callback = array($class_name, $method);
	if (!is_callable($callback)) return false;

	$args = func_get_args();
	array_shift($args);

	return call_user_func_array($callback, $args);
}

/**
 * Template method checker.
 * @param  string $method Method to check
 * @return  bool Exists or not.
 */
function eab_has_template_method ($method) {
	if (!$method) return false;

	$class_name = eab_get_template_class();
	if (!class_exists($class_name)) return false;

	return is_callable(array($class_name, $method));
}

function eab_current_time () {
	return current_time('timestamp');
}