<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Exception representing invalid operations in a queue.
 *
 * @author Peter Goodman
 */
class QueueException extends PinqException {
	
}

/**
 * Implementation of the Queue data structure.
 *
 * @author Peter Goodman
 */
class Queue implements Countable, IteratorAggregate {
	
	// public for cloning purposes
	protected $_queue = array(),
	          $_end = -1;
	
	/**
	 * $q->push(mixed $val)
	 *
	 * Push a value on to the end of the queue.
	 */
	public function push($val) {
		$this->_queue[] = $val;
		$this->_end++;
	}
	
	/**
	 * $q->shift(void) -> mixed
	 *
	 * Remove and return the first item from the queue. If there are zero items
	 * left in the queue this method will throw a QueueException.
	 */
	public function shift() {
		if($this->_end < 0)
			throw new QueueException("Nothing to shift off queue.");
		
		$this->_end--;
		return array_shift($this->_queue);
	}
	
	/**
	 * $q->front(void) -> mixed
	 *
	 * Return the first item from the queue. If there are zero items left in
	 * the queue this method will throw a QueueException.
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
	 * $q->count(void) -> int
	 *
	 * Return the number of items in the queue.
	 */
	public function count() {
		return $this->_end + 1;
	}
	
	/**
	 * $q->isEmpty(void) -> bool
	 *
	 * Check if there are zero items in queue.
	 */
	public function isEmpty() {
		return $this->_end < 0;
	}

	/**
	 * $q->clear(void) -> void
	 *
	 * Remove all items from the queue.
	 */
	public function clear() {
		$this->_end = -1;
		$this->_queue = array();
	}
	
	/**
	 * $q->extend(array $items) -> void
	 *
	 * Merge an array of items on to the end of a queue, thus extending it.
	 */
	public function extend(array $items) {
		
		$this->_queue = array_merge(
			$this->_queue, 
			array_values($items)
		);
		
		// recount
		$this->_end = count($this->_queue) - 1;
	}
	
	/**
	 * $q->toArray(void) -> array
	 *
	 * Return the queue as a numerically indexed array.
	 */
	public function getArray() {
		return $this->_queue;
	}
	
	/**
	 * $q->getIterator(void) -> ArrayIterator
	 *
	 * Return an iterator of the items in the queue. This allows a queue to be
	 * passed into a foreach() loop and iterated over.
	 */
	public function getIterator() {
		return new ArrayIterator($this->_queue);
	}
}
