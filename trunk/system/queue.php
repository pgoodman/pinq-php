<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists("queue")) {
	
	/**
	 * Quciker way to make a queue.
	 */
	function queue() {
		return new Queue;
	}
}

/**
 * Simple generic queue.
 * @author Peter Goodman
 */
class Queue implements Countable, IteratorAggregate {
	
	// public for cloning purposes
	public $_queue = array(),
		   $_end = -1;
	
	/**
	 * Push something onto the stack.
	 */
	public function push($val) {
		$this->_queue[] = $val;
		$this->_end++;
	}
	
	/**
	 * Pop a scope off the stack.
	 */
	public function shift($null = NULL) {
		if($this->_end < 0)
			throw new QueueException("Nothing to shift off queue.");
		
		$this->_end--;
		return array_shift($this->_queue);
	}
	
	/**
	 * Return whatever's on the top of the stack.
	 */
	public function &front() {
		if($this->_end < 0) {
			throw new QueueException(
				"Queue is empty. Cannot access front element."
			);
		}
		
		return $this->_queue[0];
	}
	
	/**
	 * Return how many items are in the stack.
	 */
	public function count() {
		return $this->_end + 1;
	}
	
	/**
	 * Is the queue empty? */
	public function isEmpty() {
		return $this->_end < 0;
	}

	/**
	 * Clear the queue.
	 */
	public function clear() {
		$this->_end = -1;
		$this->_queue = array();
	}
	
	/**
	 * Extend the queue.
	 */
	public function extend(array $items) {
		$this->_queue = array_merge($this->_queue, array_values($items));
		$this->_end = count($this->_queue) - 1;
	}
	
	/**
	 * Get the queue as an array.
	 */
	public function getArray() {
		return $this->_queue;
	}
	
	/**
	 * Get an iterator.
	 */
	public function getIterator() {
		return new ArrayIterator($this->_queue);
	}
}
