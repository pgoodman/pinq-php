<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * class_name(string) -> string
 *
 * Turn a string into a mixed-cased ASCII word that's suitable for being the
 * name of a class. For example:
 *     class_name(' foo--bar') -> 'FooBar'
 *
 * @author Peter Goodman
 */
function class_name($str = '') {
	$str = preg_replace('~([^a-zA-Z0-9]+)~', ' ', $str);
	return str_replace(' ', '', ucwords(strtolower($str)));
}

/**
 * function_name(string) -> string
 *
 * Turn a string into a lower case ASCII string where each word is separated
 * with an underscore. For example:
 *     function_name(' foo--bar') -> 'foo_bar'
 *
 * @author Peter Goodman
 */
function function_name($str = '') {
	return preg_replace('~([^a-z0-9_]+)~', '_', strtolower(trim($str)));
}

/**
 * call_user_class(string $class_name[, mixed $arg1[, mixed $arg2[, ...]]])
 * -> new($class_name)
 *
 * Instantiate a class with constructor arguments. Similar to call_user_func().
 *
 * @author Peter Goodman
 */
function call_user_class() {
	$args = func_get_args();
	
	if(!isset($args[0])) {
		throw new BadFunctionCallException(
			"call_user_class() expected at least one argument, zero given."
		);
	}
	
	return call_user_class_array(array_shift($args), $args);
}

/**
 * call_user_class_array(string $class_name[, array $args]) -> new($class_name)
 *
 * Instantiate a class given the class name and an array of its arguments. This
 * function is similar to call_user_func_array().
 *
 * @author Peter Goodman
 */
function call_user_class_array($class_name, array $args = array()) {
	
	if(!class_exists($class_name, FALSE)) {
		throw new UnexpectedValueException(
			"call_user_class[_array]() expects first argument to be a valid ".
			"class name. Class [{$class_name}] does not exist."
		);
	}
	
	// don't load up reflection unnecessarily	
	if(empty($args))
		return new $class_name;
	
	// reflect the class and instantiate it with arguments
	$reflector = new ReflectionClass($class_name);
    return $reflector->newInstanceArgs($args);
}

/**
 * base36_encode(int) -> string
 *
 * Convert a base 10 number to base 36.
 *
 * @author Peter Goodman
 */
function base36_encode($num) {
	return strtolower(base_convert((string)(int)$num, 10, 36));
}

/**
 * base36_decode(string) -> int
 *
 * Convert a base 36 number to base 10.
 */
function base36_decode($num) {
	return (int)base_convert((string)$num, 36, 10);
}

/**
 * random_hash(void) -> string
 *
 * Returns a random md5 hash.
 */
function random_hash() {
	return md5(uniqid(rand(), true));
}

/**
 * set_http_cookie(string $name, string $value, int $expiry[, string $path]) 
 * -> void
 *
 * Create an HTTP-only cookie.
 *
 * @author Peter Goodman
 */
function set_http_cookie($name, $value, $expiry, $path = '/') {
	
	if(headers_sent()) {
		throw new InternalErrorException(
			"Can't set cookie after headers have been sent."
		);
	}
	
	$host = trim(get_http_host(), '.');
	
	@setcookie(
		(string)$name, 
		(string)$value, 
		(int)$expiry, 
		$path,
		strpos($host, '.') !== FALSE ? ".{$host}" : '',
		get_http_scheme() == 'https',
		TRUE
	);
}

/**
 * unset_http_cookie(string $name) -> void
 *
 * Unset an HTTP-only cookie.
 *
 * @author Peter Goodman
 */
function unset_http_cookie($name) {
	set_http_cookie($name, '', time() - 7200);
}
