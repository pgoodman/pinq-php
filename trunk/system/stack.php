<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

if(!function_exists("stack")) {
	
	/**
	 * Simpler way to create a new stack.
	 */
	function stack() {
		return new Stack;
	}
}

/**
 * Simple generic stack.
 * @author Peter Goodman
 */
class Stack implements Countable, IteratorAggregate {
	
	// pubic for cloning purposes
	public $stack = array(),
		   $top = -1;
	
	/**
	 * Push something onto the stack.
	 */
	public function push($val) {
		$this->stack[] = $val;
		$this->top++;
	}
	
	/**
	 * Silently pop off the stack, that is, pop someone off if there's
	 * anything to pop.
	 */
	public function silentPop() {
		try {
			$ret = $this->pop();
		} catch(StackException $e) {
			$ret = NULL;
		}
		return $ret;
	}
	
	/**
	 * Pop something off the stack.
	 */
	public function pop($null = NULL) {
		if($this->top < 0)
			throw new StackException("Nothing to pop off stack.");
		
		$this->top--;
		return array_pop($this->stack);
	}
	
	/**
	 * Return whatever's on the top of the stack.
	 */
	public function &top() {
		if($this->top < 0) {
			throw new StackException(
				"Stack is empty. Cannot access top element."
			);
		}
		
		return $this->stack[$this->top];
	}
	
	/**
	 * Return how many items are in the stack.
	 */
	public function count() {
		return $this->top+1;
	}
	
	/**
	 * Is the stack empty? */
	public function isEmpty() {
		return $this->top < 0;
	}
	
	/**
	 * Clear the stack.
	 */
	public function clear() {
		$this->top = -1;
		$this->stack = array();
	}
	
	/**
	 * Get an iterator.
	 */
	public function getIterator() {
		return new ArrayIterator(array_reverse($this->stack));
	}
}
