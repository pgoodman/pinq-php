<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqException extends Exception { }

class StackException extends PinqException { }
class QueueException extends PinqException { }
//class ParserException extends PinqException { }
//class PrinterException extends PinqException { }
class HandlerException extends PinqException { }
class ImmutableException extends PinqException { }
//class CompositionException extends PinqException { }
class InvalidStateException extends PinqException { }
//class ModelException extends PinqException { }
class InvalidPackageException extends PinqException { }
class FlushBufferException extends PinqException { }
class ConfigurationException extends PinqException { }

/**
 * An exception representing an HTTP redirect.
 */
final class HttpRedirectException extends PinqException {
	
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
