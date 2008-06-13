<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Simple generic queue.
 * @author Peter Goodman
 */
class Queue implements Countable, IteratorAggregate {
	
	// public for cloning purposes
	public $queue = array(),
		   $end = -1;
	
	/**
	 * Push something onto the stack.
	 */
	public function push($val) {
		$this->queue[] = $val;
		$this->end++;
	}
	
	/**
	 * Pop a scope off the stack.
	 */
	public function shift($null = NULL) {
		if($this->end < 0)
			throw new QueueException("Nothing to shift off queue.");
		
		$this->end--;
		return array_shift($this->queue);
	}
	
	/**
	 * Return whatever's on the top of the stack.
	 */
	public function &front() {
		if($this->end < 0)
			throw new QueueException("Queue is empty. Cannot access front ".
			                         "element.");
		
		return $this->queue[0];
	}
	
	/**
	 * Return how many items are in the stack.
	 */
	public function count() {
		return $this->end + 1;
	}
	
	/**
	 * Is the queue empty? */
	public function isEmpty() {
		return $this->end < 0;
	}

	/**
	 * Clear the queue.
	 */
	public function clear() {
		$this->end = -1;
		$this->queue = array();
	}
	
	/**
	 * Extend the queue.
	 */
	public function extend(array $items) {
		$this->queue = array_merge($this->queue, array_values($items));
		$this->end = count($this->queue) - 1;
	}
	
	/**
	 * Get an iterator.
	 */
	public function getIterator() {
		return new ArrayIterator($this->queue);
	}
}

if(!function_exists("queue")) {
	function queue() {
		return new Queue;
	}
}
