<?php

/**
 * Templating class instance
 * @return string Template class
 */
function eab_get_template_class () {
	$class = apply_filters('eab-templating-template_class', 'Eab_Template');
	return class_exists( $class )
		? $class 
		: 'Eab_Template'
	;
}

/**
 * Template class caller wrapper.
 * A simplest wrapper, for now.
 */
function eab_call_template ( $method ) {
	$class_name = eab_get_template_class();
	if ( !class_exists( $class_name ) ) { 
		return false;
	}

	$callback = array( $class_name, $method );
	if ( !is_callable( $callback ) ) {
		return false;
	}

	$args = func_get_args();
	array_shift( $args );

	return call_user_func_array( $callback, $args );
}

/**
 * Template method checker.
 * @param  string $method Method to check
 * @return  bool Exists or not.
 */
function eab_has_template_method ($method) {
	if ( !$method ) { 
		return false;
	}

	$class_name = eab_get_template_class();
	if ( !class_exists( $class_name ) ) {
		return false;
	}

	return is_callable( array( $class_name, $method ) );
}

function eab_current_time () {
	return current_time('timestamp');
}

/* ----- PI compatibility layer ----- */
function eab_has_post_indexer () {
	return class_exists( 'postindexermodel' ) || function_exists( 'post_indexer_make_current' );
}
function eab_pi_get_table () {
	return class_exists( 'postindexermodel' ) ? 'network_posts' : 'site_posts';
}
function eab_pi_get_post_date () {
	return class_exists( 'postindexermodel' ) ? 'post_date' : 'post_published_stamp';
}
function eab_pi_get_blog_id () {
	return class_exists( 'postindexermodel' ) ? 'BLOG_ID' : 'blog_id';
}
function eab_pi_get_post_id () {
	return class_exists( 'postindexermodel' ) ? 'ID' : 'post_id';
}