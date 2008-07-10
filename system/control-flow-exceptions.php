<?php

/* $Id: */

!defined('DIR_SYSTEM') && exit();

/**
 * yield($route) -> void
 *
 * Yield control to another controller's action by passing in a route to that
 * controller's action. This function works by throwing a new YieldControlException
 * exception.
 *
 * @note The action being called will be called using the same request method
 *       as the current action.
 * @author Peter Goodman
 */
function yield($route = NULL) {
	if(NULL === $route)
		$route = get_route();
	
	throw new YieldControlException($route);
}

/**
 * Exception to yield control of the current process to another controller.
 *
 * @author Peter Goodman
 */
class YieldControlException extends PinqException {
	
	protected $_route;
	
	/**
	 * YieldControlException(route) <==> yield(route)
	 */
	public function __construct($route) {
		$this->_route = $route;
	}
	
	/**
	 * $e->getRoute(void) -> string
	 *
	 * Get the route to a controller action to change control to.
	 */
	public function getRoute() {
		return $this->_route;
	}
}

/**
 * Exception that yields control to a 500 Internal Server Error page.
 *
 * @author Peter Goodman
 */
class InternalErrorException extends YieldControlException {
	public function __construct($message) {
		parent::__construct(ERROR_500);
		$this->message = $message;
	}
}

/**
 * Exception that contains an array of generic validation errors. This class
 * extends YieldControlException and yields control to the /error/validation
 * route.
 *
 * @see YieldControlException
 * @author Peter Goodman
 */
class FailedValidationException extends YieldControlException {
	
	protected $_errors;
	
	/**
	 * ValidationException(array $errors)
	 */
	public function __construct(array $errors) {
		parent::__construct('/error/validation');
		$this->_errors = $errors;
		$this->message = "Data values failed to validate";
	}
	
	/**
	 * $e->getErrors(void) -> array
	 *
	 * Returns an associative multi-dimensional array mapping fields that had
	 * errors to and array of field-specific errors.
	 */
	public function getErrors() {
		return $this->_errors;
	}
}

/**
 * An exception representing an HTTP redirect.
 *
 * @author Peter Goodman
 */
class HttpRedirectException extends PinqException {
	
	protected $_location;
	
	/**
	 * HttpRedirectException($location, bool $as_url)
	 *
	 * @todo implement a distinction between $as_url = {FALSE, TRUE}
	 */
	public function __construct($location, $as_url) {
		
		// trim off any new lines + anything after them
		$location = preg_replace('~\r?\n.*~', '', $location);
		
		$this->_location = $location;
	}
	
	/**
	 * $e->getLocation(void) -> string
	 *
	 * Return where the redirect should go.
	 */
	public function getLocation() {
		return $this->_location;
	}
}