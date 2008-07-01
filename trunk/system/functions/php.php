<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Turn a string into a camel-cased ASCII word that's suitable for being the
 * name of a class.
 * @author Peter Goodman
 */
function class_name($str = '') {
	$str = preg_replace('~([^a-zA-Z0-9]+)~', ' ', $str);
	return str_replace(' ', '', ucwords(strtolower($str)));
}

/**
 * Turn a string into something that is suitable as a function/method name.
 * @author Peter Goodman
 */
function function_name($str = '') {
	return preg_replace('~([^a-z0-9_]+)~', '_', strtolower(trim($str)));
}

/**
 * Instantiate a class with constructor arguments.
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
 * Instantiate a class given an array of arguments.
 * @author Peter Goodman
 */
function call_user_class_array($class, array $args = array()) {
	
	if(!class_exists($class)) {
		throw new UnexpectedValueException(
			"call_user_class[_array]() expects first argument to be a valid ".
			"class name. Class [{$class}] does not exist."
		);
	}
	
	// don't load up reflection unnecessarily	
	if(empty($args))
		return new $class;
	
	// reflect the class and instantiate it with arguments
	$reflection = new ReflectionClass($class);
    return $reflection->newInstanceArgs($args);
}

