<?php

if(!function_exists('array_copy')) {
	/**
	 * Make a deep copy of an array of a tuple
	 */
	function &array_copy(array &$array) {
		$copy = array();

		$keys = array_keys($array);
		$vals = array_values($array);
		$count = count($keys);

		for($i = 0; $i < $count; ++$i) {

			// assume scalar / immediate
			$val = $vals[$i];

			if(is_object($val))
				$val = clone $val;
			else if(is_array($val))
				$val = &$this->deepCopy($val);

			// make the copy
			$copy[$keys[$i]] = &$val;
		}

		return $copy;
	}
}

if(!function_exists('tuple')) {
	/**
	 * More convenient syntax for using a tuple.
	 */
	function tuple() {
		$args = func_get_args();
		return new Tuple($args);
	}
}

/**
 * This is the equivalent of an immutable array. It maintains the quality of
 * immutability by only returning copies of things. However, if an object is
 * passed into the tuple and later modified then immutability will be broken.
 * @author Peter Goodman
 */
final class Tuple implements Iterator, ArrayAccess, Countable, Serializable {
	
	private $array,
			$count,
			$key = 0;
	
	/**
	 * Construct the tuple.
	 */
	public function __construct() {
		$array = func_get_args();
		
		// fair assumption, if only one argument and the first argument is an
		// array, assume that the array is meant to be the tuple.
		if(count($array) == 1 && is_array($array[0]))
			$array = $array[0];
		
		$this->array = array_values($array);
		$this->count = count($array);
	}
	
	// we don't want these methods to be able to modify anything
	public function offsetUnset($key) { throw new ImmutableException; }
	public function offsetSet($key, $value) { throw new ImmutableException; }
	
	/**
	 * Get the value of the tuple at a given offset. Negative indices are
	 * allowed, and so are arrays. To slice the tuple into bits, an array of
	 * indexes to get from the tuple can be passed into it.
	 */
	public function offsetGet($key) {
		
		// allow for pythonesque tuple slicing by supplying an array of keys
		// to slice by. the simplest slice can pass a range(), for example:
		// $tuple = tuple(1,2,3,4,5); 
		// to get a tuple of (2,3,4) do: $slice = $tuple[range(1,3)].
		if(is_array($key)) {
			$result = array_intersect_key($this->array, array_flip($key));
			return new self($result);
		}
		
		// force us to use an int so that php doesn't bug us about alternate
		// index types
		$key = (int)$key;
		
		// allow for negative key accesses to look at the back of the array
		if($key < 0)
			$key = $this->len - $key;
		
		// if there's nothing to return, just return NULL and forgoe doing
		// further checks on the return value
		if(!isset($this->array[$key]))
			return NULL;
		
		// get the return value.. we might still need to modify it so that be
		// are not returning a reference to something currently in the tuple
		$val = $this->array[$key];
		
		// to maintain the state of immutability, we need to return copies of
		// everything, including objects
		if(is_object($val))
			$val = clone $val;
		else if(is_array($val))
			$val = &array_copy($val);
		
		return $val;
	}
	
	/**
	 * Check if an offset of the tuple exists. Negative indices are allowed.
	 */
	public function offsetExists($key) {
		if($key < 0)
			$key = $this->len - $key;
		
		return isset($this->array[$key]);
	}
	
	/**
	 * Return the number of elements in the tuple.
	 */
	public function count() {
		return $this->count;
	}
	
	/**
	 * Allow the tuple to be iterated over, but also through the iteration,
	 * not be modified.
	 */
	public function &getIterator() {
		return new TupleIterator($this->array);
	}
	
	/**
	 * Iterator methods. The iterator was embedded into the tuple instead of
	 * using iterator aggregate so that I could reuse offsetGet and centralize
	 * copying things into it.
	 */
	public function current() {
		return $this->offsetGet($this->key());
	}
	public function valid() {
		return $this->offsetExists($this->key()+1);
	}
	public function rewind() {
		$this->key = 0;
	}
	public function next() {
		$this->key++;
	}
	public function key() {
		return $this->key;
	}
	
	/**
	 * Serialize the array within the tuple.
	 */
	public function serialize() {
		return serialize($this->array);
	}
	public function unserialize($str) { throw new ImmutableException; }
}
