<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Simple dictionary. This is a class that is array-like, although clearly not
 * as robust as PHP's ArrayObject class.
 */
class Dictionary implements ArrayAccess {
	protected $dict = array();
	
	public function __construct(array &$vals = NULL) {
		if($vals)
			$this->dict = &$vals;
	}
	
	public function __destruct() {
		unset($this->dict);
	}
	
	public function offsetGet($key) {
		if(!isset($this->dict[$key]))
			return NULL;
		
		return $this->dict[$key];
	}
	
	public function offsetSet($key, $val) {
		$this->dict[$key] = $val;
	}
	
	public function offsetUnset($key) {
		unset($this->dict[$key]);
	}
	
	public function offsetExists($key) {
		return isset($this->dict[$key]);
	}
}

/**
 * Sort of like the little-brother to the tuple. The dictionary is still
 * writable through references; however, use cases such as limiting super-
 * globals means that we overwrite something else. By doing so, a special
 * case is made where a key means something different and maintainability
 * will just avalanche from there.
 *
 * This class does little to insure the property of immutability, but is a
 * satisfactory implementation.
 * @author Peter Goodman
 */
class ReadOnlyDictionary extends Dictionary {
	public function offsetSet($key, $val) {
		throw new ImmutableException("This dictionary is read-only.");
	}
	
	public function offsetUnset($key) {
		throw new ImmutableException("This dictionary is read-only.");
	}
}


if(!function_exists("dict")) {
	function dict(array $vals = NULL) {
		return new Dictionary($vals);
	}
}