<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class StackException extends Exception { }
class QueueException extends Exception { }
class ParserException extends Exception { }
class PrinterException extends Exception { }
class HandlerException extends Exception { }
class ImmutableException extends Exception { }
class CompositionException extends Exception { }
class InvalidStateException extends Exception { }
class ModelException extends Exception { }
class InvalidPackageException extends Exception { }
class FlushBufferException extends Exception { }
class ConfigurationException extends Exception { }

class HttpRequestException extends Exception {
	public function __construct($code, $message) {
		parent::__construct();
		
	}
}

class HttpRedirectException extends Exception { }

