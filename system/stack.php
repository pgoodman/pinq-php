<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * An exception thrown on an invalid stack operation.
 *
 * @author Peter Goodman
 */
class StackException extends PinqException {
	
}

/**
 * Implementation of the stack data structure.
 *
 * @author Peter Goodman
 */
class Stack implements Countable, IteratorAggregate {
	
	// pubic for cloning purposes
	public $_stack = array(),
		   $_top = -1;
	
	/**
	 * Stack([array $default])
	 *
	 * Create a new stack data structure with the possibility of being pre-
	 * populated with default data.
	 */
	public function __construct(array $default = NULL) {
		if(NULL !== $default)
			$this->extend($default);
	}
	
	/**
	 * $s->push(mixed $val)
	 *
	 * Push an item on to the end of the stack.
	 */
	public function push($val) {
		$this->_stack[] = $val;
		$this->_top++;
	}
	
	/**
	 * $s->silentPop(void) -> mixed
	 *
	 * Remove and return the top item on the stack. If there zero items 
	 * left on the stack this function will return NULL.
	 */
	public function silentPop() {
		$ret = NULL;
		try {
			$ret = $this->pop();
		} catch(StackException $e) { }
		
		return $ret;
	}
	
	/**
	 * $s->pop(void) -> mixed
	 *
	 * Remove and return the top item on the stack. If there are zero items in
	 * the stack this method will throw a StackException.
	 */
	public function pop($null = NULL) {
		if($this->_top < 0)
			throw new StackException("Nothing to pop off stack.");
		
		$this->_top--;
		
		return array_pop($this->_stack);
	}
	
	/**
	 * $s->top(void) -> mixed
	 *
	 * Return the top item on the stack. If there are zero items in the stack
	 * this method will throw a StackException.
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
	 * $s->extend(array $items) -> void
	 *
	 * Add many items to the top of the stack.
	 */
	public function extend(array $items) {
		$this->_stack = array_merge(
			$this->_stack,
			array_values($items)
		);
		
		$this->_top = count($this->_stack) - 1;
	}
	
	/**
	 * $s->count(void) -> int
	 *
	 * Return the number of items in the stack.
	 */
	public function count() {
		return $this->_top+1;
	}
	
	/**
	 * $s->isEmpty(void) -> bool
	 *
	 * Check if there are zero items in the stack.
	 */
	public function isEmpty() {
		return $this->_top < 0;
	}
	
	/**
	 * $s->clear(void) -> void
	 *
	 * Remove all items from the stack.
	 */
	public function clear() {
		$this->_top = -1;
		$this->_stack = array();
	}
	
	/**
	 * $s->toArray(void) -> array
	 *
	 * Return the stack as a numerically indexed array.
	 */
	public function toArray() {
		return $this->_stack;
	}
	
	/**
	 * $s->getIterator(void) -> ArrayIterator
	 *
	 * Return an ArrayIterator of the stack. This is so that the stack can be
	 * used within a foreach() loop. The iterator works its way from the top
	 * of the stack to the bottom.
	 */
	public function getIterator() {
		return new ArrayIterator(array_reverse($this->_stack));
	}
}

/**
 * A stack of stacks. Despite its naming, this class doesn't actually store a
 * stack of stacks. It stores an array of arrays but acts as a stack interface
 * to the top-most array in the stack. Stacks can also be pushed on and popped
 * off.
 *
 * @author Peter Goodman
 */
class StackOfStacks extends Stack {
	
	protected $_stacks = array(),
	          $_stack_tops = array();
	
	/**
	 * $s->pushStack(void) -> void
	 *
	 * Push a new stack onto the stack.
	 */
	public function pushStack() {
		$this->_stacks[] = $this->_stack;
		$this->_stack_tops[] = $this->_top;
		
		$this->_stack = array();
		$this->_top = -1;
	}
	
	/**
	 * $s->popStack(void) -> void
	 *
	 * Pop the top stack off of the stack. If there are zero stacks left in
	 * the stack this method will throw a StackException.
	 */
	public function popStack() {
		if(count($this->_stacks) === 0)
			throw new StackException("Nothing to pop off stack.");
		
		$this->_stack = array_pop($this->_stacks);
		$this->_top = array_pop($this->_stack_tops);
	}
}
