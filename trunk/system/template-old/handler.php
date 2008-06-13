<?php

/**
 * Simple class whose responsibility it is to pool objects and delegate 
 * responsibilities to them.
 * @author Peter Goodman
 */
class Handler {
	private $store = array();
		
	public function get($key) {
		return isset($this->store[$key]) ? $this->store[$key] : NULL;
	}
	
	public function set($key, stdClass &$obj) {	
		$this->store[$key] = &$obj;
	}
}
