<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

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