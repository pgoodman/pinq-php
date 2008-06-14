<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists("dict")) {
	
	/**
	 * Simpler way to make a dictionary. Very pythonesque :P
	 */
	function dict(array $vals = NULL) {
		return new Dictionary($vals);
	}
}

/**
 * Simple dictionary. This is a class that is array-like, although clearly not
 * as robust as PHP's ArrayObject class.
 */
class Dictionary implements ArrayAccess {
	protected $dict = array();
	
	/**
	 * Constructor, bring in any default values.
	 */
	public function __construct(array &$vals = NULL) {
		
		if(!empty($vals))
			$this->dict = &$vals;
	}
	
	/**
	 * Destructor, clear the stored values.
	 */
	public function __destruct() {
		unset($this->dict);
	}
	
	/**
	 * Get a value by its key from the dictionary.
	 */
	public function offsetGet($key) {
		if(!isset($this->dict[$key]))
			return NULL;
		
		return $this->dict[$key];
	}
	
	/**
	 * Set a value to a specific key in the dictionary.
	 */
	public function offsetSet($key, $val) {
		$this->dict[$key] = $val;
	}
	
	/**
	 * Unset a specific key,value pair in the dictionary.
	 */
	public function offsetUnset($key) {
		unset($this->dict[$key]);
	}
	
	/**
	 * Check if an entry in the dictionary exists for a given key.
	 */
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
