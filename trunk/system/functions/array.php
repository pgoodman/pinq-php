<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * expect_array_keys(array $array, array $required_keys) ! UnexpectedValueException
 *
 * Test that $array has all keys present in the values of $required_keys. If
 * that is not the case then this function will throw an UnexpectedValueException.
 *
 * @author Peter Goodman
 */
function expect_array_keys(array $array, array $required_keys) {
	$keys = array_flip($required_keys);
	
	// not all the keys were present, throw an exception, unfortunately this
	// error is somewhat vague.
	if(count(array_intersect_key($array, $keys)) != count($keys)) {
		throw new UnexpectedValueException(
			"Dependency not satisfied. Expected [". implode(',', $required_keys).
			"] keys in array but did not find them all."
		);
	}
}

/**
 * &array_copy(array &$array) -> array
 *
 * Make a deep copy of an array. Scalar values and arrays are copied as usual
 * and objects are cloned.
 *
 * @author Peter Goodman
 */
function &array_copy(array &$array) {
	$copy = array();
	
	// iterate over the original array
	foreach($array as $key => $val) {
		
		// clone any objects
		if(is_object($val))
			$val = clone $val;
		
		// recursively perform a deep copy
		else if(is_array($val))
			$val = &array_copy($val);

		// make the copy
		$copy[$key] = &$val;
	}
	
	return $copy;
}
