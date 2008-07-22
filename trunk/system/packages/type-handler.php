<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class to handle classes that operate on a certain type.
 *
 * @author Peter Goodman
 */
class PinqTypeHandler implements InstantiablePackage {
	
	protected $_handlers = array();
	
	/**
	 * $h->handleScalar(string $type, Handler) -> void
	 *
	 * Handle scalar {boolean, integer, string, etc} types.
	 */
	public function handleScalar($type, Handler $handler) {
		$this->_handlers[$type] = $handler;
	}
	
	/**
	 * $h->handleClass(string $class_name, Handler) 
	 * -> void
	 * ! InvalidArgumentException
	 *
	 * Handle instances of a class/interface.
	 */
	public function handleObject($class_name, Handler $handler) {
		if(!class_exists($class_name, FALSE) &&
		   !interface_exists($class_name, FALSE)) {
			throw new InvalidArgumentException(
				"TypeHandler::handleObject() expects first parameter to be ".
				"valid class name. Class [{$class_name}] does not exist."
			);
		}
		
		$this->_handlers['object'][$class_name] = $handler;
	}
	
	/**
	 * $h->getHandler(mixed) -> {Handler, NULL}
	 *
	 * Given a variable of any type, try to find a suitable handler.
	 */
	public function getHandler($obj) {
		$type = gettype($obj);
		
		if($type == 'object') {
			foreach($this->_handlers['object'] as $class => $handler) {
				if($obj instanceof $class)
					return $handler;
			}
			
			return NULL;
		}
		
		return isset($this->_handlers[$type]) ? $this->_handlers[$type] : NULL;
	}
}
