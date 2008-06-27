<?php

/* $Id: */

!defined('DIR_SYSTEM') && exit();

/**
 * Yield control to another controller.
 * @author Peter Goodman
 */
function yield($route = NULL) {
	if(NULL === $route)
		$route = get_route();
	
	throw new YieldControlException($route);
}

/**
 * Exception to handle moving to a new controller.
 * @author Peter Goodman
 */
class YieldControlException extends PinqException {
	
	protected $_route;
	
	public function __construct($route) {
		$this->_route = $route;
	}
	public function getRoute() {
		return $this->_route;
	}
}

/**
 * An exception representing an HTTP redirect.
 * @author Peter Goodman
 */
class HttpRedirectException extends PinqException {
	
	protected $_location;
	
	// TODO: implement a distinction between $as_url = {FALSE, TRUE}
	public function __construct($location, $as_url) {
		
		// trim off any new lines + anything after them
		$location = preg_replace('~\r?\n.*~', '', $location);
		
		$this->_location = $location;
	}
	
	public function getLocation() {
		return $this->_location;
	}
}