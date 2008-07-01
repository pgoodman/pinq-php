<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Given an identifier, load and store data from some source into memory.
 *
 * @see Dictionary
 * @author Peter Goodman
 */
abstract class Loader extends Dictionary {
	
	/**
	 * $l->load(string $key, array $context) -> mixed
	 */
	abstract public function load($key, array $context = array());
	
	/**
	 * $l->store(string $key, mixed $value) -> void
	 */
	abstract public function store($key, $value = NULL);
}
