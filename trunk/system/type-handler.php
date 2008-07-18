<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

/**
 * Class to handle classes that operate on a certain type.
 *
 * @author Peter Goodman
 */
class TypeHandler {
	
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
	 * Handle instances of a class or classes that extend this class.
	 */
	public function handleClass($class_name, Handler $handler) {
		if(!class_exists($class_name, FALSE)) {
			throw new InvalidArgumentException(
				"TypeHandler::handleClass() expects first parameter to be ".
				"valid class name. Class [{$class_name}] does not exist."
			);
		}
		
		$this->_handlers['object'][$class_name] = $handler;
	}
	
	/**
	 * $h->handleInterface(string $interface_name, Handler) 
	 * -> void
	 * ! InvalidArgumentException
	 *
	 * Handle classes that implement a specific interface.
	 */
	public function handleInterface($interface_name, Handler $handler) {
		if(!interface_exists($interface_name, FALSE)) {
			throw new InvalidArgumentException(
				"TypeHandler::handleInterface() expects first parameter to ".
				"be valid interface name. Interface [{$class_name}] does not ".
				"exist."
			);
		}
		
		$this->_handlers['object'][$interface_name] = $handler;
	}
	
	/**
	 * $h->getHandler(mixed) -> {Handler, NULL}
	 *
	 * Given a variable of any type, try to find a suitable handler.
	 */
	public function getHandler($obj) {
		$type = gettype($obj);
		
		if($type == 'object') {
			
			if(empty($this->_handlers['object']))
				return NULL;
			
			foreach($this->_handlers['object'] as $class => $handler) {
				if(get_class($obj) == $class || is_subclass_of($obj, $class))
					return $handler;
				else {
					$interfaces = class_implements($obj, FALSE);
					if(in_array($class, $interfaces))
						return $handler;
				}
			}
			
			return NULL;
		}
		
		return isset($this->_handlers[$type]) ? $this->_handlers[$type] : NULL;
	}
}
