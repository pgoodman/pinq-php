<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Scope stack. Allows variables to be found from parent scopes.
 *
 * @author Peter Goodman
 */
class PinqScopeStack extends StackOfDictionaries 
                     implements InstantiablePackage, Factory {
	
	static public $_class;
	
	/**
	 * PinqScopeStack::factory(void) -> PinqScopeStack
	 *
	 * Return an instance of this class, or the proper extending class.
	 */
	static public function factory() {
		$class = self::$_class;
		return new $class;
	}
	
	/**
	 * $s->offsetGet(string $key) <==> $s[$key] -> mixed
	 *
	 * Get a value from the current scope or a parent one.
	 */
	public function offsetGet($key) {
		
		if(isset($this->_dict[$key]))
			return $this->_dict[$key];
		
		$i = count($this->_stack);
		while(isset($this->_stack[--$i])) {
			if(isset($this->_stack[$i][$key])) {
				
				// memoize and return
				$this->_dict[$key] = &$this->_stack[$i][$key];
				return $this->_stack[$i][$key];
			}
		}
		
		return NULL;
	}
}
