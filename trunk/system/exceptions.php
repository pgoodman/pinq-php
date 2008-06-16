<?php

/* $Id$ */

!defined('DIR_SYSTEM') && exit();

class PinqException extends Exception { }

class StackException extends PinqException { }
class QueueException extends PinqException { }
class ParserException extends PinqException { }
class PrinterException extends PinqException { }
class HandlerException extends PinqException { }
class ImmutableException extends PinqException { }
class CompositionException extends PinqException { }
class InvalidStateException extends PinqException { }
class ModelException extends PinqException { }
class InvalidPackageException extends PinqException { }
class FlushBufferException extends PinqException { }
class ConfigurationException extends PinqException { }

class HttpRequestException extends PinqException {
	public function __construct($code, $message) {
		parent::__construct();
		
	}
}

class HttpRedirectException extends PinqException { }

