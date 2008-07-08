<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * The same as IteratorIterator.. oddly there were issues with IteratorIterator
 * when working with records.
 *
 * @author PHP
 */
class PinqIteratorIterator implements OuterIterator {
	
	protected $_iterator;
	
	function __construct(Traversable $iterator) {
	
		if($iterator instanceof IteratorAggregate)
			$iterator = $iterator->getIterator();

		if ($iterator instanceof Iterator)
			$this->_iterator = $iterator;
		else {
			throw new Exception(
				"Classes that only implement Traversable can be wrapped only ".
				"after converting class IteratorIterator into c code"
			);
		}
	}

	function getInnerIterator() {
		return $this->_iterator;
	}

	function valid() {
		return $this->_iterator->valid();
	}

	function key() {
		return $this->_iterator->key();
	}

	function current() {
		return $this->_iterator->current();
	}

	function next() {
		return $this->_iterator->next();
	}

	function rewind() {
		return $this->_iterator->rewind();
	}

	function __call($func, $params) {
		return call_user_func_array(array($this->_iterator, $func), $params);
	}
}