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
	protected $_dict = array();
	
	/**
	 * Constructor, bring in any default values.
	 */
	public function __construct(array &$vals = NULL) {
		
		if(!empty($vals))
			$this->_dict = &$vals;
	}
	
	/**
	 * Destructor, clear the stored values.
	 */
	public function __destruct() {
		unset($this->_dict);
	}
	
	/**
	 * Get a value by its key from the dictionary.
	 */
	public function offsetGet($key) {
		if(!isset($this->_dict[$key]))
			return NULL;
		
		return $this->_dict[$key];
	}
	
	/**
	 * Set a value to a specific key in the dictionary.
	 */
	public function offsetSet($key, $val) {		
		if(NULL === $key) {
			
			// quick array_merge syntax to dictionaries using array_push
			// syntax
			if(is_array($val))
				$this->_dict = array_merge($this->_dict, $val);
			
			// invalid key type
			else {
				throw new InvalidArgumentException(
					"Dictionary::offsetSet expects first parameter to be ".
					"string or int, or NULL with the second parameter being ".
					"an array. Neither case was satisfied."
				);
			}
		
		// normal syntax, set a key into the dictionary
		} else
			$this->_dict[$key] = $val;
	}
	
	/**
	 * Unset a specific key,value pair in the dictionary.
	 */
	public function offsetUnset($key) {
		unset($this->_dict[$key]);
	}
	
	/**
	 * Check if an entry in the dictionary exists for a given key.
	 */
	public function offsetExists($key) {
		return isset($this->_dict[$key]);
	}
	
	/**
	 * Return the array in the dictionary.
	 */
	public function toArray() {
		return $this->_dict;
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
