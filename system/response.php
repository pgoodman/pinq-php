<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

abstract class Response extends PinqException {
	
}

abstract class HttpResponse extends Response {
	public function __construct($http_status_code) {
		parent::__construct(NULL, $http_status_code);
		Http::setStatus($http_status_code);
	}
}

class HttpRedirectResponse extends HttpResponse {
	protected $_location;
	public function __construct($redirect_url) {
		parent::__construct(303);
		$this->_location = preg_replace('~\r?\n.*~s', '', $redirect_url);
	}
	public function getLocation() {
		return $this->_location;
	}
}

class OutputResponse extends Response {
	protected $_output;
	public function __construct($output) {
		$this->_output = $output;
	}
	public function __toString() {
		return $this->_output;
	}
}

class MetaResponse extends Response {
	
	protected $_route,
	          $_request_method;
	
	/**
	 * MetaResponse(string $route[, string $rquest_method]) <==> yield(...)
	 */
	public function __construct($route, $request_method = NULL) {
		$this->_route = $route;
		$this->_request_method = !$request_method ? Http::getRequestMethod() 
		                                          : $request_method;
	}
	
	/**
	 * $e->getRoute(void) -> string
	 *
	 * Get the route to a controller action to change control to.
	 */
	public function getRoute() {
		return $this->_route;
	}
	
	/**
	 * $e->getRequestMethod(void) -> string
	 *
	 * Get the request method to use for action of the next controller.
	 */
	public function getRequestMethod() {
		return $this->_request_method;
	}
}

class InternalErrorResponse extends MetaResponse {
	public function __construct($message) {
		parent::__construct(ERROR_500);
		$this->message = $message;
	}
}

/**
 * Exception that contains an array of generic validation errors. This class
 * extends MetaResponse and yields control to the /error/validation
 * route.
 *
 * @see InternalErrorResponse
 * @author Peter Goodman
 */
class FailedValidationException extends MetaResponse {
	
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
 * yield(string $route[, string $request_method]) ! MetaResponse
 *
 * Yield control to another controller's action by passing in a route to that
 * controller's action.
 *
 * @note The action being called will be called using the same request method
 *       as the current action.
 * @author Peter Goodman
 */
function yield($route, $request_method = NULL) {
	throw new MetaResponse($route, $request_method);
}

/**
 * redirect(string $location) ! HttpRedirectResponse
 *
 * Do a HTTP redirect. If $as_url is TRUE then it means we're redirecting to
 * a url and not a route.
 *
 * @author Peter Goodman
 */
function redirect($location) {
	
	// we throw an exception instead of redirecting so that when the exception
	// is caught we can tear down any existing resources properly
	throw new HttpRedirectResponse($location);
}

