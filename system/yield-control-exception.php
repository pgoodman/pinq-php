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
final class YieldControlException extends PinqException {
	public function __construct($route) {
		$this->_route = $route;
	}
	public function getRoute() {
		return $this->_route;
	}
}
