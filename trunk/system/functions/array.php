<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Test that an array has, at the minimum, the desired keys set.
 * @author Peter Goodman
 */
function expect_array_keys(array $array, array $required_keys) {
	$keys = array_flip($required_keys);
	
	// not all the keys were present, throw an exception, unfortunately this
	// error is somewhat vague.
	if(count(array_intersect_key($array, $keys)) != count($required_keys)) {
		throw new UnexpectedValueException(
			"Dependency not satisfied. Expected [". implode(',', $required_keys).
			"] keys in array but did not find them all."
		);
	}
}
