<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Contract representing a class that's able to load things from a foreign
 * source and store them in its internal memory.
 */
abstract class Loader extends Dictionary {
	
	abstract public function &load($key, array $context = array());
	
	abstract public function store($key, $value = NULL);
}
