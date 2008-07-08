<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Return a new string object for a string.
 *
 * @author Peter Goodman
 */
function string() {
	$args = func_get_args();
	return new StringObject(implode('', $args));
}


/**
 * Turn a string to lower case.
 *
 * @author Peter Goodman
 */
function lower($str) {
	if($str instanceof StringObject)
		return $str->toLower();
	
	return mb_convert_case($str, MB_CASE_LOWER, 'UTF-8');
}

/**
 * Turn a string to uppercase.
 *
 * @author Peter Goodman
 */
function upper($st) {
	if($str instanceof StringObject)
		return $str->toUpper();
	
	return mb_convert_case($str, MB_CASE_UPPER, 'UTF-8');
}

/**
 * mb_str_split(string $str[, int $split_length]) -> array
 *
 * Split up a multibyte string into an array.
 *
 * @author Peter Goodman
 */
function mb_str_split($str, $split_length = 1) {
	
	// split the string into individual characters
	$parts = preg_split(
		'~([\\xc0-\\xff][\\x80-\\xbf]*|\w|\W)~', 
		$str, 
		-1, 
		PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
	);
	
	// life rocks!
	if(1 === $split_length)
		return $parts;
	
	// annoyingly I couldn't do {2} in the pattern for split by 2 chars at a
	// time
	$joined_parts = array();
	$len = count($parts);
	$len -= $len % $split_length;
	$c = $j = 0;
	
	// join the parts in the array
	while($c < $len) {
		$joined_parts[$j] = '';
		
		$i = 0;
		while($i++ < $split_length)
			$joined_parts[$j] .= array_shift($parts);
		
		$c += $split_length;
		$j++;
	}
	
	return $joined_parts;
}

/**
 * mb_strrev(string) -> string
 * Reverse a multibyte string.
 *
 * @author Peter Goodman
 */
function mb_strrev($str) {
	return implode('', array_reverse(mb_str_split($str)));
}

/**
 * Class to manipulate strings and work with them as iterators.
 * 
 * @author Peter Goodman
 */
class StringObject implements SeekableIterator, ArrayAccess, Countable {
	
	protected $str,
	          $index,
	          $len,
	          $encoding;
	
	/**
	 * StringObject([string])
	 */
	public function __construct($str = '') {
		
		if($str instanceof StringObject)
			$str = $str->__toString();
		
		$this->len = mb_strlen($str);
		$this->index = 0;
		$this->str = $str;
		$this->encoding = 'UTF-8';
	}
	
	/**
	 * $s->__toString() <==> (string)$s -> string
	 */
	public function __toString() {
		return $this->str;
	}
	
	/**
	 * $s->count(void) <==> count($s) -> int
	 */
	public function count() {
		return $this->len;
	}
	
	/**
	 * $s->valid(void) -> bool
	 */
	public function valid() {
		return $this->index < $this->len;
	}
	
	/**
	 * $s->next(void) -> void
	 */
	public function next() {
		$this->index++;
	}
	
	/**
	 * $s->key(void) -> int
	 */
	public function key() {
		return $this->index;
	}
	
	/**
	 * $s->seek(int $index) -> void
	 */
	public function seek($index) {
		if(!$this->offsetExists($index)) {
			throw new OutOfBoundsException(
				"Cannot seek to string index outside of string."
			);
		}
		$this->index = $index;
	}
	
	/**
	 * $s->current(void) -> string
	 */
	public function current() {
		return $this->offsetGet($this->index);
	}
	
	/**
	 * $s->rewind(void) -> void
	 */
	public function rewind() {
		$this->index = 0;
	}
	
	/**
	 * $s->offsetExists(int $index) <==> isset($s[$index]) -> bool
	 */
	public function offsetExists($index) {
		return $index > 0 && $index < $this->len;
	}
	
	/**
	 * $s->offsetGet(int $index) <==> $s[$index] -> string
	 */
	public function offsetGet($index) {
		if(!$this->offsetExists($index))
			return NULL;
		
		return mb_substr($this->str, $index, 1, $this->encoding);
	}
	
	/**
	 * $s->offsetSet(int $index, string $value) <==> $s[$index] = $value -> void
	 */
	public function offsetSet($index, $value) {
		
		// can't set a valye outside of the string
		if(!$this->offsetExists($index)) {
			throw new OutOfBoundsException(
				"Offset given is not part of string."
			);
		}
		
		// check the length of the value
		$len = mb_strlen($value);
		if($len < 0 || $len > 1) {
			throw new InvalidArgumentException(
				"Cannot substitute many characters into the position of one ".
				"character in a string."
			);
		}
		
		// I wonder if there is a nicer way of doing this..
		$this->str = mb_substr($this->str, 0, $index, $this->encoding) . 
		             mb_substr($value, 0, 1, $this->encoding) . 
		             mb_substr($this->str, $index++, $this->encoding);
	}
	
	/**
	 * Unimplemented.
	 */
	public function offsetUnset($index) {
		// does nothing
	}
	
	/**
	 * $s->toLower(void) -> StringObject
	 */
	public function toLower() {
		$this->str = mb_convert_case($str, MB_CASE_LOWER, $this->encoding);
		return $this;
	}
	
	/**
	 * $s->toUpper(void) -> StringObject
	 */
	public function toUpper() {
		$this->str = mb_convert_case($str, MB_CASE_UPPER, $this->encoding);
		return $this;
	}
	
	/**
	 * $s->reverse(void) <==> reverse($s) -> StringObject
	 */
	public function reverse() {
		$this->str = mb_strrev($this->str);
	}
}