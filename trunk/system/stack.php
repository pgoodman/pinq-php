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
	public $_stack = array(),
		   $_top = -1;
	
	/**
	 * Constructor, bring in a default stack.
	 */
	public function __construct(array $default = NULL) {
		if(NULL !== $default) {
			$this->_stack = array_values($default);
			$this->_top = count($default) - 1;
		}
	}
	
	/**
	 * Push something onto the stack.
	 */
	public function push($val) {
		$this->_stack[] = $val;
		$this->_top++;
	}
	
	/**
	 * Silently pop off the stack, that is, pop someone off if there's
	 * anything to pop.
	 */
	public function silentPop() {
		$ret = NULL;
		try {
			$ret = $this->pop();
		} catch(StackException $e) { }
		
		return $ret;
	}
	
	/**
	 * Pop something off the stack.
	 */
	public function pop($null = NULL) {
		if($this->_top < 0)
			throw new StackException("Nothing to pop off stack.");
		
		$this->_top--;
		
		return array_pop($this->_stack);
	}
	
	/**
	 * Return whatever's on the top of the stack.
	 */
	public function &top() {
		if($this->_top < 0) {
			throw new StackException(
				"Stack is empty. Cannot access top element."
			);
		}
		
		return $this->_stack[$this->_top];
	}
	
	/**
	 * Return how many items are in the stack.
	 */
	public function count() {
		return $this->_top+1;
	}
	
	/**
	 * Is the stack empty? */
	public function isEmpty() {
		return $this->_top < 0;
	}
	
	/**
	 * Clear the stack.
	 */
	public function clear() {
		$this->_top = -1;
		$this->_stack = array();
	}
	
	/**
	 * Get an iterator.
	 */
	public function getIterator() {
		return new ArrayIterator(array_reverse($this->_stack));
	}
	
	/**
	 * Get the array inside the stack.
	 */
	public function toArray() {
		return $this->_stack;
	}
}

class StackOfStacks extends Stack {
	
	protected $_stacks = array(),
	          $_stack_tops = array();
	
	/**
	 * Push a new stack onto the stack.
	 */
	public function pushStack() {
		$this->_stacks[] = $this->_stack;
		$this->_stack_tops[] = $this->_top;
		
		$this->_stack = array();
		$this->_top = -1;
	}
	
	/**
	 * Pop a stack off of the stack.
	 */
	public function popStack() {
		if(count($this->_stacks) === 0)
			throw new StackException("Nothing to pop off stack.");
		
		$this->_stack = array_pop($this->_stacks);
		$this->_top = array_pop($this->_stack_tops);
	}
}
