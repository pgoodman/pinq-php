<?php

/**
 * Create a scope stack. This is very similar to a registry insofar as each
 * scope is a registry of sorts.
 * @author Peter Goodman
 */
class ScopeStack extends Stack implements ArrayAccess {
	
	/**
	 * Constructor, put the root scope onto the stack. Also modify the way the
	 * stack works.
	 */
	public function __construct() {
		$global_scope = array();
		$this->push($global_scope);
	}
	
	/**
	 * Push a scope onto the stack.
	 */
	public function push($val) {
		DEBUG_MODE && assert("is_array($val)");
		parent::push($val);
	}
	
	/**
	 * Get the scope level that this key appears on.
	 */
	protected function offsetLevel($key) {
		// go down to a scope where the key might exist
		$level = $this->top;
		
		// go down the stack until we find the key that we want
		while(isset($this->stack[$level]) && !isset($this->stack[$level][$key]))
			$level--;
		
		return $level >= 0 ? $level : FALSE;
	}
	
	/**
	 * Go get a scope variable.
	 */
	public function offsetGet($key) {
		
		$null = NULL;
		
		// get the highest level that this key is found on, and return NULL
		// if it doesn't exist
		if(FALSE === ($level = $this->offsetLevel($key)))
			return $null;
		
		// memoize the value into the current scope
		if($level != $this->top)
			$this->stack[$this->top][$key] =& $this->stack[$level][$key];
		
		return $this->stack[$level][$key];
	}
	
	/**
	 * Set a scope variable.
	 */
	public function offsetSet($key, $val) {
		if(FALSE === ($level = $this->offsetLevel($key)))
			$level = $this->top;
		
		$this->scope[$level][$key] = $val;
	}
	
	/**
	 * Unset a scope variable (in its highest scope).
	 */
	public function offsetUnset($key) {
		if(FALSE !== ($level = $this->offsetLevel($key)))
			unset($this->stack[$level][$key]);
	}
	
	/**
	 * Figure out if a scope variable exists in any of the scopes.
	 */
	public function offsetExists($key) {
		return $this->offsetLevel($key) === FALSE;
	}
}
