<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();
	
/**
 * dict([array]) -> Dictionary(array)
 *
 * Return a new Dictionary object with the default values in the dictionary
 * present.
 *
 * @author Peter Goodman
 */
function dict(array $vals = NULL) {
	return new Dictionary($vals);
}

/**
 * An array-like class for mapping keys to values.
 *
 * @author Peter Goodman
 */
class Dictionary implements ArrayAccess {
	
	protected $_dict = array();
	
	/**
	 * Dictionary([array $default_values]) <==> dict($default_values)
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
	 * $d->offsetGet($key) <==> $d[$key]
	 *
	 * Get the value (entry) for a specific key in the dictionary. If the key
	 * does not exist in the dictionary then this function will return NULL.
	 */
	public function offsetGet($key) {
		if(!isset($this->_dict[$key]))
			return NULL;
		
		return $this->_dict[$key];
	}
	
	/**
	 * $d->offsetSet($key, $val) <==> $d[$key] = $val
	 * $d->offsetSet(NULL, array $val) <==> $d[] = $val
	 * 
	 * Map a key in the dictionary to a specific value.
	 *
	 * @note When specifying the key as NULL, val must be an array.
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
	 * $d->offsetUnset($key) <==> unset($d[$key])
	 *
	 * Remove a (key,value) entry in the dictionary.
	 */
	public function offsetUnset($key) {
		unset($this->_dict[$key]);
	}
	
	/**
	 * $d->offsetExists($key) <==> isset($d[$key])
	 *
	 * Check if an entry in this dictionary exists for a given key.
	 */
	public function offsetExists($key) {
		return isset($this->_dict[$key]);
	}
	
	/**
	 * $d->toArray(void) -> array
	 *
	 * Return the (key,value) pairs in the dictionary as an array mapping keys
	 * to values.
	 */
	public function toArray() {
		return $this->_dict;
	}
}

/**
 * An exception representing an invalid operation (write/delete) on an
 * immutable data structure.
 *
 * @author Peter Goodman
 */
class ImmutableException extends PinqException {
	
}

/**
 * A dictionar that after instantiation cannot be written to or have entries
 * removed from.
 *
 * @note This class does little to ensure the property of immutability for
 *       non-scalar data types.
 * @see Dictionary, ImmutableException
 * @author Peter Goodman
 */
class ReadOnlyDictionary extends Dictionary {
	
	/**
	 * $d->offsetSet($key, $val) ! ImmutableException
	 */
	public function offsetSet($key, $val) {
		throw new ImmutableException("This dictionary is read-only.");
	}
	
	/**
	 * $d->offsetUnset($key) ! ImmutableException
	 */
	public function offsetUnset($key) {
		throw new ImmutableException("This dictionary is read-only.");
	}
}

/**
 * A stack of dictionaries. This is implemented using Dictionary as the base
 * instead of Stack because implementing a dictionary as a stack is fundamentally
 * simpler than implementing a stack as a dictionary.
 *
 * @author Peter Goodman
 */
class StackOfDictionaries extends Dictionary {
	
	protected $_stack;
	
	/**
	 * StackOfDictionaries(void)
	 */
	public function __construct() {
		parent::__construct();
		
		$vars = array();
		$this->push($vars);
	}
	
	/**
	 * $s->push(array &$vars) -> void
	 *
	 * Push an array onto the stack of dictionaries.
	 */
	public function push(array &$vars) {
		$this->_stack[] = &$this->_dict;
		$this->_dict = $vars;
	}
	
	/**
	 * $d->pop(void) -> void
	 *
	 * Pop an array off the stack of dictionaries.
	 */
	public function pop() {
		$this->_dict = array_pop($this->_stack);
	}
	
	/**
	 * $d->top(void) -> mixed
	 *
	 * Return whatever is on top of the top dictionary in the stack.
	 */
	public function top() {
		return $this->toArray();
	}
}
