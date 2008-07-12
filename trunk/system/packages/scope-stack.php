<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Scope stack. Allows variables to be found from parent scopes.
 *
 * @author Peter Goodman
 */
class PinqScopeStack extends StackOfDictionaries implements InstantiablePackage {
	
	/**
	 * $s->offsetGet(string $key) <==> $s[$key] -> mixed
	 *
	 * Get a value from the current scope or a parent one.
	 */
	public function offsetGet($key) {
		
		if(isset($this->_dict[$key]))
			return $this->_dict[$key];
		
		$i = count($this->_stack) - 1;
		
		while(isset($this->_stack[$i])) {
			if(isset($this->_stack[$i][$key]))
				return $this->_stack[$i][$key];
			$i--;
		}
		
		return NULL;
	}
}
