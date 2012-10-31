<?php

/**
 * Template class caller wrapper.
 * A simplest wrapper, for now.
 */
function eab_call_template ($method) {
	$class_name = 'Eab_Template';
	if (!class_exists($class_name)) return false;

	$callback = array($class_name, $method);
	if (!is_callable($callback)) return false;

	$args = func_get_args();
	array_shift($args);

	return call_user_func_array($callback, $args);
}