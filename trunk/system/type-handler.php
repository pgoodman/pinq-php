<?php

class TypeHandler {
	
	protected $_handlers = array();
	
	public function handleScalar($type, Handler $handler) {
		$this->_handlers[$type] = $handler;
	}
	
	public function handleClass($class_name, Handler $handler) {
		if(!class_exists($class_name, FALSE)) {
			throw new InvalidArgumentException(
				"TypeHandler::handleClass() expects first parameter to be ".
				"valid class name. Class [{$class_name}] does not exist."
			);
		}
		
		$this->_handlers['object'][$class_name] = $handler;
	}
	
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
